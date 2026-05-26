<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MAILCHIMP_API_KEY')) {
    define('MAILCHIMP_API_KEY', '');
}
if (!defined('MZ_MC_SUBMISSION_BACKFILL_OPTION')) {
    define('MZ_MC_SUBMISSION_BACKFILL_OPTION', 'mz_mc_submission_backfill');
}
if (!defined('MZ_MC_SUBMISSION_BACKFILL_NOTICE_TRANSIENT')) {
    define('MZ_MC_SUBMISSION_BACKFILL_NOTICE_TRANSIENT', 'mz_mc_submission_backfill_notice');
}
if (!defined('MZ_MC_SUBMISSION_BACKFILL_HOOK')) {
    define('MZ_MC_SUBMISSION_BACKFILL_HOOK', 'mz_mc_submission_backfill_batch');
}

if (!function_exists('mz_mc_get_api_key')) {
    function mz_mc_get_api_key(): string
    {
        if (function_exists('mzf_crm_api_key')) {
            $crm_api_key = mzf_crm_api_key('mailchimp');
            if ($crm_api_key !== '') {
                return $crm_api_key;
            }
        }

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
            $api_key = trim((string) ($nested['api'] ?? ''));
            if ($api_key !== '') {
                return $api_key;
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
            $api_key = trim((string) ($direct_group['api'] ?? ''));
            if ($api_key !== '') {
                return $api_key;
            }
        }

        $candidates = [
            trim((string) ($crm['api_mailchimp'] ?? '')),
            trim((string) get_option('options_crm_api_mailchimp', '')),
            trim((string) get_option('options_crm_mailchimp_api', '')),
            trim((string) get_option('options_mailchimp_api', '')),
            trim((string) get_option('options_mz_mc_api_key', '')),
            trim((string) get_option('options_mailchimp_api_key', '')),
            trim((string) get_option('mz_mc_api_key', '')),
            trim((string) get_option('mailchimp_api_key', '')),
            trim((string) get_option('mc_api_key', '')),
            trim((string) (defined('MAILCHIMP_API_KEY') ? MAILCHIMP_API_KEY : '')),
        ];
        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}

if (!function_exists('mz_mc_get_api_dc')) {
    function mz_mc_get_api_dc(): string
    {
        $api_key = mz_mc_get_api_key();
        if ($api_key !== '' && preg_match('/-([a-z0-9]+)$/i', $api_key, $matches)) {
            return strtolower((string) $matches[1]);
        }

        return '';
    }
}

if (!function_exists('mz_mc_get_api_base')) {
    function mz_mc_get_api_base(): string
    {
        $dc = mz_mc_get_api_dc();
        if ($dc === '') {
            return '';
        }

        return 'https://' . $dc . '.api.mailchimp.com/3.0';
    }
}

if (!function_exists('mz_mc_has_api_key')) {
    function mz_mc_has_api_key(): bool
    {
        return mz_mc_get_api_key() !== '';
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
        return !empty(mz_mc_get_auth_context()['ok']);
    }
}

if (!function_exists('mz_mc_get_auth_context')) {
    function mz_mc_get_auth_context(): array
    {
        $api_key = mz_mc_get_api_key();
        $api_base = mz_mc_get_api_base();
        if ($api_key !== '' && $api_base !== '') {
            return [
                'ok' => true,
                'mode' => 'api_key',
                'api_key' => $api_key,
                'base' => $api_base,
                'dc' => mz_mc_get_api_dc(),
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
        $cache_material = (string) ($auth['api_key'] ?? '');
        if ($cache_material === '') {
            return '';
        }

        $cache_key = md5('api|' . $cache_material);
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
        $cache_material = (string) ($auth['api_key'] ?? '');
        if ($cache_material === '') {
            return [];
        }

        $cache_key = md5('api|' . $cache_material . '|' . $list_id);
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
            return new WP_Error('mz_mc_missing_auth', 'Mailchimp API key is missing or invalid.');
        }

        $base = (string) ($auth['base'] ?? '');
        if ($base === '') {
            return new WP_Error('mz_mc_missing_base', 'Mailchimp API base URL could not be derived from the API key.');
        }
        $url  = rtrim($base, '/') . '/' . ltrim($path, '/');

        $api_key = (string) ($auth['api_key'] ?? '');
        if ($api_key === '') {
            return new WP_Error('mz_mc_missing_api_key', 'Mailchimp API key is missing.');
        }

        $args = [
            'method'  => strtoupper($method),
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode('meza:' . $api_key),
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
            return new WP_Error('mz_mc_missing_auth', 'Mailchimp API key is missing or invalid.');
        }

        $list_id = mz_mc_resolve_list_id();
        if ($list_id === '') {
            return new WP_Error('mz_mc_missing_list', 'Mailchimp audience/list ID is missing and no default audience was found for this Mailchimp configuration.');
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

if (!function_exists('mz_mc_clear_legacy_oauth_state')) {
    function mz_mc_clear_legacy_oauth_state(): void
    {
        if (get_option('mz_mc_oauth', null) !== null) {
            delete_option('mz_mc_oauth');
        }
    }
}

if (!function_exists('mz_mc_set_submission_backfill_notice')) {
    function mz_mc_set_submission_backfill_notice(string $message, string $type = 'info'): void
    {
        $type = sanitize_key($type);
        if (!in_array($type, ['success', 'info', 'warning', 'error'], true)) {
            $type = 'info';
        }

        set_transient(MZ_MC_SUBMISSION_BACKFILL_NOTICE_TRANSIENT, [
            'message' => sanitize_text_field($message),
            'type' => $type,
        ], HOUR_IN_SECONDS);
    }
}

if (!function_exists('mz_mc_get_submission_backfill_state')) {
    function mz_mc_get_submission_backfill_state(): array
    {
        $state = get_option(MZ_MC_SUBMISSION_BACKFILL_OPTION);
        return is_array($state) ? $state : [];
    }
}

if (!function_exists('mz_mc_is_crm_options_save_request')) {
    function mz_mc_is_crm_options_save_request($post_id): bool
    {
        if (!in_array((string) $post_id, ['options', 'option'], true)) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        return $page === 'crm';
    }
}

if (!function_exists('mz_mc_submission_has_live_link')) {
    function mz_mc_submission_has_live_link(int $submission_id): bool
    {
        $marketing_sync = get_post_meta($submission_id, '_mzf_marketing_sync', true);
        if (!is_array($marketing_sync)) {
            return false;
        }

        $provider = strtolower(trim((string) ($marketing_sync['provider'] ?? '')));
        $sync_ok = !empty($marketing_sync['ok']);
        $contact_url = trim((string) ($marketing_sync['contact_url'] ?? ''));
        $dry_run = !empty($marketing_sync['dry_run']);

        if ($provider !== 'mailchimp' || !$sync_ok) {
            return false;
        }

        return ($contact_url !== '' || $dry_run);
    }
}

if (!function_exists('mz_mc_submission_should_backfill')) {
    function mz_mc_submission_should_backfill(int $submission_id): bool
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

        if (mz_mc_submission_has_live_link($submission_id)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('mz_mc_build_submission_payload')) {
    function mz_mc_build_submission_payload(int $submission_id): array
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

if (!function_exists('mz_mc_store_submission_sync_result')) {
    function mz_mc_store_submission_sync_result(int $submission_id, array $marketing_sync): void
    {
        $normalized_sync = function_exists('mzf_log_normalize_value')
            ? mzf_log_normalize_value($marketing_sync)
            : $marketing_sync;

        update_post_meta($submission_id, '_mzf_marketing_sync', $normalized_sync);
        update_post_meta($submission_id, '_mzf_crm_platform', 'mailchimp');
        update_post_meta($submission_id, '_mzf_crm_platform_label', 'Mailchimp');
        update_post_meta($submission_id, '_mzf_newsletter_opt_in', '1');
    }
}

if (!function_exists('mz_mc_backfill_submission')) {
    function mz_mc_backfill_submission(int $submission_id): array
    {
        if (!mz_mc_submission_should_backfill($submission_id)) {
            return ['status' => 'skipped'];
        }

        $payload = mz_mc_build_submission_payload($submission_id);
        $result = mz_mc_upsert_contact($payload);
        if (is_wp_error($result)) {
            $sync_result = [
                'ok' => false,
                'provider' => 'mailchimp',
                'label' => 'Mailchimp',
                'error' => $result->get_error_message(),
            ];
            mz_mc_store_submission_sync_result($submission_id, $sync_result);
            return [
                'status' => 'failed',
                'message' => $result->get_error_message(),
            ];
        }

        $sync_result = is_array($result)
            ? $result
            : ['ok' => true, 'provider' => 'mailchimp', 'label' => 'Mailchimp'];
        mz_mc_store_submission_sync_result($submission_id, $sync_result);

        return ['status' => 'synced'];
    }
}

if (!function_exists('mz_mc_collect_submission_backfill_ids')) {
    function mz_mc_collect_submission_backfill_ids(): array
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
            if ($submission_id > 0 && mz_mc_submission_should_backfill($submission_id)) {
                $ids[] = $submission_id;
            }
        }

        return $ids;
    }
}

if (!function_exists('mz_mc_schedule_submission_backfill_event')) {
    function mz_mc_schedule_submission_backfill_event(): void
    {
        if (!wp_next_scheduled(MZ_MC_SUBMISSION_BACKFILL_HOOK)) {
            wp_schedule_single_event(time() + 5, MZ_MC_SUBMISSION_BACKFILL_HOOK);
        }
    }
}

if (!function_exists('mz_mc_queue_submission_backfill')) {
    function mz_mc_queue_submission_backfill(string $trigger = 'manual'): array
    {
        if (!mz_mc_is_platform_selected() || !mz_mc_is_configured()) {
            return ['queued' => false, 'count' => 0];
        }

        $pending_ids = mz_mc_collect_submission_backfill_ids();
        if (empty($pending_ids)) {
            delete_option(MZ_MC_SUBMISSION_BACKFILL_OPTION);
            mz_mc_set_submission_backfill_notice('Mailchimp submission backfill is already up to date.', 'success');
            return ['queued' => false, 'count' => 0];
        }

        update_option(MZ_MC_SUBMISSION_BACKFILL_OPTION, [
            'provider' => 'mailchimp',
            'trigger' => sanitize_key($trigger),
            'pending_ids' => array_values($pending_ids),
            'total' => count($pending_ids),
            'success_count' => 0,
            'failure_count' => 0,
            'skipped_count' => 0,
            'updated_at' => time(),
        ], false);

        mz_mc_schedule_submission_backfill_event();
        mz_mc_set_submission_backfill_notice(
            sprintf('Mailchimp submission backfill queued for %d entr%s.', count($pending_ids), count($pending_ids) === 1 ? 'y' : 'ies'),
            'info'
        );

        return ['queued' => true, 'count' => count($pending_ids)];
    }
}

if (!function_exists('mz_mc_process_submission_backfill_batch')) {
    function mz_mc_process_submission_backfill_batch(): void
    {
        $state = mz_mc_get_submission_backfill_state();
        if (empty($state)) {
            return;
        }

        if (!mz_mc_is_platform_selected() || !mz_mc_is_configured()) {
            delete_option(MZ_MC_SUBMISSION_BACKFILL_OPTION);
            mz_mc_set_submission_backfill_notice('Mailchimp submission backfill stopped because Mailchimp is no longer configured.', 'warning');
            return;
        }

        $pending_ids = array_values(array_filter(array_map('absint', (array) ($state['pending_ids'] ?? []))));
        if (empty($pending_ids)) {
            delete_option(MZ_MC_SUBMISSION_BACKFILL_OPTION);
            return;
        }

        $success_count = (int) ($state['success_count'] ?? 0);
        $failure_count = (int) ($state['failure_count'] ?? 0);
        $skipped_count = (int) ($state['skipped_count'] ?? 0);
        $batch_ids = array_splice($pending_ids, 0, 20);

        foreach ($batch_ids as $submission_id) {
            $result = mz_mc_backfill_submission((int) $submission_id);
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
            update_option(MZ_MC_SUBMISSION_BACKFILL_OPTION, $state, false);
            mz_mc_schedule_submission_backfill_event();
            return;
        }

        delete_option(MZ_MC_SUBMISSION_BACKFILL_OPTION);
        mz_mc_set_submission_backfill_notice(
            sprintf(
                'Mailchimp submission backfill completed. %d synced, %d skipped, %d failed.',
                $success_count,
                $skipped_count,
                $failure_count
            ),
            $failure_count > 0 ? 'warning' : 'success'
        );
    }
}

add_action(MZ_MC_SUBMISSION_BACKFILL_HOOK, 'mz_mc_process_submission_backfill_batch');

add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!isset($_GET['page']) || $_GET['page'] !== 'crm') {
        return;
    }

    $notice = get_transient(MZ_MC_SUBMISSION_BACKFILL_NOTICE_TRANSIENT);
    if (!is_array($notice) || empty($notice['message'])) {
        return;
    }

    delete_transient(MZ_MC_SUBMISSION_BACKFILL_NOTICE_TRANSIENT);
    $type = sanitize_key((string) ($notice['type'] ?? 'info'));
    if (!in_array($type, ['success', 'info', 'warning', 'error'], true)) {
        $type = 'info';
    }

    echo '<div class="notice notice-' . esc_attr($type) . '"><p>' . esc_html((string) $notice['message']) . '</p></div>';
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
    $api_key = mz_mc_get_api_key();
    if ($api_key === '') {
        echo '<p>Mailchimp settings are missing. Set API Key and List ID in CRM Integration (Mailchimp group), or fallback constants <code>MAILCHIMP_API_KEY</code> and <code>MAILCHIMP_LIST_ID</code>.</p>';
        echo '</div>';
        return;
    }

    $list_id = mz_mc_get_list_id();
    if ($list_id === '') {
        echo '<p>Mailchimp List ID is missing. Set API Key and List ID in CRM Integration (Mailchimp group), or fallback constants <code>MAILCHIMP_API_KEY</code> and <code>MAILCHIMP_LIST_ID</code>.</p>';
        echo '</div>';
        return;
    }

    $auth = mz_mc_get_auth_context();
    if (empty($auth['ok'])) {
        echo '<p>Mailchimp API Key is saved, but it appears invalid. Mailchimp API keys should end with a data center suffix like <code>-us6</code>. You can set the API Key and List ID in CRM Integration (Mailchimp group), or fallback constants <code>MAILCHIMP_API_KEY</code> and <code>MAILCHIMP_LIST_ID</code>.</p>';
        echo '</div>';
        return;
    }

    echo '<p>Mailchimp is configured with the saved API Key and List ID <code>' . esc_html($list_id) . '</code>. You can also provide these via <code>MAILCHIMP_API_KEY</code> and <code>MAILCHIMP_LIST_ID</code> in <code>wp-config.php</code>.</p>';
    echo '</div>';
});

if (!function_exists('mz_mc_maybe_clear_legacy_oauth_state_on_save')) {
    function mz_mc_maybe_clear_legacy_oauth_state_on_save($post_id): void
    {
        if (!in_array((string) $post_id, ['options', 'option'], true)) {
            return;
        }

        if (!mz_mc_is_platform_selected() || mz_mc_is_crm_options_save_request($post_id)) {
            mz_mc_clear_legacy_oauth_state();
        }
    }
}

add_action('acf/save_post', 'mz_mc_maybe_clear_legacy_oauth_state_on_save', 30);

if (!function_exists('mz_mc_maybe_queue_submission_backfill_on_crm_save')) {
    function mz_mc_maybe_queue_submission_backfill_on_crm_save($post_id): void
    {
        if (!mz_mc_is_crm_options_save_request($post_id)) {
            return;
        }

        mz_mc_queue_submission_backfill('crm_settings_save');
    }
}

add_action('acf/save_post', 'mz_mc_maybe_queue_submission_backfill_on_crm_save', 40);

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
