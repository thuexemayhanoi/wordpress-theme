<?php
/**
 * The "Answer, FAQ and sources" meta box on posts and pages.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Edit screen UI for the editorial extras of docs/DATA-CONTRACT.md and the handler that saves them.
 *
 * Saving: nonce bound to the post, capability `edit_post`, never on autosaves or revisions, everything
 * unslashed once and passed through the shared sanitizers, empty values delete the meta, and a field that
 * is not part of the submitted form is left untouched.
 */
final class NH_SEO_Meta_Box {

	/**
	 * Name of the hidden nonce field.
	 *
	 * @var string
	 */
	const NONCE_FIELD = 'nh_seo_box_nonce';

	/**
	 * Name of the array holding every field of the box.
	 *
	 * @var string
	 */
	const FIELD_GROUP = 'nh_seo';

	/**
	 * Maximum number of terms offered in select lists.
	 *
	 * @var int
	 */
	const MAX_TERMS = 200;

	/**
	 * Nonce action for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function nonce_action( $post_id ) {
		return 'nh_seo_save_box_' . absint( $post_id );
	}

	/**
	 * Add the meta box to posts and pages.
	 *
	 * @param string $post_type Post type being edited.
	 * @return void
	 */
	public static function register( $post_type ) {
		if ( ! in_array( $post_type, NH_SEO_Data_Model::POST_TYPES, true ) ) {
			return;
		}
		add_meta_box(
			'nh-seo-box',
			__( 'Answer, FAQ and sources', 'nguyen-ha-seo-companion' ),
			array( __CLASS__, 'render' ),
			$post_type,
			'normal',
			'default'
		);
	}

	/**
	 * Print the meta box.
	 *
	 * @param WP_Post $post Post being edited.
	 * @return void
	 */
	public static function render( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$extras  = nh_seo_get_post_extras( $post->ID );
		$is_page = 'page' === $post->post_type;

		wp_nonce_field( self::nonce_action( $post->ID ), self::NONCE_FIELD, false );
		?>
		<div class="nh-seo-box">
			<p class="nh-seo-box__intro">
				<?php esc_html_e( 'Everything here is optional. Empty fields are removed. The theme, or the plugin fallback, shows the answer, takeaways, FAQ and sources on the page.', 'nguyen-ha-seo-companion' ); ?>
			</p>

			<fieldset class="nh-seo-section">
				<legend class="nh-seo-section__title"><?php esc_html_e( 'Answer first', 'nguyen-ha-seo-companion' ); ?></legend>

				<div class="nh-seo-field">
					<label for="nh-seo-summary"><?php esc_html_e( 'Quick answer', 'nguyen-ha-seo-companion' ); ?></label>
					<textarea id="nh-seo-summary" class="widefat" rows="3" name="<?php echo esc_attr( self::FIELD_GROUP ); ?>[summary]" maxlength="<?php echo esc_attr( (string) NH_SEO_Keys::LIMIT_SUMMARY ); ?>" data-nh-counter="chars" data-nh-max="<?php echo esc_attr( (string) NH_SEO_Keys::LIMIT_SUMMARY ); ?>" aria-describedby="nh-seo-summary-help nh-seo-summary-count"><?php echo esc_textarea( $extras['summary'] ); ?></textarea>
					<p class="nh-seo-counter" id="nh-seo-summary-count" data-nh-counter-for="nh-seo-summary" data-nh-counter-text="<?php /* translators: 1: characters used, 2: characters allowed. */ echo esc_attr__( '%1$s of %2$s characters', 'nguyen-ha-seo-companion' ); ?>">
						<?php
						/* translators: %s: maximum number of characters. */
						echo esc_html( sprintf( __( 'Up to %s characters.', 'nguyen-ha-seo-companion' ), number_format_i18n( NH_SEO_Keys::LIMIT_SUMMARY ) ) );
						?>
					</p>
					<p class="description" id="nh-seo-summary-help"><?php esc_html_e( 'One or two sentences that answer the question of the article right away. Plain text.', 'nguyen-ha-seo-companion' ); ?></p>
				</div>

				<div class="nh-seo-field">
					<label for="nh-seo-takeaways"><?php esc_html_e( 'Key takeaways', 'nguyen-ha-seo-companion' ); ?></label>
					<textarea id="nh-seo-takeaways" class="widefat" rows="5" name="<?php echo esc_attr( self::FIELD_GROUP ); ?>[takeaways]" data-nh-counter="lines" data-nh-max="<?php echo esc_attr( (string) NH_SEO_Keys::LIMIT_TAKEAWAYS ); ?>" aria-describedby="nh-seo-takeaways-help nh-seo-takeaways-count"><?php echo esc_textarea( implode( "\n", $extras['takeaways'] ) ); ?></textarea>
					<p class="nh-seo-counter" id="nh-seo-takeaways-count" data-nh-counter-for="nh-seo-takeaways" data-nh-counter-text="<?php /* translators: 1: lines used, 2: lines allowed. */ echo esc_attr__( '%1$s of %2$s takeaways', 'nguyen-ha-seo-companion' ); ?>">
						<?php
						/* translators: %s: maximum number of takeaways. */
						echo esc_html( sprintf( __( 'Up to %s takeaways.', 'nguyen-ha-seo-companion' ), number_format_i18n( NH_SEO_Keys::LIMIT_TAKEAWAYS ) ) );
						?>
					</p>
					<p class="description" id="nh-seo-takeaways-help">
						<?php
						/* translators: %s: maximum number of characters per takeaway. */
						echo esc_html( sprintf( __( 'One takeaway per line, up to %s characters each. Only add them when the article really has key points.', 'nguyen-ha-seo-companion' ), number_format_i18n( NH_SEO_Keys::LIMIT_TAKEAWAY_CHARS ) ) );
						?>
					</p>
				</div>

				<div class="nh-seo-field">
					<label for="nh-seo-updated"><?php esc_html_e( 'Content updated', 'nguyen-ha-seo-companion' ); ?></label>
					<input type="date" id="nh-seo-updated" name="<?php echo esc_attr( self::FIELD_GROUP ); ?>[updated]" value="<?php echo esc_attr( $extras['updated'] ); ?>" max="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" aria-describedby="nh-seo-updated-help">
					<p class="description" id="nh-seo-updated-help"><?php esc_html_e( 'The date the content was really revised, not the date of a small edit. Leave it empty when unsure. Future dates are ignored.', 'nguyen-ha-seo-companion' ); ?></p>
				</div>
			</fieldset>

			<?php
			self::render_repeater(
				array(
					'name'         => 'faq',
					'legend'       => __( 'Frequently asked questions', 'nguyen-ha-seo-companion' ),
					'help'         => __( 'Questions people really ask about this topic. Answers may use basic HTML: links, paragraphs, bold, italic, lists and code.', 'nguyen-ha-seo-companion' ),
					'add_label'    => __( 'Add question', 'nguyen-ha-seo-companion' ),
					/* translators: %d: position of the question in the list. */
					'label_up'     => __( 'Move question %d up', 'nguyen-ha-seo-companion' ),
					/* translators: %d: position of the question in the list. */
					'label_down'   => __( 'Move question %d down', 'nguyen-ha-seo-companion' ),
					/* translators: %d: position of the question in the list. */
					'label_remove' => __( 'Remove question %d', 'nguyen-ha-seo-companion' ),
					'max'          => NH_SEO_Keys::LIMIT_FAQ_ITEMS,
					'items'        => $extras['faq'],
					'fields'       => array(
						'question' => array(
							'label'     => __( 'Question', 'nguyen-ha-seo-companion' ),
							'type'      => 'text',
							'maxlength' => NH_SEO_Keys::LIMIT_FAQ_QUESTION,
						),
						'answer'   => array(
							'label'     => __( 'Answer', 'nguyen-ha-seo-companion' ),
							'type'      => 'textarea',
							'maxlength' => NH_SEO_Keys::LIMIT_FAQ_ANSWER,
						),
					),
				)
			);

			self::render_repeater(
				array(
					'name'         => 'sources',
					'legend'       => __( 'Sources', 'nguyen-ha-seo-companion' ),
					'help'         => __( 'References that support the article. Only http and https links are kept.', 'nguyen-ha-seo-companion' ),
					'add_label'    => __( 'Add source', 'nguyen-ha-seo-companion' ),
					/* translators: %d: position of the source in the list. */
					'label_up'     => __( 'Move source %d up', 'nguyen-ha-seo-companion' ),
					/* translators: %d: position of the source in the list. */
					'label_down'   => __( 'Move source %d down', 'nguyen-ha-seo-companion' ),
					/* translators: %d: position of the source in the list. */
					'label_remove' => __( 'Remove source %d', 'nguyen-ha-seo-companion' ),
					'max'          => NH_SEO_Keys::LIMIT_SOURCES,
					'items'        => $extras['sources'],
					'fields'       => array(
						'title'     => array(
							'label'     => __( 'Title', 'nguyen-ha-seo-companion' ),
							'type'      => 'text',
							'maxlength' => NH_SEO_Keys::LIMIT_SOURCE_TITLE,
						),
						'url'       => array(
							'label'     => __( 'URL', 'nguyen-ha-seo-companion' ),
							'type'      => 'url',
							'maxlength' => 2000,
						),
						'publisher' => array(
							'label'     => __( 'Publisher', 'nguyen-ha-seo-companion' ),
							'type'      => 'text',
							'maxlength' => NH_SEO_Keys::LIMIT_SOURCE_PUBLISHER,
						),
					),
				)
			);

			self::render_linking_section( $post, $is_page );
			self::render_search_section( $post );
			?>
		</div>
		<?php
	}

	/**
	 * Print a repeater (FAQ or sources): existing items plus one empty row, and a template for new rows.
	 *
	 * @param array $args Repeater definition.
	 * @return void
	 */
	private static function render_repeater( array $args ) {
		$name  = $args['name'];
		$items = array_values( (array) $args['items'] );
		$max   = (int) $args['max'];
		$rows  = $items;
		if ( count( $rows ) < $max ) {
			$rows[] = array(); // An empty row so that adding works without JavaScript too.
		}
		$base = self::FIELD_GROUP . '[' . $name . ']';
		?>
		<fieldset class="nh-seo-section nh-seo-repeater" data-nh-repeater data-nh-max="<?php echo esc_attr( (string) $max ); ?>" data-nh-label-up="<?php echo esc_attr( $args['label_up'] ); ?>" data-nh-label-down="<?php echo esc_attr( $args['label_down'] ); ?>" data-nh-label-remove="<?php echo esc_attr( $args['label_remove'] ); ?>">
			<legend class="nh-seo-section__title"><?php echo esc_html( $args['legend'] ); ?></legend>
			<p class="description">
				<?php
				echo esc_html( $args['help'] ) . ' ';
				/* translators: %s: maximum number of items. */
				echo esc_html( sprintf( __( 'Up to %s items.', 'nguyen-ha-seo-companion' ), number_format_i18n( $max ) ) );
				?>
			</p>
			<ol class="nh-seo-repeater__list" data-nh-list>
				<?php
				foreach ( $rows as $index => $row ) {
					self::render_repeater_item( $base, (string) $index, is_array( $row ) ? $row : array(), $args['fields'] );
				}
				?>
			</ol>
			<template data-nh-template>
				<?php self::render_repeater_item( $base, '__INDEX__', array(), $args['fields'] ); ?>
			</template>
			<p class="nh-seo-repeater__add" data-nh-add-wrap hidden>
				<button type="button" class="button" data-nh-add><?php echo esc_html( $args['add_label'] ); ?></button>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Print one repeater row.
	 *
	 * @param string $base   Input name prefix, such as nh_seo[faq].
	 * @param string $index  Row index (or the __INDEX__ placeholder in the template).
	 * @param array  $values Stored values of the row.
	 * @param array  $fields Field definitions.
	 * @return void
	 */
	private static function render_repeater_item( $base, $index, array $values, array $fields ) {
		?>
		<li class="nh-seo-repeater__item" data-nh-item>
			<div class="nh-seo-repeater__fields">
				<?php
				foreach ( $fields as $key => $field ) {
					$value = isset( $values[ $key ] ) && is_scalar( $values[ $key ] ) ? (string) $values[ $key ] : '';
					$input = $base . '[' . $index . '][' . $key . ']';
					?>
					<label class="nh-seo-field">
						<span class="nh-seo-field__label"><?php echo esc_html( $field['label'] ); ?></span>
						<?php if ( 'textarea' === $field['type'] ) { ?>
							<textarea class="widefat" rows="3" name="<?php echo esc_attr( $input ); ?>" maxlength="<?php echo esc_attr( (string) $field['maxlength'] ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
						<?php } else { ?>
							<input type="<?php echo esc_attr( $field['type'] ); ?>" class="widefat" name="<?php echo esc_attr( $input ); ?>" value="<?php echo esc_attr( $value ); ?>" maxlength="<?php echo esc_attr( (string) $field['maxlength'] ); ?>">
						<?php } ?>
					</label>
				<?php } ?>
			</div>
			<div class="nh-seo-repeater__actions" data-nh-actions hidden>
				<button type="button" class="button-link" data-nh-move="up"><?php esc_html_e( 'Move up', 'nguyen-ha-seo-companion' ); ?></button>
				<button type="button" class="button-link" data-nh-move="down"><?php esc_html_e( 'Move down', 'nguyen-ha-seo-companion' ); ?></button>
				<button type="button" class="button-link button-link-delete" data-nh-remove><?php esc_html_e( 'Remove', 'nguyen-ha-seo-companion' ); ?></button>
			</div>
		</li>
		<?php
	}

	/**
	 * Print the internal-linking fields: primary cluster, Pillar and hub topic.
	 *
	 * @param WP_Post $post    Post being edited.
	 * @param bool    $is_page Whether the post is a page.
	 * @return void
	 */
	private static function render_linking_section( WP_Post $post, $is_page ) {
		$group  = self::FIELD_GROUP;
		$pillar = (int) get_post_meta( $post->ID, NH_SEO_Keys::META_PILLAR, true );
		?>
		<fieldset class="nh-seo-section">
			<legend class="nh-seo-section__title"><?php esc_html_e( 'Internal linking', 'nguyen-ha-seo-companion' ); ?></legend>

			<?php if ( is_object_in_taxonomy( $post->post_type, NH_SEO_Keys::TAXONOMY_CLUSTER ) ) { ?>
				<div class="nh-seo-field">
					<?php
					$clusters = get_the_terms( $post, NH_SEO_Keys::TAXONOMY_CLUSTER );
					$clusters = is_array( $clusters ) ? $clusters : array();
					if ( $clusters ) {
						usort(
							$clusters,
							static function ( $a, $b ) {
								return strcasecmp( $a->name, $b->name );
							}
						);
						$primary = nh_seo_get_primary_cluster( $post );
						$chosen  = (int) get_post_meta( $post->ID, NH_SEO_Keys::META_PRIMARY_CLUSTER, true );
						$chosen  = $primary && $chosen === (int) $primary->term_id ? $chosen : 0;
						?>
						<label for="nh-seo-primary-cluster"><?php esc_html_e( 'Primary Topic Cluster', 'nguyen-ha-seo-companion' ); ?></label>
						<select id="nh-seo-primary-cluster" name="<?php echo esc_attr( $group ); ?>[primary_cluster]" aria-describedby="nh-seo-primary-cluster-help">
							<option value="0"><?php esc_html_e( 'Automatic (the cluster with the lowest ID)', 'nguyen-ha-seo-companion' ); ?></option>
							<?php foreach ( $clusters as $cluster ) { ?>
								<option value="<?php echo esc_attr( (string) $cluster->term_id ); ?>"<?php selected( $chosen, (int) $cluster->term_id ); ?>><?php echo esc_html( $cluster->name ); ?></option>
							<?php } ?>
						</select>
						<p class="description" id="nh-seo-primary-cluster-help"><?php esc_html_e( 'Decides the silo used for related and adjacent articles. Only clusters assigned to this post are listed.', 'nguyen-ha-seo-companion' ); ?></p>
					<?php } else { ?>
						<p class="nh-seo-field__label"><?php esc_html_e( 'Primary Topic Cluster', 'nguyen-ha-seo-companion' ); ?></p>
						<p class="description"><?php esc_html_e( 'Assign Topic Clusters to this post and save it, then come back to choose the primary one.', 'nguyen-ha-seo-companion' ); ?></p>
					<?php } ?>
					<?php
					$alias = NH_SEO_Data_Model::sanitize_cluster_alias( get_post_meta( $post->ID, NH_SEO_Keys::META_CLUSTER_ALIAS, true ) );
					if ( '' !== $alias && ! NH_SEO_Cluster_Sync::find_term( $alias ) ) {
						?>
						<p class="description nh-seo-note">
							<?php
							/* translators: %s: cluster alias written by an automated workflow. */
							echo esc_html( sprintf( __( 'The cluster alias "%s" sent by a workflow does not match any Topic Cluster yet. Create the cluster or enable automatic creation in the plugin settings.', 'nguyen-ha-seo-companion' ), $alias ) );
							?>
						</p>
					<?php } ?>
				</div>
			<?php } ?>

			<div class="nh-seo-field">
				<label for="nh-seo-pillar"><?php esc_html_e( 'Pillar page ID', 'nguyen-ha-seo-companion' ); ?></label>
				<input type="text" id="nh-seo-pillar" class="small-text" inputmode="numeric" pattern="[0-9]*" name="<?php echo esc_attr( $group ); ?>[pillar]" value="<?php echo esc_attr( $pillar ? (string) $pillar : '' ); ?>" aria-describedby="nh-seo-pillar-help">
				<?php
				$pillar_post = $pillar ? get_post( $pillar ) : null;
				if ( $pillar_post instanceof WP_Post ) {
					$edit_link = get_edit_post_link( $pillar_post, 'raw' );
					?>
					<span class="nh-seo-inline-note">
						<?php
						if ( $edit_link ) {
							echo '<a href="' . esc_url( $edit_link ) . '">' . esc_html( nh_seo_plain_title( $pillar_post ) ) . '</a>';
						} else {
							echo esc_html( nh_seo_plain_title( $pillar_post ) );
						}
						?>
					</span>
				<?php } ?>
				<p class="description" id="nh-seo-pillar-help"><?php esc_html_e( 'The published post or page that is the Pillar of this content. It overrides the Pillar of the topic. Find the ID in the address bar when you edit that page (post=123). Leave empty to use the Pillar of the topic.', 'nguyen-ha-seo-companion' ); ?></p>
			</div>

			<?php if ( $is_page ) { ?>
				<div class="nh-seo-field">
					<label for="nh-seo-hub-term"><?php esc_html_e( 'Topic listed by this Pillar or Hub page', 'nguyen-ha-seo-companion' ); ?></label>
					<?php self::render_hub_select( $post ); ?>
					<p class="description" id="nh-seo-hub-term-help"><?php esc_html_e( 'Choose the category or Topic Cluster whose articles this page lists. Leave it empty for ordinary pages.', 'nguyen-ha-seo-companion' ); ?></p>
				</div>
			<?php } ?>
		</fieldset>
		<?php
	}

	/**
	 * Print the select of categories and clusters a Hub page can list (at most MAX_TERMS of each).
	 *
	 * @param WP_Post $post Page being edited.
	 * @return void
	 */
	private static function render_hub_select( WP_Post $post ) {
		$stored = NH_SEO_Data_Model::sanitize_hub_term( get_post_meta( $post->ID, NH_SEO_Keys::META_HUB_TERM, true ) );
		$groups = array(
			'category'                    => __( 'Categories', 'nguyen-ha-seo-companion' ),
			NH_SEO_Keys::TAXONOMY_CLUSTER => __( 'Topic Clusters', 'nguyen-ha-seo-companion' ),
		);
		$seen   = false;
		?>
		<select id="nh-seo-hub-term" name="<?php echo esc_attr( self::FIELD_GROUP ); ?>[hub_term]" aria-describedby="nh-seo-hub-term-help">
			<option value=""><?php esc_html_e( 'None', 'nguyen-ha-seo-companion' ); ?></option>
			<?php
			foreach ( $groups as $taxonomy => $label ) {
				$terms = get_terms(
					array(
						'taxonomy'               => $taxonomy,
						'hide_empty'             => false,
						'orderby'                => 'name',
						'number'                 => self::MAX_TERMS,
						'update_term_meta_cache' => false,
					)
				);
				if ( empty( $terms ) || is_wp_error( $terms ) ) {
					continue;
				}
				echo '<optgroup label="' . esc_attr( $label ) . '">';
				foreach ( $terms as $term ) {
					$value = $taxonomy . ':' . $term->term_id;
					$seen  = $seen || $value === $stored;
					echo '<option value="' . esc_attr( $value ) . '"' . selected( $stored, $value, false ) . '>' . esc_html( $term->name ) . '</option>';
				}
				echo '</optgroup>';
			}

			// A stored topic beyond the first MAX_TERMS must not be lost when the page is saved.
			if ( '' !== $stored && ! $seen ) {
				$parsed = NH_SEO_Data_Model::parse_hub_term( $stored );
				$term   = $parsed ? get_term( $parsed['term_id'], $parsed['taxonomy'] ) : null;
				if ( $term instanceof WP_Term ) {
					echo '<option value="' . esc_attr( $stored ) . '" selected="selected">' . esc_html( $term->name ) . '</option>';
				}
			}
			?>
		</select>
		<?php
	}

	/**
	 * Print the search keyword and local-business fields.
	 *
	 * @param WP_Post $post Post being edited.
	 * @return void
	 */
	private static function render_search_section( WP_Post $post ) {
		$group = self::FIELD_GROUP;
		?>
		<fieldset class="nh-seo-section">
			<legend class="nh-seo-section__title"><?php esc_html_e( 'Search and local', 'nguyen-ha-seo-companion' ); ?></legend>

			<div class="nh-seo-field">
				<label for="nh-seo-keywords"><?php esc_html_e( 'Search keywords', 'nguyen-ha-seo-companion' ); ?></label>
				<input type="text" id="nh-seo-keywords" class="widefat" name="<?php echo esc_attr( $group ); ?>[keywords]" value="<?php echo esc_attr( nh_seo_sanitize_keywords( get_post_meta( $post->ID, NH_SEO_Keys::META_KEYWORDS, true ) ) ); ?>" maxlength="<?php echo esc_attr( (string) NH_SEO_Keys::LIMIT_KEYWORDS ); ?>" aria-describedby="nh-seo-keywords-help">
				<p class="description" id="nh-seo-keywords-help"><?php esc_html_e( 'Extra words, separated by commas, that should find this content in the site search. Visitors never see them.', 'nguyen-ha-seo-companion' ); ?></p>
			</div>

			<div class="nh-seo-field">
				<input type="hidden" name="<?php echo esc_attr( $group ); ?>[local_business]" value="0">
				<label for="nh-seo-local-business">
					<input type="checkbox" id="nh-seo-local-business" name="<?php echo esc_attr( $group ); ?>[local_business]" value="1" <?php checked( 1, nh_seo_sanitize_flag( get_post_meta( $post->ID, NH_SEO_Keys::META_LOCAL_BUSINESS, true ) ) ); ?> aria-describedby="nh-seo-local-business-help">
					<?php esc_html_e( 'This page has verified local-business context', 'nguyen-ha-seo-companion' ); ?>
				</label>
				<p class="description" id="nh-seo-local-business-help">
					<?php esc_html_e( 'Tick it only for pages about the real business, such as the contact or location page. It enables LocalBusiness structured data when the business profile is complete.', 'nguyen-ha-seo-companion' ); ?>
					<?php
					if ( ! nh_seo_business_is_complete() ) {
						echo ' <strong>' . esc_html__( 'The business profile is not complete yet, so nothing is output.', 'nguyen-ha-seo-companion' ) . '</strong>';
						if ( current_user_can( 'manage_options' ) ) {
							echo ' <a href="' . esc_url( NH_SEO_Settings_Page::tab_url( 'business' ) ) . '">' . esc_html__( 'Complete the business profile', 'nguyen-ha-seo-companion' ) . '</a>';
						}
					}
					?>
				</p>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Save the box. Hooked to `save_post`.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function save( $post_id, $post = null ) {
		$post_id = absint( $post_id );

		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::nonce_action( $post_id ) ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, NH_SEO_Data_Model::POST_TYPES, true ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$data = self::submitted_data();

		if ( array_key_exists( 'summary', $data ) ) {
			self::store( $post_id, NH_SEO_Keys::META_SUMMARY, nh_seo_sanitize_summary( $data['summary'] ) );
		}
		if ( array_key_exists( 'takeaways', $data ) ) {
			self::store( $post_id, NH_SEO_Keys::META_TAKEAWAYS, nh_seo_sanitize_takeaways( $data['takeaways'] ) );
		}
		if ( array_key_exists( 'faq', $data ) ) {
			self::store( $post_id, NH_SEO_Keys::META_FAQ, nh_seo_sanitize_faq( $data['faq'] ) );
		}
		if ( array_key_exists( 'sources', $data ) ) {
			self::store( $post_id, NH_SEO_Keys::META_SOURCES, nh_seo_sanitize_sources( $data['sources'] ) );
		}
		if ( array_key_exists( 'updated', $data ) ) {
			self::store( $post_id, NH_SEO_Keys::META_UPDATED, nh_seo_sanitize_date( $data['updated'] ) );
		}
		if ( array_key_exists( 'keywords', $data ) ) {
			self::store( $post_id, NH_SEO_Keys::META_KEYWORDS, nh_seo_sanitize_keywords( $data['keywords'] ) );
		}
		if ( array_key_exists( 'local_business', $data ) ) {
			self::store( $post_id, NH_SEO_Keys::META_LOCAL_BUSINESS, nh_seo_sanitize_flag( $data['local_business'] ) );
		}
		if ( array_key_exists( 'pillar', $data ) ) {
			self::store_pillar( $post_id, $data['pillar'] );
		}
		if ( array_key_exists( 'primary_cluster', $data ) && is_object_in_taxonomy( $post->post_type, NH_SEO_Keys::TAXONOMY_CLUSTER ) ) {
			$cluster = NH_SEO_Data_Model::sanitize_id( $data['primary_cluster'] );
			// Only a cluster that is assigned to the post can be its primary one.
			self::store( $post_id, NH_SEO_Keys::META_PRIMARY_CLUSTER, $cluster && has_term( $cluster, NH_SEO_Keys::TAXONOMY_CLUSTER, $post ) ? $cluster : 0 );
		}
		if ( array_key_exists( 'hub_term', $data ) && 'page' === $post->post_type ) {
			self::store( $post_id, NH_SEO_Keys::META_HUB_TERM, NH_SEO_Data_Model::sanitize_hub_term( $data['hub_term'] ) );
		}
	}

	/**
	 * The submitted field group, unslashed. Callers must run every value through a sanitizer.
	 *
	 * @return array
	 */
	private static function submitted_data() {
		if ( ! isset( $_POST[ self::FIELD_GROUP ] ) || ! is_array( $_POST[ self::FIELD_GROUP ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is verified by the caller.
			return array();
		}
		return wp_unslash( $_POST[ self::FIELD_GROUP ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by the caller; every value goes through a nh_seo_sanitize_* function.
	}

	/**
	 * Save the Pillar ID and tell the editor when the value was refused.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $raw     Submitted value.
	 * @return void
	 */
	private static function store_pillar( $post_id, $raw ) {
		$raw    = is_scalar( $raw ) ? trim( (string) $raw ) : '';
		$pillar = '' === $raw ? 0 : nh_seo_sanitize_pillar_id( $raw );

		if ( $pillar && (int) $post_id === $pillar ) {
			$pillar = 0; // A page cannot be its own Pillar.
		}
		if ( '' !== $raw && ! $pillar ) {
			NH_SEO_Admin_Notices::add(
				sprintf(
					/* translators: %s: the value typed in the Pillar page ID field. */
					__( 'The Pillar page ID "%s" was ignored. It must be the ID of another published, public post or page.', 'nguyen-ha-seo-companion' ),
					sanitize_text_field( $raw )
				)
			);
		}
		self::store( $post_id, NH_SEO_Keys::META_PILLAR, $pillar );
	}

	/**
	 * Save a value, or delete the meta when the value is empty.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key    Meta key.
	 * @param mixed  $value   Sanitized value.
	 * @return void
	 */
	private static function store( $post_id, $key, $value ) {
		if ( '' === $value || array() === $value || 0 === $value ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		// update_post_meta() unslashes its input, so slash first to keep backslashes in the content.
		update_post_meta( $post_id, $key, wp_slash( $value ) );
	}
}
