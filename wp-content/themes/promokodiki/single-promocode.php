<?php

/**
 * Шаблон одиночного промокода (single-promocode.php)
 */
get_header();
// Определяем тип поста и соответствующие префиксы метаполей
$post_type = 'promocode';
$meta_prefix = '_promocode_';

// Получаем общие метаданные
$expiry_date = get_post_meta(get_the_ID(), $meta_prefix . 'expiry_date', true);
$used_count = get_post_meta(get_the_ID(), $meta_prefix . 'used_count', true) ?: 0;
$likes = get_post_meta(get_the_ID(), $meta_prefix . 'likes', true) ?: 0;
$dislikes = get_post_meta(get_the_ID(), $meta_prefix . 'dislikes', true) ?: 0;
$is_popular = $used_count > 10;
$is_new = (time() - get_the_time('U')) < (7 * 24 * 60 * 60);
$coupon_code = get_post_meta(get_the_ID(), $meta_prefix . 'code', true);
$coupon_link = get_post_meta(get_the_ID(), $meta_prefix . 'link', true);
$is_verified = get_post_meta(get_the_ID(), $meta_prefix . 'is_verified', true);
$user_ip = $_SERVER['REMOTE_ADDR'];
$liked_ips = get_post_meta(get_the_ID(), $meta_prefix . 'liked_ips', true) ?: array();
$has_liked = in_array($user_ip, $liked_ips);

// Для shops получаем дополнительные поля

// Проверяем истек ли купон/промокод
$is_expired = false;
if (!empty($expiry_date)) {
  $current_time = current_time('timestamp');
  $expiry_timestamp = strtotime($expiry_date);
  $expiry_end_of_day = strtotime('tomorrow', $expiry_timestamp) - 1;
  $is_expired = $current_time > $expiry_end_of_day;
}

// Получаем данные категории для shops_category
$current_category = get_queried_object();
$image_url = '';
$image_alt = '';
$category_name = '';

if (is_tax('shops_category')) {
  $image_id = get_term_meta($current_category->term_id, 'shops-category-image-id', true);
  $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
  $image_alt = $image_id ? (get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: $current_category->name) : '';
  $category_name = $current_category->name;
}
?>

<section class="promocodes">
  <div class="container">
    <div class="promocodes__row">
      <div class="promocodes__column">
        <div class="promocodes__items promocodes__items_page">
          <div class="promocodes__item promocodes__item_page <?php echo $is_expired ? 'filter-grayscale' : ''; ?>" data-post-id="<?php echo get_the_ID(); ?>">
            <?php if ($is_expired) : ?>
              <div class="promocodes__badge promocodes__badge_new">Истекло</div>.
            <?php elseif ($is_popular) : ?>
              <div class="promocodes__badge promocodes__badge_popular">Популярный</div>
            <?php elseif ($is_new) : ?>
              <div class="promocodes__badge promocodes__badge_new">Новый</div>
            <?php endif; ?>

            <?php
            $image_uri = get_post_meta(get_the_ID(), 'image_url', true);
            if ($image_uri) {
            ?>
              <div class="promocodes__imgs promocodes__imgs_page">
                <?php echo '<img src="' . esc_url($image_uri) . '" alt="' . esc_attr(get_the_title()) . '">'; ?>
              </div>
            <?php } ?>

            <div class="promocodes__wrapper promocodes__wrapper_page">
              <div class="promocodes__wrap">
                <?php if (!$is_expired) : ?>
                  <div class="promocodes__latest">Опубликовано <?php echo human_time_diff(get_the_time('U'), current_time('timestamp')) . ' назад'; ?></div>
                <?php else: ?>
                  <div class="promocodes__latest">Истекло</div>
                <?php endif; ?>
                <?php if (!$expiry_date) : ?>
                  <div class="promocodes__date promocodes__date_page">Бессрочно</div>
                <?php else : ?>
                  <div class="promocodes__date promocodes__date_page">до <?php echo date('d.m.Y', strtotime($expiry_date)); ?></div>
                <?php endif; ?>
              </div>

              <h1 class="promocodes__title promocodes__title_page"><?php the_title(); ?></h1>

              <div class="promocodes__loop">
                <?php $used_count = get_post_meta(get_the_ID(), '_promocode_used_count', true) ?: 0; ?>
                <div class="promocodes__used"><?php echo $used_count; ?> Применено</div>

                <div class="promocodes__likes">
                  <div class="promocodes__like promocodes__like_yes"
                    data-post-id="<?php echo get_the_ID(); ?>" data-action="like">
                    👍
                    <span><?php echo $likes; ?></span>
                  </div>
                  <div class="promocodes__like promocodes__like_no"
                    data-post-id="<?php echo get_the_ID(); ?>" data-action="dislike">
                    👎
                    <span><?php echo $dislikes; ?></span>
                  </div>
                </div>
              </div>

              <div class="promocodes__data promocodes__data_page">
                <?php
                $author_id = get_the_author_meta('ID');
                $author_avatar = get_avatar_url($author_id, array('size' => 50));
                ?>
                <a href="<?php echo get_author_posts_url($author_id); ?>" class="promocodes__author">
                  <img src="<?php echo esc_url($author_avatar); ?>" alt="<?php echo get_the_author(); ?>">
                  <span><?php echo get_the_author(); ?></span>
                </a>
                <?php if (! empty($coupon_code) && strpos($coupon_code, 'НЕ НУЖЕН') === false) : ?>
                  <a href="<?php echo esc_attr($coupon_link); ?>" rel="nofollow" target="_blank"><button class="btn-reset promocodes__link promocodes__button" data-post-id="<?php echo get_the_ID(); ?>">Применить код</button></a>
                  <button class="btn-reset promocodes__button promocodes__copy" data-coupon="<?php echo esc_attr($coupon_code); ?>">
                    <?php echo esc_html($coupon_code); ?>
                  </button>
                <?php else : ?>
                  <a href="<?php echo esc_attr($coupon_link); ?>" rel="nofollow" target="_blank"><button class="btn-reset promocodes__link promocodes__button" data-post-id="<?php echo get_the_ID(); ?>">Перейти в магазин</button></a>
                <?php endif; ?>

                <?php if (! empty($coupon_code) && strpos($coupon_code, 'НЕ НУЖЕН') === false) : ?>
                  <input type="hidden" name="_promocode_code" value="<?php echo esc_attr($coupon_code); ?>">
                  <input type="hidden" name="_promocode_link" value="<?php echo esc_attr($coupon_link); ?>">
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="promocodes__description">
            <?php if (get_the_content()) : ?>
              <h2>Подробнее о скидке</h2>
              <?php
              $content = apply_filters('the_content', get_the_content());
              echo $content;
              ?>
            <?php endif; ?>

            <?php
            $terms = get_the_terms(get_the_ID(), 'promocode_category');
            if ($terms && !is_wp_error($terms)) : ?>
              <div class="promocodes__category">
                <div class="promocodes__category-title">Категории</div>
                <div class="promocodes__category-items">
                  <?php foreach ($terms as $term) : ?>
                    <a href="<?php echo get_term_link($term); ?>" class="promocodes__category-item"><?php echo $term->name; ?></a>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
          <?php
          comments_template();
          ?>
        </div>
      </div>

      <aside class="promocodes__aside">
        <?php
        // Получаем текущую категорию (если это страница категории или поста)
        $current_category = false;

        // Если это страница категории
        if (is_category()) {
          $current_category = get_queried_object();
        }
        // Если это одиночная запись (пост)
        elseif (is_singular('promocode')) {
          $categories = get_the_terms(get_the_ID(), 'category'); // 'category' или ваша таксономия
          if ($categories && !is_wp_error($categories)) {
            $current_category = $categories; // Берем первую категорию
          }
        }

        // Блок "Промокоды от команды" (только в текущей категории)
        $team_query_args = array(
          'post_type' => 'promocode',
          'posts_per_page' => 4,
          'author__in' => array(1), // ID авторов команды
          'orderby' => 'rand',
          'post__not_in' => array(get_the_ID()), // Исключаем текущий пост
        );

        // Если есть текущая категория, добавляем фильтр
        if ($current_category) {
          $team_query_args['tax_query'] = array(
            array(
              'taxonomy' => 'category', // Или ваша таксономия, например 'promocode_category'
              'field' => 'term_id',
              'terms' => $current_category->term_id,
            ),
          );
        }

        $team_query = new WP_Query($team_query_args);

        if ($team_query->have_posts()) : ?>
          <div class="promocodes__teams">
            <div class="promocodes__teams-title">Промокоды от команды Промокодики</div>
            <div class="promocodes__teams-items">
              <?php while ($team_query->have_posts()) : $team_query->the_post(); ?>
                <div class="promocodes__teams-item">
                  <div class="promocodes__teams-wrap">
                    <div class="promocodes__author">
                      <?php $author_avatar = get_avatar_url(get_the_author_meta('ID'), array('size' => 30)); ?>
                      <img src="<?php echo esc_url($author_avatar); ?>" alt="<?php echo get_the_author(); ?>">
                      <span><?php echo get_the_author(); ?></span>
                    </div>
                    <?php $expiry_date = get_post_meta(get_the_ID(), '_promocode_expiry_date', true); ?>
                    <?php if (!$expiry_date) : ?>
                      <div class="promocodes__teams-date">Бессрочно</div>
                    <?php else : ?>
                      <div class="promocodes__teams-date">до <?php echo date('d.m.Y', strtotime($expiry_date)); ?></div>
                    <?php endif; ?>
                  </div>
                  <a href="<?php the_permalink(); ?>" class="promocodes__teams-head"><?php the_title(); ?></a>
                </div>
              <?php endwhile;
              wp_reset_postdata(); ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="promocodes__store">
          <div class="promocodes__store-wrap">
            <div class="promocodes__store-title">Промокоды магазинов</div>
            <a href="<?php echo esc_url(home_url('/shops/')); ?>" class="promocodes__store-link">
              Все
            </a>
          </div>
          <div class="promocodes__store-items">
            <div class="promocodes__store-items">
              <div class="promocodes__store-items">
                <?php
                // Получаем 8 самых популярных категорий магазинов
                $popular_stores = get_terms(array(
                  'taxonomy' => 'shops_category',
                  'orderby' => 'count',
                  'order' => 'DESC',
                  'number' => 8,
                  'hide_empty' => true, // Показывать только категории с постами
                ));

                if (!empty($popular_stores) && !is_wp_error($popular_stores)) {
                  foreach ($popular_stores as $store) {
                    // Получаем один пост из этой категории
                    $posts = get_posts(array(
                      'post_type' => 'promocode',
                      'tax_query' => array(
                        array(
                          'taxonomy' => 'shops_category',
                          'field' => 'term_id',
                          'terms' => $store->term_id,
                        )
                      ),
                      'posts_per_page' => 1,
                      'orderby' => 'date',
                      'order' => 'DESC'
                    ));

                    if (!empty($posts)) {
                      $post = $posts[0];
                      $image_uri = get_post_meta($post->ID, 'image_url', true);

                      if ($image_uri) {
                        echo '<div class="promocodes__imgs">';
                        echo '<a href="' . get_term_link($store) . '">';
                        echo '<img src="' . esc_url($image_uri) . '" alt="' . esc_attr($store->name) . '">';
                        echo '</a>';
                        echo '</div>';
                      }
                    }
                  }
                } else {
                  echo '<p>Нет популярных магазинов</p>';
                }
                ?>
              </div>
            </div>
          </div>
          <?php
          $image_uri = get_post_meta(get_the_ID(), 'image_url', true);
          if ($image_uri) {
          ?>
            <div class="promocodes__imgs ">
              <?php echo '<img src="' . esc_url($image_uri) . '" alt="' . esc_attr(get_the_title()) . '">'; ?>
            </div>
          <?php } ?>
        </div>
      </aside>
    </div>
  </div>
</section>

<?php

get_footer();
