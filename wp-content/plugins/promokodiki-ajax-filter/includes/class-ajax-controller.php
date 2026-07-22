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
