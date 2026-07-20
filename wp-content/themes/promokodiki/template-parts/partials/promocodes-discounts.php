<section class="promocodes">
  <div class="container">
    <div class="promocodes__row">
      <div class="promocodes__column">
        <div class="tabs" data-tabs="discounts">
          <ul class="list-reset tabs__nav">
            <li class="tabs__nav-item"><button class="btn-reset tabs__nav-btn" type="button" data-tab="top">Топ</button></li>
            <li class="tabs__nav-item"><button class="btn-reset tabs__nav-btn" type="button" data-tab="new">Новинки</button></li>
            <li class="tabs__nav-item"><button class="btn-reset tabs__nav-btn" type="button" data-tab="discussed">Обсуждаемое</button></li>
          </ul>

          <div class="tabs__content">

            <!-- Топ -->
            <div class="tabs__panel" data-tab="top">
              <div class="promocodes__items">
                <?php
                $args = array(
                  'post_type'      => 'promocode',
                  'posts_per_page' => 6,
                  'meta_key'       => '_promocode_used_count',
                  'orderby'        => 'meta_value_num',
                  'order'          => 'DESC',
                  'meta_query'     => array(
                    array(
                      'key'     => '_promocode_expiry_date',
                      'value'   => current_time('mysql'),
                      'compare' => '>=',
                      'type'    => 'DATETIME',
                    )
                  )
                );

                $query = new WP_Query($args);

                if ($query->have_posts()) :
                  while ($query->have_posts()) : $query->the_post();
                    get_template_part('template-parts/promocode-card'); // ⚡ у тебя именно так называется
                  endwhile;
                  wp_reset_postdata();
                else :
                  echo '<p class="no-promocodes">Нет популярных промокодов.</p>';
                endif;
                ?>
              </div>
            </div>

            <!-- Новинки -->
            <div class="tabs__panel" data-tab="new">
              <div class="promocodes__items">
                <?php
                $args = array(
                  'post_type'      => 'promocode',
                  'posts_per_page' => 6,
                  'orderby'        => 'date',
                  'order'          => 'DESC',
                  'meta_query'     => array(
                    array(
                      'key'     => '_promocode_expiry_date',
                      'value'   => current_time('mysql'),
                      'compare' => '>=',
                      'type'    => 'DATETIME',
                    )
                  )
                );

                $query = new WP_Query($args);

                if ($query->have_posts()) :
                  while ($query->have_posts()) : $query->the_post();
                    get_template_part('template-parts/promocode-card');
                  endwhile;
                  wp_reset_postdata();
                endif;
                ?>
              </div>
            </div>

            <!-- Обсуждаемое -->
            <div class="tabs__panel" data-tab="discussed">
              <div class="promocodes__items">
                <?php
                // Обсуждаемое = сортировка по лайкам+дизлайкам
                // Лучше сделать отдельное поле _promocode_votes_total
                $args = array(
                  'post_type'      => 'promocode',
                  'posts_per_page' => 6,
                  'meta_key'       => '_promocode_likes', // или votes_total если заведёшь
                  'orderby'        => 'meta_value_num',
                  'order'          => 'DESC',
                  'meta_query'     => array(
                    array(
                      'key'     => '_promocode_expiry_date',
                      'value'   => current_time('mysql'),
                      'compare' => '>=',
                      'type'    => 'DATETIME',
                    )
                  )
                );

                $query = new WP_Query($args);

                if ($query->have_posts()) :
                  while ($query->have_posts()) : $query->the_post();
                    get_template_part('template-parts/promocode-card');
                  endwhile;
                  wp_reset_postdata();
                else :
                  echo '<p class="no-promocodes">Нет обсуждаемых промокодов.</p>';
                endif;
                ?>
              </div>
            </div>

          </div>
        </div>
      </div>


      
    </div>
  </div>
</section>