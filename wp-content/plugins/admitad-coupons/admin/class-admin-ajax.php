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
		$request = self::sanitize_request( $request );
		if ( ! wp_verify_nonce( $request['_ajax_nonce'], self::NONCE_ACTION ) ) {
			return new WP_Error( 'invalid_nonce', 'Недействительный запрос.' );
		}
		if ( ! $request['valid'] ) {
			return new WP_Error( 'invalid_request', 'Некорректные данные запроса.' );
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
