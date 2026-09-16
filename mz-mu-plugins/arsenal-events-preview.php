<?php

/**
 * Plugin Name: Arsenal Events Draft Preview
 * Description: Capability-checked, short-lived headless preview assertions for Arsenal Events.
 */

if (!defined('ABSPATH') && !defined('ARSENAL_EVENTS_PREVIEW_TESTS')) {
    exit;
}

function arsenal_events_preview_config_value(string $key, $default = '')
{
    $constant = 'ARSENAL_EVENTS_PREVIEW_' . strtoupper($key);
    if (defined($constant)) {
        return constant($constant);
    }

    $env = getenv($constant);
    if ($env !== false && $env !== '') {
        return $env;
    }

    return $default;
}

function arsenal_events_preview_config(): array
{
    $frontend_origin = rtrim((string) arsenal_events_preview_config_value('frontend_origin', ''), '/');
    $audience = (string) arsenal_events_preview_config_value('audience', $frontend_origin);

    $config = [
        'frontend_origin' => $frontend_origin,
        'audience' => $audience,
        'signing_secret' => (string) arsenal_events_preview_config_value('signing_secret', ''),
        'ttl_seconds' => (int) arsenal_events_preview_config_value('ttl_seconds', 300),
    ];
    $config['ttl_seconds'] = max(30, min(900, $config['ttl_seconds']));

    if (function_exists('apply_filters')) {
        $config = apply_filters('arsenal_events_preview_config', $config);
    }

    return is_array($config) ? $config : [];
}

function arsenal_events_preview_is_configured(array $config): bool
{
    return !empty($config['frontend_origin'])
        && !empty($config['audience'])
        && !empty($config['signing_secret']);
}

function arsenal_events_preview_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function arsenal_events_preview_base64url_decode(string $value): ?string
{
    $decoded = base64_decode(strtr($value, '-_', '+/'), true);

    return $decoded === false ? null : $decoded;
}

function arsenal_events_preview_json_encode(array $payload): string
{
    return function_exists('wp_json_encode') ? wp_json_encode($payload) : (string) json_encode($payload);
}

function arsenal_events_preview_random_token(int $bytes = 32): string
{
    if (function_exists('random_bytes')) {
        return arsenal_events_preview_base64url_encode(random_bytes($bytes));
    }

    return arsenal_events_preview_base64url_encode((string) uniqid('', true));
}

function arsenal_events_preview_sign_payload(array $payload, string $secret): string
{
    $body = arsenal_events_preview_base64url_encode(arsenal_events_preview_json_encode($payload));
    $signature = hash_hmac('sha256', $body, $secret, true);

    return $body . '.' . arsenal_events_preview_base64url_encode($signature);
}

function arsenal_events_preview_verify_assertion(string $assertion, string $secret, ?int $now = null): array
{
    $parts = explode('.', $assertion);
    if (count($parts) !== 2 || $secret === '') {
        return ['ok' => false, 'error' => 'malformed'];
    }

    [$body, $signature] = $parts;
    $expected = arsenal_events_preview_base64url_encode(hash_hmac('sha256', $body, $secret, true));
    if (!hash_equals($expected, $signature)) {
        return ['ok' => false, 'error' => 'bad_signature'];
    }

    $json = arsenal_events_preview_base64url_decode($body);
    $payload = is_string($json) ? json_decode($json, true) : null;
    if (!is_array($payload)) {
        return ['ok' => false, 'error' => 'bad_payload'];
    }

    $now = $now ?? time();
    if ((int) ($payload['exp'] ?? 0) < $now) {
        return ['ok' => false, 'error' => 'expired'];
    }

    return ['ok' => true, 'payload' => $payload];
}

function arsenal_events_preview_allowed_post_type(string $post_type): bool
{
    return in_array($post_type, ['page', 'race', 'resource'], true);
}

function arsenal_events_preview_route_for_post($post): string
{
    $post_type = (string) ($post->post_type ?? '');
    $slug = trim((string) ($post->post_name ?? ''), '/');

    if ($post_type === 'page') {
        return $slug === '' || $slug === 'home' ? '/' : '/' . $slug;
    }

    if ($post_type === 'race') {
        return '/races/' . $slug;
    }

    if ($post_type === 'resource') {
        return '/resources/' . $slug;
    }

    return '';
}

function arsenal_events_preview_assertion_payload($post, int $revision_id, array $config, ?int $now = null): array
{
    $now = $now ?? time();
    $route = arsenal_events_preview_route_for_post($post);

    return [
        'iss' => function_exists('home_url') ? home_url('/') : '',
        'aud' => (string) ($config['audience'] ?? ''),
        'site' => function_exists('home_url') ? home_url('/') : '',
        'post_id' => (int) ($post->ID ?? 0),
        'revision_id' => $revision_id,
        'post_type' => (string) ($post->post_type ?? ''),
        'slug' => (string) ($post->post_name ?? ''),
        'route' => $route,
        'iat' => $now,
        'exp' => $now + (int) ($config['ttl_seconds'] ?? 300),
        'jti' => arsenal_events_preview_random_token(16),
    ];
}

function arsenal_events_preview_create_assertion($post, int $revision_id, array $config, ?int $now = null): string
{
    return arsenal_events_preview_sign_payload(
        arsenal_events_preview_assertion_payload($post, $revision_id, $config, $now),
        (string) ($config['signing_secret'] ?? '')
    );
}

function arsenal_events_preview_code_transient_key(string $code): string
{
    return 'arsenal_events_preview_' . hash('sha256', $code);
}

function arsenal_events_preview_store_assertion(string $assertion, int $ttl_seconds): string
{
    $code = arsenal_events_preview_random_token(32);
    if (function_exists('set_transient')) {
        set_transient(arsenal_events_preview_code_transient_key($code), $assertion, $ttl_seconds);
    }

    return $code;
}

function arsenal_events_preview_exchange_code(string $code): ?string
{
    $key = arsenal_events_preview_code_transient_key($code);
    $assertion = function_exists('get_transient') ? get_transient($key) : false;
    if (function_exists('delete_transient')) {
        delete_transient($key);
    }

    return is_string($assertion) && $assertion !== '' ? $assertion : null;
}

function arsenal_events_preview_frontend_start_url(string $code, array $config): string
{
    return rtrim((string) ($config['frontend_origin'] ?? ''), '/') . '/api/preview?code=' . rawurlencode($code);
}

function arsenal_events_preview_post_link(string $preview_link, $post): string
{
    if (!$post || !arsenal_events_preview_allowed_post_type((string) ($post->post_type ?? ''))) {
        return $preview_link;
    }

    if (!function_exists('admin_url') || !function_exists('wp_create_nonce')) {
        return $preview_link;
    }

    return admin_url('admin-post.php?action=arsenal_events_preview&post_id=' . (int) $post->ID . '&_wpnonce=' . rawurlencode(wp_create_nonce('arsenal_events_preview_' . (int) $post->ID)));
}

function arsenal_events_preview_user_can_preview(int $post_id): bool
{
    return function_exists('is_user_logged_in')
        && is_user_logged_in()
        && function_exists('current_user_can')
        && current_user_can('edit_post', $post_id);
}

function arsenal_events_preview_admin_action(): void
{
    $post_id = function_exists('absint') ? absint($_GET['post_id'] ?? 0) : (int) ($_GET['post_id'] ?? 0);
    $revision_id = function_exists('absint') ? absint($_GET['revision_id'] ?? 0) : (int) ($_GET['revision_id'] ?? 0);
    $nonce = (string) ($_GET['_wpnonce'] ?? '');

    if ($post_id <= 0 || !function_exists('get_post')) {
        wp_die('Preview post not found.', 404);
    }

    if (function_exists('wp_verify_nonce') && !wp_verify_nonce($nonce, 'arsenal_events_preview_' . $post_id)) {
        wp_die('Preview request is invalid.', 403);
    }

    if (!arsenal_events_preview_user_can_preview($post_id)) {
        wp_die('You are not allowed to preview this content.', 403);
    }

    $post = get_post($post_id);
    if (!$post || !arsenal_events_preview_allowed_post_type((string) $post->post_type)) {
        wp_die('Preview post type is not supported.', 404);
    }

    if (in_array((string) $post->post_status, ['trash', 'auto-draft'], true)) {
        wp_die('Preview post is not available.', 404);
    }

    if ($revision_id > 0 && function_exists('wp_is_post_revision')) {
        $parent_id = (int) wp_is_post_revision($revision_id);
        if ($parent_id !== $post_id) {
            wp_die('Preview revision does not match this post.', 403);
        }
    }

    $config = arsenal_events_preview_config();
    if (!arsenal_events_preview_is_configured($config)) {
        wp_die('Headless preview is not configured.', 503);
    }

    $assertion = arsenal_events_preview_create_assertion($post, $revision_id, $config);
    $code = arsenal_events_preview_store_assertion($assertion, (int) $config['ttl_seconds']);
    $url = arsenal_events_preview_frontend_start_url($code, $config);

    wp_safe_redirect($url, 302);
    exit;
}

function arsenal_events_preview_rest_exchange($request): WP_REST_Response
{
    $code = '';
    if (is_object($request) && method_exists($request, 'get_param')) {
        $code = (string) $request->get_param('code');
    }

    if ($code === '') {
        return new WP_REST_Response(['error' => 'Preview code is required.'], 400);
    }

    $assertion = arsenal_events_preview_exchange_code($code);
    if ($assertion === null) {
        return new WP_REST_Response(['error' => 'Preview code is invalid or expired.'], 401);
    }

    return new WP_REST_Response(['assertion' => $assertion], 200);
}

function arsenal_events_preview_request_header($request, string $name): string
{
    if (is_object($request) && method_exists($request, 'get_header')) {
        return (string) $request->get_header($name);
    }

    return '';
}

function arsenal_events_preview_get_asserted_post(array $payload)
{
    if (!function_exists('get_post')) {
        return null;
    }

    $post = get_post((int) ($payload['post_id'] ?? 0));
    if (!$post || !arsenal_events_preview_allowed_post_type((string) $post->post_type)) {
        return null;
    }

    if ((string) $post->post_type !== (string) ($payload['post_type'] ?? '')) {
        return null;
    }

    if (arsenal_events_preview_route_for_post($post) !== (string) ($payload['route'] ?? '')) {
        return null;
    }

    return $post;
}

function arsenal_events_preview_content_source($post, int $revision_id)
{
    if ($revision_id <= 0 || !function_exists('get_post') || !function_exists('wp_is_post_revision')) {
        return $post;
    }

    $parent_id = (int) wp_is_post_revision($revision_id);
    $revision = get_post($revision_id);

    return ($parent_id === (int) $post->ID && $revision) ? $revision : $post;
}

function arsenal_events_preview_page_payload($post, $source): array
{
    $content = (string) ($source->post_content ?? '');

    return [
        'id' => (int) $post->ID,
        'slug' => (string) $post->post_name,
        'title' => (string) ($source->post_title ?? $post->post_title),
        'status' => (string) $post->post_status,
        'content' => function_exists('wp_kses_post')
            ? wp_kses_post(apply_filters('the_content', $content))
            : (string) apply_filters('the_content', $content),
        'structured_content' => function_exists('meza_get_page_structured_content') ? meza_get_page_structured_content((int) $post->ID) : [],
        'page_blocks' => function_exists('meza_get_page_blocks') ? meza_get_page_blocks((int) $post->ID) : [],
        'seo' => function_exists('meza_get_canonical_seo_payload') ? meza_get_canonical_seo_payload((int) $post->ID) : [],
        'yoast_meta' => function_exists('meza_get_yoast_metadata') ? meza_get_yoast_metadata((int) $post->ID) : [],
    ];
}

function arsenal_events_preview_post_payload($post, $source): array
{
    $post_type = (string) $post->post_type;
    $data = function_exists('meza_get_post_data') ? meza_get_post_data((int) $post->ID, $post_type, false) : [];
    $content = (string) ($source->post_content ?? '');

    $data['id'] = (int) $post->ID;
    $data['slug'] = (string) $post->post_name;
    $data['title'] = (string) ($source->post_title ?? $post->post_title);
    $data['status'] = (string) $post->post_status;
    $data['content'] = function_exists('meza_render_post_content') ? meza_render_post_content($source) : (string) apply_filters('the_content', $content);
    $data['content_raw'] = $content;

    return $data;
}

function arsenal_events_preview_rest_content($request): WP_REST_Response
{
    $config = arsenal_events_preview_config();
    $assertion = arsenal_events_preview_request_header($request, 'x-arsenal-preview-assertion');
    $verified = arsenal_events_preview_verify_assertion($assertion, (string) ($config['signing_secret'] ?? ''));

    if (empty($verified['ok']) || empty($verified['payload']) || !is_array($verified['payload'])) {
        return new WP_REST_Response(['error' => 'Preview assertion is invalid.'], 401);
    }

    $payload = $verified['payload'];
    if ((string) ($payload['aud'] ?? '') !== (string) ($config['audience'] ?? '')) {
        return new WP_REST_Response(['error' => 'Preview audience is invalid.'], 401);
    }

    $post = arsenal_events_preview_get_asserted_post($payload);
    if (!$post) {
        return new WP_REST_Response(['error' => 'Preview content is not available.'], 404);
    }

    $source = arsenal_events_preview_content_source($post, (int) ($payload['revision_id'] ?? 0));
    $data = (string) $post->post_type === 'page'
        ? arsenal_events_preview_page_payload($post, $source)
        : arsenal_events_preview_post_payload($post, $source);

    $data['preview'] = [
        'enabled' => true,
        'post_id' => (int) $post->ID,
        'revision_id' => (int) ($payload['revision_id'] ?? 0),
        'route' => (string) ($payload['route'] ?? ''),
        'expires_at' => (int) ($payload['exp'] ?? 0),
    ];
    $data['seo']['robots'] = [
        'noindex' => true,
        'nofollow' => true,
    ];

    return new WP_REST_Response([
        'post_type' => (string) $post->post_type,
        'route' => (string) ($payload['route'] ?? ''),
        'data' => $data,
    ], 200);
}

function arsenal_events_preview_register_routes(): void
{
    register_rest_route('meza/v1', '/preview/exchange', [
        'methods' => 'POST',
        'callback' => 'arsenal_events_preview_rest_exchange',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('meza/v1', '/preview/content', [
        'methods' => 'GET',
        'callback' => 'arsenal_events_preview_rest_content',
        'permission_callback' => '__return_true',
    ]);
}

if (function_exists('add_action')) {
    add_action('admin_post_arsenal_events_preview', 'arsenal_events_preview_admin_action');
    add_action('rest_api_init', 'arsenal_events_preview_register_routes', 12);
}

if (function_exists('add_filter')) {
    add_filter('preview_post_link', 'arsenal_events_preview_post_link', 10, 2);
}
