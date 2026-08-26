<?php

use League\Csv\Writer;

class Rate_My_Post_Analytics
{
    public static $analytics_record;

    public static function init()
    {
        add_filter('set-screen-option', [__CLASS__, 'set_screen'], 10, 3);
        add_filter('set_screen_option_sync_rules_per_page', [__CLASS__, 'set_screen'], 10, 3);

        // Add action to handle CSV export
        add_action('admin_init', [__CLASS__, 'handle_csv_export']);
    }

    public static function screen_option()
    {
        $option = 'per_page';
        $args   = [
            'label'   => esc_html__('Analytics', 'rate-my-post'),
            'default' => 20,
            'option'  => 'analytics_per_page'
        ];

        add_screen_option($option, $args);

        require_once dirname(__FILE__) . '/analytics_list.php';

        self::$analytics_record = new Rate_My_Post_Analytics_List();
    }

    public static function admin_page_callback()
    {
        ?>
        <div class="wrap rmp">
            <div id="poststuff">
                <p><?php esc_html_e('Here you can see the details about the recent votes on your website.', 'rate-my-post'); ?></p>

                <div id="post-body" class="metabox-holder">
                    <div id="post-body-content">
                        <div class="meta-box-sortables ui-sortable">
                            <form method="post">
                                <?php
                                self::$analytics_record->prepare_items();
                                self::$analytics_record->display(); ?>
                            </form>
                        </div>
                    </div>
                </div>
                <br class="clear">
            </div>
        </div>
        <?php
    }

    public static function set_screen($status, $option, $value)
    {
        if ('analytics_per_page' == $option) {
            return $value;
        }

        return $status;
    }

    /**
     * Handle the CSV export action
     */
    public static function handle_csv_export()
    {
        // Check if we're exporting analytics
        if (
            isset($_POST['action']) &&
            $_POST['action'] === 'rmp_export_analytics_csv' &&
            isset($_POST['rmp_export_analytics']) &&
            wp_verify_nonce($_POST['rmp_export_analytics'], 'rmp_export_analytics_nonce') &&
            current_user_can('manage_options')
        ) {
            self::export_analytics_to_csv_league();
            exit;
        }
    }

    /**
     * Export analytics data to CSV using League CSV library version 9.0
     * Optimized for large datasets with batched processing
     */
    public static function export_analytics_to_csv_league()
    {
        global $wpdb;

        // Increase PHP limits if possible
        @set_time_limit(600); // 10 minutes

        try {
            // Create a new CSV Writer
            $csv = Writer::createFromFileObject(new \SplTempFileObject());

            // Set delimiter and encoding
            $csv->setDelimiter(',');

            // Configure output for streaming with proper BOM for Excel compatibility
            $csv->setOutputBOM(Writer::BOM_UTF8);

            // Add headers row
            $csv->insertOne([
                __('Date', 'rate-my-post'),
                __('IP Address', 'rate-my-post'),
                __('User', 'rate-my-post'),
                __('Post ID', 'rate-my-post'),
                __('Post Title', 'rate-my-post'),
                __('Duration', 'rate-my-post'),
                __('Average Rating', 'rate-my-post'),
                __('Total Votes', 'rate-my-post'),
                __('Rating', 'rate-my-post')
            ]);

            // Process records in batches to handle large datasets
            $batch_size = 1000;
            $offset     = 0;

            do {
                // Get a batch of analytics data
                $sql = $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}rmp_analytics ORDER BY id DESC LIMIT %d OFFSET %d",
                    $batch_size,
                    $offset
                );

                $analytics_batch = $wpdb->get_results($sql, 'ARRAY_A');
                $count           = count($analytics_batch);

                if ($analytics_batch) {

                    $rows = [];

                    foreach ($analytics_batch as $item) {
                        // Format IP address
                        $ip = $item['ip'] ?? '';
                        if ($ip == -1) {
                            $formatted_ip = esc_html__('Tracking Disabled', 'rate-my-post');
                        } elseif ( ! empty($ip)) {
                            $formatted_ip = sanitize_text_field($ip);
                        } else {
                            $formatted_ip = 'n/a';
                        }

                        // Format user
                        $user = $item['user'] ?? '';
                        if ($user == -1) {
                            $formatted_user = esc_html__('Tracking Disabled', 'rate-my-post');
                        } elseif ( ! empty($user)) {
                            $user_info = get_userdata($user);
                            $username  = $user_info->user_login;
                            // allow hiding username in admin panel
                            if (has_filter('rmp_rater_username')) {
                                $username = apply_filters('rmp_rater_username', $username);
                            }
                            $formatted_user = $username;
                        } else {
                            $formatted_user = esc_html__('Not logged in', 'rate-my-post');
                        }

                        // Format post
                        $post_id    = absint($item['post'] ?? '');
                        $post_title = get_the_title($post_id);
                        if (empty($post_title)) {
                            $post_title = esc_html__('Unknown Post', 'rate-my-post');
                        }

                        // Format duration
                        $duration = $item['duration'] ?? '';
                        if ($duration == -1) {
                            $formatted_duration = 'AMP - n/a';
                        } else {
                            $formatted_duration = absint($duration) . ' seconds';
                        }

                        // Format time
                        $formatted_time = date('d-m-Y H:i:s', strtotime($item['time'] . ' UTC'));

                        // Prepare row data
                        $rows[] = [
                            $formatted_time,
                            $formatted_ip,
                            $formatted_user,
                            $post_id,
                            $post_title,
                            $formatted_duration,
                            floatval($item['average']),
                            absint($item['votes']),
                            absint($item['value'])
                        ];
                    }

                    $csv->insertAll($rows);

                    unset($rows);
                }

                // Move to next batch
                $offset += $batch_size;

            } while ($count == $batch_size);

            $csv->output('feedbackwp-analytics-' . date('Y-m-d') . '.csv');

        } catch (\Exception $e) {
        }
    }
}
