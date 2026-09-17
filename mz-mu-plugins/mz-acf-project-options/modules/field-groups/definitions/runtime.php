<?php

    function meza_get_local_acf_post_type_definitions(): array
    {
        $definitions = [
            meza_get_faq_post_type_definition(),
            meza_get_cta_post_type_definition(),
            meza_get_review_post_type_definition(),
            meza_get_organization_post_type_definition(),
        ];

        if (function_exists('meza_are_services_enabled') && meza_are_services_enabled()) {
            array_unshift($definitions, meza_get_service_post_type_definition());
        }

        if (function_exists('meza_supports_profile_features') && meza_supports_profile_features()) {
            $definitions[] = meza_get_profile_post_type_definition();
        }

        if (function_exists('meza_is_nonprofit_business_type') && meza_is_nonprofit_business_type()) {
            $definitions[] = meza_get_certification_post_type_definition();
        }

        if (function_exists('meza_is_event_functionality_enabled') && meza_is_event_functionality_enabled()) {
            $definitions[] = meza_get_event_post_type_definition();
        }

        if (function_exists('mzf_get_form_post_type_definition')) {
            $definitions[] = mzf_get_form_post_type_definition();
        }

        /**
         * Filters local ACF post-type definitions before managed registration.
         *
         * Extensions should append declarative ACF post-type definitions here instead
         * of calling register_post_type() or acf_add_local_internal_post_type()
         * directly. Returned definitions participate in the shared managed-definition
         * lifecycle: runtime registration, ACF export grouping, enable/disable
         * controls, override comparison, and deletion tracking.
         *
         * @param array<int,array<string,mixed>> $definitions Local ACF post-type definitions.
         */
        $definitions = apply_filters('meza_shared_project_acf_post_type_definitions', $definitions);

        return is_array($definitions) ? array_values($definitions) : [];
    }

    function meza_get_builtin_acf_post_type_slugs(): array
    {
        $slugs = [];

        foreach (meza_get_local_acf_post_type_definitions() as $definition) {
            $post_type = sanitize_key((string) ($definition['post_type'] ?? ''));
            if ($post_type !== '') {
                $slugs[] = $post_type;
            }
        }

        // Keep these out of the "new custom ACF post type" locality defaults flow
        // even though they are now managed directly in ACF instead of PHP.
        $slugs[] = 'profile';
        $slugs[] = 'organization';

        return array_values(array_unique($slugs));
    }

    function meza_get_local_acf_taxonomy_definitions(): array
    {
        $definitions = [
            meza_get_organization_type_taxonomy_definition(),
        ];

        if (function_exists('meza_are_services_enabled') && meza_are_services_enabled()) {
            $definitions[] = meza_get_service_category_taxonomy_definition();
        }

        if (function_exists('meza_are_localities_enabled') && meza_are_localities_enabled()) {
            $definitions[] = meza_get_locality_taxonomy_definition();
        }

        if (
            function_exists('meza_is_event_functionality_enabled')
            && meza_is_event_functionality_enabled()
            && function_exists('meza_get_event_type_taxonomy_definition')
        ) {
            $definitions[] = meza_get_event_type_taxonomy_definition();
        }

        if (
            function_exists('meza_are_roles_enabled')
            && meza_are_roles_enabled()
            && function_exists('meza_get_role_taxonomy_definition')
            && function_exists('meza_get_role_taxonomy_object_types')
            && meza_get_role_taxonomy_object_types() !== []
        ) {
            $definitions[] = meza_get_role_taxonomy_definition();
        }

        if (
            function_exists('meza_events_have_speakers_topics')
            && meza_events_have_speakers_topics()
            && function_exists('meza_get_topic_taxonomy_definition')
        ) {
            $definitions[] = meza_get_topic_taxonomy_definition();
        }

        if (function_exists('meza_supports_profile_features') && meza_supports_profile_features()) {
            $definitions[] = meza_get_profile_type_taxonomy_definition();
        }

        /**
         * Filters local ACF taxonomy definitions before managed registration.
         *
         * Extensions should append declarative ACF taxonomy definitions here instead
         * of calling register_taxonomy() or acf_add_local_internal_post_type()
         * directly. Returned definitions participate in the shared managed-definition
         * lifecycle: runtime registration, ACF export grouping, enable/disable
         * controls, override comparison, and deletion tracking.
         *
         * @param array<int,array<string,mixed>> $definitions Local ACF taxonomy definitions.
         */
        $definitions = apply_filters('meza_shared_project_acf_taxonomy_definitions', $definitions);

        return is_array($definitions) ? array_values($definitions) : [];
    }

    function meza_get_local_acf_post_type_has_archive_value(array $definition)
    {
        $archive_slug = sanitize_title((string) ($definition['has_archive_slug'] ?? ''));
        if ($archive_slug !== '') {
            return $archive_slug;
        }

        return !empty($definition['has_archive']);
    }

    function meza_get_local_acf_post_type_rewrite_value(array $definition)
    {
        $rewrite_definition = isset($definition['rewrite']) && is_array($definition['rewrite'])
            ? $definition['rewrite']
            : [];

        $permalink_mode = sanitize_key((string) ($rewrite_definition['permalink_rewrite'] ?? ''));
        if ($permalink_mode === '' || $permalink_mode === 'no_permalink') {
            return false;
        }

        $post_type = sanitize_key((string) ($definition['post_type'] ?? ''));
        if ($post_type === '') {
            return false;
        }

        $rewrite = [
            'slug' => $post_type,
            'with_front' => !isset($rewrite_definition['with_front']) || !empty($rewrite_definition['with_front']),
            'feeds' => !empty($rewrite_definition['feeds']),
            'pages' => !isset($rewrite_definition['pages']) || !empty($rewrite_definition['pages']),
        ];

        return $rewrite;
    }

    function meza_get_local_acf_post_type_query_var_value(array $definition)
    {
        $query_var_mode = sanitize_key((string) ($definition['query_var'] ?? ''));
        if ($query_var_mode === '' || $query_var_mode === 'no_query_var') {
            return false;
        }

        $query_var_name = sanitize_key((string) ($definition['query_var_name'] ?? ''));
        if ($query_var_name !== '') {
            return $query_var_name;
        }

        $post_type = sanitize_key((string) ($definition['post_type'] ?? ''));
        return $post_type !== '' ? $post_type : true;
    }

    function meza_get_local_acf_post_type_args(array $definition): array
    {
        $args = [
            'labels' => (array) ($definition['labels'] ?? []),
            'description' => (string) ($definition['description'] ?? ''),
            'public' => !empty($definition['public']),
            'hierarchical' => !empty($definition['hierarchical']),
            'exclude_from_search' => !empty($definition['exclude_from_search']),
            'publicly_queryable' => !empty($definition['publicly_queryable']),
            'show_ui' => !empty($definition['show_ui']),
            'show_in_menu' => !empty($definition['show_in_menu']),
            'show_in_admin_bar' => !empty($definition['show_in_admin_bar']),
            'show_in_nav_menus' => !empty($definition['show_in_nav_menus']),
            'show_in_rest' => !empty($definition['show_in_rest']),
            'rest_namespace' => (string) ($definition['rest_namespace'] ?? 'wp/v2'),
            'rest_controller_class' => (string) ($definition['rest_controller_class'] ?? 'WP_REST_Posts_Controller'),
            'menu_icon' => (string) ($definition['menu_icon']['value'] ?? 'dashicons-admin-post'),
            'supports' => (array) ($definition['supports'] ?? []),
            'has_archive' => meza_get_local_acf_post_type_has_archive_value($definition),
            'rewrite' => meza_get_local_acf_post_type_rewrite_value($definition),
            'query_var' => meza_get_local_acf_post_type_query_var_value($definition),
            'can_export' => !empty($definition['can_export']),
            'delete_with_user' => !empty($definition['delete_with_user']),
            'map_meta_cap' => true,
        ];

        $rest_base = (string) ($definition['rest_base'] ?? '');
        if ($rest_base !== '') {
            $args['rest_base'] = $rest_base;
        }

        if (isset($definition['menu_position']) && is_numeric($definition['menu_position'])) {
            $args['menu_position'] = (int) $definition['menu_position'];
        }

        $taxonomies = is_array($definition['taxonomies'] ?? null) ? array_values($definition['taxonomies']) : [];
        if ($taxonomies !== []) {
            $args['taxonomies'] = $taxonomies;
        }

        $capability_type = (string) ($definition['capability_type'] ?? '');
        if ($capability_type !== '') {
            $args['capability_type'] = $capability_type;
        }

        return $args;
    }

    function meza_get_local_acf_taxonomy_rewrite_value(array $definition)
    {
        $rewrite_definition = isset($definition['rewrite']) && is_array($definition['rewrite'])
            ? $definition['rewrite']
            : [];

        $permalink_mode = sanitize_key((string) ($rewrite_definition['permalink_rewrite'] ?? ''));
        if ($permalink_mode === '' || $permalink_mode === 'no_permalink') {
            return false;
        }

        $taxonomy = sanitize_key((string) ($definition['taxonomy'] ?? ''));
        if ($taxonomy === '') {
            return false;
        }

        return [
            'slug' => $taxonomy,
            'with_front' => !isset($rewrite_definition['with_front']) || !empty($rewrite_definition['with_front']),
            'hierarchical' => !empty($rewrite_definition['rewrite_hierarchical']),
        ];
    }

    function meza_get_local_acf_taxonomy_query_var_value(array $definition)
    {
        $query_var_mode = sanitize_key((string) ($definition['query_var'] ?? ''));
        if ($query_var_mode === '' || $query_var_mode === 'no_query_var') {
            return false;
        }

        $query_var_name = sanitize_key((string) ($definition['query_var_name'] ?? ''));
        if ($query_var_name !== '') {
            return $query_var_name;
        }

        $taxonomy = sanitize_key((string) ($definition['taxonomy'] ?? ''));
        return $taxonomy !== '' ? $taxonomy : true;
    }

    function meza_get_local_acf_taxonomy_args(array $definition): array
    {
        $default_term = [];
        $default_term_definition = (array) ($definition['default_term'] ?? []);
        if (!empty($default_term_definition['default_term_enabled'])) {
            $default_term_name = trim((string) ($default_term_definition['name'] ?? ''));
            $default_term_slug = sanitize_title((string) ($default_term_definition['slug'] ?? ''));

            if ($default_term_name !== '' && $default_term_slug !== '') {
                $default_term = [
                    'name' => $default_term_name,
                    'slug' => $default_term_slug,
                ];
            }
        }

        $args = [
            'labels' => (array) ($definition['labels'] ?? []),
            'description' => (string) ($definition['description'] ?? ''),
            'public' => !empty($definition['public']),
            'publicly_queryable' => !empty($definition['publicly_queryable']),
            'hierarchical' => !empty($definition['hierarchical']),
            'show_ui' => !empty($definition['show_ui']),
            'show_in_menu' => !empty($definition['show_in_menu']),
            'show_in_nav_menus' => !empty($definition['show_in_nav_menus']),
            'show_in_rest' => !empty($definition['show_in_rest']),
            'rest_base' => (string) ($definition['rest_base'] ?? ''),
            'rest_namespace' => (string) ($definition['rest_namespace'] ?? 'wp/v2'),
            'rest_controller_class' => (string) ($definition['rest_controller_class'] ?? 'WP_REST_Terms_Controller'),
            'show_tagcloud' => !empty($definition['show_tagcloud']),
            'show_in_quick_edit' => !empty($definition['show_in_quick_edit']),
            'show_admin_column' => !empty($definition['show_admin_column']),
            'rewrite' => meza_get_local_acf_taxonomy_rewrite_value($definition),
            'query_var' => meza_get_local_acf_taxonomy_query_var_value($definition),
            'sort' => !empty($definition['sort']),
            'capabilities' => (array) ($definition['capabilities'] ?? []),
            'default_term' => $default_term,
        ];

        $meta_box_callback = trim((string) ($definition['meta_box_cb'] ?? ''));
        if ($meta_box_callback === '') {
            $meta_box_callback = 'post_categories_meta_box';
        }
        $args['meta_box_cb'] = $meta_box_callback;

        $meta_box_sanitize_callback = trim((string) ($definition['meta_box_sanitize_cb'] ?? ''));
        if ($meta_box_sanitize_callback === '' && $meta_box_callback === 'post_categories_meta_box') {
            $meta_box_sanitize_callback = 'taxonomy_meta_box_sanitize_cb_checkboxes';
        }
        if ($meta_box_sanitize_callback !== '') {
            $args['meta_box_sanitize_cb'] = $meta_box_sanitize_callback;
        }

        return $args;
    }

    function meza_get_header_section_group_section_field_names(): array
    {
        return [
            'group_692cdbb4a0ff0' => 'section_hero',
            'group_697ffe3780c87' => 'section_form',
            'group_meza_contact_locations_section' => 'section_list-locations',
            'group_697feb01ec35f' => 'section_faqs',
            'group_meza_list_events_section' => 'section_list-events',
            'group_meza_list_past_events_section' => 'section_list-past-events',
            'group_meza_list_reviews_section' => 'section_list-reviews',
            'group_meza_list_posts_section' => 'section_list-posts',
            'group_meza_list_resources_section' => 'section_list-resources',
            'group_b2f9d7e8' => 'section_list-services',
        ];
    }

    function meza_get_section_sub_field_stable_key(string $section_field_name, string $sub_field_name): string
    {
        return 'field_meza_' . str_replace('-', '_', sanitize_key($section_field_name)) . '_' . sanitize_key($sub_field_name);
    }

    function meza_get_context_value_field_base_key(string $field_key): string
    {
        $field_key = sanitize_key($field_key);

        if ($field_key === '') {
            return '';
        }

        foreach ([
            'field_meza_event_context_',
            'field_meza_season_context_',
        ] as $prefix) {
            if (!str_starts_with($field_key, $prefix)) {
                continue;
            }

            $field_key = substr($field_key, strlen($prefix));
            $field_key = preg_replace('/_(before|after|season_after)$/', '', $field_key) ?: '';
            break;
        }

        return $field_key;
    }

    function meza_get_event_context_value_field_key(string $field_key, string $context): string
    {
        $field_key = meza_get_context_value_field_base_key($field_key);
        $context = sanitize_key($context);

        if ($field_key === '' || $context === '') {
            return '';
        }

        return 'field_meza_event_context_' . $field_key . '_' . $context;
    }

    function meza_get_season_context_value_field_key(string $field_key, string $context): string
    {
        $field_key = meza_get_context_value_field_base_key($field_key);
        $context = sanitize_key($context);

        if ($field_key === '' || $context === '') {
            return '';
        }

        return 'field_meza_season_context_' . $field_key . '_' . $context;
    }

    function meza_get_event_context_request_post_id(): int
    {
        $request_post_id = 0;

        if (isset($_GET['post']) && is_numeric($_GET['post'])) {
            $request_post_id = (int) $_GET['post'];
        } elseif (isset($_POST['post_ID']) && is_numeric($_POST['post_ID'])) {
            $request_post_id = (int) $_POST['post_ID'];
        } elseif (isset($_POST['post_id']) && is_numeric($_POST['post_id'])) {
            $request_post_id = (int) $_POST['post_id'];
        }

        return $request_post_id;
    }

    function meza_get_event_context_request_post_type(): string
    {
        $request_post_id = meza_get_event_context_request_post_id();

        if ($request_post_id > 0) {
            $post_type = get_post_type($request_post_id);
            if (is_string($post_type) && $post_type !== '') {
                return sanitize_key($post_type);
            }
        }

        if (isset($_GET['post_type'])) {
            $post_type = sanitize_key((string) $_GET['post_type']);
            if ($post_type !== '') {
                return $post_type;
            }
        }

        if (isset($_POST['post_type'])) {
            $post_type = sanitize_key((string) $_POST['post_type']);
            if ($post_type !== '') {
                return $post_type;
            }
        }

        global $typenow;
        if (is_string($typenow) && $typenow !== '') {
            return sanitize_key($typenow);
        }

        return '';
    }

    function meza_get_runtime_context_post_type(int $post_id = 0): string
    {
        if ($post_id > 0) {
            $post_type = get_post_type($post_id);
            if (is_string($post_type) && $post_type !== '') {
                return sanitize_key($post_type);
            }
        }

        return meza_get_event_context_request_post_type();
    }

    function meza_runtime_post_supports_event_context(int $post_id = 0): bool
    {
        return function_exists('meza_events_have_after_content')
            && meza_events_have_after_content()
            && meza_get_runtime_context_post_type($post_id) === 'event';
    }

    function meza_runtime_post_supports_season_context(int $post_id = 0): bool
    {
        return function_exists('meza_annual_years_have_end_of_year_content')
            && meza_annual_years_have_end_of_year_content()
            && meza_get_runtime_context_post_type($post_id) === 'event';
    }

    function meza_get_global_season_context_section_field_names(): array
    {
        return [
            'section_list-events',
            'section_list-concerts',
        ];
    }

    function meza_section_field_name_supports_global_season_context(string $section_field_name = ''): bool
    {
        $section_field_name = sanitize_key($section_field_name);

        return $section_field_name !== ''
            && in_array($section_field_name, meza_get_global_season_context_section_field_names(), true);
    }

    function meza_field_tree_has_global_season_context_sections(array $fields): bool
    {
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $field_name = sanitize_key((string) ($field['name'] ?? ''));
            $field_type = (string) ($field['type'] ?? '');

            if (
                $field_type === 'group'
                && str_starts_with($field_name, 'section_')
                && meza_section_field_name_supports_global_season_context($field_name)
            ) {
                return true;
            }

            if (in_array($field_type, ['group', 'repeater'], true)) {
                if (meza_field_tree_has_global_season_context_sections((array) ($field['sub_fields'] ?? []))) {
                    return true;
                }
            }

            if ($field_type === 'flexible_content') {
                foreach ((array) ($field['layouts'] ?? []) as $layout) {
                    if (
                        is_array($layout)
                        && meza_field_tree_has_global_season_context_sections((array) ($layout['sub_fields'] ?? []))
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    function meza_section_field_group_supports_event_context(array $field_group): bool
    {
        if (!meza_runtime_post_supports_event_context()) {
            return false;
        }

        foreach ((array) ($field_group['location'] ?? []) as $location_group) {
            if (!is_array($location_group)) {
                continue;
            }

            foreach ($location_group as $rule) {
                if (!is_array($rule)) {
                    continue;
                }

                if (
                    sanitize_key((string) ($rule['param'] ?? '')) === 'post_type'
                    && (string) ($rule['operator'] ?? '') === '=='
                    && sanitize_key((string) ($rule['value'] ?? '')) === 'event'
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    function meza_section_field_group_supports_season_context(array $field_group): bool
    {
        if (
            !function_exists('meza_annual_years_have_end_of_year_content')
            || !meza_annual_years_have_end_of_year_content()
        ) {
            return false;
        }

        if (meza_field_tree_has_global_season_context_sections((array) ($field_group['fields'] ?? []))) {
            return true;
        }

        if (!meza_runtime_post_supports_season_context()) {
            return false;
        }

        foreach ((array) ($field_group['location'] ?? []) as $location_group) {
            if (!is_array($location_group)) {
                continue;
            }

            foreach ($location_group as $rule) {
                if (!is_array($rule)) {
                    continue;
                }

                if (sanitize_key((string) ($rule['param'] ?? '')) !== 'options_page') {
                    continue;
                }

                return false;
            }
        }

        return true;
    }

    function meza_get_event_context_supported_field_types(): array
    {
        return [
            'text',
            'textarea',
            'wysiwyg',
            'link',
            // Keep url support for parity with the existing Event field group pattern.
            'url',
        ];
    }

    function meza_relationship_field_supports_event_context(array $field): bool
    {
        if ((string) ($field['type'] ?? '') !== 'relationship') {
            return false;
        }

        $name = sanitize_key((string) ($field['name'] ?? ''));

        return in_array($name, ['cta', 'faqs'], true);
    }

    function meza_get_event_context_nested_sub_field(array $field, string $target_name): ?array
    {
        foreach ((array) ($field['sub_fields'] ?? []) as $sub_field) {
            if (!is_array($sub_field)) {
                continue;
            }

            if (sanitize_key((string) ($sub_field['name'] ?? '')) === $target_name) {
                return $sub_field;
            }
        }

        return null;
    }

    function meza_get_event_context_effective_field_type(array $field): string
    {
        $field_type = sanitize_key((string) ($field['type'] ?? ''));
        if ($field_type !== 'group') {
            return $field_type;
        }

        foreach (['before', 'base', 'after', 'season_after'] as $slot) {
            $sub_field = meza_get_event_context_nested_sub_field($field, $slot);
            if (!is_array($sub_field)) {
                continue;
            }

            $sub_field_type = sanitize_key((string) ($sub_field['type'] ?? ''));
            if ($sub_field_type !== '' && $sub_field_type !== 'group') {
                return $sub_field_type;
            }
        }

        return $field_type;
    }

    function meza_is_event_context_group_field(array $field): bool
    {
        if ((string) ($field['type'] ?? '') !== 'group') {
            return false;
        }

        $before_field = meza_get_event_context_nested_sub_field($field, 'before');
        $after_field = meza_get_event_context_nested_sub_field($field, 'after');
        $season_after_field = meza_get_event_context_nested_sub_field($field, 'season_after');

        return is_array($after_field) || (is_array($before_field) && !is_array($season_after_field));
    }

    function meza_is_season_context_group_field(array $field): bool
    {
        if ((string) ($field['type'] ?? '') !== 'group') {
            return false;
        }

        $before_field = meza_get_event_context_nested_sub_field($field, 'before');
        $base_field = meza_get_event_context_nested_sub_field($field, 'base');
        $event_after_field = meza_get_event_context_nested_sub_field($field, 'after');
        $after_field = meza_get_event_context_nested_sub_field($field, 'season_after');

        return is_array($after_field)
            || is_array($base_field)
            || (is_array($before_field) && !is_array($event_after_field));
    }

    function meza_is_combined_context_group_field(array $field): bool
    {
        return meza_is_event_context_group_field($field) && meza_is_season_context_group_field($field);
    }

    function meza_collapse_event_context_field(array $field): array
    {
        $collapsed_field = $field;
        $before_field = meza_get_event_context_nested_sub_field($field, 'before');
        $after_field = meza_get_event_context_nested_sub_field($field, 'after');
        $prototype = $before_field ?? $after_field;

        if (!is_array($prototype)) {
            return $field;
        }

        $collapsed_field = array_merge($collapsed_field, $prototype);
        $collapsed_field['name'] = (string) ($field['name'] ?? ($prototype['name'] ?? ''));
        $collapsed_field['label'] = (string) ($field['label'] ?? ($prototype['label'] ?? ''));
        $collapsed_field['key'] = (string) ($field['key'] ?? ($prototype['key'] ?? ''));
        $collapsed_field['required'] = (int) ($before_field['required'] ?? $prototype['required'] ?? 0);
        $collapsed_field['conditional_logic'] = $field['conditional_logic'] ?? 0;

        unset($collapsed_field['sub_fields'], $collapsed_field['layout']);

        return $collapsed_field;
    }

    function meza_collapse_season_context_field(array $field): array
    {
        $collapsed_field = $field;
        $before_field = meza_get_event_context_nested_sub_field($field, 'before');
        $base_field = meza_get_event_context_nested_sub_field($field, 'base');
        $after_field = meza_get_event_context_nested_sub_field($field, 'season_after');
        $prototype = $before_field ?? $base_field ?? $after_field;

        if (!is_array($prototype)) {
            return $field;
        }

        $collapsed_field = array_merge($collapsed_field, $prototype);
        $collapsed_field['name'] = (string) ($field['name'] ?? ($prototype['name'] ?? ''));
        $collapsed_field['label'] = (string) ($field['label'] ?? ($prototype['label'] ?? ''));
        $collapsed_field['key'] = (string) ($field['key'] ?? ($prototype['key'] ?? ''));
        $collapsed_field['required'] = (int) ($before_field['required'] ?? $base_field['required'] ?? $prototype['required'] ?? 0);
        $collapsed_field['conditional_logic'] = $field['conditional_logic'] ?? 0;

        unset($collapsed_field['sub_fields'], $collapsed_field['layout']);

        return $collapsed_field;
    }

    function meza_collapse_event_context_section_sub_field(array $sub_field): array
    {
        return meza_collapse_event_context_field($sub_field);
    }

    function meza_section_field_value_is_empty($value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (!meza_section_field_value_is_empty($item)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    function meza_get_runtime_normalized_section_group_field(array $field, int $post_id = 0): array
    {
        $field_name = sanitize_key((string) ($field['name'] ?? ''));

        if ((string) ($field['type'] ?? '') !== 'group' || !str_starts_with($field_name, 'section_')) {
            return $field;
        }

        $supports_event_context = meza_runtime_post_supports_event_context($post_id);
        $supports_global_season_context = !is_admin()
            && meza_section_field_name_supports_global_season_context($field_name);
        $supports_season_context = meza_runtime_post_supports_season_context($post_id)
            || $supports_global_season_context;
        $sub_fields = isset($field['sub_fields']) && is_array($field['sub_fields']) ? $field['sub_fields'] : [];

        $sub_fields = meza_normalize_section_sub_fields(
            $sub_fields,
            $field_name,
            '',
            $supports_event_context
        );
        $sub_fields = meza_normalize_event_context_child_fields(
            $sub_fields,
            $supports_event_context,
            ''
        );
        $sub_fields = meza_normalize_season_context_child_fields(
            $sub_fields,
            $supports_season_context,
            '',
            $field_name
        );

        $field['sub_fields'] = meza_apply_section_secondary_link_dependency(
            meza_apply_section_display_dependency($sub_fields)
        );

        return $field;
    }

    function meza_event_context_field_value_is_empty($value): bool
    {
        return meza_section_field_value_is_empty($value);
    }

    function meza_get_textarea_rows_preference(): int
    {
        $has_event_after_content = function_exists('meza_events_have_after_content') && meza_events_have_after_content();
        $has_season_after_content = function_exists('meza_annual_years_have_end_of_year_content') && meza_annual_years_have_end_of_year_content();

        if ($has_event_after_content && $has_season_after_content) {
            return 6;
        }

        if ($has_event_after_content || $has_season_after_content) {
            return 3;
        }

        return 2;
    }

    function meza_has_combined_temporal_content_context(): bool
    {
        return function_exists('meza_events_have_after_content')
            && meza_events_have_after_content()
            && function_exists('meza_annual_years_have_end_of_year_content')
            && meza_annual_years_have_end_of_year_content();
    }

    function meza_get_textarea_role(array $field): string
    {
        $role = sanitize_key((string) ($field['meza_textarea_role'] ?? ''));
        if ($role !== '') {
            return $role;
        }

        $name = sanitize_key((string) ($field['name'] ?? ''));
        if (in_array($name, ['headline', 'display', 'subhead', 'description'], true)) {
            return $name;
        }

        return '';
    }

    function meza_is_context_value_textarea_field(array $field): bool
    {
        $name = sanitize_key((string) ($field['name'] ?? ''));
        $key = sanitize_key((string) ($field['key'] ?? ''));

        if (in_array($name, ['before', 'base', 'after', 'season_after'], true)) {
            return true;
        }

        return str_starts_with($key, 'field_meza_event_context_') || str_starts_with($key, 'field_meza_season_context_');
    }

    function meza_get_textarea_rows_for_field(array $field): int
    {
        $role = meza_get_textarea_role($field);
        $has_combined_context = function_exists('meza_has_combined_temporal_content_context')
            && meza_has_combined_temporal_content_context();

        if (in_array($role, ['headline', 'display'], true)) {
            return $has_combined_context ? 2 : 1;
        }

        if ($role === 'subhead') {
            return $has_combined_context
                ? 3
                : (meza_is_context_value_textarea_field($field) ? 2 : 1);
        }

        if ($role === 'description') {
            return $has_combined_context ? 4 : meza_get_textarea_rows_preference();
        }

        return meza_get_textarea_rows_preference();
    }

    function meza_apply_textarea_rows_to_field_tree(array $field): array
    {
        if ((string) ($field['type'] ?? '') === 'textarea') {
            $field['rows'] = meza_get_textarea_rows_for_field($field);
        }

        $field_type = (string) ($field['type'] ?? '');

        if (in_array($field_type, ['group', 'repeater'], true)) {
            foreach ((array) ($field['sub_fields'] ?? []) as $sub_field_index => $sub_field) {
                if (!is_array($sub_field)) {
                    continue;
                }

                $field['sub_fields'][$sub_field_index] = meza_apply_textarea_rows_to_field_tree($sub_field);
            }
        }

        if ($field_type === 'flexible_content') {
            foreach ((array) ($field['layouts'] ?? []) as $layout_index => $layout) {
                if (!is_array($layout)) {
                    continue;
                }

                foreach ((array) ($layout['sub_fields'] ?? []) as $sub_field_index => $sub_field) {
                    if (!is_array($sub_field)) {
                        continue;
                    }

                    $field['layouts'][$layout_index]['sub_fields'][$sub_field_index] = meza_apply_textarea_rows_to_field_tree($sub_field);
                }
            }
        }

        return $field;
    }

    function meza_normalize_event_context_link_field_value($value): array
    {
        if (is_string($value)) {
            $value = maybe_unserialize($value);
        }

        if (is_array($value)) {
            $url = isset($value['url']) && !is_array($value['url'])
                ? trim((string) $value['url'])
                : '';
            $title = isset($value['title']) && !is_array($value['title'])
                ? trim(wp_strip_all_tags((string) $value['title']))
                : '';
            $target = isset($value['target']) && !is_array($value['target'])
                ? trim((string) $value['target'])
                : '';

            if ($url === '') {
                return [];
            }

            return [
                'url' => $url,
                'title' => $title,
                'target' => $target,
            ];
        }

        $url = is_scalar($value) ? trim((string) $value) : '';
        if ($url === '') {
            return [];
        }

        return [
            'url' => $url,
            'title' => '',
            'target' => '',
        ];
    }

    function meza_merge_event_context_link_field_values(...$candidates): array
    {
        $merged = [];
        $merged_url = '';

        foreach ($candidates as $candidate) {
            $normalized = meza_normalize_event_context_link_field_value($candidate);
            $candidate_url = isset($normalized['url']) ? trim((string) $normalized['url']) : '';

            if ($candidate_url === '') {
                continue;
            }

            if ($merged_url === '') {
                $merged = $normalized;
                $merged_url = $candidate_url;
                continue;
            }

            if ($candidate_url !== $merged_url) {
                continue;
            }

            $candidate_title = isset($normalized['title']) ? trim((string) $normalized['title']) : '';
            $candidate_target = isset($normalized['target']) ? trim((string) $normalized['target']) : '';

            if ((string) ($merged['title'] ?? '') === '' && $candidate_title !== '') {
                $merged['title'] = $candidate_title;
            }

            if ((string) ($merged['target'] ?? '') === '' && $candidate_target !== '') {
                $merged['target'] = $candidate_target;
            }
        }

        if ($merged_url === '') {
            return [];
        }

        return [
            'url' => $merged_url,
            'title' => isset($merged['title']) ? trim((string) $merged['title']) : '',
            'target' => isset($merged['target']) ? trim((string) $merged['target']) : '',
        ];
    }

    function meza_enrich_event_context_link_field_value($value, ...$fallbacks): array
    {
        $normalized = meza_normalize_event_context_link_field_value($value);
        $normalized_url = isset($normalized['url']) ? trim((string) $normalized['url']) : '';

        if ($normalized_url === '') {
            return [];
        }

        return meza_merge_event_context_link_field_values($normalized, ...$fallbacks);
    }

    function meza_normalize_event_context_scalar_field_value($value)
    {
        if (is_string($value)) {
            $maybe_unserialized = maybe_unserialize($value);
            if (is_array($maybe_unserialized)) {
                $value = $maybe_unserialized;
            }
        }

        if (!is_array($value)) {
            return $value;
        }

        foreach (['before', 'base', 'after', 'season_after', 'url', 'value'] as $key) {
            if (array_key_exists($key, $value)) {
                return meza_normalize_event_context_scalar_field_value($value[$key]);
            }
        }

        foreach ($value as $nested_value) {
            $normalized_value = meza_normalize_event_context_scalar_field_value($nested_value);
            if (!meza_section_field_value_is_empty($normalized_value)) {
                return $normalized_value;
            }
        }

        return '';
    }

    function meza_normalize_event_context_relationship_field_value($value)
    {
        if (is_string($value)) {
            $maybe_unserialized = maybe_unserialize($value);
            if (is_array($maybe_unserialized)) {
                $value = $maybe_unserialized;
            }
        }

        if (!is_array($value)) {
            return $value;
        }

        foreach (['before', 'base', 'after', 'season_after', 'value'] as $key) {
            if (array_key_exists($key, $value)) {
                return meza_normalize_event_context_relationship_field_value($value[$key]);
            }
        }

        if (array_key_exists('ID', $value) || array_key_exists('id', $value)) {
            return $value;
        }

        $normalized_value = [];

        foreach ($value as $nested_value) {
            $resolved_nested_value = meza_normalize_event_context_relationship_field_value($nested_value);

            if (meza_section_field_value_is_empty($resolved_nested_value)) {
                continue;
            }

            $normalized_value[] = $resolved_nested_value;
        }

        if ($normalized_value === []) {
            return [];
        }

        return $normalized_value;
    }

    function meza_normalize_event_context_field_value($value, string $field_type)
    {
        if ($field_type === 'link') {
            return meza_normalize_event_context_link_field_value($value);
        }

        if ($field_type === 'relationship') {
            return meza_normalize_event_context_relationship_field_value($value);
        }

        return meza_normalize_event_context_scalar_field_value($value);
    }

    function meza_event_context_slot_has_usable_value($value, string $field_type): bool
    {
        if ($field_type === 'relationship') {
            if ($value === false || $value === 0 || $value === '0') {
                return false;
            }
        }

        return !meza_section_field_value_is_empty($value);
    }

    function meza_section_sub_field_supports_event_context(array $sub_field): bool
    {
        return meza_field_supports_event_context($sub_field);
    }

    function meza_field_is_event_context_exempt(array $field): bool
    {
        $name = sanitize_key((string) ($field['name'] ?? ''));

        if (in_array($name, ['id', 'disclaimer'], true)) {
            return true;
        }

        return false;
    }

    function meza_field_supports_event_context(array $field): bool
    {
        $name = sanitize_key((string) ($field['name'] ?? ''));
        $type = (string) ($field['type'] ?? '');

        if (
            $name === ''
            || str_ends_with($name, '_after')
            || str_ends_with($name, '_season_after')
            || meza_field_is_event_context_exempt($field)
        ) {
            return false;
        }

        if (meza_relationship_field_supports_event_context($field)) {
            return true;
        }

        if (in_array($type, meza_get_event_context_supported_field_types(), true)) {
            return true;
        }

        return meza_is_event_context_group_field($field);
    }

    function meza_field_supports_season_context(array $field): bool
    {
        $name = sanitize_key((string) ($field['name'] ?? ''));
        $type = (string) ($field['type'] ?? '');

        if (
            $name === ''
            || str_ends_with($name, '_season_after')
            || str_ends_with($name, '_after')
            || meza_field_is_event_context_exempt($field)
        ) {
            return false;
        }

        if (meza_relationship_field_supports_event_context($field)) {
            return true;
        }

        if (in_array($type, meza_get_event_context_supported_field_types(), true)) {
            return true;
        }

        return meza_is_season_context_group_field($field) || meza_is_event_context_group_field($field);
    }

    function meza_season_context_is_whitelisted_field(array $field, string $group_key = '', string $section_field_name = ''): bool
    {
        $group_key = sanitize_key($group_key);
        $section_field_name = sanitize_key($section_field_name);
        $field_name = sanitize_key((string) ($field['name'] ?? ''));

        if ($section_field_name === '') {
            $section_field_name = meza_get_header_section_group_section_field_names()[$group_key] ?? '';
        }

        if ($section_field_name === 'section_list-events') {
            return in_array($field_name, ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary'], true);
        }

        if ($section_field_name === 'section_list-concerts') {
            return in_array($field_name, ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary'], true);
        }

        if ($section_field_name === 'section_cta') {
            return $field_name === 'cta';
        }

        if ($group_key === 'group_meza_list_events_section') {
            return in_array($field_name, ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary'], true);
        }

        if ($group_key === 'group_68f9071f79ef0') {
            return in_array($field_name, ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary'], true);
        }

        if ($group_key === 'group_f7b16b48') {
            return $field_name === 'cta';
        }

        if ($group_key === 'group_685d8cc3cda2a') {
            return in_array($field_name, ['description', 'link', 'link_secondary'], true);
        }

        return false;
    }

    function meza_build_event_context_value_field(
        array $prototype,
        string $context,
        ?array $existing_field = null,
        string $before_key = ''
    ): array {
        $field = is_array($existing_field) ? array_merge($prototype, $existing_field) : $prototype;

        $generated_key = meza_get_event_context_value_field_key((string) ($prototype['key'] ?? ''), $context);
        if ($generated_key !== '') {
            $field['key'] = $generated_key;
        }
        $field['label'] = $context === 'after' ? 'Post-Event' : 'Pre-Event';
        $field['name'] = $context;
        $field['_name'] = $context;
        $field['required'] = $context === 'before' ? (int) ($prototype['required'] ?? 0) : 0;
        $field['conditional_logic'] = $context === 'after' && $before_key !== ''
            ? [
                [
                    [
                        'field' => $before_key,
                        'operator' => '!=empty',
                    ],
                ],
            ]
            : 0;

        if (array_key_exists('default_value', $field) && $context === 'after') {
            $field['default_value'] = '';
        }

        if ($context === 'after' && array_key_exists('min', $field)) {
            $field['min'] = '';
        }

        if ((string) ($field['type'] ?? '') === 'textarea') {
            $field['rows'] = meza_get_textarea_rows_for_field($field);
        }

        return $field;
    }

    function meza_build_season_context_value_field(
        array $prototype,
        string $context,
        ?array $existing_field = null,
        string $base_key = ''
    ): array {
        $field = is_array($existing_field) ? array_merge($prototype, $existing_field) : $prototype;

        $generated_key = meza_get_season_context_value_field_key((string) ($prototype['key'] ?? ''), $context);
        if ($generated_key !== '') {
            $field['key'] = $generated_key;
        }

        $field['label'] = $context === 'season_after' ? 'End-of-Year' : 'Active Year';
        $field['name'] = $context === 'season_after' ? 'season_after' : 'before';
        $field['_name'] = $field['name'];
        $field['required'] = $context === 'before' ? (int) ($prototype['required'] ?? 0) : 0;
        $field['conditional_logic'] = 0;

        if (array_key_exists('default_value', $field) && $context === 'season_after') {
            $field['default_value'] = '';
        }

        if ((string) ($field['type'] ?? '') === 'textarea') {
            $field['rows'] = meza_get_textarea_rows_for_field($field);
        }

        return $field;
    }

    function meza_get_event_context_field_key_map(array $source_field, array $normalized_field, bool $supports_event_context): array
    {
        $key_map = [];

        if ($supports_event_context) {
            if (!meza_is_event_context_group_field($normalized_field)) {
                return $key_map;
            }

            $before_field = meza_get_event_context_nested_sub_field($normalized_field, 'before');
            $after_field = meza_get_event_context_nested_sub_field($normalized_field, 'after');
            $before_key = is_array($before_field) ? (string) ($before_field['key'] ?? '') : '';
            if ($before_key === '') {
                return $key_map;
            }

            $source_key = (string) ($source_field['key'] ?? '');
            $normalized_key = (string) ($normalized_field['key'] ?? '');
            $after_key = is_array($after_field) ? (string) ($after_field['key'] ?? '') : '';

            foreach ([$source_key, $normalized_key, $after_key] as $candidate_key) {
                if ($candidate_key !== '' && $candidate_key !== $before_key) {
                    $key_map[$candidate_key] = $before_key;
                }
            }

            return $key_map;
        }

        if (!meza_is_event_context_group_field($source_field)) {
            return $key_map;
        }

        $collapsed_key = (string) ($normalized_field['key'] ?? '');
        if ($collapsed_key === '') {
            return $key_map;
        }

        $before_field = meza_get_event_context_nested_sub_field($source_field, 'before');
        $after_field = meza_get_event_context_nested_sub_field($source_field, 'after');
        $source_key = (string) ($source_field['key'] ?? '');
        $before_key = is_array($before_field) ? (string) ($before_field['key'] ?? '') : '';
        $after_key = is_array($after_field) ? (string) ($after_field['key'] ?? '') : '';

        foreach ([$source_key, $before_key, $after_key] as $candidate_key) {
            if ($candidate_key !== '' && $candidate_key !== $collapsed_key) {
                $key_map[$candidate_key] = $collapsed_key;
            }
        }

        return $key_map;
    }

    function meza_get_season_context_field_key_map(array $source_field, array $normalized_field, bool $supports_season_context): array
    {
        $key_map = [];

        if ($supports_season_context) {
            if (!meza_is_season_context_group_field($normalized_field)) {
                return $key_map;
            }

            $before_field = meza_get_event_context_nested_sub_field($normalized_field, 'before')
                ?? meza_get_event_context_nested_sub_field($normalized_field, 'base');
            $season_after_field = meza_get_event_context_nested_sub_field($normalized_field, 'season_after');
            $event_after_field = meza_get_event_context_nested_sub_field($normalized_field, 'after');
            $before_key = is_array($before_field) ? (string) ($before_field['key'] ?? '') : '';

            if ($before_key === '') {
                return $key_map;
            }

            $source_key = (string) ($source_field['key'] ?? '');
            $normalized_key = (string) ($normalized_field['key'] ?? '');
            $season_after_key = is_array($season_after_field) ? (string) ($season_after_field['key'] ?? '') : '';
            $event_after_key = is_array($event_after_field) ? (string) ($event_after_field['key'] ?? '') : '';

            foreach ([$source_key, $normalized_key, $season_after_key, $event_after_key] as $candidate_key) {
                if ($candidate_key !== '' && $candidate_key !== $before_key) {
                    $key_map[$candidate_key] = $before_key;
                }
            }

            return $key_map;
        }

        if (!meza_is_season_context_group_field($source_field)) {
            return $key_map;
        }

        $collapsed_key = (string) ($normalized_field['key'] ?? '');
        if ($collapsed_key === '') {
            return $key_map;
        }

        $before_field = meza_get_event_context_nested_sub_field($source_field, 'before')
            ?? meza_get_event_context_nested_sub_field($source_field, 'base');
        $season_after_field = meza_get_event_context_nested_sub_field($source_field, 'season_after');
        $event_after_field = meza_get_event_context_nested_sub_field($source_field, 'after');
        $source_key = (string) ($source_field['key'] ?? '');
        $before_key = is_array($before_field) ? (string) ($before_field['key'] ?? '') : '';
        $season_after_key = is_array($season_after_field) ? (string) ($season_after_field['key'] ?? '') : '';
        $event_after_key = is_array($event_after_field) ? (string) ($event_after_field['key'] ?? '') : '';

        foreach ([$source_key, $before_key, $season_after_key, $event_after_key] as $candidate_key) {
            if ($candidate_key !== '' && $candidate_key !== $collapsed_key) {
                $key_map[$candidate_key] = $collapsed_key;
            }
        }

        return $key_map;
    }

    function meza_remap_event_context_conditional_logic($conditional_logic, array $key_map)
    {
        if (!is_array($conditional_logic) || $key_map === []) {
            return $conditional_logic;
        }

        foreach ($conditional_logic as $group_index => $group) {
            if (!is_array($group)) {
                continue;
            }

            foreach ($group as $rule_index => $rule) {
                if (!is_array($rule)) {
                    continue;
                }

                $field_key = (string) ($rule['field'] ?? '');
                if ($field_key !== '' && isset($key_map[$field_key])) {
                    $conditional_logic[$group_index][$rule_index]['field'] = $key_map[$field_key];
                }
            }
        }

        return $conditional_logic;
    }

    function meza_remap_event_context_conditional_logic_in_field_tree(array $field, array $key_map): array
    {
        if ($key_map !== []) {
            $field['conditional_logic'] = meza_remap_event_context_conditional_logic(
                $field['conditional_logic'] ?? 0,
                $key_map
            );
        }

        $field_type = (string) ($field['type'] ?? '');

        if (in_array($field_type, ['group', 'repeater'], true)) {
            foreach ((array) ($field['sub_fields'] ?? []) as $sub_field_index => $sub_field) {
                if (!is_array($sub_field)) {
                    continue;
                }

                $field['sub_fields'][$sub_field_index] = meza_remap_event_context_conditional_logic_in_field_tree(
                    $sub_field,
                    $key_map
                );
            }
        }

        if ($field_type === 'flexible_content') {
            foreach ((array) ($field['layouts'] ?? []) as $layout_index => $layout) {
                if (!is_array($layout)) {
                    continue;
                }

                foreach ((array) ($layout['sub_fields'] ?? []) as $sub_field_index => $sub_field) {
                    if (!is_array($sub_field)) {
                        continue;
                    }

                    $field['layouts'][$layout_index]['sub_fields'][$sub_field_index] = meza_remap_event_context_conditional_logic_in_field_tree(
                        $sub_field,
                        $key_map
                    );
                }
            }
        }

        return $field;
    }

    function meza_build_event_context_field(
        array $field,
        ?array $existing_after_field = null
    ): array {
        $field_type = (string) ($field['type'] ?? '');
        $existing_before_field = null;
        $existing_after_group_field = null;
        $prototype = $field;

        if ($field_type === 'group') {
            $existing_before_field = meza_get_event_context_nested_sub_field($field, 'before');
            $existing_after_group_field = meza_get_event_context_nested_sub_field($field, 'after');
            $prototype = $existing_before_field ?? $existing_after_group_field ?? $field;
        }

        $before_field = meza_build_event_context_value_field(
            $prototype,
            'before',
            $existing_before_field
        );
        $after_field = meza_build_event_context_value_field(
            $prototype,
            'after',
            $existing_after_group_field ?? $existing_after_field,
            (string) ($before_field['key'] ?? '')
        );

        $group_field = $field;
        $group_field['type'] = 'group';
        $group_field['layout'] = 'table';
        $group_field['required'] = 0;
        $group_field['_name'] = (string) ($field['_name'] ?? ($field['name'] ?? ''));
        $group_field['conditional_logic'] = $field['conditional_logic'] ?? 0;
        $group_field['sub_fields'] = [$before_field, $after_field];

        unset(
            $group_field['default_value'],
            $group_field['maxlength'],
            $group_field['placeholder'],
            $group_field['prepend'],
            $group_field['append'],
            $group_field['return_format'],
            $group_field['rows'],
            $group_field['new_lines'],
            $group_field['preview_size'],
            $group_field['library'],
            $group_field['min_width'],
            $group_field['min_height'],
            $group_field['min_size'],
            $group_field['max_width'],
            $group_field['max_height'],
            $group_field['max_size'],
            $group_field['mime_types']
        );

        return $group_field;
    }

    function meza_build_context_tab_field(string $field_key, string $label, string $suffix): array
    {
        return [
            'key' => sanitize_key($field_key . '_' . $suffix . '_tab'),
            'label' => $label,
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
        ];
    }

    function meza_build_season_context_field(
        array $field,
        ?array $existing_after_field = null
    ): array {
        $field_type = (string) ($field['type'] ?? '');
        $existing_before_field = null;
        $existing_base_field = null;
        $existing_after_group_field = null;
        $prototype = $field;

        if ($field_type === 'group') {
            $existing_before_field = meza_get_event_context_nested_sub_field($field, 'before');
            $existing_base_field = meza_get_event_context_nested_sub_field($field, 'base');
            $existing_after_group_field = meza_get_event_context_nested_sub_field($field, 'season_after');
            if (meza_is_event_context_group_field($field)) {
                $event_before_field = meza_get_event_context_nested_sub_field($field, 'before');
                $event_after_field = meza_get_event_context_nested_sub_field($field, 'after');
                $prototype = $existing_before_field ?? $existing_base_field ?? $event_before_field ?? $event_after_field ?? $existing_after_group_field ?? $field;
            } else {
                $prototype = $existing_before_field ?? $existing_base_field ?? $existing_after_group_field ?? $field;
            }
        }

        $before_field = meza_build_season_context_value_field(
            $prototype,
            'before',
            $existing_before_field ?? $existing_base_field
        );
        $after_field = meza_build_season_context_value_field(
            $prototype,
            'season_after',
            $existing_after_group_field ?? $existing_after_field,
            (string) ($before_field['key'] ?? '')
        );

        $group_field = $field;
        $group_field['type'] = 'group';
        $group_field['layout'] = 'table';
        $group_field['required'] = 0;
        $group_field['_name'] = (string) ($field['_name'] ?? ($field['name'] ?? ''));
        $group_field['conditional_logic'] = $field['conditional_logic'] ?? 0;
        $group_field['sub_fields'] = [$before_field, $after_field];

        unset(
            $group_field['default_value'],
            $group_field['maxlength'],
            $group_field['placeholder'],
            $group_field['prepend'],
            $group_field['append'],
            $group_field['return_format'],
            $group_field['rows'],
            $group_field['new_lines'],
            $group_field['preview_size'],
            $group_field['library'],
            $group_field['min_width'],
            $group_field['min_height'],
            $group_field['min_size'],
            $group_field['max_width'],
            $group_field['max_height'],
            $group_field['max_size'],
            $group_field['mime_types']
        );

        return $group_field;
    }

    function meza_build_combined_context_field(
        array $field,
        ?array $existing_event_after_field = null,
        ?array $existing_season_after_field = null
    ): array {
        $event_group = meza_build_event_context_field($field, $existing_event_after_field);
        $season_group = meza_build_season_context_field($field, $existing_season_after_field);
        $before_field = meza_get_event_context_nested_sub_field($event_group, 'before');
        $after_field = meza_get_event_context_nested_sub_field($event_group, 'after');
        $season_after_field = meza_get_event_context_nested_sub_field($season_group, 'season_after');

        $group_field = $field;
        $group_field['type'] = 'group';
        $group_field['layout'] = 'table';
        $group_field['required'] = 0;
        $group_field['_name'] = (string) ($field['_name'] ?? ($field['name'] ?? ''));
        $group_field['conditional_logic'] = $field['conditional_logic'] ?? 0;
        $group_field['sub_fields'] = array_values(array_filter([
            $before_field,
            $after_field,
            $season_after_field,
        ], 'is_array'));

        unset(
            $group_field['default_value'],
            $group_field['maxlength'],
            $group_field['placeholder'],
            $group_field['prepend'],
            $group_field['append'],
            $group_field['return_format'],
            $group_field['rows'],
            $group_field['new_lines'],
            $group_field['preview_size'],
            $group_field['library'],
            $group_field['min_width'],
            $group_field['min_height'],
            $group_field['min_size'],
            $group_field['max_width'],
            $group_field['max_height'],
            $group_field['max_size'],
            $group_field['mime_types']
        );

        return $group_field;
    }

    function meza_build_event_context_section_sub_field(
        array $sub_field,
        string $section_field_name,
        ?array $existing_after_field = null
    ): array {
        $group_field = meza_build_event_context_field($sub_field, $existing_after_field);
        $base_name = sanitize_key((string) ($sub_field['name'] ?? ''));
        $before_field = meza_get_event_context_nested_sub_field($group_field, 'before');
        $after_field = meza_get_event_context_nested_sub_field($group_field, 'after');

        if (is_array($before_field)) {
            $before_field['key'] = meza_get_section_sub_field_stable_key($section_field_name, $base_name . '_before');
        }
        if (is_array($after_field)) {
            $after_field['key'] = meza_get_section_sub_field_stable_key($section_field_name, $base_name . '_after');
        }

        $group_field['sub_fields'] = array_values(array_filter([$before_field, $after_field], 'is_array'));

        return $group_field;
    }

    function meza_normalize_group_value_for_acf_update($value, array $field)
    {
        if (!is_array($value) || empty($field['sub_fields']) || !is_array($field['sub_fields'])) {
            return $value;
        }

        foreach ($field['sub_fields'] as $sub_field) {
            if (!is_array($sub_field)) {
                continue;
            }

            $sub_key = (string) ($sub_field['key'] ?? '');
            $sub_name = (string) ($sub_field['_name'] ?? ($sub_field['name'] ?? ''));

            if ($sub_name === '') {
                continue;
            }

            $has_key_value = $sub_key !== '' && array_key_exists($sub_key, $value);
            $has_name_value = array_key_exists($sub_name, $value);
            $key_value = $has_key_value ? $value[$sub_key] : null;
            $name_value = $has_name_value ? $value[$sub_name] : null;

            if ($has_name_value && (!$has_key_value || $key_value !== $name_value)) {
                $value[$sub_key] = $name_value;
                $key_value = $name_value;
                $has_key_value = true;
            }

            $nested_value = $has_key_value ? $key_value : ($has_name_value ? $name_value : null);
            if (!is_array($nested_value) || empty($sub_field['sub_fields']) || !is_array($sub_field['sub_fields'])) {
                continue;
            }

            $nested_value = meza_normalize_group_value_for_acf_update($nested_value, $sub_field);

            if ($sub_key !== '') {
                $value[$sub_key] = $nested_value;
            }

            if ($has_name_value) {
                $value[$sub_name] = $nested_value;
            }
        }

        return $value;
    }

    function meza_get_event_context_legacy_meta_value(int $post_id, array $path_segments, string $context = 'before')
    {
        if ($post_id <= 0) {
            return null;
        }

        $normalized_segments = [];

        foreach ($path_segments as $segment) {
            $normalized_segment = sanitize_key((string) $segment);
            if ($normalized_segment === '' && (string) $segment !== '0') {
                continue;
            }

            $normalized_segments[] = $normalized_segment;
        }

        if ($normalized_segments === []) {
            return null;
        }

        $meta_key = implode('_', $normalized_segments);
        if ($context !== 'before') {
            $meta_key .= '_' . $context;
        }

        if (!metadata_exists('post', $post_id, $meta_key)) {
            return null;
        }

        return get_post_meta($post_id, $meta_key, true);
    }

    function meza_get_section_legacy_meta_value(int $post_id, string $section_field_name, string $sub_field_name, string $context = 'before')
    {
        if ($section_field_name === '' || $sub_field_name === '') {
            return null;
        }

        return meza_get_event_context_legacy_meta_value($post_id, [$section_field_name, $sub_field_name], $context);
    }

    function meza_build_context_group_value(array $field, array $values): array
    {
        $value = $values;

        foreach ((array) ($field['sub_fields'] ?? []) as $sub_field) {
            if (!is_array($sub_field)) {
                continue;
            }

            $sub_field_name = sanitize_key((string) ($sub_field['name'] ?? ''));
            $sub_field_key = (string) ($sub_field['key'] ?? '');
            if ($sub_field_key === '') {
                continue;
            }

            if (array_key_exists($sub_field_name, $values)) {
                $value[$sub_field_key] = $values[$sub_field_name];
            }
        }

        return $value;
    }

    function meza_get_field_tree_child_value(array $container, array $field)
    {
        $field_name = sanitize_key((string) ($field['name'] ?? ''));
        $field_key = (string) ($field['key'] ?? '');

        if ($field_name !== '' && array_key_exists($field_name, $container)) {
            return $container[$field_name];
        }

        if ($field_key !== '' && array_key_exists($field_key, $container)) {
            return $container[$field_key];
        }

        return null;
    }

    function meza_set_field_tree_child_value(array $container, array $field, $value): array
    {
        $field_name = sanitize_key((string) ($field['name'] ?? ''));
        $field_key = (string) ($field['key'] ?? '');

        if ($field_name !== '') {
            $container[$field_name] = $value;
        }

        if ($field_key !== '') {
            $container[$field_key] = $value;
        }

        return $container;
    }

    function meza_get_context_group_slot_value(array $value, array $field, string $slot_name)
    {
        $normalized_slot_name = sanitize_key($slot_name);

        if ($normalized_slot_name !== '' && array_key_exists($normalized_slot_name, $value)) {
            return $value[$normalized_slot_name];
        }

        $slot_field = meza_get_event_context_nested_sub_field($field, $normalized_slot_name);
        if (!is_array($slot_field)) {
            return null;
        }

        $slot_field_key = (string) ($slot_field['key'] ?? '');
        if ($slot_field_key !== '' && array_key_exists($slot_field_key, $value)) {
            return $value[$slot_field_key];
        }

        return null;
    }

    function meza_resolve_event_context_leaf_value($value, array $field, int $post_id, bool $flatten_for_frontend, array $legacy_path_segments = [])
    {
        $before_value = null;
        $after_value = null;
        $base_value = null;
        $season_after_value = null;
        $legacy_before_value = null;
        $legacy_after_value = null;
        $legacy_base_value = null;
        $legacy_default_value = null;
        $legacy_season_after_value = null;
        $field_type = meza_get_event_context_effective_field_type($field);
        $has_event_context = meza_is_event_context_group_field($field);
        $has_season_context = meza_is_season_context_group_field($field);
        $season_uses_before_slot = $has_season_context && is_array(meza_get_event_context_nested_sub_field($field, 'before'));

        if (is_array($value)) {
            $before_value = meza_get_context_group_slot_value($value, $field, 'before');
            $after_value = meza_get_context_group_slot_value($value, $field, 'after');
            $base_value = meza_get_context_group_slot_value($value, $field, 'base');
            $season_after_value = meza_get_context_group_slot_value($value, $field, 'season_after');

            $has_explicit_context_slots =
                !meza_section_field_value_is_empty($before_value)
                || !meza_section_field_value_is_empty($after_value)
                || !meza_section_field_value_is_empty($base_value)
                || !meza_section_field_value_is_empty($season_after_value);

            if (!$has_explicit_context_slots) {
                if ($has_season_context && !$has_event_context) {
                    $base_value = $value;
                } else {
                    $before_value = $value;
                }
            }
        } elseif (!meza_section_field_value_is_empty($value)) {
            if ($has_season_context && !$has_event_context) {
                $base_value = $value;
            } else {
                $before_value = $value;
            }
        }

        if ($post_id > 0 && $legacy_path_segments !== []) {
            $legacy_default_value = meza_get_event_context_legacy_meta_value($post_id, $legacy_path_segments);
            $legacy_before_value = $has_event_context
                ? meza_get_event_context_legacy_meta_value($post_id, $legacy_path_segments, 'before')
                : null;
            $legacy_after_value = $has_event_context
                ? meza_get_event_context_legacy_meta_value($post_id, $legacy_path_segments, 'after')
                : null;
            $legacy_base_value = $has_season_context
                ? meza_get_event_context_legacy_meta_value($post_id, $legacy_path_segments, 'base')
                : null;
            $legacy_season_after_value = $has_season_context
                ? meza_get_event_context_legacy_meta_value($post_id, $legacy_path_segments, 'season_after')
                : null;
        }

        if ($has_event_context && !meza_event_context_slot_has_usable_value($before_value, $field_type)) {
            if ($legacy_before_value !== null) {
                $before_value = $legacy_before_value;
            }
        }

        if ($has_event_context && !meza_event_context_slot_has_usable_value($after_value, $field_type)) {
            if ($legacy_after_value !== null) {
                $after_value = $legacy_after_value;
            }
        }

        if ($has_season_context && !meza_event_context_slot_has_usable_value($base_value, $field_type)) {
            $legacy_base_value = $legacy_before_value;
            if ($legacy_base_value === null) {
                $legacy_base_value = meza_get_event_context_legacy_meta_value($post_id, $legacy_path_segments, 'base');
            }
            if ($legacy_base_value === null) {
                $legacy_base_value = $legacy_default_value;
            }
            if ($legacy_base_value !== null) {
                $base_value = $legacy_base_value;
            }
        }

        if ($has_season_context && !meza_event_context_slot_has_usable_value($season_after_value, $field_type)) {
            if ($legacy_season_after_value !== null) {
                $season_after_value = $legacy_season_after_value;
            }
        }

        if ($has_season_context && meza_section_field_value_is_empty($base_value) && !meza_section_field_value_is_empty($before_value)) {
            $base_value = $before_value;
        }

        if (
            $has_season_context
            && $season_uses_before_slot
            && meza_section_field_value_is_empty($before_value)
            && !meza_section_field_value_is_empty($base_value)
        ) {
            $before_value = $base_value;
        }

        if ($field_type === 'link') {
            $before_value = meza_merge_event_context_link_field_values(
                $before_value,
                $legacy_before_value,
                $legacy_base_value,
                $legacy_default_value
            );
            $after_value = meza_enrich_event_context_link_field_value(
                $after_value,
                $legacy_after_value
            );
            $base_value = meza_merge_event_context_link_field_values(
                $base_value,
                $legacy_base_value,
                $legacy_before_value,
                $legacy_default_value
            );
            $season_after_value = meza_enrich_event_context_link_field_value(
                $season_after_value,
                $legacy_season_after_value
            );
        } else {
            $before_value = meza_normalize_event_context_field_value($before_value, $field_type);
            $after_value = meza_normalize_event_context_field_value($after_value, $field_type);
            $base_value = meza_normalize_event_context_field_value($base_value, $field_type);
            $season_after_value = meza_normalize_event_context_field_value($season_after_value, $field_type);
        }

        if (!$flatten_for_frontend) {
            return meza_build_context_group_value($field, array_filter([
                'before' => $before_value,
                'after' => $after_value,
                'base' => $base_value,
                'season_after' => $season_after_value,
            ], static function ($item, $key) use ($has_event_context, $has_season_context, $season_uses_before_slot): bool {
                if ($key === 'before') {
                    return $has_event_context || $season_uses_before_slot;
                }

                if ($key === 'after') {
                    return $has_event_context;
                }

                if ($key === 'base') {
                    return $has_season_context && !$season_uses_before_slot;
                }

                if ($key === 'season_after') {
                    return $has_season_context;
                }

                return true;
            }, ARRAY_FILTER_USE_BOTH));
        }

        if ($has_event_context && get_post_type($post_id) === 'event') {
            if (meza_is_past_event_post($post_id) && !meza_section_field_value_is_empty($after_value)) {
                return $after_value;
            }

            if (!meza_section_field_value_is_empty($before_value)) {
                return $before_value;
            }
        }

        if ($has_season_context) {
            $is_post_season = function_exists('meza_is_post_season_content_context')
                ? meza_is_post_season_content_context($post_id)
                : (bool) apply_filters('meza_is_post_season_context', false, $post_id, $field);
            if ($is_post_season && !meza_section_field_value_is_empty($season_after_value)) {
                return $season_after_value;
            }

            if (!meza_section_field_value_is_empty($base_value)) {
                return $base_value;
            }
        }

        return $before_value;
    }

    function meza_resolve_event_context_section_sub_field_value($value, array $sub_field, int $post_id, bool $flatten_for_frontend)
    {
        $sub_field_name = sanitize_key((string) ($sub_field['name'] ?? ''));
        $section_field_name = sanitize_key((string) ($sub_field['parent'] ?? ''));
        if ($section_field_name === '') {
            $section_field_name = sanitize_key((string) ($sub_field['prefix'] ?? ''));
        }

        return meza_resolve_event_context_leaf_value(
            $value,
            $sub_field,
            $post_id,
            $flatten_for_frontend,
            [$section_field_name, $sub_field_name]
        );
    }

    function meza_field_tree_has_event_context(array $field): bool
    {
        if (meza_is_event_context_group_field($field)) {
            return true;
        }

        $field_type = (string) ($field['type'] ?? '');
        if (in_array($field_type, ['group', 'repeater'], true)) {
            foreach ((array) ($field['sub_fields'] ?? []) as $sub_field) {
                if (is_array($sub_field) && meza_field_tree_has_event_context($sub_field)) {
                    return true;
                }
            }
        }

        if ($field_type === 'flexible_content') {
            foreach ((array) ($field['layouts'] ?? []) as $layout) {
                if (!is_array($layout)) {
                    continue;
                }

                foreach ((array) ($layout['sub_fields'] ?? []) as $sub_field) {
                    if (is_array($sub_field) && meza_field_tree_has_event_context($sub_field)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    function meza_resolve_event_aware_field_tree_value($value, array $field, int $post_id, bool $flatten_for_frontend, array $path_segments = [])
    {
        if (meza_is_event_context_group_field($field)) {
            return meza_resolve_event_context_leaf_value(
                $value,
                $field,
                $post_id,
                $flatten_for_frontend,
                $path_segments
            );
        }

        $field_type = (string) ($field['type'] ?? '');
        if ($field_type === 'group') {
            $resolved_value = is_array($value) ? $value : [];

            foreach ((array) ($field['sub_fields'] ?? []) as $sub_field) {
                if (!is_array($sub_field)) {
                    continue;
                }

                $sub_field_name = sanitize_key((string) ($sub_field['name'] ?? ''));
                if ($sub_field_name === '') {
                    continue;
                }

                $child_value = meza_get_field_tree_child_value($resolved_value, $sub_field);

                $child_value = meza_resolve_event_aware_field_tree_value(
                    $child_value,
                    $sub_field,
                    $post_id,
                    $flatten_for_frontend,
                    array_merge($path_segments, [$sub_field_name])
                );

                $resolved_value = meza_set_field_tree_child_value($resolved_value, $sub_field, $child_value);
            }

            return $resolved_value;
        }

        if ($field_type === 'repeater') {
            if (!is_array($value)) {
                return $value;
            }

            foreach ($value as $row_index => $row_value) {
                if (!is_array($row_value)) {
                    continue;
                }

                foreach ((array) ($field['sub_fields'] ?? []) as $sub_field) {
                    if (!is_array($sub_field)) {
                        continue;
                    }

                    $sub_field_name = sanitize_key((string) ($sub_field['name'] ?? ''));
                    if ($sub_field_name === '') {
                        continue;
                    }

                    $child_value = meza_get_field_tree_child_value($row_value, $sub_field);

                    $child_value = meza_resolve_event_aware_field_tree_value(
                        $child_value,
                        $sub_field,
                        $post_id,
                        $flatten_for_frontend,
                        array_merge($path_segments, [(string) $row_index, $sub_field_name])
                    );

                    $value[$row_index] = meza_set_field_tree_child_value($value[$row_index], $sub_field, $child_value);
                }
            }

            return $value;
        }

        if ($field_type === 'flexible_content') {
            if (!is_array($value)) {
                return $value;
            }

            $layouts_by_name = [];
            foreach ((array) ($field['layouts'] ?? []) as $layout) {
                if (!is_array($layout)) {
                    continue;
                }

                $layout_name = sanitize_key((string) ($layout['name'] ?? ''));
                if ($layout_name !== '') {
                    $layouts_by_name[$layout_name] = $layout;
                }
            }

            foreach ($value as $row_index => $row_value) {
                if (!is_array($row_value)) {
                    continue;
                }

                $layout_name = sanitize_key((string) ($row_value['acf_fc_layout'] ?? ''));
                if ($layout_name === '' || !isset($layouts_by_name[$layout_name])) {
                    continue;
                }

                foreach ((array) ($layouts_by_name[$layout_name]['sub_fields'] ?? []) as $sub_field) {
                    if (!is_array($sub_field)) {
                        continue;
                    }

                    $sub_field_name = sanitize_key((string) ($sub_field['name'] ?? ''));
                    if ($sub_field_name === '') {
                        continue;
                    }

                    $child_value = meza_get_field_tree_child_value($row_value, $sub_field);

                    $child_value = meza_resolve_event_aware_field_tree_value(
                        $child_value,
                        $sub_field,
                        $post_id,
                        $flatten_for_frontend,
                        array_merge($path_segments, [(string) $row_index, $sub_field_name])
                    );

                    $value[$row_index] = meza_set_field_tree_child_value($value[$row_index], $sub_field, $child_value);
                }
            }

            return $value;
        }

        return $value;
    }

    function meza_is_past_event_post(int $post_id): bool
    {
        if ($post_id <= 0 || get_post_type($post_id) !== 'event') {
            return false;
        }

        $now = current_time('timestamp');
        $candidate_end_timestamp = 0;

        if (function_exists('\MZ\Recurring\mz_get_event_schedule')) {
            $schedule = \MZ\Recurring\mz_get_event_schedule($post_id);
            if (is_array($schedule) && array_key_exists('is_past', $schedule)) {
                if (!empty($schedule['is_past'])) {
                    return true;
                }

                $end_object = $schedule['end_obj'] ?? null;
                if ($end_object instanceof DateTimeInterface) {
                    $candidate_end_timestamp = $end_object->getTimestamp();
                } elseif (!empty($schedule['upcoming_until_mysql'])) {
                    $candidate_end_timestamp = (int) strtotime((string) $schedule['upcoming_until_mysql']);
                } elseif (!empty($schedule['end_datetime'])) {
                    $candidate_end_timestamp = (int) strtotime((string) $schedule['end_datetime']);
                } elseif (!empty($schedule['date_ymd'])) {
                    $candidate_end_timestamp = (int) strtotime((string) $schedule['date_ymd'] . ' 23:59:59');
                }
            }
        }

        $upcoming_until = trim((string) get_post_meta($post_id, 'upcoming_until', true));
        if ($upcoming_until !== '') {
            $upcoming_until_timestamp = strtotime($upcoming_until);
            if ($upcoming_until_timestamp !== false) {
                $candidate_end_timestamp = max($candidate_end_timestamp, (int) $upcoming_until_timestamp);
            }
        }

        $date_end = trim((string) get_post_meta($post_id, 'date_end', true));
        if ($date_end !== '') {
            $date_end_timestamp = strtotime($date_end . ' 23:59:59');
            if ($date_end_timestamp !== false) {
                $candidate_end_timestamp = max($candidate_end_timestamp, (int) $date_end_timestamp);
            }
        }

        $date_start = trim((string) get_post_meta($post_id, 'date_start', true));
        if ($candidate_end_timestamp <= 0 && $date_start !== '') {
            $date_start_timestamp = strtotime($date_start . ' 23:59:59');
            if ($date_start_timestamp !== false) {
                $candidate_end_timestamp = (int) $date_start_timestamp;
            }
        }

        return $candidate_end_timestamp > 0 && $candidate_end_timestamp < $now;
    }

    function meza_is_event_aware_section_group_value($value, $post_id, $field): bool
    {
        $resolved_post_id = is_numeric($post_id) ? (int) $post_id : 0;
        $field = is_array($field)
            ? meza_get_runtime_normalized_section_group_field($field, $resolved_post_id)
            : $field;
        $has_event_context = function_exists('meza_events_have_after_content')
            && meza_events_have_after_content()
            && is_array($field)
            && meza_field_tree_has_event_context($field)
            && $resolved_post_id > 0
            && get_post_type($resolved_post_id) === 'event';
        $has_season_context = function_exists('meza_annual_years_have_end_of_year_content')
            && meza_annual_years_have_end_of_year_content()
            && is_array($field)
            && meza_fields_have_season_context([$field]);

        return $has_event_context || $has_season_context;
    }

    function meza_resolve_event_aware_section_field_value($value, $post_id, $field)
    {
        $resolved_post_id = is_numeric($post_id) ? (int) $post_id : 0;
        $field = is_array($field)
            ? meza_get_runtime_normalized_section_group_field($field, $resolved_post_id)
            : $field;

        if (!meza_is_event_aware_section_group_value($value, $resolved_post_id, $field)) {
            return $value;
        }

        return meza_resolve_event_aware_field_tree_value(
            $value,
            (array) $field,
            $resolved_post_id,
            false,
            [sanitize_key((string) ($field['name'] ?? ''))]
        );
    }

    function meza_format_event_aware_section_field_value($value, $post_id, $field)
    {
        $resolved_post_id = is_numeric($post_id) ? (int) $post_id : 0;
        $field = is_array($field)
            ? meza_get_runtime_normalized_section_group_field($field, $resolved_post_id)
            : $field;

        if (!meza_is_event_aware_section_group_value($value, $resolved_post_id, $field)) {
            return $value;
        }

        return meza_resolve_event_aware_field_tree_value(
            $value,
            (array) $field,
            $resolved_post_id,
            true,
            [sanitize_key((string) ($field['name'] ?? ''))]
        );
    }

    function meza_load_builtin_event_details_group_value($value, $post_id, $field)
    {
        $resolved_post_id = is_numeric($post_id) ? (int) $post_id : 0;
        $has_event_after_content = function_exists('meza_events_have_after_content')
            && meza_events_have_after_content();
        $has_season_after_content = function_exists('meza_annual_years_have_end_of_year_content')
            && meza_annual_years_have_end_of_year_content();

        if (
            $resolved_post_id <= 0
            || get_post_type($resolved_post_id) !== 'event'
            || (!$has_event_after_content && !$has_season_after_content)
            || !is_array($field)
        ) {
            return $value;
        }

        $field_name = sanitize_key((string) ($field['name'] ?? ''));
        if (!in_array($field_name, ['summary', 'link'], true)) {
            return $value;
        }

        $group_value = is_array($value) ? $value : [];
        $before_value = $group_value['before'] ?? null;
        $after_value = $group_value['after'] ?? null;
        $base_value = $group_value['base'] ?? null;
        $season_after_value = $group_value['season_after'] ?? null;

        if (
            meza_section_field_value_is_empty($before_value)
            && metadata_exists('post', $resolved_post_id, $field_name . '_before')
        ) {
            $before_value = get_post_meta($resolved_post_id, $field_name . '_before', true);
        }

        if (meza_section_field_value_is_empty($before_value) && metadata_exists('post', $resolved_post_id, $field_name)) {
            $before_value = get_post_meta($resolved_post_id, $field_name, true);
        }

        if (
            meza_section_field_value_is_empty($after_value)
            && metadata_exists('post', $resolved_post_id, $field_name . '_after')
        ) {
            $after_value = get_post_meta($resolved_post_id, $field_name . '_after', true);
        }

        if ($field_name === 'link') {
            $before_value = meza_normalize_event_context_link_field_value($before_value);
            $after_value = meza_normalize_event_context_link_field_value($after_value);
            $base_value = meza_normalize_event_context_link_field_value($base_value);
            $season_after_value = meza_normalize_event_context_link_field_value($season_after_value);
        } else {
            $before_value = meza_normalize_event_context_scalar_field_value($before_value);
            $after_value = meza_normalize_event_context_scalar_field_value($after_value);
            $base_value = meza_normalize_event_context_scalar_field_value($base_value);
            $season_after_value = meza_normalize_event_context_scalar_field_value($season_after_value);
        }

        if (
            meza_section_field_value_is_empty($base_value)
            && metadata_exists('post', $resolved_post_id, $field_name)
        ) {
            $base_value = get_post_meta($resolved_post_id, $field_name, true);
        }

        if (
            meza_section_field_value_is_empty($season_after_value)
            && metadata_exists('post', $resolved_post_id, $field_name . '_season_after')
        ) {
            $season_after_value = get_post_meta($resolved_post_id, $field_name . '_season_after', true);
        }

        if ($field_name === 'link') {
            $base_value = meza_normalize_event_context_link_field_value($base_value);
            $season_after_value = meza_normalize_event_context_link_field_value($season_after_value);
        }

        return meza_build_context_group_value($field, [
            'before' => $before_value,
            'after' => $after_value,
            'base' => $base_value,
            'season_after' => $season_after_value,
        ]);
    }

    function meza_load_builtin_event_details_sub_field_value($value, $post_id, $field)
    {
        $resolved_post_id = is_numeric($post_id) ? (int) $post_id : 0;

        if (
            $resolved_post_id <= 0
            || get_post_type($resolved_post_id) !== 'event'
            || !function_exists('meza_events_have_after_content')
            || !meza_events_have_after_content()
            || !is_array($field)
        ) {
            return $value;
        }

        $field_key = (string) ($field['key'] ?? '');
        $field_map = [
            'field_6a08e3fc9599c' => [
                'meta_keys' => ['summary_before', 'summary'],
                'is_link' => false,
                'grouped_slot' => 'before',
            ],
            'field_6a08e3fc9599d' => [
                'meta_keys' => ['summary_after'],
                'is_link' => false,
                'grouped_slot' => 'after',
            ],
            'field_6a08d94ae1433' => [
                'meta_keys' => ['link_before', 'link'],
                'is_link' => true,
                'grouped_slot' => 'before',
            ],
            'field_6a08e0a7a257d' => [
                'meta_keys' => ['link_after'],
                'is_link' => true,
                'grouped_slot' => 'after',
            ],
        ];

        if (!isset($field_map[$field_key]) || !meza_section_field_value_is_empty($value)) {
            return $value;
        }

        $legacy_value = null;

        foreach ($field_map[$field_key]['meta_keys'] as $meta_key) {
            if (!metadata_exists('post', $resolved_post_id, $meta_key)) {
                continue;
            }

            $legacy_value = get_post_meta($resolved_post_id, $meta_key, true);
            $grouped_payload = meza_parse_event_context_grouped_payload($legacy_value);
            if (is_array($grouped_payload)) {
                $legacy_value = $grouped_payload[$field_map[$field_key]['grouped_slot']] ?? null;
            }

            if (!meza_section_field_value_is_empty($legacy_value)) {
                break;
            }
        }

        if (meza_section_field_value_is_empty($legacy_value)) {
            return $value;
        }

        if ($field_map[$field_key]['is_link']) {
            return meza_normalize_event_context_link_field_value($legacy_value);
        }

        return $legacy_value;
    }

    function meza_build_default_section_sub_field(string $section_field_name, string $sub_field_name): array
    {
        $is_textarea = in_array($sub_field_name, ['description', 'subhead'], true)
            || (in_array($sub_field_name, ['headline', 'display'], true) && meza_has_combined_temporal_content_context());
        $is_link = in_array($sub_field_name, ['link', 'link_secondary'], true);
        $field = [
            'key' => meza_get_section_sub_field_stable_key($section_field_name, $sub_field_name),
            'label' => meza_get_standard_section_sub_field_label($sub_field_name),
            'name' => $sub_field_name,
            'aria-label' => '',
            'type' => $is_textarea ? 'textarea' : ($is_link ? 'link' : 'text'),
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
        ];

        if ($is_textarea) {
            $field['rows'] = meza_get_textarea_rows_for_field([
                'name' => $sub_field_name,
                'meza_textarea_role' => $sub_field_name,
            ]);
            $field['placeholder'] = '';
            $field['new_lines'] = '';
            if (in_array($sub_field_name, ['headline', 'display', 'subhead'], true)) {
                $field['meza_textarea_role'] = $sub_field_name;
            }
            if ($sub_field_name === 'description') {
                $field['new_lines'] = 'wpautop';
            }
        } elseif ($is_link) {
            $field['return_format'] = 'array';
        } else {
            $field['placeholder'] = '';
            $field['prepend'] = '';
            $field['append'] = '';
        }

        if (
            $sub_field_name === 'display'
            && function_exists('meza_get_standard_section_header_field_names')
            && in_array('display', meza_get_standard_section_header_field_names($section_field_name), true)
        ) {
            $field['conditional_logic'] = [
                [
                    [
                        'field' => meza_get_section_sub_field_stable_key($section_field_name, 'headline'),
                        'operator' => '!=empty',
                    ],
                ],
            ];
        }

        if ($sub_field_name === 'link_secondary') {
            $field['conditional_logic'] = [
                [
                    [
                        'field' => meza_get_section_sub_field_stable_key($section_field_name, 'link'),
                        'operator' => '!=empty',
                    ],
                ],
            ];
        }

        return $field;
    }

    function meza_get_standard_section_sub_field_label(string $sub_field_name): string
    {
        $sub_field_name = sanitize_key($sub_field_name);

        $labels = [
            'headline' => 'Headline (H2)',
            'display' => 'Display',
            'subhead' => 'Subhead',
            'description' => 'Description',
            'link' => 'Link',
            'link_secondary' => 'Link (Secondary)',
            'image' => 'Image',
            'icon' => 'Icon',
            'video' => 'Video',
            'video_embed' => 'Video Embed',
            'faqs' => 'FAQs',
            'form' => 'Form',
            'id' => 'ID',
        ];

        if (array_key_exists($sub_field_name, $labels)) {
            return $labels[$sub_field_name];
        }

        return ucwords(str_replace('_', ' ', $sub_field_name));
    }

    function meza_normalize_section_sub_fields(
        array $sub_fields,
        string $section_field_name,
        string $group_key = '',
        bool $supports_event_context = false
    ): array
    {
        $target_names = function_exists('meza_get_standard_section_header_field_names')
            ? meza_get_standard_section_header_field_names($section_field_name)
            : ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary'];
        $index_by_name = [];

        foreach ($sub_fields as $index => $sub_field) {
            if (!is_array($sub_field)) {
                continue;
            }

            $name = sanitize_key((string) ($sub_field['name'] ?? ''));
            if ($name !== '') {
                $index_by_name[$name] = $index;
            }
        }

        foreach ($target_names as $name) {
            if (!array_key_exists($name, $index_by_name)) {
                $sub_fields[] = meza_build_default_section_sub_field($section_field_name, $name);
                $index_by_name[$name] = array_key_last($sub_fields);
                continue;
            }

            $index = (int) $index_by_name[$name];
            if (!is_array($sub_fields[$index])) {
                $sub_fields[$index] = meza_build_default_section_sub_field($section_field_name, $name);
                continue;
            }

            if (meza_is_event_context_group_field($sub_fields[$index]) || meza_is_season_context_group_field($sub_fields[$index])) {
                foreach ((array) ($sub_fields[$index]['sub_fields'] ?? []) as $nested_index => $nested_sub_field) {
                    if (!is_array($nested_sub_field)) {
                        continue;
                    }

                    if ($name === 'subhead' && (string) ($nested_sub_field['type'] ?? '') === 'textarea') {
                        $sub_fields[$index]['sub_fields'][$nested_index]['meza_textarea_role'] = 'subhead';
                    }

                    $nested_name = sanitize_key((string) ($nested_sub_field['name'] ?? ''));
                    if (in_array($nested_name, ['before', 'base'], true)) {
                        $sub_fields[$index]['sub_fields'][$nested_index]['default_value'] = '';
                    }
                }
                continue;
            }

            $sub_fields[$index]['default_value'] = '';

            $current_label = trim((string) ($sub_fields[$index]['label'] ?? ''));
            $default_label = meza_get_standard_section_sub_field_label($name);
            $fallback_machine_labels = [
                $name,
                ucfirst($name),
                ucwords(str_replace('_', ' ', $name)),
                ucfirst(str_replace('_', ' ', $name)),
            ];

            if ($current_label === '' || in_array($current_label, $fallback_machine_labels, true)) {
                $sub_fields[$index]['label'] = $default_label;
            }

            if (in_array($name, ['description', 'subhead'], true) || (in_array($name, ['headline', 'display'], true) && meza_has_combined_temporal_content_context())) {
                if (($sub_fields[$index]['type'] ?? '') !== 'textarea') {
                    $sub_fields[$index]['type'] = 'textarea';
                }
                $sub_fields[$index]['rows'] = meza_get_textarea_rows_for_field([
                    'name' => $name,
                    'meza_textarea_role' => $name,
                ]);
                $sub_fields[$index]['placeholder'] = '';
                if ($name === 'description') {
                    $sub_fields[$index]['new_lines'] = 'wpautop';
                } else {
                    $sub_fields[$index]['meza_textarea_role'] = $name;
                    if (!array_key_exists('new_lines', $sub_fields[$index])) {
                        $sub_fields[$index]['new_lines'] = '';
                    }
                }
                if ($name !== 'description' && !array_key_exists('new_lines', $sub_fields[$index])) {
                    $sub_fields[$index]['new_lines'] = '';
                }
            } elseif (in_array($name, ['link', 'link_secondary'], true)) {
                if (($sub_fields[$index]['type'] ?? '') !== 'link') {
                    $sub_fields[$index]['type'] = 'link';
                }
                $sub_fields[$index]['return_format'] = 'array';
            } else {
                if (($sub_fields[$index]['type'] ?? '') !== 'text') {
                    $sub_fields[$index]['type'] = 'text';
                }
            }
        }

        if ($section_field_name === 'section_list-partners') {
            $sub_fields = array_values(array_filter($sub_fields, static function ($sub_field): bool {
                if (!is_array($sub_field)) {
                    return false;
                }

                return !in_array(
                    sanitize_key((string) ($sub_field['name'] ?? '')),
                    ['summary', 'disclaimer', 'button_text'],
                    true
                );
            }));

            $index_by_name = [];
            foreach ($sub_fields as $index => $sub_field) {
                if (!is_array($sub_field)) {
                    continue;
                }

                $name = sanitize_key((string) ($sub_field['name'] ?? ''));
                if ($name !== '') {
                    $index_by_name[$name] = $index;
                }
            }
        }

        $normalized_group_key = sanitize_key($group_key);
        $order_prefix = array_values(array_unique(array_merge($target_names, ['image'])));
        if ($normalized_group_key === 'group_692cdbb4a0ff0') {
            $order_prefix = ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary', 'icon', 'image', 'video', 'video_embed'];
        } elseif ($section_field_name === 'section_form') {
            $order_prefix = ['headline', 'display', 'subhead', 'description', 'faqs', 'form', 'link', 'link_secondary', 'id'];
        } elseif ($section_field_name === 'section_faqs') {
            $order_prefix = ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary', 'faqs', 'image', 'id'];
        }
        $ordered = [];
        $used_indexes = [];

        foreach ($order_prefix as $name) {
            if (!array_key_exists($name, $index_by_name)) {
                continue;
            }

            $index = (int) $index_by_name[$name];
            if (!isset($sub_fields[$index]) || !is_array($sub_fields[$index])) {
                continue;
            }

            $ordered[] = $sub_fields[$index];
            $used_indexes[$index] = true;
        }

        foreach ($sub_fields as $index => $sub_field) {
            if (!is_array($sub_field) || isset($used_indexes[$index])) {
                continue;
            }

            $name = sanitize_key((string) ($sub_field['name'] ?? ''));
            $ordered[] = $sub_field;
            $used_indexes[$index] = true;
        }

        return $ordered;
    }

    function meza_apply_section_display_dependency(array $sub_fields): array
    {
        $headline_index = null;
        $display_index = null;

        foreach ($sub_fields as $index => $sub_field) {
            if (!is_array($sub_field)) {
                continue;
            }

            $name = sanitize_key((string) ($sub_field['name'] ?? ''));
            if ($name === 'headline') {
                $headline_index = $index;
            } elseif ($name === 'display') {
                $display_index = $index;
            }
        }

        if ($headline_index === null || $display_index === null) {
            return $sub_fields;
        }

        $headline_field = $sub_fields[$headline_index];
        $display_field = $sub_fields[$display_index];
        if (!is_array($headline_field) || !is_array($display_field)) {
            return $sub_fields;
        }

        $headline_key = '';
        if (meza_is_event_context_group_field($headline_field)) {
            $before_field = meza_get_event_context_nested_sub_field($headline_field, 'before');
            $headline_key = is_array($before_field) ? (string) ($before_field['key'] ?? '') : '';
        }

        if ($headline_key === '' && meza_is_season_context_group_field($headline_field)) {
            $before_field = meza_get_event_context_nested_sub_field($headline_field, 'before');
            $base_field = meza_get_event_context_nested_sub_field($headline_field, 'base');
            $headline_key = is_array($before_field)
                ? (string) ($before_field['key'] ?? '')
                : (is_array($base_field) ? (string) ($base_field['key'] ?? '') : '');
        }

        if ($headline_key === '') {
            $headline_key = (string) ($headline_field['key'] ?? '');
        }

        if ($headline_key === '') {
            return $sub_fields;
        }

        $sub_fields[$display_index]['conditional_logic'] = [
            [
                [
                    'field' => $headline_key,
                    'operator' => '!=empty',
                ],
            ],
        ];

        return $sub_fields;
    }

    function meza_apply_section_secondary_link_dependency(array $sub_fields): array
    {
        $primary_link_index = null;
        $secondary_link_index = null;

        foreach ($sub_fields as $index => $sub_field) {
            if (!is_array($sub_field)) {
                continue;
            }

            $name = sanitize_key((string) ($sub_field['name'] ?? ''));
            if ($name === 'link') {
                $primary_link_index = $index;
            } elseif ($name === 'link_secondary') {
                $secondary_link_index = $index;
            }
        }

        if ($primary_link_index === null || $secondary_link_index === null) {
            return $sub_fields;
        }

        $primary_link_field = $sub_fields[$primary_link_index];
        $secondary_link_field = $sub_fields[$secondary_link_index];
        if (!is_array($primary_link_field) || !is_array($secondary_link_field)) {
            return $sub_fields;
        }

        $primary_link_key = '';
        if (meza_is_event_context_group_field($primary_link_field)) {
            $before_field = meza_get_event_context_nested_sub_field($primary_link_field, 'before');
            $primary_link_key = is_array($before_field) ? (string) ($before_field['key'] ?? '') : '';
        }

        if ($primary_link_key === '' && meza_is_season_context_group_field($primary_link_field)) {
            $before_field = meza_get_event_context_nested_sub_field($primary_link_field, 'before');
            $base_field = meza_get_event_context_nested_sub_field($primary_link_field, 'base');
            $primary_link_key = is_array($before_field)
                ? (string) ($before_field['key'] ?? '')
                : (is_array($base_field) ? (string) ($base_field['key'] ?? '') : '');
        }

        if ($primary_link_key === '') {
            $primary_link_key = (string) ($primary_link_field['key'] ?? '');
        }

        if ($primary_link_key === '') {
            return $sub_fields;
        }

        $sub_fields[$secondary_link_index]['conditional_logic'] = [
            [
                [
                    'field' => $primary_link_key,
                    'operator' => '!=empty',
                ],
            ],
        ];

        return $sub_fields;
    }

    function meza_should_normalize_field_for_event_context(array $field, string $group_key = ''): bool
    {
        if (!meza_field_supports_event_context($field)) {
            return false;
        }

        $group_key = sanitize_key($group_key);
        $field_name = sanitize_key((string) ($field['name'] ?? ''));

        if ($group_key === 'group_6a08b62b10548' && !in_array($field_name, ['summary', 'link'], true)) {
            return false;
        }

        return true;
    }

    function meza_should_normalize_field_for_season_context(array $field, string $group_key = '', string $section_field_name = ''): bool
    {
        if (!meza_field_supports_season_context($field)) {
            return false;
        }

        return meza_season_context_is_whitelisted_field($field, $group_key, $section_field_name);
    }

    function meza_field_group_has_section_fields(array $fields): bool
    {
        foreach ($fields as $field) {
            if (
                is_array($field)
                && (string) ($field['type'] ?? '') === 'group'
                && str_starts_with(sanitize_key((string) ($field['name'] ?? '')), 'section_')
            ) {
                return true;
            }
        }

        return false;
    }

    function meza_fields_have_event_context(array $fields): bool
    {
        foreach ($fields as $field) {
            if (is_array($field) && meza_field_tree_has_event_context($field)) {
                return true;
            }
        }

        return false;
    }

    function meza_fields_have_season_context(array $fields): bool
    {
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            if (meza_is_season_context_group_field($field)) {
                return true;
            }

            $field_type = (string) ($field['type'] ?? '');
            if (in_array($field_type, ['group', 'repeater'], true) && meza_fields_have_season_context((array) ($field['sub_fields'] ?? []))) {
                return true;
            }

            if ($field_type === 'flexible_content') {
                foreach ((array) ($field['layouts'] ?? []) as $layout) {
                    if (is_array($layout) && meza_fields_have_season_context((array) ($layout['sub_fields'] ?? []))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    function meza_normalize_loaded_event_context_fields(array $fields, $parent): array
    {
        $ensure_internal_names = static function (array $normalized_fields): array {
            if (!function_exists('meza_ensure_acf_field_tree_has_internal_names')) {
                return $normalized_fields;
            }

            return array_values(array_map(
                static fn(array $field): array => meza_ensure_acf_field_tree_has_internal_names($field),
                array_values(array_filter($normalized_fields, 'is_array'))
            ));
        };

        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                continue;
            }

            if (function_exists('meza_ensure_acf_field_tree_has_internal_names')) {
                $field = meza_ensure_acf_field_tree_has_internal_names($field);
            }

            $fields[$index] = meza_apply_textarea_rows_to_field_tree($field);
        }

        if (!is_array($parent) || !isset($parent['location']) || isset($parent['type'])) {
            return $fields;
        }

        $group = $parent;
        $group['fields'] = $fields;
        $group_key = sanitize_key((string) ($group['key'] ?? ''));
        $supports_event_context = meza_section_field_group_supports_event_context($group);
        $supports_season_context = meza_section_field_group_supports_season_context($group);
        $has_section_fields = meza_field_group_has_section_fields($fields);
        $has_event_context = meza_fields_have_event_context($fields);
        $has_season_context = meza_fields_have_season_context($fields);
        $is_event_request = function_exists('meza_get_event_context_request_post_type')
            && meza_get_event_context_request_post_type() === 'event';
        $event_group_key = 'group_6a08b62b10548';
        $is_builtin_event_group = $group_key === $event_group_key
            && function_exists('meza_events_have_after_content')
            && meza_events_have_after_content()
            && $is_event_request;

        if (
            !$supports_event_context
            && !$has_event_context
            && !$supports_season_context
            && !$has_season_context
            && !$is_builtin_event_group
        ) {
            return $ensure_internal_names($fields);
        }

        if ($has_section_fields) {
            $normalized_group = meza_normalize_header_section_group($group);
            $normalized_fields = isset($normalized_group['fields']) && is_array($normalized_group['fields'])
                ? array_values($normalized_group['fields'])
                : $fields;
            return $ensure_internal_names($normalized_fields);
        }

        if ($is_builtin_event_group || $has_event_context || $supports_season_context || $has_season_context) {
            $fields = meza_normalize_event_context_child_fields(
                $fields,
                $supports_event_context || $is_builtin_event_group,
                $group_key
            );

            return $ensure_internal_names(meza_normalize_season_context_child_fields(
                $fields,
                $supports_season_context,
                $group_key
            ));
        }

        return $ensure_internal_names($fields);
    }

    function meza_normalize_event_context_child_fields(array $fields, bool $supports_event_context, string $group_key = ''): array
    {
        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                continue;
            }

            $field_type = (string) ($field['type'] ?? '');
            if ($field_type === 'group' && meza_is_event_context_group_field($field)) {
                continue;
            }

            if (in_array($field_type, ['group', 'repeater'], true)) {
                $fields[$index]['sub_fields'] = meza_normalize_event_context_child_fields(
                    (array) ($field['sub_fields'] ?? []),
                    $supports_event_context,
                    $group_key
                );
                continue;
            }

            if ($field_type === 'flexible_content') {
                foreach ((array) ($field['layouts'] ?? []) as $layout_index => $layout) {
                    if (!is_array($layout)) {
                        continue;
                    }

                    $fields[$index]['layouts'][$layout_index]['sub_fields'] = meza_normalize_event_context_child_fields(
                        (array) ($layout['sub_fields'] ?? []),
                        $supports_event_context,
                        $group_key
                    );
                }
            }
        }

        $normalized = [];
        $conditional_key_map = [];
        $used_indexes = [];
        $field_count = count($fields);

        for ($index = 0; $index < $field_count; $index++) {
            if (!isset($fields[$index]) || !is_array($fields[$index]) || isset($used_indexes[$index])) {
                continue;
            }

            $field = $fields[$index];
            $field_name = sanitize_key((string) ($field['name'] ?? ''));

            if (!$supports_event_context) {
                if (meza_is_event_context_group_field($field)) {
                    $collapsed_field = meza_collapse_event_context_field($field);
                    $conditional_key_map = array_merge(
                        $conditional_key_map,
                        meza_get_event_context_field_key_map($field, $collapsed_field, false)
                    );
                    $normalized[] = $collapsed_field;
                    $used_indexes[$index] = true;
                    continue;
                }

                if ($field_name !== '' && str_ends_with($field_name, '_after')) {
                    $base_name = substr($field_name, 0, -6);
                    if ($base_name !== '') {
                        foreach ($fields as $candidate_field) {
                            if (
                                is_array($candidate_field)
                                && sanitize_key((string) ($candidate_field['name'] ?? '')) === $base_name
                            ) {
                                $used_indexes[$index] = true;
                                continue 2;
                            }
                        }
                    }
                }

                $normalized[] = $field;
                $used_indexes[$index] = true;
                continue;
            }

            if (meza_is_event_context_group_field($field)) {
                $normalized[] = $field;
                $used_indexes[$index] = true;
                continue;
            }

            if ($field_name !== '' && str_ends_with($field_name, '_after')) {
                $base_name = substr($field_name, 0, -6);
                if ($base_name !== '') {
                    foreach ($fields as $candidate_field) {
                        if (
                            is_array($candidate_field)
                            && sanitize_key((string) ($candidate_field['name'] ?? '')) === $base_name
                        ) {
                            $used_indexes[$index] = true;
                            continue 2;
                        }
                    }
                }
            }

            if (!meza_should_normalize_field_for_event_context($field, $group_key)) {
                $normalized[] = $field;
                $used_indexes[$index] = true;
                continue;
            }

            $existing_after_field = null;
            $after_name = $field_name . '_after';

            foreach ($fields as $candidate_index => $candidate_field) {
                if (
                    $candidate_index !== $index
                    && !isset($used_indexes[$candidate_index])
                    && is_array($candidate_field)
                    && sanitize_key((string) ($candidate_field['name'] ?? '')) === $after_name
                ) {
                    $existing_after_field = $candidate_field;
                    $used_indexes[$candidate_index] = true;
                    break;
                }
            }

            $wrapped_field = meza_build_event_context_field($field, $existing_after_field);
            $conditional_key_map = array_merge(
                $conditional_key_map,
                meza_get_event_context_field_key_map($field, $wrapped_field, true)
            );
            $normalized[] = $wrapped_field;
            $used_indexes[$index] = true;
        }

        if ($conditional_key_map !== []) {
            foreach ($normalized as $index => $field) {
                if (!is_array($field)) {
                    continue;
                }

                $normalized[$index] = meza_remap_event_context_conditional_logic_in_field_tree(
                    $field,
                    $conditional_key_map
                );
            }
        }

        return array_values($normalized);
    }

    function meza_normalize_season_context_child_fields(
        array $fields,
        bool $supports_season_context,
        string $group_key = '',
        string $section_field_name = ''
    ): array
    {
        $normalized = [];
        $used_indexes = [];
        $conditional_key_map = [];
        $field_count = count($fields);

        for ($index = 0; $index < $field_count; $index++) {
            if (!isset($fields[$index]) || !is_array($fields[$index]) || isset($used_indexes[$index])) {
                continue;
            }

            $field = $fields[$index];
            $field_name = sanitize_key((string) ($field['name'] ?? ''));
            $field_type = (string) ($field['type'] ?? '');
            $nested_section_field_name = $section_field_name;

            if ($field_type === 'group' && str_starts_with($field_name, 'section_')) {
                $nested_section_field_name = $field_name;
            } elseif ($group_key === 'group_f7b16b48' && $nested_section_field_name === '') {
                $nested_section_field_name = 'section_cta';
            }

            if (
                $field_type === 'group'
                && (meza_is_season_context_group_field($field) || meza_is_event_context_group_field($field))
            ) {
                // Preserve existing contextual group structures as-is during the recursive pass.
            } elseif (in_array($field_type, ['group', 'repeater'], true)) {
                $field['sub_fields'] = meza_normalize_season_context_child_fields(
                    (array) ($field['sub_fields'] ?? []),
                    $supports_season_context,
                    $group_key,
                    $nested_section_field_name
                );
            } elseif ($field_type === 'flexible_content') {
                foreach ((array) ($field['layouts'] ?? []) as $layout_index => $layout) {
                    if (!is_array($layout)) {
                        continue;
                    }

                    $field['layouts'][$layout_index]['sub_fields'] = meza_normalize_season_context_child_fields(
                        (array) ($layout['sub_fields'] ?? []),
                        $supports_season_context,
                        $group_key,
                        $nested_section_field_name
                    );
                }
            }

            if (!$supports_season_context) {
                if (meza_is_season_context_group_field($field)) {
                    $collapsed_field = meza_collapse_season_context_field($field);
                    $conditional_key_map = array_merge(
                        $conditional_key_map,
                        meza_get_season_context_field_key_map($field, $collapsed_field, false)
                    );
                    $normalized[] = $collapsed_field;
                    $used_indexes[$index] = true;
                    continue;
                }

                if ($field_name !== '' && str_ends_with($field_name, '_season_after')) {
                    $used_indexes[$index] = true;
                    continue;
                }

                $normalized[] = $field;
                $used_indexes[$index] = true;
                continue;
            }

            if (meza_is_season_context_group_field($field)) {
                $normalized[] = $field;
                $used_indexes[$index] = true;
                continue;
            }

            if ($field_name !== '' && str_ends_with($field_name, '_season_after')) {
                $used_indexes[$index] = true;
                continue;
            }

            if (!meza_should_normalize_field_for_season_context($field, $group_key, $section_field_name)) {
                $normalized[] = $field;
                $used_indexes[$index] = true;
                continue;
            }

            $existing_season_after_field = null;
            $season_after_name = $field_name . '_season_after';

            foreach ($fields as $candidate_index => $candidate_field) {
                if (
                    $candidate_index !== $index
                    && !isset($used_indexes[$candidate_index])
                    && is_array($candidate_field)
                    && sanitize_key((string) ($candidate_field['name'] ?? '')) === $season_after_name
                ) {
                    $existing_season_after_field = $candidate_field;
                    $used_indexes[$candidate_index] = true;
                    break;
                }
            }

            $normalized_field = meza_is_event_context_group_field($field)
                ? meza_build_combined_context_field($field, null, $existing_season_after_field)
                : meza_build_season_context_field($field, $existing_season_after_field);
            $conditional_key_map = array_merge(
                $conditional_key_map,
                meza_get_season_context_field_key_map($field, $normalized_field, true)
            );
            $normalized[] = $normalized_field;
            $used_indexes[$index] = true;
        }

        if ($conditional_key_map !== []) {
            foreach ($normalized as $index => $field) {
                if (!is_array($field)) {
                    continue;
                }

                $normalized[$index] = meza_remap_event_context_conditional_logic_in_field_tree(
                    $field,
                    $conditional_key_map
                );
            }
        }

        return array_values($normalized);
    }

    function meza_normalize_header_section_group(array $group): array
    {
        if (empty($group['fields']) || !is_array($group['fields'])) {
            return $group;
        }

        $supports_event_context = meza_section_field_group_supports_event_context($group);
        $supports_season_context = meza_section_field_group_supports_season_context($group);

        foreach ($group['fields'] as $field_index => $field) {
            if (!is_array($field)) {
                continue;
            }

            $field_type = (string) ($field['type'] ?? '');
            $field_name = (string) ($field['name'] ?? '');
            if ($field_type !== 'true_false' || !str_starts_with($field_name, 'show_')) {
                continue;
            }

            $group['fields'][$field_index]['message'] = '';
        }

        foreach ($group['fields'] as $field_index => $field) {
            $field_name = sanitize_key((string) ($field['name'] ?? ''));
            if (!is_array($field) || (string) ($field['type'] ?? '') !== 'group' || !str_starts_with($field_name, 'section_')) {
                continue;
            }

            $sub_fields = isset($field['sub_fields']) && is_array($field['sub_fields']) ? $field['sub_fields'] : [];
            $group['fields'][$field_index]['sub_fields'] = meza_normalize_section_sub_fields(
                $sub_fields,
                $field_name,
                sanitize_key((string) ($group['key'] ?? '')),
                $supports_event_context
            );
        }

        $group['fields'] = meza_normalize_event_context_child_fields(
            $group['fields'],
            $supports_event_context,
            sanitize_key((string) ($group['key'] ?? ''))
        );
        $group['fields'] = meza_normalize_season_context_child_fields(
            $group['fields'],
            $supports_season_context,
            sanitize_key((string) ($group['key'] ?? ''))
        );

        foreach ($group['fields'] as $field_index => $field) {
            $field_name = sanitize_key((string) ($field['name'] ?? ''));
            if (!is_array($field) || (string) ($field['type'] ?? '') !== 'group' || !str_starts_with($field_name, 'section_')) {
                continue;
            }

            $sub_fields = isset($field['sub_fields']) && is_array($field['sub_fields']) ? $field['sub_fields'] : [];
            $group['fields'][$field_index]['sub_fields'] = meza_apply_section_secondary_link_dependency(
                meza_apply_section_display_dependency($sub_fields)
            );
        }

        return $group;
    }

    function meza_reset_legacy_section_text_defaults_once(): void
    {
        global $wpdb;

        $version = '2026-06-28-section-text-default-reset-v3';
        $option_name = 'meza_section_text_default_reset_version';
        if ((string) get_option($option_name, '') === $version) {
            return;
        }

        $request_method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($request_method !== 'GET') {
            return;
        }

        $legacy_defaults = [
            'section_hero_headline' => ['services'],
            'section_list-reviews_headline' => ['List Reviews'],
            'section_list-reviews_id' => ['reviews'],
            'section_list-services_headline' => ['services', 'List Services'],
            'section_list-services_id' => ['services'],
            'section_list-reviews_subhead' => ['reviews'],
            'section_list-locations_headline' => ['localities', 'List Locations'],
            'section_list-locations_id' => ['localities'],
            'section_list-events_headline' => ['events', 'List Events'],
            'section_list-events_id' => ['events'],
            'section_list-posts_headline' => ['resources', 'List Posts'],
            'section_list-posts_id' => ['posts'],
            'section_list-resources_headline' => ['List Resources'],
            'section_list-resources_id' => ['resources'],
            'section_list-partners_headline' => ['List Partners'],
            'section_list-partners_id' => ['partners'],
            'section_list-sponsors_headline' => ['List Sponsors'],
            'section_list-sponsors_id' => ['sponsors'],
            'section_faqs_headline' => ['faqs', 'List FAQs'],
            'section_faqs_subhead' => ['reviews'],
            'section_faqs_description' => ['posts'],
            'section_faqs_id' => ['faqs'],
        ];

        foreach ($legacy_defaults as $meta_key => $values) {
            $values = array_values(array_unique(array_filter(array_map('strval', (array) $values), static function (string $value): bool {
                return $value !== '';
            })));
            if ($values === []) {
                continue;
            }

            $placeholders = implode(', ', array_fill(0, count($values), '%s'));

            $post_sql = $wpdb->prepare(
                "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_key = %s AND meta_value IN ($placeholders)",
                array_merge(['', $meta_key], $values)
            );
            $wpdb->query($post_sql);

            $term_sql = $wpdb->prepare(
                "UPDATE {$wpdb->termmeta} SET meta_value = %s WHERE meta_key = %s AND meta_value IN ($placeholders)",
                array_merge(['', $meta_key], $values)
            );
            $wpdb->query($term_sql);
        }

        update_option($option_name, $version, false);
    }

    add_filter('acf/load_value/type=group', 'meza_resolve_event_aware_section_field_value', 20, 3);
    add_filter('acf/load_value/key=field_6a08e3fc9599b', 'meza_load_builtin_event_details_group_value', 25, 3);
    add_filter('acf/load_value/key=field_6a08b62b11d4a', 'meza_load_builtin_event_details_group_value', 25, 3);
    add_filter('acf/load_value/key=field_6a08e3fc9599c', 'meza_load_builtin_event_details_sub_field_value', 25, 3);
    add_filter('acf/load_value/key=field_6a08e3fc9599d', 'meza_load_builtin_event_details_sub_field_value', 25, 3);
    add_filter('acf/load_value/key=field_6a08d94ae1433', 'meza_load_builtin_event_details_sub_field_value', 25, 3);
    add_filter('acf/load_value/key=field_6a08e0a7a257d', 'meza_load_builtin_event_details_sub_field_value', 25, 3);
    add_filter('acf/load_value/type=repeater', 'meza_resolve_event_aware_section_field_value', 20, 3);
    add_filter('acf/load_value/type=flexible_content', 'meza_resolve_event_aware_section_field_value', 20, 3);
    add_filter('acf/update_value/type=group', static function ($value, $post_id, array $field) {
        $field_name = sanitize_key((string) ($field['name'] ?? ''));

        if (
            !str_starts_with($field_name, 'section_')
            && !meza_is_event_context_group_field($field)
            && !meza_is_season_context_group_field($field)
        ) {
            return $value;
        }

        return meza_normalize_group_value_for_acf_update($value, $field);
    }, 5, 3);
    add_filter('acf/load_field', static function ($field) {
        if (!is_array($field) || !function_exists('meza_ensure_acf_field_tree_has_internal_names')) {
            return $field;
        }

        return meza_ensure_acf_field_tree_has_internal_names($field);
    }, 5);
    add_filter('acf/load_field', static function (array $field): array {
        $field_name = sanitize_key((string) ($field['name'] ?? ''));

        if ((string) ($field['type'] ?? '') !== 'group' || !str_starts_with($field_name, 'section_')) {
            return $field;
        }

        $field = meza_get_runtime_normalized_section_group_field(
            $field,
            meza_get_event_context_request_post_id()
        );

        return function_exists('meza_ensure_acf_field_tree_has_internal_names')
            ? meza_ensure_acf_field_tree_has_internal_names($field)
            : $field;
    }, 25);
    add_filter('acf/load_fields', 'meza_normalize_loaded_event_context_fields', 40, 2);
    add_filter('acf/format_value/type=group', 'meza_format_event_aware_section_field_value', 20, 3);
    add_filter('acf/format_value/type=repeater', 'meza_format_event_aware_section_field_value', 20, 3);
    add_filter('acf/format_value/type=flexible_content', 'meza_format_event_aware_section_field_value', 20, 3);

    add_filter('acf/load_field/key=field_69028fd8fd533', static function (array $field): array {
        $field['layout'] = 'row';
        $field['min'] = 1;
        $sub_fields = isset($field['sub_fields']) && is_array($field['sub_fields'])
            ? array_values($field['sub_fields'])
            : [];
        $normalized_by_key = [];

        $build_gallery_video_embed_sub_field = static function (array $source = []) use ($field): array {
            return array_merge($source, [
                'key' => 'field_6902901cfd535',
                'label' => 'Video Embed',
                'name' => 'video_embed',
                '_name' => 'video_embed',
                'type' => 'oembed',
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
                'parent' => $source['parent'] ?? ($field['ID'] ?? $field['id'] ?? $field['parent'] ?? ''),
            ]);
        };

        foreach ($sub_fields as $sub_field) {
            if (!is_array($sub_field)) {
                continue;
            }

            $sub_key = (string) ($sub_field['key'] ?? '');

            if ($sub_key === 'field_69028febfd534') {
                $sub_field['menu_order'] = 1;
                $sub_field['required'] = 0;
                $sub_field['conditional_logic'] = 0;
            }

            if ($sub_key === 'field_6902901cfd535') {
                $sub_field['menu_order'] = 0;
                $sub_field['label'] = 'Video Embed';
                $sub_field['name'] = 'video_embed';
                $sub_field['_name'] = 'video_embed';
                $sub_field['type'] = 'oembed';
                $sub_field['required'] = 0;
                $sub_field['conditional_logic'] = 0;
                $sub_field['width'] = '';
                $sub_field['height'] = '';
                unset(
                    $sub_field['return_format'],
                    $sub_field['library'],
                    $sub_field['min_size'],
                    $sub_field['max_size'],
                    $sub_field['mime_types']
                );
            }

            $normalized_by_key[$sub_key] = $sub_field;
        }

        if (!isset($normalized_by_key['field_6902901cfd535'])) {
            $image_source = $normalized_by_key['field_69028febfd534'] ?? [];
            $normalized_by_key['field_6902901cfd535'] = $build_gallery_video_embed_sub_field(
                is_array($image_source) ? $image_source : []
            );
        }

        $ordered_keys = [
            'field_6902901cfd535',
            'field_69028febfd534',
        ];
        $normalized = [];

        foreach ($ordered_keys as $ordered_key) {
            if (isset($normalized_by_key[$ordered_key])) {
                $normalized[] = $normalized_by_key[$ordered_key];
                unset($normalized_by_key[$ordered_key]);
            }
        }

        foreach ($normalized_by_key as $sub_field) {
            $normalized[] = $sub_field;
        }

        $field['sub_fields'] = $normalized;

        return $field;
    }, 20);

    add_filter('acf/load_field/key=field_6902901cfd535', static function (array $field): array {
        $field['label'] = 'Video Embed';
        $field['name'] = 'video_embed';
        $field['_name'] = 'video_embed';
        $field['type'] = 'oembed';
        $field['required'] = 0;
        $field['conditional_logic'] = 0;
        $field['width'] = '';
        $field['height'] = '';
        unset(
            $field['return_format'],
            $field['library'],
            $field['min_size'],
            $field['max_size'],
            $field['mime_types']
        );

        return $field;
    }, 20);

    add_filter('acf/prepare_field/key=field_meza_section_gallery_video_embed', static function ($field) {
        return false;
    }, 20);

    add_action('acf/input/admin_footer', static function (): void {
        ?>
        <script>
            (function () {
                const repeaterKey = 'field_69028fd8fd533';
                const videoKey = 'field_6902901cfd535';
                const imageKey = 'field_69028febfd534';

                const getFieldElement = (row, key) => row.querySelector('.acf-field[data-key="' + key + '"]');

                const getImageValue = (fieldEl) => {
                    if (!(fieldEl instanceof HTMLElement)) {
                        return '';
                    }

                    const input = fieldEl.querySelector('input[type="hidden"][name*="[' + imageKey + ']"]');
                    return input instanceof HTMLInputElement ? input.value.trim() : '';
                };

                const getVideoValue = (fieldEl) => {
                    if (!(fieldEl instanceof HTMLElement)) {
                        return '';
                    }

                    const input = fieldEl.querySelector('.acf-oembed .input-value');
                    return input instanceof HTMLInputElement ? input.value.trim() : '';
                };

                const setVisible = (fieldEl, isVisible) => {
                    if (!(fieldEl instanceof HTMLElement)) {
                        return;
                    }

                    fieldEl.style.display = isVisible ? '' : 'none';
                };

                const syncRow = (row) => {
                    if (!(row instanceof HTMLElement) || row.classList.contains('acf-clone')) {
                        return;
                    }

                    const videoField = getFieldElement(row, videoKey);
                    const imageField = getFieldElement(row, imageKey);

                    if (!(videoField instanceof HTMLElement) || !(imageField instanceof HTMLElement)) {
                        return;
                    }

                    const hasVideo = getVideoValue(videoField) !== '';
                    const hasImage = getImageValue(imageField) !== '';

                    if (hasVideo && !hasImage) {
                        setVisible(videoField, true);
                        setVisible(imageField, false);
                        return;
                    }

                    if (hasImage && !hasVideo) {
                        setVisible(videoField, false);
                        setVisible(imageField, true);
                        return;
                    }

                    setVisible(videoField, true);
                    setVisible(imageField, true);
                };

                const syncRepeater = (context) => {
                    const root = context instanceof Element ? context : document;
                    const repeater = root instanceof Element && root.matches('.acf-field[data-key="' + repeaterKey + '"]')
                        ? root
                        : root.querySelector('.acf-field[data-key="' + repeaterKey + '"]');

                    if (!(repeater instanceof HTMLElement)) {
                        return;
                    }

                    repeater.querySelectorAll('.acf-row').forEach((row) => {
                        syncRow(row);
                    });
                };

                document.addEventListener('input', (event) => {
                    const target = event.target;
                    if (!(target instanceof HTMLElement)) {
                        return;
                    }

                    const row = target.closest('.acf-row');
                    if (row instanceof HTMLElement) {
                        syncRow(row);
                    }
                });

                document.addEventListener('change', (event) => {
                    const target = event.target;
                    if (!(target instanceof HTMLElement)) {
                        return;
                    }

                    const row = target.closest('.acf-row');
                    if (row instanceof HTMLElement) {
                        window.setTimeout(() => syncRow(row), 0);
                    }
                });

                const boot = () => syncRepeater(document);

                if (window.acf && typeof window.acf.addAction === 'function') {
                    window.acf.addAction('ready', boot);
                    window.acf.addAction('append', syncRepeater);
                    window.acf.addAction('show_field', syncRepeater);
                } else {
                    document.addEventListener('DOMContentLoaded', boot);
                }
            }());
        </script>
        <?php
    }, 20);
