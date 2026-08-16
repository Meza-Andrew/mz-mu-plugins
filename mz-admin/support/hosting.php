<?php

/**
 * Plugin Name: MZ Hosting Detection
 * Description: Hosting provider detection helpers.
 * Version: 1.0.1
 * Author: Meza LLC
 * Author URI: https://meza.design
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) return;

if (!function_exists('meza_is_godaddy_hosting')) {
    function meza_is_godaddy_hosting(): bool
    {
        static $cached = null;
        if (is_bool($cached)) return $cached;

        if (defined('MZ_IS_GODADDY_HOSTING')) {
            $cached = (bool) MZ_IS_GODADDY_HOSTING;
            return (bool) apply_filters('meza_is_godaddy_hosting', $cached);
        }

        $stored = get_transient('mz_is_godaddy_hosting');
        $stored_truthy = in_array($stored, [1, '1', true], true);
        if ($stored_truthy) {
            $cached = true;
            return (bool) apply_filters('meza_is_godaddy_hosting', $cached);
        }

        $signals = [];
        $needles = ['godaddy', 'secureserver', 'domaincontrol'];

        $values = [
            (string) ($_SERVER['HTTP_HOST'] ?? ''),
            (string) ($_SERVER['SERVER_NAME'] ?? ''),
            (string) ($_SERVER['SERVER_ADDR'] ?? ''),
            (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''),
            (string) gethostname(),
        ];

        foreach ($values as $value) {
            $value = strtolower(trim($value));
            if ($value === '') continue;
            foreach ($needles as $needle) {
                if (strpos($value, $needle) !== false) {
                    $signals[] = true;
                    break;
                }
            }
        }

        $server_ip = (string) ($_SERVER['SERVER_ADDR'] ?? '');
        if ($server_ip !== '' && filter_var($server_ip, FILTER_VALIDATE_IP)) {
            $rdns = strtolower((string) @gethostbyaddr($server_ip));
            if ($rdns !== '' && $rdns !== $server_ip) {
                foreach ($needles as $needle) {
                    if (strpos($rdns, $needle) !== false) {
                        $signals[] = true;
                        break;
                    }
                }
            }
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '' && function_exists('dns_get_record')) {
            $ns_records = @dns_get_record($host, DNS_NS);
            if (is_array($ns_records)) {
                foreach ($ns_records as $record) {
                    $target = strtolower((string) ($record['target'] ?? ''));
                    if ($target === '') continue;
                    if (strpos($target, 'domaincontrol.com') !== false || strpos($target, 'godaddy') !== false) {
                        $signals[] = true;
                        break;
                    }
                }
            }
        }

        $cached = !empty($signals);
        $ttl = $cached ? (12 * HOUR_IN_SECONDS) : (5 * MINUTE_IN_SECONDS);
        set_transient('mz_is_godaddy_hosting', $cached ? '1' : '0', $ttl);

        return (bool) apply_filters('meza_is_godaddy_hosting', $cached);
    }
}

if (!function_exists('meza_get_public_facing_url')) {
    function meza_is_admin_boundary_path(string $path): bool
    {
        if ($path === '/') {
            return false;
        }

        $normalized_path = '/' . ltrim((string) $path, '/');
        $admin_prefixes = [
            '/wp-admin',
            '/wp-login.php',
            '/wp-signup.php',
            '/wp-json',
            '/wp-content',
            '/admin-ajax.php',
            '/xmlrpc.php',
            '/wp-cron.php',
        ];

        foreach ($admin_prefixes as $prefix) {
            if (str_starts_with($normalized_path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    function meza_get_public_facing_url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parsed_url = wp_parse_url($url);
        if (!is_array($parsed_url)) {
            return $url;
        }

        $host = strtolower((string) ($parsed_url['host'] ?? ''));
        if ($host === '' || !str_starts_with($host, 'cms.')) {
            return $url;
        }

        $public_host = substr($host, 4);
        if ($public_host === '') {
            return $url;
        }

        $path = isset($parsed_url['path']) ? (string) $parsed_url['path'] : '/';
        if ($path === '') {
            $path = '/';
        }

        if (meza_is_admin_boundary_path($path)) {
            return $url;
        }

        $scheme = (string) ($parsed_url['scheme'] ?? 'https');
        $port = isset($parsed_url['port']) ? ':' . (int) $parsed_url['port'] : '';
        $query = isset($parsed_url['query']) && $parsed_url['query'] !== ''
            ? '?' . (string) $parsed_url['query']
            : '';
        $fragment = isset($parsed_url['fragment']) && $parsed_url['fragment'] !== ''
            ? '#' . (string) $parsed_url['fragment']
            : '';

        return $scheme . '://' . $public_host . $port . $path . $query . $fragment;
    }
}
