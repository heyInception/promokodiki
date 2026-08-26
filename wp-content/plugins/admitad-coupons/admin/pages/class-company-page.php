<?php
/**
 * Company classification profiles page.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and renders the company profile screen.
 */
final class Promokodiki_Admitad_Company_Page {
	/**
	 * Render the page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_admitad_automation' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'promokodiki-admitad' ), '', array( 'response' => 403 ) );
		}
		$context = self::table_context( (array) $_GET );
		$request = $context['request'];
		$rows = $context['rows'];
		$term_options = $context['term_options'];
		require ADMITAD_PLUGIN_DIR . 'admin/views/companies.php';
	}

	/**
	 * Build the bounded view state used by both GET and AJAX rendering.
	 *
	 * @param array<mixed> $input Query or AJAX request input.
	 * @return array{request:Promokodiki_Admitad_Admin_Request,rows:array<string,mixed>,term_options:array<int,array{id:int,label:string}>}
	 */
	public static function table_context( array $input ): array {
		$request = Promokodiki_Admitad_Admin_Request::from_array( $input, 'admitad-companies' );
		$rows = ( new Promokodiki_Admitad_Company_Profile_Repository() )->list_rows(
			$request->search(),
			$request->paged(),
			$request->per_page(),
			array( 'status' => $request->filter( 'status' ) )
		);
		$terms  = get_terms(
			array(
				'taxonomy'   => 'promocode_category',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		$terms  = is_wp_error( $terms ) ? array() : $terms;
		return array(
			'request'      => $request,
			'rows'         => $rows,
			'term_options' => Promokodiki_Admitad_Admin_Presenter::term_options( $terms ),
		);
	}
}
