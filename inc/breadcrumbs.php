<?php
/**
 * Visible breadcrumb: Home › parent category › child category › article.
 *
 * Same trail as the companion plugin's nh_seo_breadcrumb_trail() (docs/DATA-CONTRACT.md §4) so the
 * visible breadcrumb always matches the BreadcrumbList that search engines read. This module emits no
 * structured data: the SEO plugin (or the companion plugin) owns the JSON-LD.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Canonical URL of the current archive (first page, no tracking or other foreign query arguments).
 *
 * The core function get_pagenum_link() keeps whatever query string the visitor sent (utm_source, ref...) and HTML-escapes it. Date and
 * post type archives are built from their query instead; anything else falls back to the paged link without
 * arguments that are not WordPress query variables. Same algorithm as nh_seo_archive_url() in the companion plugin
 * (docs/DATA-CONTRACT.md section 4), so the visible breadcrumb and the structured data name the same URL.
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
 * Breadcrumb items for the current main query. Names are raw text (escape on output).
 *
 * @return array<int, array{name: string, url: string}> Empty on the front page and 404.
 */
function nh_editorial_breadcrumb_items() {
	if ( is_front_page() || is_404() ) {
		return array();
	}

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
		$items[] = array(
			/* translators: %s: search query. */
			'name' => sprintf( __( 'Search results for: %s', 'nguyen-ha-editorial' ), get_search_query( false ) ),
			'url'  => get_search_link( get_search_query( false ) ),
		);
	} elseif ( is_archive() ) {
		$items[] = array(
			'name' => wp_strip_all_tags( get_the_archive_title() ),
			'url'  => nh_editorial_archive_url(),
		);
	}

	/**
	 * Filters the visible breadcrumb items.
	 *
	 * @param array $items Items of name and url.
	 */
	return apply_filters( 'nh_editorial_breadcrumb_items', $items );
}

/**
 * Print the breadcrumb component.
 *
 * @return void
 */
function nh_editorial_breadcrumbs() {
	get_template_part( 'template-parts/components/breadcrumb' );
}
