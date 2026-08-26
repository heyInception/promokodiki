<?php
/**
 * Legacy database compatibility functions.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Install current plugin tables for legacy callers.
 */
function admitad_create_tables() {
	Promokodiki_Admitad_Schema::install();
}

/**
 * Legacy no-op retained to guarantee deactivation never deletes data.
 */
function admitad_drop_tables() {
	// Intentionally empty.
}
