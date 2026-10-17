<?php
/**
 * WP-CLI adapter of the command output channel. One line per method, no logic: it only exists when WP-CLI does.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Delegates to WP_CLI.
 */
final class NH_SEO_CLI_Output_WPCLI implements NH_SEO_CLI_Output {

	/**
	 * Progress bar of the running command.
	 *
	 * @var object|null
	 */
	private $bar = null;

	/**
	 * Print a line.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public function log( $message ) {
		WP_CLI::log( $message );
	}

	/**
	 * Print a success message.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public function success( $message ) {
		WP_CLI::success( $message );
	}

	/**
	 * Print a warning.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public function warning( $message ) {
		WP_CLI::warning( $message );
	}

	/**
	 * Print an error and stop the command (WP_CLI::error() exits).
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public function error( $message ) {
		WP_CLI::error( $message );
	}

	/**
	 * Print rows in a format.
	 *
	 * @param string                           $format Format.
	 * @param array<int, array<string, mixed>> $rows   Rows.
	 * @param string[]                         $fields Columns.
	 * @return void
	 */
	public function table( $format, array $rows, array $fields ) {
		\WP_CLI\Utils\format_items( $format, $rows, $fields );
	}

	/**
	 * Start a progress bar.
	 *
	 * @param string $label Label.
	 * @param int    $total Number of steps.
	 * @return void
	 */
	public function progress_start( $label, $total ) {
		$this->bar = \WP_CLI\Utils\make_progress_bar( $label, max( 1, (int) $total ) );
	}

	/**
	 * Advance the progress bar.
	 *
	 * @param int $count Steps done.
	 * @return void
	 */
	public function progress_tick( $count ) {
		if ( $this->bar ) {
			$this->bar->tick( (int) $count );
		}
	}

	/**
	 * Finish the progress bar.
	 *
	 * @return void
	 */
	public function progress_finish() {
		if ( $this->bar ) {
			$this->bar->finish();
			$this->bar = null;
		}
	}
}
