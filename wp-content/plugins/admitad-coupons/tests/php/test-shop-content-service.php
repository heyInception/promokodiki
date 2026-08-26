<?php
/** Shop content sanitization and contact enrichment tests. @package Promokodiki_Admitad */
require_once dirname( __DIR__ ) . '/harness.php';
require_once __DIR__ . '/class-test-environment-guard.php';
Promokodiki_Admitad_Test_Environment_Guard::assert_disposable_database();
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';
if ( ! taxonomy_exists( 'shops_category' ) ) {
	register_taxonomy( 'shops_category', 'promocode', array( 'public' => true ) );
}

Promokodiki_Admitad_Test_Harness::run(
	'shop content removes links with their text and keeps limited editorial markup',
	static function (): void {
		$clean = Promokodiki_Admitad_Shop_Content_Service::sanitize( '<p>До <a href="https://example.test">удалить меня</a> после</p><ul><li><strong>Пункт</strong></li></ul><img src=x><script>alert(1)</script>' );
		Promokodiki_Admitad_Test_Harness::assert_same( '<p>До  после</p><ul><li><strong>Пункт</strong></li></ul>', $clean );
		Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $clean, 'удалить меня' ) );
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'shop content removes partner-facing service sections',
	static function (): void {
		$clean = Promokodiki_Admitad_Shop_Content_Service::sanitize( '<h2>О магазине</h2><p>Описание для покупателя.</p><h3>Условия для веб-мастеров</h3><p>Запрещённый трафик и служебные правила.</p><h2>Доставка</h2><p>По всей России.</p>' );
		Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $clean, 'Описание для покупателя' ) );
		Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $clean, 'веб-мастеров' ) );
		Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $clean, 'служебные правила' ) );
		Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $clean, 'По всей России' ) );
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'contacts accept one unambiguous phone and email but never infer an address',
	static function (): void {
		$contacts = Promokodiki_Admitad_Shop_Content_Service::extract_contacts( '<p>Телефон: +7 (495) 123-45-67. Email: help@example.test. Адрес: Москва.</p>' );
		Promokodiki_Admitad_Test_Harness::assert_same( '+7 (495) 123-45-67', $contacts['phone'] );
		Promokodiki_Admitad_Test_Harness::assert_same( 'help@example.test', $contacts['email'] );
		Promokodiki_Admitad_Test_Harness::assert_true( ! isset( $contacts['address'] ) );

		$ambiguous = Promokodiki_Admitad_Shop_Content_Service::extract_contacts( 'a@example.test b@example.test +7 495 111-11-11 +7 495 222-22-22' );
		Promokodiki_Admitad_Test_Harness::assert_same( '', $ambiguous['phone'] );
		Promokodiki_Admitad_Test_Harness::assert_same( '', $ambiguous['email'] );
		Promokodiki_Admitad_Test_Harness::assert_same( 2, count( $ambiguous['phone_candidates'] ) );
		Promokodiki_Admitad_Test_Harness::assert_same( 2, count( $ambiguous['email_candidates'] ) );
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'contact enrichment fills only empty editable fields',
	static function (): void {
		$term = wp_insert_term( 'Contact shop ' . wp_generate_uuid4(), 'shops_category' );
		$term_id = (int) $term['term_id'];
		update_term_meta( $term_id, 'shop_phone', '+7 999 000-00-00' );
		try {
			$result = Promokodiki_Admitad_Shop_Content_Service::fill_empty_contacts(
				$term_id,
				array( 'site_url' => 'https://shop.example.test/', 'raw_description' => '<p>+7 (495) 123-45-67 help@example.test</p>' )
			);
			Promokodiki_Admitad_Test_Harness::assert_same( '+7 999 000-00-00', get_term_meta( $term_id, 'shop_phone', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'help@example.test', get_term_meta( $term_id, 'shop_email', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'https://shop.example.test/', get_term_meta( $term_id, 'shop_website', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( array( 'website' => true, 'phone' => false, 'email' => true ), $result );
			Promokodiki_Admitad_Test_Harness::assert_same( '', get_term_meta( $term_id, 'shop_address', true ) );
		} finally {
			wp_delete_term( $term_id, 'shops_category' );
		}
	}
);

Promokodiki_Admitad_Test_Harness::finish();
