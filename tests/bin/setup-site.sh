#!/usr/bin/env bash
#
# Provision a throw-away WordPress site for testing the theme and companion plugin.
#
# Usage:
#   tests/bin/setup-site.sh <wp_dir> <site_url> <db_name>
#
# Environment (all optional):
#   WP_CLI        wp-cli invocation        (default: "wp")
#   DB_USER       database user            (default: wp)
#   DB_PASS       database password        (default: wp)
#   DB_HOST       database host            (default: 127.0.0.1)
#   PERMALINKS    permalink structure      (default: /%category%/%postname%/)
#   WP_LOCALE     site language            (default: en_US)
#   WITH_PLUGIN   1 = activate companion   (default: 1)
#   THEME_SLUG    folder name for the theme (default: nguyen-ha-editorial)
#
# This script only touches the target WordPress directory and its database.
# It must never be pointed at a production site: it resets the database.

set -euo pipefail

WP_DIR="${1:?wp_dir required}"
SITE_URL="${2:?site_url required}"
DB_NAME="${3:?db_name required}"

WP_CLI="${WP_CLI:-wp}"
DB_USER="${DB_USER:-wp}"
DB_PASS="${DB_PASS:-wp}"
DB_HOST="${DB_HOST:-127.0.0.1}"
PERMALINKS="${PERMALINKS:-/%category%/%postname%/}"
WP_LOCALE="${WP_LOCALE:-en_US}"
WITH_PLUGIN="${WITH_PLUGIN:-1}"
THEME_SLUG="${THEME_SLUG:-nguyen-ha-editorial}"

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PLUGIN_SRC="$REPO_DIR/plugins/nguyen-ha-seo-companion"

# Refuse anything that does not look like a disposable local site.
case "$SITE_URL" in
	http://127.0.0.1*|http://localhost*) ;;
	*) echo "Refusing to provision non-local URL: $SITE_URL" >&2; exit 1 ;;
esac

wp() {
	# shellcheck disable=SC2086
	$WP_CLI --allow-root --path="$WP_DIR" "$@"
}

echo "==> wp-config.php"
wp config create \
	--dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" --dbhost="$DB_HOST" \
	--dbcharset=utf8mb4 --dbcollate=utf8mb4_unicode_520_ci \
	--skip-check --force --extra-php <<'PHP'
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', false );
define( 'DISABLE_WP_CRON', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'WP_AUTO_UPDATE_CORE', false );
define( 'FS_METHOD', 'direct' );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'EMPTY_TRASH_DAYS', 0 );
PHP

echo "==> reset database $DB_NAME"
wp db reset --yes

echo "==> core install"
wp core install --url="$SITE_URL" --title="Nguyen Ha Test Blog" \
	--admin_user=admin --admin_password=admin --admin_email=admin@example.test --skip-email

# wp-cli may append the install directory name to the stored URL; pin it explicitly.
wp option update siteurl "$SITE_URL"
wp option update home "$SITE_URL"

echo "==> link theme + plugin"
mkdir -p "$WP_DIR/wp-content/themes" "$WP_DIR/wp-content/plugins"
ln -sfn "$REPO_DIR" "$WP_DIR/wp-content/themes/$THEME_SLUG"
if [ -d "$PLUGIN_SRC" ]; then
	ln -sfn "$PLUGIN_SRC" "$WP_DIR/wp-content/plugins/nguyen-ha-seo-companion"
fi

echo "==> site options"
wp option update blogdescription "Practical motorbike, travel and mobility guides"
wp option update posts_per_page 12
wp option update timezone_string "Asia/Ho_Chi_Minh"
wp option update blog_public 1
wp option update default_comment_status open
wp option update WPLANG "$WP_LOCALE" || true
wp rewrite structure "$PERMALINKS"

echo "==> activate"
wp theme activate "$THEME_SLUG"
if [ "$WITH_PLUGIN" = "1" ] && [ -d "$PLUGIN_SRC" ]; then
	wp plugin activate nguyen-ha-seo-companion
fi
wp rewrite flush

echo "==> done: $SITE_URL (theme: $THEME_SLUG, permalinks: $PERMALINKS)"
