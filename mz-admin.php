<?php

/**
 * Plugin Name: MZ Admin
 * Description: Admin behavior, editorial workflow, and dashboard customization.
 * Version: 1.1.222
 * Author: Meza LLC
 * Author URI: https://meza.design
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) return;

if (file_exists(__DIR__ . '/mz-hosting.php')) {
    require_once __DIR__ . '/mz-hosting.php';
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        if ($needle === '') return true;
        return strpos((string) $haystack, (string) $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        $needle = (string) $needle;
        if ($needle === '') return true;
        return strncmp((string) $haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('meza_submission_manager_capability')) {
    function meza_submission_manager_capability(): string
    {
        return 'mzf_manage_submissions';
    }
}

if (!function_exists('meza_site_manager_role_key')) {
    function meza_site_manager_role_key(): string
    {
        return 'site_manager';
    }
}

if (!function_exists('meza_events_manager_role_key')) {
    function meza_events_manager_role_key(): string
    {
        return 'events_manager';
    }
}

if (!function_exists('meza_events_editor_role_key')) {
    function meza_events_editor_role_key(): string
    {
        return 'events_editor';
    }
}

if (!function_exists('meza_site_has_subscribers')) {
    function meza_site_has_subscribers(): bool
    {
        if (!function_exists('count_users')) {
            return false;
        }

        $counts = count_users();

        return !empty($counts['avail_roles']['subscriber']);
    }
}

if (!function_exists('meza_backup_manager_capability')) {
    function meza_backup_manager_capability(): string
    {
        return 'meza_manage_backups';
    }
}

if (!function_exists('meza_has_woocommerce_plugin')) {
    function meza_has_woocommerce_plugin(): bool
    {
        if (class_exists('WooCommerce')) {
            return true;
        }

        if (!defined('WP_PLUGIN_DIR')) {
            return false;
        }

        return file_exists(WP_PLUGIN_DIR . '/woocommerce/woocommerce.php');
    }
}

if (!function_exists('meza_woocommerce_capabilities')) {
    function meza_woocommerce_capabilities(): array
    {
        if (!meza_has_woocommerce_plugin()) {
            return [];
        }

        $caps = [
            'manage_woocommerce',
            'create_customers',
            'view_woocommerce_reports',
        ];

        foreach (['product', 'shop_order', 'shop_coupon'] as $capability_type) {
            $caps = array_merge($caps, [
                "edit_{$capability_type}",
                "read_{$capability_type}",
                "delete_{$capability_type}",
                "edit_{$capability_type}s",
                "edit_others_{$capability_type}s",
                "publish_{$capability_type}s",
                "read_private_{$capability_type}s",
                "delete_{$capability_type}s",
                "delete_private_{$capability_type}s",
                "delete_published_{$capability_type}s",
                "delete_others_{$capability_type}s",
                "edit_private_{$capability_type}s",
                "edit_published_{$capability_type}s",
                "manage_{$capability_type}_terms",
                "edit_{$capability_type}_terms",
                "delete_{$capability_type}_terms",
                "assign_{$capability_type}_terms",
            ]);
        }

        return array_values(array_unique($caps));
    }
}

// Keep Coupons as a WooCommerce submenu item instead of WooCommerce Marketing.
add_filter('woocommerce_register_post_type_shop_coupon', function ($args) {
    if (!is_array($args)) {
        return $args;
    }

    $args['show_in_menu'] = current_user_can('manage_woocommerce') ? 'woocommerce' : true;
    return $args;
}, 1000);

// Keep the correct WooCommerce menu highlighted on coupon screens.
add_action('admin_head', function (): void {
    global $parent_file, $submenu_file, $post_type;

    if ((string) $post_type !== 'shop_coupon') {
        return;
    }

    $parent_file = 'woocommerce';
    $submenu_file = 'edit.php?post_type=shop_coupon';
}, 1000);

if (!function_exists('meza_customer_sign_generator_capability')) {
    function meza_customer_sign_generator_capability(): string
    {
        return 'manage_client_sign_creator';
    }
}

if (!function_exists('meza_site_manager_capabilities')) {
    function meza_site_manager_capabilities(): array
    {
        $caps = [
            'read' => true,
            meza_submission_manager_capability() => true,
            meza_customer_sign_generator_capability() => true,
        ];

        $editor_role = get_role('editor');
        if ($editor_role instanceof WP_Role) {
            foreach ((array) $editor_role->capabilities as $cap => $grant) {
                if ($grant) {
                    $caps[(string) $cap] = true;
                }
            }
        }

        $site_manager_extras = [
            meza_submission_manager_capability(),
            meza_customer_sign_generator_capability(),
            'edit_theme_options',
            'list_users',
            'import',
            'export',
        ];

        foreach ($site_manager_extras as $cap) {
            $caps[$cap] = true;
        }

        foreach (meza_woocommerce_capabilities() as $cap) {
            $caps[$cap] = true;
        }

        return $caps;
    }
}

if (!function_exists('meza_sync_site_manager_role')) {
    function meza_sync_site_manager_role(): void
    {
        $role_key = meza_site_manager_role_key();
        $target_caps = meza_site_manager_capabilities();
        $role = get_role($role_key);

        if (!($role instanceof WP_Role)) {
            add_role($role_key, 'Site Manager', $target_caps);
            $role = get_role($role_key);
        }

        if ($role instanceof WP_Role) {
            foreach ($target_caps as $cap => $grant) {
                if ((bool) $grant && !$role->has_cap($cap)) {
                    $role->add_cap($cap);
                }
            }

            foreach ((array) $role->capabilities as $cap => $grant) {
                if (array_key_exists($cap, $target_caps)) {
                    if ((bool) $grant !== (bool) $target_caps[$cap]) {
                        if ($target_caps[$cap]) {
                            $role->add_cap($cap);
                        } else {
                            $role->remove_cap($cap);
                        }
                    }
                    continue;
                }

                $role->remove_cap($cap);
            }
        }

        $administrator_role = get_role('administrator');
        if ($administrator_role instanceof WP_Role) {
            if (!$administrator_role->has_cap(meza_submission_manager_capability())) {
                $administrator_role->add_cap(meza_submission_manager_capability());
            }

            if (!$administrator_role->has_cap(meza_backup_manager_capability())) {
                $administrator_role->add_cap(meza_backup_manager_capability());
            }

            if (!$administrator_role->has_cap(meza_customer_sign_generator_capability())) {
                $administrator_role->add_cap(meza_customer_sign_generator_capability());
            }
        }

        $current_user = wp_get_current_user();
        if (
            $current_user instanceof WP_User
            && (
                in_array($role_key, (array) $current_user->roles, true)
                || in_array('administrator', (array) $current_user->roles, true)
            )
        ) {
            $current_user->get_role_caps();
        }
    }
}
add_action('init', 'meza_sync_site_manager_role', 20);

if (!function_exists('meza_get_post_type_capabilities')) {
    function meza_get_post_type_capabilities(string $post_type): array
    {
        $post_type = trim($post_type);
        if ($post_type === '') {
            return [];
        }

        $post_type_object = get_post_type_object($post_type);
        if (!($post_type_object instanceof WP_Post_Type) || !isset($post_type_object->cap)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            'strval',
            (array) $post_type_object->cap
        ))));
    }
}

if (!function_exists('meza_get_event_post_capability_source_map')) {
    function meza_get_event_post_capability_source_map(): array
    {
        return [
            'edit_event' => 'edit_post',
            'read_event' => 'read_post',
            'delete_event' => 'delete_post',
            'edit_events' => 'edit_posts',
            'create_events' => 'edit_posts',
            'edit_others_events' => 'edit_others_posts',
            'publish_events' => 'publish_posts',
            'read_private_events' => 'read_private_posts',
            'delete_events' => 'delete_posts',
            'delete_private_events' => 'delete_private_posts',
            'delete_published_events' => 'delete_published_posts',
            'delete_others_events' => 'delete_others_posts',
            'edit_private_events' => 'edit_private_posts',
            'edit_published_events' => 'edit_published_posts',
        ];
    }
}

if (!function_exists('meza_get_post_type_taxonomy_capabilities')) {
    function meza_get_post_type_taxonomy_capabilities(string $post_type): array
    {
        $caps = [];

        foreach (get_object_taxonomies($post_type, 'objects') as $taxonomy) {
            if (!($taxonomy instanceof WP_Taxonomy) || !isset($taxonomy->cap)) {
                continue;
            }

            foreach ((array) $taxonomy->cap as $cap) {
                $cap = trim((string) $cap);
                if ($cap !== '') {
                    $caps[] = $cap;
                }
            }
        }

        return array_values(array_unique($caps));
    }
}

if (!function_exists('meza_is_events_limited_role')) {
    function meza_is_events_limited_role($user = null): bool
    {
        if ($user === null) {
            $user = wp_get_current_user();
        } elseif (is_numeric($user)) {
            $user = get_userdata((int) $user);
        }

        if (!($user instanceof WP_User)) {
            return false;
        }

        $role_keys = [
            meza_events_manager_role_key(),
            meza_events_editor_role_key(),
        ];

        foreach ($role_keys as $role_key) {
            if (in_array($role_key, (array) $user->roles, true)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('meza_is_events_editor_role')) {
    function meza_is_events_editor_role($user = null): bool
    {
        if ($user === null) {
            $user = wp_get_current_user();
        } elseif (is_numeric($user)) {
            $user = get_userdata((int) $user);
        }

        return $user instanceof WP_User
            && in_array(meza_events_editor_role_key(), (array) $user->roles, true);
    }
}

if (!function_exists('meza_user_has_any_role')) {
    function meza_user_has_any_role($user, array $roles): bool
    {
        if ($user === null) {
            $user = wp_get_current_user();
        } elseif (is_numeric($user)) {
            $user = get_userdata((int) $user);
        }

        if (!($user instanceof WP_User)) {
            return false;
        }

        foreach ($roles as $role) {
            if (in_array((string) $role, (array) $user->roles, true)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('meza_can_access_profile_two_factor')) {
    function meza_can_access_profile_two_factor($user = null): bool
    {
        if ($user === null) {
            $user = wp_get_current_user();
        } elseif (is_numeric($user)) {
            $user = get_userdata((int) $user);
        }

        if (!($user instanceof WP_User)) {
            return false;
        }

        if (in_array('administrator', (array) $user->roles, true)) {
            return true;
        }

        return meza_user_has_any_role($user, [
            'seo_manager',
            meza_site_manager_role_key(),
            'shop_manager',
        ]);
    }
}

if (!function_exists('meza_can_access_clear_cache')) {
    function meza_can_access_clear_cache($user = null): bool
    {
        return meza_user_has_any_role($user, [
            'administrator',
            meza_site_manager_role_key(),
        ]);
    }
}

if (!function_exists('meza_can_access_content_permissions_panel')) {
    function meza_can_access_content_permissions_panel($user = null): bool
    {
        return meza_user_has_any_role($user, [
            'administrator',
            meza_site_manager_role_key(),
        ]);
    }
}

if (!function_exists('meza_can_access_admin_bar_new_content_node')) {
    function meza_can_access_admin_bar_new_content_node($node): bool
    {
        if (!is_object($node)) {
            return false;
        }

        $node_id = (string) ($node->id ?? '');
        $href = (string) ($node->href ?? '');

        if (meza_is_events_limited_role()) {
            $allowed_node_ids = [
                'new-event',
                'new-media',
            ];

            if (!meza_is_events_editor_role()) {
                $allowed_node_ids[] = 'new-post';
            }

            if (in_array($node_id, $allowed_node_ids, true)) {
                return true;
            }

            if (str_contains($href, 'media-new.php')) {
                return true;
            }

            if (str_contains($href, 'post-new.php')) {
                $query = (string) parse_url($href, PHP_URL_QUERY);
                if ($query !== '') {
                    parse_str($query, $query_args);
                    $post_type = sanitize_key((string) ($query_args['post_type'] ?? 'post'));

                    if ($post_type === 'event') {
                        return true;
                    }

                    if ($post_type === 'post' && !meza_is_events_editor_role()) {
                        return true;
                    }
                } else {
                    return !meza_is_events_editor_role();
                }
            }

            return false;
        }

        if ($node_id === 'new-media' || str_contains($href, 'media-new.php')) {
            return current_user_can('upload_files');
        }

        if (str_contains($href, 'post-new.php')) {
            $post_type = 'post';

            $query = (string) parse_url($href, PHP_URL_QUERY);
            if ($query !== '') {
                parse_str($query, $query_args);
                if (!empty($query_args['post_type']) && is_string($query_args['post_type'])) {
                    $post_type = sanitize_key($query_args['post_type']);
                }
            }

            $post_type_object = get_post_type_object($post_type);
            if (!($post_type_object instanceof WP_Post_Type) || !isset($post_type_object->cap)) {
                return false;
            }

            $create_cap = (string) ($post_type_object->cap->create_posts ?? '');
            if ($create_cap !== '') {
                return current_user_can($create_cap);
            }

            $edit_cap = (string) ($post_type_object->cap->edit_posts ?? '');
            return $edit_cap !== '' && current_user_can($edit_cap);
        }

        return true;
    }
}

if (!function_exists('meza_get_events_role_capabilities')) {
    function meza_get_events_role_capabilities(bool $include_posts = true): array
    {
        $caps = [
            'read' => true,
            'upload_files' => true,
        ];

        $post_types = ['event', 'attachment'];
        if ($include_posts) {
            $post_types[] = 'post';
        }

        foreach ($post_types as $post_type) {
            foreach (meza_get_post_type_capabilities($post_type) as $cap) {
                $caps[$cap] = true;
            }

            foreach (meza_get_post_type_taxonomy_capabilities($post_type) as $cap) {
                $caps[$cap] = true;
            }
        }

        return $caps;
    }
}

if (!function_exists('meza_sync_events_roles')) {
    function meza_sync_events_roles(): void
    {
        $roles_to_sync = [
            meza_events_manager_role_key() => [
                'label' => 'Events Manager',
                'caps' => meza_get_events_role_capabilities(true),
            ],
            meza_events_editor_role_key() => [
                'label' => 'Events Editor',
                'caps' => meza_get_events_role_capabilities(false),
            ],
        ];

        foreach ($roles_to_sync as $role_key => $config) {
            $target_caps = (array) ($config['caps'] ?? []);
            $role = get_role($role_key);

            if (!($role instanceof WP_Role)) {
                add_role($role_key, (string) ($config['label'] ?? $role_key), $target_caps);
                $role = get_role($role_key);
            }

            if (!($role instanceof WP_Role)) {
                continue;
            }

            foreach ($target_caps as $cap => $grant) {
                if ((bool) $grant && !$role->has_cap($cap)) {
                    $role->add_cap($cap);
                }
            }

            foreach ((array) $role->capabilities as $cap => $grant) {
                if (array_key_exists($cap, $target_caps)) {
                    if ((bool) $grant !== (bool) $target_caps[$cap]) {
                        if ($target_caps[$cap]) {
                            $role->add_cap($cap);
                        } else {
                            $role->remove_cap($cap);
                        }
                    }
                    continue;
                }

                $role->remove_cap($cap);
            }
        }

        $current_user = wp_get_current_user();
        if (meza_is_events_limited_role($current_user)) {
            $current_user->get_role_caps();
        }
    }
}
add_action('init', 'meza_sync_events_roles', 20);

if (!function_exists('meza_sync_event_capabilities_from_post_access')) {
    function meza_sync_event_capabilities_from_post_access(): void
    {
        $wp_roles = wp_roles();
        if (!($wp_roles instanceof WP_Roles)) {
            return;
        }

        $skip_roles = [
            meza_events_manager_role_key(),
            meza_events_editor_role_key(),
        ];

        foreach (array_keys((array) $wp_roles->roles) as $role_key) {
            $role_key = (string) $role_key;
            if ($role_key === '' || in_array($role_key, $skip_roles, true)) {
                continue;
            }

            $role = get_role($role_key);
            if (!($role instanceof WP_Role)) {
                continue;
            }

            foreach (meza_get_event_post_capability_source_map() as $target_cap => $source_cap) {
                $target_cap = trim((string) $target_cap);
                $source_cap = trim((string) $source_cap);
                if ($target_cap === '' || $source_cap === '') {
                    continue;
                }

                if ($role->has_cap($source_cap)) {
                    if (!$role->has_cap($target_cap)) {
                        $role->add_cap($target_cap);
                    }
                    continue;
                }

                if ($role->has_cap($target_cap)) {
                    $role->remove_cap($target_cap);
                }
            }
        }

        $current_user = wp_get_current_user();
        if ($current_user instanceof WP_User) {
            $current_user->get_role_caps();
        }
    }
}
add_action('init', 'meza_sync_event_capabilities_from_post_access', 21);

if (!function_exists('meza_sync_shop_manager_role_label')) {
    function meza_sync_shop_manager_role_label(): void
    {
        if (!meza_has_woocommerce_plugin()) {
            return;
        }

        $wp_roles = wp_roles();
        if (!($wp_roles instanceof WP_Roles)) {
            return;
        }

        $role_key = 'shop_manager';
        $target_name = 'Shop Manager';
        $role = $wp_roles->roles[$role_key] ?? null;

        if (!is_array($role) || ($role['name'] ?? '') === $target_name) {
            return;
        }

        $wp_roles->roles[$role_key]['name'] = $target_name;
        $wp_roles->role_names[$role_key] = $target_name;

        update_option($wp_roles->role_key, $wp_roles->roles);
    }
}
add_action('init', 'meza_sync_shop_manager_role_label', 21);

add_filter('gettext_with_context', function ($translation, $text, $context, $domain) {
    if ($context === 'User role' && $text === 'Shop manager') {
        return 'Shop Manager';
    }

    return $translation;
}, 20, 4);

if (!function_exists('meza_normalize_add_post_type_label')) {
    function meza_normalize_add_post_type_label(string $label): string
    {
        $label = trim(wp_strip_all_tags($label));
        if ($label === '') {
            return 'Add Item';
        }

        if (preg_match('/^add new\s+/i', $label) === 1) {
            $label = preg_replace('/^add new\s+/i', '', $label) ?? $label;
        }

        if (preg_match('/^add\s+/i', $label) === 1) {
            return $label;
        }

        return 'Add ' . $label;
    }
}

if (!function_exists('meza_get_post_type_add_item_label')) {
    function meza_get_post_type_add_item_label($labels): string
    {
        $menu_name = '';

        if (is_array($labels)) {
            $menu_name = (string) ($labels['name'] ?? $labels['singular_name'] ?? '');
        } elseif (is_object($labels)) {
            $menu_name = (string) ($labels->name ?? $labels->singular_name ?? '');
        }

        return meza_normalize_add_post_type_label($menu_name);
    }
}

add_filter('register_post_type_args', function (array $args, string $post_type): array {
    if ($post_type === 'event') {
        $args['capability_type'] = ['event', 'events'];
        $args['map_meta_cap'] = true;

        $capabilities = isset($args['capabilities']) && is_array($args['capabilities'])
            ? $args['capabilities']
            : [];
        $capabilities['create_posts'] = 'create_events';
        $args['capabilities'] = $capabilities;
    }

    $labels = isset($args['labels']) && is_array($args['labels']) ? $args['labels'] : [];
    if ($labels === []) {
        return $args;
    }

    $labels['add_new_item'] = meza_get_post_type_add_item_label($labels);
    $args['labels'] = $labels;

    return $args;
}, 20, 2);

add_action('registered_post_type', function (string $post_type, WP_Post_Type $post_type_object): void {
    if (!isset($post_type_object->labels) || !is_object($post_type_object->labels)) {
        return;
    }

    $post_type_object->labels->add_new_item = meza_get_post_type_add_item_label($post_type_object->labels);
}, 20, 2);

add_filter('editable_roles', function (array $roles): array {
    if (!is_admin()) {
        return $roles;
    }

    global $pagenow;

    if ($pagenow !== 'user-edit.php') {
        return $roles;
    }

    uasort($roles, static function ($left, $right): int {
        $left_name = wp_strip_all_tags((string) ($left['name'] ?? ''));
        $right_name = wp_strip_all_tags((string) ($right['name'] ?? ''));
        $comparison = strcasecmp($right_name, $left_name);

        if ($comparison !== 0) {
            return $comparison;
        }

        return strcasecmp((string) ($right['name'] ?? ''), (string) ($left['name'] ?? ''));
    });

    return $roles;
}, 1000);

if (!function_exists('meza_sync_site_kit_dashboard_sharing')) {
    function meza_sync_site_kit_dashboard_sharing(): void
    {
        if (!defined('WP_PLUGIN_DIR') || !file_exists(WP_PLUGIN_DIR . '/google-site-kit/google-site-kit.php')) {
            return;
        }

        $option_name = 'googlesitekit_dashboard_sharing';
        $sharing_settings = get_option($option_name);
        if (!is_array($sharing_settings)) {
            $sharing_settings = [];
        }

        $shared_roles = array_values(array_filter(array_unique([
            'shop_manager',
            meza_site_manager_role_key(),
        ]), static function ($role_slug): bool {
            $role = get_role((string) $role_slug);
            return $role instanceof WP_Role && $role->has_cap('edit_posts');
        }));

        if (empty($shared_roles)) {
            return;
        }

        $target_modules = [
            'pagespeed-insights' => 'all_admins',
            'analytics-4' => 'owner',
            'search-console' => 'owner',
        ];

        $changed = false;

        foreach ($target_modules as $module_slug => $default_management) {
            $module_settings = $sharing_settings[$module_slug] ?? [];
            if (!is_array($module_settings)) {
                $module_settings = [];
            }

            $existing_shared_roles = $module_settings['sharedRoles'] ?? [];
            if (!is_array($existing_shared_roles)) {
                $existing_shared_roles = [];
            }

            $normalized_shared_roles = array_values(array_filter(array_unique(array_map(
                'strval',
                array_merge($existing_shared_roles, $shared_roles)
            ))));

            if (($module_settings['sharedRoles'] ?? null) !== $normalized_shared_roles) {
                $module_settings['sharedRoles'] = $normalized_shared_roles;
                $changed = true;
            }

            if (!isset($module_settings['management']) || !is_string($module_settings['management']) || $module_settings['management'] === '') {
                $module_settings['management'] = $default_management;
                $changed = true;
            }

            $sharing_settings[$module_slug] = $module_settings;
        }

        if ($changed) {
            update_option($option_name, $sharing_settings, false);
        }
    }
}
add_action('init', 'meza_sync_site_kit_dashboard_sharing', 25);

add_filter('map_meta_cap', function (array $caps, string $cap, int $user_id, array $args): array {
    static $mapping_site_kit_caps = false;

    if ($mapping_site_kit_caps) {
        return $caps;
    }

    $dashboard_caps = [
        'googlesitekit_view_dashboard',
        'googlesitekit_view_splash',
        'googlesitekit_view_posts_insights',
    ];
    $widget_caps = [
        'googlesitekit_view_wp_dashboard_widget',
        'googlesitekit_view_admin_bar_menu',
    ];

    if (!in_array($cap, array_merge($dashboard_caps, $widget_caps), true) || $user_id <= 0) {
        return $caps;
    }

    $mapping_site_kit_caps = true;
    $can_view_shared_dashboard = user_can($user_id, 'googlesitekit_view_shared_dashboard');
    $can_view_shared_widget = false;
    if ($can_view_shared_dashboard && in_array($cap, $widget_caps, true)) {
        $can_view_shared_widget = user_can($user_id, 'googlesitekit_read_shared_module_data', 'analytics-4')
            || user_can($user_id, 'googlesitekit_read_shared_module_data', 'search-console');
    }
    $mapping_site_kit_caps = false;

    if (in_array($cap, $widget_caps, true)) {
        if (!$can_view_shared_widget) {
            return $caps;
        }

        return ['read'];
    }

    if (!$can_view_shared_dashboard) {
        return $caps;
    }

    return ['read'];
}, 20, 4);

add_filter('user_has_cap', function (array $allcaps, array $caps, array $args, WP_User $user): array {
    static $bridging_site_kit_caps = false;

    if ($bridging_site_kit_caps || !($user instanceof WP_User) || $user->ID <= 0) {
        return $allcaps;
    }

    $requested_cap = (string) ($args[0] ?? '');
    if (!in_array($requested_cap, [
        'googlesitekit_view_posts_insights',
        'googlesitekit_view_dashboard',
        'googlesitekit_view_splash',
        'googlesitekit_view_shared_dashboard',
        'googlesitekit_read_shared_module_data',
        'googlesitekit_view_wp_dashboard_widget',
        'googlesitekit_view_admin_bar_menu',
    ], true)) {
        return $allcaps;
    }

    $bridging_site_kit_caps = true;
    $can_view_shared_dashboard = user_can($user->ID, 'googlesitekit_view_shared_dashboard');
    $can_view_shared_widget = false;
    if ($can_view_shared_dashboard) {
        $can_view_shared_widget = user_can($user->ID, 'googlesitekit_read_shared_module_data', 'analytics-4')
            || user_can($user->ID, 'googlesitekit_read_shared_module_data', 'search-console');
    }
    $bridging_site_kit_caps = false;

    if (!$can_view_shared_dashboard) {
        return $allcaps;
    }

    $allcaps['googlesitekit_view_posts_insights'] = true;
    $allcaps['googlesitekit_view_dashboard'] = true;
    $allcaps['googlesitekit_view_splash'] = true;
    if ($can_view_shared_widget) {
        $allcaps['googlesitekit_view_wp_dashboard_widget'] = true;
        $allcaps['googlesitekit_view_admin_bar_menu'] = true;
    }

    return $allcaps;
}, 20, 4);

add_action('admin_init', function (): void {
    if (!is_admin()) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
    if ($page !== 'googlesitekit-dashboard') {
        return;
    }

    if (current_user_can('googlesitekit_view_dashboard') || !current_user_can('googlesitekit_view_splash')) {
        return;
    }

    $redirect_args = ['page' => 'googlesitekit-splash'];
    foreach (['notification', 'panel'] as $arg) {
        if (!isset($_GET[$arg])) {
            continue;
        }

        $value = sanitize_text_field(wp_unslash((string) $_GET[$arg]));
        if ($value !== '') {
            $redirect_args[$arg] = $value;
        }
    }

    wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
    exit;
}, 5);

if (!function_exists('meza_get_site_kit_menu_icon')) {
    function meza_get_site_kit_menu_icon(): string
    {
        global $menu;

        $google_icon_class = '\Google\Site_Kit\Core\Util\Google_Icon';

        if (!is_array($menu)) {
            return class_exists($google_icon_class)
                ? 'data:image/svg+xml;base64,' . $google_icon_class::to_base64()
                : 'dashicons-chart-area';
        }

        foreach ($menu as $item) {
            if (!is_array($item) || (($item[2] ?? '') !== 'googlesitekit-dashboard')) {
                continue;
            }

            $candidate_icon = (string) ($item[6] ?? '');
            if ($candidate_icon !== '') {
                return $candidate_icon;
            }

            break;
        }

        return class_exists($google_icon_class)
            ? 'data:image/svg+xml;base64,' . $google_icon_class::to_base64()
            : 'dashicons-chart-area';
    }
}

if (!function_exists('meza_has_site_kit_top_level_menu_item')) {
    function meza_has_site_kit_top_level_menu_item(): bool
    {
        global $menu;

        if (!is_array($menu)) {
            return false;
        }

        foreach ($menu as $item) {
            if (!is_array($item)) {
                continue;
            }

            $slug = strtolower((string) ($item[2] ?? ''));
            $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
            $is_site_kit = $slug === 'meza-web-analytics'
                || str_contains($slug, 'googlesitekit')
                || str_contains($slug, 'google-site-kit')
                || str_contains($slug, 'site-kit')
                || in_array($title, ['web analytics', 'site kit', 'site kit by google'], true);

            if ($is_site_kit) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('meza_restore_site_kit_admin_menu')) {
    function meza_restore_site_kit_admin_menu(): void
    {
        $can_view_splash = current_user_can('googlesitekit_view_splash');
        $can_view_dashboard = current_user_can('googlesitekit_view_dashboard');
        $can_view_shared_dashboard = current_user_can('googlesitekit_view_shared_dashboard');
        $has_dashboard_access = $can_view_dashboard || $can_view_shared_dashboard;
        $use_custom_menu = !current_user_can('manage_options');

        if (!$can_view_splash && !$has_dashboard_access) {
            return;
        }

        remove_menu_page('meza-web-analytics');

        if ($use_custom_menu || !$has_dashboard_access) {
            remove_menu_page('googlesitekit-dashboard');
        }

        if (!$use_custom_menu && $has_dashboard_access && meza_has_site_kit_top_level_menu_item()) {
            return;
        }

        $target_page = $has_dashboard_access ? 'googlesitekit-dashboard' : 'googlesitekit-splash';

        add_menu_page(
            'Web Analytics',
            'Web Analytics',
            'read',
            'meza-web-analytics',
            static function () use ($target_page): void {
                wp_safe_redirect(admin_url('admin.php?page=' . $target_page));
                exit;
            },
            meza_get_site_kit_menu_icon()
        );
    }
}
add_action('admin_menu', 'meza_restore_site_kit_admin_menu', PHP_INT_MAX - 3);

add_filter('parent_file', function ($parent_file) {
    if (!is_admin() || current_user_can('manage_options')) {
        return $parent_file;
    }

    $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
    if (!in_array($page, ['meza-web-analytics', 'googlesitekit-dashboard', 'googlesitekit-splash'], true)) {
        return $parent_file;
    }

    return 'meza-web-analytics';
}, PHP_INT_MAX);

add_filter('submenu_file', function ($submenu_file) {
    if (!is_admin() || current_user_can('manage_options')) {
        return $submenu_file;
    }

    $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
    if (!in_array($page, ['meza-web-analytics', 'googlesitekit-dashboard', 'googlesitekit-splash'], true)) {
        return $submenu_file;
    }

    return 'meza-web-analytics';
}, PHP_INT_MAX - 1);

add_filter('option_page_capability_updraft-options-group', function (): string {
    return meza_backup_manager_capability();
});

add_filter('acf/get_options_page', function ($page, $slug) {
    if ($slug === 'crm' && is_array($page)) {
        $page['capability'] = 'manage_options';
    }

    return $page;
}, 20, 2);

if (!function_exists('meza_should_hide_yoast_admin_menu_for_user')) {
    function meza_should_hide_yoast_admin_menu_for_user($user = null): bool
    {
        if (!($user instanceof WP_User)) {
            $user = wp_get_current_user();
        }

        if (!($user instanceof WP_User) || $user->ID <= 0) {
            return false;
        }

        return !user_can($user, 'manage_options');
    }
}

if (!function_exists('meza_remove_yoast_admin_menu_entries')) {
    function meza_remove_yoast_admin_menu_entries(): void
    {
        if (!meza_should_hide_yoast_admin_menu_for_user()) {
            return;
        }

        global $menu, $submenu;

        remove_menu_page('wpseo_dashboard');

        if (is_array($menu)) {
            foreach ($menu as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }

                $slug = strtolower((string) ($item[2] ?? ''));
                $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
                $is_yoast = ($slug !== '' && str_starts_with($slug, 'wpseo'))
                    || str_contains($slug, 'wpseo')
                    || str_contains($slug, 'wordpress-seo')
                    || str_contains($title, 'yoast')
                    || $title === 'seo';

                if ($is_yoast) {
                    unset($menu[$index]);
                }
            }

            $menu = array_values($menu);
        }

        if (!is_array($submenu)) {
            return;
        }

        foreach ($submenu as $parent_slug => &$items) {
            if (!is_array($items)) {
                continue;
            }

            $normalized_parent_slug = strtolower((string) $parent_slug);
            $parent_is_yoast = ($normalized_parent_slug !== '' && str_starts_with($normalized_parent_slug, 'wpseo'))
                || str_contains($normalized_parent_slug, 'wpseo')
                || str_contains($normalized_parent_slug, 'wordpress-seo');

            if ($parent_is_yoast) {
                unset($submenu[$parent_slug]);
                continue;
            }

            $items = array_values(array_filter($items, static function ($item): bool {
                if (!is_array($item)) {
                    return true;
                }

                $slug = strtolower((string) ($item[2] ?? ''));
                $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
                $is_yoast = ($slug !== '' && str_starts_with($slug, 'wpseo'))
                    || str_contains($slug, 'wpseo')
                    || str_contains($slug, 'wordpress-seo')
                    || str_contains($title, 'yoast')
                    || $title === 'seo';

                return !$is_yoast;
            }));
        }
        unset($items);
    }
}

add_filter('wpseo_submenu_pages', function (array $submenu_pages): array {
    if (meza_should_hide_yoast_admin_menu_for_user()) {
        return [];
    }

    return array_values(array_filter($submenu_pages, function ($item): bool {
        if (!is_array($item)) {
            return true;
        }

        $slug = (string) ($item[4] ?? '');
        return !in_array($slug, ['wpseo_workouts', 'wpseo_redirects'], true);
    }));
}, PHP_INT_MAX);

add_action('admin_menu', function (): void {
    remove_submenu_page('wpseo_dashboard', 'wpseo_workouts');
    remove_submenu_page('wpseo_dashboard', 'wpseo_redirects');
}, 99);

add_action('admin_init', function (): void {
    if (!is_admin()) {
        return;
    }

    $user = wp_get_current_user();
    $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';

    if (meza_should_hide_yoast_admin_menu_for_user($user) && str_starts_with($page, 'wpseo')) {
        wp_safe_redirect(admin_url());
        exit;
    }

    if (
        $user instanceof WP_User
        && in_array(meza_site_manager_role_key(), (array) $user->roles, true)
        && str_contains($page, 'updraft')
    ) {
        wp_safe_redirect(admin_url());
        exit;
    }

    if (!in_array($page, ['wpseo_workouts', 'wpseo_redirects'], true)) {
        return;
    }

    wp_safe_redirect(admin_url('admin.php?page=wpseo_dashboard'));
    exit;
}, 1);

add_action('admin_menu', function (): void {
    meza_remove_yoast_admin_menu_entries();

    $user = wp_get_current_user();
    if (!($user instanceof WP_User) || !in_array(meza_site_manager_role_key(), (array) $user->roles, true)) {
        return;
    }

    remove_menu_page('updraftplus');
    remove_menu_page('updraftcentral');

    global $menu;
    if (!is_array($menu)) {
        return;
    }

    foreach ($menu as $item) {
        $slug = (string) ($item[2] ?? '');
        $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
        $is_updraft = str_contains(strtolower($slug), 'updraft')
            || in_array($title, ['backups', 'updraft', 'updraftplus'], true);

        if ($is_updraft) {
            remove_menu_page($slug);
        }
    }
}, PHP_INT_MAX);

add_action('admin_head-themes.php', function (): void {
?>
    <style>
        .themes-php .wrap .wp-heading-inline {
            margin-bottom: 8px !important;
        }
        .themes-php .wrap .wp-heading-inline .title-count.theme-count {
            display: none !important;
        }
        .themes-php .theme-browser {
            margin-top: 12px !important;
            padding-top: 0 !important;
        }
        .themes-php .page-title-action,
        .themes-php .search-form.search-themes,
        .themes-php .theme.add-new-theme,
        .themes-php .theme-browser .theme.active .theme-actions,
        .themes-php .theme-browser .theme.active .theme-actions .customize,
        .themes-php .theme-browser .theme .theme-actions .load-customize,
        .themes-php .theme-overlay .theme-actions .customize,
        .themes-php .theme-overlay .theme-actions .load-customize {
            display: none !important;
        }
    </style>
<?php
});

/** ================================
 *  EVENT ADMIN SORTING
 *  ================================ */

// Keep a raw sortable version of start_datetime in Y-m-d H:i:s.
add_action('save_post_event', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;

    $pretty = get_post_meta($post_id, 'start_datetime', true); // e.g. "September 9, 2025 6:00 pm"
    if (!$pretty) {
        delete_post_meta($post_id, 'start_datetime_raw');
        return;
    }

    $ts = strtotime($pretty);
    if ($ts) {
        update_post_meta($post_id, 'start_datetime_raw', date('Y-m-d H:i:s', $ts));
    }
});

// Register Admin Columns key as sortable.
add_filter('manage_edit-event_sortable_columns', function ($cols) {
    // `true` sets the initial click direction to DESC.
    $cols['1b5a56da68f5c3'] = ['start_datetime_order', true]; // Admin Columns key.
    return $cols;
}, 1000);

function meza_get_user_first_name_by_id(int $user_id): string
{
    if ($user_id <= 0) return '';

    $first_name = trim((string) get_user_meta($user_id, 'first_name', true));
    if ($first_name !== '') return $first_name;

    $user = get_userdata($user_id);
    if (!($user instanceof WP_User)) return '';

    return trim((string) $user->display_name);
}

function meza_get_first_name_from_user_data(array $userdata): string
{
    return trim((string) ($userdata['first_name'] ?? ''));
}

function meza_user_display_name_should_default_to_first_name(?WP_User $user, string $first_name): bool
{
    $first_name = trim($first_name);
    if ($first_name === '') {
        return false;
    }

    if (!($user instanceof WP_User)) {
        return true;
    }

    $display_name = trim((string) $user->display_name);
    if ($display_name === '' || $display_name === $first_name) {
        return true;
    }

    $nickname = trim((string) get_user_meta($user->ID, 'nickname', true));
    $fallbacks = array_filter(array_unique([
        trim((string) $user->user_login),
        trim((string) $user->user_nicename),
        trim((string) $user->user_email),
        $nickname,
    ]));

    return in_array($display_name, $fallbacks, true);
}

add_filter('wp_pre_insert_user_data', function (array $data, bool $update, ?int $user_id, array $userdata): array {
    $first_name = meza_get_first_name_from_user_data($userdata);
    if ($first_name === '') {
        return $data;
    }

    if (!$update) {
        $data['display_name'] = $first_name;
        return $data;
    }

    $user = ($user_id && $user_id > 0) ? get_userdata($user_id) : null;
    if (meza_user_display_name_should_default_to_first_name($user instanceof WP_User ? $user : null, $first_name)) {
        $data['display_name'] = $first_name;
    }

    return $data;
}, 1000, 4);

add_filter('insert_user_meta', function (array $meta, WP_User $user, bool $update, array $userdata): array {
    $first_name = meza_get_first_name_from_user_data($userdata);
    if ($first_name === '') {
        return $meta;
    }

    $nickname = trim((string) ($meta['nickname'] ?? ''));
    if (
        !$update
        || $nickname === ''
        || $nickname === (string) $user->user_login
        || $nickname === (string) $user->user_nicename
    ) {
        $meta['nickname'] = $first_name;
    }

    return $meta;
}, 1000, 4);

function meza_get_user_email_by_id(int $user_id): string
{
    if ($user_id <= 0) return '';
    $user = get_userdata($user_id);
    if (!($user instanceof WP_User)) return '';
    return sanitize_email((string) $user->user_email);
}

function meza_compact_meridiem(string $time): string
{
    return preg_replace('/\s+([ap])\.?m\.?$/i', '$1m', trim($time)) ?? trim($time);
}

function meza_post_has_internal_pages(WP_Post $post): bool
{
    $content = (string) ($post->post_content ?? '');
    if ($content === '') return false;

    if (str_contains($content, '<!--nextpage-->')) return true;
    if ((bool) preg_match('/<!--\s*wp:nextpage\b[^>]*-->/i', $content)) return true;
    if (function_exists('has_block') && has_block('nextpage', $post)) return true;

    return false;
}

function meza_post_type_has_pages(WP_Post $post): bool
{
    $post_type = (string) ($post->post_type ?? '');
    if ($post_type === 'page') return true;
    return meza_post_has_internal_pages($post);
}

function meza_post_type_has_permalink(string $post_type): bool
{
    $post_type = trim($post_type);
    if ($post_type === '') return false;

    $post_type_object = get_post_type_object($post_type);
    if (!($post_type_object instanceof WP_Post_Type)) return false;

    if (function_exists('is_post_type_viewable') && !is_post_type_viewable($post_type_object)) {
        return false;
    }

    if ($post_type === 'post' || $post_type === 'page') return true;

    return !empty($post_type_object->rewrite) || !empty($post_type_object->query_var);
}

function meza_get_menu_order_admin_column_excluded_post_types(): array
{
    return [
        'attachment',
        'event',
        'events',
        'mzf_submission',
        'shop_coupon',
        'shop_order',
        'shop_order_refund',
    ];
}

function meza_get_menu_order_admin_column_default_visible_post_types(): array
{
    // Project-level defaults. Update this list for future client projects.
    return [
        'customer',
        'faq',
        'location',
        'office',
        'organization',
        'profile',
        'product',
        'service',
        'sign',
        'team-member',
    ];
}

function meza_post_type_supports_menu_order_admin_column(string $post_type): bool
{
    $post_type = trim($post_type);
    if ($post_type === '') return false;
    if (str_starts_with($post_type, 'acf-') || str_starts_with($post_type, 'wp_')) return false;
    if (in_array($post_type, meza_get_menu_order_admin_column_excluded_post_types(), true)) return false;

    $post_type_object = get_post_type_object($post_type);
    if (!($post_type_object instanceof WP_Post_Type)) return false;
    if (empty($post_type_object->show_ui)) return false;

    return true;
}

function meza_post_type_menu_order_admin_column_is_default_visible(string $post_type): bool
{
    return in_array(trim($post_type), meza_get_menu_order_admin_column_default_visible_post_types(), true);
}

function meza_post_type_uses_native_admin_columns(string $post_type): bool
{
    $post_type = trim($post_type);
    if ($post_type === '') return false;

    return meza_post_type_supports_menu_order_admin_column($post_type);
}

function meza_post_has_permalink(int $post_id): bool
{
    if ($post_id <= 0) return false;

    $post_type = (string) get_post_type($post_id);
    if ($post_type === '' || !meza_post_type_has_permalink($post_type)) {
        return false;
    }

    $url = get_permalink($post_id);
    return is_string($url) && $url !== '' && !is_wp_error($url);
}

function meza_should_show_posts_categories_column(): bool
{
    $default_category_id = (int) get_option('default_category');
    if ($default_category_id <= 0) return true;

    $category_ids = get_terms([
        'taxonomy' => 'category',
        'hide_empty' => false,
        'fields' => 'ids',
    ]);

    if (is_wp_error($category_ids) || !is_array($category_ids)) return true;

    $category_ids = array_values(array_unique(array_map('intval', $category_ids)));
    if (count($category_ids) !== 1) return true;

    return ((int) $category_ids[0] !== $default_category_id);
}

add_filter('disable_categories_dropdown', function ($disable, $post_type) {
    if ((string) $post_type !== 'post') {
        return $disable;
    }

    return meza_should_show_posts_categories_column() ? $disable : true;
}, 200, 2);

add_filter('wp_mail_smtp_tasks_tasks_action_scheduler_tools_plugin_exceptions', function ($plugins) {
    $plugins = is_array($plugins) ? $plugins : [];
    $plugins[] = 'wp-mail-smtp/wp_mail_smtp.php';
    return array_values(array_unique(array_map('strval', $plugins)));
}, 200);

add_filter('wp_mail_smtp_tasks_admin_hide_as_menu', function ($hide_as_menu) {
    if (current_user_can('manage_options')) {
        return false;
    }

    return $hide_as_menu;
}, 200);

function meza_post_type_shows_summary_admin_column(string $post_type): bool
{
    $post_type = trim($post_type);
    if ($post_type === '') return false;
    if ($post_type === 'attachment') return false;
    if (meza_is_acf_admin_post_type($post_type)) return false;

    return post_type_supports($post_type, 'excerpt');
}

function meza_get_post_type_thumbnail_admin_column_label(string $post_type): string
{
    $post_type = trim($post_type);

    if ($post_type === 'profile') {
        return __('Photo');
    }

    if ($post_type === 'organization') {
        return __('Logo');
    }

    if ($post_type !== '' && ($post_type === 'post' || $post_type === 'page' || meza_post_type_has_permalink($post_type))) {
        return __('Share Image');
    }

    return __('Image');
}

function meza_post_type_uses_share_image_admin_column(string $post_type): bool
{
    $post_type = trim($post_type);
    if ($post_type === '') return false;
    if (in_array($post_type, ['profile', 'organization'], true)) return false;

    return ($post_type === 'post' || $post_type === 'page' || meza_post_type_has_permalink($post_type));
}

function meza_get_post_type_summary_admin_column_label(string $post_type): string
{
    return in_array(trim($post_type), ['cta', 'form'], true) ? __('Subhead') : __('Summary');
}

function meza_get_post_edit_panel_admin_label_map(string $post_type): array
{
    $post_type = trim($post_type);
    if ($post_type === '') return [];

    $labels = [];

    if (post_type_supports($post_type, 'thumbnail')) {
        $labels['postimagediv'] = meza_get_post_type_thumbnail_admin_column_label($post_type);
    }

    if (post_type_supports($post_type, 'excerpt')) {
        $labels['postexcerpt'] = meza_get_post_type_summary_admin_column_label($post_type);
    }

    if ($post_type === 'form') {
        $labels['slugdiv'] = __('Slug');
    }

    return $labels;
}

function meza_get_profile_title_column_value(int $post_id): string
{
    if ($post_id <= 0) return '';

    $field_keys = [
        'title',
        'job_title',
        'position',
        'role',
        'profile_title',
    ];

    if (function_exists('get_field')) {
        foreach ($field_keys as $field_key) {
            $value = get_field($field_key, $post_id);
            if (is_string($value) && trim($value) !== '') {
                return trim(wp_strip_all_tags($value));
            }
        }
    }

    foreach ($field_keys as $field_key) {
        $value = get_post_meta($post_id, $field_key, true);
        if (is_string($value) && trim($value) !== '') {
            return trim(wp_strip_all_tags($value));
        }
    }

    return '';
}

function meza_get_profile_primary_link_url(int $post_id): string
{
    if ($post_id <= 0) return '';

    if (function_exists('get_field')) {
        $links = get_field('links', $post_id);
        if (is_array($links)) {
            foreach ($links as $link_row) {
                if (!is_array($link_row)) continue;

                $url = trim((string) ($link_row['url'] ?? ''));
                if ($url !== '') {
                    return $url;
                }
            }
        }
    }

    $url = trim((string) get_post_meta($post_id, 'links_0_url', true));
    if ($url !== '') {
        return $url;
    }

    return '';
}

function meza_get_post_link_field_url(int $post_id, array $field_keys): string
{
    if ($post_id <= 0) return '';

    if (function_exists('get_field')) {
        foreach ($field_keys as $field_key) {
            $value = get_field((string) $field_key, $post_id);
            if (is_array($value) && is_string($value['url'] ?? null) && trim((string) $value['url']) !== '') {
                return trim((string) $value['url']);
            }
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }
    }

    foreach ($field_keys as $field_key) {
        $value = get_post_meta($post_id, (string) $field_key, true);
        if (is_array($value) && is_string($value['url'] ?? null) && trim((string) $value['url']) !== '') {
            return trim((string) $value['url']);
        }
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    return '';
}

function meza_get_post_yoast_meta_value(int $post_id, array $meta_keys): string
{
    if ($post_id <= 0) return '';

    foreach ($meta_keys as $meta_key) {
        $value = get_post_meta($post_id, (string) $meta_key, true);
        if (!is_string($value)) continue;

        $value = trim(wp_strip_all_tags($value));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function meza_get_post_yoast_share_title_value(int $post_id): string
{
    return meza_get_post_yoast_meta_value($post_id, [
        '_yoast_wpseo_opengraph-title',
        '_yoast_wpseo_twitter-title',
        '_yoast_wpseo_title',
    ]);
}

function meza_get_post_yoast_share_description_value(int $post_id): string
{
    return meza_get_post_yoast_meta_value($post_id, [
        '_yoast_wpseo_opengraph-description',
        '_yoast_wpseo_twitter-description',
        '_yoast_wpseo_metadesc',
    ]);
}

function meza_get_admin_link_column_display_text(string $url): string
{
    $url = trim($url);
    if ($url === '') return '';

    if (str_starts_with($url, '/') || str_starts_with($url, '?') || str_starts_with($url, '#')) {
        return $url;
    }

    $parsed_url = wp_parse_url($url);
    if (!is_array($parsed_url) || empty($parsed_url['host'])) {
        return $url;
    }

    $home_url = home_url('/');
    $parsed_home_url = wp_parse_url($home_url);
    if (!is_array($parsed_home_url) || empty($parsed_home_url['host'])) {
        return $url;
    }

    $normalize_host = static function (string $host): string {
        $host = strtolower(trim($host));
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    };

    if ($normalize_host((string) $parsed_url['host']) !== $normalize_host((string) $parsed_home_url['host'])) {
        return $url;
    }

    $display = (string) ($parsed_url['path'] ?? '/');
    if ($display === '') {
        $display = '/';
    }
    if ($display !== '/' && str_ends_with($display, '/')) {
        $display = rtrim($display, '/');
        if ($display === '') {
            $display = '/';
        }
    }

    if (isset($parsed_url['query']) && $parsed_url['query'] !== '') {
        $display .= '?' . $parsed_url['query'];
    }

    if (isset($parsed_url['fragment']) && $parsed_url['fragment'] !== '') {
        $display .= '#' . $parsed_url['fragment'];
    }

    return $display;
}

function meza_customize_users_admin_columns(array $columns): array
{
    if (!is_array($columns)) return $columns;

    unset($columns['posts']);
    $columns['website'] = __('Website');
    if (current_user_can('edit_users')) {
        $columns['last_login'] = __('Last Login');
    }

    $ordered = [];

    if (array_key_exists('cb', $columns)) {
        $ordered['cb'] = $columns['cb'];
        unset($columns['cb']);
    }

    foreach (['username', 'name', 'email', 'role', 'website', 'last_login'] as $key) {
        if (array_key_exists($key, $columns)) {
            $ordered[$key] = $columns[$key];
            unset($columns[$key]);
        }
    }

    foreach ($columns as $key => $label) {
        $normalized_key = strtolower(trim((string) $key));
        $normalized_label = strtolower(trim(wp_strip_all_tags((string) $label)));

        if (
            preg_match('/\b(two[\s_-]*factor|2fa)\b/i', $normalized_key) === 1
            || preg_match('/\b(two[\s-]*factor|2fa)\b/i', $normalized_label) === 1
        ) {
            continue;
        }

        $ordered[$key] = $label;
    }

    return $ordered;
}

function meza_users_last_login_admin_column_is_visible(): bool
{
    if (!current_user_can('edit_users')) {
        return false;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || $screen->id !== 'users') {
        return true;
    }

    $hidden = array_map('strval', get_hidden_columns($screen));
    return !in_array('last_login', $hidden, true);
}

function meza_render_users_website_admin_column(string $output, string $column_name, int $user_id): string
{
    if ($column_name === 'last_login') {
        if (!current_user_can('edit_users')) {
            return $output;
        }

        $timestamp = (int) get_user_meta($user_id, 'meza_last_login', true);
        if ($timestamp <= 0) {
            return '&mdash;';
        }

        $format = trim(get_option('date_format') . ' ' . get_option('time_format'));
        return esc_html(wp_date($format !== '' ? $format : 'F j, Y g:i a', $timestamp));
    }

    if ($column_name !== 'website') {
        return $output;
    }

    $url = trim((string) get_the_author_meta('user_url', $user_id));
    if ($url === '') {
        return '&mdash;';
    }

    return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html(meza_get_admin_link_column_display_text($url)) . '</a>';
}

add_filter('manage_users_columns', 'meza_customize_users_admin_columns', 1000);
add_filter('manage_users_custom_column', 'meza_render_users_website_admin_column', 100, 3);
add_filter('manage_users_sortable_columns', function (array $columns): array {
    if (!current_user_can('edit_users')) {
        return $columns;
    }

    $columns['last_login'] = [
        'last_login',
        true,
        __('Last Login'),
        __('Table ordered by Last Login.'),
        meza_users_last_login_admin_column_is_visible() ? 'desc' : false,
    ];

    return $columns;
}, 1000);

add_action('current_screen', function ($screen): void {
    if (!($screen instanceof WP_Screen) || $screen->id !== 'users') {
        return;
    }

    if (!current_user_can('edit_users')) {
        return;
    }

    $orderby = isset($_REQUEST['orderby']) ? trim((string) wp_unslash($_REQUEST['orderby'])) : '';
    if ($orderby !== '') {
        return;
    }

    if (!meza_users_last_login_admin_column_is_visible()) {
        return;
    }

    $_GET['orderby'] = 'last_login';
    $_GET['order'] = 'desc';
    $_REQUEST['orderby'] = 'last_login';
    $_REQUEST['order'] = 'desc';
}, 5);

add_action('pre_get_users', function (WP_User_Query $query): void {
    if (!is_admin() || !current_user_can('edit_users')) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || $screen->id !== 'users') {
        return;
    }

    $orderby = trim((string) $query->get('orderby'));
    $order = strtoupper((string) $query->get('order'));
    $order = ($order === 'ASC') ? 'ASC' : 'DESC';

    if ($orderby === 'last_login') {
        $query->set('meza_last_login_sort_order', $order);
        return;
    }

    if ($orderby !== '' || !meza_users_last_login_admin_column_is_visible()) {
        return;
    }

    $query->set('meza_last_login_sort_order', 'DESC');
}, 1000);

add_action('pre_user_query', function (WP_User_Query $query): void {
    $sort_order = strtoupper((string) $query->get('meza_last_login_sort_order'));
    if (!in_array($sort_order, ['ASC', 'DESC'], true)) {
        return;
    }

    global $wpdb;

    $alias = 'meza_last_login_meta';
    if (!str_contains((string) $query->query_from, $alias)) {
        $query->query_from .= $wpdb->prepare(
            " LEFT JOIN {$wpdb->usermeta} AS {$alias} ON ({$wpdb->users}.ID = {$alias}.user_id AND {$alias}.meta_key = %s)",
            'meza_last_login'
        );
    }

    $query->query_orderby = "ORDER BY CASE WHEN {$alias}.meta_value IS NULL OR {$alias}.meta_value = '' THEN 1 ELSE 0 END ASC, {$alias}.meta_value+0 {$sort_order}, {$wpdb->users}.user_login ASC";
}, 1000);

add_action('wp_login', function (string $user_login, WP_User $user): void {
    update_user_meta($user->ID, 'meza_last_login', time());
}, 10, 2);

function meza_get_faq_repeater_item_count(int $post_id): int
{
    if ($post_id <= 0) return 0;

    $field_keys = ['faqs', 'faq'];

    if (function_exists('get_field')) {
        foreach ($field_keys as $field_key) {
            $faq_items = get_field($field_key, $post_id);
            if (is_array($faq_items)) {
                return count($faq_items);
            }
        }
    }

    foreach ($field_keys as $field_key) {
        $stored_count = get_post_meta($post_id, $field_key, true);
        if (is_numeric($stored_count)) {
            return max(0, (int) $stored_count);
        }
    }

    return 0;
}

function meza_get_page_form_ids(int $post_id): array
{
    if ($post_id <= 0) return [];

    $form_ids = [];
    $append_form_ids = static function ($value) use (&$form_ids): void {
        if (is_array($value) && isset($value['form'])) {
            $value = $value['form'];
        }

        if (is_array($value)) {
            foreach ($value as $entry) {
                if (is_numeric($entry)) {
                    $form_ids[] = (int) $entry;
                    continue;
                }
                if ($entry instanceof WP_Post) {
                    $form_ids[] = (int) $entry->ID;
                    continue;
                }
                if (is_array($entry)) {
                    $entry_id = (int) ($entry['ID'] ?? $entry['id'] ?? 0);
                    if ($entry_id > 0) {
                        $form_ids[] = $entry_id;
                    }
                }
            }
            return;
        }

        if (is_numeric($value)) {
            $form_ids[] = (int) $value;
            return;
        }

        if ($value instanceof WP_Post) {
            $form_ids[] = (int) $value->ID;
        }
    };

    if (function_exists('get_field')) {
        $acf_form_candidates = [
            get_field('section_form_form', $post_id),
            get_field('form', $post_id),
            get_field('section_form', $post_id),
        ];

        foreach ($acf_form_candidates as $acf_form) {
            $append_form_ids($acf_form);
        }
    }

    if (empty($form_ids)) {
        $raw_form_candidates = [
            get_post_meta($post_id, 'section_form_form', true),
            get_post_meta($post_id, 'form', true),
            get_post_meta($post_id, 'section_form', true),
        ];

        foreach ($raw_form_candidates as $raw_form) {
            $append_form_ids($raw_form);
        }
    }

    $form_ids = array_values(array_unique(array_filter(array_map('intval', $form_ids), function ($id) {
        return $id > 0 && get_post_type($id) === 'form';
    })));

    return $form_ids;
}

function meza_insert_admin_column_after(array $columns, string $after_key, string $new_key, string $label): array
{
    if (array_key_exists($new_key, $columns)) {
        $columns[$new_key] = $label;
        return $columns;
    }

    $updated = [];
    $inserted = false;

    foreach ($columns as $key => $value) {
        $updated[$key] = $value;
        if ((string) $key === $after_key) {
            $updated[$new_key] = $label;
            $inserted = true;
        }
    }

    if (!$inserted) {
        $updated[$new_key] = $label;
    }

    return $updated;
}

function meza_ensure_summary_admin_column(array $columns, string $post_type): array
{
    if (!is_array($columns)) {
        $columns = [];
    }

    $post_type = trim($post_type);
    if ($post_type === '' || !meza_post_type_shows_summary_admin_column($post_type)) {
        unset($columns['mz_summary']);
        return $columns;
    }

    $label = meza_get_post_type_summary_admin_column_label($post_type);

    if (array_key_exists('mz_summary', $columns)) {
        $columns['mz_summary'] = $label;
        return $columns;
    }

    return meza_insert_admin_column_after($columns, 'title', 'mz_summary', $label);
}

function meza_ensure_menu_order_admin_column(array $columns, string $post_type): array
{
    if (!is_array($columns)) {
        $columns = [];
    }

    $post_type = trim($post_type);
    if ($post_type === '' || !meza_post_type_supports_menu_order_admin_column($post_type)) {
        unset($columns['mz_menu_order']);
        return $columns;
    }

    if (array_key_exists('mz_menu_order', $columns)) {
        $columns['mz_menu_order'] = __('#');
        return $columns;
    }

    if (array_key_exists('mz_id', $columns)) {
        return meza_insert_admin_column_after($columns, 'mz_id', 'mz_menu_order', __('#'));
    }

    if (array_key_exists('cb', $columns)) {
        return meza_insert_admin_column_after($columns, 'cb', 'mz_menu_order', __('#'));
    }

    return meza_insert_admin_column_after($columns, 'title', 'mz_menu_order', __('#'));
}

function meza_place_summary_before_taxonomy_columns(array $columns, string $post_type): array
{
    if (!is_array($columns)) {
        return [];
    }

    $post_type = trim($post_type);
    if ($post_type === '' || !array_key_exists('mz_summary', $columns)) {
        return $columns;
    }

    $taxonomy_keys = [];
    $taxonomies = get_object_taxonomies($post_type, 'names');
    if (is_array($taxonomies)) {
        foreach ($taxonomies as $taxonomy) {
            $taxonomy = trim((string) $taxonomy);
            if ($taxonomy === '') continue;
            $taxonomy_keys[$taxonomy] = true;
            $taxonomy_keys["taxonomy-{$taxonomy}"] = true;
        }
    }
    $taxonomy_keys['categories'] = true;

    $summary_label = $columns['mz_summary'];
    unset($columns['mz_summary']);

    $updated = [];
    $inserted = false;

    foreach ($columns as $key => $label) {
        if (!$inserted && isset($taxonomy_keys[(string) $key])) {
            $updated['mz_summary'] = $summary_label;
            $inserted = true;
        }

        $updated[$key] = $label;
    }

    if (!$inserted) {
        $updated['mz_summary'] = $summary_label;
    }

    return $updated;
}

add_filter('ac/headings', function ($headings, $list_screen) {
    if (!is_array($headings)) {
        $headings = [];
    }

    if (!is_object($list_screen) || !method_exists($list_screen, 'get_post_type')) {
        return $headings;
    }

    $post_type = trim((string) $list_screen->get_post_type());
    if ($post_type === '') {
        return $headings;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (
        $screen instanceof WP_Screen
        && $screen->base === 'edit'
        && (string) ($screen->post_type ?? '') === $post_type
    ) {
        $headings = meza_normalize_admin_columns_managed_headings($headings, $post_type);
    }

    $headings = meza_ensure_menu_order_admin_column($headings, $post_type);
    $headings = meza_ensure_summary_admin_column($headings, $post_type);
    return meza_place_summary_before_taxonomy_columns($headings, $post_type);
}, 1000, 2);

function meza_page_form_admin_column_is_default_visible(string $post_type): bool
{
    return (trim($post_type) === 'page');
}

function meza_summary_admin_column_is_default_visible(string $post_type): bool
{
    $post_type = trim($post_type);
    if ($post_type === '') return false;
    if (!meza_post_type_shows_summary_admin_column($post_type)) return false;

    return ($post_type !== 'page');
}

function meza_normalize_admin_columns_managed_headings(array $columns, string $post_type): array
{
    if (!is_array($columns)) return $columns;

    $post_type = trim($post_type);
    if ($post_type === '') return $columns;

    $supports_thumbnail = post_type_supports($post_type, 'thumbnail');
    $show_page_columns = !meza_is_acf_admin_post_type($post_type) && meza_post_type_has_permalink($post_type);
    $show_cta_link_column = ($post_type === 'cta');
    $show_cta_secondary_link_column = ($post_type === 'cta');
    $show_form_recipients_column = ($post_type === 'form');
    $show_organization_url_column = ($post_type === 'organization');
    $show_profile_title_column = ($post_type === 'profile');
    $show_profile_link_column = ($post_type === 'profile');
    $show_faq_count_column = ($post_type === 'faq');
    $show_form_slug_column = ($post_type === 'form');
    $show_review_columns = in_array($post_type, ['review', 'reviews'], true);
    $show_summary_column = meza_post_type_shows_summary_admin_column($post_type);
    $show_yoast_share_columns = defined('WPSEO_VERSION') && (
        array_key_exists('wpseo-title', $columns)
        || array_key_exists('wpseo-metadesc', $columns)
        || array_key_exists('mz_share_title', $columns)
        || array_key_exists('mz_share_description', $columns)
    );
    $thumbnail_column_label = meza_get_post_type_thumbnail_admin_column_label($post_type);
    $title_column_label = (
        in_array($post_type, ['profile', 'organization'], true)
        ? __('Name')
        : (in_array($post_type, ['cta', 'form'], true) ? __('Headline (H2)') : null)
    );

    foreach ($columns as $key => $label) {
        switch ((string) $key) {
            case 'title':
                $columns[$key] = $title_column_label ?? $label;
                break;
            case 'slug':
                unset($columns[$key]);
                break;
            case 'mz_menu_order':
                $columns[$key] = __('#');
                break;
            case 'mz_thumbnail':
                if (!$supports_thumbnail) {
                    unset($columns[$key]);
                } else {
                    $columns[$key] = $thumbnail_column_label;
                }
                break;
            case 'mz_profile_title':
                if (!$show_profile_title_column) unset($columns[$key]);
                else $columns[$key] = __('Title');
                break;
            case 'mz_profile_link':
                if (!$show_profile_link_column) unset($columns[$key]);
                else $columns[$key] = __('Link');
                break;
            case 'mz_organization_url':
                if (!$show_organization_url_column) unset($columns[$key]);
                else $columns[$key] = __('Link');
                break;
            case 'mz_summary':
                if (!$show_summary_column) unset($columns[$key]);
                else $columns[$key] = meza_get_post_type_summary_admin_column_label($post_type);
                break;
            case 'mz_faq_count':
                if (!$show_faq_count_column) unset($columns[$key]);
                else $columns[$key] = __('Count');
                break;
            case 'mz_cta_link':
                if (!$show_cta_link_column) unset($columns[$key]);
                else $columns[$key] = __('Link (Primary)');
                break;
            case 'mz_cta_secondary_link':
                if (!$show_cta_secondary_link_column) unset($columns[$key]);
                else $columns[$key] = __('Link (Secondary)');
                break;
            case 'mz_slug':
                if (!$show_form_slug_column) unset($columns[$key]);
                else $columns[$key] = __('Slug');
                break;
            case 'mz_form_recipients':
                if (!$show_form_recipients_column) unset($columns[$key]);
                else $columns[$key] = __('Recipients');
                break;
            case 'mz_review_quote':
                if (!$show_review_columns) unset($columns[$key]);
                else $columns[$key] = __('Quote');
                break;
            case 'mz_review_citer':
                if (!$show_review_columns) unset($columns[$key]);
                else $columns[$key] = __('Citer');
                break;
            case 'template':
            case 'mz_page_template':
            case 'column-page_template':
                if ($post_type === 'page') unset($columns[$key]);
                break;
            case 'mz_page_headline':
                if (!$show_page_columns) unset($columns[$key]);
                else $columns[$key] = __('Page Headline (H1)');
                break;
            case 'mz_page_cta':
                if (!$show_page_columns) unset($columns[$key]);
                else $columns[$key] = __('Page CTA');
                break;
            case 'mz_page_form':
                if (!$show_page_columns) unset($columns[$key]);
                else $columns[$key] = __('Page Form');
                break;
            case 'wpseo-title':
                $columns[$key] = __('Meta Title');
                break;
            case 'wpseo-metadesc':
                $columns[$key] = __('Meta Description');
                break;
            case 'mz_share_title':
                if (!$show_yoast_share_columns) unset($columns[$key]);
                else $columns[$key] = __('Share Title');
                break;
            case 'mz_share_description':
                if (!$show_yoast_share_columns) unset($columns[$key]);
                else $columns[$key] = __('Share Description');
                break;
            case 'mz_modified':
            case 'modified':
                $columns[$key] = __('Modified');
                break;
            case 'mz_published':
            case 'date':
                $columns[$key] = __('Published');
                break;
        }
    }

    return $columns;
}

function meza_normalize_datetime_columns(array $columns): array
{
    if (!is_array($columns)) return $columns;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $post_type = ($screen instanceof WP_Screen) ? (string) ($screen->post_type ?? '') : '';
    if ($post_type === 'product') return $columns;
    $supports_thumbnail = ($post_type !== '' && post_type_supports($post_type, 'thumbnail'));
    $show_menu_order_column = meza_post_type_supports_menu_order_admin_column($post_type);
    $show_page_columns = !meza_is_acf_admin_post_type($post_type) && meza_post_type_has_permalink($post_type);
    $show_cta_link_column = ($post_type === 'cta');
    $show_cta_secondary_link_column = ($post_type === 'cta');
    $show_form_recipients_column = ($post_type === 'form');
    $show_organization_url_column = ($post_type === 'organization');
    $show_profile_title_column = ($post_type === 'profile');
    $show_profile_link_column = ($post_type === 'profile');
    $show_faq_count_column = ($post_type === 'faq');
    $show_form_slug_column = ($post_type === 'form');
    $show_review_columns = in_array($post_type, ['review', 'reviews'], true);
    $show_summary_column = meza_post_type_shows_summary_admin_column($post_type);
    $show_share_image_with_seo_columns = ($supports_thumbnail && meza_post_type_uses_share_image_admin_column($post_type));
    $show_yoast_share_columns = !meza_is_acf_admin_post_type($post_type)
        && defined('WPSEO_VERSION')
        && (
            array_key_exists('wpseo-title', $columns)
            || array_key_exists('wpseo-metadesc', $columns)
        );
    $thumbnail_column_label = meza_get_post_type_thumbnail_admin_column_label($post_type);
    $title_column_label = (
        in_array($post_type, ['profile', 'organization'], true)
        ? __('Name')
        : (in_array($post_type, ['cta', 'form'], true) ? __('Headline (H2)') : null)
    );

    $has_modified = false;
    foreach ($columns as $key => $label) {
        $normalized_key = strtolower(trim((string) $key));
        $normalized_label = strtolower(trim(wp_strip_all_tags((string) $label)));
        if ($normalized_key === 'modified' || $normalized_key === 'mz_modified' || $normalized_label === 'modified') {
            $has_modified = true;
        }
    }

    $updated = [];
    foreach ($columns as $key => $label) {
        $normalized_key = strtolower(trim((string) $key));

        // Normalize custom identity columns so we can insert them in a stable place.
        if ($key === 'slug') continue;
        if ($key === 'mz_id' || $key === 'mz_menu_order' || $key === 'mz_thumbnail' || $key === 'mz_page_link' || $key === 'mz_summary') continue;
        if ($post_type === 'page' && in_array((string) $key, ['template', 'mz_page_template', 'column-page_template'], true)) continue;
        if (!$show_page_columns && in_array((string) $key, ['mz_page_link', 'mz_page_headline', 'mz_page_cta', 'mz_page_form'], true)) continue;

        // Posts list: remove non-editorial columns.
        if ($post_type === 'post') {
            if (in_array($normalized_key, ['author', 'comments', 'tags', 'taxonomy-post_tag'], true)) continue;
            if ($normalized_key === 'categories' && !meza_should_show_posts_categories_column()) continue;
        }
        if ($post_type === 'page') {
            if (in_array($normalized_key, ['author', 'comments'], true)) continue;
        }

        if ($key === 'title') {
            $updated['mz_id'] = __('ID');
            if ($show_menu_order_column) $updated['mz_menu_order'] = __('#');
            if ($supports_thumbnail) $updated['mz_thumbnail'] = $thumbnail_column_label;
            $updated[$key] = $title_column_label ?? $label;
            if ($show_profile_title_column) $updated['mz_profile_title'] = __('Title');
            if ($show_profile_link_column) $updated['mz_profile_link'] = __('Link');
            if ($show_organization_url_column) $updated['mz_organization_url'] = __('Link');
            if ($show_summary_column) $updated['mz_summary'] = meza_get_post_type_summary_admin_column_label($post_type);
            if ($show_faq_count_column) $updated['mz_faq_count'] = __('Count');
            if ($show_cta_link_column) $updated['mz_cta_link'] = __('Link (Primary)');
            if ($show_cta_secondary_link_column) $updated['mz_cta_secondary_link'] = __('Link (Secondary)');
            if ($show_form_slug_column) $updated['mz_slug'] = __('Slug');
            if ($show_form_recipients_column) $updated['mz_form_recipients'] = __('Recipients');
            if ($show_review_columns) {
                $updated['mz_review_quote'] = __('Quote');
                $updated['mz_review_citer'] = __('Citer');
            }
            if ($show_page_columns) {
                $updated['mz_page_headline'] = __('Page Headline (H1)');
                $updated['mz_page_cta'] = __('Page CTA');
                $updated['mz_page_form'] = __('Page Form');
            }
            continue;
        }

        if ($key === 'date') {
            if (!$has_modified) $updated['mz_modified'] = __('Modified');
            $updated['mz_published'] = __('Published');
            continue;
        }
        $updated[$key] = $label;
    }

    if (!isset($updated['mz_published'])) $updated['mz_published'] = __('Published');
    if (!$has_modified && !isset($updated['mz_modified'])) $updated['mz_modified'] = __('Modified');
    if ($show_profile_link_column && !isset($updated['mz_profile_link'])) {
        $updated['mz_profile_link'] = __('Link');
    }
    if ($show_organization_url_column && !isset($updated['mz_organization_url'])) {
        $updated['mz_organization_url'] = __('Link');
    }
    if ($show_summary_column && !isset($updated['mz_summary'])) {
        $updated['mz_summary'] = meza_get_post_type_summary_admin_column_label($post_type);
    }
    if ($show_page_columns) {
        if (!isset($updated['mz_page_headline'])) $updated['mz_page_headline'] = __('Page Headline (H1)');
        if (!isset($updated['mz_page_cta'])) $updated['mz_page_cta'] = __('Page CTA');
        if (!isset($updated['mz_page_form'])) $updated['mz_page_form'] = __('Page Form');
    }
    if ($show_yoast_share_columns) {
        if (!isset($updated['mz_share_title'])) $updated['mz_share_title'] = __('Share Title');
        if (!isset($updated['mz_share_description'])) $updated['mz_share_description'] = __('Share Description');
    }

    // Enforce editorial column order.
    $ordered = [];
    $used = [];

    $append = static function (string $key) use (&$ordered, &$updated, &$used): void {
        if (isset($used[$key])) return;
        if (!array_key_exists($key, $updated)) return;
        $ordered[$key] = $updated[$key];
        $used[$key] = true;
    };
    $append_taxonomy_columns = static function () use (&$updated, $append): void {
        foreach (array_keys($updated) as $key) {
            if (str_starts_with((string) $key, 'taxonomy-') || $key === 'categories') {
                $append((string) $key);
            }
        }
    };

    // Keep bulk checkbox first when present.
    $append('cb');

    if ($post_type === 'page') {
        // Pages: id, order, title, summary, page fields, SEO/share, then remaining editorial fields.
        $append('mz_id');
        if ($show_menu_order_column) $append('mz_menu_order');
        if (!$show_share_image_with_seo_columns) $append('mz_thumbnail');
        $append('title');
        if ($show_summary_column) $append('mz_summary');
        if ($show_page_columns) {
            $append('mz_page_headline');
            $append('mz_page_cta');
            $append('mz_page_form');
        }
        $append('wpseo-title');
        $append('wpseo-metadesc');
        if ($show_share_image_with_seo_columns) $append('mz_thumbnail');
        if ($show_yoast_share_columns) $append('mz_share_title');
        if ($show_yoast_share_columns) $append('mz_share_description');
        if ($show_organization_url_column) $append('mz_organization_url');
        $append_taxonomy_columns();
        if ($show_cta_link_column) $append('mz_cta_link');
        if ($show_cta_secondary_link_column) $append('mz_cta_secondary_link');
        if ($show_form_slug_column) $append('mz_slug');
        if ($show_form_recipients_column) $append('mz_form_recipients');
        if ($show_review_columns) {
            $append('mz_review_quote');
            $append('mz_review_citer');
        }
    } else {
        $append('mz_id');
        if ($show_menu_order_column) $append('mz_menu_order');
        if (!$show_share_image_with_seo_columns) $append('mz_thumbnail');
        $append('title');
        if ($show_summary_column) $append('mz_summary');
        if ($show_page_columns) {
            $append('mz_page_headline');
            $append('mz_page_cta');
            $append('mz_page_form');
        }
        $append('wpseo-title');
        $append('wpseo-metadesc');
        if ($show_share_image_with_seo_columns) $append('mz_thumbnail');
        if ($show_yoast_share_columns) $append('mz_share_title');
        if ($show_yoast_share_columns) $append('mz_share_description');
        if ($show_profile_title_column) $append('mz_profile_title');
        if ($show_profile_link_column) $append('mz_profile_link');
        if ($show_organization_url_column) $append('mz_organization_url');
        $append_taxonomy_columns();
        if ($show_faq_count_column) $append('mz_faq_count');
        if ($show_cta_link_column) $append('mz_cta_link');
        if ($show_cta_secondary_link_column) $append('mz_cta_secondary_link');
        if ($show_form_slug_column) $append('mz_slug');
        if ($show_form_recipients_column) $append('mz_form_recipients');
        if ($show_review_columns) {
            $append('mz_review_quote');
            $append('mz_review_citer');
        }
    }

    foreach (array_keys($updated) as $key) {
        if (str_starts_with((string) $key, 'taxonomy-') || $key === 'categories') {
            $append((string) $key);
        }
    }

    // Append any remaining columns in their original order.
    foreach (array_keys($updated) as $key) {
        $append((string) $key);
    }

    $append('mz_modified');
    $append('modified');
    $append('mz_published');
    $append('date');

    return $ordered;
}

function meza_resolve_admin_column_key(array $columns, array $aliases): string
{
    foreach ($aliases as $alias) {
        $alias = (string) $alias;
        if ($alias !== '' && array_key_exists($alias, $columns)) return $alias;
    }

    return '';
}

function meza_customize_product_admin_columns(array $columns): array
{
    if (!is_array($columns)) return $columns;

    unset($columns['thumb']);

    $columns['mz_menu_order'] = __('#');
    $columns['mz_thumbnail'] = meza_get_post_type_thumbnail_admin_column_label('product');
    $columns['mz_product_type'] = __('Product Type');
    $columns['mz_page_headline'] = __('Page Headline (H1)');
    $columns['mz_page_cta'] = __('Page CTA');
    $columns['mz_page_form'] = __('Page Form');
    $columns['mz_modified'] = __('Modified');
    $columns['mz_published'] = __('Published');

    if (defined('WPSEO_VERSION')) {
        if (!isset($columns['wpseo-title'])) $columns['wpseo-title'] = __('Meta Title');
        if (!isset($columns['wpseo-metadesc'])) $columns['wpseo-metadesc'] = __('Meta Description');
        if (!isset($columns['mz_share_title'])) $columns['mz_share_title'] = __('Share Title');
        if (!isset($columns['mz_share_description'])) $columns['mz_share_description'] = __('Share Description');
    }

    if (taxonomy_exists('product_brand')) {
        $brand_taxonomy = get_taxonomy('product_brand');
        $brand_label = ($brand_taxonomy && isset($brand_taxonomy->labels->name)) ? (string) $brand_taxonomy->labels->name : __('Brands');
        if (!isset($columns['taxonomy-product_brand']) && !isset($columns['product_brand'])) {
            $columns['taxonomy-product_brand'] = $brand_label;
        }
    }

    $ordered = [];
    $append = static function (array $aliases, ?string $fallback_key = null, ?string $fallback_label = null) use (&$ordered, $columns): void {
        $resolved_key = meza_resolve_admin_column_key($columns, $aliases);
        if ($resolved_key !== '') {
            $ordered[$resolved_key] = $columns[$resolved_key];
            return;
        }

        if ($fallback_key !== null && $fallback_label !== null) {
            $ordered[$fallback_key] = $fallback_label;
        }
    };

    $append(['cb']);
    $append(['mz_menu_order'], 'mz_menu_order', __('#'));
    $append(['featured'], 'featured', __('Featured'));
    $append(['name', 'title'], 'name', __('Name'));
    $append(['price'], 'price', __('Price'));
    $append(['is_in_stock'], 'is_in_stock', __('Stock'));
    $append(['taxonomy-product_brand', 'product_brand']);
    $append(['mz_product_type'], 'mz_product_type', __('Product Type'));
    $append(['sku'], 'sku', __('SKU'));
    $append(['product_cat', 'taxonomy-product_cat'], 'product_cat', __('Categories'));
    $append(['product_tag', 'taxonomy-product_tag'], 'product_tag', __('Tags'));
    $append(['wpseo-title']);
    $append(['wpseo-metadesc']);
    $append(['mz_thumbnail'], 'mz_thumbnail', meza_get_post_type_thumbnail_admin_column_label('product'));
    $append(['mz_share_title']);
    $append(['mz_share_description']);
    $append(['mz_page_headline'], 'mz_page_headline', __('Page Headline (H1)'));
    $append(['mz_page_cta'], 'mz_page_cta', __('Page CTA'));
    $append(['mz_page_form'], 'mz_page_form', __('Page Form'));
    $append(['mz_modified'], 'mz_modified', __('Modified'));
    $append(['mz_published'], 'mz_published', __('Published'));

    return $ordered;
}

function meza_get_product_admin_columns_for_visibility(): array
{
    $columns = [
        'cb' => '<input type="checkbox" />',
        'mz_menu_order' => __('#'),
        'mz_thumbnail' => meza_get_post_type_thumbnail_admin_column_label('product'),
        'name' => __('Name'),
        'sku' => __('SKU'),
        'is_in_stock' => __('Stock'),
        'price' => __('Price'),
        'product_cat' => __('Categories'),
        'product_tag' => __('Tags'),
        'featured' => __('Featured'),
        'date' => __('Date'),
    ];

    if (taxonomy_exists('product_brand')) {
        $brand_taxonomy = get_taxonomy('product_brand');
        $columns['taxonomy-product_brand'] = ($brand_taxonomy && isset($brand_taxonomy->labels->name)) ? (string) $brand_taxonomy->labels->name : __('Brands');
    }

    $resolved = apply_filters('manage_product_posts_columns', $columns);
    return is_array($resolved) ? $resolved : [];
}

function meza_product_admin_column_is_visible(WP_Screen $screen, array $aliases): bool
{
    if ($screen->id !== 'edit-product') return false;

    $columns = meza_get_product_admin_columns_for_visibility();
    $hidden = array_map('strval', get_hidden_columns($screen));

    foreach ($aliases as $alias) {
        $alias = (string) $alias;
        if ($alias === '' || !array_key_exists($alias, $columns)) continue;
        if (in_array($alias, $hidden, true)) continue;
        return true;
    }

    return false;
}

function meza_get_edit_screen_for_post_type(string $post_type): ?WP_Screen
{
    $post_type = trim($post_type);
    if ($post_type === '') return null;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (
        $screen instanceof WP_Screen
        && $screen->base === 'edit'
        && (string) ($screen->post_type ?? '') === $post_type
    ) {
        return $screen;
    }

    if (!function_exists('convert_to_screen')) return null;

    $screen = convert_to_screen("edit-{$post_type}");
    return ($screen instanceof WP_Screen) ? $screen : null;
}

function meza_post_type_menu_order_admin_column_is_visible(string $post_type): bool
{
    if (!meza_post_type_supports_menu_order_admin_column($post_type)) return false;

    $screen = meza_get_edit_screen_for_post_type($post_type);
    if (!($screen instanceof WP_Screen)) {
        return meza_post_type_menu_order_admin_column_is_default_visible($post_type);
    }

    $hidden = array_map('strval', get_hidden_columns($screen));
    return !in_array('mz_menu_order', $hidden, true);
}

function meza_get_forced_hidden_product_admin_columns(): array
{
    return [
        'product_tag',
        'taxonomy-product_tag',
        'mz_page_link',
        'wpseo-title',
        'wpseo-metadesc',
        'mz_share_title',
        'mz_share_description',
    ];
}

add_filter('hidden_columns', function ($hidden, $screen, $use_defaults) {
    unset($use_defaults);

    if (!($screen instanceof WP_Screen) || $screen->id !== 'edit-product') return $hidden;

    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];

    return array_values(array_unique(array_merge(
        $hidden,
        meza_get_forced_hidden_product_admin_columns()
    )));
}, 1000, 3);

add_filter('default_hidden_columns', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return $hidden;

    $post_type = (string) ($screen->post_type ?? '');
    if (!meza_post_type_supports_menu_order_admin_column($post_type)) return $hidden;

    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];

    if (meza_post_type_menu_order_admin_column_is_default_visible($post_type)) {
        return array_values(array_diff($hidden, ['mz_menu_order']));
    }

    $hidden[] = 'mz_menu_order';
    return array_values(array_unique($hidden));
}, 300, 2);

add_filter('default_hidden_columns', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return $hidden;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === '' || !meza_post_type_has_permalink($post_type)) return $hidden;
    if (meza_is_acf_admin_post_type($post_type)) return $hidden;

    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];

    if (meza_page_form_admin_column_is_default_visible($post_type)) {
        return array_values(array_diff($hidden, ['mz_page_form']));
    }

    $hidden[] = 'mz_page_form';
    return array_values(array_unique($hidden));
}, 320, 2);

add_filter('default_hidden_columns', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return $hidden;

    $post_type = (string) ($screen->post_type ?? '');
    if (!meza_post_type_shows_summary_admin_column($post_type)) return $hidden;

    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];

    if (meza_summary_admin_column_is_default_visible($post_type)) {
        return array_values(array_diff($hidden, ['mz_summary']));
    }

    $hidden[] = 'mz_summary';
    return array_values(array_unique($hidden));
}, 340, 2);

add_action('current_screen', function ($screen): void {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === '' || !meza_post_type_has_permalink($post_type)) return;
    if (meza_is_acf_admin_post_type($post_type)) return;

    add_filter("manage_edit-{$post_type}_columns", function ($columns) use ($post_type) {
        if (!is_array($columns)) $columns = [];
        if ($post_type === '' || !meza_post_type_has_permalink($post_type)) return $columns;
        if (meza_is_acf_admin_post_type($post_type)) return $columns;

        return meza_insert_admin_column_after($columns, 'mz_page_cta', 'mz_page_form', __('Page Form'));
    }, 1000);

    if (meza_page_form_admin_column_is_default_visible($post_type)) return;

    $user_id = get_current_user_id();
    if ($user_id <= 0) return;

    $meta_key = 'meza_page_form_hidden_migrated_post_types_v2';
    $migrated_post_types = get_user_meta($user_id, $meta_key, true);
    $migrated_post_types = is_array($migrated_post_types)
        ? array_values(array_unique(array_map('strval', $migrated_post_types)))
        : [];

    if (in_array($post_type, $migrated_post_types, true)) return;

    $hidden_key = 'manage' . $screen->id . 'columnshidden';
    $hidden = get_user_option($hidden_key, $user_id);
    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];

    if (!in_array('mz_page_form', $hidden, true)) {
        $hidden[] = 'mz_page_form';
        update_user_option($user_id, $hidden_key, array_values(array_unique($hidden)), true);
    }

    $migrated_post_types[] = $post_type;
    update_user_meta($user_id, $meta_key, array_values(array_unique($migrated_post_types)));
}, 50);

add_action('current_screen', function ($screen): void {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    $post_type = (string) ($screen->post_type ?? '');
    if (!meza_post_type_shows_summary_admin_column($post_type)) return;

    $user_id = get_current_user_id();
    if ($user_id <= 0) return;

    $meta_key = 'meza_summary_visibility_initialized_post_types_v1';
    $initialized_post_types = get_user_meta($user_id, $meta_key, true);
    $initialized_post_types = is_array($initialized_post_types)
        ? array_values(array_unique(array_map('strval', $initialized_post_types)))
        : [];

    if (in_array($post_type, $initialized_post_types, true)) return;

    $hidden_key = 'manage' . $screen->id . 'columnshidden';
    $hidden = get_user_option($hidden_key, $user_id);
    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];

    if (meza_summary_admin_column_is_default_visible($post_type)) {
        $hidden = array_values(array_diff($hidden, ['mz_summary']));
    } elseif (!in_array('mz_summary', $hidden, true)) {
        $hidden[] = 'mz_summary';
    }

    update_user_option($user_id, $hidden_key, array_values(array_unique($hidden)), true);

    $initialized_post_types[] = $post_type;
    update_user_meta($user_id, $meta_key, array_values(array_unique($initialized_post_types)));
}, 55);

add_filter('hidden_columns', function ($hidden, $screen, $use_defaults) {
    unset($use_defaults);

    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return $hidden;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === '' || meza_page_form_admin_column_is_default_visible($post_type)) return $hidden;
    if (!meza_post_type_has_permalink($post_type)) return $hidden;
    if (meza_is_acf_admin_post_type($post_type)) return $hidden;

    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];
    if (in_array('mz_page_form', $hidden, true)) return $hidden;

    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        $hidden[] = 'mz_page_form';
        return array_values(array_unique($hidden));
    }

    $meta_key = 'meza_page_form_hidden_migrated_post_types_v2';
    $migrated_post_types = get_user_meta($user_id, $meta_key, true);
    $migrated_post_types = is_array($migrated_post_types)
        ? array_values(array_unique(array_map('strval', $migrated_post_types)))
        : [];

    if (in_array($post_type, $migrated_post_types, true)) {
        return $hidden;
    }

    $hidden[] = 'mz_page_form';
    $migrated_post_types[] = $post_type;
    update_user_option($user_id, 'manage' . $screen->id . 'columnshidden', array_values(array_unique($hidden)), true);
    update_user_meta($user_id, $meta_key, array_values(array_unique($migrated_post_types)));

    return array_values(array_unique($hidden));
}, 330, 3);

add_filter('hidden_columns', function ($hidden, $screen, $use_defaults) {
    unset($use_defaults);

    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return $hidden;

    $post_type = (string) ($screen->post_type ?? '');
    if (!meza_post_type_shows_summary_admin_column($post_type)) return $hidden;
    if (meza_summary_admin_column_is_default_visible($post_type)) return $hidden;

    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];
    if (in_array('mz_summary', $hidden, true)) return $hidden;

    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        $hidden[] = 'mz_summary';
        return array_values(array_unique($hidden));
    }

    $meta_key = 'meza_summary_visibility_initialized_post_types_v1';
    $initialized_post_types = get_user_meta($user_id, $meta_key, true);
    $initialized_post_types = is_array($initialized_post_types)
        ? array_values(array_unique(array_map('strval', $initialized_post_types)))
        : [];

    if (in_array($post_type, $initialized_post_types, true)) {
        return $hidden;
    }

    $hidden[] = 'mz_summary';
    $initialized_post_types[] = $post_type;
    update_user_option($user_id, 'manage' . $screen->id . 'columnshidden', array_values(array_unique($hidden)), true);
    update_user_meta($user_id, $meta_key, array_values(array_unique($initialized_post_types)));

    return array_values(array_unique($hidden));
}, 350, 3);

add_filter('hidden_columns', function ($hidden, $screen, $use_defaults) {
    unset($use_defaults);

    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return $hidden;

    $post_type = (string) ($screen->post_type ?? '');
    if (!meza_post_type_supports_menu_order_admin_column($post_type)) return $hidden;
    if (!meza_post_type_menu_order_admin_column_is_default_visible($post_type)) return $hidden;

    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];
    return array_values(array_diff($hidden, ['mz_menu_order']));
}, 1100, 3);

function meza_register_datetime_sortable_columns(array $cols): array
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $post_type = ($screen instanceof WP_Screen) ? (string) ($screen->post_type ?? '') : '';
    if (($screen instanceof WP_Screen) && ((string) ($screen->post_type ?? '') === 'form')) {
        $cols['mz_slug'] = ['name', true];
    }
    if (meza_post_type_supports_menu_order_admin_column($post_type)) {
        $cols['mz_menu_order'] = meza_post_type_menu_order_admin_column_is_visible($post_type)
            ? ['menu_order', false, '', '', 'asc']
            : ['menu_order', false];
    }
    $cols['mz_id'] = ['ID', true];
    $cols['mz_published'] = ['date', true];
    // Match the global default admin post-list sort so the active header state is visible on first load.
    $cols['mz_modified'] = ['modified', true, '', '', 'desc'];
    return $cols;
}

add_filter('manage_product_posts_columns', 'meza_customize_product_admin_columns', 1000);

function meza_register_taxonomy_sortable_columns(array $cols): array
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return $cols;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === '') return $cols;

    $taxonomies = get_object_taxonomies($post_type, 'objects');
    if (!is_array($taxonomies)) return $cols;

    foreach ($taxonomies as $taxonomy => $taxonomy_obj) {
        if (!is_object($taxonomy_obj) || empty($taxonomy_obj->show_admin_column)) continue;

        $cols["taxonomy-{$taxonomy}"] = ["mz_tax_{$taxonomy}", false];
    }

    return $cols;
}

function meza_render_posts_list_column(string $column, int $post_id): void
{
    $post = get_post((int) $post_id);
    if (!($post instanceof WP_Post)) {
        if (
            $column === 'mz_modified' ||
            $column === 'mz_published' ||
            $column === 'mz_id' ||
            $column === 'mz_menu_order' ||
            $column === 'mz_form_recipients' ||
            $column === 'mz_cta_link' ||
            $column === 'mz_cta_secondary_link' ||
            $column === 'mz_faq_count' ||
            $column === 'mz_slug' ||
            $column === 'mz_summary' ||
            $column === 'mz_share_title' ||
            $column === 'mz_share_description' ||
            $column === 'mz_profile_title' ||
            $column === 'mz_profile_link' ||
            $column === 'mz_organization_url' ||
            $column === 'mz_review_quote' ||
            $column === 'mz_review_citer' ||
            $column === 'mz_thumbnail' ||
            $column === 'mz_page_link' ||
            $column === 'mz_page_headline' ||
            $column === 'mz_page_cta' ||
            $column === 'mz_page_form'
        ) {
            echo '&mdash;';
        }
        return;
    }

    if ($column === 'mz_id') {
        echo (int) $post_id;
        return;
    }
    if ($column === 'mz_menu_order') {
        echo (int) ($post->menu_order ?? 0);
        return;
    }
    if ($column === 'mz_slug') {
        $slug = (string) ($post->post_name ?? '');
        echo ($slug !== '') ? esc_html($slug) : '&mdash;';
        return;
    }
    if ($column === 'mz_faq_count') {
        echo (int) meza_get_faq_repeater_item_count((int) $post_id);
        return;
    }
    if ($column === 'mz_form_recipients') {
        $recipients = [];

        if (function_exists('get_field')) {
            $email_admin = get_field('email_admin', (int) $post_id);
            if (is_array($email_admin) && function_exists('mzf_parse_recipients')) {
                $recipients = mzf_parse_recipients($email_admin['recipients'] ?? []);
            }
        }

        if (empty($recipients) && function_exists('mzf_parse_recipients')) {
            $recipients = mzf_parse_recipients(get_post_meta((int) $post_id, 'email_admin_recipients', true));
        }

        if (empty($recipients) && function_exists('mzf_parse_recipients')) {
            $recipients = mzf_parse_recipients(get_post_meta((int) $post_id, 'email_recipients', true));
        }

        echo !empty($recipients) ? esc_html(implode(' ', $recipients)) : '&mdash;';
        return;
    }
    if ($column === 'mz_product_type') {
        $product_type = '';
        $product_type_key = '';
        if (function_exists('wc_get_product')) {
            $product = wc_get_product((int) $post_id);
            if ($product instanceof WC_Product) {
                $product_type_key = (string) $product->get_type();
                if (function_exists('wc_get_product_type_label')) {
                    $product_type = (string) wc_get_product_type_label($product);
                } else {
                    $product_types = function_exists('wc_get_product_types') ? wc_get_product_types() : [];

                    if ($product_type_key !== '' && isset($product_types[$product_type_key])) {
                        $product_type = (string) $product_types[$product_type_key];
                    } elseif ($product_type_key !== '') {
                        $product_type = ucwords(str_replace(['-', '_'], ' ', $product_type_key));
                    }
                }
            }
        }
        if ($product_type === '') {
            echo '&mdash;';
            return;
        }

        if ($product_type_key === '') {
            echo esc_html($product_type);
            return;
        }

        $filter_url = add_query_arg([
            'post_type' => 'product',
            'product_type' => $product_type_key,
        ], admin_url('edit.php'));

        echo '<a href="' . esc_url($filter_url) . '">' . esc_html($product_type) . '</a>';
        return;
    }
    if ($column === 'mz_summary') {
        $summary = trim(wp_strip_all_tags((string) ($post->post_excerpt ?? '')));
        echo ($summary !== '') ? esc_html($summary) : '&mdash;';
        return;
    }
    if ($column === 'mz_share_title') {
        $share_title = meza_get_post_yoast_share_title_value((int) $post_id);
        echo ($share_title !== '') ? esc_html($share_title) : '&mdash;';
        return;
    }
    if ($column === 'mz_share_description') {
        $share_description = meza_get_post_yoast_share_description_value((int) $post_id);
        echo ($share_description !== '') ? esc_html($share_description) : '&mdash;';
        return;
    }
    if ($column === 'mz_profile_title') {
        $profile_title = meza_get_profile_title_column_value((int) $post_id);
        echo ($profile_title !== '') ? esc_html($profile_title) : '&mdash;';
        return;
    }
    if ($column === 'mz_profile_link') {
        $url = meza_get_profile_primary_link_url((int) $post_id);

        if ($url === '') {
            echo '&mdash;';
            return;
        }

        echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html(meza_get_admin_link_column_display_text($url)) . '</a>';
        return;
    }
    if ($column === 'mz_cta_link') {
        $url = meza_get_post_link_field_url((int) $post_id, ['link']);

        if ($url === '') {
            echo '&mdash;';
            return;
        }

        echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html(meza_get_admin_link_column_display_text($url)) . '</a>';
        return;
    }
    if ($column === 'mz_cta_secondary_link') {
        $url = meza_get_post_link_field_url((int) $post_id, [
            'link_secondary',
            'secondary_link',
            'link_2',
            'secondary_cta_link',
        ]);

        if ($url === '') {
            echo '&mdash;';
            return;
        }

        echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html(meza_get_admin_link_column_display_text($url)) . '</a>';
        return;
    }
    if ($column === 'mz_organization_url') {
        $url = meza_get_post_link_field_url((int) $post_id, ['url']);

        if ($url === '') {
            echo '&mdash;';
            return;
        }

        echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html(meza_get_admin_link_column_display_text($url)) . '</a>';
        return;
    }
    if ($column === 'mz_review_quote') {
        $quote = '';
        if (function_exists('get_field')) {
            $acf_quote = get_field('quote', (int) $post_id);
            if (is_string($acf_quote)) $quote = trim(wp_strip_all_tags($acf_quote));
        }
        if ($quote === '') $quote = trim(wp_strip_all_tags((string) get_post_meta((int) $post_id, 'quote', true)));
        echo ($quote !== '') ? esc_html($quote) : '&mdash;';
        return;
    }
    if ($column === 'mz_review_citer') {
        $citer = '';
        if (function_exists('get_field')) {
            $acf_citer = get_field('citer', (int) $post_id);
            if (is_string($acf_citer)) $citer = trim(wp_strip_all_tags($acf_citer));
        }
        if ($citer === '') $citer = trim(wp_strip_all_tags((string) get_post_meta((int) $post_id, 'citer', true)));
        if ($citer === '') {
            $citer_name = '';
            $citer_title = '';

            if (function_exists('get_field')) {
                $acf_citer_name = get_field('citer_name', (int) $post_id);
                if (is_string($acf_citer_name)) $citer_name = trim(wp_strip_all_tags($acf_citer_name));

                $acf_citer_title = get_field('citer_title', (int) $post_id);
                if (is_string($acf_citer_title)) $citer_title = trim(wp_strip_all_tags($acf_citer_title));
            }

            if ($citer_name === '') $citer_name = trim(wp_strip_all_tags((string) get_post_meta((int) $post_id, 'citer_name', true)));
            if ($citer_title === '') $citer_title = trim(wp_strip_all_tags((string) get_post_meta((int) $post_id, 'citer_title', true)));

            if ($citer_name !== '' && $citer_title !== '') {
                $citer = $citer_name . ' ' . html_entity_decode('&mdash;', ENT_QUOTES, 'UTF-8') . ' ' . $citer_title;
            } elseif ($citer_name !== '') {
                $citer = $citer_name;
            }
        }
        echo ($citer !== '') ? esc_html($citer) : '&mdash;';
        return;
    }

    if ($column === 'mz_thumbnail') {
        if (!post_type_supports((string) $post->post_type, 'thumbnail')) {
            echo '&mdash;';
            return;
        }

        $thumb_id = (int) get_post_thumbnail_id((int) $post_id);
        $thumb_attrs = [
            'style' => 'width:100px;height:auto;max-width:100px;display:block;margin:0;',
            'loading' => 'lazy',
            'decoding' => 'async',
        ];
        $thumb_html = ($thumb_id > 0)
            ? wp_get_attachment_image($thumb_id, 'medium', false, $thumb_attrs)
            : get_the_post_thumbnail((int) $post_id, 'medium', $thumb_attrs);
        if ($thumb_html === '') {
            echo '&mdash;';
            return;
        }

        $thumb_alt = '';
        $thumb_url = '';
        if ($thumb_id > 0) {
            $thumb_alt = trim((string) get_post_meta($thumb_id, '_wp_attachment_image_alt', true));
            $thumb_url = (string) wp_get_attachment_url($thumb_id);
        }

        $actions = [];
        $image_edit_link = ($thumb_id > 0) ? get_edit_post_link($thumb_id) : '';
        if (is_string($image_edit_link) && $image_edit_link !== '') {
            if ($thumb_alt === '') {
                $actions[] = '<span class="fix-alt"><a href="' . esc_url($image_edit_link) . '" target="_blank" rel="noopener noreferrer" style="color:#b32d2e;font-weight:600;">' . esc_html__('Fix Alt Text') . '</a></span>';
            } else {
                $actions[] = '<span class="edit"><a href="' . esc_url($image_edit_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Edit') . '</a></span>';
            }
        }

        if ($thumb_url !== '') {
            $actions[] = '<span class="view"><a href="' . esc_url($thumb_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View') . '</a></span>';
        }

        if ($thumb_url !== '') {
            $actions[] = '<span class="download"><a href="' . esc_url($thumb_url) . '" download>' . esc_html__('Download') . '</a></span>';
        }

        echo '<span class="mz-thumb-wrap">';
        if (is_string($image_edit_link) && $image_edit_link !== '') {
            echo '<a href="' . esc_url($image_edit_link) . '" target="_blank" rel="noopener noreferrer">' . $thumb_html . '</a>';
        } else {
            echo $thumb_html;
        }
        echo '</span>';
        if (!empty($actions)) echo '<div class="row-actions">' . implode(' | ', $actions) . '</div>';
        return;
    }

    if ($column === 'mz_page_link') {
        if (!meza_post_has_permalink((int) $post_id)) {
            echo '&mdash;';
            return;
        }
        $url = (string) get_permalink((int) $post_id);

        echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html(meza_get_admin_link_column_display_text($url)) . '</a>';
        $actions = [];

        $edit_link = get_edit_post_link((int) $post_id);
        if (is_string($edit_link) && $edit_link !== '') {
            $actions[] = '<span class="edit"><a href="' . esc_url($edit_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Edit') . '</a></span>';
        }

        $actions[] = '<span class="view"><a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View') . '</a></span>';
        $actions[] = '<span class="copy"><a href="#" class="mz-copy-link" data-copy-text="' . esc_attr($url) . '">' . esc_html__('Copy URL') . '</a></span>';

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $separator = ($screen instanceof WP_Screen && $screen->id === 'edit-product') ? '' : ' | ';
        echo '<div class="row-actions">' . implode($separator, $actions) . '</div>';
        return;
    }

    if ($column === 'mz_page_headline') {
        if (!meza_post_type_has_pages($post) || !meza_post_has_permalink((int) $post_id)) {
            echo '&mdash;';
            return;
        }

        $headline = '';
        if (function_exists('get_field')) {
            $hero_group = get_field('section_hero', (int) $post_id);
            if (is_array($hero_group) && isset($hero_group['headline']) && is_string($hero_group['headline'])) {
                $headline = trim((string) $hero_group['headline']);
            }
            $acf_headline = get_field('section_hero_headline', (int) $post_id);
            if ($headline === '' && is_string($acf_headline)) $headline = trim($acf_headline);
        }
        if ($headline === '') {
            $headline = trim((string) get_post_meta((int) $post_id, 'section_hero_headline', true));
        }
        if ($headline === '') {
            $hero_meta = get_post_meta((int) $post_id, 'section_hero', true);
            if (is_array($hero_meta) && isset($hero_meta['headline']) && is_string($hero_meta['headline'])) {
                $headline = trim((string) $hero_meta['headline']);
            }
        }

        echo ($headline !== '') ? esc_html($headline) : '&mdash;';
        return;
    }

    if ($column === 'mz_page_cta') {
        if (!meza_post_type_has_pages($post) || !meza_post_has_permalink((int) $post_id)) {
            echo '&mdash;';
            return;
        }

        $cta_ids = [];
        if (function_exists('get_field')) {
            $acf_cta_candidates = [
                get_field('section_cta_cta', (int) $post_id),
                get_field('cta', (int) $post_id),
                get_field('section_cta', (int) $post_id),
            ];

            foreach ($acf_cta_candidates as $acf_cta) {
                if (is_array($acf_cta) && isset($acf_cta['cta'])) $acf_cta = $acf_cta['cta'];

                if (is_array($acf_cta)) {
                    foreach ($acf_cta as $entry) {
                        if (is_numeric($entry)) $cta_ids[] = (int) $entry;
                        if ($entry instanceof WP_Post) $cta_ids[] = (int) $entry->ID;
                    }
                } elseif (is_numeric($acf_cta)) {
                    $cta_ids[] = (int) $acf_cta;
                } elseif ($acf_cta instanceof WP_Post) {
                    $cta_ids[] = (int) $acf_cta->ID;
                }
            }
        }

        if (empty($cta_ids)) {
            $raw_cta_candidates = [
                get_post_meta((int) $post_id, 'section_cta_cta', true),
                get_post_meta((int) $post_id, 'cta', true),
                get_post_meta((int) $post_id, 'section_cta', true),
            ];

            foreach ($raw_cta_candidates as $raw_cta) {
                if (is_array($raw_cta) && isset($raw_cta['cta'])) $raw_cta = $raw_cta['cta'];

                if (is_array($raw_cta)) {
                    foreach ($raw_cta as $entry) {
                        if (is_numeric($entry)) $cta_ids[] = (int) $entry;
                    }
                } elseif (is_numeric($raw_cta)) {
                    $cta_ids[] = (int) $raw_cta;
                }
            }
        }

        $cta_ids = array_values(array_unique(array_filter($cta_ids, function ($id) {
            return $id > 0;
        })));
        if (empty($cta_ids)) {
            echo '&mdash;';
            return;
        }

        $links = [];
        foreach ($cta_ids as $cta_id) {
            $title = get_the_title($cta_id);
            $edit_link = get_edit_post_link($cta_id);
            if (!is_string($title) || trim($title) === '') $title = sprintf(__('CTA #%d'), $cta_id);

            if (is_string($edit_link) && $edit_link !== '') {
                $links[] = '<a href="' . esc_url($edit_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html($title) . '</a>';
            } else {
                $links[] = esc_html($title);
            }
        }

        echo !empty($links) ? implode('<br>', $links) : '&mdash;';
        return;
    }

    if ($column === 'mz_page_form') {
        if (!meza_post_type_has_pages($post) || !meza_post_has_permalink((int) $post_id)) {
            echo '&mdash;';
            return;
        }

        $form_ids = meza_get_page_form_ids((int) $post_id);
        if (empty($form_ids)) {
            echo '&mdash;';
            return;
        }

        $links = [];
        foreach ($form_ids as $form_id) {
            $title = get_the_title($form_id);
            $edit_link = get_edit_post_link($form_id);
            if (!is_string($title) || trim($title) === '') $title = sprintf(__('Form #%d'), $form_id);

            if (is_string($edit_link) && $edit_link !== '') {
                $links[] = '<a href="' . esc_url($edit_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html($title) . '</a>';
            } else {
                $links[] = esc_html($title);
            }
        }

        echo !empty($links) ? implode('<br>', $links) : '&mdash;';
        return;
    }

    if ($column === 'mz_modified') {
        $modified_timestamp = get_post_modified_time('U', false, $post, true);
        if (!$modified_timestamp) {
            echo '&mdash;';
            return;
        }

        $modified_by_id = (int) get_post_meta((int) $post_id, '_edit_last', true);
        if ($modified_by_id <= 0) $modified_by_id = (int) $post->post_author;
        $modified_by_name = meza_get_user_first_name_by_id($modified_by_id);
        $modified_by_email = meza_get_user_email_by_id($modified_by_id);

        $header = esc_html__('Last Modified');
        if ($modified_by_name !== '') {
            $header .= ' ' . esc_html__('by') . ' ';
            if ($modified_by_email !== '') {
                $header .= '<a href="' . esc_url('mailto:' . $modified_by_email) . '">' . esc_html($modified_by_name) . '</a>';
            } else {
                $header .= esc_html($modified_by_name);
            }
        }

        $date = wp_date(get_option('date_format'), $modified_timestamp);
        $time = meza_compact_meridiem(wp_date(get_option('time_format'), $modified_timestamp));
        $line = sprintf(__('%1$s at %2$s'), $date, $time);
        echo $header . '<br>' . esc_html($line);
        return;
    }

    if ($column !== 'mz_published') return;

    $published_timestamp = get_post_time('U', false, $post, true);
    if (!$published_timestamp) {
        echo '&mdash;';
        return;
    }

    $published_by_name = meza_get_user_first_name_by_id((int) $post->post_author);
    $published_by_email = meza_get_user_email_by_id((int) $post->post_author);
    $header = esc_html__('Published');
    if ($published_by_name !== '') {
        $header .= ' ' . esc_html__('by') . ' ';
        if ($published_by_email !== '') {
            $header .= '<a href="' . esc_url('mailto:' . $published_by_email) . '">' . esc_html($published_by_name) . '</a>';
        } else {
            $header .= esc_html($published_by_name);
        }
    }
    $date = wp_date(get_option('date_format'), $published_timestamp);
    $time = meza_compact_meridiem(wp_date(get_option('time_format'), $published_timestamp));
    $line = sprintf(__('%1$s at %2$s'), $date, $time);
    echo $header . '<br>' . esc_html($line);
}

// Apply Published/Modified columns to all post-type list tables on edit screens.
add_action('current_screen', function ($screen) {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === '') return;
    static $registered = [];
    if (isset($registered[$post_type])) return;
    $registered[$post_type] = true;

    add_filter("manage_{$post_type}_posts_columns", 'meza_normalize_datetime_columns', 1000);
    add_filter("manage_{$post_type}_posts_columns", function ($columns) use ($post_type) {
        return meza_ensure_summary_admin_column(is_array($columns) ? $columns : [], $post_type);
    }, 100000);
    add_action("manage_{$post_type}_posts_custom_column", 'meza_render_posts_list_column', 100, 2);
    add_filter("manage_edit-{$post_type}_sortable_columns", 'meza_register_datetime_sortable_columns', 1000);
    add_filter("manage_edit-{$post_type}_sortable_columns", 'meza_register_taxonomy_sortable_columns', 1001);
});

function meza_remove_yoast_score_filters(): void
{
    $wpseo_meta_columns = $GLOBALS['wpseo_meta_columns'] ?? null;
    if (!is_object($wpseo_meta_columns)) return;

    if (method_exists($wpseo_meta_columns, 'posts_filter_dropdown')) {
        remove_action('restrict_manage_posts', [$wpseo_meta_columns, 'posts_filter_dropdown']);
    }

    if (method_exists($wpseo_meta_columns, 'posts_filter_dropdown_readability')) {
        remove_action('restrict_manage_posts', [$wpseo_meta_columns, 'posts_filter_dropdown_readability']);
    }
}

function meza_disable_yoast_cornerstone_option($options)
{
    if (!is_array($options)) return $options;

    $options['enable_cornerstone_content'] = false;

    return $options;
}

function meza_remove_yoast_edit_view_tabs($views)
{
    if (!is_array($views)) return $views;

    foreach (array_keys($views) as $key) {
        if (str_starts_with((string) $key, 'yoast_')) {
            unset($views[$key]);
        }
    }

    return $views;
}

function meza_remove_sorting_view_tab($views)
{
    if (!is_array($views)) return $views;

    unset($views['byorder']);

    return $views;
}

function meza_is_woocommerce_admin_list_screen($screen): bool
{
    if (!($screen instanceof WP_Screen)) return false;

    if ($screen->base === 'edit' && in_array((string) ($screen->post_type ?? ''), ['product', 'shop_coupon', 'shop_order'], true)) {
        return true;
    }

    return in_array($screen->id, ['edit-product', 'edit-shop_coupon', 'edit-shop_order', 'woocommerce_page_wc-orders'], true);
}

function meza_is_woocommerce_admin_screen($screen): bool
{
    if (!($screen instanceof WP_Screen)) return false;
    if (meza_is_woocommerce_admin_list_screen($screen)) return true;

    $screen_id = (string) $screen->id;
    $screen_base = (string) $screen->base;
    $post_type = (string) ($screen->post_type ?? '');
    $taxonomy = (string) ($screen->taxonomy ?? '');

    if ($screen_base === 'post' && in_array($post_type, ['product', 'shop_coupon', 'shop_order'], true)) {
        return true;
    }

    if (in_array($screen_id, ['edit-product_cat', 'edit-product_tag', 'product_page_product_attributes', 'woocommerce_page_product-reviews', 'product_page_product-reviews'], true)) {
        return true;
    }

    if ($screen_base === 'edit-tags' && ($taxonomy === 'product_cat' || $taxonomy === 'product_tag' || str_starts_with($taxonomy, 'pa_'))) {
        return true;
    }

    if ($screen_base === 'edit-comments' || $screen_base === 'comment') {
        $comment_type = isset($_GET['comment_type']) ? sanitize_key(wp_unslash((string) $_GET['comment_type'])) : '';
        if ($comment_type === 'review') {
            return true;
        }
    }

    if (
        str_starts_with($screen_id, 'admin_page_meza-woocommerce-analytics')
        || str_starts_with($screen_base, 'admin_page_meza-woocommerce-analytics')
        || str_starts_with($screen_id, 'admin_page_woocommerce-marketing')
        || str_starts_with($screen_base, 'admin_page_woocommerce-marketing')
        || str_starts_with($screen_id, 'woocommerce_page_woocommerce-marketing')
        || str_starts_with($screen_base, 'woocommerce_page_woocommerce-marketing')
    ) {
        return true;
    }

    return str_starts_with($screen_id, 'woocommerce_page_wc-')
        || str_starts_with($screen_base, 'woocommerce_page_wc-');
}

add_filter('option_wpseo', 'meza_disable_yoast_cornerstone_option', 1000);
add_filter('wpseo_cornerstone_post_types', '__return_empty_array', 1000);

add_action('current_screen', function ($screen) {
    if (!($screen instanceof WP_Screen)) return;

    if ($screen->base === 'edit') {
        meza_remove_yoast_score_filters();

        $post_type = (string) ($screen->post_type ?? '');
        if ($post_type !== '') {
            add_filter("views_edit-{$post_type}", 'meza_remove_yoast_edit_view_tabs', 9999);
            add_filter("views_edit-{$post_type}", 'meza_remove_sorting_view_tab', 100000);
        }
    }

    if (!meza_is_woocommerce_admin_screen($screen)) return;

    if (class_exists(\Automattic\WooCommerce\Internal\Admin\Loader::class)) {
        remove_action('in_admin_header', [\Automattic\WooCommerce\Internal\Admin\Loader::class, 'embed_page_header']);
        remove_filter('admin_body_class', [\Automattic\WooCommerce\Internal\Admin\Loader::class, 'add_admin_body_classes']);
        remove_action('admin_head', [\Automattic\WooCommerce\Internal\Admin\Loader::class, 'remove_notices']);
        remove_action('admin_notices', [\Automattic\WooCommerce\Internal\Admin\Loader::class, 'inject_before_notices'], -9999);
        remove_action('admin_notices', [\Automattic\WooCommerce\Internal\Admin\Loader::class, 'inject_after_notices'], PHP_INT_MAX);
    }
}, 1000);

add_action('admin_head', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!meza_is_woocommerce_admin_screen($screen)) return;

    echo '<style id="meza-woocommerce-admin-layout-reset">#wpbody{margin-top:0!important;}</style>';
}, 1000);

add_action('admin_head', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!meza_is_woocommerce_admin_screen($screen)) return;
?>
    <script id="meza-woocommerce-admin-title-case">
        (() => {
            const smallWords = new Set(['a', 'an', 'and', 'as', 'at', 'but', 'by', 'en', 'for', 'if', 'in', 'of', 'on', 'or', 'the', 'to', 'v', 'via', 'vs']);
            const selectors = [
                'h1',
                '.woocommerce-layout__header-title',
                '.wrap .wp-heading-inline'
            ];

            const titleCaseText = (text) => {
                const normalized = String(text || '').trim().replace(/\s+/g, ' ');
                if (!normalized) return '';

                const words = normalized.toLowerCase().split(' ');
                return words.map((word, index) => {
                    if (!word) return word;

                    const parts = word.split(/([\/-])/);
                    const transformed = parts.map((part) => {
                        if (part === '/' || part === '-') return part;
                        if (!part) return part;

                        if (index > 0 && smallWords.has(part)) {
                            return part;
                        }

                        return part.charAt(0).toUpperCase() + part.slice(1);
                    });

                    return transformed.join('');
                }).join(' ');
            };

            const applyTitleCase = () => {
                const seen = new Set();

                document.querySelectorAll(selectors.join(',')).forEach((node) => {
                    if (!(node instanceof HTMLElement) || seen.has(node)) return;
                    seen.add(node);

                    const text = node.textContent || '';
                    const updated = titleCaseText(text);
                    if (updated && updated !== text.trim()) {
                        node.textContent = updated;
                    }
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', applyTitleCase, {
                    once: true
                });
            } else {
                applyTitleCase();
            }

            const observer = new MutationObserver(() => applyTitleCase());
            observer.observe(document.documentElement, {
                childList: true,
                subtree: true
            });
        })();
    </script>
<?php
}, 1001);

add_filter('woocommerce_products_admin_list_table_filters', function ($filters) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || $screen->id !== 'edit-product') return $filters;

    if (!meza_product_admin_column_is_visible($screen, ['taxonomy-product_brand', 'product_brand'])) {
        unset($filters['product_brand']);
    }

    if (!meza_product_admin_column_is_visible($screen, ['product_cat', 'taxonomy-product_cat'])) {
        unset($filters['product_category']);
    }

    if (!meza_product_admin_column_is_visible($screen, ['mz_product_type'])) {
        unset($filters['product_type']);
    }

    if (!meza_product_admin_column_is_visible($screen, ['is_in_stock'])) {
        unset($filters['stock_status']);
    }

    return $filters;
}, 1000);

// Keep explicit event start-date sorting. Global default ordering is set below.
add_action('pre_get_posts', function (WP_Query $q) {
    global $pagenow;
    if (!is_admin() || !$q->is_main_query() || $pagenow !== 'edit.php' || $q->get('post_type') !== 'event') return;

    $orderby = (string) $q->get('orderby');
    $order   = strtoupper((string) $q->get('order'));
    $order   = in_array($order, ['ASC', 'DESC'], true) ? $order : '';

    if ($orderby === 'start_datetime_order') {
        $dir = $order ?: 'DESC';
        $q->set('meta_key', 'start_datetime_raw');
        $q->set('meta_type', 'DATETIME');
        $q->set('orderby', [
            'meta_value' => $dir,
            'ID'         => 'DESC',
        ]);
        $q->set('order', $dir);
        return;
    }

    if ($orderby === '') {
        $q->set('meta_key', '');
        $q->set('meta_type', '');
    }
});

/** ================================
 *  CTA ADMIN SORTING
 *  ================================ */

// Default all admin post list tables to "Last Modified" DESC unless user selected a different sort.
add_action('pre_get_posts', function (WP_Query $q) {
    global $pagenow;
    if (!is_admin() || !$q->is_main_query() || $pagenow !== 'edit.php') return;

    $post_type = (string) $q->get('post_type');
    if (!meza_post_type_supports_menu_order_admin_column($post_type)) return;

    $orderby = (string) $q->get('orderby');
    if (!in_array($orderby, ['menu_order', 'mz_menu_order'], true)) return;

    $order = strtoupper((string) $q->get('order'));
    $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC';

    $q->set('orderby', [
        'menu_order' => $order,
        'title' => 'ASC',
    ]);
    $q->set('order', $order);
}, 95);

add_action('pre_get_posts', function (WP_Query $q) {
    global $pagenow;
    if (!is_admin() || !$q->is_main_query() || $pagenow !== 'edit.php') return;

    $post_type = (string) $q->get('post_type');
    if (
        meza_post_type_menu_order_admin_column_is_visible($post_type)
    ) {
        if (isset($_GET['orderby']) && $_GET['orderby'] !== '') return;

        $q->set('orderby', 'menu_order');
        $q->set('order', 'ASC');
        $_GET['orderby'] = 'menu_order';
        $_REQUEST['orderby'] = 'menu_order';
        $_GET['order'] = 'asc';
        $_REQUEST['order'] = 'asc';
        return;
    }

    // Respect explicit user sorting from list-table header clicks.
    if (isset($_GET['orderby']) && $_GET['orderby'] !== '') return;

    $q->set('orderby', 'modified');
    $q->set('order', 'DESC');
}, 100);

// Enable alphabetical sorting for taxonomy list columns on all post list tables.
add_action('pre_get_posts', function (WP_Query $q) {
    global $pagenow;
    if (!is_admin() || !$q->is_main_query() || $pagenow !== 'edit.php') return;

    $orderby = (string) $q->get('orderby');
    if (!str_starts_with($orderby, 'mz_tax_')) return;

    $taxonomy = substr($orderby, 7);
    if (!is_string($taxonomy) || $taxonomy === '') return;
    if (!taxonomy_exists($taxonomy)) return;

    $post_type = (string) $q->get('post_type');
    if ($post_type === '' || !is_object_in_taxonomy($post_type, $taxonomy)) return;

    $order = strtoupper((string) $q->get('order'));
    $q->set('order', in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC');
    $q->set('meza_tax_sort', $taxonomy);
});

add_filter('posts_clauses', function (array $clauses, WP_Query $q): array {
    if (!is_admin() || !$q->is_main_query()) return $clauses;

    $taxonomy = (string) $q->get('meza_tax_sort');
    if ($taxonomy === '') return $clauses;

    global $wpdb;
    $order = strtoupper((string) $q->get('order'));
    $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC';
    $empty_rank_order = ($order === 'DESC') ? 'DESC' : 'ASC';
    $taxonomy_sql = esc_sql($taxonomy);

    $clauses['join'] .= " LEFT JOIN {$wpdb->term_relationships} AS meza_tr ON ({$wpdb->posts}.ID = meza_tr.object_id)";
    $clauses['join'] .= " LEFT JOIN {$wpdb->term_taxonomy} AS meza_tt ON (meza_tr.term_taxonomy_id = meza_tt.term_taxonomy_id AND meza_tt.taxonomy = '{$taxonomy_sql}')";
    $clauses['join'] .= " LEFT JOIN {$wpdb->terms} AS meza_t ON (meza_tt.term_id = meza_t.term_id)";

    $clauses['groupby'] = "{$wpdb->posts}.ID";
    $term_names_expr = "GROUP_CONCAT(DISTINCT meza_t.name ORDER BY meza_t.name ASC SEPARATOR ', ')";
    $clauses['orderby'] =
        "CASE WHEN NULLIF(TRIM(MIN(meza_t.name)), '') IS NULL THEN 1 ELSE 0 END {$empty_rank_order}, " .
        "COALESCE({$term_names_expr}, '') {$order}, " .
        "{$wpdb->posts}.post_title ASC";

    return $clauses;
}, 20, 2);

/** ================================
 *  YOAST COLUMN SORTING OVERRIDES
 *  ================================ */

// Rename Yoast list-table column labels for clarity.
add_action('current_screen', function ($screen) {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === '') return;

    add_filter("manage_{$post_type}_posts_columns", function ($cols) {
        if (!is_array($cols)) return $cols;

        foreach (['wpseo-links', 'wpseo-linked', 'wpseo-score', 'wpseo-score-readability', 'wpseo-focuskw'] as $column_id) {
            if (array_key_exists($column_id, $cols)) unset($cols[$column_id]);
        }

        if (array_key_exists('wpseo-title', $cols)) {
            $cols['wpseo-title'] = 'Meta Title';
        }

        if (array_key_exists('wpseo-metadesc', $cols)) {
            $cols['wpseo-metadesc'] = 'Meta Description';
        }

        return $cols;
    }, 9999);
});

add_action('current_screen', function ($screen) {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit-tags') return;

    $taxonomy = (string) ($screen->taxonomy ?? '');
    if ($taxonomy === '') return;

    add_filter("manage_edit-{$taxonomy}_columns", function ($cols) {
        if (!is_array($cols)) return $cols;

        foreach (['wpseo-score', 'wpseo-score-readability'] as $column_id) {
            if (array_key_exists($column_id, $cols)) unset($cols[$column_id]);
        }

        return $cols;
    }, 9999);
});

// Keep Yoast metadata columns readable with fixed max widths.
add_action('admin_head', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    echo '<style id="meza-yoast-column-widths">'
        . '.wp-list-table th.column-wpseo-title,.wp-list-table td.column-wpseo-title{width:225px;max-width:225px;}'
        . '.wp-list-table th.column-mz_share_title,.wp-list-table td.column-mz_share_title{width:225px;max-width:225px;}'
        . '.wp-list-table th.column-wpseo-metadesc,.wp-list-table td.column-wpseo-metadesc{width:275px;max-width:275px;}'
        . '.wp-list-table th.column-mz_share_description,.wp-list-table td.column-mz_share_description{width:275px;max-width:275px;}'
        . '.wp-list-table th.column-mz_page_link,.wp-list-table td.column-mz_page_link{width:225px;max-width:225px;}'
        . '</style>';
});

// Remove sortable behavior from Yoast SEO meta description columns.
add_action('current_screen', function ($screen) {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === '') return;

    add_filter("manage_edit-{$post_type}_sortable_columns", function ($cols) {
        foreach (array_keys($cols) as $key) {
            $normalized = strtolower((string) $key);
            if (
                ($normalized === 'wpseo-score') ||
                ($normalized === 'wpseo-score-readability') ||
                ($normalized === 'wpseo-focuskw') ||
                str_contains($normalized, 'metadesc') ||
                str_contains($normalized, 'meta-desc') ||
                ($normalized === 'wpseo-metadesc')
            ) {
                unset($cols[$key]);
            }
        }

        return $cols;
    }, 9999);
});

/** ================================
 *  PAGES LIST COLUMN NORMALIZATION
 *  ================================ */

function meza_get_page_type_label(int $post_id): string
{
    // Prefer ACF value when available.
    if (function_exists('get_field')) {
        $acf_value = get_field('page_type', $post_id);
        if (is_string($acf_value) && trim($acf_value) !== '') return trim($acf_value);
        if (is_array($acf_value) && isset($acf_value['label']) && is_string($acf_value['label'])) {
            $label = trim($acf_value['label']);
            if ($label !== '') return $label;
        }
    }

    // Fallback to raw post meta.
    $meta_value = get_post_meta($post_id, 'page_type', true);
    if (is_string($meta_value) && trim($meta_value) !== '') return trim($meta_value);

    // Fallback to a `page_type` taxonomy term label when present.
    $terms = get_the_terms($post_id, 'page_type');
    if (!is_wp_error($terms) && !empty($terms)) {
        $first = reset($terms);
        if ($first && isset($first->name) && is_string($first->name) && trim($first->name) !== '') {
            return trim($first->name);
        }
    }

    return '';
}

function meza_get_page_type_choice_map(): array
{
    if (!function_exists('get_field_object')) return [];

    $field = get_field_object('page_type', 0, false, false);
    if (!is_array($field) || !isset($field['choices']) || !is_array($field['choices'])) return [];

    $choices = [];
    foreach ($field['choices'] as $value => $label) {
        $value = is_scalar($value) ? trim((string) $value) : '';
        $label = is_scalar($label) ? trim((string) $label) : '';
        if ($value === '' || $label === '') continue;
        $choices[$value] = $label;
    }

    return $choices;
}

function meza_get_page_template_sort_map(): array
{
    $map = [];

    global $wpdb;
    $used_templates = $wpdb->get_col(
        "SELECT DISTINCT pm.meta_value
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON (p.ID = pm.post_id)
        WHERE pm.meta_key = '_wp_page_template'
          AND pm.meta_value IS NOT NULL
          AND pm.meta_value <> ''
          AND pm.meta_value <> 'default'
          AND p.post_type = 'page'"
    );

    if (is_array($used_templates)) {
        foreach ($used_templates as $file) {
            $file = trim((string) $file);
            if ($file === '') continue;

            $template_path = locate_template($file, false, false);
            if (!is_string($template_path) || $template_path === '' || !is_readable($template_path)) continue;

            $header = get_file_data($template_path, ['template_name' => 'Template Name']);
            $label = trim((string) ($header['template_name'] ?? ''));
            if ($label === '') continue;

            $map[$file] = $label;
        }
    }

    $registered_templates = wp_get_theme()->get_page_templates(null, 'page');
    if (is_array($registered_templates)) {
        foreach ($registered_templates as $label => $file) {
            $file = trim((string) $file);
            $label = trim((string) $label);
            if ($file === '' || $label === '') continue;
            if (!isset($map[$file])) $map[$file] = $label;
        }
    }

    return $map;
}

function meza_get_page_type_sort_map(): array
{
    $map = meza_get_page_type_choice_map();

    $aliases = [
        'basic' => 'Basic Page',
        'basic page' => 'Basic Page',
        'basic-page' => 'Basic Page',
        'basic_page' => 'Basic Page',
    ];

    foreach (meza_get_page_template_sort_map() as $file => $label) {
        $file_stem = strtolower(trim((string) pathinfo($file, PATHINFO_FILENAME)));
        if ($file_stem === '') continue;

        $alias_seed = $file_stem;
        if (str_starts_with($alias_seed, 'page-')) {
            $alias_seed = substr($alias_seed, 5);
        }

        $variants = [
            strtolower(trim($label)),
            $alias_seed,
            str_replace('-', ' ', $alias_seed),
            str_replace('-', '_', $alias_seed),
        ];

        foreach ($variants as $variant) {
            $variant = trim((string) $variant);
            if ($variant === '') continue;
            if (!isset($map[$variant])) $map[$variant] = $label;
        }
    }

    foreach ($aliases as $value => $label) {
        if (!isset($map[$value])) $map[$value] = $label;
    }

    return $map;
}

function meza_get_special_page_definitions(): array
{
    return [
        [
            'option_key' => 'page_on_front',
            'label' => __('Front Page'),
            'settings_url' => admin_url('options-reading.php'),
            'capability' => 'manage_options',
        ],
        [
            'option_key' => 'page_for_posts',
            'label' => __('Posts Page'),
            'settings_url' => admin_url('options-reading.php'),
            'capability' => 'manage_options',
        ],
        [
            'option_key' => 'wp_page_for_privacy_policy',
            'label' => __('Privacy Policy Page'),
            'settings_url' => admin_url('options-privacy.php'),
            'capability' => 'manage_privacy_options',
        ],
        [
            'option_key' => 'meza_page_for_cookie_policy',
            'label' => __('Cookie Policy Page'),
            'settings_url' => admin_url('options-privacy.php'),
            'capability' => 'manage_privacy_options',
        ],
    ];
}

function meza_get_page_type_dashicon_class(string $label): string
{
    $normalized = strtolower(trim(wp_strip_all_tags($label)));
    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

    if ($normalized === '') return 'dashicons-media-document';
    if (str_contains($normalized, 'front page') || str_contains($normalized, 'home')) return 'dashicons-admin-home';
    if (str_contains($normalized, 'posts page') || str_contains($normalized, 'blog')) return 'dashicons-admin-post';
    if (str_contains($normalized, 'privacy')) return 'dashicons-privacy';
    if (str_contains($normalized, 'cookie')) return 'dashicons-hidden';
    if (str_contains($normalized, 'about')) return 'dashicons-id';
    if (str_contains($normalized, 'basic page')) return 'dashicons-welcome-write-blog';
    if (str_contains($normalized, 'form')) return 'dashicons-feedback';
    if (str_contains($normalized, 'style')) return 'dashicons-art';
    if (str_contains($normalized, 'view') || str_contains($normalized, 'layout')) return 'dashicons-screenoptions';
    if (str_contains($normalized, 'template')) return 'dashicons-layout';
    if (str_contains($normalized, 'page')) return 'dashicons-welcome-add-page';

    return 'dashicons-media-document';
}

function meza_get_page_type_label_with_dashicon(string $label): string
{
    $label = trim(wp_strip_all_tags($label));
    if ($label === '') return '&mdash;';

    $icon_class = meza_get_page_type_dashicon_class($label);

    return sprintf(
        '<span class="meza-page-type-label" style="display:inline-flex;align-items:center;gap:4px;vertical-align:middle;"><span class="dashicons %1$s" aria-hidden="true" style="font-size:16px;width:16px;height:16px;line-height:16px;display:inline-flex;align-items:center;justify-content:center;"></span><span class="meza-page-type-label-text">%2$s</span></span>',
        esc_attr($icon_class),
        esc_html($label)
    );
}

function meza_get_post_type_dashicon_class(string $post_type): string
{
    $post_type = trim($post_type);
    if ($post_type === 'post') return 'dashicons-admin-post';
    if ($post_type === 'page') return 'dashicons-admin-page';

    $explicit_icon_map = [
        'tribe_events' => 'dashicons-calendar-alt',
        'tribe_venue' => 'dashicons-location-alt',
        'tribe_organizer' => 'dashicons-groups',
        'project' => 'dashicons-portfolio',
        'provider' => 'dashicons-admin-multisite',
        'sponsor' => 'dashicons-awards',
        'popup' => 'dashicons-admin-comments',
        'popup_theme' => 'dashicons-admin-appearance',
        'view' => 'dashicons-screenoptions',
        'view-template' => 'dashicons-layout',
        'dd_layouts' => 'dashicons-screenoptions',
    ];

    if (isset($explicit_icon_map[$post_type])) {
        return $explicit_icon_map[$post_type];
    }

    $post_type_object = get_post_type_object($post_type);
    if ($post_type_object instanceof WP_Post_Type) {
        $label = trim((string) ($post_type_object->labels->name ?? $post_type_object->labels->singular_name ?? ''));
        $keyword_dashicon = meza_get_keyword_dashicon_for_top_level_menu_item($label, $post_type);
        if ($keyword_dashicon !== '') {
            return $keyword_dashicon;
        }

        $menu_icon = trim((string) ($post_type_object->menu_icon ?? ''));
        if ($menu_icon !== '' && str_contains($menu_icon, 'dashicons-')) {
            return $menu_icon;
        }
    }

    return 'dashicons-admin-post';
}

function meza_get_post_type_template_label_with_dashicon(string $post_type, string $label): string
{
    $post_type = trim($post_type);
    $label = trim(wp_strip_all_tags($label));
    if ($post_type === '' || $label === '') return '&mdash;';

    $icon_class = meza_get_post_type_dashicon_class($post_type);

    return sprintf(
        '<span class="meza-page-type-label" style="display:inline-flex;align-items:center;gap:4px;vertical-align:middle;"><span class="dashicons %1$s" aria-hidden="true" style="font-size:16px;width:16px;height:16px;line-height:16px;display:inline-flex;align-items:center;justify-content:center;"></span><span class="meza-page-type-label-text">%2$s</span></span>',
        esc_attr($icon_class),
        esc_html($label)
    );
}

function meza_get_page_template_plain_label(int $post_id): string
{
    $special_page_label = meza_get_special_page_label($post_id);
    if ($special_page_label !== '') return $special_page_label;

    $page_type = trim(meza_get_page_type_label($post_id));
    if ($page_type !== '') return $page_type;

    $template = (string) get_post_meta($post_id, '_wp_page_template', true);
    if ($template === '' || $template === 'default') {
        return __('Basic Page');
    }

    $template_path = locate_template($template, false, false);
    if (is_string($template_path) && $template_path !== '' && is_readable($template_path)) {
        $header = get_file_data($template_path, ['template_name' => 'Template Name']);
        $template_name = trim((string) ($header['template_name'] ?? ''));
        if ($template_name !== '') return $template_name;
    }

    $post = get_post($post_id);
    $templates = wp_get_theme()->get_page_templates($post instanceof WP_Post ? $post : null, 'page');
    foreach ($templates as $label => $file) {
        if ((string) $file !== $template) continue;
        $clean_label = trim((string) $label);
        if ($clean_label !== '') return $clean_label;
    }

    return '';
}

function meza_get_page_template_label(int $post_id): string
{
    $label = meza_get_page_template_plain_label($post_id);
    if ($label === '') return '&mdash;';

    return meza_get_page_type_label_with_dashicon($label);
}

function meza_get_special_page_label(int $post_id): string
{
    foreach (meza_get_special_page_definitions() as $definition) {
        $option_key = (string) ($definition['option_key'] ?? '');
        $label = (string) ($definition['label'] ?? '');
        if ($option_key === '' || $label === '') continue;
        if ((int) get_option($option_key) === $post_id) return $label;
    }

    return '';
}

function meza_get_special_page_update_link(int $post_id): string
{
    foreach (meza_get_special_page_definitions() as $definition) {
        $option_key = (string) ($definition['option_key'] ?? '');
        $settings_url = (string) ($definition['settings_url'] ?? '');
        $capability = (string) ($definition['capability'] ?? '');
        if ($option_key === '' || $settings_url === '' || $capability === '') continue;
        if ((int) get_option($option_key) !== $post_id) continue;
        if (!current_user_can($capability)) return '';
        return $settings_url;
    }

    return '';
}

function meza_get_cookie_policy_page_id(): int
{
    return (int) get_option('meza_page_for_cookie_policy');
}

function meza_get_cookie_policy_settings_row_html(): string
{
    if (!current_user_can('manage_privacy_options')) return '';

    $has_pages = (bool) get_posts([
        'post_type' => 'page',
        'posts_per_page' => 1,
        'post_status' => ['publish', 'draft'],
    ]);
    if (!$has_pages) return '';

    $cookie_policy_page_id = meza_get_cookie_policy_page_id();
    $cookie_policy_page = $cookie_policy_page_id > 0 ? get_post($cookie_policy_page_id) : null;
    $cookie_policy_page_exists = ($cookie_policy_page instanceof WP_Post) && $cookie_policy_page->post_type === 'page' && $cookie_policy_page->post_status !== 'trash';

    ob_start();
?>
    <tr class="meza-cookie-policy-page-setting">
        <th scope="row">
            <label for="meza_page_for_cookie_policy">
                <?php echo $cookie_policy_page_exists ? esc_html__('Change your Cookie Policy page') : esc_html__('Select a Cookie Policy page'); ?>
            </label>
        </th>
        <td>
            <form method="post">
                <input type="hidden" name="action" value="set-cookie-policy-page" />
                <?php
                wp_dropdown_pages([
                    'name' => 'meza_page_for_cookie_policy',
                    'show_option_none' => __('&mdash; Select &mdash;'),
                    'option_none_value' => '0',
                    'selected' => $cookie_policy_page_id,
                    'post_status' => ['draft', 'publish'],
                ]);

                wp_nonce_field('set-cookie-policy-page');

                submit_button(__('Use This Page'), 'primary', 'submit', false, ['id' => 'set-cookie-policy-page']);
                ?>
            </form>
        </td>
    </tr>
<?php

    return trim((string) ob_get_clean());
}

add_action('load-options-privacy.php', function (): void {
    if (!current_user_can('manage_privacy_options')) return;

    $action = isset($_POST['action']) ? sanitize_key(wp_unslash((string) $_POST['action'])) : '';
    if ($action !== 'set-cookie-policy-page') return;

    check_admin_referer('set-cookie-policy-page');

    $cookie_policy_page_id = isset($_POST['meza_page_for_cookie_policy']) ? (int) $_POST['meza_page_for_cookie_policy'] : 0;
    update_option('meza_page_for_cookie_policy', $cookie_policy_page_id);

    add_settings_error(
        'meza_page_for_cookie_policy',
        'meza_page_for_cookie_policy',
        __('Cookie Policy page updated successfully.'),
        'success'
    );
});

add_action('admin_footer-options-privacy.php', function (): void {
    $row_html = meza_get_cookie_policy_settings_row_html();
    if ($row_html === '') return;
?>
    <style id="meza-privacy-policy-settings-spacing">
        .privacy-settings-body hr,
        .tools-privacy-policy-page {
            margin-top: 30px !important;
        }

        .privacy-settings-body hr {
            margin-bottom: 30px !important;
        }

        .tools-privacy-policy-page th,
        .tools-privacy-policy-page td {
            padding-top: 0;
            padding-bottom: 10px;
        }

        .tools-privacy-policy-page form {
            margin-bottom: 0;
        }

        @media screen and (max-width: 782px) {
            .tools-privacy-policy-page input#set-page,
            .tools-privacy-policy-page input#set-cookie-policy-page,
            .tools-privacy-policy-page select {
                margin: 10px 0 0;
            }

            .tools-privacy-policy-page tr.meza-cookie-policy-page-setting th {
                padding-top: 20px !important;
            }
        }
    </style>
    <script id="meza-cookie-policy-page-setting">
        document.addEventListener('DOMContentLoaded', function() {
            var table = document.querySelector('.tools-privacy-policy-page');
            if (!table) {
                return;
            }

            var createForm = table.querySelector('form.wp-create-privacy-page');
            if (createForm) {
                var createRow = createForm.closest('tr');
                if (createRow) {
                    createRow.remove();
                }
            }

            if (!table.querySelector('.meza-cookie-policy-page-setting')) {
                var tbody = table.tBodies && table.tBodies.length ? table.tBodies[0] : table.appendChild(document.createElement('tbody'));
                tbody.insertAdjacentHTML('beforeend', <?php echo wp_json_encode($row_html); ?>);
            }
        });
    </script>
<?php
});

function meza_get_page_template_filter_label(int $post_id): string
{
    return trim(meza_get_page_template_plain_label($post_id));
}

function meza_get_page_template_filter_link(int $post_id): string
{
    $label = meza_get_page_template_filter_label($post_id);
    if ($label === '' || meza_is_empty_template_display($label)) return '&mdash;';

    $url = add_query_arg(
        [
            'post_type' => 'page',
            'meza_page_template_filter' => $label,
        ],
        admin_url('edit.php')
    );

    return '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
}

function meza_get_page_template_title_link(int $post_id): string
{
    $post = get_post($post_id);
    if (!($post instanceof WP_Post)) return '';

    $post_type = trim((string) $post->post_type);
    if ($post_type === '' || !meza_post_type_has_permalink($post_type)) return '';

    if ($post_type !== 'page') {
        $post_type_object = get_post_type_object($post_type);
        $singular_label = '';
        if ($post_type_object instanceof WP_Post_Type) {
            $singular_label = trim((string) ($post_type_object->labels->singular_name ?? ''));
        }
        if ($singular_label === '') {
            $singular_label = ucwords(str_replace(['-', '_'], ' ', $post_type));
        }

        $display = meza_get_post_type_template_label_with_dashicon(
            $post_type,
            sprintf(__('%s Page Template'), $singular_label)
        );

        if ($display === '' || meza_is_empty_template_display($display)) return '';

        return '<span class="meza-title-template-text">' . $display . '</span>';
    }

    $label = meza_get_page_template_filter_label($post_id);
    if ($label === '' || meza_is_empty_template_display($label)) return '';

    $display_label = $label;
    if (!preg_match('/\btemplate$/i', $display_label)) {
        $display_label .= ' ' . __('Template');
    }

    $display = meza_get_page_type_label_with_dashicon($display_label);
    if ($display === '' || meza_is_empty_template_display($display)) return '';

    return '<span class="meza-title-template-text">' . $display . '</span>';
}

function meza_is_empty_template_display(string $value): bool
{
    $stripped = wp_strip_all_tags($value);
    $decoded = html_entity_decode($stripped, ENT_QUOTES, 'UTF-8');
    $normalized = strtolower(trim(str_replace("\xc2\xa0", ' ', $decoded)));

    if ($normalized === '') return true;
    if (in_array($normalized, ['-', '—', '–', '&mdash;', '&#8212;', '&ndash;', '&#8211;'], true)) return true;

    // Treat any dash-only placeholder sequence as empty (e.g. "-", "—", "--", "&mdash;").
    return (bool) preg_match('/^[\-\x{2012}\x{2013}\x{2014}\x{2015}\s]+$/u', $normalized);
}

function meza_page_state_store_set(int $post_id, array $labels): void
{
    $state_by_post = $GLOBALS['meza_page_state_by_post'] ?? [];
    if (!is_array($state_by_post)) $state_by_post = [];
    $state_by_post[$post_id] = $labels;
    $GLOBALS['meza_page_state_by_post'] = $state_by_post;
}

function meza_page_state_store_get(int $post_id): array
{
    $state_by_post = $GLOBALS['meza_page_state_by_post'] ?? [];
    return (is_array($state_by_post) && isset($state_by_post[$post_id]) && is_array($state_by_post[$post_id]))
        ? $state_by_post[$post_id]
        : [];
}

function meza_get_page_state_labels(int $post_id): array
{
    $labels = meza_page_state_store_get($post_id);
    if (!empty($labels)) return $labels;

    // Fallback for cases where title states were not rendered yet.
    foreach (meza_get_special_page_definitions() as $definition) {
        $option_key = (string) ($definition['option_key'] ?? '');
        $label = (string) ($definition['label'] ?? '');
        if ($option_key === '' || $label === '') continue;
        if ((int) get_option($option_key) === $post_id) $labels[] = $label;
    }

    return array_values(array_unique(array_filter($labels, function ($v) {
        return is_string($v) && trim($v) !== '';
    })));
}

function meza_is_front_page(int $post_id): bool
{
    return ((int) get_option('page_on_front') === $post_id);
}

// Remove all post-state labels from the title column on Pages admin list.
add_filter('display_post_states', function ($states, $post) {
    if (!is_admin() || ($post->post_type ?? '') !== 'page') return $states;

    $labels = [];
    foreach ($states as $label) {
        $clean = trim(wp_strip_all_tags((string) $label));
        if ($clean !== '') $labels[] = $clean;
    }
    meza_page_state_store_set((int) $post->ID, array_values(array_unique($labels)));

    return [];
}, 9999, 2);

// Core list table renderer: show an em dash for front page slug.
add_action('manage_page_posts_custom_column', function ($column, $post_id) {
    if ($column !== 'slug') return;
    if (!meza_is_front_page((int) $post_id)) return;

    echo '&mdash;';
}, 100, 2);

// Admin Columns plugin renderer: show only the selected page template label from the editor dropdown.
add_filter('ac/column/value', function ($value, $id, $column) {
    if (!is_object($column) || !method_exists($column, 'get_type') || !method_exists($column, 'get_post_type')) {
        return $value;
    }
    if ((string) $column->get_post_type() !== 'page') return $value;
    if ((string) $column->get_type() !== 'column-page_template') return $value;

    return meza_get_page_template_filter_link((int) $id);
}, 100, 3);

// Admin Columns plugin renderer: show an em dash for front page slug.
add_filter('ac/column/value', function ($value, $id, $column) {
    if (!is_object($column) || !method_exists($column, 'get_type') || !method_exists($column, 'get_post_type')) {
        return $value;
    }
    if ((string) $column->get_post_type() !== 'page') return $value;
    if ((string) $column->get_type() !== 'column-slug') return $value;
    if (!meza_is_front_page((int) $id)) return $value;

    return '&mdash;';
}, 100, 3);

add_action('pre_get_posts', function (WP_Query $q) {
    global $pagenow;
    if (!is_admin() || !$q->is_main_query() || $pagenow !== 'edit.php') return;
    if ((string) $q->get('post_type') !== 'page') return;
    if (isset($_GET['orderby']) && $_GET['orderby'] !== '') return;

    $q->set('orderby', 'modified');
    $q->set('order', 'DESC');
    $_GET['orderby'] = 'modified';
    $_REQUEST['orderby'] = 'modified';
    $_GET['order'] = 'desc';
    $_REQUEST['order'] = 'desc';
}, 15);

add_action('pre_get_posts', function (WP_Query $q) {
    global $pagenow;
    if (!is_admin() || !$q->is_main_query() || $pagenow !== 'edit.php') return;
    if ((string) $q->get('post_type') !== 'page') return;

    $orderby = (string) $q->get('orderby');
    if (!in_array($orderby, ['mz_page_template', 'column-page_template', 'page_template'], true)) return;

    $order = strtoupper((string) $q->get('order'));
    $q->set('orderby', 'mz_page_template');
    $q->set('order', in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC');
    $q->set('meza_page_template_sort', true);
}, 20);

add_action('pre_get_posts', function (WP_Query $q) {
    global $pagenow;
    if (!is_admin() || !$q->is_main_query() || $pagenow !== 'edit.php') return;
    if ((string) $q->get('post_type') !== 'page') return;

    $filter_label = isset($_GET['meza_page_template_filter'])
        ? sanitize_text_field(wp_unslash((string) $_GET['meza_page_template_filter']))
        : '';
    if ($filter_label === '') return;

    $q->set('meza_page_template_filter_label', $filter_label);
}, 20);

add_filter('posts_clauses', function (array $clauses, WP_Query $q): array {
    if (!is_admin() || !$q->is_main_query()) return $clauses;
    if ((string) $q->get('post_type') !== 'page') return $clauses;

    $do_sort = (bool) $q->get('meza_page_template_sort');
    $filter_label = trim((string) $q->get('meza_page_template_filter_label'));
    if (!$do_sort && $filter_label === '') return $clauses;

    global $wpdb;

    $order = strtoupper((string) $q->get('order'));
    $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC';

    $front_page_id = (int) get_option('page_on_front');
    $posts_page_id = (int) get_option('page_for_posts');
    $privacy_page_id = (int) get_option('wp_page_for_privacy_policy');
    $cookie_page_id = meza_get_cookie_policy_page_id();

    $page_type_meta_expr = "NULL";
    $page_type_choices = meza_get_page_type_sort_map();
    if (!empty($page_type_choices)) {
        $cases = [];
        foreach ($page_type_choices as $value => $label) {
            $cases[] = "WHEN '" . esc_sql(strtolower(trim((string) $value))) . "' THEN '" . esc_sql($label) . "'";
        }
        $page_type_meta_expr = "NULLIF(TRIM(CASE LOWER(TRIM(meza_pt_meta.meta_value)) " . implode(' ', $cases) . " ELSE '' END), '')";
    }

    $page_type_terms_expr = "NULLIF(TRIM(GROUP_CONCAT(DISTINCT meza_pt_terms.name ORDER BY meza_pt_terms.name ASC SEPARATOR ', ')), '')";
    $page_type_terms_filter_expr =
        "(SELECT NULLIF(TRIM(GROUP_CONCAT(DISTINCT meza_pt_terms_sub.name ORDER BY meza_pt_terms_sub.name ASC SEPARATOR ', ')), '') " .
        "FROM {$wpdb->term_relationships} AS meza_pt_tr_sub " .
        "LEFT JOIN {$wpdb->term_taxonomy} AS meza_pt_tt_sub ON (meza_pt_tr_sub.term_taxonomy_id = meza_pt_tt_sub.term_taxonomy_id AND meza_pt_tt_sub.taxonomy = 'page_type') " .
        "LEFT JOIN {$wpdb->terms} AS meza_pt_terms_sub ON (meza_pt_tt_sub.term_id = meza_pt_terms_sub.term_id) " .
        "WHERE meza_pt_tr_sub.object_id = {$wpdb->posts}.ID)";

    $template_cases = [
        "WHEN meza_tpl_meta.meta_value IS NULL OR meza_tpl_meta.meta_value = '' OR meza_tpl_meta.meta_value = 'default' THEN 'Basic Page'",
    ];
    foreach (meza_get_page_template_sort_map() as $file => $label) {
        $template_cases[] = "WHEN meza_tpl_meta.meta_value = '" . esc_sql($file) . "' THEN '" . esc_sql($label) . "'";
    }
    $template_label_expr = "(CASE " . implode(' ', $template_cases) . " ELSE '' END)";

    $sort_label_expr =
        "(CASE " .
        "WHEN {$wpdb->posts}.ID = {$front_page_id} THEN 'Front Page' " .
        "WHEN {$wpdb->posts}.ID = {$posts_page_id} THEN 'Posts Page' " .
        "WHEN {$wpdb->posts}.ID = {$privacy_page_id} THEN 'Privacy Policy Page' " .
        "WHEN {$wpdb->posts}.ID = {$cookie_page_id} THEN 'Cookie Policy Page' " .
        "WHEN {$page_type_meta_expr} IS NOT NULL THEN {$page_type_meta_expr} " .
        "WHEN {$page_type_terms_expr} IS NOT NULL THEN {$page_type_terms_expr} " .
        "ELSE {$template_label_expr} END)";

    $filter_label_expr =
        "(CASE " .
        "WHEN {$wpdb->posts}.ID = {$front_page_id} THEN 'Front Page' " .
        "WHEN {$wpdb->posts}.ID = {$posts_page_id} THEN 'Posts Page' " .
        "WHEN {$wpdb->posts}.ID = {$privacy_page_id} THEN 'Privacy Policy Page' " .
        "WHEN {$wpdb->posts}.ID = {$cookie_page_id} THEN 'Cookie Policy Page' " .
        "WHEN {$page_type_meta_expr} IS NOT NULL THEN {$page_type_meta_expr} " .
        "WHEN {$page_type_terms_filter_expr} IS NOT NULL THEN {$page_type_terms_filter_expr} " .
        "ELSE {$template_label_expr} END)";

    $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS meza_pt_meta ON ({$wpdb->posts}.ID = meza_pt_meta.post_id AND meza_pt_meta.meta_key = 'page_type')";
    $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS meza_tpl_meta ON ({$wpdb->posts}.ID = meza_tpl_meta.post_id AND meza_tpl_meta.meta_key = '_wp_page_template')";
    $clauses['join'] .= " LEFT JOIN {$wpdb->term_relationships} AS meza_pt_tr ON ({$wpdb->posts}.ID = meza_pt_tr.object_id)";
    $clauses['join'] .= " LEFT JOIN {$wpdb->term_taxonomy} AS meza_pt_tt ON (meza_pt_tr.term_taxonomy_id = meza_pt_tt.term_taxonomy_id AND meza_pt_tt.taxonomy = 'page_type')";
    $clauses['join'] .= " LEFT JOIN {$wpdb->terms} AS meza_pt_terms ON (meza_pt_tt.term_id = meza_pt_terms.term_id)";

    $clauses['groupby'] = "{$wpdb->posts}.ID";
    if ($filter_label !== '') {
        $clauses['where'] .= $wpdb->prepare(" AND {$filter_label_expr} = %s", $filter_label);
    }

    if ($do_sort) {
        $clauses['orderby'] = "LOWER({$sort_label_expr}) {$order}, {$wpdb->posts}.post_title ASC";
    }

    return $clauses;
}, 30, 2);

/** ================================
 *  DASHBOARD WIDGET DEFAULTS
 *  ================================ */

function meza_dashboard_collect_widgets(): array
{
    global $wp_meta_boxes;

    $collected = [];
    if (!isset($wp_meta_boxes['dashboard']) || !is_array($wp_meta_boxes['dashboard'])) return $collected;

    foreach ($wp_meta_boxes['dashboard'] as $context => $priorities) {
        if (!is_array($priorities)) continue;
        foreach ($priorities as $priority => $widgets) {
            if (!is_array($widgets)) continue;
            foreach ($widgets as $widget_id => $widget) {
                if (!is_array($widget)) continue;
                $collected[$widget_id] = [
                    'context' => (string) $context,
                    'priority' => (string) $priority,
                    'widget' => $widget,
                ];
            }
        }
    }

    return $collected;
}

function meza_dashboard_find_site_kit_widget_id(array $widgets): string
{
    $known_ids = [
        'googlesitekit_dashboard_widget',
        'googlesitekit_dashboard_key_metrics',
        'googlesitekit_dashboard_summary',
        'googlesitekit_dashboard_overview',
    ];
    foreach ($known_ids as $widget_id) {
        if (isset($widgets[$widget_id])) return $widget_id;
    }

    $best_match = '';
    foreach ($widgets as $widget_id => $data) {
        $title = strtolower(trim(wp_strip_all_tags((string) (($data['widget']['title'] ?? '')))));
        if ($title === '') continue;
        if (str_contains($title, 'site kit') && str_contains($title, 'summary')) return (string) $widget_id;
        if ($best_match === '' && str_contains($title, 'site kit')) $best_match = (string) $widget_id;
    }

    return $best_match;
}

function meza_dashboard_find_woocommerce_status_widget_id(array $widgets): string
{
    $known_ids = [
        'woocommerce_dashboard_status',
        'woocommerce_dashboard_recent_reviews',
    ];
    foreach ($known_ids as $widget_id) {
        if (isset($widgets[$widget_id])) return $widget_id;
    }

    foreach ($widgets as $widget_id => $data) {
        $title = strtolower(trim(wp_strip_all_tags((string) (($data['widget']['title'] ?? '')))));
        if ($title === '') continue;
        if (str_contains($title, 'woocommerce') && str_contains($title, 'status')) return (string) $widget_id;
    }

    return '';
}

function meza_dashboard_find_wp_mail_smtp_widget_id(array $widgets): string
{
    $known_ids = [
        'wp_mail_smtp_reports_widget_lite',
        'wp_mail_smtp_reports_widget',
        'wp_mail_smtp_dashboard_widget',
    ];
    foreach ($known_ids as $widget_id) {
        if (isset($widgets[$widget_id])) return $widget_id;
    }

    foreach ($widgets as $widget_id => $data) {
        $title = strtolower(trim(wp_strip_all_tags((string) (($data['widget']['title'] ?? '')))));
        if ($title === '') continue;
        if (str_contains($title, 'wp mail smtp')) return (string) $widget_id;
    }

    return '';
}

function meza_dashboard_find_php_error_log_widget_id(array $widgets): string
{
    $known_ids = [
        'ws_php_error_log',
        'php_error_log_dashboard',
    ];
    foreach ($known_ids as $widget_id) {
        if (isset($widgets[$widget_id])) return $widget_id;
    }

    foreach ($widgets as $widget_id => $data) {
        $title = strtolower(trim(wp_strip_all_tags((string) (($data['widget']['title'] ?? '')))));
        if ($title === '') continue;
        if (str_contains($title, 'php error log')) return (string) $widget_id;
    }

    return '';
}

function meza_dashboard_widget_with_custom_title(string $widget_id, array $widget): array
{
    $title = trim(wp_strip_all_tags((string) ($widget['title'] ?? '')));
    $normalized_title = strtolower($title);
    $normalized_id = strtolower($widget_id);

    $custom_title = '';
    if ($widget_id === 'dashboard_right_now' || $normalized_title === 'at a glance') {
        $custom_title = 'Site Overview';
    } elseif ($widget_id === 'dashboard_site_health' || str_contains($normalized_title, 'site health')) {
        $custom_title = 'Site Health';
    } elseif (
        str_contains($normalized_id, 'googlesitekit')
        || str_contains($normalized_id, 'sitekit')
        || str_contains($normalized_title, 'site kit')
    ) {
        $custom_title = 'Web Analytics';
    } elseif (str_contains($normalized_id, 'wp_mail_smtp') || str_contains($normalized_title, 'wp mail smtp')) {
        $custom_title = 'Mail';
    } elseif (str_contains($normalized_id, 'woocommerce') || str_contains($normalized_title, 'woocommerce')) {
        $custom_title = 'Sales Overview';
    } elseif (str_contains($normalized_id, 'php_error_log') || str_contains($normalized_title, 'php error log')) {
        $custom_title = 'Error Log';
    }

    if ($custom_title !== '') {
        $widget['title'] = $custom_title;
        if (!isset($widget['args']) || !is_array($widget['args'])) {
            $widget['args'] = [];
        }
        $widget['args']['__widget_basename'] = $custom_title;
    }

    return $widget;
}

function meza_dashboard_normalize_widget_titles(): void
{
    global $wp_meta_boxes;

    if (!isset($wp_meta_boxes['dashboard']) || !is_array($wp_meta_boxes['dashboard'])) return;

    foreach ($wp_meta_boxes['dashboard'] as $context => $priorities) {
        if (!is_array($priorities)) continue;
        foreach ($priorities as $priority => $widgets) {
            if (!is_array($widgets)) continue;
            foreach ($widgets as $widget_id => $widget) {
                if (!is_array($widget)) continue;
                $wp_meta_boxes['dashboard'][$context][$priority][$widget_id] = meza_dashboard_widget_with_custom_title((string) $widget_id, $widget);
            }
        }
    }
}

function meza_dashboard_allowed_widget_ids(array $widgets): array
{
    $ids = [
        'column1' => [],
        'column2' => [],
        'column3' => [],
    ];

    $site_kit_widget_id = meza_dashboard_find_site_kit_widget_id($widgets);
    if ($site_kit_widget_id !== '') $ids['column1'][] = $site_kit_widget_id;

    if (isset($widgets['dashboard_right_now'])) $ids['column2'][] = 'dashboard_right_now';

    $woocommerce_widget_id = meza_dashboard_find_woocommerce_status_widget_id($widgets);
    if ($woocommerce_widget_id !== '') $ids['column2'][] = $woocommerce_widget_id;

    if (isset($widgets['dashboard_site_health'])) $ids['column2'][] = 'dashboard_site_health';

    $php_error_log_widget_id = meza_dashboard_find_php_error_log_widget_id($widgets);
    if ($php_error_log_widget_id !== '') $ids['column3'][] = $php_error_log_widget_id;

    // Keep only widgets that actually exist for this user.
    foreach ($ids as $column => $column_ids) {
        $ids[$column] = array_values(array_filter($column_ids, function ($id) use ($widgets) {
            return isset($widgets[$id]);
        }));
    }

    return $ids;
}

// Force a 4-column dashboard layout while keeping column 4 empty.
add_filter('screen_layout_columns', function ($columns) {
    if (!is_array($columns)) return $columns;
    $columns['dashboard'] = 4;
    return $columns;
});
add_filter('get_user_option_screen_layout_dashboard', function () {
    return 4;
}, 100);

// Keep forced dashboard widgets responsive: 2 columns on medium screens, 1 on small.
add_action('admin_head-index.php', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || $screen->id !== 'dashboard') return;

    echo '<style id="meza-dashboard-responsive-columns">' .
        '#screen-options-wrap .columns-prefs{' .
        'display:none!important;' .
        '}' .
        '#dashboard-widgets .postbox-container{' .
        'width:25%!important;' .
        'float:left!important;' .
        'margin-right:0!important;' .
        'clear:none!important;' .
        '}' .
        '@media screen and (max-width:1400px){' .
        '#dashboard-widgets .postbox-container{' .
        'width:50%!important;' .
        'float:left!important;' .
        'margin-right:0!important;' .
        '}' .
        '#dashboard-widgets #postbox-container-1,' .
        '#dashboard-widgets #postbox-container-3{' .
        'clear:left;' .
        '}' .
        '#dashboard-widgets #postbox-container-2,' .
        '#dashboard-widgets #postbox-container-4{' .
        'clear:none;' .
        '}' .
        '}' .
        '@media screen and (max-width:850px){' .
        '#dashboard-widgets .postbox-container{' .
        'width:100%!important;' .
        'float:none!important;' .
        'clear:both!important;' .
        '}' .
        '}' .
        '</style>';
}, PHP_INT_MAX - 2);

// Restrict dashboard widgets and place the allowed ones in requested columns.
add_action('wp_dashboard_setup', function () {
    global $wp_meta_boxes;
    if (!is_array($wp_meta_boxes) || !isset($wp_meta_boxes['dashboard'])) return;

    $widgets = meza_dashboard_collect_widgets();
    $allowed = meza_dashboard_allowed_widget_ids($widgets);
    $allowed_ids = array_values(array_unique(array_merge($allowed['column1'], $allowed['column2'], $allowed['column3'])));

    // Remove all widgets first.
    foreach (array_keys($widgets) as $widget_id) {
        remove_meta_box((string) $widget_id, 'dashboard', 'normal');
        remove_meta_box((string) $widget_id, 'dashboard', 'side');
        remove_meta_box((string) $widget_id, 'dashboard', 'column3');
        remove_meta_box((string) $widget_id, 'dashboard', 'column4');
    }

    // Rebuild dashboard with only the allowed widgets in deterministic order.
    $wp_meta_boxes['dashboard'] = [
        'normal' => ['core' => [], 'high' => [], 'default' => [], 'low' => []],
        'side' => ['core' => [], 'high' => [], 'default' => [], 'low' => []],
        'column3' => ['core' => [], 'high' => [], 'default' => [], 'low' => []],
        'column4' => ['core' => [], 'high' => [], 'default' => [], 'low' => []],
    ];

    foreach ($allowed['column1'] as $widget_id) {
        $wp_meta_boxes['dashboard']['normal']['core'][$widget_id] = meza_dashboard_widget_with_custom_title((string) $widget_id, (array) $widgets[$widget_id]['widget']);
    }
    foreach ($allowed['column2'] as $widget_id) {
        $wp_meta_boxes['dashboard']['side']['core'][$widget_id] = meza_dashboard_widget_with_custom_title((string) $widget_id, (array) $widgets[$widget_id]['widget']);
    }
    foreach ($allowed['column3'] as $widget_id) {
        $wp_meta_boxes['dashboard']['column3']['core'][$widget_id] = meza_dashboard_widget_with_custom_title((string) $widget_id, (array) $widgets[$widget_id]['widget']);
    }

    // Keep Screen Options aligned with the enforced set.
    $hidden_ids = array_values(array_diff(array_keys($widgets), $allowed_ids));
    $GLOBALS['meza_dashboard_hidden_ids'] = $hidden_ids;
}, 1000);

add_filter('default_hidden_meta_boxes', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen) || $screen->id !== 'dashboard') return $hidden;
    $forced_hidden = $GLOBALS['meza_dashboard_hidden_ids'] ?? [];
    if (!is_array($forced_hidden)) $forced_hidden = [];
    return array_values(array_unique(array_merge((array) $hidden, $forced_hidden)));
}, 100, 2);

// Ensure Screen Options checkbox labels use the same custom widget titles.
add_action('in_admin_header', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || $screen->id !== 'dashboard') return;
    meza_dashboard_normalize_widget_titles();
}, 1);

// Form edit screen defaults: keep Slug visible in Screen Options for first-load users.
add_filter('default_hidden_meta_boxes', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen) || $screen->id !== 'form') return $hidden;
    return array_values(array_diff((array) $hidden, ['slugdiv']));
}, 200, 2);

// Post edit screen defaults: keep Slug unchecked in Screen Options except on forms.
add_filter('default_hidden_meta_boxes', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen)) return $hidden;
    if (!in_array((string) ($screen->base ?? ''), ['post', 'post-new'], true)) return $hidden;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === 'form') return $hidden;

    $hidden[] = 'slugdiv';
    return array_values(array_unique($hidden));
}, 200, 2);

// Post edit screen defaults: keep Excerpt visible in the main column for first-load users.
add_filter('default_hidden_meta_boxes', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen)) return $hidden;
    if (!in_array((string) ($screen->base ?? ''), ['post', 'post-new'], true)) return $hidden;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === '' || !post_type_supports($post_type, 'excerpt')) return $hidden;

    return array_values(array_diff((array) $hidden, ['postexcerpt']));
}, 200, 2);

// Page edit screen defaults: keep Attributes visible in Screen Options for first-load users.
add_filter('default_hidden_meta_boxes', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen)) return $hidden;
    if (!in_array((string) ($screen->base ?? ''), ['post', 'post-new'], true)) return $hidden;
    if ((string) ($screen->post_type ?? '') !== 'page') return $hidden;

    return array_values(array_diff((array) $hidden, ['pageparentdiv']));
}, 200, 2);

// Page edit screen: keep Attributes visible for all users, including saved Screen Options.
add_filter('hidden_meta_boxes', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen)) return $hidden;
    if (!in_array((string) ($screen->base ?? ''), ['post', 'post-new'], true)) return $hidden;
    if ((string) ($screen->post_type ?? '') !== 'page') return $hidden;

    return array_values(array_diff((array) $hidden, ['pageparentdiv']));
}, 200, 2);

// Page edit screen: apply the visible Attributes preference once for existing users.
add_action('current_screen', function ($screen): void {
    if (!($screen instanceof WP_Screen)) return;
    if (!in_array((string) ($screen->base ?? ''), ['post', 'post-new'], true)) return;
    if ((string) ($screen->post_type ?? '') !== 'page') return;

    $screen_id = (string) ($screen->id ?? '');
    $user_id = get_current_user_id();
    if ($screen_id === '' || $user_id <= 0) return;

    $flag_key = 'meza_page_attributes_visible_applied_' . sanitize_key($screen_id);
    if (get_user_meta($user_id, $flag_key, true)) return;

    $hidden = get_user_option("metaboxhidden_{$screen_id}", $user_id);
    if (is_array($hidden) && in_array('pageparentdiv', $hidden, true)) {
        $hidden = array_values(array_diff($hidden, ['pageparentdiv']));
        update_user_option($user_id, "metaboxhidden_{$screen_id}", $hidden, true);
    }

    update_user_meta($user_id, $flag_key, 1);
}, 210);

// Page edit screens: make sure Attributes stays visible and ordered in the side column for existing users.
add_action('current_screen', function ($screen): void {
    if (!($screen instanceof WP_Screen)) return;
    if (!in_array((string) ($screen->base ?? ''), ['post', 'post-new'], true)) return;
    if ((string) ($screen->post_type ?? '') !== 'page') return;

    $screen_id = sanitize_key((string) ($screen->id ?? ''));
    $user_id = get_current_user_id();
    if ($screen_id === '' || $user_id <= 0) return;

    $migration_key = 'meza_page_attributes_box_initialized_v2_' . $screen_id;
    if (get_user_meta($user_id, $migration_key, true)) return;

    $hidden_key = 'metaboxhidden_' . $screen_id;
    $hidden_boxes = get_user_option($hidden_key, $user_id);
    if (!is_array($hidden_boxes)) {
        $hidden_boxes = [];
    }
    $hidden_boxes = array_values(array_diff(array_map('strval', $hidden_boxes), ['pageparentdiv']));
    update_user_option($user_id, $hidden_key, $hidden_boxes, false);

    $order_key = 'meta-box-order_' . $screen_id;
    $saved_order = get_user_option($order_key, $user_id);
    $updated_order = meza_post_metabox_order_with_side_priorities($saved_order, 'page');
    update_user_option($user_id, $order_key, $updated_order, false);

    update_user_meta($user_id, $migration_key, 1);
}, 220);

// Post edit screen defaults: keep Admin Menu Editor's Content Permissions box unchecked in Screen Options.
add_filter('default_hidden_meta_boxes', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen)) return $hidden;
    if (!in_array((string) ($screen->base ?? ''), ['post', 'post-new'], true)) return $hidden;
    if (!meza_can_access_content_permissions_panel()) return $hidden;

    $hidden[] = 'ame-cpe-content-permissions';
    return array_values(array_unique($hidden));
}, 200, 2);

// Post edit screen defaults: keep Admin Menu Editor's Content Permissions box unchecked once the hidden box list is finalized.
add_filter('hidden_meta_boxes', function ($hidden, $screen, $use_defaults) {
    if (!($screen instanceof WP_Screen)) return $hidden;
    if (!in_array((string) ($screen->base ?? ''), ['post', 'post-new'], true)) return $hidden;
    if (!$use_defaults || !meza_can_access_content_permissions_panel()) return $hidden;

    $hidden[] = 'ame-cpe-content-permissions';
    return array_values(array_unique($hidden));
}, 200, 3);

// Post edit screens: apply the default-hidden Content Permissions preference once for existing admins and site managers.
add_action('current_screen', function ($screen): void {
    if (!($screen instanceof WP_Screen)) return;
    if (!in_array((string) ($screen->base ?? ''), ['post', 'post-new'], true)) return;
    if (!meza_can_access_content_permissions_panel()) return;

    $screen_id = (string) ($screen->id ?? '');
    $user_id = get_current_user_id();
    if ($screen_id === '' || $user_id <= 0) return;

    $flag_key = 'meza_ame_cpe_default_applied_v2_' . sanitize_key($screen_id);
    if (get_user_meta($user_id, $flag_key, true)) return;

    $hidden = get_user_option("metaboxhidden_{$screen_id}", $user_id);
    if (!is_array($hidden)) {
        $hidden = [];
    }
    if (!in_array('ame-cpe-content-permissions', $hidden, true)) {
        $hidden[] = 'ame-cpe-content-permissions';
        update_user_option($user_id, "metaboxhidden_{$screen_id}", array_values(array_unique($hidden)), false);
    }

    update_user_meta($user_id, $flag_key, 1);
}, 200);

// Post edit screens: remove Content Permissions entirely for users who are not administrators or site managers.
add_action('add_meta_boxes', function (string $post_type): void {
    if (meza_can_access_content_permissions_panel()) return;

    foreach (['normal', 'side', 'advanced'] as $context) {
        remove_meta_box('ame-cpe-content-permissions', $post_type, $context);
    }
}, 1000, 1);

// Post edit screen: remove the Layout section from Screen Options.
add_filter('screen_layout_columns', function ($columns, $screen_id, $screen = null) {
    if ($screen instanceof WP_Screen && in_array((string) ($screen->base ?? ''), ['post', 'post-new'], true)) {
        unset($columns[$screen_id]);
    }

    return $columns;
}, 200, 3);

// Post edit screen: remove the Additional settings section from Screen Options.
add_filter('screen_settings', function ($screen_settings, $screen) {
    if (!($screen instanceof WP_Screen)) return $screen_settings;
    if (!in_array((string) ($screen->base ?? ''), ['post', 'post-new'], true)) return $screen_settings;

    return preg_replace(
        '#<fieldset class="editor-expand hidden">.*?</fieldset>#s',
        '',
        (string) $screen_settings
    ) ?? $screen_settings;
}, 200, 2);

add_action('current_screen', function ($screen): void {
    if (!($screen instanceof WP_Screen)) return;

    $screen->remove_help_tabs();
    $screen->set_help_sidebar('');
}, 250);

// Post edit screen: hide extra Screen Options sections, remove helper copy, and alphabetize Screen Elements.
add_action('admin_head', function (): void {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen)) return;
    $screen_base = (string) ($screen->base ?? '');
    $post_type = (string) ($screen->post_type ?? '');

    echo '<style id="meza-admin-help-tab-hide">#contextual-help-link-wrap,#contextual-help-wrap{display:none!important;}</style>';

    if (in_array($screen_base, ['post', 'post-new'], true)) {
        echo '<style id="meza-post-screen-options-cleanup">#screen-options-wrap .columns-prefs,#screen-options-wrap .metabox-prefs>p{display:none!important;}</style>';
        if (!meza_can_access_content_permissions_panel()) {
            echo '<style id="meza-post-content-permissions-hide">#ame-cpe-content-permissions,#screen-options-wrap label[for="ame-cpe-content-permissions-hide"]{display:none!important;}</style>';
        }
        if ($post_type === 'post') {
            echo '<style id="meza-post-screen-elements-hide">#screen-options-wrap label[for="trackbacksdiv-hide"],#screen-options-wrap label[for="slugdiv-hide"],#screen-options-wrap label[for="commentstatusdiv-hide"],#screen-options-wrap label[for="commentsdiv-hide"],#screen-options-wrap label[for="tagsdiv-post_tag-hide"],#screen-options-wrap label[for="authordiv-hide"]{display:none!important;}</style>';
        }
        echo <<<'HTML'
<script id="meza-post-screen-options-sort">
document.addEventListener('DOMContentLoaded', function () {
    var containers = document.querySelectorAll('#screen-options-wrap .metabox-prefs-container');

    containers.forEach(function (container) {
        var labels = Array.prototype.slice.call(container.querySelectorAll(':scope > label'));
        if (labels.length < 2) {
            return;
        }

        labels
            .sort(function (a, b) {
                return a.textContent.trim().localeCompare(b.textContent.trim(), undefined, {
                    sensitivity: 'base'
                });
            })
            .forEach(function (label) {
                container.appendChild(label);
            });
    });
});
</script>
HTML;
        return;
    }

    if ($screen_base === 'term' && !meza_can_access_content_permissions_panel()) {
        echo <<<'HTML'
<script id="meza-term-content-permissions-remove">
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ame-cpe-term-box').forEach(function (panel) {
        panel.remove();
    });
});
</script>
HTML;
    }
}, 200);

// Post edit screen: keep Slug unchecked in Screen Options except on forms, even for users with saved preferences.
add_filter('hidden_meta_boxes', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen)) return $hidden;
    if (!in_array((string) ($screen->base ?? ''), ['post', 'post-new'], true)) return $hidden;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === 'form') return $hidden;

    $hidden[] = 'slugdiv';
    return array_values(array_unique($hidden));
}, 200, 2);

// Form edit screen: keep Slug visible for all users (including users with saved Screen Options).
add_filter('hidden_meta_boxes', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen) || $screen->id !== 'form') return $hidden;
    return array_values(array_diff((array) $hidden, ['slugdiv']));
}, 200, 2);

// Form edit screen: render core Slug in ACF's sortable "After Title" area.
add_action('add_meta_boxes_form', function ($post) {
    $panel_labels = meza_get_post_edit_panel_admin_label_map('form');

    remove_meta_box('slugdiv', 'form', 'normal');
    add_meta_box(
        'slugdiv',
        $panel_labels['slugdiv'] ?? __('Slug'),
        'post_slug_meta_box',
        'form',
        'acf_after_title',
        'high',
        ['__back_compat_meta_box' => true]
    );
}, 1000, 1);

// Post edit screens: place the core Excerpt box after the editor, or after the title when no editor exists.
add_action('add_meta_boxes', function (string $post_type, $post): void {
    if (!($post instanceof WP_Post)) return;
    if (!post_type_supports($post_type, 'excerpt')) return;

    $panel_labels = meza_get_post_edit_panel_admin_label_map($post_type);

    remove_meta_box('postexcerpt', $post_type, 'side');
    remove_meta_box('postexcerpt', $post_type, 'normal');
    remove_meta_box('postexcerpt', $post_type, 'acf_after_title');

    $preferred_context = post_type_supports($post_type, 'editor') ? 'meza_after_editor' : 'acf_after_title';

    add_meta_box(
        'postexcerpt',
        $panel_labels['postexcerpt'] ?? __('Excerpt'),
        'meza_post_summary_meta_box',
        $post_type,
        $preferred_context,
        'high',
        ['__back_compat_meta_box' => true]
    );
}, 1000, 2);

// Classic editor side column: for non-pages keep Featured Image ahead of Attributes.
add_action('current_screen', function ($screen): void {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'post') return;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === '') return;

    $panel_labels = meza_get_post_edit_panel_admin_label_map($post_type);
    $post_type_object = get_post_type_object($post_type);
    if (!($post_type_object instanceof WP_Post_Type)) return;

    if (isset($panel_labels['postimagediv'])) {
        $image_panel_label = (string) $panel_labels['postimagediv'];
        $image_panel_label_lower = strtolower($image_panel_label);
        $post_type_object->labels->featured_image = $image_panel_label;
        $post_type_object->labels->set_featured_image = sprintf(
            __('Set %s'),
            $image_panel_label_lower
        );
        $post_type_object->labels->remove_featured_image = sprintf(
            __('Remove %s'),
            $image_panel_label_lower
        );
        $post_type_object->labels->use_featured_image = sprintf(
            __('Use as %s'),
            $image_panel_label_lower
        );
    }
    if (!isset($post_type_object->labels->attributes)) return;

    $post_type_object->labels->attributes = __('Attributes');
}, 1000);

add_filter('admin_post_thumbnail_html', function ($content, $post_id): string {
    $post = get_post((int) $post_id);
    if (!($post instanceof WP_Post)) return (string) $content;

    $panel_labels = meza_get_post_edit_panel_admin_label_map((string) $post->post_type);
    $image_panel_label = trim((string) ($panel_labels['postimagediv'] ?? ''));
    if ($image_panel_label === '') return (string) $content;

    $panel_label_lower = strtolower($image_panel_label);
    $replacements = [
        __('Click the image to edit or update') => sprintf(__('Click the %s to edit or update'), $panel_label_lower),
        __('Set featured image') => sprintf(__('Set %s'), $panel_label_lower),
        __('Remove featured image') => sprintf(__('Remove %s'), $panel_label_lower),
    ];

    return str_replace(
        array_keys($replacements),
        array_values($replacements),
        (string) $content
    );
}, 1000, 2);

add_action('add_meta_boxes_page', function ($post): void {
    if (!($post instanceof WP_Post)) return;

    global $wp_meta_boxes;

    foreach (['high', 'core', 'default', 'low'] as $priority) {
        if (!isset($wp_meta_boxes['page']['side'][$priority]['pageparentdiv']) || !is_array($wp_meta_boxes['page']['side'][$priority]['pageparentdiv'])) {
            continue;
        }

        $wp_meta_boxes['page']['side'][$priority]['pageparentdiv']['title'] = __('Attributes');
        $wp_meta_boxes['page']['side'][$priority]['pageparentdiv']['callback'] = 'meza_page_attributes_meta_box';

        $existing_args = $wp_meta_boxes['page']['side'][$priority]['pageparentdiv']['args'] ?? [];
        if (!is_array($existing_args)) {
            $existing_args = [];
        }
        $existing_args['__back_compat_meta_box'] = true;
        $wp_meta_boxes['page']['side'][$priority]['pageparentdiv']['args'] = $existing_args;
        return;
    }

    add_meta_box(
        'pageparentdiv',
        __('Attributes'),
        'meza_page_attributes_meta_box',
        'page',
        'side',
        'default',
        ['__back_compat_meta_box' => true]
    );
}, 1001, 1);

function meza_post_summary_meta_box($post): void
{
    if (!($post instanceof WP_Post)) return;
    $panel_labels = meza_get_post_edit_panel_admin_label_map((string) $post->post_type);
    $label = $panel_labels['postexcerpt'] ?? __('Excerpt');
?>
    <label class="screen-reader-text" for="excerpt"><?php echo esc_html($label); ?></label>
    <textarea rows="1" cols="40" name="excerpt" id="excerpt"><?php echo $post->post_excerpt; // textarea_escaped 
                                                                ?></textarea>
    <?php
}

function meza_page_attributes_meta_box($post): void
{
    if (!($post instanceof WP_Post)) return;

    if (post_type_supports($post->post_type, 'page-attributes') && is_post_type_hierarchical($post->post_type)) {
        $dropdown_args = [
            'post_type'        => $post->post_type,
            'exclude_tree'     => $post->ID,
            'selected'         => $post->post_parent,
            'name'             => 'parent_id',
            'show_option_none' => __('(no parent)'),
            'sort_column'      => 'menu_order, post_title',
            'echo'             => 0,
        ];

        $dropdown_args = apply_filters('page_attributes_dropdown_pages_args', $dropdown_args, $post);
        $pages = wp_dropdown_pages($dropdown_args);
        if (!empty($pages)) :
    ?>
            <p class="post-attributes-label-wrapper parent-id-label-wrapper"><label class="post-attributes-label" for="parent_id"><?php _e('Parent'); ?></label></p>
            <?php echo $pages; ?>
        <?php
        endif;
    }

    $special_page_label = ($post->post_type === 'page') ? meza_get_special_page_label((int) $post->ID) : '';
    $has_templates = count(get_page_templates($post)) > 0;

    if ($special_page_label !== '' || ($has_templates && (int) get_option('page_for_posts') !== $post->ID)) :
        $template = !empty($post->page_template) ? $post->page_template : 'default';
        $special_page_update_link = ($special_page_label !== '') ? meza_get_special_page_update_link((int) $post->ID) : '';
        ?>
        <p class="post-attributes-label-wrapper page-template-label-wrapper"><label class="post-attributes-label" for="page_template"><?php _e('Template'); ?></label>
            <?php do_action('page_attributes_meta_box_template', $template, $post); ?>
        </p>
        <?php if ($special_page_label !== '') : ?>
            <div class="post-attributes-template-static" style="display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap;">
                <?php echo meza_get_page_type_label_with_dashicon($special_page_label); ?>
                <?php if ($special_page_update_link !== '') : ?>
                    <span class="post-attributes-template-static-action" style="display:inline-flex;align-items:center;"><a href="<?php echo esc_url($special_page_update_link); ?>"><?php _e('Update'); ?></a></span>
                <?php endif; ?>
            </div>
            <input type="hidden" name="page_template" id="page_template" value="<?php echo esc_attr($template); ?>" />
        <?php else : ?>
            <select name="page_template" id="page_template">
                <?php
                $default_title = apply_filters('default_page_template_title', __('Default template'), 'meta-box');
                ?>
                <option value="default"><?php echo esc_html($default_title); ?></option>
                <?php page_template_dropdown($template, $post->post_type); ?>
            </select>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (post_type_supports($post->post_type, 'page-attributes')) : ?>
        <p class="post-attributes-label-wrapper menu-order-label-wrapper"><label class="post-attributes-label" for="menu_order"><?php _e('Order'); ?></label></p>
        <input name="menu_order" type="text" size="4" id="menu_order" value="<?php echo esc_attr($post->menu_order); ?>" />
        <?php do_action('page_attributes_misc_attributes', $post); ?>
    <?php endif;
}

function meza_sort_metabox_ids_with_priority(array $box_ids, array $priority_ids): array
{
    $normalized_ids = array_map('sanitize_key', $box_ids);
    $normalized_ids = array_values(array_filter($normalized_ids, static function ($id) {
        return $id !== '';
    }));

    $priority_ids = array_map('sanitize_key', $priority_ids);
    $priority_ids = array_values(array_filter($priority_ids, static function ($id) {
        return $id !== '';
    }));

    return array_values(array_unique(array_merge($priority_ids, $normalized_ids)));
}

function meza_get_side_metabox_priority_ids(string $post_type): array
{
    $post_type = sanitize_key($post_type);

    if ($post_type === 'page') {
        return ['submitdiv', 'pageparentdiv', 'postimagediv'];
    }

    return ['submitdiv', 'postimagediv', 'pageparentdiv'];
}

function meza_normalize_metabox_order_contexts($order_value): array
{
    if (is_array($order_value)) {
        return $order_value;
    }

    $contexts = [];
    if (is_string($order_value) && $order_value !== '') {
        parse_str($order_value, $contexts);
    }

    return is_array($contexts) ? $contexts : [];
}

function meza_post_metabox_order_with_side_priorities($order_value, string $post_type): array
{
    $contexts = meza_normalize_metabox_order_contexts($order_value);

    $side_items = [];
    if (isset($contexts['side']) && $contexts['side'] !== '') {
        $side_items = array_map('sanitize_key', explode(',', (string) $contexts['side']));
        $side_items = array_values(array_filter($side_items, static function ($id) {
            return $id !== '';
        }));
    }

    $side_items = meza_sort_metabox_ids_with_priority($side_items, meza_get_side_metabox_priority_ids($post_type));

    if (!empty($side_items)) {
        $contexts['side'] = implode(',', $side_items);
    }

    $pairs = [];
    foreach ($contexts as $context_key => $boxes) {
        $box_ids = array_map('sanitize_key', explode(',', (string) $boxes));
        $box_ids = array_values(array_unique(array_filter($box_ids, static function ($id) {
            return $id !== '';
        })));
        if (empty($box_ids)) continue;

        $pairs[sanitize_key((string) $context_key)] = implode(',', $box_ids);
    }

    return $pairs;
}

function meza_post_metabox_order_with_excerpt_in_context($order_value, string $preferred_context, string $post_type): array
{
    $contexts = meza_normalize_metabox_order_contexts($order_value);

    foreach ($contexts as $context_key => $boxes) {
        $box_ids = array_map('sanitize_key', explode(',', (string) $boxes));
        $box_ids = array_values(array_filter($box_ids, static function ($id) {
            return $id !== '' && $id !== 'postexcerpt';
        }));
        $contexts[$context_key] = implode(',', $box_ids);
    }

    $preferred_context = sanitize_key($preferred_context);
    if ($preferred_context !== '') {
        $preferred_items = [];
        if (isset($contexts[$preferred_context]) && $contexts[$preferred_context] !== '') {
            $preferred_items = array_map('sanitize_key', explode(',', (string) $contexts[$preferred_context]));
            $preferred_items = array_values(array_filter($preferred_items, static function ($id) {
                return $id !== '';
            }));
        }

        array_unshift($preferred_items, 'postexcerpt');
        $contexts[$preferred_context] = implode(',', array_values(array_unique($preferred_items)));
    }

    $side_items = [];
    if (isset($contexts['side']) && $contexts['side'] !== '') {
        $side_items = array_map('sanitize_key', explode(',', (string) $contexts['side']));
        $side_items = array_values(array_filter($side_items, static function ($id) {
            return $id !== '';
        }));
    }

    $side_items = meza_sort_metabox_ids_with_priority($side_items, meza_get_side_metabox_priority_ids($post_type));

    if (!empty($side_items)) {
        $contexts['side'] = implode(',', $side_items);
    }

    $pairs = [];
    foreach ($contexts as $context_key => $boxes) {
        $box_ids = array_map('sanitize_key', explode(',', (string) $boxes));
        $box_ids = array_values(array_unique(array_filter($box_ids, static function ($id) {
            return $id !== '';
        })));
        if (empty($box_ids)) continue;

        $pairs[sanitize_key((string) $context_key)] = implode(',', $box_ids);
    }

    return $pairs;
}

// Post edit screens: set the initial Excerpt placement once per user, then let users move it freely.
add_action('current_screen', function ($screen): void {
    if (!($screen instanceof WP_Screen)) return;
    if (!in_array((string) ($screen->base ?? ''), ['post', 'post-new'], true)) return;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === '') return;

    $user_id = get_current_user_id();
    if ($user_id <= 0) return;

    $screen_id = sanitize_key((string) $screen->id);
    if ($screen_id === '') return;

    $has_excerpt_support = post_type_supports($post_type, 'excerpt');
    $preferred_context = $has_excerpt_support
        ? (post_type_supports($post_type, 'editor') ? 'meza_after_editor' : 'acf_after_title')
        : '';
    $migration_key = 'meza_excerpt_box_initialized_v11_' . $screen_id;
    $should_bootstrap = !get_user_meta($user_id, $migration_key, true);

    add_filter('default_user_option_meta-box-order_' . $screen_id, function ($default_order) use ($should_bootstrap, $preferred_context, $post_type) {
        if (!$should_bootstrap) return $default_order;
        return meza_post_metabox_order_with_excerpt_in_context($default_order, $preferred_context, $post_type);
    }, 10, 1);

    add_filter('get_user_option_meta-box-order_' . $screen_id, function ($saved_order) use ($should_bootstrap, $preferred_context, $post_type) {
        if (!$should_bootstrap) return $saved_order;
        return meza_post_metabox_order_with_excerpt_in_context($saved_order, $preferred_context, $post_type);
    }, 10, 1);

    if (!$should_bootstrap) return;

    $order_key = 'meta-box-order_' . $screen_id;
    $saved_order = get_user_option($order_key, $user_id);
    $updated_order = meza_post_metabox_order_with_excerpt_in_context($saved_order, $preferred_context, $post_type);
    update_user_option($user_id, $order_key, $updated_order, false);

    if ($has_excerpt_support) {
        $hidden_key = 'metaboxhidden_' . $screen_id;
        $hidden_boxes = get_user_option($hidden_key, $user_id);
        if (!is_array($hidden_boxes)) {
            $hidden_boxes = [];
        }
        $hidden_boxes = array_values(array_diff($hidden_boxes, ['postexcerpt']));
        update_user_option($user_id, $hidden_key, $hidden_boxes, false);
    }

    update_user_meta($user_id, $migration_key, 1);
}, 200);

add_action('edit_form_after_editor', function ($post): void {
    if (!($post instanceof WP_Post)) return;

    $post_type = (string) ($post->post_type ?? '');
    if ($post_type === '' || !post_type_supports($post_type, 'excerpt')) return;
    if (!post_type_supports($post_type, 'editor')) return;

    echo '<style>#post-body-content{margin-bottom:0;}#meza_after_editor-sortables{margin-top:16px;}#meza_after_editor-sortables #postexcerpt{margin-top:0;}</style>';
    do_meta_boxes(get_current_screen(), 'meza_after_editor', $post);
}, 20);

function meza_form_metabox_order_with_slug_first($order_value): string
{
    $order = is_string($order_value) ? $order_value : '';
    $contexts = [];
    if ($order !== '') {
        parse_str($order, $contexts);
    }

    $normal_items = [];
    if (isset($contexts['normal'])) {
        $normal_items = array_map('sanitize_key', explode(',', (string) $contexts['normal']));
        $normal_items = array_values(array_filter($normal_items, static function ($id) {
            return $id !== '';
        }));
    }

    $after_title_items = [];
    if (isset($contexts['acf_after_title'])) {
        $after_title_items = array_map('sanitize_key', explode(',', (string) $contexts['acf_after_title']));
        $after_title_items = array_values(array_filter($after_title_items, static function ($id) {
            return $id !== '';
        }));
    }

    $normal_items = array_values(array_diff($normal_items, ['slugdiv']));
    $after_title_items = array_values(array_diff($after_title_items, ['slugdiv']));
    array_unshift($after_title_items, 'slugdiv');
    $contexts['normal'] = implode(',', array_values(array_unique($normal_items)));
    $contexts['acf_after_title'] = implode(',', array_values(array_unique($after_title_items)));

    // Keep Publish in the side column when no order has been set.
    if (!isset($contexts['side']) || trim((string) $contexts['side']) === '') {
        $contexts['side'] = 'submitdiv';
    }

    $pairs = [];
    foreach ($contexts as $context => $boxes) {
        $context_key = sanitize_key((string) $context);
        if ($context_key === '') continue;

        $box_ids = array_map('sanitize_key', explode(',', (string) $boxes));
        $box_ids = array_values(array_unique(array_filter($box_ids, static function ($id) {
            return $id !== '';
        })));
        if (empty($box_ids)) continue;

        $pairs[] = $context_key . '=' . implode(',', $box_ids);
    }

    return implode('&', $pairs);
}

// Form edit screen defaults: place Slug directly under Title (before ACF field groups).
add_filter('default_user_option_meta-box-order_form', function ($default_order) {
    return meza_form_metabox_order_with_slug_first($default_order);
}, 10, 1);

// Form edit screen: enforce Slug position under Title for users with existing saved meta box order.
add_filter('get_user_option_meta-box-order_form', function ($saved_order) {
    return meza_form_metabox_order_with_slug_first($saved_order);
}, 10, 1);

// Remove the Dashboard welcome panel for all users.
add_action('admin_init', function () {
    remove_action('welcome_panel', 'wp_welcome_panel');
});

function meza_get_allowed_tag_post_types(): array
{
    $post_types = apply_filters('meza_allowed_tag_post_types', ['product']);

    return array_values(array_unique(array_filter(array_map('sanitize_key', (array) $post_types))));
}

function meza_get_exempt_tag_taxonomies(): array
{
    $taxonomies = apply_filters('meza_exempt_tag_taxonomies', ['nav_menu', 'post_format']);

    return array_values(array_unique(array_filter(array_map('sanitize_key', (array) $taxonomies))));
}

function meza_divi_projects_enabled(): bool
{
    return (bool) apply_filters('meza_enable_divi_projects', false);
}

add_filter('et_project_posttype_args', function ($args) {
    if (meza_divi_projects_enabled() || !is_array($args)) {
        return $args;
    }

    $args['public'] = false;
    $args['publicly_queryable'] = false;
    $args['show_ui'] = false;
    $args['show_in_menu'] = false;
    $args['show_in_admin_bar'] = false;
    $args['show_in_nav_menus'] = false;
    $args['show_in_rest'] = false;
    $args['has_archive'] = false;
    $args['rewrite'] = false;
    $args['query_var'] = false;
    $args['exclude_from_search'] = true;

    return $args;
}, 999);

add_filter('register_taxonomy_args', function ($args, $taxonomy) {
    if (meza_divi_projects_enabled() || !is_array($args)) {
        return $args;
    }

    if (!in_array((string) $taxonomy, ['project_category', 'project_tag'], true)) {
        return $args;
    }

    $args['public'] = false;
    $args['show_ui'] = false;
    $args['show_admin_column'] = false;
    $args['show_in_rest'] = false;
    $args['show_in_nav_menus'] = false;
    $args['query_var'] = false;
    $args['rewrite'] = false;

    return $args;
}, 999, 2);

function meza_should_disable_tag_taxonomy_registration(string $taxonomy, array $args, $object_type): bool
{
    $taxonomy = sanitize_key($taxonomy);
    if ($taxonomy === '' || in_array($taxonomy, meza_get_exempt_tag_taxonomies(), true)) {
        return false;
    }

    if (!empty($args['hierarchical'])) {
        return false;
    }

    $object_types = array_values(array_unique(array_filter(array_map('sanitize_key', (array) $object_type))));
    if (empty($object_types)) {
        $object_types = array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($args['object_type'] ?? [])))));
    }

    if (empty($object_types)) {
        return false;
    }

    return empty(array_intersect($object_types, meza_get_allowed_tag_post_types()));
}

function meza_is_disabled_tag_taxonomy(string $taxonomy): bool
{
    $taxonomy = sanitize_key($taxonomy);
    if ($taxonomy === '' || !taxonomy_exists($taxonomy) || in_array($taxonomy, meza_get_exempt_tag_taxonomies(), true)) {
        return false;
    }

    $taxonomy_object = get_taxonomy($taxonomy);
    if (!($taxonomy_object instanceof WP_Taxonomy) || !empty($taxonomy_object->hierarchical)) {
        return false;
    }

    return empty(array_intersect((array) $taxonomy_object->object_type, meza_get_allowed_tag_post_types()));
}

function meza_disable_comments_for_post_type(string $post_type): void
{
    $post_type = sanitize_key($post_type);
    if ($post_type === '' || !post_type_exists($post_type)) {
        return;
    }

    remove_post_type_support($post_type, 'comments');
    remove_post_type_support($post_type, 'trackbacks');
}

function meza_get_design_redirect_url(): string
{
    return current_user_can('edit_theme_options') ? admin_url('nav-menus.php') : admin_url();
}

add_filter('register_taxonomy_args', function ($args, $taxonomy, $object_type) {
    if (!is_array($args)) {
        return $args;
    }

    if (!meza_should_disable_tag_taxonomy_registration((string) $taxonomy, $args, $object_type)) {
        return $args;
    }

    $args['show_ui'] = false;
    $args['show_admin_column'] = false;
    $args['show_in_nav_menus'] = false;
    $args['show_tagcloud'] = false;
    $args['show_in_quick_edit'] = false;
    $args['meta_box_cb'] = false;

    return $args;
}, 1000, 3);

add_action('registered_post_type', function ($post_type): void {
    meza_disable_comments_for_post_type((string) $post_type);
}, 1000, 1);

add_action('init', function (): void {
    foreach (get_post_types([], 'names') as $post_type) {
        meza_disable_comments_for_post_type((string) $post_type);
    }
}, 1000);

add_filter('comments_open', '__return_false', 20, 2);
add_filter('pings_open', '__return_false', 20, 2);
add_filter('comments_array', function ($comments) {
    return [];
}, 20, 2);

// Hide selected admin menu items that we do not expose to editors/admins.
add_action('admin_menu', function () {
    remove_menu_page('edit-comments.php');
    remove_menu_page('godaddy-get-help');
    remove_menu_page('admin.php?page=godaddy-get-help');

    remove_submenu_page('edit.php', 'edit-tags.php?taxonomy=post_tag');
    remove_submenu_page('options-general.php', 'options-discussion.php');

    remove_submenu_page('themes.php', 'customize.php');
    remove_submenu_page('themes.php', 'widgets.php');
    remove_submenu_page('themes.php', 'theme-editor.php');
    remove_submenu_page('themes.php', 'site-editor.php');
    remove_submenu_page('themes.php', 'site-editor.php?path=/patterns');
    remove_submenu_page('themes.php', 'edit.php?post_type=wp_block');

    remove_submenu_page('plugins.php', 'plugin-editor.php');

    if (!meza_site_has_subscribers()) {
        remove_submenu_page('tools.php', 'export-personal-data.php');
        remove_submenu_page('tools.php', 'erase-personal-data.php');
    }
}, 999);

add_action('admin_menu', function () {
    global $submenu;

    if (!is_array($submenu)) {
        return;
    }

    foreach ($submenu as &$items) {
        if (!is_array($items)) {
            continue;
        }

        $items = array_values(array_filter($items, static function ($item): bool {
            if (!is_array($item)) {
                return false;
            }

            $slug = (string) ($item[2] ?? '');
            if (!str_starts_with($slug, 'edit-tags.php?taxonomy=')) {
                return true;
            }

            parse_str((string) parse_url($slug, PHP_URL_QUERY), $query_args);
            return !meza_is_disabled_tag_taxonomy((string) ($query_args['taxonomy'] ?? ''));
        }));
    }
    unset($items);
}, PHP_INT_MAX - 5);

add_action('admin_init', function (): void {
    if (meza_site_has_subscribers()) return;
    if (!is_admin()) return;

    global $pagenow;

    if (!in_array($pagenow, ['export-personal-data.php', 'erase-personal-data.php'], true)) {
        return;
    }

    wp_die(__('Sorry, you are not allowed to access this page.'));
});

add_action('admin_init', function (): void {
    if (!is_admin()) {
        return;
    }

    global $pagenow;

    $pagenow = is_string($pagenow ?? null) ? $pagenow : '';
    $taxonomy = isset($_GET['taxonomy']) ? sanitize_key(wp_unslash((string) $_GET['taxonomy'])) : '';
    $post_type = isset($_GET['post_type']) ? sanitize_key(wp_unslash((string) $_GET['post_type'])) : '';

    if (in_array($pagenow, ['edit-comments.php', 'comment.php', 'options-discussion.php'], true)) {
        wp_safe_redirect(admin_url());
        exit;
    }

    if (in_array($pagenow, ['edit-tags.php', 'term.php'], true) && meza_is_disabled_tag_taxonomy($taxonomy)) {
        wp_safe_redirect(admin_url());
        exit;
    }

    $is_design_page = in_array($pagenow, ['customize.php', 'widgets.php', 'theme-editor.php', 'site-editor.php'], true)
        || (($pagenow === 'edit.php' || $pagenow === 'post-new.php' || $pagenow === 'post.php') && $post_type === 'wp_block');

    if ($is_design_page) {
        wp_safe_redirect(meza_get_design_redirect_url());
        exit;
    }
}, 2);

// Remove "Get Help" top-level menu item when present.
add_action('admin_menu', function () {
    global $menu;
    if (!is_array($menu) || empty($menu)) return;

    foreach ($menu as $index => $item) {
        if (!is_array($item)) continue;

        $slug = strtolower((string) ($item[2] ?? ''));
        $label = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
        $is_get_help = ($label === 'get help');
        $is_godaddy_get_help = $slug === 'godaddy-get-help' || str_contains($slug, 'page=godaddy-get-help');
        $is_godaddy_help = str_contains($slug, 'gd-system-help') || (str_contains($slug, 'godaddy') && str_contains($slug, 'help'));

        if ($is_get_help || $is_godaddy_get_help || $is_godaddy_help) {
            unset($menu[$index]);
        }
    }

    $menu = array_values($menu);
}, PHP_INT_MAX - 3);

// Remove late-registered Appearance submenu items by matching the final submenu array.
add_action('admin_menu', function () {
    global $submenu;

    if (!isset($submenu['themes.php']) || !is_array($submenu['themes.php'])) return;

    $submenu['themes.php'] = array_values(array_filter($submenu['themes.php'], function ($item) {
        if (!is_array($item)) return true;

        $label = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
        $slug = strtolower((string) ($item[2] ?? ''));

        $is_themes = $slug === 'themes.php' || $label === 'themes';
        $is_customize = ($slug === 'customize.php')
            || str_starts_with($slug, 'customize.php?')
            || $label === 'customize';
        $is_widgets = $slug === 'widgets.php' || $label === 'widgets';
        $is_site_editor = str_starts_with($slug, 'site-editor.php') || $label === 'editor';
        $is_theme_editor = $slug === 'theme-editor.php' || $label === 'theme file editor';
        $is_patterns = ($slug === 'edit.php?post_type=wp_block')
            || (str_starts_with($slug, 'site-editor.php') && str_contains($slug, 'patterns'))
            || $label === 'patterns';

        return !($is_customize || $is_widgets || $is_site_editor || $is_theme_editor || $is_patterns);
    }));
}, 99999);

function meza_admin_menu_content_group(string $menu_slug): string
{
    $menu_slug = trim($menu_slug);
    if ($menu_slug === '') return '';

    if (function_exists('acf_get_options_page') && acf_get_options_page($menu_slug)) {
        return 'without';
    }

    if ($menu_slug === 'link-manager.php') {
        return 'without';
    }

    if (
        str_contains($menu_slug, 'gf_edit_forms')
        || str_contains($menu_slug, 'gravityforms')
    ) {
        return 'without';
    }

    if ($menu_slug === 'edit.php') {
        return 'with';
    }

    if (!str_starts_with($menu_slug, 'edit.php?post_type=')) return '';

    $post_type = (string) wp_unslash((string) parse_url($menu_slug, PHP_URL_QUERY));
    parse_str($post_type, $query_args);
    $post_type = (string) ($query_args['post_type'] ?? '');
    if ($post_type === '') return '';
    if ($post_type === 'product') return 'with';
    if (function_exists('meza_is_acf_admin_post_type') && meza_is_acf_admin_post_type($post_type)) return '';

    $post_type_object = get_post_type_object($post_type);
    if (!($post_type_object instanceof WP_Post_Type)) return '';
    if (empty($post_type_object->show_ui)) return '';

    return meza_post_type_has_permalink($post_type) ? 'with' : 'without';
}

function meza_submenu_label_contains_banned_words(string $label): bool
{
    $label = strtolower(trim(wp_strip_all_tags($label)));
    if ($label === '') return false;

    return preg_match('/\b(addons?|add-ons?|help|about|support|pro|guides?|widgets?|university|education|training|integrations?|troubleshoot(?:ing)?|shortcodes?)\b/i', $label) === 1;
}

function meza_submenu_label_uses_custom_markup(string $label): bool
{
    $raw_label = trim($label);
    if ($raw_label === '') return false;

    $raw_label_lower = strtolower($raw_label);

    return preg_match('/<[^>]+>/', $raw_label) === 1
        || str_contains($raw_label_lower, 'class=')
        || str_contains($raw_label_lower, 'style=');
}

function meza_is_promotional_submenu_item(string $parent_slug, array $item): bool
{
    $parent_slug = strtolower($parent_slug);
    $slug = strtolower((string) ($item[2] ?? ''));
    $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));

    if ($title === '') return false;

    $has_promotional_slug = str_contains($slug, 'upsell')
        || str_contains($slug, 'cross-sell')
        || str_contains($slug, 'cross_sell');

    $has_promotional_title = str_starts_with($title, 'get ')
        || str_contains($title, ' free plugins')
        || str_contains($title, ' more plugins')
        || str_contains($title, 'upgrade');

    if ($has_promotional_slug || $has_promotional_title) {
        return true;
    }

    if ($parent_slug === 'smush') {
        return in_array($title, ['get more free plugins', 'get smush pro'], true);
    }

    return false;
}

function meza_is_resource_submenu_item(string $parent_slug, array $item): bool
{
    $parent_slug = strtolower($parent_slug);
    $slug = strtolower((string) ($item[2] ?? ''));
    $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));

    if ($slug === '' && $title === '') return false;

    $resource_slug_patterns = [
        'tribe-app-shop',
        'tec-events-help-hub',
        'tec-troubleshooting',
        'first-time-setup',
        'setup-guide',
        'shortcode',
    ];

    foreach ($resource_slug_patterns as $pattern) {
        if (str_contains($slug, $pattern)) {
            return true;
        }
    }

    if (
        $parent_slug === 'edit.php?post_type=tribe_events'
        && preg_match('/\b(setup|guide|widget|university|education|training|integration|troubleshoot|shortcode)\b/i', $title) === 1
    ) {
        return true;
    }

    return false;
}

function meza_is_default_wordpress_submenu_item(string $parent_slug, array $item): bool
{
    $parent_slug = strtolower($parent_slug);
    $slug = strtolower((string) ($item[2] ?? ''));

    $core_submenus = [
        'index.php' => ['index.php', 'update-core.php', 'site-health.php'],
        'edit.php' => ['edit.php', 'post-new.php'],
        'upload.php' => ['upload.php', 'media-new.php'],
        'edit.php?post_type=page' => ['edit.php?post_type=page', 'post-new.php?post_type=page'],
        'plugins.php' => ['plugins.php', 'plugin-install.php'],
        'themes.php' => ['themes.php', 'widgets.php', 'nav-menus.php'],
        'users.php' => ['users.php', 'user-new.php', 'profile.php'],
        'tools.php' => ['tools.php', 'import.php', 'export.php', 'site-health.php'],
        'options-general.php' => [
            'options-general.php',
            'options-writing.php',
            'options-reading.php',
            'options-discussion.php',
            'options-media.php',
            'options-permalink.php',
            'privacy.php',
        ],
    ];

    return isset($core_submenus[$parent_slug]) && in_array($slug, $core_submenus[$parent_slug], true);
}

function meza_get_standardized_submenu_utility_label(array $item, string $parent_slug = ''): string
{
    $parent_slug = strtolower($parent_slug);
    $slug = strtolower((string) ($item[2] ?? ''));
    $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));

    if ($slug === '' && $title === '') {
        return '';
    }

    if ($parent_slug === 'options-general.php') {
        return '';
    }

    $has_import = preg_match('/\bimport\b/i', $title) === 1 || str_contains($slug, 'import');
    $has_export = preg_match('/\bexport\b/i', $title) === 1 || str_contains($slug, 'export');
    $has_tools = preg_match('/\btools?\b/i', $title) === 1
        || preg_match('/(^|[_-])tools?([_-]|$)/i', $slug) === 1;
    $has_two_factor = preg_match('/\b(two[\s-]*factor|2fa)\b/i', $title) === 1
        || preg_match('/(^|[_-])(two[_-]*factor|2fa)([_-]|$)/i', $slug) === 1;
    $has_settings = preg_match('/\bsettings?\b/i', $title) === 1
        || preg_match('/(^|[_-])settings?([_-]|$)/i', $slug) === 1;
    $has_status = preg_match('/\bstatus\b/i', $title) === 1 || str_contains($slug, 'status');

    if ($has_import && $has_export) {
        return 'Import/Export';
    }

    if ($has_import) {
        return 'Import';
    }

    if ($has_export) {
        return 'Export';
    }

    if ($has_tools) {
        return 'Tools';
    }

    if ($has_two_factor) {
        return 'Two Factor Authentication';
    }

    if ($has_settings) {
        return 'Settings';
    }

    if ($has_status) {
        return 'Status';
    }

    return '';
}

function meza_reorder_standardized_submenu_utility_items(array $items): array
{
    $order_map = [
        'Import' => 10,
        'Export' => 20,
        'Import/Export' => 30,
        'Tools' => 40,
        'Two Factor Authentication' => 50,
        'Settings' => 60,
        'Status' => 70,
    ];

    $tail_items = [];
    $remaining_items = [];

    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            $remaining_items[] = $item;
            continue;
        }

        $label = meza_get_standardized_submenu_utility_label($item);
        if ($label === '') {
            $remaining_items[] = $item;
            continue;
        }

        $tail_items[] = [
            'item' => $item,
            'label' => $label,
            'index' => (int) $index,
        ];
    }

    if (empty($tail_items)) {
        return $items;
    }

    usort($tail_items, static function (array $left, array $right) use ($order_map): int {
        $left_order = $order_map[$left['label']] ?? 999;
        $right_order = $order_map[$right['label']] ?? 999;

        if ($left_order === $right_order) {
            return $left['index'] <=> $right['index'];
        }

        return $left_order <=> $right_order;
    });

    foreach ($tail_items as $tail_item) {
        $remaining_items[] = $tail_item['item'];
    }

    return array_values($remaining_items);
}

function meza_get_keyword_dashicon_for_top_level_menu_item(string $label, string $slug = ''): string
{
    $normalized_label = strtolower(trim(wp_strip_all_tags($label)));
    $normalized_slug = strtolower(trim($slug));
    $haystack = trim($normalized_label . ' ' . $normalized_slug);

    if ($haystack === '') {
        return '';
    }

    if (preg_match('/\bseo\b/i', $haystack) === 1) {
        return 'dashicons-search';
    }

    if (preg_match('/\b(views?|layouts?)\b/i', $haystack) === 1) {
        return 'dashicons-screenoptions';
    }

    if (preg_match('/\btemplates?\b/i', $haystack) === 1) {
        return 'dashicons-layout';
    }

    if (preg_match('/\bthemes?\b/i', $haystack) === 1) {
        return 'dashicons-admin-appearance';
    }

    if (preg_match('/\bforms?\b/i', $haystack) === 1) {
        return 'dashicons-feedback';
    }

    if (preg_match('/\bvenues?\b/i', $haystack) === 1) {
        return 'dashicons-location-alt';
    }

    if (preg_match('/\borganizers?\b/i', $haystack) === 1) {
        return 'dashicons-groups';
    }

    if (preg_match('/\b(analytics?|insights?)\b/i', $haystack) === 1) {
        return 'dashicons-chart-area';
    }

    if (preg_match('/\b(opt[\s-]?in|marketing)\b/i', $haystack) === 1) {
        return 'dashicons-megaphone';
    }

    return '';
}

function meza_get_keyword_top_level_menu_label(string $label, string $slug = ''): string
{
    $normalized_label = strtolower(trim(wp_strip_all_tags($label)));
    $normalized_slug = strtolower(trim($slug));
    $haystack = trim($normalized_label . ' ' . $normalized_slug);

    if ($haystack === '') {
        return '';
    }

    if (preg_match('/\bseo\b/i', $haystack) === 1) {
        return 'SEO';
    }

    if (preg_match('/\b(analytics?|insights?)\b/i', $haystack) === 1) {
        return 'Web Analytics';
    }

    if (preg_match('/\bforms?\b/i', $haystack) === 1) {
        return 'Forms';
    }

    if (preg_match('/\b(opt[\s-]?in|marketing)\b/i', $haystack) === 1) {
        return 'Marketing';
    }

    return '';
}

function meza_strip_parent_content_type_from_label(string $label, array $candidates): string
{
    $label = trim(wp_strip_all_tags($label));
    if ($label === '') {
        return '';
    }

    $candidates = array_values(array_unique(array_filter(array_map(static function ($candidate): string {
        return trim(wp_strip_all_tags((string) $candidate));
    }, $candidates))));

    if (empty($candidates)) {
        return $label;
    }

    $new_label = $label;

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }

        $quoted = preg_quote($candidate, '/');
        $updated_label = preg_replace('/^' . $quoted . '\s+/i', '', $new_label);
        if ($updated_label !== null && $updated_label !== $new_label) {
            $new_label = trim((string) preg_replace('/\s+/', ' ', $updated_label));
            break;
        }

        $updated_label = preg_replace('/\b' . $quoted . '\b\s*/i', '', $new_label, 1);
        if ($updated_label !== null && $updated_label !== $new_label) {
            $new_label = trim((string) preg_replace('/\s+/', ' ', $updated_label));
            break;
        }
    }

    return $new_label !== '' ? $new_label : $label;
}

function meza_get_top_level_menu_label_by_slug(string $menu_slug): string
{
    global $menu;

    if (!is_array($menu) || $menu_slug === '') {
        return '';
    }

    foreach ($menu as $menu_item) {
        if (!is_array($menu_item)) {
            continue;
        }

        if (((string) ($menu_item[2] ?? '')) !== $menu_slug) {
            continue;
        }

        return trim(wp_strip_all_tags((string) ($menu_item[0] ?? '')));
    }

    return '';
}

function meza_is_customize_submenu_slug(string $menu_slug): bool
{
    $menu_slug = strtolower(trim($menu_slug));
    if ($menu_slug === '') {
        return false;
    }

    return $menu_slug === 'customize.php'
        || str_starts_with($menu_slug, 'customize.php?');
}

function meza_get_post_type_from_admin_menu_slug(string $menu_slug): string
{
    $menu_slug = trim($menu_slug);
    if ($menu_slug === '') {
        return '';
    }

    if ($menu_slug === 'edit.php' || $menu_slug === 'post-new.php') {
        return 'post';
    }

    if (
        !str_starts_with($menu_slug, 'edit.php?')
        && !str_starts_with($menu_slug, 'post-new.php?')
    ) {
        return '';
    }

    parse_str((string) parse_url($menu_slug, PHP_URL_QUERY), $query_args);
    return sanitize_key((string) ($query_args['post_type'] ?? ''));
}

function meza_get_top_level_menu_slug_for_post_type(string $post_type): string
{
    $post_type = sanitize_key($post_type);
    if ($post_type === '') {
        return '';
    }

    return ($post_type === 'post') ? 'edit.php' : 'edit.php?post_type=' . $post_type;
}

function meza_post_type_has_top_level_admin_menu(string $post_type): bool
{
    $menu_slug = meza_get_top_level_menu_slug_for_post_type($post_type);
    return $menu_slug !== '' && meza_get_top_level_menu_label_by_slug($menu_slug) !== '';
}

function meza_dedupe_post_type_submenu_links(): void
{
    global $submenu;

    if (!is_array($submenu)) {
        return;
    }

    $occurrences = [];

    foreach ($submenu as $parent_slug => $items) {
        if (!is_array($items)) {
            continue;
        }

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $item_slug = (string) ($item[2] ?? '');
            $post_type = meza_get_post_type_from_admin_menu_slug($item_slug);
            if ($post_type === '') {
                continue;
            }

            $occurrences[$item_slug][] = [
                'parent' => (string) $parent_slug,
                'index' => (int) $index,
                'preferred_parent' => meza_get_top_level_menu_slug_for_post_type($post_type),
            ];
        }
    }

    foreach ($occurrences as $records) {
        if (count($records) < 2) {
            continue;
        }

        $keep_record = null;
        $keep_score = -1;

        foreach ($records as $record) {
            $parent_slug = (string) ($record['parent'] ?? '');
            $parent_post_type = meza_get_post_type_from_admin_menu_slug($parent_slug);
            $score = 0;

            if ($parent_slug !== '' && $parent_slug === (string) ($record['preferred_parent'] ?? '')) {
                $score = 3;
            } elseif ($parent_post_type !== '' || $parent_slug === 'edit.php') {
                $score = 2;
            } else {
                $score = 1;
            }

            if ($score > $keep_score) {
                $keep_score = $score;
                $keep_record = $record;
            }
        }

        if (!is_array($keep_record)) {
            continue;
        }

        $keep_parent = (string) ($keep_record['parent'] ?? '');

        foreach ($records as $record) {
            $parent_slug = (string) ($record['parent'] ?? '');
            $index = (int) ($record['index'] ?? -1);

            if ($parent_slug === $keep_parent || !isset($submenu[$parent_slug][$index])) {
                continue;
            }

            unset($submenu[$parent_slug][$index]);
        }
    }

    foreach ($submenu as &$items) {
        $items = array_values($items);
    }
    unset($items);
}

function meza_dedupe_tools_submenu_links(): void
{
    global $submenu;

    if (!is_array($submenu) || !isset($submenu['tools.php']) || !is_array($submenu['tools.php'])) {
        return;
    }

    $protected_tools_slugs = [
        'tools.php',
        'import.php',
        'export.php',
        'site-health.php',
        'export-personal-data.php',
        'erase-personal-data.php',
    ];

    foreach ($submenu['tools.php'] as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $item_title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
        $item_slug = (string) ($item[2] ?? '');

        if (
            str_contains($item_title, 'redirect')
            || str_contains(strtolower($item_slug), 'redirect')
        ) {
            unset($submenu['tools.php'][$index]);
            continue;
        }

        if ($item_slug === '' || in_array($item_slug, $protected_tools_slugs, true)) {
            continue;
        }

        foreach ($submenu as $parent_slug => $items) {
            if ($parent_slug === 'tools.php' || !is_array($items)) {
                continue;
            }

            foreach ($items as $other_item) {
                if (!is_array($other_item)) {
                    continue;
                }

                if (((string) ($other_item[2] ?? '')) === $item_slug) {
                    unset($submenu['tools.php'][$index]);
                    continue 3;
                }
            }
        }
    }

    $submenu['tools.php'] = array_values($submenu['tools.php']);
}

function meza_normalize_plugin_submenu_pair_label(string $label, bool $is_add_item = false): string
{
    $label = trim(wp_strip_all_tags($label));
    if ($label === '') {
        return '';
    }

    if ($is_add_item) {
        if (preg_match('/^new\s+(.+)$/i', $label, $matches) === 1) {
            return 'Add ' . trim((string) ($matches[1] ?? ''));
        }

        if (preg_match('/^add\s+(.+)$/i', $label, $matches) === 1) {
            return 'Add ' . trim((string) ($matches[1] ?? ''));
        }
    }

    if (preg_match('/^all\s+(.+)$/i', $label, $matches) === 1) {
        return 'All ' . trim((string) ($matches[1] ?? ''));
    }

    return 'All ' . $label;
}

function meza_sort_submenu_items_with_standard_structure(array $items, string $parent_slug, string $current_post_type = ''): array
{
    $parent_label = meza_get_top_level_menu_label_by_slug($parent_slug);
    $dashboard_items = [];
    $primary_post_type_items = [];
    $secondary_post_type_items = [];
    $taxonomy_items = [];
    $middle_items = [];
    $utility_items = [];
    $other_items = [];

    $expected_list_slug = strtolower($parent_slug);
    $expected_add_slug = '';
    $current_post_type_singular_label = '';
    $current_post_type_plural_label = '';
    $parent_strip_candidates = $parent_label !== '' ? [$parent_label] : [];
    if ($current_post_type !== '') {
        $expected_add_slug = ($current_post_type === 'post')
            ? 'post-new.php'
            : 'post-new.php?post_type=' . strtolower($current_post_type);

        $current_post_type_object = get_post_type_object($current_post_type);
        if ($current_post_type_object instanceof WP_Post_Type) {
            $current_post_type_singular_label = trim((string) ($current_post_type_object->labels->singular_name ?? $current_post_type_object->labels->name ?? ''));
            $current_post_type_plural_label = trim((string) ($current_post_type_object->labels->name ?? $current_post_type_object->labels->singular_name ?? ''));
        }
        if ($current_post_type_singular_label === '') {
            $current_post_type_singular_label = trim(str_replace(['-', '_'], ' ', $current_post_type));
            $current_post_type_singular_label = $current_post_type_singular_label !== '' ? ucwords($current_post_type_singular_label) : '';
        }
        if ($current_post_type_plural_label === '') {
            $current_post_type_plural_label = $current_post_type_singular_label;
        }
        if ($current_post_type_plural_label !== '') {
            $parent_strip_candidates[] = $current_post_type_plural_label;
        }
        if ($current_post_type_singular_label !== '') {
            $parent_strip_candidates[] = $current_post_type_singular_label;
        }
    }

    $has_parent_label_list_item = false;
    $normalized_parent_list_label = $parent_label !== '' ? meza_normalize_plugin_submenu_pair_label($parent_label, false) : '';
    if ($parent_label !== '') {
        foreach (array_values($items) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $item_slug = strtolower((string) ($item[2] ?? ''));
            $item_title = trim(wp_strip_all_tags((string) ($item[0] ?? '')));
            if (
                $item_slug === $expected_list_slug
                && (
                    strcasecmp($item_title, $parent_label) === 0
                    || ($normalized_parent_list_label !== '' && strcasecmp($item_title, $normalized_parent_list_label) === 0)
                )
            ) {
                $has_parent_label_list_item = true;
                break;
            }
        }
    }

    foreach (array_values($items) as $item) {
        if (!is_array($item)) {
            $other_items[] = $item;
            continue;
        }

        $slug = strtolower((string) ($item[2] ?? ''));
        $title = trim(wp_strip_all_tags((string) ($item[0] ?? '')));
        $utility_label = meza_get_standardized_submenu_utility_label($item, $parent_slug);
        $pair_group_key = '';
        $pair_group_label = '';
        $pair_priority = 0;

        if (strcasecmp($title, 'Dashboard') === 0) {
            $dashboard_items[] = $item;
            continue;
        }

        if ($utility_label !== '') {
            $utility_items[] = $item;
            continue;
        }

        if (
            $current_post_type !== ''
            && (!function_exists('meza_is_acf_admin_post_type') || !meza_is_acf_admin_post_type($current_post_type))
            && $slug === $expected_list_slug
        ) {
            $pair_group_key = strtolower($current_post_type);
            $pair_group_label = $parent_label !== '' ? $parent_label : $title;
            $pair_priority = 10;
            $item[0] = meza_normalize_plugin_submenu_pair_label($title, false);
            if (isset($item[3])) $item[3] = $item[0];
        } elseif (
            $expected_add_slug !== ''
            && (!function_exists('meza_is_acf_admin_post_type') || !meza_is_acf_admin_post_type($current_post_type))
            && $slug === $expected_add_slug
        ) {
            $pair_group_key = strtolower($current_post_type);
            $pair_group_label = $parent_label !== '' ? $parent_label : $title;
            $pair_priority = 20;
            if ($current_post_type_singular_label !== '') {
                $item[0] = meza_normalize_add_post_type_label($current_post_type_singular_label);
                if (isset($item[3])) $item[3] = $item[0];
            }
        } elseif (str_starts_with($slug, 'edit.php?post_type=')) {
            parse_str((string) parse_url($slug, PHP_URL_QUERY), $query_args);
            $pair_post_type = strtolower((string) ($query_args['post_type'] ?? ''));
            $pair_group_key = $pair_post_type;
            $pair_group_label = meza_strip_parent_content_type_from_label($title, $parent_strip_candidates);
            $pair_priority = 10;
            $item[0] = $pair_group_label !== '' ? $pair_group_label : $title;
            if (isset($item[3])) $item[3] = $item[0];
        } elseif (str_starts_with($slug, 'post-new.php?post_type=')) {
            parse_str((string) parse_url($slug, PHP_URL_QUERY), $query_args);
            $pair_post_type = strtolower((string) ($query_args['post_type'] ?? ''));
            $pair_group_key = $pair_post_type;
            $pair_group_label = $parent_label !== '' ? $parent_label : $title;
            $pair_priority = 20;
            $pair_post_type_object = get_post_type_object($pair_post_type);
            $pair_post_type_singular_label = '';
            if ($pair_post_type_object instanceof WP_Post_Type) {
                $pair_post_type_singular_label = trim((string) ($pair_post_type_object->labels->singular_name ?? $pair_post_type_object->labels->name ?? ''));
            }
            if ($pair_post_type_singular_label === '') {
                $pair_post_type_singular_label = trim(str_replace(['-', '_'], ' ', $pair_post_type));
                $pair_post_type_singular_label = $pair_post_type_singular_label !== '' ? ucwords($pair_post_type_singular_label) : '';
            }
            if ($pair_post_type_singular_label !== '') {
                $pair_post_type_singular_label = meza_strip_parent_content_type_from_label($pair_post_type_singular_label, $parent_strip_candidates);
                $item[0] = meza_normalize_add_post_type_label($pair_post_type_singular_label);
                if (isset($item[3])) $item[3] = $item[0];
            }
        } elseif (
            $has_parent_label_list_item
            && $parent_label !== ''
            && $slug === $expected_list_slug
            && (
                strcasecmp($title, $parent_label) === 0
                || ($normalized_parent_list_label !== '' && strcasecmp($title, $normalized_parent_list_label) === 0)
            )
        ) {
            $pair_group_key = strtolower(sanitize_title($parent_label));
            $pair_group_label = $parent_label;
            $pair_priority = 10;
            $item[0] = $normalized_parent_list_label !== '' ? $normalized_parent_list_label : meza_normalize_plugin_submenu_pair_label($parent_label, false);
            if (isset($item[3])) $item[3] = $item[0];
        } elseif ($has_parent_label_list_item && $parent_label !== '' && preg_match('/^(new|add)\b/i', $title) === 1) {
            $pair_group_key = strtolower(sanitize_title($parent_label));
            $pair_group_label = $parent_label;
            $pair_priority = 20;
            $item[0] = meza_normalize_plugin_submenu_pair_label($title, true);
            if (isset($item[3])) $item[3] = $item[0];
        }

        if ($pair_group_key !== '') {
            $pair_entry = [
                'item' => $item,
                'group_key' => $pair_group_key,
                'group_label' => $pair_group_label,
                'priority' => $pair_priority,
            ];

            if ($current_post_type !== '' && $pair_group_key === strtolower($current_post_type)) {
                $primary_post_type_items[] = $pair_entry;
            } else {
                $secondary_post_type_items[] = $pair_entry;
            }

            continue;
        }

        if (str_starts_with($slug, 'edit-tags.php?taxonomy=')) {
            $taxonomy_items[] = $item;
            continue;
        }

        $middle_items[] = $item;
    }

    $sort_by_label = static function (array $left, array $right): int {
        $left_label = trim(wp_strip_all_tags((string) ($left[0] ?? '')));
        $right_label = trim(wp_strip_all_tags((string) ($right[0] ?? '')));

        return strnatcasecmp($left_label, $right_label);
    };

    $sort_post_type_pairs = static function (array &$entries): void {
        usort($entries, static function (array $left, array $right): int {
            $sort = strnatcasecmp((string) ($left['group_label'] ?? ''), (string) ($right['group_label'] ?? ''));
            if ($sort !== 0) {
                return $sort;
            }

            $group_sort = strnatcasecmp((string) ($left['group_key'] ?? ''), (string) ($right['group_key'] ?? ''));
            if ($group_sort !== 0) {
                return $group_sort;
            }

            return ((int) ($left['priority'] ?? 99)) <=> ((int) ($right['priority'] ?? 99));
        });
    };

    $sort_post_type_pairs($primary_post_type_items);
    $sort_post_type_pairs($secondary_post_type_items);
    usort($taxonomy_items, $sort_by_label);

    $sortable_middle_items = [];
    $unsortable_middle_items = [];

    foreach ($middle_items as $item) {
        if (is_array($item)) {
            $sortable_middle_items[] = $item;
            continue;
        }

        $unsortable_middle_items[] = $item;
    }

    usort($sortable_middle_items, $sort_by_label);

    return array_values(array_merge(
        $dashboard_items,
        array_map(static function (array $entry): array {
            return $entry['item'];
        }, $primary_post_type_items),
        array_map(static function (array $entry): array {
            return $entry['item'];
        }, $secondary_post_type_items),
        $taxonomy_items,
        $sortable_middle_items,
        $unsortable_middle_items,
        meza_reorder_standardized_submenu_utility_items($utility_items)
    ));
}

function meza_normalize_admin_plugin_menus(): void
{
    global $menu, $submenu;

    if (!is_array($menu) || !is_array($submenu)) return;
    foreach ($menu as $index => &$item) {
        if (!is_array($item)) continue;

        $slug = strtolower((string) ($item[2] ?? ''));
        $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));

        $is_wp_mail_smtp = str_contains($slug, 'wp-mail-smtp') || $title === 'wp mail smtp';
        $is_updraft = str_contains($slug, 'updraft')
            || in_array($title, ['updraft', 'updraftplus'], true);
        $is_site_kit = str_contains($slug, 'googlesitekit')
            || str_contains($slug, 'google-site-kit')
            || in_array($title, ['site kit', 'site kit by google'], true);
        $is_aios = str_contains($slug, 'aiowpsec')
            || str_contains($slug, 'wp-security')
            || $title === 'security';
        $is_yoast = str_contains($slug, 'wpseo')
            || str_contains($title, 'yoast seo')
            || $title === 'seo';
        $is_godaddy_dashboard = str_contains($slug, 'page=wp-dashboard')
            || str_contains($slug, 'godaddy')
            || $title === 'godaddy';
        $is_customer_sign_generator = str_contains($slug, 'client-sign-generator')
            || $title === 'customer sign generator';
        $is_woocommerce = $slug === 'woocommerce'
            || $title === 'woocommerce';
        $is_make = str_contains($slug, 'ds-make')
            || $title === 'make';

        if ($is_wp_mail_smtp) {
            $item[0] = 'Mail';
            if (isset($item[3])) $item[3] = 'Mail';
            $item[6] = 'dashicons-email-alt2';
            continue;
        }

        if ($is_updraft) {
            $item[0] = 'Backups';
            if (isset($item[3])) $item[3] = 'Backups';
            continue;
        }

        if ($is_site_kit) {
            $item[0] = 'Web Analytics';
            if (isset($item[3])) $item[3] = 'Web Analytics';
            $item[6] = 'dashicons-chart-area';
            continue;
        }

        if ($is_aios) {
            $item[0] = 'Security';
            if (isset($item[3])) $item[3] = 'Security';
            $item[6] = 'dashicons-shield';
            continue;
        }

        if ($is_yoast) {
            $item[0] = 'SEO';
            if (isset($item[3])) $item[3] = 'SEO';
            $item[6] = 'dashicons-search';
            continue;
        }

        if ($is_godaddy_dashboard) {
            $item[0] = 'Hosting';
            if (isset($item[3])) $item[3] = 'Hosting';
            $item[6] = 'dashicons-admin-site-alt3';
            continue;
        }

        if ($is_customer_sign_generator) {
            $item[0] = 'Customer Sign Generator';
            if (isset($item[3])) $item[3] = 'Customer Sign Generator';
            $item[6] = 'dashicons-rest-api';
            continue;
        }

        if ($is_woocommerce) {
            $item[0] = 'Store';
            if (isset($item[3])) $item[3] = 'Store';
            continue;
        }

        if ($is_make) {
            $item[0] = 'Make';
            if (isset($item[3])) $item[3] = 'Make';
            $item[6] = 'dashicons-share-alt';
            continue;
        }

        if (str_starts_with($slug, 'edit.php?post_type=')) {
            parse_str((string) parse_url((string) $slug, PHP_URL_QUERY), $query_args);
            $post_type = (string) ($query_args['post_type'] ?? '');

            if ($post_type !== '') {
                if (function_exists('meza_is_acf_admin_post_type') && meza_is_acf_admin_post_type($post_type)) {
                    $item[0] = 'ACF';
                    if (isset($item[3])) $item[3] = 'ACF';
                    continue;
                }

                $post_type_object = get_post_type_object($post_type);
                $plural_label = '';

                if ($post_type_object instanceof WP_Post_Type) {
                    $plural_label = trim((string) ($post_type_object->labels->name ?? $post_type_object->labels->singular_name ?? ''));
                }

                if ($plural_label !== '') {
                    $item[0] = $plural_label;
                    if (isset($item[3])) $item[3] = $plural_label;
                }

                $menu_icon_map = [
                    'tribe_events' => 'dashicons-calendar-alt',
                    'tribe_venue' => 'dashicons-location-alt',
                    'tribe_organizer' => 'dashicons-groups',
                    'project' => 'dashicons-portfolio',
                    'provider' => 'dashicons-admin-multisite',
                    'sponsor' => 'dashicons-awards',
                    'popup' => 'dashicons-admin-comments',
                ];

                if (isset($menu_icon_map[$post_type])) {
                    $item[6] = $menu_icon_map[$post_type];
                }
            }
        }

        $keyword_dashicon = meza_get_keyword_dashicon_for_top_level_menu_item((string) ($item[0] ?? ''), $slug);
        if ($keyword_dashicon !== '') {
            $keyword_label = meza_get_keyword_top_level_menu_label((string) ($item[0] ?? ''), $slug);
            if ($keyword_label !== '') {
                $item[0] = $keyword_label;
                if (isset($item[3])) $item[3] = $keyword_label;
            }
            $item[6] = $keyword_dashicon;
        }
    }
    unset($item);

    foreach ($submenu as $parent_slug => &$items) {
        if (!is_array($items)) continue;

        foreach ($items as $index => &$item) {
            if (!is_array($item)) continue;

            $raw_title = (string) ($item[0] ?? '');
            $slug = strtolower((string) ($item[2] ?? ''));
            $title = strtolower(trim(wp_strip_all_tags($raw_title)));
            $is_yoast_menu = str_contains(strtolower((string) $parent_slug), 'wpseo');
            $is_wp_mail_smtp_menu = str_contains(strtolower((string) $parent_slug), 'wp-mail-smtp');
            $is_redirection = str_contains($slug, 'redirection')
                || str_contains($title, 'redirection');

            if (meza_submenu_label_contains_banned_words($raw_title)) {
                unset($items[$index]);
                continue;
            }

            if (str_contains($title, 'author seo')) {
                unset($items[$index]);
                continue;
            }

            if (
                in_array((string) $parent_slug, ['users.php', 'profile.php'], true)
                && str_contains($title, 'author')
            ) {
                unset($items[$index]);
                continue;
            }

            if (meza_is_customize_submenu_slug($slug)) {
                unset($items[$index]);
                continue;
            }

            if (meza_is_promotional_submenu_item((string) $parent_slug, $item)) {
                unset($items[$index]);
                continue;
            }

            if (meza_is_resource_submenu_item((string) $parent_slug, $item)) {
                unset($items[$index]);
                continue;
            }

            if (
                meza_submenu_label_uses_custom_markup($raw_title)
                && !meza_is_default_wordpress_submenu_item((string) $parent_slug, $item)
            ) {
                unset($items[$index]);
                continue;
            }

            if (str_contains($title, 'upgrade')) {
                unset($items[$index]);
                continue;
            }

            if (str_contains($title, 'yoast')) {
                unset($items[$index]);
                continue;
            }

            $linked_post_type = meza_get_post_type_from_admin_menu_slug((string) ($item[2] ?? ''));
            if ($linked_post_type !== '') {
                $expected_parent_slug = meza_get_top_level_menu_slug_for_post_type($linked_post_type);
                if (
                    meza_post_type_has_top_level_admin_menu($linked_post_type)
                    && (string) $parent_slug !== $expected_parent_slug
                ) {
                    unset($items[$index]);
                    continue;
                }
            }

            if ($is_redirection) {
                $item[0] = 'Redirects';
                if (isset($item[3])) $item[3] = 'Redirects';
                continue;
            }

            if ($is_yoast_menu && !in_array($title, ['general', 'settings', 'tools'], true)) {
                unset($items[$index]);
                continue;
            }

            if ($is_wp_mail_smtp_menu && !in_array($title, ['settings', 'tools'], true)) {
                unset($items[$index]);
                continue;
            }

            if ($parent_slug === 'woocommerce' && $title === 'woocommerce') {
                $item[0] = 'Store';
                if (isset($item[3])) $item[3] = 'Store';
                continue;
            }

            $is_intuitive_cpo = str_contains($slug, 'intuitive-custom-post-order')
                || str_contains($slug, 'cporder')
                || $title === 'intuitive cpo';
            $is_post_duplicator = str_contains($slug, 'post-duplicator')
                || $title === 'post duplicator';
            $is_converter_for_media = str_contains($slug, 'webp')
                || str_contains($slug, 'converter-for-media')
                || $title === 'converter for media';
            $is_wp_super_cache = str_contains($slug, 'wp-super-cache')
                || str_contains($slug, 'wpsupercache')
                || $title === 'wp super cache';
            $is_menu_editor = $slug === 'menu_editor'
                || str_contains($slug, 'menu_editor')
                || str_contains($slug, 'menu-editor')
                || str_contains($slug, 'admin-menu-editor')
                || str_contains($title, 'menu editor')
                || str_contains($title, 'admin menu');

            if ($parent_slug === 'options-general.php' && $is_intuitive_cpo) {
                $item[0] = 'Post Ordering';
                if (isset($item[3])) $item[3] = 'Post Ordering';
                continue;
            }

            if ($parent_slug === 'options-general.php' && $is_post_duplicator) {
                $item[0] = 'Post Duplication';
                if (isset($item[3])) $item[3] = 'Post Duplication';
                continue;
            }

            if (in_array($parent_slug, ['options-general.php', 'upload.php'], true) && $is_converter_for_media) {
                $new_label = ($parent_slug === 'upload.php') ? 'Performance' : 'Image Performance';
                $item[0] = $new_label;
                if (isset($item[3])) $item[3] = $new_label;
                continue;
            }

            if ($parent_slug === 'options-general.php' && $is_wp_super_cache) {
                $item[0] = 'Page Cache';
                if (isset($item[3])) $item[3] = 'Page Cache';
                continue;
            }

            if ($is_menu_editor) {
                $item[0] = 'Admin Menu';
                if (isset($item[3])) $item[3] = 'Admin Menu';
                continue;
            }

            $standardized_label = meza_get_standardized_submenu_utility_label($item, (string) $parent_slug);
            if ($standardized_label !== '') {
                $item[0] = $standardized_label;
                if (isset($item[3])) $item[3] = $standardized_label;
            }

        }
        unset($item);
        $items = array_values($items);

        if ($parent_slug === 'options-general.php') {
            $ordered_labels = [
                'Contact Information',
                'Business Information',
                'CRM Integration',
                'Page Cache',
                'Image Performance',
                'Post Ordering',
                'Post Duplication',
                'Admin Columns',
                'Admin Menu',
            ];

            $ordered_items = [];
            $matched_indexes = [];
            $privacy_index = null;

            foreach ($items as $index => $item) {
                if (!is_array($item)) continue;

                $label = trim(wp_strip_all_tags((string) ($item[0] ?? '')));

                if (strcasecmp($label, 'Privacy') === 0 && $privacy_index === null) {
                    $privacy_index = (int) $index;
                }

                $label_match_index = array_search($label, $ordered_labels, true);
                if ($label_match_index === false) continue;

                $ordered_items[$label_match_index] = $item;
                $matched_indexes[] = (int) $index;
            }

            if ($privacy_index !== null && !empty($matched_indexes)) {
                rsort($matched_indexes, SORT_NUMERIC);
                foreach ($matched_indexes as $matched_index) {
                    array_splice($items, $matched_index, 1);
                    if ($matched_index < $privacy_index) {
                        $privacy_index--;
                    }
                }

                ksort($ordered_items);
                array_splice($items, $privacy_index + 1, 0, array_values($ordered_items));
            }
        }

        if ($parent_slug !== 'options-general.php') {
            $items = meza_reorder_standardized_submenu_utility_items($items);
        }
    }
    unset($items);

    meza_dedupe_post_type_submenu_links();
    meza_dedupe_tools_submenu_links();
}

// Normalize selected plugin/admin menu labels.
add_action('admin_menu', 'meza_normalize_admin_plugin_menus', PHP_INT_MAX - 2);

function meza_rebuild_content_menu_group(): void
{
    global $menu;

    if (!is_array($menu) || empty($menu)) return;

    $with_permalink = [];
    $without_permalink = [];
    $media_items = [];

    foreach ($menu as $item) {
        if (!is_array($item)) continue;

        $slug = (string) ($item[2] ?? '');
        if ($slug === 'upload.php') {
            $media_items[] = $item;
            continue;
        }

        $group = meza_admin_menu_content_group($slug);
        if ($group === '') continue;

        $entry = [
            'item' => $item,
            'label' => strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? '')))),
        ];

        if ($group === 'with') {
            $with_permalink[] = $entry;
        } else {
            $without_permalink[] = $entry;
        }
    }

    if (empty($with_permalink) && empty($without_permalink)) return;

    $sort_entries = static function (array &$entries): void {
        usort($entries, static function (array $a, array $b): int {
            return strnatcasecmp($a['label'], $b['label']);
        });
    };

    $sort_entries($with_permalink);
    $sort_entries($without_permalink);

    $grouped_items = array_map(
        static function (array $entry): array {
            return $entry['item'];
        },
        $with_permalink
    );

    if (!empty($with_permalink) && !empty($without_permalink)) {
        $grouped_items[] = [
            '',
            'read',
            'separator-meza-content-groups',
            '',
            'wp-menu-separator',
        ];
    }

    foreach ($without_permalink as $entry) {
        $grouped_items[] = $entry['item'];
    }

    if (!empty($media_items)) {
        if (!empty($grouped_items)) {
            $grouped_items[] = [
                '',
                'read',
                'separator-meza-content-media',
                '',
                'wp-menu-separator',
            ];
        }

        foreach ($media_items as $media_item) {
            $grouped_items[] = $media_item;
        }
    }

    $rebuilt = [];
    $inserted = false;

    foreach ($menu as $item) {
        if (!is_array($item)) {
            $rebuilt[] = $item;
            continue;
        }

        $slug = (string) ($item[2] ?? '');
        if ($slug === 'upload.php' || meza_admin_menu_content_group($slug) !== '') {
            continue;
        }

        $rebuilt[] = $item;

        if (!$inserted && $slug === 'index.php') {
            $rebuilt[] = [
                '',
                'read',
                'separator-meza-dashboard-content',
                '',
                'wp-menu-separator',
            ];
            foreach ($grouped_items as $grouped_item) {
                $rebuilt[] = $grouped_item;
            }
            $inserted = true;
        }
    }

    if ($inserted) {
        $cleaned = [];
        $count = count($rebuilt);

        for ($i = 0; $i < $count; $i++) {
            $item = $rebuilt[$i];
            if (!is_array($item)) {
                $cleaned[] = $item;
                continue;
            }

            $slug = (string) ($item[2] ?? '');
            $classes = strtolower((string) ($item[4] ?? ''));
            $is_separator = str_contains($classes, 'wp-menu-separator') || str_starts_with($slug, 'separator');
            if (!$is_separator) {
                $cleaned[] = $item;
                continue;
            }

            $prev = null;
            for ($p = $i - 1; $p >= 0; $p--) {
                if (is_array($rebuilt[$p])) {
                    $prev = $rebuilt[$p];
                    break;
                }
            }

            $next = null;
            for ($n = $i + 1; $n < $count; $n++) {
                if (is_array($rebuilt[$n])) {
                    $next = $rebuilt[$n];
                    break;
                }
            }

            // Drop separators at edges and collapse stacked separators.
            if ($prev === null || $next === null) continue;
            if (!empty($cleaned)) {
                $last = $cleaned[count($cleaned) - 1];
                if (is_array($last)) {
                    $last_slug = (string) ($last[2] ?? '');
                    $last_classes = strtolower((string) ($last[4] ?? ''));
                    $last_is_separator = str_contains($last_classes, 'wp-menu-separator') || str_starts_with($last_slug, 'separator');
                    if ($last_is_separator) continue;
                }
            }

            $cleaned[] = $item;
        }

        $menu = $cleaned;
    }
}

// Group top-level content menus after Dashboard: permalink-capable first, then non-viewable/admin-only.
add_action('admin_menu', 'meza_rebuild_content_menu_group', PHP_INT_MAX - 1);

function meza_reorder_dashboard_utility_items(): void
{
    global $menu;

    if (!is_array($menu) || empty($menu)) return;

    $user = wp_get_current_user();
    $hide_yoast_menu = meza_should_hide_yoast_admin_menu_for_user($user);
    $is_site_manager_user = $user instanceof WP_User
        && in_array(meza_site_manager_role_key(), (array) $user->roles, true);

    $dashboard_index = null;
    $ordered_items = [
        'web_analytics' => null,
        'seo' => null,
    ];
    $matched_indexes = [];
    $is_site_kit_item = static function (string $slug, string $title): bool {
        return str_contains($slug, 'googlesitekit')
            || str_contains($slug, 'google-site-kit')
            || str_contains($slug, 'site-kit')
            || in_array($title, ['web analytics', 'site kit', 'site kit by google'], true);
    };
    $is_yoast_item = static function (string $slug, string $title): bool {
        return str_contains($slug, 'wpseo')
            || str_contains($slug, 'wordpress-seo')
            || str_contains($title, 'yoast seo')
            || in_array($title, ['yoast', 'seo'], true);
    };

    foreach ($menu as $index => $item) {
        if (!is_array($item)) continue;

        $slug = strtolower((string) ($item[2] ?? ''));
        $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));

        if ($slug === 'index.php' && $dashboard_index === null) {
            $dashboard_index = (int) $index;
            continue;
        }

        $is_site_kit = $is_site_kit_item($slug, $title);
        $is_yoast = $is_yoast_item($slug, $title);
        if ($is_site_kit) {
            if ($ordered_items['web_analytics'] === null) {
                $ordered_items['web_analytics'] = $item;
            }
            $matched_indexes[] = (int) $index;
            continue;
        }

        if ($is_yoast) {
            if (!$hide_yoast_menu && $ordered_items['seo'] === null) {
                $ordered_items['seo'] = $item;
            }
            $matched_indexes[] = (int) $index;
            continue;
        }
    }

    if ($dashboard_index === null) return;

    if (!empty($matched_indexes)) {
        rsort($matched_indexes, SORT_NUMERIC);
        foreach ($matched_indexes as $matched_index) {
            array_splice($menu, $matched_index, 1);
        }

        foreach ($menu as $index => $item) {
            if (is_array($item) && ((string) ($item[2] ?? '')) === 'index.php') {
                $dashboard_index = (int) $index;
                break;
            }
        }
    }

    $items_to_insert = array_values(array_filter($ordered_items, static function ($item): bool {
        return is_array($item);
    }));

    if (!empty($items_to_insert)) {
        usort($items_to_insert, static function (array $a, array $b): int {
            $label_a = strtolower(trim(wp_strip_all_tags((string) ($a[0] ?? ''))));
            $label_b = strtolower(trim(wp_strip_all_tags((string) ($b[0] ?? ''))));
            return strnatcasecmp($label_a, $label_b);
        });
        array_splice($menu, $dashboard_index + 1, 0, $items_to_insert);
    }

    $group_indexes_to_remove = [];
    for ($i = $dashboard_index + 1, $count = count($menu); $i < $count; $i++) {
        $item = $menu[$i] ?? null;
        if (!is_array($item)) continue;

        $slug = strtolower((string) ($item[2] ?? ''));
        $classes = strtolower((string) ($item[4] ?? ''));
        $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
        $is_separator = str_starts_with($slug, 'separator')
            || str_contains($classes, 'wp-menu-separator');

        if ($is_separator) {
            break;
        }

        $is_site_kit = $is_site_kit_item($slug, $title);
        $is_yoast = $is_yoast_item($slug, $title);
        $is_allowed_dashboard_item = $is_site_kit || (!$hide_yoast_menu && $is_yoast);

        if (!$is_allowed_dashboard_item) {
            $group_indexes_to_remove[] = $i;
        }
    }

    if (empty($group_indexes_to_remove)) return;

    rsort($group_indexes_to_remove, SORT_NUMERIC);
    foreach ($group_indexes_to_remove as $group_index) {
        array_splice($menu, $group_index, 1);
    }
}

// Keep utility plugins grouped with Dashboard in a fixed order before the content separator.
add_action('admin_menu', 'meza_reorder_dashboard_utility_items', PHP_INT_MAX);

function meza_get_dashboard_group_end_index(array $menu): ?int
{
    $dashboard_index = null;

    foreach ($menu as $index => $item) {
        if (is_array($item) && ((string) ($item[2] ?? '')) === 'index.php') {
            $dashboard_index = (int) $index;
            break;
        }
    }

    if ($dashboard_index === null) {
        return null;
    }

    $dashboard_group_end = $dashboard_index + 1;

    for ($i = $dashboard_index + 1, $count = count($menu); $i < $count; $i++) {
        $item = $menu[$i] ?? null;
        if (!is_array($item)) {
            break;
        }

        $slug = strtolower((string) ($item[2] ?? ''));
        $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
        $is_separator = str_starts_with($slug, 'separator')
            || str_contains(strtolower((string) ($item[4] ?? '')), 'wp-menu-separator');
        $is_dashboard_utility = str_contains($slug, 'googlesitekit')
            || str_contains($slug, 'google-site-kit')
            || str_contains($slug, 'site-kit')
            || str_contains($slug, 'wpseo')
            || str_contains($slug, 'wordpress-seo')
            || in_array($title, ['web analytics', 'site kit', 'site kit by google', 'seo'], true);

        if ($is_separator || !$is_dashboard_utility) {
            break;
        }

        $dashboard_group_end = $i + 1;
    }

    return $dashboard_group_end;
}

function meza_get_wc_admin_path(): string
{
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
    if ($page !== 'wc-admin') {
        return '';
    }

    $path = isset($_GET['path']) ? (string) wp_unslash((string) $_GET['path']) : '';
    $path = '/' . ltrim(trim($path), '/');

    return $path === '/' ? '' : $path;
}

function meza_is_woocommerce_analytics_embed(): bool
{
    return isset($_GET['meza_embed']) && wp_unslash((string) $_GET['meza_embed']) === '1';
}

function meza_is_store_analytics_wrapper_screen($screen = null): bool
{
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
    if ($page === 'meza-woocommerce-analytics') {
        return true;
    }

    if ($screen === null && function_exists('get_current_screen')) {
        $screen = get_current_screen();
    }

    if (!($screen instanceof WP_Screen)) {
        return false;
    }

    $screen_id = (string) ($screen->id ?? '');
    $screen_base = (string) ($screen->base ?? '');

    return str_starts_with($screen_id, 'woocommerce_page_meza-woocommerce-analytics')
        || str_starts_with($screen_base, 'woocommerce_page_meza-woocommerce-analytics')
        || str_starts_with($screen_id, 'admin_page_meza-woocommerce-analytics')
        || str_starts_with($screen_base, 'admin_page_meza-woocommerce-analytics');
}

function meza_get_woocommerce_analytics_nav_items(): array
{
    $items = [
        [
            'label' => 'Analytics Overview',
            'path' => '/analytics/overview',
        ],
        [
            'label' => 'Products',
            'path' => '/analytics/products',
        ],
        [
            'label' => 'Revenue',
            'path' => '/analytics/revenue',
        ],
        [
            'label' => 'Orders',
            'path' => '/analytics/orders',
        ],
        [
            'label' => 'Variations',
            'path' => '/analytics/variations',
        ],
        [
            'label' => 'Categories',
            'path' => '/analytics/categories',
        ],
        [
            'label' => 'Coupons',
            'path' => '/analytics/coupons',
        ],
        [
            'label' => 'Taxes',
            'path' => '/analytics/taxes',
        ],
        [
            'label' => 'Downloads',
            'path' => '/analytics/downloads',
        ],
    ];

    if (get_option('woocommerce_manage_stock') === 'yes') {
        $items[] = [
            'label' => 'Stock',
            'path' => '/analytics/stock',
        ];
    }

    $items[] = [
        'label' => 'Settings',
        'path' => '/analytics/settings',
    ];

    return $items;
}

function meza_normalize_woocommerce_analytics_path(string $path = ''): string
{
    $normalized_path = '/' . ltrim(trim($path), '/');
    if ($normalized_path === '/') {
        $normalized_path = '/analytics/overview';
    }

    $allowed_paths = array_map(
        static fn(array $item): string => (string) ($item['path'] ?? ''),
        meza_get_woocommerce_analytics_nav_items()
    );

    return in_array($normalized_path, $allowed_paths, true) ? $normalized_path : '/analytics/overview';
}

function meza_get_store_analytics_current_path(): string
{
    if (meza_is_store_analytics_wrapper_screen()) {
        $path = isset($_GET['analytics_path']) ? (string) wp_unslash((string) $_GET['analytics_path']) : '';
        return meza_normalize_woocommerce_analytics_path($path);
    }

    return meza_normalize_woocommerce_analytics_path(meza_get_wc_admin_path());
}

function meza_is_woocommerce_analytics_screen($screen = null): bool
{
    if ($screen === null && function_exists('get_current_screen')) {
        $screen = get_current_screen();
    }

    if (!($screen instanceof WP_Screen)) {
        return false;
    }

    $screen_id = (string) ($screen->id ?? '');
    $screen_base = (string) ($screen->base ?? '');
    $wc_admin_path = meza_get_wc_admin_path();

    if ($wc_admin_path !== '' && str_starts_with($wc_admin_path, '/analytics/')) {
        return true;
    }
    return false;
}

function meza_is_woocommerce_headerless_wc_admin_screen(): bool
{
    $wc_admin_path = meza_get_wc_admin_path();
    if ($wc_admin_path === '') {
        return false;
    }

    return str_starts_with($wc_admin_path, '/analytics/')
        || $wc_admin_path === '/customers';
}

function meza_get_wc_admin_menu_slug(string $path = ''): string
{
    $normalized_path = '/' . ltrim(trim($path), '/');

    if ($normalized_path === '/') {
        return 'wc-admin';
    }

    return 'wc-admin&path=' . $normalized_path;
}

function meza_get_wc_admin_url(string $path = ''): string
{
    return admin_url('admin.php?page=' . meza_get_wc_admin_menu_slug($path));
}

function meza_get_store_analytics_forwarded_query_args(): array
{
    $args = [];

    foreach ($_GET as $key => $value) {
        if (!is_scalar($value)) {
            continue;
        }

        $raw_key = trim((string) $key);
        if (
            $raw_key === ''
            || !preg_match('/^[A-Za-z0-9_-]+$/', $raw_key)
            || in_array(strtolower($raw_key), ['page', 'path', 'analytics_path', 'meza_embed'], true)
        ) {
            continue;
        }

        $args[$raw_key] = wp_unslash((string) $value);
    }

    return $args;
}

function meza_get_store_analytics_url(string $path = '/analytics/overview', array $args = []): string
{
    $query_args = array_merge(
        [
            'page' => 'meza-woocommerce-analytics',
            'analytics_path' => meza_normalize_woocommerce_analytics_path($path),
        ],
        $args
    );

    return add_query_arg($query_args, admin_url('admin.php'));
}

function meza_get_wc_admin_embed_url(string $path = '/analytics/overview', array $args = []): string
{
    $query_args = array_merge(
        [
            'page' => 'wc-admin',
            'path' => meza_normalize_woocommerce_analytics_path($path),
            'meza_embed' => '1',
        ],
        $args
    );

    return add_query_arg($query_args, admin_url('admin.php'));
}

function meza_is_woocommerce_menu_item(array $item, array $submenu_items = []): bool
{
    $slug = strtolower((string) ($item[2] ?? ''));
    $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));

    if (
        $slug === 'woocommerce'
        || $slug === 'wc-orders'
        || str_contains($slug, 'page=wc-orders')
        || in_array($title, ['woocommerce', 'store'], true)
    ) {
        return true;
    }

    foreach ($submenu_items as $submenu_item) {
        if (!is_array($submenu_item)) {
            continue;
        }

        $submenu_slug = strtolower((string) ($submenu_item[2] ?? ''));
        if (
            $submenu_slug === 'wc-orders'
            || str_contains($submenu_slug, 'post_type=shop_order')
            || str_contains($submenu_slug, '/customers')
            || str_contains($submenu_slug, '/analytics/overview')
            || str_contains($submenu_slug, 'page=wc-settings')
            || str_contains($submenu_slug, 'page=wc-status')
        ) {
            return true;
        }
    }

    return false;
}

function meza_get_woocommerce_admin_parent_slug(): string
{
    global $menu, $submenu, $_wp_real_parent_file;

    if (isset($_wp_real_parent_file['woocommerce']) && is_string($_wp_real_parent_file['woocommerce']) && $_wp_real_parent_file['woocommerce'] !== '') {
        return $_wp_real_parent_file['woocommerce'];
    }

    if (is_array($menu)) {
        foreach ($menu as $item) {
            if (!is_array($item)) {
                continue;
            }

            $slug = (string) ($item[2] ?? '');
            $submenu_items = isset($submenu[$slug]) && is_array($submenu[$slug]) ? $submenu[$slug] : [];

            if (meza_is_woocommerce_menu_item($item, $submenu_items)) {
                return $slug;
            }
        }
    }

    return 'woocommerce';
}

function meza_get_woocommerce_submenu_parent_slug(): string
{
    global $submenu;

    if (is_array($submenu) && isset($submenu['woocommerce']) && is_array($submenu['woocommerce'])) {
        return 'woocommerce';
    }

    if (is_array($submenu)) {
        foreach ($submenu as $parent_slug => $items) {
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $slug = strtolower((string) ($item[2] ?? ''));
                if (
                    $slug === 'wc-orders'
                    || str_contains($slug, 'post_type=shop_order')
                    || str_contains($slug, '/customers')
                    || str_contains($slug, 'page=wc-settings')
                    || str_contains($slug, 'page=wc-status')
                ) {
                    return (string) $parent_slug;
                }
            }
        }
    }

    return meza_get_woocommerce_admin_parent_slug();
}

function meza_render_store_analytics_page(): void
{
    $current_path = meza_get_store_analytics_current_path();
    $forwarded_args = meza_get_store_analytics_forwarded_query_args();
    $iframe_url = meza_get_wc_admin_embed_url($current_path, $forwarded_args);

    echo '<div class="wrap meza-woocommerce-analytics-nav-wrap"><nav class="nav-tab-wrapper" aria-label="Analytics">';

    foreach (meza_get_woocommerce_analytics_nav_items() as $item) {
        $path = (string) ($item['path'] ?? '');
        $label = (string) ($item['label'] ?? '');
        if ($path === '' || $label === '') {
            continue;
        }

        $classes = ['nav-tab'];
        if ($current_path === $path) {
            $classes[] = 'nav-tab-active';
        }

        printf(
            '<a class="%1$s" href="%2$s" data-meza-store-analytics-tab="%3$s">%4$s</a>',
            esc_attr(implode(' ', $classes)),
            esc_url(meza_get_store_analytics_url($path, $forwarded_args)),
            esc_attr($path),
            esc_html($label)
        );
    }

    echo '</nav></div>';

    printf(
        '<div class="meza-woocommerce-analytics-embed-shell"><iframe id="meza-woocommerce-analytics-iframe" class="meza-woocommerce-analytics-iframe" src="%1$s" title="%2$s" loading="eager"></iframe></div>',
        esc_url($iframe_url),
        esc_attr__('Store Analytics', 'mz-mu-plugins')
    );
}

add_action('admin_init', function (): void {
    if (!is_admin() || meza_is_woocommerce_analytics_embed()) {
        return;
    }

    $wc_admin_path = meza_get_wc_admin_path();
    if ($wc_admin_path === '' || !str_starts_with($wc_admin_path, '/analytics/')) {
        return;
    }

    wp_safe_redirect(meza_get_store_analytics_url($wc_admin_path, meza_get_store_analytics_forwarded_query_args()));
    exit;
}, 1);

add_action('admin_menu', function (): void {
    if (!meza_has_woocommerce_plugin()) {
        return;
    }

    add_submenu_page(
        'woocommerce',
        'Analytics',
        'Analytics',
        'view_woocommerce_reports',
        'meza-woocommerce-analytics',
        'meza_render_store_analytics_page'
    );
}, 1000);

add_action('admin_menu', function (): void {
    if (!meza_has_woocommerce_plugin()) {
        return;
    }

    remove_submenu_page('woocommerce', meza_get_wc_admin_menu_slug('/analytics/overview'));
    remove_submenu_page('woocommerce', 'admin.php?page=' . meza_get_wc_admin_menu_slug('/analytics/overview'));
}, 1001);

add_filter('woocommerce_analytics_report_menu_items', function ($report_pages) {
    if (!is_array($report_pages)) {
        return $report_pages;
    }

    foreach ($report_pages as &$report_page) {
        if (!is_array($report_page)) {
            continue;
        }

        $report_page_id = (string) ($report_page['id'] ?? '');

        if ($report_page_id === 'woocommerce-analytics') {
            $report_page['parent'] = 'woocommerce';
            unset($report_page['icon'], $report_page['position']);
        }
    }
    unset($report_page);

    return $report_pages;
}, 1000);

add_action('admin_head', function (): void {
    if (!(meza_is_store_analytics_wrapper_screen() || (meza_is_woocommerce_analytics_screen() && !meza_is_woocommerce_analytics_embed()))) {
        return;
    }

    echo '<style id="meza-woocommerce-analytics-nav-css">' .
        '.woocommerce_page_wc-admin #wpbody-content > .wrap.meza-woocommerce-analytics-nav-wrap:first-child,.woocommerce_page_meza-woocommerce-analytics #wpbody-content > .wrap.meza-woocommerce-analytics-nav-wrap:first-child{margin-top:0;}' .
        '.woocommerce_page_meza-woocommerce-analytics #wpbody,.woocommerce_page_meza-woocommerce-analytics #wpbody-content{background:#f0f0f1!important;}' .
        '.woocommerce_page_meza-woocommerce-analytics .wrap.meza-woocommerce-analytics-nav-wrap{margin:20px 20px 0 0;}' .
        '.meza-woocommerce-analytics-nav-wrap{margin:0;}' .
        '.meza-woocommerce-analytics-nav-wrap .nav-tab-wrapper,.woocommerce_page_wc-admin .wrap h2.nav-tab-wrapper,.woocommerce_page_wc-admin h1.nav-tab-wrapper,.woocommerce_page_meza-woocommerce-analytics .wrap h2.nav-tab-wrapper,.woocommerce_page_meza-woocommerce-analytics h1.nav-tab-wrapper{padding-top:0;}' .
        '.meza-woocommerce-analytics-nav-wrap .nav-tab-wrapper{display:flex;align-items:flex-end;gap:0;margin:0;border-bottom:1px solid #c3c4c7;padding-left:8px;}' .
        '.meza-woocommerce-analytics-nav-wrap .nav-tab{margin:0 6px -1px 0;border:1px solid #c3c4c7;border-bottom-color:#c3c4c7;background:#dcdcde;color:#50575e;font-weight:600;font-size:14px;line-height:1.71428571;padding:5px 10px;text-transform:capitalize;}' .
        '.meza-woocommerce-analytics-nav-wrap .nav-tab:hover{background:#fff;color:#1d2327;}' .
        '.meza-woocommerce-analytics-nav-wrap .nav-tab-active{background:#f0f0f1;border-bottom-color:#f0f0f1;color:#1d2327;}' .
        '.meza-woocommerce-analytics-nav-wrap .nav-tab:focus{box-shadow:none;outline:2px solid #2271b1;outline-offset:-2px;}' .
        '.woocommerce_page_meza-woocommerce-analytics #wpbody-content{padding-bottom:0;}' .
        '.woocommerce_page_meza-woocommerce-analytics .meza-woocommerce-analytics-embed-shell{margin:-8px 0 0;background:transparent;border:0;box-shadow:none;}' .
        '.woocommerce_page_meza-woocommerce-analytics .meza-woocommerce-analytics-iframe{display:block;width:100%;min-height:900px;border:0;background:transparent;}' .
        '@media screen and (max-width:782px){' .
        '.meza-woocommerce-analytics-nav-wrap{margin:0;overflow-x:auto;}' .
        '.meza-woocommerce-analytics-nav-wrap .nav-tab-wrapper{flex-wrap:nowrap;width:max-content;min-width:100%;padding:0 0 0 8px;}' .
        '.meza-woocommerce-analytics-nav-wrap .nav-tab{white-space:nowrap;padding:5px 10px;}' .
        '}' .
        '</style>';
    ?>
    <script id="meza-force-analytics-tab-navigation">
        (() => {
            const navigateToTab = (event) => {
                const link = event.target instanceof Element ? event.target.closest('a[data-meza-analytics-tab]') : null;
                if (!(link instanceof HTMLAnchorElement)) return;

                event.preventDefault();
                event.stopPropagation();
                window.location.assign(link.href);
            };

            document.addEventListener('click', navigateToTab, true);
        })();
    </script>
<?php
}, 1001);

add_action('admin_head', function (): void {
    if (!meza_is_woocommerce_analytics_embed()) {
        return;
    }

    echo '<style id="meza-woocommerce-analytics-embed-css">' .
        'html.wp-toolbar{padding-top:0!important;}' .
        '#wpadminbar,#adminmenumain,#wpfooter,#screen-meta-links,#screen-meta,.notice,.update-nag,.wrap.meza-woocommerce-analytics-nav-wrap{display:none!important;}' .
        '#wpcontent,#wpfooter{margin-left:0!important;padding-left:0!important;padding-right:0!important;}' .
        '#wpbody-content{padding:0!important;}' .
        'html,body.woocommerce_page_wc-admin,body.woocommerce_page_wc-admin #wpwrap,body.woocommerce_page_wc-admin #wpcontent,body.woocommerce_page_wc-admin #wpbody,body.woocommerce_page_wc-admin #wpbody-content{background:transparent!important;}' .
        'body.woocommerce_page_wc-admin .wrap{margin-top:20px!important;}' .
        'body.woocommerce_page_wc-admin.meza-wc-admin-analytics-embed .wrap{margin-top:30px!important;}' .
        '.woocommerce-layout,.woocommerce-layout__main,.woocommerce-layout__primary,.woocommerce-layout__content,.woocommerce-layout__activity-panel-content,.woocommerce-layout__header-wrapper{margin-left:0!important;padding-left:0!important;padding-right:0!important;}' .
        '.woocommerce-layout__main{padding-top:0!important;}' .
        '.woocommerce-layout__content{padding-top:0!important;margin-top:0!important;}' .
        '.woocommerce-layout__primary{margin-top:10px!important;}' .
        '</style>';
?>
    <script id="meza-woocommerce-analytics-embed-state">
        (() => {
            const sendState = () => {
                const url = new URL(window.location.href);
                window.parent.postMessage({
                    type: 'meza-wc-analytics-embed-state',
                    href: url.toString(),
                    path: url.searchParams.get('path') || '',
                    height: Math.max(
                        document.documentElement ? document.documentElement.scrollHeight : 0,
                        document.body ? document.body.scrollHeight : 0
                    ),
                }, window.location.origin);
            };

            const wrapHistoryMethod = (method) => {
                const original = history[method];
                if (typeof original !== 'function') return;

                history[method] = function(...args) {
                    const result = original.apply(this, args);
                    window.requestAnimationFrame(sendState);
                    return result;
                };
            };

            wrapHistoryMethod('pushState');
            wrapHistoryMethod('replaceState');
            window.addEventListener('popstate', sendState);
            window.addEventListener('load', sendState);
            window.addEventListener('resize', sendState);

            const observer = new MutationObserver(() => {
                window.requestAnimationFrame(sendState);
            });

            observer.observe(document.documentElement, {
                childList: true,
                subtree: true,
                attributes: true
            });

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', sendState, {
                    once: true
                });
            } else {
                sendState();
            }
        })();
    </script>
<?php
}, 1000);

add_filter('admin_body_class', function (string $classes): string {
    if (meza_is_woocommerce_analytics_embed()) {
        $classes .= ' meza-wc-admin-analytics-embed';
    }

    if (meza_get_wc_admin_path() === '/customers') {
        $classes .= ' meza-wc-admin-customers';
    }

    return trim($classes);
}, 1000);

add_filter('submenu_file', function ($submenu_file) {
    if (!meza_is_woocommerce_analytics_screen()) {
        return $submenu_file;
    }

    return meza_get_wc_admin_menu_slug('/analytics/overview');
}, PHP_INT_MAX);

add_action('admin_head', function (): void {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || $screen->id !== 'woocommerce_page_wc-settings') {
        return;
    }

    echo '<style id="meza-woocommerce-settings-tab-css">' .
        'body.woocommerce_page_wc-settings #wpbody,body.woocommerce_page_wc-settings #wpbody-content{background:#f0f0f1!important;}' .
        '.woocommerce_page_wc-settings .wrap.woocommerce{margin-top:0!important;}' .
        'body.woocommerce_page_wc-settings #mainform{padding-left:0!important;padding-right:0!important;}' .
        'body.woocommerce_page_wc-settings .nav-tab-wrapper{background:transparent!important;}' .
        '.woocommerce_page_wc-settings form#mainform > .nav-tab-wrapper.woo-nav-tab-wrapper{display:flex!important;align-items:flex-end!important;gap:0!important;margin:20px 0 22px!important;border-bottom:1px solid #c3c4c7!important;padding:0 0 0 8px!important;}' .
        '.woocommerce_page_wc-settings form#mainform > .nav-tab-wrapper.woo-nav-tab-wrapper .nav-tab{margin:0 6px -1px 0!important;border:1px solid #c3c4c7!important;border-bottom-color:#c3c4c7!important;background:#dcdcde!important;color:#50575e!important;font-weight:600!important;font-size:14px!important;line-height:1.71428571!important;padding:5px 10px!important;box-shadow:none!important;text-transform:capitalize!important;}' .
        '.woocommerce_page_wc-settings form#mainform > .nav-tab-wrapper.woo-nav-tab-wrapper .nav-tab:hover{background:#fff!important;color:#1d2327!important;}' .
        '.woocommerce_page_wc-settings form#mainform > .nav-tab-wrapper.woo-nav-tab-wrapper .nav-tab-active{background:#f0f0f1!important;border-bottom-color:#f0f0f1!important;color:#1d2327!important;}' .
        '.woocommerce_page_wc-settings form#mainform > .nav-tab-wrapper.woo-nav-tab-wrapper .nav-tab:focus{box-shadow:none!important;outline:2px solid #2271b1!important;outline-offset:-2px!important;}' .
        '@media screen and (max-width:782px){' .
        '.woocommerce_page_wc-settings .wrap.woocommerce{margin-top:0!important;}' .
        '.woocommerce_page_wc-settings form#mainform > .nav-tab-wrapper.woo-nav-tab-wrapper{margin:20px 0 18px!important;overflow-x:auto!important;flex-wrap:nowrap!important;padding:0 0 0 8px!important;}' .
        '.woocommerce_page_wc-settings form#mainform > .nav-tab-wrapper.woo-nav-tab-wrapper .nav-tab{white-space:nowrap!important;padding:5px 10px!important;}' .
        '}' .
        '</style>';
}, 1001);

add_action('admin_head', function (): void {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || $screen->id !== 'woocommerce_page_wc-status') {
        return;
    }

    echo '<style id="meza-woocommerce-status-tab-spacing">' .
        '.woocommerce_page_wc-status .wrap.woocommerce{margin-top:0!important;}' .
        '.woocommerce_page_wc-status .wrap.woocommerce > .nav-tab-wrapper.woo-nav-tab-wrapper{margin:20px 0 22px!important;padding:0!important;}' .
        '.woocommerce_page_wc-status .wrap.woocommerce > .nav-tab-wrapper.woo-nav-tab-wrapper .nav-tab{padding:5px 10px!important;font-size:14px!important;line-height:1.71428571!important;text-transform:capitalize!important;}' .
        '@media screen and (max-width:782px){' .
        '.woocommerce_page_wc-status .wrap.woocommerce > .nav-tab-wrapper.woo-nav-tab-wrapper{margin:20px 0 18px!important;overflow-x:auto!important;flex-wrap:nowrap!important;}' .
        '.woocommerce_page_wc-status .wrap.woocommerce > .nav-tab-wrapper.woo-nav-tab-wrapper .nav-tab{white-space:nowrap!important;padding:5px 10px!important;font-size:14px!important;line-height:1.71428571!important;text-transform:capitalize!important;}' .
        '}' .
        '</style>';
}, 1001);

add_action('admin_head', function (): void {
    if (!meza_is_woocommerce_headerless_wc_admin_screen()) {
        return;
    }

    echo '<style id="meza-hide-woocommerce-embedded-header">' .
        '#woocommerce-embedded-root,.woocommerce-layout__header,.woocommerce-layout__header-wrapper,.woocommerce-layout-header{display:none!important;}' .
        '.woocommerce_page_wc-admin.meza-wc-admin-customers #adminmenumain,' .
        '.woocommerce_page_wc-admin.meza-wc-admin-customers #adminmenuback,' .
        '.woocommerce_page_wc-admin.meza-wc-admin-customers #adminmenuwrap,' .
        '.woocommerce_page_wc-admin.meza-wc-admin-customers #adminmenu{' .
        'margin-top:0!important;padding-top:0!important;top:0!important;' .
        '}' .
        '.woocommerce_page_wc-admin.meza-wc-admin-customers #adminmenu #menu-dashboard{margin-top:2px!important;}' .
        '.woocommerce_page_wc-admin .wrap.meza-woocommerce-customers-title-wrap{margin:10px 20px 0 0!important;margin-left:0!important;padding:0!important;}' .
        '.woocommerce_page_wc-admin .wrap.meza-woocommerce-customers-title-wrap h1{margin:2px 0 0 2px;font-size:23px;line-height:1.3;font-weight:400;}' .
        '.woocommerce_page_wc-admin .woocommerce-layout__main{padding-right:0!important;}' .
        '.woocommerce_page_wc-admin .woocommerce-layout__primary{margin-left:0!important;margin-top:20px!important;}' .
        '.woocommerce_page_wc-admin.meza-wc-admin-customers .woocommerce-layout__primary{margin-top:8px!important;}' .
        '.woocommerce_page_wc-admin .woocommerce-filters-label{margin-top:0!important;}' .
        '.woocommerce_page_wc-admin .woocommerce-filters-filter{min-height:0!important;}' .
        '</style>';
?>
    <script id="meza-remove-woocommerce-embedded-header">
        (() => {
            const selectors = [
                '#woocommerce-embedded-root',
                '.woocommerce-layout__header',
                '.woocommerce-layout__header-wrapper',
                '.woocommerce-layout-header'
            ];

            const removeEmbeddedHeader = () => {
                document.querySelectorAll(selectors.join(',')).forEach((node) => {
                    if (node instanceof HTMLElement) {
                        node.remove();
                    }
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', removeEmbeddedHeader, {
                    once: true
                });
            } else {
                removeEmbeddedHeader();
            }

            const observer = new MutationObserver(() => removeEmbeddedHeader());
            observer.observe(document.documentElement, {
                childList: true,
                subtree: true
            });
        })();
    </script>
<?php
}, 1002);

add_action('admin_head', function (): void {
    if (!is_admin()) {
        return;
    }
?>
    <script id="meza-hide-top-level-analytics-menu">
        (() => {
            const hideTopLevelAnalyticsMenu = () => {
                document.querySelectorAll('#adminmenu > li > a').forEach((link) => {
                    if (!(link instanceof HTMLAnchorElement)) return;

                    const href = String(link.getAttribute('href') || '');
                    const isAnalyticsLink = href.includes('page=wc-admin') &&
                        (href.includes('/analytics/overview') || href.includes('%2Fanalytics%2Foverview'));

                    if (!isAnalyticsLink) return;

                    const menuItem = link.closest('#adminmenu > li');
                    if (menuItem instanceof HTMLElement) {
                        menuItem.style.display = 'none';
                    }
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', hideTopLevelAnalyticsMenu, {
                    once: true
                });
            } else {
                hideTopLevelAnalyticsMenu();
            }
        })();
    </script>
<?php
}, 1002);

add_action('admin_head', function (): void {
    if (!is_admin()) {
        return;
    }
?>
    <script id="meza-force-adminmenu-wc-admin-navigation">
        (() => {
            const navigateToAdminMenuLink = (event) => {
                const link = event.target instanceof Element ? event.target.closest('#adminmenu a[href*="page=wc-admin"]') : null;
                if (!(link instanceof HTMLAnchorElement)) return;

                event.preventDefault();
                event.stopPropagation();
                window.location.assign(link.href);
            };

            document.addEventListener('click', navigateToAdminMenuLink, true);
        })();
    </script>
<?php
}, 1003);

// Reset active top-level admin menu items back to standard WordPress styling.
add_action('admin_head', function (): void {
    if (!is_admin()) {
        return;
    }
?>
    <script id="meza-remove-active-adminmenu-inline-icon-styles">
        (() => {
            const removeActiveMenuIconStyles = () => {
                document.querySelectorAll(
                    '#adminmenu li.wp-has-current-submenu > a.wp-has-current-submenu .wp-menu-image,' +
                    '#adminmenu li.current > a.menu-top .wp-menu-image,' +
                    '#adminmenu li.wp-menu-open > a.menu-top .wp-menu-image'
                ).forEach((icon) => {
                    if (!(icon instanceof HTMLElement) || !icon.hasAttribute('style')) return;

                    icon.removeAttribute('style');
                });
            };

            const watchActiveMenuIconStyles = () => {
                const adminMenu = document.getElementById('adminmenu');
                if (!(adminMenu instanceof HTMLElement)) return;

                removeActiveMenuIconStyles();

                const observer = new MutationObserver(() => {
                    removeActiveMenuIconStyles();
                });

                observer.observe(adminMenu, {
                    subtree: true,
                    childList: true,
                    attributes: true,
                    attributeFilter: ['style', 'class']
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', watchActiveMenuIconStyles, {
                    once: true
                });
            } else {
                watchActiveMenuIconStyles();
            }

            window.addEventListener('load', removeActiveMenuIconStyles, {
                once: true
            });
        })();
    </script>
    <style id="meza-reset-active-top-level-admin-menu-style">
        #adminmenu li.wp-has-current-submenu > a.wp-has-current-submenu,
        #adminmenu li.current > a.menu-top,
        #adminmenu li.wp-menu-open > a.menu-top {
            background: var(--wp-admin-theme-color, #2271b1) !important;
            color: #fff !important;
            box-shadow: none !important;
            text-shadow: none !important;
            background-image: none !important;
        }

        #adminmenu li.wp-has-current-submenu > a.wp-has-current-submenu .wp-menu-image:before,
        #adminmenu li.current > a.menu-top .wp-menu-image:before,
        #adminmenu li.wp-menu-open > a.menu-top .wp-menu-image:before,
        #adminmenu li.wp-has-current-submenu > a.wp-has-current-submenu .wp-menu-name,
        #adminmenu li.current > a.menu-top .wp-menu-name,
        #adminmenu li.wp-menu-open > a.menu-top .wp-menu-name {
            color: #fff !important;
        }
    </style>
<?php
}, 1004);

add_action('in_admin_header', function (): void {
    if (!meza_is_woocommerce_analytics_screen() || meza_is_woocommerce_analytics_embed()) {
        return;
    }

    $current_path = meza_get_store_analytics_current_path();

    echo '<div class="wrap meza-woocommerce-analytics-nav-wrap"><nav class="nav-tab-wrapper" aria-label="Analytics">';

    foreach (meza_get_woocommerce_analytics_nav_items() as $item) {
        $path = (string) ($item['path'] ?? '');
        $label = (string) ($item['label'] ?? '');
        if ($path === '' || $label === '') continue;

        $classes = ['nav-tab'];
        if ($current_path === $path) {
            $classes[] = 'nav-tab-active';
        }

        printf(
            '<a class="%1$s" href="%2$s" data-meza-analytics-tab="1">%3$s</a>',
            esc_attr(implode(' ', $classes)),
            esc_url(meza_get_wc_admin_url($path)),
            esc_html($label)
        );
    }

    echo '</nav></div>';
}, 20);

add_action('in_admin_header', function (): void {
    if (meza_get_wc_admin_path() !== '/customers') {
        return;
    }

    echo '<div class="wrap meza-woocommerce-customers-title-wrap"><h1>Customers</h1></div>';
}, 21);

add_action('admin_head', function (): void {
    if (meza_is_woocommerce_analytics_screen() || !meza_is_woocommerce_headerless_wc_admin_screen()) {
        return;
    }

    echo '<style id="meza-hide-stale-woocommerce-analytics-nav">.meza-woocommerce-analytics-nav-wrap{display:none!important;}</style>';
?>
    <script id="meza-remove-stale-woocommerce-analytics-nav">
        (() => {
            const removeAnalyticsNav = () => {
                document.querySelectorAll('.meza-woocommerce-analytics-nav-wrap').forEach((node) => {
                    if (node instanceof HTMLElement) {
                        node.remove();
                    }
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', removeAnalyticsNav, {
                    once: true
                });
            } else {
                removeAnalyticsNav();
            }

            const observer = new MutationObserver(() => removeAnalyticsNav());
            observer.observe(document.documentElement, {
                childList: true,
                subtree: true
            });
        })();
    </script>
<?php
}, 1004);

add_action('admin_footer', function (): void {
    if (!meza_is_store_analytics_wrapper_screen()) {
        return;
    }
?>
    <script id="meza-store-analytics-wrapper-sync">
        (() => {
            const iframe = document.getElementById('meza-woocommerce-analytics-iframe');
            if (!(iframe instanceof HTMLIFrameElement)) return;

            const setActiveTab = (path) => {
                document.querySelectorAll('[data-meza-store-analytics-tab]').forEach((link) => {
                    if (!(link instanceof HTMLElement)) return;
                    link.classList.toggle('nav-tab-active', link.dataset.mezaStoreAnalyticsTab === path);
                });
            };

            window.addEventListener('message', (event) => {
                if (event.origin !== window.location.origin || !event.data || event.data.type !== 'meza-wc-analytics-embed-state') {
                    return;
                }

                const path = typeof event.data.path === 'string' ? event.data.path : '';
                const href = typeof event.data.href === 'string' ? event.data.href : '';
                const height = Number(event.data.height || 0);

                if (height > 0) {
                    iframe.style.height = `${Math.ceil(height)}px`;
                }

                if (!path.startsWith('/analytics/')) {
                    if (href !== '') {
                        window.location.assign(href);
                    }
                    return;
                }

                setActiveTab(path);

                const nextUrl = new URL(window.location.href);
                nextUrl.searchParams.set('analytics_path', path);
                window.history.replaceState({}, '', nextUrl.toString());
            });
        })();
    </script>
<?php
}, 1001);

function meza_reorder_woocommerce_submenu_items(): void
{
    global $submenu;

    $parent_slug = meza_get_woocommerce_submenu_parent_slug();

    if (!is_array($submenu) || !isset($submenu[$parent_slug]) || !is_array($submenu[$parent_slug])) {
        return;
    }

    $items = array_values($submenu[$parent_slug]);
    $ordered_buckets = [
        'orders' => null,
        'customers' => null,
        'coupons' => null,
        'analytics' => null,
        'status' => null,
        'settings' => null,
    ];
    $home_item = null;
    $remaining_items = [];

    foreach ($items as $item) {
        if (!is_array($item)) continue;

        $slug = strtolower((string) ($item[2] ?? ''));
        $label = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));

        $is_orders = $slug === 'wc-orders'
            || $slug === 'admin.php?page=wc-orders'
            || $slug === 'edit.php?post_type=shop_order'
            || str_contains($slug, 'post_type=shop_order');
        $is_customers = $slug === 'wc-admin&path=/customers'
            || $slug === 'admin.php?page=wc-admin&path=/customers'
            || str_contains($slug, '/customers');
        $is_coupon = $slug === 'edit.php?post_type=shop_coupon'
            || str_contains($slug, 'post_type=shop_coupon')
            || $slug === 'coupons-moved';
        $is_analytics = $slug === 'wc-admin&path=/analytics/overview'
            || $slug === 'admin.php?page=wc-admin&path=/analytics/overview'
            || $slug === 'meza-woocommerce-analytics'
            || $slug === 'admin.php?page=meza-woocommerce-analytics'
            || str_contains($slug, '/analytics/overview');
        $is_status = $slug === 'wc-status'
            || $slug === 'admin.php?page=wc-status'
            || str_contains($slug, 'page=wc-status')
            || $label === 'status';
        $is_settings = $slug === 'wc-settings'
            || $slug === 'admin.php?page=wc-settings'
            || str_contains($slug, 'page=wc-settings')
            || $label === 'settings';
        $is_home = $slug === 'wc-admin'
            || $slug === 'admin.php?page=wc-admin'
            || $slug === 'admin.php?page=wc-admin&path=/home'
            || str_contains($slug, 'wc-admin&path=/home')
            || $label === 'home';

        if ($is_orders) {
            if ($ordered_buckets['orders'] === null) {
                $ordered_buckets['orders'] = $item;
            }
            continue;
        }

        if ($is_customers) {
            if ($ordered_buckets['customers'] === null) {
                $ordered_buckets['customers'] = $item;
            }
            continue;
        }

        if ($is_coupon) {
            if ($slug !== 'coupons-moved' && $ordered_buckets['coupons'] === null) {
                $ordered_buckets['coupons'] = $item;
            }
            continue;
        }

        if ($is_analytics) {
            if ($ordered_buckets['analytics'] === null) {
                $ordered_buckets['analytics'] = $item;
            }
            continue;
        }

        if ($is_status) {
            if ($ordered_buckets['status'] === null) {
                $ordered_buckets['status'] = $item;
            }
            continue;
        }

        if ($is_settings) {
            if ($ordered_buckets['settings'] === null) {
                $ordered_buckets['settings'] = $item;
            }
            continue;
        }

        if ($is_home) {
            if ($home_item === null) {
                $home_item = $item;
            }
            continue;
        }

        $remaining_items[] = $item;
    }

    $reordered_items = array_values(array_filter($ordered_buckets, static function ($item): bool {
        return is_array($item);
    }));

    foreach ($remaining_items as $item) {
        $reordered_items[] = $item;
    }

    if (is_array($home_item)) {
        $reordered_items[] = $home_item;
    }

    $submenu[$parent_slug] = array_values($reordered_items);
}
add_action('admin_menu', 'meza_reorder_woocommerce_submenu_items', PHP_INT_MAX);

function meza_remove_payments_admin_menu(): void
{
    global $menu, $submenu;

    if (is_array($menu)) {
        foreach ($menu as $index => $item) {
            if (!is_array($item)) continue;

            $slug = strtolower((string) ($item[2] ?? ''));
            $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
            $is_payments_menu = $title === 'payments'
                || str_contains($slug, 'payments');

            if ($is_payments_menu) {
                unset($menu[$index]);
            }
        }

        $menu = array_values($menu);
    }

    if (is_array($submenu)) {
        foreach ($submenu as $parent_slug => &$items) {
            if (!is_array($items)) continue;

            $items = array_values(array_filter($items, static function ($item): bool {
                if (!is_array($item)) return true;

                $slug = strtolower((string) ($item[2] ?? ''));
                $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
                return $title !== 'payments' && !str_contains($slug, 'payments');
            }));
        }
        unset($items);
    }
}

// Remove Payments admin menu items globally, even after other plugins finish rebuilding menus.
add_action('admin_menu', 'meza_remove_payments_admin_menu', PHP_INT_MAX - 1);

function meza_remove_woocommerce_marketing_overview_submenu(): void
{
    global $submenu;

    remove_submenu_page('woocommerce-marketing', 'admin.php?page=wc-admin&path=/marketing/overview');
    remove_submenu_page('woocommerce-marketing', 'wc-admin&path=/marketing/overview');
    remove_submenu_page('admin.php?page=wc-admin&path=/marketing', 'admin.php?page=wc-admin&path=/marketing/overview');
    remove_submenu_page('admin.php?page=wc-admin&path=/marketing', 'wc-admin&path=/marketing/overview');
    remove_submenu_page('woocommerce', 'wc-addons');
    remove_submenu_page('woocommerce', 'admin.php?page=wc-addons');
    remove_submenu_page('woocommerce', 'wc-admin&path=/extensions');
    remove_submenu_page('woocommerce', 'admin.php?page=wc-admin&path=/extensions');
    remove_submenu_page('woocommerce', 'wc-reports');
    remove_submenu_page('woocommerce', 'admin.php?page=wc-reports');
    remove_submenu_page('woocommerce', 'coupons-moved');

    if (!is_array($submenu)) {
        return;
    }

    foreach ($submenu as $parent_slug => &$items) {
        if (!is_array($items)) continue;

        $normalized_parent_slug = strtolower((string) $parent_slug);
        $is_marketing_parent = $normalized_parent_slug === 'woocommerce-marketing'
            || str_contains($normalized_parent_slug, 'wc-admin&path=/marketing');
        $is_woocommerce_parent = $normalized_parent_slug === 'woocommerce';

        if (!$is_marketing_parent && !$is_woocommerce_parent) {
            continue;
        }

        $items = array_values(array_filter($items, static function ($item) use ($is_marketing_parent, $is_woocommerce_parent): bool {
            if (!is_array($item)) return true;

            $slug = strtolower((string) ($item[2] ?? ''));
            $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));

            $is_overview_slug = $slug === 'admin.php?page=wc-admin&path=/marketing/overview'
                || $slug === 'wc-admin&path=/marketing/overview'
                || str_contains($slug, '/marketing/overview');
            $is_extensions_slug = $slug === 'wc-addons'
                || $slug === 'admin.php?page=wc-addons'
                || $slug === 'wc-admin&path=/extensions'
                || $slug === 'admin.php?page=wc-admin&path=/extensions'
                || str_contains($slug, 'wc-addons')
                || str_contains($slug, 'wc-admin&path=/extensions');
            $is_reports_slug = $slug === 'wc-reports'
                || $slug === 'admin.php?page=wc-reports'
                || str_contains($slug, 'wc-reports');
            if ($is_marketing_parent && ($is_overview_slug || $title === 'overview')) {
                return false;
            }

            if ($is_woocommerce_parent && ($is_extensions_slug || preg_match('/^extensions\b/i', $title))) {
                return false;
            }

            if ($is_woocommerce_parent && ($is_reports_slug || $title === 'reports')) {
                return false;
            }

            return true;
        }));
    }
    unset($items);
}

add_action('admin_menu', 'meza_remove_woocommerce_marketing_overview_submenu', PHP_INT_MAX - 1);

// Keep WooCommerce's hidden wc-admin submenu registered for access checks, but hide Home visually.
add_action('admin_head', function (): void {
    if (!is_admin()) {
        return;
    }
?>
    <script id="meza-hide-woocommerce-home-submenu">
        (() => {
            const hideWooHomeSubmenu = () => {
                document.querySelectorAll('#adminmenu .wp-submenu a').forEach((link) => {
                    if (!(link instanceof HTMLAnchorElement)) return;

                    const href = String(link.getAttribute('href') || '');
                    const isWooHomeLink = href.includes('page=wc-admin') &&
                        !href.includes('path=');

                    if (!isWooHomeLink) return;

                    const menuItem = link.closest('li');
                    if (menuItem instanceof HTMLElement) {
                        menuItem.style.display = 'none';
                    }
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', hideWooHomeSubmenu, {
                    once: true
                });
            } else {
                hideWooHomeSubmenu();
            }
        })();
    </script>
<?php
}, 1003);

// Neutralize Smush's "last submenu item" upsell styling when promo items have been removed.
add_action('admin_head', function (): void {
    if (!is_admin()) {
        return;
    }
?>
    <style id="meza-reset-smush-submenu-style">
        #adminmenu #toplevel_page_smush .wp-submenu li:last-child a,
        #adminmenu #toplevel_page_smush .wp-submenu li:last-child a:visited,
        #adminmenu #menu-posts-tribe_events .wp-submenu li:last-child a,
        #adminmenu #menu-posts-tribe_events .wp-submenu li:last-child a:visited {
            background: transparent !important;
            color: #c3c4c7 !important;
            font-weight: 400 !important;
            white-space: normal !important;
        }

        #adminmenu #toplevel_page_smush .wp-submenu li:last-child a:hover,
        #adminmenu #toplevel_page_smush .wp-submenu li:last-child a:focus,
        #adminmenu #menu-posts-tribe_events .wp-submenu li:last-child a:hover,
        #adminmenu #menu-posts-tribe_events .wp-submenu li:last-child a:focus {
            background: transparent !important;
            color: #72aee6 !important;
        }
    </style>
    <script id="meza-reset-smush-submenu-target">
        (() => {
            const resetStyledLastSubmenuLinks = () => {
                const selectors = [
                    '#toplevel_page_smush .wp-submenu li:last-child a[href*="smush-settings"]',
                    '#menu-posts-tribe_events .wp-submenu li:last-child a[href*="tec-events-settings"]',
                ];

                selectors.forEach((selector) => {
                    const link = document.querySelector(selector);
                    if (!(link instanceof HTMLAnchorElement)) return;

                    link.removeAttribute('target');
                    link.removeAttribute('rel');
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', resetStyledLastSubmenuLinks, { once: true });
            } else {
                resetStyledLastSubmenuLinks();
            }
        })();
    </script>
<?php
}, 1004);

if (!function_exists('meza_is_locked_admin_chrome_screen')) {
    function meza_is_locked_admin_chrome_screen(): bool
    {
        if (!is_admin() || !function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (!($screen instanceof WP_Screen)) {
            return false;
        }

        return (string) $screen->post_type === 'tribe_events' && in_array((string) $screen->base, ['edit', 'post'], true);
    }
}

if (!function_exists('meza_is_whitelisted_admin_footer_screen')) {
    function meza_is_whitelisted_admin_footer_screen(): bool
    {
        return false;
    }
}

if (!function_exists('meza_is_whitelisted_admin_title_screen')) {
    function meza_is_whitelisted_admin_title_screen(): bool
    {
        return false;
    }
}

if (!function_exists('meza_should_lock_admin_footer')) {
    function meza_should_lock_admin_footer(): bool
    {
        return is_admin() && !meza_is_admin_chrome_exempt_screen() && !meza_is_whitelisted_admin_footer_screen();
    }
}

if (!function_exists('meza_should_lock_admin_title_chrome')) {
    function meza_should_lock_admin_title_chrome(): bool
    {
        return is_admin() && !meza_is_admin_chrome_exempt_screen() && !meza_is_whitelisted_admin_title_screen();
    }
}

if (!function_exists('meza_is_admin_chrome_exempt_screen')) {
    function meza_is_admin_chrome_exempt_screen(): bool
    {
        if (!is_admin()) {
            return false;
        }

        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen instanceof WP_Screen) {
                return (string) $screen->base === 'themes';
            }
        }

        $php_self = isset($_SERVER['PHP_SELF']) ? basename((string) $_SERVER['PHP_SELF']) : '';

        return $php_self === 'themes.php';
    }
}

if (!function_exists('meza_admin_footer_default_text')) {
    function meza_admin_footer_default_text(): string
    {
        return sprintf(
            '<span id="footer-thankyou">' . __('Thank you for creating with <a href="%s">WordPress</a>.') . '</span>',
            esc_url(__('https://wordpress.org/'))
        );
    }
}

if (!function_exists('meza_admin_footer_allowed_children_selectors')) {
    function meza_admin_footer_allowed_children_selectors(): array
    {
        return ['#footer-left', '#footer-upgrade', '.clear'];
    }
}

if (!function_exists('meza_admin_title_allowed_selectors')) {
    function meza_admin_title_allowed_selectors(): array
    {
        return [
            'h1.wp-heading-inline',
            '.page-title-action',
            '.subtitle',
            'hr.wp-header-end',
            '[data-meza-admin-chrome]',
            '.meza-admin-chrome',
        ];
    }
}

if (!function_exists('meza_admin_content_allowed_children_selectors')) {
    function meza_admin_content_allowed_children_selectors(): array
    {
        return [
            '#wpadminbar',
            '#wpbody',
            '#wpfooter',
            '.clear',
            '[data-meza-admin-chrome]',
            '.meza-admin-chrome',
        ];
    }
}

if (!function_exists('meza_should_force_admin_bar_in_admin')) {
    function meza_should_force_admin_bar_in_admin(): bool
    {
        if (!is_admin()) {
            return false;
        }

        if (function_exists('meza_is_woocommerce_analytics_embed') && meza_is_woocommerce_analytics_embed()) {
            return false;
        }

        return true;
    }
}

if (!function_exists('meza_should_buffer_admin_chrome_html')) {
    function meza_should_buffer_admin_chrome_html(): bool
    {
        if (!is_admin()) {
            return false;
        }

        if (meza_is_admin_chrome_exempt_screen()) {
            return false;
        }

        if ((function_exists('wp_doing_ajax') && wp_doing_ajax()) || (defined('DOING_AJAX') && DOING_AJAX)) {
            return false;
        }

        if (function_exists('wp_is_json_request') && wp_is_json_request()) {
            return false;
        }

        if ((defined('REST_REQUEST') && REST_REQUEST) || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)) {
            return false;
        }

        if ((defined('IFRAME_REQUEST') && IFRAME_REQUEST) || (defined('DOING_CRON') && DOING_CRON)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('meza_admin_wpwrap_allowed_selectors')) {
    function meza_admin_wpwrap_allowed_selectors(): array
    {
        return [
            '#wpadminbar',
            '#wpwrap',
            '.media-modal',
            '.media-modal-backdrop',
            'noscript',
            'style',
            'link',
            'svg',
            '#wp-auth-check-wrap',
            '[data-meza-admin-chrome]',
            '.meza-admin-chrome',
        ];
    }
}

if (!function_exists('meza_admin_wpbody_content_trailing_allowed_selectors')) {
    function meza_admin_wpbody_content_trailing_allowed_selectors(): array
    {
        return [
            '.clear',
            '#wp-auth-check-wrap',
            '[data-meza-admin-chrome]',
            '.meza-admin-chrome',
        ];
    }
}

if (!function_exists('meza_admin_get_env_preferred_plugin_signatures')) {
    function meza_admin_get_env_preferred_plugin_signatures(): array
    {
        if (!function_exists('mz_plugins_get_env_plugin_signatures')) {
            return [];
        }

        return array_values(array_filter(array_map('strtolower', mz_plugins_get_env_plugin_signatures()), static function ($signature): bool {
            return is_string($signature) && $signature !== '';
        }));
    }
}

if (!function_exists('meza_admin_get_disallowed_plugin_signatures')) {
    function meza_admin_get_disallowed_plugin_signatures(): array
    {
        if (!function_exists('mz_plugins_get_env_plugin_signatures')) {
            return [];
        }

        $preferred_signatures = array_fill_keys(meza_admin_get_env_preferred_plugin_signatures(), true);
        $plugin_files = (array) get_option('active_plugins', []);

        if (is_multisite()) {
            $network_active_plugins = get_site_option('active_sitewide_plugins', []);
            if (is_array($network_active_plugins)) {
                $plugin_files = array_merge($plugin_files, array_keys($network_active_plugins));
            }
        }

        $plugin_files = array_values(array_unique(array_filter(array_map('strval', $plugin_files))));
        $disallowed_signatures = [];

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = function_exists('get_plugins') ? (array) get_plugins() : [];

        foreach ($plugin_files as $plugin_file) {
            $plugin_file = strtolower(trim($plugin_file));
            if ($plugin_file === '') {
                continue;
            }

            $plugin_slug = dirname($plugin_file);
            if ($plugin_slug === '.' || $plugin_slug === '') {
                $plugin_slug = basename($plugin_file, '.php');
            }

            $plugin_name = strtolower(trim(wp_strip_all_tags((string) ($all_plugins[$plugin_file]['Name'] ?? ''))));
            $plugin_title = strtolower(trim(wp_strip_all_tags((string) ($all_plugins[$plugin_file]['Title'] ?? ''))));

            $tokens = array_filter([
                $plugin_slug,
                str_replace('-', '_', $plugin_slug),
                str_replace('-', '', $plugin_slug),
                $plugin_file,
                dirname($plugin_file) !== '.' ? dirname($plugin_file) : '',
                $plugin_name,
                str_replace(' ', '-', $plugin_name),
                str_replace(' ', '_', $plugin_name),
                str_replace([' ', '-'], '', $plugin_name),
                $plugin_title,
                str_replace(' ', '-', $plugin_title),
                str_replace(' ', '_', $plugin_title),
                str_replace([' ', '-'], '', $plugin_title),
            ], static function (string $token): bool {
                return $token !== '' && strlen($token) >= 4;
            });

            foreach ($tokens as $token) {
                if (isset($preferred_signatures[$token])) {
                    continue;
                }

                $disallowed_signatures[$token] = true;
            }
        }

        return array_keys($disallowed_signatures);
    }
}

if (!function_exists('meza_is_template_sensitive_admin_html')) {
    function meza_is_template_sensitive_admin_html(string $html): bool
    {
        $markers = [
            'class="themes-php',
            "class='themes-php",
            'id="tmpl-',
            "id='tmpl-",
            'id="tmpl-theme-single"',
            'id="tmpl-theme"',
            'type="text/template"',
            "type='text/template'",
            'type="text/html"',
            "type='text/html'",
            'wp.template(',
        ];

        foreach ($markers as $marker) {
            if (str_contains($html, $marker)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('meza_admin_is_core_template_script_id')) {
    function meza_admin_is_core_template_script_id(string $script_id): bool
    {
        $script_id = strtolower(trim($script_id));
        if ($script_id === '') {
            return false;
        }

        $allowed_prefixes = [
            'tmpl-theme',
            'tmpl-customize-',
            'tmpl-nav-menu-',
            'tmpl-available-menu-item',
            'tmpl-menu-item-',
            'tmpl-header-',
            'tmpl-media-',
            'tmpl-attachment',
            'tmpl-audio-details',
            'tmpl-video-details',
            'tmpl-image-',
            'tmpl-editor-gallery',
            'tmpl-gallery-settings',
            'tmpl-playlist-settings',
            'tmpl-embed-',
            'tmpl-crop-content',
            'tmpl-site-icon-preview-crop',
            'tmpl-uploader-',
            'tmpl-wp-playlist-',
            'tmpl-widget-',
            'tmpl-wp-media-widget-',
            'tmpl-wp-updates-',
            'tmpl-item-',
            'tmpl-community-events-',
            'tmpl-revisions-',
            'tmpl-health-check-issue',
            'tmpl-application-password-row',
            'tmpl-new-application-password',
            'tmpl-wp-file-editor-notice',
        ];

        foreach ($allowed_prefixes as $prefix) {
            if (str_starts_with($script_id, $prefix)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('meza_admin_is_allowed_after_wpwrap_element')) {
    function meza_admin_is_allowed_after_wpwrap_element(DOMElement $element, array $allowed_selectors): bool
    {
        if (meza_admin_dom_element_matches_any_selector($element, $allowed_selectors)) {
            return true;
        }

        if (strtolower($element->tagName) !== 'script') {
            return false;
        }

        $type = strtolower(trim((string) $element->getAttribute('type')));
        $script_id = (string) $element->getAttribute('id');

        if ($type === '' || in_array($type, ['text/javascript', 'application/javascript', 'module', 'importmap', 'speculationrules'], true)) {
            return true;
        }

        if (in_array($type, ['text/html', 'text/template'], true)) {
            return meza_admin_is_core_template_script_id($script_id);
        }

        return false;
    }
}

if (!function_exists('meza_admin_dom_element_has_class')) {
    function meza_admin_dom_element_has_class(DOMElement $element, string $class_name): bool
    {
        $classes = preg_split('/\s+/', trim((string) $element->getAttribute('class'))) ?: [];

        return in_array($class_name, $classes, true);
    }
}

if (!function_exists('meza_admin_dom_element_matches_selector')) {
    function meza_admin_dom_element_matches_selector(DOMElement $element, string $selector): bool
    {
        $selector = trim($selector);
        if ($selector === '') {
            return false;
        }

        if ($selector[0] === '#') {
            return $element->getAttribute('id') === substr($selector, 1);
        }

        if ($selector[0] === '.') {
            return meza_admin_dom_element_has_class($element, substr($selector, 1));
        }

        if ($selector[0] === '[' && substr($selector, -1) === ']') {
            return $element->hasAttribute(trim($selector, '[]'));
        }

        if (str_contains($selector, '.')) {
            [$tag_name, $class_name] = array_pad(explode('.', $selector, 2), 2, '');

            return strtolower($element->tagName) === strtolower($tag_name)
                && meza_admin_dom_element_has_class($element, $class_name);
        }

        return strtolower($element->tagName) === strtolower($selector);
    }
}

if (!function_exists('meza_admin_dom_element_matches_any_selector')) {
    function meza_admin_dom_element_matches_any_selector(DOMElement $element, array $selectors): bool
    {
        foreach ($selectors as $selector) {
            if (meza_admin_dom_element_matches_selector($element, (string) $selector)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('meza_admin_dom_element_attribute_haystack')) {
    function meza_admin_dom_element_attribute_haystack(DOMElement $element): string
    {
        $parts = [strtolower($element->tagName)];

        if ($element->hasAttributes()) {
            foreach ($element->attributes as $attribute) {
                if (!($attribute instanceof DOMAttr)) {
                    continue;
                }

                $parts[] = strtolower($attribute->name);
                $parts[] = strtolower((string) $attribute->value);
            }
        }

        return implode(' ', $parts);
    }
}

if (!function_exists('meza_admin_dom_element_matches_plugin_signatures')) {
    function meza_admin_dom_element_matches_plugin_signatures(DOMElement $element, array $signatures): bool
    {
        if ($signatures === []) {
            return false;
        }

        $haystack = meza_admin_dom_element_attribute_haystack($element);

        foreach ($signatures as $signature) {
            $signature = strtolower(trim((string) $signature));
            if ($signature === '') {
                continue;
            }

            if (str_contains($haystack, $signature)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('meza_admin_dom_get_element_children')) {
    function meza_admin_dom_get_element_children(DOMElement $element): array
    {
        $children = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children[] = $child;
            }
        }

        return $children;
    }
}

if (!function_exists('meza_admin_dom_get_next_element_sibling')) {
    function meza_admin_dom_get_next_element_sibling(DOMNode $node): ?DOMElement
    {
        $sibling = $node->nextSibling;

        while ($sibling !== null && !($sibling instanceof DOMElement)) {
            $sibling = $sibling->nextSibling;
        }

        return $sibling instanceof DOMElement ? $sibling : null;
    }
}

if (!function_exists('meza_admin_dom_unwrap_element')) {
    function meza_admin_dom_unwrap_element(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if (!($parent instanceof DOMNode)) {
            return;
        }

        while ($element->firstChild instanceof DOMNode) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}

if (!function_exists('meza_admin_dom_element_contains_node')) {
    function meza_admin_dom_element_contains_node(DOMElement $ancestor, DOMNode $node): bool
    {
        $parent = $node->parentNode;

        while ($parent instanceof DOMNode) {
            if ($parent->isSameNode($ancestor)) {
                return true;
            }

            $parent = $parent->parentNode;
        }

        return false;
    }
}

if (!function_exists('meza_admin_dom_replace_inner_html')) {
    function meza_admin_dom_replace_inner_html(DOMDocument $document, DOMElement $element, string $html): void
    {
        while ($element->firstChild instanceof DOMNode) {
            $element->removeChild($element->firstChild);
        }

        $fragment = new DOMDocument('1.0', 'UTF-8');
        $wrapper_id = '__meza-admin-fragment';
        $flags = defined('LIBXML_HTML_NOIMPLIED') && defined('LIBXML_HTML_NODEFDTD')
            ? LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            : 0;

        @$fragment->loadHTML('<div id="' . $wrapper_id . '">' . $html . '</div>', $flags);

        $container = $fragment->getElementById($wrapper_id);
        if (!($container instanceof DOMElement)) {
            return;
        }

        foreach (iterator_to_array($container->childNodes) as $child) {
            $element->appendChild($document->importNode($child, true));
        }
    }
}

if (!function_exists('meza_admin_dom_element_or_descendant_matches_any_selector')) {
    function meza_admin_dom_element_or_descendant_matches_any_selector(DOMElement $element, array $selectors): bool
    {
        if (meza_admin_dom_element_matches_any_selector($element, $selectors)) {
            return true;
        }

        $stack = [$element];

        while ($stack !== []) {
            /** @var DOMElement $node */
            $node = array_pop($stack);

            foreach ($node->childNodes as $child) {
                if (!($child instanceof DOMElement)) {
                    continue;
                }

                if (meza_admin_dom_element_matches_any_selector($child, $selectors)) {
                    return true;
                }

                $stack[] = $child;
            }
        }

        return false;
    }
}

if (!function_exists('meza_sanitize_admin_chrome_html')) {
    function meza_sanitize_admin_chrome_html(string $html): string
    {
        if (trim($html) === '' || !str_contains($html, 'id="wpcontent"')) {
            return $html;
        }

        if (meza_is_template_sensitive_admin_html($html)) {
            return $html;
        }

        if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
            return $html;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $use_internal_errors = libxml_use_internal_errors(true);
        $loaded = @$document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($use_internal_errors);

        if (!$loaded) {
            return $html;
        }

        $xpath = new DOMXPath($document);
        $content_selectors = meza_admin_content_allowed_children_selectors();
        $footer_selectors = meza_admin_footer_allowed_children_selectors();
        $title_selectors = meza_admin_title_allowed_selectors();
        $wpwrap_selectors = meza_admin_wpwrap_allowed_selectors();
        $wpbody_content_trailing_selectors = meza_admin_wpbody_content_trailing_allowed_selectors();
        $disallowed_plugin_signatures = meza_admin_get_disallowed_plugin_signatures();
        $bridge_stop_selectors = [
            'h2.screen-reader-text',
            '.subsubsub',
            'form',
            '.tablenav',
            '.wp-list-table',
            '#ajax-response',
            '.clear',
        ];

        $wpcontent = $document->getElementById('wpcontent');
        if ($wpcontent instanceof DOMElement) {
            foreach (meza_admin_dom_get_element_children($wpcontent) as $child) {
                if (!meza_admin_dom_element_matches_any_selector($child, $content_selectors)) {
                    $wpcontent->removeChild($child);
                }
            }
        }

        $wpbody_content = $document->getElementById('wpbody-content');
        if ($wpbody_content instanceof DOMElement) {
            $seen_main_wrap = false;

            foreach (meza_admin_dom_get_element_children($wpbody_content) as $child) {
                if (meza_admin_dom_element_has_class($child, 'wrap')) {
                    $seen_main_wrap = true;
                    continue;
                }

                if (!$seen_main_wrap) {
                    continue;
                }

                if (meza_admin_is_allowed_after_wpwrap_element($child, $wpbody_content_trailing_selectors)) {
                    continue;
                }

                if ($child->parentNode instanceof DOMNode) {
                    $child->parentNode->removeChild($child);
                }
            }
        }

        $body = $document->getElementsByTagName('body')->item(0);
        if ($body instanceof DOMElement) {
            $seen_wpwrap = false;

            foreach (meza_admin_dom_get_element_children($body) as $child) {
                if ($child->getAttribute('id') === 'wpwrap') {
                    $seen_wpwrap = true;
                    continue;
                }

                if (!$seen_wpwrap) {
                    continue;
                }

                if (!meza_admin_is_allowed_after_wpwrap_element($child, $wpwrap_selectors)
                    && meza_admin_dom_element_matches_plugin_signatures($child, $disallowed_plugin_signatures)) {
                    $body->removeChild($child);
                    continue;
                }

                if (!meza_admin_is_allowed_after_wpwrap_element($child, $wpwrap_selectors)
                    && strtolower($child->tagName) !== 'script') {
                    $body->removeChild($child);
                    continue;
                }

                if (strtolower($child->tagName) === 'script'
                    && !meza_admin_is_allowed_after_wpwrap_element($child, $wpwrap_selectors)
                    && in_array(strtolower(trim((string) $child->getAttribute('type'))), ['text/html', 'text/template'], true)) {
                    $body->removeChild($child);
                }
            }
        }

        $wpfooter = $document->getElementById('wpfooter');
        if ($wpfooter instanceof DOMElement) {
            $footer_left = $document->getElementById('footer-left');
            if ($footer_left instanceof DOMElement) {
                meza_admin_dom_replace_inner_html($document, $footer_left, meza_admin_footer_default_text());
            }

            $footer_upgrade = $document->getElementById('footer-upgrade');
            if ($footer_upgrade instanceof DOMElement) {
                $footer_upgrade_html = function_exists('core_update_footer')
                    ? (string) core_update_footer('')
                    : sprintf(__('Version %s'), esc_html((string) get_bloginfo('version', 'display')));
                meza_admin_dom_replace_inner_html($document, $footer_upgrade, $footer_upgrade_html);
            }

            foreach (meza_admin_dom_get_element_children($wpfooter) as $child) {
                if (!meza_admin_dom_element_matches_any_selector($child, $footer_selectors)) {
                    $wpfooter->removeChild($child);
                }
            }
        }

        foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " ian-client ") or contains(concat(" ", normalize-space(@class), " "), " ian-sidebar ")]') ?: [] as $node) {
            if (!($node instanceof DOMElement)) {
                continue;
            }

            if (meza_admin_dom_element_matches_any_selector($node, ['[data-meza-admin-chrome]', '.meza-admin-chrome'])) {
                continue;
            }

            if ($node->parentNode instanceof DOMNode) {
                $node->parentNode->removeChild($node);
            }
        }

        foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " wrap ")]') ?: [] as $wrap) {
            if (!($wrap instanceof DOMElement)) {
                continue;
            }

            $heading = null;
            foreach ($xpath->query('.//h1[contains(concat(" ", normalize-space(@class), " "), " wp-heading-inline ")]', $wrap) ?: [] as $node) {
                if ($node instanceof DOMElement) {
                    $heading = $node;
                    break;
                }
            }

            $header_end = null;
            foreach ($xpath->query('.//hr[contains(concat(" ", normalize-space(@class), " "), " wp-header-end ")]', $wrap) ?: [] as $node) {
                if ($node instanceof DOMElement) {
                    $header_end = $node;
                    break;
                }
            }

            if (!($heading instanceof DOMElement) || !($header_end instanceof DOMElement)) {
                continue;
            }

            $parent = $heading->parentNode;
            while ($parent instanceof DOMElement && $parent !== $wrap && !meza_admin_dom_element_matches_any_selector($parent, $title_selectors)) {
                $next_parent = $parent->parentNode;
                meza_admin_dom_unwrap_element($parent);
                $parent = $next_parent instanceof DOMElement ? $next_parent : null;
            }

            $in_title_region = false;
            foreach (meza_admin_dom_get_element_children($wrap) as $child) {
                if (!$in_title_region) {
                    if ($child === $heading || $child->isSameNode($heading) || meza_admin_dom_element_contains_node($child, $heading)) {
                        $in_title_region = true;
                    } else {
                        continue;
                    }
                }

                if ($child === $header_end || $child->isSameNode($header_end)) {
                    break;
                }

                if (meza_admin_dom_element_matches_any_selector($child, $title_selectors)) {
                    continue;
                }

                if (meza_admin_dom_element_or_descendant_matches_any_selector($child, $title_selectors)) {
                    meza_admin_dom_unwrap_element($child);
                    continue;
                }

                if ($child->parentNode instanceof DOMNode) {
                    $child->parentNode->removeChild($child);
                }
            }

            $bridge_node = meza_admin_dom_get_next_element_sibling($header_end);
            while ($bridge_node instanceof DOMElement) {
                if (meza_admin_dom_element_matches_any_selector($bridge_node, $bridge_stop_selectors)) {
                    break;
                }

                if (meza_admin_dom_element_or_descendant_matches_any_selector($bridge_node, $bridge_stop_selectors)) {
                    meza_admin_dom_unwrap_element($bridge_node);
                    $bridge_node = meza_admin_dom_get_next_element_sibling($header_end);
                    continue;
                }

                $next_node = meza_admin_dom_get_next_element_sibling($bridge_node);

                if (!meza_admin_dom_element_matches_any_selector($bridge_node, ['[data-meza-admin-chrome]', '.meza-admin-chrome'])) {
                    if ($bridge_node->parentNode instanceof DOMNode) {
                        $bridge_node->parentNode->removeChild($bridge_node);
                    }
                }

                $bridge_node = $next_node;
            }
        }

        return (string) $document->saveHTML();
    }
}

// Sanitize admin chrome server-side so plugin-injected wrappers never paint on first render.
add_action('admin_init', function (): void {
    static $buffer_started = false;

    if ($buffer_started || !meza_should_buffer_admin_chrome_html()) {
        return;
    }

    ob_start(static function (string $html): string {
        return meza_sanitize_admin_chrome_html($html);
    });

    $buffer_started = true;
}, 0);

// Keep protected admin screens on the default WordPress header/footer chrome.
add_filter('tec_common_ian_show_icon', function ($show): bool {
    if (meza_is_locked_admin_chrome_screen()) {
        return false;
    }

    return (bool) $show;
}, 1000);

add_filter('show_admin_bar', function ($show): bool {
    if (meza_should_force_admin_bar_in_admin()) {
        return true;
    }

    return (bool) $show;
}, PHP_INT_MAX);

add_action('admin_enqueue_scripts', function (): void {
    if (!meza_is_locked_admin_chrome_screen()) {
        return;
    }

    wp_dequeue_style('ian-client-css');
    wp_dequeue_script('ian-client-js');
}, PHP_INT_MAX);

add_filter('admin_footer_text', function ($text): string {
    if (!meza_should_lock_admin_footer()) {
        return (string) $text;
    }

    return meza_admin_footer_default_text();
}, PHP_INT_MAX);

add_filter('update_footer', function ($content): string {
    if (!meza_should_lock_admin_footer()) {
        return (string) $content;
    }

    if (function_exists('core_update_footer')) {
        return (string) core_update_footer('');
    }

    global $wp_version;

    return sprintf(__('Version %s'), esc_html((string) $wp_version));
}, PHP_INT_MAX);

add_action('admin_head', function (): void {
    if (is_admin()) {
        $lock_core_admin_content = !meza_is_admin_chrome_exempt_screen();
        $content_selectors = meza_admin_content_allowed_children_selectors();
        $wpwrap_selectors = meza_admin_wpwrap_allowed_selectors();
        $wpbodyContentTrailingSelectors = meza_admin_wpbody_content_trailing_allowed_selectors();
        $disallowed_plugin_signatures = meza_admin_get_disallowed_plugin_signatures();
        $content_deny_selector = '#wpcontent > *';

        foreach ($content_selectors as $selector) {
            $content_deny_selector .= ':not(' . $selector . ')';
        }
?>
    <?php if ($lock_core_admin_content) : ?>
    <style id="meza-lock-admin-content">
        <?php echo $content_deny_selector; ?> {
            display: none !important;
        }
    </style>
    <?php endif; ?>
    <?php if (meza_should_force_admin_bar_in_admin()) : ?>
    <style id="meza-force-admin-bar-visible">
        html.wp-toolbar {
            padding-top: var(--wp-admin--admin-bar--height) !important;
        }

        #wpadminbar {
            display: block !important;
        }
    </style>
    <?php endif; ?>
    <script id="meza-lock-admin-content-script">
        (() => {
            const lockCoreAdminContent = <?php echo wp_json_encode($lock_core_admin_content); ?>;
            const allowedSelectors = <?php echo wp_json_encode($content_selectors); ?>;
            const wpwrapAllowedSelectors = <?php echo wp_json_encode($wpwrap_selectors); ?>;
            const wpbodyContentTrailingSelectors = <?php echo wp_json_encode($wpbodyContentTrailingSelectors); ?>;
            const disallowedPluginSignatures = <?php echo wp_json_encode($disallowed_plugin_signatures); ?>;
            const protectedSpacingTargets = [
                document.documentElement,
                document.body,
                document.getElementById('wpwrap'),
                document.getElementById('wpcontent'),
                document.getElementById('wpbody'),
                document.getElementById('wpbody-content'),
            ].filter((element) => element instanceof HTMLElement);

            const isAllowed = (element) => {
                if (!(element instanceof Element)) return false;
                return allowedSelectors.some((selector) => element.matches(selector));
            };

            const isAllowedAfterWpwrap = (element) => {
                if (!(element instanceof Element)) return false;
                return wpwrapAllowedSelectors.some((selector) => element.matches(selector));
            };

            const isCoreTemplateScriptId = (id) => {
                const value = String(id || '').toLowerCase().trim();
                if (!value) return false;

                const allowedPrefixes = [
                    'tmpl-theme',
                    'tmpl-customize-',
                    'tmpl-nav-menu-',
                    'tmpl-available-menu-item',
                    'tmpl-menu-item-',
                    'tmpl-header-',
                    'tmpl-media-',
                    'tmpl-attachment',
                    'tmpl-audio-details',
                    'tmpl-video-details',
                    'tmpl-image-',
                    'tmpl-editor-gallery',
                    'tmpl-gallery-settings',
                    'tmpl-playlist-settings',
                    'tmpl-embed-',
                    'tmpl-crop-content',
                    'tmpl-site-icon-preview-crop',
                    'tmpl-uploader-',
                    'tmpl-wp-playlist-',
                    'tmpl-widget-',
                    'tmpl-wp-media-widget-',
                    'tmpl-wp-updates-',
                    'tmpl-item-',
                    'tmpl-community-events-',
                    'tmpl-revisions-',
                    'tmpl-health-check-issue',
                    'tmpl-application-password-row',
                    'tmpl-new-application-password',
                    'tmpl-wp-file-editor-notice',
                ];

                return allowedPrefixes.some((prefix) => value.startsWith(prefix));
            };

            const isAllowedAfterWpwrapElement = (element) => {
                if (!(element instanceof Element)) return false;
                if (isAllowedAfterWpwrap(element)) return true;
                if (element.tagName.toLowerCase() !== 'script') return false;

                const type = String(element.getAttribute('type') || '').toLowerCase().trim();

                if (!type || ['text/javascript', 'application/javascript', 'module', 'importmap', 'speculationrules'].includes(type)) {
                    return true;
                }

                if (['text/html', 'text/template'].includes(type)) {
                    return isCoreTemplateScriptId(element.id);
                }

                return false;
            };

            const isAllowedAfterWpwrapElementForSelectors = (element, selectors) => {
                if (!(element instanceof Element)) return false;
                if (Array.isArray(selectors) && selectors.some((selector) => element.matches(selector))) return true;
                if (element.tagName.toLowerCase() !== 'script') return false;

                const type = String(element.getAttribute('type') || '').toLowerCase().trim();

                if (!type || ['text/javascript', 'application/javascript', 'module', 'importmap', 'speculationrules'].includes(type)) {
                    return true;
                }

                if (['text/html', 'text/template'].includes(type)) {
                    return isCoreTemplateScriptId(element.id);
                }

                return false;
            };

            const matchesPluginSignatures = (element, signatures) => {
                if (!(element instanceof Element) || !Array.isArray(signatures) || signatures.length === 0) return false;

                const haystack = Array.from(element.attributes || [])
                    .map((attribute) => `${attribute.name} ${attribute.value}`.toLowerCase())
                    .join(' ');

                return signatures.some((signature) => haystack.includes(String(signature).toLowerCase()));
            };

            const cleanupContent = () => {
                if (!lockCoreAdminContent) return;

                const content = document.getElementById('wpcontent');
                if (!(content instanceof HTMLElement)) return;

                Array.from(content.children).forEach((child) => {
                    if (!isAllowed(child)) {
                        child.remove();
                    }
                });
            };

            const cleanupAfterWpwrap = () => {
                if (!(document.body instanceof HTMLBodyElement)) return;

                let seenWpwrap = false;
                Array.from(document.body.children).forEach((child) => {
                    if (!(child instanceof HTMLElement)) return;

                    if (child.id === 'wpwrap') {
                        seenWpwrap = true;
                        return;
                    }

                    if (!seenWpwrap) return;
                    if (isAllowedAfterWpwrapElement(child)) return;

                    if (child.tagName.toLowerCase() !== 'script') {
                        child.remove();
                        return;
                    }

                    const type = String(child.getAttribute('type') || '').toLowerCase().trim();
                    if (['text/html', 'text/template'].includes(type)) {
                        child.remove();
                        return;
                    }

                    if (!matchesPluginSignatures(child, disallowedPluginSignatures)) return;

                    child.remove();
                });
            };

            const cleanupAfterMainWrap = () => {
                const bodyContent = document.getElementById('wpbody-content');
                if (!(bodyContent instanceof HTMLElement)) return;

                let seenMainWrap = false;
                Array.from(bodyContent.children).forEach((child) => {
                    if (!(child instanceof HTMLElement)) return;

                    if (child.classList.contains('wrap')) {
                        seenMainWrap = true;
                        return;
                    }

                    if (!seenMainWrap) return;
                    if (isAllowedAfterWpwrapElementForSelectors(child, wpbodyContentTrailingSelectors)) return;

                    child.remove();
                });
            };

            const cleanupInjectedTopSpacing = () => {
                const adminBar = document.getElementById('wpadminbar');
                const adminBarVisible = adminBar instanceof HTMLElement
                    && adminBar.offsetParent !== null
                    && adminBar.getBoundingClientRect().height > 0;

                if (!adminBarVisible) return;

                protectedSpacingTargets.forEach((element) => {
                    if (!(element instanceof HTMLElement)) return;

                    element.style.removeProperty('margin-top');
                    element.style.removeProperty('padding-top');
                    element.style.removeProperty('top');
                });
            };

            cleanupContent();
            cleanupAfterWpwrap();
            cleanupAfterMainWrap();
            cleanupInjectedTopSpacing();

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', cleanupContent, { once: true });
                document.addEventListener('DOMContentLoaded', cleanupAfterWpwrap, { once: true });
                document.addEventListener('DOMContentLoaded', cleanupAfterMainWrap, { once: true });
                document.addEventListener('DOMContentLoaded', cleanupInjectedTopSpacing, { once: true });
            } else {
                window.addEventListener('load', cleanupContent, { once: true });
                window.addEventListener('load', cleanupAfterWpwrap, { once: true });
                window.addEventListener('load', cleanupAfterMainWrap, { once: true });
                window.addEventListener('load', cleanupInjectedTopSpacing, { once: true });
            }

            const content = document.getElementById('wpcontent');
            if (content instanceof HTMLElement) {
                const observer = new MutationObserver(() => {
                    cleanupContent();
                    cleanupAfterWpwrap();
                    cleanupAfterMainWrap();
                    cleanupInjectedTopSpacing();
                });
                observer.observe(content, { childList: true });
            }

            const bodyContent = document.getElementById('wpbody-content');
            if (bodyContent instanceof HTMLElement) {
                const bodyContentObserver = new MutationObserver(cleanupAfterMainWrap);
                bodyContentObserver.observe(bodyContent, { childList: true });
            }

            protectedSpacingTargets.forEach((element) => {
                const observer = new MutationObserver(cleanupInjectedTopSpacing);
                observer.observe(element, { attributes: true, attributeFilter: ['style', 'class'] });
            });

            if (document.body instanceof HTMLElement) {
                const bodyObserver = new MutationObserver(() => {
                    cleanupAfterWpwrap();
                    cleanupAfterMainWrap();
                    cleanupInjectedTopSpacing();
                });
                bodyObserver.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class'] });
            }

            window.addEventListener('resize', cleanupInjectedTopSpacing);
        })();
    </script>
<?php
    }

    if (meza_should_lock_admin_footer()) {
        $selectors = meza_admin_footer_allowed_children_selectors();
        $deny_selector = '#wpfooter > *';
        $footer_left_html = meza_admin_footer_default_text();
        $footer_upgrade_html = function_exists('core_update_footer')
            ? (string) core_update_footer('')
            : sprintf(__('Version %s'), esc_html((string) get_bloginfo('version', 'display')));

        foreach ($selectors as $selector) {
            $deny_selector .= ':not(' . $selector . ')';
        }
?>
    <style id="meza-lock-admin-footer">
        <?php echo $deny_selector; ?> {
            display: none !important;
        }
    </style>
    <script id="meza-lock-admin-footer-script">
        (() => {
            const allowedSelectors = <?php echo wp_json_encode($selectors); ?>;
            const expectedFooterLeft = <?php echo wp_json_encode($footer_left_html); ?>;
            const expectedFooterUpgrade = <?php echo wp_json_encode($footer_upgrade_html); ?>;

            const isAllowed = (element) => {
                if (!(element instanceof Element)) return false;
                return allowedSelectors.some((selector) => element.matches(selector));
            };

            const cleanupFooter = () => {
                const footer = document.getElementById('wpfooter');
                if (!(footer instanceof HTMLElement)) return;

                const footerLeft = footer.querySelector('#footer-left');
                if (footerLeft instanceof HTMLElement && footerLeft.innerHTML.trim() !== expectedFooterLeft.trim()) {
                    footerLeft.innerHTML = expectedFooterLeft;
                }

                const footerUpgrade = footer.querySelector('#footer-upgrade');
                if (footerUpgrade instanceof HTMLElement && footerUpgrade.innerHTML.trim() !== expectedFooterUpgrade.trim()) {
                    footerUpgrade.innerHTML = expectedFooterUpgrade;
                }

                Array.from(footer.children).forEach((child) => {
                    if (!isAllowed(child)) {
                        child.remove();
                    }
                });
            };

            cleanupFooter();

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', cleanupFooter, { once: true });
            } else {
                window.addEventListener('load', cleanupFooter, { once: true });
            }

            const footer = document.getElementById('wpfooter');
            if (!(footer instanceof HTMLElement)) return;

            const observer = new MutationObserver(cleanupFooter);
            observer.observe(footer, { childList: true });
        })();
    </script>
<?php
    }

    if (meza_should_lock_admin_title_chrome()) {
        $allowed_title_selectors = meza_admin_title_allowed_selectors();
?>
    <script id="meza-lock-admin-title-chrome-script">
        (() => {
            const allowedSelectors = <?php echo wp_json_encode($allowed_title_selectors); ?>;
            const listUiStartSelector = [
                'h2.screen-reader-text',
                '.subsubsub',
                'form',
                '.tablenav',
                '.wp-list-table',
                '#ajax-response',
                '.clear',
            ].join(', ');

            const isAllowed = (element) => {
                if (!(element instanceof Element)) return false;
                return allowedSelectors.some((selector) => element.matches(selector));
            };

            const unwrap = (element) => {
                if (!(element instanceof HTMLElement) || !(element.parentNode instanceof Node)) return;

                while (element.firstChild) {
                    element.parentNode.insertBefore(element.firstChild, element);
                }

                element.remove();
            };

            const cleanupTitleChrome = () => {
                document.querySelectorAll('.ian-client, .ian-sidebar').forEach((element) => {
                    if (element instanceof HTMLElement && !element.matches('[data-meza-admin-chrome], .meza-admin-chrome')) {
                        element.remove();
                    }
                });

                document.querySelectorAll('.wrap').forEach((wrap) => {
                    if (!(wrap instanceof HTMLElement)) return;

                    const heading = wrap.querySelector('h1.wp-heading-inline');
                    const headerEnd = wrap.querySelector('hr.wp-header-end');

                    if (!(heading instanceof HTMLElement) || !(headerEnd instanceof HTMLElement)) {
                        return;
                    }

                    let parent = heading.parentElement;
                    while (parent instanceof HTMLElement && parent !== wrap && !isAllowed(parent)) {
                        unwrap(parent);
                        parent = heading.parentElement;
                    }

                    let inTitleRegion = false;
                    for (const child of Array.from(wrap.children)) {
                        if (!(child instanceof HTMLElement)) continue;

                        if (!inTitleRegion) {
                            if (child === heading || child.contains(heading)) {
                                inTitleRegion = true;
                            } else {
                                continue;
                            }
                        }

                        if (child === headerEnd) {
                            break;
                        }

                        if (isAllowed(child)) {
                            continue;
                        }

                        const nestedAllowed = Array.from(child.querySelectorAll('*')).some((node) => isAllowed(node));
                        if (nestedAllowed) {
                            unwrap(child);
                            continue;
                        }

                        child.remove();
                    }

                    let bridgeNode = headerEnd.nextElementSibling;
                    while (bridgeNode instanceof HTMLElement) {
                        if (bridgeNode.matches(listUiStartSelector)) {
                            break;
                        }

                        if (bridgeNode.querySelector(listUiStartSelector)) {
                            unwrap(bridgeNode);
                            bridgeNode = headerEnd.nextElementSibling;
                            continue;
                        }

                        const nextNode = bridgeNode.nextElementSibling;

                        if (!bridgeNode.matches('[data-meza-admin-chrome], .meza-admin-chrome')) {
                            bridgeNode.remove();
                        }

                        bridgeNode = nextNode;
                    }
                });
            };

            let frame = null;
            const scheduleCleanup = () => {
                if (frame !== null) return;

                frame = window.requestAnimationFrame(() => {
                    frame = null;
                    cleanupTitleChrome();
                });
            };

            cleanupTitleChrome();

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', cleanupTitleChrome, { once: true });
            } else {
                window.addEventListener('load', cleanupTitleChrome, { once: true });
            }

            const observer = new MutationObserver(scheduleCleanup);
            observer.observe(document.body, { childList: true, subtree: true });
        })();
    </script>
<?php
    }

    if (!meza_is_locked_admin_chrome_screen()) {
        return;
    }
?>
    <style id="meza-lock-events-admin-chrome">
        .edit-php.post-type-tribe_events .ian-client,
        .edit-php.post-type-tribe_events .ian-sidebar,
        .post-php.post-type-tribe_events .ian-client,
        .post-php.post-type-tribe_events .ian-sidebar {
            display: none !important;
        }

        .edit-php.post-type-tribe_events .ian-header,
        .edit-php.post-type-tribe_events .ian-inner-wrapper,
        .post-php.post-type-tribe_events .ian-header,
        .post-php.post-type-tribe_events .ian-inner-wrapper {
            all: unset !important;
            display: contents !important;
        }
    </style>
<?php
}, 1005);

function meza_group_woocommerce_top_level_items(): void
{
    global $menu, $submenu;

    if (!is_array($menu) || empty($menu)) return;

    $ordered_items = [
        'woocommerce' => null,
        'customer_sign_generator' => null,
    ];
    $matched_indexes = [];

    foreach ($menu as $index => $item) {
        if (!is_array($item)) continue;

        $slug = strtolower((string) ($item[2] ?? ''));
        $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
        $submenu_items = isset($submenu[(string) ($item[2] ?? '')]) && is_array($submenu[(string) ($item[2] ?? '')]) ? $submenu[(string) ($item[2] ?? '')] : [];

        if (meza_is_woocommerce_menu_item($item, $submenu_items)) {
            if ($ordered_items['woocommerce'] === null) {
                $ordered_items['woocommerce'] = $item;
            }
            $matched_indexes[] = (int) $index;
            continue;
        }

        if (str_contains($slug, 'client-sign-generator') || $title === 'customer sign generator') {
            if ($ordered_items['customer_sign_generator'] === null) {
                $ordered_items['customer_sign_generator'] = $item;
            }
            $matched_indexes[] = (int) $index;
            continue;
        }

        if (
            str_contains($slug, 'wc-admin&path=/marketing')
            || str_contains($slug, 'woocommerce-marketing')
            || $title === 'marketing'
        ) {
            $matched_indexes[] = (int) $index;
            continue;
        }
    }

    $items_to_insert = array_values(array_filter($ordered_items, static function ($item): bool {
        return is_array($item);
    }));

    if (empty($items_to_insert) || empty($matched_indexes)) return;

    rsort($matched_indexes, SORT_NUMERIC);
    foreach ($matched_indexes as $matched_index) {
        array_splice($menu, $matched_index, 1);
    }

    $insert_index = meza_get_dashboard_group_end_index($menu);
    if ($insert_index === null) {
        $insert_index = 1;
    }

    $block = [
        [
            '',
            'read',
            'separator-meza-woocommerce-start',
            '',
            'wp-menu-separator',
        ],
    ];

    foreach ($items_to_insert as $item) {
        $block[] = $item;
    }

    $block[] = [
        '',
        'read',
        'separator-meza-woocommerce-end',
        '',
        'wp-menu-separator',
    ];

    array_splice($menu, $insert_index, 0, $block);
}

// Keep Store and Customer Sign Generator together directly below the Dashboard group.
add_action('admin_menu', 'meza_group_woocommerce_top_level_items', PHP_INT_MAX);

function meza_is_settings_utility_menu_item($item): bool
{
    if (!is_array($item)) return false;

    $classes = strtolower((string) ($item[4] ?? ''));
    if (str_contains($classes, 'wp-menu-separator')) return false;

    $slug = strtolower((string) ($item[2] ?? ''));
    $label = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
    $is_acf = $slug === 'edit.php?post_type=acf-field-group'
        || $label === 'acf';
    $is_mail = str_contains($slug, 'wp-mail-smtp')
        || $label === 'mail';
    $is_security = str_contains($slug, 'aiowpsec')
        || str_contains($slug, 'wp-security')
        || $label === 'security';
    $is_backups = str_contains($slug, 'updraft')
        || in_array($label, ['backups', 'updraft', 'updraftplus'], true);

    return $is_acf || $is_mail || $is_security || $is_backups;
}

function meza_group_post_settings_utilities(): void
{
    global $menu;

    if (!is_array($menu) || empty($menu)) return;

    $settings_index = null;

    foreach ($menu as $index => $item) {
        if (!is_array($item)) continue;

        if (((string) ($item[2] ?? '')) === 'options-general.php') {
            $settings_index = (int) $index;
            break;
        }
    }

    if ($settings_index === null) return;

    $utility_items = [];
    $utility_indexes = [];

    foreach ($menu as $index => $item) {
        if (!is_array($item)) continue;
        if (!meza_is_settings_utility_menu_item($item)) continue;

        $utility_indexes[] = (int) $index;
        $utility_items[] = [
            'item' => $item,
            'label' => strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? '')))),
        ];
    }

    if (empty($utility_items)) return;

    usort($utility_items, static function (array $a, array $b): int {
        $priority = [
            'acf' => 10,
            'backups' => 10,
            'updraft' => 10,
            'updraftplus' => 10,
            'mail' => 10,
            'security' => 10,
        ];

        $label_a = strtolower((string) ($a['label'] ?? ''));
        $label_b = strtolower((string) ($b['label'] ?? ''));
        $rank_a = $priority[$label_a] ?? 100;
        $rank_b = $priority[$label_b] ?? 100;

        if ($rank_a !== $rank_b) return $rank_a <=> $rank_b;
        return strnatcasecmp($label_a, $label_b);
    });

    rsort($utility_indexes, SORT_NUMERIC);
    foreach ($utility_indexes as $menu_index) {
        array_splice($menu, $menu_index, 1);
    }

    foreach ($menu as $index => $item) {
        if (is_array($item) && ((string) ($item[2] ?? '')) === 'options-general.php') {
            $settings_index = (int) $index;
            break;
        }
    }

    $items_to_insert = [[
        '',
        'read',
        'separator-meza-settings-utilities',
        '',
        'wp-menu-separator',
    ]];

    foreach ($utility_items as $entry) {
        $items_to_insert[] = $entry['item'];
    }

    $items_to_insert[] = [
        '',
        'read',
        'separator-meza-default-fallback',
        '',
        'wp-menu-separator',
    ];

    array_splice($menu, $settings_index + 1, 0, $items_to_insert);
}

// Keep ACF, Backups, Mail, and Security in their own utility group below Settings.
add_action('admin_menu', 'meza_group_post_settings_utilities', PHP_INT_MAX);

function meza_alphabetize_default_admin_menu_group(): void
{
    global $menu;

    if (!is_array($menu) || empty($menu)) return;

    $settings_index = null;
    foreach ($menu as $index => $item) {
        if (!is_array($item)) continue;

        if (((string) ($item[2] ?? '')) === 'options-general.php') {
            $settings_index = (int) $index;
            break;
        }
    }

    if ($settings_index === null) return;

    $segment_start = $settings_index + 1;
    $menu_count = count($menu);
    if (
        isset($menu[$segment_start])
        && is_array($menu[$segment_start])
        && ((string) ($menu[$segment_start][2] ?? '')) === 'separator-meza-settings-utilities'
    ) {
        $segment_start++;

        while ($segment_start < $menu_count && meza_is_settings_utility_menu_item($menu[$segment_start] ?? null)) {
            $segment_start++;
        }

        if (
            isset($menu[$segment_start])
            && is_array($menu[$segment_start])
            && ((string) ($menu[$segment_start][2] ?? '')) === 'separator-meza-default-fallback'
        ) {
            $segment_start++;
        }
    }

    if ($segment_start >= $menu_count) return;

    $segment_items = array_slice($menu, $segment_start);
    $sortable_entries = [];

    foreach ($segment_items as $offset => $item) {
        if (!is_array($item)) continue;

        $slug = strtolower((string) ($item[2] ?? ''));
        $classes = strtolower((string) ($item[4] ?? ''));
        $is_separator = str_starts_with($slug, 'separator')
            || str_contains($classes, 'wp-menu-separator');
        if ($is_separator) continue;

        $sortable_entries[] = [
            'item' => $item,
            'label' => strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? '')))),
            'offset' => $offset,
        ];
    }

    if (count($sortable_entries) < 2) return;

    usort($sortable_entries, static function (array $a, array $b): int {
        $compare = strnatcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        if ($compare !== 0) return $compare;
        return ((int) ($a['offset'] ?? 0)) <=> ((int) ($b['offset'] ?? 0));
    });

    $sorted_items = array_map(static function (array $entry): array {
        return $entry['item'];
    }, $sortable_entries);

    array_splice($menu, $segment_start, $menu_count - $segment_start, $sorted_items);
}

// Alphabetize the remaining fallback top-level group below the Settings utilities block.
add_action('admin_menu', 'meza_alphabetize_default_admin_menu_group', PHP_INT_MAX);

function meza_get_collapsed_settings_menu_map(): array
{
    global $meza_collapsed_settings_menu_map;

    return is_array($meza_collapsed_settings_menu_map) ? $meza_collapsed_settings_menu_map : [];
}

function meza_set_collapsed_settings_menu_map(array $map): void
{
    global $meza_collapsed_settings_menu_map;
    $meza_collapsed_settings_menu_map = $map;
}

function meza_get_current_admin_menu_slug_candidates(): array
{
    global $pagenow;

    $candidates = [];
    $pagenow = is_string($pagenow ?? null) ? $pagenow : '';

    if ($pagenow !== '') {
        $candidates[] = $pagenow;
    }

    $page = isset($_GET['page']) ? trim(wp_unslash((string) $_GET['page'])) : '';
    if ($page !== '') {
        $candidates[] = $page;
        $candidates[] = 'admin.php?page=' . $page;
    }

    $post_type = isset($_GET['post_type']) ? sanitize_key(wp_unslash((string) $_GET['post_type'])) : '';
    if ($pagenow !== '' && $post_type !== '') {
        $candidates[] = $pagenow . '?post_type=' . $post_type;
    }

    return array_values(array_unique(array_filter($candidates, static function ($candidate): bool {
        return is_string($candidate) && $candidate !== '';
    })));
}

function meza_should_keep_top_level_menu_item(array $item): bool
{
    $slug = strtolower((string) ($item[2] ?? ''));
    $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
    $classes = strtolower((string) ($item[4] ?? ''));

    if ($slug === '' || str_starts_with($slug, 'separator') || str_contains($classes, 'wp-menu-separator')) {
        return true;
    }

    $core_top_level_slugs = [
        'index.php',
        'edit.php',
        'upload.php',
        'edit.php?post_type=page',
        'edit-comments.php',
        'themes.php',
        'plugins.php',
        'users.php',
        'tools.php',
        'options-general.php',
        'woocommerce',
    ];

    if (in_array($slug, $core_top_level_slugs, true)) {
        return true;
    }

    if (meza_is_settings_utility_menu_item($item)) {
        return true;
    }

    $is_site_kit = str_contains($slug, 'googlesitekit')
        || str_contains($slug, 'google-site-kit')
        || str_contains($slug, 'site-kit')
        || in_array($title, ['web analytics', 'site kit', 'site kit by google'], true);
    if ($is_site_kit) {
        return true;
    }

    $is_yoast = str_contains($slug, 'wpseo')
        || str_contains($slug, 'wordpress-seo')
        || str_contains($title, 'yoast seo')
        || $title === 'seo';
    if ($is_yoast) {
        return true;
    }

    if ($slug === 'custom_widget_area') {
        return true;
    }

    return false;
}

function meza_move_single_item_top_level_menus_into_settings(): void
{
    global $menu, $submenu;

    meza_set_collapsed_settings_menu_map([]);

    if (!is_array($menu) || !is_array($submenu) || empty($menu)) return;

    if (!isset($submenu['options-general.php']) || !is_array($submenu['options-general.php'])) {
        $submenu['options-general.php'] = [];
    }

    $moved_map = [];
    $moved_items = [];
    $menu_indexes_to_remove = [];

    foreach ($menu as $index => $item) {
        if (!is_array($item)) continue;
        if (meza_should_keep_top_level_menu_item($item)) continue;

        $parent_slug = (string) ($item[2] ?? '');
        $top_level_label = trim(wp_strip_all_tags((string) ($item[0] ?? '')));
        if ($parent_slug === '' || $top_level_label === '') continue;

        $child_items = array_values(array_filter((array) ($submenu[$parent_slug] ?? []), static function ($child): bool {
            return is_array($child);
        }));

        if (count($child_items) !== 1) continue;

        $child_item = $child_items[0];
        $child_slug = (string) ($child_item[2] ?? '');
        if ($child_slug === '') continue;

        $target_slug = $parent_slug;
        if ($target_slug === '') {
            $target_slug = $child_slug;
        }

        $map_entry = [
            'parent_slug' => $parent_slug,
            'label' => $top_level_label,
            'submenu_slug' => $target_slug,
        ];

        $moved_map[$target_slug] = $map_entry;
        if ($child_slug !== $target_slug) {
            $moved_map[$child_slug] = $map_entry;
        }

        $already_under_settings = false;
        foreach ((array) $submenu['options-general.php'] as $settings_item) {
            if (!is_array($settings_item)) continue;
            if (((string) ($settings_item[2] ?? '')) === $target_slug) {
                $already_under_settings = true;
                break;
            }
        }

        if (!$already_under_settings) {
            $new_settings_item = $child_item;
            $new_settings_item[2] = $target_slug;
            $new_settings_item[0] = $top_level_label;
            if (isset($new_settings_item[3])) $new_settings_item[3] = $top_level_label;
            $moved_items[] = $new_settings_item;
        }

        unset($submenu[$parent_slug]);
        $menu_indexes_to_remove[] = (int) $index;
    }

    if (!empty($moved_items)) {
        usort($moved_items, static function (array $a, array $b): int {
            $label_a = strtolower(trim(wp_strip_all_tags((string) ($a[0] ?? ''))));
            $label_b = strtolower(trim(wp_strip_all_tags((string) ($b[0] ?? ''))));
            return strnatcasecmp($label_a, $label_b);
        });

        $submenu['options-general.php'] = array_merge($submenu['options-general.php'], $moved_items);
    }

    if (!empty($menu_indexes_to_remove)) {
        rsort($menu_indexes_to_remove, SORT_NUMERIC);
        foreach ($menu_indexes_to_remove as $menu_index) {
            array_splice($menu, $menu_index, 1);
        }
    }

    if (!empty($moved_map)) {
        meza_set_collapsed_settings_menu_map($moved_map);
    }
}

add_action('admin_menu', 'meza_move_single_item_top_level_menus_into_settings', PHP_INT_MAX);

add_filter('parent_file', function ($parent_file) {
    $moved_map = meza_get_collapsed_settings_menu_map();
    if (empty($moved_map)) return $parent_file;

    foreach (meza_get_current_admin_menu_slug_candidates() as $candidate) {
        if (isset($moved_map[$candidate])) {
            return 'options-general.php';
        }
    }

    return $parent_file;
}, PHP_INT_MAX);

add_filter('submenu_file', function ($submenu_file) {
    $moved_map = meza_get_collapsed_settings_menu_map();
    if (empty($moved_map)) return $submenu_file;

    foreach (meza_get_current_admin_menu_slug_candidates() as $candidate) {
        if (isset($moved_map[$candidate])) {
            return (string) ($moved_map[$candidate]['submenu_slug'] ?? $candidate);
        }
    }

    return $submenu_file;
}, PHP_INT_MAX);

function meza_cleanup_menu_separators(): void
{
    global $menu;
    if (!is_array($menu) || empty($menu)) return;

    $is_separator = static function ($item): bool {
        if (!is_array($item)) return false;
        $slug = strtolower((string) ($item[2] ?? ''));
        $classes = strtolower((string) ($item[4] ?? ''));
        return str_starts_with($slug, 'separator') || str_contains($classes, 'wp-menu-separator');
    };

    $cleaned = [];
    $count = count($menu);
    for ($i = 0; $i < $count; $i++) {
        $item = $menu[$i];

        if (!$is_separator($item)) {
            $cleaned[] = $item;
            continue;
        }

        $prev_non_sep = null;
        for ($p = count($cleaned) - 1; $p >= 0; $p--) {
            if (!$is_separator($cleaned[$p])) {
                $prev_non_sep = $cleaned[$p];
                break;
            }
        }

        $next_non_sep = null;
        for ($n = $i + 1; $n < $count; $n++) {
            if (!$is_separator($menu[$n])) {
                $next_non_sep = $menu[$n];
                break;
            }
        }

        // Drop separators at edges and collapse stacked separators to a single spacer.
        if ($prev_non_sep === null || $next_non_sep === null) continue;
        if (!empty($cleaned) && $is_separator($cleaned[count($cleaned) - 1])) continue;

        $cleaned[] = $item;
    }

    $menu = $cleaned;
}

// Final top-level menu cleanup pass.
add_action('admin_menu', 'meza_cleanup_menu_separators', PHP_INT_MAX);

// Preserve the rebuilt top-level menu order after WooCommerce's menu_order filter runs.
add_filter('custom_menu_order', '__return_true', PHP_INT_MAX);
add_filter('menu_order', function ($menu_order) {
    global $menu;

    if (!is_array($menu) || empty($menu)) {
        return $menu_order;
    }

    $ordered_slugs = [];

    foreach ($menu as $item) {
        if (!is_array($item)) continue;

        $slug = (string) ($item[2] ?? '');
        if ($slug === '' || in_array($slug, $ordered_slugs, true)) continue;

        $ordered_slugs[] = $slug;
    }

    foreach ((array) $menu_order as $slug) {
        $slug = (string) $slug;
        if ($slug === '' || in_array($slug, $ordered_slugs, true)) continue;

        $ordered_slugs[] = $slug;
    }

    return $ordered_slugs;
}, PHP_INT_MAX);

function meza_filter_events_role_admin_menu(): void
{
    if (!meza_is_events_limited_role()) {
        return;
    }

    global $menu, $submenu;

    if (is_array($menu)) {
        $allowed_lookup = [
            'index.php' => null,
            'edit.php?post_type=event' => null,
            'upload.php' => null,
            'profile.php' => null,
            'users.php' => null,
        ];

        if (!meza_is_events_editor_role()) {
            $allowed_lookup['edit.php'] = null;
        }

        foreach ($menu as $item) {
            if (!is_array($item)) {
                continue;
            }

            $slug = (string) ($item[2] ?? '');
            if (array_key_exists($slug, $allowed_lookup) && $allowed_lookup[$slug] === null) {
                $allowed_lookup[$slug] = $item;
            }
        }

        $rebuilt_menu = [];

        if (is_array($allowed_lookup['index.php'])) {
            $rebuilt_menu[] = $allowed_lookup['index.php'];
        }

        $content_group = [];
        foreach (['edit.php?post_type=event', 'edit.php', 'upload.php'] as $slug) {
            if (isset($allowed_lookup[$slug]) && is_array($allowed_lookup[$slug])) {
                $content_group[] = $allowed_lookup[$slug];
            }
        }

        if (!empty($rebuilt_menu) && !empty($content_group)) {
            $rebuilt_menu[] = [
                '',
                'read',
                'separator-meza-events-content-start',
                '',
                'wp-menu-separator',
            ];
        }

        foreach ($content_group as $item) {
            $rebuilt_menu[] = $item;
        }

        $profile_item = null;
        foreach (['profile.php', 'users.php'] as $slug) {
            if (isset($allowed_lookup[$slug]) && is_array($allowed_lookup[$slug])) {
                $profile_item = $allowed_lookup[$slug];
                break;
            }
        }

        if (!empty($content_group) && is_array($profile_item)) {
            $rebuilt_menu[] = [
                '',
                'read',
                'separator-meza-events-profile-start',
                '',
                'wp-menu-separator',
            ];
        }

        if (is_array($profile_item)) {
            $rebuilt_menu[] = $profile_item;
        }

        $menu = $rebuilt_menu;
    }

    if (!is_array($submenu)) {
        return;
    }

    foreach ($submenu as $parent_slug => $items) {
        if (!is_array($items)) {
            continue;
        }

        if (in_array($parent_slug, ['users.php', 'profile.php'], true)) {
            $submenu[$parent_slug] = array_values(array_filter($items, static function ($item): bool {
                if (!is_array($item)) {
                    return false;
                }

                $label = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
                $slug = (string) ($item[2] ?? '');
                if (str_contains($label, 'author')) {
                    return false;
                }

                return in_array($slug, ['profile.php', 'users.php'], true);
            }));
            continue;
        }

        if ($parent_slug === 'edit.php' && meza_is_events_editor_role()) {
            unset($submenu[$parent_slug]);
            continue;
        }

        if (!in_array($parent_slug, ['index.php', 'edit.php', 'edit.php?post_type=event', 'upload.php'], true)) {
            unset($submenu[$parent_slug]);
        }
    }
}
add_action('admin_menu', 'meza_filter_events_role_admin_menu', PHP_INT_MAX);

function meza_get_current_admin_post_type(): string
{
    $post_type = isset($_GET['post_type']) ? sanitize_key(wp_unslash((string) $_GET['post_type'])) : '';
    if ($post_type !== '') {
        return $post_type;
    }

    $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
    if ($post_id > 0) {
        $resolved_post_type = get_post_type($post_id);
        return is_string($resolved_post_type) ? $resolved_post_type : '';
    }

    global $pagenow;
    if (in_array($pagenow, ['post-new.php', 'post.php'], true)) {
        return 'post';
    }

    return '';
}

add_action('admin_init', function (): void {
    if (!is_admin() || !meza_is_events_limited_role()) {
        return;
    }

    global $pagenow;

    if (in_array($pagenow, ['options-general.php', 'tools.php'], true)) {
        wp_safe_redirect(admin_url());
        exit;
    }

    if (!meza_is_events_editor_role()) {
        return;
    }

    $post_type = meza_get_current_admin_post_type();
    if ($post_type === 'post' && in_array($pagenow, ['edit.php', 'post-new.php', 'post.php'], true)) {
        wp_safe_redirect(admin_url('edit.php?post_type=event'));
        exit;
    }
}, 1);

add_filter('wp_is_application_passwords_available_for_user', function (bool $available, $user): bool {
    if (!$available) {
        return false;
    }

    if ($user === null) {
        $user = wp_get_current_user();
    } elseif (is_numeric($user)) {
        $user = get_userdata((int) $user);
    }

    return $user instanceof WP_User && in_array('administrator', (array) $user->roles, true);
}, 10, 2);

add_action('admin_head-profile.php', function (): void {
    $can_see_two_factor = meza_can_access_profile_two_factor() ? 'true' : 'false';
    $can_see_application_passwords = meza_user_has_any_role(wp_get_current_user(), ['administrator']) ? 'true' : 'false';
?>
    <script id="meza-profile-access-cleanup">
        (() => {
            const canSeeTwoFactor = <?php echo $can_see_two_factor; ?>;
            const canSeeApplicationPasswords = <?php echo $can_see_application_passwords; ?>;

            const normalize = (text) => String(text || '').replace(/\s+/g, ' ').trim().toLowerCase();

            const hideProfileSectionByHeading = (headingText) => {
                const target = normalize(headingText);
                document.querySelectorAll('h2, label').forEach((node) => {
                    if (normalize(node.textContent) !== target) return;

                    let current = node;
                    while (current) {
                        const next = current.nextElementSibling;
                        current.style.display = 'none';
                        if (!next) break;
                        if (next.matches('h2')) break;
                        current = next;
                    }
                });
            };

            const hideAiFeatureSection = () => {
                document.querySelectorAll('label').forEach((label) => {
                    if (normalize(label.textContent) !== 'ai features') return;

                    const row = label.closest('tr') || label.parentElement;
                    if (row instanceof HTMLElement) {
                        row.style.display = 'none';
                    }
                });
            };

            const apply = () => {
                if (!canSeeApplicationPasswords) {
                    hideProfileSectionByHeading('Application Passwords');
                }

                if (!canSeeTwoFactor) {
                    hideProfileSectionByHeading('Two Factor Authentication');
                }

                hideAiFeatureSection();
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', apply, {
                    once: true
                });
            } else {
                apply();
            }
        })();
    </script>
<?php
}, 1000);

// Admin Menu Editor swaps in its custom menu after admin_menu, so reapply these mutations then as well.
add_action('admin_menu_editor-menu_replaced', function () {
    meza_restore_site_kit_admin_menu();
    meza_normalize_admin_plugin_menus();
    meza_rebuild_content_menu_group();
    meza_reorder_dashboard_utility_items();
    meza_remove_yoast_admin_menu_entries();
    meza_remove_payments_admin_menu();
    meza_reorder_woocommerce_submenu_items();
    meza_remove_woocommerce_marketing_overview_submenu();
    meza_group_woocommerce_top_level_items();
    meza_group_post_settings_utilities();
    meza_alphabetize_default_admin_menu_group();
    meza_move_single_item_top_level_menus_into_settings();
    meza_cleanup_menu_separators();
    meza_filter_events_role_admin_menu();
    meza_filter_dashboard_submenu_items();
    meza_remove_admin_menu_counters();
    meza_alphabetize_fallback_plugin_submenus();
    meza_finalize_tools_submenu_order();
}, PHP_INT_MAX);

function meza_get_dashboard_updates_submenu_item(): ?array
{
    global $submenu;

    if (isset($submenu['index.php']) && is_array($submenu['index.php'])) {
        foreach ($submenu['index.php'] as $item) {
            if (!is_array($item)) continue;

            $slug = strtolower((string) ($item[2] ?? ''));
            if ($slug === 'update-core.php') {
                return $item;
            }
        }
    }

    if (
        !current_user_can('update_core')
        && !current_user_can('update_plugins')
        && !current_user_can('update_themes')
        && !current_user_can('update_languages')
    ) {
        return null;
    }

    $capability = 'update_core';
    if (!current_user_can($capability)) {
        if (current_user_can('update_plugins')) {
            $capability = 'update_plugins';
        } elseif (current_user_can('update_themes')) {
            $capability = 'update_themes';
        } else {
            $capability = 'update_languages';
        }
    }

    return [__('Updates'), $capability, 'update-core.php'];
}

function meza_get_dashboard_site_health_submenu_item(): ?array
{
    global $submenu;

    if (isset($submenu['index.php']) && is_array($submenu['index.php'])) {
        foreach ($submenu['index.php'] as $item) {
            if (!is_array($item)) continue;

            $slug = strtolower((string) ($item[2] ?? ''));
            if ($slug === 'site-health.php') {
                return $item;
            }
        }
    }

    if (!current_user_can('view_site_health_checks')) {
        return null;
    }

    return [__('Site Health'), 'view_site_health_checks', 'site-health.php'];
}

function meza_filter_dashboard_submenu_items(): void
{
    global $submenu;

    $home_item = [__('Home'), 'read', 'index.php'];
    $updates_item = meza_get_dashboard_updates_submenu_item();
    $site_health_item = meza_get_dashboard_site_health_submenu_item();

    $filtered_items = [$home_item];
    if (is_array($updates_item)) {
        $filtered_items[] = $updates_item;
    }
    if (is_array($site_health_item)) {
        $filtered_items[] = $site_health_item;
    }

    $submenu['index.php'] = array_values($filtered_items);
}

function meza_should_keep_admin_menu_counter(string $parent_slug, string $item_slug): bool
{
    $parent_slug = strtolower($parent_slug);
    $item_slug = strtolower($item_slug);

    if ($parent_slug !== 'index.php') {
        return false;
    }

    return in_array($item_slug, ['update-core.php', 'site-health.php'], true);
}

function meza_strip_admin_menu_counter_markup(string $label): string
{
    $patterns = [
        '/\s*<span class="[^"]*(?:update-plugins|awaiting-mod|plugin-count|theme-count|update-count|pending-count|menu-counter|count-\d+)[^"]*"[^>]*>.*?<\/span>/is',
        '/\s*<span class="[^"]*(?:screen-reader-text|comments-in-moderation-text)[^"]*"[^>]*>.*?<\/span>/is',
    ];

    $previous = null;
    while ($previous !== $label) {
        $previous = $label;
        $label = (string) preg_replace($patterns, '', $label);
    }

    return trim((string) preg_replace('/\s{2,}/', ' ', $label));
}

function meza_remove_admin_menu_counters(): void
{
    global $menu, $submenu;

    if (is_array($menu)) {
        foreach ($menu as &$item) {
            if (!is_array($item)) continue;

            $item_slug = strtolower((string) ($item[2] ?? ''));
            if (meza_should_keep_admin_menu_counter('', $item_slug)) {
                continue;
            }

            $item[0] = meza_strip_admin_menu_counter_markup((string) ($item[0] ?? ''));
        }
        unset($item);
    }

    if (!is_array($submenu)) {
        return;
    }

    foreach ($submenu as $parent_slug => &$items) {
        if (!is_array($items)) continue;

        foreach ($items as &$item) {
            if (!is_array($item)) continue;

            $item_slug = strtolower((string) ($item[2] ?? ''));
            if (meza_should_keep_admin_menu_counter((string) $parent_slug, $item_slug)) {
                continue;
            }

            $item[0] = meza_strip_admin_menu_counter_markup((string) ($item[0] ?? ''));
        }
        unset($item);
    }
    unset($items);
}

// Move Site Health from Tools to Dashboard, directly under Updates.
add_action('admin_menu', function () {
    global $submenu;

    if (!isset($submenu['tools.php']) || !is_array($submenu['tools.php'])) return;

    $site_health_item = null;

    $submenu['tools.php'] = array_values(array_filter($submenu['tools.php'], function ($item) use (&$site_health_item) {
        if (!is_array($item)) return true;

        $slug = strtolower((string) ($item[2] ?? ''));
        if (!str_contains($slug, 'site-health.php')) return true;

        if ($site_health_item === null) {
            $site_health_item = $item;
        }

        return false;
    }));

    if (!is_array($site_health_item)) return;

    if (!isset($submenu['index.php']) || !is_array($submenu['index.php'])) {
        $submenu['index.php'] = [];
    }

    foreach ($submenu['index.php'] as $existing_item) {
        if (!is_array($existing_item)) continue;
        $existing_slug = strtolower((string) ($existing_item[2] ?? ''));
        if (str_contains($existing_slug, 'site-health.php')) return;
    }

    $insert_at = count($submenu['index.php']);
    foreach ($submenu['index.php'] as $index => $existing_item) {
        if (!is_array($existing_item)) continue;
        $existing_slug = strtolower((string) ($existing_item[2] ?? ''));
        if ($existing_slug === 'update-core.php') {
            $insert_at = $index + 1;
            break;
        }
    }

    array_splice($submenu['index.php'], $insert_at, 0, [$site_health_item]);
}, 100000);

// Keep Dashboard submenu limited to Home, Updates, and Site Health.
add_action('admin_menu', 'meza_filter_dashboard_submenu_items', 100001);

// Remove admin-menu counters everywhere except Dashboard > Updates and Site Health.
add_action('admin_menu', 'meza_remove_admin_menu_counters', 100002);

// Streamline Tools submenu items.
add_action('admin_menu', function () {
    global $submenu;

    if (!isset($submenu['tools.php']) || !is_array($submenu['tools.php'])) return;

    foreach ($submenu['tools.php'] as $index => &$item) {
        if (!is_array($item)) continue;

        $slug = strtolower((string) ($item[2] ?? ''));

        if ($slug === 'tools.php') {
            unset($submenu['tools.php'][$index]);
            continue;
        }

        if ($slug === 'import.php') {
            $item[2] = 'admin.php?import=wordpress';
        }
    }
    unset($item);

    $submenu['tools.php'] = meza_sort_tools_submenu_items(array_values($submenu['tools.php']));
}, 100001);

// Keep Tools > Import highlighted on the WordPress importer screen.
add_filter('parent_file', function ($parent_file) {
    if (!is_admin()) return $parent_file;

    $importer = isset($_GET['import']) ? sanitize_key(wp_unslash($_GET['import'])) : '';
    if ($importer !== 'wordpress') return $parent_file;

    return 'tools.php';
});

add_filter('submenu_file', function ($submenu_file) {
    if (!is_admin()) return $submenu_file;

    $importer = isset($_GET['import']) ? sanitize_key(wp_unslash($_GET['import'])) : '';
    if ($importer !== 'wordpress') return $submenu_file;

    return 'admin.php?import=wordpress';
});

// Normalize post-type "Add New" submenu labels to "Add {Post Type}".
add_action('admin_menu', function () {
    global $submenu;

    foreach ($submenu as $parent_slug => &$items) {
        if (!is_array($items)) continue;

        $is_post_type_parent = ($parent_slug === 'edit.php')
            || str_starts_with((string) $parent_slug, 'edit.php?post_type=');
        if (!$is_post_type_parent) continue;

        $post_type = 'post';
        if ($parent_slug !== 'edit.php') {
            parse_str((string) parse_url((string) $parent_slug, PHP_URL_QUERY), $query_args);
            $post_type = (string) ($query_args['post_type'] ?? '');
            if ($post_type === '') continue;
        }

        if (function_exists('meza_is_acf_admin_post_type') && meza_is_acf_admin_post_type($post_type)) {
            continue;
        }

        $post_type_object = get_post_type_object($post_type);
        $plural_label = '';
        $singular_label = '';
        if ($post_type_object instanceof WP_Post_Type) {
            $plural_label = trim((string) ($post_type_object->labels->name ?? $post_type_object->labels->singular_name ?? ''));
            $singular_label = trim((string) ($post_type_object->labels->singular_name ?? $post_type_object->labels->name ?? ''));
        }
        if ($plural_label === '') {
            $plural_label = ucwords(str_replace(['-', '_'], ' ', $post_type));
        }
        if ($singular_label === '') {
            $singular_label = $plural_label;
        }

        foreach ($items as &$item) {
            if (!is_array($item)) continue;

            $label = trim(wp_strip_all_tags((string) ($item[0] ?? '')));
            $slug = strtolower((string) ($item[2] ?? ''));
            $normalized_label = strtolower($label);
            $is_add_screen = ($slug === 'post-new.php')
                || str_starts_with($slug, 'post-new.php?');
            $is_list_screen = ($slug === 'edit.php')
                || str_starts_with($slug, 'edit.php?post_type=');

            if (
                $is_add_screen
                && preg_match('/^add\b/i', $label)
                && $singular_label !== ''
                && str_contains(strtolower($label), strtolower($singular_label)) === false
            ) {
                $new_label = meza_normalize_add_post_type_label($singular_label);

                $item[0] = $new_label;
                if (isset($item[3])) $item[3] = $new_label;
                continue;
            }

            if (
                $is_list_screen
                && preg_match('/^all\b/i', $label)
                && $plural_label !== ''
                && str_contains($normalized_label, strtolower($plural_label)) === false
            ) {
                $new_label = 'All ' . $plural_label;
                $new_label = trim(preg_replace('/\s+/', ' ', $new_label) ?? $new_label);

                $item[0] = $new_label;
                if (isset($item[3])) $item[3] = $new_label;
            }
        }
        unset($item);
    }
    unset($items);
}, 100002);

// Simplify content-menu taxonomy/import/export submenu labels by removing the parent content type name.
add_action('admin_menu', function () {
    global $menu, $submenu;

    foreach ($submenu as $parent_slug => &$items) {
        if (!is_array($items)) continue;

        $is_post_type_parent = ($parent_slug === 'edit.php')
            || str_starts_with((string) $parent_slug, 'edit.php?post_type=');
        $is_links_parent = ((string) $parent_slug === 'link-manager.php');
        if (!$is_post_type_parent && !$is_links_parent) continue;

        $post_type = 'post';
        if ($is_post_type_parent && $parent_slug !== 'edit.php') {
            parse_str((string) parse_url((string) $parent_slug, PHP_URL_QUERY), $query_args);
            $post_type = (string) ($query_args['post_type'] ?? '');
            if ($post_type === '') continue;
        }

        if ($is_post_type_parent && function_exists('meza_is_acf_admin_post_type') && meza_is_acf_admin_post_type($post_type)) {
            continue;
        }

        $strip_candidates = [];

        foreach ($menu as $menu_item) {
            if (!is_array($menu_item)) continue;
            if (((string) ($menu_item[2] ?? '')) !== (string) $parent_slug) continue;

            $menu_label = trim(wp_strip_all_tags((string) ($menu_item[0] ?? '')));
            if ($menu_label !== '') $strip_candidates[] = $menu_label;
            break;
        }

        if ($is_links_parent) {
            $strip_candidates[] = 'Links';
            $strip_candidates[] = 'Link';
        } else {
            $post_type_object = get_post_type_object($post_type);
            if ($post_type_object instanceof WP_Post_Type) {
                $plural = trim((string) ($post_type_object->labels->name ?? ''));
                $singular = trim((string) ($post_type_object->labels->singular_name ?? ''));
                if ($plural !== '') $strip_candidates[] = $plural;
                if ($singular !== '') $strip_candidates[] = $singular;
            }
        }

        $strip_candidates = array_values(array_unique(array_filter(array_map('trim', $strip_candidates))));
        if (empty($strip_candidates)) continue;

        foreach ($items as &$item) {
            if (!is_array($item)) continue;

            $label = trim(wp_strip_all_tags((string) ($item[0] ?? '')));
            $slug = strtolower((string) ($item[2] ?? ''));
            $is_taxonomy_item = str_starts_with($slug, 'edit-tags.php?taxonomy=');
            $is_import_export_item = str_contains($slug, 'import')
                || str_contains($slug, 'export')
                || (bool) preg_match('/\b(import|export)\b/i', $label);
            if (!$is_taxonomy_item && !$is_import_export_item) continue;
            if ($label === '') continue;

            $new_label = $label;

            foreach ($strip_candidates as $candidate) {
                if ($candidate === '') continue;

                $quoted = preg_quote($candidate, '/');
                $updated_label = preg_replace('/^' . $quoted . '\s+/i', '', $new_label);
                if ($updated_label !== null && $updated_label !== $new_label) {
                    $new_label = trim(preg_replace('/\s+/', ' ', $updated_label) ?? $updated_label);
                    break;
                }

                $updated_label = preg_replace('/\b' . $quoted . '\b\s*/i', '', $new_label, 1);
                if ($updated_label !== null && $updated_label !== $new_label) {
                    $new_label = trim(preg_replace('/\s+/', ' ', $updated_label) ?? $updated_label);
                    break;
                }
            }

            if ($new_label !== '' && $new_label !== $label) {
                $item[0] = $new_label;
                if (isset($item[3])) $item[3] = $new_label;
            }
        }
        unset($item);

        $items = meza_sort_submenu_items_with_standard_structure($items, (string) $parent_slug, $is_post_type_parent ? $post_type : '');
    }
    unset($items);
}, 100003);

function meza_alphabetize_fallback_plugin_submenus(): void
{
    global $submenu;

    if (!is_array($submenu)) {
        return;
    }

    $core_parent_slugs = [
        'index.php',
        'edit.php',
        'upload.php',
        'edit.php?post_type=page',
        'edit-comments.php',
        'themes.php',
        'plugins.php',
        'users.php',
        'tools.php',
        'options-general.php',
        'woocommerce',
    ];

    foreach ($submenu as $parent_slug => &$items) {
        if (!is_array($items) || in_array((string) $parent_slug, $core_parent_slugs, true)) {
            continue;
        }

        if (str_starts_with((string) $parent_slug, 'edit.php?post_type=')) {
            continue;
        }

        $items = meza_sort_submenu_items_with_standard_structure($items, (string) $parent_slug);
    }
    unset($items);
}

function meza_sort_tools_submenu_items(array $items): array
{
    $sortable_items = [];
    $unsortable_items = [];

    foreach (array_values($items) as $item) {
        if (is_array($item)) {
            $sortable_items[] = $item;
            continue;
        }

        $unsortable_items[] = $item;
    }

    usort($sortable_items, static function (array $left, array $right): int {
        $left_label = meza_get_standardized_submenu_utility_label($left, 'tools.php');
        $right_label = meza_get_standardized_submenu_utility_label($right, 'tools.php');
        $priority_map = [
            'Import' => 10,
            'Export' => 20,
        ];

        $left_priority = $priority_map[$left_label] ?? 100;
        $right_priority = $priority_map[$right_label] ?? 100;

        if ($left_priority !== $right_priority) {
            return $left_priority <=> $right_priority;
        }

        $left_text = trim(wp_strip_all_tags((string) ($left[0] ?? '')));
        $right_text = trim(wp_strip_all_tags((string) ($right[0] ?? '')));

        return strnatcasecmp($left_text, $right_text);
    });

    return array_values(array_merge($sortable_items, $unsortable_items));
}

function meza_finalize_tools_submenu_order(): void
{
    global $submenu;

    if (!isset($submenu['tools.php']) || !is_array($submenu['tools.php'])) {
        return;
    }

    $submenu['tools.php'] = meza_sort_tools_submenu_items($submenu['tools.php']);
}

add_action('admin_menu', 'meza_alphabetize_fallback_plugin_submenus', 100004);
add_action('admin_menu', 'meza_finalize_tools_submenu_order', PHP_INT_MAX - 1);

/** ================================
 *  ADMIN LIST ACTIONS
 *  ================================ */

function meza_get_post_type_singular_label($post): string
{
    $post_obj = null;
    if ($post instanceof WP_Post) $post_obj = $post;
    if (is_numeric($post) && (int) $post > 0) $post_obj = get_post((int) $post);
    if (!($post_obj instanceof WP_Post)) return 'Post';

    $post_type_obj = get_post_type_object((string) $post_obj->post_type);
    if (is_object($post_type_obj) && isset($post_type_obj->labels->singular_name)) {
        $label = trim((string) $post_type_obj->labels->singular_name);
        if ($label !== '') return $label;
    }

    $fallback = trim(str_replace(['-', '_'], ' ', (string) $post_obj->post_type));
    return $fallback !== '' ? ucwords($fallback) : 'Post';
}

function meza_get_view_post_label($post): string
{
    return sprintf(__('View %s'), meza_get_post_type_singular_label($post));
}

function meza_get_preview_post_label($post): string
{
    return sprintf(__('Preview %s'), meza_get_post_type_singular_label($post));
}

function meza_strip_post_type_from_action_label(string $label, $post = null): string
{
    $normalized = trim(wp_strip_all_tags($label));
    if ($normalized === '') return $normalized;

    $candidates = [];

    if ($post instanceof WP_Post) {
        $post_type_obj = get_post_type_object((string) $post->post_type);
        if (is_object($post_type_obj) && isset($post_type_obj->labels)) {
            $singular = trim((string) ($post_type_obj->labels->singular_name ?? ''));
            $name = trim((string) ($post_type_obj->labels->name ?? ''));
            if ($singular !== '') $candidates[] = $singular;
            if ($name !== '') $candidates[] = $name;
        }

        $slug_label = trim(str_replace(['-', '_'], ' ', (string) $post->post_type));
        if ($slug_label !== '') $candidates[] = ucwords($slug_label);
    }

    $candidates = array_values(array_unique(array_filter($candidates, static fn($candidate) => is_string($candidate) && trim($candidate) !== '')));

    foreach ($candidates as $candidate) {
        $updated = preg_replace('/\s+' . preg_quote($candidate, '/') . '$/i', '', $normalized);
        if ($updated === null) continue;

        $updated = trim(preg_replace('/\s+/', ' ', $updated) ?? $updated);
        if ($updated !== '' && $updated !== $normalized) return $updated;
    }

    return $normalized;
}

function meza_update_admin_action_link(string $html, $post = null, string $label = ''): string
{
    if (trim($html) === '') return $html;

    return preg_replace_callback('/<a\b([^>]*)>(.*?)<\/a>/is', static function ($matches) use ($label, $post) {
        $attrs = (string) ($matches[1] ?? '');
        $text = (string) ($matches[2] ?? '');

        $href = '';
        if (preg_match('/\bhref\s*=\s*([\'"])(.*?)\1/i', $attrs, $href_matches)) {
            $href = html_entity_decode((string) ($href_matches[2] ?? ''), ENT_QUOTES, 'UTF-8');
        }

        $text_plain = strtolower(trim(wp_strip_all_tags($text)));
        $is_edit_or_view = (
            str_contains($href, 'post.php?')
            || str_contains($href, 'action=edit')
            || str_contains($text_plain, 'edit')
            || str_contains($text_plain, 'view')
            || str_contains($text_plain, 'preview')
        );

        if ($is_edit_or_view) {
            if (!preg_match('/\btarget\s*=/i', $attrs)) {
                $attrs .= ' target="_blank"';
            }
            if (!preg_match('/\brel\s*=/i', $attrs)) {
                $attrs .= ' rel="noopener noreferrer"';
            }
        } else {
            $attrs = preg_replace('/\s*\btarget\s*=\s*([\'"]).*?\1/i', '', $attrs) ?? $attrs;
            $attrs = preg_replace('/\s*\brel\s*=\s*([\'"]).*?\1/i', '', $attrs) ?? $attrs;
        }

        $new_label = $label !== '' ? $label : meza_strip_post_type_from_action_label($text, $post);
        if ($new_label !== '') $text = esc_html($new_label);
        return '<a' . $attrs . '>' . $text . '</a>';
    }, $html, 1) ?? $html;
}

function meza_get_title_permalink_display_text(WP_Post $post): string
{
    if (!meza_post_has_permalink((int) $post->ID)) return '';

    $url = (string) get_permalink((int) $post->ID);
    if ($url === '') return '';

    return meza_get_admin_link_column_display_text($url);
}

function meza_get_copy_url_action_link(WP_Post $post): string
{
    if (!meza_post_has_permalink((int) $post->ID)) return '';

    $url = (string) get_permalink((int) $post->ID);
    if ($url === '') return '';

    $display = meza_get_title_permalink_display_text($post);
    if ($display === '') return '';

    $page_template_html = meza_post_type_has_permalink((string) $post->post_type)
        ? meza_get_page_template_title_link((int) $post->ID)
        : '';

    return '<a href="#" class="mz-copy-link" data-copy-text="' . esc_attr($url) . '" data-permalink-url="' . esc_attr($url) . '" data-permalink-display="' . esc_attr($display) . '" data-page-template-html="' . esc_attr($page_template_html) . '">' . esc_html__('Copy URL') . '</a>';
}

function meza_is_duplicate_row_action($key, $action): bool
{
    $key = strtolower(trim((string) $key));
    if (in_array($key, ['duplicate', 'duplicate_post'], true)) return true;
    if (!is_string($action)) return false;

    $normalized_action = strtolower($action);
    return str_contains($normalized_action, 'duplicate');
}

function meza_remove_quick_edit_action(array $actions, $post = null): array
{
    if (isset($actions['inline hide-if-no-js'])) unset($actions['inline hide-if-no-js']);
    if (isset($actions['inline'])) unset($actions['inline']);

    if ($post instanceof WP_Post && $post->post_type === 'product') {
        if (isset($actions['duplicate_post'])) unset($actions['duplicate_post']);

        foreach ($actions as $key => $action) {
            if (!is_string($action)) continue;
            if (str_contains($action, 'class="m4c-duplicate-post"') || str_contains($action, "class='m4c-duplicate-post'")) {
                unset($actions[$key]);
            }
        }
    }

    foreach ($actions as $key => $action) {
        if (!is_string($action)) continue;
        $label = '';
        if (in_array((string) $key, ['trash', 'delete'], true)) {
            $label = __('Delete');
        } elseif ((string) $key === 'edit') {
            $label = __('Edit');
        } elseif ((string) $key === 'view') {
            $label = __('View');
        }

        $actions[$key] = meza_update_admin_action_link($action, $post, $label);
    }

    if (!($post instanceof WP_Post)) {
        return $actions;
    }

    $copy_url_action = meza_get_copy_url_action_link($post);
    if ($copy_url_action !== '') {
        $actions['meza_copy_url'] = $copy_url_action;
    }

    $ordered = [];
    foreach (['edit', 'trash', 'delete'] as $key) {
        if (isset($actions[$key])) {
            $ordered[$key] = $actions[$key];
            unset($actions[$key]);
        }
    }

    foreach ($actions as $key => $action) {
        if (!meza_is_duplicate_row_action($key, $action)) continue;
        $ordered[$key] = meza_update_admin_action_link((string) $action, $post, __('Duplicate'));
        unset($actions[$key]);
    }

    foreach (['view', 'preview'] as $key) {
        if (isset($actions[$key])) {
            $ordered[$key] = $actions[$key];
            unset($actions[$key]);
        }
    }

    if (isset($actions['meza_copy_url'])) {
        $ordered['meza_copy_url'] = $actions['meza_copy_url'];
        unset($actions['meza_copy_url']);
    }

    foreach ($actions as $key => $action) {
        $ordered[$key] = $action;
    }

    return $ordered;
}

add_filter('post_row_actions', 'meza_remove_quick_edit_action', 1000, 2);
add_filter('page_row_actions', 'meza_remove_quick_edit_action', 1000, 2);

add_action('current_screen', function ($screen): void {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') {
        return;
    }

    $screen_id = (string) ($screen->id ?? '');
    $post_type = (string) ($screen->post_type ?? '');
    if ($screen_id === '' || $post_type === '') {
        return;
    }

    add_filter("bulk_actions-{$screen_id}", function ($actions) use ($post_type) {
        if (!is_array($actions)) {
            return $actions;
        }

        $post_type_object = get_post_type_object($post_type);
        $singular = '';
        $plural = '';

        if ($post_type_object instanceof WP_Post_Type) {
            $singular = trim((string) ($post_type_object->labels->singular_name ?? ''));
            $plural = trim((string) ($post_type_object->labels->name ?? ''));
        }

        foreach ($actions as $key => $label) {
            if (!is_string($label)) {
                continue;
            }

            $normalized_key = strtolower(trim((string) $key));
            $normalized_label = strtolower(trim(wp_strip_all_tags($label)));
            if (
                !str_contains($normalized_key, 'duplicate')
                && !str_contains($normalized_label, 'duplicate')
            ) {
                continue;
            }

            $updated = meza_strip_post_type_from_action_label($label, get_post_type_object($post_type) instanceof WP_Post_Type ? (object) ['post_type' => $post_type] : null);

            if ($updated === $label || $updated === '') {
                $updated = $label;
                foreach (array_filter([$singular, $plural]) as $candidate) {
                    $candidate = trim((string) $candidate);
                    if ($candidate === '') continue;
                    $pattern = '/\s+' . preg_quote($candidate, '/') . 's?$/i';
                    $next = preg_replace($pattern, '', $updated);
                    if ($next !== null && trim($next) !== '' && trim($next) !== trim($updated)) {
                        $updated = trim(preg_replace('/\s+/', ' ', $next) ?? $next);
                        break;
                    }
                }
            }

            $actions[$key] = ($updated !== '') ? $updated : __('Duplicate');
        }

        return $actions;
    }, 1000);
}, 1000);

add_filter('post_updated_messages', function (array $messages): array {
    global $post;
    if (!($post instanceof WP_Post)) return $messages;

    $post_type = (string) $post->post_type;
    if ($post_type === '' || !isset($messages[$post_type]) || !is_array($messages[$post_type])) return $messages;
    if (meza_is_acf_admin_post_type($post_type)) return $messages;

    $view_label = meza_get_view_post_label($post);
    $preview_label = meza_get_preview_post_label($post);

    foreach ($messages[$post_type] as $index => $message) {
        if (!is_string($message) || $message === '') continue;

        $messages[$post_type][$index] = preg_replace_callback('/<a\b([^>]*)>(.*?)<\/a>/is', static function ($matches) use ($view_label, $preview_label) {
            $attrs = (string) ($matches[1] ?? '');
            $text_html = (string) ($matches[2] ?? '');
            $text_plain = strtolower(trim(wp_strip_all_tags($text_html)));
            $label = str_contains($text_plain, 'preview') ? $preview_label : $view_label;

            if (!preg_match('/\btarget\s*=/i', $attrs)) {
                $attrs .= ' target="_blank"';
            }
            if (!preg_match('/\brel\s*=/i', $attrs)) {
                $attrs .= ' rel="noopener noreferrer"';
            }

            return '<a' . $attrs . '>' . esc_html($label) . '</a>';
        }, $message) ?? $message;
    }

    return $messages;
}, 1000);

add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!($wp_admin_bar instanceof WP_Admin_Bar)) return;

    $view_node = $wp_admin_bar->get_node('view');
    if (!is_object($view_node)) return;

    $post = get_post();
    if (!($post instanceof WP_Post)) return;

    $meta = is_array($view_node->meta ?? null) ? $view_node->meta : [];
    $meta['target'] = '_blank';

    $rel = trim((string) ($meta['rel'] ?? ''));
    if ($rel === '') {
        $meta['rel'] = 'noopener noreferrer';
    } else {
        if (!preg_match('/\bnoopener\b/i', $rel)) $rel .= ' noopener';
        if (!preg_match('/\bnoreferrer\b/i', $rel)) $rel .= ' noreferrer';
        $meta['rel'] = trim($rel);
    }

    $wp_admin_bar->add_node([
        'id' => (string) $view_node->id,
        'parent' => $view_node->parent ?? false,
        'title' => esc_html(meza_get_view_post_label($post)),
        'href' => $view_node->href ?? false,
        'group' => !empty($view_node->group),
        'meta' => $meta,
    ]);
}, 100001);

/** ================================
 *  ACF ADMIN COLUMN NORMALIZATION
 *  ================================ */

function meza_is_acf_admin_post_type(string $post_type): bool
{
    return str_starts_with($post_type, 'acf-');
}

function meza_strip_acf_key_description_columns(array $columns): array
{
    if (!is_array($columns)) return $columns;

    foreach ($columns as $key => $label) {
        $key_normalized = strtolower(trim((string) $key));
        $label_normalized = strtolower(trim(wp_strip_all_tags((string) $label)));
        if (in_array($key_normalized, ['id', 'key', 'description', 'acf_id', 'acf_key', 'acf_description'], true)) {
            unset($columns[$key]);
            continue;
        }
        if (in_array($label_normalized, ['id', 'key', 'description'], true)) {
            unset($columns[$key]);
        }
    }

    $ordered = [];
    $used = [];

    $append = static function (string $key) use (&$ordered, &$columns, &$used): void {
        if (isset($used[$key])) return;
        if (!array_key_exists($key, $columns)) return;
        $ordered[$key] = $columns[$key];
        $used[$key] = true;
    };

    $normalize = static function (string $value): string {
        $value = strtolower(trim(wp_strip_all_tags($value)));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;
        return trim($value, '_');
    };

    $find_column_key = static function (array $aliases) use ($columns, $normalize): string {
        $aliases = array_values(array_unique(array_map($normalize, $aliases)));
        foreach ($columns as $key => $label) {
            $key_normalized = $normalize((string) $key);
            $label_normalized = $normalize((string) $label);
            if (in_array($key_normalized, $aliases, true) || in_array($label_normalized, $aliases, true)) {
                return (string) $key;
            }
        }
        return '';
    };

    $append('cb');

    $order = [
        ['title'],
        ['location'],
        ['post_types', 'post_type', 'post types', 'post type'],
        ['taxonomies', 'taxonomy'],
        ['field_groups', 'field group', 'field groups'],
        ['posts', 'post'],
        ['terms', 'term'],
        ['fields', 'field'],
    ];

    foreach ($order as $aliases) {
        $resolved_key = $find_column_key($aliases);
        if ($resolved_key !== '') $append($resolved_key);
    }

    foreach (array_keys($columns) as $key) {
        $append((string) $key);
    }

    return $ordered;
}

add_action('current_screen', function ($screen) {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    $post_type = (string) ($screen->post_type ?? '');
    if (!meza_is_acf_admin_post_type($post_type)) return;

    add_filter("manage_{$post_type}_posts_columns", 'meza_strip_acf_key_description_columns', 9999);
});

// Set consistent admin list column widths.
add_action('admin_head-edit.php', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    $post_type = (string) ($screen->post_type ?? '');
    $is_acf_screen = meza_is_acf_admin_post_type((string) ($screen->post_type ?? ''));

    echo '<style id="meza-admin-list-column-widths">' .
        '.meza-admin-table-scroll{width:100%;max-width:100%;max-height:calc(100vh - 260px);overflow:auto;-webkit-overflow-scrolling:touch;border:1px solid #c3c4c7;box-sizing:border-box;background:#fff;}' .
        '.meza-admin-table-scroll table.wp-list-table{min-width:max-content;border-collapse:separate;border-spacing:0;border:none!important;box-shadow:none!important;}' .
        '.meza-admin-table-scroll table.wp-list-table thead,.meza-admin-table-scroll table.wp-list-table tfoot{position:relative;z-index:4;}' .
        '.meza-admin-table-scroll table.wp-list-table thead th,.meza-admin-table-scroll table.wp-list-table thead td{position:sticky;top:0;z-index:5;background:#fff;border-top:none!important;border-bottom:none!important;box-shadow:inset 0 -1px 0 #ccd0d4;background-clip:padding-box;}' .
        '.meza-admin-table-scroll table.wp-list-table tfoot th,.meza-admin-table-scroll table.wp-list-table tfoot td{position:sticky;bottom:0;z-index:5;background:#fff;border-top:none!important;border-bottom:none!important;box-shadow:inset 0 1px 0 #ccd0d4;background-clip:padding-box;}' .
        '.meza-admin-table-scroll table.wp-list-table tbody td{position:relative;z-index:1;background-clip:padding-box;}' .
        '.wp-list-table thead th.sorted,.wp-list-table tfoot th.sorted{background:#eef4ff;color:#0a4b78;box-shadow:inset 0 -1px 0 #b8d3ea;}' .
        '.wp-list-table th.sorted a,.wp-list-table th.sorted a:focus,.wp-list-table th.sorted a:visited{color:#0a4b78;}' .
        '.wp-list-table th.sorted .sorting-indicators{opacity:1;}' .
        '.wp-list-table th.sorted.asc .sorting-indicator.asc,.wp-list-table th.sorted.desc .sorting-indicator.desc{color:#0a4b78;opacity:1;}' .
        '.wp-list-table .column-mz_id{width:65px;max-width:65px;}' .
        '.wp-list-table .column-mz_menu_order{width:65px;max-width:65px;}' .
        '.wp-list-table .column-mz_cta_link{width:200px;max-width:200px;}' .
        '.wp-list-table .column-mz_cta_secondary_link{width:200px;max-width:200px;}' .
        '.wp-list-table .column-website{width:200px;max-width:200px;}' .
        '.wp-list-table .column-mz_faq_count{width:80px;max-width:80px;}' .
        '.wp-list-table .column-mz_form_recipients{width:150px;max-width:150px;}' .
        '.wp-list-table .column-mz_slug{width:175px;max-width:175px;}' .
        '.wp-list-table .column-mz_organization_url{width:200px;max-width:200px;}' .
        '.wp-list-table .column-mz_profile_link{width:200px;max-width:200px;}' .
        '.wp-list-table .column-mz_profile_title{width:175px;max-width:175px;}' .
        '.wp-list-table .column-mz_summary{width:325px;max-width:325px;}' .
        '.wp-list-table .column-mz_review_quote{width:325px;max-width:325px;}' .
        '.wp-list-table .column-mz_review_citer{width:175px;max-width:175px;}' .
        '.wp-list-table .column-mz_thumbnail{width:125px;}' .
        '.wp-list-table td.column-mz_thumbnail{vertical-align:top!important;}' .
        '.wp-list-table td.column-mz_thumbnail .mz-thumb-wrap{display:inline-block!important;width:100px!important;max-width:100%!important;line-height:0!important;margin:0 0 6px!important;}' .
        '.wp-list-table td.column-mz_thumbnail .mz-thumb-wrap>a{display:inline-block!important;width:100%!important;line-height:0!important;}' .
        '.wp-list-table td.column-mz_thumbnail img{width:100px!important;height:auto!important;max-width:100px!important;display:block!important;margin:0!important;}' .
        '.wp-list-table .column-mz_thumbnail .row-actions{font-size:11px;line-height:1.1;}' .
        '.wp-list-table .column-title{width:225px;}' .
        '.wp-list-table .column-title .meza-title-template,.wp-list-table .column-title .meza-title-permalink{font-size:12px;line-height:1.4;}' .
        '.wp-list-table .column-title .meza-title-template{margin:3px 0 2px;}' .
        '.wp-list-table .column-title .meza-title-permalink{margin:0 0 4px;}' .
        '.wp-list-table .column-title .meza-title-template .meza-title-template-text{display:inline-flex;align-items:center;gap:4px;color:#646970;font-weight:500;overflow-wrap:anywhere;word-break:break-word;}' .
        '.wp-list-table .column-title .meza-title-template .meza-page-type-label{display:inline-flex;align-items:flex-start;gap:4px;vertical-align:top;}' .
        '.wp-list-table .column-title .meza-title-template .meza-page-type-label-text{line-height:1.3;}' .
        '.wp-list-table .column-title .meza-title-template .dashicons{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;font-size:14px;width:14px;height:14px;line-height:14px;position:relative;}' .
        '.wp-list-table .column-title .meza-title-permalink a{color:#646970;text-decoration:none;overflow-wrap:anywhere;word-break:break-word;}' .
        '.wp-list-table .column-title .meza-title-permalink a:hover{color:#2271b1;text-decoration:underline;}' .
        '.wp-list-table .column-mz_modified,.wp-list-table .column-mz_published{width:225px;}' .
        '.wp-list-table th.column-categories,.wp-list-table td.column-categories{width:225px;max-width:225px;}' .
        '.wp-list-table th[class*="column-taxonomy-"],.wp-list-table td[class*="column-taxonomy-"]{width:225px;}' .
        '.wp-list-table th.column-mz_page_headline,.wp-list-table td.column-mz_page_headline{width:225px;max-width:225px;}' .
        '.wp-list-table th.column-mz_page_cta,.wp-list-table td.column-mz_page_cta{width:175px;max-width:175px;}' .
        '.wp-list-table th.column-mz_page_form,.wp-list-table td.column-mz_page_form{width:175px;max-width:175px;}' .
        '</style>';

    if ($post_type === 'product') {
        echo '<style id="meza-product-admin-column-widths">' .
            '.wp-list-table th.column-featured,.wp-list-table td.column-featured{width:48px!important;min-width:48px!important;max-width:48px!important;text-align:center;}' .
            '.wp-list-table th.column-mz_thumbnail,.wp-list-table td.column-mz_thumbnail{width:78px!important;min-width:78px!important;max-width:78px!important;}' .
            '.wp-list-table td.column-mz_thumbnail{vertical-align:top!important;}' .
            '.wp-list-table td.column-mz_thumbnail .mz-thumb-wrap{display:inline-block!important;width:78px!important;max-width:100%!important;line-height:0!important;margin:0 0 6px!important;}' .
            '.wp-list-table td.column-mz_thumbnail .mz-thumb-wrap>a{display:inline-block!important;width:100%!important;line-height:0!important;}' .
            '.wp-list-table td.column-mz_thumbnail img{display:block!important;width:78px!important;height:auto!important;max-width:78px!important;margin:0!important;}' .
            '.wp-list-table th.column-name,.wp-list-table td.column-name{width:240px!important;min-width:240px!important;max-width:240px!important;}' .
            '.wp-list-table th.column-price,.wp-list-table td.column-price{width:90px!important;min-width:90px!important;max-width:90px!important;white-space:nowrap!important;}' .
            '.wp-list-table th.column-is_in_stock,.wp-list-table td.column-is_in_stock{width:110px!important;min-width:110px!important;max-width:110px!important;white-space:nowrap!important;}' .
            '.wp-list-table th.column-taxonomy-product_brand,.wp-list-table td.column-taxonomy-product_brand{width:130px!important;min-width:130px!important;max-width:130px!important;}' .
            '.wp-list-table th.column-mz_product_type,.wp-list-table td.column-mz_product_type{width:140px!important;min-width:140px!important;max-width:140px!important;}' .
            '.wp-list-table th.column-sku,.wp-list-table td.column-sku{width:190px!important;min-width:190px!important;max-width:190px!important;}' .
            '.wp-list-table th.column-product_cat,.wp-list-table td.column-product_cat,.wp-list-table th.column-taxonomy-product_cat,.wp-list-table td.column-taxonomy-product_cat{width:190px!important;min-width:190px!important;max-width:190px!important;}' .
            '.wp-list-table th.column-product_tag,.wp-list-table td.column-product_tag,.wp-list-table th.column-taxonomy-product_tag,.wp-list-table td.column-taxonomy-product_tag{width:180px!important;min-width:180px!important;max-width:180px!important;}' .
            '.wp-list-table th.column-mz_page_link,.wp-list-table td.column-mz_page_link{width:320px!important;min-width:320px!important;max-width:320px!important;}' .
            '.wp-list-table th.column-wpseo-title,.wp-list-table td.column-wpseo-title{width:260px!important;min-width:260px!important;max-width:260px!important;}' .
            '.wp-list-table th.column-mz_share_title,.wp-list-table td.column-mz_share_title{width:260px!important;min-width:260px!important;max-width:260px!important;}' .
            '.wp-list-table th.column-wpseo-metadesc,.wp-list-table td.column-wpseo-metadesc{width:320px!important;min-width:320px!important;max-width:320px!important;}' .
            '.wp-list-table th.column-mz_share_description,.wp-list-table td.column-mz_share_description{width:320px!important;min-width:320px!important;max-width:320px!important;}' .
            '.wp-list-table th.column-mz_page_headline,.wp-list-table td.column-mz_page_headline{width:260px!important;min-width:260px!important;max-width:260px!important;}' .
            '.wp-list-table th.column-mz_page_cta,.wp-list-table td.column-mz_page_cta{width:220px!important;min-width:220px!important;max-width:220px!important;}' .
            '.wp-list-table th.column-mz_page_form,.wp-list-table td.column-mz_page_form{width:220px!important;min-width:220px!important;max-width:220px!important;}' .
            '.wp-list-table th.column-mz_modified,.wp-list-table td.column-mz_modified,.wp-list-table th.column-mz_published,.wp-list-table td.column-mz_published{width:220px!important;min-width:220px!important;max-width:220px!important;vertical-align:top!important;}' .
            '.wp-list-table td.column-sku,.wp-list-table td.column-product_cat,.wp-list-table td.column-taxonomy-product_cat,.wp-list-table td.column-product_tag,.wp-list-table td.column-taxonomy-product_tag,.wp-list-table td.column-mz_page_link,.wp-list-table td.column-wpseo-title,.wp-list-table td.column-mz_share_title,.wp-list-table td.column-wpseo-metadesc,.wp-list-table td.column-mz_share_description,.wp-list-table td.column-mz_page_headline,.wp-list-table td.column-mz_page_cta,.wp-list-table td.column-mz_page_form{white-space:normal!important;overflow-wrap:anywhere;word-break:break-word;vertical-align:top!important;}' .
            '.wp-list-table td.column-mz_page_link a:first-child{display:block;white-space:normal!important;overflow-wrap:anywhere;word-break:break-word;}' .
            '.wp-list-table td.column-mz_page_link .row-actions{display:flex;flex-wrap:wrap;align-items:center;gap:0;line-height:1.3;}' .
            '.wp-list-table td.column-mz_page_link .row-actions>span{display:inline-flex;align-items:center;}' .
            '.wp-list-table td.column-mz_page_link .row-actions>span+span::before{content:"|";color:#646970;display:inline-block;margin:0 .25em;}' .
            '</style>';
    }

    if (!$is_acf_screen) return;

    // ACF list tables often use generic id/key/description column slugs.
    echo '<style id="meza-acf-admin-column-normalization">' .
        'table.wp-list-table.fixed{table-layout:fixed!important;}' .
        'table.wp-list-table.fixed col.column-id,table.wp-list-table.fixed col.column-ID{display:none!important;}' .
        '.wp-list-table th.column-id,.wp-list-table td.column-id,.wp-list-table th.column-ID,.wp-list-table td.column-ID{display:none!important;}' .
        '.wp-list-table th.column-key,.wp-list-table td.column-key,.wp-list-table th.column-description,.wp-list-table td.column-description{display:none!important;}' .
        'table.wp-list-table.fixed col.column-posts,.wp-list-table th.column-posts,.wp-list-table td.column-posts{width:125px!important;min-width:125px!important;max-width:125px!important;}' .
        'table.wp-list-table.fixed col.column-terms,.wp-list-table th.column-terms,.wp-list-table td.column-terms{width:125px!important;min-width:125px!important;max-width:125px!important;}' .
        'table.wp-list-table.fixed col.column-fields,.wp-list-table th.column-fields,.wp-list-table td.column-fields{width:125px!important;min-width:125px!important;max-width:125px!important;}' .
        '</style>';
});

// Open linked post titles in a new tab on admin list tables.
add_action('admin_print_footer_scripts-edit.php', function () {
    echo '<script id="meza-admin-title-link-target">' .
        'document.querySelectorAll(".wp-list-table .row-title").forEach(function(link){' .
        'link.setAttribute("target","_blank");' .
        'link.setAttribute("rel","noopener noreferrer");' .
        '});' .
        'document.querySelectorAll(".wp-list-table .mz-copy-link[data-permalink-url]").forEach(function(link){' .
        'var actions=link.closest(".row-actions");' .
        'if(!actions) return;' .
        'var cell=actions.closest("td,th");' .
        'if(!cell || cell.querySelector(".meza-title-permalink")) return;' .
        'var templateHtml=link.getAttribute("data-page-template-html")||"";' .
        'var url=link.getAttribute("data-permalink-url")||"";' .
        'var display=link.getAttribute("data-permalink-display")||url;' .
        'if(!url || !display) return;' .
        'if(templateHtml && !cell.querySelector(".meza-title-template")){' .
        'var templateWrap=document.createElement("div");' .
        'templateWrap.className="meza-title-template";' .
        'templateWrap.innerHTML=templateHtml;' .
        'actions.parentNode.insertBefore(templateWrap,actions);' .
        '}' .
        'var wrap=document.createElement("div");' .
        'wrap.className="meza-title-permalink";' .
        'var anchor=document.createElement("a");' .
        'anchor.href=url;' .
        'anchor.target="_blank";' .
        'anchor.rel="noopener noreferrer";' .
        'anchor.textContent=display;' .
        'wrap.appendChild(anchor);' .
        'actions.parentNode.insertBefore(wrap,actions);' .
        '});' .
        'document.querySelectorAll(".wp-list-table .mz-copy-link").forEach(function(link){' .
        'link.addEventListener("click", function(e){' .
        'e.preventDefault();' .
        'var text = this.getAttribute("data-copy-text") || "";' .
        'if (!text) return;' .
        'var original = this.textContent;' .
        'var done = function(){var el=link;el.textContent="Copied";setTimeout(function(){el.textContent=original;},1200);};' .
        'if (navigator.clipboard && navigator.clipboard.writeText) {' .
        'navigator.clipboard.writeText(text).then(done).catch(function(){' .
        'var ta=document.createElement("textarea");ta.value=text;document.body.appendChild(ta);ta.select();' .
        'try{document.execCommand("copy");done();}catch(_e){}document.body.removeChild(ta);' .
        '});' .
        '} else {' .
        'var ta=document.createElement("textarea");ta.value=text;document.body.appendChild(ta);ta.select();' .
        'try{document.execCommand("copy");done();}catch(_e){}document.body.removeChild(ta);' .
        '}' .
        '});' .
        '});' .
        '</script>';
});

add_action('admin_footer-edit.php', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    echo '<script id="meza-admin-table-scroll-wrap">' .
        '(function(){' .
        'var table=document.querySelector("#posts-filter table.wp-list-table");' .
        'if(!table)return;' .
        'if(table.parentElement&&table.parentElement.classList.contains("meza-admin-table-scroll"))return;' .
        'var wrapper=document.createElement("div");' .
        'wrapper.className="meza-admin-table-scroll";' .
        'table.parentNode.insertBefore(wrapper,table);' .
        'wrapper.appendChild(table);' .
        '})();' .
        '</script>';
});

/** ================================
 *  ADMIN BAR CLEANUP
 *  ================================ */

function meza_is_admin_bar_query_monitor_node($node): bool
{
    if (!is_object($node)) return false;

    $node_id = strtolower((string) ($node->id ?? ''));
    $parent_id = strtolower((string) ($node->parent ?? ''));
    $title = strtolower(trim(wp_strip_all_tags((string) ($node->title ?? ''))));

    if ($node_id === 'query-monitor' || str_starts_with($node_id, 'query-monitor-')) {
        return true;
    }

    if ($parent_id === 'query-monitor' || str_starts_with($parent_id, 'query-monitor-')) {
        return true;
    }

    return $title === 'query monitor' && ($parent_id === '' || $parent_id === 'top-secondary' || $parent_id === 'query-monitor');
}

function meza_is_admin_bar_clear_cache_node($node): bool
{
    if (!is_object($node)) return false;

    $node_id = strtolower((string) ($node->id ?? ''));
    $title = strtolower(trim(wp_strip_all_tags((string) ($node->title ?? ''))));
    $href = strtolower((string) ($node->href ?? ''));

    if ($node_id === 'meza-flush-server-cache') {
        return true;
    }

    return str_contains($title, 'delete cache')
        || str_contains($title, 'clear page cache')
        || $title === 'clear cache'
        || (str_contains($node_id, 'super') && str_contains($node_id, 'cache'))
        || (str_contains($href, 'wp-super-cache') && str_contains($href, 'cache'))
        || str_contains($href, 'wpsc_delete_cache')
        || str_contains($href, 'wpaas_action=flush_cache');
}

function meza_is_default_admin_bar_node_id(string $node_id): bool
{
    static $default_ids = [
        'menu-toggle',
        'wp-logo',
        'about',
        'contribute',
        'wp-logo-external',
        'wporg',
        'documentation',
        'learn',
        'support-forums',
        'feedback',
        'site-name',
        'view-site',
        'edit-site',
        'dashboard',
        'menus',
        'plugins',
        'my-sites',
        'my-sites-super-admin',
        'network-admin',
        'network-admin-d',
        'network-admin-s',
        'network-admin-u',
        'network-admin-t',
        'network-admin-p',
        'network-admin-o',
        'my-sites-list',
        'get-shortlink',
        'edit',
        'view',
        'preview',
        'archive',
        'new-content',
        'new-post',
        'new-media',
        'new-link',
        'new-page',
        'new-user',
        'add-new-site',
        'updates',
        'top-secondary',
        'my-account',
        'user-actions',
        'user-info',
        'logout',
        'search',
        'recovery-mode',
    ];

    if (in_array($node_id, $default_ids, true)) {
        return true;
    }

    return preg_match('/^blog-\d+(?:-(?:d|n|c|v))?$/', $node_id) === 1;
}

function meza_should_keep_admin_bar_node($node): bool
{
    if (!is_object($node)) return false;

    $node_id = strtolower((string) ($node->id ?? ''));
    if ($node_id === '') {
        return false;
    }

    return meza_is_default_admin_bar_node_id($node_id)
        || meza_is_admin_bar_query_monitor_node($node)
        || meza_is_admin_bar_clear_cache_node($node);
}

function meza_remove_admin_bar_nodes($wp_admin_bar): void
{
    if (!($wp_admin_bar instanceof WP_Admin_Bar)) return;

    $nodes = $wp_admin_bar->get_nodes();
    if (!is_array($nodes)) return;

    foreach ($nodes as $node) {
        if (!is_object($node) || !isset($node->id)) {
            continue;
        }

        if (!meza_should_keep_admin_bar_node($node)) {
            $wp_admin_bar->remove_node((string) $node->id);
        }
    }
}

add_action('admin_bar_menu', function ($wp_admin_bar) {
    meza_remove_admin_bar_nodes($wp_admin_bar);
}, 99999);

add_action('wp_before_admin_bar_render', function () {
    global $wp_admin_bar;
    meza_remove_admin_bar_nodes($wp_admin_bar);
}, 99999);

function meza_position_flush_server_cache_node($wp_admin_bar): void
{
    if (!($wp_admin_bar instanceof WP_Admin_Bar)) return;

    $flush_node_id = 'meza-flush-server-cache';
    $toolbar_parent = false;
    $environment = defined('WP_ENV')
        ? strtolower((string) WP_ENV)
        : (function_exists('wp_get_environment_type') ? strtolower((string) wp_get_environment_type()) : 'production');
    $is_production_like_environment = in_array($environment, ['qa', 'production'], true);
    $wp_super_cache_plugin_file = trailingslashit((string) WP_PLUGIN_DIR) . 'wp-super-cache/wp-cache.php';
    $has_wp_super_cache_installed = file_exists($wp_super_cache_plugin_file);
    $nodes = $wp_admin_bar->get_nodes();
    if (!is_array($nodes)) return;

    $delete_cache_node = null;
    $query_monitor_node = null;
    $quick_link_node_ids = [];

    foreach ($nodes as $node) {
        if (!is_object($node) || !isset($node->id)) continue;

        $node_id = strtolower((string) ($node->id ?? ''));
        $title = strtolower(trim(wp_strip_all_tags((string) ($node->title ?? ''))));
        $href = strtolower((string) ($node->href ?? ''));

        $is_quick_links = (str_contains($title, 'quick links') && (str_contains($title, 'godaddy') || str_contains($node_id, 'godaddy') || str_contains($href, 'godaddy') || str_contains($href, 'wpaas')))
            || (str_contains($node_id, 'godaddy') && str_contains($node_id, 'quick'))
            || (str_contains($href, 'godaddy') && str_contains($href, 'quick'))
            || (str_contains($href, 'wpaas') && str_contains($title, 'quick links'));
        if ($is_quick_links) {
            $quick_link_node_ids[] = (string) $node->id;
        }

        $is_delete_cache = str_contains($title, 'delete cache')
            || str_contains($title, 'clear page cache')
            || (str_contains($node_id, 'super') && str_contains($node_id, 'cache'))
            || (str_contains($href, 'wp-super-cache') && str_contains($href, 'cache'))
            || str_contains($href, 'wpsc_delete_cache');
        if ($is_delete_cache && $delete_cache_node === null) {
            $delete_cache_node = $node;
        }

        $node_parent = strtolower((string) ($node->parent ?? ''));
        $is_query_monitor = ($node_id === 'query-monitor')
            || ($title === 'query monitor' && ($node_parent === '' || $node_parent === 'top-secondary'));
        if ($is_query_monitor && $query_monitor_node === null) {
            $query_monitor_node = $node;
        }
    }

    $quick_link_node_ids = array_values(array_unique($quick_link_node_ids));
    $remove_ids = [$flush_node_id];
    foreach ($quick_link_node_ids as $quick_link_node_id) {
        $remove_ids[] = $quick_link_node_id;
    }

    // Remove quick-links descendants as well so no dropdown survives.
    $changed = true;
    while ($changed) {
        $changed = false;
        foreach ($nodes as $node) {
            if (!is_object($node) || !isset($node->id)) continue;

            $id = (string) ($node->id ?? '');
            $parent = (string) ($node->parent ?? '');
            if ($id === '' || $parent === '') continue;

            if (in_array($parent, $remove_ids, true) && !in_array($id, $remove_ids, true)) {
                $remove_ids[] = $id;
                $changed = true;
            }
        }
    }

    foreach (array_unique($remove_ids) as $remove_id) {
        if ($remove_id === '') continue;
        $wp_admin_bar->remove_node($remove_id);
    }

    // This runs on multiple admin-bar hooks. On a later pass, Quick Links may
    // already be gone, so treat "no matching node remains" as success.
    $quick_links_hidden_successfully = true;
    $remaining_nodes = $wp_admin_bar->get_nodes();
    if (is_array($remaining_nodes)) {
        foreach ($remaining_nodes as $node) {
            if (!is_object($node) || !isset($node->id)) continue;

            $node_id = strtolower((string) ($node->id ?? ''));
            $title = strtolower(trim(wp_strip_all_tags((string) ($node->title ?? ''))));
            $href = strtolower((string) ($node->href ?? ''));

            $is_quick_links = (str_contains($title, 'quick links') && (str_contains($title, 'godaddy') || str_contains($node_id, 'godaddy') || str_contains($href, 'godaddy') || str_contains($href, 'wpaas')))
                || (str_contains($node_id, 'godaddy') && str_contains($node_id, 'quick'))
                || (str_contains($href, 'godaddy') && str_contains($href, 'quick'))
                || (str_contains($href, 'wpaas') && str_contains($title, 'quick links'));
            if ($is_quick_links) {
                $quick_links_hidden_successfully = false;
                break;
            }
        }
    }

    if (!$quick_links_hidden_successfully) {
        $wp_admin_bar->remove_node($flush_node_id);
    }

    $should_show_page_cache = $has_wp_super_cache_installed && ($delete_cache_node instanceof stdClass);
    $should_show_server_cache = !$has_wp_super_cache_installed && $is_production_like_environment;

    if (!meza_can_access_clear_cache()) {
        $wp_admin_bar->remove_node($flush_node_id);

        if ($delete_cache_node instanceof stdClass) {
            $delete_cache_id = (string) ($delete_cache_node->id ?? '');
            if ($delete_cache_id !== '') {
                $wp_admin_bar->remove_node($delete_cache_id);
            }
        }

        return;
    }

    $add_clone = static function ($node, string $title_override = '', $parent_override = null) use ($wp_admin_bar): void {
        if (!($node instanceof stdClass)) return;
        $node_id = (string) ($node->id ?? '');
        if ($node_id === '') return;

        $title = ($title_override !== '') ? $title_override : ($node->title ?? '');
        $meta = is_array($node->meta ?? null) ? $node->meta : [];
        if ($title_override !== '') {
            $meta['title'] = $title_override;
        }

        $wp_admin_bar->add_node([
            'id' => $node_id,
            'parent' => ($parent_override !== null) ? $parent_override : ($node->parent ?? false),
            'title' => $title,
            'href' => $node->href ?? false,
            'group' => !empty($node->group),
            'meta' => $meta,
        ]);
    };

    $add_flush = static function () use ($wp_admin_bar, $flush_node_id, $quick_links_hidden_successfully, $toolbar_parent): void {
        if (!$quick_links_hidden_successfully) {
            $wp_admin_bar->remove_node($flush_node_id);
            return;
        }

        // Keep users on the current screen while still triggering the WPaaS flush action.
        $current_request_uri = (string) ($_SERVER['REQUEST_URI'] ?? '/wp-admin/');
        if ($current_request_uri === '') {
            $current_request_uri = '/wp-admin/';
        }
        $flush_href = remove_query_arg(['wpaas_action', 'wpaas_nonce'], $current_request_uri);
        $flush_href = add_query_arg([
            'wpaas_action' => 'flush_cache',
            'wpaas_nonce' => '366b8ead40',
        ], $flush_href);

        $wp_admin_bar->add_node([
            'id' => $flush_node_id,
            'parent' => $toolbar_parent,
            'title' => 'Clear Cache',
            'href' => $flush_href,
            'group' => false,
            'meta' => ['title' => 'Clear Cache'],
        ]);
    };

    if ($query_monitor_node instanceof stdClass) {
        $query_monitor_id = (string) ($query_monitor_node->id ?? '');
        if ($query_monitor_id !== '') $wp_admin_bar->remove_node($query_monitor_id);
        $add_clone($query_monitor_node, '', $toolbar_parent);
    }

    if ($delete_cache_node instanceof stdClass) {
        $delete_cache_id = (string) ($delete_cache_node->id ?? '');
        if ($delete_cache_id !== '') $wp_admin_bar->remove_node($delete_cache_id);
    }

    // Show exactly one cache action at a time:
    // - page cache when WP Super Cache is installed
    // - server cache only on qa/production when WP Super Cache is not installed
    if ($should_show_page_cache) {
        $add_clone($delete_cache_node, 'Clear Cache', $toolbar_parent);
        return;
    }

    if ($should_show_server_cache) {
        $add_flush();
        return;
    }
}

// Remove GoDaddy Quick Links before positioning the single cache action, when applicable.
add_action('admin_bar_menu', function ($wp_admin_bar) {
    meza_position_flush_server_cache_node($wp_admin_bar);
}, PHP_INT_MAX);

add_action('wp_before_admin_bar_render', function () {
    global $wp_admin_bar;
    meza_position_flush_server_cache_node($wp_admin_bar);
}, PHP_INT_MAX);

function meza_move_howdy_to_right_side_end($wp_admin_bar): void
{
    if (!($wp_admin_bar instanceof WP_Admin_Bar)) return;

    $nodes = $wp_admin_bar->get_nodes();
    if (!is_array($nodes)) return;

    $my_account = null;
    foreach ($nodes as $node) {
        if (!is_object($node) || !isset($node->id)) continue;

        $id = strtolower((string) ($node->id ?? ''));
        $title = strtolower(trim(wp_strip_all_tags((string) ($node->title ?? ''))));
        if ($id === 'my-account' || str_starts_with($title, 'howdy')) {
            $my_account = $node;
            break;
        }
    }

    if (!($my_account instanceof stdClass)) return;

    $my_account_id = (string) ($my_account->id ?? '');
    if ($my_account_id === '') return;

    $wp_admin_bar->remove_node($my_account_id);
    $wp_admin_bar->add_node([
        'id' => $my_account_id,
        'parent' => 'top-secondary',
        'title' => $my_account->title ?? '',
        'href' => $my_account->href ?? false,
        'group' => !empty($my_account->group),
        'meta' => is_array($my_account->meta ?? null) ? $my_account->meta : [],
    ]);
}

// Keep "Howdy, {User}" as the last right-side admin-bar item.
add_action('admin_bar_menu', function ($wp_admin_bar) {
    meza_move_howdy_to_right_side_end($wp_admin_bar);
}, PHP_INT_MAX);

add_action('wp_before_admin_bar_render', function () {
    global $wp_admin_bar;
    meza_move_howdy_to_right_side_end($wp_admin_bar);
}, PHP_INT_MAX);

function meza_filter_admin_bar_new_content_menu($wp_admin_bar): void
{
    if (!($wp_admin_bar instanceof WP_Admin_Bar)) return;

    $nodes = $wp_admin_bar->get_nodes();
    if (!is_array($nodes)) return;

    $all_children = [];
    $children = [];
    foreach ($nodes as $node) {
        if (!is_object($node) || (($node->parent ?? '') !== 'new-content')) continue;
        $all_children[] = $node;
        if (!meza_can_access_admin_bar_new_content_node($node)) continue;
        $children[] = $node;
    }

    if (empty($all_children)) return;

    foreach ($all_children as $child) {
        $wp_admin_bar->remove_node((string) $child->id);
    }

    if (count($children) >= 2) {
        usort($children, static function ($a, $b) {
            $title_a = strtolower(trim(wp_strip_all_tags((string) ($a->title ?? ''))));
            $title_b = strtolower(trim(wp_strip_all_tags((string) ($b->title ?? ''))));
            return strnatcmp($title_a, $title_b);
        });
    }

    foreach ($children as $child) {
        $wp_admin_bar->add_node([
            'id' => (string) $child->id,
            'parent' => 'new-content',
            'title' => $child->title ?? '',
            'href' => $child->href ?? false,
            'group' => !empty($child->group),
            'meta' => is_array($child->meta ?? null) ? $child->meta : [],
        ]);
    }

    if (empty($children)) {
        $wp_admin_bar->remove_node('new-content');
    }
}

// Keep the "New" admin-bar dropdown limited to allowed items and alphabetized.
add_action('admin_bar_menu', function ($wp_admin_bar) {
    meza_filter_admin_bar_new_content_menu($wp_admin_bar);
}, PHP_INT_MAX);

add_action('wp_before_admin_bar_render', function () {
    global $wp_admin_bar;
    meza_filter_admin_bar_new_content_menu($wp_admin_bar);
}, PHP_INT_MAX);

// Final admin-bar pass: keep only core WordPress items plus Query Monitor and
// the single clear-cache action after all other toolbar customizations run.
add_action('admin_bar_menu', function ($wp_admin_bar) {
    meza_remove_admin_bar_nodes($wp_admin_bar);
}, PHP_INT_MAX);

add_action('wp_before_admin_bar_render', function () {
    global $wp_admin_bar;
    meza_remove_admin_bar_nodes($wp_admin_bar);
}, PHP_INT_MAX);

function meza_get_custom_logo_id(): int
{
    $theme_mod_logo_id = (int) get_theme_mod('custom_logo');
    if ($theme_mod_logo_id > 0) {
        return $theme_mod_logo_id;
    }

    $settings_logo_id = (int) get_option('meza_custom_logo_id', 0);
    return ($settings_logo_id > 0) ? $settings_logo_id : 0;
}

function meza_get_custom_logo_url(): string
{
    $custom_logo_id = meza_get_custom_logo_id();
    if ($custom_logo_id <= 0) return '';

    $custom_logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
    return is_string($custom_logo_url) ? $custom_logo_url : '';
}

function meza_get_alternative_logo_id(): int
{
    $settings_logo_id = (int) get_option('meza_alternative_logo_id', 0);
    return ($settings_logo_id > 0) ? $settings_logo_id : 0;
}

function meza_get_alternative_logo_url(): string
{
    $alternative_logo_id = meza_get_alternative_logo_id();
    if ($alternative_logo_id <= 0) return '';

    $alternative_logo_url = wp_get_attachment_image_url($alternative_logo_id, 'full');
    return is_string($alternative_logo_url) ? $alternative_logo_url : '';
}

function meza_get_logo_link_html(int $attachment_id): string
{
    if ($attachment_id <= 0) {
        return '';
    }

    $logo = wp_get_attachment_image($attachment_id, 'full', false, [
        'class' => 'custom-logo',
        'loading' => 'lazy',
        'decoding' => 'async',
    ]);

    if (!is_string($logo) || $logo === '') {
        return '';
    }

    $attributes = [
        'class' => 'custom-logo-link',
        'href' => home_url('/'),
        'rel' => 'home',
    ];

    if (is_front_page() && !is_paged()) {
        $attributes['aria-current'] = 'page';
    }

    $attribute_html = '';
    foreach ($attributes as $name => $value) {
        $attribute_html .= sprintf(' %s="%s"', esc_attr($name), esc_attr($value));
    }

    return '<a' . $attribute_html . '>' . $logo . '</a>';
}

function meza_get_footer_logo_html(): string
{
    $alternative_logo = meza_get_logo_link_html(meza_get_alternative_logo_id());
    if ($alternative_logo !== '') {
        return $alternative_logo;
    }

    if (function_exists('get_custom_logo')) {
        $custom_logo = (string) get_custom_logo();
        if ($custom_logo !== '') {
            return $custom_logo;
        }
    }

    return '';
}

function meza_sanitize_custom_logo_id($value): int
{
    $attachment_id = absint($value);
    if ($attachment_id <= 0) {
        set_theme_mod('custom_logo', 0);
        return 0;
    }

    $attachment = get_post($attachment_id);
    if (!($attachment instanceof WP_Post) || $attachment->post_type !== 'attachment') {
        add_settings_error(
            'meza_custom_logo_id',
            'meza_custom_logo_invalid',
            __('Select a valid media library image for the custom logo.', 'mz-mu-plugins')
        );

        return meza_get_custom_logo_id();
    }

    set_theme_mod('custom_logo', $attachment_id);
    return $attachment_id;
}

function meza_sanitize_alternative_logo_id($value): int
{
    $attachment_id = absint($value);
    if ($attachment_id <= 0) {
        return 0;
    }

    $attachment = get_post($attachment_id);
    if (!($attachment instanceof WP_Post) || $attachment->post_type !== 'attachment') {
        add_settings_error(
            'meza_alternative_logo_id',
            'meza_alternative_logo_invalid',
            __('Select a valid media library image for the alternative logo.', 'mz-mu-plugins')
        );

        return meza_get_alternative_logo_id();
    }

    return $attachment_id;
}

function meza_sync_custom_logo_setting(): void
{
    $option_logo_id = (int) get_option('meza_custom_logo_id', 0);
    $theme_mod_logo_id = (int) get_theme_mod('custom_logo');

    if ($option_logo_id <= 0 && $theme_mod_logo_id > 0) {
        update_option('meza_custom_logo_id', $theme_mod_logo_id);
        return;
    }

    if ($option_logo_id > 0 && $option_logo_id !== $theme_mod_logo_id) {
        set_theme_mod('custom_logo', $option_logo_id);
    }
}
add_action('after_setup_theme', 'meza_sync_custom_logo_setting', 20);

function meza_render_logo_settings_field(string $field_name, int $logo_id, string $frame_title, string $description = '', string $preview_theme = 'light'): void
{
    $logo_url = ($logo_id > 0) ? wp_get_attachment_image_url($logo_id, 'medium') : '';
    $button_label = ($logo_id > 0)
        ? __('Change logo', 'mz-mu-plugins')
        : __('Select logo', 'mz-mu-plugins');
    ?>
    <div
        class="meza-custom-logo-setting"
        data-meza-logo-field
        data-meza-logo-empty-label="<?php echo esc_attr__('Select logo', 'mz-mu-plugins'); ?>"
        data-meza-logo-filled-label="<?php echo esc_attr__('Change logo', 'mz-mu-plugins'); ?>"
        data-meza-logo-frame-title="<?php echo esc_attr($frame_title); ?>"
        data-meza-logo-button-text="<?php echo esc_attr__('Use this logo', 'mz-mu-plugins'); ?>"
    >
        <input
            type="hidden"
            id="<?php echo esc_attr($field_name); ?>"
            name="<?php echo esc_attr($field_name); ?>"
            value="<?php echo esc_attr($logo_id); ?>"
            data-meza-logo-input
        >
        <div
            class="meza-custom-logo-setting__preview meza-custom-logo-setting__preview--<?php echo esc_attr($preview_theme); ?> <?php echo ($logo_url !== '') ? 'is-visible' : ''; ?>"
            data-meza-logo-preview-wrap
        >
            <img
                src="<?php echo esc_url($logo_url ?: ''); ?>"
                alt="<?php esc_attr_e('Selected custom logo preview', 'mz-mu-plugins'); ?>"
                data-meza-logo-preview
            >
        </div>
        <p class="meza-custom-logo-setting__actions">
            <button type="button" class="button" data-meza-logo-select>
                <?php echo esc_html($button_label); ?>
            </button>
            <button
                type="button"
                class="button-link-delete"
                data-meza-logo-remove
                <?php disabled($logo_id <= 0); ?>
            >
                <?php esc_html_e('Remove logo', 'mz-mu-plugins'); ?>
            </button>
        </p>
        <?php if ($description !== '') : ?>
            <p class="description meza-custom-logo-setting__description">
                <?php echo wp_kses_post($description); ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}

function meza_render_custom_logo_settings_field(): void
{
    meza_render_logo_settings_field(
        'meza_custom_logo_id',
        meza_get_custom_logo_id(),
        __('Select site logo', 'mz-mu-plugins'),
        __('The Site Logo appears in the header, login screen, and email templates. Upload a transparent version when possible. For best results, upload an image at least <code>512</code> pixels wide.', 'mz-mu-plugins')
    );
}

function meza_render_alternative_logo_settings_field(): void
{
    meza_render_logo_settings_field(
        'meza_alternative_logo_id',
        meza_get_alternative_logo_id(),
        __('Select alternative site logo', 'mz-mu-plugins'),
        __('The Site Logo (Alternative) appears in the footer and other dark sections. Upload a transparent version when possible. For best results, upload an image at least <code>512</code> pixels wide.', 'mz-mu-plugins'),
        'dark'
    );
}

add_action('admin_init', function (): void {
    register_setting('general', 'meza_custom_logo_id', [
        'type' => 'integer',
        'sanitize_callback' => 'meza_sanitize_custom_logo_id',
        'default' => 0,
    ]);

    add_settings_field(
        'meza_custom_logo_id',
        __('Site Logo', 'mz-mu-plugins'),
        'meza_render_custom_logo_settings_field',
        'general'
    );

    register_setting('general', 'meza_alternative_logo_id', [
        'type' => 'integer',
        'sanitize_callback' => 'meza_sanitize_alternative_logo_id',
        'default' => 0,
    ]);

    add_settings_field(
        'meza_alternative_logo_id',
        __('Site Logo (Alternative)', 'mz-mu-plugins'),
        'meza_render_alternative_logo_settings_field',
        'general'
    );
});

add_action('admin_enqueue_scripts', function (string $hook_suffix): void {
    if ($hook_suffix !== 'options-general.php') {
        return;
    }

    wp_enqueue_media();
    wp_add_inline_script('jquery', <<<JS
jQuery(function ($) {
    const siteLogoField = $('#meza_custom_logo_id').closest('[data-meza-logo-field]');
    const altLogoField = $('#meza_alternative_logo_id').closest('[data-meza-logo-field]');
    const siteLogoRow = siteLogoField.closest('tr');
    const altLogoRow = altLogoField.closest('tr');
    const taglineRow = $('#blogdescription').closest('tr');
    const siteIconRow = $('#site_icon').closest('tr');
    if (siteLogoRow.length && taglineRow.length) {
        siteLogoRow.insertAfter(taglineRow);
    }
    if (altLogoRow.length) {
        if (siteIconRow.length) {
            altLogoRow.insertBefore(siteIconRow);
        } else if (siteLogoRow.length) {
            altLogoRow.insertAfter(siteLogoRow);
        }
    }

    const siteIconChooseButton = $('#choose-from-library-button');
    const siteIconRemoveButton = $('#js-remove-site-icon');
    const normalizeSiteIconButtons = function () {
        if (siteIconChooseButton.length) {
            siteIconChooseButton
                .removeClass('button-hero button-secondary')
                .addClass('button');
            siteIconChooseButton.attr('data-alt-classes', 'button');
            siteIconChooseButton.attr('data-update-text', 'Change icon');
            siteIconChooseButton.attr('data-choose-text', 'Choose a Site Icon');
            siteIconChooseButton.text(siteIconChooseButton.attr('data-state') === '1' ? 'Change icon' : 'Choose a Site Icon');
        }

        if (siteIconRemoveButton.length) {
            siteIconRemoveButton
                .removeClass('button-secondary reset')
                .addClass('button-link-delete');
            siteIconRemoveButton.text('Remove icon');
        }
    };
    normalizeSiteIconButtons();

    $('[data-meza-logo-field]').each(function () {
        const field = $(this);
        const input = field.find('[data-meza-logo-input]');
        const previewWrap = field.find('[data-meza-logo-preview-wrap]');
        const preview = field.find('[data-meza-logo-preview]');
        const selectButton = field.find('[data-meza-logo-select]');
        const removeButton = field.find('[data-meza-logo-remove]');
        const emptyLabel = field.data('mezaLogoEmptyLabel') || 'Select logo';
        const filledLabel = field.data('mezaLogoFilledLabel') || 'Replace logo';
        const frameTitle = field.data('mezaLogoFrameTitle') || 'Select logo';
        const buttonText = field.data('mezaLogoButtonText') || 'Use this logo';

        const setLogo = function (attachment) {
            const attachmentId = parseInt(attachment.id, 10) || 0;
            const previewUrl =
                (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) ||
                attachment.url ||
                '';

            input.val(attachmentId);
            preview.attr('src', previewUrl);
            previewWrap.addClass('is-visible');
            selectButton.text(filledLabel);
            removeButton.prop('disabled', false);
        };

        const clearLogo = function () {
            input.val('0');
            preview.attr('src', '');
            previewWrap.removeClass('is-visible');
            selectButton.text(emptyLabel);
            removeButton.prop('disabled', true);
        };

        let frame;

        selectButton.on('click', function (event) {
            event.preventDefault();

            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: frameTitle,
                library: { type: 'image' },
                button: { text: buttonText },
                multiple: false
            });

            frame.on('select', function () {
                const attachment = frame.state().get('selection').first().toJSON();
                setLogo(attachment);
            });

            frame.open();
        });

        removeButton.on('click', function (event) {
            event.preventDefault();
            clearLogo();
        });
    });
});
JS, 'after');
    wp_add_inline_style('common', <<<CSS
.meza-custom-logo-setting__preview{
    display:none;
    width:100%;
    max-width:320px;
    padding:16px;
    margin:0 0 12px;
    border:1px solid #dcdcde;
    border-radius:8px;
    background:#fff;
    box-sizing:border-box;
}
.meza-custom-logo-setting__preview--dark{
    background:#000;
    border-color:#000;
}
.meza-custom-logo-setting__preview.is-visible{
    display:block;
}
.meza-custom-logo-setting__preview img{
    display:block;
    width:auto;
    max-width:100%;
    max-height:120px;
    height:auto;
}
.meza-custom-logo-setting__actions{
    display:flex;
    gap:12px;
    align-items:center;
    margin:0;
}
.meza-custom-logo-setting__description{
    margin:8px 0 0;
}
.meza-custom-logo-setting__actions .button-link-delete,
.site-icon-action-buttons .button-link-delete{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:30px;
    margin:0;
    padding:0 12px;
    border:1px solid #d63638;
    border-radius:3px;
    background:#fff;
    color:#d63638;
    line-height:2.15384615;
    text-decoration:none;
    cursor:pointer;
}
.meza-custom-logo-setting__actions .button-link-delete:hover,
.meza-custom-logo-setting__actions .button-link-delete:focus,
.site-icon-action-buttons .button-link-delete:hover,
.site-icon-action-buttons .button-link-delete:focus{
    border-color:#d63638;
    background:#fcf0f1;
    color:#d63638;
}
.meza-custom-logo-setting__actions .button-link-delete[disabled],
.site-icon-action-buttons .button-link-delete[disabled]{
    border-color:#dcdcde;
    background:#f6f7f7;
    color:#a7aaad;
    cursor:default;
}
.site-icon-action-buttons{
    display:flex;
    gap:12px;
    align-items:center;
    flex-wrap:wrap;
}
.site-icon-action-buttons #choose-from-library-button{
    min-height:30px;
    margin:0;
    padding:0 12px;
    line-height:2.15384615;
}
.site-icon-action-buttons #js-remove-site-icon{
    min-height:30px;
    margin:0;
    line-height:2.15384615;
}
CSS);
});

// Use theme custom logo on wp-login.php.
add_action('login_enqueue_scripts', function () {
    $custom_logo_url = meza_get_custom_logo_url();
    if ($custom_logo_url === '') return;

    $logo_url = esc_url($custom_logo_url);
    echo '<style id="meza-login-logo">' .
        '.login h1 a,.login .wp-login-logo a{' .
        'background-image:url("' . $logo_url . '")!important;' .
        'background-size:contain!important;' .
        'background-position:center!important;' .
        'background-repeat:no-repeat!important;' .
        'width:320px!important;' .
        'height:100px!important;' .
        '}' .
        '</style>';
}, 99999);

// Last-resort visual fallback in case a plugin prints toolbar markup late.
add_action('admin_head', function () {
    echo '<style id="meza-admin-bar-hide-updraft">' .
        '#wpadminbar li[id*="updraft"],' .
        '#wpadminbar a[href*="updraft"],' .
        '#wpadminbar .updraft_admin_node,' .
        '#wpadminbar .updraftplus_admin_node{' .
        'display:none!important;' .
        '}' .
        '</style>';
}, 99999);

/** Resolve the site's Primary nav menu so Appearance > Menus opens on a predictable default. */
function meza_get_primary_nav_menu_id(): int
{
    $menu_locations = get_nav_menu_locations();
    $primary_menu_id = (int) ($menu_locations['primary'] ?? 0);

    if ($primary_menu_id > 0) {
        return $primary_menu_id;
    }

    $primary_menu = wp_get_nav_menu_object('Primary');
    if ($primary_menu instanceof WP_Term) {
        return (int) $primary_menu->term_id;
    }

    foreach (wp_get_nav_menus() as $menu) {
        if (!($menu instanceof WP_Term)) continue;

        $menu_name = strtolower(trim((string) $menu->name));
        $menu_slug = strtolower(trim((string) $menu->slug));

        if ($menu_name === 'primary' || $menu_slug === 'primary') {
            return (int) $menu->term_id;
        }
    }

    return 0;
}

function meza_title_case_label(string $label): string
{
    $normalized = trim(preg_replace('/\s+/', ' ', str_replace(['-', '_'], ' ', $label)));
    if ($normalized === '') {
        return '';
    }

    return ucwords(strtolower($normalized));
}

/** Limit Menus screen object pickers to content types that actually expose front-end permalinks. */
function meza_nav_menu_object_has_permalink($object): bool
{
    if ($object instanceof WP_Post_Type) {
        if (!empty($object->_builtin) && in_array($object->name, ['post', 'page'], true)) {
            return true;
        }

        return $object->public && is_array($object->rewrite) && !empty($object->rewrite['slug']);
    }

    if ($object instanceof WP_Taxonomy) {
        if ($object->name === 'post_tag') {
            return false;
        }

        return $object->public
            && $object->publicly_queryable
            && is_array($object->rewrite)
            && !empty($object->rewrite['slug']);
    }

    return false;
}

// Only register Menus screen object panels for post types and taxonomies that have front-end permalinks.
add_filter('nav_menu_meta_box_object', function ($object) {
    if (!($object instanceof WP_Post_Type) && !($object instanceof WP_Taxonomy)) {
        return $object;
    }

    return meza_nav_menu_object_has_permalink($object) ? $object : false;
}, 1000);

// Default Appearance > Menus to the Primary menu unless a specific menu or tab was requested.
add_action('load-nav-menus.php', function (): void {
    if (!current_user_can('edit_theme_options')) return;

    $action = isset($_REQUEST['action']) ? sanitize_key((string) wp_unslash($_REQUEST['action'])) : 'edit';
    if ($action !== 'edit') return;

    if (array_key_exists('menu', $_REQUEST)) return;

    $primary_menu_id = meza_get_primary_nav_menu_id();
    if ($primary_menu_id <= 0) return;

    wp_safe_redirect(add_query_arg(['menu' => $primary_menu_id], admin_url('nav-menus.php')));
    exit;
}, 1);

add_action('load-nav-menus.php', function (): void {
    if (!current_user_can('edit_theme_options')) return;

    ob_start(static function (string $html): string {
        $html = preg_replace(
            '/(<li class="control-section accordion-section\s+)([^"]*\s)?open(\s[^"]*)?(" id="[^"]+">)/',
            '$1$2$3$4',
            $html,
            1
        );

        $html = preg_replace(
            '/(<button type="button" class="accordion-trigger" )aria-expanded="true"/',
            '$1aria-expanded="false"',
            $html,
            1
        );

        return $html;
    });
}, 2);

// Remove WooCommerce's custom endpoints box from Appearance > Menus and Screen Options.
add_action('admin_head-nav-menus.php', function (): void {
    remove_meta_box('woocommerce_endpoints_nav_link', 'nav-menus', 'side');
}, 1000);

// Menus screen: hide the "Manage with Live Preview" action.
add_action('admin_head-nav-menus.php', function (): void {
    echo '<style id="meza-nav-menus-hide-live-preview">.nav-menus-php .page-title-action.hide-if-no-customize{display:none!important;}</style>';
}, 1000);

add_action('admin_head-users.php', function (): void {
    echo '<style id="meza-users-list-layout">'
        . '.users-php .wp-list-table .check-column{width:32px;min-width:32px;max-width:32px;}'
        . '.users-php .wp-list-table .column-username{width:220px;min-width:220px;max-width:220px;}'
        . '.users-php .wp-list-table .column-name{width:220px;min-width:220px;max-width:220px;}'
        . '.users-php .wp-list-table .column-email{width:260px;min-width:260px;max-width:260px;}'
        . '.users-php .wp-list-table .column-role{width:180px;min-width:180px;max-width:180px;}'
        . '.users-php .wp-list-table .column-website{width:200px;min-width:200px;max-width:200px;}'
        . '.users-php .wp-list-table .column-last_login{width:230px;min-width:230px;max-width:230px;}'
        . '.users-php .wp-list-table td.column-email,.users-php .wp-list-table td.column-website{white-space:normal;overflow-wrap:anywhere;word-break:break-word;}'
        . '.users-php .tablenav.top{display:none!important;}'
        . '</style>';
    ?>
    <script id="meza-users-hide-two-factor-ui">
        (() => {
            const normalize = (text) => String(text || '').replace(/\s+/g, ' ').trim().toLowerCase();
            const matchesTwoFactor = (text) => /\b(two[\s-]*factor|2fa)\b/i.test(normalize(text));

            const hideMatchingScreenOptions = () => {
                document.querySelectorAll('#screen-options-wrap label').forEach((label) => {
                    if (!matchesTwoFactor(label.textContent)) return;
                    label.style.display = 'none';
                });
            };

            const hideMatchingUserColumns = () => {
                const columnIndexes = [];

                document.querySelectorAll('.users-php .wp-list-table thead th').forEach((cell, index) => {
                    if (!matchesTwoFactor(cell.textContent)) return;
                    columnIndexes.push(index);
                });

                if (columnIndexes.length === 0) return;

                document.querySelectorAll('.users-php .wp-list-table tr').forEach((row) => {
                    row.querySelectorAll('th, td').forEach((cell, index) => {
                        if (!columnIndexes.includes(index)) return;
                        cell.style.display = 'none';
                    });
                });
            };

            const apply = () => {
                hideMatchingScreenOptions();
                hideMatchingUserColumns();
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', apply, { once: true });
            } else {
                apply();
            }
        })();
    </script>
    <?php
}, 1000);

// Menus screen: show all remaining Add Menu Items panels by default for first-load users.
add_filter('default_hidden_meta_boxes', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen) || $screen->id !== 'nav-menus') return $hidden;

    return [];
}, 200, 2);

// Menus screen: keep all remaining Add Menu Items panels visible for all users.
add_filter('hidden_meta_boxes', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen) || $screen->id !== 'nav-menus') return $hidden;

    return [];
}, 200, 2);

// Menus screen: keep all advanced menu item properties enabled for first-load users.
add_filter('default_hidden_columns', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen) || $screen->id !== 'nav-menus') return $hidden;

    $advanced_fields = ['link-target', 'title-attribute', 'css-classes', 'xfn', 'description'];
    return array_values(array_diff((array) $hidden, $advanced_fields));
}, 200, 2);

// Menus screen: keep all advanced menu item properties enabled for all users.
add_filter('hidden_columns', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen) || $screen->id !== 'nav-menus') return $hidden;

    $advanced_fields = ['link-target', 'title-attribute', 'css-classes', 'xfn', 'description'];
    return array_values(array_diff((array) $hidden, $advanced_fields));
}, 200, 2);

// Menus screen: remove the "Show advanced menu properties" section from Screen Options.
add_filter('manage_nav-menus_columns', function ($columns) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || $screen->id !== 'nav-menus') {
        return $columns;
    }

    return [];
}, 1000);

// Menus screen: keep the Pages "All" list in straight alphabetical order.
add_filter('nav_menu_items_page', function ($posts) {
    if (!is_array($posts)) {
        return $posts;
    }

    usort($posts, function ($a, $b): int {
        $a_title = trim((string) (($a->post_title ?? $a->title ?? $a->label ?? '')));
        $b_title = trim((string) (($b->post_title ?? $b->title ?? $b->label ?? '')));

        return strcasecmp($a_title, $b_title);
    });

    return $posts;
}, 1000);

function meza_force_nav_menu_default_tab(string $tab_name, string $search_key): array
{
    $had_tab = array_key_exists($tab_name, $_REQUEST);
    $original_tab = $had_tab ? $_REQUEST[$tab_name] : null;

    if (!$had_tab && empty($_REQUEST[$search_key])) {
        $_REQUEST[$tab_name] = 'all';
    }

    return [
        'had_tab' => $had_tab,
        'original_tab' => $original_tab,
    ];
}

function meza_restore_nav_menu_default_tab(string $tab_name, array $state): void
{
    if (!empty($state['had_tab'])) {
        $_REQUEST[$tab_name] = $state['original_tab'];
        return;
    }

    unset($_REQUEST[$tab_name]);
}

function meza_customize_nav_menu_tab_list_markup(string $html): string
{
    return (string) preg_replace_callback(
        '/(<ul[^>]*class="[^"]*add-menu-item-tabs[^"]*"[^>]*>)(.*?)(<\/ul>)/s',
        static function ($matches): string {
            preg_match_all('/<li\b.*?<\/li>/s', $matches[2], $items);
            $tabs = $items[0] ?? [];

            usort($tabs, static function (string $a, string $b): int {
                $rank = static function (string $tab): int {
                    $text = strtolower(trim((string) wp_strip_all_tags($tab)));

                    if (str_starts_with($text, 'all ') || $text === 'view all') return 0;
                    if ($text === 'most used' || $text === 'most recent') return 1;
                    if ($text === 'search') return 2;

                    return 99;
                };

                return $rank($a) <=> $rank($b);
            });

            return $matches[1] . implode('', $tabs) . $matches[3];
        },
        $html,
        1
    );
}

function meza_customize_post_type_nav_menu_markup(string $html, string $title, string $post_type_name): string
{
    $html = str_replace('>View All<', '>' . esc_html('All ' . meza_title_case_label($title)) . '<', $html);
    $html = preg_replace(
        '/<li\b[^>]*>\s*<a class="nav-tab-link"[^>]*data-type="' . preg_quote("tabs-panel-posttype-{$post_type_name}-most-recent", '/') . '".*?<\/li>\s*/s',
        '',
        $html
    );
    $html = preg_replace(
        '/<div id="' . preg_quote("tabs-panel-posttype-{$post_type_name}-most-recent", '/') . '"[\s\S]*?<\/div><!-- \/.tabs-panel -->\s*/',
        '',
        $html
    );

    return meza_customize_nav_menu_tab_list_markup($html);
}

function meza_customize_taxonomy_nav_menu_markup(string $html, string $title): string
{
    $html = str_replace('>View All<', '>' . esc_html('All ' . meza_title_case_label($title)) . '<', $html);

    return meza_customize_nav_menu_tab_list_markup($html);
}

function meza_get_default_taxonomy_term_id(string $taxonomy): int
{
    $taxonomy = sanitize_key($taxonomy);
    if ($taxonomy === '') {
        return 0;
    }

    $default_term = get_option('default_term_' . $taxonomy);
    if (is_array($default_term)) {
        $default_term = $default_term['term_id'] ?? 0;
    }

    $default_term_id = (int) $default_term;
    if ($default_term_id > 0) {
        return $default_term_id;
    }

    return (int) get_option('default_' . $taxonomy, 0);
}

function meza_should_hide_single_default_term_nav_menu_taxonomy(string $taxonomy): bool
{
    $taxonomy = sanitize_key($taxonomy);
    if ($taxonomy === '') {
        return false;
    }

    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'fields' => 'ids',
        'number' => 2,
    ]);

    if (is_wp_error($terms) || count((array) $terms) !== 1) {
        return false;
    }

    $default_term_id = meza_get_default_taxonomy_term_id($taxonomy);
    if ($default_term_id <= 0) {
        return false;
    }

    return (int) $terms[0] === $default_term_id;
}

function meza_nav_menu_item_post_type_meta_box($data_object, $box): void
{
    $post_type_name = (string) ($box['args']->name ?? '');
    $tab_name = $post_type_name . '-tab';
    $search_key = "quick-search-posttype-{$post_type_name}";
    $state = meza_force_nav_menu_default_tab($tab_name, $search_key);

    ob_start();
    wp_nav_menu_item_post_type_meta_box($data_object, $box);
    $html = (string) ob_get_clean();

    meza_restore_nav_menu_default_tab($tab_name, $state);

    echo meza_customize_post_type_nav_menu_markup($html, trim(wp_strip_all_tags((string) ($box['title'] ?? ''))), $post_type_name);
}

function meza_nav_menu_item_taxonomy_meta_box($data_object, $box): void
{
    $taxonomy_name = (string) ($box['args']->name ?? '');
    $tab_name = $taxonomy_name . '-tab';
    $search_key = "quick-search-taxonomy-{$taxonomy_name}";
    $state = meza_force_nav_menu_default_tab($tab_name, $search_key);

    ob_start();
    wp_nav_menu_item_taxonomy_meta_box($data_object, $box);
    $html = (string) ob_get_clean();

    meza_restore_nav_menu_default_tab($tab_name, $state);

    echo meza_customize_taxonomy_nav_menu_markup($html, trim(wp_strip_all_tags((string) ($box['title'] ?? ''))));
}

function meza_get_sorted_nav_menu_meta_boxes(): array
{
    global $wp_meta_boxes;

    $sorted = [];
    $side_boxes = $wp_meta_boxes['nav-menus']['side'] ?? [];

    foreach ((array) $side_boxes as $priority => $boxes) {
        foreach ((array) $boxes as $id => $box) {
            if (!is_array($box)) continue;
            if ($id === 'woocommerce_endpoints_nav_link') continue;

            $is_taxonomy_box = (($box['callback'] ?? null) === 'wp_nav_menu_item_taxonomy_meta_box');
            $taxonomy_name = sanitize_key((string) ($box['args']->name ?? ''));
            if ($is_taxonomy_box && $taxonomy_name !== '' && meza_should_hide_single_default_term_nav_menu_taxonomy($taxonomy_name)) {
                continue;
            }

            $title = trim(wp_strip_all_tags((string) ($box['title'] ?? '')));
            $sort_title = strtolower($title);
            $is_custom_links = $id === 'add-custom-links' || $sort_title === 'custom links';

            if (($box['callback'] ?? null) === 'wp_nav_menu_item_post_type_meta_box') {
                $box['callback'] = 'meza_nav_menu_item_post_type_meta_box';
            } elseif (($box['callback'] ?? null) === 'wp_nav_menu_item_taxonomy_meta_box') {
                $box['callback'] = 'meza_nav_menu_item_taxonomy_meta_box';
            }

            $box['title'] = meza_title_case_label($title);

            $box['_meza_sort_title'] = $sort_title;
            $box['_meza_custom_links'] = $is_custom_links;
            $sorted[$id] = $box;
        }
    }

    uasort($sorted, static function (array $a, array $b): int {
        $a_custom = !empty($a['_meza_custom_links']);
        $b_custom = !empty($b['_meza_custom_links']);

        if ($a_custom && !$b_custom) return 1;
        if (!$a_custom && $b_custom) return -1;

        return strcmp((string) ($a['_meza_sort_title'] ?? ''), (string) ($b['_meza_sort_title'] ?? ''));
    });

    foreach ($sorted as &$box) {
        unset($box['_meza_sort_title'], $box['_meza_custom_links']);
    }
    unset($box);

    return $sorted;
}

function meza_customize_nav_menu_meta_boxes(): void
{
    global $wp_meta_boxes;

    if (!isset($wp_meta_boxes['nav-menus']['side'])) {
        return;
    }

    $wp_meta_boxes['nav-menus']['side'] = [
        'default' => meza_get_sorted_nav_menu_meta_boxes(),
    ];
}
add_action('admin_head-nav-menus.php', 'meza_customize_nav_menu_meta_boxes', 1001);

// Menus screen: keep Add Menu Items panels collapsed by default.
add_filter('get_user_option_closedpostboxes_nav-menus', function ($value) {
    $boxes = meza_get_sorted_nav_menu_meta_boxes();
    return array_keys($boxes);
});

/** ================================
 *  THEME-AGNOSTIC EDITORIAL BEHAVIOR
 *  ================================ */

/** Media policy: allow SVG uploads, but only for administrators who can manage site-wide settings. */
function meza_allow_svg_uploads(array $mimes): array
{
    if (current_user_can('manage_options')) {
        $mimes['svg'] = 'image/svg+xml';
    }

    return $mimes;
}
add_filter('upload_mimes', 'meza_allow_svg_uploads');

/** ACF setup: pull the Google API key from the shared theme filter so Maps fields work in the editor. */
function meza_extend_acf_init(): void
{
    $gcloud_key = (string) apply_filters('theme_gcloud_key', '');
    if ($gcloud_key === '') return;

    acf_update_setting('google_api_key', $gcloud_key);
}
add_action('acf/init', 'meza_extend_acf_init');

/** ACF setup: center new map fields on a sensible default location so editors start from the right region. */
add_filter('acf/fields/google_map/api', function ($api) {
    $api['center_lat'] = 38.3032;
    $api['center_lng'] = -77.4605;
    $api['zoom'] = 14;

    return $api;
});

/** Media hygiene: prevent WordPress from auto-filling image titles from filenames on first upload. */
add_filter('wp_insert_attachment_data', function ($data, $postarr) {
    if (
        empty($postarr['ID'])
        && isset($postarr['post_mime_type'])
        && wp_match_mime_types('image', $postarr['post_mime_type'])
    ) {
        $data['post_title'] = '';
    }

    return $data;
}, 10, 2);

/** Dashboard: replace the default "At a Glance" list with counts for the post types editors actually manage. */
function meza_replace_glance_items()
{
    $custom_items = [];

    $post_types = get_post_types(['show_ui' => true], 'objects');

    foreach ($post_types as $post_type) {
        $post_type_name = (string) ($post_type->name ?? '');
        if ($post_type_name === '') {
            continue;
        }

        if (
            in_array($post_type_name, ['attachment', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request'], true)
            || str_starts_with($post_type_name, 'wp_')
            || (function_exists('meza_is_acf_admin_post_type') && meza_is_acf_admin_post_type($post_type_name))
        ) {
            continue;
        }

        if (empty($post_type->show_ui) || empty($post_type->cap->edit_posts) || !current_user_can($post_type->cap->edit_posts)) {
            continue;
        }

        if (!current_user_can($post_type->cap->edit_posts)) {
            continue;
        }

        $count_obj = wp_count_posts($post_type_name);
        $published = (int) $count_obj->publish;

        if ($published === 0) {
            continue;
        }

        $label = $published === 1 ? $post_type->labels->singular_name : $post_type->labels->name;
        $url = $post_type_name === 'post'
            ? admin_url('edit.php')
            : admin_url('edit.php?post_type=' . $post_type_name);
        $icon_class = meza_get_post_type_dashicon_class($post_type_name);

        $custom_items[] = [
            'name' => 'meza-' . $post_type_name,
            'count' => $published,
            'label' => $label,
            'url' => $url,
            'icon' => $icon_class,
        ];
    }

    $can_view_gravity_forms = current_user_can('gravityforms_edit_forms')
        || current_user_can('gform_full_access')
        || (class_exists('GFCommon') && method_exists('GFCommon', 'current_user_can_any') && GFCommon::current_user_can_any('gravityforms_edit_forms'));

    if ($can_view_gravity_forms) {
        $form_count = 0;

        if (class_exists('GFFormsModel') && method_exists('GFFormsModel', 'get_forms')) {
            $forms = GFFormsModel::get_forms(null, 'title', 'ASC', false);
            $form_count = is_array($forms) ? count($forms) : 0;
        } elseif (class_exists('GFAPI') && method_exists('GFAPI', 'get_forms')) {
            $forms = GFAPI::get_forms(true, false, 'title', 'ASC');
            $form_count = is_array($forms) ? count($forms) : 0;
        }

        if ($form_count === 0) {
            global $wpdb;

            $table_name = $wpdb->prefix . 'gf_form';
            $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
            if ($table_exists === $table_name) {
                $form_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE is_trash = 0");
            }
        }

        if ($form_count > 0) {
            $custom_items[] = [
                'name' => 'meza-gravityforms',
                'count' => $form_count,
                'label' => $form_count === 1 ? 'Form' : 'Forms',
                'url' => admin_url('admin.php?page=gf_edit_forms'),
                'icon' => meza_get_keyword_dashicon_for_top_level_menu_item('Forms', 'gf_edit_forms'),
            ];
        }
    }

    usort($custom_items, function ($a, $b) {
        return $b['count'] <=> $a['count'];
    });

    foreach ($custom_items as $item) {
        $label = sprintf('%s %s', number_format_i18n($item['count']), $item['label']);
        printf(
            '<li class="%s"><a href="%s"><span class="dashicons %s"></span> %s</a></li>',
            esc_attr($item['name']),
            esc_url($item['url']),
            esc_attr($item['icon']),
            esc_html($label)
        );
    }
}
add_action('dashboard_glance_items', 'meza_replace_glance_items');

/** Dashboard: hide the stock core counters so they do not duplicate the custom editorial counts above. */
function meza_hide_default_glance_items_css(): void
{
    echo '<style>
        #dashboard_right_now .post-count,
        #dashboard_right_now .page-count,
        #dashboard_right_now .comment-count,
        #dashboard_right_now .comment-mod-count {
            display: none !important;
        }
        #dashboard_right_now .search-engines-info:before, #dashboard_right_now li a:before, #dashboard_right_now li>span:before {
            content: none;
        }
    </style>';
}
add_action('admin_head', 'meza_hide_default_glance_items_css');

/** ================================
 *  WYSIWYG NORMALIZATION
 *  ================================ */

/**
 * Normalize editor HTML by removing inline styling noise and converting simple presentational tags to semantic ones.
 * This keeps stored content cleaner and makes front-end output more predictable.
 */
function meza_normalize_wysiwyg_markup(string $html): string
{
    $html = preg_replace('/<(li|span)([^>]*) style="[^"]*"([^>]*)>/i', '<$1$2$3>', $html);
    $html = preg_replace('/<span[^>]*>\s*<\/span>/i', '', $html);
    $html = preg_replace('/<span[^>]*>\s*(<[^>]+>)\s*<\/span>/i', '$1', $html);
    $html = str_replace(['<b>', '</b>', '<i>', '</i>'], ['<strong>', '</strong>', '<em>', '</em>'], $html);

    return $html;
}

/** TinyMCE defaults: reduce extra wrappers and formatting noise before editors even save the field. */
function meza_acf_tinymce_custom_settings($init)
{
    $init['forced_root_block'] = false;
    $init['force_p_newlines'] = true;
    $init['removeformat'] = 'span';
    $init['valid_elements'] = '*[*]';
    return $init;
}
add_filter('tiny_mce_before_init', 'meza_acf_tinymce_custom_settings');

/** Save-time cleanup for ACF WYSIWYG fields so the database stores the normalized version of the content. */
function meza_acf_strip_inline_styles($value, $post_id, $field)
{
    return meza_normalize_wysiwyg_markup((string) $value);
}
add_filter('acf/update_value/type=wysiwyg', 'meza_acf_strip_inline_styles', 10, 3);

/** Extra save-time cleanup for span wrappers that can survive the broader normalization pass. */
function meza_acf_strip_spans_around_elements($value, $post_id, $field)
{
    return preg_replace('/<span[^>]*>\s*(<[^>]+>)\s*<\/span>/i', '$1', $value);
}
add_filter('acf/update_value/type=wysiwyg', 'meza_acf_strip_spans_around_elements', 10, 3);

/** Render-time cleanup so existing legacy content is cleaned up even if it was saved before these rules existed. */
function meza_acf_clean_wysiwyg_output($content)
{
    return meza_normalize_wysiwyg_markup((string) $content);
}
add_filter('acf_the_content', 'meza_acf_clean_wysiwyg_output');
add_filter('the_content', 'meza_acf_clean_wysiwyg_output');

/**
 * Default the Yoast SEO panel to collapsed on post edit screens,
 * while still persisting whatever state the editor chooses afterward.
 */
add_action('admin_footer-post.php', 'meza_render_yoast_panel_state_script');
add_action('admin_footer-post-new.php', 'meza_render_yoast_panel_state_script');
add_action('admin_footer-post.php', 'meza_render_post_panel_label_sync_script', 1001);
add_action('admin_footer-post-new.php', 'meza_render_post_panel_label_sync_script', 1001);

function meza_render_yoast_panel_state_script(): void
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || (string) ($screen->base ?? '') !== 'post') {
        return;
    }

    $post_type = (string) ($screen->post_type ?? '');
    $taxonomy_labels = [];

    if ($post_type !== '') {
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        if (is_array($taxonomies)) {
            foreach ($taxonomies as $taxonomy) {
                if (!($taxonomy instanceof WP_Taxonomy) || empty($taxonomy->show_ui)) continue;

                $taxonomy_labels[] = strtolower(trim(wp_strip_all_tags((string) ($taxonomy->labels->name ?? ''))));
                $taxonomy_labels[] = strtolower(trim(wp_strip_all_tags((string) ($taxonomy->labels->singular_name ?? ''))));
            }
        }
    }

    $taxonomy_labels = array_values(array_unique(array_filter($taxonomy_labels, static function ($label): bool {
        return $label !== '';
    })));
?>
    <script id="meza-yoast-panel-state">
        (() => {
            const storageKey = 'mezaYoastPanelOpen';
            const defaultOpen = false;
            const boundTargets = new WeakSet();
            const taxonomyLabels = <?php echo wp_json_encode($taxonomy_labels); ?> || [];

            const normalize = (value) => String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
            const isElement = (value) => value instanceof HTMLElement;
            const getBlockEditorPanel = (element) => element?.closest?.('.components-panel__body, .editor-post-panel, .plugin-document-setting-panel') ||
                element?.parentElement ||
                null;
            const getSidebarButtons = () => {
                const selectors = [
                    '.edit-post-sidebar button[aria-expanded]',
                    '.editor-sidebar button[aria-expanded]',
                    '.interface-complementary-area button[aria-expanded]',
                    '.interface-interface-skeleton__sidebar button[aria-expanded]',
                ];

                return selectors.flatMap((selector) => Array.from(document.querySelectorAll(selector)));
            };
            const getButtonText = (button) => normalize(button?.textContent);
            const getButtonControls = (button) => normalize(button?.getAttribute('aria-controls'));
            const getButtonLabelledBy = (button) => normalize(button?.getAttribute('aria-labelledby'));
            const matchesPanelButton = (button, matches) => {
                if (!isElement(button)) return false;

                return matches({
                    button,
                    label: getButtonText(button),
                    controls: getButtonControls(button),
                    labelledBy: getButtonLabelledBy(button),
                });
            };
            const findBlockEditorPanelByButton = (matches) => {
                const button = getSidebarButtons().find((candidate) => matchesPanelButton(candidate, matches));
                return button ? getBlockEditorPanel(button) : null;
            };
            const getBlockEditorPanelSiblings = (container) => Array.from(container?.children || []).filter(isElement);
            const isTaxonomyPanelButton = ({
                label,
                controls,
                labelledBy
            }) => {
                if (controls.includes('taxonomy') || labelledBy.includes('taxonomy')) return true;

                return taxonomyLabels.some((taxonomyLabel) => taxonomyLabel !== '' && label === taxonomyLabel);
            };

            const readPreference = () => {
                try {
                    const value = window.localStorage.getItem(storageKey);
                    if (value === 'open') return true;
                    if (value === 'closed') return false;
                } catch (error) {}

                return null;
            };

            const writePreference = (isOpen) => {
                try {
                    window.localStorage.setItem(storageKey, isOpen ? 'open' : 'closed');
                } catch (error) {}
            };

            const isAcfMetabox = (element) => {
                if (!isElement(element) || element.id === 'wpseo_meta') return false;

                const id = normalize(element.id);
                return id.startsWith('acf-') ||
                    element.classList.contains('acf-postbox') ||
                    element.querySelector('.acf-fields, .acf-postbox, [data-acf]');
            };

            const moveClassicYoastToBottom = () => {
                const metabox = document.getElementById('wpseo_meta');
                if (!isElement(metabox) || !isElement(metabox.parentElement)) return;

                const container = metabox.parentElement;
                const siblings = Array.from(container.children).filter(isElement);
                const acfBoxes = siblings.filter(isAcfMetabox);
                const anchor = acfBoxes.length > 0 ?
                    acfBoxes[acfBoxes.length - 1] :
                    siblings.filter((element) => element !== metabox && element.classList.contains('postbox')).at(-1) || null;

                if (anchor) {
                    if (anchor.nextElementSibling !== metabox) {
                        anchor.after(metabox);
                    }
                    return;
                }

                if (container.lastElementChild !== metabox) {
                    container.appendChild(metabox);
                }
            };

            const moveBlockEditorYoastToBottom = () => {
                const target = getBlockEditorTarget();
                if (!target || !isElement(target.key)) return;

                const panel = getBlockEditorPanel(target.key);

                if (!isElement(panel) || !isElement(panel.parentElement)) return;

                const container = panel.parentElement;
                if (container.lastElementChild !== panel) {
                    container.appendChild(panel);
                }
            };

            const moveBlockEditorFeaturedImageUnderPublish = () => {
                const featuredImagePanel = findBlockEditorPanelByButton(({
                    label,
                    controls,
                    labelledBy
                }) => (
                    label.includes('featured image') ||
                    controls.includes('featured-image') ||
                    labelledBy.includes('featured-image')
                ));

                if (!isElement(featuredImagePanel) || !isElement(featuredImagePanel.parentElement)) return;

                const container = featuredImagePanel.parentElement;
                const anchor = findBlockEditorPanelByButton(({
                    label,
                    controls,
                    labelledBy
                }) => (
                    label === 'summary' ||
                    label === 'excerpt' ||
                    label === 'subhead' ||
                    label.includes('publish') ||
                    label.includes('status') ||
                    controls.includes('post-status') ||
                    labelledBy.includes('post-status')
                ));
                const taxonomyAnchor = findBlockEditorPanelByButton(isTaxonomyPanelButton);
                const siblings = getBlockEditorPanelSiblings(container);
                const anchorIndex = (isElement(anchor) && anchor.parentElement === container) ?
                    siblings.indexOf(anchor) :
                    -1;
                const taxonomyIndex = (
                        isElement(taxonomyAnchor) &&
                        taxonomyAnchor.parentElement === container &&
                        taxonomyAnchor !== featuredImagePanel
                    ) ?
                    siblings.indexOf(taxonomyAnchor) :
                    -1;

                if (anchorIndex >= 0) {
                    if (anchor.nextElementSibling !== featuredImagePanel) {
                        anchor.after(featuredImagePanel);
                    }
                    return;
                }

                if (taxonomyIndex >= 0) {
                    if (taxonomyAnchor.previousElementSibling !== featuredImagePanel) {
                        container.insertBefore(featuredImagePanel, taxonomyAnchor);
                    }
                    return;
                }

                const fallbackAnchor = siblings.find((panel) => panel !== featuredImagePanel) || null;
                const targetAnchor = (isElement(anchor) && anchor.parentElement === container && anchor !== featuredImagePanel) ?
                    anchor :
                    fallbackAnchor;

                if (!isElement(targetAnchor)) return;
                if (targetAnchor.nextElementSibling === featuredImagePanel) return;

                targetAnchor.after(featuredImagePanel);
            };

            const moveBlockEditorAttributesAheadOfTaxonomies = () => {
                const attributesPanel = findBlockEditorPanelByButton(({
                    label,
                    controls,
                    labelledBy
                }) => (
                    label === 'attributes' ||
                    controls.includes('page-attributes') ||
                    labelledBy.includes('page-attributes')
                ));

                if (!isElement(attributesPanel) || !isElement(attributesPanel.parentElement)) return;

                const container = attributesPanel.parentElement;
                const taxonomyAnchor = findBlockEditorPanelByButton(isTaxonomyPanelButton);
                if (
                    !isElement(taxonomyAnchor) ||
                    taxonomyAnchor.parentElement !== container ||
                    taxonomyAnchor === attributesPanel
                ) {
                    return;
                }

                const featuredImagePanel = findBlockEditorPanelByButton(({
                    label,
                    controls,
                    labelledBy
                }) => (
                    label.includes('featured image') ||
                    controls.includes('featured-image') ||
                    labelledBy.includes('featured-image')
                ));
                const publishAnchor = findBlockEditorPanelByButton(({
                    label,
                    controls,
                    labelledBy
                }) => (
                    label === 'summary' ||
                    label === 'excerpt' ||
                    label === 'subhead' ||
                    label.includes('publish') ||
                    label.includes('status') ||
                    controls.includes('post-status') ||
                    labelledBy.includes('post-status')
                ));
                const anchor = (
                        isElement(featuredImagePanel) &&
                        featuredImagePanel.parentElement === container &&
                        featuredImagePanel !== attributesPanel
                    ) ?
                    featuredImagePanel :
                    (
                        isElement(publishAnchor) &&
                        publishAnchor.parentElement === container &&
                        publishAnchor !== attributesPanel ?
                        publishAnchor :
                        null
                    );

                if (isElement(anchor)) {
                    if (anchor.nextElementSibling !== attributesPanel) {
                        anchor.after(attributesPanel);
                    }
                    return;
                }

                if (taxonomyAnchor.previousElementSibling !== attributesPanel) {
                    container.insertBefore(attributesPanel, taxonomyAnchor);
                }
            };

            const moveYoastToBottom = () => {
                moveBlockEditorFeaturedImageUnderPublish();
                moveBlockEditorAttributesAheadOfTaxonomies();
                moveClassicYoastToBottom();
                moveBlockEditorYoastToBottom();
            };

            const getClassicTarget = () => {
                const metabox = document.getElementById('wpseo_meta');
                if (!metabox) return null;

                return {
                    key: metabox,
                    isOpen: () => !metabox.classList.contains('closed'),
                    setOpen: (shouldOpen) => {
                        const toggle = metabox.querySelector('.handlediv, .postbox-header button.handlediv, .hndle');
                        if (toggle && !toggle.disabled) {
                            toggle.click();
                        }
                    },
                    bind: () => {
                        const observer = new MutationObserver(() => {
                            writePreference(!metabox.classList.contains('closed'));
                        });

                        observer.observe(metabox, {
                            attributes: true,
                            attributeFilter: ['class'],
                        });
                    },
                };
            };

            const getBlockEditorTarget = () => {
                for (const button of getSidebarButtons()) {
                    const label = getButtonText(button);
                    const controls = getButtonControls(button);
                    const labelledBy = getButtonLabelledBy(button);
                    const isYoastButton = label.includes('yoast seo') ||
                        controls.includes('yoast') ||
                        labelledBy.includes('yoast');

                    if (!isYoastButton) continue;

                    return {
                        key: button,
                        isOpen: () => button.getAttribute('aria-expanded') === 'true',
                        setOpen: (shouldOpen) => {
                            if (!button.disabled) {
                                button.click();
                            }
                        },
                        bind: () => {
                            const observer = new MutationObserver(() => {
                                writePreference(button.getAttribute('aria-expanded') === 'true');
                            });

                            observer.observe(button, {
                                attributes: true,
                                attributeFilter: ['aria-expanded'],
                            });
                        },
                    };
                }

                return null;
            };

            const getTarget = () => getBlockEditorTarget() || getClassicTarget();

            const syncTarget = () => {
                moveYoastToBottom();

                const target = getTarget();
                if (!target) return;

                if (!boundTargets.has(target.key)) {
                    boundTargets.add(target.key);
                    target.bind();
                }

                const preference = readPreference();
                const desiredOpen = preference === null ? defaultOpen : preference;
                const isOpen = target.isOpen();

                if (isOpen !== desiredOpen) {
                    target.setOpen(desiredOpen);
                    return;
                }

                if (preference === null) {
                    writePreference(desiredOpen);
                }
            };

            const start = () => {
                syncTarget();

                const observer = new MutationObserver(() => {
                    window.requestAnimationFrame(syncTarget);
                });

                observer.observe(document.body, {
                    childList: true,
                    subtree: true,
                });

                window.setTimeout(syncTarget, 300);
                window.setTimeout(syncTarget, 1000);
                window.setTimeout(syncTarget, 2000);
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', start, {
                    once: true
                });
            } else {
                start();
            }
        })();
    </script>
<?php
}

function meza_render_post_panel_label_sync_script(): void
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || (string) ($screen->base ?? '') !== 'post') {
        return;
    }

    $post_type = (string) ($screen->post_type ?? '');
    $panel_labels = meza_get_post_edit_panel_admin_label_map($post_type);
    if (empty($panel_labels)) {
        return;
    }

    $replacements = [];

    if (isset($panel_labels['postimagediv'])) {
        $replacements[] = [
            'from' => ['featured image'],
            'to' => (string) $panel_labels['postimagediv'],
        ];
    }

    if (isset($panel_labels['postexcerpt'])) {
        $replacements[] = [
            'from' => ['excerpt', 'summary', 'subhead'],
            'to' => (string) $panel_labels['postexcerpt'],
        ];
    }

    if (isset($panel_labels['slugdiv'])) {
        $replacements[] = [
            'from' => ['slug'],
            'to' => (string) $panel_labels['slugdiv'],
        ];
    }

    if (empty($replacements)) {
        return;
    }

?>
    <script id="meza-post-panel-label-sync">
        (() => {
            const replacements = <?php echo wp_json_encode($replacements); ?>;
            const scopeSelectors = [
                '.edit-post-sidebar',
                '.editor-sidebar',
                '.interface-complementary-area',
                '.preferences-modal',
                '.edit-post-preferences-modal',
                '.components-modal__frame',
            ];
            const normalize = (value) => String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
            const isElement = (value) => value instanceof HTMLElement;
            const shouldReplaceText = (nodeValue, replacement) => replacement.from.includes(normalize(nodeValue));

            const syncScope = (scope) => {
                if (!isElement(scope)) return;

                const walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT);
                let node = walker.nextNode();

                while (node) {
                    const parent = node.parentElement;
                    if (parent && !parent.closest('[contenteditable="true"], .block-editor-block-list__layout, .editor-styles-wrapper')) {
                        const text = normalize(node.nodeValue);

                        for (const replacement of replacements) {
                            if (!shouldReplaceText(text, replacement)) continue;
                            node.nodeValue = replacement.to;
                            break;
                        }
                    }

                    node = walker.nextNode();
                }
            };

            const syncLabels = () => {
                scopeSelectors.forEach((selector) => {
                    document.querySelectorAll(selector).forEach(syncScope);
                });
            };

            const start = () => {
                syncLabels();

                const observer = new MutationObserver(() => {
                    window.requestAnimationFrame(syncLabels);
                });

                observer.observe(document.body, {
                    childList: true,
                    subtree: true,
                });

                window.setTimeout(syncLabels, 300);
                window.setTimeout(syncLabels, 1000);
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', start, {
                    once: true
                });
            } else {
                start();
            }
        })();
    </script>
<?php
}

/**
 * Form editor UX: when ACF field groups include a duplicate "Slug" field,
 * hide it so editors use the core permalink/slug UI under the title.
 */
add_filter('acf/prepare_field', function ($field) {
    if (!is_admin()) return $field;
    if (!($field instanceof ArrayAccess) && !is_array($field)) return $field;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen)) return $field;
    if ((string) ($screen->post_type ?? '') !== 'form') return $field;
    if (!in_array((string) ($screen->base ?? ''), ['post', 'post-new'], true)) return $field;

    $name = strtolower((string) ($field['name'] ?? ''));
    $label = strtolower(trim(wp_strip_all_tags((string) ($field['label'] ?? ''))));

    $is_slug_name = in_array($name, ['slug', 'form_slug', 'post_slug'], true);
    $is_slug_label = in_array($label, ['slug', 'form slug', 'post slug'], true);

    if ($is_slug_name || $is_slug_label) {
        return false;
    }

    return $field;
}, 20, 1);
