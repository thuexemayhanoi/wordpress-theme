<?php
/**
 * Builds the folded search document of one post.
 *
 * The title column holds the title. The taxonomy column holds category, tag and topic-cluster names, the extra
 * keywords (nh_keywords) and the focus keyword of an SEO plugin (Yoast SEO, Rank Math) when present. The body
 * column holds the UNIQUE tokens of at least three characters from the summary, the manual excerpt, the
 * headings, the content, the takeaways and the FAQ, in that order of importance, capped (default 4000).
 *
 * Reads only. A password protected or non-public post is never turned into a document by the indexer.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Document builder.
 */
final class NH_SEO_Search_Document {

	/**
	 * Post meta keys written by SEO plugins that hold the focus keyword.
	 *
	 * @return string[]
	 */
	public static function focus_keyword_keys() {
		return array( '_yoast_wpseo_focuskw', 'rank_math_focus_keyword' );
	}

	/**
	 * Every post meta key whose change must refresh the index.
	 *
	 * @return string[]
	 */
	public static function watched_meta_keys() {
		return array_merge(
			array(
				NH_SEO_Keys::META_KEYWORDS,
				NH_SEO_Keys::META_SUMMARY,
				NH_SEO_Keys::META_TAKEAWAYS,
				NH_SEO_Keys::META_FAQ,
			),
			self::focus_keyword_keys()
		);
	}

	/**
	 * Build the document.
	 *
	 * @param WP_Post $post Post.
	 * @return array{post_type: string, title_fold: string, tax_fold: string, body_fold: string}
	 */
	public static function build( WP_Post $post ) {
		$extras = nh_seo_get_post_extras( $post->ID );

		$faq_text = array();
		foreach ( $extras['faq'] as $item ) {
			$faq_text[] = $item['question'];
			$faq_text[] = $item['answer'];
		}

		/**
		 * Filters the maximum number of unique body tokens stored per post.
		 *
		 * @param int $cap Default 4000.
		 */
		$cap = (int) apply_filters( 'nh_seo_search_body_token_cap', NH_SEO_Search_Text::DEFAULT_BODY_CAP );
		$cap = min( 20000, max( 100, $cap ) );

		$content  = substr( (string) $post->post_content, 0, NH_SEO_Search_Text::MAX_RAW_BYTES );
		$headings = array();
		if ( preg_match_all( '#<h[1-6][^>]*>(.*?)</h[1-6]>#is', $content, $matches ) ) {
			$headings = $matches[1];
		}

		$body = NH_SEO_Search_Text::body_tokens(
			array(
				NH_SEO_Search_Text::fold_html( $extras['summary'] ),
				NH_SEO_Search_Text::fold_html( $post->post_excerpt ),
				NH_SEO_Search_Text::fold_html( implode( ' ', $headings ) ),
				NH_SEO_Search_Text::fold_html( $content ),
				NH_SEO_Search_Text::fold_html( implode( ' ', $extras['takeaways'] ) ),
				NH_SEO_Search_Text::fold_html( implode( ' ', $faq_text ) ),
			),
			$cap
		);

		return array(
			'post_type'  => (string) $post->post_type,
			'title_fold' => NH_SEO_Search_Text::fold_title( get_post_field( 'post_title', $post, 'raw' ) ),
			'tax_fold'   => NH_SEO_Search_Text::fold_taxonomy( self::taxonomy_parts( $post ) ),
			'body_fold'  => $body,
		);
	}

	/**
	 * Strings that describe the post's topics.
	 *
	 * @param WP_Post $post Post.
	 * @return string[]
	 */
	private static function taxonomy_parts( WP_Post $post ) {
		$parts = array();

		foreach ( NH_SEO_Search_Index::indexable_taxonomies() as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $post->post_type, $taxonomy ) ) {
				continue;
			}
			$terms = get_the_terms( $post, $taxonomy );
			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$parts[] = $term->name;
			}
		}

		$parts[] = get_post_meta( $post->ID, NH_SEO_Keys::META_KEYWORDS, true );
		foreach ( self::focus_keyword_keys() as $key ) {
			$parts[] = get_post_meta( $post->ID, $key, true );
		}

		return $parts;
	}
}
