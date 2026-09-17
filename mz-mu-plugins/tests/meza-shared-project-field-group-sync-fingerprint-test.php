<?php

$meza_project_sync_test_options = [];
$meza_project_sync_test_definitions = [];
$meza_project_sync_test_default_definitions = [];
$meza_project_sync_test_deleted_keys = [];
$meza_project_sync_test_field_group_ids = [];
$meza_project_sync_test_field_groups_by_id = [];
$meza_project_sync_test_existing_fields_by_id = [];
$meza_project_sync_test_updated_fields = [];
$meza_project_sync_test_deleted_fields = [];
$meza_project_sync_test_event_mode = false;

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

function meza_get_default_editable_acf_field_group_definitions(): array
{
    global $meza_project_sync_test_default_definitions;

    return $meza_project_sync_test_default_definitions;
}

function meza_is_managed_acf_definition_manually_deleted(string $kind, array $definition): bool
{
    global $meza_project_sync_test_deleted_keys;

    return $kind === 'field_groups'
        && in_array((string) ($definition['key'] ?? ''), $meza_project_sync_test_deleted_keys, true);
}

function meza_events_have_after_content(): bool
{
    return true;
}

function meza_get_event_context_request_post_type(): string
{
    global $meza_project_sync_test_event_mode;

    return $meza_project_sync_test_event_mode ? 'event' : 'page';
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
    global $meza_project_sync_test_updated_fields,
        $meza_project_sync_test_field_groups_by_id,
        $meza_project_sync_test_existing_fields_by_id;

    $meza_project_sync_test_updated_fields[] = $field;

    $parent_key = (string) ($field['parent'] ?? '');
    if ($parent_key === '') {
        return;
    }

    foreach ($meza_project_sync_test_field_groups_by_id as $field_group_id => $field_group) {
        if ((string) ($field_group['key'] ?? '') !== $parent_key) {
            continue;
        }

        $field_key = (string) ($field['key'] ?? '');
        $fields = $meza_project_sync_test_existing_fields_by_id[(int) $field_group_id] ?? [];
        $did_replace = false;

        foreach ($fields as $index => $existing_field) {
            if (!is_array($existing_field) || (string) ($existing_field['key'] ?? '') !== $field_key) {
                continue;
            }

            $fields[$index] = $field;
            $did_replace = true;
            break;
        }

        if (!$did_replace) {
            $fields[] = $field;
        }

        $meza_project_sync_test_existing_fields_by_id[(int) $field_group_id] = $fields;
        return;
    }
}

function acf_delete_field(int $field_id): void
{
    global $meza_project_sync_test_deleted_fields;

    $meza_project_sync_test_deleted_fields[] = $field_id;
}

function meza_preserve_acf_field_tree_identifiers(array $field, ?array $existing_field = null, string $fallback_parent = ''): array
{
    if (is_array($existing_field)) {
        foreach (['ID', 'id', 'parent', 'menu_order'] as $property) {
            if (!array_key_exists($property, $field) && array_key_exists($property, $existing_field)) {
                $field[$property] = $existing_field[$property];
            }
        }
    }

    if ((!isset($field['parent']) || $field['parent'] === '') && $fallback_parent !== '') {
        $field['parent'] = $fallback_parent;
    }

    if (isset($field['sub_fields']) && is_array($field['sub_fields'])) {
        $existing_sub_fields = [];
        foreach ((array) ($existing_field['sub_fields'] ?? []) as $existing_sub_field) {
            if (!is_array($existing_sub_field)) {
                continue;
            }

            $existing_key = (string) ($existing_sub_field['key'] ?? '');
            if ($existing_key !== '') {
                $existing_sub_fields[$existing_key] = $existing_sub_field;
            }
        }

        foreach ($field['sub_fields'] as $index => $sub_field) {
            if (!is_array($sub_field)) {
                continue;
            }

            $sub_field_key = (string) ($sub_field['key'] ?? '');
            $field['sub_fields'][$index] = meza_preserve_acf_field_tree_identifiers(
                $sub_field,
                $sub_field_key !== '' ? ($existing_sub_fields[$sub_field_key] ?? null) : null,
                (string) ($field['key'] ?? $fallback_parent)
            );
        }
    }

    return $field;
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

function meza_project_sync_test_with_parents(array $fields, string $parent_key): array
{
    foreach ($fields as $index => $field) {
        if (!is_array($field)) {
            continue;
        }

        $field['parent'] = $parent_key;
        if (isset($field['sub_fields']) && is_array($field['sub_fields'])) {
            $field['sub_fields'] = meza_project_sync_test_with_parents(
                array_values($field['sub_fields']),
                (string) ($field['key'] ?? $parent_key)
            );
        }

        $fields[$index] = $field;
    }

    return $fields;
}

function meza_project_sync_test_seed_complete_db_fields(array $definition): void
{
    global $meza_project_sync_test_field_group_ids, $meza_project_sync_test_existing_fields_by_id;

    $group_key = (string) ($definition['key'] ?? '');
    $field_group_id = (int) ($meza_project_sync_test_field_group_ids[$group_key] ?? 0);
    if ($field_group_id <= 0) {
        return;
    }

    $meza_project_sync_test_existing_fields_by_id[$field_group_id] = meza_project_sync_test_with_parents(
        array_values((array) ($definition['fields'] ?? [])),
        $group_key
    );
}

function meza_project_sync_test_reset(array $definitions, array $deleted_keys = [], array $existing_fields_by_group_key = [], array $default_definitions = []): void
{
    global $meza_project_sync_test_options,
        $meza_project_sync_test_definitions,
        $meza_project_sync_test_default_definitions,
        $meza_project_sync_test_deleted_keys,
        $meza_project_sync_test_field_group_ids,
        $meza_project_sync_test_field_groups_by_id,
        $meza_project_sync_test_existing_fields_by_id,
        $meza_project_sync_test_updated_fields,
        $meza_project_sync_test_deleted_fields,
        $meza_project_sync_test_event_mode;

    $meza_project_sync_test_options = [];
    $meza_project_sync_test_definitions = $definitions;
    $meza_project_sync_test_default_definitions = $default_definitions;
    $meza_project_sync_test_deleted_keys = $deleted_keys;
    $meza_project_sync_test_field_group_ids = [];
    $meza_project_sync_test_field_groups_by_id = [];
    $meza_project_sync_test_existing_fields_by_id = [];
    $meza_project_sync_test_updated_fields = [];
    $meza_project_sync_test_deleted_fields = [];
    $meza_project_sync_test_event_mode = false;

    foreach (array_values(array_merge($definitions, $default_definitions)) as $index => $definition) {
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
        $meza_project_sync_test_existing_fields_by_id[$field_group_id] = isset($existing_fields_by_group_key[$group_key])
            ? $existing_fields_by_group_key[$group_key]
            : [];
    }
}

$base_definition = meza_project_sync_test_field_group('group_project_test', [
    meza_project_sync_test_text_field('field_project_title', 'project_title', 'Project Title'),
]);
$nested_definition = meza_project_sync_test_field_group('group_project_nested', [
    meza_project_sync_test_repeater_field([
        meza_project_sync_test_text_field('field_project_metric_value', 'value', 'Value'),
        meza_project_sync_test_text_field('field_project_metric_label', 'label', 'Label'),
    ]),
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
    meza_project_sync_test_assert_same(1, count($meza_project_sync_test_updated_fields), 'The changed definition should synchronize its missing field tree once.');

    $meza_project_sync_test_updated_fields = [];
    meza_sync_shared_project_field_group_fields();
    meza_project_sync_test_assert_same([], $meza_project_sync_test_updated_fields, 'The changed definition should not repeat after its fingerprint is recorded.');
});

meza_project_sync_test_case('current fingerprint with missing nested field triggers sync', function () use ($nested_definition): void {
    global $meza_project_sync_test_options, $meza_project_sync_test_updated_fields;

    meza_project_sync_test_reset([$nested_definition], [], [
        'group_project_nested' => meza_project_sync_test_with_parents([
            meza_project_sync_test_repeater_field([
                meza_project_sync_test_text_field('field_project_metric_value', 'value', 'Value'),
            ]),
        ], 'group_project_nested'),
    ]);
    $current_version = meza_get_shared_project_field_group_fields_sync_version();
    $meza_project_sync_test_options[meza_get_shared_project_field_group_fields_sync_option_name()] = $current_version;

    meza_sync_shared_project_field_group_fields();

    meza_project_sync_test_assert_same(['field_project_repeater'], array_map(static fn(array $field): string => (string) ($field['key'] ?? ''), $meza_project_sync_test_updated_fields), 'Current fingerprint must not mask an incomplete nested tree.');
    meza_project_sync_test_assert(meza_shared_project_field_group_definition_trees_are_complete([$nested_definition]), 'Normal ACF sync should reconcile the missing nested field tree.');
    meza_project_sync_test_assert_same($current_version, get_option(meza_get_shared_project_field_group_fields_sync_option_name(), ''), 'Current fingerprint remains recorded after the tree is complete.');
});

meza_project_sync_test_case('db-backed group missing nested metrics value label is reconciled', function () use ($nested_definition): void {
    global $meza_project_sync_test_updated_fields;

    meza_project_sync_test_reset([$nested_definition], [], [
        'group_project_nested' => meza_project_sync_test_with_parents([
            meza_project_sync_test_repeater_field([]),
        ], 'group_project_nested'),
    ]);

    meza_sync_shared_project_field_group_fields();
    $updated_field = $meza_project_sync_test_updated_fields[0] ?? [];
    $sub_field_keys = array_map(
        static fn(array $field): string => (string) ($field['key'] ?? ''),
        (array) ($updated_field['sub_fields'] ?? [])
    );

    meza_project_sync_test_assert_same(['field_project_metric_value', 'field_project_metric_label'], $sub_field_keys, 'Missing nested value/label fields should be written through the parent field tree.');
    meza_project_sync_test_assert(meza_shared_project_field_group_definition_trees_are_complete([$nested_definition]), 'The reconciled nested metrics field tree should be complete.');
});

meza_project_sync_test_case('current fingerprint with complete tree skips repeat sync', function () use ($nested_definition): void {
    global $meza_project_sync_test_options, $meza_project_sync_test_updated_fields;

    meza_project_sync_test_reset([$nested_definition]);
    meza_project_sync_test_seed_complete_db_fields($nested_definition);
    $current_version = meza_get_shared_project_field_group_fields_sync_version();
    $meza_project_sync_test_options[meza_get_shared_project_field_group_fields_sync_option_name()] = $current_version;

    meza_sync_shared_project_field_group_fields();

    meza_project_sync_test_assert_same([], $meza_project_sync_test_updated_fields, 'A current fingerprint with a complete DB tree should skip sync.');
});

meza_project_sync_test_case('duplicate disabled same-key record cannot satisfy active completeness', function () use ($nested_definition): void {
    global $meza_project_sync_test_field_group_ids,
        $meza_project_sync_test_field_groups_by_id,
        $meza_project_sync_test_existing_fields_by_id,
        $meza_project_sync_test_options,
        $meza_project_sync_test_updated_fields;

    meza_project_sync_test_reset([$nested_definition], [], [
        'group_project_nested' => [],
    ]);
    $meza_project_sync_test_field_group_ids['group_project_nested'] = 101;
    $meza_project_sync_test_field_groups_by_id[202] = [
        'ID' => 202,
        'key' => 'group_project_nested',
        'post_status' => 'acf-disabled',
    ];
    $meza_project_sync_test_existing_fields_by_id[202] = meza_project_sync_test_with_parents(
        array_values((array) ($nested_definition['fields'] ?? [])),
        'group_project_nested'
    );
    $meza_project_sync_test_options[meza_get_shared_project_field_group_fields_sync_option_name()] = meza_get_shared_project_field_group_fields_sync_version();

    meza_sync_shared_project_field_group_fields();

    meza_project_sync_test_assert_same(['field_project_repeater'], array_map(static fn(array $field): string => (string) ($field['key'] ?? ''), $meza_project_sync_test_updated_fields), 'A complete disabled duplicate must not satisfy active DB tree completeness.');
    meza_project_sync_test_assert(meza_shared_project_field_group_definition_trees_are_complete([$nested_definition]), 'The active same-key record should be reconciled.');
});

meza_project_sync_test_case('mis-parented nested field triggers sync', function () use ($nested_definition): void {
    global $meza_project_sync_test_updated_fields;

    $fields = meza_project_sync_test_with_parents(array_values((array) ($nested_definition['fields'] ?? [])), 'group_project_nested');
    $fields[0]['sub_fields'][0]['parent'] = 'field_project_wrong_parent';

    meza_project_sync_test_reset([$nested_definition], [], [
        'group_project_nested' => $fields,
    ]);

    meza_sync_shared_project_field_group_fields();

    meza_project_sync_test_assert_same(['field_project_repeater'], array_map(static fn(array $field): string => (string) ($field['key'] ?? ''), $meza_project_sync_test_updated_fields), 'A mis-parented nested field should force reconciliation.');
    meza_project_sync_test_assert(meza_shared_project_field_group_definition_trees_are_complete([$nested_definition]), 'Reconciliation should restore intended parent-key paths.');
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

meza_project_sync_test_case('default-editable current fingerprint with missing nested field triggers sync', function () use ($nested_definition): void {
    global $meza_project_sync_test_options, $meza_project_sync_test_updated_fields;

    meza_project_sync_test_reset([], [], [
        'group_project_nested' => meza_project_sync_test_with_parents([
            meza_project_sync_test_repeater_field([
                meza_project_sync_test_text_field('field_project_metric_value', 'value', 'Value'),
            ]),
        ], 'group_project_nested'),
    ], [$nested_definition]);
    $current_version = meza_get_default_editable_field_group_fields_sync_version();
    $meza_project_sync_test_options[meza_get_default_editable_field_group_fields_sync_option_name()] = $current_version;

    meza_sync_default_editable_field_group_fields();

    meza_project_sync_test_assert_same(['field_project_repeater'], array_map(static fn(array $field): string => (string) ($field['key'] ?? ''), $meza_project_sync_test_updated_fields), 'Current default-editable fingerprint must not mask an incomplete nested tree.');
    meza_project_sync_test_assert(meza_shared_project_field_group_definition_trees_are_complete([$nested_definition]), 'Default-editable sync should reconcile the missing nested field tree.');
    meza_project_sync_test_assert_same($current_version, get_option(meza_get_default_editable_field_group_fields_sync_option_name(), ''), 'Current default-editable fingerprint remains recorded after the tree is complete.');
});

meza_project_sync_test_case('default-editable current fingerprint with complete tree skips repeat sync', function () use ($nested_definition): void {
    global $meza_project_sync_test_options, $meza_project_sync_test_updated_fields;

    meza_project_sync_test_reset([], [], [], [$nested_definition]);
    meza_project_sync_test_seed_complete_db_fields($nested_definition);
    $current_version = meza_get_default_editable_field_group_fields_sync_version();
    $meza_project_sync_test_options[meza_get_default_editable_field_group_fields_sync_option_name()] = $current_version;

    meza_sync_default_editable_field_group_fields();

    meza_project_sync_test_assert_same([], $meza_project_sync_test_updated_fields, 'A current default-editable fingerprint with a complete DB tree should skip sync.');
});

meza_project_sync_test_case('managed-deleted default-editable definitions remain excluded from fingerprint and sync', function () use ($base_definition): void {
    global $meza_project_sync_test_updated_fields;

    $deleted_definition = meza_project_sync_test_field_group('group_project_deleted', [
        meza_project_sync_test_text_field('field_project_deleted', 'deleted', 'Deleted'),
    ]);
    meza_project_sync_test_reset([], ['group_project_deleted'], [], [$base_definition, $deleted_definition]);

    $effective_definitions = meza_get_default_editable_field_group_fields_sync_definitions();
    meza_project_sync_test_assert_same([$base_definition], $effective_definitions, 'Deleted default-editable definitions should be excluded from the effective sync set.');

    meza_sync_default_editable_field_group_fields();
    $updated_keys = array_values(array_map(static fn(array $field): string => (string) ($field['key'] ?? ''), $meza_project_sync_test_updated_fields));

    meza_project_sync_test_assert_same(['field_project_title'], $updated_keys, 'Default-editable sync should not update fields from managed-deleted definitions.');
});

meza_project_sync_test_case('default and event modes remain distinct', function () use ($base_definition): void {
    global $meza_project_sync_test_event_mode;

    meza_project_sync_test_reset([$base_definition]);
    $default_version = meza_get_shared_project_field_group_fields_sync_version();

    $meza_project_sync_test_event_mode = true;
    $event_version = meza_get_shared_project_field_group_fields_sync_version();

    meza_project_sync_test_assert($default_version !== $event_version, 'Default and event context versions should stay distinct.');
    meza_project_sync_test_assert(str_contains($default_version, '-default-'), 'Default context should retain its mode marker.');
    meza_project_sync_test_assert(str_contains($event_version, '-event-'), 'Event context should retain its mode marker.');
});

echo "Meza shared project field-group sync fingerprint tests passed\n";
