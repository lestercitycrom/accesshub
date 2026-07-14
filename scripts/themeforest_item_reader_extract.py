import asyncio
import csv
import html
import json
import re
import sys
from collections import Counter
from dataclasses import asdict, dataclass
from pathlib import Path
from urllib.parse import urljoin, urlparse, urlunparse

import httpx
from bs4 import BeautifulSoup

INPUT = Path(sys.argv[1])
OUT = Path(sys.argv[2] if len(sys.argv) > 2 else 'output-reader')
START = int(sys.argv[3]) if len(sys.argv) > 3 else 0
LIMIT = int(sys.argv[4]) if len(sys.argv) > 4 else 1000
OUT.mkdir(parents=True, exist_ok=True)

JINA_CONCURRENCY = 5
VERIFY_CONCURRENCY = 10
JINA_TIMEOUT = httpx.Timeout(95.0, connect=25.0)
VERIFY_TIMEOUT = httpx.Timeout(35.0, connect=18.0)
UA = (
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
    'AppleWebKit/537.36 (KHTML, like Gecko) '
    'Chrome/140.0.0.0 Safari/537.36'
)

MARKDOWN_LINK_RE = re.compile(r'\[([^\]]*)\]\((https?://[^\s)]+)(?:\s+"[^"]*")?\)', re.I)
RAW_URL_RE = re.compile(r'https?://[^\s<>"\]\)]+', re.I)
HOME_FILE_RE = re.compile(r'^(?:index|home|homepage|default|main)(?:[-_]?\d+)?\.(?:html?|php|aspx?)$', re.I)
INTERNAL_RE = re.compile(
    r'/(?:docs?|documentation|blog|article|post|single|product(?:-page)?|shop|cart|checkout|login|register|signup|404|coming-soon|contact|about|faq|portfolio-item)(?:[/.\-_]|$)',
    re.I,
)
DEMO_WORD_RE = re.compile(r'\b(?:demo|preview|template|theme|showcase|landing)\b', re.I)
BAD_WORD_RE = re.compile(
    r'\b(?:hire|support|documentation|docs|youtube|facebook|twitter|instagram|pinterest|behance|dribbble|privacy|cookie|license|affiliate|portfolio)\b',
    re.I,
)
IMAGE_EXT_RE = re.compile(r'\.(?:png|jpe?g|gif|webp|svg|ico|bmp)(?:\?|$)', re.I)
CHALLENGE_RE = re.compile(r'performing security verification|just a moment|requiring captcha', re.I)

BLOCKED_HOST_PARTS = (
    'themeforest.net', 'codecanyon.net', 'videohive.net', 'audiojungle.net',
    'graphicriver.net', 'photodune.net', '3docean.net', 'envato.com',
    'envatousercontent.com', 'envato-static.com', 'elements.envato.com',
    'cookiebot.com', 'google.com', 'microsoft.com', 'facebook.com',
    'instagram.com', 'twitter.com', 'x.com', 'youtube.com', 'youtu.be',
    'pinterest.com', 'linkedin.com', 'tiktok.com', 'discord.com',
    'localhost', 'cloudflare.com',
)

@dataclass
class Result:
    rank: int
    item_id: int
    slug: str
    item_url: str
    item_page_status: str = ''
    item_title: str = ''
    candidate_url: str = ''
    candidate_score: int = 0
    candidate_method: str = ''
    resolved_url: str = ''
    clean_demo_url: str = ''
    http_status: int = 0
    classification: str = 'unresolved'
    confidence: str = 'none'
    page_title: str = ''
    candidate_count: int = 0
    error: str = ''


def normalize_url(value: str) -> str:
    value = html.unescape(value.strip()).replace('\\/', '/')
    value = value.rstrip('.,;')
    parsed = urlparse(value)
    if parsed.scheme not in ('http', 'https'):
        return value
    scheme = 'https'
    path = re.sub(r'/{2,}', '/', parsed.path or '/')
    return urlunparse((scheme, parsed.netloc.lower(), path, '', parsed.query, ''))


def host(value: str) -> str:
    return urlparse(value).netloc.lower().split(':')[0].removeprefix('www.')


def item_tokens(slug: str, title: str = '') -> set[str]:
    text = f'{slug} {title}'.lower()
    ignored = {
        'responsive', 'html', 'html5', 'template', 'theme', 'multipurpose',
        'bootstrap', 'website', 'site', 'admin', 'dashboard', 'landing',
        'ecommerce', 'commerce', 'modern', 'creative', 'business', 'the',
        'and', 'with', 'for', 'plus', 'mobile', 'app', 'ui', 'kit',
    }
    return {
        token for token in re.findall(r'[a-z0-9]{3,}', text)
        if token not in ignored
    }


def is_blocked_url(value: str) -> bool:
    parsed = urlparse(value)
    hostname = parsed.netloc.lower()
    if not hostname or parsed.scheme not in ('http', 'https'):
        return True
    if any(part in hostname for part in BLOCKED_HOST_PARTS):
        return True
    if IMAGE_EXT_RE.search(parsed.path):
        return True
    return False


def extract_title(markdown: str) -> str:
    match = re.search(r'^Title:\s*(.+)$', markdown, re.M)
    return match.group(1).strip() if match else ''


def extract_candidates(markdown: str, slug: str, title: str):
    entries = []
    for match in MARKDOWN_LINK_RE.finditer(markdown):
        label, raw = match.group(1), match.group(2)
        url = normalize_url(raw)
        if not is_blocked_url(url):
            entries.append((url, label.strip(), match.start()))

    # Some descriptions expose plain URLs outside Markdown links.
    linked_urls = {entry[0] for entry in entries}
    for match in RAW_URL_RE.finditer(markdown):
        url = normalize_url(match.group(0))
        if url not in linked_urls and not is_blocked_url(url):
            entries.append((url, '', match.start()))

    exact_counts = Counter(url for url, _, _ in entries)
    host_counts = Counter(host(url) for url, _, _ in entries)
    tokens = item_tokens(slug, title)
    scored = []

    for url, label, position in entries:
        parsed = urlparse(url)
        path = parsed.path or '/'
        filename = path.rstrip('/').split('/')[-1]
        lower_blob = f'{url} {label}'.lower()
        url_tokens = set(re.findall(r'[a-z0-9]{3,}', lower_blob))
        overlap = tokens & url_tokens
        score = 0
        reasons = []

        repeat = exact_counts[url]
        if repeat > 1:
            score += min(70, (repeat - 1) * 12)
            reasons.append(f'exact-repeat:{repeat}')
        same_host_count = host_counts[host(url)]
        score += min(35, max(0, same_host_count - 1) * 3)
        if same_host_count >= 3:
            reasons.append(f'host-repeat:{same_host_count}')

        if overlap:
            score += min(55, len(overlap) * 18)
            reasons.append('item-token:' + ','.join(sorted(overlap)[:4]))
        if DEMO_WORD_RE.search(lower_blob):
            score += 24
            reasons.append('demo-word')
        if any(word in host(url) for word in ('demo', 'theme', 'preview', 'template')):
            score += 28
            reasons.append('demo-host')
        if path in ('', '/'):
            score += 14
            reasons.append('host-root')
        depth = len([segment for segment in path.split('/') if segment])
        if depth <= 2:
            score += 18
            reasons.append('short-path')
        elif depth >= 6:
            score -= 15
        if path.endswith('/'):
            score += 12
            reasons.append('directory')
        if HOME_FILE_RE.match(filename):
            score += 30
            reasons.append('home-file')
        if INTERNAL_RE.search(path):
            score -= 75
            reasons.append('internal-path')
        if BAD_WORD_RE.search(lower_blob):
            score -= 70
            reasons.append('bad-word')
        if parsed.query:
            score -= 4
        # Description links occur much later than global navigation/cookie links.
        if position > 10_000:
            score += 12
            reasons.append('description-area')

        scored.append({
            'url': url,
            'label': label,
            'score': score,
            'reasons': '+'.join(reasons),
            'position': position,
        })

    # Deduplicate, retaining the highest score for each URL.
    by_url = {}
    for entry in scored:
        old = by_url.get(entry['url'])
        if old is None or entry['score'] > old['score']:
            by_url[entry['url']] = entry
    return sorted(by_url.values(), key=lambda x: (-x['score'], len(x['url']), x['position']))


async def fetch_jina(client: httpx.AsyncClient, item_url: str):
    attempts = [
        'https://r.jina.ai/' + item_url,
        'https://r.jina.ai/http://' + item_url.removeprefix('https://'),
    ]
    errors = []
    for round_no in range(3):
        for reader_url in attempts:
            try:
                response = await client.get(reader_url, follow_redirects=True)
                text = response.text
                if response.status_code == 200 and len(text) > 4_000 and not CHALLENGE_RE.search(text):
                    return text, f'jina:{round_no + 1}'
                errors.append(f'{response.status_code}:{len(text)}')
            except Exception as exc:
                errors.append(type(exc).__name__)
        await asyncio.sleep(2.5 * (round_no + 1))
    raise RuntimeError('item reader failed ' + ','.join(errors[-6:]))


def home_score(anchor, href: str, current: str):
    label = ' '.join(anchor.get_text(' ', strip=True).split())
    blob = f"{label} {' '.join(anchor.get('class', []))} {anchor.get('id', '')} {anchor.get('title', '')} {anchor.get('aria-label', '')}".lower()
    parsed = urlparse(href)
    filename = parsed.path.rstrip('/').split('/')[-1]
    score = 0
    reason = []
    if re.fullmatch(r'(?:home|homepage|main|start|demo)', label, re.I):
        score += 70; reason.append('home-text')
    if any(key in blob for key in ('navbar-brand', 'site-logo', 'header-logo', 'logo', 'brand')):
        score += 50; reason.append('logo')
    if HOME_FILE_RE.match(filename):
        score += 45; reason.append('home-file')
    if parsed.path.endswith('/'):
        score += 10
    if INTERNAL_RE.search(parsed.path):
        score -= 70
    if host(href) != host(current):
        score -= 100
    return score, '+'.join(reason)


def infer_clean_url(text: str, current: str):
    parsed_current = urlparse(current)
    current_internal = bool(INTERNAL_RE.search(parsed_current.path))
    current_file = parsed_current.path.rstrip('/').split('/')[-1]
    soup = BeautifulSoup(text, 'lxml')
    candidates = []
    demo_links = 0

    for anchor in soup.find_all('a', href=True):
        raw = anchor.get('href', '').strip()
        if not raw or raw.startswith(('#', 'javascript:', 'mailto:', 'tel:', 'data:')):
            continue
        href = normalize_url(urljoin(current, raw))
        if host(href) != host(current):
            continue
        label = anchor.get_text(' ', strip=True)
        if DEMO_WORD_RE.search(f'{href} {label}') and not INTERNAL_RE.search(urlparse(href).path):
            demo_links += 1
        score, reason = home_score(anchor, href, current)
        candidates.append((score, href, reason))

    if candidates:
        score, href, reason = max(candidates, key=lambda x: (x[0], -len(x[1])))
        if score >= 65 and (current_internal or href != current):
            return href, 'direct_home', 'high', reason

    path = parsed_current.path
    if not current_internal and (path.endswith('/') or HOME_FILE_RE.match(current_file) or path in ('', '/')):
        classification = 'demo_selector' if demo_links >= 4 else 'direct_home'
        return current, classification, 'high', 'entry-page'
    if not current_internal:
        classification = 'demo_selector' if demo_links >= 4 else 'probable_home'
        return current, classification, 'medium', 'non-internal-entry'

    if candidates:
        score, href, reason = max(candidates, key=lambda x: (x[0], -len(x[1])))
        if score >= 35:
            return href, 'probable_home', 'low', reason
    return current, 'unresolved', 'low', 'internal-no-home'


async def verify_candidates(client: httpx.AsyncClient, candidates):
    failures = []
    for entry in candidates[:8]:
        url = entry['url']
        try:
            response = await client.get(url, follow_redirects=True)
            resolved = normalize_url(str(response.url))
            if response.status_code < 400 and not is_blocked_url(resolved) and len(response.text) > 300:
                clean, classification, confidence, method = infer_clean_url(response.text, resolved)
                return {
                    **entry,
                    'resolved': resolved,
                    'clean': clean,
                    'status': response.status_code,
                    'classification': classification,
                    'confidence': confidence,
                    'method': method,
                    'title': BeautifulSoup(response.text, 'lxml').title.get_text(' ', strip=True)[:240]
                        if BeautifulSoup(response.text, 'lxml').title else '',
                }
            failures.append(f'{response.status_code}:{url}')
        except Exception as exc:
            failures.append(f'{type(exc).__name__}:{url}')
    raise RuntimeError('no candidate verified; ' + ' | '.join(failures[:8]))


async def process(item, jina_client, verify_client, jina_sem, verify_sem):
    item_url = f"https://themeforest.net/item/{item['slug']}/{item['item_id']}"
    result = Result(
        rank=int(item['rank']),
        item_id=int(item['item_id']),
        slug=item['slug'],
        item_url=item_url,
    )
    try:
        async with jina_sem:
            markdown, reader_status = await fetch_jina(jina_client, item_url)
        result.item_page_status = reader_status
        result.item_title = extract_title(markdown)
        candidates = extract_candidates(markdown, result.slug, result.item_title)
        result.candidate_count = len(candidates)
        if not candidates:
            raise RuntimeError('no external description candidates')
        async with verify_sem:
            chosen = await verify_candidates(verify_client, candidates)
        result.candidate_url = chosen['url']
        result.candidate_score = chosen['score']
        result.candidate_method = chosen['reasons'] + '+' + chosen['method']
        result.resolved_url = chosen['resolved']
        result.clean_demo_url = chosen['clean']
        result.http_status = chosen['status']
        result.classification = chosen['classification']
        result.confidence = chosen['confidence']
        result.page_title = chosen['title']
    except Exception as exc:
        result.error = f'{type(exc).__name__}: {str(exc)[:700]}'
    return result


def write_results(results):
    results.sort(key=lambda x: x.rank)
    rows = [asdict(item) for item in results]
    if not rows:
        return
    fields = list(rows[0].keys())
    (OUT / 'themeforest-clean-demo-pages.json').write_text(
        json.dumps(rows, ensure_ascii=False, indent=2) + '\n', encoding='utf-8'
    )
    with (OUT / 'themeforest-clean-demo-pages.csv').open('w', encoding='utf-8-sig', newline='') as file:
        writer = csv.DictWriter(file, fieldnames=fields)
        writer.writeheader(); writer.writerows(rows)
    verified = [
        item for item in results
        if item.clean_demo_url and item.http_status < 400 and item.confidence in ('high', 'medium')
    ]
    review = [item for item in results if item not in verified]
    (OUT / 'themeforest-clean-demo-pages.txt').write_text(
        '\n'.join(item.clean_demo_url for item in verified) + ('\n' if verified else ''),
        encoding='utf-8',
    )
    with (OUT / 'themeforest-needs-review.csv').open('w', encoding='utf-8-sig', newline='') as file:
        writer = csv.DictWriter(file, fieldnames=fields)
        writer.writeheader(); writer.writerows(asdict(item) for item in review)
    summary = {
        'start': START,
        'requested': LIMIT,
        'processed': len(results),
        'item_pages_read': sum(bool(item.item_page_status) for item in results),
        'verified_high_medium': len(verified),
        'direct_home': sum(item.classification == 'direct_home' for item in results),
        'demo_selector': sum(item.classification == 'demo_selector' for item in results),
        'probable_home': sum(item.classification == 'probable_home' for item in results),
        'high': sum(item.confidence == 'high' for item in results),
        'medium': sum(item.confidence == 'medium' for item in results),
        'low': sum(item.confidence == 'low' for item in results),
        'errors': sum(bool(item.error) for item in results),
    }
    (OUT / 'summary.json').write_text(json.dumps(summary, indent=2) + '\n', encoding='utf-8')


async def main():
    all_items = json.loads(INPUT.read_text(encoding='utf-8'))
    items = all_items[START:START + LIMIT]
    headers = {'User-Agent': UA, 'Accept': 'text/html,application/xhtml+xml,*/*;q=0.8'}
    jina_limits = httpx.Limits(max_connections=JINA_CONCURRENCY + 2, max_keepalive_connections=JINA_CONCURRENCY)
    verify_limits = httpx.Limits(max_connections=VERIFY_CONCURRENCY + 5, max_keepalive_connections=VERIFY_CONCURRENCY)
    jina_sem = asyncio.Semaphore(JINA_CONCURRENCY)
    verify_sem = asyncio.Semaphore(VERIFY_CONCURRENCY)
    async with httpx.AsyncClient(headers=headers, timeout=JINA_TIMEOUT, limits=jina_limits) as jina_client, \
            httpx.AsyncClient(headers=headers, timeout=VERIFY_TIMEOUT, limits=verify_limits, verify=True) as verify_client:
        tasks = [asyncio.create_task(process(item, jina_client, verify_client, jina_sem, verify_sem)) for item in items]
        results = []
        for index, task in enumerate(asyncio.as_completed(tasks), 1):
            results.append(await task)
            if index % 5 == 0:
                okay = sum(bool(item.clean_demo_url) for item in results)
                print(f'processed {index}/{len(items)} clean={okay}', flush=True)
                write_results(results)
    write_results(results)
    print((OUT / 'summary.json').read_text(encoding='utf-8'), flush=True)

if __name__ == '__main__':
    asyncio.run(main())
