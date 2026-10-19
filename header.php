<?php
/**
 * Site header: brand, primary navigation (mega menu), search panel and color-mode toggle.
 *
 * Keyboard order follows the visual order on desktop: brand, navigation, actions.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'nguyen-ha-editorial' ); ?></a>
<?php do_action( 'nh_editorial_after_skip_link' ); ?>

<header id="masthead" class="site-header">
	<div class="wrap header-row">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php echo nh_editorial_brand_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside the helper. ?>
		</a>

		<nav id="primary-navigation" class="primary-nav" aria-label="<?php esc_attr_e( 'Primary', 'nguyen-ha-editorial' ); ?>">
			<?php nh_editorial_primary_menu(); ?>
		</nav>

		<div class="header-actions">
			<button type="button" class="icon-btn search-toggle" aria-expanded="false" aria-controls="header-search">
				<?php echo nh_editorial_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Search', 'nguyen-ha-editorial' ); ?></span>
			</button>
			<?php if ( nh_editorial_option( 'nh_dark_mode' ) ) : ?>
			<button type="button" class="icon-btn mode-toggle" aria-pressed="false">
				<?php echo nh_editorial_icon( 'moon', 'icon--moon' ) . nh_editorial_icon( 'sun', 'icon--sun' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Dark mode', 'nguyen-ha-editorial' ); ?></span>
			</button>
			<?php endif; ?>
			<button type="button" class="icon-btn nav-toggle" aria-expanded="false" aria-controls="primary-navigation">
				<?php echo nh_editorial_icon( 'menu', 'icon--open' ) . nh_editorial_icon( 'close', 'icon--close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'nguyen-ha-editorial' ); ?></span>
			</button>
		</div>
	</div>

	<div id="header-search" class="header-search" hidden>
		<div class="wrap">
			<?php
			get_search_form(
				array(
					'aria_label' => __( 'Search the site', 'nguyen-ha-editorial' ),
					'suggest'    => true,
				)
			);
			?>
		</div>
	</div>
</header>

<main id="primary" class="site-main" tabindex="-1">
