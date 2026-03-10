<?php

add_action('wp_ajax_nopriv_send_form_data', 'send_form_data');
add_action('wp_ajax_send_form_data',        'send_form_data');

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
                wp_send_json_error(['message' => 'reCAPTCHA token missing.'], 400);
            }

            $recaptcha_secret_key = defined('GRECAPTCHA_SECRET_KEY') ? GRECAPTCHA_SECRET_KEY : '';
            if ($recaptcha_secret_key === '') {
                error_log('reCAPTCHA: secret key missing');
                wp_send_json_error(['message' => 'Server configuration error (reCAPTCHA).'], 500);
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
                wp_send_json_error(['message' => 'reCAPTCHA HTTP error.'], 400);
            }

            $result = json_decode(wp_remote_retrieve_body($resp), true);
            $score  = isset($result['score']) ? (float) $result['score'] : null;

            if (empty($result['success']) || $score < 0.5) {
                error_log('reCAPTCHA: failed, score=' . ($score ?? 'N/A'));
                wp_send_json_error(['message' => 'Failed bot check.'], 400);
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
        $data = [];
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
            wp_send_json_error(['message' => 'Legacy fields are not allowed. Use canonical keys: ' . implode(', ', $pairs)], 400);
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
                wp_send_json_error(['message' => 'Unknown fields are not allowed: ' . implode(', ', $unknown)], 400);
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
            $data['Phone'] = trim((string) ($data['ContactPhone'] ?? ''));
        }
        if (trim((string) ($data['Company'] ?? '')) === '') {
            $data['Company'] = trim((string) ($data['Organization'] ?? ''));
        }
        if (empty($data['Interest']) && !empty($data['Interests'])) {
            $data['Interest'] = $data['Interests'];
        }
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
            wp_send_json_error(['message' => 'Unsupported FormSlug: ' . (string) $data['FormSlug']], 400);
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
            wp_send_json_error(['message' => 'Legacy FormSlug alias is not allowed. Use: ' . $legacy_slug_target], 400);
        }
        if ($strict_mode) {
            if ($raw_slug === '') {
                wp_send_json_error(['message' => 'Missing required field: FormSlug'], 400);
            }
            if ($data['FormSlug'] !== $raw_slug) {
                wp_send_json_error(['message' => 'Non-canonical FormSlug provided. Use: ' . $data['FormSlug']], 400);
            }
        }

        if ($strict_mode) {
            foreach (['Interest', 'Reason'] as $array_key) {
                if (!array_key_exists($array_key, $src)) {
                    continue;
                }
                if (!is_array($src[$array_key])) {
                    wp_send_json_error(['message' => $array_key . ' must be submitted as an array in strict mode.'], 400);
                }
            }
        }

        $reason_source = $data['Reason'] ?? ($_POST['Reason'] ?? '');
        $interest_source = $data['Interest'] ?? ($_POST['Interest'] ?? '');
        $data['ReasonRaw'] = $reason_source;
        $data['InterestRaw'] = $interest_source;
        $data['ReasonNorm'] = function_exists('mzf_normalize_terms')
            ? mzf_normalize_terms($reason_source)
            : [];
        $data['InterestNorm'] = function_exists('mzf_normalize_terms')
            ? mzf_normalize_terms($interest_source)
            : [];

        $required_fields = apply_filters('mzf_required_fields', ['FirstName', 'LastName', 'Email'], $data);
        $required_fields = mzf_apply_required_rules((array) $required_fields, $data);
        foreach ($required_fields as $rf) {
            if (empty($data[$rf])) {
                error_log("Form: missing required field {$rf}");
                wp_send_json_error(['message' => "Missing required field: {$rf}"], 400);
            }
        }

        $default_honeypot = 'Website';
        $honeypot_field = apply_filters('mzf_honeypot_field', $default_honeypot, $data);
        if (!empty($honeypot_field) && !empty($data[$honeypot_field])) {
            error_log("Form: honeypot tripped");
            $debug_log('honeypot_blocked', ['field' => (string) $honeypot_field]);
            wp_send_json_error(['message' => 'Spam detected.'], 400);
        }

        $data['Email'] = sanitize_email($data['Email']);
        if (!is_email($data['Email'])) {
            error_log('Form: invalid email ' . $data['Email']);
            $debug_log('invalid_email', ['email' => (string) $data['Email']]);
            wp_send_json_error(['message' => 'Invalid email address.'], 400);
        }

        $validation_result = apply_filters('mzf_validate_data', true, $data);
        if (is_wp_error($validation_result)) {
            wp_send_json_error(['message' => $validation_result->get_error_message()], 400);
        } elseif ($validation_result === false) {
            wp_send_json_error(['message' => 'Invalid form submission.'], 400);
        }
        if (function_exists('mzf_validate_commercial_submission')) {
            $commercial_validation = mzf_validate_commercial_submission($data);
            if (is_wp_error($commercial_validation)) {
                wp_send_json_error(['message' => $commercial_validation->get_error_message()], 400);
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
        $label_company = trim((string) ($data['Organization'] ?? '')) !== '' ? 'Company/Organization' : 'Company';
        $label_interest = !empty($data['Interests']) ? 'Interests' : 'Interest';
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

        if (!empty($data['ItemType'])) $body .= '<p><strong>' . $h($label_item_type) . ':</strong><br>' . $h((string) $data['ItemType']) . '</p>';
        if (!empty($data['ItemName'])) $body .= '<p><strong>Item:</strong><br>' . $h((string) $data['ItemName']) . '</p>';
        if (!empty($data['Quantity'])) $body .= '<p><strong>Quantity:</strong><br>' . $h((string) $data['Quantity']) . '</p>';
        if (!empty($data['Dimensions'])) $body .= '<p><strong>Dimensions:</strong><br>' . $h((string) $data['Dimensions']) . '</p>';
        if (!empty($data['DateNeeded'])) $body .= '<p><strong>' . $h($label_date) . ':</strong><br>' . $h($human_date((string) $data['DateNeeded'])) . '</p>';
        if (!empty($data['Duration'])) $body .= '<p><strong>' . $h($label_duration) . ':</strong><br>' . $h($format_weeks_days((string) $data['Duration'])) . '</p>';
        if (!empty($data['LocationDisplay'])) $body .= '<p><strong>' . $h($label_location) . ':</strong><br>' . $h((string) $data['LocationDisplay']) . '</p>';
        if (!empty($data['Interest'])) $body .= '<p><strong>' . $h($label_interest) . ':</strong><br>' . $h($maybe_join($data['Interest'])) . '</p>';
        if (!empty($data['Reason'])) $body .= '<p><strong>Reason:</strong><br>' . $h($maybe_join($data['Reason'])) . '</p>';

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

        $best_query = null;
        if (!empty($org_place_name))               $best_query = $org_place_name;
        elseif (!empty($org_addr_query) && preg_match('/^-?\d+(\.\d+)?,-?\d+(\.\d+)?$/', $org_addr_query)) $best_query = $org_addr_query;
        elseif (!empty($org_addr_display))         $best_query = $org_addr_display;

        if ($org_place_id && $best_query) {
            $org_maps_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($best_query) . '&query_place_id=' . rawurlencode($org_place_id);
        } elseif ($org_place_id) {
            $org_maps_url = 'https://www.google.com/maps/place/?q=place_id:' . rawurlencode($org_place_id);
        } elseif (!empty($org_addr_query)) {
            $org_maps_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($org_addr_query);
        } else {
            $org_maps_url = null;
        }

        $admin_footer_html = '<hr><p><small>'
            . ($org_addr_display && $org_maps_url ? '<a href="' . esc_url($org_maps_url) . '" target="_blank" rel="noopener">' . esc_html($org_addr_display) . '</a><br>' : '')
            . ($org_phone ? '<a href="tel:' . esc_attr($org_phone_href) . '" target="_blank">' . esc_html($org_phone) . '</a>' : '')
            . '<br>'
            . ($org_email_display ? '<a href="mailto:' . esc_attr($org_email_display) . '" target="_blank">' . esc_html($org_email_display) . '</a><br>' : '')
            . '<a href="' . esc_url($site_url) . '" target="_blank">' . $site_url . '</a></small></p>';
        $body .= $admin_footer_html;

        $body = mzf_render_admin_body($body, $data, ['footer_html' => $admin_footer_html, 'domain' => (string) $domain]);

        $body = apply_filters('mzf_email_body', $body, $data);

        $page_id = isset($_POST['PageId']) ? absint($_POST['PageId']) : ($page_id ?? 0);
        $slug = sanitize_key((string) ($data['FormSlug'] ?? ''));
        $is_quote = in_array($slug, ['quote', 'vehicle-wraps', 'wall-graphics', 'banner-printing', 'print-quote'], true);

        $first_name = isset($data['FirstName']) ? sanitize_text_field($data['FirstName']) : '';
        $last_name  = isset($data['LastName'])  ? sanitize_text_field($data['LastName'])  : '';
        $full_name  = trim("$first_name $last_name") ?: 'Unknown Sender';
        $company    = !empty($data['Company']) ? sanitize_text_field($data['Company']) : '';

        $prefix = 'New request from ';

        $core    = $prefix . $full_name . ($company ? ' at ' . $company : '');
        $core    = mzf_apply_subject_templates($core, $data, ['domain' => (string) $domain, 'page_slug' => (string) $slug]);
        $subject = apply_filters('mzf_subject', $core, $data, (int)($data['PageId'] ?? 0), $slug, ($data['ItemType'] ?? ''), $is_quote);
        if ($domain) {
            $subject = preg_replace('/\s*\(' . preg_quote($domain, '/') . '\)\s*/i', ' ', $subject);
            $subject = trim(preg_replace('/\s{2,}/', ' ', $subject));
            $subject .= ' (' . $domain . ')';
        }

        $__meza_set_html = function () {
            return 'text/html; charset=UTF-8';
        };
        add_filter('wp_mail_content_type', $__meza_set_html);

        $from_email    = 'wordpress@' . $domain;
        $admin_headers = ['From: ' . $site_name . ' <' . $from_email . '>', 'Reply-To: ' . ($full_name ?: 'Form Submitter') . ' <' . $data['Email'] . '>'];
        $user_from     = $org_email_hdr ?: $from_email;
        $user_headers  = ['From: ' . $site_name . ' <' . $user_from . '>', 'Reply-To: ' . $site_name . ' <' . $user_from . '>'];

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
            if (function_exists('mzf_log_submission')) {
                mzf_log_submission([
                    'form_slug' => (string) ($data['FormSlug'] ?? ''),
                    'page_id' => (int) ($data['PageId'] ?? 0),
                    'name' => (string) $full_name,
                    'email' => (string) ($data['Email'] ?? ''),
                    'delivery_status' => 'error_no_recipients',
                    'admin_ok' => false,
                    'user_ok' => false,
                    'subject' => (string) $subject,
                    'env' => (string) $env,
                    'error_message' => 'No admin recipients configured.',
                    'payload' => (array) $data,
                    'submitted' => function_exists('mzf_prepare_submission_payload') ? mzf_prepare_submission_payload((array) $_POST) : (array) $_POST,
                    'recipients' => [],
                    'attachments' => (array) $attached_meta,
                ]);
            }
            wp_send_json_error(['message' => 'No admin recipients configured.'], 500);
        }

        $admin_headers = (array) apply_filters('mzf_admin_headers', $admin_headers, $data, $env, $to);
        $user_headers  = (array) apply_filters('mzf_user_headers', $user_headers, $data, $env, $to);

        // Core default BCC support (replaces DS adapter BCC bridge behavior).
        $default_bcc = sanitize_email((string) mzf_get('admin_bcc_email', ''));
        if ($default_bcc && is_email($default_bcc)) {
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

        $attachments    = [];
        $attached_names = [];
        $attached_meta  = [];
        $file_errors    = [];

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

            foreach ($_FILES as $file) {
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

                    $moved = wp_handle_upload($single, ['test_form' => false, 'mimes' => $allowed_mimes]);

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
            wp_send_json_error(['message' => implode('<br>', $file_errors)], 400);
        }

        if (!empty($attached_meta)) {
            $data['UploadedFiles'] = $attached_meta;
        }

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

        $footer_html = '<hr><p><small>'
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

        $newsletter_opt_in = mzf_truthy($data['NewsletterSignup'] ?? '');
        $newsletter_opt_in = (bool) apply_filters('mzf_newsletter_opt_in', $newsletter_opt_in, $data);
        if ($newsletter_opt_in) {
            $lead_tags = [];
            if (!empty($data['PageId'])) {
                $slug = (string) get_post_field('post_name', (int) $data['PageId']);
                if ($slug !== '') {
                    $lead_tags[] = sanitize_title($slug);
                }
            }
            $lead_tags[] = 'Website Lead';
            $data['LeadTags'] = array_values(array_unique(array_filter($lead_tags)));
            mzf_sync_marketing_contact($data);
        }

        remove_filter('wp_mail_content_type', $__meza_set_html);

        $delivery_success = (bool) apply_filters('mzf_delivery_success', ($admin_ok && $user_ok), $admin_ok, $user_ok, $data);
        $partial_success = (bool) apply_filters('mzf_partial_success', $admin_ok, $admin_ok, $user_ok, $data);
        $delivery_status = $delivery_success ? 'success' : ($partial_success ? 'partial' : 'failed');
        $submission_log_id = 0;
        if (function_exists('mzf_log_submission')) {
            $submission_log_id = (int) mzf_log_submission([
                'form_slug' => (string) ($data['FormSlug'] ?? ''),
                'page_id' => (int) ($data['PageId'] ?? 0),
                'name' => (string) $full_name,
                'email' => (string) ($data['Email'] ?? ''),
                'delivery_status' => (string) $delivery_status,
                'admin_ok' => (bool) $admin_ok,
                'user_ok' => (bool) $user_ok,
                'subject' => (string) $subject,
                'env' => (string) $env,
                'payload' => (array) $data,
                'submitted' => function_exists('mzf_prepare_submission_payload') ? mzf_prepare_submission_payload((array) $_POST) : (array) $_POST,
                'recipients' => (array) $to,
                'attachments' => (array) $attached_meta,
            ]);
        }
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
            'submission_log_id' => $submission_log_id,
        ];
        $debug_log('delivery_result', $debug_payload);

        if ($delivery_success) {
            $payload = ['message_success' => $successMsg];
            if ($debug_response_enabled) {
                $payload['debug'] = $debug_payload;
            }
            wp_send_json_success($payload, 200);
        }
        if (!$admin_ok) error_log('Mail: admin send failed');
        if (!$user_ok)  error_log('Mail: user confirmation failed');

        if ($partial_success) {
            $payload = ['message_success' => $successMsg];
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
