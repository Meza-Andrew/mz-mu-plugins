<?php

/**
 * Plugin Name: MZ Admin
 * Description: Admin behavior, editorial workflow, and dashboard customization.
 * Version: 1.1.547
 * Author: Meza LLC
 * Author URI: https://meza.design
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) return;

// Prevent core admin menu helpers from receiving a null plugin page slug.
foreach (['_GET', '_POST', '_REQUEST'] as $superglobal_name) {
    if (!isset($GLOBALS[$superglobal_name]) || !is_array($GLOBALS[$superglobal_name])) {
        continue;
    }

    if (array_key_exists('page', $GLOBALS[$superglobal_name]) && $GLOBALS[$superglobal_name]['page'] === null) {
        unset($GLOBALS[$superglobal_name]['page']);
    }
}

if (array_key_exists('plugin_page', $GLOBALS) && $GLOBALS['plugin_page'] === null) {
    unset($GLOBALS['plugin_page']);
}

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

if (!function_exists('meza_normalize_sync_payload')) {
    function meza_normalize_sync_payload($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = meza_normalize_sync_payload($item);
        }

        if ($normalized !== [] && array_keys($normalized) !== range(0, count($normalized) - 1)) {
            ksort($normalized);
        }

        return $normalized;
    }
}

if (!function_exists('meza_get_sync_signature')) {
    function meza_get_sync_signature(array $payload): string
    {
        return md5((string) wp_json_encode(meza_normalize_sync_payload($payload)));
    }
}

if (!function_exists('meza_get_stored_sync_signature')) {
    function meza_get_stored_sync_signature(string $option_name): string
    {
        $signature = get_option($option_name, '');
        return is_string($signature) ? $signature : '';
    }
}

if (!function_exists('meza_store_sync_signature')) {
    function meza_store_sync_signature(string $option_name, string $signature): void
    {
        if ($signature === '') {
            return;
        }

        if (get_option($option_name, null) === null) {
            add_option($option_name, $signature, '', false);
            return;
        }

        update_option($option_name, $signature, false);
    }
}

if (!function_exists('meza_is_plugin_basename_active')) {
    function meza_is_plugin_basename_active(string $plugin_basename): bool
    {
        $plugin_basename = trim($plugin_basename);
        if ($plugin_basename === '') {
            return false;
        }

        $active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
        if (is_array($active_plugins) && in_array($plugin_basename, $active_plugins, true)) {
            return true;
        }

        if (!is_multisite()) {
            return false;
        }

        $sitewide_plugins = get_site_option('active_sitewide_plugins');
        return is_array($sitewide_plugins) && array_key_exists($plugin_basename, $sitewide_plugins);
    }
}

if (!function_exists('meza_set_option_autoload')) {
    function meza_set_option_autoload(string $option_name, bool $autoload): bool
    {
        $option_name = trim($option_name);
        if ($option_name === '') {
            return false;
        }

        global $wpdb;

        if (!($wpdb instanceof wpdb)) {
            return false;
        }

        $current_autoload = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT autoload FROM $wpdb->options WHERE option_name = %s LIMIT 1",
                $option_name
            )
        );

        if (!is_string($current_autoload) || $current_autoload === '') {
            return false;
        }

        $autoload_values = function_exists('wp_autoload_values_to_autoload')
            ? wp_autoload_values_to_autoload()
            : ['yes', 'on', 'auto-on', 'auto'];

        $should_autoload = in_array($current_autoload, $autoload_values, true);
        if ($should_autoload === $autoload) {
            return true;
        }

        $updated = $wpdb->update(
            $wpdb->options,
            ['autoload' => $autoload ? 'on' : 'off'],
            ['option_name' => $option_name],
            ['%s'],
            ['%s']
        );

        if ($updated === false) {
            return false;
        }

        if ($autoload) {
            wp_cache_delete($option_name, 'options');
            wp_cache_delete('alloptions', 'options');
            return true;
        }

        $alloptions = wp_load_alloptions(true);
        if (isset($alloptions[$option_name])) {
            unset($alloptions[$option_name]);
            wp_cache_set('alloptions', $alloptions, 'options');
        }

        return true;
    }
}

if (!function_exists('meza_get_nonautoload_option_names')) {
    function meza_get_nonautoload_option_names(): array
    {
        return [
            'et_google_fonts_cache',
            'monsterinsights_report_data_overview',
            'otgs-installer-log',
            'et_bloom_stats_cache',
        ];
    }
}

if (!function_exists('meza_migrate_nonautoload_options')) {
    function meza_migrate_nonautoload_options(): void
    {
        $migration_option = 'meza_nonautoload_option_migration_20260408_v2';
        if (get_option($migration_option, '') === 'done') {
            return;
        }

        foreach (meza_get_nonautoload_option_names() as $option_name) {
            if (!is_string($option_name) || $option_name === '') {
                continue;
            }

            if (get_option($option_name, null) === null) {
                continue;
            }

            meza_set_option_autoload($option_name, false);
        }

        update_option($migration_option, 'done', false);
    }
}

if (!function_exists('meza_get_transient_cleanup_option_patterns')) {
    function meza_get_transient_cleanup_option_patterns(): array
    {
        return [
            '_site_transient_t15s-registry-gforms',
            '_site_transient_timeout_t15s-registry-gforms',
            '_transient_GFCache_%',
            '_transient_timeout_GFCache_%',
            '_site_transient_GFCache_%',
            '_site_transient_timeout_GFCache_%',
            '_transient_wpv_cached_view_%',
            '_transient_timeout_wpv_cached_view_%',
            '_transient_wpv_cached_form_%',
            '_transient_timeout_wpv_cached_form_%',
            '_transient_wpv_cached_loop_%',
            '_transient_timeout_wpv_cached_loop_%',
            '_transient_wpv_transient_%',
            '_transient_timeout_wpv_transient_%',
            '_transient_toolset_types_cache_sg_%',
            '_transient_timeout_toolset_types_cache_sg_%',
        ];
    }
}

if (!function_exists('meza_delete_options_by_like_patterns')) {
    function meza_delete_options_by_like_patterns(array $patterns): int
    {
        global $wpdb;

        if (!($wpdb instanceof wpdb)) {
            return 0;
        }

        $total_deleted = 0;

        foreach ($patterns as $pattern) {
            $pattern = trim((string) $pattern);
            if ($pattern === '') {
                continue;
            }

            if (str_contains($pattern, '%')) {
                $deleted = $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                        $pattern
                    )
                );
            } else {
                $deleted = $wpdb->delete(
                    $wpdb->options,
                    ['option_name' => $pattern],
                    ['%s']
                );
            }

            if (is_numeric($deleted) && $deleted > 0) {
                $total_deleted += (int) $deleted;
            }
        }

        if ($total_deleted > 0) {
            wp_cache_delete('alloptions', 'options');
        }

        return $total_deleted;
    }
}

if (!function_exists('meza_should_disable_transient_autoload')) {
    function meza_should_disable_transient_autoload(string $transient): bool
    {
        $transient = trim($transient);
        if ($transient === '') {
            return false;
        }

        foreach ([
            'GFCache_',
            'wpv_cached_view_',
            'wpv_cached_form_',
            'wpv_cached_loop_',
            'wpv_transient_',
            'toolset_types_cache_sg_',
        ] as $prefix) {
            if (str_starts_with($transient, $prefix)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('meza_force_transient_option_nonautoload')) {
    function meza_force_transient_option_nonautoload($transient, $value = null, $expiration = 0): void
    {
        if (!is_string($transient)) {
            return;
        }

        $expiration = is_numeric($expiration) ? (int) $expiration : 0;
        if ($expiration > 0 || !meza_should_disable_transient_autoload($transient)) {
            return;
        }

        meza_set_option_autoload('_transient_' . $transient, false);
    }
}

if (!function_exists('meza_cleanup_oversized_transient_caches')) {
    function meza_cleanup_oversized_transient_caches(): void
    {
        $migration_option = 'meza_transient_cache_cleanup_20260408_v2';
        if (get_option($migration_option, '') === 'done') {
            return;
        }

        meza_delete_options_by_like_patterns(meza_get_transient_cleanup_option_patterns());
        update_option($migration_option, 'done', false);
    }
}

if (!function_exists('meza_get_post_counts_cache_ttl')) {
    function meza_get_post_counts_cache_ttl(): int
    {
        $ttl = (int) apply_filters('meza_admin_post_counts_cache_ttl', 300);
        return $ttl > 0 ? $ttl : 300;
    }
}

if (!function_exists('meza_get_post_type_counts_cache_key')) {
    function meza_get_post_type_counts_cache_key(string $post_type): string
    {
        return 'meza_post_counts_' . md5(sanitize_key($post_type));
    }
}

if (!function_exists('meza_get_post_type_counts')) {
    function meza_get_post_type_counts(string $post_type): array
    {
        $post_type = sanitize_key($post_type);
        if ($post_type === '') {
            return [];
        }

        if (!isset($GLOBALS['meza_post_type_counts_request_cache']) || !is_array($GLOBALS['meza_post_type_counts_request_cache'])) {
            $GLOBALS['meza_post_type_counts_request_cache'] = [];
        }

        if (array_key_exists($post_type, $GLOBALS['meza_post_type_counts_request_cache'])) {
            return is_array($GLOBALS['meza_post_type_counts_request_cache'][$post_type])
                ? $GLOBALS['meza_post_type_counts_request_cache'][$post_type]
                : [];
        }

        $transient_key = meza_get_post_type_counts_cache_key($post_type);
        $cached_counts = get_transient($transient_key);

        if (is_array($cached_counts)) {
            $GLOBALS['meza_post_type_counts_request_cache'][$post_type] = $cached_counts;
            return $cached_counts;
        }

        $counts = wp_count_posts($post_type);
        if (!is_object($counts)) {
            $GLOBALS['meza_post_type_counts_request_cache'][$post_type] = [];
            return [];
        }

        $normalized_counts = [];

        foreach (get_object_vars($counts) as $status => $count) {
            $normalized_counts[sanitize_key((string) $status)] = (int) $count;
        }

        $GLOBALS['meza_post_type_counts_request_cache'][$post_type] = $normalized_counts;
        set_transient($transient_key, $normalized_counts, meza_get_post_counts_cache_ttl());

        return $normalized_counts;
    }
}

if (!function_exists('meza_get_published_post_type_count')) {
    function meza_get_published_post_type_count(string $post_type): int
    {
        $counts = meza_get_post_type_counts($post_type);
        return max(0, (int) ($counts['publish'] ?? 0));
    }
}

if (!function_exists('meza_get_nontrashed_post_type_count_cached')) {
    function meza_get_nontrashed_post_type_count_cached(string $post_type): int
    {
        $counts = meza_get_post_type_counts($post_type);
        $total = 0;

        foreach ($counts as $status => $count) {
            $status = sanitize_key((string) $status);
            if (in_array($status, ['auto-draft', 'trash', 'inherit'], true)) {
                continue;
            }

            $total += (int) $count;
        }

        return $total;
    }
}

if (!function_exists('meza_flush_post_type_counts_cache')) {
    function meza_flush_post_type_counts_cache(string $post_type): void
    {
        $post_type = sanitize_key($post_type);
        if ($post_type === '') {
            return;
        }

        if (isset($GLOBALS['meza_post_type_counts_request_cache']) && is_array($GLOBALS['meza_post_type_counts_request_cache'])) {
            unset($GLOBALS['meza_post_type_counts_request_cache'][$post_type]);
        }

        delete_transient(meza_get_post_type_counts_cache_key($post_type));
    }
}

if (!function_exists('meza_flush_post_type_counts_cache_for_post')) {
    function meza_flush_post_type_counts_cache_for_post(int $post_id): void
    {
        if ($post_id <= 0) {
            return;
        }

        $post_type = get_post_type($post_id);
        if (!is_string($post_type) || $post_type === '') {
            return;
        }

        meza_flush_post_type_counts_cache($post_type);
    }
}

add_action('save_post', function (int $post_id, WP_Post $post): void {
    if ($post_id <= 0) {
        return;
    }

    meza_flush_post_type_counts_cache((string) $post->post_type);
}, 20, 2);

add_action('trashed_post', 'meza_flush_post_type_counts_cache_for_post', 20);
add_action('untrashed_post', 'meza_flush_post_type_counts_cache_for_post', 20);
add_action('before_delete_post', 'meza_flush_post_type_counts_cache_for_post', 20);
add_action('init', 'meza_migrate_nonautoload_options', 20);
add_action('init', 'meza_cleanup_oversized_transient_caches', 21);
add_action('set_transient', 'meza_force_transient_option_nonautoload', 20, 3);

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

if (!function_exists('meza_seo_manager_role_key')) {
    function meza_seo_manager_role_key(): string
    {
        return 'wpseo_manager';
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

if (!function_exists('meza_get_conference_admin_menu_slug')) {
    function meza_get_conference_admin_menu_slug(): string
    {
        return sanitize_key((string) apply_filters('meza_conference_admin_menu_slug', 'conference'));
    }
}

if (!function_exists('meza_get_conference_schedule_admin_menu_slug')) {
    function meza_get_conference_schedule_admin_menu_slug(): string
    {
        return sanitize_key((string) apply_filters('meza_conference_schedule_admin_menu_slug', 'conference-schedule'));
    }
}

if (!function_exists('meza_can_access_conference_admin_menu')) {
    function meza_can_access_conference_admin_menu($user = null): bool
    {
        return meza_user_has_any_role($user, ['administrator', meza_site_manager_role_key()]);
    }
}

if (!function_exists('meza_should_show_conference_admin_menu')) {
    function meza_should_show_conference_admin_menu(): bool
    {
        if (!meza_can_access_conference_admin_menu(wp_get_current_user())) {
            return false;
        }

        if (function_exists('meza_is_conference_business_type')) {
            return meza_is_conference_business_type();
        }

        $option_value = get_option('options_type');
        $value = is_scalar($option_value) ? sanitize_key(trim((string) $option_value)) : '';

        return $value === 'conference';
    }
}

if (!function_exists('meza_get_conference_admin_menu_slug_aliases')) {
    function meza_get_conference_admin_menu_slug_aliases(): array
    {
        $aliases = [
            meza_get_conference_admin_menu_slug(),
            'conference',
            'event-info',
        ];

        return array_values(array_unique(array_filter(array_map(static function ($value): string {
            return sanitize_key((string) $value);
        }, $aliases))));
    }
}

if (!function_exists('meza_build_conference_admin_menu_item')) {
    function meza_build_conference_admin_menu_item(): array
    {
        $parent_slug = meza_get_conference_admin_menu_slug();
        $css_class = 'menu-top toplevel_page_' . $parent_slug;
        $hookname = 'toplevel_page_' . $parent_slug;

        return [
            __('Conference'),
            'manage_options',
            $parent_slug,
            __('Conference'),
            $css_class,
            $hookname,
            'dashicons-tickets-alt',
        ];
    }
}

if (!function_exists('meza_normalize_conference_admin_menu_item')) {
    function meza_normalize_conference_admin_menu_item(array $item): array
    {
        $defaults = meza_build_conference_admin_menu_item();

        $item[0] = $defaults[0];
        $item[1] = (string) ($item[1] ?? $defaults[1]);
        if ($item[1] === '') {
            $item[1] = $defaults[1];
        }
        $item[2] = $defaults[2];
        $item[3] = $defaults[3];
        $item[4] = $defaults[4];
        $item[5] = $defaults[5];
        $item[6] = $defaults[6];

        return $item;
    }
}

if (!function_exists('meza_maybe_move_legacy_conference_submenu')) {
    function meza_maybe_move_legacy_conference_submenu(): void
    {
        global $submenu;

        if (!is_array($submenu)) {
            return;
        }

        $parent_slug = meza_get_conference_admin_menu_slug();
        if ($parent_slug === '' || $parent_slug === 'event-info' || !isset($submenu['event-info'])) {
            return;
        }

        $legacy_items = is_array($submenu['event-info']) ? $submenu['event-info'] : [];
        $current_items = isset($submenu[$parent_slug]) && is_array($submenu[$parent_slug]) ? $submenu[$parent_slug] : [];

        $submenu[$parent_slug] = array_values(array_merge($legacy_items, $current_items));
        unset($submenu['event-info']);
    }
}

if (!function_exists('meza_ensure_conference_admin_menu_exists')) {
    function meza_ensure_conference_admin_menu_exists(): void
    {
        global $menu, $submenu;

        $parent_slug = meza_get_conference_admin_menu_slug();
        if ($parent_slug === '') {
            return;
        }

        if (!meza_should_show_conference_admin_menu()) {
            remove_menu_page($parent_slug);
            remove_menu_page('event-info');
            if (is_array($submenu)) {
                unset($submenu[$parent_slug], $submenu['event-info']);
            }
            return;
        }

        if (!is_array($menu)) {
            $menu = [];
        }

        meza_maybe_move_legacy_conference_submenu();

        $aliases = meza_get_conference_admin_menu_slug_aliases();
        $menu_index = null;

        foreach ($menu as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $slug = sanitize_key((string) ($item[2] ?? ''));
            if ($slug === '' || !in_array($slug, $aliases, true)) {
                continue;
            }

            $menu[$index] = meza_normalize_conference_admin_menu_item($item);
            $menu_index = (int) $index;
            break;
        }

        if ($menu_index === null) {
            $conference_item = meza_build_conference_admin_menu_item();
            $insert_at = count($menu);

            foreach ($menu as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }

                $slug = (string) ($item[2] ?? '');
                if (in_array($slug, ['edit.php?post_type=cta', 'edit.php?post_type=event'], true)) {
                    $insert_at = (int) $index;
                    break;
                }
            }

            array_splice($menu, $insert_at, 0, [$conference_item]);
        }

        if (!is_array($submenu)) {
            $submenu = [];
        }

        if (!isset($submenu[$parent_slug]) || !is_array($submenu[$parent_slug])) {
            $submenu[$parent_slug] = [];
        }
    }
}

if (!function_exists('meza_sync_admin_menu_editor_conference_menu_items')) {
    function meza_sync_admin_menu_editor_conference_menu_items(): void
    {
        if (!is_admin()) {
            return;
        }

        $parent_slug = meza_get_conference_admin_menu_slug();
        $schedule_slug = meza_get_conference_schedule_admin_menu_slug();
        if ($parent_slug === '' || $parent_slug === 'event-info') {
            return;
        }

        $menu_editor_settings = get_option('ws_menu_editor');
        if (
            !is_array($menu_editor_settings)
            || !isset($menu_editor_settings['custom_menu']['tree'])
            || !is_array($menu_editor_settings['custom_menu']['tree'])
        ) {
            return;
        }

        $tree = $menu_editor_settings['custom_menu']['tree'];
        $did_update = false;

        if (isset($tree['event-info']) && is_array($tree['event-info']) && !isset($tree[$parent_slug])) {
            $tree[$parent_slug] = $tree['event-info'];
            unset($tree['event-info']);
            $did_update = true;
        }

        if (!isset($tree[$parent_slug]) || !is_array($tree[$parent_slug])) {
            return;
        }

        $conference_node = $tree[$parent_slug];
        $conference_node['template_id'] = '>' . $parent_slug;
        $conference_node['menu_title'] = 'Conference';

        $defaults = isset($conference_node['defaults']) && is_array($conference_node['defaults']) ? $conference_node['defaults'] : [];
        $defaults['menu_title'] = 'Conference';
        $defaults['page_title'] = 'Conference';
        $defaults['access_level'] = 'edit_posts';
        $defaults['file'] = $parent_slug;
        $defaults['css_class'] = 'menu-top toplevel_page_' . $parent_slug;
        $defaults['hookname'] = 'toplevel_page_' . $parent_slug;
        $defaults['icon_url'] = 'dashicons-tickets-alt';
        $defaults['is_plugin_page'] = true;
        $defaults['url'] = 'admin.php?page=' . $parent_slug;
        $conference_node['defaults'] = $defaults;

        $existing_items = isset($conference_node['items']) && is_array($conference_node['items']) ? $conference_node['items'] : [];
        $normalized_items = [];
        $information_item = null;
        $schedule_item = null;
        $segments_item = null;
        $segment_taxonomy_items = [];
        $other_items = [];
        $expected_segment_taxonomy_items = meza_get_segment_taxonomy_admin_menu_editor_items($parent_slug);

        foreach ($existing_items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $item_defaults = isset($item['defaults']) && is_array($item['defaults']) ? $item['defaults'] : [];
            $file = (string) ($item_defaults['file'] ?? $item['file'] ?? '');
            $url = (string) ($item_defaults['url'] ?? '');
            $normalized_slug = sanitize_key((string) preg_replace('/^admin\.php\?page=/', '', html_entity_decode($file, ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8')));

            if (in_array($normalized_slug, meza_get_conference_admin_menu_slug_aliases(), true)) {
                $item['template_id'] = $parent_slug . '>' . $parent_slug;
                $item_defaults['menu_title'] = 'Information';
                $item_defaults['page_title'] = 'Conference';
                $item_defaults['access_level'] = 'edit_posts';
                $item_defaults['file'] = $parent_slug;
                $item_defaults['is_plugin_page'] = true;
                $item_defaults['url'] = 'admin.php?page=' . $parent_slug;
                $item['defaults'] = $item_defaults;
                $information_item = $item;
                continue;
            }

            if ($schedule_slug !== '' && ($normalized_slug === $schedule_slug || $url === 'admin.php?page=' . $schedule_slug)) {
                $item['template_id'] = $parent_slug . '>' . $schedule_slug;
                $item_defaults['menu_title'] = 'Schedule';
                $item_defaults['page_title'] = 'Schedule';
                $item_defaults['access_level'] = 'edit_posts';
                $item_defaults['file'] = $schedule_slug;
                $item_defaults['is_plugin_page'] = true;
                $item_defaults['url'] = 'admin.php?page=' . $schedule_slug;
                $item['defaults'] = $item_defaults;
                $schedule_item = $item;
                continue;
            }

            if ($file === 'edit.php?post_type=segment') {
                $item['menu_title'] = 'Segments';
                $item['template_id'] = $parent_slug . '>edit.php?post_type=segment';
                $item_defaults['menu_title'] = 'Segments';
                $item_defaults['access_level'] = 'edit_posts';
                $item_defaults['file'] = 'edit.php?post_type=segment';
                $item_defaults['url'] = 'edit.php?post_type=segment';
                $item['defaults'] = $item_defaults;
                $segments_item = $item;
                continue;
            }

            if (isset($expected_segment_taxonomy_items[$url])) {
                $segment_taxonomy_items[$url] = $item;
                continue;
            }

            if ($file === 'post-new.php?post_type=segment') {
                $did_update = true;
                continue;
            }

            $other_items[] = $item;
        }

        if ($information_item === null) {
            $information_item = [
                'template_id' => $parent_slug . '>' . $parent_slug,
                'defaults' => [
                    'menu_title' => 'Information',
                    'page_title' => 'Conference',
                    'access_level' => 'edit_posts',
                    'file' => $parent_slug,
                    'is_plugin_page' => true,
                    'url' => 'admin.php?page=' . $parent_slug,
                ],
                'f' => 'ip',
            ];
            $did_update = true;
        }

        if ($schedule_slug !== '' && $schedule_item === null) {
            $schedule_item = [
                'template_id' => $parent_slug . '>' . $schedule_slug,
                'defaults' => [
                    'menu_title' => 'Schedule',
                    'page_title' => 'Schedule',
                    'access_level' => 'edit_posts',
                    'file' => $schedule_slug,
                    'is_plugin_page' => true,
                    'url' => 'admin.php?page=' . $schedule_slug,
                ],
                'f' => 'ip',
            ];
            $did_update = true;
        }

        if ($segments_item === null) {
            $segments_item = [
                'template_id' => $parent_slug . '>edit.php?post_type=segment',
                'defaults' => [
                    'menu_title' => 'Segments',
                    'access_level' => 'edit_posts',
                    'file' => 'edit.php?post_type=segment',
                    'url' => 'edit.php?post_type=segment',
                ],
                'f' => 'ip',
            ];
            $did_update = true;
        }

        foreach ($expected_segment_taxonomy_items as $slug => $item) {
            if (!isset($segment_taxonomy_items[$slug])) {
                $segment_taxonomy_items[$slug] = $item;
                $did_update = true;
            }
        }

        $normalized_items = array_values(array_filter([
            $information_item,
            $schedule_item,
            $segments_item,
            ...array_values($segment_taxonomy_items),
            ...$other_items,
        ]));

        if ($normalized_items !== $existing_items) {
            $conference_node['items'] = $normalized_items;
            $did_update = true;
        }

        $tree[$parent_slug] = $conference_node;

        if (!$did_update) {
            return;
        }

        $menu_editor_settings['custom_menu']['tree'] = $tree;
        update_option('ws_menu_editor', $menu_editor_settings);
    }
}
add_action('admin_init', 'meza_sync_admin_menu_editor_conference_menu_items', 20);

if (!function_exists('meza_is_conference_information_admin_page')) {
    function meza_is_conference_information_admin_page(): bool
    {
        if (!is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        if ($page !== '' && in_array($page, meza_get_conference_admin_menu_slug_aliases(), true)) {
            return true;
        }

        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (!($screen instanceof WP_Screen)) {
            return false;
        }

        $screen_id = sanitize_key((string) $screen->id);
        return $screen_id !== '' && in_array($screen_id, meza_get_conference_admin_menu_slug_aliases(), true);
    }
}

if (!function_exists('meza_is_conference_schedule_admin_page')) {
    function meza_is_conference_schedule_admin_page(): bool
    {
        if (!is_admin()) {
            return false;
        }

        $schedule_slug = meza_get_conference_schedule_admin_menu_slug();
        if ($schedule_slug === '') {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        if ($page === $schedule_slug) {
            return true;
        }

        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (!($screen instanceof WP_Screen)) {
            return false;
        }

        return sanitize_key((string) $screen->id) === $schedule_slug;
    }
}

if (!function_exists('meza_get_segment_post_type_labels')) {
    function meza_get_segment_post_type_labels(): array
    {
        return [
            'name' => __('Segments'),
            'singular_name' => __('Segment'),
            'menu_name' => __('Segments'),
            'all_items' => __('All Segments'),
            'edit_item' => __('Edit Segment'),
            'view_item' => __('View Segment'),
            'view_items' => __('View Segments'),
            'add_new_item' => __('Add New Segment'),
            'add_new' => __('Add New Segment'),
            'new_item' => __('New Segment'),
            'search_items' => __('Search Segments'),
            'not_found' => __('No segments found'),
            'not_found_in_trash' => __('No segments found in Trash'),
            'archives' => __('Segment Archives'),
            'attributes' => __('Segment Attributes'),
            'insert_into_item' => __('Insert into segment'),
            'uploaded_to_this_item' => __('Uploaded to this segment'),
            'filter_items_list' => __('Filter segments list'),
            'filter_by_date' => __('Filter segments by date'),
            'items_list_navigation' => __('Segments list navigation'),
            'items_list' => __('Segments list'),
            'item_published' => __('Segment published.'),
            'item_published_privately' => __('Segment published privately.'),
            'item_reverted_to_draft' => __('Segment reverted to draft.'),
            'item_scheduled' => __('Segment scheduled.'),
            'item_updated' => __('Segment updated.'),
            'item_link' => __('Segment Link'),
            'item_link_description' => __('A link to a segment.'),
        ];
    }
}

if (!function_exists('meza_get_segment_post_type_args')) {
    function meza_get_segment_post_type_args(): array
    {
        $parent_slug = meza_get_conference_admin_menu_slug();

        return [
            'labels' => meza_get_segment_post_type_labels(),
            'description' => '',
            'public' => true,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => meza_should_show_conference_admin_menu() && $parent_slug !== '' ? $parent_slug : false,
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => false,
            'show_in_rest' => true,
            'exclude_from_search' => true,
            'hierarchical' => false,
            'can_export' => true,
            'menu_icon' => 'dashicons-admin-post',
            'supports' => ['title', 'thumbnail', 'custom-fields', 'excerpt', 'page-attributes'],
            'rewrite' => false,
            'has_archive' => false,
            'query_var' => true,
        ];
    }
}

if (!function_exists('meza_get_segment_taxonomy_menu_items')) {
    function meza_get_segment_taxonomy_menu_items(): array
    {
        $items = [];

        foreach (meza_get_cached_object_taxonomies('segment', 'objects') as $taxonomy) {
            if (!($taxonomy instanceof WP_Taxonomy) || empty($taxonomy->show_ui)) {
                continue;
            }

            $taxonomy_name = sanitize_key((string) $taxonomy->name);
            if ($taxonomy_name === '') {
                continue;
            }

            $label = trim((string) ($taxonomy->labels->menu_name ?? $taxonomy->label ?? $taxonomy_name));
            $label = $label !== '' ? $label : $taxonomy_name;
            $capability = (string) ($taxonomy->cap->manage_terms ?? 'manage_categories');
            $slug = 'edit-tags.php?taxonomy=' . $taxonomy_name . '&post_type=segment';

            $items[$slug] = [
                $label,
                $capability,
                $slug,
                $label,
            ];
        }

        return $items;
    }
}

if (!function_exists('meza_get_segment_taxonomy_admin_menu_editor_items')) {
    function meza_get_segment_taxonomy_admin_menu_editor_items(string $parent_slug): array
    {
        $items = [];

        foreach (meza_get_cached_object_taxonomies('segment', 'objects') as $taxonomy) {
            if (!($taxonomy instanceof WP_Taxonomy) || empty($taxonomy->show_ui)) {
                continue;
            }

            $taxonomy_name = sanitize_key((string) $taxonomy->name);
            if ($taxonomy_name === '') {
                continue;
            }

            $label = trim((string) ($taxonomy->labels->menu_name ?? $taxonomy->label ?? $taxonomy_name));
            $label = $label !== '' ? $label : $taxonomy_name;
            $capability = (string) ($taxonomy->cap->manage_terms ?? 'manage_categories');
            $slug = 'edit-tags.php?taxonomy=' . $taxonomy_name . '&post_type=segment';
            $escaped_slug = str_replace('&', '&amp;', $slug);

            $items[$slug] = [
                'template_id' => $parent_slug . '>' . $escaped_slug,
                'defaults' => [
                    'menu_title' => $label,
                    'access_level' => $capability,
                    'file' => $escaped_slug,
                    'url' => $slug,
                ],
                'f' => 'ip',
            ];
        }

        return $items;
    }
}

add_action('init', function (): void {
    if (post_type_exists('segment')) {
        return;
    }

    register_post_type('segment', meza_get_segment_post_type_args());
}, 1000);

if (!function_exists('meza_normalize_conference_admin_submenu')) {
    function meza_normalize_conference_admin_submenu(): void
    {
        global $submenu;

        $parent_slug = meza_get_conference_admin_menu_slug();
        if ($parent_slug === '') {
            return;
        }

        if (!meza_should_show_conference_admin_menu()) {
            unset($submenu[$parent_slug]);
            return;
        }

        if (!isset($submenu[$parent_slug]) || !is_array($submenu[$parent_slug])) {
            $submenu[$parent_slug] = [];
        }

        $information_item = null;
        $schedule_item = null;
        $segments_item = null;
        $segment_taxonomy_items = [];
        $other_items = [];
        $expected_segment_taxonomy_items = meza_get_segment_taxonomy_menu_items();
        $schedule_slug = meza_get_conference_schedule_admin_menu_slug();

        foreach ((array) $submenu[$parent_slug] as $item) {
            if (!is_array($item) || count($item) < 3) {
                continue;
            }

            $slug = (string) ($item[2] ?? '');
            $normalized_slug = sanitize_key((string) preg_replace('/^admin\.php\?page=/', '', $slug));

            if ($information_item === null && in_array($normalized_slug, meza_get_conference_admin_menu_slug_aliases(), true)) {
                $item[0] = __('Information');
                $item[2] = $parent_slug;
                $information_item = $item;
                continue;
            }

            if (
                $schedule_slug !== ''
                && ($slug === $schedule_slug || $normalized_slug === $schedule_slug)
            ) {
                $item[0] = __('Schedule');
                $item[2] = $schedule_slug;
                if (isset($item[3])) {
                    $item[3] = __('Schedule');
                }
                $schedule_item = $item;
                continue;
            }

            if ($slug === 'edit.php?post_type=segment') {
                $item[0] = __('Segments');
                if (isset($item[3])) {
                    $item[3] = __('Segments');
                }
                $segments_item = $item;
                continue;
            }

            if (isset($expected_segment_taxonomy_items[$slug])) {
                $segment_taxonomy_items[$slug] = $item;
                continue;
            }

            if ($slug === 'post-new.php?post_type=segment') {
                continue;
            }

            $other_items[] = $item;
        }

        if ($information_item === null) {
            $information_item = [
                __('Information'),
                'manage_options',
                $parent_slug,
                __('Information'),
            ];
        }

        if ($schedule_slug !== '' && $schedule_item === null) {
            $schedule_item = [
                __('Schedule'),
                'manage_options',
                $schedule_slug,
                __('Schedule'),
            ];
        }

        if (post_type_exists('segment')) {
            if ($segments_item === null) {
                $segments_item = [
                    __('Segments'),
                    'edit_posts',
                    'edit.php?post_type=segment',
                    __('Segments'),
                ];
            }

        }

        foreach ($expected_segment_taxonomy_items as $slug => $item) {
            if (!isset($segment_taxonomy_items[$slug])) {
                $segment_taxonomy_items[$slug] = $item;
            }
        }

        $submenu[$parent_slug] = array_values(array_filter([
            $information_item,
            $schedule_item,
            $segments_item,
            ...array_values($segment_taxonomy_items),
            ...$other_items,
        ]));
    }
}

add_action('admin_menu', 'meza_normalize_conference_admin_submenu', PHP_INT_MAX - 5);
add_action('admin_menu_editor-menu_replaced', 'meza_normalize_conference_admin_submenu', PHP_INT_MAX - 5);

add_action('admin_menu', function (): void {
    if (!meza_should_show_conference_admin_menu()) {
        remove_menu_page(meza_get_conference_admin_menu_slug());
    }

    if (post_type_exists('segment')) {
        remove_menu_page('edit.php?post_type=segment');
    }
}, PHP_INT_MAX);
add_action('admin_menu_editor-menu_replaced', function (): void {
    if (!meza_should_show_conference_admin_menu()) {
        remove_menu_page(meza_get_conference_admin_menu_slug());
    }

    if (post_type_exists('segment')) {
        remove_menu_page('edit.php?post_type=segment');
    }
}, PHP_INT_MAX);

if (!function_exists('meza_finalize_conference_admin_menu')) {
    function meza_finalize_conference_admin_menu(): void
    {
        meza_ensure_conference_admin_menu_exists();
        meza_normalize_conference_admin_submenu();
    }
}
add_action('admin_menu', 'meza_finalize_conference_admin_menu', PHP_INT_MAX);
add_action('admin_menu_editor-menu_replaced', 'meza_finalize_conference_admin_menu', PHP_INT_MAX);

if (!function_exists('meza_is_segment_admin_screen')) {
    function meza_is_segment_admin_screen(): bool
    {
        if (!is_admin()) {
            return false;
        }

        $post_type = isset($_GET['post_type']) ? sanitize_key((string) wp_unslash($_GET['post_type'])) : '';
        if ($post_type === '') {
            $post_type = isset($_POST['post_type']) ? sanitize_key((string) wp_unslash($_POST['post_type'])) : '';
        }

        if ($post_type === '' && isset($_GET['post'])) {
            $post_id = (int) $_GET['post'];
            if ($post_id > 0) {
                $post_type = (string) get_post_type($post_id);
            }
        }

        if ($post_type === '' && function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen instanceof WP_Screen) {
                $post_type = (string) ($screen->post_type ?? '');
            }
        }

        return $post_type === 'segment';
    }
}

// Keep Segments nested under the Conference options page on every environment.
add_filter('register_post_type_args', function ($args, $post_type) {
    if ($post_type !== 'segment' || !is_array($args)) {
        return $args;
    }

    return array_merge($args, meza_get_segment_post_type_args());
}, 1000, 2);

add_filter('user_has_cap', function (array $allcaps, array $caps, array $args, $user): array {
    if (!is_admin() || !meza_can_access_conference_admin_menu($user)) {
        return $allcaps;
    }

    if (!meza_is_conference_information_admin_page() && !meza_is_conference_schedule_admin_page()) {
        return $allcaps;
    }

    $requested_cap = strtolower((string) ($args[0] ?? ''));
    if ($requested_cap !== '') {
        $allcaps[$requested_cap] = true;
    }

    foreach ($caps as $cap) {
        $cap = strtolower((string) $cap);
        if ($cap !== '') {
            $allcaps[$cap] = true;
        }
    }

    $allcaps['manage_options'] = true;

    return $allcaps;
}, 21, 4);

// Keep the correct WooCommerce menu highlighted on coupon screens.
add_action('admin_head', function (): void {
    global $parent_file, $submenu_file, $post_type;

    if ((string) $post_type !== 'shop_coupon') {
        return;
    }

    $parent_file = 'woocommerce';
    $submenu_file = 'edit.php?post_type=shop_coupon';
}, 1000);

add_filter('parent_file', function ($parent_file) {
    if (meza_is_conference_information_admin_page()) {
        return meza_get_conference_admin_menu_slug();
    }

    if (meza_is_conference_schedule_admin_page()) {
        return meza_get_conference_admin_menu_slug();
    }

    if (!meza_is_segment_admin_screen()) {
        return $parent_file;
    }

    $conference_slug = meza_get_conference_admin_menu_slug();

    return $conference_slug !== '' ? $conference_slug : $parent_file;
}, 1000);

add_filter('submenu_file', function ($submenu_file) {
    if (meza_is_conference_information_admin_page()) {
        return meza_get_conference_admin_menu_slug();
    }

    if (meza_is_conference_schedule_admin_page()) {
        return meza_get_conference_schedule_admin_menu_slug();
    }

    if (!meza_is_segment_admin_screen()) {
        return $submenu_file;
    }

    return 'edit.php?post_type=segment';
}, 1000);

if (!function_exists('meza_customer_sign_generator_capability')) {
    function meza_customer_sign_generator_capability(): string
    {
        return 'manage_client_sign_creator';
    }
}

if (!function_exists('meza_redirect_manager_capability')) {
    function meza_redirect_manager_capability(): string
    {
        return 'meza_manage_redirects';
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
            meza_redirect_manager_capability(),
            'wpseo_manage_options',
            'edit_theme_options',
            'list_users',
            'import',
            'export',
            'gravityforms_edit_forms',
            'gravityforms_create_form',
            'gravityforms_view_entries',
            'gravityforms_view_settings',
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

if (!function_exists('meza_seo_manager_capabilities')) {
    function meza_seo_manager_capabilities(): array
    {
        $caps = [
            'read' => true,
        ];

        $editor_role = get_role('editor');
        if ($editor_role instanceof WP_Role) {
            foreach ((array) $editor_role->capabilities as $cap => $grant) {
                if ($grant) {
                    $caps[(string) $cap] = true;
                }
            }
        }

        $seo_manager_extras = [
            meza_redirect_manager_capability(),
            'import',
            'wpseo_manage_options',
            'wpseo_edit_advanced_metadata',
            'wpseo_bulk_edit',
        ];

        foreach ($seo_manager_extras as $cap) {
            $caps[$cap] = true;
        }

        return $caps;
    }
}

if (!function_exists('meza_sync_site_manager_role')) {
    function meza_role_matches_target_capabilities(WP_Role $role, array $target_caps): bool
    {
        foreach ($target_caps as $cap => $grant) {
            if ((bool) $grant !== $role->has_cap((string) $cap)) {
                return false;
            }
        }

        foreach ((array) $role->capabilities as $cap => $grant) {
            if (!array_key_exists($cap, $target_caps) && (bool) $grant) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('meza_sync_site_manager_role')) {
    function meza_sync_site_manager_role(): void
    {
        $role_key = meza_site_manager_role_key();
        $target_caps = meza_site_manager_capabilities();
        $role = get_role($role_key);
        $administrator_role = get_role('administrator');
        $admin_required_caps = [
            meza_submission_manager_capability(),
            meza_backup_manager_capability(),
            meza_customer_sign_generator_capability(),
            meza_redirect_manager_capability(),
            'wpseo_manage_options',
            'wpseo_edit_advanced_metadata',
            'wpseo_bulk_edit',
        ];
        $sync_signature = meza_get_sync_signature([
            'role_key' => $role_key,
            'target_caps' => $target_caps,
            'admin_required_caps' => $admin_required_caps,
        ]);
        $stored_signature = meza_get_stored_sync_signature('meza_sync_site_manager_role_signature');

        if (
            $role instanceof WP_Role
            && $administrator_role instanceof WP_Role
            && $stored_signature === $sync_signature
            && meza_role_matches_target_capabilities($role, $target_caps)
        ) {
            $administrator_caps_are_synced = true;

            foreach ($admin_required_caps as $cap) {
                if (!$administrator_role->has_cap($cap)) {
                    $administrator_caps_are_synced = false;
                    break;
                }
            }

            if ($administrator_caps_are_synced) {
                return;
            }
        }

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

            foreach ([meza_redirect_manager_capability(), 'wpseo_manage_options', 'wpseo_edit_advanced_metadata', 'wpseo_bulk_edit'] as $cap) {
                if (!$administrator_role->has_cap($cap)) {
                    $administrator_role->add_cap($cap);
                }
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

        meza_store_sync_signature('meza_sync_site_manager_role_signature', $sync_signature);
    }
}
add_action('init', 'meza_sync_site_manager_role', 20);

if (!function_exists('meza_sync_seo_manager_role')) {
    function meza_sync_seo_manager_role(): void
    {
        $role_key = meza_seo_manager_role_key();
        $target_caps = meza_seo_manager_capabilities();
        $role = get_role($role_key);
        $sync_signature = meza_get_sync_signature([
            'role_key' => $role_key,
            'target_caps' => $target_caps,
        ]);
        $stored_signature = meza_get_stored_sync_signature('meza_sync_seo_manager_role_signature');

        if (
            $role instanceof WP_Role
            && $stored_signature === $sync_signature
            && meza_role_matches_target_capabilities($role, $target_caps)
        ) {
            return;
        }

        if (!($role instanceof WP_Role)) {
            add_role($role_key, 'SEO Manager', $target_caps);
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

        $current_user = wp_get_current_user();
        if (
            $current_user instanceof WP_User
            && in_array($role_key, (array) $current_user->roles, true)
        ) {
            $current_user->get_role_caps();
        }

        meza_store_sync_signature('meza_sync_seo_manager_role_signature', $sync_signature);
    }
}
add_action('init', 'meza_sync_seo_manager_role', 20);

if (!function_exists('meza_is_aios_user_two_factor_request')) {
    function meza_is_aios_user_two_factor_request(): bool
    {
        if (!is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';

        return $page === 'aiowpsec_two_factor_auth_user';
    }
}

add_filter('aios_management_permission', function ($capability) {
    $current_user = wp_get_current_user();
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash((string) $_GET['tab'])) : '';
    $nav_context = isset($_GET['mz_nav']) ? sanitize_key(wp_unslash((string) $_GET['mz_nav'])) : '';

    if (
        in_array($page, ['aiowpsec_tools', 'aiowpsec_two_factor_auth_user'], true)
        && $current_user instanceof WP_User
        && $current_user->exists()
        && !in_array('administrator', (array) $current_user->roles, true)
    ) {
        return 'read';
    }

    if (
        $page === 'aiowpsec'
        && $tab === 'locked-ip'
        && in_array($nav_context, ['users', 'security'], true)
        && $current_user instanceof WP_User
        && function_exists('meza_site_manager_role_key')
        && in_array(meza_site_manager_role_key(), (array) $current_user->roles, true)
    ) {
        return 'read';
    }

    if (meza_is_aios_user_two_factor_request() && meza_user_has_any_role($current_user, [meza_site_manager_role_key()])) {
        return 'read';
    }

    return $capability;
}, 20);

add_filter('simba_tfa_management_capability', function ($capability) {
    $current_user = wp_get_current_user();

    if (
        meza_is_aios_user_two_factor_request()
        && $current_user instanceof WP_User
        && $current_user->exists()
        && !in_array('administrator', (array) $current_user->roles, true)
    ) {
        return 'read';
    }

    if (!meza_is_aios_user_two_factor_request() || !meza_user_has_any_role($current_user, [meza_site_manager_role_key()])) {
        return $capability;
    }

    return 'read';
}, 20);

if (!function_exists('meza_get_post_type_capabilities')) {
    function meza_get_cached_post_type_object(string $post_type): ?WP_Post_Type
    {
        static $cache = [];

        $post_type = sanitize_key($post_type);
        if ($post_type === '') {
            return null;
        }

        if (array_key_exists($post_type, $cache)) {
            return $cache[$post_type];
        }

        $post_type_object = get_post_type_object($post_type);
        $cache[$post_type] = $post_type_object instanceof WP_Post_Type ? $post_type_object : null;

        return $cache[$post_type];
    }
}

if (!function_exists('meza_get_cached_object_taxonomies')) {
    function meza_get_cached_object_taxonomies(string $post_type, string $output = 'names'): array
    {
        static $cache = [];

        $post_type = sanitize_key($post_type);
        $output = $output === 'objects' ? 'objects' : 'names';

        if ($post_type === '') {
            return [];
        }

        if (isset($cache[$output][$post_type])) {
            return is_array($cache[$output][$post_type]) ? $cache[$output][$post_type] : [];
        }

        $taxonomies = get_object_taxonomies($post_type, $output);
        $cache[$output][$post_type] = is_array($taxonomies) ? $taxonomies : [];

        return $cache[$output][$post_type];
    }
}

if (!function_exists('meza_get_post_type_capabilities')) {
    function meza_get_post_type_capabilities(string $post_type): array
    {
        $post_type = trim($post_type);
        if ($post_type === '') {
            return [];
        }

        $post_type_object = meza_get_cached_post_type_object($post_type);
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

        foreach (meza_get_cached_object_taxonomies($post_type, 'objects') as $taxonomy) {
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

        // AIOS exposes per-user 2FA via the standard logged-in `read` capability.
        return user_can($user, 'read');
    }
}

add_filter('map_meta_cap', function (array $caps, string $cap, int $user_id, array $args): array {
    if ($cap !== 'view_site_health_checks') {
        return $caps;
    }

    return ['manage_options'];
}, 19, 4);

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

if (!function_exists('meza_get_admin_notice_hook_names')) {
    function meza_get_admin_notice_hook_names(): array
    {
        return [
            'admin_notices',
            'all_admin_notices',
            'network_admin_notices',
            'user_admin_notices',
        ];
    }
}

if (!function_exists('meza_get_callback_cache_key')) {
    function meza_get_callback_cache_key($callback): string
    {
        if (is_string($callback)) {
            return 'function:' . $callback;
        }

        if ($callback instanceof Closure) {
            return 'closure:' . spl_object_hash($callback);
        }

        if (is_array($callback)) {
            $target = $callback[0] ?? null;
            $method = (string) ($callback[1] ?? '');

            if (is_object($target)) {
                return 'method:' . get_class($target) . ':' . spl_object_hash($target) . ':' . $method;
            }

            if (is_string($target)) {
                return 'static:' . $target . ':' . $method;
            }
        }

        if (is_object($callback) && method_exists($callback, '__invoke')) {
            return 'invokable:' . get_class($callback) . ':' . spl_object_hash($callback);
        }

        return '';
    }
}

if (!function_exists('meza_get_callback_signature')) {
    function meza_get_callback_signature($callback): string
    {
        static $signature_cache = [];

        $cache_key = meza_get_callback_cache_key($callback);
        if ($cache_key !== '' && array_key_exists($cache_key, $signature_cache)) {
            return $signature_cache[$cache_key];
        }

        $signature = '';

        if (is_string($callback)) {
            $signature = $callback;
        } elseif ($callback instanceof Closure) {
            $signature = 'closure';
        } elseif (is_array($callback)) {
            $target = $callback[0] ?? null;
            $method = (string) ($callback[1] ?? '');

            if (is_object($target)) {
                $signature = get_class($target) . '::' . $method;
            } elseif (is_string($target)) {
                $signature = $target . '::' . $method;
            }
        } elseif (is_object($callback) && method_exists($callback, '__invoke')) {
            $signature = get_class($callback) . '::__invoke';
        }

        if ($cache_key !== '') {
            $signature_cache[$cache_key] = $signature;
        }

        return $signature;
    }
}

if (!function_exists('meza_get_callback_source_file')) {
    function meza_get_callback_source_file($callback): string
    {
        static $source_file_cache = [];

        $cache_key = meza_get_callback_cache_key($callback);
        if ($cache_key !== '' && array_key_exists($cache_key, $source_file_cache)) {
            return $source_file_cache[$cache_key];
        }

        $source_file = '';

        try {
            if (is_string($callback) && function_exists($callback)) {
                $reflection = new ReflectionFunction($callback);
                $source_file = wp_normalize_path((string) $reflection->getFileName());
            } elseif ($callback instanceof Closure) {
                $reflection = new ReflectionFunction($callback);
                $source_file = wp_normalize_path((string) $reflection->getFileName());
            } elseif (is_array($callback) && count($callback) >= 2) {
                $target = $callback[0];
                $method = (string) $callback[1];

                if ((is_object($target) || is_string($target)) && method_exists($target, $method)) {
                    $reflection = new ReflectionMethod($target, $method);
                    $source_file = wp_normalize_path((string) $reflection->getFileName());
                }
            } elseif (is_object($callback) && method_exists($callback, '__invoke')) {
                $reflection = new ReflectionMethod($callback, '__invoke');
                $source_file = wp_normalize_path((string) $reflection->getFileName());
            }
        } catch (ReflectionException $exception) {
            $source_file = '';
        }

        if ($cache_key !== '') {
            $source_file_cache[$cache_key] = $source_file;
        }

        return $source_file;
    }
}

if (!function_exists('meza_string_matches_plugin_signatures')) {
    function meza_string_matches_plugin_signatures(string $value, array $signatures): bool
    {
        $value = strtolower(trim(wp_normalize_path($value)));
        if ($value === '' || $signatures === []) {
            return false;
        }

        foreach ($signatures as $signature) {
            $signature = strtolower(trim((string) $signature));
            if ($signature === '') {
                continue;
            }

            if (str_contains($value, $signature)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('meza_should_suppress_admin_notice_callback')) {
    function meza_should_suppress_admin_notice_callback($callback): bool
    {
        static $suppression_cache = [];

        $cache_key = meza_get_callback_cache_key($callback);
        if ($cache_key !== '' && array_key_exists($cache_key, $suppression_cache)) {
            return $suppression_cache[$cache_key];
        }

        $signature = strtolower(trim(meza_get_callback_signature($callback)));
        $should_suppress = false;

        if ($signature !== '' && (str_contains($signature, 'meza_') || str_contains($signature, 'mz_'))) {
            if ($cache_key !== '') {
                $suppression_cache[$cache_key] = false;
            }
            return false;
        }

        $source_file = meza_get_callback_source_file($callback);
        if ($source_file === '') {
            $should_suppress = false;
        } elseif (
            str_contains($source_file, '/wp-admin/')
            || str_contains($source_file, '/wp-includes/')
            || str_contains($source_file, '/wp-content/mu-plugins/mz-mu-plugins/')
        ) {
            $should_suppress = false;
        } else {
            $should_suppress = str_contains($source_file, '/wp-content/plugins/')
                || str_contains($source_file, '/wp-content/mu-plugins/');
        }

        if ($cache_key !== '') {
            $suppression_cache[$cache_key] = $should_suppress;
        }

        return $should_suppress;
    }
}

if (!function_exists('meza_suppress_non_meza_plugin_admin_notice_callbacks')) {
    function meza_suppress_non_meza_plugin_admin_notice_callbacks(): void
    {
        global $wp_filter;

        foreach (meza_get_admin_notice_hook_names() as $hook_name) {
            $hook = $wp_filter[$hook_name] ?? null;
            if (!($hook instanceof WP_Hook) || !is_array($hook->callbacks)) {
                continue;
            }

            foreach ($hook->callbacks as $priority => $callbacks) {
                foreach ((array) $callbacks as $callback_data) {
                    $callback = $callback_data['function'] ?? null;
                    if ($callback === null || !meza_should_suppress_admin_notice_callback($callback)) {
                        continue;
                    }

                    remove_action($hook_name, $callback, (int) $priority);
                }
            }
        }
    }
}

if (!function_exists('meza_can_access_admin_bar_new_content_node')) {
    function meza_admin_bar_allows_hidden_post_type_new_node(string $post_type): bool
    {
        $post_type = sanitize_key($post_type);
        if ($post_type === '') {
            return false;
        }

        $post_type_object = get_post_type_object($post_type);
        if (!($post_type_object instanceof WP_Post_Type)) {
            return false;
        }

        $labels = $post_type_object->labels ?? null;
        $haystack = implode(' ', array_filter([
            $post_type,
            is_object($labels) ? (string) ($labels->name ?? '') : '',
            is_object($labels) ? (string) ($labels->singular_name ?? '') : '',
        ], static function ($value): bool {
            return is_string($value) && trim($value) !== '';
        }));

        return preg_match('/\b(organizers?|venues?)\b/i', $haystack) === 1;
    }

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

        if ($node_id === 'new-user' || str_contains($href, 'user-new.php')) {
            $user = wp_get_current_user();
            return $user instanceof WP_User && in_array('administrator', (array) $user->roles, true);
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

            if (
                $post_type !== 'post'
                && $post_type !== 'page'
                && $post_type_object->show_in_menu === false
                && !meza_admin_bar_allows_hidden_post_type_new_node($post_type)
            ) {
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
        $sync_signature = meza_get_sync_signature($roles_to_sync);
        $stored_signature = meza_get_stored_sync_signature('meza_sync_events_roles_signature');
        $roles_are_present = true;

        foreach (array_keys($roles_to_sync) as $role_key) {
            if (!(get_role((string) $role_key) instanceof WP_Role)) {
                $roles_are_present = false;
                break;
            }
        }

        if ($roles_are_present && $stored_signature === $sync_signature) {
            return;
        }

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

        meza_store_sync_signature('meza_sync_events_roles_signature', $sync_signature);
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
        $capability_map = meza_get_event_post_capability_source_map();
        $signature_payload = [];

        foreach (array_keys((array) $wp_roles->roles) as $role_key) {
            $role_key = (string) $role_key;
            if ($role_key === '' || in_array($role_key, $skip_roles, true)) {
                continue;
            }

            $role_data = (array) ($wp_roles->roles[$role_key] ?? []);
            $role_caps = (array) ($role_data['capabilities'] ?? []);
            $signature_payload[$role_key] = [];

            foreach ($capability_map as $target_cap => $source_cap) {
                $signature_payload[$role_key][(string) $target_cap] = !empty($role_caps[(string) $source_cap]);
            }
        }

        $sync_signature = meza_get_sync_signature($signature_payload);
        if (meza_get_stored_sync_signature('meza_sync_event_capabilities_signature') === $sync_signature) {
            return;
        }

        foreach (array_keys((array) $wp_roles->roles) as $role_key) {
            $role_key = (string) $role_key;
            if ($role_key === '' || in_array($role_key, $skip_roles, true)) {
                continue;
            }

            $role = get_role($role_key);
            if (!($role instanceof WP_Role)) {
                continue;
            }

            foreach ($capability_map as $target_cap => $source_cap) {
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

        meza_store_sync_signature('meza_sync_event_capabilities_signature', $sync_signature);
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
            meza_seo_manager_role_key(),
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
        $sync_signature = meza_get_sync_signature([
            'shared_roles' => $shared_roles,
            'target_modules' => $target_modules,
        ]);

        if (meza_get_stored_sync_signature('meza_sync_site_kit_dashboard_sharing_signature') === $sync_signature) {
            return;
        }

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

        meza_store_sync_signature('meza_sync_site_kit_dashboard_sharing_signature', $sync_signature);
    }
}
add_action('init', 'meza_sync_site_kit_dashboard_sharing', 25);

if (!function_exists('meza_can_view_site_kit_dashboard_as_limited_role')) {
    function meza_can_view_site_kit_dashboard_as_limited_role($user = null): bool
    {
        return meza_user_has_any_role($user, [
            meza_site_manager_role_key(),
            meza_seo_manager_role_key(),
        ]);
    }
}

if (!function_exists('meza_is_site_kit_plugin_available')) {
    function meza_is_site_kit_plugin_available(): bool
    {
        return meza_is_plugin_basename_active('google-site-kit/google-site-kit.php');
    }
}

if (!function_exists('meza_can_access_site_kit_dashboard')) {
    function meza_can_access_site_kit_dashboard($user = null): bool
    {
        if ($user === null) {
            $user = wp_get_current_user();
        }

        return user_can($user, 'googlesitekit_view_dashboard')
            || user_can($user, 'googlesitekit_view_authenticated_dashboard')
            || user_can($user, 'googlesitekit_view_shared_dashboard')
            || user_can($user, 'googlesitekit_authenticate')
            || user_can($user, 'googlesitekit_setup')
            || user_can($user, 'googlesitekit_manage_options');
    }
}

if (!function_exists('meza_can_access_site_kit_splash')) {
    function meza_can_access_site_kit_splash($user = null): bool
    {
        if ($user === null) {
            $user = wp_get_current_user();
        }

        return user_can($user, 'googlesitekit_view_splash')
            || user_can($user, 'googlesitekit_authenticate')
            || user_can($user, 'googlesitekit_setup')
            || user_can($user, 'googlesitekit_manage_options');
    }
}

if (!function_exists('meza_can_manage_site_kit')) {
    function meza_can_manage_site_kit($user = null): bool
    {
        return meza_user_has_any_role($user, ['administrator']);
    }
}

if (!function_exists('meza_get_yoast_management_caps')) {
    function meza_get_yoast_management_caps(): array
    {
        return [
            'wpseo_manage_options',
            'wpseo_edit_advanced_metadata',
            'wpseo_bulk_edit',
        ];
    }
}

if (!function_exists('meza_can_access_yoast_capabilities')) {
    function meza_can_access_yoast_capabilities($user = null): bool
    {
        if ($user === null) {
            $user = wp_get_current_user();
        } elseif (is_numeric($user)) {
            $user = get_userdata((int) $user);
        }

        if (!($user instanceof WP_User) || $user->ID <= 0) {
            return false;
        }

        if (is_multisite() && is_super_admin($user->ID)) {
            return true;
        }

        if (meza_user_has_any_role($user, [
            'administrator',
            meza_site_manager_role_key(),
            meza_seo_manager_role_key(),
        ])) {
            return true;
        }

        $user->get_role_caps();

        foreach (meza_get_yoast_management_caps() as $cap) {
            if (!empty($user->allcaps[$cap])) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('meza_can_manage_yoast')) {
    function meza_can_manage_yoast($user = null): bool
    {
        return meza_can_access_yoast_capabilities($user);
    }
}

if (!function_exists('meza_get_seo_manager_blocked_post_types')) {
    function meza_get_seo_manager_blocked_post_types(): array
    {
        return [
            'organization',
            'review',
        ];
    }
}

if (!function_exists('meza_is_seo_manager_blocked_post_type')) {
    function meza_is_seo_manager_blocked_post_type(string $post_type): bool
    {
        return in_array(sanitize_key($post_type), meza_get_seo_manager_blocked_post_types(), true);
    }
}

if (!function_exists('meza_get_seo_manager_blocked_plugin_admin_pages')) {
    function meza_get_seo_manager_blocked_plugin_admin_pages(): array
    {
        return [
            'organization-events-settings',
        ];
    }
}

if (!function_exists('meza_is_restricted_seo_manager_user')) {
    function meza_is_restricted_seo_manager_user($user = null): bool
    {
        return function_exists('meza_user_has_any_role')
            && function_exists('meza_seo_manager_role_key')
            && meza_user_has_any_role($user, [meza_seo_manager_role_key()]);
    }
}

if (!function_exists('meza_should_block_seo_manager_admin_request')) {
    function meza_should_block_seo_manager_admin_request(): bool
    {
        if (!is_admin() || !meza_is_restricted_seo_manager_user(wp_get_current_user())) {
            return false;
        }

        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        if ($page !== '' && in_array($page, meza_get_seo_manager_blocked_plugin_admin_pages(), true)) {
            return true;
        }

        global $pagenow;

        $post_type = sanitize_key((string) ($_GET['post_type'] ?? ''));
        if ($post_type !== '' && meza_is_seo_manager_blocked_post_type($post_type)) {
            return in_array((string) $pagenow, ['edit.php', 'post-new.php'], true);
        }

        $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        if ($post_id > 0) {
            $post = get_post($post_id);
            if ($post instanceof WP_Post && meza_is_seo_manager_blocked_post_type((string) $post->post_type)) {
                return in_array((string) $pagenow, ['post.php', 'post-new.php'], true);
            }
        }

        return false;
    }
}

if (!function_exists('meza_can_manage_privacy_options')) {
    function meza_can_manage_privacy_options($user = null): bool
    {
        return meza_user_has_any_role($user, [
            'administrator',
            meza_site_manager_role_key(),
        ]);
    }
}

add_filter('map_meta_cap', function (array $caps, string $cap, int $user_id, array $args): array {
    if ($cap !== 'manage_privacy_options' || $user_id <= 0 || !meza_can_manage_privacy_options($user_id)) {
        return $caps;
    }

    return ['read'];
}, 19, 4);

add_filter('user_has_cap', function (array $allcaps, array $caps, array $args, WP_User $user): array {
    if (!($user instanceof WP_User) || $user->ID <= 0 || !meza_can_manage_privacy_options($user)) {
        return $allcaps;
    }

    if ((string) ($args[0] ?? '') !== 'manage_privacy_options') {
        return $allcaps;
    }

    $allcaps['manage_privacy_options'] = true;

    return $allcaps;
}, 19, 4);

add_filter('map_meta_cap', function (array $caps, string $cap, int $user_id, array $args): array {
    if ($user_id <= 0 || !in_array($cap, meza_get_yoast_management_caps(), true) || !meza_can_manage_yoast($user_id)) {
        return $caps;
    }

    return ['read'];
}, 19, 4);

add_filter('user_has_cap', function (array $allcaps, array $caps, array $args, WP_User $user): array {
    if (!($user instanceof WP_User) || $user->ID <= 0 || !meza_can_manage_yoast($user)) {
        return $allcaps;
    }

    $requested_cap = (string) ($args[0] ?? '');
    if (!in_array($requested_cap, meza_get_yoast_management_caps(), true)) {
        return $allcaps;
    }

    foreach (meza_get_yoast_management_caps() as $cap) {
        $allcaps[$cap] = true;
    }

    return $allcaps;
}, 19, 4);

add_filter('map_meta_cap', function (array $caps, string $cap, int $user_id, array $args): array {
    if (
        $user_id <= 0
        || !meza_is_restricted_seo_manager_user($user_id)
        || !in_array($cap, ['edit_post', 'delete_post', 'read_post'], true)
    ) {
        return $caps;
    }

    $post_id = isset($args[0]) ? (int) $args[0] : 0;
    if ($post_id <= 0) {
        return $caps;
    }

    $post = get_post($post_id);
    if (!($post instanceof WP_Post) || !meza_is_seo_manager_blocked_post_type((string) $post->post_type)) {
        return $caps;
    }

    return ['do_not_allow'];
}, 19, 4);

add_action('admin_init', function (): void {
    if (!meza_should_block_seo_manager_admin_request()) {
        return;
    }

    wp_safe_redirect(admin_url());
    exit;
}, 19);

add_filter('map_meta_cap', function (array $caps, string $cap, int $user_id, array $args): array {
    static $mapping_site_kit_caps = false;

    if ($mapping_site_kit_caps) {
        return $caps;
    }

    $management_caps = [
        'googlesitekit_authenticate',
        'googlesitekit_setup',
        'googlesitekit_manage_options',
    ];
    $dashboard_caps = [
        'googlesitekit_view_dashboard',
        'googlesitekit_view_authenticated_dashboard',
        'googlesitekit_view_splash',
        'googlesitekit_view_posts_insights',
    ];
    $widget_caps = [
        'googlesitekit_view_wp_dashboard_widget',
        'googlesitekit_view_admin_bar_menu',
    ];

    if (in_array($cap, $management_caps, true) && $user_id > 0 && meza_can_manage_site_kit($user_id)) {
        return ['read'];
    }

    if (in_array($cap, $dashboard_caps, true) && $user_id > 0 && (meza_can_manage_site_kit($user_id) || meza_can_view_site_kit_dashboard_as_limited_role($user_id))) {
        return ['read'];
    }

    if (in_array($cap, $widget_caps, true) && $user_id > 0 && (meza_can_manage_site_kit($user_id) || meza_can_view_site_kit_dashboard_as_limited_role($user_id))) {
        return ['read'];
    }

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
    $management_caps = [
        'googlesitekit_authenticate',
        'googlesitekit_setup',
        'googlesitekit_manage_options',
    ];

    if (in_array($requested_cap, $management_caps, true) && meza_can_manage_site_kit($user)) {
        foreach ($management_caps as $site_kit_cap) {
            $allcaps[$site_kit_cap] = true;
        }

        return $allcaps;
    }

    $dashboard_caps = [
        'googlesitekit_view_posts_insights',
        'googlesitekit_view_dashboard',
        'googlesitekit_view_authenticated_dashboard',
        'googlesitekit_view_splash',
    ];
    $widget_caps = [
        'googlesitekit_view_wp_dashboard_widget',
        'googlesitekit_view_admin_bar_menu',
    ];

    if (in_array($requested_cap, $dashboard_caps, true) && (meza_can_manage_site_kit($user) || meza_can_view_site_kit_dashboard_as_limited_role($user))) {
        foreach ($dashboard_caps as $site_kit_cap) {
            $allcaps[$site_kit_cap] = true;
        }

        return $allcaps;
    }

    if (in_array($requested_cap, $widget_caps, true) && (meza_can_manage_site_kit($user) || meza_can_view_site_kit_dashboard_as_limited_role($user))) {
        foreach ($widget_caps as $site_kit_cap) {
            $allcaps[$site_kit_cap] = true;
        }

        return $allcaps;
    }

    if (!in_array($requested_cap, [
        'googlesitekit_view_posts_insights',
        'googlesitekit_view_dashboard',
        'googlesitekit_view_authenticated_dashboard',
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
    $allcaps['googlesitekit_view_authenticated_dashboard'] = true;
    $allcaps['googlesitekit_view_splash'] = true;
    if ($can_view_shared_widget) {
        $allcaps['googlesitekit_view_wp_dashboard_widget'] = true;
        $allcaps['googlesitekit_view_admin_bar_menu'] = true;
    }

    return $allcaps;
}, 20, 4);

add_filter('user_has_cap', function (array $allcaps, array $caps, array $args, $user): array {
    if (
        !($user instanceof WP_User)
        || !meza_can_manage_site_kit($user)
        || !is_admin()
        || !meza_is_site_kit_admin_page()
    ) {
        return $allcaps;
    }

    $requested_cap = strtolower((string) ($args[0] ?? ''));
    if ($requested_cap !== '') {
        $allcaps[$requested_cap] = true;
    }

    foreach ($caps as $cap) {
        $cap = strtolower((string) $cap);
        if ($cap !== '') {
            $allcaps[$cap] = true;
        }
    }

    $allcaps['manage_options'] = true;

    return $allcaps;
}, 21, 4);

add_action('admin_init', function (): void {
    if (!is_admin()) {
        return;
    }

    $site_kit_available = meza_is_site_kit_plugin_available();
    $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
    if (in_array($page, ['meza-web-analytics', 'mz-web-analytics'], true) && !$site_kit_available) {
        wp_safe_redirect(admin_url());
        exit;
    }

    if (str_starts_with($page, 'googlesitekit-') && !$site_kit_available) {
        wp_safe_redirect(admin_url());
        exit;
    }

    if (in_array($page, ['meza-web-analytics', 'mz-web-analytics'], true) && meza_can_manage_site_kit()) {
        wp_safe_redirect(admin_url('admin.php?page=googlesitekit-dashboard'));
        exit;
    }

    if ($page !== 'googlesitekit-dashboard') {
        return;
    }

    if (meza_can_access_site_kit_dashboard() || !meza_can_access_site_kit_splash()) {
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

if (!function_exists('meza_is_site_kit_admin_page')) {
    function meza_is_site_kit_admin_page(?string $page = null): bool
    {
        if ($page === null) {
            $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
        } else {
            $page = sanitize_key((string) $page);
        }

        if (in_array($page, ['meza-web-analytics', 'mz-web-analytics'], true)) {
            return true;
        }

        return $page !== '' && str_starts_with($page, 'googlesitekit-');
    }
}

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

if (!function_exists('meza_get_site_kit_dashboard_menu_slug')) {
    function meza_get_site_kit_dashboard_menu_slug(): string
    {
        return 'admin.php?page=googlesitekit-dashboard';
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
            $is_site_kit = in_array($slug, ['meza-web-analytics', 'mz-web-analytics'], true)
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
        $site_kit_available = meza_is_site_kit_plugin_available();
        $can_view_splash = meza_can_access_site_kit_splash();
        $has_dashboard_access = meza_can_access_site_kit_dashboard();
        $use_custom_menu = !current_user_can('manage_options');

        if (!$can_view_splash && !$has_dashboard_access && !meza_can_manage_site_kit()) {
            return;
        }

        remove_menu_page('meza-web-analytics');
        remove_menu_page('mz-web-analytics');

        if (!$site_kit_available) {
            remove_menu_page('googlesitekit-dashboard');
            return;
        }

        if ($site_kit_available && ($use_custom_menu || !$has_dashboard_access)) {
            remove_menu_page('googlesitekit-dashboard');
        }

        if ($site_kit_available && !$use_custom_menu && $has_dashboard_access && meza_has_site_kit_top_level_menu_item()) {
            return;
        }

        $target_page = ($has_dashboard_access || meza_can_manage_site_kit()) ? 'googlesitekit-dashboard' : 'googlesitekit-splash';
        $menu_slug = 'admin.php?page=' . $target_page;

        add_menu_page(
            'Web Analytics',
            'Web Analytics',
            'read',
            $menu_slug,
            '',
            meza_get_site_kit_menu_icon()
        );
    }
}
add_action('admin_menu', 'meza_restore_site_kit_admin_menu', PHP_INT_MAX - 3);

add_filter('parent_file', function ($parent_file) {
    if (!is_admin() || current_user_can('manage_options')) {
        return $parent_file;
    }

    if (!meza_is_site_kit_admin_page()) {
        return $parent_file;
    }

    return meza_get_site_kit_dashboard_menu_slug();
}, PHP_INT_MAX);

add_filter('submenu_file', function ($submenu_file) {
    if (!is_admin() || current_user_can('manage_options')) {
        return $submenu_file;
    }

    if (!meza_is_site_kit_admin_page()) {
        return $submenu_file;
    }

    return meza_get_site_kit_dashboard_menu_slug();
}, PHP_INT_MAX - 1);

if (!function_exists('meza_should_normalize_site_kit_menu_for_user')) {
    function meza_should_normalize_site_kit_menu_for_user($user = null): bool
    {
        if (!($user instanceof WP_User)) {
            $user = wp_get_current_user();
        }

        if (!($user instanceof WP_User) || $user->ID <= 0) {
            return false;
        }

        return current_user_can('manage_options') || meza_can_view_site_kit_dashboard_as_limited_role($user);
    }
}

if (!function_exists('meza_enforce_normalized_site_kit_menu_state')) {
    function meza_enforce_normalized_site_kit_menu_state(): void
    {
        $user = wp_get_current_user();
        if (!is_admin() || !meza_should_normalize_site_kit_menu_for_user($user)) {
            return;
        }

        global $menu, $submenu;

        $is_limited_dashboard_user = meza_can_view_site_kit_dashboard_as_limited_role($user);

        if ($is_limited_dashboard_user) {
            remove_submenu_page('googlesitekit-dashboard', 'googlesitekit-settings');
            remove_submenu_page('admin.php?page=googlesitekit-dashboard', 'googlesitekit-settings');

            if (is_array($submenu)) {
                foreach (['googlesitekit-dashboard', 'admin.php?page=googlesitekit-dashboard'] as $parent_slug) {
                    if (!isset($submenu[$parent_slug]) || !is_array($submenu[$parent_slug])) {
                        continue;
                    }

                    $submenu[$parent_slug] = array_values(array_filter($submenu[$parent_slug], static function ($item): bool {
                        return is_array($item) && ((string) ($item[2] ?? '')) !== 'googlesitekit-settings';
                    }));
                }
            }
        }

        if (!is_array($menu)) {
            return;
        }

        foreach ($menu as &$item) {
            if (!is_array($item)) {
                continue;
            }

            $slug = (string) ($item[2] ?? '');
            $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
            $is_site_kit = $slug === 'googlesitekit-dashboard'
                || $slug === 'admin.php?page=googlesitekit-dashboard'
                || $title === 'web analytics';

            if (!$is_site_kit) {
                continue;
            }

            $item[6] = 'dashicons-chart-area';
        }
        unset($item);
    }
}

add_action('admin_menu', function (): void {
    meza_enforce_normalized_site_kit_menu_state();
}, PHP_INT_MAX);

add_action('current_screen', function (): void {
    meza_enforce_normalized_site_kit_menu_state();
}, 1000);

add_action('adminmenu', function (): void {
    meza_enforce_normalized_site_kit_menu_state();
}, PHP_INT_MAX);

add_action('admin_init', function (): void {
    if (
        !is_admin()
        || current_user_can('manage_options')
        || !meza_can_view_site_kit_dashboard_as_limited_role(wp_get_current_user())
    ) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
    if ($page !== 'googlesitekit-settings') {
        return;
    }

    wp_safe_redirect(admin_url('admin.php?page=googlesitekit-dashboard'));
    exit;
}, 20);

add_filter('option_page_capability_updraft-options-group', function (): string {
    return meza_backup_manager_capability();
});

add_filter('redirection_role', function ($role): string {
    return meza_redirect_manager_capability();
}, 20);

add_filter('redirection_capability_check', function ($capability, $permission_name): string {
    return meza_redirect_manager_capability();
}, 20, 2);

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

        return !meza_can_access_yoast_capabilities($user);
    }
}

if (!function_exists('meza_is_current_yoast_admin_page')) {
    function meza_is_current_yoast_admin_page(): bool
    {
        if (!is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        if ($page !== '' && str_starts_with($page, 'wpseo')) {
            return true;
        }

        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (!($screen instanceof WP_Screen)) {
            return false;
        }

        $screen_id = strtolower((string) $screen->id);
        $screen_base = strtolower((string) $screen->base);

        return str_contains($screen_id, 'wpseo')
            || str_contains($screen_id, 'wordpress-seo')
            || str_contains($screen_base, 'wpseo')
            || str_contains($screen_base, 'wordpress-seo');
    }
}

if (!function_exists('meza_get_yoast_menu_entry_slug')) {
    function meza_get_yoast_menu_entry_slug(): string
    {
        return 'wpseo_dashboard';
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
        return !in_array($slug, ['wpseo_workouts', 'wpseo_redirects', 'wpseo_page_academy', 'wpseo_licenses'], true);
    }));
}, PHP_INT_MAX);

add_filter('wpseo_helpscout_show_beacon', '__return_false', PHP_INT_MAX);

add_action('admin_init', function (): void {
    if (!meza_is_current_yoast_admin_page()) {
        return;
    }

    remove_all_actions('wpseo_admin_promo_footer');
}, 20);

add_action('admin_menu', function (): void {
    remove_submenu_page('wpseo_dashboard', 'wpseo_workouts');
    remove_submenu_page('wpseo_dashboard', 'wpseo_redirects');
    remove_submenu_page('wpseo_dashboard', 'wpseo_page_academy');
    remove_submenu_page('wpseo_dashboard', 'wpseo_licenses');
}, 99);

add_filter('wpseo_introductions', function (array $introductions): array {
    return [];
}, PHP_INT_MAX);

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
}, 1);

add_action('admin_head', function (): void {
    if (!meza_is_current_yoast_admin_page()) {
        return;
    }

    $yoast_locked_section_markers = [
        '.yst-feature-upsell--card [name="wpseo_titles.publishing_principles_id"]',
        '.yst-feature-upsell--card [id="input-wpseo_titles-org-description"]',
        '.yst-feature-upsell--card [id="input-wpseo_titles-org-vat-id"]',
        '.yst-feature-upsell--card [id^="button-wpseo_titles-social-image-"]',
        '.yst-feature-upsell--card [id^="input-wpseo_titles-social-title-"]',
        '.yst-feature-upsell--card [id^="input-wpseo_titles-social-description-"]',
        '.yst-toggle-field--disabled [name="wpseo_titles.display-metabox-pt-attachment"]',
    ];
    $yoast_locked_section_selectors = [];

    foreach ($yoast_locked_section_markers as $marker) {
        $yoast_locked_section_selectors[] = "#yoast-seo-settings section.yst-grid:has($marker)";
        $yoast_locked_section_selectors[] = "#yoast-seo-settings .yst-mb-8:has(+ section.yst-grid:has($marker))";
        $yoast_locked_section_selectors[] = "#yoast-seo-settings .yst-mb-8:has(+ hr + section.yst-grid:has($marker))";
        $yoast_locked_section_selectors[] = "#yoast-seo-settings hr:has(+ section.yst-grid:has($marker))";
        $yoast_locked_section_selectors[] = "#yoast-seo-settings hr.yst-my-8:has(+ section.yst-grid $marker)";
        $yoast_locked_section_selectors[] = "#yoast-seo-settings hr.yst-my-8:has(+ .yst-mb-8 + section.yst-grid $marker)";
        $yoast_locked_section_selectors[] = "#yoast-seo-settings hr.yst-my-8:has(+ .yst-mb-8 + hr + section.yst-grid $marker)";
    }

    $yoast_locked_section_rules = implode(
        "\n\n        ",
        array_map(
            static fn(string $selector): string => $selector . " {\n            display: none !important;\n        }",
            array_unique($yoast_locked_section_selectors)
        )
    );
    $yoast_shadow_locked_section_rules = implode(
        "\n\n                ",
        array_map(
            static fn(string $selector): string => str_replace('#yoast-seo-settings ', '', $selector) . " {\n                    display: none !important;\n                }",
            array_unique($yoast_locked_section_selectors)
        )
    );
?>
    <style id="meza-yoast-promo-cleanup">
        .yoast_premium_upsell,
        #sidebar-container,
        #yoast-helpscout-beacon,
        #webinar-promo-notification,
        .notice-yoast.yoast-general-page-notices,
        .notice-yoast.yoast-webinar-dashboard,
        #yst-settings-header-root:empty {
            display: none !important;
        }

        #yoast-seo-settings .yst-feature-upsell--card,
        #yoast-seo-settings .yst-autocomplete-field--disabled,
        #yoast-seo-settings .yst-tag-field--disabled,
        #yoast-seo-settings .yst-toggle-field:has([role="switch"][disabled]),
        #yoast-seo-settings .yst-toggle-field:has([role="switch"][aria-disabled="true"]),
        #yoast-seo-settings .yst-toggle-field:has([role="switch"][data-headlessui-state~="disabled"]),
        #yoast-seo-settings .yst-toggle-field:has([data-headlessui-state~="disabled"]) {
            display: none !important;
        }

        <?php echo $yoast_locked_section_rules; ?>

        #yoast-seo-settings .yst-divide-y > div:has(a[href*="get-edd-integration"]) {
            display: none !important;
        }
    </style>
    <script id="meza-yoast-promo-cleanup-script">
        (() => {
            const shadowCss = `
                .yst-feature-upsell--card,
                .yst-autocomplete-field--disabled,
                .yst-tag-field--disabled,
                .yst-toggle-field:has([role="switch"][disabled]),
                .yst-toggle-field:has([role="switch"][aria-disabled="true"]),
                .yst-toggle-field:has([role="switch"][data-headlessui-state~="disabled"]),
                .yst-toggle-field:has([data-headlessui-state~="disabled"]) {
                    display: none !important;
                }

                <?php echo $yoast_shadow_locked_section_rules; ?>

                .yst-divide-y > div:has(a[href*="get-edd-integration"]) {
                    display: none !important;
                }
            `;
            const promoContainerSelectors = [
                '.yoast_premium_upsell',
                '.yoast-sidebar__product',
                '.yoast-sidebar__section',
                '.notice-yoast.yoast-general-page-notices',
                '.notice-yoast.yoast-webinar-dashboard',
                '#webinar-promo-notification',
                '.yst-max-w-4xl',
                '.yst-p-6.yst-flex.yst-flex-col',
                '.xl\\:yst-max-w-3xl'
            ].join(', ');
            const getYoastShadowRoots = () => Array.from(document.querySelectorAll('body *'))
                .filter((node) => node instanceof HTMLElement && node.shadowRoot instanceof ShadowRoot)
                .map((node) => node.shadowRoot)
                .filter((root) => root.querySelector('.yst-root'));
            const ensureYoastShadowStyles = () => {
                getYoastShadowRoots().forEach((root) => {
                    let style = root.getElementById('meza-yoast-shadow-cleanup');

                    if (!(style instanceof HTMLStyleElement)) {
                        style = document.createElement('style');
                        style.id = 'meza-yoast-shadow-cleanup';
                        root.appendChild(style);
                    }

                    if (style.textContent !== shadowCss) {
                        style.textContent = shadowCss;
                    }
                });
            };
            const removePromoNode = (node) => {
                if (!(node instanceof HTMLElement)) {
                    return;
                }

                const container = node.closest(promoContainerSelectors);

                if (container instanceof HTMLElement) {
                    container.remove();
                    return;
                }

                node.remove();
            };

            const cleanupYoastPromos = () => {
                [document, ...getYoastShadowRoots()].forEach((root) => {
                    root.querySelectorAll('#yoast-helpscout-beacon').forEach((node) => node.remove());
                    root.querySelectorAll('.yoast_premium_upsell, #sidebar-container').forEach((node) => node.remove());
                    root.querySelectorAll('.yoast-sidebar__product, .yoast-sidebar__section').forEach((node) => node.remove());
                    root.querySelectorAll('#webinar-promo-notification, .notice-yoast.yoast-general-page-notices, .notice-yoast.yoast-webinar-dashboard').forEach((node) => node.remove());
                    root.querySelectorAll('[data-action="load-nfd-ctb"]').forEach(removePromoNode);
                    root.querySelectorAll('a[href*="yoa.st/3t6"]').forEach((link) => {
                        if (!(link instanceof HTMLAnchorElement)) {
                            return;
                        }

                        removePromoNode(link);
                    });

                    root.querySelectorAll('#yst-settings-header-root').forEach((node) => {
                        if (node instanceof HTMLElement && node.childElementCount === 0 && node.textContent.trim() === '') {
                            node.remove();
                        }
                    });
                });
            };
            const cleanupYoastPromosNow = () => {
                ensureYoastShadowStyles();
                cleanupYoastPromos();
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', cleanupYoastPromosNow, { once: true });
            } else {
                cleanupYoastPromosNow();
            }

            const observer = new MutationObserver(() => cleanupYoastPromosNow());
            observer.observe(document.documentElement, { childList: true, subtree: true });
        })();
    </script>
<?php
}, 1002);

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


if (!defined('MZ_ADMIN_DIR')) {
    define('MZ_ADMIN_DIR', __DIR__ . '/mz-admin');
}

foreach ([
    'list-tables.php',
    'screen-defaults.php',
    'admin-menu.php',
    'row-actions.php',
    'acf-admin-columns.php',
    'admin-bar-branding-nav-menus.php',
    'editorial.php',
] as $mz_admin_module) {
    require_once MZ_ADMIN_DIR . '/' . $mz_admin_module;
}
