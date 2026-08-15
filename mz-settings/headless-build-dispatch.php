<?php

if (!defined('ABSPATH')) {
    exit;
}

const MEZA_HEADLESS_BUILD_QUEUE_OPTION = 'meza_headless_build_dispatch_queue';
const MEZA_HEADLESS_BUILD_LAST_RESULT_OPTION = 'meza_headless_build_dispatch_last_result';
const MEZA_HEADLESS_BUILD_STATUS_OPTION = 'meza_headless_build_status';
const MEZA_HEADLESS_BUILD_CRON_HOOK = 'meza_headless_build_dispatch_event';
const MEZA_HEADLESS_BUILD_MANUAL_NONCE_ACTION = 'meza_headless_build_manual_dispatch';
const MEZA_HEADLESS_BUILD_STATUS_CALLBACK_ACTION = 'meza_headless_build_update_status';
const MEZA_HEADLESS_BUILD_STATUS_POLL_ACTION = 'meza_headless_build_get_status';

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

    return meza_headless_build_dispatch_block_reason() === '';
}

function meza_headless_build_auto_dispatch_enabled(): bool
{
    if (defined('MZ_HEADLESS_BUILD_AUTO_DISPATCH_ENABLED')) {
        return meza_truthy(MZ_HEADLESS_BUILD_AUTO_DISPATCH_ENABLED);
    }

    return meza_truthy(meza_headless_build_dispatch_env('MZ_HEADLESS_BUILD_AUTO_DISPATCH_ENABLED', '0'));
}

function meza_headless_build_status_secret(): string
{
    if (defined('MZ_HEADLESS_BUILD_STATUS_SECRET') && MZ_HEADLESS_BUILD_STATUS_SECRET !== '') {
        return (string) MZ_HEADLESS_BUILD_STATUS_SECRET;
    }

    return meza_headless_build_dispatch_env('MZ_HEADLESS_BUILD_STATUS_SECRET');
}

function meza_headless_build_status_callback_available(): bool
{
    return meza_headless_build_status_secret() !== '';
}

function meza_headless_build_dispatch_block_reason(): string
{
    if (meza_current_environment_label() !== 'production') {
        return 'Sync Content dispatch is only enabled in production.';
    }

    if (meza_headless_build_dispatch_token() === '' || meza_headless_build_dispatch_url() === '') {
        return 'Sync Content dispatch is missing GitHub repository settings or token.';
    }

    if (!meza_headless_build_status_callback_available()) {
        return 'Sync Content status callback secret is missing. Configure matching WordPress and GitHub callback secrets before syncing.';
    }

    return '';
}

function meza_headless_build_admin_bar_warning_message(): string
{
    $block_reason = meza_headless_build_dispatch_block_reason();

    if ($block_reason !== '') {
        return $block_reason;
    }

    return '';
}

function meza_headless_build_manual_dispatch_available(): bool
{
    return meza_headless_build_dispatch_block_reason() === '';
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

function meza_headless_build_status_callback_url(): string
{
    return admin_url('admin-ajax.php');
}

function meza_headless_build_create_dispatch_id(): string
{
    if (function_exists('wp_generate_uuid4')) {
        return wp_generate_uuid4();
    }

    return uniqid('meza-headless-build-', true);
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

function meza_headless_build_read_result(): array
{
    $result = get_option(MEZA_HEADLESS_BUILD_LAST_RESULT_OPTION, []);

    return is_array($result) ? $result : [];
}

function meza_headless_build_status_timeout_seconds(string $phase): int
{
    $default = match ($phase) {
        'queued' => 120,
        'in_progress' => 1800,
        default => 0,
    };

    $configured = match ($phase) {
        'queued' => defined('MZ_HEADLESS_BUILD_QUEUED_TIMEOUT_SECONDS')
            ? (int) MZ_HEADLESS_BUILD_QUEUED_TIMEOUT_SECONDS
            : (int) meza_headless_build_dispatch_env('MZ_HEADLESS_BUILD_QUEUED_TIMEOUT_SECONDS', (string) $default),
        'in_progress' => defined('MZ_HEADLESS_BUILD_RUNNING_TIMEOUT_SECONDS')
            ? (int) MZ_HEADLESS_BUILD_RUNNING_TIMEOUT_SECONDS
            : (int) meza_headless_build_dispatch_env('MZ_HEADLESS_BUILD_RUNNING_TIMEOUT_SECONDS', (string) $default),
        default => $default,
    };

    return max(0, $configured);
}

function meza_headless_build_status_reference_timestamp(array $status): string
{
    $phase = (string) ($status['phase'] ?? '');

    if ($phase === 'queued') {
        return (string) ($status['updatedAt'] ?: $status['dispatchedAt'] ?: $status['queuedAt'] ?: '');
    }

    if ($phase === 'in_progress') {
        return (string) ($status['updatedAt'] ?: $status['startedAt'] ?: $status['dispatchedAt'] ?: '');
    }

    return '';
}

function meza_headless_build_status_is_stale(array $status): bool
{
    $phase = (string) ($status['phase'] ?? '');
    if (!in_array($phase, ['queued', 'in_progress'], true)) {
        return false;
    }

    $timeout = meza_headless_build_status_timeout_seconds($phase);
    if ($timeout <= 0) {
        return false;
    }

    $reference_timestamp = meza_headless_build_status_reference_timestamp($status);
    if ($reference_timestamp === '') {
        return false;
    }

    $age = meza_headless_build_diff_seconds($reference_timestamp, gmdate('c'));

    return $age !== null && $age >= $timeout;
}

function meza_headless_build_mark_stale_status(array $status): array
{
    $phase = (string) ($status['phase'] ?? '');
    $now = gmdate('c');
    $message = $phase === 'queued'
        ? 'Static sync stayed queued too long. The GitHub workflow may not have started or the callback may be misconfigured. Refresh complete and ready to retry.'
        : 'Static sync status timed out waiting for completion. The GitHub workflow may be stuck or the callback may be misconfigured. Refresh complete and ready to retry.';

    $status['phase'] = 'dispatch_failed';
    $status['ok'] = false;
    $status['conclusion'] = 'failure';
    $status['message'] = $message;
    $status['updatedAt'] = $now;

    if ((string) ($status['completedAt'] ?? '') === '') {
        $status['completedAt'] = $now;
    }

    if (!is_numeric($status['elapsedSeconds'] ?? null)) {
        $elapsed = meza_headless_build_diff_seconds(
            (string) ($status['dispatchedAt'] ?: $status['queuedAt'] ?: ''),
            $now
        );
        $status['elapsedSeconds'] = $elapsed;
    }

    return meza_headless_build_store_status($status);
}

function meza_headless_build_default_status(): array
{
    return [
        'dispatchId' => '',
        'phase' => 'idle',
        'message' => '',
        'ok' => null,
        'conclusion' => '',
        'queuedAt' => '',
        'dispatchedAt' => '',
        'startedAt' => '',
        'completedAt' => '',
        'updatedAt' => '',
        'elapsedSeconds' => null,
        'runId' => '',
        'runUrl' => '',
        'sourceSite' => '',
        'sourceLabel' => '',
    ];
}

function meza_headless_build_normalize_status(array $status): array
{
    $defaults = meza_headless_build_default_status();
    $normalized = array_merge($defaults, $status);
    $string_keys = [
        'dispatchId',
        'phase',
        'message',
        'conclusion',
        'queuedAt',
        'dispatchedAt',
        'startedAt',
        'completedAt',
        'updatedAt',
        'runId',
        'runUrl',
        'sourceSite',
        'sourceLabel',
    ];

    foreach ($string_keys as $key) {
        $normalized[$key] = trim((string) ($normalized[$key] ?? ''));
    }

    if ($normalized['ok'] === null) {
        // Leave null intact so the UI can distinguish "in progress" from failure.
    } else {
        $normalized['ok'] = in_array($normalized['ok'], [true, 1, '1', 'true'], true);
    }

    $elapsed = $normalized['elapsedSeconds'];
    $normalized['elapsedSeconds'] = is_numeric($elapsed) ? max(0, (int) $elapsed) : null;

    return $normalized;
}

function meza_headless_build_read_status(): array
{
    $status = get_option(MEZA_HEADLESS_BUILD_STATUS_OPTION, []);
    $normalized = is_array($status)
        ? meza_headless_build_normalize_status($status)
        : meza_headless_build_default_status();

    if (meza_headless_build_status_is_stale($normalized)) {
        return meza_headless_build_mark_stale_status($normalized);
    }

    return $normalized;
}

function meza_headless_build_store_status(array $status): array
{
    $normalized = meza_headless_build_normalize_status($status);
    update_option(MEZA_HEADLESS_BUILD_STATUS_OPTION, $normalized, false);

    return $normalized;
}

function meza_headless_build_diff_seconds(string $from, string $to): ?int
{
    if ($from === '' || $to === '') {
        return null;
    }

    try {
        $from_time = new DateTimeImmutable($from);
        $to_time = new DateTimeImmutable($to);
    } catch (Exception $exception) {
        return null;
    }

    return max(0, $to_time->getTimestamp() - $from_time->getTimestamp());
}

function meza_headless_build_status_message(string $phase, string $conclusion = ''): string
{
    if ($phase === 'queued') {
        return 'Queued for static sync.';
    }

    if ($phase === 'in_progress') {
        return 'Static sync is running.';
    }

    if ($phase === 'completed') {
        if ($conclusion === 'success') {
            return 'Static sync finished successfully.';
        }

        if ($conclusion === 'cancelled') {
            return 'Static sync was cancelled.';
        }

        return 'Static sync failed.';
    }

    if ($phase === 'dispatch_failed') {
        return 'Unable to dispatch static sync.';
    }

    return '';
}

function meza_headless_build_mark_dispatch_status(
    string $dispatch_id,
    array $queue,
    bool $ok,
    string $message = '',
    int $status_code = 0
): array {
    $dispatched_at = gmdate('c');

    return meza_headless_build_store_status([
        'dispatchId' => $dispatch_id,
        'phase' => $ok ? 'queued' : 'dispatch_failed',
        'message' => $message !== '' ? $message : meza_headless_build_status_message($ok ? 'queued' : 'dispatch_failed'),
        'ok' => $ok ? null : false,
        'conclusion' => $ok ? '' : 'failure',
        'queuedAt' => (string) ($queue['queued_at'] ?? $dispatched_at),
        'dispatchedAt' => $dispatched_at,
        'startedAt' => '',
        'completedAt' => '',
        'updatedAt' => $dispatched_at,
        'elapsedSeconds' => null,
        'runId' => '',
        'runUrl' => '',
        'sourceSite' => home_url('/'),
        'sourceLabel' => get_bloginfo('name'),
    ]);
}

function meza_headless_build_update_status_from_callback(array $payload): array
{
    $current = meza_headless_build_read_status();
    $dispatch_id = trim((string) ($payload['dispatchId'] ?? ''));

    if ($dispatch_id === '') {
        return $current;
    }

    if (($current['dispatchId'] ?? '') !== '' && $dispatch_id !== $current['dispatchId']) {
        return $current;
    }

    $phase = trim((string) ($payload['phase'] ?? ''));
    $conclusion = trim((string) ($payload['conclusion'] ?? ''));
    $updated_at = gmdate('c');
    $status = $current;
    $status['dispatchId'] = $dispatch_id;
    $status['updatedAt'] = $updated_at;

    if ($phase === 'in_progress') {
        $started_at = trim((string) ($payload['startedAt'] ?? ''));
        $status['phase'] = 'in_progress';
        $status['message'] = trim((string) ($payload['message'] ?? '')) ?: meza_headless_build_status_message('in_progress');
        $status['ok'] = null;
        $status['conclusion'] = '';
        $status['startedAt'] = $started_at !== '' ? $started_at : ($status['startedAt'] ?: $updated_at);
        $status['runId'] = trim((string) ($payload['runId'] ?? ''));
        $status['runUrl'] = trim((string) ($payload['runUrl'] ?? ''));

        return meza_headless_build_store_status($status);
    }

    if ($phase === 'completed') {
        $completed_at = trim((string) ($payload['completedAt'] ?? ''));
        $message = trim((string) ($payload['message'] ?? ''));
        $elapsed = is_numeric($payload['elapsedSeconds'] ?? null) ? (int) $payload['elapsedSeconds'] : null;
        if ($elapsed === null) {
            $elapsed = meza_headless_build_diff_seconds(
                (string) ($status['dispatchedAt'] ?? ''),
                $completed_at !== '' ? $completed_at : $updated_at
            );
        }

        $status['phase'] = 'completed';
        $status['conclusion'] = $conclusion;
        $status['ok'] = $conclusion === 'success';
        $status['message'] = $message !== '' ? $message : meza_headless_build_status_message('completed', $conclusion);
        $status['completedAt'] = $completed_at !== '' ? $completed_at : $updated_at;
        $status['elapsedSeconds'] = $elapsed;
        $status['runId'] = trim((string) ($payload['runId'] ?? '')) ?: $status['runId'];
        $status['runUrl'] = trim((string) ($payload['runUrl'] ?? '')) ?: $status['runUrl'];

        return meza_headless_build_store_status($status);
    }

    return meza_headless_build_store_status($status);
}

function meza_headless_build_schedule_dispatch(): void
{
    if (!meza_headless_build_auto_dispatch_enabled()) {
        return;
    }

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

function meza_headless_build_queue_taxonomy_change(string $taxonomy): void
{
    $taxonomy = sanitize_key($taxonomy);
    if ($taxonomy === '') {
        return;
    }

    if ($taxonomy === 'nav_menu') {
        meza_headless_build_queue_global_change('menu');
        return;
    }

    $taxonomy_object = get_taxonomy($taxonomy);
    if (!($taxonomy_object instanceof WP_Taxonomy)) {
        return;
    }

    meza_headless_build_queue_changes([
        'paths' => ['/'],
        'types' => ['taxonomy', $taxonomy],
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

function meza_headless_build_dispatch_queue(bool $is_manual_dispatch = false): void
{
    if (!$is_manual_dispatch && !meza_headless_build_auto_dispatch_enabled()) {
        return;
    }

    if (!meza_headless_build_dispatch_enabled()) {
        return;
    }

    $queue = meza_headless_build_read_queue();

    if ($queue['paths'] === [] && $queue['slugs'] === [] && $queue['types'] === []) {
        return;
    }

    $token = meza_headless_build_dispatch_token();
    $url = meza_headless_build_dispatch_url();
    $dispatch_id = meza_headless_build_create_dispatch_id();
    $block_reason = meza_headless_build_dispatch_block_reason();

    if ($block_reason !== '' || $token === '' || $url === '') {
        meza_headless_build_store_result([
            'sent_at' => gmdate('c'),
            'ok' => false,
            'message' => $block_reason !== ''
                ? $block_reason
                : 'Headless build dispatch is missing token or repo configuration.',
            'queue' => $queue,
        ]);
        meza_headless_build_mark_dispatch_status(
            $dispatch_id,
            $queue,
            false,
            $block_reason !== ''
                ? $block_reason
                : 'Headless build dispatch is missing token or repo configuration.'
        );
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
            'dispatchId' => $dispatch_id,
            'callbackUrl' => meza_headless_build_status_callback_url(),
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
        meza_headless_build_mark_dispatch_status(
            $dispatch_id,
            $queue,
            false,
            $response->get_error_message()
        );
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
    meza_headless_build_mark_dispatch_status(
        $dispatch_id,
        $queue,
        $ok,
        $ok
            ? meza_headless_build_status_message('queued')
            : wp_remote_retrieve_body($response),
        $status
    );

    if ($ok) {
        delete_option(MEZA_HEADLESS_BUILD_QUEUE_OPTION);
        return;
    }

    meza_headless_build_schedule_dispatch();
}

function meza_headless_build_dispatch_now(array $fallback_changes = []): array
{
    if (!meza_headless_build_manual_dispatch_available()) {
        $block_reason = meza_headless_build_dispatch_block_reason();
        $result = [
            'sent_at' => gmdate('c'),
            'ok' => false,
            'message' => $block_reason !== ''
                ? $block_reason
                : 'Headless build dispatch is missing token or repo configuration.',
        ];

        meza_headless_build_store_result($result);

        return $result;
    }

    $queue = meza_headless_build_read_queue();
    $has_pending_changes = $queue['paths'] !== [] || $queue['slugs'] !== [] || $queue['types'] !== [];

    if (!$has_pending_changes) {
        $fallback_changes = is_array($fallback_changes) ? $fallback_changes : [];
        if ($fallback_changes === []) {
            $fallback_changes = [
                'paths' => ['/'],
                'types' => ['manual'],
            ];
        }

        meza_headless_build_queue_changes($fallback_changes);
    }

    meza_headless_build_dispatch_queue(true);

    return meza_headless_build_read_result();
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

add_action('transition_post_status', function ($new_status, $old_status, $post) {
    if (!$post instanceof WP_Post) {
        return;
    }

    if (wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID)) {
        return;
    }

    if ($old_status !== 'publish' || $new_status === 'publish') {
        return;
    }

    meza_headless_build_queue_changes(meza_headless_build_payload_for_post($post));
}, 20, 3);

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

add_action('created_term', function ($term_id, $tt_id, $taxonomy) {
    meza_headless_build_queue_taxonomy_change((string) $taxonomy);
}, 20, 3);

add_action('edited_term', function ($term_id, $tt_id, $taxonomy) {
    meza_headless_build_queue_taxonomy_change((string) $taxonomy);
}, 20, 3);

add_action('delete_term', function ($term_id, $tt_id, $taxonomy) {
    meza_headless_build_queue_taxonomy_change((string) $taxonomy);
}, 20, 3);

add_action('wp_ajax_meza_headless_build_dispatch_now', function (): void {
    if (!function_exists('meza_can_access_clear_cache') || !meza_can_access_clear_cache()) {
        wp_send_json_error([
            'message' => 'You do not have permission to sync content.',
        ], 403);
    }

    $nonce = isset($_REQUEST['nonce']) ? (string) wp_unslash($_REQUEST['nonce']) : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, MEZA_HEADLESS_BUILD_MANUAL_NONCE_ACTION)) {
        wp_send_json_error([
            'message' => 'Your sync session expired. Refresh wp-admin and try again.',
        ], 403);
    }

    $result = meza_headless_build_dispatch_now([
        'paths' => ['/'],
        'types' => ['manual'],
    ]);

    if (!empty($result['ok'])) {
        wp_send_json_success([
            'message' => 'Headless content sync dispatched successfully.',
            'result' => $result,
            'status' => meza_headless_build_read_status(),
        ]);
    }

    wp_send_json_error([
        'message' => (string) ($result['message'] ?? 'Unable to dispatch headless content sync.'),
        'result' => $result,
        'status' => meza_headless_build_read_status(),
    ]);
});

add_action('wp_ajax_' . MEZA_HEADLESS_BUILD_STATUS_POLL_ACTION, function (): void {
    if (!function_exists('meza_can_access_clear_cache') || !meza_can_access_clear_cache()) {
        wp_send_json_error([
            'message' => 'You do not have permission to view sync status.',
        ], 403);
    }

    wp_send_json_success([
        'status' => meza_headless_build_read_status(),
        'serverTime' => gmdate('c'),
    ]);
});

$meza_headless_build_status_callback = function (): void {
    $expected_secret = meza_headless_build_status_secret();
    $provided_secret = (string) ($_SERVER['HTTP_X_MEZA_HEADLESS_BUILD_STATUS_SECRET'] ?? '');

    if ($expected_secret === '' || !hash_equals($expected_secret, $provided_secret)) {
        wp_send_json_error([
            'message' => 'Invalid sync status secret.',
        ], 403);
    }

    $dispatch_id = trim((string) wp_unslash($_REQUEST['dispatchId'] ?? ''));
    if ($dispatch_id === '') {
        wp_send_json_error([
            'message' => 'Missing dispatchId.',
        ], 400);
    }

    $status = meza_headless_build_update_status_from_callback([
        'dispatchId' => $dispatch_id,
        'phase' => trim((string) wp_unslash($_REQUEST['phase'] ?? '')),
        'conclusion' => trim((string) wp_unslash($_REQUEST['conclusion'] ?? '')),
        'message' => trim((string) wp_unslash($_REQUEST['message'] ?? '')),
        'startedAt' => trim((string) wp_unslash($_REQUEST['startedAt'] ?? '')),
        'completedAt' => trim((string) wp_unslash($_REQUEST['completedAt'] ?? '')),
        'elapsedSeconds' => wp_unslash($_REQUEST['elapsedSeconds'] ?? null),
        'runId' => trim((string) wp_unslash($_REQUEST['runId'] ?? '')),
        'runUrl' => trim((string) wp_unslash($_REQUEST['runUrl'] ?? '')),
    ]);

    wp_send_json_success([
        'status' => $status,
        'serverTime' => gmdate('c'),
    ]);
};

add_action('wp_ajax_' . MEZA_HEADLESS_BUILD_STATUS_CALLBACK_ACTION, $meza_headless_build_status_callback);
add_action('wp_ajax_nopriv_' . MEZA_HEADLESS_BUILD_STATUS_CALLBACK_ACTION, $meza_headless_build_status_callback);
