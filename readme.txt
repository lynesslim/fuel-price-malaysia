=== FIVE Fuel Price Malaysia (data.gov.my) ===
Contributors: Supercraft, FIVE Petroleum Malaysia
Tags: fuel price, petrol, diesel, ron95, ron97, data.gov.my, malaysia, five petroleum, cron, shortcode
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automated weekly synchronization of official Malaysian retail fuel prices (RON95, RON97, Diesel) from data.gov.my, with flexible WP-Cron scheduling, manual refresh button, and shortcodes for front-end text widgets and page builders.

== Description ==

**FIVE Fuel Price Malaysia** automatically fetches and updates the latest retail fuel prices directly from the official Malaysian Government Open Data API (`data.gov.my/data-catalogue/fuelprice`).

### Key Features
* **Automated Weekly Sync**: Built-in WP-Cron scheduling lets you choose the exact day (e.g. Wednesday) and time (e.g. 17:00 / 5:00 PM) based on your WordPress local timezone.
* **Instant Manual Sync**: One-click "Sync Prices Now" button in the admin dashboard with real-time AJAX feedback.
* **Granular Shortcodes**: Output pure values or formatted prices for RON95, RON97, Diesel (Peninsular), Diesel (East Malaysia), and effective dates.
* **Ready for Text Widgets & Page Builders**: Works seamlessly inside WordPress Classic Text Widgets, Block/Gutenberg Widgets, Custom HTML widgets, Elementor, Divi, Bricks, and Beaver Builder.
* **Weekly Change Tracking**: Calculates weekly price movements (e.g., +RM 0.05 or -RM 0.05) with color-coded direction indicators.
* **Targeted Subsidies Data**: Supports viewing SKPS, BUDI, and SKDS targeted categories.
* **Pre-Built Responsive Layouts**: Includes ready-to-use card grids (`[fuel_price_cards]`), comparison tables (`[fuel_price_table]`), and header tickers (`[fuel_price_ticker]`).

== Installation ==

1. Upload the plugin folder `Fuel Price` to the `/wp-content/plugins/` directory (or zip and upload via **Plugins > Add New > Upload Plugin**).
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **Settings > Fuel Price Malaysia** to review your schedule or click **Sync Prices Now** to fetch the latest data immediately.

== Shortcode Usage ==

### 1. Granular Shortcodes (Ideal for styling inside Text Widgets)
* `[fuel_price type="ron95"]` - Outputs `RM 3.77`
* `[fuel_price type="ron97"]` - Outputs `RM 4.25`
* `[fuel_price type="diesel"]` - Outputs `RM 4.67` (Diesel Peninsular)
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
* `[fuel_diesel_east]`
* `[fuel_price_date]`

### 3. Pre-Styled Components
* `[fuel_price_cards]` - Displays a modern, responsive 4-column card grid with badges and change tags.
  * Parameters: `theme="light"` or `theme="dark"`, `title="Current Fuel Prices"`, `show_date="true"`, `show_change="true"`.
* `[fuel_price_table]` - Displays a clean comparison table.
* `[fuel_price_ticker]` - Compact inline fuel price bar for headers.

== Frequently Asked Questions ==

= Can I display the fuel price inside a WordPress Text Widget? =
Yes! The plugin automatically enables shortcode execution inside standard WordPress text and HTML widgets (`widget_text` and `widget_custom_html_content`).

= How do I style the price text? =
You can set `wrap="none"` or use standard `[fuel_price type="ron95"]` inside any widget or page builder block (such as an Elementor Heading or Text block) and apply any font, color, or CSS styling you desire.

= When does the government announce fuel prices? =
In Malaysia, weekly retail fuel prices are announced by the Ministry of Finance / KPDN every Wednesday afternoon (usually between 17:00 and 18:00 MYT) and take effect on Thursday. The plugin defaults to scheduling every Wednesday at 17:00.

= Does it slow down my website? =
No. The API is called in the background via WP-Cron (or via the manual sync button). Front-end visitors read directly from cached WordPress options, resulting in zero API delay.

== Changelog ==

= 1.0.0 =
* Initial release.
* Automated WP-Cron scheduling with day-of-week and time selection.
* Manual AJAX refresh button in the admin dashboard.
* Flexible shortcodes for RON95, RON97, Diesel Peninsular, Diesel East Malaysia, and weekly change tracking.
* Widget shortcode support.
* Responsive cards, table, and ticker layouts.
