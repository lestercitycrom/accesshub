import asyncio
import re
from urllib.parse import urlparse

import themeforest_search_unresolved as base_search

base = base_search.base
strict = base_search.strict
v4 = base_search.v4
ORIGINAL_RESOLVE_ROW = base_search.resolve_row
ORIGINAL_VERIFY_ONE = base_search.verify_one

SUSPICIOUS_PATH_RE = re.compile(
    r'/(?:auth|sign[-_]?in|login|register|reviews?|builder|forums?|faqs?|support|docs?|documentation|changelog|blog|article|post|single|product|shop|cart|checkout|portfolio-item)(?:[/.\-_]|$)',
    re.I,
)
SUSPICIOUS_TITLE_RE = re.compile(
    r'\b(?:documentation|changelog|support forum|sign in|login|reviews?)\b',
    re.I,
)
DEMOISH_RE = re.compile(r'\b(?:demo|preview|showcase|live)\b', re.I)


def should_recheck(row):
    if not row.get('clean_demo_url'):
        return True
    if row.get('confidence') not in ('high', 'medium'):
        return True
    if int(row.get('http_status') or 0) >= 400:
        return True
    url = row.get('clean_demo_url', '')
    parsed = urlparse(url)
    if SUSPICIOUS_PATH_RE.search(parsed.path):
        return True
    if SUSPICIOUS_TITLE_RE.search(row.get('page_title', '')):
        return True
    # Medium-confidence shallow brand/product pages are often sales pages rather
    # than the actual demo. Search them again unless the URL is explicitly demo-like.
    depth = len([part for part in parsed.path.split('/') if part])
    if row.get('confidence') == 'medium' and depth <= 1 and not DEMOISH_RE.search(url):
        return True
    return False


async def verify_one_v2(client, candidate, item):
    response = await client.get(candidate['url'], follow_redirects=True)
    resolved = base.normalize_url(str(response.url))
    if response.status_code >= 400 or strict.is_blocked_url_v2(resolved) or len(response.text) <= 300:
        raise RuntimeError(f'HTTP {response.status_code}')

    parsed = urlparse(resolved)
    primary = strict.primary_token(item['slug'])
    current_blob = f'{resolved} {candidate.get("label", "")}'.lower()
    current_demoish = bool(DEMOISH_RE.search(current_blob))

    # Sales/product pages often expose the actual demo through a strong
    # Live Demo / Preview button. Follow it even when the product page path
    # itself does not contain /product/.
    links = base_search.demo_links_from_page(response.text, resolved, item)
    if links and (not current_demoish or SUSPICIOUS_PATH_RE.search(parsed.path)):
        for score, demo_url, label in links[:10]:
            if score < 45:
                continue
            try:
                demo_response = await client.get(demo_url, follow_redirects=True)
                demo_resolved = base.normalize_url(str(demo_response.url))
                if (
                    demo_response.status_code < 400
                    and len(demo_response.text) > 300
                    and not strict.is_blocked_url_v2(demo_resolved)
                    and not SUSPICIOUS_PATH_RE.search(urlparse(demo_resolved).path)
                ):
                    response = demo_response
                    resolved = demo_resolved
                    parsed = urlparse(resolved)
                    break
            except Exception:
                continue

    clean, classification, confidence, method = base.infer_clean_url(response.text, resolved)
    clean_parsed = urlparse(clean)
    if strict.is_blocked_url_v2(clean):
        raise RuntimeError('clean URL blocked')
    if strict.DOCS_RE.search(clean_parsed.netloc.lower()) or strict.DOCS_RE.search(clean_parsed.path.lower()):
        raise RuntimeError('clean URL is documentation')
    if SUSPICIOUS_PATH_RE.search(clean_parsed.path):
        # Preserve the resolved demo page only if it is no longer suspicious.
        if not SUSPICIOUS_PATH_RE.search(parsed.path):
            clean = resolved
            classification = 'direct_home'
            confidence = 'medium'
            method += '+replace-suspicious-home'
        else:
            raise RuntimeError('candidate remains an internal page')

    if (
        v4.product_specific(resolved, primary)
        and not v4.product_specific(clean, primary)
        and v4.path_depth(clean) < v4.path_depth(resolved)
    ):
        clean = resolved
        classification = 'demo_selector' if v4.path_depth(resolved) <= 2 else 'direct_home'
        confidence = 'high'
        method += '+preserve-product-path'

    soup = base_search.BeautifulSoup(response.text, 'lxml')
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


async def resolve_row_v2(row, search_client, verify_client, search_sem, verify_sem):
    if not should_recheck(row):
        return row

    # Keep the original candidate only as a fallback; clear it while the search
    # runs so a failed search cannot silently preserve a known internal page.
    previous_url = row.get('clean_demo_url', '')
    previous_confidence = row.get('confidence', 'none')
    previous_classification = row.get('classification', 'unresolved')
    previous_error = row.get('error', '')
    row['clean_demo_url'] = ''
    row['confidence'] = 'none'
    row['classification'] = 'unresolved'

    resolved = await ORIGINAL_RESOLVE_ROW(
        row,
        search_client,
        verify_client,
        search_sem,
        verify_sem,
    )
    if resolved.get('clean_demo_url'):
        return resolved

    # A previously verified non-suspicious entry should never reach this branch.
    # For suspicious old entries, retain the URL only in diagnostic fields, not
    # in the clean TXT list.
    resolved['error'] = (
        previous_error
        + ('; ' if previous_error else '')
        + f'rejected_previous={previous_url} confidence={previous_confidence} classification={previous_classification}; '
        + resolved.get('error', '')
    )[:2400]
    return resolved


base_search.verify_one = verify_one_v2
base_search.resolve_row = resolve_row_v2
base_search.SEARCH_CONCURRENCY = 1

if __name__ == '__main__':
    asyncio.run(base_search.main())
