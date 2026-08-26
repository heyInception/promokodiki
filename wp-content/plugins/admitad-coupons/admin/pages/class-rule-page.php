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
		$context = self::table_context( (array) $_GET );
		$request = $context['request']; $rows = $context['rows']; $term_options = $context['term_options'];
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

	/** Shared list context for canonical GET and AJAX requests. */
	public static function table_context( array $input ): array {
		$request = Promokodiki_Admitad_Admin_Request::from_array( $input, 'admitad-rules' );
		$rows = ( new Promokodiki_Admitad_Rule_Repository() )->list_rows( $request->search(), $request->paged(), $request->per_page(), array( 'status' => $request->filter( 'status' ) ) );
		$terms = get_terms( array( 'taxonomy' => 'promocode_category', 'hide_empty' => false, 'orderby' => 'name' ) );
		return array( 'request' => $request, 'rows' => $rows, 'term_options' => Promokodiki_Admitad_Admin_Presenter::term_options( is_wp_error( $terms ) ? array() : $terms ) );
	}
}
