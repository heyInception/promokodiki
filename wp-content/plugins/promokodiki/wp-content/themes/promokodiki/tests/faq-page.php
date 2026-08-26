<?php
/**
 * FAQ page rendering contract.
 *
 * Run: php tests/faq-page.php
 */

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback ) {}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = null ) {
		echo $text;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return $url;
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $content ) {
		return $content;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return $path;
	}
}

require_once dirname( __DIR__ ) . '/inc/faq-page.php';

function faq_page_assert_contains( $needle, $haystack, $message ) {
	if ( false === strpos( $haystack, $needle ) ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$html = promokodiki_render_faq_page(
	array(
		array(
			'title' => 'Промокоды',
			'items' => array(
				array(
					'question' => 'Как применить промокод?',
					'answer'   => '<p>Введите код в корзине.</p>',
				),
			),
		),
	),
	array(
		'title'        => 'Часто задаваемые вопросы',
		'introduction' => 'Поможем сэкономить на покупках.',
		'cta_title'    => 'Не нашли ответ?',
		'cta_text'     => 'Напишите нам, и мы поможем.',
		'cta_url'      => '/contacts/',
		'cta_label'    => 'Связаться с нами',
		'sidebar_title' => 'Содержание',
		'sidebar_description' => 'Выберите раздел, чтобы быстро перейти к ответам.',
	)
);

faq_page_assert_contains( 'itemtype="https://schema.org/FAQPage"', $html, 'FAQPage schema is rendered' );
faq_page_assert_contains( '<h1>Часто задаваемые вопросы</h1>', $html, 'Page title is rendered' );
faq_page_assert_contains( '<h2>Промокоды</h2>', $html, 'Section title is rendered' );
faq_page_assert_contains( 'id="faq-section-1"', $html, 'Section has an anchor target' );
faq_page_assert_contains( 'class="faq-page__sidebar"', $html, 'Sticky sidebar is rendered' );
faq_page_assert_contains( 'Выберите раздел, чтобы быстро перейти к ответам.', $html, 'Sidebar description is rendered' );
faq_page_assert_contains( 'href="#faq-section-1"', $html, 'Sidebar links to its section anchor' );
faq_page_assert_contains( 'aria-expanded="false"', $html, 'Accordion starts collapsed' );
faq_page_assert_contains( 'aria-controls="faq-answer-1"', $html, 'Accordion control is associated with its answer' );
faq_page_assert_contains( '<p>Введите код в корзине.</p>', $html, 'WYSIWYG answer is rendered' );
faq_page_assert_contains( 'href="/contacts/"', $html, 'Contact CTA is rendered' );

echo "FAQ page rendering contract passed.\n";
