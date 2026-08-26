<?php
/** Click statistics integration tests. */

require_once dirname( __DIR__ ) . '/harness.php';

$post_ids = array();

try {
	$published_id = wp_insert_post(
		array(
			'post_type'   => 'promocode',
			'post_status' => 'publish',
			'post_title'  => 'PAF click fixture published',
		)
	);
	$draft_id = wp_insert_post(
		array(
			'post_type'   => 'promocode',
			'post_status' => 'draft',
			'post_title'  => 'PAF click fixture draft',
		)
	);
	$undated_id = wp_insert_post(
		array(
			'post_type'   => 'promocode',
			'post_status' => 'publish',
			'post_title'  => 'PAF click fixture undated',
		)
	);
	$expired_id = wp_insert_post(
		array(
			'post_type'   => 'promocode',
			'post_status' => 'publish',
			'post_title'  => 'PAF click fixture expired',
		)
	);
	$inactive_id = wp_insert_post(
		array(
			'post_type'   => 'promocode',
			'post_status' => 'publish',
			'post_title'  => 'PAF click fixture inactive',
		)
	);
	$post_ids = array( $published_id, $draft_id, $undated_id, $expired_id, $inactive_id );
	update_post_meta( $published_id, '_promocode_used_count', 12 );
	update_post_meta( $expired_id, '_promocode_expiry_date', wp_date( 'Y-m-d', current_time( 'timestamp' ) - DAY_IN_SECONDS ) );
	update_post_meta( $inactive_id, '_promocode_is_active', 'no' );

	Promokodiki_Filter_Test_Harness::run(
		'each click increments daily and lifetime totals',
		static function () use ( $published_id ): void {
			global $wpdb;

			Promokodiki_Filter_Test_Harness::assert_same( 13, Promokodiki_Filter_Click_Stats::increment( $published_id ) );
			Promokodiki_Filter_Test_Harness::assert_same( 14, Promokodiki_Filter_Click_Stats::increment( $published_id ) );
			Promokodiki_Filter_Test_Harness::assert_same( '14', get_post_meta( $published_id, '_promocode_used_count', true ) );
			Promokodiki_Filter_Test_Harness::assert_same(
				'2',
				$wpdb->get_var(
					$wpdb->prepare(
						"SELECT clicks FROM {$wpdb->prefix}promokodiki_click_stats WHERE promocode_id = %d AND click_date = %s",
						$published_id,
						current_time( 'Y-m-d' )
					)
				)
			);
			Promokodiki_Filter_Test_Harness::assert_true(
				in_array( $published_id, Promokodiki_Filter_Click_Stats::ranked_ids( 7, 100, 0, false ), true )
			);
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'clicks reject unpublished promocodes',
		static function () use ( $draft_id ): void {
			Promokodiki_Filter_Test_Harness::assert_true(
				is_wp_error( Promokodiki_Filter_Click_Stats::increment( $draft_id ) )
			);
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'weekly rankings keep undated offers and exclude expired or inactive offers',
		static function () use ( $undated_id, $expired_id, $inactive_id ): void {
			global $wpdb;

			$table = $wpdb->prefix . 'promokodiki_click_stats';
			foreach ( array( $undated_id, $expired_id, $inactive_id ) as $post_id ) {
				$wpdb->insert(
					$table,
					array(
						'promocode_id' => $post_id,
						'click_date'    => current_time( 'Y-m-d' ),
						'clicks'        => 1,
					),
					array( '%d', '%s', '%d' )
				);
			}

			$ranked_ids = Promokodiki_Filter_Click_Stats::ranked_ids( 7, 100, 0, false );
			Promokodiki_Filter_Test_Harness::assert_true( in_array( $undated_id, $ranked_ids, true ) );
			Promokodiki_Filter_Test_Harness::assert_true( ! in_array( $expired_id, $ranked_ids, true ) );
			Promokodiki_Filter_Test_Harness::assert_true( ! in_array( $inactive_id, $ranked_ids, true ) );
		}
	);

	Promokodiki_Filter_Test_Harness::run(
		'public click endpoint hooks are registered',
		static function (): void {
			Promokodiki_Filter_Test_Harness::assert_true(
				false !== has_action( 'wp_ajax_promokodiki_filter_track_click', array( 'Promokodiki_Filter_Ajax_Controller', 'track_click' ) )
			);
			Promokodiki_Filter_Test_Harness::assert_true(
				false !== has_action( 'wp_ajax_nopriv_promokodiki_filter_track_click', array( 'Promokodiki_Filter_Ajax_Controller', 'track_click' ) )
			);
		}
	);

	Promokodiki_Filter_Test_Harness::finish();
} finally {
	global $wpdb;
	foreach ( $post_ids as $post_id ) {
		$wpdb->delete( $wpdb->prefix . 'promokodiki_click_stats', array( 'promocode_id' => $post_id ), array( '%d' ) );
		wp_delete_post( $post_id, true );
	}
}
