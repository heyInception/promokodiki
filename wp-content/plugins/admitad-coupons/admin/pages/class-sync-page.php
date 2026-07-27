<?php
/**
 * Admitad synchronization page.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders recent runs and safe manual controls.
 */
final class Promokodiki_Admitad_Sync_Page {
	/**
	 * Render synchronization operations.
	 */
	public function render(): void {
		$snapshot = Promokodiki_Admitad_Diagnostics::snapshot();
		require ADMITAD_PLUGIN_DIR . 'admin/views/sync.php';
	}
}
