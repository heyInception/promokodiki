<?php
/**
 * REST API consumed by the external MTProto worker.
 *
 * @package Promokodiki_Telegram
 */

defined( 'ABSPATH' ) || exit;

final class Promokodiki_Telegram_REST_Controller {
	private const NAMESPACE = 'promokodiki-telegram/v1';
	private const MAX_ITEMS = 200;

	private Promokodiki_Telegram_Promocode_Repository $repository;

	public function __construct( ?Promokodiki_Telegram_Promocode_Repository $repository = null ) {
		$this->repository = $repository ?: new Promokodiki_Telegram_Promocode_Repository();
	}

	public static function register_routes(): void {
		$controller = new self();
		register_rest_route(
			self::NAMESPACE,
			'/config',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $controller, 'config' ),
				'permission_callback' => array( $controller, 'permission' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $controller, 'import' ),
				'permission_callback' => array( $controller, 'permission' ),
			)
		);
	}

	/** @return true|WP_Error */
	public function permission( WP_REST_Request $request ) {
		return Promokodiki_Telegram_Request_Auth::verify( $request );
	}

	public function config( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$channels = array();
		foreach ( Promokodiki_Telegram_Config::channels() as $channel ) {
			if ( empty( $channel['enabled'] ) ) {
				continue;
			}
			$username   = sanitize_key( (string) $channel['username'] );
			$channels[] = array(
				'username'            => $username,
				'last_message_id'     => max( 0, (int) ( $channel['last_message_id'] ?? 0 ) ),
				'tracked_message_ids' => $this->tracked_message_ids( $username ),
			);
		}

		return new WP_REST_Response(
			array(
				'initial_limit' => self::MAX_ITEMS,
				'initial_days'  => 7,
				'channels'      => $channels,
			),
			200
		);
	}

	public function import( WP_REST_Request $request ): WP_REST_Response {
		$params   = $request->get_params();
		$channel  = sanitize_key( ltrim( (string) ( $params['channel'] ?? '' ), '@' ) );
		$channels = Promokodiki_Telegram_Config::channels();
		if ( ! isset( $channels[ $channel ] ) || empty( $channels[ $channel ]['enabled'] ) ) {
			return new WP_REST_Response( array( 'code' => 'telegram_channel_disabled' ), 400 );
		}

		$items      = is_array( $params['items'] ?? null ) ? array_slice( $params['items'], 0, self::MAX_ITEMS ) : array();
		$post_ids   = array();
		$errors     = array();
		$imported   = 0;
		$skip_map   = is_array( $params['skipped'] ?? null ) ? $params['skipped'] : array();
		$skip_count = 0;
		$clean_skip_map = array();
		foreach ( $skip_map as $reason => $count ) {
			$clean_skip_map[ sanitize_key( (string) $reason ) ] = max( 0, (int) $count );
			$skip_count += max( 0, (int) $count );
		}
		$skip_map = $clean_skip_map;

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				++$skip_count;
				continue;
			}
			$item['channel'] = $channel;
			$result          = $this->repository->upsert( $item );
			if ( is_wp_error( $result ) ) {
				++$skip_count;
				$errors[] = $result->get_error_code();
				continue;
			}
			++$imported;
			$post_ids[] = (int) $result['post_id'];
			if ( 'failed' === ( $result['media_status'] ?? '' ) ) {
				$errors[] = 'telegram_media_failed:' . max( 0, (int) ( $item['message_id'] ?? 0 ) );
			}
		}

		$inactive = is_array( $params['inactive_message_ids'] ?? null ) ? array_slice( $params['inactive_message_ids'], 0, self::MAX_ITEMS ) : array();
		foreach ( $inactive as $message_id ) {
			$this->repository->deactivate_source( $channel, (int) $message_id, 'telegram_inactive' );
		}

		$row                    = $channels[ $channel ];
		$row['last_message_id'] = max( (int) ( $row['last_message_id'] ?? 0 ), (int) ( $params['newest_message_id'] ?? 0 ) );
		$row['last_synced_at']  = current_time( 'mysql', true );
		$row['last_status']     = 'success';
		$row['last_error']      = '';
		$row['imported_count']  = max( 0, (int) ( $row['imported_count'] ?? 0 ) ) + $imported;
		$row['skipped_count']   = max( 0, (int) ( $row['skipped_count'] ?? 0 ) ) + $skip_count;
		$channels[ $channel ]   = $row;
		Promokodiki_Telegram_Config::save_channels( $channels );

		Promokodiki_Telegram_Log::add(
			array(
				'channel'   => $channel,
				'status'    => 'success',
				'imported'  => $imported,
				'skipped'   => $skip_count,
				'inspected' => (int) ( $params['inspected_count'] ?? 0 ),
				'details'   => array( 'skipped' => $skip_map, 'errors' => $errors ),
			)
		);

		return new WP_REST_Response(
			array(
				'imported' => $imported,
				'skipped'  => $skip_count,
				'post_ids' => $post_ids,
			),
			200
		);
	}

	/** @return int[] */
	private function tracked_message_ids( string $channel ): array {
		$posts = get_posts(
			array(
				'post_type'              => 'promocode',
				'promokodiki_include_telegram' => true,
				'post_status'            => array( 'publish', 'draft' ),
				'posts_per_page'         => self::MAX_ITEMS,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => '_telegram_channel',
				'meta_value'             => $channel,
			)
		);

		return array_values(
			array_filter(
				array_map(
					static fn( int $post_id ): int => (int) get_post_meta( $post_id, '_telegram_message_id', true ),
					array_map( 'intval', $posts )
				)
			)
		);
	}
}
