<?php
/**
 * About page rendering contract.
 *
 * Run: php tests/about-page.php
 */

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback ) {}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) { return $text; }
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) { return $url; }
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $content ) { return $content; }
}

require_once dirname( __DIR__ ) . '/inc/about-page.php';

function about_page_assert_contains( $needle, $haystack, $message ) {
	if ( false === strpos( $haystack, $needle ) ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function about_page_assert_not_contains( $needle, $haystack, $message ) {
	if ( false !== strpos( $haystack, $needle ) ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$html = promokodiki_render_about_page(
	array(
		'title'       => 'О нас',
		'introduction' => '<p>Помогаем покупать выгоднее.</p>',
		'hero_image'  => array( 'url' => '/about-hero.jpg', 'alt' => 'Покупки со скидкой' ),
		'stats'       => array(
			array( 'value' => '10 000+', 'label' => 'промокодов' ),
		),
		'how_title'   => 'Как это работает',
		'how_steps'   => array(
			array( 'title' => 'Выберите магазин', 'description' => 'Найдите нужное предложение.' ),
		),
		'values_title' => 'Почему Promokodiki',
		'values'      => array(
			array( 'title' => 'Проверяем предложения', 'description' => 'Обновляем информацию.' ),
		),
		'cta_title'   => 'Экономьте с нами',
		'cta_text'    => 'Выберите магазин и найдите скидку.',
		'cta_label'   => 'Все магазины',
		'cta_url'     => '/shops/',
	)
);

about_page_assert_contains( '<h1>О нас</h1>', $html, 'Page title is rendered' );
about_page_assert_contains( 'src="/about-hero.jpg"', $html, 'Hero image is rendered' );
about_page_assert_contains( 'class="about-page__stat-value">10 000+</span>', $html, 'Statistic is rendered' );
about_page_assert_contains( '<h2>Как это работает</h2>', $html, 'How it works section is rendered' );
about_page_assert_contains( '<h3>Выберите магазин</h3>', $html, 'How it works card is rendered' );
about_page_assert_contains( '<h2>Почему Promokodiki</h2>', $html, 'Values section is rendered' );
about_page_assert_contains( 'href="/shops/"', $html, 'CTA URL is rendered' );
about_page_assert_contains( 'class="about-page__cta-link"', $html, 'CTA link is rendered' );
about_page_assert_contains( 'Все магазины', $html, 'CTA label is rendered' );
about_page_assert_not_contains( 'about-page__stats"></div>', $html, 'Empty statistics container is omitted' );

echo "About page rendering contract passed.\n";
