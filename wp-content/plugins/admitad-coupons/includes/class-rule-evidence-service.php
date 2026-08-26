<?php
/**
 * Candidate rule evidence accumulation.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activates observed phrases only after configured evidence thresholds.
 */
final class Promokodiki_Admitad_Rule_Evidence_Service {
	/**
	 * Rule repository.
	 *
	 * @var Promokodiki_Admitad_Rule_Repository
	 */
	private Promokodiki_Admitad_Rule_Repository $rules;

	/**
	 * Constructor.
	 *
	 * @param Promokodiki_Admitad_Rule_Repository|null $rules Rule repository.
	 */
	public function __construct( ?Promokodiki_Admitad_Rule_Repository $rules = null ) {
		$this->rules = $rules ?? new Promokodiki_Admitad_Rule_Repository();
	}

	/**
	 * Observe one phrase/category/campaign outcome.
	 *
	 * @param string $phrase        Phrase.
	 * @param int    $term_id       Site term.
	 * @param int    $campaign_id   Campaign ID.
	 * @param bool   $contradiction Whether evidence contradicted the mapping.
	 */
	public function observe( string $phrase, int $term_id, int $campaign_id, bool $contradiction ): void {
		$rule_id = $this->rules->find_id( $phrase, $term_id );
		if ( 0 === $rule_id ) {
			$rule_id = $this->rules->save( $phrase, $term_id, 20, 'candidate', 'phrase', 'observed' );
		}
		$key                      = 'promokodiki_admitad_rule_evidence_' . $rule_id;
		$evidence                 = (array) get_option(
			$key,
			array(
				'observations'   => 0,
				'campaign_ids'   => array(),
				'contradictions' => 0,
			)
		);
		$evidence['observations'] = (int) ( $evidence['observations'] ?? 0 ) + 1;
		$campaign_ids             = array_map( 'absint', (array) ( $evidence['campaign_ids'] ?? array() ) );
		if ( $campaign_id > 0 ) {
			$campaign_ids[] = $campaign_id;
		}
		$evidence['campaign_ids'] = array_values( array_unique( $campaign_ids ) );
		if ( $contradiction ) {
			$evidence['contradictions'] = (int) ( $evidence['contradictions'] ?? 0 ) + 1;
		}
		update_option( $key, $evidence, false );
		$this->rules->update_evidence(
			$rule_id,
			(int) $evidence['observations'],
			count( $evidence['campaign_ids'] ),
			(int) ( $evidence['contradictions'] ?? 0 )
		);

		if ( (int) $evidence['contradictions'] > (int) Promokodiki_Admitad_Config::get( 'candidate_conflicts' ) ) {
			$this->rules->set_status( $rule_id, 'conflict' );
			return;
		}
		if (
			(int) $evidence['observations'] >= (int) Promokodiki_Admitad_Config::get( 'candidate_evidence' )
			&& count( $evidence['campaign_ids'] ) >= (int) Promokodiki_Admitad_Config::get( 'candidate_campaigns' )
		) {
			$this->rules->set_status( $rule_id, 'active' );
		}
	}
}
