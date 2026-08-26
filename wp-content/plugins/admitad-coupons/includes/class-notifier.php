<?php
/**
 * Operational health warnings and throttled alerts.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks failures, delayed jobs, and administrator notifications.
 */
final class Promokodiki_Admitad_Notifier {
	/**
	 * Alert throttle.
	 */
	private const ALERT_THROTTLE = 12 * HOUR_IN_SECONDS;

	/**
	 * Mail callback.
	 *
	 * @var callable
	 */
	private $mailer;

	/**
	 * Clock callback.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @param callable|null $mailer Mail callback.
	 * @param callable|null $clock  Unix timestamp callback.
	 */
	public function __construct( ?callable $mailer = null, ?callable $clock = null ) {
		$this->mailer = $mailer ?? 'wp_mail';
		$this->clock  = $clock ?? 'time';
	}

	/**
	 * Record a successful job and clear its failure streak.
	 *
	 * @param string $job Job name.
	 */
	public function record_success( string $job ): void {
		delete_option( $this->failure_key( $job ) );
	}

	/**
	 * Record a failure and alert at threshold or immediately for OAuth.
	 *
	 * @param string   $job   Job name.
	 * @param WP_Error $error Failure.
	 */
	public function record_failure( string $job, WP_Error $error ): void {
		$job   = sanitize_key( $job );
		$count = (int) get_option( $this->failure_key( $job ), 0 ) + 1;
		update_option( $this->failure_key( $job ), $count, false );

		$data     = (array) $error->get_error_data();
		$is_oauth = str_contains( (string) $error->get_error_code(), 'oauth' )
			|| 401 === (int) ( $data['status'] ?? 0 );
		if ( ! $is_oauth && $count < 2 ) {
			return;
		}

		$this->send_throttled_alert( $job, $error );
	}

	/**
	 * Find recurring events overdue by more than two expected intervals.
	 *
	 * @return array<int, string>
	 */
	public function check_delayed_jobs(): array {
		$now     = (int) call_user_func( $this->clock );
		$delayed = array();
		$jobs    = array(
			'coupon'    => array( 'promokodiki_admitad_coupon_sync', (int) Promokodiki_Admitad_Config::get( 'coupon_interval' ) ),
			'reference' => array( 'promokodiki_admitad_reference_sync', (int) Promokodiki_Admitad_Config::get( 'reference_interval' ) ),
			'reconcile' => array( 'promokodiki_admitad_reconcile', (int) Promokodiki_Admitad_Config::get( 'reconcile_interval' ) ),
		);
		foreach ( $jobs as $job => $definition ) {
			$event = wp_get_scheduled_event( $definition[0] );
			if ( $event && (int) $event->timestamp < $now - ( 2 * $definition[1] ) ) {
				$delayed[] = $job;
			}
		}
		update_option( 'promokodiki_admitad_delayed_jobs', $delayed, false );
		return $delayed;
	}

	/**
	 * Return the current administrator warning text.
	 */
	public static function admin_notice_message(): string {
		$jobs = (array) get_option( 'promokodiki_admitad_delayed_jobs', array() );
		if ( ! $jobs ) {
			return '';
		}

		return sprintf(
			/* translators: %s: delayed job identifiers. */
			__( 'Синхронизация Admitad задерживается: %s. Проверьте WP-Cron и журнал запусков.', 'promokodiki-admitad' ),
			implode( ', ', array_map( 'sanitize_key', $jobs ) )
		);
	}

	/**
	 * Render the delayed-job warning.
	 */
	public static function render_admin_notice(): void {
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- The custom capability is installed by Promokodiki_Admitad_Capabilities.
		if ( ! current_user_can( 'manage_admitad_settings' ) ) {
			return;
		}
		$message = self::admin_notice_message();
		if ( '' !== $message ) {
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $message ) );
		}
	}

	/**
	 * Send at most one alert per job during the throttle window.
	 *
	 * @param string   $job   Job name.
	 * @param WP_Error $error Failure.
	 */
	private function send_throttled_alert( string $job, WP_Error $error ): void {
		if ( ! Promokodiki_Admitad_Config::get( 'email_alerts' ) ) {
			return;
		}
		$now      = (int) call_user_func( $this->clock );
		$last_key = 'promokodiki_admitad_last_alert_' . $job;
		if ( (int) get_option( $last_key, 0 ) + self::ALERT_THROTTLE > $now ) {
			return;
		}
		$recipient = (string) Promokodiki_Admitad_Config::get( 'email_recipient' );
		if ( '' === $recipient ) {
			return;
		}

		$sent = (bool) call_user_func(
			$this->mailer,
			$recipient,
			sprintf( '[Promokodiki] Admitad: %s', $job ),
			sanitize_text_field( $error->get_error_message() )
		);
		if ( $sent ) {
			update_option( $last_key, $now, false );
		}
	}

	/**
	 * Return a sanitized failure-counter option key.
	 *
	 * @param string $job Job name.
	 */
	private function failure_key( string $job ): string {
		return 'promokodiki_admitad_failure_count_' . sanitize_key( $job );
	}
}
