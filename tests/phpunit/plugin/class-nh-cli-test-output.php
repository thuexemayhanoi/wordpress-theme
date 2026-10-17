<?php
/**
 * Test double for the WP-CLI output channel of the companion plugin's commands.
 *
 * It records everything the commands print, and error() throws like WP_CLI::error() halts a real command.
 * WP-CLI itself is NOT loaded by these tests, and the real adapter (NH_SEO_CLI_Output_WPCLI, one line per
 * method) is not exercised by them.
 *
 * @package nguyen-ha-seo-companion
 */

/**
 * Recording output channel.
 */
class NH_CLI_Test_Output implements NH_SEO_CLI_Output {

	/**
	 * Everything printed, in order, as array( type, message ).
	 *
	 * @var array<int, array{0: string, 1: string}>
	 */
	public $lines = array();

	/**
	 * Tables printed as array( format, rows, fields ).
	 *
	 * @var array<int, array>
	 */
	public $tables = array();

	/**
	 * Progress bar events as array( event, value ).
	 *
	 * @var array<int, array>
	 */
	public $progress = array();

	/**
	 * Print a line.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public function log( $message ) {
		$this->lines[] = array( 'log', $message );
	}

	/**
	 * Print a success message.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public function success( $message ) {
		$this->lines[] = array( 'success', $message );
	}

	/**
	 * Print a warning.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public function warning( $message ) {
		$this->lines[] = array( 'warning', $message );
	}

	/**
	 * Print an error and halt, like WP_CLI::error().
	 *
	 * @param string $message Message.
	 * @return void
	 * @throws RuntimeException Always.
	 */
	public function error( $message ) {
		$this->lines[] = array( 'error', $message );
		throw new RuntimeException( $message );
	}

	/**
	 * Record a table.
	 *
	 * @param string                           $format Format.
	 * @param array<int, array<string, mixed>> $rows   Rows.
	 * @param string[]                         $fields Columns.
	 * @return void
	 */
	public function table( $format, array $rows, array $fields ) {
		$this->tables[] = array( $format, $rows, $fields );
	}

	/**
	 * Record the start of a progress bar.
	 *
	 * @param string $label Label.
	 * @param int    $total Number of steps.
	 * @return void
	 */
	public function progress_start( $label, $total ) {
		$this->progress[] = array( 'start', $total );
	}

	/**
	 * Record a progress step.
	 *
	 * @param int $count Steps.
	 * @return void
	 */
	public function progress_tick( $count ) {
		$this->progress[] = array( 'tick', $count );
	}

	/**
	 * Record the end of a progress bar.
	 *
	 * @return void
	 */
	public function progress_finish() {
		$this->progress[] = array( 'finish', 0 );
	}

	/**
	 * Messages of one type.
	 *
	 * @param string $type log | success | warning | error.
	 * @return string[]
	 */
	public function messages( $type ) {
		$out = array();
		foreach ( $this->lines as $line ) {
			if ( $type === $line[0] ) {
				$out[] = $line[1];
			}
		}
		return $out;
	}

	/**
	 * Sum of the progress ticks.
	 *
	 * @return int
	 */
	public function ticks() {
		$sum = 0;
		foreach ( $this->progress as $event ) {
			if ( 'tick' === $event[0] ) {
				$sum += (int) $event[1];
			}
		}
		return $sum;
	}
}
