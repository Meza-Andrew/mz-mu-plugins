<?php

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

        $terms = [
            'partner' => 'Partner',
        ];

        if (meza_supports_sponsor_features()) {
            $terms['sponsor'] = 'Sponsor';
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
add_action('init', 'meza_seed_default_organization_type_terms', 2);

if (!function_exists('meza_migrate_legacy_default_locality_term')) {
    function meza_migrate_legacy_default_locality_term(): void
    {
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
add_action('init', 'meza_migrate_legacy_default_locality_term', 1);

if (!function_exists('meza_seed_default_locality_terms')) {
    function meza_seed_default_locality_terms(): void
    {
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
add_action('init', 'meza_seed_default_locality_terms', 2);

if (!function_exists('meza_ensure_locality_taxonomy_runtime_registration')) {
    function meza_ensure_locality_taxonomy_runtime_registration(): void
    {
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
        return meza_should_seed_default_acf_field_groups();
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
    function meza_track_managed_acf_definition_event(string $kind, array $candidate, bool $is_deleted): void
    {
        $definition = meza_get_managed_acf_definition_match($kind, $candidate);
        if (!is_array($definition)) {
            return;
        }

        if ($is_deleted) {
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

add_action('wp_trash_post', 'meza_track_managed_acf_post_deletion', 5);
add_action('before_delete_post', 'meza_track_managed_acf_post_deletion', 5);
add_action('untrashed_post', 'meza_track_managed_acf_post_restoration', 5);
add_action('acf/trash_field_group', static function (array $field_group): void {
    meza_track_managed_acf_definition_event('field_groups', $field_group, true);
}, 5);
add_action('acf/delete_field_group', static function (array $field_group): void {
    meza_track_managed_acf_definition_event('field_groups', $field_group, true);
}, 5);
add_action('acf/untrash_field_group', static function (array $field_group): void {
    meza_track_managed_acf_definition_event('field_groups', $field_group, false);
}, 5);
add_action('acf/trash_post_type', static function (array $post_type): void {
    meza_track_managed_acf_definition_event('post_types', $post_type, true);
}, 5);
add_action('acf/delete_post_type', static function (array $post_type): void {
    meza_track_managed_acf_definition_event('post_types', $post_type, true);
}, 5);
add_action('acf/untrash_post_type', static function (array $post_type): void {
    meza_track_managed_acf_definition_event('post_types', $post_type, false);
}, 5);
add_action('acf/trash_taxonomy', static function (array $taxonomy): void {
    meza_track_managed_acf_definition_event('taxonomies', $taxonomy, true);
}, 5);
add_action('acf/delete_taxonomy', static function (array $taxonomy): void {
    meza_track_managed_acf_definition_event('taxonomies', $taxonomy, true);
}, 5);
add_action('acf/untrash_taxonomy', static function (array $taxonomy): void {
    meza_track_managed_acf_definition_event('taxonomies', $taxonomy, false);
}, 5);
add_action('acf/trash_ui_options_page', static function (array $options_page): void {
    meza_track_managed_acf_definition_event('options_pages', $options_page, true);
}, 5);
add_action('acf/delete_ui_options_page', static function (array $options_page): void {
    meza_track_managed_acf_definition_event('options_pages', $options_page, true);
}, 5);
add_action('acf/untrash_ui_options_page', static function (array $options_page): void {
    meza_track_managed_acf_definition_event('options_pages', $options_page, false);
}, 5);

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

    function meza_get_default_editable_acf_field_group_definitions(): array
    {
        $definitions = [
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
                                'key' => 'field_meza_list_profiles_subhead',
                                'label' => 'Subhead',
                                'name' => 'subhead',
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
                        ],
                    ],
                ],
                'location' => [
                    [
                        [
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'page',
                        ],
                    ],
                ],
                'menu_order' => 9,
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
                                'key' => 'field_06661b49',
                                'label' => 'Subhead',
                                'name' => 'subhead',
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
                                'key' => 'field_69c16e4052291',
                                'label' => 'Subhead',
                                'name' => 'subhead',
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
                                'layout' => 'table',
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
                'location' => [
                    [
                        [
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'page',
                        ],
                    ],
                ],
                'menu_order' => 13,
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
                                'key' => 'field_6901493a64a13',
                                'label' => 'Subhead',
                                'name' => 'subhead',
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
                                'min' => 6,
                                'max' => 0,
                                'collapsed' => '',
                                'button_label' => 'Add Media',
                                'rows_per_page' => 20,
                                'sub_fields' => [
                                    [
                                        'key' => 'field_69028febfd534',
                                        'label' => 'Image',
                                        'name' => 'image',
                                        'aria-label' => '',
                                        'type' => 'image',
                                        'instructions' => '',
                                        'required' => 1,
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
                                        'parent_repeater' => 'field_69028fd8fd533',
                                    ],
                                    [
                                        'key' => 'field_6902901cfd535',
                                        'label' => 'Video',
                                        'name' => 'video',
                                        'aria-label' => '',
                                        'type' => 'file',
                                        'instructions' => '',
                                        'required' => 0,
                                        'conditional_logic' => [
                                            [
                                                [
                                                    'field' => 'field_69028febfd534',
                                                    'operator' => '!=empty',
                                                ],
                                            ],
                                        ],
                                        'wrapper' => [
                                            'width' => '',
                                            'class' => '',
                                            'id' => '',
                                        ],
                                        'return_format' => 'id',
                                        'library' => 'all',
                                        'min_size' => '',
                                        'max_size' => '',
                                        'mime_types' => '.webm',
                                        'allow_in_bindings' => 0,
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
                'location' => [
                    [
                        [
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'page',
                        ],
                    ],
                ],
                'menu_order' => 10,
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

        if (meza_supports_sponsor_features()) {
            $definitions[] = meza_get_list_sponsors_section_field_group_definition();
        }

        if (meza_should_register_contact_locations_section_group()) {
            $definitions[] = meza_get_contact_locations_section_field_group_definition();
        }

        if (meza_business_information_enables_ecommerce()) {
            $definitions = array_merge($definitions, meza_get_ecommerce_default_editable_acf_field_group_definitions());
        }

        return $definitions;
    }
}

if (!function_exists('meza_get_default_editable_acf_taxonomy_definitions')) {
    function meza_get_default_editable_acf_taxonomy_definitions(): array
    {
        $definitions = [];

        if (meza_supports_sponsor_features()) {
            $definitions[] = meza_get_sponsor_type_taxonomy_definition();
        }

        return $definitions;
    }
}

if (!function_exists('meza_get_existing_editable_acf_field_group_id')) {
    function meza_get_existing_editable_acf_field_group_id(array $definition): int
    {
        if (!function_exists('acf_get_field_groups')) {
            return 0;
        }

        $filters = function_exists('acf_disable_filters') ? acf_disable_filters() : null;
        $field_groups = (array) acf_get_field_groups();
        if (function_exists('acf_enable_filters')) {
            acf_enable_filters($filters ?? []);
        }

        $target_key = (string) ($definition['key'] ?? '');
        $target_title = trim((string) ($definition['title'] ?? ''));

        foreach ($field_groups as $field_group) {
            if (!is_array($field_group)) {
                continue;
            }

            if ($target_key !== '' && (string) ($field_group['key'] ?? '') === $target_key) {
                $field_group_id = (int) ($field_group['ID'] ?? 0);
                if ($field_group_id > 0) {
                    return $field_group_id;
                }
            }
        }

        foreach ($field_groups as $field_group) {
            if (!is_array($field_group)) {
                continue;
            }

            if ($target_title !== '' && trim((string) ($field_group['title'] ?? '')) === $target_title) {
                $field_group_id = (int) ($field_group['ID'] ?? 0);
                if ($field_group_id > 0) {
                    return $field_group_id;
                }
            }
        }

        if (!function_exists('acf_get_raw_field_groups')) {
            return 0;
        }

        foreach ((array) acf_get_raw_field_groups() as $field_group) {
            if (!is_array($field_group)) {
                continue;
            }

            if ($target_key !== '' && (string) ($field_group['key'] ?? '') === $target_key) {
                return (int) ($field_group['ID'] ?? 0);
            }
        }

        foreach ((array) acf_get_raw_field_groups() as $field_group) {
            if (!is_array($field_group)) {
                continue;
            }

            if ($target_title !== '' && trim((string) ($field_group['title'] ?? '')) === $target_title) {
                return (int) ($field_group['ID'] ?? 0);
            }
        }

        return 0;
    }
}

if (!function_exists('meza_delete_editable_acf_field_group_definition')) {
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
        return '2026-05-02-orders-and-show-prefix-v2';
    }

    function meza_get_acf_field_group_order_migration_option_name(): string
    {
        return 'meza_acf_field_group_order_migration_version';
    }

    function meza_get_raw_editable_acf_field_group_id(array $definition): int
    {
        if (!function_exists('acf_get_raw_field_groups')) {
            return 0;
        }

        $target_key = (string) ($definition['key'] ?? '');
        $target_title = trim((string) ($definition['title'] ?? ''));

        foreach ((array) acf_get_raw_field_groups() as $field_group) {
            if (!is_array($field_group)) {
                continue;
            }

            if ($target_key !== '' && (string) ($field_group['key'] ?? '') === $target_key) {
                return (int) ($field_group['ID'] ?? 0);
            }
        }

        foreach ((array) acf_get_raw_field_groups() as $field_group) {
            if (!is_array($field_group)) {
                continue;
            }

            if ($target_title !== '' && trim((string) ($field_group['title'] ?? '')) === $target_title) {
                return (int) ($field_group['ID'] ?? 0);
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

            $existing_id = meza_get_existing_editable_acf_field_group_id($definition);
            if ($existing_id > 0) {
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

        $field_group['location'] = meza_get_acf_location_rules_for_permalink_post_types();
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
    $field_group['location'] = meza_get_acf_location_rules_for_permalink_post_types();

    return $field_group;
});

add_filter('acf/load_post_types', function (array $posts): array {
    meza_register_acf_export_tool_local_definitions();

    return $posts;
}, 5);

add_filter('acf/load_taxonomies', function (array $posts): array {
    meza_register_acf_export_tool_local_definitions();

    return $posts;
}, 5);

add_filter('acf/load_ui_options_pages', function (array $posts): array {
    meza_register_acf_export_tool_local_definitions();

    return $posts;
}, 5);

add_filter('acf/load_fields', function (array $fields, $parent): array {
    if (!is_array($parent) || !meza_is_hardcoded_acf_field_group($parent)) {
        return $fields;
    }

    $mergeable_groups = meza_get_mergeable_db_acf_field_groups_for_local_group($parent);
    if (empty($mergeable_groups) || !function_exists('acf_get_raw_fields') || !function_exists('acf_get_field')) {
        return $fields;
    }

    $existing_keys = [];
    foreach ($fields as $field) {
        if (is_array($field) && !empty($field['key'])) {
            $existing_keys[(string) $field['key']] = true;
        }
    }

    $next_menu_order = count($fields);

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

            $field['parent'] = (string) ($parent['key'] ?? $field['parent'] ?? '');
            $field['menu_order'] = $next_menu_order;
            $fields[] = $field;

            $existing_keys[$field_key] = true;
            $next_menu_order++;
        }
    }

    return $fields;
}, 20, 2);

