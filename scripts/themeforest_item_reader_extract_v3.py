import asyncio
import re
from urllib.parse import parse_qs, quote_plus, unquote, urlparse, urlunparse

import themeforest_item_reader_extract_v2 as strict

base = strict.base
ORIGINAL_PROCESS = base.process
ORIGINAL_EXTRACT = base.extract_candidates

SEARCH_BLOCKED = (
    'stylelib.org', 'sourceforest.net', 'themeplace.pro', 'templatelelo.com',
    'amesome-templates.com', 'reactemplates.com', 'tem-pest.com',
    'swiftpod.io', 'reptipare.com', 'bluew.net', 'lcmc.net',
    'nulled', 'download', 'free-template', 'freebies', 'cracked',
)
SEARCH_SEMAPHORE = asyncio.Semaphore(4)
DOC_SEGMENTS = {'doc', 'docs', 'documentation', 'changelog', 'support', 'forums', 'forum', 'faq', 'faqs'}


def build_url(parsed, segments):
    path = '/' + '/'.join(segments)
    if not path.endswith('/'):
        path += '/'
    return base.normalize_url(urlunparse((parsed.scheme or 'https', parsed.netloc, path, '', '', '')))


def derived_parent_candidates(entry, primary):
    url = entry['url']
    parsed = urlparse(url)
    segments = [segment for segment in parsed.path.split('/') if segment]
    results = []
    lower_segments = [segment.lower().split('.')[0] for segment in segments]

    cut_points = []
    for index, segment in enumerate(lower_segments):
        if segment in DOC_SEGMENTS or any(word in segment for word in ('doc', 'changelog', 'support', 'forum')):
            cut_points.append(index)
    if segments and '.' in segments[-1]:
        cut_points.append(len(segments) - 1)

    for cut in sorted(set(cut_points)):
        parent_segments = segments[:cut]
        if parent_segments:
            results.append(build_url(parsed, parent_segments))
        # Also test the host root when the brand is embedded in the hostname.
        if primary and primary in parsed.netloc.lower():
            results.append(base.normalize_url(f'https://{parsed.netloc}/'))

    unique = []
    seen = set()
    for candidate in results:
        if candidate in seen or strict.is_blocked_url_v2(candidate):
            continue
        seen.add(candidate)
        unique.append({
            **entry,
            'url': candidate,
            'score': max(55, int(entry.get('score', 0)) + 35),
            'reasons': entry.get('reasons', '') + '+derived-parent',
            'relevant': True,
        })
    return unique


def extract_candidates_v3(markdown, slug, title):
    candidates = ORIGINAL_EXTRACT(markdown, slug, title)
    primary = strict.primary_token(slug)
    enhanced = []
    for entry in candidates:
        blob = f"{entry['url']} {entry.get('label', '')}".lower()
        copied = dict(entry)
        if primary and primary in blob and not copied.get('relevant'):
            copied['score'] = int(copied['score']) + 95
            copied['reasons'] = copied.get('reasons', '') + f'+substring-primary:{primary}'
            copied['relevant'] = True
        enhanced.append(copied)
        if primary and primary in blob:
            enhanced.extend(derived_parent_candidates(copied, primary))

    by_url = {}
    for entry in enhanced:
        old = by_url.get(entry['url'])
        if old is None or entry['score'] > old['score']:
            by_url[entry['url']] = entry
    return sorted(
        by_url.values(),
        key=lambda item: (not item.get('relevant', False), -item['score'], len(item['url'])),
    )


async def verify_candidates_v3(client, candidates):
    failures = []
    attempted = 0
    for entry in candidates[:24]:
        if not entry.get('relevant') or entry['score'] < 25:
            continue
        url = entry['url']
        if any(domain in urlparse(url).netloc.lower() for domain in SEARCH_BLOCKED):
            continue
        attempted += 1
        try:
            response = await client.get(url, follow_redirects=True)
            resolved = base.normalize_url(str(response.url))
            parsed = urlparse(resolved)
            if response.status_code >= 400 or strict.is_blocked_url_v2(resolved) or len(response.text) <= 300:
                failures.append(f'{response.status_code}:{url}')
                continue
            if strict.DOCS_RE.search(parsed.netloc.lower()) or strict.DOCS_RE.search(parsed.path.lower()):
                failures.append(f'docs:{url}')
                continue

            clean, classification, confidence, method = base.infer_clean_url(response.text, resolved)
            clean_parsed = urlparse(clean)
            if strict.is_blocked_url_v2(clean):
                failures.append(f'blocked-clean:{clean}')
                continue
            if strict.DOCS_RE.search(clean_parsed.netloc.lower()) or strict.DOCS_RE.search(clean_parsed.path.lower()):
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
    raise RuntimeError('no candidate verified; ' + ' | '.join(failures[:14]))


def decode_ddg_url(raw):
    if raw.startswith('//'):
        raw = 'https:' + raw
    parsed = urlparse(raw)
    if 'duckduckgo.com' in parsed.netloc and parsed.path.startswith('/l/'):
        value = parse_qs(parsed.query).get('uddg', [''])[0]
        return unquote(value)
    return raw


def search_candidates(html, slug, title):
    soup = base.BeautifulSoup(html, 'lxml')
    primary = strict.primary_token(slug)
    results = []
    for rank, anchor in enumerate(soup.select('a.result__a'), 1):
        raw = anchor.get('href', '')
        url = base.normalize_url(decode_ddg_url(raw))
        if not url.startswith(('http://', 'https://')) or strict.is_blocked_url_v2(url):
            continue
        hostname = urlparse(url).netloc.lower()
        if any(blocked in hostname for blocked in SEARCH_BLOCKED):
            continue
        label = anchor.get_text(' ', strip=True)
        blob = f'{url} {label}'.lower()
        primary_match = bool(primary and primary in blob)
        if not primary_match:
            continue
        score = 180 - min(rank, 20) * 6
        if any(word in blob for word in ('demo', 'preview', 'theme', 'template')):
            score += 35
        if urlparse(url).path in ('', '/'):
            score += 15
        if strict.DOCS_RE.search(hostname) or strict.DOCS_RE.search(urlparse(url).path.lower()):
            score -= 80
        entry = {
            'url': url,
            'label': label,
            'score': score,
            'reasons': f'ddg-rank:{rank}+primary:{primary}',
            'position': rank,
            'relevant': True,
            'primary': primary,
        }
        results.append(entry)
        results.extend(derived_parent_candidates(entry, primary))

    by_url = {}
    for entry in results:
        old = by_url.get(entry['url'])
        if old is None or entry['score'] > old['score']:
            by_url[entry['url']] = entry
    return sorted(by_url.values(), key=lambda item: (-item['score'], len(item['url'])))


async def ddg_fallback(item, result, verify_client):
    title = result.item_title or item['slug'].replace('-', ' ')
    query = f'"{title}" live demo'
    url = 'https://html.duckduckgo.com/html/?q=' + quote_plus(query)
    async with SEARCH_SEMAPHORE:
        response = await verify_client.get(url, follow_redirects=True)
    if response.status_code >= 400 or len(response.text) < 2_000:
        raise RuntimeError(f'DDG HTTP {response.status_code}')
    candidates = search_candidates(response.text, item['slug'], title)
    if not candidates:
        # A less exact query helps old templates whose titles changed.
        query = f"{strict.primary_token(item['slug'])} {item['slug'].replace('-', ' ')} template demo"
        url = 'https://html.duckduckgo.com/html/?q=' + quote_plus(query)
        async with SEARCH_SEMAPHORE:
            response = await verify_client.get(url, follow_redirects=True)
        candidates = search_candidates(response.text, item['slug'], title)
    return await verify_candidates_v3(verify_client, candidates)


async def process_v3(item, jina_client, verify_client, jina_sem, verify_sem):
    result = await ORIGINAL_PROCESS(item, jina_client, verify_client, jina_sem, verify_sem)
    if result.clean_demo_url:
        return result
    first_error = result.error
    try:
        async with verify_sem:
            chosen = await ddg_fallback(item, result, verify_client)
        result.candidate_url = chosen['url']
        result.candidate_score = chosen['score']
        result.candidate_method = chosen['reasons'] + '+' + chosen['method'] + '+search-fallback'
        result.resolved_url = chosen['resolved']
        result.clean_demo_url = chosen['clean']
        result.http_status = chosen['status']
        result.classification = chosen['classification']
        result.confidence = chosen['confidence']
        result.page_title = chosen['title']
        result.error = ''
    except Exception as exc:
        result.error = f'{first_error}; fallback={type(exc).__name__}: {str(exc)[:420]}'
    return result


base.extract_candidates = extract_candidates_v3
base.verify_candidates = verify_candidates_v3
base.process = process_v3

if __name__ == '__main__':
    asyncio.run(base.main())
