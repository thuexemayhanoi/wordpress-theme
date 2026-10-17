<?php
/**
 * WordPress test-suite configuration (PHPUnit integration tests).
 *
 * Everything is driven by environment variables so the same file works locally and in CI:
 *
 *   WP_CORE_DIR          Path to an extracted WordPress tree (required).
 *   WP_TESTS_DB_NAME     Throw-away database. ALL tables with the test prefix are dropped (default: wp_phpunit).
 *   WP_TESTS_DB_USER     (default: wp)
 *   WP_TESTS_DB_PASSWORD (default: wp)
 *   WP_TESTS_DB_HOST     (default: 127.0.0.1)
 *
 * @package nguyen-ha-editorial
 */

$nh_core_dir = getenv( 'WP_CORE_DIR' );
if ( ! $nh_core_dir || ! is_dir( $nh_core_dir ) ) {
	echo 'Set WP_CORE_DIR to an extracted WordPress directory before running PHPUnit.' . PHP_EOL;
	exit( 1 );
}

define( 'ABSPATH', rtrim( $nh_core_dir, '/' ) . '/' );

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', false );

define( 'DB_NAME', getenv( 'WP_TESTS_DB_NAME' ) ? getenv( 'WP_TESTS_DB_NAME' ) : 'wp_phpunit' );
define( 'DB_USER', getenv( 'WP_TESTS_DB_USER' ) ? getenv( 'WP_TESTS_DB_USER' ) : 'wp' );
define( 'DB_PASSWORD', false !== getenv( 'WP_TESTS_DB_PASSWORD' ) ? getenv( 'WP_TESTS_DB_PASSWORD' ) : 'wp' );
define( 'DB_HOST', getenv( 'WP_TESTS_DB_HOST' ) ? getenv( 'WP_TESTS_DB_HOST' ) : '127.0.0.1' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'AUTH_KEY', 'test' );
define( 'SECURE_AUTH_KEY', 'test' );
define( 'LOGGED_IN_KEY', 'test' );
define( 'NONCE_KEY', 'test' );
define( 'AUTH_SALT', 'test' );
define( 'SECURE_AUTH_SALT', 'test' );
define( 'LOGGED_IN_SALT', 'test' );
define( 'NONCE_SALT', 'test' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required by the WordPress test suite.

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Nguyen Ha Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );

// The theme under test is exposed through tests/phpunit/bootstrap.php, which forces it as the active theme.
define( 'WP_DEFAULT_THEME', 'nguyen-ha-editorial' );
