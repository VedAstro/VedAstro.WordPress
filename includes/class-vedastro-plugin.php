<?php
defined('ABSPATH') || exit;

final class VedAstro_Plugin {
    public static function boot() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_filter('no_texturize_shortcodes', function ($tags) { $tags[] = 'vedastro'; return $tags; });
        add_action('wp_enqueue_scripts', function () {
            wp_enqueue_style('vedastro', plugins_url('assets/vedastro.css', dirname(__DIR__) . '/vedastro.php'), array(), VEDASTRO_VERSION);
        });
        foreach (array('vedastro_chart', 'vedastro_ashtakavarga', 'vedastro', 'vedastro_panchanga', 'vedastro_planets', 'vedastro_houses', 'vedastro_horoscope', 'vedastro_planet', 'vedastro_house', 'vedastro_dasa', 'vedastro_compatibility') as $tag) {
            add_shortcode($tag, array(__CLASS__, 'shortcode'));
        }
    }

    public static function settings() {
        return array_merge(array(
            'api_key' => 'FreeAPIUser', 'ayanamsa' => 'RAMAN', 'location' => 'Kuala Lumpur',
            'latitude' => '3.139', 'longitude' => '101.6869', 'timezone' => 'Asia/Kuala_Lumpur',
            'cache_minutes' => 60, 'timeout' => 30,
        ), (array) get_option('vedastro_settings', array()));
    }

    public static function sanitize_settings($input) {
        $old = self::settings();
        $input = is_array($input) ? $input : array();
        $out = $old;
        foreach (array('api_key', 'ayanamsa', 'location') as $field) {
            if (isset($input[$field]) && is_scalar($input[$field])) {
                $out[$field] = sanitize_text_field($input[$field]);
            }
        }
        // An empty password field preserves the existing key.
        if ($out['api_key'] === '') { $out['api_key'] = $old['api_key']; }
        foreach (array('latitude' => 90, 'longitude' => 180) as $field => $limit) {
            if (isset($input[$field]) && is_numeric($input[$field]) && abs((float) $input[$field]) <= $limit) {
                $out[$field] = (string) $input[$field];
            } else {
                add_settings_error('vedastro_settings', $field, 'Enter a valid ' . $field . '.');
            }
        }
        try {
            new DateTimeZone((string) ($input['timezone'] ?? ''));
            $out['timezone'] = (string) $input['timezone'];
        } catch (Exception $e) {
            add_settings_error('vedastro_settings', 'timezone', 'Enter an IANA timezone such as Asia/Kuala_Lumpur.');
        }
        foreach (array('cache_minutes' => array(0, 10080), 'timeout' => array(5, 120)) as $field => $range) {
            if (isset($input[$field]) && is_numeric($input[$field])) {
                $out[$field] = max($range[0], min($range[1], (int) $input[$field]));
            }
        }
        return $out;
    }

    public static function menu() {
        add_options_page('VedAstro', 'VedAstro', 'manage_options', 'vedastro', array(__CLASS__, 'admin_page'));
    }

    public static function register_settings() {
        register_setting('vedastro', 'vedastro_settings', array('type' => 'array', 'sanitize_callback' => array(__CLASS__, 'sanitize_settings'), 'show_in_rest' => false));
    }

    public static function admin_page() {
        if (!current_user_can('manage_options')) { return; }
        $s = self::settings();
        echo '<div class="wrap"><h1>VedAstro</h1><p>Calculations are sent to the VedAstro service. API keys stay on your server. A free shared key is included; use your own key for your subscription.</p>';
        echo '<form method="post" action="options.php">';
        settings_fields('vedastro');
        echo '<table class="form-table" role="presentation">';
        $fields = array('api_key' => 'API key (blank keeps current key)', 'ayanamsa' => 'Ayanamsa (e.g. RAMAN or LAHIRI)', 'location' => 'Location name', 'latitude' => 'Latitude (-90 to 90)', 'longitude' => 'Longitude (-180 to 180)', 'timezone' => 'Timezone (IANA name or UTC offset)', 'cache_minutes' => 'Cache minutes (0 disables; maximum 10080)', 'timeout' => 'Request timeout in seconds (5–120)');
        foreach ($fields as $name => $label) {
            $type = $name === 'api_key' ? 'password' : 'text';
            $value = $name === 'api_key' ? '' : $s[$name];
            echo '<tr><th><label for="vedastro-' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td><input class="regular-text" id="vedastro-' . esc_attr($name) . '" name="vedastro_settings[' . esc_attr($name) . ']" type="' . esc_attr($type) . '" value="' . esc_attr($value) . '" autocomplete="off"' . (defined('VEDASTRO_API_KEY') && $name === 'api_key' ? ' disabled' : '') . '></td></tr>';
        }
        echo '</table>';
        submit_button();
        echo '</form><h2>Shortcodes</h2><p>Add a Shortcode block to a post or page. Dates use YYYY-MM-DD HH:MM. Omit date for the current hour in the configured timezone. Set an explicit birth date for natal calculations.</p>';
        foreach (self::examples() as $label => $example) {
            echo '<h3>' . esc_html($label) . '</h3><pre style="white-space:pre-wrap">' . esc_html($example) . '</pre>';
        }
        echo '<p>Optional attributes: location, latitude, longitude, timezone, ayanamsa, title, format="json". Birth data placed in public posts and API results are public. Avoid publishing private birth details. Full-page caching may keep current calculations visible longer than this plugin cache.</p><p><a href="https://github.com/VedAstro/VedAstro.WordPress#readme" target="_blank" rel="noopener noreferrer">Documentation and support</a> · <a href="https://vedastro.org/Account.html">API account</a> · <a href="https://vedastro.org/PrivacyPolicy.html">Service privacy policy</a></p></div>';
    }

    public static function examples() {
        return array(
            'South Indian birth chart' => '[vedastro_chart style="south" chart="RasiD1" date="1990-05-15 10:30"]',
            'North Indian Navamsha chart' => '[vedastro_chart style="north" chart="NavamshaD9" date="1990-05-15 10:30"]',
            'Ashtakavarga tables' => '[vedastro_ashtakavarga date="1990-05-15 10:30"]',
            'Daily Panchanga' => '[vedastro_panchanga]',
            'Nine planetary signs' => '[vedastro_planets date="1990-05-15 10:30"]',
            'Twelve house signs' => '[vedastro_houses date="1990-05-15 10:30"]',
            'Horoscope predictions' => '[vedastro_horoscope date="1990-05-15 10:30"]',
            'All data for one planet' => '[vedastro_planet planet="Sun" date="1990-05-15 10:30"]',
            'All data for one house' => '[vedastro_house house="House1" date="1990-05-15 10:30"]',
            'Current Vimshottari Dasa for a birth chart' => '[vedastro_dasa date="1990-05-15 10:30"]',
            'Compatibility (second person has independent location/timezone)' => '[vedastro_compatibility date="1990-05-15 10:30" partner_date="1992-07-20 14:00" partner_location="Mumbai" partner_latitude="19.076" partner_longitude="72.8777" partner_timezone="Asia/Kolkata"]',
            'Any calculation: time is added automatically' => '[vedastro endpoint="AllPlanetNavamshaSign" date="1990-05-15 10:30"]',
            'Additional parameters as enclosed JSON' => '[vedastro endpoint="PlanetRasiD1Sign" date="1990-05-15 10:30"]{"planetName":"Sun"}[/vedastro]',
        );
    }

    /** Create the same Time JSON object as the Python client, with strict date validation. */
    public static function time(array $a, $prefix = '', $current = false) {
        $s = self::settings();
        $get = function ($key) use ($a, $prefix, $s) { return $a[$prefix . $key] ?? $a[$key] ?? $s[$key] ?? ''; };
        $lat = $get('latitude'); $lon = $get('longitude');
        if (!is_numeric($lat) || !is_numeric($lon) || abs((float) $lat) > 90 || abs((float) $lon) > 180) {
            return new WP_Error('vedastro_location', 'Enter valid latitude and longitude.');
        }
        try {
            $zone = new DateTimeZone((string) $get('timezone'));
            $date = $current ? '' : ($a[$prefix . 'date'] ?? '');
            if ($date === '') {
                $dt = new DateTimeImmutable('now', $zone);
                // Stable hourly cache keys while still showing current conditions.
                $dt = $dt->setTime((int) $dt->format('H'), 0);
            } else {
                $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date, $zone);
                if (!$dt || $dt->format('Y-m-d H:i') !== $date) {
                    throw new InvalidArgumentException('date');
                }
            }
        } catch (Exception $e) {
            return new WP_Error('vedastro_time', 'Use a valid date (YYYY-MM-DD HH:MM) and timezone (e.g. Asia/Kuala_Lumpur).');
        }
        return array('StdTime' => $dt->format('H:i d/m/Y P'), 'Location' => array('Name' => (string) $get('location'), 'Longitude' => (float) $lon, 'Latitude' => (float) $lat));
    }

    public static function shortcode($attributes, $content = null, $tag = 'vedastro') {
        $a = is_array($attributes) ? $attributes : array();
        $title = $a['title'] ?? ucwords(str_replace('_', ' ', $tag));
        if (in_array($tag, array('vedastro_chart', 'vedastro_ashtakavarga'), true)) {
            return VedAstro_Visuals::shortcode($a, $tag);
        }
        $result = self::run($a, $content, $tag);
        $html = '<section class="vedastro-result" aria-label="' . esc_attr($title) . '"><h3>' . esc_html($title) . '</h3>';
        if (is_wp_error($result)) {
            return $html . '<p class="vedastro-error" role="status">' . esc_html($result->get_error_message()) . '</p></section>';
        }
        if (($a['format'] ?? '') === 'json') {
            return $html . '<pre>' . esc_html(wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre></section>';
        }
        return $html . self::render($result) . '</section>';
    }

    private static function run(array $a, $content, $tag) {
        $params = array();
        if (trim((string) $content) !== '') {
            // Decode HTML entities inserted by WordPress editors; never execute nested shortcodes.
            $raw = trim(html_entity_decode((string) $content, ENT_QUOTES, 'UTF-8'));
            $object = json_decode($raw);
            if (strlen($raw) > 65536 || !is_object($object) || json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error('vedastro_json', 'Enclosed parameters must be a valid JSON object. Use straight quotes in a Shortcode block.');
            }
            // Keep empty objects distinct from arrays for typed API parameters.
            $params = (array) $object;
        }
        $time = self::time($a);
        if (is_wp_error($time)) { return $time; }
        $ayanamsa = $a['ayanamsa'] ?? '';
        switch ($tag) {
            case 'vedastro_panchanga':
                return VedAstro_Client::calculate('PanchangaTable', array('inputTime' => $time), $ayanamsa);
            case 'vedastro_planets': $endpoint = 'AllPlanetRasiSigns'; break;
            case 'vedastro_houses': $endpoint = 'AllHouseBhavaChalitSigns'; break;
            case 'vedastro_planet':
                $endpoint = 'AllPlanetData'; $params['planetName'] = $a['planet'] ?? 'Sun'; break;
            case 'vedastro_house':
                $endpoint = 'AllHouseData'; $params['houseName'] = $a['house'] ?? 'House1'; break;
            case 'vedastro_horoscope':
                $endpoint = 'HoroscopePredictions'; $params['birthTime'] = $time; break;
            case 'vedastro_dasa':
                if (empty($a['date'])) { return new WP_Error('vedastro_birth', 'Set date to the birth date and time.'); }
                $endpoint = 'DasaAtTime'; $params['birthTime'] = $time;
                $params['at'] = self::time(array_merge($a, array('date' => $a['at'] ?? '')));
                if (is_wp_error($params['at'])) { return $params['at']; }
                $params['system'] = $a['system'] ?? 'Vimshottari';
                $params['depth'] = max(1, min(5, (int) ($a['depth'] ?? 3)));
                break;
            case 'vedastro_compatibility':
                if (empty($a['date']) || empty($a['partner_date'])) { return new WP_Error('vedastro_birth', 'Set date and partner_date to both birth times.'); }
                $endpoint = 'MatchReport'; $params['maleBirthTime'] = $time;
                $params['femaleBirthTime'] = self::time($a, 'partner_');
                if (is_wp_error($params['femaleBirthTime'])) { return $params['femaleBirthTime']; }
                break;
            default: $endpoint = $a['endpoint'] ?? '';
        }
        if (!in_array($tag, array('vedastro_horoscope', 'vedastro_dasa', 'vedastro_compatibility'), true)) {
            // Generic methods can use birthTime, at, or any other parameter name.
            $time_param = $a['time_param'] ?? 'time';
            if ($time_param !== 'none' && !array_key_exists($time_param, $params)) { $params[$time_param] = $time; }
        }
        return VedAstro_Client::calculate($endpoint, $params, $ayanamsa);
    }

    public static function render($value, $depth = 0) {
        if ($depth > 10) { return '<pre>' . esc_html(wp_json_encode($value, JSON_UNESCAPED_UNICODE)) . '</pre>'; }
        if ($value === null) { return '<span>No value</span>'; }
        if (is_bool($value)) { return $value ? 'Yes' : 'No'; }
        if (!is_array($value)) { return nl2br(esc_html((string) $value)); }
        if (!$value) { return '<p>No results.</p>'; }
        $html = '<table class="vedastro-table"><tbody>';
        foreach ($value as $key => $item) {
            $label = is_int($key) ? (string) ($key + 1) : preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $key);
            $html .= '<tr><th scope="row">' . esc_html($label) . '</th><td>' . self::render($item, $depth + 1) . '</td></tr>';
        }
        return $html . '</tbody></table>';
    }
}
