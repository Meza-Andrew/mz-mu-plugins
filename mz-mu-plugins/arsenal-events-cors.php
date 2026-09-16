<?php

/**
 * Arsenal Events REST CORS runtime.
 *
 * CORS is browser access control only. It must not be treated as
 * server-to-server authentication for public REST reads or revalidation.
 */

if (!defined('ABSPATH') && !defined('ARSENAL_EVENTS_CORS_TESTS')) {
    exit;
}

function arsenal_events_cors_starts_with(string $value, string $prefix): bool
{
    return substr($value, 0, strlen($prefix)) === $prefix;
}

function arsenal_events_cors_config_value(string $key, $default = '')
{
    $constant = 'ARSENAL_EVENTS_FRONTEND_' . strtoupper($key);
    if (defined($constant)) {
        return constant($constant);
    }

    $env = getenv($constant);
    if ($env !== false && $env !== '') {
        return $env;
    }

    return $default;
}

function arsenal_events_cors_environment_type(): string
{
    if (function_exists('wp_get_environment_type')) {
        return strtolower((string) wp_get_environment_type());
    }

    if (defined('WP_ENVIRONMENT_TYPE')) {
        return strtolower((string) constant('WP_ENVIRONMENT_TYPE'));
    }

    $env = getenv('WP_ENVIRONMENT_TYPE');
    if ($env !== false && $env !== '') {
        return strtolower((string) $env);
    }

    return 'production';
}

function arsenal_events_cors_is_development_environment(?string $environment = null): bool
{
    $environment = strtolower((string) ($environment ?? arsenal_events_cors_environment_type()));

    return in_array($environment, ['local', 'development'], true);
}

function arsenal_events_cors_local_origins(): array
{
    return [
        'http://localhost:3000',
        'http://localhost:3001',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:3001',
    ];
}

function arsenal_events_cors_origin_candidates($value): array
{
    if (is_array($value)) {
        return array_values($value);
    }

    if (!is_string($value)) {
        return [];
    }

    return preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

function arsenal_events_cors_normalize_origin($origin): ?string
{
    if (!is_string($origin)) {
        return null;
    }

    $origin = trim($origin);
    if ($origin === '' || strtolower($origin) === 'null' || preg_match('/[\x00-\x1F\x7F]/', $origin)) {
        return null;
    }

    $parts = parse_url($origin);
    if (!is_array($parts)) {
        return null;
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        return null;
    }

    foreach (['user', 'pass', 'path', 'query', 'fragment'] as $forbidden_part) {
        if (array_key_exists($forbidden_part, $parts) && (string) $parts[$forbidden_part] !== '') {
            return null;
        }
    }

    $port = $parts['port'] ?? null;
    if ($port !== null) {
        $port = (int) $port;
        if ($port < 1 || $port > 65535) {
            return null;
        }
    }

    $normalized = $scheme . '://' . $host;
    if ($port !== null && !(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))) {
        $normalized .= ':' . $port;
    }

    return $normalized;
}

function arsenal_events_cors_configured_origins(?string $environment = null): array
{
    $environment = strtolower((string) ($environment ?? arsenal_events_cors_environment_type()));
    $origins = [];

    $configured_values = array_merge(
        arsenal_events_cors_origin_candidates(arsenal_events_cors_config_value('production_origin', '')),
        arsenal_events_cors_origin_candidates(arsenal_events_cors_config_value('preview_origins', ''))
    );

    foreach ($configured_values as $candidate) {
        $normalized = arsenal_events_cors_normalize_origin($candidate);
        if ($normalized !== null) {
            $origins[$normalized] = true;
        }
    }

    if (arsenal_events_cors_is_development_environment($environment)) {
        foreach (arsenal_events_cors_local_origins() as $local_origin) {
            $origins[$local_origin] = true;
        }
    }

    return array_keys($origins);
}

function arsenal_events_cors_is_allowed_origin($origin, ?string $environment = null): bool
{
    $normalized = arsenal_events_cors_normalize_origin($origin);
    if ($normalized === null) {
        return false;
    }

    return in_array($normalized, arsenal_events_cors_configured_origins($environment), true);
}

function arsenal_events_cors_request_origin(): string
{
    return isset($_SERVER['HTTP_ORIGIN']) ? trim((string) $_SERVER['HTTP_ORIGIN']) : '';
}

function arsenal_events_cors_is_meza_rest_route($request = null): bool
{
    if (is_object($request) && method_exists($request, 'get_route')) {
        return arsenal_events_cors_starts_with((string) $request->get_route(), '/meza/v1/');
    }

    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');

    return strpos($uri, '/wp-json/meza/v1/') !== false || strpos($uri, 'rest_route=/meza/v1/') !== false;
}

function arsenal_events_cors_error(): WP_Error
{
    return new WP_Error(
        'cors_origin_not_allowed',
        'Origin not allowed to access this resource',
        ['status' => 403]
    );
}

function arsenal_events_cors_validate_origin_for_request($result)
{
    if (!arsenal_events_cors_is_meza_rest_route()) {
        return $result;
    }

    $origin = arsenal_events_cors_request_origin();
    if ($origin === '') {
        return $result;
    }

    return arsenal_events_cors_is_allowed_origin($origin) ? $result : arsenal_events_cors_error();
}

function arsenal_events_cors_apply_response_headers($response, string $origin, bool $preflight = false): void
{
    if (!is_object($response) || !method_exists($response, 'header')) {
        return;
    }

    $normalized = arsenal_events_cors_normalize_origin($origin);
    if ($normalized === null) {
        return;
    }

    $response->header('Access-Control-Allow-Origin', $normalized);
    $response->header('Vary', 'Origin');

    if ($preflight) {
        $response->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-WP-Nonce, X-Requested-With');
        $response->header('Access-Control-Max-Age', '600');
    }
}

function arsenal_events_cors_preflight_response($response, $server, $request)
{
    if (!arsenal_events_cors_is_meza_rest_route($request) || !is_object($request) || !method_exists($request, 'get_method')) {
        return $response;
    }

    if (strtoupper((string) $request->get_method()) !== 'OPTIONS') {
        return $response;
    }

    $origin = arsenal_events_cors_request_origin();
    if ($origin === '') {
        return $response;
    }

    if (!arsenal_events_cors_is_allowed_origin($origin)) {
        return arsenal_events_cors_error();
    }

    $preflight = new WP_REST_Response(null, 204);
    arsenal_events_cors_apply_response_headers($preflight, $origin, true);

    return $preflight;
}

function arsenal_events_cors_add_response_headers($response, $server, $request)
{
    if (!arsenal_events_cors_is_meza_rest_route($request)) {
        return $response;
    }

    $origin = arsenal_events_cors_request_origin();
    if ($origin === '' || !arsenal_events_cors_is_allowed_origin($origin)) {
        return $response;
    }

    arsenal_events_cors_apply_response_headers($response, $origin, false);

    return $response;
}

if (function_exists('add_filter')) {
    add_filter('rest_authentication_errors', 'arsenal_events_cors_validate_origin_for_request', 5, 1);
    add_filter('rest_pre_dispatch', 'arsenal_events_cors_preflight_response', 5, 3);
    add_filter('rest_post_dispatch', 'arsenal_events_cors_add_response_headers', 10, 3);
}
