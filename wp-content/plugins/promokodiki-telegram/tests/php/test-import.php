<?php
/** Telegram promocode persistence contract. */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/promokodiki-telegram.php';

if ( ! class_exists( 'Promokodiki_Telegram_Promocode_Repository' ) ) {
	throw new RuntimeException( 'Telegram promocode repository is not implemented.' );
}

if ( function_exists( 'admitad_register_content_types' ) ) {
	admitad_register_content_types();
}
Promokodiki_Telegram_Activator::ensure_category();

$created_ids = array();
$resolver    = static function ( string $url ): array {
	return array(
		'url'         => 'https://partner.example/click?target=' . rawurlencode( $url ),
		'status'      => 'admitad',
		'campaign_id' => 77,
	);
};
$repository  = new Promokodiki_Telegram_Promocode_Repository( $resolver );
$future      = time() + ( 72 * HOUR_IN_SECONDS );
$payload     = array(
	'channel'             => 'tranzhiraru',
	'message_id'          => 987654,
	'detected_code_count' => 1,
	'confidence'          => 'high',
	'title'               => 'Скидка 10% на товары Geltek',
	'excerpt'             => 'Дополнительная скидка на товары бренда.',
	'code'                => 'GELTEK10',
	'destination_url'     => 'https://shop.example/catalog/item?utm_source=telegram',
	'source_url'          => 'https://t.me/tranzhiraru/987654',
	'raw_text'            => 'Скидка 10% по промокоду GELTEK10. https://shop.example/catalog/item',
	'published_at'        => gmdate( DATE_ATOM, time() - HOUR_IN_SECONDS ),
	'edited_at'           => '',
	'views'               => 1200,
	'expires_at'          => $future,
	'discount_label'      => '10%',
	'discount_value'      => 10,
	'media'               => null,
);

try {
	Promokodiki_Telegram_Test_Harness::run(
		'accepted worker item creates a categorized promocode',
		static function () use ( $repository, $payload, &$created_ids, $future ): void {
			$result = $repository->upsert( $payload );
			Promokodiki_Telegram_Test_Harness::assert_same( 'created', $result['status'] );
			$post_id       = (int) $result['post_id'];
			$created_ids[] = $post_id;
			Promokodiki_Telegram_Test_Harness::assert_same( 'publish', get_post_status( $post_id ) );
			Promokodiki_Telegram_Test_Harness::assert_same( 'promocode', get_post_type( $post_id ) );
			Promokodiki_Telegram_Test_Harness::assert_same( 'yandex-market-' . $post_id, get_post_field( 'post_name', $post_id ) );
			Promokodiki_Telegram_Test_Harness::assert_same( 'GELTEK10', get_post_meta( $post_id, '_promocode_code', true ) );
			Promokodiki_Telegram_Test_Harness::assert_same( 'yes', get_post_meta( $post_id, '_promocode_is_active', true ) );
			Promokodiki_Telegram_Test_Harness::assert_same( 'tranzhiraru:987654', get_post_meta( $post_id, '_telegram_source_key', true ) );
			Promokodiki_Telegram_Test_Harness::assert_same( (string) $future, get_post_meta( $post_id, '_telegram_expires_at', true ) );
			Promokodiki_Telegram_Test_Harness::assert_same( 'admitad', get_post_meta( $post_id, '_telegram_affiliate_status', true ) );
			Promokodiki_Telegram_Test_Harness::assert_true( str_starts_with( get_post_meta( $post_id, '_promocode_link', true ), 'https://partner.example/' ) );
			$terms = wp_get_object_terms( $post_id, 'promocode_category', array( 'fields' => 'slugs' ) );
			Promokodiki_Telegram_Test_Harness::assert_true( in_array( 'promokody-iz-telegram', $terms, true ) );
		}
	);

	Promokodiki_Telegram_Test_Harness::run(
		'duplicate titles receive stable post ID slugs',
		static function () use ( $repository, $payload, &$created_ids ): void {
			$duplicate               = $payload;
			$duplicate['message_id'] = 987658;
			$result                  = $repository->upsert( $duplicate );
			$post_id                 = (int) $result['post_id'];
			$created_ids[]           = $post_id;

			Promokodiki_Telegram_Test_Harness::assert_same( 'yandex-market-' . $post_id, get_post_field( 'post_name', $post_id ) );
			Promokodiki_Telegram_Test_Harness::assert_true( ! str_ends_with( get_post_field( 'post_name', $post_id ), '-2' ) );
		}
	);

	Promokodiki_Telegram_Test_Harness::run(
		'explicit cart discount is stored without a promocode',
		static function () use ( $repository, $payload, &$created_ids ): void {
			$deal                          = $payload;
			$deal['message_id']            = 987659;
			$deal['offer_type']             = 'cart_discount';
			$deal['detected_code_count']   = 0;
			$deal['code']                  = '';
			$deal['title']                 = 'Неимоверно дёшево — скидка 5% в корзине';
			$deal['discount_label']        = '5%';
			$deal['discount_value']        = 5;
			$result                        = $repository->upsert( $deal );
			$post_id                       = (int) $result['post_id'];
			$created_ids[]                 = $post_id;

			Promokodiki_Telegram_Test_Harness::assert_same( 'created', $result['status'] );
			Promokodiki_Telegram_Test_Harness::assert_same( '', get_post_meta( $post_id, '_promocode_code', true ) );
			Promokodiki_Telegram_Test_Harness::assert_same( 'cart_discount', get_post_meta( $post_id, '_telegram_offer_type', true ) );
			Promokodiki_Telegram_Test_Harness::assert_same( '0', get_post_meta( $post_id, '_telegram_detected_code_count', true ) );
		}
	);

	Promokodiki_Telegram_Test_Harness::run(
		'reimport updates the same source while a manual lock preserves edits',
		static function () use ( $repository, $payload, &$created_ids ): void {
			$first   = $repository->upsert( $payload );
			$updated = $payload;
			$updated['title'] = 'Обновлённое предложение';
			$updated['views'] = 5000;
			$second = $repository->upsert( $updated );
			Promokodiki_Telegram_Test_Harness::assert_same( (int) $first['post_id'], (int) $second['post_id'] );
			Promokodiki_Telegram_Test_Harness::assert_same( 'updated', $second['status'] );
			Promokodiki_Telegram_Test_Harness::assert_same( 'Обновлённое предложение', get_the_title( $second['post_id'] ) );
			Promokodiki_Telegram_Test_Harness::assert_same( '5000', get_post_meta( $second['post_id'], '_telegram_views', true ) );

			update_post_meta( $second['post_id'], '_telegram_manual_lock', 'yes' );
			$locked          = $updated;
			$locked['title'] = 'Не должно перезаписаться';
			$third           = $repository->upsert( $locked );
			Promokodiki_Telegram_Test_Harness::assert_same( 'locked', $third['status'] );
			Promokodiki_Telegram_Test_Harness::assert_same( 'Обновлённое предложение', get_the_title( $third['post_id'] ) );
			$created_ids[] = (int) $third['post_id'];
		}
	);

	Promokodiki_Telegram_Test_Harness::run(
		'multiple detected codes are rejected before persistence',
		static function () use ( $repository, $payload ): void {
			$invalid                          = $payload;
			$invalid['message_id']            = 987655;
			$invalid['detected_code_count']   = 2;
			$invalid['code']                  = 'FIRST';
			$result = $repository->upsert( $invalid );
			Promokodiki_Telegram_Test_Harness::assert_true( is_wp_error( $result ) );
			Promokodiki_Telegram_Test_Harness::assert_same( 'telegram_multiple_codes', $result->get_error_code() );
		}
	);

	Promokodiki_Telegram_Test_Harness::run(
		'deleted unlocked source is unpublished',
		static function () use ( $repository, $payload, &$created_ids ): void {
			$deletable               = $payload;
			$deletable['message_id'] = 987656;
			$created                 = $repository->upsert( $deletable );
			$post_id                 = (int) $created['post_id'];
			$created_ids[]           = $post_id;
			$result                  = $repository->deactivate_source( 'tranzhiraru', 987656, 'deleted' );
			Promokodiki_Telegram_Test_Harness::assert_same( true, $result );
			Promokodiki_Telegram_Test_Harness::assert_same( 'draft', get_post_status( $post_id ) );
			Promokodiki_Telegram_Test_Harness::assert_same( 'no', get_post_meta( $post_id, '_promocode_is_active', true ) );
		}
	);

	Promokodiki_Telegram_Test_Harness::run(
		'media attachment failure is recorded instead of being silent',
		static function () use ( $resolver, $payload, &$created_ids ): void {
			$repository_with_failed_media = new Promokodiki_Telegram_Promocode_Repository(
				$resolver,
				static fn( int $post_id, array $media ): int => 0
			);
			$with_media               = $payload;
			$with_media['message_id'] = 987657;
			$with_media['media']      = array( 'mime_type' => 'image/jpeg', 'data' => 'invalid' );
			$result                   = $repository_with_failed_media->upsert( $with_media );
			$post_id                  = (int) $result['post_id'];
			$created_ids[]            = $post_id;

			Promokodiki_Telegram_Test_Harness::assert_same( 'failed', $result['media_status'] );
			Promokodiki_Telegram_Test_Harness::assert_same( 'failed', get_post_meta( $post_id, '_telegram_media_status', true ) );
		}
	);

	Promokodiki_Telegram_Test_Harness::finish();
} finally {
	foreach ( array_unique( $created_ids ) as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}
