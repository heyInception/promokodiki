<?php

use League\Csv\Writer;

class Rate_My_Post_Stats
{
    public static $stats_record;

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
            'label'   => esc_html__('Stats', 'rate-my-post'),
            'default' => 20,
            'option'  => 'stats_per_page'
        ];

        add_screen_option($option, $args);

        require_once dirname(__FILE__) . '/stats_list.php';

        self::$stats_record = new Rate_My_Post_Stats_List();
    }

    public static function admin_page_callback()
    {
        ?>
        <div class="wrap rmp">

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <p><?php esc_html_e('List of rated posts and pages! To see feedback or change ratings click on a post/page title below and find the FeedbackWP Metabox at the bottom.', 'rate-my-post'); ?></p>

                        <form method="post">
                            <?php
                            self::$stats_record->prepare_items();
                            self::$stats_record->display(); ?>
                        </form>
                    </div>
                    <div id="postbox-container-1" class="postbox-container">
                        <div class="meta-box-sortables">
                            <?php Rate_My_Post_Admin::sidebar_content(); ?>
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
        if ('stats_per_page' == $option) {
            return $value;
        }

        return $status;
    }

    /**
     * Handle the CSV export action
     */
    public static function handle_csv_export()
    {
        // Check if we're exporting stats
        if (
            isset($_POST['action']) &&
            $_POST['action'] === 'rmp_export_stats_csv' &&
            isset($_POST['rmp_export_stats']) &&
            wp_verify_nonce($_POST['rmp_export_stats'], 'rmp_export_stats_nonce') &&
            current_user_can('manage_options')
        ) {
            self::export_stats_to_csv_league();
            exit;
        }
    }

    /**
     * Export stats data to CSV using League CSV library
     * Optimized for large datasets with batched processing
     */
    public static function export_stats_to_csv_league()
    {
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
                __('Post ID', 'rate-my-post'),
                __('Title', 'rate-my-post'),
                __('Votes', 'rate-my-post'),
                __('Average Rating', 'rate-my-post'),
                __('Feedback Count', 'rate-my-post'),
                __('Post Type', 'rate-my-post'),
                __('Post URL', 'rate-my-post')
            ]);

            // Process posts in batches to handle large datasets
            $batch_size = 200; // Increased batch size with League CSV
            $offset     = 0;

            do {
                // Get all rated posts in batches
                $args = [
                    'fields'         => 'ids',
                    'post_type'      => Rate_My_Post_Admin::define_post_types(),
                    'post_status'    => 'publish',
                    'posts_per_page' => $batch_size,
                    'offset'         => $offset,
                    'meta_query'     => [
                        [
                            'key'     => 'rmp_vote_count',
                            'value'   => 0,
                            'compare' => '>'
                        ]
                    ]
                ];

                $query = new WP_Query($args);
                $count = count($query->posts);

                // Output data rows
                if ($query->have_posts()) {

                    $rows = [];

                    foreach ($query->posts as $post_id) {
                        // Get feedback count
                        $feedback_count = 0;
                        $feedbacks      = Rate_My_Post_Admin::feedbacks($post_id);
                        if ($feedbacks) {
                            $feedback_count = count($feedbacks);
                        }

                        $post_title = get_the_title($post_id);

                        if (empty($post_title)) {
                            $post_title = 'Unknown Post';
                        }

                        // Prepare row data
                        $rows[] = [
                            $post_id,
                            $post_title,
                            absint(get_post_meta($post_id, 'rmp_vote_count', true)),
                            Rate_My_Post_Common::get_average_rating($post_id),
                            $feedback_count,
                            get_post_type($post_id),
                            get_permalink($post_id)
                        ];
                    }

                    // Add all rows at once for better performance
                    $csv->insertAll($rows);

                    unset($rows);
                }

                // Move to next batch
                $offset += $batch_size;
                wp_reset_postdata();

            } while ($count == $batch_size); // Continue until we get less posts than batch size

            $csv->output('feedbackwp-stats-' . date('Y-m-d') . '.csv');

        } catch (\Exception $e) {
        }
    }
}