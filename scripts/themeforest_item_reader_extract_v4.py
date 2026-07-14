import asyncio
import re
from urllib.parse import parse_qs, quote_plus, unquote, urlparse, urlunparse

import themeforest_item_reader_extract_v3 as v3

base = v3.base
ORIGINAL_PROCESS_V3 = base.process
ORIGINAL_DERIVED = v3.derived_parent_candidates

PRODUCT_BAD_PATH_RE = re.compile(r'/(?:forums?|faqs?|support)(?:/|$)', re.I)
DOC_SEGMENT_RE = re.compile(r'(?:docs?|documentation|changelog)', re.I)
QUALIFIER_WORDS = (
    'admin', 'react', 'angular', 'vue', 'laravel', 'aspnet',
    'ecommerce', 'shop', 'dashboard',
)
SEARCH_SEMAPHORE = asyncio.Semaphore(2)


def path_depth(url):
    return len([part for part in urlparse(url).path.split('/') if part])


def qualifiers(slug):
    parts = set(re.findall(r'[a-z0-9]+', slug.lower()))
    return [word for word in QUALIFIER_WORDS if word in parts]


def product_specific(url, primary):
    parsed = urlparse(url)
    blob = f'{parsed.netloc}{parsed.path}'.lower()
    return bool(primary and primary in blob)


def build_parent(parsed, segments):
    path = '/' + '/'.join(segments)
    if not path.endswith('/'):
        path += '/'
    return base.normalize_url(urlunparse((parsed.scheme or 'https', parsed.netloc, path, '', '', '')))


def derived_parent_candidates_v4(entry, primary):
    parsed = urlparse(entry['url'])
    segments = [segment for segment in parsed.path.split('/') if segment]
    lower = [segment.lower().split('.')[0] for segment in segments]
    results = []

    for index, segment in enumerate(lower):
        if not DOC_SEGMENT_RE.search(segment):
            continue
        parent_segments = segments[:index]
        parent = build_parent(parsed, parent_segments) if parent_segments else base.normalize_url(f'https://{parsed.netloc}/')
        if product_specific(parent, primary):
            results.append(parent)
        elif primary and primary in parsed.netloc.lower():
            results.append(base.normalize_url(f'https://{parsed.netloc}/'))

    # A changelog/documentation file often lives directly inside the product root.
    if segments and '.' in segments[-1] and DOC_SEGMENT_RE.search(segments[-1]):
        parent = build_parent(parsed, segments[:-1]) if segments[:-1] else base.normalize_url(f'https://{parsed.netloc}/')
        if product_specific(parent, primary) or (primary and primary in parsed.netloc.lower()):
            results.append(parent)

    unique = []
    seen = set()
    for url in results:
        if url in seen or v3.strict.is_blocked_url_v2(url):
            continue
        seen.add(url)
        unique.append({
            **entry,
            'url': url,
            'score': max(65, int(entry.get('score', 0)) + 45),
            'reasons': entry.get('reasons', '') + '+safe-derived-parent',
            'relevant': True,
        })
    return unique


v3.derived_parent_candidates = derived_parent_candidates_v4


def decode_ddg(raw):
    if raw.startswith('//'):
        raw = 'https:' + raw
    parsed = urlparse(raw)
    if 'duckduckgo.com' in parsed.netloc and parsed.path.startswith('/l/'):
        return unquote(parse_qs(parsed.query).get('uddg', [''])[0])
    return raw


def parse_search_results(html, item, title):
    soup = base.BeautifulSoup(html, 'lxml')
    primary = v3.strict.primary_token(item['slug'])
    wanted_qualifiers = qualifiers(item['slug'])
    results = []
    anchors = soup.select('a.result__a') or soup.select('a.result-link')
    for rank, anchor in enumerate(anchors, 1):
        url = base.normalize_url(decode_ddg(anchor.get('href', '')))
        if not url.startswith(('http://', 'https://')) or v3.strict.is_blocked_url_v2(url):
            continue
        hostname = urlparse(url).netloc.lower()
        if any(blocked in hostname for blocked in v3.SEARCH_BLOCKED):
            continue
        label = anchor.get_text(' ', strip=True)
        blob = f'{url} {label}'.lower()
        if not primary or primary not in blob:
            continue

        score = 220 - min(rank, 25) * 7
        if any(word in blob for word in ('demo', 'preview', 'theme', 'template')):
            score += 35
        for word in wanted_qualifiers:
            if word in blob:
                score += 45
            elif word in ('admin', 'react', 'angular', 'vue', 'laravel', 'aspnet'):
                score -= 35
        if urlparse(url).path in ('', '/'):
            score += 10
        if v3.strict.DOCS_RE.search(hostname) or v3.strict.DOCS_RE.search(urlparse(url).path.lower()):
            score -= 65

        entry = {
            'url': url,
            'label': label,
            'score': score,
            'reasons': f'ddg-v4-rank:{rank}+primary:{primary}',
            'position': rank,
            'relevant': True,
            'primary': primary,
        }
        results.append(entry)
        results.extend(derived_parent_candidates_v4(entry, primary))

    by_url = {}
    for entry in results:
        old = by_url.get(entry['url'])
        if old is None or entry['score'] > old['score']:
            by_url[entry['url']] = entry
    return sorted(by_url.values(), key=lambda x: (-x['score'], len(x['url'])))


async def fetch_search_page(client, query):
    urls = [
        'https://html.duckduckgo.com/html/?kl=us-en&q=' + quote_plus(query),
        'https://lite.duckduckgo.com/lite/?kl=us-en&q=' + quote_plus(query),
    ]
    errors = []
    for round_no in range(3):
        for url in urls:
            try:
                async with SEARCH_SEMAPHORE:
                    response = await client.get(url, follow_redirects=True)
                if response.status_code == 200 and len(response.text) > 4_000 and 'result' in response.text.lower():
                    return response.text
                errors.append(f'{response.status_code}:{len(response.text)}')
            except Exception as exc:
                errors.append(type(exc).__name__)
        await asyncio.sleep(2.0 * (round_no + 1))
    raise RuntimeError('search unavailable ' + ','.join(errors[-6:]))


async def search_fallback_v4(item, result, client):
    title = result.item_title or item['slug'].replace('-', ' ')
    primary = v3.strict.primary_token(item['slug'])
    query_candidates = [
        f'"{title}" demo',
        f'{primary} {" ".join(qualifiers(item["slug"]))} template live demo',
        f'{item["slug"].replace("-", " ")} demo',
    ]
    all_candidates = []
    for query in query_candidates:
        html = await fetch_search_page(client, query)
        all_candidates.extend(parse_search_results(html, item, title))
        if len(all_candidates) >= 5:
            break

    by_url = {}
    for candidate in all_candidates:
        old = by_url.get(candidate['url'])
        if old is None or candidate['score'] > old['score']:
            by_url[candidate['url']] = candidate
    candidates = sorted(by_url.values(), key=lambda x: (-x['score'], len(x['url'])))
    if not candidates:
        raise RuntimeError('search returned no relevant official candidate')
    return await v3.verify_candidates_v3(client, candidates)


def preserve_product_path(result, item):
    if not result.clean_demo_url or not result.resolved_url:
        return result
    primary = v3.strict.primary_token(item['slug'])
    if (
        product_specific(result.resolved_url, primary)
        and not product_specific(result.clean_demo_url, primary)
        and path_depth(result.clean_demo_url) < path_depth(result.resolved_url)
    ):
        result.clean_demo_url = result.resolved_url
        result.classification = 'demo_selector' if path_depth(result.resolved_url) <= 2 else 'direct_home'
        result.confidence = 'high'
        result.candidate_method += '+preserve-product-path'
    return result


async def process_v4(item, jina_client, verify_client, jina_sem, verify_sem):
    result = await ORIGINAL_PROCESS_V3(item, jina_client, verify_client, jina_sem, verify_sem)
    invalid = bool(result.candidate_url and PRODUCT_BAD_PATH_RE.search(urlparse(result.candidate_url).path))
    if result.clean_demo_url and not invalid:
        return preserve_product_path(result, item)

    first_error = result.error
    try:
        async with verify_sem:
            chosen = await search_fallback_v4(item, result, verify_client)
        result.candidate_url = chosen['url']
        result.candidate_score = chosen['score']
        result.candidate_method = chosen['reasons'] + '+' + chosen['method'] + '+search-v4'
        result.resolved_url = chosen['resolved']
        result.clean_demo_url = chosen['clean']
        result.http_status = chosen['status']
        result.classification = chosen['classification']
        result.confidence = chosen['confidence']
        result.page_title = chosen['title']
        result.error = ''
        return preserve_product_path(result, item)
    except Exception as exc:
        result.clean_demo_url = '' if invalid else result.clean_demo_url
        result.error = f'{first_error}; v4={type(exc).__name__}: {str(exc)[:500]}'
        return result


base.process = process_v4

if __name__ == '__main__':
    asyncio.run(base.main())
