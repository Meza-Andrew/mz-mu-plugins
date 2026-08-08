<?php

if (!defined('ABSPATH')) {
    exit;
}

const MEZA_HEADLESS_BUILD_QUEUE_OPTION = 'meza_headless_build_dispatch_queue';
const MEZA_HEADLESS_BUILD_LAST_RESULT_OPTION = 'meza_headless_build_dispatch_last_result';
const MEZA_HEADLESS_BUILD_CRON_HOOK = 'meza_headless_build_dispatch_event';

function meza_headless_build_dispatch_env(string $name, string $default = ''): string
{
    $value = getenv($name);

    return is_string($value) && $value !== '' ? $value : $default;
}

function meza_headless_build_dispatch_enabled(): bool
{
    if (defined('MZ_HEADLESS_BUILD_DISPATCH_ENABLED')) {
        return meza_truthy(MZ_HEADLESS_BUILD_DISPATCH_ENABLED);
    }

    return meza_headless_build_dispatch_token() !== ''
        && meza_current_environment_label() === 'production';
}

function meza_headless_build_dispatch_owner(): string
{
    if (defined('MZ_HEADLESS_BUILD_DISPATCH_OWNER') && MZ_HEADLESS_BUILD_DISPATCH_OWNER !== '') {
        return (string) MZ_HEADLESS_BUILD_DISPATCH_OWNER;
    }

    return meza_headless_build_dispatch_env('MZ_HEADLESS_BUILD_DISPATCH_OWNER', 'Meza-Andrew');
}

function meza_headless_build_dispatch_repo(): string
{
    if (defined('MZ_HEADLESS_BUILD_DISPATCH_REPO') && MZ_HEADLESS_BUILD_DISPATCH_REPO !== '') {
        return (string) MZ_HEADLESS_BUILD_DISPATCH_REPO;
    }

    return meza_headless_build_dispatch_env('MZ_HEADLESS_BUILD_DISPATCH_REPO', 'iu-headless-frontend');
}

function meza_headless_build_dispatch_branch(): string
{
    if (defined('MZ_HEADLESS_BUILD_DISPATCH_BRANCH') && MZ_HEADLESS_BUILD_DISPATCH_BRANCH !== '') {
        return (string) MZ_HEADLESS_BUILD_DISPATCH_BRANCH;
    }

    return meza_headless_build_dispatch_env('MZ_HEADLESS_BUILD_DISPATCH_BRANCH', 'main');
}

function meza_headless_build_dispatch_event_type(): string
{
    if (defined('MZ_HEADLESS_BUILD_DISPATCH_EVENT_TYPE') && MZ_HEADLESS_BUILD_DISPATCH_EVENT_TYPE !== '') {
        return (string) MZ_HEADLESS_BUILD_DISPATCH_EVENT_TYPE;
    }

    return meza_headless_build_dispatch_env(
        'MZ_HEADLESS_BUILD_DISPATCH_EVENT_TYPE',
        'headless-content-changed'
    );
}

function meza_headless_build_dispatch_token(): string
{
    if (defined('MZ_HEADLESS_BUILD_DISPATCH_TOKEN') && MZ_HEADLESS_BUILD_DISPATCH_TOKEN !== '') {
        return (string) MZ_HEADLESS_BUILD_DISPATCH_TOKEN;
    }

    return meza_headless_build_dispatch_env('MZ_HEADLESS_BUILD_DISPATCH_TOKEN');
}

function meza_headless_build_dispatch_delay(): int
{
    $configured = defined('MZ_HEADLESS_BUILD_DISPATCH_DEBOUNCE_SECONDS')
        ? (int) MZ_HEADLESS_BUILD_DISPATCH_DEBOUNCE_SECONDS
        : (int) meza_headless_build_dispatch_env('MZ_HEADLESS_BUILD_DISPATCH_DEBOUNCE_SECONDS', '10');

    return max(0, $configured);
}

function meza_headless_build_dispatch_url(): string
{
    $owner = trim(meza_headless_build_dispatch_owner());
    $repo = trim(meza_headless_build_dispatch_repo());

    if ($owner === '' || $repo === '') {
        return '';
    }

    return sprintf(
        'https://api.github.com/repos/%s/%s/dispatches',
        rawurlencode($owner),
        rawurlencode($repo)
    );
}

function meza_headless_build_normalize_path(string $path): string
{
    $trimmed = trim($path);

    if ($trimmed === '') {
        return '/';
    }

    $without_origin = preg_replace('#^https?://[^/]+#i', '', $trimmed);
    $with_leading_slash = str_starts_with($without_origin, '/')
        ? $without_origin
        : '/' . $without_origin;
    $collapsed = preg_replace('#/+#', '/', $with_leading_slash);

    if ($collapsed === '/' || $collapsed === '') {
        return '/';
    }

    return rtrim($collapsed, '/') . '/';
}

function meza_headless_build_unique_strings(array $values): array
{
    $normalized = array_map(
        static fn($value) => trim((string) $value),
        $values
    );
    $normalized = array_filter(
        $normalized,
        static fn($value) => $value !== ''
    );

    return array_values(array_unique($normalized));
}

function meza_headless_build_read_queue(): array
{
    $queue = get_option(MEZA_HEADLESS_BUILD_QUEUE_OPTION, []);

    if (!is_array($queue)) {
        $queue = [];
    }

    return [
        'paths' => meza_headless_build_unique_strings((array) ($queue['paths'] ?? [])),
        'slugs' => meza_headless_build_unique_strings((array) ($queue['slugs'] ?? [])),
        'types' => meza_headless_build_unique_strings((array) ($queue['types'] ?? [])),
        'queued_at' => isset($queue['queued_at']) ? (string) $queue['queued_at'] : '',
    ];
}

function meza_headless_build_write_queue(array $queue): void
{
    update_option(
        MEZA_HEADLESS_BUILD_QUEUE_OPTION,
        [
            'paths' => meza_headless_build_unique_strings((array) ($queue['paths'] ?? [])),
            'slugs' => meza_headless_build_unique_strings((array) ($queue['slugs'] ?? [])),
            'types' => meza_headless_build_unique_strings((array) ($queue['types'] ?? [])),
            'queued_at' => (string) ($queue['queued_at'] ?? gmdate('c')),
        ],
        false
    );
}

function meza_headless_build_store_result(array $result): void
{
    update_option(MEZA_HEADLESS_BUILD_LAST_RESULT_OPTION, $result, false);
}

function meza_headless_build_schedule_dispatch(): void
{
    if (!meza_headless_build_dispatch_enabled()) {
        return;
    }

    if (wp_next_scheduled(MEZA_HEADLESS_BUILD_CRON_HOOK)) {
        return;
    }

    wp_schedule_single_event(
        time() + meza_headless_build_dispatch_delay(),
        MEZA_HEADLESS_BUILD_CRON_HOOK
    );
}

function meza_headless_build_queue_changes(array $changes): void
{
    if (!meza_headless_build_dispatch_enabled()) {
        return;
    }

    $queue = meza_headless_build_read_queue();
    $queue['paths'] = array_merge($queue['paths'], (array) ($changes['paths'] ?? []));
    $queue['slugs'] = array_merge($queue['slugs'], (array) ($changes['slugs'] ?? []));
    $queue['types'] = array_merge($queue['types'], (array) ($changes['types'] ?? []));
    $queue['queued_at'] = gmdate('c');

    meza_headless_build_write_queue($queue);
    meza_headless_build_schedule_dispatch();
}

function meza_headless_build_queue_global_change(string $type = 'settings'): void
{
    meza_headless_build_queue_changes([
        'paths' => ['/'],
        'types' => [$type],
    ]);
}

function meza_headless_build_post_is_dispatchable(WP_Post $post): bool
{
    if (wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID)) {
        return false;
    }

    if (in_array($post->post_status, ['auto-draft', 'draft', 'inherit', 'trash'], true)) {
        return false;
    }

    return true;
}

function meza_headless_build_payload_for_post(WP_Post $post): array
{
    $payload = [
        'paths' => [],
        'slugs' => [],
        'types' => [$post->post_type],
    ];

    if ($post->post_name !== '') {
        $payload['slugs'][] = $post->post_name;
    }

    if ($post->post_status === 'publish') {
        $permalink = get_permalink($post);

        if (is_string($permalink) && $permalink !== '') {
            $payload['paths'][] = meza_headless_build_normalize_path($permalink);
        }
    }

    if ((int) get_option('page_on_front', 0) === (int) $post->ID) {
        $payload['paths'][] = '/';
        $payload['types'][] = 'home';
    }

    if ((int) get_option('page_for_posts', 0) === (int) $post->ID) {
        $payload['paths'][] = '/blog/';
        $payload['types'][] = 'blog';
    }

    if ($post->post_type === 'post') {
        $payload['paths'][] = '/blog/';
        $payload['types'][] = 'article';
    }

    return $payload;
}

function meza_headless_build_dispatch_queue(): void
{
    if (!meza_headless_build_dispatch_enabled()) {
        return;
    }

    $queue = meza_headless_build_read_queue();

    if ($queue['paths'] === [] && $queue['slugs'] === [] && $queue['types'] === []) {
        return;
    }

    $token = meza_headless_build_dispatch_token();
    $url = meza_headless_build_dispatch_url();

    if ($token === '' || $url === '') {
        meza_headless_build_store_result([
            'sent_at' => gmdate('c'),
            'ok' => false,
            'message' => 'Headless build dispatch is missing token or repo configuration.',
            'queue' => $queue,
        ]);
        return;
    }

    $body = [
        'event_type' => meza_headless_build_dispatch_event_type(),
        'client_payload' => [
            'scope' => 'changed',
            'changedPaths' => $queue['paths'],
            'changedSlugs' => $queue['slugs'],
            'changedTypes' => $queue['types'],
            'branch' => meza_headless_build_dispatch_branch(),
            'environment' => meza_current_environment_label(),
            'queuedAt' => $queue['queued_at'],
            'sourceSite' => home_url('/'),
            'sourceLabel' => get_bloginfo('name'),
        ],
    ];

    $response = wp_remote_post(
        $url,
        [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'User-Agent' => 'meza-headless-build-dispatch',
                'X-GitHub-Api-Version' => '2022-11-28',
            ],
            'body' => wp_json_encode($body),
        ]
    );

    if (is_wp_error($response)) {
        meza_headless_build_store_result([
            'sent_at' => gmdate('c'),
            'ok' => false,
            'message' => $response->get_error_message(),
            'queue' => $queue,
        ]);
        meza_headless_build_schedule_dispatch();
        return;
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $ok = $status >= 200 && $status < 300;

    meza_headless_build_store_result([
        'sent_at' => gmdate('c'),
        'ok' => $ok,
        'status' => $status,
        'message' => wp_remote_retrieve_body($response),
        'queue' => $queue,
    ]);

    if ($ok) {
        delete_option(MEZA_HEADLESS_BUILD_QUEUE_OPTION);
        return;
    }

    meza_headless_build_schedule_dispatch();
}

add_action(MEZA_HEADLESS_BUILD_CRON_HOOK, 'meza_headless_build_dispatch_queue');

add_action('save_post', function ($post_id, $post) {
    if (!$post instanceof WP_Post) {
        return;
    }

    if (!meza_headless_build_post_is_dispatchable($post)) {
        return;
    }

    meza_headless_build_queue_changes(meza_headless_build_payload_for_post($post));
}, 20, 2);

add_action('before_delete_post', function ($post_id, $post) {
    if (!$post instanceof WP_Post) {
        return;
    }

    meza_headless_build_queue_changes(meza_headless_build_payload_for_post($post));
}, 20, 2);

add_action('wp_update_nav_menu', function () {
    meza_headless_build_queue_global_change('menu');
}, 20);

add_action('acf/save_post', function ($post_id) {
    if (!is_string($post_id)) {
        return;
    }

    if ($post_id === 'options' || str_starts_with($post_id, 'options_')) {
        meza_headless_build_queue_global_change('settings');
    }
}, 20);

add_action('updated_option', function ($option) {
    if (!in_array(
        $option,
        ['blogdescription', 'blogname', 'page_for_posts', 'page_on_front', 'show_on_front'],
        true
    )) {
        return;
    }

    meza_headless_build_queue_global_change('settings');
}, 20, 1);
