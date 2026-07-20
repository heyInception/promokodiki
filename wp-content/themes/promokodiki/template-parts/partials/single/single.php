<article class="single" itemscope itemtype="https://schema.org/Article">
  <div class="container">
    <div class="single__column">
      <div class="single__title">
        <h1 itemprop="headline"><?php the_title(); ?></h1>
      </div>
      <div class="single__wrap">
        <div class="single__data">
          <span itemprop="datePublished" content="<?php echo get_the_date('c'); ?>"><?php echo get_the_date('d.m.Y'); ?></span>
          <span>|</span>
          <span itemprop="timeRequired" content="<?php echo 'PT' . reading_time() . 'M'; ?>">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path
                d="M9 4.55556V9H13.4444M9 17C7.94943 17 6.90914 16.7931 5.93853 16.391C4.96793 15.989 4.08601 15.3997 3.34315 14.6569C2.60028 13.914 2.011 13.0321 1.60896 12.0615C1.20693 11.0909 1 10.0506 1 9C1 7.94943 1.20693 6.90914 1.60896 5.93853C2.011 4.96793 2.60028 4.08601 3.34315 3.34315C4.08601 2.60028 4.96793 2.011 5.93853 1.60896C6.90914 1.20693 7.94943 1 9 1C11.1217 1 13.1566 1.84285 14.6569 3.34315C16.1571 4.84344 17 6.87827 17 9C17 11.1217 16.1571 13.1566 14.6569 14.6569C13.1566 16.1571 11.1217 17 9 17Z"
                stroke="#9B9AA0" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <?php echo reading_time(); ?> мин
          </span>
          <span itemprop="interactionStatistic" itemscope itemtype="https://schema.org/InteractionCounter">
            <meta itemprop="interactionType" content="https://schema.org/WatchAction" />
            <meta itemprop="userInteractionCount" content="<?php echo get_post_meta(get_the_ID(), 'post_views_count', true) ?: 0; ?>" />
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path
                d="M21.257 10.962C21.731 11.582 21.731 12.419 21.257 13.038C19.764 14.987 16.182 19 12 19C7.818 19 4.236 14.987 2.743 13.038C2.51206 12.7413 2.38666 12.376 2.38666 12C2.38666 11.624 2.51206 11.2587 2.743 10.962C4.236 9.013 7.818 5 12 5C16.182 5 19.764 9.013 21.257 10.962Z"
                stroke="#9B9AA0" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
              <path
                d="M12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15Z"
                stroke="#9B9AA0" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <?php echo get_post_meta(get_the_ID(), 'post_views_count', true) ?: 0; ?>
          </span>
        </div>
        <?php if (get_the_modified_time('d.m.Y') != get_the_time('d.m.Y')) : ?>
          <div class="single__update">Обновлено: <span itemprop="dateModified" content="<?php the_modified_time('c'); ?>"><?php the_modified_time('d.m.Y'); ?></span></div>
        <?php endif; ?>
      </div>

      <?php if (has_post_thumbnail()) : ?>
        <img src="<?php the_post_thumbnail_url('large'); ?>" alt="<?php the_title_attribute(); ?>" itemprop="image">
      <?php endif; ?>
      <div itemprop="articleBody">
        <?php the_content(); ?>
      </div>
    </div>
  </div>
  <div class="custom">
    <div class="container">
      <div class="custom__working">
        <div class="custom__working-title">Над статьей работали</div>
        <div class="custom__working-wrap">
          <?php
          $author_id = get_the_author_meta('ID');
          $avatar_url = get_avatar_url($author_id, array('size' => 100));
          ?>
          <div class="custom__img" itemprop="author" itemscope itemtype="https://schema.org/Person">
            <img src="<?php echo $avatar_url; ?>" alt="<?php the_author(); ?>" itemprop="image">
            <div class="custom__working-wrapper">
              <span>Автор</span>
              <a href="<?php echo get_author_posts_url($author_id); ?>" itemprop="url">
                <p itemprop="name"><?php the_author(); ?></p>
              </a>
            </div>
          </div>
          <div class="custom__working-share">
            <span>Поделиться</span>
            <div class="share-buttons">
              <script src="https://yastatic.net/share2/share.js"></script>
              <div class="ya-share2" data-curtain data-size="l" data-services="vkontakte,odnoklassniki,telegram"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- Дополнительные метаданные для статьи -->
  <meta itemprop="publisher" itemscope itemtype="https://schema.org/Organization">
  <meta itemprop="name" content="<?php bloginfo('name'); ?>">
  </meta>
  <meta itemprop="mainEntityOfPage" content="<?php the_permalink(); ?>">
</article>
<script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "QAPage",
    "mainEntity": {
      "@type": "Question",
      "datePublished": "<?php echo get_the_date('c'); ?>",
      "name": "<?php the_title(); ?>",
      "text": "Какое время прочтения и когда была изменена статья?",
      "author": {
        "@type": "Person",
        "name": "<?php the_author(); ?>",
        "url": "<?php echo get_author_posts_url($author_id); ?>"
      },
      "acceptedAnswer": {
        "@type": "Answer",
        "author": {
          "@type": "Organization",
          "name": "Штраф.ком"
        },
        "text": "⌚ Время прочтения: <?php echo reading_time(); ?> минута. 👀 Данную статью уже прочли <?php echo get_post_meta(get_the_ID(), 'post_views_count', true) ?: 0; ?> раза. ✒️ Автор: <?php the_author(); ?>"
      },
      "answerCount": 1
    }
  }
</script>
<style>
  .qa-content {
    display: none;
  }
</style>