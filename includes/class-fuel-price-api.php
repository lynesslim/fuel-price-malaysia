<?php
/**
 * Fuel Price API Client
 *
 * Handles fetching, parsing, and storing fuel price data from data.gov.my.
 *
 * @package Fuel_Price_Malaysia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fuel_Price_API {

	/**
	 * API Endpoint URL
	 */
	const API_URL = 'https://api.data.gov.my/data-catalogue/?id=fuelprice&sort=-date&limit=10';

	/**
	 * Option key for storing fuel price data
	 */
	const OPTION_DATA_KEY = 'fuel_price_data';

	/**
	 * Option key for sync status / logs
	 */
	const OPTION_STATUS_KEY = 'fuel_price_sync_status';

	/**
	 * Fetch data from data.gov.my and store in options
	 *
	 * @return array Result containing 'success' (bool) and 'message' (string), and optionally 'data' (array)
	 */
	public static function fetch_and_store() {
		$response = wp_remote_get(
			self::API_URL,
			array(
				'timeout'     => 20,
				'redirection' => 5,
				'headers'     => array(
					'Accept'     => 'application/json',
					'User-Agent' => 'WordPress/FuelPriceMalaysiaPlugin; ' . home_url(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			self::log_status( false, 'Network error: ' . $error_message );
			return array(
				'success' => false,
				'message' => 'Network error: ' . $error_message,
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			$error_message = sprintf( 'HTTP Error %d: %s', $response_code, wp_remote_retrieve_response_message( $response ) );
			self::log_status( false, $error_message );
			return array(
				'success' => false,
				'message' => $error_message,
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data ) ) {
			$error_message = 'Invalid API response format or empty payload received.';
			self::log_status( false, $error_message );
			return array(
				'success' => false,
				'message' => $error_message,
			);
		}

		// Find the latest record with series_type == 'level' and series_type == 'change_weekly'
		$level_record  = null;
		$change_record = null;

		foreach ( $data as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$series_type = isset( $item['series_type'] ) ? strtolower( trim( $item['series_type'] ) ) : '';

			if ( null === $level_record && 'level' === $series_type ) {
				$level_record = $item;
			}

			if ( null === $change_record && 'change_weekly' === $series_type ) {
				$change_record = $item;
			}

			if ( null !== $level_record && null !== $change_record ) {
				break;
			}
		}

		if ( null === $level_record ) {
			$error_message = 'Could not find any retail price record (series_type = level) in API response.';
			self::log_status( false, $error_message );
			return array(
				'success' => false,
				'message' => $error_message,
			);
		}

		$parsed_data = array(
			'date'            => isset( $level_record['date'] ) ? sanitize_text_field( $level_record['date'] ) : '',
			'ron95'           => isset( $level_record['ron95'] ) && is_numeric( $level_record['ron95'] ) ? (float) $level_record['ron95'] : null,
			'ron97'           => isset( $level_record['ron97'] ) && is_numeric( $level_record['ron97'] ) ? (float) $level_record['ron97'] : null,
			'diesel'          => isset( $level_record['diesel'] ) && is_numeric( $level_record['diesel'] ) ? (float) $level_record['diesel'] : null,
			'diesel_eastmsia' => isset( $level_record['diesel_eastmsia'] ) && is_numeric( $level_record['diesel_eastmsia'] ) ? (float) $level_record['diesel_eastmsia'] : null,
			'ron95_skps'      => isset( $level_record['ron95_skps'] ) && is_numeric( $level_record['ron95_skps'] ) ? (float) $level_record['ron95_skps'] : null,
			'ron95_budi95'    => isset( $level_record['ron95_budi95'] ) && is_numeric( $level_record['ron95_budi95'] ) ? (float) $level_record['ron95_budi95'] : null,
			'diesel_budi'     => isset( $level_record['diesel_budi'] ) && is_numeric( $level_record['diesel_budi'] ) ? (float) $level_record['diesel_budi'] : null,
			'diesel_skds'     => isset( $level_record['diesel_skds'] ) && is_numeric( $level_record['diesel_skds'] ) ? (float) $level_record['diesel_skds'] : null,
			'changes'         => array(
				'date'            => isset( $change_record['date'] ) ? sanitize_text_field( $change_record['date'] ) : '',
				'ron95'           => isset( $change_record['ron95'] ) && is_numeric( $change_record['ron95'] ) ? (float) $change_record['ron95'] : 0.0,
				'ron97'           => isset( $change_record['ron97'] ) && is_numeric( $change_record['ron97'] ) ? (float) $change_record['ron97'] : 0.0,
				'diesel'          => isset( $change_record['diesel'] ) && is_numeric( $change_record['diesel'] ) ? (float) $change_record['diesel'] : 0.0,
				'diesel_eastmsia' => isset( $change_record['diesel_eastmsia'] ) && is_numeric( $change_record['diesel_eastmsia'] ) ? (float) $change_record['diesel_eastmsia'] : 0.0,
			),
			'raw_level'       => $level_record,
			'updated_at'      => current_time( 'mysql' ),
			'timestamp'       => time(),
		);

		update_option( self::OPTION_DATA_KEY, $parsed_data );

		$success_message = sprintf(
			'Successfully fetched fuel prices for date %s (Updated: %s).',
			$parsed_data['date'],
			current_time( 'Y-m-d H:i:s' )
		);

		self::log_status( true, $success_message );

		return array(
			'success' => true,
			'message' => $success_message,
			'data'    => $parsed_data,
		);
	}

	/**
	 * Log the sync status
	 *
	 * @param bool   $success Status flag.
	 * @param string $message Status description.
	 */
	public static function log_status( $success, $message ) {
		$status = array(
			'success'    => (bool) $success,
			'message'    => sanitize_text_field( $message ),
			'timestamp'  => time(),
			'time_human' => current_time( 'Y-m-d H:i:s' ),
		);
		update_option( self::OPTION_STATUS_KEY, $status );
	}

	/**
	 * Get stored fuel price data
	 *
	 * @return array
	 */
	public static function get_stored_data() {
		$data = get_option( self::OPTION_DATA_KEY, array() );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Get sync status
	 *
	 * @return array
	 */
	public static function get_sync_status() {
		$status = get_option( self::OPTION_STATUS_KEY, array() );
		return is_array( $status ) ? $status : array();
	}
}
