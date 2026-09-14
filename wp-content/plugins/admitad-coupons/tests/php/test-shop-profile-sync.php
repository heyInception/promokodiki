<?php
/**
 * Shop term enrichment tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once __DIR__ . '/class-test-environment-guard.php';
Promokodiki_Admitad_Test_Environment_Guard::assert_disposable_database();
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';
Promokodiki_Admitad_Plugin::register();

Promokodiki_Admitad_Test_Harness::run(
	'shop descriptions keep safe editorial HTML and remove executable markup',
	static function (): void {
		$source = '<h2>О бренде</h2><p onclick="alert(1)">Магазин <strong>одежды</strong> <a href="https://example.test" onmouseover="bad()">на сайте</a>.</p>'
			. '<script>alert(2)</script><iframe src="https://evil.test"></iframe><form><input value="secret"></form>'
			. '<p><a href="javascript:alert(3)">Опасная ссылка</a></p>';
		$clean = Promokodiki_Admitad_Shop_Profile_Sync::sanitize_description( $source );

		Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $clean, '<h2>О бренде</h2>' ) );
		Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $clean, '<strong>одежды</strong>' ) );
		Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $clean, 'href="https://example.test"' ) );
		Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $clean, 'РЅР° СЃР°Р№С‚Рµ' ) );
		Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $clean, 'РћРїР°СЃРЅР°СЏ СЃСЃС‹Р»РєР°' ) );
		foreach ( array( '<script', '<iframe', '<form', '<input', 'onclick', 'onmouseover', 'javascript:' ) as $unsafe ) {
			Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $clean, $unsafe ), 'Unsafe fragment survived: ' . $unsafe );
		}
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'shop summary uses two customer-facing paragraphs and stays within a word boundary',
	static function (): void {
		$long_word_safe = str_repeat( 'выгодные покупки ', 60 );
		$source         = '<h2>О магазине</h2><p>Первый абзац для покупателей.</p>'
			. '<h3>Минус-слова</h3><p>brand, бренд, название.</p>'
			. '<h3>Преимущества</h3><p>' . $long_word_safe . '</p><p>Третий клиентский абзац не должен попасть.</p>';
		$summary = Promokodiki_Admitad_Shop_Profile_Sync::summary( $source, 700 );

		Promokodiki_Admitad_Test_Harness::assert_true( str_starts_with( $summary, 'Первый абзац для покупателей.' ) );
		Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $summary, 'brand' ) );
		Promokodiki_Admitad_Test_Harness::assert_true( ! str_contains( $summary, 'Третий клиентский' ) );
		Promokodiki_Admitad_Test_Harness::assert_true( mb_strlen( $summary ) <= 700 );
		Promokodiki_Admitad_Test_Harness::assert_true( str_ends_with( $summary, 'покупки' ) );
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'exact campaign ID is the only automatic shop link and empty fields preserve term data',
	static function (): void {
		$campaign_id = 987654330;
		$linked      = wp_insert_term( 'Exact campaign shop', 'shops_category', array( 'description' => 'Редакторское описание термина' ) );
		$similar     = wp_insert_term( 'Exact campaign shop RU', 'shops_category' );
		$linked_id   = is_wp_error( $linked ) ? 0 : (int) $linked['term_id'];
		$similar_id  = is_wp_error( $similar ) ? 0 : (int) $similar['term_id'];

		Promokodiki_Admitad_Test_Harness::assert_true( $linked_id > 0 && $similar_id > 0 );
		update_term_meta( $linked_id, 'admitad_campaign_id', (string) $campaign_id );
		update_term_meta( $linked_id, 'about_shop', 'Ручное ACF-описание' );
		update_term_meta( $linked_id, 'rating', '4.9' );

		$campaign = array(
			'external_id'    => (string) $campaign_id,
			'name'           => 'Exact Campaign RU',
			'description'    => 'Короткое описание API',
			'raw_description' => '<p>Полное <strong>описание API</strong>.</p>',
			'rating'         => 4.6,
			'image_url'      => 'https://cdn.example.test/exact.png',
			'site_url'       => 'https://exact.example.test/',
		);

		try {
			$result = ( new Promokodiki_Admitad_Shop_Profile_Sync() )->sync_campaign( $campaign );
			Promokodiki_Admitad_Test_Harness::assert_same( array( 'updated' => 1, 'unlinked' => 0, 'term_id' => $linked_id ), $result );
			Promokodiki_Admitad_Test_Harness::assert_same( '<p>Короткое описание API</p>', get_term_meta( $linked_id, '_admitad_shop_description', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'Короткое описание API', get_term_meta( $linked_id, '_admitad_shop_summary', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'Exact Campaign RU', get_term_meta( $linked_id, '_admitad_shop_campaign_name', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( '4.6', (string) get_term_meta( $linked_id, '_admitad_shop_rating', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'https://cdn.example.test/exact.png', get_term_meta( $linked_id, '_admitad_shop_image_url', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'https://exact.example.test/', get_term_meta( $linked_id, '_admitad_shop_website', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( '', get_term_meta( $similar_id, '_admitad_shop_description', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'Ручное ACF-описание', get_term_meta( $linked_id, 'about_shop', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( '4.9', get_term_meta( $linked_id, 'rating', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'Редакторское описание термина', get_term( $linked_id, 'shops_category' )->description );

			$empty = array(
				'external_id'    => (string) $campaign_id,
				'description'    => '',
				'raw_description' => '',
				'rating'         => null,
				'image_url'      => '',
				'site_url'       => '',
			);
			( new Promokodiki_Admitad_Shop_Profile_Sync() )->sync_campaign( $empty );
			Promokodiki_Admitad_Test_Harness::assert_same( '<p>Короткое описание API</p>', get_term_meta( $linked_id, '_admitad_shop_description', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( '4.6', (string) get_term_meta( $linked_id, '_admitad_shop_rating', true ) );

			$unlinked = ( new Promokodiki_Admitad_Shop_Profile_Sync() )->sync_campaign( array_merge( $campaign, array( 'external_id' => '987654331' ) ) );
			Promokodiki_Admitad_Test_Harness::assert_same( array( 'updated' => 0, 'unlinked' => 1, 'term_id' => 0 ), $unlinked );
		} finally {
			wp_delete_term( $linked_id, 'shops_category' );
			wp_delete_term( $similar_id, 'shops_category' );
		}
	}
);

Promokodiki_Admitad_Test_Harness::run(
	'confirmed Moulinex campaigns merge into the editorial term and retain a redirect',
	static function (): void {
		$old_version = get_option( 'promokodiki_admitad_shop_merge_version', null );
		$old_redirects = get_option( 'promokodiki_admitad_shop_redirects', null );
		$target = wp_insert_term( 'Moulinex', 'shops_category', array( 'slug' => 'moulinex' ) );
		$source = wp_insert_term( 'MoulinexRU', 'shops_category', array( 'slug' => 'moulinexru' ) );
		$target_id = is_wp_error( $target ) ? 0 : (int) $target['term_id'];
		$source_id = is_wp_error( $source ) ? 0 : (int) $source['term_id'];
		$post_id = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'publish', 'post_title' => 'Moulinex test offer' ) );
		try {
			Promokodiki_Admitad_Test_Harness::assert_true( $target_id > 0 && $source_id > 0 && $post_id > 0 );
			update_term_meta( $target_id, 'admitad_campaign_id', '15488' );
			update_term_meta( $source_id, 'admitad_campaign_id', '182754' );
			update_term_meta( $target_id, '_admitad_shop_website', 'https://moulinex.ru/' );
			update_term_meta( $source_id, '_admitad_shop_website', 'https://www.moulinex.ru/catalog/' );
			update_term_meta( $source_id, '_admitad_shop_description', '<p>Полезное описание</p>' );
			wp_set_object_terms( $post_id, array( $source_id ), 'shops_category' );
			delete_option( 'promokodiki_admitad_shop_merge_version' );
			delete_option( 'promokodiki_admitad_shop_redirects' );

			Promokodiki_Admitad_Shop_Merge_Migration::maybe_run();

			Promokodiki_Admitad_Test_Harness::assert_same( 'Moulinex', get_term( $target_id, 'shops_category' )->name );
			Promokodiki_Admitad_Test_Harness::assert_same( '182754', get_term_meta( $target_id, 'admitad_campaign_id', true ) );
			Promokodiki_Admitad_Test_Harness::assert_same( 'MoulinexRU', get_term_meta( $target_id, '_admitad_shop_campaign_name', true ) );
			Promokodiki_Admitad_Test_Harness::assert_true( has_term( $target_id, 'shops_category', $post_id ) );
			Promokodiki_Admitad_Test_Harness::assert_true( null === get_term( $source_id, 'shops_category' ) );
			Promokodiki_Admitad_Test_Harness::assert_same( array( 'moulinexru' => 'moulinex' ), get_option( 'promokodiki_admitad_shop_redirects' ) );
		} finally {
			wp_delete_post( $post_id, true );
			wp_delete_term( $target_id, 'shops_category' );
			wp_delete_term( $source_id, 'shops_category' );
			delete_option( 'promokodiki_admitad_shop_merge_version' );
			delete_option( 'promokodiki_admitad_shop_redirects' );
			if ( null !== $old_version ) { update_option( 'promokodiki_admitad_shop_merge_version', $old_version, false ); }
			if ( null !== $old_redirects ) { update_option( 'promokodiki_admitad_shop_redirects', $old_redirects, false ); }
		}
	}
);

Promokodiki_Admitad_Test_Harness::finish();
