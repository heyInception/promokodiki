<?php
/**
 * Plugin configuration stored in bounded WordPress options.
 *
 * @package Promokodiki_Telegram
 */

defined( 'ABSPATH' ) || exit;

final class Promokodiki_Telegram_Config {
	private const SETTINGS_OPTION = 'promokodiki_telegram_settings';
	private const CHANNELS_OPTION = 'promokodiki_telegram_channels';
	private const CATEGORY_SLUG   = 'promokody-iz-telegram';

	/** @return array<string, mixed> */
	public static function settings(): array {
		$settings = get_option( self::SETTINGS_OPTION, array() );
		$settings = is_array( $settings ) ? $settings : array();

		return array(
			'card_count' => self::clamp_card_count( (int) ( $settings['card_count'] ?? 4 ) ),
			'secret'     => sanitize_text_field( (string) ( $settings['secret'] ?? '' ) ),
		);
	}

	/** @param array<string, mixed> $settings Raw settings. */
	public static function save_settings( array $settings ): void {
		$current = self::settings();
		$next    = array(
			'card_count' => self::clamp_card_count( (int) ( $settings['card_count'] ?? $current['card_count'] ) ),
			'secret'     => sanitize_text_field( (string) ( $settings['secret'] ?? $current['secret'] ) ),
		);
		update_option( self::SETTINGS_OPTION, $next, false );
	}

	public static function card_count(): int {
		return (int) self::settings()['card_count'];
	}

	public static function secret(): string {
		return (string) self::settings()['secret'];
	}

	/** @return array<string, array<string, mixed>> */
	public static function channels(): array {
		$channels = get_option( self::CHANNELS_OPTION, array() );
		return is_array( $channels ) ? $channels : array();
	}

	/** @param array<string, array<string, mixed>> $channels Channel rows keyed by username. */
	public static function save_channels( array $channels ): void {
		$clean = array();
		foreach ( $channels as $username => $channel ) {
			$username = self::sanitize_username( (string) $username );
			if ( '' === $username || ! is_array( $channel ) ) {
				continue;
			}
			$clean[ $username ] = array(
				'username'        => $username,
				'enabled'         => ! empty( $channel['enabled'] ),
				'last_message_id' => max( 0, (int) ( $channel['last_message_id'] ?? 0 ) ),
				'last_synced_at'  => sanitize_text_field( (string) ( $channel['last_synced_at'] ?? '' ) ),
				'last_status'     => sanitize_key( (string) ( $channel['last_status'] ?? 'pending' ) ),
				'last_error'      => sanitize_text_field( (string) ( $channel['last_error'] ?? '' ) ),
				'imported_count'  => max( 0, (int) ( $channel['imported_count'] ?? 0 ) ),
				'skipped_count'   => max( 0, (int) ( $channel['skipped_count'] ?? 0 ) ),
			);
		}
		update_option( self::CHANNELS_OPTION, $clean, false );
	}

	public static function category_term_id(): int {
		$term = get_term_by( 'slug', self::CATEGORY_SLUG, 'promocode_category' );
		return $term instanceof WP_Term ? (int) $term->term_id : 0;
	}

	public static function category_slug(): string {
		return self::CATEGORY_SLUG;
	}

	public static function ensure_defaults(): void {
		$settings = self::settings();
		if ( '' === $settings['secret'] ) {
			$settings['secret'] = wp_generate_password( 64, false, false );
		}
		self::save_settings( $settings );

		$channels = self::channels();
		if ( ! isset( $channels['tranzhiraru'] ) ) {
			$channels['tranzhiraru'] = array(
				'username'        => 'tranzhiraru',
				'enabled'         => true,
				'last_message_id' => 0,
				'last_synced_at'  => '',
				'last_status'     => 'pending',
				'last_error'      => '',
				'imported_count'  => 0,
				'skipped_count'   => 0,
			);
		}
		self::save_channels( $channels );
	}

	private static function clamp_card_count( int $count ): int {
		return max( 4, min( 20, $count ) );
	}

	private static function sanitize_username( string $username ): string {
		$username = ltrim( strtolower( trim( $username ) ), '@' );
		return preg_match( '/^[a-z0-9_]{5,32}$/', $username ) ? $username : '';
	}
}
