<?php
/**
 * Admitad diagnostics page.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a sanitized diagnostics export.
 */
final class Promokodiki_Admitad_Diagnostics_Page {
	/**
	 * Render diagnostics.
	 */
	public function render(): void {
		$snapshot = Promokodiki_Admitad_Diagnostics::snapshot();
		require ADMITAD_PLUGIN_DIR . 'admin/views/diagnostics.php';
	}
}
