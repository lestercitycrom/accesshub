import asyncio
import re
from urllib.parse import urlparse

import themeforest_item_reader_extract_v5 as v5

base = v5.base
AUTHOR_RE = re.compile(
    r'(?:\bBy\s+|\bby\s+)?\[([^\]]+)\]\(https?://themeforest\.net/user/([^/)]+)',
    re.I,
)


def extract_author(markdown):
    explicit = re.search(
        r'(?:\bBy\s+|\bby\s+)\[([^\]]+)\]\(https?://themeforest\.net/user/([^/)]+)',
        markdown,
        re.I,
    )
    if explicit:
        return explicit.group(2).lower()
    match = AUTHOR_RE.search(markdown)
    return match.group(2).lower() if match else ''


async def process_description(item, jina_client, verify_client, jina_sem, verify_sem):
    item_url = f"https://themeforest.net/item/{item['slug']}/{item['item_id']}"
    result = base.Result(
        rank=int(item['rank']),
        item_id=int(item['item_id']),
        slug=item['slug'],
        item_url=item_url,
    )
    try:
        async with jina_sem:
            markdown, reader_status = await base.fetch_jina(jina_client, item_url)
        author = extract_author(markdown)
        result.item_page_status = reader_status + (f';author={author}' if author else '')
        result.item_title = base.extract_title(markdown)
        candidates = base.extract_candidates(markdown, result.slug, result.item_title)
        result.candidate_count = len(candidates)
        if not candidates:
            raise RuntimeError('no external description candidates')
        async with verify_sem:
            chosen = await base.verify_candidates(verify_client, candidates)
        result.candidate_url = chosen['url']
        result.candidate_score = chosen['score']
        result.candidate_method = chosen['reasons'] + '+' + chosen['method'] + '+description-only'
        result.resolved_url = chosen['resolved']
        result.clean_demo_url = chosen['clean']
        result.http_status = chosen['status']
        result.classification = chosen['classification']
        result.confidence = chosen['confidence']
        result.page_title = chosen['title']

        invalid = bool(v5.v4.PRODUCT_BAD_PATH_RE.search(urlparse(result.candidate_url).path))
        if invalid:
            result.clean_demo_url = ''
            result.classification = 'unresolved'
            result.confidence = 'none'
            result.error = 'description candidate is support/forum/FAQ, search required'
        else:
            result = v5.v4.preserve_product_path(result, item)
    except Exception as exc:
        result.error = f'{type(exc).__name__}: {str(exc)[:700]}'
    return result


base.process = process_description

if __name__ == '__main__':
    asyncio.run(base.main())
