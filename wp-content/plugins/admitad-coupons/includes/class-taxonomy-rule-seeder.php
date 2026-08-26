<?php
/**
 * Safe exact-name keyword seeding for the editorial taxonomy.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seeds every existing category and subcategory without changing hierarchy.
 */
final class Promokodiki_Admitad_Taxonomy_Rule_Seeder {
	/**
	 * Seed exact active phrase rules for all current taxonomy terms.
	 *
	 * @return array{created:int,skipped:int,created_rule_ids:array<int,int>}
	 */
	public function seed_all_terms(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'promocode_category',
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array(
				'created'          => 0,
				'skipped'          => 0,
				'created_rule_ids' => array(),
			);
		}
		$rules   = new Promokodiki_Admitad_Rule_Repository();
		$created = array();
		$skipped = 0;
		foreach ( $terms as $term ) {
			if ( $rules->find_id( $term->name, (int) $term->term_id ) > 0 ) {
				++$skipped;
				continue;
			}
			$created[] = $rules->save( $term->name, (int) $term->term_id, 20, 'active', 'phrase', 'taxonomy_seed' );
		}
		return array(
			'created'          => count( $created ),
			'skipped'          => $skipped,
			'created_rule_ids' => $created,
		);
	}
}
