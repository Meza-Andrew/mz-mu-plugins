<?php

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

    if (isset($widgets['dashboard_right_now'])) $ids['column1'][] = 'dashboard_right_now';

    $site_kit_widget_id = meza_dashboard_find_site_kit_widget_id($widgets);
    if ($site_kit_widget_id !== '') $ids['column1'][] = $site_kit_widget_id;

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

function meza_dashboard_flatten_allowed_widget_ids(array $allowed): array
{
    return array_values(array_unique(array_merge(
        $allowed['column1'] ?? [],
        $allowed['column2'] ?? [],
        $allowed['column3'] ?? []
    )));
}

function meza_dashboard_forced_order(array $allowed): array
{
    return [
        'normal' => implode(',', array_values($allowed['column1'] ?? [])),
        'side' => implode(',', array_values($allowed['column2'] ?? [])),
        'column3' => implode(',', array_values($allowed['column3'] ?? [])),
        'column4' => '',
    ];
}

function meza_dashboard_remove_disallowed_widgets_from_registry(array $allowed_lookup): void
{
    global $wp_meta_boxes;

    if (!isset($wp_meta_boxes['dashboard']) || !is_array($wp_meta_boxes['dashboard'])) {
        return;
    }

    foreach ($wp_meta_boxes['dashboard'] as $context => $priorities) {
        if (!is_array($priorities)) {
            continue;
        }

        foreach ($priorities as $priority => $widgets) {
            if (!is_array($widgets)) {
                continue;
            }

            foreach (array_keys($widgets) as $widget_id) {
                if (isset($allowed_lookup[$widget_id])) {
                    continue;
                }

                unset($wp_meta_boxes['dashboard'][$context][$priority][$widget_id]);
            }
        }
    }
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
    $widgets = meza_dashboard_collect_widgets();
    if ($widgets === []) {
        return;
    }

    meza_dashboard_normalize_widget_titles();

    $allowed = meza_dashboard_allowed_widget_ids($widgets);
    $allowed_ids = meza_dashboard_flatten_allowed_widget_ids($allowed);
    $allowed_lookup = array_fill_keys($allowed_ids, true);

    meza_dashboard_remove_disallowed_widgets_from_registry($allowed_lookup);

    $hidden_ids = array_values(array_diff(array_keys($widgets), $allowed_ids));
    $GLOBALS['meza_dashboard_hidden_ids'] = $hidden_ids;
    $GLOBALS['meza_dashboard_allowed_ids'] = $allowed_ids;
    $GLOBALS['meza_dashboard_forced_order'] = meza_dashboard_forced_order($allowed);

    $user_id = get_current_user_id();
    if ($user_id > 0) {
        $migration_key = 'meza_dashboard_widget_layout_initialized_v1';
        if (!get_user_meta($user_id, $migration_key, true)) {
            update_user_option($user_id, 'metaboxhidden_dashboard', $hidden_ids, false);
            update_user_option($user_id, 'closedpostboxes_dashboard', [], false);
            update_user_option($user_id, 'meta-box-order_dashboard', $GLOBALS['meza_dashboard_forced_order'], false);
            update_user_option($user_id, 'screen_layout_dashboard', 4, false);
            update_user_meta($user_id, $migration_key, 1);
        }
    }
}, 1000);

add_filter('default_hidden_meta_boxes', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen) || $screen->id !== 'dashboard') return $hidden;
    $forced_hidden = $GLOBALS['meza_dashboard_hidden_ids'] ?? [];
    if (!is_array($forced_hidden)) $forced_hidden = [];
    $allowed_ids = $GLOBALS['meza_dashboard_allowed_ids'] ?? [];
    if (!is_array($allowed_ids)) $allowed_ids = [];

    return array_values(array_unique(array_merge(array_diff((array) $hidden, $allowed_ids), $forced_hidden)));
}, 100, 2);

add_filter('hidden_meta_boxes', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen) || $screen->id !== 'dashboard') return $hidden;
    $forced_hidden = $GLOBALS['meza_dashboard_hidden_ids'] ?? [];
    if (!is_array($forced_hidden)) $forced_hidden = [];
    $allowed_ids = $GLOBALS['meza_dashboard_allowed_ids'] ?? [];
    if (!is_array($allowed_ids)) $allowed_ids = [];

    return array_values(array_unique(array_merge(array_diff((array) $hidden, $allowed_ids), $forced_hidden)));
}, 100, 2);

add_filter('get_user_option_meta-box-order_dashboard', function ($value) {
    $forced_order = $GLOBALS['meza_dashboard_forced_order'] ?? [];
    if (!is_array($forced_order) || $forced_order === []) {
        return $value;
    }

    if (!is_array($value)) {
        $value = [];
    }

    return array_merge($value, $forced_order);
}, 100);

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

function meza_render_admin_help_tab_hide(): void
{
    echo '<style id="meza-admin-help-tab-hide">#contextual-help-link-wrap,#contextual-help-wrap{display:none!important;}</style>';
}

function meza_render_post_screen_options_cleanup(): void
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen)) return;

    $post_type = (string) ($screen->post_type ?? '');

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
}

function meza_render_term_content_permissions_cleanup(): void
{
    if (meza_can_access_content_permissions_panel()) {
        return;
    }

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

add_action('current_screen', function ($screen): void {
    if (!($screen instanceof WP_Screen)) return;

    $screen->remove_help_tabs();
    $screen->set_help_sidebar('');
    $screen_base = (string) ($screen->base ?? '');

    if (in_array($screen_base, ['post', 'post-new'], true)) {
        add_action('admin_head', 'meza_render_post_screen_options_cleanup', 200);
        return;
    }

    if ($screen_base === 'term') {
        add_action('admin_head', 'meza_render_term_content_permissions_cleanup', 200);
    }
}, 250);

add_action('admin_head', 'meza_render_admin_help_tab_hide', 200);

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

if (!function_exists('meza_get_screen_option_default_meta_like_patterns')) {
    function meza_get_screen_option_default_meta_like_patterns(): array
    {
        return [
            'manage%columnshidden',
            'metaboxhidden_%',
            'closedpostboxes_%',
            'meta-box-order_%',
            'screen_layout_%',
            'edit_%_per_page',
        ];
    }
}

if (!function_exists('meza_get_screen_option_reset_meta_like_patterns')) {
    function meza_get_screen_option_reset_meta_like_patterns(): array
    {
        return array_merge(
            meza_get_screen_option_default_meta_like_patterns(),
            [
                'meza_%_initialized_%',
                'meza_%_migrated_%',
            ]
        );
    }
}

if (!function_exists('meza_normalize_screen_option_value')) {
    function meza_normalize_screen_option_value($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        return array_values(array_unique(array_map('strval', $value)));
    }
}

if (!function_exists('meza_get_admin_screen_option_defaults')) {
    function meza_get_admin_screen_option_defaults(): array
    {
        $defaults = get_option('meza_admin_screen_option_defaults', []);

        return is_array($defaults) ? $defaults : [];
    }
}

if (!function_exists('meza_get_admin_screen_option_default_value')) {
    function meza_get_admin_screen_option_default_value(string $meta_key)
    {
        $meta_key = trim($meta_key);
        if ($meta_key === '') {
            return null;
        }

        $defaults = meza_get_admin_screen_option_defaults();

        return array_key_exists($meta_key, $defaults)
            ? meza_normalize_screen_option_value($defaults[$meta_key])
            : null;
    }
}

if (!function_exists('meza_register_admin_screen_option_default_filters')) {
    function meza_register_admin_screen_option_default_filters(): void
    {
        static $registered = [];

        foreach (array_keys(meza_get_admin_screen_option_defaults()) as $meta_key) {
            $meta_key = trim((string) $meta_key);
            if ($meta_key === '' || isset($registered[$meta_key])) {
                continue;
            }

            add_filter('default_user_option_' . $meta_key, function ($default_value) use ($meta_key) {
                $admin_default = meza_get_admin_screen_option_default_value($meta_key);

                return $admin_default !== null ? $admin_default : $default_value;
            }, 1000, 1);

            add_filter('get_user_option_' . $meta_key, function ($value) use ($meta_key) {
                if ($value !== false && $value !== null && $value !== '') {
                    return $value;
                }

                $admin_default = meza_get_admin_screen_option_default_value($meta_key);

                return $admin_default !== null ? $admin_default : $value;
            }, 1000, 1);

            $registered[$meta_key] = true;
        }
    }
}

if (!function_exists('meza_capture_admin_screen_option_defaults_for_screen')) {
    function meza_capture_admin_screen_option_defaults_for_screen(WP_Screen $screen): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $user_id = get_current_user_id();
        $screen_id = (string) ($screen->id ?? '');
        if ($user_id <= 0 || $screen_id === '') {
            return;
        }

        $defaults = meza_get_admin_screen_option_defaults();
        $updated = $defaults;

        $updated['manage' . $screen_id . 'columnshidden'] = meza_normalize_screen_option_value(get_hidden_columns($screen));

        if (function_exists('get_hidden_meta_boxes')) {
            $updated['metaboxhidden_' . $screen_id] = meza_normalize_screen_option_value(get_hidden_meta_boxes($screen));
        }

        foreach ([
            'closedpostboxes_' . $screen_id,
            'meta-box-order_' . $screen_id,
            'screen_layout_' . $screen_id,
        ] as $meta_key) {
            $value = get_user_option($meta_key, $user_id);
            if ($value === false || $value === null || $value === '') {
                continue;
            }

            $updated[$meta_key] = meza_normalize_screen_option_value($value);
        }

        if (method_exists($screen, 'get_option')) {
            $per_page_option = (string) $screen->get_option('per_page', 'option');
            if ($per_page_option !== '') {
                $per_page_value = get_user_option($per_page_option, $user_id);
                if ($per_page_value !== false && $per_page_value !== null && $per_page_value !== '') {
                    $updated[$per_page_option] = meza_normalize_screen_option_value($per_page_value);
                }
            }
        }

        if ($updated === $defaults) {
            return;
        }

        update_option('meza_admin_screen_option_defaults', $updated, false);
        meza_register_admin_screen_option_default_filters();
    }
}

add_action('current_screen', function ($screen): void {
    if (!($screen instanceof WP_Screen)) {
        return;
    }

    meza_capture_admin_screen_option_defaults_for_screen($screen);
}, 999);

meza_register_admin_screen_option_default_filters();

if (!function_exists('meza_get_screen_options_reset_token')) {
    function meza_get_screen_options_reset_token(): string
    {
        if (!defined('MZ_RESET_SCREEN_OPTIONS_FOR_CURRENT_USER')) {
            return '';
        }

        $token = trim((string) constant('MZ_RESET_SCREEN_OPTIONS_FOR_CURRENT_USER'));

        return $token !== '' ? $token : '';
    }
}

if (!function_exists('meza_reset_screen_options_for_user')) {
    function meza_reset_screen_options_for_user(int $user_id): void
    {
        global $wpdb;

        if ($user_id <= 0 || !isset($wpdb->usermeta)) {
            return;
        }

        $like_sql = [];
        $like_values = [$user_id];

        foreach (meza_get_screen_option_reset_meta_like_patterns() as $pattern) {
            $like_sql[] = 'meta_key LIKE %s';
            $like_values[] = $pattern;
        }

        $meta_keys = $wpdb->get_col($wpdb->prepare(
            "
            SELECT meta_key
            FROM {$wpdb->usermeta}
            WHERE user_id = %d
              AND (" . implode(' OR ', $like_sql) . ')
            ',
            ...$like_values
        ));

        if (!is_array($meta_keys) || empty($meta_keys)) {
            return;
        }

        foreach (array_values(array_unique(array_map('strval', $meta_keys))) as $meta_key) {
            if ($meta_key === '') {
                continue;
            }

            delete_user_meta($user_id, $meta_key);
        }
    }
}

if (!function_exists('meza_get_all_users_screen_options_reset_token')) {
    function meza_get_all_users_screen_options_reset_token(): string
    {
        if (!defined('MZ_RESET_SCREEN_OPTIONS_FOR_ALL_USERS')) {
            return '';
        }

        $token = trim((string) constant('MZ_RESET_SCREEN_OPTIONS_FOR_ALL_USERS'));

        return $token !== '' ? $token : '';
    }
}

if (!function_exists('meza_reset_screen_options_for_all_users')) {
    function meza_reset_screen_options_for_all_users(): void
    {
        global $wpdb;

        if (!isset($wpdb->usermeta)) {
            return;
        }

        $like_sql = [];
        $like_values = [];

        foreach (meza_get_screen_option_reset_meta_like_patterns() as $pattern) {
            $like_sql[] = 'meta_key LIKE %s';
            $like_values[] = $pattern;
        }

        if ($like_sql === []) {
            return;
        }

        $wpdb->query($wpdb->prepare(
            "
            DELETE FROM {$wpdb->usermeta}
            WHERE " . implode(' OR ', $like_sql),
            ...$like_values
        ));
    }
}

add_action('admin_init', function (): void {
    if (!is_admin()) {
        return;
    }

    $token = meza_get_screen_options_reset_token();
    if ($token === '') {
        return;
    }

    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return;
    }

    $applied_token_key = 'meza_screen_options_reset_token';
    $applied_token = get_user_meta($user_id, $applied_token_key, true);
    if (is_string($applied_token) && hash_equals($applied_token, $token)) {
        return;
    }

    meza_reset_screen_options_for_user($user_id);
    update_user_meta($user_id, $applied_token_key, $token);
}, 1);

add_action('admin_init', function (): void {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    $token = meza_get_all_users_screen_options_reset_token();
    if ($token === '') {
        return;
    }

    $applied_token_key = 'meza_screen_options_reset_all_users_token';
    $applied_token = (string) get_option($applied_token_key, '');
    if ($applied_token !== '' && hash_equals($applied_token, $token)) {
        return;
    }

    meza_reset_screen_options_for_all_users();
    update_option($applied_token_key, $token, false);
}, 1);

if (!function_exists('meza_get_post_admin_list_per_page')) {
    function meza_get_post_admin_list_per_page(): int
    {
        $default_per_page = function_exists('meza_admin_items_per_page_target')
            ? meza_admin_items_per_page_target()
            : 20;
        $per_page = (int) apply_filters('meza_post_admin_list_per_page', $default_per_page);
        return $per_page > 0 ? $per_page : 20;
    }
}

if (!function_exists('meza_get_post_admin_list_screen_option_name')) {
    function meza_get_post_admin_list_screen_option_name(string $post_type): string
    {
        $post_type = sanitize_key($post_type);
        return $post_type !== '' ? 'edit_' . $post_type . '_per_page' : '';
    }
}

// Keep post-type admin lists light enough to stay responsive on content-heavy sites.
add_action('current_screen', function ($screen): void {
    if (!($screen instanceof WP_Screen)) return;
    if ((string) ($screen->base ?? '') !== 'edit') return;

    $post_type = sanitize_key((string) ($screen->post_type ?? ''));
    if ($post_type === '') return;

    $post_type_object = get_post_type_object($post_type);
    if (!($post_type_object instanceof WP_Post_Type) || empty($post_type_object->show_ui)) return;

    $option = meza_get_post_admin_list_screen_option_name($post_type);
    if ($option === '') return;
    $target_per_page = meza_get_post_admin_list_per_page();
    $user_id = get_current_user_id();

    add_filter($option, function ($per_page) use ($target_per_page) {
        $per_page = (int) $per_page;
        if ($per_page <= 0) {
            return $target_per_page;
        }

        return min($per_page, $target_per_page);
    });

    add_filter('get_user_option_' . $option, function ($value) use ($target_per_page) {
        $value = (int) $value;
        if ($value <= 0) {
            return $target_per_page;
        }

        return min($value, $target_per_page);
    });

    if ($user_id <= 0) return;

    $saved_value = (int) get_user_option($option, $user_id);
    if ($saved_value !== $target_per_page) {
        update_user_option($user_id, $option, $target_per_page, false);
    }
}, 20);

// Enforce the post-list page size on the actual main admin query too, since some
// edit screens can rebuild the query with a larger payload after screen options load.
add_action('pre_get_posts', function (WP_Query $query): void {
    global $pagenow;

    if (!is_admin() || !$query->is_main_query() || $pagenow !== 'edit.php') {
        return;
    }

    $post_type = sanitize_key((string) $query->get('post_type'));
    if ($post_type === '') {
        $post_type = 'post';
    }

    if (!post_type_exists($post_type)) {
        return;
    }

    $post_type_object = get_post_type_object($post_type);
    if (!($post_type_object instanceof WP_Post_Type) || empty($post_type_object->show_ui)) {
        return;
    }

    $target_per_page = meza_get_post_admin_list_per_page();
    $current_per_page = (int) $query->get('posts_per_page');

    if ($current_per_page <= 0 || $current_per_page > $target_per_page) {
        $query->set('posts_per_page', $target_per_page);
    }

    $archive_per_page = (int) $query->get('posts_per_archive_page');
    if ($archive_per_page <= 0 || $archive_per_page > $target_per_page) {
        $query->set('posts_per_archive_page', $target_per_page);
    }

    if (
        is_post_type_hierarchical($post_type)
        && (string) $query->get('fields') === 'id=>parent'
        && (int) $query->get('posts_per_page') === $target_per_page
    ) {
        $query->set('fields', '');
    }
}, 5);

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
    remove_submenu_page('themes.php', 'custom-background');
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

function meza_filter_disabled_tag_taxonomy_submenus(): void
{
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
}

add_action('admin_menu', 'meza_filter_disabled_tag_taxonomy_submenus', PHP_INT_MAX - 5);

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

    $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';

    $is_design_page = in_array($pagenow, ['customize.php', 'widgets.php', 'theme-editor.php', 'site-editor.php'], true)
        || ($pagenow === 'themes.php' && $page === 'custom-background')
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
        $is_background = $slug === 'custom-background'
            || str_contains($slug, 'page=custom-background')
            || $label === 'background';
        $is_widgets = $slug === 'widgets.php' || $label === 'widgets';
        $is_site_editor = str_starts_with($slug, 'site-editor.php') || $label === 'editor';
        $is_theme_editor = $slug === 'theme-editor.php' || $label === 'theme file editor';
        $is_patterns = ($slug === 'edit.php?post_type=wp_block')
            || (str_starts_with($slug, 'site-editor.php') && str_contains($slug, 'patterns'))
            || $label === 'patterns';

        return !($is_customize || $is_background || $is_widgets || $is_site_editor || $is_theme_editor || $is_patterns);
    }));
}, 99999);

function meza_admin_menu_content_group(string $menu_slug): string
{
    $menu_slug = trim($menu_slug);
    if ($menu_slug === '') return '';

    $rule_based_group = meza_admin_resolve_menu_content_group_from_rules($menu_slug);
    if ($rule_based_group !== '') {
        return $rule_based_group;
    }

    if (function_exists('acf_get_options_page') && acf_get_options_page($menu_slug)) {
        return 'without';
    }

    if ($menu_slug === 'link-manager.php') {
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

if (!function_exists('meza_admin_normalize_rule_string_list')) {
    function meza_admin_normalize_rule_string_list($values): array
    {
        if (is_string($values)) {
            $values = [$values];
        }

        if (!is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $value = strtolower(trim((string) $value));
            if ($value === '') {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }
}

if (!function_exists('meza_admin_menu_slug_matches_rule')) {
    function meza_admin_menu_slug_matches_rule(string $menu_slug, array $rule): bool
    {
        $menu_slug = strtolower(trim($menu_slug));
        if ($menu_slug === '') {
            return false;
        }

        foreach (meza_admin_normalize_rule_string_list($rule['menu_slug_equals'] ?? []) as $candidate) {
            if ($menu_slug === $candidate) {
                return true;
            }
        }

        foreach (meza_admin_normalize_rule_string_list($rule['menu_slug_contains'] ?? []) as $candidate) {
            if (str_contains($menu_slug, $candidate)) {
                return true;
            }
        }

        $callback = $rule['match_callback'] ?? null;
        if (is_callable($callback)) {
            return (bool) call_user_func($callback, $menu_slug, $rule);
        }

        return false;
    }
}

if (!function_exists('meza_admin_get_menu_content_group_rules')) {
    function meza_admin_get_menu_content_group_rules(): array
    {
        $defaults = [
            [
                'id' => 'gravityforms',
                'group' => 'without',
                'menu_slug_contains' => [
                    'gf_edit_forms',
                    'gravityforms',
                ],
            ],
        ];

        $rules = apply_filters('meza_admin_menu_content_group_rules', $defaults);

        return is_array($rules) ? $rules : $defaults;
    }
}

if (!function_exists('meza_admin_resolve_menu_content_group_from_rules')) {
    function meza_admin_resolve_menu_content_group_from_rules(string $menu_slug): string
    {
        foreach (meza_admin_get_menu_content_group_rules() as $rule) {
            if (!is_array($rule) || !meza_admin_menu_slug_matches_rule($menu_slug, $rule)) {
                continue;
            }

            $group = strtolower(trim((string) ($rule['group'] ?? '')));
            if (in_array($group, ['with', 'without'], true)) {
                return $group;
            }
        }

        return '';
    }
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
