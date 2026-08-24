<?php
/**
 * UI kit stylesheet contract.
 *
 * Run: php tests/ui-kit.php
 */

$theme_directory = dirname( __DIR__ );
$stylesheet_path = $theme_directory . '/assets/css/ui-kit.css';
$functions_path  = $theme_directory . '/functions.php';

function ui_kit_assert_true( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function ui_kit_assert_contains( $needle, $haystack, $message ) {
	ui_kit_assert_true( false !== strpos( $haystack, $needle ), $message );
}

ui_kit_assert_true( file_exists( $stylesheet_path ), 'The standalone UI kit stylesheet exists' );

$stylesheet = file_get_contents( $stylesheet_path );
$functions  = file_get_contents( $functions_path );

ui_kit_assert_contains( '.ui-button', $stylesheet, 'The reusable button base class is available' );
ui_kit_assert_contains( '.ui-link', $stylesheet, 'The reusable link base class is available' );

foreach ( array( 'orange', 'pink', 'blue' ) as $variant ) {
	ui_kit_assert_contains( ".ui-button--{$variant}", $stylesheet, "The {$variant} button variant is available" );
	ui_kit_assert_contains( ".ui-link--{$variant}", $stylesheet, "The {$variant} link variant is available" );
}

ui_kit_assert_contains( '.ui-button:hover', $stylesheet, 'Buttons expose the Figma hover state' );
ui_kit_assert_contains( '.ui-button:active', $stylesheet, 'Buttons expose the Figma active state' );
ui_kit_assert_contains( '.ui-button:focus-visible', $stylesheet, 'Buttons expose a keyboard focus state' );
ui_kit_assert_contains( '.ui-link:hover', $stylesheet, 'Links expose the Figma hover state' );
ui_kit_assert_contains( '.ui-link:active', $stylesheet, 'Links expose the Figma active state' );
ui_kit_assert_contains( '.ui-link:focus-visible', $stylesheet, 'Links expose a keyboard focus state' );

foreach ( array( '#ffede3', '#fa6108', '#ffe9f2', '#fe1477', '#cae8ff', '#1999fa', '#93d0ff' ) as $color ) {
	ui_kit_assert_contains( $color, $stylesheet, "The Figma color {$color} is preserved" );
}

ui_kit_assert_contains( "'promokodiki-ui-kit'", $functions, 'The UI kit stylesheet is enqueued by WordPress' );
ui_kit_assert_contains( "'/assets/css/ui-kit.css'", $functions, 'The enqueue points at the standalone UI kit file' );
ui_kit_assert_contains( "array('promokodiki-overrides')", $functions, 'The UI kit loads after existing theme overrides' );

echo "UI kit stylesheet contract passed.\n";
