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
