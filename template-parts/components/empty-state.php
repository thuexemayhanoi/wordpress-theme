<?php
/**
 * Friendly empty state: a message, a search form and popular topics.
 *
 * Arguments: title, text, search (bool), topics (bool).
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

$nh_editorial_empty  = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'title'  => __( 'Nothing found', 'nguyen-ha-editorial' ),
		'text'   => '',
		'search' => true,
		'topics' => true,
	)
);
$nh_editorial_topics = $nh_editorial_empty['topics'] ? nh_editorial_search_topics() : array();
?>
<section class="empty-state">
	<h2 class="empty-state__title"><?php echo esc_html( $nh_editorial_empty['title'] ); ?></h2>
	<?php if ( '' !== $nh_editorial_empty['text'] ) : ?>
		<p class="empty-state__text"><?php echo esc_html( $nh_editorial_empty['text'] ); ?></p>
	<?php endif; ?>
	<?php
	if ( $nh_editorial_empty['search'] ) {
		get_search_form( array( 'aria_label' => __( 'Search articles', 'nguyen-ha-editorial' ) ) );
	}
	?>
	<?php if ( ! empty( $nh_editorial_topics ) ) : ?>
		<p class="empty-state__label"><?php esc_html_e( 'Popular topics', 'nguyen-ha-editorial' ); ?></p>
		<ul class="chips">
			<?php foreach ( $nh_editorial_topics as $nh_editorial_topic ) : ?>
				<li><a class="chip" href="<?php echo esc_url( $nh_editorial_topic['url'] ); ?>"><?php echo esc_html( $nh_editorial_topic['name'] ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
