<?php
/**
 * Admitad overview page.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the high-level automation health.
 */
final class Promokodiki_Admitad_Overview_Page {
	/**
	 * Render overview.
	 */
	public function render(): void {
		$snapshot = Promokodiki_Admitad_Diagnostics::snapshot();
		require ADMITAD_PLUGIN_DIR . 'admin/views/overview.php';
	}
}
