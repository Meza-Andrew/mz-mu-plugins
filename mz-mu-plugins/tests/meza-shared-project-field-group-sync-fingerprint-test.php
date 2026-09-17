<?php

$meza_project_sync_test_options = [];
$meza_project_sync_test_definitions = [];
$meza_project_sync_test_deleted_keys = [];
$meza_project_sync_test_field_group_ids = [];
$meza_project_sync_test_field_groups_by_id = [];
$meza_project_sync_test_existing_fields_by_id = [];
$meza_project_sync_test_updated_fields = [];
$meza_project_sync_test_deleted_fields = [];

function meza_project_sync_test_assert(bool $condition, string $message): void
{
    if ($condition) {
        return;
    }

    throw new RuntimeException($message);
}

function meza_project_sync_test_assert_same($expected, $actual, string $message): void
{
    if ($expected === $actual) {
        return;
    }

    throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function meza_project_sync_test_case(string $name, callable $run): void
{
    $run();
    echo "ok - {$name}\n";
}

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
{
}

function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
{
}

function wp_json_encode($data, int $options = 0, int $depth = 512)
{
    return json_encode($data, $options, $depth);
}

function sanitize_key($key): string
{
    $key = strtolower((string) $key);

    return preg_replace('/[^a-z0-9_\-]/', '', $key) ?: '';
}

function get_option($name, $default = false)
{
    global $meza_project_sync_test_options;

    return array_key_exists((string) $name, $meza_project_sync_test_options)
        ? $meza_project_sync_test_options[(string) $name]
        : $default;
}

function update_option($name, $value, $autoload = null): bool
{
    global $meza_project_sync_test_options;

    $meza_project_sync_test_options[(string) $name] = $value;

    return true;
}

function meza_get_shared_project_acf_field_groups(): array
{
    global $meza_project_sync_test_definitions;

    return $meza_project_sync_test_definitions;
}

function meza_is_managed_acf_definition_manually_deleted(string $kind, array $definition): bool
{
    global $meza_project_sync_test_deleted_keys;

    return $kind === 'field_groups'
        && in_array((string) ($definition['key'] ?? ''), $meza_project_sync_test_deleted_keys, true);
}

function meza_get_existing_editable_acf_field_group_id(array $definition): int
{
    global $meza_project_sync_test_field_group_ids;

    return (int) ($meza_project_sync_test_field_group_ids[(string) ($definition['key'] ?? '')] ?? 0);
}

function acf_get_field_group($field_group_id)
{
    global $meza_project_sync_test_field_groups_by_id;

    return $meza_project_sync_test_field_groups_by_id[(int) $field_group_id] ?? null;
}

function acf_get_fields($field_group_id): array
{
    global $meza_project_sync_test_existing_fields_by_id;

    return $meza_project_sync_test_existing_fields_by_id[(int) $field_group_id] ?? [];
}

function acf_update_field(array $field): void
{
    global $meza_project_sync_test_updated_fields;

    $meza_project_sync_test_updated_fields[] = $field;
}

function acf_delete_field(int $field_id): void
{
    global $meza_project_sync_test_deleted_fields;

    $meza_project_sync_test_deleted_fields[] = $field_id;
}

require_once __DIR__ . '/../mz-acf-project-options/modules/registration.php';

function meza_project_sync_test_field_group(string $group_key, array $fields): array
{
    return [
        'key' => $group_key,
        'title' => 'Project Test Group',
        'fields' => $fields,
    ];
}

function meza_project_sync_test_text_field(string $key, string $name, string $label): array
{
    return [
        'key' => $key,
        'name' => $name,
        'label' => $label,
        'type' => 'text',
    ];
}

function meza_project_sync_test_repeater_field(array $sub_fields): array
{
    return [
        'key' => 'field_project_repeater',
        'name' => 'project_repeater',
        'label' => 'Project Repeater',
        'type' => 'repeater',
        'sub_fields' => $sub_fields,
    ];
}

function meza_project_sync_test_reset(array $definitions, array $deleted_keys = []): void
{
    global $meza_project_sync_test_options,
        $meza_project_sync_test_definitions,
        $meza_project_sync_test_deleted_keys,
        $meza_project_sync_test_field_group_ids,
        $meza_project_sync_test_field_groups_by_id,
        $meza_project_sync_test_existing_fields_by_id,
        $meza_project_sync_test_updated_fields,
        $meza_project_sync_test_deleted_fields;

    $meza_project_sync_test_options = [];
    $meza_project_sync_test_definitions = $definitions;
    $meza_project_sync_test_deleted_keys = $deleted_keys;
    $meza_project_sync_test_field_group_ids = [];
    $meza_project_sync_test_field_groups_by_id = [];
    $meza_project_sync_test_existing_fields_by_id = [];
    $meza_project_sync_test_updated_fields = [];
    $meza_project_sync_test_deleted_fields = [];

    foreach ($definitions as $index => $definition) {
        if (!is_array($definition)) {
            continue;
        }

        $group_key = (string) ($definition['key'] ?? '');
        if ($group_key === '') {
            continue;
        }

        $field_group_id = $index + 101;
        $meza_project_sync_test_field_group_ids[$group_key] = $field_group_id;
        $meza_project_sync_test_field_groups_by_id[$field_group_id] = [
            'ID' => $field_group_id,
            'key' => $group_key,
        ];
        $meza_project_sync_test_existing_fields_by_id[$field_group_id] = [];
    }
}

$base_definition = meza_project_sync_test_field_group('group_project_test', [
    meza_project_sync_test_text_field('field_project_title', 'project_title', 'Project Title'),
]);

meza_project_sync_test_case('same effective definition set does not repeat sync', function () use ($base_definition): void {
    global $meza_project_sync_test_updated_fields;

    meza_project_sync_test_reset([$base_definition]);

    meza_sync_shared_project_field_group_fields();
    meza_project_sync_test_assert_same(1, count($meza_project_sync_test_updated_fields), 'Initial sync should update the field once.');

    $recorded_version = get_option(meza_get_shared_project_field_group_fields_sync_option_name(), '');
    $meza_project_sync_test_updated_fields = [];

    meza_sync_shared_project_field_group_fields();

    meza_project_sync_test_assert_same([], $meza_project_sync_test_updated_fields, 'The same effective definition should not resync.');
    meza_project_sync_test_assert_same($recorded_version, get_option(meza_get_shared_project_field_group_fields_sync_option_name(), ''), 'The same definition should keep the recorded fingerprint version.');
});

meza_project_sync_test_case('changed nested project field definition changes version and syncs once', function () use ($base_definition): void {
    global $meza_project_sync_test_definitions, $meza_project_sync_test_updated_fields;

    meza_project_sync_test_reset([$base_definition]);
    meza_sync_shared_project_field_group_fields();
    $base_version = get_option(meza_get_shared_project_field_group_fields_sync_option_name(), '');

    $meza_project_sync_test_definitions = [
        meza_project_sync_test_field_group('group_project_test', [
            meza_project_sync_test_text_field('field_project_title', 'project_title', 'Project Title'),
            meza_project_sync_test_repeater_field([
                meza_project_sync_test_text_field('field_project_metric_label', 'label', 'Label'),
            ]),
        ]),
    ];
    $changed_version = meza_get_shared_project_field_group_fields_sync_version();
    $meza_project_sync_test_updated_fields = [];

    meza_sync_shared_project_field_group_fields();

    meza_project_sync_test_assert($changed_version !== $base_version, 'A nested field extension should change the fingerprinted version.');
    meza_project_sync_test_assert_same(2, count($meza_project_sync_test_updated_fields), 'The changed definition should synchronize its current fields once.');

    $meza_project_sync_test_updated_fields = [];
    meza_sync_shared_project_field_group_fields();
    meza_project_sync_test_assert_same([], $meza_project_sync_test_updated_fields, 'The changed definition should not repeat after its fingerprint is recorded.');
});

meza_project_sync_test_case('distinct effective definitions produce distinct stable fingerprints', function () use ($base_definition): void {
    $first = meza_get_shared_project_field_group_fields_sync_fingerprint([$base_definition]);
    $second = meza_get_shared_project_field_group_fields_sync_fingerprint([
        meza_project_sync_test_field_group('group_project_test', [
            meza_project_sync_test_text_field('field_project_title', 'project_title', 'Different Label'),
        ]),
    ]);
    $third = meza_get_shared_project_field_group_fields_sync_fingerprint([$base_definition]);

    meza_project_sync_test_assert($first !== $second, 'Different definitions should not share a fingerprint.');
    meza_project_sync_test_assert_same($first, $third, 'The same definition should produce a stable fingerprint.');
});

meza_project_sync_test_case('managed-deleted definitions remain excluded from fingerprint and sync', function () use ($base_definition): void {
    global $meza_project_sync_test_updated_fields;

    $deleted_definition = meza_project_sync_test_field_group('group_project_deleted', [
        meza_project_sync_test_text_field('field_project_deleted', 'deleted', 'Deleted'),
    ]);
    meza_project_sync_test_reset([$base_definition, $deleted_definition], ['group_project_deleted']);

    $effective_definitions = meza_get_shared_project_field_group_fields_sync_definitions();
    meza_project_sync_test_assert_same([$base_definition], $effective_definitions, 'Deleted field-group definitions should be excluded from the effective sync set.');

    meza_sync_shared_project_field_group_fields();
    $updated_keys = array_values(array_map(static fn(array $field): string => (string) ($field['key'] ?? ''), $meza_project_sync_test_updated_fields));

    meza_project_sync_test_assert_same(['field_project_title'], $updated_keys, 'Sync should not update fields from managed-deleted definitions.');
});

echo "Meza shared project field-group sync fingerprint tests passed\n";
