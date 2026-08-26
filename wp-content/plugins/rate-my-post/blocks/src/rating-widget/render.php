<div <?php echo get_block_wrapper_attributes(); ?>>
    <?php echo do_shortcode(sprintf('[ratemypost%s]', $attributes['id'] ? '  id="' . $attributes['id'] . '"' : '')) ?>
</div>