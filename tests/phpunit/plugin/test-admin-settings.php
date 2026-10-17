<?php
/**
 * Tests for the Settings page: registration, sanitization, rendering, tabs and status.
 *
 * @package nguyen-ha-seo-companion
 */

/**
 * NH_SEO_Settings_Page.
 */
class NH_Test_Admin_Settings extends NH_Unit_Test_Case {

	/**
	 * User IDs by role.
	 *
	 * @var int[]
	 */
	private $users = array();

	public function set_up() {
		parent::set_up();
		remove_theme_support( 'nguyen-ha-companion' );
		delete_option( NH_SEO_Keys::OPTION_BUSINESS );
		delete_option( NH_SEO_Keys::OPTION_SETTINGS );
		$this->reset_settings_errors();
		foreach ( array( 'administrator', 'editor' ) as $role ) {
			$this->users[ $role ] = self::factory()->user->create( array( 'role' => $role ) );
		}
		wp_set_current_user( $this->users['administrator'] );

		// The registrations (and their sanitize filters) happen on admin_init in wp-admin. Firing that whole
		// action would also run core's update checks, so call the registration directly.
		NH_SEO_Settings_Page::register_settings();
	}

	public function tear_down() {
		$this->reset_settings_errors();
		unset( $_GET['tab'] );
		parent::tear_down();
	}

	/**
	 * Forget settings errors collected so far.
	 *
	 * @return void
	 */
	private function reset_settings_errors() {
		$GLOBALS['wp_settings_errors'] = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test isolation.
		delete_transient( 'settings_errors' );
	}

	/**
	 * Print the settings page on a tab.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	private function page( $tab = 'business' ) {
		$_GET['tab'] = $tab;
		NH_SEO_Settings_Page::register_settings();
		ob_start();
		NH_SEO_Settings_Page::render_page();
		return ob_get_clean();
	}

	/**
	 * Parse HTML for inspection.
	 *
	 * @param string $html HTML fragment.
	 * @return DOMXPath
	 */
	private function dom( $html ) {
		$document = new DOMDocument();
		libxml_use_internal_errors( true );
		$document->loadHTML( '<?xml encoding="utf-8" ?><html><body>' . $html . '</body></html>' );
		libxml_clear_errors();
		return new DOMXPath( $document );
	}

	/**
	 * Text content of a DOM node (the DOM API property is camelCase).
	 *
	 * @param DOMNode $node Node.
	 * @return string
	 */
	private function text( $node ) {
		return $node->textContent; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
	}

	/**
	 * Tag name of a DOM element (the DOM API property is camelCase).
	 *
	 * @param DOMNode $node Node.
	 * @return string
	 */
	private function tag_name( $node ) {
		return $node->nodeName; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
	}

	/**
	 * A complete, valid business profile as the form would post it.
	 *
	 * @return array
	 */
	private function profile_post() {
		return array(
			'name'        => 'Example Co',
			'legal_name'  => 'Example Company Ltd',
			'type'        => 'AutoRental',
			'url'         => 'https://example.org',
			'phone'       => '+00 000 000 000',
			'email'       => 'hello@example.org',
			'street'      => '1 Example Street',
			'locality'    => 'Example City',
			'region'      => 'Example Region',
			'postal'      => '00000',
			'country'     => 'vn',
			'lat'         => '21.0285',
			'lng'         => '105.8542',
			'hours'       => "Mo-Su 09:00-21:00\nSa,Su 10:00-12:30",
			'price_range' => '$$',
			'has_map'     => 'https://maps.example.org/place',
			'social'      => "https://social.example.org/a\nhttps://social.example.net/b",
			'logo_id'     => '0',
		);
	}

	public function test_both_options_are_registered_with_the_settings_api() {
		$this->assertIsInt( has_action( 'admin_init', array( 'NH_SEO_Settings_Page', 'register_settings' ) ) );
		$this->assertIsInt( has_action( 'admin_menu', array( 'NH_SEO_Settings_Page', 'add_menu' ) ) );
		$registered = get_registered_settings();

		$this->assertArrayHasKey( 'nh_seo_business', $registered );
		$this->assertSame( 'nh_seo_business_group', $registered['nh_seo_business']['group'] );
		$this->assertSame( array( 'NH_SEO_Settings_Page', 'sanitize_business' ), $registered['nh_seo_business']['sanitize_callback'] );
		$this->assertFalse( $registered['nh_seo_business']['show_in_rest'] );

		$this->assertArrayHasKey( 'nh_seo_settings', $registered );
		$this->assertSame( 'nh_seo_general_group', $registered['nh_seo_settings']['group'] );
		$this->assertSame( array( 'NH_SEO_Settings_Page', 'sanitize_general' ), $registered['nh_seo_settings']['sanitize_callback'] );
		$this->assertFalse( $registered['nh_seo_settings']['show_in_rest'] );
	}

	public function test_saving_is_restricted_to_site_administrators() {
		$this->assertSame( 'manage_options', apply_filters( 'option_page_capability_nh_seo_business_group', 'manage_options' ) );
		$this->assertSame( 'manage_options', apply_filters( 'option_page_capability_nh_seo_general_group', 'manage_options' ) );
		$this->assertSame( 'manage_options', NH_SEO_Settings_Page::CAPABILITY );
	}

	public function test_every_profile_key_and_every_setting_has_a_field() {
		$business = array();
		foreach ( NH_SEO_Settings_Page::business_sections() as $section ) {
			foreach ( $section['fields'] as $field ) {
				$business[] = $field['key'];
			}
		}
		$general = array();
		foreach ( NH_SEO_Settings_Page::general_sections() as $section ) {
			foreach ( $section['fields'] as $field ) {
				$general[] = $field['key'];
			}
		}

		$this->assertEqualSets( array_keys( nh_seo_business_defaults() ), $business );
		$this->assertEqualSets( array_keys( nh_seo_settings_defaults() ), $general );
	}

	public function test_fields_are_registered_under_their_sections() {
		NH_SEO_Settings_Page::register_settings();
		global $wp_settings_fields, $wp_settings_sections;

		$this->assertArrayHasKey( 'nh_seo_identity', $wp_settings_sections['nh_seo_business_group'] );
		$this->assertArrayHasKey( 'nh-seo-nh_seo_business-name', $wp_settings_fields['nh_seo_business_group']['nh_seo_identity'] );
		$this->assertArrayHasKey( 'nh-seo-nh_seo_settings-schema_mode', $wp_settings_fields['nh_seo_general_group']['nh_seo_structured'] );
	}

	public function test_the_menu_entry_needs_manage_options_and_sits_under_settings() {
		global $submenu;
		NH_SEO_Settings_Page::add_menu();

		$found = null;
		foreach ( $submenu['options-general.php'] as $item ) {
			if ( 'nguyen-ha-seo-companion' === $item[2] ) {
				$found = $item;
			}
		}
		$this->assertNotNull( $found );
		$this->assertSame( 'manage_options', $found[1] );
		$this->assertSame( 'Nguyen Ha SEO Companion', $found[0] );
		$this->assertTrue( user_can( $this->users['administrator'], $found[1] ) );
		$this->assertFalse( user_can( $this->users['editor'], $found[1] ) );
	}

	public function test_the_page_refuses_users_without_the_capability() {
		wp_set_current_user( $this->users['editor'] );
		$this->expectException( 'WPDieException' );

		NH_SEO_Settings_Page::render_page();
	}

	public function test_the_plugins_screen_gets_a_settings_link() {
		$links = NH_SEO_Settings_Page::action_links( array( '<a href="#">Deactivate</a>' ) );

		$this->assertCount( 2, $links );
		$this->assertStringContainsString( 'options-general.php?page=nguyen-ha-seo-companion&#038;tab=business', $links[0] );
		$this->assertStringContainsString( '>Settings</a>', $links[0] );
		$this->assertIsInt( has_filter( 'plugin_action_links_' . plugin_basename( NH_SEO_FILE ), array( 'NH_SEO_Settings_Page', 'action_links' ) ) );
	}

	public function test_tabs_come_from_a_whitelist() {
		foreach ( array( 'business', 'general', 'tools', 'status' ) as $tab ) {
			$_GET['tab'] = $tab;
			$this->assertSame( $tab, NH_SEO_Settings_Page::current_tab() );
		}
		foreach ( array( 'nope', '../../etc/passwd', '<script>', '', array( 'general' ) ) as $bad ) {
			$_GET['tab'] = $bad;
			$this->assertSame( 'business', NH_SEO_Settings_Page::current_tab(), wp_json_encode( $bad ) );
		}
		unset( $_GET['tab'] );
		$this->assertSame( 'business', NH_SEO_Settings_Page::current_tab() );

		$this->assertStringContainsString( 'tab=status', NH_SEO_Settings_Page::tab_url( 'status' ) );
		$this->assertStringContainsString( 'tab=business', NH_SEO_Settings_Page::tab_url( 'bogus' ) );
	}

	public function test_the_page_shows_the_four_tabs_and_marks_the_current_one() {
		$html  = $this->page( 'general' );
		$xpath = $this->dom( $html );

		$tabs   = $xpath->query( '//nav[contains(@class,"nav-tab-wrapper")]/a' );
		$labels = array();
		foreach ( $tabs as $tab ) {
			$labels[] = trim( $this->text( $tab ) );
		}
		$this->assertSame( array( 'Business profile', 'General', 'Tools', 'Status' ), $labels );
		$current = $xpath->query( '//nav//a[@aria-current="page"]' );
		$this->assertSame( 1, $current->length );
		$this->assertSame( 'General', $this->text( $current->item( 0 ) ) );
		$this->assertStringContainsString( 'nav-tab-active', $current->item( 0 )->getAttribute( 'class' ) );
		$this->assertSame( 1, $xpath->query( '//h1' )->length );
	}

	public function test_the_business_form_posts_to_options_php_with_the_settings_api_nonce() {
		$xpath = $this->dom( $this->page( 'business' ) );

		$form = $xpath->query( '//form[@action="options.php"]' );
		$this->assertSame( 1, $form->length );
		$this->assertSame( 'post', $form->item( 0 )->getAttribute( 'method' ) );
		$this->assertSame( 'nh_seo_business_group', $xpath->query( '//input[@name="option_page"]' )->item( 0 )->getAttribute( 'value' ) );
		$this->assertSame( 'update', $xpath->query( '//input[@name="action"]' )->item( 0 )->getAttribute( 'value' ) );

		$nonce = $xpath->query( '//input[@name="_wpnonce"]' )->item( 0 )->getAttribute( 'value' );
		$this->assertNotFalse( wp_verify_nonce( $nonce, 'nh_seo_business_group-options' ) );
		$this->assertFalse( wp_verify_nonce( $nonce, 'nh_seo_general_group-options' ), 'each form has its own nonce' );
	}

	public function test_nothing_is_pre_filled_in_the_business_profile_form() {
		$xpath = $this->dom( $this->page( 'business' ) );

		$inputs = $xpath->query( '//form//input[starts-with(@name,"nh_seo_business[")]' );
		$this->assertGreaterThanOrEqual( 10, $inputs->length );
		foreach ( $inputs as $input ) {
			$this->assertSame( '', $input->getAttribute( 'value' ), $input->getAttribute( 'name' ) . ' must start empty' );
		}
		foreach ( $xpath->query( '//form//textarea' ) as $textarea ) {
			$this->assertSame( '', trim( $this->text( $textarea ) ), $textarea->getAttribute( 'name' ) . ' must start empty' );
		}
		$selected = $xpath->query( '//select[@name="nh_seo_business[type]"]/option[@selected]' );
		$this->assertSame( 1, $selected->length );
		$this->assertSame( 'LocalBusiness', $selected->item( 0 )->getAttribute( 'value' ), 'the neutral type is the default' );
	}

	public function test_the_business_form_explains_that_data_must_be_verified() {
		$html = $this->page( 'business' );

		$this->assertStringContainsString( 'Enter only information that is accurate and publicly verifiable.', $html );
		$this->assertStringContainsString( 'Nothing here is pre-filled or guessed.', $html );
		$this->assertStringContainsString( 'The profile is not complete yet.', $html );

		update_option( NH_SEO_Keys::OPTION_BUSINESS, $this->profile_post() );
		$this->assertStringContainsString( 'The profile is complete.', $this->page( 'business' ) );
	}

	public function test_the_business_form_has_a_field_for_every_profile_key() {
		$xpath = $this->dom( $this->page( 'business' ) );

		foreach ( array_keys( nh_seo_business_defaults() ) as $key ) {
			$this->assertGreaterThanOrEqual( 1, $xpath->query( '//*[@name="nh_seo_business[' . $key . ']"]' )->length, $key );
		}
		$this->assertSame( 'tel', $xpath->query( '//input[@name="nh_seo_business[phone]"]' )->item( 0 )->getAttribute( 'type' ) );
		$this->assertSame( 'email', $xpath->query( '//input[@name="nh_seo_business[email]"]' )->item( 0 )->getAttribute( 'type' ) );
		$this->assertSame( '2', $xpath->query( '//input[@name="nh_seo_business[country]"]' )->item( 0 )->getAttribute( 'maxlength' ) );
		$types = $xpath->query( '//select[@name="nh_seo_business[type]"]/option' );
		$this->assertSame(
			nh_seo_business_types(),
			array_map(
				static function ( $option ) {
					return $option->getAttribute( 'value' );
				},
				iterator_to_array( $types )
			)
		);
	}

	public function test_stored_values_are_shown_in_the_form_and_survive_a_second_save() {
		$post = $this->profile_post();
		update_option( NH_SEO_Keys::OPTION_BUSINESS, $post );
		$stored = get_option( NH_SEO_Keys::OPTION_BUSINESS );

		$xpath = $this->dom( $this->page( 'business' ) );
		$this->assertSame( 'Example Co', $xpath->query( '//input[@name="nh_seo_business[name]"]' )->item( 0 )->getAttribute( 'value' ) );
		$this->assertSame( 'VN', $xpath->query( '//input[@name="nh_seo_business[country]"]' )->item( 0 )->getAttribute( 'value' ) );
		$this->assertSame( 'AutoRental', $xpath->query( '//select[@name="nh_seo_business[type]"]/option[@selected]' )->item( 0 )->getAttribute( 'value' ) );
		$this->assertSame( "Mo-Su 09:00-21:00\nSa,Su 10:00-12:30", trim( $this->text( $xpath->query( '//textarea[@name="nh_seo_business[hours]"]' )->item( 0 ) ) ) );

		// Submit exactly what the form shows: the stored profile must not change.
		$resubmitted = array();
		foreach ( array_keys( nh_seo_business_defaults() ) as $key ) {
			$node = $xpath->query( '//*[@name="nh_seo_business[' . $key . ']"]' )->item( 0 );
			if ( 'select' === $this->tag_name( $node ) ) {
				$resubmitted[ $key ] = $xpath->query( './option[@selected]', $node )->item( 0 )->getAttribute( 'value' );
			} elseif ( 'textarea' === $this->tag_name( $node ) ) {
				$resubmitted[ $key ] = $this->text( $node );
			} else {
				$resubmitted[ $key ] = $node->getAttribute( 'value' );
			}
		}
		update_option( NH_SEO_Keys::OPTION_BUSINESS, $resubmitted );
		$this->assertSame( $stored, get_option( NH_SEO_Keys::OPTION_BUSINESS ) );
		$this->assertSame( array(), get_settings_errors( 'nh_seo_business' ), 'a clean resubmission raises no warning' );
	}

	public function test_hostile_stored_values_cannot_break_out_of_the_form() {
		update_option(
			NH_SEO_Keys::OPTION_BUSINESS,
			array(
				'name'     => '" onfocus="alert(1)" x="',
				'locality' => "'><script>alert(2)</script>",
				'hours'    => "Mo-Su 09:00-21:00\n</textarea><script>alert(3)</script>",
			)
		);

		$html  = $this->page( 'business' );
		$xpath = $this->dom( $html );

		$this->assertStringNotContainsString( '<script', $html );
		$name = $xpath->query( '//input[@name="nh_seo_business[name]"]' )->item( 0 );
		$this->assertFalse( $name->hasAttribute( 'onfocus' ), 'a quote in a value must not open a new attribute' );
		$this->assertSame( '" onfocus="alert(1)" x="', $name->getAttribute( 'value' ) );
	}

	public function test_valid_business_input_round_trips() {
		update_option( NH_SEO_Keys::OPTION_BUSINESS, $this->profile_post() );

		$profile = nh_seo_get_business_profile();
		$this->assertSame( 'Example Co', $profile['name'] );
		$this->assertSame( 'Example Company Ltd', $profile['legal_name'] );
		$this->assertSame( 'AutoRental', $profile['type'] );
		$this->assertSame( 'https://example.org', $profile['url'] );
		$this->assertSame( '+00 000 000 000', $profile['phone'] );
		$this->assertSame( 'hello@example.org', $profile['email'] );
		$this->assertSame( 'VN', $profile['country'] );
		$this->assertSame( '21.0285', $profile['lat'] );
		$this->assertSame( '105.8542', $profile['lng'] );
		$this->assertSame( array( 'Mo-Su 09:00-21:00', 'Sa,Su 10:00-12:30' ), $profile['hours'] );
		$this->assertSame( '$$', $profile['price_range'] );
		$this->assertSame( array( 'https://social.example.org/a', 'https://social.example.net/b' ), $profile['social'] );
		$this->assertTrue( nh_seo_business_is_complete( $profile ) );
		$this->assertSame( array(), get_settings_errors( 'nh_seo_business' ) );
	}

	public function test_invalid_business_input_is_cleaned_and_reported_once() {
		update_option(
			NH_SEO_Keys::OPTION_BUSINESS,
			array(
				'name'    => '<b>Shop</b>',
				'type'    => 'NotAType',
				'url'     => 'javascript:alert(1)',
				'email'   => 'not-an-email',
				'country' => '123',
				'lat'     => '999',
				'lng'     => 'abc',
				'has_map' => 'ftp://example.org/map',
				'hours'   => "Mo-Su 09:00-21:00\nevery day\nMo-Su 25:00-26:00",
				'social'  => "https://social.example.org/a\njavascript:alert(1)",
				'logo_id' => '999999',
				'phone'   => '+00 000 <script>',
			)
		);

		$profile = get_option( NH_SEO_Keys::OPTION_BUSINESS );
		$this->assertSame( 'Shop', $profile['name'] );
		$this->assertSame( 'LocalBusiness', $profile['type'] );
		foreach ( array( 'url', 'email', 'country', 'lat', 'lng', 'has_map' ) as $key ) {
			$this->assertSame( '', $profile[ $key ], $key );
		}
		$this->assertSame( array( 'Mo-Su 09:00-21:00' ), $profile['hours'] );
		$this->assertSame( array( 'https://social.example.org/a' ), $profile['social'] );
		$this->assertSame( 0, $profile['logo_id'] );
		$this->assertSame( '+00 000', $profile['phone'] );

		$errors = get_settings_errors( 'nh_seo_business' );
		$this->assertCount( 1, $errors, 'the warning is raised once even though update_option sanitizes twice' );
		$this->assertSame( 'nh_seo_business_cleared', $errors[0]['code'] );
		$this->assertSame( 'warning', $errors[0]['type'] );
		foreach ( array( 'Website address', 'Email address', 'Country code', 'Latitude', 'Longitude', 'Map link', 'Opening hours', 'Social profiles', 'Logo' ) as $label ) {
			$this->assertStringContainsString( $label, $errors[0]['message'], $label );
		}
		$this->assertStringNotContainsString( 'Business name', $errors[0]['message'] );
	}

	public function test_a_coordinate_of_zero_is_valid() {
		update_option(
			NH_SEO_Keys::OPTION_BUSINESS,
			array(
				'lat' => '0',
				'lng' => '0.0',
			)
		);

		$profile = get_option( NH_SEO_Keys::OPTION_BUSINESS );
		$this->assertSame( '0', $profile['lat'] );
		$this->assertSame( '0', $profile['lng'] );
		$this->assertSame( array(), get_settings_errors( 'nh_seo_business' ) );
	}

	public function test_only_a_real_image_can_be_the_logo() {
		$image = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'logo',
			)
		);
		update_post_meta( $image, '_wp_attached_file', 'logo.jpg' );
		$pdf = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'application/pdf',
				'post_title'     => 'doc',
			)
		);
		update_post_meta( $pdf, '_wp_attached_file', 'doc.pdf' );

		update_option( NH_SEO_Keys::OPTION_BUSINESS, array( 'logo_id' => (string) $image ) );
		$this->assertSame( $image, get_option( NH_SEO_Keys::OPTION_BUSINESS )['logo_id'] );

		update_option( NH_SEO_Keys::OPTION_BUSINESS, array( 'logo_id' => (string) $pdf ) );
		$this->assertSame( 0, get_option( NH_SEO_Keys::OPTION_BUSINESS )['logo_id'], 'a PDF is not a logo' );

		$post = self::factory()->post->create();
		update_option( NH_SEO_Keys::OPTION_BUSINESS, array( 'logo_id' => (string) $post ) );
		$this->assertSame( 0, get_option( NH_SEO_Keys::OPTION_BUSINESS )['logo_id'], 'a post is not a logo' );
	}

	public function test_the_logo_picker_shows_the_current_logo() {
		$image = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'logo',
			)
		);
		update_post_meta( $image, '_wp_attached_file', 'logo.jpg' );
		update_option( NH_SEO_Keys::OPTION_BUSINESS, array( 'logo_id' => (string) $image ) );

		$xpath = $this->dom( $this->page( 'business' ) );

		$this->assertSame( (string) $image, $xpath->query( '//input[@name="nh_seo_business[logo_id]"]' )->item( 0 )->getAttribute( 'value' ) );
		$this->assertSame( 1, $xpath->query( '//*[@data-nh-media-preview]//img' )->length );
		$this->assertFalse( $xpath->query( '//button[@data-nh-media-remove]' )->item( 0 )->hasAttribute( 'hidden' ), 'the remove button is shown when there is a logo' );
	}

	public function test_non_array_business_input_falls_back_to_the_empty_profile() {
		update_option( NH_SEO_Keys::OPTION_BUSINESS, 'garbage' );

		$profile = get_option( NH_SEO_Keys::OPTION_BUSINESS );
		$this->assertSame( nh_seo_business_defaults(), $profile );
	}

	public function test_general_settings_round_trip() {
		update_option(
			NH_SEO_Keys::OPTION_SETTINGS,
			array(
				'schema_mode'           => 'extras',
				'search_mode'           => 'native',
				'auto_cluster_terms'    => '0',
				'fallback_render'       => '0',
				'purge_caches'          => '0',
				'localbusiness_on_home' => '1',
			)
		);

		$this->assertSame(
			array(
				'schema_mode'           => 'extras',
				'search_mode'           => 'native',
				'auto_cluster_terms'    => 0,
				'fallback_render'       => 0,
				'purge_caches'          => 0,
				'localbusiness_on_home' => 1,
			),
			nh_seo_get_settings()
		);
		$this->assertSame( nh_seo_get_settings(), get_option( NH_SEO_Keys::OPTION_SETTINGS ), 'the stored option is already sanitized' );
	}

	public function test_invalid_general_settings_fall_back_to_the_defaults() {
		update_option(
			NH_SEO_Keys::OPTION_SETTINGS,
			array(
				'schema_mode'     => 'bogus',
				'search_mode'     => array( 'index' ),
				'fallback_render' => 'maybe',
			)
		);

		$settings = nh_seo_get_settings();
		$this->assertSame( 'auto', $settings['schema_mode'] );
		$this->assertSame( 'auto', $settings['search_mode'] );
		$this->assertSame( 0, $settings['fallback_render'] );
		$this->assertSame( 1, $settings['purge_caches'], 'missing keys keep their defaults' );

		update_option( NH_SEO_Keys::OPTION_SETTINGS, 'garbage' );
		$this->assertSame( nh_seo_settings_defaults(), get_option( NH_SEO_Keys::OPTION_SETTINGS ) );
	}

	public function test_the_general_form_pairs_every_checkbox_with_a_hidden_zero() {
		$xpath = $this->dom( $this->page( 'general' ) );

		$this->assertSame( 'nh_seo_general_group', $xpath->query( '//input[@name="option_page"]' )->item( 0 )->getAttribute( 'value' ) );
		foreach ( array( 'auto_cluster_terms', 'fallback_render', 'purge_caches', 'localbusiness_on_home' ) as $key ) {
			$name = 'nh_seo_settings[' . $key . ']';
			$this->assertSame( 1, $xpath->query( '//input[@type="hidden" and @name="' . $name . '" and @value="0"]' )->length, "$key hidden zero" );
			$this->assertSame( 1, $xpath->query( '//input[@type="checkbox" and @name="' . $name . '" and @value="1"]' )->length, "$key checkbox" );
		}
		// The hidden zero comes first, so an unchecked box posts 0 and a checked one posts 1 (the last value wins).
		$order = $xpath->query( '//input[@name="nh_seo_settings[fallback_render]"]' );
		$this->assertSame( 'hidden', $order->item( 0 )->getAttribute( 'type' ) );
		$this->assertSame( 'checkbox', $order->item( 1 )->getAttribute( 'type' ) );
	}

	public function test_the_general_form_reflects_the_stored_settings() {
		update_option(
			NH_SEO_Keys::OPTION_SETTINGS,
			array(
				'schema_mode'           => 'off',
				'search_mode'           => 'index',
				'fallback_render'       => 0,
				'localbusiness_on_home' => 1,
			)
		);

		$xpath = $this->dom( $this->page( 'general' ) );

		$this->assertSame( 'off', $xpath->query( '//select[@name="nh_seo_settings[schema_mode]"]/option[@selected]' )->item( 0 )->getAttribute( 'value' ) );
		$this->assertSame( 'index', $xpath->query( '//select[@name="nh_seo_settings[search_mode]"]/option[@selected]' )->item( 0 )->getAttribute( 'value' ) );
		$this->assertSame( 0, $xpath->query( '//input[@type="checkbox" and @name="nh_seo_settings[fallback_render]" and @checked]' )->length );
		$this->assertSame( 1, $xpath->query( '//input[@type="checkbox" and @name="nh_seo_settings[purge_caches]" and @checked]' )->length );
		$this->assertSame( 1, $xpath->query( '//input[@type="checkbox" and @name="nh_seo_settings[localbusiness_on_home]" and @checked]' )->length );
		$this->assertSame(
			array( 'auto', 'extras', 'full', 'off' ),
			array_map(
				static function ( $option ) {
					return $option->getAttribute( 'value' );
				},
				iterator_to_array( $xpath->query( '//select[@name="nh_seo_settings[schema_mode]"]/option' ) )
			)
		);
	}

	public function test_the_tools_tab_calls_the_tools_panel_action() {
		$calls = 0;
		add_action(
			'nh_seo_render_tools_panel',
			static function () use ( &$calls ) {
				++$calls;
				echo '<div id="nh-test-tools-panel">Search index tools</div>';
			}
		);

		$html = $this->page( 'tools' );

		$this->assertSame( 1, $calls );
		$this->assertStringContainsString( '<div id="nh-test-tools-panel">Search index tools</div>', $html );
		$this->assertStringNotContainsString( 'There are no tools available.', $html );

		$this->page( 'business' );
		$this->page( 'status' );
		$this->assertSame( 1, $calls, 'only the Tools tab runs the action' );
	}

	public function test_the_tools_tab_says_so_when_no_module_adds_a_panel() {
		remove_all_actions( 'nh_seo_render_tools_panel' );

		$this->assertStringContainsString( 'There are no tools available.', $this->page( 'tools' ) );
	}

	public function test_the_status_box_is_read_only_and_shows_the_defaults() {
		$html  = $this->page( 'status' );
		$xpath = $this->dom( $html );

		$this->assertSame( 0, $xpath->query( '//form' )->length );
		$this->assertSame( 0, $xpath->query( '//input | //textarea | //select' )->length );
		$rows = NH_SEO_Settings_Page::status_rows();
		$this->assertSame( 'n/a', $rows['Internal search index'], 'the default search status is n/a' );
		foreach ( array( 'Plugin version', 'SEO plugin detected', 'Structured data in effect', 'Theme support', 'Fallback rendering', 'Internal search index', 'Business profile', 'Topic Clusters' ) as $label ) {
			$this->assertArrayHasKey( $label, $rows );
			$this->assertStringContainsString( '<th scope="row">' . $label . '</th>', $html );
		}
		$this->assertSame( NH_SEO_VERSION, $rows['Plugin version'] );
	}

	public function test_the_status_box_uses_the_search_status_filter_and_escapes_it() {
		add_filter(
			'nh_seo_search_status',
			static function () {
				return 'Ready: 1,234 posts <script>alert(1)</script>';
			}
		);

		$html = $this->page( 'status' );

		$this->assertStringContainsString( 'Ready: 1,234 posts &lt;script&gt;alert(1)&lt;/script&gt;', $html );
		$this->assertStringNotContainsString( '<script', $html );

		remove_all_filters( 'nh_seo_search_status' );
		add_filter(
			'nh_seo_search_status',
			static function () {
				return array( 'not', 'a', 'string' );
			}
		);
		$this->assertSame( 'n/a', NH_SEO_Settings_Page::status_rows()['Internal search index'], 'non-scalar values are ignored' );
	}

	public function test_the_status_box_reports_the_detected_seo_plugin_and_the_effective_mode() {
		$rows = NH_SEO_Settings_Page::status_rows();
		$this->assertSame( 'None', $rows['SEO plugin detected'] );
		$this->assertStringStartsWith( 'Full', $rows['Structured data in effect'] );

		add_filter(
			'nh_seo_detected_seo_plugin',
			static function () {
				return 'rank-math';
			}
		);
		$rows = NH_SEO_Settings_Page::status_rows();
		$this->assertSame( 'Rank Math SEO', $rows['SEO plugin detected'] );
		$this->assertStringStartsWith( 'Extras only', $rows['Structured data in effect'] );

		remove_all_filters( 'nh_seo_detected_seo_plugin' );
		add_filter(
			'nh_seo_detected_seo_plugin',
			static function () {
				return 'some-custom-seo';
			}
		);
		$this->assertSame( 'some-custom-seo', NH_SEO_Settings_Page::status_rows()['SEO plugin detected'] );

		update_option( NH_SEO_Keys::OPTION_SETTINGS, array( 'schema_mode' => 'off' ) );
		$this->assertSame( 'Off', NH_SEO_Settings_Page::status_rows()['Structured data in effect'] );
	}

	public function test_the_status_box_reports_theme_support_and_the_fallback() {
		$rows = NH_SEO_Settings_Page::status_rows();
		$this->assertStringContainsString( 'FAQ: shown by the plugin fallback', $rows['Theme support'] );
		$this->assertSame( 'On', $rows['Fallback rendering'] );

		add_theme_support(
			'nguyen-ha-companion',
			array(
				'faq'     => true,
				'summary' => true,
			)
		);
		$rows = NH_SEO_Settings_Page::status_rows();
		$this->assertStringContainsString( 'FAQ: shown by the theme', $rows['Theme support'] );
		$this->assertStringContainsString( 'Quick answer: shown by the theme', $rows['Theme support'] );
		$this->assertStringContainsString( 'Sources: shown by the plugin fallback', $rows['Theme support'] );

		update_option( NH_SEO_Keys::OPTION_SETTINGS, array( 'fallback_render' => 0 ) );
		$rows = NH_SEO_Settings_Page::status_rows();
		$this->assertSame( 'Off', $rows['Fallback rendering'] );
		$this->assertStringContainsString( 'Sources: not shown', $rows['Theme support'] );
		$this->assertStringContainsString( 'FAQ: shown by the theme', $rows['Theme support'] );
	}

	public function test_the_status_box_reports_business_profile_and_cluster_counts() {
		$rows = NH_SEO_Settings_Page::status_rows();
		$this->assertSame( 'Not complete', $rows['Business profile'] );
		$this->assertSame( '0', $rows['Topic Clusters'] );

		update_option( NH_SEO_Keys::OPTION_BUSINESS, $this->profile_post() );
		self::factory()->term->create_many( 3, array( 'taxonomy' => 'topic_cluster' ) );
		$rows = NH_SEO_Settings_Page::status_rows();
		$this->assertSame( 'Complete', $rows['Business profile'] );
		$this->assertSame( '3', $rows['Topic Clusters'] );
	}

	public function test_the_page_never_calls_other_modules_unless_they_hook_in() {
		// The Tools tab is the only integration point: the other tabs must work on their own.
		foreach ( array( 'business', 'general', 'status' ) as $tab ) {
			$this->assertNotSame( '', $this->page( $tab ), $tab );
		}
	}
}
