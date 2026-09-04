<?php
/**
 * Fuel Price Admin Settings Page
 *
 * Handles the admin dashboard settings, manual sync, and shortcode documentation.
 *
 * @package Fuel_Price_Malaysia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fuel_Price_Admin {

	/**
	 * Page slug
	 */
	const MENU_SLUG = 'fuel-price-settings';

	/**
	 * Initialize admin hooks
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_manual_sync_post' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		// AJAX action for instant manual sync
		add_action( 'wp_ajax_fuel_price_manual_sync', array( __CLASS__, 'ajax_manual_sync' ) );
	}

	/**
	 * Register menu under Settings
	 */
	public static function add_admin_menu() {
		add_options_page(
			__( 'Fuel Price Malaysia Settings', 'fuel-price' ),
			__( 'Fuel Price Malaysia', 'fuel-price' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin CSS and JS
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'settings_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'fuel-price-admin-css',
			FUEL_PRICE_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			FUEL_PRICE_VERSION
		);

		wp_enqueue_script(
			'fuel-price-admin-js',
			FUEL_PRICE_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			FUEL_PRICE_VERSION,
			true
		);

		wp_localize_script(
			'fuel-price-admin-js',
			'fuelPriceAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'syncNonce' => wp_create_nonce( 'fuel_price_manual_sync_nonce' ),
				'strings'   => array(
					'syncing'       => __( 'Fetching live data from data.gov.my...', 'fuel-price' ),
					'syncSuccess'   => __( 'Prices updated successfully!', 'fuel-price' ),
					'syncFailed'    => __( 'Sync failed. Please check error log.', 'fuel-price' ),
					'copied'        => __( 'Copied!', 'fuel-price' ),
				),
			)
		);
	}

	/**
	 * Handle manual sync via standard POST request (fallback)
	 */
	public static function handle_manual_sync_post() {
		if ( ! isset( $_POST['fuel_price_action'] ) || 'manual_sync' !== $_POST['fuel_price_action'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'fuel_price_sync_action', 'fuel_price_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'fuel-price' ) );
		}

		$result = Fuel_Price_API::fetch_and_store();

		if ( $result['success'] ) {
			add_settings_error(
				'fuel_price_messages',
				'fuel_price_synced',
				$result['message'],
				'success'
			);
		} else {
			add_settings_error(
				'fuel_price_messages',
				'fuel_price_sync_failed',
				$result['message'],
				'error'
			);
		}
	}

	/**
	 * Handle saving schedule settings
	 */
	public static function handle_save_settings() {
		if ( ! isset( $_POST['fuel_price_action'] ) || 'save_settings' !== $_POST['fuel_price_action'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'fuel_price_settings_action', 'fuel_price_settings_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'fuel-price' ) );
		}

		$settings = array(
			'enabled'     => isset( $_POST['fuel_price_enabled'] ) ? 1 : 0,
			'frequency'   => isset( $_POST['fuel_price_frequency'] ) ? sanitize_text_field( $_POST['fuel_price_frequency'] ) : 'weekly',
			'day_of_week' => isset( $_POST['fuel_price_day_of_week'] ) ? sanitize_text_field( $_POST['fuel_price_day_of_week'] ) : '3',
			'time'        => isset( $_POST['fuel_price_time'] ) ? sanitize_text_field( $_POST['fuel_price_time'] ) : '17:00',
		);

		Fuel_Price_Cron::save_settings( $settings );

		add_settings_error(
			'fuel_price_messages',
			'fuel_price_saved',
			__( 'Settings saved and cron schedule updated successfully.', 'fuel-price' ),
			'success'
		);
	}

	/**
	 * AJAX endpoint for instant manual sync
	 */
	public static function ajax_manual_sync() {
		check_ajax_referer( 'fuel_price_manual_sync_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'fuel-price' ) ) );
		}

		$result = Fuel_Price_API::fetch_and_store();

		if ( $result['success'] ) {
			$data     = Fuel_Price_API::get_stored_data();
			$next_run = Fuel_Price_Cron::get_next_run_info();

			wp_send_json_success(
				array(
					'message'     => $result['message'],
					'data'        => $data,
					'nextRun'     => $next_run,
					'lastUpdated' => current_time( 'Y-m-d H:i:s' ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => $result['message'],
				)
			);
		}
	}

	/**
	 * Render the full settings page HTML
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$data        = Fuel_Price_API::get_stored_data();
		$status      = Fuel_Price_API::get_sync_status();
		$settings    = Fuel_Price_Cron::get_settings();
		$next_run    = Fuel_Price_Cron::get_next_run_info();
		$site_time   = current_time( 'Y-m-d H:i:s' );
		$timezone_str = wp_timezone_string();

		?>
		<div class="wrap fuel-price-admin-wrap">
			<header class="fuel-price-header">
				<div class="header-left">
					<span class="header-badge">FIVE Petroleum Malaysia</span>
					<h1 class="header-title">⛽ Fuel Price Malaysia Settings</h1>
					<p class="header-desc">
						Automated sync with the official Malaysian Government Open Data API (<a href="https://data.gov.my/data-catalogue/fuelprice" target="_blank" rel="noopener">data.gov.my</a>).
					</p>
				</div>
				<div class="header-actions">
					<button type="button" id="btn-ajax-sync" class="button button-primary button-hero">
						<span class="dashicons dashicons-update"></span>
						<span class="btn-text">Sync Prices Now</span>
					</button>
				</div>
			</header>

			<?php settings_errors( 'fuel_price_messages' ); ?>

			<div id="fuel-price-ajax-notice" class="notice notice-info is-dismissible" style="display: none;">
				<p></p>
			</div>

			<div class="fuel-price-grid">

				<!-- Left Column: Live Status & Settings -->
				<div class="fuel-price-main-col">

					<!-- Card: Current Live Prices -->
					<div class="fuel-card">
						<div class="fuel-card-header">
							<div class="card-title-group">
								<h2>Current Stored Fuel Prices</h2>
								<?php if ( ! empty( $data['date'] ) ) : ?>
									<span class="pill pill-date">Effective Date: <strong><?php echo esc_html( gmdate( 'j M Y', strtotime( $data['date'] ) ) ); ?></strong></span>
								<?php endif; ?>
							</div>
							<div class="card-header-meta">
								<?php if ( ! empty( $status['time_human'] ) ) : ?>
									<span class="last-sync-meta">Last Sync: <?php echo esc_html( $status['time_human'] ); ?></span>
								<?php endif; ?>
							</div>
						</div>

						<div class="fuel-card-body">
							<div class="prices-display-grid">
								<!-- RON95 -->
								<div class="price-box ron95">
									<div class="price-tag">RON95</div>
									<div class="price-value" id="val-ron95">
										<span class="unit">RM</span> <?php echo esc_html( isset( $data['ron95'] ) ? number_format( $data['ron95'], 2 ) : 'N/A' ); ?>
									</div>
									<div class="price-change" id="chg-ron95">
										<?php echo self::render_diff_badge( isset( $data['changes']['ron95'] ) ? $data['changes']['ron95'] : 0 ); ?>
									</div>
								</div>

								<!-- RON97 -->
								<div class="price-box ron97">
									<div class="price-tag">RON97</div>
									<div class="price-value" id="val-ron97">
										<span class="unit">RM</span> <?php echo esc_html( isset( $data['ron97'] ) ? number_format( $data['ron97'], 2 ) : 'N/A' ); ?>
									</div>
									<div class="price-change" id="chg-ron97">
										<?php echo self::render_diff_badge( isset( $data['changes']['ron97'] ) ? $data['changes']['ron97'] : 0 ); ?>
									</div>
								</div>

								<!-- Diesel Peninsular -->
								<div class="price-box diesel">
									<div class="price-tag">Diesel (Peninsular)</div>
									<div class="price-value" id="val-diesel">
										<span class="unit">RM</span> <?php echo esc_html( isset( $data['diesel'] ) ? number_format( $data['diesel'], 2 ) : 'N/A' ); ?>
									</div>
									<div class="price-change" id="chg-diesel">
										<?php echo self::render_diff_badge( isset( $data['changes']['diesel'] ) ? $data['changes']['diesel'] : 0 ); ?>
									</div>
								</div>

								<!-- Diesel East Malaysia -->
								<div class="price-box diesel-east">
									<div class="price-tag">Diesel (Sabah / Sarawak)</div>
									<div class="price-value" id="val-diesel-east">
										<span class="unit">RM</span> <?php echo esc_html( isset( $data['diesel_eastmsia'] ) ? number_format( $data['diesel_eastmsia'], 2 ) : 'N/A' ); ?>
									</div>
									<div class="price-change" id="chg-diesel-east">
										<?php echo self::render_diff_badge( isset( $data['changes']['diesel_eastmsia'] ) ? $data['changes']['diesel_eastmsia'] : 0 ); ?>
									</div>
								</div>
							</div>

							<?php if ( ! empty( $data['ron95_skps'] ) || ! empty( $data['diesel_budi'] ) || ! empty( $data['diesel_skds'] ) ) : ?>
								<details class="targeted-subsidies-toggle">
									<summary>View Targeted / Subsidized Categories (SKPS, BUDI, SKDS)</summary>
									<div class="targeted-subsidies-grid">
										<?php if ( ! empty( $data['ron95_skps'] ) ) : ?>
											<div><strong>RON95 SKPS:</strong> RM <?php echo esc_html( number_format( $data['ron95_skps'], 2 ) ); ?></div>
										<?php endif; ?>
										<?php if ( ! empty( $data['ron95_budi95'] ) ) : ?>
											<div><strong>RON95 BUDI95:</strong> RM <?php echo esc_html( number_format( $data['ron95_budi95'], 2 ) ); ?></div>
										<?php endif; ?>
										<?php if ( ! empty( $data['diesel_budi'] ) ) : ?>
											<div><strong>Diesel BUDI:</strong> RM <?php echo esc_html( number_format( $data['diesel_budi'], 2 ) ); ?></div>
										<?php endif; ?>
										<?php if ( ! empty( $data['diesel_skds'] ) ) : ?>
											<div><strong>Diesel SKDS:</strong> RM <?php echo esc_html( number_format( $data['diesel_skds'], 2 ) ); ?></div>
										<?php endif; ?>
									</div>
								</details>
							<?php endif; ?>
						</div>
					</div>

					<!-- Card: Cron Schedule Settings Form -->
					<div class="fuel-card">
						<div class="fuel-card-header">
							<h2>Automated Cronjob Schedule</h2>
						</div>
						<div class="fuel-card-body">
							<form method="post" action="">
								<?php wp_nonce_field( 'fuel_price_settings_action', 'fuel_price_settings_nonce' ); ?>
								<input type="hidden" name="fuel_price_action" value="save_settings" />

								<table class="form-table fuel-settings-table">
									<tr>
										<th scope="row">Enable Cron Sync</th>
										<td>
											<label class="switch-label">
												<input type="checkbox" name="fuel_price_enabled" value="1" <?php checked( $settings['enabled'], 1 ); ?> />
												<span>Automatically fetch and update fuel prices in the background</span>
											</label>
										</td>
									</tr>

									<tr>
										<th scope="row"><label for="fuel_price_frequency">Frequency</label></th>
										<td>
											<select name="fuel_price_frequency" id="fuel_price_frequency">
												<option value="weekly" <?php selected( $settings['frequency'], 'weekly' ); ?>>Once a Week (Recommended)</option>
												<option value="daily" <?php selected( $settings['frequency'], 'daily' ); ?>>Daily</option>
												<option value="twicedaily" <?php selected( $settings['frequency'], 'twicedaily' ); ?>>Twice Daily</option>
												<option value="hourly" <?php selected( $settings['frequency'], 'hourly' ); ?>>Hourly</option>
											</select>
											<p class="description">Malaysian fuel prices are revised weekly by the Ministry of Finance.</p>
										</td>
									</tr>

									<tr id="row-day-of-week" style="<?php echo ( 'weekly' !== $settings['frequency'] ) ? 'display:none;' : ''; ?>">
										<th scope="row"><label for="fuel_price_day_of_week">Day of the Week</label></th>
										<td>
											<select name="fuel_price_day_of_week" id="fuel_price_day_of_week">
												<option value="1" <?php selected( $settings['day_of_week'], '1' ); ?>>Monday</option>
												<option value="2" <?php selected( $settings['day_of_week'], '2' ); ?>>Tuesday</option>
												<option value="3" <?php selected( $settings['day_of_week'], '3' ); ?>>Wednesday (Government announcement day)</option>
												<option value="4" <?php selected( $settings['day_of_week'], '4' ); ?>>Thursday</option>
												<option value="5" <?php selected( $settings['day_of_week'], '5' ); ?>>Friday</option>
												<option value="6" <?php selected( $settings['day_of_week'], '6' ); ?>>Saturday</option>
												<option value="0" <?php selected( $settings['day_of_week'], '0' ); ?>>Sunday</option>
											</select>
											<p class="description">Select the day when the cron job should run.</p>
										</td>
									</tr>

									<tr>
										<th scope="row"><label for="fuel_price_time">Time of Day</label></th>
										<td>
											<input type="time" name="fuel_price_time" id="fuel_price_time" value="<?php echo esc_attr( $settings['time'] ); ?>" required />
											<p class="description">
												Current site timezone is <strong><?php echo esc_html( $timezone_str ); ?></strong>. Current site time: <strong><?php echo esc_html( $site_time ); ?></strong>.
												<br>New prices are typically posted between 17:00 (5:00 PM) and 18:00 (6:00 PM).
											</p>
										</td>
									</tr>
								</table>

								<div class="cron-status-box">
									<div class="status-indicator">
										<span class="dashicons dashicons-clock"></span>
										<div class="status-text">
											<strong>Next Scheduled Run:</strong>
											<span id="next-run-human"><?php echo esc_html( $next_run['human_time'] ); ?></span>
											<?php if ( ! empty( $next_run['time_diff'] ) ) : ?>
												<span class="badge-diff" id="next-run-diff">(in <?php echo esc_html( $next_run['time_diff'] ); ?>)</span>
											<?php endif; ?>
										</div>
									</div>
								</div>

								<p class="submit">
									<button type="submit" class="button button-primary">Save Schedule Settings</button>
								</p>
							</form>
						</div>
					</div>

				</div>

				<!-- Right Column: Shortcode Reference & Styling Guide -->
				<div class="fuel-price-side-col">

					<div class="fuel-card shortcode-instructions-card">
						<div class="fuel-card-header">
							<div class="card-title-group">
								<span class="dashicons dashicons-editor-code"></span>
								<h2>Shortcode Instructions & Usage Guide</h2>
							</div>
						</div>
						<div class="fuel-card-body">
							<div class="instruction-callout">
								<h4>💡 How to Display in Text Widgets:</h4>
								<ol>
									<li>Go to <strong>Appearance &gt; Widgets</strong> in WordPress.</li>
									<li>Add a <strong>Text</strong> or <strong>Custom HTML</strong> widget to your Sidebar, Footer, or Header area.</li>
									<li>Paste any of the shortcodes below into the widget content.</li>
									<li>Click <strong>Save</strong> — shortcodes are automatically processed in widgets!</li>
								</ol>
							</div>

							<h3 class="section-subtitle">1. Quick Copy Shortcodes</h3>
							<p class="side-desc">Click <strong>Copy</strong> on any shortcode to copy it to your clipboard:</p>

							<div class="shortcode-copy-list">
								<!-- RON95 -->
								<div class="shortcode-copy-item">
									<div class="item-label">RON95 Retail Price</div>
									<div class="item-code">
										<code>[fuel_price type="ron95"]</code>
										<button type="button" class="btn-copy" data-copy='[fuel_price type="ron95"]' title="Copy Shortcode">Copy</button>
									</div>
									<div class="item-alias">Shorthand: <code>[fuel_ron95]</code></div>
								</div>

								<!-- RON97 -->
								<div class="shortcode-copy-item">
									<div class="item-label">RON97 Retail Price</div>
									<div class="item-code">
										<code>[fuel_price type="ron97"]</code>
										<button type="button" class="btn-copy" data-copy='[fuel_price type="ron97"]' title="Copy Shortcode">Copy</button>
									</div>
									<div class="item-alias">Shorthand: <code>[fuel_ron97]</code></div>
								</div>

								<!-- Diesel Peninsular -->
								<div class="shortcode-copy-item">
									<div class="item-label">Diesel (Peninsular)</div>
									<div class="item-code">
										<code>[fuel_price type="diesel"]</code>
										<button type="button" class="btn-copy" data-copy='[fuel_price type="diesel"]' title="Copy Shortcode">Copy</button>
									</div>
									<div class="item-alias">Shorthand: <code>[fuel_diesel]</code></div>
								</div>

								<!-- Diesel Sabah & Sarawak -->
								<div class="shortcode-copy-item">
									<div class="item-label">Diesel (Sabah / Sarawak)</div>
									<div class="item-code">
										<code>[fuel_price type="diesel_eastmsia"]</code>
										<button type="button" class="btn-copy" data-copy='[fuel_price type="diesel_eastmsia"]' title="Copy Shortcode">Copy</button>
									</div>
									<div class="item-alias">Shorthand: <code>[fuel_diesel_east]</code></div>
								</div>

								<!-- Effective Date -->
								<div class="shortcode-copy-item">
									<div class="item-label">Effective Date</div>
									<div class="item-code">
										<code>[fuel_price type="date" format="d M Y"]</code>
										<button type="button" class="btn-copy" data-copy='[fuel_price type="date" format="d M Y"]' title="Copy Shortcode">Copy</button>
									</div>
									<div class="item-alias">Shorthand: <code>[fuel_price_date]</code></div>
								</div>

								<!-- Weekly Change -->
								<div class="shortcode-copy-item">
									<div class="item-label">Weekly Price Change (RON95)</div>
									<div class="item-code">
										<code>[fuel_price type="change_ron95"]</code>
										<button type="button" class="btn-copy" data-copy='[fuel_price type="change_ron95"]' title="Copy Shortcode">Copy</button>
									</div>
									<div class="item-alias">Also: <code>change_ron97</code>, <code>change_diesel</code></div>
								</div>

								<!-- Pre-styled Cards -->
								<div class="shortcode-copy-item">
									<div class="item-label">Pre-styled Cards Grid (All Fuels)</div>
									<div class="item-code">
										<code>[fuel_price_cards]</code>
										<button type="button" class="btn-copy" data-copy="[fuel_price_cards]" title="Copy Shortcode">Copy</button>
									</div>
									<div class="item-alias">Dark theme: <code>[fuel_price_cards theme="dark"]</code></div>
								</div>

								<!-- Pre-styled Table -->
								<div class="shortcode-copy-item">
									<div class="item-label">Pre-styled Comparison Table</div>
									<div class="item-code">
										<code>[fuel_price_table]</code>
										<button type="button" class="btn-copy" data-copy="[fuel_price_table]" title="Copy Shortcode">Copy</button>
									</div>
								</div>

								<!-- Ticker Bar -->
								<div class="shortcode-copy-item">
									<div class="item-label">Header Ticker Bar</div>
									<div class="item-code">
										<code>[fuel_price_ticker]</code>
										<button type="button" class="btn-copy" data-copy="[fuel_price_ticker]" title="Copy Shortcode">Copy</button>
									</div>
								</div>
							</div>

							<h3 class="section-subtitle" style="margin-top: 24px;">2. Shortcode Customization Parameters</h3>
							<table class="parameters-table">
								<thead>
									<tr>
										<th>Parameter</th>
										<th>Default</th>
										<th>Description</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td><code>type</code></td>
										<td><code>ron95</code></td>
										<td><code>ron95</code>, <code>ron97</code>, <code>diesel</code>, <code>diesel_eastmsia</code>, <code>date</code>, <code>change_ron95</code>, etc.</td>
									</tr>
									<tr>
										<td><code>prefix</code></td>
										<td><code>"RM "</code></td>
										<td>Text placed before the price (e.g. <code>prefix="RM "</code> or <code>prefix=""</code>).</td>
									</tr>
									<tr>
										<td><code>suffix</code></td>
										<td><code>""</code></td>
										<td>Text placed after the price (e.g. <code>suffix=" /L"</code> or <code>suffix=" per litre"</code>).</td>
									</tr>
									<tr>
										<td><code>decimals</code></td>
										<td><code>2</code></td>
										<td>Number of decimal places (e.g. <code>decimals="2"</code>).</td>
									</tr>
									<tr>
										<td><code>wrap</code></td>
										<td><code>"span"</code></td>
										<td>Set to <code>wrap="none"</code> to output pure raw text without HTML tags for custom styling.</td>
									</tr>
									<tr>
										<td><code>format</code></td>
										<td><code>"d M Y"</code></td>
										<td>Date format (used when <code>type="date"</code>). E.g. <code>"j F Y"</code> or <code>"d/m/Y"</code>.</td>
									</tr>
								</tbody>
							</table>

							<div class="styling-tips-box">
								<h3>🎨 3. Example: Custom Styling in a Widget or Elementor</h3>
								<p>Wrap the shortcode in your own HTML / CSS tags for complete design control:</p>
								<pre><code>&lt;div style="background:#1f2937; color:#fff; padding:20px; border-radius:12px;"&gt;
  &lt;h4 style="color:#f59e0b; margin:0;"&gt;FIVE RON95&lt;/h4&gt;
  &lt;div style="font-size:36px; font-weight:bold; color:#fff;"&gt;
    [fuel_price type="ron95" prefix="RM " suffix=" /L" wrap="none"]
  &lt;/div&gt;
  &lt;small style="color:#9ca3af;"&gt;Effective: [fuel_price type="date" format="j F Y"]&lt;/small&gt;
&lt;/div&gt;</code></pre>
							</div>
						</div>
					</div>

				</div>

			</div>
		</div>
		<?php
	}

	/**
	 * Helper to render difference badge in admin
	 */
	private static function render_diff_badge( $diff ) {
		$diff = floatval( $diff );
		if ( $diff > 0 ) {
			return '<span class="diff-badge diff-up">▲ +RM ' . esc_html( number_format( abs( $diff ), 2 ) ) . '</span>';
		} elseif ( $diff < 0 ) {
			return '<span class="diff-badge diff-down">▼ -RM ' . esc_html( number_format( abs( $diff ), 2 ) ) . '</span>';
		}
		return '<span class="diff-badge diff-neutral">━ Unchanged</span>';
	}
}
