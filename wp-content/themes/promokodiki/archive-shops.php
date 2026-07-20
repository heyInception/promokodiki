<?php

/**
 * Archive Template for Shops Post Type
 */

get_header();
?>

<section class="alphabetical">
  <div class="container">
    <div class="alphabetical__column">
      <div class="alphabetical__title">
        <h1><?php is_page() ? the_title() : post_type_archive_title(); ?></h1>
      </div>

      <div class="alphabetical__search">
        <form id="shops-search-form" action="<?php echo esc_url(home_url('/shops/')); ?>" method="get" class="form">
          <input type="text" id="shops-search-input" name="s" class="input-reset form__input" placeholder="Название категории" value="<?php echo get_search_query(); ?>" autocomplete="off">
          <button type="submit" id="shops-search-submit" class="btn-reset form__btn">
            <svg width="21" height="21" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path
                d="M20 20L15.514 15.506M18 9.5C18 11.7543 17.1045 13.9163 15.5104 15.5104C13.9163 17.1045 11.7543 18 9.5 18C7.24566 18 5.08365 17.1045 3.48959 15.5104C1.89553 13.9163 1 11.7543 1 9.5C1 7.24566 1.89553 5.08365 3.48959 3.48959C5.08365 1.89553 7.24566 1 9.5 1C11.7543 1 13.9163 1.89553 15.5104 3.48959C17.1045 5.08365 18 7.24566 18 9.5Z"
                stroke="#F682A5" stroke-width="2" stroke-linecap="round" />
            </svg>
          </button>
        </form>
      </div>

      <?php
      // Получаем все категории магазинов
      $categories = get_terms(array(
        'taxonomy' => 'shops_category',
        'hide_empty' => true,
        'orderby' => 'name',
        'order' => 'ASC'
      ));

      // Создаем массив первых букв категорий
      $first_letters = array();
      if (!empty($categories) && !is_wp_error($categories)) {
        foreach ($categories as $category) {
          $first_letter = mb_strtolower(mb_substr($category->name, 0, 1, 'UTF-8'), 'UTF-8');
          if (is_numeric($first_letter)) {
            $first_letters['0-9'] = true;
          } else {
            $first_letters[$first_letter] = true;
          }
        }
      }
      ?>

      <div class="alphabetical__index">
        <div class="alphabetical__index-wrap">
          <a href="#0-9" class="alphabetical__index-item alphabetical__index-item_mr <?php echo !isset($first_letters['0-9']) ? 'alphabetical__index-item_not' : ''; ?>">0-9</a>
          <?php foreach (range('a', 'z') as $letter): ?>
            <a href="#<?php echo $letter; ?>" class="alphabetical__index-item <?php echo !isset($first_letters[$letter]) ? 'alphabetical__index-item_not' : ''; ?>"><?php echo $letter; ?></a>
          <?php endforeach; ?>
        </div>
        <div class="alphabetical__index-wrap">
          <?php
          $cyrillic_letters = array('а', 'б', 'в', 'г', 'д', 'е', 'ж', 'з', 'и', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'э', 'ю', 'я', 'ё');
          foreach ($cyrillic_letters as $letter): ?>
            <a href="#<?php echo $letter; ?>" class="alphabetical__index-item <?php echo !isset($first_letters[$letter]) ? 'alphabetical__index-item_not' : ''; ?>"><?php echo $letter; ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="alphabetical__lists" id="shops-list-container">
        <?php
        // Получаем все категории магазинов
        $categories = get_terms(array(
          'taxonomy' => 'shops_category',
          'hide_empty' => true,
          'orderby' => 'name',
          'order' => 'ASC'
        ));

        if (!empty($categories) && !is_wp_error($categories)) {
          // Группируем по первой букве
          $grouped_categories = array();
          foreach ($categories as $category) {
            $first_letter = mb_strtolower(mb_substr($category->name, 0, 1, 'UTF-8'), 'UTF-8');

            if (is_numeric($first_letter)) {
              $grouped_categories['0-9'][] = $category;
            } elseif (preg_match('/[a-z]/', $first_letter)) {
              $grouped_categories[$first_letter][] = $category;
            } else {
              $grouped_categories[$first_letter][] = $category;
            }
          }

          // Сначала цифры
          if (isset($grouped_categories['0-9'])): ?>
            <div class="alphabetical__list" data-letter-group="0-9">
              <div id="0-9" class="alphabetical__name">0-9</div>
              <div class="alphabetical__list-wrap">
                <?php foreach ($grouped_categories['0-9'] as $category): ?>
                  <a href="<?php echo get_term_link($category); ?>" class="alphabetical__list-item" data-category-name="<?php echo mb_strtolower($category->name, 'UTF-8'); ?>">
                    <?php echo $category->name; ?>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif;

          // Затем латинские буквы
          foreach (range('a', 'z') as $letter):
            if (isset($grouped_categories[$letter])): ?>
              <div class="alphabetical__list" data-letter-group="<?php echo $letter; ?>">
                <div id="<?php echo $letter; ?>" class="alphabetical__name"><?php echo strtoupper($letter); ?></div>
                <div class="alphabetical__list-wrap">
                  <?php foreach ($grouped_categories[$letter] as $category): ?>
                    <a href="<?php echo get_term_link($category); ?>" class="alphabetical__list-item" data-category-name="<?php echo mb_strtolower($category->name, 'UTF-8'); ?>">
                      <?php echo $category->name; ?>
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif;
          endforeach;

          // Затем кириллические буквы
          foreach ($cyrillic_letters as $letter):
            if (isset($grouped_categories[$letter])): ?>
              <div class="alphabetical__list" data-letter-group="<?php echo $letter; ?>">
                <div id="<?php echo $letter; ?>" class="alphabetical__name"><?php echo mb_strtoupper($letter, 'UTF-8'); ?></div>
                <div class="alphabetical__list-wrap">
                  <?php foreach ($grouped_categories[$letter] as $category): ?>
                    <a href="<?php echo get_term_link($category); ?>" class="alphabetical__list-item" data-category-name="<?php echo mb_strtolower($category->name, 'UTF-8'); ?>">
                      <?php echo $category->name; ?>
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>
        <?php endif;
          endforeach;
        } else {
          echo '<p>Категории магазинов не найдены</p>';
        }
        ?>
      </div>
    </div>
  </div>
</section>

<script>
  jQuery(document).ready(function($) {
    var $searchInput = $('#shops-search-input');
    var $searchForm = $('#shops-search-form');
    var $shopsContainer = $('#shops-list-container');
    var $categoryItems = $('.alphabetical__list-item');
    var $letterGroups = $('.alphabetical__list');

    // Функция для фильтрации категорий
    function filterCategories(searchTerm) {
      if (searchTerm.length >= 2) {
        $categoryItems.each(function() {
          var categoryName = $(this).data('category-name') || '';
          if (typeof categoryName === 'string' && categoryName.indexOf(searchTerm.toLowerCase()) !== -1) {
            $(this).show();
            $(this).closest('.alphabetical__list').show();
          } else {
            $(this).hide();
          }
        });

        // Скрываем пустые группы
        $letterGroups.each(function() {
          var $group = $(this);
          if ($group.find('.alphabetical__list-item:visible').length === 0) {
            $group.hide();
          } else {
            $group.show();
          }
        });
      } else {
        // Показываем все, если меньше 3 символов
        $categoryItems.show();
        $letterGroups.show();
      }
    }

    // Обработка ввода в поиск
    $searchInput.on('input', function() {
      var searchTerm = $(this).val().trim();
      filterCategories(searchTerm);
    });

    // Обработка отправки формы (якорная навигация)
    $searchForm.on('submit', function(e) {
      e.preventDefault();
      var searchTerm = $searchInput.val().trim();

      if (searchTerm.length >= 2) {
        // Находим первую подходящую категорию и скроллим к ней
        var $firstMatch = $('.alphabetical__list-item').filter(function() {
          var categoryName = $(this).data('category-name') || '';
          return typeof categoryName === 'string' && categoryName.indexOf(searchTerm.toLowerCase()) !== -1;
        }).first();

        if ($firstMatch.length) {
          $('html, body').animate({
            scrollTop: $firstMatch.offset().top - 100
          }, 500);
        }
      } else {
        // Если меньше 3 символов, скроллим к началу списка
        $('html, body').animate({
          scrollTop: $shopsContainer.offset().top - 100
        }, 500);
      }
    });
  });
</script>


<?php get_footer(); ?>
