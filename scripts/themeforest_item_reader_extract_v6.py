import asyncio
import re
from urllib.parse import quote_plus, urlparse

import themeforest_item_reader_extract_v5 as v5

base = v5.base
DESCRIPTION_ONLY_PROCESS = v5.DESCRIPTION_ONLY_PROCESS
SEARCH_SEMAPHORE = asyncio.Semaphore(2)

AUTHOR_RE = re.compile(
    r'(?:\bBy\s+|\bby\s+)?\[([^\]]+)\]\(https?://themeforest\.net/user/([^/)]+)',
    re.I,
)
RESULT_MARKERS = ('result__a', 'result-link')


def extract_author(markdown):
    # Prefer an explicitly introduced author link.
    explicit = re.search(
        r'(?:\bBy\s+|\bby\s+)\[([^\]]+)\]\(https?://themeforest\.net/user/([^/)]+)',
        markdown,
        re.I,
    )
    if explicit:
        return explicit.group(2).lower()
    match = AUTHOR_RE.search(markdown)
    return match.group(2).lower() if match else ''


async def fetch_search_page_v6(client, query):
    urls = [
        'https://html.duckduckgo.com/html/?kl=us-en&q=' + quote_plus(query),
        'https://lite.duckduckgo.com/lite/?kl=us-en&q=' + quote_plus(query),
    ]
    errors = []
    for round_no in range(4):
        for url in urls:
            try:
                async with SEARCH_SEMAPHORE:
                    response = await client.get(url, follow_redirects=True)
                lower = response.text.lower()
                if response.status_code < 400 and len(response.text) > 4_000 and any(marker in lower for marker in RESULT_MARKERS):
                    return response.text
                errors.append(f'{response.status_code}:{len(response.text)}')
            except Exception as exc:
                errors.append(type(exc).__name__)
        await asyncio.sleep(2.5 * (round_no + 1))
    raise RuntimeError('search unavailable ' + ','.join(errors[-8:]))


def author_rescore(candidates, author):
    if not author:
        return candidates
    author_parts = [part for part in re.findall(r'[a-z0-9]{3,}', author.lower()) if part not in {'theme', 'themes', 'studio'}]
    rescored = []
    for candidate in candidates:
        copied = dict(candidate)
        blob = f"{copied['url']} {copied.get('label', '')}".lower()
        matched = any(part in blob for part in author_parts) if author_parts else author in blob
        if matched:
            copied['score'] += 110
            copied['reasons'] += f'+author:{author}'
        else:
            copied['score'] -= 65
            copied['reasons'] += '+author-missing'
        rescored.append(copied)
    return sorted(rescored, key=lambda x: (-x['score'], len(x['url'])))


async def search_fallback_v6(item, result, jina_client, verify_client, jina_sem):
    title = result.item_title or item['slug'].replace('-', ' ')
    author = ''
    try:
        async with jina_sem:
            markdown, _ = await base.fetch_jina(jina_client, result.item_url)
        author = extract_author(markdown)
        if not result.item_title:
            title = base.extract_title(markdown) or title
    except Exception:
        pass

    primary = v5.v4.v3.strict.primary_token(item['slug'])
    qualifier_text = ' '.join(v5.v4.qualifiers(item['slug']))
    queries = [
        f'"{title}" {author} demo'.strip(),
        f'{primary} {author} {qualifier_text} template live demo'.strip(),
        f'{item["slug"].replace("-", " ")} {author} demo'.strip(),
    ]

    all_candidates = []
    for query in queries:
        html = await fetch_search_page_v6(verify_client, query)
        candidates = v5.v4.parse_search_results(html, item, title)
        all_candidates.extend(author_rescore(candidates, author))
        if len(all_candidates) >= 7:
            break

    by_url = {}
    for candidate in all_candidates:
        old = by_url.get(candidate['url'])
        if old is None or candidate['score'] > old['score']:
            by_url[candidate['url']] = candidate
    candidates = sorted(by_url.values(), key=lambda x: (-x['score'], len(x['url'])))
    if not candidates:
        raise RuntimeError('search returned no relevant candidate')
    return await v5.v4.v3.verify_candidates_v3(verify_client, candidates)


async def process_v6(item, jina_client, verify_client, jina_sem, verify_sem):
    result = await DESCRIPTION_ONLY_PROCESS(
        item,
        jina_client,
        verify_client,
        jina_sem,
        verify_sem,
    )
    invalid = bool(
        result.candidate_url
        and v5.v4.PRODUCT_BAD_PATH_RE.search(urlparse(result.candidate_url).path)
    )
    if result.clean_demo_url and not invalid:
        return v5.v4.preserve_product_path(result, item)

    first_error = result.error
    try:
        async with verify_sem:
            chosen = await search_fallback_v6(
                item,
                result,
                jina_client,
                verify_client,
                jina_sem,
            )
        result.candidate_url = chosen['url']
        result.candidate_score = chosen['score']
        result.candidate_method = (
            chosen['reasons'] + '+' + chosen['method'] + '+author-search-v6'
        )
        result.resolved_url = chosen['resolved']
        result.clean_demo_url = chosen['clean']
        result.http_status = chosen['status']
        result.classification = chosen['classification']
        result.confidence = chosen['confidence']
        result.page_title = chosen['title']
        result.error = ''
        return v5.v4.preserve_product_path(result, item)
    except Exception as exc:
        if invalid:
            result.clean_demo_url = ''
        result.error = (
            f'{first_error}; author-search={type(exc).__name__}: {str(exc)[:650]}'
        )
        return result


base.process = process_v6

if __name__ == '__main__':
    asyncio.run(base.main())
