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
		Promokodiki_Filter_Test_Harness::assert_same( 1, $like['likes'] );
		Promokodiki_Filter_Test_Harness::assert_same( 0, $like['dislikes'] );
		Promokodiki_Filter_Test_Harness::assert_same( 1, $like['total'] );
		Promokodiki_Filter_Test_Harness::assert_same( 1, (int) get_post_meta( $post_id, '_promocode_votes_total', true ) );
		$dislike = Promokodiki_Filter_Promo_Interactions::vote( $post_id, 'fixture-voter', 'dislike' );
		Promokodiki_Filter_Test_Harness::assert_same( 0, $dislike['likes'] );
		Promokodiki_Filter_Test_Harness::assert_same( 1, $dislike['dislikes'] );
		Promokodiki_Filter_Test_Harness::assert_same( 1, $dislike['total'] );
		Promokodiki_Filter_Test_Harness::assert_same( 1, (int) get_post_meta( $post_id, '_promocode_votes_total', true ) );
	} );
	Promokodiki_Filter_Test_Harness::run( 'new vote persists total reconstructed from legacy counters', static function (): void {
		$legacy_post_id = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'publish', 'post_title' => 'Legacy interaction fixture' ) );
		update_post_meta( $legacy_post_id, '_promocode_likes', 4 );
		update_post_meta( $legacy_post_id, '_promocode_dislikes', 2 );

		try {
			$result = Promokodiki_Filter_Promo_Interactions::vote( $legacy_post_id, 'legacy-fixture-voter', 'like' );
			Promokodiki_Filter_Test_Harness::assert_same( 5, $result['likes'] );
			Promokodiki_Filter_Test_Harness::assert_same( 2, $result['dislikes'] );
			Promokodiki_Filter_Test_Harness::assert_same( 7, $result['total'] );
			Promokodiki_Filter_Test_Harness::assert_same( 7, (int) get_post_meta( $legacy_post_id, '_promocode_votes_total', true ) );
		} finally {
			global $wpdb;
			$wpdb->delete( $wpdb->prefix . 'promokodiki_promo_votes', array( 'promocode_id' => $legacy_post_id ), array( '%d' ) );
			wp_delete_post( $legacy_post_id, true );
		}
	} );
} finally {
	global $wpdb;
	$wpdb->delete( $wpdb->prefix . 'promokodiki_promo_usage', array( 'promocode_id' => $post_id ), array( '%d' ) );
	$wpdb->delete( $wpdb->prefix . 'promokodiki_promo_votes', array( 'promocode_id' => $post_id ), array( '%d' ) );
	$wpdb->delete( $wpdb->prefix . 'promokodiki_click_stats', array( 'promocode_id' => $post_id ), array( '%d' ) );
	wp_delete_post( $post_id, true );
}
Promokodiki_Filter_Test_Harness::finish();
