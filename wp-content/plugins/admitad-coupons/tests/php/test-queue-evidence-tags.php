<?php
/**
 * Review queue, rule evidence, taxonomy seeds, and managed tag tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

$term_ids      = array();
$post_ids      = array();
$rule_ids      = array();
$queue_entities = array();
$old_settings  = get_option( 'promokodiki_admitad_settings', array() );

try {
	admitad_register_content_types();
	Promokodiki_Admitad_Schema::install();
	update_option(
		'promokodiki_admitad_settings',
		array_merge(
			(array) $old_settings,
			array(
				'candidate_evidence'  => 5,
				'candidate_campaigns' => 2,
				'candidate_conflicts' => 0,
				'auto_tags'           => true,
			)
		),
		false
	);
	$suffix = wp_generate_password( 8, false );
	$parent = wp_insert_term( 'Тест спорт ' . $suffix, 'promocode_category' );
	$child  = wp_insert_term( 'Тест кроссовки ' . $suffix, 'promocode_category', array( 'parent' => (int) $parent['term_id'] ) );
	if ( is_wp_error( $parent ) || is_wp_error( $child ) ) {
		throw new RuntimeException( 'Unable to create queue/evidence test terms.' );
	}
	$parent_id = (int) $parent['term_id'];
	$child_id  = (int) $child['term_id'];
	$term_ids  = array( $parent_id, $child_id );

	Promokodiki_Admitad_Test_Harness::run(
		'review queue deduplicates unresolved reasons and assignment queues low confidence',
		static function () use ( $child_id, &$queue_entities, &$post_ids ): void {
			global $wpdb;

			$entity_id        = 'queue-' . wp_generate_password( 8, false );
			$queue_entities[] = $entity_id;
			$queue            = new Promokodiki_Admitad_Review_Queue_Repository();
			$table            = Promokodiki_Admitad_Schema::table( 'review_queue' );
			$first            = $queue->enqueue( 'coupon', $entity_id, 'conflicting_signals', array( 'term_id' => $child_id ) );
			$second           = $queue->enqueue( 'coupon', $entity_id, 'conflicting_signals', array( 'term_id' => $child_id ) );
			Promokodiki_Admitad_Test_Harness::assert_same( $first, $second );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The test verifies its own row in the plugin-owned queue table; values are prepared.
			Promokodiki_Admitad_Test_Harness::assert_same( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE entity_type = %s AND entity_id = %s AND reason_code = %s AND status = %s", 'coupon', $entity_id, 'conflicting_signals', 'open' ) ) );

			$post_id    = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'publish', 'post_title' => 'Low confidence fixture' ) );
			$post_ids[] = $post_id;
			update_post_meta( $post_id, 'admitad_coupon_id', $entity_id . '-low' );
			$queue_entities[] = $entity_id . '-low';
			$result = new Promokodiki_Admitad_Classification_Result(
				array( $child_id ),
				$child_id,
				'low',
				array( 'algorithm_version' => 'queue-test', 'signals' => array(), 'conflicts' => array() )
			);
			Promokodiki_Admitad_Test_Harness::assert_true(
				( new Promokodiki_Admitad_Assignment_Service( null, $queue ) )->assign( $post_id, $result, 'queue_test' )
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The test verifies its own row in the plugin-owned queue table; values are prepared.
			Promokodiki_Admitad_Test_Harness::assert_same( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE entity_type = %s AND entity_id = %s AND reason_code = %s AND status = %s", 'coupon', $entity_id . '-low', 'low_confidence', 'open' ) ) );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'every taxonomy level receives an exact safe seed without overriding existing rules',
		static function () use ( $parent_id, $child_id, &$rule_ids ): void {
			$seeder  = new Promokodiki_Admitad_Taxonomy_Rule_Seeder();
			$report  = $seeder->seed_all_terms();
			$rule_ids = array_merge( $rule_ids, $report['created_rule_ids'] );
			$rules   = new Promokodiki_Admitad_Rule_Repository();
			Promokodiki_Admitad_Test_Harness::assert_same(
				'active',
				$rules->find_status( (string) get_term( $parent_id, 'promocode_category' )->name, $parent_id )
			);
			Promokodiki_Admitad_Test_Harness::assert_same(
				'active',
				$rules->find_status( (string) get_term( $child_id, 'promocode_category' )->name, $child_id )
			);
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'candidate phrases require five observations, two campaigns, and zero contradictions',
		static function () use ( $child_id, &$rule_ids ): void {
			$phrase   = 'беговые кроссовки ' . wp_generate_password( 6, false );
			$evidence = new Promokodiki_Admitad_Rule_Evidence_Service();
			foreach ( array( 1001, 1002, 1001, 1002, 1001 ) as $campaign_id ) {
				$evidence->observe( $phrase, $child_id, $campaign_id, false );
			}
			$rules      = new Promokodiki_Admitad_Rule_Repository();
			$rule_ids[] = $rules->find_id( $phrase, $child_id );
			Promokodiki_Admitad_Test_Harness::assert_same( 'active', $rules->find_status( $phrase, $child_id ) );

			$conflict_phrase = 'спорная фраза ' . wp_generate_password( 6, false );
			$evidence->observe( $conflict_phrase, $child_id, 2001, true );
			$rule_ids[] = $rules->find_id( $conflict_phrase, $child_id );
			Promokodiki_Admitad_Test_Harness::assert_same( 'conflict', $rules->find_status( $conflict_phrase, $child_id ) );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'managed tags cover structured coupon traits and disabling preserves relationships',
		static function () use ( &$post_ids, $old_settings ): void {
			$post_id    = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'publish', 'post_title' => 'Tag fixture' ) );
			$post_ids[] = $post_id;
			$manager    = new Promokodiki_Admitad_Tag_Manager();
			$manager->sync(
				$post_id,
				array(
					'title'       => 'Подарок и бесплатная доставка новым клиентам',
					'description' => 'Персональное эксклюзивное предложение',
					'discount'    => '15%',
					'types'       => array( array( 'name' => 'Эксклюзивный промокод' ) ),
				)
			);
			$before = wp_get_object_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) );
			foreach ( array( 'Скидка', 'Бесплатная доставка', 'Подарок', 'Новым клиентам', 'Эксклюзив', 'Персональный' ) as $expected ) {
				Promokodiki_Admitad_Test_Harness::assert_true( in_array( $expected, $before, true ), 'Missing managed tag: ' . $expected );
			}

			update_option( 'promokodiki_admitad_settings', array_merge( (array) $old_settings, array( 'auto_tags' => false ) ), false );
			$manager->sync( $post_id, array( 'title' => '', 'description' => '', 'discount' => '', 'types' => array() ) );
			$after = wp_get_object_terms( $post_id, 'post_tag', array( 'fields' => 'names' ) );
			sort( $before );
			sort( $after );
			Promokodiki_Admitad_Test_Harness::assert_same( $before, $after );
		}
	);
} finally {
	global $wpdb;
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( array_unique( array_filter( array_map( 'intval', $rule_ids ) ) ) as $rule_id ) {
		delete_option( 'promokodiki_admitad_rule_evidence_' . $rule_id );
		$wpdb->delete( Promokodiki_Admitad_Schema::table( 'rule' ), array( 'id' => $rule_id ), array( '%d' ) );
	}
	foreach ( $queue_entities as $entity_id ) {
		$wpdb->delete( Promokodiki_Admitad_Schema::table( 'review_queue' ), array( 'entity_id' => $entity_id ), array( '%s' ) );
	}
	$wpdb->query( "DELETE FROM " . Promokodiki_Admitad_Schema::table( 'classification_history' ) . " WHERE algorithm_version = 'queue-test'" );
	foreach ( array_reverse( $term_ids ) as $term_id ) {
		wp_delete_term( $term_id, 'promocode_category' );
	}
	update_option( 'promokodiki_admitad_settings', $old_settings, false );
}

Promokodiki_Admitad_Test_Harness::finish();
