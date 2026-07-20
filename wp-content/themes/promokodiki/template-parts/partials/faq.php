<?php
if (!function_exists('faq_sections')) {
  function faq_sections($post_id = null)
  {
    // для таксономий
    if (is_tax() || is_category() || is_tag()) {
      $term = get_queried_object();

      if (have_rows('faq_end', $term)) :
        while (have_rows('faq_end', $term)) : the_row(); ?>

          <section class="faq" itemscope itemtype="https://schema.org/FAQPage">
            <div class="faq__row faq__row_promo">
              <div class="faq__wrap">
                <div class="faq__title faq__title_center">
                  <?php if (get_sub_field('zagolovok_faq')) : ?>
                    <?php the_sub_field('zagolovok_faq'); ?>
                  <?php else: ?>
                    <h2 itemprop="name">Частые вопросы "<?php echo esc_html($term->name); ?>"</h2>
                  <?php endif; ?>
                </div>
              </div>

              <?php if (have_rows('faq')) : ?>
                <div class="faq__items faq__items_promo">
                  <?php while (have_rows('faq')) : the_row(); ?>
                    <div class="faq__item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                      <div class="faq__head" itemprop="name"><?php the_sub_field('zagolovok'); ?></div>
                      <div class="faq__content" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                        <div itemprop="text">
                          <p><?php the_sub_field('tekst'); ?></p>
                        </div>
                      </div>
                    </div>
                  <?php endwhile; ?>
                </div>
              <?php endif; ?>
            </div>
          </section>

        <?php endwhile;
      endif;

      // для постов
    } elseif (have_rows('faq_end', $post_id)) {
      while (have_rows('faq_end', $post_id)) : the_row(); ?>

        <section class="faq" itemscope itemtype="https://schema.org/FAQPage">
          <div class="container">
            <div class="faq__row">
              <div class="faq__wrap">
                <div class="faq__title">
                  <h2 itemprop="name"><?php the_sub_field('zagolovok_faq'); ?></h2>
                </div>
                <img src="<?php echo get_template_directory_uri(); ?>/img/faq.png" alt="">
              </div>

              <?php if (have_rows('faq')) : ?>
                <div class="faq__items">
                  <?php while (have_rows('faq')) : the_row(); ?>
                    <div class="faq__item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                      <div class="faq__head" itemprop="name"><?php the_sub_field('zagolovok'); ?></div>
                      <div class="faq__content" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                        <div itemprop="text">
                          <p><?php the_sub_field('tekst'); ?></p>
                        </div>
                      </div>
                    </div>
                  <?php endwhile; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </section>

<?php endwhile;
    }
  }
}

// вызов
if (is_tax() || is_category() || is_tag()) {
  $term = get_queried_object();
  faq_sections($term); // ACF для таксономий, с кастомным HTML
} elseif (have_rows('faq_end')) {
  faq_sections(get_the_ID()); // ACF для поста
} else {
  faq_sections('option'); // fallback, например опции
}
?>