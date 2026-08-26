<?php
/**
 * Plugin activation orchestration.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activates durable plugin structures.
 */
final class Promokodiki_Admitad_Activator {
	/**
	 * Activate the integration.
	 */
	public static function activate(): void {
		admitad_register_content_types();
		Promokodiki_Admitad_Schema::install();
		Promokodiki_Admitad_Capabilities::install();
		( new Promokodiki_Admitad_Taxonomy_Rule_Seeder() )->seed_all_terms();
		Promokodiki_Admitad_Plugin::schedule();
		flush_rewrite_rules();
	}
}
