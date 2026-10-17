#!/usr/bin/env bash
#
# Reproduce the scale measurements of docs/PERFORMANCE.md on a disposable local site.
#
# Usage: tools/measure-scale.sh <posts> <port> <wordpress_source_dir>
#
#   posts                  number of generated articles (1000, 10000, 50000, ...)
#   port                   local port for PHP's built-in server
#   wordpress_source_dir   a downloaded WordPress directory; it is copied, never modified
#
# Environment (all optional):
#   WP_CLI        wp-cli invocation                              (default: "wp")
#   DB_USER       database user                                  (default: wp)
#   DB_PASS       database password                              (default: wp)
#   DB_HOST       database host                                  (default: 127.0.0.1)
#   DB_NAME       database to reset and fill                     (default: wp_scale_<posts>)
#   WORK_DIR      directory for the copy of WordPress            (default: a new temporary directory)
#   WITH_PLUGIN   1 = companion plugin active                    (default: 1)
#   RUNS          requests per page, the first one is cold       (default: 3)
#   RESET_WIDGETS 1 = empty the widget areas, so the theme's own defaults show; 0 = keep the default blocks
#                 (Archives, Categories, ...) WordPress puts there on a fresh install      (default: 1)
#
# The database must exist and DB_USER needs all privileges on it. Only the copy of WordPress and that database
# are touched; the seed scripts refuse any URL that is not local. The figures describe PHP's built-in server
# on the machine that runs this script (not production hosting) and are meant for comparing pages and releases.

set -euo pipefail

POSTS="${1:?number of posts required}"
PORT="${2:?port required}"
WP_SRC="${3:?WordPress source directory required}"

WP_CLI="${WP_CLI:-wp}"
DB_USER="${DB_USER:-wp}"
DB_PASS="${DB_PASS:-wp}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_NAME="${DB_NAME:-wp_scale_${POSTS}}"
WITH_PLUGIN="${WITH_PLUGIN:-1}"
RUNS="${RUNS:-3}"

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"
WORK="${WORK_DIR:-$(mktemp -d)}"
SITE="$WORK/wp"
URL="http://127.0.0.1:${PORT}"

wp() {
	# shellcheck disable=SC2086
	$WP_CLI --allow-root --path="$SITE" "$@"
}

echo "==> copying WordPress to $SITE"
mkdir -p "$WORK"
cp -a "$WP_SRC" "$SITE"
rm -f "$SITE/wp-config.php" "$SITE/wp-content/themes/nguyen-ha-editorial" "$SITE/wp-content/plugins/nguyen-ha-seo-companion"

echo "==> provisioning $URL (database $DB_NAME)"
DB_USER="$DB_USER" DB_PASS="$DB_PASS" DB_HOST="$DB_HOST" PERMALINKS='/%postname%/' WITH_PLUGIN="$WITH_PLUGIN" \
	bash "$REPO/tests/bin/setup-site.sh" "$SITE" "$URL" "$DB_NAME" > "$WORK/setup.log" 2>&1 || { tail -20 "$WORK/setup.log" >&2; exit 1; }

wp config set SAVEQUERIES true --raw > /dev/null
if [ "${RESET_WIDGETS:-1}" = 1 ]; then
	wp widget reset --all > /dev/null
fi
mkdir -p "$SITE/wp-content/mu-plugins"
cp "$REPO/tests/bin/mu-metrics.php" "$SITE/wp-content/mu-plugins/"

echo "==> seeding $POSTS articles"
NH_BULK_POSTS="$POSTS" wp eval-file "$REPO/tests/bin/seed-bulk.php" > "$WORK/seed.log" 2>&1 || { tail -20 "$WORK/seed.log" >&2; exit 1; }

PER_PAGE="$(wp option get posts_per_page)"
export NH_LAST_PAGE=$(( (POSTS + PER_PAGE - 1) / PER_PAGE ))
PREFIX="$(wp db prefix)"
NH_MID_POST="$(wp db query "SELECT ID FROM ${PREFIX}posts WHERE post_type = 'post' AND post_status = 'publish' ORDER BY post_date DESC LIMIT 1 OFFSET $(( POSTS / 2 ))" --skip-column-names)"
export NH_MID_POST

echo "==> serving"
bash "$REPO/tests/bin/serve.sh" "$SITE" "$PORT" > "$WORK/serve.log" 2>&1 &
SERVER=$!
trap 'kill "$SERVER" 2> /dev/null || true' EXIT
for _ in $(seq 1 60); do
	if curl -fsS -o /dev/null "$URL/" 2> /dev/null; then
		break
	fi
	sleep 1
done

{
	echo "Articles: $POSTS, posts per page: $PER_PAGE, companion plugin: $([ "$WITH_PLUGIN" = 1 ] && echo active || echo inactive), widget areas: $([ "${RESET_WIDGETS:-1}" = 1 ] && echo empty || echo "WordPress defaults")"
	echo "WordPress $(wp core version), PHP $(php -r 'echo PHP_VERSION;'), $(nproc) CPU, $(date -u +%Y-%m-%d)"
	echo
	node "$REPO/tests/bin/measure.mjs" "$URL" "$RUNS"
} | tee "$WORK/result.md"

echo
echo "Result saved in $WORK/result.md (site copy kept in $SITE; drop database $DB_NAME when done)."
