<?php
/** Offline safeguards for the Editorial-only English homepage and menu selection. */
if ( 'cli' !== PHP_SAPI ) {
	exit;
}
define( 'ABSPATH', __DIR__ . '/../../../../' );

class WP_Post {
	public $ID;
	public function __construct( $id ) {
		$this->ID = $id;
	}
}
class NH_VIEN_Routes {
	public static function live() {
		return $GLOBALS['live_routes'];
	}
	public static function home_page() {
		return $GLOBALS['en_home'];
	}
}
class NH_VIEN_Context {
	public static function is_english() {
		return $GLOBALS['english_context'];
	}
}
$GLOBALS['live_routes'] = false;
$GLOBALS['english_context'] = false;
$GLOBALS['en_home'] = new WP_Post( 200 );
$GLOBALS['queried'] = 200;
$GLOBALS['page'] = true;
$GLOBALS['stylesheet'] = 'wordpress-theme';
$GLOBALS['english_menu'] = false;

function is_page() {
	return $GLOBALS['page'];
}
function get_stylesheet() {
	return $GLOBALS['stylesheet'];
}
function get_queried_object_id() {
	return $GLOBALS['queried'];
}
function get_theme_file_path( $path ) {
	return __FILE__;
}
function get_nav_menu_locations() {
	return array( 'primary' => 10, 'footer' => 20 );
}
function wp_get_nav_menu_object( $id ) {
	if ( 10 === $id ) {
		return (object) array( 'slug' => 'primary' );
	}
	if ( 20 === $id ) {
		return (object) array( 'slug' => 'footer' );
	}
	if ( 'primary-en' === $id && $GLOBALS['english_menu'] ) {
		return (object) array( 'slug' => 'primary-en' );
	}
	return false;
}
function nh_editorial_fallback_menu() {
	echo '<ul id="primary-menu"><li>English category</li></ul>';
}
function expect_home( $ok, $message ) {
	if ( ! $ok ) {
		throw new RuntimeException( $message );
	}
}
require dirname( __DIR__ ) . '/includes/class-nh-vien-content.php';

$template = '/themes/original/page.php';
expect_home( $template === NH_VIEN_Content::english_home_template( $template ), 'Closed gate changed template.' );
$GLOBALS['live_routes'] = true;
expect_home( $template === NH_VIEN_Content::english_home_template( $template ), 'VI request changed template.' );
$GLOBALS['english_context'] = true;
expect_home( __FILE__ === NH_VIEN_Content::english_home_template( $template ), 'English homepage did not reuse Editorial.' );
$GLOBALS['queried'] = 201;
expect_home( $template === NH_VIEN_Content::english_home_template( $template ), 'Other English page took homepage template.' );
$GLOBALS['queried'] = 200;
$GLOBALS['stylesheet'] = 'other-theme';
expect_home( $template === NH_VIEN_Content::english_home_template( $template ), 'Unrelated theme modified.' );
$GLOBALS['stylesheet'] = 'wordpress-theme';
$GLOBALS['page'] = false;
expect_home( $template === NH_VIEN_Content::english_home_template( $template ), 'English archive took the page template.' );
$GLOBALS['page'] = true;
expect_home( null === NH_VIEN_Content::prevent_vietnamese_menu( null, (object) array( 'theme_location' => 'other' ) ), 'Unrelated menu was modified.' );
$out = NH_VIEN_Content::prevent_vietnamese_menu( null, (object) array( 'theme_location' => 'primary' ) );
expect_home( false !== strpos( $out, 'English category' ), 'English header exposed the Vietnamese menu.' );
expect_home( '' === NH_VIEN_Content::prevent_vietnamese_menu( null, (object) array( 'theme_location' => 'footer' ) ), 'English footer exposed Vietnamese menu.' );
$GLOBALS['english_menu'] = true;
expect_home( null === NH_VIEN_Content::prevent_vietnamese_menu( null, (object) array( 'theme_location' => 'primary' ) ), 'Configured English menu was blocked.' );

echo "PASS: Editorial /en/ home-only template, VI isolation and EN menu fallbacks.\n";
