<?php
/**
 * Administrative asset integration tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

Promokodiki_Admitad_Test_Harness::run(
	'admin assets enqueue only on Admitad promocode pages',
	static function (): void {
		$original_get = $_GET;

		try {
			Promokodiki_Admitad_Test_Harness::assert_true(
				false !== has_action(
					'admin_enqueue_scripts',
					array( 'Promokodiki_Admitad_Admin_Assets', 'enqueue' )
				)
			);

			wp_dequeue_script( 'promokodiki-admitad-admin' );
			wp_dequeue_style( 'promokodiki-admitad-admin' );
			$_GET = array(
				'post_type' => 'post',
				'page'      => 'admitad-history',
			);
			do_action( 'admin_enqueue_scripts', 'edit.php' );
			Promokodiki_Admitad_Test_Harness::assert_true( ! wp_script_is( 'promokodiki-admitad-admin', 'enqueued' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( ! wp_style_is( 'promokodiki-admitad-admin', 'enqueued' ) );

			$_GET = array(
				'post_type' => 'promocode',
				'page'      => 'plugins',
			);
			do_action( 'admin_enqueue_scripts', 'plugins.php' );
			Promokodiki_Admitad_Test_Harness::assert_true( ! wp_script_is( 'promokodiki-admitad-admin', 'enqueued' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( ! wp_style_is( 'promokodiki-admitad-admin', 'enqueued' ) );

			$_GET = array(
				'post_type' => 'promocode',
				'page'      => 'admitad-history',
			);
			do_action( 'admin_enqueue_scripts', 'edit.php' );
			Promokodiki_Admitad_Test_Harness::assert_true( wp_script_is( 'promokodiki-admitad-admin', 'enqueued' ) );
			Promokodiki_Admitad_Test_Harness::assert_true( wp_style_is( 'promokodiki-admitad-admin', 'enqueued' ) );

			$localized = (string) wp_scripts()->get_data( 'promokodiki-admitad-admin', 'data' );
			$prefix    = 'var PromokodikiAdmitadAdmin = ';
			$config    = json_decode( substr( $localized, strlen( $prefix ), -1 ), true );
			Promokodiki_Admitad_Test_Harness::assert_same(
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'promokodiki_admitad_admin_ajax' ),
					'i18n'    => array(
						'loading' => 'Загрузка…',
						'retry'   => 'Повторить',
					),
				),
				$config
			);
		} finally {
			$_GET = $original_get;
			wp_dequeue_script( 'promokodiki-admitad-admin' );
			wp_dequeue_style( 'promokodiki-admitad-admin' );
		}
	}
);

Promokodiki_Admitad_Test_Harness::finish();
