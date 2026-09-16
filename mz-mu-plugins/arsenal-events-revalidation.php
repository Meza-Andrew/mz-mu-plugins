<?php

/**
 * Plugin Name: Arsenal Events Revalidation
 * Description: Sends authenticated, path-scoped frontend revalidation requests after CMS changes.
 */

if (!defined('ABSPATH') && !defined('ARSENAL_EVENTS_REVALIDATION_TESTS')) {
    exit;
}

function arsenal_events_revalidation_starts_with(string $value, string $prefix): bool
{
    return substr($value, 0, strlen($prefix)) === $prefix;
}

function arsenal_events_revalidation_contains(string $value, string $needle): bool
{
    return $needle === '' || strpos($value, $needle) !== false;
}

function arsenal_events_revalidation_config_value(string $key, $default = null)
{
    $constant = 'ARSENAL_EVENTS_REVALIDATION_' . strtoupper($key);
    if (defined($constant)) {
        return constant($constant);
    }

    $env = getenv($constant);
    if ($env !== false && $env !== '') {
        return $env;
    }

    if (function_exists('get_option')) {
        $option = get_option('arsenal_events_revalidation_' . strtolower($key), null);
        if ($option !== null && $option !== false && $option !== '') {
            return $option;
        }
    }

    return $default;
}

function arsenal_events_revalidation_config(): array
{
    $config = [
        'endpoint' => (string) arsenal_events_revalidation_config_value('endpoint', ''),
        'secret' => (string) arsenal_events_revalidation_config_value('secret', ''),
        'header_name' => (string) arsenal_events_revalidation_config_value('header_name', 'X-Arsenal-Revalidation-Secret'),
        'timeout' => (float) arsenal_events_revalidation_config_value('timeout', 5),
        'retry_count' => (int) arsenal_events_revalidation_config_value('retry_count', 2),
        'retry_delay_ms' => (int) arsenal_events_revalidation_config_value('retry_delay_ms', 250),
    ];

    $config['timeout'] = max(1, min(30, $config['timeout']));
    $config['retry_count'] = max(0, min(5, $config['retry_count']));
    $config['retry_delay_ms'] = max(0, min(5000, $config['retry_delay_ms']));

    if (function_exists('apply_filters')) {
        $config = apply_filters('arsenal_events_revalidation_config', $config);
    }

    return is_array($config) ? $config : [];
}

function arsenal_events_revalidation_is_configured(array $config): bool
{
    return !empty($config['endpoint']) && !empty($config['secret']);
}

function arsenal_events_revalidation_normalize_path($path): ?string
{
    if (!is_string($path) || $path === '') {
        return null;
    }

    $path = trim($path);
    if (
        $path === ''
        || preg_match('#^[a-z][a-z0-9+.-]*://#i', $path)
        || arsenal_events_revalidation_starts_with($path, '//')
    ) {
        return null;
    }

    $path = '/' . ltrim($path, '/');
    $path = preg_split('/[?#]/', $path, 2)[0];
    $path = preg_replace('#/+#', '/', $path);

    return $path === '' ? '/' : $path;
}

function arsenal_events_revalidation_unique_paths(array $paths): array
{
    $unique = [];
    foreach ($paths as $path) {
        $normalized = arsenal_events_revalidation_normalize_path($path);
        if ($normalized !== null) {
            $unique[$normalized] = true;
        }
    }

    return array_keys($unique);
}

function arsenal_events_revalidation_post_detail_path(string $post_type, string $slug): array
{
    $slug = trim($slug, '/');
    if ($slug === '') {
        return [];
    }

    if ($post_type === 'race') {
        return ['/races/' . $slug];
    }

    if ($post_type === 'resource') {
        return ['/resources/' . $slug];
    }

    if ($post_type === 'page') {
        return [$slug === 'home' ? '/' : '/' . $slug];
    }

    return [];
}

function arsenal_events_revalidation_paths_for_post_change(
    string $post_type,
    string $new_slug,
    string $new_status,
    string $old_slug = '',
    string $old_status = ''
): array {
    $paths = [];
    $was_visible = $old_status === 'publish';
    $is_visible = $new_status === 'publish';

    if (!$was_visible && !$is_visible) {
        return [];
    }

    if ($was_visible) {
        $paths = array_merge($paths, arsenal_events_revalidation_post_detail_path($post_type, $old_slug ?: $new_slug));
    }

    if ($is_visible) {
        $paths = array_merge($paths, arsenal_events_revalidation_post_detail_path($post_type, $new_slug));
    }

    if ($post_type === 'race') {
        $paths = array_merge($paths, ['/races', '/races-and-results', '/']);
    } elseif ($post_type === 'resource') {
        $paths = array_merge($paths, ['/resources', '/for-race-directors', '/']);
    }

    return arsenal_events_revalidation_unique_paths($paths);
}

function arsenal_events_revalidation_paths_for_settings(): array
{
    return ['/', '/contact', '/races', '/resources', '/races-and-results', '/for-race-directors'];
}

function arsenal_events_revalidation_paths_for_race_director_options(): array
{
    return ['/for-race-directors', '/'];
}

function arsenal_events_revalidation_paths_for_race_results_options(): array
{
    return ['/races-and-results', '/races', '/'];
}

function arsenal_events_revalidation_paths_for_option(string $option_name): array
{
    $name = strtolower($option_name);

    if (
        arsenal_events_revalidation_starts_with($name, 'theme_mods_')
        || arsenal_events_revalidation_starts_with($name, '_options_site_')
        || in_array($name, ['nav_menu_options', 'sidebars_widgets'], true)
        || arsenal_events_revalidation_contains($name, 'header_nav_links')
        || arsenal_events_revalidation_contains($name, 'footer_nav_links')
        || arsenal_events_revalidation_contains($name, 'social_links')
        || arsenal_events_revalidation_contains($name, 'site_')
    ) {
        return arsenal_events_revalidation_paths_for_settings();
    }

    if (
        arsenal_events_revalidation_starts_with($name, 'options_rd_')
        || arsenal_events_revalidation_starts_with($name, '_options_rd_')
        || arsenal_events_revalidation_contains($name, 'race_director')
    ) {
        return arsenal_events_revalidation_paths_for_race_director_options();
    }

    if (
        arsenal_events_revalidation_starts_with($name, 'options_rr_')
        || arsenal_events_revalidation_starts_with($name, '_options_rr_')
        || arsenal_events_revalidation_contains($name, 'race_results')
    ) {
        return arsenal_events_revalidation_paths_for_race_results_options();
    }

    return [];
}

function arsenal_events_revalidation_paths_for_post_id(int $post_id): array
{
    if (!function_exists('get_post')) {
        return [];
    }

    $post = get_post($post_id);
    if (!$post || empty($post->post_type)) {
        return [];
    }

    return arsenal_events_revalidation_paths_for_post_change(
        (string) $post->post_type,
        (string) ($post->post_name ?? ''),
        (string) ($post->post_status ?? ''),
        (string) ($post->post_name ?? ''),
        (string) ($post->post_status ?? '')
    );
}

function arsenal_events_revalidation_paths_for_attachment(int $attachment_id): array
{
    $paths = [];

    if (function_exists('get_posts')) {
        $posts = get_posts([
            'post_type' => ['page', 'race', 'resource'],
            'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
            'posts_per_page' => 25,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'value' => (string) $attachment_id,
                    'compare' => 'LIKE',
                ],
            ],
        ]);

        foreach ((array) $posts as $post_id) {
            $paths = array_merge($paths, arsenal_events_revalidation_paths_for_post_id((int) $post_id));
        }
    }

    if ($paths === []) {
        $paths = ['/', '/races', '/resources', '/races-and-results', '/for-race-directors'];
    }

    return arsenal_events_revalidation_unique_paths($paths);
}

function arsenal_events_revalidation_queue(array $paths, string $reason): void
{
    $paths = arsenal_events_revalidation_unique_paths($paths);
    if ($paths === []) {
        return;
    }

    $queued = $GLOBALS['arsenal_events_revalidation_queue'] ?? ['paths' => [], 'reasons' => []];
    foreach ($paths as $path) {
        $queued['paths'][$path] = true;
    }
    $queued['reasons'][$reason] = true;

    $GLOBALS['arsenal_events_revalidation_queue'] = $queued;
}

function arsenal_events_revalidation_flush_queue(): array
{
    $queued = $GLOBALS['arsenal_events_revalidation_queue'] ?? ['paths' => [], 'reasons' => []];
    $GLOBALS['arsenal_events_revalidation_queue'] = ['paths' => [], 'reasons' => []];

    $paths = array_keys((array) ($queued['paths'] ?? []));
    $reasons = array_keys((array) ($queued['reasons'] ?? []));

    return arsenal_events_revalidation_dispatch_paths($paths, implode(',', $reasons) ?: 'cms_change');
}

function arsenal_events_revalidation_dedupe_key(array $paths): string
{
    sort($paths);
    return 'arsenal_events_revalidation_' . md5(implode('|', $paths));
}

function arsenal_events_revalidation_should_skip_dispatch(array $paths): bool
{
    if (!function_exists('get_transient') || !function_exists('set_transient')) {
        return false;
    }

    $key = arsenal_events_revalidation_dedupe_key($paths);
    if (get_transient($key)) {
        return true;
    }

    set_transient($key, 1, 10);
    return false;
}

function arsenal_events_revalidation_dispatch_paths(array $paths, string $reason = 'manual'): array
{
    $paths = arsenal_events_revalidation_unique_paths($paths);
    $config = arsenal_events_revalidation_config();

    if ($paths === []) {
        return ['dispatched' => false, 'status' => 'empty', 'paths' => []];
    }

    if (!arsenal_events_revalidation_is_configured($config)) {
        return ['dispatched' => false, 'status' => 'disabled', 'paths' => $paths];
    }

    if (arsenal_events_revalidation_should_skip_dispatch($paths)) {
        return ['dispatched' => false, 'status' => 'duplicate', 'paths' => $paths];
    }

    $attempts = ((int) $config['retry_count']) + 1;
    $body = [
        'paths' => $paths,
        'reason' => $reason,
        'source' => 'wordpress',
    ];
    $args = [
        'method' => 'POST',
        'timeout' => (float) $config['timeout'],
        'headers' => [
            'Content-Type' => 'application/json',
            (string) $config['header_name'] => (string) $config['secret'],
        ],
        'body' => function_exists('wp_json_encode') ? wp_json_encode($body) : json_encode($body),
    ];

    $last_status = 0;
    $last_error = '';

    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        $response = wp_remote_post((string) $config['endpoint'], $args);

        if (function_exists('is_wp_error') && is_wp_error($response)) {
            $last_error = method_exists($response, 'get_error_message') ? $response->get_error_message() : 'transport_error';
            $retryable = true;
        } else {
            $last_status = arsenal_events_revalidation_response_code($response);
            $last_error = '';
            $retryable = $last_status === 429 || $last_status >= 500;

            if ($last_status >= 200 && $last_status < 300) {
                return [
                    'dispatched' => true,
                    'status' => 'success',
                    'paths' => $paths,
                    'attempts' => $attempt,
                    'http_status' => $last_status,
                ];
            }

            if (!$retryable) {
                arsenal_events_revalidation_log_failure('permanent_failure', $config, $last_status, $attempt);
                return [
                    'dispatched' => false,
                    'status' => 'permanent_failure',
                    'paths' => $paths,
                    'attempts' => $attempt,
                    'http_status' => $last_status,
                ];
            }
        }

        if (!$retryable || $attempt >= $attempts) {
            break;
        }

        arsenal_events_revalidation_sleep((int) $config['retry_delay_ms'], $attempt);
    }

    arsenal_events_revalidation_log_failure($last_error !== '' ? 'transport_failure' : 'retry_exhausted', $config, $last_status, $attempts);

    return [
        'dispatched' => false,
        'status' => $last_error !== '' ? 'transport_failure' : 'retry_exhausted',
        'paths' => $paths,
        'attempts' => $attempts,
        'http_status' => $last_status,
    ];
}

function arsenal_events_revalidation_response_code($response): int
{
    if (function_exists('wp_remote_retrieve_response_code')) {
        return (int) wp_remote_retrieve_response_code($response);
    }

    return (int) ($response['response']['code'] ?? 0);
}

function arsenal_events_revalidation_sleep(int $delay_ms, int $attempt): void
{
    if ($delay_ms <= 0) {
        return;
    }

    $delay = min(5000, $delay_ms * $attempt);
    if (function_exists('apply_filters')) {
        $delay = (int) apply_filters('arsenal_events_revalidation_retry_delay_ms', $delay, $attempt);
    }

    if ($delay > 0) {
        usleep($delay * 1000);
    }
}

function arsenal_events_revalidation_redact_url(string $url): string
{
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return '[redacted-url]';
    }

    $redacted = '';
    if (!empty($parts['scheme'])) {
        $redacted .= $parts['scheme'] . '://';
    }
    $redacted .= $parts['host'];
    if (!empty($parts['port'])) {
        $redacted .= ':' . $parts['port'];
    }
    $redacted .= $parts['path'] ?? '';
    if (!empty($parts['query'])) {
        $redacted .= '?[redacted]';
    }

    return $redacted;
}

function arsenal_events_revalidation_log_failure(string $status, array $config, int $http_status, int $attempts): void
{
    error_log(sprintf(
        'Arsenal Events revalidation %s for %s after %d attempt(s), http_status=%d',
        $status,
        arsenal_events_revalidation_redact_url((string) ($config['endpoint'] ?? '')),
        $attempts,
        $http_status
    ));
}

function arsenal_events_revalidation_capture_old_post($post_id): void
{
    if (!function_exists('get_post')) {
        return;
    }

    $post = get_post((int) $post_id);
    if (!$post || !in_array((string) $post->post_type, ['page', 'race', 'resource'], true)) {
        return;
    }

    $GLOBALS['arsenal_events_revalidation_old_posts'][(int) $post_id] = [
        'post_type' => (string) $post->post_type,
        'slug' => (string) $post->post_name,
        'status' => (string) $post->post_status,
    ];
}

function arsenal_events_revalidation_handle_save_post($post_id, $post): void
{
    $post_id = (int) $post_id;
    if (!$post || !in_array((string) $post->post_type, ['page', 'race', 'resource'], true)) {
        return;
    }

    if (
        (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id))
        || (function_exists('wp_is_post_autosave') && wp_is_post_autosave($post_id))
    ) {
        return;
    }

    $old = $GLOBALS['arsenal_events_revalidation_old_posts'][$post_id] ?? [];
    $paths = arsenal_events_revalidation_paths_for_post_change(
        (string) $post->post_type,
        (string) $post->post_name,
        (string) $post->post_status,
        (string) ($old['slug'] ?? ''),
        (string) ($old['status'] ?? '')
    );

    arsenal_events_revalidation_queue($paths, 'post_save:' . (string) $post->post_type);
}

function arsenal_events_revalidation_handle_transition(string $new_status, string $old_status, $post): void
{
    if (!$post || !in_array((string) $post->post_type, ['page', 'race', 'resource'], true)) {
        return;
    }

    $paths = arsenal_events_revalidation_paths_for_post_change(
        (string) $post->post_type,
        (string) $post->post_name,
        $new_status,
        (string) $post->post_name,
        $old_status
    );

    arsenal_events_revalidation_queue($paths, 'post_status:' . (string) $post->post_type);
}

function arsenal_events_revalidation_handle_post_meta($meta_id, $object_id, $meta_key): void
{
    $object_id = (int) $object_id;
    $meta_key = (string) $meta_key;

    if (arsenal_events_revalidation_starts_with($meta_key, '_yoast_wpseo_')) {
        arsenal_events_revalidation_queue(arsenal_events_revalidation_paths_for_post_id($object_id), 'yoast_meta');
        return;
    }

    if ($meta_key === '_wp_attachment_image_alt' && function_exists('get_post')) {
        $post = get_post($object_id);
        if ($post && (string) $post->post_type === 'attachment') {
            arsenal_events_revalidation_queue(arsenal_events_revalidation_paths_for_attachment($object_id), 'attachment_alt');
        }
    }
}

function arsenal_events_revalidation_handle_option($option_name): void
{
    arsenal_events_revalidation_queue(arsenal_events_revalidation_paths_for_option((string) $option_name), 'option:' . (string) $option_name);
}

function arsenal_events_revalidation_handle_acf_save($post_id): void
{
    if (in_array($post_id, ['option', 'options'], true)) {
        arsenal_events_revalidation_queue(arsenal_events_revalidation_paths_for_settings(), 'acf_options');
    }
}

function arsenal_events_revalidation_handle_attachment($attachment_id): void
{
    arsenal_events_revalidation_queue(arsenal_events_revalidation_paths_for_attachment((int) $attachment_id), 'attachment');
}

function arsenal_events_revalidation_handle_attachment_metadata($metadata, $attachment_id)
{
    arsenal_events_revalidation_handle_attachment((int) $attachment_id);

    return $metadata;
}

if (function_exists('add_action')) {
    add_action('pre_post_update', 'arsenal_events_revalidation_capture_old_post', 10, 1);
    add_action('save_post', 'arsenal_events_revalidation_handle_save_post', 20, 2);
    add_action('transition_post_status', 'arsenal_events_revalidation_handle_transition', 20, 3);
    add_action('added_post_meta', 'arsenal_events_revalidation_handle_post_meta', 20, 3);
    add_action('updated_post_meta', 'arsenal_events_revalidation_handle_post_meta', 20, 3);
    add_action('deleted_post_meta', 'arsenal_events_revalidation_handle_post_meta', 20, 3);
    add_action('added_option', 'arsenal_events_revalidation_handle_option', 20, 1);
    add_action('updated_option', 'arsenal_events_revalidation_handle_option', 20, 1);
    add_action('deleted_option', 'arsenal_events_revalidation_handle_option', 20, 1);
    add_action('acf/save_post', 'arsenal_events_revalidation_handle_acf_save', 20, 1);
    add_action('add_attachment', 'arsenal_events_revalidation_handle_attachment', 20, 1);
    add_action('edit_attachment', 'arsenal_events_revalidation_handle_attachment', 20, 1);
    add_action('delete_attachment', 'arsenal_events_revalidation_handle_attachment', 20, 1);
    add_action('shutdown', 'arsenal_events_revalidation_flush_queue', 20, 0);
}

if (function_exists('add_filter')) {
    add_filter('wp_update_attachment_metadata', 'arsenal_events_revalidation_handle_attachment_metadata', 20, 2);
}
