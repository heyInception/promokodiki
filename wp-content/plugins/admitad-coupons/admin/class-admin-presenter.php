<?php
/**
 * Shared presentation helpers for Admitad administration screens.
 *
 * @package Promokodiki_Admitad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translates internal state into consistent administrative presentation data.
 */
final class Promokodiki_Admitad_Admin_Presenter {
	/**
	 * Status labels and semantic badge classes by domain.
	 *
	 * @var array<string, array<string, array{label:string,class:string}>>
	 */
	private const STATUS_REGISTRY = array(
		'sync'       => array(
			'scheduled' => array( 'label' => 'Запланировано', 'class' => 'promokodiki-admitad-status--neutral' ),
			'running'   => array( 'label' => 'Выполняется', 'class' => 'promokodiki-admitad-status--info' ),
			'completed' => array( 'label' => 'Завершено', 'class' => 'promokodiki-admitad-status--success' ),
			'failed'    => array( 'label' => 'Ошибка', 'class' => 'promokodiki-admitad-status--error' ),
			'cancelled' => array( 'label' => 'Отменено', 'class' => 'promokodiki-admitad-status--neutral' ),
			'delayed'   => array( 'label' => 'Отложено', 'class' => 'promokodiki-admitad-status--warning' ),
		),
		'mapping'    => array(
			'active'   => array( 'label' => 'Активно', 'class' => 'promokodiki-admitad-status--success' ),
			'inactive' => array( 'label' => 'Неактивно', 'class' => 'promokodiki-admitad-status--neutral' ),
			'unmapped' => array( 'label' => 'Не сопоставлено', 'class' => 'promokodiki-admitad-status--warning' ),
		),
		'profile'    => array(
			'active'   => array( 'label' => 'Активно', 'class' => 'promokodiki-admitad-status--success' ),
			'inactive' => array( 'label' => 'Неактивно', 'class' => 'promokodiki-admitad-status--neutral' ),
			'unmapped' => array( 'label' => 'Не сопоставлено', 'class' => 'promokodiki-admitad-status--warning' ),
		),
		'rule'       => array(
			'active'    => array( 'label' => 'Активно', 'class' => 'promokodiki-admitad-status--success' ),
			'candidate' => array( 'label' => 'Кандидат', 'class' => 'promokodiki-admitad-status--info' ),
			'suspended' => array( 'label' => 'Приостановлено', 'class' => 'promokodiki-admitad-status--warning' ),
			'conflict'  => array( 'label' => 'Конфликт', 'class' => 'promokodiki-admitad-status--error' ),
			'archived'  => array( 'label' => 'В архиве', 'class' => 'promokodiki-admitad-status--neutral' ),
		),
		'review'     => array(
			'open'     => array( 'label' => 'Открыто', 'class' => 'promokodiki-admitad-status--warning' ),
			'resolved' => array( 'label' => 'Решено', 'class' => 'promokodiki-admitad-status--success' ),
			'archived' => array( 'label' => 'В архиве', 'class' => 'promokodiki-admitad-status--neutral' ),
		),
		'confidence' => array(
			'high'     => array( 'label' => 'Высокая', 'class' => 'promokodiki-admitad-status--success' ),
			'medium'   => array( 'label' => 'Средняя', 'class' => 'promokodiki-admitad-status--warning' ),
			'low'      => array( 'label' => 'Низкая', 'class' => 'promokodiki-admitad-status--error' ),
			'conflict' => array( 'label' => 'Конфликт', 'class' => 'promokodiki-admitad-status--error' ),
		),
		'snapshot'   => array(
			'previewed'   => array( 'label' => 'Предпросмотр', 'class' => 'promokodiki-admitad-status--info' ),
			'applied'     => array( 'label' => 'Применено', 'class' => 'promokodiki-admitad-status--success' ),
			'rolled_back' => array( 'label' => 'Откат выполнен', 'class' => 'promokodiki-admitad-status--neutral' ),
			'expired'     => array( 'label' => 'Истекло', 'class' => 'promokodiki-admitad-status--warning' ),
		),
	);

	/**
	 * Return a Russian label and semantic badge class for an internal status.
	 *
	 * @param string $domain Status domain.
	 * @param string $value  Internal status value.
	 * @return array{label:string,class:string}
	 */
	public static function status( string $domain, string $value ): array {
		return self::STATUS_REGISTRY[ $domain ][ $value ] ?? array(
			'label' => sprintf( 'Неизвестный статус (%s)', $value ),
			'class' => 'promokodiki-admitad-status--neutral',
		);
	}

	/**
	 * Return a taxonomy term's complete ancestor path.
	 *
	 * @param int $term_id Promocode category term ID.
	 * @return string
	 */
	public static function term_path( int $term_id ): string {
		$labels  = array();
		$visited = array();
		$current = $term_id;

		for ( $depth = 0; $depth < 100 && $current > 0 && ! isset( $visited[ $current ] ); ++$depth ) {
			$term = get_term( $current, 'promocode_category' );
			if ( ! $term || is_wp_error( $term ) ) {
				break;
			}

			$visited[ $current ] = true;
			$labels[]            = (string) $term->name;
			$current             = (int) $term->parent;
		}

		return empty( $labels ) ? '—' : implode( ' → ', array_reverse( $labels ) );
	}

	/**
	 * Return taxonomy terms in a select-friendly form with full labels.
	 *
	 * @param array<int, WP_Term> $terms Promocode category terms.
	 * @return array<int, array{id:int,label:string}>
	 */
	public static function term_options( array $terms ): array {
		$options = array();

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$options[] = array(
				'id'    => (int) $term->term_id,
				'label' => self::term_path( (int) $term->term_id ),
			);
		}

		return $options;
	}
}
