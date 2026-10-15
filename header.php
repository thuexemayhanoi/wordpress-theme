<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#primary"><?php esc_html_e( 'Skip to content', 'nguyen-ha-editorial' ); ?></a>
<header class="site-header">
<div class="wrap header-row">
<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
<?php if ( has_custom_logo() ) {
    $logo_id = get_theme_mod( 'custom_logo' );
    echo wp_get_attachment_image( $logo_id, 'thumbnail', false, array( 'class' => 'custom-logo' ) );
} else { ?>
<span class="brand-mark" aria-hidden="true">NH</span>
<?php } ?>
<span><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span></a>
<nav id="site-navigation" class="header-nav" aria-label="<?php esc_attr_e( 'Main menu', 'nguyen-ha-editorial' ); ?>">
<?php wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'fallback_cb' => 'wp_page_menu', 'depth' => 2 ) ); ?>
</nav>
<div class="nav-actions">
<button class="icon-btn" id="theme-toggle" type="button" aria-label="<?php esc_attr_e( 'Switch color mode', 'nguyen-ha-editorial' ); ?>" title="<?php esc_attr_e( 'Switch color mode', 'nguyen-ha-editorial' ); ?>">◐</button>
<button class="icon-btn mobile-toggle" id="menu-toggle" type="button" aria-controls="site-navigation" aria-expanded="false" aria-label="<?php esc_attr_e( 'Toggle menu', 'nguyen-ha-editorial' ); ?>">☰</button>
</div></div>
</header>
<main id="primary" class="site-main">
