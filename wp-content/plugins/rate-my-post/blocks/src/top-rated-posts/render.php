<?php
$number              = isset($attributes['number']) ? '  number="' . absint($attributes['number']) . '"' : '';
$minimum_rating      = isset($attributes['minimum_rating']) ? '  minimum_rating="' . floatval($attributes['minimum_rating']) . '"' : '';
$show_featured_image = isset($attributes['show_featured_image']) ? '  show_featured_image="' . $attributes['show_featured_image'] . '"' : '';
$show_star_rating    = isset($attributes['show_star_rating']) ? '  show_star_rating="' . $attributes['show_star_rating'] . '"' : '';
?>
<div <?php echo get_block_wrapper_attributes(); ?>>
    <?php echo do_shortcode(sprintf('[ratemypost-top-rated%s]', $number . $minimum_rating . $show_featured_image . $show_star_rating)) ?>
</div>
