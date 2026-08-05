<?php

/**
 * Plugin Name: MZ Loader
 * Description: Loads Meza's WordPress enhancements.
 * Version: 1.0.8
 */

if (!defined('ABSPATH')) {
    exit;
}

$mz_plugin_files = [
    'mz-site-documentation.php',
    'mz-admin.php',
    'mz-acf-project-options.php',
    'mz-events-recurring.php',
    'mz-form.php',
    'mz-tools.php',
    'mz-post-parent-guard.php',
    'mz-theme-config.php',
    'mz-performance.php',
    'mz-plugins.php',
    'mz-security.php',
    'mz-settings.php',
    'mz-woocommerce.php',
];

foreach ($mz_plugin_files as $mz_plugin_file) {
    $mz_plugin_path = __DIR__ . '/mz-mu-plugins/' . $mz_plugin_file;

    if (file_exists($mz_plugin_path)) {
        require_once $mz_plugin_path;
    }
}
