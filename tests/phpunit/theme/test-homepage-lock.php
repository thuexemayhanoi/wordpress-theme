<?php
/**
 * The homepage of the live blog is protected: its H1 and introduction must not change when the theme does.
 *
 * The approved texts are those of the public baseline kept by the AI Writer repository
 * (data/seo/homepage-protection-baseline-20261017.json): the blog shows its latest posts as the front page
 * and the old theme prints a fixed H1 and lead. This theme takes both from two Customizer settings, which
 * is how the same texts are reproduced; these tests prove that, and that the theme never prints the other
 * protected values (title, description, canonical) itself.
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

/**
 * Homepage headline parity and the reminder notice.
 */
class NH_Test_Homepage_Lock extends NH_Render_Test_Case {

	const H1   = 'Thuê Xe Máy Hà Nội – Bảng Giá, Thủ Tục & Kinh Nghiệm';
	const LEAD = 'Tìm hiểu các dòng xe số, xe ga, xe 50cc và xe điện. Tham khảo giá thuê theo ngày, tuần, tháng cùng hướng dẫn đặt cọc, giấy tờ và nhận xe tại Hà Nội.';

	/**
	 * Latest posts as the front page, the site title and tagline of the live blog, a few articles.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->use_permalinks( '/%postname%/' );
		update_option( 'show_on_front', 'posts' );
		update_option( 'page_on_front', 0 );
		update_option( 'page_for_posts', 0 );
		update_option( 'blogname', 'Thuê xe máy Nguyễn Hà' );
		update_option( 'blogdescription', 'Thuê xe máy, xe 50cc và xe điện tại Hà Nội' );
		$category = self::factory()->category->create( array( 'name' => 'Kinh nghiệm' ) );
		$this->make_posts( $category, 3 );
		nh_editorial_cache_bump_now();
	}

	public function test_without_settings_the_headline_and_lead_are_the_site_title_and_tagline() {
		$html = $this->render_url( home_url( '/' ) );
		$xp   = $this->xpath( $html );
		$this->assertSame( 1, $xp->query( '//h1' )->length );
		$this->assertSame( 'Thuê xe máy Nguyễn Hà', trim( $xp->query( '//h1' )->item( 0 )->textContent ) );
		$this->assertSame( 'Thuê xe máy, xe 50cc và xe điện tại Hà Nội', trim( $xp->query( $this->by_class( 'home-intro__lead', 'p' ) )->item( 0 )->textContent ) );
	}

	public function test_the_two_customizer_settings_reproduce_the_protected_homepage_text_exactly() {
		set_theme_mod( 'nh_home_headline', self::H1 );
		set_theme_mod( 'nh_home_subline', self::LEAD );

		$html = $this->render_url( home_url( '/' ) );
		$xp   = $this->xpath( $html );

		$this->assertSame( 1, $xp->query( '//h1' )->length, 'one H1 on the front page' );
		$this->assertSame( self::H1, trim( $xp->query( '//h1' )->item( 0 )->textContent ) );
		$this->assertSame( self::LEAD, trim( $xp->query( $this->by_class( 'home-intro__lead', 'p' ) )->item( 0 )->textContent ) );
		$this->assertStringContainsString( 'Thủ Tục &amp; Kinh Nghiệm', $html, 'the ampersand is escaped once' );
	}

	public function test_the_values_survive_the_way_the_customizer_saves_them() {
		$sanitized = nh_editorial_sanitize_setting( 'nh_home_headline', self::H1 );
		$this->assertSame( self::H1, $sanitized, 'the sanitizer keeps the en dash, the ampersand and the capitals' );
		$this->assertSame( self::LEAD, nh_editorial_sanitize_setting( 'nh_home_subline', self::LEAD ) );
	}

	public function test_the_theme_never_prints_the_other_protected_values_itself() {
		set_theme_mod( 'nh_home_headline', self::H1 );
		$xp = $this->xpath( $this->render_url( home_url( '/' ) ) );

		$this->assertSame( 0, $xp->query( '//meta[@name="description"]' )->length );
		$this->assertSame( 0, $xp->query( '//meta[starts-with(@property,"og:")]' )->length );
		$this->assertSame( 0, $xp->query( '//script[@type="application/ld+json"]' )->length );
		$this->assertLessThanOrEqual( 1, $xp->query( '//link[@rel="canonical"]' )->length );
		$this->assertSame( 'front-page.php', basename( $this->current_template() ), 'the front page template serves the latest posts' );
	}

	public function test_the_reminder_is_shown_until_a_headline_is_set_and_can_be_dismissed() {
		set_current_screen( 'dashboard' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertTrue( nh_editorial_headline_notice_visible() );

		ob_start();
		nh_editorial_headline_notice();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'autofocus%5Bcontrol%5D=nh_home_headline', $html );

		update_user_meta( get_current_user_id(), 'nh_editorial_headline_notice_dismissed', 1 );
		$this->assertFalse( nh_editorial_headline_notice_visible(), 'dismissed by this user' );
		delete_user_meta( get_current_user_id(), 'nh_editorial_headline_notice_dismissed' );

		set_theme_mod( 'nh_home_headline', self::H1 );
		$this->assertFalse( nh_editorial_headline_notice_visible(), 'nothing to remind once the headline is set' );
		remove_theme_mod( 'nh_home_headline' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertFalse( nh_editorial_headline_notice_visible(), 'only users who may edit theme options see it' );
	}
}
