<?php
/**
 * Builds individual schema.org nodes from real, stored data only.
 *
 * Rules enforced here:
 *  - nothing is invented: no ratings, reviews, offers, prices (price range only when the owner typed one),
 *    no author credentials beyond what the author stored in their profile;
 *  - plain text properties never carry markup (tags stripped, entities decoded);
 *  - empty properties are removed, ids are stable and unique per page;
 *  - images are nested objects so a graph holds one node per @type.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Node builders.
 */
final class NH_SEO_Schema_Builder {

	/**
	 * Maximum characters of an Article headline (schema.org / Google guidance).
	 */
	const HEADLINE_LIMIT = 110;

	/**
	 * Plain text from any string: markup removed, entities decoded, whitespace collapsed.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function text( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = wp_strip_all_tags( (string) $value );
		$value = wp_specialchars_decode( $value, ENT_QUOTES );
		// Decoding can reveal markup that was stored entity-encoded.
		$value     = wp_strip_all_tags( $value );
		$collapsed = preg_replace( '/\s+/u', ' ', $value );
		return trim( null === $collapsed ? $value : $collapsed );
	}

	/**
	 * Whether an array is a list (keys 0..n-1). Stand-in for array_is_list(), which needs PHP 8.1.
	 *
	 * @param array $value Array.
	 * @return bool
	 */
	public static function is_list( array $value ) {
		if ( array() === $value ) {
			return true;
		}
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Remove empty properties recursively (null, empty strings, empty arrays) and nested objects that carry
	 * nothing but a @type. Zero and false are kept.
	 *
	 * @param mixed $value Value or node.
	 * @return mixed
	 */
	public static function clean( $value ) {
		if ( is_array( $value ) ) {
			$list = self::is_list( $value );
			$out  = array();
			foreach ( $value as $key => $item ) {
				$item = self::clean( $item );
				if ( null === $item || array() === $item ) {
					continue;
				}
				$out[ $key ] = $item;
			}
			if ( ! $list && array( '@type' ) === array_keys( $out ) ) {
				return null;
			}
			return $list ? array_values( $out ) : $out;
		}
		if ( is_string( $value ) ) {
			return '' === trim( $value ) ? null : $value;
		}
		return $value;
	}

	/**
	 * Stable node ids. Fragments are unique per node kind; page-level ids hang off the page URL.
	 *
	 * @param string $kind Node kind: organization | website | localbusiness | person | webpage | article | breadcrumb | faq.
	 * @param string $url  Page URL for page-level nodes.
	 * @param int    $user User ID for person nodes.
	 * @return string
	 */
	public static function id( $kind, $url = '', $user = 0 ) {
		$home = home_url( '/' );
		switch ( $kind ) {
			case 'organization':
			case 'website':
			case 'localbusiness':
				return $home . '#' . $kind;
			case 'person':
				// Hashed so that the id never exposes a numeric user ID.
				return $home . '#/schema/person/' . substr( wp_hash( 'nh_seo_person_' . (int) $user ), 0, 20 );
			default:
				return $url . '#' . $kind;
		}
	}

	/**
	 * Site name as plain text.
	 *
	 * @return string
	 */
	public static function site_name() {
		return self::text( get_bloginfo( 'name', 'raw' ) );
	}

	/**
	 * Business profile with every key present (a filter may return a partial array).
	 *
	 * @return array<string, mixed>
	 */
	public static function profile() {
		return array_merge( nh_seo_business_defaults(), (array) nh_seo_get_business_profile() );
	}

	/**
	 * ImageObject for an attachment, or an empty array.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, mixed>
	 */
	public static function image( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return array();
		}
		$src = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( ! is_array( $src ) || empty( $src[0] ) ) {
			return array();
		}
		return array(
			'@type'      => 'ImageObject',
			'url'        => esc_url_raw( $src[0] ),
			'contentUrl' => esc_url_raw( $src[0] ),
			'width'      => isset( $src[1] ) ? (int) $src[1] : 0,
			'height'     => isset( $src[2] ) ? (int) $src[2] : 0,
		);
	}

	/**
	 * Organization: data from the verified profile only; the name falls back to the site name.
	 *
	 * @param array<string, mixed> $profile             Business profile.
	 * @param bool                 $links_local_business Whether a LocalBusiness node is printed on the same page.
	 * @return array<string, mixed>
	 */
	public static function organization( array $profile, $links_local_business ) {
		$contact = array();
		if ( '' !== $profile['phone'] ) {
			$contact = array(
				'@type'     => 'ContactPoint',
				'telephone' => $profile['phone'],
				'email'     => $profile['email'],
			);
		}

		$node = array(
			'@type'        => 'Organization',
			'@id'          => self::id( 'organization' ),
			'name'         => '' !== $profile['name'] ? $profile['name'] : self::site_name(),
			'legalName'    => $profile['legal_name'],
			'url'          => '' !== $profile['url'] ? $profile['url'] : home_url( '/' ),
			'logo'         => self::image( $profile['logo_id'] ),
			'sameAs'       => $profile['social'],
			'contactPoint' => $contact,
		);

		if ( $links_local_business ) {
			// The owner declared this entity as a physical place on this very page.
			$node['location'] = array( '@id' => self::id( 'localbusiness' ) );
		}
		return $node;
	}

	/**
	 * WebSite with the publisher link and the site's own search.
	 *
	 * @return array<string, mixed>
	 */
	public static function website() {
		return array(
			'@type'           => 'WebSite',
			'@id'             => self::id( 'website' ),
			'url'             => home_url( '/' ),
			'name'            => self::site_name(),
			'description'     => self::text( get_bloginfo( 'description', 'raw' ) ),
			'publisher'       => array( '@id' => self::id( 'organization' ) ),
			'inLanguage'      => get_bloginfo( 'language' ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/' ) . '?s={search_term_string}',
				),
				'query-input' => 'required name=search_term_string',
			),
		);
	}

	/**
	 * Page type for a request kind.
	 *
	 * @param array<string, mixed> $context Request context.
	 * @return string
	 */
	public static function page_type( array $context ) {
		switch ( $context['kind'] ) {
			case 'search':
				return 'SearchResultsPage';
			case 'author':
				return 'ProfilePage';
			case 'archive':
				return 'CollectionPage';
			case 'home':
				return $context['front'] ? 'WebPage' : 'CollectionPage';
			default:
				return 'WebPage';
		}
	}

	/**
	 * The WebPage-like node of the request.
	 *
	 * @param array<string, mixed> $context        Request context.
	 * @param array<string, mixed> $extras         Post extras (singular only).
	 * @param array<int, array>    $trail          Breadcrumb trail.
	 * @param int                  $author_user_id Person node the page is about (author archives).
	 * @return array<string, mixed>
	 */
	public static function webpage( array $context, array $extras, array $trail, $author_user_id ) {
		$url  = $context['url'];
		$post = $context['post'];

		if ( $post instanceof WP_Post ) {
			$name = nh_seo_plain_title( $post );
		} elseif ( 'home' === $context['kind'] ) {
			$posts_page = (int) get_option( 'page_for_posts' );
			$name       = ( ! $context['front'] && $posts_page ) ? nh_seo_plain_title( $posts_page ) : self::site_name();
		} elseif ( empty( $trail ) ) {
			$name = self::site_name();
		} else {
			$last = end( $trail );
			$name = self::text( isset( $last['name'] ) ? $last['name'] : '' );
		}

		$node = array(
			'@type'      => self::page_type( $context ),
			'@id'        => self::id( 'webpage', $url ),
			'url'        => $url,
			'name'       => $name,
			'isPartOf'   => array( '@id' => self::id( 'website' ) ),
			'inLanguage' => get_bloginfo( 'language' ),
		);

		if ( count( $trail ) > 1 ) {
			$node['breadcrumb'] = array( '@id' => self::id( 'breadcrumb', $url ) );
		}

		if ( $post instanceof WP_Post ) {
			$node['description'] = self::description( $post, $extras );
			$node               += self::dates( $post, isset( $extras['updated'] ) ? (string) $extras['updated'] : '' );
		}

		if ( $author_user_id ) {
			$node['mainEntity'] = array( '@id' => self::id( 'person', '', $author_user_id ) );
		}
		return $node;
	}

	/**
	 * BreadcrumbList from the canonical trail. At least two real items are needed.
	 *
	 * @param array<int, array> $trail Trail of name and url items.
	 * @param string            $url   Page URL.
	 * @return array<string, mixed>
	 */
	public static function breadcrumb( array $trail, $url ) {
		$items = array();
		foreach ( $trail as $crumb ) {
			$name = self::text( isset( $crumb['name'] ) ? $crumb['name'] : '' );
			$link = isset( $crumb['url'] ) ? esc_url_raw( (string) $crumb['url'] ) : '';
			if ( '' === $name || '' === $link ) {
				continue;
			}
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => count( $items ) + 1,
				'name'     => $name,
				'item'     => $link,
			);
		}
		if ( count( $items ) < 2 ) {
			return array();
		}
		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => self::id( 'breadcrumb', $url ),
			'itemListElement' => $items,
		);
	}

	/**
	 * Description: the answer-first summary, else a manually written excerpt. Never generated from the content.
	 *
	 * @param WP_Post              $post   Post.
	 * @param array<string, mixed> $extras Post extras.
	 * @return string
	 */
	public static function description( WP_Post $post, array $extras ) {
		if ( ! empty( $extras['summary'] ) ) {
			return self::text( $extras['summary'] );
		}
		return has_excerpt( $post ) ? self::text( $post->post_excerpt ) : '';
	}

	/**
	 * Publication and modification dates.
	 *
	 * The modified date is the owner's real content-update date when one is stored and valid, otherwise the
	 * modified time of the post; it is never earlier than the publication date.
	 *
	 * @param WP_Post $post    Post.
	 * @param string  $updated Real content-update date (Y-m-d) or ''.
	 * @return array<string, string>
	 */
	public static function dates( WP_Post $post, $updated ) {
		$published = get_post_time( 'c', false, $post, false );
		if ( ! $published ) {
			return array();
		}
		$modified = get_post_modified_time( 'c', false, $post, false );
		if ( ! $modified || strtotime( $modified ) < strtotime( $published ) ) {
			$modified = $published;
		}
		if ( '' !== $updated && strcmp( $updated, substr( $published, 0, 10 ) ) >= 0 ) {
			$modified = $updated;
		}
		return array(
			'datePublished' => $published,
			'dateModified'  => $modified,
		);
	}

	/**
	 * Number of words in the content (markup and shortcodes removed).
	 *
	 * @param WP_Post $post Post.
	 * @return int
	 */
	public static function word_count( WP_Post $post ) {
		$text  = self::text( strip_shortcodes( $post->post_content ) );
		$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $words ) ? count( $words ) : 0;
	}

	/**
	 * Names of the assigned terms of a taxonomy as plain text.
	 *
	 * @param WP_Post $post     Post.
	 * @param string  $taxonomy Taxonomy.
	 * @return string[]
	 */
	public static function term_names( WP_Post $post, $taxonomy ) {
		$terms = get_the_terms( $post, $taxonomy );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}
		$names = array();
		foreach ( $terms as $term ) {
			$name = trim( str_replace( ',', ' ', self::text( $term->name ) ) );
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}
		return array_values( array_unique( $names ) );
	}

	/**
	 * Article node for a post.
	 *
	 * @param WP_Post              $post   Post.
	 * @param array<string, mixed> $extras Post extras.
	 * @param string               $url    Permalink.
	 * @return array<string, mixed>
	 */
	public static function article( WP_Post $post, array $extras, $url ) {
		$primary  = nh_seo_get_primary_category( $post );
		$keywords = self::term_names( $post, 'post_tag' );

		$node = array(
			'@type'            => 'Article',
			'@id'              => self::id( 'article', $url ),
			'isPartOf'         => array( '@id' => self::id( 'webpage', $url ) ),
			'mainEntityOfPage' => array( '@id' => self::id( 'webpage', $url ) ),
			'headline'         => nh_seo_truncate( nh_seo_plain_title( $post ), self::HEADLINE_LIMIT ),
			'description'      => self::description( $post, $extras ),
			'image'            => self::image( get_post_thumbnail_id( $post ) ),
		);

		$node += self::dates( $post, isset( $extras['updated'] ) ? (string) $extras['updated'] : '' );

		if ( get_userdata( (int) $post->post_author ) ) {
			$node['author'] = array( '@id' => self::id( 'person', '', (int) $post->post_author ) );
		}

		$node['publisher']      = array( '@id' => self::id( 'organization' ) );
		$node['articleSection'] = $primary instanceof WP_Term ? self::text( $primary->name ) : '';
		$node['keywords']       = implode( ', ', $keywords );
		$node['wordCount']      = self::word_count( $post );
		$node['inLanguage']     = get_bloginfo( 'language' );
		return $node;
	}

	/**
	 * Person node from a user: only the name, the author archive and what the author stored themselves.
	 *
	 * @param WP_User $user User.
	 * @return array<string, mixed>
	 */
	public static function person( WP_User $user ) {
		$job_title = nh_seo_sanitize_line( get_user_meta( $user->ID, NH_SEO_Keys::USER_JOB_TITLE, true ), 120 );
		$same_as   = nh_seo_sanitize_url_list( get_user_meta( $user->ID, NH_SEO_Keys::USER_SAMEAS, true ) );

		return array(
			'@type'    => 'Person',
			'@id'      => self::id( 'person', '', $user->ID ),
			'name'     => self::text( $user->display_name ),
			'url'      => get_author_posts_url( $user->ID ),
			'jobTitle' => $job_title,
			'sameAs'   => $same_as,
		);
	}

	/**
	 * FAQPage node. Question text is plain; answers keep the sanitized subset of HTML that is visible on the page.
	 *
	 * @param array<int, array{question: string, answer: string}> $faq FAQ items.
	 * @param string                                              $url Page URL.
	 * @return array<string, mixed>
	 */
	public static function faq( array $faq, $url ) {
		$questions = array();
		foreach ( $faq as $item ) {
			$name = self::text( $item['question'] );
			if ( '' === $name || '' === trim( (string) $item['answer'] ) ) {
				continue;
			}
			$questions[] = array(
				'@type'          => 'Question',
				'name'           => $name,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => (string) $item['answer'],
				),
			);
		}
		return array(
			'@type'      => 'FAQPage',
			'@id'        => self::id( 'faq', $url ),
			'url'        => $url,
			'mainEntity' => $questions,
		);
	}

	/**
	 * LocalBusiness node from the verified profile. priceRange appears only when the owner typed one.
	 *
	 * @param array<string, mixed> $profile Business profile.
	 * @return array<string, mixed>
	 */
	public static function local_business( array $profile ) {
		$type = in_array( $profile['type'], nh_seo_business_types(), true ) ? $profile['type'] : 'LocalBusiness';

		$address = array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $profile['street'],
			'addressLocality' => $profile['locality'],
			'addressRegion'   => $profile['region'],
			'postalCode'      => $profile['postal'],
			'addressCountry'  => $profile['country'],
		);

		$geo = array();
		if ( '' !== $profile['lat'] && '' !== $profile['lng'] ) {
			$geo = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $profile['lat'],
				'longitude' => (float) $profile['lng'],
			);
		}

		return array(
			'@type'        => $type,
			'@id'          => self::id( 'localbusiness' ),
			'name'         => $profile['name'],
			'url'          => '' !== $profile['url'] ? $profile['url'] : home_url( '/' ),
			'telephone'    => $profile['phone'],
			'email'        => $profile['email'],
			'image'        => self::image( $profile['logo_id'] ),
			'address'      => $address,
			'geo'          => $geo,
			'openingHours' => array_values( (array) $profile['hours'] ),
			'hasMap'       => $profile['has_map'],
			'priceRange'   => $profile['price_range'],
			'sameAs'       => $profile['social'],
		);
	}
}
