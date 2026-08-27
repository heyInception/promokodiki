<?php
/** Telegram-only top slider integration test. */

require_once dirname( __DIR__, 3 ) . '/plugins/promokodiki-telegram/tests/harness.php';
require_once dirname( __DIR__, 3 ) . '/plugins/promokodiki-telegram/promokodiki-telegram.php';
require_once dirname( __DIR__ ) . '/inc/top.php';

if ( function_exists( 'admitad_register_content_types' ) ) { admitad_register_content_types(); }
Promokodiki_Telegram_Activator::ensure_category();
$original_settings = get_option( 'promokodiki_telegram_settings', null );
$ids = array();

try {
	Promokodiki_Telegram_Config::save_settings( array( 'card_count' => 4 ) );
	for ( $index = 1; $index <= 5; $index++ ) {
		$result = ( new Promokodiki_Telegram_Promocode_Repository() )->upsert( array(
			'channel' => 'tranzhiraru', 'message_id' => 92000 + $index, 'detected_code_count' => 1, 'confidence' => 'high',
			'title' => 'Telegram offer ' . $index, 'excerpt' => 'Скидка без указания источника', 'code' => 'TOPCODE' . $index,
			'destination_url' => 'https://merchant.example/' . $index, 'source_url' => 'https://t.me/tranzhiraru/' . $index,
			'raw_text' => 'Промокод', 'published_at' => gmdate( DATE_ATOM, time() - $index * 60 ), 'edited_at' => '', 'views' => 100 - $index,
			'expires_at' => time() + DAY_IN_SECONDS, 'discount_label' => '10%', 'discount_value' => 10,
		) );
		$ids[] = (int) $result['post_id'];
	}
	$deal = ( new Promokodiki_Telegram_Promocode_Repository() )->upsert( array(
		'channel' => 'tranzhiraru', 'message_id' => 92100, 'offer_type' => 'cart_discount', 'detected_code_count' => 0, 'confidence' => 'high',
		'title' => 'Cart discount', 'excerpt' => 'Скидка 5% в корзине', 'code' => '',
		'destination_url' => 'https://market.yandex.ru/deal', 'source_url' => 'https://t.me/tranzhiraru/100',
		'raw_text' => 'Скидка 5% в корзине', 'published_at' => gmdate( DATE_ATOM, time() - 30 ), 'edited_at' => '', 'views' => 200,
		'expires_at' => time() + DAY_IN_SECONDS, 'discount_label' => '5%', 'discount_value' => 5,
	) );
	$deal_id = (int) $deal['post_id'];
	$ids[]   = $deal_id;

	Promokodiki_Telegram_Test_Harness::run( 'top renders configured Telegram slides without source attribution', static function (): void {
		ob_start();
		promokodiki_render_telegram_top();
		$html = (string) ob_get_clean();
		Promokodiki_Telegram_Test_Harness::assert_same( 4, substr_count( $html, 'swiper-slide top__slide' ) );
		Promokodiki_Telegram_Test_Harness::assert_true( str_contains( $html, 'top__slider swiper' ) );
		Promokodiki_Telegram_Test_Harness::assert_true( str_contains( $html, 'data-post-id=' ) && str_contains( $html, 'data-action="like"' ) );
		Promokodiki_Telegram_Test_Harness::assert_true( ! str_contains( $html, 'tranzhiraru' ) && ! str_contains( $html, 't.me/' ) );
		Promokodiki_Telegram_Test_Harness::assert_true( str_contains( $html, 'class="top__author"' ) );
		Promokodiki_Telegram_Test_Harness::assert_true( str_contains( $html, '>Telegram</span>' ) );
		Promokodiki_Telegram_Test_Harness::assert_true( str_contains( $html, 'class="top__quantity"' ) && str_contains( $html, 'class="top__max"' ) );
	} );

	Promokodiki_Telegram_Test_Harness::run( 'code-less top card links directly to the store', static function () use ( $deal_id ): void {
		ob_start();
		promokodiki_render_telegram_card( $deal_id );
		$html = (string) ob_get_clean();
		Promokodiki_Telegram_Test_Harness::assert_true( str_contains( $html, '>Перейти в магазин</a>' ) );
		Promokodiki_Telegram_Test_Harness::assert_true( str_contains( $html, 'target="_blank"' ) );
		Promokodiki_Telegram_Test_Harness::assert_true( ! str_contains( $html, 'promocodes__view' ) );
	} );

	Promokodiki_Telegram_Test_Harness::finish();
} finally {
	foreach ( $ids as $id ) { wp_delete_post( $id, true ); }
	if ( null === $original_settings ) { delete_option( 'promokodiki_telegram_settings' ); } else { update_option( 'promokodiki_telegram_settings', $original_settings, false ); }
}
