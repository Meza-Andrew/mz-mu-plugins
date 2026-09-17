<?php

$meza_deleted_sync_test_actions = [];
$meza_deleted_sync_test_options = [];
$meza_deleted_sync_test_imports = [];
$meza_deleted_sync_test_existing_ids = [];
$meza_deleted_sync_test_existing_groups = [];
$meza_deleted_sync_test_existing_fields = [];
$meza_deleted_sync_test_force_rerun = false;

function meza_deleted_sync_test_assert(bool $condition, string $message): void
{
    if ($condition) {
        return;
    }

    throw new RuntimeException($message);
}

function meza_deleted_sync_test_assert_same($expected, $actual, string $message): void
{
    if ($expected === $actual) {
        return;
    }

    throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function meza_deleted_sync_test_case(string $name, callable $run): void
{
    $run();
    echo "ok - {$name}\n";
}

function add_action(string $hook_name, $callback, int $priority = 10, int $accepted_args = 1): void
{
    global $meza_deleted_sync_test_actions;

    $meza_deleted_sync_test_actions[$hook_name][$priority][] = $callback;
}

function add_filter(string $hook_name, $callback, int $priority = 10, int $accepted_args = 1): void
{
}

function sanitize_key($key): string
{
    $key = strtolower((string) $key);

    return preg_replace('/[^a-z0-9_\-]/', '', $key) ?: '';
}

function get_option($name, $default = false)
{
    global $meza_deleted_sync_test_options;

    return array_key_exists((string) $name, $meza_deleted_sync_test_options)
        ? $meza_deleted_sync_test_options[(string) $name]
        : $default;
}

function update_option($name, $value, $autoload = null): bool
{
    global $meza_deleted_sync_test_options;

    $meza_deleted_sync_test_options[(string) $name] = $value;

    return true;
}

function mz_plugins_should_rerun(): bool
{
    global $meza_deleted_sync_test_force_rerun;

    return $meza_deleted_sync_test_force_rerun;
}

function acf_import_field_group(array $definition): void
{
    global $meza_deleted_sync_test_imports;

    $meza_deleted_sync_test_imports[] = [
        'key' => (string) ($definition['key'] ?? ''),
        'title' => (string) ($definition['title'] ?? ''),
    ];
}

function meza_get_default_editable_acf_field_group_definitions(): array
{
    return [
        [
            'key' => 'group_meza_enabled_test_section',
            'title' => 'Enabled Test Section',
            'fields' => [],
        ],
        [
            'key' => 'group_meza_list_segments_section',
            'title' => 'List Segments Section',
            'fields' => [],
        ],
    ];
}

function meza_get_ecommerce_editable_acf_field_group_definitions(): array
{
    return [];
}

function meza_get_ecommerce_default_editable_acf_field_group_definitions(): array
{
    return [];
}

function meza_business_information_enables_ecommerce(): bool
{
    return false;
}

function acf_get_raw_field_groups(): array
{
    global $meza_deleted_sync_test_existing_ids, $meza_deleted_sync_test_existing_groups;

    if ($meza_deleted_sync_test_existing_groups !== []) {
        return $meza_deleted_sync_test_existing_groups;
    }

    $groups = [];
    foreach ($meza_deleted_sync_test_existing_ids as $key => $id) {
        $groups[] = [
            'ID' => (int) $id,
            'key' => (string) $key,
            'title' => (string) $key,
        ];
    }

    return $groups;
}

function acf_get_raw_fields($field_group_id): array
{
    global $meza_deleted_sync_test_existing_fields;

    return $meza_deleted_sync_test_existing_fields[(int) $field_group_id] ?? [];
}

function get_post_status($post_id): string
{
    global $meza_deleted_sync_test_existing_groups;

    foreach ($meza_deleted_sync_test_existing_groups as $group) {
        if ((int) ($group['ID'] ?? 0) === (int) $post_id) {
            return (string) ($group['post_status'] ?? 'publish');
        }
    }

    return 'publish';
}

function meza_get_builtin_acf_field_group_definitions(): array
{
    return [];
}

function meza_get_local_acf_post_type_definitions(): array
{
    return [];
}

function meza_get_local_acf_taxonomy_definitions(): array
{
    return [];
}

function meza_get_shared_project_acf_options_pages(): array
{
    return [];
}

function post_type_exists(string $post_type): bool
{
    return false;
}

function taxonomy_exists(string $taxonomy): bool
{
    return false;
}

function register_post_type(string $post_type, array $args): void
{
}

function register_taxonomy(string $taxonomy, array $object_type, array $args): void
{
}

function meza_should_skip_local_acf_post_type_definition(array $definition): bool
{
    return false;
}

require_once __DIR__ . '/../mz-acf-project-options/modules/managed-definitions.php';

function meza_deleted_sync_test_reset(bool $force_rerun): void
{
    global $meza_deleted_sync_test_options, $meza_deleted_sync_test_imports, $meza_deleted_sync_test_existing_ids, $meza_deleted_sync_test_existing_groups, $meza_deleted_sync_test_existing_fields, $meza_deleted_sync_test_force_rerun;

    $meza_deleted_sync_test_options = [
        meza_get_managed_acf_deleted_objects_option_name() => [
            'field_groups' => [
                'group_meza_list_segments_section',
            ],
            'post_types' => [],
            'taxonomies' => [],
            'options_pages' => [],
        ],
    ];
    $meza_deleted_sync_test_imports = [];
    $meza_deleted_sync_test_existing_ids = [];
    $meza_deleted_sync_test_existing_groups = [];
    $meza_deleted_sync_test_existing_fields = [];
    $meza_deleted_sync_test_force_rerun = $force_rerun;
}

function meza_deleted_sync_test_add_raw_field_group(int $id, string $key, string $title, string $post_status = 'publish', array $fields = []): void
{
    global $meza_deleted_sync_test_existing_groups, $meza_deleted_sync_test_existing_fields;

    $meza_deleted_sync_test_existing_groups[] = [
        'ID' => $id,
        'key' => $key,
        'title' => $title,
        'post_status' => $post_status,
    ];
    $meza_deleted_sync_test_existing_fields[$id] = $fields;
}

meza_deleted_sync_test_case('managed deleted field group is not recreated during normal sync', function (): void {
    global $meza_deleted_sync_test_imports, $meza_deleted_sync_test_options;

    meza_deleted_sync_test_reset(false);
    meza_seed_default_editable_acf_field_groups();

    meza_deleted_sync_test_assert_same(
        [
            [
                'key' => 'group_meza_enabled_test_section',
                'title' => 'Enabled Test Section',
            ],
        ],
        $meza_deleted_sync_test_imports,
        'Normal sync should import enabled groups and skip managed deleted groups.'
    );
    meza_deleted_sync_test_assert_same(
        [
            'field_groups' => ['group_meza_list_segments_section'],
            'post_types' => [],
            'taxonomies' => [],
            'options_pages' => [],
        ],
        $meza_deleted_sync_test_options[meza_get_managed_acf_deleted_objects_option_name()],
        'Normal sync should not mutate unrelated managed deleted state.'
    );
});

meza_deleted_sync_test_case('managed deleted field group is not recreated during forced sync', function (): void {
    global $meza_deleted_sync_test_imports, $meza_deleted_sync_test_options;

    meza_deleted_sync_test_reset(true);
    meza_seed_default_editable_acf_field_groups();

    meza_deleted_sync_test_assert_same(
        [
            [
                'key' => 'group_meza_enabled_test_section',
                'title' => 'Enabled Test Section',
            ],
        ],
        $meza_deleted_sync_test_imports,
        'Forced sync should still import enabled groups and skip managed deleted groups.'
    );
    meza_deleted_sync_test_assert_same(
        [
            'field_groups' => ['group_meza_list_segments_section'],
            'post_types' => [],
            'taxonomies' => [],
            'options_pages' => [],
        ],
        $meza_deleted_sync_test_options[meza_get_managed_acf_deleted_objects_option_name()],
        'Forced sync should not mutate unrelated managed deleted state.'
    );
});

meza_deleted_sync_test_case('explicit reactivation makes field group synchronizable', function (): void {
    global $meza_deleted_sync_test_imports, $meza_deleted_sync_test_options;

    meza_deleted_sync_test_reset(true);
    meza_clear_managed_acf_definition_deleted('field_groups', [
        'key' => 'group_meza_list_segments_section',
        'title' => 'List Segments Section',
    ]);

    meza_seed_default_editable_acf_field_groups();

    meza_deleted_sync_test_assert_same(
        [
            [
                'key' => 'group_meza_enabled_test_section',
                'title' => 'Enabled Test Section',
            ],
            [
                'key' => 'group_meza_list_segments_section',
                'title' => 'List Segments Section',
            ],
        ],
        $meza_deleted_sync_test_imports,
        'After explicit reactivation, the previously deleted group should be synchronizable.'
    );
    meza_deleted_sync_test_assert_same(
        [
            'field_groups' => [],
            'post_types' => [],
            'taxonomies' => [],
            'options_pages' => [],
        ],
        $meza_deleted_sync_test_options[meza_get_managed_acf_deleted_objects_option_name()],
        'Explicit reactivation should only clear the requested managed deleted field group.'
    );
});

meza_deleted_sync_test_case('existing managed field groups are not reimported', function (): void {
    global $meza_deleted_sync_test_existing_ids, $meza_deleted_sync_test_imports;

    meza_deleted_sync_test_reset(true);
    $meza_deleted_sync_test_existing_ids['group_meza_enabled_test_section'] = 42;

    meza_seed_default_editable_acf_field_groups();

    meza_deleted_sync_test_assert_same(
        [],
        $meza_deleted_sync_test_imports,
        'Existing enabled groups should not be duplicated and deleted groups should remain retired.'
    );
});

meza_deleted_sync_test_case('active managed field group wins over disabled same-key duplicate', function (): void {
    $definition = [
        'key' => 'group_meza_enabled_test_section',
        'title' => 'Enabled Test Section',
        'fields' => [],
    ];

    meza_deleted_sync_test_reset(true);
    meza_deleted_sync_test_add_raw_field_group(
        109,
        'group_meza_enabled_test_section',
        'Enabled Test Section',
        'acf-disabled',
        [
            [
                'key' => 'field_disabled_historical',
                'name' => 'disabled_historical',
            ],
        ]
    );
    meza_deleted_sync_test_add_raw_field_group(
        103,
        'group_meza_enabled_test_section',
        'Enabled Test Section',
        'publish',
        []
    );

    meza_deleted_sync_test_assert_same(
        103,
        meza_get_raw_editable_acf_field_group_id($definition),
        'Raw editable field-group identity should select the active canonical record before a disabled duplicate.'
    );
    meza_deleted_sync_test_assert_same(
        103,
        meza_get_existing_editable_acf_field_group_id($definition),
        'Existing editable field-group identity should fall back to the active raw record before a disabled duplicate.'
    );
});

meza_deleted_sync_test_case('existing duplicate managed field groups do not cause repair import', function (): void {
    global $meza_deleted_sync_test_imports;

    meza_deleted_sync_test_reset(true);
    meza_deleted_sync_test_add_raw_field_group(
        36,
        'group_meza_enabled_test_section',
        'Enabled Test Section',
        'publish',
        []
    );
    meza_deleted_sync_test_add_raw_field_group(
        37,
        'group_meza_enabled_test_section',
        'Enabled Test Section',
        'publish',
        [
            [
                'key' => 'field_meza_enabled_test_section',
                'name' => 'section_enabled-test',
            ],
        ]
    );

    meza_repair_empty_default_editable_acf_field_groups();

    meza_deleted_sync_test_assert_same(
        [],
        $meza_deleted_sync_test_imports,
        'An empty duplicate must not trigger a third import when an active matching managed group already has fields.'
    );
});

echo "Meza managed ACF deleted sync tests passed\n";
