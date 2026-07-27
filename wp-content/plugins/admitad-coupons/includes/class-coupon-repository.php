<?php
/**
 * Hash-aware coupon persistence.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists normalized Admitad coupons without overwriting editorial content.
 */
final class Promokodiki_Admitad_Coupon_Repository {
	/**
	 * Duplicate detector.
	 *
	 * @var Promokodiki_Admitad_Duplicate_Detector
	 */
	private Promokodiki_Admitad_Duplicate_Detector $duplicates;

	/**
	 * Review queue.
	 *
	 * @var Promokodiki_Admitad_Review_Queue_Repository
	 */
	private Promokodiki_Admitad_Review_Queue_Repository $queue;

	/**
	 * Constructor.
	 *
	 * @param Promokodiki_Admitad_Duplicate_Detector|null      $duplicates Duplicate detector.
	 * @param Promokodiki_Admitad_Review_Queue_Repository|null $queue      Review queue.
	 */
	public function __construct( ?Promokodiki_Admitad_Duplicate_Detector $duplicates = null, ?Promokodiki_Admitad_Review_Queue_Repository $queue = null ) {
		$this->duplicates = $duplicates ?? new Promokodiki_Admitad_Duplicate_Detector();
		$this->queue      = $queue ?? new Promokodiki_Admitad_Review_Queue_Repository();
	}

	/**
	 * Create or update one normalized coupon.
	 *
	 * @param array<string, mixed> $coupon Normalized coupon.
	 * @param int                  $run_id Current synchronization run.
	 * @return array{post_id:int,state:string}
	 */
	public function upsert( array $coupon, int $run_id ): array {
		if ( ! $this->eligible( $coupon ) ) {
			return array(
				'post_id' => 0,
				'state'   => 'failed',
			);
		}

		$post_id = $this->find( (string) $coupon['external_id'] );
		if ( 0 !== $post_id && get_post_meta( $post_id, '_admitad_payload_hash', true ) === (string) $coupon['payload_hash'] ) {
			update_post_meta( $post_id, '_admitad_last_seen_run_id', $run_id );
			return array(
				'post_id' => $post_id,
				'state'   => 'unchanged',
			);
		}

		$is_new        = 0 === $post_id;
		$duplicate_ids = $is_new ? $this->duplicates->find( $coupon ) : array();
		$start         = strtotime( (string) ( $coupon['date_start'] ?? '' ) );
		$post_data     = array(
			'post_type'   => 'promocode',
			'post_status' => $start && $start > time() ? 'future' : 'publish',
			'post_date'   => $start ? wp_date( 'Y-m-d H:i:s', $start ) : current_time( 'mysql' ),
		);
		if ( $is_new || ! Promokodiki_Admitad_Editorial_Locks::content_locked( $post_id ) ) {
			$post_data['post_title']   = (string) $coupon['title'];
			$post_data['post_content'] = (string) $coupon['description'];
			$post_data['post_excerpt'] = (string) $coupon['short_name'];
		}
		if ( ! $is_new ) {
			$post_data['ID'] = $post_id;
		}

		$result = Promokodiki_Admitad_Import_Context::run(
			static fn() => $is_new
				? wp_insert_post( wp_slash( $post_data ), true )
				: wp_update_post( wp_slash( $post_data ), true )
		);
		if ( is_wp_error( $result ) ) {
			return array(
				'post_id' => $post_id,
				'state'   => 'failed',
			);
		}
		$post_id = (int) $result;

		$this->update_meta( $post_id, $coupon, $run_id );
		$this->assign_shop( $post_id, $coupon['campaign'] );
		if ( $duplicate_ids ) {
			$this->queue->enqueue(
				'coupon',
				(string) $coupon['external_id'],
				'suspected_duplicate',
				array(
					'matching_post_ids' => $duplicate_ids,
					'campaign_id'       => (string) ( $coupon['campaign']['id'] ?? '' ),
					'promocode'         => (string) ( $coupon['promocode'] ?? '' ),
					'title'             => (string) ( $coupon['title'] ?? '' ),
					'date_start'        => (string) ( $coupon['date_start'] ?? '' ),
					'date_end'          => (string) ( $coupon['date_end'] ?? '' ),
				)
			);
		}

		return array(
			'post_id' => $post_id,
			'state'   => $is_new ? 'created' : 'updated',
		);
	}

	/**
	 * Check whether a normalized coupon may be stored.
	 *
	 * @param array<string, mixed> $coupon Coupon.
	 */
	private function eligible( array $coupon ): bool {
		$species = (string) ( $coupon['species'] ?? '' );
		$regions = (array) ( $coupon['regions'] ?? array() );
		return '' !== (string) ( $coupon['external_id'] ?? '' )
			&& in_array( $species, array( 'promocode', 'action' ), true )
			&& ( ! $regions || in_array( 'ru', $regions, true ) )
			&& ! empty( $coupon['has_affiliate_link'] )
			&& '' !== (string) ( $coupon['goto_link'] ?? '' );
	}

	/**
	 * Find an existing imported coupon.
	 *
	 * @param string $external_id Admitad coupon ID.
	 */
	private function find( string $external_id ): int {
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Admitad ID is the canonical legacy lookup key.
		$posts = get_posts(
			array(
				'post_type'                    => 'promocode',
				'post_status'                  => 'any',
				'fields'                       => 'ids',
				'posts_per_page'               => 1,
				'promokodiki_include_inactive' => true,
				'meta_key'                     => 'admitad_coupon_id',
				'meta_value'                   => $external_id,
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		return $posts ? (int) $posts[0] : 0;
	}

	/**
	 * Update source-owned metadata.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $coupon  Coupon.
	 * @param int                  $run_id  Run ID.
	 */
	private function update_meta( int $post_id, array $coupon, int $run_id ): void {
		$campaign = (array) $coupon['campaign'];
		$meta     = array(
			'admitad_coupon_id'            => (string) $coupon['external_id'],
			'_admitad_payload_hash'        => (string) $coupon['payload_hash'],
			'_admitad_last_seen_run_id'    => $run_id,
			'_admitad_source_status'       => (string) $coupon['source_status'],
			'_admitad_original_categories' => wp_json_encode( $coupon['categories'], JSON_UNESCAPED_UNICODE ),
			'_promocode_code'              => (string) $coupon['promocode'],
			'_promocode_link'              => (string) $coupon['goto_link'],
			'_promocode_expiry_date'       => (string) $coupon['date_end'],
			'_promocode_is_active'         => 'active' === $coupon['source_status'] ? 'yes' : 'no',
			'_promocode_is_verified'       => 'yes',
			'campaign_id'                  => (string) ( $campaign['id'] ?? '' ),
			'campaign_name'                => (string) ( $campaign['name'] ?? '' ),
			'discount'                     => (string) $coupon['discount'],
			'species'                      => (string) $coupon['species'],
			'frameset_link'                => (string) $coupon['frameset_link'],
			'goto_link'                    => (string) $coupon['goto_link'],
			'date_start'                   => (string) $coupon['date_start'],
			'date_end'                     => (string) $coupon['date_end'],
			'image_url'                    => (string) $coupon['image_url'],
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Assign a campaign shop term.
	 *
	 * @param int                  $post_id  Post ID.
	 * @param array<string, mixed> $campaign Campaign.
	 */
	private function assign_shop( int $post_id, array $campaign ): void {
		$term_id = admitad_find_or_create_shop( $campaign );
		if ( $term_id ) {
			wp_set_post_terms( $post_id, array( $term_id ), 'shops_category', false );
		}
	}
}
