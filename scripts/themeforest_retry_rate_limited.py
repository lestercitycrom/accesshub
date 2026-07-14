import asyncio
import random
import sys

import httpx

# Import after CLI arguments are already available. This installs the strict
# description-page processor used by the successful first pass.
import themeforest_description_extract as description

base = description.base

base.JINA_CONCURRENCY = 1
base.VERIFY_CONCURRENCY = 4
base.JINA_TIMEOUT = httpx.Timeout(120.0, connect=30.0)
base.VERIFY_TIMEOUT = httpx.Timeout(45.0, connect=20.0)


async def slow_fetch_jina(client: httpx.AsyncClient, item_url: str):
    reader_urls = [
        'https://r.jina.ai/' + item_url,
        'https://r.jina.ai/http://' + item_url.removeprefix('https://'),
    ]
    errors = []

    for round_no in range(10):
        for reader_url in reader_urls:
            try:
                response = await client.get(
                    reader_url,
                    follow_redirects=True,
                    headers={
                        'X-Return-Format': 'markdown',
                        'X-Timeout': '90',
                    },
                )
                text = response.text
                if (
                    response.status_code == 200
                    and len(text) > 4_000
                    and not base.CHALLENGE_RE.search(text)
                ):
                    # A small inter-request pause avoids immediately consuming
                    # the next allowance on the same runner IP.
                    await asyncio.sleep(1.2 + random.random() * 1.3)
                    return text, f'jina-retry:{round_no + 1}'

                errors.append(f'{response.status_code}:{len(text)}')

                retry_after = response.headers.get('retry-after')
                if response.status_code == 429 and retry_after:
                    try:
                        await asyncio.sleep(min(60.0, float(retry_after)))
                    except ValueError:
                        pass
            except Exception as exc:
                errors.append(type(exc).__name__)

        await asyncio.sleep(min(50.0, 4.0 + round_no * 5.0) + random.random() * 3.0)

    raise RuntimeError('item reader retry failed ' + ','.join(errors[-12:]))


base.fetch_jina = slow_fetch_jina


if __name__ == '__main__':
    asyncio.run(base.main())
