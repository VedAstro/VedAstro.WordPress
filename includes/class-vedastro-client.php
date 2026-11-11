<?php
defined('ABSPATH') || exit;

/** A small, server-side client. No visitor-controlled URLs or credentials. */
final class VedAstro_Client {
    const BASE_URL = 'https://vedastro.zaishi.net/api/Calculate/';

    public static function calculate($endpoint, array $params = array(), $ayanamsa = '') {
        if (!is_string($endpoint) || !preg_match('/^[A-Za-z][A-Za-z0-9]{0,99}$/D', $endpoint)) {
            return new WP_Error('vedastro_endpoint', 'Use a calculation method name, not a URL.');
        }
        $settings = VedAstro_Plugin::settings();
        // Always use the administrator's key, even when JSON includes an APIKey.
        foreach (array_keys($params) as $key) {
            if (strtolower((string) $key) === 'apikey') {
                unset($params[$key]);
            }
        }
        $api_key = defined('VEDASTRO_API_KEY') ? VEDASTRO_API_KEY : $settings['api_key'];
        if ($ayanamsa !== '') {
            $params['Ayanamsa'] = $ayanamsa;
        } elseif (!isset($params['Ayanamsa'])) {
            $params['Ayanamsa'] = $settings['ayanamsa'];
        }
        $body = wp_json_encode($params);
        if ($body === false || strlen($body) > 65536) {
            return new WP_Error('vedastro_params', 'Calculation parameters must be valid JSON under 64 KB.');
        }
        $key = 'vedastro_' . hash('sha256', $endpoint . $body . $api_key);
        $cached = get_transient($key);
        // A wrapper preserves valid false, null, zero and empty payloads.
        if (is_array($cached) && array_key_exists('result', $cached)) {
            return $cached['result'];
        }
        $response = wp_remote_post(self::BASE_URL . $endpoint, array(
            'timeout' => (int) $settings['timeout'],
            'redirection' => 0,
            'limit_response_size' => 4 * 1024 * 1024,
            'headers' => array('Content-Type' => 'application/json', 'Accept' => 'application/json', 'x-api-key' => $api_key),
            'body' => $body,
        ));
        if (is_wp_error($response)) {
            return new WP_Error('vedastro_connection', 'VedAstro is temporarily unavailable. Please try again later.');
        }
        $status = wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            return new WP_Error('vedastro_http', 'VedAstro could not complete this request (HTTP ' . (int) $status . ').');
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (is_array($data) && ($data['Status'] ?? '') === 'Fail' && is_string($data['Payload'] ?? null) && stripos($data['Payload'], 'rate limit') !== false) {
            return new WP_Error('vedastro_limit', 'VedAstro API rate limit reached. Please try again later or configure your subscription key in Settings.');
        }
        if (!is_array($data) || !isset($data['Status']) || $data['Status'] !== 'Pass' || !array_key_exists('Payload', $data)) {
            // Never expose raw upstream errors: these can contain credentials or request data.
            return new WP_Error('vedastro_response', 'VedAstro could not calculate this result. Check the method, inputs and API subscription.');
        }
        $result = $data['Payload'];
        if (is_array($result) && array_key_exists($endpoint, $result)) {
            $result = $result[$endpoint];
        }
        if ((int) $settings['cache_minutes'] > 0) {
            set_transient($key, array('result' => $result), (int) $settings['cache_minutes'] * 60);
        }
        return $result;
    }
}
