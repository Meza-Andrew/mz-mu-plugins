<?php

// Constant Contact Integration
// ===============================

// --- Config (set these in wp-config.php if you prefer) ---
if (!defined('CC_CLIENT_ID'))     define('CC_CLIENT_ID',     'YOUR_CLIENT_ID');
if (!defined('CC_CLIENT_SECRET')) define('CC_CLIENT_SECRET', 'YOUR_CLIENT_SECRET');

// Scopes: keep contact_data + offline_access (space-separated)
if (!defined('CC_SCOPES'))        define('CC_SCOPES',        'contact_data offline_access');

// Must match your CC app redirect URL EXACTLY (scheme/host/path)
if (!defined('CC_REDIRECT_URI'))  define('CC_REDIRECT_URI',  home_url('/cc-oauth-callback/'));

// Your audience/list
if (!defined('CC_LIST_ID'))       define('CC_LIST_ID',       'af35f4c0-c3e2-11ef-aa6e-fa163e9ef3b3');

// Optional: set a tag id if you want to auto-tag signups (else leave blank)
if (!defined('CC_TAG_ID'))        define('CC_TAG_ID',        ''); // e.g., '12345678-...'

if (!function_exists('cc_get_api_key')) {
    function cc_get_api_key(): string
    {
        if (defined('CC_API_KEY') && trim((string) CC_API_KEY) !== '') {
            return trim((string) CC_API_KEY);
        }

        $crm = [];
        if (function_exists('get_field')) {
            $acf_crm = get_field('crm', 'option');
            if (is_array($acf_crm)) {
                $crm = $acf_crm;
            } else {
                // Backward/fallback read for environments using reversed args.
                $acf_crm_fallback = get_field('option', 'crm');
                if (is_array($acf_crm_fallback)) {
                    $crm = $acf_crm_fallback;
                }
            }
        }
        if (empty($crm)) {
            $opt_crm = get_option('crm');
            if (is_array($opt_crm)) {
                $crm = $opt_crm;
            }
        }

        return trim((string) ($crm['api_constant-contact'] ?? $crm['api_constant_contact'] ?? ''));
    }
}

if (!function_exists('cc_get_auth_context')) {
    function cc_get_auth_context(): array
    {
        $oauth_token = cc_get_access_token();
        if (!empty($oauth_token)) {
            return ['token' => (string) $oauth_token, 'mode' => 'oauth'];
        }

        $api_key = cc_get_api_key();
        if ($api_key !== '') {
            return ['token' => $api_key, 'mode' => 'api_key'];
        }

        return [];
    }
}

if (!function_exists('cc_has_wp_config_keys')) {
    function cc_has_wp_config_keys(): bool
    {
        $client_id = defined('CC_CLIENT_ID') ? trim((string) CC_CLIENT_ID) : '';
        $client_secret = defined('CC_CLIENT_SECRET') ? trim((string) CC_CLIENT_SECRET) : '';
        $list_id = defined('CC_LIST_ID') ? trim((string) CC_LIST_ID) : '';

        if ($client_id === '' || $client_secret === '' || $list_id === '') return false;
        if (in_array($client_id, ['YOUR_CLIENT_ID', 'your_client_id'], true)) return false;
        if (in_array($client_secret, ['YOUR_CLIENT_SECRET', 'your_client_secret'], true)) return false;

        return true;
    }
}

// --- Admin page to connect OAuth ---
add_action('admin_menu', function () {
    // Only expose this menu when credentials are explicitly provided via wp-config constants.
    if (!cc_has_wp_config_keys()) return;

    add_submenu_page(
        'options-general.php',
        'Constant Contact',
        'Constant Contact',
        'manage_options',
        'cc-connect',
        function () {
            $params = [
                'client_id'     => CC_CLIENT_ID,
                'scope'         => CC_SCOPES, // space-separated
                'response_type' => 'code',
                'redirect_uri'  => CC_REDIRECT_URI, // byte-for-byte w/ app
                'state'         => wp_create_nonce('cc_oauth_state'),
            ];
            $auth_base = 'https://authz.constantcontact.com/oauth2/default/v1/authorize';
            $auth_url  = $auth_base . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

            echo '<div class="wrap"><h1>Constant Contact</h1>';
            echo '<p><a class="button button-primary" href="' . esc_url($auth_url) . '">Connect Constant Contact</a></p>';
            echo '</div>';
        }
    );
});

add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;
    if (!isset($_GET['page']) || $_GET['page'] !== 'cc-connect') return;

    $t = get_option('cc_tokens');
    if (empty($t['access_token'])) {
        echo '<div class="notice notice-error"><p>Constant Contact is not connected.</p></div>';
        return;
    }
    $expires_at = (int)($t['obtained_at'] ?? 0) + (int)($t['expires_in'] ?? 0);
    $mins = max(0, floor(($expires_at - time()) / 60));
    $rt_short = empty($t['refresh_token']) ? '⚠️ Missing refresh token' : 'OK';
    echo '<div class="notice notice-success"><p>Connected. Access token ~' . esc_html($mins) . ' min left. Refresh token: ' . esc_html($rt_short) . '.</p></div>';
});

// --- Pretty callback catcher: /cc-oauth-callback/ ---
add_action('template_redirect', function () {
    $uri = wp_parse_url(CC_REDIRECT_URI, PHP_URL_PATH);
    $req = isset($_SERVER['REQUEST_URI'])
        ? wp_parse_url((is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], PHP_URL_PATH)
        : '';
    if (rtrim($req, '/') === rtrim($uri, '/')) {
        cc_process_oauth_callback();
        exit;
    }
});

if (!function_exists('cc_process_oauth_callback')) {
    function cc_process_oauth_callback()
    {
        $code  = isset($_GET['code'])  ? sanitize_text_field($_GET['code'])  : '';
        $state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';

        if (!$code || !$state || !wp_verify_nonce($state, 'cc_oauth_state')) {
            wp_die('Invalid OAuth response.');
        }

        $token_url = 'https://authz.constantcontact.com/oauth2/default/v1/token';
        $headers = [
            'Authorization' => 'Basic ' . base64_encode(CC_CLIENT_ID . ':' . CC_CLIENT_SECRET),
            'Content-Type'  => 'application/x-www-form-urlencoded',
            'Accept'        => 'application/json',
        ];
        $body = [
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => CC_REDIRECT_URI,
        ];

        $resp = wp_remote_post($token_url, ['headers' => $headers, 'body' => $body, 'timeout' => 20]);

        if (!is_wp_error($resp)) {
            error_log('CC TOKEN HTTP ' . wp_remote_retrieve_response_code($resp));
            error_log('CC TOKEN BODY ' . wp_remote_retrieve_body($resp));
        }
        if (is_wp_error($resp)) wp_die('Token request failed.');

        $code_http = wp_remote_retrieve_response_code($resp);
        $json      = json_decode(wp_remote_retrieve_body($resp), true);

        if ($code_http < 200 || $code_http >= 300 || empty($json['access_token'])) {
            wp_die('Token exchange failed.');
        }

        $data = [
            'access_token'  => $json['access_token'],
            'refresh_token' => $json['refresh_token'] ?? '',
            'expires_in'    => (int)($json['expires_in'] ?? 0),
            'obtained_at'   => time(),
            'token_type'    => $json['token_type'] ?? 'Bearer',
            'scope'         => $json['scope']      ?? '',
        ];
        update_option('cc_tokens', $data, false);

        wp_safe_redirect(add_query_arg('cc_connected', '1', admin_url('options-general.php?page=cc-connect')));
        exit;
    }
}
if (!function_exists('cc_api_request')) {
    function cc_api_request($method, $url, $payload = null, $headers = [])
    {
        $auth = cc_get_auth_context();
        if (empty($auth['token'])) {
            return new WP_Error('cc_no_auth', 'No Constant Contact OAuth token or API key');
        }

        $args = [
            'method'  => strtoupper($method),
            'headers' => array_merge([
                'Authorization' => 'Bearer ' . $auth['token'],
                'Accept'        => 'application/json',
            ], $headers),
            'timeout' => 20,
        ];
        if (!is_null($payload)) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = is_string($payload) ? $payload : wp_json_encode($payload);
        }

        $resp = wp_remote_request($url, $args);
        if (is_wp_error($resp)) return $resp;

        $code = wp_remote_retrieve_response_code($resp);
        if ($code === 401 && ($auth['mode'] ?? '') === 'oauth') {
            // try one refresh only
            $token = cc_get_access_token(999999);
            if (!$token) return $resp;
            $args['headers']['Authorization'] = 'Bearer ' . $token;
            $resp = wp_remote_request($url, $args);
        }
        return $resp;
    }
}
if (!function_exists('cc_get_access_token')) {
    function cc_get_access_token($skew_seconds = 300) // refresh ~5m early
    {
        $t = get_option('cc_tokens');
        if (empty($t['access_token'])) return false;

        $expires_at = (int)($t['obtained_at'] ?? 0) + (int)($t['expires_in'] ?? 0);
        if (time() < $expires_at - $skew_seconds) return $t['access_token'];

        if (empty($t['refresh_token'])) return false;
        if (get_transient('cc_refresh_lock')) {
            // someone else refreshing—use current token optimistically
            return $t['access_token'];
        }
        set_transient('cc_refresh_lock', 1, 30);

        $token_url = 'https://authz.constantcontact.com/oauth2/default/v1/token';
        $headers = [
            'Authorization' => 'Basic ' . base64_encode(CC_CLIENT_ID . ':' . CC_CLIENT_SECRET),
            'Content-Type'  => 'application/x-www-form-urlencoded',
            'Accept'        => 'application/json',
        ];
        $body = [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $t['refresh_token'],
        ];

        $resp = wp_remote_post($token_url, ['headers' => $headers, 'body' => $body, 'timeout' => 20]);
        delete_transient('cc_refresh_lock');

        if (is_wp_error($resp)) {
            error_log('CC refresh error: ' . $resp->get_error_message());
            return false;
        }

        $code_http = wp_remote_retrieve_response_code($resp);
        $json      = json_decode(wp_remote_retrieve_body($resp), true);

        if ($code_http < 200 || $code_http >= 300 || empty($json['access_token'])) {
            error_log('CC refresh failed: HTTP ' . $code_http . ' ' . wp_remote_retrieve_body($resp));
            return false;
        }

        $t['access_token']  = $json['access_token'];
        if (!empty($json['refresh_token'])) $t['refresh_token'] = $json['refresh_token']; // rotated
        $t['expires_in']  = (int)($json['expires_in'] ?? 0);
        $t['obtained_at'] = time();
        $t['token_type']  = $json['token_type'] ?? 'Bearer';
        $t['scope']       = $json['scope']      ?? ($t['scope'] ?? '');

        update_option('cc_tokens', $t, false);
        return $t['access_token'];
    }
}
if (!function_exists('cc_get_custom_field_id_by_label')) {
    function cc_get_custom_field_id_by_label(string $label)
    {
        $cache_key = 'cc_cf_' . md5($label);
        if ($id = get_option($cache_key)) return $id;

        $token = cc_get_access_token();
        if (!$token) return false;

        $resp = wp_remote_get('https://api.cc.email/v3/contact_custom_fields', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
            'timeout' => 15,
        ]);
        if (is_wp_error($resp)) return false;

        $code = wp_remote_retrieve_response_code($resp);
        $data = json_decode(wp_remote_retrieve_body($resp), true);

        if ($code >= 200 && $code < 300 && !empty($data['custom_fields'])) {
            foreach ($data['custom_fields'] as $cf) {
                if (!empty($cf['label']) && strcasecmp($cf['label'], $label) === 0 && !empty($cf['custom_field_id'])) {
                    update_option($cache_key, $cf['custom_field_id'], false);
                    return $cf['custom_field_id'];
                }
            }
        }
        return false;
    }
}
if (!function_exists('mz_cc_add_contact')) {
    /**
     * Upsert a contact and add to CC list; optionally tag.
     * Expects $data keys: Email (required), FirstName, LastName, Organization (Company), Website, LeadTags (array/string)
     */
    function mz_cc_add_contact(array $data)
    {
        $email = isset($data['Email']) ? sanitize_email($data['Email']) : '';
        if (!is_email($email)) return;

        $auth = cc_get_auth_context();
        if (empty($auth['token'])) {
            error_log('CC: no OAuth token or API key; skipping signup.');
            return;
        }

        $payload = [
            'email_address'    => strtolower(trim($email)),
            'list_memberships' => [CC_LIST_ID],
        ];

        if (!empty($data['FirstName']))    $payload['first_name']   = sanitize_text_field($data['FirstName']);
        if (!empty($data['LastName']))     $payload['last_name']    = sanitize_text_field($data['LastName']);
        if (!empty($data['Organization'])) $payload['company_name'] = mb_substr(sanitize_text_field($data['Organization']), 0, 50);

        // Custom Field: Website URL (label must exist in CC)
        if (!empty($data['Website'])) {
            $cf_id = cc_get_custom_field_id_by_label('Website URL');
            if ($cf_id) {
                $payload['custom_fields'][] = [
                    'custom_field_id' => $cf_id,
                    'value'           => esc_url_raw($data['Website']),
                ];
            } else {
                error_log('CC: custom field "Website URL" not found.');
            }
        }

        $resp = cc_api_request('POST', 'https://api.cc.email/v3/contacts/sign_up_form', $payload);

        if (is_wp_error($resp)) {
            error_log('CC upsert error: ' . $resp->get_error_message());
            return;
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code < 200 || $code >= 300) {
            error_log('CC upsert http ' . $code . ': ' . wp_remote_retrieve_body($resp));
            return;
        }

        $contact_id = $body['contact_id'] ?? ($body['contact']['contact_id'] ?? null);
        if (!$contact_id) {
            error_log('CC: missing contact_id in response: ' . wp_remote_retrieve_body($resp));
            return;
        }

        // Optional: tag contact
        if (defined('CC_TAG_ID') && CC_TAG_ID) {
            $tagPayload = [
                'source'  => ['contact_ids' => [$contact_id]],
                'tag_ids' => [CC_TAG_ID],
            ];
            $tagResp = wp_remote_post(
                'https://api.cc.email/v3/activities/contacts_taggings_add',
                [
                        'headers' => [
                        'Authorization' => 'Bearer ' . $auth['token'],
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json',
                    ],
                    'body'    => wp_json_encode($tagPayload),
                    'timeout' => 20,
                ]
            );
            if (is_wp_error($tagResp) || wp_remote_retrieve_response_code($tagResp) >= 300) {
                error_log('CC tag error: ' . (is_wp_error($tagResp) ? $tagResp->get_error_message() : wp_remote_retrieve_body($tagResp)));
            }
        }
    }
}
add_action('init', function () {
    if (!wp_next_scheduled('cc_hourly_refresh')) {
        wp_schedule_event(time() + 300, 'hourly', 'cc_hourly_refresh');
    }
});
add_action('cc_hourly_refresh', function () {
    // force a refresh if expiring within 30 minutes
    $t = get_option('cc_tokens');
    if (empty($t['access_token']) || empty($t['expires_in']) || empty($t['obtained_at'])) return;

    $expires_at = (int)$t['obtained_at'] + (int)$t['expires_in'];
    if (time() >= $expires_at - 1800) {
        cc_get_access_token(999999); // forces refresh
    }
});

// (Optional) Allow AJAX hit to callback (not required for your flow, but harmless)
add_action('wp_ajax_cc_oauth_callback',        'cc_process_oauth_callback');
add_action('wp_ajax_nopriv_cc_oauth_callback', 'cc_process_oauth_callback');
