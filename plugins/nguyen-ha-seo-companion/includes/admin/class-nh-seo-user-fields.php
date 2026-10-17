<?php
/**
 * User profile: author details for search engines (job title and profile links).
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds the job title and sameAs profile links to the profile of users who can write posts, and saves them.
 *
 * Saving: nonce bound to the edited user, capability `edit_user` for that user, only for users who can
 * `edit_posts`, sanitizers from the data model, empty values delete the meta, and a form without the fields
 * leaves the stored values untouched. Nothing is invented: empty profiles stay empty.
 */
final class NH_SEO_User_Fields {

	/**
	 * Name of the hidden nonce field.
	 *
	 * @var string
	 */
	const NONCE_FIELD = 'nh_seo_user_nonce';

	/**
	 * Nonce action for a user.
	 *
	 * @param int $user_id Edited user ID.
	 * @return string
	 */
	public static function nonce_action( $user_id ) {
		return 'nh_seo_user_fields_' . absint( $user_id );
	}

	/**
	 * Print the fields on the profile screens.
	 *
	 * @param WP_User $user User being edited.
	 * @return void
	 */
	public static function render( $user ) {
		if ( ! $user instanceof WP_User || ! user_can( $user, 'edit_posts' ) ) {
			return;
		}

		wp_nonce_field( self::nonce_action( $user->ID ), self::NONCE_FIELD, false );
		?>
		<h2><?php esc_html_e( 'Author details for search engines', 'nguyen-ha-seo-companion' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Shown in the author box and in the structured data of articles. Only fill in what is true and public: nothing is added automatically.', 'nguyen-ha-seo-companion' ); ?></p>
		<table class="form-table" role="presentation">
			<tr class="user-nh-seo-job-title-wrap">
				<th scope="row"><label for="nh-seo-job-title"><?php esc_html_e( 'Job title', 'nguyen-ha-seo-companion' ); ?></label></th>
				<td>
					<input type="text" name="nh_seo_job_title" id="nh-seo-job-title" class="regular-text" maxlength="<?php echo esc_attr( (string) NH_SEO_Data_Model::LIMIT_JOB_TITLE ); ?>" value="<?php echo esc_attr( (string) get_user_meta( $user->ID, NH_SEO_Keys::USER_JOB_TITLE, true ) ); ?>">
				</td>
			</tr>
			<tr class="user-nh-seo-sameas-wrap">
				<th scope="row"><label for="nh-seo-sameas"><?php esc_html_e( 'Profile links', 'nguyen-ha-seo-companion' ); ?></label></th>
				<td>
					<textarea name="nh_seo_sameas" id="nh-seo-sameas" class="large-text code" rows="4" aria-describedby="nh-seo-sameas-help"><?php echo esc_textarea( (string) get_user_meta( $user->ID, NH_SEO_Keys::USER_SAMEAS, true ) ); ?></textarea>
					<p class="description" id="nh-seo-sameas-help"><?php esc_html_e( 'One address per line, for example the author\'s professional profile pages. Only http and https links are kept.', 'nguyen-ha-seo-companion' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save the fields. Hooked to `personal_options_update` and `edit_user_profile_update`.
	 *
	 * @param int $user_id Edited user ID.
	 * @return void
	 */
	public static function save( $user_id ) {
		$user_id = absint( $user_id );

		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::nonce_action( $user_id ) ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_user', $user_id ) || ! user_can( $user_id, 'edit_posts' ) ) {
			return;
		}

		if ( isset( $_POST['nh_seo_job_title'] ) ) {
			self::store( $user_id, NH_SEO_Keys::USER_JOB_TITLE, NH_SEO_Data_Model::sanitize_job_title( wp_unslash( $_POST['nh_seo_job_title'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by the data model.
		}

		if ( isset( $_POST['nh_seo_sameas'] ) ) {
			$raw   = wp_unslash( $_POST['nh_seo_sameas'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by the data model.
			$clean = NH_SEO_Data_Model::sanitize_sameas( $raw );

			$invalid = self::count_invalid_urls( $raw );
			if ( $invalid > 0 ) {
				NH_SEO_Admin_Notices::add(
					sprintf(
						/* translators: %d: number of profile links that were ignored. */
						_n( '%d profile link was ignored because it is not a valid http or https address.', '%d profile links were ignored because they are not valid http or https addresses.', $invalid, 'nguyen-ha-seo-companion' ),
						$invalid
					)
				);
			}
			self::store( $user_id, NH_SEO_Keys::USER_SAMEAS, $clean );
		}
	}

	/**
	 * Number of non-empty lines that are not valid http(s) addresses.
	 *
	 * @param mixed $raw Submitted text.
	 * @return int
	 */
	private static function count_invalid_urls( $raw ) {
		$invalid = 0;
		foreach ( nh_seo_to_list( $raw ) as $line ) {
			$line = is_scalar( $line ) ? trim( (string) $line ) : '';
			if ( '' !== $line && '' === esc_url_raw( $line, nh_seo_allowed_protocols() ) ) {
				++$invalid;
			}
		}
		return $invalid;
	}

	/**
	 * Save a value or delete the meta when it is empty.
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     Meta key.
	 * @param string $value   Sanitized value.
	 * @return void
	 */
	private static function store( $user_id, $key, $value ) {
		if ( '' === $value ) {
			delete_user_meta( $user_id, $key );
			return;
		}
		update_user_meta( $user_id, $key, wp_slash( $value ) );
	}
}
