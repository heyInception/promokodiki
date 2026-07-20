<?php

/**
 * promokodiki functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package promokodiki
 */

if (! defined('_S_VERSION')) {
	// Replace the version number of the theme on each release.
	define('_S_VERSION', '1.0.0');
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function promokodiki_setup()
{
	/*
		* Make theme available for translation.
		* Translations can be filed in the /languages/ directory.
		* If you're building a theme based on promokodiki, use a find and replace
		* to change 'promokodiki' to the name of your theme in all the template files.
		*/
	load_theme_textdomain('promokodiki', get_template_directory() . '/languages');

	// Add default posts and comments RSS feed links to head.
	add_theme_support('automatic-feed-links');

	/*
		* Let WordPress manage the document title.
		* By adding theme support, we declare that this theme does not use a
		* hard-coded <title> tag in the document head, and expect WordPress to
		* provide it for us.
		*/
	add_theme_support('title-tag');

	/*
		* Enable support for Post Thumbnails on posts and pages.
		*
		* @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
		*/
	add_theme_support('post-thumbnails');

	// This theme uses wp_nav_menu() in one location.
	register_nav_menus(
		array(
			'menu-1' => esc_html__('Primary', 'promokodiki'),
		)
	);

	/*
		* Switch default core markup for search form, comment form, and comments
		* to output valid HTML5.
		*/
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);

	// Set up the WordPress core custom background feature.
	add_theme_support(
		'custom-background',
		apply_filters(
			'promokodiki_custom_background_args',
			array(
				'default-color' => 'ffffff',
				'default-image' => '',
			)
		)
	);

	// Add theme support for selective refresh for widgets.
	add_theme_support('customize-selective-refresh-widgets');

	/**
	 * Add support for core custom logo.
	 *
	 * @link https://codex.wordpress.org/Theme_Logo
	 */
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 250,
			'width'       => 250,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);
}
add_action('after_setup_theme', 'promokodiki_setup');

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function promokodiki_content_width()
{
	$GLOBALS['content_width'] = apply_filters('promokodiki_content_width', 640);
}
add_action('after_setup_theme', 'promokodiki_content_width', 0);

/**
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
function promokodiki_widgets_init()
{
	register_sidebar(
		array(
			'name'          => esc_html__('Sidebar', 'promokodiki'),
			'id'            => 'sidebar-1',
			'description'   => esc_html__('Add widgets here.', 'promokodiki'),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action('widgets_init', 'promokodiki_widgets_init');

/**
 * Enqueue scripts and styles.
 */
function promokodiki_scripts()
{
	wp_enqueue_style('promokodiki-main-vendor', get_stylesheet_directory_uri() . '/assets/css/vendor.css', true, '1.0', 'all');
	wp_enqueue_style('promokodiki-main-style', get_stylesheet_directory_uri() . '/assets/css/main.css', true, '1.0', 'all');
	wp_enqueue_style('promokodiki-style', get_stylesheet_uri(), array(), _S_VERSION);
	$overrides_path = get_stylesheet_directory() . '/assets/css/overrides.css';
	if (file_exists($overrides_path)) {
		wp_enqueue_style('promokodiki-overrides', get_stylesheet_directory_uri() . '/assets/css/overrides.css', array('promokodiki-style'), filemtime($overrides_path));
	}
	wp_style_add_data('promokodiki-style', 'rtl', 'replace');
	wp_enqueue_script('jquery');
	wp_enqueue_script('promokodiki-main', get_template_directory_uri() . '/js/main.js', array(), _S_VERSION, true);
	wp_enqueue_script('promokodiki-custom', get_template_directory_uri() . '/js/customizer.js', array(), _S_VERSION, true);

	if (is_singular() && comments_open() && get_option('thread_comments')) {
		wp_enqueue_script('comment-reply');
	}
}
add_action('wp_enqueue_scripts', 'promokodiki_scripts');

/**
 * Implement the Custom Header feature.
 */
require get_template_directory() . '/inc/custom-header.php';

/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Functions which enhance the theme by hooking into WordPress.
 */
require get_template_directory() . '/inc/template-functions.php';

/**
 * Customizer additions.
 */
require get_template_directory() . '/inc/customizer.php';


require get_template_directory() . '/inc/admin-menu.php';

require get_template_directory() . '/inc/bread.php';
require get_template_directory() . '/inc/layout.php';
require get_template_directory() . '/inc/top.php';
require get_template_directory() . '/inc/ajax-search.php';

/**
 * Load Jetpack compatibility file.
 */
if (defined('JETPACK__VERSION')) {
	require get_template_directory() . '/inc/jetpack.php';
}

function remove_admin_bar_bump()
{
	remove_action('wp_head', '_admin_bar_bump_cb');
}
add_action('get_header', 'remove_admin_bar_bump');

add_filter('upload_mimes', 'svg_upload_allow');

# Добавляет SVG в список разрешенных для загрузки файлов.
function svg_upload_allow($mimes)
{
	$mimes['svg']  = 'image/svg+xml';

	return $mimes;
}
// Обработчик AJAX для увеличения счетчика использований
// Обработчик AJAX для увеличения счетчика использований
function increment_promocode_used_count()
{
	if (!isset($_POST['post_id'])) {
		wp_send_json_error('Не указан ID промокода');
	}

	$post_id = intval($_POST['post_id']);
	$current_count = get_post_meta($post_id, '_promocode_used_count', true) ?: 0;
	$new_count = $current_count + 1;

	update_post_meta($post_id, '_promocode_used_count', $new_count);

	wp_send_json_success(array(
		'new_count' => $new_count
	));
}
add_action('wp_ajax_increment_promocode_count', 'increment_promocode_used_count');
add_action('wp_ajax_nopriv_increment_promocode_count', 'increment_promocode_used_count');

// Handle promocode feedback (like/dislike)
function handle_promocode_feedback()
{
	// Verify nonce first
	if (!wp_verify_nonce($_POST['security'], 'promocode_feedback_nonce')) {
		wp_send_json_error('Invalid nonce');
	}

	if (!isset($_POST['post_id'], $_POST['feedback_action'])) {
		wp_send_json_error('Missing required parameters');
	}

	$post_id = intval($_POST['post_id']);
	$action = sanitize_text_field($_POST['feedback_action']);

	// Get current counts
	$likes = get_post_meta($post_id, '_promocode_likes', true) ?: 0;
	$dislikes = get_post_meta($post_id, '_promocode_dislikes', true) ?: 0;

	// Update counts based on action
	if ($action === 'like') {
		$likes++;
		update_post_meta($post_id, '_promocode_likes', $likes);
	} elseif ($action === 'dislike') {
		$dislikes++;
		update_post_meta($post_id, '_promocode_dislikes', $dislikes);
	}

	wp_send_json_success(array(
		'count' => $action === 'like' ? $likes : $dislikes,
		'message' => 'Thank you for your feedback!'
	));
}
add_action('wp_ajax_handle_promocode_feedback', 'handle_promocode_feedback');
add_action('wp_ajax_nopriv_handle_promocode_feedback', 'handle_promocode_feedback');

function my_enqueue_scripts()
{
	wp_enqueue_script('promocodes-ajax', get_template_directory_uri() . '/js/promocodes-ajax.js', array('jquery'), null, true);
	wp_localize_script('promocodes-ajax', 'my_ajax', array(
		'url' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('promokodiki_frontend')
	));
}
add_action('wp_enqueue_scripts', 'my_enqueue_scripts');


//like dislike

// Регистрация скриптов
function promocodes_likes_scripts()
{
	wp_enqueue_script(
		'promocodes-likes',
		get_template_directory_uri() . '/js/promocodes-like.js',
		array('jquery'),
		'1.0',
		true
	);

	// In your enqueue function
	wp_localize_script('promocodes-likes', 'promocodes_ajax', array(
		'ajaxurl' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('promocode_feedback_nonce')
	));
}
add_action('wp_enqueue_scripts', 'promocodes_likes_scripts');


function cc_mime_types($mimes)
{
	$mimes['svg'] = 'image/svg+xml';
	return $mimes;
}

add_filter('upload_mimes', 'cc_mime_types');



// Добавляем обработчик AJAX
add_action('wp_ajax_load_more_promocodes', 'load_more_promocodes');
add_action('wp_ajax_nopriv_load_more_promocodes', 'load_more_promocodes');

function load_more_promocodes()
{
	check_ajax_referer('promokodiki_frontend', 'nonce');
	$page = isset($_POST['page']) ? intval($_POST['page']) : 1;
	$category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
	$post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
	$tab      = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : ''; // ⚡ добавили

	$args = array(
		'posts_per_page' => 6,
		'paged' => $page,
		'post_status' => 'publish',
	);

	// Устанавливаем тип поста и таксономию
	if ($post_type === 'shops') {
		$args['post_type'] = 'promocode';
		$taxonomy = 'shops_category';
	} elseif ($post_type === 'promocode') {
		$args['post_type'] = 'promocode';
		$taxonomy = 'promocode_category';
	} else {
		$args['post_type'] = 'promocode';
		$taxonomy = 'promocode_category';
	}

	// Добавляем параметры категории
	if (!empty($category) && !empty($taxonomy)) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => $taxonomy,
				'field' => 'slug',
				'terms' => $category,
			)
		);
	}

	// ⚡ Сортировки для разных табов
	if ($tab === 'top') {
		// например сортируем по количеству использований
		$args['meta_key'] = '_promocode_used_count';
		$args['orderby']  = 'meta_value_num';
		$args['order']    = 'DESC';
	} elseif ($tab === 'new') {
		$args['orderby'] = 'date';
		$args['order']   = 'DESC';
	} elseif ($tab === 'discussed') {
		// сортируем по лайкам
		$args['meta_key'] = '_promocode_likes';
		$args['orderby']  = 'meta_value_num';
		$args['order']    = 'DESC';
	}

	$query = new WP_Query($args);

	if ($query->have_posts()) {
		while ($query->have_posts()) {
			$query->the_post();
			get_template_part('template-parts/promocode-card');
		}
		wp_reset_postdata();
	} else {
		echo '<p class="no-promocodes">Больше промокодов нет.</p>';
	}

	wp_die();
}


// Добавляем переменную ajaxurl для фронтенда
add_action('wp_head', 'add_ajaxurl');
function add_ajaxurl()
{
	echo '<script type="text/javascript">
        var ajaxurl = "' . esc_url(admin_url('admin-ajax.php')) . '";
        var promokodikiAjaxNonce = "' . esc_js(wp_create_nonce('promokodiki_frontend')) . '";
    </script>';
}




// Включение комментариев для промокодов
function enable_comments_for_promocode($post_types)
{
	$post_types[] = 'promocode';
	return $post_types;
}
add_filter('wpdiscuz_post_types', 'enable_comments_for_promocode');

// Принудительный вывод комментариев
function force_promocode_comments()
{
	if (is_singular('promocode') && function_exists('wpDiscuz')) {
		echo '<div class="promocode-comments-section">';
		wpDiscuz();
		echo '</div>';
	}
}
add_action('wp_footer', 'force_promocode_comments', 100);


// Шорткод: [admitad_coupons_tree website_id="22" limit="5"]
// Legacy live-API shortcode intentionally disabled; imports are handled by the plugin.

function admitad_coupons_tree_shortcode($atts)
{
	$atts = shortcode_atts([
		'website_id' => 22,
		'limit'      => 5,
	], $atts);

	$access_token = '';

	// 1. Загружаем все категории (одноразово)
	$categories_url = "https://api.admitad.com/categories/?limit=500&language=ru";
	$args = [
		'headers' => [
			'Authorization' => 'Bearer ' . $access_token,
		],
	];
	$response = wp_remote_get($categories_url, $args);
	if (is_wp_error($response)) return "Ошибка при загрузке категорий.";
	$cats_body = json_decode(wp_remote_retrieve_body($response), true);
	if (empty($cats_body['results'])) return '';

	// Строим карту категорий: id => категория
	$categories_map = [];
	foreach ($cats_body['results'] as $cat) {
		$categories_map[$cat['id']] = $cat;
	}

	// Функция получения полного пути категории (родитель → подкатегория)
	function get_category_path($cat_id, $categories_map)
	{
		$cat = $categories_map[$cat_id] ?? null;
		if (!$cat) return '';
		if (!empty($cat['parent']['id'])) {
			return get_category_path($cat['parent']['id'], $categories_map) . ' → ' . $cat['name'];
		}
		return $cat['name'];
	}

	// 2. Загружаем купоны
	$coupons_url = "https://api.admitad.com/coupons/website/{$atts['website_id']}/?limit={$atts['limit']}";
	$response = wp_remote_get($coupons_url, $args);
	if (is_wp_error($response)) return "Ошибка при загрузке купонов.";
	$body = json_decode(wp_remote_retrieve_body($response), true);
	if (empty($body['results'])) return "Купонов не найдено.";

	// 3. Вывод
	ob_start();
	echo '<div class="admitad-coupons">';

	foreach ($body['results'] as $coupon) {
		echo '<div class="coupon">';
		echo '<h3>' . esc_html($coupon['name']) . '</h3>';
		echo '<p>' . esc_html($coupon['description']) . '</p>';

		// Категории купона
		if (!empty($coupon['categories'])) {
			echo '<p><strong>Категории:</strong></p><ul>';
			foreach ($coupon['categories'] as $c) {
				echo '<li>' . esc_html(get_category_path($c['id'], $categories_map)) . '</li>';
			}
			echo '</ul>';
		}

		// Ссылка на купон
		if (!empty($coupon['goto_link'])) {
			echo '<a href="' . esc_url($coupon['goto_link']) . '" target="_blank">Перейти</a>';
		}

		echo '</div>';
	}

	echo '</div>';
	return ob_get_clean();
}


add_filter('postmeta_form_limit', 'meta_limit_increase');
function meta_limit_increase($limit)
{
	return 100; // Увеличить лимит до 100
}

add_filter('asp_results', 'asp_filter_expired_promocodes', 10, 2);
function asp_filter_expired_promocodes($results, $search) {
    $current_date = current_time('timestamp');
    $filtered = array();
    
    foreach ($results as $post) {
        $expiry = get_post_meta($post->id, '_promocode_expiry_date', true);
        if (empty($expiry)) {
            $filtered[] = $post; // Показываем без даты
            continue;
        }
        
        $expiry_timestamp = strtotime($expiry);
        if ($expiry_timestamp > $current_date) {
            $filtered[] = $post; // Показываем актуальные
        }
    }
    
    return $filtered;
}


// В functions.php добавьте:
add_action('wp_ajax_get_next_update_time', 'get_next_update_time_ajax');
add_action('wp_ajax_nopriv_get_next_update_time', 'get_next_update_time_ajax');

function get_next_update_time_ajax() {
    $next_update = get_next_update_time();
    wp_send_json_success(array(
        'next_update' => $next_update
    ));
}

// Получение времени сервера (один запрос)
add_action('wp_ajax_get_server_time', 'get_server_time_ajax');
add_action('wp_ajax_nopriv_get_server_time', 'get_server_time_ajax');

function get_server_time_ajax() {
    wp_send_json_success(array(
        'server_time' => current_time('timestamp'),
        'next_update' => get_next_update_time()
    ));
}


function reading_time()
{
	$content = get_post_field('post_content', get_the_ID());
	$word_count = str_word_count(strip_tags($content));
	$readingtime = ceil($word_count / 200); // 200 слов в минуту

	return $readingtime ?: 1;
}
function set_post_views()
{
	if (is_single()) {
		$post_id = get_the_ID();
		$count = get_post_meta($post_id, 'post_views_count', true);

		if ($count == '') {
			$count = 0;
			delete_post_meta($post_id, 'post_views_count');
			add_post_meta($post_id, 'post_views_count', $count);
		} else {
			$count++;
			update_post_meta($post_id, 'post_views_count', $count);
		}
	}
}
add_action('wp_head', 'set_post_views');


// Функция проверки поисковых ботов
function is_search_bot() {
    if (empty($_SERVER['HTTP_USER_AGENT'])) {
        return false;
    }

    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    $bots = array('Googlebot', 'YandexBot', 'YandexAccessibilityBot', 'YandexMobileBot',
                  'Googlebot-Image', 'YandexImages', 'Mail.RU_Bot', 'bingbot', 'Baiduspider',
                  'FacebookExternalHit', 'Twitterbot', 'WhatsApp', 'TelegramBot');

    foreach ($bots as $bot) {
        if (stripos($user_agent, $bot) !== false) {
            return true;
        }
    }

    return false;
}
