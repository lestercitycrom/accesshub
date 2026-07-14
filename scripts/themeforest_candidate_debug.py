import asyncio
import json
import sys
from pathlib import Path

# Reuse strict scorer without running its main routine.
import themeforest_item_reader_extract_v2 as strict

base = strict.base
INPUT = Path(sys.argv[1])
OUT = Path(sys.argv[2])
START = int(sys.argv[3]) if len(sys.argv) > 3 else 0
LIMIT = int(sys.argv[4]) if len(sys.argv) > 4 else 20
OUT.mkdir(parents=True, exist_ok=True)

async def main():
    items = json.loads(INPUT.read_text(encoding='utf-8'))[START:START + LIMIT]
    headers = {'User-Agent': base.UA, 'Accept': 'text/html,*/*'}
    limits = base.httpx.Limits(max_connections=5, max_keepalive_connections=5)
    results = []
    async with base.httpx.AsyncClient(headers=headers, timeout=base.JINA_TIMEOUT, limits=limits) as client:
        sem = asyncio.Semaphore(5)
        async def one(item):
            item_url = f"https://themeforest.net/item/{item['slug']}/{item['item_id']}"
            async with sem:
                try:
                    markdown, status = await base.fetch_jina(client, item_url)
                    candidates = base.extract_candidates(markdown, item['slug'], base.extract_title(markdown))
                    return {
                        'rank': item['rank'],
                        'slug': item['slug'],
                        'reader': status,
                        'title': base.extract_title(markdown),
                        'top_candidates': [
                            {
                                'url': c['url'],
                                'score': c['score'],
                                'relevant': c.get('relevant'),
                                'primary': c.get('primary'),
                                'label': c.get('label', '')[:180],
                                'reasons': c.get('reasons', ''),
                            }
                            for c in candidates[:20]
                        ],
                    }
                except Exception as exc:
                    return {'rank': item['rank'], 'slug': item['slug'], 'error': f'{type(exc).__name__}: {exc}'}
        tasks = [asyncio.create_task(one(item)) for item in items]
        for index, task in enumerate(asyncio.as_completed(tasks), 1):
            results.append(await task)
            print(f'debug {index}/{len(items)}', flush=True)
    results.sort(key=lambda x: x['rank'])
    (OUT / 'candidate-debug.json').write_text(json.dumps(results, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')

if __name__ == '__main__':
    asyncio.run(main())
