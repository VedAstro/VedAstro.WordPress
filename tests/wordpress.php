<?php
// Run with wp eval-file after installing the actual ZIP into WordPress.
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if (!is_plugin_active('vedastro/vedastro.php')) { throw new RuntimeException('Plugin not active'); }
$seen = array();
add_filter('pre_http_request', function ($pre, $args, $url) use (&$seen) {
    if (strpos($url, VedAstro_Client::BASE_URL) !== 0) { return $pre; }
    $endpoint = basename($url);
    $seen[] = array($endpoint, json_decode($args['body'], true));
    return array('headers' => array(), 'body' => wp_json_encode(array('Status' => 'Pass', 'Payload' => array($endpoint => array('Name' => '<script>bad</script>', 'Value' => 0)))), 'response' => array('code' => 200, 'message' => 'OK'), 'cookies' => array());
}, 10, 3);
foreach (VedAstro_Plugin::examples() as $example) {
    $html = do_shortcode($example);
    if (strpos($html, 'vedastro-result') === false || strpos($html, 'vedastro-error') !== false || strpos($html, '<script>') !== false) {
        throw new RuntimeException('Shortcode failed: ' . $example . ' => ' . $html);
    }
}
$before = count($seen);
do_shortcode('[vedastro_planets date="1990-05-15 10:30"]');
if (count($seen) !== $before) { throw new RuntimeException('WordPress transient cache missed'); }
wp_set_current_user(1);
ob_start(); VedAstro_Plugin::admin_page(); $admin = ob_get_clean();
if (strpos($admin, 'options.php') === false || strpos($admin, '_wpnonce') === false) { throw new RuntimeException('Settings form missing nonce'); }
wp_set_current_user(0);
ob_start(); VedAstro_Plugin::admin_page(); $admin = ob_get_clean();
if ($admin !== '') { throw new RuntimeException('Non-admin can view settings'); }
WP_CLI::success('All documented shortcodes, escaping, caching and admin capability checks passed in WordPress.');
