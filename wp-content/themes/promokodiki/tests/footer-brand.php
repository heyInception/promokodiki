<?php
/** Contract test for footer brand and automatic year. */

function get_template_directory_uri() { return '/theme'; }
function home_url( $path = '/' ) { return 'https://promokodiki.com' . $path; }
function esc_url( $value ) { return $value; }
function esc_html( $value ) { return $value; }
function wp_footer() {}
function wp_date( $format ) { return gmdate( $format ); }

ob_start();
require dirname( __DIR__ ) . '/footer.php';
$html = ob_get_clean();

if ( str_contains( $html, 'TEST.ru' ) || str_contains( $html, 'test.ru' ) ) {
	fwrite( STDERR, "Footer still contains the placeholder brand.\n" );
	exit( 1 );
}
if ( ! str_contains( $html, '© 2017-' . gmdate( 'Y' ) . ' promokodiki.com' ) ) {
	fwrite( STDERR, "Footer does not contain the automatic promokodiki.com copyright.\n" );
	exit( 1 );
}

echo "Footer brand contract passed.\n";
