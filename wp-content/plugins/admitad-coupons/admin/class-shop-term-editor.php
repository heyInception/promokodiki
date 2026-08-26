<?php
/** Secure Admitad section on shop taxonomy terms. @package Promokodiki_Admitad */
defined( 'ABSPATH' ) || exit;

final class Promokodiki_Admitad_Shop_Term_Editor {
	public static function register(): void {
		add_action( 'shops_category_edit_form_fields', array( self::class, 'render' ) );
		add_action( 'edited_shops_category', array( self::class, 'handle_save' ) );
	}

	public static function render( WP_Term $term ): void {
		if ( ! current_user_can( 'manage_admitad_automation' ) ) { return; }
		$source = (string) get_term_meta( $term->term_id, '_admitad_shop_source_description', true );
		$manual = (string) get_term_meta( $term->term_id, '_admitad_shop_manual_description', true );
		wp_nonce_field( 'promokodiki_admitad_shop_editor', '_admitad_shop_editor_nonce' );
		echo '<tr class="form-field"><th colspan="2"><h2>' . esc_html__( 'Описание из Admitad', 'promokodiki-admitad' ) . '</h2></th></tr>';
		self::input_row( 'admitad_campaign_id', 'Campaign ID Admitad', (string) get_term_meta( $term->term_id, 'admitad_campaign_id', true ), 'number' );
		echo '<tr class="form-field"><th><label>' . esc_html__( 'Исходное описание', 'promokodiki-admitad' ) . '</label></th><td><div class="admitad-source-description" data-admitad-source-description>' . wp_kses_post( $source ) . '</div><button type="button" class="button" data-admitad-copy-source>' . esc_html__( 'Скопировать исходное', 'promokodiki-admitad' ) . '</button></td></tr>';
		echo '<tr class="form-field"><th><label for="admitad-shop-manual-description">' . esc_html__( 'Описание для сайта', 'promokodiki-admitad' ) . '</label></th><td>';
		wp_editor( $manual, 'admitad-shop-manual-description', array( 'textarea_name' => '_admitad_shop_manual_description', 'media_buttons' => false, 'teeny' => true, 'quicktags' => false, 'tinymce' => array( 'toolbar1' => 'formatselect,bold,italic,bullist,numlist,undo,redo', 'toolbar2' => '' ) ) );
		echo '<label><input type="checkbox" name="_admitad_shop_reset_description" value="1"> ' . esc_html__( 'Сбросить ручную версию', 'promokodiki-admitad' ) . '</label></td></tr>';
		self::input_row( 'shop_address', 'Адрес магазина', (string) get_term_meta( $term->term_id, 'shop_address', true ) );
		self::input_row( 'shop_phone', 'Телефон', (string) get_term_meta( $term->term_id, 'shop_phone', true ) );
		self::input_row( 'shop_email', 'Почта магазина', (string) get_term_meta( $term->term_id, 'shop_email', true ), 'email' );
		self::input_row( 'shop_website', 'Сайт', (string) get_term_meta( $term->term_id, 'shop_website', true ), 'url' );
		$hints = (array) get_term_meta( $term->term_id, '_admitad_shop_contact_hints', true );
		if ( ! empty( $hints['phones'] ) || ! empty( $hints['emails'] ) ) {
			echo '<tr><th>' . esc_html__( 'Кандидаты из Admitad', 'promokodiki-admitad' ) . '</th><td><p class="description">' . esc_html( implode( ', ', array_merge( (array) ( $hints['phones'] ?? array() ), (array) ( $hints['emails'] ?? array() ) ) ) ) . '</p></td></tr>';
		}
		self::input_row( '_admitad_shop_manual_affiliate_url', 'Ручная партнёрская ссылка', (string) get_term_meta( $term->term_id, '_admitad_shop_manual_affiliate_url', true ), 'url' );
		echo '<tr class="form-field"><th>' . esc_html__( 'Deeplink Admitad', 'promokodiki-admitad' ) . '</th><td><code>' . esc_html( (string) get_term_meta( $term->term_id, '_admitad_shop_deeplink', true ) ) . '</code><p class="description">' . esc_html__( 'Статус:', 'promokodiki-admitad' ) . ' ' . esc_html( (string) get_term_meta( $term->term_id, '_admitad_shop_deeplink_status', true ) ) . '</p><button type="submit" class="button" name="_admitad_shop_regenerate_deeplink" value="1">' . esc_html__( 'Перегенерировать', 'promokodiki-admitad' ) . '</button></td></tr>';
	}

	private static function input_row( string $name, string $label, string $value, string $type = 'text' ): void {
		printf( '<tr class="form-field"><th><label for="%1$s">%2$s</label></th><td><input id="%1$s" name="%1$s" type="%3$s" value="%4$s" class="regular-text"></td></tr>', esc_attr( $name ), esc_html( $label ), esc_attr( $type ), esc_attr( $value ) );
	}

	public static function handle_save( int $term_id ): void { self::save( $term_id, wp_unslash( $_POST ) ); }

	public static function save( int $term_id, array $data ): void {
		if ( ! current_user_can( 'manage_admitad_automation' ) || ! wp_verify_nonce( sanitize_text_field( (string) ( $data['_admitad_shop_editor_nonce'] ?? '' ) ), 'promokodiki_admitad_shop_editor' ) ) { return; }
		$campaign_id = absint( $data['admitad_campaign_id'] ?? 0 );
		$old_campaign_id = absint( get_term_meta( $term_id, 'admitad_campaign_id', true ) );
		if ( $campaign_id > 0 && $campaign_id !== $old_campaign_id ) {
			( new Promokodiki_Admitad_Shop_Link_Audit() )->assign( $term_id, $campaign_id, get_current_user_id() );
		}
		if ( ! empty( $data['_admitad_shop_reset_description'] ) ) { delete_term_meta( $term_id, '_admitad_shop_manual_description' ); }
		elseif ( array_key_exists( '_admitad_shop_manual_description', $data ) ) { update_term_meta( $term_id, '_admitad_shop_manual_description', Promokodiki_Admitad_Shop_Content_Service::sanitize( (string) $data['_admitad_shop_manual_description'] ) ); }
		$fields = array( 'shop_address' => 'text', 'shop_phone' => 'text', 'shop_email' => 'email', 'shop_website' => 'url', '_admitad_shop_manual_affiliate_url' => 'url' );
		foreach ( $fields as $key => $type ) {
			if ( ! array_key_exists( $key, $data ) ) { continue; }
			$value = 'email' === $type ? sanitize_email( (string) $data[ $key ] ) : ( 'url' === $type ? esc_url_raw( (string) $data[ $key ] ) : sanitize_text_field( (string) $data[ $key ] ) );
			'' === $value ? delete_term_meta( $term_id, $key ) : update_term_meta( $term_id, $key, $value );
		}
		update_term_meta( $term_id, '_admitad_shop_manual_audit', array( 'updated_at' => time(), 'user_id' => get_current_user_id(), 'source' => 'manual' ) );
		if ( ! empty( $data['_admitad_shop_regenerate_deeplink'] ) ) { ( new Promokodiki_Admitad_Deeplink_Queue() )->enqueue( $term_id ); }
	}
}
