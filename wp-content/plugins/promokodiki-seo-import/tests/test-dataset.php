<?php
/** Pure dataset contract for the SEO import. */

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ ); }

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
}
if ( ! function_exists( 'mb_strtolower' ) ) {
	function mb_strtolower( $value ) { return strtr( strtolower( $value ), array( 'Ф' => 'ф', 'С' => 'с' ) ); }
}

require_once dirname( __DIR__ ) . '/includes/class-seo-dataset.php';

$failures = array();
$assert = static function ( bool $condition, string $message ) use ( &$failures ): void {
	if ( ! $condition ) { $failures[] = $message; }
};

$assert( 'финансы и страхование' === Promokodiki_SEO_Dataset::normalize_name( "  Финансы   и Страхование\n" ), 'Names must normalize case and whitespace.' );
$assert( 'Промокоды %%currentmonth%% %%currentyear%% | Promokodiki' === Promokodiki_SEO_Dataset::expand_placeholders( 'Промокоды [текущий месяц/год] | [Название сайта]' ), 'Approved placeholders must become Yoast variables.' );
$assert( Promokodiki_SEO_Dataset::is_stale_seasonal( 'Новогодние промокоды 2025-2026 года', 2026 ), 'Past seasonal ranges must be rejected.' );
$assert( ! Promokodiki_SEO_Dataset::is_stale_seasonal( 'Промокоды %%currentyear%%', 2026 ), 'Dynamic years must not be rejected.' );

if ( function_exists( 'get_option' ) && class_exists( 'Promokodiki_SEO_Command' ) ) {
	$original = get_option( 'wpseo_taxonomy_meta', array() );
	$created  = wp_insert_term( 'SEO storage fixture ' . wp_generate_uuid4(), 'promocode_category' );
	$term_id  = is_wp_error( $created ) ? 0 : (int) $created['term_id'];
	if ( $term_id && class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
		WPSEO_Taxonomy_Meta::set_value( $term_id, 'promocode_category', 'focuskw', 'preserve-me' );
	} elseif ( $term_id ) {
		$fixture = $original;
		$fixture['promocode_category'][ $term_id ] = array( 'wpseo_focuskw' => 'preserve-me' );
		update_option( 'wpseo_taxonomy_meta', $fixture, false );
	}
	try {
		$command = new Promokodiki_SEO_Command();
		$write   = new ReflectionMethod( $command, 'write_term_seo' );
		$read    = new ReflectionMethod( $command, 'read_term_seo' );
		$write->invoke( $command, $term_id, 'CSV title', 'CSV description' );
		$stored = $read->invoke( $command, $term_id );
		$all    = get_option( 'wpseo_taxonomy_meta', array() );
		$assert( 'CSV title' === $stored['title'], 'Term title must use Yoast taxonomy storage.' );
		$assert( 'CSV description' === $stored['description'], 'Term description must use Yoast taxonomy storage.' );
		$assert( 'preserve-me' === ( $all['promocode_category'][ $term_id ]['wpseo_focuskw'] ?? '' ), 'Other Yoast term fields must be preserved.' );
	} finally {
		update_option( 'wpseo_taxonomy_meta', $original, false );
		if ( $term_id ) { wp_delete_term( $term_id, 'promocode_category' ); }
	}
}

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}
echo "PASS SEO dataset contract\n";
