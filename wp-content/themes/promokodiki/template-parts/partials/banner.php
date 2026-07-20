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
                <a href="<?php echo esc_url($ssylkaz['url']); ?>" target="<?php echo esc_attr($ssylkaz['target']); ?>" class="banner__button btn-reset"><?php echo esc_html($ssylkaz['title']); ?></a>
              <?php endif; ?>
            </div>
            <div class="banner__items">
              <?php
              $popular_categories = get_terms([
                'taxonomy' => 'promocode_category',
                'orderby' => 'include',
                'order' => 'ASC',
                'number' => 4,
                'hide_empty' => false,
              ]);

              if (!empty($popular_categories) && !is_wp_error($popular_categories)) :
                $popular_colors = ['pink', 'blue', 'orange', 'yellow'];
                foreach ($popular_categories as $index => $category) :
                  $image_id = get_term_meta($category->term_id, 'category_image', true);
                  $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : get_template_directory_uri() . '/img/default-category.jpg';
              ?>
                  <a href="<?php echo esc_url(get_term_link($category)); ?>"
                    class="banner__item banner__item_<?php echo esc_attr($popular_colors[$index % count($popular_colors)]); ?>"
                    style="background-image: url(<?php echo esc_url(get_template_directory_uri() . '/img/banner-' . ($index + 1) . '.png'); ?>)">
                    <?php echo esc_html($category->name); ?>
                  </a>
                <?php endforeach;
              else : ?>
                <p>Нет доступных категорий</p>
              <?php endif; ?>
            </div>
            <a href="" class="banner__button btn-reset banner__button_m">В каталог</a>
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