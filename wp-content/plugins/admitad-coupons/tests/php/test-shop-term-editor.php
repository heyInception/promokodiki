<?php
/** Shop taxonomy editor tests. @package Promokodiki_Admitad */
require_once dirname( __DIR__ ) . '/harness.php';
require_once __DIR__ . '/class-test-environment-guard.php';
Promokodiki_Admitad_Test_Environment_Guard::assert_disposable_database();
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';
if ( ! taxonomy_exists( 'shops_category' ) ) { register_taxonomy( 'shops_category', 'promocode', array( 'public' => true ) ); }
Promokodiki_Admitad_Capabilities::install();

Promokodiki_Admitad_Test_Harness::run(
	'shop editor exposes Campaign ID source manual content contacts and deeplink controls',
	static function (): void {
		$term = wp_insert_term( 'Editor shop ' . wp_generate_uuid4(), 'shops_category' );
		$term_id = (int) $term['term_id'];
		$admin = wp_insert_user( array( 'user_login' => 'shop-term-admin-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
		update_term_meta( $term_id, '_admitad_shop_source_description', '<p>Исходное описание</p>' );
		try {
			wp_set_current_user( $admin );
			ob_start();
			Promokodiki_Admitad_Shop_Term_Editor::render( get_term( $term_id, 'shops_category' ) );
			$html = (string) ob_get_clean();
			foreach ( array( 'Описание из Admitad', 'admitad_campaign_id', '_admitad_shop_manual_description', 'Скопировать исходное', 'shop_address', 'shop_phone', 'shop_email', 'shop_website', '_admitad_shop_manual_affiliate_url', 'Перегенерировать' ) as $needle ) {
				Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $html, $needle ), 'Missing editor control: ' . $needle );
			}
			Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $html, 'Исходное описание' ) );
		} finally {
			wp_delete_user( $admin );
			wp_delete_term( $term_id, 'shops_category' );
		}
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'shop editor save requires capability nonce and sanitizes manual values',
	static function (): void {
		$term = wp_insert_term( 'Save shop ' . wp_generate_uuid4(), 'shops_category' );
		$term_id = (int) $term['term_id'];
		$editor = wp_insert_user( array( 'user_login' => 'shop-term-editor-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
		$admin = wp_insert_user( array( 'user_login' => 'shop-term-save-admin-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
		( new Promokodiki_Admitad_Reference_Repository() )->sync_campaigns( array( array( 'external_id' => 12345, 'name' => 'Editor Campaign', 'categories' => array(), 'source_status' => 'active', 'description' => '', 'raw_description' => '', 'rating' => null, 'image_url' => '', 'site_url' => 'https://shop.example.test/' ) ) );
		$payload = array(
			'admitad_campaign_id' => '12345',
			'_admitad_shop_manual_description' => '<p>Текст <a href="https://bad.test">ссылка</a></p>',
			'shop_phone' => '+7 999 123-45-67', 'shop_email' => 'help@example.test', 'shop_website' => 'https://shop.example.test/',
			'_admitad_shop_manual_affiliate_url' => 'https://ad.admitad.com/g/manual/',
		);
		try {
			wp_set_current_user( $editor );
			Promokodiki_Admitad_Shop_Term_Editor::save( $term_id, $payload + array( '_admitad_shop_editor_nonce' => wp_create_nonce( 'promokodiki_admitad_shop_editor' ) ) );
			Promokodiki_Admitad_Test_Harness::assert_same( '', get_term_meta( $term_id, 'admitad_campaign_id', true ) );
			wp_set_current_user( $admin );
			Promokodiki_Admitad_Shop_Term_Editor::save( $term_id, $payload + array( '_admitad_shop_editor_nonce' => 'bad' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( '', get_term_meta( $term_id, 'admitad_campaign_id', true ) );
			Promokodiki_Admitad_Shop_Term_Editor::save( $term_id, $payload + array( '_admitad_shop_editor_nonce' => wp_create_nonce( 'promokodiki_admitad_shop_editor' ) ) );
			Promokodiki_Admitad_Test_Harness::assert_same( '12345', get_term_meta( $term_id, 'admitad_campaign_id', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( '<p>Текст </p>', get_term_meta( $term_id, '_admitad_shop_manual_description', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'help@example.test', get_term_meta( $term_id, 'shop_email', true ) );
			$audit = get_term_meta( $term_id, '_admitad_shop_manual_audit', true );
			Promokodiki_Admitad_Test_Harness::assert_same( $admin, $audit['user_id'] );
			Promokodiki_Admitad_Test_Harness::assert_same( 'manual', $audit['source'] );
		} finally {
			wp_set_current_user( 0 ); wp_delete_user( $editor ); wp_delete_user( $admin ); wp_delete_term( $term_id, 'shops_category' );
		}
	}
);
Promokodiki_Admitad_Test_Harness::finish();
