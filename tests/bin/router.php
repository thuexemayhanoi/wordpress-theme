<?php
/**
 * Router for `php -S` so WordPress pretty permalinks work without .htaccess.
 *
 * Test tooling only. It never runs inside WordPress, so it is guarded to the
 * CLI-server SAPI and refuses to do anything when requested through another SAPI.
 *
 * @package nguyen-ha-editorial
 */

if ( 'cli-server' !== PHP_SAPI ) {
	exit;
}

$nh_root = $_SERVER['DOCUMENT_ROOT'];
$nh_path = rawurldecode( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );

// Static files and real PHP entry points (wp-login.php, wp-admin/*.php...) are served directly.
if ( '/' !== $nh_path && false === strpos( $nh_path, '..' ) && is_file( $nh_root . $nh_path ) ) {
	return false;
}

// Directory requests that own an index.php (e.g. /wp-admin/).
if ( '/' !== $nh_path && is_dir( $nh_root . $nh_path ) && is_file( rtrim( $nh_root . $nh_path, '/' ) . '/index.php' ) ) {
	return false;
}

$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $nh_root . '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';

require $nh_root . '/index.php';
