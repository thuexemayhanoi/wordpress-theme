<?php
/**
 * Pagination for the main query. Plain crawlable links (/page/2/), nothing is injected by script.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

global $wp_query;
if ( ! $wp_query || (int) $wp_query->max_num_pages < 2 ) {
	return;
}
?>
<div class="pagination-wrap">
	<?php nh_editorial_posts_pagination(); ?>
</div>
