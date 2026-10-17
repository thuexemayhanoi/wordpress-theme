<?php
/**
 * Topical authority engine: silo, Pillar, Hub and neighbouring articles.
 *
 * Architecture served: Homepage → Pillar → Hub → Silo → Supporting content (docs/DATA-CONTRACT.md §3).
 * Everything is optional metadata: without clusters or pillars the category tree is the silo. All
 * lookups only ever return published, public, non-password-protected posts, and expensive ones are
 * cached in the versioned fragment cache (inc/cache.php). Nothing scans the whole posts table.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Name of the Topic Cluster taxonomy when it is registered (companion plugin), otherwise ''.
 *
 * @return string
 */
function nh_editorial_cluster_taxonomy() {
	return taxonomy_exists( 'topic_cluster' ) ? 'topic_cluster' : '';
}

/**
 * Primary Topic Cluster term of a post.
 *
 * The term stored in nh_primary_cluster when it is assigned to the post, otherwise the assigned term
 * with the lowest ID.
 *
 * @param int $post_id Post ID.
 * @return WP_Term|null
 */
function nh_editorial_get_primary_cluster( $post_id ) {
	$taxonomy = nh_editorial_cluster_taxonomy();
	if ( '' === $taxonomy ) {
		return null;
	}

	$terms = get_the_terms( $post_id, $taxonomy );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return null;
	}

	$by_id = array();
	foreach ( $terms as $term ) {
		$by_id[ (int) $term->term_id ] = $term;
	}

	$primary = (int) get_post_meta( $post_id, 'nh_primary_cluster', true );
	if ( $primary && isset( $by_id[ $primary ] ) ) {
		return $by_id[ $primary ];
	}

	ksort( $by_id );
	return reset( $by_id );
}

/**
 * Human label for a workflow cluster alias such as "scooter-rental-basics".
 *
 * @param string $alias Alias stored in the nh_topic_cluster meta.
 * @return string
 */
function nh_editorial_alias_label( $alias ) {
	$alias = trim( (string) $alias );
	if ( false === strpos( $alias, ' ' ) && preg_match( '/[-_]/', $alias ) ) {
		$alias = ucfirst( trim( str_replace( array( '-', '_' ), ' ', $alias ) ) );
	}
	return $alias;
}

/**
 * The silo of a post: the group used for related articles, previous/next and the topic label.
 *
 * Order: Topic Cluster taxonomy term, then the workflow alias meta (works without the plugin), then
 * the primary category.
 *
 * @param int $post_id Post ID.
 * @return array{type: string, id: string, label: string, term: WP_Term|null, alias: string, category: WP_Term|null}|null
 */
function nh_editorial_get_silo( $post_id ) {
	$post_id  = absint( $post_id );
	$category = nh_editorial_primary_category( $post_id );

	$cluster = nh_editorial_get_primary_cluster( $post_id );
	if ( $cluster ) {
		return array(
			'type'     => 'taxonomy',
			'id'       => 'topic_cluster:' . $cluster->term_id,
			'label'    => $cluster->name,
			'term'     => $cluster,
			'alias'    => '',
			'category' => $category,
		);
	}

	$alias = nh_editorial_clean_text( get_post_meta( $post_id, 'nh_topic_cluster', true ), 120 );
	if ( '' !== $alias ) {
		return array(
			'type'     => 'meta',
			'id'       => 'meta:' . $alias,
			'label'    => nh_editorial_alias_label( $alias ),
			'term'     => null,
			'alias'    => $alias,
			'category' => $category,
		);
	}

	if ( $category ) {
		return array(
			'type'     => 'taxonomy',
			'id'       => 'category:' . $category->term_id,
			'label'    => $category->name,
			'term'     => $category,
			'alias'    => '',
			'category' => $category,
		);
	}

	return null;
}

/**
 * Whether a post or page may be linked to: published, public type, not password protected.
 *
 * @param WP_Post|int|null $post Post or ID.
 * @return bool
 */
function nh_editorial_is_linkable( $post ) {
	$post = get_post( $post );
	return $post instanceof WP_Post
		&& 'publish' === $post->post_status
		&& '' === (string) $post->post_password
		&& is_post_type_viewable( $post->post_type );
}

/**
 * How many more IDs a list query fetches so that the rare password-protected posts can be dropped afterwards.
 *
 * @return int
 */
function nh_editorial_protected_margin() {
	return 8;
}

/**
 * Keep the IDs of published posts that are not password protected, in the order given.
 *
 * One primary-key lookup, so a list never needs "post_password = ''" in its main query.
 *
 * @param int[] $ids Post IDs.
 * @return int[]
 */
function nh_editorial_only_public_ids( array $ids ) {
	global $wpdb;

	$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	if ( empty( $ids ) ) {
		return array();
	}

	$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	// The placeholders are generated from the number of IDs, which were all cast to integers above.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Primary-key lookup, cached by the caller; the placeholders are generated.
	$public = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID IN ({$placeholders}) AND post_status = 'publish' AND post_password = ''", $ids ) );
	$public = array_flip( array_map( 'intval', (array) $public ) );

	return array_values(
		array_filter(
			$ids,
			static function ( $id ) use ( $public ) {
				return isset( $public[ $id ] );
			}
		)
	);
}

/**
 * IDs of the newest public articles for a query, without password-protected ones.
 *
 * WP_Query's has_password => false adds a post_password condition. Next to a category with tens of thousands of
 * articles that condition makes the database read whole post rows instead of walking the date index: 2 to 5 seconds
 * for the homepage on a site of 50,000 articles, against 0.1 s without it. The query therefore fetches a few IDs
 * more and the protected ones are removed with nh_editorial_only_public_ids().
 *
 * @param array<string, mixed> $args  WP_Query arguments (taxonomy, order, post__in, post__not_in...).
 * @param int                  $limit Number of IDs wanted.
 * @return int[]
 */
function nh_editorial_query_public_ids( array $args, $limit ) {
	$limit = max( 1, absint( $limit ) );
	unset( $args['has_password'] );

	$query = new WP_Query(
		array_merge(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			),
			$args,
			array( 'posts_per_page' => $limit + nh_editorial_protected_margin() )
		)
	);

	return array_slice( nh_editorial_only_public_ids( array_map( 'intval', $query->posts ) ), 0, $limit );
}

/**
 * Describe a pillar target when it is linkable.
 *
 * @param int $pillar_id Post or page ID.
 * @param int $exclude   Post ID that must not point at itself.
 * @return array{id: int, title: string, url: string, excerpt: string}|null
 */
function nh_editorial_describe_pillar( $pillar_id, $exclude = 0 ) {
	$pillar_id = absint( $pillar_id );
	$exclude   = absint( $exclude );
	if ( ! $pillar_id || $pillar_id === $exclude || ! nh_editorial_is_linkable( $pillar_id ) ) {
		return null;
	}
	return array(
		'id'      => $pillar_id,
		'title'   => nh_editorial_plain_title( $pillar_id ),
		'url'     => (string) get_permalink( $pillar_id ),
		'excerpt' => wp_trim_words( wp_strip_all_tags( get_the_excerpt( $pillar_id ) ), 28, '…' ),
	);
}

/**
 * Pillar mapped to a term, walking up the ancestors of hierarchical terms (nearest first).
 *
 * @param WP_Term $term    Category or cluster term.
 * @param int     $exclude Post ID that must not point at itself.
 * @return array{id: int, title: string, url: string, excerpt: string}|null
 */
function nh_editorial_get_term_pillar( $term, $exclude = 0 ) {
	if ( ! $term instanceof WP_Term ) {
		return null;
	}

	$ids = array( (int) $term->term_id );
	if ( is_taxonomy_hierarchical( $term->taxonomy ) ) {
		$ids = array_merge( $ids, array_map( 'intval', get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) );
	}

	foreach ( $ids as $term_id ) {
		$pillar = nh_editorial_describe_pillar( get_term_meta( $term_id, 'nh_pillar_id', true ), $exclude );
		if ( $pillar ) {
			return $pillar;
		}
	}
	return null;
}

/**
 * The Pillar of a post: the post's own mapping, then its cluster, then its category (and ancestors).
 *
 * @param int $post_id Post ID.
 * @return array{id: int, title: string, url: string, excerpt: string, source: string}|null
 */
function nh_editorial_get_pillar( $post_id ) {
	$post_id = absint( $post_id );

	$own = nh_editorial_describe_pillar( get_post_meta( $post_id, 'nh_pillar_id', true ), $post_id );
	if ( $own ) {
		return array_merge( $own, array( 'source' => 'post' ) );
	}

	$cluster = nh_editorial_get_primary_cluster( $post_id );
	if ( $cluster ) {
		$found = nh_editorial_get_term_pillar( $cluster, $post_id );
		if ( $found ) {
			return array_merge( $found, array( 'source' => 'cluster' ) );
		}
	}

	$category = nh_editorial_primary_category( $post_id );
	if ( $category ) {
		$found = nh_editorial_get_term_pillar( $category, $post_id );
		if ( $found ) {
			return array_merge( $found, array( 'source' => 'category' ) );
		}
	}

	return null;
}

/**
 * Top-level ancestor of a category (the category itself when it has no parent).
 *
 * @param WP_Term $term Category.
 * @return WP_Term
 */
function nh_editorial_top_category( $term ) {
	$ancestors = get_ancestors( $term->term_id, 'category', 'taxonomy' );
	if ( empty( $ancestors ) ) {
		return $term;
	}
	$top = get_term( (int) end( $ancestors ), 'category' );
	return $top instanceof WP_Term ? $top : $term;
}

/**
 * The Hub of a post: the top-level category archive that lists its silo (always exists when the post
 * has a category).
 *
 * @param int $post_id Post ID.
 * @return array{name: string, url: string}|null
 */
function nh_editorial_get_hub( $post_id ) {
	$category = nh_editorial_primary_category( $post_id );
	if ( ! $category ) {
		return null;
	}
	$top  = nh_editorial_top_category( $category );
	$link = get_term_link( $top );
	if ( is_wp_error( $link ) ) {
		return null;
	}
	return array(
		'name' => $top->name,
		'url'  => $link,
	);
}

/**
 * Query fragment selecting the members of a silo.
 *
 * @param array $silo Silo from nh_editorial_get_silo().
 * @return array WP_Query arguments (tax_query or meta_query).
 */
function nh_editorial_silo_query_args( array $silo ) {
	if ( 'meta' === $silo['type'] ) {
		return array(
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Fallback for workflow aliases without the taxonomy; results are cached.
				array(
					'key'     => 'nh_topic_cluster',
					'value'   => $silo['alias'],
					'compare' => '=',
				),
			),
		);
	}

	return array(
		'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Indexed term relationship lookup; results are cached.
			array(
				'taxonomy'         => $silo['term']->taxonomy,
				'field'            => 'term_id',
				'terms'            => array( (int) $silo['term']->term_id ),
				'include_children' => false,
			),
		),
	);
}

/**
 * Neighbouring published articles inside the silo, by publish date with an ID tie-breaker.
 *
 * @param int $post_id Post ID.
 * @return array{prev: int, next: int, label: string}
 */
function nh_editorial_get_adjacent_ids( $post_id ) {
	$post_id = absint( $post_id );
	$empty   = array(
		'prev'  => 0,
		'next'  => 0,
		'label' => '',
	);

	$silo = nh_editorial_get_silo( $post_id );
	$post = get_post( $post_id );
	if ( ! $silo || ! $post instanceof WP_Post ) {
		return $empty;
	}

	$result = nh_editorial_cache_remember(
		'adjacent:' . $post_id . ':' . $silo['id'],
		static function () use ( $post, $silo ) {
			return array(
				'prev' => nh_editorial_query_adjacent( $post, $silo, true ),
				'next' => nh_editorial_query_adjacent( $post, $silo, false ),
			);
		}
	);

	return array(
		'prev'  => (int) $result['prev'],
		'next'  => (int) $result['next'],
		'label' => $silo['label'],
	);
}

/**
 * One-row lookup of the previous or next published post in a silo.
 *
 * WP_Query cannot express "same date, lower ID" together with a term or meta restriction, so this uses
 * a prepared statement on indexed columns (post type/status/date and term relationships).
 *
 * @param WP_Post $post     Current post.
 * @param array   $silo     Silo.
 * @param bool    $previous True for the older neighbour, false for the newer.
 * @return int Post ID or 0.
 */
function nh_editorial_query_adjacent( WP_Post $post, array $silo, $previous ) {
	global $wpdb;

	$operator = $previous ? '<' : '>';
	$order    = $previous ? 'DESC' : 'ASC';

	if ( 'meta' === $silo['type'] ) {
		$join  = "INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'nh_topic_cluster' AND pm.meta_value = %s";
		$args  = array( $silo['alias'] );
		$scope = '';
	} else {
		$join  = "INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id";
		$args  = array();
		$scope = $wpdb->prepare( 'AND tt.term_id = %d AND tt.taxonomy = %s', (int) $silo['term']->term_id, $silo['term']->taxonomy );
	}

	// $join and $scope are built from constants and prepared fragments above; $operator and $order are fixed literals.
	$sql = "SELECT p.ID FROM {$wpdb->posts} p {$join}
		WHERE p.post_type = 'post' AND p.post_status = 'publish' AND p.post_password = '' {$scope}
		AND ( p.post_date {$operator} %s OR ( p.post_date = %s AND p.ID {$operator} %d ) )
		ORDER BY p.post_date {$order}, p.ID {$order} LIMIT 1";

	$args[] = $post->post_date;
	$args[] = $post->post_date;
	$args[] = (int) $post->ID;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Cached by the caller; see the method description.
	return (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
}

/**
 * Exact number of published articles in a term, descendants included. One cheap indexed COUNT.
 *
 * Term counts stored by WordPress only cover posts assigned directly to the term, and WordPress'
 * own "padded" counts load every relationship row, which does not scale. Hubs call this only for
 * the few categories they display.
 *
 * @param WP_Term $term Category or cluster term.
 * @return int
 */
function nh_editorial_count_term_posts( WP_Term $term ) {
	$query = new WP_Query(
		array(
			'post_type'              => 'post',
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Indexed term lookup; cached by callers.
				array(
					'taxonomy'         => $term->taxonomy,
					'field'            => 'term_id',
					'terms'            => array( (int) $term->term_id ),
					'include_children' => true,
				),
			),
		)
	);
	return (int) $query->found_posts;
}

/**
 * Describe a category as a hub card.
 *
 * @param WP_Term $term     Category.
 * @param bool    $children Whether to include the strongest child categories.
 * @param array   $kids     Child terms keyed by parent ID (only used when $children is true).
 * @return array{id: int, name: string, slug: string, url: string, count: int, description: string, pillar: array|null, children: array}|null
 */
function nh_editorial_describe_hub( WP_Term $term, $children = false, array $kids = array() ) {
	$link = get_term_link( $term );
	if ( is_wp_error( $link ) ) {
		return null;
	}

	$child_cards = array();
	if ( $children && ! empty( $kids[ (int) $term->term_id ] ) ) {
		foreach ( array_slice( $kids[ (int) $term->term_id ], 0, 6 ) as $child ) {
			$child_link = get_term_link( $child );
			if ( ! is_wp_error( $child_link ) ) {
				$child_cards[] = array(
					'id'   => (int) $child->term_id,
					'name' => $child->name,
					'url'  => $child_link,
				);
			}
		}
	}

	return array(
		'id'          => (int) $term->term_id,
		'name'        => $term->name,
		'slug'        => $term->slug,
		'url'         => $link,
		'count'       => nh_editorial_count_term_posts( $term ),
		'description' => wp_trim_words( wp_strip_all_tags( $term->description ), 22, '…' ),
		'pillar'      => nh_editorial_get_term_pillar( $term ),
		'children'    => $child_cards,
	);
}

/**
 * Top-level categories for hubs and menus, largest first, with exact article counts.
 *
 * @param int $limit Maximum number of categories.
 * @return array<int, array{id: int, name: string, slug: string, url: string, count: int, description: string, pillar: array|null, children: array}>
 */
function nh_editorial_get_hubs( $limit = 12 ) {
	$limit = max( 1, absint( $limit ) );

	return nh_editorial_cache_remember(
		'hubs:' . $limit,
		static function () use ( $limit ) {
			$default = (int) get_option( 'default_category' );

			// hide_empty keeps parents whose posts live only in children (hierarchical defaults to true).
			$terms = get_terms(
				array(
					'taxonomy'   => 'category',
					'hide_empty' => true,
					'orderby'    => 'count',
					'order'      => 'DESC',
					'number'     => 500,
				)
			);
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				return array();
			}

			$top      = array();
			$kids     = array();
			$subtotal = array();
			$by_id    = array();
			foreach ( $terms as $term ) {
				$by_id[ (int) $term->term_id ] = $term;
			}
			foreach ( $terms as $term ) {
				$parent = (int) $term->parent;
				if ( 0 === $parent ) {
					$top[] = $term;
				} else {
					$kids[ $parent ][] = $term;
				}
				// Ranking weight: the direct count of a term is added to itself and to every ancestor, so
				// a hub whose articles all sit two or three levels down is ranked by its real size.
				$cursor = (int) $term->term_id;
				for ( $depth = 0; $cursor && isset( $by_id[ $cursor ] ) && $depth < 10; $depth++ ) {
					$subtotal[ $cursor ] = ( isset( $subtotal[ $cursor ] ) ? $subtotal[ $cursor ] : 0 ) + (int) $term->count;
					$cursor              = (int) $by_id[ $cursor ]->parent;
				}
			}

			// The default category is only a hub when it is the only one that exists.
			if ( count( $top ) > 1 ) {
				$top = array_values(
					array_filter(
						$top,
						static function ( $term ) use ( $default ) {
							return (int) $term->term_id !== $default;
						}
					)
				);
			}

			usort(
				$top,
				static function ( $a, $b ) use ( $subtotal ) {
					$weight = $subtotal[ (int) $b->term_id ] <=> $subtotal[ (int) $a->term_id ];
					return 0 !== $weight ? $weight : strcmp( $a->name, $b->name );
				}
			);

			$hubs = array();
			foreach ( array_slice( $top, 0, $limit ) as $term ) {
				$hub = nh_editorial_describe_hub( $term, true, $kids );
				if ( $hub ) {
					$hubs[] = $hub;
				}
			}
			return $hubs;
		}
	);
}

/**
 * Child categories of a category as hub cards.
 *
 * @param int $term_id Parent category ID.
 * @return array<int, array{id: int, name: string, slug: string, url: string, count: int, description: string, pillar: array|null, children: array}>
 */
function nh_editorial_get_child_hubs( $term_id ) {
	$term_id = absint( $term_id );

	return nh_editorial_cache_remember(
		'child-hubs:' . $term_id,
		static function () use ( $term_id ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'category',
					'parent'     => $term_id,
					'hide_empty' => true,
					'orderby'    => 'count',
					'order'      => 'DESC',
					'number'     => 24,
				)
			);
			if ( is_wp_error( $terms ) ) {
				return array();
			}

			$hubs = array();
			foreach ( $terms as $term ) {
				$hub = nh_editorial_describe_hub( $term );
				if ( $hub ) {
					$hubs[] = $hub;
				}
			}
			return $hubs;
		}
	);
}

/**
 * Terms listed by a Pillar/Hub page: "category:<id>" or "topic_cluster:<id>" in the nh_hub_term meta,
 * otherwise any term that maps this page as its pillar.
 *
 * @param int $page_id Page ID.
 * @return WP_Term|null
 */
function nh_editorial_get_hub_term( $page_id ) {
	$page_id = absint( $page_id );

	$raw = nh_editorial_meta_string( get_post_meta( $page_id, 'nh_hub_term', true ) );
	if ( preg_match( '/^(category|topic_cluster):(\d+)$/', $raw, $match ) && taxonomy_exists( $match[1] ) ) {
		$term = get_term( (int) $match[2], $match[1] );
		if ( $term instanceof WP_Term ) {
			return $term;
		}
	}

	foreach ( array( 'category', 'topic_cluster' ) as $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 1,
				'meta_key'   => 'nh_pillar_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Term meta table is small; one term.
				'meta_value' => $page_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			return $terms[0];
		}
	}
	return null;
}

/**
 * Published articles that support a Hub/Pillar page, newest first, with no draft or private posts.
 *
 * @param WP_Term $term  Category or cluster term (descendants included for categories).
 * @param int     $limit Maximum number of articles.
 * @param int     $skip  Post ID to leave out (the page itself when it is a post).
 * @return int[] Post IDs.
 */
function nh_editorial_get_supporting_ids( WP_Term $term, $limit = 12, $skip = 0 ) {
	$limit = max( 1, absint( $limit ) );
	$skip  = absint( $skip );

	return nh_editorial_cache_remember(
		'supporting:' . $term->taxonomy . ':' . $term->term_id . ':' . $limit . ':' . $skip,
		static function () use ( $term, $limit, $skip ) {
			return nh_editorial_query_public_ids(
				array(
					'post__not_in' => $skip ? array( $skip ) : array(),
					'tax_query'    => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Indexed term lookup; cached.
						array(
							'taxonomy'         => $term->taxonomy,
							'field'            => 'term_id',
							'terms'            => array( (int) $term->term_id ),
							'include_children' => true,
						),
					),
				),
				$limit
			);
		}
	);
}
