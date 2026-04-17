<?php

/**
 * Plugin Name: MZ Form (MU)
 * Description: AJAX form intake + admin/user emailer with DS-compatible defaults.
 * Author: Meza
 * Version: 1.1.2
 */

if (!defined('ABSPATH')) {
    exit;
}

$mzf_base = __DIR__ . '/mz-form';

$mzf_require = static function (string $path, bool $required = true): bool {
    if (is_readable($path)) {
        require_once $path;
        return true;
    }

    error_log('MZF include missing: ' . $path);
    return !$required;
};

$mzf_core_ok = true;
$mzf_core_ok = $mzf_require($mzf_base . '/slug-registry.php', true) && $mzf_core_ok;
$mzf_core_ok = $mzf_require($mzf_base . '/config.php', true) && $mzf_core_ok;
$mzf_core_ok = $mzf_require($mzf_base . '/helpers.php', true) && $mzf_core_ok;
$mzf_core_ok = $mzf_require($mzf_base . '/submissions-log.php', true) && $mzf_core_ok;
$mzf_core_ok = $mzf_require($mzf_base . '/handlers/ajax.php', true) && $mzf_core_ok;
$mzf_core_ok = $mzf_require($mzf_base . '/hooks.php', true) && $mzf_core_ok;
$mzf_core_ok = $mzf_require($mzf_base . '/admin-ui.php', true) && $mzf_core_ok;
$mzf_core_ok = $mzf_require(__DIR__ . '/mz-form-migration-tools.php', true) && $mzf_core_ok;
$mzf_core_ok = $mzf_require(__DIR__ . '/mz-form-client-overrides.php', true) && $mzf_core_ok;

if (!$mzf_core_ok) {
    error_log('MZF initialization aborted due to missing required files.');
    return;
}

// Providers are optional; form core should stay online if one is missing.
$mzf_require($mzf_base . '/providers/constant-contact.php', false);
$mzf_require($mzf_base . '/providers/mailchimp.php', false);
$mzf_require($mzf_base . '/providers/marketing.php', false);
