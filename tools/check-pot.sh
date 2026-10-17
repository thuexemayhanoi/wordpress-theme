#!/usr/bin/env bash
#
# Fail when a translation template no longer matches the strings in the source.
#
# Usage: tools/check-pot.sh <wp-cli command> <source dir> <domain> <committed .pot> [extra make-pot args...]
# Example: tools/check-pot.sh "php wp-cli.phar --allow-root" . nguyen-ha-editorial languages/nguyen-ha-editorial.pot --skip-theme-json
#
# Only the set of strings is compared (message, context and plural), never file names, line numbers or
# dates, so moving code around does not make the check fail.

set -euo pipefail

WPCLI="${1:?wp-cli command required}"
SOURCE="${2:?source dir required}"
DOMAIN="${3:?text domain required}"
COMMITTED="${4:?committed pot required}"
shift 4

GENERATED="$(mktemp --suffix=.pot)"
trap 'rm -f "$GENERATED"' EXIT

# shellcheck disable=SC2086
$WPCLI i18n make-pot "$SOURCE" "$GENERATED" --slug="$DOMAIN" --domain="$DOMAIN" \
	--exclude=node_modules,vendor,tests,plugins,docs,.claude,tools,assets,dist "$@" >/dev/null

python3 -I - "$GENERATED" "$COMMITTED" <<'PY'
import re
import sys

def strings(path):
    text = open(path, encoding='utf-8').read()
    found = set()
    for block in text.strip().split('\n\n'):
        def field(name):
            match = re.search(r'^%s (""\n(?:"[^\n]*"\n?)+|"[^\n]*")' % name, block, re.M)
            if not match:
                return None
            return ''.join(re.findall(r'"(.*)"', match.group(1)))
        msgid = field('msgid')
        if msgid:
            found.add((field('msgctxt') or '', msgid, field('msgid_plural') or ''))
    return found

generated, committed = strings(sys.argv[1]), strings(sys.argv[2])
missing = sorted(generated - committed)
stale = sorted(committed - generated)
if missing or stale:
    print('The translation template is out of date.')
    for item in missing:
        print('  in the source but not in the template: %r' % (item[1],))
    for item in stale:
        print('  in the template but not in the source: %r' % (item[1],))
    print('Regenerate it with wp i18n make-pot and update the .po catalogs.')
    sys.exit(1)
print('Template is up to date (%d strings).' % len(committed))
PY
