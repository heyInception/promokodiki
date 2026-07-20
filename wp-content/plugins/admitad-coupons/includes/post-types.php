<?php
/** Content types owned by the integration plugin. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function admitad_register_content_types() {
	register_post_type(
		'promocode',
		array(
			'labels' => array(
				'name'          => 'Промокоды',
				'singular_name' => 'Промокод',
				'menu_name'     => 'Промокоды',
				'add_new_item'  => 'Добавить промокод',
				'edit_item'     => 'Редактировать промокод',
				'view_item'     => 'Посмотреть промокод',
				'search_items'  => 'Искать промокоды',
			),
			'public'            => true,
			'has_archive'       => true,
			'rewrite'           => array( 'slug' => 'promocodes' ),
			'menu_icon'         => 'dashicons-tickets-alt',
			'menu_position'     => 25,
			'show_in_rest'      => true,
			'show_in_nav_menus' => true,
			'delete_with_user'  => false,
			'supports'          => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'custom-fields', 'revisions', 'comments' ),
		)
	);

	register_taxonomy(
		'promocode_category',
		array( 'promocode' ),
		array(
			'labels'            => array( 'name' => 'Рубрики промокодов', 'singular_name' => 'Рубрика' ),
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'promocode-category' ),
		)
	);

	register_taxonomy(
		'promocode_tag',
		array( 'promocode' ),
		array(
			'labels'            => array( 'name' => 'Метки промокодов', 'singular_name' => 'Метка' ),
			'public'            => true,
			'hierarchical'      => false,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'promocode-tag' ),
		)
	);

	register_taxonomy(
		'promocode_brand',
		array( 'promocode' ),
		array(
			'labels'            => array( 'name' => 'Бренды', 'singular_name' => 'Бренд' ),
			'public'            => true,
			'hierarchical'      => false,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'promocode-brand' ),
		)
	);

	register_taxonomy(
		'shops_category',
		array( 'promocode' ),
		array(
			'labels'            => array(
				'name'          => 'Магазины',
				'singular_name' => 'Магазин',
				'menu_name'     => 'Магазины',
			),
			'public'            => true,
			'hierarchical'      => false,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'shops-category' ),
		)
	);
}

/**
 * Former coupon URLs used the /shops/{coupon}/ shape. They are intentionally
 * retired rather than redirected or exposed as an alias of a promocode.
 */
function admitad_retire_legacy_shop_urls() {
	$path = trim( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
	if ( '' === $path || 'shops' === $path || ! str_starts_with( $path, 'shops/' ) ) {
		return;
	}
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	$template = get_404_template();
	if ( $template ) {
		include $template;
	}
	exit;
}
add_action( 'template_redirect', 'admitad_retire_legacy_shop_urls', 0 );

add_filter(
	'redirect_canonical',
	static function ( $redirect ) {
		$path = trim( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
		return str_starts_with( $path, 'shops/' ) ? false : $redirect;
	},
	0
);
