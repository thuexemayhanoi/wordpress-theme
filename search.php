<?php
/**
 * Search results: the phrase, how many results, matching topics, a topic filter and the articles.
 *
 * Search pages are not indexable; WordPress core (or the SEO plugin) adds the robots directive, this
 * template deliberately adds none.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

get_header();

global $wp_query;
$nh_editorial_phrase = nh_editorial_search_phrase();
$nh_editorial_hubs   = nh_editorial_get_hubs( 8 );
$nh_editorial_active = isset( $_GET['cat'] ) ? absint( wp_unslash( $_GET['cat'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter, no state change.

$nh_editorial_matching = array();
if ( mb_strlen( $nh_editorial_phrase ) >= 2 ) {
	$nh_editorial_terms = get_terms(
		array(
			'taxonomy'   => 'category',
			'name__like' => $nh_editorial_phrase,
			'number'     => 6,
			'hide_empty' => true,
		)
	);
	if ( ! is_wp_error( $nh_editorial_terms ) ) {
		foreach ( $nh_editorial_terms as $nh_editorial_term ) {
			$nh_editorial_link = get_term_link( $nh_editorial_term );
			if ( ! is_wp_error( $nh_editorial_link ) ) {
				$nh_editorial_matching[] = array(
					'name' => $nh_editorial_term->name,
					'url'  => $nh_editorial_link,
				);
			}
		}
	}
}
?>
<div class="wrap inner-page">
	<header class="archive-header archive-header--search">
		<?php nh_editorial_breadcrumbs(); ?>
		<p class="eyebrow"><?php esc_html_e( 'Search results', 'nguyen-ha-editorial' ); ?></p>
		<h1 class="archive-title">
			<?php
			/* translators: %s: search phrase. */
			echo esc_html( sprintf( __( 'Results for “%s”', 'nguyen-ha-editorial' ), $nh_editorial_phrase ) );
			?>
		</h1>
		<p class="archive-meta" role="status">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: number of results. */
					_n( '%s result', '%s results', (int) $wp_query->found_posts, 'nguyen-ha-editorial' ),
					number_format_i18n( (int) $wp_query->found_posts )
				)
			);
			?>
		</p>
		<?php get_search_form( array( 'aria_label' => __( 'Refine your search', 'nguyen-ha-editorial' ) ) ); ?>

		<?php if ( function_exists( 'nh_seo_search_was_relaxed' ) && nh_seo_search_was_relaxed() ) : ?>
			<p class="notice"><?php esc_html_e( 'No exact match for all words, showing the closest results.', 'nguyen-ha-editorial' ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $nh_editorial_matching ) ) : ?>
			<p class="archive-label"><?php esc_html_e( 'Matching topics', 'nguyen-ha-editorial' ); ?></p>
			<ul class="chips">
				<?php foreach ( $nh_editorial_matching as $nh_editorial_topic ) : ?>
					<li><a class="chip" href="<?php echo esc_url( $nh_editorial_topic['url'] ); ?>"><?php echo esc_html( $nh_editorial_topic['name'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( ! empty( $nh_editorial_hubs ) && have_posts() ) : ?>
			<p class="archive-label" id="search-filter-label"><?php esc_html_e( 'Search within a topic', 'nguyen-ha-editorial' ); ?></p>
			<ul class="chips" aria-labelledby="search-filter-label">
				<li>
					<a class="chip<?php echo 0 === $nh_editorial_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 's', rawurlencode( $nh_editorial_phrase ), home_url( '/' ) ) ); ?>"<?php echo 0 === $nh_editorial_active ? ' aria-current="true"' : ''; ?>><?php esc_html_e( 'All topics', 'nguyen-ha-editorial' ); ?></a>
				</li>
				<?php foreach ( $nh_editorial_hubs as $nh_editorial_hub ) : ?>
					<li>
						<a class="chip<?php echo $nh_editorial_active === $nh_editorial_hub['id'] ? ' is-active' : ''; ?>" href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									's'   => rawurlencode( $nh_editorial_phrase ),
									'cat' => $nh_editorial_hub['id'],
								),
								home_url( '/' )
							)
						);
						?>
										"<?php echo $nh_editorial_active === $nh_editorial_hub['id'] ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $nh_editorial_hub['name'] ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</header>

	<?php
	if ( have_posts() ) {
		get_template_part(
			'template-parts/components/post-grid',
			null,
			array(
				'heading' => 'h2',
				'phrase'  => $nh_editorial_phrase,
			)
		);
	} else {
		get_template_part(
			'template-parts/components/empty-state',
			null,
			array(
				'title'  => __( 'No matching articles', 'nguyen-ha-editorial' ),
				'text'   => __( 'Try different or fewer words, check the spelling, or start from one of the topics below.', 'nguyen-ha-editorial' ),
				'search' => false,
			)
		);
	}
	?>
</div>
<?php
get_footer();
