import asyncio
import re
import sys
from urllib.parse import urlparse

# Import the first extractor with the same CLI arguments, then replace only
# candidate filtering/ranking. Its main() will use these patched functions.
import themeforest_item_reader_extract as base

ORIGINAL_IS_BLOCKED_URL = base.is_blocked_url
ORIGINAL_EXTRACT_CANDIDATES = base.extract_candidates

GENERIC_SLUG_WORDS = {
    'responsive', 'html', 'html5', 'template', 'theme', 'multipurpose',
    'bootstrap', 'website', 'site', 'admin', 'dashboard', 'landing',
    'ecommerce', 'commerce', 'modern', 'creative', 'business', 'the',
    'and', 'with', 'for', 'plus', 'mobile', 'app', 'web', 'design',
    'material', 'angular', 'angularjs', 'react', 'reactjs', 'vue', 'vuejs',
    'laravel', 'aspnet', 'core', 'redux', 'onepage', 'multi', 'page',
}
GENERIC_EXACT_HOSTS = {
    'monodesk.com', 'www.monodesk.com',
    'placeit.net', 'www.placeit.net',
    'angularjs.org', 'www.angularjs.org',
    'reactjs.org', 'www.reactjs.org',
    'vuejs.org', 'www.vuejs.org',
    'getbootstrap.com', 'www.getbootstrap.com',
    'jquery.com', 'www.jquery.com',
    'npmjs.com', 'www.npmjs.com',
    'medium.com', 'www.medium.com',
    'w3.org', 'www.w3.org',
}
NON_PAGE_EXT_RE = re.compile(
    r'\.(?:png|jpe?g|gif|webp|svg|ico|bmp|txt|pdf|zip|rar|7z|xml|json|md)(?:\?|$)',
    re.I,
)
DOCS_RE = re.compile(
    r'(?:^|[./_-])(?:docs?|documentation|changelog|support)(?:[./_-]|$)',
    re.I,
)


def primary_token(slug: str) -> str:
    for token in re.findall(r'[a-z0-9]{3,}', slug.lower()):
        if token not in GENERIC_SLUG_WORDS:
            return token
    tokens = re.findall(r'[a-z0-9]{3,}', slug.lower())
    return tokens[0] if tokens else ''


def is_blocked_url_v2(value: str) -> bool:
    parsed = urlparse(value)
    hostname = parsed.netloc.lower()
    if not hostname or parsed.scheme not in ('http', 'https'):
        return True
    if hostname in GENERIC_EXACT_HOSTS:
        return True
    if any(part in hostname for part in base.BLOCKED_HOST_PARTS):
        return True
    if NON_PAGE_EXT_RE.search(parsed.path):
        return True
    return False


def extract_number(reason: str, name: str) -> int:
    match = re.search(rf'{re.escape(name)}:(\d+)', reason)
    return int(match.group(1)) if match else 0


def extract_candidates_v2(markdown: str, slug: str, title: str):
    # The original scorer reads base.is_blocked_url dynamically. Temporarily
    # point that helper to the strict filter, but call the saved original
    # scorer to avoid recursive self-invocation.
    current_is_blocked = base.is_blocked_url
    base.is_blocked_url = is_blocked_url_v2
    try:
        candidates = ORIGINAL_EXTRACT_CANDIDATES(markdown, slug, title)
    finally:
        base.is_blocked_url = current_is_blocked

    primary = primary_token(slug)
    rescored = []
    for entry in candidates:
        url = entry['url']
        label = entry.get('label', '')
        blob = f'{url} {label}'.lower()
        host_name = urlparse(url).netloc.lower()
        path = urlparse(url).path.lower()
        tokens = set(re.findall(r'[a-z0-9]{3,}', blob))
        repeat = extract_number(entry.get('reasons', ''), 'exact-repeat')
        host_repeat = extract_number(entry.get('reasons', ''), 'host-repeat')
        primary_match = bool(primary and primary in tokens)
        demo_signal = bool(base.DEMO_WORD_RE.search(blob)) or any(
            word in host_name for word in ('demo', 'theme', 'preview', 'template')
        )

        score = int(entry['score'])
        reasons = entry.get('reasons', '')
        if primary_match:
            score += 90
            reasons += f'+primary:{primary}'
        else:
            score -= 38
            reasons += '+no-primary'

        if DOCS_RE.search(host_name) or DOCS_RE.search(path):
            score -= 130
            reasons += '+docs-penalty'
        if 'builder' in path and not primary_match:
            score -= 45
            reasons += '+builder-penalty'

        relevant = (
            primary_match
            or repeat >= 3
            or (host_repeat >= 5 and demo_signal)
        )
        if not relevant:
            score -= 80
            reasons += '+not-relevant'

        rescored.append({
            **entry,
            'score': score,
            'reasons': reasons.strip('+'),
            'relevant': relevant,
            'primary': primary,
        })

    return sorted(
        rescored,
        key=lambda item: (
            not item['relevant'],
            -item['score'],
            len(item['url']),
            item['position'],
        ),
    )


async def verify_candidates_v2(client, candidates):
    failures = []
    attempted = 0
    for entry in candidates[:14]:
        if not entry.get('relevant') or entry['score'] < 45:
            continue
        url = entry['url']
        attempted += 1
        try:
            response = await client.get(url, follow_redirects=True)
            resolved = base.normalize_url(str(response.url))
            parsed = urlparse(resolved)
            if response.status_code >= 400 or is_blocked_url_v2(resolved) or len(response.text) <= 300:
                failures.append(f'{response.status_code}:{url}')
                continue
            if DOCS_RE.search(parsed.netloc.lower()) or DOCS_RE.search(parsed.path.lower()):
                failures.append(f'docs:{url}')
                continue

            clean, classification, confidence, method = base.infer_clean_url(
                response.text,
                resolved,
            )
            clean_parsed = urlparse(clean)
            if is_blocked_url_v2(clean):
                failures.append(f'blocked-clean:{clean}')
                continue
            if DOCS_RE.search(clean_parsed.netloc.lower()) or DOCS_RE.search(clean_parsed.path.lower()):
                failures.append(f'docs-clean:{clean}')
                continue

            soup = base.BeautifulSoup(response.text, 'lxml')
            return {
                **entry,
                'resolved': resolved,
                'clean': clean,
                'status': response.status_code,
                'classification': classification,
                'confidence': confidence,
                'method': method,
                'title': soup.title.get_text(' ', strip=True)[:240] if soup.title else '',
            }
        except Exception as exc:
            failures.append(f'{type(exc).__name__}:{url}')
    if attempted == 0:
        raise RuntimeError('no relevant candidate above threshold')
    raise RuntimeError('no relevant candidate verified; ' + ' | '.join(failures[:12]))


base.is_blocked_url = is_blocked_url_v2
base.extract_candidates = extract_candidates_v2
base.verify_candidates = verify_candidates_v2

if __name__ == '__main__':
    asyncio.run(base.main())
