<?php
/**
 * Minimal safe foundation fragment for authenticated AJAX responses.
 *
 * @package Promokodiki_Admitad
 */

$value = $message ?? '';
if ( ! is_string( $value ) ) {
	throw new InvalidArgumentException( 'Invalid foundation fragment context.' );
}
?>
<div class="promokodiki-admitad-fragment" data-admitad-fragment="foundation">
	<?php echo esc_html( $value ); ?>
</div>
