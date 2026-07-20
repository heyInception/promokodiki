<?php

/**
 * Шаблон страницы категории промокодов (taxonomy-promocode_category.php)
 */
get_header();

// Получаем текущую категорию
$current_category = get_queried_object();
$category_image = get_term_meta($current_category->term_id, 'category_image', true);

?>
<div class="container">
    <div class="main__title">
        <h1><?php single_cat_title() ?></h1>
        <?php
        if ($category_image) {
            echo wp_get_attachment_image($category_image, 'large');
        }
        ?>
    </div>
</div>
<section class="popular">
    <div class="container">
        <?php
        // Получаем текущую категорию
        $current_term = get_queried_object();

        // Получаем подкатегории текущей категории
        $subcategories = get_terms(array(
            'taxonomy' => 'promocode_category',
            'parent' => $current_term->term_id,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ));

        // Проверяем наличие подкатегорий ДО вывода блока
        if ($subcategories && !is_wp_error($subcategories) && !empty($subcategories)) :
        ?>

            <div class="popular__column">
                <div class="popular__title">
                    <h2>Подкатегории</h2>
                </div>
                <div class="popular__items">
                    <?php
                    $popular_colors = ['pink', 'blue', 'orange', 'yellow'];
                    $color_index = 0;

                    foreach ($subcategories as $subcategory) :
                        $image_id = get_term_meta($subcategory->term_id, 'category_image', true);
                        $image_url = $image_id ? get_template_directory_uri() . '/img/default-category.jpg' : get_template_directory_uri() . '/img/default-category.jpg';

                        // Цвет для каждой подкатегории (циклически)
                        $current_color = $popular_colors[$color_index % count($popular_colors)];
                    ?>
                        <a href="<?php echo get_term_link($subcategory); ?>"
                            class="banner__item banner__item_<?php echo $current_color; ?>">
                            <?php echo esc_html($subcategory->name); ?>
                        </a>
                    <?php
                        $color_index++;
                    endforeach;
                    ?>
                </div>
            </div>

        <?php endif; // Конец проверки наличия подкатегорий 
        ?>
    </div>
</section>
<section class="promocodes">
    <div class="container">
        <div class="promocodes__row">
            <div class="promocodes__column">
                <div class="promocodes__filters">
                    <div class="promocodes__filters-wrap">
                        <?php //echo do_shortcode('[fe_widget id="99" horizontal="no" show_count="no"]'); ?>
                       
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
                        <p class="no-promocodes">В этой категории пока нет промокодов.</p>
                    <?php endif; ?>
                </div>
                <?php if (category_description()) : ?>
                    <div class="promocodes__desc">
                        <?php echo category_description(); ?>
                    </div>
                <?php endif; ?>
                <?php if (have_rows('sekczii', $current_category)): ?>
                    <?php while (have_rows('sekczii', $current_category)) : the_row(); ?>
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

                <div class="promocodes__rating">
                    <div class="promocodes__rating-title">Рейтинг <?php bloginfo('name'); ?></div>
                    <div class="promocodes__rating-subtitle">Нас оценили уже 1 500 пользователей 🤩</div>
                    <div class="promocodes__rating-stars">
                        <div class="promocodes__rating-star promocodes__rating-star_ya">
                            <img src="<?php echo get_template_directory_uri(); ?>/img/rating-img-1.png" alt="Яндекс">
                            <span>4,5 <span>/5</span></span>
                        </div>
                        <div class="promocodes__rating-star promocodes__rating-star_ggl">
                            <img src="<?php echo get_template_directory_uri(); ?>/img/rating-img-2.png" alt="Google">
                            <span>4,5 <span>/5</span></span>
                        </div>
                        <div class="promocodes__rating-star promocodes__rating-star_str">
                            <img src="<?php echo get_template_directory_uri(); ?>/img/rating-img-3.png" alt="Trustpilot">
                            <span>5 <span>/5</span></span>
                        </div>
                    </div>
                    <div class="promocodes__rating-content">
                        <p>Сервис "<?php bloginfo('name'); ?>" помогает Вам экономить на покупках в любимых магазинах. Наша команда вручную отбирает лучшие промокоды и скидки на самые популярные и стильные товары.</p>
                        <p>Присоединяйтесь к нашему сообществу и наслаждайтесь шопингом с умом — быть в тренде стало ещё доступнее!</p>
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
                                $campaign_name = get_post_meta(get_the_ID(), 'campaign_name', true);
                        ?>
                                <div class="promocodes__teams-item">
                                    <div class="promocodes__teams-wrap">
                                        <div class="promocodes__author">
                                            <img src="<?php echo esc_url($image_uri); ?>"
                                                alt="<?php echo esc_html($campaign_name); ?>">
                                            <span><?php echo esc_html($campaign_name); ?></span>
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
                    <a href="<?php echo get_post_type_archive_link('promocode'); ?>" class="promocodes__teams-link">
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
