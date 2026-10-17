<?php
/**
 * Settings → Nguyen Ha SEO Companion: business profile, general settings, tools and status.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * The settings screen. It uses the Settings API, so saving goes through options.php, which checks the
 * nonce and the `manage_options` capability; every value is sanitized by the shared nh_seo_sanitize_*
 * functions. Nothing is pre-filled: the business profile starts empty and must come from the owner.
 */
final class NH_SEO_Settings_Page {

	/**
	 * Menu slug of the page.
	 *
	 * @var string
	 */
	const SLUG = 'nguyen-ha-seo-companion';

	/**
	 * Capability needed to see and change the settings.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Settings group (option_page) of the business profile form.
	 *
	 * @var string
	 */
	const GROUP_BUSINESS = 'nh_seo_business_group';

	/**
	 * Settings group (option_page) of the general settings form.
	 *
	 * @var string
	 */
	const GROUP_GENERAL = 'nh_seo_general_group';

	/**
	 * Tabs, in display order.
	 *
	 * @var string[]
	 */
	const TABS = array( 'business', 'general', 'tools', 'status' );

	/**
	 * Add the page under Settings.
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_options_page(
			'Nguyen Ha SEO Companion',
			'Nguyen Ha SEO Companion',
			self::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::tab_url( 'business' ) ) . '">' . esc_html__( 'Settings', 'nguyen-ha-seo-companion' ) . '</a>' );
		return $links;
	}

	/**
	 * URL of a tab.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public static function tab_url( $tab = 'business' ) {
		return add_query_arg(
			array(
				'page' => self::SLUG,
				'tab'  => in_array( $tab, self::TABS, true ) ? $tab : 'business',
			),
			admin_url( 'options-general.php' )
		);
	}

	/**
	 * The tab being shown, from the query string (a whitelist, so safe to read without a nonce).
	 *
	 * @return string
	 */
	public static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'business'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation, checked against a whitelist.
		return in_array( $tab, self::TABS, true ) ? $tab : 'business';
	}

	/**
	 * Register both options with the Settings API, with their sections and fields.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			self::GROUP_BUSINESS,
			NH_SEO_Keys::OPTION_BUSINESS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_business' ),
				'show_in_rest'      => false,
			)
		);
		register_setting(
			self::GROUP_GENERAL,
			NH_SEO_Keys::OPTION_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_general' ),
				'show_in_rest'      => false,
			)
		);

		foreach ( self::business_sections() as $section_id => $section ) {
			add_settings_section( $section_id, $section['title'], '__return_null', self::GROUP_BUSINESS );
			foreach ( $section['fields'] as $field ) {
				self::add_field( $field, NH_SEO_Keys::OPTION_BUSINESS, self::GROUP_BUSINESS, $section_id );
			}
		}
		foreach ( self::general_sections() as $section_id => $section ) {
			add_settings_section( $section_id, $section['title'], '__return_null', self::GROUP_GENERAL );
			foreach ( $section['fields'] as $field ) {
				self::add_field( $field, NH_SEO_Keys::OPTION_SETTINGS, self::GROUP_GENERAL, $section_id );
			}
		}
	}

	/**
	 * Register one field with the Settings API.
	 *
	 * @param array  $field   Field definition.
	 * @param string $option  Option name.
	 * @param string $page    Settings page (the group).
	 * @param string $section Section ID.
	 * @return void
	 */
	private static function add_field( array $field, $option, $page, $section ) {
		$field['option'] = $option;
		$field['id']     = 'nh-seo-' . $option . '-' . $field['key'];
		if ( 'checkbox' !== $field['type'] && 'media' !== $field['type'] ) {
			$field['label_for'] = $field['id'];
		}
		add_settings_field( $field['id'], $field['label'], array( __CLASS__, 'render_field' ), $page, $section, $field );
	}

	/**
	 * Sections and fields of the business profile.
	 *
	 * @return array<string, array{title: string, fields: array}>
	 */
	public static function business_sections() {
		return array(
			'nh_seo_identity' => array(
				'title'  => __( 'Identity', 'nguyen-ha-seo-companion' ),
				'fields' => array(
					array(
						'key'       => 'name',
						'label'     => __( 'Business name', 'nguyen-ha-seo-companion' ),
						'type'      => 'text',
						'maxlength' => 120,
						'help'      => __( 'The name customers know. Used in structured data and the contact block of the theme.', 'nguyen-ha-seo-companion' ),
					),
					array(
						'key'       => 'legal_name',
						'label'     => __( 'Legal name', 'nguyen-ha-seo-companion' ),
						'type'      => 'text',
						'maxlength' => 160,
						'help'      => __( 'Optional. The registered company name, if it differs from the business name.', 'nguyen-ha-seo-companion' ),
					),
					array(
						'key'     => 'type',
						'label'   => __( 'Business type', 'nguyen-ha-seo-companion' ),
						'type'    => 'select',
						'options' => nh_seo_business_types(),
						'help'    => __( 'The most specific schema.org type that is true for the business. When unsure, keep LocalBusiness.', 'nguyen-ha-seo-companion' ),
					),
					array(
						'key'   => 'url',
						'label' => __( 'Website address', 'nguyen-ha-seo-companion' ),
						'type'  => 'url',
						'help'  => __( 'Optional. The official website of the business, starting with https://.', 'nguyen-ha-seo-companion' ),
					),
					array(
						'key'   => 'logo_id',
						'label' => __( 'Logo', 'nguyen-ha-seo-companion' ),
						'type'  => 'media',
						'help'  => __( 'Optional. An image from the media library. Nothing is output without a logo.', 'nguyen-ha-seo-companion' ),
					),
				),
			),
			'nh_seo_contact'  => array(
				'title'  => __( 'Contact', 'nguyen-ha-seo-companion' ),
				'fields' => array(
					array(
						'key'       => 'phone',
						'label'     => __( 'Phone number', 'nguyen-ha-seo-companion' ),
						'type'      => 'tel',
						'maxlength' => 30,
						'help'      => __( 'A number that customers can really call, with the country code.', 'nguyen-ha-seo-companion' ),
					),
					array(
						'key'   => 'email',
						'label' => __( 'Email address', 'nguyen-ha-seo-companion' ),
						'type'  => 'email',
						'help'  => __( 'Optional. A public contact address.', 'nguyen-ha-seo-companion' ),
					),
				),
			),
			'nh_seo_address'  => array(
				'title'  => __( 'Address', 'nguyen-ha-seo-companion' ),
				'fields' => array(
					array(
						'key'       => 'street',
						'label'     => __( 'Street address', 'nguyen-ha-seo-companion' ),
						'type'      => 'text',
						'maxlength' => 200,
					),
					array(
						'key'       => 'locality',
						'label'     => __( 'City', 'nguyen-ha-seo-companion' ),
						'type'      => 'text',
						'maxlength' => 120,
					),
					array(
						'key'       => 'region',
						'label'     => __( 'State or region', 'nguyen-ha-seo-companion' ),
						'type'      => 'text',
						'maxlength' => 120,
					),
					array(
						'key'       => 'postal',
						'label'     => __( 'Postal code', 'nguyen-ha-seo-companion' ),
						'type'      => 'text',
						'maxlength' => 20,
					),
					array(
						'key'       => 'country',
						'label'     => __( 'Country code', 'nguyen-ha-seo-companion' ),
						'type'      => 'text',
						'maxlength' => 2,
						'help'      => __( 'Two letters (ISO 3166-1), for example VN.', 'nguyen-ha-seo-companion' ),
					),
					array(
						'key'       => 'lat',
						'label'     => __( 'Latitude', 'nguyen-ha-seo-companion' ),
						'type'      => 'text',
						'maxlength' => 16,
						'help'      => __( 'Optional. Decimal degrees between -90 and 90, copied from a map service.', 'nguyen-ha-seo-companion' ),
					),
					array(
						'key'       => 'lng',
						'label'     => __( 'Longitude', 'nguyen-ha-seo-companion' ),
						'type'      => 'text',
						'maxlength' => 16,
						'help'      => __( 'Optional. Decimal degrees between -180 and 180.', 'nguyen-ha-seo-companion' ),
					),
				),
			),
			'nh_seo_extras'   => array(
				'title'  => __( 'Opening hours and links', 'nguyen-ha-seo-companion' ),
				'fields' => array(
					array(
						'key'   => 'hours',
						'label' => __( 'Opening hours', 'nguyen-ha-seo-companion' ),
						'type'  => 'textarea',
						'rows'  => 4,
						'help'  => __( 'One rule per line: days, a space, then the opening and closing time, for example Mo-Su 09:00-21:00 or Sa,Su 10:00-12:30. Days are Mo Tu We Th Fr Sa Su. Lines in another format are ignored. Only enter hours that are accurate.', 'nguyen-ha-seo-companion' ),
					),
					array(
						'key'       => 'price_range',
						'label'     => __( 'Price range', 'nguyen-ha-seo-companion' ),
						'type'      => 'text',
						'maxlength' => 40,
						'help'      => __( 'Optional free text. Leave it empty when unsure.', 'nguyen-ha-seo-companion' ),
					),
					array(
						'key'   => 'has_map',
						'label' => __( 'Map link', 'nguyen-ha-seo-companion' ),
						'type'  => 'url',
						'help'  => __( 'Optional. A link to the business on a map service.', 'nguyen-ha-seo-companion' ),
					),
					array(
						'key'   => 'social',
						'label' => __( 'Social profiles', 'nguyen-ha-seo-companion' ),
						'type'  => 'textarea',
						'rows'  => 4,
						'help'  => __( 'One profile address per line (http or https). Used to tell search engines which profiles belong to the business.', 'nguyen-ha-seo-companion' ),
					),
				),
			),
		);
	}

	/**
	 * Sections and fields of the general settings.
	 *
	 * @return array<string, array{title: string, fields: array}>
	 */
	public static function general_sections() {
		return array(
			'nh_seo_structured' => array(
				'title'  => __( 'Structured data', 'nguyen-ha-seo-companion' ),
				'fields' => array(
					array(
						'key'     => 'schema_mode',
						'label'   => __( 'Structured data mode', 'nguyen-ha-seo-companion' ),
						'type'    => 'select',
						'options' => array(
							'auto'   => __( 'Automatic (recommended)', 'nguyen-ha-seo-companion' ),
							'extras' => __( 'Extras only: FAQ and local business next to another SEO plugin', 'nguyen-ha-seo-companion' ),
							'full'   => __( 'Full: this plugin outputs the whole graph', 'nguyen-ha-seo-companion' ),
							'off'    => __( 'Off', 'nguyen-ha-seo-companion' ),
						),
						'help'    => __( 'Automatic uses "extras" when another SEO plugin is active and "full" otherwise, so the page never gets two competing graphs.', 'nguyen-ha-seo-companion' ),
					),
					array(
						'key'   => 'localbusiness_on_home',
						'label' => __( 'Local business on the home page', 'nguyen-ha-seo-companion' ),
						'type'  => 'checkbox',
						'text'  => __( 'Output LocalBusiness data on the front page', 'nguyen-ha-seo-companion' ),
						'help'  => __( 'Only used when the business profile is complete.', 'nguyen-ha-seo-companion' ),
					),
				),
			),
			'nh_seo_content'    => array(
				'title'  => __( 'Content', 'nguyen-ha-seo-companion' ),
				'fields' => array(
					array(
						'key'   => 'fallback_render',
						'label' => __( 'Fallback rendering', 'nguyen-ha-seo-companion' ),
						'type'  => 'checkbox',
						'text'  => __( 'Show the quick answer, takeaways, FAQ and sources when the theme does not', 'nguyen-ha-seo-companion' ),
						'help'  => __( 'Only the parts the active theme does not display itself are added to the article. FAQ structured data is only output when the FAQ is visible.', 'nguyen-ha-seo-companion' ),
					),
					array(
						'key'   => 'auto_cluster_terms',
						'label' => __( 'Topic Clusters', 'nguyen-ha-seo-companion' ),
						'type'  => 'checkbox',
						'text'  => __( 'Create Topic Clusters automatically from the cluster alias sent by publishing workflows', 'nguyen-ha-seo-companion' ),
						'help'  => __( 'When off, an alias is only applied if a cluster with that slug or name already exists.', 'nguyen-ha-seo-companion' ),
					),
				),
			),
			'nh_seo_search'     => array(
				'title'  => __( 'Search and caching', 'nguyen-ha-seo-companion' ),
				'fields' => array(
					array(
						'key'     => 'search_mode',
						'label'   => __( 'Site search', 'nguyen-ha-seo-companion' ),
						'type'    => 'select',
						'options' => array(
							'auto'   => __( 'Automatic: use the internal index when it is ready', 'nguyen-ha-seo-companion' ),
							'native' => __( 'WordPress default search', 'nguyen-ha-seo-companion' ),
							'index'  => __( 'Internal index whenever it exists', 'nguyen-ha-seo-companion' ),
						),
						'help'    => __( 'Build the index in the Tools tab. Search falls back to the WordPress default whenever the index is not usable.', 'nguyen-ha-seo-companion' ),
					),
					array(
						'key'   => 'purge_caches',
						'label' => __( 'Page caches', 'nguyen-ha-seo-companion' ),
						'type'  => 'checkbox',
						'text'  => __( 'Purge page caches when content changes', 'nguyen-ha-seo-companion' ),
						'help'  => __( 'Works with the supported cache plugins. No request leaves your server.', 'nguyen-ha-seo-companion' ),
					),
				),
			),
		);
	}

	/**
	 * Print one settings field.
	 *
	 * @param array $field Field definition (from add_field()).
	 * @return void
	 */
	public static function render_field( $field ) {
		$option = $field['option'];
		$key    = $field['key'];
		$name   = $option . '[' . $key . ']';
		$id     = $field['id'];
		$values = NH_SEO_Keys::OPTION_BUSINESS === $option ? self::stored_business() : nh_seo_get_settings();
		$value  = isset( $values[ $key ] ) ? $values[ $key ] : '';
		$help   = isset( $field['help'] ) ? $field['help'] : '';
		$desc   = '' !== $help ? ' aria-describedby="' . esc_attr( $id . '-help' ) . '"' : '';

		switch ( $field['type'] ) {
			case 'textarea':
				$value = is_array( $value ) ? implode( "\n", $value ) : (string) $value;
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" class="large-text code" rows="' . esc_attr( (string) ( isset( $field['rows'] ) ? (int) $field['rows'] : 4 ) ) . '"' . $desc . '>' . esc_textarea( $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $desc is built from escaped parts.
				break;

			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $desc . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $desc is built from escaped parts.
				foreach ( $field['options'] as $option_value => $option_label ) {
					// Lists (business types) are plain values; maps (modes) have keys.
					$option_value = is_int( $option_value ) ? (string) $option_label : (string) $option_value;
					echo '<option value="' . esc_attr( $option_value ) . '"' . selected( (string) $value, $option_value, false ) . '>' . esc_html( (string) $option_label ) . '</option>';
				}
				echo '</select>';
				break;

			case 'checkbox':
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
				echo '<label for="' . esc_attr( $id ) . '"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( 1, (int) $value, false ) . $desc . '> ' . esc_html( $field['text'] ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts and core helpers.
				break;

			case 'media':
				self::render_media_field( $name, $id, (int) $value, $desc );
				break;

			default:
				$type  = in_array( $field['type'], array( 'text', 'url', 'email', 'tel' ), true ) ? $field['type'] : 'text';
				$class = 'country' === $key ? 'small-text nh-seo-uppercase' : 'regular-text';
				$attr  = ' type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" class="' . esc_attr( $class ) . '"';
				if ( ! empty( $field['maxlength'] ) ) {
					$attr .= ' maxlength="' . esc_attr( (string) (int) $field['maxlength'] ) . '"';
				}
				if ( 'tel' === $type ) {
					$attr .= ' inputmode="tel"';
				}
				if ( in_array( $key, array( 'lat', 'lng' ), true ) ) {
					$attr .= ' inputmode="decimal"';
				}
				echo '<input' . $attr . $desc . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
		}

		if ( '' !== $help ) {
			echo '<p class="description" id="' . esc_attr( $id . '-help' ) . '">' . esc_html( $help ) . '</p>';
		}
	}

	/**
	 * Print the logo picker (the script in assets/admin.js drives wp.media).
	 *
	 * @param string $name Input name.
	 * @param string $id   Input ID.
	 * @param int    $logo Attachment ID, or 0.
	 * @param string $desc Prepared aria-describedby attribute.
	 * @return void
	 */
	private static function render_media_field( $name, $id, $logo, $desc ) {
		$logo    = $logo && wp_attachment_is_image( $logo ) ? $logo : 0;
		$preview = $logo ? wp_get_attachment_image(
			$logo,
			array( 96, 96 ),
			false,
			array( 'alt' => '' )
		) : '';
		?>
		<div class="nh-seo-media" data-nh-media>
			<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $logo ? (string) $logo : '' ); ?>" data-nh-media-input<?php echo $desc; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts. ?>>
			<div class="nh-seo-media__preview" data-nh-media-preview><?php echo wp_kses_post( $preview ); ?></div>
			<p>
				<button type="button" class="button" data-nh-media-select><?php esc_html_e( 'Choose logo', 'nguyen-ha-seo-companion' ); ?></button>
				<button type="button" class="button-link button-link-delete" data-nh-media-remove<?php echo $logo ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove logo', 'nguyen-ha-seo-companion' ); ?></button>
			</p>
		</div>
		<?php
	}

	/**
	 * The stored business profile, sanitized but not filtered by other code, for the form.
	 *
	 * @return array<string, mixed>
	 */
	private static function stored_business() {
		$stored = get_option( NH_SEO_Keys::OPTION_BUSINESS, array() );
		return nh_seo_sanitize_business( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Sanitize the business profile and tell the user which entries were not valid.
	 *
	 * @param mixed $input Submitted profile (unslashed by options.php).
	 * @return array<string, mixed>
	 */
	public static function sanitize_business( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = nh_seo_sanitize_business( $input );

		// The logo must be an image of the media library.
		if ( $clean['logo_id'] && ! wp_attachment_is_image( $clean['logo_id'] ) ) {
			$clean['logo_id'] = 0;
		}

		$cleared = array();
		foreach ( array( 'url', 'email', 'has_map', 'country', 'lat', 'lng', 'logo_id' ) as $key ) {
			if ( 'logo_id' === $key ) {
				// A logo is given when it is a positive ID ("" and "0" mean no logo).
				$given = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) && absint( $input[ $key ] ) > 0;
				$empty = ! $clean[ $key ];
			} else {
				$given = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) && '' !== trim( (string) $input[ $key ] );
				$empty = '' === $clean[ $key ]; // A latitude of "0" is a valid value.
			}
			if ( $given && $empty ) {
				$cleared[] = $key;
			}
		}
		foreach ( array( 'hours', 'social' ) as $key ) {
			if ( self::count_lines( isset( $input[ $key ] ) ? $input[ $key ] : '' ) > count( $clean[ $key ] ) ) {
				$cleared[] = $key;
			}
		}

		if ( $cleared && function_exists( 'add_settings_error' ) ) {
			$labels = array();
			foreach ( self::business_sections() as $section ) {
				foreach ( $section['fields'] as $field ) {
					if ( in_array( $field['key'], $cleared, true ) ) {
						$labels[] = $field['label'];
					}
				}
			}
			add_settings_error(
				NH_SEO_Keys::OPTION_BUSINESS,
				'nh_seo_business_cleared',
				sprintf(
					/* translators: %s: comma separated list of field names. */
					__( 'These fields contained invalid or surplus values, which were not saved: %s.', 'nguyen-ha-seo-companion' ),
					implode( ', ', $labels )
				),
				'warning'
			);
		}

		return $clean;
	}

	/**
	 * Sanitize the general settings.
	 *
	 * @param mixed $input Submitted settings (unslashed by options.php).
	 * @return array<string, mixed>
	 */
	public static function sanitize_general( $input ) {
		return nh_seo_sanitize_settings( $input );
	}

	/**
	 * Number of different non-empty lines in a submitted list.
	 *
	 * @param mixed $value Newline separated text or array.
	 * @return int
	 */
	private static function count_lines( $value ) {
		$lines = array();
		foreach ( nh_seo_to_list( $value ) as $line ) {
			$line = is_scalar( $line ) ? trim( (string) $line ) : '';
			if ( '' !== $line ) {
				$lines[ $line ] = true;
			}
		}
		return count( $lines );
	}

	/**
	 * Print the settings page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'nguyen-ha-seo-companion' ), 403 );
		}

		$tab = self::current_tab();
		?>
		<div class="wrap nh-seo-settings">
			<h1>Nguyen Ha SEO Companion</h1>
			<?php self::render_tabs( $tab ); ?>
			<div class="nh-seo-settings__panel">
				<?php
				switch ( $tab ) {
					case 'general':
						self::render_form( self::GROUP_GENERAL );
						break;
					case 'tools':
						self::render_tools();
						break;
					case 'status':
						self::render_status();
						break;
					default:
						self::render_business_intro();
						self::render_form( self::GROUP_BUSINESS );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Print the tab navigation.
	 *
	 * @param string $current Current tab.
	 * @return void
	 */
	private static function render_tabs( $current ) {
		$labels = array(
			'business' => __( 'Business profile', 'nguyen-ha-seo-companion' ),
			'general'  => __( 'General', 'nguyen-ha-seo-companion' ),
			'tools'    => __( 'Tools', 'nguyen-ha-seo-companion' ),
			'status'   => __( 'Status', 'nguyen-ha-seo-companion' ),
		);
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Settings sections', 'nguyen-ha-seo-companion' ) . '">';
		foreach ( $labels as $tab => $label ) {
			$active = $tab === $current;
			echo '<a href="' . esc_url( self::tab_url( $tab ) ) . '" class="nav-tab' . ( $active ? ' nav-tab-active' : '' ) . '"' . ( $active ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * Print a Settings API form for a group.
	 *
	 * @param string $group Settings group.
	 * @return void
	 */
	private static function render_form( $group ) {
		echo '<form action="options.php" method="post">';
		settings_fields( $group );
		do_settings_sections( $group );
		submit_button();
		echo '</form>';
	}

	/**
	 * Print the verification warning above the business form.
	 *
	 * @return void
	 */
	private static function render_business_intro() {
		?>
		<div class="notice notice-info inline nh-seo-verify">
			<p><strong><?php esc_html_e( 'Enter only information that is accurate and publicly verifiable.', 'nguyen-ha-seo-companion' ); ?></strong></p>
			<p><?php esc_html_e( 'Nothing here is pre-filled or guessed. Search engines and visitors rely on this data, so leave a field empty rather than approximate it. Structured data for a local business is only produced when the name, phone number, street address and city are filled in.', 'nguyen-ha-seo-companion' ); ?></p>
			<p>
				<?php
				echo esc_html(
					nh_seo_business_is_complete( self::stored_business() )
						? __( 'The profile is complete.', 'nguyen-ha-seo-companion' )
						: __( 'The profile is not complete yet.', 'nguyen-ha-seo-companion' )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Print the Tools tab: other modules add their panels with the nh_seo_render_tools_panel action.
	 *
	 * @return void
	 */
	private static function render_tools() {
		echo '<h2>' . esc_html__( 'Tools', 'nguyen-ha-seo-companion' ) . '</h2>';

		$has_panels = (bool) has_action( 'nh_seo_render_tools_panel' );

		/**
		 * Prints the panels of the Tools tab (for example the search index tools).
		 */
		do_action( 'nh_seo_render_tools_panel' );

		if ( ! $has_panels ) {
			echo '<p>' . esc_html__( 'There are no tools available.', 'nguyen-ha-seo-companion' ) . '</p>';
		}
	}

	/**
	 * Print the read-only Status tab.
	 *
	 * @return void
	 */
	private static function render_status() {
		$rows = self::status_rows();
		echo '<h2>' . esc_html__( 'Status', 'nguyen-ha-seo-companion' ) . '</h2>';
		echo '<table class="widefat striped nh-seo-status" role="presentation"><tbody>';
		foreach ( $rows as $label => $value ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Label and value of every line of the Status tab (plain text).
	 *
	 * @return array<string, string>
	 */
	public static function status_rows() {
		$settings = nh_seo_get_settings();
		$seo      = nh_seo_detect_seo_plugin();
		$known    = array(
			'yoast'         => 'Yoast SEO',
			'rank-math'     => 'Rank Math SEO',
			'aioseo'        => 'All in One SEO',
			'seopress'      => 'SEOPress',
			'seo-framework' => 'The SEO Framework',
			'smartcrawl'    => 'SmartCrawl',
			'slim-seo'      => 'Slim SEO',
			'squirrly'      => 'Squirrly SEO',
		);

		$modes = array(
			'full'   => __( 'Full: this plugin outputs the whole graph', 'nguyen-ha-seo-companion' ),
			'extras' => __( 'Extras only: FAQ and local business next to another SEO plugin', 'nguyen-ha-seo-companion' ),
			'off'    => __( 'Off', 'nguyen-ha-seo-companion' ),
		);
		$mode  = nh_seo_schema_mode();

		$features = array(
			'summary'   => __( 'Quick answer', 'nguyen-ha-seo-companion' ),
			'takeaways' => __( 'Key takeaways', 'nguyen-ha-seo-companion' ),
			'faq'       => __( 'FAQ', 'nguyen-ha-seo-companion' ),
			'sources'   => __( 'Sources', 'nguyen-ha-seo-companion' ),
			'updated'   => __( 'Update date', 'nguyen-ha-seo-companion' ),
		);
		$theme    = array();
		foreach ( $features as $feature => $label ) {
			if ( nh_seo_theme_supports( $feature ) ) {
				/* translators: %s: name of an editorial block, such as FAQ. */
				$theme[] = sprintf( __( '%s: shown by the theme', 'nguyen-ha-seo-companion' ), $label );
			} elseif ( ! empty( $settings['fallback_render'] ) ) {
				/* translators: %s: name of an editorial block, such as FAQ. */
				$theme[] = sprintf( __( '%s: shown by the plugin fallback', 'nguyen-ha-seo-companion' ), $label );
			} else {
				/* translators: %s: name of an editorial block, such as FAQ. */
				$theme[] = sprintf( __( '%s: not shown', 'nguyen-ha-seo-companion' ), $label );
			}
		}

		$clusters = wp_count_terms(
			array(
				'taxonomy'   => NH_SEO_Keys::TAXONOMY_CLUSTER,
				'hide_empty' => false,
			)
		);

		/**
		 * Filters the one-line status of the internal search index shown on the Status tab.
		 *
		 * @param string $status Status text. 'n/a' when the search module is not available.
		 */
		$search = apply_filters( 'nh_seo_search_status', 'n/a' );

		return array(
			__( 'Plugin version', 'nguyen-ha-seo-companion' )            => NH_SEO_VERSION,
			__( 'SEO plugin detected', 'nguyen-ha-seo-companion' )       => '' === $seo ? __( 'None', 'nguyen-ha-seo-companion' ) : ( isset( $known[ $seo ] ) ? $known[ $seo ] : $seo ),
			__( 'Structured data in effect', 'nguyen-ha-seo-companion' ) => isset( $modes[ $mode ] ) ? $modes[ $mode ] : $mode,
			__( 'Theme support', 'nguyen-ha-seo-companion' )             => implode( '; ', $theme ),
			__( 'Fallback rendering', 'nguyen-ha-seo-companion' )        => ! empty( $settings['fallback_render'] ) ? __( 'On', 'nguyen-ha-seo-companion' ) : __( 'Off', 'nguyen-ha-seo-companion' ),
			__( 'Internal search index', 'nguyen-ha-seo-companion' )     => is_scalar( $search ) ? (string) $search : 'n/a',
			__( 'Business profile', 'nguyen-ha-seo-companion' )          => nh_seo_business_is_complete( self::stored_business() ) ? __( 'Complete', 'nguyen-ha-seo-companion' ) : __( 'Not complete', 'nguyen-ha-seo-companion' ),
			__( 'Topic Clusters', 'nguyen-ha-seo-companion' )            => is_wp_error( $clusters ) ? '0' : number_format_i18n( (int) $clusters ),
		);
	}
}
