#!/usr/bin/env bash
#
# Build the installable zips from the committed state (git archive honours .gitattributes):
#   dist/nguyen-ha-editorial-<version>.zip      the theme, folder "nguyen-ha-editorial"
#   dist/nguyen-ha-seo-companion-<version>.zip  the companion plugin, folder "nguyen-ha-seo-companion"
#   dist/nguyen-ha-vi-en-<version>.zip          the Vietnamese-English plugin, folder "nguyen-ha-vi-en" (no tests)
#   dist/SHA256SUMS
#
# Usage: tools/package.sh [git-ref]   (default HEAD; commit first, uncommitted changes are not packaged)

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."
REF="${1:-HEAD}"

THEME_VERSION="$(git show "$REF:style.css" | sed -n 's/^Version:[[:space:]]*//p' | head -1)"
PLUGIN_VERSION="$(git show "$REF:plugins/nguyen-ha-seo-companion/nguyen-ha-seo-companion.php" | sed -n 's/^ \* Version:[[:space:]]*//p' | head -1)"
VI_EN_VERSION="$(git show "$REF:plugins/nguyen-ha-vi-en/nguyen-ha-vi-en.php" | sed -n 's/^ \* Version:[[:space:]]*//p' | head -1)"

if [ -z "$THEME_VERSION" ] || [ -z "$PLUGIN_VERSION" ] || [ -z "$VI_EN_VERSION" ]; then
	echo "Could not read the theme or plugin versions" >&2
	exit 1
fi

rm -rf dist
mkdir -p dist

git archive --format=zip --prefix=nguyen-ha-editorial/ -o "dist/nguyen-ha-editorial-${THEME_VERSION}.zip" "$REF"
git archive --format=zip --prefix=nguyen-ha-seo-companion/ -o "dist/nguyen-ha-seo-companion-${PLUGIN_VERSION}.zip" "$REF:plugins/nguyen-ha-seo-companion"
git archive --format=zip --prefix=nguyen-ha-vi-en/ -o "dist/nguyen-ha-vi-en-${VI_EN_VERSION}.zip" "$REF:plugins/nguyen-ha-vi-en"

# Version manifest: what was built, from which commit, and the checksum of each zip.
python3 -I - "$REF" "$THEME_VERSION" "$PLUGIN_VERSION" "$VI_EN_VERSION" <<'PY'
import hashlib, json, os, re, subprocess, sys, zipfile

ref, theme_version, plugin_version, vi_en_version = sys.argv[1:5]

def git(*args):
    return subprocess.check_output(('git',) + args, text=True).strip()

def header(text, key, prefix=''):
    match = re.search(r'^%s%s:[ \t]*(.+)$' % (re.escape(prefix), re.escape(key)), text, re.M)
    return match.group(1).strip() if match else ''

def archive(name, zip_path, source_text, prefix):
    with open(zip_path, 'rb') as handle:
        data = handle.read()
    with zipfile.ZipFile(zip_path) as zipped:
        files = [n for n in zipped.namelist() if not n.endswith('/')]
    return {
        'slug': name,
        'version': header(source_text, 'Version', prefix),
        'requires_wordpress': header(source_text, 'Requires at least', prefix),
        'tested_up_to': header(source_text, 'Tested up to', prefix),
        'requires_php': header(source_text, 'Requires PHP', prefix),
        'zip': os.path.basename(zip_path),
        'sha256': hashlib.sha256(data).hexdigest(),
        'bytes': len(data),
        'files': len(files),
    }

theme_text = git('show', ref + ':style.css')
plugin_text = git('show', ref + ':plugins/nguyen-ha-seo-companion/nguyen-ha-seo-companion.php')
remote = os.environ.get('GITHUB_REPOSITORY') or re.sub(r'^https?://[^/@]*@', 'https://', git('config', '--get', 'remote.origin.url') or '')

plugin_readme = git('show', ref + ':plugins/nguyen-ha-seo-companion/readme.txt')
vi_en_text = git('show', ref + ':plugins/nguyen-ha-vi-en/nguyen-ha-vi-en.php')
vi_en_readme = git('show', ref + ':plugins/nguyen-ha-vi-en/readme.txt')

manifest = {
    'source': {
        'repository': remote,
        'commit': git('rev-parse', ref),
        'commit_time': git('log', '-1', '--format=%cI', ref),
        'ref': os.environ.get('GITHUB_REF_NAME', ref),
    },
    'theme': archive('nguyen-ha-editorial', 'dist/nguyen-ha-editorial-%s.zip' % theme_version, theme_text, ''),
    'plugin': archive('nguyen-ha-seo-companion', 'dist/nguyen-ha-seo-companion-%s.zip' % plugin_version, plugin_text, ' * '),
    'vi_en_plugin': archive('nguyen-ha-vi-en', 'dist/nguyen-ha-vi-en-%s.zip' % vi_en_version, vi_en_text, ' * '),
    'install': {
        'order': ['plugin (optional but recommended)', 'theme', 'vi_en_plugin (optional, independent of the theme and of the plugin; does nothing until the site opts in, see docs/VI-EN-PLUGIN.md)'],
        'activation': 'Installing a zip never activates it. Activate the theme only after checking the staging copy (docs/INSTALL-STAGING.md).',
        'rollback': 'docs/ROLLBACK.md',
    },
}
manifest['plugin']['tested_up_to'] = manifest['plugin']['tested_up_to'] or header(plugin_readme, 'Tested up to')
manifest['vi_en_plugin']['tested_up_to'] = manifest['vi_en_plugin']['tested_up_to'] or header(vi_en_readme, 'Tested up to')
with open('dist/manifest.json', 'w', encoding='utf-8') as out:
    json.dump(manifest, out, indent=2, ensure_ascii=False)
    out.write('\n')
PY

(cd dist && sha256sum -- *.zip manifest.json > SHA256SUMS)
echo "Built:"
ls -l dist
