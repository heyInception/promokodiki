<?php
/**
 * Safe category assignment service.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies classification results while honoring category locks.
 */
final class Promokodiki_Admitad_Assignment_Service {
	/**
	 * History repository.
	 *
	 * @var Promokodiki_Admitad_Classification_History_Repository
	 */
	private Promokodiki_Admitad_Classification_History_Repository $history;

	/**
	 * Constructor.
	 *
	 * @param Promokodiki_Admitad_Classification_History_Repository|null $history History repository.
	 */
	public function __construct( ?Promokodiki_Admitad_Classification_History_Repository $history = null ) {
		$this->history = $history ?? new Promokodiki_Admitad_Classification_History_Repository();
	}

	/**
	 * Apply a classification result.
	 *
	 * @param int                                       $post_id Post ID.
	 * @param Promokodiki_Admitad_Classification_Result $result  Classification.
	 * @param string                                    $trigger Trigger name.
	 */
	public function assign( int $post_id, Promokodiki_Admitad_Classification_Result $result, string $trigger ): bool {
		if ( Promokodiki_Admitad_Editorial_Locks::category_locked( $post_id ) || 'promocode' !== get_post_type( $post_id ) ) {
			return false;
		}
		$previous_terms   = array_map( 'intval', wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'ids' ) ) );
		$previous_primary = (int) get_post_meta( $post_id, '_admitad_primary_term_id', true );
		$assigned         = Promokodiki_Admitad_Import_Context::run(
			static fn() => wp_set_post_terms( $post_id, $result->term_ids(), 'promocode_category', false )
		);
		if ( is_wp_error( $assigned ) ) {
			return false;
		}
		update_post_meta( $post_id, '_admitad_primary_term_id', $result->primary_term_id() );
		update_post_meta( $post_id, '_admitad_classification_confidence', $result->confidence() );
		update_post_meta(
			$post_id,
			'_admitad_classification_explanation',
			wp_json_encode( $result->explanation(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);
		$this->history->record( $post_id, $result, $previous_terms, $previous_primary, $trigger );
		return true;
	}
}
