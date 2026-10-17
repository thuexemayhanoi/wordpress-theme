<?php
/**
 * Grid of article cards for the main query, followed by pagination.
 *
 * Arguments: heading (card title tag, default h2 because the template already owns the H1),
 * skip (post IDs to leave out, used by the homepage where featured posts are shown above),
 * phrase (search phrase to highlight).
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

$nh_editorial_grid  = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'heading' => 'h2',
		'skip'    => array(),
		'phrase'  => '',
	)
);
$nh_editorial_skip  = array_map( 'absint', (array) $nh_editorial_grid['skip'] );
$nh_editorial_index = 0;
?>
<div class="grid grid--cards">
	<?php
	while ( have_posts() ) {
		the_post();
		if ( in_array( get_the_ID(), $nh_editorial_skip, true ) ) {
			continue;
		}
		get_template_part(
			'template-parts/cards/post-card',
			null,
			array(
				'heading'  => $nh_editorial_grid['heading'],
				// The first row may claim the LCP slot; only the first card that has an image gets it.
				'priority' => $nh_editorial_index < 3,
				'category' => true,
				'phrase'   => (string) $nh_editorial_grid['phrase'],
			)
		);
		++$nh_editorial_index;
	}
	?>
</div>
<?php get_template_part( 'template-parts/components/pagination' ); ?>
