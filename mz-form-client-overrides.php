<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Client-level MZ Form overrides.
 *
 * Keep this file portable across sites by limiting it to filters.
 */

add_filter('mzf_recipients', static function (array $to, array $data, $env): array {
    $env_name = strtolower(trim((string) $env));
    if (in_array($env_name, ['development', 'staging', 'local'], true)) {
        return $to;
    }

    $store = trim((string) ($data['Store'] ?? ''));
    if ($store === '' || !function_exists('mz_resolve_office_email')) {
        return $to;
    }

    $ref_path = '';
    $referer = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';
    if ($referer !== '') {
        $parsed = wp_parse_url($referer);
        if (is_array($parsed) && !empty($parsed['path'])) {
            $ref_path = (string) $parsed['path'];
        }
    }

    $office_email = sanitize_email((string) mz_resolve_office_email($store, $ref_path));
    if (!is_email($office_email)) {
        return $to;
    }

    // Store-specific routing: send to the selected office recipient.
    return [$office_email];
}, 20, 3);

add_filter('mzf_recaptcha_disabled', static function (bool $disabled): bool {
    $env = defined('WP_ENV') ? strtolower(trim((string) WP_ENV)) : '';
    if (in_array($env, ['development', 'local', 'staging'], true)) {
        return true;
    }
    return $disabled;
}, 20);

add_filter('mzf_require_last_name', static function (bool $required, array $data): bool {
    $slug = sanitize_key((string) ($data['FormSlug'] ?? ''));
    if ($slug === 'contact') {
        return false;
    }
    return $required;
}, 20, 2);
