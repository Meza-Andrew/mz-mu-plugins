<?php

/**
 * Plugin Name: MZ Loader
 * Description: Loads Meza's WordPress enhancements.
 * Version: 1.0.8
 */

if (!defined('ABSPATH')) {
    exit;
}

// The site-level MU loader symlink points at this package root, but runtime modules
// are loaded from the inner mz-mu-plugins directory below. Keep loadable modules there.
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
    'arsenal-events-cpt.php',
    'mz-rest-api.php',
];

foreach ($mz_plugin_files as $mz_plugin_file) {
    $mz_plugin_path = __DIR__ . '/mz-mu-plugins/' . $mz_plugin_file;

    if (file_exists($mz_plugin_path)) {
        require_once $mz_plugin_path;
    }
}
