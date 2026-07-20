<?php
if (!function_exists('top_sections')) {
  function top_sections($post_id)
  {
    while (have_rows('top_promokodov_end', $post_id)) : the_row();
?>
      <section class="top">
        <div class="container">
          <div class="top__row">
            <div class="top__title top__title_m">
              <h2><?php the_sub_field('zagolovok'); ?></h2>
            </div>
            <div class="top__banner">
              <div class="top__banner-title">Обновляем купоны каждые 3 часа</div>
              <div class="top__banner-img">
                <img src="<?php echo get_template_directory_uri(); ?>/img/top-banner-tg.png" alt="">
              </div>
              <div class="top__banner-text">осталось</div>
              <div class="top__banner-countdown">
                <ul class="list-reset">
                  <li><span id="topHours"></span>часа</li>
                  <li><span id="topMinutes"></span>мин</li>
                  <li><span id="topSeconds"></span>сек</li>
                </ul>
              </div>
            </div>
            <div class="top__column">
              <div class="top__title">
                <h2>Топ промокодов из Telegram</h2>
              </div>
              <div class="top__items" id="popular-promocodes-container">
                <?php
                $promocode_ids = get_popular_promocodes();
                if (!empty($promocode_ids)) {
                  display_promocodes_items($promocode_ids);
                } else {
                  echo '<div class="top__item"><div class="top__head">Нет активных промокодов</div></div>';
                }
                ?>
              </div>
            </div>
          </div>
        </div>
      </section>
    <?php endwhile; ?>
  <?php } ?>
<?php } ?>
<?php
if (have_rows('top_promokodov_end')) {
  top_sections(null); // Текущий пост
} else {
  top_sections('option'); // Пост с ID 23
}

?>