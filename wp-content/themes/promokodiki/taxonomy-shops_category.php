<?php

/**
 * Шаблон страницы категории магазинов (taxonomy-shops_category.php)
 */
get_header();

// Получаем текущую категорию
?>
<?php
$current_category = get_queried_object();
$expiry_date = get_post_meta(get_the_ID(), '_promocode_expiry_date', true);
if ($current_category instanceof WP_Term && $current_category->taxonomy === 'shops_category') {
    $image_id = get_term_meta($current_category->term_id, 'shops-category-image-id', true);

    // Проверяем, что изображение существует
    if ($image_id && ($image_url = wp_get_attachment_image_url($image_id, 'medium'))) {
        $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: $current_category->name;
?>
        <div class="container">
            <div class="main__title">
                <h1><?php echo esc_html($current_category->name); ?></h1>
                <?php
                $image_uri = get_post_meta(get_the_ID(), 'image_url', true);
                if ($image_uri) {
                ?>
                    <div class="category-image-wrapper">
                        <?php echo '<img src="' . esc_url($image_uri) . '" alt="' . esc_attr(get_the_title()) . '" class="category-image">'; ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    <?php
    } else {
        // Выводим заглушку, если изображения нет
    ?>
        <div class="container">
            <div class="main__title">
                <h1><?php echo esc_html($current_category->name); ?></h1>
                <?php
                $image_uri = get_post_meta(get_the_ID(), 'image_url', true);
                if ($image_uri) {
                ?>
                    <div class="category-image-wrapper">
                        <?php echo '<img src="' . esc_url($image_uri) . '" alt="' . esc_attr(get_the_title()) . '" class="category-image">'; ?>
                    </div>
                <?php } ?>
            </div>
        </div>
<?php
    }
}
?>
<section class="promocodes">
    <div class="container">
        <div class="promocodes__row">
            <div class="promocodes__column">
                <div class="promocodes__filters">
                    <div class="promocodes__filters-wrap">
                        <?php echo do_shortcode('[fe_widget horizontal="no" show_count="no"]'); ?>
                    </div>

                    <div class="promocodes__sort">
                        <?php echo do_shortcode('[fe_sort id="2"]'); ?>
                    </div>
                </div>

                <div class="promocodes__items">
                    <?php
                    $paged = max(1, get_query_var('paged'));
                    $current_term = get_queried_object();

                    // Определяем параметры запроса
                    $args = array(
                        'posts_per_page' => 6,
                        'paged' => $paged,
                    );

                    if (is_tax('shops_category')) {
                        $args['post_type'] = 'promocode';
                        $args['tax_query'] = array(
                            array(
                                'taxonomy' => 'shops_category',
                                'field' => 'term_id',
                                'terms' => $current_term->term_id,
                            )
                        );
                    } elseif (is_tax('promocode_category')) {
                        $args['post_type'] = 'promocode';
                        $args['tax_query'] = array(
                            array(
                                'taxonomy' => 'promocode_category',
                                'field' => 'term_id',
                                'terms' => $current_term->term_id,
                            )
                        );
                    }

                    $query = new WP_Query($args);

                    if ($query->have_posts()) : ?>
                        <?php while ($query->have_posts()) : $query->the_post(); ?>
                            <?php get_template_part('template-parts/promocode-card'); ?>
                        <?php endwhile; ?>
                        <?php wp_reset_postdata(); ?>
                    <?php else : ?>
                    <?php endif; ?>
                </div>
                <div class="promocodes__desc">
                    <?php echo category_description(); ?>
                </div>
               <?php if (have_rows('sekczii', $current_category )): ?>
                    <?php while (have_rows('sekczii', $current_category )) : the_row(); ?>
                        <?php if (get_row_layout() == 'pervyj_ekran') : ?>
                            <?php get_template_part('template-parts/partials/banner'); ?>
                        <?php elseif (get_row_layout() == 'top_promokodov') : ?>
                            <?php get_template_part('template-parts/partials/top'); ?>
                        <?php elseif (get_row_layout() == 'new') : ?>
                            <?php get_template_part('template-parts/partials/new'); ?>
                        <?php elseif (get_row_layout() == 'promokody') : ?>
                            <?php get_template_part('template-parts/partials/promocodes'); ?>
                        <?php elseif (get_row_layout() == 'faq') : ?>
                            <?php get_template_part('template-parts/partials/faq'); ?>
                        <?php elseif (get_row_layout() == 'seo') : ?>
                            <?php get_template_part('template-parts/partials/seo'); ?>
                        <?php endif; ?>
                    <?php endwhile; ?>
                <?php else: ?>
                    <?php // No layouts found 
                    ?>
                <?php endif; ?>
            </div>

            <aside class="promocodes__aside">
                <?php
                $taxonomy_prefix = 'shops_category';
                $term_id = get_queried_object_id();
                $term_id_prefixed = $taxonomy_prefix . '_' . $term_id;
                ?>
                <?php
                $shop_rating = get_field('rating', $term_id_prefixed) ?: 0; // ACF поле для рейтинга (число от 1 до 5)
                $about_shop = get_field('about_shop', $term_id_prefixed); // ACF поле "О магазине"
                $address = get_field('address', $term_id_prefixed); // ACF поле адреса
                $phone = get_field('phone', $term_id_prefixed); // ACF поле телефона
                $email = get_field('email', $term_id_prefixed); // ACF поле email
                $website = get_field('website', $term_id_prefixed); // ACF поле сайта
                ?>
                <?php $izobrazhenie_magazina = get_field('izobrazhenie_magazina', $term_id_prefixed); ?>
                <div class="promocodes__shop">
                    <div class="promocodes__shop-wrap">
                        <div class="promocodes__shop-logo">
                            <?php
                            $image_uri = get_post_meta(get_the_ID(), 'image_url', true);
                            if ($image_uri) {
                            ?>
                                <?php echo '<img src="' . esc_url($image_uri) . '" alt="' . esc_attr(get_the_title()) . '" class="category-image">'; ?>
                            <?php } ?>
                        </div>
                        <div class="promocodes__shop-stars">
                            <?php
                            // Генерируем случайный рейтинг от 4.0 до 5.0
                            $shop_rating = round(rand(45, 50) / 10, 1); // Пример: 4.2, 4.7, 5.0

                            // Проверяем и приводим рейтинг к числу (на всякий случай)
                            $rating = is_numeric($shop_rating) ? (float)$shop_rating : 0;
                            $full_stars = floor($rating);
                            $has_half_star = ($rating - $full_stars) >= 0.5;

                            // Выводим звёзды
                            for ($i = 1; $i <= 5; $i++):
                                if ($i <= $full_stars): ?>
                                    <svg width="19" height="19" viewBox="0 0 19 19">
                                        <use xlink:href="#star" />
                                    </svg>
                                <?php elseif ($i == $full_stars + 1 && $has_half_star): ?>
                                    <svg width="19" height="19" viewBox="0 0 19 19">
                                        <use xlink:href="#half-star" />
                                        <defs>
                                            <linearGradient id="paint0_linear_532_1985" x1="0.3125" y1="9.59949" x2="19.0403" y2="9.59949" gradientUnits="userSpaceOnUse">
                                                <stop stop-color="#FFB11A" />
                                                <stop offset="0.5" stop-color="#FFB11A" />
                                                <stop offset="0.509615" stop-color="#D9D9D9" />
                                                <stop offset="1" stop-color="#D9D9D9" />
                                            </linearGradient>
                                        </defs>
                                    </svg>
                                <?php else: ?>
                                    <svg width="19" height="19" viewBox="0 0 19 19">
                                        <use xlink:href="#not-star" />
                                    </svg>
                            <?php endif;
                            endfor; ?>
                        </div>
                    </div>

                    <div class="promocodes__shop-title">О магазине</div>
                    <div class="promocodes__shop-text">
                        <?php echo wpautop($about_shop); ?>
                    </div>

                    <button class="promocodes__shop-view btn-reset">Подробнее</button>

                    <div class="promocodes__shop-data">
                        <?php if ($address) : ?>
                            <address class="promocodes__shop-loc">
                                <svg width="18" height="21" viewBox="0 0 18 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <use xlink:href="#svgloc" />
                                </svg>
                                <?php echo esc_html($address); ?>
                            </address>
                        <?php endif; ?>

                        <?php if ($phone) : ?>
                            <div class="promocodes__shop-tel">
                                <svg width="18" height="19" viewBox="0 0 18 19" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <use xlink:href="#svgtel" />
                                </svg>
                                <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a>
                            </div>
                        <?php endif; ?>

                        <?php if ($email) : ?>
                            <div class="promocodes__shop-mail">
                                <svg width="20" height="17" viewBox="0 0 20 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <use xlink:href="#svgmail" />
                                </svg>
                                <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                            </div>
                        <?php endif; ?>

                        <?php if ($website) : ?>
                            <div class="promocodes__shop-site">
                                <svg width="18" height="19" viewBox="0 0 18 19" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <use xlink:href="#svgsite" />
                                </svg>

                                <a href="<?php echo esc_url($website); ?>" target="_blank" rel="nofollow noopener"><?php echo esc_html($website); ?></a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="promocodes__shop-pay">
                        <span>Способы оплаты</span>
                        <div class="promocodes__shop-imgs">
                            <img src="<?php echo get_template_directory_uri(); ?>/img/shop-pay-1.png" alt="">
                            <img src="<?php echo get_template_directory_uri(); ?>/img/shop-pay-2.png" alt="">
                            <img src="<?php echo get_template_directory_uri(); ?>/img/shop-pay-3.png" alt="">
                            <img src="<?php echo get_template_directory_uri(); ?>/img/shop-pay-4.png" alt="">
                            <img src="<?php echo get_template_directory_uri(); ?>/img/shop-pay-5.png" alt="">
                        </div>
                    </div>
                </div>
                <div class="promocodes__store">
                    <div class="promocodes__store-wrap">
                        <div class="promocodes__store-title">Промокоды магазинов</div>
                        <a href="<?php echo esc_url(home_url('/shops/')); ?>" class="promocodes__store-link">
                            Все
                        </a>
                    </div>
                    <div class="promocodes__store-items">
                        <div class="promocodes__store-items">
                            <div class="promocodes__store-items">
                                <?php
                                // Получаем 8 самых популярных категорий магазинов
                                $popular_stores = get_terms(array(
                                    'taxonomy' => 'shops_category',
                                    'orderby' => 'count',
                                    'order' => 'DESC',
                                    'number' => 8,
                                    'hide_empty' => true, // Показывать только категории с постами
                                ));

                                if (!empty($popular_stores) && !is_wp_error($popular_stores)) {
                                    foreach ($popular_stores as $store) {
                                        // Получаем один пост из этой категории
                                        $posts = get_posts(array(
                                            'post_type' => 'promocode',
                                            'tax_query' => array(
                                                array(
                                                    'taxonomy' => 'shops_category',
                                                    'field' => 'term_id',
                                                    'terms' => $store->term_id,
                                                )
                                            ),
                                            'posts_per_page' => 1,
                                            'orderby' => 'date',
                                            'order' => 'DESC'
                                        ));

                                        if (!empty($posts)) {
                                            $post = $posts[0];
                                            $image_uri = get_post_meta($post->ID, 'image_url', true);

                                            if ($image_uri) {
                                                echo '<div class="promocodes__imgs">';
                                                echo '<a href="' . get_term_link($store) . '">';
                                                echo '<img src="' . esc_url($image_uri) . '" alt="' . esc_attr($store->name) . '">';
                                                echo '</a>';
                                                echo '</div>';
                                            }
                                        }
                                    }
                                } else {
                                    echo '<p>Нет популярных магазинов</p>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="promocodes__teams">
                    <div class="promocodes__teams-title">Последние промокоды</div>
                    <div class="promocodes__teams-items">
                        <?php
                        $recent_promocodes = new WP_Query(array(
                            'post_type' => 'promocode',
                            'posts_per_page' => 4,
                            'orderby' => 'date',
                            'order' => 'DESC'
                        ));

                        if ($recent_promocodes->have_posts()) :
                            while ($recent_promocodes->have_posts()) : $recent_promocodes->the_post();
                                $expiry_date = get_post_meta(get_the_ID(), '_promocode_expiry_date', true);
                                $image_uri = get_post_meta(get_the_ID(), 'image_url', true);
                                $terms = get_the_terms(get_the_ID(), 'shops_category');
                                $term_name = !empty($terms) ? $terms[0]->name : '';
                        ?>
                                <div class="promocodes__teams-item">
                                    <div class="promocodes__teams-wrap">
                                        <div class="promocodes__author">
                                            <img src="<?php echo esc_url($image_uri); ?>"
                                                alt="<?php echo esc_html($term_name); ?>">
                                            <span><?php echo esc_html($term_name); ?></span>
                                        </div>
                                        <?php if (!$expiry_date) : ?>
                                            <div class="promocodes__teams-date">Бессрочно</div>
                                        <?php else : ?>
                                            <div class="promocodes__teams-date">до <?php echo date('d.m.Y', strtotime($expiry_date)); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <a href="<?php the_permalink(); ?>" class="promocodes__teams-head"><?php the_title(); ?></a>
                                </div>
                            <?php endwhile; ?>
                        <?php else : ?>
                            <p>Нет доступных промокодов</p>
                        <?php endif;
                        wp_reset_postdata(); ?>
                    </div>
                    <a href="<?php echo esc_url(home_url('/shops/')); ?>" class="promocodes__teams-link">
                        Смотреть все предложения
                        <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M0.994756 10.7327C0.887968 10.7329 0.792554 10.6909 0.708514 10.6067L0.141578 9.853C0.0575377 9.76882 0.0154248 9.66924 0.0152398 9.55427C0.0150424 9.43163 0.0568346 9.33191 0.140615 9.25513L3.37599 6.11271C3.76693 5.733 3.78185 5.11018 3.40953 4.7122L0.266953 1.353C0.182913 1.26882 0.1408 1.16924 0.140615 1.05427C0.140443 0.946957 0.182235 0.847244 0.265991 0.755127L0.691863 0.258856C0.775632 0.174404 0.87091 0.132091 0.977697 0.131918C1.09211 0.131732 1.19515 0.173723 1.28682 0.257889L6.13442 5.125C6.21846 5.20918 6.26057 5.30876 6.26076 5.42373C6.26094 5.53871 6.21915 5.63842 6.13538 5.72288L1.30347 10.6057C1.2197 10.6902 1.1168 10.7325 0.994756 10.7327Z" fill="#FE3388" />
                        </svg>
                    </a>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php
// Скрипты для работы страницы
wp_enqueue_script('clipboard-js', 'https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js', array(), '2.0.8', true);
wp_enqueue_script('select2-js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true);
wp_enqueue_style('select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');

get_footer();
