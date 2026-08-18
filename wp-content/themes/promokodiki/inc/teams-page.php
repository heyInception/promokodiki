<?php
/**
 * Teams page fields and rendering helpers.
 *
 * @package promokodiki
 */

function promokodiki_get_teams_page_data( $post_id ) {
	$data = array(
		'title'        => function_exists( 'get_the_title' ) ? get_the_title( $post_id ) : '',
		'introduction' => '',
	);

	if ( function_exists( 'get_field' ) ) {
		$data['introduction'] = get_field( 'teams_page_introduction', $post_id );
	}

	return $data;
}

function promokodiki_get_teams_page_sections( $post_id ) {
	$sections = function_exists( 'get_field' ) ? get_field( 'teams_page_sections', $post_id ) : array();

	return is_array( $sections ) ? $sections : array();
}

function promokodiki_render_teams_page( $sections, $data ) {
	$title        = ! empty( $data['title'] ) ? $data['title'] : __( 'Наша команда', 'promokodiki' );
	$introduction = ! empty( $data['introduction'] ) ? $data['introduction'] : '';
	$sections     = is_array( $sections ) ? $sections : array();

	ob_start();
	?>
	<section class="teams-page">
		<div class="container">
			<header class="teams-page__header">
				<h1><?php echo esc_html( $title ); ?></h1>
				<?php if ( $introduction ) : ?>
					<div class="teams-page__introduction"><?php echo wp_kses_post( $introduction ); ?></div>
				<?php endif; ?>
			</header>

			<div class="teams-page__sections">
				<?php foreach ( $sections as $section ) : ?>
					<?php
					$section_title = isset( $section['title'] ) ? $section['title'] : '';
					$description   = isset( $section['description'] ) ? $section['description'] : '';
					$members       = isset( $section['members'] ) && is_array( $section['members'] ) ? $section['members'] : array();
					$valid_members = array_filter(
						$members,
						static function ( $member ) {
							return ! empty( $member['name'] ) && ! empty( $member['photo']['url'] );
						}
					);

					if ( ! $section_title || empty( $valid_members ) ) {
						continue;
					}
					?>
					<section class="teams-page__section">
						<h2><?php echo esc_html( $section_title ); ?></h2>
						<?php if ( $description ) : ?>
							<div class="teams-page__description"><?php echo wp_kses_post( $description ); ?></div>
						<?php endif; ?>
						<ul class="teams-page__members">
							<?php foreach ( $valid_members as $member ) : ?>
								<?php
								$name  = $member['name'];
								$photo = $member['photo'];
								$url   = isset( $member['url'] ) ? $member['url'] : '';
								?>
								<li class="teams-page__member">
									<?php if ( $url ) : ?>
										<a class="teams-page__member-link" href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( $name ); ?>">
									<?php endif; ?>
									<img src="<?php echo esc_url( $photo['url'] ); ?>" alt="<?php echo esc_attr( $name ); ?>"<?php echo ! empty( $photo['width'] ) ? ' width="' . esc_attr( $photo['width'] ) . '"' : ''; ?><?php echo ! empty( $photo['height'] ) ? ' height="' . esc_attr( $photo['height'] ) . '"' : ''; ?>>
									<?php if ( $url ) : ?>
										</a>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php

	return ob_get_clean();
}

function promokodiki_register_teams_page_fields() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'    => 'group_promokodiki_teams_page',
			'title'  => __( 'Содержимое страницы «Наша команда»', 'promokodiki' ),
			'fields' => array(
				array(
					'key'   => 'field_promokodiki_teams_introduction',
					'label' => __( 'Вступление', 'promokodiki' ),
					'name'  => 'teams_page_introduction',
					'type'  => 'wysiwyg',
				),
				array(
					'key'          => 'field_promokodiki_teams_sections',
					'label'        => __( 'Команды', 'promokodiki' ),
					'name'         => 'teams_page_sections',
					'type'         => 'repeater',
					'layout'       => 'block',
					'button_label' => __( 'Добавить команду', 'promokodiki' ),
					'sub_fields'   => array(
						array(
							'key'      => 'field_promokodiki_teams_section_title',
							'label'    => __( 'Заголовок', 'promokodiki' ),
							'name'     => 'title',
							'type'     => 'text',
							'required' => 1,
						),
						array(
							'key'   => 'field_promokodiki_teams_section_description',
							'label' => __( 'Описание', 'promokodiki' ),
							'name'  => 'description',
							'type'  => 'wysiwyg',
						),
						array(
							'key'          => 'field_promokodiki_teams_members',
							'label'        => __( 'Участники', 'promokodiki' ),
							'name'         => 'members',
							'type'         => 'repeater',
							'layout'       => 'table',
							'button_label' => __( 'Добавить участника', 'promokodiki' ),
							'sub_fields'   => array(
								array(
									'key'      => 'field_promokodiki_teams_member_name',
									'label'    => __( 'Имя', 'promokodiki' ),
									'name'     => 'name',
									'type'     => 'text',
									'required' => 1,
								),
								array(
									'key'           => 'field_promokodiki_teams_member_photo',
									'label'         => __( 'Фото', 'promokodiki' ),
									'name'          => 'photo',
									'type'          => 'image',
									'required'      => 1,
									'return_format' => 'array',
								),
								array(
									'key'   => 'field_promokodiki_teams_member_url',
									'label' => __( 'Ссылка', 'promokodiki' ),
									'name'  => 'url',
									'type'  => 'url',
								),
							),
						),
					),
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'page_template',
						'operator' => '==',
						'value'    => 'page-teams.php',
					),
				),
			),
		)
	);
}
add_action( 'acf/init', 'promokodiki_register_teams_page_fields' );
