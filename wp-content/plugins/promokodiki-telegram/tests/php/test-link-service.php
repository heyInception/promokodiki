<?php
/** Admitad link conversion contract. */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/promokodiki-telegram.php';

if ( ! class_exists( 'Promokodiki_Telegram_Link_Service' ) ) {
	throw new RuntimeException( 'Telegram link conversion is not implemented.' );
}

$profiles = array(
	array( 'campaign_id' => 42, 'site_url' => 'https://www.shop.example/catalog', 'status' => 'active' ),
	array( 'campaign_id' => 99, 'site_url' => 'https://inactive.example', 'status' => 'inactive' ),
);

Promokodiki_Telegram_Test_Harness::run(
	'active campaign domain produces Admitad deeplink',
	static function () use ( $profiles ): void {
		$service = new Promokodiki_Telegram_Link_Service(
			static fn(): array => $profiles,
			static fn( int $campaign_id, string $url ): array => array( 'link' => 'https://ad.admitad.com/g/' . $campaign_id . '/?ulp=' . rawurlencode( $url ) )
		);
		$result = $service->resolve( 'https://shop.example/product?utm_source=telegram#offer' );
		Promokodiki_Telegram_Test_Harness::assert_same( 'admitad', $result['status'] );
		Promokodiki_Telegram_Test_Harness::assert_same( 42, $result['campaign_id'] );
		Promokodiki_Telegram_Test_Harness::assert_true( str_starts_with( $result['url'], 'https://ad.admitad.com/' ) );
	}
);

Promokodiki_Telegram_Test_Harness::run(
	'no campaign falls back to cleaned direct link',
	static function () use ( $profiles ): void {
		$service = new Promokodiki_Telegram_Link_Service( static fn(): array => $profiles );
		$result  = $service->resolve( 'https://other.example/deal?utm_campaign=tg&sku=10#fragment' );
		Promokodiki_Telegram_Test_Harness::assert_same( 'direct', $result['status'] );
		Promokodiki_Telegram_Test_Harness::assert_same( 0, $result['campaign_id'] );
		Promokodiki_Telegram_Test_Harness::assert_same( 'https://other.example/deal?sku=10', $result['url'] );
	}
);

Promokodiki_Telegram_Test_Harness::run(
	'Admitad failure falls back to cleaned direct link',
	static function () use ( $profiles ): void {
		$service = new Promokodiki_Telegram_Link_Service(
			static fn(): array => $profiles,
			static fn(): WP_Error => new WP_Error( 'offline', 'API offline' )
		);
		$result = $service->resolve( 'https://www.shop.example/item?utm_medium=social&id=7' );
		Promokodiki_Telegram_Test_Harness::assert_same( 'direct_api_error', $result['status'] );
		Promokodiki_Telegram_Test_Harness::assert_same( 42, $result['campaign_id'] );
		Promokodiki_Telegram_Test_Harness::assert_same( 'https://www.shop.example/item?id=7', $result['url'] );
	}
);

Promokodiki_Telegram_Test_Harness::run(
	'Yandex Market links discard the entire query and fragment',
	static function () use ( $profiles ): void {
		$service = new Promokodiki_Telegram_Link_Service( static fn(): array => $profiles );
		$result  = $service->resolve( 'https://market.yandex.ru/wishlist/75ecbad4-9924-5f0d-75ec-bad499245f0d?publicId=abc&clid=2580165#offer' );
		Promokodiki_Telegram_Test_Harness::assert_same(
			'https://market.yandex.ru/wishlist/75ecbad4-9924-5f0d-75ec-bad499245f0d',
			$result['url']
		);
	}
);

Promokodiki_Telegram_Test_Harness::finish();
