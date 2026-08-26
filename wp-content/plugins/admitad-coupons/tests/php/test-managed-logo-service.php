<?php
/**
 * Managed shop-logo lifecycle tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once __DIR__ . '/class-test-environment-guard.php';
Promokodiki_Admitad_Test_Environment_Guard::assert_disposable_database();
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';
Promokodiki_Admitad_Plugin::register();
Promokodiki_Admitad_Schema::install();

/**
 * Create a real tiny PNG temporary file.
 */
function promokodiki_admitad_logo_png(): string {
	$file = wp_tempnam( 'managed-logo.png' );
	file_put_contents( $file, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z7ioAAAAASUVORK5CYII=' ) );
	return $file;
}

/**
 * Store one campaign snapshot.
 */
function promokodiki_admitad_logo_campaign( int $campaign_id, string $url ): void {
	( new Promokodiki_Admitad_Reference_Repository() )->sync_campaigns(
		array(
			Promokodiki_Admitad_Campaign_Normalizer::normalize(
				array(
					'id'         => $campaign_id,
					'name'       => 'Logo campaign ' . $campaign_id,
					'status'     => 'active',
					'image'      => $url,
					'categories' => array(),
				)
			),
		)
	);
}

Promokodiki_Admitad_Test_Harness::run(
	'valid campaign logos import once, reuse by hash, and retain the old logo after failure',
	static function (): void {
		global $wpdb;

		$suffix     = wp_generate_uuid4();
		$campaign_a = wp_rand( 910000000, 919999998 );
		$campaign_b = $campaign_a + 1;
		$term_a     = wp_insert_term( 'Managed logo A ' . $suffix, 'shops_category' );
		$term_b     = wp_insert_term( 'Managed logo B ' . $suffix, 'shops_category' );
		$term_a_id  = is_wp_error( $term_a ) ? 0 : (int) $term_a['term_id'];
		$term_b_id  = is_wp_error( $term_b ) ? 0 : (int) $term_b['term_id'];
		update_term_meta( $term_a_id, 'admitad_campaign_id', (string) $campaign_a );
		update_term_meta( $term_b_id, 'admitad_campaign_id', (string) $campaign_b );
		promokodiki_admitad_logo_campaign( $campaign_a, 'https://cdn.example.test/logo-a.png' );
		promokodiki_admitad_logo_campaign( $campaign_b, 'https://cdn.example.test/logo-b.png' );
		$downloads = 0;
		$downloader = static function ( string $url ) use ( &$downloads ) {
			++$downloads;
			if ( str_contains( $url, 'failed' ) ) {
				return new WP_Error( 'download_failed', 'Fixture failure.' );
			}
			return promokodiki_admitad_logo_png();
		};
		$service       = new Promokodiki_Admitad_Managed_Logo_Service( $downloader );
		$attachment_id = 0;

		try {
			$first         = $service->process_campaign( $campaign_a );
			$attachment_id = (int) $first['attachment_id'];
			Promokodiki_Admitad_Test_Harness::assert_same( 'downloaded', $first['state'] );
			Promokodiki_Admitad_Test_Harness::assert_true( $attachment_id > 0 );
			Promokodiki_Admitad_Test_Harness::assert_same( 'yes', get_post_meta( $attachment_id, '_admitad_managed_logo', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( $attachment_id, (int) get_term_meta( $term_a_id, '_admitad_shop_logo_id', true ) );

			$second = $service->process_campaign( $campaign_b );
			Promokodiki_Admitad_Test_Harness::assert_same( 'reused', $second['state'] );
			Promokodiki_Admitad_Test_Harness::assert_same( $attachment_id, (int) $second['attachment_id'] );
			Promokodiki_Admitad_Test_Harness::assert_same( array( $campaign_a, $campaign_b ), array_map( 'intval', get_post_meta( $attachment_id, '_admitad_campaign_ids', true ) ) );

			$unchanged = $service->process_campaign( $campaign_a );
			Promokodiki_Admitad_Test_Harness::assert_same( 'unchanged', $unchanged['state'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 2, $downloads );

			promokodiki_admitad_logo_campaign( $campaign_a, 'https://cdn.example.test/failed.png' );
			$failed = $service->process_campaign( $campaign_a );
			Promokodiki_Admitad_Test_Harness::assert_same( 'failed', $failed['state'] );
			Promokodiki_Admitad_Test_Harness::assert_same( $attachment_id, (int) get_term_meta( $term_a_id, '_admitad_shop_logo_id', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'https://cdn.example.test/logo-a.png', get_term_meta( $term_a_id, '_admitad_shop_logo_source_url', true ) );
		} finally {
			wp_delete_term( $term_a_id, 'shops_category' );
			wp_delete_term( $term_b_id, 'shops_category' );
			if ( $attachment_id > 0 ) {
				wp_delete_attachment( $attachment_id, true );
			}
			$wpdb->delete( Promokodiki_Admitad_Schema::table( 'company_profile' ), array( 'campaign_id' => $campaign_a ), array( '%d' ) );
			$wpdb->delete( Promokodiki_Admitad_Schema::table( 'company_profile' ), array( 'campaign_id' => $campaign_b ), array( '%d' ) );
		}
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'oversized, non-image, and unsupported SVG downloads are rejected without changing term media',
	static function (): void {
		global $wpdb;

		$campaign_id = wp_rand( 920000000, 929999999 );
		$term        = wp_insert_term( 'Rejected managed logo ' . wp_generate_uuid4(), 'shops_category' );
		$term_id     = is_wp_error( $term ) ? 0 : (int) $term['term_id'];
		update_term_meta( $term_id, 'admitad_campaign_id', (string) $campaign_id );
		$cases = array(
			'https://cdn.example.test/large.png' => static function (): string {
				$file = wp_tempnam( 'large.png' );
				file_put_contents( $file, str_repeat( 'x', ( 2 * MB_IN_BYTES ) + 1 ) );
				return $file;
			},
			'https://cdn.example.test/not-image.txt' => static function (): string {
				$file = wp_tempnam( 'not-image.txt' );
				file_put_contents( $file, 'plain text' );
				return $file;
			},
			'https://cdn.example.test/logo.svg' => static function (): string {
				$file = wp_tempnam( 'logo.svg' );
				file_put_contents( $file, '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>' );
				return $file;
			},
		);

		try {
			foreach ( $cases as $url => $factory ) {
				promokodiki_admitad_logo_campaign( $campaign_id, $url );
				$service = new Promokodiki_Admitad_Managed_Logo_Service( static fn() => $factory() );
				$result  = $service->process_campaign( $campaign_id );
				Promokodiki_Admitad_Test_Harness::assert_same( 'unsupported', $result['state'] );
				Promokodiki_Admitad_Test_Harness::assert_same( 0, (int) get_term_meta( $term_id, '_admitad_shop_logo_id', true ) );
			}
		} finally {
			wp_delete_term( $term_id, 'shops_category' );
			$wpdb->delete( Promokodiki_Admitad_Schema::table( 'company_profile' ), array( 'campaign_id' => $campaign_id ), array( '%d' ) );
		}
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'cleanup previews and deletes only unreferenced integration-owned attachments',
	static function (): void {
		$service       = new Promokodiki_Admitad_Managed_Logo_Service();
		$baseline      = $service->cleanup_preview();
		$png_bytes     = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z7ioAAAAASUVORK5CYII=' );
		$owned_upload  = wp_upload_bits( 'owned-orphan-' . wp_generate_uuid4() . '.png', null, $png_bytes );
		$other_upload  = wp_upload_bits( 'unrelated-' . wp_generate_uuid4() . '.png', null, $png_bytes );
		$owned_file    = (string) $owned_upload['file'];
		$unrelated_file = (string) $other_upload['file'];
		$owned_id      = wp_insert_attachment( array( 'post_title' => 'Owned orphan', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $owned_file );
		$unrelated_id  = wp_insert_attachment( array( 'post_title' => 'Unrelated image', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $unrelated_file );
		update_attached_file( $owned_id, $owned_file );
		update_attached_file( $unrelated_id, $unrelated_file );
		update_post_meta( $owned_id, '_admitad_managed_logo', 'yes' );

		try {
			$preview = $service->cleanup_preview();
			$new_orphans = array_values( array_diff( array_map( 'intval', $preview['attachment_ids'] ), array_map( 'intval', $baseline['attachment_ids'] ) ) );
			Promokodiki_Admitad_Test_Harness::assert_same( array( (int) $owned_id ), $new_orphans );
			Promokodiki_Admitad_Test_Harness::assert_true( $preview['bytes'] > 0 );

			$term = wp_insert_term( 'Cleanup race guard ' . wp_generate_uuid4(), 'shops_category' );
			$term_id = is_wp_error( $term ) ? 0 : (int) $term['term_id'];
			update_term_meta( $term_id, '_admitad_shop_logo_id', (int) $owned_id );
			$skipped = $service->cleanup( array( $owned_id, $unrelated_id ) );
			Promokodiki_Admitad_Test_Harness::assert_same( array( 'deleted' => 0, 'skipped' => 2 ), $skipped );
			wp_delete_term( $term_id, 'shops_category' );

			$deleted = $service->cleanup( array( $owned_id ) );
			Promokodiki_Admitad_Test_Harness::assert_same( array( 'deleted' => 1, 'skipped' => 0 ), $deleted );
			Promokodiki_Admitad_Test_Harness::assert_same( null, get_post( $owned_id ) );
			Promokodiki_Admitad_Test_Harness::assert_true( get_post( $unrelated_id ) instanceof WP_Post );
		} finally {
			if ( get_post( $owned_id ) ) {
				wp_delete_attachment( $owned_id, true );
			}
			if ( get_post( $unrelated_id ) ) {
				wp_delete_attachment( $unrelated_id, true );
			}
			foreach ( array( $owned_file, $unrelated_file ) as $file ) {
				if ( file_exists( $file ) ) {
					unlink( $file );
				}
			}
		}
	}
);

Promokodiki_Admitad_Test_Harness::finish();
