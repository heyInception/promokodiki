<?php
// Функция для получения популярных промокодов с кешированием
function get_popular_promocodes()
{
    $cache_key = 'popular_promocodes_data';
    $last_update_key = 'popular_promocodes_last_update';

    $last_update = get_option($last_update_key, 0);
    $current_time = current_time('timestamp');
    $cache_duration = 3 * HOUR_IN_SECONDS; // 3 часа

    // Проверяем, нужно ли обновить кеш
    if (($current_time - $last_update) >= $cache_duration) {
        // Обновляем данные
        $promocodes = fetch_fresh_promocodes();
        update_option($cache_key, $promocodes);
        update_option($last_update_key, $current_time);
    } else {
        // Берем из кеша
        $promocodes = get_option($cache_key, array());

        // Если кеш пуст, обновляем принудительно
        if (empty($promocodes)) {
            $promocodes = fetch_fresh_promocodes();
            update_option($cache_key, $promocodes);
            update_option($last_update_key, $current_time);
        }
    }

    return $promocodes;
}

// Функция для получения свежих промокодов
function fetch_fresh_promocodes()
{
    // Получаем количество из настроек или используем значение по умолчанию
    $posts_per_page = get_option('popular_promocodes_count', 4); // По умолчанию 5

    $args = array(
        'post_type' => 'promocode',
        'posts_per_page' => intval($posts_per_page), // Используем динамическое значение
        'meta_key' => '_promocode_used_count',
        'orderby' => 'meta_value_num',
        'order' => 'ASC',
        'meta_query' => array(
            array(
                'key' => '_promocode_expiry_date',
                'value' => date('Y-m-d'),
                'compare' => '>=',
                'type' => 'DATE'
            ),
            array(
                'key' => '_promocode_is_active',
                'value' => 'yes',
                'compare' => '='
            )
        )
    );

    $query = new WP_Query($args);
    $promocodes = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $promocodes[] = get_the_ID();
        }
        wp_reset_postdata();
    }

    return $promocodes;
}
// Получаем время следующего обновления для таймера
// Получаем время следующего обновления для таймера
function get_next_update_time()
{
    $last_update = get_option('popular_promocodes_last_update', 0);

    // Если нет последнего обновления, создаем
    if ($last_update == 0) {
        $last_update = current_time('timestamp');
        update_option('popular_promocodes_last_update', $last_update);
    }

    $next_update = $last_update + (3 * HOUR_IN_SECONDS);
    return $next_update;
}

// Обновление промокодов (возвращаем также время)
add_action('wp_ajax_refresh_popular_promocodes', 'refresh_popular_promocodes');
add_action('wp_ajax_nopriv_refresh_popular_promocodes', 'refresh_popular_promocodes');

function refresh_popular_promocodes()
{
    // Обновляем кеш
    $promocodes = fetch_fresh_promocodes();
    update_option('popular_promocodes_data', $promocodes);
    update_option('popular_promocodes_last_update', current_time('timestamp'));

    // Возвращаем HTML для обновления
    ob_start();
    display_promocodes_items($promocodes);
    $html = ob_get_clean();

    wp_send_json_success(array(
        'html' => $html,
        'next_update' => get_next_update_time(),
        'server_time' => current_time('timestamp')
    ));
}
// Функция для отображения промокодов
function display_promocodes_items($promocode_ids)
{
    if (empty($promocode_ids)) {
        echo '<div class="top__item"><div class="top__head">Нет активных промокодов</div></div>';
        return;
    }

    foreach ($promocode_ids as $post_id) {
        setup_postdata(get_post($post_id));
        $expiry_date = get_post_meta($post_id, '_promocode_expiry_date', true);
        $used_count = get_post_meta($post_id, '_promocode_used_count', true) ?: 0;
        $likes = get_post_meta($post_id, '_promocode_likes', true) ?: 0;
        $dislikes = get_post_meta($post_id, '_promocode_dislikes', true) ?: 0;
        $coupon_code = get_post_meta($post_id, '_promocode_code', true);
        $coupon_link = get_post_meta($post_id, '_promocode_link', true);
        $campaign_name = get_post_meta($post_id, 'campaign_name', true);
        $is_popular = $used_count > 10;
        $is_new = (time() - get_post_time('U', true, $post_id)) < (7 * 24 * 60 * 60);

        $is_expired = false;
        if (!empty($expiry_date)) {
            $current_time = current_time('timestamp');
            $expiry_timestamp = strtotime($expiry_date);
            $expiry_end_of_day = strtotime('tomorrow', $expiry_timestamp) - 1;
            $is_expired = $current_time > $expiry_end_of_day;
        }
?>

        <div class="top__item" data-post-id="<?php echo $post_id; ?>">
            <?php if ($is_expired) : ?>
                <div class="promocodes__badge promocodes__badge_new">Истекло</div>
            <?php elseif ($is_popular) : ?>
                <div class="promocodes__badge promocodes__badge_popular">Популярный</div>
            <?php elseif ($is_new) : ?>
                <div class="promocodes__badge promocodes__badge_new">Новый</div>
            <?php endif; ?>

            <div class="top__img">
                <?php
                $image_url = get_post_meta($post_id, 'image_url', true);
                if ($image_url) : ?>
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo get_the_title($post_id); ?>">
                <?php else : ?>
                    <img src="<?php echo get_template_directory_uri(); ?>/img/top-1.png" alt="">
                <?php endif; ?>
            </div>

            <div class="top__wrap">
                <div class="top__last">
                    <?php if (!$is_expired) : ?>
                        <?php echo human_time_diff(get_post_time('U', true, $post_id), current_time('timestamp')) . ' назад'; ?>
                    <?php else: ?>
                        Истек
                    <?php endif; ?>
                </div>
                <div class="top__max">
                    <?php if (!$expiry_date) : ?>
                        Бессрочно
                    <?php else : ?>
                        до <?php echo date('d.m.Y', strtotime($expiry_date)); ?>
                    <?php endif; ?>
                </div>
            </div>

            <a href="<?php the_permalink(); ?>" class="top__head" title="<?php echo get_the_title($post_id); ?>"><?php echo get_the_title($post_id); ?></a>

            <div class="top__wrapper">
                <div class="top__wrap">
                    <div class="top__quantity"><?php echo $used_count; ?> Применено</div>
                    <div class="top__likes">
                        <div class="top__up" data-action="like" data-post-id="<?php echo $post_id; ?>">
                            <?php echo $likes; ?>
                        </div>
                        <div class="top__down" data-action="dislike" data-post-id="<?php echo $post_id; ?>">
                            <?php echo $dislikes; ?>
                        </div>
                    </div>
                </div>

                <div class="top__author">
                    <?php
                    // Получаем image_url из метаполя поста
                    $image_url = get_post_meta($post_id, 'image_url', true);

                    // Поиск категории магазина
                    $display_name = $campaign_name;
                    $author_url = '';
                    $author_avatar = '';

                    if (!empty($campaign_name)) {
                        $shop_categories = get_terms(array(
                            'taxonomy' => 'shops_category',
                            'hide_empty' => false
                        ));

                        $matched_category = null;
                        $campaign_name_clean = strtolower(trim($campaign_name));

                        foreach ($shop_categories as $category) {
                            $category_name_clean = strtolower(trim($category->name));
                            if (
                                $category_name_clean === $campaign_name_clean ||
                                strpos($campaign_name_clean, $category_name_clean) !== false ||
                                strpos($category_name_clean, $campaign_name_clean) !== false
                            ) {
                                $matched_category = $category;
                                break;
                            }
                        }

                        if ($matched_category) {
                            $author_url = get_term_link($matched_category);
                            $display_name = $matched_category->name;
                        }
                    }

                    // Приоритет: сначала image_url из поста, потом из категории, потом аватар автора
                    if (!empty($image_url)) {
                        $author_avatar = $image_url;
                    } elseif ($matched_category) {
                        $image_id = get_term_meta($matched_category->term_id, 'shops-category-image-id', true);
                        if ($image_id) {
                            $author_avatar = wp_get_attachment_image_url($image_id, 'thumbnail');
                        }
                    }

                    if (empty($author_avatar)) {
                        $author_id = get_post_field('post_author', $post_id);
                        $author_avatar = get_avatar_url($author_id, array('size' => 24));
                    }
                    ?>

                    <?php if ($author_avatar) : ?>
                        <img src="<?php echo esc_url($author_avatar); ?>" alt="<?php echo esc_attr($display_name); ?>">
                    <?php endif; ?>

                    <?php if (!empty($author_url)) : ?>
                        <a href="<?php echo esc_url($author_url); ?>" class="top__nick" target="_blank" rel="nofollow">
                            @<?php echo str_replace(' ', '', $display_name); ?>
                        </a>
                    <?php else : ?>
                        <span class="top__nick">@<?php echo str_replace(' ', '', $display_name); ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($coupon_code) && strpos($coupon_code, 'НЕ НУЖЕН') === false && !$is_expired) : ?>
                    <div class="top__button btn-reset promocodes__view" data-post-id="<?php echo $post_id; ?>" data-coupon-code="<?php echo esc_attr($coupon_code); ?>">
                        Показать промокод
                    </div>
                <?php elseif (!empty($coupon_link)) : ?>
                    <a href="<?php echo esc_url($coupon_link); ?>" rel="nofollow" target="_blank" class="top__button top__button_link btn-reset promocodes__link ">
                        Перейти в магазин
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php
    }
}









// Добавляем настройку в functions.php
add_action('admin_menu', 'popular_promocodes_settings');

function popular_promocodes_settings()
{
    add_options_page(
        'Настройки популярных промокодов',
        'Популярные промокоды',
        'manage_options',
        'popular-promocodes',
        'popular_promocodes_settings_page'
    );
}

function popular_promocodes_settings_page()
{
    ?>
    <div class="wrap">
        <h1>Настройки популярных промокодов</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('popular_promocodes_settings');
            do_settings_sections('popular_promocodes_settings');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="popular_promocodes_count">Количество промокодов</label>
                    </th>
                    <td>
                        <input type="number"
                            id="popular_promocodes_count"
                            name="popular_promocodes_count"
                            value="<?php echo esc_attr(get_option('popular_promocodes_count', 5)); ?>"
                            min="1"
                            max="20" />
                        <p class="description">Количество отображаемых промокодов (от 1 до 20)</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
<?php
}

// Регистрируем настройку
add_action('admin_init', 'register_popular_promocodes_settings');

function register_popular_promocodes_settings()
{
    register_setting('popular_promocodes_settings', 'popular_promocodes_count', array(
        'type' => 'integer',
        'sanitize_callback' => 'intval',
        'default' => 4
    ));
}

add_action('update_option_popular_promocodes_count', 'clear_popular_promocodes_cache', 10, 3);

function clear_popular_promocodes_cache($old_value, $new_value, $option)
{
    delete_option('popular_promocodes_data');
    delete_option('popular_promocodes_last_update');
    // Обновляем сразу
    $promocodes = fetch_fresh_promocodes();
    update_option('popular_promocodes_data', $promocodes);
    update_option('popular_promocodes_last_update', current_time('timestamp'));
}
