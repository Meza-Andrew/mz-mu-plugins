<?php

    function meza_get_local_acf_post_type_definitions(): array
    {
        $definitions = [
            meza_get_service_post_type_definition(),
            meza_get_faq_post_type_definition(),
            meza_get_cta_post_type_definition(),
            meza_get_review_post_type_definition(),
        ];

        if (function_exists('mzf_get_form_post_type_definition')) {
            $definitions[] = mzf_get_form_post_type_definition();
        }

        return $definitions;
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
            meza_get_service_category_taxonomy_definition(),
            meza_get_locality_taxonomy_definition(),
        ];

        if (meza_supports_sponsor_features()) {
            $definitions[] = meza_get_sponsor_type_taxonomy_definition();
        }

        return $definitions;
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
        return [
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
            'group_meza_list_reviews_section' => 'section_list-reviews',
            'group_meza_list_posts_section' => 'section_list-posts',
            'group_meza_list_resources_section' => 'section_list-resources',
            'group_b2f9d7e8' => 'section_list-services',
        ];
    }

    function meza_get_list_section_headline_defaults_by_group_key(): array
    {
        $defaults = [];

        foreach (meza_get_header_section_group_section_field_names() as $group_key => $_section_name) {
            $group_key = sanitize_key((string) $group_key);
            if ($group_key === '') {
                continue;
            }

            $group_title = '';
            switch ($group_key) {
                case 'group_b2f9d7e8':
                    $group_title = 'List Services Section';
                    break;
                case 'group_meza_list_reviews_section':
                    $group_title = 'List Reviews Section';
                    break;
                case 'group_meza_list_posts_section':
                    $group_title = 'List Posts Section';
                    break;
                case 'group_meza_list_resources_section':
                    $group_title = 'List Resources Section';
                    break;
                case 'group_697feb01ec35f':
                    $group_title = 'List FAQs Section';
                    break;
                case 'group_meza_contact_locations_section':
                    $group_title = 'List Locations Section';
                    break;
            }

            if ($group_title === '') {
                continue;
            }

            $defaults[$group_key] = trim(preg_replace('/\s+Section$/', '', $group_title));
        }

        return $defaults;
    }

    function meza_get_section_sub_field_stable_key(string $section_field_name, string $sub_field_name): string
    {
        return 'field_meza_' . str_replace('-', '_', sanitize_key($section_field_name)) . '_' . sanitize_key($sub_field_name);
    }

    function meza_build_default_section_sub_field(string $section_field_name, string $sub_field_name): array
    {
        $is_textarea = $sub_field_name === 'description';
        $is_link = $sub_field_name === 'link';
        $field = [
            'key' => meza_get_section_sub_field_stable_key($section_field_name, $sub_field_name),
            'label' => ucfirst($sub_field_name),
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
            $field['rows'] = 2;
            $field['placeholder'] = '';
            $field['new_lines'] = '';
        } elseif ($is_link) {
            $field['return_format'] = 'array';
        } else {
            $field['placeholder'] = '';
            $field['prepend'] = '';
            $field['append'] = '';
        }

        return $field;
    }

    function meza_normalize_section_sub_fields(array $sub_fields, string $section_field_name, string $group_key = ''): array
    {
        $target_names = ['headline', 'display', 'subhead', 'description', 'link'];
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

            $sub_fields[$index]['default_value'] = '';

            if ($name === 'description') {
                if (($sub_fields[$index]['type'] ?? '') !== 'textarea') {
                    $sub_fields[$index]['type'] = 'textarea';
                }
            } elseif ($name === 'link') {
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

        $headline_defaults = meza_get_list_section_headline_defaults_by_group_key();
        $normalized_group_key = sanitize_key($group_key);
        if ($normalized_group_key !== '' && array_key_exists($normalized_group_key, $headline_defaults) && array_key_exists('headline', $index_by_name)) {
            $headline_index = (int) $index_by_name['headline'];
            if (isset($sub_fields[$headline_index]) && is_array($sub_fields[$headline_index])) {
                $sub_fields[$headline_index]['default_value'] = (string) $headline_defaults[$normalized_group_key];
            }
        }

        $order_prefix = ['headline', 'display', 'subhead', 'description', 'image', 'link'];
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
            if ($name === 'id') {
                continue;
            }

            $ordered[] = $sub_field;
            $used_indexes[$index] = true;
        }

        if (array_key_exists('id', $index_by_name)) {
            $id_index = (int) $index_by_name['id'];
            if (isset($sub_fields[$id_index]) && is_array($sub_fields[$id_index])) {
                $ordered[] = $sub_fields[$id_index];
            }
        }

        return $ordered;
    }

    function meza_normalize_header_section_group(array $group): array
    {
        $group_key = sanitize_key((string) ($group['key'] ?? ''));
        $section_field_name = meza_get_header_section_group_section_field_names()[$group_key] ?? '';
        if (empty($group['fields']) || !is_array($group['fields'])) {
            return $group;
        }

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

        if ($section_field_name === '') {
            return $group;
        }

        foreach ($group['fields'] as $field_index => $field) {
            if (!is_array($field) || ($field['name'] ?? '') !== $section_field_name || ($field['type'] ?? '') !== 'group') {
                continue;
            }

            $sub_fields = isset($field['sub_fields']) && is_array($field['sub_fields']) ? $field['sub_fields'] : [];
            $group['fields'][$field_index]['sub_fields'] = meza_normalize_section_sub_fields($sub_fields, $section_field_name, $group_key);
            break;
        }

        return $group;
    }

    function meza_reset_legacy_section_text_defaults_once(): void
    {
        global $wpdb;

        $version = '2026-05-02-section-text-default-reset-v1';
        $option_name = 'meza_section_text_default_reset_version';
        if ((string) get_option($option_name, '') === $version) {
            return;
        }

        $legacy_defaults = [
            'section_hero_headline' => ['services'],
            'section_list-services_headline' => ['services'],
            'section_list-reviews_subhead' => ['reviews'],
            'section_list-locations_headline' => ['localities'],
            'section_list-posts_headline' => ['resources'],
            'section_faqs_headline' => ['faqs'],
            'section_faqs_subhead' => ['reviews'],
            'section_faqs_description' => ['posts'],
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

