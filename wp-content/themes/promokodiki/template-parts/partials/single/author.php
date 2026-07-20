<section class="posts" itemscope itemtype="https://schema.org/Person">
  <div class="container">
    <div class="posts__column">
      <?php
      // Получаем данные автора
      $author_id = get_queried_object_id();
      $author_name = get_the_author_meta('display_name', $author_id);
      $author_description = get_the_author_meta('description', $author_id);
      $author_posts_count = count_user_posts($author_id);
      $avatar_url = get_avatar_url($author_id, array('size' => 200));

      // Устанавливаем количество постов на страницу
      $posts_per_page = 5;
      $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

      // Создаем новый запрос с пагинацией
      $args = array(
        'author' => $author_id,
        'posts_per_page' => $posts_per_page,
        'paged' => $paged
      );
      $author_posts = new WP_Query($args);
      ?>

      <div class="posts__author">
        <div class="posts__author-img">
          <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($author_name); ?>" itemprop="image">
        </div>
        <div class="posts__author-wrap">
          <div class="posts__author-name" itemprop="name"><?php echo esc_html($author_name); ?></div>
          <div class="posts__author-posts">
            <div class="posts__author-post">Автор <span itemprop="worksFor" itemscope itemtype="https://schema.org/Organization">
                <span itemprop="name"><?php echo esc_html(get_bloginfo('name')); ?></span>
              </span></div>
            <div class="posts__author-summ"><?php echo $author_posts_count; ?> <?php echo _n('публикация', 'публикаций', $author_posts_count, 'textdomain'); ?></div>
          </div>
          <?php if ($author_description) : ?>
            <div class="posts__author-content" itemprop="description"><?php echo esc_html($author_description); ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="posts__items" itemscope itemtype="https://schema.org/ItemList">
        <?php
        if ($author_posts->have_posts()) :
          $position = 1;
          while ($author_posts->have_posts()) : $author_posts->the_post();
            $post_date = get_the_date('d.m.Y');
            $post_url = get_permalink();
            $post_title = get_the_title();
            $post_excerpt = get_the_excerpt();
            $thumbnail_url = get_the_post_thumbnail_url() ?: get_template_directory_uri() . '/img/faq.png';
            $reading_time = reading_time();
            $views_count = get_post_meta(get_the_ID(), 'post_views_count', true) ?: 0;
        ?>
            <div class="posts__item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
              <meta itemprop="position" content="<?php echo $position++; ?>" />
              <div class="posts__img" itemprop="image" itemscope itemtype="https://schema.org/ImageObject">
                <a href="<?php echo $post_url; ?>" itemprop="url">
                  <img src="<?php echo $thumbnail_url; ?>" alt="<?php echo esc_attr($post_title); ?>" itemprop="contentUrl">
                </a>
              </div>
              <div class="posts__wrap">
                <div class="single__data single__data_posts">
                  <span itemprop="datePublished" content="<?php echo get_the_date('c'); ?>"><?php echo $post_date; ?></span>
                  <span>|</span>
                  <span itemprop="timeRequired" content="<?php echo 'PT' . $reading_time . 'M'; ?>">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path
                        d="M9 4.55556V9H13.4444M9 17C7.94943 17 6.90914 16.7931 5.93853 16.391C4.96793 15.989 4.08601 15.3997 3.34315 14.6569C2.60028 13.914 2.011 13.0321 1.60896 12.0615C1.20693 11.0909 1 10.0506 1 9C1 7.94943 1.20693 6.90914 1.60896 5.93853C2.011 4.96793 2.60028 4.08601 3.34315 3.34315C4.08601 2.60028 4.96793 2.011 5.93853 1.60896C6.90914 1.20693 7.94943 1 9 1C11.1217 1 13.1566 1.84285 14.6569 3.34315C16.1571 4.84344 17 6.87827 17 9C17 11.1217 16.1571 13.1566 14.6569 14.6569C13.1566 16.1571 11.1217 17 9 17Z"
                        stroke="#9B9AA0" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <?php echo $reading_time; ?> мин
                  </span>
                  <span itemprop="interactionStatistic" itemscope itemtype="https://schema.org/InteractionCounter">
                    <meta itemprop="interactionType" content="https://schema.org/WatchAction" />
                    <meta itemprop="userInteractionCount" content="<?php echo $views_count; ?>" />
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path
                        d="M21.257 10.962C21.731 11.582 21.731 12.419 21.257 13.038C19.764 14.987 16.182 19 12 19C7.818 19 4.236 14.987 2.743 13.038C2.51206 12.7413 2.38666 12.376 2.38666 12C2.38666 11.624 2.51206 11.2587 2.743 10.962C4.236 9.013 7.818 5 12 5C16.182 5 19.764 9.013 21.257 10.962Z"
                        stroke="#9B9AA0" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                      <path
                        d="M12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15Z"
                        stroke="#9B9AA0" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <?php echo $views_count; ?>
                  </span>
                </div>
                <a href="<?php echo $post_url; ?>" class="posts__title" itemprop="url">
                  <span itemprop="name"><?php echo $post_title; ?></span>
                </a>
                <div class="posts__content" itemprop="description"><?php echo $post_excerpt; ?></div>
              </div>
            </div>
        <?php
          endwhile;
          wp_reset_postdata();
        else :
          echo '<p>У этого автора пока нет публикаций.</p>';
        endif;
        ?>
      </div>

      <?php
      // Показываем кнопку "Показать еще" только если постов больше 5 и есть еще посты для загрузки
      if ($author_posts_count > $posts_per_page && $author_posts->max_num_pages > $paged) :
      ?>
        <div class="posts__load">
          <button class="btn-reset button-blue" id="load-more-posts" data-page="1" data-author="<?php echo $author_id; ?>">Показать еще</button>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>