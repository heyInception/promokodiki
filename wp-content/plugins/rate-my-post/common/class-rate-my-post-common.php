<?php

/**
 * Methods for both public and admin side
 *
 * @link       http://wordpress.org/plugins/rate-my-post/
 * @since      3.0.0
 *
 * @package    Rate_My_Post
 * @subpackage Rate_My_Post/common
 */

class Rate_My_Post_Common
{

    // Plugin name - string
    private $rate_my_post;

    // Plugin version - string
    private $version;

    // Init
    public function __construct($rate_my_post, $version)
    {
        $this->rate_my_post = $rate_my_post;
        $this->version      = $version;
    }

    // post's vote count
    public static function get_vote_count($post_id = false)
    {
        if ( ! $post_id) {
            $post_id = get_the_id();
        }

        return absint(get_post_meta($post_id, 'rmp_vote_count', true));
    }

    // post's sum of ratings
    public static function get_sum_of_ratings($post_id = false)
    {
        if ( ! $post_id) {
            $post_id = get_the_id();
        }

        return absint(get_post_meta($post_id, 'rmp_rating_val_sum', true));
    }

    // calculate average rating
    public static function calculate_average_rating($sum_of_ratings, $vote_count)
    {
        if ( ! $sum_of_ratings || ! $vote_count) {
            return 0;
        }

        return round(($sum_of_ratings / $vote_count), 1);
    }

    // returns average rating
    public static function get_average_rating($post_id = false)
    {
        if ( ! $post_id) {
            $post_id = get_the_id();
        }
        $ratings_sum = self::get_sum_of_ratings($post_id);
        $vote_count  = self::get_vote_count($post_id);

        if ($ratings_sum && $vote_count) {
            return round(($ratings_sum / $vote_count), 1);
        }

        return 0;
    }

    // returns max rating
    public static function max_rating()
    {
        $max_rating = 5;
        if (has_filter('rmp_max_rating')) {
            $max_rating = apply_filters('rmp_max_rating', $max_rating);
        }

        return absint($max_rating);
    }

    public static function update_post_id_rating($post_id, $vote_count, $avg_rating)
    {
        $post_id = absint($post_id);

        $max_rating = self::max_rating();

        $rating_sum = absint(round($vote_count * $avg_rating));

        // recalculate avg rating - for example if user inserts vote count 1 and rating 4.5 we want to avg rating to be 4 as the former is not possible
        if ($rating_sum && $vote_count) {
            $avg_rating = $rating_sum / $vote_count;
        } else {
            $rating_sum = 0;
            $vote_count = 0;
            $avg_rating = 0;
        }

        if ( ! $vote_count || $vote_count < 1 || $avg_rating < 1 || $avg_rating > $max_rating) {
            return new WP_Error('update_post_id_rating_error', esc_html__('Invalid vote count or average rating!', 'rate-my-post'));
        }

        // update vote count, sum of ratings and average rating
        update_post_meta($post_id, 'rmp_vote_count', $vote_count);

        update_post_meta($post_id, 'rmp_rating_val_sum', $rating_sum);

        update_post_meta($post_id, 'rmp_avg_rating', $avg_rating);

        return true;
    }

    public static function is_boolean($maybe_bool)
    {
        if (is_bool($maybe_bool)) return true;

        if (is_string($maybe_bool)) {

            $maybe_bool = strtolower($maybe_bool);

            $valid_boolean_values = [
                'false',
                'true',
                '0',
                '1',
            ];

            return in_array($maybe_bool, $valid_boolean_values, true);
        }

        if (is_int($maybe_bool)) {
            return in_array($maybe_bool, array(0, 1), true);
        }

        return false;
    }

    public static function get_setting($key, $default = false, $is_empty = false)
    {
        $data = get_option('rmp_options');

        if ($is_empty === true) {
            return isset($data[$key]) && ( ! empty($data[$key]) || self::is_boolean($data[$key])) ? $data[$key] : $default;
        }

        return isset($data[$key]) ? $data[$key] : $default;
    }

    /**
     * Check if flag is set or exists.
     *
     * @param string $flag_id
     *
     * @return bool|mixed
     */
    public static function is_bulk_rate_flag_exists($flag_id)
    {
        return in_array($flag_id, get_option('rmp_bulk_rate_flag', []));
    }

    /**
     * Set a bulk sync flag
     *
     * @param string $flag_id
     *
     * @return bool
     */
    public static function set_bulk_rate_flag($flag_id)
    {
        $old   = get_option('rmp_bulk_rate_flag', []);
        $old[] = $flag_id;

        return update_option('rmp_bulk_rate_flag', $old, false);
    }

    /**
     * Delete a bulk sync flag
     *
     * @param string $flag_id
     *
     * @return bool
     */
    public static function delete_bulk_rate_flag($flag_id)
    {
        $old = get_option('rmp_bulk_rate_flag', []);

        return update_option('rmp_bulk_rate_flag', array_diff($old, [$flag_id]), false);
    }

    public static function is_bulk_rate_background_process()
    {
        return defined('RMP_BULK_RATE_PROCESS_TASK') && RMP_BULK_RATE_PROCESS_TASK === 'true';
    }

    public static function enabled_post_types()
    {
        $bucket = ['crw', 'post'];

        $options = get_option("rmp_options", []);

        if ( ! empty($options['pages'] && $options['pages'] == 2)) $bucket[] = 'page';

        if ( ! empty($options['cptRating']) && is_array($options['cptRating'])) {
            $bucket = array_merge($bucket, $options['cptRating']);
        }

        return $bucket;
    }

    public static function is_show_for_post_edit_screen()
    {
        global $post;

        if (isset($post->post_type)) {

            $is_post_type_check_success = in_array($post->post_type, self::enabled_post_types());

            if (apply_filters('rmp_is_show_for_post_edit_screen', $is_post_type_check_success, $post)) return true;
        }

        return false;
    }
}
