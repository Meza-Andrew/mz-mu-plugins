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
        return trim((string) get_option('mz_mc_api_key', ''));
    }
}

if (!function_exists('mz_mc_get_list_id')) {
    function mz_mc_get_list_id(): string
    {
        if (defined('MAILCHIMP_LIST_ID') && MAILCHIMP_LIST_ID) {
            return trim((string) MAILCHIMP_LIST_ID);
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
        return mz_mc_get_api_key() !== '' && mz_mc_get_list_id() !== '';
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

        $list_id = mz_mc_get_list_id();
        if ($list_id === '') {
            return new WP_Error('mz_mc_missing_list', 'Mailchimp list ID is missing.');
        }

        $member_hash = md5(strtolower(trim($email)));
        $first = trim((string) ($data['FirstName'] ?? ''));
        $last  = trim((string) ($data['LastName'] ?? ''));
        $company = trim((string) ($data['Company'] ?? ($data['Organization'] ?? '')));
        $phone = preg_replace('/\D+/', '', (string) ($data['Phone'] ?? ''));
        $zip = trim((string) ($data['ZipCode'] ?? ''));

        $merge_fields = array_filter([
            'FNAME'   => $first,
            'LNAME'   => $last,
            'PHONE'   => $phone,
            'ZIP'     => $zip,
            'COMPANY' => $company,
        ], static fn($v) => $v !== '');

        $payload = [
            'email_address' => $email,
            'status_if_new' => 'subscribed',
            'status'        => 'subscribed',
            'merge_fields'  => (object) $merge_fields,
        ];

        $resp = mz_mc_api_request('PUT', '/lists/' . rawurlencode($list_id) . '/members/' . $member_hash, $payload);
        if (is_wp_error($resp)) {
            return $resp;
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('mz_mc_upsert_failed', 'Mailchimp upsert failed: HTTP ' . $code);
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
            $tag_resp = mz_mc_api_request('POST', '/lists/' . rawurlencode($list_id) . '/members/' . $member_hash . '/tags', $tag_payload);
            if (is_wp_error($tag_resp)) {
                return $tag_resp;
            }
            $tag_code = (int) wp_remote_retrieve_response_code($tag_resp);
            if ($tag_code < 200 || $tag_code >= 300) {
                return new WP_Error('mz_mc_tag_failed', 'Mailchimp tag update failed: HTTP ' . $tag_code);
            }
        }

        return true;
    }
}

add_action('admin_menu', function () {
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
        'ZipCode'      => isset($_GET['zip']) ? sanitize_text_field((string) $_GET['zip']) : '',
        'Organization' => isset($_GET['company']) ? sanitize_text_field((string) $_GET['company']) : '',
        'LeadTags'     => !empty($_GET['tags'])
            ? array_values(array_filter(array_map('trim', explode(',', (string) $_GET['tags']))))
            : ['Website Lead', 'debug'],
    ]);

    if (is_wp_error($res)) {
        wp_send_json_error(['result' => $res->get_error_message()], 500);
    }
    wp_send_json_success(['result' => 'ok'], 200);
});
