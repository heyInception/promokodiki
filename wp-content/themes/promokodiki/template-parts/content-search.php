<?php
/**
 * Template part for displaying results in search pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package promokodiki
 */

?>

<?php
if ( 'promocode' === get_post_type() ) {
	get_template_part( 'template-parts/promocode-card' );
	return;
}

// Определяем тип поста и соответствующие префиксы метаполей
$post_type = 'promocode';
$meta_prefix = '_promocode_';

// Получаем общие метаданные
$expiry_date = get_post_meta(get_the_ID(), $meta_prefix . 'expiry_date', true);
$used_count = get_post_meta(get_the_ID(), $meta_prefix . 'used_count', true) ?: 0;
$likes = get_post_meta(get_the_ID(), $meta_prefix . 'likes', true) ?: 0;
$dislikes = get_post_meta(get_the_ID(), $meta_prefix . 'dislikes', true) ?: 0;
$is_popular = $used_count > 10;
$is_new = (time() - get_the_time('U')) < (7 * 24 * 60 * 60);
$coupon_code = get_post_meta(get_the_ID(), $meta_prefix . 'code', true);
$coupon_link = get_post_meta(get_the_ID(), $meta_prefix . 'link', true);
$is_verified = get_post_meta(get_the_ID(), $meta_prefix . 'is_verified', true);
$user_ip = $_SERVER['REMOTE_ADDR'];
$liked_ips = get_post_meta(get_the_ID(), $meta_prefix . 'liked_ips', true) ?: array();
$has_liked = in_array($user_ip, $liked_ips);
$campaign_name = get_post_meta(get_the_ID(), 'campaign_name', true);
// Для shops получаем дополнительные поля

// Проверяем истек ли купон/промокод
$is_expired = false;
if (!empty($expiry_date)) {
    $current_time = current_time('timestamp');
    $expiry_timestamp = strtotime($expiry_date);
    $expiry_end_of_day = strtotime('tomorrow', $expiry_timestamp) - 1;
    $is_expired = $current_time > $expiry_end_of_day;
}

// Получаем данные категории для shops_category
$current_category = get_queried_object();
$image_url = '';
$image_alt = '';
$category_name = '';

if (is_tax('shops_category')) {
    $image_id = get_term_meta($current_category->term_id, 'shops-category-image-id', true);
    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
    $image_alt = $image_id ? (get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: $current_category->name) : '';
    $category_name = $current_category->name;
}
?>
<div id="post-<?php the_ID(); ?>" class="promocodes__item <?php echo $is_expired ? 'filter-grayscale' : ''; ?>" data-post-id="<?php echo get_the_ID(); ?>">
    <?php if ($is_expired) : ?>
        <div class="promocodes__badge promocodes__badge_new">Истекло</div>
    <?php elseif ($is_popular) : ?>
        <div class="promocodes__badge promocodes__badge_popular">Популярный</div>
    <?php elseif ($is_new) : ?>
        <div class="promocodes__badge promocodes__badge_new">Новый</div>
    <?php endif; ?>

    <?php
    $image_uri = get_post_meta(get_the_ID(), 'image_url', true);
    if ($image_uri) {
    ?>
        <div class="promocodes__imgs ">
            <?php echo '<img src="' . esc_url($image_uri) . '" alt="' . esc_attr(get_the_title()) . '">'; ?>
        </div>
    <?php } ?>

    <div class="promocodes__wrapper">
        <div class="promocodes__wrap">
            <?php if (!$is_expired) : ?>
                <div class="promocodes__latest">Опубликовано <?php echo human_time_diff(get_the_time('U'), current_time('timestamp')) . ' назад'; ?></div>
            <?php else: ?>
                <div class="promocodes__latest">Истекло</div>
            <?php endif; ?>
            <?php if (!$expiry_date) : ?>
                <div class="promocodes__date promocodes__date_dn">Бессрочно</div>
            <?php else : ?>
                <div class="promocodes__date ">до <?php echo date('d.m.Y', strtotime($expiry_date)); ?></div>
            <?php endif; ?>
        </div>

        <a href="<?php the_permalink(); ?>" class="promocodes__title"><?php the_title(); ?></a>

        <div class="promocodes__data">
             <div class="promocodes__author">
                    <?php
                    $image_uri = get_post_meta(get_the_ID(), 'image_url', true);
                    if ($image_uri) {
                    ?>
                        <?php echo '<img src="' . esc_url($image_uri) . '" alt="' . esc_attr(get_the_title()) . '">'; ?>
                    <?php } ?>
                    <?php if ($campaign_name) : ?>
                        <span><?php echo esc_html($campaign_name); ?></span>
                    <?php endif; ?>
                </div>

            <div class="promocodes__used"><?php echo $used_count; ?> Применено</div>

            <div class="promocodes__likes">
                <div class="promocodes__like promocodes__like_yes"
                    data-post-id="<?php echo get_the_ID(); ?>" data-action="like">
                    👍
                    <span><?php echo $likes; ?></span>
                </div>
                <div class="promocodes__like promocodes__like_no"
                    data-post-id="<?php echo get_the_ID(); ?>" data-action="dislike">
                    👎
                    <span><?php echo $dislikes; ?></span>
                </div>
            </div>

            <?php if (! empty($coupon_code) && strpos($coupon_code, 'НЕ НУЖЕН') === false) : ?>
                <button class="btn-reset promocodes__view promocodes__button" data-post-id="<?php echo get_the_ID(); ?>">Посмотреть код</button>
            <?php else : ?>
                <a href="<?php echo esc_attr($coupon_link); ?>" rel="nofollow"><button class="btn-reset promocodes__link promocodes__button" data-post-id="<?php echo get_the_ID(); ?>">Перейти в магазин</button></a>
            <?php endif; ?>

            <?php if (! empty($coupon_code) && strpos($coupon_code, 'НЕ НУЖЕН') === false) : ?>
                <input type="hidden" name="_promocode_code" value="<?php echo esc_attr($coupon_code); ?>">
                <input type="hidden" name="_promocode_link" value="<?php echo esc_attr($coupon_link); ?>">
            <?php endif; ?>
        </div>
    </div>

    <div class="promocodes__data promocodes__data_m">
        <?php if ($image_url || $category_name) : ?>
            <div class="promocodes__author">
                <?php if ($image_url) : ?>
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($image_alt); ?>">
                <?php endif; ?>
                <?php if ($category_name) : ?>
                    <span><?php echo esc_html($category_name); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
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
                    <div class="promocodes__author">
                        <?php if ($image_url) : ?>
                            <img src="<?php echo esc_url($image_url); ?>"
                                alt="<?php echo esc_attr($brand->name); ?>">
                        <?php endif; ?>
                        <span><?php echo esc_html($brand->name); ?> </span>
                    </div>
        <?php
                }
            } else {
                echo '<p>Нет привязанных брендов</p>';
            }
        }
        ?>

        <div class="promocodes__used"><?php echo $used_count; ?> Применено</div>

        <div class="promocodes__likes">
            <div class="promocodes__like promocodes__like_yes"
                data-post-id="<?php echo get_the_ID(); ?>" data-action="like">
                👍
                <span><?php echo $likes; ?></span>
            </div>
            <div class="promocodes__like promocodes__like_no"
                data-post-id="<?php echo get_the_ID(); ?>" data-action="dislike">
                👎
                <span><?php echo $dislikes; ?></span>
            </div>
        </div>

        <?php if (!empty($coupon_code) && strpos($coupon_code, 'НЕ НУЖЕН') === false) : ?>
            <button class="btn-reset promocodes__view promocodes__button" data-post-id="<?php echo get_the_ID(); ?>" data-graph-path="promocode-<?php the_ID(); ?>">Посмотреть код</button>
        <?php else : ?>
            <a href="<?php echo esc_attr($coupon_link); ?>" rel="nofollow"><button class="btn-reset promocodes__link promocodes__button">Перейти в магазин</button></a>
        <?php endif; ?>

        <?php if (!empty($coupon_code) && strpos($coupon_code, 'НЕ НУЖЕН') === false) : ?>
            <input type="hidden" name="_promocode_code" value="<?php echo esc_attr($coupon_code); ?>">
        <?php endif; ?>
    </div>
</div>
