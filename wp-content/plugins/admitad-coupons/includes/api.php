<?php
/** Admitad OAuth and HTTP client. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function admitad_config( $key ) {
	$constants = array(
		'client_id'     => 'PROMOKODIKI_ADMITAD_CLIENT_ID',
		'client_secret' => 'PROMOKODIKI_ADMITAD_CLIENT_SECRET',
		'website_id'   => 'PROMOKODIKI_ADMITAD_WEBSITE_ID',
	);

	if ( isset( $constants[ $key ] ) && defined( $constants[ $key ] ) ) {
		return (string) constant( $constants[ $key ] );
	}

	return (string) get_option( 'promokodiki_admitad_' . $key, '' );
}

function admitad_clear_cached_token() {
	delete_option( 'admitad_access_token' );
	delete_option( 'admitad_token_expires' );
}

function get_admitad_token( $force_refresh = false ) {
	$cached_token  = get_option( 'admitad_access_token', '' );
	$cached_expiry = (int) get_option( 'admitad_token_expires', 0 );

	if ( ! $force_refresh && $cached_token && $cached_expiry > time() + 60 ) {
		return $cached_token;
	}

	$client_id     = admitad_config( 'client_id' );
	$client_secret = admitad_config( 'client_secret' );
	if ( '' === $client_id || '' === $client_secret ) {
		return new WP_Error( 'admitad_not_configured', 'Admitad credentials are not configured.' );
	}

	$response = wp_remote_post(
		'https://api.admitad.com/token/',
		array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body' => array(
				'grant_type' => 'client_credentials',
				'client_id'  => $client_id,
				'scope'      => 'advcampaigns websites coupons coupons_for_website public_data advcampaigns_for_website',
			),
			'timeout'   => 20,
			'sslverify' => true,
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status = wp_remote_retrieve_response_code( $response );
	$data   = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 200 !== $status || empty( $data['access_token'] ) ) {
		return new WP_Error( 'admitad_oauth_error', 'Admitad OAuth request failed.', array( 'status' => $status ) );
	}

	$expires_in = max( 120, (int) ( $data['expires_in'] ?? 3600 ) );
	update_option( 'admitad_access_token', sanitize_text_field( $data['access_token'] ), false );
	update_option( 'admitad_token_expires', time() + $expires_in - 60, false );

	return $data['access_token'];
}

function admitad_api_get( $path, array $query = array(), $retry = 0 ) {
	$token = get_admitad_token( false );
	if ( is_wp_error( $token ) ) {
		return $token;
	}

	$url      = add_query_arg( $query, 'https://api.admitad.com/' . ltrim( $path, '/' ) );
	$response = wp_remote_get(
		$url,
		array(
			'headers'   => array( 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ),
			'timeout'   => 60,
			'sslverify' => true,
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status = wp_remote_retrieve_response_code( $response );
	if ( 401 === $status && $retry < 1 ) {
		admitad_clear_cached_token();
		$refreshed = get_admitad_token( true );
		return is_wp_error( $refreshed ) ? $refreshed : admitad_api_get( $path, $query, $retry + 1 );
	}

	if ( ( 429 === $status || $status >= 500 ) && $retry < 2 ) {
		$retry_after = min( 10, max( 1, (int) wp_remote_retrieve_header( $response, 'retry-after' ) ) );
		sleep( $retry_after );
		return admitad_api_get( $path, $query, $retry + 1 );
	}

	if ( 200 !== $status ) {
		return new WP_Error( 'admitad_http_error', 'Admitad API request failed.', array( 'status' => $status ) );
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	return JSON_ERROR_NONE === json_last_error()
		? $data
		: new WP_Error( 'admitad_json_error', json_last_error_msg() );
}

function get_admitad_coupons_from_api( $limit = 200, $offset = 0 ) {
	$website_id = admitad_config( 'website_id' );
	if ( '' === $website_id ) {
		return new WP_Error( 'admitad_not_configured', 'Admitad website ID is not configured.' );
	}

	return admitad_api_get(
		'coupons/website/' . rawurlencode( $website_id ) . '/',
		array( 'limit' => absint( $limit ), 'offset' => absint( $offset ), 'region' => 'RU' )
	);
}

