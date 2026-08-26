<?php

class Rate_My_Post_Top_Rated_Widget_Shortcode
{
    public function __construct()
    {
        add_shortcode('ratemypost-top-rated', [$this, 'render']);
    }

    public function render($atts)
    {
        $atts = shortcode_atts([
            'number'              => 10,
            'minimum_rating'      => 1,
            'minimum_votes'       => 1,
            'show_featured_image' => 'false',
            'show_star_rating'    => 'false'
        ], $atts);

        $topRatedPosts = Rate_My_Post_Public::top_rated_posts($atts['number'], $atts['minimum_rating'], $atts['minimum_votes']);

        ob_start();

        ?>
        <!-- FeedbackWP (https://feedbackwp.com) - Top Rated Posts Widget -->
        <div class="rmp-tr-posts-widget">
            <?php foreach ($topRatedPosts as $post): ?>
                <div class="rmp-tr-posts-widget__post">
                    <?php if ( ! empty($post['thumb']) && in_array($atts['show_featured_image'], ['true', true, '1'], true)): ?>
                        <div class="rmp-tr-posts-widget__img-container">
                            <a href="<?php echo $post['postLink']; ?>">
                                <img class="rmp-tr-posts-widget__img" src="<?php echo $post['thumb']; ?>" alt="<?php echo $post['title'] ?>"/>
                            </a>
                        </div>
                    <?php endif; ?>
                    <?php if (in_array($atts['show_star_rating'], ['true', true, '1'], true)): ?>
                        <div class="rmp-tr-posts-widget__star-rating">
                            <?php echo Rate_My_Post_Public::get_visual_rating($post['postID']); ?>
                            <span class="rmp-tr-posts-widget__avg-rating"><?php echo $post['avgRating']; ?></span>
                            <span class="rmp-tr-posts-widget__num-votes">(<?php echo $post['votes']; ?>)</span>
                        </div>
                    <?php endif; ?>
                    <?php do_action('rmp_before_widget_title', $post['postID']); ?>
                    <p>
                        <a class="rmp-tr-posts-widget__link" href="<?php echo $post['postLink']; ?>"><?php echo $post['title']; ?></a>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
        <!-- End FeedbackWP - Top Rated Posts Widget -->
        <?php

        return ob_get_clean();
    }

    /**
     * @return self
     */
    public static function init()
    {
        static $instance = null;

        if (is_null($instance)) {
            $instance = new self();
        }

        return $instance;
    }
}
