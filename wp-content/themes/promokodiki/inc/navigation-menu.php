<?php
/**
 * Primary navigation helpers.
 *
 * @package promokodiki
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'PROMOKODIKI_TESTING' ) ) {
	exit;
}

/**
 * Return the root promocode categories used by the primary menu.
 *
 * @return array<int, array{id:int,name:string,url:string,image_url:string}>
 */
function promokodiki_get_menu_categories() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'promocode_category',
			'parent'     => 0,
			'hide_empty' => true,
			'pad_counts' => true,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return array();
	}

	$categories = array();
	$fallback   = get_template_directory_uri() . '/img/categories/default.png';

	foreach ( $terms as $term ) {
		$term_url = get_term_link( $term );
		if ( is_wp_error( $term_url ) ) {
			continue;
		}

		$image_slug = sanitize_title( $term->slug );
		$image_path = get_template_directory() . '/img/categories/' . $image_slug . '.png';
		$image_url  = file_exists( $image_path )
			? get_template_directory_uri() . '/img/categories/' . $image_slug . '.png'
			: $fallback;

		$categories[] = array(
			'id'        => (int) $term->term_id,
			'name'      => (string) $term->name,
			'url'       => (string) $term_url,
			'image_url' => $image_url,
		);
	}

	return $categories;
}

/**
 * Render the category toggle and its one-level list.
 *
 * @param array<int, array{id:int,name:string,url:string,image_url:string}> $categories Categories.
 * @param bool                                                               $expanded Initial mobile state.
 * @return string
 */
function promokodiki_render_promocode_submenu( $categories, $expanded ) {
	if ( empty( $categories ) ) {
		return '';
	}

	$expanded_value = $expanded ? 'true' : 'false';
	$html           = '<button class="nav__submenu-toggle btn-reset" type="button" aria-expanded="' . esc_attr( $expanded_value ) . '" aria-controls="promocode-category-menu" data-promocode-submenu-toggle>';
	$html          .= '<span class="screen-reader-text">' . esc_html__( 'Показать рубрики промокодов', 'promokodiki' ) . '</span>';
	$html          .= '<span class="nav__submenu-chevron" aria-hidden="true"></span></button>';
	$html          .= '<ul id="promocode-category-menu" class="sub-menu nav__category-list" data-promocode-submenu data-mobile-default-expanded="' . esc_attr( $expanded_value ) . '">';

	foreach ( $categories as $category ) {
		$html .= '<li class="nav__category-item">';
		$html .= '<a href="' . esc_url( $category['url'] ) . '">';
		$html .= '<img class="nav__category-image" src="' . esc_url( $category['image_url'] ) . '" alt="' . esc_attr( $category['name'] ) . '" width="32" height="32" loading="lazy">';
		$html .= '<span>' . esc_html( $category['name'] ) . '</span></a></li>';
	}

	$html .= '</ul>';

	return $html;
}

/**
 * Render a decorative, replaceable top-level menu icon.
 *
 * @param string $filename SVG filename within img/menu.
 * @return string
 */
function promokodiki_render_primary_menu_icon( $filename ) {
	return '<img class="nav__item-icon" src="' . esc_url( get_template_directory_uri() . '/img/menu/' . $filename ) . '" alt="" width="24" height="24" aria-hidden="true">';
}

/**
 * Render the guaranteed Promocodes menu item.
 *
 * @param array $categories Category view models.
 * @param bool  $expanded Initial mobile state.
 * @return string
 */
function promokodiki_render_promocode_menu_item( $categories, $expanded ) {
	$classes = 'menu-item menu-item--promocodes';
	if ( ! empty( $categories ) ) {
		$classes .= ' menu-item-has-children';
	}
	if ( $expanded ) {
		$classes .= ' mobile-categories-default-open';
	}

	$html  = '<li class="' . esc_attr( $classes ) . '">';
	$html .= '<a href="' . esc_url( get_post_type_archive_link( 'promocode' ) ) . '">';
	$html .= promokodiki_render_primary_menu_icon( 'menu-promocodes.svg' );
	$html .= '<span>' . esc_html__( 'Промокоды', 'promokodiki' ) . '</span></a>';
	$html .= promokodiki_render_promocode_submenu( $categories, $expanded );
	$html .= '</li>';

	return $html;
}

/**
 * Render a predictable primary menu when no WordPress menu is assigned.
 *
 * @param array $categories Category view models.
 * @param bool  $expanded Initial mobile state.
 * @return string
 */
function promokodiki_render_default_primary_menu( $categories, $expanded ) {
	$items = array(
		array( 'label' => __( 'Магазины', 'promokodiki' ), 'url' => home_url( '/shops/' ), 'icon' => 'menu-shops.svg' ),
		array( 'label' => __( 'Скидки', 'promokodiki' ), 'url' => home_url( '/discounts/' ), 'icon' => 'menu-discounts.svg' ),
		array( 'label' => __( 'Рейтинг и помощь', 'promokodiki' ), 'url' => home_url( '/faq/' ), 'icon' => 'menu-rating.svg' ),
		array( 'label' => __( 'О нас', 'promokodiki' ), 'url' => home_url( '/about/' ), 'icon' => 'menu-about.svg' ),
	);

	$html = '<ul id="primary-menu" class="menu list-reset nav__list nav-menu">';
	$html .= '<li class="menu-item"><a href="' . esc_url( $items[0]['url'] ) . '">' . promokodiki_render_primary_menu_icon( $items[0]['icon'] ) . '<span>' . esc_html( $items[0]['label'] ) . '</span></a></li>';
	$html .= promokodiki_render_promocode_menu_item( $categories, $expanded );

	foreach ( array_slice( $items, 1 ) as $item ) {
		$html .= '<li class="menu-item"><a href="' . esc_url( $item['url'] ) . '">' . promokodiki_render_primary_menu_icon( $item['icon'] ) . '<span>' . esc_html( $item['label'] ) . '</span></a></li>';
	}

	$html .= '</ul>';

	return $html;
}

/**
 * Render the mobile-only favorite-browser action.
 *
 * @return string
 */
function promokodiki_render_mobile_favorite_action() {
	$ios_help     = __( 'Нажмите «Поделиться» → «Добавить в Избранное».', 'promokodiki' );
	$android_help = __( 'Откройте меню браузера → «Добавить в закладки».', 'promokodiki' );
	$html  = '<div class="nav__favorite">';
	$html .= '<button class="nav__favorite-button btn-reset" type="button" data-mobile-favorite data-ios-help="' . esc_attr( $ios_help ) . '" data-android-help="' . esc_attr( $android_help ) . '">';
	$html .= '<span>' . esc_html__( 'Добавить наш сайт в избранное', 'promokodiki' ) . '</span>';
	$html .= '<span class="nav__favorite-heart" aria-hidden="true">♥</span></button>';
	$html .= '<p class="nav__favorite-help" data-mobile-favorite-help hidden aria-live="polite"></p>';
	$html .= '</div>';

	return $html;
}

/**
 * Whether promocode categories start expanded when the mobile panel opens.
 *
 * @return bool
 */
function promokodiki_mobile_categories_expanded() {
	return (bool) get_theme_mod( 'promokodiki_mobile_categories_expanded', true );
}

/**
 * Resolve the placeholder icon used by a top-level mobile menu item.
 *
 * @param object $item WordPress menu item.
 * @return string
 */
function promokodiki_primary_menu_icon( $item ) {
	$title = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( wp_strip_all_tags( $item->title ) ), 'UTF-8' ) : strtolower( trim( wp_strip_all_tags( $item->title ) ) );
	$icons = array(
		'магазины'         => 'menu-shops.svg',
		'промокоды'        => 'menu-promocodes.svg',
		'скидки'           => 'menu-discounts.svg',
		'рейтинг и помощь' => 'menu-rating.svg',
		'о нас'            => 'menu-about.svg',
	);

	return isset( $icons[ $title ] ) ? get_template_directory_uri() . '/img/menu/' . $icons[ $title ] : '';
}

/**
 * Check whether a menu item is the promocode archive link.
 *
 * @param object $item WordPress menu item.
 * @return bool
 */
function promokodiki_is_promocode_menu_item( $item ) {
	$archive_url = get_post_type_archive_link( 'promocode' );
	$item_url    = isset( $item->url ) ? untrailingslashit( (string) $item->url ) : '';
	$title       = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( wp_strip_all_tags( $item->title ) ), 'UTF-8' ) : strtolower( trim( wp_strip_all_tags( $item->title ) ) );

	return 'промокоды' === $title || ( $archive_url && untrailingslashit( $archive_url ) === $item_url );
}

if ( class_exists( 'Walker_Nav_Menu' ) ) {
	/**
	 * Add mobile icons and the dynamic promocode submenu without replacing menu administration.
	 */
	class Promokodiki_Primary_Menu_Walker extends Walker_Nav_Menu {
		/** @var array */
		private $categories;

		/** @var bool */
		private $has_promocode_item = false;

		/**
		 * @param array $categories Category view models.
		 */
		public function __construct( $categories ) {
			$this->categories = $categories;
		}

		/**
		 * Whether the administered menu already contains Promocodes.
		 *
		 * @return bool
		 */
		public function has_promocode_item() {
			return $this->has_promocode_item;
		}

		/**
		 * Start a menu element.
		 *
		 * @param string $output Used to append additional content.
		 * @param object $data_object Menu item data object.
		 * @param int    $depth Depth of menu item.
		 * @param object $args Menu arguments.
		 * @param int    $current_object_id Current object ID.
		 */
		public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
			$offset = strlen( $output );
			parent::start_el( $output, $data_object, $depth, $args, $current_object_id );

			if ( 0 !== (int) $depth ) {
				return;
			}

			$fragment = substr( $output, $offset );
			$icon     = promokodiki_primary_menu_icon( $data_object );

			if ( $icon ) {
				$icon_html = '<img class="nav__item-icon" src="' . esc_url( $icon ) . '" alt="" width="24" height="24" aria-hidden="true">';
				$fragment  = preg_replace( '/(<a\b[^>]*>)/', '$1' . $icon_html, $fragment, 1 );
			}

			if ( promokodiki_is_promocode_menu_item( $data_object ) ) {
				$this->has_promocode_item = true;
				$extra_class = ' menu-item--promocodes';
				if ( ! empty( $this->categories ) ) {
					$extra_class .= ' menu-item-has-children';
				}
				if ( promokodiki_mobile_categories_expanded() ) {
					$extra_class .= ' mobile-categories-default-open';
				}
				$fragment = preg_replace( '/class="([^"]*)"/', 'class="$1' . $extra_class . '"', $fragment, 1 );
				$fragment .= promokodiki_render_promocode_submenu( $this->categories, promokodiki_mobile_categories_expanded() );
			}

			$output = substr( $output, 0, $offset ) . $fragment;
		}
	}
}

/**
 * Render the complete primary navigation.
 *
 * @param array $args Optional arguments used by tests or templates.
 * @return string
 */
function promokodiki_render_primary_navigation( $args = array() ) {
	$categories = isset( $args['categories'] ) ? $args['categories'] : promokodiki_get_menu_categories();
	$expanded   = promokodiki_mobile_categories_expanded();
	$menu_args  = array(
		'theme_location' => 'menu-1',
		'menu_id'        => 'primary-menu',
		'container'      => false,
		'items_wrap'     => '<ul id="%1$s" class="%2$s list-reset nav__list nav-menu">%3$s</ul>',
		'echo'           => false,
		'fallback_cb'    => false,
	);

	$walker = null;
	if ( class_exists( 'Promokodiki_Primary_Menu_Walker' ) ) {
		$walker              = new Promokodiki_Primary_Menu_Walker( $categories );
		$menu_args['walker'] = $walker;
	}

	$menu = wp_nav_menu( $menu_args );
	if ( ! $menu ) {
		$menu = promokodiki_render_default_primary_menu( $categories, $expanded );
	} elseif ( $walker && ! $walker->has_promocode_item() ) {
		$menu = preg_replace( '/<\/ul>\s*$/', promokodiki_render_promocode_menu_item( $categories, $expanded ) . '</ul>', $menu, 1 );
	}
	$html = '<nav id="site-navigation" class="nav main-navigation" aria-label="' . esc_attr__( 'Основная навигация', 'promokodiki' ) . '">';
	$html .= '<button class="menu-toggle btn-reset" type="button" aria-controls="primary-menu-panel" aria-expanded="false" aria-label="' . esc_attr__( 'Основное меню', 'promokodiki' ) . '"><span class="menu-toggle__icon" aria-hidden="true"></span></button>';
	$html .= '<div id="primary-menu-panel" class="nav__panel">' . $menu . promokodiki_render_mobile_favorite_action() . '</div></nav>';

	return $html;
}
