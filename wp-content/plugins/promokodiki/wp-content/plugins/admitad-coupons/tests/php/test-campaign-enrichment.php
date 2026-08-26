<?php
/**
 * Campaign enrichment normalization and persistence tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once __DIR__ . '/class-test-environment-guard.php';
Promokodiki_Admitad_Test_Environment_Guard::assert_disposable_database();
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

Promokodiki_Admitad_Test_Harness::run(
	'campaign enrichment fields are normalized and included in the payload hash',
	static function (): void {
		$raw = array(
			'id'              => 987654321,
			'name'            => 'Enrichment fixture',
			'status'          => 'active',
			'description'     => "  Короткое\nописание  ",
			'raw_description' => '<p>Полное <strong>описание</strong></p>',
			'rating'          => '4.7',
			'image'           => 'https://cdn.example.test/logo.png',
			'site_url'        => 'https://shop.example.test/',
			'categories'      => array(),
		);
		$normalized = Promokodiki_Admitad_Campaign_Normalizer::normalize( $raw );

		Promokodiki_Admitad_Test_Harness::assert_same( "Короткое\nописание", $normalized['description'] ?? null );
		Promokodiki_Admitad_Test_Harness::assert_same( '<p>Полное <strong>описание</strong></p>', $normalized['raw_description'] ?? null );
		Promokodiki_Admitad_Test_Harness::assert_same( 4.7, $normalized['rating'] ?? null );
		Promokodiki_Admitad_Test_Harness::assert_same( 'https://cdn.example.test/logo.png', $normalized['image_url'] ?? null );
		Promokodiki_Admitad_Test_Harness::assert_same( 'https://shop.example.test/', $normalized['site_url'] ?? null );

		$changed              = $raw;
		$changed['rating']    = '4.8';
		$changed_normalized   = Promokodiki_Admitad_Campaign_Normalizer::normalize( $changed );
		Promokodiki_Admitad_Test_Harness::assert_true( $normalized['payload_hash'] !== $changed_normalized['payload_hash'] );

		foreach ( array( '', 'not-a-number', -1, 0, 5.1, INF, NAN ) as $invalid ) {
			$changed['rating'] = $invalid;
			$invalid_rating    = Promokodiki_Admitad_Campaign_Normalizer::normalize( $changed );
			Promokodiki_Admitad_Test_Harness::assert_same( null, $invalid_rating['rating'] ?? null );
		}
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'empty campaign fields preserve the last non-empty enrichment snapshot',
	static function (): void {
		global $wpdb;

		Promokodiki_Admitad_Schema::install();
		$campaign_id = 987654322;
		$table       = Promokodiki_Admitad_Schema::table( 'company_profile' );
		$repository  = new Promokodiki_Admitad_Reference_Repository();
		$complete    = Promokodiki_Admitad_Campaign_Normalizer::normalize(
			array(
				'id'              => $campaign_id,
				'name'            => 'Persistent fixture',
				'status'          => 'active',
				'description'     => 'Краткое описание',
				'raw_description' => '<p>Полное описание</p>',
				'rating'          => 4.6,
				'image'           => 'https://cdn.example.test/persistent.png',
				'site_url'        => 'https://persistent.example.test/',
				'categories'      => array(),
			)
		);

		try {
			$repository->sync_campaigns( array( $complete ) );
			$empty = Promokodiki_Admitad_Campaign_Normalizer::normalize(
				array(
					'id'              => $campaign_id,
					'name'            => 'Persistent fixture renamed',
					'status'          => 'active',
					'description'     => '',
					'raw_description' => '',
					'rating'          => null,
					'image'           => '',
					'site_url'        => '',
					'categories'      => array(),
				)
			);
			$repository->sync_campaigns( array( $empty ) );
			$profile = $repository->campaign( $campaign_id );

			Promokodiki_Admitad_Test_Harness::assert_same( 'Persistent fixture renamed', $profile['display_name'] ?? null );
			Promokodiki_Admitad_Test_Harness::assert_same( 'Краткое описание', $profile['description'] ?? null );
			Promokodiki_Admitad_Test_Harness::assert_same( '<p>Полное описание</p>', $profile['raw_description'] ?? null );
			Promokodiki_Admitad_Test_Harness::assert_same( 4.6, isset( $profile['rating'] ) ? (float) $profile['rating'] : null );
			Promokodiki_Admitad_Test_Harness::assert_same( 'https://cdn.example.test/persistent.png', $profile['image_url'] ?? null );
			Promokodiki_Admitad_Test_Harness::assert_same( 'https://persistent.example.test/', $profile['site_url'] ?? null );
		} finally {
			$wpdb->delete( $table, array( 'campaign_id' => $campaign_id ), array( '%d' ) );
		}
	}
);

Promokodiki_Admitad_Test_Harness::finish();
