<?php
require_once dirname( __DIR__ ) . '/harness.php';
Promokodiki_Filter_Test_Harness::run( 'interaction service is available', static function (): void {
	Promokodiki_Filter_Test_Harness::assert_true( class_exists( 'Promokodiki_Filter_Promo_Interactions' ) );
} );
$post_id = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'publish', 'post_title' => 'Interaction fixture' ) );
try {
	Promokodiki_Filter_Test_Harness::run( 'usage cooldown prevents duplicate count', static function () use ( $post_id ): void {
		$first = Promokodiki_Filter_Promo_Interactions::record_usage( $post_id, 'fixture-visitor' );
		$second = Promokodiki_Filter_Promo_Interactions::record_usage( $post_id, 'fixture-visitor' );
		Promokodiki_Filter_Test_Harness::assert_true( true === $first['counted'] );
		Promokodiki_Filter_Test_Harness::assert_same( false, $second['counted'] );
	} );
	Promokodiki_Filter_Test_Harness::run( 'visitor can change reaction without adding a second vote', static function () use ( $post_id ): void {
		$like = Promokodiki_Filter_Promo_Interactions::vote( $post_id, 'fixture-voter', 'like' );
		$dislike = Promokodiki_Filter_Promo_Interactions::vote( $post_id, 'fixture-voter', 'dislike' );
		Promokodiki_Filter_Test_Harness::assert_same( 1, $like['likes'] );
		Promokodiki_Filter_Test_Harness::assert_same( 0, $dislike['likes'] );
		Promokodiki_Filter_Test_Harness::assert_same( 1, $dislike['dislikes'] );
	} );
} finally {
	global $wpdb;
	$wpdb->delete( $wpdb->prefix . 'promokodiki_promo_usage', array( 'promocode_id' => $post_id ), array( '%d' ) );
	$wpdb->delete( $wpdb->prefix . 'promokodiki_promo_votes', array( 'promocode_id' => $post_id ), array( '%d' ) );
	$wpdb->delete( $wpdb->prefix . 'promokodiki_click_stats', array( 'promocode_id' => $post_id ), array( '%d' ) );
	wp_delete_post( $post_id, true );
}
Promokodiki_Filter_Test_Harness::finish();
