<?php

/**
 * Шаблон архива промокодов (archive-promocode.php)
 */
get_header(); ?>

<main class="archive-promocode">
  <!-- Секция популярных категорий -->
  <section class="popular">
    <div class="container">
      <div class="popular__column">
        <div class="popular__title">
          <h2>Популярные категории</h2>
        </div>
        <div class="popular__items">
          <?php
          $popular_categories = get_terms(array(
            'taxonomy' => 'promocode_category',
            'orderby'  => 'include',
            'order' => 'DESC',
            'include'       => array(600, 622, 687, 693),
            'number' => 4,
            'hide_empty' => false,
          ));

          $popular_colors = ['pink', 'blue', 'orange', 'yellow'];
          $color_index = 0;

          if ($popular_categories && !is_wp_error($popular_categories)) :
            foreach ($popular_categories as $category) :
              $image_id = get_term_meta($category->term_id, 'category_image', true);
              $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : get_template_directory_uri() . '/img/default-category.jpg';
          ?>
              <a href="<?php echo get_term_link($category); ?>"
                class="banner__item banner__item_<?php echo $popular_colors[$color_index % count($popular_colors)]; ?>"
                style="background-image: url(<?php echo get_template_directory_uri(); ?>/img/banner-<?php echo $color_index + 1; ?>.png)">
                <?php echo $category->name; ?>
              </a>
          <?php
              $color_index++;
            endforeach;
          endif;
          ?>
        </div>
      </div>
    </div>
  </section>

  <!-- Секция всех категорий -->
  <!-- <section class="category">
    <div class="container">
      <div class="category__column">
        <div class="category__title">
          <h2>Категории</h2>
        </div>
        <div class="category__items">
          <?php
          $all_categories = get_terms(array(
            'taxonomy' => 'promocode_category',
            'orderby' => 'id',
            'order' => 'ASC',
            'hide_empty' => false,
          ));

          if ($all_categories && !is_wp_error($all_categories)) :
            foreach ($all_categories as $category) :
              // Формируем путь к изображению по slug (например, /img/categories/food.png)
              $image_slug = sanitize_title($category->slug);
              $image_path = get_template_directory() . '/img/categories/' . $image_slug . '.png';
              $image_url = file_exists($image_path)
                ? get_template_directory_uri() . '/img/categories/' . $image_slug . '.png'
                : get_template_directory_uri() . '/img/categories/default.png'; // Запасное изображение
          ?>
              <a href="<?php echo get_term_link($category); ?>" class="category__item">
                <img
                  src="<?php echo esc_url($image_url); ?>"
                  alt="<?php echo esc_attr($category->name); ?>"
                  loading="lazy">
                <span><?php echo $category->name; ?></span>
              </a>
          <?php endforeach;
          endif;
          ?>
        </div>
      </div>
    </div>
  </section> -->
  <section class="category">
    <div class="container">
      <div class="category__column">
        <div class="category__title">
          <h2>Категории</h2>
        </div>

        <ul class="category__items">
          <?php
          // Рекурсивная функция для вывода вложенных категорий
          function display_nested_categories($parent_id = 0, $level = 0)
          {
            $categories = get_terms(array(
              'taxonomy' => 'promocode_category',
              'orderby' => 'name',
              'order' => 'ASC',
              'hide_empty' => false,
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

                <div class="<?php echo $item_class; ?>">
                    <a href="<?php echo get_term_link($category); ?>" class="category__item">
                       <img
                      src="<?php echo esc_url($image_url); ?>"
                      alt="<?php echo esc_attr($category->name); ?>"
                      class="category-nested__icon"
                      loading="lazy">
                      <?php echo $category->name; ?>
                    </a>

                    
                </div>

          <?php
              }
            }
          }

          // Выводим категории
          display_nested_categories();
          ?>
        </ul>
      </div>
    </div>
  </section>

  <!-- <script>
    // JavaScript для раскрытия/скрытия подкатегорий
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.category-nested__toggle').forEach(function(toggle) {
        toggle.addEventListener('click', function() {
          const categoryId = this.getAttribute('data-category-id');
          const childrenList = document.getElementById('children-' + categoryId);
          const icon = this.querySelector('.toggle-icon');

          if (childrenList.style.display === 'none' || childrenList.style.display === '') {
            childrenList.style.display = 'block';
            icon.textContent = '▼';
            this.setAttribute('aria-expanded', 'true');
          } else {
            childrenList.style.display = 'none';
            icon.textContent = '▶';
            this.setAttribute('aria-expanded', 'false');
          }
        });
      });
    });
  </script> -->
</main>

<?php get_footer(); ?>