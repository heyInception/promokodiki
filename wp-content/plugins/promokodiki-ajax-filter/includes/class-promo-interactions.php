<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Promokodiki_Filter_Promo_Interactions {
	public static function vote( int $post_id, string $visitor_id, string $reaction ): array|WP_Error {
		if ( ! in_array( $reaction, array( 'like', 'dislike' ), true ) || 'promocode' !== get_post_type( $post_id ) ) { return new WP_Error( 'invalid_vote', 'Invalid vote.' ); }
		global $wpdb; $table = $wpdb->prefix . 'promokodiki_promo_votes'; $key = hash( 'sha256', wp_salt( 'auth' ) . $visitor_id );
		$old = $wpdb->get_var( $wpdb->prepare( "SELECT reaction FROM {$table} WHERE promocode_id=%d AND visitor_hash=%s", $post_id, $key ) );
		if ( $old !== $reaction ) {
			if ( $old ) { update_post_meta( $post_id, '_promocode_' . $old . 's', max( 0, (int) get_post_meta( $post_id, '_promocode_' . $old . 's', true ) - 1 ) ); }
			update_post_meta( $post_id, '_promocode_' . $reaction . 's', (int) get_post_meta( $post_id, '_promocode_' . $reaction . 's', true ) + 1 );
			$wpdb->replace( $table, array( 'promocode_id'=>$post_id, 'visitor_hash'=>$key, 'reaction'=>$reaction, 'updated_at'=>current_time('mysql') ), array('%d','%s','%s','%s') );
		}
		return array( 'likes'=>(int)get_post_meta($post_id,'_promocode_likes',true), 'dislikes'=>(int)get_post_meta($post_id,'_promocode_dislikes',true), 'reaction'=>$reaction );
	}
	public static function record_usage( int $post_id, string $visitor_id ): array|WP_Error {
		$post = get_post( $post_id );
		if ( ! $post || 'promocode' !== $post->post_type || 'publish' !== $post->post_status ) { return new WP_Error( 'invalid_promocode', 'Invalid promocode.' ); }
		$cooldown = Promokodiki_Filter_Settings::get()['usage_cooldown_hours'];
		$key = hash( 'sha256', wp_salt( 'auth' ) . $visitor_id );
		global $wpdb; $table = $wpdb->prefix . 'promokodiki_promo_usage';
		if ( $cooldown > 0 ) {
			$last = $wpdb->get_var( $wpdb->prepare( "SELECT used_at FROM {$table} WHERE promocode_id=%d AND visitor_hash=%s", $post_id, $key ) );
			if ( $last && strtotime( $last ) > current_time( 'timestamp' ) - $cooldown * HOUR_IN_SECONDS ) return array( 'counted' => false, 'count' => (int) get_post_meta( $post_id, '_promocode_used_count', true ) );
		}
		$wpdb->replace( $table, array( 'promocode_id'=>$post_id, 'visitor_hash'=>$key, 'used_at'=>current_time('mysql') ), array('%d','%s','%s') );
		$count = Promokodiki_Filter_Click_Stats::increment( $post_id );
		if ( is_wp_error( $count ) ) { return $count; }
		return array( 'counted'=>true, 'count'=>$count );
	}
}
