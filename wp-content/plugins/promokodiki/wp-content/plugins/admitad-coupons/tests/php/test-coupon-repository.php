<?php
/**
 * Coupon repository and editorial lock integration tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

/**
 * Build a normalized coupon fixture.
 *
 * @param string $id Coupon ID.
 * @return array<string, mixed>
 */
function promokodiki_admitad_repository_fixture( string $id ): array {
	return Promokodiki_Admitad_Coupon_Normalizer::normalize(
		array(
			'id'                 => $id,
			'status'             => 'active',
			'name'               => 'API title ' . $id,
			'description'        => 'API description',
			'short_name'         => 'Short',
			'campaign'           => array(
				'id'       => 7775,
				'name'     => 'Repository Test Shop',
				'site_url' => 'https://example.test/',
			),
			'categories'         => array( array( 'id' => 4, 'name' => 'Обувь' ) ),
			'types'              => array( array( 'id' => 2, 'name' => 'Скидка' ) ),
			'species'            => 'promocode',
			'promocode'          => 'CODE10',
			'goto_link'          => 'https://example.test/affiliate',
			'date_start'         => '2026-01-01T00:00:00',
			'date_end'           => '2027-12-31T23:59:00',
			'discount'           => '10%',
			'language'           => 'ru',
			'regions'            => array( 'RU' ),
			'has_affiliate_link' => true,
		)
	);
}

/**
 * Delete the shop term created by repository fixtures.
 */
function promokodiki_admitad_delete_repository_shop(): void {
	$terms = get_terms(
		array(
			'taxonomy'   => 'shops_category',
			'hide_empty' => false,
			'fields'     => 'ids',
			'meta_key'   => 'admitad_campaign_id',
			'meta_value' => '7775',
		)
	);
	foreach ( is_wp_error( $terms ) ? array() : $terms as $term_id ) {
		wp_delete_term( $term_id, 'shops_category' );
	}
}

Promokodiki_Admitad_Test_Harness::run(
	'coupon upsert creates, hash-skips, updates, and preserves locked content',
	static function (): void {
		Promokodiki_Admitad_Plugin::register();
		Promokodiki_Admitad_Editorial_Locks::register();
		$repository = new Promokodiki_Admitad_Coupon_Repository();
		$coupon     = promokodiki_admitad_repository_fixture( 'test-330714' );
		$post_id    = 0;

		try {
			$created = $repository->upsert( $coupon, 10 );
			$post_id = $created['post_id'];
			Promokodiki_Admitad_Test_Harness::assert_same( 'created', $created['state'] );
			Promokodiki_Admitad_Test_Harness::assert_same( '10', (string) get_post_meta( $post_id, '_admitad_last_seen_run_id', true ) );
			Promokodiki_Admitad_Test_Harness::assert_true( ! Promokodiki_Admitad_Editorial_Locks::content_locked( $post_id ) );

			$unchanged = $repository->upsert( $coupon, 11 );
			Promokodiki_Admitad_Test_Harness::assert_same( 'unchanged', $unchanged['state'] );
			Promokodiki_Admitad_Test_Harness::assert_same( '11', (string) get_post_meta( $post_id, '_admitad_last_seen_run_id', true ) );

			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_title'   => 'Редакторский заголовок',
					'post_content' => 'Редакторский текст',
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_true( Promokodiki_Admitad_Editorial_Locks::content_locked( $post_id ) );

			$changed                 = $coupon;
			$changed['title']        = 'Changed API title';
			$changed['description']  = 'Changed API description';
			$changed['discount']     = '20%';
			$changed['payload_hash'] = 'changed-payload-hash';
			$updated                 = $repository->upsert( $changed, 12 );

			Promokodiki_Admitad_Test_Harness::assert_same( 'updated', $updated['state'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'Редакторский заголовок', get_the_title( $post_id ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'Редакторский текст', get_post_field( 'post_content', $post_id ) );
			Promokodiki_Admitad_Test_Harness::assert_same( '20%', get_post_meta( $post_id, 'discount', true ) );
		} finally {
			if ( $post_id ) {
				wp_delete_post( $post_id, true );
			}
			promokodiki_admitad_delete_repository_shop();
		}
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'import context suppresses locks while manual saves create them',
	static function (): void {
		Promokodiki_Admitad_Plugin::register();
		Promokodiki_Admitad_Editorial_Locks::register();
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'promocode',
				'post_status' => 'publish',
				'post_title'  => 'Manual test',
			)
		);

		try {
			delete_post_meta( $post_id, '_admitad_content_locked' );
			Promokodiki_Admitad_Import_Context::run(
				static fn() => wp_update_post( array( 'ID' => $post_id, 'post_title' => 'Automated save' ) )
			);
			Promokodiki_Admitad_Test_Harness::assert_true( ! Promokodiki_Admitad_Editorial_Locks::content_locked( $post_id ) );
			Promokodiki_Admitad_Test_Harness::assert_true( ! Promokodiki_Admitad_Import_Context::active() );

			wp_update_post( array( 'ID' => $post_id, 'post_title' => 'Manual save' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( Promokodiki_Admitad_Editorial_Locks::content_locked( $post_id ) );
		} finally {
			wp_delete_post( $post_id, true );
		}
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'eligibility and source state handle action, future, inactive, and missing links',
	static function (): void {
		Promokodiki_Admitad_Plugin::register();
		$repository = new Promokodiki_Admitad_Coupon_Repository();
		$post_ids   = array();

		try {
			$action             = promokodiki_admitad_repository_fixture( 'test-action' );
			$action['species']  = 'action';
			$action['promocode'] = '';
			$action['payload_hash'] = 'action-hash';
			$action_result      = $repository->upsert( $action, 20 );
			$post_ids[]         = $action_result['post_id'];
			Promokodiki_Admitad_Test_Harness::assert_same( 'created', $action_result['state'] );

			$future                 = promokodiki_admitad_repository_fixture( 'test-future' );
			$future['date_start']   = '2035-01-01T00:00:00';
			$future['payload_hash'] = 'future-hash';
			$future_result          = $repository->upsert( $future, 21 );
			$post_ids[]             = $future_result['post_id'];
			Promokodiki_Admitad_Test_Harness::assert_same( 'future', get_post_status( $future_result['post_id'] ) );

			$inactive                 = promokodiki_admitad_repository_fixture( 'test-inactive' );
			$inactive['source_status'] = 'inactive';
			$inactive['payload_hash']  = 'inactive-hash';
			$inactive_result           = $repository->upsert( $inactive, 22 );
			$post_ids[]                = $inactive_result['post_id'];
			Promokodiki_Admitad_Test_Harness::assert_same(
				'no',
				get_post_meta( $inactive_result['post_id'], '_promocode_is_active', true )
			);

			$ineligible                       = promokodiki_admitad_repository_fixture( 'test-no-link' );
			$ineligible['has_affiliate_link'] = false;
			$ineligible['goto_link']          = '';
			$ineligible['payload_hash']       = 'no-link-hash';
			$failed                           = $repository->upsert( $ineligible, 23 );
			Promokodiki_Admitad_Test_Harness::assert_same( 'failed', $failed['state'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 0, $failed['post_id'] );
		} finally {
			foreach ( $post_ids as $post_id ) {
				wp_delete_post( $post_id, true );
			}
			promokodiki_admitad_delete_repository_shop();
		}
	}
);

Promokodiki_Admitad_Test_Harness::finish();
