<?php
/**
 * HTML builders for the editorial extras: summary, takeaways, FAQ, sources and the update date.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns already sanitized data into semantic HTML. Every value is escaped again on output (and FAQ answers
 * pass wp_kses again), so the markup stays safe even if data reaches this class without being sanitized.
 * Classes are prefixed `nh-seo-`; each builder returns '' when there is nothing to show.
 */
final class NH_SEO_Renderer {

	/**
	 * Resolve the post of a public renderer and make sure its extras may be shown.
	 *
	 * @param WP_Post|int|null $post Post or ID. Defaults to the current post.
	 * @return WP_Post|null Null when there is no post, or it is password protected and not unlocked.
	 */
	public static function target( $post ) {
		$post = get_post( $post );
		if ( ! $post instanceof WP_Post || post_password_required( $post ) ) {
			return null;
		}
		return $post;
	}

	/**
	 * The "content updated" line.
	 *
	 * @param string $date Date as Y-m-d.
	 * @return string
	 */
	public static function updated( $date ) {
		$date = nh_seo_sanitize_date( $date );
		if ( '' === $date ) {
			return '';
		}
		$object = date_create_immutable_from_format( '!Y-m-d', $date, wp_timezone() );
		if ( ! $object ) {
			return '';
		}

		return '<p class="nh-seo-updated"><span class="nh-seo-updated__label">' . esc_html__( 'Content updated:', 'nguyen-ha-seo-companion' ) . '</span> '
			. '<time datetime="' . esc_attr( $date ) . '">' . esc_html( wp_date( get_option( 'date_format' ), $object->getTimestamp(), wp_timezone() ) ) . '</time></p>';
	}

	/**
	 * The answer-first summary.
	 *
	 * @param string $summary Plain text summary.
	 * @return string
	 */
	public static function summary( $summary ) {
		$summary = is_scalar( $summary ) ? trim( (string) $summary ) : '';
		if ( '' === $summary ) {
			return '';
		}

		return '<section class="nh-seo-block nh-seo-summary">'
			. self::title( __( 'Quick answer', 'nguyen-ha-seo-companion' ) )
			. '<p class="nh-seo-summary__text">' . esc_html( $summary ) . '</p>'
			. '</section>';
	}

	/**
	 * The key takeaways list.
	 *
	 * @param array $items Plain text items.
	 * @return string
	 */
	public static function takeaways( $items ) {
		$list = '';
		foreach ( is_array( $items ) ? $items : array() as $item ) {
			if ( is_scalar( $item ) && '' !== trim( (string) $item ) ) {
				$list .= '<li>' . esc_html( trim( (string) $item ) ) . '</li>';
			}
		}
		if ( '' === $list ) {
			return '';
		}

		return '<section class="nh-seo-block nh-seo-takeaways">'
			. self::title( __( 'Key takeaways', 'nguyen-ha-seo-companion' ) )
			. '<ul class="nh-seo-takeaways__list">' . $list . '</ul>'
			. '</section>';
	}

	/**
	 * The FAQ. Every question and answer is always visible (no accordion), which keeps the content
	 * available without JavaScript and consistent with the FAQPage structured data.
	 *
	 * @param array $items Items of question and answer (answers may hold the allowed HTML).
	 * @return string
	 */
	public static function faq( $items ) {
		$list = '';
		foreach ( is_array( $items ) ? $items : array() as $item ) {
			if ( ! is_array( $item ) || empty( $item['question'] ) || empty( $item['answer'] ) || ! is_scalar( $item['question'] ) || ! is_scalar( $item['answer'] ) ) {
				continue;
			}
			$answer = wp_kses( wpautop( (string) $item['answer'] ), nh_seo_allowed_html(), nh_seo_allowed_protocols() );
			if ( '' === trim( wp_strip_all_tags( $answer ) ) ) {
				continue;
			}
			$list .= '<div class="nh-seo-faq__item">'
				. '<h3 class="nh-seo-faq__question">' . esc_html( trim( (string) $item['question'] ) ) . '</h3>'
				. '<div class="nh-seo-faq__answer">' . $answer . '</div>'
				. '</div>';
		}
		if ( '' === $list ) {
			return '';
		}

		return '<section class="nh-seo-block nh-seo-faq">'
			. self::title( __( 'Frequently asked questions', 'nguyen-ha-seo-companion' ) )
			. $list
			. '</section>';
	}

	/**
	 * The references list.
	 *
	 * @param array $items Items of title, url and publisher.
	 * @return string
	 */
	public static function sources( $items ) {
		$list = '';
		foreach ( is_array( $items ) ? $items : array() as $item ) {
			if ( ! is_array( $item ) || empty( $item['url'] ) || ! is_scalar( $item['url'] ) ) {
				continue;
			}
			$url = esc_url( trim( (string) $item['url'] ), nh_seo_allowed_protocols() );
			if ( '' === $url ) {
				continue;
			}

			$title = isset( $item['title'] ) && is_scalar( $item['title'] ) ? trim( (string) $item['title'] ) : '';
			if ( '' === $title ) {
				$host  = wp_parse_url( $url, PHP_URL_HOST );
				$title = $host ? (string) $host : $url;
			}

			$list .= '<li class="nh-seo-sources__item"><a href="' . $url . '">' . esc_html( $title ) . '</a>';
			if ( ! empty( $item['publisher'] ) && is_scalar( $item['publisher'] ) ) {
				$list .= ' <span class="nh-seo-sources__publisher">(' . esc_html( trim( (string) $item['publisher'] ) ) . ')</span>';
			}
			$list .= '</li>';
		}
		if ( '' === $list ) {
			return '';
		}

		return '<section class="nh-seo-block nh-seo-sources">'
			. self::title( __( 'Sources', 'nguyen-ha-seo-companion' ) )
			. '<ol class="nh-seo-sources__list">' . $list . '</ol>'
			. '</section>';
	}

	/**
	 * A block heading.
	 *
	 * @param string $text Already translated heading text.
	 * @return string
	 */
	private static function title( $text ) {
		return '<h2 class="nh-seo-block__title">' . esc_html( $text ) . '</h2>';
	}
}
