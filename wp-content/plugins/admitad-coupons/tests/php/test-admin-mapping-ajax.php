<?php
/**
 * Category mapping and company profile AJAX integration tests.
 *
 * @package Promokodiki_Admitad
 */

require_once dirname( __DIR__ ) . '/harness.php';
require_once dirname( __DIR__, 2 ) . '/admitad-coupons.php';

$old_user = get_current_user_id();
$user_ids = array();
$term_ids = array();

try {
	admitad_register_content_types();
	Promokodiki_Admitad_Schema::install();
	Promokodiki_Admitad_Capabilities::install();
	$administrator_id = wp_insert_user(
		array(
			'user_login' => 'mapping-ajax-admin-' . wp_generate_password( 8, false ),
			'user_pass'  => wp_generate_password( 20 ),
			'user_email' => wp_generate_password( 8, false ) . '@example.test',
			'role'       => 'administrator',
		)
	);
	$editor_id = wp_insert_user(
		array(
			'user_login' => 'mapping-ajax-editor-' . wp_generate_password( 8, false ),
			'user_pass'  => wp_generate_password( 20 ),
			'user_email' => wp_generate_password( 8, false ) . '@example.test',
			'role'       => 'editor',
		)
	);
	$user_ids = array( $administrator_id, $editor_id );

	$parent = wp_insert_term( 'Parent fixture', 'promocode_category' );
	$child  = wp_insert_term( 'Child fixture', 'promocode_category', array( 'parent' => (int) $parent['term_id'] ) );
	$other  = wp_insert_term( 'Other fixture', 'promocode_category' );
	$term_ids = array( (int) $parent['term_id'], (int) $child['term_id'], (int) $other['term_id'] );

	Promokodiki_Admitad_Test_Harness::run(
		'mapping and company forms provide labelled Russian guidance and full term paths',
		static function () use ( $administrator_id ): void {
			wp_set_current_user( $administrator_id );
			foreach ( array( 'Promokodiki_Admitad_Category_Map_Page', 'Promokodiki_Admitad_Company_Page' ) as $class_name ) {
				ob_start();
				( new $class_name() )->render();
				$html = (string) ob_get_clean();
				preg_match_all( '/<(?:input|select|textarea)\\b[^>]*\\bname="([^"]+)"/i', $html, $matches, PREG_SET_ORDER );
				foreach ( $matches as $match ) {
					$name = $match[1];
					if ( false !== stripos( $match[0], 'type="hidden"' ) || in_array( $name, array( 'action', 'operation', '_wpnonce', 'post_type', 'page' ), true ) ) {
						continue;
					}
					preg_match( '/\\bid="([^"]+)"/i', $match[0], $id_match );
					$control_id = $id_match[1] ?? '';
					Promokodiki_Admitad_Test_Harness::assert_true(
						'' !== $control_id && false !== stripos( $html, '<label' ) && false !== stripos( $html, 'for="' . $control_id . '"' ),
						'Control ' . $name . ' must have an explicit label.'
					);
				}
				Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $html, 'Parent fixture → Child fixture' ), 'Full Parent → Child term option is missing.' );
			}

			ob_start();
			( new Promokodiki_Admitad_Category_Map_Page() )->render();
			$mapping_html = (string) ob_get_clean();
			Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $mapping_html, 'Пространство имён' ), 'Namespace explanation is missing.' );
			Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $mapping_html, 'Вес' ), 'Mapping weight explanation is missing.' );

			ob_start();
			( new Promokodiki_Admitad_Company_Page() )->render();
			$company_html = (string) ob_get_clean();
			Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $company_html, 'Допустимые' ), 'Allowed categories explanation is missing.' );
			Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $company_html, 'по умолчанию' ), 'Default category explanation is missing.' );
			Promokodiki_Admitad_Test_Harness::assert_true( str_contains( $company_html, 'Вес' ), 'Company weight explanation is missing.' );
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'company AJAX search is administrator-only, bounded, and exposes stable choices',
		static function () use ( $administrator_id, $editor_id ): void {
			wp_set_current_user( $editor_id );
			$forbidden = Promokodiki_Admitad_Admin_Ajax::handle(
				array(
					'operation'   => 'company_search',
					'page'        => 'admitad-companies',
					's'           => 'fixture',
					'_ajax_nonce' => wp_create_nonce( 'promokodiki_admitad_admin_ajax' ),
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 'forbidden', $forbidden->get_error_code() );

			wp_set_current_user( $administrator_id );
			$choices = Promokodiki_Admitad_Admin_Ajax::handle(
				array(
					'operation'   => 'company_search',
					'page'        => 'admitad-companies',
					's'           => '',
					'limit'       => '500',
					'_ajax_nonce' => wp_create_nonce( 'promokodiki_admitad_admin_ajax' ),
				)
			);
			Promokodiki_Admitad_Test_Harness::assert_true( ! is_wp_error( $choices ) );
			Promokodiki_Admitad_Test_Harness::assert_true( count( $choices['items'] ) <= 20 );
			foreach ( $choices['items'] as $choice ) {
				Promokodiki_Admitad_Test_Harness::assert_same( array( 'id', 'text' ), array_keys( $choice ) );
				Promokodiki_Admitad_Test_Harness::assert_true( is_int( $choice['id'] ) );
				Promokodiki_Admitad_Test_Harness::assert_true( is_string( $choice['text'] ) );
			}
		}
	);

	Promokodiki_Admitad_Test_Harness::run(
		'company profile save rejects a default category outside its allowed set',
		static function () use ( $administrator_id, $term_ids ): void {
			wp_set_current_user( $administrator_id );
			$result = ( new Promokodiki_Admitad_Admin_Actions() )->save_company_profile(
				999991,
				$term_ids[2],
				array( $term_ids[1] ),
				40,
				'Invalid profile fixture'
			);
			Promokodiki_Admitad_Test_Harness::assert_same( 'invalid_company_profile', $result->get_error_code() );
		}
	);
} finally {
	global $wpdb;
	wp_set_current_user( $old_user );
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
	foreach ( $term_ids as $term_id ) {
		wp_delete_term( $term_id, 'promocode_category' );
	}
	$wpdb->delete( Promokodiki_Admitad_Schema::table( 'company_category' ), array( 'campaign_id' => 999991 ), array( '%d' ) );
	$wpdb->delete( Promokodiki_Admitad_Schema::table( 'company_profile' ), array( 'campaign_id' => 999991 ), array( '%d' ) );
}

Promokodiki_Admitad_Test_Harness::finish();
