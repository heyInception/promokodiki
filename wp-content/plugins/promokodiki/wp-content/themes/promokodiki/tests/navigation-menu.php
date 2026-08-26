<?php
/**
 * Primary navigation rendering contract.
 *
 * Run: php tests/navigation-menu.php
 */

define( 'PROMOKODIKI_TESTING', true );

$navigation_module = dirname( __DIR__ ) . '/inc/navigation-menu.php';

if ( ! file_exists( $navigation_module ) ) {
	fwrite( STDERR, "FAIL: Navigation menu module does not exist.\n" );
	exit( 1 );
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback ) {}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = null ) {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = null ) {
		return esc_attr( $text );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'get_template_directory_uri' ) ) {
	function get_template_directory_uri() {
		return '/theme';
	}
}

if ( ! function_exists( 'get_template_directory' ) ) {
	function get_template_directory() {
		return dirname( __DIR__ );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		return strtolower( trim( (string) $title ) );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return '/site' . $path;
	}
}

if ( ! function_exists( 'get_post_type_archive_link' ) ) {
	function get_post_type_archive_link( $post_type ) {
		return 'promocode' === $post_type ? '/site/promocodes/' : '';
	}
}

$navigation_get_terms_args = array();
$navigation_theme_mods     = array();
$navigation_wp_nav_args    = array();

if ( ! function_exists( 'wp_nav_menu' ) ) {
	function wp_nav_menu( $args ) {
		$GLOBALS['navigation_wp_nav_args'] = $args;
		return false;
	}
}

if ( ! function_exists( 'get_theme_mod' ) ) {
	function get_theme_mod( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['navigation_theme_mods'] ) ? $GLOBALS['navigation_theme_mods'][ $name ] : $default;
	}
}

if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( $args ) {
		$GLOBALS['navigation_get_terms_args'] = $args;

		return array(
			(object) array( 'term_id' => 10, 'name' => 'Clothing', 'slug' => 'clothing' ),
			(object) array( 'term_id' => 20, 'name' => 'Travel', 'slug' => 'travel' ),
		);
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ) {
		return false;
	}
}

if ( ! function_exists( 'get_term_link' ) ) {
	function get_term_link( $term ) {
		return '/promocode-category/' . $term->slug . '/';
	}
}

require_once $navigation_module;

function navigation_assert_true( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function navigation_assert_contains( $needle, $haystack, $message ) {
	navigation_assert_true( false !== strpos( $haystack, $needle ), $message );
}

function navigation_assert_before( $first, $second, $haystack, $message ) {
	$first_position  = strpos( $haystack, $first );
	$second_position = strpos( $haystack, $second );
	navigation_assert_true(
		false !== $first_position && false !== $second_position && $first_position < $second_position,
		$message
	);
}

$categories = promokodiki_get_menu_categories();

navigation_assert_true( 0 === $navigation_get_terms_args['parent'], 'Only root categories are queried' );
navigation_assert_true( true === $navigation_get_terms_args['hide_empty'], 'Empty categories are excluded' );
navigation_assert_true( true === $navigation_get_terms_args['pad_counts'], 'Descendant promocodes keep their root category visible' );
navigation_assert_true( 'name' === $navigation_get_terms_args['orderby'], 'Categories request alphabetical ordering' );
navigation_assert_true( 'Clothing' === $categories[0]['name'], 'WordPress category ordering is preserved' );
navigation_assert_true( '/theme/img/categories/clothing.png' === $categories[0]['image_url'], 'Existing slug image is used' );
navigation_assert_true( '/theme/img/categories/default.png' === $categories[1]['image_url'], 'Missing slug image uses the approved local fallback' );

$submenu = promokodiki_render_promocode_submenu( $categories, true );

navigation_assert_contains( 'class="nav__submenu-toggle', $submenu, 'A separate submenu toggle is rendered' );
navigation_assert_contains( 'aria-expanded="true"', $submenu, 'Expanded state is exposed to assistive technology' );
navigation_assert_contains( 'aria-controls="promocode-category-menu"', $submenu, 'Toggle controls the category list' );
navigation_assert_contains( 'href="/promocode-category/clothing/"', $submenu, 'Category links use their term URLs' );
navigation_assert_contains( 'alt="Clothing"', $submenu, 'Category image has a useful alternative' );
navigation_assert_before( 'Clothing', 'Travel', $submenu, 'Rendered categories remain alphabetical' );

navigation_assert_true( function_exists( 'promokodiki_render_default_primary_menu' ), 'A deterministic menu fallback exists' );
$fallback_menu = promokodiki_render_default_primary_menu( $categories, true );
navigation_assert_before( 'Магазины', 'Промокоды', $fallback_menu, 'Fallback menu follows the approved top-level order' );
navigation_assert_contains( 'href="/site/promocodes/"', $fallback_menu, 'Fallback Promocodes label links to the archive' );
navigation_assert_contains( 'menu-promocodes.svg', $fallback_menu, 'Fallback uses the replaceable Promocodes icon' );
navigation_assert_contains( 'id="promocode-category-menu"', $fallback_menu, 'Fallback Promocodes item contains the categories' );
navigation_assert_contains( 'menu-item--promocodes menu-item-has-children', $fallback_menu, 'Promocodes uses the native WordPress parent class when a submenu exists' );
navigation_assert_contains( 'mobile-categories-default-open', $fallback_menu, 'Expanded server state is visible before JavaScript runs' );
$collapsed_fallback_menu = promokodiki_render_default_primary_menu( $categories, false );
navigation_assert_true( false === strpos( $collapsed_fallback_menu, 'mobile-categories-default-open' ), 'Collapsed server state does not render the open class' );

$primary_navigation = promokodiki_render_primary_navigation( array( 'categories' => $categories ) );
navigation_assert_true( false === $navigation_wp_nav_args['container'], 'WordPress does not wrap the menu list in an extra container' );
navigation_assert_contains( 'class="nav__panel"', $primary_navigation, 'Primary navigation renders the responsive panel' );

$favorite = promokodiki_render_mobile_favorite_action();

navigation_assert_contains( 'data-mobile-favorite', $favorite, 'Mobile favorite action exposes a JavaScript hook' );
navigation_assert_contains( 'data-ios-help=', $favorite, 'iOS help is localized in server-rendered data' );
navigation_assert_contains( 'data-android-help=', $favorite, 'Android help is localized in server-rendered data' );
navigation_assert_contains( 'Добавить наш сайт в избранное', $favorite, 'Mobile favorite action uses the approved label' );
navigation_assert_contains( 'hidden', $favorite, 'Favorite instructions start hidden' );

navigation_assert_true( function_exists( 'promokodiki_mobile_categories_expanded' ), 'Mobile category setting helper exists' );
navigation_assert_true( promokodiki_mobile_categories_expanded(), 'Mobile categories are expanded by default' );
$navigation_theme_mods['promokodiki_mobile_categories_expanded'] = false;
navigation_assert_true( ! promokodiki_mobile_categories_expanded(), 'Unchecked setting starts mobile categories collapsed' );

echo "Navigation menu rendering contract passed.\n";
