<?php
/**
 * Bounded synchronization log.
 *
 * @package Promokodiki_Telegram
 */

defined( 'ABSPATH' ) || exit;

final class Promokodiki_Telegram_Log {
	private const OPTION = 'promokodiki_telegram_log';
	private const LIMIT  = 100;

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
			'details'   => is_array( $entry['details'] ?? null ) ? $entry['details'] : array(),
		);
		$entries = self::entries();
		array_unshift( $entries, $entry );
		update_option( self::OPTION, array_slice( $entries, 0, self::LIMIT ), false );
	}
}
