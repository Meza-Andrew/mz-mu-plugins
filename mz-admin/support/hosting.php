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
