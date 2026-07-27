<?php
/**
 * Complete single-coupon import pipeline.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes, persists, classifies, assigns, and tags one API coupon.
 */
final class Promokodiki_Admitad_Import_Pipeline {
	/**
	 * Coupon repository.
	 *
	 * @var Promokodiki_Admitad_Coupon_Repository
	 */
	private Promokodiki_Admitad_Coupon_Repository $coupons;

	/**
	 * Classifier.
	 *
	 * @var Promokodiki_Admitad_Classifier
	 */
	private Promokodiki_Admitad_Classifier $classifier;

	/**
	 * Assignment service.
	 *
	 * @var Promokodiki_Admitad_Assignment_Service
	 */
	private Promokodiki_Admitad_Assignment_Service $assignments;

	/**
	 * Tag manager.
	 *
	 * @var Promokodiki_Admitad_Tag_Manager
	 */
	private Promokodiki_Admitad_Tag_Manager $tags;

	/**
	 * Constructor.
	 *
	 * @param Promokodiki_Admitad_Coupon_Repository|null  $coupons     Coupon repository.
	 * @param Promokodiki_Admitad_Classifier|null         $classifier  Classifier.
	 * @param Promokodiki_Admitad_Assignment_Service|null $assignments Assignment service.
	 * @param Promokodiki_Admitad_Tag_Manager|null        $tags        Tag manager.
	 */
	public function __construct( $coupons = null, $classifier = null, $assignments = null, $tags = null ) {
		$this->coupons     = $coupons ?? new Promokodiki_Admitad_Coupon_Repository();
		$this->classifier  = $classifier ?? new Promokodiki_Admitad_Classifier();
		$this->assignments = $assignments ?? new Promokodiki_Admitad_Assignment_Service();
		$this->tags        = $tags ?? new Promokodiki_Admitad_Tag_Manager();
	}

	/**
	 * Process one raw Admitad coupon.
	 *
	 * @param array<string,mixed> $raw_coupon Raw API payload.
	 * @param int                 $run_id     Synchronization run ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function process( array $raw_coupon, int $run_id ) {
		$normalized = Promokodiki_Admitad_Coupon_Normalizer::normalize( $raw_coupon );
		$stored     = $this->coupons->upsert( $normalized, $run_id );
		$post_id    = (int) $stored['post_id'];
		if ( $post_id <= 0 ) {
			return array_merge(
				$stored,
				array(
					'normalized'  => $normalized,
					'assigned'    => false,
					'term_ids'    => array(),
					'confidence'  => '',
					'explanation' => array(),
				)
			);
		}
		$other    = get_term_by( 'slug', 'other', 'promocode_category' );
		$result   = $this->classifier->classify(
			$normalized,
			array(
				'locked_term_ids'   => (array) get_post_meta( $post_id, '_admitad_locked_term_ids', true ),
				'locked_primary_id' => (int) get_post_meta( $post_id, '_admitad_primary_term_id', true ),
				'other_term_id'     => $other instanceof WP_Term ? (int) $other->term_id : 0,
			)
		);
		$assigned = $this->assignments->assign( $post_id, $result, 'coupon_sync' );
		$this->tags->sync( $post_id, $normalized );
		return array_merge(
			$stored,
			array(
				'normalized'  => $normalized,
				'assigned'    => $assigned,
				'term_ids'    => $result->term_ids(),
				'confidence'  => $result->confidence(),
				'explanation' => $result->explanation(),
			)
		);
	}
}
