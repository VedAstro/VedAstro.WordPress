<?php
/**
 * Plugin Name: VedAstro
 * Plugin URI: https://vedastro.org/WordPressPlugin.html
 * Description: North/South Indian birth charts, Ashtakavarga, Panchanga, predictions and more using the VedAstro API.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: VedAstro
 * Author URI: https://vedastro.org
 * License: GPL-2.0-or-later
 * Text Domain: vedastro
 * Update URI: https://github.com/VedAstro/VedAstro.WordPress
 */

defined('ABSPATH') || exit;
define('VEDASTRO_VERSION', '1.0.0');
require_once __DIR__ . '/includes/class-vedastro-client.php';
require_once __DIR__ . '/includes/class-vedastro-visuals.php';
require_once __DIR__ . '/includes/class-vedastro-plugin.php';
VedAstro_Plugin::boot();
