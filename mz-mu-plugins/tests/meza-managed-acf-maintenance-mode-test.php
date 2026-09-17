<?php

$meza_maintenance_test_actions = [];
$meza_maintenance_test_filters = [];
$meza_maintenance_test_mutations = [];
$meza_maintenance_test_registrations = 0;
$meza_maintenance_test_suppressed = false;

function meza_maintenance_test_assert(bool $condition, string $message): void
{
    if ($condition) {
        return;
    }

    throw new RuntimeException($message);
}

function meza_maintenance_test_assert_same($expected, $actual, string $message): void
{
    if ($expected === $actual) {
        return;
    }

    throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function meza_maintenance_test_case(string $name, callable $run): void
{
    $run();
    echo "ok - {$name}\n";
}

function add_action(string $hook_name, $callback, int $priority = 10, int $accepted_args = 1): void
{
    global $meza_maintenance_test_actions;

    $meza_maintenance_test_actions[$hook_name][$priority][] = [
        'callback' => $callback,
        'accepted_args' => $accepted_args,
    ];
}

function add_filter(string $hook_name, $callback, int $priority = 10, int $accepted_args = 1): void
{
    global $meza_maintenance_test_filters;

    $meza_maintenance_test_filters[$hook_name][$priority][] = [
        'callback' => $callback,
        'accepted_args' => $accepted_args,
    ];
}

function apply_filters(string $hook_name, $value)
{
    global $meza_maintenance_test_filters;

    $callbacks_by_priority = $meza_maintenance_test_filters[$hook_name] ?? [];
    ksort($callbacks_by_priority);

    foreach ($callbacks_by_priority as $callbacks) {
        foreach ($callbacks as $spec) {
            $value = call_user_func($spec['callback'], $value);
        }
    }

    return $value;
}

function meza_maintenance_test_do_action(string $hook_name, ...$args): void
{
    global $meza_maintenance_test_actions;

    $callbacks_by_priority = $meza_maintenance_test_actions[$hook_name] ?? [];
    ksort($callbacks_by_priority);

    foreach ($callbacks_by_priority as $callbacks) {
        foreach ($callbacks as $spec) {
            call_user_func_array(
                $spec['callback'],
                array_slice($args, 0, (int) $spec['accepted_args'])
            );
        }
    }
}

function sanitize_key($key): string
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key)) ?: '';
}

function meza_maintenance_test_reset(bool $suppressed): void
{
    global $meza_maintenance_test_actions,
        $meza_maintenance_test_filters,
        $meza_maintenance_test_mutations,
        $meza_maintenance_test_registrations,
        $meza_maintenance_test_suppressed;

    $meza_maintenance_test_actions = [];
    $meza_maintenance_test_filters = [];
    $meza_maintenance_test_mutations = [];
    $meza_maintenance_test_registrations = 0;
    $meza_maintenance_test_suppressed = $suppressed;

    add_filter('meza_managed_acf_automatic_mutations_suppressed', static function (bool $default): bool {
        global $meza_maintenance_test_suppressed;

        return $meza_maintenance_test_suppressed || $default;
    });
}

function meza_maintenance_test_registration(): void
{
    global $meza_maintenance_test_registrations;

    $meza_maintenance_test_registrations++;
}

function meza_maintenance_test_mutation(): void
{
    global $meza_maintenance_test_mutations;

    $meza_maintenance_test_mutations[] = 'automatic';
}

function meza_maintenance_test_explicit_operation(): void
{
    global $meza_maintenance_test_mutations;

    $meza_maintenance_test_mutations[] = 'explicit';
}

require_once __DIR__ . '/../mz-acf-project-options/modules/managed-definitions.php';

meza_maintenance_test_case('default mode runs automatic managed mutations', function (): void {
    global $meza_maintenance_test_mutations, $meza_maintenance_test_registrations;

    meza_maintenance_test_reset(false);
    add_action('acf/init', 'meza_maintenance_test_registration', 15);
    meza_add_managed_acf_automatic_mutation_action('acf/init', 'meza_maintenance_test_mutation', 24);

    meza_maintenance_test_do_action('acf/init');

    meza_maintenance_test_assert_same(1, $meza_maintenance_test_registrations, 'Normal registration action should run.');
    meza_maintenance_test_assert_same(['automatic'], $meza_maintenance_test_mutations, 'Automatic mutation should run by default.');
});

meza_maintenance_test_case('suppressed mode keeps registration and blocks automatic managed mutations', function (): void {
    global $meza_maintenance_test_mutations, $meza_maintenance_test_registrations;

    meza_maintenance_test_reset(true);
    add_action('acf/init', 'meza_maintenance_test_registration', 15);
    meza_add_managed_acf_automatic_mutation_action('acf/init', 'meza_maintenance_test_mutation', 24);

    meza_maintenance_test_do_action('acf/init');

    meza_maintenance_test_assert_same(1, $meza_maintenance_test_registrations, 'Registration should still run while automatic mutations are suppressed.');
    meza_maintenance_test_assert_same([], $meza_maintenance_test_mutations, 'Suppressed mode should skip automatic managed mutations.');
});

meza_maintenance_test_case('explicit maintenance operation runs while automatic mode is suppressed', function (): void {
    global $meza_maintenance_test_mutations;

    meza_maintenance_test_reset(true);
    add_filter('meza_managed_acf_maintenance_operations', static function (array $operations): array {
        $operations['test_explicit_operation'] = 'meza_maintenance_test_explicit_operation';

        return $operations;
    });

    meza_maintenance_test_assert(
        meza_run_managed_acf_maintenance_operation('test_explicit_operation'),
        'Known explicit maintenance operation should be invokable.'
    );
    meza_maintenance_test_assert_same(['explicit'], $meza_maintenance_test_mutations, 'Explicit operation should run despite automatic suppression.');
    meza_maintenance_test_assert(
        !meza_run_managed_acf_maintenance_operation('missing_operation'),
        'Unknown maintenance operation should fail closed.'
    );
});

echo "Meza managed ACF maintenance mode tests passed\n";
