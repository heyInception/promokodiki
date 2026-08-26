<?php
/** Deeplink persistence and fallback tests. @package Promokodiki_Admitad */
require_once dirname( __DIR__ ) . '/harness.php';
require_once __DIR__ . '/class-test-environment-guard.php';
Promokodiki_Admitad_Test_Environment_Guard::assert_disposable_database();
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';
if ( ! taxonomy_exists( 'shops_category' ) ) { register_taxonomy( 'shops_category', 'promocode', array( 'public' => true ) ); }

Promokodiki_Admitad_Test_Harness::run(
	'deeplink service persists success and resolves manual automatic direct priority',
	static function (): void {
		update_option( 'promokodiki_admitad_website_id', '2811611', false );
		$term = wp_insert_term( 'Deeplink shop ' . wp_generate_uuid4(), 'shops_category' );
		$id   = (int) $term['term_id'];
		update_term_meta( $id, 'admitad_campaign_id', '7775' );
		update_term_meta( $id, 'shop_website', 'https://shop.example.test/' );
		$service = new Promokodiki_Admitad_Deeplink_Service(
			static fn(): array => array( 'link' => 'https://ad.admitad.com/g/automatic/' )
		);
		try {
			Promokodiki_Admitad_Test_Harness::assert_same( 'created', $service->process_term( $id )['result'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'https://ad.admitad.com/g/automatic/', $service->resolved_url( $id ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'unchanged', $service->process_term( $id )['result'] );
			update_term_meta( $id, '_admitad_shop_manual_affiliate_url', 'https://manual.example.test/affiliate/' );
			Promokodiki_Admitad_Test_Harness::assert_same( 'https://manual.example.test/affiliate/', $service->resolved_url( $id ) );
		} finally {
			wp_delete_term( $id, 'shops_category' );
			delete_option( 'promokodiki_admitad_website_id' );
		}
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'transient errors preserve a previously working deeplink and unsupported falls back to direct URL',
	static function (): void {
		update_option( 'promokodiki_admitad_website_id', '2811611', false );
		$term = wp_insert_term( 'Fallback shop ' . wp_generate_uuid4(), 'shops_category' );
		$id   = (int) $term['term_id'];
		update_term_meta( $id, 'admitad_campaign_id', '8888' );
		update_term_meta( $id, 'shop_website', 'https://fallback.example.test/' );
		try {
			$ok = new Promokodiki_Admitad_Deeplink_Service( static fn(): array => array( 'link' => 'https://ad.admitad.com/g/old/' ) );
			$ok->process_term( $id );
			update_term_meta( $id, 'shop_website', 'https://fallback.example.test/new/' );
			$error = new Promokodiki_Admitad_Deeplink_Service( static fn() => new WP_Error( 'admitad_retryable', 'Later.' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'error', $error->process_term( $id )['result'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'https://ad.admitad.com/g/old/', $error->resolved_url( $id ) );

			delete_term_meta( $id, '_admitad_shop_deeplink' );
			$unsupported = new Promokodiki_Admitad_Deeplink_Service(
				static fn() => new WP_Error( 'admitad_http_error', 'No deeplink.', array( 'status' => 403 ) )
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 'unsupported', $unsupported->process_term( $id )['result'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'https://fallback.example.test/new/', $unsupported->resolved_url( $id ) );
		} finally {
			wp_delete_term( $id, 'shops_category' );
			delete_option( 'promokodiki_admitad_website_id' );
		}
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'deeplink queue is deduplicated and processes a bounded batch',
	static function (): void {
		$processed = array();
		$worker    = new Promokodiki_Admitad_Deeplink_Queue(
			static function ( int $term_id ) use ( &$processed ): void { $processed[] = $term_id; }
		);
		delete_option( 'promokodiki_admitad_deeplink_queue' );
		$worker->enqueue( 91 ); $worker->enqueue( 92 ); $worker->enqueue( 91 );
		Promokodiki_Admitad_Test_Harness::assert_same( 2, $worker->pending_count() );
		$worker->run_batch( 1 );
		Promokodiki_Admitad_Test_Harness::assert_same( array( 91 ), $processed );
		Promokodiki_Admitad_Test_Harness::assert_same( 1, $worker->pending_count() );
		$worker->run_batch( 20 );
		Promokodiki_Admitad_Test_Harness::assert_same( array( 91, 92 ), $processed );
		delete_option( 'promokodiki_admitad_deeplink_queue' );
	}
);

Promokodiki_Admitad_Test_Harness::finish();
