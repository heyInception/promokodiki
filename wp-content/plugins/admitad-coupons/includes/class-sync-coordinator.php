<?php
/**
 * Resumable Admitad synchronization coordinator.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes one bounded API page per invocation.
 */
final class Promokodiki_Admitad_Sync_Coordinator {
	/**
	 * API client.
	 *
	 * @var Promokodiki_Admitad_Api_Client
	 */
	private Promokodiki_Admitad_Api_Client $api;
	/**
	 * Complete coupon pipeline.
	 *
	 * @var Promokodiki_Admitad_Import_Pipeline
	 */
	private Promokodiki_Admitad_Import_Pipeline $pipeline;
	/**
	 * Run repository.
	 *
	 * @var Promokodiki_Admitad_Sync_Run_Repository
	 */
	private Promokodiki_Admitad_Sync_Run_Repository $runs;
	/**
	 * Job lock.
	 *
	 * @var Promokodiki_Admitad_Job_Lock
	 */
	private Promokodiki_Admitad_Job_Lock $locks;
	/**
	 * Reference repository.
	 *
	 * @var Promokodiki_Admitad_Reference_Repository
	 */
	private Promokodiki_Admitad_Reference_Repository $references;
	/**
	 * Single-event scheduler.
	 *
	 * @var callable
	 */
	private $scheduler;

	/**
	 * Constructor.
	 *
	 * @param Promokodiki_Admitad_Api_Client|null                                            $api        API client.
	 * @param Promokodiki_Admitad_Coupon_Repository|Promokodiki_Admitad_Import_Pipeline|null $coupons Coupon persistence or pipeline.
	 * @param Promokodiki_Admitad_Sync_Run_Repository|null                                   $runs       Run repository.
	 * @param Promokodiki_Admitad_Job_Lock|null                                              $locks      Job lock.
	 * @param callable|null                                                                  $scheduler  Single-event scheduler.
	 * @param Promokodiki_Admitad_Reference_Repository|null                                  $references Reference repository.
	 */
	public function __construct( $api = null, $coupons = null, $runs = null, $locks = null, ?callable $scheduler = null, $references = null ) {
		$this->api        = $api ?? new Promokodiki_Admitad_Api_Client();
		$this->pipeline   = $coupons instanceof Promokodiki_Admitad_Import_Pipeline
			? $coupons
			: new Promokodiki_Admitad_Import_Pipeline( $coupons );
		$this->runs       = $runs ?? new Promokodiki_Admitad_Sync_Run_Repository();
		$this->locks      = $locks ?? new Promokodiki_Admitad_Job_Lock();
		$this->references = $references ?? new Promokodiki_Admitad_Reference_Repository();
		$this->scheduler  = $scheduler ?? 'wp_schedule_single_event';
	}

	/**
	 * Handle a scheduled coupon batch.
	 *
	 * @param int $run_id Run ID.
	 * @param int $offset Page offset.
	 */
	public static function handle_coupon_batch( int $run_id, int $offset ): void {
		( new self() )->run_coupon_batch( $run_id, $offset );
	}

	/**
	 * Handle a scheduled reference batch.
	 *
	 * @param int    $run_id Run ID.
	 * @param string $phase  Reference phase.
	 * @param int    $offset Page offset.
	 */
	public static function handle_reference_batch( int $run_id, string $phase, int $offset ): void {
		( new self() )->run_reference_batch( $run_id, $phase, $offset );
	}

	/** Handle one scheduled logo batch. */
	public static function handle_logo_batch( int $run_id, int $offset, int $total ): void {
		( new self() )->run_logo_batch( $run_id, $offset, $total );
	}

	/**
	 * Start a coupon synchronization.
	 *
	 * @return int|WP_Error
	 */
	public function start_coupon_sync() {
		return $this->start( 'coupon', 'promokodiki_admitad_coupon_batch', array( 0 ) );
	}

	/**
	 * Start a reference synchronization.
	 *
	 * @return int|WP_Error
	 */
	public function start_reference_sync() {
		return $this->start( 'reference', 'promokodiki_admitad_reference_batch', array( 'categories', 0 ) );
	}

	/**
	 * Run one coupon page.
	 *
	 * @param int $run_id Run ID.
	 * @param int $offset Page offset.
	 * @return array<string, mixed>|WP_Error
	 */
	public function run_coupon_batch( int $run_id, int $offset ) {
		$limit = (int) Promokodiki_Admitad_Config::get( 'batch_size' );
		$page  = $this->api->coupon_page( $limit, $offset );
		if ( is_wp_error( $page ) ) {
			return $this->handle_error( 'coupon', $run_id, $offset, $page, 'promokodiki_admitad_coupon_batch', array( $run_id, $offset ) );
		}

		$counters = $this->counters( $run_id );
		foreach ( $page['results'] as $raw_coupon ) {
			$result = $this->pipeline->process( $raw_coupon, $run_id );
			if ( is_wp_error( $result ) ) {
				++$counters['processed'];
				++$counters['failed'];
				continue;
			}
			++$counters['processed'];
			++$counters[ $result['state'] ];
		}
		$next     = $offset + count( $page['results'] );
		$complete = count( $page['results'] ) < $limit;
		$this->finish_or_continue( 'coupon', $run_id, $next, $counters, $complete, 'promokodiki_admitad_coupon_batch', array( $run_id, $next ) );
		return array(
			'next_offset' => $next,
			'complete'    => $complete,
			'counters'    => $counters,
		);
	}

	/**
	 * Run one reference page.
	 *
	 * @param int    $run_id Run ID.
	 * @param string $phase  Reference phase.
	 * @param int    $offset Page offset.
	 * @return array<string, mixed>|WP_Error
	 */
	public function run_reference_batch( int $run_id, string $phase, int $offset ) {
		$limit = (int) Promokodiki_Admitad_Config::get( 'batch_size' );
		$page  = 'categories' === $phase ? $this->api->coupon_category_page( $limit, $offset ) : $this->api->campaign_page( $limit, $offset );
		if ( is_wp_error( $page ) ) {
			return $this->handle_error( 'reference', $run_id, $offset, $page, 'promokodiki_admitad_reference_batch', array( $run_id, $phase, $offset ) );
		}
		$items = $page['results'];
		if ( 'categories' === $phase ) {
			$this->references->sync_coupon_categories( $items );
		} else {
			$campaigns = array_map( array( 'Promokodiki_Admitad_Campaign_Normalizer', 'normalize' ), $items );
			$this->references->sync_campaigns( $campaigns );
			$shop_profiles = new Promokodiki_Admitad_Shop_Profile_Sync();
			foreach ( $campaigns as $campaign ) {
				$shop_profiles->sync_campaign( $campaign );
			}
		}
		$next = $offset + count( $items );
		if ( count( $items ) >= $limit ) {
			$this->schedule( time() + 1, 'promokodiki_admitad_reference_batch', array( $run_id, $phase, $next ) );
			$this->runs->heartbeat( $run_id, $next, array( 'processed' => $next ) );
			return array(
				'phase'       => $phase,
				'next_offset' => $next,
				'complete'    => false,
			);
		}
		if ( 'categories' === $phase ) {
			$this->schedule( time() + 1, 'promokodiki_admitad_reference_batch', array( $run_id, 'campaigns', 0 ) );
			return array(
				'phase'       => 'campaigns',
				'next_offset' => 0,
				'complete'    => false,
			);
		}
		if ( ! get_option( 'promokodiki_admitad_shop_enrichment_complete' ) && ! get_option( 'promokodiki_admitad_shop_enrichment_requested' ) ) {
			$this->runs->complete( $run_id, array( 'processed' => $next ) );
			( new Promokodiki_Admitad_Notifier() )->record_success( 'reference' );
			$this->release( 'reference', $run_id );
			return array( 'phase' => 'campaigns', 'next_offset' => $next, 'complete' => true );
		}
		global $wpdb;
		$profile_table = Promokodiki_Admitad_Schema::table( 'company_profile' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Count bounds the follow-up logo traversal.
		$logo_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$profile_table} WHERE image_url <> ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Validated table identifier.
		$this->schedule( time() + 1, 'promokodiki_admitad_logo_batch', array( $run_id, 0, $logo_total ) );
		return array(
			'phase'       => 'campaigns',
			'next_offset' => $next,
			'complete'    => false,
		);
	}

	/**
	 * Process one bounded page of stored campaign logos.
	 *
	 * @return array{next_offset:int,complete:bool}
	 */
	public function run_logo_batch( int $run_id, int $offset, int $total ): array {
		global $wpdb;

		$limit = (int) Promokodiki_Admitad_Config::get( 'batch_size' );
		$table = Promokodiki_Admitad_Schema::table( 'company_profile' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded continuation reads snapshot IDs.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT campaign_id FROM {$table} WHERE image_url <> '' ORDER BY campaign_id ASC LIMIT %d OFFSET %d", $limit, max( 0, $offset ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Validated table identifier.
		$service = new Promokodiki_Admitad_Managed_Logo_Service();
		foreach ( $ids as $campaign_id ) {
			$service->process_campaign( (int) $campaign_id );
		}
		$next     = $offset + count( $ids );
		$complete = count( $ids ) < $limit || $next >= $total;
		if ( $complete ) {
			$this->runs->complete( $run_id, array( 'processed' => max( $total, $next ) ) );
			update_option( 'promokodiki_admitad_shop_enrichment_complete', time(), false );
			delete_option( 'promokodiki_admitad_shop_enrichment_requested' );
			( new Promokodiki_Admitad_Notifier() )->record_success( 'reference' );
			$this->release( 'reference', $run_id );
		} else {
			$this->runs->heartbeat( $run_id, $next, array( 'processed' => $next ) );
			$this->schedule( time() + 1, 'promokodiki_admitad_logo_batch', array( $run_id, $next, $total ) );
		}
		return array( 'next_offset' => $next, 'complete' => $complete );
	}

	/**
	 * Start and schedule a named job.
	 *
	 * @param string       $job       Job name.
	 * @param string       $hook      Batch hook.
	 * @param array<mixed> $tail_args Initial hook arguments.
	 * @return int|WP_Error
	 */
	private function start( string $job, string $hook, array $tail_args ) {
		$owner = wp_generate_uuid4();
		if ( ! $this->locks->acquire( $job, $owner, 600 ) ) {
			return new WP_Error( 'admitad_sync_locked', 'An Admitad synchronization is already running.' );
		}
		$run_id = $this->runs->start( $job );
		update_option( 'promokodiki_admitad_run_owner_' . $run_id, $owner, false );
		$this->schedule( time() + 1, $hook, array_merge( array( $run_id ), $tail_args ) );
		return $run_id;
	}

	/**
	 * Read accumulated counters.
	 *
	 * @param int $run_id Run ID.
	 * @return array<string, int>
	 */
	private function counters( int $run_id ): array {
		$row = $this->runs->get( $run_id ) ?? array();
		$out = array();
		foreach ( array( 'processed', 'created', 'updated', 'unchanged', 'failed' ) as $name ) {
			$out[ $name ] = (int) ( $row[ $name . '_count' ] ?? 0 );
		}
		return $out;
	}

	/**
	 * Persist progress and either finish or schedule continuation.
	 *
	 * @param string             $job      Job name.
	 * @param int                $run_id   Run ID.
	 * @param int                $next     Next offset.
	 * @param array<string, int> $counters Counters.
	 * @param bool               $complete Completion flag.
	 * @param string             $hook     Continuation hook.
	 * @param array<mixed>       $args     Hook arguments.
	 */
	private function finish_or_continue( string $job, int $run_id, int $next, array $counters, bool $complete, string $hook, array $args ): void {
		if ( $complete ) {
			$this->runs->complete( $run_id, $counters );
			( new Promokodiki_Admitad_Notifier() )->record_success( $job );
			$this->release( $job, $run_id );
			return;
		}
		$this->runs->heartbeat( $run_id, $next, $counters );
		$this->schedule( time() + 1, $hook, $args );
	}

	/**
	 * Schedule a retry or fail the run.
	 *
	 * @param string       $job     Job name.
	 * @param int          $run_id  Run ID.
	 * @param int          $offset  Current offset.
	 * @param WP_Error     $error   API error.
	 * @param string       $hook    Retry hook.
	 * @param array<mixed> $args    Hook arguments.
	 * @return WP_Error
	 */
	private function handle_error( string $job, int $run_id, int $offset, WP_Error $error, string $hook, array $args ) {
		unset( $offset );
		if ( 'admitad_retryable' === $error->get_error_code() ) {
			$key     = 'promokodiki_admitad_retry_' . $run_id;
			$attempt = (int) get_option( $key, 0 ) + 1;
			update_option( $key, $attempt, false );
			if ( $attempt <= (int) Promokodiki_Admitad_Config::get( 'max_retries' ) ) {
				$data  = (array) $error->get_error_data();
				$delay = max( 1, (int) ( $data['retry_after'] ?? Promokodiki_Admitad_Config::get( 'retry_base_seconds' ) ) );
				$this->schedule( time() + $delay, $hook, $args );
				return $error;
			}
		}
		$this->runs->fail( $run_id, $error );
		( new Promokodiki_Admitad_Notifier() )->record_failure( $job, $error );
		$this->release( $job, $run_id );
		return $error;
	}

	/**
	 * Release job state.
	 *
	 * @param string $job    Job name.
	 * @param int    $run_id Run ID.
	 */
	private function release( string $job, int $run_id ): void {
		$key   = 'promokodiki_admitad_run_owner_' . $run_id;
		$owner = (string) get_option( $key, '' );
		$this->locks->release( $job, $owner );
		delete_option( $key );
		delete_option( 'promokodiki_admitad_retry_' . $run_id );
	}

	/**
	 * Schedule one event.
	 *
	 * @param int          $timestamp Unix timestamp.
	 * @param string       $hook      Hook.
	 * @param array<mixed> $args      Hook arguments.
	 */
	private function schedule( int $timestamp, string $hook, array $args ): bool {
		return (bool) call_user_func( $this->scheduler, $timestamp, $hook, $args );
	}
}
