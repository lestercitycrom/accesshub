import asyncio
from urllib.parse import urlparse

import themeforest_item_reader_extract_v4 as v4

base = v4.base
DESCRIPTION_ONLY_PROCESS = v4.v3.ORIGINAL_PROCESS


async def process_v5(item, jina_client, verify_client, jina_sem, verify_sem):
    # Run the item-description path once. Unlike v3/v4 chaining, this does not
    # invoke DuckDuckGo before the final fallback below.
    result = await DESCRIPTION_ONLY_PROCESS(
        item,
        jina_client,
        verify_client,
        jina_sem,
        verify_sem,
    )
    invalid = bool(
        result.candidate_url
        and v4.PRODUCT_BAD_PATH_RE.search(urlparse(result.candidate_url).path)
    )
    if result.clean_demo_url and not invalid:
        return v4.preserve_product_path(result, item)

    first_error = result.error
    try:
        async with verify_sem:
            chosen = await v4.search_fallback_v4(item, result, verify_client)
        result.candidate_url = chosen['url']
        result.candidate_score = chosen['score']
        result.candidate_method = (
            chosen['reasons'] + '+' + chosen['method'] + '+single-search-v5'
        )
        result.resolved_url = chosen['resolved']
        result.clean_demo_url = chosen['clean']
        result.http_status = chosen['status']
        result.classification = chosen['classification']
        result.confidence = chosen['confidence']
        result.page_title = chosen['title']
        result.error = ''
        return v4.preserve_product_path(result, item)
    except Exception as exc:
        if invalid:
            result.clean_demo_url = ''
        result.error = (
            f'{first_error}; search={type(exc).__name__}: {str(exc)[:600]}'
        )
        return result


base.process = process_v5

if __name__ == '__main__':
    asyncio.run(base.main())
