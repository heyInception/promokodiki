<?php
// Определяем тип поста и соответствующие префиксы метаполей
$post_type = 'promocode';
$meta_prefix = '_promocode_';

// Получаем общие метаданные
$expiry_date = get_post_meta(get_the_ID(), $meta_prefix . 'expiry_date', true);
$used_count = get_post_meta(get_the_ID(), $meta_prefix . 'used_count', true) ?: 0;
$likes = get_post_meta(get_the_ID(), $meta_prefix . 'likes', true) ?: 0;
$dislikes = get_post_meta(get_the_ID(), $meta_prefix . 'dislikes', true) ?: 0;
$badge = class_exists( 'Promokodiki_Filter_Promo_Status' ) ? Promokodiki_Filter_Promo_Status::for_post( get_the_ID() ) : '';
$is_popular = 'popular' === $badge;
$is_new = 'new' === $badge;
$coupon_code = get_post_meta(get_the_ID(), $meta_prefix . 'code', true);
$coupon_link = get_post_meta(get_the_ID(), $meta_prefix . 'link', true);
$is_verified = get_post_meta(get_the_ID(), $meta_prefix . 'is_verified', true);
$campaign_name = get_post_meta(get_the_ID(), 'campaign_name', true);
// Для shops получаем дополнительные поля

// Проверяем истек ли купон/промокод
$is_expired = 'expired' === $badge;
if ('' === $badge && !empty($expiry_date)) {
    $current_time = current_time('timestamp');
    $expiry_timestamp = strtotime($expiry_date);
    $expiry_end_of_day = strtotime('tomorrow', $expiry_timestamp) - 1;
    $is_expired = $current_time > $expiry_end_of_day;
}

$has_coupon_code = ! empty( $coupon_code ) && false === strpos( $coupon_code, 'НЕ НУЖЕН' );
$expiry_label    = $expiry_date ? wp_date( 'd.m.Y', strtotime( $expiry_date ) ) : 'Бессрочно';
$visitor_id      = isset( $_COOKIE['promokodiki_visitor'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['promokodiki_visitor'] ) ) : '';
$user_reaction   = class_exists( 'Promokodiki_Filter_Promo_Interactions' )
    ? Promokodiki_Filter_Promo_Interactions::reaction_for( get_the_ID(), $visitor_id )
    : '';
$description     = wp_strip_all_tags( get_the_excerpt() );

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
<div
    class="promocodes__item <?php echo $is_expired ? 'filter-grayscale' : ''; ?>"
    data-post-id="<?php echo esc_attr( (string) get_the_ID() ); ?>"
    data-store-url="<?php echo esc_url( $coupon_link ); ?>"
    data-code="<?php echo esc_attr( $has_coupon_code ? $coupon_code : '' ); ?>"
    data-expiry="<?php echo esc_attr( $expiry_label ); ?>"
    data-expired="<?php echo $is_expired ? 'true' : 'false'; ?>"
    data-description="<?php echo esc_attr( $description ); ?>"
>
    <?php if ('expired' === $badge) : ?>
        <div class="promocodes__badge promocodes__badge_new">Истекло</div>
    <?php elseif ('popular' === $badge) : ?>
        <div class="promocodes__badge promocodes__badge_popular">Популярный</div>
    <?php elseif ('new' === $badge) : ?>
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
                <div class="promocodes__like promocodes__like_yes<?php echo 'like' === $user_reaction ? ' is-active' : ''; ?>"
                    data-post-id="<?php echo get_the_ID(); ?>" data-action="like">
                    👍
                    <span><?php echo $likes; ?></span>
                </div>
                <div class="promocodes__like promocodes__like_no<?php echo 'dislike' === $user_reaction ? ' is-active' : ''; ?>"
                    data-post-id="<?php echo get_the_ID(); ?>" data-action="dislike">
                    👎
                    <span><?php echo $dislikes; ?></span>
                </div>
            </div>

            <?php if ( $is_expired ) : ?>
                <button class="btn-reset promocodes__button" disabled>Промокод истёк</button>
            <?php elseif ( $has_coupon_code ) : ?>
                <button class="btn-reset promocodes__view promocodes__button ui-button ui-button--orange" data-post-id="<?php echo get_the_ID(); ?>">Посмотреть код</button>
            <?php else : ?>
                <a href="<?php echo esc_url( $coupon_link ); ?>" class="btn-reset promocodes__link promocodes__button ui-button ui-button--orange" data-post-id="<?php echo esc_attr( (string) get_the_ID() ); ?>" rel="nofollow noopener" target="_blank">Перейти в магазин</a>
            <?php endif; ?>

            <?php if ( $has_coupon_code ) : ?>
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
                        <?php $brand_url = get_term_link( $brand ); ?>
                        <?php if ( ! is_wp_error( $brand_url ) ) : ?><a href="<?php echo esc_url( $brand_url ); ?>"><span><?php echo esc_html($brand->name); ?></span></a><?php else : ?><span><?php echo esc_html($brand->name); ?></span><?php endif; ?>
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
            <div class="promocodes__like promocodes__like_yes<?php echo 'like' === $user_reaction ? ' is-active' : ''; ?>"
                data-post-id="<?php echo get_the_ID(); ?>" data-action="like">
                👍
                <span><?php echo $likes; ?></span>
            </div>
            <div class="promocodes__like promocodes__like_no<?php echo 'dislike' === $user_reaction ? ' is-active' : ''; ?>"
                data-post-id="<?php echo get_the_ID(); ?>" data-action="dislike">
                👎
                <span><?php echo $dislikes; ?></span>
            </div>
        </div>

        <?php if ( $is_expired ) : ?>
            <button class="btn-reset promocodes__button" disabled>Промокод истёк</button>
        <?php elseif ( $has_coupon_code ) : ?>
            <button class="btn-reset promocodes__view promocodes__button ui-button ui-button--orange" data-post-id="<?php echo get_the_ID(); ?>" data-graph-path="promocode-<?php the_ID(); ?>">Посмотреть код</button>
        <?php else : ?>
            <a href="<?php echo esc_url( $coupon_link ); ?>" class="btn-reset promocodes__link promocodes__button ui-button ui-button--orange" data-post-id="<?php echo esc_attr( (string) get_the_ID() ); ?>" rel="nofollow noopener" target="_blank">Перейти в магазин</a>
        <?php endif; ?>

        <?php if ( $has_coupon_code ) : ?>
            <input type="hidden" name="_promocode_code" value="<?php echo esc_attr($coupon_code); ?>">
        <?php endif; ?>
    </div>
</div>
