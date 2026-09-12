<?php
if (!function_exists('banner_sections')) {
  function banner_sections($post_id)
  {
    while (have_rows('pervyj_ekran_end', $post_id)) : the_row();
?>
      <section class="banner">
        <div class="container">
          <div class="banner__row">
            <div class="banner__column">
              <div class="banner__title">
                <h2><?php the_sub_field('zagolovok'); ?></h2>
              </div>
              <?php $ssylkaz = get_sub_field('ssylkaz'); ?>
              <?php if ($ssylkaz) : ?>
                <a href="<?php echo esc_url($ssylkaz['url']); ?>" target="<?php echo esc_attr($ssylkaz['target']); ?>" class="banner__button btn-reset ui-button ui-button--pink"><?php echo esc_html($ssylkaz['title']); ?></a>
              <?php endif; ?>
            </div>
            <div class="banner__items">
              <?php
              $category_cards = [
                'zdorove-i-krasota' => [
                  'color' => 'pink',
                  'image' => 1,
                ],
                'elektronika' => [
                  'color' => 'blue',
                  'image' => 2,
                ],
                'moda' => [
                  'color' => 'orange',
                  'image' => 3,
                ],
                'produkty-pitaniya-i-bytovaya-himiya' => [
                  'color' => 'yellow',
                  'image' => 4,
                ],
              ];

              $popular_categories = get_terms([
                'taxonomy' => 'promocode_category',
                'slug' => array_keys($category_cards),
                'orderby' => 'none',
                'hide_empty' => false,
              ]);

              if (!empty($popular_categories) && !is_wp_error($popular_categories)) :
                $categories_by_slug = [];

                foreach ($popular_categories as $category) {
                  $categories_by_slug[$category->slug] = $category;
                }

                foreach ($category_cards as $slug => $card) :
                  if (empty($categories_by_slug[$slug])) {
                    continue;
                  }

                  $category = $categories_by_slug[$slug];
              ?>
                  <a href="<?php echo esc_url(get_term_link($category)); ?>"
                    class="banner__item banner__item_<?php echo esc_attr($card['color']); ?>"
                    style="background-image: url(<?php echo esc_url(get_template_directory_uri() . '/img/banner-' . $card['image'] . '.png'); ?>)">
                    <?php echo esc_html($category->name); ?>
                  </a>
                <?php endforeach;
              else : ?>
                <p>Нет доступных категорий</p>
              <?php endif; ?>
            </div>
            <a href="<?php echo esc_url( get_post_type_archive_link( 'promocode' ) ); ?>" class="banner__button btn-reset ui-button ui-button--pink banner__button_m">В каталог</a>
          </div>
        </div>
      </section>
    <?php endwhile; ?>
  <?php } ?>
<?php } ?>
<?php
if (have_rows('pervyj_ekran_end')) {
  banner_sections(null); // Текущий пост
} else {
  banner_sections('option'); // Пост с ID 23
}

?>
