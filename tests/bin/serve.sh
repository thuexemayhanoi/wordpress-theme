#!/usr/bin/env bash
#
# Serve a provisioned test site with PHP's built-in server.
#
# Usage: tests/bin/serve.sh <wp_dir> <port>
#
# Runs in the foreground; start it with `&` (or a CI "background" step) and stop it with `kill`.

set -euo pipefail

WP_DIR="${1:?wp_dir required}"
PORT="${2:?port required}"
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"

exec php \
	-d opcache.enable_cli=1 -d opcache.validate_timestamps=1 -d opcache.revalidate_freq=0 \
	-d memory_limit=512M -d max_execution_time=120 \
	-d error_log="$WP_DIR/wp-content/php-error.log" -d log_errors=1 -d display_errors=0 \
	-S "127.0.0.1:${PORT}" -t "$WP_DIR" "$HERE/router.php"
