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
        $normalize_provider = static function ($value): string {
            $value = strtolower(trim((string) $value));
            if (in_array($value, ['constant_contact', 'constant contact', 'constantcontact'], true)) {
                return 'constant_contact';
            }
            if (in_array($value, ['mailchimp', 'mail_chimp'], true)) {
                return 'mailchimp';
            }
            if (in_array($value, ['none', ''], true)) {
                return 'none';
            }
            return '';
        };

        $provider = defined('MZF_MARKETING_PROVIDER')
            ? (string) MZF_MARKETING_PROVIDER
            : (string) mzf_get('marketing_provider', 'none');
        $provider = $normalize_provider(apply_filters('mzf_marketing_provider', $provider));

        if ($provider === '') {
            $provider = 'none';
        }

        if ($provider === 'none') {
            $crm_provider_raw = '';
            if (function_exists('get_field')) {
                $crm_acf = get_field('crm', 'option');
                if (is_array($crm_acf)) {
                    $crm_provider_raw = (string) ($crm_acf['platform'] ?? '');
                }
            }
            if ($crm_provider_raw === '') {
                $crm = get_option('crm');
                if (is_array($crm)) {
                    $crm_provider_raw = (string) ($crm['platform'] ?? '');
                }
            }
            if ($crm_provider_raw === '') {
                $crm_provider_raw = (string) get_option('crm_platform', '');
            }
            $crm_provider = $normalize_provider($crm_provider_raw);
            if ($crm_provider !== '' && $crm_provider !== 'none') {
                return $crm_provider;
            }

            if (function_exists('mz_mc_is_configured') && mz_mc_is_configured()) {
                return 'mailchimp';
            }
            if (function_exists('cc_is_configured') && cc_is_configured()) {
                return 'constant_contact';
            }
        }
        return $provider ?: 'none';
    }
}

if (!function_exists('mzf_sync_marketing_contact')) {
    function mzf_sync_marketing_contact(array $data): array
    {
        $provider = mzf_marketing_provider();
        if ($provider === 'none') {
            return ['ok' => false, 'provider' => 'none'];
        }

        if ($provider === 'mailchimp') {
            if (!function_exists('mz_mc_upsert_contact')) {
                return [
                    'ok' => false,
                    'provider' => 'mailchimp',
                    'label' => 'Mailchimp',
                    'error' => 'Mailchimp provider is unavailable.',
                ];
            }
            $result = mz_mc_upsert_contact($data);
            if (is_wp_error($result)) {
                error_log('Mailchimp sync failed: ' . $result->get_error_message());
                return [
                    'ok' => false,
                    'provider' => 'mailchimp',
                    'label' => 'Mailchimp',
                    'error' => $result->get_error_message(),
                ];
            }
            return is_array($result)
                ? $result
                : ['ok' => true, 'provider' => 'mailchimp', 'label' => 'Mailchimp'];
        }

        if ($provider === 'constant_contact') {
            if (!function_exists('mz_cc_add_contact')) {
                return [
                    'ok' => false,
                    'provider' => 'constant_contact',
                    'label' => 'Constant Contact',
                    'error' => 'Constant Contact provider is unavailable.',
                ];
            }
            $phone_value = '';
            foreach (['Phone', 'WorkPhone', 'Work phone', 'work_phone', 'phone', 'phone_number', 'PhoneNumber', 'phoneNumber', 'Work Phone', 'workPhone'] as $phone_key) {
                $candidate = isset($data[$phone_key]) ? trim((string) $data[$phone_key]) : '';
                if ($candidate !== '') {
                    $phone_value = $candidate;
                    break;
                }
            }
            $zip_value = '';
            foreach (['Zip', 'ZipCode', 'Zip code', 'zip_code', 'zipcode', 'Zipcode', 'zipCode', 'PostalCode', 'postal_code', 'Postal Code', 'postal'] as $zip_key) {
                $candidate = isset($data[$zip_key]) ? trim((string) $data[$zip_key]) : '';
                if ($candidate !== '') {
                    $zip_value = $candidate;
                    break;
                }
            }
            $result = mz_cc_add_contact([
                'Email'        => $data['Email'] ?? '',
                'FirstName'    => $data['FirstName'] ?? '',
                'LastName'     => $data['LastName'] ?? '',
                'Phone'        => $phone_value,
                'Zip'          => $zip_value,
                'Company'      => $data['Company'] ?? '',
                'LeadTags'     => $data['LeadTags'] ?? [],
            ]);
            if (is_wp_error($result)) {
                error_log('Constant Contact sync failed: ' . $result->get_error_message());
                return [
                    'ok' => false,
                    'provider' => 'constant_contact',
                    'label' => 'Constant Contact',
                    'error' => $result->get_error_message(),
                ];
            }
            return is_array($result)
                ? $result
                : ['ok' => true, 'provider' => 'constant_contact', 'label' => 'Constant Contact'];
        }

        return ['ok' => false, 'provider' => $provider];
    }
}
