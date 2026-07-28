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
	 * Immutable fragment registry. Every fragment declares its owning section,
	 * preventing a caller from selecting a lower-privilege page for a fragment.
	 *
	 * @var array<string,array{file:string,page:string,context:string[]}>
	 */
	private const FRAGMENTS = array(
		'foundation' => array(
			'file'    => 'foundation.php',
			'page'    => 'admitad-settings',
			'context' => array( 'message' ),
		),
	);

	/**
	 * Return the owning admin section for an allowlisted fragment.
	 *
	 * @param string $fragment Fragment name.
	 * @return string Admin page slug.
	 * @throws InvalidArgumentException When the fragment is not allowlisted.
	 */
	public static function page( string $fragment ): string {
		return self::definition( $fragment )['page'];
	}

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
		$definition = self::definition( $fragment );
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

		$buffer_level = ob_get_level();
		ob_start();
		try {
			extract( $variables, EXTR_SKIP );
			require $path;
			$html = ob_get_contents();
			return false === $html ? '' : $html;
		} finally {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}
		}
	}

	/**
	 * Read one immutable fragment definition without accepting filesystem input.
	 *
	 * @param string $fragment Fragment name.
	 * @return array{file:string,page:string,context:string[]} Fragment definition.
	 * @throws InvalidArgumentException When the fragment is not allowlisted.
	 */
	private static function definition( string $fragment ): array {
		$fragment = sanitize_key( $fragment );
		if ( ! isset( self::FRAGMENTS[ $fragment ] ) ) {
			throw new InvalidArgumentException( 'Unknown Admitad admin fragment.' );
		}

		return self::FRAGMENTS[ $fragment ];
	}
}
