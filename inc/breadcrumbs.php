<?php
/**
 * Visible breadcrumb: Home › parent category › child category › article.
 *
 * What the page shows must be what search engines read in the BreadcrumbList, and whoever prints that list owns the
 * trail (docs/DATA-CONTRACT.md §4):
 *
 * - an SEO plugin that prints the BreadcrumbList (Yoast SEO, read through its documented surface): the visible
 *   trail is the plugin's own, including its settings (which taxonomy a post shows, the "Home" text, the
 *   "You searched for" prefix);
 * - the companion plugin in full mode, or no plugin at all: the trail built below, which is the same one the
 *   companion plugin's nh_seo_breadcrumb_trail() serialises (a parity test keeps them identical).
 *
 * This module emits no structured data: the SEO plugin (or the companion plugin) owns the JSON-LD.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Canonical URL of the current archive or search results (first page, no tracking or other foreign query arguments).
 *
 * The core function get_pagenum_link() keeps whatever query string the visitor sent (utm_source, ref...) and HTML-escapes it. Date and
 * post type archives are built from their query instead; anything else (term, author, search) falls back to the paged
 * link of the address the visitor reached, without the arguments that are not WordPress query variables. Same
 * algorithm as nh_seo_archive_url() in the companion plugin (docs/DATA-CONTRACT.md section 4), so the visible
 * breadcrumb and the structured data name the same URL.
 *
 * @return string
 */
function nh_editorial_archive_url() {
	global $wp;

	if ( is_day() ) {
		return (string) get_day_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ), (int) get_query_var( 'day' ) );
	}
	if ( is_month() ) {
		return (string) get_month_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ) );
	}
	if ( is_year() ) {
		return (string) get_year_link( (int) get_query_var( 'year' ) );
	}
	if ( is_post_type_archive() ) {
		$object = get_queried_object();
		$link   = $object instanceof WP_Post_Type ? get_post_type_archive_link( $object->name ) : false;
		if ( $link ) {
			return (string) $link;
		}
	}

	$url     = (string) get_pagenum_link( 1, false );
	$foreign = array();
	foreach ( array_keys( wp_parse_args( (string) wp_parse_url( $url, PHP_URL_QUERY ) ) ) as $name ) {
		if ( ! in_array( $name, (array) $wp->public_query_vars, true ) ) {
			$foreign[] = $name;
		}
	}
	return $foreign ? remove_query_arg( $foreign, $url ) : $url;
}

/**
 * Breadcrumb crumbs of the current page as Yoast SEO declares them, or null when Yoast is not active or has none.
 *
 * Reads YoastSEO()->meta->for_current_page()->breadcrumbs, the documented surface: the same list that Yoast turns
 * into the BreadcrumbList of its graph (Yoast has built it for the head of this page already, so this costs
 * nothing). Anything unexpected (an older Yoast without the surface, a page it cannot describe) gives null and the
 * theme builds its own trail.
 *
 * @param object|null $yoast The object YoastSEO() returns; null reads it (a parameter so that the shape can be tested without Yoast).
 * @return array<int, mixed>|null Crumbs of text, url and sometimes hide_in_schema.
 */
function nh_editorial_yoast_breadcrumbs( $yoast = null ) {
	if ( null === $yoast ) {
		if ( ! function_exists( 'YoastSEO' ) ) {
			return null;
		}
		$yoast = YoastSEO();
	}
	// Yoast serves its surfaces and the values of a page through magic getters, and isset() does not reach a getter
	// that has no __isset(): ask whether the property can exist, then read it.
	if ( ! is_object( $yoast ) || ! ( property_exists( $yoast, 'meta' ) || method_exists( $yoast, '__get' ) ) ) {
		return null;
	}

	try {
		$meta = $yoast->meta;
		if ( ! is_object( $meta ) || ! method_exists( $meta, 'for_current_page' ) ) {
			return null;
		}
		$page = $meta->for_current_page();
		if ( ! is_object( $page ) || ! ( property_exists( $page, 'breadcrumbs' ) || method_exists( $page, '__get' ) ) ) {
			return null;
		}
		$crumbs = $page->breadcrumbs;
	} catch ( Throwable $error ) {
		return null;
	}

	return is_array( $crumbs ) ? $crumbs : null;
}

/**
 * The trail the SEO plugin declares to search engines (its BreadcrumbList), as items of name and url, or null when the
 * theme has to build its own.
 *
 * Null in these cases: the filter nh_editorial_breadcrumbs_follow_seo_plugin says no; the companion plugin runs in
 * full mode (it prints the BreadcrumbList from the trail this theme builds); there is no SEO plugin whose trail can
 * be read. Crumbs the plugin hides from its structured data (hide_in_schema) are left out here too, so that the page
 * and the BreadcrumbList stay the same list.
 *
 * @return array<int, array{name: string, url: string}>|null
 */
function nh_editorial_seo_plugin_breadcrumbs() {
	/**
	 * Filters whether the visible breadcrumb follows the trail of the SEO plugin that prints the BreadcrumbList.
	 *
	 * Return false to always show the trail this theme builds from the primary category and its parents. The page
	 * and the structured data may then differ, for instance when the SEO plugin shows no category for posts.
	 *
	 * @param bool $follow True by default.
	 */
	if ( ! apply_filters( 'nh_editorial_breadcrumbs_follow_seo_plugin', true ) ) {
		return null;
	}

	// The companion plugin serialises the trail built below when it prints the whole graph itself.
	if ( function_exists( 'nh_seo_schema_mode' ) && 'full' === nh_seo_schema_mode() ) {
		return null;
	}

	$crumbs = nh_editorial_yoast_breadcrumbs();

	/**
	 * Filters the crumbs of the SEO plugin for the current page.
	 *
	 * Yoast SEO is read by the theme. For any other SEO plugin that prints a BreadcrumbList, return its crumbs here:
	 * an array of arrays with "text" and "url" (and optionally "hide_in_schema" for a crumb its structured data omits).
	 * Null (the default for everything but Yoast) means the theme builds the trail.
	 *
	 * @param array|null $crumbs Crumbs read from Yoast SEO, or null.
	 */
	$crumbs = apply_filters( 'nh_editorial_seo_plugin_crumbs', $crumbs );
	if ( ! is_array( $crumbs ) ) {
		return null;
	}

	$items = array();
	foreach ( $crumbs as $crumb ) {
		if ( ! is_array( $crumb ) || ! empty( $crumb['hide_in_schema'] ) || ! isset( $crumb['text'] ) || ! is_scalar( $crumb['text'] ) ) {
			continue;
		}
		$name = trim( wp_specialchars_decode( wp_strip_all_tags( (string) $crumb['text'] ), ENT_QUOTES ) );
		if ( '' === $name ) {
			continue;
		}
		$items[] = array(
			'name' => $name,
			'url'  => isset( $crumb['url'] ) && is_scalar( $crumb['url'] ) ? (string) $crumb['url'] : '',
		);
	}

	return $items ? $items : null;
}

/**
 * Breadcrumb items for the current main query. Names are raw text (escape on output).
 *
 * @return array<int, array{name: string, url: string}> Empty on the front page and 404.
 */
function nh_editorial_breadcrumb_items() {
	if ( is_front_page() || is_404() ) {
		return array();
	}

	$items = nh_editorial_seo_plugin_breadcrumbs();
	if ( null === $items ) {
		$items = nh_editorial_own_breadcrumb_items();
	}

	/**
	 * Filters the visible breadcrumb items.
	 *
	 * @param array $items Items of name and url.
	 */
	return apply_filters( 'nh_editorial_breadcrumb_items', $items );
}

/**
 * The trail this theme builds itself: Home, the primary category with its parents (posts), the parent pages, the term
 * with its parents, or the archive. The companion plugin's nh_seo_breadcrumb_trail() is the same trail.
 *
 * @return array<int, array{name: string, url: string}>
 */
function nh_editorial_own_breadcrumb_items() {
	$items = array(
		array(
			'name' => __( 'Home', 'nguyen-ha-editorial' ),
			'url'  => home_url( '/' ),
		),
	);

	if ( is_singular() ) {
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return $items;
		}

		if ( 'post' === $post->post_type ) {
			$primary = nh_editorial_primary_category( $post );
			if ( $primary ) {
				$chain   = array_reverse( get_ancestors( $primary->term_id, 'category', 'taxonomy' ) );
				$chain[] = $primary->term_id;
				foreach ( $chain as $term_id ) {
					$term = get_term( $term_id, 'category' );
					$link = $term instanceof WP_Term ? get_term_link( $term ) : '';
					if ( $term instanceof WP_Term && ! is_wp_error( $link ) ) {
						$items[] = array(
							'name' => $term->name,
							'url'  => $link,
						);
					}
				}
			}
		} elseif ( is_post_type_hierarchical( $post->post_type ) ) {
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
				if ( 'publish' === get_post_status( $ancestor_id ) ) {
					$items[] = array(
						'name' => nh_editorial_plain_title( $ancestor_id ),
						'url'  => (string) get_permalink( $ancestor_id ),
					);
				}
			}
		} else {
			$archive = get_post_type_archive_link( $post->post_type );
			$object  = get_post_type_object( $post->post_type );
			if ( $archive && $object ) {
				$items[] = array(
					'name' => $object->labels->name,
					'url'  => $archive,
				);
			}
		}

		$items[] = array(
			'name' => nh_editorial_plain_title( $post ),
			'url'  => (string) get_permalink( $post ),
		);
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			if ( is_taxonomy_hierarchical( $term->taxonomy ) ) {
				foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
					$ancestor = get_term( $ancestor_id, $term->taxonomy );
					$link     = $ancestor instanceof WP_Term ? get_term_link( $ancestor ) : '';
					if ( $ancestor instanceof WP_Term && ! is_wp_error( $link ) ) {
						$items[] = array(
							'name' => $ancestor->name,
							'url'  => $link,
						);
					}
				}
			}
			$link = get_term_link( $term );
			if ( ! is_wp_error( $link ) ) {
				$items[] = array(
					'name' => $term->name,
					'url'  => $link,
				);
			}
		}
	} elseif ( is_author() ) {
		$author = get_queried_object();
		if ( $author instanceof WP_User ) {
			$items[] = array(
				'name' => $author->display_name,
				'url'  => get_author_posts_url( $author->ID ),
			);
		}
	} elseif ( is_search() ) {
		// The address the visitor reached, not get_search_link(): /?s=word and /search/word/ show the same results, and a
		// breadcrumb that names the other one points a crawler at a second URL for the page it is reading.
		$items[] = array(
			/* translators: %s: search query. */
			'name' => sprintf( __( 'Search results for: %s', 'nguyen-ha-editorial' ), get_search_query( false ) ),
			'url'  => nh_editorial_archive_url(),
		);
	} elseif ( is_archive() ) {
		$items[] = array(
			'name' => wp_strip_all_tags( get_the_archive_title() ),
			'url'  => nh_editorial_archive_url(),
		);
	}

	return $items;
}

/**
 * Print the breadcrumb component.
 *
 * @return void
 */
function nh_editorial_breadcrumbs() {
	get_template_part( 'template-parts/components/breadcrumb' );
}
