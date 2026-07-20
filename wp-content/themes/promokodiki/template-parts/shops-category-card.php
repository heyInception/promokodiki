<?php
/**
 * Шаблон для отображения категории магазина в результатах поиска
 * 
 * @package promokodiki
 */

// Ожидаем переменные: $category, $image_url, $promocode_count
if (!isset($category) || !is_object($category)) {
    return;
}

$image_url = isset($image_url) ? $image_url : '';
$promocode_count = isset($promocode_count) ? $promocode_count : 0;
?>

<div class="shops-category__item">
    <a href="<?php echo esc_url(get_term_link($category)); ?>" class="shops-category__link">
        <?php if ($image_url): ?>
            <div class="shops-category__image">
                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($category->name); ?>">
            </div>
        <?php endif; ?>
        <div class="shops-category__content">
            <h3 class="shops-category__title"><?php echo esc_html($category->name); ?></h3>
            <span class="shops-category__count"><?php echo $promocode_count; ?> промокодов</span>
        </div>
    </a>
</div>