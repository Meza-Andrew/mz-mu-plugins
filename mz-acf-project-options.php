<?php

/**
 * Shared ACF option pages and field groups we want available on every project.
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) {
    return;
}

if (!function_exists('meza_get_business_information_type')) {
    function meza_get_business_information_type(): string
    {
        if (
            is_admin()
            && isset($_POST['acf'])
            && is_array($_POST['acf'])
            && array_key_exists('field_meza_business_type', $_POST['acf'])
        ) {
            $posted_value = sanitize_key(trim((string) wp_unslash($_POST['acf']['field_meza_business_type'])));
            if (in_array($posted_value, ['business', 'nonprofit', 'conference'], true)) {
                return $posted_value;
            }
        }

        $option_value = get_option('options_type');
        $value = is_scalar($option_value) ? trim((string) $option_value) : '';

        $value = sanitize_key($value);

        return in_array($value, ['business', 'nonprofit', 'conference'], true) ? $value : 'business';
    }
}

if (!function_exists('meza_is_conference_business_type')) {
    function meza_is_conference_business_type(): bool
    {
        return meza_get_business_information_type() === 'conference';
    }
}

if (!function_exists('meza_get_contact_page_id_for_acf_rules')) {
    function meza_get_contact_page_id_for_acf_rules(): int
    {
        if (function_exists('meza_get_page_by_candidate_slugs') && defined('MEZA_CONTACT_SLUGS')) {
            $page = meza_get_page_by_candidate_slugs(MEZA_CONTACT_SLUGS);
            if ($page instanceof WP_Post) {
                return (int) $page->ID;
            }
        }

        $page = get_page_by_path('contact', OBJECT, 'page');

        return $page instanceof WP_Post ? (int) $page->ID : 0;
    }
}

if (!function_exists('meza_supports_sponsor_features')) {
    function meza_supports_sponsor_features(): bool
    {
        return in_array(meza_get_business_information_type(), ['conference', 'nonprofit'], true);
    }
}

if (!function_exists('meza_business_information_acf_truthy')) {
    function meza_business_information_acf_truthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) !== 0;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('meza_business_information_enables_ecommerce')) {
    function meza_saved_business_information_enables_ecommerce(): bool
    {
        return meza_business_information_acf_truthy(get_option('options_ecommerce', 0));
    }

    function meza_should_seed_ecommerce_default_editable_acf_field_groups(): bool
    {
        if (is_admin() && meza_is_business_information_acf_submission() && isset($_POST['acf']) && is_array($_POST['acf'])) {
            return meza_business_information_enables_ecommerce();
        }

        return meza_saved_business_information_enables_ecommerce();
    }

    function meza_business_information_enables_ecommerce(): bool
    {
        if (is_admin() && meza_is_business_information_acf_submission() && isset($_POST['acf']) && is_array($_POST['acf'])) {
            if (array_key_exists('field_meza_business_ecommerce', $_POST['acf'])) {
                return meza_business_information_acf_truthy(wp_unslash($_POST['acf']['field_meza_business_ecommerce']));
            }

            return false;
        }

        return meza_saved_business_information_enables_ecommerce();
    }
}

if (!function_exists('meza_get_business_information_menu_label')) {
    function meza_get_business_information_menu_label(): string
    {
        $label_map = [
            'business' => 'Business Information',
            'nonprofit' => 'Nonprofit Information',
            'conference' => 'Conference Information',
        ];

        return $label_map[meza_get_business_information_type()] ?? $label_map['business'];
    }
}

if (!function_exists('meza_get_business_information_page_title')) {
    function meza_get_business_information_page_title(): string
    {
        $title_map = [
            'business' => 'Business Information Settings',
            'nonprofit' => 'Nonprofit Information Settings',
            'conference' => 'Conference Information',
        ];

        return $title_map[meza_get_business_information_type()] ?? $title_map['business'];
    }
}

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
}

if (!function_exists('meza_is_hardcoded_acf_field_group')) {
    function meza_is_hardcoded_acf_field_group(array $field_group): bool
    {
        $local = (string) ($field_group['local'] ?? '');
        return $local === 'php';
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

        if ($post_type === 'acf-taxonomy' && !meza_supports_sponsor_features()) {
            $slug = sanitize_key((string) ($post['taxonomy'] ?? ''));
            if ($slug === 'sponsor-type') {
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
    function meza_get_acf_export_tool_definition_key_map(string $post_type, string $group): array
    {
        $definitions = [];

        if ($group === 'built_in') {
            switch ($post_type) {
                case 'acf-field-group':
                    $definitions = meza_get_shared_project_acf_field_groups();
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
                    $definitions = meza_get_default_editable_acf_field_group_definitions();
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

        $default_key_map = meza_get_acf_export_tool_definition_key_map($post_type, 'defaults');
        $built_in_key_map = meza_get_acf_export_tool_definition_key_map($post_type, 'built_in');

        foreach (meza_get_acf_export_tool_choices($post_type) as $choice) {
            $key = (string) ($choice['key'] ?? '');
            $title = trim((string) ($choice['title'] ?? ''));

            if ($key === '' || $title === '') {
                continue;
            }

            if (isset($built_in_key_map[$key])) {
                $groups['built_in'][$key] = $title;
                continue;
            }

            if (isset($default_key_map[$key])) {
                $groups['defaults'][$key] = $title;
                continue;
            }

            $groups['custom'][$key] = $title;
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

            foreach (meza_get_shared_project_acf_field_groups() as $group) {
                if (!is_array($group) || empty($group['key'])) {
                    continue;
                }

                acf_add_local_field_group($group);
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

if (!function_exists('meza_get_theme_thumbnail_id')) {
    function meza_get_theme_thumbnail_id(): int
    {
        $thumbnail_id = (int) get_option('meza_theme_thumbnail_id', 0);

        return $thumbnail_id > 0 && get_post_type($thumbnail_id) === 'attachment'
            ? $thumbnail_id
            : 0;
    }
}

if (!function_exists('meza_get_social_share_default_id')) {
    function meza_get_social_share_default_id(): int
    {
        $default_id = (int) get_option('meza_social_share_default_id', 0);
        if ($default_id > 0 && get_post_type($default_id) === 'attachment') {
            return $default_id;
        }

        return meza_get_theme_thumbnail_id();
    }
}

if (!function_exists('meza_get_social_share_default_url')) {
    function meza_get_social_share_default_url(): string
    {
        $default_id = meza_get_social_share_default_id();
        if ($default_id <= 0) {
            return '';
        }

        $default_url = wp_get_attachment_image_url($default_id, 'full');

        return is_string($default_url) ? $default_url : '';
    }
}

if (!function_exists('meza_post_uses_share_image_permalink')) {
    function meza_post_uses_share_image_permalink(int $post_id): bool
    {
        $post = get_post($post_id);
        if (!($post instanceof WP_Post)) {
            return false;
        }

        if (in_array((string) $post->post_status, ['auto-draft', 'trash'], true)) {
            return false;
        }

        $post_type = trim((string) $post->post_type);
        if ($post_type === '') {
            return false;
        }

        $post_type_object = get_post_type_object($post_type);
        if (!($post_type_object instanceof WP_Post_Type)) {
            return false;
        }

        if (function_exists('is_post_type_viewable') && !is_post_type_viewable($post_type_object)) {
            return false;
        }

        if ($post_type === 'post' || $post_type === 'page') {
            return true;
        }

        return !empty($post_type_object->rewrite) || !empty($post_type_object->query_var);
    }
}

if (!function_exists('meza_get_permalink_enabled_post_type_slugs_option_name')) {
    function meza_get_permalink_enabled_post_type_slugs_option_name(): string
    {
        return 'meza_permalink_enabled_post_type_slugs_v1';
    }
}

if (!function_exists('meza_sort_permalink_enabled_post_type_slugs')) {
    function meza_sort_permalink_enabled_post_type_slugs(array $post_type_slugs): array
    {
        $post_type_slugs = array_values(array_unique(array_filter(array_map('sanitize_key', $post_type_slugs))));

        usort($post_type_slugs, static function (string $left, string $right): int {
            $priority = [
                'page' => 0,
                'post' => 1,
            ];

            $left_priority = $priority[$left] ?? 10;
            $right_priority = $priority[$right] ?? 10;

            if ($left_priority !== $right_priority) {
                return $left_priority <=> $right_priority;
            }

            return strcmp($left, $right);
        });

        return $post_type_slugs;
    }
}

if (!function_exists('meza_collect_current_permalink_enabled_post_type_slugs')) {
    function meza_collect_current_permalink_enabled_post_type_slugs(): array
    {
        $post_type_objects = get_post_types(['show_ui' => true], 'objects');
        if (!is_array($post_type_objects)) {
            return ['page', 'post'];
        }

        $post_type_slugs = [];

        foreach ($post_type_objects as $post_type => $post_type_object) {
            $post_type = sanitize_key((string) $post_type);
            if ($post_type === '' || $post_type === 'attachment' || !($post_type_object instanceof WP_Post_Type)) {
                continue;
            }

            if (function_exists('is_post_type_viewable') && !is_post_type_viewable($post_type_object)) {
                continue;
            }

            if ($post_type !== 'post' && $post_type !== 'page' && empty($post_type_object->rewrite) && empty($post_type_object->query_var)) {
                continue;
            }

            $post_type_slugs[] = $post_type;
        }

        return meza_sort_permalink_enabled_post_type_slugs($post_type_slugs);
    }
}

if (!function_exists('meza_get_permalink_enabled_post_type_slugs')) {
    function meza_get_permalink_enabled_post_type_slugs(): array
    {
        $cached_post_type_slugs = get_option(meza_get_permalink_enabled_post_type_slugs_option_name(), []);
        if (!is_array($cached_post_type_slugs)) {
            $cached_post_type_slugs = [];
        }

        return meza_sort_permalink_enabled_post_type_slugs(array_merge(
            $cached_post_type_slugs,
            meza_collect_current_permalink_enabled_post_type_slugs()
        ));
    }
}

if (!function_exists('meza_refresh_permalink_enabled_post_type_slugs')) {
    function meza_refresh_permalink_enabled_post_type_slugs(): void
    {
        update_option(
            meza_get_permalink_enabled_post_type_slugs_option_name(),
            meza_collect_current_permalink_enabled_post_type_slugs(),
            false
        );
    }
}
add_action('init', 'meza_refresh_permalink_enabled_post_type_slugs', 100);

if (!function_exists('meza_get_acf_location_rules_for_permalink_post_types')) {
    function meza_get_acf_location_rules_for_permalink_post_types(): array
    {
        $location = [];

        foreach (meza_get_permalink_enabled_post_type_slugs() as $post_type) {
            $location[] = [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => $post_type,
                ],
            ];
        }

        return $location;
    }
}

if (!function_exists('meza_get_permalink_enabled_taxonomy_slugs')) {
    function meza_get_permalink_enabled_taxonomy_slugs(): array
    {
        $taxonomy_slugs = [];

        foreach ((array) get_taxonomies(['public' => true], 'objects') as $taxonomy_slug => $taxonomy_object) {
            if (!is_string($taxonomy_slug) || $taxonomy_slug === '') {
                continue;
            }

            if (!($taxonomy_object instanceof WP_Taxonomy)) {
                continue;
            }

            if (empty($taxonomy_object->query_var)) {
                continue;
            }

            $rewrite = $taxonomy_object->rewrite;
            if ($rewrite === false) {
                continue;
            }

            $taxonomy_slugs[] = $taxonomy_slug;
        }

        sort($taxonomy_slugs, SORT_STRING);
        return array_values(array_unique($taxonomy_slugs));
    }
}

if (!function_exists('meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies')) {
    function meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies(): array
    {
        $location = meza_get_acf_location_rules_for_permalink_post_types();

        foreach (meza_get_permalink_enabled_taxonomy_slugs() as $taxonomy_slug) {
            $location[] = [
                [
                    'param' => 'taxonomy',
                    'operator' => '==',
                    'value' => $taxonomy_slug,
                ],
            ];
        }

        return $location;
    }
}

if (!function_exists('meza_get_post_explicit_social_share_image_id')) {
    function meza_get_post_explicit_social_share_image_id(int $post_id): int
    {
        if ($post_id <= 0) {
            return 0;
        }

        foreach (
            [
                '_yoast_wpseo_opengraph-image-id',
                '_yoast_wpseo_twitter-image-id',
            ] as $meta_key
        ) {
            $attachment_id = absint(get_post_meta($post_id, $meta_key, true));
            if ($attachment_id > 0 && get_post_type($attachment_id) === 'attachment') {
                return $attachment_id;
            }
        }

        foreach (
            [
                '_yoast_wpseo_opengraph-image',
                '_yoast_wpseo_twitter-image',
            ] as $meta_key
        ) {
            $image_url = trim((string) get_post_meta($post_id, $meta_key, true));
            if ($image_url === '') {
                continue;
            }

            $attachment_id = function_exists('attachment_url_to_postid')
                ? absint(attachment_url_to_postid($image_url))
                : 0;

            if ($attachment_id > 0 && get_post_type($attachment_id) === 'attachment') {
                return $attachment_id;
            }
        }

        return 0;
    }
}

if (!function_exists('meza_get_post_explicit_social_share_image_url')) {
    function meza_get_post_explicit_social_share_image_url(int $post_id): string
    {
        $attachment_id = meza_get_post_explicit_social_share_image_id($post_id);
        if ($attachment_id > 0) {
            $image_url = wp_get_attachment_image_url($attachment_id, 'full');
            if (is_string($image_url) && $image_url !== '') {
                return $image_url;
            }
        }

        foreach (
            [
                '_yoast_wpseo_opengraph-image',
                '_yoast_wpseo_twitter-image',
            ] as $meta_key
        ) {
            $image_url = trim((string) get_post_meta($post_id, $meta_key, true));
            if ($image_url !== '') {
                return $image_url;
            }
        }

        return '';
    }
}

if (!function_exists('meza_post_has_explicit_social_share_image')) {
    function meza_post_has_explicit_social_share_image(int $post_id): bool
    {
        if ($post_id <= 0) {
            return false;
        }

        if (meza_get_post_explicit_social_share_image_id($post_id) > 0) {
            return true;
        }

        return meza_get_post_explicit_social_share_image_url($post_id) !== '';
    }
}

if (!function_exists('meza_post_has_explicit_social_share_image_meta')) {
    function meza_post_has_explicit_social_share_image_meta(int $post_id, array $meta_keys): bool
    {
        if ($post_id <= 0) {
            return false;
        }

        foreach ($meta_keys as $meta_key) {
            $meta_value = trim((string) get_post_meta($post_id, (string) $meta_key, true));
            if ($meta_value !== '') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('meza_get_post_resolved_social_share_image_id')) {
    function meza_get_post_resolved_social_share_image_id(int $post_id): int
    {
        $explicit_attachment_id = meza_get_post_explicit_social_share_image_id($post_id);
        if ($explicit_attachment_id > 0) {
            return $explicit_attachment_id;
        }

        if (!meza_post_uses_share_image_permalink($post_id)) {
            return 0;
        }

        return meza_get_social_share_default_id();
    }
}

if (!function_exists('meza_get_post_resolved_social_share_image_url')) {
    function meza_get_post_resolved_social_share_image_url(int $post_id): string
    {
        $explicit_image_url = meza_get_post_explicit_social_share_image_url($post_id);
        if ($explicit_image_url !== '') {
            return $explicit_image_url;
        }

        if (!meza_post_uses_share_image_permalink($post_id)) {
            return '';
        }

        return meza_get_social_share_default_url();
    }
}

if (!function_exists('meza_post_is_using_site_default_social_share_image')) {
    function meza_post_is_using_site_default_social_share_image(int $post_id): bool
    {
        if ($post_id <= 0 || meza_post_has_explicit_social_share_image($post_id)) {
            return false;
        }

        return meza_post_uses_share_image_permalink($post_id) && meza_get_social_share_default_id() > 0;
    }
}

if (!function_exists('meza_get_social_share_default_image_payload')) {
    function meza_get_social_share_default_image_payload(): array
    {
        $attachment_id = meza_get_social_share_default_id();
        $image_url = meza_get_social_share_default_url();

        if ($attachment_id <= 0 || $image_url === '') {
            return [];
        }

        return [
            'id' => $attachment_id,
            'url' => $image_url,
            'alt' => trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true)),
            'warnings' => [],
        ];
    }
}

if (!function_exists('meza_get_theme_thumbnail_allowed_extensions')) {
    function meza_get_theme_thumbnail_allowed_extensions(): array
    {
        return ['png', 'gif', 'jpg', 'jpeg', 'webp', 'avif'];
    }
}

if (!function_exists('meza_theme_thumbnail_attachment_is_supported')) {
    function meza_theme_thumbnail_attachment_is_supported(int $attachment_id): bool
    {
        $mime_type = strtolower((string) get_post_mime_type($attachment_id));

        return in_array($mime_type, [
            'image/png',
            'image/gif',
            'image/jpeg',
            'image/webp',
            'image/avif',
        ], true);
    }
}

if (!function_exists('meza_get_shared_project_acf_options_pages')) {
    function meza_get_shared_project_acf_options_pages(): array
    {
        $pages = [
            [
                'page_title'      => meza_get_business_information_page_title(),
                'menu_title'      => meza_get_business_information_menu_label(),
                'menu_slug'       => 'business-information',
                'parent_slug'     => 'options-general.php',
                'capability'      => 'manage_options',
                'redirect'        => false,
                'update_button'   => 'Update',
                'updated_message' => 'Settings Updated',
                'autoload'        => false,
            ],
            [
                'page_title'      => 'Branding Settings',
                'menu_title'      => 'Branding',
                'menu_slug'       => 'branding',
                'parent_slug'     => 'options-general.php',
                'capability'      => 'manage_options',
                'redirect'        => false,
                'update_button'   => 'Update',
                'updated_message' => 'Options Updated',
                'autoload'        => false,
            ],
            [
                'page_title'      => 'CRM Integration Settings',
                'menu_title'      => 'CRM Integration',
                'menu_slug'       => 'crm',
                'parent_slug'     => 'options-general.php',
                'capability'      => 'manage_options',
                'redirect'        => false,
                'update_button'   => 'Update',
                'updated_message' => 'Settings Updated',
                'autoload'        => false,
            ],
        ];

        $pages[] = [
            'page_title'      => 'Conference',
            'menu_title'      => 'Conference',
            'menu_slug'       => 'conference',
            'parent_slug'     => 'none',
            'capability'      => 'manage_options',
            'redirect'        => false,
            'icon_url'        => 'dashicons-tickets-alt',
            'update_button'   => 'Update Conference',
            'updated_message' => 'Conference Updated',
            'autoload'        => false,
        ];

        $pages[] = [
            'page_title'      => 'Schedule',
            'menu_title'      => 'Schedule',
            'menu_slug'       => 'conference-schedule',
            'parent_slug'     => 'conference',
            'capability'      => 'manage_options',
            'position'        => 2,
            'redirect'        => false,
            'update_button'   => 'Update Schedule',
            'updated_message' => 'Schedule Updated',
            'autoload'        => false,
        ];

        $pages = apply_filters('meza_shared_project_acf_options_pages', $pages);

        return is_array($pages) ? array_values($pages) : [];
    }
}

if (!function_exists('meza_get_shared_project_acf_options_page_slugs')) {
    function meza_get_shared_project_acf_options_page_slugs(): array
    {
        $slugs = [];

        foreach (meza_get_shared_project_acf_options_pages() as $page) {
            $slug = sanitize_key((string) ($page['menu_slug'] ?? ''));
            if ($slug !== '') {
                $slugs[$slug] = true;
            }
        }

        return array_keys($slugs);
    }
}

if (!function_exists('meza_get_legacy_shared_project_acf_options_page_slug_map')) {
    function meza_get_legacy_shared_project_acf_options_page_slug_map(): array
    {
        return [
            'business-info' => 'business-information',
            'organization-info' => 'business-information',
            'event-info' => 'conference',
        ];
    }
}

if (!function_exists('meza_get_shared_project_acf_field_groups')) {
    function meza_get_business_information_general_fields(): array
    {
        return [
            [
                'key' => 'field_meza_business_site_title',
                'label' => 'Name',
                'name' => 'wp_site_title',
                'aria-label' => '',
                'type' => 'text',
                'instructions' => 'Updates the WordPress site name used in browser tabs, admin screens, and fallback branding.',
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
                'key' => 'field_meza_business_tagline',
                'label' => 'Tagline',
                'name' => 'wp_tagline',
                'aria-label' => '',
                'type' => 'text',
                'instructions' => 'Updates the WordPress site tagline used in metadata and select templates.',
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
                'key' => 'field_meza_business_type',
                'label' => 'Type',
                'name' => 'type',
                'aria-label' => '',
                'type' => 'radio',
                'instructions' => '',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'choices' => [
                    'business' => 'Business',
                    'nonprofit' => 'Nonprofit',
                    'conference' => 'Conference',
                ],
                'default_value' => 'business',
                'return_format' => 'value',
                'allow_null' => 0,
                'other_choice' => 0,
                'save_other_choice' => 0,
                'layout' => 'horizontal',
            ],
            [
                'key' => 'field_meza_business_ecommerce',
                'label' => 'E-Commerce',
                'name' => 'ecommerce',
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
        ];
    }

    function meza_get_business_information_locations_fields(): array
    {
        return [
            [
                'key' => 'field_meza_business_locations',
                'label' => 'Locations',
                'name' => 'locations',
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
                'layout' => 'block',
                'pagination' => 0,
                'min' => 0,
                'max' => 0,
                'collapsed' => 'field_meza_business_location_name',
                'button_label' => 'Add Location',
                'rows_per_page' => 20,
                'sub_fields' => [
                    [
                        'key' => 'field_meza_business_location_name',
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
                        'default_value' => 'localities',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'parent_repeater' => 'field_meza_business_locations',
                    ],
                    [
                        'key' => 'field_meza_business_location_primary',
                        'label' => 'Primary',
                        'name' => 'primary',
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
                        'message' => 'Is this the primary location?',
                        'default_value' => 0,
                        'allow_in_bindings' => 0,
                        'ui' => 0,
                        'ui_on_text' => '',
                        'ui_off_text' => '',
                        'parent_repeater' => 'field_meza_business_locations',
                    ],
                    [
                        'key' => 'field_meza_business_location_phone',
                        'label' => 'Phone',
                        'name' => 'phone',
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
                        'parent_repeater' => 'field_meza_business_locations',
                    ],
                    [
                        'key' => 'field_meza_business_location_email',
                        'label' => 'Email',
                        'name' => 'email',
                        'aria-label' => '',
                        'type' => 'email',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'parent_repeater' => 'field_meza_business_locations',
                    ],
                    [
                        'key' => 'field_meza_business_location_address',
                        'label' => 'Address',
                        'name' => 'address',
                        'aria-label' => '',
                        'type' => 'google_map',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'center_lat' => '',
                        'center_lng' => '',
                        'zoom' => '',
                        'height' => '',
                        'allow_in_bindings' => 0,
                        'parent_repeater' => 'field_meza_business_locations',
                    ],
                    [
                        'key' => 'field_meza_business_location_hours',
                        'label' => 'Hours',
                        'name' => 'hours',
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
                        'min' => 0,
                        'max' => 0,
                        'rows_per_page' => 20,
                        'layout' => 'table',
                        'button_label' => 'Add Row',
                        'collapsed' => '',
                        'sub_fields' => [
                            [
                                'key' => 'field_meza_business_location_hours_day',
                                'label' => 'Day',
                                'name' => 'day',
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
                                'parent_repeater' => 'field_meza_business_location_hours',
                            ],
                            [
                                'key' => 'field_meza_business_location_hours_time',
                                'label' => 'Time',
                                'name' => 'time',
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
                                'parent_repeater' => 'field_meza_business_location_hours',
                            ],
                        ],
                        'parent_repeater' => 'field_meza_business_locations',
                    ],
                    [
                        'key' => 'field_meza_business_location_note',
                        'label' => 'Note',
                        'name' => 'note',
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
                        'allow_in_bindings' => 1,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'parent_repeater' => 'field_meza_business_locations',
                    ],
                ],
            ],
        ];
    }

    function meza_get_contact_locations_section_fields(): array
    {
        return [
            [
                'key' => 'field_meza_contact_locations_visibility',
                'label' => 'Visibility',
                'name' => 'show_list-locations',
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
                'message' => 'Show the List Locations section this page?',
                'default_value' => 0,
                'allow_in_bindings' => 0,
                'ui' => 0,
                'ui_on_text' => '',
                'ui_off_text' => '',
            ],
            [
                'key' => 'field_meza_contact_locations_section_group',
                'label' => 'Section',
                'name' => 'section_list-locations',
                'aria-label' => '',
                'type' => 'group',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_meza_contact_locations_visibility',
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
                        'key' => 'field_meza_contact_locations_headline',
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
                        'default_value' => 'localities',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ],
                    [
                        'key' => 'field_meza_contact_locations_subhead',
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
                        'key' => 'field_meza_contact_locations_id',
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
                        'default_value' => 'localities',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ],
                ],
            ],
        ];
    }

    function meza_get_contact_locations_section_field_group_definition(): array
    {
        return [
            'key' => 'group_meza_contact_locations_section',
            'title' => 'List Locations Section',
            'fields' => meza_get_contact_locations_section_fields(),
            'location' => [
                [
                    [
                        'param' => 'page',
                        'operator' => '==',
                        'value' => (string) meza_get_contact_page_id_for_acf_rules(),
                    ],
                ],
                [
                    [
                        'param' => 'page_type',
                        'operator' => '==',
                        'value' => 'front_page',
                    ],
                ],
            ],
            'menu_order' => 18,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => '',
            'show_in_rest' => 0,
            'display_title' => '',
        ];
    }

    function meza_get_business_information_contact_fields(): array
    {
        return [
            [
                'key' => 'field_meza_business_phone_group',
                'label' => 'Phone',
                'name' => 'phone_group',
                'aria-label' => '',
                'type' => 'group',
                'instructions' => 'Used in the footer of emails sent through the website.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'key' => 'field_69b5a28923bbe',
                        'label' => '',
                        'name' => 'phone',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_meza_business_use_primary_location_phone',
                                    'operator' => '!=',
                                    'value' => '1',
                                ],
                            ],
                        ],
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
                        'key' => 'field_meza_business_use_primary_location_phone',
                        'label' => '',
                        'name' => 'use_primary_location_phone',
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
                        'message' => 'Use primary location\'s phone',
                        'default_value' => 0,
                        'allow_in_bindings' => 0,
                        'ui' => 0,
                        'ui_on_text' => '',
                        'ui_off_text' => '',
                    ],
                ],
            ],
            [
                'key' => 'field_meza_business_email_group',
                'label' => 'Email',
                'name' => 'email_group',
                'aria-label' => '',
                'type' => 'group',
                'instructions' => 'The default recipient for forms. Also used in the footer of emails sent through the website.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'key' => 'field_68c133a0425a8',
                        'label' => '',
                        'name' => 'email',
                        'aria-label' => '',
                        'type' => 'email',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_meza_business_use_primary_location_email',
                                    'operator' => '!=',
                                    'value' => '1',
                                ],
                            ],
                        ],
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ],
                    [
                        'key' => 'field_meza_business_use_primary_location_email',
                        'label' => '',
                        'name' => 'use_primary_location_email',
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
                        'message' => 'Use primary location\'s email',
                        'default_value' => 0,
                        'allow_in_bindings' => 0,
                        'ui' => 0,
                        'ui_on_text' => '',
                        'ui_off_text' => '',
                    ],
                ],
            ],
            [
                'key' => 'field_meza_business_address_group',
                'label' => 'Mailing Address',
                'name' => 'address_group',
                'aria-label' => '',
                'type' => 'group',
                'instructions' => 'Used in the footer of emails sent through the website. Only put an address in this field if visitors can come to a real location.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'key' => 'field_69b5a2b623bc0',
                        'label' => '',
                        'name' => 'address',
                        'aria-label' => '',
                        'type' => 'google_map',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_meza_business_use_primary_location_address',
                                    'operator' => '!=',
                                    'value' => '1',
                                ],
                                [
                                    'field' => 'field_meza_business_mailing_address',
                                    'operator' => '==empty',
                                ],
                            ],
                        ],
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'allow_in_bindings' => 0,
                        'center_lat' => '38.3032',
                        'center_lng' => '-77.4605',
                        'zoom' => '',
                        'height' => '',
                    ],
                    [
                        'key' => 'field_meza_business_use_primary_location_address',
                        'label' => '',
                        'name' => 'use_primary_location_address',
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
                        'message' => 'Use primary location\'s address',
                        'default_value' => 0,
                        'allow_in_bindings' => 0,
                        'ui' => 0,
                        'ui_on_text' => '',
                        'ui_off_text' => '',
                    ],
                ],
            ],
            [
                'key' => 'field_meza_business_mailing_address',
                'label' => 'Mailing Address',
                'name' => 'address_text',
                'aria-label' => '',
                'type' => 'textarea',
                'instructions' => 'Used in the footer of emails sent through the website. Only put a mailing-only address in this field, such as a P.O. Box.',
                'required' => 0,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_meza_business_use_primary_location_address',
                            'operator' => '!=',
                            'value' => '1',
                        ],
                        [
                            'field' => 'field_69b5a2b623bc0',
                            'operator' => '==empty',
                        ],
                    ],
                ],
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
            ],
        ];
    }

    function meza_get_business_information_locations_option_rows(): array
    {
        if (
            is_admin()
            && isset($_POST['acf']['field_meza_business_locations'])
            && is_array($_POST['acf']['field_meza_business_locations'])
        ) {
            return array_values(array_filter(
                wp_unslash($_POST['acf']['field_meza_business_locations']),
                'is_array'
            ));
        }

        if (!function_exists('get_field')) {
            return meza_get_business_information_locations_rows_from_raw_options();
        }

        $rows = get_field('locations', 'option');

        if (is_array($rows)) {
            return array_values(array_filter($rows, 'is_array'));
        }

        return meza_get_business_information_locations_rows_from_raw_options();
    }

    function meza_get_business_information_locations_rows_from_raw_options(): array
    {
        $row_count = (int) get_option('options_locations', 0);
        if ($row_count <= 0) {
            return [];
        }

        $rows = [];

        for ($index = 0; $index < $row_count; $index++) {
            $row = [
                'name' => (string) get_option("options_locations_{$index}_name", ''),
                'primary' => get_option("options_locations_{$index}_primary", ''),
                'phone' => (string) get_option("options_locations_{$index}_phone", ''),
                'email' => (string) get_option("options_locations_{$index}_email", ''),
                'address' => get_option("options_locations_{$index}_address", null),
                'note' => (string) get_option("options_locations_{$index}_note", ''),
            ];

            $hours = [];
            $hours_count = (int) get_option("options_locations_{$index}_hours", 0);

            for ($hour_index = 0; $hour_index < $hours_count; $hour_index++) {
                $hours[] = [
                    'day' => (string) get_option("options_locations_{$index}_hours_{$hour_index}_day", ''),
                    'time' => (string) get_option("options_locations_{$index}_hours_{$hour_index}_time", ''),
                ];
            }

            if (!empty($hours)) {
                $row['hours'] = $hours;
            }

            $rows[] = $row;
        }

        return array_values(array_filter($rows, static function (array $row): bool {
            foreach (['name', 'phone', 'email', 'note'] as $key) {
                if (trim((string) ($row[$key] ?? '')) !== '') {
                    return true;
                }
            }

            if (!empty($row['primary'])) {
                return true;
            }

            if (meza_acf_google_map_has_meaningful_value($row['address'] ?? null)) {
                return true;
            }

            return !empty($row['hours']);
        }));
    }

    function meza_should_register_contact_locations_section_group(): bool
    {
        return (int) get_option('options_locations', 0) > 1
            && meza_get_contact_page_id_for_acf_rules() > 0;
    }

    function meza_count_primary_business_information_locations($rows): int
    {
        if (!is_array($rows)) {
            return 0;
        }

        $primary_count = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (!empty($row['primary'])) {
                $primary_count++;
            }
        }

        return $primary_count;
    }

    function meza_business_information_has_primary_location(?array $rows = null): bool
    {
        if ($rows === null) {
            $rows = meza_get_business_information_locations_option_rows();
        }

        return meza_count_primary_business_information_locations($rows) > 0;
    }

    function meza_get_primary_business_information_location(?array $rows = null): array
    {
        if ($rows === null) {
            $rows = meza_get_business_information_locations_option_rows();
        }

        if (!is_array($rows)) {
            return [];
        }

        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['primary'])) {
                continue;
            }

            return $row;
        }

        return [];
    }

    function meza_get_business_information_contact_group(string $group_name): array
    {
        if (!function_exists('get_field')) {
            return [];
        }

        $value = get_field($group_name, 'option');

        return is_array($value) ? $value : [];
    }

    function meza_get_business_information_contact_field_value(string $field_name, string $group_name, string $legacy_name): string
    {
        if (!function_exists('get_field')) {
            return '';
        }

        $group = meza_get_business_information_contact_group($group_name);
        if (array_key_exists($field_name, $group)) {
            return is_scalar($group[$field_name]) ? trim((string) $group[$field_name]) : '';
        }

        return trim((string) get_field($legacy_name, 'option'));
    }

    function meza_business_information_uses_primary_location_field(string $field_name): bool
    {
        if (!function_exists('get_field')) {
            return false;
        }

        $group_map = [
            'phone' => ['group' => 'phone_group', 'toggle' => 'use_primary_location_phone'],
            'email' => ['group' => 'email_group', 'toggle' => 'use_primary_location_email'],
            'address' => ['group' => 'address_group', 'toggle' => 'use_primary_location_address'],
        ];

        $config = $group_map[$field_name] ?? null;
        if (!is_array($config)) {
            return false;
        }

        $group = meza_get_business_information_contact_group((string) $config['group']);
        if (array_key_exists((string) $config['toggle'], $group)) {
            return !empty($group[(string) $config['toggle']]);
        }

        return !empty(get_field((string) $config['toggle'], 'option'));
    }

    function meza_get_primary_business_information_location_field_value(string $field_name, ?array $rows = null)
    {
        $primary_location = meza_get_primary_business_information_location($rows);
        if (empty($primary_location)) {
            return null;
        }

        $value = $primary_location[$field_name] ?? null;

        if ($field_name === 'address') {
            return meza_acf_google_map_has_meaningful_value($value) ? $value : null;
        }

        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value !== '' ? $value : null;
    }

    function meza_get_business_information_phone(): string
    {
        if (meza_business_information_uses_primary_location_field('phone')) {
            $primary_phone = meza_get_primary_business_information_location_field_value('phone');
            return is_string($primary_phone) ? $primary_phone : '';
        }

        return meza_get_business_information_contact_field_value('phone', 'phone_group', 'phone');
    }

    function meza_get_business_information_email(): string
    {
        if (meza_business_information_uses_primary_location_field('email')) {
            $primary_email = meza_get_primary_business_information_location_field_value('email');
            return is_string($primary_email) ? $primary_email : '';
        }

        return meza_get_business_information_contact_field_value('email', 'email_group', 'email');
    }

    function meza_get_business_information_address(): array
    {
        if (!function_exists('get_field')) {
            return ['raw' => null, 'text' => ''];
        }

        if (meza_business_information_uses_primary_location_field('address')) {
            return [
                'raw' => meza_get_primary_business_information_location_field_value('address'),
                'text' => '',
            ];
        }

        $group = meza_get_business_information_contact_group('address_group');
        $group_raw = $group['address'] ?? null;

        return [
            'raw' => meza_acf_google_map_has_meaningful_value($group_raw) ? $group_raw : get_field('address', 'option'),
            'text' => trim((string) get_field('address_text', 'option')),
        ];
    }

    function meza_validate_business_information_primary_location_dependency($valid, string $field_name, $value)
    {
        return $valid;
    }

    function meza_validate_business_information_email_group($valid, $value, array $field = [])
    {
        $posted_group = meza_get_posted_acf_field_value((string) ($field['key'] ?? ''));
        $group = is_array($posted_group) ? $posted_group : (is_array($value) ? $value : []);
        $use_primary = !empty(meza_get_posted_acf_group_subfield_value(
            $group,
            'field_meza_business_use_primary_location_email',
            'use_primary_location_email'
        ));
        $email = sanitize_email((string) meza_get_posted_acf_group_subfield_value(
            $group,
            'field_68c133a0425a8',
            'email'
        ));

        if ($use_primary) {
            return true;
        }

        return is_email($email) ? true : 'value is required';
    }

    function meza_validate_business_information_address_group($valid, $value, array $field = [])
    {
        $posted_group = meza_get_posted_acf_field_value((string) ($field['key'] ?? ''));
        $group = is_array($posted_group) ? $posted_group : (is_array($value) ? $value : []);
        $use_primary = !empty(meza_get_posted_acf_group_subfield_value(
            $group,
            'field_meza_business_use_primary_location_address',
            'use_primary_location_address'
        ));
        $address = meza_get_posted_acf_group_subfield_value(
            $group,
            'field_69b5a2b623bc0',
            'address'
        );
        $address_text = trim((string) meza_get_posted_acf_field_value('field_meza_business_mailing_address'));
        if ($address_text === '' && function_exists('get_field')) {
            $address_text = trim((string) get_field('address_text', 'option'));
        }

        if ($use_primary) {
            return true;
        }

        if (meza_acf_google_map_has_meaningful_value($address) || $address_text !== '') {
            return true;
        }

        return 'value is required';
    }

    function meza_is_business_information_acf_submission(): bool
    {
        if (
            isset($_POST['acf'])
            && is_array($_POST['acf'])
            && (
                array_key_exists('field_meza_business_email_group', $_POST['acf'])
                || array_key_exists('field_meza_business_address_group', $_POST['acf'])
                || array_key_exists('field_meza_business_mailing_address', $_POST['acf'])
            )
        ) {
            return true;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';

        return $page === 'business-information';
    }

    function meza_get_posted_acf_field_value(string $field_key)
    {
        if (!isset($_POST['acf']) || !is_array($_POST['acf']) || !array_key_exists($field_key, $_POST['acf'])) {
            return null;
        }

        return wp_unslash($_POST['acf'][$field_key]);
    }

    function meza_get_posted_acf_group_subfield_value($group_value, string $field_key, string $field_name = '')
    {
        if (!is_array($group_value)) {
            return null;
        }

        if (array_key_exists($field_key, $group_value)) {
            return $group_value[$field_key];
        }

        if ($field_name !== '' && array_key_exists($field_name, $group_value)) {
            return $group_value[$field_name];
        }

        return null;
    }

    function meza_get_business_information_branding_fields(): array
    {
        return [
            [
                'key' => 'field_meza_business_site_logo',
                'label' => 'Site Logo',
                'name' => 'wp_site_logo',
                'aria-label' => '',
                'type' => 'image',
                'instructions' => 'Appears in the header, login screen, and email templates. Upload a transparent version when possible.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'return_format' => 'id',
                'library' => 'all',
                'preview_size' => 'meza_branding_preview',
                'min_width' => '',
                'min_height' => '',
                'min_size' => '',
                'max_width' => '',
                'max_height' => '',
                'max_size' => '',
                'mime_types' => '',
                'allow_in_bindings' => 0,
            ],
            [
                'key' => 'field_meza_business_site_logo_alt',
                'label' => 'Site Logo (Alternative)',
                'name' => 'wp_site_logo_alternative',
                'aria-label' => '',
                'type' => 'image',
                'instructions' => 'Appears in the footer and other dark sections. Upload a transparent version when possible.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'return_format' => 'id',
                'library' => 'all',
                'preview_size' => 'meza_branding_preview',
                'min_width' => '',
                'min_height' => '',
                'min_size' => '',
                'max_width' => '',
                'max_height' => '',
                'max_size' => '',
                'mime_types' => '',
                'allow_in_bindings' => 0,
            ],
            [
                'key' => 'field_meza_business_site_icon',
                'label' => 'Site Icon',
                'name' => 'wp_site_icon',
                'aria-label' => '',
                'type' => 'image',
                'instructions' => 'Used in browser tabs, bookmark bars, and mobile apps. Upload a square image that is at least 512 by 512 pixels.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'return_format' => 'id',
                'library' => 'all',
                'preview_size' => 'meza_branding_preview',
                'min_width' => '',
                'min_height' => '',
                'min_size' => '',
                'max_width' => '',
                'max_height' => '',
                'max_size' => '',
                'mime_types' => '',
                'allow_in_bindings' => 0,
            ],
            [
                'key' => 'field_meza_business_theme_thumbnail',
                'label' => 'Theme Thumbnail',
                'name' => 'wp_theme_thumbnail',
                'aria-label' => '',
                'type' => 'image',
                'instructions' => 'Used by WordPress to represent the website. Upload a 4:3 image that is at least 1200 by 900 pixels.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'return_format' => 'id',
                'library' => 'all',
                'preview_size' => 'meza_theme_thumbnail_preview',
                'min_width' => '',
                'min_height' => '',
                'min_size' => '',
                'max_width' => '',
                'max_height' => '',
                'max_size' => '',
                'mime_types' => 'png,gif,jpg,jpeg,webp,avif',
                'allow_in_bindings' => 0,
            ],
            [
                'key' => 'field_meza_business_social_share_default',
                'label' => 'Social Share Default',
                'name' => 'wp_social_share_default',
                'aria-label' => '',
                'type' => 'image',
                'instructions' => 'Used when a page or post does not have its own share image. Defaults to the Theme Thumbnail until you choose a replacement.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'return_format' => 'id',
                'library' => 'all',
                'preview_size' => 'meza_theme_thumbnail_preview',
                'min_width' => '',
                'min_height' => '',
                'min_size' => '',
                'max_width' => '',
                'max_height' => '',
                'max_size' => '',
                'mime_types' => 'png,gif,jpg,jpeg,webp,avif',
                'allow_in_bindings' => 0,
            ],
        ];
    }

    function meza_get_crm_integration_fields(): array
    {
        return [
            [
                'key' => 'field_69b5b54f29383',
                'label' => 'Platform',
                'name' => 'platform',
                'aria-label' => '',
                'type' => 'select',
                'instructions' => '',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'choices' => [
                    'Constant Contact' => 'Constant Contact',
                    'Go High Level' => 'Go High Level',
                    'MailChimp' => 'MailChimp',
                    'Zeffy' => 'Zeffy',
                ],
                'default_value' => false,
                'return_format' => 'value',
                'multiple' => 0,
                'allow_null' => 1,
                'allow_in_bindings' => 0,
                'ui' => 0,
                'ajax' => 0,
                'placeholder' => '',
                'create_options' => 0,
                'save_options' => 0,
            ],
            [
                'key' => 'field_69b5b6c6fe884',
                'label' => 'Constant Contact',
                'name' => 'constant-contact',
                'aria-label' => '',
                'type' => 'group',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_69b5b54f29383',
                            'operator' => '==',
                            'value' => 'Constant Contact',
                        ],
                    ],
                ],
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'layout' => 'row',
                'sub_fields' => [
                    [
                        'key' => 'field_69b5b6c6fe885',
                        'label' => 'Client ID',
                        'name' => 'client_id',
                        'aria-label' => '',
                        'type' => 'password',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ],
                    [
                        'key' => 'field_69b5c69ed8589',
                        'label' => 'Client Secret',
                        'name' => 'client_secret',
                        'aria-label' => '',
                        'type' => 'password',
                        'instructions' => '',
                        'required' => 1,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_69b5b6c6fe885',
                                    'operator' => '!=empty',
                                ],
                            ],
                        ],
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ],
                    [
                        'key' => 'field_69b5b6c6fe886',
                        'label' => 'List ID',
                        'name' => 'list',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 1,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_69b5b6c6fe885',
                                    'operator' => '!=empty',
                                ],
                                [
                                    'field' => 'field_69b5c69ed8589',
                                    'operator' => '!=empty',
                                ],
                            ],
                        ],
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
            [
                'key' => 'field_69b5d528ed70b',
                'label' => 'Mailchimp',
                'name' => 'mailchimp',
                'aria-label' => '',
                'type' => 'group',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_69b5b54f29383',
                            'operator' => '==',
                            'value' => 'MailChimp',
                        ],
                    ],
                ],
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'layout' => 'row',
                'sub_fields' => [
                    [
                        'key' => 'field_69b5d528ed70c',
                        'label' => 'API Key',
                        'name' => 'api',
                        'aria-label' => '',
                        'type' => 'password',
                        'instructions' => '',
                        'required' => 1,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ],
                    [
                        'key' => 'field_69b5d528ed70e',
                        'label' => 'List ID',
                        'name' => 'list',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 1,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_69b5d528ed70c',
                                    'operator' => '!=empty',
                                ],
                            ],
                        ],
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
        ];
    }

    function meza_get_conference_information_fields(): array
    {
        return [
            [
                'key' => 'field_688f802239c77',
                'label' => 'Date',
                'name' => 'event_date',
                'aria-label' => '',
                'type' => 'date_picker',
                'instructions' => '',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'display_format' => 'F j, Y',
                'return_format' => 'F j, Y',
                'first_day' => 1,
                'default_to_current_date' => 0,
                'allow_in_bindings' => 0,
            ],
            [
                'key' => 'field_688f809439c78',
                'label' => 'Start Time',
                'name' => 'event_start_time',
                'aria-label' => '',
                'type' => 'time_picker',
                'instructions' => '',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'display_format' => 'g:i a',
                'return_format' => 'g:i a',
                'allow_in_bindings' => 0,
            ],
            [
                'key' => 'field_688f80fe66ecc',
                'label' => 'End Time',
                'name' => 'event_end_time',
                'aria-label' => '',
                'type' => 'time_picker',
                'instructions' => '',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'display_format' => 'g:i a',
                'return_format' => 'g:i a',
                'allow_in_bindings' => 0,
            ],
            [
                'key' => 'field_688f81259766f',
                'label' => 'Location',
                'name' => 'event_location',
                'aria-label' => '',
                'type' => 'google_map',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'center_lat' => '',
                'center_lng' => '',
                'zoom' => '',
                'height' => '',
                'allow_in_bindings' => 0,
            ],
        ];
    }

    function meza_get_conference_schedule_fields(): array
    {
        return [
            [
                'key' => 'field_69f04bae33353',
                'label' => 'Segments',
                'name' => 'event_schedule',
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
                'layout' => 'row',
                'pagination' => 0,
                'min' => 1,
                'max' => 0,
                'collapsed' => 'field_69f04bfb33356',
                'button_label' => 'Add Segment',
                'rows_per_page' => 20,
                'sub_fields' => [
                    [
                        'key' => 'field_69f04bcc33354',
                        'label' => 'Start Time',
                        'name' => 'start_time',
                        'aria-label' => '',
                        'type' => 'time_picker',
                        'instructions' => '',
                        'required' => 1,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'display_format' => 'g:i a',
                        'return_format' => 'g:i a',
                        'allow_in_bindings' => 0,
                        'parent_repeater' => 'field_69f04bae33353',
                    ],
                    [
                        'key' => 'field_69f04be833355',
                        'label' => 'End Time',
                        'name' => 'end_time',
                        'aria-label' => '',
                        'type' => 'time_picker',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'display_format' => 'g:i a',
                        'return_format' => 'g:i a',
                        'allow_in_bindings' => 0,
                        'parent_repeater' => 'field_69f04bae33353',
                    ],
                    [
                        'key' => 'field_69f04bfb33356',
                        'label' => 'Segment',
                        'name' => 'segment',
                        'aria-label' => '',
                        'type' => 'relationship',
                        'instructions' => '',
                        'required' => 1,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'post_type' => [
                            'segment',
                        ],
                        'post_status' => [
                            'publish',
                        ],
                        'taxonomy' => '',
                        'filters' => [
                            'search',
                        ],
                        'return_format' => 'id',
                        'min' => 1,
                        'max' => 1,
                        'allow_in_bindings' => 0,
                        'elements' => [
                            'featured_image',
                        ],
                        'bidirectional' => 0,
                        'bidirectional_target' => [],
                        'parent_repeater' => 'field_69f04bae33353',
                    ],
                ],
            ],
        ];
    }

    function meza_get_segment_fields(): array
    {
        return [
            [
                'key' => 'field_69f04ade6d1e4',
                'label' => 'Speakers',
                'name' => 'speakers',
                'aria-label' => '',
                'type' => 'relationship',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'post_type' => [
                    'profile',
                ],
                'post_status' => [
                    'publish',
                ],
                'taxonomy' => [
                    'profile-type:speaker',
                ],
                'filters' => [
                    'search',
                ],
                'return_format' => 'id',
                'min' => '',
                'max' => 1,
                'allow_in_bindings' => 0,
                'elements' => [
                    'featured_image',
                ],
                'bidirectional' => 0,
                'bidirectional_target' => [],
            ],
            [
                'key' => 'field_69f04ade6d1e7',
                'label' => 'Sponsors',
                'name' => 'sponsors',
                'aria-label' => '',
                'type' => 'relationship',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'post_type' => [
                    'organization',
                ],
                'post_status' => [
                    'publish',
                ],
                'taxonomy' => [
                    'organization-type:sponsor',
                ],
                'filters' => [
                    'search',
                ],
                'return_format' => 'id',
                'min' => '',
                'max' => 3,
                'allow_in_bindings' => 0,
                'elements' => [
                    'featured_image',
                ],
                'bidirectional' => 0,
                'bidirectional_target' => [],
            ],
        ];
    }

    function meza_get_cta_post_type_definition(): array
    {
        return [
            'key' => 'post_type_697fe24b7fe3f',
            'title' => 'CTAs',
            'menu_order' => 0,
            'active' => true,
            'post_type' => 'cta',
            'advanced_configuration' => true,
            'import_source' => '',
            'import_date' => '',
            'allow_ai_access' => false,
            'ai_description' => '',
            'labels' => [
                'name' => 'CTAs',
                'singular_name' => 'CTA',
                'menu_name' => 'CTAs',
                'all_items' => 'All CTAs',
                'edit_item' => 'Edit CTA',
                'view_item' => 'View CTA',
                'view_items' => 'View CTAs',
                'add_new_item' => 'Add New CTA',
                'add_new' => 'Add New CTA',
                'new_item' => 'New CTA',
                'parent_item_colon' => 'Parent CTA:',
                'search_items' => 'Search CTAs',
                'not_found' => 'No ctas found',
                'not_found_in_trash' => 'No ctas found in Trash',
                'archives' => 'CTA Archives',
                'attributes' => 'CTA Attributes',
                'featured_image' => '',
                'set_featured_image' => '',
                'remove_featured_image' => '',
                'use_featured_image' => '',
                'insert_into_item' => 'Insert into cta',
                'uploaded_to_this_item' => 'Uploaded to this cta',
                'filter_items_list' => 'Filter ctas list',
                'filter_by_date' => 'Filter ctas by date',
                'items_list_navigation' => 'CTAs list navigation',
                'items_list' => 'CTAs list',
                'item_published' => 'CTA published.',
                'item_published_privately' => 'CTA published privately.',
                'item_reverted_to_draft' => 'CTA reverted to draft.',
                'item_scheduled' => 'CTA scheduled.',
                'item_updated' => 'CTA updated.',
                'item_link' => 'CTA Link',
                'item_link_description' => 'A link to a cta.',
            ],
            'description' => '',
            'public' => true,
            'hierarchical' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'admin_menu_parent' => '',
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => false,
            'show_in_rest' => true,
            'rest_base' => '',
            'rest_namespace' => 'wp/v2',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'menu_position' => '',
            'menu_icon' => [
                'type' => 'dashicons',
                'value' => 'dashicons-share-alt2',
            ],
            'rename_capabilities' => false,
            'singular_capability_name' => 'post',
            'plural_capability_name' => 'posts',
            'supports' => [
                'title',
                'excerpt',
                'custom-fields',
            ],
            'taxonomies' => '',
            'has_archive' => false,
            'has_archive_slug' => '',
            'rewrite' => [
                'permalink_rewrite' => 'no_permalink',
            ],
            'query_var' => 'post_type_key',
            'query_var_name' => '',
            'can_export' => true,
            'delete_with_user' => false,
            'register_meta_box_cb' => '',
            'enter_title_here' => '',
        ];
    }

    function meza_get_review_post_type_definition(): array
    {
        return [
            'key' => 'post_type_697fdc6d18380',
            'title' => 'Reviews',
            'menu_order' => 0,
            'active' => true,
            'post_type' => 'review',
            'advanced_configuration' => true,
            'import_source' => '',
            'import_date' => '',
            'allow_ai_access' => false,
            'ai_description' => '',
            'labels' => [
                'name' => 'Reviews',
                'singular_name' => 'Review',
                'menu_name' => 'Reviews',
                'all_items' => 'All Reviews',
                'edit_item' => 'Edit Review',
                'view_item' => 'View Review',
                'view_items' => 'View Reviews',
                'add_new_item' => 'Add New Review',
                'add_new' => 'Add New Review',
                'new_item' => 'New Review',
                'parent_item_colon' => 'Parent Review:',
                'search_items' => 'Search Reviews',
                'not_found' => 'No reviews found',
                'not_found_in_trash' => 'No reviews found in Trash',
                'archives' => 'Review Archives',
                'attributes' => 'Review Attributes',
                'featured_image' => '',
                'set_featured_image' => '',
                'remove_featured_image' => '',
                'use_featured_image' => '',
                'insert_into_item' => 'Insert into review',
                'uploaded_to_this_item' => 'Uploaded to this review',
                'filter_items_list' => 'Filter reviews list',
                'filter_by_date' => 'Filter reviews by date',
                'items_list_navigation' => 'Reviews list navigation',
                'items_list' => 'Reviews list',
                'item_published' => 'Review published.',
                'item_published_privately' => 'Review published privately.',
                'item_reverted_to_draft' => 'Review reverted to draft.',
                'item_scheduled' => 'Review scheduled.',
                'item_updated' => 'Review updated.',
                'item_link' => 'Review Link',
                'item_link_description' => 'A link to a review.',
            ],
            'description' => '',
            'public' => true,
            'hierarchical' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'admin_menu_parent' => '',
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => false,
            'show_in_rest' => true,
            'rest_base' => '',
            'rest_namespace' => 'wp/v2',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'menu_position' => '',
            'menu_icon' => [
                'type' => 'dashicons',
                'value' => 'dashicons-star-half',
            ],
            'rename_capabilities' => false,
            'singular_capability_name' => 'post',
            'plural_capability_name' => 'posts',
            'supports' => [
                'title',
                'editor',
                'page-attributes',
                'custom-fields',
            ],
            'taxonomies' => '',
            'has_archive' => false,
            'has_archive_slug' => '',
            'rewrite' => [
                'permalink_rewrite' => 'no_permalink',
            ],
            'query_var' => 'post_type_key',
            'query_var_name' => '',
            'can_export' => true,
            'delete_with_user' => false,
            'register_meta_box_cb' => '',
            'enter_title_here' => '',
        ];
    }

    function meza_get_faq_post_type_definition(): array
    {
        return [
            'key' => 'post_type_697fdfac7eb87',
            'title' => 'FAQs',
            'menu_order' => 0,
            'active' => true,
            'post_type' => 'faq',
            'advanced_configuration' => true,
            'import_source' => '',
            'import_date' => '',
            'allow_ai_access' => false,
            'ai_description' => '',
            'labels' => [
                'name' => 'FAQs',
                'singular_name' => 'FAQ',
                'menu_name' => 'FAQs',
                'all_items' => 'All FAQs',
                'edit_item' => 'Edit FAQ',
                'view_item' => 'View FAQ',
                'view_items' => 'View FAQs',
                'add_new_item' => 'Add New FAQ',
                'add_new' => 'Add New FAQ',
                'new_item' => 'New FAQ',
                'parent_item_colon' => 'Parent FAQ:',
                'search_items' => 'Search FAQs',
                'not_found' => 'No faqs found',
                'not_found_in_trash' => 'No faqs found in Trash',
                'archives' => 'FAQ Archives',
                'attributes' => 'FAQ Attributes',
                'featured_image' => '',
                'set_featured_image' => '',
                'remove_featured_image' => '',
                'use_featured_image' => '',
                'insert_into_item' => 'Insert into faq',
                'uploaded_to_this_item' => 'Uploaded to this faq',
                'filter_items_list' => 'Filter faqs list',
                'filter_by_date' => 'Filter faqs by date',
                'items_list_navigation' => 'FAQs list navigation',
                'items_list' => 'FAQs list',
                'item_published' => 'FAQ published.',
                'item_published_privately' => 'FAQ published privately.',
                'item_reverted_to_draft' => 'FAQ reverted to draft.',
                'item_scheduled' => 'FAQ scheduled.',
                'item_updated' => 'FAQ updated.',
                'item_link' => 'FAQ Link',
                'item_link_description' => 'A link to a faq.',
            ],
            'description' => '',
            'public' => true,
            'hierarchical' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'admin_menu_parent' => '',
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => false,
            'show_in_rest' => true,
            'rest_base' => '',
            'rest_namespace' => 'wp/v2',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'menu_position' => '',
            'menu_icon' => [
                'type' => 'dashicons',
                'value' => 'dashicons-info',
            ],
            'rename_capabilities' => false,
            'singular_capability_name' => 'post',
            'plural_capability_name' => 'posts',
            'supports' => [
                'title',
                'page-attributes',
                'custom-fields',
            ],
            'taxonomies' => '',
            'has_archive' => false,
            'has_archive_slug' => '',
            'rewrite' => [
                'permalink_rewrite' => 'no_permalink',
            ],
            'query_var' => 'post_type_key',
            'query_var_name' => '',
            'can_export' => true,
            'delete_with_user' => false,
            'register_meta_box_cb' => '',
            'enter_title_here' => '',
        ];
    }

    function meza_get_sponsor_type_taxonomy_definition(): array
    {
        return [
            'key' => 'taxonomy_688f86716fff9',
            'title' => 'Sponsor Types',
            'menu_order' => 0,
            'active' => true,
            'taxonomy' => 'sponsor-type',
            'object_type' => [
                'organization',
            ],
            'advanced_configuration' => 1,
            'import_source' => '',
            'import_date' => '',
            'labels' => [
                'name' => 'Sponsor Types',
                'singular_name' => 'Sponsor Type',
                'menu_name' => 'Sponsor Types',
                'all_items' => 'All Sponsor Types',
                'edit_item' => 'Edit Sponsor Type',
                'view_item' => 'View Sponsor Type',
                'update_item' => 'Update Sponsor Type',
                'add_new_item' => 'Add New Sponsor Type',
                'new_item_name' => 'New Sponsor Type Name',
                'search_items' => 'Search Sponsor Types',
                'popular_items' => 'Popular Sponsor Types',
                'separate_items_with_commas' => 'Separate sponsor types with commas',
                'add_or_remove_items' => 'Add or remove sponsor types',
                'choose_from_most_used' => 'Choose from the most used sponsor types',
                'most_used' => '',
                'not_found' => 'No sponsor types found',
                'no_terms' => 'No sponsor types',
                'name_field_description' => '',
                'slug_field_description' => '',
                'desc_field_description' => '',
                'items_list_navigation' => 'Sponsor Types list navigation',
                'items_list' => 'Sponsor Types list',
                'back_to_items' => '← Go to sponsor types',
                'item_link' => 'Sponsor Type Link',
                'item_link_description' => 'A link to a sponsor type',
            ],
            'description' => '',
            'capabilities' => [
                'manage_terms' => 'manage_categories',
                'edit_terms' => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts',
            ],
            'public' => 1,
            'publicly_queryable' => 0,
            'hierarchical' => 0,
            'show_ui' => 1,
            'show_in_menu' => 1,
            'show_in_nav_menus' => 0,
            'show_in_rest' => 1,
            'rest_base' => '',
            'rest_namespace' => 'wp/v2',
            'rest_controller_class' => 'WP_REST_Terms_Controller',
            'show_tagcloud' => 0,
            'show_in_quick_edit' => 1,
            'show_admin_column' => 1,
            'rewrite' => [
                'permalink_rewrite' => 'no_permalink',
            ],
            'query_var' => 'taxonomy_key',
            'query_var_name' => '',
            'default_term' => [
                'default_term_enabled' => '0',
            ],
            'sort' => 0,
            'meta_box' => 'default',
            'meta_box_cb' => '',
            'meta_box_sanitize_cb' => '',
            'allow_ai_access' => false,
            'ai_description' => '',
        ];
    }

    function meza_get_locality_taxonomy_definition(): array
    {
        return [
            'key' => 'taxonomy_66b426000102',
            'title' => 'Localities',
            'menu_order' => 0,
            'active' => true,
            'taxonomy' => 'locality',
            'object_type' => [
                'service',
                'review',
                'organization',
            ],
            'advanced_configuration' => 1,
            'import_source' => '',
            'import_date' => '',
            'labels' => [
                'name' => 'Localities',
                'singular_name' => 'Locality',
                'menu_name' => 'Localities',
                'add_new_item' => 'Add New Locality',
            ],
            'description' => '',
            'capabilities' => [
                'manage_terms' => 'manage_categories',
                'edit_terms' => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts',
            ],
            'public' => 1,
            'publicly_queryable' => 1,
            'hierarchical' => 1,
            'show_ui' => 1,
            'show_in_menu' => 1,
            'show_in_nav_menus' => 1,
            'show_in_rest' => 1,
            'rest_base' => '',
            'rest_namespace' => 'wp/v2',
            'rest_controller_class' => 'WP_REST_Terms_Controller',
            'show_tagcloud' => 1,
            'show_in_quick_edit' => 1,
            'show_admin_column' => 1,
            'rewrite' => [
                'permalink_rewrite' => 'taxonomy_key',
                'with_front' => '1',
                'rewrite_hierarchical' => '1',
            ],
            'query_var' => 'post_type_key',
            'query_var_name' => '',
            'default_term' => [
                'default_term_enabled' => '1',
                'name' => 'Fredericksburg, Virginia',
                'slug' => 'fredericksburg-va',
            ],
            'sort' => 0,
            'meta_box' => 'default',
            'meta_box_cb' => '',
            'meta_box_sanitize_cb' => '',
            'allow_ai_access' => false,
            'ai_description' => '',
        ];
    }

    function meza_get_service_category_taxonomy_definition(): array
    {
        return [
            'key' => 'taxonomy_69f3aa4870280',
            'title' => 'Service Categories',
            'menu_order' => 0,
            'active' => true,
            'taxonomy' => 'service-category',
            'object_type' => [
                'service',
            ],
            'advanced_configuration' => 1,
            'import_source' => '',
            'import_date' => '',
            'labels' => [
                'name' => 'Service Categories',
                'singular_name' => 'Service Category',
                'menu_name' => 'Service Categories',
                'all_items' => 'All Service Categories',
                'edit_item' => 'Edit Service Category',
                'view_item' => 'View Service Category',
                'update_item' => 'Update Service Category',
                'add_new_item' => 'Add New Service Category',
                'new_item_name' => 'New Service Category Name',
                'search_items' => 'Search Service Categories',
                'popular_items' => 'Popular Service Categories',
                'separate_items_with_commas' => 'Separate service categories with commas',
                'add_or_remove_items' => 'Add or remove service categories',
                'choose_from_most_used' => 'Choose from the most used service categories',
                'most_used' => '',
                'not_found' => 'No service categories found',
                'no_terms' => 'No service categories',
                'name_field_description' => '',
                'slug_field_description' => '',
                'desc_field_description' => '',
                'items_list_navigation' => 'Service Categories list navigation',
                'items_list' => 'Service Categories list',
                'back_to_items' => '← Go to service categories',
                'item_link' => 'Service Category Link',
                'item_link_description' => 'A link to a service category',
            ],
            'description' => '',
            'capabilities' => [
                'manage_terms' => 'manage_categories',
                'edit_terms' => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_posts',
            ],
            'public' => 1,
            'publicly_queryable' => 0,
            'hierarchical' => 0,
            'show_ui' => 1,
            'show_in_menu' => 1,
            'show_in_nav_menus' => 0,
            'show_in_rest' => 1,
            'rest_base' => '',
            'rest_namespace' => 'wp/v2',
            'rest_controller_class' => 'WP_REST_Terms_Controller',
            'show_tagcloud' => 1,
            'show_in_quick_edit' => 1,
            'show_admin_column' => 1,
            'rewrite' => [
                'permalink_rewrite' => 'no_permalink',
            ],
            'query_var' => 'taxonomy_key',
            'query_var_name' => '',
            'default_term' => [
                'default_term_enabled' => '0',
            ],
            'sort' => 0,
            'meta_box' => 'default',
            'meta_box_cb' => '',
            'meta_box_sanitize_cb' => '',
            'allow_ai_access' => false,
            'ai_description' => '',
        ];
    }

    function meza_get_service_post_type_definition(): array
    {
        return [
            'key' => 'post_type_66b426000001',
            'title' => 'Services',
            'menu_order' => 0,
            'active' => true,
            'post_type' => 'service',
            'advanced_configuration' => true,
            'import_source' => '',
            'import_date' => '',
            'allow_ai_access' => false,
            'ai_description' => '',
            'labels' => [
                'name' => 'Services',
                'singular_name' => 'Service',
                'menu_name' => 'Services',
                'all_items' => 'All Services',
                'edit_item' => 'Edit Service',
                'view_item' => 'View Service',
                'view_items' => 'View Services',
                'add_new_item' => 'Add New Service',
                'add_new' => 'Add New Service',
                'new_item' => 'New Service',
                'parent_item_colon' => '',
                'search_items' => 'Search Services',
                'not_found' => 'No services found',
                'not_found_in_trash' => 'No services found in Trash',
                'archives' => '',
                'attributes' => '',
                'featured_image' => '',
                'set_featured_image' => '',
                'remove_featured_image' => '',
                'use_featured_image' => '',
                'insert_into_item' => '',
                'uploaded_to_this_item' => '',
                'filter_items_list' => '',
                'filter_by_date' => '',
                'items_list_navigation' => '',
                'items_list' => '',
                'item_published' => '',
                'item_published_privately' => '',
                'item_reverted_to_draft' => '',
                'item_scheduled' => '',
                'item_updated' => '',
                'item_link' => '',
                'item_link_description' => '',
            ],
            'description' => '',
            'public' => true,
            'hierarchical' => true,
            'exclude_from_search' => false,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'show_in_rest' => true,
            'rest_base' => '',
            'rest_namespace' => 'wp/v2',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'menu_position' => '',
            'menu_icon' => [
                'type' => 'dashicons',
                'value' => 'dashicons-hammer',
            ],
            'rename_capabilities' => false,
            'singular_capability_name' => 'post',
            'plural_capability_name' => 'posts',
            'supports' => [
                'title',
                'thumbnail',
                'custom-fields',
                'excerpt',
                'page-attributes',
            ],
            'taxonomies' => [
                'locality',
                'service-category',
            ],
            'has_archive' => false,
            'has_archive_slug' => '',
            'rewrite' => [
                'permalink_rewrite' => 'post_type_key',
                'with_front' => '1',
                'feeds' => '0',
                'pages' => '1',
            ],
            'query_var' => 'post_type_key',
            'query_var_name' => '',
            'can_export' => true,
            'delete_with_user' => false,
            'register_meta_box_cb' => '',
            'enter_title_here' => '',
        ];
    }

    function meza_get_hero_section_field_group_definition(): array
    {
        return [
            'key' => 'group_692cdbb4a0ff0',
            'title' => 'Hero Section',
            'fields' => [
                [
                    'key' => 'field_692cdbb570d1d',
                    'label' => 'Section',
                    'name' => 'section_hero',
                    'aria-label' => '',
                    'type' => 'group',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'layout' => 'block',
                    'sub_fields' => [
                        [
                            'key' => 'field_692cdbd770d1e',
                            'label' => 'Headline (H1)',
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
                            'default_value' => 'services',
                            'maxlength' => '',
                            'allow_in_bindings' => 0,
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                        ],
                        [
                            'key' => 'field_69b0793b330e0',
                            'label' => 'Display',
                            'name' => 'display',
                            'aria-label' => '',
                            'type' => 'text',
                            'instructions' => '',
                            'required' => 0,
                            'conditional_logic' => [
                                [
                                    [
                                        'field' => 'field_692cdbd770d1e',
                                        'operator' => '!=empty',
                                    ],
                                ],
                            ],
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
                            'key' => 'field_692cdbdf70d1f',
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
                            'key' => 'field_692cdbeb70d20',
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
                            'key' => 'field_692cdc0270d21',
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
                            'key' => 'field_697fe81bb6b38',
                            'label' => 'Link (Secondary)',
                            'name' => 'link_secondary',
                            'aria-label' => '',
                            'type' => 'link',
                            'instructions' => '',
                            'required' => 0,
                            'conditional_logic' => [
                                [
                                    [
                                        'field' => 'field_692cdc0270d21',
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
                            'key' => 'field_69cfe961a379e',
                            'label' => 'Icon',
                            'name' => 'icon',
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
                            'preview_size' => 'thumbnail',
                        ],
                        [
                            'key' => 'field_692cdc5a70d23',
                            'label' => 'Image',
                            'name' => 'image',
                            'aria-label' => '',
                            'type' => 'image',
                            'instructions' => '',
                            'required' => false,
                            'conditional_logic' => 0,
                            'wrapper' => [
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ],
                            'return_format' => 'id',
                            'library' => 'all',
                            'preview_size' => 'medium',
                            'min_width' => 0,
                            'min_height' => 0,
                            'min_size' => 0,
                            'max_width' => 0,
                            'max_height' => 0,
                            'max_size' => 0,
                            'mime_types' => '',
                        ],
                        [
                            'key' => 'field_692cdc8070d24',
                            'label' => 'Video File',
                            'name' => 'video',
                            'aria-label' => '',
                            'type' => 'file',
                            'instructions' => '',
                            'required' => 0,
                            'conditional_logic' => [
                                [
                                    [
                                        'field' => 'field_692cdc5a70d23',
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
                            'library' => 'all',
                            'min_size' => '',
                            'max_size' => '',
                            'mime_types' => '',
                            'allow_in_bindings' => 0,
                        ],
                        [
                            'key' => 'field_692cdcb870d25',
                            'label' => 'Video Embed',
                            'name' => 'video_embed',
                            'aria-label' => '',
                            'type' => 'oembed',
                            'instructions' => '',
                            'required' => 0,
                            'conditional_logic' => [
                                [
                                    [
                                        'field' => 'field_692cdc5a70d23',
                                        'operator' => '!=empty',
                                    ],
                                ],
                            ],
                            'wrapper' => [
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ],
                            'width' => '',
                            'height' => '',
                            'allow_in_bindings' => 0,
                        ],
                        [
                            'key' => 'field_692cdc4870d22',
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
                            'default_value' => 'top',
                            'maxlength' => '',
                            'allow_in_bindings' => 0,
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                        ],
                    ],
                ],
            ],
            'location' => meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies(),
            'menu_order' => 1,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => [
                'discussion',
                'comments',
                'revisions',
                'author',
                'format',
                'tags',
                'send-trackbacks',
            ],
            'active' => true,
            'description' => '',
            'show_in_rest' => 0,
            'display_title' => '',
            'allow_ai_access' => false,
            'ai_description' => '',
        ];
    }

    function meza_get_form_section_field_group_definition(): array
    {
        return [
            'key' => 'group_697ffe3780c87',
            'title' => 'Form Section',
            'fields' => [
                [
                    'key' => 'field_69b03fca31afa',
                    'label' => 'Visibility',
                    'name' => 'show_form',
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
                    'key' => 'field_69b03fdc31afb',
                    'label' => 'Section',
                    'name' => 'section_form',
                    'aria-label' => '',
                    'type' => 'group',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_69b03fca31afa',
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
                            'key' => 'field_69b03fe631afc',
                            'label' => 'Headline',
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
                            'key' => 'field_69b03ff131afd',
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
                            'key' => 'field_69b1272a3d536',
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
                            'new_lines' => '',
                        ],
                        [
                            'key' => 'field_697ffe37abb58',
                            'label' => 'Form',
                            'name' => 'form',
                            'aria-label' => '',
                            'type' => 'relationship',
                            'instructions' => '',
                            'required' => 1,
                            'conditional_logic' => 0,
                            'wrapper' => [
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ],
                            'post_type' => [
                                'form',
                            ],
                            'post_status' => [
                                'publish',
                            ],
                            'taxonomy' => '',
                            'filters' => [
                                'search',
                            ],
                            'return_format' => 'id',
                            'min' => 1,
                            'max' => 1,
                            'allow_in_bindings' => 0,
                            'elements' => '',
                            'bidirectional' => 0,
                            'bidirectional_target' => [],
                        ],
                        [
                            'key' => 'field_69b03ff731afe',
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
            'location' => meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies(),
            'menu_order' => 16,
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
    }

    function meza_get_cta_section_field_group_definition(): array
    {
        return [
            'key' => 'group_f7b16b48',
            'title' => 'CTA Section',
            'fields' => [
                [
                    'key' => 'field_232e234f',
                    'label' => 'CTA',
                    'name' => 'cta',
                    'aria-label' => '',
                    'type' => 'relationship',
                    'instructions' => '',
                    'required' => false,
                    'conditional_logic' => false,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'post_type' => [
                        'cta',
                    ],
                    'filters' => [
                        'search',
                    ],
                    'return_format' => 'id',
                    'taxonomy' => [],
                    'min' => 0,
                    'max' => 0,
                    'elements' => [],
                    'bidirectional_target' => [],
                ],
                [
                    'key' => 'field_meza_cta_section_id',
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
            'location' => meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies(),
            'menu_order' => 20,
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
    }

    function meza_get_list_faqs_section_field_group_definition(): array
    {
        return [
            'key' => 'group_697feb01ec35f',
            'title' => 'List FAQs Section',
            'fields' => [
                [
                    'key' => 'field_69b03ef5673a1',
                    'label' => 'Visibility',
                    'name' => 'show_list-faqs',
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
                    'key' => 'field_69b03f0e673a2',
                    'label' => 'Section',
                    'name' => 'section_faqs',
                    'aria-label' => '',
                    'type' => 'group',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_69b03ef5673a1',
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
                            'key' => 'field_69b03f37673a3',
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
                            'default_value' => 'faqs',
                            'maxlength' => '',
                            'allow_in_bindings' => 0,
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                        ],
                        [
                            'key' => 'field_69b03f46673a4',
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
                            'default_value' => 'reviews',
                            'maxlength' => '',
                            'allow_in_bindings' => 0,
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                        ],
                        [
                            'key' => 'field_69ce52d4d29eb',
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
                            'key' => 'field_69b11ab5d9e51',
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
                            'default_value' => 'posts',
                            'maxlength' => '',
                            'allow_in_bindings' => 0,
                            'rows' => 2,
                            'placeholder' => '',
                            'new_lines' => '',
                        ],
                        [
                            'key' => 'field_69b11ad6d9e52',
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
                            'key' => 'field_697ffc2b176c2',
                            'label' => 'FAQs',
                            'name' => 'faqs',
                            'aria-label' => '',
                            'type' => 'relationship',
                            'instructions' => '',
                            'required' => 1,
                            'conditional_logic' => 0,
                            'wrapper' => [
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ],
                            'post_type' => [
                                'faq',
                            ],
                            'post_status' => [
                                'publish',
                            ],
                            'taxonomy' => '',
                            'filters' => [
                                'search',
                            ],
                            'return_format' => 'id',
                            'min' => 1,
                            'max' => '',
                            'allow_in_bindings' => 0,
                            'elements' => '',
                            'bidirectional' => 0,
                            'bidirectional_target' => [],
                        ],
                        [
                            'key' => 'field_69b03f4e673a5',
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
                            'default_value' => 'faqs',
                            'maxlength' => '',
                            'allow_in_bindings' => 0,
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                        ],
                    ],
                ],
            ],
            'location' => meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies(),
            'menu_order' => 25,
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
    }

    function meza_get_list_reviews_section_field_group_definition(): array
    {
        return [
            'key' => 'group_meza_list_reviews_section',
            'title' => 'List Reviews Section',
            'fields' => [
                [
                    'key' => 'field_meza_show_list_reviews',
                    'label' => 'Visibility',
                    'name' => 'show_list-reviews',
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
                    'key' => 'field_meza_section_list_reviews',
                    'label' => 'Section',
                    'name' => 'section_list-reviews',
                    'aria-label' => '',
                    'type' => 'group',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_meza_show_list_reviews',
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
                            'key' => 'field_meza_list_reviews_headline',
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
                            'key' => 'field_meza_list_reviews_subhead',
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
                            'default_value' => 'reviews',
                            'maxlength' => '',
                            'allow_in_bindings' => 0,
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                        ],
                        [
                            'key' => 'field_meza_list_reviews_link',
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
                        'key' => 'field_meza_list_reviews_id',
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
                            'default_value' => 'reviews',
                            'maxlength' => '',
                            'allow_in_bindings' => 0,
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                        ],
                    ],
                ],
            ],
            'location' => meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies(),
            'menu_order' => 3,
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
    }

    function meza_get_list_posts_section_field_group_definition(): array
    {
        return [
            'key' => 'group_meza_list_posts_section',
            'title' => 'List Posts Section',
            'fields' => [
                [
                    'key' => 'field_meza_show_list_posts',
                    'label' => 'Visibility',
                    'name' => 'show_list-posts',
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
                    'key' => 'field_meza_section_list_posts',
                    'label' => 'Section',
                    'name' => 'section_list-posts',
                    'aria-label' => '',
                    'type' => 'group',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_meza_show_list_posts',
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
                            'key' => 'field_meza_list_posts_headline',
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
                            'default_value' => 'resources',
                            'maxlength' => '',
                            'allow_in_bindings' => 0,
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                        ],
                        [
                            'key' => 'field_meza_list_posts_subhead',
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
                            'key' => 'field_meza_list_posts_link',
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
                        'key' => 'field_meza_list_posts_id',
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
                            'default_value' => 'posts',
                            'maxlength' => '',
                            'allow_in_bindings' => 0,
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                        ],
                    ],
                ],
            ],
            'location' => meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies(),
            'menu_order' => 23,
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
    }

    function meza_get_list_resources_section_field_group_definition(): array
    {
        return [
            'key' => 'group_meza_list_resources_section',
            'title' => 'List Resources Section',
            'fields' => [
                [
                    'key' => 'field_meza_show_list_resources',
                    'label' => 'Visibility',
                    'name' => 'show_list-resources',
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
                    'key' => 'field_meza_section_list_resources',
                    'label' => 'Section',
                    'name' => 'section_list-resources',
                    'aria-label' => '',
                    'type' => 'group',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_meza_show_list_resources',
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
                            'key' => 'field_meza_list_resources_headline',
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
                            'key' => 'field_meza_list_resources_subhead',
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
                            'key' => 'field_meza_list_resources_link',
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
                        'key' => 'field_meza_list_resources_id',
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
                            'default_value' => 'resources',
                            'maxlength' => '',
                            'allow_in_bindings' => 0,
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                        ],
                    ],
                ],
            ],
            'location' => meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies(),
            'menu_order' => 22,
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
    }

    function meza_get_list_services_section_field_group_definition(): array
    {
        return [
            'key' => 'group_b2f9d7e8',
            'title' => 'List Services Section',
            'fields' => [
                [
                    'key' => 'field_3ce1e3d6',
                    'label' => 'Visibility',
                    'name' => 'show_list-services',
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
                    'allow_in_bindings' => 1,
                    'ui' => 0,
                    'ui_on_text' => '',
                    'ui_off_text' => '',
                ],
                [
                    'key' => 'field_e278a597',
                    'label' => 'Section',
                    'name' => 'section_list-services',
                    'aria-label' => '',
                    'type' => 'group',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_3ce1e3d6',
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
                            'key' => 'field_643fcf0d',
                            'label' => 'Headline (H2)',
                            'name' => 'headline',
                            'aria-label' => '',
                            'type' => 'text',
                            'instructions' => '',
                            'required' => 1,
                            'conditional_logic' => false,
                            'wrapper' => [
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ],
                            'default_value' => 'services',
                            'maxlength' => '',
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                        ],
                        [
                            'key' => 'field_9fe55eb5',
                            'label' => 'Subhead',
                            'name' => 'subhead',
                            'aria-label' => '',
                            'type' => 'text',
                            'instructions' => '',
                            'required' => 0,
                            'conditional_logic' => false,
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
                        'key' => 'field_1086a562',
                            'label' => 'ID',
                            'name' => 'id',
                            'aria-label' => '',
                            'type' => 'text',
                            'instructions' => '',
                            'required' => 0,
                            'conditional_logic' => false,
                            'wrapper' => [
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ],
                            'default_value' => 'services',
                            'maxlength' => '',
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                        ],
                    ],
                ],
            ],
            'location' => meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies(),
            'menu_order' => 7,
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
    }

    function meza_get_list_partners_section_field_group_definition(): array
    {
        return [
            'key' => 'group_688f8c2740e76',
            'title' => 'List Partners Section',
            'fields' => [
                [
                    'key' => 'field_688f8c2757d70',
                    'label' => 'Visibility',
                    'name' => 'show_list-partners',
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
                    'message' => 'Show the List Partners section on this page?',
                    'default_value' => 0,
                    'allow_in_bindings' => 0,
                    'ui' => 0,
                    'ui_on_text' => '',
                    'ui_off_text' => '',
                ],
                [
                    'key' => 'field_688f8c2757dd7',
                    'label' => 'Section',
                    'name' => 'section_list-partners',
                    'aria-label' => '',
                    'type' => 'group',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_688f8c2757d70',
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
                            'key' => 'field_688f8c275f9ac',
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
                            'key' => 'field_6897509e5631e',
                            'label' => 'Summary',
                            'name' => 'summary',
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
                            'key' => 'field_688f8c275fa67',
                            'label' => 'Disclaimer',
                            'name' => 'disclaimer',
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
                            'key' => 'field_689750c55631f',
                            'label' => 'Button Text',
                            'name' => 'button_text',
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
                            'key' => 'field_688f8c275faa1',
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
            'menu_order' => 11,
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
    }

    function meza_get_list_sponsors_section_field_group_definition(): array
    {
        return [
            'key' => 'group_688f8acec1ae2',
            'title' => 'List Sponsors Section',
            'fields' => [
                [
                    'key' => 'field_688f8aced885c',
                    'label' => 'Visibility',
                    'name' => 'show_list-sponsors',
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
                    'message' => 'Show the List Sponsors section on this page?',
                    'default_value' => 0,
                    'allow_in_bindings' => 0,
                    'ui' => 0,
                    'ui_on_text' => '',
                    'ui_off_text' => '',
                ],
                [
                    'key' => 'field_688f8aced88a6',
                    'label' => 'Section',
                    'name' => 'section_list-sponsors',
                    'aria-label' => '',
                    'type' => 'group',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_688f8aced885c',
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
                            'key' => 'field_688f8acee3d3c',
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
                            'key' => 'field_688f8acee3d82',
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
                            'key' => 'field_688f8acee3dc0',
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
                            'key' => 'field_688f8acee3e3c',
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
    }

    function meza_get_cta_field_group_definition(): array
    {
        return [
            'key' => 'group_697ff4f7482e6',
            'title' => 'CTA',
            'fields' => [
                [
                    'key' => 'field_69b07b3a68600',
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
                    'new_lines' => '',
                ],
                [
                    'key' => 'field_697ff4f7c4120',
                    'label' => 'Link',
                    'name' => 'link',
                    'aria-label' => '',
                    'type' => 'link',
                    'instructions' => '',
                    'required' => 1,
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
                    'key' => 'field_697ff50dc4122',
                    'label' => 'Link (Secondary)',
                    'name' => 'link_secondary',
                    'aria-label' => '',
                    'type' => 'link',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_697ff4f7c4120',
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
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'cta',
                    ],
                ],
            ],
            'menu_order' => 0,
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
    }

    function meza_profile_uses_organization_field(): bool
    {
        return meza_is_conference_business_type();
    }

    function meza_review_uses_profile_citer(): bool
    {
        return meza_is_conference_business_type();
    }

    function meza_get_profile_field_group_definition(): array
    {
        $fields = [
            [
                'key' => 'field_6902a3bf958b8',
                'label' => 'Title',
                'name' => 'title',
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
        ];

        if (meza_profile_uses_organization_field()) {
            $fields[] = [
                'key' => 'field_6902c3cbe169b',
                'label' => 'Organization',
                'name' => 'organization',
                'aria-label' => '',
                'type' => 'relationship',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'post_type' => [
                    'organization',
                ],
                'post_status' => [
                    'publish',
                ],
                'taxonomy' => '',
                'filters' => [
                    'search',
                ],
                'return_format' => 'id',
                'min' => '',
                'max' => 1,
                'allow_in_bindings' => 0,
                'elements' => [
                    'featured_image',
                ],
                'bidirectional' => 0,
                'bidirectional_target' => [],
            ];
        }

        $fields[] = [
            'key' => 'field_68bb85c35a8e2',
            'label' => 'URL',
            'name' => 'url',
            'aria-label' => '',
            'type' => 'link',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => meza_profile_uses_organization_field()
                ? [
                    [
                        [
                            'field' => 'field_6902c3cbe169b',
                            'operator' => '==empty',
                        ],
                    ],
                ]
                : 0,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => '',
            ],
            'return_format' => 'url',
            'allow_in_bindings' => 0,
        ];

        return [
            'key' => 'group_68bb85c355957',
            'title' => 'Profile',
            'fields' => $fields,
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'profile',
                    ],
                ],
            ],
            'menu_order' => 0,
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
    }

    function meza_get_organization_field_group_definition(): array
    {
        return [
            'key' => 'group_69b07c3129ab0',
            'title' => 'Organization',
            'fields' => [
                [
                    'key' => 'field_69b07c31cf5f1',
                    'label' => 'URL',
                    'name' => 'url',
                    'aria-label' => '',
                    'type' => 'url',
                    'instructions' => '',
                    'required' => 1,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'default_value' => '',
                    'allow_in_bindings' => 1,
                    'placeholder' => '',
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'organization',
                    ],
                ],
            ],
            'menu_order' => 0,
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
    }

    function meza_get_faq_field_group_definition(): array
    {
        return [
            'key' => 'group_681255c43a8a1',
            'title' => 'FAQ',
            'fields' => [
                [
                    'key' => 'field_681255cc3a8a2',
                    'label' => 'FAQs',
                    'name' => 'faqs',
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
                    'layout' => 'block',
                    'pagination' => 0,
                    'min' => 1,
                    'max' => 0,
                    'collapsed' => 'field_681255d93a8a3',
                    'button_label' => 'Add FAQ',
                    'rows_per_page' => 20,
                    'sub_fields' => [
                        [
                            'key' => 'field_681255d93a8a3',
                            'label' => 'Question',
                            'name' => 'question',
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
                            'parent_repeater' => 'field_681255cc3a8a2',
                        ],
                        [
                            'key' => 'field_681255e53a8a4',
                            'label' => 'Answer',
                            'name' => 'answer',
                            'aria-label' => '',
                            'type' => 'wysiwyg',
                            'instructions' => '',
                            'required' => 1,
                            'conditional_logic' => 0,
                            'wrapper' => [
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ],
                            'default_value' => '',
                            'allow_in_bindings' => 0,
                            'tabs' => 'all',
                            'toolbar' => 'basic',
                            'media_upload' => 0,
                            'delay' => 0,
                            'parent_repeater' => 'field_681255cc3a8a2',
                        ],
                    ],
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'faq',
                    ],
                ],
            ],
            'menu_order' => 0,
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
    }

    function meza_get_review_field_group_definition(): array
    {
        $citer_field = meza_review_uses_profile_citer()
            ? [
                'key' => 'field_69f05fb0b9f36',
                'label' => 'Citer',
                'name' => 'citer',
                'aria-label' => '',
                'type' => 'relationship',
                'instructions' => '',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'post_type' => [
                    'profile',
                ],
                'post_status' => [
                    'publish',
                ],
                'taxonomy' => '',
                'filters' => [
                    'search',
                ],
                'return_format' => 'object',
                'min' => 1,
                'max' => 1,
                'allow_in_bindings' => 0,
                'elements' => [
                    'featured_image',
                ],
                'bidirectional' => 0,
                'bidirectional_target' => [],
            ]
            : [
                'key' => 'field_68f28d2cd3c79',
                'label' => 'Citer',
                'name' => 'citer',
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
            ];

        return [
            'key' => 'group_68f28d05a8e4b',
            'title' => 'Review',
            'fields' => [
                [
                    'key' => 'field_68f28d05d3c78',
                    'label' => 'Quote',
                    'name' => 'quote',
                    'aria-label' => '',
                    'type' => 'textarea',
                    'instructions' => 'Limited to 150 characters.',
                    'required' => 1,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'default_value' => '',
                    'maxlength' => 200,
                    'allow_in_bindings' => 0,
                    'rows' => 2,
                    'placeholder' => '',
                    'new_lines' => '',
                ],
                $citer_field,
                [
                    'key' => 'field_697ff1f193b2a',
                    'label' => 'URL',
                    'name' => 'url',
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
                    'return_format' => 'url',
                    'allow_in_bindings' => 0,
                ],
                [
                    'key' => 'field_697ff1b893b29',
                    'label' => 'Date',
                    'name' => 'date',
                    'aria-label' => '',
                    'type' => 'date_picker',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'display_format' => 'F j, Y',
                    'return_format' => 'Ymd',
                    'first_day' => 1,
                    'default_to_current_date' => 1,
                    'allow_in_bindings' => 0,
                ],
                [
                    'key' => 'field_6811ca58eb4cb',
                    'label' => 'Featured',
                    'name' => 'featured',
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
                    'ui_on_text' => '',
                    'ui_off_text' => '',
                    'ui' => 1,
                    'allow_in_bindings' => 0,
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'review',
                    ],
                ],
            ],
            'menu_order' => 0,
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
    }

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
            'rewrite' => false,
            'query_var' => false,
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
        $field = [
            'key' => meza_get_section_sub_field_stable_key($section_field_name, $sub_field_name),
            'label' => ucfirst($sub_field_name),
            'name' => $sub_field_name,
            'aria-label' => '',
            'type' => $is_textarea ? 'textarea' : 'text',
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
        } else {
            $field['placeholder'] = '';
            $field['prepend'] = '';
            $field['append'] = '';
        }

        return $field;
    }

    function meza_normalize_section_sub_fields(array $sub_fields, string $section_field_name, string $group_key = ''): array
    {
        $target_names = ['headline', 'display', 'subhead', 'description'];
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
        if ($section_field_name === '' || empty($group['fields']) || !is_array($group['fields'])) {
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

    function meza_get_shared_project_acf_field_groups(): array
    {
        $groups = [
            [
                'key' => 'group_meza_business_information_general',
                'title' => 'General',
                'fields' => meza_get_business_information_general_fields(),
                'location' => [
                    [
                        [
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => 'business-information',
                        ],
                    ],
                ],
                'menu_order' => 1,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'left',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
            ],
            [
                'key' => 'group_meza_business_information_locations',
                'title' => 'Locations',
                'fields' => meza_get_business_information_locations_fields(),
                'location' => [
                    [
                        [
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => 'business-information',
                        ],
                    ],
                ],
                'menu_order' => 2,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'left',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
            ],
            [
                'key' => 'group_meza_business_branding',
                'title' => 'Visuals and Identity',
                'fields' => meza_get_business_information_branding_fields(),
                'location' => [
                    [
                        [
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => 'branding',
                        ],
                    ],
                ],
                'menu_order' => 1,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'left',
                'instruction_placement' => 'field',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
            ],
            [
                'key' => 'group_69b5b29b9d099',
                'title' => 'CRM Integration',
                'fields' => meza_get_crm_integration_fields(),
                'location' => [
                    [
                        [
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => 'crm',
                        ],
                    ],
                ],
                'menu_order' => 3,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'top',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
            ],
            [
                'key' => 'group_688f8020b16f8',
                'title' => 'Information',
                'fields' => meza_get_conference_information_fields(),
                'location' => [
                    [
                        [
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => 'conference',
                        ],
                    ],
                ],
                'menu_order' => 4,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'top',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
            ],
            [
                'key' => 'group_69f04f931a834',
                'title' => 'Schedule',
                'fields' => meza_get_conference_schedule_fields(),
                'location' => [
                    [
                        [
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => 'conference-schedule',
                        ],
                    ],
                ],
                'menu_order' => 5,
                'position' => 'normal',
                'style' => 'seamless',
                'label_placement' => 'top',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
            ],
            [
                'key' => 'group_69f04ade6c91c',
                'title' => 'Segment',
                'fields' => meza_get_segment_fields(),
                'location' => [
                    [
                        [
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'segment',
                        ],
                    ],
                ],
                'menu_order' => 0,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'top',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
            ],
            [
                'key' => 'group_68b3145739719',
                'title' => 'Mission, Vision, and Values',
                'fields' => [
                    [
                        'key' => 'field_69c172d3bb939',
                        'label' => 'Mission',
                        'name' => 'mission',
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
                        'rows' => 3,
                        'placeholder' => '',
                        'new_lines' => '',
                    ],
                    [
                        'key' => 'field_69c172ebbb93a',
                        'label' => 'Vision',
                        'name' => 'vision',
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
                        'rows' => 3,
                        'placeholder' => '',
                        'new_lines' => '',
                    ],
                    [
                        'key' => 'field_69c172fabb93b',
                        'label' => 'Values',
                        'name' => 'values',
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
                        'collapsed' => 'field_69c17303bb93c',
                        'button_label' => 'Add Value',
                        'rows_per_page' => 20,
                        'sub_fields' => [
                            [
                                'key' => 'field_69c17303bb93c',
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
                                'parent_repeater' => 'field_69c172fabb93b',
                            ],
                            [
                                'key' => 'field_69c1731ebb93d',
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
                                'new_lines' => '',
                                'parent_repeater' => 'field_69c172fabb93b',
                            ],
                            [
                                'key' => 'field_69c1732cbb93e',
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
                                'parent_repeater' => 'field_69c172fabb93b',
                            ],
                        ],
                    ],
                ],
                'location' => [
                    [
                        [
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => 'branding',
                        ],
                    ],
                ],
                'menu_order' => 2,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'left',
                'instruction_placement' => 'field',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
            ],
            [
                'key' => 'group_68c1337b43d46',
                'title' => 'Contact Information',
                'fields' => meza_get_business_information_contact_fields(),
                'location' => [
                    [
                        [
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => 'business-information',
                        ],
                    ],
                ],
                'menu_order' => 3,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'left',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
            ],
            meza_get_hero_section_field_group_definition(),
            meza_get_form_section_field_group_definition(),
            meza_get_cta_section_field_group_definition(),
            meza_get_list_services_section_field_group_definition(),
            meza_get_list_posts_section_field_group_definition(),
            meza_get_list_resources_section_field_group_definition(),
            meza_get_list_faqs_section_field_group_definition(),
            meza_get_list_reviews_section_field_group_definition(),
            meza_get_cta_field_group_definition(),
            meza_get_profile_field_group_definition(),
            meza_get_organization_field_group_definition(),
            meza_get_faq_field_group_definition(),
            meza_get_review_field_group_definition(),
        ];

        if (meza_should_register_contact_locations_section_group()) {
            $groups[] = meza_get_contact_locations_section_field_group_definition();
        }

        foreach ($groups as $index => $group) {
            if (!is_array($group)) {
                continue;
            }

            $groups[$index] = meza_normalize_header_section_group($group);
        }

        $groups = apply_filters('meza_shared_project_acf_field_groups', $groups);

        return is_array($groups) ? array_values($groups) : [];
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

if (!function_exists('meza_seed_default_locality_terms')) {
    function meza_seed_default_locality_terms(): void
    {
        $taxonomy = 'locality';
        if (!taxonomy_exists($taxonomy)) {
            return;
        }

        if (term_exists('fredericksburg-va', $taxonomy)) {
            return;
        }

        wp_insert_term('Fredericksburg, Virginia', $taxonomy, [
            'slug' => 'fredericksburg-va',
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
                return sanitize_key((string) ($definition['menu_slug'] ?? ''));
        }

        return '';
    }
}

if (!function_exists('meza_get_runtime_local_managed_acf_definitions_by_kind')) {
    function meza_get_runtime_local_managed_acf_definitions_by_kind(string $kind): array
    {
        switch ($kind) {
            case 'field_groups':
                return meza_get_shared_project_acf_field_groups();

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
                    meza_get_shared_project_acf_field_groups(),
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
        $candidate_identifier = meza_get_managed_acf_definition_identifier($kind, $candidate);
        if ($candidate_identifier === '') {
            return null;
        }

        foreach (meza_get_managed_acf_definitions_by_kind($kind) as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            if (meza_get_managed_acf_definition_identifier($kind, $definition) === $candidate_identifier) {
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
                'menu_order' => 5,
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
                        'message' => 'Show the List Localities section on this page?',
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
                        'message' => 'Show the Benefits section on this page?',
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
                        'message' => 'Show the Gallery section on this page?',
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
        return '2026-05-01-orders-and-show-prefix-v1';
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
    function meza_seed_default_editable_acf_field_groups(): void
    {
        if (!function_exists('acf_import_field_group')) {
            return;
        }

        foreach (meza_get_default_editable_acf_field_group_definitions() as $definition) {
            if (!is_array($definition) || empty($definition['key']) || empty($definition['title'])) {
                continue;
            }

            if (meza_is_managed_acf_definition_manually_deleted('field_groups', $definition)) {
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

if (!function_exists('meza_get_business_information_branding_field_map')) {
    function meza_get_business_information_branding_field_map(): array
    {
        return [
            'wp_site_title' => [
                'load' => static function (): string {
                    return (string) get_option('blogname', '');
                },
                'update' => static function ($value): string {
                    $sanitized_value = sanitize_text_field((string) $value);
                    update_option('blogname', $sanitized_value);
                    return $sanitized_value;
                },
            ],
            'wp_tagline' => [
                'load' => static function (): string {
                    return (string) get_option('blogdescription', '');
                },
                'update' => static function ($value): string {
                    $sanitized_value = sanitize_text_field((string) $value);
                    update_option('blogdescription', $sanitized_value);
                    return $sanitized_value;
                },
            ],
            'wp_site_logo' => [
                'load' => static function (): int {
                    if (function_exists('meza_get_custom_logo_id')) {
                        return (int) meza_get_custom_logo_id();
                    }

                    return (int) get_theme_mod('custom_logo');
                },
                'update' => static function ($value): int {
                    $logo_id = function_exists('meza_sanitize_custom_logo_id')
                        ? (int) meza_sanitize_custom_logo_id($value)
                        : absint($value);

                    update_option('meza_custom_logo_id', $logo_id);
                    return $logo_id;
                },
            ],
            'wp_site_logo_alternative' => [
                'load' => static function (): int {
                    if (function_exists('meza_get_alternative_logo_id')) {
                        return (int) meza_get_alternative_logo_id();
                    }

                    return (int) get_option('meza_alternative_logo_id', 0);
                },
                'update' => static function ($value): int {
                    $logo_id = function_exists('meza_sanitize_alternative_logo_id')
                        ? (int) meza_sanitize_alternative_logo_id($value)
                        : absint($value);

                    update_option('meza_alternative_logo_id', $logo_id);
                    return $logo_id;
                },
            ],
            'wp_site_icon' => [
                'load' => static function (): int {
                    return (int) get_option('site_icon', 0);
                },
                'validate' => static function ($valid, $value) {
                    if ($valid !== true) {
                        return $valid;
                    }

                    $attachment_id = absint($value);
                    if ($attachment_id <= 0) {
                        return $valid;
                    }

                    $attachment = get_post($attachment_id);
                    if (!($attachment instanceof WP_Post) || $attachment->post_type !== 'attachment') {
                        return __('Select a valid media library image for the Site Icon.', 'mz-mu-plugins');
                    }

                    $metadata = wp_get_attachment_metadata($attachment_id);
                    $width = (int) ($metadata['width'] ?? 0);
                    $height = (int) ($metadata['height'] ?? 0);

                    if ($width < 512 || $height < 512) {
                        return __('Select a Site Icon image that is at least 512 by 512 pixels.', 'mz-mu-plugins');
                    }

                    return $valid;
                },
                'update' => static function ($value): int {
                    $site_icon_id = absint($value);
                    update_option('site_icon', $site_icon_id);
                    return $site_icon_id;
                },
            ],
            'wp_theme_thumbnail' => [
                'load' => static function (): int {
                    return meza_get_theme_thumbnail_id();
                },
                'validate' => static function ($valid, $value) {
                    if ($valid !== true) {
                        return $valid;
                    }

                    $attachment_id = absint($value);
                    if ($attachment_id <= 0) {
                        return $valid;
                    }

                    $attachment = get_post($attachment_id);
                    if (!($attachment instanceof WP_Post) || $attachment->post_type !== 'attachment') {
                        return __('Select a valid media library image for the Theme Thumbnail.', 'mz-mu-plugins');
                    }

                    if (!meza_theme_thumbnail_attachment_is_supported($attachment_id)) {
                        return __('Select a PNG, JPG, GIF, WebP, or AVIF image for the Theme Thumbnail.', 'mz-mu-plugins');
                    }

                    $thumbnail_url = wp_get_attachment_image_url($attachment_id, 'full');
                    if (!is_string($thumbnail_url) || $thumbnail_url === '') {
                        return __('The selected Theme Thumbnail image URL could not be found.', 'mz-mu-plugins');
                    }

                    return $valid;
                },
                'update' => static function ($value): int {
                    $thumbnail_id = absint($value);
                    $GLOBALS['meza_previous_theme_thumbnail_id'] = meza_get_theme_thumbnail_id();

                    if ($thumbnail_id <= 0) {
                        delete_option('meza_theme_thumbnail_id');
                        return 0;
                    }

                    update_option('meza_theme_thumbnail_id', $thumbnail_id, false);
                    return $thumbnail_id;
                },
            ],
            'wp_social_share_default' => [
                'load' => static function (): int {
                    return meza_get_social_share_default_id();
                },
                'validate' => static function ($valid, $value) {
                    if ($valid !== true) {
                        return $valid;
                    }

                    $attachment_id = absint($value);
                    if ($attachment_id <= 0) {
                        return $valid;
                    }

                    $attachment = get_post($attachment_id);
                    if (!($attachment instanceof WP_Post) || $attachment->post_type !== 'attachment') {
                        return __('Select a valid media library image for the Social Share Default.', 'mz-mu-plugins');
                    }

                    if (!meza_theme_thumbnail_attachment_is_supported($attachment_id)) {
                        return __('Select a PNG, JPG, GIF, WebP, or AVIF image for the Social Share Default.', 'mz-mu-plugins');
                    }

                    $thumbnail_url = wp_get_attachment_image_url($attachment_id, 'full');
                    if (!is_string($thumbnail_url) || $thumbnail_url === '') {
                        return __('The selected Social Share Default image URL could not be found.', 'mz-mu-plugins');
                    }

                    return $valid;
                },
                'update' => static function ($value): int {
                    $default_id = absint($value);
                    $theme_thumbnail_id = meza_get_theme_thumbnail_id();
                    $stored_default_id = (int) get_option('meza_social_share_default_id', 0);
                    $previous_theme_thumbnail_id = isset($GLOBALS['meza_previous_theme_thumbnail_id'])
                        ? absint($GLOBALS['meza_previous_theme_thumbnail_id'])
                        : $theme_thumbnail_id;

                    if ($default_id <= 0) {
                        delete_option('meza_social_share_default_id');

                        return $theme_thumbnail_id;
                    }

                    if ($stored_default_id <= 0 && $previous_theme_thumbnail_id > 0 && $default_id === $previous_theme_thumbnail_id) {
                        delete_option('meza_social_share_default_id');
                        return $theme_thumbnail_id > 0 ? $theme_thumbnail_id : $default_id;
                    }

                    if ($default_id > 0 && $default_id === $theme_thumbnail_id) {
                        delete_option('meza_social_share_default_id');
                        return $default_id;
                    }

                    update_option('meza_social_share_default_id', $default_id, false);
                    return $default_id;
                },
            ],
        ];

        return $definitions;
    }
}

if (!function_exists('meza_normalize_acf_textarea_option_value')) {
    function meza_normalize_acf_textarea_option_value($value): string
    {
        if (!is_array($value)) {
            return (string) $value;
        }

        $preferred_keys = [
            'address',
            'formatted_address',
            'place_name',
            'street_name',
            'street_number',
            'city',
            'state',
            'state_short',
            'post_code',
            'country',
            'country_short',
        ];

        foreach (['address', 'formatted_address'] as $key) {
            if (isset($value[$key]) && !is_array($value[$key])) {
                $formatted = trim((string) $value[$key]);
                if ($formatted !== '') {
                    return $formatted;
                }
            }
        }

        $parts = [];

        $append_scalar = static function ($candidate) use (&$parts): void {
            if (is_array($candidate)) {
                return;
            }

            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                $parts[$candidate] = true;
            }
        };

        foreach ($preferred_keys as $key) {
            if (array_key_exists($key, $value)) {
                $append_scalar($value[$key]);
            }
        }

        array_walk_recursive($value, static function ($candidate) use (&$parts): void {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                $parts[$candidate] = true;
            }
        });

        return implode("\n", array_keys($parts));
    }
}

if (!function_exists('meza_acf_google_map_has_meaningful_value')) {
    function meza_acf_google_map_has_meaningful_value($value): bool
    {
        if (is_array($value)) {
            foreach (['address', 'formatted_address', 'place_name', 'name', 'place_id'] as $key) {
                if (!array_key_exists($key, $value) || is_array($value[$key])) {
                    continue;
                }

                if (trim((string) $value[$key]) !== '') {
                    return true;
                }
            }

            return false;
        }

        return trim((string) $value) !== '';
    }
}

add_filter('acf/load_value', static function ($value, $post_id, $field) {
    if (($field['type'] ?? '') !== 'textarea' || !is_array($value)) {
        return $value;
    }

    $normalized_post_id = is_scalar($post_id) ? (string) $post_id : '';
    if ($normalized_post_id !== '' && !in_array($normalized_post_id, ['option', 'options'], true) && !str_starts_with($normalized_post_id, 'options_')) {
        return $value;
    }

    return meza_normalize_acf_textarea_option_value($value);
}, 5, 3);

add_filter('acf/load_value/key=field_69b5a2b623bc0', static function ($value, $post_id, $field) {
    if (meza_acf_google_map_has_meaningful_value($value)) {
        return $value;
    }

    return '';
}, 5, 3);

add_filter('acf/update_value', static function ($value, $post_id, $field) {
    if (($field['type'] ?? '') !== 'textarea' || !is_array($value)) {
        return $value;
    }

    $normalized_post_id = is_scalar($post_id) ? (string) $post_id : '';
    if ($normalized_post_id !== '' && !in_array($normalized_post_id, ['option', 'options'], true) && !str_starts_with($normalized_post_id, 'options_')) {
        return $value;
    }

    return meza_normalize_acf_textarea_option_value($value);
}, 5, 3);

add_filter('acf/update_value/key=field_69b5a2b623bc0', static function ($value, $post_id, $field) {
    if (meza_acf_google_map_has_meaningful_value($value)) {
        return $value;
    }

    return '';
}, 5, 3);

add_filter('acf/validate_value/name=locations', static function ($valid, $value, $field, $input) {
    if ($valid !== true) {
        return $valid;
    }

    if (meza_count_primary_business_information_locations($value) > 1) {
        return 'There can only be 1 primary location.';
    }

    return $valid;
}, 20, 4);

add_filter('acf/validate_value/name=use_primary_location_phone', static function ($valid, $value) {
    return meza_validate_business_information_primary_location_dependency($valid, 'phone', $value);
}, 20, 4);

add_filter('acf/validate_value/key=field_meza_business_email_group', static function ($valid, $value, $field) {
    return meza_validate_business_information_email_group($valid, $value, is_array($field) ? $field : []);
}, 20, 4);

add_filter('acf/validate_value/key=field_meza_business_address_group', static function ($valid, $value, $field) {
    return meza_validate_business_information_address_group($valid, $value, is_array($field) ? $field : []);
}, 20, 4);

add_filter('acf/validate_value/key=field_68c133a0425a8', static function ($valid, $value) {
    if (!meza_is_business_information_acf_submission()) {
        return $valid;
    }

    return true;
}, 20, 4);

add_filter('acf/validate_value/key=field_69b5a2b623bc0', static function ($valid, $value) {
    if (!meza_is_business_information_acf_submission()) {
        return $valid;
    }

    return true;
}, 20, 4);

add_filter('acf/validate_value/key=field_meza_business_mailing_address', static function ($valid, $value) {
    if (!meza_is_business_information_acf_submission()) {
        return $valid;
    }

    return true;
}, 20, 4);

add_filter('acf/validate_value/name=use_primary_location_email', static function ($valid, $value) {
    return meza_validate_business_information_primary_location_dependency($valid, 'email', $value);
}, 20, 4);

add_filter('acf/validate_value/name=use_primary_location_address', static function ($valid, $value) {
    return meza_validate_business_information_primary_location_dependency($valid, 'address', $value);
}, 20, 4);

add_filter('acf/prepare_field/key=field_meza_business_location_primary', static function ($field) {
    if (!is_admin()) {
        return $field;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen)) {
        return $field;
    }

    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    if ($page !== 'business-information' || !meza_business_information_has_primary_location()) {
        return $field;
    }

    return !empty($field['value']) ? $field : false;
}, 20);

foreach (meza_get_business_information_branding_field_map() as $field_name => $callbacks) {
    if (isset($callbacks['load']) && is_callable($callbacks['load'])) {
        add_filter("acf/load_value/name={$field_name}", static function ($value, $post_id, $field) use ($callbacks) {
            return call_user_func($callbacks['load']);
        }, 20, 3);
    }

    if (isset($callbacks['validate']) && is_callable($callbacks['validate'])) {
        add_filter("acf/validate_value/name={$field_name}", static function ($valid, $value, $field, $input) use ($callbacks) {
            return call_user_func($callbacks['validate'], $valid, $value);
        }, 20, 4);
    }

    if (isset($callbacks['update']) && is_callable($callbacks['update'])) {
        add_filter("acf/update_value/name={$field_name}", static function ($value, $post_id, $field) use ($callbacks) {
            return call_user_func($callbacks['update'], $value);
        }, 20, 3);
    }
}

add_filter('wp_prepare_themes_for_js', static function (array $themes): array {
    $thumbnail_id = meza_get_theme_thumbnail_id();
    if ($thumbnail_id <= 0) {
        return $themes;
    }

    $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'full');
    if (!is_string($thumbnail_url) || $thumbnail_url === '') {
        return $themes;
    }

    $current_theme = get_stylesheet();

    foreach ($themes as &$theme) {
        if (!is_array($theme) || (string) ($theme['id'] ?? '') !== $current_theme) {
            continue;
        }

        $theme['screenshot'] = [$thumbnail_url];
        break;
    }
    unset($theme);

    return $themes;
}, 20);

add_filter('wpseo_add_opengraph_additional_images', static function ($image_container) {
    if (is_object($image_container) && method_exists($image_container, 'has_images') && $image_container->has_images()) {
        return $image_container;
    }

    $default_id = meza_get_social_share_default_id();
    if ($default_id <= 0 || !is_object($image_container) || !method_exists($image_container, 'add_image_by_id')) {
        return $image_container;
    }

    $image_container->add_image_by_id($default_id);

    return $image_container;
}, 20);

add_filter('wpseo_twitter_image', static function ($image_url): string {
    $image_url = is_scalar($image_url) ? trim((string) $image_url) : '';
    if ($image_url !== '') {
        return $image_url;
    }

    return meza_get_social_share_default_url();
}, 20);

add_filter('wpseo_post_edit_values', static function (array $values, $post): array {
    if (!($post instanceof WP_Post) || !meza_post_is_using_site_default_social_share_image((int) $post->ID)) {
        return $values;
    }

    $default_image_url = meza_get_social_share_default_url();
    if ($default_image_url === '') {
        return $values;
    }

    $values['social_image_template'] = $default_image_url;

    return $values;
}, 20, 2);

add_action('admin_enqueue_scripts', static function (): void {
    if (!is_admin() || !function_exists('get_current_screen')) {
        return;
    }

    $screen = get_current_screen();
    if (!($screen instanceof WP_Screen) || !in_array((string) $screen->base, ['post', 'post-new'], true)) {
        return;
    }

    $post_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;
    if ($post_id <= 0) {
        $post = get_post();
        $post_id = $post instanceof WP_Post ? (int) $post->ID : 0;
    }

    if ($post_id <= 0 || !meza_post_uses_share_image_permalink($post_id)) {
        return;
    }

    $default_image = meza_get_social_share_default_image_payload();
    if (empty($default_image)) {
        return;
    }

    $config = [
        'image' => $default_image,
        'hasExplicitFacebookImage' => meza_post_has_explicit_social_share_image_meta($post_id, [
            '_yoast_wpseo_opengraph-image-id',
            '_yoast_wpseo_opengraph-image',
        ]),
        'hasExplicitTwitterImage' => meza_post_has_explicit_social_share_image_meta($post_id, [
            '_yoast_wpseo_twitter-image-id',
            '_yoast_wpseo_twitter-image',
        ]),
    ];

    $script = 'window.mezaYoastSocialShareDefault = ' . wp_json_encode($config) . ';'
        . '(function(config){'
        . 'if(!config||!config.image||!config.image.url){return;}'
        . 'var attempts=0;'
        . 'var apply=function(){'
        . 'if(!window.wp||!window.wp.data||typeof window.wp.data.dispatch!=="function"){return false;}'
        . 'var store=window.wp.data.dispatch("yoast-seo/editor");'
        . 'if(!store){return false;}'
        . 'if(typeof store.loadFacebookPreviewData==="function"){store.loadFacebookPreviewData();}'
        . 'if(typeof store.loadTwitterPreviewData==="function"){store.loadTwitterPreviewData();}'
        . 'if(!config.hasExplicitFacebookImage&&typeof store.setFacebookPreviewImage==="function"){store.setFacebookPreviewImage(config.image);}'
        . 'if(!config.hasExplicitTwitterImage&&typeof store.setTwitterPreviewImage==="function"){store.setTwitterPreviewImage({id:config.image.id,url:config.image.url,alt:config.image.alt||"",warnings:config.image.warnings||[]});}'
        . 'return true;'
        . '};'
        . 'if(apply()){return;}'
        . 'var timer=window.setInterval(function(){attempts+=1;if(apply()||attempts>40){window.clearInterval(timer);}},150);'
        . '})(window.mezaYoastSocialShareDefault);';

    foreach (['yoast-seo-post-edit', 'yoast-seo-post-edit-classic'] as $handle) {
        if (wp_script_is($handle, 'registered') || wp_script_is($handle, 'enqueued')) {
            wp_add_inline_script($handle, $script, 'after');
        }
    }
}, 20);

if (!function_exists('meza_is_business_information_options_screen')) {
    function meza_is_business_information_options_screen(): bool
    {
        if (!is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        return $page === 'branding';
    }
}

add_filter('acf/ui_options_page/registration_args', function (array $args, array $post): array {
    $menu_slug = (string) ($args['menu_slug'] ?? '');

    if ($menu_slug === 'branding') {
        $args['page_title'] = 'Branding Settings';
        $args['menu_title'] = 'Branding';

        return $args;
    }

    if ($menu_slug === 'business-information') {
        $args['page_title'] = meza_get_business_information_page_title();
        $args['menu_title'] = meza_get_business_information_menu_label();

        return $args;
    }

    if ($menu_slug === 'crm') {
        $args['page_title'] = 'CRM Integration Settings';
        $args['menu_title'] = 'CRM Integration';
        $args['capability'] = 'manage_options';

        return $args;
    }

    if ($menu_slug === 'conference') {
        $args['page_title'] = 'Conference';
        $args['menu_title'] = 'Conference';
        $args['menu_slug'] = 'conference';
        $args['capability'] = 'manage_options';
        $args['parent_slug'] = 'none';

        return $args;
    }

    if ($menu_slug === 'conference-schedule') {
        $args['page_title'] = 'Schedule';
        $args['menu_title'] = 'Schedule';
        $args['menu_slug'] = 'conference-schedule';
        $args['capability'] = 'manage_options';
        $args['parent_slug'] = 'conference';

        return $args;
    }

    return $args;
}, 20, 2);

add_filter('acf/get_options_page', function ($page, $slug) {
    if (!is_array($page)) {
        return $page;
    }

    $slug = sanitize_key((string) $slug);
    $legacy_slug_map = meza_get_legacy_shared_project_acf_options_page_slug_map();
    if (isset($legacy_slug_map[$slug])) {
        $slug = $legacy_slug_map[$slug];
    }

    if ($slug === 'branding') {
        $page['page_title'] = 'Branding Settings';
        $page['menu_title'] = 'Branding';
    } elseif ($slug === 'business-information') {
        $page['page_title'] = meza_get_business_information_page_title();
        $page['menu_title'] = meza_get_business_information_menu_label();
        $page['menu_slug'] = 'business-information';
        $page['capability'] = 'manage_options';
    } elseif ($slug === 'crm') {
        $page['page_title'] = 'CRM Integration Settings';
        $page['menu_title'] = 'CRM Integration';
        $page['menu_slug'] = 'crm';
        $page['capability'] = 'manage_options';
    } elseif ($slug === 'conference') {
        $page['page_title'] = 'Conference';
        $page['menu_title'] = 'Conference';
        $page['menu_slug'] = 'conference';
        $page['capability'] = 'manage_options';
        $page['parent_slug'] = 'none';
    } elseif ($slug === 'conference-schedule') {
        $page['page_title'] = 'Schedule';
        $page['menu_title'] = 'Schedule';
        $page['menu_slug'] = 'conference-schedule';
        $page['capability'] = 'manage_options';
        $page['parent_slug'] = 'conference';
    }

    return $page;
}, 20, 2);

add_filter('acf/get_options_pages', function ($pages) {
    if (!is_array($pages)) {
        return $pages;
    }

    foreach (meza_get_legacy_shared_project_acf_options_page_slug_map() as $legacy_slug => $current_slug) {
        if (isset($pages[$current_slug])) {
            unset($pages[$legacy_slug]);
            continue;
        }

        if (!isset($pages[$legacy_slug]) || !is_array($pages[$legacy_slug])) {
            continue;
        }

        $page = $pages[$legacy_slug];
        $page['menu_slug'] = $current_slug;
        $page['page_title'] = meza_get_business_information_page_title();
        $page['menu_title'] = meza_get_business_information_menu_label();
        $page['capability'] = 'manage_options';
        $pages[$current_slug] = $page;
        unset($pages[$legacy_slug]);
    }

    return $pages;
}, 50);

add_action('admin_init', function (): void {
    if (!is_admin()) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    if ($page === '') {
        return;
    }

    $legacy_slug_map = meza_get_legacy_shared_project_acf_options_page_slug_map();
    if (!isset($legacy_slug_map[$page])) {
        return;
    }

    wp_safe_redirect(admin_url('options-general.php?page=' . $legacy_slug_map[$page]));
    exit;
}, 1);

add_filter('parent_file', function ($parent_file) {
    if (!is_admin()) {
        return $parent_file;
    }

    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    if ($page === '' || !in_array($page, meza_get_shared_project_acf_options_page_slugs(), true)) {
        return $parent_file;
    }

    return 'options-general.php';
}, 20);

add_filter('submenu_file', function ($submenu_file) {
    if (!is_admin()) {
        return $submenu_file;
    }

    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    if ($page === '' || !in_array($page, meza_get_shared_project_acf_options_page_slugs(), true)) {
        return $submenu_file;
    }

    return $page;
}, 20);

if (!function_exists('meza_normalize_branding_settings_submenu_item')) {
    function meza_normalize_branding_settings_submenu_item(): void
    {
        global $submenu;

        if (!isset($submenu['options-general.php']) || !is_array($submenu['options-general.php'])) {
            return;
        }

        $acf_options_available = meza_shared_project_acf_options_are_available();
        $is_site_manager = function_exists('meza_is_site_manager_user') && meza_is_site_manager_user(wp_get_current_user());
        $menu_capability = $is_site_manager ? 'read' : 'manage_options';

        $business_information_item = null;
        $branding_item = null;
        $crm_item = null;
        $business_information_menu_label = meza_get_business_information_menu_label();
        $business_information_page_title = meza_get_business_information_page_title();
        $expected_items = [
            'business-information' => [$business_information_menu_label, $menu_capability, 'business-information', $business_information_page_title],
            'branding' => ['Branding', $menu_capability, 'branding', 'Branding Settings'],
            'crm' => ['CRM Integration', 'manage_options', 'crm', 'CRM Integration Settings'],
        ];
        $allowed_expected_item_slugs = array_keys($expected_items);

        if ($is_site_manager) {
            if (function_exists('meza_get_site_manager_allowed_settings_page_slugs')) {
                $allowed_expected_item_slugs = array_values(array_intersect(
                    $allowed_expected_item_slugs,
                    array_map('sanitize_key', meza_get_site_manager_allowed_settings_page_slugs())
                ));
            }
        }

        foreach ($submenu['options-general.php'] as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $slug = (string) ($item[2] ?? '');

            if (in_array($slug, ['business-information', 'admin.php?page=business-information', 'options-general.php?page=business-information'], true)) {
                if (!$acf_options_available) {
                    unset($submenu['options-general.php'][$index]);
                    continue;
                }

                $submenu['options-general.php'][$index][0] = $business_information_menu_label;
                $submenu['options-general.php'][$index][1] = $menu_capability;
                $submenu['options-general.php'][$index][2] = 'business-information';
                if (isset($submenu['options-general.php'][$index][3])) {
                    $submenu['options-general.php'][$index][3] = $business_information_page_title;
                }

                $business_information_item = $submenu['options-general.php'][$index];
                unset($submenu['options-general.php'][$index]);
                continue;
            }

            if (in_array($slug, ['crm', 'admin.php?page=crm', 'options-general.php?page=crm'], true)) {
                if (!$acf_options_available) {
                    unset($submenu['options-general.php'][$index]);
                    continue;
                }

                $submenu['options-general.php'][$index][0] = 'CRM Integration';
                $submenu['options-general.php'][$index][2] = 'crm';
                if (isset($submenu['options-general.php'][$index][3])) {
                    $submenu['options-general.php'][$index][3] = 'CRM Integration Settings';
                }

                $crm_item = $submenu['options-general.php'][$index];
                unset($submenu['options-general.php'][$index]);
                continue;
            }

            if (!in_array($slug, ['branding', 'admin.php?page=branding', 'options-general.php?page=branding'], true)) {
                continue;
            }

            if (!$acf_options_available) {
                unset($submenu['options-general.php'][$index]);
                continue;
            }

            $submenu['options-general.php'][$index][0] = 'Branding';
            $submenu['options-general.php'][$index][1] = $menu_capability;
            $submenu['options-general.php'][$index][2] = 'branding';
            if (isset($submenu['options-general.php'][$index][3])) {
                $submenu['options-general.php'][$index][3] = 'Branding Settings';
            }

            $branding_item = $submenu['options-general.php'][$index];
            unset($submenu['options-general.php'][$index]);
        }

        if (!$acf_options_available) {
            $submenu['options-general.php'] = array_values($submenu['options-general.php']);
            return;
        }

        if ($business_information_item === null && in_array('business-information', $allowed_expected_item_slugs, true)) {
            $business_information_item = $expected_items['business-information'];
        }

        if ($branding_item === null && in_array('branding', $allowed_expected_item_slugs, true)) {
            $branding_item = $expected_items['branding'];
        }

        if ($crm_item === null && in_array('crm', $allowed_expected_item_slugs, true)) {
            $crm_item = $expected_items['crm'];
        }

        $submenu['options-general.php'] = array_values($submenu['options-general.php']);

        $insert_index = 1;
        foreach ($submenu['options-general.php'] as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $slug = (string) ($item[2] ?? '');
            if ($slug === 'options-writing.php') {
                $insert_index = $index;
                break;
            }
        }

        $items_to_insert = array_values(array_filter([
            $business_information_item,
            $branding_item,
            $crm_item,
        ], 'is_array'));

        if ($items_to_insert === []) {
            return;
        }

        array_splice($submenu['options-general.php'], $insert_index, 0, $items_to_insert);

        if ($is_site_manager) {
            $desired_order = [
                $business_information_menu_label,
                'Branding',
                'Privacy',
                'CRM Integration',
            ];

            usort($submenu['options-general.php'], static function (array $a, array $b) use ($desired_order): int {
                $label_a = trim(wp_strip_all_tags((string) ($a[0] ?? '')));
                $label_b = trim(wp_strip_all_tags((string) ($b[0] ?? '')));
                $index_a = array_search($label_a, $desired_order, true);
                $index_b = array_search($label_b, $desired_order, true);

                $index_a = ($index_a === false) ? PHP_INT_MAX : (int) $index_a;
                $index_b = ($index_b === false) ? PHP_INT_MAX : (int) $index_b;

                if ($index_a === $index_b) {
                    return strnatcasecmp($label_a, $label_b);
                }

                return $index_a <=> $index_b;
            });
        }
    }
}

add_action('admin_menu', 'meza_normalize_branding_settings_submenu_item', PHP_INT_MAX - 1);
add_action('admin_menu_editor-menu_replaced', 'meza_normalize_branding_settings_submenu_item', PHP_INT_MAX - 1);
add_action('admin_menu', 'meza_normalize_branding_settings_submenu_item', PHP_INT_MAX);
add_action('admin_menu_editor-menu_replaced', 'meza_normalize_branding_settings_submenu_item', PHP_INT_MAX);

add_action('after_setup_theme', function (): void {
    if (function_exists('add_image_size')) {
        add_image_size('meza_branding_preview', 250, 50, false);
        add_image_size('meza_theme_thumbnail_preview', 250, 188, false);
    }
}, 20);

add_action('admin_head', function (): void {
    if (!meza_is_business_information_options_screen()) {
        return;
    }
?>
    <style id="meza-business-branding-preview-fix">
        #acf-group_meza_business_branding .acf-image-uploader .image-wrap,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-image-uploader .image-wrap {
            padding: 10px;
            box-sizing: border-box;
            background: #f0f0f1;
        }

        #acf-group_meza_business_branding .acf-field[data-name="wp_site_logo_alternative"] .acf-image-uploader .image-wrap,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-field[data-name="wp_site_logo_alternative"] .acf-image-uploader .image-wrap {
            background: #1d2327;
        }

        #acf-group_meza_business_branding .acf-field[data-name="wp_site_logo_alternative"] .acf-image-uploader .image-wrap img,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-field[data-name="wp_site_logo_alternative"] .acf-image-uploader .image-wrap img {
            background: transparent;
        }

        #acf-group_meza_business_branding .acf-field[data-name="wp_theme_thumbnail"] .acf-image-uploader .image-wrap,
        #acf-group_meza_business_branding .acf-field[data-name="wp_social_share_default"] .acf-image-uploader .image-wrap,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-field[data-name="wp_theme_thumbnail"] .acf-image-uploader .image-wrap,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-field[data-name="wp_social_share_default"] .acf-image-uploader .image-wrap {
            width: 250px;
            height: 188px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        #acf-group_meza_business_branding .acf-field[data-name="wp_theme_thumbnail"] .acf-image-uploader .image-wrap img,
        #acf-group_meza_business_branding .acf-field[data-name="wp_social_share_default"] .acf-image-uploader .image-wrap img,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-field[data-name="wp_theme_thumbnail"] .acf-image-uploader .image-wrap img,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-field[data-name="wp_social_share_default"] .acf-image-uploader .image-wrap img {
            width: 100%;
            height: 100%;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        #acf-group_meza_business_branding .acf-label p,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-label p {
            display: block;
            margin-top: 6px;
            color: #646970;
        }

        #acf-group_meza_business_branding .acf-input>p.description,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-input>p.description {
            display: none;
        }

        #acf-group_meza_business_branding .acf-image-uploader .image-wrap img[src$=".svg"],
        .acf-postbox[data-key="group_meza_business_branding"] .acf-image-uploader .image-wrap img[src$=".svg"] {
            min-width: 0;
            min-height: 0;
        }

        #acf-group_meza_business_branding .acf-field[data-name="wp_site_icon"] .acf-image-uploader,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-field[data-name="wp_site_icon"] .acf-image-uploader {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }

        #acf-group_meza_business_branding .meza-site-icon-dark-preview,
        .acf-postbox[data-key="group_meza_business_branding"] .meza-site-icon-dark-preview {
            display: none;
            padding: 10px;
            box-sizing: border-box;
            background: #1d2327;
        }

        #acf-group_meza_business_branding .meza-site-icon-dark-preview.is-visible,
        .acf-postbox[data-key="group_meza_business_branding"] .meza-site-icon-dark-preview.is-visible {
            display: block;
        }

        #acf-group_meza_business_branding .meza-site-icon-dark-preview img,
        .acf-postbox[data-key="group_meza_business_branding"] .meza-site-icon-dark-preview img {
            display: block;
            width: auto;
            height: auto;
            max-width: 100%;
            max-height: 50px;
            background: transparent;
        }

        #acf-group_meza_business_branding .acf-field[data-name="wp_site_icon"] .acf-image-uploader .hide-if-value,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-field[data-name="wp_site_icon"] .acf-image-uploader .hide-if-value {
            flex-basis: 100%;
        }
    </style>
    <script id="meza-business-branding-label-notes">
        (() => {
            const moveDescriptions = () => {
                const fields = document.querySelectorAll(
                    '#acf-group_meza_business_branding .acf-field, .acf-postbox[data-key="group_meza_business_branding"] .acf-field'
                );

                fields.forEach((field) => {
                    if (!(field instanceof HTMLElement)) {
                        return;
                    }

                    const label = field.querySelector(':scope > .acf-label');
                    const inputDescription = field.querySelector(':scope > .acf-input > p.description');

                    if (!(label instanceof HTMLElement) || !(inputDescription instanceof HTMLElement)) {
                        return;
                    }

                    let labelDescription = label.querySelector(':scope > p');
                    if (!(labelDescription instanceof HTMLElement)) {
                        labelDescription = document.createElement('p');
                        label.appendChild(labelDescription);
                    }

                    labelDescription.textContent = inputDescription.textContent ?? '';
                });
            };

            const syncSiteIconDarkPreview = (uploader) => {
                if (!(uploader instanceof HTMLElement)) {
                    return;
                }

                let preview = uploader.querySelector(':scope > .meza-site-icon-dark-preview');
                if (!(preview instanceof HTMLElement)) {
                    preview = document.createElement('div');
                    preview.className = 'meza-site-icon-dark-preview';
                    preview.innerHTML = '<img alt="" />';
                    const hideIfValue = uploader.querySelector(':scope > .hide-if-value');
                    if (hideIfValue instanceof HTMLElement) {
                        uploader.insertBefore(preview, hideIfValue);
                    } else {
                        uploader.appendChild(preview);
                    }
                }

                const previewImage = preview.querySelector('img');
                const sourceImage = uploader.querySelector(':scope > .show-if-value.image-wrap img');
                const hasValue = uploader.classList.contains('has-value') &&
                    sourceImage instanceof HTMLImageElement &&
                    sourceImage.getAttribute('src');

                if (!(previewImage instanceof HTMLImageElement) || !hasValue) {
                    preview.classList.remove('is-visible');
                    if (previewImage instanceof HTMLImageElement) {
                        previewImage.removeAttribute('src');
                        previewImage.removeAttribute('alt');
                    }
                    return;
                }

                previewImage.src = sourceImage.getAttribute('src') || '';
                previewImage.alt = sourceImage.getAttribute('alt') || '';
                preview.classList.add('is-visible');
            };

            const bindSiteIconSourceObserver = (uploader) => {
                if (!(uploader instanceof HTMLElement)) {
                    return;
                }

                if (uploader.mezaSiteIconSourceObserver instanceof MutationObserver) {
                    uploader.mezaSiteIconSourceObserver.disconnect();
                }

                const sourceImage = uploader.querySelector(':scope > .show-if-value.image-wrap img');
                if (!(sourceImage instanceof HTMLImageElement)) {
                    uploader.mezaSiteIconSourceObserver = null;
                    return;
                }

                const sourceObserver = new MutationObserver(() => {
                    syncSiteIconDarkPreview(uploader);
                });

                sourceObserver.observe(sourceImage, {
                    attributes: true,
                    attributeFilter: ['src', 'alt'],
                });

                uploader.mezaSiteIconSourceObserver = sourceObserver;
            };

            const setupSiteIconDarkPreview = () => {
                const uploaders = document.querySelectorAll(
                    '#acf-group_meza_business_branding .acf-field[data-name="wp_site_icon"] .acf-image-uploader, .acf-postbox[data-key="group_meza_business_branding"] .acf-field[data-name="wp_site_icon"] .acf-image-uploader'
                );

                uploaders.forEach((uploader) => {
                    if (!(uploader instanceof HTMLElement)) {
                        return;
                    }

                    syncSiteIconDarkPreview(uploader);
                    bindSiteIconSourceObserver(uploader);

                    if (uploader.dataset.mezaSiteIconPreviewReady === '1') {
                        return;
                    }

                    const classObserver = new MutationObserver(() => {
                        syncSiteIconDarkPreview(uploader);
                        bindSiteIconSourceObserver(uploader);
                    });

                    classObserver.observe(uploader, {
                        attributes: true,
                        attributeFilter: ['class'],
                    });

                    const treeObserver = new MutationObserver(() => {
                        syncSiteIconDarkPreview(uploader);
                        bindSiteIconSourceObserver(uploader);
                    });

                    treeObserver.observe(uploader, {
                        childList: true,
                        subtree: true,
                    });

                    uploader.dataset.mezaSiteIconPreviewReady = '1';
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    moveDescriptions();
                    setupSiteIconDarkPreview();
                }, {
                    once: true
                });
            } else {
                moveDescriptions();
                setupSiteIconDarkPreview();
            }
        })();
    </script>
<?php
});

add_action('admin_head', function (): void {
    if (!is_admin()) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    if ($page !== 'business-information') {
        return;
    }
?>
    <style id="meza-business-contact-compact-ui">
        #acf-group_68c1337b43d46 .acf-field[data-key="field_meza_business_phone_group"]>.acf-label,
        #acf-group_68c1337b43d46 .acf-field[data-key="field_meza_business_email_group"]>.acf-label,
        #acf-group_68c1337b43d46 .acf-field[data-key="field_meza_business_address_group"]>.acf-label,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-key="field_meza_business_phone_group"]>.acf-label,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-key="field_meza_business_email_group"]>.acf-label,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-key="field_meza_business_address_group"]>.acf-label {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }

        #acf-group_68c1337b43d46 .acf-field[data-key="field_meza_business_phone_group"]>.acf-input,
        #acf-group_68c1337b43d46 .acf-field[data-key="field_meza_business_email_group"]>.acf-input,
        #acf-group_68c1337b43d46 .acf-field[data-key="field_meza_business_address_group"]>.acf-input,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-key="field_meza_business_phone_group"]>.acf-input,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-key="field_meza_business_email_group"]>.acf-input,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-key="field_meza_business_address_group"]>.acf-input {
            padding-left: 12px !important;
            padding-right: 12px !important;
        }

        #acf-group_68c1337b43d46 .acf-field[data-key="field_meza_business_email_group"]>.acf-label label::after,
        #acf-group_68c1337b43d46 .acf-field[data-key="field_meza_business_address_group"]>.acf-label label::after,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-key="field_meza_business_email_group"]>.acf-label label::after,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-key="field_meza_business_address_group"]>.acf-label label::after {
            content: " *";
            color: #f00;
        }

        #acf-group_68c1337b43d46 .acf-field[data-type="group"]>.acf-input>.acf-fields,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-type="group"]>.acf-input>.acf-fields {
            border: 0;
            background: transparent;
        }

        #acf-group_68c1337b43d46 .acf-field[data-type="group"]>.acf-input>.acf-fields>.acf-field,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-type="group"]>.acf-input>.acf-fields>.acf-field {
            border: 0;
            margin: 0;
            padding: 0 0 8px;
        }

        #acf-group_68c1337b43d46 .acf-field[data-type="group"]>.acf-input>.acf-fields>.acf-field:last-child,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-type="group"]>.acf-input>.acf-fields>.acf-field:last-child {
            padding-bottom: 0;
        }

        #acf-group_68c1337b43d46 .acf-field[data-type="group"]>.acf-input>.acf-fields>.acf-field .acf-label,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-type="group"]>.acf-input>.acf-fields>.acf-field .acf-label {
            margin: 0;
            padding: 0;
        }

        #acf-group_68c1337b43d46 .acf-field[data-type="group"]>.acf-input>.acf-fields>.acf-field .acf-input,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-type="group"]>.acf-input>.acf-fields>.acf-field .acf-input {
            margin: 0;
            padding: 0;
        }

        #acf-group_68c1337b43d46 .acf-field[data-type="group"]>.acf-input>.acf-fields>.acf-field .acf-input .description,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-type="group"]>.acf-input>.acf-fields>.acf-field .acf-input .description {
            margin: 0 0 8px;
        }
    </style>
<?php
}, 20);

add_action('acf/init', function (): void {
    if (!function_exists('acf_add_options_page') || !function_exists('acf_add_local_field_group')) {
        return;
    }

    $is_export_tools_screen = meza_is_acf_export_tools_screen();

    if (function_exists('acf_add_local_internal_post_type')) {
        foreach (meza_get_local_acf_post_type_definitions() as $definition) {
            if (
                !is_array($definition)
                || empty($definition['key'])
                || (
                    !$is_export_tools_screen
                    && meza_should_skip_local_acf_post_type_definition($definition)
                )
                || (
                    !$is_export_tools_screen
                    && meza_is_managed_acf_definition_manually_deleted('post_types', $definition)
                )
            ) {
                continue;
            }

            acf_add_local_internal_post_type($definition, 'acf-post-type');
        }

        if ($is_export_tools_screen) {
            foreach (meza_get_local_acf_taxonomy_definitions() as $definition) {
                if (!is_array($definition) || empty($definition['key'])) {
                    continue;
                }

                acf_add_local_internal_post_type($definition, 'acf-taxonomy');
            }
        }
    }

    foreach (meza_get_shared_project_acf_options_pages() as $page) {
        if (
            !is_array($page)
            || (
                !$is_export_tools_screen
                && meza_is_managed_acf_definition_manually_deleted('options_pages', $page)
            )
        ) {
            continue;
        }

        $page_slug = sanitize_key((string) ($page['menu_slug'] ?? ''));
        if ($page_slug === '') {
            continue;
        }

        acf_add_options_page($page);

        if ($is_export_tools_screen && function_exists('acf_add_local_internal_post_type')) {
            $export_page = $page;
            $export_page['key'] = !empty($export_page['key'])
                ? (string) $export_page['key']
                : 'ui_options_page_' . $page_slug;
            $export_page['title'] = (string) ($export_page['page_title'] ?? $export_page['menu_title'] ?? $page_slug);
            $export_page['menu_slug'] = $page_slug;
            $export_page['active'] = array_key_exists('active', $export_page) ? (bool) $export_page['active'] : true;

            acf_add_local_internal_post_type($export_page, 'acf-ui-options-page');
        }
    }

    foreach (meza_get_shared_project_acf_field_groups() as $group) {
        if (
            !is_array($group)
            || empty($group['key'])
            || meza_is_managed_acf_definition_manually_deleted('field_groups', $group)
        ) {
            continue;
        }

        acf_add_local_field_group($group);
    }
}, 15);

add_action('acf/init', 'meza_seed_default_editable_acf_taxonomies', 18);
add_action('acf/init', 'meza_sync_default_editable_acf_field_group_order_and_names', 19);
add_action('acf/init', 'meza_seed_default_editable_acf_field_groups', 20);

if (!function_exists('meza_sync_hero_section_field_group_locations')) {
    function meza_get_hero_section_field_group_location_sync_version(): string
    {
        return '2026-05-01-hero-section-location-v1';
    }

    function meza_get_hero_section_field_group_location_sync_option_name(): string
    {
        return 'meza_hero_section_field_group_location_sync_version';
    }

    function meza_sync_hero_section_field_group_locations(): void
    {
        if (!function_exists('acf_get_field_group') || !function_exists('acf_update_field_group')) {
            return;
        }

        $version = meza_get_hero_section_field_group_location_sync_version();
        if ((string) get_option(meza_get_hero_section_field_group_location_sync_option_name(), '') === $version) {
            return;
        }

        $definition = meza_get_hero_section_field_group_definition();
        $field_group_id = meza_get_existing_editable_acf_field_group_id($definition);
        if ($field_group_id <= 0) {
            update_option(meza_get_hero_section_field_group_location_sync_option_name(), $version, false);
            return;
        }

        $field_group = acf_get_field_group($field_group_id);
        if (!is_array($field_group)) {
            return;
        }

        $field_group['location'] = $definition['location'] ?? [];
        $field_group['menu_order'] = (int) ($definition['menu_order'] ?? 0);
        $field_group['title'] = (string) ($definition['title'] ?? ($field_group['title'] ?? ''));

        acf_update_field_group($field_group);

        update_option(meza_get_hero_section_field_group_location_sync_option_name(), $version, false);
    }
}
add_action('acf/init', 'meza_sync_hero_section_field_group_locations', 21);

if (!function_exists('meza_sync_list_reviews_section_field_group_locations')) {
    function meza_get_list_reviews_section_field_group_location_sync_version(): string
    {
        return '2026-05-02-list-reviews-location-v1';
    }

    function meza_get_list_reviews_section_field_group_location_sync_option_name(): string
    {
        return 'meza_list_reviews_section_field_group_location_sync_version';
    }

    function meza_sync_list_reviews_section_field_group_locations(): void
    {
        if (!function_exists('acf_get_field_group') || !function_exists('acf_update_field_group')) {
            return;
        }

        $version = meza_get_list_reviews_section_field_group_location_sync_version();
        if ((string) get_option(meza_get_list_reviews_section_field_group_location_sync_option_name(), '') === $version) {
            return;
        }

        $definition = meza_get_list_reviews_section_field_group_definition();
        $field_group_id = meza_get_existing_editable_acf_field_group_id($definition);
        if ($field_group_id <= 0) {
            update_option(meza_get_list_reviews_section_field_group_location_sync_option_name(), $version, false);
            return;
        }

        $field_group = acf_get_field_group($field_group_id);
        if (!is_array($field_group)) {
            return;
        }

        $field_group['location'] = $definition['location'] ?? [];
        $field_group['menu_order'] = (int) ($definition['menu_order'] ?? 0);
        $field_group['title'] = (string) ($definition['title'] ?? ($field_group['title'] ?? ''));

        acf_update_field_group($field_group);

        update_option(meza_get_list_reviews_section_field_group_location_sync_option_name(), $version, false);
    }
}
add_action('acf/init', 'meza_sync_list_reviews_section_field_group_locations', 22);

if (!function_exists('meza_sync_section_field_group_menu_order')) {
    function meza_get_section_field_group_menu_order_sync_version(): string
    {
        return '2026-05-02-section-field-group-menu-order-v3';
    }

    function meza_get_section_field_group_menu_order_sync_option_name(): string
    {
        return 'meza_section_field_group_menu_order_sync_version';
    }

    function meza_get_section_field_group_menu_order_map(): array
    {
        return [
            'Hero Section' => 1,
            'List Reviews Section' => 3,
            'List Brands Section' => 5,
            'List Services Section' => 7,
            'List Products Section' => 8,
            'Gallery Section' => 10,
            'List Partners Section' => 11,
            'List Profiles Section' => 12,
            'Benefits Section' => 13,
            'List Localities Section' => 14,
            'Form Section' => 16,
            'List Locations Section' => 18,
            'CTA Section' => 20,
            'List Resources Section' => 22,
            'List Posts Section' => 23,
            'List FAQs Section' => 25,
        ];
    }

    function meza_sync_section_field_group_menu_order(): void
    {
        if (!function_exists('acf_get_field_groups') || !function_exists('acf_update_field_group')) {
            return;
        }

        $version = meza_get_section_field_group_menu_order_sync_version();
        if ((string) get_option(meza_get_section_field_group_menu_order_sync_option_name(), '') === $version) {
            return;
        }

        $menu_order_by_title = meza_get_section_field_group_menu_order_map();
        $delete_titles = [
            'List Sign Types Section',
        ];
        $did_update = false;

        foreach ((array) acf_get_field_groups() as $field_group) {
            if (!is_array($field_group)) {
                continue;
            }

            $title = trim((string) ($field_group['title'] ?? ''));
            $field_group_id = (int) ($field_group['ID'] ?? 0);

            if ($title === '' || $field_group_id <= 0) {
                continue;
            }

            if (in_array($title, $delete_titles, true) && function_exists('acf_delete_field_group')) {
                acf_delete_field_group($field_group_id);
                $did_update = true;
                continue;
            }

            if (!array_key_exists($title, $menu_order_by_title)) {
                continue;
            }

            $target_menu_order = (int) $menu_order_by_title[$title];
            $current_menu_order = (int) ($field_group['menu_order'] ?? 0);

            if ($current_menu_order === $target_menu_order) {
                continue;
            }

            $full_group = function_exists('acf_get_field_group')
                ? acf_get_field_group($field_group_id)
                : $field_group;
            if (!is_array($full_group)) {
                continue;
            }

            $full_group['menu_order'] = $target_menu_order;
            acf_update_field_group($full_group);
            $did_update = true;
        }

        if ($did_update || (string) get_option(meza_get_section_field_group_menu_order_sync_option_name(), '') !== $version) {
            update_option(meza_get_section_field_group_menu_order_sync_option_name(), $version, false);
        }
    }
}
add_action('acf/init', 'meza_sync_section_field_group_menu_order', 23);

add_action('acf/init', 'meza_seed_default_organization_type_terms', 25);
add_action('acf/update_post_type', 'meza_attach_locality_to_new_custom_acf_post_type', 20);
add_action('init', 'meza_reset_legacy_section_text_defaults_once', 30);

if (!function_exists('meza_refresh_seeded_acf_field_groups_after_business_information_save')) {
    function meza_refresh_seeded_acf_field_groups_after_business_information_save($post_id): void
    {
        if (
            !meza_should_seed_default_acf_field_groups()
            || !meza_is_business_information_acf_submission()
            || !in_array($post_id, ['option', 'options'], true)
        ) {
            return;
        }

        meza_seed_default_editable_acf_field_groups();
    }
}
add_action('acf/save_post', 'meza_refresh_seeded_acf_field_groups_after_business_information_save', 20);

if (!function_exists('meza_sync_ecommerce_after_business_information_save')) {
    function meza_persist_business_information_ecommerce_setting(): void
    {
        update_option('options_ecommerce', meza_business_information_enables_ecommerce() ? '1' : '0', false);
    }

    function meza_remove_ecommerce_default_editable_acf_field_groups(): void
    {
        foreach (meza_get_ecommerce_editable_acf_field_group_definitions() as $definition) {
            if (!is_array($definition) || empty($definition['key']) || empty($definition['title'])) {
                continue;
            }

            meza_delete_editable_acf_field_group_definition($definition);
        }
    }

    function meza_sync_ecommerce_defaults(): void
    {
        if (meza_business_information_enables_ecommerce()) {
            if (function_exists('mz_plugins_ensure_catalog_plugin_active')) {
                mz_plugins_ensure_catalog_plugin_active('woocommerce/woocommerce.php');
            }

            if (function_exists('meza_draft_all_woocommerce_core_pages')) {
                meza_draft_all_woocommerce_core_pages();
            }

            return;
        }

        meza_remove_ecommerce_default_editable_acf_field_groups();

        if (function_exists('mz_plugins_deactivate_plugin')) {
            mz_plugins_deactivate_plugin('woocommerce/woocommerce.php');
        }

        if (function_exists('mz_plugins_queue_plugin_package_removal')) {
            mz_plugins_queue_plugin_package_removal('woocommerce/woocommerce.php');
        }
    }

    function meza_sync_ecommerce_after_business_information_save($post_id): void
    {
        if (
            !meza_is_business_information_acf_submission()
            || !in_array($post_id, ['option', 'options'], true)
        ) {
            return;
        }

        meza_persist_business_information_ecommerce_setting();
        meza_sync_ecommerce_defaults();
    }
}
add_action('acf/save_post', 'meza_sync_ecommerce_after_business_information_save', 25);

add_action('admin_init', function (): void {
    static $did_sync = false;

    if (
        $did_sync
        || !is_admin()
        || !function_exists('mz_plugins_should_rerun')
        || !mz_plugins_should_rerun()
    ) {
        return;
    }

    $did_sync = true;
    meza_sync_ecommerce_defaults();
}, 40);

add_action('acf/include_admin_tools', function (): void {
    if (!class_exists('ACF_Admin_Tool_Export') || !class_exists('ACF_Admin_Tool')) {
        return;
    }

    if (!class_exists('Meza_ACF_Admin_Tool_Export')) {
        class Meza_ACF_Admin_Tool_Export extends ACF_Admin_Tool_Export
        {
            public function load()
            {
                if (!meza_current_user_can_see_hardcoded_acf_export_items()) {
                    parent::load();
                    return;
                }

                meza_with_acf_export_local_enabled(static function (): void {
                    meza_register_acf_export_tool_local_definitions();
                });

                parent::load();
            }

            public function get_selected()
            {
                if (!meza_current_user_can_see_hardcoded_acf_export_items()) {
                    return parent::get_selected();
                }

                return meza_with_acf_export_local_enabled(function () {
                    meza_register_acf_export_tool_local_definitions();
                    return parent::get_selected();
                });
            }

            public function html_field_selection()
            {
                if (!meza_current_user_can_see_hardcoded_acf_export_items()) {
                    parent::html_field_selection();
                    return;
                }

                meza_with_acf_export_local_enabled(function (): void {
                    meza_register_acf_export_tool_local_definitions();

                    acf_update_setting('l10n_var_export', false);
                    $store = acf_get_store('field-groups');
                    if ($store) {
                        $store->reset();
                    }

                    $sections = [
                        [
                            'post_type' => 'acf-field-group',
                            'label' => __('Select Field Groups', 'acf'),
                            'name' => 'keys',
                        ],
                        [
                            'post_type' => 'acf-post-type',
                            'label' => __('Select Post Types', 'acf'),
                            'name' => 'post_type_keys',
                        ],
                        [
                            'post_type' => 'acf-taxonomy',
                            'label' => __('Select Taxonomies', 'acf'),
                            'name' => 'taxonomy_keys',
                        ],
                        [
                            'post_type' => 'acf-ui-options-page',
                            'label' => __('Select Options Pages', 'acf'),
                            'name' => 'ui_options_page_keys',
                        ],
                    ];

                    $selected = $this->get_selected_keys();

                    foreach ($sections as $section) {
                        $grouped_choices = meza_get_acf_export_tool_grouped_choice_maps($section['post_type']);
                        $has_choices = false;

                        foreach ($grouped_choices as $choices) {
                            if (!empty($choices)) {
                                $has_choices = true;
                                break;
                            }
                        }

                        if (!$has_choices) {
                            continue;
                        }

                        echo '<div class="acf-field">';
                        echo '<div class="acf-label"><label>' . esc_html($section['label']) . '</label></div>';
                        echo '<div class="acf-input">';

                        foreach (
                            [
                                'custom' => 'Custom',
                                'defaults' => 'Defaults',
                                'built_in' => 'Built-In',
                            ] as $group_key => $group_label
                        ) {
                            if (empty($grouped_choices[$group_key])) {
                                continue;
                            }

                            acf_render_field_wrap(
                                [
                                    'label' => $group_label,
                                    'type' => 'checkbox',
                                    'name' => $section['name'],
                                    'prefix' => false,
                                    'value' => $selected,
                                    'toggle' => true,
                                    'choices' => $grouped_choices[$group_key],
                                ]
                            );
                        }

                        echo '</div>';
                        echo '</div>';
                    }
                });
            }
        }
    }

    if (isset(acf()->admin_tools) && is_object(acf()->admin_tools)) {
        acf()->admin_tools->tools['export'] = new Meza_ACF_Admin_Tool_Export();
    }
}, 20);

add_action('admin_head', function (): void {
    if (!meza_is_acf_export_tools_screen()) {
        return;
    }
?>
    <style id="meza-acf-export-tool-group-spacing">
        #acf-admin-tools .acf-meta-box-wrap .acf-fields .acf-field .acf-field:first-child {
            margin-top: 0;
        }

        #acf-admin-tools .acf-meta-box-wrap .acf-fields .acf-input {
            padding-bottom: 8px;
        }

        .acf-admin-page .acf-radio-list.acf-bl li:last-of-type,
        .acf-admin-page .acf-checkbox-list.acf-bl li:last-of-type {
            margin-bottom: 8px;
        }
    </style>
<?php
}, 20);
