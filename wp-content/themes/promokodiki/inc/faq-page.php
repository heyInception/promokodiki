<?php
/**
 * FAQ page fields and rendering helpers.
 *
 * @package promokodiki
 */

function promokodiki_get_default_faq_sections() {
	return array(
		array(
			'title' => __( 'Промокоды и скидки', 'promokodiki' ),
			'items' => array(
				array(
					'question' => __( 'Что такое промокод?', 'promokodiki' ),
					'answer'   => '<p>' . esc_html__( 'Промокод — это специальный код, который даёт скидку, подарок или другое преимущество при оформлении заказа в магазине.', 'promokodiki' ) . '</p>',
				),
				array(
					'question' => __( 'Как применить промокод?', 'promokodiki' ),
					'answer'   => '<p>' . esc_html__( 'Выберите подходящее предложение, скопируйте код и вставьте его в поле для промокода на сайте магазина до оплаты заказа.', 'promokodiki' ) . '</p>',
				),
				array(
					'question' => __( 'Почему промокод может не сработать?', 'promokodiki' ),
					'answer'   => '<p>' . esc_html__( 'Проверьте срок действия, условия акции, минимальную сумму заказа и список товаров-исключений. Некоторые коды действуют только для новых покупателей.', 'promokodiki' ) . '</p>',
				),
			),
		),
		array(
			'title' => __( 'Работа с Промокодиками', 'promokodiki' ),
			'items' => array(
				array(
					'question' => __( 'Нужно ли регистрироваться на сайте?', 'promokodiki' ),
					'answer'   => '<p>' . esc_html__( 'Нет. Все промокоды и скидки доступны бесплатно — выберите магазин и воспользуйтесь подходящим предложением.', 'promokodiki' ) . '</p>',
				),
				array(
					'question' => __( 'Как вы проверяете предложения?', 'promokodiki' ),
					'answer'   => '<p>' . esc_html__( 'Мы регулярно обновляем каталог и отмечаем актуальные предложения. При этом условия акции на стороне магазина могут меняться, поэтому советуем всегда проверять их перед покупкой.', 'promokodiki' ) . '</p>',
				),
			),
		),
	);
}

function promokodiki_get_faq_page_data( $post_id ) {
	$data = array(
		'title'        => get_the_title( $post_id ),
		'introduction' => '',
		'cta_title'    => __( 'Не нашли ответ?', 'promokodiki' ),
		'cta_text'     => __( 'Напишите нам — мы постараемся помочь.', 'promokodiki' ),
		'cta_url'      => home_url( '/contacts/' ),
		'cta_label'    => __( 'Связаться с нами', 'promokodiki' ),
		'sidebar_title'       => __( 'Содержание', 'promokodiki' ),
		'sidebar_description' => __( 'Выберите раздел, чтобы быстро перейти к ответам.', 'promokodiki' ),
	);

	if ( function_exists( 'get_field' ) ) {
		$data['introduction'] = get_field( 'faq_page_introduction', $post_id );
		$data['cta_title']    = get_field( 'faq_page_cta_title', $post_id ) ?: $data['cta_title'];
		$data['cta_text']     = get_field( 'faq_page_cta_text', $post_id ) ?: $data['cta_text'];
		$data['cta_url']      = get_field( 'faq_page_cta_url', $post_id ) ?: $data['cta_url'];
		$data['cta_label']    = get_field( 'faq_page_cta_label', $post_id ) ?: $data['cta_label'];
		$data['sidebar_title']       = get_field( 'faq_page_sidebar_title', $post_id ) ?: $data['sidebar_title'];
		$data['sidebar_description'] = get_field( 'faq_page_sidebar_description', $post_id ) ?: $data['sidebar_description'];
	}

	return $data;
}

function promokodiki_get_faq_page_sections( $post_id ) {
	$sections = function_exists( 'get_field' ) ? get_field( 'faq_page_sections', $post_id ) : array();

	return ! empty( $sections ) && is_array( $sections ) ? $sections : promokodiki_get_default_faq_sections();
}

function promokodiki_render_faq_page( $sections, $data ) {
	if ( empty( $sections ) || ! is_array( $sections ) ) {
		return '';
	}

	$title        = ! empty( $data['title'] ) ? $data['title'] : __( 'Часто задаваемые вопросы', 'promokodiki' );
	$introduction = ! empty( $data['introduction'] ) ? $data['introduction'] : '';
	$cta_title    = ! empty( $data['cta_title'] ) ? $data['cta_title'] : __( 'Не нашли ответ?', 'promokodiki' );
	$cta_text     = ! empty( $data['cta_text'] ) ? $data['cta_text'] : '';
	$cta_url      = ! empty( $data['cta_url'] ) ? $data['cta_url'] : home_url( '/contacts/' );
	$cta_label    = ! empty( $data['cta_label'] ) ? $data['cta_label'] : __( 'Связаться с нами', 'promokodiki' );
	$sidebar_title       = ! empty( $data['sidebar_title'] ) ? $data['sidebar_title'] : __( 'Содержание', 'promokodiki' );
	$sidebar_description = ! empty( $data['sidebar_description'] ) ? $data['sidebar_description'] : '';
	$index        = 0;
	$section_index = 0;

	ob_start();
	?>
	<section class="faq-page" itemscope itemtype="https://schema.org/FAQPage">
		<div class="container">
			<div class="faq-page__layout">
				<aside class="faq-page__sidebar">
					<div class="faq-page__sidebar-inner">
						<p class="faq-page__sidebar-title"><?php echo esc_html( $sidebar_title ); ?></p>
						<?php if ( $sidebar_description ) : ?>
							<p class="faq-page__sidebar-description"><?php echo esc_html( $sidebar_description ); ?></p>
						<?php endif; ?>
						<button class="faq-page__sidebar-toggle" type="button" aria-expanded="false" aria-controls="faq-page-navigation">
							<?php echo esc_html( $sidebar_title ); ?>
						</button>
						<nav id="faq-page-navigation" class="faq-page__nav" aria-label="<?php esc_attr_e( 'Навигация по FAQ', 'promokodiki' ); ?>">
							<ul>
								<?php $navigation_index = 0; ?>
								<?php foreach ( $sections as $section ) : ?>
									<?php
									$navigation_title = isset( $section['title'] ) ? $section['title'] : '';
									$navigation_items = isset( $section['items'] ) && is_array( $section['items'] ) ? $section['items'] : array();
									if ( ! $navigation_title || empty( $navigation_items ) ) {
										continue;
									}
									$navigation_index++;
									?>
									<li><a href="#faq-section-<?php echo esc_attr( $navigation_index ); ?>"><?php echo esc_html( $navigation_title ); ?></a></li>
								<?php endforeach; ?>
							</ul>
						</nav>
					</div>
				</aside>

				<div class="faq-page__content">
					<header class="faq-page__header">
						<h1><?php echo esc_html( $title ); ?></h1>
						<?php if ( $introduction ) : ?>
							<div class="faq-page__introduction"><?php echo wp_kses_post( $introduction ); ?></div>
						<?php endif; ?>
					</header>

					<div class="faq-page__sections">
				<?php foreach ( $sections as $section ) : ?>
					<?php
					$section_title = isset( $section['title'] ) ? $section['title'] : '';
					$items         = isset( $section['items'] ) && is_array( $section['items'] ) ? $section['items'] : array();
					if ( ! $section_title || empty( $items ) ) {
						continue;
					}
					$section_index++;
					?>
					<section id="faq-section-<?php echo esc_attr( $section_index ); ?>" class="faq-page__section">
						<h2><?php echo esc_html( $section_title ); ?></h2>
						<div class="faq-page__items">
							<?php foreach ( $items as $item ) : ?>
								<?php
								$question = isset( $item['question'] ) ? $item['question'] : '';
								$answer   = isset( $item['answer'] ) ? $item['answer'] : '';
								if ( ! $question || ! $answer ) {
									continue;
								}
								$index++;
								$answer_id = 'faq-answer-' . $index;
								?>
								<article class="faq-page__item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
									<h3>
										<button class="faq-page__question" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr( $answer_id ); ?>" itemprop="name">
											<span><?php echo esc_html( $question ); ?></span>
										</button>
									</h3>
									<div id="<?php echo esc_attr( $answer_id ); ?>" class="faq-page__answer" hidden itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
										<div itemprop="text"><?php echo wp_kses_post( $answer ); ?></div>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>
					</div>

					<aside class="faq-page__cta">
						<h2><?php echo esc_html( $cta_title ); ?></h2>
						<?php if ( $cta_text ) : ?>
							<p><?php echo esc_html( $cta_text ); ?></p>
						<?php endif; ?>
						<a class="faq-page__cta-link" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?></a>
					</aside>
				</div>
			</div>
		</div>
	</section>
	<?php

	return ob_get_clean();
}

function promokodiki_register_faq_page_fields() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_promokodiki_faq_page',
			'title'    => __( 'Содержимое FAQ', 'promokodiki' ),
			'fields'   => array(
				array(
					'key'   => 'field_promokodiki_faq_page_introduction',
					'label' => __( 'Вступление', 'promokodiki' ),
					'name'  => 'faq_page_introduction',
					'type'  => 'wysiwyg',
				),
				array(
					'key'          => 'field_promokodiki_faq_page_sections',
					'label'        => __( 'Разделы FAQ', 'promokodiki' ),
					'name'         => 'faq_page_sections',
					'type'         => 'repeater',
					'layout'       => 'block',
					'button_label' => __( 'Добавить раздел', 'promokodiki' ),
					'sub_fields'   => array(
						array(
							'key'   => 'field_promokodiki_faq_page_section_title',
							'label' => __( 'Заголовок раздела', 'promokodiki' ),
							'name'  => 'title',
							'type'  => 'text',
							'required' => 1,
						),
						array(
							'key'          => 'field_promokodiki_faq_page_items',
							'label'        => __( 'Вопросы и ответы', 'promokodiki' ),
							'name'         => 'items',
							'type'         => 'repeater',
							'layout'       => 'table',
							'button_label' => __( 'Добавить вопрос', 'promokodiki' ),
							'sub_fields'   => array(
								array(
									'key'   => 'field_promokodiki_faq_page_question',
									'label' => __( 'Вопрос', 'promokodiki' ),
									'name'  => 'question',
									'type'  => 'text',
									'required' => 1,
								),
								array(
									'key'   => 'field_promokodiki_faq_page_answer',
									'label' => __( 'Ответ', 'promokodiki' ),
									'name'  => 'answer',
									'type'  => 'wysiwyg',
									'required' => 1,
								),
							),
						),
					),
				),
				array(
					'key'   => 'field_promokodiki_faq_page_sidebar_title',
					'label' => __( 'Заголовок сайдбара', 'promokodiki' ),
					'name'  => 'faq_page_sidebar_title',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_promokodiki_faq_page_sidebar_description',
					'label' => __( 'Описание сайдбара', 'promokodiki' ),
					'name'  => 'faq_page_sidebar_description',
					'type'  => 'textarea',
				),
				array(
					'key'   => 'field_promokodiki_faq_page_cta_title',
					'label' => __( 'Заголовок CTA', 'promokodiki' ),
					'name'  => 'faq_page_cta_title',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_promokodiki_faq_page_cta_text',
					'label' => __( 'Текст CTA', 'promokodiki' ),
					'name'  => 'faq_page_cta_text',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_promokodiki_faq_page_cta_url',
					'label' => __( 'Ссылка CTA', 'promokodiki' ),
					'name'  => 'faq_page_cta_url',
					'type'  => 'url',
				),
				array(
					'key'   => 'field_promokodiki_faq_page_cta_label',
					'label' => __( 'Текст кнопки CTA', 'promokodiki' ),
					'name'  => 'faq_page_cta_label',
					'type'  => 'text',
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'page_template',
						'operator' => '==',
						'value'    => 'page-faq.php',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'promokodiki_register_faq_page_fields' );
