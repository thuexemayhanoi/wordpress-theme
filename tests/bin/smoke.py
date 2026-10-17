#!/usr/bin/env python3
"""HTTP smoke test of a seeded test site (tests/bin/setup-site.sh + tests/bin/seed-fixtures.php).

It requests the main page types of the theme over real HTTP and checks only what must hold on every
supported WordPress version: expected status codes, exactly one H1, a main landmark and a skip link, no PHP
message in the page, and no PHP error-log entry that comes from the theme or the companion plugin.

Usage: python3 -I tests/bin/smoke.py <base_url> [--log <file>]...

WordPress with WP_DEBUG_LOG writes to wp-content/debug.log, while the PHP server of tests/bin/serve.sh logs
to wp-content/php-error.log: pass both.

Test data only: the slugs below belong to the deterministic fixture set of seed-fixtures.php.
"""

import argparse
import re
import sys
import urllib.error
import urllib.request

# (path, expected status, page has the full theme chrome and exactly one H1)
PAGES = [
    ("/", 200, True),
    ("/page/2/", 200, False),
    ("/?s=scooter", 200, True),
    ("/?s=zzzzqqqq", 200, True),
    ("/?s=", 200, True),
    ("/does-not-exist-12345/", 404, True),
    ("/complete-guide-to-choosing-your-first-scooter/", 200, True),
    ("/how-to-check-a-rental-scooter-before-you-ride/", 200, True),
    ("/motorbike-rental-guide/", 200, True),
    ("/about/", 200, True),
    ("/contact/", 200, True),
    ("/category/motorbike-rental/", 200, True),
    ("/category/motorbike-rental/manual-bikes/", 200, True),
    ("/category/hanoi-travel-guides/", 200, True),
    ("/category/du-lich-ha-noi/", 200, True),
    ("/author/jane_editor/", 200, True),
    ("/hello-world/", 200, True),
    ("/password-protected-article/", 200, True),
    ("/hidden-private-article/", 404, True),
    ("/hidden-scheduled-article/", 404, True),
    ("/feed/", 200, False),
    ("/wp-sitemap.xml", 200, False),
]

PHP_MESSAGE = re.compile(r"(Fatal error|Parse error|Warning:|Notice:|Deprecated:|Stack trace)")
OWN_CODE = re.compile(r"nguyen-ha|wordpress-theme")


def fetch(url):
    request = urllib.request.Request(url, headers={"User-Agent": "nh-smoke"})
    try:
        response = urllib.request.urlopen(request, timeout=60)
        return response.status, response.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode("utf-8", "replace")


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("base_url")
    parser.add_argument("--log", action="append", default=[], help="PHP log of the site (repeatable); entries from the theme or plugin fail the run")
    args = parser.parse_args()
    base = args.base_url.rstrip("/")

    failures = 0
    for path, wanted, chrome in PAGES:
        status, html = fetch(base + path)
        problems = []
        if status != wanted:
            problems.append("status %s, expected %s" % (status, wanted))
        if PHP_MESSAGE.search(html):
            problems.append("PHP message in the page")
        if chrome and wanted == 200:
            h1 = len(re.findall(r"<h1[\s>]", html))
            if h1 != 1:
                problems.append("%d H1 elements" % h1)
        if chrome:
            if "<main" not in html:
                problems.append("no main landmark")
            if not re.search(r'<a[^>]+class="[^"]*skip-link', html):
                problems.append("no skip link")
        failures += bool(problems)
        print("%s %s %8dB %s %s" % ("FAIL" if problems else "ok  ", status, len(html), path, "; ".join(problems)))

    for log in args.log:
        try:
            with open(log, encoding="utf-8", errors="replace") as handle:
                lines = [line.rstrip() for line in handle if OWN_CODE.search(line)]
        except FileNotFoundError:
            lines = []
        for line in lines[:20]:
            print("FAIL %s: %s" % (log, line))
        failures += bool(lines)

    print("pages: %d, failed: %d" % (len(PAGES), failures))
    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main())
