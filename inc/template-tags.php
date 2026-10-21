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
 * Decorative SVG used only when a post or page has no featured image.
 * It is not an image attachment and must not be reported as one to SEO plugins.
 *
 * @param bool $with_term Whether to display an editorial category label.
 * @param int  $post_id   Post/page ID; zero uses the current post.
 * @return string Presentation-only escaped HTML.
 */
function nh_editorial_media_fallback( $with_term = true, $post_id = 0 ) {
    $post = get_post( $post_id ?: get_the_ID() );
    if ( ! $post instanceof WP_Post ) {
        return '';
    }

    $path = (string) wp_parse_url( (string) get_permalink( $post ), PHP_URL_PATH );
    $path = trim( $path, '/' );
    $is_en = 'en' === $path || 0 === strpos( $path, 'en/' );
    $caption = $is_en ? 'EXPLORE HANOI' : 'KHÁM PHÁ HÀ NỘI';
    $term = nh_editorial_primary_category( $post );
    $label = $term instanceof WP_Term ? $term->name : get_bloginfo( 'name' );

    $vector = '<svg class="nh-fallback__vector" viewBox="0 0 360 138" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">'
        . '<circle cx="286" cy="28" r="30" fill="currentColor" stroke="none" opacity=".08"/>'
        . '<circle cx="65" cy="111" r="37" fill="currentColor" stroke="none" opacity=".08"/>'
        . '<path d="M18 109H342M35 115H325M51 121H309" opacity=".56"/>'
        . '<path d="M43 109L93 35L143 109M93 35V109M93 35L192 109M177 109L230 37L281 109M230 37V109M230 37L320 109" opacity=".67"/>'
        . '<g stroke-width="2.6"><circle cx="146" cy="82" r="15"/><circle cx="221" cy="82" r="15"/>'
        . '<path d="M146 82L169 52H194L207 82H221M169 52L183 82L205 56M158 82H183M189 46H201M207 59L215 65M180 52H173"/></g>'
        . '</svg>';

    $html = '<span class="nh-fallback" aria-hidden="true">' . $vector
        . '<span class="nh-fallback__caption">' . esc_html( $caption ) . '</span>';
    if ( $with_term ) {
        $html .= '<span class="nh-fallback__term">' . esc_html( $label ) . '</span>';
    }
    return $html . '</span>';
}

/**
 * The shared card fallback. All three post card sizes call this tag.
 *
 * @return string Escaped presentation HTML.
 */
function nh_editorial_card_placeholder_label() {
    return nh_editorial_media_fallback( true );
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
	if ( ! $post instanceof WP_Post || post_password_required( $post ) ) {
		// A protected text must not reveal its length either.
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

	if ( $args['reading'] && nh_editorial_option( 'nh_reading_time' ) && ! post_password_required( $post ) ) {
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
		$letter    = mb_substr( $word, 0, 1 );
		$initials .= function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $letter ) : strtoupper( $letter );
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
			$ids    = array();
			$sticky = array_filter( array_map( 'absint', (array) get_option( 'sticky_posts' ) ) );
			if ( ! empty( $sticky ) ) {
				$ids = nh_editorial_query_public_ids( array( 'post__in' => $sticky ), $count );
			}

			if ( count( $ids ) < $count ) {
				$ids = array_merge( $ids, nh_editorial_query_public_ids( array( 'post__not_in' => $ids ), $count - count( $ids ) ) );
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
			return nh_editorial_query_public_ids( array( 'post__not_in' => $exclude ), $count );
		}
	);
}

/**
 * Wrap content tables in a keyboard-focusable scroll region so wide tables never break the layout on
 * small screens (and can be scrolled without a mouse). The table markup itself is untouched.
 *
 * The outermost tables are found by walking the opening and closing tags, one linear pass: a regular
 * expression that spans a whole table fails on a nested table over 24 KB (the wrapper's closing tag then
 * lands inside a cell and swallows the rest of the article) and returns nothing at all on a table of about
 * a megabyte. Unbalanced markup is left exactly as it is.
 *
 * @param string $content Rendered post content.
 * @return string
 */
function nh_editorial_wrap_tables( $content ) {
	if ( is_admin() || is_feed() || ! is_singular() || false === stripos( $content, '<table' ) ) {
		return $content;
	}
	if ( ! preg_match_all( '#<(/?)table(?=[\s/>])#i', $content, $tags, PREG_OFFSET_CAPTURE ) ) {
		return $content;
	}

	$depth = 0;
	$from  = 0;
	$spans = array();
	foreach ( $tags[0] as $index => $tag ) {
		if ( '' === $tags[1][ $index ][0] ) {
			if ( 0 === $depth ) {
				$from = $tag[1];
			}
			++$depth;
			continue;
		}
		if ( 0 === $depth ) {
			return $content; // A closing tag with no table open.
		}
		--$depth;
		if ( 0 === $depth ) {
			$to = strpos( $content, '>', $tag[1] );
			if ( false === $to ) {
				return $content;
			}
			$spans[] = array( $from, $to + 1 );
		}
	}
	if ( 0 !== $depth || empty( $spans ) ) {
		return $content;
	}

	$open   = '<div class="table-scroll" role="region" tabindex="0" aria-label="' . esc_attr__( 'Scrollable table', 'nguyen-ha-editorial' ) . '">';
	$output = '';
	$cursor = 0;
	foreach ( $spans as $span ) {
		$output .= substr( $content, $cursor, $span[0] - $cursor ) . $open . substr( $content, $span[0], $span[1] - $span[0] ) . '</div>';
		$cursor  = $span[1];
	}
	return $output . substr( $content, $cursor );
}
add_filter( 'the_content', 'nh_editorial_wrap_tables', 30 );
