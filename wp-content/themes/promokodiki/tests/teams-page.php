<?php
/**
 * Teams page rendering contract.
 *
 * Run: php tests/teams-page.php
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

require_once dirname( __DIR__ ) . '/inc/teams-page.php';

function teams_page_assert_contains( $needle, $haystack, $message ) {
	if ( false === strpos( $haystack, $needle ) ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function teams_page_assert_not_contains( $needle, $haystack, $message ) {
	if ( false !== strpos( $haystack, $needle ) ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$html = promokodiki_render_teams_page(
	array(
		array(
			'title'       => 'Редакция',
			'description' => '<p>Ищем лучшие предложения.</p>',
			'members'     => array(
				array(
					'name'  => 'Анна',
					'photo' => array( 'url' => '/anna.jpg' ),
					'url'   => '/authors/anna/',
				),
				array(
					'name'  => 'Иван',
					'photo' => array( 'url' => '/ivan.jpg' ),
					'url'   => '',
				),
				array(
					'name'  => '',
					'photo' => array( 'url' => '/invalid.jpg' ),
				),
			),
		),
		array(
			'title'   => 'Пустая секция',
			'members' => array(),
		),
	),
	array(
		'title'        => 'Наша команда',
		'introduction' => '<p>Знакомьтесь.</p>',
	)
);

teams_page_assert_contains( '<h1>Наша команда</h1>', $html, 'Page title is rendered' );
teams_page_assert_contains( '<p>Знакомьтесь.</p>', $html, 'Introduction is rendered' );
teams_page_assert_contains( '<h2>Редакция</h2>', $html, 'Section title is rendered' );
teams_page_assert_contains( 'href="/authors/anna/"', $html, 'Linked avatar URL is rendered' );
teams_page_assert_contains( 'aria-label="Анна"', $html, 'Linked avatar has accessible name' );
teams_page_assert_contains( 'src="/ivan.jpg"', $html, 'Unlinked avatar image is rendered' );
teams_page_assert_contains( 'alt="Иван"', $html, 'Member name is used as image alt text' );
teams_page_assert_not_contains( 'Пустая секция', $html, 'Empty section is omitted' );
teams_page_assert_not_contains( 'invalid.jpg', $html, 'Incomplete member is omitted' );

echo "Teams page rendering contract passed.\n";
