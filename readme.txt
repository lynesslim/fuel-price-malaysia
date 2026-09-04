=== FIVE Fuel Price Malaysia (data.gov.my) ===
Contributors: Supercraft, FIVE Petroleum Malaysia
Tags: fuel price, petrol, diesel, ron95, ron97, data.gov.my, malaysia, five petroleum, cron, shortcode
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automated weekly synchronization of official Malaysian retail fuel prices (RON95, RON97, Diesel, Euro 5 B7) from data.gov.my, with flexible WP-Cron scheduling, manual refresh button, and shortcodes for front-end text widgets and page builders.

== Description ==

**FIVE Fuel Price Malaysia** automatically fetches and updates the latest retail fuel prices directly from the official Malaysian Government Open Data API (`data.gov.my/data-catalogue/fuelprice`).

### Key Features
* **Automated Weekly Sync**: Built-in WP-Cron scheduling lets you choose the exact day (e.g. Wednesday) and time (e.g. 17:00 / 5:00 PM) based on your WordPress local timezone.
* **Instant Manual Sync**: One-click "Sync Prices Now" button in the admin dashboard with real-time AJAX feedback.
* **Diesel Euro 5 B7 Support**: Automatically computes the official Malaysian +20 sen (+RM 0.20) Euro 5 B7 price offset with customizable setting.
* **Granular Shortcodes**: Output pure values or formatted prices for RON95, RON97, Diesel (Peninsular), Diesel Euro 5 B7, Diesel (East Malaysia), and effective dates.
* **Ready for Text Widgets & Page Builders**: Works seamlessly inside WordPress Classic Text Widgets, Block/Gutenberg Widgets, Custom HTML widgets, Elementor, Divi, Bricks, and Beaver Builder.
* **Weekly Change Tracking**: Calculates weekly price movements (e.g., +RM 0.05 or -RM 0.05) with color-coded direction indicators.
* **Targeted Subsidies Data**: Supports viewing SKPS, BUDI, and SKDS targeted categories.
* **Pre-Built Responsive Layouts**: Includes ready-to-use card grids (`[fuel_price_cards]`), comparison tables (`[fuel_price_table]`), and header tickers (`[fuel_price_ticker]`).

== Installation ==

1. Upload the plugin folder `Fuel Price` to the `/wp-content/plugins/` directory (or zip and upload via **Plugins > Add New > Upload Plugin**).
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **Fuel Price** in the WordPress admin sidebar to review your schedule, adjust the Euro 5 B7 offset, or click **Sync Prices Now** to fetch the latest data immediately.

== Shortcode Usage ==

### 1. Granular Shortcodes (Ideal for styling inside Text Widgets)
* `[fuel_price type="ron95"]` - Outputs `RM 3.77`
* `[fuel_price type="ron97"]` - Outputs `RM 4.25`
* `[fuel_price type="diesel"]` - Outputs `RM 4.67` (Diesel Euro 5 B10 Peninsular)
* `[fuel_price type="diesel_b7"]` - Outputs `RM 4.87` (Diesel Euro 5 B7 Peninsular)
* `[fuel_price type="diesel_eastmsia"]` - Outputs `RM 2.15` (Diesel Sabah & Sarawak)
* `[fuel_price type="date" format="d M Y"]` - Outputs the effective date (e.g., `03 Sep 2026`)
* `[fuel_price type="change_ron95"]` - Outputs weekly price change (e.g., `-RM 0.05`)

### Shortcode Attributes:
* `prefix`: Prefix text before number (default: `"RM "`).
* `suffix`: Suffix text after number (e.g., `" / litre"`).
* `decimals`: Decimal places (default: `2`).
* `wrap`: `"span"` (default), `"div"`, or `"none"` (pure raw value with no HTML tags).
* `class`: Custom CSS class name.
* `format`: Date format when `type="date"` (default: `"d M Y"`).

### Example: Styling Inside a Text Widget / Custom HTML:
```html
<div class="custom-ron95-card">
  <h3>RON95 Petrol</h3>
  <span style="font-size: 32px; font-weight: 800; color: #d97706;">
    [fuel_price type="ron95" prefix="RM " suffix=" /L"]
  </span>
  <p>Updated: [fuel_price type="date" format="j F Y"]</p>
</div>
```

### 2. Convenience Aliases
* `[fuel_ron95]`
* `[fuel_ron97]`
* `[fuel_diesel]`
* `[fuel_diesel_b7]` (Euro 5 B7 Peninsular)
* `[fuel_diesel_b7_east]` (Euro 5 B7 Sabah & Sarawak)
* `[fuel_diesel_east]`
* `[fuel_price_date]`

### 3. Pre-Styled Components
* `[fuel_price_cards]` - Displays a modern, responsive card grid with badges and change tags (includes Euro 5 B7).
  * Parameters: `theme="light"` or `theme="dark"`, `title="Current Fuel Prices"`, `show_date="true"`, `show_change="true"`, `show_b7="true"`.
* `[fuel_price_table]` - Displays a clean comparison table with Euro 5 B10 and B7.
* `[fuel_price_ticker]` - Compact inline fuel price bar for headers.

== Frequently Asked Questions ==

= Can I display the fuel price inside a WordPress Text Widget? =
Yes! The plugin automatically enables shortcode execution inside standard WordPress text and HTML widgets (`widget_text` and `widget_custom_html_content`).

= How do I style the price text? =
You can set `wrap="none"` or use standard `[fuel_price type="ron95"]` inside any widget or page builder block (such as an Elementor Heading or Text block) and apply any font, color, or CSS styling you desire.

= Does data.gov.my include Diesel Euro 5 B7? =
The government API provides the regulated Euro 5 B10 price. In Malaysia, Euro 5 B7 is officially regulated at a fixed 20-sen premium (+RM 0.20) above B10. The plugin automatically calculates this rate and lets you adjust the offset in settings.

= When does the government announce fuel prices? =
In Malaysia, weekly retail fuel prices are announced by the Ministry of Finance / KPDN every Wednesday afternoon (usually between 17:00 and 18:00 MYT) and take effect on Thursday. The plugin defaults to scheduling every Wednesday at 17:00.

= Does it slow down my website? =
No. The API is called in the background via WP-Cron (or via the manual sync button). Front-end visitors read directly from cached WordPress options, resulting in zero API delay.

== Changelog ==

= 1.1.0 =
* Added official Diesel Euro 5 B7 pricing support with automatic +RM 0.20 (+20 sen) offset calculation.
* Added `[fuel_price type="diesel_b7"]` and shorthand `[fuel_diesel_b7]`.
* Added customizable Euro 5 B7 premium offset setting in the WordPress admin panel.
* Updated `[fuel_price_cards]`, `[fuel_price_table]`, and `[fuel_price_ticker]` to display Euro 5 B7.
* Integrated Plugin Update Checker (PUC v5.7) for 1-click GitHub updates.

= 1.0.0 =
* Initial release.
* Automated WP-Cron scheduling with day-of-week and time selection.
* Manual AJAX refresh button in the admin dashboard.
* Flexible shortcodes for RON95, RON97, Diesel Peninsular, Diesel East Malaysia, and weekly change tracking.
* Widget shortcode support.
* Responsive cards, table, and ticker layouts.
