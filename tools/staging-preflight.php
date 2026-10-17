<?php
/**
 * Read-only readiness report for a staging (or production, before activation) WordPress site.
 *
 * Run it with WP-CLI on the site you want to inspect. Options are plain arguments after the file name, written
 * without dashes (WP-CLI would reject unknown --options for eval-file):
 *
 *   wp eval-file tools/staging-preflight.php
 *   wp eval-file tools/staging-preflight.php headline="Approved H1" lead="Approved lead paragraph"
 *   wp eval-file tools/staging-preflight.php content format=json
 *
 * Options:
 *   headline=<text>  Expected homepage headline (the approved H1). Compared character by character with the
 *                    Customizer setting of this theme.
 *   lead=<text>      Expected homepage introduction.
 *   content          Also fingerprint the text of every post and page (slower on big sites).
 *   format=<format>  text (default) or json.
 *
 * The script only reads: options, posts, terms and files. It writes nothing, activates nothing and contacts no
 * other host. It exits with status 1 when a check FAILS (warnings and information do not change the status).
 *
 * The fingerprints are for comparing the site with itself: run the script before the theme is activated, again
 * right after, and again after a rollback. Identical fingerprints show that posts, pages, categories, permalinks
 * and the protected options did not change.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

/**
 * Accumulates the lines of the report.
 *
 * @param string|null $level   ok, warn, fail or info. Null returns the collected lines.
 * @param string      $section Section title.
 * @param string      $message Message.
 * @return array<int, array<string, string>>|null
 */
function nh_editorial_preflight_record( $level = null, $section = '', $message = '' ) {
	static $lines = array();
	if ( null === $level ) {
		return $lines;
	}
	$lines[] = array(
		'level'   => $level,
		'section' => $section,
		'message' => $message,
	);
	return null;
}

/**
 * Header values of the theme folder of this repository (style.css), found by its text domain.
 *
 * @return array<string, mixed>|null Theme data or null when it is not installed.
 */
function nh_editorial_preflight_find_theme() {
	foreach ( wp_get_themes() as $slug => $theme ) {
		if ( 'nguyen-ha-editorial' === $theme->get( 'TextDomain' ) ) {
			return array(
				'slug'    => $slug,
				'theme'   => $theme,
				'version' => (string) $theme->get( 'Version' ),
				'wp'      => (string) $theme->get( 'RequiresWP' ),
				'php'     => (string) $theme->get( 'RequiresPHP' ),
				'dir'     => $theme->get_stylesheet_directory(),
			);
		}
	}
	return null;
}

/**
 * Environment and installation checks.
 *
 * @param array<string, mixed>|null $found Theme data.
 * @return void
 */
function nh_editorial_preflight_environment( $found ) {
	global $wp_version;

	$section = 'Environment';
	$min_wp  = $found && '' !== $found['wp'] ? $found['wp'] : '6.3';
	$min_php = $found && '' !== $found['php'] ? $found['php'] : '7.4';

	nh_editorial_preflight_record( version_compare( $wp_version, $min_wp, '>=' ) ? 'ok' : 'fail', $section, "WordPress {$wp_version} (the theme needs {$min_wp} or later)" );
	nh_editorial_preflight_record( version_compare( PHP_VERSION, $min_php, '>=' ) ? 'ok' : 'fail', $section, 'PHP ' . PHP_VERSION . " (the theme needs {$min_php} or later)" );
	nh_editorial_preflight_record( extension_loaded( 'mbstring' ) ? 'ok' : 'warn', $section, extension_loaded( 'mbstring' ) ? 'mbstring is available' : 'mbstring is missing: the theme still works (every call is guarded) but WordPress recommends it' );
	nh_editorial_preflight_record( 'info', $section, wp_using_ext_object_cache() ? 'A persistent object cache is in use' : 'No persistent object cache: the theme keeps its fragment cache in transients (fine on shared hosting)' );
	nh_editorial_preflight_record( 'info', $section, 'WP-Cron is ' . ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? 'disabled (a real cron job must call wp-cron.php for scheduled work)' : 'enabled' ) );
	nh_editorial_preflight_record( 'info', $section, 'Permalink structure: ' . ( '' === (string) get_option( 'permalink_structure' ) ? 'plain (?p=ID)' : get_option( 'permalink_structure' ) ) );

	$section = 'Theme installation';
	if ( ! $found ) {
		nh_editorial_preflight_record( 'fail', $section, 'The theme (text domain nguyen-ha-editorial) is not installed in the themes directory' );
		return;
	}

	$theme  = $found['theme'];
	$errors = $theme->errors();
	nh_editorial_preflight_record( 'ok', $section, "Installed as the folder '{$found['slug']}', version {$found['version']}" );
	nh_editorial_preflight_record( is_wp_error( $errors ) ? 'fail' : 'ok', $section, is_wp_error( $errors ) ? 'WordPress reports a problem with the theme: ' . $errors->get_error_message() : 'WordPress accepts the theme (no errors)' );

	foreach ( array( 'style.css', 'functions.php', 'index.php', 'theme.json', 'assets/css/critical.min.css', 'assets/css/main.min.css', 'assets/js/theme.min.js', 'languages/vi.mo' ) as $file ) {
		if ( ! is_readable( $found['dir'] . '/' . $file ) ) {
			nh_editorial_preflight_record( 'fail', $section, "Missing file: {$file}" );
		}
	}

	$active = get_option( 'stylesheet' );
	if ( $active === $found['slug'] ) {
		nh_editorial_preflight_record( 'info', $section, 'This theme is the ACTIVE theme' );
	} else {
		$other = wp_get_theme( $active );
		nh_editorial_preflight_record( 'info', $section, "Active theme: '{$active}' (" . $other->get( 'Name' ) . ', version ' . $other->get( 'Version' ) . '). It must stay installed: it is the way back (docs/ROLLBACK.md)' );
	}

	$dev = array();
	foreach ( array( 'tests', 'tools', 'docs', 'plugins', 'node_modules', 'vendor', '.git' ) as $folder ) {
		if ( is_dir( $found['dir'] . '/' . $folder ) ) {
			$dev[] = $folder;
		}
	}
	nh_editorial_preflight_record( $dev ? 'warn' : 'ok', $section, $dev ? 'Development folders are present in the theme folder (' . implode( ', ', $dev ) . '): harmless, but a zip install does not contain them (docs/DEPLOYMENT.md)' : 'No development folders in the theme folder' );
}

/**
 * SEO ownership: which plugin prints title, canonical, robots and structured data.
 *
 * @return void
 */
function nh_editorial_preflight_seo() {
	$section = 'SEO ownership';
	$plugins = array(
		'Yoast SEO'      => 'WPSEO_VERSION',
		'Rank Math'      => 'RANK_MATH_VERSION',
		'All in One SEO' => 'AIOSEO_VERSION',
		'SEOPress'       => 'SEOPRESS_VERSION',
	);
	$found   = array();
	foreach ( $plugins as $name => $constant ) {
		if ( defined( $constant ) ) {
			$found[] = $name . ' ' . constant( $constant );
		}
	}
	if ( $found ) {
		nh_editorial_preflight_record( 'ok', $section, 'SEO plugin active: ' . implode( ', ', $found ) . '. The theme prints no title, meta, canonical, robots, Open Graph or JSON-LD, so this plugin stays the only owner' );
	} else {
		nh_editorial_preflight_record( 'warn', $section, 'No known SEO plugin is active: the theme prints no meta description, Open Graph or structured data, so pages would have none' );
	}

	$companion = 'nguyen-ha-seo-companion/nguyen-ha-seo-companion.php';
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( is_plugin_active( $companion ) ) {
		$mode = function_exists( 'nh_seo_schema_mode' ) ? nh_seo_schema_mode() : 'unknown';
		nh_editorial_preflight_record( 'info', $section, "Companion plugin is active, effective structured data mode: {$mode}" . ( $found && 'extras' !== $mode && 'off' !== $mode ? ' (WARNING: an SEO plugin is active, the mode should be extras)' : '' ) );
		if ( $found && 'full' === $mode ) {
			nh_editorial_preflight_record( 'fail', $section, 'The companion plugin would print a full JSON-LD graph next to the SEO plugin: set Structured data to auto or extras' );
		}
	} else {
		nh_editorial_preflight_record( 'info', $section, 'Companion plugin is not active (optional): editorial extras and topic clusters are not available, the theme still works' );
	}
}

/**
 * Content structure the theme depends on (counts only).
 *
 * @return void
 */
function nh_editorial_preflight_content() {
	global $wpdb;

	$section = 'Content';
	$posts   = wp_count_posts( 'post' );
	$pages   = wp_count_posts( 'page' );
	nh_editorial_preflight_record( 'info', $section, sprintf( 'Posts: %d published, %d draft, %d private, %d scheduled, %d pending', $posts->publish, $posts->draft, $posts->private, $posts->future, $posts->pending ) );
	nh_editorial_preflight_record( 'info', $section, sprintf( 'Pages: %d published, %d draft, %d private', $pages->publish, $pages->draft, $pages->private ) );

	$tops = get_terms(
		array(
			'taxonomy'   => 'category',
			'parent'     => 0,
			'hide_empty' => false,
		)
	);
	$all  = (int) wp_count_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
		)
	);
	nh_editorial_preflight_record( 'info', $section, sprintf( 'Categories: %d in total, %d at the top level', $all, is_array( $tops ) ? count( $tops ) : 0 ) );

	$default = (int) get_option( 'default_category' );
	$term    = get_term( $default, 'category' );
	if ( $term instanceof WP_Term && (int) $term->count > 0 && $all > 1 ) {
		nh_editorial_preflight_record( 'warn', $section, sprintf( "%d published post(s) are only in the default category '%s': they have no real topic (the theme never treats the default category as a hub)", $term->count, $term->name ) );
	}

	if ( is_array( $tops ) ) {
		foreach ( $tops as $top ) {
			if ( $top->term_id === $default ) {
				continue;
			}
			$children = get_term_children( $top->term_id, 'category' );
			nh_editorial_preflight_record( 'info', $section, sprintf( "Top-level category '%s' (id %d, %d own post(s), %d sub-categories)", $top->name, $top->term_id, $top->count, is_array( $children ) ? count( $children ) : 0 ) );
		}
	}

	// A "published" count of real posts, ignoring the default-category rule above.
	$published = (int) $posts->publish;
	nh_editorial_preflight_record( $published > 15000 && ! apply_filters( 'nh_editorial_search_index_active', false ) ? 'warn' : 'info', $section, $published > 15000 ? 'More than 15,000 articles: without the companion plugin search index the search-as-you-type suggestions stay off and search reads every article' : 'Site size is within the range measured by the theme (docs/PERFORMANCE.md)' );

	// Orphaned references that would show as empty links.
	$sql      = "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} tt LEFT JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy = 'category' AND t.term_id IS NULL";
	$orphaned = (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Read-only diagnostic query, no input.
	nh_editorial_preflight_record( $orphaned ? 'warn' : 'ok', $section, $orphaned ? "{$orphaned} category row(s) without a term row" : 'Category tables are consistent' );
}

/**
 * Homepage headline and introduction compared with the approved texts.
 *
 * @param array<string, mixed>|null  $found Theme data.
 * @param array<string, string|bool> $args  Options.
 * @return void
 */
function nh_editorial_preflight_homepage( $found, array $args ) {
	$section = 'Homepage';
	nh_editorial_preflight_record( 'info', $section, 'Front page shows: ' . ( 'page' === get_option( 'show_on_front' ) ? 'a static page (id ' . (int) get_option( 'page_on_front' ) . ')' : 'the latest posts' ) );
	nh_editorial_preflight_record( 'info', $section, 'Site title: ' . get_option( 'blogname' ) );
	nh_editorial_preflight_record( 'info', $section, 'Tagline: ' . get_option( 'blogdescription' ) );

	if ( ! $found ) {
		return;
	}
	$mods     = get_option( 'theme_mods_' . $found['slug'], array() );
	$mods     = is_array( $mods ) ? $mods : array();
	$headline = isset( $mods['nh_home_headline'] ) ? (string) $mods['nh_home_headline'] : '';
	$lead     = isset( $mods['nh_home_subline'] ) ? (string) $mods['nh_home_subline'] : '';

	foreach (
		array(
			'headline' => array( 'Homepage headline (H1)', $headline ),
			'lead'     => array( 'Homepage introduction', $lead ),
		) as $key => $item
	) {
		list( $label, $actual ) = $item;
		if ( isset( $args[ $key ] ) ) {
			nh_editorial_preflight_record( $args[ $key ] === $actual ? 'ok' : 'fail', $section, $args[ $key ] === $actual ? "{$label} equals the approved text" : "{$label} differs from the approved text. Expected: {$args[ $key ]} | Actual: " . ( '' === $actual ? '(empty: the site title or tagline is used)' : $actual ) );
		} else {
			nh_editorial_preflight_record( '' === $actual ? 'warn' : 'info', $section, '' === $actual ? "{$label} is not set in the Customizer: the site title/tagline is used. Pass {$key}=\"...\" to compare it with the approved text" : "{$label}: {$actual}" );
		}
	}
}

/**
 * Hash of a list of rows.
 *
 * @param array<int, string> $rows Rows.
 * @return string
 */
function nh_editorial_preflight_hash( array $rows ) {
	return substr( hash( 'sha256', implode( "\n", $rows ) ), 0, 16 );
}

/**
 * Fingerprints of what the theme must never change.
 *
 * @param bool $with_content Also hash the text of every post and page.
 * @return void
 */
function nh_editorial_preflight_fingerprints( $with_content ) {
	global $wpdb;

	$section = 'Fingerprints (compare before and after activation)';

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Read-only diagnostics, table names only.
	$rows = $wpdb->get_results( "SELECT ID, post_name, post_status, post_parent, post_type, post_title, post_modified_gmt FROM {$wpdb->posts} WHERE post_type IN ('post', 'page') ORDER BY ID", ARRAY_N );
	$list = array();
	foreach ( (array) $rows as $row ) {
		$list[] = implode( '|', $row );
	}
	nh_editorial_preflight_record( 'info', $section, sprintf( 'posts and pages (id, slug, status, parent, type, title, modified): %d rows, %s', count( $list ), nh_editorial_preflight_hash( $list ) ) );

	$rows = $wpdb->get_results( "SELECT t.term_id, t.slug, tt.taxonomy, tt.parent FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy IN ('category', 'post_tag') ORDER BY t.term_id", ARRAY_N );
	$list = array();
	foreach ( (array) $rows as $row ) {
		$list[] = implode( '|', $row );
	}
	nh_editorial_preflight_record( 'info', $section, sprintf( 'categories and tags (id, slug, taxonomy, parent): %d rows, %s', count( $list ), nh_editorial_preflight_hash( $list ) ) );

	$rows = $wpdb->get_results( "SELECT tr.object_id, tt.term_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = 'category' ORDER BY tr.object_id, tt.term_id", ARRAY_N );
	$list = array();
	foreach ( (array) $rows as $row ) {
		$list[] = implode( '|', $row );
	}
	nh_editorial_preflight_record( 'info', $section, sprintf( 'post to category assignments: %d rows, %s', count( $list ), nh_editorial_preflight_hash( $list ) ) );
	// phpcs:enable

	$protected = array();
	foreach ( array( 'siteurl', 'home', 'blogname', 'blogdescription', 'blog_public', 'show_on_front', 'page_on_front', 'page_for_posts', 'permalink_structure', 'category_base', 'tag_base', 'default_category', 'posts_per_page', 'timezone_string', 'WPLANG' ) as $option ) {
		$protected[] = $option . '=' . maybe_serialize( get_option( $option ) );
	}
	nh_editorial_preflight_record( 'info', $section, 'protected options (site URLs, title, front page, permalinks, indexing flag): ' . nh_editorial_preflight_hash( $protected ) );
	nh_editorial_preflight_record( 'info', $section, 'rewrite rules: ' . nh_editorial_preflight_hash( array( maybe_serialize( get_option( 'rewrite_rules' ) ) ) ) );

	if ( $with_content ) {
		$ctx   = hash_init( 'sha256' );
		$count = 0;
		$last  = 0;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Read-only, keyset pagination on the primary key.
			$batch = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_content, post_excerpt FROM {$wpdb->posts} WHERE post_type IN ('post', 'page') AND ID > %d ORDER BY ID LIMIT 500", $last ), ARRAY_N );
			foreach ( (array) $batch as $row ) {
				hash_update( $ctx, implode( '|', $row ) . "\n" );
				$last = (int) $row[0];
				++$count;
			}
		} while ( ! empty( $batch ) );
		nh_editorial_preflight_record( 'info', $section, sprintf( 'post and page text (content, excerpt): %d rows, %s', $count, substr( hash_final( $ctx ), 0, 16 ) ) );
	} else {
		nh_editorial_preflight_record( 'info', $section, 'post and page text: not fingerprinted (add --content)' );
	}
}

/**
 * Print the report.
 *
 * @param string $format text or json.
 * @return int Number of failed checks.
 */
function nh_editorial_preflight_print( $format ) {
	$lines  = nh_editorial_preflight_record();
	$failed = 0;
	foreach ( $lines as $line ) {
		if ( 'fail' === $line['level'] ) {
			++$failed;
		}
	}

	if ( 'json' === $format ) {
		WP_CLI::line(
			wp_json_encode(
				array(
					'failed' => $failed,
					'checks' => $lines,
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			)
		);
		return $failed;
	}

	$labels  = array(
		'ok'   => '[ OK ]',
		'warn' => '[WARN]',
		'fail' => '[FAIL]',
		'info' => '[INFO]',
	);
	$current = '';
	foreach ( $lines as $line ) {
		if ( $line['section'] !== $current ) {
			$current = $line['section'];
			WP_CLI::line( '' );
			WP_CLI::line( '== ' . $current );
		}
		WP_CLI::line( $labels[ $line['level'] ] . ' ' . $line['message'] );
	}
	WP_CLI::line( '' );
	WP_CLI::line( $failed ? "{$failed} check(s) FAILED." : 'No check failed.' );
	return $failed;
}

/**
 * Turn the plain arguments into options: "key=value" becomes key => value, a bare word becomes word => true.
 *
 * @param array<int, string> $args Positional arguments of the command.
 * @return array<string, string|bool>
 */
function nh_editorial_preflight_options( array $args ) {
	$options = array();
	foreach ( $args as $arg ) {
		$arg = ltrim( (string) $arg, '-' );
		if ( false !== strpos( $arg, '=' ) ) {
			list( $key, $value ) = explode( '=', $arg, 2 );
			$options[ $key ]     = $value;
		} elseif ( '' !== $arg ) {
			$options[ $arg ] = true;
		}
	}
	return $options;
}

/**
 * Run the report.
 *
 * @param array<string, string|bool> $assoc Options.
 * @return void
 */
function nh_editorial_preflight_run( array $assoc ) {
	$found = nh_editorial_preflight_find_theme();
	nh_editorial_preflight_environment( $found );
	nh_editorial_preflight_seo();
	nh_editorial_preflight_content();
	nh_editorial_preflight_homepage( $found, $assoc );
	nh_editorial_preflight_fingerprints( isset( $assoc['content'] ) );

	$format = isset( $assoc['format'] ) && 'json' === $assoc['format'] ? 'json' : 'text';
	if ( nh_editorial_preflight_print( $format ) > 0 ) {
		WP_CLI::halt( 1 );
	}
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Provided by `wp eval-file`.
nh_editorial_preflight_run( nh_editorial_preflight_options( isset( $args ) && is_array( $args ) ? $args : array() ) );
