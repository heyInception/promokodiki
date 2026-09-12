<?php
/**
 * Contract test for the fixed category cards in the home-page banner.
 */

$banner_have_rows_calls = 0;
$banner_get_terms_args  = array();

function have_rows( $selector, $post_id = null ) {
	global $banner_have_rows_calls;

	++$banner_have_rows_calls;

	return $banner_have_rows_calls <= 2;
}

function the_row() {}

function the_sub_field( $field_name ) {
	if ( 'zagolovok' === $field_name ) {
		echo 'Banner';
	}
}

function get_sub_field( $field_name ) {
	return false;
}

function get_terms( $args ) {
	global $banner_get_terms_args;

	$banner_get_terms_args = $args;

	return array(
		(object) array(
			'term_id' => 30,
			'slug'    => 'moda',
			'name'    => 'Мода',
		),
		(object) array(
			'term_id' => 10,
			'slug'    => 'zdorove-i-krasota',
			'name'    => 'Здоровье и красота',
		),
		(object) array(
			'term_id' => 40,
			'slug'    => 'produkty-pitaniya-i-bytovaya-himiya',
			'name'    => 'Продукты питания и Бытовая химия',
		),
	);
}

function is_wp_error( $value ) {
	return false;
}

function get_template_directory_uri() {
	return '/theme';
}

function get_term_link( $term ) {
	return '/promocode-category/' . $term->slug . '/';
}

function get_post_type_archive_link( $post_type ) {
	return 'promocode' === $post_type ? '/promocodes/' : '';
}

function esc_url( $value ) {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $value ) {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

function esc_html( $value ) {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

function banner_categories_assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		fwrite(
			STDERR,
			$message . PHP_EOL . 'Expected: ' . var_export( $expected, true ) . PHP_EOL . 'Actual: ' . var_export( $actual, true ) . PHP_EOL
		);
		exit( 1 );
	}
}

ob_start();
require dirname( __DIR__ ) . '/template-parts/partials/banner.php';
$banner_html = ob_get_clean();

banner_categories_assert_same(
	array(
		'zdorove-i-krasota',
		'elektronika',
		'moda',
		'produkty-pitaniya-i-bytovaya-himiya',
	),
	$banner_get_terms_args['slug'] ?? null,
	'The banner queries exactly the approved category slugs.'
);
banner_categories_assert_same( false, $banner_get_terms_args['hide_empty'] ?? null, 'Empty approved categories remain visible.' );
banner_categories_assert_same(
	1,
	substr_count( $banner_html, 'href="/promocodes/" class="banner__button btn-reset ui-button ui-button--pink banner__button_m"' ),
	'The mobile catalogue CTA uses the promocode archive URL.'
);

preg_match_all(
	'/<a href="([^"]+)"\s+class="([^"]+)"\s+style="([^"]+)">\s*([^<]+?)\s*<\/a>/',
	$banner_html,
	$banner_matches,
	PREG_SET_ORDER
);

$rendered_cards = array_map(
	static function ( $match ) {
		return array( $match[1], $match[2], $match[3], trim( $match[4] ) );
	},
	$banner_matches
);

banner_categories_assert_same(
	array(
		array(
			'/promocode-category/zdorove-i-krasota/',
			'banner__item banner__item_pink',
			'background-image: url(/theme/img/banner-1.png)',
			'Здоровье и красота',
		),
		array(
			'/promocode-category/moda/',
			'banner__item banner__item_orange',
			'background-image: url(/theme/img/banner-3.png)',
			'Мода',
		),
		array(
			'/promocode-category/produkty-pitaniya-i-bytovaya-himiya/',
			'banner__item banner__item_yellow',
			'background-image: url(/theme/img/banner-4.png)',
			'Продукты питания и Бытовая химия',
		),
	),
	$rendered_cards,
	'The banner preserves approved order and category-specific styling when a category is missing.'
);

echo "Banner category contract passed.\n";
