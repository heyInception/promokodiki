<?php
/**
 * Canonical administrative request-state integration tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

Promokodiki_Admitad_Test_Harness::run(
	'admin request canonicalizes allowlisted state',
	static function (): void {
		$request = Promokodiki_Admitad_Admin_Request::from_array(
			array(
				'paged'      => '2',
				'per_page'   => '50',
				's'          => '<b>needle</b>',
				'reason'     => '<b>low_confidence</b>',
				'unexpected' => 'discard-me',
			),
			'admitad-history'
		);

		Promokodiki_Admitad_Test_Harness::assert_same( 'admitad-history', $request->page() );
		Promokodiki_Admitad_Test_Harness::assert_same( 2, $request->paged() );
		Promokodiki_Admitad_Test_Harness::assert_same( 50, $request->per_page() );
		Promokodiki_Admitad_Test_Harness::assert_same( 'needle', $request->search() );
		Promokodiki_Admitad_Test_Harness::assert_same( 'low_confidence', $request->filter( 'reason' ) );
		Promokodiki_Admitad_Test_Harness::assert_same( '', $request->filter( 'unexpected' ) );
		Promokodiki_Admitad_Test_Harness::assert_same(
			array(
				'post_type' => 'promocode',
				'page'      => 'admitad-history',
				'paged'     => 2,
				'per_page'  => 50,
				's'         => 'needle',
				'reason'    => 'low_confidence',
			),
			$request->query_args()
		);
		Promokodiki_Admitad_Test_Harness::assert_true(
			str_contains(
				$request->url(),
				'edit.php?post_type=promocode&page=admitad-history&paged=2&per_page=50'
			)
		);

		$invalid = Promokodiki_Admitad_Admin_Request::from_array(
			array(
				'paged'      => '0',
				'per_page'   => '500',
				's'          => array( 'not-a-scalar' ),
				'unknown'    => 'discard-me',
			),
			'not-an-admitad-page'
		);

		Promokodiki_Admitad_Test_Harness::assert_same( 'admitad-overview', $invalid->page() );
		Promokodiki_Admitad_Test_Harness::assert_same( 1, $invalid->paged() );
		Promokodiki_Admitad_Test_Harness::assert_same( 20, $invalid->per_page() );
		Promokodiki_Admitad_Test_Harness::assert_same( '', $invalid->search() );
		Promokodiki_Admitad_Test_Harness::assert_same( '', $invalid->filter( 'unknown' ) );
		Promokodiki_Admitad_Test_Harness::assert_true( ! isset( $invalid->query_args()['unknown'] ) );

		$negative_history = Promokodiki_Admitad_Admin_Request::from_array(
			array(
				'paged'  => '-7',
				'reason' => 'low_confidence',
				'status' => 'discard-on-history',
			),
			'admitad-history'
		);
		Promokodiki_Admitad_Test_Harness::assert_same( 1, $negative_history->paged() );
		Promokodiki_Admitad_Test_Harness::assert_same( 'low_confidence', $negative_history->filter( 'reason' ) );
		Promokodiki_Admitad_Test_Harness::assert_same( '', $negative_history->filter( 'status' ) );
		Promokodiki_Admitad_Test_Harness::assert_true( ! isset( $negative_history->query_args()['status'] ) );
	}
);

Promokodiki_Admitad_Test_Harness::finish();
