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
			'recent_runs'    => ( new Promokodiki_Admitad_Sync_Run_Repository() )->recent( 20 ),
			'queue'          => array(
				'low_confidence'      => ( new Promokodiki_Admitad_Review_Queue_Repository() )->count_unresolved( 'low_confidence' ),
				'conflicting_signals' => ( new Promokodiki_Admitad_Review_Queue_Repository() )->count_unresolved( 'conflicting_signals' ),
				'suspected_duplicate' => ( new Promokodiki_Admitad_Review_Queue_Repository() )->count_unresolved( 'suspected_duplicate' ),
			),
			'delayed_jobs'   => ( new Promokodiki_Admitad_Notifier() )->check_delayed_jobs(),
		);
		return self::redact( $data );
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
