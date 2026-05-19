<?php

if (!defined('MEZA_ACTIVITY_LOG_OPTION')) {
    define('MEZA_ACTIVITY_LOG_OPTION', 'meza_admin_activity_log_entries_v1');
}

if (!defined('MEZA_ACTIVITY_LOG_LIMIT')) {
    define('MEZA_ACTIVITY_LOG_LIMIT', 200);
}

if (!defined('MEZA_ACTIVITY_LOG_PAGE_SLUG')) {
    define('MEZA_ACTIVITY_LOG_PAGE_SLUG', 'meza-activity-log');
}

if (!defined('MEZA_ACTIVITY_LOG_PER_PAGE_OPTION')) {
    define('MEZA_ACTIVITY_LOG_PER_PAGE_OPTION', 'meza_activity_log_per_page');
}

if (!defined('MEZA_ACTIVITY_LOG_PER_PAGE_DEFAULT')) {
    define('MEZA_ACTIVITY_LOG_PER_PAGE_DEFAULT', 20);
}

function meza_activity_log_get_menu_capability(): string
{
    return function_exists('meza_view_site_activity_capability')
        ? meza_view_site_activity_capability()
        : 'meza_doc_view_site_activity';
}

function meza_activity_log_can_view(): bool
{
    return current_user_can(meza_activity_log_get_menu_capability());
}

function meza_activity_log_get_parent_menu_slug(): string
{
    if (function_exists('meza_get_site_settings_menu_slug')) {
        $slug = (string) meza_get_site_settings_menu_slug();
        if ($slug !== '') {
            return $slug;
        }
    }

    return 'index.php';
}

function meza_register_activity_log_dashboard_page(): void
{
    $hook_suffix = add_submenu_page(
        meza_activity_log_get_parent_menu_slug(),
        'Activity',
        'Activity',
        meza_activity_log_get_menu_capability(),
        MEZA_ACTIVITY_LOG_PAGE_SLUG,
        'meza_render_activity_log_dashboard_page'
    );

    if ($hook_suffix) {
        add_action('load-' . $hook_suffix, 'meza_activity_log_load_dashboard_page');
    }
}
add_action('admin_menu', 'meza_register_activity_log_dashboard_page', 30);
add_action('admin_menu_editor-menu_replaced', 'meza_register_activity_log_dashboard_page', 30);

function meza_activity_log_load_dashboard_page(): void
{
    add_screen_option('per_page', [
        'label' => 'Number of items per page:',
        'default' => MEZA_ACTIVITY_LOG_PER_PAGE_DEFAULT,
        'option' => MEZA_ACTIVITY_LOG_PER_PAGE_OPTION,
    ]);
}

add_filter('set-screen-option', static function ($status, string $option, $value) {
    if ($option !== MEZA_ACTIVITY_LOG_PER_PAGE_OPTION) {
        return $status;
    }

    $value = (int) $value;
    if ($value < 1) {
        $value = MEZA_ACTIVITY_LOG_PER_PAGE_DEFAULT;
    }

    return min(200, $value);
}, 10, 3);

function meza_activity_log_get_action_labels(): array
{
    return [
        'created' => 'Created',
        'updated' => 'Updated',
        'published' => 'Published',
        'scheduled' => 'Scheduled',
        'unpublished' => 'Unpublished',
        'status_changed' => 'Status changed',
        'trashed' => 'Moved to Trash',
        'restored' => 'Restored',
        'deleted' => 'Deleted',
        'uploaded' => 'Uploaded',
        'settings_updated' => 'Settings updated',
        'user_created' => 'User created',
        'user_updated' => 'User updated',
        'role_changed' => 'Role changed',
        'user_deleted' => 'User deleted',
        'password_reset' => 'Password reset',
        'menu_updated' => 'Menu updated',
        'plugin_activated' => 'Plugin activated',
        'plugin_deactivated' => 'Plugin deactivated',
        'plugin_updated' => 'Plugin updated',
        'plugin_installed' => 'Plugin installed',
        'theme_switched' => 'Theme switched',
        'theme_updated' => 'Theme updated',
        'theme_installed' => 'Theme installed',
        'core_updated' => 'WordPress updated',
        'integration_failed' => 'Integration failed',
        'log_reset' => 'Activity log reset',
    ];
}

function meza_activity_log_get_action_label(string $action): string
{
    $labels = meza_activity_log_get_action_labels();

    return $labels[$action] ?? ucwords(str_replace('_', ' ', $action));
}

function meza_activity_log_get_action_dashicon(string $action): string
{
    $map = [
        'created' => 'dashicons-plus-alt2',
        'updated' => 'dashicons-edit',
        'published' => 'dashicons-yes-alt',
        'scheduled' => 'dashicons-clock',
        'unpublished' => 'dashicons-hidden',
        'status_changed' => 'dashicons-randomize',
        'trashed' => 'dashicons-trash',
        'restored' => 'dashicons-undo',
        'deleted' => 'dashicons-remove',
        'uploaded' => 'dashicons-upload',
        'settings_updated' => 'dashicons-admin-settings',
        'user_created' => 'dashicons-admin-users',
        'user_updated' => 'dashicons-admin-users',
        'role_changed' => 'dashicons-admin-users',
        'user_deleted' => 'dashicons-dismiss',
        'password_reset' => 'dashicons-lock',
        'menu_updated' => 'dashicons-menu',
        'plugin_activated' => 'dashicons-admin-plugins',
        'plugin_deactivated' => 'dashicons-admin-plugins',
        'plugin_updated' => 'dashicons-update',
        'plugin_installed' => 'dashicons-download',
        'theme_switched' => 'dashicons-admin-appearance',
        'theme_updated' => 'dashicons-update',
        'theme_installed' => 'dashicons-admin-appearance',
        'core_updated' => 'dashicons-update',
        'integration_failed' => 'dashicons-warning',
        'log_reset' => 'dashicons-backup',
    ];

    return $map[$action] ?? 'dashicons-admin-post';
}

function meza_activity_log_get_category_labels(): array
{
    return [
        'content' => 'Content Activity',
        'site' => 'Site Activity',
    ];
}

function meza_activity_log_get_category_label(string $category): string
{
    $labels = meza_activity_log_get_category_labels();

    return $labels[$category] ?? ucwords(str_replace('_', ' ', $category));
}

function meza_activity_log_get_post_type_label(string $post_type): string
{
    $post_type_object = get_post_type_object($post_type);
    if ($post_type_object instanceof WP_Post_Type) {
        return (string) ($post_type_object->labels->singular_name ?? $post_type);
    }

    return ucwords(str_replace(['-', '_'], ' ', $post_type));
}

function meza_activity_log_get_post_status_label(string $status): string
{
    $status = trim($status);
    if ($status === '') {
        return '';
    }

    $status_object = get_post_status_object($status);
    if ($status_object && isset($status_object->label)) {
        return wp_strip_all_tags((string) $status_object->label);
    }

    return ucwords(str_replace(['-', '_'], ' ', $status));
}

function meza_activity_log_get_post_title(WP_Post $post): string
{
    $title = trim(wp_strip_all_tags((string) get_the_title($post)));
    if ($title !== '') {
        return $title;
    }

    if ($post->post_type === 'attachment') {
        return 'Untitled media item';
    }

    return 'Untitled content';
}

function meza_activity_log_get_user_display_name(int $user_id, string $fallback = ''): string
{
    if ($user_id > 0) {
        $user = get_userdata($user_id);
        if ($user instanceof WP_User) {
            return trim((string) $user->display_name);
        }
    }

    $fallback = trim($fallback);
    return $fallback !== '' ? $fallback : 'System';
}

function meza_activity_log_get_user_object_title($user): string
{
    if ($user instanceof WP_User) {
        $display_name = trim((string) $user->display_name);
        $user_login = trim((string) $user->user_login);

        if ($display_name !== '' && $user_login !== '' && strcasecmp($display_name, $user_login) !== 0) {
            return $display_name . ' (' . $user_login . ')';
        }

        if ($display_name !== '') {
            return $display_name;
        }

        if ($user_login !== '') {
            return $user_login;
        }
    }

    return 'User account';
}

function meza_activity_log_get_user_edit_url(int $user_id): string
{
    return $user_id > 0 ? (string) get_edit_user_link($user_id) : '';
}

function meza_activity_log_values_equal($left, $right): bool
{
    if (is_scalar($left) && is_scalar($right)) {
        return (string) $left === (string) $right;
    }

    return wp_json_encode($left) === wp_json_encode($right);
}

function meza_activity_log_should_track_post(WP_Post $post): bool
{
    if (
        wp_is_post_revision($post->ID)
        || wp_is_post_autosave($post->ID)
        || in_array($post->post_type, ['revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request'], true)
        || str_starts_with((string) $post->post_type, 'wp_')
    ) {
        return false;
    }

    if (function_exists('meza_is_acf_admin_post_type') && meza_is_acf_admin_post_type((string) $post->post_type)) {
        return false;
    }

    if ($post->post_type === 'attachment') {
        return true;
    }

    $post_type_object = get_post_type_object((string) $post->post_type);
    if (!($post_type_object instanceof WP_Post_Type)) {
        return false;
    }

    return (bool) $post_type_object->show_ui;
}

function meza_activity_log_store_entry(array $entry): void
{
    $option_name = MEZA_ACTIVITY_LOG_OPTION;
    $entries = get_option($option_name, []);

    if (!is_array($entries)) {
        $entries = [];
    }

    array_unshift($entries, $entry);
    $entries = array_slice($entries, 0, (int) MEZA_ACTIVITY_LOG_LIMIT);

    if (get_option($option_name, null) === null) {
        add_option($option_name, $entries, '', false);
        return;
    }

    update_option($option_name, $entries, false);
}

function meza_activity_log_maybe_reset_entries(): void
{
    if (
        !is_admin()
        || !defined('MZ_RESET_ACTIVITY_LOG')
        || !MZ_RESET_ACTIVITY_LOG
    ) {
        return;
    }

    delete_option(MEZA_ACTIVITY_LOG_OPTION);

    meza_activity_log_record_event([
        'category' => 'site',
        'action' => 'log_reset',
        'object_type' => 'activity_log',
        'object_subtype' => 'manual_reset',
        'object_label' => 'Activity Log',
        'object_title' => 'Activity Log',
        'details' => 'Activity log was reset from wp-config.php.',
    ]);
}
add_action('admin_init', 'meza_activity_log_maybe_reset_entries', 1);

function meza_activity_log_record_event(array $entry): void
{
    $action = sanitize_key((string) ($entry['action'] ?? ''));
    if ($action === '') {
        return;
    }

    $user_id = isset($entry['user_id']) ? (int) $entry['user_id'] : get_current_user_id();
    $object_type = sanitize_key((string) ($entry['object_type'] ?? 'site'));
    $category = sanitize_key((string) ($entry['category'] ?? ($object_type === 'post' ? 'content' : 'site')));
    if (!in_array($category, ['content', 'site'], true)) {
        $category = 'site';
    }

    meza_activity_log_store_entry([
        'timestamp' => isset($entry['timestamp']) ? (int) $entry['timestamp'] : current_time('timestamp'),
        'category' => $category,
        'category_label' => meza_activity_log_get_category_label($category),
        'action' => $action,
        'action_label' => meza_activity_log_get_action_label($action),
        'user_id' => $user_id,
        'user_name' => meza_activity_log_get_user_display_name($user_id, (string) ($entry['user_name'] ?? '')),
        'object_id' => isset($entry['object_id']) ? (int) $entry['object_id'] : 0,
        'object_type' => $object_type,
        'object_subtype' => sanitize_key((string) ($entry['object_subtype'] ?? '')),
        'object_label' => trim((string) ($entry['object_label'] ?? '')),
        'object_title' => trim((string) ($entry['object_title'] ?? '')),
        'object_url' => isset($entry['object_url']) && is_string($entry['object_url']) ? $entry['object_url'] : '',
        'status' => sanitize_key((string) ($entry['status'] ?? '')),
        'status_label' => trim((string) ($entry['status_label'] ?? '')),
        'details' => trim((string) ($entry['details'] ?? '')),
        'is_inferred' => !empty($entry['is_inferred']),
    ]);
}

function meza_activity_log_record_post_event(string $action, WP_Post $post, array $overrides = []): void
{
    if (!meza_activity_log_should_track_post($post)) {
        return;
    }

    $status = isset($overrides['status']) ? sanitize_key((string) $overrides['status']) : (string) $post->post_status;
    $url = get_edit_post_link($post->ID, 'raw');

    meza_activity_log_record_event([
        'category' => 'content',
        'timestamp' => isset($overrides['timestamp']) ? (int) $overrides['timestamp'] : current_time('timestamp'),
        'action' => $action,
        'user_id' => isset($overrides['user_id']) ? (int) $overrides['user_id'] : get_current_user_id(),
        'user_name' => (string) ($overrides['user_name'] ?? ''),
        'object_id' => (int) $post->ID,
        'object_type' => 'post',
        'object_subtype' => (string) $post->post_type,
        'object_label' => meza_activity_log_get_post_type_label((string) $post->post_type),
        'object_title' => meza_activity_log_get_post_title($post),
        'object_url' => is_string($url) ? $url : '',
        'status' => $status,
        'status_label' => meza_activity_log_get_post_status_label($status),
        'details' => trim((string) ($overrides['details'] ?? '')),
        'is_inferred' => !empty($overrides['is_inferred']),
    ]);
}

function meza_activity_log_get_transition_details(string $old_status, string $new_status): string
{
    $old_label = meza_activity_log_get_post_status_label($old_status);
    $new_label = meza_activity_log_get_post_status_label($new_status);

    if ($old_label === '' && $new_label !== '') {
        return sprintf('Status set to %s.', $new_label);
    }

    if ($old_label !== '' && $new_label !== '') {
        return sprintf('Status changed from %s to %s.', $old_label, $new_label);
    }

    return '';
}

function meza_activity_log_get_tracked_option_definitions(): array
{
    return [
        'blogname' => 'Site Title',
        'blogdescription' => 'Tagline',
        'show_on_front' => 'Homepage Displays',
        'page_on_front' => 'Homepage',
        'page_for_posts' => 'Posts Page',
        'blog_public' => 'Search Engine Visibility',
        'permalink_structure' => 'Permalink Structure',
    ];
}

function meza_activity_log_get_setting_option_label(string $option_name): string
{
    $definitions = meza_activity_log_get_tracked_option_definitions();

    return $definitions[$option_name] ?? ucwords(str_replace(['-', '_'], ' ', $option_name));
}

function meza_activity_log_get_post_reference_label($value): string
{
    $post_id = (int) $value;
    if ($post_id < 1) {
        return 'None';
    }

    $post = get_post($post_id);
    if (!($post instanceof WP_Post)) {
        return '#' . $post_id;
    }

    $title = meza_activity_log_get_post_title($post);

    return $title . ' (#' . $post_id . ')';
}

function meza_activity_log_get_menu_reference_label($value): string
{
    $menu_id = (int) $value;
    if ($menu_id < 1) {
        return 'Unassigned';
    }

    $menu = wp_get_nav_menu_object($menu_id);
    if ($menu instanceof WP_Term && !empty($menu->name)) {
        return trim((string) $menu->name);
    }

    return '#' . $menu_id;
}

function meza_activity_log_format_setting_value(string $option_name, $value): string
{
    if ($option_name === 'show_on_front') {
        return $value === 'page' ? 'A static page' : 'Your latest posts';
    }

    if (in_array($option_name, ['page_on_front', 'page_for_posts'], true)) {
        return meza_activity_log_get_post_reference_label($value);
    }

    if ($option_name === 'blog_public') {
        return ((int) $value === 1) ? 'Discourage disabled' : 'Discourage enabled';
    }

    if ($option_name === 'permalink_structure') {
        $value = trim((string) $value);
        return $value !== '' ? $value : 'Plain';
    }

    $value = trim((string) $value);
    return $value !== '' ? $value : 'Empty';
}

function meza_activity_log_record_option_change(string $option_name, $new_value, $old_value): void
{
    if (meza_activity_log_values_equal($new_value, $old_value)) {
        return;
    }

    $details = sprintf(
        '%s changed from %s to %s.',
        meza_activity_log_get_setting_option_label($option_name),
        meza_activity_log_format_setting_value($option_name, $old_value),
        meza_activity_log_format_setting_value($option_name, $new_value)
    );

    meza_activity_log_record_event([
        'category' => 'site',
        'action' => 'settings_updated',
        'object_type' => 'setting',
        'object_subtype' => sanitize_key($option_name),
        'object_label' => 'Site Setting',
        'object_title' => meza_activity_log_get_setting_option_label($option_name),
        'object_url' => admin_url('options-general.php'),
        'details' => $details,
    ]);
}

function meza_activity_log_record_theme_locations_change(array $new_locations, array $old_locations): void
{
    $normalized_old = [];
    foreach ($old_locations as $location => $menu_id) {
        $normalized_old[sanitize_key((string) $location)] = (int) $menu_id;
    }

    $normalized_new = [];
    foreach ($new_locations as $location => $menu_id) {
        $normalized_new[sanitize_key((string) $location)] = (int) $menu_id;
    }

    if ($normalized_new === $normalized_old) {
        return;
    }

    $location_labels = get_registered_nav_menus();
    $changes = [];

    foreach (array_unique(array_merge(array_keys($normalized_old), array_keys($normalized_new))) as $location) {
        $old_menu_id = $normalized_old[$location] ?? 0;
        $new_menu_id = $normalized_new[$location] ?? 0;

        if ($old_menu_id === $new_menu_id) {
            continue;
        }

        $location_label = trim((string) ($location_labels[$location] ?? ucwords(str_replace(['-', '_'], ' ', $location))));
        $changes[] = sprintf(
            '%s changed from %s to %s',
            $location_label,
            meza_activity_log_get_menu_reference_label($old_menu_id),
            meza_activity_log_get_menu_reference_label($new_menu_id)
        );
    }

    if ($changes === []) {
        return;
    }

    meza_activity_log_record_event([
        'category' => 'site',
        'action' => 'settings_updated',
        'object_type' => 'menu',
        'object_subtype' => 'nav_menu_locations',
        'object_label' => 'Menu Locations',
        'object_title' => 'Menu Assignments',
        'object_url' => admin_url('nav-menus.php'),
        'details' => implode('. ', $changes) . '.',
    ]);
}

function meza_activity_log_record_acf_options_page_save(string $post_id): void
{
    if (!function_exists('meza_get_settings_admin_page_title_for_slug')) {
        return;
    }

    $normalized_post_id = strtolower(trim($post_id));
    if ($normalized_post_id === '' || ($normalized_post_id !== 'options' && $normalized_post_id !== 'option' && !str_starts_with($normalized_post_id, 'options_'))) {
        return;
    }

    $slug = 'options';
    if (str_starts_with($normalized_post_id, 'options_')) {
        $slug = str_replace('_', '-', substr($normalized_post_id, 8));
    }

    $allowed_slugs = [
        'options',
        'branding',
        'business-information',
        'content-model',
        'crm',
        'ecommerce',
    ];

    if (!in_array($slug, $allowed_slugs, true)) {
        return;
    }

    $title = $slug === 'options'
        ? 'Site Settings'
        : meza_get_settings_admin_page_title_for_slug($slug);

    $url = $slug === 'options'
        ? admin_url('options-general.php')
        : (
            function_exists('meza_get_shared_project_acf_options_page_menu_slug')
                ? admin_url(meza_get_shared_project_acf_options_page_menu_slug($slug))
                : admin_url('options-general.php')
        );

    meza_activity_log_record_event([
        'category' => 'site',
        'action' => 'settings_updated',
        'object_type' => 'setting',
        'object_subtype' => sanitize_key($slug),
        'object_label' => 'Settings Page',
        'object_title' => $title,
        'object_url' => $url,
        'details' => $title . ' was updated.',
    ]);
}

function meza_activity_log_record_integration_failure(string $provider, string $message, array $context = []): void
{
    $provider = sanitize_key($provider);
    if ($provider === '') {
        $provider = 'integration';
    }

    $provider_label = ucwords(str_replace('_', ' ', $provider));
    $email = isset($context['Email']) ? sanitize_email((string) $context['Email']) : '';
    $form_label = isset($context['FormLabel']) ? sanitize_text_field((string) $context['FormLabel']) : '';

    $details_parts = [trim($message)];

    if ($form_label !== '') {
        $details_parts[] = 'Form: ' . $form_label;
    }

    if ($email !== '') {
        $details_parts[] = 'Email: ' . $email;
    }

    meza_activity_log_record_event([
        'category' => 'site',
        'action' => 'integration_failed',
        'object_type' => 'integration',
        'object_subtype' => $provider,
        'object_label' => 'Integration',
        'object_title' => $provider_label,
        'object_url' => function_exists('meza_get_shared_project_acf_options_page_menu_slug')
            ? admin_url(meza_get_shared_project_acf_options_page_menu_slug('crm'))
            : admin_url('options-general.php'),
        'details' => implode('. ', array_filter($details_parts)) . '.',
    ]);
}

add_action('transition_post_status', function (string $new_status, string $old_status, WP_Post $post): void {
    if (!meza_activity_log_should_track_post($post)) {
        return;
    }

    if (
        $new_status === $old_status
        || $old_status === 'trash'
        || in_array($new_status, ['auto-draft', 'inherit', 'new', 'trash'], true)
    ) {
        return;
    }

    $action = 'status_changed';

    if ($new_status === 'publish' && $old_status !== 'publish') {
        $action = 'published';
    } elseif ($new_status === 'future') {
        $action = 'scheduled';
    } elseif ($new_status === 'draft' && in_array($old_status, ['new', 'auto-draft'], true)) {
        $action = 'created';
    } elseif ($old_status === 'publish' && $new_status !== 'publish') {
        $action = 'unpublished';
    }

    meza_activity_log_record_post_event($action, $post, [
        'status' => $new_status,
        'details' => meza_activity_log_get_transition_details($old_status, $new_status),
    ]);
}, 20, 3);

add_action('post_updated', function (int $post_id, WP_Post $post_after, WP_Post $post_before): void {
    if (!meza_activity_log_should_track_post($post_after)) {
        return;
    }

    if (
        $post_after->post_status !== $post_before->post_status
        || in_array((string) $post_before->post_status, ['auto-draft', 'new'], true)
        || in_array((string) $post_after->post_status, ['auto-draft', 'inherit'], true)
    ) {
        return;
    }

    meza_activity_log_record_post_event('updated', $post_after);
}, 20, 3);

add_action('add_attachment', function (int $post_id): void {
    $post = get_post($post_id);
    if ($post instanceof WP_Post) {
        meza_activity_log_record_post_event('uploaded', $post);
    }
}, 20);

add_action('wp_trash_post', function (int $post_id): void {
    $post = get_post($post_id);
    if ($post instanceof WP_Post) {
        meza_activity_log_record_post_event('trashed', $post, ['status' => 'trash']);
    }
}, 20);

add_action('untrashed_post', function (int $post_id): void {
    $post = get_post($post_id);
    if ($post instanceof WP_Post) {
        meza_activity_log_record_post_event('restored', $post);
    }
}, 20);

add_action('before_delete_post', function (int $post_id, WP_Post $post): void {
    meza_activity_log_record_post_event('deleted', $post);
}, 20, 2);

foreach (array_keys(meza_activity_log_get_tracked_option_definitions()) as $tracked_option_name) {
    add_filter('pre_update_option_' . $tracked_option_name, function ($new_value, $old_value) use ($tracked_option_name) {
        meza_activity_log_record_option_change($tracked_option_name, $new_value, $old_value);
        return $new_value;
    }, 10, 2);
}

add_action('set_theme_mod_custom_logo', function ($value, $old_value): void {
    if (meza_activity_log_values_equal($value, $old_value)) {
        return;
    }

    meza_activity_log_record_event([
        'category' => 'site',
        'action' => 'settings_updated',
        'object_type' => 'appearance',
        'object_subtype' => 'custom_logo',
        'object_label' => 'Appearance',
        'object_title' => 'Site Logo',
        'object_url' => admin_url('themes.php?page=custom-header'),
        'details' => sprintf(
            'Site logo changed from %s to %s.',
            meza_activity_log_get_post_reference_label($old_value),
            meza_activity_log_get_post_reference_label($value)
        ),
    ]);
}, 10, 2);

add_action('updated_option', function (string $option, $old_value, $value): void {
    if (!str_starts_with($option, 'theme_mods_')) {
        return;
    }

    $old_locations = is_array($old_value) ? (array) ($old_value['nav_menu_locations'] ?? []) : [];
    $new_locations = is_array($value) ? (array) ($value['nav_menu_locations'] ?? []) : [];
    meza_activity_log_record_theme_locations_change($new_locations, $old_locations);
}, 10, 3);

add_action('wp_update_nav_menu', function (int $menu_id): void {
    $menu = wp_get_nav_menu_object($menu_id);
    $menu_name = $menu instanceof WP_Term && !empty($menu->name)
        ? trim((string) $menu->name)
        : 'Navigation Menu';

    meza_activity_log_record_event([
        'category' => 'site',
        'action' => 'menu_updated',
        'object_id' => $menu_id,
        'object_type' => 'menu',
        'object_subtype' => 'navigation',
        'object_label' => 'Menu',
        'object_title' => $menu_name,
        'object_url' => admin_url('nav-menus.php?action=edit&menu=' . $menu_id),
        'details' => $menu_name . ' was updated.',
    ]);
}, 20);

add_action('user_register', function (int $user_id): void {
    $user = get_userdata($user_id);
    if (!($user instanceof WP_User)) {
        return;
    }

    $roles = array_map('translate_user_role', array_map('strval', (array) $user->roles));

    meza_activity_log_record_event([
        'category' => 'site',
        'action' => 'user_created',
        'object_id' => $user_id,
        'object_type' => 'user',
        'object_subtype' => 'account',
        'object_label' => 'User',
        'object_title' => meza_activity_log_get_user_object_title($user),
        'object_url' => meza_activity_log_get_user_edit_url($user_id),
        'details' => $roles !== [] ? ('Assigned role: ' . implode(', ', $roles) . '.') : 'User account created.',
    ]);
}, 20);

add_action('profile_update', function (int $user_id, WP_User $old_user_data): void {
    $user = get_userdata($user_id);
    if (!($user instanceof WP_User)) {
        return;
    }

    $changes = [];

    if ((string) $old_user_data->display_name !== (string) $user->display_name) {
        $changes[] = sprintf(
            'Display name changed from %s to %s',
            trim((string) $old_user_data->display_name) !== '' ? $old_user_data->display_name : 'Empty',
            trim((string) $user->display_name) !== '' ? $user->display_name : 'Empty'
        );
    }

    if ((string) $old_user_data->user_email !== (string) $user->user_email) {
        $changes[] = sprintf(
            'Email changed from %s to %s',
            trim((string) $old_user_data->user_email) !== '' ? $old_user_data->user_email : 'Empty',
            trim((string) $user->user_email) !== '' ? $user->user_email : 'Empty'
        );
    }

    if ($changes === []) {
        return;
    }

    meza_activity_log_record_event([
        'category' => 'site',
        'action' => 'user_updated',
        'object_id' => $user_id,
        'object_type' => 'user',
        'object_subtype' => 'account',
        'object_label' => 'User',
        'object_title' => meza_activity_log_get_user_object_title($user),
        'object_url' => meza_activity_log_get_user_edit_url($user_id),
        'details' => implode('. ', $changes) . '.',
    ]);
}, 20, 2);

add_action('set_user_role', function (int $user_id, string $role, array $old_roles): void {
    $user = get_userdata($user_id);
    if (!($user instanceof WP_User)) {
        return;
    }

    $old_role_labels = array_map('translate_user_role', array_map('strval', $old_roles));
    $new_role_label = $role !== '' ? translate_user_role($role) : 'None';

    meza_activity_log_record_event([
        'category' => 'site',
        'action' => 'role_changed',
        'object_id' => $user_id,
        'object_type' => 'user',
        'object_subtype' => 'role',
        'object_label' => 'User Role',
        'object_title' => meza_activity_log_get_user_object_title($user),
        'object_url' => meza_activity_log_get_user_edit_url($user_id),
        'details' => sprintf(
            'Role changed from %s to %s.',
            $old_role_labels !== [] ? implode(', ', $old_role_labels) : 'None',
            $new_role_label
        ),
    ]);
}, 20, 3);

add_action('delete_user', function (int $user_id, $reassign, WP_User $user): void {
    $details = 'User account deleted.';
    if ((int) $reassign > 0) {
        $details = 'User account deleted and reassigned to user #' . (int) $reassign . '.';
    }

    meza_activity_log_record_event([
        'category' => 'site',
        'action' => 'user_deleted',
        'object_id' => $user_id,
        'object_type' => 'user',
        'object_subtype' => 'account',
        'object_label' => 'User',
        'object_title' => meza_activity_log_get_user_object_title($user),
        'details' => $details,
    ]);
}, 20, 3);

add_action('password_reset', function (WP_User $user): void {
    meza_activity_log_record_event([
        'category' => 'site',
        'action' => 'password_reset',
        'object_id' => (int) $user->ID,
        'object_type' => 'user',
        'object_subtype' => 'security',
        'object_label' => 'User Security',
        'object_title' => meza_activity_log_get_user_object_title($user),
        'object_url' => meza_activity_log_get_user_edit_url((int) $user->ID),
        'details' => 'Password reset completed.',
    ]);
}, 20);

add_action('activated_plugin', function (string $plugin, bool $network_wide): void {
    $plugin_name = $plugin;
    if (function_exists('get_plugin_data')) {
        $plugin_file = WP_PLUGIN_DIR . '/' . ltrim($plugin, '/');
        if (is_readable($plugin_file)) {
            $plugin_data = get_plugin_data($plugin_file, false, false);
            $plugin_name = trim((string) ($plugin_data['Name'] ?? '')) ?: $plugin;
        }
    }

    meza_activity_log_record_event([
        'category' => 'site',
        'action' => 'plugin_activated',
        'object_type' => 'plugin',
        'object_subtype' => sanitize_key(dirname($plugin)),
        'object_label' => 'Plugin',
        'object_title' => $plugin_name,
        'object_url' => admin_url('plugins.php'),
        'details' => $network_wide ? 'Plugin activated network-wide.' : 'Plugin activated.',
    ]);
}, 20, 2);

add_action('deactivated_plugin', function (string $plugin, bool $network_wide): void {
    $plugin_name = $plugin;
    if (function_exists('get_plugin_data')) {
        $plugin_file = WP_PLUGIN_DIR . '/' . ltrim($plugin, '/');
        if (is_readable($plugin_file)) {
            $plugin_data = get_plugin_data($plugin_file, false, false);
            $plugin_name = trim((string) ($plugin_data['Name'] ?? '')) ?: $plugin;
        }
    }

    meza_activity_log_record_event([
        'category' => 'site',
        'action' => 'plugin_deactivated',
        'object_type' => 'plugin',
        'object_subtype' => sanitize_key(dirname($plugin)),
        'object_label' => 'Plugin',
        'object_title' => $plugin_name,
        'object_url' => admin_url('plugins.php'),
        'details' => $network_wide ? 'Plugin deactivated network-wide.' : 'Plugin deactivated.',
    ]);
}, 20, 2);

add_action('switch_theme', function ($new_name, WP_Theme $new_theme, WP_Theme $old_theme): void {
    meza_activity_log_record_event([
        'category' => 'site',
        'action' => 'theme_switched',
        'object_type' => 'theme',
        'object_subtype' => sanitize_key((string) $new_theme->get_stylesheet()),
        'object_label' => 'Theme',
        'object_title' => trim((string) $new_theme->get('Name')) ?: (string) $new_name,
        'object_url' => admin_url('themes.php'),
        'details' => sprintf(
            'Theme switched from %s to %s.',
            trim((string) $old_theme->get('Name')) ?: $old_theme->get_stylesheet(),
            trim((string) $new_theme->get('Name')) ?: $new_theme->get_stylesheet()
        ),
    ]);
}, 20, 3);

add_action('upgrader_process_complete', function ($upgrader, array $hook_extra): void {
    $type = sanitize_key((string) ($hook_extra['type'] ?? ''));
    $action = sanitize_key((string) ($hook_extra['action'] ?? ''));

    if ($action === '' || $type === '') {
        return;
    }

    if ($type === 'plugin') {
        $plugins = array_map('strval', (array) ($hook_extra['plugins'] ?? []));
        foreach ($plugins as $plugin) {
            $plugin_name = $plugin;
            if (function_exists('get_plugin_data')) {
                $plugin_file = WP_PLUGIN_DIR . '/' . ltrim($plugin, '/');
                if (is_readable($plugin_file)) {
                    $plugin_data = get_plugin_data($plugin_file, false, false);
                    $plugin_name = trim((string) ($plugin_data['Name'] ?? '')) ?: $plugin;
                }
            }

            meza_activity_log_record_event([
                'category' => 'site',
                'action' => $action === 'install' ? 'plugin_installed' : 'plugin_updated',
                'object_type' => 'plugin',
                'object_subtype' => sanitize_key(dirname($plugin)),
                'object_label' => 'Plugin',
                'object_title' => $plugin_name,
                'object_url' => admin_url('plugins.php'),
                'details' => $action === 'install' ? 'Plugin installed.' : 'Plugin updated.',
            ]);
        }
        return;
    }

    if ($type === 'theme') {
        $themes = array_map('strval', (array) ($hook_extra['themes'] ?? []));
        foreach ($themes as $stylesheet) {
            $theme = wp_get_theme($stylesheet);
            $theme_name = $theme instanceof WP_Theme
                ? (trim((string) $theme->get('Name')) ?: $stylesheet)
                : $stylesheet;

            meza_activity_log_record_event([
                'category' => 'site',
                'action' => $action === 'install' ? 'theme_installed' : 'theme_updated',
                'object_type' => 'theme',
                'object_subtype' => sanitize_key($stylesheet),
                'object_label' => 'Theme',
                'object_title' => $theme_name,
                'object_url' => admin_url('themes.php'),
                'details' => $action === 'install' ? 'Theme installed.' : 'Theme updated.',
            ]);
        }
        return;
    }

    if ($type === 'core' && $action === 'update') {
        meza_activity_log_record_event([
            'category' => 'site',
            'action' => 'core_updated',
            'object_type' => 'core',
            'object_subtype' => 'wordpress',
            'object_label' => 'WordPress Core',
            'object_title' => 'WordPress',
            'object_url' => admin_url('update-core.php'),
            'details' => 'WordPress core updated.',
        ]);
    }
}, 20, 2);

add_action('acf/save_post', function ($post_id): void {
    if (!is_string($post_id)) {
        return;
    }

    meza_activity_log_record_acf_options_page_save($post_id);
}, 40);

function meza_activity_log_get_entries(): array
{
    $entries = get_option(MEZA_ACTIVITY_LOG_OPTION, []);

    if (!is_array($entries)) {
        return [];
    }

    return array_values(array_filter($entries, 'is_array'));
}

function meza_activity_log_per_page(): int
{
    $per_page = (int) get_user_option(MEZA_ACTIVITY_LOG_PER_PAGE_OPTION);
    if ($per_page < 1) {
        $per_page = MEZA_ACTIVITY_LOG_PER_PAGE_DEFAULT;
    }

    return min(200, $per_page);
}

function meza_activity_log_get_filter_definitions(): array
{
    return [
        'all' => [
            'label' => 'All',
        ],
        'content' => [
            'label' => 'Content Activity',
            'categories' => ['content'],
        ],
        'site' => [
            'label' => 'Site Activity',
            'categories' => ['site'],
        ],
        'published' => [
            'label' => 'Published',
            'actions' => ['published'],
        ],
        'updated' => [
            'label' => 'Updated',
            'actions' => ['updated'],
        ],
        'removed' => [
            'label' => 'Removed',
            'actions' => ['trashed', 'deleted', 'restored'],
        ],
        'uploaded' => [
            'label' => 'Uploads',
            'actions' => ['uploaded'],
        ],
    ];
}

function meza_activity_log_entry_matches_filter(array $entry, array $definition): bool
{
    $category = sanitize_key((string) ($entry['category'] ?? ''));
    if (!empty($definition['categories']) && !in_array($category, array_map('strval', (array) $definition['categories']), true)) {
        return false;
    }

    $action = sanitize_key((string) ($entry['action'] ?? ''));
    if (!empty($definition['actions']) && !in_array($action, array_map('strval', (array) $definition['actions']), true)) {
        return false;
    }

    return true;
}

function meza_activity_log_filter_entries(array $entries, string $filter): array
{
    $definitions = meza_activity_log_get_filter_definitions();
    $definition = $definitions[$filter] ?? null;

    if (!is_array($definition) || $filter === 'all') {
        return $entries;
    }

    return array_values(array_filter($entries, static function ($entry) use ($definition): bool {
        return is_array($entry) && meza_activity_log_entry_matches_filter($entry, $definition);
    }));
}

function meza_activity_log_get_filter_counts(array $entries): array
{
    $counts = [];

    foreach (meza_activity_log_get_filter_definitions() as $key => $definition) {
        $counts[$key] = count(meza_activity_log_filter_entries($entries, (string) $key));
    }

    return $counts;
}

function meza_activity_log_get_today_counts(array $entries): array
{
    $today = wp_date('Y-m-d', current_time('timestamp'));
    $counts = [
        'content' => 0,
        'site' => 0,
        'published' => 0,
        'removed' => 0,
    ];

    foreach ($entries as $entry) {
        $timestamp = (int) ($entry['timestamp'] ?? 0);
        if ($timestamp < 1 || wp_date('Y-m-d', $timestamp) !== $today) {
            continue;
        }

        $category = sanitize_key((string) ($entry['category'] ?? ''));
        if ($category === 'content') {
            $counts['content']++;
        } elseif ($category === 'site') {
            $counts['site']++;
        }

        $action = sanitize_key((string) ($entry['action'] ?? ''));
        if ($action === 'published') {
            $counts['published']++;
        } elseif (in_array($action, ['trashed', 'deleted'], true)) {
            $counts['removed']++;
        }
    }

    return $counts;
}

function meza_activity_log_get_bootstrap_entries(int $limit = 20): array
{
    $entries = [];
    $post_types = get_post_types(['show_ui' => true], 'names');

    if (!is_array($post_types) || $post_types === []) {
        return [];
    }

    $recent_posts = get_posts([
        'posts_per_page' => $limit,
        'post_type' => array_values($post_types),
        'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
        'orderby' => 'modified',
        'order' => 'DESC',
        'suppress_filters' => false,
    ]);

    foreach ($recent_posts as $post) {
        if (!($post instanceof WP_Post) || !meza_activity_log_should_track_post($post)) {
            continue;
        }

        $last_editor_id = (int) get_post_meta($post->ID, '_edit_last', true);
        if ($last_editor_id < 1) {
            $last_editor_id = (int) $post->post_author;
        }

        $entries[] = [
            'timestamp' => max(0, (int) get_post_modified_time('U', false, $post)),
            'category' => 'content',
            'category_label' => meza_activity_log_get_category_label('content'),
            'action' => 'updated',
            'action_label' => 'Updated',
            'user_id' => $last_editor_id,
            'user_name' => meza_activity_log_get_user_display_name($last_editor_id),
            'object_id' => (int) $post->ID,
            'object_type' => 'post',
            'object_subtype' => (string) $post->post_type,
            'object_label' => meza_activity_log_get_post_type_label((string) $post->post_type),
            'object_title' => meza_activity_log_get_post_title($post),
            'object_url' => (string) get_edit_post_link($post->ID, 'raw'),
            'status' => (string) $post->post_status,
            'status_label' => meza_activity_log_get_post_status_label((string) $post->post_status),
            'details' => 'Inferred from the current post state until live events accumulate.',
            'is_inferred' => true,
        ];
    }

    return $entries;
}

function meza_activity_log_get_current_filter(): string
{
    $filter = isset($_GET['activity_filter']) ? sanitize_key(wp_unslash($_GET['activity_filter'])) : 'all';
    $definitions = meza_activity_log_get_filter_definitions();
    if (!isset($definitions[$filter])) {
        return 'all';
    }

    return $filter;
}

function meza_activity_log_get_current_page_number(): int
{
    return isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
}

function meza_activity_log_build_admin_url(array $overrides = [], array $remove = []): string
{
    $args = [
        'page' => MEZA_ACTIVITY_LOG_PAGE_SLUG,
    ];

    $current_filter = meza_activity_log_get_current_filter();
    if ($current_filter !== 'all') {
        $args['activity_filter'] = $current_filter;
    }

    $current_page = meza_activity_log_get_current_page_number();
    if ($current_page > 1) {
        $args['paged'] = $current_page;
    }

    foreach ($remove as $key) {
        unset($args[$key]);
    }

    foreach ($overrides as $key => $value) {
        if (
            $value === ''
            || $value === null
            || $value === false
            || ($key === 'activity_filter' && $value === 'all')
            || ($key === 'paged' && (int) $value < 2)
        ) {
            unset($args[$key]);
            continue;
        }

        $args[$key] = $value;
    }

    $admin_file = meza_activity_log_get_parent_menu_slug() === 'index.php'
        ? 'index.php'
        : 'admin.php';

    return add_query_arg($args, admin_url($admin_file));
}

function meza_activity_log_get_list_table_instance(array $entries, bool $using_bootstrap_entries, string $current_filter): WP_List_Table
{
    if (!class_exists('WP_List_Table')) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
    }

    if (!class_exists('Meza_Activity_Log_List_Table')) {
        class Meza_Activity_Log_List_Table extends WP_List_Table
        {
            private array $entries;
            private bool $using_bootstrap_entries;
            private string $current_filter;
            private array $counts = [];

            public function __construct(array $entries, bool $using_bootstrap_entries, string $current_filter)
            {
                parent::__construct([
                    'singular' => 'activity entry',
                    'plural' => 'activity entries',
                    'ajax' => false,
                ]);

                $this->entries = $entries;
                $this->using_bootstrap_entries = $using_bootstrap_entries;
                $this->current_filter = $current_filter;
                $this->counts = meza_activity_log_get_filter_counts($entries);
            }

            public function get_columns(): array
            {
                return [
                    'when' => 'When',
                    'user' => 'User',
                    'action' => 'Action',
                    'content' => 'Item',
                    'details' => 'Details',
                ];
            }

            protected function get_default_primary_column_name(): string
            {
                return 'content';
            }

            public function get_views(): array
            {
                $views = [];

                foreach (meza_activity_log_get_filter_definitions() as $key => $definition) {
                    $url = meza_activity_log_build_admin_url([
                        'activity_filter' => $key,
                    ], ['paged']);

                    $class = $this->current_filter === $key ? ' class="current" aria-current="page"' : '';
                    $views[$key] = sprintf(
                        '<a href="%s"%s>%s <span class="count">(%s)</span></a>',
                        esc_url($url),
                        $class,
                        esc_html((string) $definition['label']),
                        esc_html(number_format_i18n((int) ($this->counts[$key] ?? 0)))
                    );
                }

                return $views;
            }

            public function no_items(): void
            {
                if ($this->using_bootstrap_entries) {
                    echo 'No recent activity snapshot is available yet.';
                    return;
                }

                echo 'No matching activity yet.';
            }

            public function prepare_items(): void
            {
                $this->_column_headers = [$this->get_columns(), [], []];

                $filtered_entries = meza_activity_log_filter_entries($this->entries, $this->current_filter);
                $total_items = count($filtered_entries);
                $per_page = meza_activity_log_per_page();
                $current_page = $this->get_pagenum();
                $offset = max(0, ($current_page - 1) * $per_page);

                $this->items = array_slice($filtered_entries, $offset, $per_page);

                $this->set_pagination_args([
                    'total_items' => $total_items,
                    'per_page' => $per_page,
                    'total_pages' => $total_items > 0 ? (int) ceil($total_items / $per_page) : 0,
                ]);
            }

            public function column_default($item, $column_name)
            {
                return '&mdash;';
            }

            public function column_when(array $item): string
            {
                $timestamp = (int) ($item['timestamp'] ?? 0);
                if ($timestamp < 1) {
                    return 'Unknown time';
                }

                $formatted_time = wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
                $relative_time = human_time_diff($timestamp, current_time('timestamp')) . ' ago';

                return '<strong>' . esc_html($formatted_time) . '</strong><br><span class="description">' . esc_html($relative_time) . '</span>';
            }

            public function column_user(array $item): string
            {
                $user_name = trim((string) ($item['user_name'] ?? ''));
                if ($user_name === '' || strcasecmp($user_name, 'System') === 0) {
                    return '&mdash;';
                }

                return esc_html($user_name);
            }

            public function column_action(array $item): string
            {
                $action = sanitize_key((string) ($item['action'] ?? ''));
                $action_label = (string) ($item['action_label'] ?? meza_activity_log_get_action_label($action));

                return '<span class="dashicons ' . esc_attr(meza_activity_log_get_action_dashicon($action)) . '" aria-hidden="true"></span> ' . esc_html($action_label);
            }

            public function column_content(array $item): string
            {
                $title = trim((string) ($item['object_title'] ?? 'Untitled item'));
                $url = trim((string) ($item['object_url'] ?? ''));
                $object_label = trim((string) ($item['object_label'] ?? 'Item'));
                $status_label = trim((string) ($item['status_label'] ?? ''));
                $category_label = trim((string) ($item['category_label'] ?? ''));
                $is_inferred = !empty($item['is_inferred']);

                $meta_parts = array_filter([
                    $category_label,
                    $object_label,
                    $status_label,
                    $is_inferred ? 'Snapshot' : '',
                ], static function ($value): bool {
                    return trim((string) $value) !== '';
                });

                $content = $url !== ''
                    ? '<strong><a href="' . esc_url($url) . '">' . esc_html($title) . '</a></strong>'
                    : '<strong>' . esc_html($title) . '</strong>';

                if ($meta_parts !== []) {
                    $content .= '<br><span class="description">' . esc_html(implode(' - ', $meta_parts)) . '</span>';
                }

                return $content;
            }

            public function column_details(array $item): string
            {
                $details = trim((string) ($item['details'] ?? ''));
                return $details !== '' ? esc_html($details) : '&mdash;';
            }
        }
    }

    return new Meza_Activity_Log_List_Table($entries, $using_bootstrap_entries, $current_filter);
}

function meza_activity_log_render_summary_table(array $today_counts): void
{
    ?>
    <table class="widefat striped" style="max-width: 820px; margin-bottom: 16px;">
        <thead>
            <tr>
                <th>Content today</th>
                <th>Site today</th>
                <th>Published today</th>
                <th>Removed today</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= esc_html(number_format_i18n((int) $today_counts['content'])) ?></td>
                <td><?= esc_html(number_format_i18n((int) $today_counts['site'])) ?></td>
                <td><?= esc_html(number_format_i18n((int) $today_counts['published'])) ?></td>
                <td><?= esc_html(number_format_i18n((int) $today_counts['removed'])) ?></td>
            </tr>
        </tbody>
    </table>
    <?php
}

function meza_render_activity_log_dashboard_page(): void
{
    if (!meza_activity_log_can_view()) {
        wp_die('You do not have permission to view this page.');
    }

    $filter = meza_activity_log_get_current_filter();
    $stored_entries = meza_activity_log_get_entries();
    $using_bootstrap_entries = ($stored_entries === []);
    $all_entries = $using_bootstrap_entries ? meza_activity_log_get_bootstrap_entries(MEZA_ACTIVITY_LOG_LIMIT) : $stored_entries;
    $today_counts = meza_activity_log_get_today_counts($all_entries);
    $list_table = meza_activity_log_get_list_table_instance($all_entries, $using_bootstrap_entries, $filter);
    $list_table->prepare_items();
    ?>
    <div class="wrap">
        <h1>Activity</h1>
        <p>Review recent content changes separately from broader site-management activity.</p>

        <?php if ($using_bootstrap_entries) : ?>
            <div class="notice notice-info inline">
                <p>Live logging starts with the next real editor action. These first rows are a recent content snapshot so the prototype is useful right away.</p>
            </div>
        <?php endif; ?>

        <?php meza_activity_log_render_summary_table($today_counts); ?>

        <form method="get">
            <input type="hidden" name="page" value="<?= esc_attr(MEZA_ACTIVITY_LOG_PAGE_SLUG) ?>">
            <?php if ($filter !== 'all') : ?>
                <input type="hidden" name="activity_filter" value="<?= esc_attr($filter) ?>">
            <?php endif; ?>

            <?php $list_table->views(); ?>
            <?php $list_table->display(); ?>
        </form>
    </div>
    <?php
}
