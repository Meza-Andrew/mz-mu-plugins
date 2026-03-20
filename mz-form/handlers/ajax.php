<?php

add_action('wp_ajax_nopriv_send_form_data', 'send_form_data');
add_action('wp_ajax_send_form_data',        'send_form_data');
add_action('wp_ajax_nopriv_mzf_log_submit_attempt', 'mzf_log_submit_attempt');
add_action('wp_ajax_mzf_log_submit_attempt',        'mzf_log_submit_attempt');
add_action('wp_ajax_nopriv_mzf_log_client_validation_attempt', 'mzf_log_submit_attempt');
add_action('wp_ajax_mzf_log_client_validation_attempt',        'mzf_log_submit_attempt');

if (!function_exists('mzf_log_submit_attempt')) :
    function mzf_log_submit_attempt()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(['message' => 'Invalid request method.'], 405);
        }

        if (!function_exists('mzf_log_submission')) {
            wp_send_json_error(['message' => 'Submission logger unavailable.'], 500);
        }

        global $env;
        $env_default = (string) mzf_get('default_env', 'production');
        $env = !empty($env) ? strtolower((string) $env) : strtolower($env_default);

        $submitted_payload = function_exists('mzf_prepare_submission_payload')
            ? mzf_prepare_submission_payload((array) $_POST)
            : (array) wp_unslash($_POST);
        $attempt_message = isset($_POST['mzf_submission_log_message'])
            ? sanitize_textarea_field((string) wp_unslash($_POST['mzf_submission_log_message']))
            : '';
        $client_message = isset($_POST['mzf_client_validation_message'])
            ? sanitize_textarea_field((string) wp_unslash($_POST['mzf_client_validation_message']))
            : '';
        $client_invalid_fields = isset($_POST['mzf_client_invalid_fields'])
            ? sanitize_text_field((string) wp_unslash($_POST['mzf_client_invalid_fields']))
            : '';
        $action = isset($_POST['action'])
            ? sanitize_key((string) wp_unslash($_POST['action']))
            : '';
        $client_validation_failed = isset($_POST['mzf_client_validation_failed'])
            && in_array(strtolower(trim((string) wp_unslash($_POST['mzf_client_validation_failed']))), ['1', 'true', 'yes'], true);
        $is_client_validation_attempt = (
            $action === 'mzf_log_client_validation_attempt' ||
            $client_validation_failed ||
            $client_invalid_fields !== ''
        );
        $delivery_status = isset($_POST['mzf_submission_log_status'])
            ? sanitize_key((string) wp_unslash($_POST['mzf_submission_log_status']))
            : '';
        if ($delivery_status === '') {
            $delivery_status = $is_client_validation_attempt ? 'validation_client' : 'error';
        } elseif ($delivery_status === 'error' && $is_client_validation_attempt) {
            $delivery_status = 'validation_client';
        }

        $message = $attempt_message !== ''
            ? $attempt_message
            : ($client_message !== '' ? $client_message : 'Submit button clicked.');

        $error_parts = array_values(array_filter([
            $message,
            ($client_invalid_fields !== '') ? 'Invalid fields: ' . $client_invalid_fields : '',
        ]));

        $log_id = (int) mzf_log_submission([
            'form_slug' => sanitize_key((string) ($submitted_payload['FormSlug'] ?? '')),
            'page_id' => isset($submitted_payload['PageId']) ? (int) $submitted_payload['PageId'] : 0,
            'email' => sanitize_email((string) ($submitted_payload['Email'] ?? $submitted_payload['ContactEmail'] ?? '')),
            'first_name' => sanitize_text_field((string) ($submitted_payload['FirstName'] ?? $submitted_payload['ContactFirstName'] ?? '')),
            'last_name' => sanitize_text_field((string) ($submitted_payload['LastName'] ?? $submitted_payload['ContactLastName'] ?? '')),
            'phone' => sanitize_text_field((string) ($submitted_payload['Phone'] ?? $submitted_payload['ContactPhone'] ?? '')),
            'delivery_status' => $delivery_status,
            'admin_ok' => false,
            'user_ok' => false,
            'env' => (string) $env,
            'error_message' => implode(' | ', $error_parts),
            'payload' => $submitted_payload,
            'submitted' => $submitted_payload,
        ]);

        wp_send_json_success(['submission_log_id' => $log_id], 200);
    }
endif;

if (!function_exists('send_form_data')) :
    function send_form_data()
    {

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(['message' => 'Invalid request method.'], 405);
        }

        global $env, $prod_url;
        $env_default = (string) mzf_get('default_env', 'production');
        $env      = !empty($env) ? strtolower($env) : strtolower($env_default);
        $prod_url = !empty($prod_url) ? $prod_url : home_url();
        $domain   = parse_url($prod_url, PHP_URL_HOST);
        $site_domain = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        if ($site_domain === '' && !empty($_SERVER['HTTP_HOST'])) {
            $site_domain = preg_replace('/:\d+$/', '', (string) $_SERVER['HTTP_HOST']);
        }
        if ($site_domain === '') {
            $site_domain = (string) $domain;
        }
        $debug_allowed = function_exists('mzf_debug_allowed') ? mzf_debug_allowed((array) $_POST) : false;
        $debug_log_enabled = (bool) mzf_get('debug_log', true);
        $debug_response_enabled = $debug_allowed && (bool) mzf_get('debug_response', false);
        $debug_log = static function (string $event, array $context = []) use ($debug_allowed, $debug_log_enabled) {
            if (!$debug_allowed || !$debug_log_enabled) {
                return;
            }
            error_log('MZF DEBUG [' . $event . '] ' . wp_json_encode($context));
        };
        $debug_log('request_start', [
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
            'env' => (string) $env,
            'keys' => array_values(array_map('strval', array_keys((array) $_POST))),
        ]);

        $submitted_payload = function_exists('mzf_prepare_submission_payload')
            ? mzf_prepare_submission_payload((array) $_POST)
            : (array) wp_unslash($_POST);
        $data = [];
        $log_submission_attempt = static function (array $entry) use (&$data, $env, $submitted_payload): int {
            if (!function_exists('mzf_log_submission')) {
                return 0;
            }

            if (!isset($entry['payload']) || !is_array($entry['payload'])) {
                $entry['payload'] = (array) $data;
            }
            if (!isset($entry['submitted']) || !is_array($entry['submitted'])) {
                $entry['submitted'] = $submitted_payload;
            }
            if (!isset($entry['env'])) {
                $entry['env'] = (string) $env;
            }
            if (!isset($entry['form_slug'])) {
                $entry['form_slug'] = (string) ($entry['payload']['FormSlug'] ?? $submitted_payload['FormSlug'] ?? '');
            }
            if (!isset($entry['page_id'])) {
                $entry['page_id'] = (int) ($entry['payload']['PageId'] ?? $submitted_payload['PageId'] ?? 0);
            }
            if (!isset($entry['email'])) {
                $entry['email'] = (string) ($entry['payload']['Email'] ?? $submitted_payload['Email'] ?? '');
            }
            if (!isset($entry['first_name'])) {
                $entry['first_name'] = (string) ($entry['payload']['FirstName'] ?? $submitted_payload['FirstName'] ?? '');
            }
            if (!isset($entry['last_name'])) {
                $entry['last_name'] = (string) ($entry['payload']['LastName'] ?? $submitted_payload['LastName'] ?? '');
            }
            if (!isset($entry['phone'])) {
                $entry['phone'] = (string) ($entry['payload']['Phone'] ?? $submitted_payload['Phone'] ?? '');
            }

            return (int) mzf_log_submission($entry);
        };
        $fail_request = static function (string $message, int $status_code, string $delivery_status, array $entry = []) use ($log_submission_attempt): void {
            if (!isset($entry['delivery_status'])) {
                $entry['delivery_status'] = $delivery_status;
            }
            if (!isset($entry['error_message'])) {
                $entry['error_message'] = $message;
            }
            $log_submission_attempt($entry);
            wp_send_json_error(['message' => $message], $status_code);
        };

        $client_validation_failed = isset($_POST['mzf_client_validation_failed'])
            && in_array(strtolower(trim((string) wp_unslash($_POST['mzf_client_validation_failed']))), ['1', 'true', 'yes'], true);
        if ($client_validation_failed) {
            $client_message = isset($_POST['mzf_client_validation_message'])
                ? sanitize_textarea_field((string) wp_unslash($_POST['mzf_client_validation_message']))
                : 'Client-side validation failed.';
            $client_invalid_fields = isset($_POST['mzf_client_invalid_fields'])
                ? sanitize_text_field((string) wp_unslash($_POST['mzf_client_invalid_fields']))
                : '';

            $error_parts = array_values(array_filter([
                $client_message,
                ($client_invalid_fields !== '') ? 'Invalid fields: ' . $client_invalid_fields : '',
            ]));

            $submission_log_id = $log_submission_attempt([
                'delivery_status' => 'validation_client',
                'admin_ok' => false,
                'user_ok' => false,
                'error_message' => implode(' | ', $error_parts),
                'submitted' => $submitted_payload,
            ]);

            wp_send_json_success([
                'submission_log_id' => $submission_log_id,
            ], 200);
        }

        // --- reCAPTCHA v3 ---
        $recaptcha_disabled_default = defined('DS_RECAPTCHA_DISABLE') && DS_RECAPTCHA_DISABLE === true;
        if (in_array($env, ['development', 'local'], true)) {
            $recaptcha_disabled_default = true;
        }
        $recaptcha_disabled = (bool) apply_filters('mzf_recaptcha_disabled', $recaptcha_disabled_default);

        if (!$recaptcha_disabled) {
            $recaptcha_token = isset($_POST['g-recaptcha-response']) ? sanitize_text_field($_POST['g-recaptcha-response']) : '';
            if ($recaptcha_token === '') {
                error_log('reCAPTCHA: token missing');
                $fail_request('reCAPTCHA token missing.', 400, 'error_recaptcha_token_missing');
            }

            $recaptcha_secret_key = defined('GRECAPTCHA_SECRET_KEY') ? GRECAPTCHA_SECRET_KEY : '';
            if ($recaptcha_secret_key === '') {
                error_log('reCAPTCHA: secret key missing');
                $fail_request('Server configuration error (reCAPTCHA).', 500, 'error_recaptcha_config');
            }

            $resp = wp_remote_post(
                'https://www.google.com/recaptcha/api/siteverify',
                [
                    'body'    => [
                        'secret'   => $recaptcha_secret_key,
                        'response' => $recaptcha_token,
                        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
                    ],
                    'timeout' => 15,
                ]
            );

            if (is_wp_error($resp)) {
                error_log('reCAPTCHA: HTTP error - ' . $resp->get_error_message());
                $fail_request('reCAPTCHA HTTP error.', 400, 'error_recaptcha_http', [
                    'error_message' => 'reCAPTCHA HTTP error: ' . $resp->get_error_message(),
                ]);
            }

            $result = json_decode(wp_remote_retrieve_body($resp), true);
            $score  = isset($result['score']) ? (float) $result['score'] : null;

            if (empty($result['success']) || $score < 0.5) {
                error_log('reCAPTCHA: failed, score=' . ($score ?? 'N/A'));
                $fail_request('Failed bot check.', 400, 'error_recaptcha_failed', [
                    'error_message' => 'Failed bot check. Score=' . ($score ?? 'N/A'),
                ]);
            }
        }

        $h = static function ($v) {
            return esc_html($v);
        };
        $maybe_join = static function ($v) {
            return is_array($v) ? implode(', ', array_map('sanitize_text_field', $v)) : trim((string) $v);
        };
        $human_date = static function ($raw) {
            $raw = trim((string) $raw);
            if ($raw === '') return '';
            $ts = strtotime($raw);
            if (!$ts) return $raw;
            return (date('H:i', $ts) !== '00:00') ? date_i18n('F j, Y \a\t g:i A', $ts) : date_i18n('F j, Y', $ts);
        };
        $format_weeks_days = static function ($days_raw) {
            if ($days_raw === null || $days_raw === '') return '';
            if (is_array($days_raw)) $days_raw = reset($days_raw);
            $days  = (int) preg_replace('/\D+/', '', (string) $days_raw);
            if ($days < 0) return trim((string)$days_raw);
            $weeks = intdiv($days, 7);
            $rem = $days % 7;
            $parts = [];
            if ($weeks > 0) $parts[] = $weeks . ' ' . ($weeks === 1 ? 'week' : 'weeks');
            if ($rem   > 0) $parts[] = $rem   . ' ' . ($rem   === 1 ? 'day'  : 'days');
            return $parts ? implode(' ', $parts) : '0 days';
        };

        $fields = function_exists('mzf_fields') ? mzf_fields() : [];

        $src  = $_POST;
        $strict_mode = function_exists('mzf_is_strict_mode') ? mzf_is_strict_mode() : false;
        $legacy_keys = function_exists('mzf_find_legacy_keys') ? mzf_find_legacy_keys((array) $src) : [];
        if (!empty($legacy_keys)) {
            do_action('mzf_deprecated_keys_detected', $legacy_keys, $src);
            $log_deprecations = (bool) mzf_get('strict_log_deprecations', true);
            if ($log_deprecations) {
                error_log('MZF deprecated keys: ' . wp_json_encode($legacy_keys));
            }
            $pairs = [];
            foreach ($legacy_keys as $legacy => $canonical) {
                $pairs[] = $legacy . '->' . $canonical;
            }
            $debug_log('legacy_keys_rejected', ['legacy_keys' => $legacy_keys]);
            $fail_request('Legacy fields are not allowed. Use canonical keys: ' . implode(', ', $pairs), 400, 'validation_legacy_keys');
        }

        if (function_exists('mzf_reject_unknown_fields') && mzf_reject_unknown_fields()) {
            $allowed_transport = ['action', 'g-recaptcha-response', '_wpnonce', '_ajax_nonce', 'security'];
            $allowed = array_fill_keys(array_merge((array) $fields, $allowed_transport), true);
            $unknown = [];
            foreach (array_keys((array) $src) as $k) {
                $key = (string) $k;
                if (isset($allowed[$key])) {
                    continue;
                }
                if (stripos($key, 'utm_') === 0) {
                    continue;
                }
                if ($key === 'debug_key') {
                    continue;
                }
                $unknown[] = $key;
            }
            if (!empty($unknown)) {
                $debug_log('unknown_fields_rejected', ['unknown' => $unknown]);
                $fail_request('Unknown fields are not allowed: ' . implode(', ', $unknown), 400, 'validation_unknown_fields');
            }
        }

        foreach ($fields as $field) {
            if (!isset($src[$field])) {
                $data[$field] = '';
                continue;
            }
            $val = $src[$field];
            if ($field === 'Comments') {
                $data[$field] = is_array($val)
                    ? array_map('sanitize_textarea_field', array_filter($val, static fn($v) => $v !== '' && $v !== null))
                    : sanitize_textarea_field((string) $val);
            } else {
                $data[$field] = is_array($val)
                    ? array_map('sanitize_text_field', array_filter($val, static fn($v) => $v !== '' && $v !== null))
                    : sanitize_text_field($val);
            }
        }

        // Support original DS field names while keeping a stable internal contract.
        if (trim((string) ($data['Website'] ?? '')) === '') {
            $data['Website'] = trim((string) ($data['Honeypot'] ?? ''));
        }
        if (trim((string) ($data['Phone'] ?? '')) === '') {
            $data['Phone'] = trim((string) (
                $data['ContactPhone']
                ?? $data['WorkPhone']
                ?? $data['Work Phone']
                ?? $data['work_phone']
                ?? $data['phone_number']
                ?? $data['PhoneNumber']
                ?? $data['WorkPhoneNumber']
                ?? $data['workPhone']
                ?? ''
            ));
        }
        if (trim((string) ($data['Zip'] ?? '')) === '') {
            $data['Zip'] = trim((string) (
                $data['ZipCode']
                ?? $data['Zip code']
                ?? $data['zip_code']
                ?? $data['zipcode']
                ?? $data['Zipcode']
                ?? $data['zipCode']
                ?? $data['PostalCode']
                ?? $data['Postal Code']
                ?? $data['postal_code']
                ?? $data['postal']
                ?? $data['ReceivingAddressPostal']
                ?? $data['ReceivingAddressZip']
                ?? ''
            ));
        }
        if (trim((string) ($data['Zip'] ?? '')) === '') {
            $address_sources = [
                (string) ($data['LocationDisplay'] ?? ''),
                (string) ($data['ReceivingAddressDisplay'] ?? ''),
                (string) ($data['ReceivingAddress'] ?? ''),
                (string) ($data['Address'] ?? ''),
                (string) ($data['Location'] ?? ''),
            ];
            foreach ($address_sources as $address_source) {
                $address_source = trim($address_source);
                if ($address_source === '') {
                    continue;
                }
                if (preg_match('/\\b(\\d{5}(?:-\\d{4})?)\\b/', $address_source, $zip_match)) {
                    $data['Zip'] = (string) ($zip_match[1] ?? '');
                    break;
                }
                if (preg_match('/\\b([A-Za-z]\\d[A-Za-z][\\s-]?\\d[A-Za-z]\\d)\\b/', $address_source, $postal_match)) {
                    $data['Zip'] = strtoupper((string) ($postal_match[1] ?? ''));
                    break;
                }
            }
        }
        $interest_values = is_array($data['Interests'] ?? null)
            ? array_map('sanitize_text_field', (array) $data['Interests'])
            : array_filter(array_map('trim', explode(',', (string) ($data['Interests'] ?? ''))));
        $interest_values = array_values(array_filter($interest_values, static fn($v) => trim((string) $v) !== ''));
        $has_other_selected = false;
        foreach ($interest_values as $interest_value) {
            if (strcasecmp(trim((string) $interest_value), 'Other') === 0) {
                $has_other_selected = true;
                break;
            }
        }
        $interest_values = array_values(array_filter(
            $interest_values,
            static function ($v): bool {
                $value = trim((string) $v);
                if ($value === '') {
                    return false;
                }
                if (strcasecmp($value, 'Other') === 0) {
                    return false;
                }
                if (strcasecmp($value, 'I am open to any role') === 0 || strcasecmp($value, 'Open to Any Role') === 0) {
                    return false;
                }
                return true;
            }
        ));
        if ($has_other_selected) {
            $other_interest = trim((string) ($data['OtherInterest'] ?? ''));
            if ($other_interest !== '') {
                $interest_values[] = sanitize_text_field($other_interest);
            }
        }
        $data['Interests'] = array_values(array_unique($interest_values));

        if (trim((string) ($data['DateNeeded'] ?? '')) === '') {
            $data['DateNeeded'] = trim((string) ($data['RentalDate'] ?? $data['Deadline'] ?? $data['Date'] ?? ''));
        }
        if (trim((string) ($data['Duration'] ?? '')) === '') {
            $data['Duration'] = trim((string) ($data['RentalDuration'] ?? $data['Days'] ?? ''));
        }
        if (trim((string) ($data['ItemType'] ?? '')) === '') {
            $data['ItemType'] = trim((string) ($data['Service'] ?? $data['SignType'] ?? $data['ApparelType'] ?? $data['PrintType'] ?? $data['WrapType'] ?? ''));
        }
        if (trim((string) ($data['ItemName'] ?? '')) === '') {
            $data['ItemName'] = trim((string) ($data['Products'] ?? $data['ProductName'] ?? ''));
        }
        if (trim((string) ($data['Dimensions'] ?? '')) === '') {
            $data['Dimensions'] = trim((string) ($data['PrintSize'] ?? $data['Size'] ?? ''));
        }
        if (trim((string) ($data['Quantity'] ?? '')) === '') {
            $data['Quantity'] = trim((string) ($data['PrintCount'] ?? $data['PrintQuantity'] ?? $data['Count'] ?? $data['Amount'] ?? ''));
        }
        if (trim((string) ($data['LocationDisplay'] ?? '')) === '') {
            $data['LocationDisplay'] = trim((string) ($data['Location'] ?? $data['Place'] ?? $data['ReceivingAddressDisplay'] ?? $data['ReceivingAddress'] ?? ''));
        }
        // Canonical comments key only.
        $comments = isset($data['Comments']) ? trim((string) $data['Comments']) : '';
        $data['Comments'] = $comments;

        // Canonical slug/value normalization for cross-client standardization.
        $raw_slug = function_exists('mzf_get_form_slug')
            ? mzf_get_form_slug($data)
            : sanitize_key((string) ($data['FormSlug'] ?? ''));
        if (!$strict_mode && $raw_slug === '' && function_exists('mzf_infer_form_slug')) {
            $raw_slug = mzf_infer_form_slug($data);
        }
        $data['FormSlugRaw'] = $raw_slug;
        $data['FormSlug'] = function_exists('mzf_normalize_form_slug')
            ? mzf_normalize_form_slug($raw_slug)
            : $raw_slug;
        if (!function_exists('mzf_is_registered_slug') || !mzf_is_registered_slug((string) $data['FormSlug'])) {
            $fail_request('Unsupported FormSlug: ' . (string) $data['FormSlug'], 400, 'validation_unsupported_form_slug');
        }
        $legacy_slug_target = function_exists('mzf_legacy_slug_target')
            ? mzf_legacy_slug_target($raw_slug)
            : '';
        if ($legacy_slug_target !== '') {
            do_action('mzf_deprecated_slug_detected', $raw_slug, $legacy_slug_target, $src);
            $log_deprecations = (bool) mzf_get('strict_log_deprecations', true);
            if ($log_deprecations) {
                error_log('MZF deprecated FormSlug alias: ' . $raw_slug . '->' . $legacy_slug_target);
            }
            $fail_request('Legacy FormSlug alias is not allowed. Use: ' . $legacy_slug_target, 400, 'validation_legacy_form_slug');
        }
        if ($strict_mode) {
            if ($raw_slug === '') {
                $fail_request('Missing required field: FormSlug', 400, 'validation_missing_form_slug');
            }
            if ($data['FormSlug'] !== $raw_slug) {
                $fail_request('Non-canonical FormSlug provided. Use: ' . $data['FormSlug'], 400, 'validation_noncanonical_form_slug');
            }
        }

        if ($strict_mode) {
            foreach (['Interests'] as $array_key) {
                if (!array_key_exists($array_key, $src)) {
                    continue;
                }
                if (!is_array($src[$array_key])) {
                    $fail_request($array_key . ' must be submitted as an array in strict mode.', 400, 'validation_invalid_array_shape');
                }
            }
        }

        $interests_source = $data['Interests'] ?? ($_POST['Interests'] ?? '');
        $data['InterestsRaw'] = $interests_source;
        $data['InterestsNorm'] = function_exists('mzf_normalize_terms')
            ? mzf_normalize_terms($interests_source)
            : [];

        $required_fields = apply_filters('mzf_required_fields', ['FirstName', 'LastName', 'Email'], $data);
        $required_fields = mzf_apply_required_rules((array) $required_fields, $data);
        $require_last_name = (bool) apply_filters(
            'mzf_require_last_name',
            in_array('LastName', (array) $required_fields, true),
            $data,
            (array) $required_fields
        );
        if (!$require_last_name) {
            $required_fields = array_values(array_filter(
                (array) $required_fields,
                static fn($field) => (string) $field !== 'LastName'
            ));
        }
        foreach ($required_fields as $rf) {
            if (empty($data[$rf])) {
                error_log("Form: missing required field {$rf}");
                $fail_request("Missing required field: {$rf}", 400, 'validation_missing_required_field');
            }
        }

        $default_honeypot = 'Website';
        $honeypot_field = apply_filters('mzf_honeypot_field', $default_honeypot, $data);
        if (!empty($honeypot_field) && !empty($data[$honeypot_field])) {
            error_log("Form: honeypot tripped");
            $debug_log('honeypot_blocked', ['field' => (string) $honeypot_field]);
            $fail_request('Spam detected.', 400, 'validation_honeypot');
        }

        $data['Email'] = sanitize_email($data['Email']);
        if (!is_email($data['Email'])) {
            error_log('Form: invalid email ' . $data['Email']);
            $debug_log('invalid_email', ['email' => (string) $data['Email']]);
            $fail_request('Invalid email address.', 400, 'validation_invalid_email');
        }

        $validation_result = apply_filters('mzf_validate_data', true, $data);
        if (is_wp_error($validation_result)) {
            $fail_request($validation_result->get_error_message(), 400, 'validation_custom');
        } elseif ($validation_result === false) {
            $fail_request('Invalid form submission.', 400, 'validation_custom');
        }
        if (function_exists('mzf_validate_commercial_submission')) {
            $commercial_validation = mzf_validate_commercial_submission($data);
            if (is_wp_error($commercial_validation)) {
                $fail_request($commercial_validation->get_error_message(), 400, 'validation_commercial');
            }
        }
        if (function_exists('mzf_validate_volunteer_submission')) {
            $volunteer_validation = mzf_validate_volunteer_submission($data);
            if (is_wp_error($volunteer_validation)) {
                $fail_request($volunteer_validation->get_error_message(), 400, 'validation_volunteer');
            }
        }

        $page_id   = isset($data['PageId']) ? absint($data['PageId']) : 0;
        $site_name = get_bloginfo('name');
        $site_url  = home_url();

        $org_email_raw     = function_exists('get_field') ? (string) get_field('email', 'option') : '';
        $org_email_hdr     = sanitize_email($org_email_raw);
        $org_email_display = $org_email_raw ? antispambot($org_email_raw) : '';

        $org_phone      = function_exists('get_field') ? (string) get_field('phone', 'option') : '';
        $org_phone_href = preg_replace('/[^\d\+]/', '', $org_phone);

        $full_name = trim($data['FirstName'] . ' ' . $data['LastName']);
        $slug_for_labels = sanitize_key((string) ($data['FormSlug'] ?? ''));
        $label_company = 'Organization/Company';
        $label_interests = 'Interests';
        $label_item_type = trim((string) ($data['Service'] ?? '')) !== '' ? 'Service' : 'Item Type';
        $label_date = (
            trim((string) ($data['RentalDate'] ?? '')) !== ''
            || trim((string) ($data['Deadline'] ?? '')) !== ''
            || trim((string) ($data['Date'] ?? '')) !== ''
        ) ? 'Rental Date' : 'Date Needed';
        $label_duration = (trim((string) ($data['RentalDuration'] ?? '')) !== '' || trim((string) ($data['Days'] ?? '')) !== '')
            ? 'Rental Duration'
            : 'Duration';
        $label_location = (
            in_array($slug_for_labels, ['rent', 'buy'], true)
            || trim((string) ($data['ReceivingAddress'] ?? '')) !== ''
            || trim((string) ($data['ReceivingAddressDisplay'] ?? '')) !== ''
        ) ? 'Shipping Address' : 'Location';

        $body  = '';
        $body .= '<hr><h3 style="margin:1em 0 .5em 0;">Contact Details</h3>';
        $body .= '<p><strong>Name:</strong><br>' . $h($full_name) . '</p>';
        $body .= '<p><strong>Email:</strong><br><a href="mailto:' . esc_attr($data['Email']) . '" target="_blank">' . $h($data['Email']) . '</a></p>';
        if (!empty($data['Phone'])) {
            $tel = preg_replace('/[^\d\+]/', '', $data['Phone']);
            $body .= '<p><strong>Phone:</strong><br><a href="tel:' . esc_attr($tel) . '" target="_blank">' . $h($data['Phone']) . '</a></p>';
        }
        if (!empty($data['Company'])) $body .= '<p><strong>' . $h($label_company) . ':</strong><br>' . $h($data['Company']) . '</p>';

        $body .= '<hr><h3 style="margin:1em 0 .5em 0;">Request Details</h3>';

        if (!empty($data['ItemType']) && trim((string) ($data['Service'] ?? '')) === '') $body .= '<p><strong>' . $h($label_item_type) . ':</strong><br>' . $h((string) $data['ItemType']) . '</p>';
        if (!empty($data['ItemName'])) $body .= '<p><strong>Item:</strong><br>' . $h((string) $data['ItemName']) . '</p>';
        if (!empty($data['Quantity'])) $body .= '<p><strong>Quantity:</strong><br>' . $h((string) $data['Quantity']) . '</p>';
        $service_value = strtolower(trim((string) ($data['Service'] ?? '')));
        $allow_dimensions = ($slug_for_labels === 'print-quote' || $service_value === 'printing');
        if (!empty($data['Dimensions']) && $allow_dimensions) $body .= '<p><strong>Dimensions:</strong><br>' . $h((string) $data['Dimensions']) . '</p>';
        if (!empty($data['DateNeeded'])) $body .= '<p><strong>' . $h($label_date) . ':</strong><br>' . $h($human_date((string) $data['DateNeeded'])) . '</p>';
        if (!empty($data['Duration'])) $body .= '<p><strong>' . $h($label_duration) . ':</strong><br>' . $h($format_weeks_days((string) $data['Duration'])) . '</p>';
        if (!empty($data['LocationDisplay'])) {
            $addr_display = trim((string) $data['LocationDisplay']);
            $addr_display_label = preg_replace('/,\s*(US|USA|United States(?: of America)?)\s*$/i', '', $addr_display);
            $addr_display_label = trim((string) $addr_display_label);
            if ($addr_display_label === '') {
                $addr_display_label = $addr_display;
            }
            $place_id = trim((string) ($data['ReceivingPlaceID'] ?? ''));
            $receiving_lat = trim((string) ($data['ReceivingLatitude'] ?? ''));
            $receiving_lng = trim((string) ($data['ReceivingLongitude'] ?? ''));
            $base_lat = trim((string) ($data['Latitude'] ?? ''));
            $base_lng = trim((string) ($data['Longitude'] ?? ''));
            $coords = '';
            if ($receiving_lat !== '' && $receiving_lng !== '') {
                $coords = $receiving_lat . ',' . $receiving_lng;
            } elseif ($base_lat !== '' && $base_lng !== '') {
                $coords = $base_lat . ',' . $base_lng;
            }
            $receiving_option = strtolower(trim((string) ($data['ReceivingOption'] ?? '')));
            $is_delivery = ($receiving_option === 'delivery' || $receiving_option === 'deliver');
            if ($is_delivery) {
                $destination = $coords !== '' ? $coords : $addr_display;
                $maps_url = 'https://www.google.com/maps/dir/?api=1&origin=' . rawurlencode('My Location')
                    . '&destination=' . rawurlencode($destination);
                if ($place_id !== '') {
                    $maps_url .= '&destination_place_id=' . rawurlencode($place_id);
                }
            } else {
                $query = $coords !== '' ? $coords : $addr_display;
                $maps_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query);
                if ($place_id !== '') {
                    $maps_url .= '&query_place_id=' . rawurlencode($place_id);
                }
            }
            $body .= '<p><strong>' . $h($label_location) . ':</strong><br><a href="' . esc_url($maps_url) . '" target="_blank" rel="noopener">' . $h($addr_display_label) . '</a></p>';
        }
        if (!empty($data['Interests'])) $body .= '<p><strong>' . $h($label_interests) . ':</strong><br>' . $h($maybe_join($data['Interests'])) . '</p>';

        if (!empty($data['FilesLink'])) {
            $sl = esc_url_raw((string)$data['FilesLink']);
            if ($sl) {
                $body .= '<p><strong>Files link:</strong><br>'
                    . '<a href="' . esc_url($sl) . '" target="_blank" rel="noopener noreferrer">'
                    . $h($sl)
                    . '</a></p>';
            }
        }

        if (!empty($data['Comments']))       $body .= '<p><strong>Comments</strong><br>' . nl2br($h($data['Comments'])) . '</p>';

        $format_acf_map_address = static function ($raw) {
            if (!$raw) return [null, null, null, null];
            if (is_string($raw)) {
                $t = trim($raw);
                if ($t !== '' && ($t[0] === '{' || $t[0] === '[')) {
                    $decoded = json_decode($raw, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $raw = $decoded;
                }
            }
            if (is_array($raw)) {
                $display  = '';
                $place_id = !empty($raw['place_id']) ? (string) $raw['place_id'] : null;
                $name     = !empty($raw['name']) ? (string) $raw['name'] : null;
                if (!empty($raw['address']))           $display = trim($raw['address']);
                elseif (!empty($raw['formatted_address'])) $display = trim($raw['formatted_address']);
                $query = (!empty($raw['lat']) && !empty($raw['lng'])) ? ((float)$raw['lat'] . ',' . (float)$raw['lng']) : ($display ?: null);
                return [$display ?: null, $query, $place_id, $name];
            }
            $display = trim((string) $raw);
            return [$display ?: null, $display ?: null, null, null];
        };

        $org_addr_raw = function_exists('get_field') ? get_field('address', 'option') : null;
        [$org_addr_display, $org_addr_query, $org_place_id, $org_place_name] = $format_acf_map_address($org_addr_raw);
        $org_has_maps_meta = false;
        if (is_array($org_addr_raw)) {
            $raw_place_id = trim((string) ($org_addr_raw['place_id'] ?? ''));
            $raw_lat = trim((string) ($org_addr_raw['lat'] ?? ''));
            $raw_lng = trim((string) ($org_addr_raw['lng'] ?? ''));
            $org_has_maps_meta = ($raw_place_id !== '' || ($raw_lat !== '' && $raw_lng !== ''));
        }

        $best_query = null;
        if (!empty($org_place_name))               $best_query = $org_place_name;
        elseif (!empty($org_addr_query) && preg_match('/^-?\d+(\.\d+)?,-?\d+(\.\d+)?$/', $org_addr_query)) $best_query = $org_addr_query;
        elseif (!empty($org_addr_display))         $best_query = $org_addr_display;

        if ($org_has_maps_meta && $org_place_id && $best_query) {
            $org_maps_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($best_query) . '&query_place_id=' . rawurlencode($org_place_id);
        } elseif ($org_has_maps_meta && $org_place_id) {
            $org_maps_url = 'https://www.google.com/maps/place/?q=place_id:' . rawurlencode($org_place_id);
        } elseif ($org_has_maps_meta && !empty($org_addr_query) && preg_match('/^-?\d+(\.\d+)?,-?\d+(\.\d+)?$/', (string) $org_addr_query)) {
            $org_maps_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($org_addr_query);
        } else {
            $org_maps_url = null;
        }

        $marketing_sync = ['ok' => false, 'provider' => 'none'];
        $newsletter_opt_in = mzf_truthy($data['NewsletterSignup'] ?? '');
        $newsletter_opt_in = (bool) apply_filters('mzf_newsletter_opt_in', $newsletter_opt_in, $data);
        if ($newsletter_opt_in) {
            $lead_tags = [];
            if (!empty($data['PageId'])) {
                $lead_page_slug = (string) get_post_field('post_name', (int) $data['PageId']);
                if ($lead_page_slug !== '') {
                    $lead_tags[] = 'website_page_' . sanitize_title($lead_page_slug);
                }
            }
            $form_slug_tag = sanitize_title((string) ($data['FormSlug'] ?? ''));
            if ($form_slug_tag !== '') {
                $lead_tags[] = 'website_form_' . $form_slug_tag;
            }
            $data['LeadTags'] = array_values(array_unique(array_filter($lead_tags)));
            $marketing_sync = mzf_sync_marketing_contact($data);
        }

        $admin_footer_html = function_exists('mzf_build_footer_html')
            ? mzf_build_footer_html('admin', $org_addr_display, $org_maps_url, $org_phone, $org_phone_href, $org_email_display, $site_url, (string) $domain)
            : '<hr><p><small>'
                . ($org_addr_display && $org_maps_url ? '<a href="' . esc_url($org_maps_url) . '" target="_blank" rel="noopener">' . esc_html($org_addr_display) . '</a><br>' : '')
                . ($org_phone ? '<a href="tel:' . esc_attr($org_phone_href) . '" target="_blank">' . esc_html($org_phone) . '</a>' : '')
                . '<br>'
                . ($org_email_display ? '<a href="mailto:' . esc_attr($org_email_display) . '" target="_blank">' . esc_html($org_email_display) . '</a><br>' : '')
                . '<a href="' . esc_url($site_url) . '" target="_blank">' . $site_url . '</a></small></p>';
        $body .= $admin_footer_html;

        $admin_render_context = [
            'footer_html' => $admin_footer_html,
            'domain' => (string) $domain,
            'marketing_sync' => $marketing_sync,
        ];

        $body = mzf_render_admin_body($body, $data, $admin_render_context);

        $page_id = isset($_POST['PageId']) ? absint($_POST['PageId']) : ($page_id ?? 0);
        $slug = sanitize_key((string) ($data['FormSlug'] ?? ''));
        $is_quote = in_array($slug, ['quote', 'upload-files', 'vehicle-wraps', 'wall-graphics', 'banner-printing', 'print-quote'], true);

        $first_name = isset($data['FirstName']) ? sanitize_text_field($data['FirstName']) : '';
        $last_name  = isset($data['LastName'])  ? sanitize_text_field($data['LastName'])  : '';
        $full_name  = trim("$first_name $last_name") ?: 'Unknown Sender';
        $company    = !empty($data['Company']) ? sanitize_text_field($data['Company']) : '';

        $prefix = 'New request from ';

        $core    = $prefix . $full_name . ($company ? ' at ' . $company : '');
        $core    = mzf_apply_subject_templates($core, $data, ['domain' => (string) $site_domain, 'page_slug' => (string) $slug]);
        $subject = apply_filters('mzf_subject', $core, $data, (int)($data['PageId'] ?? 0), $slug, ($data['ItemType'] ?? ''), $is_quote);
        $default_subject = $subject;
        $interest_values = [];
        $interest_source = $data['Interests'] ?? [];
        if (is_array($interest_source)) {
            foreach ($interest_source as $interest_item) {
                $interest_item = trim((string) $interest_item);
                if ($interest_item !== '') {
                    $interest_values[] = $interest_item;
                }
            }
        } else {
            $interest_text = trim((string) $interest_source);
            if ($interest_text !== '') {
                foreach (preg_split('/\s*,\s*/', $interest_text) as $interest_item) {
                    $interest_item = trim((string) $interest_item);
                    if ($interest_item !== '') {
                        $interest_values[] = $interest_item;
                    }
                }
            }
        }
        $interest_values = array_values(array_unique($interest_values));
        $interest_count = count($interest_values);
        $interest_subject = '';
        if ($interest_count === 1) {
            $interest_norm = strtolower($interest_values[0]);
            if ($interest_norm === 'wide format printing') {
                $interest_subject = 'print';
            } elseif ($interest_norm === 'signs & graphics' || $interest_norm === 'signs and graphics') {
                $interest_subject = 'sign';
            }
        }
        $service_subject = strtolower(trim((string) ($data['Service'] ?? '')));
        $is_upload_files = ($slug === 'upload-files');
        $print_subject_text = $is_upload_files ? 'New print request from ' : 'New print quote request from ';
        $sign_subject_text = $is_upload_files ? 'New sign request from ' : 'New sign quote request from ';
        if ($interest_count > 1) {
            $subject = $default_subject;
        } elseif ($interest_subject === 'print') {
            $subject = $print_subject_text . $full_name . ($company ? ' at ' . $company : '');
        } elseif ($interest_subject === 'sign') {
            $subject = $sign_subject_text . $full_name . ($company ? ' at ' . $company : '');
        } elseif ($service_subject === 'printing') {
            $subject = $print_subject_text . $full_name . ($company ? ' at ' . $company : '');
        } elseif ($service_subject === 'signs') {
            $subject = $sign_subject_text . $full_name . ($company ? ' at ' . $company : '');
        }
        $store_subject = trim((string) ($data['Store'] ?? ''));
        if ($store_subject !== '') {
            $store_segment = ' for ' . $store_subject;
            if (stripos($subject, $store_segment) === false) {
                $subject = trim($subject) . $store_segment;
            }
        }
        if ($site_domain) {
            $subject = preg_replace('/\s*[\(\[]' . preg_quote($site_domain, '/') . '[\)\]]\s*/i', ' ', $subject);
            $subject = trim(preg_replace('/\s{2,}/', ' ', $subject));
            $subject .= ' [' . $site_domain . ']';
        }

        $__meza_set_html = function () {
            return 'text/html; charset=UTF-8';
        };
        add_filter('wp_mail_content_type', $__meza_set_html);

        $from_email    = 'wordpress@' . $domain;
        $admin_headers = ['From: ' . $site_name . ' <' . $from_email . '>', 'Reply-To: ' . ($full_name ?: 'Form Submitter') . ' <' . $data['Email'] . '>'];
        $user_from     = $org_email_hdr ?: $from_email;
        $user_headers  = ['From: ' . $site_name . ' <' . $user_from . '>', 'Reply-To: ' . $site_name . ' <' . $user_from . '>'];
        $attachments    = [];
        $attached_names = [];
        $attached_meta  = [];
        $file_errors    = [];

        // Standardized recipients: non-live tester, then form config recipients, then option/admin fallback.
        $meza_admin = sanitize_email((string) mzf_get('admin_bcc_email', 'info@meza.design'));
        if (!is_email($meza_admin)) {
            $meza_admin = 'info@meza.design';
        }
        $form_cfg = mzf_resolve_form_config($data);

        $to = [];
        $is_live_env = in_array((string) $env, ['production', 'qa'], true);
        if (!$is_live_env && is_user_logged_in()) {
            $u = wp_get_current_user();
            if (!empty($u->user_email) && is_email($u->user_email)) {
                $to[] = $u->user_email;
            }
        }
        if (empty($to) && function_exists('mzf_recipients_from_form_config')) {
            $to = mzf_recipients_from_form_config($data, $form_cfg);
        }
        if (empty($to) && !empty($org_email_hdr) && is_email($org_email_hdr)) {
            $to[] = $org_email_hdr;
        }
        if (empty($to)) {
            $admin_fallback = sanitize_email((string) get_option('admin_email'));
            if ($admin_fallback && is_email($admin_fallback)) {
                $to[] = $admin_fallback;
            }
        }
        if (empty($to)) {
            $to[] = $meza_admin;
        }

        // finalize + guarantee
        $to = array_values(array_unique(array_filter(apply_filters('mzf_recipients', $to, $data, $env), 'is_email')));
        if (empty($to)) {
            error_log('Mail: no admin recipients resolved');
            $debug_log('no_recipients', ['form_slug' => (string) ($data['FormSlug'] ?? ''), 'env' => (string) $env]);
            $fail_request('No admin recipients configured.', 500, 'error_no_recipients', [
                'name' => (string) $full_name,
                'email' => (string) ($data['Email'] ?? ''),
                'admin_ok' => false,
                'user_ok' => false,
                'subject' => (string) $subject,
                'recipients' => [],
                'attachments' => (array) $attached_meta,
            ]);
        }

        $admin_headers = (array) apply_filters('mzf_admin_headers', $admin_headers, $data, $env, $to);
        $user_headers  = (array) apply_filters('mzf_user_headers', $user_headers, $data, $env, $to);

        // Core default BCC support (replaces DS adapter BCC bridge behavior).
        $default_bcc = sanitize_email((string) mzf_get('admin_bcc_email', ''));
        $bcc_allowed_envs = ['production', 'qa'];
        if (in_array((string) $env, $bcc_allowed_envs, true) && $default_bcc && is_email($default_bcc)) {
            $has_bcc = false;
            foreach ((array) $admin_headers as $hdr) {
                if (stripos((string) $hdr, 'bcc:') === 0) {
                    $has_bcc = true;
                    break;
                }
            }
            if (!$has_bcc) {
                $admin_headers[] = 'Bcc: ' . $default_bcc;
            }
        }

        if (!empty($_FILES) && is_array($_FILES)) {

            $allowed_mimes = [
                // images
                'jpg|jpeg|jpe' => 'image/jpeg',
                'gif'          => 'image/gif',
                'png'          => 'image/png',
                'svg'          => 'image/svg+xml',

                // docs
                'pdf'          => 'application/pdf',
                'doc'          => 'application/msword',
                'docx'         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls'          => 'application/vnd.ms-excel',
                'xlsx'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'txt'          => 'text/plain',
                'csv'          => 'text/csv',

                // arch/alt types you likely need
                'zip'          => 'application/zip',
                // some user agents send these:
                'pdf_alt'      => 'application/x-pdf',
                'zip_alt'      => 'application/x-zip-compressed',
            ];

            // === Dynamic per-file limit based on server realities ===
            // Uses the smallest of upload_max_filesize, post_max_size, and memory_limit.
            $to_bytes = static function ($val) {
                if ($val === false || $val === null || $val === '') return PHP_INT_MAX;
                // WP helper converts "64M", "1G", etc → bytes
                if (function_exists('wp_convert_hr_to_bytes')) {
                    $b = wp_convert_hr_to_bytes($val);
                    // guard: negative or 0 means "no limit" → treat as very large
                    return ($b && $b > 0) ? $b : PHP_INT_MAX;
                }
                // Fallback if WP helper unavailable
                $val = trim((string) $val);
                $last = strtolower(substr($val, -1));
                $num  = (int) $val;
                switch ($last) {
                    case 'g':
                        $num *= 1024;
                    case 'm':
                        $num *= 1024;
                    case 'k':
                        $num *= 1024;
                }
                return $num > 0 ? $num : PHP_INT_MAX;
            };

            $php_upload = $to_bytes(ini_get('upload_max_filesize'));
            $php_post   = $to_bytes(ini_get('post_max_size'));
            $php_mem    = $to_bytes(ini_get('memory_limit'));

            // Hard ceiling from PHP:
            $php_hard_cap = min($php_upload, $php_post, $php_mem);

            // Optional safety headroom (e.g., 95%) to avoid edge truncation:
            $headroom = 0.95;
            $computed_max = (int) floor($php_hard_cap * $headroom);

            // Optional project UI cap (e.g., you advertise 25MB in the UI):
            $ui_cap_bytes = 25 * 1024 * 1024; // set to 0 to disable UI cap

            // Final limit = min(server cap with headroom, UI cap if set)
            $max_bytes = $ui_cap_bytes > 0 ? min($computed_max, $ui_cap_bytes) : $computed_max;

            // Allow theme/plugins to override:
            $max_bytes = (int) apply_filters('mzf_max_file_bytes', $max_bytes);

            // For messages:
            $max_hr = function_exists('size_format') ? size_format($max_bytes) : sprintf('%.2f MB', $max_bytes / 1048576);

            error_log('Mailer max_bytes=' . $max_bytes . ' (' . $max_hr . ')');

            require_once ABSPATH . 'wp-admin/includes/file.php';

            foreach ($_FILES as $field_name => $file) {
                $field_name = (string) $field_name;
                $is_photo_id_field = ($field_name === 'PhotoID');
                $field_allowed_mimes = $is_photo_id_field
                    ? [
                        'jpg|jpeg|jpe' => 'image/jpeg',
                        'png'          => 'image/png',
                        'pdf'          => 'application/pdf',
                        'pdf_alt'      => 'application/x-pdf',
                    ]
                    : $allowed_mimes;
                $files = [];
                if (is_array($file['name'])) {
                    $count = count($file['name']);
                    for ($i = 0; $i < $count; $i++) {
                        if (empty($file['name'][$i]) || (int)$file['size'][$i] <= 0) continue;
                        $files[] = [
                            'name'     => $file['name'][$i],
                            'type'     => $file['type'][$i],
                            'tmp_name' => $file['tmp_name'][$i],
                            'error'    => $file['error'][$i],
                            'size'     => $file['size'][$i],
                        ];
                    }
                } else {
                    if (!empty($file['name']) && (int)$file['size'] > 0) $files[] = $file;
                }
                if ($is_photo_id_field && count($files) > 1) {
                    $file_errors[] = 'Photo ID: Please upload exactly one file (JPG, JPEG, PNG, or PDF).';
                    continue;
                }

                foreach ($files as $single) {
                    $name = $single['name'] ?? 'file';
                    $size = (int)($single['size'] ?? 0);

                    if ((int)$single['size'] > $max_bytes) {
                        $file_errors[] = sprintf(
                            '%s exceeds the maximum allowed size of %s.',
                            esc_html($single['name'] ?? 'file'),
                            esc_html($max_hr)
                        );
                        continue;
                    }

                    $moved = wp_handle_upload($single, ['test_form' => false, 'mimes' => $field_allowed_mimes]);

                    if (is_array($moved) && !empty($moved['file'])) {
                        $attachments[]    = $moved['file'];
                        $safe_name = sanitize_file_name($name);
                        $attached_names[] = $safe_name;
                        $attached_meta[] = [
                            'name' => $safe_name,
                            'url'  => !empty($moved['url']) ? esc_url_raw((string) $moved['url']) : '',
                        ];
                    } else {
                        // surface WP/host message if present
                        $err_text = '';
                        if (is_array($moved) && !empty($moved['error'])) {
                            $err_text = (string) $moved['error'];
                        } elseif (is_string($moved) && $moved !== '') {
                            $err_text = $moved;
                        } else {
                            $err_text = 'Upload failed (type not allowed or blocked by server).';
                        }
                        error_log('Upload failed: ' . $name . ' | ' . $err_text);
                        $file_errors[] = sprintf('%s: %s', esc_html($name), esc_html($err_text));
                    }
                }
            }
        }

        // If any file failed → return a 400 with details (so UI shows it immediately)
        if ($file_errors) {
            $debug_log('upload_errors', ['errors' => $file_errors]);
            $fail_request(implode('<br>', $file_errors), 400, 'error_upload', [
                'name' => (string) $full_name,
                'email' => (string) ($data['Email'] ?? ''),
                'admin_ok' => false,
                'user_ok' => false,
                'subject' => (string) $subject,
                'attachments' => (array) $attached_meta,
                'error_message' => implode(' | ', array_map('wp_strip_all_tags', $file_errors)),
            ]);
        }

        if (!empty($attached_meta)) {
            $data['UploadedFiles'] = $attached_meta;
            if (function_exists('mzf_render_admin_body')) {
                $body = mzf_render_admin_body($body, $data, $admin_render_context);
            }
            $file_links = [];
            $files_block_label = (sanitize_key((string) ($data['FormSlug'] ?? '')) === 'volunteer') ? 'Photo ID' : 'Files';
            foreach ($attached_meta as $meta) {
                $name = trim((string) ($meta['name'] ?? ''));
                $url = trim((string) ($meta['url'] ?? ''));
                if ($name !== '' && $url !== '') {
                    $file_links[] = '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($name) . '</a>';
                }
            }
            if (!empty($file_links) && !preg_match('/<strong>(Files|Photo ID):<\/strong>/i', (string) $body)) {
                $files_block = '<p><strong>' . esc_html($files_block_label) . ':</strong><br>' . implode('<br>', $file_links) . '</p>';
                if (preg_match('/<p><strong>Files link:<\/strong><br>.*?<\/p>/is', (string) $body)) {
                    $body = preg_replace('/(<p><strong>Files link:<\/strong><br>.*?<\/p>)/is', $files_block . '$1', (string) $body, 1);
                } elseif (preg_match('/<hr[^>]*><p><small>/i', (string) $body)) {
                    $body = preg_replace('/<hr[^>]*><p><small>/i', $files_block . '$0', (string) $body, 1);
                } else {
                    $body .= $files_block;
                }
            }
        }

        $body = apply_filters('mzf_email_body', $body, $data);

        $successMsgRaw = function_exists('mzf_form_config_value')
            ? mzf_form_config_value($form_cfg, ['messages.success', 'message_success'], 'Your submission was sent successfully.')
            : ((isset($form_cfg['message_success']) && $form_cfg['message_success'] !== '') ? $form_cfg['message_success'] : 'Your submission was sent successfully.');
        $errorMsgRaw = function_exists('mzf_form_config_value')
            ? mzf_form_config_value($form_cfg, ['messages.error', 'message_error'], 'Your submission failed to send. Please try again.')
            : ((isset($form_cfg['message_error']) && $form_cfg['message_error'] !== '') ? $form_cfg['message_error'] : 'Your submission failed to send. Please try again.');
        $successMsg = wp_kses_post((string) $successMsgRaw);
        $errorMsg   = wp_kses_post((string) $errorMsgRaw);
        $successMsg = (string) apply_filters('mzf_success_message', $successMsg, $data, $form_cfg);
        $errorMsg   = (string) apply_filters('mzf_error_message', $errorMsg, $data, $form_cfg);

        $footer_html = function_exists('mzf_build_footer_html')
            ? mzf_build_footer_html('user', $org_addr_display, $org_maps_url, $org_phone, $org_phone_href, $org_email_display, $site_url, (string) $domain)
            : '<hr><p><small>'
                . ($org_addr_display && $org_maps_url ? '<a href="' . esc_url($org_maps_url) . '" target="_blank" rel="noopener">' . esc_html($org_addr_display) . '</a><br>' : '')
                . ($org_phone ? '<a href="tel:' . esc_attr($org_phone_href) . '" target="_blank">' . esc_html($org_phone) . '</a>' : '')
                . '<br>'
                . ($org_email_display ? '<a href="mailto:' . esc_attr($org_email_display) . '" target="_blank">' . esc_html($org_email_display) . '</a><br>' : '')
                . '<a href="' . esc_url($domain) . '" target="_blank">https://' . $domain . '</a></small></p>';

        $user_email = mz_build_user_email($data, $footer_html);

        $admin_ok = wp_mail($to, $subject, $body, $admin_headers, $attachments);
        $user_ok  = wp_mail($data['Email'], $user_email['subject'], $user_email['body'], $user_headers);

        do_action('mz_form_validated', [
            'first'    => (string) ($data['FirstName'] ?? ''),
            'last'     => (string) ($data['LastName'] ?? ''),
            'email'    => (string) ($data['Email'] ?? ''),
            'phone'    => (string) ($data['Phone'] ?? ''),
            'vocals'   => (string) ($data['Vocals'] ?? ''),
            'comments' => (string) ($data['Comments'] ?? ''),
            'pageId'   => (int) ($data['PageId'] ?? 0),
            'data'     => $data,
        ]);

        remove_filter('wp_mail_content_type', $__meza_set_html);

        $delivery_success = (bool) apply_filters('mzf_delivery_success', ($admin_ok && $user_ok), $admin_ok, $user_ok, $data);
        $partial_success = (bool) apply_filters('mzf_partial_success', $admin_ok, $admin_ok, $user_ok, $data);
        $delivery_status = $delivery_success ? 'success' : ($partial_success ? 'partial' : 'failed');
        $submission_log_id = 0;
        $submission_log_id = $log_submission_attempt([
            'form_slug' => (string) ($data['FormSlug'] ?? ''),
            'page_id' => (int) ($data['PageId'] ?? 0),
            'name' => (string) $full_name,
            'email' => (string) ($data['Email'] ?? ''),
            'delivery_status' => (string) $delivery_status,
            'admin_ok' => (bool) $admin_ok,
            'user_ok' => (bool) $user_ok,
            'subject' => (string) $subject,
            'env' => (string) $env,
            'crm_platform' => function_exists('mzf_crm_platform') ? (string) mzf_crm_platform() : '',
            'newsletter_opt_in' => !empty($newsletter_opt_in),
            'marketing_sync' => is_array($marketing_sync) ? $marketing_sync : [],
            'payload' => (array) $data,
            'submitted' => $submitted_payload,
            'recipients' => (array) $to,
            'attachments' => (array) $attached_meta,
        ]);
        $debug_payload = [
            'env' => (string) $env,
            'form_slug' => (string) ($data['FormSlug'] ?? ''),
            'page_id' => (int) ($data['PageId'] ?? 0),
            'to' => array_values((array) $to),
            'subject' => (string) $subject,
            'admin_ok' => (bool) $admin_ok,
            'user_ok' => (bool) $user_ok,
            'required_fields' => array_values((array) $required_fields),
            'unknown_fields_rejected' => false,
            'strict_mode' => (bool) $strict_mode,
            'marketing_sync' => is_array($marketing_sync) ? $marketing_sync : [],
            'submission_log_id' => $submission_log_id,
        ];
        $debug_log('delivery_result', $debug_payload);

        if ($delivery_success) {
            $payload = ['message_success' => $successMsg];
            if (is_array($marketing_sync) && !empty($marketing_sync['dry_run'])) {
                $payload['marketing_sync'] = $marketing_sync;
            }
            if ($debug_response_enabled) {
                $payload['debug'] = $debug_payload;
            }
            wp_send_json_success($payload, 200);
        }
        if (!$admin_ok) error_log('Mail: admin send failed');
        if (!$user_ok)  error_log('Mail: user confirmation failed');

        if ($partial_success) {
            $payload = ['message_success' => $successMsg];
            if (is_array($marketing_sync) && !empty($marketing_sync['dry_run'])) {
                $payload['marketing_sync'] = $marketing_sync;
            }
            if ($debug_response_enabled) {
                $payload['debug'] = $debug_payload;
            }
            wp_send_json_success($payload, 200);
        }

        $error_payload = ['message' => $errorMsg];
        if ($debug_response_enabled) {
            $error_payload['debug'] = $debug_payload;
        }
        wp_send_json_error($error_payload, 400);
    }
endif;

if (!function_exists('mzf_render_marketing_dry_run_alert_script')) {
    function mzf_render_marketing_dry_run_alert_script(): void
    {
        if (is_admin()) {
            return;
        }
        if (!function_exists('mzf_marketing_dry_run_enabled') || !mzf_marketing_dry_run_enabled()) {
            return;
        }
        ?>
        <script>
            (function () {
                if (window.__mzfDryRunAlertInit) return;
                if (typeof window.fetch !== 'function') return;
                window.__mzfDryRunAlertInit = true;

                const originalFetch = window.fetch.bind(window);

                function isSendFormRequest(input) {
                    try {
                        const url = typeof input === 'string'
                            ? input
                            : (input && typeof input.url === 'string' ? input.url : '');
                        if (!url) return false;
                        return /admin-ajax\.php/i.test(url) && /(?:\?|&)action=send_form_data(?:&|$)/.test(url);
                    } catch (e) {
                        return false;
                    }
                }

                function buildDryRunMessage(sync) {
                    const provider = (sync && (sync.label || sync.provider)) || 'provider';
                    const requests = sync && Array.isArray(sync.dry_run_requests) ? sync.dry_run_requests : [];
                    const chunks = ['Would successfully send to ' + provider + ' on qa and production environments!'];

                    if (!requests.length) {
                        chunks.push('No payload captured.');
                        return chunks.join('\n\n');
                    }

                    function nonEmpty(value) {
                        return typeof value !== 'undefined' && value !== null && String(value).trim() !== '';
                    }

                    function addField(lines, label, value) {
                        if (!nonEmpty(value)) return;
                        lines.push(label + ':\n' + String(value));
                    }

                    function collectPayloadFields(payload, tagCounter) {
                        const lines = [];
                        if (!payload || typeof payload !== 'object') {
                            return { lines: lines, tagCounter: tagCounter };
                        }

                        addField(lines, 'Email', payload.email || payload.email_address);
                        addField(lines, 'First Name', payload.first_name || (payload.merge_fields && payload.merge_fields.FNAME));
                        addField(lines, 'Last Name', payload.last_name || (payload.merge_fields && payload.merge_fields.LNAME));
                        addField(lines, 'Company', payload.company_name || (payload.merge_fields && payload.merge_fields.COMPANY));
                        addField(lines, 'Phone', payload.phone_number || (payload.phone_numbers && payload.phone_numbers[0] && payload.phone_numbers[0].phone_number) || (payload.merge_fields && payload.merge_fields.PHONE));
                        addField(lines, 'Zip', (payload.street_address && payload.street_address.postal_code) || (payload.street_addresses && payload.street_addresses[0] && payload.street_addresses[0].postal_code) || (payload.merge_fields && payload.merge_fields.ZIP));

                        if (Array.isArray(payload.tags)) {
                            payload.tags.forEach(function (tag) {
                                if (tag && tag.status === 'active') {
                                    addField(lines, 'Tag' + tagCounter, tag.name);
                                    tagCounter += 1;
                                }
                            });
                        }
                        if (Array.isArray(payload.tag_names)) {
                            payload.tag_names.forEach(function (tagName) {
                                addField(lines, 'Tag' + tagCounter, tagName);
                                tagCounter += 1;
                            });
                        }
                        return { lines: lines, tagCounter: tagCounter };
                    }

                    let allFields = [];
                    let tagCounter = 1;
                    requests.forEach(function (req) {
                        if (req && typeof req.payload !== 'undefined') {
                            const result = collectPayloadFields(req.payload, tagCounter);
                            tagCounter = result.tagCounter;
                            allFields = allFields.concat(result.lines);
                        }
                    });

                    if (allFields.length) {
                        const unique = [];
                        const seen = new Set();
                        allFields.forEach(function (line) {
                            if (!seen.has(line)) {
                                seen.add(line);
                                unique.push(line);
                            }
                        });
                        chunks.push(unique.join('\n\n'));
                    } else {
                        chunks.push('No non-technical fields.');
                    }

                    return chunks.join('\n\n');
                }

                window.fetch = function (input, init) {
                    return originalFetch(input, init).then(function (response) {
                        if (!isSendFormRequest(input)) {
                            return response;
                        }

                        response.clone().json().then(function (json) {
                            const sync = json && json.data && json.data.marketing_sync ? json.data.marketing_sync : null;
                            if (sync && sync.dry_run) {
                                window.alert(buildDryRunMessage(sync));
                            }
                        }).catch(function () {});

                        return response;
                    });
                };
            })();
        </script>
        <?php
    }
}
add_action('wp_footer', 'mzf_render_marketing_dry_run_alert_script', 100);
