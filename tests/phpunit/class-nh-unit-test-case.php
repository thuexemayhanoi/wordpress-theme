<?php
/**
 * Base test case with helpers shared by theme and plugin integration tests.
 *
 * @package nguyen-ha-editorial
 */

/**
 * WP_UnitTestCase plus helpers for pretty permalinks, template rendering and HTML inspection.
 */
abstract class NH_Unit_Test_Case extends WP_UnitTestCase {

	/**
	 * Enable pretty permalinks properly: the test suite registers taxonomies before the permalink
	 * structure exists, so rewrite tags such as %category% must be registered again.
	 *
	 * @param string $structure Permalink structure.
	 * @return void
	 */
	protected function use_permalinks( $structure = '/%category%/%postname%/' ) {
		global $wp_rewrite;
		$this->set_permalink_structure( $structure );
		create_initial_taxonomies();
		$wp_rewrite->flush_rules();
	}

	/**
	 * Make sure the topic_cluster taxonomy exists (the companion plugin registers it when active).
	 *
	 * @return void
	 */
	protected function ensure_cluster_taxonomy() {
		if ( ! taxonomy_exists( 'topic_cluster' ) ) {
			register_taxonomy(
				'topic_cluster',
				'post',
				array(
					'public'  => false,
					'rewrite' => false,
				)
			);
		}
	}

	/**
	 * Template file WordPress would load for the current query (same order as template-loader.php).
	 *
	 * @return string Absolute path.
	 */
	protected function current_template() {
		$map = array(
			'is_404'               => 'get_404_template',
			'is_search'            => 'get_search_template',
			'is_front_page'        => 'get_front_page_template',
			'is_home'              => 'get_home_template',
			'is_privacy_policy'    => 'get_privacy_policy_template',
			'is_post_type_archive' => 'get_post_type_archive_template',
			'is_tax'               => 'get_taxonomy_template',
			'is_attachment'        => 'get_attachment_template',
			'is_single'            => 'get_single_template',
			'is_page'              => 'get_page_template',
			'is_singular'          => 'get_singular_template',
			'is_category'          => 'get_category_template',
			'is_tag'               => 'get_tag_template',
			'is_author'            => 'get_author_template',
			'is_date'              => 'get_date_template',
			'is_archive'           => 'get_archive_template',
		);
		foreach ( $map as $conditional => $getter ) {
			if ( $conditional() ) {
				$template = $getter();
				if ( $template ) {
					return $template;
				}
			}
		}
		return get_index_template();
	}

	/**
	 * Load a URL into the main query and return the full page HTML.
	 *
	 * @param string $url URL to visit.
	 * @return string
	 */
	protected function render_url( $url ) {
		$this->go_to( $url );
		return $this->render_current_page();
	}

	/**
	 * Render the template of the current main query.
	 *
	 * @return string
	 */
	protected function render_current_page() {
		$template = $this->current_template();
		ob_start();
		include $template;
		return (string) ob_get_clean();
	}

	/**
	 * Parse HTML for XPath assertions.
	 *
	 * @param string $html HTML document or fragment.
	 * @return DOMXPath
	 */
	protected function xpath( $html ) {
		$previous = libxml_use_internal_errors( true );
		$doc      = new DOMDocument();
		$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		return new DOMXPath( $doc );
	}

	/**
	 * Count nodes matching an XPath expression.
	 *
	 * @param string $html  HTML.
	 * @param string $query XPath.
	 * @return int
	 */
	protected function count_nodes( $html, $query ) {
		return $this->xpath( $html )->query( $query )->length;
	}

	/**
	 * Create published posts in a category, oldest first, one hour apart.
	 *
	 * @param int $category_id Category ID.
	 * @param int $count       Number of posts.
	 * @param int $start       Unix time of the first post.
	 * @return int[]
	 */
	protected function make_posts( $category_id, $count, $start = 0 ) {
		$start = $start ? $start : time() - ( $count * HOUR_IN_SECONDS * 2 );
		$ids   = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$ids[] = self::factory()->post->create(
				array(
					'post_title'    => 'Post ' . $category_id . '-' . $i,
					'post_status'   => 'publish',
					'post_date'     => gmdate( 'Y-m-d H:i:s', $start + ( $i * HOUR_IN_SECONDS ) ),
					'post_category' => array( $category_id ),
				)
			);
		}
		return $ids;
	}
}
