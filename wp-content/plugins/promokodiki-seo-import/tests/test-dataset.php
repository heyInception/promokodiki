<?php
/** Pure dataset contract for the SEO import. */

define( 'ABSPATH', __DIR__ );

function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
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

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}
echo "PASS SEO dataset contract\n";
