<?php
/** Telegram plugin activation contract. */

require_once dirname( __DIR__ ) . '/harness.php';

$plugin_file = dirname( __DIR__, 2 ) . '/promokodiki-telegram.php';
if ( ! file_exists( $plugin_file ) ) {
	throw new RuntimeException( 'Telegram plugin bootstrap does not exist.' );
}

require_once $plugin_file;

if ( function_exists( 'admitad_register_content_types' ) ) {
	admitad_register_content_types();
}

$original_channels = get_option( 'promokodiki_telegram_channels', null );
$original_settings = get_option( 'promokodiki_telegram_settings', null );
$original_version  = get_option( 'promokodiki_telegram_db_version', null );
$existing_term     = get_term_by( 'slug', 'promokody-iz-telegram', 'promocode_category' );
$legacy_post_id    = wp_insert_post(
	array(
		'post_type'   => 'promocode',
		'post_status' => 'publish',
		'post_title'  => 'Repeated Telegram code',
		'post_name'   => 'skidka-20-po-promokodu-test-2',
	)
);
$locked_post_id    = wp_insert_post(
	array(
		'post_type'   => 'promocode',
		'post_status' => 'publish',
		'post_title'  => 'Ручной заголовок',
	)
);
update_post_meta( $legacy_post_id, '_telegram_source_key', 'tranzhiraru:100' );
update_post_meta( $legacy_post_id, '_telegram_raw_text', "Кофе Орнелио 1 кг!\nСкидка 20% по промокоду TEST20" );
update_post_meta( $legacy_post_id, '_telegram_discount_value', 20 );
update_post_meta( $legacy_post_id, '_promocode_code', 'TEST20' );
update_post_meta( $locked_post_id, '_telegram_source_key', 'tranzhiraru:101' );
update_post_meta( $locked_post_id, '_telegram_raw_text', "Товар\nСкидка 10% по промокоду LOCK10" );
update_post_meta( $locked_post_id, '_telegram_discount_value', 10 );
update_post_meta( $locked_post_id, '_promocode_code', 'LOCK10' );
update_post_meta( $locked_post_id, '_telegram_manual_lock', 'yes' );
Promokodiki_Telegram_Config::save_channels(
	array(
		'tranzhiraru' => array(
			'username'        => 'tranzhiraru',
			'enabled'         => true,
			'last_message_id' => 999,
		),
	)
);
delete_option( 'promokodiki_telegram_db_version' );

try {
	Promokodiki_Telegram_Activator::activate();

	Promokodiki_Telegram_Test_Harness::run(
		'activation creates the Telegram category and defaults',
		static function (): void {
			$term = get_term_by( 'slug', 'promokody-iz-telegram', 'promocode_category' );
			Promokodiki_Telegram_Test_Harness::assert_true( $term instanceof WP_Term );
			Promokodiki_Telegram_Test_Harness::assert_same( 'Промокоды из Telegram', $term->name );
			Promokodiki_Telegram_Test_Harness::assert_same( $term->term_id, Promokodiki_Telegram_Config::category_term_id() );

			$channels = Promokodiki_Telegram_Config::channels();
			Promokodiki_Telegram_Test_Harness::assert_true( isset( $channels['tranzhiraru'] ) );
			Promokodiki_Telegram_Test_Harness::assert_true( true === $channels['tranzhiraru']['enabled'] );
			Promokodiki_Telegram_Test_Harness::assert_same( 4, Promokodiki_Telegram_Config::card_count() );
			Promokodiki_Telegram_Test_Harness::assert_true( strlen( Promokodiki_Telegram_Config::secret() ) >= 32 );
		}
	);

	Promokodiki_Telegram_Test_Harness::run(
		'activation migrates existing Telegram permalinks',
		static function () use ( $legacy_post_id ): void {
			Promokodiki_Telegram_Test_Harness::assert_same( 'yandex-market-' . $legacy_post_id, get_post_field( 'post_name', $legacy_post_id ) );
		}
	);

	Promokodiki_Telegram_Test_Harness::run(
		'upgrade refreshes unlocked titles and schedules one backfill',
		static function () use ( $legacy_post_id, $locked_post_id ): void {
			Promokodiki_Telegram_Test_Harness::assert_same( 'Кофе Орнелио 1 кг — скидка 20% по промокоду', get_the_title( $legacy_post_id ) );
			Promokodiki_Telegram_Test_Harness::assert_same( 'Ручной заголовок', get_the_title( $locked_post_id ) );
			$channels = Promokodiki_Telegram_Config::channels();
			Promokodiki_Telegram_Test_Harness::assert_same( 0, (int) $channels['tranzhiraru']['last_message_id'] );
			$channels['tranzhiraru']['last_message_id'] = 321;
			Promokodiki_Telegram_Config::save_channels( $channels );
			Promokodiki_Telegram_Activator::maybe_upgrade();
			$channels = Promokodiki_Telegram_Config::channels();
			Promokodiki_Telegram_Test_Harness::assert_same( 321, (int) $channels['tranzhiraru']['last_message_id'] );
		}
	);

	Promokodiki_Telegram_Test_Harness::run(
		'card count is constrained to the approved slider range',
		static function (): void {
			Promokodiki_Telegram_Config::save_settings( array( 'card_count' => 2 ) );
			Promokodiki_Telegram_Test_Harness::assert_same( 4, Promokodiki_Telegram_Config::card_count() );
			Promokodiki_Telegram_Config::save_settings( array( 'card_count' => 50 ) );
			Promokodiki_Telegram_Test_Harness::assert_same( 20, Promokodiki_Telegram_Config::card_count() );
		}
	);

	Promokodiki_Telegram_Test_Harness::finish();
} finally {
	wp_clear_scheduled_hook( 'promokodiki_telegram_expire' );
	if ( null === $original_channels ) {
		delete_option( 'promokodiki_telegram_channels' );
	} else {
		update_option( 'promokodiki_telegram_channels', $original_channels, false );
	}
	if ( null === $original_settings ) {
		delete_option( 'promokodiki_telegram_settings' );
	} else {
		update_option( 'promokodiki_telegram_settings', $original_settings, false );
	}
	if ( null === $original_version ) {
		delete_option( 'promokodiki_telegram_db_version' );
	} else {
		update_option( 'promokodiki_telegram_db_version', $original_version, false );
	}
	wp_delete_post( $legacy_post_id, true );
	wp_delete_post( $locked_post_id, true );
	if ( ! $existing_term ) {
		$created = get_term_by( 'slug', 'promokody-iz-telegram', 'promocode_category' );
		if ( $created instanceof WP_Term ) {
			wp_delete_term( $created->term_id, 'promocode_category' );
		}
	}
}
