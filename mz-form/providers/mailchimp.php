<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MAILCHIMP_CLIENT_ID')) {
    define('MAILCHIMP_CLIENT_ID', '');
}
if (!defined('MAILCHIMP_CLIENT_SECRET')) {
    define('MAILCHIMP_CLIENT_SECRET', '');
}
if (!defined('MAILCHIMP_REDIRECT_URI')) {
    define('MAILCHIMP_REDIRECT_URI', home_url('/mc-oauth-callback/'));
}

if (!function_exists('mz_mc_get_client_id')) {
    function mz_mc_get_client_id(): string
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

        $nested = $crm['mailchimp'] ?? $crm['mail_chimp'] ?? null;
        if (is_array($nested)) {
            $client_id = trim((string) ($nested['client_id'] ?? ''));
            if ($client_id !== '') {
                return $client_id;
            }
        }

        $direct_group = [];
        if (function_exists('get_field')) {
            $acf_group = get_field('mailchimp', 'option');
            if (is_array($acf_group)) {
                $direct_group = $acf_group;
            }
        }
        if (empty($direct_group)) {
            $opt_group = get_option('options_mailchimp');
            if (is_array($opt_group)) {
                $direct_group = $opt_group;
            }
        }
        if (!empty($direct_group)) {
            $client_id = trim((string) ($direct_group['client_id'] ?? ''));
            if ($client_id !== '') {
                return $client_id;
            }
        }

        $candidates = [
            trim((string) ($crm['client_id_mailchimp'] ?? '')),
            trim((string) get_option('options_crm_client_id_mailchimp', '')),
            trim((string) get_option('options_mailchimp_client_id', '')),
            trim((string) get_option('mc_client_id', '')),
            trim((string) (defined('MAILCHIMP_CLIENT_ID') ? MAILCHIMP_CLIENT_ID : '')),
        ];
        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}

if (!function_exists('mz_mc_get_client_secret')) {
    function mz_mc_get_client_secret(): string
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

        $nested = $crm['mailchimp'] ?? $crm['mail_chimp'] ?? null;
        if (is_array($nested)) {
            $client_secret = trim((string) ($nested['client_secret'] ?? ''));
            if ($client_secret !== '') {
                return $client_secret;
            }
        }

        $direct_group = [];
        if (function_exists('get_field')) {
            $acf_group = get_field('mailchimp', 'option');
            if (is_array($acf_group)) {
                $direct_group = $acf_group;
            }
        }
        if (empty($direct_group)) {
            $opt_group = get_option('options_mailchimp');
            if (is_array($opt_group)) {
                $direct_group = $opt_group;
            }
        }
        if (!empty($direct_group)) {
            $client_secret = trim((string) ($direct_group['client_secret'] ?? ''));
            if ($client_secret !== '') {
                return $client_secret;
            }
        }

        $candidates = [
            trim((string) ($crm['client_secret_mailchimp'] ?? '')),
            trim((string) get_option('options_crm_client_secret_mailchimp', '')),
            trim((string) get_option('options_mailchimp_client_secret', '')),
            trim((string) get_option('mc_client_secret', '')),
            trim((string) (defined('MAILCHIMP_CLIENT_SECRET') ? MAILCHIMP_CLIENT_SECRET : '')),
        ];
        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}

if (!function_exists('mz_mc_get_oauth_data')) {
    function mz_mc_get_oauth_data(): array
    {
        $data = get_option('mz_mc_oauth');
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('mz_mc_get_oauth_token')) {
    function mz_mc_get_oauth_token(): string
    {
        $data = mz_mc_get_oauth_data();
        return trim((string) ($data['access_token'] ?? ''));
    }
}

if (!function_exists('mz_mc_get_oauth_dc')) {
    function mz_mc_get_oauth_dc(): string
    {
        $data = mz_mc_get_oauth_data();
        return trim((string) ($data['dc'] ?? ''));
    }
}

if (!function_exists('mz_mc_get_oauth_base')) {
    function mz_mc_get_oauth_base(): string
    {
        $data = mz_mc_get_oauth_data();
        $api_endpoint = trim((string) ($data['api_endpoint'] ?? ''));
        if ($api_endpoint !== '' && preg_match('#^https?://#i', $api_endpoint)) {
            return rtrim($api_endpoint, '/');
        }

        $dc = mz_mc_get_oauth_dc();
        if ($dc !== '') {
            return 'https://' . $dc . '.api.mailchimp.com/3.0';
        }

        return '';
    }
}

if (!function_exists('mz_mc_has_oauth_app_keys')) {
    function mz_mc_has_oauth_app_keys(): bool
    {
        return mz_mc_get_client_id() !== '' && mz_mc_get_client_secret() !== '';
    }
}

if (!function_exists('mz_mc_is_oauth_connected')) {
    function mz_mc_is_oauth_connected(): bool
    {
        return mz_mc_get_oauth_token() !== '' && mz_mc_get_oauth_base() !== '';
    }
}

if (!function_exists('mz_mc_get_list_id')) {
    function mz_mc_get_list_id(): string
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

        if (function_exists('mzf_crm_list_id')) {
            $crm_list_id = mzf_crm_list_id('mailchimp');
            if ($crm_list_id !== '') {
                return $crm_list_id;
            }
        }

        $nested = $crm['mailchimp'] ?? $crm['mail_chimp'] ?? null;
        if (is_array($nested)) {
            $list_id = trim((string) ($nested['list'] ?? ''));
            if ($list_id !== '') {
                return $list_id;
            }
        }

        $direct_group = [];
        if (function_exists('get_field')) {
            $acf_group = get_field('mailchimp', 'option');
            if (is_array($acf_group)) {
                $direct_group = $acf_group;
            }
        }
        if (empty($direct_group)) {
            $opt_group = get_option('options_mailchimp');
            if (is_array($opt_group)) {
                $direct_group = $opt_group;
            }
        }
        if (!empty($direct_group)) {
            $list_id = trim((string) ($direct_group['list'] ?? ''));
            if ($list_id !== '') {
                return $list_id;
            }
        }

        $candidates = [
            trim((string) ($crm['list_mailchimp'] ?? '')),
            trim((string) get_option('options_crm_list_mailchimp', '')),
            trim((string) get_option('options_mailchimp_list', '')),
            trim((string) get_option('options_mailchimp_list_id', '')),
            trim((string) get_option('mz_mc_list_id', '')),
            trim((string) get_option('mc_list_id', '')),
            trim((string) (defined('MAILCHIMP_LIST_ID') ? MAILCHIMP_LIST_ID : '')),
        ];
        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}

if (!function_exists('mz_mc_is_configured')) {
    function mz_mc_is_configured(): bool
    {
        return mz_mc_is_oauth_connected();
    }
}

if (!function_exists('mz_mc_get_auth_context')) {
    function mz_mc_get_auth_context(): array
    {
        $oauth_token = mz_mc_get_oauth_token();
        $oauth_base = mz_mc_get_oauth_base();
        if ($oauth_token !== '' && $oauth_base !== '') {
            return [
                'ok' => true,
                'mode' => 'oauth',
                'token' => $oauth_token,
                'base' => $oauth_base,
                'dc' => mz_mc_get_oauth_dc(),
            ];
        }

        return ['ok' => false];
    }
}

if (!function_exists('mz_mc_get_default_list_id')) {
    function mz_mc_get_default_list_id(): string
    {
        static $resolved = [];

        $auth = mz_mc_get_auth_context();
        if (empty($auth['ok'])) {
            return '';
        }
        $cache_material = (string) ($auth['token'] ?? '');
        if ($cache_material === '') {
            return '';
        }

        $cache_key = md5('oauth|' . $cache_material);
        if (array_key_exists($cache_key, $resolved)) {
            return $resolved[$cache_key];
        }

        $transient_key = 'mz_mc_default_list_' . $cache_key;
        $cached_list_id = get_transient($transient_key);
        if (is_string($cached_list_id) && trim($cached_list_id) !== '') {
            $resolved[$cache_key] = trim($cached_list_id);
            return $resolved[$cache_key];
        }

        $lookup = mz_mc_list_search();
        if (!$lookup['ok']) {
            $resolved[$cache_key] = '';
            return '';
        }

        $first_list = $lookup['lists'][0]['id'] ?? '';
        $first_list = trim((string) $first_list);
        if ($first_list !== '') {
            set_transient($transient_key, $first_list, HOUR_IN_SECONDS);
        }

        $resolved[$cache_key] = $first_list;
        return $resolved[$cache_key];
    }
}

if (!function_exists('mz_mc_resolve_list_id')) {
    function mz_mc_resolve_list_id(): string
    {
        $list_id = mz_mc_get_list_id();
        if ($list_id !== '') {
            return $list_id;
        }

        return mz_mc_get_default_list_id();
    }
}

if (!function_exists('mz_mc_response_error_message')) {
    function mz_mc_response_error_message(int $code, array $body): string
    {
        $detail = trim((string) ($body['detail'] ?? ''));
        $title = trim((string) ($body['title'] ?? ''));
        if ($detail !== '') {
            return $detail;
        }
        if ($title !== '') {
            return $title;
        }

        return 'HTTP ' . $code;
    }
}

if (!function_exists('mz_mc_debug_log')) {
    function mz_mc_debug_log(string $event, array $context = []): void
    {
        error_log('Mailchimp ' . $event . ': ' . wp_json_encode($context));
    }
}

if (!function_exists('mz_mc_get_available_merge_tags')) {
    function mz_mc_get_available_merge_tags(string $list_id): array
    {
        static $resolved = [];

        $list_id = trim($list_id);
        if ($list_id === '') {
            return [];
        }

        $auth = mz_mc_get_auth_context();
        if (empty($auth['ok'])) {
            return [];
        }
        $cache_material = (string) ($auth['token'] ?? '');
        if ($cache_material === '') {
            return [];
        }

        $cache_key = md5('oauth|' . $cache_material . '|' . $list_id);
        if (array_key_exists($cache_key, $resolved)) {
            return $resolved[$cache_key];
        }

        $transient_key = 'mz_mc_merge_tags_' . $cache_key;
        $cached_tags = get_transient($transient_key);
        if (is_array($cached_tags)) {
            $resolved[$cache_key] = $cached_tags;
            return $resolved[$cache_key];
        }

        $resp = mz_mc_api_request('GET', '/lists/' . rawurlencode($list_id) . '/merge-fields?count=100');
        if (is_wp_error($resp)) {
            $resolved[$cache_key] = [];
            return [];
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode((string) wp_remote_retrieve_body($resp), true);
        if ($code < 200 || $code >= 300 || !is_array($body)) {
            $resolved[$cache_key] = [];
            return [];
        }

        $tags = [];
        foreach ((array) ($body['merge_fields'] ?? []) as $field) {
            $tag = strtoupper(trim((string) ($field['tag'] ?? '')));
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }

        $tags = array_values(array_unique($tags));
        set_transient($transient_key, $tags, HOUR_IN_SECONDS);
        $resolved[$cache_key] = $tags;
        return $resolved[$cache_key];
    }
}

if (!function_exists('mz_mc_build_contact_url')) {
    function mz_mc_build_contact_url(string $key_or_dc, array $member): string
    {
        $dc = trim($key_or_dc);
        $web_id = trim((string) ($member['web_id'] ?? ''));
        if ($dc === '' || $web_id === '') {
            return '';
        }

        return 'https://' . rawurlencode($dc) . '.admin.mailchimp.com/lists/members/view?id=' . rawurlencode($web_id);
    }
}

if (!function_exists('mz_mc_get_member')) {
    function mz_mc_get_member(string $list_id, string $member_hash)
    {
        if ($list_id === '' || $member_hash === '') {
            return new WP_Error('mz_mc_missing_member_lookup', 'Mailchimp member lookup requires a list ID and member hash.');
        }

        $resp = mz_mc_api_request('GET', '/lists/' . rawurlencode($list_id) . '/members/' . $member_hash);
        if (is_wp_error($resp)) {
            return $resp;
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode((string) wp_remote_retrieve_body($resp), true);
        if (!is_array($body)) {
            $body = [];
        }

        if ($code === 404) {
            return false;
        }

        if ($code < 200 || $code >= 300) {
            return new WP_Error('mz_mc_member_lookup_failed', 'Mailchimp member lookup failed: ' . mz_mc_response_error_message($code, $body));
        }

        return $body;
    }
}

if (!function_exists('mz_mc_api_request')) {
    function mz_mc_api_request(string $method, string $path, $payload = null)
    {
        $auth = mz_mc_get_auth_context();
        if (empty($auth['ok'])) {
            return new WP_Error('mz_mc_missing_auth', 'Mailchimp authentication is missing.');
        }

        $base = (string) ($auth['base'] ?? '');
        if ($base === '') {
            return new WP_Error('mz_mc_missing_base', 'Mailchimp API base URL is missing.');
        }
        $url  = rtrim($base, '/') . '/' . ltrim($path, '/');

        $authorization = 'OAuth ' . (string) ($auth['token'] ?? '');

        $args = [
            'method'  => strtoupper($method),
            'headers' => [
                'Authorization' => $authorization,
                'Accept'        => 'application/json',
            ],
            'timeout' => 20,
        ];

        if (!is_null($payload)) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = is_string($payload) ? $payload : wp_json_encode($payload);
        }

        return wp_remote_request($url, $args);
    }
}

if (!function_exists('mz_mc_list_search')) {
    function mz_mc_list_search(): array
    {
        $resp = mz_mc_api_request('GET', '/lists?count=100');
        if (is_wp_error($resp)) {
            return ['ok' => false, 'error' => $resp->get_error_message(), 'lists' => []];
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode((string) wp_remote_retrieve_body($resp), true);
        if ($code < 200 || $code >= 300) {
            return ['ok' => false, 'error' => 'HTTP ' . $code, 'lists' => []];
        }

        $lists = [];
        foreach ((array) ($body['lists'] ?? []) as $list) {
            $lists[] = [
                'id'   => (string) ($list['id'] ?? ''),
                'name' => (string) ($list['name'] ?? ''),
            ];
        }
        return ['ok' => true, 'error' => '', 'lists' => $lists];
    }
}

if (!function_exists('mz_mc_upsert_contact')) {
    function mz_mc_upsert_contact(array $data)
    {
        $email = sanitize_email((string) ($data['Email'] ?? ''));
        if (!is_email($email)) {
            return new WP_Error('mz_mc_missing_email', 'Email is required.');
        }

        $auth = mz_mc_get_auth_context();
        if (empty($auth['ok'])) {
            return new WP_Error('mz_mc_missing_auth', 'Mailchimp authentication is missing.');
        }

        $list_id = mz_mc_resolve_list_id();
        if ($list_id === '') {
            return new WP_Error('mz_mc_missing_list', 'Mailchimp audience/list ID is missing and no default audience was found for this Mailchimp connection.');
        }

        $member_hash = md5(strtolower(trim($email)));
        $existing_member = mz_mc_get_member($list_id, $member_hash);
        if (is_wp_error($existing_member)) {
            return $existing_member;
        }
        $member_exists = is_array($existing_member) && !empty($existing_member);
        $first = trim((string) ($data['FirstName'] ?? ''));
        $last  = trim((string) ($data['LastName'] ?? ''));
        $company = trim((string) ($data['Company'] ?? ''));
        $phone = preg_replace('/\D+/', '', (string) ($data['Phone'] ?? ''));
        $zip = trim((string) ($data['Zip'] ?? ''));

        $candidate_merge_fields = array_filter([
            'FNAME'   => $first,
            'LNAME'   => $last,
            'PHONE'   => $phone,
            'ZIP'     => $zip,
            'COMPANY' => $company,
        ], static fn($v) => $v !== '');
        $available_merge_tags = mz_mc_get_available_merge_tags($list_id);
        $merge_fields = [];
        if (!empty($available_merge_tags)) {
            foreach ($candidate_merge_fields as $tag => $value) {
                if (in_array($tag, $available_merge_tags, true)) {
                    $merge_fields[$tag] = $value;
                }
            }
        }

        $payload = [
            'email_address' => $email,
            'status_if_new' => 'subscribed',
            'status'        => 'subscribed',
        ];
        if (!empty($merge_fields)) {
            $payload['merge_fields'] = (object) $merge_fields;
        }

        $tags = [];
        if (!empty($data['LeadTags'])) {
            $tags = is_array($data['LeadTags']) ? $data['LeadTags'] : [(string) $data['LeadTags']];
        }
        $tags = array_values(array_unique(array_filter(array_map('sanitize_text_field', $tags))));

        $upsert_path = '/lists/' . rawurlencode($list_id) . '/members/' . $member_hash . '?skip_merge_validation=true';
        $upsert_url = '';
        $auth_base = trim((string) ($auth['base'] ?? ''));
        if ($auth_base !== '') {
            $upsert_url = rtrim($auth_base, '/') . '/' . ltrim($upsert_path, '/');
        }
        $tag_path = '/lists/' . rawurlencode($list_id) . '/members/' . $member_hash . '/tags';
        $tag_url = '';
        if ($auth_base !== '') {
            $tag_url = rtrim($auth_base, '/') . '/' . ltrim($tag_path, '/');
        }

        mz_mc_debug_log('upsert request', [
            'email' => $email,
            'list_id' => $list_id,
            'member_hash' => $member_hash,
            'member_exists' => $member_exists,
            'available_merge_tags' => $available_merge_tags,
            'candidate_merge_fields' => $candidate_merge_fields,
            'payload' => $payload,
        ]);

        if (function_exists('mzf_marketing_dry_run_enabled') && mzf_marketing_dry_run_enabled('mailchimp')) {
            $dry_requests = [[
                'method' => 'PUT',
                'url' => $upsert_url !== '' ? $upsert_url : $upsert_path,
                'payload' => $payload,
            ]];
            if (!empty($tags)) {
                $dry_requests[] = [
                    'method' => 'POST',
                    'url' => $tag_url !== '' ? $tag_url : $tag_path,
                    'payload' => [
                        'tags' => array_map(static fn($tag) => ['name' => $tag, 'status' => 'active'], $tags),
                    ],
                ];
            }

            mz_mc_debug_log('dry run: provider writes skipped', [
                'email' => $email,
                'list_id' => $list_id,
                'member_hash' => $member_hash,
                'requests' => $dry_requests,
            ]);

            return [
                'ok' => true,
                'provider' => 'mailchimp',
                'label' => 'Mailchimp',
                'action' => $member_exists ? 'update' : 'add',
                'email' => $email,
                'list_id' => $list_id,
                'member_hash' => $member_hash,
                'dry_run' => true,
                'dry_run_env' => function_exists('wp_get_environment_type') ? (string) wp_get_environment_type() : '',
                'dry_run_reason' => 'Marketing dry run is enabled for this environment.',
                'dry_run_requests' => $dry_requests,
            ];
        }

        $resp = mz_mc_api_request('PUT', $upsert_path, $payload);
        if (is_wp_error($resp)) {
            mz_mc_debug_log('upsert transport error', [
                'email' => $email,
                'list_id' => $list_id,
                'error' => $resp->get_error_message(),
            ]);
            return $resp;
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode((string) wp_remote_retrieve_body($resp), true);
        if (!is_array($body)) {
            $body = [];
        }

        mz_mc_debug_log('upsert response', [
            'email' => $email,
            'list_id' => $list_id,
            'code' => $code,
            'body' => $body,
        ]);

        if ($code < 200 || $code >= 300) {
            return new WP_Error('mz_mc_upsert_failed', 'Mailchimp upsert failed: ' . mz_mc_response_error_message($code, $body));
        }

        if (!empty($tags)) {
            $tag_payload = [
                'tags' => array_map(static fn($tag) => ['name' => $tag, 'status' => 'active'], $tags),
            ];
            mz_mc_debug_log('tag request', [
                'email' => $email,
                'list_id' => $list_id,
                'member_hash' => $member_hash,
                'payload' => $tag_payload,
            ]);
            $tag_resp = mz_mc_api_request('POST', $tag_path, $tag_payload);
            if (is_wp_error($tag_resp)) {
                mz_mc_debug_log('tag transport error', [
                    'email' => $email,
                    'list_id' => $list_id,
                    'error' => $tag_resp->get_error_message(),
                ]);
                return $tag_resp;
            }
            $tag_code = (int) wp_remote_retrieve_response_code($tag_resp);
            if ($tag_code < 200 || $tag_code >= 300) {
                $tag_body = json_decode((string) wp_remote_retrieve_body($tag_resp), true);
                if (!is_array($tag_body)) {
                    $tag_body = [];
                }
                mz_mc_debug_log('tag response', [
                    'email' => $email,
                    'list_id' => $list_id,
                    'code' => $tag_code,
                    'body' => $tag_body,
                ]);
                return new WP_Error('mz_mc_tag_failed', 'Mailchimp tag update failed: ' . mz_mc_response_error_message($tag_code, $tag_body));
            }
            $tag_body = json_decode((string) wp_remote_retrieve_body($tag_resp), true);
            if (!is_array($tag_body)) {
                $tag_body = [];
            }
            mz_mc_debug_log('tag response', [
                'email' => $email,
                'list_id' => $list_id,
                'code' => $tag_code,
                'body' => $tag_body,
            ]);
        }

        return [
            'ok' => true,
            'provider' => 'mailchimp',
            'label' => 'Mailchimp',
            'action' => $member_exists ? 'update' : 'add',
            'link_text' => $member_exists ? 'Updated in Mailchimp' : 'Added to Mailchimp',
            'email' => $email,
            'list_id' => $list_id,
            'member_hash' => $member_hash,
            'member_id' => trim((string) ($body['id'] ?? '')),
            'web_id' => trim((string) ($body['web_id'] ?? '')),
            'contact_url' => mz_mc_build_contact_url((string) ($auth['dc'] ?? ''), $body),
        ];
    }
}

if (!function_exists('mz_mc_is_platform_selected')) {
    function mz_mc_is_platform_selected(): bool
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

        return in_array($value, ['mailchimp', 'mail-chimp'], true);
    }
}

if (!function_exists('mz_mc_build_auth_url')) {
    function mz_mc_build_auth_url(): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => mz_mc_get_client_id(),
            'redirect_uri' => MAILCHIMP_REDIRECT_URI,
            'state' => wp_create_nonce('mz_mc_oauth_state'),
        ];
        return 'https://login.mailchimp.com/oauth2/authorize?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('mz_mc_fetch_oauth_metadata')) {
    function mz_mc_fetch_oauth_metadata(string $access_token): array
    {
        $resp = wp_remote_get('https://login.mailchimp.com/oauth2/metadata', [
            'headers' => [
                'Authorization' => 'OAuth ' . $access_token,
                'Accept' => 'application/json',
            ],
            'timeout' => 20,
        ]);
        if (is_wp_error($resp)) {
            return [];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode((string) wp_remote_retrieve_body($resp), true);
        if ($code < 200 || $code >= 300 || !is_array($body)) {
            return [];
        }
        return $body;
    }
}

if (!function_exists('mz_mc_process_oauth_callback')) {
    function mz_mc_process_oauth_callback(): void
    {
        $code  = isset($_GET['code']) ? sanitize_text_field((string) $_GET['code']) : '';
        $state = isset($_GET['state']) ? sanitize_text_field((string) $_GET['state']) : '';
        if ($code === '' || $state === '' || !wp_verify_nonce($state, 'mz_mc_oauth_state')) {
            wp_die('Invalid Mailchimp OAuth response.');
        }
        if (!mz_mc_has_oauth_app_keys()) {
            wp_die('Missing Mailchimp OAuth app credentials.');
        }

        $resp = wp_remote_post('https://login.mailchimp.com/oauth2/token', [
            'body' => [
                'grant_type' => 'authorization_code',
                'client_id' => mz_mc_get_client_id(),
                'client_secret' => mz_mc_get_client_secret(),
                'redirect_uri' => MAILCHIMP_REDIRECT_URI,
                'code' => $code,
            ],
            'timeout' => 20,
        ]);
        if (is_wp_error($resp)) {
            wp_die('Mailchimp token request failed.');
        }

        $http_code = (int) wp_remote_retrieve_response_code($resp);
        $body = json_decode((string) wp_remote_retrieve_body($resp), true);
        $access_token = is_array($body) ? trim((string) ($body['access_token'] ?? '')) : '';
        if ($http_code < 200 || $http_code >= 300 || $access_token === '') {
            wp_die('Mailchimp token exchange failed.');
        }

        $meta = mz_mc_fetch_oauth_metadata($access_token);
        $oauth = [
            'access_token' => $access_token,
            'dc' => trim((string) ($meta['dc'] ?? '')),
            'api_endpoint' => trim((string) ($meta['api_endpoint'] ?? '')),
            'accountname' => trim((string) ($meta['accountname'] ?? '')),
            'login' => trim((string) ($meta['login'] ?? '')),
            'obtained_at' => time(),
        ];
        update_option('mz_mc_oauth', $oauth, false);

        wp_safe_redirect(add_query_arg('mc_connected', '1', admin_url('options-general.php?page=crm')));
        exit;
    }
}

add_action('template_redirect', function () {
    $uri = wp_parse_url(MAILCHIMP_REDIRECT_URI, PHP_URL_PATH);
    $req = isset($_SERVER['REQUEST_URI'])
        ? wp_parse_url((is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], PHP_URL_PATH)
        : '';
    if (rtrim((string) $req, '/') === rtrim((string) $uri, '/')) {
        mz_mc_process_oauth_callback();
        exit;
    }
});

add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!isset($_GET['page']) || $_GET['page'] !== 'crm') {
        return;
    }
    if (!mz_mc_is_platform_selected()) {
        return;
    }

    echo '<div class="notice notice-info"><p><strong>Mailchimp Integration</strong></p>';
    if (!mz_mc_has_oauth_app_keys()) {
        echo '<p>OAuth app credentials are missing. Set Client ID and Client Secret in CRM Integration (Mailchimp group), or fallback constants <code>MAILCHIMP_CLIENT_ID</code> and <code>MAILCHIMP_CLIENT_SECRET</code>.</p>';
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
    echo '<div class="mz-oauth-row">';

    $oauth = mz_mc_get_oauth_data();
    $token = trim((string) ($oauth['access_token'] ?? ''));
    if ($token === '') {
        echo '<a class="button button-primary" href="' . esc_url(mz_mc_build_auth_url()) . '">Connect Mailchimp</a>';
        echo '<span class="mz-oauth-status">Refresh token not available until you connect.</span>';
        echo '</div>';
        echo '<p style="margin:0 0 10px 0;"><strong>Redirect URI:</strong> <code>' . esc_html((string) MAILCHIMP_REDIRECT_URI) . '</code></p>';
        echo '</div>';
        return;
    }
    echo '<button type="button" class="button button-primary mz-oauth-connected" disabled aria-disabled="true">';
    echo '<span class="dashicons dashicons-yes-alt"></span>';
    echo 'Connected to Mailchimp!';
    echo '</button>';
    echo '<span class="mz-oauth-status">Refresh token saved. Access token auto-refreshes.</span>';
    echo '</div>';
    echo '<p style="margin:0 0 10px 0;"><strong>Redirect URI:</strong> <code>' . esc_html((string) MAILCHIMP_REDIRECT_URI) . '</code></p>';
    echo '</div>';
});

if (!function_exists('mz_mc_maybe_disconnect_when_platform_changes')) {
    function mz_mc_maybe_disconnect_when_platform_changes($post_id): void
    {
        if (!in_array((string) $post_id, ['options', 'option'], true)) {
            return;
        }
        if (mz_mc_is_platform_selected()) {
            return;
        }
        if (get_option('mz_mc_oauth', null) !== null) {
            delete_option('mz_mc_oauth');
        }
    }
}

add_action('acf/save_post', 'mz_mc_maybe_disconnect_when_platform_changes', 30);

add_action('init', function () {
    if (!isset($_GET['mc_test']) || $_GET['mc_test'] !== '1') {
        return;
    }
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('Unauthorized', 'Unauthorized', 403);
    }

    $email = isset($_GET['email']) ? sanitize_email((string) $_GET['email']) : '';
    if (!$email || !is_email($email)) {
        wp_send_json_error(['message' => 'Valid ?email= is required'], 400);
    }

    $res = mz_mc_upsert_contact([
        'Email'        => $email,
        'FirstName'    => isset($_GET['first']) ? sanitize_text_field((string) $_GET['first']) : '',
        'LastName'     => isset($_GET['last']) ? sanitize_text_field((string) $_GET['last']) : '',
        'Phone'        => isset($_GET['phone']) ? preg_replace('/\D+/', '', (string) $_GET['phone']) : '',
        'Zip'      => isset($_GET['zip']) ? sanitize_text_field((string) $_GET['zip']) : '',
        'Company'      => isset($_GET['company']) ? sanitize_text_field((string) $_GET['company']) : '',
        'LeadTags'     => !empty($_GET['tags'])
            ? array_values(array_filter(array_map('trim', explode(',', (string) $_GET['tags']))))
            : ['Website Lead', 'debug'],
    ]);

    if (is_wp_error($res)) {
        wp_send_json_error(['result' => $res->get_error_message()], 500);
    }
    wp_send_json_success(['result' => 'ok'], 200);
});
