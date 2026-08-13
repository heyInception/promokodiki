<?php
/**
 * Main plugin hook registration.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers integration hooks.
 */
final class Promokodiki_Admitad_Plugin {
	/**
	 * Register WordPress hooks.
	 */
	public static function boot(): void {
		add_action( 'init', array( self::class, 'register' ), 0 );
		add_action( 'init', array( self::class, 'maybe_upgrade_schema' ), 1 );
		add_action( 'init', array( self::class, 'schedule' ), 20 );
		// phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- The administrator-configured interval is bounded by Promokodiki_Admitad_Config.
		add_filter( 'cron_schedules', array( self::class, 'cron_schedules' ) );
		Promokodiki_Admitad_Editorial_Locks::register();
		Promokodiki_Admitad_Visibility::register();
		add_action( 'promokodiki_admitad_coupon_sync', array( self::class, 'handle_coupon_sync' ) );
		add_action( 'promokodiki_admitad_reference_sync', array( self::class, 'handle_reference_sync' ) );
		add_action( 'promokodiki_admitad_reconcile', array( self::class, 'handle_reconcile' ) );
		add_action( 'promokodiki_admitad_retention', array( self::class, 'handle_retention' ) );
		add_action(
			'promokodiki_admitad_apply_classification',
			array( 'Promokodiki_Admitad_Reclassification_Service', 'handle_apply_batch' ),
			10,
			2
		);
		add_action( 'admin_init', array( self::class, 'check_job_health' ) );
		add_action( 'admin_notices', array( 'Promokodiki_Admitad_Notifier', 'render_admin_notice' ) );
		add_action(
			'promokodiki_admitad_coupon_batch',
			array( 'Promokodiki_Admitad_Sync_Coordinator', 'handle_coupon_batch' ),
			10,
			2
		);
		add_action(
			'promokodiki_admitad_reference_batch',
			array( 'Promokodiki_Admitad_Sync_Coordinator', 'handle_reference_batch' ),
			10,
			3
		);
		add_action( 'promokodiki_admitad_logo_batch', array( 'Promokodiki_Admitad_Sync_Coordinator', 'handle_logo_batch' ), 10, 3 );
		add_action( 'promokodiki_admitad_deeplink_batch', array( 'Promokodiki_Admitad_Deeplink_Queue', 'handle' ) );
	}

	/**
	 * Register content types owned by the integration.
	 */
	public static function register(): void {
		admitad_register_content_types();
	}

	/**
	 * Apply idempotent schema upgrades after plugin updates.
	 */
	public static function maybe_upgrade_schema(): void {
		if ( (int) get_option( 'promokodiki_admitad_db_version', 0 ) < Promokodiki_Admitad_Schema::VERSION ) {
			Promokodiki_Admitad_Schema::install();
		}
	}

	/**
	 * Return all recurring, continuation, and legacy hooks for cleanup.
	 *
	 * @return array<int, string>
	 */
	public static function cron_hooks(): array {
		return array(
			'promokodiki_admitad_coupon_sync',
			'promokodiki_admitad_reference_sync',
			'promokodiki_admitad_reconcile',
			'promokodiki_admitad_retention',
			'promokodiki_admitad_coupon_batch',
			'promokodiki_admitad_reference_batch',
			'promokodiki_admitad_logo_batch',
			'promokodiki_admitad_deeplink_batch',
			'promokodiki_admitad_apply_classification',
			'update_admitad_coupons_event',
			'update_admitad_shop_coupons_event',
		);
	}

	/**
	 * Add configurable recurring intervals.
	 *
	 * @param array<string, array<string, mixed>> $schedules Existing schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public static function cron_schedules( array $schedules ): array {
		$schedules['promokodiki_admitad_coupon']    = array(
			'interval' => (int) Promokodiki_Admitad_Config::get( 'coupon_interval' ),
			'display'  => __( 'Admitad coupon synchronization', 'promokodiki-admitad' ),
		);
		$schedules['promokodiki_admitad_reference'] = array(
			'interval' => (int) Promokodiki_Admitad_Config::get( 'reference_interval' ),
			'display'  => __( 'Admitad reference synchronization', 'promokodiki-admitad' ),
		);
		$schedules['promokodiki_admitad_reconcile'] = array(
			'interval' => (int) Promokodiki_Admitad_Config::get( 'reconcile_interval' ),
			'display'  => __( 'Admitad coupon reconciliation', 'promokodiki-admitad' ),
		);
		return $schedules;
	}

	/**
	 * Register recurring events once.
	 */
	public static function schedule(): void {
		$events = array(
			'promokodiki_admitad_coupon_sync'    => 'promokodiki_admitad_coupon',
			'promokodiki_admitad_reference_sync' => 'promokodiki_admitad_reference',
			'promokodiki_admitad_reconcile'      => 'promokodiki_admitad_reconcile',
			'promokodiki_admitad_retention'      => 'daily',
		);
		foreach ( $events as $hook => $recurrence ) {
			if ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_event( time() + MINUTE_IN_SECONDS, $recurrence, $hook );
			}
		}
		wp_clear_scheduled_hook( 'update_admitad_coupons_event' );
		wp_clear_scheduled_hook( 'update_admitad_shop_coupons_event' );
	}

	/**
	 * Start the resumable coupon job.
	 */
	public static function handle_coupon_sync(): void {
		$result = ( new Promokodiki_Admitad_Sync_Coordinator() )->start_coupon_sync();
		if ( is_wp_error( $result ) && 'admitad_sync_locked' !== $result->get_error_code() ) {
			( new Promokodiki_Admitad_Notifier() )->record_failure( 'coupon', $result );
		}
	}

	/**
	 * Start the resumable reference job.
	 */
	public static function handle_reference_sync(): void {
		$result = ( new Promokodiki_Admitad_Sync_Coordinator() )->start_reference_sync();
		if ( is_wp_error( $result ) && 'admitad_sync_locked' !== $result->get_error_code() ) {
			( new Promokodiki_Admitad_Notifier() )->record_failure( 'reference', $result );
		}
	}

	/**
	 * Reconcile the newest completed coupon traversal once.
	 */
	public static function handle_reconcile(): void {
		$runs = new Promokodiki_Admitad_Sync_Run_Repository();
		$run  = $runs->latest_completed( 'coupon' );
		if ( ! $run || (int) get_option( 'promokodiki_admitad_last_reconciled_run', 0 ) === (int) $run['id'] ) {
			return;
		}

		$result   = ( new Promokodiki_Admitad_Reconciler( $runs ) )->after_completed_run( (int) $run['id'] );
		$notifier = new Promokodiki_Admitad_Notifier();
		if ( is_wp_error( $result ) ) {
			$notifier->record_failure( 'reconcile', $result );
			return;
		}
		update_option( 'promokodiki_admitad_last_reconciled_run', (int) $run['id'], false );
		update_option( 'promokodiki_admitad_last_reconcile', time(), false );
		$notifier->record_success( 'reconcile' );
	}

	/**
	 * Delete expired operational detail while preserving active snapshots and queues.
	 */
	public static function handle_retention(): void {
		( new Promokodiki_Admitad_Retention() )->run();
	}

	/**
	 * Refresh delayed-job state before rendering administrator notices.
	 */
	public static function check_job_health(): void {
		( new Promokodiki_Admitad_Notifier() )->check_delayed_jobs();
	}
}
