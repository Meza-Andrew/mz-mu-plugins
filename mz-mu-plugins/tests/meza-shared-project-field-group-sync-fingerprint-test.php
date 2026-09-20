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
$meza_project_sync_test_filters = [];

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
    global $meza_project_sync_test_filters;

    $meza_project_sync_test_filters[$hook][$priority][] = $callback;
}

function apply_filters(string $hook, $value)
{
    global $meza_project_sync_test_filters;

    $callbacks_by_priority = $meza_project_sync_test_filters[$hook] ?? [];
    ksort($callbacks_by_priority);

    foreach ($callbacks_by_priority as $callbacks) {
        foreach ($callbacks as $callback) {
            $value = $callback($value);
        }
    }

    return $value;
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

function meza_get_base_default_editable_acf_field_group_definitions(): array
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

function acf_get_field_group($field_group_id)
{
    global $meza_project_sync_test_field_groups_by_id;

    return $meza_project_sync_test_field_groups_by_id[(int) $field_group_id] ?? null;
}

function acf_get_field_groups(): array
{
    global $meza_project_sync_test_field_groups_by_id;

    return array_values($meza_project_sync_test_field_groups_by_id);
}

function acf_get_raw_field_groups(): array
{
    return acf_get_field_groups();
}

function acf_get_fields($field_group_id): array
{
    global $meza_project_sync_test_existing_fields_by_id;

    return $meza_project_sync_test_existing_fields_by_id[(int) $field_group_id] ?? [];
}

function get_post_status($post_id): string
{
    global $meza_project_sync_test_field_groups_by_id;

    return (string) ($meza_project_sync_test_field_groups_by_id[(int) $post_id]['post_status'] ?? 'publish');
}

function meza_project_sync_test_field_without_descendants(array $field): array
{
    unset($field['sub_fields']);

    if (isset($field['layouts']) && is_array($field['layouts'])) {
        foreach ($field['layouts'] as $layout_index => $layout) {
            if (!is_array($layout)) {
                continue;
            }

            unset($layout['sub_fields']);
            $field['layouts'][$layout_index] = $layout;
        }
    }

    return $field;
}

function meza_project_sync_test_upsert_field(array $fields, array $field): array
{
    $field_key = (string) ($field['key'] ?? '');
    $did_replace = false;

    foreach ($fields as $index => $existing_field) {
        if (!is_array($existing_field) || (string) ($existing_field['key'] ?? '') !== $field_key) {
            continue;
        }

        $updated_field = meza_project_sync_test_field_without_descendants($field)
            + array_intersect_key($existing_field, ['sub_fields' => true]);

        if (isset($updated_field['layouts']) && is_array($updated_field['layouts'])) {
            $existing_layouts_by_key = [];
            foreach ((array) ($existing_field['layouts'] ?? []) as $existing_layout) {
                if (!is_array($existing_layout)) {
                    continue;
                }

                $layout_key = (string) ($existing_layout['key'] ?? '');
                if ($layout_key !== '') {
                    $existing_layouts_by_key[$layout_key] = $existing_layout;
                    continue;
                }

                $layout_name = (string) ($existing_layout['name'] ?? '');
                if ($layout_name !== '') {
                    $existing_layouts_by_key['name:' . $layout_name] = $existing_layout;
                }
            }

            foreach ($updated_field['layouts'] as $layout_index => $layout) {
                if (!is_array($layout)) {
                    continue;
                }

                $layout_key = (string) ($layout['key'] ?? '');
                if ($layout_key === '') {
                    $layout_name = (string) ($layout['name'] ?? '');
                    $layout_key = $layout_name !== '' ? 'name:' . $layout_name : '';
                }

                if ($layout_key !== '' && isset($existing_layouts_by_key[$layout_key]['sub_fields'])) {
                    $layout['sub_fields'] = $existing_layouts_by_key[$layout_key]['sub_fields'];
                    $updated_field['layouts'][$layout_index] = $layout;
                }
            }
        }

        $fields[$index] = $updated_field;
        $did_replace = true;
        break;
    }

    if (!$did_replace) {
        $fields[] = meza_project_sync_test_field_without_descendants($field);
    }

    return array_values($fields);
}

function meza_project_sync_test_upsert_field_under_parent(array $fields, string $parent_key, array $field, bool &$did_update): array
{
    foreach ($fields as $index => $existing_field) {
        if (!is_array($existing_field)) {
            continue;
        }

        if ((string) ($existing_field['key'] ?? '') === $parent_key) {
            $existing_field['sub_fields'] = meza_project_sync_test_upsert_field(
                (array) ($existing_field['sub_fields'] ?? []),
                $field
            );
            $fields[$index] = $existing_field;
            $did_update = true;
            return $fields;
        }

        if (isset($existing_field['sub_fields']) && is_array($existing_field['sub_fields'])) {
            $existing_field['sub_fields'] = meza_project_sync_test_upsert_field_under_parent(
                $existing_field['sub_fields'],
                $parent_key,
                $field,
                $did_update
            );
            if ($did_update) {
                $fields[$index] = $existing_field;
                return $fields;
            }
        }

        if (!isset($existing_field['layouts']) || !is_array($existing_field['layouts'])) {
            continue;
        }

        foreach ($existing_field['layouts'] as $layout_index => $layout) {
            if (!is_array($layout)) {
                continue;
            }

            if ((string) ($layout['key'] ?? '') === $parent_key) {
                $layout['sub_fields'] = meza_project_sync_test_upsert_field(
                    (array) ($layout['sub_fields'] ?? []),
                    $field
                );
                $existing_field['layouts'][$layout_index] = $layout;
                $fields[$index] = $existing_field;
                $did_update = true;
                return $fields;
            }

            if (isset($layout['sub_fields']) && is_array($layout['sub_fields'])) {
                $layout['sub_fields'] = meza_project_sync_test_upsert_field_under_parent(
                    $layout['sub_fields'],
                    $parent_key,
                    $field,
                    $did_update
                );
                if ($did_update) {
                    $existing_field['layouts'][$layout_index] = $layout;
                    $fields[$index] = $existing_field;
                    return $fields;
                }
            }
        }
    }

    return $fields;
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

        $fields = $meza_project_sync_test_existing_fields_by_id[(int) $field_group_id] ?? [];
        $meza_project_sync_test_existing_fields_by_id[(int) $field_group_id] = meza_project_sync_test_upsert_field(
            $fields,
            $field
        );
        return;
    }

    foreach ($meza_project_sync_test_existing_fields_by_id as $field_group_id => $fields) {
        $did_update = false;
        $fields = meza_project_sync_test_upsert_field_under_parent($fields, $parent_key, $field, $did_update);
        if (!$did_update) {
            continue;
        }

        $meza_project_sync_test_existing_fields_by_id[(int) $field_group_id] = $fields;
        return;
    }
}

function acf_delete_field(int $field_id): void
{
    global $meza_project_sync_test_deleted_fields, $meza_project_sync_test_existing_fields_by_id;

    $meza_project_sync_test_deleted_fields[] = $field_id;

    foreach ($meza_project_sync_test_existing_fields_by_id as $field_group_id => $fields) {
        $meza_project_sync_test_existing_fields_by_id[$field_group_id] = meza_project_sync_test_remove_field_by_id($fields, $field_id);
    }
}

function meza_project_sync_test_remove_field_by_id(array $fields, int $field_id): array
{
    $remaining = [];
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }

        if ((int) ($field['ID'] ?? 0) === $field_id) {
            continue;
        }

        if (isset($field['sub_fields']) && is_array($field['sub_fields'])) {
            $field['sub_fields'] = meza_project_sync_test_remove_field_by_id($field['sub_fields'], $field_id);
        }

        if (isset($field['layouts']) && is_array($field['layouts'])) {
            foreach ($field['layouts'] as $layout_index => $layout) {
                if (!is_array($layout)) {
                    continue;
                }

                $layout['sub_fields'] = meza_project_sync_test_remove_field_by_id((array) ($layout['sub_fields'] ?? []), $field_id);
                $field['layouts'][$layout_index] = $layout;
            }
        }

        $remaining[] = $field;
    }

    return array_values($remaining);
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

require_once __DIR__ . '/../mz-acf-project-options/modules/managed-definitions.php';
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

function meza_project_sync_test_group_field(array $sub_fields): array
{
    return [
        'key' => 'field_project_group',
        'name' => 'project_group',
        'label' => 'Project Group',
        'type' => 'group',
        'sub_fields' => $sub_fields,
    ];
}

function meza_project_sync_test_flexible_field(array $layout_sub_fields): array
{
    return [
        'key' => 'field_project_flexible',
        'name' => 'project_flexible',
        'label' => 'Project Flexible',
        'type' => 'flexible_content',
        'layouts' => [
            [
                'key' => 'layout_project_feature',
                'name' => 'feature',
                'label' => 'Feature',
                'sub_fields' => $layout_sub_fields,
            ],
        ],
    ];
}

function meza_project_sync_test_with_parents(array $fields, string $parent_key): array
{
    foreach ($fields as $index => $field) {
        if (!is_array($field)) {
            continue;
        }

        $field['parent'] = $parent_key;
        $field['menu_order'] = (int) $index;
        if (isset($field['sub_fields']) && is_array($field['sub_fields'])) {
            $field['sub_fields'] = meza_project_sync_test_with_parents(
                array_values($field['sub_fields']),
                (string) ($field['key'] ?? $parent_key)
            );
        }

        if (isset($field['layouts']) && is_array($field['layouts'])) {
            foreach ($field['layouts'] as $layout_index => $layout) {
                if (!is_array($layout)) {
                    continue;
                }

                $layout['parent'] = (string) ($field['key'] ?? $parent_key);
                if (isset($layout['sub_fields']) && is_array($layout['sub_fields'])) {
                    $layout['sub_fields'] = meza_project_sync_test_with_parents(
                        array_values($layout['sub_fields']),
                        (string) ($layout['key'] ?? ($field['key'] ?? $parent_key))
                    );
                }

                $field['layouts'][$layout_index] = $layout;
            }
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

function meza_project_sync_test_updated_field_keys(): array
{
    global $meza_project_sync_test_updated_fields;

    return array_values(array_map(
        static fn(array $field): string => (string) ($field['key'] ?? ''),
        $meza_project_sync_test_updated_fields
    ));
}

function meza_project_sync_test_find_field_path(array $fields, array $path): ?array
{
    if ($path === []) {
        return null;
    }

    $name = array_shift($path);
    foreach ($fields as $field) {
        if (!is_array($field) || (string) ($field['name'] ?? '') !== $name) {
            continue;
        }

        if ($path === []) {
            return $field;
        }

        $nested = meza_project_sync_test_find_field_path((array) ($field['sub_fields'] ?? []), $path);
        if (is_array($nested)) {
            return $nested;
        }

        foreach ((array) ($field['layouts'] ?? []) as $layout) {
            if (!is_array($layout)) {
                continue;
            }

            $nested = meza_project_sync_test_find_field_path((array) ($layout['sub_fields'] ?? []), $path);
            if (is_array($nested)) {
                return $nested;
            }
        }
    }

    return null;
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
        $meza_project_sync_test_event_mode,
        $meza_project_sync_test_filters;

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
    $meza_project_sync_test_filters = [];

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
            'title' => (string) ($definition['title'] ?? ''),
            'post_status' => 'publish',
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
$base_nested_definition = meza_project_sync_test_field_group('group_project_nested', [
    meza_project_sync_test_repeater_field([
        meza_project_sync_test_text_field('field_project_metric_value', 'value', 'Value'),
    ]),
]);
$unrelated_default_definition = meza_project_sync_test_field_group('group_project_unrelated', [
    meza_project_sync_test_text_field('field_project_unrelated_title', 'title', 'Title'),
]);
$flexible_definition = meza_project_sync_test_field_group('group_project_flexible', [
    meza_project_sync_test_flexible_field([
        meza_project_sync_test_text_field('field_project_feature_title', 'title', 'Title'),
        meza_project_sync_test_text_field('field_project_feature_summary', 'summary', 'Summary'),
    ]),
]);
$group_definition = meza_project_sync_test_field_group('group_project_group', [
    meza_project_sync_test_group_field([
        meza_project_sync_test_text_field('field_project_group_title', 'title', 'Title'),
        meza_project_sync_test_text_field('field_project_group_extension', 'extension', 'Extension'),
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
    meza_project_sync_test_assert_same(
        ['field_project_repeater', 'field_project_metric_label'],
        meza_project_sync_test_updated_field_keys(),
        'The changed definition should synchronize the parent field and its missing nested child once.'
    );

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

    meza_project_sync_test_assert_same(
        ['field_project_repeater', 'field_project_metric_label'],
        meza_project_sync_test_updated_field_keys(),
        'Current fingerprint must not mask an incomplete nested tree.'
    );
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
    meza_project_sync_test_assert_same(
        ['field_project_repeater', 'field_project_metric_value', 'field_project_metric_label'],
        meza_project_sync_test_updated_field_keys(),
        'Missing nested value/label fields should be written as explicit child rows after their parent.'
    );
    meza_project_sync_test_assert(meza_shared_project_field_group_definition_trees_are_complete([$nested_definition]), 'The reconciled nested metrics field tree should be complete.');
});

meza_project_sync_test_case('stale repeater and group descendants block completeness then are deleted idempotently', function () use ($nested_definition, $group_definition): void {
    global $meza_project_sync_test_existing_fields_by_id,
        $meza_project_sync_test_updated_fields,
        $meza_project_sync_test_deleted_fields;

    $repeater_fields = meza_project_sync_test_with_parents(array_values((array) ($nested_definition['fields'] ?? [])), 'group_project_nested');
    $repeater_fields[0]['ID'] = 410;
    $repeater_fields[0]['sub_fields'][0]['ID'] = 411;
    $repeater_fields[0]['sub_fields'][1]['ID'] = 412;
    $repeater_fields[0]['sub_fields'][] = meza_project_sync_test_text_field('field_project_stale_repeater', 'stale_repeater', 'Stale Repeater') + [
        'ID' => 499,
        'parent' => 'field_project_repeater',
        'menu_order' => 2,
    ];

    $group_fields = meza_project_sync_test_with_parents(array_values((array) ($group_definition['fields'] ?? [])), 'group_project_group');
    $group_fields[0]['ID'] = 510;
    $group_fields[0]['sub_fields'][0]['ID'] = 511;
    $group_fields[0]['sub_fields'][1]['ID'] = 512;
    $group_fields[0]['sub_fields'][] = meza_project_sync_test_text_field('field_project_stale_group', 'stale_group', 'Stale Group') + [
        'ID' => 599,
        'parent' => 'field_project_group',
        'menu_order' => 2,
    ];

    meza_project_sync_test_reset([$nested_definition, $group_definition], [], [
        'group_project_nested' => $repeater_fields,
        'group_project_group' => $group_fields,
    ]);

    meza_project_sync_test_assert(!meza_shared_project_field_group_definition_trees_are_complete([$nested_definition, $group_definition]), 'Unexpected nested managed descendants must make the tree incomplete.');
    meza_sync_shared_project_field_group_fields();

    meza_project_sync_test_assert_same([499, 599], $meza_project_sync_test_deleted_fields, 'Each stale nested field ID must be passed to acf_delete_field().');
    $reconciled_repeater = $meza_project_sync_test_existing_fields_by_id[101] ?? [];
    $reconciled_group = $meza_project_sync_test_existing_fields_by_id[102] ?? [];
    meza_project_sync_test_assert_same(null, meza_project_sync_test_find_field_path($reconciled_repeater, ['project_repeater', 'stale_repeater']), 'The stale repeater field must be absent after reconciliation.');
    meza_project_sync_test_assert_same(null, meza_project_sync_test_find_field_path($reconciled_group, ['project_group', 'stale_group']), 'The stale group field must be absent after reconciliation.');
    $label = meza_project_sync_test_find_field_path($reconciled_repeater, ['project_repeater', 'label']);
    $extension = meza_project_sync_test_find_field_path($reconciled_group, ['project_group', 'extension']);
    meza_project_sync_test_assert_same(412, (int) ($label['ID'] ?? 0), 'Retained repeater extension fields must keep their IDs.');
    meza_project_sync_test_assert_same('field_project_repeater', (string) ($label['parent'] ?? ''), 'Retained repeater extension fields must retain their parent key.');
    meza_project_sync_test_assert_same(1, (int) ($label['menu_order'] ?? -1), 'Retained repeater extension fields must retain their order.');
    meza_project_sync_test_assert_same(512, (int) ($extension['ID'] ?? 0), 'Retained group extension fields must keep their IDs.');
    meza_project_sync_test_assert_same('field_project_group', (string) ($extension['parent'] ?? ''), 'Retained group extension fields must retain their parent key.');
    meza_project_sync_test_assert(meza_shared_project_field_group_definition_trees_are_complete([$nested_definition, $group_definition]), 'The corrected trees must become complete after stale descendants are deleted.');

    $meza_project_sync_test_updated_fields = [];
    $meza_project_sync_test_deleted_fields = [];
    meza_sync_shared_project_field_group_fields();
    meza_project_sync_test_assert_same([], $meza_project_sync_test_updated_fields, 'A second corrected-tree sync must perform no updates.');
    meza_project_sync_test_assert_same([], $meza_project_sync_test_deleted_fields, 'A second corrected-tree sync must perform no deletions.');
});

meza_project_sync_test_case('flexible layout descendants are reconciled recursively', function () use ($flexible_definition): void {
    global $meza_project_sync_test_existing_fields_by_id;

    meza_project_sync_test_reset([$flexible_definition], [], [
        'group_project_flexible' => meza_project_sync_test_with_parents([
            meza_project_sync_test_flexible_field([
                meza_project_sync_test_text_field('field_project_feature_title', 'title', 'Title'),
            ]),
        ], 'group_project_flexible'),
    ]);

    meza_sync_shared_project_field_group_fields();

    meza_project_sync_test_assert_same(
        ['field_project_flexible', 'field_project_feature_summary'],
        meza_project_sync_test_updated_field_keys(),
        'Missing flexible layout descendants should be written parent-first.'
    );

    $fields = $meza_project_sync_test_existing_fields_by_id[101] ?? [];
    $summary = meza_project_sync_test_find_field_path($fields, ['project_flexible', 'summary']);
    meza_project_sync_test_assert_same('layout_project_feature', (string) ($summary['parent'] ?? ''), 'Flexible layout child should be parented to the layout key.');
    meza_project_sync_test_assert_same(1, (int) ($summary['menu_order'] ?? -1), 'Flexible layout child should use the expected menu order.');
    meza_project_sync_test_assert(meza_shared_project_field_group_definition_trees_are_complete([$flexible_definition]), 'The reconciled flexible layout tree should be complete.');
});

meza_project_sync_test_case('stale flexible-layout descendants are deleted recursively', function () use ($flexible_definition): void {
    global $meza_project_sync_test_existing_fields_by_id, $meza_project_sync_test_deleted_fields;

    $fields = meza_project_sync_test_with_parents(array_values((array) ($flexible_definition['fields'] ?? [])), 'group_project_flexible');
    $fields[0]['ID'] = 610;
    $fields[0]['layouts'][0]['sub_fields'][0]['ID'] = 611;
    $fields[0]['layouts'][0]['sub_fields'][1]['ID'] = 612;
    $fields[0]['layouts'][0]['sub_fields'][] = meza_project_sync_test_text_field('field_project_stale_layout', 'stale_layout', 'Stale Layout') + [
        'ID' => 699,
        'parent' => 'layout_project_feature',
        'menu_order' => 2,
    ];

    meza_project_sync_test_reset([$flexible_definition], [], ['group_project_flexible' => $fields]);
    meza_project_sync_test_assert(!meza_shared_project_field_group_definition_trees_are_complete([$flexible_definition]), 'Unexpected flexible-layout descendants must make the tree incomplete.');
    meza_sync_shared_project_field_group_fields();
    meza_project_sync_test_assert_same([699], $meza_project_sync_test_deleted_fields, 'The stale flexible-layout field ID must be passed to acf_delete_field().');
    $reconciled = $meza_project_sync_test_existing_fields_by_id[101] ?? [];
    meza_project_sync_test_assert_same(null, meza_project_sync_test_find_field_path($reconciled, ['project_flexible', 'stale_layout']), 'The stale flexible-layout descendant must be absent after reconciliation.');
    meza_project_sync_test_assert(meza_shared_project_field_group_definition_trees_are_complete([$flexible_definition]), 'The flexible-layout tree must become complete after stale descendant deletion.');
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

    meza_project_sync_test_assert_same(
        ['field_project_repeater', 'field_project_metric_value', 'field_project_metric_label'],
        meza_project_sync_test_updated_field_keys(),
        'A complete disabled duplicate must not satisfy active DB tree completeness.'
    );
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

    meza_project_sync_test_assert_same(
        ['field_project_repeater', 'field_project_metric_value'],
        meza_project_sync_test_updated_field_keys(),
        'A mis-parented nested field should force reconciliation.'
    );
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
    meza_project_sync_test_assert_same(['field_project_title'], meza_project_sync_test_updated_field_keys(), 'Sync should not update fields from managed-deleted definitions.');
});

meza_project_sync_test_case('default-extension sync scopes nested changes to active canonical record', function () use ($base_nested_definition, $nested_definition, $unrelated_default_definition): void {
    global $meza_project_sync_test_field_group_ids,
        $meza_project_sync_test_field_groups_by_id,
        $meza_project_sync_test_existing_fields_by_id,
        $meza_project_sync_test_updated_fields;

    meza_project_sync_test_reset([], [], [
        'group_project_nested' => meza_project_sync_test_with_parents(
            array_values((array) ($base_nested_definition['fields'] ?? [])),
            'group_project_nested'
        ),
        'group_project_unrelated' => [],
    ], [$base_nested_definition, $unrelated_default_definition]);

    $meza_project_sync_test_field_groups_by_id[109] = [
        'ID' => 109,
        'key' => 'group_project_nested',
        'title' => 'Project Test Group',
        'post_status' => 'acf-disabled',
    ];
    $meza_project_sync_test_existing_fields_by_id[109] = meza_project_sync_test_with_parents(
        array_values((array) ($nested_definition['fields'] ?? [])),
        'group_project_nested'
    );
    $disabled_before = $meza_project_sync_test_existing_fields_by_id[109];
    $field_group_count_before = count($meza_project_sync_test_field_groups_by_id);

    add_filter('meza_shared_project_default_acf_field_group_definitions', static function (array $definitions) use ($nested_definition): array {
        foreach ($definitions as $index => $definition) {
            if (is_array($definition) && (string) ($definition['key'] ?? '') === 'group_project_nested') {
                $definitions[$index] = $nested_definition;
            }
        }

        return $definitions;
    });

    meza_project_sync_test_assert_same(
        101,
        meza_get_existing_editable_acf_field_group_id($nested_definition),
        'Managed field-group resolver should select the active canonical record before a disabled same-key duplicate.'
    );
    meza_project_sync_test_assert_same(
        ['group_project_nested'],
        array_map(static fn(array $definition): string => (string) ($definition['key'] ?? ''), meza_get_default_editable_field_group_fields_sync_definitions()),
        'Only the default definition whose effective field tree changed should enter the scoped sync set.'
    );

    meza_sync_default_editable_field_group_fields();

    meza_project_sync_test_assert_same(
        ['field_project_repeater', 'field_project_metric_label'],
        meza_project_sync_test_updated_field_keys(),
        'Default-extension sync should reconcile only the extended active canonical record.'
    );
    meza_project_sync_test_assert(meza_shared_project_field_group_definition_trees_are_complete([$nested_definition]), 'The active canonical record should become complete.');
    $active_fields = $meza_project_sync_test_existing_fields_by_id[101] ?? [];
    $value_field = meza_project_sync_test_find_field_path($active_fields, ['project_repeater', 'value']);
    $label_field = meza_project_sync_test_find_field_path($active_fields, ['project_repeater', 'label']);
    meza_project_sync_test_assert_same('field_project_repeater', (string) ($value_field['parent'] ?? ''), 'Existing nested value field should keep the intended parent key.');
    meza_project_sync_test_assert_same(0, (int) ($value_field['menu_order'] ?? -1), 'Existing nested value field should keep menu order 0.');
    meza_project_sync_test_assert_same('field_project_repeater', (string) ($label_field['parent'] ?? ''), 'New nested label field should be inserted under the repeater parent key.');
    meza_project_sync_test_assert_same(1, (int) ($label_field['menu_order'] ?? -1), 'New nested label field should be inserted with menu order 1.');
    meza_project_sync_test_assert_same($disabled_before, $meza_project_sync_test_existing_fields_by_id[109], 'Disabled historical duplicate should remain unchanged.');
    meza_project_sync_test_assert_same($field_group_count_before, count($meza_project_sync_test_field_groups_by_id), 'Default-extension sync should not seed a third field group.');
    meza_project_sync_test_assert(!meza_shared_project_field_group_definition_trees_are_complete([$unrelated_default_definition]), 'Unrelated incomplete default groups should remain outside the scoped sync gate.');
    meza_project_sync_test_assert_same(meza_get_default_editable_field_group_fields_sync_version(), get_option(meza_get_default_editable_field_group_fields_sync_option_name(), ''), 'Default-extension sync option should record once scoped active trees are complete.');
    $meza_project_sync_test_updated_fields = [];
    meza_sync_default_editable_field_group_fields();
    meza_project_sync_test_assert_same([], $meza_project_sync_test_updated_fields, 'Completed default-extension sync should not duplicate nested fields on repeat.');
});

meza_project_sync_test_case('unchanged default-extension set does not broadly reconcile defaults', function () use ($base_nested_definition, $unrelated_default_definition): void {
    global $meza_project_sync_test_updated_fields;

    meza_project_sync_test_reset([], [], [
        'group_project_unrelated' => [],
    ], [$base_nested_definition, $unrelated_default_definition]);

    meza_sync_default_editable_field_group_fields();
    $recorded_version = get_option(meza_get_default_editable_field_group_fields_sync_option_name(), '');

    meza_project_sync_test_assert_same([], $meza_project_sync_test_updated_fields, 'Unchanged default definitions should not trigger broad reconciliation.');
    meza_project_sync_test_assert_same(meza_get_default_editable_field_group_fields_sync_version(), $recorded_version, 'Unchanged default-extension fingerprint should still be recorded.');
    meza_project_sync_test_assert(!meza_shared_project_field_group_definition_trees_are_complete([$unrelated_default_definition]), 'An unchanged incomplete default group should not be repaired by extension sync.');

    $meza_project_sync_test_updated_fields = [];
    meza_sync_default_editable_field_group_fields();
    meza_project_sync_test_assert_same([], $meza_project_sync_test_updated_fields, 'Unchanged recorded extension set should skip repeat sync.');
});

meza_project_sync_test_case('default-extension sync option waits for scoped active tree completeness', function () use ($base_nested_definition, $nested_definition): void {
    global $meza_project_sync_test_field_groups_by_id,
        $meza_project_sync_test_existing_fields_by_id,
        $meza_project_sync_test_updated_fields;

    meza_project_sync_test_reset([], [], [], [$base_nested_definition]);
    unset($meza_project_sync_test_field_groups_by_id[101], $meza_project_sync_test_existing_fields_by_id[101]);

    add_filter('meza_shared_project_default_acf_field_group_definitions', static function (array $definitions) use ($nested_definition): array {
        return [$nested_definition];
    });

    meza_sync_default_editable_field_group_fields();

    meza_project_sync_test_assert_same([], $meza_project_sync_test_updated_fields, 'Missing active canonical record should not write fields.');
    meza_project_sync_test_assert_same('', get_option(meza_get_default_editable_field_group_fields_sync_option_name(), ''), 'Default-extension sync option must not record before scoped active trees are complete.');
});

meza_project_sync_test_case('managed-deleted default-extension definitions remain excluded from fingerprint and sync', function () use ($base_nested_definition, $nested_definition): void {
    global $meza_project_sync_test_updated_fields;

    meza_project_sync_test_reset([], ['group_project_nested'], [], [$base_nested_definition]);
    add_filter('meza_shared_project_default_acf_field_group_definitions', static function (array $definitions) use ($nested_definition): array {
        return [$nested_definition];
    });

    $effective_definitions = meza_get_default_editable_field_group_fields_sync_definitions();
    meza_project_sync_test_assert_same([], $effective_definitions, 'Deleted default-extension definitions should be excluded before diffing and fingerprinting.');

    meza_sync_default_editable_field_group_fields();
    $updated_keys = array_values(array_map(static fn(array $field): string => (string) ($field['key'] ?? ''), $meza_project_sync_test_updated_fields));

    meza_project_sync_test_assert_same([], $updated_keys, 'Default-extension sync should not update fields from managed-deleted definitions.');
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
