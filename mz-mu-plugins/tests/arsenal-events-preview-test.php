<?php

define('ARSENAL_EVENTS_PREVIEW_TESTS', true);

$arsenal_events_preview_test_transients = [];
$arsenal_events_preview_test_posts = [];
$arsenal_events_preview_test_current_user_can = true;
$arsenal_events_preview_test_logged_in = true;

class WP_REST_Response
{
    private $data;
    private int $status;

    public function __construct($data = null, int $status = 200)
    {
        $this->data = $data;
        $this->status = $status;
    }

    public function get_data()
    {
        return $this->data;
    }

    public function get_status(): int
    {
        return $this->status;
    }
}

class Arsenal_Events_Preview_Test_Request
{
    private array $params;
    private array $headers;

    public function __construct(array $params = [], array $headers = [])
    {
        $this->params = $params;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }

    public function get_param(string $name)
    {
        return $this->params[$name] ?? null;
    }

    public function get_header(string $name): string
    {
        return (string) ($this->headers[strtolower($name)] ?? '');
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
    if ($hook_name === 'arsenal_events_preview_config') {
        $value['frontend_origin'] = 'https://frontend.example';
        $value['audience'] = 'https://frontend.example';
        $value['signing_secret'] = 'preview-signing-secret';
        $value['ttl_seconds'] = 300;
    }

    if ($hook_name === 'the_content') {
        return '<p>' . $value . '</p>';
    }

    return $value;
}

function register_rest_route($namespace, $route, array $args): void
{
}

function home_url($path = ''): string
{
    return 'https://cms.example/' . ltrim((string) $path, '/');
}

function admin_url($path = ''): string
{
    return 'https://cms.example/wp-admin/' . ltrim((string) $path, '/');
}

function wp_create_nonce($action): string
{
    return 'nonce-' . $action;
}

function wp_verify_nonce($nonce, $action): bool
{
    return $nonce === 'nonce-' . $action;
}

function is_user_logged_in(): bool
{
    global $arsenal_events_preview_test_logged_in;

    return $arsenal_events_preview_test_logged_in;
}

function current_user_can($capability, $object_id = null): bool
{
    global $arsenal_events_preview_test_current_user_can;

    return $capability === 'edit_post' && $object_id > 0 && $arsenal_events_preview_test_current_user_can;
}

function absint($value): int
{
    return abs((int) $value);
}

function get_post($post_id)
{
    global $arsenal_events_preview_test_posts;

    return $arsenal_events_preview_test_posts[(int) $post_id] ?? null;
}

function wp_is_post_revision($post_id)
{
    $post = get_post((int) $post_id);

    return $post && (string) ($post->post_type ?? '') === 'revision'
        ? (int) ($post->post_parent ?? 0)
        : false;
}

function set_transient($key, $value, $expiration): bool
{
    global $arsenal_events_preview_test_transients;

    $arsenal_events_preview_test_transients[$key] = $value;

    return true;
}

function get_transient($key)
{
    global $arsenal_events_preview_test_transients;

    return $arsenal_events_preview_test_transients[$key] ?? false;
}

function delete_transient($key): bool
{
    global $arsenal_events_preview_test_transients;

    unset($arsenal_events_preview_test_transients[$key]);

    return true;
}

function wp_json_encode($value): string
{
    return json_encode($value);
}

function wp_kses_post($value)
{
    return $value;
}

function meza_get_page_structured_content(int $post_id): array
{
    return ['cta' => ['title' => 'Preview CTA']];
}

function meza_get_page_blocks(int $post_id): array
{
    return [['type' => 'content', 'props' => ['html' => '<p>Block</p>']]];
}

function meza_get_canonical_seo_payload(int $post_id): array
{
    return ['title' => 'SEO', 'robots' => ['noindex' => false, 'nofollow' => false]];
}

function meza_get_yoast_metadata(int $post_id): array
{
    return ['title' => 'Yoast'];
}

function meza_render_post_content($post): string
{
    return '<p>' . (string) ($post->post_content ?? '') . '</p>';
}

function meza_get_post_data(int $post_id, string $post_type, bool $published_only = true): array
{
    $post = get_post($post_id);
    if (!$post || ($published_only && (string) $post->post_status !== 'publish')) {
        return [];
    }

    return [
        'id' => (int) $post->ID,
        'slug' => (string) $post->post_name,
        'title' => (string) $post->post_title,
        'status' => (string) $post->post_status,
        'content' => meza_render_post_content($post),
        'seo' => meza_get_canonical_seo_payload($post_id),
    ];
}

require_once __DIR__ . '/../arsenal-events-preview.php';

function arsenal_events_preview_test_reset(): void
{
    global $arsenal_events_preview_test_transients,
        $arsenal_events_preview_test_posts,
        $arsenal_events_preview_test_current_user_can,
        $arsenal_events_preview_test_logged_in;

    $arsenal_events_preview_test_transients = [];
    $arsenal_events_preview_test_current_user_can = true;
    $arsenal_events_preview_test_logged_in = true;
    $arsenal_events_preview_test_posts = [
        10 => (object) [
            'ID' => 10,
            'post_type' => 'page',
            'post_name' => 'contact',
            'post_title' => 'Contact Draft',
            'post_status' => 'draft',
            'post_content' => 'Draft contact body',
            'post_excerpt' => '',
        ],
        11 => (object) [
            'ID' => 11,
            'post_type' => 'race',
            'post_name' => 'wildcat-preview',
            'post_title' => 'Wildcat Draft',
            'post_status' => 'draft',
            'post_content' => 'Draft race body',
            'post_excerpt' => '',
        ],
        12 => (object) [
            'ID' => 12,
            'post_type' => 'resource',
            'post_name' => 'timing-preview',
            'post_title' => 'Timing Draft',
            'post_status' => 'draft',
            'post_content' => 'Draft resource body',
            'post_excerpt' => '',
        ],
        20 => (object) [
            'ID' => 20,
            'post_type' => 'revision',
            'post_parent' => 10,
            'post_name' => '10-revision-v1',
            'post_title' => 'Contact Revision',
            'post_status' => 'inherit',
            'post_content' => 'Revision contact body',
            'post_excerpt' => '',
        ],
    ];
}

function arsenal_events_preview_test_assert($condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function arsenal_events_preview_test_assert_same($expected, $actual, string $message): void
{
    if ($expected === $actual) {
        return;
    }

    fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
    exit(1);
}

function arsenal_events_preview_test(string $name, callable $run): void
{
    arsenal_events_preview_test_reset();
    $run();
    echo "ok - {$name}\n";
}

arsenal_events_preview_test('rejects unauthenticated and insufficient capability preview requests', function (): void {
    global $arsenal_events_preview_test_logged_in, $arsenal_events_preview_test_current_user_can;

    $arsenal_events_preview_test_logged_in = false;
    arsenal_events_preview_test_assert_same(false, arsenal_events_preview_user_can_preview(10), 'Logged-out users cannot preview.');

    $arsenal_events_preview_test_logged_in = true;
    $arsenal_events_preview_test_current_user_can = false;
    arsenal_events_preview_test_assert_same(false, arsenal_events_preview_user_can_preview(10), 'Users without edit_post cannot preview.');
});

arsenal_events_preview_test('creates valid page, race, and resource preview assertions', function (): void {
    $config = arsenal_events_preview_config();

    foreach ([10 => '/contact', 11 => '/races/wildcat-preview', 12 => '/resources/timing-preview'] as $post_id => $route) {
        $assertion = arsenal_events_preview_create_assertion(get_post($post_id), 0, $config, 1000);
        $verified = arsenal_events_preview_verify_assertion($assertion, $config['signing_secret'], 1001);

        arsenal_events_preview_test_assert(!empty($verified['ok']), 'Assertion should verify.');
        arsenal_events_preview_test_assert_same($route, $verified['payload']['route'], 'Assertion route should be scoped.');
    }
});

arsenal_events_preview_test('rejects token expiry, malformed signature, wrong audience, and route mismatch', function (): void {
    $config = arsenal_events_preview_config();
    $assertion = arsenal_events_preview_create_assertion(get_post(10), 0, $config, 1000);

    arsenal_events_preview_test_assert_same(false, arsenal_events_preview_verify_assertion($assertion, $config['signing_secret'], 2000)['ok'], 'Expired assertions fail.');
    arsenal_events_preview_test_assert_same(false, arsenal_events_preview_verify_assertion($assertion . 'x', $config['signing_secret'], 1001)['ok'], 'Tampered assertions fail.');

    $payload = arsenal_events_preview_assertion_payload(get_post(10), 0, $config, time());
    $payload['aud'] = 'https://wrong.example';
    $wrong_audience = arsenal_events_preview_sign_payload($payload, $config['signing_secret']);
    $response = arsenal_events_preview_rest_content(new Arsenal_Events_Preview_Test_Request([], [
        'x-arsenal-preview-assertion' => $wrong_audience,
    ]));
    arsenal_events_preview_test_assert_same(401, $response->get_status(), 'Wrong audience fails.');

    $payload = arsenal_events_preview_assertion_payload(get_post(10), 0, $config, time());
    $payload['route'] = '/wrong-route';
    $route_mismatch = arsenal_events_preview_sign_payload($payload, $config['signing_secret']);
    $response = arsenal_events_preview_rest_content(new Arsenal_Events_Preview_Test_Request([], [
        'x-arsenal-preview-assertion' => $route_mismatch,
    ]));
    arsenal_events_preview_test_assert_same(404, $response->get_status(), 'Route mismatch fails.');
});

arsenal_events_preview_test('exchanges one-time code without leaking assertion in redirect URL', function (): void {
    $config = arsenal_events_preview_config();
    $assertion = arsenal_events_preview_create_assertion(get_post(10), 0, $config, 1000);
    $code = arsenal_events_preview_store_assertion($assertion, 300);
    $url = arsenal_events_preview_frontend_start_url($code, $config);

    arsenal_events_preview_test_assert(strpos($url, $assertion) === false, 'Frontend start URL must not contain assertion.');
    arsenal_events_preview_test_assert(strpos($url, $config['signing_secret']) === false, 'Frontend start URL must not contain signing secret.');

    $first = arsenal_events_preview_rest_exchange(new Arsenal_Events_Preview_Test_Request(['code' => $code]));
    arsenal_events_preview_test_assert_same(200, $first->get_status(), 'First exchange succeeds.');
    arsenal_events_preview_test_assert_same($assertion, $first->get_data()['assertion'], 'Exchange returns assertion.');

    $second = arsenal_events_preview_rest_exchange(new Arsenal_Events_Preview_Test_Request(['code' => $code]));
    arsenal_events_preview_test_assert_same(401, $second->get_status(), 'Replay exchange fails.');
});

arsenal_events_preview_test('serves authorized preview content with noindex robots and revision body', function (): void {
    $config = arsenal_events_preview_config();
    $assertion = arsenal_events_preview_create_assertion(get_post(10), 20, $config, time());
    $response = arsenal_events_preview_rest_content(new Arsenal_Events_Preview_Test_Request([], [
        'x-arsenal-preview-assertion' => $assertion,
    ]));
    $data = $response->get_data();

    arsenal_events_preview_test_assert_same(200, $response->get_status(), 'Preview content succeeds.');
    arsenal_events_preview_test_assert_same('page', $data['post_type'], 'Page preview is identified.');
    arsenal_events_preview_test_assert_same('Contact Revision', $data['data']['title'], 'Revision title is used.');
    arsenal_events_preview_test_assert_same(['noindex' => true, 'nofollow' => true], $data['data']['seo']['robots'], 'Preview content is noindex.');
});
