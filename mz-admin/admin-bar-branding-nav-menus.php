<?php

/** ================================
 *  ADMIN BAR CLEANUP
 *  ================================ */

if (!function_exists('meza_get_clear_cache_admin_bar_title')) {
    function meza_get_clear_cache_admin_bar_title(): string
    {
        return '<span class="ab-icon dashicons dashicons-database-remove" aria-hidden="true"></span><span class="ab-label">Clear Cache</span>';
    }
}

if (!function_exists('meza_is_clear_cache_request')) {
    function meza_is_clear_cache_request(): bool
    {
        $request_uri = strtolower((string) ($_SERVER['REQUEST_URI'] ?? ''));
        $wpaas_action = strtolower((string) ($_REQUEST['wpaas_action'] ?? ''));
        $wpsc_delete_cache = strtolower((string) ($_REQUEST['wpsc_delete_cache'] ?? ''));

        return $wpaas_action === 'flush_cache'
            || $wpsc_delete_cache !== ''
            || str_contains($request_uri, 'wpaas_action=flush_cache')
            || str_contains($request_uri, 'wpsc_delete_cache');
    }
}

if (!function_exists('meza_flush_object_cache_backends')) {
    function meza_flush_object_cache_backends(): void
    {
        global $wp_object_cache;

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
            return;
        }

        if (is_object($wp_object_cache) && method_exists($wp_object_cache, 'flush')) {
            $wp_object_cache->flush();
        }
    }
}

add_action('init', function (): void {
    if (!meza_can_access_clear_cache() || !meza_is_clear_cache_request()) {
        return;
    }

    meza_flush_object_cache_backends();
}, 5);

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
        || str_contains($title, 'clear page and object cache')
        || $title === 'clear cache'
        || (str_contains($node_id, 'super') && str_contains($node_id, 'cache'))
        || (str_contains($href, 'wp-super-cache') && str_contains($href, 'cache'))
        || str_contains($href, 'wpsc_delete_cache')
        || str_contains($href, 'wpaas_action=flush_cache');
}

function meza_is_admin_bar_wp_mail_smtp_node($node): bool
{
    if (!is_object($node)) return false;

    $node_id = strtolower((string) ($node->id ?? ''));
    $title = strtolower(trim(wp_strip_all_tags((string) ($node->title ?? ''))));
    $href = strtolower((string) ($node->href ?? ''));
    $meta_class = strtolower((string) ($node->meta->class ?? ''));

    return str_contains($node_id, 'wp-mail-smtp')
        || str_contains($title, 'wp mail smtp')
        || str_contains($href, 'wp-mail-smtp')
        || str_contains($meta_class, 'wp-mail-smtp');
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

    if (meza_is_admin_bar_wp_mail_smtp_node($node)) {
        return false;
    }

    if (
        strtolower((string) ($node->parent ?? '')) === 'new-content'
        && function_exists('meza_can_access_admin_bar_new_content_node')
        && meza_can_access_admin_bar_new_content_node($node)
    ) {
        return true;
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

    $add_clone = static function ($node, string $title_override = '', $parent_override = null, string $meta_title_override = '') use ($wp_admin_bar): void {
        if (!($node instanceof stdClass)) return;
        $node_id = (string) ($node->id ?? '');
        if ($node_id === '') return;

        $title = ($title_override !== '') ? $title_override : ($node->title ?? '');
        $meta = is_array($node->meta ?? null) ? $node->meta : [];
        $toolbar_action_classes = [];
        if (meza_is_admin_bar_query_monitor_node($node)) {
            $toolbar_action_classes[] = 'meza-admin-bar-toolbar-action';
            $toolbar_action_classes[] = 'meza-admin-bar-query-monitor-action';
        }
        if ($title_override !== '') {
            $toolbar_action_classes[] = 'meza-admin-bar-toolbar-action';
            $toolbar_action_classes[] = 'meza-admin-bar-cache-action';
        }
        if ($toolbar_action_classes !== []) {
            $meta['class'] = trim(((string) ($meta['class'] ?? '')) . ' ' . implode(' ', $toolbar_action_classes));
        }
        if ($meta_title_override !== '') {
            $meta['title'] = $meta_title_override;
        } elseif ($title_override !== '') {
            $meta['title'] = trim(wp_strip_all_tags($title_override));
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
            'title' => meza_get_clear_cache_admin_bar_title(),
            'href' => $flush_href,
            'group' => false,
            'meta' => [
                'title' => 'Clear Page and Object Cache',
                'class' => 'meza-admin-bar-toolbar-action meza-admin-bar-cache-action',
            ],
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
        $add_clone($delete_cache_node, meza_get_clear_cache_admin_bar_title(), $toolbar_parent, 'Clear Page and Object Cache');
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
    $child_titles = [];
    $child_hrefs = [];
    foreach ($nodes as $node) {
        if (!is_object($node) || (($node->parent ?? '') !== 'new-content')) continue;
        $all_children[] = $node;
        if (!meza_can_access_admin_bar_new_content_node($node)) continue;
        $children[] = $node;

        $child_title = strtolower(trim(wp_strip_all_tags((string) ($node->title ?? ''))));
        if ($child_title !== '') {
            $child_titles[$child_title] = true;
        }

        $child_href = trim((string) ($node->href ?? ''));
        if ($child_href !== '') {
            $child_hrefs[$child_href] = true;
        }
    }

    if (empty($all_children)) return;

    $existing_child_ids = [];
    foreach ($all_children as $child) {
        $child_id = strtolower((string) ($child->id ?? ''));
        if ($child_id !== '') {
            $existing_child_ids[$child_id] = true;
        }
    }

    if (function_exists('meza_get_site_overview_dashboard_post_type_items')) {
        foreach (meza_get_site_overview_dashboard_post_type_items(true) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $post_type = sanitize_key((string) ($item['post_type'] ?? ''));
            if ($post_type === '' || $post_type === 'attachment') {
                continue;
            }

            $post_type_object = get_post_type_object($post_type);
            if (!($post_type_object instanceof WP_Post_Type)) {
                continue;
            }

            $create_cap = (string) ($post_type_object->cap->create_posts ?? $post_type_object->cap->edit_posts ?? '');
            if ($create_cap === '' || !current_user_can($create_cap)) {
                continue;
            }

            $node_id = 'new-' . $post_type;
            if (isset($existing_child_ids[$node_id])) {
                continue;
            }

            $node = (object) [
                'id' => $node_id,
                'parent' => 'new-content',
                'title' => (string) ($item['singular_label'] ?? $item['label'] ?? $post_type_object->labels->singular_name ?? $post_type),
                'href' => $post_type === 'post'
                    ? admin_url('post-new.php')
                    : admin_url('post-new.php?post_type=' . $post_type),
                'group' => false,
                'meta' => [],
            ];

            if (!meza_can_access_admin_bar_new_content_node($node)) {
                continue;
            }

            $children[] = $node;
            $existing_child_ids[$node_id] = true;
            $child_titles[strtolower(trim(wp_strip_all_tags((string) $node->title)))] = true;
            $child_hrefs[(string) $node->href] = true;
        }
    }

    if (function_exists('meza_admin_get_dashboard_provider_items')) {
        foreach (meza_admin_get_dashboard_provider_items() as $item) {
            if (!is_array($item)) {
                continue;
            }

            $node_id = sanitize_key((string) ($item['new_node_id'] ?? ''));
            $title = trim((string) ($item['new_label'] ?? ''));
            $href = trim((string) ($item['new_url'] ?? ''));
            $required_capability = trim((string) ($item['new_capability'] ?? ''));

            if ($node_id === '' || $title === '' || $href === '' || isset($existing_child_ids[$node_id])) {
                continue;
            }

            $normalized_title = strtolower(trim(wp_strip_all_tags($title)));
            if (isset($child_titles[$normalized_title]) || isset($child_hrefs[$href])) {
                continue;
            }

            if ($required_capability !== '' && !current_user_can($required_capability)) {
                continue;
            }

            $node = (object) [
                'id' => $node_id,
                'parent' => 'new-content',
                'title' => $title,
                'href' => $href,
                'group' => false,
                'meta' => [],
            ];

            if (!meza_can_access_admin_bar_new_content_node($node)) {
                continue;
            }

            $children[] = $node;
            $existing_child_ids[$node_id] = true;
            $child_titles[$normalized_title] = true;
            $child_hrefs[$href] = true;
        }
    }

    foreach ($all_children as $child) {
        $wp_admin_bar->remove_node((string) $child->id);
    }

    if (count($children) >= 2) {
        $deduped_children = [];
        $seen_titles = [];
        $seen_hrefs = [];

        foreach ($children as $child) {
            if (!is_object($child)) {
                continue;
            }

            $normalized_title = strtolower(trim(wp_strip_all_tags((string) ($child->title ?? ''))));
            $normalized_href = trim((string) ($child->href ?? ''));

            if (
                ($normalized_title !== '' && isset($seen_titles[$normalized_title]))
                || ($normalized_href !== '' && isset($seen_hrefs[$normalized_href]))
            ) {
                continue;
            }

            if ($normalized_title !== '') {
                $seen_titles[$normalized_title] = true;
            }

            if ($normalized_href !== '') {
                $seen_hrefs[$normalized_href] = true;
            }

            $deduped_children[] = $child;
        }

        $children = $deduped_children;
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
    $description = is_string($description) ? $description : '';
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

add_action('admin_enqueue_scripts', function (string $hook_suffix): void {
    if ($hook_suffix !== 'options-general.php') {
        return;
    }

    wp_add_inline_script('jquery', <<<JS
jQuery(function ($) {
    ['#blogname', '#blogdescription', '#site_icon_hidden_field'].forEach(function (selector) {
        const row = $(selector).closest('tr');
        if (row.length) {
            row.hide();
        }
    });
});
JS, 'after');
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

if (!function_exists('meza_output_admin_bar_toolbar_alignment_css')) {
    function meza_output_admin_bar_toolbar_alignment_css(): void
    {
        echo '<style id="meza-admin-bar-cache-icon-alignment">' .
            '#wpadminbar #wp-admin-bar-wp-logo > .ab-item,' .
            '#wpadminbar #wp-admin-bar-site-name > .ab-item,' .
            '#wpadminbar #wp-admin-bar-new-content > .ab-item,' .
            '#wpadminbar .meza-admin-bar-toolbar-action > .ab-item{' .
            'display:flex!important;' .
            'align-items:center!important;' .
            'gap:6px;' .
            'min-height:32px!important;' .
            '}' .
            '#wpadminbar #wp-admin-bar-wp-logo > .ab-item .ab-icon,' .
            '#wpadminbar #wp-admin-bar-site-name > .ab-item:before,' .
            '#wpadminbar #wp-admin-bar-new-content > .ab-item:before,' .
            '#wpadminbar #wp-admin-bar-new-content > .ab-item .ab-icon,' .
            '#wpadminbar .meza-admin-bar-toolbar-action > .ab-item .ab-icon{' .
            'display:inline-flex!important;' .
            'float:none!important;' .
            'align-items:center!important;' .
            'justify-content:center!important;' .
            'width:20px!important;' .
            'min-width:20px!important;' .
            'height:20px!important;' .
            'padding:0!important;' .
            'margin:0!important;' .
            'line-height:20px!important;' .
            'font-size:20px!important;' .
            'vertical-align:middle!important;' .
            '}' .
            '#wpadminbar #wp-admin-bar-wp-logo > .ab-item .ab-icon:before,' .
            '#wpadminbar #wp-admin-bar-site-name > .ab-item:before,' .
            '#wpadminbar #wp-admin-bar-new-content > .ab-item:before,' .
            '#wpadminbar #wp-admin-bar-new-content > .ab-item .ab-icon:before,' .
            '#wpadminbar .meza-admin-bar-toolbar-action > .ab-item .ab-icon:before{' .
            'display:block!important;' .
            'width:20px!important;' .
            'height:20px!important;' .
            'line-height:20px!important;' .
            'font-size:20px!important;' .
            'margin:0!important;' .
            'top:0!important;' .
            '}' .
            '#wpadminbar #wp-admin-bar-new-content > .ab-item:before,' .
            '#wpadminbar #wp-admin-bar-new-content > .ab-item .ab-icon:before{' .
            'position:relative!important;' .
            'top:1px!important;' .
            '}' .
            '#wpadminbar #wp-admin-bar-wp-logo > .ab-item .screen-reader-text{' .
            'margin:0!important;' .
            '}' .
            '#wpadminbar #wp-admin-bar-wp-logo > .ab-item .ab-label,' .
            '#wpadminbar #wp-admin-bar-site-name > .ab-item .ab-label,' .
            '#wpadminbar #wp-admin-bar-new-content > .ab-item .ab-label,' .
            '#wpadminbar .meza-admin-bar-toolbar-action > .ab-item .ab-label{' .
            'line-height:32px!important;' .
            '}' .
            '</style>';
    }
}

add_action('admin_head', 'meza_output_admin_bar_toolbar_alignment_css', 99999);
add_action('wp_head', 'meza_output_admin_bar_toolbar_alignment_css', 99999);

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

add_action('admin_head-upload.php', function (): void {
    echo '<style id="meza-media-list-layout">'
        . '.upload-php .wp-list-table .column-mz_id{width:65px;min-width:65px;max-width:65px;}'
        . '.upload-php .wp-list-table .column-mz_converted{width:90px;min-width:90px;max-width:90px;text-align:center;}'
        . '.upload-php .wp-list-table .column-title{width:450px;min-width:450px;max-width:450px;}'
        . '.upload-php .wp-list-table .column-alt_text{width:225px;min-width:225px;max-width:225px;}'
        . '.upload-php .wp-list-table .column-mime_type{width:125px;min-width:125px;max-width:125px;}'
        . '.upload-php .wp-list-table .column-dimensions{width:125px;min-width:125px;max-width:125px;}'
        . '.upload-php .wp-list-table .column-file_size{width:125px;min-width:125px;max-width:125px;}'
        . '.upload-php .wp-list-table .column-parent{width:224px;min-width:224px;max-width:224px;}'
        . '.upload-php .wp-list-table .column-mz_modified{width:225px;min-width:225px;max-width:225px;}'
        . '.upload-php .wp-list-table .column-mz_published{width:225px;min-width:225px;max-width:225px;}'
        . '.upload-php .wp-list-table td.column-mz_converted{text-align:center;vertical-align:top!important;padding-top:10px!important;}'
        . '.upload-php .wp-list-table .meza-boolean-icon{display:inline-block;font-size:18px;line-height:1;font-weight:700;}'
        . '.upload-php .wp-list-table .meza-boolean-icon-true{color:#2271b1;}'
        . '.upload-php .wp-list-table .meza-boolean-icon-false{color:#b32d2e;}'
        . '.upload-php .wp-list-table td.column-title{vertical-align:top!important;}'
        . '.upload-php .wp-list-table td.column-title .media-icon{display:inline-flex!important;align-items:flex-start!important;justify-content:flex-start!important;width:100px!important;max-width:100%!important;max-height:100px!important;line-height:0!important;margin:0 12px 6px 0!important;vertical-align:top!important;float:left!important;overflow:visible!important;}'
        . '.upload-php .wp-list-table td.column-title .media-icon img{display:block!important;width:auto!important;height:auto!important;max-width:100px!important;max-height:100px!important;object-fit:contain!important;margin:0!important;}'
        . '.upload-php .wp-list-table td.column-title .row-title{display:block;}'
        . '</style>';
}, 1000);

add_action('admin_head-edit-comments.php', function (): void {
    echo '<style id="meza-comments-list-layout">'
        . '.edit-comments-php .wp-list-table .column-author{width:220px;min-width:220px;max-width:220px;}'
        . '.edit-comments-php .wp-list-table .column-comment_id{width:65px;min-width:65px;max-width:65px;}'
        . '.edit-comments-php .wp-list-table .column-comment{width:325px;min-width:325px;max-width:325px;}'
        . '.edit-comments-php .wp-list-table .column-response{width:225px;min-width:225px;max-width:225px;}'
        . '.edit-comments-php .wp-list-table .column-date{width:225px;min-width:225px;max-width:225px;}'
        . '</style>';
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
