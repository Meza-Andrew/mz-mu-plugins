<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mzf_truthy')) {
    function mzf_truthy($val): bool
    {
        if (is_bool($val)) {
            return $val;
        }
        $v = is_string($val) ? strtolower(trim($val)) : $val;
        return in_array($v, [1, '1', 'true', 'yes', 'on', 'y'], true);
    }
}

if (!function_exists('mzf_marketing_provider')) {
    function mzf_marketing_provider(): string
    {
        $provider = defined('MZF_MARKETING_PROVIDER')
            ? (string) MZF_MARKETING_PROVIDER
            : (string) mzf_get('marketing_provider', 'none');
        $provider = strtolower(trim((string) apply_filters('mzf_marketing_provider', $provider)));

        if (!in_array($provider, ['none', 'mailchimp', 'constant_contact'], true)) {
            $provider = 'none';
        }

        if ($provider === 'none') {
            if (function_exists('mz_mc_is_configured') && mz_mc_is_configured()) {
                return 'mailchimp';
            }
            if (defined('CC_LIST_ID') && CC_LIST_ID && get_option('cc_tokens')) {
                return 'constant_contact';
            }
        }
        return $provider ?: 'none';
    }
}

if (!function_exists('mzf_sync_marketing_contact')) {
    function mzf_sync_marketing_contact(array $data): bool
    {
        $provider = mzf_marketing_provider();
        if ($provider === 'none') {
            return false;
        }

        if ($provider === 'mailchimp') {
            if (!function_exists('mz_mc_upsert_contact')) {
                return false;
            }
            $result = mz_mc_upsert_contact($data);
            if (is_wp_error($result)) {
                error_log('Mailchimp sync failed: ' . $result->get_error_message());
                return false;
            }
            return true;
        }

        if ($provider === 'constant_contact') {
            if (!function_exists('mz_cc_add_contact')) {
                return false;
            }
            mz_cc_add_contact([
                'Email'        => $data['Email'] ?? '',
                'FirstName'    => $data['FirstName'] ?? '',
                'LastName'     => $data['LastName'] ?? '',
                'Organization' => $data['Company'] ?? ($data['Organization'] ?? ''),
                'Website'      => $data['Website'] ?? '',
                'LeadTags'     => $data['LeadTags'] ?? [],
            ]);
            return true;
        }

        return false;
    }
}
