<?php
if (!function_exists('promocodes_sections')) {
  function promocodes_sections($post_id)
  {
    while (have_rows('promokody_end', $post_id)) : the_row();
?>
      <?php if (get_sub_field('vklyuchit') == 1) : ?>
        <div class="container">
          <h1><?php the_title(); ?></h1>
        </div>
        <section class="promocodes">
          <div class="container">
            <div class="promocodes__row">
              <div class="promocodes__column">
                <?php if (function_exists('promokodiki_filter_render')) : ?>
                  <?php
                  promokodiki_filter_render(array(
                    'context' => 'home',
                    'object_id' => 0,
                  ));
                  ?>
                <?php else : ?>
                <div class="promocodes__filters">
                  <div class="promocodes__filters-wrap">
                    <p><?php esc_html_e('Активируйте Promokodiki AJAX Filter.', 'promokodiki'); ?></p>
                  </div>
                  <div class="promocodes__sort">
                  </div>
                </div>
                <div class="promocodes__items">
                  <?php
                  // Создаем кастомный запрос для промокодов
                  $args = array(
                    'post_type' => 'promocode',
                    'posts_per_page' => 8, // Количество промокодов на главной
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'meta_query' => array(
                      array(
                        'key' => '_promocode_expiry_date',
                        'value' => current_time('mysql'),
                        'compare' => '>=',
                        'type' => 'DATETIME'
                      )
                    )
                  );

                  $promocodes_query = new WP_Query($args);

                  if ($promocodes_query->have_posts()) :
                    while ($promocodes_query->have_posts()) : $promocodes_query->the_post();
                      $expiry_date = get_post_meta(get_the_ID(), '_promocode_expiry_date', true);
                      $used_count = get_post_meta(get_the_ID(), '_promocode_used_count', true) ?: 0;
                      $likes = get_post_meta(get_the_ID(), '_promocode_likes', true) ?: 0;
                      $dislikes = get_post_meta(get_the_ID(), '_promocode_dislikes', true) ?: 0;
                      $is_popular = $used_count > 10;
                      $is_new = (time() - get_the_time('U')) < (7 * 24 * 60 * 60);
                      $coupon_code = get_post_meta(get_the_ID(), '_promocode_code', true);
                      $coupon_link = get_post_meta(get_the_ID(), '_promocode_link', true);
                      $is_verified = get_post_meta(get_the_ID(), '_promocode_is_verified', true);
                      $is_expired = false;

                      if (!empty($expiry_date)) {
                        $current_time = current_time('timestamp');
                        $expiry_timestamp = strtotime($expiry_date);
                        $expiry_end_of_day = strtotime('tomorrow', $expiry_timestamp) - 1;
                        $is_expired = $current_time > $expiry_end_of_day;
                      }

                      $image_url = '';
                      $category_name = '';
                      $brands = get_the_terms(get_the_ID(), 'promocode_brand');
                  ?>
                      <div class="promocodes__item <?php echo $is_expired ? 'filter-grayscale' : ''; ?>" data-post-id="<?php echo get_the_ID(); ?>">
                        <?php if ($is_expired) : ?>
                          <div class="promocodes__badge promocodes__badge_new">Истекло</div>
                        <?php elseif ($is_popular) : ?>
                          <div class="promocodes__badge promocodes__badge_popular">Популярный</div>
                        <?php elseif ($is_new) : ?>
                          <div class="promocodes__badge promocodes__badge_new">Новый</div>
                        <?php endif; ?>

                        <?php
                        $image_uri = get_post_meta(get_the_ID(), 'image_url', true);
                        if ($image_uri) : ?>
                          <div class="promocodes__imgs">
                            <img src="<?php echo esc_url($image_uri); ?>" alt="<?php echo esc_attr(get_the_title()); ?>">
                          </div>
                        <?php endif; ?>

                        <div class="promocodes__wrapper">
                          <div class="promocodes__wrap">
                            <?php if (!$is_expired) : ?>
                              <div class="promocodes__latest">Опубликовано <?php echo human_time_diff(get_the_time('U'), current_time('timestamp')) . ' назад'; ?></div>
                            <?php else: ?>
                              <div class="promocodes__latest">Истекло</div>
                            <?php endif; ?>
                            <?php if ($expiry_date) : ?>
                              <div class="promocodes__date">до <?php echo date('d.m.Y', strtotime($expiry_date)); ?></div>
                            <?php endif; ?>
                          </div>

                          <a href="<?php the_permalink(); ?>" class="promocodes__title"><?php the_title(); ?></a>

                          <div class="promocodes__data">
                            <?php
                            // Получаем campaign_name из метаполя поста
                            $campaign_name = get_post_meta(get_the_ID(), '_promocode_campaign_name', true);

                            // Если campaign_name не найден, пробуем альтернативные ключи
                            if (empty($campaign_name)) {
                              $campaign_name = get_post_meta(get_the_ID(), 'campaign_name', true);
                            }
                            if (empty($campaign_name)) {
                              $campaign_name = get_post_meta(get_the_ID(), '_campaign_name', true);
                            }

                            // Получаем изображение из метаполя image_url
                            $image_uri = get_post_meta(get_the_ID(), 'image_url', true);

                            if (!empty($campaign_name)) {
                              // Поиск категории магазина
                              $display_name = $campaign_name;
                              $author_url = '';

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

                              // Если image_uri не задан, пробуем получить изображение из категории
                              if (empty($image_uri) && $matched_category) {
                                $image_id = get_term_meta($matched_category->term_id, 'shops-category-image-id', true);
                                if ($image_id) {
                                  $image_uri = wp_get_attachment_image_url($image_id, 'medium');
                                }
                              }

                              // Если всё еще нет изображения, берем аватар автора
                              if (empty($image_uri)) {
                                $author_id = get_post_field('post_author', get_the_ID());
                                $image_uri = get_avatar_url($author_id, array('size' => 30));
                              }
                            ?>

                              <div class="promocodes__author">
                                <?php if ($image_uri) : ?>
                                  <img src="<?php echo esc_url($image_uri); ?>"
                                    alt="<?php echo esc_attr($display_name); ?>">
                                <?php endif; ?>

                                <?php if (!empty($author_url)) : ?>
                                  <a href="<?php echo esc_url($author_url); ?>" target="_blank" rel="nofollow">
                                    <?php echo esc_html($display_name); ?>
                                  </a>
                                <?php else : ?>
                                  <span><?php echo esc_html($display_name); ?></span>
                                <?php endif; ?>
                              </div>

                            <?php } else { ?>
                              <div class="promocodes__author">
                                <?php if ($image_uri) : ?>
                                  <img src="<?php echo esc_url($image_uri); ?>" alt="Изображение">
                                <?php endif; ?>
                                <span>Без названия</span>
                              </div>
                            <?php } ?>

                            <div class="promocodes__used"><?php echo $used_count; ?> Применено</div>

                            <div class="promocodes__likes">
                              <div class="promocodes__like promocodes__like_yes" data-post-id="<?php echo get_the_ID(); ?>" data-action="like">
                                👍 <span><?php echo $likes; ?></span>
                              </div>
                              <div class="promocodes__like promocodes__like_no" data-post-id="<?php echo get_the_ID(); ?>" data-action="dislike">
                                👎 <span><?php echo $dislikes; ?></span>
                              </div>
                            </div>

                            <?php if (!empty($coupon_code) && strpos($coupon_code, 'НЕ НУЖЕН') === false) : ?>
                              <button class="btn-reset promocodes__view promocodes__button" data-post-id="<?php echo get_the_ID(); ?>" data-graph-path="promocode-<?php the_ID(); ?>">Посмотреть код</button>
                            <?php else : ?>
                              <a href="<?php echo esc_attr($coupon_link); ?>" rel="nofollow" target="_blank"><button class="btn-reset promocodes__link promocodes__button" data-post-id="<?php echo get_the_ID(); ?>">Перейти в магазин</button></a>
                            <?php endif; ?>

                            <?php if (!empty($coupon_code) && strpos($coupon_code, 'НЕ НУЖЕН') === false) : ?>
                              <input type="hidden" name="_promocode_code" value="<?php echo esc_attr($coupon_code); ?>">
                              <input type="hidden" name="_promocode_link" value="<?php echo esc_attr($coupon_link); ?>">
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    <?php endwhile;
                    wp_reset_postdata(); ?>
                  <?php else : ?>
                    <p class="no-promocodes">На данный момент нет активных промокодов.</p>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
              </div>
              <aside class="promocodes__aside">
                <div class="promocodes__rating">
                  <div class="promocodes__rating-title">Рейтинг <?php bloginfo('name'); ?></div>
                  <div class="promocodes__rating-subtitle">Нас оценили уже 1 500 пользователей 🤩</div>
                  <div class="promocodes__rating-stars">
                    <div class="promocodes__rating-star promocodes__rating-star_ya">
                      <img src="<?php echo get_template_directory_uri(); ?>/img/rating-img-1.png" alt="Яндекс">
                      <span>4,5 <span>/5</span></span>
                    </div>
                    <div class="promocodes__rating-star promocodes__rating-star_ggl">
                      <img src="<?php echo get_template_directory_uri(); ?>/img/rating-img-2.png" alt="Google">
                      <span>4,5 <span>/5</span></span>
                    </div>
                    <div class="promocodes__rating-star promocodes__rating-star_str">
                      <img src="<?php echo get_template_directory_uri(); ?>/img/rating-img-3.png" alt="Trustpilot">
                      <span>5 <span>/5</span></span>
                    </div>
                  </div>
                  <div class="promocodes__rating-content">
                    <p>Сервис "<?php bloginfo('name'); ?>" помогает Вам экономить на покупках в любимых магазинах. Наша команда вручную отбирает лучшие промокоды и скидки на самые популярные и стильные товары.</p>
                    <p>Присоединяйтесь к нашему сообществу и наслаждайтесь шопингом с умом — быть в тренде стало ещё доступнее!</p>
                  </div>
                </div>
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
                </div>
                <div class="promocodes__teams">
                  <div class="promocodes__teams-title">Последние промокоды</div>
                  <div class="promocodes__teams-items">
                    <?php
                    $recent_promocodes = new WP_Query(array(
                      'post_type' => 'promocode',
                      'posts_per_page' => 4,
                      'post__not_in' => array(get_the_ID()),
                    ));

                    while ($recent_promocodes->have_posts()) : $recent_promocodes->the_post();
                      $expiry_date = get_post_meta(get_the_ID(), '_promocode_expiry_date', true);
                      $author_avatar = get_avatar_url(get_the_author_meta('ID'), array('size' => 30));
                    ?>
                      <div class="promocodes__teams-item">
                        <div class="promocodes__teams-wrap">
                          <div class="promocodes__author">
                            <?php
                            // Получаем campaign_name из метаполя поста
                            $campaign_name = get_post_meta(get_the_ID(), '_promocode_campaign_name', true);

                            // Если campaign_name не найден, пробуем альтернативные ключи
                            if (empty($campaign_name)) {
                              $campaign_name = get_post_meta(get_the_ID(), 'campaign_name', true);
                            }
                            if (empty($campaign_name)) {
                              $campaign_name = get_post_meta(get_the_ID(), '_campaign_name', true);
                            }

                            // Получаем изображение из метаполя image_url
                            $image_uri = get_post_meta(get_the_ID(), 'image_url', true);

                            if (!empty($campaign_name)) {
                              // Поиск категории магазина
                              $display_name = $campaign_name;
                              $author_url = '';
                              $author_avatar = '';

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

                              // Приоритет: сначала image_uri из поста, потом из категории, потом аватар автора
                              if (!empty($image_uri)) {
                                $author_avatar = $image_uri;
                              } elseif ($matched_category) {
                                $image_id = get_term_meta($matched_category->term_id, 'shops-category-image-id', true);
                                if ($image_id) {
                                  $author_avatar = wp_get_attachment_image_url($image_id, 'thumbnail');
                                }
                              }

                              // Если всё еще нет изображения, берем аватар автора поста
                              if (empty($author_avatar)) {
                                $author_id = get_post_field('post_author', get_the_ID());
                                $author_avatar = get_avatar_url($author_id, array('size' => 24));
                              }
                            ?>

                              <?php if ($author_avatar) : ?>
                                <img src="<?php echo esc_url($author_avatar); ?>"
                                  alt="<?php echo esc_attr($display_name); ?>">
                              <?php endif; ?>

                              <?php if (!empty($author_url)) : ?>
                                <a href="<?php echo esc_url($author_url); ?>" class="top__nick" target="_blank" rel="nofollow">
                                  @<?php echo str_replace(' ', '', $display_name); ?>
                                </a>
                              <?php else : ?>
                                <span class="top__nick">@<?php echo str_replace(' ', '', $display_name); ?></span>
                              <?php endif; ?>

                            <?php } else { ?>
                              <?php if (!empty($image_uri)) : ?>
                                <img src="<?php echo esc_url($image_uri); ?>" alt="Изображение">
                              <?php endif; ?>
                              <span class="top__nick">Без названия</span>
                            <?php } ?>
                          </div>

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
                  <a href="<?php echo get_post_type_archive_link('promocode'); ?>" class="promocodes__teams-link">
                    Смотреть все предложения
                    <svg width="7" height="11" viewBox="0 0 7 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M0.994756 10.7327C0.887968 10.7329 0.792554 10.6909 0.708514 10.6067L0.141578 9.853C0.0575377 9.76882 0.0154248 9.66924 0.0152398 9.55427C0.0150424 9.43163 0.0568346 9.33191 0.140615 9.25513L3.37599 6.11271C3.76693 5.733 3.78185 5.11018 3.40953 4.7122L0.266953 1.353C0.182913 1.26882 0.1408 1.16924 0.140615 1.05427C0.140443 0.946957 0.182235 0.847244 0.265991 0.755127L0.691863 0.258856C0.775632 0.174404 0.87091 0.132091 0.977697 0.131918C1.09211 0.131732 1.19515 0.173723 1.28682 0.257889L6.13442 5.125C6.21846 5.20918 6.26057 5.30876 6.26076 5.42373C6.26094 5.53871 6.21915 5.63842 6.13538 5.72288L1.30347 10.6057C1.2197 10.6902 1.1168 10.7325 0.994756 10.7327Z" fill="#FE3388" />
                    </svg>
                  </a>
                </div>
              </aside>
            </div>
          </div>
        </section>
      <?php else : ?>
        <?php // echo 'false'; 
        ?>
      <?php endif; ?>
    <?php endwhile; ?>
  <?php } ?>
<?php } ?>
<?php
if (have_rows('promokody_end')) {
  promocodes_sections(null); // Текущий пост
} else {
  promocodes_sections('option'); // Пост с ID 23
}

?>
