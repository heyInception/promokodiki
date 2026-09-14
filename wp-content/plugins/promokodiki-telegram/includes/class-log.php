<?php
/**
 * Bounded synchronization log.
 *
 * @package Promokodiki_Telegram
 */

defined( 'ABSPATH' ) || exit;

final class Promokodiki_Telegram_Log {
	private const OPTION = 'promokodiki_telegram_log';
	private const LIMIT  = 30;
	private const STALE_AFTER = 6 * HOUR_IN_SECONDS;

	/** @return array<int, array<string, mixed>> */
	public static function entries(): array {
		$entries = get_option( self::OPTION, array() );
		return is_array( $entries ) ? array_values( $entries ) : array();
	}

	/** @param array<string, mixed> $entry Log entry. */
	public static function add( array $entry ): void {
		$entry = array(
			'timestamp' => sanitize_text_field( (string) ( $entry['timestamp'] ?? current_time( 'mysql', true ) ) ),
			'channel'   => sanitize_key( (string) ( $entry['channel'] ?? '' ) ),
			'status'    => sanitize_key( (string) ( $entry['status'] ?? 'error' ) ),
			'imported'  => max( 0, (int) ( $entry['imported'] ?? 0 ) ),
			'skipped'   => max( 0, (int) ( $entry['skipped'] ?? 0 ) ),
			'inspected' => max( 0, (int) ( $entry['inspected'] ?? 0 ) ),
			'deactivated' => max( 0, (int) ( $entry['deactivated'] ?? 0 ) ),
			'duration_ms' => max( 0, (int) ( $entry['duration_ms'] ?? 0 ) ),
			'details'   => is_array( $entry['details'] ?? null ) ? $entry['details'] : array(),
		);
		$entries = self::entries();
		array_unshift( $entries, $entry );
		update_option( self::OPTION, array_slice( $entries, 0, self::LIMIT ), false );
	}

	/** @return array<string, mixed> */
	public static function health( ?int $now = null ): array {
		$entries = self::entries();
		$now     = $now ?? time();
		if ( array() === $entries ) {
			return array( 'state' => 'never_run', 'last_success' => '', 'last_error' => array() );
		}

		$last_success = null;
		$last_error   = array();
		foreach ( $entries as $entry ) {
			if ( null === $last_success && 'success' === ( $entry['status'] ?? '' ) ) {
				$last_success = $entry;
			}
			if ( array() === $last_error && 'success' !== ( $entry['status'] ?? '' ) ) {
				$last_error = is_array( $entry['details'] ?? null ) ? $entry['details'] : array();
			}
		}

		$timestamp = null === $last_success ? 0 : strtotime( (string) $last_success['timestamp'] . ' UTC' );
		$state     = null === $last_success ? 'error' : ( $timestamp > 0 && ( $now - $timestamp ) > self::STALE_AFTER ? 'stale' : 'healthy' );
		return array(
			'state'        => $state,
			'last_success' => null === $last_success ? '' : (string) $last_success['timestamp'],
			'last_error'   => $last_error,
			'latest'       => $entries[0],
		);
	}
}
