<?php
/** Worker REST batch contract. */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/promokodiki-telegram.php';

if ( ! class_exists( 'Promokodiki_Telegram_REST_Controller' ) || ! class_exists( 'Promokodiki_Telegram_Log' ) ) {
	throw new RuntimeException( 'Telegram REST batch handling is not implemented.' );
}

if ( function_exists( 'admitad_register_content_types' ) ) {
	admitad_register_content_types();
}
Promokodiki_Telegram_Activator::ensure_category();

$original_channels = get_option( 'promokodiki_telegram_channels', null );
$original_log      = get_option( 'promokodiki_telegram_log', null );
$created_ids       = array();
$repository        = new Promokodiki_Telegram_Promocode_Repository();
$controller        = new Promokodiki_Telegram_REST_Controller( $repository );

try {
	Promokodiki_Telegram_Config::save_channels(
		array(
			'tranzhiraru' => array(
				'username'        => 'tranzhiraru',
				'enabled'         => true,
				'last_message_id' => 0,
			),
		)
	);
	delete_option( 'promokodiki_telegram_log' );

	Promokodiki_Telegram_Test_Harness::run(
		'worker config returns enabled channels and import bounds',
		static function () use ( $controller ): void {
			$response = $controller->config( new WP_REST_Request( 'GET', '/promokodiki-telegram/v1/config' ) );
			$data     = $response->get_data();
			Promokodiki_Telegram_Test_Harness::assert_same( 200, $response->get_status() );
			Promokodiki_Telegram_Test_Harness::assert_same( 200, $data['initial_limit'] );
			Promokodiki_Telegram_Test_Harness::assert_same( 7, $data['initial_days'] );
			Promokodiki_Telegram_Test_Harness::assert_same( 'tranzhiraru', $data['channels'][0]['username'] );
			Promokodiki_Telegram_Test_Harness::assert_true( isset( $data['channels'][0]['tracked_message_ids'] ) );
		}
	);

	Promokodiki_Telegram_Test_Harness::run(
		'import batch persists items, status, and skipped diagnostics',
		static function () use ( $controller, &$created_ids ): void {
			$request = new WP_REST_Request( 'POST', '/promokodiki-telegram/v1/import' );
			$request->set_body_params(
				array(
					'channel'             => 'tranzhiraru',
					'newest_message_id'   => 7654321,
					'inspected_count'     => 20,
					'skipped'             => array( 'multiple_codes' => 2, 'missing_link' => 3 ),
					'inactive_message_ids'=> array(),
					'items'               => array(
						array(
							'channel'             => 'tranzhiraru',
							'message_id'          => 7654321,
							'detected_code_count' => 1,
							'confidence'          => 'high',
							'title'               => 'Скидка на товары',
							'excerpt'             => 'Скидка 15% на товары.',
							'code'                => 'TGCODE15',
							'destination_url'     => 'https://shop.example/product',
							'source_url'          => 'https://t.me/tranzhiraru/7654321',
							'raw_text'            => 'Промокод TGCODE15',
							'published_at'        => gmdate( DATE_ATOM, time() - 300 ),
							'edited_at'           => '',
							'views'               => 250,
							'expires_at'          => time() + DAY_IN_SECONDS,
							'discount_label'      => '15%',
							'discount_value'      => 15,
						),
					),
				)
			);

			$response = $controller->import( $request );
			$data     = $response->get_data();
			Promokodiki_Telegram_Test_Harness::assert_same( 200, $response->get_status() );
			Promokodiki_Telegram_Test_Harness::assert_same( 1, $data['imported'] );
			Promokodiki_Telegram_Test_Harness::assert_same( 5, $data['skipped'] );
			$created_ids[] = (int) $data['post_ids'][0];

			$channel = Promokodiki_Telegram_Config::channels()['tranzhiraru'];
			Promokodiki_Telegram_Test_Harness::assert_same( 7654321, $channel['last_message_id'] );
			Promokodiki_Telegram_Test_Harness::assert_same( 'success', $channel['last_status'] );
			Promokodiki_Telegram_Test_Harness::assert_same( 1, $channel['imported_count'] );
			Promokodiki_Telegram_Test_Harness::assert_same( 5, $channel['skipped_count'] );

			$entries = Promokodiki_Telegram_Log::entries();
			Promokodiki_Telegram_Test_Harness::assert_same( 'success', $entries[0]['status'] );
			Promokodiki_Telegram_Test_Harness::assert_same( 'tranzhiraru', $entries[0]['channel'] );
		}
	);

	Promokodiki_Telegram_Test_Harness::finish();
} finally {
	foreach ( array_unique( $created_ids ) as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	if ( null === $original_channels ) {
		delete_option( 'promokodiki_telegram_channels' );
	} else {
		update_option( 'promokodiki_telegram_channels', $original_channels, false );
	}
	if ( null === $original_log ) {
		delete_option( 'promokodiki_telegram_log' );
	} else {
		update_option( 'promokodiki_telegram_log', $original_log, false );
	}
}
