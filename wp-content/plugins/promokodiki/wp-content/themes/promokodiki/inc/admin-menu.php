<?php
/** Admin presentation for the canonical promocode post type. */

if (!defined('ABSPATH')) {
    exit;
}

function promokodiki_add_promocode_meta_box()
{
    add_meta_box('promocode_details', 'Детали промокода', 'promokodiki_render_promocode_meta_box', 'promocode', 'normal', 'high');
}
add_action('add_meta_boxes_promocode', 'promokodiki_add_promocode_meta_box');

function promokodiki_render_promocode_meta_box($post)
{
    wp_nonce_field('promokodiki_save_promocode', 'promokodiki_promocode_nonce');
    $fields = array(
        '_promocode_code' => array('Код', 'text'),
        '_promocode_link' => array('Ссылка', 'url'),
        '_promocode_expiry_date' => array('Дата окончания', 'date'),
    );
    echo '<table class="form-table">';
    foreach ($fields as $key => $field) {
        printf(
            '<tr><th><label for="%1$s">%2$s</label></th><td><input class="regular-text" type="%3$s" id="%1$s" name="%1$s" value="%4$s"></td></tr>',
            esc_attr($key),
            esc_html($field[0]),
            esc_attr($field[1]),
            esc_attr(get_post_meta($post->ID, $key, true))
        );
    }
    foreach (array('_promocode_is_active' => 'Активен', '_promocode_is_verified' => 'Проверен') as $key => $label) {
        printf(
            '<tr><th>%1$s</th><td><label><input type="checkbox" name="%2$s" value="yes" %3$s> Да</label></td></tr>',
            esc_html($label),
            esc_attr($key),
            checked(get_post_meta($post->ID, $key, true), 'yes', false)
        );
    }
    echo '</table>';
}

function promokodiki_save_promocode_meta($post_id)
{
    if (!isset($_POST['promokodiki_promocode_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['promokodiki_promocode_nonce'])), 'promokodiki_save_promocode')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE || !current_user_can('edit_post', $post_id)) {
        return;
    }
    $sanitizers = array(
        '_promocode_code' => 'sanitize_text_field',
        '_promocode_link' => 'esc_url_raw',
        '_promocode_expiry_date' => 'sanitize_text_field',
    );
    foreach ($sanitizers as $key => $sanitizer) {
        $value = isset($_POST[$key]) ? call_user_func($sanitizer, wp_unslash($_POST[$key])) : '';
        update_post_meta($post_id, $key, $value);
    }
    foreach (array('_promocode_is_active', '_promocode_is_verified') as $key) {
        update_post_meta($post_id, $key, isset($_POST[$key]) ? 'yes' : 'no');
    }
}
add_action('save_post_promocode', 'promokodiki_save_promocode_meta');

function promokodiki_promocode_columns($columns)
{
    $columns['promocode_code'] = 'Код';
    $columns['promocode_expiry'] = 'Действует до';
    $columns['promocode_shop'] = 'Магазин';
    return $columns;
}
add_filter('manage_promocode_posts_columns', 'promokodiki_promocode_columns');

function promokodiki_render_promocode_column($column, $post_id)
{
    if ('promocode_code' === $column) {
        echo esc_html(get_post_meta($post_id, '_promocode_code', true));
    } elseif ('promocode_expiry' === $column) {
        echo esc_html(get_post_meta($post_id, '_promocode_expiry_date', true));
    } elseif ('promocode_shop' === $column) {
        $terms = get_the_terms($post_id, 'shops_category');
        echo esc_html($terms && !is_wp_error($terms) ? implode(', ', wp_list_pluck($terms, 'name')) : '—');
    }
}
add_action('manage_promocode_posts_custom_column', 'promokodiki_render_promocode_column', 10, 2);

function promokodiki_term_image_add_field()
{
    echo '<div class="form-field"><label for="shops-category-image-id">Изображение</label><input type="hidden" id="shops-category-image-id" name="shops-category-image-id" value=""><div id="shops-category-image-wrapper"></div><button type="button" class="button shops_tax_media_button">Выбрать</button> <button type="button" class="button shops_tax_media_remove">Удалить</button></div>';
}

function promokodiki_term_image_edit_field($term)
{
    $image_id = absint(get_term_meta($term->term_id, 'shops-category-image-id', true));
    echo '<tr class="form-field"><th><label for="shops-category-image-id">Изображение</label></th><td>';
    echo '<input type="hidden" id="shops-category-image-id" name="shops-category-image-id" value="' . esc_attr($image_id) . '"><div id="shops-category-image-wrapper">';
    if ($image_id) {
        echo wp_get_attachment_image($image_id, 'thumbnail');
    }
    echo '</div><button type="button" class="button shops_tax_media_button">Выбрать</button> <button type="button" class="button shops_tax_media_remove">Удалить</button></td></tr>';
}

function promokodiki_save_term_image($term_id)
{
    if (!current_user_can('manage_categories')) {
        return;
    }
    $image_id = isset($_POST['shops-category-image-id']) ? absint($_POST['shops-category-image-id']) : 0;
    $image_id ? update_term_meta($term_id, 'shops-category-image-id', $image_id) : delete_term_meta($term_id, 'shops-category-image-id');
}

foreach (array('shops_category', 'promocode_category') as $taxonomy) {
    add_action($taxonomy . '_add_form_fields', 'promokodiki_term_image_add_field');
    add_action($taxonomy . '_edit_form_fields', 'promokodiki_term_image_edit_field');
    add_action('created_' . $taxonomy, 'promokodiki_save_term_image');
    add_action('edited_' . $taxonomy, 'promokodiki_save_term_image');
}

function promokodiki_term_media_assets($hook)
{
    if (!in_array($hook, array('edit-tags.php', 'term.php'), true) || !in_array($_GET['taxonomy'] ?? '', array('shops_category', 'promocode_category'), true)) {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_script('shops-category-media-js', get_template_directory_uri() . '/js/shops-category-media.js', array('jquery'), _S_VERSION, true);
}
add_action('admin_enqueue_scripts', 'promokodiki_term_media_assets');

