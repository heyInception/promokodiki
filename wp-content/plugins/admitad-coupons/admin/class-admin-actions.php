<?php
/**
 * Capability- and nonce-protected administrative mutations.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles all current Admitad admin-post actions.
 */
final class Promokodiki_Admitad_Admin_Actions {
	/**
	 * Register action handlers.
	 */
	public static function register(): void {
		add_action( 'admin_post_promokodiki_admitad_save_settings', array( self::class, 'handle_save_settings' ) );
		add_action( 'admin_post_promokodiki_admitad_refresh_token', array( self::class, 'handle_refresh_token' ) );
		add_action( 'admin_post_promokodiki_admitad_unlock_post', array( self::class, 'handle_unlock_post' ) );
	}

	/**
	 * Validate and save global settings and credentials.
	 *
	 * @param array<string, mixed> $settings    Raw settings.
	 * @param array<string, mixed> $credentials Raw credentials.
	 * @param string               $nonce       Request nonce.
	 * @return true|WP_Error
	 */
	public function save_settings( array $settings, array $credentials, string $nonce ) {
		if ( ! current_user_can( 'manage_admitad_automation' ) ) {
			return new WP_Error( 'forbidden', 'You cannot manage Admitad automation.' );
		}
		if ( ! wp_verify_nonce( $nonce, 'promokodiki_admitad_save_settings' ) ) {
			return new WP_Error( 'invalid_nonce', 'Invalid settings nonce.' );
		}

		$previous = Promokodiki_Admitad_Config::sanitize( (array) get_option( Promokodiki_Admitad_Config::OPTION_NAME, array() ) );
		$clean    = Promokodiki_Admitad_Config::sanitize( $settings );
		update_option( Promokodiki_Admitad_Config::OPTION_NAME, $clean, false );

		$credential_options = array(
			'client_id'     => 'PROMOKODIKI_ADMITAD_CLIENT_ID',
			'client_secret' => 'PROMOKODIKI_ADMITAD_CLIENT_SECRET',
			'website_id'    => 'PROMOKODIKI_ADMITAD_WEBSITE_ID',
		);
		foreach ( $credential_options as $key => $constant ) {
			if ( defined( $constant ) ) {
				continue;
			}
			$value = sanitize_text_field( (string) ( $credentials[ $key ] ?? '' ) );
			if ( 'client_secret' === $key && '' === $value ) {
				continue;
			}
			update_option( 'promokodiki_admitad_' . $key, $value, false );
		}
		admitad_clear_cached_token();

		if (
			$previous['coupon_interval'] !== $clean['coupon_interval']
			|| $previous['reference_interval'] !== $clean['reference_interval']
			|| $previous['reconcile_interval'] !== $clean['reconcile_interval']
		) {
			foreach ( array( 'promokodiki_admitad_coupon_sync', 'promokodiki_admitad_reference_sync', 'promokodiki_admitad_reconcile' ) as $hook ) {
				wp_clear_scheduled_hook( $hook );
			}
			Promokodiki_Admitad_Plugin::schedule();
		}
		return true;
	}

	/**
	 * Remove one editorial lock after reviewer confirmation.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $scope   categories or content.
	 * @param string $nonce   Request nonce.
	 * @return true|WP_Error
	 */
	public function unlock_post( int $post_id, string $scope, string $nonce ) {
		if ( ! current_user_can( 'review_admitad_mapping' ) || 'promocode' !== get_post_type( $post_id ) ) {
			return new WP_Error( 'forbidden', 'You cannot unlock this coupon.' );
		}
		if ( ! wp_verify_nonce( $nonce, 'promokodiki_admitad_unlock_' . $post_id ) ) {
			return new WP_Error( 'invalid_nonce', 'Invalid unlock nonce.' );
		}
		if ( 'categories' === $scope ) {
			delete_post_meta( $post_id, '_admitad_category_locked' );
			delete_post_meta( $post_id, '_admitad_locked_term_ids' );
			return true;
		}
		if ( 'content' === $scope ) {
			delete_post_meta( $post_id, '_admitad_content_locked' );
			return true;
		}
		return new WP_Error( 'invalid_scope', 'Unknown lock scope.' );
	}

	/**
	 * Handle the settings form.
	 */
	public static function handle_save_settings(): void {
		$result = ( new self() )->save_settings(
			(array) wp_unslash( $_POST['settings'] ?? array() ),
			(array) wp_unslash( $_POST['credentials'] ?? array() ),
			sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) )
		);
		self::redirect_or_die( $result, 'admitad-settings' );
	}

	/**
	 * Refresh OAuth token without displaying it.
	 */
	public static function handle_refresh_token(): void {
		if (
			! current_user_can( 'manage_admitad_automation' )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ),
				'promokodiki_admitad_refresh_token'
			)
		) {
			wp_die( esc_html__( 'Недопустимый запрос.', 'promokodiki-admitad' ), '', array( 'response' => 403 ) );
		}
		$result = get_admitad_token( true );
		self::redirect_or_die( is_wp_error( $result ) ? $result : true, 'admitad-settings' );
	}

	/**
	 * Handle one lock reset.
	 */
	public static function handle_unlock_post(): void {
		$post_id = absint( $_POST['post_id'] ?? 0 );
		$result  = ( new self() )->unlock_post(
			$post_id,
			sanitize_key( wp_unslash( $_POST['scope'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) )
		);
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 403 ) );
		}
		wp_safe_redirect( get_edit_post_link( $post_id, 'raw' ) );
		exit;
	}

	/**
	 * Redirect after success or terminate on a validated failure.
	 *
	 * @param true|WP_Error $result Result.
	 * @param string        $page   Target page.
	 */
	private static function redirect_or_die( $result, string $page ): void {
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 403 ) );
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'     => 'promocode',
					'page'          => $page,
					'admitad_saved' => '1',
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}
}
