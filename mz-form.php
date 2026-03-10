<?php

/**
 * Plugin Name: MZ Form (MU)
 * Description: AJAX form intake + admin/user emailer with DS-compatible defaults.
 * Author: Meza
 * Version: 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$mzf_base = __DIR__ . '/mz-form';

require_once $mzf_base . '/slug-registry.php';
require_once $mzf_base . '/config.php';
require_once $mzf_base . '/helpers.php';
require_once $mzf_base . '/providers/constant-contact.php';
require_once $mzf_base . '/providers/mailchimp.php';
require_once $mzf_base . '/providers/marketing.php';
require_once $mzf_base . '/handlers/ajax.php';
require_once $mzf_base . '/hooks.php';
require_once $mzf_base . '/admin-ui.php';
require_once __DIR__ . '/mz-form-migration-tools.php';
require_once __DIR__ . '/mz-form-client-overrides.php';
