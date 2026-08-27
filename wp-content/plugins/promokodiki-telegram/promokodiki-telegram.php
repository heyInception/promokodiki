<?php
/**
 * Plugin Name: Promokodiki Telegram
 * Description: Imports high-confidence Telegram promocodes through an authenticated MTProto worker.
 * Version: 1.1.0
 * Author: Promokodiki
 * Text Domain: promokodiki-telegram
 *
 * @package Promokodiki_Telegram
 */

defined( 'ABSPATH' ) || exit;

define( 'PROMOKODIKI_TELEGRAM_VERSION', '1.1.0' );
define( 'PROMOKODIKI_TELEGRAM_FILE', __FILE__ );
define( 'PROMOKODIKI_TELEGRAM_DIR', plugin_dir_path( __FILE__ ) );
define( 'PROMOKODIKI_TELEGRAM_URL', plugin_dir_url( __FILE__ ) );

require_once PROMOKODIKI_TELEGRAM_DIR . 'includes/class-config.php';
require_once PROMOKODIKI_TELEGRAM_DIR . 'includes/class-activator.php';
require_once PROMOKODIKI_TELEGRAM_DIR . 'includes/class-request-auth.php';
require_once PROMOKODIKI_TELEGRAM_DIR . 'includes/class-link-service.php';
require_once PROMOKODIKI_TELEGRAM_DIR . 'includes/class-media-service.php';
require_once PROMOKODIKI_TELEGRAM_DIR . 'includes/class-promocode-repository.php';
require_once PROMOKODIKI_TELEGRAM_DIR . 'includes/class-log.php';
require_once PROMOKODIKI_TELEGRAM_DIR . 'includes/class-rest-controller.php';
require_once PROMOKODIKI_TELEGRAM_DIR . 'includes/class-ranking.php';
require_once PROMOKODIKI_TELEGRAM_DIR . 'includes/class-query.php';
require_once PROMOKODIKI_TELEGRAM_DIR . 'admin/class-admin.php';
require_once PROMOKODIKI_TELEGRAM_DIR . 'admin/class-metabox.php';
require_once PROMOKODIKI_TELEGRAM_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Promokodiki_Telegram_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Promokodiki_Telegram_Activator', 'deactivate' ) );

Promokodiki_Telegram_Plugin::boot();
