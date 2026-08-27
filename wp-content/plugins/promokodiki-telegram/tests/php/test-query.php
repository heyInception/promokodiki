<?php
/** Telegram ranking, expiry, and visibility contract. */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/promokodiki-telegram.php';
require_once dirname( __DIR__, 3 ) . '/promokodiki-ajax-filter/promokodiki-ajax-filter.php';

if ( ! class_exists( 'Promokodiki_Telegram_Query' ) || ! class_exists( 'Promokodiki_Telegram_Ranking' ) ) {
	throw new RuntimeException( 'Telegram ranking and visibility are not implemented.' );
}

if ( function_exists( 'admitad_register_content_types' ) ) {
	admitad_register_content_types();
}
Promokodiki_Telegram_Activator::ensure_category();
$created_ids = array();

$make = static function ( int $message_id, int $views, int $published, bool $pinned = false ) use ( &$created_ids ): int {
	$result = ( new Promokodiki_Telegram_Promocode_Repository() )->upsert(
		array(
			'channel' => 'tranzhiraru', 'message_id' => $message_id, 'detected_code_count' => 1, 'confidence' => 'high',
			'title' => 'Telegram ' . $message_id, 'excerpt' => 'Offer', 'code' => 'CODE' . $message_id,
			'destination_url' => 'https://unknown.example/' . $message_id, 'source_url' => 'https://t.me/tranzhiraru/' . $message_id,
			'raw_text' => 'Promo', 'published_at' => gmdate( DATE_ATOM, $published ), 'edited_at' => '', 'views' => $views,
			'expires_at' => time() + DAY_IN_SECONDS, 'discount_label' => '10%', 'discount_value' => 10,
		)
	);
	$post_id       = (int) $result['post_id'];
	$created_ids[] = $post_id;
	update_post_meta( $post_id, '_telegram_pinned', $pinned ? 'yes' : 'no' );
	return $post_id;
};

try {
	$old_id    = $make( 91001, 1000, time() - 20 * HOUR_IN_SECONDS );
	$fresh_id  = $make( 91002, 500, time() - HOUR_IN_SECONDS );
	$pinned_id = $make( 91003, 10, time() - 10 * HOUR_IN_SECONDS, true );

	Promokodiki_Telegram_Test_Harness::run(
		'pinned leads and view velocity beats raw views',
		static function () use ( $pinned_id, $fresh_id, $old_id ): void {
			$ids = Promokodiki_Telegram_Query::top_ids( 4 );
			Promokodiki_Telegram_Test_Harness::assert_same( $pinned_id, $ids[0] );
			Promokodiki_Telegram_Test_Harness::assert_true( array_search( $fresh_id, $ids, true ) < array_search( $old_id, $ids, true ) );
		}
	);

	Promokodiki_Telegram_Test_Harness::run(
		'query limit is constrained to four through twenty',
		static function (): void {
			Promokodiki_Telegram_Test_Harness::assert_true( count( Promokodiki_Telegram_Query::top_ids( 1 ) ) <= 4 );
			Promokodiki_Telegram_Test_Harness::assert_true( count( Promokodiki_Telegram_Query::top_ids( 99 ) ) <= 20 );
		}
	);

	Promokodiki_Telegram_Test_Harness::run(
		'exact timestamp expiry unpublishes unlocked posts',
		static function () use ( $old_id ): void {
			update_post_meta( $old_id, '_telegram_expires_at', time() - 1 );
			Promokodiki_Telegram_Query::expire_posts();
			Promokodiki_Telegram_Test_Harness::assert_same( 'draft', get_post_status( $old_id ) );
			Promokodiki_Telegram_Test_Harness::assert_same( 'no', get_post_meta( $old_id, '_promocode_is_active', true ) );
		}
	);

	Promokodiki_Telegram_Test_Harness::run(
		'ordinary collections exclude Telegram while search remains visible',
		static function (): void {
			$ordinary = new WP_Query();
			$ordinary->set( 'post_type', 'promocode' );
			Promokodiki_Telegram_Query::exclude_from_general_query( $ordinary );
			Promokodiki_Telegram_Test_Harness::assert_true( ! empty( $ordinary->get( 'meta_query' ) ) );

			$search = new WP_Query();
			$search->is_search = true;
			Promokodiki_Telegram_Query::exclude_from_general_query( $search );
			Promokodiki_Telegram_Test_Harness::assert_same( '', $search->get( 'meta_query' ) );

			$single = new WP_Query();
			$single->is_singular = true;
			$single->set( 'post_type', 'promocode' );
			Promokodiki_Telegram_Query::exclude_from_general_query( $single );
			Promokodiki_Telegram_Test_Harness::assert_same( '', $single->get( 'meta_query' ) );
		}
	);

	Promokodiki_Telegram_Test_Harness::run(
		'home filter exposes and returns the Telegram category',
		static function () use ( $fresh_id, $pinned_id ): void {
			Promokodiki_Filter_Context::flush_cache();
			$context = Promokodiki_Filter_Context::resolve( 'home' );
			$options = Promokodiki_Filter_Option_Service::build(
				$context,
				array( 'category_id' => 0, 'brand_id' => 0 )
			);
			$term_id = Promokodiki_Telegram_Config::category_term_id();
			$option_ids = array_map( 'intval', wp_list_pluck( $options['category_options'], 'id' ) );

			Promokodiki_Telegram_Test_Harness::assert_true( in_array( $term_id, $option_ids, true ) );

			$result = Promokodiki_Filter_Query_Service::run(
				array(
					'category_id' => $term_id,
					'brand_id'    => 0,
					'page'        => 1,
					'sort'        => 'newest',
					'popular'     => false,
				),
				$context,
				array(
					'initial_count'  => 20,
					'load_more_count' => 20,
					'show_expired'   => false,
					'popular_days'   => 7,
				)
			);
			$post_ids = array_map( static fn( WP_Post $post ): int => (int) $post->ID, $result['posts'] );
			Promokodiki_Telegram_Test_Harness::assert_true( in_array( $fresh_id, $post_ids, true ) );
			Promokodiki_Telegram_Test_Harness::assert_true( in_array( $pinned_id, $post_ids, true ) );
		}
	);

	Promokodiki_Telegram_Test_Harness::finish();
} finally {
	foreach ( $created_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}
