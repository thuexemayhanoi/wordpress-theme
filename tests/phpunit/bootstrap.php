<?php
/**
 * PHPUnit bootstrap: WordPress integration tests for the theme and the companion plugin.
 *
 * @package nguyen-ha-editorial
 */

// Test tooling: never runs through a web server.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$nh_root = dirname( __DIR__, 2 );

require_once $nh_root . '/vendor/autoload.php';

putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Test bootstrap only.

$nh_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
if ( ! $nh_tests_dir ) {
	echo 'wp-phpunit not found. Run `composer install` first.' . PHP_EOL;
	exit( 1 );
}

require_once $nh_tests_dir . '/includes/functions.php';

/*
 * Expose the repository itself as a theme called "nguyen-ha-editorial" without touching the
 * WordPress tree: a temp theme root holds one symlink back to the repository.
 */
$nh_theme_root = sys_get_temp_dir() . '/nh-editorial-theme-root-' . getmypid();
if ( ! is_dir( $nh_theme_root ) ) {
	mkdir( $nh_theme_root, 0777, true );
}
if ( ! file_exists( $nh_theme_root . '/nguyen-ha-editorial' ) ) {
	symlink( $nh_root, $nh_theme_root . '/nguyen-ha-editorial' );
}
register_shutdown_function(
	static function () use ( $nh_theme_root ) {
		if ( is_link( $nh_theme_root . '/nguyen-ha-editorial' ) ) {
			unlink( $nh_theme_root . '/nguyen-ha-editorial' );
		}
		if ( is_dir( $nh_theme_root ) ) {
			rmdir( $nh_theme_root );
		}
	}
);

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $nh_root, $nh_theme_root ) {
		register_theme_directory( $nh_theme_root );

		$plugin = $nh_root . '/plugins/nguyen-ha-seo-companion/nguyen-ha-seo-companion.php';
		if ( is_readable( $plugin ) ) {
			require $plugin;
		}
	}
);

tests_add_filter(
	'pre_option_template',
	static function () {
		return 'nguyen-ha-editorial';
	}
);
tests_add_filter(
	'pre_option_stylesheet',
	static function () {
		return 'nguyen-ha-editorial';
	}
);

require $nh_tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/class-nh-unit-test-case.php';
