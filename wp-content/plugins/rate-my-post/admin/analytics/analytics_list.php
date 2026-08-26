<?php

class Rate_My_Post_Analytics_List extends \WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'analytic',
            'plural'   => 'analytics',
            'ajax'     => false
        ]);
    }

    public function get_analytics($per_page, $page_number)
    {
        global $wpdb;

        $sql = "SELECT * FROM {$wpdb->prefix}rmp_analytics WHERE 1 = %d";

        $replacement = [1];

        $orderby = ! empty($_REQUEST['orderby']) &&
                   in_array($_REQUEST['orderby'], ['id', 'average', 'votes', 'value']) ? $_REQUEST['orderby'] : 'id';

        $sql .= " ORDER BY $orderby";

        $order = ! empty($_REQUEST['order']) &&
                 in_array($_REQUEST['order'], ['asc', 'desc']) ? $_REQUEST['order'] : 'desc';

        $sql .= sprintf(' %s', strtoupper($order));

        $sql .= " LIMIT $per_page";
        $sql .= ' OFFSET ' . ($page_number - 1) * $per_page;

        return $wpdb->get_results($wpdb->prepare($sql, $replacement), 'ARRAY_A');
    }

    public static function record_count()
    {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}rmp_analytics";

        return $wpdb->get_var($sql);
    }

    public function get_sortable_columns()
    {
        $sortable_columns = [
            'time'    => ['time', false],
            'average' => ['average', false],
            'votes'   => ['votes', false],
            'value'   => ['value', false]
        ];

        return $sortable_columns;
    }

    public function get_columns()
    {
        $columns = [
            'time'     => __('Date', 'rate-my-post'),
            'ip'       => __('IP Address', 'rate-my-post'),
            'user'     => __('User', 'rate-my-post'),
            'post'     => __('Post', 'rate-my-post'),
            'duration' => __('Duration', 'rate-my-post'),
            'average'  => __('Average Rating', 'rate-my-post'),
            'votes'    => __('Total Votes', 'rate-my-post'),
            'value'    => __('Rating', 'rate-my-post')
        ];

        return $columns;
    }

    public function no_items()
    {
        esc_html_e('No analytics found.', 'rate-my-post');
    }

    protected function extra_tablenav($which)
    {
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('rmp_export_analytics_nonce', 'rmp_export_analytics'); ?>
            <input type="hidden" name="action" value="rmp_export_analytics_csv">
            <button type="submit" class="button">
                <?php esc_html_e('Export to CSV', 'rate-my-post'); ?>
            </button>
        </form>
        <?php
    }

    public function column_default($item, $column_name)
    {
        if ($column_name == 'average') {
            return floatval($item['average']);
        }

        if (in_array($column_name, ['votes', 'value'])) {
            return absint($item[$column_name]);
        }

        return $item[$column_name] ?? '';
    }

    public function column_ip($item)
    {
        $ip = $item['ip'] ?? '';

        if ($ip == -1) {
            return esc_html__('Tracking Disabled', 'rate-my-post');
        }

        if ( ! empty($ip)) {
            return sanitize_text_field($ip);
        }

        return 'n/a';
    }

    public function column_user($item)
    {
        $user = $item['user'] ?? '';

        if ($user == -1) {
            return esc_html__('Tracking Disabled', 'rate-my-post');
        }

        if ( ! empty($user)) {

            $user_info = get_userdata($user);
            $username  = $user_info->user_login;
            // allow hiding username in admin panel
            if (has_filter('rmp_rater_username')) {
                $username = apply_filters('rmp_rater_username', $username);
            }

            return $username;
        }

        return esc_html__('Not logged in', 'rate-my-post');
    }

    public function column_post($item)
    {
        $postID = absint($item['post'] ?? '');

        if (get_post_type($postID) != 'crw') {
            return sprintf('<a href="%s">%s</a>', get_the_permalink($postID), get_the_title($postID));
        }

        return get_the_title($postID);
    }

    public function column_duration($item)
    {
        $duration = $item['duration'] ?? '';

        if ($duration == -1) return 'AMP - n/a';

        return absint($duration) . ' seconds';
    }

    public function column_time($item)
    {
        return date('d-m-Y H:i:s', strtotime($item['time'] . ' UTC'));
    }

    public function prepare_items()
    {

        $this->_column_headers = $this->get_column_info();

        $per_page     = $this->get_items_per_page('analytics_per_page', 20);
        $current_page = $this->get_pagenum();
        $total_items  = self::record_count();

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page
        ]);

        $this->items = $this->get_analytics($per_page, $current_page);
    }
}