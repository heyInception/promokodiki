<?php
/** AJAX payload integration tests. */

require_once dirname( __DIR__ ) . '/harness.php';

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
			array( 'html', 'page', 'has_more', 'total', 'message' ),
			array_keys( $payload )
		);
		Promokodiki_Filter_Test_Harness::assert_same( 1, $payload['page'] );
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
