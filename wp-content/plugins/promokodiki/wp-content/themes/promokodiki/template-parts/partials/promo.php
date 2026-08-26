<div class="promo">
  <div class="promo__img promo__img_left" data-sale="sale">
    <img src="<?php echo get_template_directory_uri(); ?>/img/sale.svg" alt="">
  </div>
  <div class="container">
    <div class="promo__row">
      <div class="promo__content">
        <img src="<?php echo get_template_directory_uri(); ?>/img/promo-fire.png" alt="">
        <div class="promo__last">Последнее</div>
        <?php
        $latest_promo = new WP_Query(array(
          'post_type' => 'promocode',
          'posts_per_page' => 1,
          'orderby' => 'date',
          'order' => 'DESC',
        ));

        if ($latest_promo->have_posts()) :
          while ($latest_promo->have_posts()) : $latest_promo->the_post();
            $post_date = get_the_date('U'); // Дата публикации в Unix-формате
            $current_time = current_time('U'); // Текущее время в Unix-формате
            $time_diff = human_time_diff($post_date, $current_time); // Разница в читаемом формате (например, "5 мин. назад")
        ?>
            <div class="promo__text">
              <span>
                <?php the_title(); ?> – добавлена <?php echo $time_diff; ?> назад
              </span>
            </div>
        <?php endwhile;
          wp_reset_postdata();
        endif;
        ?>
      </div>
    </div>
  </div>
  <div class="promo__img promo__img_right" data-sale="sale">
    <img src="<?php echo get_template_directory_uri(); ?>/img/sale.svg" alt="">
  </div>
</div>