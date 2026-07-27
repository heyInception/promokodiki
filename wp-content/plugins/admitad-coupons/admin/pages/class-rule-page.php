<?php
/**
 * Safe keyword rules page.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and renders the explicit phrase rule screen.
 */
final class Promokodiki_Admitad_Rule_Page {
	/**
	 * Render the page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_admitad_automation' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'promokodiki-admitad' ), '', array( 'response' => 403 ) );
		}
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
		$page   = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$rows   = ( new Promokodiki_Admitad_Rule_Repository() )->list_rows( $search, $page, 20 );
		$terms  = get_terms(
			array(
				'taxonomy'   => 'promocode_category',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		$terms  = is_wp_error( $terms ) ? array() : $terms;
		require ADMITAD_PLUGIN_DIR . 'admin/views/rules.php';
	}
}
