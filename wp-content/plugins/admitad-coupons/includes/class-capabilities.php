<?php
/**
 * Admitad automation capabilities.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installs plugin capabilities on WordPress roles.
 */
final class Promokodiki_Admitad_Capabilities {
	/**
	 * Install administrator and editor capabilities.
	 */
	public static function install(): void {
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( 'manage_admitad_automation' );
			$administrator->add_cap( 'review_admitad_mapping' );
		}

		$editor = get_role( 'editor' );
		if ( $editor ) {
			$editor->add_cap( 'review_admitad_mapping' );
			$editor->remove_cap( 'manage_admitad_automation' );
		}
	}
}
