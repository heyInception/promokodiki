<?php
/** Deterministic browser fixtures for wp-env. */
$discounts = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Скидки', 'post_name' => 'discounts' ) );
update_post_meta( $discounts, '_wp_page_template', 'page-discounts.php' );
$front = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Главная', 'post_name' => 'home' ) );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $front );
$shop = wp_insert_term( 'Тестовый магазин', 'shops_category', array( 'slug' => 'test-shop' ) );
$category = wp_insert_term( 'Тестовая категория', 'promocode_category', array( 'slug' => 'test-category' ) );
for ( $index = 1; $index <= 8; $index++ ) {
	$post_id = wp_insert_post( array( 'post_type' => 'promocode', 'post_status' => 'publish', 'post_title' => 'Тестовый промокод ' . $index, 'post_excerpt' => 'Тестовое предложение' ) );
	wp_set_object_terms( $post_id, array( (int) $shop['term_id'] ), 'shops_category' );
	wp_set_object_terms( $post_id, array( (int) $category['term_id'] ), 'promocode_category' );
	update_post_meta( $post_id, '_promocode_code', 'TEST' . $index );
	update_post_meta( $post_id, '_promocode_link', 'https://example.test/' );
	update_post_meta( $post_id, '_promocode_is_active', 'yes' );
}
$menu_id = wp_create_nav_menu( 'Primary smoke menu' );
wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'Тестовый магазин', 'menu-item-url' => get_term_link( (int) $shop['term_id'], 'shops_category' ), 'menu-item-status' => 'publish' ) );
$locations = get_theme_mod( 'nav_menu_locations', array() );
$locations['menu-1'] = $menu_id;
set_theme_mod( 'nav_menu_locations', $locations );
update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();
