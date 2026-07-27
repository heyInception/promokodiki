<?php
/**
 * Synchronization run persistence.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores resumable job state and counters.
 */
final class Promokodiki_Admitad_Sync_Run_Repository {
	/**
	 * Read a run.
	 *
	 * @param int $run_id Run ID.
	 * @return array<string, mixed>|null
	 */
	public function get( int $run_id ): ?array {
		global $wpdb;

		$table = Promokodiki_Admitad_Schema::table( 'sync_run' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The validated table identifier cannot use a value placeholder.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $run_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Start a run.
	 *
	 * @param string $type Job type.
	 * @throws RuntimeException When the run cannot be inserted.
	 */
	public function start( string $type ): int {
		global $wpdb;

		$now   = gmdate( 'Y-m-d H:i:s' );
		$table = Promokodiki_Admitad_Schema::table( 'sync_run' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Durable operational state requires the custom table.
		$result = $wpdb->insert(
			$table,
			array(
				'job_type'      => sanitize_key( $type ),
				'status'        => 'running',
				'started_at'    => $now,
				'heartbeat_at'  => $now,
				'error_summary' => '',
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			throw new RuntimeException( 'Unable to start Admitad synchronization run.' );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Persist a cursor and counters.
	 *
	 * @param int                  $run_id   Run ID.
	 * @param int                  $cursor   Next offset.
	 * @param array<string, mixed> $counters Run counters.
	 */
	public function heartbeat( int $run_id, int $cursor, array $counters ): void {
		$this->update(
			$run_id,
			array_merge(
				$this->counter_fields( $counters ),
				array(
					'cursor_offset' => max( 0, $cursor ),
					'heartbeat_at'  => gmdate( 'Y-m-d H:i:s' ),
				)
			)
		);
	}

	/**
	 * Mark a run completed.
	 *
	 * @param int                  $run_id   Run ID.
	 * @param array<string, mixed> $counters Final counters.
	 */
	public function complete( int $run_id, array $counters ): void {
		$now = gmdate( 'Y-m-d H:i:s' );
		$this->update(
			$run_id,
			array_merge(
				$this->counter_fields( $counters ),
				array(
					'status'       => 'completed',
					'heartbeat_at' => $now,
					'completed_at' => $now,
				)
			)
		);
	}

	/**
	 * Mark a run failed with a sanitized summary.
	 *
	 * @param int      $run_id Run ID.
	 * @param WP_Error $error  Failure.
	 */
	public function fail( int $run_id, WP_Error $error ): void {
		$now     = gmdate( 'Y-m-d H:i:s' );
		$message = sanitize_text_field( $error->get_error_message() );
		$message = preg_replace(
			'/(access_token|client_secret)\s*[:=]\s*[^\s,&]+/i',
			'$1=[redacted]',
			$message
		);
		$summary = array(
			'code'    => sanitize_key( $error->get_error_code() ),
			'message' => $message,
			'status'  => absint( is_array( $error->get_error_data() ) ? ( $error->get_error_data()['status'] ?? 0 ) : 0 ),
		);

		$this->update(
			$run_id,
			array(
				'status'        => 'failed',
				'heartbeat_at'  => $now,
				'completed_at'  => $now,
				'error_summary' => wp_json_encode( $summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			)
		);
	}

	/**
	 * Map public counter names to table columns.
	 *
	 * @param array<string, mixed> $counters Counters.
	 * @return array<string, int>
	 */
	private function counter_fields( array $counters ): array {
		$fields = array();
		foreach ( array( 'processed', 'created', 'updated', 'unchanged', 'failed', 'deactivated', 'reactivated' ) as $name ) {
			if ( array_key_exists( $name, $counters ) ) {
				$fields[ $name . '_count' ] = max( 0, (int) $counters[ $name ] );
			}
		}
		return $fields;
	}

	/**
	 * Update one run.
	 *
	 * @param int                  $run_id Run ID.
	 * @param array<string, mixed> $fields Updated fields.
	 * @throws RuntimeException When the run cannot be updated.
	 */
	private function update( int $run_id, array $fields ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Durable operational state requires the custom table.
		$result = $wpdb->update(
			Promokodiki_Admitad_Schema::table( 'sync_run' ),
			$fields,
			array( 'id' => $run_id )
		);
		if ( false === $result ) {
			throw new RuntimeException( 'Unable to update Admitad synchronization run.' );
		}
	}
}
