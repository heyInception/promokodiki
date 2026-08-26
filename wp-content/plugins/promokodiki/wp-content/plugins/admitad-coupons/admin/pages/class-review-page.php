<?php
/**
 * Manual review queue page.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and renders the explainable review queue.
 */
final class Promokodiki_Admitad_Review_Page {
	/**
	 * Render the page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'review_admitad_mapping' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'promokodiki-admitad' ), '', array( 'response' => 403 ) );
		}
		$context = self::table_context( (array) $_GET ); $request = $context['request']; $rows = $context['rows']; $term_options = $context['term_options']; $totals = $context['totals'];
		$terms  = get_terms(
			array(
				'taxonomy'   => 'promocode_category',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		$terms  = is_wp_error( $terms ) ? array() : $terms;
		require ADMITAD_PLUGIN_DIR . 'admin/views/review.php';
	}

	/** Build safe detailed queue rows, resolving at most two coupon posts. */
	public static function table_context( array $input ): array {
		$request = Promokodiki_Admitad_Admin_Request::from_array( $input, 'admitad-review' );
		$groups = array( 'low_confidence' => array( 'low_confidence' ), 'conflicts' => array( 'conflict', 'rule_conflict' ), 'unknown_category' => array( 'unmapped_category', 'missing_mapping' ), 'missing_company' => array( 'missing_campaign_id', 'missing_company_profile' ), 'duplicates' => array( 'suspected_duplicate' ) );
		$reason = $request->filter( 'reason' ); $filters = array( 'status' => $request->filter( 'status' ) );
		if ( isset( $groups[ $reason ] ) ) { $filters['reasons'] = $groups[ $reason ]; }
		$repository = new Promokodiki_Admitad_Review_Queue_Repository(); $rows = $repository->list_rows( $request->search(), $request->paged(), $request->per_page(), $filters );
		foreach ( $rows['items'] as &$row ) { $row['post_id'] = 0; $row['title'] = '—'; $row['company'] = '—'; $row['term_paths'] = '—'; $row['view_url'] = ''; $row['edit_url'] = ''; $row['confidence'] = (string) ( $row['evidence']['confidence'] ?? $row['severity'] ); if ( 'coupon' === $row['entity_type'] ) { $posts = get_posts( array( 'post_type' => 'promocode', 'post_status' => 'any', 'posts_per_page' => 2, 'fields' => 'ids', 'meta_key' => 'admitad_coupon_id', 'meta_value' => (string) $row['entity_id'] ) ); if ( 1 === count( $posts ) ) { $id = (int) $posts[0]; $row['post_id'] = $id; $row['title'] = get_the_title( $id ); $row['company'] = (string) get_post_meta( $id, 'company', true ); $row['term_paths'] = implode( ', ', array_map( static fn( int $term ): string => Promokodiki_Admitad_Admin_Presenter::term_path( $term ), wp_get_post_terms( $id, 'promocode_category', array( 'fields' => 'ids' ) ) ) ); $row['view_url'] = get_permalink( $id ); $row['edit_url'] = get_edit_post_link( $id, 'raw' ); } } }
		unset( $row ); $terms = get_terms( array( 'taxonomy' => 'promocode_category', 'hide_empty' => false, 'orderby' => 'name' ) ); $totals = array(); foreach ( $groups as $key => $codes ) { $totals[ $key ] = $repository->list_rows( '', 1, 20, array( 'status' => 'open', 'reasons' => $codes ) )['total']; }
		return array( 'request' => $request, 'rows' => $rows, 'term_options' => Promokodiki_Admitad_Admin_Presenter::term_options( is_wp_error( $terms ) ? array() : $terms ), 'totals' => $totals );
	}
}
