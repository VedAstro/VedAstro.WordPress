<?php
defined('WP_UNINSTALL_PLUGIN') || exit;

// Only this plugin's data; WordPress manages expiration during normal use.
delete_option('vedastro_settings');
global $wpdb;
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
    $wpdb->esc_like('_transient_vedastro_') . '%',
    $wpdb->esc_like('_transient_timeout_vedastro_') . '%'
));
