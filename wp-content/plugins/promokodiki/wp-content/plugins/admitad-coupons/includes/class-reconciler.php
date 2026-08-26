<?php
/**
 * Completed-run coupon reconciliation.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reconciles source visibility only after a complete coupon traversal.
 */
final class Promokodiki_Admitad_Reconciler {
	/**
	 * Run repository.
	 *
	 * @var Promokodiki_Admitad_Sync_Run_Repository
	 */
	private Promokodiki_Admitad_Sync_Run_Repository $runs;

	/**
	 * Constructor.
	 *
	 * @param Promokodiki_Admitad_Sync_Run_Repository|null $runs Run repository.
	 */
	public function __construct( ?Promokodiki_Admitad_Sync_Run_Repository $runs = null ) {
		$this->runs = $runs ?? new Promokodiki_Admitad_Sync_Run_Repository();
	}

	/**
	 * Reconcile imported coupons against one completed run.
	 *
	 * @param int $run_id Completed coupon run ID.
	 * @return array{deactivated:int,reactivated:int,missed:int}|WP_Error
	 */
	public function after_completed_run( int $run_id ) {
		$run = $this->runs->get( $run_id );
		if ( ! $run || 'coupon' !== $run['job_type'] || 'completed' !== $run['status'] ) {
			return new WP_Error( 'admitad_run_incomplete', 'Only a completed coupon run can be reconciled.' );
		}

		$result   = array(
			'deactivated' => 0,
			'reactivated' => 0,
			'missed'      => 0,
		);
		$post_ids = get_posts(
			array(
				'post_type'                    => 'promocode',
				'post_status'                  => array( 'publish', 'future', 'draft', 'private' ),
				'posts_per_page'               => -1,
				'fields'                       => 'ids',
				'no_found_rows'                => true,
				'update_post_meta_cache'       => false,
				'update_post_term_cache'       => false,
				'promokodiki_include_inactive' => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The indexed post type/status bounds the one-time reconciliation scan to imported coupons.
				'meta_key'                     => 'admitad_coupon_id',
			)
		);
		$threshold = (int) Promokodiki_Admitad_Config::get( 'missing_threshold' );

		foreach ( $post_ids as $post_id ) {
			$last_seen  = (int) get_post_meta( $post_id, '_admitad_last_seen_run_id', true );
			$was_active = 'no' !== get_post_meta( $post_id, '_promocode_is_active', true );
			if ( $run_id === $last_seen ) {
				update_post_meta( $post_id, '_promocode_is_active', 'yes' );
				delete_post_meta( $post_id, '_admitad_miss_count' );
				if ( ! $was_active ) {
					++$result['reactivated'];
				}
				continue;
			}

			$miss_count = (int) get_post_meta( $post_id, '_admitad_miss_count', true ) + 1;
			update_post_meta( $post_id, '_admitad_miss_count', $miss_count );
			++$result['missed'];
			if ( $was_active && $miss_count >= $threshold ) {
				update_post_meta( $post_id, '_promocode_is_active', 'no' );
				++$result['deactivated'];
			}
		}

		$this->runs->set_reconciliation_counts( $run_id, $result['deactivated'], $result['reactivated'] );
		return $result;
	}
}
