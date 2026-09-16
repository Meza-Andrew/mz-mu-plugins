<?php

define('ARSENAL_EVENTS_REVALIDATION_TESTS', true);

$arsenal_events_test_options = [];
$arsenal_events_test_transients = [];
$arsenal_events_test_remote_responses = [];
$arsenal_events_test_remote_requests = [];
$arsenal_events_test_sleep_delays = [];
$arsenal_events_test_posts = [];
$arsenal_events_test_attachment_refs = [];

class WP_Error
{
    private string $message;

    public function __construct(string $code = '', string $message = '')
    {
        $this->message = $message !== '' ? $message : $code;
    }

    public function get_error_message(): string
    {
        return $this->message;
    }
}

function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1): void
{
}

function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1): void
{
}

function apply_filters($hook_name, $value)
{
    global $arsenal_events_test_sleep_delays;

    if ($hook_name === 'arsenal_events_revalidation_retry_delay_ms') {
        $arsenal_events_test_sleep_delays[] = $value;

        return 0;
    }

    return $value;
}

function get_option($name, $default = null)
{
    global $arsenal_events_test_options;

    return array_key_exists($name, $arsenal_events_test_options) ? $arsenal_events_test_options[$name] : $default;
}

function get_transient($name)
{
    global $arsenal_events_test_transients;

    return $arsenal_events_test_transients[$name] ?? false;
}

function set_transient($name, $value, $expiration): bool
{
    global $arsenal_events_test_transients;

    $arsenal_events_test_transients[$name] = $value;

    return true;
}

function wp_json_encode($value): string
{
    return json_encode($value);
}

function wp_remote_post($url, array $args)
{
    global $arsenal_events_test_remote_requests, $arsenal_events_test_remote_responses;

    $arsenal_events_test_remote_requests[] = [
        'url' => $url,
        'args' => $args,
    ];

    $response = array_shift($arsenal_events_test_remote_responses);
    if ($response instanceof WP_Error) {
        return $response;
    }

    return [
        'response' => [
            'code' => is_int($response) ? $response : 200,
        ],
    ];
}

function wp_remote_retrieve_response_code($response): int
{
    return (int) ($response['response']['code'] ?? 0);
}

function is_wp_error($thing): bool
{
    return $thing instanceof WP_Error;
}

function get_post($post_id)
{
    global $arsenal_events_test_posts;

    return $arsenal_events_test_posts[$post_id] ?? null;
}

function get_posts(array $args): array
{
    global $arsenal_events_test_attachment_refs;

    $needle = (int) ($args['meta_query'][0]['value'] ?? 0);

    return $arsenal_events_test_attachment_refs[$needle] ?? [];
}

require_once __DIR__ . '/../arsenal-events-revalidation.php';

function arsenal_events_test_reset(): void
{
    global $arsenal_events_test_options,
        $arsenal_events_test_transients,
        $arsenal_events_test_remote_responses,
        $arsenal_events_test_remote_requests,
        $arsenal_events_test_sleep_delays,
        $arsenal_events_test_posts,
        $arsenal_events_test_attachment_refs;

    $arsenal_events_test_options = [
        'arsenal_events_revalidation_endpoint' => 'https://frontend.example/api/revalidate?secret=do-not-log',
        'arsenal_events_revalidation_secret' => 'runtime-secret',
        'arsenal_events_revalidation_retry_count' => 2,
        'arsenal_events_revalidation_retry_delay_ms' => 100,
    ];
    $arsenal_events_test_transients = [];
    $arsenal_events_test_remote_responses = [];
    $arsenal_events_test_remote_requests = [];
    $arsenal_events_test_sleep_delays = [];
    $arsenal_events_test_posts = [];
    $arsenal_events_test_attachment_refs = [];
    $GLOBALS['arsenal_events_revalidation_queue'] = ['paths' => [], 'reasons' => []];
    $GLOBALS['arsenal_events_revalidation_old_posts'] = [];
}

function arsenal_events_test_assert($condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function arsenal_events_test_assert_same($expected, $actual, string $message): void
{
    if ($expected === $actual) {
        return;
    }

    fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
    exit(1);
}

function arsenal_events_test(string $name, callable $run): void
{
    arsenal_events_test_reset();
    $run();
    echo "ok - {$name}\n";
}

arsenal_events_test('maps page slug and status transitions', function (): void {
    arsenal_events_test_assert_same(
        ['/contact'],
        arsenal_events_revalidation_paths_for_post_change('page', 'contact', 'publish', 'contact', 'publish'),
        'Approved published pages should invalidate their exact path'
    );

    arsenal_events_test_assert_same(
        ['/contact'],
        arsenal_events_revalidation_paths_for_post_change('page', 'contact', 'publish', 'draft-contact', 'draft'),
        'Publishing an approved page should invalidate the new path'
    );

    arsenal_events_test_assert_same(
        ['/contact'],
        arsenal_events_revalidation_paths_for_post_change('page', 'draft-contact', 'draft', 'contact', 'publish'),
        'Unpublishing an approved page should invalidate the old path'
    );

    arsenal_events_test_assert_same(
        [],
        arsenal_events_revalidation_paths_for_post_change('page', 'about', 'publish', 'faq', 'publish'),
        'Unapproved page paths should not be sent to the frontend'
    );
});

arsenal_events_test('maps race and resource paths', function (): void {
    arsenal_events_test_assert_same(
        ['/races/wildcat', '/races', '/races-and-results', '/'],
        arsenal_events_revalidation_paths_for_post_change('race', 'wildcat', 'publish', 'wildcat', 'publish'),
        'Race changes should invalidate detail, archive, results, and home'
    );

    arsenal_events_test_assert_same(
        ['/resources/timing-services', '/resources', '/for-race-directors', '/'],
        arsenal_events_revalidation_paths_for_post_change('resource', 'timing-services', 'publish', 'timing-services', 'publish'),
        'Resource changes should invalidate detail, archive, Race Director, and home'
    );
});

arsenal_events_test('maps settings, option groups, and attachments', function (): void {
    global $arsenal_events_test_posts, $arsenal_events_test_attachment_refs;

    arsenal_events_test_assert_same(
        ['/', '/contact', '/races', '/resources', '/races-and-results', '/for-race-directors'],
        arsenal_events_revalidation_paths_for_option('options_site_email'),
        'Global settings should use broad layout invalidation'
    );

    arsenal_events_test_assert_same(
        ['/for-race-directors', '/'],
        arsenal_events_revalidation_paths_for_option('options_rd_hero_image'),
        'Race Director options should invalidate Race Director and home'
    );

    arsenal_events_test_assert_same(
        ['/for-race-directors', '/'],
        arsenal_events_revalidation_paths_for_option('_options_rd_hero_image'),
        'Race Director ACF field key options should invalidate Race Director and home'
    );

    arsenal_events_test_assert_same(
        ['/races-and-results', '/races', '/'],
        arsenal_events_revalidation_paths_for_option('options_rr_hero_image'),
        'Race Results options should invalidate results, races, and home'
    );

    arsenal_events_test_assert_same(
        ['/races-and-results', '/races', '/'],
        arsenal_events_revalidation_paths_for_option('_options_rr_hero_image'),
        'Race Results ACF field key options should invalidate results, races, and home'
    );

    $arsenal_events_test_posts[44] = (object) [
        'post_type' => 'race',
        'post_name' => 'referencing-race',
        'post_status' => 'publish',
    ];
    $arsenal_events_test_attachment_refs[236] = [44];

    arsenal_events_test_assert_same(
        ['/races/referencing-race', '/races', '/races-and-results', '/'],
        arsenal_events_revalidation_paths_for_attachment(236),
        'Resolvable attachments should invalidate known referencing content'
    );

    arsenal_events_test_assert_same(
        ['/', '/races', '/resources', '/races-and-results', '/for-race-directors'],
        arsenal_events_revalidation_paths_for_attachment(999),
        'Unresolved attachments should use conservative invalidation'
    );
});

arsenal_events_test('dispatches successful single and batched requests with header auth', function (): void {
    global $arsenal_events_test_remote_responses, $arsenal_events_test_remote_requests;

    $arsenal_events_test_remote_responses = [200];
    $result = arsenal_events_revalidation_dispatch_paths(['/contact', '/races'], 'test');

    arsenal_events_test_assert_same('success', $result['status'], 'Successful dispatch should report success');
    arsenal_events_test_assert_same(['/contact', '/races'], $result['paths'], 'Batched paths should be preserved');
    arsenal_events_test_assert_same('runtime-secret', $arsenal_events_test_remote_requests[0]['args']['headers']['X-Arsenal-Revalidation-Secret'], 'Secret should be sent in the configured header');
    arsenal_events_test_assert_same(null, $arsenal_events_test_remote_requests[0]['args']['headers']['Authorization'] ?? null, 'Authorization header should not be added implicitly');

    $body = json_decode($arsenal_events_test_remote_requests[0]['args']['body'], true);
    arsenal_events_test_assert_same(['/contact', '/races'], $body['paths'], 'Request body should include batched paths');
});

arsenal_events_test('missing configuration disables dispatch without CMS mutation', function (): void {
    global $arsenal_events_test_options, $arsenal_events_test_remote_requests, $arsenal_events_test_transients;

    $arsenal_events_test_options = [];
    $result = arsenal_events_revalidation_dispatch_paths(['/contact'], 'test');

    arsenal_events_test_assert_same('disabled', $result['status'], 'Missing endpoint or secret should disable dispatch');
    arsenal_events_test_assert_same([], $arsenal_events_test_remote_requests, 'Disabled dispatch should not call remote endpoint');
    arsenal_events_test_assert_same([], $arsenal_events_test_transients, 'Disabled dispatch should not write dedupe transients');
});

arsenal_events_test('deduplicates hook storms before dispatch', function (): void {
    global $arsenal_events_test_remote_responses, $arsenal_events_test_remote_requests;

    $arsenal_events_test_remote_responses = [200];
    arsenal_events_revalidation_queue(['/contact', '/contact'], 'save_post');
    arsenal_events_revalidation_queue(['/contact', '/races'], 'yoast_meta');
    $result = arsenal_events_revalidation_flush_queue();

    arsenal_events_test_assert_same('success', $result['status'], 'Queued dispatch should succeed');
    arsenal_events_test_assert_same(['/contact', '/races'], $result['paths'], 'Queue should suppress duplicate paths');
    arsenal_events_test_assert_same(1, count($arsenal_events_test_remote_requests), 'Queue should flush as one remote request');

    $duplicate = arsenal_events_revalidation_dispatch_paths(['/races', '/contact'], 'duplicate');
    arsenal_events_test_assert_same('duplicate', $duplicate['status'], 'Recent identical dispatch should be suppressed');
});

arsenal_events_test('does not retry permanent 4xx failures', function (): void {
    global $arsenal_events_test_remote_responses, $arsenal_events_test_remote_requests;

    $arsenal_events_test_remote_responses = [401, 200];
    $result = arsenal_events_revalidation_dispatch_paths(['/contact'], 'test');

    arsenal_events_test_assert_same('permanent_failure', $result['status'], 'Auth failures should be permanent failures');
    arsenal_events_test_assert_same(1, count($arsenal_events_test_remote_requests), '4xx failures should not retry');
});

arsenal_events_test('retries 429 and 5xx until recovery or exhaustion', function (): void {
    global $arsenal_events_test_remote_responses, $arsenal_events_test_remote_requests, $arsenal_events_test_sleep_delays;

    $arsenal_events_test_remote_responses = [429, 500, 200];
    $result = arsenal_events_revalidation_dispatch_paths(['/races'], 'test');

    arsenal_events_test_assert_same('success', $result['status'], 'Retryable responses should recover when a later attempt succeeds');
    arsenal_events_test_assert_same(3, count($arsenal_events_test_remote_requests), '429 and 5xx should retry');
    arsenal_events_test_assert_same([100, 200], $arsenal_events_test_sleep_delays, 'Retry delays should use bounded backoff schedule');

    arsenal_events_test_reset();
    global $arsenal_events_test_remote_responses, $arsenal_events_test_remote_requests;
    $arsenal_events_test_remote_responses = [500, 500, 500];
    $result = arsenal_events_revalidation_dispatch_paths(['/races'], 'test');

    arsenal_events_test_assert_same('retry_exhausted', $result['status'], 'Repeated 5xx responses should exhaust retries');
    arsenal_events_test_assert_same(3, count($arsenal_events_test_remote_requests), 'Retry count should bound attempts');
});

arsenal_events_test('retries transport and timeout failures', function (): void {
    global $arsenal_events_test_remote_responses, $arsenal_events_test_remote_requests;

    $arsenal_events_test_remote_responses = [new WP_Error('timeout', 'request timed out'), 200];
    $result = arsenal_events_revalidation_dispatch_paths(['/resources'], 'test');

    arsenal_events_test_assert_same('success', $result['status'], 'Transport failures should recover when retry succeeds');
    arsenal_events_test_assert_same(2, count($arsenal_events_test_remote_requests), 'Transport failures should retry');

    arsenal_events_test_reset();
    global $arsenal_events_test_remote_responses;
    $arsenal_events_test_remote_responses = [
        new WP_Error('timeout', 'request timed out'),
        new WP_Error('timeout', 'request timed out'),
        new WP_Error('timeout', 'request timed out'),
    ];
    $result = arsenal_events_revalidation_dispatch_paths(['/resources'], 'test');

    arsenal_events_test_assert_same('transport_failure', $result['status'], 'Repeated transport failures should exhaust retries');
});

arsenal_events_test('redacts secret-bearing URL queries', function (): void {
    arsenal_events_test_assert_same(
        'https://frontend.example/api/revalidate?[redacted]',
        arsenal_events_revalidation_redact_url('https://frontend.example/api/revalidate?secret=secret-value&token=abc'),
        'Logged URLs should redact query strings'
    );
});

arsenal_events_test('strictly rejects malformed and unapproved revalidation paths', function (): void {
    foreach (['/', '/contact', '/races', '/races/wildcat-5k-2026-09-19', '/resources/timing-services', '/races-and-results', '/for-race-directors'] as $path) {
        arsenal_events_test_assert_same($path, arsenal_events_revalidation_normalize_path($path), "{$path} should be allowed");
    }

    foreach ([
        'contact',
        '/about',
        '/races/../contact',
        '/races/%2e%2e/contact',
        '/resources/timing?draft=true',
        '/resources/timing#hash',
        'https://frontend.example/contact',
        '//frontend.example/contact',
    ] as $path) {
        arsenal_events_test_assert_same(null, arsenal_events_revalidation_normalize_path($path), "{$path} should be rejected");
    }
});

echo "Arsenal Events revalidation tests passed\n";
