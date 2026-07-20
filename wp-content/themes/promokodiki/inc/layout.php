<?php
// В файле functions.php
add_filter('ymc/post/layout/custom_24946', 'custom_promocode_card_layout', 10, 5);
// Если нужен для всех фильтров без ID:
// add_filter('ymc/post/layout/custom', 'custom_promocode_card_layout', 10, 5);

function custom_promocode_card_layout($output, $post_id, $filter_id, $popup_class, $term_settings)
{
    // Переопределяем глобальный объект поста
    global $post;
    $post = get_post($post_id);
    setup_postdata($post);

    // ===== НАЧАЛО ВАШЕЙ КАРТОЧКИ =====
    ob_start();

    $post_type = get_post_type($post_id);
    $meta_prefix = '_promocode_';

    // Получаем метаданные
    $expiry_date = get_post_meta($post_id, $meta_prefix . 'expiry_date', true);
    $used_count = get_post_meta($post_id, $meta_prefix . 'used_count', true) ?: 0;
    $likes = get_post_meta($post_id, $meta_prefix . 'likes', true) ?: 0;
    $dislikes = get_post_meta($post_id, $meta_prefix . 'dislikes', true) ?: 0;
    $is_popular = $used_count > 10;
    $is_new = (time() - get_the_time('U', $post_id)) < (7 * 24 * 60 * 60);
    $coupon_code = get_post_meta($post_id, $meta_prefix . 'code', true);
    $coupon_link = get_post_meta($post_id, $meta_prefix . 'link', true);
    $campaign_name = get_post_meta($post_id, 'campaign_name', true);

    // Проверка на истечение
    $is_expired = false;
    if (!empty($expiry_date)) {
        $current_time = current_time('timestamp');
        $expiry_timestamp = strtotime($expiry_date);
        $expiry_end_of_day = strtotime('tomorrow', $expiry_timestamp) - 1;
        $is_expired = $current_time > $expiry_end_of_day;
    }

    // Получаем категории/таксономии из term_settings (переданных от YMC Filter)
    $category_names = [];
    $category_images = [];

    if (!empty($term_settings)) {
        foreach ($term_settings as $term_info) {
            if ($term_info['term_visible'] === 'true' || $term_info['term_visible'] === true) {
                $category_names[] = esc_html($term_info['term_name']);

                // Если есть иконка в настройках таксономии
                if (!empty($term_info['term_icon_url'])) {
                    $category_images[] = esc_url($term_info['term_icon_url']);
                }
            }
        }
    }

    // Альтернативный способ получения таксономий
    $taxonomies = get_object_taxonomies($post_type);
    $terms = [];
    foreach ($taxonomies as $taxonomy) {
        $post_terms = get_the_terms($post_id, $taxonomy);
        if ($post_terms && !is_wp_error($post_terms)) {
            $terms = array_merge($terms, $post_terms);
        }
    }

    // Получаем бренды для промокодов
    $brands = [];
    if ($post_type === 'promocode') {
        $brand_terms = get_the_terms($post_id, 'promocode_brand');
        if ($brand_terms && !is_wp_error($brand_terms)) {
            $brands = $brand_terms;
        }
    }
?>

    <div class="promocodes__item <?php echo $is_expired ? 'filter-grayscale' : ''; ?>" data-post-id="<?php echo $post_id; ?>">
        <?php if ($is_expired) : ?>
            <div class="promocodes__badge promocodes__badge_new">Истекло</div>
        <?php elseif ($is_popular) : ?>
            <div class="promocodes__badge promocodes__badge_popular">Популярный</div>
        <?php elseif ($is_new) : ?>
            <div class="promocodes__badge promocodes__badge_new">Новый</div>
        <?php endif; ?>

        <?php
        $image_uri = get_post_meta($post_id, 'image_url', true);
        if ($image_uri) {
        ?>
            <div class="promocodes__imgs">
                <?php echo '<img src="' . esc_url($image_uri) . '" alt="' . esc_attr(get_the_title($post_id)) . '">'; ?>
            </div>
        <?php } ?>

        <div class="promocodes__wrapper">
            <div class="promocodes__wrap">
                <?php if (!$is_expired) : ?>
                    <div class="promocodes__latest">Опубликовано <?php echo human_time_diff(get_the_time('U', $post_id), current_time('timestamp')) . ' назад'; ?></div>
                <?php else: ?>
                    <div class="promocodes__latest">Истекло</div>
                <?php endif; ?>
                <?php if (!$expiry_date) : ?>
                    <div class="promocodes__date promocodes__date_dn">Бессрочно</div>
                <?php else : ?>
                    <div class="promocodes__date">до <?php echo date('d.m.Y', strtotime($expiry_date)); ?></div>
                <?php endif; ?>
            </div>

            <a href="<?php echo get_permalink($post_id); ?>" class="promocodes__title"><?php echo get_the_title($post_id); ?></a>

            <div class="promocodes__data">
                <?php if (!empty($category_names) || $campaign_name) : ?>
                    <div class="promocodes__author">
                        <?php
                        // Показываем первую категорию как изображение
                        if (!empty($category_images) && !empty($category_images[0])) : ?>
                            <img src="<?php echo $category_images[0]; ?>" alt="<?php echo $category_names[0] ?? ''; ?>">
                        <?php elseif ($image_uri) : ?>
                            <img src="<?php echo esc_url($image_uri); ?>" alt="<?php echo esc_attr(get_the_title($post_id)); ?>">
                        <?php endif; ?>

                        <?php if ($campaign_name) : ?>
                            <span><?php echo esc_html($campaign_name); ?></span>
                        <?php elseif (!empty($category_names)) : ?>
                            <span><?php echo implode(', ', $category_names); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="promocodes__used"><?php echo $used_count; ?> Применено</div>

                <div class="promocodes__likes">
                    <div class="promocodes__like promocodes__like_yes"
                        data-post-id="<?php echo $post_id; ?>" data-action="like">
                        👍
                        <span><?php echo $likes; ?></span>
                    </div>
                    <div class="promocodes__like promocodes__like_no"
                        data-post-id="<?php echo $post_id; ?>" data-action="dislike">
                        👎
                        <span><?php echo $dislikes; ?></span>
                    </div>
                </div>

                <?php if (!empty($coupon_code) && strpos($coupon_code, 'НЕ НУЖЕН') === false) : ?>
                    <button class="btn-reset promocodes__view promocodes__button" data-post-id="<?php echo $post_id; ?>">Посмотреть код</button>
                <?php else : ?>
                    <a href="<?php echo esc_url($coupon_link); ?>" rel="nofollow">
                        <button class="btn-reset promocodes__link promocodes__button" data-post-id="<?php echo $post_id; ?>">Перейти в магазин</button>
                    </a>
                <?php endif; ?>

                <?php if (!empty($coupon_code) && strpos($coupon_code, 'НЕ НУЖЕН') === false) : ?>
                    <input type="hidden" name="_promocode_code" value="<?php echo esc_attr($coupon_code); ?>">
                    <input type="hidden" name="_promocode_link" value="<?php echo esc_attr($coupon_link); ?>">
                <?php endif; ?>
            </div>
        </div>

        <!-- Мобильная версия -->
        <div class="promocodes__data promocodes__data_m">
            <?php if (!empty($category_names)) : ?>
                <div class="promocodes__author">
                    <?php if (!empty($category_images) && !empty($category_images[0])) : ?>
                        <img src="<?php echo $category_images[0]; ?>" alt="<?php echo $category_names[0] ?? ''; ?>">
                    <?php endif; ?>
                    <span><?php echo implode(', ', $category_names); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($brands)) : ?>
                <?php foreach ($brands as $brand) :
                    $brand_image_id = get_term_meta($brand->term_id, 'image', true);
                    if (!$brand_image_id) $brand_image_id = get_term_meta($brand->term_id, 'brand_image', true);
                    if (!$brand_image_id) $brand_image_id = get_term_meta($brand->term_id, 'promocode_brand-image-id', true);
                    $brand_image_url = $brand_image_id ? wp_get_attachment_image_url($brand_image_id, 'medium') : '';
                ?>
                    <div class="promocodes__author">
                        <?php if ($brand_image_url) : ?>
                            <img src="<?php echo esc_url($brand_image_url); ?>" alt="<?php echo esc_attr($brand->name); ?>">
                        <?php endif; ?>
                        <span><?php echo esc_html($brand->name); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="promocodes__used"><?php echo $used_count; ?> Применено</div>

            <div class="promocodes__likes">
                <div class="promocodes__like promocodes__like_yes"
                    data-post-id="<?php echo $post_id; ?>" data-action="like">
                    👍
                    <span><?php echo $likes; ?></span>
                </div>
                <div class="promocodes__like promocodes__like_no"
                    data-post-id="<?php echo $post_id; ?>" data-action="dislike">
                    👎
                    <span><?php echo $dislikes; ?></span>
                </div>
            </div>

            <?php if (!empty($coupon_code) && strpos($coupon_code, 'НЕ НУЖЕН') === false) : ?>
                <button class="btn-reset promocodes__view promocodes__button" data-post-id="<?php echo $post_id; ?>" data-graph-path="promocode-<?php echo $post_id; ?>">Посмотреть код</button>
            <?php else : ?>
                <a href="<?php echo esc_url($coupon_link); ?>" rel="nofollow">
                    <button class="btn-reset promocodes__link promocodes__button">Перейти в магазин</button>
                </a>
            <?php endif; ?>

            <?php if (!empty($coupon_code) && strpos($coupon_code, 'НЕ НУЖЕН') === false) : ?>
                <input type="hidden" name="_promocode_code" value="<?php echo esc_attr($coupon_code); ?>">
            <?php endif; ?>
        </div>
    </div>

<?php
    // ===== КОНЕЦ КАРТОЧКИ =====
    $output = ob_get_clean();

    // Восстанавливаем глобальный объект поста
    wp_reset_postdata();

    return $output;
}
