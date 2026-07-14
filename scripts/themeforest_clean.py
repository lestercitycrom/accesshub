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

INPUT = Path(sys.argv[1] if len(sys.argv) > 1 else 'data/themeforest_previews.json')
OUT_DIR = Path(sys.argv[2] if len(sys.argv) > 2 else 'output')
OUT_DIR.mkdir(parents=True, exist_ok=True)

CONCURRENCY = 18
TIMEOUT = httpx.Timeout(35.0, connect=20.0)
UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36'

HOME_TEXT_RE = re.compile(r'^(home|homepage|main|start|demo|preview|главная|головна)$', re.I)
HOME_FILE_RE = re.compile(r'^(index|home|homepage|default|main)([-_]?\d+)?\.(?:html?|php|aspx?)$', re.I)
BAD_PATH_RE = re.compile(r'/(?:docs?|documentation|blog|article|post|product|shop|cart|checkout|login|register|signup|404|coming-soon|contact|about)(?:/|$)', re.I)
WRAPPER_PATTERNS = [
    re.compile(r'<iframe[^>]+class=["\'][^"\']*full-screen-preview__frame[^"\']*["\'][^>]+src=["\']([^"\']+)', re.I),
    re.compile(r'<iframe[^>]+src=["\']([^"\']+)["\'][^>]+class=["\'][^"\']*full-screen-preview__frame', re.I),
    re.compile(r'<iframe[^>]+src=["\']([^"\']+)["\']', re.I),
    re.compile(r'"(?:preview_url|previewUrl|iframe_url|iframeUrl)"\s*:\s*"([^"]+)"', re.I),
    re.compile(r'data-(?:preview-url|url)=["\']([^"\']+)', re.I),
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
    confidence: str = 'none'
    method: str = ''
    http_status: int = 0
    title: str = ''
    error: str = ''


def norm_url(url: str) -> str:
    url = html.unescape(url.strip()).replace('\\/', '/')
    p = urlparse(url)
    if not p.scheme:
        return url
    scheme = 'https' if p.scheme in ('http', 'https') else p.scheme
    return urlunparse((scheme, p.netloc.lower(), p.path or '/', p.params, p.query, ''))


def same_host(a: str, b: str) -> bool:
    ha = urlparse(a).netloc.lower().split(':')[0]
    hb = urlparse(b).netloc.lower().split(':')[0]
    return ha == hb


def path_depth(url: str) -> int:
    return len([x for x in urlparse(url).path.split('/') if x])


def extract_iframe_url(text: str, base_url: str) -> str:
    soup = BeautifulSoup(text, 'lxml')
    selectors = [
        'iframe.full-screen-preview__frame',
        'iframe[name="preview-frame"]',
        'iframe[src]',
    ]
    for selector in selectors:
        node = soup.select_one(selector)
        if node and node.get('src'):
            return norm_url(urljoin(base_url, node['src']))
    for pattern in WRAPPER_PATTERNS:
        m = pattern.search(text)
        if m:
            return norm_url(urljoin(base_url, m.group(1)))
    return ''


def candidate_score(anchor, href: str, current_url: str) -> tuple[int, str]:
    text = ' '.join(anchor.get_text(' ', strip=True).split())
    cls = ' '.join(anchor.get('class', []))
    aid = anchor.get('id', '') or ''
    aria = anchor.get('aria-label', '') or ''
    title = anchor.get('title', '') or ''
    blob = f'{text} {cls} {aid} {aria} {title}'.lower()
    parsed = urlparse(href)
    filename = parsed.path.rstrip('/').split('/')[-1]
    score = 0
    reasons = []

    if HOME_TEXT_RE.match(text):
        score += 55; reasons.append('home-text')
    if any(k in blob for k in ('logo', 'brand', 'navbar-brand', 'site-logo', 'header-logo')):
        score += 48; reasons.append('logo')
    if anchor.find(['img', 'svg']) and any(k in blob for k in ('logo', 'brand', 'header')):
        score += 20; reasons.append('logo-media')
    if parsed.path in ('', '/'):
        score += 30; reasons.append('host-root')
    if parsed.path.endswith('/'):
        score += 12; reasons.append('directory')
    if HOME_FILE_RE.match(filename):
        score += 35; reasons.append('home-file')
    if parsed.query == urlparse(current_url).query and parsed.query:
        score += 8; reasons.append('same-query')
    if BAD_PATH_RE.search(parsed.path):
        score -= 42; reasons.append('bad-section')
    depth_delta = path_depth(current_url) - path_depth(href)
    if depth_delta > 0:
        score += min(depth_delta * 5, 20); reasons.append('shallower')
    elif depth_delta < -1:
        score -= min(abs(depth_delta) * 4, 20)
    return score, '+'.join(reasons)


def infer_home(text: str, current_url: str) -> tuple[str, str, str]:
    soup = BeautifulSoup(text, 'lxml')
    candidates: dict[str, tuple[int, str]] = {}

    for anchor in soup.find_all('a', href=True):
        raw = anchor.get('href', '').strip()
        if not raw or raw.startswith(('#', 'javascript:', 'mailto:', 'tel:')):
            continue
        href = norm_url(urljoin(current_url, raw))
        if urlparse(href).scheme not in ('http', 'https') or not same_host(href, current_url):
            continue
        score, reason = candidate_score(anchor, href, current_url)
        old = candidates.get(href)
        if old is None or score > old[0]:
            candidates[href] = (score, reason)

    canonical = soup.find('link', rel=lambda x: x and 'canonical' in x)
    if canonical and canonical.get('href'):
        href = norm_url(urljoin(current_url, canonical['href']))
        if same_host(href, current_url):
            candidates[href] = max(candidates.get(href, (-999, '')), (24, 'canonical'))
    og = soup.find('meta', attrs={'property': 'og:url'})
    if og and og.get('content'):
        href = norm_url(urljoin(current_url, og['content']))
        if same_host(href, current_url):
            candidates[href] = max(candidates.get(href, (-999, '')), (20, 'og:url'))

    if candidates:
        href, (score, reason) = max(candidates.items(), key=lambda kv: (kv[1][0], -len(kv[0])))
        if score >= 52:
            return href, 'high', reason
        if score >= 32:
            return href, 'medium', reason

    p = urlparse(current_url)
    filename = p.path.rstrip('/').split('/')[-1]
    if filename and '.' in filename and not HOME_FILE_RE.match(filename):
        parent = p.path.rsplit('/', 1)[0] + '/'
        root_candidate = urlunparse((p.scheme, p.netloc, parent, '', p.query, ''))
        return norm_url(root_candidate), 'low', 'same-directory'

    return norm_url(current_url), 'medium', 'already-entry'


async def fetch(client: httpx.AsyncClient, url: str):
    last = None
    for attempt in range(4):
        try:
            r = await client.get(url, follow_redirects=True)
            if r.status_code == 429:
                await asyncio.sleep(min(30, 3 * (attempt + 1)))
                last = 'HTTP 429'
                continue
            return r
        except Exception as exc:
            last = str(exc)
            await asyncio.sleep(1.5 * (attempt + 1))
    raise RuntimeError(last or 'fetch failed')


async def process_one(item: dict, client: httpx.AsyncClient, sem: asyncio.Semaphore) -> Result:
    result = Result(
        rank=int(item['rank']),
        item_id=int(item['item_id']),
        slug=item['slug'],
        themeforest_preview_url=item.get('live_preview_url') or item.get('themeforest_preview_url'),
    )
    async with sem:
        try:
            wrapper = await fetch(client, result.themeforest_preview_url)
            result.http_status = wrapper.status_code
            direct = extract_iframe_url(wrapper.text, str(wrapper.url))
            if not direct:
                final_wrapper = norm_url(str(wrapper.url))
                if 'themeforest.net' not in urlparse(final_wrapper).netloc.lower():
                    direct = final_wrapper
                else:
                    result.error = 'iframe URL not found'
                    return result
            result.direct_demo_url = direct

            demo = await fetch(client, direct)
            result.resolved_demo_url = norm_url(str(demo.url))
            result.http_status = demo.status_code
            soup = BeautifulSoup(demo.text, 'lxml')
            result.title = (soup.title.get_text(' ', strip=True) if soup.title else '')[:250]
            home, confidence, method = infer_home(demo.text, result.resolved_demo_url)
            result.clean_home_url = home
            result.confidence = confidence
            result.method = method
            if demo.status_code >= 400:
                result.error = f'demo HTTP {demo.status_code}'
        except Exception as exc:
            result.error = str(exc)[:500]
        return result


async def collect_ranked_previews(client: httpx.AsyncClient, target: int = 1000) -> list[dict]:
    collected: list[dict] = []
    seen: set[int] = set()
    page_no = 1
    rx = re.compile(r'https?://(?:preview\.)?themeforest\.net/item/([^/?#]+)/full_screen_preview/(\d+)', re.I)
    while len(collected) < target and page_no <= 60:
        url = f'https://themeforest.net/category/site-templates?sort=sales&page={page_no}'
        r = await fetch(client, url)
        print(f'collect page {page_no}: HTTP {r.status_code}', flush=True)
        soup = BeautifulSoup(r.text, 'lxml')
        links = [a.get('href', '') for a in soup.select('a[href*="/full_screen_preview/"]')]
        page_found = 0
        for raw in links:
            absolute = urljoin(str(r.url), raw)
            m = rx.search(absolute)
            if not m:
                continue
            slug, item_id_s = m.group(1), m.group(2)
            item_id = int(item_id_s)
            if item_id in seen:
                continue
            seen.add(item_id)
            page_found += 1
            collected.append({
                'rank': len(collected) + 1,
                'item_id': item_id,
                'slug': slug,
                'live_preview_url': f'https://preview.themeforest.net/item/{slug}/full_screen_preview/{item_id}',
                'source_page': page_no,
            })
            if len(collected) >= target:
                break
        if page_found == 0:
            OUT_DIR.mkdir(parents=True, exist_ok=True)
            (OUT_DIR / f'collection-page-{page_no}.html').write_text(r.text, encoding='utf-8')
            raise RuntimeError(f'No Live Preview links found on ranking page {page_no}')
        print(f'collected {len(collected)}/{target}', flush=True)
        page_no += 1
        await asyncio.sleep(0.8)
    if len(collected) < target:
        raise RuntimeError(f'Collected only {len(collected)} of {target}')
    return collected


async def main():
    pre_headers = {
        'User-Agent': UA,
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language': 'en-US,en;q=0.8',
        'Cache-Control': 'no-cache',
    }
    pre_limits = httpx.Limits(max_connections=8, max_keepalive_connections=8)
    if INPUT.exists():
        items = json.loads(INPUT.read_text(encoding='utf-8'))
    else:
        async with httpx.AsyncClient(headers=pre_headers, timeout=TIMEOUT, limits=pre_limits, verify=True) as collector_client:
            items = await collect_ranked_previews(collector_client, 1000)
        INPUT.parent.mkdir(parents=True, exist_ok=True)
        INPUT.write_text(json.dumps(items, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    sem = asyncio.Semaphore(CONCURRENCY)
    headers = {
        'User-Agent': UA,
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language': 'en-US,en;q=0.8',
        'Cache-Control': 'no-cache',
    }
    limits = httpx.Limits(max_connections=CONCURRENCY + 5, max_keepalive_connections=CONCURRENCY)
    async with httpx.AsyncClient(headers=headers, timeout=TIMEOUT, limits=limits, verify=True) as client:
        tasks = [asyncio.create_task(process_one(item, client, sem)) for item in items]
        results = []
        for i, task in enumerate(asyncio.as_completed(tasks), 1):
            results.append(await task)
            if i % 50 == 0:
                print(f'processed {i}/{len(tasks)}', flush=True)

    results.sort(key=lambda x: x.rank)
    rows = [asdict(x) for x in results]
    (OUT_DIR / 'themeforest-clean-demo-pages.json').write_text(
        json.dumps(rows, ensure_ascii=False, indent=2) + '\n', encoding='utf-8'
    )
    with (OUT_DIR / 'themeforest-clean-demo-pages.csv').open('w', encoding='utf-8-sig', newline='') as f:
        w = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
        w.writeheader(); w.writerows(rows)

    usable = [r for r in results if r.clean_home_url and r.confidence in ('high', 'medium')]
    (OUT_DIR / 'themeforest-clean-demo-pages.txt').write_text(
        '\n'.join(r.clean_home_url for r in usable) + ('\n' if usable else ''), encoding='utf-8'
    )
    review = [r for r in results if r.confidence in ('low', 'none') or r.error]
    with (OUT_DIR / 'themeforest-needs-review.csv').open('w', encoding='utf-8-sig', newline='') as f:
        w = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
        w.writeheader(); w.writerows(asdict(x) for x in review)

    summary = {
        'total': len(results),
        'direct_extracted': sum(bool(r.direct_demo_url) for r in results),
        'clean_home_found': sum(bool(r.clean_home_url) for r in results),
        'high': sum(r.confidence == 'high' for r in results),
        'medium': sum(r.confidence == 'medium' for r in results),
        'low': sum(r.confidence == 'low' for r in results),
        'none': sum(r.confidence == 'none' for r in results),
        'with_error': sum(bool(r.error) for r in results),
        'usable_txt': len(usable),
        'needs_review': len(review),
    }
    (OUT_DIR / 'summary.json').write_text(json.dumps(summary, indent=2) + '\n', encoding='utf-8')
    print(json.dumps(summary, indent=2), flush=True)

if __name__ == '__main__':
    asyncio.run(main())
