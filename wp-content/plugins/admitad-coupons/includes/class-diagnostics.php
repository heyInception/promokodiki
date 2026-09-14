<?php
/**
 * Sanitized Admitad operational diagnostics.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds a secret-free health snapshot for administrators.
 */
final class Promokodiki_Admitad_Diagnostics {
	/**
	 * Build a complete sanitized snapshot.
	 *
	 * @return array<string, mixed>
	 */
	public static function snapshot(): array {
		$lock = new Promokodiki_Admitad_Job_Lock();
		$runs = ( new Promokodiki_Admitad_Sync_Run_Repository() )->recent( 30 );
		$data = array(
			'plugin_version' => ADMITAD_PLUGIN_VERSION,
			'schema_version' => (string) get_option( 'promokodiki_admitad_db_version', '' ),
			'configured'     => array(
				'client_id'     => '' !== (string) Promokodiki_Admitad_Config::get( 'client_id' ),
				'client_secret' => '' !== (string) Promokodiki_Admitad_Config::get( 'client_secret' ),
				'website_id'    => '' !== (string) Promokodiki_Admitad_Config::get( 'website_id' ),
			),
			'cron'           => self::cron(),
			'locks'          => array(
				'coupon'    => $lock->status( 'coupon' ),
				'reference' => $lock->status( 'reference' ),
			),
			'health'         => self::health( $runs ),
			'recent_runs'    => $runs,
			'queue'          => array(
				'low_confidence'      => ( new Promokodiki_Admitad_Review_Queue_Repository() )->count_unresolved( 'low_confidence' ),
				'conflicting_signals' => ( new Promokodiki_Admitad_Review_Queue_Repository() )->count_unresolved( 'conflicting_signals' ),
				'suspected_duplicate' => ( new Promokodiki_Admitad_Review_Queue_Repository() )->count_unresolved( 'suspected_duplicate' ),
			),
			'delayed_jobs'   => ( new Promokodiki_Admitad_Notifier() )->check_delayed_jobs(),
		);
		return self::redact( $data );
	}

	/** @param array<int, array<string, mixed>> $runs @return array<string, array<string, mixed>> */
	private static function health( array $runs ): array {
		$types = array(
			'coupon'    => (int) Promokodiki_Admitad_Config::get( 'coupon_interval' ),
			'reference' => (int) Promokodiki_Admitad_Config::get( 'reference_interval' ),
		);
		$result = array();
		foreach ( $types as $type => $interval ) {
			$matching = array_values( array_filter( $runs, static fn( array $run ): bool => $type === ( $run['job_type'] ?? '' ) ) );
			$successes = array_values( array_filter( $matching, static fn( array $run ): bool => 'completed' === ( $run['status'] ?? '' ) ) );
			$errors    = array_values( array_filter( $matching, static fn( array $run ): bool => 'failed' === ( $run['status'] ?? '' ) ) );
			$success   = $successes[0] ?? false;
			$error     = $errors[0] ?? false;
			$ended_at = is_array( $success ) ? strtotime( (string) ( $success['completed_at'] ?? '' ) . ' UTC' ) : 0;
			$latest   = $matching[0] ?? array();
			$state    = array() === $matching ? 'never_run' : ( 'failed' === ( $latest['status'] ?? '' ) || ! is_array( $success ) ? 'error' : ( $ended_at > 0 && time() - $ended_at > 2 * max( 1, $interval ) ? 'stale' : 'healthy' ) );
			$result[ $type ] = array(
				'state'          => $state,
				'last_success'   => is_array( $success ) ? (string) ( $success['completed_at'] ?? '' ) : '',
				'last_error'     => is_array( $error ) ? (array) ( $error['error_summary'] ?? array() ) : array(),
				'counts'         => array(
					'received'    => (int) ( $latest['processed_count'] ?? 0 ),
					'created'     => (int) ( $latest['created_count'] ?? 0 ),
					'updated'     => (int) ( $latest['updated_count'] ?? 0 ),
					'rejected'    => (int) ( $latest['failed_count'] ?? 0 ),
					'deactivated' => (int) ( $latest['deactivated_count'] ?? 0 ),
				),
				'duration_seconds' => self::duration( $latest ),
				'source'            => 'Admitad',
			);
		}
		return $result;
	}

	/** @param array<string, mixed> $run */
	private static function duration( array $run ): int {
		$started = strtotime( (string) ( $run['started_at'] ?? '' ) . ' UTC' );
		$ended   = strtotime( (string) ( $run['completed_at'] ?? $run['heartbeat_at'] ?? '' ) . ' UTC' );
		return $started > 0 && $ended >= $started ? $ended - $started : 0;
	}

	/**
	 * Return current recurring event details.
	 *
	 * @return array<string, array<string, int|bool>>
	 */
	private static function cron(): array {
		$definitions = array(
			'coupon_sync'    => 'promokodiki_admitad_coupon_sync',
			'reference_sync' => 'promokodiki_admitad_reference_sync',
			'reconcile'      => 'promokodiki_admitad_reconcile',
			'retention'      => 'promokodiki_admitad_retention',
		);
		$cron        = array();
		foreach ( $definitions as $name => $hook ) {
			$event         = wp_get_scheduled_event( $hook );
			$cron[ $name ] = array(
				'scheduled' => is_object( $event ),
				'timestamp' => is_object( $event ) ? (int) $event->timestamp : 0,
				'interval'  => is_object( $event ) ? (int) $event->interval : 0,
			);
		}
		return $cron;
	}

	/**
	 * Recursively redact sensitive keys and authorization-like strings.
	 *
	 * @param mixed  $value Value.
	 * @param string $key   Parent key.
	 * @return mixed
	 */
	private static function redact( $value, string $key = '' ) {
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}
		if ( preg_match( '/token|secret|authorization/i', $key ) ) {
			return '[redacted]';
		}
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $child_key => $child_value ) {
				$clean[ $child_key ] = self::redact( $child_value, (string) $child_key );
			}
			return $clean;
		}
		if ( is_string( $value ) ) {
			return preg_replace( '/(?:Authorization:\s*)?Bearer\s+[^\s",}]+/i', '[redacted]', $value );
		}
		return $value;
	}
}
