<?php
/**
 * Unicode normalization and safe phrase-rule integration tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

$term_ids = array();

try {
	admitad_register_content_types();
	Promokodiki_Admitad_Schema::install();
	Promokodiki_Admitad_Test_Harness::run(
		'normalizer handles case, yo, punctuation, hyphens, and Unicode tokens',
		static function (): void {
			Promokodiki_Admitad_Test_Harness::assert_same(
				'скидка на телефоны еще',
				Promokodiki_Admitad_Text_Normalizer::normalize( '  СКИДКА—на телефоны, ещё! ' )
			);
			Promokodiki_Admitad_Test_Harness::assert_same(
				array( 'обувь', 'для', 'бега', '2026' ),
				Promokodiki_Admitad_Text_Normalizer::tokens( 'Обувь-для бега 2026' )
			);
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'phrase and token rules respect boundaries while prefix is explicit',
		static function () use ( &$term_ids ): void {
			$suffix = wp_generate_password( 8, false );
			$travel = wp_insert_term( 'Тест: Путешествия ' . $suffix, 'promocode_category' );
			$shoes  = wp_insert_term( 'Тест: Обувь ' . $suffix, 'promocode_category' );
			if ( is_wp_error( $travel ) || is_wp_error( $shoes ) ) {
				throw new RuntimeException( 'Unable to create rule test terms.' );
			}
			$travel_id = (int) $travel['term_id'];
			$shoes_id  = (int) $shoes['term_id'];
			$term_ids  = array( $travel_id, $shoes_id );

			$rules = new Promokodiki_Admitad_Rule_Repository();
			$rules->save( 'беговые кроссовки', $shoes_id, 30, 'active', 'phrase', 'test' );
			$rules->save( 'тур', $travel_id, 20, 'active', 'token', 'test' );
			$prefix_id = $rules->save( 'авиа', $travel_id, 10, 'active', 'prefix', 'test' );
			$rules->save( 'секретная скидка', $travel_id, 100, 'suspended', 'phrase', 'test' );

			$matches = $rules->match( Promokodiki_Admitad_Text_Normalizer::normalize( 'Скидка на беговые кроссовки' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( array( $shoes_id ), array_values( array_unique( array_column( $matches, 'site_term_id' ) ) ) );
			Promokodiki_Admitad_Test_Harness::assert_same(
				array(),
				$rules->match( Promokodiki_Admitad_Text_Normalizer::normalize( 'Культурное событие' ) )
			);
			Promokodiki_Admitad_Test_Harness::assert_same(
				$prefix_id,
				(int) $rules->match( Promokodiki_Admitad_Text_Normalizer::normalize( 'Авиабилеты' ) )[0]['id']
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 'active', $rules->find_status( 'авиа', $travel_id ) );
			Promokodiki_Admitad_Test_Harness::assert_true( $rules->set_status( $prefix_id, 'suspended' ) );
			Promokodiki_Admitad_Test_Harness::assert_same(
				array(),
				$rules->match( Promokodiki_Admitad_Text_Normalizer::normalize( 'Авиабилеты' ) )
			);
		}
	);
} finally {
	global $wpdb;
	$wpdb->query( 'DELETE FROM ' . Promokodiki_Admitad_Schema::table( 'rule' ) . " WHERE source = 'test'" );
	foreach ( $term_ids as $term_id ) {
		wp_delete_term( $term_id, 'promocode_category' );
	}
}

Promokodiki_Admitad_Test_Harness::finish();
