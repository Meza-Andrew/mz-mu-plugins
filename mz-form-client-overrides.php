<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Client-level MZ Form overrides.
 *
 * Keep this file portable across sites by limiting it to filters.
 */

$mzf_contact_interest_data = static function (array $data, array $src = []): array {
    $interest_source = $data['Interests'] ?? $src['Interests'] ?? [];
    $interest_values = [];

    if (is_array($interest_source)) {
        foreach ($interest_source as $interest_value) {
            $interest_value = trim((string) $interest_value);
            if ($interest_value !== '') {
                $interest_values[] = $interest_value;
            }
        }
    } else {
        $interest_text = trim((string) $interest_source);
        if ($interest_text !== '') {
            foreach (preg_split('/\s*,\s*/', $interest_text) as $interest_value) {
                $interest_value = trim((string) $interest_value);
                if ($interest_value !== '') {
                    $interest_values[] = $interest_value;
                }
            }
        }
    }

    $labels_by_key = [
        'borrowing' => 'Borrowing',
        'co-lending' => 'Co-Lending',
        'third-party' => 'Third-Party',
        'other' => 'Other',
    ];

    $normalized_keys = [];
    foreach ($interest_values as $interest_value) {
        $interest_key = sanitize_key((string) $interest_value);
        if (!isset($labels_by_key[$interest_key])) {
            continue;
        }
        $normalized_keys[] = $interest_key;
    }

    $normalized_keys = array_values(array_unique($normalized_keys));
    $business_interest_labels = [];
    foreach ($normalized_keys as $interest_key) {
        if (!in_array($interest_key, ['borrowing', 'co-lending'], true)) {
            continue;
        }
        $business_interest_labels[] = $labels_by_key[$interest_key];
    }

    $request_interest = implode('/', $business_interest_labels);
    $primary_subject = count($normalized_keys) === 1 ? (string) $normalized_keys[0] : '';

    return [
        'keys' => $normalized_keys,
        'request_interest' => $request_interest,
        'primary_subject' => $primary_subject,
    ];
};

add_filter('mzf_recipients', static function (array $to, array $data, $env): array {
    $env_name = strtolower(trim((string) $env));
    if (in_array($env_name, ['development', 'staging', 'local'], true)) {
        return $to;
    }

    $form_slug = sanitize_key((string) ($data['FormSlug'] ?? ''));
    if ($form_slug === 'contact') {
        $option_email = function_exists('get_field') ? sanitize_email((string) get_field('email', 'option')) : '';
        $interest_map = [
            'volunteer' => [$option_email],
            'sponsor' => [$option_email],
            'events' => [$option_email],
            'hiv-testing' => ['jason@fahass.org', 'michelle@fahass.org'],
            'appointments' => ['jason@fahass.org', 'michelle@fahass.org'],
            'free-condoms' => ['jason@fahass.org', 'michelle@fahass.org'],
        ];

        $raw_interests = $data['Interests'] ?? [];
        if (!is_array($raw_interests)) {
            $raw_interests = preg_split('/\s*,\s*/', trim((string) $raw_interests)) ?: [];
        }

        $mapped_recipients = [];
        foreach ((array) $raw_interests as $interest) {
            $interest_key = sanitize_title((string) $interest);
            if ($interest_key === '' || empty($interest_map[$interest_key])) {
                continue;
            }
            foreach ($interest_map[$interest_key] as $email) {
                $email = sanitize_email((string) $email);
                if (is_email($email)) {
                    $mapped_recipients[] = $email;
                }
            }
        }

        $mapped_recipients = array_values(array_unique($mapped_recipients));
        if (!empty($mapped_recipients)) {
            return $mapped_recipients;
        }
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

add_filter('mzf_field_labels', static function (array $labels, array $data): array {
    $slug = sanitize_key((string) ($data['FormSlug'] ?? ''));
    if ($slug === 'contact') {
        $labels['Interests'] = 'Interested In';
    }
    if ($slug === 'co-lender') {
        $labels['Experience'] = 'Co-Lended Before';
        $labels['Comments'] = 'Comments';
    }

    return $labels;
}, 20, 2);

add_filter('mzf_admin_request_heading', static function (string $heading, array $data): string {
    $slug = sanitize_key((string) ($data['FormSlug'] ?? ''));
    if ($slug === 'co-lender') {
        return 'Experience';
    }

    return $heading;
}, 20, 2);

add_filter('mzf_normalized_data', static function (array $data, array $src) use ($mzf_contact_interest_data): array {
    $slug = sanitize_key((string) ($data['FormSlug'] ?? $src['FormSlug'] ?? ''));
    if ($slug === 'contact') {
        $contact_interest_data = $mzf_contact_interest_data($data, $src);
        $data['ContactInterestKeys'] = $contact_interest_data['keys'];
        $data['ContactInterestPrimary'] = $contact_interest_data['primary_subject'];
        $data['Interests'] = $contact_interest_data['request_interest'];
        return $data;
    }

    if ($slug !== 'co-lender') {
        return $data;
    }

    $experience = trim((string) ($data['Experience'] ?? $src['Experience'] ?? ''));
    $co_lender = trim((string) ($data['CoLender'] ?? $src['CoLender'] ?? ''));
    if ($experience !== '') {
        $experience_normalized = strtolower($experience);
        if ($experience_normalized === 'yes' && $co_lender !== '') {
            $data['Experience'] = 'Yes with ' . $co_lender;
        } elseif ($experience_normalized === 'yes') {
            $data['Experience'] = 'Yes';
        } elseif ($experience_normalized === 'no') {
            $data['Experience'] = 'No';
        } else {
            $data['Experience'] = $experience;
        }
    }

    return $data;
}, 20, 2);

add_filter('mzf_subject', static function (string $subject, array $data): string {
    $slug = sanitize_key((string) ($data['FormSlug'] ?? ''));
    if ($slug !== 'contact') {
        return $subject;
    }

    $contact_interest = sanitize_key((string) ($data['ContactInterestPrimary'] ?? ''));
    if ($contact_interest === 'co-lending') {
        return 'An interested co-lender sent a message';
    }
    if ($contact_interest === 'borrowing') {
        return 'An interested borrower sent a message';
    }
    if ($contact_interest === 'third-party') {
        return 'A third-party involved in a transaction sent a message';
    }

    return $subject;
}, 20, 2);
