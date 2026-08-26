<?php
/**
 * Mobile navigation Customizer contract.
 *
 * Run: php tests/mobile-menu-customizer.php
 */

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback ) {}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		return $text;
	}
}

class Navigation_Customizer_Selective_Refresh {
	public function add_partial( $id, $args ) {}
}

class Navigation_Customizer_Manager {
	public $sections = array();
	public $settings = array();
	public $controls = array();
	public $selective_refresh;

	public function __construct() {
		$this->selective_refresh = new Navigation_Customizer_Selective_Refresh();
		$this->settings          = array(
			'blogname'         => (object) array( 'transport' => '' ),
			'blogdescription'  => (object) array( 'transport' => '' ),
			'header_textcolor' => (object) array( 'transport' => '' ),
		);
	}

	public function get_setting( $id ) {
		return $this->settings[ $id ];
	}

	public function add_section( $id, $args ) {
		$this->sections[ $id ] = $args;
	}

	public function add_setting( $id, $args ) {
		$this->settings[ $id ] = $args;
	}

	public function add_control( $id, $args ) {
		$this->controls[ $id ] = $args;
	}
}

function navigation_customizer_assert_true( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/inc/customizer.php';

navigation_customizer_assert_true( function_exists( 'promokodiki_sanitize_checkbox' ), 'Checkbox sanitizer exists' );
navigation_customizer_assert_true( promokodiki_sanitize_checkbox( true ), 'True checkbox values are accepted' );
navigation_customizer_assert_true( ! promokodiki_sanitize_checkbox( 'unexpected' ), 'Unexpected checkbox values are rejected' );

$manager = new Navigation_Customizer_Manager();
promokodiki_customize_register( $manager );

navigation_customizer_assert_true( isset( $manager->sections['promokodiki_mobile_menu'] ), 'Mobile menu Customizer section is registered' );
navigation_customizer_assert_true( isset( $manager->settings['promokodiki_mobile_categories_expanded'] ), 'Initial category state setting is registered' );
navigation_customizer_assert_true( true === $manager->settings['promokodiki_mobile_categories_expanded']['default'], 'Customizer setting defaults to expanded' );
navigation_customizer_assert_true( 'promokodiki_sanitize_checkbox' === $manager->settings['promokodiki_mobile_categories_expanded']['sanitize_callback'], 'Customizer setting sanitizes checkbox input' );
navigation_customizer_assert_true( 'checkbox' === $manager->controls['promokodiki_mobile_categories_expanded']['type'], 'Customizer renders a checkbox control' );

echo "Mobile menu Customizer contract passed.\n";

