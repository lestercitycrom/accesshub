import asyncio
import csv
import html
import json
import re
import sys
from dataclasses import dataclass, asdict
from pathlib import Path
from urllib.parse import urljoin, urlparse, urlunparse

import httpx
from bs4 import BeautifulSoup

OUT = Path(sys.argv[1] if len(sys.argv) > 1 else 'output')
OUT.mkdir(parents=True, exist_ok=True)
TARGET = int(sys.argv[2]) if len(sys.argv) > 2 else 1000
CONCURRENCY = 16
TIMEOUT = httpx.Timeout(40.0, connect=20.0)
UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36'

PREVIEW_RE = re.compile(r'https?://(?:preview\.)?themeforest\.net/item/([^/?#)]+?)/full_screen_preview/(\d+)', re.I)
HOME_TEXT_RE = re.compile(r'^(home|homepage|main|start|demo|preview|головна|главная)$', re.I)
HOME_FILE_RE = re.compile(r'^(?:index|home|homepage|default|main)(?:[-_]?\d+)?\.(?:html?|php|aspx?)$', re.I)
INTERNAL_RE = re.compile(r'/(?:docs?|documentation|blog|article|post|single|product(?:-page)?|shop|cart|checkout|login|register|signup|404|coming-soon|contact|about|faq)(?:[/.\-_]|$)', re.I)
DEMO_LINK_RE = re.compile(r'\b(?:live\s*)?(?:demo|preview|view\s+demo|demo\s*\d+)\b', re.I)
WRAPPER_PATTERNS = [
    re.compile(r'<iframe[^>]+class=["\'][^"\']*full-screen-preview__frame[^"\']*["\'][^>]+src=["\']([^"\']+)', re.I),
    re.compile(r'<iframe[^>]+src=["\']([^"\']+)["\'][^>]+class=["\'][^"\']*full-screen-preview__frame', re.I),
    re.compile(r'<iframe[^>]+src=["\']([^"\']+)["\']', re.I),
    re.compile(r'"(?:preview_url|previewUrl|iframe_url|iframeUrl)"\s*:\s*"([^"]+)"', re.I),
]

@dataclass
class Result:
    rank: int
    item_id: int
    slug: str
    themeforest_preview_url: str
    direct_demo_url: str = ''
    resolved_demo_url: str = ''
    clean_home_url: str = ''
    clean_status: int = 0
    classification: str = 'unresolved'
    confidence: str = 'none'
    method: str = ''
    title: str = ''
    error: str = ''


def norm(url: str) -> str:
    url = html.unescape((url or '').strip()).replace('\\/', '/')
    p = urlparse(url)
    if not p.scheme:
        return url
    scheme = 'https' if p.scheme in ('http', 'https') else p.scheme
    return urlunparse((scheme, p.netloc.lower(), p.path or '/', p.params, p.query, ''))


def host(url: str) -> str:
    return urlparse(url).netloc.lower().split(':')[0].removeprefix('www.')


def same_host(a: str, b: str) -> bool:
    return host(a) == host(b)


def segments(url: str) -> list[str]:
    return [x for x in urlparse(url).path.split('/') if x]


def common_prefix_depth(a: str, b: str) -> int:
    aa, bb = segments(a), segments(b)
    n = 0
    for x, y in zip(aa, bb):
        if x != y:
            break
        n += 1
    return n


def extract_frame(text: str, base: str) -> str:
    soup = BeautifulSoup(text, 'lxml')
    for selector in ('iframe.full-screen-preview__frame', 'iframe[name="preview-frame"]', 'iframe[src]'):
        node = soup.select_one(selector)
        if node and node.get('src'):
            return norm(urljoin(base, node['src']))
    for rx in WRAPPER_PATTERNS:
        m = rx.search(text)
        if m:
            return norm(urljoin(base, m.group(1)))
    return ''


async def fetch(client: httpx.AsyncClient, url: str, tries: int = 4):
    last = None
    for attempt in range(tries):
        try:
            r = await client.get(url, follow_redirects=True)
            if r.status_code == 429:
                retry = int(r.headers.get('retry-after', '5') or 5)
                await asyncio.sleep(min(45, max(retry, 3 * (attempt + 1))))
                last = 'HTTP 429'
                continue
            if r.status_code >= 500 and attempt + 1 < tries:
                await asyncio.sleep(2 * (attempt + 1))
                last = f'HTTP {r.status_code}'
                continue
            return r
        except Exception as exc:
            last = str(exc)
            await asyncio.sleep(1.5 * (attempt + 1))
    raise RuntimeError(last or 'fetch failed')


async def collect_items(client: httpx.AsyncClient, target: int) -> list[dict]:
    items, seen = [], set()
    for page in range(1, 70):
        if len(items) >= target:
            break
        source = f'https://themeforest.net/category/site-templates?page={page}&sort=sales'
        proxy = 'https://r.jina.ai/' + source
        text = ''
        try:
            r = await fetch(client, source, tries=2)
            text = r.text
        except Exception:
            pass
        matches = list(PREVIEW_RE.finditer(text))
        if not matches:
            r = await fetch(client, proxy, tries=5)
            text = r.text
            matches = list(PREVIEW_RE.finditer(text))
        page_added = 0
        for m in matches:
            slug, item_id = m.group(1), int(m.group(2))
            if item_id in seen:
                continue
            seen.add(item_id)
            page_added += 1
            items.append({
                'rank': len(items) + 1,
                'item_id': item_id,
                'slug': slug,
                'live_preview_url': f'https://preview.themeforest.net/item/{slug}/full_screen_preview/{item_id}',
                'source_page': page,
            })
            if len(items) >= target:
                break
        print(f'collect page={page} added={page_added} total={len(items)}', flush=True)
        if page_added == 0:
            (OUT / f'collection-page-{page}.txt').write_text(text, encoding='utf-8')
            raise RuntimeError(f'No preview links on page {page}')
        await asyncio.sleep(1.2)
    if len(items) < target:
        raise RuntimeError(f'Collected only {len(items)} of {target}')
    (OUT / 'ranked-input.json').write_text(json.dumps(items, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    return items


def anchor_score(anchor, href: str, current: str) -> tuple[int, str]:
    text = ' '.join(anchor.get_text(' ', strip=True).split())
    classes = ' '.join(anchor.get('class', []))
    blob = f"{text} {classes} {anchor.get('id','')} {anchor.get('title','')} {anchor.get('aria-label','')}".lower()
    p = urlparse(href)
    filename = p.path.rstrip('/').split('/')[-1]
    score, why = 0, []
    if HOME_TEXT_RE.match(text):
        score += 70; why.append('home-text')
    if any(k in blob for k in ('navbar-brand', 'site-logo', 'header-logo', 'logo', 'brand')):
        score += 48; why.append('logo')
    if HOME_FILE_RE.match(filename):
        score += 55; why.append('home-file')
    if p.path.endswith('/'):
        score += 10; why.append('directory')
    if INTERNAL_RE.search(p.path):
        score -= 65; why.append('internal-path')
    cp = common_prefix_depth(current, href)
    cur_depth = len(segments(current))
    href_depth = len(segments(href))
    if cp >= max(1, cur_depth - 1):
        score += 28; why.append('same-demo-dir')
    elif cur_depth >= 2 and cp == 0:
        score -= 38; why.append('outside-demo-path')
    if href_depth < cur_depth:
        score += min((cur_depth - href_depth) * 4, 16); why.append('shallower')
    if p.path in ('', '/') and cur_depth >= 2:
        score -= 35; why.append('host-root-risk')
    if DEMO_LINK_RE.search(text) and not INTERNAL_RE.search(p.path):
        score += 18; why.append('demo-text')
    return score, '+'.join(why)


def infer_home(text: str, current: str) -> tuple[str, str, str, str]:
    soup = BeautifulSoup(text, 'lxml')
    current_path = urlparse(current).path
    current_file = current_path.rstrip('/').split('/')[-1]
    current_internal = bool(INTERNAL_RE.search(current_path))
    candidates: dict[str, tuple[int, str]] = {}

    for a in soup.find_all('a', href=True):
        raw = a.get('href', '').strip()
        if not raw or raw.startswith(('#', 'javascript:', 'mailto:', 'tel:', 'data:')):
            continue
        href = norm(urljoin(current, raw))
        if urlparse(href).scheme not in ('http', 'https') or not same_host(href, current):
            continue
        score, why = anchor_score(a, href, current)
        old = candidates.get(href)
        if old is None or score > old[0]:
            candidates[href] = (score, why)

    best = None
    if candidates:
        best = max(candidates.items(), key=lambda kv: (kv[1][0], -len(kv[0])))
        href, (score, why) = best
        if score >= 88:
            return href, 'clean_home', 'high', why
        if score >= 58 and current_internal:
            return href, 'probable_home', 'medium', why

    if not current_internal and (current_path.endswith('/') or HOME_FILE_RE.match(current_file) or current_path in ('', '/')):
        return norm(current), 'clean_home', 'high', 'resolved-entry'

    if not current_internal:
        return norm(current), 'probable_home', 'medium', 'resolved-entry-noninternal'

    if best and best[1][0] >= 35:
        return best[0], 'probable_home', 'low', best[1][1]

    p = urlparse(current)
    if '.' in current_file:
        parent = p.path.rsplit('/', 1)[0] + '/'
        return norm(urlunparse((p.scheme, p.netloc, parent, '', '', ''))), 'directory_fallback', 'low', 'same-directory'
    return norm(current), 'unresolved', 'low', 'resolved-fallback'


async def process_one(item: dict, client: httpx.AsyncClient, sem: asyncio.Semaphore) -> Result:
    result = Result(
        rank=item['rank'], item_id=item['item_id'], slug=item['slug'],
        themeforest_preview_url=item['live_preview_url'],
    )
    async with sem:
        try:
            wrapper = await fetch(client, result.themeforest_preview_url)
            direct = extract_frame(wrapper.text, str(wrapper.url))
            if not direct:
                final = norm(str(wrapper.url))
                if 'themeforest.net' not in host(final):
                    direct = final
                else:
                    result.error = f'iframe-not-found HTTP {wrapper.status_code}'
                    return result
            result.direct_demo_url = direct
            demo = await fetch(client, direct)
            result.resolved_demo_url = norm(str(demo.url))
            soup = BeautifulSoup(demo.text, 'lxml')
            result.title = (soup.title.get_text(' ', strip=True) if soup.title else '')[:240]
            home, classification, confidence, method = infer_home(demo.text, result.resolved_demo_url)
            result.clean_home_url = home
            result.classification = classification
            result.confidence = confidence
            result.method = method
            try:
                check = await fetch(client, home, tries=2)
                result.clean_status = check.status_code
                result.clean_home_url = norm(str(check.url))
                if check.status_code >= 400:
                    result.error = f'clean-home HTTP {check.status_code}'
            except Exception as exc:
                result.error = f'clean-home check: {exc}'
        except Exception as exc:
            result.error = str(exc)[:400]
        return result


async def main():
    headers = {
        'User-Agent': UA,
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language': 'en-US,en;q=0.8',
        'Cache-Control': 'no-cache',
    }
    limits = httpx.Limits(max_connections=CONCURRENCY + 8, max_keepalive_connections=CONCURRENCY + 4)
    async with httpx.AsyncClient(headers=headers, timeout=TIMEOUT, limits=limits, verify=True) as client:
        items = await collect_items(client, TARGET)
        sem = asyncio.Semaphore(CONCURRENCY)
        tasks = [asyncio.create_task(process_one(x, client, sem)) for x in items]
        results = []
        for i, task in enumerate(asyncio.as_completed(tasks), 1):
            results.append(await task)
            if i % 25 == 0:
                print(f'processed {i}/{len(tasks)}', flush=True)

    results.sort(key=lambda x: x.rank)
    rows = [asdict(x) for x in results]
    (OUT / 'themeforest-clean-demo-pages.json').write_text(json.dumps(rows, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    with (OUT / 'themeforest-clean-demo-pages.csv').open('w', encoding='utf-8-sig', newline='') as f:
        w = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
        w.writeheader(); w.writerows(rows)

    verified = [r for r in results if r.clean_home_url and 200 <= r.clean_status < 400 and r.confidence in ('high', 'medium')]
    (OUT / 'themeforest-clean-demo-pages.txt').write_text('\n'.join(r.clean_home_url for r in verified) + ('\n' if verified else ''), encoding='utf-8')
    review = [r for r in results if r not in verified]
    with (OUT / 'themeforest-needs-review.csv').open('w', encoding='utf-8-sig', newline='') as f:
        w = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
        w.writeheader(); w.writerows(asdict(r) for r in review)

    summary = {
        'total': len(results),
        'direct_extracted': sum(bool(r.direct_demo_url) for r in results),
        'verified_high_medium': len(verified),
        'high': sum(r.confidence == 'high' for r in results),
        'medium': sum(r.confidence == 'medium' for r in results),
        'low': sum(r.confidence == 'low' for r in results),
        'errors': sum(bool(r.error) for r in results),
        'needs_review': len(review),
    }
    (OUT / 'summary.json').write_text(json.dumps(summary, indent=2) + '\n', encoding='utf-8')
    print(json.dumps(summary, indent=2), flush=True)

if __name__ == '__main__':
    asyncio.run(main())
