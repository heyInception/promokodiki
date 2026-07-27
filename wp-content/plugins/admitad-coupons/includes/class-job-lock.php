<?php
/**
 * Heartbeat-based synchronization lock.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prevents overlapping jobs while allowing stale-owner recovery.
 */
final class Promokodiki_Admitad_Job_Lock {
	/**
	 * Clock callback.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @param callable|null $clock Optional Unix timestamp provider.
	 */
	public function __construct( ?callable $clock = null ) {
		$this->clock = null !== $clock ? $clock : 'time';
	}

	/**
	 * Acquire a named lock.
	 *
	 * @param string $job   Job name.
	 * @param string $owner Unique owner token.
	 * @param int    $ttl   Lock lifetime in seconds.
	 */
	public function acquire( string $job, string $owner, int $ttl ): bool {
		$key     = $this->key( $job );
		$now     = $this->now();
		$current = get_option( $key, array() );

		if ( is_array( $current ) && $current ) {
			$heartbeat   = (int) ( $current['heartbeat'] ?? 0 );
			$current_ttl = max( 1, (int) ( $current['ttl'] ?? $ttl ) );
			if ( $heartbeat + $current_ttl >= $now ) {
				return false;
			}
			delete_option( $key );
		}

		return add_option(
			$key,
			array(
				'owner'     => sanitize_text_field( $owner ),
				'acquired'  => $now,
				'heartbeat' => $now,
				'ttl'       => max( 1, $ttl ),
			),
			'',
			false
		);
	}

	/**
	 * Refresh a lock owned by the caller.
	 *
	 * @param string $job   Job name.
	 * @param string $owner Owner token.
	 */
	public function refresh( string $job, string $owner ): bool {
		$key     = $this->key( $job );
		$current = get_option( $key, array() );
		if ( ! is_array( $current ) || sanitize_text_field( $owner ) !== ( $current['owner'] ?? '' ) ) {
			return false;
		}

		$current['heartbeat'] = $this->now();
		return update_option( $key, $current, false );
	}

	/**
	 * Release a lock owned by the caller.
	 *
	 * @param string $job   Job name.
	 * @param string $owner Owner token.
	 */
	public function release( string $job, string $owner ): bool {
		$key     = $this->key( $job );
		$current = get_option( $key, array() );
		if ( ! is_array( $current ) || sanitize_text_field( $owner ) !== ( $current['owner'] ?? '' ) ) {
			return false;
		}

		return delete_option( $key );
	}

	/**
	 * Return lock health without exposing the owner token.
	 *
	 * @param string $job Job name.
	 * @return array{locked:bool,heartbeat:int,ttl:int,expired:bool}
	 */
	public function status( string $job ): array {
		$current   = get_option( $this->key( $job ), array() );
		$heartbeat = is_array( $current ) ? (int) ( $current['heartbeat'] ?? 0 ) : 0;
		$ttl       = is_array( $current ) ? max( 1, (int) ( $current['ttl'] ?? 600 ) ) : 0;
		$locked    = is_array( $current ) && ! empty( $current );
		return array(
			'locked'    => $locked,
			'heartbeat' => $heartbeat,
			'ttl'       => $ttl,
			'expired'   => $locked && $heartbeat + $ttl < $this->now(),
		);
	}

	/**
	 * Remove only an expired lock.
	 *
	 * @param string $job Job name.
	 */
	public function recover_stale( string $job ): bool {
		$status = $this->status( $job );
		return $status['expired'] ? delete_option( $this->key( $job ) ) : false;
	}

	/**
	 * Return a safe option key.
	 *
	 * @param string $job Job name.
	 */
	private function key( string $job ): string {
		return 'promokodiki_admitad_lock_' . sanitize_key( $job );
	}

	/**
	 * Read the current Unix timestamp.
	 */
	private function now(): int {
		return (int) call_user_func( $this->clock );
	}
}
