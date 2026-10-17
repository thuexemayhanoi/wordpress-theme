<?php
/**
 * Tools panel of the search index: status, build / rebuild with a progress bar, clear.
 *
 * The settings page of the plugin (module admin) fires the action `nh_seo_render_tools_panel` on its Tools
 * tab and shows the one-line filter `nh_seo_search_status` in its Status box. Everything here needs the
 * `manage_options` capability; the two AJAX actions also verify a nonce and accept POST only.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin panel and AJAX endpoints.
 */
final class NH_SEO_Search_Admin {

	/**
	 * Nonce action for the AJAX requests.
	 */
	const NONCE_ACTION = 'nh_seo_search_index';

	/**
	 * Script handle.
	 */
	const HANDLE = 'nh-seo-admin-search-index';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'nh_seo_render_tools_panel', array( __CLASS__, 'render_panel' ) );
		add_filter( 'nh_seo_search_status', array( __CLASS__, 'filter_status' ) );
		add_action( 'wp_ajax_nh_seo_search_build', array( __CLASS__, 'ajax_build' ) );
		add_action( 'wp_ajax_nh_seo_search_clear', array( __CLASS__, 'ajax_clear' ) );
	}

	/**
	 * Filter: nh_seo_search_status. Supplies the one-line status (plain text, escape on output).
	 *
	 * @param mixed $status Status from earlier callbacks.
	 * @return string
	 */
	public static function filter_status( $status ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Filter callback: the incoming value is replaced on purpose.
		return NH_SEO_Search_State::status_line();
	}

	/**
	 * Render the panel. Called on the action nh_seo_render_tools_panel.
	 *
	 * @return void
	 */
	public static function render_panel() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		self::enqueue();
		$state = NH_SEO_Search_State::get();

		?>
		<div class="nh-seo-search-panel" id="nh-seo-search-panel">
			<h2><?php esc_html_e( 'Internal search index', 'nguyen-ha-seo-companion' ); ?></h2>
			<p>
				<?php esc_html_e( 'Builds a table of folded words (accents removed, so "Thuê xe máy" matches "thue xe may") that makes search fast on large sites. Posts, pages and their settings are never changed. Until the index is complete, WordPress search is used.', 'nguyen-ha-seo-companion' ); ?>
			</p>
			<p class="nh-seo-search-status" id="nh-seo-search-status" role="status" aria-live="polite">
				<strong><?php esc_html_e( 'Status:', 'nguyen-ha-seo-companion' ); ?></strong>
				<span id="nh-seo-search-status-text"><?php echo esc_html( NH_SEO_Search_State::status_line() ); ?></span>
			</p>
			<p>
				<progress id="nh-seo-search-progress" max="100" value="<?php echo esc_attr( (string) ( $state['ready'] ? 100 : 0 ) ); ?>" <?php echo ( $state['ready'] || ! $state['building'] ) ? 'hidden' : ''; ?>></progress>
			</p>
			<p class="nh-seo-search-actions">
				<button type="button" class="button button-primary" id="nh-seo-search-build"><?php esc_html_e( 'Build / rebuild index', 'nguyen-ha-seo-companion' ); ?></button>
				<button type="button" class="button" id="nh-seo-search-clear"><?php esc_html_e( 'Clear index', 'nguyen-ha-seo-companion' ); ?></button>
			</p>
			<noscript>
				<p><?php esc_html_e( 'JavaScript is required to build the index from this page. You can also run "wp nh-seo reindex" with WP-CLI.', 'nguyen-ha-seo-companion' ); ?></p>
			</noscript>
		</div>
		<?php
	}

	/**
	 * Enqueue the script and its configuration.
	 *
	 * @return void
	 */
	private static function enqueue() {
		wp_enqueue_script( self::HANDLE, NH_SEO_URL . 'assets/admin-search-index.js', array(), NH_SEO_VERSION, true );

		$config = array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( self::NONCE_ACTION ),
			'buildAction' => 'nh_seo_search_build',
			'clearAction' => 'nh_seo_search_clear',
			'i18n'        => array(
				'starting'     => __( 'Starting…', 'nguyen-ha-seo-companion' ),
				/* translators: 1: posts indexed so far, 2: posts to index, 3: percent. */
				'progress'     => __( 'Indexed %1$s of %2$s posts (%3$s%%).', 'nguyen-ha-seo-companion' ),
				/* translators: %s: number of posts. */
				'done'         => __( 'Done: %s posts indexed. Searches now use the index.', 'nguyen-ha-seo-companion' ),
				'clearing'     => __( 'Clearing…', 'nguyen-ha-seo-companion' ),
				'cleared'      => __( 'The index was cleared. Native search is used.', 'nguyen-ha-seo-companion' ),
				'confirmClear' => __( 'Clear the search index? Searches use native WordPress search until you rebuild it.', 'nguyen-ha-seo-companion' ),
				'retrying'     => __( 'Connection problem, trying again…', 'nguyen-ha-seo-companion' ),
				'failed'       => __( 'The request failed. Nothing was lost: run the build again.', 'nguyen-ha-seo-companion' ),
				'leaveWarning' => __( 'The index is still being built. Leave this page?', 'nguyen-ha-seo-companion' ),
			),
		);
		wp_add_inline_script( self::HANDLE, 'window.nhSeoSearchIndex = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );
	}

	/**
	 * Check the request before doing anything: POST, capability and nonce.
	 *
	 * @return void Dies with a JSON error when the request is not allowed.
	 */
	private static function guard() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' !== $method ) {
			wp_send_json_error( array( 'message' => __( 'Use a POST request.', 'nguyen-ha-seo-companion' ) ), 405 );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'nguyen-ha-seo-companion' ) ), 403 );
		}
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'The security token expired. Reload the page and try again.', 'nguyen-ha-seo-companion' ) ), 403 );
		}
	}

	/**
	 * AJAX: wp_ajax_nh_seo_search_build. Steps: start, then batch with the returned cursor and build token.
	 *
	 * @return void
	 */
	public static function ajax_build() {
		self::guard();

		// The nonce was verified in guard().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$step   = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
		$cursor = isset( $_POST['cursor'] ) ? absint( wp_unslash( $_POST['cursor'] ) ) : 0;
		$build  = isset( $_POST['build'] ) ? sanitize_text_field( wp_unslash( $_POST['build'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( 'start' === $step ) {
			$result = NH_SEO_Search_Builder::start();
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
			}
			wp_send_json_success(
				array(
					'build'   => $result['build'],
					'cursor'  => 0,
					'total'   => $result['total'],
					'indexed' => 0,
					'done'    => false,
					'percent' => 0,
				)
			);
		}

		if ( 'batch' === $step ) {
			$result = NH_SEO_Search_Builder::run_batch( $cursor, 0, $build );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), 409 );
			}
			$result['build'] = $build;
			wp_send_json_success( $result );
		}

		wp_send_json_error( array( 'message' => __( 'Unknown step.', 'nguyen-ha-seo-companion' ) ), 400 );
	}

	/**
	 * AJAX: wp_ajax_nh_seo_search_clear.
	 *
	 * @return void
	 */
	public static function ajax_clear() {
		self::guard();
		NH_SEO_Search_Builder::clear();
		wp_send_json_success( array( 'status' => NH_SEO_Search_State::status_line() ) );
	}
}
