<?php
/**
 * Schema module: decides what to print, and prints it.
 *
 * Modes (see nh_seo_schema_mode()):
 *  off     nothing is printed.
 *  extras  an SEO plugin owns titles, canonicals and the graph. Only separate JSON-LD blocks are printed
 *          (FAQPage and LocalBusiness) and only under strict conditions. The SEO plugin's graph is never edited.
 *  full    no SEO plugin: one @graph with Organization, WebSite, the page node, BreadcrumbList, Article and
 *          the FAQPage / LocalBusiness nodes under the same conditions as in extras mode.
 *
 * Nothing is printed for 404, previews, search results (extras mode), drafts, private or password protected content.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * JSON-LD output.
 */
final class NH_SEO_Schema {

	/**
	 * JSON encoding flags: readable UTF-8, plain slashes, and "<", ">" and "&" escaped so that no value can
	 * ever close the script element or open markup.
	 */
	const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;

	/**
	 * Register the output hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'print_head' ), 20 );
	}

	/**
	 * Print every JSON-LD block of the current request.
	 *
	 * @return void
	 */
	public static function print_head() {
		foreach ( self::blocks() as $json ) {
			// The JSON is encoded with JSON_HEX_TAG and JSON_HEX_AMP: it cannot contain markup or a closing script tag.
			echo '<script type="application/ld+json">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * JSON strings, one per script element, for the current request.
	 *
	 * @return string[]
	 */
	public static function blocks() {
		$blocks = array();
		foreach ( self::documents() as $document ) {
			$json = self::encode( $document );
			if ( '' !== $json ) {
				$blocks[] = $json;
			}
		}
		return $blocks;
	}

	/**
	 * Encode a document for a script element.
	 *
	 * @param array $document Document.
	 * @return string JSON, or '' when the data cannot be encoded.
	 */
	public static function encode( array $document ) {
		$json = wp_json_encode( $document, self::JSON_FLAGS );
		return is_string( $json ) ? $json : '';
	}

	/**
	 * Documents (arrays, not yet encoded) for the current request.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function documents() {
		$mode = nh_seo_schema_mode();
		if ( 'off' === $mode ) {
			return array();
		}

		$context = NH_SEO_Schema_Context::detect();
		if ( null === $context ) {
			return array();
		}

		$post    = $context['post'];
		$extras  = $post instanceof WP_Post ? nh_seo_get_post_extras( $post->ID ) : array();
		$profile = NH_SEO_Schema_Builder::profile();

		$faq_node = self::faq_node( $context, $extras );
		$lb_node  = self::local_business_node( $context, $profile );

		if ( 'full' === $mode ) {
			$nodes = self::full_nodes( $context, $extras, $profile, $faq_node, $lb_node );
		} else {
			$nodes = array( $faq_node, $lb_node );
		}

		$nodes = NH_SEO_Schema_Builder::clean( $nodes );

		/**
		 * Filters the JSON-LD nodes of the current request just before they are printed.
		 *
		 * In "full" mode the nodes become one @graph. In "extras" mode every node is printed as its own
		 * script element. Nothing in this plugin ever adds ratings, reviews or offers; a filter that does is
		 * responsible for the truth of that data.
		 *
		 * @param array  $nodes   List of nodes (arrays with @type).
		 * @param string $mode    full | extras.
		 * @param array  $context Request context: kind, post, user, url, front.
		 */
		$nodes = apply_filters( 'nh_seo_schema_graph', is_array( $nodes ) ? $nodes : array(), $mode, $context );
		$nodes = self::finalize( $nodes );
		if ( empty( $nodes ) ) {
			return array();
		}

		if ( 'full' === $mode ) {
			return array(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => $nodes,
				),
			);
		}

		$documents = array();
		foreach ( $nodes as $node ) {
			$documents[] = array( '@context' => 'https://schema.org' ) + $node;
		}
		return $documents;
	}

	/**
	 * Keep valid nodes only (arrays with a @type) and drop repeated @id values (first one wins).
	 *
	 * @param mixed $nodes Nodes after filters.
	 * @return array<int, array<string, mixed>>
	 */
	private static function finalize( $nodes ) {
		$out  = array();
		$seen = array();
		foreach ( (array) $nodes as $node ) {
			if ( ! is_array( $node ) || empty( $node['@type'] ) ) {
				continue;
			}
			if ( isset( $node['@id'] ) && is_string( $node['@id'] ) ) {
				if ( isset( $seen[ $node['@id'] ] ) ) {
					continue;
				}
				$seen[ $node['@id'] ] = true;
			}
			$out[] = $node;
		}
		return $out;
	}

	/**
	 * Nodes of the single @graph printed in "full" mode.
	 *
	 * @param array<string, mixed> $context  Request context.
	 * @param array<string, mixed> $extras   Post extras.
	 * @param array<string, mixed> $profile  Business profile.
	 * @param array<string, mixed> $faq_node FAQPage node or empty array.
	 * @param array<string, mixed> $lb_node  LocalBusiness node or empty array.
	 * @return array<int, array<string, mixed>>
	 */
	private static function full_nodes( array $context, array $extras, array $profile, array $faq_node, array $lb_node ) {
		$trail = nh_seo_breadcrumb_trail();
		$post  = $context['post'];
		$user  = $context['user'] instanceof WP_User ? $context['user'] : null;

		$nodes   = array();
		$nodes[] = NH_SEO_Schema_Builder::organization( $profile, ! empty( $lb_node ) );
		$nodes[] = NH_SEO_Schema_Builder::website();
		$nodes[] = NH_SEO_Schema_Builder::webpage( $context, $extras, $trail, $user ? $user->ID : 0 );
		$nodes[] = NH_SEO_Schema_Builder::breadcrumb( $trail, $context['url'] );

		if ( $post instanceof WP_Post && 'post' === $post->post_type ) {
			$nodes[] = NH_SEO_Schema_Builder::article( $post, $extras, $context['url'] );
			$author  = get_userdata( (int) $post->post_author );
			if ( $author instanceof WP_User ) {
				$nodes[] = NH_SEO_Schema_Builder::person( $author );
			}
		}

		if ( $user ) {
			$nodes[] = NH_SEO_Schema_Builder::person( $user );
		}

		$nodes[] = $faq_node;
		$nodes[] = $lb_node;
		return $nodes;
	}

	/**
	 * FAQPage node, only when the FAQ is visible to readers, holds enough items and nobody else prints it.
	 *
	 * @param array<string, mixed> $context Request context.
	 * @param array<string, mixed> $extras  Post extras.
	 * @return array<string, mixed> Node or empty array.
	 */
	private static function faq_node( array $context, array $extras ) {
		$post = $context['post'];
		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$faq = isset( $extras['faq'] ) && is_array( $extras['faq'] ) ? $extras['faq'] : array();

		/**
		 * Filters the minimum number of FAQ items needed before FAQPage markup is printed.
		 *
		 * @param int $min Minimum number of items. Default 2.
		 */
		$min = max( 1, (int) apply_filters( 'nh_seo_faq_min_items', 2 ) );

		if ( count( $faq ) < $min || ! nh_seo_faq_is_visible( $post->ID ) || NH_SEO_Schema_Detect::has_external_faq( $post ) ) {
			return array();
		}
		return NH_SEO_Schema_Builder::faq( $faq, $context['url'] );
	}

	/**
	 * LocalBusiness node, only with a complete verified profile AND an explicit flag on the page (or the
	 * front page when the setting allows it), and only when no Local SEO add-on prints it already.
	 *
	 * @param array<string, mixed> $context Request context.
	 * @param array<string, mixed> $profile Business profile.
	 * @return array<string, mixed> Node or empty array.
	 */
	private static function local_business_node( array $context, array $profile ) {
		if ( ! nh_seo_business_is_complete( $profile ) ) {
			return array();
		}

		$post     = $context['post'];
		$flagged  = $post instanceof WP_Post && 1 === nh_seo_sanitize_flag( get_post_meta( $post->ID, NH_SEO_Keys::META_LOCAL_BUSINESS, true ) );
		$settings = nh_seo_get_settings();
		$on_home  = $context['front'] && ! empty( $settings['localbusiness_on_home'] );

		if ( ! $flagged && ! $on_home ) {
			return array();
		}
		if ( NH_SEO_Schema_Detect::has_external_localbusiness() ) {
			return array();
		}
		return NH_SEO_Schema_Builder::local_business( $profile );
	}
}
