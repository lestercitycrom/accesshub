import asyncio
import csv
import json
import re
import sys
from dataclasses import asdict
from pathlib import Path
from urllib.parse import urljoin, urlparse

ORIGINAL_ARGV = sys.argv[:]
INPUT = Path(ORIGINAL_ARGV[1])
OUT = Path(ORIGINAL_ARGV[2] if len(ORIGINAL_ARGV) > 2 else 'output-browser')
LIMIT = int(ORIGINAL_ARGV[3]) if len(ORIGINAL_ARGV) > 3 else 1000
OUT.mkdir(parents=True, exist_ok=True)

sys.argv = [ORIGINAL_ARGV[0], str(OUT)]
import themeforest_clean_v2 as core
sys.argv = ORIGINAL_ARGV
core.OUT = OUT

from playwright.async_api import async_playwright, TimeoutError as PlaywrightTimeoutError

CONCURRENCY = 5
NAV_TIMEOUT = 45_000
CHALLENGE_TITLES = ('just a moment', 'attention required', 'security verification')
THEMEFOREST_HOSTS = ('themeforest.net', 'envato.com')
URL_RE = re.compile(r'https?://[^"\'<>\\\s]+', re.I)


def is_themeforest(url: str) -> bool:
    host = urlparse(url).netloc.lower()
    return any(domain in host for domain in THEMEFOREST_HOSTS)


def usable_external(url: str) -> bool:
    if not url or not url.startswith(('http://', 'https://')):
        return False
    host = urlparse(url).netloc.lower()
    if not host or is_themeforest(url):
        return False
    blocked = ('google.com/recaptcha', 'hcaptcha.com', 'challenges.cloudflare.com')
    return not any(value in url.lower() for value in blocked)


async def wait_out_challenge(page):
    for _ in range(12):
        try:
            title = (await page.title()).lower()
        except Exception:
            title = ''
        if not any(marker in title for marker in CHALLENGE_TITLES):
            return
        await page.wait_for_timeout(1_000)


async def frame_candidate(page):
    candidates = []
    for frame in page.frames:
        url = frame.url
        if frame == page.main_frame or not usable_external(url):
            continue
        candidates.append((url, frame))
    if candidates:
        candidates.sort(key=lambda pair: len(pair[0]), reverse=True)
        return candidates[0]

    selectors = (
        'iframe.full-screen-preview__frame',
        'iframe[name="preview-frame"]',
        'iframe[src]',
    )
    for selector in selectors:
        locator = page.locator(selector).first
        try:
            if await locator.count():
                raw = await locator.get_attribute('src')
                if raw:
                    url = urljoin(page.url, raw)
                    if usable_external(url):
                        return url, None
        except Exception:
            pass

    try:
        html = await page.content()
    except Exception:
        html = ''
    for match in URL_RE.findall(html):
        url = match.replace('\\/', '/')
        if usable_external(url):
            return url, None
    return '', None


async def goto_page(page, url: str):
    response = None
    try:
        response = await page.goto(url, wait_until='domcontentloaded', timeout=NAV_TIMEOUT)
    except PlaywrightTimeoutError:
        pass
    await wait_out_challenge(page)
    try:
        await page.wait_for_timeout(1_500)
    except Exception:
        pass
    return response


async def inspect_item(item, context, semaphore):
    result = core.Result(
        rank=int(item['rank']),
        item_id=int(item['item_id']),
        slug=item['slug'],
        themeforest_preview_url=item['live_preview_url'],
    )
    async with semaphore:
        page = await context.new_page()
        try:
            response = await goto_page(page, result.themeforest_preview_url)
            direct, frame = await frame_candidate(page)

            if not direct and usable_external(page.url):
                direct = page.url
                frame = page.main_frame

            if not direct:
                status = response.status if response else 0
                title = ''
                try:
                    title = await page.title()
                except Exception:
                    pass
                result.error = f'browser-frame-not-found HTTP {status} title={title[:120]}'
                return result

            if frame is None:
                await goto_page(page, direct)
                if usable_external(page.url):
                    direct = page.url
                frame = page.main_frame

            result.direct_demo_url = core.norm(direct)
            result.resolved_demo_url = core.norm(frame.url or direct)
            try:
                result.title = (await frame.title())[:240]
            except Exception:
                result.title = ''
            try:
                content = await frame.content()
            except Exception:
                content = ''

            if not content:
                result.error = 'empty demo frame content'
                return result

            home, classification, confidence, method = core.infer_home(
                content,
                result.resolved_demo_url,
            )
            result.clean_home_url = home
            result.classification = classification
            result.confidence = confidence
            result.method = 'browser:' + method

            check_page = page
            check_response = await goto_page(check_page, home)
            result.clean_status = check_response.status if check_response else 0
            if usable_external(check_page.url):
                result.clean_home_url = core.norm(check_page.url)
            if result.clean_status >= 400:
                result.error = f'clean-home HTTP {result.clean_status}'
        except Exception as exc:
            result.error = f'{type(exc).__name__}: {str(exc)[:350]}'
        finally:
            await page.close()
        return result


def write_outputs(results):
    results.sort(key=lambda item: item.rank)
    rows = [asdict(item) for item in results]
    if not rows:
        return
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
        and (item.clean_status == 0 or 200 <= item.clean_status < 400)
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


async def main():
    items = json.loads(INPUT.read_text(encoding='utf-8'))[:LIMIT]
    semaphore = asyncio.Semaphore(CONCURRENCY)
    async with async_playwright() as playwright:
        browser = await playwright.chromium.launch(
            headless=True,
            args=[
                '--disable-blink-features=AutomationControlled',
                '--disable-dev-shm-usage',
                '--no-sandbox',
            ],
        )
        context = await browser.new_context(
            viewport={'width': 1440, 'height': 900},
            locale='en-US',
            java_script_enabled=True,
            user_agent=(
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                'AppleWebKit/537.36 (KHTML, like Gecko) '
                'Chrome/140.0.0.0 Safari/537.36'
            ),
        )
        await context.add_init_script(
            "Object.defineProperty(navigator, 'webdriver', {get: () => undefined});"
        )

        async def route_handler(route):
            resource = route.request.resource_type
            if resource in ('image', 'media', 'font'):
                await route.abort()
            else:
                await route.continue_()

        await context.route('**/*', route_handler)
        tasks = [
            asyncio.create_task(inspect_item(item, context, semaphore))
            for item in items
        ]
        results = []
        for index, task in enumerate(asyncio.as_completed(tasks), 1):
            results.append(await task)
            if index % 5 == 0:
                direct = sum(bool(item.direct_demo_url) for item in results)
                print(f'processed {index}/{len(items)} direct={direct}', flush=True)
                write_outputs(results)
        await context.close()
        await browser.close()

    write_outputs(results)
    print((OUT / 'summary.json').read_text(encoding='utf-8'), flush=True)

if __name__ == '__main__':
    asyncio.run(main())
