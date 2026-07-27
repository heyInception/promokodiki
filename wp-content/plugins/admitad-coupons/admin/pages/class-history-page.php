<?php
/**
 * Classification history and validation page.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the immutable history, preview, rollback, and validation screen.
 */
final class Promokodiki_Admitad_History_Page {
	/**
	 * Render the page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'review_admitad_mapping' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'promokodiki-admitad' ), '', array( 'response' => 403 ) );
		}
		$page     = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$history  = ( new Promokodiki_Admitad_Classification_History_Repository() )->list_rows( $page, 20 );
		$snapshot = null;
		if ( isset( $_GET['snapshot'] ) ) {
			$snapshot = ( new Promokodiki_Admitad_Reclassification_Service() )->get_snapshot( sanitize_text_field( wp_unslash( $_GET['snapshot'] ) ) );
		}
		$sample = null;
		$report = null;
		if ( isset( $_GET['sample'] ) ) {
			$validation = new Promokodiki_Admitad_Validation_Service();
			$sample     = $validation->sample( sanitize_text_field( wp_unslash( $_GET['sample'] ) ) );
			$report     = $sample ? $validation->report( (string) $sample['id'] ) : null;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'promocode_category',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		$terms = is_wp_error( $terms ) ? array() : $terms;
		require ADMITAD_PLUGIN_DIR . 'admin/views/history.php';
	}
}
