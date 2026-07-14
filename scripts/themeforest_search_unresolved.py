import asyncio
import csv
import json
import re
import sys
from pathlib import Path
from urllib.parse import parse_qs, quote_plus, unquote, urljoin, urlparse

import httpx
from bs4 import BeautifulSoup

import themeforest_item_reader_extract_v6 as v6

base = v6.base
v4 = v6.v5.v4
strict = v4.v3.strict

INPUT = Path(sys.argv[1])
OUT = Path(sys.argv[2] if len(sys.argv) > 2 else 'search-output')
OUT.mkdir(parents=True, exist_ok=True)

SEARCH_CONCURRENCY = 2
SEARCH_TIMEOUT = httpx.Timeout(55.0, connect=20.0)
VERIFY_TIMEOUT = httpx.Timeout(35.0, connect=18.0)
RESULT_MARKERS = ('result__a', 'result-link', 'b_algo')
DEMO_ANCHOR_RE = re.compile(r'\b(?:live\s+demo|view\s+demo|preview|demo)\b', re.I)


def row_author(row):
    match = re.search(r'(?:^|;)author=([^;]+)', row.get('item_page_status', ''))
    return match.group(1).lower() if match else ''


def decode_ddg(raw):
    if raw.startswith('//'):
        raw = 'https:' + raw
    parsed = urlparse(raw)
    if 'duckduckgo.com' in parsed.netloc and parsed.path.startswith('/l/'):
        return unquote(parse_qs(parsed.query).get('uddg', [''])[0])
    return raw


def search_score(url, label, item, rank, author, engine):
    primary = strict.primary_token(item['slug'])
    blob = f'{url} {label}'.lower()
    if not primary or primary not in blob:
        return None
    score = 260 - min(rank, 30) * 7
    if any(word in blob for word in ('demo', 'preview', 'theme', 'template')):
        score += 40
    qualifier_words = v4.qualifiers(item['slug'])
    for word in qualifier_words:
        if word in blob:
            score += 50
        elif word in ('admin', 'react', 'angular', 'vue', 'laravel', 'aspnet'):
            score -= 35
    author_parts = [part for part in re.findall(r'[a-z0-9]{3,}', author) if part not in {'theme', 'themes', 'studio'}]
    if author_parts:
        if any(part in blob for part in author_parts):
            score += 120
        else:
            score -= 70
    parsed = urlparse(url)
    if parsed.path in ('', '/'):
        score += 10
    if strict.DOCS_RE.search(parsed.netloc.lower()) or strict.DOCS_RE.search(parsed.path.lower()):
        score -= 55
    return {
        'url': base.normalize_url(url),
        'label': label,
        'score': score,
        'reasons': f'{engine}-rank:{rank}+primary:{primary}' + (f'+author:{author}' if author else ''),
        'position': rank,
        'relevant': True,
        'primary': primary,
    }


def parse_ddg(html, item, author):
    soup = BeautifulSoup(html, 'lxml')
    anchors = soup.select('a.result__a') or soup.select('a.result-link')
    output = []
    for rank, anchor in enumerate(anchors, 1):
        url = base.normalize_url(decode_ddg(anchor.get('href', '')))
        if not url.startswith(('http://', 'https://')) or strict.is_blocked_url_v2(url):
            continue
        hostname = urlparse(url).netloc.lower()
        if any(blocked in hostname for blocked in v4.v3.SEARCH_BLOCKED):
            continue
        candidate = search_score(url, anchor.get_text(' ', strip=True), item, rank, author, 'ddg')
        if candidate:
            output.append(candidate)
            output.extend(v4.derived_parent_candidates_v4(candidate, candidate['primary']))
    return output


def parse_bing(html, item, author):
    soup = BeautifulSoup(html, 'lxml')
    output = []
    for rank, anchor in enumerate(soup.select('li.b_algo h2 a[href]'), 1):
        url = base.normalize_url(anchor.get('href', ''))
        if not url.startswith(('http://', 'https://')) or 'bing.com' in urlparse(url).netloc.lower():
            continue
        if strict.is_blocked_url_v2(url):
            continue
        hostname = urlparse(url).netloc.lower()
        if any(blocked in hostname for blocked in v4.v3.SEARCH_BLOCKED):
            continue
        candidate = search_score(url, anchor.get_text(' ', strip=True), item, rank, author, 'bing')
        if candidate:
            output.append(candidate)
            output.extend(v4.derived_parent_candidates_v4(candidate, candidate['primary']))
    return output


async def fetch_search(client, query, semaphore):
    encoded = quote_plus(query)
    endpoints = [
        ('ddg', f'https://html.duckduckgo.com/html/?kl=us-en&q={encoded}'),
        ('ddg', f'https://lite.duckduckgo.com/lite/?kl=us-en&q={encoded}'),
        ('bing', f'https://www.bing.com/search?q={encoded}&count=20'),
    ]
    errors = []
    for round_no in range(3):
        for engine, url in endpoints:
            try:
                async with semaphore:
                    response = await client.get(url, follow_redirects=True)
                    await asyncio.sleep(0.7)
                lower = response.text.lower()
                if response.status_code < 400 and len(response.text) > 4_000 and any(marker in lower for marker in RESULT_MARKERS):
                    return engine, response.text
                errors.append(f'{engine}:{response.status_code}:{len(response.text)}')
            except Exception as exc:
                errors.append(f'{engine}:{type(exc).__name__}')
        await asyncio.sleep(2.0 * (round_no + 1))
    raise RuntimeError('search unavailable ' + ','.join(errors[-9:]))


def demo_links_from_page(text, current, item):
    soup = BeautifulSoup(text, 'lxml')
    primary = strict.primary_token(item['slug'])
    qualifiers = v4.qualifiers(item['slug'])
    links = []
    for anchor in soup.find_all('a', href=True):
        raw = anchor.get('href', '').strip()
        if not raw or raw.startswith(('#', 'javascript:', 'mailto:', 'tel:')):
            continue
        url = base.normalize_url(urljoin(current, raw))
        if strict.is_blocked_url_v2(url):
            continue
        label = anchor.get_text(' ', strip=True)
        blob = f'{url} {label}'.lower()
        if not DEMO_ANCHOR_RE.search(blob):
            continue
        score = 0
        if primary in blob:
            score += 90
        if any(word in blob for word in qualifiers):
            score += 45
        if DEMO_ANCHOR_RE.search(label):
            score += 50
        if strict.DOCS_RE.search(urlparse(url).path.lower()):
            score -= 100
        links.append((score, url, label))
    links.sort(key=lambda entry: (-entry[0], len(entry[1])))
    return links


async def verify_one(client, candidate, item):
    response = await client.get(candidate['url'], follow_redirects=True)
    resolved = base.normalize_url(str(response.url))
    if response.status_code >= 400 or strict.is_blocked_url_v2(resolved) or len(response.text) <= 300:
        raise RuntimeError(f'HTTP {response.status_code}')

    parsed = urlparse(resolved)
    product_like = bool(base.INTERNAL_RE.search(parsed.path))
    if product_like:
        for _, demo_url, _ in demo_links_from_page(response.text, resolved, item)[:8]:
            try:
                demo_response = await client.get(demo_url, follow_redirects=True)
                demo_resolved = base.normalize_url(str(demo_response.url))
                if demo_response.status_code < 400 and len(demo_response.text) > 300 and not strict.is_blocked_url_v2(demo_resolved):
                    response = demo_response
                    resolved = demo_resolved
                    break
            except Exception:
                continue

    clean, classification, confidence, method = base.infer_clean_url(response.text, resolved)
    clean_parsed = urlparse(clean)
    if strict.is_blocked_url_v2(clean):
        raise RuntimeError('clean URL blocked')
    if strict.DOCS_RE.search(clean_parsed.netloc.lower()) or strict.DOCS_RE.search(clean_parsed.path.lower()):
        raise RuntimeError('clean URL is documentation')

    primary = strict.primary_token(item['slug'])
    if v4.product_specific(resolved, primary) and not v4.product_specific(clean, primary) and v4.path_depth(clean) < v4.path_depth(resolved):
        clean = resolved
        classification = 'demo_selector' if v4.path_depth(resolved) <= 2 else 'direct_home'
        confidence = 'high'
        method += '+preserve-product-path'

    soup = BeautifulSoup(response.text, 'lxml')
    return {
        **candidate,
        'resolved': resolved,
        'clean': clean,
        'status': response.status_code,
        'classification': classification,
        'confidence': confidence,
        'method': method,
        'title': soup.title.get_text(' ', strip=True)[:240] if soup.title else '',
    }


async def resolve_row(row, search_client, verify_client, search_sem, verify_sem):
    if row.get('clean_demo_url') and row.get('confidence') in ('high', 'medium') and int(row.get('http_status') or 0) < 400:
        return row
    item = {'rank': int(row['rank']), 'item_id': int(row['item_id']), 'slug': row['slug']}
    title = row.get('item_title') or item['slug'].replace('-', ' ')
    author = row_author(row)
    primary = strict.primary_token(item['slug'])
    qualifier_text = ' '.join(v4.qualifiers(item['slug']))
    queries = [
        f'"{title}" {author} live demo'.strip(),
        f'{primary} {author} {qualifier_text} template demo'.strip(),
        f'{item["slug"].replace("-", " ")} demo'.strip(),
    ]
    candidates = []
    errors = []
    for query in queries:
        try:
            engine, html = await fetch_search(search_client, query, search_sem)
            found = parse_ddg(html, item, author) if engine == 'ddg' else parse_bing(html, item, author)
            candidates.extend(found)
            if len(candidates) >= 6:
                break
        except Exception as exc:
            errors.append(str(exc))

    by_url = {}
    for candidate in candidates:
        old = by_url.get(candidate['url'])
        if old is None or candidate['score'] > old['score']:
            by_url[candidate['url']] = candidate
    candidates = sorted(by_url.values(), key=lambda entry: (-entry['score'], len(entry['url'])))

    for candidate in candidates[:20]:
        try:
            async with verify_sem:
                chosen = await verify_one(verify_client, candidate, item)
            row['candidate_url'] = chosen['url']
            row['candidate_score'] = chosen['score']
            row['candidate_method'] = chosen['reasons'] + '+' + chosen['method'] + '+search-pass'
            row['resolved_url'] = chosen['resolved']
            row['clean_demo_url'] = chosen['clean']
            row['http_status'] = chosen['status']
            row['classification'] = chosen['classification']
            row['confidence'] = chosen['confidence']
            row['page_title'] = chosen['title']
            row['error'] = ''
            return row
        except Exception as exc:
            errors.append(f"{candidate['url']}:{type(exc).__name__}:{exc}")

    row['error'] = (row.get('error', '') + '; search-pass=' + ' | '.join(errors[-10:]))[:1800]
    return row


def write_outputs(rows):
    rows.sort(key=lambda row: int(row['rank']))
    fields = list(rows[0].keys())
    (OUT / 'themeforest-clean-demo-pages.json').write_text(json.dumps(rows, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    with (OUT / 'themeforest-clean-demo-pages.csv').open('w', encoding='utf-8-sig', newline='') as file:
        writer = csv.DictWriter(file, fieldnames=fields); writer.writeheader(); writer.writerows(rows)
    verified = [row for row in rows if row.get('clean_demo_url') and row.get('confidence') in ('high','medium') and int(row.get('http_status') or 0) < 400]
    unresolved = [row for row in rows if row not in verified]
    (OUT / 'themeforest-clean-demo-pages.txt').write_text('\n'.join(row['clean_demo_url'] for row in verified) + ('\n' if verified else ''), encoding='utf-8')
    with (OUT / 'themeforest-needs-review.csv').open('w', encoding='utf-8-sig', newline='') as file:
        writer = csv.DictWriter(file, fieldnames=fields); writer.writeheader(); writer.writerows(unresolved)
    summary = {
        'total': len(rows),
        'verified': len(verified),
        'unresolved': len(unresolved),
        'high': sum(row.get('confidence') == 'high' for row in rows),
        'medium': sum(row.get('confidence') == 'medium' for row in rows),
        'search_resolved': sum('search-pass' in row.get('candidate_method','') for row in rows),
        'errors': sum(bool(row.get('error')) for row in rows),
    }
    (OUT / 'summary.json').write_text(json.dumps(summary, indent=2) + '\n', encoding='utf-8')
    return summary


async def main():
    rows = json.loads(INPUT.read_text(encoding='utf-8'))
    headers = {'User-Agent': base.UA, 'Accept': 'text/html,application/xhtml+xml,*/*;q=0.8'}
    search_sem = asyncio.Semaphore(SEARCH_CONCURRENCY)
    verify_sem = asyncio.Semaphore(4)
    search_limits = httpx.Limits(max_connections=SEARCH_CONCURRENCY + 2, max_keepalive_connections=SEARCH_CONCURRENCY)
    verify_limits = httpx.Limits(max_connections=6, max_keepalive_connections=4)
    async with httpx.AsyncClient(headers=headers, timeout=SEARCH_TIMEOUT, limits=search_limits) as search_client, \
            httpx.AsyncClient(headers=headers, timeout=VERIFY_TIMEOUT, limits=verify_limits, verify=True) as verify_client:
        tasks = [asyncio.create_task(resolve_row(row, search_client, verify_client, search_sem, verify_sem)) for row in rows]
        resolved_rows = []
        for index, task in enumerate(asyncio.as_completed(tasks), 1):
            resolved_rows.append(await task)
            if index % 20 == 0:
                print(f'search pass {index}/{len(rows)}', flush=True)
                write_outputs(resolved_rows + [row for row in rows if row not in resolved_rows and row.get('clean_demo_url')])
    summary = write_outputs(resolved_rows)
    print(json.dumps(summary, indent=2), flush=True)

if __name__ == '__main__':
    asyncio.run(main())
