<?php
/**
 * Fuel Price Shortcodes
 *
 * Registers shortcodes for displaying fuel prices and enables shortcodes in widgets.
 *
 * @package Fuel_Price_Malaysia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fuel_Price_Shortcodes {

	/**
	 * Initialize shortcodes and widget filters
	 */
	public static function init() {
		// Enable shortcodes in WordPress text & HTML widgets
		add_filter( 'widget_text', 'do_shortcode' );
		add_filter( 'widget_custom_html_content', 'do_shortcode' );

		// Register core shortcode
		add_shortcode( 'fuel_price', array( __CLASS__, 'render_fuel_price' ) );

		// Register convenience aliases
		add_shortcode( 'fuel_ron95', array( __CLASS__, 'render_ron95' ) );
		add_shortcode( 'fuel_ron97', array( __CLASS__, 'render_ron97' ) );
		add_shortcode( 'fuel_diesel', array( __CLASS__, 'render_diesel' ) );
		add_shortcode( 'fuel_diesel_east', array( __CLASS__, 'render_diesel_east' ) );
		add_shortcode( 'fuel_price_date', array( __CLASS__, 'render_date' ) );

		// Register pre-built responsive components
		add_shortcode( 'fuel_price_cards', array( __CLASS__, 'render_cards' ) );
		add_shortcode( 'fuel_price_table', array( __CLASS__, 'render_table' ) );
		add_shortcode( 'fuel_price_ticker', array( __CLASS__, 'render_ticker' ) );

		// Enqueue frontend styles if shortcode is used
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Register frontend assets
	 */
	public static function register_assets() {
		wp_register_style(
			'fuel-price-frontend',
			FUEL_PRICE_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			FUEL_PRICE_VERSION
		);
	}

	/**
	 * Render single fuel price value
	 *
	 * Attributes:
	 *   type: ron95, ron97, diesel, diesel_eastmsia, ron95_skps, ron95_budi95, diesel_budi, diesel_skds, date,
	 *         change_ron95, change_ron97, change_diesel, change_diesel_eastmsia
	 *   prefix: default "RM " (or "" for dates)
	 *   suffix: default ""
	 *   decimals: default 2
	 *   format: date format, default "d M Y"
	 *   wrap: "span", "div", or "none" (default "span")
	 *   class: custom CSS class
	 *   fallback: default "N/A"
	 *   show_sign: true/false for change amounts
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Formatted output.
	 */
	public static function render_fuel_price( $atts ) {
		$atts = shortcode_atts(
			array(
				'type'      => 'ron95',
				'prefix'    => 'RM ',
				'suffix'    => '',
				'decimals'  => 2,
				'format'    => 'd M Y',
				'wrap'      => 'span',
				'class'     => '',
				'fallback'  => 'N/A',
				'show_sign' => 'true',
			),
			$atts,
			'fuel_price'
		);

		$type = strtolower( trim( $atts['type'] ) );

		// Normalize aliases
		$aliases = array(
			'diesel_peninsular'    => 'diesel',
			'diesel_peninsula'     => 'diesel',
			'diesel_east'          => 'diesel_eastmsia',
			'diesel_sabah_sarawak' => 'diesel_eastmsia',
			'effective_date'       => 'date',
			'price_date'           => 'date',
		);
		if ( isset( $aliases[ $type ] ) ) {
			$type = $aliases[ $type ];
		}

		$data = Fuel_Price_API::get_stored_data();

		// Handle Date
		if ( 'date' === $type ) {
			if ( empty( $data['date'] ) ) {
				return esc_html( $atts['fallback'] );
			}
			$prefix    = ( 'RM ' === $atts['prefix'] ) ? '' : $atts['prefix'];
			$timestamp = strtotime( $data['date'] );
			$formatted = $timestamp ? gmdate( $atts['format'], $timestamp ) : $data['date'];
			$output    = esc_html( $prefix . $formatted . $atts['suffix'] );

			return self::wrap_output( $output, 'date', $atts );
		}

		// Handle Weekly Change Values
		if ( strpos( $type, 'change_' ) === 0 ) {
			$fuel_key = substr( $type, 7 );
			if ( isset( $aliases[ $fuel_key ] ) ) {
				$fuel_key = $aliases[ $fuel_key ];
			}

			$changes = isset( $data['changes'] ) && is_array( $data['changes'] ) ? $data['changes'] : array();
			if ( ! isset( $changes[ $fuel_key ] ) || ! is_numeric( $changes[ $fuel_key ] ) ) {
				return esc_html( $atts['fallback'] );
			}

			$change_val = (float) $changes[ $fuel_key ];
			$sign       = '';
			if ( filter_var( $atts['show_sign'], FILTER_VALIDATE_BOOLEAN ) ) {
				if ( $change_val > 0 ) {
					$sign = '+';
				} elseif ( $change_val < 0 ) {
					$sign = '-';
				}
			}

			$formatted_number = number_format( abs( $change_val ), intval( $atts['decimals'] ), '.', '' );
			$output           = esc_html( $sign . $atts['prefix'] . $formatted_number . $atts['suffix'] );

			$direction_class = ( $change_val > 0 ) ? 'change-up' : ( ( $change_val < 0 ) ? 'change-down' : 'change-neutral' );
			$classes         = 'fuel-change ' . $direction_class . ' ' . sanitize_html_class( $atts['class'] );

			return self::wrap_output( $output, $type, $atts, $classes );
		}

		// Handle Fuel Prices
		if ( ! isset( $data[ $type ] ) || null === $data[ $type ] || ! is_numeric( $data[ $type ] ) ) {
			return esc_html( $atts['fallback'] );
		}

		$price_val        = (float) $data[ $type ];
		$formatted_number = number_format( $price_val, intval( $atts['decimals'] ), '.', '' );
		$output           = esc_html( $atts['prefix'] . $formatted_number . $atts['suffix'] );

		return self::wrap_output( $output, $type, $atts );
	}

	/**
	 * Helper to wrap output in HTML container or return pure text
	 */
	private static function wrap_output( $text, $type, $atts, $custom_classes = '' ) {
		$wrap = strtolower( trim( $atts['wrap'] ) );

		if ( 'none' === $wrap || 'false' === $wrap ) {
			return $text;
		}

		$tag     = in_array( $wrap, array( 'div', 'p', 'b', 'strong', 'em' ), true ) ? $wrap : 'span';
		$classes = array( 'five-fuel-price', 'fuel-type-' . sanitize_html_class( $type ) );

		if ( ! empty( $custom_classes ) ) {
			$classes[] = $custom_classes;
		}
		if ( ! empty( $atts['class'] ) ) {
			$classes[] = sanitize_html_class( $atts['class'] );
		}

		return sprintf(
			'<%1$s class="%2$s" data-fuel-type="%3$s">%4$s</%1$s>',
			$tag,
			esc_attr( implode( ' ', array_filter( $classes ) ) ),
			esc_attr( $type ),
			$text
		);
	}

	/**
	 * Convenience Shortcode: [fuel_ron95]
	 */
	public static function render_ron95( $atts ) {
		$atts         = is_array( $atts ) ? $atts : array();
		$atts['type'] = 'ron95';
		return self::render_fuel_price( $atts );
	}

	/**
	 * Convenience Shortcode: [fuel_ron97]
	 */
	public static function render_ron97( $atts ) {
		$atts         = is_array( $atts ) ? $atts : array();
		$atts['type'] = 'ron97';
		return self::render_fuel_price( $atts );
	}

	/**
	 * Convenience Shortcode: [fuel_diesel]
	 */
	public static function render_diesel( $atts ) {
		$atts         = is_array( $atts ) ? $atts : array();
		$atts['type'] = 'diesel';
		return self::render_fuel_price( $atts );
	}

	/**
	 * Convenience Shortcode: [fuel_diesel_east]
	 */
	public static function render_diesel_east( $atts ) {
		$atts         = is_array( $atts ) ? $atts : array();
		$atts['type'] = 'diesel_eastmsia';
		return self::render_fuel_price( $atts );
	}

	/**
	 * Convenience Shortcode: [fuel_price_date]
	 */
	public static function render_date( $atts ) {
		$atts           = is_array( $atts ) ? $atts : array();
		$atts['type']   = 'date';
		$atts['prefix'] = isset( $atts['prefix'] ) ? $atts['prefix'] : '';
		return self::render_fuel_price( $atts );
	}

	/**
	 * Render ready-to-use modern Cards layout: [fuel_price_cards]
	 */
	public static function render_cards( $atts ) {
		wp_enqueue_style( 'fuel-price-frontend' );

		$atts = shortcode_atts(
			array(
				'title'       => __( 'Current Fuel Prices in Malaysia', 'fuel-price' ),
				'show_date'   => 'true',
				'show_change' => 'true',
				'theme'       => 'light', // 'light' or 'dark'
				'class'       => '',
			),
			$atts,
			'fuel_price_cards'
		);

		$data = Fuel_Price_API::get_stored_data();
		if ( empty( $data ) ) {
			return '<div class="fuel-price-empty">' . esc_html__( 'Fuel price data currently unavailable.', 'fuel-price' ) . '</div>';
		}

		$fuels = array(
			array(
				'key'       => 'ron95',
				'title'     => 'RON95 Petrol',
				'badge'     => 'RON 95',
				'badge_cls' => 'badge-ron95',
				'price'     => isset( $data['ron95'] ) ? $data['ron95'] : null,
				'change'    => isset( $data['changes']['ron95'] ) ? $data['changes']['ron95'] : 0,
			),
			array(
				'key'       => 'ron97',
				'title'     => 'RON97 Petrol',
				'badge'     => 'RON 97',
				'badge_cls' => 'badge-ron97',
				'price'     => isset( $data['ron97'] ) ? $data['ron97'] : null,
				'change'    => isset( $data['changes']['ron97'] ) ? $data['changes']['ron97'] : 0,
			),
			array(
				'key'       => 'diesel',
				'title'     => 'Diesel (Peninsular)',
				'badge'     => 'EURO 5',
				'badge_cls' => 'badge-diesel',
				'price'     => isset( $data['diesel'] ) ? $data['diesel'] : null,
				'change'    => isset( $data['changes']['diesel'] ) ? $data['changes']['diesel'] : 0,
			),
			array(
				'key'       => 'diesel_eastmsia',
				'title'     => 'Diesel (Sabah & Sarawak)',
				'badge'     => 'EURO 5',
				'badge_cls' => 'badge-diesel-east',
				'price'     => isset( $data['diesel_eastmsia'] ) ? $data['diesel_eastmsia'] : null,
				'change'    => isset( $data['changes']['diesel_eastmsia'] ) ? $data['changes']['diesel_eastmsia'] : 0,
			),
		);

		$theme_class = ( 'dark' === $atts['theme'] ) ? 'theme-dark' : 'theme-light';

		ob_start();
		?>
		<div class="five-fuel-cards-container <?php echo esc_attr( $theme_class . ' ' . $atts['class'] ); ?>">
			<?php if ( ! empty( $atts['title'] ) ) : ?>
				<div class="five-fuel-header">
					<h3 class="five-fuel-title"><?php echo esc_html( $atts['title'] ); ?></h3>
					<?php if ( filter_var( $atts['show_date'], FILTER_VALIDATE_BOOLEAN ) && ! empty( $data['date'] ) ) : ?>
						<span class="five-fuel-date-badge">
							<span class="date-icon">📅</span> Effective: <?php echo esc_html( gmdate( 'j M Y', strtotime( $data['date'] ) ) ); ?>
						</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="five-fuel-grid">
				<?php foreach ( $fuels as $fuel ) : ?>
					<div class="five-fuel-card card-<?php echo esc_attr( $fuel['key'] ); ?>">
						<div class="card-top">
							<span class="card-fuel-badge <?php echo esc_attr( $fuel['badge_cls'] ); ?>"><?php echo esc_html( $fuel['badge'] ); ?></span>
							<span class="card-fuel-name"><?php echo esc_html( $fuel['title'] ); ?></span>
						</div>
						<div class="card-middle">
							<span class="currency">RM</span>
							<span class="amount"><?php echo esc_html( null !== $fuel['price'] ? number_format( $fuel['price'], 2 ) : 'N/A' ); ?></span>
							<span class="unit">/ litre</span>
						</div>
						<?php if ( filter_var( $atts['show_change'], FILTER_VALIDATE_BOOLEAN ) ) : ?>
							<div class="card-bottom">
								<?php
								$diff = floatval( $fuel['change'] );
								if ( $diff > 0 ) :
									?>
									<span class="change-tag change-up">▲ +RM <?php echo esc_html( number_format( abs( $diff ), 2 ) ); ?></span>
								<?php elseif ( $diff < 0 ) : ?>
									<span class="change-tag change-down">▼ -RM <?php echo esc_html( number_format( abs( $diff ), 2 ) ); ?></span>
								<?php else : ?>
									<span class="change-tag change-neutral">━ Unchanged</span>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render ready-to-use Table layout: [fuel_price_table]
	 */
	public static function render_table( $atts ) {
		wp_enqueue_style( 'fuel-price-frontend' );

		$atts = shortcode_atts(
			array(
				'class' => '',
			),
			$atts,
			'fuel_price_table'
		);

		$data = Fuel_Price_API::get_stored_data();
		if ( empty( $data ) ) {
			return '<div class="fuel-price-empty">' . esc_html__( 'Fuel price data currently unavailable.', 'fuel-price' ) . '</div>';
		}

		$date_display = ! empty( $data['date'] ) ? gmdate( 'd M Y', strtotime( $data['date'] ) ) : 'N/A';

		ob_start();
		?>
		<div class="five-fuel-table-wrapper <?php echo esc_attr( $atts['class'] ); ?>">
			<table class="five-fuel-table">
				<thead>
					<tr>
						<th>Fuel Type</th>
						<th>Price (RM / Litre)</th>
						<th>Weekly Change</th>
						<th>Effective Date</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong>RON95</strong> (Petrol)</td>
						<td class="price-col">RM <?php echo esc_html( isset( $data['ron95'] ) ? number_format( $data['ron95'], 2 ) : 'N/A' ); ?></td>
						<td><?php echo self::render_change_pill( isset( $data['changes']['ron95'] ) ? $data['changes']['ron95'] : 0 ); ?></td>
						<td><?php echo esc_html( $date_display ); ?></td>
					</tr>
					<tr>
						<td><strong>RON97</strong> (Petrol)</td>
						<td class="price-col">RM <?php echo esc_html( isset( $data['ron97'] ) ? number_format( $data['ron97'], 2 ) : 'N/A' ); ?></td>
						<td><?php echo self::render_change_pill( isset( $data['changes']['ron97'] ) ? $data['changes']['ron97'] : 0 ); ?></td>
						<td><?php echo esc_html( $date_display ); ?></td>
					</tr>
					<tr>
						<td><strong>Diesel</strong> (Peninsular)</td>
						<td class="price-col">RM <?php echo esc_html( isset( $data['diesel'] ) ? number_format( $data['diesel'], 2 ) : 'N/A' ); ?></td>
						<td><?php echo self::render_change_pill( isset( $data['changes']['diesel'] ) ? $data['changes']['diesel'] : 0 ); ?></td>
						<td><?php echo esc_html( $date_display ); ?></td>
					</tr>
					<tr>
						<td><strong>Diesel</strong> (Sabah &amp; Sarawak)</td>
						<td class="price-col">RM <?php echo esc_html( isset( $data['diesel_eastmsia'] ) ? number_format( $data['diesel_eastmsia'], 2 ) : 'N/A' ); ?></td>
						<td><?php echo self::render_change_pill( isset( $data['changes']['diesel_eastmsia'] ) ? $data['changes']['diesel_eastmsia'] : 0 ); ?></td>
						<td><?php echo esc_html( $date_display ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render compact Ticker layout: [fuel_price_ticker]
	 */
	public static function render_ticker( $atts ) {
		wp_enqueue_style( 'fuel-price-frontend' );

		$data = Fuel_Price_API::get_stored_data();
		if ( empty( $data ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="five-fuel-ticker">
			<span class="ticker-label">⛽ Malaysia Fuel:</span>
			<span class="ticker-item"><span class="ticker-fuel">RON95</span> RM <?php echo esc_html( isset( $data['ron95'] ) ? number_format( $data['ron95'], 2 ) : 'N/A' ); ?></span>
			<span class="ticker-sep">•</span>
			<span class="ticker-item"><span class="ticker-fuel">RON97</span> RM <?php echo esc_html( isset( $data['ron97'] ) ? number_format( $data['ron97'], 2 ) : 'N/A' ); ?></span>
			<span class="ticker-sep">•</span>
			<span class="ticker-item"><span class="ticker-fuel">Diesel</span> RM <?php echo esc_html( isset( $data['diesel'] ) ? number_format( $data['diesel'], 2 ) : 'N/A' ); ?></span>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Helper to render change pill in tables
	 */
	private static function render_change_pill( $diff ) {
		$diff = floatval( $diff );
		if ( $diff > 0 ) {
			return '<span class="change-tag change-up">▲ +' . esc_html( number_format( abs( $diff ), 2 ) ) . '</span>';
		} elseif ( $diff < 0 ) {
			return '<span class="change-tag change-down">▼ -' . esc_html( number_format( abs( $diff ), 2 ) ) . '</span>';
		}
		return '<span class="change-tag change-neutral">━ 0.00</span>';
	}
}
