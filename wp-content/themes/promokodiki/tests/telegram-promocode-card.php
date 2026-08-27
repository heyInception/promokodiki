<?php
/** Telegram promocode card integration test. */

require_once dirname( __DIR__, 3 ) . '/plugins/promokodiki-telegram/tests/harness.php';
require_once dirname( __DIR__, 3 ) . '/plugins/promokodiki-telegram/promokodiki-telegram.php';

if ( function_exists( 'admitad_register_content_types' ) ) {
	admitad_register_content_types();
}

$post_id       = wp_insert_post(
	array(
		'post_type'    => 'promocode',
		'post_status'  => 'publish',
		'post_title'   => 'Telegram card image',
		'post_excerpt' => 'Telegram card image test',
	)
);
$attachment_id = 0;
$brand_post_id = 0;
$brand_term_id = 0;

try {
	$service       = new Promokodiki_Telegram_Media_Service();
	$attachment_id = $service->attach(
		(int) $post_id,
		array(
			'filename'  => 'telegram-card.png',
			'mime_type' => 'image/png',
			'data'      => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
		)
	);
	update_post_meta( $post_id, '_promocode_code', 'CARDTEST' );
	update_post_meta( $post_id, '_promocode_link', 'https://market.yandex.ru/product/1' );
	update_post_meta( $post_id, '_telegram_source_key', 'tranzhiraru:200' );

	Promokodiki_Telegram_Test_Harness::run(
		'card renders the Telegram featured image',
		static function () use ( $post_id, $attachment_id ): void {
			global $post;
			$post = get_post( $post_id );
			setup_postdata( $post );

			ob_start();
			include dirname( __DIR__ ) . '/template-parts/promocode-card.php';
			$html = (string) ob_get_clean();

			Promokodiki_Telegram_Test_Harness::assert_true( $attachment_id > 0 );
			Promokodiki_Telegram_Test_Harness::assert_true( str_contains( $html, 'class="promocodes__imgs' ) );
			Promokodiki_Telegram_Test_Harness::assert_true( str_contains( $html, wp_get_attachment_image_url( $attachment_id, 'medium' ) ) );
			wp_reset_postdata();
		}
	);

	Promokodiki_Telegram_Test_Harness::run(
		'Telegram card renders the fixed author identity',
		static function () use ( $post_id ): void {
			global $post;
			$post = get_post( $post_id );
			setup_postdata( $post );

			ob_start();
			include dirname( __DIR__ ) . '/template-parts/promocode-card.php';
			$html = (string) ob_get_clean();

			Promokodiki_Telegram_Test_Harness::assert_true( str_contains( $html, 'class="promocodes__author"' ) );
			Promokodiki_Telegram_Test_Harness::assert_true( str_contains( $html, 'https://promokodiki.com/wp-content/uploads/2026/08/telegram-svgrepo-com.svg' ) );
			Promokodiki_Telegram_Test_Harness::assert_true( str_contains( $html, 'class="top__nick">@telegram</span>' ) );
			wp_reset_postdata();
		}
	);

	$brand = wp_insert_term( 'Card Brand ' . wp_generate_uuid4(), 'shops_category' );
	if ( ! is_wp_error( $brand ) ) {
		$brand_term_id = (int) $brand['term_id'];
	}
	update_term_meta( $brand_term_id, '_admitad_shop_logo_id', $attachment_id );
	$brand_post_id = wp_insert_post(
		array(
			'post_type'    => 'promocode',
			'post_status'  => 'publish',
			'post_title'   => 'Brand card',
			'post_excerpt' => 'Brand card test',
		)
	);
	wp_set_object_terms( $brand_post_id, array( $brand_term_id ), 'shops_category' );
	update_post_meta( $brand_post_id, '_promocode_code', 'BRANDTEST' );
	update_post_meta( $brand_post_id, '_promocode_link', 'https://example.com/product/1' );

	Promokodiki_Telegram_Test_Harness::run(
		'ordinary card renders its associated brand',
		static function () use ( $brand_post_id, $brand_term_id, $attachment_id ): void {
			global $post;
			$post = get_post( $brand_post_id );
			setup_postdata( $post );

			ob_start();
			include dirname( __DIR__ ) . '/template-parts/promocode-card.php';
			$html = (string) ob_get_clean();
			$brand = get_term( $brand_term_id, 'shops_category' );

			Promokodiki_Telegram_Test_Harness::assert_true( $brand instanceof WP_Term );
			Promokodiki_Telegram_Test_Harness::assert_true( str_contains( $html, esc_html( $brand->name ) ) );
			Promokodiki_Telegram_Test_Harness::assert_true( str_contains( $html, wp_get_attachment_image_url( $attachment_id, 'medium' ) ) );
			wp_reset_postdata();
		}
	);

	Promokodiki_Telegram_Test_Harness::finish();
} finally {
	if ( $attachment_id > 0 ) {
		wp_delete_attachment( $attachment_id, true );
	}
	if ( $brand_post_id > 0 ) {
		wp_delete_post( $brand_post_id, true );
	}
	if ( $brand_term_id > 0 ) {
		wp_delete_term( $brand_term_id, 'shops_category' );
	}
	wp_delete_post( $post_id, true );
}
