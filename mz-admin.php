<?php

/**
 * Plugin Name: DS Admin
 * Description: Admin behavior, editorial workflow, and dashboard customization.
 * Version: 1.1.0
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

add_filter('option_page_capability_updraft-options-group', function (): string {
    return meza_backup_manager_capability();
});

add_filter('acf/get_options_page', function ($page, $slug) {
    if ($slug === 'crm' && is_array($page)) {
        $page['capability'] = 'manage_options';
    }

    return $page;
}, 20, 2);

add_filter('wpseo_submenu_pages', function (array $submenu_pages): array {
    $user = wp_get_current_user();
    if ($user instanceof WP_User && in_array(meza_site_manager_role_key(), (array) $user->roles, true)) {
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
    if ($user instanceof WP_User && in_array(meza_site_manager_role_key(), (array) $user->roles, true)) {
        $site_manager_page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        if (str_starts_with($site_manager_page, 'wpseo') || str_contains($site_manager_page, 'updraft')) {
            wp_safe_redirect(admin_url());
            exit;
        }
    }

    $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
    if (!in_array($page, ['wpseo_workouts', 'wpseo_redirects'], true)) {
        return;
    }

    wp_safe_redirect(admin_url('admin.php?page=wpseo_dashboard'));
    exit;
}, 1);

add_action('admin_menu', function (): void {
    $user = wp_get_current_user();
    if (!($user instanceof WP_User) || !in_array(meza_site_manager_role_key(), (array) $user->roles, true)) {
        return;
    }

    remove_menu_page('wpseo_dashboard');
    remove_menu_page('updraftplus');
    remove_menu_page('updraftcentral');

    global $menu;
    if (!is_array($menu)) {
        return;
    }

    foreach ($menu as $item) {
        $slug = (string) ($item[2] ?? '');
        $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
        $is_yoast = ($slug !== '' && str_starts_with($slug, 'wpseo'))
            || str_contains(strtolower($slug), 'wpseo')
            || str_contains($title, 'seo');
        $is_updraft = str_contains(strtolower($slug), 'updraft')
            || in_array($title, ['backups', 'updraft', 'updraftplus'], true);

        if ($is_yoast || $is_updraft) {
            remove_menu_page($slug);
        }
    }
}, PHP_INT_MAX);

add_action('admin_head-themes.php', function (): void {
    if (current_user_can('switch_themes')) {
        return;
    }
    ?>
    <style>
        .wrap .wp-heading-inline .title-count.theme-count,
        .theme-browser .theme.active .theme-actions .customize,
        .theme-overlay .theme-actions .customize {
            display: none !important;
        }
    </style>
    <?php
});

add_action('admin_menu', function (): void {
    if (current_user_can('switch_themes')) {
        return;
    }

    global $submenu;
    if (!isset($submenu['themes.php']) || !is_array($submenu['themes.php'])) {
        return;
    }

    foreach ($submenu['themes.php'] as &$item) {
        if (!is_array($item) || ((string) ($item[2] ?? '')) !== 'themes.php') {
            continue;
        }

        $item[0] = trim((string) preg_replace('/<span class="[^"]*update-plugins[^"]*">.*?<\/span>/i', '', (string) ($item[0] ?? '')));
        break;
    }
    unset($item);
}, PHP_INT_MAX);

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

function meza_post_has_permalink(int $post_id): bool
{
    if ($post_id <= 0) return false;
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

function meza_normalize_datetime_columns(array $columns): array
{
    if (!is_array($columns)) return $columns;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $post_type = ($screen instanceof WP_Screen) ? (string) ($screen->post_type ?? '') : '';
    if ($post_type === 'product') return $columns;
    $supports_thumbnail = ($post_type !== '' && post_type_supports($post_type, 'thumbnail'));
    $show_page_columns = !meza_is_acf_admin_post_type($post_type) && meza_post_type_has_permalink($post_type);
    $show_organization_url_column = ($post_type === 'organization');
    $show_form_slug_column = ($post_type === 'form');
    $show_review_columns = in_array($post_type, ['review', 'reviews'], true);
    $show_summary_column = (
        $post_type !== ''
        && !in_array($post_type, ['page', 'attachment'], true)
        && !meza_is_acf_admin_post_type($post_type)
        && (
            $post_type === 'cta'
            || meza_post_type_has_permalink($post_type)
        )
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
        if ($key === 'mz_id' || $key === 'mz_thumbnail') continue;
        if (!$show_page_columns && in_array((string) $key, ['mz_page_link', 'mz_page_headline', 'mz_page_cta'], true)) continue;

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
            if ($supports_thumbnail) $updated['mz_thumbnail'] = __('Image');
            $updated[$key] = in_array($post_type, ['cta', 'form'], true) ? __('Headline (H2)') : $label;
            if ($show_organization_url_column) $updated['mz_organization_url'] = __('URL');
            if ($show_summary_column) $updated['mz_summary'] = ($post_type === 'cta') ? __('Subhead') : __('Summary');
            if ($show_form_slug_column) $updated['mz_slug'] = __('Slug');
            if ($show_review_columns) {
                $updated['mz_review_quote'] = __('Quote');
                $updated['mz_review_citer'] = __('Citer');
            }
            if ($show_page_columns) {
                $updated['mz_page_link'] = __('Link');
                $updated['mz_page_headline'] = __('Page Headline (H1)');
                $updated['mz_page_cta'] = __('Page CTA');
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
    if ($show_organization_url_column && !isset($updated['mz_organization_url'])) {
        $updated['mz_organization_url'] = __('URL');
    }
    if ($show_page_columns) {
        if (!isset($updated['mz_page_link'])) $updated['mz_page_link'] = __('Link');
        if (!isset($updated['mz_page_headline'])) $updated['mz_page_headline'] = __('Page Headline (H1)');
        if (!isset($updated['mz_page_cta'])) $updated['mz_page_cta'] = __('Page CTA');
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
        // Pages: id, image, title, link, headline, cta, meta title, meta description, template, modified, published.
        $append('mz_id');
        $append('mz_thumbnail');
        $append('title');
        if ($show_organization_url_column) $append('mz_organization_url');
        $append_taxonomy_columns();
        if ($show_summary_column) $append('mz_summary');
        if ($show_form_slug_column) $append('mz_slug');
        if ($show_review_columns) {
            $append('mz_review_quote');
            $append('mz_review_citer');
        }
        if ($show_page_columns) {
            $append('mz_page_link');
            $append('mz_page_headline');
            $append('mz_page_cta');
        }
        $append('wpseo-title');
        $append('wpseo-metadesc');
        $append('mz_page_template');
        $append('template');
    } else {
        $append('mz_id');
        $append('mz_thumbnail');
        $append('title');
        if ($show_organization_url_column) $append('mz_organization_url');
        $append_taxonomy_columns();
        if ($show_summary_column) $append('mz_summary');
        if ($show_form_slug_column) $append('mz_slug');
        if ($show_review_columns) {
            $append('mz_review_quote');
            $append('mz_review_citer');
        }
        if ($show_page_columns) {
            $append('mz_page_link');
            $append('mz_page_headline');
            $append('mz_page_cta');
        }
        $append('wpseo-title');
        $append('wpseo-metadesc');
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

    $columns['mz_product_type'] = __('Product Type');
    $columns['mz_page_link'] = __('Link');
    $columns['mz_page_headline'] = __('Page Headline (H1)');
    $columns['mz_page_cta'] = __('Page CTA');
    $columns['mz_modified'] = __('Modified');
    $columns['mz_published'] = __('Published');

    if (defined('WPSEO_VERSION')) {
        if (!isset($columns['wpseo-title'])) $columns['wpseo-title'] = __('Meta Title');
        if (!isset($columns['wpseo-metadesc'])) $columns['wpseo-metadesc'] = __('Meta Description');
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
    $append(['featured'], 'featured', __('Featured'));
    $append(['thumb', 'mz_thumbnail'], 'thumb', __('Image'));
    $append(['name', 'title'], 'name', __('Name'));
    $append(['price'], 'price', __('Price'));
    $append(['is_in_stock'], 'is_in_stock', __('Stock'));
    $append(['taxonomy-product_brand', 'product_brand']);
    $append(['mz_product_type'], 'mz_product_type', __('Product Type'));
    $append(['sku'], 'sku', __('SKU'));
    $append(['product_cat', 'taxonomy-product_cat'], 'product_cat', __('Categories'));
    $append(['product_tag', 'taxonomy-product_tag'], 'product_tag', __('Tags'));
    $append(['mz_page_link'], 'mz_page_link', __('Link'));
    $append(['wpseo-title']);
    $append(['wpseo-metadesc']);
    $append(['mz_page_headline'], 'mz_page_headline', __('Page Headline (H1)'));
    $append(['mz_page_cta'], 'mz_page_cta', __('Page CTA'));
    $append(['mz_modified'], 'mz_modified', __('Modified'));
    $append(['mz_published'], 'mz_published', __('Published'));

    return $ordered;
}

function meza_get_product_admin_columns_for_visibility(): array
{
    $columns = [
        'cb' => '<input type="checkbox" />',
        'thumb' => __('Image'),
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

function meza_get_forced_hidden_product_admin_columns(): array
{
    return [
        'product_tag',
        'taxonomy-product_tag',
        'mz_page_link',
        'wpseo-title',
        'wpseo-metadesc',
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

function meza_register_datetime_sortable_columns(array $cols): array
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (($screen instanceof WP_Screen) && ((string) ($screen->post_type ?? '') === 'form')) {
        $cols['mz_slug'] = ['name', true];
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
            $column === 'mz_slug' ||
            $column === 'mz_summary' ||
            $column === 'mz_organization_url' ||
            $column === 'mz_review_quote' ||
            $column === 'mz_review_citer' ||
            $column === 'mz_thumbnail' ||
            $column === 'mz_page_link' ||
            $column === 'mz_page_headline' ||
            $column === 'mz_page_cta'
        ) {
            echo '&mdash;';
        }
        return;
    }

    if ($column === 'mz_id') {
        echo (int) $post_id;
        return;
    }
    if ($column === 'mz_slug') {
        $slug = (string) ($post->post_name ?? '');
        echo ($slug !== '') ? esc_html($slug) : '&mdash;';
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
    if ($column === 'mz_organization_url') {
        $url = '';
        if (function_exists('get_field')) {
            $acf_url = get_field('url', (int) $post_id);
            if (is_string($acf_url)) $url = trim($acf_url);
        }
        if ($url === '') $url = trim((string) get_post_meta((int) $post_id, 'url', true));

        if ($url === '') {
            echo '&mdash;';
            return;
        }

        echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Website') . '</a>';
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
        echo ($citer !== '') ? esc_html($citer) : '&mdash;';
        return;
    }

    if ($column === 'mz_thumbnail') {
        if (!post_type_supports((string) $post->post_type, 'thumbnail')) {
            echo '&mdash;';
            return;
        }

        $thumb_id = (int) get_post_thumbnail_id((int) $post_id);
        $thumb_html = get_the_post_thumbnail(
            (int) $post_id,
            'thumbnail',
            [
                'style' => 'max-width:100px;max-height:100px;width:auto;height:auto;display:block;margin:0;',
                'loading' => 'lazy',
                'decoding' => 'async',
            ]
        );
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

        if (is_string($image_edit_link) && $image_edit_link !== '') {
            echo '<a href="' . esc_url($image_edit_link) . '" target="_blank" rel="noopener noreferrer">' . $thumb_html . '</a>';
        } else {
            echo $thumb_html;
        }
        if (!empty($actions)) echo '<div class="row-actions">' . implode(' | ', $actions) . '</div>';
        return;
    }

    if ($column === 'mz_page_link') {
        if (!meza_post_has_permalink((int) $post_id)) {
            echo '&mdash;';
            return;
        }
        $url = (string) get_permalink((int) $post_id);

        echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($url) . '</a>';
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

add_filter('option_wpseo', 'meza_disable_yoast_cornerstone_option', 1000);
add_filter('wpseo_cornerstone_post_types', '__return_empty_array', 1000);

add_action('current_screen', function ($screen) {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    meza_remove_yoast_score_filters();

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type !== '') {
        add_filter("views_edit-{$post_type}", 'meza_remove_yoast_edit_view_tabs', 9999);
        add_filter("views_edit-{$post_type}", 'meza_remove_sorting_view_tab', 100000);
    }

    if ($screen->id !== 'edit-product') return;

    if (class_exists(\Automattic\WooCommerce\Internal\Admin\Loader::class)) {
        remove_action('in_admin_header', [\Automattic\WooCommerce\Internal\Admin\Loader::class, 'embed_page_header']);
        remove_filter('admin_body_class', [\Automattic\WooCommerce\Internal\Admin\Loader::class, 'add_admin_body_classes']);
        remove_action('admin_head', [\Automattic\WooCommerce\Internal\Admin\Loader::class, 'remove_notices']);
        remove_action('admin_notices', [\Automattic\WooCommerce\Internal\Admin\Loader::class, 'inject_before_notices'], -9999);
        remove_action('admin_notices', [\Automattic\WooCommerce\Internal\Admin\Loader::class, 'inject_after_notices'], PHP_INT_MAX);
    }
}, 1000);

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

        foreach (['wpseo-links', 'wpseo-linked', 'wpseo-score', 'wpseo-score-readability'] as $column_id) {
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
        . '.wp-list-table th.column-wpseo-metadesc,.wp-list-table td.column-wpseo-metadesc{width:275px;max-width:275px;}'
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

function meza_get_page_template_label(int $post_id): string
{
    if ((int) get_option('page_on_front') === $post_id) return esc_html__('Front Page');
    if ((int) get_option('page_for_posts') === $post_id) return esc_html__('Posts Page');
    if ((int) get_option('wp_page_for_privacy_policy') === $post_id) return esc_html__('Privacy Policy Page');

    $page_type = trim(meza_get_page_type_label($post_id));
    if ($page_type !== '') return esc_html($page_type);

    $template = (string) get_post_meta($post_id, '_wp_page_template', true);
    if ($template === '' || $template === 'default') {
        return esc_html__('Basic Page');
    }

    $template_path = locate_template($template, false, false);
    if (is_string($template_path) && $template_path !== '' && is_readable($template_path)) {
        $header = get_file_data($template_path, ['template_name' => 'Template Name']);
        $template_name = trim((string) ($header['template_name'] ?? ''));
        if ($template_name !== '') return esc_html($template_name);
    }

    $post = get_post($post_id);
    $templates = wp_get_theme()->get_page_templates($post instanceof WP_Post ? $post : null, 'page');
    foreach ($templates as $label => $file) {
        if ((string) $file !== $template) continue;
        $clean_label = trim((string) $label);
        if ($clean_label !== '') return esc_html($clean_label);
    }

    return '&mdash;';
}

function meza_get_page_template_filter_label(int $post_id): string
{
    $label = html_entity_decode(wp_strip_all_tags(meza_get_page_template_label($post_id)), ENT_QUOTES, 'UTF-8');
    return trim($label);
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
    if ((int) get_option('page_on_front') === $post_id) $labels[] = __('Front Page');
    if ((int) get_option('page_for_posts') === $post_id) $labels[] = __('Posts Page');
    if ((int) get_option('wp_page_for_privacy_policy') === $post_id) $labels[] = __('Privacy Policy Page');

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

// Replace the Page Template column with an MZ-rendered one so we can include moved title states.
add_filter('manage_pages_columns', function ($columns) {
    $updated = [];
    $has_template_column = false;
    foreach ($columns as $key => $label) {
        $is_template_column = ($key === 'template') || (strtolower(trim((string) $label)) === 'page template');
        if ($is_template_column) {
            $updated['mz_page_template'] = 'Page Template';
            $has_template_column = true;
            continue;
        }
        $updated[$key] = $label;
    }
    if (!$has_template_column) {
        $updated['mz_page_template'] = 'Page Template';
    }
    return $updated;
}, 1000);

// Render only the selected page template label from the editor dropdown in the Page Template cell.
$meza_render_page_template_column = function ($column, $post_id) {
    if ($column !== 'mz_page_template') return;

    echo meza_get_page_template_filter_link((int) $post_id);
};
add_action('manage_page_posts_custom_column', $meza_render_page_template_column, 100, 2);

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

add_filter('manage_edit-page_sortable_columns', function ($cols) {
    if (!is_array($cols)) return $cols;

    $cols['mz_page_template'] = ['mz_page_template', false];
    return $cols;
}, 1000);

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
        "WHEN {$page_type_meta_expr} IS NOT NULL THEN {$page_type_meta_expr} " .
        "WHEN {$page_type_terms_expr} IS NOT NULL THEN {$page_type_terms_expr} " .
        "ELSE {$template_label_expr} END)";

    $filter_label_expr =
        "(CASE " .
        "WHEN {$wpdb->posts}.ID = {$front_page_id} THEN 'Front Page' " .
        "WHEN {$wpdb->posts}.ID = {$posts_page_id} THEN 'Posts Page' " .
        "WHEN {$wpdb->posts}.ID = {$privacy_page_id} THEN 'Privacy Policy Page' " .
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
        $custom_title = 'WooCommerce';
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
        'column2' => ['dashboard_right_now'],
        'column3' => [],
    ];

    $site_kit_widget_id = meza_dashboard_find_site_kit_widget_id($widgets);
    if ($site_kit_widget_id !== '') $ids['column1'][] = $site_kit_widget_id;

    $woocommerce_widget_id = meza_dashboard_find_woocommerce_status_widget_id($widgets);
    if ($woocommerce_widget_id !== '') $ids['column2'][] = $woocommerce_widget_id;

    if (isset($widgets['dashboard_site_health'])) $ids['column2'][] = 'dashboard_site_health';

    $wp_mail_smtp_widget_id = meza_dashboard_find_wp_mail_smtp_widget_id($widgets);
    if ($wp_mail_smtp_widget_id !== '') $ids['column2'][] = $wp_mail_smtp_widget_id;

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

// Form edit screen: keep Slug visible for all users (including users with saved Screen Options).
add_filter('hidden_meta_boxes', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen) || $screen->id !== 'form') return $hidden;
    return array_values(array_diff((array) $hidden, ['slugdiv']));
}, 200, 2);

// Form edit screen: render core Slug in ACF's sortable "After Title" area.
add_action('add_meta_boxes_form', function ($post) {
    remove_meta_box('slugdiv', 'form', 'normal');
    add_meta_box(
        'slugdiv',
        __('Slug'),
        'post_slug_meta_box',
        'form',
        'acf_after_title',
        'high',
        ['__back_compat_meta_box' => true]
    );
}, 1000, 1);

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

// Hide selected admin menu items that we do not expose to editors/admins.
add_action('admin_menu', function () {
    remove_menu_page('edit-comments.php');
    remove_menu_page('godaddy-get-help');
    remove_menu_page('admin.php?page=godaddy-get-help');

    remove_submenu_page('edit.php', 'edit-tags.php?taxonomy=post_tag');

    remove_submenu_page('themes.php', 'customize.php');
    remove_submenu_page('themes.php', 'theme-editor.php');
    remove_submenu_page('themes.php', 'site-editor.php?path=/patterns');
    remove_submenu_page('themes.php', 'edit.php?post_type=wp_block');

    remove_submenu_page('plugins.php', 'plugin-editor.php');
}, 999);

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

        $is_customize = ($slug === 'customize.php')
            || str_starts_with($slug, 'customize.php?')
            || $label === 'customize';
        $is_patterns = ($slug === 'edit.php?post_type=wp_block')
            || (str_starts_with($slug, 'site-editor.php') && str_contains($slug, 'patterns'))
            || $label === 'patterns';

        return !($is_customize || $is_patterns);
    }));
}, 99999);

function meza_admin_menu_content_group(string $menu_slug): string
{
    $menu_slug = trim($menu_slug);
    if ($menu_slug === '') return '';

    if (function_exists('acf_get_options_page') && acf_get_options_page($menu_slug)) {
        return 'without';
    }

    if ($menu_slug === 'upload.php' || $menu_slug === 'edit.php') {
        return 'with';
    }

    if (!str_starts_with($menu_slug, 'edit.php?post_type=')) return '';

    $post_type = (string) wp_unslash((string) parse_url($menu_slug, PHP_URL_QUERY));
    parse_str($post_type, $query_args);
    $post_type = (string) ($query_args['post_type'] ?? '');
    if ($post_type === '') return '';
    if (function_exists('meza_is_acf_admin_post_type') && meza_is_acf_admin_post_type($post_type)) return '';

    $post_type_object = get_post_type_object($post_type);
    if (!($post_type_object instanceof WP_Post_Type)) return '';
    if (empty($post_type_object->show_ui)) return '';

    return meza_post_type_has_permalink($post_type) ? 'with' : 'without';
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
            || str_contains($slug, 'all-in-one')
            || str_contains($title, 'all-in-one')
            || str_contains($title, 'aios');
        $is_yoast = str_contains($slug, 'wpseo')
            || str_contains($title, 'yoast seo')
            || $title === 'seo';
        $is_godaddy_dashboard = str_contains($slug, 'page=wp-dashboard')
            || str_contains($slug, 'godaddy')
            || $title === 'godaddy';
        $is_customer_sign_generator = str_contains($slug, 'client-sign-generator')
            || $title === 'customer sign generator';
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

        if ($is_make) {
            $item[0] = 'Make';
            if (isset($item[3])) $item[3] = 'Make';
            $item[6] = 'dashicons-share-alt';
        }
    }
    unset($item);

    foreach ($submenu as $parent_slug => &$items) {
        if (!is_array($items)) continue;

        foreach ($items as $index => &$item) {
            if (!is_array($item)) continue;

            $slug = strtolower((string) ($item[2] ?? ''));
            $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
            $is_yoast_menu = str_contains(strtolower((string) $parent_slug), 'wpseo');
            $is_wp_mail_smtp_menu = str_contains(strtolower((string) $parent_slug), 'wp-mail-smtp');
            $is_redirection = str_contains($slug, 'redirection')
                || $title === 'redirection';

            if (str_contains($title, 'upgrade')) {
                unset($items[$index]);
                continue;
            }

            if (str_contains($title, 'yoast')) {
                unset($items[$index]);
                continue;
            }

            if ($parent_slug === 'tools.php' && $is_redirection) {
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

            if ($parent_slug === 'options-general.php' && $is_redirection) {
                $item[0] = 'Redirects';
                if (isset($item[3])) $item[3] = 'Redirects';
            }
        }
        unset($item);
        $items = array_values($items);

        if ($parent_slug === 'options-general.php') {
            $ordered_labels = [
                'Redirects',
                'Page Cache',
                'Object Cache',
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
    }
    unset($items);
}

// Normalize selected plugin/admin menu labels.
add_action('admin_menu', 'meza_normalize_admin_plugin_menus', PHP_INT_MAX - 2);

function meza_rebuild_content_menu_group(): void
{
    global $menu;

    if (!is_array($menu) || empty($menu)) return;

    $with_permalink = [];
    $without_permalink = [];

    foreach ($menu as $item) {
        if (!is_array($item)) continue;

        $slug = (string) ($item[2] ?? '');
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

    $rebuilt = [];
    $inserted = false;

    foreach ($menu as $item) {
        if (!is_array($item)) {
            $rebuilt[] = $item;
            continue;
        }

        $slug = (string) ($item[2] ?? '');
        if (meza_admin_menu_content_group($slug) !== '') {
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
    $is_site_manager_user = $user instanceof WP_User
        && in_array(meza_site_manager_role_key(), (array) $user->roles, true);

    $dashboard_index = null;
    $ordered_items = [
        'web_analytics' => null,
        'seo' => null,
        'backups' => null,
    ];
    $matched_indexes = [];

    foreach ($menu as $index => $item) {
        if (!is_array($item)) continue;

        $slug = strtolower((string) ($item[2] ?? ''));
        $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));

        if ($slug === 'index.php' && $dashboard_index === null) {
            $dashboard_index = (int) $index;
            continue;
        }

        $is_site_kit = str_contains($slug, 'googlesitekit')
            || str_contains($slug, 'google-site-kit')
            || str_contains($slug, 'site-kit')
            || in_array($title, ['web analytics', 'site kit', 'site kit by google'], true);
        $is_yoast = str_contains($slug, 'wpseo')
            || str_contains($slug, 'wordpress-seo')
            || str_contains($title, 'seo');
        $is_updraft = str_contains($slug, 'updraft')
            || in_array($title, ['backups', 'updraft', 'updraftplus'], true);

        if ($is_site_kit) {
            if ($ordered_items['web_analytics'] === null) {
                $ordered_items['web_analytics'] = $item;
            }
            $matched_indexes[] = (int) $index;
            continue;
        }

        if ($is_yoast) {
            if (!$is_site_manager_user && $ordered_items['seo'] === null) {
                $ordered_items['seo'] = $item;
            }
            $matched_indexes[] = (int) $index;
            continue;
        }

        if ($is_updraft) {
            if (!$is_site_manager_user && $ordered_items['backups'] === null) {
                $ordered_items['backups'] = $item;
            }
            $matched_indexes[] = (int) $index;
        }
    }

    if ($dashboard_index === null || empty($matched_indexes)) return;

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

    $items_to_insert = array_values(array_filter($ordered_items, static function ($item): bool {
        return is_array($item);
    }));

    if (empty($items_to_insert)) return;

    array_splice($menu, $dashboard_index + 1, 0, $items_to_insert);
}

// Keep utility plugins grouped with Dashboard in a fixed order before the content separator.
add_action('admin_menu', 'meza_reorder_dashboard_utility_items', PHP_INT_MAX);

function meza_group_customer_sign_generator_under_dashboard(): void
{
    global $menu;

    if (!is_array($menu) || empty($menu)) return;

    $customer_sign_item = null;
    $matched_indexes = [];

    foreach ($menu as $index => $item) {
        if (!is_array($item)) continue;

        $slug = strtolower((string) ($item[2] ?? ''));
        $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
        $is_customer_sign_generator = str_contains($slug, 'client-sign-generator')
            || $title === 'customer sign generator';

        if (!$is_customer_sign_generator) continue;

        if ($customer_sign_item === null) {
            $customer_sign_item = $item;
        }

        $matched_indexes[] = (int) $index;
    }

    if (!is_array($customer_sign_item) || empty($matched_indexes)) return;

    rsort($matched_indexes, SORT_NUMERIC);
    foreach ($matched_indexes as $matched_index) {
        array_splice($menu, $matched_index, 1);
    }

    $dashboard_index = null;
    foreach ($menu as $index => $item) {
        if (is_array($item) && ((string) ($item[2] ?? '')) === 'index.php') {
            $dashboard_index = (int) $index;
            break;
        }
    }

    if ($dashboard_index === null) {
        $menu[] = $customer_sign_item;
        return;
    }

    $dashboard_group_end = $dashboard_index + 1;

    for ($i = $dashboard_index + 1, $count = count($menu); $i < $count; $i++) {
        $item = $menu[$i] ?? null;
        if (!is_array($item)) break;

        $slug = strtolower((string) ($item[2] ?? ''));
        $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
        $is_separator = str_starts_with($slug, 'separator')
            || str_contains(strtolower((string) ($item[4] ?? '')), 'wp-menu-separator');
        $is_dashboard_utility = str_contains($slug, 'googlesitekit')
            || str_contains($slug, 'google-site-kit')
            || str_contains($slug, 'site-kit')
            || str_contains($slug, 'wpseo')
            || str_contains($slug, 'wordpress-seo')
            || str_contains($slug, 'updraft')
            || in_array($title, ['web analytics', 'site kit', 'site kit by google', 'seo', 'backups', 'updraft', 'updraftplus'], true);

        if ($is_separator || !$is_dashboard_utility) break;

        $dashboard_group_end = $i + 1;
    }

    $items_to_insert = [[
        '',
        'read',
        'separator-meza-dashboard-customer-sign-generator',
        '',
        'wp-menu-separator',
    ]];

    $items_to_insert[] = $customer_sign_item;

    array_splice($menu, $dashboard_group_end, 0, $items_to_insert);
}

// Keep Customer Sign Generator in its own block directly below the Dashboard utility group.
add_action('admin_menu', 'meza_group_customer_sign_generator_under_dashboard', PHP_INT_MAX);

function meza_ensure_site_manager_woocommerce_analytics_submenu(): void
{
    if (!meza_has_woocommerce_plugin()) return;

    $user = wp_get_current_user();
    $is_site_manager_user = $user instanceof WP_User
        && in_array(meza_site_manager_role_key(), (array) $user->roles, true);

    if (!$is_site_manager_user) {
        return;
    }

    global $menu;
    if (!is_array($menu)) {
        return;
    }

    foreach ($menu as $item) {
        if (!is_array($item)) continue;

        $slug = strtolower((string) ($item[2] ?? ''));
        if ($slug === 'meza-woocommerce-analytics') {
            return;
        }
    }

    add_menu_page(
        'Analytics',
        'Analytics',
        'read',
        'meza-woocommerce-analytics',
        static function (): void {
            wp_safe_redirect(admin_url('admin.php?page=wc-admin&path=/analytics/overview'));
            exit;
        },
        'dashicons-chart-bar',
        57
    );

    global $submenu;
    if (!is_array($submenu)) {
        return;
    }

    $existing_slugs = [];
    foreach ((array) ($submenu['meza-woocommerce-analytics'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $existing_slugs[] = strtolower((string) ($item[2] ?? ''));
    }

    $analytics_items = [
        'meza-woocommerce-analytics-overview' => [
            'label' => 'Overview',
            'path' => '/analytics/overview',
        ],
        'meza-woocommerce-analytics-products' => [
            'label' => 'Products',
            'path' => '/analytics/products',
        ],
        'meza-woocommerce-analytics-revenue' => [
            'label' => 'Revenue',
            'path' => '/analytics/revenue',
        ],
        'meza-woocommerce-analytics-orders' => [
            'label' => 'Orders',
            'path' => '/analytics/orders',
        ],
        'meza-woocommerce-analytics-variations' => [
            'label' => 'Variations',
            'path' => '/analytics/variations',
        ],
        'meza-woocommerce-analytics-categories' => [
            'label' => 'Categories',
            'path' => '/analytics/categories',
        ],
        'meza-woocommerce-analytics-coupons' => [
            'label' => 'Coupons',
            'path' => '/analytics/coupons',
        ],
        'meza-woocommerce-analytics-taxes' => [
            'label' => 'Taxes',
            'path' => '/analytics/taxes',
        ],
        'meza-woocommerce-analytics-downloads' => [
            'label' => 'Downloads',
            'path' => '/analytics/downloads',
        ],
        'meza-woocommerce-analytics-settings' => [
            'label' => 'Settings',
            'path' => '/analytics/settings',
        ],
    ];

    if (get_option('woocommerce_manage_stock') === 'yes') {
        $analytics_items['meza-woocommerce-analytics-stock'] = [
            'label' => 'Stock',
            'path' => '/analytics/stock',
        ];
    }

    foreach ($analytics_items as $slug => $item) {
        if (in_array(strtolower($slug), $existing_slugs, true)) {
            continue;
        }

        add_submenu_page(
            'meza-woocommerce-analytics',
            $item['label'],
            $item['label'],
            'read',
            $slug,
            static function () use ($item): void {
                wp_safe_redirect(admin_url('admin.php?page=wc-admin&path=/' . ltrim((string) $item['path'], '/')));
                exit;
            }
        );
    }
}

// WooCommerce Analytics is top-level by default; add a matching top-level entry for site managers.
add_action('admin_menu', 'meza_ensure_site_manager_woocommerce_analytics_submenu', PHP_INT_MAX - 1);

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

function meza_group_woocommerce_top_level_items(): void
{
    global $menu;

    if (!is_array($menu) || empty($menu)) return;

    $appearance_index = null;
    foreach ($menu as $index => $item) {
        if (is_array($item) && ((string) ($item[2] ?? '')) === 'themes.php') {
            $appearance_index = (int) $index;
            break;
        }
    }

    if ($appearance_index === null) return;

    $ordered_items = [
        'woocommerce' => null,
        'products' => null,
        'marketing' => null,
        'analytics' => null,
    ];
    $matched_indexes = [];

    foreach ($menu as $index => $item) {
        if (!is_array($item)) continue;

        $slug = strtolower((string) ($item[2] ?? ''));
        $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));

        if ($slug === 'woocommerce' || $title === 'woocommerce') {
            if ($ordered_items['woocommerce'] === null) {
                $ordered_items['woocommerce'] = $item;
            }
            $matched_indexes[] = (int) $index;
            continue;
        }

        if ($slug === 'edit.php?post_type=product' || $title === 'products') {
            if ($ordered_items['products'] === null) {
                $ordered_items['products'] = $item;
            }
            $matched_indexes[] = (int) $index;
            continue;
        }

        if (
            str_contains($slug, 'wc-admin&path=/marketing')
            || str_contains($slug, 'woocommerce-marketing')
            || $title === 'marketing'
        ) {
            if ($ordered_items['marketing'] === null) {
                $ordered_items['marketing'] = $item;
            }
            $matched_indexes[] = (int) $index;
            continue;
        }

        if (
            $slug === 'meza-woocommerce-analytics'
            || $slug === 'wc-admin&path=/analytics/overview'
            || (str_contains($slug, 'wc-admin') && str_contains($slug, '/analytics'))
            || $title === 'analytics'
        ) {
            if ($ordered_items['analytics'] === null) {
                $ordered_items['analytics'] = $item;
            }
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

    foreach ($menu as $index => $item) {
        if (is_array($item) && ((string) ($item[2] ?? '')) === 'themes.php') {
            $appearance_index = (int) $index;
            break;
        }
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

    array_splice($menu, $appearance_index, 0, $block);
}

// Keep WooCommerce-related top-level menus together directly above Appearance.
add_action('admin_menu', 'meza_group_woocommerce_top_level_items', PHP_INT_MAX);

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

        $classes = strtolower((string) ($item[4] ?? ''));
        if (str_contains($classes, 'wp-menu-separator')) continue;

        $slug = strtolower((string) ($item[2] ?? ''));
        $label = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
        $is_hosting = str_contains($slug, 'page=wp-dashboard')
            || str_contains($slug, 'godaddy')
            || in_array($label, ['hosting', 'godaddy'], true);
        $is_make = str_contains($slug, 'ds-make')
            || $label === 'make';

        if ((int) $index <= $settings_index && !$is_make && !$is_hosting) continue;

        $is_acf = $slug === 'edit.php?post_type=acf-field-group'
            || $label === 'acf';
        $is_mail = str_contains($slug, 'wp-mail-smtp')
            || $label === 'mail';
        $is_security = str_contains($slug, 'aiowpsec')
            || str_contains($slug, 'wp-security')
            || $label === 'security';
        $is_make = $is_make || str_contains($slug, 'ds-make')
            || $label === 'make';

        if (!$is_acf && !$is_mail && !$is_security && !$is_hosting && !$is_make) continue;

        $utility_indexes[] = (int) $index;
        $utility_items[] = [
            'item' => $item,
            'label' => $label,
        ];
    }

    if (empty($utility_items)) return;

    usort($utility_items, static function (array $a, array $b): int {
        $priority = [
            'acf' => 10,
            'mail' => 20,
            'security' => 30,
            'hosting' => 40,
            'godaddy' => 40,
            'make' => 50,
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

    array_splice($menu, $settings_index + 1, 0, $items_to_insert);
}

// Keep ACF, Mail, Security, Hosting, and Make in their own utility group below Settings.
add_action('admin_menu', 'meza_group_post_settings_utilities', PHP_INT_MAX);

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

// Admin Menu Editor swaps in its custom menu after admin_menu, so reapply these mutations then as well.
add_action('admin_menu_editor-menu_replaced', function () {
    meza_normalize_admin_plugin_menus();
    meza_rebuild_content_menu_group();
    meza_reorder_dashboard_utility_items();
    meza_group_customer_sign_generator_under_dashboard();
    meza_ensure_site_manager_woocommerce_analytics_submenu();
    meza_remove_payments_admin_menu();
    meza_group_woocommerce_top_level_items();
    meza_group_post_settings_utilities();
    meza_cleanup_menu_separators();
}, PHP_INT_MAX);

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

    $submenu['tools.php'] = array_values($submenu['tools.php']);
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

        $post_type_object = get_post_type_object($post_type);
        $singular_label = '';
        if ($post_type_object instanceof WP_Post_Type) {
            $singular_label = trim((string) ($post_type_object->labels->singular_name ?? ''));
        }
        if ($singular_label === '') {
            $singular_label = ucwords(str_replace(['-', '_'], ' ', $post_type));
        }

        foreach ($items as &$item) {
            if (!is_array($item)) continue;

            $label = trim(wp_strip_all_tags((string) ($item[0] ?? '')));
            $slug = strtolower((string) ($item[2] ?? ''));
            $is_add_screen = ($slug === 'post-new.php')
                || str_starts_with($slug, 'post-new.php?');

            if ($is_add_screen && preg_match('/^add\s+new\b/i', $label)) {
                $new_label = 'Add';
                if ($singular_label !== '') {
                    $new_label .= ' ' . $singular_label;
                }
                $new_label = trim(preg_replace('/\s+/', ' ', $new_label) ?? $new_label);

                $item[0] = $new_label;
                if (isset($item[3])) $item[3] = $new_label;
            }
        }
        unset($item);
    }
    unset($items);
}, 100002);

// Simplify post-type taxonomy/import/export submenu labels by removing the parent post type name.
add_action('admin_menu', function () {
    global $menu, $submenu;

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

        $post_type_object = get_post_type_object($post_type);
        $strip_candidates = [];

        foreach ($menu as $menu_item) {
            if (!is_array($menu_item)) continue;
            if (((string) ($menu_item[2] ?? '')) !== (string) $parent_slug) continue;

            $menu_label = trim(wp_strip_all_tags((string) ($menu_item[0] ?? '')));
            if ($menu_label !== '') $strip_candidates[] = $menu_label;
            break;
        }

        if ($post_type_object instanceof WP_Post_Type) {
            $plural = trim((string) ($post_type_object->labels->name ?? ''));
            $singular = trim((string) ($post_type_object->labels->singular_name ?? ''));
            if ($plural !== '') $strip_candidates[] = $plural;
            if ($singular !== '') $strip_candidates[] = $singular;
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
    }
    unset($items);
}, 100003);

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

        if (!preg_match('/\btarget\s*=/i', $attrs)) {
            $attrs .= ' target="_blank"';
        }
        if (!preg_match('/\brel\s*=/i', $attrs)) {
            $attrs .= ' rel="noopener noreferrer"';
        }

        $new_label = $label !== '' ? $label : meza_strip_post_type_from_action_label($text, $post);
        if ($new_label !== '') $text = esc_html($new_label);
        return '<a' . $attrs . '>' . $text . '</a>';
    }, $html, 1) ?? $html;
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
        $actions[$key] = meza_update_admin_action_link($action, $post);
    }

    return $actions;
}

add_filter('post_row_actions', 'meza_remove_quick_edit_action', 1000, 2);
add_filter('page_row_actions', 'meza_remove_quick_edit_action', 1000, 2);

add_filter('post_updated_messages', function (array $messages): array {
    global $post;
    if (!($post instanceof WP_Post)) return $messages;

    $post_type = (string) $post->post_type;
    if ($post_type === '' || !isset($messages[$post_type]) || !is_array($messages[$post_type])) return $messages;

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

    $product_screen_css = '';
    if ($post_type === 'product') {
        $product_screen_css = '#wpbody{margin-top:0!important;}';
    }

    echo '<style id="meza-admin-list-column-widths">' .
        $product_screen_css .
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
        '.wp-list-table .column-mz_id{width:75px;}' .
        '.wp-list-table .column-mz_slug{width:175px;max-width:175px;}' .
        '.wp-list-table .column-mz_organization_url{width:125px;max-width:125px;}' .
        '.wp-list-table .column-mz_summary{width:325px;max-width:325px;}' .
        '.wp-list-table .column-mz_review_quote{width:325px;max-width:325px;}' .
        '.wp-list-table .column-mz_review_citer{width:175px;max-width:175px;}' .
        '.wp-list-table .column-mz_thumbnail{width:125px;}' .
        '.wp-list-table td.column-mz_thumbnail{vertical-align:top!important;}' .
        '.wp-list-table .column-mz_thumbnail .row-actions{font-size:11px;line-height:1.1;}' .
        '.wp-list-table .column-title{width:225px;}' .
        '.wp-list-table .column-mz_modified,.wp-list-table .column-mz_published{width:225px;}' .
        '.wp-list-table th.column-categories,.wp-list-table td.column-categories{width:225px;max-width:225px;}' .
        '.wp-list-table th[class*="column-taxonomy-"],.wp-list-table td[class*="column-taxonomy-"]{width:225px;}' .
        '.wp-list-table th.column-mz_page_template,.wp-list-table td.column-mz_page_template{width:150px;max-width:150px;}' .
        '.wp-list-table th.column-mz_page_headline,.wp-list-table td.column-mz_page_headline{width:225px;max-width:225px;}' .
        '.wp-list-table th.column-mz_page_cta,.wp-list-table td.column-mz_page_cta{width:175px;max-width:175px;}' .
        '</style>';

    if ($post_type === 'product') {
        echo '<style id="meza-product-admin-column-widths">' .
            '.wp-list-table th.column-featured,.wp-list-table td.column-featured{width:48px!important;min-width:48px!important;max-width:48px!important;text-align:center;}' .
            '.wp-list-table th.column-thumb,.wp-list-table td.column-thumb{width:78px!important;min-width:78px!important;max-width:78px!important;}' .
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
            '.wp-list-table th.column-wpseo-metadesc,.wp-list-table td.column-wpseo-metadesc{width:320px!important;min-width:320px!important;max-width:320px!important;}' .
            '.wp-list-table th.column-mz_page_headline,.wp-list-table td.column-mz_page_headline{width:260px!important;min-width:260px!important;max-width:260px!important;}' .
            '.wp-list-table th.column-mz_page_cta,.wp-list-table td.column-mz_page_cta{width:220px!important;min-width:220px!important;max-width:220px!important;}' .
            '.wp-list-table th.column-mz_modified,.wp-list-table td.column-mz_modified,.wp-list-table th.column-mz_published,.wp-list-table td.column-mz_published{width:220px!important;min-width:220px!important;max-width:220px!important;vertical-align:top!important;}' .
            '.wp-list-table td.column-sku,.wp-list-table td.column-product_cat,.wp-list-table td.column-taxonomy-product_cat,.wp-list-table td.column-product_tag,.wp-list-table td.column-taxonomy-product_tag,.wp-list-table td.column-mz_page_link,.wp-list-table td.column-wpseo-title,.wp-list-table td.column-wpseo-metadesc,.wp-list-table td.column-mz_page_headline,.wp-list-table td.column-mz_page_cta{white-space:normal!important;overflow-wrap:anywhere;word-break:break-word;vertical-align:top!important;}' .
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

function meza_remove_admin_bar_nodes($wp_admin_bar): void
{
    if (!($wp_admin_bar instanceof WP_Admin_Bar)) return;

    // Known core/plugin IDs.
    $wp_admin_bar->remove_node('comments');
    $wp_admin_bar->remove_node('wpseo-menu');
    $wp_admin_bar->remove_node('updraft_admin_node');
    $wp_admin_bar->remove_node('updraftplus_admin_node');

    // Fallback for plugin/version-specific IDs.
    $nodes = $wp_admin_bar->get_nodes();
    if (!is_array($nodes)) return;

    foreach ($nodes as $node) {
        if (!is_object($node) || !isset($node->id)) continue;

        $id = strtolower((string) $node->id);
        $title = strtolower(wp_strip_all_tags((string) ($node->title ?? '')));
        $href = strtolower((string) ($node->href ?? ''));
        $meta_class = strtolower((string) (($node->meta['class'] ?? '')));

        $is_yoast = str_contains($id, 'wpseo')
            || str_contains($title, 'yoast')
            || str_contains($href, 'wpseo')
            || str_contains($meta_class, 'wpseo');
        $is_updraft = str_contains($id, 'updraft')
            || str_contains($title, 'updraft')
            || str_contains($href, 'updraft')
            || str_contains($meta_class, 'updraft');
        $is_feedback = str_contains($title, 'leave feedback');
        $is_assistant = (
            $title === 'assistant'
            || str_contains($title, 'assistant')
        );

        if ($is_yoast || $is_updraft || $is_feedback || $is_assistant) {
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

    $add_flush = static function () use ($wp_admin_bar, $flush_node_id, $quick_links_hidden_successfully): void {
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
            'parent' => 'top-secondary',
            'title' => 'Clear Cache',
            'href' => $flush_href,
            'group' => false,
            'meta' => ['title' => 'Clear Cache'],
        ]);
    };

    if ($query_monitor_node instanceof stdClass) {
        $query_monitor_id = (string) ($query_monitor_node->id ?? '');
        if ($query_monitor_id !== '') $wp_admin_bar->remove_node($query_monitor_id);
        $add_clone($query_monitor_node, '', 'top-secondary');
    }

    if ($delete_cache_node instanceof stdClass) {
        $delete_cache_id = (string) ($delete_cache_node->id ?? '');
        if ($delete_cache_id !== '') $wp_admin_bar->remove_node($delete_cache_id);
    }

    // Show exactly one cache action at a time:
    // - page cache when WP Super Cache is installed
    // - server cache only on qa/production when WP Super Cache is not installed
    if ($should_show_page_cache) {
        $add_clone($delete_cache_node, 'Clear Cache', 'top-secondary');
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

// Keep the "New" admin-bar dropdown in alphabetical order.
add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!($wp_admin_bar instanceof WP_Admin_Bar)) return;

    $nodes = $wp_admin_bar->get_nodes();
    if (!is_array($nodes)) return;

    $children = [];
    foreach ($nodes as $node) {
        if (!is_object($node) || (($node->parent ?? '') !== 'new-content')) continue;
        $children[] = $node;
    }

    if (count($children) < 2) return;

    usort($children, static function ($a, $b) {
        $title_a = strtolower(trim(wp_strip_all_tags((string) ($a->title ?? ''))));
        $title_b = strtolower(trim(wp_strip_all_tags((string) ($b->title ?? ''))));
        return strnatcmp($title_a, $title_b);
    });

    foreach ($children as $child) {
        $wp_admin_bar->remove_node((string) $child->id);
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
}, 100000);

function meza_get_custom_logo_url(): string
{
    $custom_logo_id = (int) get_theme_mod('custom_logo');
    if ($custom_logo_id <= 0) return '';

    $custom_logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
    return is_string($custom_logo_url) ? $custom_logo_url : '';
}

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

// Hide plugin/theme update badges in the left admin menu, but keep Updates and Site Health notices.
add_action('admin_head', function () {
    echo '<style id="meza-admin-menu-hide-selected-counters">' .
        '#adminmenu #menu-plugins .update-plugins,' .
        '#adminmenu #menu-appearance .wp-submenu a[href="themes.php"] .update-plugins{' .
        'display:none!important;' .
        '}' .
        '</style>';
}, 99999);

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

    $post_types = get_post_types(['public' => true], 'objects');

    foreach ($post_types as $post_type) {
        if (!current_user_can($post_type->cap->edit_posts)) {
            continue;
        }

        $count_obj = wp_count_posts($post_type->name);
        $published = (int) $count_obj->publish;

        if ($published === 0) {
            continue;
        }

        $label = $published === 1 ? $post_type->labels->singular_name : $post_type->labels->name;
        $url = admin_url('edit.php?post_type=' . $post_type->name);
        $icon_class = 'dashicons-admin-post';

        if (!empty($post_type->menu_icon) && str_contains($post_type->menu_icon, 'dashicons-')) {
            $icon_class = $post_type->menu_icon;
        }

        $custom_items[] = [
            'name' => 'meza-' . $post_type->name,
            'count' => $published,
            'label' => $label,
            'url' => $url,
            'icon' => $icon_class,
        ];
    }

    if (current_user_can('edit_posts')) {
        $num_comments = wp_count_comments();
        $approved = (int) $num_comments->approved;

        if ($approved > 0) {
            $custom_items[] = [
                'name' => 'meza-comments',
                'count' => $approved,
                'label' => _n('Comment', 'Comments', $approved),
                'url' => admin_url('edit-comments.php'),
                'icon' => 'dashicons-admin-comments',
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
        #dashboard_right_now .comment-count {
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
