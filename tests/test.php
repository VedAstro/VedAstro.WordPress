<?php
// Deterministic HTTP contract tests, runnable without installing WordPress.
define('ABSPATH', __DIR__);
$options = array(); $cache = array(); $calls = 0; $next = null;
class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($v) { return $v instanceof WP_Error; }
function get_option($key, $default) { global $options; return $options[$key] ?? $default; }
function get_transient($key) { global $cache; return $cache[$key] ?? false; }
function set_transient($key, $value, $ttl) { global $cache; $cache[$key] = $value; }
function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); }
function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function esc_attr($v) { return esc_html($v); }
function sanitize_text_field($v) { return trim(strip_tags($v)); }
function add_settings_error($a, $b, $c) {}
function wp_remote_post($url, $args) {
    global $calls, $next, $request, $headers;
    $calls++; $request = array($url, json_decode($args['body'], true));
    $headers = $args['headers'];
    return $next;
}
function wp_remote_retrieve_response_code($v) { return $v['code']; }
function wp_remote_retrieve_body($v) { return $v['body']; }
require __DIR__ . '/../includes/class-vedastro-client.php';
require __DIR__ . '/../includes/class-vedastro-plugin.php';
function check($condition, $label) {
    if (!$condition) { throw new RuntimeException($label); }
    echo "PASS $label\n";
}
function response($payload, $status = 'Pass') {
    global $next, $cache;
    $cache = array();
    $next = array('code' => 200, 'body' => json_encode(array('Status' => $status, 'Payload' => $payload)));
}

$time = VedAstro_Plugin::time(array('date' => '1990-05-15 10:30', 'timezone' => 'Asia/Kolkata'));
check($time['StdTime'] === '10:30 15/05/1990 +05:30', 'Python-compatible time and timezone');
check(is_wp_error(VedAstro_Plugin::time(array('date' => '2026-02-30 10:30'))), 'invalid calendar dates rejected');
check(is_wp_error(VedAstro_Plugin::time(array('latitude' => '91'))), 'coordinate ranges checked');
check(is_wp_error(VedAstro_Plugin::time(array('timezone' => 'made/up'))), 'invalid timezones rejected');
check(VedAstro_Plugin::time(array('date' => '2026-07-01 10:00', 'timezone' => 'America/New_York'))['StdTime'] === '10:00 01/07/2026 -04:00', 'DST offset calculated for date');
check(is_wp_error(VedAstro_Client::calculate('https://localhost/secret')), 'arbitrary URLs rejected');
foreach (array(false, null, 0, '', array()) as $value) {
    response(array('Test' => $value));
    check(VedAstro_Client::calculate('Test') === $value, 'empty payload preserved: ' . json_encode($value));
    $before = $calls;
    check(VedAstro_Client::calculate('Test') === $value && $calls === $before, 'empty payload cached');
}
response(array('PlanetRasiD1Sign' => '<script>alert(1)</script>'));
$html = VedAstro_Plugin::shortcode(array('endpoint' => 'PlanetRasiD1Sign'), '{"planetName":"Sun","APIKey":"attacker"}');
check(!isset($request[1]['APIKey']) && $headers['x-api-key'] === 'FreeAPIUser' && $request[1]['planetName'] === 'Sun', 'admin key sent in header; author key removed from JSON');
check(isset($request[1]['time']['StdTime']) && $request[1]['Ayanamsa'] === 'RAMAN', 'time and ayanamsa sent in POST JSON');
check(strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;') !== false, 'upstream HTML escaped');
check(strpos(VedAstro_Plugin::shortcode(array(), '[]'), 'valid JSON object') !== false, 'JSON must be an object');
response('APIKey=secret', 'Fail');
check(strpos(VedAstro_Plugin::shortcode(array('endpoint' => 'Fail')), 'secret') === false, 'upstream error details hidden');
$before = $calls; VedAstro_Client::calculate('Fail');
check($calls === $before + 1, 'failed results never cached');
$next = new WP_Error('timeout', 'secret');
check(is_wp_error(VedAstro_Client::calculate('Timeout')), 'transport failures handled');
$next = array('code' => 429, 'body' => 'secret');
check(strpos(VedAstro_Client::calculate('Limit')->get_error_message(), '429') !== false, 'HTTP errors handled');
$next = array('code' => 200, 'body' => '<html>not JSON</html>');
check(is_wp_error(VedAstro_Client::calculate('Invalid')), 'invalid JSON rejected');
foreach (array('vedastro_planets' => 'AllPlanetRasiSigns', 'vedastro_houses' => 'AllHouseBhavaChalitSigns', 'vedastro_horoscope' => 'HoroscopePredictions', 'vedastro_planet' => 'AllPlanetData', 'vedastro_house' => 'AllHouseData', 'vedastro_dasa' => 'DasaAtTime', 'vedastro_compatibility' => 'MatchReport') as $tag => $endpoint) {
    response(array($endpoint => 'ok'));
    $html = VedAstro_Plugin::shortcode(array('date' => '1990-05-15 10:30', 'partner_date' => '1992-07-20 14:00', 'partner_timezone' => 'Asia/Kolkata'), null, $tag);
    check($request[0] === VedAstro_Client::BASE_URL . $endpoint && strpos($html, '>ok<') !== false, $tag . ' endpoint contract');
    if ($tag === 'vedastro_compatibility') { check(strpos($request[1]['femaleBirthTime']['StdTime'], '+05:30') !== false, 'partner timezone is independent'); }
}
response(array('PanchangaTable' => array('Tithi' => 'value', 'Sunset' => 'value')));
$html = VedAstro_Plugin::shortcode(array(), null, 'vedastro_panchanga');
check(strpos($html, 'Tithi') !== false && strpos($html, 'Sunset') !== false && isset($request[1]['inputTime']), 'Panchanga uses one bundled request');
response(array('Test' => 'ok'));
VedAstro_Plugin::shortcode(array('endpoint' => 'Test', 'time_param' => 'none'), '{"options":{}}');
check(!isset($request[1]['time']) && $request[1]['options'] === array(), 'generic methods can omit automatic time');
echo "All contract tests passed.\n";
