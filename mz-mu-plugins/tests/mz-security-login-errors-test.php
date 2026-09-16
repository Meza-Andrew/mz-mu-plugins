<?php

define('ABSPATH', __DIR__ . '/');

$mz_security_login_error_test_filters = [];

function mz_security_login_error_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
    global $mz_security_login_error_test_filters;
    $mz_security_login_error_test_filters[$hook][] = $callback;
}

function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
}

function remove_action(string $hook, $callback, int $priority = 10): void
{
}

require_once __DIR__ . '/../mz-security.php';

$callbacks = $mz_security_login_error_test_filters['login_errors'] ?? [];
mz_security_login_error_test_assert(count($callbacks) === 1, 'Security runtime should register one login-error filter.');

$callback = $callbacks[0];
$unknown_user_message = $callback('Unknown username.');
$wrong_password_message = $callback('Incorrect password.');

mz_security_login_error_test_assert($unknown_user_message === 'Invalid login credentials.', 'Login failures must receive a generic message.');
mz_security_login_error_test_assert($unknown_user_message === $wrong_password_message, 'Login failures must not enumerate invalid usernames or passwords.');

echo "MZ Security login-error tests passed\n";
