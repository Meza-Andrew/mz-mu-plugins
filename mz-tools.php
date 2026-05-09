<?php

/**
 * Plugin Name: MZ Tools
 * Description: Migration and reconciliation tools for Meza WordPress projects.
 * Version: 1.0.8
 * Author: Meza
 */

if (!defined('ABSPATH')) {
    exit;
}

$mz_tools_modules = [
    __DIR__ . '/mz-tools/post-type-migration.php',
    __DIR__ . '/mz-tools/faq-migration.php',
    __DIR__ . '/mz-tools/form-migration.php',
];

foreach ($mz_tools_modules as $mz_tools_module) {
    if (file_exists($mz_tools_module)) {
        require_once $mz_tools_module;
    }
}
