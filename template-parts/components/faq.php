<?php
/**
 * Article FAQ as native, keyboard-accessible disclosure widgets. Answers are filtered again on output.
 *
 * The questions are real content on the page, which is what allows the companion plugin (or an SEO
 * plugin) to describe them as FAQPage structured data. The theme itself prints no JSON-LD.
 *
 * Arguments: faq (items from nh_editorial_get_extras()).
 *
 * @package nguyen-ha-editorial
 */

defined( 'ABSPATH' ) || exit;

$nh_editorial_faq = isset( $args['faq'] ) && is_array( $args['faq'] ) ? $args['faq'] : array();
if ( empty( $nh_editorial_faq ) ) {
	return;
}
?>
<section class="faq" id="faq" aria-labelledby="faq-title">
	<h2 id="faq-title" class="faq__title"><?php esc_html_e( 'Frequently asked questions', 'nguyen-ha-editorial' ); ?></h2>
	<div class="faq__list">
		<?php foreach ( $nh_editorial_faq as $nh_editorial_index => $nh_editorial_entry ) : ?>
			<details class="faq__item"<?php echo 0 === $nh_editorial_index ? ' open' : ''; ?>>
				<summary class="faq__summary">
					<h3 class="faq__question"><?php echo esc_html( $nh_editorial_entry['question'] ); ?></h3>
					<?php echo nh_editorial_icon( 'chevron-down', 'faq__chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?>
				</summary>
				<div class="faq__answer prose"><?php echo wp_kses( $nh_editorial_entry['answer'], nh_editorial_faq_allowed_html(), array( 'http', 'https' ) ); ?></div>
			</details>
		<?php endforeach; ?>
	</div>
</section>
