<?php
/**
 * Output channel of the WP-CLI commands.
 *
 * The commands talk to this interface instead of calling WP_CLI directly. WP-CLI gets the real adapter
 * (NH_SEO_CLI_Output_WPCLI); tests and other tools pass their own, which is why the command class can be used
 * and tested without WP-CLI being loaded.
 *
 * @package nguyen-ha-seo-companion
 */

defined( 'ABSPATH' ) || exit;

/**
 * Output channel.
 */
interface NH_SEO_CLI_Output {

	/**
	 * Print a line.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public function log( $message );

	/**
	 * Print a success message.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public function success( $message );

	/**
	 * Print a warning.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public function warning( $message );

	/**
	 * Print an error and stop the command. Must not return.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public function error( $message );

	/**
	 * Print rows in a format (table, json, csv, yaml).
	 *
	 * @param string                           $format Format.
	 * @param array<int, array<string, mixed>> $rows   Rows.
	 * @param string[]                         $fields Columns.
	 * @return void
	 */
	public function table( $format, array $rows, array $fields );

	/**
	 * Start a progress bar.
	 *
	 * @param string $label Label.
	 * @param int    $total Number of steps.
	 * @return void
	 */
	public function progress_start( $label, $total );

	/**
	 * Advance the progress bar.
	 *
	 * @param int $count Steps done.
	 * @return void
	 */
	public function progress_tick( $count );

	/**
	 * Finish the progress bar.
	 *
	 * @return void
	 */
	public function progress_finish();
}
