<?php

if (!function_exists('meza_normalize_managed_acf_runtime_flag')) {
    function meza_normalize_managed_acf_runtime_flag($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((int) $value) !== 0;
        }

        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return false;
        }

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('meza_managed_acf_automatic_mutations_suppressed')) {
    function meza_managed_acf_automatic_mutations_suppressed(): bool
    {
        $suppressed = false;

        if (defined('MEZA_MANAGED_ACF_SUPPRESS_AUTOMATIC_MUTATIONS')) {
            $suppressed = meza_normalize_managed_acf_runtime_flag(MEZA_MANAGED_ACF_SUPPRESS_AUTOMATIC_MUTATIONS);
        }

        $env_value = getenv('MEZA_MANAGED_ACF_SUPPRESS_AUTOMATIC_MUTATIONS');
        if ($env_value !== false) {
            $suppressed = meza_normalize_managed_acf_runtime_flag($env_value);
        }

        if (function_exists('apply_filters')) {
            $suppressed = (bool) apply_filters('meza_managed_acf_automatic_mutations_suppressed', $suppressed);
        }

        return $suppressed;
    }
}

if (!function_exists('meza_managed_acf_automatic_mutations_enabled')) {
    function meza_managed_acf_automatic_mutations_enabled(): bool
    {
        return !meza_managed_acf_automatic_mutations_suppressed();
    }
}

if (!function_exists('meza_add_managed_acf_automatic_mutation_action')) {
    function meza_add_managed_acf_automatic_mutation_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
    {
        add_action($hook, static function (...$args) use ($callback): void {
            if (!meza_managed_acf_automatic_mutations_enabled()) {
                return;
            }

            call_user_func_array($callback, $args);
        }, $priority, $accepted_args);
    }
}

if (!function_exists('meza_get_managed_acf_maintenance_operations')) {
    function meza_get_managed_acf_maintenance_operations(): array
    {
        $operations = [
            'migrate_legacy_default_locality_term' => 'meza_migrate_legacy_default_locality_term',
            'seed_default_organization_type_terms' => 'meza_seed_default_organization_type_terms',
            'seed_default_profile_type_terms' => 'meza_seed_default_profile_type_terms',
            'seed_default_event_type_terms' => 'meza_seed_default_event_type_terms',
            'seed_default_locality_terms' => 'meza_seed_default_locality_terms',
            'migrate_auto_trashed_field_groups_to_disabled' => 'meza_migrate_auto_trashed_editable_acf_field_groups_to_disabled',
            'seed_default_taxonomies' => 'meza_seed_default_editable_acf_taxonomies',
            'sync_default_field_group_order_and_names' => 'meza_sync_default_editable_acf_field_group_order_and_names',
            'repair_empty_default_field_groups' => 'meza_repair_empty_default_editable_acf_field_groups',
            'seed_default_field_groups' => 'meza_seed_default_editable_acf_field_groups',
            'remove_legacy_business_information_toggle_field_groups' => 'meza_remove_legacy_business_information_toggle_field_groups',
            'sync_hero_section_field_group_locations' => 'meza_sync_hero_section_field_group_locations',
            'sync_list_reviews_section_field_group_locations' => 'meza_sync_list_reviews_section_field_group_locations',
            'sync_list_products_section_field_group_locations' => 'meza_sync_list_products_section_field_group_locations',
            'sync_list_profiles_section_field_group_fields' => 'meza_sync_list_profiles_section_field_group_fields',
            'cleanup_duplicate_list_profiles_group_fields' => 'meza_cleanup_duplicate_list_profiles_group_fields',
            'sync_list_profiles_section_secondary_link_subfield_name' => 'meza_sync_list_profiles_section_secondary_link_subfield_name',
            'sync_hidden_default_section_field_group_locations' => 'meza_sync_hidden_default_section_field_group_locations',
            'remove_cta_section_id_field' => 'meza_remove_cta_section_id_field',
            'sync_section_show_field_messages_blank' => 'meza_sync_section_show_field_messages_blank',
            'sync_section_group_sub_fields' => 'meza_sync_section_group_sub_fields',
            'sync_shared_project_field_group_fields' => 'meza_sync_shared_project_field_group_fields',
            'sync_default_extension_field_group_fields' => 'meza_sync_default_editable_field_group_fields',
            'sync_events_options_group_fields' => 'meza_sync_events_options_group_fields',
            'sync_section_field_group_menu_order' => 'meza_sync_section_field_group_menu_order',
            'dedupe_managed_field_groups' => 'meza_dedupe_managed_acf_field_groups',
            'cleanup_legacy_content_model_field_group_selection_options' => 'meza_cleanup_legacy_content_model_field_group_selection_options',
            'migrate_legacy_list_testimonials_meta' => 'meza_migrate_legacy_list_testimonials_meta_once',
            'sync_list_reviews_reference_values' => 'meza_sync_list_reviews_reference_values_once',
            'reset_legacy_section_text_defaults' => 'meza_reset_legacy_section_text_defaults_once',
            'migrate_certification_url_meta' => 'meza_migrate_certification_url_meta_once',
            'sync_ecommerce_defaults' => 'meza_sync_ecommerce_defaults',
            'sync_posts_category_configuration' => 'meza_sync_posts_category_configuration',
        ];

        if (function_exists('apply_filters')) {
            $operations = apply_filters('meza_managed_acf_maintenance_operations', $operations);
        }

        return array_filter((array) $operations, static fn($callback): bool => is_callable($callback));
    }
}

if (!function_exists('meza_run_managed_acf_maintenance_operation')) {
    function meza_run_managed_acf_maintenance_operation(string $operation): bool
    {
        $operations = meza_get_managed_acf_maintenance_operations();
        if (!isset($operations[$operation]) || !is_callable($operations[$operation])) {
            return false;
        }

        call_user_func($operations[$operation]);

        return true;
    }
}

if (!function_exists('meza_run_managed_acf_maintenance_operations')) {
    function meza_run_managed_acf_maintenance_operations(array $operations): array
    {
        $results = [];
        foreach ($operations as $operation) {
            $operation = (string) $operation;
            $results[$operation] = meza_run_managed_acf_maintenance_operation($operation);
        }

        return $results;
    }
}

if (!function_exists('meza_register_local_acf_post_type_fallbacks')) {
    function meza_register_local_acf_post_type_fallbacks(): void
    {
        foreach (meza_get_local_acf_post_type_definitions() as $definition) {
            $post_type = sanitize_key((string) ($definition['post_type'] ?? ''));
            if (
                $post_type === ''
                || post_type_exists($post_type)
                || meza_should_skip_local_acf_post_type_definition($definition)
                || meza_is_managed_acf_definition_manually_deleted('post_types', $definition)
            ) {
                continue;
            }

            register_post_type($post_type, meza_get_local_acf_post_type_args($definition));
        }
    }
}
add_action('init', 'meza_register_local_acf_post_type_fallbacks', 1);

if (!function_exists('meza_register_local_acf_taxonomy_fallbacks')) {
    function meza_register_local_acf_taxonomy_fallbacks(): void
    {
        foreach (meza_get_local_acf_taxonomy_definitions() as $definition) {
            $taxonomy = sanitize_key((string) ($definition['taxonomy'] ?? ''));
            $object_type = array_values(array_filter(array_map('sanitize_key', (array) ($definition['object_type'] ?? []))));

            if (
                $taxonomy === ''
                || taxonomy_exists($taxonomy)
                || $object_type === []
                || meza_is_managed_acf_definition_manually_deleted('taxonomies', $definition)
            ) {
                continue;
            }

            register_taxonomy($taxonomy, $object_type, meza_get_local_acf_taxonomy_args($definition));
        }
    }
}
add_action('init', 'meza_register_local_acf_taxonomy_fallbacks', 1);

if (!function_exists('meza_seed_default_organization_type_terms')) {
    function meza_seed_default_organization_type_terms(): void
    {
        $taxonomy = 'organization-type';
        if (!taxonomy_exists($taxonomy)) {
            return;
        }

        $ensure_term = static function (string $slug, string $name, int $parent_id = 0) use ($taxonomy): int {
            $matching_terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'parent' => $parent_id,
                'name' => $name,
                'number' => 1,
                'fields' => 'all',
            ]);
            if (is_array($matching_terms)) {
                foreach ($matching_terms as $matching_term) {
                    if ($matching_term instanceof WP_Term && strcasecmp($matching_term->name, $name) === 0) {
                        return (int) $matching_term->term_id;
                    }
                }
            }

            $existing = term_exists($slug, $taxonomy, $parent_id);
            if (is_array($existing) && !empty($existing['term_id'])) {
                return (int) $existing['term_id'];
            }

            if (is_int($existing) && $existing > 0) {
                return $existing;
            }

            $inserted = wp_insert_term($name, $taxonomy, array_filter([
                'slug' => $slug,
                'parent' => $parent_id > 0 ? $parent_id : null,
            ], static fn($value) => $value !== null));

            if (is_wp_error($inserted) || empty($inserted['term_id'])) {
                return 0;
            }

            return (int) $inserted['term_id'];
        };

        $ensure_term('partner', 'Partner');

    }
}
meza_add_managed_acf_automatic_mutation_action('init', 'meza_seed_default_organization_type_terms', 2);

if (!function_exists('meza_seed_default_profile_type_terms')) {
    function meza_seed_default_profile_type_terms(): void
    {
        if (!function_exists('meza_supports_profile_features') || !meza_supports_profile_features()) {
            return;
        }

        $taxonomy = 'profile-type';
        if (!taxonomy_exists($taxonomy)) {
            return;
        }

        $terms = [];

        if (function_exists('meza_events_have_speakers_topics') && meza_events_have_speakers_topics()) {
            $terms['speaker'] = 'Speaker';
        }

        if (function_exists('meza_is_nonprofit_business_type') && meza_is_nonprofit_business_type()) {
            $terms['donor'] = 'Donor';
        }

        foreach ($terms as $slug => $name) {
            if (term_exists($slug, $taxonomy)) {
                continue;
            }

            wp_insert_term($name, $taxonomy, [
                'slug' => $slug,
            ]);
        }
    }
}
meza_add_managed_acf_automatic_mutation_action('init', 'meza_seed_default_profile_type_terms', 2);

if (!function_exists('meza_seed_default_event_type_terms')) {
    function meza_seed_default_event_type_terms(): void
    {
        if (!function_exists('meza_events_have_speakers_topics') || !meza_events_have_speakers_topics()) {
            return;
        }

        $taxonomy = 'event-type';
        if (!taxonomy_exists($taxonomy)) {
            return;
        }

        if (term_exists('conference', $taxonomy)) {
            return;
        }

        wp_insert_term('Conference', $taxonomy, [
            'slug' => 'conference',
        ]);
    }
}
meza_add_managed_acf_automatic_mutation_action('init', 'meza_seed_default_event_type_terms', 2);

if (!function_exists('meza_migrate_legacy_default_locality_term')) {
    function meza_migrate_legacy_default_locality_term(): void
    {
        if (!(function_exists('meza_are_localities_enabled') && meza_are_localities_enabled())) {
            return;
        }

        $taxonomy = 'locality';
        if (!taxonomy_exists($taxonomy)) {
            return;
        }

        $current_term = get_term_by('slug', 'fredericksburg-virginia', $taxonomy);
        if ($current_term instanceof WP_Term) {
            return;
        }

        $legacy_term = get_term_by('slug', 'fredericksburg-va', $taxonomy);
        if (!($legacy_term instanceof WP_Term)) {
            return;
        }

        wp_update_term((int) $legacy_term->term_id, $taxonomy, [
            'name' => 'Fredericksburg, Virginia',
            'slug' => 'fredericksburg-virginia',
        ]);
    }
}
meza_add_managed_acf_automatic_mutation_action('init', 'meza_migrate_legacy_default_locality_term', 1);

if (!function_exists('meza_seed_default_locality_terms')) {
    function meza_seed_default_locality_terms(): void
    {
        if (!(function_exists('meza_are_localities_enabled') && meza_are_localities_enabled())) {
            return;
        }

        $taxonomy = 'locality';
        if (!taxonomy_exists($taxonomy)) {
            return;
        }

        if (term_exists('fredericksburg-virginia', $taxonomy)) {
            return;
        }

        wp_insert_term('Fredericksburg, Virginia', $taxonomy, [
            'slug' => 'fredericksburg-virginia',
        ]);
    }
}
meza_add_managed_acf_automatic_mutation_action('init', 'meza_seed_default_locality_terms', 2);

if (!function_exists('meza_ensure_locality_taxonomy_runtime_registration')) {
    function meza_ensure_locality_taxonomy_runtime_registration(): void
    {
        if (!(function_exists('meza_are_localities_enabled') && meza_are_localities_enabled())) {
            return;
        }

        $definition = meza_get_locality_taxonomy_definition();
        $taxonomy = sanitize_key((string) ($definition['taxonomy'] ?? ''));
        $object_types = array_values(array_filter(array_map('sanitize_key', (array) ($definition['object_type'] ?? []))));

        if ($taxonomy === '' || $object_types === []) {
            return;
        }

        if (!taxonomy_exists($taxonomy)) {
            register_taxonomy($taxonomy, $object_types, meza_get_local_acf_taxonomy_args($definition));
            return;
        }

        foreach ($object_types as $object_type) {
            if ($object_type === '' || !post_type_exists($object_type)) {
                continue;
            }

            register_taxonomy_for_object_type($taxonomy, $object_type);
        }
    }
}
add_action('init', 'meza_ensure_locality_taxonomy_runtime_registration', 3);

if (!function_exists('meza_should_seed_default_acf_field_groups')) {
    function meza_should_seed_default_acf_field_groups(): bool
    {
        if (function_exists('mz_plugins_should_rerun')) {
            return mz_plugins_should_rerun();
        }

        if (defined('MZ_FORCE_RERUN')) {
            $value = MZ_FORCE_RERUN;

            if (is_bool($value)) {
                return $value;
            }

            if (is_numeric($value)) {
                return ((int) $value) !== 0;
            }

            return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}

if (!function_exists('meza_should_ignore_managed_acf_deletions')) {
    function meza_should_ignore_managed_acf_deletions(): bool
    {
        return false;
    }
}

if (!function_exists('meza_get_managed_acf_deleted_objects_option_name')) {
    function meza_get_managed_acf_deleted_objects_option_name(): string
    {
        return 'meza_managed_acf_deleted_objects_v1';
    }
}

if (!function_exists('meza_normalize_managed_acf_deleted_objects')) {
    function meza_normalize_managed_acf_deleted_objects($value): array
    {
        $value = is_array($value) ? $value : [];
        $normalized = [];

        foreach (['field_groups', 'post_types', 'taxonomies', 'options_pages'] as $kind) {
            $normalized[$kind] = array_values(array_unique(array_filter(array_map(
                static function ($identifier): string {
                    return sanitize_key((string) $identifier);
                },
                is_array($value[$kind] ?? null) ? $value[$kind] : []
            ))));
        }

        return $normalized;
    }
}

if (!function_exists('meza_get_managed_acf_deleted_objects')) {
    function meza_get_managed_acf_deleted_objects(): array
    {
        return meza_prune_runtime_local_managed_acf_deleted_objects(
            meza_normalize_managed_acf_deleted_objects(
                get_option(meza_get_managed_acf_deleted_objects_option_name(), [])
            )
        );
    }
}

if (!function_exists('meza_update_managed_acf_deleted_objects')) {
    function meza_update_managed_acf_deleted_objects(array $deleted_objects): void
    {
        update_option(
            meza_get_managed_acf_deleted_objects_option_name(),
            meza_prune_runtime_local_managed_acf_deleted_objects($deleted_objects),
            false
        );
    }
}

if (!function_exists('meza_get_managed_acf_definition_identifier')) {
    function meza_get_managed_acf_definition_identifier(string $kind, array $definition): string
    {
        switch ($kind) {
            case 'field_groups':
                return sanitize_key((string) ($definition['key'] ?? ''));

            case 'post_types':
                return sanitize_key((string) ($definition['post_type'] ?? ''));

            case 'taxonomies':
                return sanitize_key((string) ($definition['taxonomy'] ?? ''));

            case 'options_pages':
                $slug = sanitize_key((string) ($definition['menu_slug'] ?? ''));
                $legacy_slug_map = meza_get_legacy_shared_project_acf_options_page_slug_map();

                return isset($legacy_slug_map[$slug])
                    ? sanitize_key((string) $legacy_slug_map[$slug])
                    : $slug;
        }

        return '';
    }
}

if (!function_exists('meza_get_runtime_local_managed_acf_definitions_by_kind')) {
    function meza_get_runtime_local_managed_acf_definitions_by_kind(string $kind): array
    {
        switch ($kind) {
            case 'field_groups':
                return meza_get_builtin_acf_field_group_definitions();

            case 'post_types':
                return meza_get_local_acf_post_type_definitions();

            case 'taxonomies':
                return meza_get_local_acf_taxonomy_definitions();

            case 'options_pages':
                return meza_get_shared_project_acf_options_pages();
        }

        return [];
    }
}

if (!function_exists('meza_get_runtime_local_managed_acf_definition_identifiers')) {
    function meza_get_runtime_local_managed_acf_definition_identifiers(string $kind): array
    {
        static $cache = [];

        if (array_key_exists($kind, $cache)) {
            return $cache[$kind];
        }

        $identifiers = [];

        foreach (meza_get_runtime_local_managed_acf_definitions_by_kind($kind) as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $identifier = meza_get_managed_acf_definition_identifier($kind, $definition);
            if ($identifier === '') {
                continue;
            }

            if (
                $kind === 'post_types'
                && function_exists('meza_content_model_object_management_supports_runtime_toggle')
                && meza_content_model_object_management_supports_runtime_toggle($kind, $identifier)
            ) {
                continue;
            }

            $identifiers[$identifier] = true;
        }

        $cache[$kind] = array_keys($identifiers);

        return $cache[$kind];
    }
}

if (!function_exists('meza_definition_is_runtime_local_managed')) {
    function meza_definition_is_runtime_local_managed(string $kind, array $definition): bool
    {
        $identifier = meza_get_managed_acf_definition_identifier($kind, $definition);
        if ($identifier === '') {
            return false;
        }

        return in_array($identifier, meza_get_runtime_local_managed_acf_definition_identifiers($kind), true);
    }
}

if (!function_exists('meza_prune_runtime_local_managed_acf_deleted_objects')) {
    function meza_prune_runtime_local_managed_acf_deleted_objects(array $deleted_objects): array
    {
        $deleted_objects = meza_normalize_managed_acf_deleted_objects($deleted_objects);

        foreach (['field_groups', 'post_types', 'taxonomies', 'options_pages'] as $kind) {
            $runtime_local_identifiers = meza_get_runtime_local_managed_acf_definition_identifiers($kind);
            if ($runtime_local_identifiers === []) {
                continue;
            }

            $deleted_objects[$kind] = array_values(array_filter(
                $deleted_objects[$kind] ?? [],
                static function (string $identifier) use ($runtime_local_identifiers): bool {
                    return !in_array($identifier, $runtime_local_identifiers, true);
                }
            ));
        }

        return $deleted_objects;
    }
}

if (!function_exists('meza_get_managed_acf_definitions_by_kind')) {
    function meza_get_managed_acf_definitions_by_kind(string $kind): array
    {
        switch ($kind) {
            case 'field_groups':
                return array_merge(
                    meza_get_builtin_acf_field_group_definitions(),
                    meza_get_default_editable_acf_field_group_definitions()
                );

            case 'post_types':
                return meza_get_local_acf_post_type_definitions();

            case 'taxonomies':
                return meza_get_local_acf_taxonomy_definitions();

            case 'options_pages':
                return meza_get_shared_project_acf_options_pages();
        }

        return [];
    }
}

if (!function_exists('meza_get_managed_acf_definition_match')) {
    function meza_get_managed_acf_definition_match(string $kind, array $candidate): ?array
    {
        if ($kind === 'field_groups') {
            return meza_get_matching_acf_field_group_definition($candidate, meza_get_managed_acf_definitions_by_kind($kind));
        }

        foreach (meza_get_managed_acf_definitions_by_kind($kind) as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            if (
                meza_get_managed_acf_definition_identifier($kind, $definition)
                === meza_get_managed_acf_definition_identifier($kind, $candidate)
            ) {
                return $definition;
            }
        }

        return null;
    }
}

if (!function_exists('meza_get_managed_acf_definition_internal_post_type')) {
    function meza_get_managed_acf_definition_internal_post_type(string $kind): string
    {
        switch ($kind) {
            case 'field_groups':
                return 'acf-field-group';

            case 'post_types':
                return 'acf-post-type';

            case 'taxonomies':
                return 'acf-taxonomy';

            case 'options_pages':
                return 'acf-ui-options-page';
        }

        return '';
    }
}

if (!function_exists('meza_normalize_managed_acf_definition_comparison_value')) {
    function meza_normalize_managed_acf_definition_comparison_value($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        if (meza_is_list_array_compat($value)) {
            return array_values(array_map(
                static function ($item) {
                    return meza_normalize_managed_acf_definition_comparison_value($item);
                },
                $value
            ));
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[$key] = meza_normalize_managed_acf_definition_comparison_value($item);
        }

        ksort($normalized);

        return $normalized;
    }
}

if (!function_exists('meza_prepare_options_page_definition_for_comparison')) {
    function meza_prepare_options_page_definition_for_comparison(array $definition): array
    {
        $menu_slug = sanitize_key((string) ($definition['menu_slug'] ?? ''));
        $legacy_slug_map = meza_get_legacy_shared_project_acf_options_page_slug_map();

        if (isset($legacy_slug_map[$menu_slug])) {
            $menu_slug = sanitize_key((string) $legacy_slug_map[$menu_slug]);
        }

        $definition['menu_slug'] = $menu_slug;

        if (empty($definition['key']) && $menu_slug !== '') {
            $definition['key'] = 'ui_options_page_' . $menu_slug;
        }

        if (empty($definition['title'])) {
            $definition['title'] = (string) ($definition['page_title'] ?? $definition['menu_title'] ?? $menu_slug);
        }

        return $definition;
    }
}

if (!function_exists('meza_get_managed_acf_definition_comparison_payload')) {
    function meza_get_managed_acf_definition_comparison_payload(string $kind, array $definition): array
    {
        if ($kind === 'field_groups') {
            $definition = meza_get_full_acf_field_group_for_matching($definition);

            if (function_exists('acf_validate_internal_post_type')) {
                $validated_definition = acf_validate_internal_post_type(
                    $definition,
                    meza_get_managed_acf_definition_internal_post_type($kind)
                );

                if (is_array($validated_definition)) {
                    $definition = $validated_definition;
                }
            }

            $fields = isset($definition['fields']) && is_array($definition['fields'])
                ? array_values(array_filter($definition['fields'], 'is_array'))
                : [];

            return meza_normalize_managed_acf_definition_comparison_value([
                'key' => (string) ($definition['key'] ?? ''),
                'title' => trim((string) ($definition['title'] ?? '')),
                'menu_order' => (int) ($definition['menu_order'] ?? 0),
                'active' => !empty($definition['active']),
                'description' => (string) ($definition['description'] ?? ''),
                'fields' => array_map(
                    static function (array $field): array {
                        return meza_normalize_acf_field_for_signature($field);
                    },
                    $fields
                ),
                'position' => (string) ($definition['position'] ?? ''),
                'style' => (string) ($definition['style'] ?? ''),
                'label_placement' => (string) ($definition['label_placement'] ?? ''),
                'instruction_placement' => (string) ($definition['instruction_placement'] ?? ''),
                'hide_on_screen' => meza_normalize_managed_acf_definition_comparison_value((array) ($definition['hide_on_screen'] ?? [])),
                'show_in_rest' => !empty($definition['show_in_rest']),
                'display_title' => (string) ($definition['display_title'] ?? ''),
                'allow_ai_access' => !empty($definition['allow_ai_access']),
                'ai_description' => (string) ($definition['ai_description'] ?? ''),
            ]);
        }

        if ($kind === 'options_pages') {
            $definition = meza_prepare_options_page_definition_for_comparison($definition);
        }

        $internal_post_type = meza_get_managed_acf_definition_internal_post_type($kind);

        if ($internal_post_type !== '' && function_exists('acf_validate_internal_post_type')) {
            $validated_definition = acf_validate_internal_post_type($definition, $internal_post_type);

            if (is_array($validated_definition)) {
                $definition = $validated_definition;
            }
        }

        unset(
            $definition['ID'],
            $definition['local'],
            $definition['_valid'],
            $definition['modified'],
            $definition['private'],
            $definition['local_file'],
            $definition['not_registered']
        );

        if ($kind === 'options_pages') {
            unset($definition['_menu_slug']);
        }

        return meza_normalize_managed_acf_definition_comparison_value($definition);
    }
}

if (!function_exists('meza_get_default_editable_acf_field_group_location_rules')) {
    function meza_get_default_editable_acf_field_group_location_rules(): array
    {
        return [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'page',
                ],
            ],
        ];
    }
}

if (!function_exists('meza_apply_default_editable_acf_field_group_location_rules')) {
    function meza_apply_default_editable_acf_field_group_location_rules(array $definitions): array
    {
        $location = meza_get_default_editable_acf_field_group_location_rules();
        $preserve_location_keys = [
            'group_meza_content_section',
            'group_meza_list_profiles_section',
            'group_meza_list_events_section',
            'group_69c16e4051e95',
            'group_6901490e04b96',
            'group_68f903208a8e1',
            'group_6913c78d0b35c',
            'group_6913c9b535e72',
            'group_6913cee5325cb',
            'group_688f8acec1ae2',
            'group_688f8c2740e76',
            'group_meza_list_sponsor_levels_section',
            'group_meza_contact_locations_section',
        ];

        foreach ($definitions as $index => $definition) {
            if (!is_array($definition) || empty($definition['fields'])) {
                continue;
            }

            if (in_array((string) ($definition['key'] ?? ''), $preserve_location_keys, true)) {
                continue;
            }

            $definitions[$index]['location'] = $location;
        }

        return $definitions;
    }
}

if (!function_exists('meza_managed_acf_definition_matches_default_config')) {
    function meza_managed_acf_definition_matches_default_config(string $kind, array $candidate, ?array $definition = null): bool
    {
        $definition = is_array($definition)
            ? $definition
            : meza_get_managed_acf_definition_match($kind, $candidate);

        if (!is_array($definition)) {
            return false;
        }

        return meza_get_managed_acf_definition_comparison_payload($kind, $candidate)
            === meza_get_managed_acf_definition_comparison_payload($kind, $definition);
    }
}

if (!function_exists('meza_get_managed_acf_definition_status')) {
    function meza_get_managed_acf_definition_status(string $kind, array $candidate): string
    {
        $definition = meza_get_managed_acf_definition_match($kind, $candidate);

        if (!is_array($definition)) {
            return 'custom';
        }

        return meza_managed_acf_definition_matches_default_config($kind, $candidate, $definition)
            ? 'default'
            : 'custom';
    }
}

if (!function_exists('meza_is_supported_acf_internal_post_type')) {
    function meza_is_supported_acf_internal_post_type(string $post_type): bool
    {
        return in_array($post_type, [
            'acf-field-group',
            'acf-post-type',
            'acf-taxonomy',
            'acf-ui-options-page',
        ], true);
    }
}

if (!function_exists('meza_get_current_acf_internal_admin_post_type')) {
    function meza_get_current_acf_internal_admin_post_type(): string
    {
        $request_post_type = isset($_REQUEST['post_type'])
            ? sanitize_key((string) wp_unslash($_REQUEST['post_type']))
            : '';

        if (meza_is_supported_acf_internal_post_type($request_post_type)) {
            return $request_post_type;
        }

        $post_id = isset($_REQUEST['post']) ? absint($_REQUEST['post']) : 0;
        if ($post_id <= 0) {
            return '';
        }

        $post_type = get_post_type($post_id);

        return is_string($post_type) && meza_is_supported_acf_internal_post_type($post_type)
            ? $post_type
            : '';
    }
}

if (!function_exists('meza_is_acf_admin_definition_management_request')) {
    function meza_is_acf_admin_definition_management_request(): bool
    {
        if (!is_admin()) {
            return false;
        }

        $post_type = meza_get_current_acf_internal_admin_post_type();
        if ($post_type === '') {
            return false;
        }

        $page = isset($_REQUEST['page']) ? sanitize_key((string) wp_unslash($_REQUEST['page'])) : '';
        if ($page === 'acf-tools') {
            return false;
        }

        global $pagenow;

        return in_array((string) $pagenow, ['edit.php', 'post.php', 'post-new.php'], true);
    }
}

if (!function_exists('meza_is_built_in_acf_admin_definition_object')) {
    function meza_is_built_in_acf_admin_definition_object(string $post_type, array $object): bool
    {
        if (
            $post_type === 'acf-field-group'
            && function_exists('meza_is_acf_field_group_override_record')
            && meza_is_acf_field_group_override_record($object)
        ) {
            return false;
        }

        if (!function_exists('meza_get_acf_export_tool_group_for_post')) {
            return false;
        }

        return meza_get_acf_export_tool_group_for_post($post_type, $object) === 'built_in';
    }
}

if (!function_exists('meza_filter_built_in_acf_admin_definition_objects')) {
    function meza_filter_built_in_acf_admin_definition_objects(string $post_type, array $objects): array
    {
        if (!meza_is_acf_admin_definition_management_request()) {
            return $objects;
        }

        if (!meza_is_supported_acf_internal_post_type($post_type)) {
            return $objects;
        }

        return array_values(array_filter($objects, static function ($object) use ($post_type): bool {
            return !is_array($object) || !meza_is_built_in_acf_admin_definition_object($post_type, $object);
        }));
    }
}

if (!function_exists('meza_get_built_in_acf_admin_definition_post_ids')) {
    function meza_get_built_in_acf_admin_definition_post_ids(string $post_type): array
    {
        if (
            !meza_is_supported_acf_internal_post_type($post_type)
            || !function_exists('acf_get_internal_post_type_posts')
        ) {
            return [];
        }

        $ids = [];

        foreach ((array) acf_get_internal_post_type_posts($post_type) as $object) {
            if (!is_array($object) || !meza_is_built_in_acf_admin_definition_object($post_type, $object)) {
                continue;
            }

            $object_id = (int) ($object['ID'] ?? 0);
            if ($object_id > 0) {
                $ids[] = $object_id;
            }
        }

        return array_values(array_unique($ids));
    }
}

if (!function_exists('meza_redirect_built_in_acf_admin_definition_edit_request')) {
    function meza_redirect_built_in_acf_admin_definition_edit_request(): void
    {
        if (!meza_is_acf_admin_definition_management_request()) {
            return;
        }

        global $pagenow;
        if ($pagenow !== 'post.php') {
            return;
        }

        $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
        if ($post_id <= 0) {
            return;
        }

        $post_type = get_post_type($post_id);
        if (!is_string($post_type) || !meza_is_supported_acf_internal_post_type($post_type)) {
            return;
        }

        $object = [];

        switch ($post_type) {
            case 'acf-field-group':
                if (function_exists('acf_get_field_group')) {
                    $field_group = acf_get_field_group($post_id);
                    $object = is_array($field_group)
                        ? meza_get_full_acf_field_group_for_matching($field_group)
                        : [];
                }
                break;

            case 'acf-post-type':
                $object = function_exists('acf_get_post_type')
                    ? (array) acf_get_post_type($post_id)
                    : [];
                break;

            case 'acf-taxonomy':
                $object = function_exists('acf_get_taxonomy')
                    ? (array) acf_get_taxonomy($post_id)
                    : [];
                break;

            case 'acf-ui-options-page':
                $object = function_exists('acf_get_ui_options_page')
                    ? (array) acf_get_ui_options_page($post_id)
                    : [];
                break;
        }

        if ($object === [] || !meza_is_built_in_acf_admin_definition_object($post_type, $object)) {
            return;
        }

        wp_safe_redirect(add_query_arg([
            'post_type' => $post_type,
            'meza_acf_locked' => '1',
        ], admin_url('edit.php')));
        exit;
    }
}

if (!function_exists('meza_is_database_backed_acf_ui_object')) {
    function meza_is_database_backed_acf_ui_object(array $object): bool
    {
        $local = strtolower(trim((string) ($object['local'] ?? '')));
        if (in_array($local, ['php', 'json'], true)) {
            return false;
        }

        return (int) ($object['ID'] ?? 0) > 0 || in_array($local, ['', 'db', 'database'], true);
    }
}

if (!function_exists('meza_get_database_backed_built_in_acf_field_group_definition')) {
    function meza_get_database_backed_built_in_acf_field_group_definition(array $field_group): ?array
    {
        if (
            !function_exists('meza_get_builtin_acf_field_group_definition_for_candidate')
            || !function_exists('meza_is_acf_field_group_override_record')
        ) {
            return null;
        }

        if (meza_is_acf_field_group_override_record($field_group)) {
            return null;
        }

        if (!meza_is_database_backed_acf_ui_object($field_group)) {
            return null;
        }

        $definition = meza_get_builtin_acf_field_group_definition_for_candidate($field_group);

        return is_array($definition) ? $definition : null;
    }
}

if (!function_exists('meza_get_database_acf_post_type_definition_by_slug')) {
    function meza_get_database_acf_post_type_definition_by_slug(string $post_type_slug, array $exclude_definition = []): ?array
    {
        $post_type_slug = sanitize_key($post_type_slug);
        if ($post_type_slug === '' || !function_exists('acf_get_raw_post_types')) {
            return null;
        }

        $excluded_key = sanitize_key((string) ($exclude_definition['key'] ?? ''));

        foreach ((array) acf_get_raw_post_types() as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $definition_slug = sanitize_key((string) ($definition['post_type'] ?? ''));
            if ($definition_slug !== $post_type_slug) {
                continue;
            }

            $definition_key = sanitize_key((string) ($definition['key'] ?? ''));
            if ($excluded_key !== '' && $definition_key === $excluded_key) {
                continue;
            }

            return $definition;
        }

        return null;
    }
}

if (!function_exists('meza_should_skip_local_acf_post_type_definition')) {
    function meza_should_skip_local_acf_post_type_definition(array $definition): bool
    {
        $post_type_slug = sanitize_key((string) ($definition['post_type'] ?? ''));
        if ($post_type_slug === '') {
            return false;
        }

        return is_array(meza_get_database_acf_post_type_definition_by_slug($post_type_slug, $definition));
    }
}

if (!function_exists('meza_is_managed_acf_definition_manually_deleted')) {
    function meza_is_managed_acf_definition_manually_deleted(string $kind, array $definition): bool
    {
        if (meza_should_ignore_managed_acf_deletions()) {
            return false;
        }

        $identifier = meza_get_managed_acf_definition_identifier($kind, $definition);
        if ($identifier === '') {
            return false;
        }

        $deleted_objects = meza_get_managed_acf_deleted_objects();

        return in_array($identifier, $deleted_objects[$kind] ?? [], true);
    }
}

if (!function_exists('meza_mark_managed_acf_definition_deleted')) {
    function meza_mark_managed_acf_definition_deleted(string $kind, array $definition): void
    {
        if (meza_definition_is_runtime_local_managed($kind, $definition)) {
            return;
        }

        $identifier = meza_get_managed_acf_definition_identifier($kind, $definition);
        if ($identifier === '') {
            return;
        }

        $deleted_objects = meza_get_managed_acf_deleted_objects();
        $deleted_objects[$kind] = $deleted_objects[$kind] ?? [];

        if (!in_array($identifier, $deleted_objects[$kind], true)) {
            $deleted_objects[$kind][] = $identifier;
            meza_update_managed_acf_deleted_objects($deleted_objects);
        }
    }
}

if (!function_exists('meza_clear_managed_acf_definition_deleted')) {
    function meza_clear_managed_acf_definition_deleted(string $kind, array $definition): void
    {
        $identifier = meza_get_managed_acf_definition_identifier($kind, $definition);
        if ($identifier === '') {
            return;
        }

        $deleted_objects = meza_get_managed_acf_deleted_objects();
        $deleted_objects[$kind] = array_values(array_filter(
            $deleted_objects[$kind] ?? [],
            static function (string $deleted_identifier) use ($identifier): bool {
                return $deleted_identifier !== $identifier;
            }
        ));

        meza_update_managed_acf_deleted_objects($deleted_objects);
    }
}

if (!function_exists('meza_track_managed_acf_definition_event')) {
    function meza_is_managed_acf_definition_tracking_suppressed(): bool
    {
        global $meza_managed_acf_definition_tracking_suppression_depth;

        return ((int) ($meza_managed_acf_definition_tracking_suppression_depth ?? 0)) > 0;
    }

    function meza_with_managed_acf_definition_tracking_suppressed(callable $callback)
    {
        global $meza_managed_acf_definition_tracking_suppression_depth;

        $meza_managed_acf_definition_tracking_suppression_depth = (int) ($meza_managed_acf_definition_tracking_suppression_depth ?? 0) + 1;

        try {
            return $callback();
        } finally {
            $meza_managed_acf_definition_tracking_suppression_depth = max(
                0,
                (int) ($meza_managed_acf_definition_tracking_suppression_depth ?? 0) - 1
            );
        }
    }

    function meza_track_managed_acf_definition_event(string $kind, array $candidate, bool $is_deleted): void
    {
        if (
            $kind === 'field_groups'
            && function_exists('meza_is_acf_field_group_override_record')
            && meza_is_acf_field_group_override_record($candidate)
        ) {
            return;
        }

        if (
            $kind === 'field_groups'
            && function_exists('meza_get_database_backed_built_in_acf_field_group_definition')
        ) {
            $built_in_definition = meza_get_database_backed_built_in_acf_field_group_definition($candidate);
            if (is_array($built_in_definition)) {
                meza_clear_managed_acf_definition_deleted('field_groups', $built_in_definition);
                return;
            }
        }

        $definition = meza_get_managed_acf_definition_match($kind, $candidate);
        if (!is_array($definition)) {
            return;
        }

        if ($is_deleted) {
            if (meza_is_managed_acf_definition_tracking_suppressed()) {
                return;
            }

            meza_mark_managed_acf_definition_deleted($kind, $definition);
            return;
        }

        meza_clear_managed_acf_definition_deleted($kind, $definition);
    }
}

if (!function_exists('meza_get_managed_acf_post_deletion_match')) {
    function meza_get_managed_acf_post_deletion_match(int $post_id): ?array
    {
        $post = get_post($post_id);
        if (!($post instanceof WP_Post)) {
            return null;
        }

        $kind = '';
        $candidate = null;

        switch ($post->post_type) {
            case 'acf-field-group':
                if (!function_exists('acf_get_field_group')) {
                    return null;
                }

                if (
                    function_exists('meza_is_acf_field_group_override_record')
                    && meza_is_acf_field_group_override_record($post_id)
                ) {
                    return null;
                }

                $kind = 'field_groups';
                $candidate = acf_get_field_group($post_id);
                break;

            case 'acf-post-type':
                if (!function_exists('acf_get_post_type')) {
                    return null;
                }

                $kind = 'post_types';
                $candidate = acf_get_post_type($post_id);
                break;

            case 'acf-taxonomy':
                if (!function_exists('acf_get_taxonomy')) {
                    return null;
                }

                $kind = 'taxonomies';
                $candidate = acf_get_taxonomy($post_id);
                break;

            case 'acf-ui-options-page':
                if (!function_exists('acf_get_ui_options_page')) {
                    return null;
                }

                $kind = 'options_pages';
                $candidate = acf_get_ui_options_page($post_id);
                break;
        }

        if ($kind === '' || !is_array($candidate)) {
            return null;
        }

        if (
            $kind === 'field_groups'
            && function_exists('meza_get_database_backed_built_in_acf_field_group_definition')
        ) {
            $built_in_definition = meza_get_database_backed_built_in_acf_field_group_definition($candidate);
            if (is_array($built_in_definition)) {
                return [
                    'kind' => 'field_groups',
                    'definition' => $built_in_definition,
                    'ignore_delete' => true,
                ];
            }
        }

        $definition = meza_get_managed_acf_definition_match($kind, $candidate);
        if (!is_array($definition)) {
            return null;
        }

        return [
            'kind' => $kind,
            'definition' => $definition,
        ];
    }
}

if (!function_exists('meza_track_managed_acf_post_deletion')) {
    function meza_track_managed_acf_post_deletion(int $post_id): void
    {
        $match = meza_get_managed_acf_post_deletion_match($post_id);
        if (!is_array($match) || !is_array($match['definition'] ?? null)) {
            return;
        }

        if (!empty($match['ignore_delete'])) {
            meza_clear_managed_acf_definition_deleted((string) $match['kind'], $match['definition']);
            return;
        }

        meza_mark_managed_acf_definition_deleted((string) $match['kind'], $match['definition']);
    }
}

if (!function_exists('meza_track_managed_acf_post_restoration')) {
    function meza_track_managed_acf_post_restoration(int $post_id): void
    {
        $match = meza_get_managed_acf_post_deletion_match($post_id);
        if (!is_array($match) || !is_array($match['definition'] ?? null)) {
            return;
        }

        meza_clear_managed_acf_definition_deleted((string) $match['kind'], $match['definition']);
    }
}

if (!function_exists('meza_sync_acf_field_group_override_record')) {
    function meza_resolve_acf_field_group_sync_target_id(array $field_group): int
    {
        $field_group_id = (int) ($field_group['ID'] ?? $field_group['id'] ?? 0);
        if ($field_group_id > 0) {
            return $field_group_id;
        }

        if (!function_exists('acf_get_raw_field_groups')) {
            return 0;
        }

        $target_key = sanitize_key((string) ($field_group['key'] ?? ''));
        $target_title = trim((string) ($field_group['title'] ?? ''));

        $matches = array_values(array_filter((array) acf_get_raw_field_groups(), static function ($raw_group) use ($target_key, $target_title): bool {
            if (!is_array($raw_group)) {
                return false;
            }

            if ($target_key !== '' && sanitize_key((string) ($raw_group['key'] ?? '')) === $target_key) {
                return true;
            }

            return $target_title !== '' && trim((string) ($raw_group['title'] ?? '')) === $target_title;
        }));

        if ($matches === []) {
            return 0;
        }

        usort($matches, static function (array $left, array $right): int {
            return ((int) ($right['ID'] ?? 0)) <=> ((int) ($left['ID'] ?? 0));
        });

        return (int) ($matches[0]['ID'] ?? 0);
    }

    function meza_sync_acf_field_group_override_record(int $post_id): void
    {
        if (
            $post_id <= 0
            || !function_exists('acf_get_field_group')
            || !function_exists('meza_get_acf_field_group_override_meta_key')
            || !function_exists('meza_get_acf_field_group_override_definition_key')
        ) {
            return;
        }

        $post = get_post($post_id);
        if (!($post instanceof WP_Post) || $post->post_type !== 'acf-field-group') {
            return;
        }

        $field_group = acf_get_field_group($post_id);
        if (!is_array($field_group)) {
            return;
        }

        $definition_key = meza_get_acf_field_group_override_definition_key($field_group);
        $meta_key = meza_get_acf_field_group_override_meta_key();

        if ($definition_key === '') {
            delete_post_meta($post_id, $meta_key);
            return;
        }

        update_post_meta($post_id, $meta_key, $definition_key);

        $duplicate_ids = get_posts([
            'post_type' => 'acf-field-group',
            'post_status' => ['publish', 'acf-disabled', 'trash'],
            'fields' => 'ids',
            'posts_per_page' => -1,
            'post__not_in' => [$post_id],
            'meta_key' => $meta_key,
            'meta_value' => $definition_key,
            'orderby' => 'ID',
            'order' => 'DESC',
            'no_found_rows' => true,
            'suppress_filters' => false,
        ]);

        foreach ($duplicate_ids as $duplicate_id) {
            delete_post_meta((int) $duplicate_id, $meta_key);
        }
    }
}

if (!function_exists('meza_maybe_sync_acf_field_group_override_record')) {
    function meza_maybe_sync_acf_field_group_override_record(int $post_id, WP_Post $post, bool $update): void
    {
        unset($update);

        if (
            $post->post_type !== 'acf-field-group'
            || wp_is_post_revision($post_id)
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        ) {
            return;
        }

        meza_sync_acf_field_group_override_record($post_id);
    }
}

meza_add_managed_acf_automatic_mutation_action('wp_trash_post', 'meza_track_managed_acf_post_deletion', 5);
meza_add_managed_acf_automatic_mutation_action('before_delete_post', 'meza_track_managed_acf_post_deletion', 5);
meza_add_managed_acf_automatic_mutation_action('untrashed_post', 'meza_track_managed_acf_post_restoration', 5);
meza_add_managed_acf_automatic_mutation_action('save_post_acf-field-group', 'meza_maybe_sync_acf_field_group_override_record', 20, 3);
meza_add_managed_acf_automatic_mutation_action('acf/update_field_group', static function (array $field_group): void {
    $field_group_id = function_exists('meza_resolve_acf_field_group_sync_target_id')
        ? meza_resolve_acf_field_group_sync_target_id($field_group)
        : (int) ($field_group['ID'] ?? 0);
    if ($field_group_id <= 0 || !function_exists('meza_sync_acf_field_group_override_record')) {
        return;
    }

    meza_sync_acf_field_group_override_record($field_group_id);
}, 20);
meza_add_managed_acf_automatic_mutation_action('acf/import_field_group', static function (array $field_group): void {
    $field_group_id = function_exists('meza_resolve_acf_field_group_sync_target_id')
        ? meza_resolve_acf_field_group_sync_target_id($field_group)
        : (int) ($field_group['ID'] ?? 0);
    if ($field_group_id <= 0 || !function_exists('meza_sync_acf_field_group_override_record')) {
        return;
    }

    meza_sync_acf_field_group_override_record($field_group_id);
}, 20);
meza_add_managed_acf_automatic_mutation_action('acf/trash_field_group', static function (array $field_group): void {
    meza_track_managed_acf_definition_event('field_groups', $field_group, true);
}, 5);
meza_add_managed_acf_automatic_mutation_action('acf/delete_field_group', static function (array $field_group): void {
    meza_track_managed_acf_definition_event('field_groups', $field_group, true);
}, 5);
meza_add_managed_acf_automatic_mutation_action('acf/untrash_field_group', static function (array $field_group): void {
    meza_track_managed_acf_definition_event('field_groups', $field_group, false);
}, 5);
meza_add_managed_acf_automatic_mutation_action('acf/trash_post_type', static function (array $post_type): void {
    meza_track_managed_acf_definition_event('post_types', $post_type, true);
}, 5);
meza_add_managed_acf_automatic_mutation_action('acf/delete_post_type', static function (array $post_type): void {
    meza_track_managed_acf_definition_event('post_types', $post_type, true);
}, 5);
meza_add_managed_acf_automatic_mutation_action('acf/untrash_post_type', static function (array $post_type): void {
    meza_track_managed_acf_definition_event('post_types', $post_type, false);
}, 5);
meza_add_managed_acf_automatic_mutation_action('acf/trash_taxonomy', static function (array $taxonomy): void {
    meza_track_managed_acf_definition_event('taxonomies', $taxonomy, true);
}, 5);
meza_add_managed_acf_automatic_mutation_action('acf/delete_taxonomy', static function (array $taxonomy): void {
    meza_track_managed_acf_definition_event('taxonomies', $taxonomy, true);
}, 5);
meza_add_managed_acf_automatic_mutation_action('acf/untrash_taxonomy', static function (array $taxonomy): void {
    meza_track_managed_acf_definition_event('taxonomies', $taxonomy, false);
}, 5);
meza_add_managed_acf_automatic_mutation_action('acf/trash_ui_options_page', static function (array $options_page): void {
    meza_track_managed_acf_definition_event('options_pages', $options_page, true);
}, 5);
meza_add_managed_acf_automatic_mutation_action('acf/delete_ui_options_page', static function (array $options_page): void {
    meza_track_managed_acf_definition_event('options_pages', $options_page, true);
}, 5);
meza_add_managed_acf_automatic_mutation_action('acf/untrash_ui_options_page', static function (array $options_page): void {
    meza_track_managed_acf_definition_event('options_pages', $options_page, false);
}, 5);

if (!function_exists('meza_apply_shared_project_default_acf_field_group_definition_filters')) {
    function meza_apply_shared_project_default_acf_field_group_definition_filters(array $definitions): array
    {
        if (function_exists('apply_filters')) {
            $definitions = apply_filters('meza_shared_project_default_acf_field_group_definitions', $definitions);
        }

        return array_values(array_filter((array) $definitions, 'is_array'));
    }
}

if (!function_exists('meza_get_default_editable_acf_field_group_definitions')) {
    function meza_get_ecommerce_editable_acf_field_group_definitions(): array
    {
        static $definitions = null;

        if (is_array($definitions)) {
            return $definitions;
        }

        $path = __DIR__ . '/defaults/acf-ecommerce-field-groups.json';
        if (!is_readable($path)) {
            $definitions = [];
            return $definitions;
        }

        $json = file_get_contents($path);
        if (!is_string($json) || trim($json) === '') {
            $definitions = [];
            return $definitions;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            $definitions = [];
            return $definitions;
        }

        $definitions = array_values(array_filter($decoded, 'is_array'));

        return $definitions;
    }

    function meza_get_ecommerce_default_editable_acf_field_group_definitions(): array
    {
        if (!meza_should_seed_ecommerce_default_editable_acf_field_groups()) {
            return [];
        }

        return meza_get_ecommerce_editable_acf_field_group_definitions();
    }

    if (!function_exists('meza_get_base_default_editable_acf_field_group_definitions')) {
        function meza_get_base_default_editable_acf_field_group_definitions(): array
        {
            $definitions = [
            meza_get_content_section_field_group_definition(),
            [
                'key' => 'group_meza_list_profiles_section',
                'title' => 'List Profiles Section',
                'fields' => [
                    [
                        'key' => 'field_meza_show_list_profiles',
                        'label' => 'Visibility',
                        'name' => 'show_list-profiles',
                        'aria-label' => '',
                        'type' => 'true_false',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'message' => '',
                        'default_value' => 0,
                        'allow_in_bindings' => 0,
                        'ui' => 0,
                        'ui_on_text' => '',
                        'ui_off_text' => '',
                    ],
                    [
                        'key' => 'field_meza_section_list_profiles',
                        'label' => 'Section',
                        'name' => 'section_list-profiles',
                        'aria-label' => '',
                        'type' => 'group',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_meza_show_list_profiles',
                                    'operator' => '==',
                                    'value' => '1',
                                ],
                            ],
                        ],
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'layout' => 'block',
                        'sub_fields' => [
                            [
                                'key' => 'field_meza_list_profiles_headline',
                                'label' => 'Headline (H2)',
                                'name' => 'headline',
                                'aria-label' => '',
                                'type' => 'text',
                                'instructions' => '',
                                'required' => 1,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'default_value' => '',
                                'maxlength' => '',
                                'allow_in_bindings' => 0,
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                            ],
                            [
                                'key' => 'field_meza_list_profiles_display',
                                'label' => 'Display',
                                'name' => 'display',
                                'aria-label' => '',
                                'type' => 'text',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'default_value' => '',
                                'maxlength' => '',
                                'allow_in_bindings' => 0,
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                            ],
                            [
                                'key' => 'field_meza_list_profiles_subhead',
                            ] + meza_get_standard_list_section_subhead_field('field_meza_list_profiles_subhead'),
                            [
                                'key' => 'field_meza_list_profiles_description',
                                'label' => 'Description',
                                'name' => 'description',
                                'aria-label' => '',
                                'type' => 'textarea',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'default_value' => '',
                                'maxlength' => '',
                                'allow_in_bindings' => 0,
                                'rows' => 2,
                                'placeholder' => '',
                                'new_lines' => 'wpautop',
                            ],
                            [
                                'key' => 'field_meza_list_profiles_link',
                                'label' => 'Link',
                                'name' => 'link',
                                'aria-label' => '',
                                'type' => 'link',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'return_format' => 'array',
                                'allow_in_bindings' => 0,
                            ],
                            [
                                'key' => 'field_meza_list_profiles_link_secondary',
                                'label' => 'Link (Secondary)',
                                'name' => 'link_secondary',
                                'aria-label' => '',
                                'type' => 'link',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => [
                                    [
                                        [
                                            'field' => 'field_meza_list_profiles_link',
                                            'operator' => '!=empty',
                                        ],
                                    ],
                                ],
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'return_format' => 'array',
                                'allow_in_bindings' => 0,
                            ],
                            [
                                'key' => 'field_meza_list_profiles_id',
                                'label' => 'ID',
                                'name' => 'id',
                                'aria-label' => '',
                                'type' => 'text',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'default_value' => '',
                                'maxlength' => '',
                                'allow_in_bindings' => 0,
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                            ],
                            [
                                'key' => 'field_meza_list_profiles_selected_profiles',
                                'label' => 'Profiles',
                                'name' => 'profiles',
                                'aria-label' => '',
                                'type' => 'relationship',
                                'instructions' => 'Selected Profiles can be dragged to control display order.',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => ['width' => '', 'class' => '', 'id' => ''],
                                'post_type' => ['profile'],
                                'post_status' => ['publish'],
                                'taxonomy' => [],
                                'filters' => ['search'],
                                'return_format' => 'id',
                                'min' => 0,
                                'max' => 0,
                                'elements' => [],
                                'bidirectional' => 0,
                                'bidirectional_target' => [],
                            ],
                        ],
                    ],
                ],
                'location' => meza_get_acf_location_rules_hidden_by_default(),
                'menu_order' => 15,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'top',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
                'allow_ai_access' => false,
                'ai_description' => '',
            ],
            [
                'key' => 'group_9f5b5c9a',
                'title' => 'List Localities Section',
                'fields' => [
                    [
                        'key' => 'field_6e5b5e63',
                        'label' => 'Visibility',
                        'name' => 'show_list-localities',
                        'aria-label' => '',
                        'type' => 'true_false',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => 0,
                        'message' => '',
                        'ui' => 0,
                        'ui_on_text' => '',
                        'ui_off_text' => '',
                    ],
                    [
                        'key' => 'field_b471daa1',
                        'label' => 'List Localities Section',
                        'name' => 'section_list-localities',
                        'aria-label' => '',
                        'type' => 'group',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_6e5b5e63',
                                    'operator' => '==',
                                    'value' => '1',
                                ],
                            ],
                        ],
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'layout' => 'block',
                        'sub_fields' => [
                            [
                                'key' => 'field_c042b116',
                                'label' => 'Headline (H2)',
                                'name' => 'headline',
                                'aria-label' => '',
                                'type' => 'text',
                                'instructions' => '',
                                'required' => 1,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'default_value' => '',
                                'maxlength' => '',
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                            ],
                            [
                                'key' => 'field_meza_list_localities_display',
                                'label' => 'Display',
                                'name' => 'display',
                                'aria-label' => '',
                                'type' => 'text',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'default_value' => '',
                                'maxlength' => '',
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                            ],
                            [
                                'key' => 'field_06661b49',
                            ] + meza_get_standard_list_section_subhead_field('field_06661b49'),
                            [
                                'key' => 'field_meza_list_localities_description',
                                'label' => 'Description',
                                'name' => 'description',
                                'aria-label' => '',
                                'type' => 'textarea',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'default_value' => '',
                                'maxlength' => '',
                                'rows' => 2,
                                'placeholder' => '',
                                'new_lines' => 'wpautop',
                            ],
                            [
                                'key' => 'field_meza_list_localities_link',
                                'label' => 'Link',
                                'name' => 'link',
                                'aria-label' => '',
                                'type' => 'link',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'return_format' => 'array',
                                'allow_in_bindings' => 0,
                            ],
                            [
                                'key' => 'field_adb2ba7f',
                                'label' => 'ID',
                                'name' => 'id',
                                'aria-label' => '',
                                'type' => 'text',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'default_value' => '',
                                'maxlength' => '',
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                            ],
                        ],
                    ],
                ],
                'location' => meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies(),
                'menu_order' => 26,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'top',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
                'allow_ai_access' => false,
                'ai_description' => '',
            ],
            [
                'key' => 'group_69c16e4051e95',
                'title' => 'Benefits Section',
                'fields' => [
                    [
                        'key' => 'field_69c16e4052284',
                        'label' => 'Visibility',
                        'name' => 'show_benefits',
                        'aria-label' => '',
                        'type' => 'true_false',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'message' => '',
                        'default_value' => 0,
                        'allow_in_bindings' => 0,
                        'ui' => 0,
                        'ui_on_text' => '',
                        'ui_off_text' => '',
                    ],
                    [
                        'key' => 'field_69c16e4052287',
                        'label' => 'Section',
                        'name' => 'section_benefits',
                        'aria-label' => '',
                        'type' => 'group',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_69c16e4052284',
                                    'operator' => '==',
                                    'value' => '1',
                                ],
                            ],
                        ],
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'layout' => 'block',
                        'sub_fields' => [
                            [
                                'key' => 'field_69c16e4052290',
                                'label' => 'Headline (H2)',
                                'name' => 'headline',
                                'aria-label' => '',
                                'type' => 'text',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'default_value' => '',
                                'maxlength' => '',
                                'allow_in_bindings' => 0,
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                            ],
                            [
                                'key' => 'field_meza_benefits_display',
                                'label' => 'Display',
                                'name' => 'display',
                                'aria-label' => '',
                                'type' => 'text',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'default_value' => '',
                                'maxlength' => '',
                                'allow_in_bindings' => 0,
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                            ],
                            [
                                'key' => 'field_69c16e4052291',
                            ] + meza_get_standard_list_section_subhead_field('field_69c16e4052291'),
                            [
                                'key' => 'field_69c16e4052292',
                                'label' => 'Description',
                                'name' => 'description',
                                'aria-label' => '',
                                'type' => 'textarea',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'default_value' => '',
                                'maxlength' => '',
                                'allow_in_bindings' => 0,
                                'rows' => '',
                                'placeholder' => '',
                                'new_lines' => '',
                            ],
                            [
                                'key' => 'field_69c16e4052293',
                                'label' => 'Link',
                                'name' => 'link',
                                'aria-label' => '',
                                'type' => 'link',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'return_format' => 'array',
                                'allow_in_bindings' => 0,
                            ],
                            [
                                'key' => 'field_69c16e4052294',
                                'label' => 'Image',
                                'name' => 'image',
                                'aria-label' => '',
                                'type' => 'image',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'return_format' => 'id',
                                'library' => 'all',
                                'min_width' => '',
                                'min_height' => '',
                                'min_size' => '',
                                'max_width' => '',
                                'max_height' => '',
                                'max_size' => '',
                                'mime_types' => '',
                                'allow_in_bindings' => 0,
                                'preview_size' => 'medium',
                            ],
                            [
                                'key' => 'field_69c16e4052295',
                                'label' => 'Points',
                                'name' => 'points',
                                'aria-label' => '',
                                'type' => 'repeater',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'layout' => 'row',
                                'pagination' => 0,
                                'min' => 0,
                                'max' => 0,
                                'collapsed' => '',
                                'button_label' => 'Add Point',
                                'rows_per_page' => 20,
                                'sub_fields' => [
                                    [
                                        'key' => 'field_69c16e4052296',
                                        'label' => 'Headline (H3)',
                                        'name' => 'headline_h3',
                                        'aria-label' => '',
                                        'type' => 'text',
                                        'instructions' => '',
                                        'required' => 0,
                                        'conditional_logic' => 0,
                                        'wrapper' => [
                                            'width' => '',
                                            'class' => '',
                                            'id' => '',
                                        ],
                                        'default_value' => '',
                                        'maxlength' => '',
                                        'allow_in_bindings' => 0,
                                        'placeholder' => '',
                                        'prepend' => '',
                                        'append' => '',
                                        'parent_repeater' => 'field_69c16e4052295',
                                    ],
                                    [
                                        'key' => 'field_69c16e4052297',
                                        'label' => 'Description',
                                        'name' => 'description',
                                        'aria-label' => '',
                                        'type' => 'textarea',
                                        'instructions' => '',
                                        'required' => 0,
                                        'conditional_logic' => 0,
                                        'wrapper' => [
                                            'width' => '',
                                            'class' => '',
                                            'id' => '',
                                        ],
                                        'default_value' => '',
                                        'maxlength' => '',
                                        'allow_in_bindings' => 0,
                                        'rows' => '',
                                        'placeholder' => '',
                                        'new_lines' => '',
                                        'parent_repeater' => 'field_69c16e4052295',
                                    ],
                                    [
                                        'key' => 'field_69c16e4052298',
                                        'label' => 'Icon',
                                        'name' => 'icon',
                                        'aria-label' => '',
                                        'type' => 'text',
                                        'instructions' => '',
                                        'required' => 0,
                                        'conditional_logic' => 0,
                                        'wrapper' => [
                                            'width' => '',
                                            'class' => '',
                                            'id' => '',
                                        ],
                                        'default_value' => '',
                                        'maxlength' => '',
                                        'allow_in_bindings' => 0,
                                        'placeholder' => '',
                                        'prepend' => '',
                                        'append' => '',
                                        'parent_repeater' => 'field_69c16e4052295',
                                    ],
                                ],
                            ],
                            [
                                'key' => 'field_69c16e4052299',
                                'label' => 'ID',
                                'name' => 'id',
                                'aria-label' => '',
                                'type' => 'text',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'default_value' => '',
                                'maxlength' => '',
                                'allow_in_bindings' => 0,
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                            ],
                        ],
                    ],
                ],
                'location' => meza_get_acf_location_rules_hidden_by_default(),
                'menu_order' => 8,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'top',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
                'allow_ai_access' => false,
                'ai_description' => '',
            ],
            meza_get_list_events_section_field_group_definition(),
            meza_get_list_segments_section_field_group_definition(),
            meza_get_list_partners_section_field_group_definition(),
            [
                'key' => 'group_6901490e04b96',
                'title' => 'Gallery Section',
                'fields' => [
                    [
                        'key' => 'field_6901490e64a10',
                        'label' => 'Visibility',
                        'name' => 'show_gallery',
                        'aria-label' => '',
                        'type' => 'true_false',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'message' => '',
                        'default_value' => 0,
                        'allow_in_bindings' => 0,
                        'ui' => 0,
                        'ui_on_text' => '',
                        'ui_off_text' => '',
                    ],
                    [
                        'key' => 'field_6901492764a11',
                        'label' => 'Section',
                        'name' => 'section_gallery',
                        'aria-label' => '',
                        'type' => 'group',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_6901490e64a10',
                                    'operator' => '==',
                                    'value' => '1',
                                ],
                            ],
                        ],
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'layout' => 'block',
                        'sub_fields' => [
                            [
                                'key' => 'field_6901493264a12',
                                'label' => 'Headline (H2)',
                                'name' => 'headline',
                                'aria-label' => '',
                                'type' => 'text',
                                'instructions' => '',
                                'required' => 1,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'default_value' => '',
                                'maxlength' => '',
                                'allow_in_bindings' => 0,
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                            ],
                            [
                                'key' => 'field_meza_gallery_display',
                                'label' => 'Display',
                                'name' => 'display',
                                'aria-label' => '',
                                'type' => 'text',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'default_value' => '',
                                'maxlength' => '',
                                'allow_in_bindings' => 0,
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                            ],
                            [
                                'key' => 'field_6901493a64a13',
                            ] + meza_get_standard_list_section_subhead_field('field_6901493a64a13'),
                            [
                                'key' => 'field_meza_gallery_description',
                                'label' => 'Description',
                                'name' => 'description',
                                'aria-label' => '',
                                'type' => 'textarea',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'default_value' => '',
                                'maxlength' => '',
                                'allow_in_bindings' => 0,
                                'rows' => 2,
                                'placeholder' => '',
                                'new_lines' => 'wpautop',
                            ],
                            [
                                'key' => 'field_6901494264a14',
                                'label' => 'Link',
                                'name' => 'link',
                                'aria-label' => '',
                                'type' => 'link',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'return_format' => 'array',
                                'allow_in_bindings' => 0,
                            ],
                            [
                                'key' => 'field_69028fd8fd533',
                                'label' => 'Gallery',
                                'name' => 'gallery',
                                'aria-label' => '',
                                'type' => 'repeater',
                                'instructions' => '',
                                'required' => 1,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'layout' => 'table',
                                'pagination' => 0,
                                'min' => 1,
                                'max' => 0,
                                'collapsed' => '',
                                'button_label' => 'Add Media',
                                'rows_per_page' => 20,
                                'sub_fields' => [
                                    [
                                        'key' => 'field_6902901cfd535',
                                        'label' => 'Video Embed',
                                        'name' => 'video_embed',
                                        'aria-label' => '',
                                        'type' => 'oembed',
                                        'instructions' => '',
                                        'required' => 0,
                                        'conditional_logic' => 0,
                                        'wrapper' => [
                                            'width' => '',
                                            'class' => '',
                                            'id' => '',
                                        ],
                                        'menu_order' => 0,
                                        'width' => '',
                                        'height' => '',
                                        'allow_in_bindings' => 0,
                                        'parent_repeater' => 'field_69028fd8fd533',
                                    ],
                                    [
                                        'key' => 'field_69028febfd534',
                                        'label' => 'Image',
                                        'name' => 'image',
                                        'aria-label' => '',
                                        'type' => 'image',
                                        'instructions' => '',
                                        'required' => 0,
                                        'conditional_logic' => 0,
                                        'wrapper' => [
                                            'width' => '',
                                            'class' => '',
                                            'id' => '',
                                        ],
                                        'menu_order' => 1,
                                        'return_format' => 'id',
                                        'library' => 'all',
                                        'min_width' => '',
                                        'min_height' => '',
                                        'min_size' => '',
                                        'max_width' => '',
                                        'max_height' => '',
                                        'max_size' => '',
                                        'mime_types' => '',
                                        'allow_in_bindings' => 0,
                                        'preview_size' => 'medium',
                                        'parent_repeater' => 'field_69028fd8fd533',
                                    ],
                                ],
                            ],
                            [
                                'key' => 'field_6901494a64a15',
                                'label' => 'ID',
                                'name' => 'id',
                                'aria-label' => '',
                                'type' => 'text',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'default_value' => '',
                                'maxlength' => '',
                                'allow_in_bindings' => 0,
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                            ],
                        ],
                    ],
                ],
                'location' => meza_get_acf_location_rules_hidden_by_default(),
                'menu_order' => 14,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'top',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
                'allow_ai_access' => false,
                'ai_description' => '',
            ],
        ];

        if (function_exists('meza_is_nonprofit_business_type') && meza_is_nonprofit_business_type()) {
            $definitions[] = meza_get_standard_list_section_field_group_definition([
                'group_key' => 'group_6913c78d0b35c',
                'title' => 'List Donor Levels Section',
                'visibility_key' => 'field_6913c78d0ea98',
                'visibility_name' => 'show_list-donor-levels',
                'section_key' => 'field_6913c7ad0ea99',
                'section_name' => 'section_list-donor-levels',
                'headline_key' => 'field_6913c7c30ea9a',
                'include_display' => true,
                'display_key' => 'field_meza_list_donor_levels_display',
                'subhead_key' => 'field_6913c7cf0ea9b',
                'include_description' => true,
                'description_key' => 'field_meza_list_donor_levels_description',
                'link_key' => 'field_6913d07aef493',
                'id_key' => 'field_6913c7de0ea9c',
                'location' => meza_get_acf_location_rules_hidden_by_default(),
                'menu_order' => 10,
            ]);

            $definitions[] = meza_get_standard_list_section_field_group_definition([
                'group_key' => 'group_6913c9b535e72',
                'title' => 'List Certifications Section',
                'visibility_key' => 'field_6913c9b53a5d8',
                'visibility_name' => 'show_list-certifications',
                'section_key' => 'field_6913c9b53a628',
                'section_name' => 'section_list-certifications',
                'headline_key' => 'field_6913c9b53fab8',
                'include_display' => true,
                'display_key' => 'field_meza_list_certifications_display',
                'subhead_key' => 'field_meza_list_certifications_subhead',
                'include_description' => true,
                'description_key' => 'field_6913c9b53faf7',
                'link_key' => 'field_meza_list_certifications_link',
                'id_key' => 'field_6913c9b53fb37',
                'location' => meza_get_acf_location_rules_hidden_by_default(),
                'menu_order' => 17,
            ]);

            $definitions[] = [
                'key' => 'group_6913cee5325cb',
                'title' => 'List Donation Options Section',
                'fields' => [
                    meza_get_standard_list_section_visibility_field(
                        'field_6913cee5378be',
                        'show_list-donation-options'
                    ),
                    meza_get_standard_list_section_group_field(
                        'field_6913cee537904',
                        'section_list-donation-options',
                        'field_6913cee5378be',
                        [
                            meza_get_standard_list_section_text_field(
                                'field_6913cee53dc7e',
                                'Headline (H2)',
                                'headline',
                                '',
                                1
                            ),
                            meza_get_standard_list_section_text_field(
                                'field_meza_list_donation_options_display',
                                'Display',
                                'display'
                            ),
                            meza_get_standard_list_section_text_field(
                                'field_6913cee53dcc6',
                                'Subhead',
                                'subhead'
                            ),
                            meza_get_standard_list_section_description_field(
                                'field_meza_list_donation_options_description'
                            ),
                            [
                                'key' => 'field_6913cf1aa9746',
                                'label' => 'Options',
                                'name' => 'options',
                                'aria-label' => '',
                                'type' => 'repeater',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'layout' => 'table',
                                'pagination' => 0,
                                'min' => 2,
                                'max' => 0,
                                'collapsed' => 'field_6913cf3ea9747',
                                'button_label' => 'Add Option',
                                'rows_per_page' => 20,
                                'sub_fields' => [
                                    [
                                        'key' => 'field_6913cf3ea9747',
                                        'label' => 'Name',
                                        'name' => 'name',
                                        'aria-label' => '',
                                        'type' => 'text',
                                        'instructions' => '',
                                        'required' => 1,
                                        'conditional_logic' => 0,
                                        'wrapper' => [
                                            'width' => '',
                                            'class' => '',
                                            'id' => '',
                                        ],
                                        'default_value' => '',
                                        'maxlength' => '',
                                        'allow_in_bindings' => 0,
                                        'placeholder' => '',
                                        'prepend' => '',
                                        'append' => '',
                                        'parent_repeater' => 'field_6913cf1aa9746',
                                    ],
                                    [
                                        'key' => 'field_6913cf4aa9748',
                                        'label' => 'Summary',
                                        'name' => 'summary',
                                        'aria-label' => '',
                                        'type' => 'textarea',
                                        'instructions' => '',
                                        'required' => 1,
                                        'conditional_logic' => 0,
                                        'wrapper' => [
                                            'width' => '',
                                            'class' => '',
                                            'id' => '',
                                        ],
                                        'default_value' => '',
                                        'maxlength' => '',
                                        'allow_in_bindings' => 0,
                                        'rows' => 2,
                                        'placeholder' => '',
                                        'new_lines' => '',
                                        'parent_repeater' => 'field_6913cf1aa9746',
                                    ],
                                    [
                                        'key' => 'field_6913cf5ea9749',
                                        'label' => 'Link',
                                        'name' => 'link',
                                        'aria-label' => '',
                                        'type' => 'link',
                                        'instructions' => '',
                                        'required' => 0,
                                        'conditional_logic' => 0,
                                        'wrapper' => [
                                            'width' => '',
                                            'class' => '',
                                            'id' => '',
                                        ],
                                        'return_format' => 'array',
                                        'allow_in_bindings' => 0,
                                        'parent_repeater' => 'field_6913cf1aa9746',
                                    ],
                                ],
                            ],
                            meza_get_standard_list_section_text_field(
                                'field_6913cee53dd6b',
                                'ID',
                                'id'
                            ),
                        ]
                    ),
                ],
                'location' => meza_get_acf_location_rules_hidden_by_default(),
                'menu_order' => 12,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'top',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
                'allow_ai_access' => false,
                'ai_description' => '',
            ];

            $definitions[] = meza_get_standard_list_section_field_group_definition([
                'group_key' => 'group_68f903208a8e1',
                'title' => 'List Donors and Sponsors Section',
                'visibility_key' => 'field_68f903209176e',
                'visibility_name' => 'show_list-donors-sponsors',
                'visibility_allow_in_bindings' => 1,
                'section_key' => 'field_68f90320917b5',
                'section_name' => 'section_list-donors-sponsors',
                'headline_key' => 'field_68f903209bad7',
                'include_display' => true,
                'display_key' => 'field_meza_list_donors_sponsors_display',
                'subhead_key' => 'field_68f903209bb19',
                'include_description' => true,
                'description_key' => 'field_meza_list_donors_sponsors_description',
                'link_key' => 'field_68f903209bb57',
                'id_key' => 'field_68f903209bb99',
                'location' => meza_get_acf_location_rules_hidden_by_default(),
                'menu_order' => 20,
            ]);
        }

        if (meza_supports_sponsor_features()) {
            $definitions[] = meza_get_list_sponsors_section_field_group_definition();
            $definitions[] = meza_get_standard_list_section_field_group_definition([
                'group_key' => 'group_meza_list_sponsor_levels_section',
                'title' => 'List Sponsor Levels Section',
                'visibility_key' => 'field_meza_show_list_sponsor_levels',
                'visibility_name' => 'show_list-sponsor-levels',
                'section_key' => 'field_meza_section_list_sponsor_levels',
                'section_name' => 'section_list-sponsor-levels',
                'headline_key' => 'field_meza_list_sponsor_levels_headline',
                'include_display' => true,
                'display_key' => 'field_meza_list_sponsor_levels_display',
                'subhead_key' => 'field_meza_list_sponsor_levels_subhead',
                'include_description' => true,
                'description_key' => 'field_meza_list_sponsor_levels_description',
                'link_key' => 'field_meza_list_sponsor_levels_link',
                'id_key' => 'field_meza_list_sponsor_levels_id',
                'location' => meza_get_acf_location_rules_hidden_by_default(),
                'menu_order' => 11,
            ]);
        }

        $definitions[] = meza_get_contact_locations_section_field_group_definition();

        if (!(function_exists('meza_are_localities_enabled') && meza_are_localities_enabled())) {
            $definitions = array_values(array_filter($definitions, static function ($definition): bool {
                return !is_array($definition)
                    || (string) ($definition['key'] ?? '') !== 'group_9f5b5c9a';
            }));
        }

        if (meza_business_information_enables_ecommerce()) {
            $definitions = array_merge($definitions, meza_get_ecommerce_default_editable_acf_field_group_definitions());
        }

        $definitions = meza_apply_default_editable_acf_field_group_location_rules($definitions);

        foreach ($definitions as $index => $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $definitions[$index] = meza_normalize_header_section_group($definition);
        }

            return $definitions;
        }
    }

    function meza_get_default_editable_acf_field_group_definitions(): array
    {
        return meza_apply_shared_project_default_acf_field_group_definition_filters(
            meza_get_base_default_editable_acf_field_group_definitions()
        );
    }
}

if (!function_exists('meza_get_default_editable_acf_taxonomy_definitions')) {
    function meza_get_default_editable_acf_taxonomy_definitions(): array
    {
        return [];
    }
}

if (!function_exists('meza_get_existing_editable_acf_field_group_id')) {
    function meza_get_acf_field_group_candidate_status(array $field_group): string
    {
        $status = (string) ($field_group['post_status'] ?? '');
        if ($status !== '') {
            return $status;
        }

        $field_group_id = (int) ($field_group['ID'] ?? 0);
        if ($field_group_id > 0 && function_exists('get_post_status')) {
            $status = (string) get_post_status($field_group_id);
            if ($status !== '') {
                return $status;
            }
        }

        if (array_key_exists('active', $field_group) && !$field_group['active']) {
            return 'acf-disabled';
        }

        return 'publish';
    }

    function meza_get_acf_field_group_candidate_status_rank(array $field_group): int
    {
        $status = meza_get_acf_field_group_candidate_status($field_group);

        if ($status === 'publish') {
            return 0;
        }

        if ($status === 'acf-disabled') {
            return 1;
        }

        if ($status === 'trash') {
            return 2;
        }

        return 3;
    }

    function meza_sort_acf_field_group_candidates(array $field_groups): array
    {
        $field_groups = array_values(array_filter($field_groups, 'is_array'));

        usort($field_groups, static function (array $a, array $b): int {
            $status_rank = meza_get_acf_field_group_candidate_status_rank($a)
                <=> meza_get_acf_field_group_candidate_status_rank($b);
            if ($status_rank !== 0) {
                return $status_rank;
            }

            $menu_order = (int) ($a['menu_order'] ?? 0) <=> (int) ($b['menu_order'] ?? 0);
            if ($menu_order !== 0) {
                return $menu_order;
            }

            $title = strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            if ($title !== 0) {
                return $title;
            }

            return (int) ($a['ID'] ?? 0) <=> (int) ($b['ID'] ?? 0);
        });

        return $field_groups;
    }

    function meza_get_matching_acf_field_group_candidates(array $definition, array $field_groups): array
    {
        $target_key = (string) ($definition['key'] ?? '');
        $target_title = trim((string) ($definition['title'] ?? ''));

        $key_matches = [];
        foreach ($field_groups as $field_group) {
            if (!is_array($field_group)) {
                continue;
            }

            if ($target_key !== '' && (string) ($field_group['key'] ?? '') === $target_key) {
                $key_matches[] = $field_group;
            }
        }

        if ($key_matches !== []) {
            return meza_sort_acf_field_group_candidates($key_matches);
        }

        $title_matches = [];
        foreach ($field_groups as $field_group) {
            if (!is_array($field_group)) {
                continue;
            }

            if ($target_title !== '' && trim((string) ($field_group['title'] ?? '')) === $target_title) {
                $title_matches[] = $field_group;
            }
        }

        return meza_sort_acf_field_group_candidates($title_matches);
    }

    function meza_get_canonical_acf_field_group_candidate(array $definition, array $field_groups): array
    {
        $matches = meza_get_matching_acf_field_group_candidates($definition, $field_groups);

        return $matches[0] ?? [];
    }

    function meza_get_existing_editable_acf_field_group_id(array $definition): int
    {
        if (function_exists('acf_get_field_groups')) {
            $filters = function_exists('acf_disable_filters') ? acf_disable_filters() : null;
            $field_group = meza_get_canonical_acf_field_group_candidate($definition, (array) acf_get_field_groups());
            if (function_exists('acf_enable_filters')) {
                acf_enable_filters($filters ?? []);
            }

            $field_group_id = (int) ($field_group['ID'] ?? 0);
            if ($field_group_id > 0) {
                return $field_group_id;
            }
        }

        if (!function_exists('acf_get_raw_field_groups')) {
            return 0;
        }

        $field_group = meza_get_canonical_acf_field_group_candidate($definition, (array) acf_get_raw_field_groups());

        return (int) ($field_group['ID'] ?? 0);
    }
}

if (!function_exists('meza_delete_editable_acf_field_group_definition')) {
    function meza_get_conditional_editable_acf_field_group_target_definitions(): array
    {
        $definitions = [
            meza_get_list_events_section_field_group_definition(),
            [
                'key' => 'group_9f5b5c9a',
                'title' => 'List Localities Section',
            ],
            meza_get_list_sponsors_section_field_group_definition(),
            [
                'key' => 'group_meza_list_sponsor_levels_section',
                'title' => 'List Sponsor Levels Section',
            ],
            [
                'key' => 'group_6913c78d0b35c',
                'title' => 'List Donor Levels Section',
            ],
            [
                'key' => 'group_6913c9b535e72',
                'title' => 'List Certifications Section',
            ],
            [
                'key' => 'group_6913cee5325cb',
                'title' => 'List Donation Options Section',
            ],
            [
                'key' => 'group_68f903208a8e1',
                'title' => 'List Donors and Sponsors Section',
            ],
        ];

        foreach (meza_get_ecommerce_editable_acf_field_group_definitions() as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $definitions[] = $definition;
        }

        return array_values(array_filter($definitions, static function ($definition): bool {
            return is_array($definition)
                && !empty($definition['key'])
                && !empty($definition['title']);
        }));
    }

    function meza_get_conditional_editable_acf_field_group_target_identifier_map(): array
    {
        $map = [];

        foreach (meza_get_conditional_editable_acf_field_group_target_definitions() as $definition) {
            $identifier = meza_get_managed_acf_definition_identifier('field_groups', $definition);
            if ($identifier === '') {
                continue;
            }

            $map[$identifier] = $definition;
        }

        return $map;
    }

    function meza_get_auto_disabled_editable_acf_field_groups_option_name(): string
    {
        return 'meza_auto_disabled_editable_acf_field_groups_v1';
    }

    function meza_get_auto_disabled_editable_acf_field_group_identifiers(): array
    {
        $identifiers = get_option(meza_get_auto_disabled_editable_acf_field_groups_option_name(), []);
        if (!is_array($identifiers)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static function ($identifier): string {
                return sanitize_key((string) $identifier);
            },
            $identifiers
        ))));
    }

    function meza_update_auto_disabled_editable_acf_field_group_identifiers(array $identifiers): void
    {
        $identifiers = array_values(array_unique(array_filter(array_map(
            static function ($identifier): string {
                return sanitize_key((string) $identifier);
            },
            $identifiers
        ))));

        update_option(meza_get_auto_disabled_editable_acf_field_groups_option_name(), $identifiers, false);
    }

    function meza_mark_editable_acf_field_group_auto_disabled(array $definition): void
    {
        $identifier = meza_get_managed_acf_definition_identifier('field_groups', $definition);
        if ($identifier === '') {
            return;
        }

        $identifiers = meza_get_auto_disabled_editable_acf_field_group_identifiers();
        if (in_array($identifier, $identifiers, true)) {
            return;
        }

        $identifiers[] = $identifier;
        meza_update_auto_disabled_editable_acf_field_group_identifiers($identifiers);
    }

    function meza_clear_editable_acf_field_group_auto_disabled(array $definition): void
    {
        $identifier = meza_get_managed_acf_definition_identifier('field_groups', $definition);
        if ($identifier === '') {
            return;
        }

        $identifiers = array_values(array_filter(
            meza_get_auto_disabled_editable_acf_field_group_identifiers(),
            static function (string $saved_identifier) use ($identifier): bool {
                return $saved_identifier !== $identifier;
            }
        ));

        meza_update_auto_disabled_editable_acf_field_group_identifiers($identifiers);
    }

    function meza_is_editable_acf_field_group_auto_disabled(array $definition): bool
    {
        $identifier = meza_get_managed_acf_definition_identifier('field_groups', $definition);
        if ($identifier === '') {
            return false;
        }

        return in_array($identifier, meza_get_auto_disabled_editable_acf_field_group_identifiers(), true);
    }

    function meza_get_auto_trashed_editable_acf_field_groups_option_name(): string
    {
        return 'meza_auto_trashed_editable_acf_field_groups_v1';
    }

    function meza_get_auto_trashed_editable_acf_field_group_identifiers(): array
    {
        $identifiers = get_option(meza_get_auto_trashed_editable_acf_field_groups_option_name(), []);
        if (!is_array($identifiers)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static function ($identifier): string {
                return sanitize_key((string) $identifier);
            },
            $identifiers
        ))));
    }

    function meza_mark_editable_acf_field_group_auto_trashed(array $definition): void
    {
        meza_mark_editable_acf_field_group_auto_disabled($definition);
    }

    function meza_clear_editable_acf_field_group_auto_trashed(array $definition): void
    {
        meza_clear_editable_acf_field_group_auto_disabled($definition);
    }

    function meza_is_editable_acf_field_group_auto_trashed(array $definition): bool
    {
        return meza_is_editable_acf_field_group_auto_disabled($definition);
    }

    function meza_get_raw_editable_acf_field_group(array $definition): array
    {
        if (function_exists('acf_get_raw_field_groups')) {
            $field_group = meza_get_canonical_acf_field_group_candidate($definition, (array) acf_get_raw_field_groups());
            if ($field_group !== []) {
                return $field_group;
            }
        }

        $target_title = trim((string) ($definition['title'] ?? ''));
        if ($target_title === '') {
            return [];
        }

        $posts = get_posts([
            'posts_per_page' => -1,
            'post_type' => 'acf-field-group',
            'post_status' => ['publish', 'acf-disabled', 'trash'],
            'orderby' => 'menu_order title',
            'order' => 'ASC',
            'suppress_filters' => false,
            'cache_results' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        $field_groups = [];
        foreach ($posts as $post) {
            if (!($post instanceof WP_Post) || trim((string) $post->post_title) !== $target_title) {
                continue;
            }

            if (!function_exists('acf_get_raw_internal_post_type')) {
                return [];
            }

            $field_group = acf_get_raw_internal_post_type((int) $post->ID, 'acf-field-group');
            if (is_array($field_group)) {
                $field_group['post_status'] = (string) $post->post_status;
                $field_groups[] = $field_group;
            }
        }

        return meza_get_canonical_acf_field_group_candidate($definition, $field_groups);
    }

    function meza_get_trashed_editable_acf_field_group_id(array $definition): int
    {
        $field_group = meza_get_raw_editable_acf_field_group($definition);
        $field_group_id = (int) ($field_group['ID'] ?? 0);
        if ($field_group_id <= 0) {
            return 0;
        }

        return get_post_status($field_group_id) === 'trash' ? $field_group_id : 0;
    }

    function meza_get_disabled_editable_acf_field_group_id(array $definition): int
    {
        $field_group = meza_get_raw_editable_acf_field_group($definition);
        $field_group_id = (int) ($field_group['ID'] ?? 0);
        if ($field_group_id <= 0) {
            return 0;
        }

        return get_post_status($field_group_id) === 'acf-disabled' ? $field_group_id : 0;
    }

    function meza_disable_editable_acf_field_group_definition(array $definition): bool
    {
        if (!function_exists('acf_update_internal_post_type_active_status')) {
            return false;
        }

        $field_group_id = meza_get_raw_editable_acf_field_group_id($definition);
        if ($field_group_id <= 0) {
            return false;
        }

        $did_disable = (bool) meza_with_managed_acf_definition_tracking_suppressed(static function () use ($field_group_id) {
            return acf_update_internal_post_type_active_status($field_group_id, false, 'acf-field-group');
        });

        if ($did_disable) {
            meza_mark_editable_acf_field_group_auto_disabled($definition);
        }

        return $did_disable;
    }

    function meza_enable_editable_acf_field_group_definition(array $definition): bool
    {
        if (!function_exists('acf_update_internal_post_type_active_status')) {
            return false;
        }

        $field_group_id = meza_get_raw_editable_acf_field_group_id($definition);
        if ($field_group_id <= 0) {
            return false;
        }

        $did_enable = (bool) acf_update_internal_post_type_active_status($field_group_id, true, 'acf-field-group');
        if ($did_enable) {
            meza_clear_editable_acf_field_group_auto_disabled($definition);
        }

        return $did_enable;
    }

    function meza_migrate_auto_trashed_editable_acf_field_groups_to_disabled(): void
    {
        static $did_run = false;

        if ($did_run) {
            return;
        }
        $did_run = true;

        $trashed_identifiers = meza_get_auto_trashed_editable_acf_field_group_identifiers();
        if ($trashed_identifiers === []) {
            return;
        }

        foreach (meza_get_default_editable_acf_field_group_definitions() as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $identifier = meza_get_managed_acf_definition_identifier('field_groups', $definition);
            if ($identifier === '' || !in_array($identifier, $trashed_identifiers, true)) {
                continue;
            }

            $trashed_id = meza_get_trashed_editable_acf_field_group_id($definition);
            if ($trashed_id > 0 && function_exists('acf_untrash_field_group')) {
                acf_untrash_field_group($trashed_id);
            }

            meza_disable_editable_acf_field_group_definition($definition);
        }

        delete_option(meza_get_auto_trashed_editable_acf_field_groups_option_name());
    }

    function meza_trash_editable_acf_field_group_definition(array $definition): bool
    {
        if (!function_exists('acf_trash_field_group')) {
            return false;
        }

        $existing_id = meza_get_existing_editable_acf_field_group_id($definition);
        if ($existing_id <= 0) {
            return false;
        }

        $did_trash = (bool) meza_with_managed_acf_definition_tracking_suppressed(static function () use ($existing_id) {
            return acf_trash_field_group($existing_id);
        });

        if ($did_trash) {
            meza_mark_editable_acf_field_group_auto_trashed($definition);
        }

        return $did_trash;
    }

    function meza_restore_editable_acf_field_group_definition(array $definition): bool
    {
        if (!function_exists('acf_untrash_field_group')) {
            return false;
        }

        $trashed_id = meza_get_trashed_editable_acf_field_group_id($definition);
        if ($trashed_id <= 0) {
            return false;
        }

        $did_restore = (bool) acf_untrash_field_group($trashed_id);
        if ($did_restore) {
            meza_clear_editable_acf_field_group_auto_trashed($definition);
        }

        return $did_restore;
    }

    function meza_reconcile_conditional_editable_acf_field_groups(): void
    {
        $target_map = meza_get_conditional_editable_acf_field_group_target_identifier_map();
        if ($target_map === []) {
            return;
        }

        $desired_map = [];

        foreach (meza_get_default_editable_acf_field_group_definitions() as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $identifier = meza_get_managed_acf_definition_identifier('field_groups', $definition);
            if ($identifier === '' || !isset($target_map[$identifier])) {
                continue;
            }

            $desired_map[$identifier] = $definition;
        }

        foreach ($target_map as $identifier => $definition) {
            if (isset($desired_map[$identifier])) {
                continue;
            }

            if (meza_get_existing_editable_acf_field_group_id($definition) > 0) {
                meza_disable_editable_acf_field_group_definition($definition);
                continue;
            }

            if (meza_get_disabled_editable_acf_field_group_id($definition) <= 0) {
                meza_clear_editable_acf_field_group_auto_disabled($definition);
            }
        }
    }

    function meza_delete_editable_acf_field_group_definition(array $definition): void
    {
        if (!function_exists('acf_delete_field_group')) {
            return;
        }

        $existing_id = meza_get_existing_editable_acf_field_group_id($definition);
        if ($existing_id <= 0) {
            return;
        }

        acf_delete_field_group($existing_id);
    }
}

if (!function_exists('meza_get_acf_field_group_order_migration_version')) {
    function meza_get_acf_field_group_order_migration_version(): string
    {
        return '2026-06-02-orders-and-show-prefix-v3';
    }

    function meza_get_acf_field_group_order_migration_option_name(): string
    {
        return 'meza_acf_field_group_order_migration_version';
    }

    function meza_get_matching_raw_editable_acf_field_groups(array $definition): array
    {
        if (!function_exists('acf_get_raw_field_groups')) {
            return [];
        }

        return meza_get_matching_acf_field_group_candidates($definition, (array) acf_get_raw_field_groups());
    }

    function meza_raw_acf_field_group_has_fields(array $field_group): bool
    {
        if (!function_exists('acf_get_raw_fields')) {
            return false;
        }

        $field_group_id = (int) ($field_group['ID'] ?? 0);
        if ($field_group_id <= 0) {
            return false;
        }

        return array_values(array_filter((array) acf_get_raw_fields($field_group_id), 'is_array')) !== [];
    }

    function meza_has_active_raw_editable_acf_field_group_with_fields(array $definition): bool
    {
        foreach (meza_get_matching_raw_editable_acf_field_groups($definition) as $field_group) {
            $field_group_id = (int) ($field_group['ID'] ?? 0);
            if ($field_group_id <= 0 || get_post_status($field_group_id) !== 'publish') {
                continue;
            }

            if (meza_raw_acf_field_group_has_fields($field_group)) {
                return true;
            }
        }

        return false;
    }

    function meza_get_raw_editable_acf_field_group_id(array $definition): int
    {
        foreach (meza_get_matching_raw_editable_acf_field_groups($definition) as $field_group) {
            $field_group_id = (int) ($field_group['ID'] ?? 0);
            if ($field_group_id > 0) {
                return $field_group_id;
            }
        }

        return 0;
    }

    function meza_migrate_acf_option_name(string $old_name, string $new_name): void
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_id, option_name FROM {$wpdb->options} WHERE option_name IN (%s, %s)",
                $old_name,
                '_' . $old_name
            ),
            ARRAY_A
        );

        foreach ($rows as $row) {
            $current_name = (string) ($row['option_name'] ?? '');
            $option_id = (int) ($row['option_id'] ?? 0);
            if ($option_id <= 0 || $current_name === '') {
                continue;
            }

            $target_name = $current_name === '_' . $old_name ? '_' . $new_name : $new_name;
            $target_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                    $target_name
                )
            );

            if ($target_exists) {
                continue;
            }

            $wpdb->update(
                $wpdb->options,
                ['option_name' => $target_name],
                ['option_id' => $option_id],
                ['%s'],
                ['%d']
            );
        }
    }

    function meza_migrate_acf_meta_name_in_table(string $table, string $id_column, string $object_column, string $old_name, string $new_name): void
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$id_column} AS row_id, {$object_column} AS object_id, meta_key FROM {$table} WHERE meta_key IN (%s, %s)",
                $old_name,
                '_' . $old_name
            ),
            ARRAY_A
        );

        foreach ($rows as $row) {
            $row_id = (int) ($row['row_id'] ?? 0);
            $object_id = (int) ($row['object_id'] ?? 0);
            $current_key = (string) ($row['meta_key'] ?? '');

            if ($row_id <= 0 || $object_id <= 0 || $current_key === '') {
                continue;
            }

            $target_key = $current_key === '_' . $old_name ? '_' . $new_name : $new_name;
            $target_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT {$id_column} FROM {$table} WHERE {$object_column} = %d AND meta_key = %s LIMIT 1",
                    $object_id,
                    $target_key
                )
            );

            if ($target_exists) {
                continue;
            }

            $wpdb->update(
                $table,
                ['meta_key' => $target_key],
                [$id_column => $row_id],
                ['%s'],
                ['%d']
            );
        }
    }

    function meza_acf_migration_value_has_content($value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '' || $value === 'a:0:{}' || $value === 'N;') {
                return false;
            }
        }

        return true;
    }

    function meza_normalize_certification_url_meta_value($value): string
    {
        if (is_string($value)) {
            $value = maybe_unserialize($value);
        }

        if (is_array($value)) {
            $value = $value['url'] ?? '';
        }

        if (!is_scalar($value)) {
            return '';
        }

        return esc_url_raw(trim((string) $value));
    }

    function meza_migrate_acf_option_name_with_blank_target_merge(string $old_name, string $new_name): void
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_id, option_name, option_value FROM {$wpdb->options} WHERE option_name IN (%s, %s)",
                $old_name,
                '_' . $old_name
            ),
            ARRAY_A
        );

        foreach ($rows as $row) {
            $current_name = (string) ($row['option_name'] ?? '');
            $option_id = (int) ($row['option_id'] ?? 0);
            $source_value = $row['option_value'] ?? null;

            if ($option_id <= 0 || $current_name === '') {
                continue;
            }

            $target_name = $current_name === '_' . $old_name ? '_' . $new_name : $new_name;
            $target_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT option_id, option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                    $target_name
                ),
                ARRAY_A
            );

            if (!is_array($target_row)) {
                $wpdb->update(
                    $wpdb->options,
                    ['option_name' => $target_name],
                    ['option_id' => $option_id],
                    ['%s'],
                    ['%d']
                );
                continue;
            }

            if (
                strpos($current_name, '_') !== 0
                && !meza_acf_migration_value_has_content($target_row['option_value'] ?? null)
                && meza_acf_migration_value_has_content($source_value)
            ) {
                $wpdb->update(
                    $wpdb->options,
                    ['option_value' => $source_value],
                    ['option_id' => (int) $target_row['option_id']],
                    ['%s'],
                    ['%d']
                );
            }
        }
    }

    function meza_migrate_acf_meta_name_in_table_with_blank_target_merge(string $table, string $id_column, string $object_column, string $old_name, string $new_name): void
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$id_column} AS row_id, {$object_column} AS object_id, meta_key, meta_value FROM {$table} WHERE meta_key IN (%s, %s)",
                $old_name,
                '_' . $old_name
            ),
            ARRAY_A
        );

        foreach ($rows as $row) {
            $row_id = (int) ($row['row_id'] ?? 0);
            $object_id = (int) ($row['object_id'] ?? 0);
            $current_key = (string) ($row['meta_key'] ?? '');
            $source_value = $row['meta_value'] ?? null;

            if ($row_id <= 0 || $object_id <= 0 || $current_key === '') {
                continue;
            }

            $target_key = $current_key === '_' . $old_name ? '_' . $new_name : $new_name;
            $target_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT {$id_column} AS row_id, meta_value FROM {$table} WHERE {$object_column} = %d AND meta_key = %s LIMIT 1",
                    $object_id,
                    $target_key
                ),
                ARRAY_A
            );

            if (!is_array($target_row)) {
                $wpdb->update(
                    $table,
                    ['meta_key' => $target_key],
                    [$id_column => $row_id],
                    ['%s'],
                    ['%d']
                );
                continue;
            }

            if (
                strpos($current_key, '_') !== 0
                && !meza_acf_migration_value_has_content($target_row['meta_value'] ?? null)
                && meza_acf_migration_value_has_content($source_value)
            ) {
                $wpdb->update(
                    $table,
                    ['meta_value' => $source_value],
                    [$id_column => (int) $target_row['row_id']],
                    ['%s'],
                    ['%d']
                );
            }
        }
    }

    function meza_get_list_reviews_legacy_meta_name_map(): array
    {
        return [
            'visibility_list-testimonials' => 'show_list-reviews',
            'show_list-testimonials' => 'show_list-reviews',
            'section_list-testimonials' => 'section_list-reviews',
            'section_list-testimonials_headline' => 'section_list-reviews_headline',
            'section_list-testimonials_display' => 'section_list-reviews_display',
            'section_list-testimonials_subhead' => 'section_list-reviews_subhead',
            'section_list-testimonials_description' => 'section_list-reviews_description',
            'section_list-testimonials_link' => 'section_list-reviews_link',
            'section_list-testimonials_id' => 'section_list-reviews_id',
            'section_list-testimonials_action_text' => 'section_list-reviews_action_text',
        ];
    }

    function meza_migrate_legacy_list_testimonials_meta_once(): void
    {
        $version = '2026-05-04-list-testimonials-meta-v1';
        $option_name = 'meza_list_testimonials_meta_migration_version';

        if ((string) get_option($option_name, '') === $version) {
            return;
        }

        foreach (meza_get_list_reviews_legacy_meta_name_map() as $old_name => $new_name) {
            meza_migrate_acf_option_name_with_blank_target_merge($old_name, $new_name);
            meza_migrate_acf_meta_name_in_table_with_blank_target_merge($GLOBALS['wpdb']->postmeta, 'meta_id', 'post_id', $old_name, $new_name);
            meza_migrate_acf_meta_name_in_table_with_blank_target_merge($GLOBALS['wpdb']->termmeta, 'meta_id', 'term_id', $old_name, $new_name);
            meza_migrate_acf_meta_name_in_table_with_blank_target_merge($GLOBALS['wpdb']->usermeta, 'umeta_id', 'user_id', $old_name, $new_name);
            meza_migrate_acf_meta_name_in_table_with_blank_target_merge($GLOBALS['wpdb']->commentmeta, 'meta_id', 'comment_id', $old_name, $new_name);
        }

        update_option($option_name, $version, false);
    }

    function meza_migrate_certification_url_meta_once(): void
    {
        $version = '2026-05-17-certification-url-v1';
        $option_name = 'meza_certification_url_meta_migration_version';

        if ((string) get_option($option_name, '') === $version) {
            return;
        }

        $certification_ids = get_posts([
            'post_type' => 'certification',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'suppress_filters' => true,
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        foreach ((array) $certification_ids as $certification_id) {
            $certification_id = (int) $certification_id;
            if ($certification_id <= 0) {
                continue;
            }

            $raw_url = get_post_meta($certification_id, 'url', true);
            $raw_link = get_post_meta($certification_id, 'link', true);

            $normalized_url = meza_normalize_certification_url_meta_value($raw_url);
            $normalized_link = meza_normalize_certification_url_meta_value($raw_link);
            $final_url = $normalized_url !== '' ? $normalized_url : $normalized_link;

            if ($final_url !== '') {
                update_post_meta($certification_id, 'url', $final_url);
                update_post_meta($certification_id, '_url', 'field_6913c90ee4d75');
            } elseif ($raw_url !== '' && $raw_url !== null) {
                delete_post_meta($certification_id, 'url');
            }

            if ($raw_link !== '' && $raw_link !== null) {
                delete_post_meta($certification_id, 'link');
            }

            $link_reference = get_post_meta($certification_id, '_link', true);
            if ($link_reference !== '' && $link_reference !== null) {
                delete_post_meta($certification_id, '_link');
            }
        }

        update_option($option_name, $version, false);
    }

    function meza_update_acf_reference_values_in_meta_table(string $table, string $id_column, array $reference_map): void
    {
        global $wpdb;

        foreach ($reference_map as $meta_key => $expected_value) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT {$id_column} AS row_id, meta_value FROM {$table} WHERE meta_key = %s",
                    $meta_key
                ),
                ARRAY_A
            );

            foreach ($rows as $row) {
                $row_id = (int) ($row['row_id'] ?? 0);
                $current_value = (string) ($row['meta_value'] ?? '');

                if ($row_id <= 0 || $current_value === $expected_value) {
                    continue;
                }

                $wpdb->update(
                    $table,
                    ['meta_value' => $expected_value],
                    [$id_column => $row_id],
                    ['%s'],
                    ['%d']
                );
            }
        }
    }

    function meza_update_acf_reference_values_in_options(array $reference_map): void
    {
        global $wpdb;

        foreach ($reference_map as $option_name => $expected_value) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT option_id, option_value FROM {$wpdb->options} WHERE option_name = %s",
                    $option_name
                ),
                ARRAY_A
            );

            foreach ($rows as $row) {
                $option_id = (int) ($row['option_id'] ?? 0);
                $current_value = (string) ($row['option_value'] ?? '');

                if ($option_id <= 0 || $current_value === $expected_value) {
                    continue;
                }

                $wpdb->update(
                    $wpdb->options,
                    ['option_value' => $expected_value],
                    ['option_id' => $option_id],
                    ['%s'],
                    ['%d']
                );
            }
        }
    }

    function meza_get_list_reviews_reference_value_map(): array
    {
        return [
            '_show_list-reviews' => 'field_meza_show_list_reviews',
            '_section_list-reviews' => 'field_meza_section_list_reviews',
            '_section_list-reviews_headline' => 'field_meza_list_reviews_headline',
            '_section_list-reviews_display' => meza_get_section_sub_field_stable_key('section_list-reviews', 'display'),
            '_section_list-reviews_subhead' => 'field_meza_list_reviews_subhead',
            '_section_list-reviews_description' => meza_get_section_sub_field_stable_key('section_list-reviews', 'description'),
            '_section_list-reviews_link' => 'field_meza_list_reviews_link',
            '_section_list-reviews_id' => 'field_meza_list_reviews_id',
        ];
    }

    function meza_sync_list_reviews_reference_values_once(): void
    {
        $version = '2026-05-04-list-reviews-reference-values-v1';
        $option_name = 'meza_list_reviews_reference_values_sync_version';

        if ((string) get_option($option_name, '') === $version) {
            return;
        }

        $reference_map = meza_get_list_reviews_reference_value_map();
        meza_update_acf_reference_values_in_options($reference_map);
        meza_update_acf_reference_values_in_meta_table($GLOBALS['wpdb']->postmeta, 'meta_id', $reference_map);
        meza_update_acf_reference_values_in_meta_table($GLOBALS['wpdb']->termmeta, 'meta_id', $reference_map);
        meza_update_acf_reference_values_in_meta_table($GLOBALS['wpdb']->usermeta, 'umeta_id', $reference_map);
        meza_update_acf_reference_values_in_meta_table($GLOBALS['wpdb']->commentmeta, 'meta_id', $reference_map);

        update_option($option_name, $version, false);
    }

    function meza_migrate_visibility_field_to_show(array $field): void
    {
        if (!function_exists('acf_update_field')) {
            return;
        }

        $old_name = (string) ($field['name'] ?? '');
        if ($old_name === '' || strpos($old_name, 'visibility_') !== 0) {
            return;
        }

        $new_name = 'show_' . substr($old_name, strlen('visibility_'));
        if ($new_name === $old_name) {
            return;
        }

        $updated_field = $field;
        $updated_field['name'] = $new_name;
        acf_update_field($updated_field);

        meza_migrate_acf_option_name($old_name, $new_name);
        meza_migrate_acf_meta_name_in_table($GLOBALS['wpdb']->postmeta, 'meta_id', 'post_id', $old_name, $new_name);
        meza_migrate_acf_meta_name_in_table($GLOBALS['wpdb']->termmeta, 'meta_id', 'term_id', $old_name, $new_name);
        meza_migrate_acf_meta_name_in_table($GLOBALS['wpdb']->usermeta, 'umeta_id', 'user_id', $old_name, $new_name);
        meza_migrate_acf_meta_name_in_table($GLOBALS['wpdb']->commentmeta, 'meta_id', 'comment_id', $old_name, $new_name);
    }

    function meza_clear_show_field_message(array $field): void
    {
        if (!function_exists('acf_update_field')) {
            return;
        }

        $field_type = (string) ($field['type'] ?? '');
        $field_name = (string) ($field['name'] ?? '');
        if ($field_type !== 'true_false' || !str_starts_with($field_name, 'show_')) {
            return;
        }

        if ((string) ($field['message'] ?? '') === '') {
            return;
        }

        $updated_field = $field;
        $updated_field['message'] = '';
        acf_update_field($updated_field);
    }

    function meza_sync_default_editable_acf_field_group_order_and_names(): void
    {
        if (!meza_should_seed_default_acf_field_groups()) {
            return;
        }

        $version = meza_get_acf_field_group_order_migration_version();
        if ((string) get_option(meza_get_acf_field_group_order_migration_option_name(), '') === $version) {
            return;
        }

        $definitions = meza_get_default_editable_acf_field_group_definitions();
        if (meza_business_information_enables_ecommerce()) {
            $definitions = array_merge($definitions, meza_get_ecommerce_default_editable_acf_field_group_definitions());
        }

        foreach ($definitions as $definition) {
            if (!is_array($definition) || empty($definition['title'])) {
                continue;
            }

            $field_group_id = meza_get_raw_editable_acf_field_group_id($definition);
            if ($field_group_id <= 0) {
                continue;
            }

            wp_update_post([
                'ID' => $field_group_id,
                'menu_order' => (int) ($definition['menu_order'] ?? 0),
                'post_title' => (string) ($definition['title'] ?? ''),
            ]);

            foreach ((array) acf_get_fields($field_group_id) as $field) {
                if (!is_array($field)) {
                    continue;
                }

                meza_migrate_visibility_field_to_show($field);
                meza_clear_show_field_message($field);
            }
        }

        if (function_exists('acf_get_raw_field_groups')) {
            foreach ((array) acf_get_raw_field_groups() as $field_group) {
                $field_group_id = (int) ($field_group['ID'] ?? 0);
                if ($field_group_id <= 0) {
                    continue;
                }

                foreach ((array) acf_get_fields($field_group_id) as $field) {
                    if (!is_array($field)) {
                        continue;
                    }

                    meza_migrate_visibility_field_to_show($field);
                    meza_clear_show_field_message($field);
                }
            }
        }

        update_option(meza_get_acf_field_group_order_migration_option_name(), $version, false);
    }
}

meza_add_managed_acf_automatic_mutation_action('acf/init', static function (): void {
    if (function_exists('meza_migrate_auto_trashed_editable_acf_field_groups_to_disabled')) {
        meza_migrate_auto_trashed_editable_acf_field_groups_to_disabled();
    }
}, 18);

if (!function_exists('meza_get_existing_editable_acf_taxonomy_id')) {
    function meza_get_existing_editable_acf_taxonomy_id(array $definition): int
    {
        if (!function_exists('acf_get_acf_taxonomies')) {
            return 0;
        }

        $filters = function_exists('acf_disable_filters') ? acf_disable_filters() : null;
        $taxonomies = (array) acf_get_acf_taxonomies();
        if (function_exists('acf_enable_filters')) {
            acf_enable_filters($filters ?? []);
        }

        $target_key = (string) ($definition['key'] ?? '');
        $target_name = sanitize_key((string) ($definition['taxonomy'] ?? ''));
        $target_title = trim((string) ($definition['title'] ?? ''));

        foreach ($taxonomies as $taxonomy) {
            if (!is_array($taxonomy)) {
                continue;
            }

            if ($target_key !== '' && (string) ($taxonomy['key'] ?? '') === $target_key) {
                return (int) ($taxonomy['ID'] ?? 0);
            }
        }

        foreach ($taxonomies as $taxonomy) {
            if (!is_array($taxonomy)) {
                continue;
            }

            if ($target_name !== '' && sanitize_key((string) ($taxonomy['taxonomy'] ?? '')) === $target_name) {
                return (int) ($taxonomy['ID'] ?? 0);
            }
        }

        foreach ($taxonomies as $taxonomy) {
            if (!is_array($taxonomy)) {
                continue;
            }

            if ($target_title !== '' && trim((string) ($taxonomy['title'] ?? '')) === $target_title) {
                return (int) ($taxonomy['ID'] ?? 0);
            }
        }

        return 0;
    }
}

if (!function_exists('meza_seed_default_editable_acf_taxonomies')) {
    function meza_seed_default_editable_acf_taxonomies(): void
    {
        if (!function_exists('acf_import_taxonomy')) {
            return;
        }

        foreach (meza_get_default_editable_acf_taxonomy_definitions() as $definition) {
            if (!is_array($definition) || empty($definition['key']) || empty($definition['taxonomy'])) {
                continue;
            }

            if (meza_is_managed_acf_definition_manually_deleted('taxonomies', $definition)) {
                continue;
            }

            $existing_id = meza_get_existing_editable_acf_taxonomy_id($definition);
            if ($existing_id > 0) {
                continue;
            }

            acf_import_taxonomy($definition);
        }

        update_option('meza_default_acf_taxonomies_initialized', 1, false);
    }
}

if (!function_exists('meza_seed_default_editable_acf_field_groups')) {
    function meza_is_ecommerce_editable_acf_field_group_definition(array $definition): bool
    {
        $target_key = (string) ($definition['key'] ?? '');
        if ($target_key === '') {
            return false;
        }

        foreach (meza_get_ecommerce_editable_acf_field_group_definitions() as $ecommerce_definition) {
            if (!is_array($ecommerce_definition)) {
                continue;
            }

            if ((string) ($ecommerce_definition['key'] ?? '') === $target_key) {
                return true;
            }
        }

        return false;
    }

    function meza_get_empty_default_editable_acf_field_group_repair_version(): string
    {
        return '2026-06-02-empty-default-editable-groups-v1';
    }

    function meza_get_empty_default_editable_acf_field_group_repair_option_name(): string
    {
        return 'meza_empty_default_editable_acf_field_group_repair_version';
    }

    function meza_repair_empty_default_editable_acf_field_groups(): void
    {
        if (
            !function_exists('acf_import_field_group')
            || !function_exists('acf_get_raw_fields')
        ) {
            return;
        }

        $version = meza_get_empty_default_editable_acf_field_group_repair_version();
        if (
            !meza_should_seed_default_acf_field_groups()
            && (string) get_option(meza_get_empty_default_editable_acf_field_group_repair_option_name(), '') === $version
        ) {
            return;
        }

        $definitions = meza_get_default_editable_acf_field_group_definitions();
        if (meza_business_information_enables_ecommerce()) {
            $definitions = array_merge($definitions, meza_get_ecommerce_default_editable_acf_field_group_definitions());
        }

        foreach ($definitions as $definition) {
            if (!is_array($definition) || empty($definition['key']) || empty($definition['title'])) {
                continue;
            }

            $is_ecommerce_definition = meza_is_ecommerce_editable_acf_field_group_definition($definition);
            if (
                meza_is_managed_acf_definition_manually_deleted('field_groups', $definition)
                && !($is_ecommerce_definition && meza_business_information_enables_ecommerce())
            ) {
                continue;
            }

            $field_group_id = meza_get_raw_editable_acf_field_group_id($definition);
            if ($field_group_id <= 0) {
                continue;
            }

            if (meza_has_active_raw_editable_acf_field_group_with_fields($definition)) {
                continue;
            }

            $raw_fields = array_values(array_filter((array) acf_get_raw_fields($field_group_id), 'is_array'));
            if ($raw_fields !== []) {
                continue;
            }

            acf_import_field_group($definition);
        }

        update_option(meza_get_empty_default_editable_acf_field_group_repair_option_name(), $version, false);
    }

    function meza_seed_default_editable_acf_field_groups(): void
    {
        if (!function_exists('acf_import_field_group')) {
            return;
        }

        foreach (meza_get_default_editable_acf_field_group_definitions() as $definition) {
            if (!is_array($definition) || empty($definition['key']) || empty($definition['title'])) {
                continue;
            }

            $is_ecommerce_definition = meza_is_ecommerce_editable_acf_field_group_definition($definition);
            if (
                meza_is_managed_acf_definition_manually_deleted('field_groups', $definition)
                && !($is_ecommerce_definition && meza_business_information_enables_ecommerce())
            ) {
                continue;
            }

            if (meza_get_raw_editable_acf_field_group_id($definition) > 0) {
                continue;
            }

            acf_import_field_group($definition);
        }

        update_option('meza_default_acf_field_groups_initialized', 1, false);
    }
}

if (!function_exists('meza_attach_locality_to_new_custom_acf_post_type')) {
    function meza_should_rerun_locality_post_type_defaults(): bool
    {
        return meza_should_seed_default_acf_field_groups();
    }

    function meza_get_locality_defaulted_acf_post_types_option_name(): string
    {
        return 'meza_locality_defaulted_acf_post_types_v1';
    }

    function meza_get_locality_defaulted_acf_post_type_ids(): array
    {
        $ids = get_option(meza_get_locality_defaulted_acf_post_types_option_name(), []);
        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    function meza_mark_locality_defaulted_acf_post_type(int $post_id): void
    {
        if ($post_id <= 0) {
            return;
        }

        $ids = meza_get_locality_defaulted_acf_post_type_ids();
        if (in_array($post_id, $ids, true)) {
            return;
        }

        $ids[] = $post_id;
        update_option(meza_get_locality_defaulted_acf_post_types_option_name(), $ids, false);
    }

    function meza_attach_locality_to_new_custom_acf_post_type(array $post_type): void
    {
        if (!function_exists('acf_update_post_type')) {
            return;
        }

        if (!(function_exists('meza_are_localities_enabled') && meza_are_localities_enabled())) {
            return;
        }

        $post_id = (int) ($post_type['ID'] ?? 0);
        $post_type_slug = sanitize_key((string) ($post_type['post_type'] ?? ''));
        if ($post_type_slug === '' || in_array($post_type_slug, meza_get_builtin_acf_post_type_slugs(), true)) {
            return;
        }

        $should_rerun_defaults = meza_should_rerun_locality_post_type_defaults();
        if (
            !$should_rerun_defaults
            && $post_id > 0
            && in_array($post_id, meza_get_locality_defaulted_acf_post_type_ids(), true)
        ) {
            return;
        }

        $selected_taxonomies = $post_type['taxonomies'] ?? [];
        if (!is_array($selected_taxonomies)) {
            $selected_taxonomies = (array) $selected_taxonomies;
        }

        $selected_taxonomies = array_values(array_unique(array_filter(array_map('sanitize_key', $selected_taxonomies))));
        if (!empty($selected_taxonomies)) {
            meza_mark_locality_defaulted_acf_post_type($post_id);
            return;
        }

        $post_type['taxonomies'] = ['locality'];
        acf_update_post_type($post_type);
        meza_mark_locality_defaulted_acf_post_type((int) ($post_type['ID'] ?? $post_id));
    }
}

add_filter('acf/load_field_groups', function (array $field_groups, string $post_type): array {
    foreach ($field_groups as &$field_group) {
        if (!is_array($field_group) || (($field_group['key'] ?? '') !== 'group_meza_list_reviews_section')) {
            continue;
        }

        $field_group['location'] = meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies_excluding_posts();
    }
    unset($field_group);

    return $field_groups;
}, 20, 2);

add_filter('acf/load_field_groups', function (array $field_groups, string $post_type): array {
    meza_register_acf_export_tool_local_definitions();

    if (!meza_should_merge_acf_field_groups_for_current_screen()) {
        return $field_groups;
    }

    $local_signatures = [];
    foreach ($field_groups as $field_group) {
        if (!is_array($field_group) || !meza_is_hardcoded_acf_field_group($field_group)) {
            continue;
        }

        $signature = meza_get_acf_field_group_merge_signature($field_group);
        if ($signature !== '') {
            $local_signatures[$signature] = true;
        }
    }

    if (empty($local_signatures)) {
        return $field_groups;
    }

    return array_values(array_filter($field_groups, static function ($field_group) use ($local_signatures): bool {
        if (!is_array($field_group) || meza_is_hardcoded_acf_field_group($field_group)) {
            return true;
        }

        $signature = meza_get_acf_field_group_merge_signature($field_group);

        return $signature === '' || empty($local_signatures[$signature]);
    }));
}, 25, 2);

add_filter('acf/load_field_group/key=group_meza_list_reviews_section', function (array $field_group): array {
    $field_group['location'] = meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies_excluding_posts();

    return $field_group;
});

add_filter('acf/load_fields/key=group_meza_site_events', function (array $fields, $parent): array {
    $expected_fields = function_exists('meza_get_events_fields') ? meza_get_events_fields() : [];
    if ($expected_fields === []) {
        return $fields;
    }

    $existing_fields_by_key = [];
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }

        $field_key = (string) ($field['key'] ?? '');
        if ($field_key !== '') {
            $existing_fields_by_key[$field_key] = $field;
        }
    }

    $normalized_fields = [];
    $parent_key = is_array($parent) ? (string) ($parent['key'] ?? 'group_meza_site_events') : 'group_meza_site_events';

    foreach (array_values($expected_fields) as $menu_order => $expected_field) {
        if (!is_array($expected_field)) {
            continue;
        }

        $field_key = (string) ($expected_field['key'] ?? '');
        if ($field_key === '') {
            continue;
        }

        $field = $expected_field;
        if (isset($existing_fields_by_key[$field_key]) && is_array($existing_fields_by_key[$field_key])) {
            $field = array_merge($existing_fields_by_key[$field_key], $expected_field);
        }

        if (function_exists('meza_preserve_acf_field_tree_identifiers')) {
            $field = meza_preserve_acf_field_tree_identifiers(
                $field,
                $existing_fields_by_key[$field_key] ?? null,
                $parent_key
            );
        }

        $field['parent'] = $parent_key;
        $field['menu_order'] = $menu_order;
        $normalized_fields[] = $field;
        unset($existing_fields_by_key[$field_key]);
    }

    foreach ($existing_fields_by_key as $field) {
        if (!is_array($field)) {
            continue;
        }

        $field_name = sanitize_key((string) ($field['name'] ?? ''));
        if (in_array($field_name, ['events', 'events_indexable', 'events_speakers', 'events_after'], true)) {
            continue;
        }

        if (function_exists('meza_preserve_acf_field_tree_identifiers')) {
            $field = meza_preserve_acf_field_tree_identifiers($field, $field, $parent_key);
        }

        $field['parent'] = $parent_key;
        $field['menu_order'] = count($normalized_fields);
        $normalized_fields[] = $field;
    }

    return $normalized_fields;
}, 30, 2);

add_filter('acf/load_fields/key=group_meza_site_season', function (array $fields, $parent): array {
    $expected_fields = function_exists('meza_get_season_fields') ? meza_get_season_fields() : [];
    if ($expected_fields === []) {
        return $fields;
    }

    $existing_fields_by_key = [];
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }

        $field_key = (string) ($field['key'] ?? '');
        if ($field_key !== '') {
            $existing_fields_by_key[$field_key] = $field;
        }
    }

    $normalized_fields = [];
    $parent_key = is_array($parent) ? (string) ($parent['key'] ?? 'group_meza_site_season') : 'group_meza_site_season';

    foreach (array_values($expected_fields) as $menu_order => $expected_field) {
        if (!is_array($expected_field)) {
            continue;
        }

        $field_key = (string) ($expected_field['key'] ?? '');
        if ($field_key === '') {
            continue;
        }

        $field = $expected_field;
        if (isset($existing_fields_by_key[$field_key]) && is_array($existing_fields_by_key[$field_key])) {
            $field = array_merge($existing_fields_by_key[$field_key], $expected_field);
        }

        if (function_exists('meza_preserve_acf_field_tree_identifiers')) {
            $field = meza_preserve_acf_field_tree_identifiers(
                $field,
                $existing_fields_by_key[$field_key] ?? null,
                $parent_key
            );
        }

        $field['parent'] = $parent_key;
        $field['menu_order'] = $menu_order;
        $normalized_fields[] = $field;
        unset($existing_fields_by_key[$field_key]);
    }

    foreach ($existing_fields_by_key as $field) {
        if (!is_array($field)) {
            continue;
        }

        $field_name = sanitize_key((string) ($field['name'] ?? ''));
        if ($field_name === 'season_after') {
            continue;
        }

        if (function_exists('meza_preserve_acf_field_tree_identifiers')) {
            $field = meza_preserve_acf_field_tree_identifiers($field, $field, $parent_key);
        }

        $field['parent'] = $parent_key;
        $field['menu_order'] = count($normalized_fields);
        $normalized_fields[] = $field;
    }

    return $normalized_fields;
}, 30, 2);

if (!function_exists('meza_acf_field_group_has_top_tabs')) {
    function meza_acf_field_group_has_top_tabs(array $fields): bool
    {
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            if (($field['type'] ?? '') === 'tab' && (($field['placement'] ?? 'top') === 'top')) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('meza_get_custom_content_tab_field_definition')) {
    function meza_get_custom_content_tab_field_definition(string $parent_key, int $menu_order): array
    {
        return [
            'key' => 'field_meza_custom_content_tab_' . sanitize_key($parent_key),
            'label' => 'Custom Content',
            'name' => '',
            'aria-label' => '',
            'type' => 'tab',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => '',
            ],
            'placement' => 'top',
            'endpoint' => 0,
            'selected' => 0,
            'parent' => $parent_key,
            'menu_order' => $menu_order,
        ];
    }
}

if (!function_exists('meza_acf_field_is_relationship_type')) {
    function meza_acf_field_is_relationship_type(array $field): bool
    {
        return sanitize_key((string) ($field['type'] ?? '')) === 'relationship';
    }
}

if (!function_exists('meza_find_acf_tab_section_insert_index')) {
    function meza_find_acf_tab_section_insert_index(array $fields, string $tab_label): ?int
    {
        $normalized_target = sanitize_title($tab_label);
        $tab_index = null;

        foreach ($fields as $index => $field) {
            if (!is_array($field) || ($field['type'] ?? '') !== 'tab') {
                continue;
            }

            if (sanitize_title((string) ($field['label'] ?? '')) === $normalized_target) {
                $tab_index = $index;
                break;
            }
        }

        if ($tab_index === null) {
            return null;
        }

        $insert_index = count($fields);
        foreach ($fields as $index => $field) {
            if ($index <= $tab_index || !is_array($field) || ($field['type'] ?? '') !== 'tab') {
                continue;
            }

            $insert_index = $index;
            break;
        }

        return $insert_index;
    }
}

add_filter('acf/load_post_types', function (array $posts): array {
    meza_register_acf_export_tool_local_definitions();

    return meza_filter_built_in_acf_admin_definition_objects('acf-post-type', $posts);
}, 5);

add_filter('acf/load_taxonomies', function (array $posts): array {
    meza_register_acf_export_tool_local_definitions();

    return meza_filter_built_in_acf_admin_definition_objects('acf-taxonomy', $posts);
}, 5);

add_filter('acf/load_ui_options_pages', function (array $posts): array {
    meza_register_acf_export_tool_local_definitions();

    return meza_filter_built_in_acf_admin_definition_objects('acf-ui-options-page', $posts);
}, 5);

add_action('pre_get_posts', function (WP_Query $query): void {
    if (
        !is_admin()
        || !$query->is_main_query()
        || !meza_is_acf_admin_definition_management_request()
    ) {
        return;
    }

    $post_type = sanitize_key((string) $query->get('post_type'));
    if (!meza_is_supported_acf_internal_post_type($post_type)) {
        return;
    }

    $built_in_ids = meza_get_built_in_acf_admin_definition_post_ids($post_type);
    if ($built_in_ids === []) {
        return;
    }

    $post__not_in = array_map('intval', (array) $query->get('post__not_in'));
    $query->set('post__not_in', array_values(array_unique(array_merge($post__not_in, $built_in_ids))));
}, 4);

add_action('admin_init', 'meza_redirect_built_in_acf_admin_definition_edit_request', 20);

add_filter('acf/load_fields', function (array $fields, $parent): array {
    if (!is_array($parent) || !meza_is_hardcoded_acf_field_group($parent)) {
        return $fields;
    }

    $mergeable_groups = meza_get_mergeable_db_acf_field_groups_for_local_group($parent);
    $override_group = function_exists('meza_get_db_acf_field_group_override_for_local_group')
        ? meza_get_db_acf_field_group_override_for_local_group($parent)
        : null;

    if (is_array($override_group)) {
        $has_override_group = false;

        foreach ($mergeable_groups as $mergeable_group) {
            if (!is_array($mergeable_group)) {
                continue;
            }

            if (
                (int) ($mergeable_group['ID'] ?? 0) === (int) ($override_group['ID'] ?? 0)
                || (
                    !empty($mergeable_group['key'])
                    && !empty($override_group['key'])
                    && (string) $mergeable_group['key'] === (string) $override_group['key']
                )
            ) {
                $has_override_group = true;
                break;
            }
        }

        if (!$has_override_group) {
            $mergeable_groups[] = $override_group;
        }
    }

    if (empty($mergeable_groups) || !function_exists('acf_get_raw_fields') || !function_exists('acf_get_field')) {
        return $fields;
    }

    $existing_keys = [];
    foreach ($fields as $field) {
        if (is_array($field) && !empty($field['key'])) {
            $existing_keys[(string) $field['key']] = true;
        }
    }

    if (function_exists('meza_merge_safe_acf_field_overrides')) {
        foreach ($mergeable_groups as $mergeable_group) {
            $mergeable_fields = isset($mergeable_group['fields']) && is_array($mergeable_group['fields'])
                ? meza_index_acf_fields_by_key($mergeable_group['fields'])
                : [];

            foreach ($fields as $field_index => $field) {
                if (!is_array($field)) {
                    continue;
                }

                $field_key = (string) ($field['key'] ?? '');
                if ($field_key === '' || !isset($mergeable_fields[$field_key])) {
                    continue;
                }

                $fields[$field_index] = meza_merge_safe_acf_field_overrides(
                    $field,
                    $mergeable_fields[$field_key]
                );
            }
        }
    }

    $next_menu_order = count($fields);
    $appended_custom_fields = [];

    foreach ($mergeable_groups as $mergeable_group) {
        $group_id = (int) ($mergeable_group['ID'] ?? 0);
        if ($group_id <= 0) {
            continue;
        }

        foreach ((array) acf_get_raw_fields($group_id) as $raw_field) {
            $field_id = (int) ($raw_field['ID'] ?? 0);
            if ($field_id <= 0) {
                continue;
            }

            $field = acf_get_field($field_id);
            if (!is_array($field)) {
                continue;
            }

            $field_key = (string) ($field['key'] ?? '');
            if ($field_key === '' || isset($existing_keys[$field_key])) {
                continue;
            }

            if (function_exists('meza_preserve_acf_field_tree_identifiers')) {
                $field = meza_preserve_acf_field_tree_identifiers(
                    $field,
                    $field,
                    (string) ($parent['key'] ?? $field['parent'] ?? '')
                );
            }

            $field['parent'] = (string) ($parent['key'] ?? $field['parent'] ?? '');
            $field['menu_order'] = $next_menu_order;
            $appended_custom_fields[] = $field;

            $existing_keys[$field_key] = true;
            $next_menu_order++;
        }
    }

    if ($appended_custom_fields !== []) {
        $relationship_fields = [];
        $other_fields = [];

        foreach ($appended_custom_fields as $field) {
            if (meza_acf_field_is_relationship_type($field)) {
                $relationship_fields[] = $field;
            } else {
                $other_fields[] = $field;
            }
        }

        $has_top_tabs = meza_acf_field_group_has_top_tabs($fields);
        if ($relationship_fields !== [] && $has_top_tabs) {
            $insert_index = meza_find_acf_tab_section_insert_index($fields, 'Relationships');
            if ($insert_index !== null) {
                array_splice($fields, $insert_index, 0, $relationship_fields);
            } else {
                $other_fields = array_merge($relationship_fields, $other_fields);
            }
        } else {
            $other_fields = array_merge($relationship_fields, $other_fields);
        }

        if ($other_fields !== []) {
            $parent_key = (string) ($parent['key'] ?? '');
            if ($parent_key !== '' && $has_top_tabs) {
                $fields[] = meza_get_custom_content_tab_field_definition($parent_key, count($fields));
            }

            foreach ($other_fields as $field) {
                $fields[] = $field;
            }
        }

        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                continue;
            }

            if (function_exists('meza_preserve_acf_field_tree_identifiers')) {
                $field = meza_preserve_acf_field_tree_identifiers(
                    $field,
                    $field,
                    (string) ($parent['key'] ?? $field['parent'] ?? '')
                );
            }

            $fields[$index] = $field;
            $fields[$index]['menu_order'] = $index;
            $fields[$index]['parent'] = (string) ($parent['key'] ?? $field['parent'] ?? '');
        }
    }

    return $fields;
}, 20, 2);
