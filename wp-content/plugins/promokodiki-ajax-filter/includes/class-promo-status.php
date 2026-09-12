<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Promokodiki_Filter_Promo_Status {
	private const LISTING_GRACE_DAYS = 7;

	private static function expiry_date( int $post_id ): string {
		$expiry = trim( (string) get_post_meta( $post_id, '_promocode_expiry_date', true ) );
		if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2})/', $expiry, $matches ) ) {
			return '';
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $matches[1], wp_timezone() );
		return $date && $date->format( 'Y-m-d' ) === $matches[1] ? $matches[1] : '';
	}

	public static function expiry_state( int $post_id ): string {
		$expiry = self::expiry_date( $post_id );
		$today  = current_time( 'Y-m-d' );
		if ( '' === $expiry ) { return 'undated'; }
		if ( $expiry >= $today ) { return 'active'; }
		$cutoff = wp_date( 'Y-m-d', current_datetime()->modify( '-' . self::LISTING_GRACE_DAYS . ' days' )->getTimestamp() );
		return $expiry >= $cutoff ? 'grace' : 'hidden';
	}

	public static function is_listing_visible( int $post_id ): bool {
		return 'hidden' !== self::expiry_state( $post_id );
	}

	public static function is_recommendable( int $post_id ): bool {
		return in_array( self::expiry_state( $post_id ), array( 'active', 'undated' ), true );
	}

	public static function expiry_label( int $post_id ): string {
		$expiry = self::expiry_date( $post_id );
		if ( '' === $expiry ) { return 'Срок не указан'; }
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $expiry, wp_timezone() );
		return $date ? $date->format( 'd.m.Y' ) : 'Срок не указан';
	}

	public static function for_post( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post || 'promocode' !== $post->post_type ) { return ''; }
		$settings = Promokodiki_Filter_Settings::get();
		if ( in_array( self::expiry_state( $post_id ), array( 'grace', 'hidden' ), true ) ) { return 'expired'; }
		if ( get_post_timestamp( $post ) >= current_time( 'timestamp' ) - ( $settings['new_days'] * DAY_IN_SECONDS ) ) { return 'new'; }
		if ( Promokodiki_Filter_Click_Stats::count_for_post( $post_id, $settings['popular_days'] ) >= $settings['popular_min_clicks'] ) { return 'popular'; }
		return '';
	}
}
