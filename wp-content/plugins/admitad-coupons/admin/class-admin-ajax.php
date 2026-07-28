<?php
/**
 * Secure AJAX entry point for Admitad administration screens.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates and dispatches authenticated Admitad administration AJAX requests.
 */
final class Promokodiki_Admitad_Admin_Ajax {
	/**
	 * WordPress AJAX action name.
	 */
	private const ACTION = 'promokodiki_admitad_admin';

	/**
	 * AJAX nonce action name.
	 */
	private const NONCE_ACTION = 'promokodiki_admitad_admin_ajax';

	/**
	 * Maximum entries accepted in a request context or state array.
	 */
	private const MAX_ARRAY_ITEMS = 20;

	/**
	 * Maximum nested-array depth accepted from an AJAX request.
	 */
	private const MAX_ARRAY_DEPTH = 2;

	/**
	 * Maximum length of a sanitized scalar request value.
	 */
	private const MAX_SCALAR_LENGTH = 255;

	/**
	 * Register the authenticated AJAX route.
	 */
	public static function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( self::class, 'dispatch' ) );
	}

	/**
	 * Send an AJAX response for the current POST request.
	 */
	public static function dispatch(): void {
		if ( ! check_ajax_referer( self::NONCE_ACTION, '_ajax_nonce', false ) ) {
			self::send_error( new WP_Error( 'invalid_nonce', 'Недействительный запрос.' ) );
			return;
		}

		$result = self::handle( (array) $_POST );
		if ( is_wp_error( $result ) ) {
			self::send_error( $result );
			return;
		}

		wp_send_json_success( $result );
	}

	/**
	 * Process a request without emitting a JSON response.
	 *
	 * @param array<string, mixed> $request Request data.
	 * @return array<string, mixed>|WP_Error Response data or a stable error.
	 */
	public static function handle( array $request ) {
		$raw_request = $request;
		$request = self::sanitize_request( $request );
		if ( ! wp_verify_nonce( $request['_ajax_nonce'], self::NONCE_ACTION ) ) {
			return new WP_Error( 'invalid_nonce', 'Недействительный запрос.' );
		}
		if ( ! $request['valid'] ) {
			return new WP_Error( 'invalid_request', 'Некорректные данные запроса.' );
		}

		if ( in_array( $request['operation'], array( 'category_map_list', 'category_map_save', 'company_list', 'company_search', 'company_save' ), true ) ) {
			try {
				return self::handle_mapping_operation( $request['operation'], $request, $raw_request );
			} catch ( Throwable $error ) {
				self::log_failure( $request );
				return new WP_Error( 'server_error', 'Не удалось выполнить операцию. Повторите попытку.' );
			}
		}
		if ( in_array( $request['operation'], array( 'rule_list', 'rule_save', 'rule_archive', 'rule_restore', 'rule_status' ), true ) ) {
			try { return self::handle_rule_operation( $request['operation'], $request, $raw_request ); } catch ( Throwable $error ) { self::log_failure( $request ); return new WP_Error( 'server_error', 'Не удалось выполнить операцию. Повторите попытку.' ); }
		}
		if ( in_array( $request['operation'], array( 'review_list', 'review_resolve_coupon', 'review_archive' ), true ) ) {
			try { return self::handle_review_operation( $request['operation'], $request, $raw_request ); } catch ( Throwable $error ) { self::log_failure( $request ); return new WP_Error( 'server_error', 'Не удалось выполнить операцию. Повторите попытку.' ); }
		}
		if ( in_array( $request['operation'], array( 'history_list', 'history_snapshot', 'history_sample_review' ), true ) ) {
			try { return self::handle_history_operation( $request['operation'], $request, $raw_request ); } catch ( Throwable $error ) { self::log_failure( $request ); return new WP_Error( 'server_error', 'Не удалось выполнить операцию. Повторите попытку.' ); }
		}

		if ( 'render_fragment' !== $request['operation'] ) {
			return new WP_Error( 'invalid_operation', 'Неизвестная операция.' );
		}

		try {
			$fragment_page = Promokodiki_Admitad_Admin_Fragments::page( $request['fragment'] );
		} catch ( InvalidArgumentException $error ) {
			return new WP_Error( 'invalid_fragment', 'Неизвестный фрагмент.' );
		}

		try {
			$capabilities = Promokodiki_Admitad_Admin_Menu::section_capabilities();
			if ( ! isset( $capabilities[ $fragment_page ] ) ) {
				throw new RuntimeException( 'Unknown fragment page capability.' );
			}

			if ( $request['page'] !== $fragment_page || ! current_user_can( $capabilities[ $fragment_page ] ) ) {
				return new WP_Error( 'forbidden', 'Недостаточно прав для выполнения операции.' );
			}

			$html = Promokodiki_Admitad_Admin_Fragments::render( $request['fragment'], $request['context'] );
			$state = Promokodiki_Admitad_Admin_Request::from_array( $request['state'], $fragment_page );
			return array(
				'message' => 'Готово.',
				'html'    => $html,
				'url'     => $state->url(),
				'state'   => $state->query_args(),
			);
		} catch ( Throwable $error ) {
			self::log_failure( $request );
			return new WP_Error( 'server_error', 'Не удалось выполнить операцию. Повторите попытку.' );
		}
	}

	/** Handle keyword-rule changes through the shared action layer. */
	private static function handle_rule_operation( string $operation, array $request, array $raw_request ) {
		if ( 'admitad-rules' !== $request['page'] || ! current_user_can( 'manage_admitad_automation' ) ) { return new WP_Error( 'forbidden', 'Недостаточно прав для управления правилами.' ); }
		$actions = new Promokodiki_Admitad_Admin_Actions();
		if ( 'rule_save' === $operation ) { $result = $actions->save_rule( sanitize_text_field( self::raw_scalar( $raw_request, 'phrase' ) ), absint( self::raw_scalar( $raw_request, 'site_term_id' ) ), absint( self::raw_scalar( $raw_request, 'weight' ) ), sanitize_key( self::raw_scalar( $raw_request, 'status' ) ), sanitize_key( self::raw_scalar( $raw_request, 'mode' ) ) ); }
		elseif ( 'rule_archive' === $operation ) { $result = $actions->archive_rule( absint( self::raw_scalar( $raw_request, 'rule_id' ) ) ); }
		elseif ( 'rule_restore' === $operation ) { $result = $actions->restore_rule( absint( self::raw_scalar( $raw_request, 'rule_id' ) ) ); }
		elseif ( 'rule_status' === $operation ) { $result = $actions->set_rule_status( absint( self::raw_scalar( $raw_request, 'rule_id' ) ), sanitize_key( self::raw_scalar( $raw_request, 'status' ) ) ); }
		else { $result = true; }
		if ( is_wp_error( $result ) ) { return $result; }
		$context = Promokodiki_Admitad_Rule_Page::table_context( $raw_request );
		return array( 'message' => 'Готово.', 'html' => Promokodiki_Admitad_Admin_Fragments::render( 'rules-table', $context ), 'url' => $context['request']->url(), 'state' => $context['request']->query_args() );
	}

	/** Handle reviewer operations without widening editor access. */
	private static function handle_review_operation( string $operation, array $request, array $raw_request ) {
		if ( 'admitad-review' !== $request['page'] || ! current_user_can( 'review_admitad_mapping' ) ) { return new WP_Error( 'forbidden', 'Недостаточно прав для очереди проверки.' ); }
		$actions = new Promokodiki_Admitad_Admin_Actions();
		if ( 'review_resolve_coupon' === $operation ) { $result = $actions->resolve_coupon_only( absint( self::raw_scalar( $raw_request, 'queue_id' ) ), self::term_ids( $raw_request['term_ids'] ?? array() ) ); }
		elseif ( 'review_archive' === $operation ) { $result = ( new Promokodiki_Admitad_Review_Queue_Repository() )->archive( absint( self::raw_scalar( $raw_request, 'queue_id' ) ) ) ? true : new WP_Error( 'invalid_queue_item', 'Не удалось архивировать случай.' ); }
		else { $result = true; }
		if ( is_wp_error( $result ) ) { return $result; }
		$context = Promokodiki_Admitad_Review_Page::table_context( $raw_request );
		return array( 'message' => 'Готово.', 'html' => Promokodiki_Admitad_Admin_Fragments::render( 'review-table', $context ), 'url' => $context['request']->url(), 'state' => $context['request']->query_args() );
	}

	/** Render history results only; recovery preview/apply/rollback remains on admin-post. */
	private static function handle_history_operation( string $operation, array $request, array $raw_request ) {
		if ( 'admitad-history' !== $request['page'] || ! current_user_can( 'review_admitad_mapping' ) ) { return new WP_Error( 'forbidden', 'Недостаточно прав для истории классификации.' ); }
		if ( 'history_sample_review' === $operation && current_user_can( 'manage_admitad_automation' ) ) { $sample = sanitize_text_field( self::raw_scalar( $raw_request, 'sample_id' ) ); ( new Promokodiki_Admitad_Validation_Service() )->record_review( $sample, absint( self::raw_scalar( $raw_request, 'post_id' ) ), self::term_ids( $raw_request['expected_terms'] ?? array() ) ); }
		if ( 'history_snapshot' === $operation ) { $snapshot = ( new Promokodiki_Admitad_Reclassification_Service() )->get_snapshot( sanitize_text_field( self::raw_scalar( $raw_request, 'snapshot' ) ) ); return array( 'message' => 'Готово.', 'html' => Promokodiki_Admitad_Admin_Fragments::render( 'history-snapshot', array( 'snapshot' => $snapshot ) ) ); }
		$context = Promokodiki_Admitad_History_Page::table_context( $raw_request );
		return array( 'message' => 'Готово.', 'html' => Promokodiki_Admitad_Admin_Fragments::render( 'history-table', $context ), 'url' => $context['request']->url(), 'state' => $context['request']->query_args() );
	}

	/**
	 * Handle the administrator-only mapping and company operations.
	 *
	 * @param string              $operation   Allowlisted operation.
	 * @param array<string,mixed> $request     Sanitized common request values.
	 * @param array<string,mixed> $raw_request Raw request values, sanitized below by field.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function handle_mapping_operation( string $operation, array $request, array $raw_request ) {
		if ( ! current_user_can( 'manage_admitad_automation' ) ) {
			return new WP_Error( 'forbidden', 'Недостаточно прав для управления маппингом Admitad.' );
		}

		if ( 'category_map_list' === $operation || 'category_map_save' === $operation ) {
			$page_class = Promokodiki_Admitad_Category_Map_Page::class;
			$page       = 'admitad-category-map';
			$fragment   = 'category-map-table';
		} else {
			$page_class = Promokodiki_Admitad_Company_Page::class;
			$page       = 'admitad-companies';
			$fragment   = 'company-table';
		}
		if ( $request['page'] !== $page ) {
			return new WP_Error( 'forbidden', 'Недостаточно прав для выполнения операции.' );
		}

		$actions = new Promokodiki_Admitad_Admin_Actions();
		if ( 'category_map_save' === $operation ) {
			$result = $actions->create_global_category_map(
				sanitize_key( self::raw_scalar( $raw_request, 'namespace' ) ),
				absint( self::raw_scalar( $raw_request, 'external_id' ) ),
				absint( self::raw_scalar( $raw_request, 'site_term_id' ) ),
				absint( self::raw_scalar( $raw_request, 'weight' ) )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		if ( 'company_save' === $operation ) {
			$result = $actions->save_company_profile(
				absint( self::raw_scalar( $raw_request, 'campaign_id' ) ),
				absint( self::raw_scalar( $raw_request, 'default_term_id' ) ),
				self::term_ids( $raw_request['allowed_term_ids'] ?? array() ),
				absint( self::raw_scalar( $raw_request, 'weight' ) ),
				sanitize_text_field( self::raw_scalar( $raw_request, 'display_name' ) )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		if ( 'company_search' === $operation ) {
			$items = ( new Promokodiki_Admitad_Company_Profile_Repository() )->search_campaigns(
				sanitize_text_field( self::raw_scalar( $raw_request, 's' ) ),
				20
			);
			return array(
				'message' => 'Готово.',
				'items'   => array_map( static fn( array $item ): array => array( 'id' => (int) $item['campaign_id'], 'text' => (string) $item['display_name'] ), $items ),
			);
		}

		$context = $page_class::table_context( $raw_request );
		return array(
			'message' => 'Готово.',
			'html'    => Promokodiki_Admitad_Admin_Fragments::render( $fragment, $context ),
			'url'     => $context['request']->url(),
			'state'   => $context['request']->query_args(),
		);
	}

	/**
	 * Read one scalar raw request field safely.
	 *
	 * @param array<string,mixed> $request Request data.
	 * @param string              $key     Field key.
	 * @return string
	 */
	private static function raw_scalar( array $request, string $key ): string {
		return isset( $request[ $key ] ) && is_scalar( $request[ $key ] ) ? (string) wp_unslash( $request[ $key ] ) : '';
	}

	/**
	 * Return at most twenty integer term IDs from one request field.
	 *
	 * @param mixed $value Raw field value.
	 * @return array<int,int>
	 */
	private static function term_ids( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		return array_values( array_unique( array_map( 'absint', array_slice( $value, 0, self::MAX_ARRAY_ITEMS ) ) ) );
	}

	/**
	 * Return a safe, deliberately sanitised request shape.
	 *
	 * @param array<mixed> $request Raw request data.
	 * @return array{operation:string,page:string,fragment:string,_ajax_nonce:string,context:array<string,mixed>,state:array<string,mixed>,valid:bool}
	 */
	private static function sanitize_request( array $request ): array {
		$context = isset( $request['context'] ) && is_array( $request['context'] )
			? self::sanitize_array( $request['context'] )
			: array( 'value' => array(), 'valid' => ! isset( $request['context'] ) );
		return array(
			'operation'   => self::key_value( $request, 'operation' ),
			'page'        => self::key_value( $request, 'page' ),
			'fragment'    => self::key_value( $request, 'fragment' ),
			'_ajax_nonce' => self::text_value( $request, '_ajax_nonce' ),
			'context'     => $context['value'],
			'state'       => self::flat_state( $request ),
			'valid'       => $context['valid'],
		);
	}

	/**
	 * Extract canonical navigation fields from the ordinary flat request shape.
	 *
	 * Forms retain their no-JavaScript GET names, so AJAX deliberately accepts
	 * those same top-level scalar fields instead of a separate state[] protocol.
	 *
	 * @param array<mixed> $request Raw request data.
	 * @return array<string,mixed> Flat state candidates.
	 */
	private static function flat_state( array $request ): array {
		$state = array();
		foreach ( $request as $key => $value ) {
			if ( ! is_scalar( $key ) || in_array( (string) $key, array( 'action', 'operation', 'page', 'fragment', '_ajax_nonce', 'context', 'state' ), true ) || ! is_scalar( $value ) ) {
				continue;
			}
			$state[ sanitize_key( (string) $key ) ] = self::limit_scalar( sanitize_text_field( wp_unslash( (string) $value ) ) );
		}

		return $state;
	}

	/**
	 * Sanitize a bounded nested request array.
	 *
	 * @param array<mixed> $input Input array.
	 * @param int          $depth Current nesting depth.
	 * @return array{value:array<string,mixed>,valid:bool} Sanitised bounded array.
	 */
	private static function sanitize_array( array $input, int $depth = 0 ): array {
		if ( $depth >= self::MAX_ARRAY_DEPTH || count( $input ) > self::MAX_ARRAY_ITEMS ) {
			return array( 'value' => array(), 'valid' => false );
		}

		$output = array();
		foreach ( $input as $key => $value ) {
			if ( ! is_scalar( $key ) ) {
				continue;
			}
			$key = sanitize_key( (string) $key );
			if ( '' === $key ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$nested = self::sanitize_array( $value, $depth + 1 );
				if ( ! $nested['valid'] ) {
					return array( 'value' => array(), 'valid' => false );
				}
				$output[ $key ] = $nested['value'];
			} elseif ( is_scalar( $value ) ) {
				$output[ $key ] = self::limit_scalar( sanitize_text_field( wp_unslash( (string) $value ) ) );
			}
		}

		return array( 'value' => $output, 'valid' => true );
	}

	/**
	 * Read a scalar key-style request value.
	 *
	 * @param array<mixed> $request Request input.
	 * @param string       $key     Request key.
	 * @return string Sanitised value.
	 */
	private static function key_value( array $request, string $key ): string {
		return sanitize_key( self::text_value( $request, $key ) );
	}

	/**
	 * Read a scalar text request value.
	 *
	 * @param array<mixed> $request Request input.
	 * @param string       $key     Request key.
	 * @return string Sanitised value.
	 */
	private static function text_value( array $request, string $key ): string {
		if ( ! isset( $request[ $key ] ) || ! is_scalar( $request[ $key ] ) ) {
			return '';
		}

		return self::limit_scalar( sanitize_text_field( wp_unslash( (string) $request[ $key ] ) ) );
	}

	/**
	 * Limit a scalar's byte length after sanitization.
	 *
	 * @param string $value Sanitized scalar value.
	 * @return string Bounded value.
	 */
	private static function limit_scalar( string $value ): string {
		return substr( $value, 0, self::MAX_SCALAR_LENGTH );
	}

	/**
	 * Send a stable error payload with a safe HTTP status.
	 *
	 * @param WP_Error $error Response error.
	 */
	private static function send_error( WP_Error $error ): void {
		$code = $error->get_error_code();
		wp_send_json_error(
			array(
				'message' => $error->get_error_message(),
				'code'    => $code,
			),
			self::error_status( $code )
		);
	}

	/**
	 * Map stable error codes to safe HTTP statuses.
	 *
	 * @param string $code Error code.
	 * @return int HTTP status.
	 */
	private static function error_status( string $code ): int {
		if ( 'forbidden' === $code ) {
			return 403;
		}
		if ( 'server_error' === $code ) {
			return 500;
		}
		return 400;
	}

	/**
	 * Log only safe request identifiers, without exception details or payloads.
	 *
	 * @param array{operation:string,page:string,fragment:string,_ajax_nonce:string,context:array<string,mixed>,state:array<string,mixed>,valid:bool} $request Sanitised request.
	 */
	private static function log_failure( array $request ): void {
		error_log(
			'Promokodiki Admitad AJAX request failed: ' . wp_json_encode(
				array(
					'operation' => $request['operation'],
					'page'      => $request['page'],
					'fragment'  => $request['fragment'],
				)
			)
		);
	}
}
