<?php
if (!function_exists('new_sections')) {
  function new_sections($post_id)
  {
    while (have_rows('new_end', $post_id)) : the_row();
?>
      <section class="new">
        <div class="container">
          <div class="new__column">
            <div class="new__title">
              <h2><?php the_sub_field('zagolovok'); ?></h2>
            </div>
            <div class="new__items">
              <?php
              // Рекурсивная функция для вывода вложенных категорий
              function display_nested_categories($parent_id = 0, $level = 0)
              {
                $categories = get_terms(array(
                  'taxonomy' => 'promocode_category',
                  'orderby' => 'name',
                  'order' => 'ASC',
                  'hide_empty' => false,
                  'number' => 10,
                  'parent' => $parent_id,
                ));

                if ($categories && !is_wp_error($categories)) {
                  foreach ($categories as $category) {
                    // Формируем путь к изображению
                    $image_slug = sanitize_title($category->slug);
                    $image_path = get_template_directory() . '/img/categories/' . $image_slug . '.png';
                    $image_url = file_exists($image_path)
                      ? get_template_directory_uri() . '/img/categories/' . $image_slug . '.png'
                      : get_template_directory_uri() . '/img/categories/default.png';

                    // Класс для отступов в зависимости от уровня
                    $item_class = 'category-nested__item';
                    $item_class .= $level > 0 ? ' category-nested__item--level-' . $level : '';
              ?>

                    <a href="<?php echo get_term_link($category); ?>" class="new__item">
                      <img
                        src="<?php echo esc_url($image_url); ?>"
                        alt="<?php echo esc_attr($category->name); ?>"
                        loading="lazy"
                        width="60"
                        height="60">
                      <span><?php echo $category->name; ?></span>
                    </a>

              <?php
                  }
                }
              }

              // Выводим категории
              display_nested_categories();
              ?>
            </div>
          </div>
        </div>
      </section>
    <?php endwhile; ?>
  <?php } ?>
<?php } ?>
<?php
if (have_rows('new_end')) {
  new_sections(null); // Текущий пост
} else {
  new_sections('option'); // Пост с ID 23
}

?>