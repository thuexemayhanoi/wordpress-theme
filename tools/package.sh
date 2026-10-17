#!/usr/bin/env bash
#
# Build the installable zips from the committed state (git archive honours .gitattributes):
#   dist/nguyen-ha-editorial-<version>.zip      the theme, folder "nguyen-ha-editorial"
#   dist/nguyen-ha-seo-companion-<version>.zip  the companion plugin, folder "nguyen-ha-seo-companion"
#   dist/SHA256SUMS
#
# Usage: tools/package.sh [git-ref]   (default HEAD; commit first, uncommitted changes are not packaged)

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."
REF="${1:-HEAD}"

THEME_VERSION="$(git show "$REF:style.css" | sed -n 's/^Version:[[:space:]]*//p' | head -1)"
PLUGIN_VERSION="$(git show "$REF:plugins/nguyen-ha-seo-companion/nguyen-ha-seo-companion.php" | sed -n 's/^ \* Version:[[:space:]]*//p' | head -1)"

if [ -z "$THEME_VERSION" ] || [ -z "$PLUGIN_VERSION" ]; then
	echo "Could not read the theme or plugin version" >&2
	exit 1
fi

rm -rf dist
mkdir -p dist

git archive --format=zip --prefix=nguyen-ha-editorial/ -o "dist/nguyen-ha-editorial-${THEME_VERSION}.zip" "$REF"
git archive --format=zip --prefix=nguyen-ha-seo-companion/ -o "dist/nguyen-ha-seo-companion-${PLUGIN_VERSION}.zip" "$REF:plugins/nguyen-ha-seo-companion"

(cd dist && sha256sum -- *.zip > SHA256SUMS)
echo "Built:"
ls -l dist
