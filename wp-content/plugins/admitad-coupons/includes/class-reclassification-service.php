<?php
/**
 * Previewable and reversible bulk reclassification.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores dry-run snapshots before any taxonomy mutation.
 */
final class Promokodiki_Admitad_Reclassification_Service {
	/**
	 * Batch size.
	 */
	private const BATCH_SIZE = 50;

	/**
	 * Classifier callback.
	 *
	 * @var callable
	 */
	private $classifier;

	/**
	 * History repository.
	 *
	 * @var Promokodiki_Admitad_Classification_History_Repository
	 */
	private Promokodiki_Admitad_Classification_History_Repository $history;

	/**
	 * Assignment service.
	 *
	 * @var Promokodiki_Admitad_Assignment_Service
	 */
	private Promokodiki_Admitad_Assignment_Service $assignments;

	/**
	 * Constructor.
	 *
	 * @param callable|null                                              $classifier  Classifier callback.
	 * @param Promokodiki_Admitad_Classification_History_Repository|null $history     History.
	 * @param Promokodiki_Admitad_Assignment_Service|null                $assignments Assignment service.
	 */
	public function __construct( ?callable $classifier = null, $history = null, $assignments = null ) {
		$engine            = new Promokodiki_Admitad_Classifier();
		$this->classifier  = $classifier ?? static fn( array $coupon, array $context ) => $engine->classify( $coupon, $context );
		$this->history     = $history ?? new Promokodiki_Admitad_Classification_History_Repository();
		$this->assignments = $assignments ?? new Promokodiki_Admitad_Assignment_Service( $this->history );
	}

	/**
	 * Create an immutable affected-only preview.
	 *
	 * @param array<int, int> $post_ids Post IDs.
	 * @return array{id:string,post_ids:array<int,int>,count:int,status:string}
	 */
	public function preview( array $post_ids ): array {
		$snapshot_id = wp_generate_uuid4();
		$affected    = array();
		foreach ( array_values( array_unique( array_map( 'absint', $post_ids ) ) ) as $post_id ) {
			if ( 'promocode' !== get_post_type( $post_id ) || Promokodiki_Admitad_Editorial_Locks::category_locked( $post_id ) ) {
				continue;
			}
			$current_terms   = array_map( 'intval', wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) ) );
			$current_primary = (int) get_post_meta( $post_id, '_admitad_primary_term_id', true );
			$result          = call_user_func( $this->classifier, $this->coupon_from_post( $post_id ), $this->context_for_post( $post_id ) );
			$before          = $current_terms;
			$after           = $result->term_ids();
			sort( $before );
			sort( $after );
			if ( $before === $after && $current_primary === $result->primary_term_id() ) {
				continue;
			}
			$this->history->record( $post_id, $result, $current_terms, $current_primary, 'preview', $snapshot_id );
			$affected[] = $post_id;
		}
		update_option(
			$this->status_key( $snapshot_id ),
			array(
				'status'     => 'previewed',
				'created_at' => time(),
			),
			false
		);
		return array(
			'id'       => $snapshot_id,
			'post_ids' => $affected,
			'count'    => count( $affected ),
			'status'   => 'previewed',
		);
	}

	/**
	 * Apply an entire stored preview synchronously.
	 *
	 * @param string $snapshot_id Snapshot ID.
	 */
	public function apply_preview( string $snapshot_id ): int {
		$snapshot = $this->get_snapshot( $snapshot_id );
		if ( ! $snapshot || 'previewed' !== $snapshot['status'] ) {
			return 0;
		}
		$count = $this->apply_rows( $snapshot['rows'] );
		$this->set_status( $snapshot_id, 'applied' );
		return $count;
	}

	/**
	 * Restore exact pre-preview categories.
	 *
	 * @param string $snapshot_id Snapshot ID.
	 */
	public function rollback( string $snapshot_id ): int {
		$snapshot = $this->get_snapshot( $snapshot_id );
		if ( ! $snapshot || 'applied' !== $snapshot['status'] ) {
			return 0;
		}
		$count = 0;
		foreach ( $snapshot['rows'] as $row ) {
			if ( Promokodiki_Admitad_Editorial_Locks::category_locked( (int) $row['post_id'] ) ) {
				continue;
			}
			Promokodiki_Admitad_Import_Context::run(
				static fn() => wp_set_post_terms(
					(int) $row['post_id'],
					array_map( 'intval', $row['previous_terms'] ),
					'promocode_category',
					false
				)
			);
			update_post_meta( (int) $row['post_id'], '_admitad_primary_term_id', (int) $row['previous_primary_term_id'] );
			++$count;
		}
		$this->set_status( $snapshot_id, 'rolled_back' );
		return $count;
	}

	/**
	 * Return stored snapshot details.
	 *
	 * @param string $snapshot_id Snapshot ID.
	 * @return array<string, mixed>|null
	 */
	public function get_snapshot( string $snapshot_id ): ?array {
		$rows  = $this->history->snapshot_rows( $snapshot_id );
		$state = get_option( $this->status_key( $snapshot_id ), array() );
		if ( ! is_array( $state ) || ! isset( $state['status'] ) ) {
			return null;
		}
		return array(
			'id'         => $snapshot_id,
			'status'     => sanitize_key( (string) $state['status'] ),
			'created_at' => (int) ( $state['created_at'] ?? 0 ),
			'post_ids'   => array_map( 'intval', array_column( $rows, 'post_id' ) ),
			'rows'       => $rows,
		);
	}

	/**
	 * Schedule a background apply from a stored snapshot.
	 *
	 * @param string $snapshot_id Snapshot ID.
	 */
	public function schedule_apply( string $snapshot_id ): void {
		if ( $this->get_snapshot( $snapshot_id ) && ! wp_next_scheduled( 'promokodiki_admitad_apply_classification', array( $snapshot_id, 0 ) ) ) {
			wp_schedule_single_event( time() + 1, 'promokodiki_admitad_apply_classification', array( $snapshot_id, 0 ) );
		}
	}

	/**
	 * Process one scheduled apply batch.
	 *
	 * @param string $snapshot_id Snapshot ID.
	 * @param int    $offset      Row offset.
	 */
	public static function handle_apply_batch( string $snapshot_id, int $offset ): void {
		$service  = new self();
		$snapshot = $service->get_snapshot( $snapshot_id );
		if ( ! $snapshot || 'previewed' !== $snapshot['status'] ) {
			return;
		}
		$rows = array_slice( $snapshot['rows'], max( 0, $offset ), self::BATCH_SIZE );
		$service->apply_rows( $rows );
		$next = $offset + count( $rows );
		if ( $next < count( $snapshot['rows'] ) ) {
			wp_schedule_single_event( time() + 1, 'promokodiki_admitad_apply_classification', array( $snapshot_id, $next ) );
			return;
		}
		$service->set_status( $snapshot_id, 'applied' );
	}

	/**
	 * Apply decoded preview rows.
	 *
	 * @param array<int, array<string, mixed>> $rows Snapshot rows.
	 */
	private function apply_rows( array $rows ): int {
		$count = 0;
		foreach ( $rows as $row ) {
			$result = new Promokodiki_Admitad_Classification_Result(
				array_map( 'intval', $row['result_terms'] ),
				(int) $row['result_primary_term_id'],
				(string) $row['confidence'],
				(array) $row['explanation']
			);
			if ( $this->assignments->assign( (int) $row['post_id'], $result, 'preview_apply' ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Build classifier input from canonical imported meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	private function coupon_from_post( int $post_id ): array {
		$categories = get_post_meta( $post_id, '_admitad_original_categories', true );
		if ( is_string( $categories ) ) {
			$decoded    = json_decode( $categories, true );
			$categories = is_array( $decoded ) ? $decoded : array();
		}
		return array(
			'external_id' => (string) get_post_meta( $post_id, 'admitad_coupon_id', true ),
			'title'       => get_the_title( $post_id ),
			'description' => (string) get_post_field( 'post_content', $post_id ),
			'categories'  => is_array( $categories ) ? $categories : array(),
			'campaign'    => array(
				'id'   => (string) get_post_meta( $post_id, 'campaign_id', true ),
				'name' => (string) get_post_meta( $post_id, 'campaign_name', true ),
			),
		);
	}

	/**
	 * Build fallback context without changing taxonomy.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	private function context_for_post( int $post_id ): array {
		$other = get_term_by( 'slug', 'other', 'promocode_category' );
		return array(
			'locked_term_ids'   => (array) get_post_meta( $post_id, '_admitad_locked_term_ids', true ),
			'locked_primary_id' => (int) get_post_meta( $post_id, '_admitad_primary_term_id', true ),
			'other_term_id'     => $other instanceof WP_Term ? (int) $other->term_id : 0,
		);
	}

	/**
	 * Set snapshot status while preserving creation time.
	 *
	 * @param string $snapshot_id Snapshot ID.
	 * @param string $status      Status.
	 */
	private function set_status( string $snapshot_id, string $status ): void {
		$state           = (array) get_option( $this->status_key( $snapshot_id ), array() );
		$state['status'] = sanitize_key( $status );
		update_option( $this->status_key( $snapshot_id ), $state, false );
	}

	/**
	 * Return snapshot status option key.
	 *
	 * @param string $snapshot_id Snapshot ID.
	 */
	private function status_key( string $snapshot_id ): string {
		return 'promokodiki_admitad_snapshot_' . sanitize_key( $snapshot_id );
	}
}
