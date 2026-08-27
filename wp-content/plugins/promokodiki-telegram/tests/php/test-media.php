<?php
/** Telegram media persistence contract. */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/promokodiki-telegram.php';

if ( ! class_exists( 'Promokodiki_Telegram_Media_Service' ) ) {
	throw new RuntimeException( 'Telegram media persistence is not implemented.' );
}

$post_id       = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'draft', 'post_title' => 'Media test' ) );
$attachment_id = 0;

try {
	Promokodiki_Telegram_Test_Harness::run(
		'one bounded image is stored and reused',
		static function () use ( $post_id, &$attachment_id ): void {
			$service = new Promokodiki_Telegram_Media_Service();
			$media   = array(
				'filename'  => 'telegram-thumb.png',
				'mime_type' => 'image/png',
				'data'      => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
			);
			$attachment_id = $service->attach( (int) $post_id, $media );
			Promokodiki_Telegram_Test_Harness::assert_true( $attachment_id > 0 );
			Promokodiki_Telegram_Test_Harness::assert_same( $attachment_id, (int) get_post_thumbnail_id( $post_id ) );
			Promokodiki_Telegram_Test_Harness::assert_same( $attachment_id, $service->attach( (int) $post_id, $media ) );
		}
	);

	Promokodiki_Telegram_Test_Harness::run(
		'invalid media is ignored',
		static function () use ( $post_id ): void {
			$service = new Promokodiki_Telegram_Media_Service();
			Promokodiki_Telegram_Test_Harness::assert_same( 0, $service->attach( (int) $post_id, array( 'mime_type' => 'text/html', 'data' => 'PGgxPg==' ) ) );
		}
	);

	Promokodiki_Telegram_Test_Harness::finish();
} finally {
	if ( $attachment_id > 0 ) {
		wp_delete_attachment( $attachment_id, true );
	}
	wp_delete_post( $post_id, true );
}
