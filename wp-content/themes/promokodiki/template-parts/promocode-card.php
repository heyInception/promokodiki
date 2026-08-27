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
$is_telegram = '' !== get_post_meta(get_the_ID(), '_telegram_source_key', true);
$telegram_icon_url = 'https://promokodiki.com/wp-content/uploads/2026/08/telegram-svgrepo-com.svg';
$author_brands = get_the_terms(get_the_ID(), 'shops_category');
if (!$author_brands || is_wp_error($author_brands)) {
    $author_brands = get_the_terms(get_the_ID(), 'promocode_brand');
}
$author_brands = $author_brands && !is_wp_error($author_brands) ? $author_brands : array();

$render_promocode_author = static function () use ($is_telegram, $telegram_icon_url, $author_brands, $campaign_name): void {
    if ($is_telegram) {
        ?>
        <div class="promocodes__author">
            <img src="<?php echo esc_url($telegram_icon_url); ?>" alt="Telegram">
            <span class="top__nick">@telegram</span>
        </div>
        <?php
        return;
    }

    if ($author_brands) {
        foreach ($author_brands as $brand) {
            $image_id = get_term_meta($brand->term_id, '_admitad_shop_logo_id', true);
            if (!$image_id) $image_id = get_term_meta($brand->term_id, 'image', true);
            if (!$image_id) $image_id = get_term_meta($brand->term_id, 'brand_image', true);
            if (!$image_id) $image_id = get_term_meta($brand->term_id, 'promocode_brand-image-id', true);
            $brand_image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
            $brand_url = get_term_link($brand);
            ?>
            <div class="promocodes__author">
                <?php if ($brand_image_url) : ?>
                    <img src="<?php echo esc_url($brand_image_url); ?>" alt="<?php echo esc_attr($brand->name); ?>">
                <?php endif; ?>
                <?php if (!is_wp_error($brand_url)) : ?>
                    <a href="<?php echo esc_url($brand_url); ?>"><span><?php echo esc_html($brand->name); ?></span></a>
                <?php else : ?>
                    <span><?php echo esc_html($brand->name); ?></span>
                <?php endif; ?>
            </div>
            <?php
        }
        return;
    }

    if ($campaign_name) {
        ?>
        <div class="promocodes__author">
            <span><?php echo esc_html($campaign_name); ?></span>
        </div>
        <?php
    }
};
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
    $image_id = get_term_meta($current_category->term_id, '_admitad_shop_logo_id', true);
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
    $image_uri = get_the_post_thumbnail_url(get_the_ID(), 'medium');
    if (!$image_uri) {
        $image_uri = get_post_meta(get_the_ID(), 'image_url', true);
    }
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
            <?php $render_promocode_author(); ?>

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
        <?php $render_promocode_author(); ?>

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
