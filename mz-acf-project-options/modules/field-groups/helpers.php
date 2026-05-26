<?php


if (!function_exists('meza_shared_project_acf_options_are_available')) {
    function meza_shared_project_acf_options_are_available(): bool
    {
        return function_exists('acf_add_options_page') && function_exists('acf_add_local_field_group');
    }
}

if (!function_exists('meza_normalize_acf_field_group_location')) {
    function meza_normalize_acf_field_group_location(array $location): array
    {
        $normalized = [];

        foreach ($location as $group) {
            if (!is_array($group)) {
                continue;
            }

            $rules = [];
            foreach ($group as $rule) {
                if (!is_array($rule)) {
                    continue;
                }

                $rules[] = [
                    'param' => (string) ($rule['param'] ?? ''),
                    'operator' => (string) ($rule['operator'] ?? ''),
                    'value' => (string) ($rule['value'] ?? ''),
                ];
            }

            usort($rules, static function (array $a, array $b): int {
                return strcmp(wp_json_encode($a), wp_json_encode($b));
            });

            if (!empty($rules)) {
                $normalized[] = $rules;
            }
        }

        usort($normalized, static function (array $a, array $b): int {
            return strcmp(wp_json_encode($a), wp_json_encode($b));
        });

        return $normalized;
    }
}

if (!function_exists('meza_get_acf_field_group_merge_signature')) {
    function meza_is_list_array_compat(array $array): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($array);
        }

        $expected_key = 0;
        foreach (array_keys($array) as $key) {
            if ($key !== $expected_key) {
                return false;
            }

            $expected_key++;
        }

        return true;
    }

    function meza_sort_acf_signature_value($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        if (meza_is_list_array_compat($value)) {
            foreach ($value as $index => $item) {
                $value[$index] = meza_sort_acf_signature_value($item);
            }

            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = meza_sort_acf_signature_value($item);
        }

        ksort($value);

        return $value;
    }

    function meza_normalize_acf_field_for_signature(array $field): array
    {
        $normalized = [
            'key' => (string) ($field['key'] ?? ''),
            'name' => (string) ($field['name'] ?? ''),
            'label' => (string) ($field['label'] ?? ''),
            'type' => (string) ($field['type'] ?? ''),
            'required' => (string) ($field['required'] ?? ''),
        ];

        if (isset($field['sub_fields']) && is_array($field['sub_fields'])) {
            $normalized['sub_fields'] = array_values(array_map(
                static fn(array $sub_field): array => meza_normalize_acf_field_for_signature($sub_field),
                array_values(array_filter($field['sub_fields'], 'is_array'))
            ));
        }

        if (isset($field['layouts']) && is_array($field['layouts'])) {
            $layouts = [];

            foreach ($field['layouts'] as $layout) {
                if (!is_array($layout)) {
                    continue;
                }

                $normalized_layout = [
                    'key' => (string) ($layout['key'] ?? ''),
                    'name' => (string) ($layout['name'] ?? ''),
                    'label' => (string) ($layout['label'] ?? ''),
                    'display' => (string) ($layout['display'] ?? ''),
                ];

                if (isset($layout['sub_fields']) && is_array($layout['sub_fields'])) {
                    $normalized_layout['sub_fields'] = array_values(array_map(
                        static fn(array $sub_field): array => meza_normalize_acf_field_for_signature($sub_field),
                        array_values(array_filter($layout['sub_fields'], 'is_array'))
                    ));
                }

                $layouts[] = meza_sort_acf_signature_value($normalized_layout);
            }

            $normalized['layouts'] = $layouts;
        }

        return meza_sort_acf_signature_value($normalized);
    }

    function meza_get_acf_field_group_fields_signature(array $field_group): string
    {
        $fields = isset($field_group['fields']) && is_array($field_group['fields'])
            ? array_values(array_filter($field_group['fields'], 'is_array'))
            : [];

        if (empty($fields)) {
            return '';
        }

        $normalized_fields = array_map(
            static fn(array $field): array => meza_normalize_acf_field_for_signature($field),
            $fields
        );

        return md5(wp_json_encode($normalized_fields));
    }

    function meza_get_acf_field_signature_match_token(array $field): string
    {
        $name = sanitize_key((string) ($field['name'] ?? ''));
        if ($name !== '') {
            return 'name:' . $name;
        }

        $key = sanitize_key((string) ($field['key'] ?? ''));
        if ($key !== '') {
            return 'key:' . $key;
        }

        $label = sanitize_title((string) ($field['label'] ?? ''));
        $type = sanitize_key((string) ($field['type'] ?? ''));

        return 'label:' . $label . '|type:' . $type;
    }

    function meza_get_acf_field_signature_aligned_to_definition(array $candidate_field, array $definition_field): array
    {
        foreach (['key', 'name', 'label', 'type', 'required'] as $property) {
            if (array_key_exists($property, $definition_field)) {
                $candidate_field[$property] = $definition_field[$property];
            }
        }

        if (isset($definition_field['sub_fields']) && is_array($definition_field['sub_fields'])) {
            $candidate_sub_fields = isset($candidate_field['sub_fields']) && is_array($candidate_field['sub_fields'])
                ? array_values(array_filter($candidate_field['sub_fields'], 'is_array'))
                : [];

            $candidate_sub_fields_by_token = [];
            foreach ($candidate_sub_fields as $sub_field) {
                $candidate_sub_fields_by_token[meza_get_acf_field_signature_match_token($sub_field)] = $sub_field;
            }

            $aligned_sub_fields = [];
            foreach (array_values(array_filter($definition_field['sub_fields'], 'is_array')) as $definition_sub_field) {
                $token = meza_get_acf_field_signature_match_token($definition_sub_field);
                if (!isset($candidate_sub_fields_by_token[$token])) {
                    continue;
                }

                $aligned_sub_fields[] = meza_get_acf_field_signature_aligned_to_definition(
                    $candidate_sub_fields_by_token[$token],
                    $definition_sub_field
                );
            }

            $candidate_field['sub_fields'] = $aligned_sub_fields;
        }

        return $candidate_field;
    }

    function meza_get_acf_field_group_fields_signature_aligned_to_definition(array $candidate, array $definition): string
    {
        $candidate_fields = isset($candidate['fields']) && is_array($candidate['fields'])
            ? array_values(array_filter($candidate['fields'], 'is_array'))
            : [];
        $definition_fields = isset($definition['fields']) && is_array($definition['fields'])
            ? array_values(array_filter($definition['fields'], 'is_array'))
            : [];

        if ($candidate_fields === [] || $definition_fields === []) {
            return meza_get_acf_field_group_fields_signature($candidate);
        }

        $candidate_fields_by_token = [];
        foreach ($candidate_fields as $candidate_field) {
            $candidate_fields_by_token[meza_get_acf_field_signature_match_token($candidate_field)] = $candidate_field;
        }

        $aligned_fields = [];
        foreach ($definition_fields as $definition_field) {
            $token = meza_get_acf_field_signature_match_token($definition_field);
            if (!isset($candidate_fields_by_token[$token])) {
                continue;
            }

            $aligned_fields[] = meza_get_acf_field_signature_aligned_to_definition(
                $candidate_fields_by_token[$token],
                $definition_field
            );
        }

        $candidate['fields'] = $aligned_fields;

        return meza_get_acf_field_group_fields_signature($candidate);
    }

    function meza_get_acf_field_group_merge_signature(array $field_group): string
    {
        $title = trim((string) ($field_group['title'] ?? ''));
        $location = meza_normalize_acf_field_group_location((array) ($field_group['location'] ?? []));

        if ($title === '' || empty($location)) {
            return '';
        }

        return md5(wp_json_encode([
            'title' => $title,
            'location' => $location,
        ]));
    }

    function meza_acf_field_group_matches_definition(array $candidate, array $definition): bool
    {
        $candidate_key = (string) ($candidate['key'] ?? '');
        $definition_key = (string) ($definition['key'] ?? '');
        $candidate_title = trim((string) ($candidate['title'] ?? ''));
        $definition_title = trim((string) ($definition['title'] ?? ''));

        $candidate_fields_signature = meza_get_acf_field_group_fields_signature($candidate);
        $definition_fields_signature = meza_get_acf_field_group_fields_signature($definition);

        if ($candidate_key !== '' && $definition_key !== '') {
            if ($candidate_key !== $definition_key) {
                return false;
            }

            if ($candidate_title !== '' && $definition_title !== '' && $candidate_title !== $definition_title) {
                return false;
            }

            if ($candidate_fields_signature !== '' && $definition_fields_signature !== '') {
                return $candidate_fields_signature === $definition_fields_signature;
            }

            return true;
        }

        $candidate_signature = meza_get_acf_field_group_merge_signature($candidate);
        $definition_signature = meza_get_acf_field_group_merge_signature($definition);

        if (
            $candidate_signature !== ''
            && $definition_signature !== ''
            && $candidate_signature === $definition_signature
        ) {
            return true;
        }

        // ACF sometimes gives the export tool a thinner DB-backed object than the
        // full PHP local definition. Keep known hard-coded singleton groups stable.
        if (
            $candidate_title !== ''
            && $candidate_title === $definition_title
            && in_array($definition_title, ['Form', 'Service'], true)
        ) {
            return true;
        }

        return false;
    }

    function meza_get_matching_acf_field_group_definition(array $candidate, array $definitions): ?array
    {
        foreach ($definitions as $definition) {
            if (!is_array($definition) || !meza_acf_field_group_matches_definition($candidate, $definition)) {
                continue;
            }

            return $definition;
        }

        return null;
    }

    function meza_get_acf_field_group_definition_by_key(array $definitions, string $target_key): ?array
    {
        if ($target_key === '') {
            return null;
        }

        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            if ((string) ($definition['key'] ?? '') === $target_key) {
                return $definition;
            }
        }

        return null;
    }
}

if (!function_exists('meza_is_hardcoded_acf_field_group')) {
    function meza_is_hardcoded_acf_field_group(array $field_group): bool
    {
        $local = (string) ($field_group['local'] ?? '');
        return $local === 'php';
    }
}

if (!function_exists('meza_get_full_acf_field_group_for_matching')) {
    function meza_get_full_acf_field_group_for_matching(array $field_group): array
    {
        if (!empty($field_group['fields']) && is_array($field_group['fields'])) {
            return $field_group;
        }

        $key = (string) ($field_group['key'] ?? '');
        if ($key === '') {
            return $field_group;
        }

        $full_field_group = $field_group;

        if (function_exists('acf_get_raw_field_group')) {
            $raw_field_group = acf_get_raw_field_group($key);
            if (is_array($raw_field_group)) {
                $full_field_group = array_merge($raw_field_group, $field_group);
            }
        }

        if (function_exists('acf_get_fields')) {
            $fields = acf_get_fields($key);
            if (is_array($fields) && !empty($fields)) {
                $full_field_group['fields'] = $fields;
            }
        }

        return $full_field_group;
    }
}

if (!function_exists('meza_should_merge_acf_field_groups_for_current_screen')) {
    function meza_should_merge_acf_field_groups_for_current_screen(): bool
    {
        if (!is_admin() || !function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }

        $acf_admin_post_types = [
            'acf-field-group',
            'acf-post-type',
            'acf-taxonomy',
            'acf-ui-options-page',
        ];

        return !in_array((string) ($screen->post_type ?? ''), $acf_admin_post_types, true);
    }
}

if (!function_exists('meza_is_acf_export_tools_screen')) {
    function meza_is_acf_export_tools_screen(): bool
    {
        if (!is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        $post_type = isset($_GET['post_type']) ? sanitize_key((string) wp_unslash($_GET['post_type'])) : '';

        return $page === 'acf-tools' && $post_type === 'acf-field-group';
    }
}

if (!function_exists('meza_current_user_can_see_hardcoded_acf_export_items')) {
    function meza_current_user_can_see_hardcoded_acf_export_items(): bool
    {
        return current_user_can('manage_options');
    }
}

if (!function_exists('meza_get_acf_export_tool_choices')) {
    function meza_get_acf_export_tool_key_prefix(string $post_type): string
    {
        return match ($post_type) {
            'acf-field-group' => 'group_',
            'acf-post-type' => 'post_type_',
            'acf-taxonomy' => 'taxonomy_',
            'acf-ui-options-page' => 'ui_options_page_',
            default => '',
        };
    }

    function meza_is_conference_only_acf_export_field_group(array $post): bool
    {
        $locations = isset($post['location']) && is_array($post['location']) ? $post['location'] : [];

        foreach ($locations as $location_group) {
            if (!is_array($location_group)) {
                continue;
            }

            foreach ($location_group as $rule) {
                if (!is_array($rule)) {
                    continue;
                }

                $param = sanitize_key((string) ($rule['param'] ?? ''));
                $value = sanitize_key((string) ($rule['value'] ?? ''));

                if ($param === 'options_page' && in_array($value, ['conference', 'conference-schedule'], true)) {
                    return true;
                }

                if ($param === 'post_type' && $value === 'segment') {
                    return true;
                }
            }
        }

        return false;
    }

    function meza_should_include_acf_export_tool_choice(string $post_type, array $post): bool
    {
        if ($post_type === 'acf-field-group' && !meza_is_conference_business_type()) {
            return !meza_is_conference_only_acf_export_field_group($post);
        }

        if ($post_type === 'acf-post-type' && !meza_is_conference_business_type()) {
            $slug = sanitize_key((string) ($post['post_type'] ?? ''));
            if ($slug === 'segment') {
                return false;
            }
        }

        if ($post_type === 'acf-ui-options-page' && !meza_is_conference_business_type()) {
            $slug = sanitize_key((string) ($post['menu_slug'] ?? ''));
            if (in_array($slug, ['conference', 'conference-schedule'], true)) {
                return false;
            }
        }

        return true;
    }

    function meza_get_acf_export_tool_choices(string $post_type): array
    {
        if (!function_exists('acf_get_internal_post_type_posts')) {
            return [];
        }

        $choices = [];
        $key_prefix = meza_get_acf_export_tool_key_prefix($post_type);

        foreach ((array) acf_get_internal_post_type_posts($post_type) as $post) {
            if (
                !is_array($post)
                || empty($post['key'])
                || !is_string($post['key'])
                || (
                    function_exists('acf_internal_post_object_contains_valid_key')
                    && !acf_internal_post_object_contains_valid_key($post)
                )
            ) {
                continue;
            }

            if ($key_prefix === '' || strpos((string) $post['key'], $key_prefix) !== 0) {
                continue;
            }

            if (!meza_should_include_acf_export_tool_choice($post_type, $post)) {
                continue;
            }

            $title = trim((string) ($post['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $choices[] = [
                'key' => (string) $post['key'],
                'title' => $title,
                'local' => (string) ($post['local'] ?? ''),
            ];
        }

        return $choices;
    }
}

if (!function_exists('meza_get_acf_export_tool_choice_map')) {
    function meza_get_acf_export_tool_choice_map(string $post_type): array
    {
        $choices = [];

        foreach (meza_get_acf_export_tool_choices($post_type) as $choice) {
            $key = (string) ($choice['key'] ?? '');
            $title = trim((string) ($choice['title'] ?? ''));

            if ($key === '' || $title === '') {
                continue;
            }

            $choices[$key] = $title;
        }

        return $choices;
    }
}

if (!function_exists('meza_get_acf_export_tool_grouped_choice_maps')) {
    function meza_get_acf_export_default_editable_acf_field_group_definitions(): array
    {
        $definitions = meza_get_default_editable_acf_field_group_definitions();

        if (function_exists('meza_get_contact_locations_section_field_group_definition')) {
            $contact_locations_definition = meza_get_contact_locations_section_field_group_definition();
            if (is_array($contact_locations_definition)) {
                $definitions[] = $contact_locations_definition;
            }
        }

        return array_values(array_filter($definitions, 'is_array'));
    }

    function meza_get_builtin_acf_field_group_definitions(): array
    {
        $definitions = meza_get_shared_project_acf_field_groups();

        if (function_exists('meza_are_services_enabled') && meza_are_services_enabled()) {
            $definitions[] = meza_get_service_field_group_definition();
        }

        if (function_exists('meza_is_event_functionality_enabled') && meza_is_event_functionality_enabled()) {
            $definitions[] = meza_get_event_field_group_definition();
        }

        $definitions[] = meza_get_hero_section_field_group_definition();

        if (function_exists('mzf_get_form_field_group_definition')) {
            $definitions[] = mzf_get_form_field_group_definition();
        }

        return array_values(array_filter($definitions, 'is_array'));
    }

    function meza_find_matching_acf_export_definition(array $post, string $identifier_key, array $definitions, array $aliases = []): ?array
    {
        $identifier = sanitize_key((string) ($post[$identifier_key] ?? ''));
        if ($identifier === '') {
            return null;
        }

        if (isset($aliases[$identifier])) {
            $identifier = sanitize_key((string) $aliases[$identifier]);
        }

        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            if (sanitize_key((string) ($definition[$identifier_key] ?? '')) === $identifier) {
                return $definition;
            }
        }

        return null;
    }

    function meza_get_acf_export_tool_group_for_post(string $post_type, array $post): string
    {
        switch ($post_type) {
            case 'acf-field-group':
                $post = meza_get_full_acf_field_group_for_matching($post);
                $post_key = (string) ($post['key'] ?? '');
                $post_title = trim((string) ($post['title'] ?? ''));
                $post_local = (string) ($post['local'] ?? '');
                $post_fields_signature = meza_get_acf_field_group_fields_signature($post);

                $built_in_definition = meza_get_acf_field_group_definition_by_key(
                    meza_get_builtin_acf_field_group_definitions(),
                    $post_key
                );
                if (is_array($built_in_definition)) {
                    if ($post_local === 'php') {
                        return 'built_in';
                    }

                    $definition_title = trim((string) ($built_in_definition['title'] ?? ''));
                    $definition_fields_signature = meza_get_acf_field_group_fields_signature($built_in_definition);

                    if (
                        $post_title !== ''
                        && $definition_title !== ''
                        && $post_title === $definition_title
                        && $post_fields_signature !== ''
                        && $definition_fields_signature !== ''
                        && $post_fields_signature === $definition_fields_signature
                    ) {
                        return 'built_in';
                    }
                }

                $default_definition = meza_get_acf_field_group_definition_by_key(
                    meza_get_acf_export_default_editable_acf_field_group_definitions(),
                    $post_key
                );
                if (is_array($default_definition)) {
                    $definition_title = trim((string) ($default_definition['title'] ?? ''));
                    $definition_fields_signature = meza_get_acf_field_group_fields_signature($default_definition);
                    $post_fields_signature = meza_get_acf_field_group_fields_signature_aligned_to_definition($post, $default_definition);

                    if (
                        $post_title !== ''
                        && $definition_title !== ''
                        && $post_title === $definition_title
                        && $post_fields_signature !== ''
                        && $definition_fields_signature !== ''
                        && $post_fields_signature === $definition_fields_signature
                    ) {
                        return 'defaults';
                    }
                }

                return 'custom';

            case 'acf-post-type':
                return is_array(meza_find_matching_acf_export_definition($post, 'post_type', meza_get_local_acf_post_type_definitions()))
                    ? 'built_in'
                    : 'custom';

            case 'acf-taxonomy':
                if (is_array(meza_find_matching_acf_export_definition($post, 'taxonomy', meza_get_default_editable_acf_taxonomy_definitions()))) {
                    return 'defaults';
                }

                return is_array(meza_find_matching_acf_export_definition($post, 'taxonomy', meza_get_local_acf_taxonomy_definitions()))
                    ? 'built_in'
                    : 'custom';

            case 'acf-ui-options-page':
                return is_array(meza_find_matching_acf_export_definition(
                    $post,
                    'menu_slug',
                    meza_get_shared_project_acf_options_pages(),
                    meza_get_legacy_shared_project_acf_options_page_slug_map()
                ))
                    ? 'built_in'
                    : 'custom';
        }

        return 'custom';
    }

    function meza_get_acf_export_tool_definition_key_map(string $post_type, string $group): array
    {
        $definitions = [];

        if ($group === 'built_in') {
            switch ($post_type) {
                case 'acf-field-group':
                    $definitions = meza_get_builtin_acf_field_group_definitions();
                    break;
                case 'acf-post-type':
                    $definitions = meza_get_local_acf_post_type_definitions();
                    break;
                case 'acf-taxonomy':
                    $definitions = meza_get_local_acf_taxonomy_definitions();
                    break;
                case 'acf-ui-options-page':
                    foreach (meza_get_shared_project_acf_options_pages() as $page) {
                        if (!is_array($page)) {
                            continue;
                        }

                        $slug = sanitize_key((string) ($page['menu_slug'] ?? ''));
                        if ($slug === '') {
                            continue;
                        }

                        $definitions[] = [
                            'key' => 'ui_options_page_' . $slug,
                        ];
                    }
                    break;
            }
        } elseif ($group === 'defaults') {
            switch ($post_type) {
                case 'acf-field-group':
                    $definitions = meza_get_acf_export_default_editable_acf_field_group_definitions();
                    break;
                case 'acf-taxonomy':
                    $definitions = meza_get_default_editable_acf_taxonomy_definitions();
                    break;
            }
        }

        $key_map = [];

        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $key = (string) ($definition['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $key_map[$key] = true;
        }

        return $key_map;
    }

    function meza_get_acf_export_tool_grouped_choice_maps(string $post_type): array
    {
        $groups = [
            'custom' => [],
            'defaults' => [],
            'built_in' => [],
        ];

        if (!function_exists('acf_get_internal_post_type_posts')) {
            return $groups;
        }

        $key_prefix = meza_get_acf_export_tool_key_prefix($post_type);

        foreach ((array) acf_get_internal_post_type_posts($post_type) as $post) {
            if (
                !is_array($post)
                || empty($post['key'])
                || !is_string($post['key'])
                || (
                    function_exists('acf_internal_post_object_contains_valid_key')
                    && !acf_internal_post_object_contains_valid_key($post)
                )
            ) {
                continue;
            }

            $key = (string) $post['key'];
            if ($key_prefix === '' || strpos($key, $key_prefix) !== 0 || !meza_should_include_acf_export_tool_choice($post_type, $post)) {
                continue;
            }

            $title = trim((string) ($post['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $group = meza_get_acf_export_tool_group_for_post($post_type, $post);
            if (!isset($groups[$group])) {
                $group = 'custom';
            }

            $groups[$group][$key] = $title;
        }

        return $groups;
    }
}

if (!function_exists('meza_register_acf_export_tool_local_definitions')) {
    function meza_with_acf_export_local_enabled(callable $callback)
    {
        $local_was_enabled = function_exists('acf_is_filter_enabled')
            ? (bool) acf_is_filter_enabled('local')
            : true;

        if (function_exists('acf_enable_local')) {
            acf_enable_local();
        }

        try {
            return $callback();
        } finally {
            if (!$local_was_enabled && function_exists('acf_disable_local')) {
                acf_disable_local();
            }
        }
    }

    function meza_prepare_acf_local_field_for_export(array $field): array
    {
        if (!array_key_exists('_name', $field)) {
            $field['_name'] = isset($field['name']) ? (string) $field['name'] : '';
        }

        if (isset($field['sub_fields']) && is_array($field['sub_fields'])) {
            $field['sub_fields'] = array_values(array_map(
                static fn(array $sub_field): array => meza_prepare_acf_local_field_for_export($sub_field),
                array_values(array_filter($field['sub_fields'], 'is_array'))
            ));
        }

        if (isset($field['layouts']) && is_array($field['layouts'])) {
            $normalized_layouts = [];

            foreach ($field['layouts'] as $layout) {
                if (!is_array($layout)) {
                    continue;
                }

                if (isset($layout['sub_fields']) && is_array($layout['sub_fields'])) {
                    $layout['sub_fields'] = array_values(array_map(
                        static fn(array $sub_field): array => meza_prepare_acf_local_field_for_export($sub_field),
                        array_values(array_filter($layout['sub_fields'], 'is_array'))
                    ));
                }

                $normalized_layouts[] = $layout;
            }

            $field['layouts'] = $normalized_layouts;
        }

        return $field;
    }

    function meza_prepare_acf_local_field_group_for_export(array $group): array
    {
        if (isset($group['fields']) && is_array($group['fields'])) {
            $group['fields'] = array_values(array_map(
                static fn(array $field): array => meza_prepare_acf_local_field_for_export($field),
                array_values(array_filter($group['fields'], 'is_array'))
            ));
        }

        return $group;
    }

    function meza_register_acf_export_tool_local_definitions(): void
    {
        static $did_register = false;

        if (
            $did_register
            || !meza_is_acf_export_tools_screen()
            || !function_exists('acf_add_local_field_group')
            || !function_exists('acf_add_local_internal_post_type')
        ) {
            return;
        }

        meza_with_acf_export_local_enabled(static function () use (&$did_register): void {
            if ($did_register) {
                return;
            }

            $did_register = true;

            foreach (meza_get_local_acf_post_type_definitions() as $definition) {
                if (!is_array($definition) || empty($definition['key'])) {
                    continue;
                }

                acf_add_local_internal_post_type($definition, 'acf-post-type');
            }

            foreach (meza_get_local_acf_taxonomy_definitions() as $definition) {
                if (!is_array($definition) || empty($definition['key'])) {
                    continue;
                }

                acf_add_local_internal_post_type($definition, 'acf-taxonomy');
            }

            foreach (meza_get_shared_project_acf_options_pages() as $page) {
                if (!is_array($page)) {
                    continue;
                }

                $page_slug = sanitize_key((string) ($page['menu_slug'] ?? ''));
                if ($page_slug === '') {
                    continue;
                }

                acf_add_options_page($page);

                $export_page = $page;
                $export_page['key'] = !empty($export_page['key'])
                    ? (string) $export_page['key']
                    : 'ui_options_page_' . $page_slug;
                $export_page['title'] = (string) ($export_page['page_title'] ?? $export_page['menu_title'] ?? $page_slug);
                $export_page['menu_slug'] = $page_slug;
                $export_page['active'] = array_key_exists('active', $export_page) ? (bool) $export_page['active'] : true;

                acf_add_local_internal_post_type($export_page, 'acf-ui-options-page');
            }

            foreach (meza_get_builtin_acf_field_group_definitions() as $group) {
                if (!is_array($group) || empty($group['key'])) {
                    continue;
                }

                acf_add_local_field_group(meza_prepare_acf_local_field_group_for_export($group));
            }
        });
    }
}

if (!function_exists('meza_get_mergeable_db_acf_field_groups_for_local_group')) {
    function meza_get_mergeable_db_acf_field_groups_for_local_group(array $local_group): array
    {
        static $groups_by_signature = null;

        $signature = meza_get_acf_field_group_merge_signature($local_group);
        if ($signature === '') {
            return [];
        }

        if ($groups_by_signature === null) {
            $groups_by_signature = [];

            if (function_exists('acf_get_raw_field_groups')) {
                foreach ((array) acf_get_raw_field_groups() as $raw_group) {
                    if (!is_array($raw_group)) {
                        continue;
                    }

                    $raw_signature = meza_get_acf_field_group_merge_signature($raw_group);
                    if ($raw_signature === '') {
                        continue;
                    }

                    $groups_by_signature[$raw_signature][] = $raw_group;
                }
            }
        }

        $matches = $groups_by_signature[$signature] ?? [];

        return array_values(array_filter($matches, static function (array $group) use ($local_group): bool {
            return (string) ($group['key'] ?? '') !== (string) ($local_group['key'] ?? '');
        }));
    }
}
