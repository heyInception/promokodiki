<?php
/**
 * Plugin Name: Promokodiki Admitad Sync
 * Description: Imports Admitad coupons into a single promocode post type and links them to shops.
 * Version: 3.0.0
 * Author: Promokodiki
 * Text Domain: promokodiki-admitad
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ADMITAD_PLUGIN_VERSION', '3.0.0' );
define( 'ADMITAD_PLUGIN_FILE', __FILE__ );
define( 'ADMITAD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ADMITAD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ADMITAD_PLUGIN_DIR . 'includes/post-types.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-config.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-capabilities.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-schema.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-activator.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-plugin.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/db.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/api.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-api-client.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-coupon-normalizer.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-campaign-normalizer.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-text-normalizer.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-rule-repository.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-category-map-repository.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-company-profile-repository.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-classification-result.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-classifier.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-classification-history-repository.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-review-queue-repository.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-rule-evidence-service.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-taxonomy-rule-seeder.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-backup-gate.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-legacy-migration.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-tag-manager.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-assignment-service.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-reclassification-service.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-validation-service.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-job-lock.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-sync-run-repository.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-import-context.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-editorial-locks.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-duplicate-detector.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-coupon-repository.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-import-pipeline.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-reference-repository.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-shop-content-service.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-shop-profile-sync.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-managed-logo-service.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-reconciler.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-visibility.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-notifier.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-diagnostics.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-retention.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-sync-coordinator.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/class-recovery-coordinator.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/importer.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/migration.php';
require_once ADMITAD_PLUGIN_DIR . 'includes/cli.php';
require_once ADMITAD_PLUGIN_DIR . 'admin/class-admin-menu.php';
require_once ADMITAD_PLUGIN_DIR . 'admin/class-admin-actions.php';
require_once ADMITAD_PLUGIN_DIR . 'admin/class-promocode-lock-metabox.php';
require_once ADMITAD_PLUGIN_DIR . 'admin/class-admin-presenter.php';
require_once ADMITAD_PLUGIN_DIR . 'admin/class-admin-request.php';
require_once ADMITAD_PLUGIN_DIR . 'admin/class-admin-assets.php';
require_once ADMITAD_PLUGIN_DIR . 'admin/class-admin-fragments.php';
require_once ADMITAD_PLUGIN_DIR . 'admin/class-admin-ajax.php';
require_once ADMITAD_PLUGIN_DIR . 'admin/pages/class-settings-page.php';
require_once ADMITAD_PLUGIN_DIR . 'admin/pages/class-overview-page.php';
require_once ADMITAD_PLUGIN_DIR . 'admin/pages/class-sync-page.php';
require_once ADMITAD_PLUGIN_DIR . 'admin/pages/class-diagnostics-page.php';
require_once ADMITAD_PLUGIN_DIR . 'admin/pages/class-category-map-page.php';
require_once ADMITAD_PLUGIN_DIR . 'admin/pages/class-company-page.php';
require_once ADMITAD_PLUGIN_DIR . 'admin/pages/class-rule-page.php';
require_once ADMITAD_PLUGIN_DIR . 'admin/pages/class-review-page.php';
require_once ADMITAD_PLUGIN_DIR . 'admin/pages/class-history-page.php';

/** Remove scheduled jobs without deleting imported content. */
function admitad_coupons_deactivate() {

	foreach ( admitad_cron_hooks() as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, array( 'Promokodiki_Admitad_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, 'admitad_coupons_deactivate' );
Promokodiki_Admitad_Plugin::boot();
Promokodiki_Admitad_Admin_Menu::register();
Promokodiki_Admitad_Admin_Assets::register();
Promokodiki_Admitad_Admin_Ajax::register();
Promokodiki_Admitad_Admin_Actions::register();
Promokodiki_Admitad_Promocode_Lock_Metabox::register();
