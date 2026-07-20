<?php

function get_popular_promocodes()
{
    $args = array(
        'post_type' => 'promocode',
        'posts_per_page' => 4, // 4 самых популярных
        'meta_key' => '_promocode_likes',
        
        'orderby' => 'meta_value_num',
        'order' => 'DESC',
        'meta_query' => array(
            array(
                'key' => '_promocode_likes',
                'value' => 2,
                'compare' => '>=',
                'type' => 'NUMERIC'
            )
        )
    );

    return new WP_Query($args);
}

function update_promocodes_transient()
{
    // Устанавливаем следующее обновление через 3 часа от текущего времени
    $next_update = time() + (3 * HOUR_IN_SECONDS);
    set_transient('promocodes_next_update', $next_update, 3 * HOUR_IN_SECONDS);

    return $next_update;
}


add_action('wp_ajax_get_popular_promocodes', 'ajax_get_popular_promocodes');
add_action('wp_ajax_nopriv_get_popular_promocodes', 'ajax_get_popular_promocodes');

function ajax_get_popular_promocodes()
{
    $popular_promocodes = get_popular_promocodes();
    $html = '';

    if ($popular_promocodes->have_posts()) {
        ob_start();
        while ($popular_promocodes->have_posts()) : $popular_promocodes->the_post();
            $expiry_date = get_post_meta(get_the_ID(), '_promocode_expiry_date', true);
            $used_count = get_post_meta(get_the_ID(), '_promocode_used_count', true) ?: 0;
            $likes = get_post_meta(get_the_ID(), '_promocode_likes', true) ?: 0;
            $dislikes = get_post_meta(get_the_ID(), '_promocode_dislikes', true) ?: 0;
            $is_new = (time() - get_the_time('U')) < (7 * 24 * 60 * 60);
            $is_popular = $used_count > 10; // Пример условия для популярного
            $coupon_code = get_post_meta(get_the_ID(), '_promocode_code', true);
            $thumbnail_url = get_the_post_thumbnail_url() ?: get_template_directory_uri() . '/img/top-default.png';

            $user_ip = $_SERVER['REMOTE_ADDR'];
            $liked_ips = get_post_meta(get_the_ID(), '_promocode_liked_ips', true) ?: array();
            $has_liked = in_array($user_ip, $liked_ips);

            $is_expired = false;
            if (!empty($expiry_date)) {
                $current_time = current_time('timestamp');
                $expiry_timestamp = strtotime($expiry_date);
                // Добавляем 1 день к expiry date, чтобы промокод был валиден весь день истечения
                $expiry_end_of_day = strtotime('tomorrow', $expiry_timestamp) - 1;
                $is_expired = $current_time > $expiry_end_of_day;
            }
?>
            <div class="top__item <?php if ($is_expired) : ?> filter-grayscale  <?php endif; ?>" data-post-id="<?php echo get_the_ID(); ?>">
                <?php if ($is_expired) : ?>
                    <div class="promocodes__badge promocodes__badge_new">Истекло
                    </div>
                <?php elseif ($is_new) : ?>
                    <div class="promocodes__badge promocodes__badge_new">Новый</div>
                <?php elseif ($is_popular) : ?>
                    <div class="promocodes__badge promocodes__badge_popular">Популярный</div>
                <?php endif; ?>
                <a href="<?php the_permalink(); ?>" class="top__img">
                    <img src="<?php echo $thumbnail_url; ?>" alt="<?php the_title(); ?>">
                </a>
                <div class="top__wrap">
                    <div class="top__last"><?php echo human_time_diff(get_the_time('U'), current_time('timestamp')) . ' назад'; ?></div>
                    <?php if ($expiry_date) : ?>
                        <div class="top__max">до <?php echo date('d.m.Y', strtotime($expiry_date)); ?></div>
                    <?php endif; ?>
                </div>
                <a href="<?php the_permalink(); ?>" class="top__head"><?php the_title(); ?></a>
                <div class="top__wrapper">
                    <div class="top__wrap">
                        <div class="top__quantity"><?php echo $used_count; ?> Применено</div>
                        <div class="top__likes">
                            <div class="top__up promocodes__like promocodes__like_yes" data-post-id="<?php echo get_the_ID(); ?>" data-action="like"><?php echo $likes; ?></div>
                            <div class="top__down promocodes__like promocodes__like_no" data-post-id="<?php echo get_the_ID(); ?>" data-action="dislike"><?php echo $dislikes; ?></div>
                        </div>
                    </div>
                    <?php
                    // Альтернативный способ получить ID поста
                    $post_id = get_the_ID();

                    if ('promocode' === get_post_type($post_id)) {
                        $brands = get_the_terms($post_id, 'promocode_brand');

                        if ($brands && !is_wp_error($brands)) {
                            foreach ($brands as $brand) {
                                // Пробуем разные варианты метаполей
                                $image_id = get_term_meta($brand->term_id, 'image', true);
                                if (!$image_id) $image_id = get_term_meta($brand->term_id, 'brand_image', true);
                                if (!$image_id) $image_id = get_term_meta($brand->term_id, 'promocode_brand-image-id', true);

                                $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
                    ?>
                                <div class="top__author">
                                    <?php if ($image_url) : ?>
                                        <img src="<?php echo esc_url($image_url); ?>"
                                            alt="<?php echo esc_attr($brand->name); ?>">
                                    <?php endif; ?>
                                    <div class="top__nick"><?php echo esc_html($brand->name); ?></div>
                                </div>
                    <?php
                            }
                        } else {
                            echo '<p>Нет привязанных брендов</p>';
                        }
                    }
                    ?>
                    <?php if ($coupon_code) : ?>
                        <button class="top__button"  data-post-id="<?php echo get_the_ID(); ?>" data-graph-path="promocode-<?php the_ID(); ?>">Показать промокод</button>
                    <?php else : ?>
                        <a href="<?php the_permalink(); ?>" class="top__button">Подробнее</a>
                    <?php endif; ?>
                </div>
            </div>
<?php
        endwhile;
        wp_reset_postdata();
        $html = ob_get_clean();
    } else {
        $html = '<p>Популярных промокодов пока нет</p>';
    }

    update_promocodes_transient();

    wp_send_json_success(array('html' => $html));
}
add_action('rest_api_init', function() {
    register_rest_route('promocodes/v1', '/next-update', array(
        'methods' => 'GET',
        'callback' => 'get_next_update_time',
        'permission_callback' => '__return_true'
    ));
});

function get_next_update_time() {
    $next_update = get_transient('promocodes_next_update');
    
    if (false === $next_update) {
        $next_update = time() + (3 * HOUR_IN_SECONDS);
        set_transient('promocodes_next_update', $next_update, 3 * HOUR_IN_SECONDS);
    }
    
    return array(
        'next_update' => $next_update,
        'current_time' => time()
    );
}

// Включить комментарии для типа записи promocode
add_filter('comments_open', 'enable_comments_for_promocode', 10, 2);
function enable_comments_for_promocode($open, $post_id) {
    $post = get_post($post_id);
    if ($post->post_type == 'promocode') {
        $open = true;
    }
    return $open;
}

// Разрешить комментарии по умолчанию для promocode
add_action('wp_insert_post', 'set_default_comments_for_promocode', 10, 3);
function set_default_comments_for_promocode($post_id, $post, $update) {
    if (!$update && $post->post_type == 'promocode') {
        update_post_meta($post_id, '_wp_page_template', 'default');
        wp_update_post(array(
            'ID' => $post_id,
            'comment_status' => 'open'
        ));
    }
}