<?php

function meza_is_promotional_submenu_item(string $parent_slug, array $item): bool
{
    $parent_slug = strtolower($parent_slug);
    $slug = strtolower((string) ($item[2] ?? ''));
    $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));

    if ($title === '') return false;

    foreach (meza_admin_get_submenu_cleanup_rules() as $rule) {
        if (!is_array($rule) || ($rule['type'] ?? '') !== 'promotional') {
            continue;
        }

        if (meza_admin_submenu_cleanup_rule_matches($parent_slug, $slug, $title, $rule)) {
            return true;
        }
    }

    return false;
}

function meza_is_resource_submenu_item(string $parent_slug, array $item): bool
{
    $parent_slug = strtolower($parent_slug);
    $slug = strtolower((string) ($item[2] ?? ''));
    $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));

    if ($slug === '' && $title === '') return false;

    foreach (meza_admin_get_submenu_cleanup_rules() as $rule) {
        if (!is_array($rule) || ($rule['type'] ?? '') !== 'resource') {
            continue;
        }

        if (meza_admin_submenu_cleanup_rule_matches($parent_slug, $slug, $title, $rule)) {
            return true;
        }
    }

    return false;
}

if (!function_exists('meza_admin_submenu_cleanup_rule_matches')) {
    function meza_admin_submenu_cleanup_rule_matches(string $parent_slug, string $slug, string $title, array $rule): bool
    {
        $parent_slug = strtolower(trim($parent_slug));
        $slug = strtolower(trim($slug));
        $title = strtolower(trim($title));

        $parent_slug_equals = meza_admin_normalize_rule_string_list($rule['parent_slug_equals'] ?? []);
        if ($parent_slug_equals !== [] && !in_array($parent_slug, $parent_slug_equals, true)) {
            return false;
        }

        foreach (meza_admin_normalize_rule_string_list($rule['submenu_slug_contains'] ?? []) as $pattern) {
            if (str_contains($slug, $pattern)) {
                return true;
            }
        }

        foreach (meza_admin_normalize_rule_string_list($rule['submenu_title_starts_with'] ?? []) as $pattern) {
            if (str_starts_with($title, $pattern)) {
                return true;
            }
        }

        foreach (meza_admin_normalize_rule_string_list($rule['submenu_title_contains'] ?? []) as $pattern) {
            if (str_contains($title, $pattern)) {
                return true;
            }
        }

        $title_regex = (string) ($rule['submenu_title_regex'] ?? '');
        if ($title_regex !== '' && preg_match($title_regex, $title) === 1) {
            return true;
        }

        $callback = $rule['match_callback'] ?? null;
        if (is_callable($callback)) {
            return (bool) call_user_func($callback, $parent_slug, $slug, $title, $rule);
        }

        return false;
    }
}

if (!function_exists('meza_admin_get_submenu_cleanup_rules')) {
    function meza_admin_get_submenu_cleanup_rules(): array
    {
        $defaults = [
            [
                'type' => 'promotional',
                'submenu_slug_contains' => [
                    'upsell',
                    'cross-sell',
                    'cross_sell',
                ],
            ],
            [
                'type' => 'promotional',
                'submenu_title_starts_with' => [
                    'get ',
                ],
            ],
            [
                'type' => 'promotional',
                'submenu_title_contains' => [
                    ' free plugins',
                    ' more plugins',
                    'upgrade',
                ],
            ],
            [
                'type' => 'resource',
                'submenu_slug_contains' => [
                    'tribe-app-shop',
                    'tec-events-help-hub',
                    'tec-troubleshooting',
                    'first-time-setup',
                    'setup-guide',
                    'shortcode',
                ],
            ],
            [
                'type' => 'resource',
                'parent_slug_equals' => [
                    'edit.php?post_type=tribe_events',
                ],
                'submenu_title_regex' => '/\b(setup|guide|widget|university|education|training|integration|troubleshoot|shortcode)\b/i',
            ],
        ];

        $rules = apply_filters('meza_admin_submenu_cleanup_rules', $defaults);

        return is_array($rules) ? $rules : $defaults;
    }
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

function meza_can_view_settings_tools_submenu_items($user = null): bool
{
    return meza_user_has_any_role($user, ['administrator', meza_site_manager_role_key()]);
}

function meza_should_restrict_settings_tools_submenu_items($user = null): bool
{
    return !meza_can_view_settings_tools_submenu_items($user);
}

function meza_is_site_manager_user($user = null): bool
{
    return meza_user_has_any_role($user, [meza_site_manager_role_key()]);
}

function meza_site_manager_is_utility_admin_request(): bool
{
    if (!is_admin()) {
        return false;
    }

    global $pagenow;

    if (in_array((string) $pagenow, ['tools.php', 'import.php', 'export.php'], true)) {
        return true;
    }

    $request_values = [
        isset($_GET['page']) ? (string) wp_unslash($_GET['page']) : '',
        isset($_GET['tab']) ? (string) wp_unslash($_GET['tab']) : '',
        isset($_GET['post_type']) ? (string) wp_unslash($_GET['post_type']) : '',
        isset($_GET['taxonomy']) ? (string) wp_unslash($_GET['taxonomy']) : '',
        isset($_GET['action']) ? (string) wp_unslash($_GET['action']) : '',
    ];

    foreach ($request_values as $value) {
        $value = strtolower(trim($value));
        if ($value === '') {
            continue;
        }

        if (
            preg_match('/\b(import|export|tools?|settings?|status)\b/i', $value) === 1
            || preg_match('/(^|[_-])(import|export|tools?|settings?|status)([_-]|$)/i', $value) === 1
        ) {
            return true;
        }
    }

    return false;
}

function meza_is_restricted_settings_tools_submenu_item(string $parent_slug, array $item): bool
{
    $parent_slug = strtolower($parent_slug);

    if (meza_is_default_wordpress_submenu_item($parent_slug, $item)) {
        return false;
    }

    if (in_array($parent_slug, ['options-general.php', 'tools.php'], true)) {
        return true;
    }

    $utility_label = meza_get_standardized_submenu_utility_label($item, $parent_slug);

    return in_array($utility_label, ['Import', 'Export', 'Import/Export', 'Tools', 'Settings', 'Status'], true);
}

function meza_should_grant_site_manager_utility_submenu_access(string $parent_slug, array $item): bool
{
    if (!meza_is_site_manager_user(wp_get_current_user())) {
        return false;
    }

    if (meza_is_default_wordpress_submenu_item($parent_slug, $item)) {
        return false;
    }

    $slug = strtolower((string) ($item[2] ?? ''));
    $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));

    $has_import = preg_match('/\bimport\b/i', $title) === 1 || str_contains($slug, 'import');
    $has_export = preg_match('/\bexport\b/i', $title) === 1 || str_contains($slug, 'export');
    $has_tools = preg_match('/\btools?\b/i', $title) === 1
        || preg_match('/(^|[_-])tools?([_-]|$)/i', $slug) === 1;
    $has_settings = preg_match('/\bsettings?\b/i', $title) === 1
        || preg_match('/(^|[_-])settings?([_-]|$)/i', $slug) === 1;
    $has_status = preg_match('/\bstatus\b/i', $title) === 1 || str_contains($slug, 'status');

    return $has_import || $has_export || $has_tools || $has_settings || $has_status;
}

add_filter('user_has_cap', function (array $allcaps, array $caps, array $args, $user): array {
    if (!meza_is_site_manager_user($user)) {
        return $allcaps;
    }

    $requested_caps = array_values(array_unique(array_map(static function ($cap): string {
        return strtolower((string) $cap);
    }, $caps)));

    if ($requested_caps === []) {
        return $allcaps;
    }

    $utility_caps = ['manage_options', 'edit_theme_options', 'import', 'export'];
    $needs_utility_grant = count(array_intersect($requested_caps, $utility_caps)) > 0;

    if (!$needs_utility_grant) {
        return $allcaps;
    }

    if (!doing_action('admin_menu') && !meza_site_manager_is_utility_admin_request()) {
        return $allcaps;
    }

    foreach ($utility_caps as $cap) {
        $allcaps[$cap] = true;
    }

    return $allcaps;
}, 5, 4);

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

function meza_is_sqlite_object_cache_menu_item(string $slug, string $title): bool
{
    return str_contains($slug, 'sqlite-object-cache')
        || str_contains($slug, 'sqlite_object_cache')
        || str_contains($slug, 'sqliteobjectcache')
        || $title === 'sqlite object cache';
}

function meza_get_settings_submenu_priority_labels(): array
{
    return [
        'Contact Information',
        'Business Information',
        'CRM Integration',
        'Page Cache',
        'Object Cache',
        'Image Performance',
        'Post Ordering',
        'Post Duplication',
        'Admin Columns',
        'Admin Menu',
    ];
}

function meza_reorder_settings_submenu_items(array $items): array
{
    $ordered_labels = meza_get_settings_submenu_priority_labels();
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

    if ($privacy_index === null || empty($matched_indexes)) {
        return $items;
    }

    rsort($matched_indexes, SORT_NUMERIC);
    foreach ($matched_indexes as $matched_index) {
        array_splice($items, $matched_index, 1);
        if ($matched_index < $privacy_index) {
            $privacy_index--;
        }
    }

    ksort($ordered_items);
    array_splice($items, $privacy_index + 1, 0, array_values($ordered_items));

    return $items;
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

function meza_post_type_is_visible_in_admin_menu(string $post_type): bool
{
    static $visibility_cache = [];

    $post_type = sanitize_key($post_type);
    if ($post_type === '') {
        return false;
    }

    if (array_key_exists($post_type, $visibility_cache)) {
        return $visibility_cache[$post_type];
    }

    if (in_array($post_type, ['post', 'page'], true)) {
        $visibility_cache[$post_type] = true;
        return true;
    }

    $post_type_object = meza_get_cached_post_type_object($post_type);
    if (!($post_type_object instanceof WP_Post_Type) || empty($post_type_object->show_ui)) {
        $visibility_cache[$post_type] = false;
        return false;
    }

    $visibility_cache[$post_type] = $post_type_object->show_in_menu !== false;

    return $visibility_cache[$post_type];
}

function meza_get_nontrashed_post_type_count(string $post_type): int
{
    $post_type = sanitize_key($post_type);
    if ($post_type === '') {
        return 0;
    }

    return meza_get_nontrashed_post_type_count_cached($post_type);
}

function meza_should_hide_calendar_embeds_submenu_for_current_user(): bool
{
    return meza_admin_should_hide_submenu_item_for_current_user(
        'edit.php?post_type=tribe_events',
        'edit.php?post_type=tribe_calendar_embed'
    );
}

if (!function_exists('meza_admin_get_submenu_visibility_rules')) {
    function meza_admin_get_submenu_visibility_rules(): array
    {
        $defaults = [
            [
                'id' => 'empty-calendar-embeds-for-site-managers',
                'parent_slug' => 'edit.php?post_type=tribe_events',
                'submenu_slug' => 'edit.php?post_type=tribe_calendar_embed',
                'required_roles' => [meza_site_manager_role_key()],
                'excluded_roles' => ['administrator'],
                'hide_callback' => static function (): bool {
                    return meza_get_nontrashed_post_type_count('tribe_calendar_embed') < 1;
                },
            ],
        ];

        $rules = apply_filters('meza_admin_submenu_visibility_rules', $defaults);

        return is_array($rules) ? $rules : $defaults;
    }
}

if (!function_exists('meza_admin_submenu_visibility_rule_matches')) {
    function meza_admin_submenu_visibility_rule_matches(string $parent_slug, string $submenu_slug, array $rule, ?WP_User $user = null): bool
    {
        $parent_slug = strtolower(trim($parent_slug));
        $submenu_slug = strtolower(trim($submenu_slug));

        if ($parent_slug === '' || $submenu_slug === '') {
            return false;
        }

        if ($parent_slug !== strtolower(trim((string) ($rule['parent_slug'] ?? '')))) {
            return false;
        }

        if ($submenu_slug !== strtolower(trim((string) ($rule['submenu_slug'] ?? '')))) {
            return false;
        }

        if (!($user instanceof WP_User)) {
            $user = wp_get_current_user();
        }

        if (!($user instanceof WP_User)) {
            return false;
        }

        $required_roles = meza_admin_normalize_rule_string_list($rule['required_roles'] ?? []);
        if ($required_roles !== [] && count(array_intersect($required_roles, array_map('strtolower', (array) $user->roles))) < 1) {
            return false;
        }

        $excluded_roles = meza_admin_normalize_rule_string_list($rule['excluded_roles'] ?? []);
        if ($excluded_roles !== [] && count(array_intersect($excluded_roles, array_map('strtolower', (array) $user->roles))) > 0) {
            return false;
        }

        $hide_callback = $rule['hide_callback'] ?? null;
        if (is_callable($hide_callback)) {
            return (bool) call_user_func($hide_callback, $parent_slug, $submenu_slug, $user, $rule);
        }

        return false;
    }
}

if (!function_exists('meza_admin_should_hide_submenu_item_for_current_user')) {
    function meza_admin_should_hide_submenu_item_for_current_user(string $parent_slug, string $submenu_slug, $user = null): bool
    {
        foreach (meza_admin_get_submenu_visibility_rules() as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            if (meza_admin_submenu_visibility_rule_matches($parent_slug, $submenu_slug, $rule, $user instanceof WP_User ? $user : null)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('meza_apply_dynamic_submenu_visibility_rules')) {
    function meza_apply_dynamic_submenu_visibility_rules(): void
    {
        global $submenu;

        if (!is_array($submenu)) {
            return;
        }

        foreach (meza_admin_get_submenu_visibility_rules() as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $parent_slug = (string) ($rule['parent_slug'] ?? '');
            $submenu_slug = (string) ($rule['submenu_slug'] ?? '');
            if ($parent_slug === '' || $submenu_slug === '') {
                continue;
            }

            if (!meza_admin_should_hide_submenu_item_for_current_user($parent_slug, $submenu_slug)) {
                continue;
            }

            remove_submenu_page($parent_slug, $submenu_slug);

            if (!isset($submenu[$parent_slug]) || !is_array($submenu[$parent_slug])) {
                continue;
            }

            $normalized_submenu_slug = strtolower($submenu_slug);
            $submenu[$parent_slug] = array_values(array_filter($submenu[$parent_slug], static function ($item) use ($normalized_submenu_slug): bool {
                if (!is_array($item)) {
                    return false;
                }

                return strtolower((string) ($item[2] ?? '')) !== $normalized_submenu_slug;
            }));
        }
    }
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
    $should_restrict_settings_tools_submenu_items = meza_should_restrict_settings_tools_submenu_items();
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

            if (meza_admin_should_hide_submenu_item_for_current_user((string) $parent_slug, $slug)) {
                unset($items[$index]);
                continue;
            }

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

            if (meza_should_grant_site_manager_utility_submenu_access((string) $parent_slug, $item)) {
                $item[1] = 'read';
            }

            if (
                $should_restrict_settings_tools_submenu_items
                && meza_is_restricted_settings_tools_submenu_item((string) $parent_slug, $item)
            ) {
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

            if ($parent_slug !== 'options-general.php' && $title === 'general') {
                $item[0] = 'Dashboard';
                if (isset($item[3])) $item[3] = 'Dashboard';
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
            $is_sqlite_object_cache = meza_is_sqlite_object_cache_menu_item($slug, $title);
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

            if ($parent_slug === 'options-general.php' && $is_sqlite_object_cache) {
                $item[0] = 'Object Cache';
                if (isset($item[3])) $item[3] = 'Object Cache';
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
            $items = meza_reorder_settings_submenu_items($items);
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

    $grouped_items = [];

    foreach ($with_permalink as $entry) {
        $grouped_items[] = $entry['item'];
    }

    if (!empty($with_permalink) && (!empty($media_items) || !empty($without_permalink))) {
        $grouped_items[] = [
            '',
            'read',
            'separator-meza-content-media',
            '',
            'wp-menu-separator',
        ];
    }

    if (!empty($media_items)) {
        foreach ($media_items as $media_item) {
            $grouped_items[] = $media_item;
        }
    }

    if ((!empty($with_permalink) || !empty($media_items)) && !empty($without_permalink)) {
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

// Group top-level content menus after Dashboard: permalink-capable first, then Media, then non-viewable/admin-only.
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

// Keep WooCommerce-related admin menu patches together instead of injecting them in separate head callbacks.
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

// Normalize last submenu links when plugins leave internal admin links marked as external/promo items.
add_action('admin_head', function (): void {
    if (!is_admin()) {
        return;
    }
?>
    <style id="meza-normalize-last-submenu-link-style">
        #adminmenu .wp-submenu li:last-child a.meza-normalized-submenu-link,
        #adminmenu .wp-submenu li:last-child a.meza-normalized-submenu-link:visited {
            background: transparent !important;
            color: #c3c4c7 !important;
            font-weight: 400 !important;
            white-space: normal !important;
        }

        #adminmenu .wp-submenu li:last-child a.meza-normalized-submenu-link:hover,
        #adminmenu .wp-submenu li:last-child a.meza-normalized-submenu-link:focus {
            background: transparent !important;
            color: #72aee6 !important;
        }
    </style>
    <script id="meza-normalize-last-submenu-links">
        (() => {
            const isInternalAdminUrl = (href) => {
                if (!href) return false;

                try {
                    const url = new URL(href, window.location.origin);
                    if (url.origin !== window.location.origin) return false;

                    return url.pathname.includes('/wp-admin/')
                        || url.searchParams.has('page')
                        || url.searchParams.has('post_type');
                } catch (error) {
                    return false;
                }
            };

            const normalizeLastSubmenuLinks = () => {
                document.querySelectorAll('#adminmenu .wp-submenu li:last-child a').forEach((link) => {
                    if (!(link instanceof HTMLAnchorElement)) return;

                    const href = String(link.getAttribute('href') || '');
                    if (!isInternalAdminUrl(href)) return;

                    const hadExternalAttrs = link.hasAttribute('target') || link.hasAttribute('rel');

                    link.removeAttribute('target');
                    link.removeAttribute('rel');

                    if (hadExternalAttrs) {
                        link.classList.add('meza-normalized-submenu-link');
                    }
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', normalizeLastSubmenuLinks, { once: true });
            } else {
                normalizeLastSubmenuLinks();
            }
        })();
    </script>
<?php
}, 1004);

if (!function_exists('meza_is_locked_admin_chrome_screen')) {
    function meza_admin_get_locked_admin_chrome_rules(): array
    {
        $defaults = [
            [
                'id' => 'events-editorial-chrome',
                'post_types' => ['tribe_events'],
                'screen_bases' => ['edit', 'post'],
                'hide_selectors' => ['.ian-client', '.ian-sidebar'],
                'unwrap_selectors' => ['.ian-header', '.ian-inner-wrapper'],
                'dequeue_styles' => ['ian-client-css'],
                'dequeue_scripts' => ['ian-client-js'],
                'disable_tec_common_icon' => true,
            ],
        ];

        $rules = apply_filters('meza_admin_locked_admin_chrome_rules', $defaults);

        return is_array($rules) ? $rules : $defaults;
    }

    function meza_admin_locked_admin_chrome_rule_matches_screen(array $rule, ?WP_Screen $screen = null): bool
    {
        if (!($screen instanceof WP_Screen)) {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        }

        if (!($screen instanceof WP_Screen)) {
            return false;
        }

        $post_types = meza_admin_normalize_rule_string_list($rule['post_types'] ?? []);
        if ($post_types !== [] && !in_array(strtolower((string) ($screen->post_type ?? '')), $post_types, true)) {
            return false;
        }

        $screen_bases = meza_admin_normalize_rule_string_list($rule['screen_bases'] ?? []);
        if ($screen_bases !== [] && !in_array(strtolower((string) $screen->base), $screen_bases, true)) {
            return false;
        }

        $match_callback = $rule['match_callback'] ?? null;
        if (is_callable($match_callback)) {
            return (bool) call_user_func($match_callback, $screen, $rule);
        }

        return true;
    }

    function meza_admin_get_current_locked_admin_chrome_rule(?WP_Screen $screen = null): ?array
    {
        foreach (meza_admin_get_locked_admin_chrome_rules() as $rule) {
            if (!is_array($rule) || !meza_admin_locked_admin_chrome_rule_matches_screen($rule, $screen)) {
                continue;
            }

            return $rule;
        }

        return null;
    }

    function meza_admin_get_locked_admin_chrome_scope_selectors(array $rule, ?WP_Screen $screen = null): array
    {
        if (!($screen instanceof WP_Screen)) {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        }

        $custom_scopes = meza_admin_normalize_rule_string_list($rule['body_scopes'] ?? []);
        if ($custom_scopes !== []) {
            return $custom_scopes;
        }

        if (!($screen instanceof WP_Screen)) {
            return [];
        }

        $post_type = sanitize_html_class((string) ($screen->post_type ?? ''));
        if ($post_type === '') {
            return [];
        }

        $selectors = [];
        $base = strtolower((string) $screen->base);

        if ($base === 'edit') {
            $selectors[] = '.edit-php.post-type-' . $post_type;
        }

        if ($base === 'post') {
            $selectors[] = '.post-php.post-type-' . $post_type;
            $selectors[] = '.post-new-php.post-type-' . $post_type;
        }

        if ($selectors === []) {
            $selectors[] = '.post-type-' . $post_type;
        }

        return array_values(array_unique($selectors));
    }

    function meza_is_locked_admin_chrome_screen(): bool
    {
        if (!is_admin() || !function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (!($screen instanceof WP_Screen)) {
            return false;
        }

        return meza_admin_get_current_locked_admin_chrome_rule($screen) !== null;
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
    function meza_is_preferred_plugin_admin_screen(?WP_Screen $screen = null): bool
    {
        $signatures = [];

        if (function_exists('mz_plugins_get_env_plugin_signatures')) {
            $signatures = array_values(array_filter(array_map('strtolower', mz_plugins_get_env_plugin_signatures()), static function ($signature): bool {
                return is_string($signature) && $signature !== '';
            }));
        }

        // WP Super Cache needs to keep its native screen wrappers and injected UI intact.
        $signatures = array_values(array_unique(array_merge($signatures, [
            'wp-super-cache',
            'wpsupercache',
            'wp super cache',
        ])));

        if ($signatures === []) {
            return false;
        }

        if (!($screen instanceof WP_Screen) && function_exists('get_current_screen')) {
            $screen = get_current_screen();
        }

        $haystacks = [];

        if ($screen instanceof WP_Screen) {
            $haystacks[] = strtolower((string) ($screen->id ?? ''));
            $haystacks[] = strtolower((string) ($screen->base ?? ''));
            $haystacks[] = strtolower((string) ($screen->parent_base ?? ''));
            $haystacks[] = strtolower((string) ($screen->parent_file ?? ''));
        }

        $haystacks[] = strtolower((string) ($_GET['page'] ?? ''));
        $haystacks[] = strtolower((string) ($_GET['plugin_status'] ?? ''));
        $haystacks[] = strtolower((string) ($_SERVER['REQUEST_URI'] ?? ''));
        $haystacks[] = strtolower((string) ($_SERVER['PHP_SELF'] ?? ''));

        $haystacks = array_values(array_filter(array_map('trim', $haystacks), static function (string $value): bool {
            return $value !== '';
        }));

        foreach ($haystacks as $haystack) {
            foreach ($signatures as $signature) {
                if ($signature !== '' && str_contains($haystack, $signature)) {
                    return true;
                }
            }
        }

        return false;
    }

    function meza_is_admin_chrome_exempt_screen(): bool
    {
        if (!is_admin()) {
            return false;
        }

        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen instanceof WP_Screen) {
                if ((string) $screen->id === 'edit-page') {
                    return true;
                }

                if ((string) $screen->base === 'themes') {
                    return true;
                }

                if (meza_is_preferred_plugin_admin_screen($screen)) {
                    return true;
                }
            }
        }

        $php_self = isset($_SERVER['PHP_SELF']) ? basename((string) $_SERVER['PHP_SELF']) : '';

        if ($php_self === 'themes.php') {
            return true;
        }

        return meza_is_preferred_plugin_admin_screen();
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

if (!function_exists('meza_admin_wpbody_content_pre_wrap_allowed_selectors')) {
    function meza_admin_wpbody_content_pre_wrap_allowed_selectors(): array
    {
        return [
            '#screen-meta',
            '#screen-meta-links',
            '.notice',
            '.update-nag',
            '.updated',
            '.error',
            'script',
            '.clear',
            '[data-meza-admin-chrome]',
            '.meza-admin-chrome',
        ];
    }
}

if (!function_exists('meza_admin_notice_selector_matches')) {
    function meza_admin_notice_selector_matches(DOMElement $element): bool
    {
        $class_names = preg_split('/\s+/', strtolower(trim((string) $element->getAttribute('class')))) ?: [];
        $class_lookup = array_fill_keys(array_filter($class_names), true);

        if (isset($class_lookup['notice']) || isset($class_lookup['update-nag']) || isset($class_lookup['updated']) || isset($class_lookup['error'])) {
            return true;
        }

        return false;
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
    function meza_admin_is_allowed_after_wpwrap_element(DOMElement $element, array $allowed_selectors, array $preferred_plugin_signatures = []): bool
    {
        if (meza_admin_dom_element_matches_any_selector($element, $allowed_selectors)) {
            return true;
        }

        if (meza_admin_dom_element_matches_plugin_signatures($element, $preferred_plugin_signatures)) {
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

if (!function_exists('meza_admin_dom_wrap_element')) {
    function meza_admin_dom_wrap_element(DOMDocument $document, DOMElement $element, string $tag_name, array $attributes = []): ?DOMElement
    {
        $parent = $element->parentNode;
        if (!($parent instanceof DOMNode)) {
            return null;
        }

        $wrapper = $document->createElement($tag_name);

        foreach ($attributes as $attribute_name => $attribute_value) {
            $wrapper->setAttribute((string) $attribute_name, (string) $attribute_value);
        }

        $parent->insertBefore($wrapper, $element);
        $wrapper->appendChild($element);

        return $wrapper;
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
        $wpbody_content_pre_wrap_selectors = meza_admin_wpbody_content_pre_wrap_allowed_selectors();
        $wpbody_content_trailing_selectors = meza_admin_wpbody_content_trailing_allowed_selectors();
        $preferred_plugin_signatures = meza_admin_get_env_preferred_plugin_signatures();
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

        foreach ($xpath->query('//form[@id="posts-filter"]//table[contains(concat(" ", normalize-space(@class), " "), " wp-list-table ")]') ?: [] as $table) {
            if (!($table instanceof DOMElement)) {
                continue;
            }

            $parent = $table->parentNode;
            if ($parent instanceof DOMElement && meza_admin_dom_element_has_class($parent, 'meza-admin-table-scroll')) {
                continue;
            }

            meza_admin_dom_wrap_element($document, $table, 'div', [
                'class' => 'meza-admin-table-scroll',
                'style' => 'display:block;width:100%;max-width:100%;max-height:calc(100vh - 260px);overflow:auto;-webkit-overflow-scrolling:touch;',
            ]);
        }

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
                if (!$seen_main_wrap) {
                    if (meza_admin_dom_element_has_class($child, 'wrap')) {
                        $seen_main_wrap = true;
                        continue;
                    }

                    if (meza_admin_dom_element_matches_any_selector($child, $wpbody_content_pre_wrap_selectors)) {
                        continue;
                    }

                    if (meza_admin_dom_element_matches_plugin_signatures($child, $preferred_plugin_signatures)) {
                        continue;
                    }

                    if (
                        !meza_admin_dom_element_matches_any_selector($child, ['[data-meza-admin-chrome]', '.meza-admin-chrome'])
                        && meza_admin_dom_element_matches_plugin_signatures($child, $disallowed_plugin_signatures)
                    ) {
                        $wpbody_content->removeChild($child);
                        continue;
                    }

                    if ($child->parentNode instanceof DOMNode) {
                        $child->parentNode->removeChild($child);
                    }

                    continue;
                }

                if (meza_admin_dom_element_has_class($child, 'wrap')) {
                    $seen_main_wrap = true;
                    continue;
                }

                if (meza_admin_is_allowed_after_wpwrap_element($child, $wpbody_content_trailing_selectors, $preferred_plugin_signatures)) {
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

                if (!meza_admin_is_allowed_after_wpwrap_element($child, $wpwrap_selectors, $preferred_plugin_signatures)
                    && meza_admin_dom_element_matches_plugin_signatures($child, $disallowed_plugin_signatures)) {
                    $body->removeChild($child);
                    continue;
                }

                if (!meza_admin_is_allowed_after_wpwrap_element($child, $wpwrap_selectors, $preferred_plugin_signatures)
                    && strtolower($child->tagName) !== 'script') {
                    $body->removeChild($child);
                    continue;
                }

                if (strtolower($child->tagName) === 'script'
                    && !meza_admin_is_allowed_after_wpwrap_element($child, $wpwrap_selectors, $preferred_plugin_signatures)
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

        foreach ($xpath->query('//*') ?: [] as $node) {
            if (!($node instanceof DOMElement)) {
                continue;
            }

            if (!meza_admin_notice_selector_matches($node)) {
                continue;
            }

            if (meza_admin_dom_element_matches_any_selector($node, ['[data-meza-admin-chrome]', '.meza-admin-chrome'])) {
                continue;
            }

            if (!meza_admin_dom_element_matches_plugin_signatures($node, $disallowed_plugin_signatures)) {
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
    $rule = meza_admin_get_current_locked_admin_chrome_rule();
    if (is_array($rule) && !empty($rule['disable_tec_common_icon'])) {
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
    $rule = meza_admin_get_current_locked_admin_chrome_rule();
    if (!is_array($rule)) {
        return;
    }

    foreach (meza_admin_normalize_rule_string_list($rule['dequeue_styles'] ?? []) as $handle) {
        wp_dequeue_style($handle);
    }

    foreach (meza_admin_normalize_rule_string_list($rule['dequeue_scripts'] ?? []) as $handle) {
        wp_dequeue_script($handle);
    }
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
        $is_admin_chrome_exempt_screen = meza_is_admin_chrome_exempt_screen();
        $lock_core_admin_content = !$is_admin_chrome_exempt_screen;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $is_edit_screen = $screen instanceof WP_Screen && $screen->base === 'edit';
        $content_selectors = meza_admin_content_allowed_children_selectors();
        $wpwrap_selectors = meza_admin_wpwrap_allowed_selectors();
        $wpbodyContentPreWrapSelectors = meza_admin_wpbody_content_pre_wrap_allowed_selectors();
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
            const isAdminChromeExemptScreen = <?php echo wp_json_encode($is_admin_chrome_exempt_screen); ?>;
            const lockCoreAdminContent = <?php echo wp_json_encode($lock_core_admin_content); ?>;
            const isEditScreen = <?php echo wp_json_encode($is_edit_screen); ?>;
            const allowedSelectors = <?php echo wp_json_encode($content_selectors); ?>;
            const wpwrapAllowedSelectors = <?php echo wp_json_encode($wpwrap_selectors); ?>;
            const wpbodyContentPreWrapSelectors = <?php echo wp_json_encode($wpbodyContentPreWrapSelectors); ?>;
            const wpbodyContentTrailingSelectors = <?php echo wp_json_encode($wpbodyContentTrailingSelectors); ?>;
            const preferredPluginSignatures = <?php echo wp_json_encode(meza_admin_get_env_preferred_plugin_signatures()); ?>;
            const disallowedPluginSignatures = <?php echo wp_json_encode($disallowed_plugin_signatures); ?>;
            const protectedSpacingTargets = [
                document.documentElement,
                document.body,
                document.getElementById('wpwrap'),
                document.getElementById('wpcontent'),
                document.getElementById('wpbody'),
                document.getElementById('wpbody-content'),
            ].filter((element) => element && typeof element.nodeType === 'number');

            const isAllowed = (element) => {
                if (!(element instanceof Element)) return false;
                return allowedSelectors.some((selector) => element.matches(selector));
            };

            const isAllowedAfterWpwrap = (element) => {
                if (!(element instanceof Element)) return false;
                return wpwrapAllowedSelectors.some((selector) => element.matches(selector));
            };

            const isAllowedBeforeMainWrap = (element) => {
                if (!(element instanceof Element)) return false;
                return wpbodyContentPreWrapSelectors.some((selector) => element.matches(selector));
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
                if (matchesPluginSignatures(element, preferredPluginSignatures)) return true;
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
                if (matchesPluginSignatures(element, preferredPluginSignatures)) return true;
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

                    if (!seenMainWrap) {
                        if (child.classList.contains('wrap')) {
                            seenMainWrap = true;
                            return;
                        }

                        if (isAllowedBeforeMainWrap(child)) {
                            return;
                        }

                        if (matchesPluginSignatures(child, preferredPluginSignatures)) {
                            return;
                        }

                        if (
                            !child.matches('[data-meza-admin-chrome], .meza-admin-chrome')
                            && matchesPluginSignatures(child, disallowedPluginSignatures)
                        ) {
                            child.remove();
                            return;
                        }

                        child.remove();
                        return;
                    }

                    if (child.classList.contains('wrap')) {
                        seenMainWrap = true;
                        return;
                    }
                    if (matchesPluginSignatures(child, preferredPluginSignatures)) return;
                    if (isAllowedAfterWpwrapElementForSelectors(child, wpbodyContentTrailingSelectors)) return;

                    child.remove();
                });
            };

            const cleanupPluginNotices = () => {
                document.querySelectorAll('.notice, .update-nag, .updated, .error, .pum-alerts').forEach((node) => {
                    if (!(node instanceof HTMLElement)) return;
                    if (node.matches('[data-meza-admin-chrome], .meza-admin-chrome')) return;
                    if (!matchesPluginSignatures(node, disallowedPluginSignatures)) return;
                    node.remove();
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

            if (isAdminChromeExemptScreen) {
                cleanupInjectedTopSpacing();
                window.addEventListener('resize', cleanupInjectedTopSpacing);
                return;
            }

            cleanupContent();
            cleanupAfterWpwrap();
            cleanupAfterMainWrap();
            cleanupPluginNotices();
            cleanupInjectedTopSpacing();

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', cleanupContent, { once: true });
                document.addEventListener('DOMContentLoaded', cleanupAfterWpwrap, { once: true });
                document.addEventListener('DOMContentLoaded', cleanupAfterMainWrap, { once: true });
                document.addEventListener('DOMContentLoaded', cleanupPluginNotices, { once: true });
                document.addEventListener('DOMContentLoaded', cleanupInjectedTopSpacing, { once: true });
            } else {
                window.addEventListener('load', cleanupContent, { once: true });
                window.addEventListener('load', cleanupAfterWpwrap, { once: true });
                window.addEventListener('load', cleanupAfterMainWrap, { once: true });
                window.addEventListener('load', cleanupPluginNotices, { once: true });
                window.addEventListener('load', cleanupInjectedTopSpacing, { once: true });
            }

            if (!isEditScreen) {
                const content = document.getElementById('wpcontent');
                if (content instanceof HTMLElement) {
                    const observer = new MutationObserver(() => {
                        cleanupContent();
                        cleanupAfterWpwrap();
                        cleanupAfterMainWrap();
                        cleanupPluginNotices();
                        cleanupInjectedTopSpacing();
                    });
                    observer.observe(content, { childList: true });
                }

                const bodyContent = document.getElementById('wpbody-content');
                if (bodyContent instanceof HTMLElement) {
                    const bodyContentObserver = new MutationObserver(() => {
                        cleanupAfterMainWrap();
                        cleanupPluginNotices();
                    });
                    bodyContentObserver.observe(bodyContent, { childList: true });
                }

                protectedSpacingTargets.forEach((element) => {
                    if (!element || typeof element.nodeType !== 'number') return;

                    const observer = new MutationObserver(cleanupInjectedTopSpacing);
                    observer.observe(element, { attributes: true, attributeFilter: ['style', 'class'] });
                });

                if (document.body instanceof HTMLElement) {
                    const bodyObserver = new MutationObserver(() => {
                        cleanupAfterWpwrap();
                        cleanupAfterMainWrap();
                        cleanupPluginNotices();
                        cleanupInjectedTopSpacing();
                    });
                    bodyObserver.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class'] });
                }
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

            if (document.body instanceof HTMLBodyElement) {
                const observer = new MutationObserver(scheduleCleanup);
                observer.observe(document.body, { childList: true, subtree: true });
            }
        })();
    </script>
<?php
    }

    $locked_admin_chrome_rule = meza_admin_get_current_locked_admin_chrome_rule();
    if (!is_array($locked_admin_chrome_rule)) {
        return;
    }

    $locked_admin_chrome_scope_selectors = meza_admin_get_locked_admin_chrome_scope_selectors($locked_admin_chrome_rule);
    $locked_admin_chrome_hide_selectors = meza_admin_normalize_rule_string_list($locked_admin_chrome_rule['hide_selectors'] ?? []);
    $locked_admin_chrome_unwrap_selectors = meza_admin_normalize_rule_string_list($locked_admin_chrome_rule['unwrap_selectors'] ?? []);

    $hide_rules = [];
    foreach ($locked_admin_chrome_scope_selectors as $scope_selector) {
        foreach ($locked_admin_chrome_hide_selectors as $selector) {
            $hide_rules[] = $scope_selector . ' ' . $selector;
        }
    }

    $unwrap_rules = [];
    foreach ($locked_admin_chrome_scope_selectors as $scope_selector) {
        foreach ($locked_admin_chrome_unwrap_selectors as $selector) {
            $unwrap_rules[] = $scope_selector . ' ' . $selector;
        }
    }

    if ($hide_rules === [] && $unwrap_rules === []) {
        return;
    }
?>
    <style id="meza-lock-admin-chrome">
        <?php if ($hide_rules !== []) : ?>
        <?php echo implode(",\n        ", $hide_rules); ?> {
            display: none !important;
        }
        <?php endif; ?>

        <?php if ($unwrap_rules !== []) : ?>
        <?php echo implode(",\n        ", $unwrap_rules); ?> {
            all: unset !important;
            display: contents !important;
        }
        <?php endif; ?>
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
        $normalized_top_level_label = strtolower($top_level_label);
        if ($parent_slug === '' || $top_level_label === '') continue;

        if (meza_is_sqlite_object_cache_menu_item(strtolower($parent_slug), $normalized_top_level_label)) {
            $top_level_label = 'Object Cache';
        }

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
        $submenu['options-general.php'] = meza_reorder_settings_submenu_items(array_values($submenu['options-general.php']));
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

function meza_prune_empty_top_level_admin_menu_groups(): void
{
    global $menu, $submenu;

    if (!is_array($menu) || empty($menu)) {
        return;
    }

    $protected_top_level_slugs = [
        'index.php',
        'profile.php',
    ];

    $top_level_requires_accessible_children = static function (array $item): bool {
        $slug = strtolower((string) ($item[2] ?? ''));

        if (in_array($slug, ['tools.php', 'options-general.php'], true)) {
            return true;
        }

        $utility_label = meza_get_standardized_submenu_utility_label($item);

        return in_array($utility_label, ['Tools', 'Settings'], true);
    };

    $has_real_accessible_child_items = static function (string $parent_slug, array $submenu_items): bool {
        $normalized_parent_slug = strtolower($parent_slug);

        foreach ($submenu_items as $submenu_item) {
            if (!is_array($submenu_item)) {
                continue;
            }

            $submenu_slug = strtolower((string) ($submenu_item[2] ?? ''));
            if ($submenu_slug === '' || $submenu_slug === $normalized_parent_slug) {
                continue;
            }

            if (meza_is_restricted_settings_tools_submenu_item($normalized_parent_slug, $submenu_item)) {
                continue;
            }

            return true;
        }

        return false;
    };

    $filtered_menu = [];

    foreach ($menu as $item) {
        if (!is_array($item)) {
            $filtered_menu[] = $item;
            continue;
        }

        $slug = (string) ($item[2] ?? '');
        if ($slug === '' || in_array($slug, $protected_top_level_slugs, true)) {
            $filtered_menu[] = $item;
            continue;
        }

        if (!array_key_exists($slug, (array) $submenu)) {
            if ($top_level_requires_accessible_children($item)) {
                continue;
            }

            $filtered_menu[] = $item;
            continue;
        }

        $submenu_items = array_values(array_filter((array) ($submenu[$slug] ?? []), static function ($submenu_item): bool {
            return is_array($submenu_item);
        }));

        $has_real_child_items = $has_real_accessible_child_items($slug, $submenu_items);

        if ($submenu_items === [] || (!$has_real_child_items && $top_level_requires_accessible_children($item))) {
            unset($submenu[$slug]);
            continue;
        }

        $filtered_menu[] = $item;
    }

    $menu = $filtered_menu;
}

add_action('admin_menu', 'meza_prune_empty_top_level_admin_menu_groups', PHP_INT_MAX - 1);

function meza_restore_settings_utility_group_separator(): void
{
    global $menu;

    if (!is_array($menu) || empty($menu)) {
        return;
    }

    $is_separator = static function ($item): bool {
        if (!is_array($item)) {
            return false;
        }

        $slug = strtolower((string) ($item[2] ?? ''));
        $classes = strtolower((string) ($item[4] ?? ''));

        return str_starts_with($slug, 'separator') || str_contains($classes, 'wp-menu-separator');
    };

    $first_utility_index = null;

    foreach ($menu as $index => $item) {
        if (!is_array($item) || !meza_is_settings_utility_menu_item($item)) {
            continue;
        }

        $first_utility_index = (int) $index;
        break;
    }

    if ($first_utility_index === null || $first_utility_index <= 0) {
        return;
    }

    $previous_item = $menu[$first_utility_index - 1] ?? null;
    if ($is_separator($previous_item)) {
        return;
    }

    array_splice($menu, $first_utility_index, 0, [[
        '',
        'read',
        'separator-meza-settings-utilities',
        '',
        'wp-menu-separator',
    ]]);
}

add_action('admin_menu', 'meza_restore_settings_utility_group_separator', PHP_INT_MAX - 1);

function meza_restore_default_fallback_group_separator(): void
{
    global $menu;

    if (!is_array($menu) || empty($menu)) {
        return;
    }

    $is_separator = static function ($item): bool {
        if (!is_array($item)) {
            return false;
        }

        $slug = strtolower((string) ($item[2] ?? ''));
        $classes = strtolower((string) ($item[4] ?? ''));

        return str_starts_with($slug, 'separator') || str_contains($classes, 'wp-menu-separator');
    };

    $is_preferred_admin_group_item = static function ($item): bool {
        if (!is_array($item)) {
            return false;
        }

        $slug = strtolower((string) ($item[2] ?? ''));

        if (in_array($slug, ['themes.php', 'users.php', 'tools.php', 'options-general.php'], true)) {
            return true;
        }

        return meza_is_settings_utility_menu_item($item);
    };

    foreach ($menu as $item) {
        if (!is_array($item)) {
            continue;
        }

        if (((string) ($item[2] ?? '')) === 'separator-meza-default-fallback') {
            return;
        }
    }

    $group_start = null;

    foreach ($menu as $index => $item) {
        if (!$is_preferred_admin_group_item($item)) {
            continue;
        }

        $group_start = (int) $index;
        break;
    }

    if ($group_start === null) {
        return;
    }

    $insert_index = null;

    for ($i = $group_start, $count = count($menu); $i < $count; $i++) {
        $item = $menu[$i] ?? null;

        if (!is_array($item)) {
            continue;
        }

        if ($is_separator($item)) {
            continue;
        }

        if ($is_preferred_admin_group_item($item)) {
            $insert_index = $i + 1;
            continue;
        }

        break;
    }

    if ($insert_index === null) {
        return;
    }

    $next_item = $menu[$insert_index] ?? null;
    if (!is_array($next_item)) {
        return;
    }

    if ($is_separator($next_item) || $is_preferred_admin_group_item($next_item)) {
        return;
    }

    array_splice($menu, $insert_index, 0, [[
        '',
        'read',
        'separator-meza-default-fallback',
        '',
        'wp-menu-separator',
    ]]);
}

add_action('admin_menu', 'meza_restore_default_fallback_group_separator', PHP_INT_MAX - 1);
// Final top-level menu cleanup pass.
add_action('admin_menu', 'meza_cleanup_menu_separators', PHP_INT_MAX);

function meza_enforce_restricted_top_level_utility_menus(): void
{
    global $menu, $submenu;

    if (!meza_should_restrict_settings_tools_submenu_items()) {
        return;
    }

    if (function_exists('remove_menu_page')) {
        remove_menu_page('tools.php');
    }

    if (is_array($menu)) {
        $menu = array_values(array_filter($menu, static function ($item): bool {
            if (!is_array($item)) {
                return true;
            }

            return strtolower((string) ($item[2] ?? '')) !== 'tools.php';
        }));
    }

    if (is_array($submenu) && isset($submenu['tools.php'])) {
        unset($submenu['tools.php']);
    }
}

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

function meza_hide_empty_calendar_embeds_submenu_for_site_managers(): void
{
    meza_apply_dynamic_submenu_visibility_rules();
}
add_action('admin_menu', 'meza_hide_empty_calendar_embeds_submenu_for_site_managers', PHP_INT_MAX - 1);

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

function meza_apply_late_admin_menu_mutations(): void
{
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
    meza_apply_tail_admin_menu_mutations();
    meza_filter_disabled_tag_taxonomy_submenus();
    meza_enforce_restricted_top_level_utility_menus();
    meza_prune_empty_top_level_admin_menu_groups();
    meza_restore_settings_utility_group_separator();
    meza_restore_default_fallback_group_separator();
    meza_cleanup_menu_separators();
}

function meza_get_admin_menu_editor_property(object $instance, string $property)
{
    try {
        $reflection = new ReflectionProperty($instance, $property);
        $reflection->setAccessible(true);
        return $reflection->getValue($instance);
    } catch (ReflectionException $exception) {
        return null;
    }
}

function meza_set_admin_menu_editor_property(object $instance, string $property, $value): bool
{
    try {
        $reflection = new ReflectionProperty($instance, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($instance, $value);
        return true;
    } catch (ReflectionException $exception) {
        return false;
    }
}

function meza_call_admin_menu_editor_method(object $instance, string $method, array $args = [])
{
    try {
        $reflection = new ReflectionMethod($instance, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($instance, $args);
    } catch (ReflectionException $exception) {
        return null;
    }
}

function meza_build_admin_menu_editor_default_snapshot(array $default_menu, array $default_submenu): array
{
    global $menu, $submenu, $meza_collapsed_settings_menu_map;

    $live_menu = $menu;
    $live_submenu = $submenu;
    $live_collapsed_settings_map = $meza_collapsed_settings_menu_map ?? null;

    $menu = $default_menu;
    $submenu = $default_submenu;
    meza_set_collapsed_settings_menu_map([]);
    meza_apply_late_admin_menu_mutations();

    $snapshot = [
        'menu' => is_array($menu) ? array_values($menu) : [],
        'submenu' => is_array($submenu) ? $submenu : [],
    ];

    $menu = $live_menu;
    $submenu = $live_submenu;

    if (is_array($live_collapsed_settings_map)) {
        $meza_collapsed_settings_menu_map = $live_collapsed_settings_map;
    } else {
        unset($meza_collapsed_settings_menu_map);
    }

    return $snapshot;
}

function meza_build_admin_menu_editor_config(array $menu, array $submenu, array $blacklist = []): ?array
{
    if (!class_exists('ameMenu', false)) {
        return null;
    }

    try {
        $tree = ameMenu::wp2tree($menu, $submenu, $blacklist);
        return ameMenu::load_array($tree);
    } catch (Throwable $exception) {
        return null;
    }
}

// Admin Menu Editor snapshots the default menu before our late mutations run,
// so rebuild its internal defaults from the same finalized menu pipeline.
function meza_sync_admin_menu_editor_default_menu_snapshot(): void
{
    if (!is_admin() || !class_exists('WPMenuEditor', false)) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
    if ($page !== 'menu_editor') {
        return;
    }

    global $wp_menu_editor;

    if (!($wp_menu_editor instanceof WPMenuEditor)) {
        return;
    }

    $default_menu = meza_get_admin_menu_editor_property($wp_menu_editor, 'default_wp_menu');
    $default_submenu = meza_get_admin_menu_editor_property($wp_menu_editor, 'default_wp_submenu');

    if (!is_array($default_menu) || !is_array($default_submenu)) {
        return;
    }

    $snapshot = meza_build_admin_menu_editor_default_snapshot($default_menu, $default_submenu);

    meza_set_admin_menu_editor_property($wp_menu_editor, 'default_wp_menu', $snapshot['menu']);
    meza_set_admin_menu_editor_property($wp_menu_editor, 'default_wp_submenu', $snapshot['submenu']);

    $menu_url_blacklist = meza_call_admin_menu_editor_method($wp_menu_editor, 'get_menu_url_black_list');
    if (!is_array($menu_url_blacklist)) {
        $menu_url_blacklist = [];
    }

    $default_config = meza_build_admin_menu_editor_config($snapshot['menu'], $snapshot['submenu'], $menu_url_blacklist);

    if (class_exists('ameMenuTemplateBuilder', false)) {
        $template_builder = new ameMenuTemplateBuilder();
        $item_templates = $template_builder->build($snapshot['menu'], $snapshot['submenu'], $menu_url_blacklist);
        $item_templates_with_specials = meza_call_admin_menu_editor_method(
            $wp_menu_editor,
            'add_special_templates',
            [$item_templates]
        );

        if (is_array($item_templates_with_specials)) {
            $item_templates = $item_templates_with_specials;
        }

        meza_set_admin_menu_editor_property($wp_menu_editor, 'item_templates', $item_templates);
        meza_set_admin_menu_editor_property(
            $wp_menu_editor,
            'relative_template_order',
            $template_builder->getRelativeTemplateOrder()
        );
    }

    $custom_wp_menu = meza_get_admin_menu_editor_property($wp_menu_editor, 'custom_wp_menu');
    if (!is_array($custom_wp_menu) || empty($custom_wp_menu)) {
        meza_set_admin_menu_editor_property($wp_menu_editor, 'merged_custom_menu', $default_config);
        return;
    }

    global $menu, $submenu;

    $original_menu = $menu;
    $original_submenu = $submenu;

    $wp_menu_editor->replace_wp_menu('');
    $active_config = meza_build_admin_menu_editor_config(
        is_array($menu) ? $menu : [],
        is_array($submenu) ? $submenu : [],
        $menu_url_blacklist
    );
    $wp_menu_editor->restore_wp_menu();

    $menu = $original_menu;
    $submenu = $original_submenu;

    meza_set_admin_menu_editor_property(
        $wp_menu_editor,
        'merged_custom_menu',
        is_array($active_config) ? $active_config : $default_config
    );
}
add_action('admin_menu', 'meza_sync_admin_menu_editor_default_menu_snapshot', PHP_INT_MAX);

// Admin Menu Editor swaps in its custom menu after admin_menu, so reapply these mutations then as well.
add_action('admin_menu_editor-menu_replaced', 'meza_apply_late_admin_menu_mutations', PHP_INT_MAX);

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

function meza_move_site_health_tools_submenu_to_dashboard(): void
{
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
}

function meza_streamline_tools_submenu_items(): void
{
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
}

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

function meza_normalize_post_type_add_new_submenu_labels(): void
{
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
}

function meza_simplify_content_menu_submenu_labels(): void
{
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
}

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

function meza_apply_tail_admin_menu_mutations(): void
{
    meza_move_site_health_tools_submenu_to_dashboard();
    meza_filter_dashboard_submenu_items();
    meza_streamline_tools_submenu_items();
    meza_remove_admin_menu_counters();
    meza_normalize_post_type_add_new_submenu_labels();
    meza_simplify_content_menu_submenu_labels();
    meza_alphabetize_fallback_plugin_submenus();
    meza_finalize_tools_submenu_order();
}

// Apply the late dashboard/tools/submenu cleanup as a single ordered pass.
add_action('admin_menu', 'meza_apply_tail_admin_menu_mutations', PHP_INT_MAX - 1);
