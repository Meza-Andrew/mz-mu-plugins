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
        return '2026-05-02-list-products-location-v1';
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
        return '2026-05-03-section-group-sub-fields-v1';
    }

    function meza_get_section_group_sub_fields_sync_option_name(): string
    {
        return 'meza_section_group_sub_fields_sync_version';
    }

    function meza_sync_section_group_sub_fields(): void
    {
        if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields') || !function_exists('acf_update_field')) {
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

            foreach ((array) acf_get_fields($field_group_id) as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $field_name = sanitize_key((string) ($field['name'] ?? ''));
                $field_type = (string) ($field['type'] ?? '');
                if ($field_type !== 'group' || !str_starts_with($field_name, 'section_')) {
                    continue;
                }

                $existing_sub_fields = isset($field['sub_fields']) && is_array($field['sub_fields'])
                    ? $field['sub_fields']
                    : [];
                $normalized_sub_fields = meza_normalize_section_sub_fields($existing_sub_fields, $field_name, '');

                if ($normalized_sub_fields === $existing_sub_fields) {
                    continue;
                }

                $field['sub_fields'] = $normalized_sub_fields;
                acf_update_field($field);
                $did_update = true;
            }
        }

        if ($did_update || (string) get_option(meza_get_section_group_sub_fields_sync_option_name(), '') !== $version) {
            update_option(meza_get_section_group_sub_fields_sync_option_name(), $version, false);
        }
    }
}
add_action('acf/init', 'meza_sync_section_group_sub_fields', 24);

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
            'List Services Section' => 3,
            'List Products Section' => 4,
            'List Reviews Section' => 6,
            'List Brands Section' => 8,
            'Gallery Section' => 10,
            'List Partners Section' => 11,
            'List Profiles Section' => 9,
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
add_action('init', 'meza_migrate_legacy_list_testimonials_meta_once', 29);
add_action('init', 'meza_sync_list_reviews_reference_values_once', 30);
add_action('init', 'meza_reset_legacy_section_text_defaults_once', 31);

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

        #acf-admin-tools .meza-acf-export-bucket.is-collapsed > .acf-input {
            display: none;
        }
    </style>
    <script>
        (() => {
            const storageKeyPrefix = 'mezaAcfExportBucketState:v1:';

            const getBucketStorageKey = (bucket, groupKey) => {
                const sectionField = bucket.closest('.acf-field');
                const sectionLabel = sectionField instanceof HTMLElement
                    ? ((sectionField.querySelector(':scope > .acf-label > label')?.textContent) || '').trim()
                    : '';

                return storageKeyPrefix + sectionLabel + ':' + groupKey;
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
                    const groupKey = bucket.dataset.groupKey || '';
                    const storageKey = getBucketStorageKey(bucket, groupKey);

                    const syncButtonLabel = () => {
                        button.textContent = bucket.classList.contains('is-collapsed') ? 'Expand' : 'Collapse';
                    };

                    button.addEventListener('click', () => {
                        bucket.classList.toggle('is-collapsed');
                        try {
                            window.localStorage.setItem(storageKey, bucket.classList.contains('is-collapsed') ? 'collapsed' : 'expanded');
                        } catch (error) {
                        }
                        syncButtonLabel();
                    });

                    let savedState = '';
                    try {
                        savedState = window.localStorage.getItem(storageKey) || '';
                    } catch (error) {
                    }

                    if (savedState === 'collapsed') {
                        bucket.classList.add('is-collapsed');
                    } else if (savedState === 'expanded') {
                        bucket.classList.remove('is-collapsed');
                    } else if (groupKey === 'defaults' || groupKey === 'built_in') {
                        bucket.classList.add('is-collapsed');
                    }

                    syncButtonLabel();
                    labelWrap.appendChild(button);
                    bucket.dataset.mezaBucketToggleReady = '1';
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initBucketToggles, { once: true });
            } else {
                initBucketToggles();
            }
        })();
    </script>
<?php
}, 20);
