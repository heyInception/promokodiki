<?php
/**
 * Replaceable company-profile table fragment.
 *
 * @package Promokodiki_Admitad
 */

$total_pages = max( 1, (int) ceil( $rows['total'] / $rows['per_page'] ) );
?>
<div class="tablenav top">
	<label for="company-per-page"><?php esc_html_e( 'Строк на странице', 'promokodiki-admitad' ); ?></label>
	<select id="company-per-page" name="per_page" form="company-page-size">
		<?php foreach ( array( 20, 50, 100 ) as $size ) : ?><option value="<?php echo esc_attr( (string) $size ); ?>"<?php selected( $request->per_page(), $size ); ?>><?php echo esc_html( (string) $size ); ?></option><?php endforeach; ?>
	</select>
	<form id="company-page-size" method="get" data-admitad-ajax data-admitad-action="promokodiki_admitad_admin" data-admitad-operation="company_list" data-admitad-page="admitad-companies" data-admitad-fragment="company-table"><input type="hidden" name="post_type" value="promocode"><input type="hidden" name="page" value="admitad-companies"><input type="hidden" name="s" value="<?php echo esc_attr( $request->search() ); ?>"><button type="submit" class="button"><?php esc_html_e( 'Применить', 'promokodiki-admitad' ); ?></button></form>
</div>
<table class="widefat striped"><thead><tr><th>Campaign ID</th><th><?php esc_html_e( 'Компания', 'promokodiki-admitad' ); ?></th><th><?php esc_html_e( 'Допустимые рубрики', 'promokodiki-admitad' ); ?></th><th><?php esc_html_e( 'По умолчанию', 'promokodiki-admitad' ); ?></th><th><?php esc_html_e( 'Статус', 'promokodiki-admitad' ); ?></th></tr></thead><tbody>
<?php if ( empty( $rows['items'] ) ) : ?><tr><td colspan="5"><?php esc_html_e( 'Профили не найдены.', 'promokodiki-admitad' ); ?></td></tr><?php endif; ?>
<?php foreach ( $rows['items'] as $row ) : $status = Promokodiki_Admitad_Admin_Presenter::status( 'profile', (string) $row['status'] ); ?><tr><td><?php echo esc_html( (string) $row['campaign_id'] ); ?></td><td><?php echo esc_html( (string) $row['display_name'] ); ?></td><td><?php echo esc_html( implode( ', ', array_map( static fn( int $id ): string => Promokodiki_Admitad_Admin_Presenter::term_path( $id ), $row['allowed_term_ids'] ) ) ?: '—' ); ?></td><td><?php echo esc_html( Promokodiki_Admitad_Admin_Presenter::term_path( (int) $row['default_term_id'] ) ); ?></td><td><span class="<?php echo esc_attr( $status['class'] ); ?>"><?php echo esc_html( $status['label'] ); ?></span></td></tr><?php endforeach; ?>
</tbody></table>
<?php if ( $total_pages > 1 ) : ?><nav class="tablenav-pages" aria-label="<?php esc_attr_e( 'Страницы профилей компаний', 'promokodiki-admitad' ); ?>"><?php for ( $number = 1; $number <= $total_pages; ++$number ) : $url = add_query_arg( 'paged', $number, $request->url() ); ?><a href="<?php echo esc_url( $url ); ?>" data-admitad-ajax data-admitad-action="promokodiki_admitad_admin" data-admitad-operation="company_list" data-admitad-page="admitad-companies" data-admitad-fragment="company-table"<?php echo $number === $request->paged() ? ' aria-current="page"' : ''; ?>><?php echo esc_html( (string) $number ); ?></a> <?php endfor; ?></nav><?php endif; ?>
