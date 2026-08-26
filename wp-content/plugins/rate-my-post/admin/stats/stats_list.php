<?php

class Rate_My_Post_Stats_List extends \WP_List_Table
{
    static $cache = null;

    public function __construct()
    {
        parent::__construct([
            'singular' => 'stat',
            'plural'   => 'stats',
            'ajax'     => false
        ]);
    }

    private function get_wp_query($per_page, $page_number)
    {
        if (is_null(self::$cache)) {

            $order = ! empty($_REQUEST['order']) &&
                     in_array($_REQUEST['order'], ['asc', 'desc']) ? $_REQUEST['order'] : 'desc';

            $args = [
                'fields'         => 'ids',
                'post_type'      => Rate_My_Post_Admin::define_post_types(),
                'posts_per_page' => $per_page,
                'paged'          => $page_number,
                'meta_query'     => [
                    [
                        'key'     => 'rmp_vote_count',
                        'value'   => 0,
                        'compare' => '>'
                    ]
                ]
            ];

            if ( ! empty($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], [
                    'rmp_avg_rating',
                    'rmp_vote_count'
                ])) {
                $args['meta_key'] = sanitize_text_field($_REQUEST['orderby']);
                $args['orderby']  = 'meta_value_num';
                $args['order']    = strtoupper($order);
            }

            self::$cache = new WP_Query($args);
        }

        return self::$cache;
    }

    public function get_stats($per_page, $page_number)
    {
        return $this->get_wp_query($per_page, $page_number)->get_posts();
    }

    public function record_count()
    {
        return self::$cache->found_posts;
    }

    public function get_columns()
    {
        return [
            'title'          => __('Title', 'rate-my-post'),
            'rmp_vote_count' => __('Votes', 'rate-my-post'),
            'rmp_avg_rating' => __('Average Rating', 'rate-my-post'),
            'feedback'       => __('Feedback', 'rate-my-post')
        ];
    }

    public function get_sortable_columns()
    {
        return [
            'rmp_avg_rating' => ['rmp_avg_rating', true],
            'rmp_vote_count' => ['rmp_vote_count', true]
        ];
    }

    public function no_items()
    {
        esc_html_e('No stats found.', 'rate-my-post');
    }

    protected function extra_tablenav($which)
    {
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('rmp_export_stats_nonce', 'rmp_export_stats'); ?>
            <input type="hidden" name="action" value="rmp_export_stats_csv">
            <button type="submit" class="button">
                <?php esc_html_e('Export to CSV', 'rate-my-post'); ?>
            </button>
        </form>
        <?php
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'title':
                return sprintf('<a href="%s" target="_blank">%s</a>', get_edit_post_link($item), get_the_title($item));
            case 'rmp_vote_count':
                return absint(get_post_meta($item, 'rmp_vote_count', true));
            case 'rmp_avg_rating':
                return Rate_My_Post_Common::get_average_rating($item);
            case 'feedback':
                $feedback_count = 0;
                $data           = Rate_My_Post_Admin::feedbacks($item);
                if ($data) $feedback_count = count($data);

                return $feedback_count;
        }

        return '';
    }

    public function prepare_items()
    {
        $per_page     = $this->get_items_per_page('stats_per_page', 20);
        $current_page = $this->get_pagenum();

        $this->items = $this->get_stats($per_page, $current_page);

        $this->_column_headers = $this->get_column_info();

        $total_items = self::record_count();

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page
        ]);
    }
}