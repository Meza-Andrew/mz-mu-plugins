<?php

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

    foreach (meza_get_builtin_acf_field_group_definitions() as $group) {
        if (
            !is_array($group)
            || empty($group['key'])
            || (
                function_exists('meza_is_content_model_built_in_field_group_enabled')
                && !meza_is_content_model_built_in_field_group_enabled($group)
            )
            || meza_is_managed_acf_definition_manually_deleted('field_groups', $group)
        ) {
            continue;
        }

        if (function_exists('meza_apply_db_acf_field_group_overrides_to_local_group')) {
            $group = meza_apply_db_acf_field_group_overrides_to_local_group($group);
        }

        acf_add_local_field_group($group);
    }
}, 15);

add_action('acf/init', 'meza_seed_default_editable_acf_taxonomies', 18);
add_action('acf/init', 'meza_sync_default_editable_acf_field_group_order_and_names', 19);
add_action('acf/init', 'meza_repair_empty_default_editable_acf_field_groups', 19);
add_action('acf/init', 'meza_seed_default_editable_acf_field_groups', 20);

if (!function_exists('meza_remove_legacy_business_information_toggle_field_groups')) {
    function meza_get_legacy_business_information_toggle_field_group_cleanup_version(): string
    {
        return '2026-05-19-business-information-toggle-groups-v1';
    }

    function meza_get_legacy_business_information_toggle_field_group_cleanup_option_name(): string
    {
        return 'meza_legacy_business_information_toggle_field_group_cleanup_version';
    }

    function meza_legacy_business_information_toggle_field_group_locations_match(array $field_group): bool
    {
        foreach ((array) ($field_group['location'] ?? []) as $location_group) {
            foreach ((array) $location_group as $rule) {
                if (!is_array($rule)) {
                    continue;
                }

                if (
                    (string) ($rule['param'] ?? '') === 'options_page'
                    && (string) ($rule['operator'] ?? '') === '=='
                    && (string) ($rule['value'] ?? '') === 'business-information'
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    function meza_remove_legacy_business_information_toggle_field_groups(): void
    {
        if (!function_exists('acf_get_field_groups') || !function_exists('acf_delete_field_group')) {
            return;
        }

        $version = meza_get_legacy_business_information_toggle_field_group_cleanup_version();
        if ((string) get_option(meza_get_legacy_business_information_toggle_field_group_cleanup_option_name(), '') === $version) {
            return;
        }

        $target_keys = [
            'group_6a0947aec1746',
            'group_6a09483cc7a92',
        ];
        $target_titles = [
            'Services',
            'Localities',
        ];

        $did_update = false;

        foreach ((array) acf_get_field_groups() as $field_group) {
            if (!is_array($field_group)) {
                continue;
            }

            $field_group_id = (int) ($field_group['ID'] ?? 0);
            if ($field_group_id <= 0 || !meza_legacy_business_information_toggle_field_group_locations_match($field_group)) {
                continue;
            }

            $field_group_key = (string) ($field_group['key'] ?? '');
            $field_group_title = trim((string) ($field_group['title'] ?? ''));

            if (
                !in_array($field_group_key, $target_keys, true)
                && !in_array($field_group_title, $target_titles, true)
            ) {
                continue;
            }

            acf_delete_field_group($field_group_id);
            $did_update = true;
        }

        if ($did_update || (string) get_option(meza_get_legacy_business_information_toggle_field_group_cleanup_option_name(), '') !== $version) {
            update_option(meza_get_legacy_business_information_toggle_field_group_cleanup_option_name(), $version, false);
        }
    }
}
add_action('acf/init', 'meza_remove_legacy_business_information_toggle_field_groups', 21);

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
        return '2026-05-21-list-reviews-location-v2';
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

if (!function_exists('meza_get_ecommerce_field_group_definition_by_title')) {
    function meza_get_ecommerce_field_group_definition_by_title(string $title): array
    {
        $title = trim($title);
        if ($title === '') {
            return [];
        }

        foreach (meza_get_ecommerce_editable_acf_field_group_definitions() as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            if (trim((string) ($definition['title'] ?? '')) !== $title) {
                continue;
            }

            return $definition;
        }

        return [];
    }
}

if (!function_exists('meza_sync_list_products_section_field_group_locations')) {
    function meza_get_list_products_section_field_group_location_sync_version(): string
    {
        return '2026-05-21-list-products-location-v2';
    }

    function meza_get_list_products_section_field_group_location_sync_option_name(): string
    {
        return 'meza_list_products_section_field_group_location_sync_version';
    }

    function meza_sync_list_products_section_field_group_locations(): void
    {
        if (!function_exists('acf_get_field_group') || !function_exists('acf_update_field_group')) {
            return;
        }

        $version = meza_get_list_products_section_field_group_location_sync_version();
        if ((string) get_option(meza_get_list_products_section_field_group_location_sync_option_name(), '') === $version) {
            return;
        }

        $definition = meza_get_ecommerce_field_group_definition_by_title('List Products Section');
        if ($definition === []) {
            update_option(meza_get_list_products_section_field_group_location_sync_option_name(), $version, false);
            return;
        }

        $field_group_id = meza_get_existing_editable_acf_field_group_id($definition);
        if ($field_group_id <= 0) {
            update_option(meza_get_list_products_section_field_group_location_sync_option_name(), $version, false);
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
        update_option(meza_get_list_products_section_field_group_location_sync_option_name(), $version, false);
    }
}
add_action('acf/init', 'meza_sync_list_products_section_field_group_locations', 22);

if (!function_exists('meza_sync_list_profiles_section_field_group_fields')) {
    function meza_get_list_profiles_section_field_group_fields_sync_version(): string
    {
        return '2026-05-21-list-profiles-fields-v2';
    }

    function meza_get_list_profiles_section_field_group_fields_sync_option_name(): string
    {
        return 'meza_list_profiles_section_field_group_fields_sync_version';
    }

    function meza_sync_list_profiles_section_field_group_fields(): void
    {
        if (!function_exists('meza_sync_builtin_field_group_fields') || !function_exists('meza_get_default_editable_acf_field_group_definitions')) {
            return;
        }

        $version = meza_get_list_profiles_section_field_group_fields_sync_version();
        if ((string) get_option(meza_get_list_profiles_section_field_group_fields_sync_option_name(), '') === $version) {
            return;
        }

        $definition = [];

        foreach ((array) meza_get_default_editable_acf_field_group_definitions() as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            if ((string) ($candidate['key'] ?? '') !== 'group_meza_list_profiles_section') {
                continue;
            }

            $definition = $candidate;
            break;
        }

        if ($definition === []) {
            update_option(meza_get_list_profiles_section_field_group_fields_sync_option_name(), $version, false);
            return;
        }

        meza_sync_builtin_field_group_fields($definition);
        update_option(meza_get_list_profiles_section_field_group_fields_sync_option_name(), $version, false);
    }
}
add_action('acf/init', 'meza_sync_list_profiles_section_field_group_fields', 24);

if (!function_exists('meza_cleanup_duplicate_list_profiles_group_fields')) {
    function meza_get_duplicate_list_profiles_group_fields_cleanup_version(): string
    {
        return '2026-05-22-list-profiles-duplicate-group-fields-v1';
    }

    function meza_get_duplicate_list_profiles_group_fields_cleanup_option_name(): string
    {
        return 'meza_duplicate_list_profiles_group_fields_cleanup_version';
    }

    function meza_delete_acf_field_tree(int $field_id): void
    {
        if ($field_id <= 0 || !function_exists('acf_delete_field')) {
            return;
        }

        $child_fields = get_posts([
            'post_type' => 'acf-field',
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => -1,
            'orderby' => 'menu_order ID',
            'order' => 'ASC',
            'post_parent' => $field_id,
            'suppress_filters' => false,
            'cache_results' => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        foreach ($child_fields as $child_field_id) {
            meza_delete_acf_field_tree((int) $child_field_id);
        }

        acf_delete_field($field_id);
    }

    function meza_cleanup_duplicate_list_profiles_group_fields(): void
    {
        if (!function_exists('acf_get_field_group') || !function_exists('acf_flush_field_cache')) {
            return;
        }

        $version = meza_get_duplicate_list_profiles_group_fields_cleanup_version();
        if ((string) get_option(meza_get_duplicate_list_profiles_group_fields_cleanup_option_name(), '') === $version) {
            return;
        }

        $field_group = acf_get_field_group('group_meza_list_profiles_section');
        $field_group_id = is_array($field_group) ? (int) ($field_group['ID'] ?? 0) : 0;
        if ($field_group_id <= 0) {
            update_option(meza_get_duplicate_list_profiles_group_fields_cleanup_option_name(), $version, false);
            return;
        }

        $canonical_field_ids = get_posts([
            'post_type' => 'acf-field',
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'post_parent' => $field_group_id,
            'name' => 'field_meza_section_list_profiles',
            'suppress_filters' => false,
            'cache_results' => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        $canonical_field_id = !empty($canonical_field_ids) ? (int) end($canonical_field_ids) : 0;
        if ($canonical_field_id <= 0) {
            update_option(meza_get_duplicate_list_profiles_group_fields_cleanup_option_name(), $version, false);
            return;
        }

        $duplicate_field_ids = get_posts([
            'post_type' => 'acf-field',
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'name' => 'field_meza_section_list_profiles',
            'suppress_filters' => false,
            'cache_results' => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        foreach ($duplicate_field_ids as $duplicate_field_id) {
            $duplicate_field_id = (int) $duplicate_field_id;
            if ($duplicate_field_id <= 0 || $duplicate_field_id === $canonical_field_id) {
                continue;
            }

            meza_delete_acf_field_tree($duplicate_field_id);
        }

        $canonical_field = acf_get_raw_field($canonical_field_id);
        if (is_array($canonical_field)) {
            acf_flush_field_cache($canonical_field);
        }

        update_option(meza_get_duplicate_list_profiles_group_fields_cleanup_option_name(), $version, false);
    }
}
add_action('acf/init', 'meza_cleanup_duplicate_list_profiles_group_fields', 23);

if (!function_exists('meza_sync_list_profiles_section_secondary_link_subfield_name')) {
    function meza_get_list_profiles_section_secondary_link_subfield_name_sync_version(): string
    {
        return '2026-05-22-list-profiles-secondary-link-subfield-name-v4';
    }

    function meza_get_list_profiles_section_secondary_link_subfield_name_sync_option_name(): string
    {
        return 'meza_list_profiles_section_secondary_link_subfield_name_sync_version';
    }

    function meza_get_list_profiles_section_group_field_definition(): array
    {
        if (!function_exists('meza_get_default_editable_acf_field_group_definitions')) {
            return [];
        }

        foreach ((array) meza_get_default_editable_acf_field_group_definitions() as $definition) {
            if (!is_array($definition) || (string) ($definition['key'] ?? '') !== 'group_meza_list_profiles_section') {
                continue;
            }

            foreach ((array) ($definition['fields'] ?? []) as $field_definition) {
                if (!is_array($field_definition) || (string) ($field_definition['key'] ?? '') !== 'field_meza_section_list_profiles') {
                    continue;
                }

                return $field_definition;
            }
        }

        return [];
    }

    function meza_sync_list_profiles_section_secondary_link_subfield_name(): void
    {
        if (!function_exists('acf_get_field') || !function_exists('acf_update_field')) {
            return;
        }

        $version = meza_get_list_profiles_section_secondary_link_subfield_name_sync_version();
        if ((string) get_option(meza_get_list_profiles_section_secondary_link_subfield_name_sync_option_name(), '') === $version) {
            return;
        }

        $group_field_definition = meza_get_list_profiles_section_group_field_definition();
        $group_field = acf_get_field('field_meza_section_list_profiles');

        if (is_array($group_field) && $group_field_definition !== []) {
            $group_field_definition['ID'] = $group_field['ID'] ?? null;
            $group_field_definition['parent'] = $group_field['parent'] ?? ($group_field_definition['parent'] ?? '');
            $group_field_definition['menu_order'] = (int) ($group_field['menu_order'] ?? $group_field_definition['menu_order'] ?? 0);
            if (function_exists('meza_preserve_acf_field_tree_identifiers')) {
                $group_field_definition = meza_preserve_acf_field_tree_identifiers(
                    $group_field_definition,
                    $group_field
                );
            }

            acf_update_field($group_field_definition);
        }

        $sub_field = acf_get_field('field_meza_list_profiles_link_secondary');
        if (!is_array($sub_field)) {
            update_option(meza_get_list_profiles_section_secondary_link_subfield_name_sync_option_name(), $version, false);
            return;
        }

        if ((string) ($sub_field['name'] ?? '') !== 'link_secondary') {
            $sub_field['name'] = 'link_secondary';
            acf_update_field($sub_field);
        }

        update_option(meza_get_list_profiles_section_secondary_link_subfield_name_sync_option_name(), $version, false);
    }
}
add_action('acf/init', 'meza_sync_list_profiles_section_secondary_link_subfield_name', 24);

if (!function_exists('meza_get_hidden_default_section_location_titles')) {
    function meza_get_hidden_default_section_location_titles(): array
    {
        return [
            'Benefits Section',
            'Content Section',
            'Gallery Section',
            'List Brands Section',
            'List Certifications Section',
            'List Donation Options Section',
            'List Donor Levels Section',
            'List Donors & Sponsors Section',
            'List Donors and Sponsors Section',
            'List Events Section',
            'List Locations Section',
            'List Partners Section',
            'List Products Section',
            'List Profiles Section',
            'List Sponsor Levels Section',
            'List Sponsors Section',
        ];
    }
}

if (!function_exists('meza_get_hidden_default_section_location_definitions')) {
    function meza_get_hidden_default_section_location_definitions(): array
    {
        $target_titles = meza_get_hidden_default_section_location_titles();
        $definitions_by_title = [];

        $sources = [];

        if (function_exists('meza_get_shared_project_acf_field_groups')) {
            $sources[] = meza_get_shared_project_acf_field_groups();
        }

        if (function_exists('meza_get_default_editable_acf_field_group_definitions')) {
            $sources[] = meza_get_default_editable_acf_field_group_definitions();
        }

        if (function_exists('meza_get_ecommerce_editable_acf_field_group_definitions')) {
            $sources[] = meza_get_ecommerce_editable_acf_field_group_definitions();
        }

        foreach ($sources as $definitions) {
            foreach ((array) $definitions as $definition) {
                if (!is_array($definition)) {
                    continue;
                }

                $title = trim((string) ($definition['title'] ?? ''));
                if ($title === '' || !in_array($title, $target_titles, true)) {
                    continue;
                }

                $definitions_by_title[$title] = $definition;
            }
        }

        return $definitions_by_title;
    }
}

if (!function_exists('meza_sync_hidden_default_section_field_group_locations')) {
    function meza_get_hidden_default_section_field_group_location_sync_version(): string
    {
        return '2026-05-21-hidden-default-section-locations-v1';
    }

    function meza_get_hidden_default_section_field_group_location_sync_option_name(): string
    {
        return 'meza_hidden_default_section_field_group_location_sync_version';
    }

    function meza_sync_hidden_default_section_field_group_locations(): void
    {
        if (!function_exists('acf_get_field_group') || !function_exists('acf_update_field_group')) {
            return;
        }

        $version = meza_get_hidden_default_section_field_group_location_sync_version();
        if ((string) get_option(meza_get_hidden_default_section_field_group_location_sync_option_name(), '') === $version) {
            return;
        }

        $definitions = meza_get_hidden_default_section_location_definitions();

        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $field_group_id = function_exists('meza_get_existing_editable_acf_field_group_id')
                ? meza_get_existing_editable_acf_field_group_id($definition)
                : 0;

            if ($field_group_id <= 0) {
                continue;
            }

            $field_group = acf_get_field_group($field_group_id);
            if (!is_array($field_group)) {
                continue;
            }

            $field_group['location'] = $definition['location'] ?? [];
            $field_group['menu_order'] = (int) ($definition['menu_order'] ?? 0);
            $field_group['title'] = (string) ($definition['title'] ?? ($field_group['title'] ?? ''));

            acf_update_field_group($field_group);
        }

        update_option(meza_get_hidden_default_section_field_group_location_sync_option_name(), $version, false);
    }
}
add_action('acf/init', 'meza_sync_hidden_default_section_field_group_locations', 23);

if (!function_exists('meza_remove_cta_section_id_field')) {
    function meza_get_remove_cta_section_id_field_sync_version(): string
    {
        return '2026-05-02-remove-cta-section-id-v1';
    }

    function meza_get_remove_cta_section_id_field_sync_option_name(): string
    {
        return 'meza_remove_cta_section_id_field_sync_version';
    }

    function meza_remove_cta_section_id_field(): void
    {
        if (!function_exists('acf_get_field_group') || !function_exists('acf_get_fields') || !function_exists('acf_delete_field')) {
            return;
        }

        $version = meza_get_remove_cta_section_id_field_sync_version();
        if ((string) get_option(meza_get_remove_cta_section_id_field_sync_option_name(), '') === $version) {
            return;
        }

        $definition = meza_get_cta_section_field_group_definition();
        $field_group_id = meza_get_existing_editable_acf_field_group_id($definition);
        if ($field_group_id <= 0) {
            update_option(meza_get_remove_cta_section_id_field_sync_option_name(), $version, false);
            return;
        }

        foreach ((array) acf_get_fields($field_group_id) as $field) {
            if (!is_array($field)) {
                continue;
            }

            if ((string) ($field['name'] ?? '') !== 'id') {
                continue;
            }

            $field_id = (int) ($field['ID'] ?? 0);
            if ($field_id > 0) {
                acf_delete_field($field_id);
            }
        }

        update_option(meza_get_remove_cta_section_id_field_sync_option_name(), $version, false);
    }
}
add_action('acf/init', 'meza_remove_cta_section_id_field', 22);

if (!function_exists('meza_sync_section_show_field_messages_blank')) {
    function meza_get_section_show_field_messages_blank_sync_version(): string
    {
        return '2026-05-02-section-show-message-blank-v1';
    }

    function meza_get_section_show_field_messages_blank_sync_option_name(): string
    {
        return 'meza_section_show_field_messages_blank_sync_version';
    }

    function meza_sync_section_show_field_messages_blank(): void
    {
        if (!function_exists('acf_get_raw_field_groups') || !function_exists('acf_get_fields') || !function_exists('acf_update_field')) {
            return;
        }

        $version = meza_get_section_show_field_messages_blank_sync_version();
        if ((string) get_option(meza_get_section_show_field_messages_blank_sync_option_name(), '') === $version) {
            return;
        }

        foreach ((array) acf_get_raw_field_groups() as $field_group) {
            if (!is_array($field_group)) {
                continue;
            }

            $title = (string) ($field_group['title'] ?? '');
            if ($title === '' || strpos($title, 'Section') === false) {
                continue;
            }

            $field_group_id = (int) ($field_group['ID'] ?? 0);
            if ($field_group_id <= 0) {
                continue;
            }

            foreach ((array) acf_get_fields($field_group_id) as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $field_type = (string) ($field['type'] ?? '');
                $field_name = (string) ($field['name'] ?? '');
                if ($field_type !== 'true_false' || !str_starts_with($field_name, 'show_')) {
                    continue;
                }

                if ((string) ($field['message'] ?? '') === '') {
                    continue;
                }

                $field['message'] = '';
                acf_update_field($field);
            }
        }

        update_option(meza_get_section_show_field_messages_blank_sync_option_name(), $version, false);
    }
}
add_action('acf/init', 'meza_sync_section_show_field_messages_blank', 23);

if (!function_exists('meza_sync_section_group_sub_fields')) {
    function meza_get_section_group_sub_fields_sync_version(): string
    {
        $mode = 'default';

        if (
            function_exists('meza_events_have_after_content')
            && meza_events_have_after_content()
            && function_exists('meza_get_event_context_request_post_type')
            && meza_get_event_context_request_post_type() === 'event'
        ) {
            $mode = 'event';
        }

        return '2026-07-01-section-group-sub-fields-v9-' . $mode;
    }

    function meza_get_section_group_sub_fields_sync_option_name(): string
    {
        return 'meza_section_group_sub_fields_sync_version';
    }

    function meza_sync_section_group_sub_fields(): void
    {
        if (
            !function_exists('acf_get_field_groups')
            || !function_exists('acf_get_fields')
            || !function_exists('acf_update_field')
            || !function_exists('acf_delete_field')
        ) {
            return;
        }

        $version = meza_get_section_group_sub_fields_sync_version();
        if ((string) get_option(meza_get_section_group_sub_fields_sync_option_name(), '') === $version) {
            return;
        }

        $did_update = false;

        foreach ((array) acf_get_field_groups() as $field_group) {
            if (!is_array($field_group)) {
                continue;
            }

            $field_group_id = (int) ($field_group['ID'] ?? 0);
            if ($field_group_id <= 0) {
                continue;
            }

            $existing_fields = (array) acf_get_fields($field_group_id);
            if ($existing_fields === []) {
                continue;
            }

            $is_section_group = false;
            foreach ($existing_fields as $field) {
                if (
                    is_array($field)
                    && (string) ($field['type'] ?? '') === 'group'
                    && str_starts_with(sanitize_key((string) ($field['name'] ?? '')), 'section_')
                ) {
                    $is_section_group = true;
                    break;
                }
            }

            if (!$is_section_group) {
                continue;
            }

            $field_group_with_fields = $field_group;
            $field_group_with_fields['fields'] = $existing_fields;
            $normalized_field_group = meza_normalize_header_section_group($field_group_with_fields);
            $normalized_fields = isset($normalized_field_group['fields']) && is_array($normalized_field_group['fields'])
                ? array_values($normalized_field_group['fields'])
                : $existing_fields;

            $existing_fields_by_key = [];
            foreach ($existing_fields as $existing_field) {
                if (!is_array($existing_field)) {
                    continue;
                }

                $existing_key = (string) ($existing_field['key'] ?? '');
                if ($existing_key !== '') {
                    $existing_fields_by_key[$existing_key] = $existing_field;
                }
            }

            $normalized_keys = [];
            foreach ($normalized_fields as $menu_order => $normalized_field) {
                if (!is_array($normalized_field)) {
                    continue;
                }

                $normalized_key = (string) ($normalized_field['key'] ?? '');
                if ($normalized_key === '') {
                    continue;
                }

                $normalized_field['menu_order'] = $menu_order;
                $normalized_keys[$normalized_key] = true;
                $existing_field = $existing_fields_by_key[$normalized_key] ?? null;
                if (function_exists('meza_preserve_acf_field_tree_identifiers')) {
                    $normalized_field = meza_preserve_acf_field_tree_identifiers(
                        $normalized_field,
                        $existing_field,
                        (string) ($field_group['key'] ?? $field_group_id)
                    );
                }

                if (is_array($existing_field) && $existing_field === $normalized_field) {
                    continue;
                }

                acf_update_field($normalized_field);
                $did_update = true;
            }

            foreach ($existing_fields_by_key as $existing_key => $existing_field) {
                if (isset($normalized_keys[$existing_key])) {
                    continue;
                }

                $existing_field_id = (int) ($existing_field['ID'] ?? 0);
                if ($existing_field_id <= 0) {
                    continue;
                }

                acf_delete_field($existing_field_id);
                $did_update = true;
            }
        }

        if ($did_update || (string) get_option(meza_get_section_group_sub_fields_sync_option_name(), '') !== $version) {
            update_option(meza_get_section_group_sub_fields_sync_option_name(), $version, false);
        }
    }
}
add_action('acf/init', 'meza_sync_section_group_sub_fields', 24);

if (!function_exists('meza_sync_shared_project_field_group_fields')) {
    function meza_get_shared_project_field_group_fields_sync_version(): string
    {
        $mode = 'default';

        if (
            function_exists('meza_events_have_after_content')
            && meza_events_have_after_content()
            && function_exists('meza_get_event_context_request_post_type')
            && meza_get_event_context_request_post_type() === 'event'
        ) {
            $mode = 'event';
        }

        return '2026-07-01-shared-project-field-group-fields-v5-' . $mode;
    }

    function meza_get_shared_project_field_group_fields_sync_option_name(): string
    {
        return 'meza_shared_project_field_group_fields_sync_version';
    }

    function meza_sync_shared_project_field_group_fields(): void
    {
        if (!function_exists('meza_get_shared_project_acf_field_groups')) {
            return;
        }

        $version = meza_get_shared_project_field_group_fields_sync_version();
        if ((string) get_option(meza_get_shared_project_field_group_fields_sync_option_name(), '') === $version) {
            return;
        }

        $did_update = false;

        foreach ((array) meza_get_shared_project_acf_field_groups() as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            if (meza_sync_builtin_field_group_fields($definition)) {
                $did_update = true;
            }
        }

        if ($did_update || (string) get_option(meza_get_shared_project_field_group_fields_sync_option_name(), '') !== $version) {
            update_option(meza_get_shared_project_field_group_fields_sync_option_name(), $version, false);
        }
    }
}
add_action('acf/init', 'meza_sync_shared_project_field_group_fields', 24);

if (!function_exists('meza_get_shared_project_acf_field_group_definition_by_key')) {
    function meza_get_shared_project_acf_field_group_definition_by_key(string $group_key): array
    {
        $group_key = (string) $group_key;
        if ($group_key === '') {
            return [];
        }

        foreach (meza_get_shared_project_acf_field_groups() as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            if ((string) ($definition['key'] ?? '') === $group_key) {
                return $definition;
            }
        }

        return [];
    }
}

if (!function_exists('meza_sync_builtin_field_group_fields')) {
    function meza_sync_builtin_field_group_fields(array $definition): bool
    {
        if (
            !function_exists('acf_get_field_group')
            || !function_exists('acf_get_fields')
            || !function_exists('acf_update_field')
            || !function_exists('acf_delete_field')
        ) {
            return false;
        }

        $field_group_id = meza_get_existing_editable_acf_field_group_id($definition);
        if ($field_group_id <= 0) {
            return false;
        }

        $field_group = acf_get_field_group($field_group_id);
        if (!is_array($field_group)) {
            return false;
        }

        $expected_fields = isset($definition['fields']) && is_array($definition['fields'])
            ? array_values($definition['fields'])
            : [];
        $existing_fields = (array) acf_get_fields($field_group_id);

        $existing_fields_by_key = [];
        foreach ($existing_fields as $existing_field) {
            if (!is_array($existing_field)) {
                continue;
            }

            $existing_key = (string) ($existing_field['key'] ?? '');
            if ($existing_key !== '') {
                $existing_fields_by_key[$existing_key] = $existing_field;
            }
        }

        $did_update = false;
        $expected_keys = [];

        foreach ($expected_fields as $menu_order => $expected_field) {
            if (!is_array($expected_field)) {
                continue;
            }

            $expected_key = (string) ($expected_field['key'] ?? '');
            if ($expected_key === '') {
                continue;
            }

            $expected_field['parent'] = $field_group['key'] ?? $definition['key'] ?? '';
            $expected_field['menu_order'] = (int) $menu_order;
            $expected_keys[$expected_key] = true;

            $existing_field = $existing_fields_by_key[$expected_key] ?? null;
            if (function_exists('meza_preserve_acf_field_tree_identifiers')) {
                $expected_field = meza_preserve_acf_field_tree_identifiers(
                    $expected_field,
                    $existing_field,
                    (string) ($field_group['key'] ?? $definition['key'] ?? '')
                );
            }

            if (is_array($existing_field) && $existing_field === $expected_field) {
                continue;
            }

            acf_update_field($expected_field);
            $did_update = true;
        }

        foreach ($existing_fields_by_key as $existing_key => $existing_field) {
            if (isset($expected_keys[$existing_key])) {
                continue;
            }

            $existing_field_id = (int) ($existing_field['ID'] ?? 0);
            if ($existing_field_id <= 0) {
                continue;
            }

            acf_delete_field($existing_field_id);
            $did_update = true;
        }

        return $did_update;
    }
}

if (!function_exists('meza_sync_events_options_group_fields')) {
    function meza_get_events_options_group_fields_sync_version(): string
    {
        return '2026-05-17-events-options-fields-v1';
    }

    function meza_get_events_options_group_fields_sync_option_name(): string
    {
        return 'meza_events_options_group_fields_sync_version';
    }

    function meza_sync_events_options_group_fields(): void
    {
        $version = meza_get_events_options_group_fields_sync_version();
        if ((string) get_option(meza_get_events_options_group_fields_sync_option_name(), '') === $version) {
            return;
        }

        $definition = meza_get_shared_project_acf_field_group_definition_by_key('group_meza_site_events');
        if ($definition === []) {
            update_option(meza_get_events_options_group_fields_sync_option_name(), $version, false);
            return;
        }

        meza_sync_builtin_field_group_fields($definition);
        update_option(meza_get_events_options_group_fields_sync_option_name(), $version, false);
    }
}
add_action('acf/init', 'meza_sync_events_options_group_fields', 24);

if (!function_exists('meza_register_missing_events_options_local_field')) {
    function meza_register_missing_events_options_local_field(): void
    {
        if (
            !function_exists('acf_add_local_field')
            || !function_exists('acf_get_local_fields')
            || !function_exists('meza_get_events_fields')
        ) {
            return;
        }

        $existing_fields = (array) acf_get_local_fields('group_meza_site_events');
        foreach ($existing_fields as $existing_field) {
            if (
                is_array($existing_field)
                && (string) ($existing_field['key'] ?? '') === 'field_meza_event_after_content'
            ) {
                return;
            }
        }

        foreach (meza_get_events_fields() as $menu_order => $field) {
            if (
                !is_array($field)
                || (string) ($field['key'] ?? '') !== 'field_meza_event_after_content'
            ) {
                continue;
            }

            $field['parent'] = 'group_meza_site_events';
            $field['menu_order'] = (int) $menu_order;
            acf_add_local_field($field);
            return;
        }
    }
}
add_action('acf/init', 'meza_register_missing_events_options_local_field', 16);

if (!function_exists('meza_sync_section_field_group_menu_order')) {
    function meza_get_section_field_group_menu_order_sync_version(): string
    {
        return '2026-06-29-section-field-group-menu-order-v5';
    }

    function meza_get_section_field_group_menu_order_sync_option_name(): string
    {
        return 'meza_section_field_group_menu_order_sync_version';
    }

    function meza_get_section_field_group_menu_order_map(): array
    {
        return [
            'Hero Section' => 1,
            'List Services Section' => 3,
            'List Products Section' => 4,
            'List Segments Section' => 5,
            'List Reviews Section' => 6,
            'Benefits Section' => 8,
            'List Donor Levels Section' => 10,
            'List Sponsor Levels Section' => 11,
            'List Donation Options Section' => 12,
            'Gallery Section' => 14,
            'List Profiles Section' => 15,
            'List Certifications Section' => 17,
            'List Partners Section' => 18,
            'List Sponsors Section' => 19,
            'List Donors & Sponsors Section' => 20,
            'List Donors and Sponsors Section' => 20,
            'List Brands Section' => 21,
            'Content Section' => 23,
            'List Events Section' => 25,
            'List Localities Section' => 26,
            'Form Section' => 28,
            'CTA Section' => 29,
            'List Locations Section' => 31,
            'List Posts Section' => 33,
            'List Resources Section' => 34,
            'List FAQs Section' => 35,
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
add_action('init', 'meza_migrate_legacy_list_testimonials_meta_once', 29);
add_action('init', 'meza_sync_list_reviews_reference_values_once', 30);
add_action('init', 'meza_reset_legacy_section_text_defaults_once', 31);
add_action('init', 'meza_migrate_certification_url_meta_once', 32);

if (!function_exists('meza_get_content_model_field_group_management_field_key_map')) {
    function meza_get_content_model_field_group_management_field_key_map(): array
    {
        return [
            'custom' => 'field_meza_content_model_custom_field_groups',
            'defaults' => 'field_meza_content_model_default_field_groups',
            'built_in' => 'field_meza_content_model_built_in_field_groups',
        ];
    }

    function meza_get_content_model_field_group_management_protected_keys(): array
    {
        return [
            'group_meza_content_model_field_groups',
            'group_69f04ade6c91c',
        ];
    }

    function meza_get_content_model_field_group_management_option_name(string $bucket): string
    {
        return match ($bucket) {
            'custom' => 'options_content_model_custom_field_groups',
            'defaults' => 'options_content_model_default_field_groups',
            'built_in' => 'options_content_model_built_in_field_groups',
            default => '',
        };
    }

    function meza_get_content_model_saved_option_value(string $option_name, ?bool &$exists = null)
    {
        $missing_sentinel = '__meza_content_model_option_missing__';
        $value = get_option($option_name, $missing_sentinel);

        if ($value === $missing_sentinel) {
            $exists = false;
            return null;
        }

        $exists = true;

        return $value;
    }

    function meza_normalize_content_model_field_group_management_title(string $title): string
    {
        $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        $title = wp_strip_all_tags($title);
        $title = preg_replace('/\s+/', ' ', $title);

        return trim((string) $title);
    }

    function meza_get_content_model_field_group_management_title_match_key(string $title): string
    {
        $title = meza_normalize_content_model_field_group_management_title($title);
        $title = preg_replace('/\s+\(copy(?:\s+\d+)?\)$/i', '', $title);

        return sanitize_title((string) $title);
    }

    function meza_get_content_model_available_option_page_slugs(): array
    {
        $slugs = [];

        if (function_exists('meza_get_content_model_object_management_active_option_page_definitions')) {
            foreach (meza_get_content_model_object_management_active_option_page_definitions() as $page) {
                if (!is_array($page)) {
                    continue;
                }

                $slug = sanitize_key((string) ($page['menu_slug'] ?? ''));
                if ($slug !== '') {
                    $slugs[$slug] = $slug;
                }
            }
        }

        return array_values($slugs);
    }

    function meza_get_content_model_available_post_type_identifiers(): array
    {
        $identifiers = [
            'attachment' => 'attachment',
            'page' => 'page',
            'post' => 'post',
            'product' => 'product',
        ];

        if (function_exists('meza_get_content_model_object_management_all_built_in_post_type_definitions')) {
            foreach (meza_get_content_model_object_management_all_built_in_post_type_definitions() as $definition) {
                if (!is_array($definition)) {
                    continue;
                }

                $identifier = sanitize_key((string) ($definition['post_type'] ?? ''));
                if ($identifier !== '') {
                    $identifiers[$identifier] = $identifier;
                }
            }
        }

        if (function_exists('meza_get_content_model_object_management_custom_records')) {
            foreach (meza_get_content_model_object_management_custom_records('post_types') as $identifier => $record) {
                unset($record);
                $identifier = sanitize_key((string) $identifier);
                if ($identifier !== '') {
                    $identifiers[$identifier] = $identifier;
                }
            }
        }

        return array_values($identifiers);
    }

    function meza_get_content_model_available_taxonomy_identifiers(): array
    {
        $identifiers = [
            'category' => 'category',
            'post_tag' => 'post_tag',
        ];

        if (function_exists('meza_get_content_model_object_management_all_built_in_taxonomy_definitions')) {
            foreach (meza_get_content_model_object_management_all_built_in_taxonomy_definitions() as $definition) {
                if (!is_array($definition)) {
                    continue;
                }

                $identifier = sanitize_key((string) ($definition['taxonomy'] ?? ''));
                if ($identifier !== '') {
                    $identifiers[$identifier] = $identifier;
                }
            }
        }

        if (function_exists('meza_get_default_editable_acf_taxonomy_definitions')) {
            foreach ((array) meza_get_default_editable_acf_taxonomy_definitions() as $definition) {
                if (!is_array($definition)) {
                    continue;
                }

                $identifier = sanitize_key((string) ($definition['taxonomy'] ?? ''));
                if ($identifier !== '') {
                    $identifiers[$identifier] = $identifier;
                }
            }
        }

        if (function_exists('meza_get_content_model_object_management_custom_records')) {
            foreach (meza_get_content_model_object_management_custom_records('taxonomies') as $identifier => $record) {
                unset($record);
                $identifier = sanitize_key((string) $identifier);
                if ($identifier !== '') {
                    $identifiers[$identifier] = $identifier;
                }
            }
        }

        return array_values($identifiers);
    }

    function meza_is_content_model_field_group_location_rule_available(array $rule): bool
    {
        $param = sanitize_key((string) ($rule['param'] ?? ''));
        $operator = (string) ($rule['operator'] ?? '==');
        $value = (string) ($rule['value'] ?? '');
        $is_not_equal = $operator === '!=';

        if ($param === '') {
            return true;
        }

        $check = true;

        switch ($param) {
            case 'options_page':
                $check = in_array(sanitize_key($value), meza_get_content_model_available_option_page_slugs(), true);
                break;
            case 'post_type':
                $check = in_array(sanitize_key($value), meza_get_content_model_available_post_type_identifiers(), true);
                break;
            case 'taxonomy':
                $taxonomy = sanitize_key((string) strtok($value, ':'));
                $check = in_array($taxonomy, meza_get_content_model_available_taxonomy_identifiers(), true);
                break;
            default:
                $check = true;
                break;
        }

        return $is_not_equal ? !$check : $check;
    }

    function meza_is_content_model_field_group_dependency_available(array $definition): bool
    {
        $title_match_key = meza_get_content_model_field_group_management_title_match_key((string) ($definition['title'] ?? ''));
        if ($title_match_key === '') {
            return true;
        }

        $required_post_types_by_title = [
            'list-certifications-section' => ['certification'],
            'list-donor-levels-section' => ['profile'],
            'list-donors-sponsors-section' => ['organization', 'profile'],
            'list-events-section' => ['event'],
            'list-segments-section' => ['segment'],
            'list-partners-section' => ['organization'],
            'list-products-section' => ['product'],
            'list-profiles-section' => ['profile'],
            'list-resources-section' => ['resource'],
            'list-sponsor-levels-section' => ['organization'],
            'list-sponsors-section' => ['organization'],
        ];

        $required_post_types = $required_post_types_by_title[$title_match_key] ?? [];
        if ($required_post_types === []) {
            return true;
        }

        $enabled_post_types = array_fill_keys(
            meza_get_content_model_object_management_current_enabled_identifiers('post_types'),
            true
        );

        foreach ($required_post_types as $identifier) {
            $identifier = sanitize_key((string) $identifier);
            if ($identifier === '' || !isset($enabled_post_types[$identifier])) {
                return false;
            }
        }

        return true;
    }

    function meza_is_content_model_field_group_definition_available(array $definition): bool
    {
        if (!meza_is_content_model_field_group_dependency_available($definition)) {
            return false;
        }

        $locations = (array) ($definition['location'] ?? []);
        if ($locations === []) {
            return true;
        }

        foreach ($locations as $group) {
            if (!is_array($group) || $group === []) {
                continue;
            }

            $group_matches = true;

            foreach ($group as $rule) {
                if (!is_array($rule) || !meza_is_content_model_field_group_location_rule_available($rule)) {
                    $group_matches = false;
                    break;
                }
            }

            if ($group_matches) {
                return true;
            }
        }

        return false;
    }

    function meza_get_content_model_field_group_management_definition_map(string $bucket): array
    {
        $definitions = [];

        if ($bucket === 'built_in') {
            $definitions = function_exists('meza_get_builtin_acf_field_group_definitions')
                ? meza_get_builtin_acf_field_group_definitions()
                : [];
        } elseif ($bucket === 'defaults') {
            $definitions = function_exists('meza_get_acf_export_default_editable_acf_field_group_definitions')
                ? meza_get_acf_export_default_editable_acf_field_group_definitions()
                : [];
        }

        $protected_keys = meza_get_content_model_field_group_management_protected_keys();
        $map = [];

        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $key = sanitize_key((string) ($definition['key'] ?? ''));
            $title = meza_normalize_content_model_field_group_management_title((string) ($definition['title'] ?? ''));
            if (
                $key === ''
                || $title === ''
                || in_array($key, $protected_keys, true)
                || !meza_is_content_model_field_group_definition_available($definition)
            ) {
                continue;
            }

            $definition['title'] = $title;
            $map[$key] = $definition;
        }

        uasort($map, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });

        return $map;
    }

    function meza_get_content_model_custom_field_group_records(): array
    {
        if (!function_exists('acf_get_raw_internal_post_type')) {
            return [];
        }

        $posts = get_posts([
            'posts_per_page' => -1,
            'post_type' => 'acf-field-group',
            'post_status' => ['publish', 'acf-disabled'],
            'orderby' => 'menu_order title',
            'order' => 'ASC',
            'suppress_filters' => false,
            'cache_results' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        $records = [];

        foreach ($posts as $post) {
            if (!($post instanceof WP_Post)) {
                continue;
            }

            $field_group = acf_get_raw_internal_post_type((int) $post->ID, 'acf-field-group');
            if (!is_array($field_group)) {
                continue;
            }

            $key = sanitize_key((string) ($field_group['key'] ?? ''));
            $title = meza_normalize_content_model_field_group_management_title((string) ($field_group['title'] ?? ''));
            if (
                $key === ''
                || $title === ''
                || !meza_is_content_model_field_group_definition_available($field_group)
            ) {
                continue;
            }

            $matched_field_group = function_exists('acf_get_field_group')
                ? acf_get_field_group((int) $post->ID)
                : $field_group;
            if (is_array($matched_field_group) && function_exists('meza_get_full_acf_field_group_for_matching')) {
                $matched_field_group = meza_get_full_acf_field_group_for_matching($matched_field_group);
            }

            if (function_exists('meza_get_acf_admin_definition_status_for_post')) {
                if (meza_get_acf_admin_definition_status_for_post('acf-field-group', (int) $post->ID) !== 'custom') {
                    continue;
                }
            } elseif (function_exists('meza_get_acf_export_tool_group_for_post')) {
                if (!is_array($matched_field_group) || meza_get_acf_export_tool_group_for_post('acf-field-group', $matched_field_group) !== 'custom') {
                    continue;
                }
            }

            $records[$key] = [
                'ID' => (int) $post->ID,
                'key' => $key,
                'title' => $title,
                'status' => (string) get_post_status($post),
            ];
        }

        uasort($records, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });

        return $records;
    }

    function meza_is_content_model_custom_field_group_duplicate_of_managed_definition(array $candidate, array $managed_definitions): bool
    {
        if (
            !function_exists('meza_get_acf_field_group_fields_signature')
            || !function_exists('meza_get_acf_field_group_fields_signature_aligned_to_definition')
        ) {
            return false;
        }

        $candidate_title = meza_normalize_content_model_field_group_management_title((string) ($candidate['title'] ?? ''));
        $candidate_title_key = meza_get_content_model_field_group_management_title_match_key($candidate_title);
        $candidate_location = function_exists('meza_normalize_acf_field_group_location')
            ? meza_normalize_acf_field_group_location((array) ($candidate['location'] ?? []))
            : [];
        $candidate_signature = meza_get_acf_field_group_fields_signature($candidate);

        foreach ($managed_definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $definition_title = meza_normalize_content_model_field_group_management_title((string) ($definition['title'] ?? ''));
            $definition_title_key = meza_get_content_model_field_group_management_title_match_key($definition_title);
            if ($candidate_title_key !== '' && $candidate_title_key === $definition_title_key) {
                return true;
            }

            $definition_location = function_exists('meza_normalize_acf_field_group_location')
                ? meza_normalize_acf_field_group_location((array) ($definition['location'] ?? []))
                : [];
            if (empty($candidate_location) || empty($definition_location) || $candidate_location !== $definition_location) {
                continue;
            }

            $definition_signature = meza_get_acf_field_group_fields_signature($definition);
            if ($candidate_signature !== '' && $definition_signature !== '' && $candidate_signature === $definition_signature) {
                return true;
            }

            $aligned_signature = meza_get_acf_field_group_fields_signature_aligned_to_definition($candidate, $definition);
            if ($aligned_signature !== '' && $definition_signature !== '' && $aligned_signature === $definition_signature) {
                return true;
            }
        }

        return false;
    }

    function meza_get_content_model_field_group_management_choice_map(string $bucket): array
    {
        if ($bucket === 'custom') {
            $choices = [];

            foreach (meza_get_content_model_custom_field_group_records() as $record) {
                $key = sanitize_key((string) ($record['key'] ?? ''));
                $title = meza_normalize_content_model_field_group_management_title((string) ($record['title'] ?? ''));
                if ($key === '' || $title === '') {
                    continue;
                }

                $choices[$key] = $title;
            }

            return $choices;
        }

        $choices = [];

        foreach (meza_get_content_model_field_group_management_definition_map($bucket) as $key => $definition) {
            $choices[$key] = (string) ($definition['title'] ?? $key);
        }

        return $choices;
    }

    function meza_normalize_content_model_field_group_management_selection($value, array $choices): array
    {
        $selected = [];

        foreach ((array) $value as $candidate) {
            $key = sanitize_key((string) $candidate);
            if ($key === '' || !array_key_exists($key, $choices)) {
                continue;
            }

            $selected[$key] = $key;
        }

        return array_values($selected);
    }

    function meza_get_content_model_field_group_management_posted_selection(string $bucket, array $choices): ?array
    {
        $field_key = meza_get_content_model_field_group_management_field_key_map()[$bucket] ?? '';
        if (
            $field_key === ''
            || !isset($_POST['acf'])
            || !is_array($_POST['acf'])
            || !array_key_exists($field_key, $_POST['acf'])
        ) {
            return null;
        }

        return meza_normalize_content_model_field_group_management_selection(
            wp_unslash($_POST['acf'][$field_key]),
            $choices
        );
    }

    function meza_get_content_model_field_group_management_current_selection(string $bucket, array $choices): array
    {
        $posted_selection = meza_get_content_model_field_group_management_posted_selection($bucket, $choices);
        if ($posted_selection !== null) {
            return $posted_selection;
        }

        if ($bucket === 'built_in') {
            $selected = [];

            foreach (meza_get_content_model_field_group_management_definition_map('built_in') as $key => $definition) {
                if (!array_key_exists($key, $choices)) {
                    continue;
                }

                if (meza_is_content_model_built_in_field_group_enabled($definition)) {
                    $selected[$key] = $key;
                }
            }

            return array_values($selected);
        }

        if (!in_array($bucket, ['custom', 'defaults'], true)) {
            $option_name = meza_get_content_model_field_group_management_option_name($bucket);
            if ($option_name !== '') {
                $saved_selection = meza_get_content_model_saved_option_value($option_name, $has_saved_selection);
                if ($has_saved_selection) {
                    return meza_normalize_content_model_field_group_management_selection($saved_selection, $choices);
                }
            }
        }

        $selected = [];

        if ($bucket === 'custom') {
            foreach (meza_get_content_model_custom_field_group_records() as $record) {
                $key = sanitize_key((string) ($record['key'] ?? ''));
                $status = (string) ($record['status'] ?? '');
                if ($key === '' || !array_key_exists($key, $choices) || in_array($status, ['trash', 'acf-disabled'], true)) {
                    continue;
                }

                $selected[$key] = $key;
            }

            return array_values($selected);
        }

        foreach (meza_get_content_model_field_group_management_definition_map($bucket) as $key => $definition) {
            if (!array_key_exists($key, $choices)) {
                continue;
            }

            if ($bucket === 'built_in') {
                if (meza_is_content_model_built_in_field_group_enabled($definition)) {
                    $selected[$key] = $key;
                }

                continue;
            }

            $status = function_exists('meza_get_raw_editable_acf_field_group')
                ? (string) get_post_status((int) (meza_get_raw_editable_acf_field_group($definition)['ID'] ?? 0))
                : '';

            if ($status === 'publish') {
                $selected[$key] = $key;
                continue;
            }

            if ($status === 'acf-disabled' || $status === 'trash') {
                continue;
            }
        }

        return array_values($selected);
    }

    function meza_set_content_model_field_group_management_saved_selection(string $bucket, array $selected_keys): void
    {
        $option_name = meza_get_content_model_field_group_management_option_name($bucket);
        $field_key = meza_get_content_model_field_group_management_field_key_map()[$bucket] ?? '';

        if ($option_name === '') {
            return;
        }

        if (in_array($bucket, ['built_in', 'defaults', 'custom'], true)) {
            delete_option($option_name);
            delete_option('_' . $option_name);
            return;
        }

        $normalized = meza_normalize_content_model_field_group_management_selection(
            $selected_keys,
            meza_get_content_model_field_group_management_choice_map($bucket)
        );

        update_option($option_name, $normalized, false);

        if ($field_key !== '') {
            update_option('_' . $option_name, $field_key, false);
        }
    }

    function meza_get_content_model_field_group_selection_cleanup_version(): string
    {
        return '2026-06-02-content-model-field-group-selections-v1';
    }

    function meza_get_content_model_field_group_selection_cleanup_option_name(): string
    {
        return 'meza_content_model_field_group_selection_cleanup_version';
    }

    function meza_cleanup_legacy_content_model_field_group_selection_options(): void
    {
        $version = meza_get_content_model_field_group_selection_cleanup_version();
        $option_name = meza_get_content_model_field_group_selection_cleanup_option_name();

        if ((string) get_option($option_name, '') === $version) {
            return;
        }

        foreach (['custom', 'defaults', 'built_in'] as $bucket) {
            $legacy_option_name = meza_get_content_model_field_group_management_option_name($bucket);
            if ($legacy_option_name === '') {
                continue;
            }

            delete_option($legacy_option_name);
            delete_option('_' . $legacy_option_name);
        }

        update_option($option_name, $version, false);
    }

    function meza_is_content_model_built_in_field_group_enabled(array $definition): bool
    {
        $identifier = sanitize_key((string) ($definition['key'] ?? ''));
        if ($identifier === '') {
            return false;
        }

        if (in_array($identifier, meza_get_content_model_field_group_management_protected_keys(), true)) {
            return true;
        }

        return meza_is_content_model_field_group_definition_available($definition);
    }

    function meza_prepare_content_model_field_group_management_field(array $field, string $bucket): array
    {
        $field['choices'] = meza_get_content_model_field_group_management_choice_map($bucket);

        return $field;
    }

    function meza_load_content_model_field_group_management_value($value, $post_id, array $field, string $bucket)
    {
        unset($value, $post_id);

        return meza_get_content_model_field_group_management_current_selection(
            $bucket,
            (array) ($field['choices'] ?? [])
        );
    }

    function meza_sync_content_model_built_in_field_group_selection(array $selected_keys): void
    {
        // Built-in field groups are controlled directly from runtime
        // availability and local definitions.
        unset($selected_keys);
    }

    function meza_sync_content_model_default_field_group_selection(array $selected_keys): void
    {
        if (
            !function_exists('meza_mark_managed_acf_definition_deleted')
            || !function_exists('meza_clear_managed_acf_definition_deleted')
            || !function_exists('meza_get_existing_editable_acf_field_group_id')
            || !function_exists('meza_get_disabled_editable_acf_field_group_id')
            || !function_exists('meza_disable_editable_acf_field_group_definition')
            || !function_exists('meza_enable_editable_acf_field_group_definition')
            || !function_exists('meza_is_editable_acf_field_group_auto_disabled')
            || !function_exists('meza_clear_editable_acf_field_group_auto_disabled')
        ) {
            return;
        }

        foreach (meza_get_content_model_field_group_management_definition_map('defaults') as $key => $definition) {
            if (in_array($key, $selected_keys, true)) {
                meza_clear_managed_acf_definition_deleted('field_groups', $definition);

                if (meza_is_editable_acf_field_group_auto_disabled($definition)) {
                    if (meza_get_disabled_editable_acf_field_group_id($definition) > 0) {
                        meza_enable_editable_acf_field_group_definition($definition);
                    } elseif (meza_get_existing_editable_acf_field_group_id($definition) > 0) {
                        meza_clear_editable_acf_field_group_auto_disabled($definition);
                    }
                }

                continue;
            }

            meza_mark_managed_acf_definition_deleted('field_groups', $definition);

            if (meza_get_existing_editable_acf_field_group_id($definition) > 0) {
                meza_disable_editable_acf_field_group_definition($definition);
                continue;
            }

            if (meza_get_disabled_editable_acf_field_group_id($definition) <= 0) {
                meza_clear_editable_acf_field_group_auto_disabled($definition);
            }
        }
    }

    function meza_sync_content_model_custom_field_group_selection(array $selected_keys): void
    {
        if (!function_exists('acf_update_internal_post_type_active_status')) {
            return;
        }

        foreach (meza_get_content_model_custom_field_group_records() as $key => $record) {
            $field_group_id = (int) ($record['ID'] ?? 0);
            $status = (string) ($record['status'] ?? '');
            if ($field_group_id <= 0) {
                continue;
            }

            if (in_array($key, $selected_keys, true)) {
                if ($status === 'acf-disabled') {
                    acf_update_internal_post_type_active_status($field_group_id, true, 'acf-field-group');
                }

                continue;
            }

            if ($status === 'publish') {
                acf_update_internal_post_type_active_status($field_group_id, false, 'acf-field-group');
            }
        }
    }

    function meza_sync_content_model_field_group_management_after_save($post_id): void
    {
        if (!meza_is_content_structure_acf_submission() || !in_array($post_id, ['option', 'options'], true)) {
            return;
        }

        $built_in_choices = meza_get_content_model_field_group_management_choice_map('built_in');
        $default_choices = meza_get_content_model_field_group_management_choice_map('defaults');
        $custom_choices = meza_get_content_model_field_group_management_choice_map('custom');

        $built_in_selection = meza_get_content_model_field_group_management_posted_selection('built_in', $built_in_choices);
        if ($built_in_selection === null) {
            $built_in_selection = meza_get_content_model_field_group_management_current_selection('built_in', $built_in_choices);
        }

        $default_selection = meza_get_content_model_field_group_management_posted_selection('defaults', $default_choices);
        if ($default_selection === null) {
            $default_selection = meza_get_content_model_field_group_management_current_selection('defaults', $default_choices);
        }

        $custom_selection = meza_get_content_model_field_group_management_posted_selection('custom', $custom_choices);
        if ($custom_selection === null) {
            $custom_selection = meza_get_content_model_field_group_management_current_selection('custom', $custom_choices);
        }

        meza_set_content_model_field_group_management_saved_selection('built_in', $built_in_selection);
        meza_set_content_model_field_group_management_saved_selection('defaults', $default_selection);
        meza_set_content_model_field_group_management_saved_selection('custom', $custom_selection);

        meza_sync_content_model_built_in_field_group_selection(
            $built_in_selection
        );
        meza_sync_content_model_default_field_group_selection(
            $default_selection
        );
        meza_sync_content_model_custom_field_group_selection(
            $custom_selection
        );
    }
}

add_action('acf/init', 'meza_cleanup_legacy_content_model_field_group_selection_options', 24);

add_action('init', static function (): void {
    if (!post_type_exists('profile')) {
        return;
    }

    add_post_type_support('profile', 'editor');
}, 20);

add_filter('acf/load_field/key=field_meza_content_model_custom_field_groups', static function ($field) {
    return is_array($field)
        ? meza_prepare_content_model_field_group_management_field($field, 'custom')
        : $field;
});

add_filter('acf/load_field/key=field_meza_content_model_default_field_groups', static function ($field) {
    return is_array($field)
        ? meza_prepare_content_model_field_group_management_field($field, 'defaults')
        : $field;
});

add_filter('acf/load_field/key=field_meza_content_model_built_in_field_groups', static function ($field) {
    return is_array($field)
        ? meza_prepare_content_model_field_group_management_field($field, 'built_in')
        : $field;
});

if (!function_exists('meza_get_content_model_object_management_configs')) {
    function meza_get_content_model_object_management_configs(): array
    {
        return [
            'post_types' => [
                'internal_post_type' => 'acf-post-type',
                'buckets' => [
                    'custom' => 'field_meza_content_model_custom_post_types',
                    'built_in' => 'field_meza_content_model_built_in_post_types',
                    'built_in_non_indexable' => 'field_meza_content_model_built_in_non_indexable_post_types',
                ],
                'indexable_field_key' => 'field_meza_content_model_indexable_post_types',
                'protected_built_in_identifiers' => [],
            ],
            'taxonomies' => [
                'internal_post_type' => 'acf-taxonomy',
                'buckets' => [
                    'custom' => 'field_meza_content_model_custom_taxonomies',
                    'built_in' => 'field_meza_content_model_built_in_taxonomies',
                    'built_in_non_indexable' => 'field_meza_content_model_built_in_non_indexable_taxonomies',
                ],
                'indexable_field_key' => 'field_meza_content_model_indexable_taxonomies',
                'protected_built_in_identifiers' => [],
            ],
            'options_pages' => [
                'internal_post_type' => 'acf-ui-options-page',
                'buckets' => [
                    'custom' => 'field_meza_content_model_custom_options_pages',
                    'built_in' => 'field_meza_content_model_built_in_options_pages',
                ],
                'protected_built_in_identifiers' => [
                    'business-information',
                    'branding',
                    'conference',
                    'conference-schedule',
                    'content-model',
                    'crm',
                    'ecommerce',
                ],
            ],
        ];
    }

    function meza_get_content_model_object_management_field_key_map(): array
    {
        $map = [];

        foreach (meza_get_content_model_object_management_configs() as $kind => $config) {
            foreach ((array) ($config['buckets'] ?? []) as $bucket => $field_key) {
                $field_key = sanitize_key((string) $field_key);
                if ($field_key === '') {
                    continue;
                }

                $map[$field_key] = [
                    'kind' => $kind,
                    'bucket' => $bucket,
                ];
            }

            $indexable_field_key = sanitize_key((string) ($config['indexable_field_key'] ?? ''));
            if ($indexable_field_key !== '') {
                $map[$indexable_field_key] = [
                    'kind' => $kind,
                    'bucket' => 'indexable',
                ];
            }
        }

        return $map;
    }

    function meza_get_content_model_object_management_behavior(string $kind, string $identifier): array
    {
        $identifier = sanitize_key($identifier);

        $defaults = [
            'supports_runtime_toggle' => true,
            'supports_admin_menu_toggle' => true,
            'ui_note' => '',
        ];

        $map = [
            'post_types' => [
                'certification' => [
                    'supports_runtime_toggle' => true,
                    'supports_admin_menu_toggle' => true,
                ],
                'organization' => [
                    'supports_runtime_toggle' => true,
                    'supports_admin_menu_toggle' => true,
                ],
            ],
        ];

        return array_merge($defaults, (array) ($map[$kind][$identifier] ?? []));
    }

    function meza_content_model_object_management_supports_runtime_toggle(string $kind, string $identifier): bool
    {
        return !empty(meza_get_content_model_object_management_behavior($kind, $identifier)['supports_runtime_toggle']);
    }

    function meza_get_content_model_object_management_field_key(string $kind, string $bucket): string
    {
        $config = meza_get_content_model_object_management_configs()[$kind] ?? [];
        if ($bucket === 'indexable') {
            return sanitize_key((string) ($config['indexable_field_key'] ?? ''));
        }

        return sanitize_key((string) (($config['buckets'] ?? [])[$bucket] ?? ''));
    }

    function meza_get_content_model_object_management_protected_built_in_identifiers(string $kind): array
    {
        $config = meza_get_content_model_object_management_configs()[$kind] ?? [];
        $identifiers = (array) ($config['protected_built_in_identifiers'] ?? []);

        if ($kind === 'post_types') {
            $identifiers = array_merge($identifiers, [
                'cta',
                'faq',
                'form',
                'page',
                'review',
            ]);
        }

        if ($kind === 'taxonomies') {
            $identifiers = array_merge(
                $identifiers,
                meza_get_content_model_object_management_required_built_in_taxonomy_identifiers()
            );
        }

        return array_values(array_unique(array_filter(array_map(
            static function ($identifier): string {
                return sanitize_key((string) $identifier);
            },
            $identifiers
        ))));
    }

    function meza_get_content_model_object_management_protected_indexable_identifiers(string $kind): array
    {
        $identifiers = [];

        if ($kind === 'post_types') {
            $identifiers[] = 'page';
        }

        return array_values(array_unique(array_filter(array_map('sanitize_key', $identifiers))));
    }

    function meza_get_content_model_object_management_current_built_in_post_type_selection(): array
    {
        $selection = [];
        $has_posted_selection = false;
        $has_saved_selection = false;

        foreach (['built_in', 'built_in_non_indexable'] as $bucket) {
            $choices = meza_get_content_model_object_management_choice_map('post_types', $bucket);
            $posted_selection = meza_get_content_model_object_management_posted_selection('post_types', $bucket, $choices);

            if ($posted_selection !== null) {
                $has_posted_selection = true;
                $selection = array_merge($selection, $posted_selection);
                continue;
            }

            $saved_selection = meza_get_content_model_object_management_saved_panel_selection('post_types', $bucket, $choices);
            if ($saved_selection !== null) {
                $has_saved_selection = true;
                $selection = array_merge($selection, $saved_selection);
            }
        }

        if ($has_posted_selection || $has_saved_selection) {
            return array_values(array_unique(array_map('sanitize_key', $selection)));
        }

        foreach (['built_in', 'built_in_non_indexable'] as $bucket) {
            $choices = meza_get_content_model_object_management_choice_map('post_types', $bucket);
            foreach (meza_get_content_model_object_management_current_selection('post_types', $bucket, $choices) as $identifier) {
                $identifier = sanitize_key((string) $identifier);
                if ($identifier !== '') {
                    $selection[$identifier] = $identifier;
                }
            }
        }

        return array_values($selection);
    }

    function meza_is_content_model_built_in_post_type_selected(string $identifier): bool
    {
        $identifier = sanitize_key($identifier);
        if ($identifier === '') {
            return false;
        }

        return in_array($identifier, meza_get_content_model_object_management_current_built_in_post_type_selection(), true);
    }

    function meza_is_content_model_saved_built_in_post_type_selected(string $identifier): bool
    {
        $identifier = sanitize_key($identifier);
        if ($identifier === '') {
            return false;
        }

        $saved_selection = [];
        $has_saved_selection = false;

        foreach ([
            'options_content_model_built_in_post_types',
            'options_content_model_built_in_non_indexable_post_types',
        ] as $option_name) {
            $saved_value = meza_get_content_model_saved_option_value($option_name, $option_exists);
            if (!$option_exists) {
                continue;
            }

            $has_saved_selection = true;
            $saved_selection = array_merge($saved_selection, (array) $saved_value);
        }

        if ($has_saved_selection) {
            $normalized = array_values(array_unique(array_filter(array_map('sanitize_key', (array) $saved_selection))));
            return in_array($identifier, $normalized, true);
        }

        foreach (meza_get_local_acf_post_type_definitions() as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            if (sanitize_key((string) ($definition['post_type'] ?? '')) === $identifier) {
                return true;
            }
        }

        return false;
    }

    function meza_get_content_model_object_management_dependent_built_in_taxonomy_definitions(): array
    {
        $definitions = [];

        if (function_exists('meza_get_organization_type_taxonomy_definition')) {
            $definitions['organization-type'] = meza_get_organization_type_taxonomy_definition();
        }

        if (function_exists('meza_get_event_type_taxonomy_definition')) {
            $definitions['event-type'] = meza_get_event_type_taxonomy_definition();
        }

        if (function_exists('meza_get_profile_type_taxonomy_definition')) {
            $definitions['profile-type'] = meza_get_profile_type_taxonomy_definition();
        }

        return $definitions;
    }

    function meza_get_content_model_object_management_required_built_in_taxonomy_identifiers(): array
    {
        $required = [];
        $dependencies = [
            'organization-type' => 'organization',
            'event-type' => 'event',
            'profile-type' => 'profile',
        ];

        foreach ($dependencies as $taxonomy_identifier => $post_type_identifier) {
            if (meza_is_content_model_built_in_post_type_selected($post_type_identifier)) {
                $required[$taxonomy_identifier] = $taxonomy_identifier;
            }
        }

        return array_values($required);
    }

    function meza_is_content_model_built_in_taxonomy_selected(string $identifier): bool
    {
        $identifier = sanitize_key($identifier);
        if ($identifier === '') {
            return false;
        }

        foreach (['built_in', 'built_in_non_indexable'] as $bucket) {
            $choices = meza_get_content_model_object_management_choice_map('taxonomies', $bucket);
            if (in_array($identifier, meza_get_content_model_object_management_current_selection('taxonomies', $bucket, $choices), true)) {
                return true;
            }
        }

        return false;
    }

    function meza_get_content_model_object_management_all_built_in_post_type_definitions(): array
    {
        $definitions = [
            meza_get_faq_post_type_definition(),
            meza_get_cta_post_type_definition(),
            meza_get_review_post_type_definition(),
            meza_get_organization_post_type_definition(),
            meza_get_service_post_type_definition(),
            meza_get_event_post_type_definition(),
        ];

        if (function_exists('meza_supports_profile_features') && meza_supports_profile_features()) {
            $definitions[] = meza_get_profile_post_type_definition();
        }

        if (function_exists('meza_is_nonprofit_business_type') && meza_is_nonprofit_business_type()) {
            $definitions[] = meza_get_certification_post_type_definition();
        }

        if (function_exists('mzf_get_form_post_type_definition')) {
            $definitions[] = mzf_get_form_post_type_definition();
        }

        return $definitions;
    }

    function meza_get_content_model_object_management_all_built_in_taxonomy_definitions(): array
    {
        $definitions = [];

        if (meza_is_content_model_saved_built_in_post_type_selected('service')) {
            $definitions[] = meza_get_service_category_taxonomy_definition();
        }

        if (function_exists('meza_get_locality_taxonomy_definition')) {
            $definitions[] = meza_get_locality_taxonomy_definition();
        }

        if (meza_is_content_model_built_in_post_type_selected('event')) {
            $definitions[] = meza_get_topic_taxonomy_definition();
        }

        foreach (meza_get_content_model_object_management_required_built_in_taxonomy_identifiers() as $identifier) {
            $definition = meza_get_content_model_object_management_dependent_built_in_taxonomy_definitions()[$identifier] ?? null;
            if (is_array($definition)) {
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    function meza_get_content_model_object_management_active_option_page_definitions(): array
    {
        $pages = [];

        foreach (meza_get_shared_project_acf_options_pages() as $page) {
            if (!is_array($page)) {
                continue;
            }

            $slug = sanitize_key((string) ($page['menu_slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            if (in_array($slug, ['conference', 'conference-schedule'], true)) {
                if (!(function_exists('meza_is_conference_business_type') && meza_is_conference_business_type())) {
                    continue;
                }
            }

            $pages[] = $page;
        }

        return $pages;
    }

    function meza_get_content_model_object_management_synthetic_definitions(string $kind, string $bucket): array
    {
        if ($kind === 'post_types' && $bucket === 'built_in') {
            return [
                [
                    'post_type' => 'page',
                    'title' => 'Pages',
                    'labels' => [
                        'singular_name' => 'Pages',
                    ],
                    'public' => 1,
                    'publicly_queryable' => 1,
                    'exclude_from_search' => 0,
                    'rewrite' => true,
                ],
                [
                    'post_type' => 'post',
                    'title' => 'Posts',
                    'labels' => [
                        'singular_name' => 'Posts',
                    ],
                    'public' => 1,
                    'publicly_queryable' => 1,
                    'exclude_from_search' => 0,
                    'rewrite' => true,
                ],
                [
                    'post_type' => 'product',
                    'title' => 'Products',
                    'labels' => [
                        'singular_name' => 'Product',
                    ],
                    'public' => 1,
                    'publicly_queryable' => 1,
                    'exclude_from_search' => 0,
                    'rewrite' => true,
                ],
            ];
        }

        if ($kind === 'post_types' && $bucket === 'built_in_non_indexable') {
            if (!meza_is_content_model_saved_built_in_post_type_selected('product')) {
                return [];
            }

            return [
                [
                    'post_type' => 'product-review',
                    'title' => 'Product Reviews',
                    'labels' => [
                        'singular_name' => 'Product Reviews',
                    ],
                    'public' => 0,
                    'publicly_queryable' => 0,
                    'exclude_from_search' => 1,
                    'rewrite' => false,
                ],
            ];
        }

        if ($kind === 'taxonomies' && in_array($bucket, ['built_in', 'built_in_non_indexable'], true)) {
            $definitions = [];

            if (meza_is_content_model_saved_built_in_post_type_selected('post')) {
                $definitions[] = [
                    'taxonomy' => 'category',
                    'title' => 'Post Categories',
                    'labels' => [
                        'singular_name' => 'Post Categories',
                    ],
                ];
                if (function_exists('meza_are_post_categories_enabled') && meza_are_post_categories_enabled()) {
                    $definitions[] = [
                        'taxonomy' => 'post_tag',
                        'title' => 'Post Tags',
                        'labels' => [
                            'singular_name' => 'Post Tags',
                        ],
                    ];
                }
            }

            if (meza_is_content_model_saved_built_in_post_type_selected('product')) {
                $definitions[] = [
                    'taxonomy' => 'product_cat',
                    'title' => 'Product Categories',
                    'labels' => [
                        'singular_name' => 'Product Categories',
                    ],
                    'public' => 1,
                    'publicly_queryable' => 1,
                    'rewrite' => true,
                ];

                if (function_exists('meza_are_product_categories_enabled') && meza_are_product_categories_enabled()) {
                    $definitions[] = [
                        'taxonomy' => 'product_tag',
                        'title' => 'Product Tags',
                        'labels' => [
                            'singular_name' => 'Product Tags',
                        ],
                        'public' => 1,
                        'publicly_queryable' => 1,
                        'rewrite' => true,
                    ];
                }

                if (taxonomy_exists('product_brand')) {
                    $definitions[] = [
                        'taxonomy' => 'product_brand',
                        'title' => 'Product Brands',
                        'labels' => [
                            'singular_name' => 'Product Brands',
                        ],
                        'public' => 1,
                        'publicly_queryable' => 1,
                        'rewrite' => true,
                    ];
                }
            }

            return $definitions;
        }

        return [];
    }

    function meza_get_content_model_object_management_legacy_enabled_option_map(): array
    {
        return [
            'post_types' => [
                'service' => [
                    'option_name' => 'options_services',
                    'field_key' => 'field_6a0947aec1f52',
                    'legacy_option_name' => null,
                ],
                'event' => [
                    'option_name' => 'options_events',
                    'field_key' => 'field_meza_include_event_functionality',
                    'legacy_option_name' => 'options_include_event_functionality',
                ],
                'product' => [
                    'option_name' => 'options_woocommerce',
                    'field_key' => '',
                    'legacy_option_name' => null,
                ],
            ],
            'taxonomies' => [
                'locality' => [
                    'option_name' => 'options_localities',
                    'field_key' => 'field_6a09483cc8282',
                    'legacy_option_name' => null,
                ],
                'topic' => [
                    'option_name' => 'options_events_speakers',
                    'field_key' => 'field_meza_event_speakers_topics',
                    'legacy_option_name' => 'options_event_speakers_topics',
                ],
                'category' => [
                    'option_name' => 'options_posts_categories',
                    'field_key' => 'field_meza_posts_categories',
                    'legacy_option_name' => null,
                ],
                'post_tag' => [
                    'option_name' => 'options_posts_tags',
                    'field_key' => 'field_meza_posts_tags',
                    'legacy_option_name' => null,
                ],
            ],
        ];
    }

    function meza_get_content_model_object_management_legacy_indexable_option_map(): array
    {
        return [
            'post_types' => [
                'service' => [
                    'option_name' => 'options_services_indexable',
                    'field_key' => 'field_6a0947aec1f56',
                    'legacy_option_name' => null,
                ],
                'event' => [
                    'option_name' => 'options_events_indexable',
                    'field_key' => 'field_meza_indexable_events',
                    'legacy_option_name' => 'options_indexable_events',
                ],
                'product' => [
                    'option_name' => 'options_product_indexing',
                    'field_key' => '',
                    'legacy_option_name' => null,
                ],
            ],
            'taxonomies' => [
                'locality' => [
                    'option_name' => 'options_localities_indexable',
                    'field_key' => 'field_6a09483cc8285',
                    'legacy_option_name' => null,
                ],
                'topic' => [
                    'option_name' => 'options_topics_indexable',
                    'field_key' => '',
                    'legacy_option_name' => null,
                ],
                'category' => [
                    'option_name' => 'options_posts_categories_indexable',
                    'field_key' => 'field_meza_posts_categories_indexable',
                    'legacy_option_name' => null,
                ],
                'post_tag' => [
                    'option_name' => 'options_posts_tags_indexable',
                    'field_key' => '',
                    'legacy_option_name' => null,
                ],
            ],
        ];
    }

    function meza_get_content_model_object_management_legacy_option_definition(string $kind, string $identifier, string $type): array
    {
        $map = $type === 'indexable'
            ? meza_get_content_model_object_management_legacy_indexable_option_map()
            : meza_get_content_model_object_management_legacy_enabled_option_map();

        return (array) (($map[$kind] ?? [])[$identifier] ?? []);
    }

    function meza_get_content_model_object_management_legacy_state(string $kind, string $identifier, string $type): ?bool
    {
        $definition = meza_get_content_model_object_management_legacy_option_definition($kind, $identifier, $type);
        $option_name = (string) ($definition['option_name'] ?? '');
        if ($option_name === '') {
            return null;
        }

        $value = get_option($option_name, null);
        if ($value !== null) {
            return meza_business_information_acf_truthy($value);
        }

        $legacy_option_name = (string) ($definition['legacy_option_name'] ?? '');
        if ($legacy_option_name !== '') {
            $legacy_value = get_option($legacy_option_name, null);
            if ($legacy_value !== null) {
                return meza_business_information_acf_truthy($legacy_value);
            }
        }

        return null;
    }

    function meza_set_content_model_object_management_legacy_state(string $kind, string $identifier, string $type, bool $enabled): void
    {
        $definition = meza_get_content_model_object_management_legacy_option_definition($kind, $identifier, $type);
        $option_name = (string) ($definition['option_name'] ?? '');
        if ($option_name === '') {
            return;
        }

        $field_key = (string) ($definition['field_key'] ?? '');
        $value = $enabled ? '1' : '0';

        update_option($option_name, $value, false);

        if ($field_key !== '') {
            update_option('_' . $option_name, $field_key, false);
        }

        $legacy_option_name = (string) ($definition['legacy_option_name'] ?? '');
        if ($legacy_option_name !== '') {
            update_option($legacy_option_name, $value, false);
        }
    }

    function meza_get_content_model_object_management_indexable_identifiers(string $kind): array
    {
        $identifiers = [];

        foreach (meza_get_content_model_object_management_definition_map($kind, 'built_in') as $identifier => $definition) {
            unset($definition);
            $identifier = sanitize_key((string) $identifier);
            if ($identifier !== '') {
                $identifiers[$identifier] = $identifier;
            }
        }

        foreach (meza_get_content_model_object_management_custom_records($kind) as $identifier => $record) {
            unset($record);
            $identifier = sanitize_key((string) $identifier);
            if ($identifier !== '') {
                $identifiers[$identifier] = $identifier;
            }
        }

        return array_values($identifiers);
    }

    function meza_get_content_model_object_management_non_indexable_identifiers(string $kind): array
    {
        $map = [
            'post_types' => [
                'certification',
                'cta',
                'faq',
                'form',
                'organization',
                'product-review',
                'review',
                'song',
            ],
            'taxonomies' => [
                'event-type',
                'instrument',
                'organization-type',
                'post_tag',
                'profile-type',
                'season',
            ],
        ];

        return array_values(array_unique(array_filter(array_map(
            'sanitize_key',
            (array) ($map[$kind] ?? [])
        ))));
    }

    function meza_is_content_model_object_management_toggleable_post_type_identifier(string $identifier): bool
    {
        $identifier = sanitize_key($identifier);
        if ($identifier === '') {
            return false;
        }

        return in_array($identifier, [
            'post',
            'service',
            'event',
            'profile',
            'organization',
            'review',
            'faq',
            'cta',
            'form',
            'certification',
        ], true);
    }

    function meza_is_content_model_object_management_taxonomy_default_off(array $definition): bool
    {
        $object_types = array_values(array_filter(array_map(
            'sanitize_key',
            (array) ($definition['object_type'] ?? [])
        )));

        foreach ($object_types as $object_type) {
            if (meza_is_content_model_object_management_toggleable_post_type_identifier($object_type)) {
                return true;
            }
        }

        return false;
    }

    function meza_is_content_model_object_management_rewrite_indexable($rewrite): bool
    {
        if ($rewrite === false || $rewrite === null || $rewrite === '') {
            return false;
        }

        if (is_array($rewrite)) {
            $mode = sanitize_key((string) ($rewrite['permalink_rewrite'] ?? ''));
            if ($mode !== '') {
                return $mode !== 'no_permalink';
            }

            if (array_key_exists('slug', $rewrite)) {
                return trim((string) $rewrite['slug']) !== '';
            }

            return true;
        }

        return (bool) $rewrite;
    }

    function meza_is_content_model_object_management_permalink_capable(string $kind, array $object): bool
    {
        unset($kind);

        return meza_is_content_model_object_management_rewrite_indexable($object['rewrite'] ?? false);
    }

    function meza_get_content_model_object_management_custom_locked_indexable_identifiers(string $kind): array
    {
        $identifiers = [];

        foreach (meza_get_content_model_object_management_custom_records($kind) as $identifier => $record) {
            $identifier = sanitize_key((string) $identifier);
            $object = (array) ($record['object'] ?? []);

            if (
                $identifier === ''
                || $object === []
                || meza_is_content_model_object_management_permalink_capable($kind, $object)
            ) {
                continue;
            }

            $identifiers[$identifier] = $identifier;
        }

        return array_values($identifiers);
    }

    function meza_is_content_model_object_management_indexable_by_default(string $kind, array $object): bool
    {
        if ($kind === 'post_types') {
            return !empty($object['public'])
                && !empty($object['publicly_queryable'])
                && empty($object['exclude_from_search'])
                && meza_is_content_model_object_management_rewrite_indexable($object['rewrite'] ?? false);
        }

        if ($kind === 'taxonomies') {
            return !empty($object['public'])
                && !empty($object['publicly_queryable'])
                && meza_is_content_model_object_management_rewrite_indexable($object['rewrite'] ?? false);
        }

        return false;
    }

    function meza_get_content_model_object_management_indexable_default_selection(string $kind, array $choices): array
    {
        $selected = [];
        $locked_identifiers = meza_get_content_model_object_management_non_indexable_identifiers($kind);
        $protected_identifiers = meza_get_content_model_object_management_protected_indexable_identifiers($kind);

        foreach (meza_get_content_model_object_management_custom_records($kind) as $identifier => $record) {
            if (
                !array_key_exists($identifier, $choices)
                || in_array($identifier, $locked_identifiers, true)
                || !meza_is_content_model_object_management_indexable_by_default($kind, (array) ($record['object'] ?? []))
            ) {
                continue;
            }

            $selected[$identifier] = $identifier;
        }

        foreach (meza_get_content_model_object_management_definition_map($kind, 'built_in') as $identifier => $definition) {
            if (!array_key_exists($identifier, $choices)) {
                continue;
            }

            if (in_array($identifier, $protected_identifiers, true)) {
                $selected[$identifier] = $identifier;
                continue;
            }

            if (in_array($identifier, $locked_identifiers, true)) {
                continue;
            }

            $legacy_state = meza_get_content_model_object_management_legacy_state($kind, $identifier, 'indexable');
            if ($legacy_state !== null) {
                if ($legacy_state) {
                    $selected[$identifier] = $identifier;
                }

                continue;
            }

            if (meza_is_content_model_object_management_indexable_by_default($kind, $definition)) {
                $selected[$identifier] = $identifier;
            }
        }

        return array_values($selected);
    }

    function meza_get_content_model_reset_default_off_built_in_post_type_identifiers(): array
    {
        $identifiers = [
            'event',
            'post',
            'product',
            'product-review',
            'review',
            'service',
        ];

        if (function_exists('meza_is_nonprofit_business_type') && meza_is_nonprofit_business_type()) {
            $identifiers = array_merge($identifiers, [
                'certification',
                'organization',
                'profile',
            ]);
        }

        return array_values(array_unique(array_filter(array_map('sanitize_key', $identifiers))));
    }

    function meza_get_content_model_reset_default_off_built_in_taxonomy_identifiers(): array
    {
        return [
            'category',
            'locality',
            'product_brand',
            'product_cat',
            'product_tag',
            'topic',
        ];
    }

    function meza_get_content_model_reset_default_indexable_selection(string $kind, array $choices): array
    {
        $selected = [];
        $locked_identifiers = meza_get_content_model_object_management_non_indexable_identifiers($kind);
        $protected_identifiers = meza_get_content_model_object_management_protected_indexable_identifiers($kind);

        foreach (meza_get_content_model_object_management_custom_records($kind) as $identifier => $record) {
            if (
                !array_key_exists($identifier, $choices)
                || in_array($identifier, $locked_identifiers, true)
                || !meza_is_content_model_object_management_indexable_by_default($kind, (array) ($record['object'] ?? []))
            ) {
                continue;
            }

            $selected[$identifier] = $identifier;
        }

        foreach (meza_get_content_model_object_management_definition_map($kind, 'built_in') as $identifier => $definition) {
            if (!array_key_exists($identifier, $choices)) {
                continue;
            }

            if (in_array($identifier, $protected_identifiers, true)) {
                $selected[$identifier] = $identifier;
                continue;
            }

            if (in_array($identifier, $locked_identifiers, true)) {
                continue;
            }

            if (meza_is_content_model_object_management_indexable_by_default($kind, $definition)) {
                $selected[$identifier] = $identifier;
            }
        }

        return array_values($selected);
    }

    function meza_get_content_model_object_management_saved_panel_option_name(string $kind, string $bucket): string
    {
        $map = [
            'post_types:built_in' => 'options_content_model_built_in_post_types',
            'post_types:built_in_non_indexable' => 'options_content_model_built_in_non_indexable_post_types',
            'post_types:indexable' => 'options_content_model_indexable_post_types',
            'taxonomies:built_in' => 'options_content_model_built_in_taxonomies',
            'taxonomies:built_in_non_indexable' => 'options_content_model_built_in_non_indexable_taxonomies',
            'taxonomies:indexable' => 'options_content_model_indexable_taxonomies',
        ];

        return (string) ($map[$kind . ':' . $bucket] ?? '');
    }

    function meza_get_content_model_object_management_saved_panel_field_key(string $kind, string $bucket): string
    {
        $config = meza_get_content_model_object_management_configs()[$kind] ?? [];

        if ($bucket === 'indexable') {
            return sanitize_key((string) ($config['indexable_field_key'] ?? ''));
        }

        return sanitize_key((string) (($config['buckets'] ?? [])[$bucket] ?? ''));
    }

    function meza_set_content_model_object_management_saved_panel_selection(string $kind, string $bucket, array $selected_identifiers): void
    {
        $option_name = meza_get_content_model_object_management_saved_panel_option_name($kind, $bucket);
        $field_key = meza_get_content_model_object_management_saved_panel_field_key($kind, $bucket);

        if ($option_name === '') {
            return;
        }

        $choices = meza_get_content_model_object_management_choice_map($kind, $bucket);
        $normalized = meza_normalize_content_model_field_group_management_selection($selected_identifiers, $choices);

        if (in_array($bucket, ['built_in', 'built_in_non_indexable'], true)) {
            foreach (meza_get_content_model_object_management_protected_built_in_identifiers($kind) as $identifier) {
                if (array_key_exists($identifier, $choices) && !in_array($identifier, $normalized, true)) {
                    $normalized[] = $identifier;
                }
            }
        }

        if ($bucket === 'indexable') {
            $normalized = array_values(array_filter($normalized, static function (string $identifier) use ($kind): bool {
                return !in_array($identifier, meza_get_content_model_object_management_non_indexable_identifiers($kind), true);
            }));

            foreach (meza_get_content_model_object_management_protected_indexable_identifiers($kind) as $identifier) {
                if (array_key_exists($identifier, $choices) && !in_array($identifier, $normalized, true)) {
                    $normalized[] = $identifier;
                }
            }
        }

        update_option($option_name, array_values(array_unique($normalized)), false);

        if ($field_key !== '') {
            update_option('_' . $option_name, $field_key, false);
        }
    }

    function meza_get_content_model_object_management_saved_panel_selection(string $kind, string $bucket, array $choices): ?array
    {
        $option_name = meza_get_content_model_object_management_saved_panel_option_name($kind, $bucket);
        if ($option_name === '') {
            return null;
        }

        $saved = meza_get_content_model_saved_option_value($option_name, $option_exists);
        if (!$option_exists) {
            return null;
        }

        $selection = meza_normalize_content_model_field_group_management_selection($saved, $choices);

        if ($bucket === 'indexable') {
            $selection = array_values(array_filter($selection, static function (string $identifier) use ($kind): bool {
                return !in_array($identifier, meza_get_content_model_object_management_non_indexable_identifiers($kind), true);
            }));
        }

        return $selection;
    }

    function meza_get_content_model_object_management_definition_map(string $kind, string $bucket): array
    {
        $definitions = [];

        switch ($kind . ':' . $bucket) {
            case 'post_types:built_in':
            case 'post_types:built_in_non_indexable':
                $definitions = array_merge(
                    meza_get_content_model_object_management_all_built_in_post_type_definitions(),
                    meza_get_content_model_object_management_synthetic_definitions($kind, $bucket)
                );
                break;
            case 'taxonomies:built_in':
            case 'taxonomies:built_in_non_indexable':
                $definitions = array_merge(
                    meza_get_content_model_object_management_all_built_in_taxonomy_definitions(),
                    meza_get_content_model_object_management_synthetic_definitions($kind, $bucket)
                );
                break;
            case 'taxonomies:defaults':
                $definitions = function_exists('meza_get_default_editable_acf_taxonomy_definitions')
                    ? meza_get_default_editable_acf_taxonomy_definitions()
                    : [];
                break;
            case 'options_pages:built_in':
                $definitions = meza_get_content_model_object_management_active_option_page_definitions();
                break;
        }

        $map = [];

        foreach ((array) $definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            if ($kind === 'options_pages' && $bucket === 'built_in' && !meza_is_content_model_field_group_location_rule_available([
                'param' => 'options_page',
                'operator' => '==',
                'value' => (string) ($definition['menu_slug'] ?? ''),
            ])) {
                continue;
            }

            $identifier = meza_get_managed_acf_definition_identifier($kind, $definition);
            if ($identifier === '') {
                continue;
            }

            $map[$identifier] = $definition;
        }

        if (in_array($bucket, ['built_in', 'built_in_non_indexable'], true)) {
            $non_indexable_identifiers = meza_get_content_model_object_management_non_indexable_identifiers($kind);

            $map = array_filter(
                $map,
                static function (string $identifier) use ($bucket, $non_indexable_identifiers): bool {
                    $is_non_indexable = in_array($identifier, $non_indexable_identifiers, true);

                    return $bucket === 'built_in_non_indexable'
                        ? $is_non_indexable
                        : !$is_non_indexable;
                },
                ARRAY_FILTER_USE_KEY
            );
        }

        uasort($map, static function (array $left, array $right) use ($kind): int {
            return strcasecmp(
                meza_get_content_model_object_management_object_title($kind, $left),
                meza_get_content_model_object_management_object_title($kind, $right)
            );
        });

        return $map;
    }

    function meza_get_content_model_object_management_object(string $kind, int $post_id): array
    {
        switch ($kind) {
            case 'post_types':
                return function_exists('acf_get_post_type') ? (array) acf_get_post_type($post_id) : [];
            case 'taxonomies':
                return function_exists('acf_get_taxonomy') ? (array) acf_get_taxonomy($post_id) : [];
            case 'options_pages':
                return function_exists('acf_get_ui_options_page') ? (array) acf_get_ui_options_page($post_id) : [];
        }

        return [];
    }

    function meza_get_content_model_object_management_object_title(string $kind, array $object): string
    {
        $title = '';

        switch ($kind) {
            case 'post_types':
                $title = (string) ($object['title'] ?? ($object['labels']['singular_name'] ?? ($object['post_type'] ?? '')));
                break;
            case 'taxonomies':
                $title = (string) ($object['title'] ?? ($object['labels']['singular_name'] ?? ($object['taxonomy'] ?? '')));
                break;
            case 'options_pages':
                $title = (string) ($object['title'] ?? ($object['page_title'] ?? ($object['menu_title'] ?? ($object['menu_slug'] ?? ''))));
                break;
        }

        $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        $title = wp_strip_all_tags($title);
        $title = preg_replace('/\s+/', ' ', $title);

        return trim((string) $title);
    }

    function meza_get_content_model_object_management_custom_records(string $kind): array
    {
        $config = meza_get_content_model_object_management_configs()[$kind] ?? [];
        $internal_post_type = (string) ($config['internal_post_type'] ?? '');
        if ($internal_post_type === '') {
            return [];
        }

        $posts = get_posts([
            'posts_per_page' => -1,
            'post_type' => $internal_post_type,
            'post_status' => ['publish', 'acf-disabled'],
            'orderby' => 'menu_order title',
            'order' => 'ASC',
            'suppress_filters' => false,
            'cache_results' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        $records = [];

        foreach ($posts as $post) {
            if (!($post instanceof WP_Post)) {
                continue;
            }

            $object = meza_get_content_model_object_management_object($kind, (int) $post->ID);
            if (!is_array($object) || $object === []) {
                continue;
            }

            $identifier = meza_get_managed_acf_definition_identifier($kind, $object);
            $title = meza_get_content_model_object_management_object_title($kind, $object);
            if ($identifier === '' || $title === '') {
                continue;
            }

            if (function_exists('meza_get_acf_admin_definition_status_for_post')) {
                if (meza_get_acf_admin_definition_status_for_post($internal_post_type, (int) $post->ID) !== 'custom') {
                    continue;
                }
            } elseif (function_exists('meza_get_acf_export_tool_group_for_post')) {
                if (meza_get_acf_export_tool_group_for_post($internal_post_type, $object) !== 'custom') {
                    continue;
                }
            }

            $records[$identifier] = [
                'ID' => (int) $post->ID,
                'identifier' => $identifier,
                'title' => $title,
                'status' => (string) get_post_status($post),
                'object' => $object,
            ];
        }

        uasort($records, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });

        return $records;
    }

    function meza_is_content_model_object_management_custom_definition(string $kind, array $object): bool
    {
        $config = meza_get_content_model_object_management_configs()[$kind] ?? [];
        $internal_post_type = (string) ($config['internal_post_type'] ?? '');
        $object_id = (int) ($object['ID'] ?? 0);

        if (
            $internal_post_type !== ''
            && $object_id > 0
            && function_exists('meza_get_acf_admin_definition_status_for_post')
        ) {
            return meza_get_acf_admin_definition_status_for_post($internal_post_type, $object_id) === 'custom';
        }

        return $internal_post_type !== ''
            && function_exists('meza_get_acf_export_tool_group_for_post')
            && meza_get_acf_export_tool_group_for_post($internal_post_type, $object) === 'custom';
    }

    function meza_get_content_model_object_management_choice_map(string $kind, string $bucket): array
    {
        if ($bucket === 'indexable') {
            return array_merge(
                meza_get_content_model_object_management_choice_map($kind, 'custom'),
                meza_get_content_model_object_management_choice_map($kind, 'built_in')
            );
        }

        if ($bucket === 'custom') {
            $choices = [];

            foreach (meza_get_content_model_object_management_custom_records($kind) as $record) {
                $identifier = sanitize_key((string) ($record['identifier'] ?? ''));
                $title = (string) ($record['title'] ?? '');
                if ($identifier === '' || $title === '') {
                    continue;
                }

                $choices[$identifier] = $title;
            }

            return $choices;
        }

        $choices = [];

        foreach (meza_get_content_model_object_management_definition_map($kind, $bucket) as $identifier => $definition) {
            $choices[$identifier] = meza_get_content_model_object_management_object_title($kind, $definition);
        }

        return $choices;
    }

    function meza_get_content_model_object_management_posted_selection(string $kind, string $bucket, array $choices): ?array
    {
        $field_key = meza_get_content_model_object_management_field_key($kind, $bucket);
        if (
            $field_key === ''
            || !isset($_POST['acf'])
            || !is_array($_POST['acf'])
            || !array_key_exists($field_key, $_POST['acf'])
        ) {
            return null;
        }

        return meza_normalize_content_model_field_group_management_selection(
            wp_unslash($_POST['acf'][$field_key]),
            $choices
        );
    }

    function meza_get_content_model_object_management_current_enabled_identifiers(string $kind): array
    {
        $enabled = [];

        foreach (['custom', 'built_in', 'built_in_non_indexable'] as $bucket) {
            $field_key = meza_get_content_model_object_management_field_key($kind, $bucket);
            if ($field_key === '') {
                continue;
            }

            $choices = meza_get_content_model_object_management_choice_map($kind, $bucket);
            foreach (meza_get_content_model_object_management_current_selection($kind, $bucket, $choices) as $identifier) {
                $identifier = sanitize_key((string) $identifier);
                if ($identifier !== '') {
                    $enabled[$identifier] = $identifier;
                }
            }
        }

        return array_values($enabled);
    }

    function meza_get_content_model_object_management_current_selection(string $kind, string $bucket, array $choices): array
    {
        $posted_selection = meza_get_content_model_object_management_posted_selection($kind, $bucket, $choices);
        if ($posted_selection !== null) {
            if (in_array($bucket, ['built_in', 'built_in_non_indexable'], true)) {
                $protected = meza_get_content_model_object_management_protected_built_in_identifiers($kind);
                foreach ($protected as $identifier) {
                    if (array_key_exists($identifier, $choices) && !in_array($identifier, $posted_selection, true)) {
                        $posted_selection[] = $identifier;
                    }
                }
            }

            return array_values(array_unique($posted_selection));
        }

        if ($bucket === 'indexable') {
            $saved_selection = meza_get_content_model_object_management_saved_panel_selection($kind, $bucket, $choices);
            if ($saved_selection !== null) {
                foreach (meza_get_content_model_object_management_protected_indexable_identifiers($kind) as $identifier) {
                    if (array_key_exists($identifier, $choices) && !in_array($identifier, $saved_selection, true)) {
                        $saved_selection[] = $identifier;
                    }
                }

                $enabled_identifiers = array_fill_keys(
                    meza_get_content_model_object_management_current_enabled_identifiers($kind),
                    true
                );

                return array_values(array_filter($saved_selection, static function (string $identifier) use ($enabled_identifiers): bool {
                    return isset($enabled_identifiers[$identifier]);
                }));
            }

            $default_selection = meza_get_content_model_object_management_indexable_default_selection($kind, $choices);
            $enabled_identifiers = array_fill_keys(
                meza_get_content_model_object_management_current_enabled_identifiers($kind),
                true
            );

            return array_values(array_filter($default_selection, static function (string $identifier) use ($enabled_identifiers): bool {
                return isset($enabled_identifiers[$identifier]);
            }));
        }

        if ($bucket === 'custom') {
            $selected = [];

            foreach (meza_get_content_model_object_management_custom_records($kind) as $record) {
                $identifier = sanitize_key((string) ($record['identifier'] ?? ''));
                $status = (string) ($record['status'] ?? '');
                if ($identifier === '' || !array_key_exists($identifier, $choices) || $status !== 'publish') {
                    continue;
                }

                $selected[$identifier] = $identifier;
            }

            return array_values($selected);
        }

        $selected = [];
        $protected = in_array($bucket, ['built_in', 'built_in_non_indexable'], true)
            ? meza_get_content_model_object_management_protected_built_in_identifiers($kind)
            : [];
        $saved_panel_selection = meza_get_content_model_object_management_saved_panel_selection($kind, $bucket, $choices);

        if ($saved_panel_selection !== null) {
            foreach ($protected as $identifier) {
                if (array_key_exists($identifier, $choices) && !in_array($identifier, $saved_panel_selection, true)) {
                    $saved_panel_selection[] = $identifier;
                }
            }

            return array_values(array_unique($saved_panel_selection));
        }

        foreach (meza_get_content_model_object_management_definition_map($kind, $bucket) as $identifier => $definition) {
            if (!array_key_exists($identifier, $choices)) {
                continue;
            }

            if (in_array($identifier, $protected, true)) {
                $selected[$identifier] = $identifier;
                continue;
            }

            $legacy_state = meza_get_content_model_object_management_legacy_state($kind, $identifier, 'enabled');
            if ($legacy_state !== null) {
                if ($legacy_state && meza_get_content_model_object_management_saved_panel_option_name($kind, $bucket) === '') {
                    $selected[$identifier] = $identifier;
                }

                continue;
            }

            if (!meza_is_managed_acf_definition_manually_deleted($kind, $definition)) {
                if (
                    $kind === 'post_types'
                    && in_array($bucket, ['built_in', 'built_in_non_indexable'], true)
                    && in_array($identifier, meza_get_content_model_reset_default_off_built_in_post_type_identifiers(), true)
                ) {
                    continue;
                }

                if (
                    $kind === 'taxonomies'
                    && in_array($bucket, ['built_in', 'built_in_non_indexable'], true)
                    && meza_is_content_model_object_management_taxonomy_default_off($definition)
                ) {
                    continue;
                }

                $selected[$identifier] = $identifier;
            }
        }

        return array_values($selected);
    }

    function meza_get_content_model_reset_field_group_selection(string $bucket): array
    {
        if (
            $bucket === 'defaults'
            && function_exists('meza_is_nonprofit_business_type')
            && meza_is_nonprofit_business_type()
        ) {
            return [];
        }

        return array_values(array_map(
            'sanitize_key',
            array_keys(meza_get_content_model_field_group_management_choice_map($bucket))
        ));
    }

    function meza_get_content_model_reset_object_selection(string $kind, string $bucket): array
    {
        $choices = meza_get_content_model_object_management_choice_map($kind, $bucket);
        if ($bucket === 'custom') {
            return array_values(array_map('sanitize_key', array_keys($choices)));
        }

        $selected = [];
        $protected = in_array($bucket, ['built_in', 'built_in_non_indexable'], true)
            ? meza_get_content_model_object_management_protected_built_in_identifiers($kind)
            : [];
        $default_off_post_types = meza_get_content_model_reset_default_off_built_in_post_type_identifiers();
        $default_off_taxonomies = meza_get_content_model_reset_default_off_built_in_taxonomy_identifiers();

        foreach (meza_get_content_model_object_management_definition_map($kind, $bucket) as $identifier => $definition) {
            if (!array_key_exists($identifier, $choices)) {
                continue;
            }

            if (in_array($identifier, $protected, true)) {
                $selected[$identifier] = $identifier;
                continue;
            }

            if ($kind === 'post_types' && in_array($identifier, $default_off_post_types, true)) {
                continue;
            }

            if (
                $kind === 'taxonomies'
                && (
                    in_array($identifier, $default_off_taxonomies, true)
                    || meza_is_content_model_object_management_taxonomy_default_off($definition)
                )
            ) {
                continue;
            }

            $selected[$identifier] = $identifier;
        }

        return array_values($selected);
    }

    function meza_get_content_model_reset_indexable_selection(string $kind): array
    {
        $choices = meza_get_content_model_object_management_choice_map($kind, 'indexable');
        $enabled_lookup = [];

        foreach (['custom', 'built_in', 'built_in_non_indexable'] as $bucket) {
            foreach ((array) ($_POST['acf'][meza_get_content_model_object_management_field_key($kind, $bucket)] ?? []) as $identifier) {
                $identifier = sanitize_key((string) $identifier);
                if ($identifier !== '') {
                    $enabled_lookup[$identifier] = true;
                }
            }
        }

        return array_values(array_filter(
            meza_get_content_model_reset_default_indexable_selection($kind, $choices),
            static function (string $identifier) use ($enabled_lookup): bool {
                return isset($enabled_lookup[$identifier]);
            }
        ));
    }

    function meza_get_content_model_taxonomy_term_management_field_key_prefix(): string
    {
        return 'field_meza_content_model_taxonomy_terms_';
    }

    function meza_get_content_model_taxonomy_term_management_field_name_prefix(): string
    {
        return 'content_model_taxonomy_terms_';
    }

    function meza_get_content_model_taxonomy_term_management_field_key(string $taxonomy): string
    {
        $taxonomy = sanitize_key($taxonomy);

        return $taxonomy === '' ? '' : meza_get_content_model_taxonomy_term_management_field_key_prefix() . $taxonomy;
    }

    function meza_get_content_model_taxonomy_term_management_hidden_option_name(string $taxonomy): string
    {
        $taxonomy = sanitize_key($taxonomy);

        return $taxonomy === '' ? '' : 'options_content_model_hidden_taxonomy_terms_' . $taxonomy;
    }

    function meza_get_content_model_taxonomy_term_management_rendered_input_name(string $taxonomy): string
    {
        $taxonomy = sanitize_key($taxonomy);

        return $taxonomy === '' ? '' : 'meza_content_model_rendered_taxonomy_terms[' . $taxonomy . '][]';
    }

    function meza_get_content_model_nonprofit_default_taxonomy_terms(): array
    {
        return [
            'profile-type' => [
                [
                    'name' => 'Board Member',
                    'children' => [
                        'President',
                        'Secretary',
                        'Treasurer',
                        'Vice President',
                    ],
                ],
                [
                    'name' => 'Donor',
                ],
            ],
            'organization-type' => [
                [
                    'name' => 'Partner',
                ],
                [
                    'name' => 'Venue',
                ],
            ],
        ];
    }

    function meza_seed_content_model_nonprofit_default_taxonomy_terms(string $taxonomy): void
    {
        $taxonomy = sanitize_key($taxonomy);
        if (
            $taxonomy === ''
            || !function_exists('meza_is_nonprofit_business_type')
            || !meza_is_nonprofit_business_type()
            || !taxonomy_exists($taxonomy)
        ) {
            return;
        }

        $definitions = meza_get_content_model_nonprofit_default_taxonomy_terms()[$taxonomy] ?? [];
        if ($definitions === []) {
            return;
        }

        $ensure_term = static function (string $name, string $taxonomy, int $parent_id = 0) use (&$ensure_term): int {
            $matching_terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'parent' => $parent_id,
                'name' => $name,
                'number' => 1,
                'fields' => 'all',
                'meza_include_hidden_content_model_terms' => true,
            ]);
            if (is_array($matching_terms)) {
                foreach ($matching_terms as $matching_term) {
                    if ($matching_term instanceof WP_Term && strcasecmp($matching_term->name, $name) === 0) {
                        return (int) $matching_term->term_id;
                    }
                }
            }

            $existing_term = term_exists($name, $taxonomy, $parent_id);
            if (is_array($existing_term) && !empty($existing_term['term_id'])) {
                return (int) $existing_term['term_id'];
            }

            if (is_int($existing_term) && $existing_term > 0) {
                return $existing_term;
            }

            $inserted = wp_insert_term($name, $taxonomy, $parent_id > 0 ? ['parent' => $parent_id] : []);
            if (is_wp_error($inserted) || empty($inserted['term_id'])) {
                return 0;
            }

            return (int) $inserted['term_id'];
        };

        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $name = trim((string) ($definition['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $parent_id = $ensure_term($name, $taxonomy, 0);
            if ($parent_id <= 0) {
                continue;
            }

            foreach ((array) ($definition['children'] ?? []) as $child_name) {
                $child_name = trim((string) $child_name);
                if ($child_name === '') {
                    continue;
                }

                $ensure_term($child_name, $taxonomy, $parent_id);
            }
        }
    }

    function meza_get_content_model_nonprofit_default_taxonomy_hierarchy_parent_ids(string $taxonomy): array
    {
        $taxonomy = sanitize_key($taxonomy);
        if ($taxonomy === '' || !taxonomy_exists($taxonomy) || !is_taxonomy_hierarchical($taxonomy)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'fields' => 'id=>parent',
            'get' => 'all',
            'orderby' => 'id',
            'update_term_meta_cache' => false,
            'meza_include_hidden_content_model_terms' => true,
        ]);
        if (is_wp_error($terms) || !is_array($terms)) {
            return [];
        }

        $parent_ids = [];

        foreach ($terms as $parent_id) {
            $parent_id = absint($parent_id);
            if ($parent_id <= 0) {
                continue;
            }

            $parent_ids[$parent_id] = $parent_id;
        }

        return array_values($parent_ids);
    }

    function meza_rebuild_content_model_taxonomy_hierarchy_cache(string $taxonomy): void
    {
        $taxonomy = sanitize_key($taxonomy);
        if ($taxonomy === '' || !taxonomy_exists($taxonomy) || !is_taxonomy_hierarchical($taxonomy)) {
            return;
        }

        delete_option("{$taxonomy}_children");
        _get_term_hierarchy($taxonomy);
    }

    function meza_repair_content_model_nonprofit_default_taxonomy_hierarchy_caches(): void
    {
        if (!function_exists('meza_is_nonprofit_business_type') || !meza_is_nonprofit_business_type()) {
            return;
        }

        foreach (array_keys(meza_get_content_model_nonprofit_default_taxonomy_terms()) as $taxonomy) {
            $taxonomy = sanitize_key((string) $taxonomy);
            if ($taxonomy === '' || !taxonomy_exists($taxonomy) || !is_taxonomy_hierarchical($taxonomy)) {
                continue;
            }

            meza_seed_content_model_nonprofit_default_taxonomy_terms($taxonomy);

            $expected_parent_ids = meza_get_content_model_nonprofit_default_taxonomy_hierarchy_parent_ids($taxonomy);
            if ($expected_parent_ids === []) {
                continue;
            }

            $cached_children = get_option("{$taxonomy}_children", null);
            $cached_parent_ids = [];

            if (is_array($cached_children)) {
                foreach ($cached_children as $parent_id => $child_ids) {
                    $parent_id = absint($parent_id);
                    if ($parent_id <= 0 || !is_array($child_ids) || $child_ids === []) {
                        continue;
                    }

                    $cached_parent_ids[$parent_id] = $parent_id;
                }
            }

            if (array_diff($expected_parent_ids, array_values($cached_parent_ids)) !== []) {
                meza_rebuild_content_model_taxonomy_hierarchy_cache($taxonomy);
            }
        }
    }

    function meza_normalize_content_model_taxonomy_term_ids($value): array
    {
        $normalized = [];

        foreach ((array) $value as $candidate) {
            $term_id = absint($candidate);
            if ($term_id <= 0) {
                continue;
            }

            $normalized[$term_id] = $term_id;
        }

        return array_values($normalized);
    }

    function meza_get_content_model_taxonomy_term_management_taxonomy_definitions(): array
    {
        $enabled_identifiers = array_fill_keys(
            meza_get_content_model_object_management_current_enabled_identifiers('taxonomies'),
            true
        );
        $definitions = [];
        $build_description = static function (string $taxonomy): string {
            $taxonomy_object = taxonomy_exists($taxonomy) ? get_taxonomy($taxonomy) : null;

            if ($taxonomy_object && !empty($taxonomy_object->description)) {
                return trim((string) $taxonomy_object->description);
            }

            return '';
        };

        foreach (['built_in', 'built_in_non_indexable'] as $bucket) {
            foreach (meza_get_content_model_object_management_definition_map('taxonomies', $bucket) as $identifier => $definition) {
                $identifier = sanitize_key((string) $identifier);
                if ($identifier === '' || !isset($enabled_identifiers[$identifier])) {
                    continue;
                }

                $definitions[$identifier] = [
                    'taxonomy' => $identifier,
                    'title' => meza_get_content_model_object_management_object_title('taxonomies', $definition),
                    'description' => $build_description($identifier),
                ];
            }
        }

        foreach (meza_get_content_model_object_management_custom_records('taxonomies') as $identifier => $record) {
            $identifier = sanitize_key((string) $identifier);
            if ($identifier === '' || !isset($enabled_identifiers[$identifier])) {
                continue;
            }

            $definitions[$identifier] = [
                'taxonomy' => $identifier,
                'title' => (string) ($record['title'] ?? $identifier),
                'description' => $build_description($identifier),
            ];
        }

        uasort($definitions, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });

        return $definitions;
    }

    function meza_get_content_model_taxonomy_term_management_term_choices(string $taxonomy): array
    {
        $taxonomy = sanitize_key($taxonomy);
        if ($taxonomy === '') {
            return [];
        }

        meza_seed_content_model_nonprofit_default_taxonomy_terms($taxonomy);

        $grouped_terms = meza_get_content_model_taxonomy_term_management_term_groups($taxonomy);
        if ($grouped_terms !== []) {
            $choices = [];

            foreach ($grouped_terms as $group) {
                foreach ((array) ($group['items'] ?? []) as $item) {
                    $term_id = (string) ($item['id'] ?? '');
                    $label = (string) ($item['label'] ?? '');
                    if ($term_id === '' || $label === '' || array_key_exists($term_id, $choices)) {
                        continue;
                    }

                    $choices[$term_id] = $label;
                }
            }

            if ($choices !== []) {
                return $choices;
            }
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
            'meza_include_hidden_content_model_terms' => true,
        ]);
        if (is_wp_error($terms) || !is_array($terms)) {
            return [];
        }

        $choices = [];

        foreach ($terms as $term) {
            if (!($term instanceof WP_Term)) {
                continue;
            }

            $prefix = '';
            if (is_taxonomy_hierarchical($taxonomy)) {
                $prefix = str_repeat('— ', count(get_ancestors((int) $term->term_id, $taxonomy, 'taxonomy')));
            }

            $choices[(string) $term->term_id] = $prefix . (string) $term->name;
        }

        return $choices;
    }

    function meza_get_content_model_taxonomy_term_management_term_groups(string $taxonomy): array
    {
        $taxonomy = sanitize_key($taxonomy);
        if ($taxonomy === '' || !taxonomy_exists($taxonomy) || !is_taxonomy_hierarchical($taxonomy)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
            'meza_include_hidden_content_model_terms' => true,
        ]);
        if (is_wp_error($terms) || !is_array($terms) || $terms === []) {
            return [];
        }

        $terms_by_id = [];
        $children_by_parent = [];

        foreach ($terms as $term) {
            if (!($term instanceof WP_Term)) {
                continue;
            }

            $term_id = (int) $term->term_id;
            $parent_id = (int) $term->parent;

            if ($term_id <= 0) {
                continue;
            }

            $terms_by_id[$term_id] = $term;
            $children_by_parent[$parent_id] = $children_by_parent[$parent_id] ?? [];
            $children_by_parent[$parent_id][] = $term_id;
        }

        if (($children_by_parent[0] ?? []) === []) {
            return [];
        }

        $append_term_columns = static function (int $term_id, array &$columns) use (&$append_term_columns, $terms_by_id, $children_by_parent): void {
            $term = $terms_by_id[$term_id] ?? null;
            if (!($term instanceof WP_Term)) {
                return;
            }

            $items = [
                [
                    'id' => (string) $term_id,
                    'label' => (string) $term->name,
                    'depth' => 0,
                    'is_parent' => true,
                    'parent_id' => $term->parent > 0 ? (string) $term->parent : null,
                ],
            ];

            foreach ((array) ($children_by_parent[$term_id] ?? []) as $child_id) {
                $child_term = $terms_by_id[(int) $child_id] ?? null;
                if (!($child_term instanceof WP_Term)) {
                    continue;
                }

                $items[] = [
                    'id' => (string) $child_id,
                    'label' => '— ' . (string) $child_term->name,
                    'depth' => 1,
                    'is_parent' => false,
                    'parent_id' => (string) $term_id,
                ];
            }

            $columns[] = [
                'id' => (string) $term_id,
                'title' => (string) $term->name,
                'children' => array_values(array_map('strval', array_map('intval', (array) ($children_by_parent[$term_id] ?? [])))),
                'items' => $items,
            ];

            foreach ((array) ($children_by_parent[$term_id] ?? []) as $child_id) {
                if (!empty($children_by_parent[(int) $child_id])) {
                    $append_term_columns((int) $child_id, $columns);
                }
            }
        };

        $groups = [];

        foreach ((array) ($children_by_parent[0] ?? []) as $top_level_term_id) {
            $append_term_columns((int) $top_level_term_id, $groups);
        }

        usort($groups, static function (array $left, array $right): int {
            $left_title = (string) ($left['title'] ?? '');
            $right_title = (string) ($right['title'] ?? '');

            return strcasecmp($left_title, $right_title);
        });

        return $groups;
    }

    function meza_get_content_model_taxonomy_term_management_hidden_term_ids(string $taxonomy): array
    {
        $option_name = meza_get_content_model_taxonomy_term_management_hidden_option_name($taxonomy);
        if ($option_name === '') {
            return [];
        }

        return meza_normalize_content_model_taxonomy_term_ids(get_option($option_name, []));
    }

    function meza_get_content_model_taxonomy_term_management_posted_selection(string $taxonomy, array $choices): ?array
    {
        $field_key = meza_get_content_model_taxonomy_term_management_field_key($taxonomy);
        if (
            $field_key === ''
            || !isset($_POST['acf'])
            || !is_array($_POST['acf'])
            || !array_key_exists($field_key, $_POST['acf'])
        ) {
            return null;
        }

        $allowed_ids = array_fill_keys(array_map('strval', array_keys($choices)), true);
        $selected = [];

        foreach (meza_normalize_content_model_taxonomy_term_ids(wp_unslash($_POST['acf'][$field_key])) as $term_id) {
            if (!isset($allowed_ids[(string) $term_id])) {
                continue;
            }

            $selected[$term_id] = (string) $term_id;
        }

        return array_values($selected);
    }

    function meza_get_content_model_taxonomy_term_management_rendered_term_ids(string $taxonomy, array $choices): array
    {
        $input_name = meza_get_content_model_taxonomy_term_management_rendered_input_name($taxonomy);
        if (
            $input_name === ''
            || !isset($_POST['meza_content_model_rendered_taxonomy_terms'])
            || !is_array($_POST['meza_content_model_rendered_taxonomy_terms'])
            || !array_key_exists($taxonomy, $_POST['meza_content_model_rendered_taxonomy_terms'])
        ) {
            return [];
        }

        $allowed_ids = array_fill_keys(array_map('strval', array_keys($choices)), true);
        $rendered = [];

        foreach (meza_normalize_content_model_taxonomy_term_ids(wp_unslash($_POST['meza_content_model_rendered_taxonomy_terms'][$taxonomy])) as $term_id) {
            if (!isset($allowed_ids[(string) $term_id])) {
                continue;
            }

            $rendered[$term_id] = $term_id;
        }

        return array_values($rendered);
    }

    function meza_load_content_model_taxonomy_term_management_value($value, $post_id, array $field)
    {
        unset($value, $post_id);

        $taxonomy = sanitize_key((string) ($field['meza_taxonomy'] ?? ''));
        if ($taxonomy === '') {
            return [];
        }

        $choices = (array) ($field['choices'] ?? []);
        $posted_selection = meza_get_content_model_taxonomy_term_management_posted_selection($taxonomy, $choices);
        if ($posted_selection !== null) {
            return $posted_selection;
        }

        $hidden_lookup = array_fill_keys(
            array_map('strval', meza_get_content_model_taxonomy_term_management_hidden_term_ids($taxonomy)),
            true
        );
        $selected = [];

        foreach (array_keys($choices) as $term_id) {
            $term_id = (string) $term_id;
            if ($term_id === '' || isset($hidden_lookup[$term_id])) {
                continue;
            }

            $selected[] = $term_id;
        }

        return $selected;
    }

    function meza_is_content_model_taxonomy_term_management_screen(): bool
    {
        if (!is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';

        return in_array($page, ['content-model', 'content-structure'], true);
    }

    function meza_should_filter_hidden_content_model_taxonomy_terms_in_admin(): bool
    {
        return is_admin() && !meza_is_content_model_taxonomy_term_management_screen();
    }

    function meza_sync_content_model_taxonomy_term_management_after_save($post_id): void
    {
        if (!meza_is_content_structure_acf_submission() || !in_array($post_id, ['option', 'options'], true)) {
            return;
        }

        foreach (meza_get_content_model_taxonomy_term_management_taxonomy_definitions() as $taxonomy => $definition) {
            unset($definition);

            meza_seed_content_model_nonprofit_default_taxonomy_terms($taxonomy);

            $choices = meza_get_content_model_taxonomy_term_management_term_choices($taxonomy);
            $current_term_ids = array_map('intval', array_keys($choices));
            $posted_selection = meza_get_content_model_taxonomy_term_management_posted_selection($taxonomy, $choices);
            $rendered_term_ids = array_map(
                'intval',
                meza_get_content_model_taxonomy_term_management_rendered_term_ids($taxonomy, $choices)
            );
            $selected_term_ids = $posted_selection === null
                ? $current_term_ids
                : array_map('intval', $posted_selection);

            if ($rendered_term_ids !== []) {
                $new_term_ids = array_values(array_diff($current_term_ids, $rendered_term_ids));
                if ($new_term_ids !== []) {
                    $selected_term_ids = array_values(array_unique(array_merge($selected_term_ids, $new_term_ids)));
                }
            }

            $hidden_term_ids = array_values(array_diff($current_term_ids, $selected_term_ids));

            update_option(
                meza_get_content_model_taxonomy_term_management_hidden_option_name($taxonomy),
                meza_normalize_content_model_taxonomy_term_ids($hidden_term_ids),
                false
            );
        }
    }

    function meza_should_reset_content_model_to_defaults(): bool
    {
        return meza_is_content_structure_acf_submission()
            && isset($_POST['meza_content_model_reset_defaults'])
            && wp_unslash($_POST['meza_content_model_reset_defaults']) === '1';
    }

    function meza_should_block_site_manager_content_model_updates(): bool
    {
        return is_admin()
            && meza_is_content_structure_acf_submission()
            && function_exists('meza_is_content_model_read_only_for_current_user')
            && meza_is_content_model_read_only_for_current_user()
            && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST'
            && (
                (isset($_POST['acf']) && is_array($_POST['acf']))
                || isset($_POST['meza_content_model_reset_defaults'])
            );
    }

    function meza_get_content_model_read_only_redirect_url(): string
    {
        $target = function_exists('meza_get_shared_project_acf_options_page_menu_slug')
            ? meza_get_shared_project_acf_options_page_menu_slug('content-model')
            : 'admin.php?page=content-model';

        return add_query_arg('meza_content_model_read_only', '1', admin_url($target));
    }

    function meza_apply_content_model_reset_defaults_to_posted_acf(): void
    {
        if (
            !meza_should_reset_content_model_to_defaults()
            || !isset($_POST['acf'])
            || !is_array($_POST['acf'])
        ) {
            return;
        }

        $_POST['acf'][meza_get_content_model_field_group_management_field_key_map()['custom']] = meza_get_content_model_reset_field_group_selection('custom');
        $_POST['acf'][meza_get_content_model_field_group_management_field_key_map()['defaults']] = meza_get_content_model_reset_field_group_selection('defaults');
        $_POST['acf'][meza_get_content_model_field_group_management_field_key_map()['built_in']] = meza_get_content_model_reset_field_group_selection('built_in');

        $_POST['acf'][meza_get_content_model_object_management_field_key('post_types', 'custom')] = meza_get_content_model_reset_object_selection('post_types', 'custom');
        $_POST['acf'][meza_get_content_model_object_management_field_key('post_types', 'built_in')] = meza_get_content_model_reset_object_selection('post_types', 'built_in');
        $_POST['acf'][meza_get_content_model_object_management_field_key('post_types', 'built_in_non_indexable')] = meza_get_content_model_reset_object_selection('post_types', 'built_in_non_indexable');

        $_POST['acf'][meza_get_content_model_object_management_field_key('taxonomies', 'custom')] = meza_get_content_model_reset_object_selection('taxonomies', 'custom');
        $_POST['acf'][meza_get_content_model_object_management_field_key('taxonomies', 'built_in')] = meza_get_content_model_reset_object_selection('taxonomies', 'built_in');
        $_POST['acf'][meza_get_content_model_object_management_field_key('taxonomies', 'built_in_non_indexable')] = meza_get_content_model_reset_object_selection('taxonomies', 'built_in_non_indexable');

        $_POST['acf'][meza_get_content_model_object_management_field_key('options_pages', 'custom')] = meza_get_content_model_reset_object_selection('options_pages', 'custom');
        $_POST['acf'][meza_get_content_model_object_management_field_key('options_pages', 'built_in')] = meza_get_content_model_reset_object_selection('options_pages', 'built_in');

        $_POST['acf'][meza_get_content_model_object_management_field_key('post_types', 'indexable')] = meza_get_content_model_reset_indexable_selection('post_types');
        $_POST['acf'][meza_get_content_model_object_management_field_key('taxonomies', 'indexable')] = meza_get_content_model_reset_indexable_selection('taxonomies');

        foreach (meza_get_content_model_taxonomy_term_management_taxonomy_definitions() as $taxonomy => $definition) {
            unset($definition);

            $_POST['acf'][meza_get_content_model_taxonomy_term_management_field_key($taxonomy)] = array_keys(
                meza_get_content_model_taxonomy_term_management_term_choices($taxonomy)
            );
        }

        $_POST['acf']['field_meza_event_after_content'] = 0;
        $_POST['acf']['field_meza_enable_season_after_content'] = 0;
    }

    function meza_prepare_content_model_object_management_field(array $field, string $kind, string $bucket): array
    {
        $choices = meza_get_content_model_object_management_choice_map($kind, $bucket);

        foreach ($choices as $identifier => $label) {
            $note = trim((string) (meza_get_content_model_object_management_behavior($kind, (string) $identifier)['ui_note'] ?? ''));
            if ($note === '') {
                continue;
            }

            $choices[$identifier] = sprintf('%s (%s)', $label, $note);
        }

        $field['choices'] = $choices;

        return $field;
    }

    function meza_prepare_content_model_taxonomy_term_management_field(array $field): array
    {
        $taxonomy = sanitize_key((string) ($field['meza_taxonomy'] ?? ''));
        if ($taxonomy === '') {
            return $field;
        }

        $field['choices'] = meza_get_content_model_taxonomy_term_management_term_choices($taxonomy);

        return $field;
    }

    function meza_get_taxonomy_term_ancestor_ids(int $term_id, string $taxonomy): array
    {
        $ancestor_ids = [];
        $parent_id = (int) wp_get_term_taxonomy_parent_id($term_id, $taxonomy);

        while ($parent_id > 0) {
            $ancestor_ids[] = $parent_id;

            $parent_term = get_term($parent_id, $taxonomy);
            if (!$parent_term || is_wp_error($parent_term)) {
                break;
            }

            $parent_id = (int) $parent_term->parent;
        }

        return array_values(array_unique(array_filter($ancestor_ids)));
    }

    function meza_sync_profile_type_term_ancestors_for_post(int $post_id): array
    {
        if ($post_id <= 0 || get_post_type($post_id) !== 'profile' || !taxonomy_exists('profile-type')) {
            return [];
        }

        $term_ids = wp_get_object_terms($post_id, 'profile-type', ['fields' => 'ids']);
        if (is_wp_error($term_ids) || $term_ids === []) {
            return [];
        }

        $term_ids = array_values(array_unique(array_map('intval', $term_ids)));
        $missing_ancestor_ids = [];

        foreach ($term_ids as $term_id) {
            foreach (meza_get_taxonomy_term_ancestor_ids($term_id, 'profile-type') as $ancestor_id) {
                if (!in_array($ancestor_id, $term_ids, true)) {
                    $missing_ancestor_ids[] = $ancestor_id;
                }
            }
        }

        $missing_ancestor_ids = array_values(array_unique(array_map('intval', $missing_ancestor_ids)));
        if ($missing_ancestor_ids === []) {
            return [];
        }

        $updated_term_ids = array_values(array_unique(array_merge($term_ids, $missing_ancestor_ids)));
        $result = wp_set_object_terms($post_id, $updated_term_ids, 'profile-type', false);

        return is_wp_error($result) ? [] : $missing_ancestor_ids;
    }

    function meza_backfill_profile_type_term_ancestors(array $post_ids = []): array
    {
        if ($post_ids === []) {
            $post_ids = get_posts([
                'fields'         => 'ids',
                'numberposts'    => -1,
                'post_status'    => 'any',
                'post_type'      => 'profile',
                'suppress_filters' => false,
            ]);
        }

        $updated_posts = [];

        foreach ($post_ids as $post_id) {
            $added_term_ids = meza_sync_profile_type_term_ancestors_for_post((int) $post_id);
            if ($added_term_ids !== []) {
                $updated_posts[(int) $post_id] = $added_term_ids;
            }
        }

        return $updated_posts;
    }

    function meza_sync_profile_type_term_ancestors_after_terms_set($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids): void
    {
        unset($terms, $tt_ids, $append, $old_tt_ids);

        if ($taxonomy !== 'profile-type' || get_post_type((int) $object_id) !== 'profile') {
            return;
        }

        static $syncing_post_ids = [];
        $object_id = (int) $object_id;

        if (isset($syncing_post_ids[$object_id])) {
            return;
        }

        $syncing_post_ids[$object_id] = true;
        meza_sync_profile_type_term_ancestors_for_post($object_id);
        unset($syncing_post_ids[$object_id]);
    }

    function meza_load_content_model_object_management_value($value, $post_id, array $field, string $kind, string $bucket)
    {
        unset($value, $post_id);

        return meza_get_content_model_object_management_current_selection(
            $kind,
            $bucket,
            (array) ($field['choices'] ?? [])
        );
    }

    function meza_sync_content_model_object_management_custom_selection(string $kind, array $selected_identifiers): void
    {
        $config = meza_get_content_model_object_management_configs()[$kind] ?? [];
        $internal_post_type = (string) ($config['internal_post_type'] ?? '');
        if ($internal_post_type === '' || !function_exists('acf_update_internal_post_type_active_status')) {
            return;
        }

        foreach (meza_get_content_model_object_management_custom_records($kind) as $identifier => $record) {
            $object_id = (int) ($record['ID'] ?? 0);
            $status = (string) ($record['status'] ?? '');
            if ($object_id <= 0) {
                continue;
            }

            if (in_array($identifier, $selected_identifiers, true)) {
                if ($status === 'acf-disabled') {
                    acf_update_internal_post_type_active_status($object_id, true, $internal_post_type);
                }

                continue;
            }

            if ($status === 'publish') {
                acf_update_internal_post_type_active_status($object_id, false, $internal_post_type);
            }
        }
    }

    function meza_sync_content_model_object_management_managed_selection(string $kind, string $bucket, array $selected_identifiers): void
    {
        $protected = in_array($bucket, ['built_in', 'built_in_non_indexable'], true)
            ? meza_get_content_model_object_management_protected_built_in_identifiers($kind)
            : [];

        foreach (meza_get_content_model_object_management_definition_map($kind, $bucket) as $identifier => $definition) {
            $is_selected = in_array($identifier, $protected, true) || in_array($identifier, $selected_identifiers, true);
            $legacy_state = meza_get_content_model_object_management_legacy_option_definition($kind, $identifier, 'enabled');
            if ($legacy_state !== []) {
                meza_set_content_model_object_management_legacy_state($kind, $identifier, 'enabled', $is_selected);
            }

            if (!meza_content_model_object_management_supports_runtime_toggle($kind, $identifier)) {
                continue;
            }

            if ($is_selected) {
                meza_clear_managed_acf_definition_deleted($kind, $definition);
                continue;
            }

            meza_mark_managed_acf_definition_deleted($kind, $definition);
        }
    }

    function meza_sync_content_model_object_management_dependent_taxonomy_selection(): void
    {
        $required_identifiers = meza_get_content_model_object_management_required_built_in_taxonomy_identifiers();

        foreach (meza_get_content_model_object_management_dependent_built_in_taxonomy_definitions() as $identifier => $definition) {
            if (in_array($identifier, $required_identifiers, true)) {
                meza_clear_managed_acf_definition_deleted('taxonomies', $definition);
                continue;
            }

            meza_mark_managed_acf_definition_deleted('taxonomies', $definition);
        }
    }

    function meza_sync_content_model_object_management_indexable_selection(string $kind, array $selected_identifiers): void
    {
        $enabled_identifiers = [];
        $custom_permalinks_capable_identifiers = array_fill_keys(
            meza_get_content_model_object_management_custom_locked_indexable_identifiers($kind),
            false
        );

        foreach (['custom', 'built_in', 'built_in_non_indexable'] as $bucket) {
            $choices = meza_get_content_model_object_management_choice_map($kind, $bucket);
            $enabled_identifiers = array_merge(
                $enabled_identifiers,
                meza_get_content_model_object_management_posted_selection($kind, $bucket, $choices) ?? []
            );
        }

        $enabled_identifiers = array_values(array_unique(array_map('sanitize_key', $enabled_identifiers)));
        $enabled_lookup = array_fill_keys($enabled_identifiers, true);

        $selected_identifiers = array_values(array_filter(
            $selected_identifiers,
            static function (string $identifier) use ($kind, $enabled_lookup, $custom_permalinks_capable_identifiers): bool {
                return isset($enabled_lookup[$identifier])
                    && !array_key_exists($identifier, $custom_permalinks_capable_identifiers)
                    && !in_array($identifier, meza_get_content_model_object_management_non_indexable_identifiers($kind), true);
            }
        ));

        foreach (meza_get_content_model_object_management_protected_indexable_identifiers($kind) as $identifier) {
            if (!in_array($identifier, $selected_identifiers, true)) {
                $selected_identifiers[] = $identifier;
            }
        }

        foreach (meza_get_content_model_object_management_indexable_identifiers($kind) as $identifier) {
            meza_set_content_model_object_management_legacy_state(
                $kind,
                $identifier,
                'indexable',
                in_array($identifier, $selected_identifiers, true)
            );
        }
    }

    function meza_auto_enable_list_events_default_field_group_for_event_selection(array $previous_enabled_post_types): void
    {
        if (
            !function_exists('meza_get_content_model_object_management_current_enabled_identifiers')
            || !function_exists('meza_get_content_model_field_group_management_definition_map')
            || !function_exists('meza_clear_managed_acf_definition_deleted')
            || !function_exists('meza_get_existing_editable_acf_field_group_id')
            || !function_exists('meza_get_disabled_editable_acf_field_group_id')
            || !function_exists('meza_enable_editable_acf_field_group_definition')
            || !function_exists('meza_is_editable_acf_field_group_auto_disabled')
            || !function_exists('meza_clear_editable_acf_field_group_auto_disabled')
        ) {
            return;
        }

        $previous_enabled_post_types = array_values(array_unique(array_map('sanitize_key', $previous_enabled_post_types)));
        $current_enabled_post_types = array_values(array_unique(array_map(
            'sanitize_key',
            meza_get_content_model_object_management_current_enabled_identifiers('post_types')
        )));

        if (
            in_array('event', $previous_enabled_post_types, true)
            || !in_array('event', $current_enabled_post_types, true)
        ) {
            return;
        }

        $definition = meza_get_content_model_field_group_management_definition_map('defaults')['group_meza_list_events_section'] ?? [];
        if (!is_array($definition) || $definition === []) {
            return;
        }

        meza_clear_managed_acf_definition_deleted('field_groups', $definition);

        if (!meza_is_editable_acf_field_group_auto_disabled($definition)) {
            return;
        }

        if (meza_get_disabled_editable_acf_field_group_id($definition) > 0) {
            meza_enable_editable_acf_field_group_definition($definition);
            return;
        }

        if (meza_get_existing_editable_acf_field_group_id($definition) > 0) {
            meza_clear_editable_acf_field_group_auto_disabled($definition);
        }
    }

    function meza_sync_content_model_object_management_after_save($post_id): void
    {
        if (!meza_is_content_structure_acf_submission() || !in_array($post_id, ['option', 'options'], true)) {
            return;
        }

        $previous_enabled_post_types = meza_get_content_model_object_management_current_enabled_identifiers('post_types');

        foreach (meza_get_content_model_object_management_configs() as $kind => $config) {
            foreach ((array) ($config['buckets'] ?? []) as $bucket => $field_key) {
                unset($field_key);
                $choices = meza_get_content_model_object_management_choice_map($kind, $bucket);
                $selected_identifiers = meza_get_content_model_object_management_posted_selection($kind, $bucket, $choices) ?? [];
                meza_set_content_model_object_management_saved_panel_selection($kind, $bucket, $selected_identifiers);

                if ($bucket === 'custom') {
                    meza_sync_content_model_object_management_custom_selection($kind, $selected_identifiers);
                    continue;
                }

                meza_sync_content_model_object_management_managed_selection($kind, $bucket, $selected_identifiers);
            }

            $indexable_field_key = sanitize_key((string) ($config['indexable_field_key'] ?? ''));
            if ($indexable_field_key !== '') {
                $indexable_choices = meza_get_content_model_object_management_choice_map($kind, 'indexable');
                $selected_indexables = meza_get_content_model_object_management_posted_selection(
                    $kind,
                    'indexable',
                    $indexable_choices
                ) ?? [];
                meza_set_content_model_object_management_saved_panel_selection($kind, 'indexable', $selected_indexables);

                meza_sync_content_model_object_management_indexable_selection($kind, $selected_indexables);
            }
        }

        meza_sync_content_model_object_management_dependent_taxonomy_selection();
        meza_auto_enable_list_events_default_field_group_for_event_selection($previous_enabled_post_types);
    }

    function meza_reconcile_content_model_object_management_saved_selection(): void
    {
        if (meza_is_content_structure_acf_submission()) {
            return;
        }

        foreach (meza_get_content_model_object_management_configs() as $kind => $config) {
            foreach ((array) ($config['buckets'] ?? []) as $bucket => $field_key) {
                unset($field_key);

                if ($bucket === 'custom') {
                    continue;
                }

                $choices = meza_get_content_model_object_management_choice_map($kind, $bucket);
                $saved_selection = meza_get_content_model_object_management_saved_panel_selection($kind, $bucket, $choices);
                if ($saved_selection === null) {
                    continue;
                }

                meza_sync_content_model_object_management_managed_selection($kind, $bucket, $saved_selection);
            }
        }

        meza_sync_content_model_object_management_dependent_taxonomy_selection();
    }
}

if (!function_exists('meza_disable_content_model_custom_post_type_permalinks')) {
    function meza_disable_content_model_custom_post_type_permalinks(array $args, array $post): array
    {
        if (
            !meza_is_content_model_object_management_custom_definition('post_types', $post)
            || !meza_is_content_model_object_management_permalink_capable('post_types', $post)
        ) {
            return $args;
        }

        $identifier = sanitize_key((string) ($post['post_type'] ?? ''));
        if ($identifier === '') {
            return $args;
        }

        $saved_selection = meza_get_content_model_object_management_saved_panel_selection(
            'post_types',
            'indexable',
            meza_get_content_model_object_management_choice_map('post_types', 'indexable')
        );
        if (!is_array($saved_selection) || in_array($identifier, $saved_selection, true)) {
            return $args;
        }

        $args['rewrite'] = false;
        $args['has_archive'] = false;
        $args['publicly_queryable'] = false;
        $args['query_var'] = false;
        $args['exclude_from_search'] = true;

        return $args;
    }
}
add_filter('acf/post_type/registration_args', 'meza_disable_content_model_custom_post_type_permalinks', 20, 2);

if (!function_exists('meza_disable_content_model_custom_taxonomy_permalinks')) {
    function meza_disable_content_model_custom_taxonomy_permalinks(array $args, array $taxonomy): array
    {
        if (
            !meza_is_content_model_object_management_custom_definition('taxonomies', $taxonomy)
            || !meza_is_content_model_object_management_permalink_capable('taxonomies', $taxonomy)
        ) {
            return $args;
        }

        $identifier = sanitize_key((string) ($taxonomy['taxonomy'] ?? ''));
        if ($identifier === '') {
            return $args;
        }

        $saved_selection = meza_get_content_model_object_management_saved_panel_selection(
            'taxonomies',
            'indexable',
            meza_get_content_model_object_management_choice_map('taxonomies', 'indexable')
        );
        if (!is_array($saved_selection) || in_array($identifier, $saved_selection, true)) {
            return $args;
        }

        $args['rewrite'] = false;
        $args['publicly_queryable'] = false;
        $args['query_var'] = false;

        return $args;
    }
}
add_filter('acf/taxonomy/registration_args', 'meza_disable_content_model_custom_taxonomy_permalinks', 20, 2);

add_action('acf/validate_save_post', 'meza_apply_content_model_reset_defaults_to_posted_acf', 5);

add_action('admin_init', static function (): void {
    if (!meza_should_block_site_manager_content_model_updates()) {
        return;
    }

    wp_safe_redirect(meza_get_content_model_read_only_redirect_url());
    exit;
}, 1);

add_action('admin_notices', static function (): void {
    if (
        !is_admin()
        || !function_exists('meza_is_content_model_read_only_for_current_user')
        || !meza_is_content_model_read_only_for_current_user()
    ) {
        return;
    }

    if (!isset($_GET['meza_content_model_read_only']) || wp_unslash($_GET['meza_content_model_read_only']) !== '1') {
        return;
    }

    echo '<div class="notice notice-info"><p>'
        . esc_html__('Content Model is view-only for site managers.')
        . '</p></div>';
});

add_filter('acf/load_value/key=field_meza_content_model_custom_field_groups', static function ($value, $post_id, $field) {
    return is_array($field)
        ? meza_load_content_model_field_group_management_value($value, $post_id, $field, 'custom')
        : $value;
}, 10, 3);

add_filter('acf/load_value/key=field_meza_content_model_default_field_groups', static function ($value, $post_id, $field) {
    return is_array($field)
        ? meza_load_content_model_field_group_management_value($value, $post_id, $field, 'defaults')
        : $value;
}, 10, 3);

add_filter('acf/load_value/key=field_meza_content_model_built_in_field_groups', static function ($value, $post_id, $field) {
    return is_array($field)
        ? meza_load_content_model_field_group_management_value($value, $post_id, $field, 'built_in')
        : $value;
}, 10, 3);

foreach (meza_get_content_model_object_management_field_key_map() as $field_key => $configuration) {
    add_filter('acf/load_field/key=' . $field_key, static function ($field) use ($configuration) {
        return is_array($field)
            ? meza_prepare_content_model_object_management_field($field, (string) $configuration['kind'], (string) $configuration['bucket'])
            : $field;
    });

    add_filter('acf/load_value/key=' . $field_key, static function ($value, $post_id, $field) use ($configuration) {
        return is_array($field)
            ? meza_load_content_model_object_management_value($value, $post_id, $field, (string) $configuration['kind'], (string) $configuration['bucket'])
            : $value;
    }, 10, 3);
}

add_filter('acf/load_field', static function ($field) {
    if (
        !is_array($field)
        || !str_starts_with((string) ($field['key'] ?? ''), meza_get_content_model_taxonomy_term_management_field_key_prefix())
    ) {
        return $field;
    }

    return meza_prepare_content_model_taxonomy_term_management_field($field);
});

add_filter('acf/load_value', static function ($value, $post_id, $field) {
    if (
        !is_array($field)
        || !str_starts_with((string) ($field['key'] ?? ''), meza_get_content_model_taxonomy_term_management_field_key_prefix())
    ) {
        return $value;
    }

    return meza_load_content_model_taxonomy_term_management_value($value, $post_id, $field);
}, 10, 3);

add_action('acf/save_post', 'meza_sync_content_model_field_group_management_after_save', 19);
add_action('acf/save_post', 'meza_sync_content_model_object_management_after_save', 19);
add_action('acf/save_post', 'meza_sync_content_model_taxonomy_term_management_after_save', 19);
add_action('set_object_terms', 'meza_sync_profile_type_term_ancestors_after_terms_set', 20, 6);
add_action('init', 'meza_reconcile_content_model_object_management_saved_selection', 28);
add_action('init', 'meza_repair_content_model_nonprofit_default_taxonomy_hierarchy_caches', 29);

add_filter('get_terms_args', static function (array $args, array $taxonomies): array {
    if (
        !$taxonomies
        || !meza_should_filter_hidden_content_model_taxonomy_terms_in_admin()
        || !empty($args['meza_include_hidden_content_model_terms'])
    ) {
        return $args;
    }

    $exclude = meza_normalize_content_model_taxonomy_term_ids($args['exclude'] ?? []);

    foreach ($taxonomies as $taxonomy) {
        $taxonomy = sanitize_key((string) $taxonomy);
        if ($taxonomy === '') {
            continue;
        }

        $exclude = array_merge($exclude, meza_get_content_model_taxonomy_term_management_hidden_term_ids($taxonomy));
    }

    if ($exclude !== []) {
        $args['exclude'] = meza_normalize_content_model_taxonomy_term_ids($exclude);
    }

    return $args;
}, 20, 2);

add_action('admin_init', static function (): void {
    if (!meza_should_filter_hidden_content_model_taxonomy_terms_in_admin()) {
        return;
    }

    $taxonomy = isset($_GET['taxonomy']) ? sanitize_key((string) wp_unslash($_GET['taxonomy'])) : '';
    $term_id = isset($_GET['tag_ID'])
        ? absint(wp_unslash($_GET['tag_ID']))
        : (isset($_GET['term_ID']) ? absint(wp_unslash($_GET['term_ID'])) : 0);

    if ($taxonomy === '' || $term_id <= 0) {
        return;
    }

    if (!in_array($term_id, meza_get_content_model_taxonomy_term_management_hidden_term_ids($taxonomy), true)) {
        return;
    }

    $redirect_url = add_query_arg(
        array_filter([
            'taxonomy' => $taxonomy,
            'post_type' => isset($_GET['post_type']) ? sanitize_key((string) wp_unslash($_GET['post_type'])) : '',
        ]),
        admin_url('edit-tags.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}, 5);

if (!function_exists('meza_refresh_seeded_acf_field_groups_after_business_information_save')) {
    function meza_refresh_seeded_acf_field_groups_after_business_information_save($post_id): void
    {
        if (
            (
                !meza_is_business_information_acf_submission()
                && !meza_is_content_structure_acf_submission()
            )
            || !in_array($post_id, ['option', 'options'], true)
        ) {
            return;
        }

        meza_reconcile_conditional_editable_acf_field_groups();
        meza_seed_default_editable_acf_field_groups();
    }
}
add_action('acf/save_post', 'meza_refresh_seeded_acf_field_groups_after_business_information_save', 20);

if (!function_exists('meza_field_group_locations_include_event_post_type')) {
    function meza_field_group_locations_include_event_post_type(array $field_group): bool
    {
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
}

if (!function_exists('meza_get_acf_field_group_fields_identifier')) {
    function meza_get_acf_field_group_fields_identifier(array $field_group)
    {
        $field_group_id = (int) ($field_group['ID'] ?? ($field_group['id'] ?? 0));
        if ($field_group_id > 0) {
            return $field_group_id;
        }

        $field_group_key = (string) ($field_group['key'] ?? '');
        if ($field_group_key !== '') {
            return $field_group_key;
        }

        return null;
    }
}

if (!function_exists('meza_normalize_event_context_fields_for_migration')) {
    function meza_normalize_event_context_fields_for_migration(array $field_group, array $fields): array
    {
        $group_key = sanitize_key((string) ($field_group['key'] ?? ''));
        $event_group_key = 'group_6a08b62b10548';

        if (function_exists('meza_field_group_has_section_fields') && meza_field_group_has_section_fields($fields)) {
            foreach ($fields as $field_index => $field) {
                $field_name = sanitize_key((string) ($field['name'] ?? ''));
                if (!is_array($field) || (string) ($field['type'] ?? '') !== 'group' || !str_starts_with($field_name, 'section_')) {
                    continue;
                }

                $sub_fields = isset($field['sub_fields']) && is_array($field['sub_fields']) ? $field['sub_fields'] : [];
                $fields[$field_index]['sub_fields'] = meza_normalize_section_sub_fields(
                    $sub_fields,
                    $field_name,
                    $group_key,
                    true
                );
            }

            $fields = meza_normalize_event_context_child_fields($fields, true, $group_key);

            foreach ($fields as $field_index => $field) {
                $field_name = sanitize_key((string) ($field['name'] ?? ''));
                if (!is_array($field) || (string) ($field['type'] ?? '') !== 'group' || !str_starts_with($field_name, 'section_')) {
                    continue;
                }

                $sub_fields = isset($field['sub_fields']) && is_array($field['sub_fields']) ? $field['sub_fields'] : [];
                $fields[$field_index]['sub_fields'] = meza_apply_section_display_dependency($sub_fields);
            }

            return $fields;
        }

        return meza_normalize_event_context_child_fields(
            $fields,
            true,
            $group_key === '' ? $event_group_key : $group_key
        );
    }
}

if (!function_exists('meza_normalize_season_context_fields_for_migration')) {
    function meza_normalize_season_context_fields_for_migration(array $field_group, array $fields): array
    {
        $group_key = sanitize_key((string) ($field_group['key'] ?? ''));

        if (
            function_exists('meza_field_group_has_section_fields')
            && meza_field_group_has_section_fields($fields)
        ) {
            foreach ($fields as $field_index => $field) {
                $field_name = sanitize_key((string) ($field['name'] ?? ''));
                if (!is_array($field) || (string) ($field['type'] ?? '') !== 'group' || !str_starts_with($field_name, 'section_')) {
                    continue;
                }

                $sub_fields = isset($field['sub_fields']) && is_array($field['sub_fields']) ? $field['sub_fields'] : [];
                $fields[$field_index]['sub_fields'] = meza_normalize_section_sub_fields(
                    $sub_fields,
                    $field_name,
                    $group_key,
                    true
                );
            }

            $fields = meza_normalize_event_context_child_fields($fields, function_exists('meza_events_have_after_content') && meza_events_have_after_content(), $group_key);
            $fields = meza_normalize_season_context_child_fields($fields, true, $group_key);

            foreach ($fields as $field_index => $field) {
                $field_name = sanitize_key((string) ($field['name'] ?? ''));
                if (!is_array($field) || (string) ($field['type'] ?? '') !== 'group' || !str_starts_with($field_name, 'section_')) {
                    continue;
                }

                $sub_fields = isset($field['sub_fields']) && is_array($field['sub_fields']) ? $field['sub_fields'] : [];
                $fields[$field_index]['sub_fields'] = meza_apply_section_display_dependency($sub_fields);
            }

            return $fields;
        }

        $fields = meza_normalize_event_context_child_fields(
            $fields,
            function_exists('meza_events_have_after_content') && meza_events_have_after_content(),
            $group_key
        );

        return meza_normalize_season_context_child_fields($fields, true, $group_key);
    }
}

if (!function_exists('meza_parse_event_context_grouped_payload')) {
    function meza_parse_event_context_grouped_payload($value): ?array
    {
        if (is_string($value)) {
            $trimmed_value = trim($value);
            if ($trimmed_value !== '' && ($trimmed_value[0] === '{' || $trimmed_value[0] === '[')) {
                $decoded_value = json_decode($trimmed_value, true);
                if (is_array($decoded_value)) {
                    $value = $decoded_value;
                }
            } else {
                $maybe_unserialized = maybe_unserialize($value);
                if (is_array($maybe_unserialized)) {
                    $value = $maybe_unserialized;
                }
            }
        }

        if (!is_array($value) || (!array_key_exists('before', $value) && !array_key_exists('after', $value))) {
            return null;
        }

        return [
            'before' => $value['before'] ?? null,
            'after' => $value['after'] ?? null,
            '__matched_slots' => [
                'before' => array_key_exists('before', $value),
                'after' => array_key_exists('after', $value),
            ],
        ];
    }
}

if (!function_exists('meza_extract_event_context_grouped_payload_from_field')) {
    function meza_extract_event_context_grouped_payload_from_field($value, array $field): ?array
    {
        if (is_string($value)) {
            $trimmed_value = trim($value);
            if ($trimmed_value !== '' && ($trimmed_value[0] === '{' || $trimmed_value[0] === '[')) {
                $decoded_value = json_decode($trimmed_value, true);
                if (is_array($decoded_value)) {
                    $value = $decoded_value;
                }
            } else {
                $maybe_unserialized = maybe_unserialize($value);
                if (is_array($maybe_unserialized)) {
                    $value = $maybe_unserialized;
                }
            }
        }

        if (!is_array($value)) {
            return null;
        }

        $grouped_payload = function_exists('meza_parse_event_context_grouped_payload')
            ? meza_parse_event_context_grouped_payload($value)
            : null;
        if (is_array($grouped_payload)) {
            return $grouped_payload;
        }

        if (!function_exists('meza_get_event_context_nested_sub_field')) {
            return null;
        }

        $before_field = meza_get_event_context_nested_sub_field($field, 'before');
        $after_field = meza_get_event_context_nested_sub_field($field, 'after');
        $before_value = null;
        $after_value = null;
        $before_name = '';
        $before_key = '';
        $after_name = '';
        $after_key = '';
        $matched = false;

        if (is_array($before_field)) {
            $before_name = sanitize_key((string) ($before_field['name'] ?? ''));
            $before_key = (string) ($before_field['key'] ?? '');

            if ($before_name !== '' && array_key_exists($before_name, $value)) {
                $before_value = $value[$before_name];
                $matched = true;
            } elseif ($before_key !== '' && array_key_exists($before_key, $value)) {
                $before_value = $value[$before_key];
                $matched = true;
            }
        }

        if (is_array($after_field)) {
            $after_name = sanitize_key((string) ($after_field['name'] ?? ''));
            $after_key = (string) ($after_field['key'] ?? '');

            if ($after_name !== '' && array_key_exists($after_name, $value)) {
                $after_value = $value[$after_name];
                $matched = true;
            } elseif ($after_key !== '' && array_key_exists($after_key, $value)) {
                $after_value = $value[$after_key];
                $matched = true;
            }
        }

        if (!$matched) {
            return null;
        }

        return [
            'before' => $before_value,
            'after' => $after_value,
            '__matched_slots' => [
                'before' => is_array($before_field) && (
                    ($before_name !== '' && array_key_exists($before_name, $value))
                    || ($before_key !== '' && array_key_exists($before_key, $value))
                ),
                'after' => is_array($after_field) && (
                    ($after_name !== '' && array_key_exists($after_name, $value))
                    || ($after_key !== '' && array_key_exists($after_key, $value))
                ),
            ],
        ];
    }
}

if (!function_exists('meza_normalize_context_meta_candidate')) {
    function meza_normalize_context_meta_candidate($value, string $field_type = '')
    {
        $field_type = sanitize_key($field_type);

        if ($field_type === 'link' && function_exists('meza_normalize_event_context_link_field_value')) {
            return meza_normalize_event_context_link_field_value($value);
        }

        if ($field_type === 'relationship' && function_exists('meza_normalize_event_context_relationship_field_value')) {
            return meza_normalize_event_context_relationship_field_value($value);
        }

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
                return meza_normalize_context_meta_candidate($value[$key], $field_type);
            }
        }

        foreach ($value as $nested_value) {
            $normalized_value = meza_normalize_context_meta_candidate($nested_value, $field_type);
            if (
                !function_exists('meza_section_field_value_is_empty')
                || !meza_section_field_value_is_empty($normalized_value)
            ) {
                return $normalized_value;
            }
        }

        return '';
    }
}

if (!function_exists('meza_update_event_context_meta_reference')) {
    function meza_update_event_context_meta_reference(int $post_id, string $meta_key, string $field_key): void
    {
        if ($post_id <= 0 || $meta_key === '' || $field_key === '') {
            return;
        }

        update_post_meta($post_id, '_' . $meta_key, $field_key);
    }
}

if (!function_exists('meza_context_meta_values_differ')) {
    function meza_context_meta_values_differ($left, $right): bool
    {
        return maybe_serialize($left) !== maybe_serialize($right);
    }
}

if (!function_exists('meza_get_context_value_field_type')) {
    function meza_get_context_value_field_type(array $field, array $candidate_fields = []): string
    {
        $field_type = sanitize_key((string) ($field['type'] ?? ''));
        if ($field_type !== 'group') {
            return $field_type;
        }

        foreach ($candidate_fields as $candidate_field) {
            if (!is_array($candidate_field)) {
                continue;
            }

            $candidate_type = sanitize_key((string) ($candidate_field['type'] ?? ''));
            if ($candidate_type !== '' && $candidate_type !== 'group') {
                return $candidate_type;
            }
        }

        return $field_type;
    }
}

if (!function_exists('meza_migrate_event_after_content_field_meta')) {
    function meza_migrate_event_after_content_field_meta(
        int $post_id,
        array $field,
        array $path_segments,
        bool $force_before_sync = false
    ): void
    {
        if (!function_exists('meza_is_event_context_group_field')) {
            return;
        }

        if (meza_is_event_context_group_field($field)) {
            $normalized_segments = array_values(array_filter(array_map(static function ($segment): string {
                return sanitize_key((string) $segment);
            }, $path_segments), static function (string $segment): bool {
                return $segment !== '';
            }));

            if ($normalized_segments === []) {
                return;
            }

            $base_meta_key = implode('_', $normalized_segments);
            $before_meta_key = $base_meta_key . '_before';
            $after_meta_key = $base_meta_key . '_after';
            $before_field = meza_get_event_context_nested_sub_field($field, 'before');
            $after_field = meza_get_event_context_nested_sub_field($field, 'after');
            $field_type = meza_get_context_value_field_type($field, [$before_field, $after_field]);
            $base_value = metadata_exists('post', $post_id, $base_meta_key)
                ? get_post_meta($post_id, $base_meta_key, true)
                : null;
            $grouped_payload = function_exists('meza_extract_event_context_grouped_payload_from_field')
                ? meza_extract_event_context_grouped_payload_from_field($base_value, $field)
                : meza_parse_event_context_grouped_payload($base_value);
            $matched_slots = is_array($grouped_payload['__matched_slots'] ?? null)
                ? $grouped_payload['__matched_slots']
                : [];
            $before_slot_was_posted = !empty($matched_slots['before']);
            $after_slot_was_posted = !empty($matched_slots['after']);
            $before_candidate = $grouped_payload['before'] ?? $base_value;
            $after_candidate = $grouped_payload['after'] ?? null;

            $before_slot_is_explicitly_empty = $before_slot_was_posted
                && (
                    (function_exists('meza_section_field_value_is_empty') && meza_section_field_value_is_empty($before_candidate))
                    || $before_candidate === null
                );
            $after_slot_is_explicitly_empty = $after_slot_was_posted
                && (
                    (function_exists('meza_section_field_value_is_empty') && meza_section_field_value_is_empty($after_candidate))
                    || $after_candidate === null
                );

            if ($grouped_payload === null && metadata_exists('post', $post_id, $base_meta_key . '_after')) {
                $after_candidate = get_post_meta($post_id, $base_meta_key . '_after', true);
            }

            $stored_before_value = metadata_exists('post', $post_id, $before_meta_key)
                ? get_post_meta($post_id, $before_meta_key, true)
                : null;
            $stored_after_value = metadata_exists('post', $post_id, $after_meta_key)
                ? get_post_meta($post_id, $after_meta_key, true)
                : null;

            if (
                (function_exists('meza_section_field_value_is_empty') && meza_section_field_value_is_empty($before_candidate))
                || $before_candidate === null
            ) {
                if (!$before_slot_was_posted) {
                    $before_candidate = $stored_before_value;
                }
            }
            if (
                $field_type === 'link'
                && function_exists('meza_merge_event_context_link_field_values')
                && !$before_slot_is_explicitly_empty
            ) {
                $before_candidate = meza_merge_event_context_link_field_values(
                    $before_candidate,
                    $stored_before_value,
                    $base_value
                );
            } else {
                $before_candidate = meza_normalize_context_meta_candidate($before_candidate, $field_type);
            }

            if (
                (function_exists('meza_section_field_value_is_empty') && meza_section_field_value_is_empty($after_candidate))
                || $after_candidate === null
            ) {
                if (!$after_slot_was_posted) {
                    $after_candidate = $stored_after_value;
                }
            }
            if (
                $field_type === 'link'
                && function_exists('meza_enrich_event_context_link_field_value')
                && !$after_slot_is_explicitly_empty
            ) {
                $after_candidate = meza_enrich_event_context_link_field_value(
                    $after_candidate,
                    $stored_after_value
                );
            } else {
                $after_candidate = meza_normalize_context_meta_candidate($after_candidate, $field_type);
            }

            $before_is_empty = !metadata_exists('post', $post_id, $before_meta_key)
                || (function_exists('meza_section_field_value_is_empty')
                    && meza_section_field_value_is_empty(get_post_meta($post_id, $before_meta_key, true)));

            if (
                is_array($before_field)
                && (
                    $before_is_empty
                    || (
                        $field_type === 'link'
                        && (!function_exists('meza_section_field_value_is_empty') || !meza_section_field_value_is_empty($before_candidate))
                        && meza_context_meta_values_differ($stored_before_value, $before_candidate)
                    )
                    || (
                        $force_before_sync
                        && (!function_exists('meza_section_field_value_is_empty') || !meza_section_field_value_is_empty($before_candidate))
                        && meza_context_meta_values_differ($stored_before_value, $before_candidate)
                    )
                )
                && (!function_exists('meza_section_field_value_is_empty') || !meza_section_field_value_is_empty($before_candidate))
            ) {
                update_post_meta($post_id, $before_meta_key, $before_candidate);
                meza_update_event_context_meta_reference(
                    $post_id,
                    $before_meta_key,
                    (string) ($before_field['key'] ?? '')
                );
            } elseif (
                is_array($before_field)
                && $before_slot_was_posted
                && $before_slot_is_explicitly_empty
            ) {
                delete_post_meta($post_id, $before_meta_key);
                delete_post_meta($post_id, '_' . $before_meta_key);
            }

            $after_is_empty = !metadata_exists('post', $post_id, $after_meta_key)
                || (function_exists('meza_section_field_value_is_empty')
                    && meza_section_field_value_is_empty(get_post_meta($post_id, $after_meta_key, true)));

            if (
                is_array($after_field)
                && (
                    $after_is_empty
                    || (
                        $field_type === 'link'
                        && (!function_exists('meza_section_field_value_is_empty') || !meza_section_field_value_is_empty($after_candidate))
                        && meza_context_meta_values_differ($stored_after_value, $after_candidate)
                    )
                )
                && (!function_exists('meza_section_field_value_is_empty') || !meza_section_field_value_is_empty($after_candidate))
            ) {
                update_post_meta($post_id, $after_meta_key, $after_candidate);
                meza_update_event_context_meta_reference(
                    $post_id,
                    $after_meta_key,
                    (string) ($after_field['key'] ?? '')
                );
            } elseif (
                is_array($after_field)
                && $after_slot_was_posted
                && $after_slot_is_explicitly_empty
            ) {
                delete_post_meta($post_id, $after_meta_key);
                delete_post_meta($post_id, '_' . $after_meta_key);
            }

            $base_is_empty = !metadata_exists('post', $post_id, $base_meta_key)
                || (function_exists('meza_section_field_value_is_empty')
                    && meza_section_field_value_is_empty($base_value));

            if (
                $grouped_payload !== null
                || ($base_is_empty && (!function_exists('meza_section_field_value_is_empty') || !meza_section_field_value_is_empty($before_candidate)))
                || (
                    $field_type === 'link'
                    && (!function_exists('meza_section_field_value_is_empty') || !meza_section_field_value_is_empty($before_candidate))
                    && meza_context_meta_values_differ(meza_normalize_context_meta_candidate($base_value, $field_type), $before_candidate)
                )
            ) {
                update_post_meta($post_id, $base_meta_key, $before_candidate);
            }

            return;
        }

        $field_type = (string) ($field['type'] ?? '');

        if (in_array($field_type, ['group', 'repeater'], true)) {
            foreach ((array) ($field['sub_fields'] ?? []) as $sub_field) {
                if (!is_array($sub_field)) {
                    continue;
                }

                $sub_field_name = sanitize_key((string) ($sub_field['name'] ?? ''));
                if ($sub_field_name === '') {
                    continue;
                }

                meza_migrate_event_after_content_field_meta(
                    $post_id,
                    $sub_field,
                    array_merge($path_segments, [$sub_field_name]),
                    $force_before_sync
                );
            }

            return;
        }

        if ($field_type === 'flexible_content') {
            foreach ((array) ($field['layouts'] ?? []) as $layout) {
                if (!is_array($layout)) {
                    continue;
                }

                $layout_name = sanitize_key((string) ($layout['name'] ?? ''));

                foreach ((array) ($layout['sub_fields'] ?? []) as $sub_field) {
                    if (!is_array($sub_field)) {
                        continue;
                    }

                    $sub_field_name = sanitize_key((string) ($sub_field['name'] ?? ''));
                    if ($sub_field_name === '') {
                        continue;
                    }

                    $next_segments = $path_segments;
                    if ($layout_name !== '') {
                        $next_segments[] = $layout_name;
                    }
                    $next_segments[] = $sub_field_name;

                    meza_migrate_event_after_content_field_meta(
                        $post_id,
                        $sub_field,
                        $next_segments,
                        $force_before_sync
                    );
                }
            }
        }
    }
}

if (!function_exists('meza_migrate_event_after_content_post_values')) {
    function meza_migrate_event_after_content_post_values(int $post_id, bool $force_before_sync = false): void
    {
        if (
            $post_id <= 0
            || get_post_type($post_id) !== 'event'
            || !function_exists('acf_get_field_groups')
            || !function_exists('acf_get_fields')
            || !function_exists('meza_field_tree_has_event_context')
        ) {
            return;
        }

        foreach ((array) acf_get_field_groups() as $field_group) {
            if (!is_array($field_group) || !meza_field_group_locations_include_event_post_type($field_group)) {
                continue;
            }

            $field_group_identifier = meza_get_acf_field_group_fields_identifier($field_group);
            if ($field_group_identifier === null) {
                continue;
            }

            $fields = (array) acf_get_fields($field_group_identifier);
            if ($fields === []) {
                continue;
            }

            $fields = meza_normalize_event_context_fields_for_migration($field_group, $fields);

            foreach ($fields as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $field_name = sanitize_key((string) ($field['name'] ?? ''));
                $field_type = (string) ($field['type'] ?? '');

                if ($field_name === '' || $field_type === 'tab' || !meza_field_tree_has_event_context($field)) {
                    continue;
                }

                meza_migrate_event_after_content_field_meta($post_id, $field, [$field_name], $force_before_sync);
            }
        }
    }
}

if (!function_exists('meza_sync_event_after_content_migration_state')) {
    function meza_copy_builtin_event_details_before_values(int $post_id, bool $force_before_sync = false): void
    {
        if ($post_id <= 0 || get_post_type($post_id) !== 'event') {
            return;
        }

        $field_map = [
            'summary' => 'field_6a08e3fc9599c',
            'link' => 'field_6a08d94ae1433',
        ];

        foreach ($field_map as $meta_key => $before_field_key) {
            $before_meta_key = $meta_key . '_before';

            $current_before_value = metadata_exists('post', $post_id, $before_meta_key)
                ? get_post_meta($post_id, $before_meta_key, true)
                : null;
            if (
                !$before_field_key
                || (
                    !$force_before_sync
                    && (
                        (
                            function_exists('meza_section_field_value_is_empty')
                            && !meza_section_field_value_is_empty($current_before_value)
                        )
                        || (
                            !function_exists('meza_section_field_value_is_empty')
                            && $current_before_value !== null
                        )
                    )
                )
            ) {
                continue;
            }

            $legacy_value = metadata_exists('post', $post_id, $meta_key)
                ? get_post_meta($post_id, $meta_key, true)
                : null;

            $grouped_payload = meza_parse_event_context_grouped_payload($legacy_value);
            if (is_array($grouped_payload)) {
                $legacy_value = $grouped_payload['before'] ?? null;
            }

            if ($meta_key === 'link') {
                $legacy_value = meza_normalize_context_meta_candidate($legacy_value, 'link');
                $current_before_value = meza_normalize_context_meta_candidate($current_before_value, 'link');
            }

            if (
                function_exists('meza_section_field_value_is_empty')
                && meza_section_field_value_is_empty($legacy_value)
            ) {
                continue;
            }

            if (
                $force_before_sync
                && !meza_context_meta_values_differ($current_before_value, $legacy_value)
            ) {
                continue;
            }

            update_post_meta($post_id, $before_meta_key, $legacy_value);
            meza_update_event_context_meta_reference($post_id, $before_meta_key, $before_field_key);
        }
    }

    function meza_run_event_after_content_migration(bool $force_before_sync = false): void
    {
        $event_ids = get_posts([
            'post_type' => 'event',
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
            'cache_results' => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        foreach ($event_ids as $event_id) {
            $event_id = (int) $event_id;
            if ($event_id <= 0) {
                continue;
            }

            meza_copy_builtin_event_details_before_values($event_id, $force_before_sync);
            meza_migrate_event_after_content_post_values($event_id, $force_before_sync);
        }
    }

    function meza_sync_event_after_content_migration_state($value, $post_id, $field)
    {
        if (
            !in_array($post_id, ['option', 'options'], true)
            || !function_exists('meza_business_information_acf_truthy')
        ) {
            return $value;
        }

        $was_enabled = meza_business_information_acf_truthy(get_option('options_events_after', 0));
        $is_enabled = meza_business_information_acf_truthy($value);

        // Persist this specific toggle at the field-update layer so it cannot
        // silently depend on later generic options-page save behavior.
        update_option('options_events_after', $is_enabled ? '1' : '0', false);
        update_option('_options_events_after', 'field_meza_event_after_content', false);
        update_option('meza_events_after_content_last_update_debug', [
            'post_id' => $post_id,
            'value' => $is_enabled ? '1' : '0',
            'timestamp' => current_time('mysql'),
        ], false);

        if (!$was_enabled && $is_enabled) {
            meza_run_event_after_content_migration(true);
        }

        return $value;
    }
}
add_filter('acf/update_value/key=field_meza_event_after_content', 'meza_sync_event_after_content_migration_state', 20, 3);

if (!function_exists('meza_migrate_season_after_content_field_meta')) {
    function meza_migrate_season_after_content_field_meta(int $post_id, array $field, array $path_segments): void
    {
        if (!function_exists('meza_is_season_context_group_field')) {
            return;
        }

        if (meza_is_season_context_group_field($field)) {
            $normalized_segments = array_values(array_filter(array_map(static function ($segment): string {
                return sanitize_key((string) $segment);
            }, $path_segments), static function (string $segment): bool {
                return $segment !== '';
            }));

            if ($normalized_segments === []) {
                return;
            }

            $base_meta_key = implode('_', $normalized_segments);
            $season_before_meta_key = $base_meta_key . '_before';
            $event_after_meta_key = $base_meta_key . '_after';
            $season_after_meta_key = $base_meta_key . '_season_after';
            $season_before_field = meza_get_event_context_nested_sub_field($field, 'before')
                ?? meza_get_event_context_nested_sub_field($field, 'base');
            $event_after_field = meza_get_event_context_nested_sub_field($field, 'after');
            $season_after_field = meza_get_event_context_nested_sub_field($field, 'season_after');
            $field_type = meza_get_context_value_field_type($field, [$season_before_field, $season_after_field]);
            $base_value = metadata_exists('post', $post_id, $base_meta_key)
                ? get_post_meta($post_id, $base_meta_key, true)
                : null;

            $season_before_candidate = $base_value;
            $event_after_candidate = metadata_exists('post', $post_id, $event_after_meta_key)
                ? get_post_meta($post_id, $event_after_meta_key, true)
                : null;
            $season_after_candidate = metadata_exists('post', $post_id, $season_after_meta_key)
                ? get_post_meta($post_id, $season_after_meta_key, true)
                : null;

            if (is_array($base_value)) {
                if (array_key_exists('before', $base_value)) {
                    $season_before_candidate = $base_value['before'];
                } elseif (array_key_exists('base', $base_value)) {
                    $season_before_candidate = $base_value['base'];
                }

                if (array_key_exists('season_after', $base_value)) {
                    $season_after_candidate = $base_value['season_after'];
                }

                if (array_key_exists('after', $base_value)) {
                    $event_after_candidate = $base_value['after'];
                }
            }

            $stored_season_before_value = metadata_exists('post', $post_id, $season_before_meta_key)
                ? get_post_meta($post_id, $season_before_meta_key, true)
                : null;
            $stored_event_after_value = metadata_exists('post', $post_id, $event_after_meta_key)
                ? get_post_meta($post_id, $event_after_meta_key, true)
                : null;

            if (
                (function_exists('meza_section_field_value_is_empty') && meza_section_field_value_is_empty($season_before_candidate))
                || $season_before_candidate === null
            ) {
                $season_before_candidate = $stored_season_before_value;
            }
            if (
                (function_exists('meza_section_field_value_is_empty') && meza_section_field_value_is_empty($event_after_candidate))
                || $event_after_candidate === null
            ) {
                $event_after_candidate = $stored_event_after_value;
            }
            $season_before_candidate = meza_normalize_context_meta_candidate($season_before_candidate, $field_type);
            $event_after_candidate = meza_normalize_context_meta_candidate($event_after_candidate, $field_type);
            $season_after_candidate = meza_normalize_context_meta_candidate($season_after_candidate, $field_type);

            $season_before_is_empty = !metadata_exists('post', $post_id, $season_before_meta_key)
                || (function_exists('meza_section_field_value_is_empty')
                    && meza_section_field_value_is_empty(get_post_meta($post_id, $season_before_meta_key, true)));

            if (
                is_array($season_before_field)
                && (
                    $season_before_is_empty
                    || (
                        !function_exists('meza_section_field_value_is_empty')
                        || !meza_section_field_value_is_empty($season_before_candidate)
                    ) && meza_context_meta_values_differ($stored_season_before_value, $season_before_candidate)
                )
                && (!function_exists('meza_section_field_value_is_empty') || !meza_section_field_value_is_empty($season_before_candidate))
            ) {
                update_post_meta($post_id, $season_before_meta_key, $season_before_candidate);
                meza_update_event_context_meta_reference(
                    $post_id,
                    $season_before_meta_key,
                    (string) ($season_before_field['key'] ?? '')
                );
            }

            $event_after_is_empty = !metadata_exists('post', $post_id, $event_after_meta_key)
                || (function_exists('meza_section_field_value_is_empty')
                    && meza_section_field_value_is_empty(get_post_meta($post_id, $event_after_meta_key, true)));

            if (
                is_array($event_after_field)
                && (
                    $event_after_is_empty
                    || (
                        !function_exists('meza_section_field_value_is_empty')
                        || !meza_section_field_value_is_empty($event_after_candidate)
                    ) && meza_context_meta_values_differ($stored_event_after_value, $event_after_candidate)
                )
                && (!function_exists('meza_section_field_value_is_empty') || !meza_section_field_value_is_empty($event_after_candidate))
            ) {
                update_post_meta($post_id, $event_after_meta_key, $event_after_candidate);
                meza_update_event_context_meta_reference(
                    $post_id,
                    $event_after_meta_key,
                    (string) ($event_after_field['key'] ?? '')
                );
            }

            if (
                is_array($season_after_field)
                && (
                    !metadata_exists('post', $post_id, $season_after_meta_key)
                    || (
                        function_exists('meza_section_field_value_is_empty')
                        && meza_section_field_value_is_empty(get_post_meta($post_id, $season_after_meta_key, true))
                    )
                    || meza_context_meta_values_differ(
                        metadata_exists('post', $post_id, $season_after_meta_key)
                            ? get_post_meta($post_id, $season_after_meta_key, true)
                            : null,
                        $season_after_candidate
                    )
                )
                && (!function_exists('meza_section_field_value_is_empty') || !meza_section_field_value_is_empty($season_after_candidate))
            ) {
                update_post_meta($post_id, $season_after_meta_key, $season_after_candidate);
                meza_update_event_context_meta_reference(
                    $post_id,
                    $season_after_meta_key,
                    (string) ($season_after_field['key'] ?? '')
                );
            }

            if (
                is_array($season_after_field)
                && metadata_exists('post', $post_id, $season_after_meta_key)
                && (!function_exists('meza_section_field_value_is_empty') || !meza_section_field_value_is_empty(get_post_meta($post_id, $season_after_meta_key, true)))
            ) {
                meza_update_event_context_meta_reference(
                    $post_id,
                    $season_after_meta_key,
                    (string) ($season_after_field['key'] ?? '')
                );
            }

            $base_is_empty = !metadata_exists('post', $post_id, $base_meta_key)
                || (function_exists('meza_section_field_value_is_empty')
                    && meza_section_field_value_is_empty($base_value));

            if (
                is_array($base_value)
                || ($base_is_empty && (!function_exists('meza_section_field_value_is_empty') || !meza_section_field_value_is_empty($season_before_candidate)))
            ) {
                update_post_meta($post_id, $base_meta_key, $season_before_candidate);
            }

            return;
        }

        $field_type = (string) ($field['type'] ?? '');

        if (in_array($field_type, ['group', 'repeater'], true)) {
            foreach ((array) ($field['sub_fields'] ?? []) as $sub_field) {
                if (!is_array($sub_field)) {
                    continue;
                }

                $sub_field_name = sanitize_key((string) ($sub_field['name'] ?? ''));
                if ($sub_field_name === '' || $field_type === 'group' && $sub_field_name === 'season_after') {
                    continue;
                }

                meza_migrate_season_after_content_field_meta(
                    $post_id,
                    $sub_field,
                    array_merge($path_segments, [$sub_field_name])
                );
            }

            return;
        }

        if ($field_type === 'flexible_content') {
            foreach ((array) ($field['layouts'] ?? []) as $layout) {
                if (!is_array($layout)) {
                    continue;
                }

                $layout_name = sanitize_key((string) ($layout['name'] ?? ''));

                foreach ((array) ($layout['sub_fields'] ?? []) as $sub_field) {
                    if (!is_array($sub_field)) {
                        continue;
                    }

                    $sub_field_name = sanitize_key((string) ($sub_field['name'] ?? ''));
                    if ($sub_field_name === '') {
                        continue;
                    }

                    $next_segments = $path_segments;
                    if ($layout_name !== '') {
                        $next_segments[] = $layout_name;
                    }
                    $next_segments[] = $sub_field_name;

                    meza_migrate_season_after_content_field_meta($post_id, $sub_field, $next_segments);
                }
            }
        }
    }
}

if (!function_exists('meza_migrate_season_after_content_post_values')) {
    function meza_migrate_season_after_content_post_values(int $post_id): void
    {
        if (
            $post_id <= 0
            || !function_exists('acf_get_field_groups')
            || !function_exists('acf_get_fields')
        ) {
            return;
        }

        foreach ((array) acf_get_field_groups() as $field_group) {
            if (!is_array($field_group)) {
                continue;
            }

            $field_group_identifier = meza_get_acf_field_group_fields_identifier($field_group);
            if ($field_group_identifier === null) {
                continue;
            }

            $fields = (array) acf_get_fields($field_group_identifier);
            if ($fields === []) {
                continue;
            }

            $fields = meza_normalize_season_context_fields_for_migration($field_group, $fields);

            foreach ($fields as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $field_name = sanitize_key((string) ($field['name'] ?? ''));
                $field_type = (string) ($field['type'] ?? '');

                if ($field_name === '' || $field_type === 'tab' || !meza_fields_have_season_context([$field])) {
                    continue;
                }

                meza_migrate_season_after_content_field_meta($post_id, $field, [$field_name]);
            }
        }
    }
}

if (!function_exists('meza_run_season_after_content_migration')) {
    function meza_run_season_after_content_migration(): void
    {
        $post_ids = get_posts([
            'post_type' => 'any',
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
            'cache_results' => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        foreach ($post_ids as $post_id) {
            $post_id = (int) $post_id;
            if ($post_id <= 0 || wp_is_post_revision($post_id)) {
                continue;
            }

            meza_migrate_season_after_content_post_values($post_id);
        }
    }

    function meza_sync_season_after_content_migration_state($value, $post_id, $field)
    {
        if (
            !in_array($post_id, ['option', 'options'], true)
            || !function_exists('meza_business_information_acf_truthy')
        ) {
            return $value;
        }

        $was_enabled = meza_business_information_acf_truthy(get_option('options_season_after', 0));
        $is_enabled = meza_business_information_acf_truthy($value);

        update_option('options_season_after', $is_enabled ? '1' : '0', false);
        update_option('_options_season_after', 'field_meza_enable_season_after_content', false);

        if (!$was_enabled && $is_enabled) {
            if (function_exists('meza_run_event_after_content_migration')) {
                meza_run_event_after_content_migration(true);
            }
            meza_run_season_after_content_migration();
        }

        return $value;
    }
}
add_filter('acf/update_value/key=field_meza_enable_season_after_content', 'meza_sync_season_after_content_migration_state', 20, 3);

if (!function_exists('meza_repair_current_season_after_content_meta_on_admin_load')) {
    function meza_repair_current_season_after_content_meta_on_admin_load(): void
    {
        if (
            !is_admin()
            || !function_exists('meza_seasons_have_after_content')
            || !meza_seasons_have_after_content()
        ) {
            return;
        }

        $screen_post_id = 0;
        if (isset($_GET['post']) && is_numeric($_GET['post'])) {
            $screen_post_id = (int) $_GET['post'];
        } elseif (isset($_POST['post']) && is_numeric($_POST['post'])) {
            $screen_post_id = (int) $_POST['post'];
        } elseif (isset($_POST['post_ID']) && is_numeric($_POST['post_ID'])) {
            $screen_post_id = (int) $_POST['post_ID'];
        }

        if ($screen_post_id <= 0 || wp_is_post_revision($screen_post_id)) {
            return;
        }

        meza_migrate_season_after_content_post_values($screen_post_id);
    }
}
add_action('load-post.php', 'meza_repair_current_season_after_content_meta_on_admin_load', 21);

if (!function_exists('meza_sync_season_after_content_after_business_information_save')) {
    function meza_sync_season_after_content_after_business_information_save($post_id): void
    {
        if (
            (
                !meza_is_business_information_acf_submission()
                && !meza_is_content_structure_acf_submission()
            )
            || !in_array($post_id, ['option', 'options'], true)
            || !function_exists('meza_seasons_have_after_content')
            || !meza_seasons_have_after_content()
        ) {
            return;
        }

        if (function_exists('meza_run_event_after_content_migration')) {
            meza_run_event_after_content_migration(true);
        }
        meza_run_season_after_content_migration();
    }
}
add_action('acf/save_post', 'meza_sync_season_after_content_after_business_information_save', 24);

if (!function_exists('meza_repair_current_event_after_content_meta_on_admin_load')) {
    function meza_repair_current_event_after_content_meta_on_admin_load(): void
    {
        if (
            !is_admin()
        ) {
            return;
        }

        $screen_post_id = 0;
        if (isset($_GET['post']) && is_numeric($_GET['post'])) {
            $screen_post_id = (int) $_GET['post'];
        } elseif (isset($_POST['post']) && is_numeric($_POST['post'])) {
            $screen_post_id = (int) $_POST['post'];
        } elseif (isset($_POST['post_ID']) && is_numeric($_POST['post_ID'])) {
            $screen_post_id = (int) $_POST['post_ID'];
        }

        if ($screen_post_id <= 0 || get_post_type($screen_post_id) !== 'event') {
            return;
        }

        meza_migrate_event_after_content_post_values($screen_post_id);
    }
}
add_action('load-post.php', 'meza_repair_current_event_after_content_meta_on_admin_load', 20);

if (!function_exists('meza_sync_events_after_business_information_save')) {
    function meza_persist_business_information_event_settings(): void
    {
        if (!isset($_POST['acf']) || !is_array($_POST['acf']) || !function_exists('meza_business_information_acf_truthy')) {
            return;
        }

        $field_option_map = [
            'field_meza_include_event_functionality' => [
                'options_events' => '1',
                '_options_events' => 'field_meza_include_event_functionality',
                'legacy_option' => 'options_include_event_functionality',
            ],
            'field_meza_indexable_events' => [
                'options_events_indexable' => '1',
                '_options_events_indexable' => 'field_meza_indexable_events',
                'legacy_option' => 'options_indexable_events',
            ],
            'field_meza_event_speakers_topics' => [
                'options_events_speakers' => '1',
                '_options_events_speakers' => 'field_meza_event_speakers_topics',
                'legacy_option' => 'options_event_speakers_topics',
            ],
            'field_meza_event_after_content' => [
                'options_events_after' => '1',
                '_options_events_after' => 'field_meza_event_after_content',
            ],
            'field_meza_enable_season_after_content' => [
                'options_season_after' => '1',
                '_options_season_after' => 'field_meza_enable_season_after_content',
            ],
        ];

        foreach ($field_option_map as $field_key => $targets) {
            if (!array_key_exists($field_key, $_POST['acf'])) {
                continue;
            }

            $normalized_value = (
                meza_business_information_acf_truthy(wp_unslash($_POST['acf'][$field_key]))
            ) ? '1' : '0';

            foreach ($targets as $option_name => $stored_value) {
                if ($option_name === 'legacy_option') {
                    update_option($stored_value, $normalized_value, false);
                    continue;
                }

                update_option(
                    $option_name,
                    str_starts_with($option_name, '_') ? $stored_value : $normalized_value,
                    false
                );
            }
        }
    }

    function meza_sync_events_after_business_information_save($post_id): void
    {
        if (
            !meza_is_business_information_acf_submission()
            || !in_array($post_id, ['option', 'options'], true)
        ) {
            return;
        }

        meza_persist_business_information_event_settings();
    }
}
add_action('acf/save_post', 'meza_sync_events_after_business_information_save', 24);

if (!function_exists('meza_sync_event_after_content_after_business_information_save')) {
    function meza_sync_event_after_content_after_business_information_save($post_id): void
    {
        if (
            (
                !meza_is_business_information_acf_submission()
                && !meza_is_content_structure_acf_submission()
            )
            || !in_array($post_id, ['option', 'options'], true)
            || !function_exists('meza_events_have_after_content')
            || !meza_events_have_after_content()
        ) {
            return;
        }

        meza_run_event_after_content_migration(true);
    }
}
add_action('acf/save_post', 'meza_sync_event_after_content_after_business_information_save', 25);

if (!function_exists('meza_sync_ecommerce_after_business_information_save')) {
    function meza_persist_business_information_ecommerce_setting(): void
    {
        $settings = meza_get_business_information_ecommerce_settings();

        update_option('options_woocommerce', !empty($settings['woocommerce']) ? '1' : '0', false);
        update_option('options_product_indexing', !empty($settings['product_indexing']) ? '1' : '0', false);
        update_option('options_store', !empty($settings['store']) ? '1' : '0', false);
        update_option('options_accounts', !empty($settings['accounts']) ? '1' : '0', false);

        $legacy_selection = [];
        if (!empty($settings['woocommerce'])) {
            $legacy_selection[] = 'content_fields';
        }
        if (!empty($settings['store'])) {
            $legacy_selection[] = 'store_functionality';
        }

        update_option('options_ecommerce', $legacy_selection, false);
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

            if (function_exists('meza_sync_woocommerce_configuration')) {
                meza_sync_woocommerce_configuration(true);
            }

            return;
        }

        if (function_exists('meza_sync_woocommerce_configuration')) {
            meza_sync_woocommerce_configuration(true);
        }

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

if (!function_exists('meza_sync_ecommerce_after_content_structure_save')) {
    function meza_sync_ecommerce_after_content_structure_save($post_id): void
    {
        if (
            !meza_is_content_structure_acf_submission()
            || !in_array($post_id, ['option', 'options'], true)
        ) {
            return;
        }

        if (function_exists('meza_persist_business_information_ecommerce_setting')) {
            meza_persist_business_information_ecommerce_setting();
        }

        if (function_exists('meza_sync_ecommerce_defaults')) {
            meza_sync_ecommerce_defaults();
        }
    }
}
add_action('acf/save_post', 'meza_sync_ecommerce_after_content_structure_save', 25);

if (!function_exists('meza_should_noindex_post_category_archives')) {
    function meza_should_noindex_post_category_archives(): bool
    {
        return !(function_exists('meza_are_post_categories_indexable') && meza_are_post_categories_indexable());
    }
}

if (!function_exists('meza_should_noindex_post_tag_archives')) {
    function meza_should_noindex_post_tag_archives(): bool
    {
        return !(function_exists('meza_are_post_tags_indexable') && meza_are_post_tags_indexable());
    }
}

if (!function_exists('meza_sync_post_category_taxonomy_visibility')) {
    function meza_sync_post_category_taxonomy_visibility(?bool $allow_indexing = null): void
    {
        if ($allow_indexing === null) {
            $allow_indexing = function_exists('meza_are_post_categories_indexable') && meza_are_post_categories_indexable();
        }

        $titles = get_option('wpseo_titles', []);
        if (!is_array($titles)) {
            $titles = [];
        }

        $target_noindex = !$allow_indexing;
        $option_key = 'noindex-tax-category';

        if (array_key_exists($option_key, $titles) && (bool) $titles[$option_key] === $target_noindex) {
            return;
        }

        $titles[$option_key] = $target_noindex;
        update_option('wpseo_titles', $titles, false);
    }
}

if (!function_exists('meza_sync_post_tag_taxonomy_visibility')) {
    function meza_sync_post_tag_taxonomy_visibility(?bool $allow_indexing = null): void
    {
        if ($allow_indexing === null) {
            $allow_indexing = function_exists('meza_are_post_tags_indexable') && meza_are_post_tags_indexable();
        }

        $titles = get_option('wpseo_titles', []);
        if (!is_array($titles)) {
            $titles = [];
        }

        $target_noindex = !$allow_indexing;
        $option_key = 'noindex-tax-post_tag';

        if (array_key_exists($option_key, $titles) && (bool) $titles[$option_key] === $target_noindex) {
            return;
        }

        $titles[$option_key] = $target_noindex;
        update_option('wpseo_titles', $titles, false);
    }
}

if (!function_exists('meza_get_posts_category_configuration_signature')) {
    function meza_get_posts_category_configuration_signature(): string
    {
        return sprintf(
            'posts:%d|index:%d|tags:%d|tags_index:%d',
            function_exists('meza_are_post_categories_enabled') && meza_are_post_categories_enabled() ? 1 : 0,
            function_exists('meza_are_post_categories_indexable') && meza_are_post_categories_indexable() ? 1 : 0,
            function_exists('meza_are_post_tags_enabled') && meza_are_post_tags_enabled() ? 1 : 0,
            function_exists('meza_are_post_tags_indexable') && meza_are_post_tags_indexable() ? 1 : 0
        );
    }
}

if (!function_exists('meza_sync_posts_category_configuration')) {
    function meza_sync_posts_category_configuration(bool $force = false): void
    {
        $signature_option = 'meza_posts_category_configuration_signature';
        $target_signature = meza_get_posts_category_configuration_signature();
        $current_signature = (string) get_option($signature_option, '');

        if (!$force && $current_signature === $target_signature) {
            return;
        }

        meza_sync_post_category_taxonomy_visibility();
        meza_sync_post_tag_taxonomy_visibility();
        update_option($signature_option, $target_signature, false);
    }
}

if (!function_exists('meza_sync_native_post_taxonomy_support')) {
    function meza_sync_native_post_taxonomy_support(): void
    {
        $categories_enabled = function_exists('meza_are_post_categories_enabled') && meza_are_post_categories_enabled();
        $tags_enabled = function_exists('meza_are_post_tags_enabled') && meza_are_post_tags_enabled();

        if ($categories_enabled) {
            register_taxonomy_for_object_type('category', 'post');
        } else {
            unregister_taxonomy_for_object_type('category', 'post');
        }

        if ($tags_enabled) {
            register_taxonomy_for_object_type('post_tag', 'post');
        } else {
            unregister_taxonomy_for_object_type('post_tag', 'post');
        }
    }
}
add_action('init', 'meza_sync_native_post_taxonomy_support', 30);

if (!function_exists('meza_sync_native_post_runtime_permalinks')) {
    function meza_sync_native_post_runtime_permalinks(): void
    {
        global $wp_post_types;

        if (
            !is_array($wp_post_types)
            || !isset($wp_post_types['post'])
            || !($wp_post_types['post'] instanceof WP_Post_Type)
            || (function_exists('meza_are_posts_enabled') && meza_are_posts_enabled())
        ) {
            return;
        }

        $wp_post_types['post']->publicly_queryable = false;
        $wp_post_types['post']->rewrite = false;
        $wp_post_types['post']->query_var = false;
        $wp_post_types['post']->has_archive = false;
        $wp_post_types['post']->exclude_from_search = true;
    }
}
add_action('init', 'meza_sync_native_post_runtime_permalinks', 30);

if (!function_exists('meza_remove_native_post_rewrite_rules_when_disabled')) {
    function meza_remove_native_post_rewrite_rules_when_disabled(array $rules): array
    {
        return function_exists('meza_are_posts_enabled') && !meza_are_posts_enabled()
            ? []
            : $rules;
    }
}
add_filter('post_rewrite_rules', 'meza_remove_native_post_rewrite_rules_when_disabled', 20);

if (!function_exists('meza_filter_native_post_permalink_when_disabled')) {
    function meza_filter_native_post_permalink_when_disabled(string $permalink, WP_Post $post, bool $leavename): string
    {
        unset($leavename);

        if (
            $post->post_type !== 'post'
            || !function_exists('meza_are_posts_enabled')
            || meza_are_posts_enabled()
        ) {
            return $permalink;
        }

        return '';
    }
}
add_filter('post_link', 'meza_filter_native_post_permalink_when_disabled', 20, 3);

if (!function_exists('meza_prevent_native_post_permalink_requests_when_disabled')) {
    function meza_prevent_native_post_permalink_requests_when_disabled(): void
    {
        if (
            is_admin()
            || !function_exists('meza_are_posts_enabled')
            || meza_are_posts_enabled()
            || !is_singular('post')
        ) {
            return;
        }

        global $wp_query;

        if ($wp_query instanceof WP_Query) {
            $wp_query->set_404();
        }

        status_header(404);
        nocache_headers();
    }
}
add_action('template_redirect', 'meza_prevent_native_post_permalink_requests_when_disabled', 1);

if (!function_exists('meza_disable_native_post_canonical_redirects_when_disabled')) {
    function meza_disable_native_post_canonical_redirects_when_disabled($redirect_url, string $requested_url)
    {
        unset($requested_url);

        if (
            !function_exists('meza_are_posts_enabled')
            || meza_are_posts_enabled()
            || !is_singular('post')
        ) {
            return $redirect_url;
        }

        return false;
    }
}
add_filter('redirect_canonical', 'meza_disable_native_post_canonical_redirects_when_disabled', 20, 2);

if (!function_exists('meza_sync_posts_after_business_information_save')) {
    function meza_sync_posts_after_business_information_save($post_id): void
    {
        if (
            (
                !meza_is_business_information_acf_submission()
                && !meza_is_content_structure_acf_submission()
            )
            || !in_array($post_id, ['option', 'options'], true)
        ) {
            return;
        }

        meza_sync_posts_category_configuration(true);
    }
}
add_action('acf/save_post', 'meza_sync_posts_after_business_information_save', 25);

if (!function_exists('meza_schedule_event_rewrite_flush_after_content_structure_save')) {
    function meza_schedule_event_rewrite_flush_after_content_structure_save($post_id): void
    {
        if (
            (
                !meza_is_business_information_acf_submission()
                && !meza_is_content_structure_acf_submission()
            )
            || !in_array($post_id, ['option', 'options'], true)
        ) {
            return;
        }

        set_transient('meza_flush_rewrite_needed', 1, 5 * MINUTE_IN_SECONDS);
    }
}
add_action('acf/save_post', 'meza_schedule_event_rewrite_flush_after_content_structure_save', 25);

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

add_action('admin_init', function (): void {
    static $did_sync = false;

    if ($did_sync || !is_admin()) {
        return;
    }

    $did_sync = true;
    meza_sync_posts_category_configuration();
}, 41);

add_filter('wpseo_robots', function ($robots) {
    if (!is_category() || !meza_should_noindex_post_category_archives()) {
        return $robots;
    }

    return 'noindex, nofollow';
}, 20);

add_filter('wp_robots', function (array $robots): array {
    if (!is_category() || !meza_should_noindex_post_category_archives()) {
        return $robots;
    }

    unset(
        $robots['index'],
        $robots['follow'],
        $robots['max-snippet'],
        $robots['max-image-preview'],
        $robots['max-video-preview']
    );

    $robots['noindex'] = true;
    $robots['nofollow'] = true;

    return $robots;
}, 20);

add_filter('wpseo_robots', function ($robots) {
    if (!is_tag() || !meza_should_noindex_post_tag_archives()) {
        return $robots;
    }

    return 'noindex, nofollow';
}, 21);

add_filter('wp_robots', function (array $robots): array {
    if (!is_tag() || !meza_should_noindex_post_tag_archives()) {
        return $robots;
    }

    unset(
        $robots['index'],
        $robots['follow'],
        $robots['max-snippet'],
        $robots['max-image-preview'],
        $robots['max-video-preview']
    );

    $robots['noindex'] = true;
    $robots['nofollow'] = true;

    return $robots;
}, 21);

add_action('acf/include_admin_tools', function (): void {
    if (!class_exists('ACF_Admin_Tool_Export') || !class_exists('ACF_Admin_Tool')) {
        return;
    }

    if (!class_exists('Meza_ACF_Admin_Tool_Export')) {
        class Meza_ACF_Admin_Tool_Export extends ACF_Admin_Tool_Export
        {
            private function get_bucket_field_name(string $base_name, string $group_key): string
            {
                return $base_name . '__' . $group_key;
            }

            public function get_selected_keys()
            {
                if (!meza_current_user_can_see_hardcoded_acf_export_items()) {
                    return parent::get_selected_keys();
                }

                $all_keys = [];
                $base_names = [
                    'keys',
                    'taxonomy_keys',
                    'post_type_keys',
                    'ui_options_page_keys',
                ];
                $group_keys = [
                    'custom',
                    'defaults',
                    'built_in',
                ];
                $append_keys = static function ($keys) use (&$all_keys): void {
                    if (!$keys) {
                        return;
                    }

                    foreach ((array) $keys as $key) {
                        if (!is_scalar($key)) {
                            continue;
                        }

                        $key = (string) $key;
                        if ($key === '') {
                            continue;
                        }

                        $all_keys[] = $key;
                    }
                };

                foreach ($base_names as $base_name) {
                    $append_keys(acf_maybe_get_POST($base_name));

                    foreach ($group_keys as $group_key) {
                        $append_keys(acf_maybe_get_POST($this->get_bucket_field_name($base_name, $group_key)));
                    }

                    $keys = acf_maybe_get_GET($base_name);
                    if ($keys) {
                        $append_keys(explode('+', str_replace(' ', '+', (string) $keys)));
                    }

                    foreach ($group_keys as $group_key) {
                        $keys = acf_maybe_get_GET($this->get_bucket_field_name($base_name, $group_key));
                        if ($keys) {
                            $append_keys(explode('+', str_replace(' ', '+', (string) $keys)));
                        }
                    }
                }

                if (empty($all_keys)) {
                    return false;
                }

                return array_values(array_unique($all_keys));
            }

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

                    $selected = $this->get_selected_keys();
                    $json = [];

                    if (!$selected) {
                        return false;
                    }

                    foreach ($selected as $key) {
                        $post_type = acf_determine_internal_post_type($key);
                        if (!$post_type) {
                            continue;
                        }

                        if (
                            $post_type === 'acf-field-group'
                            && function_exists('meza_get_acf_export_tool_field_group_for_key')
                        ) {
                            $post = meza_get_acf_export_tool_field_group_for_key((string) $key);
                        } else {
                            $post = acf_get_internal_post_type($key, $post_type);
                        }

                        if (!is_array($post) || $post === []) {
                            continue;
                        }

                        if (
                            $post_type === 'acf-field-group'
                            && (!isset($post['fields']) || !is_array($post['fields']))
                        ) {
                            $post['fields'] = acf_get_fields($post);
                        }

                        $json[] = acf_prepare_internal_post_type_for_export($post, $post_type);
                    }

                    return $json !== [] ? $json : false;
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

                        echo '<div class="acf-field meza-acf-export-section" data-export-post-type="' . esc_attr($section['post_type']) . '">';
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
                                    'name' => $this->get_bucket_field_name($section['name'], $group_key),
                                    'prefix' => false,
                                    'value' => $selected,
                                    'toggle' => true,
                                    'choices' => $grouped_choices[$group_key],
                                    'wrapper' => [
                                        'class' => 'meza-acf-export-bucket',
                                        'data-group-key' => $group_key,
                                    ],
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

        #acf-admin-tools .meza-acf-export-bucket > .acf-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        #acf-admin-tools .meza-acf-export-bucket > .acf-label label {
            margin: 0;
        }

        #acf-admin-tools .meza-acf-export-section-controls {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-left: auto;
        }

        #acf-admin-tools .meza-acf-export-section-toggle {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 400;
        }

        #acf-admin-tools .meza-acf-export-section-toggle input {
            margin: 0;
        }

        #acf-admin-tools .meza-acf-export-bucket-toggle {
            border: 0;
            background: transparent;
            color: #2271b1;
            cursor: pointer;
            font: inherit;
            padding: 0;
            text-decoration: underline;
        }

        #acf-admin-tools .meza-acf-export-bucket-toggle:hover,
        #acf-admin-tools .meza-acf-export-bucket-toggle:focus {
            color: #135e96;
        }

        #acf-admin-tools .meza-acf-export-section-action {
            border: 0;
            background: transparent;
            color: #2271b1;
            cursor: pointer;
            font: inherit;
            padding: 0;
            text-decoration: underline;
        }

        #acf-admin-tools .meza-acf-export-section-action:hover,
        #acf-admin-tools .meza-acf-export-section-action:focus {
            color: #135e96;
        }

        #acf-admin-tools .meza-acf-export-bucket.is-collapsed > .acf-input {
            display: none;
        }
    </style>
    <script>
        (() => {
            const defaultCollapsedGroupKeys = new Set(['defaults', 'built_in']);

            const getSectionLabel = (sectionField) => {
                if (!(sectionField instanceof HTMLElement)) {
                    return '';
                }

                return ((sectionField.querySelector(':scope > .acf-label > label')?.textContent) || '').trim();
            };

            const getBucketGroupKey = (bucket) => {
                if (!(bucket instanceof HTMLElement)) {
                    return '';
                }

                return bucket.dataset.groupKey || '';
            };

            const isBucketDefaultCollapsed = (bucket) => defaultCollapsedGroupKeys.has(getBucketGroupKey(bucket));

            const isBucketEffectivelyCollapsed = (bucket) => {
                if (!(bucket instanceof HTMLElement)) {
                    return false;
                }

                return bucket.dataset.mezaCollapsed === '1'
                    ? true
                    : bucket.dataset.mezaCollapsed === '0'
                        ? false
                        : isBucketDefaultCollapsed(bucket);
            };

            const applyBucketState = (bucket) => {
                if (!(bucket instanceof HTMLElement)) {
                    return;
                }

                bucket.classList.toggle('is-collapsed', isBucketEffectivelyCollapsed(bucket));

                if (typeof bucket.mezaSyncButtonLabel === 'function') {
                    bucket.mezaSyncButtonLabel();
                }
            };

            const getSectionManagedBuckets = (sectionField) => {
                if (!(sectionField instanceof HTMLElement)) {
                    return [];
                }

                return Array.from(sectionField.querySelectorAll(':scope > .acf-input > .meza-acf-export-bucket'))
                    .filter((bucket) => bucket instanceof HTMLElement);
            };

            const syncSectionToggle = (sectionField) => {
                if (!(sectionField instanceof HTMLElement) || typeof sectionField.mezaSectionToggleSync !== 'function') {
                    return;
                }

                sectionField.mezaSectionToggleSync();
            };

            const setBucketCheckboxesChecked = (bucket, checked) => {
                if (!(bucket instanceof HTMLElement)) {
                    return;
                }

                const inputs = bucket.querySelectorAll('.acf-checkbox-list input[type="checkbox"]');
                inputs.forEach((input) => {
                    if (!(input instanceof HTMLInputElement) || input.classList.contains('acf-checkbox-toggle')) {
                        return;
                    }

                    input.checked = checked;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });

                const toggleInput = bucket.querySelector('.acf-checkbox-list input[type="checkbox"].acf-checkbox-toggle');
                if (toggleInput instanceof HTMLInputElement) {
                    toggleInput.checked = checked;
                    toggleInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            };

            const setSectionCheckboxesChecked = (sectionField, checked) => {
                if (!(sectionField instanceof HTMLElement)) {
                    return;
                }

                const buckets = sectionField.querySelectorAll(':scope > .acf-input > .meza-acf-export-bucket');
                buckets.forEach((bucket) => {
                    if (bucket instanceof HTMLElement) {
                        setBucketCheckboxesChecked(bucket, checked);
                    }
                });
            };

            const getSectionSelectableInputs = (sectionField) => {
                if (!(sectionField instanceof HTMLElement)) {
                    return [];
                }

                return Array.from(sectionField.querySelectorAll(
                    ':scope > .acf-input > .meza-acf-export-bucket .acf-checkbox-list input[type="checkbox"]'
                )).filter((input) => input instanceof HTMLInputElement && !input.classList.contains('acf-checkbox-toggle'));
            };

            const initBucketToggles = () => {
                const buckets = document.querySelectorAll('#acf-admin-tools .meza-acf-export-bucket');

                buckets.forEach((bucket) => {
                    if (!(bucket instanceof HTMLElement) || bucket.dataset.mezaBucketToggleReady === '1') {
                        return;
                    }

                    const labelWrap = bucket.querySelector(':scope > .acf-label');
                    const label = labelWrap ? labelWrap.querySelector('label') : null;
                    const inputWrap = bucket.querySelector(':scope > .acf-input');

                    if (!(labelWrap instanceof HTMLElement) || !(label instanceof HTMLElement) || !(inputWrap instanceof HTMLElement)) {
                        return;
                    }

                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'meza-acf-export-bucket-toggle';
                    bucket.mezaSyncButtonLabel = () => {
                        button.textContent = bucket.classList.contains('is-collapsed') ? 'Expand' : 'Collapse';
                    };

                    button.addEventListener('click', () => {
                        const nextState = bucket.classList.contains('is-collapsed') ? '0' : '1';
                        const sectionField = bucket.closest('.meza-acf-export-section');
                        bucket.dataset.mezaCollapsed = nextState;

                        applyBucketState(bucket);
                        syncSectionToggle(sectionField);
                    });

                    applyBucketState(bucket);
                    labelWrap.appendChild(button);
                    bucket.dataset.mezaBucketToggleReady = '1';
                });
            };

            const initSectionToggles = () => {
                const sections = document.querySelectorAll('#acf-admin-tools .meza-acf-export-section');

                sections.forEach((sectionField) => {
                    if (!(sectionField instanceof HTMLElement) || sectionField.dataset.mezaSectionToggleReady === '1') {
                        return;
                    }

                    const buckets = getSectionManagedBuckets(sectionField);
                    if (buckets.length <= 1) {
                        return;
                    }

                    const labelWrap = sectionField.querySelector(':scope > .acf-label');
                    if (!(labelWrap instanceof HTMLElement)) {
                        return;
                    }

                    const controlsWrap = document.createElement('span');
                    controlsWrap.className = 'meza-acf-export-section-controls';

                    const selectToggleButton = document.createElement('button');
                    selectToggleButton.type = 'button';
                    selectToggleButton.className = 'meza-acf-export-section-action';
                    selectToggleButton.setAttribute(
                        'aria-label',
                        'Toggle all export choices for ' + (getSectionLabel(sectionField) || 'this section')
                    );

                    const expandToggleButton = document.createElement('button');
                    expandToggleButton.type = 'button';
                    expandToggleButton.className = 'meza-acf-export-section-action';
                    expandToggleButton.setAttribute(
                        'aria-label',
                        'Expand or collapse all export groups for ' + (getSectionLabel(sectionField) || 'this section')
                    );

                    controlsWrap.append(selectToggleButton, expandToggleButton);

                    sectionField.mezaSectionToggleSync = () => {
                        const inputs = getSectionSelectableInputs(sectionField);
                        const checkedCount = inputs.filter((input) => input.checked).length;
                        const expandedCount = buckets.filter((bucket) => !bucket.classList.contains('is-collapsed')).length;
                        const allExpanded = buckets.length > 0 && expandedCount === buckets.length;
                        const allSelected = inputs.length > 0 && checkedCount === inputs.length;

                        selectToggleButton.textContent = allSelected ? 'Deselect All' : 'Select All';
                        expandToggleButton.textContent = allExpanded ? 'Collapse All' : 'Expand All';
                    };

                    selectToggleButton.addEventListener('click', () => {
                        const inputs = getSectionSelectableInputs(sectionField);
                        const shouldCheck = !(
                            inputs.length > 0
                            && inputs.every((input) => input.checked)
                        );
                        sectionField.dataset.mezaBulkToggleActive = '1';

                        try {
                            setSectionCheckboxesChecked(sectionField, shouldCheck);
                        } finally {
                            delete sectionField.dataset.mezaBulkToggleActive;
                        }

                        syncSectionToggle(sectionField);
                    });

                    expandToggleButton.addEventListener('click', () => {
                        const shouldExpand = buckets.some((bucket) => bucket.classList.contains('is-collapsed'));

                        buckets.forEach((bucket) => {
                            bucket.dataset.mezaCollapsed = shouldExpand ? '0' : '1';
                            bucket.classList.toggle('is-collapsed', !shouldExpand);
                            if (typeof bucket.mezaSyncButtonLabel === 'function') {
                                bucket.mezaSyncButtonLabel();
                            }
                        });

                        syncSectionToggle(sectionField);
                    });

                    sectionField.addEventListener('change', (event) => {
                        const target = event.target;
                        if (!(target instanceof HTMLInputElement) || target.type !== 'checkbox') {
                            return;
                        }

                        if (!target.closest('.meza-acf-export-bucket')) {
                            return;
                        }

                        if (sectionField.dataset.mezaBulkToggleActive === '1') {
                            return;
                        }

                        syncSectionToggle(sectionField);
                    });

                    labelWrap.appendChild(controlsWrap);
                    sectionField.dataset.mezaSectionToggleReady = '1';
                    syncSectionToggle(sectionField);
                });
            };

            const initExportToolToggles = () => {
                initBucketToggles();
                initSectionToggles();
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initExportToolToggles, { once: true });
            } else {
                initExportToolToggles();
            }
        })();
    </script>
<?php
}, 20);
