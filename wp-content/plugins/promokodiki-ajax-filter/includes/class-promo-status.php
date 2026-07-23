<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Promokodiki_Filter_Promo_Status {
	public static function for_post( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post || 'promocode' !== $post->post_type ) { return ''; }
		$settings = Promokodiki_Filter_Settings::get();
		$expiry = get_post_meta( $post_id, '_promocode_expiry_date', true );
		if ( '' !== $expiry && $expiry < current_time( 'Y-m-d' ) ) { return 'expired'; }
		if ( get_post_timestamp( $post ) >= current_time( 'timestamp' ) - ( $settings['new_days'] * DAY_IN_SECONDS ) ) { return 'new'; }
		if ( Promokodiki_Filter_Click_Stats::count_for_post( $post_id, $settings['popular_days'] ) >= $settings['popular_min_clicks'] ) { return 'popular'; }
		return '';
	}
}
