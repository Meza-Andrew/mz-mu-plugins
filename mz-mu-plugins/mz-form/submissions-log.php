<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mzf_register_submission_log_post_type')) {
    function mzf_register_submission_log_post_type(): void
    {
        register_post_type('mzf_submission', [
            'labels' => [
                'name' => 'Form Submissions',
                'singular_name' => 'Form Submission',
            ],
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }
}
add_action('init', 'mzf_register_submission_log_post_type');

if (!function_exists('mzf_log_normalize_value')) {
    function mzf_log_normalize_value($value)
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $k => $v) {
                $normalized[sanitize_key((string) $k)] = mzf_log_normalize_value($v);
            }
            return $normalized;
        }

        if (is_object($value)) {
            if ($value instanceof JsonSerializable) {
                $json_value = $value->jsonSerialize();
                if ($json_value !== $value) {
                    return mzf_log_normalize_value($json_value);
                }
            }

            if ($value instanceof Stringable) {
                return sanitize_textarea_field((string) $value);
            }

            return mzf_log_normalize_value(get_object_vars($value));
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        if (is_resource($value)) {
            return '';
        }

        return sanitize_textarea_field((string) $value);
    }
}

if (!function_exists('mzf_prepare_submission_payload')) {
    function mzf_prepare_submission_payload(array $source): array
    {
        $excluded = [
            'action',
            '_wpnonce',
            '_ajax_nonce',
            'security',
            'g-recaptcha-response',
            'debug_key',
            'mzf_submission_log_status',
            'mzf_submission_log_message',
            'mzf_client_validation_failed',
            'mzf_client_invalid_fields',
            'mzf_client_validation_message',
        ];

        foreach ($excluded as $key) {
            unset($source[$key]);
        }

        return mzf_log_normalize_value(wp_unslash($source));
    }
}

if (!function_exists('mzf_humanize_submission_identifier')) {
    function mzf_humanize_submission_identifier(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = str_replace(['-', '_'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;

        return ucwords($value);
    }
}

if (!function_exists('mzf_log_lookup_value')) {
    function mzf_log_lookup_value(array $entry, array $keys, array ...$sources): string
    {
        $buckets = [$entry];
        foreach ($sources as $source) {
            if (is_array($source) && !empty($source)) {
                $buckets[] = $source;
            }
        }

        foreach ($buckets as $bucket) {
            foreach ($keys as $key) {
                $key = (string) $key;
                $candidates = array_values(array_unique(array_filter([
                    $key,
                    lcfirst($key),
                    sanitize_key($key),
                    strtolower($key),
                ])));

                foreach ($candidates as $candidate) {
                    if (!array_key_exists($candidate, $bucket)) {
                        continue;
                    }

                    $value = trim((string) $bucket[$candidate]);
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return '';
    }
}

if (!function_exists('mzf_split_submission_name')) {
    function mzf_split_submission_name(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?: $name);
        if ($name === '') {
            return ['', ''];
        }

        $parts = explode(' ', $name);
        $first_name = (string) array_shift($parts);
        $last_name = trim(implode(' ', $parts));

        return [$first_name, $last_name];
    }
}

if (!function_exists('mzf_log_app_submission')) {
    function mzf_log_app_submission(array $entry): int
    {
        $payload = isset($entry['payload']) && is_array($entry['payload'])
            ? (array) $entry['payload']
            : [];
        $submitted = isset($entry['submitted']) && is_array($entry['submitted'])
            ? (array) $entry['submitted']
            : $payload;

        $app_slug = sanitize_key((string) ($entry['app_slug'] ?? $entry['app'] ?? 'app'));
        $app_label = sanitize_text_field((string) ($entry['app_label'] ?? mzf_humanize_submission_identifier($app_slug)));
        $form_slug = sanitize_key((string) ($entry['form_slug'] ?? $entry['form'] ?? mzf_log_lookup_value($entry, ['FormSlug', 'form_slug', 'form'], $payload, $submitted)));
        $form_label = sanitize_text_field((string) ($entry['form_label'] ?? mzf_humanize_submission_identifier($form_slug)));
        $source = sanitize_key((string) ($entry['source'] ?? 'app'));
        $source_label = sanitize_text_field((string) ($entry['source_label'] ?? mzf_humanize_submission_identifier($source)));

        $name = mzf_log_lookup_value($entry, ['name', 'contact_name', 'contactName'], $payload, $submitted);
        $first_name = sanitize_text_field((string) ($entry['first_name'] ?? mzf_log_lookup_value($entry, ['first_name', 'FirstName', 'firstname', 'contact_first_name', 'contactFirstName'], $payload, $submitted)));
        $last_name = sanitize_text_field((string) ($entry['last_name'] ?? mzf_log_lookup_value($entry, ['last_name', 'LastName', 'lastname', 'contact_last_name', 'contactLastName'], $payload, $submitted)));

        if ($first_name === '' && $last_name === '' && $name !== '') {
            [$first_name, $last_name] = mzf_split_submission_name($name);
        }

        $email = sanitize_email((string) ($entry['email'] ?? mzf_log_lookup_value($entry, ['email', 'Email', 'contact_email', 'contactEmail'], $payload, $submitted)));
        $phone = sanitize_text_field((string) ($entry['phone'] ?? mzf_log_lookup_value($entry, ['phone', 'Phone', 'phone_number', 'phoneNumber', 'contact_phone', 'contactPhone'], $payload, $submitted)));

        $context_defaults = [];
        if ($app_slug !== '') {
            $context_defaults['AppSlug'] = $app_slug;
        }
        if ($app_label !== '') {
            $context_defaults['AppLabel'] = $app_label;
        }
        if ($form_slug !== '') {
            $context_defaults['FormSlug'] = $form_slug;
        }
        if ($form_label !== '') {
            $context_defaults['FormLabel'] = $form_label;
        }
        if ($email !== '') {
            $context_defaults['Email'] = $email;
        }
        if ($first_name !== '') {
            $context_defaults['FirstName'] = $first_name;
        }
        if ($last_name !== '') {
            $context_defaults['LastName'] = $last_name;
        }
        if ($phone !== '') {
            $context_defaults['Phone'] = $phone;
        }

        return mzf_log_submission(array_merge($entry, [
            'source' => $source,
            'source_label' => $source_label,
            'app_slug' => $app_slug,
            'app_label' => $app_label,
            'form_slug' => $form_slug,
            'form_label' => $form_label,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'payload' => array_merge($context_defaults, $payload),
            'submitted' => array_merge($context_defaults, $submitted),
        ]));
    }
}

if (!function_exists('mzf_submission_status_group')) {
    function mzf_submission_status_group(string $raw_status): string
    {
        $raw_status = sanitize_key($raw_status);
        if (in_array($raw_status, ['success', 'spam_detected', 'failure_validation', 'failure_form_issue'], true)) {
            return $raw_status;
        }

        if ($raw_status === 'success') {
            return 'success';
        }

        if (in_array($raw_status, ['validation_honeypot', 'error_recaptcha_failed', 'error_recaptcha_token_missing'], true)) {
            return 'spam_detected';
        }

        if ($raw_status !== '' && strpos($raw_status, 'validation_') === 0) {
            return 'failure_validation';
        }

        return 'failure_form_issue';
    }
}

if (!function_exists('mzf_submission_status_label')) {
    function mzf_submission_status_label(string $status): string
    {
        $status = sanitize_key($status);
        $group = in_array($status, ['success', 'spam_detected', 'failure_validation', 'failure_form_issue'], true)
            ? $status
            : mzf_submission_status_group($status);

        if ($group === 'success') {
            return 'Success';
        }

        if ($group === 'spam_detected') {
            return 'Spam Detected';
        }

        if ($group === 'failure_validation') {
            return 'Validation Issue';
        }

        return 'Form Issue';
    }
}

if (!function_exists('mzf_log_submission')) {
    function mzf_log_submission(array $entry): int
    {
        if (!post_type_exists('mzf_submission')) {
            return 0;
        }

        $payload = (array) ($entry['payload'] ?? []);
        $submitted = (array) ($entry['submitted'] ?? []);

        $form_slug = sanitize_key((string) ($entry['form_slug'] ?? ($payload['FormSlug'] ?? $submitted['FormSlug'] ?? '')));
        $form_label = sanitize_text_field((string) ($entry['form_label'] ?? ''));
        if ($form_label === '') {
            $form_label = mzf_humanize_submission_identifier($form_slug);
        }
        $source = sanitize_key((string) ($entry['source'] ?? 'form'));
        $source_label = sanitize_text_field((string) ($entry['source_label'] ?? mzf_humanize_submission_identifier($source)));
        $app_slug = sanitize_key((string) ($entry['app_slug'] ?? $entry['app'] ?? ''));
        $app_label = sanitize_text_field((string) ($entry['app_label'] ?? mzf_humanize_submission_identifier($app_slug)));
        $email = sanitize_email((string) ($entry['email'] ?? ($payload['Email'] ?? $submitted['Email'] ?? '')));
        $first_name = sanitize_text_field((string) ($entry['first_name'] ?? ($payload['FirstName'] ?? $submitted['FirstName'] ?? '')));
        $last_name = sanitize_text_field((string) ($entry['last_name'] ?? ($payload['LastName'] ?? $submitted['LastName'] ?? '')));
        $phone = sanitize_text_field((string) ($entry['phone'] ?? ($payload['Phone'] ?? $submitted['Phone'] ?? '')));
        $crm_platform_raw = (string) ($entry['crm_platform'] ?? '');
        $crm_platform = sanitize_key(str_replace('-', '_', strtolower(trim($crm_platform_raw))));
        $crm_platform_label = sanitize_text_field((string) ($entry['crm_platform_label'] ?? ''));
        $newsletter_opt_in = !empty($entry['newsletter_opt_in']);
        $marketing_sync = isset($entry['marketing_sync']) && is_array($entry['marketing_sync'])
            ? mzf_log_normalize_value((array) $entry['marketing_sync'])
            : [];
        $name = trim($first_name . ' ' . $last_name);
        $status = sanitize_key((string) ($entry['delivery_status'] ?? 'unknown'));
        $status_group = mzf_submission_status_group($status);
        $status_label = mzf_submission_status_label($status_group);
        $page_id = (int) ($entry['page_id'] ?? ($payload['PageId'] ?? $submitted['PageId'] ?? 0));
        $recipients = array_values(array_filter(array_map('sanitize_email', (array) ($entry['recipients'] ?? [])), 'is_email'));
        $admin_to = implode(', ', $recipients);
        $form_post_id = 0;
        if ($form_slug !== '') {
            $form_post = get_page_by_path($form_slug, OBJECT, 'form');
            if ($form_post instanceof WP_Post) $form_post_id = (int) $form_post->ID;
        }

        $title_context = $form_label;
        if ($title_context === '') {
            if ($form_slug !== '') {
                $title_context = strtoupper($form_slug);
            } elseif ($app_label !== '') {
                $title_context = $app_label;
            } elseif ($source_label !== '') {
                $title_context = $source_label;
            } else {
                $title_context = 'Form';
            }
        }

        $title_parts = array_values(array_filter([
            $title_context,
            $name,
            $email,
            $status_label,
        ], static fn($v) => trim((string) $v) !== ''));

        $post_id = wp_insert_post([
            'post_type' => 'mzf_submission',
            'post_status' => 'private',
            'post_title' => implode(' | ', $title_parts),
        ], true);

        if (is_wp_error($post_id) || !$post_id) {
            return 0;
        }

        update_post_meta($post_id, '_mzf_form_slug', $form_slug);
        update_post_meta($post_id, '_mzf_form_label', $form_label);
        update_post_meta($post_id, '_mzf_source', $source);
        update_post_meta($post_id, '_mzf_source_label', $source_label);
        update_post_meta($post_id, '_mzf_app_slug', $app_slug);
        update_post_meta($post_id, '_mzf_app_label', $app_label);
        update_post_meta($post_id, '_mzf_email', $email);
        update_post_meta($post_id, '_mzf_name', $name);
        update_post_meta($post_id, '_mzf_first_name', $first_name);
        update_post_meta($post_id, '_mzf_last_name', $last_name);
        update_post_meta($post_id, '_mzf_phone', $phone);
        update_post_meta($post_id, '_mzf_page_id', $page_id);
        update_post_meta($post_id, '_mzf_form_post_id', $form_post_id);
        update_post_meta($post_id, '_mzf_admin_to', $admin_to);
        update_post_meta($post_id, '_mzf_delivery_status', $status);
        update_post_meta($post_id, '_mzf_delivery_status_group', $status_group);
        update_post_meta($post_id, '_mzf_admin_ok', !empty($entry['admin_ok']) ? '1' : '0');
        update_post_meta($post_id, '_mzf_user_ok', !empty($entry['user_ok']) ? '1' : '0');
        update_post_meta($post_id, '_mzf_subject', sanitize_text_field((string) ($entry['subject'] ?? '')));
        update_post_meta($post_id, '_mzf_env', sanitize_key((string) ($entry['env'] ?? '')));
        update_post_meta($post_id, '_mzf_error_message', sanitize_textarea_field((string) ($entry['error_message'] ?? '')));
        update_post_meta($post_id, '_mzf_recipients', mzf_log_normalize_value($recipients));
        update_post_meta($post_id, '_mzf_payload', mzf_log_normalize_value($payload));
        update_post_meta($post_id, '_mzf_submitted', mzf_log_normalize_value($submitted));
        update_post_meta($post_id, '_mzf_attachments', mzf_log_normalize_value((array) ($entry['attachments'] ?? [])));
        update_post_meta($post_id, '_mzf_crm_platform', $crm_platform);
        update_post_meta($post_id, '_mzf_crm_platform_label', $crm_platform_label);
        update_post_meta($post_id, '_mzf_newsletter_opt_in', $newsletter_opt_in ? '1' : '0');
        update_post_meta($post_id, '_mzf_marketing_sync', $marketing_sync);

        return (int) $post_id;
    }
}
