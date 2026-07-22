<?php
/**
 * Filter form and promocode card rendering.
 *
 * @package PromokodikiAjaxFilter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Promokodiki_Filter_Renderer {
	public static function render( string $type, int $object_id = 0 ): string {
		$context = Promokodiki_Filter_Context::resolve( $type, $object_id );
		if ( is_wp_error( $context ) ) {
			return self::error_markup( $context->get_error_message() );
		}

		$settings = Promokodiki_Filter_Settings::get();
		$request  = is_array( $_GET ) ? wp_unslash( $_GET ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state    = Promokodiki_Filter_State::from_request( $request, $settings, $type );
		$options  = Promokodiki_Filter_Option_Service::build( $context, $state );
		if ( is_wp_error( $options ) ) {
			return self::error_markup( $options->get_error_message() );
		}
		$state                       = $options['state'];
		$context['category_options'] = $options['category_options'];
		$context['brand_options']    = $options['brand_options'];

		$result = Promokodiki_Filter_Query_Service::run( $state, $context, $settings );
		if ( is_wp_error( $result ) ) {
			return self::error_markup( $result->get_error_message() );
		}

		$form_action   = self::form_action( $type, $object_id );
		$context_token = wp_create_nonce( 'promokodiki_filter_context_' . $type . '_' . $object_id );
		$cards_html    = self::render_cards( $result['posts'] );
		if ( '' === $cards_html ) {
			$message    = ! empty( $state['popular'] ) ? $settings['weekly_empty_label'] : $settings['empty_label'];
			$cards_html = '<p class="no-promocodes">' . esc_html( $message ) . '</p>';
		}

		ob_start();
		?>
		<div
			class="promokodiki-filter"
			data-promokodiki-filter
			data-context="<?php echo esc_attr( $type ); ?>"
			data-object-id="<?php echo esc_attr( (string) $object_id ); ?>"
			data-context-token="<?php echo esc_attr( $context_token ); ?>"
		>
			<?php require PROMOKODIKI_FILTER_DIR . 'templates/filter-form.php'; ?>
			<div class="promokodiki-filter__loader" data-filter-loader aria-hidden="true" hidden>
				<span class="screen-reader-text"><?php esc_html_e( 'Загрузка…', 'promokodiki-ajax-filter' ); ?></span>
			</div>
			<div class="promocodes__items" data-filter-results aria-live="polite" aria-busy="false">
				<?php echo $cards_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Card template escapes fields. ?>
			</div>
			<button
				type="button"
				class="promokodiki-filter__more"
				data-filter-more
				<?php echo $result['has_more'] ? '' : 'hidden'; ?>
			>
				<?php echo esc_html( $settings['load_more_label'] ); ?>
			</button>
			<div class="promokodiki-filter__status" data-filter-status role="status" aria-live="polite"></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public static function render_cards( array $posts ): string {
		global $post;

		$template = locate_template( 'template-parts/promocode-card.php', false, false );
		$original = $post;
		ob_start();
		try {
			foreach ( $posts as $item ) {
				$post = $item; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				setup_postdata( $post );
				if ( $template ) {
					include $template;
				} else {
					echo '<article class="promocodes__item"><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></article>';
				}
			}
		} finally {
			$post = $original; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			if ( $post instanceof WP_Post ) {
				setup_postdata( $post );
			} else {
				wp_reset_postdata();
			}
		}

		return trim( (string) ob_get_clean() );
	}

	private static function form_action( string $type, int $object_id ): string {
		if ( 'home' === $type ) {
			return home_url( '/' );
		}

		$taxonomy = 'shop' === $type ? 'shops_category' : 'promocode_category';
		$link     = get_term_link( $object_id, $taxonomy );
		return is_wp_error( $link ) ? home_url( '/' ) : $link;
	}

	private static function error_markup( string $message ): string {
		return '<p class="promokodiki-filter__error" role="alert">' . esc_html( $message ) . '</p>';
	}
}

function promokodiki_filter_render( array $args = array() ): void {
	$type      = sanitize_key( (string) ( $args['context'] ?? 'home' ) );
	$object_id = absint( $args['object_id'] ?? 0 );
	echo Promokodiki_Filter_Renderer::render( $type, $object_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
