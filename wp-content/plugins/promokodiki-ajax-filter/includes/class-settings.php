<?php
/**
 * Filter settings and validation.
 *
 * @package PromokodikiAjaxFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Promokodiki_Filter_Settings {
	public const OPTION_NAME = 'promokodiki_filter_settings';

	private const SORTS = array( 'newest', 'popular', 'expiring', 'oldest' );

	public static function defaults(): array {
		return array(
			'initial_count'     => 8,
			'load_more_count'   => 8,
			'popular_days'      => 7,
			'default_sort'      => 'newest',
			'enabled_sorts'     => self::SORTS,
			'show_expired'      => false,
			'category_label'    => 'Категории',
			'brand_label'       => 'Бренды',
			'popular_label'     => 'Популярное за неделю',
			'sort_label'        => 'Без сортировки',
			'load_more_label'   => 'Показать ещё',
			'retry_label'       => 'Повторить',
			'apply_label'       => 'Применить',
			'empty_label'       => 'Промокоды не найдены.',
			'weekly_empty_label'=> 'Данных пока нет.',
		);
	}

	public static function get(): array {
		$value = get_option( self::OPTION_NAME, array() );
		return self::sanitize( is_array( $value ) ? $value : array() );
	}

	public static function sanitize( array $input ): array {
		$defaults = self::defaults();
		$output   = $defaults;

		$output['initial_count']   = self::bounded_int( $input['initial_count'] ?? $defaults['initial_count'], 1, 100 );
		$output['load_more_count'] = self::bounded_int( $input['load_more_count'] ?? $defaults['load_more_count'], 1, 100 );
		$output['popular_days']    = self::bounded_int( $input['popular_days'] ?? $defaults['popular_days'], 1, 31 );
		$output['show_expired']    = ! empty( $input['show_expired'] );

		$requested_sorts = isset( $input['enabled_sorts'] ) && is_array( $input['enabled_sorts'] )
			? array_map( 'sanitize_key', $input['enabled_sorts'] )
			: $defaults['enabled_sorts'];
		$enabled_sorts  = array_values( array_intersect( self::SORTS, $requested_sorts ) );
		$output['enabled_sorts'] = $enabled_sorts ?: $defaults['enabled_sorts'];

		$default_sort = sanitize_key( (string) ( $input['default_sort'] ?? $defaults['default_sort'] ) );
		$output['default_sort'] = in_array( $default_sort, $output['enabled_sorts'], true )
			? $default_sort
			: $output['enabled_sorts'][0];

		foreach ( self::label_keys() as $key ) {
			$output[ $key ] = sanitize_text_field( (string) ( $input[ $key ] ?? $defaults[ $key ] ) );
			if ( '' === $output[ $key ] ) {
				$output[ $key ] = $defaults[ $key ];
			}
		}

		return $output;
	}

	public static function allowed_sorts(): array {
		return self::SORTS;
	}

	public static function register(): void {
		register_setting(
			'promokodiki_filter',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'capability'        => 'manage_options',
				'default'           => self::defaults(),
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);

		add_settings_section(
			'promokodiki_filter_main',
			__( 'Настройки фильтра', 'promokodiki-ajax-filter' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Настройки применяются к главной, категориям промокодов и страницам магазинов.', 'promokodiki-ajax-filter' ) . '</p>';
			},
			'promokodiki-ajax-filter'
		);

		$fields = array(
			'initial_count'   => array( 'label' => __( 'Карточек при первой загрузке', 'promokodiki-ajax-filter' ), 'type' => 'number', 'min' => 1, 'max' => 100 ),
			'load_more_count' => array( 'label' => __( 'Карточек по кнопке «Показать ещё»', 'promokodiki-ajax-filter' ), 'type' => 'number', 'min' => 1, 'max' => 100 ),
			'popular_days'    => array( 'label' => __( 'Период популярности, дней', 'promokodiki-ajax-filter' ), 'type' => 'number', 'min' => 1, 'max' => 31 ),
			'default_sort'    => array( 'label' => __( 'Сортировка по умолчанию', 'promokodiki-ajax-filter' ), 'type' => 'sort' ),
			'enabled_sorts'   => array( 'label' => __( 'Доступные сортировки', 'promokodiki-ajax-filter' ), 'type' => 'sorts' ),
			'show_expired'    => array( 'label' => __( 'Истёкшие промокоды', 'promokodiki-ajax-filter' ), 'type' => 'checkbox' ),
		);
		foreach ( self::label_keys() as $key ) {
			$fields[ $key ] = array( 'label' => self::label_title( $key ), 'type' => 'text' );
		}

		foreach ( $fields as $key => $field ) {
			add_settings_field(
				'promokodiki_filter_' . $key,
				$field['label'],
				array( __CLASS__, 'render_field' ),
				'promokodiki-ajax-filter',
				'promokodiki_filter_main',
				array_merge( $field, array( 'key' => $key ) )
			);
		}
	}

	public static function add_menu(): void {
		add_submenu_page(
			'edit.php?post_type=promocode',
			__( 'AJAX-фильтр', 'promokodiki-ajax-filter' ),
			__( 'AJAX-фильтр', 'promokodiki-ajax-filter' ),
			'manage_options',
			'promokodiki-ajax-filter',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'У вас нет доступа к этим настройкам.', 'promokodiki-ajax-filter' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Promokodiki AJAX Filter', 'promokodiki-ajax-filter' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'promokodiki_filter' );
				do_settings_sections( 'promokodiki-ajax-filter' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public static function render_field( array $args ): void {
		$settings = self::get();
		$key      = sanitize_key( $args['key'] );
		$type     = sanitize_key( $args['type'] );
		$name     = self::OPTION_NAME . '[' . $key . ']';

		if ( 'checkbox' === $type ) {
			echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( ! empty( $settings[ $key ] ), true, false ) . '> ' . esc_html__( 'Показывать истёкшие в конце', 'promokodiki-ajax-filter' ) . '</label>';
			return;
		}

		if ( 'sort' === $type ) {
			echo '<select name="' . esc_attr( $name ) . '">';
			foreach ( self::sort_labels() as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '" ' . selected( $settings[ $key ], $value, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
			return;
		}

		if ( 'sorts' === $type ) {
			foreach ( self::sort_labels() as $value => $label ) {
				echo '<label style="display:block"><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $value ) . '" ' . checked( in_array( $value, $settings[ $key ], true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
			}
			return;
		}

		$extra = '';
		if ( 'number' === $type ) {
			$extra = ' min="' . esc_attr( (string) $args['min'] ) . '" max="' . esc_attr( (string) $args['max'] ) . '"';
		}
		echo '<input class="regular-text" type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $settings[ $key ] ) . '"' . $extra . '>';
	}

	public static function render_conflict_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = array(
			'filter-everything/filter-everything.php'     => 'Filter Everything',
			'filter-everything-pro/filter-everything.php' => 'Filter Everything Pro',
		);
		$active = array();
		foreach ( $plugins as $basename => $name ) {
			if ( is_plugin_active( $basename ) ) {
				$active[] = $name;
			}
		}
		if ( ! $active ) {
			return;
		}

		echo '<div class="notice notice-warning is-dismissible"><p>';
		echo esc_html(
			sprintf(
				/* translators: %s: comma-separated plugin names. */
				__( 'Обнаружены активные плагины: %s. Отключите их вручную только после проверки нового фильтра.', 'promokodiki-ajax-filter' ),
				implode( ', ', $active )
			)
		);
		echo '</p></div>';
	}

	private static function bounded_int( mixed $value, int $minimum, int $maximum ): int {
		return max( $minimum, min( $maximum, (int) $value ) );
	}

	private static function label_keys(): array {
		return array(
			'category_label',
			'brand_label',
			'popular_label',
			'sort_label',
			'load_more_label',
			'retry_label',
			'apply_label',
			'empty_label',
			'weekly_empty_label',
		);
	}

	private static function sort_labels(): array {
		return array(
			'newest'   => __( 'Сначала новые', 'promokodiki-ajax-filter' ),
			'popular'  => __( 'Сначала популярные', 'promokodiki-ajax-filter' ),
			'expiring' => __( 'Скоро истекают', 'promokodiki-ajax-filter' ),
			'oldest'   => __( 'Сначала старые', 'promokodiki-ajax-filter' ),
		);
	}

	private static function label_title( string $key ): string {
		$titles = array(
			'category_label'     => __( 'Подпись категорий', 'promokodiki-ajax-filter' ),
			'brand_label'        => __( 'Подпись брендов', 'promokodiki-ajax-filter' ),
			'popular_label'      => __( 'Подпись популярности', 'promokodiki-ajax-filter' ),
			'sort_label'         => __( 'Подпись сортировки', 'promokodiki-ajax-filter' ),
			'load_more_label'    => __( 'Текст «Показать ещё»', 'promokodiki-ajax-filter' ),
			'retry_label'        => __( 'Текст повтора', 'promokodiki-ajax-filter' ),
			'apply_label'        => __( 'Текст применения без JavaScript', 'promokodiki-ajax-filter' ),
			'empty_label'        => __( 'Сообщение пустой выдачи', 'promokodiki-ajax-filter' ),
			'weekly_empty_label' => __( 'Сообщение без недельных данных', 'promokodiki-ajax-filter' ),
		);
		return $titles[ $key ];
	}
}
