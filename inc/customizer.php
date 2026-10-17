<?php
/**
 * Customizer: one panel generated from the settings schema in inc/config.php.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Customizer sections. The key matches the "section" field in the schema.
 *
 * @return array<string, array{title: string, priority: int, description?: string}>
 */
function nh_editorial_customizer_sections() {
	return array(
		'brand'   => array(
			'title'    => __( 'Brand', 'nguyen-ha-editorial' ),
			'priority' => 10,
		),
		'layout'  => array(
			'title'    => __( 'Layout and features', 'nguyen-ha-editorial' ),
			'priority' => 20,
		),
		'article' => array(
			'title'    => __( 'Articles', 'nguyen-ha-editorial' ),
			'priority' => 30,
		),
		'home'    => array(
			'title'    => __( 'Homepage', 'nguyen-ha-editorial' ),
			'priority' => 40,
		),
		'cta'     => array(
			'title'       => __( 'Call to action', 'nguyen-ha-editorial' ),
			'description' => __( 'A single call-to-action band shown on the homepage and after articles. Business details (phone, address, opening hours) are managed in the Nguyen Ha SEO Companion plugin so they stay consistent everywhere.', 'nguyen-ha-editorial' ),
			'priority'    => 50,
		),
		'footer'  => array(
			'title'    => __( 'Footer and social', 'nguyen-ha-editorial' ),
			'priority' => 60,
		),
	);
}

/**
 * Register panel, sections, settings and controls.
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager.
 * @return void
 */
function nh_editorial_customize_register( $wp_customize ) {
	$wp_customize->add_panel(
		'nh_editorial',
		array(
			'title'    => __( 'Nguyen Ha Editorial', 'nguyen-ha-editorial' ),
			'priority' => 30,
		)
	);

	foreach ( nh_editorial_customizer_sections() as $id => $section ) {
		$wp_customize->add_section(
			'nh_section_' . $id,
			array_merge( $section, array( 'panel' => 'nh_editorial' ) )
		);
	}

	foreach ( nh_editorial_settings_schema() as $key => $def ) {
		$wp_customize->add_setting(
			$key,
			array(
				'default'           => $def['default'],
				'transport'         => 'refresh',
				'sanitize_callback' => static function ( $value ) use ( $key ) {
					return nh_editorial_sanitize_setting( $key, $value );
				},
			)
		);

		$control = array(
			'label'       => $def['label'],
			'description' => isset( $def['description'] ) ? $def['description'] : '',
			'section'     => 'nh_section_' . $def['section'],
			'settings'    => $key,
		);

		switch ( $def['type'] ) {
			case 'color':
				$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $key, $control ) );
				break;

			case 'image':
				$wp_customize->add_control(
					new WP_Customize_Media_Control(
						$wp_customize,
						$key,
						array_merge( $control, array( 'mime_type' => 'image' ) )
					)
				);
				break;

			case 'checkbox':
				$wp_customize->add_control( $key, array_merge( $control, array( 'type' => 'checkbox' ) ) );
				break;

			case 'number':
				$wp_customize->add_control(
					$key,
					array_merge(
						$control,
						array(
							'type'        => 'number',
							'input_attrs' => array(
								'min' => $def['min'],
								'max' => $def['max'],
							),
						)
					)
				);
				break;

			case 'select':
				$wp_customize->add_control(
					$key,
					array_merge(
						$control,
						array(
							'type'    => 'select',
							'choices' => $def['choices'],
						)
					)
				);
				break;

			case 'url':
				$wp_customize->add_control( $key, array_merge( $control, array( 'type' => 'url' ) ) );
				break;

			case 'urls':
			case 'kses':
				$wp_customize->add_control( $key, array_merge( $control, array( 'type' => 'textarea' ) ) );
				break;

			default:
				$wp_customize->add_control( $key, array_merge( $control, array( 'type' => 'text' ) ) );
		}
	}
}
add_action( 'customize_register', 'nh_editorial_customize_register' );
