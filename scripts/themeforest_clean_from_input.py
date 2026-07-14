import asyncio
import csv
import json
import sys
from dataclasses import asdict
from pathlib import Path

import httpx
import themeforest_clean_v2 as core

INPUT = Path(sys.argv[1])
OUT = Path(sys.argv[2] if len(sys.argv) > 2 else 'output')
OUT.mkdir(parents=True, exist_ok=True)
core.OUT = OUT

async def main():
    items = json.loads(INPUT.read_text(encoding='utf-8'))
    if len(items) != 1000:
        raise RuntimeError(f'Expected 1000 input items, got {len(items)}')
    if len({int(x['item_id']) for x in items}) != 1000:
        raise RuntimeError('Input contains duplicate item IDs')

    headers = {
        'User-Agent': core.UA,
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language': 'en-US,en;q=0.8',
        'Cache-Control': 'no-cache',
    }
    limits = httpx.Limits(
        max_connections=core.CONCURRENCY + 8,
        max_keepalive_connections=core.CONCURRENCY + 4,
    )
    sem = asyncio.Semaphore(core.CONCURRENCY)
    async with httpx.AsyncClient(
        headers=headers,
        timeout=core.TIMEOUT,
        limits=limits,
        verify=True,
    ) as client:
        tasks = [
            asyncio.create_task(core.process_one(item, client, sem))
            for item in items
        ]
        results = []
        for index, task in enumerate(asyncio.as_completed(tasks), 1):
            results.append(await task)
            if index % 25 == 0:
                direct = sum(bool(r.direct_demo_url) for r in results)
                clean = sum(bool(r.clean_home_url) for r in results)
                print(
                    f'processed {index}/1000 direct={direct} clean={clean}',
                    flush=True,
                )

    results.sort(key=lambda item: item.rank)
    rows = [asdict(item) for item in results]
    fields = list(rows[0].keys())

    (OUT / 'themeforest-clean-demo-pages.json').write_text(
        json.dumps(rows, ensure_ascii=False, indent=2) + '\n',
        encoding='utf-8',
    )
    with (OUT / 'themeforest-clean-demo-pages.csv').open(
        'w', encoding='utf-8-sig', newline=''
    ) as file:
        writer = csv.DictWriter(file, fieldnames=fields)
        writer.writeheader()
        writer.writerows(rows)

    verified = [
        item for item in results
        if item.clean_home_url
        and 200 <= item.clean_status < 400
        and item.confidence in ('high', 'medium')
    ]
    review = [item for item in results if item not in verified]

    (OUT / 'themeforest-clean-demo-pages.txt').write_text(
        '\n'.join(item.clean_home_url for item in verified)
        + ('\n' if verified else ''),
        encoding='utf-8',
    )
    with (OUT / 'themeforest-needs-review.csv').open(
        'w', encoding='utf-8-sig', newline=''
    ) as file:
        writer = csv.DictWriter(file, fieldnames=fields)
        writer.writeheader()
        writer.writerows(asdict(item) for item in review)

    summary = {
        'total': len(results),
        'direct_extracted': sum(bool(item.direct_demo_url) for item in results),
        'verified_high_medium': len(verified),
        'high': sum(item.confidence == 'high' for item in results),
        'medium': sum(item.confidence == 'medium' for item in results),
        'low': sum(item.confidence == 'low' for item in results),
        'none': sum(item.confidence == 'none' for item in results),
        'errors': sum(bool(item.error) for item in results),
        'needs_review': len(review),
    }
    (OUT / 'summary.json').write_text(
        json.dumps(summary, indent=2) + '\n', encoding='utf-8'
    )
    print(json.dumps(summary, indent=2), flush=True)

if __name__ == '__main__':
    asyncio.run(main())
