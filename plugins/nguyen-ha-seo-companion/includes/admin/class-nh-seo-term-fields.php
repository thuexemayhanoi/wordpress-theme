<?php
/**
 * Term screens: the Pillar page ID of a category or Topic Cluster.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds the "Pillar page ID" field to the add and edit forms of categories and Topic Clusters and saves it.
 *
 * Saving: nonce, capability `edit_term` on the saved term, the value goes through nh_seo_sanitize_pillar_id(),
 * an empty value deletes the meta, and a request that does not carry the field (quick edit, REST, imports)
 * leaves the stored Pillar untouched.
 */
final class NH_SEO_Term_Fields {

	/**
	 * Name of the hidden nonce field.
	 *
	 * @var string
	 */
	const NONCE_FIELD = 'nh_seo_term_nonce';

	/**
	 * Name of the Pillar field.
	 *
	 * @var string
	 */
	const FIELD = 'nh_seo_pillar_id';

	/**
	 * Nonce action for a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return string
	 */
	public static function nonce_action( $taxonomy ) {
		return 'nh_seo_term_fields_' . sanitize_key( $taxonomy );
	}

	/**
	 * Field on the "Add new" form.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return void
	 */
	public static function render_add_field( $taxonomy ) {
		if ( ! self::is_supported( $taxonomy ) ) {
			return;
		}
		wp_nonce_field( self::nonce_action( $taxonomy ), self::NONCE_FIELD, false );
		?>
		<div class="form-field term-nh-seo-pillar-wrap">
			<label for="nh-seo-term-pillar"><?php esc_html_e( 'Pillar page ID', 'nguyen-ha-seo-companion' ); ?></label>
			<input type="text" name="<?php echo esc_attr( self::FIELD ); ?>" id="nh-seo-term-pillar" class="small-text" value="" inputmode="numeric" pattern="[0-9]*" aria-describedby="nh-seo-term-pillar-help">
			<p class="description" id="nh-seo-term-pillar-help"><?php echo esc_html( self::help_text() ); ?></p>
		</div>
		<?php
	}

	/**
	 * Row on the "Edit" form.
	 *
	 * @param WP_Term $term     Term being edited.
	 * @param string  $taxonomy Taxonomy name.
	 * @return void
	 */
	public static function render_edit_field( $term, $taxonomy ) {
		if ( ! $term instanceof WP_Term || ! self::is_supported( $taxonomy ) ) {
			return;
		}
		$pillar = (int) get_term_meta( $term->term_id, NH_SEO_Keys::TERM_PILLAR, true );
		$post   = $pillar ? get_post( $pillar ) : null;

		wp_nonce_field( self::nonce_action( $taxonomy ), self::NONCE_FIELD, false );
		?>
		<tr class="form-field term-nh-seo-pillar-wrap">
			<th scope="row"><label for="nh-seo-term-pillar"><?php esc_html_e( 'Pillar page ID', 'nguyen-ha-seo-companion' ); ?></label></th>
			<td>
				<input type="text" name="<?php echo esc_attr( self::FIELD ); ?>" id="nh-seo-term-pillar" class="small-text" value="<?php echo esc_attr( $pillar ? (string) $pillar : '' ); ?>" inputmode="numeric" pattern="[0-9]*" aria-describedby="nh-seo-term-pillar-help">
				<?php
				if ( $post instanceof WP_Post ) {
					$link = get_edit_post_link( $post, 'raw' );
					echo '<span class="nh-seo-inline-note">';
					if ( $link ) {
						echo '<a href="' . esc_url( $link ) . '">' . esc_html( nh_seo_plain_title( $post ) ) . '</a>';
					} else {
						echo esc_html( nh_seo_plain_title( $post ) );
					}
					echo '</span>';
				}
				?>
				<p class="description" id="nh-seo-term-pillar-help"><?php echo esc_html( self::help_text() ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save the field. Hooked to `created_{taxonomy}` and `edited_{taxonomy}`.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public static function save( $term_id ) {
		$term_id = absint( $term_id );
		$term    = get_term( $term_id );
		if ( ! $term instanceof WP_Term || ! self::is_supported( $term->taxonomy ) ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::nonce_action( $term->taxonomy ) ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::FIELD ] ) ) {
			return; // The field was not part of the submitted form (for example quick edit): keep the stored Pillar.
		}

		$raw    = trim( sanitize_text_field( wp_unslash( $_POST[ self::FIELD ] ) ) );
		$pillar = '' === $raw ? 0 : nh_seo_sanitize_pillar_id( $raw );

		if ( '' !== $raw && ! $pillar ) {
			NH_SEO_Admin_Notices::add(
				sprintf(
					/* translators: %s: the value typed in the Pillar page ID field. */
					__( 'The Pillar page ID "%s" was ignored. It must be the ID of a published, public post or page.', 'nguyen-ha-seo-companion' ),
					$raw
				)
			);
		}

		if ( $pillar ) {
			update_term_meta( $term_id, NH_SEO_Keys::TERM_PILLAR, $pillar );
		} else {
			delete_term_meta( $term_id, NH_SEO_Keys::TERM_PILLAR );
		}
	}

	/**
	 * Whether the taxonomy carries the Pillar field.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return bool
	 */
	private static function is_supported( $taxonomy ) {
		return in_array( $taxonomy, NH_SEO_Admin_Assets::TERM_TAXONOMIES, true );
	}

	/**
	 * Help text shown under the field.
	 *
	 * @return string
	 */
	private static function help_text() {
		return __( 'The published post or page that is the Pillar of this topic. Articles of the topic link to it. Find the ID in the address bar when you edit that page (post=123). Leave it empty when the topic has no Pillar.', 'nguyen-ha-seo-companion' );
	}
}
