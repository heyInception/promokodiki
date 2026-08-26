<?php
/**
 * promokodiki Theme Customizer
 *
 * @package promokodiki
 */

/**
 * Add postMessage support for site title and description for the Theme Customizer.
 *
 * @param WP_Customize_Manager $wp_customize Theme Customizer object.
 */
function promokodiki_customize_register( $wp_customize ) {
	$wp_customize->get_setting( 'blogname' )->transport         = 'postMessage';
	$wp_customize->get_setting( 'blogdescription' )->transport  = 'postMessage';
	$wp_customize->get_setting( 'header_textcolor' )->transport = 'postMessage';

	if ( isset( $wp_customize->selective_refresh ) ) {
		$wp_customize->selective_refresh->add_partial(
			'blogname',
			array(
				'selector'        => '.site-title a',
				'render_callback' => 'promokodiki_customize_partial_blogname',
			)
		);
		$wp_customize->selective_refresh->add_partial(
			'blogdescription',
			array(
				'selector'        => '.site-description',
				'render_callback' => 'promokodiki_customize_partial_blogdescription',
			)
		);
	}

	$wp_customize->add_section(
		'promokodiki_mobile_menu',
		array(
			'title'       => __( 'Мобильное меню', 'promokodiki' ),
			'description' => __( 'Настройте начальное состояние мобильной навигации.', 'promokodiki' ),
			'priority'    => 35,
		)
	);

	$wp_customize->add_setting(
		'promokodiki_mobile_categories_expanded',
		array(
			'default'           => true,
			'sanitize_callback' => 'promokodiki_sanitize_checkbox',
			'transport'         => 'refresh',
		)
	);

	$wp_customize->add_control(
		'promokodiki_mobile_categories_expanded',
		array(
			'label'       => __( 'Раскрывать рубрики промокодов при открытии меню', 'promokodiki' ),
			'section'     => 'promokodiki_mobile_menu',
			'settings'    => 'promokodiki_mobile_categories_expanded',
			'type'        => 'checkbox',
		)
	);
}
add_action( 'customize_register', 'promokodiki_customize_register' );

/**
 * Sanitize a Customizer checkbox value.
 *
 * @param mixed $checked Raw checkbox value.
 * @return bool
 */
function promokodiki_sanitize_checkbox( $checked ) {
	return in_array( $checked, array( true, 1, '1', 'true', 'on' ), true );
}

/**
 * Render the site title for the selective refresh partial.
 *
 * @return void
 */
function promokodiki_customize_partial_blogname() {
	bloginfo( 'name' );
}

/**
 * Render the site tagline for the selective refresh partial.
 *
 * @return void
 */
function promokodiki_customize_partial_blogdescription() {
	bloginfo( 'description' );
}

/**
 * Binds JS handlers to make Theme Customizer preview reload changes asynchronously.
 */
function promokodiki_customize_preview_js() {
	wp_enqueue_script( 'promokodiki-customizer', get_template_directory_uri() . '/js/customizer.js', array( 'customize-preview' ), _S_VERSION, true );
}
add_action( 'customize_preview_init', 'promokodiki_customize_preview_js' );
