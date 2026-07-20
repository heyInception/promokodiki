<?php
// Обработчик AJAX для поиска
add_action('wp_ajax_load_more_search_results', 'load_more_search_results');
add_action('wp_ajax_nopriv_load_more_search_results', 'load_more_search_results');

function load_more_search_results() {
     $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $search_query = isset($_POST['search_query']) ? sanitize_text_field(trim($_POST['search_query'])) : '';
    
    if (empty($search_query)) {
        wp_die();
    }

    // Получаем категории из URL напрямую
    $promocode_categories = array();
    if (isset($_GET['termset']['promocode_category']) && is_array($_GET['termset']['promocode_category'])) {
        $promocode_categories = array_map('intval', $_GET['termset']['promocode_category']);
    }

    $posts_per_page = 6;
    $offset = ($page - 1) * $posts_per_page;

    // 1. Запрос для категорий магазинов
    $shops_categories_args = array(
        'taxonomy' => 'shops_category',
        'name__like' => $search_query,
        'hide_empty' => true,
        'number' => $posts_per_page,
        'offset' => $offset,
        'orderby' => 'count',
        'order' => 'DESC'
    );
    
    $shops_categories = get_terms($shops_categories_args);
    
    // 2. Запрос для промокодов с учетом категорий
    $promocodes_args = array(
        'post_type' => 'promocode',
        's' => $search_query,
        'posts_per_page' => $posts_per_page,
        'offset' => $offset,
        'post_status' => 'publish',
        'orderby' => 'relevance',
        'order' => 'DESC'
    );
    
    // Добавляем фильтр по категориям, если они есть
    if (!empty($promocode_categories)) {
        $promocodes_args['tax_query'] = array(
            array(
                'taxonomy' => 'promocode_category',
                'field' => 'term_id',
                'terms' => $promocode_categories,
                'operator' => 'IN'
            )
        );
    }
    
    $promocodes_query = new WP_Query($promocodes_args);
    
    $has_results = false;
    
    // Выводим категории магазинов
    if (!empty($shops_categories) && !is_wp_error($shops_categories)) {
        $has_results = true;
        foreach ($shops_categories as $category) {
            // Получаем изображение для категории
            $image_id = get_term_meta($category->term_id, 'category_image_id', true);
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
            
            // Получаем количество промокодов в категории
            $promocode_count = get_term_meta($category->term_id, 'promocode_count', true);
            if (!$promocode_count) {
                $promocode_count = count(get_posts(array(
                    'post_type' => 'promocode',
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'promocode_category',
                            'field' => 'slug',
                            'terms' => $category->slug
                        )
                    ),
                    'posts_per_page' => -1,
                    'fields' => 'ids'
                )));
            }
            
            // Используем шаблон для категорий
            set_query_var('category', $category);
            set_query_var('image_url', $image_url);
            set_query_var('promocode_count', $promocode_count);
            get_template_part('template-parts/shops-category-card');
        }
    }
    
    // Выводим промокоды
    if ($promocodes_query->have_posts()) {
        $has_results = true;
        while ($promocodes_query->have_posts()) {
            $promocodes_query->the_post();
            get_template_part('template-parts/promocode-card');
        }
        wp_reset_postdata();
    }
    
    // Если нет результатов
    if (!$has_results) {
        echo '<p class="no-promocodes">Больше результатов нет.</p>';
    }

    wp_die();
}
?>