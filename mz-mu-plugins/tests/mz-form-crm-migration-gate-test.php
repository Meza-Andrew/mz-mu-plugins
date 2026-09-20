<?php

define('ABSPATH', __DIR__);

$mzf_test_actions = [];
$mzf_test_filters = [];
$mzf_test_fields = [];
$mzf_test_options = [];
$mzf_test_writes = [];
$mzf_test_update_field_result = true;

function mzf_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
{
    global $mzf_test_actions;

    $mzf_test_actions[$hook][$priority][] = [
        'callback' => $callback,
        'accepted_args' => $accepted_args,
    ];
}

function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
{
    global $mzf_test_filters;

    $mzf_test_filters[$hook][$priority][] = [
        'callback' => $callback,
        'accepted_args' => $accepted_args,
    ];
}

function apply_filters(string $hook, $value)
{
    global $mzf_test_filters;

    $callbacks_by_priority = $mzf_test_filters[$hook] ?? [];
    ksort($callbacks_by_priority);

    foreach ($callbacks_by_priority as $callbacks) {
        foreach ($callbacks as $spec) {
            $value = call_user_func($spec['callback'], $value);
        }
    }

    return $value;
}

function mzf_test_do_action(string $hook): void
{
    global $mzf_test_actions;

    $callbacks_by_priority = $mzf_test_actions[$hook] ?? [];
    ksort($callbacks_by_priority);

    foreach ($callbacks_by_priority as $callbacks) {
        foreach ($callbacks as $spec) {
            call_user_func($spec['callback']);
        }
    }
}

function did_action(string $hook): int
{
    return 0;
}

function get_option(string $key, $default = false)
{
    global $mzf_test_options;

    return $mzf_test_options[$key] ?? $default;
}

function update_option(string $key, $value, $autoload = null): bool
{
    global $mzf_test_options, $mzf_test_writes;

    $mzf_test_writes[] = "option:{$key}";
    $mzf_test_options[$key] = $value;

    return true;
}

function get_field(string $key, $scope = null)
{
    global $mzf_test_fields;

    return $mzf_test_fields[$key] ?? null;
}

function update_field(string $key, $value, $scope = null)
{
    global $mzf_test_fields, $mzf_test_update_field_result, $mzf_test_writes;

    $mzf_test_writes[] = "field:{$key}";
    if ($mzf_test_update_field_result) {
        $mzf_test_fields[$key] = $value;
    }

    return $mzf_test_update_field_result;
}

function sanitize_text_field($value): string
{
    return trim((string) $value);
}

function esc_html($value): string
{
    return (string) $value;
}

function wp_json_encode($value): string
{
    return (string) json_encode($value);
}

function mzf_test_load_config(): void
{
    require __DIR__ . '/../mz-form/config.php';
}

function mzf_test_legacy_options(): array
{
    return [
        'options_crm_api_mailchimp' => 'fake-api-key',
        'options_crm_list_mailchimp' => 'fake-list-id',
    ];
}

function mzf_test_corrected_crm(): array
{
    return [
        'api_mailchimp' => 'fake-api-key',
        'list_mailchimp' => 'fake-list-id',
        'mailchimp' => [
            'api' => 'fake-api-key',
            'list' => 'fake-list-id',
        ],
        'platform' => 'MailChimp',
    ];
}

function mzf_test_registered_operation(): callable
{
    $operations = apply_filters('meza_managed_acf_maintenance_operations', []);
    mzf_test_assert(
        ($operations['migrate_mailchimp_to_crm'] ?? null) === 'mzf_maybe_migrate_mailchimp_to_crm',
        'The migration must be available only as the named maintenance operation.'
    );

    return $operations['migrate_mailchimp_to_crm'];
}

function mzf_test_assert_no_automatic_migration(): void
{
    global $mzf_test_actions, $mzf_test_writes;

    mzf_test_assert(empty($mzf_test_actions['acf/init']), 'Config must not register an acf/init callback.');
    mzf_test_assert(empty($mzf_test_actions['init']), 'Config must not register an init callback.');
    mzf_test_do_action('acf/init');
    mzf_test_do_action('init');
    mzf_test_assert($mzf_test_writes === [], 'Simulated request hooks must not write.');
}

function mzf_test_run_case(string $case): void
{
    global $mzf_test_fields, $mzf_test_options, $mzf_test_update_field_result, $mzf_test_writes;

    if ($case === 'acf-success') {
        $mzf_test_options = mzf_test_legacy_options();
    } elseif ($case === 'option-fallback') {
        $mzf_test_options = mzf_test_legacy_options();
        $mzf_test_update_field_result = false;
    } elseif ($case === 'already-migrated-acf') {
        $mzf_test_options = mzf_test_legacy_options();
        $mzf_test_fields['crm'] = mzf_test_corrected_crm();
    } elseif ($case === 'already-migrated-option') {
        $mzf_test_options = array_merge(mzf_test_legacy_options(), [
            'crm' => mzf_test_corrected_crm(),
        ]);
    }

    mzf_test_load_config();
    mzf_test_assert_no_automatic_migration();
    $operation = mzf_test_registered_operation();
    $operation();

    if ($case === 'no-legacy') {
        mzf_test_assert($mzf_test_writes === [], 'No legacy data must remain a no-op.');
        return;
    }

    if ($case === 'already-migrated-acf' || $case === 'already-migrated-option') {
        mzf_test_assert($mzf_test_writes === [], 'A corrected persisted CRM destination must make a fresh explicit operation a no-op.');
        return;
    }

    mzf_test_assert(in_array('field:crm', $mzf_test_writes, true), 'Explicit maintenance must attempt the ACF CRM write.');
    mzf_test_assert(($mzf_test_options['options_crm_platform'] ?? null) === 'MailChimp', 'Explicit migration must normalize the CRM platform.');

    if ($case === 'acf-success') {
        mzf_test_assert(isset($mzf_test_fields['crm']), 'The ACF success path must retain the CRM group write.');
        mzf_test_assert(isset($mzf_test_options['options_crm_mailchimp']), 'The ACF success path must synchronize option fallbacks.');
    } else {
        mzf_test_assert(isset($mzf_test_options['crm']), 'The option fallback must save the CRM group when ACF refuses the write.');
        mzf_test_assert(isset($mzf_test_options['options_crm']), 'The option fallback must preserve the legacy options group.');
    }

    $writes_after_first_run = $mzf_test_writes;
    $operation();
    mzf_test_assert($mzf_test_writes === $writes_after_first_run, 'A second call in one request must be duplicate-suppressed.');
}

$case = $argv[1] ?? null;
if ($case !== null) {
    mzf_test_assert(in_array($case, ['acf-success', 'option-fallback', 'already-migrated-acf', 'already-migrated-option', 'no-legacy'], true), 'Unknown test case.');
    mzf_test_run_case($case);
    exit(0);
}

foreach (['acf-success', 'option-fallback', 'already-migrated-acf', 'already-migrated-option', 'no-legacy'] as $subprocess_case) {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($subprocess_case);
    exec($command, $output, $status);
    mzf_test_assert($status === 0, "Isolated {$subprocess_case} regression case failed.");
}

echo "MZ Form CRM migration gate tests passed\n";
