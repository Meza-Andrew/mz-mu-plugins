<?php

if (!defined('ABSPATH')) {
    exit;
}

// Constant Contact Integration
// ===============================

// --- Optional config overrides (set these in wp-config.php if preferred) ---
if (!defined('CC_CLIENT_ID')) {
    define('CC_CLIENT_ID', '');
}
if (!defined('CC_CLIENT_SECRET')) {
    define('CC_CLIENT_SECRET', '');
}
if (!defined('CC_SCOPES')) {
    define('CC_SCOPES', 'contact_data offline_access');
}
if (!defined('CC_REDIRECT_URI')) {
    define('CC_REDIRECT_URI', home_url('/cc-oauth-callback/'));
}
if (!defined('CC_LIST_ID')) {
    define('CC_LIST_ID', '');
}
if (!defined('MZ_CC_SUBMISSION_BACKFILL_OPTION')) {
    define('MZ_CC_SUBMISSION_BACKFILL_OPTION', 'mz_cc_submission_backfill');
}
if (!defined('MZ_CC_SUBMISSION_BACKFILL_NOTICE_TRANSIENT')) {
    define('MZ_CC_SUBMISSION_BACKFILL_NOTICE_TRANSIENT', 'mz_cc_submission_backfill_notice');
}
if (!defined('MZ_CC_SUBMISSION_BACKFILL_HOOK')) {
    define('MZ_CC_SUBMISSION_BACKFILL_HOOK', 'mz_cc_submission_backfill_batch');
}

if (!function_exists('cc_get_client_id')) {
    function cc_get_client_id(): string
    {
        $crm = function_exists('mzf_get_crm_group') ? mzf_get_crm_group() : [];
        if (empty($crm) && function_exists('get_field')) {
            $acf_crm = get_field('crm', 'option');
            if (is_array($acf_crm)) {
                $crm = $acf_crm;
            }
        }
        if (empty($crm)) {
            $opt_crm = get_option('crm');
            if (is_array($opt_crm)) {
                $crm = $opt_crm;
            }
        }

        $nested = $crm['constant-contact'] ?? $crm['constant_contact'] ?? null;
        if (is_array($nested)) {
            $client_id = trim((string) ($nested['client_id'] ?? ''));
            if ($client_id !== '') {
                return $client_id;
            }
        }

        $direct_group = [];
        if (function_exists('get_field')) {
            $acf_group = get_field('constant-contact', 'option');
            if (is_array($acf_group)) {
                $direct_group = $acf_group;
            } else {
                $acf_group = get_field('constant_contact', 'option');
                if (is_array($acf_group)) {
                    $direct_group = $acf_group;
                }
            }
        }
        if (empty($direct_group)) {
            $opt_group = get_option('options_constant-contact');
            if (is_array($opt_group)) {
                $direct_group = $opt_group;
            } else {
                $opt_group = get_option('options_constant_contact');
                if (is_array($opt_group)) {
                    $direct_group = $opt_group;
                }
            }
        }
        if (!empty($direct_group)) {
            $client_id = trim((string) ($direct_group['client_id'] ?? ''));
            if ($client_id !== '') {
                return $client_id;
            }
        }

        $candidates = [
            trim((string) ($crm['client_id_constant-contact'] ?? '')),
            trim((string) ($crm['client_id_constant_contact'] ?? '')),
            trim((string) get_option('options_crm_client_id_constant-contact', '')),
            trim((string) get_option('options_crm_client_id_constant_contact', '')),
            trim((string) get_option('options_constant-contact_client_id', '')),
            trim((string) get_option('options_constant_contact_client_id', '')),
            trim((string) get_option('cc_client_id', '')),
            trim((string) (defined('CC_CLIENT_ID') ? CC_CLIENT_ID : '')),
        ];
        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}

if (!function_exists('cc_get_client_secret')) {
    function cc_get_client_secret(): string
    {
        $crm = function_exists('mzf_get_crm_group') ? mzf_get_crm_group() : [];
        if (empty($crm) && function_exists('get_field')) {
            $acf_crm = get_field('crm', 'option');
            if (is_array($acf_crm)) {
                $crm = $acf_crm;
            }
        }
        if (empty($crm)) {
            $opt_crm = get_option('crm');
            if (is_array($opt_crm)) {
                $crm = $opt_crm;
            }
        }

        $nested = $crm['constant-contact'] ?? $crm['constant_contact'] ?? null;
        if (is_array($nested)) {
            $client_secret = trim((string) ($nested['client_secret'] ?? ''));
            if ($client_secret !== '') {
                return $client_secret;
            }
        }

        $direct_group = [];
        if (function_exists('get_field')) {
            $acf_group = get_field('constant-contact', 'option');
            if (is_array($acf_group)) {
                $direct_group = $acf_group;
            } else {
                $acf_group = get_field('constant_contact', 'option');
                if (is_array($acf_group)) {
                    $direct_group = $acf_group;
                }
            }
        }
        if (empty($direct_group)) {
            $opt_group = get_option('options_constant-contact');
            if (is_array($opt_group)) {
                $direct_group = $opt_group;
            } else {
                $opt_group = get_option('options_constant_contact');
                if (is_array($opt_group)) {
                    $direct_group = $opt_group;
                }
            }
        }
        if (!empty($direct_group)) {
            $client_secret = trim((string) ($direct_group['client_secret'] ?? ''));
            if ($client_secret !== '') {
                return $client_secret;
            }
        }

        $candidates = [
            trim((string) ($crm['client_secret_constant-contact'] ?? '')),
            trim((string) ($crm['client_secret_constant_contact'] ?? '')),
            trim((string) get_option('options_crm_client_secret_constant-contact', '')),
            trim((string) get_option('options_crm_client_secret_constant_contact', '')),
            trim((string) get_option('options_constant-contact_client_secret', '')),
            trim((string) get_option('options_constant_contact_client_secret', '')),
            trim((string) get_option('cc_client_secret', '')),
            trim((string) (defined('CC_CLIENT_SECRET') ? CC_CLIENT_SECRET : '')),
        ];
        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}

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

        $nested = $crm['constant-contact'] ?? $crm['constant_contact'] ?? null;
        if (is_array($nested)) {
            $nested_api = trim((string) ($nested['api'] ?? ''));
            if ($nested_api !== '') {
                return $nested_api;
            }
        }

        $candidates = [
            trim((string) ($crm['api_constant-contact'] ?? '')),
            trim((string) ($crm['api_constant_contact'] ?? '')),
            trim((string) get_option('options_crm_api_constant-contact', '')),
            trim((string) get_option('options_crm_api_constant_contact', '')),
            trim((string) get_option('cc_api_key', '')),
        ];
        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}

if (!function_exists('cc_get_list_id')) {
    function cc_get_list_id(): string
    {
        if (function_exists('mzf_crm_list_id')) {
            $crm_list = mzf_crm_list_id('constant-contact');
            if ($crm_list !== '') {
                return $crm_list;
            }
        }

        $crm = [];
        if (function_exists('get_field')) {
            $acf_crm = get_field('crm', 'option');
            if (is_array($acf_crm)) {
                $crm = $acf_crm;
            }
        }
        if (empty($crm)) {
            $opt_crm = get_option('crm');
            if (is_array($opt_crm)) {
                $crm = $opt_crm;
            }
        }

        $nested = $crm['constant-contact'] ?? $crm['constant_contact'] ?? null;
        if (is_array($nested)) {
            $nested_list = trim((string) ($nested['list'] ?? ''));
            if ($nested_list !== '') {
                return $nested_list;
            }
        }

        $direct_group = [];
        if (function_exists('get_field')) {
            $acf_group = get_field('constant-contact', 'option');
            if (is_array($acf_group)) {
                $direct_group = $acf_group;
            } else {
                $acf_group = get_field('constant_contact', 'option');
                if (is_array($acf_group)) {
                    $direct_group = $acf_group;
                }
            }
        }
        if (empty($direct_group)) {
            $opt_group = get_option('options_constant-contact');
            if (is_array($opt_group)) {
                $direct_group = $opt_group;
            } else {
                $opt_group = get_option('options_constant_contact');
                if (is_array($opt_group)) {
                    $direct_group = $opt_group;
                }
            }
        }
        if (!empty($direct_group)) {
            $list_id = trim((string) ($direct_group['list'] ?? ''));
            if ($list_id !== '') {
                return $list_id;
            }
        }

        $candidates = [
            trim((string) ($crm['list_constant-contact'] ?? '')),
            trim((string) ($crm['list_constant_contact'] ?? '')),
            trim((string) get_option('options_crm_list_constant-contact', '')),
            trim((string) get_option('options_crm_list_constant_contact', '')),
            trim((string) get_option('options_constant-contact_list', '')),
            trim((string) get_option('options_constant_contact_list', '')),
            trim((string) get_option('cc_list_id', '')),
            trim((string) (defined('CC_LIST_ID') ? CC_LIST_ID : '')),
        ];
        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
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
        $client_id = cc_get_client_id();
        $client_secret = cc_get_client_secret();
        $list_id = cc_get_list_id();

        if ($client_id === '' || $client_secret === '' || $list_id === '') return false;

        return true;
    }
}

if (!function_exists('cc_is_configured')) {
    function cc_is_configured(): bool
    {
        return cc_get_list_id() !== '' && (cc_get_api_key() !== '' || cc_get_access_token() !== false);
    }
}

if (!function_exists('cc_is_platform_selected')) {
    function cc_is_platform_selected(): bool
    {
        $raw = '';

        if (function_exists('get_field')) {
            $platform = get_field('platform', 'option');
            if (is_string($platform) && trim($platform) !== '') {
                $raw = $platform;
            }

            if ($raw === '') {
                $crm = get_field('crm', 'option');
                if (is_array($crm) && !empty($crm['platform'])) {
                    $raw = (string) $crm['platform'];
                }
            }
        }

        if ($raw === '') {
            $raw = (string) get_option('options_platform', '');
        }
        if ($raw === '') {
            $crm = get_option('crm');
            if (is_array($crm) && !empty($crm['platform'])) {
                $raw = (string) $crm['platform'];
            }
        }
        if ($raw === '') {
            $raw = (string) get_option('crm_platform', '');
        }

        $value = strtolower(trim($raw));
        $value = str_replace(['_', ' '], '-', $value);
        $value = preg_replace('/-+/', '-', $value);

        return in_array($value, ['constant-contact', 'constantcontact'], true);
    }
}

if (!function_exists('cc_maybe_disconnect_when_platform_changes')) {
    function cc_maybe_disconnect_when_platform_changes($post_id): void
    {
        // ACF options page saves use "options"/"option".
        if (!in_array((string) $post_id, ['options', 'option'], true)) {
            return;
        }

        // Keep ACF values; only drop OAuth auth state when CC isn't selected.
        if (cc_is_platform_selected()) {
            return;
        }

        if (get_option('cc_tokens', null) !== null) {
            delete_option('cc_tokens');
        }
        delete_transient('cc_refresh_lock');
    }
}

add_action('acf/save_post', 'cc_maybe_disconnect_when_platform_changes', 30);

if (!function_exists('cc_is_crm_options_save_request')) {
    function cc_is_crm_options_save_request($post_id): bool
    {
        if (!in_array((string) $post_id, ['options', 'option'], true)) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        return $page === 'crm';
    }
}

if (!function_exists('cc_build_auth_url')) {
    function cc_build_auth_url(): string
    {
        $params = [
            'client_id'     => cc_get_client_id(),
            'scope'         => CC_SCOPES, // space-separated
            'response_type' => 'code',
            'redirect_uri'  => CC_REDIRECT_URI, // byte-for-byte w/ app
            'state'         => wp_create_nonce('cc_oauth_state'),
        ];
        $auth_base = 'https://authz.constantcontact.com/oauth2/default/v1/authorize';
        return $auth_base . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}

add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!isset($_GET['page']) || $_GET['page'] !== 'crm') {
        return;
    }
    if (!cc_is_platform_selected()) {
        return;
    }

    echo '<div class="notice notice-info"><p><strong>Constant Contact Integration</strong></p>';
    if (!cc_has_wp_config_keys()) {
        echo '<p>OAuth app credentials are missing. Set Client ID, Client Secret, and List ID in CRM Integration (Constant Contact group), or fallback constants <code>CC_CLIENT_ID</code>, <code>CC_CLIENT_SECRET</code>, and <code>CC_LIST_ID</code>.</p>';
        echo '</div>';
        return;
    }

    echo '<style>
        .mz-oauth-row{display:flex;align-items:center;gap:10px;margin:4px 0 6px 0}
        .wp-core-ui .button.button-primary.mz-oauth-connected,
        .wp-core-ui .button.button-primary.mz-oauth-connected:disabled,
        .wp-core-ui .button.button-primary.mz-oauth-connected[disabled],
        .wp-core-ui .button.button-primary.mz-oauth-connected.disabled{
            display:inline-flex !important;align-items:center !important;gap:5px;height:auto;line-height:1.2;padding-top:6px;padding-bottom:6px;
            color:#fff !important;background-color:#72aee6 !important;border-color:#72aee6 !important;opacity:1 !important;filter:none !important;
            box-shadow:none !important;text-shadow:none !important;cursor:default
        }
        .wp-core-ui .button.button-primary.mz-oauth-connected .dashicons{font-size:14px;line-height:16px;display:inline-flex;align-items:center;justify-content:center;color:#fff !important;vertical-align:middle;width:16px;height:16px;flex:0 0 16px}
        .mz-oauth-status{margin:0}
        @media (max-width:782px){.mz-oauth-row{flex-direction:column;align-items:flex-start;gap:6px}}
    </style>';

    $t = get_option('cc_tokens');
    $connected = !empty($t['access_token']);
    echo '<div class="mz-oauth-row">';
    if ($connected) {
        echo '<button type="button" class="button button-primary mz-oauth-connected" disabled aria-disabled="true">';
        echo '<span class="dashicons dashicons-yes-alt"></span>';
        echo 'Connected to Constant Contact!';
        echo '</button>';
        echo '<span class="mz-oauth-status">Refresh token saved. Access token auto-refreshes.</span>';
    } else {
        echo '<a class="button button-primary" href="' . esc_url(cc_build_auth_url()) . '">Connect Constant Contact</a>';
        echo '<span class="mz-oauth-status">Refresh token not available until you connect.</span>';
    }
    echo '</div>';
    echo '<p style="margin:0 0 10px 0;"><strong>Redirect URI:</strong> <code>' . esc_html((string) CC_REDIRECT_URI) . '</code></p>';
    echo '</div>';
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
        if (cc_get_client_id() === '' || cc_get_client_secret() === '') {
            wp_die('Missing Constant Contact client credentials.');
        }

        $token_url = 'https://authz.constantcontact.com/oauth2/default/v1/token';
        $headers = [
            'Authorization' => 'Basic ' . base64_encode(cc_get_client_id() . ':' . cc_get_client_secret()),
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
        if (function_exists('cc_queue_submission_backfill')) {
            cc_queue_submission_backfill('oauth_connect');
        }

        wp_safe_redirect(add_query_arg('cc_connected', '1', admin_url('options-general.php?page=crm')));
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

        if (cc_get_client_id() === '' || cc_get_client_secret() === '') {
            delete_transient('cc_refresh_lock');
            return false;
        }

        $token_url = 'https://authz.constantcontact.com/oauth2/default/v1/token';
        $headers = [
            'Authorization' => 'Basic ' . base64_encode(cc_get_client_id() . ':' . cc_get_client_secret()),
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
if (!function_exists('cc_extract_tag_id')) {
    function cc_extract_tag_id(array $tag): string
    {
        $id = trim((string) ($tag['tag_id'] ?? $tag['contact_tag_id'] ?? $tag['id'] ?? ''));
        return $id;
    }
}
if (!function_exists('cc_find_or_create_tag_ids')) {
    function cc_find_or_create_tag_ids(array $tag_names): array
    {
        $tag_names = array_values(array_unique(array_filter(array_map(static function ($name) {
            return sanitize_text_field((string) $name);
        }, $tag_names))));
        if (empty($tag_names)) {
            return [];
        }

        $resp = cc_api_request('GET', 'https://api.cc.email/v3/contact_tags');
        if (is_wp_error($resp)) {
            return [];
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode((string) wp_remote_retrieve_body($resp), true);
        $existing_by_name = [];
        if ($code >= 200 && $code < 300 && is_array($body)) {
            $tags = [];
            if (!empty($body['tags']) && is_array($body['tags'])) {
                $tags = $body['tags'];
            } elseif (!empty($body['contact_tags']) && is_array($body['contact_tags'])) {
                $tags = $body['contact_tags'];
            }
            foreach ($tags as $tag) {
                if (!is_array($tag)) {
                    continue;
                }
                $name = strtolower(trim((string) ($tag['name'] ?? '')));
                $id = cc_extract_tag_id($tag);
                if ($name !== '' && $id !== '') {
                    $existing_by_name[$name] = $id;
                }
            }
        }

        $tag_ids = [];
        foreach ($tag_names as $name) {
            $key = strtolower(trim($name));
            if ($key !== '' && isset($existing_by_name[$key])) {
                $tag_ids[] = $existing_by_name[$key];
            }
        }

        $missing_names = [];
        foreach ($tag_names as $name) {
            $key = strtolower(trim($name));
            if ($key !== '' && !isset($existing_by_name[$key])) {
                $missing_names[] = $name;
            }
        }

        foreach ($missing_names as $missing_name) {
            $create_resp = cc_api_request('POST', 'https://api.cc.email/v3/contact_tags', [
                'name' => $missing_name,
            ]);
            if (is_wp_error($create_resp)) {
                continue;
            }

            $create_code = (int) wp_remote_retrieve_response_code($create_resp);
            $create_body = json_decode((string) wp_remote_retrieve_body($create_resp), true);
            if ($create_code < 200 || $create_code >= 300 || !is_array($create_body)) {
                continue;
            }

            $new_id = cc_extract_tag_id($create_body);
            if ($new_id !== '') {
                $tag_ids[] = $new_id;
            }
        }

        return array_values(array_unique(array_filter($tag_ids)));
    }
}
if (!function_exists('cc_find_contact_by_email')) {
    function cc_find_contact_by_email(string $email): array
    {
        $email = strtolower(trim($email));
        if ($email === '' || !is_email($email)) {
            return ['exists' => false, 'contact_id' => ''];
        }

        $resp = cc_api_request('GET', 'https://api.cc.email/v3/contacts?email=' . rawurlencode($email));
        if (is_wp_error($resp)) {
            return ['exists' => false, 'contact_id' => ''];
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) {
            return ['exists' => false, 'contact_id' => ''];
        }

        $body = json_decode((string) wp_remote_retrieve_body($resp), true);
        if (!is_array($body)) {
            return ['exists' => false, 'contact_id' => ''];
        }

        $contacts = [];
        if (!empty($body['contacts']) && is_array($body['contacts'])) {
            $contacts = $body['contacts'];
        } elseif (!empty($body['contact']) && is_array($body['contact'])) {
            $contacts = [$body['contact']];
        } elseif (!empty($body['contact_id']) || !empty($body['email_address'])) {
            $contacts = [$body];
        }

        foreach ($contacts as $contact) {
            if (!is_array($contact)) {
                continue;
            }

            $candidate_email = strtolower(trim((string) ($contact['email_address']['address'] ?? $contact['email_address'] ?? '')));
            if ($candidate_email === '' || $candidate_email !== $email) {
                continue;
            }

            $contact_id = trim((string) ($contact['contact_id'] ?? ''));
            return ['exists' => true, 'contact_id' => $contact_id];
        }

        return ['exists' => false, 'contact_id' => ''];
    }
}
if (!function_exists('cc_get_contact_details')) {
    function cc_get_contact_details(string $contact_id): array
    {
        $contact_id = trim($contact_id);
        if ($contact_id === '') {
            return [];
        }

        $url = 'https://api.cc.email/v3/contacts/' . rawurlencode($contact_id) . '?include=phone_numbers,street_addresses,list_memberships';
        $resp = cc_api_request('GET', $url);
        if (is_wp_error($resp)) {
            return [];
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) {
            return [];
        }

        $body = json_decode((string) wp_remote_retrieve_body($resp), true);
        return is_array($body) ? $body : [];
    }
}
if (!function_exists('cc_update_work_phone')) {
    function cc_update_work_phone(string $contact_id, string $email, string $work_phone, string $list_id = '')
    {
        $contact_id = trim($contact_id);
        $email = strtolower(trim($email));
        $work_phone = trim($work_phone);
        if ($contact_id === '' || $email === '' || $work_phone === '') {
            return new WP_Error('cc_work_phone_missing_data', 'Missing data for Constant Contact work phone update.');
        }

        $detail = cc_get_contact_details($contact_id);
        $payload = [
            'email_address' => [
                'address' => $email,
            ],
            'update_source' => 'Account',
        ];

        foreach (['first_name', 'last_name', 'job_title', 'company_name', 'anniversary', 'birthday_month', 'birthday_day', 'create_source'] as $core_key) {
            if (isset($detail[$core_key]) && $detail[$core_key] !== '') {
                $payload[$core_key] = $detail[$core_key];
            }
        }

        $list_memberships = [];
        if (!empty($detail['list_memberships']) && is_array($detail['list_memberships'])) {
            foreach ($detail['list_memberships'] as $lid) {
                $lid = trim((string) $lid);
                if ($lid !== '') {
                    $list_memberships[] = $lid;
                }
            }
        }
        if ($list_id !== '') {
            $list_memberships[] = $list_id;
        }
        $list_memberships = array_values(array_unique(array_filter($list_memberships)));
        if (!empty($list_memberships)) {
            $payload['list_memberships'] = $list_memberships;
        }

        $phones = [];
        if (!empty($detail['phone_numbers']) && is_array($detail['phone_numbers'])) {
            foreach ($detail['phone_numbers'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $kind = trim((string) ($entry['kind'] ?? ''));
                $number = trim((string) ($entry['phone_number'] ?? ''));
                if ($kind === '' || $number === '') {
                    continue;
                }
                $phones[] = [
                    'kind' => $kind,
                    'phone_number' => $number,
                ];
            }
        }

        $replaced = false;
        foreach ($phones as &$entry) {
            if (strtolower((string) ($entry['kind'] ?? '')) === 'work') {
                $entry['phone_number'] = $work_phone;
                $replaced = true;
                break;
            }
        }
        unset($entry);
        if (!$replaced) {
            $phones[] = [
                'kind' => 'work',
                'phone_number' => $work_phone,
            ];
        }
        $payload['phone_numbers'] = $phones;

        return cc_api_request('PUT', 'https://api.cc.email/v3/contacts/' . rawurlencode($contact_id), $payload);
    }
}
if (!function_exists('cc_update_work_postal')) {
    function cc_update_work_postal(string $contact_id, string $email, string $work_postal, string $country = 'US', string $list_id = '')
    {
        $contact_id = trim($contact_id);
        $email = strtolower(trim($email));
        $work_postal = strtoupper(trim($work_postal));
        $country = strtoupper(trim($country));
        if ($contact_id === '' || $email === '' || $work_postal === '') {
            return new WP_Error('cc_work_postal_missing_data', 'Missing data for Constant Contact work postal update.');
        }
        if ($country === '') {
            $country = 'US';
        }

        $detail = cc_get_contact_details($contact_id);
        $payload = [
            'email_address' => [
                'address' => $email,
            ],
            'update_source' => 'Account',
        ];

        foreach (['first_name', 'last_name', 'job_title', 'company_name', 'anniversary', 'birthday_month', 'birthday_day', 'create_source'] as $core_key) {
            if (isset($detail[$core_key]) && $detail[$core_key] !== '') {
                $payload[$core_key] = $detail[$core_key];
            }
        }

        $list_memberships = [];
        if (!empty($detail['list_memberships']) && is_array($detail['list_memberships'])) {
            foreach ($detail['list_memberships'] as $lid) {
                $lid = trim((string) $lid);
                if ($lid !== '') {
                    $list_memberships[] = $lid;
                }
            }
        }
        if ($list_id !== '') {
            $list_memberships[] = $list_id;
        }
        $list_memberships = array_values(array_unique(array_filter($list_memberships)));
        if (!empty($list_memberships)) {
            $payload['list_memberships'] = $list_memberships;
        }

        $addresses = [];
        if (!empty($detail['street_addresses']) && is_array($detail['street_addresses'])) {
            foreach ($detail['street_addresses'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $kind = trim((string) ($entry['kind'] ?? ''));
                if ($kind === '') {
                    continue;
                }
                $clean = ['kind' => $kind];
                foreach (['street', 'street2', 'city', 'state', 'postal_code', 'country'] as $k) {
                    if (isset($entry[$k]) && trim((string) $entry[$k]) !== '') {
                        $clean[$k] = trim((string) $entry[$k]);
                    }
                }
                $addresses[] = $clean;
            }
        }

        $replaced = false;
        foreach ($addresses as &$entry) {
            if (strtolower((string) ($entry['kind'] ?? '')) === 'work') {
                $entry['postal_code'] = $work_postal;
                if (empty($entry['country'])) {
                    $entry['country'] = $country;
                }
                $replaced = true;
                break;
            }
        }
        unset($entry);
        if (!$replaced) {
            $addresses[] = [
                'kind' => 'work',
                'postal_code' => $work_postal,
                'country' => $country,
            ];
        }
        $payload['street_addresses'] = $addresses;

        return cc_api_request('PUT', 'https://api.cc.email/v3/contacts/' . rawurlencode($contact_id), $payload);
    }
}
if (!function_exists('cc_get_contact_url')) {
    function cc_get_contact_url(string $contact_id = ''): string
    {
        $contact_id = trim($contact_id);
        if ($contact_id !== '') {
            return (string) esc_url_raw('https://app.constantcontact.com/contacts/' . rawurlencode($contact_id) . '/profile');
        }

        $url = '';
        if (function_exists('mzf_crm_click_url')) {
            $url = trim((string) mzf_crm_click_url('constant-contact'));
        }
        if ($url === '' && function_exists('mzf_crm_dashboard_url')) {
            $url = trim((string) mzf_crm_dashboard_url('constant-contact'));
        }
        if ($url === '') {
            $url = 'https://app.constantcontact.com/pages/contacts/ui/contacts';
        }
        if (!preg_match('#^https?://#i', $url)) {
            return '';
        }

        return (string) esc_url_raw($url);
    }
}

if (!function_exists('cc_set_submission_backfill_notice')) {
    function cc_set_submission_backfill_notice(string $message, string $type = 'info'): void
    {
        $type = sanitize_key($type);
        if (!in_array($type, ['success', 'info', 'warning', 'error'], true)) {
            $type = 'info';
        }

        set_transient(MZ_CC_SUBMISSION_BACKFILL_NOTICE_TRANSIENT, [
            'message' => sanitize_text_field($message),
            'type' => $type,
        ], HOUR_IN_SECONDS);
    }
}

if (!function_exists('cc_get_submission_backfill_state')) {
    function cc_get_submission_backfill_state(): array
    {
        $state = get_option(MZ_CC_SUBMISSION_BACKFILL_OPTION);
        return is_array($state) ? $state : [];
    }
}

if (!function_exists('cc_submission_has_live_link')) {
    function cc_submission_has_live_link(int $submission_id): bool
    {
        $marketing_sync = get_post_meta($submission_id, '_mzf_marketing_sync', true);
        if (!is_array($marketing_sync)) {
            return false;
        }

        $provider = strtolower(trim((string) ($marketing_sync['provider'] ?? '')));
        $sync_ok = !empty($marketing_sync['ok']);
        $contact_url = trim((string) ($marketing_sync['contact_url'] ?? ''));
        $dry_run = !empty($marketing_sync['dry_run']);

        if ($provider !== 'constant_contact' || !$sync_ok) {
            return false;
        }

        return ($contact_url !== '' || $dry_run);
    }
}

if (!function_exists('cc_submission_should_backfill')) {
    function cc_submission_should_backfill(int $submission_id): bool
    {
        if ($submission_id <= 0 || get_post_type($submission_id) !== 'mzf_submission') {
            return false;
        }

        if (function_exists('mzf_submission_has_crm_entry') && !mzf_submission_has_crm_entry($submission_id)) {
            return false;
        }

        $email = function_exists('mzf_submission_text_value')
            ? mzf_submission_text_value($submission_id, ['Email'], '_mzf_email')
            : trim((string) get_post_meta($submission_id, '_mzf_email', true));
        if (!is_email($email)) {
            return false;
        }

        $status_group = sanitize_key((string) get_post_meta($submission_id, '_mzf_delivery_status_group', true));
        if ($status_group === '') {
            $raw_status = sanitize_key((string) get_post_meta($submission_id, '_mzf_delivery_status', true));
            if (function_exists('mzf_submission_status_group')) {
                $status_group = mzf_submission_status_group($raw_status);
            }
        }
        if (in_array($status_group, ['spam_detected', 'failure_validation'], true)) {
            return false;
        }

        if (cc_submission_has_live_link($submission_id)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('cc_build_submission_payload')) {
    function cc_build_submission_payload(int $submission_id): array
    {
        $text_value = static function (array $keys, string $meta_key = '') use ($submission_id): string {
            if (function_exists('mzf_submission_text_value')) {
                return mzf_submission_text_value($submission_id, $keys, $meta_key);
            }

            if ($meta_key !== '') {
                return trim((string) get_post_meta($submission_id, $meta_key, true));
            }

            return '';
        };

        $int_value = static function (array $keys, string $meta_key = '') use ($submission_id): int {
            if (function_exists('mzf_submission_int_value')) {
                return mzf_submission_int_value($submission_id, $keys, $meta_key);
            }

            if ($meta_key !== '') {
                return (int) get_post_meta($submission_id, $meta_key, true);
            }

            return 0;
        };

        $form_slug = sanitize_key($text_value(['FormSlug'], '_mzf_form_slug'));
        $page_id = $int_value(['PageId'], '_mzf_page_id');
        $zip = function_exists('mzf_submission_zip_code')
            ? mzf_submission_zip_code($submission_id)
            : '';
        $lead_tags = [];

        if ($page_id > 0) {
            $page_slug = sanitize_title((string) get_post_field('post_name', $page_id));
            if ($page_slug !== '') {
                $lead_tags[] = 'website_page_' . $page_slug;
            }
        }

        if ($form_slug !== '') {
            $lead_tags[] = 'website_form_' . sanitize_title($form_slug);
        }

        return [
            'Email' => $text_value(['Email'], '_mzf_email'),
            'FirstName' => $text_value(['FirstName', 'ContactFirstName'], '_mzf_first_name'),
            'LastName' => $text_value(['LastName', 'ContactLastName'], '_mzf_last_name'),
            'Phone' => $text_value(['Phone', 'WorkPhone', 'phone'], '_mzf_phone'),
            'Company' => $text_value(['Company']),
            'Zip' => $zip,
            'PageId' => $page_id,
            'FormSlug' => $form_slug,
            'NewsletterSignup' => 'Yes',
            'LeadTags' => array_values(array_unique(array_filter($lead_tags))),
        ];
    }
}

if (!function_exists('cc_store_submission_sync_result')) {
    function cc_store_submission_sync_result(int $submission_id, array $marketing_sync): void
    {
        $normalized_sync = function_exists('mzf_log_normalize_value')
            ? mzf_log_normalize_value($marketing_sync)
            : $marketing_sync;

        update_post_meta($submission_id, '_mzf_marketing_sync', $normalized_sync);
        update_post_meta($submission_id, '_mzf_crm_platform', 'constant_contact');
        update_post_meta($submission_id, '_mzf_crm_platform_label', 'Constant Contact');
        update_post_meta($submission_id, '_mzf_newsletter_opt_in', '1');
    }
}

if (!function_exists('cc_backfill_submission')) {
    function cc_backfill_submission(int $submission_id): array
    {
        if (!cc_submission_should_backfill($submission_id)) {
            return ['status' => 'skipped'];
        }

        $payload = cc_build_submission_payload($submission_id);
        $result = mz_cc_add_contact($payload);
        if (is_wp_error($result)) {
            $sync_result = [
                'ok' => false,
                'provider' => 'constant_contact',
                'label' => 'Constant Contact',
                'error' => $result->get_error_message(),
            ];
            cc_store_submission_sync_result($submission_id, $sync_result);
            return [
                'status' => 'failed',
                'message' => $result->get_error_message(),
            ];
        }

        $sync_result = is_array($result)
            ? $result
            : ['ok' => true, 'provider' => 'constant_contact', 'label' => 'Constant Contact'];
        cc_store_submission_sync_result($submission_id, $sync_result);

        return ['status' => 'synced'];
    }
}

if (!function_exists('cc_collect_submission_backfill_ids')) {
    function cc_collect_submission_backfill_ids(): array
    {
        $query = new WP_Query([
            'post_type' => 'mzf_submission',
            'post_status' => ['private', 'publish', 'draft', 'pending', 'future'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'ASC',
            'no_found_rows' => true,
        ]);

        $ids = [];
        foreach ((array) $query->posts as $submission_id) {
            $submission_id = (int) $submission_id;
            if ($submission_id > 0 && cc_submission_should_backfill($submission_id)) {
                $ids[] = $submission_id;
            }
        }

        return $ids;
    }
}

if (!function_exists('cc_schedule_submission_backfill_event')) {
    function cc_schedule_submission_backfill_event(): void
    {
        if (!wp_next_scheduled(MZ_CC_SUBMISSION_BACKFILL_HOOK)) {
            wp_schedule_single_event(time() + 5, MZ_CC_SUBMISSION_BACKFILL_HOOK);
        }
    }
}

if (!function_exists('cc_queue_submission_backfill')) {
    function cc_queue_submission_backfill(string $trigger = 'manual'): array
    {
        if (!cc_is_platform_selected() || !cc_is_configured()) {
            return ['queued' => false, 'count' => 0];
        }

        $pending_ids = cc_collect_submission_backfill_ids();
        if (empty($pending_ids)) {
            delete_option(MZ_CC_SUBMISSION_BACKFILL_OPTION);
            cc_set_submission_backfill_notice('Constant Contact submission backfill is already up to date.', 'success');
            return ['queued' => false, 'count' => 0];
        }

        update_option(MZ_CC_SUBMISSION_BACKFILL_OPTION, [
            'provider' => 'constant_contact',
            'trigger' => sanitize_key($trigger),
            'pending_ids' => array_values($pending_ids),
            'total' => count($pending_ids),
            'success_count' => 0,
            'failure_count' => 0,
            'skipped_count' => 0,
            'updated_at' => time(),
        ], false);

        cc_schedule_submission_backfill_event();
        cc_set_submission_backfill_notice(
            sprintf('Constant Contact submission backfill queued for %d entr%s.', count($pending_ids), count($pending_ids) === 1 ? 'y' : 'ies'),
            'info'
        );

        return ['queued' => true, 'count' => count($pending_ids)];
    }
}

if (!function_exists('cc_process_submission_backfill_batch')) {
    function cc_process_submission_backfill_batch(): void
    {
        $state = cc_get_submission_backfill_state();
        if (empty($state)) {
            return;
        }

        if (!cc_is_platform_selected() || !cc_is_configured()) {
            delete_option(MZ_CC_SUBMISSION_BACKFILL_OPTION);
            cc_set_submission_backfill_notice('Constant Contact submission backfill stopped because Constant Contact is no longer connected.', 'warning');
            return;
        }

        $pending_ids = array_values(array_filter(array_map('absint', (array) ($state['pending_ids'] ?? []))));
        if (empty($pending_ids)) {
            delete_option(MZ_CC_SUBMISSION_BACKFILL_OPTION);
            return;
        }

        $success_count = (int) ($state['success_count'] ?? 0);
        $failure_count = (int) ($state['failure_count'] ?? 0);
        $skipped_count = (int) ($state['skipped_count'] ?? 0);
        $batch_ids = array_splice($pending_ids, 0, 20);

        foreach ($batch_ids as $submission_id) {
            $result = cc_backfill_submission((int) $submission_id);
            $status = (string) ($result['status'] ?? '');
            if ($status === 'synced') {
                $success_count++;
            } elseif ($status === 'failed') {
                $failure_count++;
            } else {
                $skipped_count++;
            }
        }

        if (!empty($pending_ids)) {
            $state['pending_ids'] = $pending_ids;
            $state['success_count'] = $success_count;
            $state['failure_count'] = $failure_count;
            $state['skipped_count'] = $skipped_count;
            $state['updated_at'] = time();
            update_option(MZ_CC_SUBMISSION_BACKFILL_OPTION, $state, false);
            cc_schedule_submission_backfill_event();
            return;
        }

        delete_option(MZ_CC_SUBMISSION_BACKFILL_OPTION);
        cc_set_submission_backfill_notice(
            sprintf(
                'Constant Contact submission backfill completed. %d synced, %d skipped, %d failed.',
                $success_count,
                $skipped_count,
                $failure_count
            ),
            $failure_count > 0 ? 'warning' : 'success'
        );
    }
}

add_action(MZ_CC_SUBMISSION_BACKFILL_HOOK, 'cc_process_submission_backfill_batch');

add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!isset($_GET['page']) || $_GET['page'] !== 'crm') {
        return;
    }

    $notice = get_transient(MZ_CC_SUBMISSION_BACKFILL_NOTICE_TRANSIENT);
    if (!is_array($notice) || empty($notice['message'])) {
        return;
    }

    delete_transient(MZ_CC_SUBMISSION_BACKFILL_NOTICE_TRANSIENT);
    $type = sanitize_key((string) ($notice['type'] ?? 'info'));
    if (!in_array($type, ['success', 'info', 'warning', 'error'], true)) {
        $type = 'info';
    }

    echo '<div class="notice notice-' . esc_attr($type) . '"><p>' . esc_html((string) $notice['message']) . '</p></div>';
});

if (!function_exists('mz_cc_add_contact')) {
    /**
     * Upsert a contact and add to CC list; optionally tag.
     * Expects $data keys: Email (required), FirstName, LastName, Company, Phone, Zip, LeadTags (array/string)
     */
    function mz_cc_add_contact(array $data)
    {
        $email_raw = (string) (
            $data['Email']
            ?? $data['EmailAddress']
            ?? $data['email']
            ?? $data['Email address']
            ?? ''
        );
        $email = sanitize_email($email_raw);
        if (!is_email($email)) {
            return new WP_Error('cc_missing_email', 'Email is required.');
        }

        $auth = cc_get_auth_context();
        if (empty($auth['token'])) {
            return new WP_Error('cc_no_auth', 'No Constant Contact OAuth token or API key.');
        }

        $list_id = cc_get_list_id();
        if ($list_id === '') {
            return new WP_Error('cc_missing_list', 'Constant Contact list ID is missing.');
        }
        $existing = cc_find_contact_by_email($email);
        $contact_exists = !empty($existing['exists']);

        $payload = [
            'email_address'    => strtolower(trim($email)),
            'list_memberships' => [$list_id],
        ];

        $first_name = trim((string) ($data['FirstName'] ?? $data['First name'] ?? $data['first_name'] ?? ''));
        $last_name = trim((string) ($data['LastName'] ?? $data['Last name'] ?? $data['last_name'] ?? ''));
        $company_name = trim((string) ($data['Company'] ?? $data['Company name'] ?? $data['company_name'] ?? ''));
        $phone = '';
        foreach ([
            'Phone',
            'WorkPhone',
            'Work phone',
            'work_phone',
            'phone',
            'phone_number',
            'PhoneNumber',
            'phoneNumber',
            'Work Phone',
            'workPhone',
        ] as $phone_key) {
            $candidate = isset($data[$phone_key]) ? trim((string) $data[$phone_key]) : '';
            if ($candidate !== '') {
                $phone = $candidate;
                break;
            }
        }

        $zip = '';
        foreach ([
            'Zip',
            'ZipCode',
            'Zip code',
            'zip_code',
            'zipcode',
            'Zipcode',
            'zipCode',
            'PostalCode',
            'postal_code',
            'Postal Code',
            'postal',
        ] as $zip_key) {
            $candidate = isset($data[$zip_key]) ? trim((string) $data[$zip_key]) : '';
            if ($candidate !== '') {
                $zip = $candidate;
                break;
            }
        }

        if ($first_name !== '') {
            $payload['first_name'] = sanitize_text_field($first_name);
        }
        if ($last_name !== '') {
            $payload['last_name'] = sanitize_text_field($last_name);
        }
        if ($company_name !== '') {
            $payload['company_name'] = mb_substr(sanitize_text_field($company_name), 0, 50);
        }
        $normalized_phone = '';
        if ($phone !== '') {
            $normalized_phone = trim((string) preg_replace('/[^0-9+\-\(\)\.\s]/', '', $phone));
            if ($normalized_phone !== '') {
                $payload['phone_number'] = $normalized_phone;
            }
        }
        $normalized_postal = '';
        $normalized_country = 'US';
        if ($zip !== '') {
            $normalized_postal = strtoupper(trim((string) preg_replace('/[^A-Za-z0-9\- ]/', '', $zip)));
            if ($normalized_postal !== '') {
                $country = '';
                foreach (['Country', 'country', 'ReceivingAddressCountry', 'receiving_address_country', 'AddressCountry', 'address_country'] as $country_key) {
                    $candidate_country = isset($data[$country_key]) ? trim((string) $data[$country_key]) : '';
                    if ($candidate_country !== '') {
                        $country = sanitize_text_field($candidate_country);
                        break;
                    }
                }
                if ($country === '') {
                    $country = 'US';
                }
                $normalized_country = strtoupper((string) $country);
                $payload['street_address'] = [
                    'kind' => 'work',
                    'postal_code' => $normalized_postal,
                    'country' => $normalized_country,
                ];
            }
        }

        $lead_tags = [];
        if (!empty($data['LeadTags'])) {
            $lead_tags = is_array($data['LeadTags']) ? $data['LeadTags'] : [(string) $data['LeadTags']];
        }
        $lead_tags = array_values(array_unique(array_filter(array_map('sanitize_text_field', $lead_tags))));

        if (function_exists('mzf_marketing_dry_run_enabled') && mzf_marketing_dry_run_enabled('constant_contact')) {
            $dry_requests = [[
                'method' => 'POST',
                'url' => 'https://api.cc.email/v3/contacts/sign_up_form',
                'payload' => $payload,
            ]];
            if ($normalized_phone !== '') {
                $dry_requests[] = [
                    'method' => 'PUT',
                    'url' => 'https://api.cc.email/v3/contacts/{contact_id}',
                    'payload' => [
                        'phone_numbers' => [[
                            'kind' => 'work',
                            'phone_number' => $normalized_phone,
                        ]],
                    ],
                ];
            }
            if ($normalized_postal !== '') {
                $dry_requests[] = [
                    'method' => 'PUT',
                    'url' => 'https://api.cc.email/v3/contacts/{contact_id}',
                    'payload' => [
                        'street_addresses' => [[
                            'kind' => 'work',
                            'postal_code' => $normalized_postal,
                            'country' => $normalized_country,
                        ]],
                    ],
                ];
            }
            if (!empty($lead_tags)) {
                $dry_requests[] = [
                    'method' => 'POST',
                    'url' => 'https://api.cc.email/v3/activities/contacts_taggings_add',
                    'payload' => [
                        'source' => ['contact_ids' => ['<resolved_after_contact_upsert>']],
                        'tag_names' => $lead_tags,
                    ],
                ];
            }

            error_log('Constant Contact dry run: ' . wp_json_encode([
                'email' => $email,
                'list_id' => $list_id,
                'contact_exists' => $contact_exists,
                'requests' => $dry_requests,
            ]));

            return [
                'ok' => true,
                'provider' => 'constant_contact',
                'label' => 'Constant Contact',
                'action' => $contact_exists ? 'update' : 'add',
                'email' => $email,
                'list_id' => $list_id,
                'dry_run' => true,
                'dry_run_env' => function_exists('wp_get_environment_type') ? (string) wp_get_environment_type() : '',
                'dry_run_reason' => 'Marketing dry run is enabled for this environment.',
                'dry_run_requests' => $dry_requests,
            ];
        }

        $resp = cc_api_request('POST', 'https://api.cc.email/v3/contacts/sign_up_form', $payload);
        if (is_wp_error($resp)) {
            return $resp;
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode((string) wp_remote_retrieve_body($resp), true);
        if (!is_array($body)) {
            $body = [];
        }
        if ($code < 200 || $code >= 300) {
            return new WP_Error('cc_upsert_failed', 'Constant Contact upsert failed: HTTP ' . $code);
        }

        $contact_id = (string) ($body['contact_id'] ?? ($body['contact']['contact_id'] ?? ''));
        if ($contact_id === '') {
            $contact_id = (string) ($existing['contact_id'] ?? '');
            if ($contact_id === '') {
                return new WP_Error('cc_missing_contact_id', 'Constant Contact response did not include contact_id.');
            }
        }

        if ($normalized_phone !== '') {
            $work_resp = cc_update_work_phone((string) $contact_id, (string) $email, (string) $normalized_phone, (string) $list_id);
            if (is_wp_error($work_resp) || (int) wp_remote_retrieve_response_code($work_resp) >= 300) {
                error_log('CC work phone update failed: ' . (is_wp_error($work_resp) ? $work_resp->get_error_message() : wp_remote_retrieve_body($work_resp)));
            }
        }
        if ($normalized_postal !== '') {
            $postal_resp = cc_update_work_postal((string) $contact_id, (string) $email, (string) $normalized_postal, (string) $normalized_country, (string) $list_id);
            if (is_wp_error($postal_resp) || (int) wp_remote_retrieve_response_code($postal_resp) >= 300) {
                error_log('CC work postal update failed: ' . (is_wp_error($postal_resp) ? $postal_resp->get_error_message() : wp_remote_retrieve_body($postal_resp)));
            }
        }

        $lead_tag_ids = cc_find_or_create_tag_ids($lead_tags);

        // Optional: tag contact by LeadTags.
        $tag_ids = array_values(array_unique(array_filter($lead_tag_ids)));
        if (!empty($tag_ids)) {
            $tagPayload = [
                'source'  => ['contact_ids' => [$contact_id]],
                'tag_ids' => $tag_ids,
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

        return [
            'ok' => true,
            'provider' => 'constant_contact',
            'label' => 'Constant Contact',
            'action' => $contact_exists ? 'update' : 'add',
            'link_text' => $contact_exists ? 'Updated in Constant Contact' : 'Added to Constant Contact',
            'contact_url' => cc_get_contact_url((string) $contact_id),
            'email' => $email,
            'list_id' => $list_id,
            'contact_id' => (string) $contact_id,
        ];
    }
}
add_action('init', function () {
    if (!wp_next_scheduled('cc_hourly_refresh')) {
        wp_schedule_event(time() + 300, 'hourly', 'cc_hourly_refresh');
    }
});

if (!function_exists('cc_maybe_queue_submission_backfill_on_crm_save')) {
    function cc_maybe_queue_submission_backfill_on_crm_save($post_id): void
    {
        if (!cc_is_crm_options_save_request($post_id)) {
            return;
        }

        cc_queue_submission_backfill('crm_settings_save');
    }
}

add_action('acf/save_post', 'cc_maybe_queue_submission_backfill_on_crm_save', 40);

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
