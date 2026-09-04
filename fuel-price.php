<?php
/**
 * Plugin Name: FIVE Fuel Price Malaysia (data.gov.my)
 * Plugin URI:  https://five.my/
 * Description: Automated weekly sync of official Malaysian fuel prices (RON95, RON97, Diesel) from data.gov.my, customizable WP-Cron schedule, manual refresh button, and flexible shortcodes for front-end text widgets and page builders.
 * Version:     1.0.0
 * Author:      Supercraft / FIVE Petroleum Malaysia
 * Text Domain: fuel-price
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP:      7.4
 *
 * @package Fuel_Price_Malaysia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FUEL_PRICE_VERSION', '1.0.0' );
define( 'FUEL_PRICE_PLUGIN_FILE', __FILE__ );
define( 'FUEL_PRICE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FUEL_PRICE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load includes
require_once FUEL_PRICE_PLUGIN_DIR . 'includes/class-fuel-price-api.php';
require_once FUEL_PRICE_PLUGIN_DIR . 'includes/class-fuel-price-cron.php';
require_once FUEL_PRICE_PLUGIN_DIR . 'includes/class-fuel-price-shortcodes.php';

// Initialize Plugin Update Checker (YahnisElsts PUC v5.7)
if ( file_exists( FUEL_PRICE_PLUGIN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php' ) ) {
	require_once FUEL_PRICE_PLUGIN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php';

	$fuel_repo_url = apply_filters(
		'fuel_price_github_repo_url',
		'https://github.com/lynesslim/fuel-price-malaysia/'
	);

	$fuel_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		$fuel_repo_url,
		__FILE__,
		'fuel-price-malaysia'
	);

	// Track the main branch for updates
	$fuel_update_checker->setBranch( 'main' );

	// Prefer downloading release assets (e.g. attached zip files in GitHub releases)
	if ( method_exists( $fuel_update_checker->getVcsApi(), 'enableReleaseAssets' ) ) {
		$fuel_update_checker->getVcsApi()->enableReleaseAssets();
	}

	// Allow setting authentication token for private repositories via filter
	$github_token = apply_filters( 'fuel_price_github_token', '' );
	if ( ! empty( $github_token ) ) {
		$fuel_update_checker->setAuthentication( $github_token );
	}
}

if ( is_admin() ) {
	require_once FUEL_PRICE_PLUGIN_DIR . 'includes/class-fuel-price-admin.php';
}

/**
 * Plugin activation handler
 */
function fuel_price_activate() {
	// Set default settings if not exists
	$existing_settings = get_option( Fuel_Price_Cron::SETTINGS_KEY );
	if ( false === $existing_settings ) {
		$default_settings = array(
			'frequency'   => 'weekly',
			'day_of_week' => '3',     // Wednesday
			'time'        => '17:00', // 5:00 PM
			'enabled'     => 1,
		);
		update_option( Fuel_Price_Cron::SETTINGS_KEY, $default_settings );
	}

	// Schedule the WP-Cron event
	Fuel_Price_Cron::reschedule_event();

	// If no price data has ever been stored, trigger an immediate initial fetch
	$existing_data = get_option( Fuel_Price_API::OPTION_DATA_KEY );
	if ( empty( $existing_data ) ) {
		Fuel_Price_API::fetch_and_store();
	}
}
register_activation_hook( __FILE__, 'fuel_price_activate' );

/**
 * Plugin deactivation handler
 */
function fuel_price_deactivate() {
	// Clear scheduled cron events
	Fuel_Price_Cron::clear_event();
}
register_deactivation_hook( __FILE__, 'fuel_price_deactivate' );

/**
 * Initialize components
 */
function fuel_price_init() {
	Fuel_Price_Cron::init();
	Fuel_Price_Shortcodes::init();

	if ( is_admin() ) {
		Fuel_Price_Admin::init();

		// Add "Settings" shortcut link on the Plugins list page
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'fuel_price_add_action_links' );
	}
}
add_action( 'plugins_loaded', 'fuel_price_init' );

/**
 * Add settings action link on plugins page
 *
 * @param array $links Existing links.
 * @return array
 */
function fuel_price_add_action_links( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=' . Fuel_Price_Admin::MENU_SLUG ) ),
		esc_html__( 'Settings', 'fuel-price' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
