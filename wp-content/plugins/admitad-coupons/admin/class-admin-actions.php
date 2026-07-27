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
		add_action( 'admin_post_promokodiki_admitad_operation', array( self::class, 'handle_operation' ) );
		add_action( 'admin_post_promokodiki_admitad_mapping_action', array( self::class, 'handle_mapping_action' ) );
	}

	/**
	 * Resolve a queue item by assigning and locking one coupon only.
	 *
	 * @param int             $queue_id Queue row ID.
	 * @param array<int, int> $term_ids Existing site category IDs.
	 * @return true|WP_Error
	 */
	public function resolve_coupon_only( int $queue_id, array $term_ids ) {
		if (
			! current_user_can( 'review_admitad_mapping' )
			|| ( ! current_user_can( 'manage_admitad_automation' ) && ! Promokodiki_Admitad_Config::get( 'editor_review_enabled' ) )
		) {
			return new WP_Error( 'forbidden', 'You cannot resolve Admitad review cases.' );
		}
		$queue = new Promokodiki_Admitad_Review_Queue_Repository();
		$item  = $queue->get_open( $queue_id );
		if ( ! $item || 'coupon' !== $item['entity_type'] ) {
			return new WP_Error( 'invalid_queue_item', 'An open coupon review case is required.' );
		}
		$term_ids = array_values( array_unique( array_map( 'absint', $term_ids ) ) );
		$term_ids = array_slice( $term_ids, 0, 3 );
		foreach ( $term_ids as $term_id ) {
			if ( ! get_term( $term_id, 'promocode_category' ) instanceof WP_Term ) {
				return new WP_Error( 'invalid_term', 'Every selected category must already exist.' );
			}
		}
		if ( array() === $term_ids ) {
			return new WP_Error( 'invalid_term', 'Select at least one existing category.' );
		}
		$posts = get_posts(
			array(
				'post_type'      => 'promocode',
				'post_status'    => 'any',
				'posts_per_page' => 2,
				'fields'         => 'ids',
				'meta_key'       => 'admitad_coupon_id',
				'meta_value'     => (string) $item['entity_id'],
			)
		);
		if ( 1 !== count( $posts ) ) {
			return new WP_Error( 'coupon_not_unique', 'Exactly one coupon must match the queue entity.' );
		}
		$post_id = (int) $posts[0];
		$result  = wp_set_post_terms( $post_id, $term_ids, 'promocode_category', false );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		update_post_meta( $post_id, '_admitad_primary_term_id', $term_ids[0] );
		update_post_meta( $post_id, '_admitad_category_locked', 'yes' );
		update_post_meta( $post_id, '_admitad_locked_term_ids', $term_ids );
		return $queue->resolve( $queue_id, 'coupon_only' )
			? true
			: new WP_Error( 'queue_update_failed', 'The coupon was changed but the queue case could not be resolved.' );
	}

	/**
	 * Create one administrator-only stable external category map.
	 *
	 * @param string $namespace  coupon or campaign.
	 * @param int    $external_id External category ID.
	 * @param int    $term_id     Existing site term ID.
	 * @param int    $weight      Signal weight.
	 * @return true|WP_Error
	 */
	public function create_global_category_map( string $namespace, int $external_id, int $term_id, int $weight = 100 ) {
		if ( ! current_user_can( 'manage_admitad_automation' ) ) {
			return new WP_Error( 'forbidden', 'You cannot change global Admitad mappings.' );
		}
		try {
			( new Promokodiki_Admitad_Category_Map_Repository() )->save( $namespace, $external_id, $term_id, $weight );
		} catch ( Throwable $error ) {
			return new WP_Error( 'invalid_mapping', $error->getMessage() );
		}
		return true;
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
	 * Run a bounded operational action.
	 *
	 * @param string $operation Operation key.
	 * @param string $nonce     Request nonce.
	 * @return true|int|WP_Error
	 */
	public function run_operation( string $operation, string $nonce ) {
		if ( ! current_user_can( 'manage_admitad_automation' ) ) {
			return new WP_Error( 'forbidden', 'You cannot run Admitad operations.' );
		}
		if ( ! wp_verify_nonce( $nonce, 'promokodiki_admitad_operation' ) ) {
			return new WP_Error( 'invalid_nonce', 'Invalid operation nonce.' );
		}
		$operation = sanitize_key( $operation );
		if ( 'coupon_sync' === $operation ) {
			return ( new Promokodiki_Admitad_Sync_Coordinator() )->start_coupon_sync();
		}
		if ( 'reference_sync' === $operation ) {
			return ( new Promokodiki_Admitad_Sync_Coordinator() )->start_reference_sync();
		}
		if ( 'reconcile' === $operation ) {
			Promokodiki_Admitad_Plugin::handle_reconcile();
			return true;
		}
		if ( in_array( $operation, array( 'recover_coupon_lock', 'recover_reference_lock' ), true ) ) {
			$job = str_contains( $operation, 'coupon' ) ? 'coupon' : 'reference';
			return ( new Promokodiki_Admitad_Job_Lock() )->recover_stale( $job )
				? true
				: new WP_Error( 'lock_not_stale', 'The selected lock is not stale.' );
		}
		if ( 'test_email' === $operation ) {
			$recipient = (string) Promokodiki_Admitad_Config::get( 'email_recipient' );
			return wp_mail( $recipient, '[Promokodiki] Admitad test', 'Admitad automation email notifications are working.' )
				? true
				: new WP_Error( 'mail_failed', 'WordPress could not send the test email.' );
		}
		return new WP_Error( 'invalid_operation', 'Unknown Admitad operation.' );
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
	 * Handle a manual operational action.
	 */
	public static function handle_operation(): void {
		$result = ( new self() )->run_operation(
			sanitize_key( wp_unslash( $_POST['operation'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) )
		);
		self::redirect_or_die( $result, 'admitad-sync' );
	}

	/**
	 * Handle all mapping administration forms.
	 */
	public static function handle_mapping_action(): void {
		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'promokodiki_admitad_mapping_action' ) ) {
			wp_die( esc_html__( 'Недопустимый запрос.', 'promokodiki-admitad' ), '', array( 'response' => 403 ) );
		}
		$operation = sanitize_key( wp_unslash( $_POST['operation'] ?? '' ) );
		$actions   = new self();
		$page      = 'admitad-review';
		$result    = new WP_Error( 'invalid_operation', 'Unknown mapping operation.' );

		if ( 'save_category_map' === $operation ) {
			$page   = 'admitad-category-map';
			$result = $actions->create_global_category_map(
				sanitize_key( wp_unslash( $_POST['namespace'] ?? '' ) ),
				absint( $_POST['external_id'] ?? 0 ),
				absint( $_POST['site_term_id'] ?? 0 ),
				absint( $_POST['weight'] ?? 100 )
			);
		} elseif ( 'resolve_coupon_only' === $operation ) {
			$result = $actions->resolve_coupon_only(
				absint( $_POST['queue_id'] ?? 0 ),
				array_map( 'absint', (array) wp_unslash( $_POST['term_ids'] ?? array() ) )
			);
		} elseif ( 'save_company' === $operation ) {
			$page   = 'admitad-companies';
			$result = $actions->save_company_profile(
				absint( $_POST['campaign_id'] ?? 0 ),
				absint( $_POST['default_term_id'] ?? 0 ),
				array_map( 'absint', (array) wp_unslash( $_POST['allowed_term_ids'] ?? array() ) ),
				absint( $_POST['weight'] ?? 40 ),
				sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) )
			);
		} elseif ( 'save_rule' === $operation ) {
			$page   = 'admitad-rules';
			$result = $actions->save_rule(
				sanitize_text_field( wp_unslash( $_POST['phrase'] ?? '' ) ),
				absint( $_POST['site_term_id'] ?? 0 ),
				absint( $_POST['weight'] ?? 20 ),
				sanitize_key( wp_unslash( $_POST['status'] ?? 'candidate' ) ),
				sanitize_key( wp_unslash( $_POST['mode'] ?? 'phrase' ) )
			);
		} elseif ( 'set_rule_status' === $operation ) {
			$page   = 'admitad-rules';
			$result = $actions->set_rule_status(
				absint( $_POST['rule_id'] ?? 0 ),
				sanitize_key( wp_unslash( $_POST['status'] ?? '' ) )
			);
		}
		self::redirect_or_die( $result, $page );
	}

	/**
	 * Save one company classification profile.
	 *
	 * @param int             $campaign_id     Campaign ID.
	 * @param int             $default_term_id Default term.
	 * @param array<int, int> $allowed_term_ids Allowed terms.
	 * @param int             $weight          Weight.
	 * @param string          $display_name    Display name.
	 * @return true|WP_Error
	 */
	public function save_company_profile( int $campaign_id, int $default_term_id, array $allowed_term_ids, int $weight, string $display_name ) {
		if ( ! current_user_can( 'manage_admitad_automation' ) ) {
			return new WP_Error( 'forbidden', 'You cannot change company profiles.' );
		}
		try {
			( new Promokodiki_Admitad_Company_Profile_Repository() )->save_profile( $campaign_id, $default_term_id, $allowed_term_ids, $weight, $display_name );
		} catch ( Throwable $error ) {
			return new WP_Error( 'invalid_company_profile', $error->getMessage() );
		}
		return true;
	}

	/**
	 * Save an explicit phrase rule.
	 *
	 * @param string $phrase  Phrase.
	 * @param int    $term_id Term ID.
	 * @param int    $weight  Weight.
	 * @param string $status  Status.
	 * @param string $mode    Match mode.
	 * @return true|WP_Error
	 */
	public function save_rule( string $phrase, int $term_id, int $weight, string $status, string $mode ) {
		if ( ! current_user_can( 'manage_admitad_automation' ) ) {
			return new WP_Error( 'forbidden', 'You cannot change phrase rules.' );
		}
		try {
			( new Promokodiki_Admitad_Rule_Repository() )->save( $phrase, $term_id, $weight, $status, $mode, 'editorial' );
		} catch ( Throwable $error ) {
			return new WP_Error( 'invalid_rule', $error->getMessage() );
		}
		return true;
	}

	/**
	 * Change one rule status.
	 *
	 * @param int    $rule_id Rule ID.
	 * @param string $status  Status.
	 * @return true|WP_Error
	 */
	public function set_rule_status( int $rule_id, string $status ) {
		if ( ! current_user_can( 'manage_admitad_automation' ) ) {
			return new WP_Error( 'forbidden', 'You cannot change phrase rules.' );
		}
		return ( new Promokodiki_Admitad_Rule_Repository() )->set_status( $rule_id, $status )
			? true
			: new WP_Error( 'invalid_rule_status', 'Unable to change the rule status.' );
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
