<?php
/**
 * Safe renderer for Admitad administration AJAX fragments.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders only explicitly registered partial views.
 */
final class Promokodiki_Admitad_Admin_Fragments {
	/**
	 * Immutable fragment registry. Foundation work intentionally has no AJAX
	 * partials yet; later entries must declare both a basename and context keys.
	 *
	 * @var array<string,array{file:string,context:string[]}>
	 */
	private const FRAGMENTS = array();

	/**
	 * Render an allowlisted admin partial with explicit context variables.
	 *
	 * @param string               $fragment Fragment name.
	 * @param array<string, mixed> $context  Render context.
	 * @return string Rendered HTML.
	 * @throws InvalidArgumentException When the fragment is not allowlisted.
	 * @throws RuntimeException When an allowlisted fragment is unavailable.
	 */
	public static function render( string $fragment, array $context ): string {
		$fragment = sanitize_key( $fragment );
		if ( ! isset( self::FRAGMENTS[ $fragment ] ) ) {
			throw new InvalidArgumentException( 'Unknown Admitad admin fragment.' );
		}

		$definition = self::FRAGMENTS[ $fragment ];
		$file       = $definition['file'];
		if ( ! preg_match( '/^[a-z0-9-]+\\.php$/', $file ) ) {
			throw new RuntimeException( 'Invalid Admitad admin fragment definition.' );
		}

		$directory = ADMITAD_PLUGIN_DIR . 'admin/views/partials/';
		$path      = $directory . $file;
		if ( ! is_file( $path ) ) {
			throw new RuntimeException( 'Admitad admin fragment is unavailable.' );
		}

		$variables = array();
		foreach ( $definition['context'] as $key ) {
			if ( array_key_exists( $key, $context ) ) {
				$variables[ $key ] = $context[ $key ];
			}
		}

		ob_start();
		extract( $variables, EXTR_SKIP );
		require $path;
		return (string) ob_get_clean();
	}
}
