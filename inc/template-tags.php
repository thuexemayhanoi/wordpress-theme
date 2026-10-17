<?php
/**
 * Template tags shared by every template and component.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Safe heading tag for components. Components never emit an H1 (each template owns exactly one).
 *
 * @param int|string $level Requested level (2 to 4).
 * @return string h2 | h3 | h4
 */
function nh_editorial_heading_tag( $level ) {
	$level = (int) preg_replace( '/\D/', '', (string) $level );
	return 'h' . max( 2, min( 4, $level ) );
}

/**
 * Query that returns the given posts in the given order.
 *
 * @param int[] $ids Post IDs.
 * @return WP_Query
 */
function nh_editorial_posts_by_ids( array $ids ) {
	$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

	return new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'post__in'            => empty( $ids ) ? array( 0 ) : $ids,
			'orderby'             => 'post__in',
			'posts_per_page'      => max( 1, count( $ids ) ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
}

/**
 * Estimated reading time in whole minutes (at least one).
 *
 * @param WP_Post|int|null $post Post (defaults to the current post).
 * @return int
 */
function nh_editorial_reading_time( $post = null ) {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post ) {
		return 1;
	}

	$text  = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
	$words = (int) preg_match_all( '/[\p{L}\p{N}]+/u', $text );

	/**
	 * Filters the reading speed in words per minute.
	 *
	 * @param int $wpm Words per minute.
	 */
	$wpm = max( 100, (int) apply_filters( 'nh_editorial_words_per_minute', 200 ) );

	return max( 1, (int) ceil( $words / $wpm ) );
}

/**
 * Visible <time> element, the one place that formats dates for people and machines.
 *
 * @param string $iso     Machine readable date (ISO 8601 or Y-m-d).
 * @param string $label   Human readable date.
 * @param string $classes Extra CSS classes.
 * @return string Safe HTML.
 */
function nh_editorial_time_tag( $iso, $label, $classes = '' ) {
	return '<time class="' . esc_attr( trim( 'meta__time ' . $classes ) ) . '" datetime="' . esc_attr( $iso ) . '">' . esc_html( $label ) . '</time>';
}

/**
 * Print the meta line of an article or card.
 *
 * Arguments (all booleans, default false): author, date, updated, reading, category.
 *
 * @param array $args Which parts to show.
 * @return void
 */
function nh_editorial_post_meta( array $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'author'   => false,
			'date'     => false,
			'updated'  => false,
			'reading'  => false,
			'category' => false,
		)
	);
	$post = get_post();
	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$parts = array();

	if ( $args['category'] ) {
		$term = nh_editorial_primary_category( $post );
		if ( $term ) {
			$link = get_term_link( $term );
			if ( ! is_wp_error( $link ) ) {
				$parts[] = '<a class="meta__term" href="' . esc_url( $link ) . '">' . esc_html( $term->name ) . '</a>';
			}
		}
	}

	if ( $args['author'] ) {
		// A post whose author was deleted (or never set) has no author page and no name to show.
		$author_name = get_the_author_meta( 'display_name', (int) $post->post_author );
		if ( '' !== trim( (string) $author_name ) ) {
			$parts[] = '<span class="meta__author">' . nh_editorial_icon( 'user' ) . '<a href="' . esc_url( get_author_posts_url( (int) $post->post_author ) ) . '" rel="author">' . esc_html( $author_name ) . '</a></span>';
		}
	}

	if ( $args['date'] ) {
		$parts[] = '<span class="meta__date">' . nh_editorial_icon( 'calendar' ) . '<span class="screen-reader-text">' . esc_html__( 'Published', 'nguyen-ha-editorial' ) . ' </span>'
			. nh_editorial_time_tag( get_the_date( DATE_W3C, $post ), get_the_date( '', $post ) ) . '</span>';
	}

	if ( $args['updated'] ) {
		$updated = nh_editorial_updated_date( $post );
		if ( $updated ) {
			$parts[] = '<span class="meta__updated">' . esc_html__( 'Updated', 'nguyen-ha-editorial' ) . ' ' . nh_editorial_time_tag( $updated['iso'], $updated['label'], 'meta__time--updated' ) . '</span>';
		}
	}

	if ( $args['reading'] && nh_editorial_option( 'nh_reading_time' ) ) {
		$minutes = nh_editorial_reading_time( $post );
		$parts[] = '<span class="meta__reading">' . nh_editorial_icon( 'clock' ) . esc_html(
			sprintf(
				/* translators: %d: minutes. */
				_n( '%d min read', '%d min read', $minutes, 'nguyen-ha-editorial' ),
				$minutes
			)
		) . '</span>';
	}

	if ( empty( $parts ) ) {
		return;
	}

	// Every part above is escaped where it is built.
	echo '<div class="meta">' . implode( '', $parts ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Print numbered pagination with crawlable links and no extra heading.
 *
 * @return void
 */
function nh_editorial_posts_pagination() {
	$template = static function () {
		return '<nav class="navigation %1$s" aria-label="%4$s"><div class="nav-links">%3$s</div></nav>';
	};
	add_filter( 'navigation_markup_template', $template );

	the_posts_pagination(
		array(
			'mid_size'           => 1,
			'end_size'           => 1,
			'prev_text'          => nh_editorial_icon( 'arrow-left' ) . '<span class="screen-reader-text">' . esc_html__( 'Previous page', 'nguyen-ha-editorial' ) . '</span>',
			'next_text'          => nh_editorial_icon( 'arrow-right' ) . '<span class="screen-reader-text">' . esc_html__( 'Next page', 'nguyen-ha-editorial' ) . '</span>',
			'before_page_number' => '<span class="screen-reader-text">' . esc_html__( 'Page', 'nguyen-ha-editorial' ) . ' </span>',
			'aria_label'         => __( 'Pagination', 'nguyen-ha-editorial' ),
		)
	);

	remove_filter( 'navigation_markup_template', $template );
}

/**
 * "12 articles" label.
 *
 * @param int $count Number of articles.
 * @return string Plain text.
 */
function nh_editorial_count_label( $count ) {
	$count = absint( $count );
	/* translators: %s: number of articles. */
	return sprintf( _n( '%s article', '%s articles', $count, 'nguyen-ha-editorial' ), number_format_i18n( $count ) );
}

/**
 * Initials of the site name for the logo fallback (two letters at most).
 *
 * @return string
 */
function nh_editorial_site_initials() {
	$words    = preg_split( '/\s+/u', trim( wp_strip_all_tags( get_bloginfo( 'name' ) ) ), -1, PREG_SPLIT_NO_EMPTY );
	$initials = '';
	foreach ( array_slice( (array) $words, 0, 2 ) as $word ) {
		$initials .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
	}
	return '' !== $initials ? $initials : 'N';
}

/**
 * Brand markup for the header: the custom logo (and the optional dark-mode logo) or a text mark.
 *
 * @return string Safe HTML.
 */
function nh_editorial_brand_markup() {
	$name    = get_bloginfo( 'name' );
	$logo_id = (int) get_theme_mod( 'custom_logo' );

	if ( $logo_id && wp_attachment_is_image( $logo_id ) ) {
		$html = '';

		// The dark-mode logo comes first so CSS can hide the light one with a sibling selector.
		$dark_id = (int) nh_editorial_option( 'nh_logo_dark' );
		if ( $dark_id && wp_attachment_is_image( $dark_id ) ) {
			$html .= wp_get_attachment_image(
				$dark_id,
				'full',
				false,
				array(
					'class'    => 'custom-logo custom-logo--dark',
					'alt'      => $name,
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			);
		}

		$html .= wp_get_attachment_image(
			$logo_id,
			'full',
			false,
			array(
				'class'         => 'custom-logo custom-logo--light',
				'alt'           => $name,
				'loading'       => 'eager',
				'fetchpriority' => 'high',
				'decoding'      => 'async',
			)
		);
		return $html;
	}

	return '<span class="brand__mark" aria-hidden="true">' . esc_html( nh_editorial_site_initials() ) . '</span><span class="brand__name">' . esc_html( $name ) . '</span>';
}

/**
 * Posts for the homepage hero: sticky posts first (newest first), then the latest posts.
 *
 * Only published, public, non-password-protected posts. Cached in the versioned fragment cache and
 * refreshed when sticky posts or publication change.
 *
 * @param int $count Number of posts (one large story plus the rest).
 * @return int[]
 */
function nh_editorial_get_featured_ids( $count = 4 ) {
	$count = max( 1, absint( $count ) );

	return nh_editorial_cache_remember(
		'featured:' . $count,
		static function () use ( $count ) {
			$base = array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'has_password'           => false,
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'fields'                 => 'ids',
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			);

			$ids    = array();
			$sticky = array_filter( array_map( 'absint', (array) get_option( 'sticky_posts' ) ) );
			if ( ! empty( $sticky ) ) {
				$query = new WP_Query(
					array_merge(
						$base,
						array(
							'post__in'       => $sticky,
							'posts_per_page' => $count,
						)
					)
				);
				$ids   = array_map( 'intval', $query->posts );
			}

			if ( count( $ids ) < $count ) {
				$query = new WP_Query(
					array_merge(
						$base,
						array(
							'post__not_in'   => $ids,
							'posts_per_page' => $count - count( $ids ),
						)
					)
				);
				$ids   = array_merge( $ids, array_map( 'intval', $query->posts ) );
			}

			return $ids;
		}
	);
}

/**
 * Latest published posts excluding some IDs (static front page "latest" list).
 *
 * @param int   $count   Number of posts.
 * @param int[] $exclude Post IDs to leave out.
 * @return int[]
 */
function nh_editorial_get_latest_ids( $count, array $exclude = array() ) {
	$count   = max( 1, absint( $count ) );
	$exclude = array_values( array_filter( array_map( 'absint', $exclude ) ) );

	return nh_editorial_cache_remember(
		'latest:' . $count . ':' . implode( '-', $exclude ),
		static function () use ( $count, $exclude ) {
			$query = new WP_Query(
				array(
					'post_type'              => 'post',
					'post_status'            => 'publish',
					'has_password'           => false,
					'posts_per_page'         => $count,
					'post__not_in'           => $exclude,
					'ignore_sticky_posts'    => true,
					'no_found_rows'          => true,
					'fields'                 => 'ids',
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			return array_map( 'intval', $query->posts );
		}
	);
}

/**
 * Wrap content tables in a keyboard-focusable scroll region so wide tables never break the layout on
 * small screens (and can be scrolled without a mouse). The table markup itself is untouched.
 *
 * @param string $content Rendered post content.
 * @return string
 */
function nh_editorial_wrap_tables( $content ) {
	if ( is_admin() || ! is_singular() || false === stripos( $content, '<table' ) ) {
		return $content;
	}
	// Nested or unbalanced tables cannot be wrapped safely with a simple pattern: leave them alone.
	if ( substr_count( strtolower( $content ), '<table' ) !== substr_count( strtolower( $content ), '</table>' ) ) {
		return $content;
	}
	if ( preg_match( '#<table\b(?:(?!</table>).)*<table\b#is', $content ) ) {
		return $content;
	}

	$open = '<div class="table-scroll" role="region" tabindex="0" aria-label="' . esc_attr__( 'Scrollable table', 'nguyen-ha-editorial' ) . '">';

	return (string) preg_replace( '#(<table\b.*?</table>)#is', $open . '$1</div>', $content );
}
add_filter( 'the_content', 'nh_editorial_wrap_tables', 30 );
