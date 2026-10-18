<?php
/**
 * "Browse by topic": the Hub cards (top-level categories, with Pillar links when mapped).
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

$nh_editorial_hubs = nh_editorial_get_hubs( 8 );
if ( empty( $nh_editorial_hubs ) ) {
	return;
}
?>
<section class="section section--hubs" id="topics" aria-labelledby="topics-title">
	<div class="wrap">
		<div class="section-head">
			<div class="section-head__text">
				<h2 id="topics-title" class="section-head__title"><?php esc_html_e( 'Browse by topic', 'nguyen-ha-editorial' ); ?></h2>
				<p class="section-head__desc"><?php esc_html_e( 'Start from a topic and follow the guides inside it.', 'nguyen-ha-editorial' ); ?></p>
			</div>
		</div>
		<?php
		get_template_part(
			'template-parts/components/hubs-grid',
			null,
			array(
				'hubs'    => $nh_editorial_hubs,
				'heading' => 'h3',
			)
		);
		?>
	</div>
</section>


<?php
/**
 * Direct paths from the homepage to real, published service pages.
 *
 * Keep the editorial category hubs above intact. Resolve permalink dynamically,
 * and never show a link to an unpublished or deleted WordPress page.
 */
$nh_editorial_service_links = array(
	array( 'slug' => 'thue-xe-may-ha-noi', 'title' => 'Xe số và xe ga', 'description' => 'Tìm hiểu dịch vụ thuê xe số và xe ga tại Hà Nội.' ),
	array( 'slug' => 'xe-50cc', 'title' => 'Xe máy 50cc', 'description' => 'Tham khảo lựa chọn xe 50cc và những điều cần biết trước khi thuê.' ),
	array( 'slug' => 'xe-dien', 'title' => 'Xe điện', 'description' => 'Tìm hiểu dịch vụ thuê xe điện và các lưu ý khi sử dụng.' ),
	array( 'slug' => 'bang-gia', 'title' => 'Bảng giá thuê xe', 'description' => 'Xem bảng giá và thông tin thuê xe được công bố trên website.' ),
);
$nh_editorial_published_services = array();

foreach ( $nh_editorial_service_links as $nh_editorial_service ) {
	$nh_editorial_page = get_page_by_path( $nh_editorial_service['slug'], OBJECT, 'page' );
	if ( $nh_editorial_page instanceof WP_Post && 'publish' === get_post_status( $nh_editorial_page ) ) {
		$nh_editorial_service['url'] = get_permalink( $nh_editorial_page );
		$nh_editorial_published_services[] = $nh_editorial_service;
	}
}
if ( ! empty( $nh_editorial_published_services ) ) :
	?>
	<section class="section section--home-services" id="services" aria-labelledby="home-services-title">
		<div class="wrap">
			<div class="section-head">
				<div class="section-head__text">
					<h2 class="section-head__title" id="home-services-title">Dịch vụ thuê xe tại Hà Nội</h2>
					<p class="section-head__desc">Chọn loại xe phù hợp hoặc xem bảng giá trước khi đặt thuê.</p>
				</div>
			</div>
			<div class="grid grid--hubs">
				<?php foreach ( $nh_editorial_published_services as $nh_editorial_service ) : ?>
					<article class="card card--hub">
						<div class="card__body">
							<h3 class="card__title"><a href="<?php echo esc_url( $nh_editorial_service['url'] ); ?>"><?php echo esc_html( $nh_editorial_service['title'] ); ?></a></h3>
							<p class="card__excerpt"><?php echo esc_html( $nh_editorial_service['description'] ); ?></p>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>
