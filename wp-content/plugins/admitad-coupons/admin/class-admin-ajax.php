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

		$result = self::handle( self::sanitize_request( (array) wp_unslash( $_POST ) ) );
		if ( is_wp_error( $result ) ) {
			self::send_error( $result );
			return;
		}

		wp_send_json_success( $result );
	}

	/**
	 * Process a sanitised request without emitting a JSON response.
	 *
	 * @param array<string, mixed> $request Request data.
	 * @return array<string, mixed>|WP_Error Response data or a stable error.
	 */
	public static function handle( array $request ) {
		$request = self::sanitize_request( $request );
		if ( ! wp_verify_nonce( $request['_ajax_nonce'], self::NONCE_ACTION ) ) {
			return new WP_Error( 'invalid_nonce', 'Недействительный запрос.' );
		}

		if ( 'render_fragment' !== $request['operation'] ) {
			return new WP_Error( 'invalid_operation', 'Неизвестная операция.' );
		}

		$capabilities = Promokodiki_Admitad_Admin_Menu::section_capabilities();
		if ( ! isset( $capabilities[ $request['page'] ] ) ) {
			return new WP_Error( 'invalid_operation', 'Неизвестная операция.' );
		}

		if ( ! current_user_can( $capabilities[ $request['page'] ] ) ) {
			return new WP_Error( 'forbidden', 'Недостаточно прав для выполнения операции.' );
		}

		try {
			$html = Promokodiki_Admitad_Admin_Fragments::render( $request['fragment'], $request['context'] );
		} catch ( InvalidArgumentException $error ) {
			return new WP_Error( 'invalid_fragment', 'Неизвестный фрагмент.' );
		} catch ( Throwable $error ) {
			self::log_failure( $request );
			return new WP_Error( 'server_error', 'Не удалось выполнить операцию. Повторите попытку.' );
		}

		$state = Promokodiki_Admitad_Admin_Request::from_array( $request['state'], $request['page'] );
		return array(
			'message' => 'Готово.',
			'html'    => $html,
			'url'     => $state->url(),
			'state'   => $state->query_args(),
		);
	}

	/**
	 * Return a safe, deliberately sanitised request shape.
	 *
	 * @param array<mixed> $request Raw request data.
	 * @return array{operation:string,page:string,fragment:string,_ajax_nonce:string,context:array<string,mixed>,state:array<string,mixed>}
	 */
	private static function sanitize_request( array $request ): array {
		$context = isset( $request['context'] ) && is_array( $request['context'] )
			? self::sanitize_array( $request['context'] )
			: array();
		$state   = isset( $request['state'] ) && is_array( $request['state'] )
			? self::sanitize_array( $request['state'] )
			: array();

		return array(
			'operation'   => self::key_value( $request, 'operation' ),
			'page'        => self::key_value( $request, 'page' ),
			'fragment'    => self::key_value( $request, 'fragment' ),
			'_ajax_nonce' => self::text_value( $request, '_ajax_nonce' ),
			'context'     => $context,
			'state'       => $state,
		);
	}

	/**
	 * Sanitize a bounded nested request array.
	 *
	 * @param array<mixed> $input Input array.
	 * @param int          $depth Current nesting depth.
	 * @return array<string,mixed> Sanitised array.
	 */
	private static function sanitize_array( array $input, int $depth = 0 ): array {
		if ( $depth >= 3 ) {
			return array();
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
				$output[ $key ] = self::sanitize_array( $value, $depth + 1 );
			} elseif ( is_scalar( $value ) ) {
				$output[ $key ] = sanitize_text_field( wp_unslash( (string) $value ) );
			}
		}

		return $output;
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

		return sanitize_text_field( wp_unslash( (string) $request[ $key ] ) );
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
	 * @param array{operation:string,page:string,fragment:string,_ajax_nonce:string,context:array<string,mixed>,state:array<string,mixed>} $request Sanitised request.
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
