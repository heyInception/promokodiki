<?php
/**
 * Plugin logger.
 *
 * @package DeleteDuplicatePosts
 */

namespace DeleteDuplicatePosts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class DDP_Logger {

	/**
	 * Returns log lines via AJAX.
	 *
	 * @author  Lars Koudal
	 * @author  Unknown
	 * @since   v0.0.1
	 * @version v1.0.0  Tuesday, January 12th, 2021.
	 * @version v1.0.1  Wednesday, November 1st, 2023.
	 * @access  public static
	 * @param   boolean $return Default: false
	 * @return  void|array
	 */

	public static function return_loglines_ajax( $return_data = false ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have sufficient permissions to perform this action.', 'delete-duplicate-posts' ) );
			return;
		}

		check_ajax_referer( 'cp_ddp_return_loglines' );

		$currstep = filter_input( INPUT_POST, 'step', FILTER_SANITIZE_NUMBER_INT ) ?: 0;
		++$currstep;

		$json_response = array( 'step' => $currstep );

		global $wpdb;
		$loglines = $wpdb->get_results( "SELECT datime, note FROM {$wpdb->prefix}ddp_log ORDER BY datime DESC LIMIT 100;" );

		if ( ! empty( $loglines ) ) {
			// Escape here because the admin JS injects these values as HTML
			// (jQuery .append). Log notes can contain post titles authored by
			// lower-privileged users, so treat them as untrusted.
			foreach ( $loglines as $line ) {
				$line->datime = esc_html( $line->datime );
				$line->note   = esc_html( $line->note );
			}
			$json_response['results'] = $loglines;
		} else {
			$json_response['msg'] = __( 'Error: Log is empty.. do something :-)', 'delete-duplicate-posts' );
			if ( $return_data ) {
				return $json_response;
			}
			wp_send_json_error( $json_response );
			return; // Make sure to exit here to prevent sending multiple responses.
		}

		if ( $return_data ) {
			return $json_response;
		}
		wp_send_json_success( $json_response );
	}


	/**
	 * timerstart.
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Thursday, June 9th, 2022.
	 * @access  public static
	 * @param   mixed   $watchname
	 * @return  void
	 */
	public static function timerstart( $watchname ) {
		set_transient( 'ddp_' . $watchname, microtime( true ), 60 * 60 * 1 );
	}


	/**
	 * timerstop.
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Thursday, June 9th, 2022.
	 * @access  public static
	 * @param   mixed   $watchname
	 * @param   integer $digits     Default: 3
	 * @return  mixed
	 */
	public static function timerstop( $watchname, $digits = 3 ) {
		$return = round( microtime( true ) - get_transient( 'ddp_' . $watchname ), $digits );
		delete_transient( 'ddp_' . $watchname );
		return $return;
	}


	/**
	 * Log a notification to the database
	 *
	 * @author   Lars Koudal
	 * @since    v0.0.1
	 * @version  v1.0.0  Monday, January 11th, 2021.
	 * @access   public static
	 * @param    mixed   $text
	 * @return   void
	 */
	public static function log( $text ) {
		global $wpdb;
		$ddp_logtable = $wpdb->prefix . 'ddp_log';

		// Insert log entry
		$insert_result = $wpdb->insert(
			$ddp_logtable,
			array(
				'datime' => current_time( 'mysql' ),
				'note'   => $text,
			),
			array( '%s', '%s' )
		);

		if ( false === $insert_result ) {
			// Handle error appropriately (e.g., log or throw exception)
			return;
		}

		// Efficiently check if row count exceeds 1000 and delete old entries
		$row_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$ddp_logtable};" );
		if ( $row_count > 1000 ) {
			$wpdb->query(
				"DELETE FROM {$ddp_logtable} WHERE id NOT IN (
																							SELECT id FROM (
																								SELECT id FROM {$ddp_logtable} ORDER BY datime DESC LIMIT 500
																								) AS sub
																								)"
			);
		}
	}

}
