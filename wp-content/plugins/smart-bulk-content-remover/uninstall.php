<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) { die(); }

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) )
{
	exit;
}