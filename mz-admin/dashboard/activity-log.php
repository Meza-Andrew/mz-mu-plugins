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
    return 'manage_options';
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
    ];

    return $map[$action] ?? 'dashicons-admin-post';
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

function meza_activity_log_record_post_event(string $action, WP_Post $post, array $overrides = []): void
{
    if (!meza_activity_log_should_track_post($post)) {
        return;
    }

    $user_id = isset($overrides['user_id']) ? (int) $overrides['user_id'] : get_current_user_id();
    $timestamp = isset($overrides['timestamp']) ? (int) $overrides['timestamp'] : current_time('timestamp');
    $status = isset($overrides['status']) ? sanitize_key((string) $overrides['status']) : (string) $post->post_status;
    $url = get_edit_post_link($post->ID, 'raw');

    meza_activity_log_store_entry([
        'timestamp' => $timestamp,
        'action' => sanitize_key($action),
        'action_label' => meza_activity_log_get_action_label($action),
        'user_id' => $user_id,
        'user_name' => meza_activity_log_get_user_display_name($user_id, (string) ($overrides['user_name'] ?? '')),
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
            'actions' => [],
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

function meza_activity_log_filter_entries(array $entries, string $filter): array
{
    $definitions = meza_activity_log_get_filter_definitions();
    if (!isset($definitions[$filter]) || $definitions[$filter]['actions'] === []) {
        return $entries;
    }

    $allowed_actions = array_map('strval', (array) $definitions[$filter]['actions']);

    return array_values(array_filter($entries, function ($entry) use ($allowed_actions) {
        $action = sanitize_key((string) ($entry['action'] ?? ''));
        return in_array($action, $allowed_actions, true);
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
        'updated' => 0,
        'published' => 0,
        'removed' => 0,
    ];

    foreach ($entries as $entry) {
        $timestamp = (int) ($entry['timestamp'] ?? 0);
        if ($timestamp < 1 || wp_date('Y-m-d', $timestamp) !== $today) {
            continue;
        }

        $action = sanitize_key((string) ($entry['action'] ?? ''));
        if ($action === 'updated') {
            $counts['updated']++;
        } elseif ($action === 'published') {
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
                    'content' => 'Content',
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
                $title = trim((string) ($item['object_title'] ?? 'Untitled content'));
                $url = trim((string) ($item['object_url'] ?? ''));
                $object_label = trim((string) ($item['object_label'] ?? 'Content'));
                $status_label = trim((string) ($item['status_label'] ?? ''));
                $is_inferred = !empty($item['is_inferred']);

                $meta_parts = array_filter([
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
    <table class="widefat striped" style="max-width: 720px; margin-bottom: 16px;">
        <thead>
            <tr>
                <th>Updated today</th>
                <th>Published today</th>
                <th>Removed today</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= esc_html(number_format_i18n((int) $today_counts['updated'])) ?></td>
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
        <p>Latest editorial activity across posts, pages, media, and editable content types.</p>

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
