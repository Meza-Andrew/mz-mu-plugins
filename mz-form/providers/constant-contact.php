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
if (!function_exists('cc_get_contact_url')) {
    function cc_get_contact_url(string $contact_id = ''): string
    {
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
        $phone = trim((string) ($data['Phone'] ?? $data['WorkPhone'] ?? $data['Work phone'] ?? $data['work_phone'] ?? ''));
        $zip = trim((string) ($data['Zip'] ?? $data['ZipCode'] ?? $data['Zip code'] ?? $data['zip_code'] ?? ''));

        if ($first_name !== '') {
            $payload['first_name'] = sanitize_text_field($first_name);
        }
        if ($last_name !== '') {
            $payload['last_name'] = sanitize_text_field($last_name);
        }
        if ($company_name !== '') {
            $payload['company_name'] = mb_substr(sanitize_text_field($company_name), 0, 50);
        }
        if ($phone !== '') {
            $phone_number = preg_replace('/[^\d+]/', '', $phone);
            if ($phone_number !== '') {
                $payload['phone_numbers'] = [[
                    'phone_number' => $phone_number,
                    'kind' => 'work',
                ]];
            }
        }
        if ($zip !== '') {
            $postal_code = sanitize_text_field($zip);
            if ($postal_code !== '') {
                $payload['street_addresses'] = [[
                    'postal_code' => $postal_code,
                ]];
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

        $contact_id = $body['contact_id'] ?? ($body['contact']['contact_id'] ?? null);
        if (!$contact_id) {
            $contact_id = (string) ($existing['contact_id'] ?? '');
            if ($contact_id === '') {
                return new WP_Error('cc_missing_contact_id', 'Constant Contact response did not include contact_id.');
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
