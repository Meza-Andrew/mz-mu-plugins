<?php

define('ARSENAL_EVENTS_CORS_TESTS', true);

$arsenal_events_cors_test_environment = 'production';

class WP_Error
{
    private string $code;
    private string $message;
    private array $data;

    public function __construct(string $code = '', string $message = '', array $data = [])
    {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    public function get_error_code(): string
    {
        return $this->code;
    }

    public function get_error_message(): string
    {
        return $this->message;
    }

    public function get_error_data(): array
    {
        return $this->data;
    }
}

class WP_REST_Response
{
    private int $status;
    private array $headers = [];

    public function __construct($data = null, int $status = 200)
    {
        $this->status = $status;
    }

    public function header(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    public function get_status(): int
    {
        return $this->status;
    }

    public function get_headers(): array
    {
        return $this->headers;
    }
}

class Arsenal_Events_Cors_Test_Request
{
    private string $route;
    private string $method;

    public function __construct(string $route, string $method = 'GET')
    {
        $this->route = $route;
        $this->method = $method;
    }

    public function get_route(): string
    {
        return $this->route;
    }

    public function get_method(): string
    {
        return $this->method;
    }
}

function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1): void
{
}

function wp_get_environment_type(): string
{
    global $arsenal_events_cors_test_environment;

    return $arsenal_events_cors_test_environment;
}

require_once __DIR__ . '/../arsenal-events-cors.php';

function arsenal_events_cors_test_reset(string $environment = 'production'): void
{
    global $arsenal_events_cors_test_environment;

    $arsenal_events_cors_test_environment = $environment;
    unset($_SERVER['HTTP_ORIGIN'], $_SERVER['REQUEST_URI']);
    putenv('ARSENAL_EVENTS_FRONTEND_PRODUCTION_ORIGIN');
    putenv('ARSENAL_EVENTS_FRONTEND_PREVIEW_ORIGINS');
}

function arsenal_events_cors_test_assert($condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function arsenal_events_cors_test_assert_same($expected, $actual, string $message): void
{
    if ($expected === $actual) {
        return;
    }

    fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
    exit(1);
}

function arsenal_events_cors_test(string $name, callable $run): void
{
    arsenal_events_cors_test_reset();
    $run();
    echo "ok - {$name}\n";
}

arsenal_events_cors_test('normalizes origins for exact matching', function (): void {
    arsenal_events_cors_test_assert_same(
        'https://example.com',
        arsenal_events_cors_normalize_origin('https://EXAMPLE.com:443'),
        'Default HTTPS ports should normalize away'
    );

    arsenal_events_cors_test_assert_same(
        'http://example.com',
        arsenal_events_cors_normalize_origin('http://example.com:80'),
        'Default HTTP ports should normalize away'
    );

    arsenal_events_cors_test_assert_same(
        null,
        arsenal_events_cors_normalize_origin('https://example.com/path'),
        'Origins with paths should be rejected'
    );
});

arsenal_events_cors_test('allows approved production origin', function (): void {
    putenv('ARSENAL_EVENTS_FRONTEND_PRODUCTION_ORIGIN=https://frontend.example');

    $_SERVER['REQUEST_URI'] = '/wp-json/meza/v1/settings';
    $_SERVER['HTTP_ORIGIN'] = 'https://frontend.example';

    arsenal_events_cors_test_assert_same(
        true,
        arsenal_events_cors_is_allowed_origin('https://frontend.example'),
        'Configured production origin should be allowed'
    );
    arsenal_events_cors_test_assert_same(
        null,
        arsenal_events_cors_validate_origin_for_request(null),
        'Approved origin should not block REST auth'
    );
});

arsenal_events_cors_test('rejects unapproved origin', function (): void {
    putenv('ARSENAL_EVENTS_FRONTEND_PRODUCTION_ORIGIN=https://frontend.example');

    $_SERVER['REQUEST_URI'] = '/wp-json/meza/v1/settings';
    $_SERVER['HTTP_ORIGIN'] = 'https://attacker.example';

    $result = arsenal_events_cors_validate_origin_for_request(null);
    arsenal_events_cors_test_assert($result instanceof WP_Error, 'Rejected origin should return WP_Error');
    arsenal_events_cors_test_assert_same('cors_origin_not_allowed', $result->get_error_code(), 'Rejected origin should use CORS error code');
    arsenal_events_cors_test_assert_same(['status' => 403], $result->get_error_data(), 'Rejected origin should be forbidden');
});

arsenal_events_cors_test('rejects malformed origin', function (): void {
    putenv('ARSENAL_EVENTS_FRONTEND_PRODUCTION_ORIGIN=https://frontend.example');

    $_SERVER['REQUEST_URI'] = '/wp-json/meza/v1/settings';
    $_SERVER['HTTP_ORIGIN'] = 'not-a-valid-origin';

    $result = arsenal_events_cors_validate_origin_for_request(null);
    arsenal_events_cors_test_assert($result instanceof WP_Error, 'Malformed origin should return WP_Error');
});

arsenal_events_cors_test('allows missing Origin without adding CORS headers', function (): void {
    putenv('ARSENAL_EVENTS_FRONTEND_PRODUCTION_ORIGIN=https://frontend.example');

    $_SERVER['REQUEST_URI'] = '/wp-json/meza/v1/settings';

    $response = new WP_REST_Response(null, 200);
    $returned = arsenal_events_cors_add_response_headers($response, null, new Arsenal_Events_Cors_Test_Request('/meza/v1/settings'));

    arsenal_events_cors_test_assert_same(null, arsenal_events_cors_validate_origin_for_request(null), 'Missing Origin should not block server requests');
    arsenal_events_cors_test_assert_same([], $returned->get_headers(), 'Missing Origin should not receive browser CORS headers');
});

arsenal_events_cors_test('allows no-Origin server request without CORS authentication', function (): void {
    $_SERVER['REQUEST_URI'] = '/wp-json/meza/v1/races';

    arsenal_events_cors_test_assert_same(
        null,
        arsenal_events_cors_validate_origin_for_request(null),
        'No-Origin server request should be allowed even with no browser origin config'
    );
});

arsenal_events_cors_test('allows documented local development origins only in development', function (): void {
    arsenal_events_cors_test_reset('local');

    arsenal_events_cors_test_assert_same(
        true,
        arsenal_events_cors_is_allowed_origin('http://localhost:3001'),
        'Local development origin should be allowed in local environment'
    );

    arsenal_events_cors_test_reset('production');

    arsenal_events_cors_test_assert_same(
        false,
        arsenal_events_cors_is_allowed_origin('http://localhost:3001'),
        'Local development origin should not be allowed in production'
    );
});

arsenal_events_cors_test('allows configured production and preview origins', function (): void {
    putenv('ARSENAL_EVENTS_FRONTEND_PRODUCTION_ORIGIN=https://frontend.example');
    putenv('ARSENAL_EVENTS_FRONTEND_PREVIEW_ORIGINS=https://preview-one.example, https://preview-two.example');

    arsenal_events_cors_test_assert_same(true, arsenal_events_cors_is_allowed_origin('https://frontend.example'), 'Production origin should be allowed');
    arsenal_events_cors_test_assert_same(true, arsenal_events_cors_is_allowed_origin('https://preview-one.example'), 'First preview origin should be allowed');
    arsenal_events_cors_test_assert_same(true, arsenal_events_cors_is_allowed_origin('https://preview-two.example'), 'Second preview origin should be allowed');
});

arsenal_events_cors_test('fails closed in production with absent or invalid configuration', function (): void {
    arsenal_events_cors_test_assert_same([], arsenal_events_cors_configured_origins('production'), 'Absent production config should allow no origins');
    arsenal_events_cors_test_assert_same(false, arsenal_events_cors_is_allowed_origin('https://frontend.example'), 'Absent production config should reject browser origins');

    putenv('ARSENAL_EVENTS_FRONTEND_PRODUCTION_ORIGIN=https://frontend.example/path');
    putenv('ARSENAL_EVENTS_FRONTEND_PREVIEW_ORIGINS=not-a-url');

    arsenal_events_cors_test_assert_same([], arsenal_events_cors_configured_origins('production'), 'Invalid production config should allow no origins');
});

arsenal_events_cors_test('handles approved and rejected preflight requests', function (): void {
    putenv('ARSENAL_EVENTS_FRONTEND_PRODUCTION_ORIGIN=https://frontend.example');

    $_SERVER['HTTP_ORIGIN'] = 'https://frontend.example';
    $response = arsenal_events_cors_preflight_response(null, null, new Arsenal_Events_Cors_Test_Request('/meza/v1/settings', 'OPTIONS'));
    arsenal_events_cors_test_assert($response instanceof WP_REST_Response, 'Approved preflight should return a REST response');
    arsenal_events_cors_test_assert_same(204, $response->get_status(), 'Approved preflight should return 204');
    arsenal_events_cors_test_assert_same('https://frontend.example', $response->get_headers()['Access-Control-Allow-Origin'] ?? '', 'Approved preflight should echo normalized origin');
    arsenal_events_cors_test_assert_same('GET, POST, OPTIONS', $response->get_headers()['Access-Control-Allow-Methods'] ?? '', 'Approved preflight should include allowed methods');

    $_SERVER['HTTP_ORIGIN'] = 'https://attacker.example';
    $rejected = arsenal_events_cors_preflight_response(null, null, new Arsenal_Events_Cors_Test_Request('/meza/v1/settings', 'OPTIONS'));
    arsenal_events_cors_test_assert($rejected instanceof WP_Error, 'Rejected preflight should return WP_Error');
});

echo "Arsenal Events CORS tests passed\n";
