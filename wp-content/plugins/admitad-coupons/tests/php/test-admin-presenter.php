<?php
/**
 * Shared administrative presenter integration tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

$term_ids = array();

try {
	admitad_register_content_types();
	Promokodiki_Admitad_Test_Harness::run(
		'admin presenter translates statuses and builds full term paths',
		static function () use ( &$term_ids ): void {
			$parent = wp_insert_term( 'Parent Fixture', 'promocode_category' );
			$child  = wp_insert_term(
				'Child Fixture',
				'promocode_category',
				array( 'parent' => (int) $parent['term_id'] )
			);
			$grandchild = wp_insert_term(
				'Grandchild Fixture',
				'promocode_category',
				array( 'parent' => (int) $child['term_id'] )
			);
			$term_ids = array( (int) $grandchild['term_id'], (int) $child['term_id'], (int) $parent['term_id'] );

			$status = Promokodiki_Admitad_Admin_Presenter::status( 'rule', 'archived' );
			Promokodiki_Admitad_Test_Harness::assert_same( 'В архиве', $status['label'] );
			Promokodiki_Admitad_Test_Harness::assert_same(
				array(
					'label' => 'Неизвестный статус (future_state)',
					'class' => 'promokodiki-admitad-status--neutral',
				),
				Promokodiki_Admitad_Admin_Presenter::status( 'rule', 'future_state' )
			);
			Promokodiki_Admitad_Test_Harness::assert_same(
				'Parent Fixture → Child Fixture → Grandchild Fixture',
				Promokodiki_Admitad_Admin_Presenter::term_path( (int) $grandchild['term_id'] )
			);
			Promokodiki_Admitad_Test_Harness::assert_same( '—', Promokodiki_Admitad_Admin_Presenter::term_path( 0 ) );
			Promokodiki_Admitad_Test_Harness::assert_same(
				array(
					array( 'id' => (int) $grandchild['term_id'], 'label' => 'Parent Fixture → Child Fixture → Grandchild Fixture' ),
					array( 'id' => (int) $child['term_id'], 'label' => 'Parent Fixture → Child Fixture' ),
					array( 'id' => (int) $parent['term_id'], 'label' => 'Parent Fixture' ),
				),
				Promokodiki_Admitad_Admin_Presenter::term_options(
					array( get_term( (int) $grandchild['term_id'] ), get_term( (int) $child['term_id'] ), get_term( (int) $parent['term_id'] ) )
				)
			);
		}
	);
} finally {
	foreach ( $term_ids as $term_id ) {
		wp_delete_term( $term_id, 'promocode_category' );
	}
}

Promokodiki_Admitad_Test_Harness::finish();
