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
		$context = self::table_context( (array) $_GET ); $request = $context['request']; $history = $context['history'];
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

	/** Shared history list context with explicit edit.php query pagination. */
	public static function table_context( array $input ): array {
		$request = Promokodiki_Admitad_Admin_Request::from_array( $input, 'admitad-history' );
		$history = ( new Promokodiki_Admitad_Classification_History_Repository() )->list_rows( $request->search(), $request->paged(), $request->per_page(), array( 'snapshot_id' => $request->filter( 'snapshot' ) ) );
		foreach ( $history['items'] as &$row ) { $id = (int) $row['post_id']; $row['title'] = get_the_title( $id ); $row['view_url'] = get_permalink( $id ); $row['edit_url'] = get_edit_post_link( $id, 'raw' ); $row['previous_paths'] = implode( ', ', array_map( static fn( int $term ): string => Promokodiki_Admitad_Admin_Presenter::term_path( $term ), $row['previous_terms'] ) ); $row['result_paths'] = implode( ', ', array_map( static fn( int $term ): string => Promokodiki_Admitad_Admin_Presenter::term_path( $term ), $row['result_terms'] ) ); }
		unset( $row ); return array( 'request' => $request, 'history' => $history );
	}
}
