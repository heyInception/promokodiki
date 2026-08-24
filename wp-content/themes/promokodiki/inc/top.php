<?php
/**
 * Build one server-owned Telegram-style snapshot per three-hour window.
 *
 * @param int|null $now   WordPress timestamp, primarily injectable for tests.
 * @param bool     $force Rebuild the current window without accepting client input.
 * @return array{ids: int[], next_update: int}
 */
function promokodiki_top_snapshot(?int $now = null, bool $force = false): array
{
    $now = $now ?? current_time('timestamp');
    $window_seconds = 3 * HOUR_IN_SECONDS;
    $window_start = intdiv($now, $window_seconds) * $window_seconds;
    $next_update = $window_start + $window_seconds;
    $cache = get_option('promokodiki_top_snapshot_v2', array());

    if (! $force && isset($cache['window'], $cache['ids']) && (int) $cache['window'] === $window_start) {
        return array('ids' => array_map('absint', (array) $cache['ids']), 'next_update' => $next_update);
    }

    $count = max(1, min(20, (int) get_option('popular_promocodes_count', 4)));
    $query = new WP_Query(array(
        'post_type' => 'promocode',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_query' => array(
            'relation' => 'AND',
            array('key' => '_promocode_is_active', 'value' => 'yes', 'compare' => '='),
            array(
                'relation' => 'OR',
                array('key' => '_promocode_expiry_date', 'compare' => 'NOT EXISTS'),
                array('key' => '_promocode_expiry_date', 'value' => '', 'compare' => '='),
                array('key' => '_promocode_expiry_date', 'value' => wp_date('Y-m-d', $now), 'compare' => '>=', 'type' => 'DATE'),
            ),
        ),
    ));

    global $wpdb;
    $click_table = $wpdb->prefix . 'promokodiki_click_stats';
    $click_table_exists = $click_table === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $click_table));
    $ranked = array();
    foreach ($query->posts as $post_id) {
        $post_id = (int) $post_id;
        $clicks = $click_table_exists ? (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(clicks),0) FROM {$click_table} WHERE promocode_id=%d AND click_date >= %s",
            $post_id,
            wp_date('Y-m-d', $now - (7 * DAY_IN_SECONDS))
        )) : 0;
        $likes = max(0, (int) get_post_meta($post_id, '_promocode_likes', true));
        $dislikes = max(0, (int) get_post_meta($post_id, '_promocode_dislikes', true));
        $age_hours = max(0, (int) floor(($now - (int) get_post_time('U', true, $post_id)) / HOUR_IN_SECONDS));
        $fresh = $age_hours <= (7 * 24);
        $code = (string) get_post_meta($post_id, '_promocode_code', true);
        $has_code = '' !== trim($code) && false === strpos($code, 'НЕ НУЖЕН');
        $score = ($has_code ? 1000 : 0) + ($clicks * 100) + (($likes + $dislikes) * 10) + max(0, 168 - $age_hours);
        $jitter = (int) (hexdec(substr(hash('sha256', $window_start . ':' . $post_id), 0, 4)) % 101);
        $ranked[] = compact('post_id', 'score', 'jitter', 'fresh', 'has_code');
    }

    usort($ranked, static function (array $left, array $right): int {
        $left_weight = $left['score'] + $left['jitter'];
        $right_weight = $right['score'] + $right['jitter'];
        return $right_weight <=> $left_weight ?: $right['post_id'] <=> $left['post_id'];
    });

    $ids = array();
    foreach ($ranked as $candidate) {
        if ($candidate['fresh']) {
            $ids[] = $candidate['post_id'];
            break;
        }
    }
    foreach ($ranked as $candidate) {
        if (count($ids) >= $count) {
            break;
        }
        if (! in_array($candidate['post_id'], $ids, true)) {
            $ids[] = $candidate['post_id'];
        }
    }

    $previous_ids = array_map('absint', (array) ($cache['ids'] ?? array()));
    if ($ids === $previous_ids && count($ranked) > $count) {
        foreach ($ranked as $candidate) {
            if (! in_array($candidate['post_id'], $ids, true)) {
                $ids[count($ids) - 1] = $candidate['post_id'];
                break;
            }
        }
    }

    update_option('promokodiki_top_snapshot_v2', array('window' => $window_start, 'ids' => $ids, 'previous_ids' => $previous_ids), false);
    return array('ids' => $ids, 'next_update' => $next_update);
}

function get_popular_promocodes(): array
{
    return promokodiki_top_snapshot()['ids'];
}

function get_next_update_time(): int
{
    return promokodiki_top_snapshot()['next_update'];
}

add_action('wp_ajax_promokodiki_top_snapshot', 'promokodiki_top_snapshot_ajax');
add_action('wp_ajax_nopriv_promokodiki_top_snapshot', 'promokodiki_top_snapshot_ajax');

function promokodiki_top_snapshot_ajax(): void
{
    check_ajax_referer('promokodiki_filter_frontend', 'nonce');
    $snapshot = promokodiki_top_snapshot();
    ob_start();
    display_promocodes_items($snapshot['ids']);
    wp_send_json_success(array(
        'html' => (string) ob_get_clean(),
        'next_update' => $snapshot['next_update'],
        'server_time' => current_time('timestamp'),
    ));
}
// Функция для отображения промокодов
function display_promocodes_items($promocode_ids)
{
    if (empty($promocode_ids)) {
        echo '<div class="top__item"><div class="top__head">Нет активных промокодов</div></div>';
        return;
    }

    foreach ($promocode_ids as $post_id) {
        setup_postdata(get_post($post_id));
        $expiry_date = get_post_meta($post_id, '_promocode_expiry_date', true);
        $used_count = get_post_meta($post_id, '_promocode_used_count', true) ?: 0;
        $likes = get_post_meta($post_id, '_promocode_likes', true) ?: 0;
        $dislikes = get_post_meta($post_id, '_promocode_dislikes', true) ?: 0;
        $coupon_code = get_post_meta($post_id, '_promocode_code', true);
        $coupon_link = get_post_meta($post_id, '_promocode_link', true);
        $campaign_name = get_post_meta($post_id, 'campaign_name', true);
        $is_popular = $used_count > 10;
        $is_new = (time() - get_post_time('U', true, $post_id)) < (7 * 24 * 60 * 60);
		$has_coupon_code = ! empty($coupon_code) && false === strpos($coupon_code, 'НЕ НУЖЕН');
		$expiry_label = $expiry_date ? wp_date('d.m.Y', strtotime($expiry_date)) : 'Бессрочно';
		$visitor_id = isset($_COOKIE['promokodiki_visitor']) ? sanitize_text_field(wp_unslash($_COOKIE['promokodiki_visitor'])) : '';
		$user_reaction = class_exists('Promokodiki_Filter_Promo_Interactions')
			? Promokodiki_Filter_Promo_Interactions::reaction_for($post_id, $visitor_id)
			: '';
		$description = wp_strip_all_tags(get_the_excerpt($post_id));

        $is_expired = false;
        if (!empty($expiry_date)) {
            $current_time = current_time('timestamp');
            $expiry_timestamp = strtotime($expiry_date);
            $expiry_end_of_day = strtotime('tomorrow', $expiry_timestamp) - 1;
            $is_expired = $current_time > $expiry_end_of_day;
        }
?>

        <div
			class="top__item"
			data-post-id="<?php echo esc_attr((string) $post_id); ?>"
			data-store-url="<?php echo esc_url($coupon_link); ?>"
			data-code="<?php echo esc_attr($has_coupon_code ? $coupon_code : ''); ?>"
			data-expiry="<?php echo esc_attr($expiry_label); ?>"
			data-expired="<?php echo $is_expired ? 'true' : 'false'; ?>"
			data-description="<?php echo esc_attr($description); ?>"
		>
            <?php if ($is_expired) : ?>
                <div class="promocodes__badge promocodes__badge_new">Истекло</div>
            <?php elseif ($is_popular) : ?>
                <div class="promocodes__badge promocodes__badge_popular">Популярный</div>
            <?php elseif ($is_new) : ?>
                <div class="promocodes__badge promocodes__badge_new">Новый</div>
            <?php endif; ?>

            <div class="top__img">
                <?php
                $image_url = get_post_meta($post_id, 'image_url', true);
                if ($image_url) : ?>
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo get_the_title($post_id); ?>">
                <?php else : ?>
                    <img src="<?php echo get_template_directory_uri(); ?>/img/top-1.png" alt="">
                <?php endif; ?>
            </div>

            <div class="top__wrap">
                <div class="top__last">
                    <?php if (!$is_expired) : ?>
                        <?php echo human_time_diff(get_post_time('U', true, $post_id), current_time('timestamp')) . ' назад'; ?>
                    <?php else: ?>
                        Истек
                    <?php endif; ?>
                </div>
                <div class="top__max">
                    <?php if (!$expiry_date) : ?>
                        Бессрочно
                    <?php else : ?>
                        до <?php echo date('d.m.Y', strtotime($expiry_date)); ?>
                    <?php endif; ?>
                </div>
            </div>

            <a href="<?php the_permalink(); ?>" class="top__head" title="<?php echo get_the_title($post_id); ?>"><?php echo get_the_title($post_id); ?></a>

            <div class="top__wrapper">
                <div class="top__wrap">
                    <div class="top__quantity"><?php echo $used_count; ?> Применено</div>
                    <div class="top__likes">
                        <div class="top__up promocodes__like promocodes__like_yes<?php echo 'like' === $user_reaction ? ' is-active' : ''; ?>" data-action="like" data-post-id="<?php echo esc_attr((string) $post_id); ?>">
                            <span><?php echo esc_html((string) $likes); ?></span>
                        </div>
                        <div class="top__down promocodes__like promocodes__like_no<?php echo 'dislike' === $user_reaction ? ' is-active' : ''; ?>" data-action="dislike" data-post-id="<?php echo esc_attr((string) $post_id); ?>">
                            <span><?php echo esc_html((string) $dislikes); ?></span>
                        </div>
                    </div>
                </div>

                <div class="top__author">
                    <?php
                    // Получаем image_url из метаполя поста
                    $image_url = get_post_meta($post_id, 'image_url', true);

                    // Поиск категории магазина
                    $display_name = $campaign_name;
                    $author_url = '';
                    $author_avatar = '';

                    if (!empty($campaign_name)) {
                        $shop_categories = get_terms(array(
                            'taxonomy' => 'shops_category',
                            'hide_empty' => false
                        ));

                        $matched_category = null;
                        $campaign_name_clean = strtolower(trim($campaign_name));

                        foreach ($shop_categories as $category) {
                            $category_name_clean = strtolower(trim($category->name));
                            if (
                                $category_name_clean === $campaign_name_clean ||
                                strpos($campaign_name_clean, $category_name_clean) !== false ||
                                strpos($category_name_clean, $campaign_name_clean) !== false
                            ) {
                                $matched_category = $category;
                                break;
                            }
                        }

                        if ($matched_category) {
                            $author_url = get_term_link($matched_category);
                            $display_name = $matched_category->name;
                        }
                    }

                    // Приоритет: сначала image_url из поста, потом из категории, потом аватар автора
                    if (!empty($image_url)) {
                        $author_avatar = $image_url;
                    } elseif ($matched_category) {
                        $image_id = get_term_meta($matched_category->term_id, 'shops-category-image-id', true);
                        if ($image_id) {
                            $author_avatar = wp_get_attachment_image_url($image_id, 'thumbnail');
                        }
                    }

                    if (empty($author_avatar)) {
                        $author_id = get_post_field('post_author', $post_id);
                        $author_avatar = get_avatar_url($author_id, array('size' => 24));
                    }
                    ?>

                    <?php if ($author_avatar) : ?>
                        <img src="<?php echo esc_url($author_avatar); ?>" alt="<?php echo esc_attr($display_name); ?>">
                    <?php endif; ?>

                    <?php if (!empty($author_url)) : ?>
                        <a href="<?php echo esc_url($author_url); ?>" class="top__nick" target="_blank" rel="nofollow">
                            @<?php echo str_replace(' ', '', $display_name); ?>
                        </a>
                    <?php else : ?>
                        <span class="top__nick">@<?php echo str_replace(' ', '', $display_name); ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($is_expired) : ?>
					<button class="top__button btn-reset" disabled>Промокод истёк</button>
				<?php elseif ($has_coupon_code) : ?>
                    <button class="top__button btn-reset promocodes__view ui-button ui-button--orange" data-post-id="<?php echo esc_attr((string) $post_id); ?>">
                        Показать промокод
                    </button>
                <?php elseif (!empty($coupon_link)) : ?>
                    <a href="<?php echo esc_url($coupon_link); ?>" rel="nofollow noopener" target="_blank" data-post-id="<?php echo esc_attr((string) $post_id); ?>" class="top__button top__button_link btn-reset promocodes__link">
                        Перейти в магазин
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php
    }
}









// Добавляем настройку в functions.php
add_action('admin_menu', 'popular_promocodes_settings');

function popular_promocodes_settings()
{
    add_options_page(
        'Настройки популярных промокодов',
        'Популярные промокоды',
        'manage_options',
        'popular-promocodes',
        'popular_promocodes_settings_page'
    );
}

function popular_promocodes_settings_page()
{
    ?>
    <div class="wrap">
        <h1>Настройки популярных промокодов</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('popular_promocodes_settings');
            do_settings_sections('popular_promocodes_settings');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="popular_promocodes_count">Количество промокодов</label>
                    </th>
                    <td>
                        <input type="number"
                            id="popular_promocodes_count"
                            name="popular_promocodes_count"
                            value="<?php echo esc_attr(get_option('popular_promocodes_count', 5)); ?>"
                            min="1"
                            max="20" />
                        <p class="description">Количество отображаемых промокодов (от 1 до 20)</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
<?php
}

// Регистрируем настройку
add_action('admin_init', 'register_popular_promocodes_settings');

function register_popular_promocodes_settings()
{
    register_setting('popular_promocodes_settings', 'popular_promocodes_count', array(
        'type' => 'integer',
        'sanitize_callback' => 'intval',
        'default' => 4
    ));
}

add_action('update_option_popular_promocodes_count', 'clear_popular_promocodes_cache', 10, 3);

function clear_popular_promocodes_cache($old_value, $new_value, $option)
{
    delete_option('promokodiki_top_snapshot_v2');
}
