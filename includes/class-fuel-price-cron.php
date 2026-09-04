<?php
/**
 * Fuel Price Cron Manager
 *
 * Manages WP-Cron schedules, recurrence calculation, and automated background sync.
 *
 * @package Fuel_Price_Malaysia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fuel_Price_Cron {

	/**
	 * Cron hook action name
	 */
	const CRON_HOOK = 'fuel_price_cron_hook';

	/**
	 * Settings option name
	 */
	const SETTINGS_KEY = 'fuel_price_settings';

	/**
	 * Initialize cron hooks
	 */
	public static function init() {
		// Register custom cron intervals
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedules' ) );

		// Register execution hook
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_sync' ) );
	}

	/**
	 * Add custom intervals to WP Cron
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function add_cron_schedules( $schedules ) {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS, // 604800 seconds
				'display'  => __( 'Once Weekly', 'fuel-price' ),
			);
		}

		return $schedules;
	}

	/**
	 * Get plugin settings with defaults
	 *
	 * @return array
	 */
	public static function get_settings() {
		$defaults = array(
			'frequency'   => 'weekly',
			'day_of_week' => '3',     // 3 = Wednesday
			'time'        => '17:00', // 5:00 PM (17:00)
			'b7_premium'  => 0.20,    // +RM 0.20 premium for Euro 5 B7
			'enabled'     => 1,
		);

		$settings = get_option( self::SETTINGS_KEY, array() );
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Update settings and re-schedule cron event
	 *
	 * @param array $new_settings Settings array.
	 * @return bool
	 */
	public static function save_settings( $new_settings ) {
		$clean = array(
			'frequency'   => in_array( $new_settings['frequency'], array( 'weekly', 'daily', 'twicedaily', 'hourly' ), true ) ? $new_settings['frequency'] : 'weekly',
			'day_of_week' => (string) max( 0, min( 6, intval( $new_settings['day_of_week'] ) ) ),
			'time'        => sanitize_text_field( $new_settings['time'] ),
			'b7_premium'  => isset( $new_settings['b7_premium'] ) && is_numeric( $new_settings['b7_premium'] ) ? (float) $new_settings['b7_premium'] : 0.20,
			'enabled'     => ! empty( $new_settings['enabled'] ) ? 1 : 0,
		);

		// Validate time format HH:MM
		if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $clean['time'] ) ) {
			$clean['time'] = '17:00';
		}

		$updated = update_option( self::SETTINGS_KEY, $clean );

		// Reschedule cron event
		self::reschedule_event();

		return $updated;
	}

	/**
	 * Calculate the next run timestamp in UTC based on site's timezone
	 *
	 * @return int UTC UNIX timestamp
	 */
	public static function calculate_next_run() {
		$settings = self::get_settings();
		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$now      = new DateTimeImmutable( 'now', $timezone );

		$parts  = explode( ':', $settings['time'] );
		$hour   = isset( $parts[0] ) ? intval( $parts[0] ) : 17;
		$minute = isset( $parts[1] ) ? intval( $parts[1] ) : 0;

		$frequency = $settings['frequency'];

		if ( 'weekly' === $frequency ) {
			$target_dow  = intval( $settings['day_of_week'] );
			$current_dow = intval( $now->format( 'w' ) ); // 0 (Sun) - 6 (Sat)

			$days_to_add = ( $target_dow - $current_dow + 7 ) % 7;

			// If target day is today, check if time has already passed
			$today_target = $now->setTime( $hour, $minute, 0 );
			if ( 0 === $days_to_add && $now >= $today_target ) {
				$days_to_add = 7;
			}

			$target = $now->modify( "+{$days_to_add} days" )->setTime( $hour, $minute, 0 );
			return $target->getTimestamp();

		} elseif ( 'daily' === $frequency ) {
			$today_target = $now->setTime( $hour, $minute, 0 );
			if ( $now >= $today_target ) {
				$target = $now->modify( '+1 day' )->setTime( $hour, $minute, 0 );
			} else {
				$target = $today_target;
			}
			return $target->getTimestamp();

		} elseif ( 'twicedaily' === $frequency ) {
			return $now->modify( '+12 hours' )->getTimestamp();
		} elseif ( 'hourly' === $frequency ) {
			return $now->modify( '+1 hour' )->getTimestamp();
		}

		return $now->modify( '+1 week' )->getTimestamp();
	}

	/**
	 * Reschedule the cron event
	 */
	public static function reschedule_event() {
		self::clear_event();

		$settings = self::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$next_run  = self::calculate_next_run();
		$frequency = $settings['frequency'];

		wp_schedule_event( $next_run, $frequency, self::CRON_HOOK );
	}

	/**
	 * Clear scheduled event
	 */
	public static function clear_event() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Run the sync task (called by WP-Cron)
	 */
	public static function run_sync() {
		Fuel_Price_API::fetch_and_store();
	}

	/**
	 * Get next scheduled run time information
	 *
	 * @return array
	 */
	public static function get_next_run_info() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( ! $timestamp ) {
			return array(
				'scheduled'   => false,
				'timestamp'   => 0,
				'human_time'  => __( 'Not scheduled', 'fuel-price' ),
				'time_diff'   => '',
			);
		}

		$timezone   = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$dt         = ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone );
		$human_time = $dt->format( 'l, j F Y, g:i A' );
		$time_diff  = human_time_diff( time(), $timestamp );

		return array(
			'scheduled'  => true,
			'timestamp'  => $timestamp,
			'human_time' => $human_time,
			'time_diff'  => $time_diff,
		);
	}
}
