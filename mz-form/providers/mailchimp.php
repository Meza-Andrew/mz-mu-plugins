<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mz_mc_get_api_key')) {
    function mz_mc_get_api_key(): string
    {
        if (defined('MAILCHIMP_API_KEY') && MAILCHIMP_API_KEY) {
            return trim((string) MAILCHIMP_API_KEY);
        }
        if (function_exists('mzf_crm_api_key')) {
            $crm_key = mzf_crm_api_key('mailchimp');
            if ($crm_key !== '') {
                return $crm_key;
            }
        }
        return trim((string) get_option('mz_mc_api_key', ''));
    }
}

if (!function_exists('mz_mc_has_wp_config_keys')) {
    function mz_mc_has_wp_config_keys(): bool
    {
        $api_key_defined = defined('MAILCHIMP_API_KEY') && trim((string) MAILCHIMP_API_KEY) !== '';
        $list_id_defined = defined('MAILCHIMP_LIST_ID') && trim((string) MAILCHIMP_LIST_ID) !== '';
        return $api_key_defined && $list_id_defined;
    }
}

if (!function_exists('mz_mc_get_list_id')) {
    function mz_mc_get_list_id(): string
    {
        if (defined('MAILCHIMP_LIST_ID') && MAILCHIMP_LIST_ID) {
            return trim((string) MAILCHIMP_LIST_ID);
        }
        if (function_exists('mzf_crm_list_id')) {
            $crm_list_id = mzf_crm_list_id('mailchimp');
            if ($crm_list_id !== '') {
                return $crm_list_id;
            }
        }
        return trim((string) get_option('mz_mc_list_id', ''));
    }
}

if (!function_exists('mz_mc_get_dc_from_api_key')) {
    function mz_mc_get_dc_from_api_key(string $api_key): string
    {
        $dash_pos = strrpos($api_key, '-');
        if ($dash_pos === false) {
            return '';
        }
        return trim(substr($api_key, $dash_pos + 1));
    }
}

if (!function_exists('mz_mc_is_configured')) {
    function mz_mc_is_configured(): bool
    {
        return mz_mc_get_api_key() !== '';
    }
}

if (!function_exists('mz_mc_get_default_list_id')) {
    function mz_mc_get_default_list_id(): string
    {
        static $resolved = [];

        $api_key = mz_mc_get_api_key();
        if ($api_key === '') {
            return '';
        }

        $cache_key = md5($api_key);
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

        $api_key = mz_mc_get_api_key();
        if ($api_key === '') {
            return [];
        }

        $cache_key = md5($api_key . '|' . $list_id);
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
    function mz_mc_build_contact_url(string $api_key, array $member): string
    {
        $dc = mz_mc_get_dc_from_api_key($api_key);
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
        $api_key = mz_mc_get_api_key();
        if ($api_key === '') {
            return new WP_Error('mz_mc_missing_key', 'Mailchimp API key is missing.');
        }

        $dc = mz_mc_get_dc_from_api_key($api_key);
        if ($dc === '') {
            return new WP_Error('mz_mc_bad_key', 'Mailchimp API key appears invalid.');
        }

        $base = 'https://' . $dc . '.api.mailchimp.com/3.0';
        $url  = rtrim($base, '/') . '/' . ltrim($path, '/');

        $args = [
            'method'  => strtoupper($method),
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode('anystring:' . $api_key),
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

        $api_key = mz_mc_get_api_key();
        if ($api_key === '') {
            return new WP_Error('mz_mc_missing_key', 'Mailchimp API key is missing.');
        }

        $list_id = mz_mc_resolve_list_id();
        if ($list_id === '') {
            return new WP_Error('mz_mc_missing_list', 'Mailchimp audience/list ID is missing and no default audience was found for this API key.');
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

        mz_mc_debug_log('upsert request', [
            'email' => $email,
            'list_id' => $list_id,
            'member_hash' => $member_hash,
            'member_exists' => $member_exists,
            'available_merge_tags' => $available_merge_tags,
            'candidate_merge_fields' => $candidate_merge_fields,
            'payload' => $payload,
        ]);

        $resp = mz_mc_api_request('PUT', '/lists/' . rawurlencode($list_id) . '/members/' . $member_hash . '?skip_merge_validation=true', $payload);
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

        $tags = [];
        if (!empty($data['LeadTags'])) {
            $tags = is_array($data['LeadTags']) ? $data['LeadTags'] : [(string) $data['LeadTags']];
        }
        $tags = array_values(array_unique(array_filter(array_map('sanitize_text_field', $tags))));

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
            $tag_resp = mz_mc_api_request('POST', '/lists/' . rawurlencode($list_id) . '/members/' . $member_hash . '/tags', $tag_payload);
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
            'contact_url' => mz_mc_build_contact_url($api_key, $body),
        ];
    }
}

add_action('admin_menu', function () {
    // Only expose this menu when credentials are explicitly provided via wp-config constants.
    if (!mz_mc_has_wp_config_keys()) return;

    add_submenu_page(
        'options-general.php',
        'Mailchimp',
        'Mailchimp',
        'manage_options',
        'mz-mailchimp',
        function () {
            if (!current_user_can('manage_options')) {
                return;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mz_mc_save'])) {
                check_admin_referer('mz_mc_save_settings');
                $api_key = sanitize_text_field((string) ($_POST['mz_mc_api_key'] ?? ''));
                $list_id = sanitize_text_field((string) ($_POST['mz_mc_list_id'] ?? ''));
                update_option('mz_mc_api_key', $api_key, false);
                update_option('mz_mc_list_id', $list_id, false);
                echo '<div class="notice notice-success"><p>Mailchimp settings saved.</p></div>';
            }

            $api_key = esc_attr(mz_mc_get_api_key());
            $list_id = esc_attr(mz_mc_get_list_id());
            $lookup = null;
            if (isset($_GET['mz_mc_lookup']) && $_GET['mz_mc_lookup'] === '1') {
                $lookup = mz_mc_list_search();
            }

            echo '<div class="wrap"><h1>Mailchimp</h1>';
            echo '<p>Set credentials here or via constants: <code>MAILCHIMP_API_KEY</code>, <code>MAILCHIMP_LIST_ID</code>.</p>';
            echo '<form method="post">';
            wp_nonce_field('mz_mc_save_settings');
            echo '<table class="form-table"><tbody>';
            echo '<tr><th scope="row"><label for="mz_mc_api_key">API Key</label></th><td><input id="mz_mc_api_key" name="mz_mc_api_key" type="text" class="regular-text" value="' . $api_key . '" /></td></tr>';
            echo '<tr><th scope="row"><label for="mz_mc_list_id">List ID</label></th><td><input id="mz_mc_list_id" name="mz_mc_list_id" type="text" class="regular-text" value="' . $list_id . '" /></td></tr>';
            echo '</tbody></table>';
            echo '<p><button class="button button-primary" type="submit" name="mz_mc_save" value="1">Save</button> ';
            echo '<a class="button" href="' . esc_url(add_query_arg('mz_mc_lookup', '1')) . '">Search Lists</a></p>';
            echo '</form>';

            if (is_array($lookup)) {
                if (!$lookup['ok']) {
                    echo '<div class="notice notice-error"><p>List lookup failed: ' . esc_html((string) $lookup['error']) . '</p></div>';
                } else {
                    echo '<h2>Available Lists</h2>';
                    if (empty($lookup['lists'])) {
                        echo '<p>No lists found.</p>';
                    } else {
                        echo '<ul>';
                        foreach ($lookup['lists'] as $list) {
                            echo '<li><code>' . esc_html($list['id']) . '</code> - ' . esc_html($list['name']) . '</li>';
                        }
                        echo '</ul>';
                    }
                }
            }
            echo '</div>';
        }
    );
});

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
