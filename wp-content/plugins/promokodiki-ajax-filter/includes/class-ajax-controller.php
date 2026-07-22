<?php
/**
 * Public AJAX endpoints.
 *
 * @package PromokodikiAjaxFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Promokodiki_Filter_Ajax_Controller {
	public static function results(): void {
		check_ajax_referer( 'promokodiki_filter_frontend', 'nonce' );

		$request = is_array( $_POST ) ? wp_unslash( $_POST ) : array();
		$payload = self::build_results_payload( $request );
		if ( is_wp_error( $payload ) ) {
			wp_send_json_error( array( 'message' => $payload->get_error_message() ), 400 );
		}

		wp_send_json_success( $payload );
	}

	public static function build_results_payload( array $request ): array|WP_Error {
		$type      = sanitize_key( (string) ( $request['context'] ?? '' ) );
		$object_id = absint( $request['object_id'] ?? 0 );
		$nonce     = sanitize_text_field( (string) ( $request['context_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'promokodiki_filter_context_' . $type . '_' . $object_id ) ) {
			return new WP_Error( 'invalid_filter_context_nonce', __( 'Filter context could not be verified.', 'promokodiki-ajax-filter' ) );
		}

		$context = Promokodiki_Filter_Context::resolve( $type, $object_id );
		if ( is_wp_error( $context ) ) {
			return $context;
		}

		$settings = Promokodiki_Filter_Settings::get();
		$state    = Promokodiki_Filter_State::from_request( $request, $settings, $type );
		$options  = Promokodiki_Filter_Option_Service::build( $context, $state );
		if ( is_wp_error( $options ) ) {
			return $options;
		}
		$state = $options['state'];

		$result = Promokodiki_Filter_Query_Service::run( $state, $context, $settings );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$html    = Promokodiki_Filter_Renderer::render_cards( $result['posts'] );
		$message = sprintf(
			/* translators: %d: number of matching promocodes. */
			_n( 'Найден %d промокод.', 'Найдено %d промокодов.', $result['total'], 'promokodiki-ajax-filter' ),
			$result['total']
		);
		if ( '' === $html ) {
			$empty   = ! empty( $state['popular'] ) ? $settings['weekly_empty_label'] : $settings['empty_label'];
			$html    = '<p class="no-promocodes">' . esc_html( $empty ) . '</p>';
			$message = $empty;
		}

		return array(
			'html'             => $html,
			'page'             => (int) $result['page'],
			'has_more'         => (bool) $result['has_more'],
			'total'            => (int) $result['total'],
			'message'          => $message,
			'state'            => array(
				'category' => (string) $state['category_id'],
				'brand'    => (string) $state['brand_id'],
				'sort'     => $state['sort'],
				'popular'  => (bool) $state['popular'],
			),
			'category_options' => $options['category_options'],
			'brand_options'    => $options['brand_options'],
		);
	}

	public static function track_click(): void {
		check_ajax_referer( 'promokodiki_filter_frontend', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		$result  = Promokodiki_Filter_Click_Stats::increment( $post_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'new_count' => $result ) );
	}
}
