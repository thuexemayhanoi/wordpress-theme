<?php
/**
 * Data model: the Topic Cluster taxonomy and the registered post, term and user meta.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the taxonomy and every meta key of docs/DATA-CONTRACT.md with REST schemas, sanitizers and
 * capability checks. The sanitizing logic itself lives in includes/functions.php and is only referenced here.
 */
final class NH_SEO_Data_Model {

	/**
	 * Post types that carry the editorial meta.
	 *
	 * @var string[]
	 */
	const POST_TYPES = array( 'post', 'page' );

	/**
	 * Maximum length of an author job title.
	 *
	 * @var int
	 */
	const LIMIT_JOB_TITLE = 120;

	/**
	 * Register hooks. The taxonomy and the meta are registered early on `init` so that every other
	 * `init` callback (and the REST API) can rely on them.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_meta' ), 6 );

		foreach ( self::POST_TYPES as $post_type ) {
			add_filter( 'rest_prepare_' . $post_type, array( __CLASS__, 'filter_rest_post_meta' ), 10, 3 );
		}
		add_filter( 'rest_' . NH_SEO_Keys::TAXONOMY_CLUSTER . '_query', array( __CLASS__, 'filter_rest_cluster_query' ) );
	}

	/**
	 * REST: keep editorial fields away from readers who must not see them.
	 *
	 * The keywords are an editor's working note (the editor screen says visitors never see them), and a
	 * password-protected post keeps everything behind its password, including the summary, the takeaways, the FAQ and
	 * the sources (core only hides the body). A user who can edit the post, such as the publishing workflow, still
	 * sees every field; so does a reader who sends the post password, checked the way core checks it for the body.
	 *
	 * @param WP_REST_Response $response Response.
	 * @param WP_Post          $post     Post.
	 * @param WP_REST_Request  $request  Request.
	 * @return WP_REST_Response
	 */
	public static function filter_rest_post_meta( $response, $post, $request ) {
		if ( ! $response instanceof WP_REST_Response || ! $post instanceof WP_Post || current_user_can( 'edit_post', $post->ID ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( ! is_array( $data ) || ! isset( $data['meta'] ) || ! is_array( $data['meta'] ) ) {
			return $response;
		}

		$given  = isset( $request['password'] ) && is_scalar( $request['password'] ) ? (string) $request['password'] : '';
		$locked = '' !== $post->post_password && ! ( '' !== $given && hash_equals( $post->post_password, $given ) );

		foreach ( array_keys( $data['meta'] ) as $key ) {
			if ( NH_SEO_Keys::META_KEYWORDS === $key || ( $locked && 0 === strpos( (string) $key, 'nh_' ) ) ) {
				unset( $data['meta'][ $key ] );
			}
		}
		$response->set_data( $data );
		return $response;
	}

	/**
	 * REST: the names of clusters that only draft posts use are not for visitors. Clusters are created from a hidden
	 * field when a post is saved, drafts included, and core lists every term of a REST-enabled taxonomy to anyone.
	 *
	 * @param array $args Query arguments of the terms request.
	 * @return array
	 */
	public static function filter_rest_cluster_query( $args ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			$args['hide_empty'] = true;
		}
		return $args;
	}

	/**
	 * Register the taxonomy and all meta keys. Also used by the test suite, which resets registrations.
	 *
	 * @return void
	 */
	public static function register() {
		self::register_taxonomy();
		self::register_meta();
	}

	/**
	 * Register the flat, non-public Topic Cluster taxonomy.
	 *
	 * Clusters only group posts for internal linking: they have no front-end URL, no archive and no
	 * sitemap entry, so they can never compete with category pages.
	 *
	 * @return void
	 */
	public static function register_taxonomy() {
		$labels = array(
			'name'                       => _x( 'Topic Clusters', 'taxonomy general name', 'nguyen-ha-seo-companion' ),
			'singular_name'              => _x( 'Topic Cluster', 'taxonomy singular name', 'nguyen-ha-seo-companion' ),
			'menu_name'                  => __( 'Topic Clusters', 'nguyen-ha-seo-companion' ),
			'all_items'                  => __( 'All Topic Clusters', 'nguyen-ha-seo-companion' ),
			'edit_item'                  => __( 'Edit Topic Cluster', 'nguyen-ha-seo-companion' ),
			'update_item'                => __( 'Update Topic Cluster', 'nguyen-ha-seo-companion' ),
			'add_new_item'               => __( 'Add New Topic Cluster', 'nguyen-ha-seo-companion' ),
			'new_item_name'              => __( 'New Topic Cluster Name', 'nguyen-ha-seo-companion' ),
			'search_items'               => __( 'Search Topic Clusters', 'nguyen-ha-seo-companion' ),
			'popular_items'              => __( 'Popular Topic Clusters', 'nguyen-ha-seo-companion' ),
			'separate_items_with_commas' => __( 'Separate topic clusters with commas', 'nguyen-ha-seo-companion' ),
			'add_or_remove_items'        => __( 'Add or remove topic clusters', 'nguyen-ha-seo-companion' ),
			'choose_from_most_used'      => __( 'Choose from the most used topic clusters', 'nguyen-ha-seo-companion' ),
			'not_found'                  => __( 'No topic clusters found.', 'nguyen-ha-seo-companion' ),
			'no_terms'                   => __( 'No topic clusters', 'nguyen-ha-seo-companion' ),
			'items_list_navigation'      => __( 'Topic clusters list navigation', 'nguyen-ha-seo-companion' ),
			'items_list'                 => __( 'Topic clusters list', 'nguyen-ha-seo-companion' ),
			'back_to_items'              => __( '&larr; Go to Topic Clusters', 'nguyen-ha-seo-companion' ),
		);

		register_taxonomy(
			NH_SEO_Keys::TAXONOMY_CLUSTER,
			array( 'post' ),
			array(
				'labels'             => $labels,
				'description'        => __( 'Groups articles into topics for internal linking. Clusters have no public pages of their own.', 'nguyen-ha-seo-companion' ),
				'hierarchical'       => false,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'rest_base'          => 'topic_clusters',
				'show_in_nav_menus'  => false,
				'show_tagcloud'      => false,
				'rewrite'            => false,
				'query_var'          => false,
			)
		);
	}

	/**
	 * Register post, term and user meta.
	 *
	 * @return void
	 */
	public static function register_meta() {
		foreach ( self::post_meta_definitions() as $key => $definition ) {
			$args                  = $definition['args'];
			$args['single']        = true;
			$args['auth_callback'] = array( __CLASS__, 'can_edit_post_meta' );
			foreach ( $definition['post_types'] as $post_type ) {
				register_post_meta( $post_type, $key, $args );
			}
		}

		foreach ( array( 'category', NH_SEO_Keys::TAXONOMY_CLUSTER ) as $taxonomy ) {
			register_term_meta(
				$taxonomy,
				NH_SEO_Keys::TERM_PILLAR,
				array(
					'type'              => 'integer',
					'description'       => __( 'ID of the published post or page that acts as the Pillar for this topic.', 'nguyen-ha-seo-companion' ),
					'single'            => true,
					'default'           => 0,
					'show_in_rest'      => true,
					'sanitize_callback' => 'nh_seo_sanitize_pillar_id',
					'auth_callback'     => array( __CLASS__, 'can_edit_term_meta' ),
				)
			);
		}

		register_meta(
			'user',
			NH_SEO_Keys::USER_JOB_TITLE,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_job_title' ),
				'auth_callback'     => array( __CLASS__, 'can_edit_user_meta' ),
			)
		);
		register_meta(
			'user',
			NH_SEO_Keys::USER_SAMEAS,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_sameas' ),
				'auth_callback'     => array( __CLASS__, 'can_edit_user_meta' ),
			)
		);
	}

	/**
	 * Registration arguments of every post meta key, with the post types it applies to.
	 *
	 * Limits are not part of the REST schemas on purpose: the shared sanitizers truncate, so a long
	 * value from the AI Writer is shortened instead of failing the whole publish request.
	 *
	 * @return array<string, array{post_types: string[], args: array}>
	 */
	public static function post_meta_definitions() {
		$both = self::POST_TYPES;

		return array(
			NH_SEO_Keys::META_SUMMARY         => array(
				'post_types' => $both,
				'args'       => array(
					'type'              => 'string',
					'description'       => __( 'Answer-first summary shown at the top of the article. Plain text, 800 characters.', 'nguyen-ha-seo-companion' ),
					'default'           => '',
					'show_in_rest'      => true,
					'sanitize_callback' => 'nh_seo_sanitize_summary',
				),
			),
			NH_SEO_Keys::META_TAKEAWAYS       => array(
				'post_types' => $both,
				'args'       => array(
					'type'              => 'array',
					'description'       => __( 'Key takeaways: up to 10 plain-text items of 300 characters.', 'nguyen-ha-seo-companion' ),
					'default'           => array(),
					'show_in_rest'      => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
					'sanitize_callback' => 'nh_seo_sanitize_takeaways',
				),
			),
			NH_SEO_Keys::META_FAQ             => array(
				'post_types' => $both,
				'args'       => array(
					'type'              => 'array',
					'description'       => __( 'Frequently asked questions: up to 20 items with a question and an answer that may contain basic HTML.', 'nguyen-ha-seo-companion' ),
					'default'           => array(),
					'show_in_rest'      => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'question' => array( 'type' => 'string' ),
									'answer'   => array( 'type' => 'string' ),
								),
							),
						),
					),
					'sanitize_callback' => 'nh_seo_sanitize_faq',
				),
			),
			NH_SEO_Keys::META_SOURCES         => array(
				'post_types' => $both,
				'args'       => array(
					'type'              => 'array',
					'description'       => __( 'References: up to 15 items with a title, an http(s) URL and a publisher.', 'nguyen-ha-seo-companion' ),
					'default'           => array(),
					'show_in_rest'      => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'title'     => array( 'type' => 'string' ),
									'url'       => array( 'type' => 'string' ),
									'publisher' => array( 'type' => 'string' ),
								),
							),
						),
					),
					'sanitize_callback' => 'nh_seo_sanitize_sources',
				),
			),
			NH_SEO_Keys::META_UPDATED         => array(
				'post_types' => $both,
				'args'       => array(
					'type'              => 'string',
					'description'       => __( 'The date (YYYY-MM-DD) the content was really updated. Invalid or future dates are dropped.', 'nguyen-ha-seo-companion' ),
					'default'           => '',
					'show_in_rest'      => true,
					'sanitize_callback' => 'nh_seo_sanitize_date',
				),
			),
			NH_SEO_Keys::META_CLUSTER_ALIAS   => array(
				'post_types' => $both,
				'args'       => array(
					'type'              => 'string',
					'description'       => __( 'Topic Cluster slug or name. It is synced to the Topic Cluster taxonomy.', 'nguyen-ha-seo-companion' ),
					'default'           => '',
					'show_in_rest'      => true,
					'sanitize_callback' => array( __CLASS__, 'sanitize_cluster_alias' ),
				),
			),
			NH_SEO_Keys::META_PRIMARY_CLUSTER => array(
				'post_types' => $both,
				'args'       => array(
					'type'              => 'integer',
					'description'       => __( 'Term ID of the primary Topic Cluster. It must be assigned to the post to count.', 'nguyen-ha-seo-companion' ),
					'default'           => 0,
					'show_in_rest'      => true,
					'sanitize_callback' => array( __CLASS__, 'sanitize_primary_cluster' ),
				),
			),
			NH_SEO_Keys::META_PILLAR          => array(
				'post_types' => $both,
				'args'       => array(
					'type'              => 'integer',
					'description'       => __( 'ID of the published post or page that is the Pillar of this article.', 'nguyen-ha-seo-companion' ),
					'default'           => 0,
					'show_in_rest'      => true,
					'sanitize_callback' => 'nh_seo_sanitize_pillar_id',
				),
			),
			NH_SEO_Keys::META_HUB_TERM        => array(
				'post_types' => array( 'page' ),
				'args'       => array(
					'type'              => 'string',
					'description'       => __( 'Pages only. "category:<id>" or "topic_cluster:<id>": the topic this Pillar or Hub page lists.', 'nguyen-ha-seo-companion' ),
					'default'           => '',
					'show_in_rest'      => true,
					'sanitize_callback' => array( __CLASS__, 'sanitize_hub_term' ),
				),
			),
			NH_SEO_Keys::META_LOCAL_BUSINESS  => array(
				'post_types' => $both,
				'args'       => array(
					'type'              => 'boolean',
					'description'       => __( 'The page has verified local-business context.', 'nguyen-ha-seo-companion' ),
					'default'           => false,
					'show_in_rest'      => true,
					'sanitize_callback' => 'nh_seo_sanitize_flag',
				),
			),
			NH_SEO_Keys::META_KEYWORDS        => array(
				'post_types' => $both,
				'args'       => array(
					'type'              => 'string',
					'description'       => __( 'Extra comma separated keywords for the internal search index only.', 'nguyen-ha-seo-companion' ),
					'default'           => '',
					'show_in_rest'      => true,
					'sanitize_callback' => 'nh_seo_sanitize_keywords',
				),
			),
		);
	}

	/**
	 * Sanitize the Topic Cluster alias (a slug or a name).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_cluster_alias( $value ) {
		return nh_seo_sanitize_line( $value, NH_SEO_Keys::LIMIT_CLUSTER_ALIAS );
	}

	/**
	 * Sanitize a primary cluster term ID: it must be an existing Topic Cluster term. 0 when invalid.
	 *
	 * Whether the term is assigned to the post is checked on read by nh_seo_get_primary_cluster().
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function sanitize_primary_cluster( $value ) {
		$term_id = self::sanitize_id( $value );
		if ( ! $term_id ) {
			return 0;
		}
		return get_term( $term_id, NH_SEO_Keys::TAXONOMY_CLUSTER ) instanceof WP_Term ? $term_id : 0;
	}

	/**
	 * Strict ID parsing: a positive integer or a string of digits. 0 for anything else.
	 *
	 * Unlike absint(), "12abc" is not 12, "1e3" is not 1000 and -5 is not 5.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function sanitize_id( $value ) {
		if ( is_string( $value ) ) {
			$value = trim( $value );
			return ctype_digit( $value ) && strlen( $value ) < 19 ? (int) $value : 0;
		}
		return is_int( $value ) ? max( 0, $value ) : 0;
	}

	/**
	 * Sanitize a hub term reference such as "category:12" or "topic_cluster:7". '' when invalid or when the term does not exist.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_hub_term( $value ) {
		$parsed = self::parse_hub_term( $value );
		if ( ! $parsed ) {
			return '';
		}
		$term = get_term( $parsed['term_id'], $parsed['taxonomy'] );
		return $term instanceof WP_Term ? $parsed['taxonomy'] . ':' . $term->term_id : '';
	}

	/**
	 * Split a hub term reference into its taxonomy and term ID.
	 *
	 * @param mixed $value Stored value such as "category:12".
	 * @return array{taxonomy: string, term_id: int}|null Null when the value is not a valid reference.
	 */
	public static function parse_hub_term( $value ) {
		if ( ! is_scalar( $value ) || ! preg_match( '/^(category|topic_cluster):(\d{1,18})$/', trim( (string) $value ), $match ) ) {
			return null;
		}
		return array(
			'taxonomy' => $match[1],
			'term_id'  => (int) $match[2],
		);
	}

	/**
	 * Sanitize an author job title.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_job_title( $value ) {
		return nh_seo_sanitize_line( $value, self::LIMIT_JOB_TITLE );
	}

	/**
	 * Sanitize the profile URL list of an author. Stored as one URL per line.
	 *
	 * @param mixed $value Newline separated string or array.
	 * @return string
	 */
	public static function sanitize_sameas( $value ) {
		return implode( "\n", nh_seo_sanitize_url_list( $value ) );
	}

	/**
	 * Meta authorization: whoever may edit the post may edit its editorial meta.
	 *
	 * @param bool   $allowed  Decision so far.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Post ID.
	 * @param int    $user_id  User ID.
	 * @return bool
	 */
	public static function can_edit_post_meta( $allowed, $meta_key, $post_id, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		return $allowed && $user_id && user_can( $user_id, 'edit_post', $post_id );
	}

	/**
	 * Meta authorization for term meta: whoever may edit the term may edit its Pillar.
	 *
	 * @param bool   $allowed  Decision so far.
	 * @param string $meta_key Meta key.
	 * @param int    $term_id  Term ID.
	 * @param int    $user_id  User ID.
	 * @return bool
	 */
	public static function can_edit_term_meta( $allowed, $meta_key, $term_id, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		return $allowed && $user_id && user_can( $user_id, 'edit_term', $term_id );
	}

	/**
	 * Meta authorization for user meta: whoever may edit the user may edit these fields.
	 *
	 * @param bool   $allowed  Decision so far.
	 * @param string $meta_key Meta key.
	 * @param int    $user_id  Edited user ID.
	 * @param int    $actor_id Acting user ID.
	 * @return bool
	 */
	public static function can_edit_user_meta( $allowed, $meta_key, $user_id, $actor_id = 0 ) {
		$actor_id = $actor_id ? (int) $actor_id : get_current_user_id();
		return $allowed && $actor_id && user_can( $actor_id, 'edit_user', $user_id );
	}
}
