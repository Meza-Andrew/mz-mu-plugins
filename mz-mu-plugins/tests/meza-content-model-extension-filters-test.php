<?php

$meza_test_filters = [];
$meza_test_actions = [];
$meza_registered_post_types = [];
$meza_registered_taxonomies = [];
$meza_acf_local_internal_post_types = [];

function meza_content_model_extension_test_assert($condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function meza_content_model_extension_test_assert_same($expected, $actual, string $message): void
{
    if ($expected === $actual) {
        return;
    }

    fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
    exit(1);
}

function meza_content_model_extension_test(string $name, callable $run): void
{
    $run();
    echo "ok - {$name}\n";
}

function add_filter(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
    global $meza_test_filters;

    $meza_test_filters[$hook_name][$priority][] = $callback;
}

function apply_filters(string $hook_name, $value)
{
    global $meza_test_filters;

    if (empty($meza_test_filters[$hook_name])) {
        return $value;
    }

    ksort($meza_test_filters[$hook_name]);
    foreach ($meza_test_filters[$hook_name] as $callbacks) {
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

function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
    global $meza_test_actions;

    $meza_test_actions[$hook_name][$priority][] = $callback;
}

function sanitize_key($key): string
{
    $key = strtolower((string) $key);

    return preg_replace('/[^a-z0-9_\-]/', '', $key) ?: '';
}

function sanitize_title($title): string
{
    $title = strtolower((string) $title);
    $title = preg_replace('/[^a-z0-9]+/', '-', $title) ?: '';

    return trim($title, '-');
}

function post_type_exists(string $post_type): bool
{
    global $meza_registered_post_types;

    return array_key_exists($post_type, $meza_registered_post_types);
}

function taxonomy_exists(string $taxonomy): bool
{
    global $meza_registered_taxonomies;

    return array_key_exists($taxonomy, $meza_registered_taxonomies);
}

function register_post_type(string $post_type, array $args): void
{
    global $meza_registered_post_types;

    $meza_registered_post_types[$post_type] = $args;
}

function register_taxonomy(string $taxonomy, array $object_type, array $args): void
{
    global $meza_registered_taxonomies;

    $meza_registered_taxonomies[$taxonomy] = [
        'object_type' => $object_type,
        'args' => $args,
    ];
}

function get_option($name, $default = false)
{
    return $default;
}

function update_option($name, $value, $autoload = null): bool
{
    return true;
}

function acf_get_raw_post_types(): array
{
    return [];
}

function acf_add_options_page(array $page): void
{
}

function acf_add_local_field_group(array $group): void
{
}

function acf_add_local_internal_post_type(array $definition, string $internal_post_type): void
{
    global $meza_acf_local_internal_post_types;

    $meza_acf_local_internal_post_types[] = [
        'definition' => $definition,
        'internal_post_type' => $internal_post_type,
    ];
}

function meza_is_acf_export_tools_screen(): bool
{
    return true;
}

function meza_get_shared_project_acf_options_pages(): array
{
    return [];
}

function meza_get_builtin_acf_field_group_definitions(): array
{
    return [];
}

function meza_get_default_editable_acf_field_group_definitions(): array
{
    return [];
}

function meza_get_legacy_shared_project_acf_options_page_slug_map(): array
{
    return [];
}

function meza_get_faq_post_type_definition(): array
{
    return meza_content_model_extension_test_post_type_definition('faq', 'FAQ');
}

function meza_get_cta_post_type_definition(): array
{
    return meza_content_model_extension_test_post_type_definition('cta', 'CTA');
}

function meza_get_review_post_type_definition(): array
{
    return meza_content_model_extension_test_post_type_definition('review', 'Review');
}

function meza_get_organization_post_type_definition(): array
{
    return meza_content_model_extension_test_post_type_definition('organization', 'Organization');
}

function meza_get_organization_type_taxonomy_definition(): array
{
    return meza_content_model_extension_test_taxonomy_definition('organization-type', ['organization'], 'Organization Type');
}

function meza_content_model_extension_test_post_type_definition(string $slug, string $label): array
{
    return [
        'key' => 'post_type_' . str_replace('-', '_', $slug),
        'post_type' => $slug,
        'labels' => [
            'name' => $label . 's',
            'singular_name' => $label,
        ],
        'description' => '',
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'supports' => ['title', 'editor'],
        'rewrite' => [
            'permalink_rewrite' => 'post_type_key',
        ],
        'query_var' => 'post_type_key',
        'can_export' => true,
    ];
}

function meza_content_model_extension_test_taxonomy_definition(string $slug, array $object_type, string $label): array
{
    return [
        'key' => 'taxonomy_' . str_replace('-', '_', $slug),
        'taxonomy' => $slug,
        'object_type' => $object_type,
        'labels' => [
            'name' => $label . 's',
            'singular_name' => $label,
        ],
        'description' => '',
        'public' => true,
        'publicly_queryable' => true,
        'hierarchical' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'rewrite' => [
            'permalink_rewrite' => 'taxonomy_key',
        ],
        'query_var' => 'taxonomy_key',
    ];
}

require_once __DIR__ . '/../mz-acf-project-options/modules/field-groups/definitions/runtime.php';
require_once __DIR__ . '/../mz-acf-project-options/modules/managed-definitions.php';
require_once __DIR__ . '/../mz-acf-project-options/modules/registration.php';

meza_content_model_extension_test('default install has no fictional extension definitions', function (): void {
    $post_type_slugs = array_map(static function (array $definition): string {
        return sanitize_key((string) ($definition['post_type'] ?? ''));
    }, meza_get_local_acf_post_type_definitions());

    $taxonomy_slugs = array_map(static function (array $definition): string {
        return sanitize_key((string) ($definition['taxonomy'] ?? ''));
    }, meza_get_local_acf_taxonomy_definitions());

    meza_content_model_extension_test_assert(!in_array('fiction_book', $post_type_slugs, true), 'Default post-type definitions should not include the fictional extension.');
    meza_content_model_extension_test_assert(!in_array('fiction_genre', $taxonomy_slugs, true), 'Default taxonomy definitions should not include the fictional extension.');
});

add_filter('meza_shared_project_acf_post_type_definitions', static function (array $definitions): array {
    $definitions[] = meza_content_model_extension_test_post_type_definition('fiction_book', 'Fiction Book');

    return $definitions;
});

add_filter('meza_shared_project_acf_taxonomy_definitions', static function (array $definitions): array {
    $definitions[] = meza_content_model_extension_test_taxonomy_definition('fiction_genre', ['fiction_book'], 'Fiction Genre');

    return $definitions;
});

add_filter('meza_shared_project_default_acf_field_group_definitions', static function (array $definitions): array {
    $definitions[] = [
        'key' => 'group_fiction_default_section',
        'title' => 'Fiction Default Section',
        'fields' => [],
    ];

    return $definitions;
});

meza_content_model_extension_test('fictional filters append managed definitions', function (): void {
    $taxonomy_identifiers = meza_get_runtime_local_managed_acf_definition_identifiers('taxonomies');
    $post_type_definitions = meza_get_managed_acf_definitions_by_kind('post_types');

    $post_type_slugs = array_map(static function (array $definition): string {
        return sanitize_key((string) ($definition['post_type'] ?? ''));
    }, $post_type_definitions);

    meza_content_model_extension_test_assert(in_array('fiction_book', $post_type_slugs, true), 'Filtered post type should be present in managed definitions.');
    meza_content_model_extension_test_assert(in_array('fiction_genre', $taxonomy_identifiers, true), 'Filtered taxonomy should be tracked as a runtime-local managed definition.');

    meza_content_model_extension_test_assert(is_array(meza_get_managed_acf_definition_match('post_types', ['post_type' => 'fiction_book'])), 'Filtered post type should participate in managed-definition matching.');
    meza_content_model_extension_test_assert(is_array(meza_get_managed_acf_definition_match('taxonomies', ['taxonomy' => 'fiction_genre'])), 'Filtered taxonomy should participate in managed-definition matching.');
});

meza_content_model_extension_test('fictional default field-group filter appends effective definitions', function (): void {
    $definitions = meza_apply_shared_project_default_acf_field_group_definition_filters([]);
    $keys = array_map(static fn(array $definition): string => (string) ($definition['key'] ?? ''), $definitions);

    meza_content_model_extension_test_assert(in_array('group_fiction_default_section', $keys, true), 'Filtered default-editable field group should be present in effective definitions.');
});

meza_content_model_extension_test('fictional filters register through framework fallbacks', function (): void {
    global $meza_registered_post_types, $meza_registered_taxonomies;

    meza_register_local_acf_post_type_fallbacks();
    meza_register_local_acf_taxonomy_fallbacks();

    meza_content_model_extension_test_assert(array_key_exists('fiction_book', $meza_registered_post_types), 'Filtered post type should register through the standard fallback mechanism.');
    meza_content_model_extension_test_assert(array_key_exists('fiction_genre', $meza_registered_taxonomies), 'Filtered taxonomy should register through the standard fallback mechanism.');
    meza_content_model_extension_test_assert_same(['fiction_book'], $meza_registered_taxonomies['fiction_genre']['object_type'], 'Filtered taxonomy should preserve its declared object types.');
});

meza_content_model_extension_test('fictional filters register through ACF local internals', function (): void {
    global $meza_test_actions, $meza_acf_local_internal_post_types;

    putenv('MEZA_MANAGED_ACF_SUPPRESS_AUTOMATIC_MUTATIONS=1');

    foreach ($meza_test_actions['acf/init'] ?? [] as $callbacks) {
        foreach ($callbacks as $callback) {
            if ($callback instanceof Closure) {
                $callback();
            }
        }
    }

    putenv('MEZA_MANAGED_ACF_SUPPRESS_AUTOMATIC_MUTATIONS');

    $registered = [];
    foreach ($meza_acf_local_internal_post_types as $record) {
        $definition = $record['definition'];
        $internal_post_type = $record['internal_post_type'];
        if ($internal_post_type === 'acf-post-type') {
            $registered[] = sanitize_key((string) ($definition['post_type'] ?? ''));
        }
        if ($internal_post_type === 'acf-taxonomy') {
            $registered[] = sanitize_key((string) ($definition['taxonomy'] ?? ''));
        }
    }

    meza_content_model_extension_test_assert(in_array('fiction_book', $registered, true), 'Filtered post type should register through ACF local internals.');
    meza_content_model_extension_test_assert(in_array('fiction_genre', $registered, true), 'Filtered taxonomy should register through ACF local internals.');
});

echo "Meza content-model extension filter tests passed\n";
