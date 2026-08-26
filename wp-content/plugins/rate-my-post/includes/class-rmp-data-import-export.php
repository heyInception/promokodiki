<?php

class Rate_My_Post_Data_Import_Export
{
    private $prefix = 'rmp_';

    public function __construct()
    {
        add_action('admin_init', [$this, 'rmp_handle_admin_actions']);
    }

    public function rmp_handle_admin_actions()
    {
        if ( ! isset($_POST['rmp_ie_action'])) return;

        switch ($_POST['rmp_ie_action']) {
            case 'rmp_export':
                $this->download_export();
                break;

            case 'rmp_import':
                $result = $this->handle_import_upload();
                if ($result['success']) {
                    add_action('admin_notices', function () use ($result) {
                        echo '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
                    });
                } else {
                    add_action('admin_notices', function () use ($result) {
                        echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
                    });
                }
                break;
        }
    }

    /**
     * Export all RMP plugin settings to a JSON file
     *
     * @return array Result array with success status and message/data
     */
    public function export_settings()
    {
        // Security checks
        if ( ! $this->verify_permissions()) {
            return ['success' => false, 'message' => 'Insufficient permissions'];
        }

        if ( ! $this->verify_nonce()) {
            return ['success' => false, 'message' => 'Security check failed'];
        }

        global $wpdb;

        try {
            // Prepare and execute query with proper escaping
            $sql = $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like($this->prefix) . '%'
            );

            $results = $wpdb->get_results($sql, ARRAY_A);

            if ($wpdb->last_error) {
                return ['success' => false, 'message' => 'Database error occurred'];
            }

            // Process results and sanitize data
            $export_data = [];
            foreach ($results as $row) {
                $option_name  = sanitize_key($row['option_name']);
                $option_value = $row['option_value'];

                // Try to unserialize if it's serialized data
                $unserialized              = maybe_unserialize($option_value);
                $export_data[$option_name] = $unserialized;
            }
            unset($export_data['rmp_admin_notices']);
            unset($export_data['rmp_version']);
            unset($export_data['rmp_bulk_rate_flag']);

            // Add metadata
            $export_package = [
                'timestamp'  => current_time('timestamp'),
                'site_url'   => get_site_url(),
                'wp_version' => get_bloginfo('version'),
                'data'       => $export_data
            ];

            return [
                'success' => true,
                'data'    => $export_package,
                'count'   => count($export_data)
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Export failed'];
        }
    }

    /**
     * Import RMP plugin settings from uploaded file
     *
     * @param string $file_content JSON content of the import file
     *
     * @return array Result array with success status and message
     */
    public function import_settings($file_content)
    {
        // Security checks
        if ( ! $this->verify_permissions()) {
            return ['success' => false, 'message' => 'Insufficient permissions'];
        }

        if ( ! $this->verify_nonce()) {
            return ['success' => false, 'message' => 'Security check failed'];
        }

        // Validate and decode JSON
        $import_data = json_decode($file_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'Invalid JSON format'];
        }

        // Validate import structure
        if ( ! $this->validate_import_structure($import_data)) {
            return ['success' => false, 'message' => 'Invalid import file structure'];
        }

        global $wpdb;

        try {
            // Start transaction
            $wpdb->query('START TRANSACTION');

            $imported_count = 0;
            $skipped_count  = 0;

            foreach ($import_data['data'] as $option_name => $option_value) {
                // Validate option name
                if ( ! $this->validate_option_name($option_name)) {
                    $skipped_count++;
                    continue;
                }

                // Sanitize option name
                $option_name = sanitize_key($option_name);

                // Use update_option for better handling of existing options
                $result = update_option($option_name, $option_value);

                if ($result !== false) {
                    $imported_count++;
                } else {
                    $skipped_count++;
                }
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            return [
                'success'  => true,
                'message'  => sprintf(
                    'Import completed. %d settings imported, %d skipped.',
                    $imported_count,
                    $skipped_count
                ),
                'imported' => $imported_count,
                'skipped'  => $skipped_count
            ];

        } catch (Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');
            error_log('RMP Import Exception: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Import failed'];
        }
    }

    /**
     * Handle file download for export
     */
    public function download_export()
    {
        $export_result = $this->export_settings();

        if ( ! $export_result['success']) {
            wp_die($export_result['message']);
        }

        $filename  = 'rmp-settings-export-' . date('Y-m-d-H-i-s') . '.json';
        $json_data = wp_json_encode($export_result['data'], JSON_PRETTY_PRINT);

        // Set headers for file download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json_data));
        header('Cache-Control: no-cache, must-revalidate');

        echo $json_data;
        exit;
    }

    /**
     * Handle file upload for import
     */
    public function handle_import_upload()
    {
        if ( ! isset($_FILES['import_file'])) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }

        $file = $_FILES['import_file'];

        // Validate file upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload error'];
        }

        // Check file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            return ['success' => false, 'message' => 'File too large (max 5MB)'];
        }

        // Check file type
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $file['tmp_name']);
        finfo_close($file_info);

        if ( ! in_array($mime_type, ['application/json', 'text/plain'])) {
            return ['success' => false, 'message' => 'Invalid file type. Only JSON files allowed.'];
        }

        // Read file content
        $file_content = file_get_contents($file['tmp_name']);

        if ($file_content === false) {
            return ['success' => false, 'message' => 'Could not read file'];
        }

        // Import the data
        return $this->import_settings($file_content);
    }

    /**
     * Verify user permissions
     */
    private function verify_permissions()
    {
        return current_user_can('manage_options');
    }

    /**
     * Verify WordPress nonce
     */
    private function verify_nonce()
    {
        return isset($_REQUEST['_wpnonce']) &&
               wp_verify_nonce($_REQUEST['_wpnonce'], 'rmp_data_action');
    }

    /**
     * Validate import file structure
     */
    private function validate_import_structure($data)
    {
        $required_keys = ['timestamp', 'data'];

        foreach ($required_keys as $key) {
            if ( ! isset($data[$key])) return false;
        }

        // Verify data is array
        if ( ! is_array($data['data'])) return false;

        return true;
    }

    /**
     * Validate option name
     */
    private function validate_option_name($option_name)
    {
        // Must start with our prefix
        if (strpos($option_name, $this->prefix) !== 0) {
            return false;
        }

        // Must be valid key format
        if ( ! preg_match('/^[a-zA-Z0-9_]+$/', $option_name)) {
            return false;
        }

        return true;
    }

    public static function rmp_admin_page()
    {
        ?>
        <h2 class="rmp-tab-content__title"><?php esc_html_e('Export & Import Settings', 'rate-my-post'); ?></h2>

        <!-- Export Section -->
        <div class="card">
            <h3><?php esc_html_e('Export Settings', 'rate-my-post'); ?></h3>
            <p><?php esc_html_e('Download all plugin settings as a JSON file.', 'rate-my-post'); ?></p>
            <form method="post">
                <?php wp_nonce_field('rmp_data_action'); ?>
                <input type="hidden" name="rmp_ie_action" value="rmp_export">
                <input type="submit" class="button button-primary" value="<?php esc_html_e('Export Settings', 'rate-my-post') ?>">
            </form>
        </div>

        <!-- Import Section -->
        <div class="card">
            <h3><?php esc_html_e('Import Settings', 'rate-my-post'); ?></h3>
            <p><?php esc_html_e('Upload a previously exported JSON file to restore settings.', 'rate-my-post'); ?></p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('rmp_data_action'); ?>
                <input type="hidden" name="rmp_ie_action" value="rmp_import">
                <p><input type="file" name="import_file" accept=".json" required></p>
                <p>
                    <input type="submit" class="button button-primary" value="<?php esc_html_e('Import Settings', 'rate-my-post'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
}