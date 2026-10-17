<?php
/**
 * Module "admin": meta box, term and profile fields, settings page and notices.
 *
 * Every hook below only fires inside wp-admin (or during a save), and the classes are loaded on first use,
 * so the module costs next to nothing on the front end.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class_name ) {
		static $files = array(
			'NH_SEO_Admin_Assets'  => 'class-nh-seo-admin-assets.php',
			'NH_SEO_Admin_Notices' => 'class-nh-seo-admin-notices.php',
			'NH_SEO_Meta_Box'      => 'class-nh-seo-meta-box.php',
			'NH_SEO_Term_Fields'   => 'class-nh-seo-term-fields.php',
			'NH_SEO_User_Fields'   => 'class-nh-seo-user-fields.php',
			'NH_SEO_Settings_Page' => 'class-nh-seo-settings-page.php',
		);
		if ( isset( $files[ $class_name ] ) ) {
			require_once __DIR__ . '/' . $files[ $class_name ];
		}
	}
);

// Post and page editor.
add_action( 'add_meta_boxes', array( 'NH_SEO_Meta_Box', 'register' ) );
add_action( 'save_post', array( 'NH_SEO_Meta_Box', 'save' ), 10, 2 );

// Pillar field on category and Topic Cluster screens.
foreach ( array( 'category', 'topic_cluster' ) as $nh_seo_taxonomy ) {
	add_action( $nh_seo_taxonomy . '_add_form_fields', array( 'NH_SEO_Term_Fields', 'render_add_field' ) );
	add_action( $nh_seo_taxonomy . '_edit_form_fields', array( 'NH_SEO_Term_Fields', 'render_edit_field' ), 10, 2 );
	add_action( 'created_' . $nh_seo_taxonomy, array( 'NH_SEO_Term_Fields', 'save' ) );
	add_action( 'edited_' . $nh_seo_taxonomy, array( 'NH_SEO_Term_Fields', 'save' ) );
}
unset( $nh_seo_taxonomy );

// Author details on the profile screens.
add_action( 'show_user_profile', array( 'NH_SEO_User_Fields', 'render' ) );
add_action( 'edit_user_profile', array( 'NH_SEO_User_Fields', 'render' ) );
add_action( 'personal_options_update', array( 'NH_SEO_User_Fields', 'save' ) );
add_action( 'edit_user_profile_update', array( 'NH_SEO_User_Fields', 'save' ) );

// Settings → Nguyen Ha SEO Companion.
add_action( 'admin_menu', array( 'NH_SEO_Settings_Page', 'add_menu' ) );
add_action( 'admin_init', array( 'NH_SEO_Settings_Page', 'register_settings' ) );
add_filter( 'plugin_action_links_' . plugin_basename( NH_SEO_FILE ), array( 'NH_SEO_Settings_Page', 'action_links' ) );

// Assets and notices.
add_action( 'admin_enqueue_scripts', array( 'NH_SEO_Admin_Assets', 'enqueue' ) );
add_action( 'admin_notices', array( 'NH_SEO_Admin_Notices', 'render' ) );
