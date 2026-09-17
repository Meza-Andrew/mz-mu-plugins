<?php

$meza_phase1a_filters = [];
$meza_phase1a_actions = [];
$meza_phase1a_options = [];
$meza_phase1a_role_terms = [];
$meza_phase1a_set_terms = [];

function meza_phase1a_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function meza_phase1a_assert_same($expected, $actual, string $message): void
{
    if ($expected === $actual) {
        return;
    }

    throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function meza_phase1a_test(string $name, callable $run): void
{
    $run();
    echo "ok - {$name}\n";
}

function add_filter(string $hook_name, $callback, int $priority = 10, int $accepted_args = 1): void
{
    global $meza_phase1a_filters;

    $meza_phase1a_filters[$hook_name][$priority][] = $callback;
}

function add_action(string $hook_name, $callback, int $priority = 10, int $accepted_args = 1): void
{
    global $meza_phase1a_actions;

    $meza_phase1a_actions[$hook_name][$priority][] = $callback;
}

function apply_filters(string $hook_name, $value)
{
    global $meza_phase1a_filters;

    foreach ($meza_phase1a_filters[$hook_name] ?? [] as $callbacks) {
        foreach ($callbacks as $callback) {
            $value = $callback($value);
        }
    }

    return $value;
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

function wp_strip_all_tags($text): string
{
    return strip_tags((string) $text);
}

function wp_unslash($value)
{
    return $value;
}

function is_admin(): bool
{
    return true;
}

function get_option($name, $default = false)
{
    global $meza_phase1a_options;

    return array_key_exists((string) $name, $meza_phase1a_options)
        ? $meza_phase1a_options[(string) $name]
        : $default;
}

function update_option($name, $value, $autoload = null): bool
{
    global $meza_phase1a_options;

    $meza_phase1a_options[(string) $name] = $value;

    return true;
}

function meza_get_business_information_toggle_value(string $field_key, string $option_name, int $default): bool
{
    return (bool) get_option($option_name, $default);
}

function meza_is_content_model_checkbox_selection_checked($field_keys, string $identifier): ?bool
{
    $field_keys = (array) $field_keys;
    foreach ($field_keys as $field_key) {
        if (!isset($_POST['acf']) || !is_array($_POST['acf']) || !array_key_exists($field_key, $_POST['acf'])) {
            continue;
        }

        return in_array($identifier, (array) $_POST['acf'][$field_key], true);
    }

    return null;
}

function meza_is_saved_content_model_checkbox_selection_checked($option_names, string $identifier): ?bool
{
    $has_saved = false;
    $selection = [];

    foreach ((array) $option_names as $option_name) {
        $value = get_option($option_name, null);
        if ($value !== null) {
            $has_saved = true;
            $selection = array_merge($selection, (array) $value);
        }
    }

    return $has_saved ? in_array($identifier, $selection, true) : null;
}

function meza_supports_profile_features(): bool
{
    return true;
}

function meza_is_nonprofit_business_type(): bool
{
    return false;
}

function meza_is_event_functionality_enabled(): bool
{
    return true;
}

function meza_events_have_speakers_topics(): bool
{
    return false;
}

function meza_is_acf_export_tools_screen(): bool
{
    return true;
}

function register_post_type(string $post_type, array $args): void
{
}

function register_taxonomy(string $taxonomy, array $object_type, array $args): void
{
}

function post_type_exists(string $post_type): bool
{
    return false;
}

function taxonomy_exists(string $taxonomy): bool
{
    return false;
}

function get_posts(array $args = []): array
{
    return [];
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
}

function meza_get_builtin_acf_field_group_definitions(): array
{
    return [];
}

function meza_get_shared_project_acf_options_pages(): array
{
    return [];
}

function meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies(): array
{
    return [[['param' => 'post_type', 'operator' => '==', 'value' => 'page']]];
}

function meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies_excluding_posts(): array
{
    return meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies();
}

function meza_get_acf_location_rules_hidden_by_default(): array
{
    return [[['param' => 'post_type', 'operator' => '==', 'value' => '__hidden__']]];
}

class WP_Post
{
}

class WP_Post_Type
{
}

class WP_Error
{
}

function is_wp_error($value): bool
{
    return $value instanceof WP_Error;
}

function wp_get_object_terms(int $object_id, string $taxonomy, array $args = []): array
{
    global $meza_phase1a_role_terms;

    return $taxonomy === 'role' ? ($meza_phase1a_role_terms[$object_id] ?? []) : [];
}

function wp_set_object_terms(int $object_id, array $terms, string $taxonomy, bool $append = false): array
{
    global $meza_phase1a_set_terms;

    $meza_phase1a_set_terms[] = compact('object_id', 'terms', 'taxonomy', 'append');

    return $terms;
}

require_once __DIR__ . '/../mz-acf-project-options/modules/business-information.php';
require_once __DIR__ . '/../mz-acf-project-options/modules/field-groups/definitions/business-information.php';
require_once __DIR__ . '/../mz-acf-project-options/modules/field-groups/definitions/section-field-groups.php';
require_once __DIR__ . '/../mz-acf-project-options/modules/field-groups/definitions/content-model-definitions.php';
require_once __DIR__ . '/../mz-acf-project-options/modules/field-groups/definitions/content-field-groups.php';
require_once __DIR__ . '/../mz-acf-project-options/modules/field-groups/definitions/runtime.php';
require_once __DIR__ . '/../mz-acf-project-options/modules/managed-definitions.php';
require_once __DIR__ . '/../mz-acf-project-options/modules/registration.php';

function meza_phase1a_find_field(array $fields, string $name): ?array
{
    foreach ($fields as $field) {
        if (is_array($field) && ($field['name'] ?? '') === $name) {
            return $field;
        }
    }

    return null;
}

function meza_phase1a_collect_field_values(array $fields, string $key): array
{
    $values = [];

    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }

        if (array_key_exists($key, $field)) {
            $values[] = $field[$key];
        }

        if (isset($field['sub_fields']) && is_array($field['sub_fields'])) {
            $values = array_merge($values, meza_phase1a_collect_field_values($field['sub_fields'], $key));
        }
    }

    return $values;
}

meza_phase1a_test('hero default is neutral', function (): void {
    $hero = meza_get_hero_section_field_group_definition();
    $section = meza_phase1a_find_field($hero['fields'], 'section_hero');
    $headline = meza_phase1a_find_field($section['sub_fields'] ?? [], 'headline');

    meza_phase1a_assert_same('', $headline['default_value'] ?? null, 'Hero headline default should be empty.');
    meza_phase1a_assert(trim((string) ($section['instructions'] ?? '')) !== '', 'Hero author instructions should be present.');
});

meza_phase1a_test('list past events is editorial only', function (): void {
    $group = meza_get_list_past_events_section_field_group_definition();
    $fields = $group['fields'] ?? [];
    $types = meza_phase1a_collect_field_values($fields, 'type');
    $names = meza_phase1a_collect_field_values($fields, 'name');

    foreach (['relationship', 'taxonomy', 'date_picker'] as $disallowed_type) {
        meza_phase1a_assert(!in_array($disallowed_type, $types, true), "Past Events must not include {$disallowed_type} controls.");
    }

    foreach ($names as $name) {
        $name = (string) $name;
        foreach (['selector', 'filter', 'cutoff', 'query', 'date_after', 'date_before'] as $disallowed_name) {
            meza_phase1a_assert(!str_contains($name, $disallowed_name), "Past Events field {$name} should not add selection/query behavior.");
        }
    }
});

meza_phase1a_test('list services selects ordered service IDs', function (): void {
    $group = meza_get_list_services_section_field_group_definition();
    $section = meza_phase1a_find_field($group['fields'], 'section_list-services');
    $services = meza_phase1a_find_field($section['sub_fields'] ?? [], 'services');

    meza_phase1a_assert_same('relationship', $services['type'] ?? null, 'List Services should use a relationship field.');
    meza_phase1a_assert_same(['service'], $services['post_type'] ?? null, 'List Services should select Service posts.');
    meza_phase1a_assert_same('id', $services['return_format'] ?? null, 'List Services should return IDs.');
    meza_phase1a_assert_same(1, $services['required'] ?? null, 'List Services should require selected services when enabled.');
    meza_phase1a_assert_same(1, $services['min'] ?? null, 'List Services should require at least one service.');
});

meza_phase1a_test('service icon fields are mutually exclusive', function (): void {
    $group = meza_get_service_field_group_definition();
    $fa_icon = meza_phase1a_find_field($group['fields'], 'fa_icon');
    $icon_image = meza_phase1a_find_field($group['fields'], 'icon_image');

    meza_phase1a_assert_same('text', $fa_icon['type'] ?? null, 'Service FA Icon should be a text field.');
    meza_phase1a_assert_same('image', $icon_image['type'] ?? null, 'Service Icon Image should be an image field.');
    meza_phase1a_assert_same('id', $icon_image['return_format'] ?? null, 'Service Icon Image should return an attachment ID.');

    $_POST['acf'] = [
        'field_meza_service_fa_icon' => 'fa-solid fa-star',
        'field_meza_service_icon_image' => 42,
    ];
    $valid = meza_validate_service_icon_choice(true, 'fa-solid fa-star', ['key' => 'field_meza_service_fa_icon']);
    meza_phase1a_assert(is_string($valid), 'Dual service icon values should be rejected.');
    $_POST = [];
});

meza_phase1a_test('event and post promotion fields are independently locatable', function (): void {
    $event = meza_get_event_promotion_field_group_definition();
    $post = meza_get_post_promotion_field_group_definition();
    $organization = meza_phase1a_find_field($event['fields'], 'organization');

    meza_phase1a_assert_same('Event Promotion', $event['title'] ?? null, 'Event promotion group should be standalone.');
    meza_phase1a_assert_same('event', $event['location'][0][0]['value'] ?? null, 'Event promotion should locate on event posts.');
    meza_phase1a_assert_same('post', $post['location'][0][0]['value'] ?? null, 'Post promotion should locate on posts.');
    meza_phase1a_assert_same(1, $organization['max'] ?? null, 'Event promotion organization should be single-select.');
    meza_phase1a_assert_same('id', $organization['return_format'] ?? null, 'Event promotion organization should return one ID.');
    meza_phase1a_assert_same(['featured_image'], $organization['elements'] ?? null, 'Organization featured image should be available as logo source.');
});

meza_phase1a_test('role taxonomy is disabled by default and validates applies-to', function (): void {
    global $meza_phase1a_options;

    $meza_phase1a_options = [];
    $_POST = [];
    meza_phase1a_assert(!meza_are_roles_enabled(), 'Role taxonomy should be disabled by default.');

    $taxonomy_slugs = array_map(static function (array $definition): string {
        return sanitize_key((string) ($definition['taxonomy'] ?? ''));
    }, meza_get_local_acf_taxonomy_definitions());
    meza_phase1a_assert(!in_array('role', $taxonomy_slugs, true), 'Default local taxonomy definitions should not include role.');

    $_POST['acf'] = [
        'field_meza_content_model_built_in_non_indexable_taxonomies' => ['role'],
    ];
    $valid = meza_validate_role_taxonomy_applies_to_value(true, []);
    meza_phase1a_assert(is_string($valid), 'Role Applies To should be required when Role is enabled.');
    $_POST = [];

    $meza_phase1a_options = [
        'options_content_model_built_in_non_indexable_taxonomies' => ['role'],
        'options_role_taxonomy_applies_to' => ['post', 'review'],
    ];
    meza_phase1a_assert(meza_are_roles_enabled(), 'Saved content model selection should enable Role.');
    meza_phase1a_assert_same(['post', 'review'], meza_get_role_taxonomy_object_types(), 'Role object types should come from Applies To.');

    $role_definition = meza_get_role_taxonomy_definition();
    meza_phase1a_assert_same(['post', 'review'], $role_definition['object_type'], 'Role definition should synchronize object types.');
    meza_phase1a_assert_same(0, $role_definition['public'], 'Role should not be public.');
    meza_phase1a_assert_same(0, $role_definition['publicly_queryable'], 'Role should not have public query routes.');
    meza_phase1a_assert_same(1, $role_definition['show_in_rest'], 'Role should be exposed in REST.');
    meza_phase1a_assert_same(1, $role_definition['show_admin_column'], 'Role should show admin columns.');

    $role_args = meza_get_local_acf_taxonomy_args($role_definition);
    meza_phase1a_assert_same(false, $role_args['query_var'], 'Role should not register a public query var.');
    meza_phase1a_assert_same(false, $role_args['rewrite'], 'Role should not register a public archive rewrite.');

    $taxonomy_slugs = array_map(static function (array $definition): string {
        return sanitize_key((string) ($definition['taxonomy'] ?? ''));
    }, meza_get_local_acf_taxonomy_definitions());
    meza_phase1a_assert(in_array('role', $taxonomy_slugs, true), 'Enabled Role should enter local taxonomy definitions.');
});

meza_phase1a_test('role assignment is normalized to one term', function (): void {
    global $meza_phase1a_role_terms, $meza_phase1a_set_terms;

    $meza_phase1a_role_terms = [
        123 => [4, 9],
    ];
    $meza_phase1a_set_terms = [];

    meza_enforce_single_role_assignment(123, [4, 9], [], 'role', false, []);

    meza_phase1a_assert_same([
        [
            'object_id' => 123,
            'terms' => [4],
            'taxonomy' => 'role',
            'append' => false,
        ],
    ], $meza_phase1a_set_terms, 'Role assignments should be reduced to one term.');
});

echo "Meza Phase 1A ACF foundation tests passed\n";
