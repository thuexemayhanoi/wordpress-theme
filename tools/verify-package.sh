#!/usr/bin/env bash
#
# Check the zips produced by tools/package.sh before they are published or deployed.
#
# Usage: tools/verify-package.sh [dist-dir]
#
# Verifies for each zip: the expected top-level folder, the required files, the absence of development
# files and secrets, PHP syntax of every file, no oversized file, and that the version in the zip name
# matches the version in the plugin or theme header. The Vietnamese-English plugin zip is also checked for
# the direct-access guard of every file, one version in header, constant and readme, and independence from
# the theme and the companion plugin.

set -euo pipefail

DIST="${1:-dist}"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
FAIL=0

fail() {
	echo "FAIL: $*" >&2
	FAIL=1
}

unpack() {
	local zip="$1" dir="$2"
	mkdir -p "$dir"
	python3 -I - "$zip" "$dir" <<'PY'
import sys, zipfile
zipfile.ZipFile(sys.argv[1]).extractall(sys.argv[2])
PY
}

common_checks() {
	local root="$1" label="$2"

	# Development files and secrets must not ship.
	for pattern in 'node_modules' 'vendor' 'tests' 'tools' '.git' '.github' 'composer.json' 'package.json' 'phpunit.xml.dist' 'phpcs.xml.dist' '.env' '*.pem' '*.key' 'auth.json'; do
		if find "$root" -name "$pattern" -not -path "*/languages/*" | grep -q .; then
			fail "$label contains $pattern"
		fi
	done

	# Every PHP file parses and starts with the WordPress guard (index.php silencers excepted).
	while IFS= read -r -d '' file; do
		php -l "$file" >/dev/null 2>&1 || fail "$label: PHP syntax error in ${file#"$root"/}"
	done < <(find "$root" -name '*.php' -print0)

	# Nothing oversized.
	if find "$root" -type f -size +2048k | grep -q .; then
		fail "$label contains a file larger than 2 MB"
	fi
}

THEME_ZIP="$(ls "$DIST"/nguyen-ha-editorial-*.zip 2>/dev/null | head -1 || true)"
PLUGIN_ZIP="$(ls "$DIST"/nguyen-ha-seo-companion-*.zip 2>/dev/null | head -1 || true)"
VI_EN_ZIP="$(ls "$DIST"/nguyen-ha-vi-en-*.zip 2>/dev/null | head -1 || true)"
[ -n "$THEME_ZIP" ] || fail "theme zip not found in $DIST"
[ -n "$PLUGIN_ZIP" ] || fail "plugin zip not found in $DIST"
[ -n "$VI_EN_ZIP" ] || fail "VI-EN plugin zip not found in $DIST"

if [ -n "$THEME_ZIP" ]; then
	unpack "$THEME_ZIP" "$WORK/theme"
	ROOT="$WORK/theme/nguyen-ha-editorial"
	[ -d "$ROOT" ] || fail "theme zip must contain the folder nguyen-ha-editorial"
	for required in style.css functions.php index.php theme.json screenshot.png LICENSE README.md assets/css/critical.min.css assets/css/main.min.css assets/js/theme.min.js languages/nguyen-ha-editorial.pot languages/vi.mo; do
		[ -s "$ROOT/$required" ] || fail "theme zip lacks $required"
	done
	common_checks "$ROOT" "theme"
	[ ! -e "$ROOT/plugins" ] || fail "theme zip must not contain the plugin source"
	[ ! -e "$ROOT/assets/src" ] || fail "theme zip must not contain asset sources"
	ZIP_VERSION="$(basename "$THEME_ZIP" .zip | sed 's/^nguyen-ha-editorial-//')"
	HEADER_VERSION="$(sed -n 's/^Version:[[:space:]]*//p' "$ROOT/style.css" | head -1)"
	[ "$ZIP_VERSION" = "$HEADER_VERSION" ] || fail "theme zip name says $ZIP_VERSION but style.css says $HEADER_VERSION"
	grep -q "define( 'NH_EDITORIAL_VERSION', '$HEADER_VERSION' )" "$ROOT/functions.php" || fail "NH_EDITORIAL_VERSION does not match style.css ($HEADER_VERSION)"
fi

if [ -n "$PLUGIN_ZIP" ]; then
	unpack "$PLUGIN_ZIP" "$WORK/plugin"
	ROOT="$WORK/plugin/nguyen-ha-seo-companion"
	[ -d "$ROOT" ] || fail "plugin zip must contain the folder nguyen-ha-seo-companion"
	for required in nguyen-ha-seo-companion.php uninstall.php readme.txt languages/nguyen-ha-seo-companion.pot; do
		[ -s "$ROOT/$required" ] || fail "plugin zip lacks $required"
	done
	common_checks "$ROOT" "plugin"
	ZIP_VERSION="$(basename "$PLUGIN_ZIP" .zip | sed 's/^nguyen-ha-seo-companion-//')"
	HEADER_VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$ROOT/nguyen-ha-seo-companion.php" | head -1)"
	[ "$ZIP_VERSION" = "$HEADER_VERSION" ] || fail "plugin zip name says $ZIP_VERSION but its header says $HEADER_VERSION"
	grep -q "Stable tag: $HEADER_VERSION" "$ROOT/readme.txt" || fail "readme.txt Stable tag does not match $HEADER_VERSION"
fi

if [ -n "$VI_EN_ZIP" ]; then
	unpack "$VI_EN_ZIP" "$WORK/vi-en"
	ROOT="$WORK/vi-en/nguyen-ha-vi-en"
	[ -d "$ROOT" ] || fail "VI-EN zip must contain the folder nguyen-ha-vi-en"
	for required in nguyen-ha-vi-en.php index.php readme.txt assets/switcher.css includes/class-nh-vien-plugin.php includes/class-nh-vien-routes.php includes/class-nh-vien-seo.php includes/class-nh-vien-switcher.php; do
		[ -s "$ROOT/$required" ] || fail "VI-EN zip lacks $required"
	done
	common_checks "$ROOT" "VI-EN plugin"

	# Every PHP file refuses a direct request (the silencing index.php files excepted).
	while IFS= read -r -d '' file; do
		[ "$(basename "$file")" = index.php ] && continue
		grep -q "defined( 'ABSPATH' )" "$file" || fail "VI-EN plugin: ${file#"$ROOT"/} lacks the ABSPATH guard"
	done < <(find "$ROOT" -name '*.php' -print0)

	# One version everywhere: zip name, plugin header, constant, readme.
	ZIP_VERSION="$(basename "$VI_EN_ZIP" .zip | sed 's/^nguyen-ha-vi-en-//')"
	HEADER_VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$ROOT/nguyen-ha-vi-en.php" | head -1)"
	[ "$ZIP_VERSION" = "$HEADER_VERSION" ] || fail "VI-EN zip name says $ZIP_VERSION but its header says $HEADER_VERSION"
	grep -q "define( 'NH_VIEN_VERSION', '$HEADER_VERSION' )" "$ROOT/nguyen-ha-vi-en.php" || fail "NH_VIEN_VERSION does not match the header ($HEADER_VERSION)"
	grep -q "^Stable tag: $HEADER_VERSION\$" "$ROOT/readme.txt" || fail "VI-EN readme.txt Stable tag does not match $HEADER_VERSION"
	for key in 'Requires at least' 'Requires PHP'; do
		in_header="$(sed -n "s/^ \* $key:[[:space:]]*//p" "$ROOT/nguyen-ha-vi-en.php" | head -1)"
		in_readme="$(sed -n "s/^$key:[[:space:]]*//p" "$ROOT/readme.txt" | head -1)"
		[ -n "$in_header" ] && [ "$in_header" = "$in_readme" ] || fail "VI-EN plugin: '$key' differs between the header ($in_header) and readme.txt ($in_readme)"
	done

	# Independent of this repository's theme and companion plugin. It may notice them, always behind a check that they
	# exist: the action the theme fires after its skip link, the theme's fragment-cache filter and bump function, and
	# the companion's purge queue. Nothing else of theirs may be referenced.
	coupling="$(grep -rEno "nh_editorial[a-z_]*|NH_EDITORIAL[A-Za-z_]*|nh_seo[a-z_]*|NH_SEO[A-Za-z_]*|nguyen-ha-seo-companion|nguyen-ha-editorial|get_template_directory|get_stylesheet_directory" "$ROOT" --include='*.php' | grep -vE ':(nh_editorial_after_skip_link|nh_editorial_cache_bump|nh_editorial_cache_version|NH_SEO_Cache)$' || true)"
	[ -z "$coupling" ] || fail "VI-EN plugin refers to the theme or the companion plugin beyond its optional hooks: $coupling"
	# A file read from outside the plugin folder would break the zip on its own.
	if grep -rEn "(require|include)(_once)?[^;]*\.\./" "$ROOT" --include='*.php' | grep -q .; then
		fail "VI-EN plugin loads a file from outside its own folder"
	fi
fi

if [ "$FAIL" -eq 0 ]; then
	echo "Packages verified: $(basename "${THEME_ZIP:-none}"), $(basename "${PLUGIN_ZIP:-none}"), $(basename "${VI_EN_ZIP:-none}")"
fi
exit "$FAIL"
