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
		$started = $this->start_preview( $post_ids );
		while ( 'previewing' === $this->preview_progress( $started['id'] )['status'] ) { $this->preview_next_batch( $started['id'] ); }
		$snapshot = $this->get_snapshot( $started['id'] );
		return array( 'id' => $started['id'], 'post_ids' => $snapshot['post_ids'], 'count' => count( $snapshot['post_ids'] ), 'status' => $snapshot['status'] );
	}

	/** Create a durable preview cursor without changing taxonomy. */
	public function start_preview( array $post_ids = array() ): array {
		if ( array() === $post_ids ) {
			$post_ids = get_posts( array( 'post_type' => 'promocode', 'post_status' => array( 'publish', 'future', 'draft', 'private' ), 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'meta_key' => 'admitad_coupon_id', 'no_found_rows' => true ) );
		}
		$snapshot_id = wp_generate_uuid4();
		$source_ids = array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) ); sort( $source_ids );
		update_option( $this->status_key( $snapshot_id ), array( 'status' => 'previewing', 'owner_id' => get_current_user_id(), 'created_at' => time(), 'expires_at' => time() + DAY_IN_SECONDS, 'source_post_ids' => $source_ids, 'cursor' => 0, 'processed' => 0, 'affected' => 0, 'unchanged' => 0, 'locked' => 0, 'failed' => 0, 'heartbeat' => time() ), false );
		return array( 'id' => $snapshot_id, 'count' => count( $source_ids ), 'status' => 'previewing' );
	}

	/** Process at most fifty immutable preview rows without taxonomy mutation. */
	public function preview_next_batch( string $snapshot_id ) {
		$state = $this->authorized_state( $snapshot_id, array( 'previewing' ) );
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		return $this->with_snapshot_lock(
			$snapshot_id,
			function () use ( $snapshot_id ) {
				$state    = (array) get_option( $this->status_key( $snapshot_id ), array() );
				$ids      = array_slice( array_map( 'absint', (array) $state['source_post_ids'] ), (int) $state['cursor'], self::BATCH_SIZE );
				$existing = array_flip( array_map( 'intval', array_column( $this->history->snapshot_rows( $snapshot_id ), 'post_id' ) ) );
				foreach ( $ids as $post_id ) {
					++$state['processed'];
					try {
						if ( 'promocode' !== get_post_type( $post_id ) ) {
							++$state['unchanged'];
						} elseif ( Promokodiki_Admitad_Editorial_Locks::category_locked( $post_id ) ) {
							++$state['locked'];
						} else {
							$current_terms   = array_map( 'intval', wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) ) );
							$current_primary = (int) get_post_meta( $post_id, '_admitad_primary_term_id', true );
							$result          = call_user_func( $this->classifier, $this->coupon_from_post( $post_id ), $this->context_for_post( $post_id ) );
							$before          = $current_terms;
							$after           = $result->term_ids();
							sort( $before );
							sort( $after );
							if ( $before === $after && $current_primary === $result->primary_term_id() ) {
								++$state['unchanged'];
							} else {
								if ( ! isset( $existing[ $post_id ] ) ) {
									$this->history->record( $post_id, $result, $current_terms, $current_primary, 'preview', $snapshot_id );
									$existing[ $post_id ] = true;
								}
								++$state['affected'];
							}
						}
					} catch ( Throwable $error ) {
						++$state['failed'];
					}
					++$state['cursor'];
					$this->heartbeat_snapshot( $snapshot_id, $state );
				}
				if ( $state['cursor'] >= count( $state['source_post_ids'] ) ) {
					$state['status'] = 'previewed';
				}
				$this->heartbeat_snapshot( $snapshot_id, $state );
				return $this->preview_progress( $snapshot_id );
			}
		);
	}

	/** Read durable preview progress without exposing implementation details. */
	public function preview_progress( string $snapshot_id ) {
		$state = $this->authorized_state( $snapshot_id, array( 'previewing', 'previewed' ) );
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		unset( $state['source_post_ids'] );
		$state['snapshot_id'] = sanitize_text_field( $snapshot_id );
		return $state;
	}

	/** Begin or resume an owned apply operation. */
	public function start_apply( string $snapshot_id ) {
		$state = $this->authorized_state( $snapshot_id, array( 'previewed', 'applying' ), true );
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		if ( 'applying' === $state['status'] ) {
			return $this->snapshot_progress( $snapshot_id );
		}
		return $this->start_snapshot_operation( $snapshot_id, $state, 'applying' );
	}

	/** Apply at most fifty immutable preview rows. */
	public function apply_next_batch( string $snapshot_id ) {
		$state = $this->authorized_state( $snapshot_id, array( 'applying' ) );
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		return $this->with_snapshot_lock( $snapshot_id, fn() => $this->apply_batch_unlocked( $snapshot_id ) );
	}

	/** Apply a batch while holding the per-snapshot mutex. */
	private function apply_batch_unlocked( string $snapshot_id ) {
		$state = (array) get_option( $this->status_key( $snapshot_id ), array() );
		$rows  = $this->history->snapshot_rows( $snapshot_id );
		$batch = array_slice( $rows, (int) $state['cursor'], self::BATCH_SIZE );
		foreach ( $batch as $row ) {
			++$state['processed'];
			$post_id = (int) $row['post_id'];
			if ( 'promocode' !== get_post_type( $post_id ) || Promokodiki_Admitad_Editorial_Locks::category_locked( $post_id ) ) {
				++$state['skipped'];
			} else try {
				$result = new Promokodiki_Admitad_Classification_Result(
					array_map( 'intval', $row['result_terms'] ),
					(int) $row['result_primary_term_id'],
					(string) $row['confidence'],
					(array) $row['explanation']
				);
				if ( $this->assignments->assign( $post_id, $result, 'preview_apply' ) ) {
					++$state['changed'];
				} else {
					++$state['failed'];
					$this->add_failure( $state, $post_id, 'apply_failed' );
				}
			} catch ( Throwable $error ) {
				++$state['failed'];
				$this->add_failure( $state, $post_id, 'apply_failed' );
			}
			++$state['cursor'];
			$this->heartbeat_snapshot( $snapshot_id, $state );
		}
		if ( $state['cursor'] >= count( $rows ) ) {
			$state['status'] = 'applied';
			$state['expires_at'] = time() + ( DAY_IN_SECONDS * (int) Promokodiki_Admitad_Config::get( 'log_retention_days' ) );
		}
		$this->heartbeat_snapshot( $snapshot_id, $state );
		return $this->snapshot_progress( $snapshot_id );
	}

	/** Begin or resume rollback of an owned applied snapshot. */
	public function start_rollback( string $snapshot_id ) {
		$state = $this->authorized_state( $snapshot_id, array( 'applied', 'rolling_back' ), true );
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		if ( 'rolling_back' === $state['status'] ) {
			return $this->snapshot_progress( $snapshot_id );
		}
		return $this->start_snapshot_operation( $snapshot_id, $state, 'rolling_back' );
	}

	/** Restore at most fifty rows from immutable previous taxonomy fields. */
	public function rollback_next_batch( string $snapshot_id ) {
		$state = $this->authorized_state( $snapshot_id, array( 'rolling_back' ) );
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		return $this->with_snapshot_lock( $snapshot_id, fn() => $this->rollback_batch_unlocked( $snapshot_id ) );
	}

	/** Roll back a batch while holding the per-snapshot mutex. */
	private function rollback_batch_unlocked( string $snapshot_id ) {
		$state = (array) get_option( $this->status_key( $snapshot_id ), array() );
		$rows  = $this->history->snapshot_rows( $snapshot_id );
		$batch = array_slice( $rows, (int) $state['cursor'], self::BATCH_SIZE );
		foreach ( $batch as $row ) {
			++$state['processed'];
			$post_id = (int) $row['post_id'];
			if ( 'promocode' !== get_post_type( $post_id ) || Promokodiki_Admitad_Editorial_Locks::category_locked( $post_id ) ) {
				++$state['skipped'];
			} else try {
				$assigned = Promokodiki_Admitad_Import_Context::run(
					static fn() => wp_set_post_terms(
						$post_id,
						array_map( 'intval', $row['previous_terms'] ),
						'promocode_category',
						false
					)
				);
				if ( is_wp_error( $assigned ) ) {
					throw new RuntimeException( 'Unable to restore snapshot taxonomy.' );
				}
				update_post_meta( $post_id, '_admitad_primary_term_id', (int) $row['previous_primary_term_id'] );
				++$state['changed'];
			} catch ( Throwable $error ) {
				++$state['failed'];
				$this->add_failure( $state, $post_id, 'rollback_failed' );
			}
			++$state['cursor'];
			$this->heartbeat_snapshot( $snapshot_id, $state );
		}
		if ( $state['cursor'] >= count( $rows ) ) {
			$state['status'] = 'rolled_back';
			$state['expires_at'] = time() + ( DAY_IN_SECONDS * (int) Promokodiki_Admitad_Config::get( 'log_retention_days' ) );
		}
		$this->heartbeat_snapshot( $snapshot_id, $state );
		return $this->snapshot_progress( $snapshot_id );
	}

	/** Read sanitized durable apply or rollback progress. */
	public function snapshot_progress( string $snapshot_id ) {
		$state = $this->authorized_state( $snapshot_id, array( 'previewing', 'previewed', 'applying', 'applied', 'rolling_back', 'rolled_back' ) );
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		unset( $state['source_post_ids'] );
		$state['snapshot_id'] = sanitize_text_field( $snapshot_id );
		return $state;
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
		if ( 1 !== preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $snapshot_id ) ) {
			return null;
		}
		$rows  = $this->history->snapshot_rows( $snapshot_id );
		$state = get_option( $this->status_key( $snapshot_id ), array() );
		$active_statuses = array( 'previewing', 'applying', 'rolling_back' );
		if (
			! is_array( $state )
			|| ! isset( $state['status'] )
			|| ( (int) ( $state['expires_at'] ?? 0 ) < time() && ! in_array( $state['status'], $active_statuses, true ) )
		) {
			return null;
		}
		return array(
			'id'         => $snapshot_id,
			'status'     => sanitize_key( (string) $state['status'] ),
			'owner_id'   => (int) ( $state['owner_id'] ?? 0 ),
			'created_at' => (int) ( $state['created_at'] ?? 0 ),
			'expires_at' => (int) ( $state['expires_at'] ?? 0 ),
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
		$state = $this->start_apply( $snapshot_id );
		if ( ! is_wp_error( $state ) && 'applying' === $state['status'] && ! wp_next_scheduled( 'promokodiki_admitad_apply_classification', array( $snapshot_id, 0 ) ) ) {
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
		unset( $offset );
		$service  = new self();
		$snapshot = $service->get_snapshot( $snapshot_id );
		if ( ! $snapshot || 'applying' !== $snapshot['status'] ) {
			return;
		}
		$previous_user = get_current_user_id();
		wp_set_current_user( (int) $snapshot['owner_id'] );
		try {
			$progress = $service->apply_next_batch( $snapshot_id );
		} finally {
			wp_set_current_user( $previous_user );
		}
		if ( ! is_wp_error( $progress ) && 'applying' === $progress['status'] ) {
			wp_schedule_single_event( time() + 1, 'promokodiki_admitad_apply_classification', array( $snapshot_id, (int) $progress['cursor'] ) );
		}
	}

	/** Validate capability, ownership, expiry, and an allowed state. */
	private function authorized_state( string $snapshot_id, array $statuses, bool $allow_expired = false ) {
		if ( ! current_user_can( 'manage_admitad_automation' ) ) {
			return new WP_Error( 'forbidden', 'Недостаточно прав для управления снимком.' );
		}
		$snapshot = $this->get_snapshot( sanitize_text_field( $snapshot_id ) );
		if ( ! $snapshot && $allow_expired && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $snapshot_id ) ) {
			$state = (array) get_option( $this->status_key( $snapshot_id ), array() );
			if ( ! empty( $state['status'] ) ) {
				$snapshot = array(
					'status'   => sanitize_key( (string) $state['status'] ),
					'owner_id' => (int) ( $state['owner_id'] ?? 0 ),
				);
			}
		}
		if ( ! $snapshot ) {
			return new WP_Error( 'invalid_snapshot', 'Снимок недоступен.' );
		}
		if ( get_current_user_id() !== (int) $snapshot['owner_id'] ) {
			return new WP_Error( 'foreign_snapshot', 'Снимок принадлежит другому пользователю.' );
		}
		if ( ! in_array( $snapshot['status'], $statuses, true ) ) {
			return new WP_Error( 'invalid_snapshot_state', 'Снимок находится в неподходящем состоянии.' );
		}
		return (array) get_option( $this->status_key( $snapshot_id ), array() );
	}

	/** Initialize bounded operation counters while retaining immutable preview data. */
	private function start_snapshot_operation( string $snapshot_id, array $state, string $status ): array {
		$state['status'] = $status;
		$state['cursor'] = 0;
		$state['processed'] = 0;
		$state['changed'] = 0;
		$state['skipped'] = 0;
		$state['failed'] = 0;
		$state['failure_summaries'] = array();
		$state['heartbeat'] = time();
		$state['expires_at'] = time() + DAY_IN_SECONDS;
		update_option( $this->status_key( $snapshot_id ), $state, false );
		return $this->snapshot_progress( $snapshot_id );
	}

	/** Persist cursor progress and keep an active operation resumable. */
	private function heartbeat_snapshot( string $snapshot_id, array &$state ): void {
		$state['heartbeat']  = time();
		if ( in_array( $state['status'], array( 'previewing', 'applying', 'rolling_back' ), true ) ) {
			$state['expires_at'] = time() + DAY_IN_SECONDS;
		}
		update_option( $this->status_key( $snapshot_id ), $state, false );

		$lock_key = $this->lock_key( $snapshot_id );
		$lock     = get_option( $lock_key, array() );
		if ( is_array( $lock ) && ! empty( $lock['token'] ) ) {
			$lock['heartbeat'] = time();
			update_option( $lock_key, $lock, false );
		}
	}

	/** Serialize each mutable step and safely recover abandoned locks. */
	private function with_snapshot_lock( string $snapshot_id, callable $callback ) {
		$lock_key = $this->lock_key( $snapshot_id );
		$token    = wp_generate_uuid4();
		$lock     = array(
			'token'     => $token,
			'owner_id'  => get_current_user_id(),
			'heartbeat' => time(),
		);
		if ( ! add_option( $lock_key, $lock, '', false ) ) {
			$existing = get_option( $lock_key, array() );
			if ( is_array( $existing ) && (int) ( $existing['heartbeat'] ?? 0 ) < time() - 120 ) {
				delete_option( $lock_key );
			}
			if ( ! add_option( $lock_key, $lock, '', false ) ) {
				return new WP_Error( 'snapshot_locked', 'Операция над снимком уже выполняется.' );
			}
		}
		try {
			return call_user_func( $callback );
		} finally {
			$current = get_option( $lock_key, array() );
			if ( is_array( $current ) && hash_equals( $token, (string) ( $current['token'] ?? '' ) ) ) {
				delete_option( $lock_key );
			}
		}
	}

	/** Store only stable path-free failure evidence. */
	private function add_failure( array &$state, int $post_id, string $code ): void {
		if ( count( (array) ( $state['failure_summaries'] ?? array() ) ) >= 20 ) {
			return;
		}
		$state['failure_summaries'][] = array( 'post_id' => $post_id, 'code' => sanitize_key( $code ) );
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
		if ( in_array( $state['status'], array( 'applied', 'rolled_back' ), true ) ) {
			$state['expires_at'] = time() + ( DAY_IN_SECONDS * (int) Promokodiki_Admitad_Config::get( 'log_retention_days' ) );
		}
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

	/** Return per-snapshot mutex option key. */
	private function lock_key( string $snapshot_id ): string {
		return 'promokodiki_admitad_snapshot_lock_' . sanitize_key( $snapshot_id );
	}
}
