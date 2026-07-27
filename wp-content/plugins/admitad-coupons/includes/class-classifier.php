<?php
/**
 * Deterministic Admitad coupon classifier.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves structured, company, and safe text signals without side effects.
 */
final class Promokodiki_Admitad_Classifier {
	/**
	 * Category maps.
	 *
	 * @var Promokodiki_Admitad_Category_Map_Repository
	 */
	private Promokodiki_Admitad_Category_Map_Repository $maps;

	/**
	 * Company profiles.
	 *
	 * @var Promokodiki_Admitad_Company_Profile_Repository
	 */
	private Promokodiki_Admitad_Company_Profile_Repository $profiles;

	/**
	 * Phrase rules.
	 *
	 * @var Promokodiki_Admitad_Rule_Repository
	 */
	private Promokodiki_Admitad_Rule_Repository $rules;

	/**
	 * Constructor.
	 *
	 * @param Promokodiki_Admitad_Category_Map_Repository|null    $maps     Category maps.
	 * @param Promokodiki_Admitad_Company_Profile_Repository|null $profiles Company profiles.
	 * @param Promokodiki_Admitad_Rule_Repository|null            $rules    Phrase rules.
	 */
	public function __construct( $maps = null, $profiles = null, $rules = null ) {
		$this->maps     = $maps ?? new Promokodiki_Admitad_Category_Map_Repository();
		$this->profiles = $profiles ?? new Promokodiki_Admitad_Company_Profile_Repository();
		$this->rules    = $rules ?? new Promokodiki_Admitad_Rule_Repository();
	}

	/**
	 * Classify one normalized coupon.
	 *
	 * @param array<string, mixed> $coupon  Normalized coupon.
	 * @param array<string, mixed> $context Lock and fallback context.
	 */
	public function classify( array $coupon, array $context = array() ): Promokodiki_Admitad_Classification_Result {
		$locked_terms = array_values( array_filter( array_map( 'absint', (array) ( $context['locked_term_ids'] ?? array() ) ) ) );
		if ( $locked_terms ) {
			$locked_primary = absint( $context['locked_primary_id'] ?? $locked_terms[0] );
			return Promokodiki_Admitad_Classification_Result::locked( $locked_terms, $locked_primary );
		}

		$signals = $this->coupon_category_signals( $coupon );
		$signals = array_merge( $signals, $this->campaign_map_signals( $coupon ) );
		$profile = $this->profile( $coupon );
		if ( $profile && $profile['default_term_id'] > 0 ) {
			$signals[] = array(
				'term_id' => $profile['default_term_id'],
				'weight'  => $profile['weight'],
				'kind'    => 'company',
				'source'  => 'company_default',
			);
		}
		$signals = array_merge( $signals, $this->text_signals( $coupon ) );

		$rejected = array();
		if ( $profile && $profile['allowed_term_ids'] ) {
			$signals = array_values(
				array_filter(
					$signals,
					static function ( array $signal ) use ( $profile, &$rejected ): bool {
						if ( in_array( (int) $signal['term_id'], $profile['allowed_term_ids'], true ) ) {
							return true;
						}
						$rejected[] = array(
							'term_id' => (int) $signal['term_id'],
							'reason'  => 'outside_company_profile',
							'source'  => (string) $signal['source'],
						);
						return false;
					}
				)
			);
		}

		return $this->resolve(
			$signals,
			$rejected,
			absint( $context['other_term_id'] ?? 0 ),
			(int) Promokodiki_Admitad_Config::get( 'max_categories' )
		);
	}

	/**
	 * Build category-ID signals.
	 *
	 * @param array<string, mixed> $coupon Coupon.
	 * @return array<int, array<string, mixed>>
	 */
	private function coupon_category_signals( array $coupon ): array {
		$signals = array();
		foreach ( (array) ( $coupon['categories'] ?? array() ) as $category ) {
			$external_id = absint( is_array( $category ) ? ( $category['id'] ?? 0 ) : $category );
			foreach ( $this->maps->signals_for_external( 'coupon', $external_id ) as $mapping ) {
				$signals[] = array(
					'term_id'     => $mapping['term_id'],
					'weight'      => $mapping['weight'],
					'kind'        => 'structured',
					'source'      => 'coupon_category',
					'external_id' => $external_id,
				);
			}
		}
		return $signals;
	}

	/**
	 * Build campaign-ID mapping signals.
	 *
	 * @param array<string, mixed> $coupon Coupon.
	 * @return array<int, array<string, mixed>>
	 */
	private function campaign_map_signals( array $coupon ): array {
		$campaign_id = absint( $coupon['campaign']['id'] ?? 0 );
		$signals     = array();
		foreach ( $this->maps->signals_for_external( 'campaign', $campaign_id ) as $mapping ) {
			$signals[] = array(
				'term_id'     => $mapping['term_id'],
				'weight'      => $mapping['weight'],
				'kind'        => 'structured',
				'source'      => 'campaign_category',
				'external_id' => $campaign_id,
			);
		}
		return $signals;
	}

	/**
	 * Read the coupon campaign profile.
	 *
	 * @param array<string, mixed> $coupon Coupon.
	 * @return array{default_term_id:int,allowed_term_ids:array<int,int>,weight:int}|null
	 */
	private function profile( array $coupon ): ?array {
		$campaign_id = absint( $coupon['campaign']['id'] ?? 0 );
		return $campaign_id > 0 ? $this->profiles->profile_for_campaign( $campaign_id ) : null;
	}

	/**
	 * Build title and description phrase signals.
	 *
	 * @param array<string, mixed> $coupon Coupon.
	 * @return array<int, array<string, mixed>>
	 */
	private function text_signals( array $coupon ): array {
		$signals = array();
		$fields  = array(
			'title'       => (int) Promokodiki_Admitad_Config::get( 'weight_title' ),
			'description' => (int) Promokodiki_Admitad_Config::get( 'weight_description' ),
		);
		foreach ( $fields as $field => $source_weight ) {
			foreach ( $this->rules->match( wp_strip_all_tags( (string) ( $coupon[ $field ] ?? '' ) ) ) as $rule ) {
				$signals[] = array(
					'term_id'      => (int) $rule['site_term_id'],
					'weight'       => (int) $rule['weight'] + $source_weight,
					'kind'         => 'text',
					'source'       => $field,
					'rule_id'      => (int) $rule['id'],
					'rule_version' => (int) $rule['rule_version'],
					'phrase'       => (string) $rule['normalized_phrase'],
				);
			}
		}
		return $signals;
	}

	/**
	 * Aggregate, rank, and cap signals.
	 *
	 * @param array<int, array<string, mixed>> $signals   Signals.
	 * @param array<int, array<string, mixed>> $rejected  Rejected signals.
	 * @param int                              $other_id   Editorial fallback term.
	 * @param int                              $max_terms  Selection cap.
	 */
	private function resolve( array $signals, array $rejected, int $other_id, int $max_terms ): Promokodiki_Admitad_Classification_Result {
		$aggregate = array();
		foreach ( $signals as $signal ) {
			$term_id = (int) $signal['term_id'];
			if ( ! $this->valid_term( $term_id ) ) {
				$rejected[] = array(
					'term_id' => $term_id,
					'reason'  => 'missing_site_term',
					'source'  => (string) $signal['source'],
				);
				continue;
			}
			if ( ! isset( $aggregate[ $term_id ] ) ) {
				$aggregate[ $term_id ] = array(
					'term_id' => $term_id,
					'score'   => 0,
					'signals' => array(),
					'depth'   => $this->depth( $term_id ),
				);
			}
			$aggregate[ $term_id ]['score']    += max( 0, (int) $signal['weight'] );
			$aggregate[ $term_id ]['signals'][] = $signal;
		}

		$ranked = array_values( $aggregate );
		usort(
			$ranked,
			static function ( array $left, array $right ): int {
				$score_order = $right['score'] <=> $left['score'];
				if ( 0 !== $score_order ) {
					return $score_order;
				}
				$depth_order = $right['depth'] <=> $left['depth'];
				return 0 !== $depth_order ? $depth_order : ( $left['term_id'] <=> $right['term_id'] );
			}
		);

		$selected = array();
		foreach ( $ranked as $candidate ) {
			if ( count( $selected ) >= max( 1, $max_terms ) ) {
				break;
			}
			$redundant = false;
			foreach ( $selected as $selected_id ) {
				if ( term_is_ancestor_of( $candidate['term_id'], $selected_id, 'promocode_category' ) ) {
					$redundant = true;
					break;
				}
			}
			if ( ! $redundant ) {
				$selected[] = (int) $candidate['term_id'];
			}
		}

		if ( ! $selected && $this->valid_term( $other_id ) ) {
			$selected[] = $other_id;
		}
		$conflicts  = $this->conflicts( $ranked );
		$confidence = $this->confidence( $ranked, $conflicts );
		if ( ! $ranked ) {
			$confidence = 'low';
		}
		$rule_versions = array();
		foreach ( $signals as $signal ) {
			if ( isset( $signal['rule_id'], $signal['rule_version'] ) ) {
				$rule_versions[ (int) $signal['rule_id'] ] = (int) $signal['rule_version'];
			}
		}
		return new Promokodiki_Admitad_Classification_Result(
			$selected,
			$selected[0] ?? 0,
			$confidence,
			array(
				'algorithm_version' => '1.0',
				'signals'           => $signals,
				'ranked'            => $ranked,
				'rejected'          => $rejected,
				'conflicts'         => $conflicts,
				'rule_versions'     => $rule_versions,
			)
		);
	}

	/**
	 * Detect an equally strong unrelated top pair.
	 *
	 * @param array<int, array<string, mixed>> $ranked Ranked candidates.
	 * @return array<int, array<string, mixed>>
	 */
	private function conflicts( array $ranked ): array {
		if ( count( $ranked ) < 2 || $ranked[0]['score'] !== $ranked[1]['score'] ) {
			return array();
		}
		$left  = (int) $ranked[0]['term_id'];
		$right = (int) $ranked[1]['term_id'];
		if (
			term_is_ancestor_of( $left, $right, 'promocode_category' )
			|| term_is_ancestor_of( $right, $left, 'promocode_category' )
		) {
			return array();
		}
		return array(
			array(
				'term_ids' => array( $left, $right ),
				'score'    => (int) $ranked[0]['score'],
			),
		);
	}

	/**
	 * Convert the leading score and evidence into a confidence label.
	 *
	 * @param array<int, array<string, mixed>> $ranked    Ranked candidates.
	 * @param array<int, array<string, mixed>> $conflicts Conflicts.
	 */
	private function confidence( array $ranked, array $conflicts ): string {
		if ( ! $ranked ) {
			return 'low';
		}
		$score       = (int) $ranked[0]['score'];
		$top_signals = (array) $ranked[0]['signals'];
		$text_only   = 1 === count( $top_signals ) && 'text' === ( $top_signals[0]['kind'] ?? '' );
		if ( $score >= (int) Promokodiki_Admitad_Config::get( 'confidence_high' ) && ! $conflicts && ! $text_only ) {
			return 'high';
		}
		if ( $score >= (int) Promokodiki_Admitad_Config::get( 'confidence_medium' ) ) {
			return 'medium';
		}
		return 'low';
	}

	/**
	 * Return taxonomy depth.
	 *
	 * @param int $term_id Term ID.
	 */
	private function depth( int $term_id ): int {
		return count( get_ancestors( $term_id, 'promocode_category', 'taxonomy' ) );
	}

	/**
	 * Validate a site category.
	 *
	 * @param int $term_id Term ID.
	 */
	private function valid_term( int $term_id ): bool {
		return get_term( $term_id, 'promocode_category' ) instanceof WP_Term;
	}
}
