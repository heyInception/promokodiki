<?php
/** AJAX payload integration tests. */

require_once dirname( __DIR__ ) . '/harness.php';

$discount_ids = array();

try {
	for ( $index = 0; $index < 8; $index++ ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'promocode',
				'post_status' => 'publish',
				'post_title'  => 'PAF AJAX discount ' . $index,
				'post_date'   => wp_date( 'Y-m-d H:i:s', time() - ( $index * MINUTE_IN_SECONDS ) ),
			)
		);
		$discount_ids[] = $post_id;
		update_post_meta( $post_id, '_promocode_expiry_date', wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ) );
	}

	Promokodiki_Filter_Test_Harness::run(
		'discounts payload sanitizes sort and paginates six cards at a time',
		static function () use ( $discount_ids ): void {
			$restrict_query = static function ( WP_Query $query ) use ( $discount_ids ): void {
				if ( 'promocode' === $query->get( 'post_type' ) ) {
					$query->set( 'post__in', $discount_ids );
				}
			};
			add_action( 'pre_get_posts', $restrict_query );
			try {
				$page_one = Promokodiki_Filter_Ajax_Controller::build_results_payload(
					array(
						'context'       => 'discounts',
						'object_id'     => 0,
						'context_nonce' => wp_create_nonce( 'promokodiki_filter_context_discounts_0' ),
						'paf_sort'      => 'NeWeSt',
					)
				);
				$page_two = Promokodiki_Filter_Ajax_Controller::build_results_payload(
					array(
						'context'       => 'discounts',
						'object_id'     => 0,
						'context_nonce' => wp_create_nonce( 'promokodiki_filter_context_discounts_0' ),
						'paf_sort'      => 'newest',
						'paf_page'      => 2,
					)
				);
			} finally {
				remove_action( 'pre_get_posts', $restrict_query );
			}

			Promokodiki_Filter_Test_Harness::assert_true( is_array( $page_one ) );
			Promokodiki_Filter_Test_Harness::assert_true( is_array( $page_two ) );
			Promokodiki_Filter_Test_Harness::assert_same( 'newest', $page_one['state']['sort'] );
			Promokodiki_Filter_Test_Harness::assert_same( 6, substr_count( $page_one['html'], 'class="promocodes__item' ) );
			Promokodiki_Filter_Test_Harness::assert_same( true, $page_one['has_more'] );
			Promokodiki_Filter_Test_Harness::assert_same( 1, $page_one['page'] );
			Promokodiki_Filter_Test_Harness::assert_same( array_slice( $discount_ids, 0, 6 ), paf_ajax_result_ids( $page_one['html'] ) );
			Promokodiki_Filter_Test_Harness::assert_same( 2, substr_count( $page_two['html'], 'class="promocodes__item' ) );
			Promokodiki_Filter_Test_Harness::assert_same( false, $page_two['has_more'] );
			Promokodiki_Filter_Test_Harness::assert_same( 2, $page_two['page'] );
			Promokodiki_Filter_Test_Harness::assert_same( array_slice( $discount_ids, 6, 2 ), paf_ajax_result_ids( $page_two['html'] ) );
		}
	);
} finally {
	foreach ( $discount_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}

Promokodiki_Filter_Test_Harness::run(
	'results payload returns stable public shape',
	static function (): void {
		$payload = Promokodiki_Filter_Ajax_Controller::build_results_payload(
			array(
				'context'       => 'home',
				'object_id'     => 0,
				'context_nonce' => wp_create_nonce( 'promokodiki_filter_context_home_0' ),
				'paf_sort'      => 'newest',
			)
		);
		Promokodiki_Filter_Test_Harness::assert_true( is_array( $payload ) );
		Promokodiki_Filter_Test_Harness::assert_same(
			array( 'html', 'page', 'has_more', 'total', 'message', 'state', 'category_options', 'brand_options' ),
			array_keys( $payload )
		);
		Promokodiki_Filter_Test_Harness::assert_same( 1, $payload['page'] );
		Promokodiki_Filter_Test_Harness::assert_true( isset( $payload['state'] ) );
		Promokodiki_Filter_Test_Harness::assert_true( isset( $payload['category_options'] ) );
		Promokodiki_Filter_Test_Harness::assert_true( isset( $payload['brand_options'] ) );
		Promokodiki_Filter_Test_Harness::assert_same( '', $payload['state']['category'] );
		Promokodiki_Filter_Test_Harness::assert_same( '', $payload['state']['brand'] );
	}
);

Promokodiki_Filter_Test_Harness::run(
	'results payload rejects forged context and selections',
	static function (): void {
		$forged = Promokodiki_Filter_Ajax_Controller::build_results_payload(
			array(
				'context'       => 'home',
				'object_id'     => 0,
				'context_nonce' => 'invalid',
			)
		);
		Promokodiki_Filter_Test_Harness::assert_true( is_wp_error( $forged ) );

		$selection = Promokodiki_Filter_Ajax_Controller::build_results_payload(
			array(
				'context'       => 'home',
				'object_id'     => 0,
				'context_nonce' => wp_create_nonce( 'promokodiki_filter_context_home_0' ),
				'paf_brand'     => 999999999,
			)
		);
		Promokodiki_Filter_Test_Harness::assert_true( is_wp_error( $selection ) );
	}
);

Promokodiki_Filter_Test_Harness::run(
	'public result endpoint hooks are registered',
	static function (): void {
		Promokodiki_Filter_Test_Harness::assert_true(
			false !== has_action( 'wp_ajax_promokodiki_filter_results', array( 'Promokodiki_Filter_Ajax_Controller', 'results' ) )
		);
		Promokodiki_Filter_Test_Harness::assert_true(
			false !== has_action( 'wp_ajax_nopriv_promokodiki_filter_results', array( 'Promokodiki_Filter_Ajax_Controller', 'results' ) )
		);
	}
);

Promokodiki_Filter_Test_Harness::finish();

function paf_ajax_result_ids( string $html ): array {
	preg_match_all( '/<div class="promocodes__item [^"]*" data-post-id="(\d+)">/', $html, $matches );
	return array_map( 'intval', $matches[1] );
}
