<?php
/**
 * Deterministic Telegram promocode ranking.
 *
 * @package Promokodiki_Telegram
 */

defined( 'ABSPATH' ) || exit;

final class Promokodiki_Telegram_Ranking {
	public static function score( int $post_id, ?int $now = null ): float {
		$now          = $now ?: time();
		$published_at = strtotime( (string) get_post_meta( $post_id, '_telegram_published_at', true ) );
		$age_hours    = max( 1.0, ( $now - ( $published_at ?: $now ) ) / HOUR_IN_SECONDS );
		$views        = max( 0, (int) get_post_meta( $post_id, '_telegram_views', true ) );
		$discount     = max( 0.0, (float) get_post_meta( $post_id, '_telegram_discount_value', true ) );
		$pinned       = 'yes' === get_post_meta( $post_id, '_telegram_pinned', true ) ? 1000000.0 : 0.0;
		$velocity     = ( $views / $age_hours ) * 100.0;
		$freshness    = max( 0.0, 168.0 - $age_hours ) * 10.0;
		return $pinned + $velocity + $freshness + min( 100.0, $discount ) * 2.0;
	}
}
