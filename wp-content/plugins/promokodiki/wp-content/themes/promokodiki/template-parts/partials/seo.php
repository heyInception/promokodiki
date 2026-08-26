<?php
if (!function_exists('seo_sections')) {
  function seo_sections($post_id)
  {
    while (have_rows('seo_end', $post_id)) : the_row();
?>
      <section class="seo">
        <div class="container">
          <div class="seo__row">
            <div class="seo__column">
              <?php if (get_sub_field('zagolovok')) : ?>
                <div class="seo__title">
                  <h2><?php the_sub_field('zagolovok'); ?></h2>
                </div>
              <?php endif; ?>
              <div class="seo__content">
                <div class="content_block hide">
                  <?php the_content(); ?>
                </div>
                <button class="content_toggle btn-reset button-blue">Показать всё</button>
              </div>
            </div>
            <div class="seo__img">
              <img src="<?php echo get_template_directory_uri(); ?>/img/seo-img.png" alt="">
            </div>
          </div>
        </div>
      </section>
    <?php endwhile; ?>
  <?php } ?>
<?php } ?>
<?php
if (have_rows('seo_end')) {
  seo_sections(null); // Текущий пост
} else {
  seo_sections('option'); // Пост с ID 23
}

?>