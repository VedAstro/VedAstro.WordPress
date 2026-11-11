=== VedAstro ===
Contributors: vedastro
Tags: astrology, panchanga, horoscope, vedic, shortcode
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Panchanga, planets, houses, horoscope predictions, Dasa and compatibility through the VedAstro API.

== Description ==
Server-rendered shortcodes with server-side API keys, cached results and escaped output. The generic [vedastro] shortcode also supports other Calculate methods. See the included README.md for examples and settings.

External service: calculations send supplied dates, locations, parameters and the configured API key to https://vedastro.zaishi.net/api/Calculate/ when rendering uncached results. No background telemetry. API limits and subscriptions apply. Service: https://vedastro.org — privacy: https://vedastro.org/PrivacyPolicy.html

== Installation ==
1. Download vedastro.zip from https://github.com/VedAstro/VedAstro.WordPress/releases/latest
2. Upload using Plugins > Add New > Upload Plugin and activate.
3. Configure Settings > VedAstro.
4. Insert [vedastro_panchanga] into a Shortcode block.

== Frequently Asked Questions ==
= Is a key required? =
The shared FreeAPIUser key is included by default. Use your own key for your subscription.

= How do I update? =
Upload the latest release ZIP and choose the replacement option in WordPress.

= Does this replace the legacy plugin? =
Yes. Replace legacy horoscope_predictions shortcodes with vedastro_horoscope using date="YYYY-MM-DD HH:MM" and configure the new settings.

== Changelog ==
= 1.0.0 =
Fresh implementation with nine shortcodes, JSON API support, configurable caching, secure settings and automatic GitHub release packaging.
