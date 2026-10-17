<?php
/**
 * Detection of structured data that another plugin already prints.
 *
 * Two questions matter to the schema module:
 *
 *  - does the content already hold a FAQ block of Yoast SEO or Rank Math (they print their own FAQPage)?
 *  - is a Local SEO add-on (Yoast Local SEO, Rank Math Local SEO module) active (it prints its own LocalBusiness)?
 *
 * Detection uses constants, classes, stored module lists and plain block-name detection only. It never calls
 * into another plugin. Both answers can be overridden with a filter, which is also the extension point for
 * plugins this code does not know. The detection data is a plain list so that tests can exercise the exact
 * same code path with made-up constant and class names instead of defining real plugin constants.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * External structured data detection.
 */
final class NH_SEO_Schema_Detect {

	/**
	 * Block names that print their own FAQPage markup.
	 *
	 * @return string[]
	 */
	public static function faq_block_names() {
		$names = array( 'yoast/faq-block', 'rank-math/faq-block' );

		/**
		 * Filters the block names treated as "prints its own FAQPage".
		 *
		 * @param string[] $names Block names.
		 */
		return (array) apply_filters( 'nh_seo_external_faq_blocks', $names );
	}

	/**
	 * Whether the post content already holds a FAQ block that prints its own structured data.
	 *
	 * @param WP_Post|int $post Post or ID.
	 * @return bool
	 */
	public static function has_external_faq( $post ) {
		$post     = get_post( $post );
		$detected = false;

		if ( $post instanceof WP_Post ) {
			foreach ( self::faq_block_names() as $block_name ) {
				if ( is_string( $block_name ) && '' !== $block_name && has_block( $block_name, $post ) ) {
					$detected = true;
					break;
				}
			}
		}

		/**
		 * Filters whether another plugin already prints FAQPage markup for this post.
		 *
		 * @param bool         $detected Whether a known FAQ block was found in the content.
		 * @param WP_Post|null $post     The post.
		 */
		return (bool) apply_filters( 'nh_seo_has_external_faq', $detected, $post );
	}

	/**
	 * How Local SEO add-ons are recognised.
	 *
	 * Each probe lists constants and classes whose presence means the plugin is active. A probe with a
	 * "module" entry also needs that module to be switched on (Rank Math keeps the slugs of its active
	 * modules in one option).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function local_seo_probes() {
		$probes = array(
			'yoast-local' => array(
				'constants' => array( 'WPSEO_LOCAL_VERSION' ),
				'classes'   => array( 'WPSEO_Local_Core' ),
			),
			'rank-math'   => array(
				'constants' => array( 'RANK_MATH_VERSION' ),
				'classes'   => array( 'RankMath' ),
				'module'    => array(
					'option' => 'rank_math_modules',
					'slug'   => 'local-seo',
				),
			),
		);

		/**
		 * Filters how Local SEO add-ons are detected. Add a probe for a plugin that prints its own LocalBusiness.
		 *
		 * @param array $probes Probes keyed by name: constants, classes and optionally module (option and slug).
		 */
		return (array) apply_filters( 'nh_seo_local_seo_probes', $probes );
	}

	/**
	 * Whether a Local SEO add-on that prints its own LocalBusiness markup is active.
	 *
	 * @return bool
	 */
	public static function has_external_localbusiness() {
		$detected = false;

		foreach ( self::local_seo_probes() as $probe ) {
			if ( is_array( $probe ) && self::probe_matches( $probe ) ) {
				$detected = true;
				break;
			}
		}

		/**
		 * Filters whether another plugin already prints LocalBusiness markup.
		 *
		 * @param bool $detected Whether a Local SEO add-on was detected.
		 */
		return (bool) apply_filters( 'nh_seo_has_external_localbusiness', $detected );
	}

	/**
	 * Whether one probe matches: the plugin is present and, when required, its module is on.
	 *
	 * @param array<string, mixed> $probe Probe.
	 * @return bool
	 */
	private static function probe_matches( array $probe ) {
		$present = false;

		foreach ( isset( $probe['constants'] ) ? (array) $probe['constants'] : array() as $constant ) {
			if ( is_string( $constant ) && '' !== $constant && defined( $constant ) ) {
				$present = true;
				break;
			}
		}
		if ( ! $present ) {
			foreach ( isset( $probe['classes'] ) ? (array) $probe['classes'] : array() as $class_name ) {
				if ( is_string( $class_name ) && '' !== $class_name && class_exists( $class_name, false ) ) {
					$present = true;
					break;
				}
			}
		}
		if ( ! $present ) {
			return false;
		}

		if ( empty( $probe['module'] ) || ! is_array( $probe['module'] ) ) {
			return true;
		}
		$option = isset( $probe['module']['option'] ) ? (string) $probe['module']['option'] : '';
		$slug   = isset( $probe['module']['slug'] ) ? (string) $probe['module']['slug'] : '';
		if ( '' === $option || '' === $slug ) {
			return false;
		}
		$modules = get_option( $option, array() );
		return is_array( $modules ) && in_array( $slug, $modules, true );
	}
}
