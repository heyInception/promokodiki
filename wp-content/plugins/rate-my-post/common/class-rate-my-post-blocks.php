<?php

class Rate_My_Post_Blocks
{
    public function __construct()
    {
        add_action('init', [$this, 'register_blocks']);

        add_action('enqueue_block_assets', [$this, 'load_assets_in_block']);
    }

    public function register_blocks()
    {
        if ( ! function_exists('register_block_type')) return;

        register_block_type(__DIR__ . '/../blocks/build/rating-widget');
        register_block_type(__DIR__ . '/../blocks/build/rating-result-widget');
        register_block_type(__DIR__ . '/../blocks/build/top-rated-posts');
    }

    public function load_assets_in_block()
    {
        global $plugin_public;

        $plugin_public->enqueue_styles();
    }

    /**
     * @return self
     */
    public static function get_instance()
    {
        static $instance = null;

        if (is_null($instance)) {
            $instance = new self();
        }

        return $instance;
    }
}
