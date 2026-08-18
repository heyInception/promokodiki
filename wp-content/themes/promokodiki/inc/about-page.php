<?php
/**
 * About page fields and rendering helpers.
 *
 * @package promokodiki
 */

function promokodiki_get_about_page_data( $post_id ) {
	$data = array(
		'title'        => function_exists( 'get_the_title' ) ? get_the_title( $post_id ) : '',
		'introduction' => '<p>Promokodiki помогает находить актуальные промокоды, скидки и выгодные предложения для ежедневных покупок.</p>',
		'hero_image'   => array(),
		'stats'        => array( array( 'value' => '10 000+', 'label' => 'предложений на сайте' ), array( 'value' => '1 000+', 'label' => 'проверенных магазинов' ), array( 'value' => 'Каждый день', 'label' => 'обновляем промокоды' ) ),
		'how_title'    => 'Как это работает',
		'how_steps'    => array( array( 'title' => 'Выберите магазин', 'description' => 'Найдите любимый магазин или нужную категорию.' ), array( 'title' => 'Откройте предложение', 'description' => 'Посмотрите условия скидки и скопируйте промокод.' ), array( 'title' => 'Экономьте на покупке', 'description' => 'Перейдите в магазин и примените код в корзине.' ) ),
		'values_title' => 'Почему Promokodiki',
		'values'       => array( array( 'title' => 'Проверяем предложения', 'description' => 'Отслеживаем сроки действия и обновляем информацию.' ), array( 'title' => 'Собираем лучшее', 'description' => 'Помогаем быстро сравнить акции и выбрать подходящую.' ), array( 'title' => 'Экономим ваше время', 'description' => 'Все условия собраны в одном понятном месте.' ) ),
		'cta_title'    => 'Начните экономить сегодня',
		'cta_text'     => 'Выберите магазин и найдите подходящую скидку.',
		'cta_label'    => 'Все магазины',
		'cta_url'      => '/shops/',
	);

	if ( ! function_exists( 'get_field' ) ) {
		return $data;
	}

	foreach ( array_keys( $data ) as $field_name ) {
		if ( 'title' === $field_name ) {
			continue;
		}

		$field_value = get_field( 'about_page_' . $field_name, $post_id );
		if ( false !== $field_value && null !== $field_value && '' !== $field_value ) {
			$data[ $field_name ] = $field_value;
		}
	}

	return $data;
}

function promokodiki_render_about_page( $data ) {
	$data = is_array( $data ) ? $data : array();
	$title = ! empty( $data['title'] ) ? $data['title'] : __( 'О нас', 'promokodiki' );
	$stats = ! empty( $data['stats'] ) && is_array( $data['stats'] ) ? $data['stats'] : array();
	$how_steps = ! empty( $data['how_steps'] ) && is_array( $data['how_steps'] ) ? $data['how_steps'] : array();
	$values = ! empty( $data['values'] ) && is_array( $data['values'] ) ? $data['values'] : array();
	$hero_image = ! empty( $data['hero_image']['url'] ) ? $data['hero_image'] : array();

	ob_start();
	?>
	<section class="about-page">
		<div class="container">
			<header class="about-page__hero">
				<div class="about-page__hero-content">
					<h1><?php echo esc_html( $title ); ?></h1>
					<?php if ( ! empty( $data['introduction'] ) ) : ?>
						<div class="about-page__introduction"><?php echo wp_kses_post( $data['introduction'] ); ?></div>
					<?php endif; ?>
				</div>
				<?php if ( $hero_image ) : ?>
					<img class="about-page__hero-image" src="<?php echo esc_url( $hero_image['url'] ); ?>" alt="<?php echo esc_attr( ! empty( $hero_image['alt'] ) ? $hero_image['alt'] : $title ); ?>"<?php echo ! empty( $hero_image['width'] ) ? ' width="' . esc_attr( $hero_image['width'] ) . '"' : ''; ?><?php echo ! empty( $hero_image['height'] ) ? ' height="' . esc_attr( $hero_image['height'] ) . '"' : ''; ?>>
				<?php endif; ?>
			</header>

			<?php if ( $stats ) : ?>
				<ul class="about-page__stats">
					<?php foreach ( $stats as $stat ) : ?>
						<?php if ( empty( $stat['value'] ) || empty( $stat['label'] ) ) { continue; } ?>
						<li class="about-page__stat"><span class="about-page__stat-value"><?php echo esc_html( $stat['value'] ); ?></span><span class="about-page__stat-label"><?php echo esc_html( $stat['label'] ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( ! empty( $data['how_title'] ) && $how_steps ) : ?>
				<section class="about-page__section about-page__how">
					<h2><?php echo esc_html( $data['how_title'] ); ?></h2>
					<ol class="about-page__cards about-page__steps">
						<?php foreach ( $how_steps as $index => $step ) : ?>
							<?php if ( empty( $step['title'] ) ) { continue; } ?>
							<li class="about-page__card"><span class="about-page__step-number"><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span><h3><?php echo esc_html( $step['title'] ); ?></h3><?php if ( ! empty( $step['description'] ) ) : ?><p><?php echo esc_html( $step['description'] ); ?></p><?php endif; ?></li>
						<?php endforeach; ?>
					</ol>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $data['values_title'] ) && $values ) : ?>
				<section class="about-page__section about-page__values">
					<h2><?php echo esc_html( $data['values_title'] ); ?></h2>
					<ul class="about-page__cards">
						<?php foreach ( $values as $value ) : ?>
							<?php if ( empty( $value['title'] ) ) { continue; } ?>
							<li class="about-page__card"><h3><?php echo esc_html( $value['title'] ); ?></h3><?php if ( ! empty( $value['description'] ) ) : ?><p><?php echo esc_html( $value['description'] ); ?></p><?php endif; ?></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $data['cta_title'] ) && ! empty( $data['cta_label'] ) && ! empty( $data['cta_url'] ) ) : ?>
				<section class="about-page__cta">
					<h2><?php echo esc_html( $data['cta_title'] ); ?></h2>
					<?php if ( ! empty( $data['cta_text'] ) ) : ?><p><?php echo esc_html( $data['cta_text'] ); ?></p><?php endif; ?>
					<a class="about-page__cta-link" href="<?php echo esc_url( $data['cta_url'] ); ?>"><?php echo esc_html( $data['cta_label'] ); ?></a>
				</section>
			<?php endif; ?>
		</div>
	</section>
	<?php

	return ob_get_clean();
}

function promokodiki_register_about_page_fields() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
		'key' => 'group_promokodiki_about_page', 'title' => __( 'Содержимое страницы «О нас»', 'promokodiki' ),
		'fields' => array(
			array( 'key' => 'field_about_introduction', 'label' => __( 'Вступление', 'promokodiki' ), 'name' => 'about_page_introduction', 'type' => 'wysiwyg' ),
			array( 'key' => 'field_about_hero_image', 'label' => __( 'Изображение в первом экране', 'promokodiki' ), 'name' => 'about_page_hero_image', 'type' => 'image', 'return_format' => 'array' ),
			array( 'key' => 'field_about_stats', 'label' => __( 'Статистика', 'promokodiki' ), 'name' => 'about_page_stats', 'type' => 'repeater', 'layout' => 'table', 'sub_fields' => array( array( 'key' => 'field_about_stat_value', 'label' => __( 'Значение', 'promokodiki' ), 'name' => 'value', 'type' => 'text', 'required' => 1 ), array( 'key' => 'field_about_stat_label', 'label' => __( 'Подпись', 'promokodiki' ), 'name' => 'label', 'type' => 'text', 'required' => 1 ) ) ),
			array( 'key' => 'field_about_how_title', 'label' => __( 'Заголовок блока «Как это работает»', 'promokodiki' ), 'name' => 'about_page_how_title', 'type' => 'text' ),
			array( 'key' => 'field_about_how_steps', 'label' => __( 'Шаги', 'promokodiki' ), 'name' => 'about_page_how_steps', 'type' => 'repeater', 'layout' => 'block', 'sub_fields' => array( array( 'key' => 'field_about_step_title', 'label' => __( 'Заголовок', 'promokodiki' ), 'name' => 'title', 'type' => 'text', 'required' => 1 ), array( 'key' => 'field_about_step_description', 'label' => __( 'Описание', 'promokodiki' ), 'name' => 'description', 'type' => 'textarea' ) ) ),
			array( 'key' => 'field_about_values_title', 'label' => __( 'Заголовок блока преимуществ', 'promokodiki' ), 'name' => 'about_page_values_title', 'type' => 'text' ),
			array( 'key' => 'field_about_values', 'label' => __( 'Преимущества', 'promokodiki' ), 'name' => 'about_page_values', 'type' => 'repeater', 'layout' => 'block', 'sub_fields' => array( array( 'key' => 'field_about_value_title', 'label' => __( 'Заголовок', 'promokodiki' ), 'name' => 'title', 'type' => 'text', 'required' => 1 ), array( 'key' => 'field_about_value_description', 'label' => __( 'Описание', 'promokodiki' ), 'name' => 'description', 'type' => 'textarea' ) ) ),
			array( 'key' => 'field_about_cta_title', 'label' => __( 'Заголовок CTA', 'promokodiki' ), 'name' => 'about_page_cta_title', 'type' => 'text' ),
			array( 'key' => 'field_about_cta_text', 'label' => __( 'Текст CTA', 'promokodiki' ), 'name' => 'about_page_cta_text', 'type' => 'textarea' ),
			array( 'key' => 'field_about_cta_label', 'label' => __( 'Текст кнопки CTA', 'promokodiki' ), 'name' => 'about_page_cta_label', 'type' => 'text' ),
			array( 'key' => 'field_about_cta_url', 'label' => __( 'Ссылка CTA', 'promokodiki' ), 'name' => 'about_page_cta_url', 'type' => 'url' ),
		),
		'location' => array( array( array( 'param' => 'page_template', 'operator' => '==', 'value' => 'page-about.php' ) ) ),
	) );
}
add_action( 'acf/init', 'promokodiki_register_about_page_fields' );
