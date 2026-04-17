<?php

/** ================================
 *  EVENT ADMIN SORTING
 *  ================================ */

// Keep a raw sortable version of start_datetime in Y-m-d H:i:s.
add_action('save_post_tribe_events', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;

    $pretty = '';

    foreach (['_EventStartDate', 'start_datetime', 'start_date', '_event_start_date'] as $meta_key) {
        $value = get_post_meta($post_id, $meta_key, true);
        if (is_string($value) && trim($value) !== '') {
            $pretty = trim($value);
            break;
        }
    }

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

function meza_get_review_admin_column_date_raw_value(int $post_id): string
{
    $post_id = (int) $post_id;
    if ($post_id <= 0) return '';

    if (function_exists('get_field')) {
        $acf_date = get_field('date', $post_id);
        if (is_string($acf_date) && trim($acf_date) !== '') {
            return trim($acf_date);
        }
    }

    $meta_date = get_post_meta($post_id, 'date', true);
    return is_string($meta_date) ? trim($meta_date) : '';
}

function meza_get_review_admin_column_date_timestamp(int $post_id): int
{
    $raw_value = meza_get_review_admin_column_date_raw_value($post_id);
    if ($raw_value === '') return 0;

    return meza_get_admin_date_timestamp_from_raw_value($raw_value);
}

function meza_get_admin_date_timestamp_from_raw_value(string $raw_value): int
{
    $raw_value = trim($raw_value);
    if ($raw_value === '') return 0;

    if (preg_match('/^\d{8}$/', $raw_value)) {
        $year = (int) substr($raw_value, 0, 4);
        $month = (int) substr($raw_value, 4, 2);
        $day = (int) substr($raw_value, 6, 2);

        if (checkdate($month, $day, $year)) {
            $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(wp_timezone_string() ?: 'UTC');
            $datetime = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day), $timezone);
            return $datetime->getTimestamp();
        }
    }

    $timestamp = strtotime($raw_value);
    return $timestamp !== false ? (int) $timestamp : 0;
}

function meza_admin_column_identifier_supports_date_value(string $identifier): bool
{
    $normalized = meza_normalize_admin_link_column_identifier($identifier);
    if ($normalized === '') {
        return false;
    }

    if (in_array($normalized, ['modified', 'last modified', 'published'], true)) {
        return false;
    }

    return str_contains($normalized, 'date');
}

function meza_get_admin_date_identifier_flags(array $identifiers): array
{
    $normalized = array_map('meza_normalize_admin_link_column_identifier', array_values(array_filter(array_map('strval', $identifiers))));
    $haystack = ' ' . implode(' ', $normalized) . ' ';

    $wants_end = str_contains($haystack, ' end ')
        || str_contains($haystack, ' expiration ')
        || str_contains($haystack, ' expiry ')
        || str_contains($haystack, ' expires ');
    $wants_start = !$wants_end && (str_contains($haystack, ' start ') || str_contains($haystack, ' begins ') || str_contains($haystack, ' from '));

    return [
        'start' => $wants_start,
        'end' => $wants_end,
    ];
}

function meza_get_admin_date_candidate_meta_keys(array $identifiers): array
{
    $candidates = [];
    $flags = meza_get_admin_date_identifier_flags($identifiers);

    foreach ($identifiers as $identifier) {
        $identifier = trim((string) $identifier);
        if ($identifier === '' || str_starts_with($identifier, '_')) {
            continue;
        }

        if (!meza_admin_column_identifier_supports_date_value($identifier)) {
            continue;
        }

        $candidates[] = $identifier;

        $normalized = meza_normalize_admin_link_column_identifier($identifier);
        if ($normalized !== '') {
            $candidates[] = str_replace(' ', '_', $normalized);
            $candidates[] = str_replace(' ', '', $normalized);
        }
    }

    if ($flags['end']) {
        $candidates = array_merge($candidates, [
            'date_end',
            'end_date',
            'end_datetime',
            'expiration_date',
            'expiry_date',
            'expires_on',
            'expires_at',
        ]);
    } elseif ($flags['start']) {
        $candidates = array_merge($candidates, [
            'date_start',
            'start_date',
            'start_datetime',
            'date',
        ]);
    } else {
        $candidates = array_merge($candidates, [
            'date',
            'date_start',
            'start_date',
            'start_datetime',
            'event_date',
            'promo_date',
            'review_date',
            'date_end',
            'end_date',
            'end_datetime',
            'expiration_date',
            'expiry_date',
            'expires_on',
            'expires_at',
        ]);
    }

    return array_values(array_unique(array_filter(array_map(
        static function ($key): string {
            $key = trim((string) $key);
            return str_starts_with($key, '_') ? '' : $key;
        },
        $candidates
    ))));
}

function meza_get_post_admin_date_raw_value_from_meta_keys(int $post_id, array $meta_keys): string
{
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return '';
    }

    foreach ($meta_keys as $meta_key) {
        $meta_key = trim((string) $meta_key);
        if ($meta_key === '') {
            continue;
        }

        if (function_exists('get_field')) {
            $acf_value = get_field($meta_key, $post_id);
            if (is_string($acf_value) && trim($acf_value) !== '') {
                return trim($acf_value);
            }
        }

        $meta_value = get_post_meta($post_id, $meta_key, true);
        if (is_string($meta_value) && trim($meta_value) !== '') {
            return trim($meta_value);
        }
    }

    return '';
}

function meza_resolve_admin_date_meta_keys_for_post(int $post_id, array $identifiers): array
{
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $meta = get_post_meta($post_id);
    $meta_keys = is_array($meta) ? array_keys($meta) : [];
    $candidate_keys = meza_get_admin_date_candidate_meta_keys($identifiers);
    $resolved = [];

    foreach ($candidate_keys as $candidate_key) {
        if (in_array($candidate_key, $meta_keys, true)) {
            $resolved[] = $candidate_key;
        }
    }

    if ($resolved !== []) {
        return array_values(array_unique(array_filter(array_map('strval', $resolved))));
    }

    $flags = meza_get_admin_date_identifier_flags($identifiers);
    foreach ($meta_keys as $meta_key) {
        $meta_key = trim((string) $meta_key);
        if ($meta_key === '' || str_starts_with($meta_key, '_') || !str_contains(strtolower($meta_key), 'date')) {
            continue;
        }

        $normalized = meza_normalize_admin_link_column_identifier($meta_key);
        if ($flags['end'] && !str_contains($normalized, 'end') && !str_contains($normalized, 'expir') && !str_contains($normalized, 'expires')) {
            continue;
        }

        if ($flags['start'] && !str_contains($normalized, 'start')) {
            continue;
        }

        $resolved[] = $meta_key;
    }

    return array_values(array_unique(array_filter(array_map('strval', $resolved))));
}

function meza_resolve_admin_date_sort_meta_key(string $post_type, array $identifiers): string
{
    $post_type = sanitize_key($post_type);
    if ($post_type === '') {
        return '';
    }

    $sample_posts = get_posts([
        'post_type' => $post_type,
        'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
        'posts_per_page' => 1,
        'fields' => 'ids',
        'orderby' => 'date',
        'order' => 'DESC',
        'suppress_filters' => true,
    ]);

    $sample_post_id = (int) ($sample_posts[0] ?? 0);
    if ($sample_post_id <= 0) {
        return '';
    }

    $resolved_keys = meza_resolve_admin_date_meta_keys_for_post($sample_post_id, $identifiers);
    return (string) ($resolved_keys[0] ?? '');
}

function meza_get_post_admin_date_display_value(int $post_id, array $identifiers): string
{
    $resolved_keys = meza_resolve_admin_date_meta_keys_for_post($post_id, $identifiers);
    $flags = meza_get_admin_date_identifier_flags($identifiers);
    $format = (string) (get_option('date_format') ?: 'F j, Y');

    $start_timestamp = meza_get_admin_date_timestamp_from_raw_value(meza_get_post_admin_date_raw_value_from_meta_keys($post_id, array_values(array_unique(array_merge(
        array_intersect($resolved_keys, ['date_start', 'start_date', 'start_datetime']),
        ['date_start', 'start_date', 'start_datetime']
    )))));
    $end_timestamp = meza_get_admin_date_timestamp_from_raw_value(meza_get_post_admin_date_raw_value_from_meta_keys($post_id, array_values(array_unique(array_merge(
        array_intersect($resolved_keys, ['date_end', 'end_date', 'end_datetime', 'expiration_date', 'expiry_date', 'expires_on', 'expires_at']),
        ['date_end', 'end_date', 'end_datetime', 'expiration_date', 'expiry_date', 'expires_on', 'expires_at']
    )))));

    if ($flags['end'] && $end_timestamp > 0) {
        return wp_date($format, $end_timestamp);
    }

    if ($flags['start'] && $start_timestamp > 0) {
        return wp_date($format, $start_timestamp);
    }

    if (!$flags['start'] && !$flags['end'] && $start_timestamp > 0) {
        if ($end_timestamp > 0 && $end_timestamp !== $start_timestamp) {
            return wp_date($format, $start_timestamp) . ' - ' . wp_date($format, $end_timestamp);
        }

        return wp_date($format, $start_timestamp);
    }

    $primary_timestamp = meza_get_admin_date_timestamp_from_raw_value(meza_get_post_admin_date_raw_value_from_meta_keys($post_id, $resolved_keys));
    if ($primary_timestamp > 0) {
        return wp_date($format, $primary_timestamp);
    }

    if ($end_timestamp > 0) {
        return wp_date($format, $end_timestamp);
    }

    return '';
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

    $post_type_object = meza_get_cached_post_type_object($post_type);
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
        'organizer',
        'organization',
        'partner',
        'profile',
        'product',
        'service',
        'sign',
        'sponsor',
        'team-member',
        'venue',
    ];
}

function meza_get_menu_order_admin_column_default_visible_post_type_fragments(): array
{
    return [
        'organizer',
        'venue',
        'partner',
        'sponsor',
    ];
}

function meza_get_organization_like_post_types(): array
{
    $post_types = [
        'organization',
        'partner',
        'partners',
        'sponsor',
        'sponsors',
    ];

    foreach (meza_admin_get_post_type_display_hints() as $post_type => $hints) {
        if (!is_array($hints) || empty($hints['organization_like'])) {
            continue;
        }

        $post_type = trim((string) $post_type);
        if ($post_type === '') {
            continue;
        }

        $post_types[] = $post_type;
    }

    return array_values(array_unique($post_types));
}

function meza_post_type_is_organization_like(string $post_type): bool
{
    return in_array(trim($post_type), meza_get_organization_like_post_types(), true);
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
    $post_type = trim($post_type);
    if ($post_type === '') return false;

    $seeded_columns = meza_get_seeded_admin_column_defaults_for_post_type($post_type);
    if (
        $seeded_columns !== []
        && !array_key_exists('date', $seeded_columns)
        && !array_key_exists('mz_published', $seeded_columns)
    ) {
        return true;
    }

    if (in_array($post_type, meza_get_menu_order_admin_column_default_visible_post_types(), true)) {
        return true;
    }

    foreach (meza_get_menu_order_admin_column_default_visible_post_type_fragments() as $fragment) {
        if ($fragment !== '' && str_contains($post_type, $fragment)) {
            return true;
        }
    }

    return false;
}

function meza_post_type_organization_link_admin_column_is_default_visible(string $post_type): bool
{
    return trim($post_type) === 'organization';
}

function meza_review_link_admin_column_is_default_visible(string $post_type): bool
{
    return false;
}

function meza_post_type_is_profile_like(string $post_type): bool
{
    return in_array(trim($post_type), ['profile', 'team-member'], true);
}

if (!function_exists('meza_admin_post_type_preserves_custom_admin_column_order')) {
    function meza_admin_post_type_preserves_custom_admin_column_order(string $post_type): bool
    {
        $post_type = trim($post_type);
        if ($post_type === '') {
            return false;
        }

        $hints = meza_admin_get_post_type_display_hints();

        return !empty($hints[$post_type]['preserve_custom_admin_column_order']);
    }
}

if (!function_exists('meza_admin_get_post_type_display_hints')) {
    function meza_admin_get_post_type_display_hints(): array
    {
        $defaults = [
            'tribe_events' => [
                'preserve_custom_admin_column_order' => true,
                'taxonomy_column_aliases' => [
                    'tribe_events_cat' => ['events-cats'],
                ],
            ],
            'tribe_venue' => [
                'organization_like' => true,
            ],
            'tribe_organizer' => [
                'organization_like' => true,
            ],
        ];

        $hints = apply_filters('meza_admin_post_type_display_hints', $defaults);

        return is_array($hints) ? $hints : $defaults;
    }
}

if (!function_exists('meza_admin_get_post_type_taxonomy_column_aliases')) {
    function meza_admin_get_post_type_taxonomy_column_aliases(string $post_type, string $taxonomy_name): array
    {
        $post_type = trim($post_type);
        $taxonomy_name = trim($taxonomy_name);

        if ($post_type === '' || $taxonomy_name === '') {
            return [];
        }

        $hints = meza_admin_get_post_type_display_hints();
        $aliases = $hints[$post_type]['taxonomy_column_aliases'][$taxonomy_name] ?? [];

        return meza_admin_normalize_rule_string_list($aliases);
    }
}

if (!function_exists('meza_get_taxonomy_admin_column_preferred_keys')) {
    function meza_get_taxonomy_admin_column_preferred_keys(string $post_type, string $taxonomy_name): array
    {
        $post_type = trim($post_type);
        $taxonomy_name = trim($taxonomy_name);

        if ($post_type === '' || $taxonomy_name === '') {
            return [];
        }

        if ($post_type === 'post' && $taxonomy_name === 'category') {
            return ['categories', 'taxonomy-category', 'category'];
        }

        return array_values(array_unique(array_filter(array_merge(
            meza_admin_get_post_type_taxonomy_column_aliases($post_type, $taxonomy_name),
            ['taxonomy-' . $taxonomy_name, $taxonomy_name]
        ))));
    }
}

function meza_page_marketing_admin_columns_are_default_visible(string $post_type): bool
{
    if (!meza_acf_fields_are_available()) {
        return false;
    }

    return in_array(trim($post_type), ['page', 'service'], true);
}

function meza_post_type_uses_native_admin_columns(string $post_type): bool
{
    $post_type = trim($post_type);
    if ($post_type === '') return false;

    return meza_post_type_supports_menu_order_admin_column($post_type);
}

function meza_acf_fields_are_available(): bool
{
    return function_exists('get_field');
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
    return true;
}

add_filter('disable_categories_dropdown', function ($disable, $post_type) {
    if ((string) $post_type !== 'post') {
        return $disable;
    }

    return meza_should_show_posts_categories_column() ? $disable : true;
}, 200, 2);

add_filter('manage_posts_columns', function ($columns, $post_type) {
    if ((string) $post_type !== 'post') {
        return $columns;
    }

    return meza_ensure_taxonomy_admin_columns(is_array($columns) ? $columns : [], 'post');
}, 1000, 2);

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

add_filter('pdfemb_tasks_admin_hide_as_menu', function ($hide_as_menu) {
    if (current_user_can('manage_options') && !meza_is_site_manager_user(wp_get_current_user())) {
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

    if (meza_post_type_is_profile_like($post_type)) {
        return __('Photo');
    }

    if (meza_post_type_is_organization_like($post_type)) {
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
    if (meza_post_type_is_profile_like($post_type) || meza_post_type_is_organization_like($post_type)) return false;

    return ($post_type === 'post' || $post_type === 'page' || meza_post_type_has_permalink($post_type));
}

function meza_get_post_type_summary_admin_column_label(string $post_type): string
{
    if (meza_post_type_is_profile_like($post_type)) {
        return __('Short Bio');
    }

    return in_array(trim($post_type), ['cta', 'form'], true) ? __('Subhead') : __('Summary');
}

function meza_post_type_shows_share_text_admin_columns(string $post_type): bool
{
    $post_type = trim($post_type);
    if ($post_type === '' || meza_is_acf_admin_post_type($post_type)) {
        return false;
    }

    return meza_post_type_has_permalink($post_type) && meza_post_type_is_visible_in_admin_menu($post_type);
}

function meza_get_taxonomy_admin_column_sort_labels(string $post_type): array
{
    static $labels_cache = [];

    $post_type = trim($post_type);
    if ($post_type === '') {
        return [];
    }

    if (array_key_exists($post_type, $labels_cache)) {
        return $labels_cache[$post_type];
    }

    $labels = [];
    $taxonomies = meza_get_cached_object_taxonomies($post_type, 'objects');

    if (!is_array($taxonomies)) {
        return [];
    }

    foreach ($taxonomies as $taxonomy_name => $taxonomy) {
        if (!($taxonomy instanceof WP_Taxonomy) || empty($taxonomy->show_ui)) {
            continue;
        }

        $column_label = trim(wp_strip_all_tags((string) ($taxonomy->labels->name ?? '')));
        if ($column_label === '') {
            $column_label = ucwords(str_replace(['-', '_'], ' ', (string) $taxonomy_name));
        }

        $tokens = [
            (string) $taxonomy_name,
            'taxonomy-' . (string) $taxonomy_name,
            strtolower($column_label),
        ];

        $singular_label = trim(wp_strip_all_tags((string) ($taxonomy->labels->singular_name ?? '')));
        if ($singular_label !== '') {
            $tokens[] = strtolower($singular_label);
        }

        foreach (meza_admin_get_post_type_taxonomy_column_aliases($post_type, (string) $taxonomy_name) as $alias) {
            $tokens[] = strtolower(trim((string) $alias));
        }

        foreach (array_values(array_unique(array_filter(array_map('strtolower', array_map('trim', $tokens))))) as $token) {
            $labels[$token] = $column_label;
        }
    }

    if ($post_type === 'post' && meza_should_show_posts_categories_column()) {
        foreach (['categories', 'taxonomy-category', 'category'] as $token) {
            $labels[$token] = __('Categories');
        }
    }

    $labels_cache[$post_type] = $labels;

    return $labels_cache[$post_type];
}

function meza_ensure_taxonomy_admin_columns(array $columns, string $post_type): array
{
    if (!is_array($columns)) {
        return [];
    }

    $post_type = trim($post_type);
    if ($post_type === '') {
        return $columns;
    }

    $taxonomies = meza_get_cached_object_taxonomies($post_type, 'objects');
    if (!is_array($taxonomies)) {
        return $columns;
    }

    foreach ($taxonomies as $taxonomy_name => $taxonomy) {
        if (!($taxonomy instanceof WP_Taxonomy) || empty($taxonomy->show_ui)) {
            continue;
        }

        $preferred_key = 'taxonomy-' . (string) $taxonomy_name;
        $candidate_keys = meza_get_taxonomy_admin_column_preferred_keys($post_type, (string) $taxonomy_name);

        $already_exists = false;
        foreach ($candidate_keys as $candidate_key) {
            if ($candidate_key !== '' && array_key_exists($candidate_key, $columns)) {
                $already_exists = true;
                break;
            }
        }

        if ($already_exists) {
            continue;
        }

        $column_label = trim(wp_strip_all_tags((string) ($taxonomy->labels->name ?? '')));
        if ($column_label === '') {
            $column_label = ucwords(str_replace(['-', '_'], ' ', (string) $taxonomy_name));
        }

        $columns[$preferred_key] = $column_label;
    }

    return $columns;
}

function meza_get_taxonomy_admin_column_keys_for_post_type(string $post_type): array
{
    $post_type = trim($post_type);
    if ($post_type === '') {
        return [];
    }

    $taxonomies = get_object_taxonomies($post_type, 'objects');
    if (!is_array($taxonomies)) {
        return [];
    }

    $keys = [];

    foreach ($taxonomies as $taxonomy_name => $taxonomy) {
        if (!($taxonomy instanceof WP_Taxonomy) || empty($taxonomy->show_ui)) {
            continue;
        }

        $keys = array_merge($keys, meza_get_taxonomy_admin_column_preferred_keys($post_type, (string) $taxonomy_name));
    }

    return array_values(array_unique(array_filter(array_map('strval', $keys))));
}

function meza_get_taxonomy_admin_column_width_selectors(string $post_type): array
{
    $post_type = trim($post_type);
    if ($post_type === '') {
        return [];
    }

    $selectors = [];

    foreach (meza_get_taxonomy_admin_column_keys_for_post_type($post_type) as $key) {
        $key = trim((string) $key);
        if ($key === '') {
            continue;
        }

        $selectors[] = '.wp-list-table th#' . $key;
        $selectors[] = '.wp-list-table th.column-' . $key;
        $selectors[] = '.wp-list-table td.column-' . $key;

        if (!str_starts_with($key, 'taxonomy-')) {
            $selectors[] = '.wp-list-table th.column-taxonomy-' . $key;
            $selectors[] = '.wp-list-table td.column-taxonomy-' . $key;
        }
    }

    return array_values(array_unique($selectors));
}

function meza_post_type_uses_admin_columns_layout(string $post_type): bool
{
    $post_type = trim($post_type);
    if ($post_type === '') {
        return false;
    }

    if (!is_admin()) {
        return false;
    }

    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if (!function_exists('is_plugin_active') || !is_plugin_active('codepress-admin-columns/codepress-admin-columns.php')) {
        return false;
    }

    // TEC's Events list table is the one admin screen where the managed-columns
    // path has been unstable. Let it use the simpler core list-table flow.
    if ($post_type === 'tribe_events') {
        return false;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    return $screen instanceof WP_Screen
        && $screen->base === 'edit'
        && (string) ($screen->post_type ?? '') === $post_type;
}

function meza_get_compact_date_admin_column_width_selectors(string $post_type): array
{
    $post_type = trim($post_type);
    if ($post_type === '') {
        return [];
    }

    $selectors = [];
    $candidate_keys = ['start-date', 'end-date', 'start_date', 'end_date'];

    foreach (array_values(array_unique($candidate_keys)) as $key) {
        $selectors[] = '.wp-list-table th#' . $key;
        $selectors[] = '.wp-list-table th.column-' . $key;
        $selectors[] = '.wp-list-table td.column-' . $key;
    }

    return array_values(array_unique($selectors));
}

function meza_get_current_admin_column_width_selectors(array $columns, array $tokens): array
{
    if (!is_array($columns) || $columns === [] || $tokens === []) {
        return [];
    }

    $selectors = [];

    foreach ($columns as $key => $label) {
        $key = trim((string) $key);
        if ($key === '' || !meza_admin_column_matches_tokens($key, $label, $tokens)) {
            continue;
        }

        $selectors[] = '.wp-list-table th#' . $key;
        $selectors[] = '.wp-list-table th.column-' . $key;
        $selectors[] = '.wp-list-table td.column-' . $key;
    }

    return array_values(array_unique($selectors));
}

function meza_get_event_date_admin_column_keys(array $columns): array
{
    if (!is_array($columns)) {
        return ['start' => '', 'end' => ''];
    }

    $resolved = [
        'start' => '',
        'end' => '',
    ];

    foreach ($columns as $key => $label) {
        $key = (string) $key;

        if ($resolved['start'] === '' && meza_admin_column_matches_tokens($key, $label, ['start-date', 'start_date', 'start date'])) {
            $resolved['start'] = $key;
            continue;
        }

        if ($resolved['end'] === '' && meza_admin_column_matches_tokens($key, $label, ['end-date', 'end_date', 'end date'])) {
            $resolved['end'] = $key;
        }
    }

    if ($resolved['start'] === '') {
        foreach ($columns as $key => $label) {
            if (meza_admin_column_matches_tokens((string) $key, $label, ['date'])) {
                $resolved['start'] = (string) $key;
                break;
            }
        }
    }

    return $resolved;
}

function meza_combine_event_date_admin_columns(array $columns, string $post_type): array
{
    if (!is_array($columns)) {
        return [];
    }

    if (!meza_is_event_post_type($post_type)) {
        return $columns;
    }

    $date_keys = meza_get_event_date_admin_column_keys($columns);
    $start_key = (string) ($date_keys['start'] ?? '');
    $end_key = (string) ($date_keys['end'] ?? '');

    if ($start_key === '' || $end_key === '' || !array_key_exists($start_key, $columns) || !array_key_exists($end_key, $columns)) {
        return $columns;
    }

    $columns[$start_key] = __('Date');
    unset($columns[$end_key]);

    return $columns;
}

function meza_get_event_date_admin_column_runtime_keys(array $columns): array
{
    if (!is_array($columns) || $columns === []) {
        return [];
    }

    $keys = [];
    $resolved = meza_get_event_date_admin_column_keys($columns);

    $start_key = trim((string) ($resolved['start'] ?? ''));
    if ($start_key !== '') {
        $keys[] = $start_key;
    }

    foreach (meza_get_admin_column_keys_matching_tokens($columns, ['date', 'start-date', 'start_date', 'start date']) as $key) {
        $keys[] = $key;
    }

    return array_values(array_unique(array_filter(array_map('strval', $keys))));
}

function meza_get_review_date_admin_column_runtime_keys(array $columns): array
{
    if (!is_array($columns) || $columns === []) {
        return [];
    }

    $keys = meza_get_admin_column_keys_matching_tokens($columns, ['date', 'review date']);

    return array_values(array_unique(array_filter(array_map('strval', $keys))));
}

function meza_get_generic_date_admin_column_runtime_keys(array $columns): array
{
    if (!is_array($columns) || $columns === []) {
        return [];
    }

    $keys = [];

    foreach ($columns as $key => $label) {
        $key = trim((string) $key);
        if ($key === '' || in_array($key, ['mz_modified', 'modified', 'mz_published', 'published'], true)) {
            continue;
        }

        if (
            meza_admin_column_identifier_supports_date_value($key)
            || meza_admin_column_identifier_supports_date_value((string) $label)
        ) {
            $keys[] = $key;
        }
    }

    return array_values(array_unique(array_filter(array_map('strval', $keys))));
}

function meza_get_age_group_admin_column_keys(array $columns): array
{
    return meza_get_admin_column_keys_matching_tokens($columns, ['age group', 'age_group', 'age-group']);
}

function meza_get_sorted_taxonomy_admin_column_keys(array $columns, string $post_type): array
{
    if (!is_array($columns)) {
        return [];
    }

    $sort_labels = meza_get_taxonomy_admin_column_sort_labels($post_type);
    if (empty($sort_labels)) {
        return [];
    }

    $taxonomy_columns = [];
    $position = 0;

    foreach ($columns as $key => $label) {
        $key_string = strtolower(trim((string) $key));
        $label_string = strtolower(trim(wp_strip_all_tags((string) $label)));
        $sort_label = $sort_labels[$key_string] ?? $sort_labels[$label_string] ?? null;

        if (!is_string($sort_label) || $sort_label === '') {
            $position++;
            continue;
        }

        $taxonomy_columns[(string) $key] = [
            'taxonomy' => meza_get_admin_column_taxonomy_name((string) $key, $post_type),
            'label' => strtolower($sort_label),
            'position' => $position,
        ];
        $position++;
    }

    $grouped_taxonomy_columns = [];

    foreach ($taxonomy_columns as $key => $data) {
        $taxonomy_name = trim((string) ($data['taxonomy'] ?? ''));
        if ($taxonomy_name === '') {
            $taxonomy_name = strtolower(trim((string) $key));
        }

        if (!isset($grouped_taxonomy_columns[$taxonomy_name])) {
            $grouped_taxonomy_columns[$taxonomy_name] = [];
        }

        $grouped_taxonomy_columns[$taxonomy_name][$key] = $data;
    }

    $preferred_taxonomy_columns = [];

    foreach ($grouped_taxonomy_columns as $taxonomy_name => $group) {
        foreach (meza_get_taxonomy_admin_column_preferred_keys($post_type, (string) $taxonomy_name) as $preferred_key) {
            if (isset($group[$preferred_key])) {
                $preferred_taxonomy_columns[$preferred_key] = $group[$preferred_key];
                continue 2;
            }
        }

        $first_key = array_key_first($group);
        if (is_string($first_key) && $first_key !== '') {
            $preferred_taxonomy_columns[$first_key] = $group[$first_key];
        }
    }

    uasort($preferred_taxonomy_columns, static function (array $a, array $b): int {
        $label_compare = strnatcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        if ($label_compare !== 0) {
            return $label_compare;
        }

        return ((int) ($a['position'] ?? 0)) <=> ((int) ($b['position'] ?? 0));
    });

    return array_keys($preferred_taxonomy_columns);
}

function meza_reinsert_sorted_taxonomy_columns(array $columns, string $post_type): array
{
    if (!is_array($columns)) {
        return [];
    }

    $sorted_taxonomy_keys = meza_get_sorted_taxonomy_admin_column_keys($columns, $post_type);
    if (count($sorted_taxonomy_keys) < 2) {
        return $columns;
    }

    if (meza_post_type_uses_admin_columns_layout($post_type)) {
        return $columns;
    }

    $taxonomy_key_map = array_fill_keys($sorted_taxonomy_keys, true);
    $rebuilt = [];
    $inserted = false;

    foreach ($columns as $key => $label) {
        if (!$inserted && isset($taxonomy_key_map[(string) $key])) {
            foreach ($sorted_taxonomy_keys as $taxonomy_key) {
                $rebuilt[$taxonomy_key] = $columns[$taxonomy_key];
            }
            $inserted = true;
        }

        if (isset($taxonomy_key_map[(string) $key])) {
            continue;
        }

        $rebuilt[$key] = $label;
    }

    if (!$inserted) {
        foreach ($sorted_taxonomy_keys as $taxonomy_key) {
            $rebuilt[$taxonomy_key] = $columns[$taxonomy_key];
        }
    }

    return $rebuilt;
}

function meza_normalize_admin_column_token(string $value): string
{
    return strtolower(trim(wp_strip_all_tags($value)));
}

function meza_admin_column_matches_tokens(string $key, $label, array $tokens): bool
{
    $normalized_key = meza_normalize_admin_column_token($key);
    $normalized_label = meza_normalize_admin_column_token((string) $label);

    foreach ($tokens as $token) {
        $token = meza_normalize_admin_column_token((string) $token);
        if ($token === '') {
            continue;
        }

        if ($normalized_key === $token || $normalized_label === $token) {
            return true;
        }
    }

    return false;
}

function meza_get_admin_column_keys_matching_tokens(array $columns, array $tokens): array
{
    if (!is_array($columns) || $columns === [] || $tokens === []) {
        return [];
    }

    $matched = [];

    foreach ($columns as $key => $label) {
        $key = trim((string) $key);
        if ($key === '') {
            continue;
        }

        if (!meza_admin_column_matches_tokens($key, $label, $tokens)) {
            continue;
        }

        $matched[] = $key;
    }

    return array_values(array_unique($matched));
}

function meza_get_current_screen_column_headers_map($screen): array
{
    if (!($screen instanceof WP_Screen) || !function_exists('get_column_headers')) {
        return [];
    }

    $headers = get_column_headers($screen);

    return is_array($headers) ? $headers : [];
}

function meza_move_taxonomy_columns_before_meta_columns(array $columns, string $post_type): array
{
    if (!is_array($columns)) {
        return [];
    }

    $post_type = trim($post_type);
    if ($post_type === '') {
        return $columns;
    }

    if (meza_post_type_uses_admin_columns_layout($post_type)) {
        return $columns;
    }

    $sorted_taxonomy_keys = meza_get_sorted_taxonomy_admin_column_keys($columns, $post_type);
    if (empty($sorted_taxonomy_keys)) {
        return $columns;
    }

    $anchor_tokens = [
        'mz_profile_link',
        'mz_organization_url',
        'wpseo-title',
        'wpseo-metadesc',
        'mz_share_title',
        'mz_share_description',
        'link',
        'meta title',
        'meta description',
        'share title',
        'share description',
    ];

    if (meza_post_type_uses_share_image_admin_column($post_type)) {
        $anchor_tokens[] = 'mz_thumbnail';
        $anchor_tokens[] = meza_get_post_type_thumbnail_admin_column_label($post_type);
    }

    $taxonomy_key_map = array_fill_keys($sorted_taxonomy_keys, true);
    $anchor_key = '';

    foreach ($columns as $key => $label) {
        $key = (string) $key;
        if (isset($taxonomy_key_map[$key])) {
            continue;
        }

        if (meza_admin_column_matches_tokens($key, $label, $anchor_tokens)) {
            $anchor_key = $key;
            break;
        }
    }

    if ($anchor_key === '') {
        return $columns;
    }

    $rebuilt = [];
    $inserted = false;

    foreach ($columns as $key => $label) {
        $key = (string) $key;

        if (!$inserted && $key === $anchor_key) {
            foreach ($sorted_taxonomy_keys as $taxonomy_key) {
                $rebuilt[$taxonomy_key] = $columns[$taxonomy_key];
            }
            $inserted = true;
        }

        if (isset($taxonomy_key_map[$key])) {
            continue;
        }

        $rebuilt[$key] = $label;
    }

    if (!$inserted) {
        return $columns;
    }

    return $rebuilt;
}

function meza_move_custom_admin_columns_before_meta_columns(array $columns, string $post_type): array
{
    if (!is_array($columns)) {
        return [];
    }

    $post_type = trim($post_type);
    if ($post_type === '') {
        return $columns;
    }

    if (meza_post_type_uses_admin_columns_layout($post_type)) {
        return $columns;
    }

    if (meza_admin_post_type_preserves_custom_admin_column_order($post_type)) {
        return $columns;
    }

    $anchor_tokens = [
        'mz_profile_link',
        'mz_organization_url',
        'wpseo-title',
        'wpseo-metadesc',
        'mz_share_title',
        'mz_share_description',
        'link',
        'meta title',
        'meta description',
        'share title',
        'share description',
    ];

    if (meza_post_type_uses_share_image_admin_column($post_type)) {
        $anchor_tokens[] = 'mz_thumbnail';
        $anchor_tokens[] = meza_get_post_type_thumbnail_admin_column_label($post_type);
    }

    $taxonomy_key_map = array_fill_keys(meza_get_sorted_taxonomy_admin_column_keys($columns, $post_type), true);
    $reserved_key_map = array_fill_keys([
        'cb',
        'title',
        'mz_id',
        'mz_menu_order',
        'mz_thumbnail',
        'mz_profile_title',
        'mz_profile_link',
        'mz_organization_url',
        'mz_summary',
        'mz_faq_count',
        'mz_cta_link',
        'mz_cta_secondary_link',
        'mz_slug',
        'mz_form_recipients',
        'mz_review_quote',
        'mz_review_citer',
        'mz_page_headline',
        'mz_page_cta',
        'mz_page_form',
        'start-date',
        'end-date',
        'mz_modified',
        'modified',
        'mz_published',
        'date',
    ], true);
    $excluded_custom_column_tokens = [];

    if ($post_type === 'provider') {
        $excluded_custom_column_tokens[] = 'is this provider accepting new patients?';
    }

    $custom_keys = [];

    foreach (array_keys($columns) as $key) {
        $key = (string) $key;
        if (
            $key === ''
            || isset($taxonomy_key_map[$key])
            || isset($reserved_key_map[$key])
            || meza_admin_column_matches_tokens($key, $columns[$key] ?? '', $anchor_tokens)
            || meza_admin_column_matches_tokens($key, $columns[$key] ?? '', $excluded_custom_column_tokens)
        ) {
            continue;
        }

        $custom_keys[] = $key;
    }

    if (empty($custom_keys)) {
        return $columns;
    }

    $custom_key_map = array_fill_keys($custom_keys, true);
    $anchor_key = '';

    foreach ($columns as $key => $label) {
        $key = (string) $key;
        if (isset($custom_key_map[$key])) {
            continue;
        }

        if (meza_admin_column_matches_tokens($key, $label, $anchor_tokens)) {
            $anchor_key = $key;
            break;
        }
    }

    if ($anchor_key === '') {
        return $columns;
    }

    $rebuilt = [];
    $inserted = false;

    foreach ($columns as $key => $label) {
        $key = (string) $key;

        if (!$inserted && $key === $anchor_key) {
            foreach ($custom_keys as $custom_key) {
                $rebuilt[$custom_key] = $columns[$custom_key];
            }
            $inserted = true;
        }

        if (isset($custom_key_map[$key])) {
            continue;
        }

        $rebuilt[$key] = $label;
    }

    return $inserted ? $rebuilt : $columns;
}

function meza_apply_default_admin_column_order(array $columns, string $post_type): array
{
    if (!is_array($columns)) {
        return [];
    }

    $post_type = trim($post_type);
    if ($post_type === '') {
        return $columns;
    }

    $ordered = [];
    $used = [];
    $used_taxonomies = [];

    $append = static function (string $key) use (&$ordered, &$columns, &$used, &$used_taxonomies, $post_type): void {
        if ($key === '' || isset($used[$key]) || !array_key_exists($key, $columns)) {
            return;
        }

        $taxonomy_name = meza_get_admin_column_taxonomy_name($key, $post_type);
        if ($taxonomy_name !== '' && isset($used_taxonomies[$taxonomy_name])) {
            $used[$key] = true;
            return;
        }

        $ordered[$key] = $columns[$key];
        $used[$key] = true;

        if ($taxonomy_name !== '') {
            $used_taxonomies[$taxonomy_name] = true;
        }
    };

    $append_first_match = static function (array $tokens) use (&$columns, &$used, $append): void {
        foreach ($columns as $key => $label) {
            $key = (string) $key;
            if ($key === '' || isset($used[$key])) {
                continue;
            }

            if (!meza_admin_column_matches_tokens($key, $label, $tokens)) {
                continue;
            }

            $append($key);
            return;
        }
    };

    $append_taxonomy_columns = static function () use ($append, $columns, $post_type): void {
        foreach (meza_get_sorted_taxonomy_admin_column_keys($columns, $post_type) as $key) {
            $append((string) $key);
        }
    };

    $append_first_match(['cb']);
    $append_first_match(['mz_id', 'id']);
    $append_first_match(['mz_menu_order', '#']);

    $thumbnail_label = meza_normalize_admin_column_token(meza_get_post_type_thumbnail_admin_column_label($post_type));
    if ($thumbnail_label !== '' && $thumbnail_label !== 'share image') {
        $append_first_match(['mz_thumbnail', $thumbnail_label]);
    }

    if (meza_post_type_is_profile_like($post_type)) {
        $append_first_match(['mz_summary', 'summary']);
        $append_first_match(['title', 'name', 'headline (h2)']);
    } else {
        $append_first_match(['title', 'name', 'headline (h2)']);
        $append_first_match(['mz_summary', 'summary']);
    }
    $append_first_match(['mz_profile_title']);
    $append_first_match(['mz_profile_link', 'mz_organization_url', 'mz_page_link', 'link']);
    $append_taxonomy_columns();
    $append_first_match(['mz_page_headline', 'page headline (h1)']);
    $append_first_match(['mz_page_cta', 'page cta']);
    $append_first_match(['mz_page_form', 'page form']);
    $append_first_match(['wpseo-title', 'meta title']);
    $append_first_match(['wpseo-metadesc', 'meta description']);
    $append_first_match(['share image']);
    $append_first_match(['mz_share_title', 'share title']);
    $append_first_match(['mz_share_description', 'share description']);
    $append_first_match(['mz_modified', 'modified']);
    $append_first_match(['mz_published', 'published', 'date']);

    foreach (array_keys($columns) as $key) {
        $append((string) $key);
    }

    return $ordered;
}

function meza_build_reordered_acp_column_collection($columns, string $post_type = '', string $table_id = ''): ?AC\ColumnCollection
{
    if (!class_exists('AC\\ColumnCollection') || !($columns instanceof AC\ColumnIterator)) {
        return null;
    }

    $post_type = trim($post_type);
    $table_id = trim($table_id);
    $layout_key = meza_get_seeded_admin_column_layout_key($post_type, $table_id);

    if ($post_type === '' && $layout_key === '') {
        return null;
    }

    $column_map = [];
    $labels = [];

    foreach ($columns as $column) {
        if (!is_object($column) || !method_exists($column, 'get_id') || !method_exists($column, 'get_label')) {
            continue;
        }

        $id = trim((string) $column->get_id());
        if ($id === '') {
            continue;
        }

        $column_map[$id] = $column;
        $labels[$id] = (string) $column->get_label();
    }

    if ($column_map === []) {
        return null;
    }

    $desired = ($layout_key !== '')
        ? meza_apply_seeded_admin_column_order($labels, $layout_key, true)
        : meza_normalize_admin_columns_managed_headings($labels, $post_type, false);
    $ordered = new AC\ColumnCollection();
    $used = [];
    $drop_unseeded_columns = ($layout_key !== '');

    foreach (array_keys($desired) as $id) {
        $id = (string) $id;
        if ($id === '' || !isset($column_map[$id]) || isset($used[$id])) {
            continue;
        }

        $ordered->add($column_map[$id]);
        $used[$id] = true;
    }

    if (!$drop_unseeded_columns) {
        foreach ($column_map as $id => $column) {
            if (isset($used[$id])) {
                continue;
            }

            $ordered->add($column);
        }
    }

    return $ordered;
}

function meza_get_acp_column_collection_ids($columns): array
{
    if (!($columns instanceof AC\ColumnIterator)) {
        return [];
    }

    $ids = [];

    foreach ($columns as $column) {
        if (!is_object($column) || !method_exists($column, 'get_id')) {
            continue;
        }

        $id = trim((string) $column->get_id());
        if ($id !== '') {
            $ids[] = $id;
        }
    }

    return $ids;
}

function meza_get_acp_column_collection_types($columns): array
{
    if (!($columns instanceof AC\ColumnIterator)) {
        return [];
    }

    $types = [];

    foreach ($columns as $column) {
        if (!is_object($column) || !method_exists($column, 'get_type')) {
            continue;
        }

        $type = trim((string) $column->get_type());
        if ($type !== '') {
            $types[] = $type;
        }
    }

    return $types;
}

function meza_get_seeded_acp_default_admin_columns(): array
{
    return [
        '_ac_columns_default_acf-post-type' => [
            'title' => ['label' => 'Title'],
            'acf-taxonomies' => ['label' => 'Taxonomies'],
            'acf-field-groups' => ['label' => 'Field Groups'],
            'acf-count' => ['label' => 'Posts'],
            'mz_modified' => ['label' => 'Modified'],
            'mz_published' => ['label' => 'Published'],
        ],
        '_ac_columns_default_acf-taxonomy' => [
            'title' => ['label' => 'Title'],
            'acf-post-types' => ['label' => 'Post Types'],
            'acf-field-groups' => ['label' => 'Field Groups'],
            'acf-count' => ['label' => 'Terms'],
            'mz_modified' => ['label' => 'Modified'],
            'mz_published' => ['label' => 'Published'],
        ],
        '_ac_columns_default_acf-ui-options-page' => [
            'title' => ['label' => 'Title'],
            'mz_modified' => ['label' => 'Modified'],
            'mz_published' => ['label' => 'Published'],
        ],
        '_ac_columns_default_cta' => [
            'mz_id' => ['label' => 'ID'],
            'title' => ['label' => 'Headline (H2)'],
            'mz_summary' => ['label' => 'Subhead'],
            'mz_cta_link' => ['label' => 'Link (Primary)'],
            'mz_cta_secondary_link' => ['label' => 'Link (Secondary)'],
            'mz_modified' => ['label' => 'Modified'],
            'mz_published' => ['label' => 'Published'],
        ],
        '_ac_columns_default_faq' => [
            'mz_id' => ['label' => 'ID'],
            'title' => ['label' => 'Title'],
            'mz_faq_count' => ['label' => 'Count'],
            'mz_modified' => ['label' => 'Modified'],
            'mz_published' => ['label' => 'Published'],
        ],
        '_ac_columns_default_form' => [
            'mz_id' => ['label' => 'ID'],
            'title' => ['label' => 'Name'],
            'mz_slug' => ['label' => 'Slug'],
            'mz_form_recipients' => ['label' => 'Recipients'],
            'mz_modified' => ['label' => 'Modified'],
            'mz_published' => ['label' => 'Published'],
        ],
        '_ac_columns_default_organization' => [
            'mz_id' => ['label' => 'ID'],
            'mz_menu_order' => ['label' => '#'],
            'mz_thumbnail' => ['label' => 'Logo'],
            'title' => ['label' => 'Name'],
            'mz_summary' => ['label' => 'Summary'],
            'mz_organization_url' => ['label' => 'Link'],
            'mz_modified' => ['label' => 'Modified'],
            'mz_published' => ['label' => 'Published'],
        ],
        '_ac_columns_default_page' => [
            'mz_id' => ['label' => 'ID'],
            'mz_menu_order' => ['label' => '#'],
            'title' => ['label' => 'Title'],
            'mz_summary' => ['label' => 'Summary'],
            'mz_page_headline' => ['label' => 'Page Headline (H1)'],
            'mz_page_cta' => ['label' => 'Page CTA'],
            'mz_page_form' => ['label' => 'Page Form'],
            'wpseo-title' => ['label' => 'Meta Title'],
            'wpseo-metadesc' => ['label' => 'Meta Description'],
            'mz_thumbnail' => ['label' => 'Share Image'],
            'mz_share_title' => ['label' => 'Share Title'],
            'mz_share_description' => ['label' => 'Share Description'],
            'mz_modified' => ['label' => 'Modified'],
            'mz_published' => ['label' => 'Published'],
        ],
        '_ac_columns_default_post' => [
            'mz_id' => ['label' => 'ID'],
            'mz_menu_order' => ['label' => '#'],
            'title' => ['label' => 'Title'],
            'mz_summary' => ['label' => 'Summary'],
            'categories' => ['label' => 'Categories'],
            'mz_page_headline' => ['label' => 'Page Headline (H1)'],
            'mz_page_cta' => ['label' => 'Page CTA'],
            'mz_page_form' => ['label' => 'Page Form'],
            'wpseo-title' => ['label' => 'Meta Title'],
            'wpseo-metadesc' => ['label' => 'Meta Description'],
            'mz_thumbnail' => ['label' => 'Share Image'],
            'mz_share_title' => ['label' => 'Share Title'],
            'mz_share_description' => ['label' => 'Share Description'],
            'mz_modified' => ['label' => 'Modified'],
            'mz_published' => ['label' => 'Published'],
        ],
        '_ac_columns_default_profile' => [
            'mz_id' => ['label' => 'ID'],
            'mz_menu_order' => ['label' => '#'],
            'mz_thumbnail' => ['label' => 'Photo'],
            'title' => ['label' => 'Name'],
            'mz_summary' => ['label' => 'Short Bio'],
            'mz_profile_title' => ['label' => 'Title'],
            'mz_profile_link' => ['label' => 'Link'],
            'mz_modified' => ['label' => 'Modified'],
            'mz_published' => ['label' => 'Published'],
        ],
        '_ac_columns_default_service' => [
            'mz_id' => ['label' => 'ID'],
            'mz_menu_order' => ['label' => '#'],
            'title' => ['label' => 'Title'],
            'mz_summary' => ['label' => 'Summary'],
            'mz_page_headline' => ['label' => 'Page Headline (H1)'],
            'mz_page_cta' => ['label' => 'Page CTA'],
            'mz_page_form' => ['label' => 'Page Form'],
            'wpseo-title' => ['label' => 'Meta Title'],
            'wpseo-metadesc' => ['label' => 'Meta Description'],
            'mz_thumbnail' => ['label' => 'Share Image'],
            'mz_share_title' => ['label' => 'Share Title'],
            'mz_share_description' => ['label' => 'Share Description'],
            'mz_modified' => ['label' => 'Modified'],
            'mz_published' => ['label' => 'Published'],
        ],
        '_ac_columns_default_team-member' => [
            'mz_id' => ['label' => 'ID'],
            'mz_menu_order' => ['label' => '#'],
            'mz_thumbnail' => ['label' => 'Photo'],
            'title' => ['label' => 'Name'],
            'mz_summary' => ['label' => 'Short Bio'],
            'mz_profile_title' => ['label' => 'Title'],
            'mz_profile_link' => ['label' => 'Link'],
            'mz_modified' => ['label' => 'Modified'],
            'mz_published' => ['label' => 'Published'],
        ],
        '_ac_columns_default_event' => [
            'mz_id' => ['label' => 'ID'],
            'title' => ['label' => 'Title'],
            'start-date' => ['label' => 'Start Date'],
            'mz_summary' => ['label' => 'Summary'],
            'mz_page_headline' => ['label' => 'Page Headline (H1)'],
            'mz_page_cta' => ['label' => 'Page CTA'],
            'mz_page_form' => ['label' => 'Page Form'],
            'wpseo-title' => ['label' => 'Meta Title'],
            'wpseo-metadesc' => ['label' => 'Meta Description'],
            'mz_thumbnail' => ['label' => 'Share Image'],
            'mz_share_title' => ['label' => 'Share Title'],
            'mz_share_description' => ['label' => 'Share Description'],
            'mz_modified' => ['label' => 'Modified'],
            'mz_published' => ['label' => 'Published'],
        ],
        '_ac_columns_default_project' => [
            'mz_id' => ['label' => 'ID'],
            'mz_menu_order' => ['label' => '#'],
            'mz_thumbnail' => ['label' => 'Image'],
            'title' => ['label' => 'Title'],
            'mz_summary' => ['label' => 'Summary'],
            'mz_modified' => ['label' => 'Modified'],
        ],
        '_ac_columns_default_resource' => [
            'mz_id' => ['label' => 'ID'],
            'mz_menu_order' => ['label' => '#'],
            'title' => ['label' => 'Title'],
            'mz_summary' => ['label' => 'Summary'],
            'mz_page_headline' => ['label' => 'Page Headline (H1)'],
            'mz_page_cta' => ['label' => 'Page CTA'],
            'mz_page_form' => ['label' => 'Page Form'],
            'wpseo-title' => ['label' => 'Meta Title'],
            'wpseo-metadesc' => ['label' => 'Meta Description'],
            'mz_thumbnail' => ['label' => 'Share Image'],
            'mz_share_title' => ['label' => 'Share Title'],
            'mz_share_description' => ['label' => 'Share Description'],
            'mz_modified' => ['label' => 'Modified'],
            'mz_published' => ['label' => 'Published'],
        ],
        '_ac_columns_default_review' => [
            'mz_id' => ['label' => 'ID'],
            'mz_menu_order' => ['label' => '#'],
            'title' => ['label' => 'Title'],
            'date' => ['label' => 'Date'],
            'mz_review_quote' => ['label' => 'Quote'],
            'mz_review_citer' => ['label' => 'Citer'],
            'mz_review_link' => ['label' => 'Link'],
            'mz_modified' => ['label' => 'Modified'],
            'mz_published' => ['label' => 'Published'],
        ],
        '_ac_columns_default_reviews' => [
            'mz_id' => ['label' => 'ID'],
            'mz_menu_order' => ['label' => '#'],
            'title' => ['label' => 'Title'],
            'date' => ['label' => 'Date'],
            'mz_review_quote' => ['label' => 'Quote'],
            'mz_review_citer' => ['label' => 'Citer'],
            'mz_review_link' => ['label' => 'Link'],
            'mz_modified' => ['label' => 'Modified'],
            'mz_published' => ['label' => 'Published'],
        ],
        '_ac_columns_default_wp_block' => [
            'mz_id' => ['label' => 'ID'],
            'title' => ['label' => 'Title'],
            'taxonomy-wp_pattern_category' => ['label' => 'Pattern Categories'],
            'mz_modified' => ['label' => 'Modified'],
            'mz_published' => ['label' => 'Published'],
        ],
        '_ac_columns_default_wp-comments' => [
            'author' => ['label' => 'Author'],
            'comment_id' => ['label' => 'ID'],
            'comment' => ['label' => 'Comment'],
            'response' => ['label' => 'In response to'],
            'date' => ['label' => 'Submitted on'],
        ],
        '_ac_columns_default_wp-media' => [
            'mz_id' => ['label' => 'ID'],
            'title' => ['label' => 'File'],
            'parent' => ['label' => 'Uploaded to'],
            'alt_text' => ['label' => 'Alt Text'],
            'file_size' => ['label' => 'Size'],
            'mime_type' => ['label' => 'Type'],
            'dimensions' => ['label' => 'Dimensions'],
            'mz_converted' => ['label' => 'Converted'],
            'mz_modified' => ['label' => 'Modified'],
            'mz_published' => ['label' => 'Published'],
        ],
        '_ac_columns_default_wp-users' => [
            'username' => ['label' => 'Username'],
            'name' => ['label' => 'Name'],
            'email' => ['label' => 'Email'],
            'role' => ['label' => 'Role'],
            'website' => ['label' => 'Website'],
            'last_login' => ['label' => 'Last Login'],
        ],
    ];
}

function meza_get_seeded_admin_column_defaults_for_list_key(string $list_key): array
{
    $list_key = trim($list_key);
    if ($list_key === '') {
        return [];
    }

    $option_name = '_ac_columns_default_' . $list_key;
    $defaults = meza_get_seeded_acp_default_admin_columns();

    return isset($defaults[$option_name]) && is_array($defaults[$option_name])
        ? $defaults[$option_name]
        : [];
}

function meza_get_seeded_admin_column_defaults_for_post_type(string $post_type): array
{
    return meza_get_seeded_admin_column_defaults_for_list_key($post_type);
}

function meza_get_seeded_admin_column_alias_candidates(string $key): array
{
    $key = trim($key);
    if ($key === '') {
        return [];
    }

    $aliases = [
        'date' => ['date', 'column-date'],
        'mz_published' => ['mz_published'],
        'website' => ['website', 'mz_website'],
        'mz_website' => ['mz_website', 'website'],
        'last_login' => ['last_login', 'mz_last_login'],
        'mz_last_login' => ['mz_last_login', 'last_login'],
        'comment_id' => ['comment_id', 'mz_id'],
        'start-date' => ['start-date', 'start_date'],
        'start_date' => ['start_date', 'start-date'],
    ];

    return array_values(array_unique(array_filter(array_map('strval', $aliases[$key] ?? [$key]))));
}

function meza_get_seeded_admin_column_label_candidates(string $seeded_key, string $seeded_label): array
{
    $candidates = [];
    $seeded_key = trim($seeded_key);
    $seeded_label = trim($seeded_label);

    if ($seeded_label !== '') {
        $candidates[] = $seeded_label;
    }

    $label_aliases = [
        'date' => ['Published', 'Date'],
        'mz_published' => ['Published', 'Date'],
        'start-date' => ['Start Date', 'Date'],
        'start_date' => ['Start Date', 'Date'],
        'mz_modified' => ['Modified'],
        'mz_faq_count' => ['Count'],
        'mime_type' => ['Type'],
        'file_size' => ['Size'],
        'comment_id' => ['ID'],
        'mz_id' => ['ID'],
    ];

    foreach ($label_aliases[$seeded_key] ?? [] as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate !== '') {
            $candidates[] = $candidate;
        }
    }

    return array_values(array_unique(array_filter(array_map('strval', $candidates))));
}

function meza_find_matching_seeded_admin_column_key(array $columns, string $seeded_key, string $seeded_label = '', array $used = []): string
{
    foreach (meza_get_seeded_admin_column_alias_candidates($seeded_key) as $candidate_key) {
        if ($candidate_key !== '' && array_key_exists($candidate_key, $columns) && !isset($used[$candidate_key])) {
            return $candidate_key;
        }
    }

    $label_candidates = array_fill_keys(array_map('meza_normalize_admin_column_token', meza_get_seeded_admin_column_label_candidates($seeded_key, $seeded_label)), true);

    if ($label_candidates !== []) {
        foreach ($columns as $candidate_key => $candidate_label) {
            $candidate_key = (string) $candidate_key;
            if ($candidate_key === '' || isset($used[$candidate_key])) {
                continue;
            }

            if (isset($label_candidates[meza_normalize_admin_column_token((string) $candidate_label)])) {
                return $candidate_key;
            }
        }
    }

    return '';
}

function meza_apply_seeded_admin_column_order(array $columns, string $list_key, bool $drop_unseeded_columns = false): array
{
    if (!is_array($columns)) {
        return [];
    }

    $seeded_columns = meza_get_seeded_admin_column_defaults_for_list_key($list_key);
    if ($seeded_columns === []) {
        return $columns;
    }

    $ordered = [];
    $used = [];

    if (array_key_exists('cb', $columns)) {
        $ordered['cb'] = $columns['cb'];
        $used['cb'] = true;
    }

    foreach ($seeded_columns as $seeded_key => $seeded_column) {
        $match = meza_find_matching_seeded_admin_column_key(
            $columns,
            (string) $seeded_key,
            (string) ($seeded_column['label'] ?? ''),
            $used
        );
        if ($match === '' || isset($used[$match])) {
            continue;
        }

        $ordered[$match] = $columns[$match];
        $used[$match] = true;
    }

    // Keep taxonomy columns available on seeded Admin Columns layouts even when
    // the layout definition itself does not enumerate them.
    if (post_type_exists($list_key)) {
        foreach (meza_get_sorted_taxonomy_admin_column_keys($columns, $list_key) as $taxonomy_key) {
            $taxonomy_key = (string) $taxonomy_key;
            if ($taxonomy_key === '' || isset($used[$taxonomy_key]) || !array_key_exists($taxonomy_key, $columns)) {
                continue;
            }

            $ordered[$taxonomy_key] = $columns[$taxonomy_key];
            $used[$taxonomy_key] = true;
        }
    }

    foreach (['mz_modified', 'mz_published'] as $runtime_key) {
        if (!array_key_exists($runtime_key, $columns) || isset($used[$runtime_key])) {
            continue;
        }

        $ordered[$runtime_key] = $columns[$runtime_key];
        $used[$runtime_key] = true;
    }

    if ($drop_unseeded_columns) {
        return $ordered;
    }

    foreach ($columns as $key => $label) {
        $key = (string) $key;
        if ($key === '' || isset($used[$key])) {
            continue;
        }

        $ordered[$key] = $label;
    }

    return $ordered;
}

function meza_get_seeded_admin_column_layout_key(string $post_type = '', string $table_id = ''): string
{
    $table_id = trim($table_id);
    if ($table_id !== '' && meza_get_seeded_admin_column_defaults_for_list_key($table_id) !== []) {
        return $table_id;
    }

    $post_type = trim($post_type);
    if ($post_type !== '' && meza_get_seeded_admin_column_defaults_for_post_type($post_type) !== []) {
        return $post_type;
    }

    return '';
}

function meza_get_default_visible_taxonomy_admin_column_keys(string $post_type): array
{
    $post_type = trim($post_type);
    if ($post_type === '') {
        return [];
    }

    $seeded_columns = meza_get_seeded_admin_column_defaults_for_post_type($post_type);
    if ($seeded_columns === []) {
        return meza_get_taxonomy_admin_column_keys_for_post_type($post_type);
    }

    $visible_keys = [];

    foreach (array_keys($seeded_columns) as $seeded_key) {
        foreach (meza_get_seeded_admin_column_alias_candidates((string) $seeded_key) as $candidate_key) {
            $taxonomy_name = meza_get_admin_column_taxonomy_name($candidate_key, $post_type);
            if ($taxonomy_name === '') {
                continue;
            }

            $visible_keys[] = $candidate_key;
            $visible_keys = array_merge(
                $visible_keys,
                meza_get_taxonomy_admin_column_preferred_keys($post_type, $taxonomy_name)
            );
        }
    }

    if ($visible_keys === []) {
        return meza_get_taxonomy_admin_column_keys_for_post_type($post_type);
    }

    return array_values(array_unique(array_filter(array_map('strval', $visible_keys))));
}

function meza_sync_seeded_acp_default_admin_columns(): void
{
    $target_version = '1.1.260';
    if ((string) get_option('meza_acp_seeded_default_admin_columns_migration') === $target_version) {
        return;
    }

    foreach (meza_get_seeded_acp_default_admin_columns() as $option_name => $columns) {
        update_option($option_name, $columns, false);
    }

    update_option('meza_acp_seeded_default_admin_columns_migration', $target_version, false);
}

add_action('admin_init', 'meza_sync_seeded_acp_default_admin_columns', 999);

function meza_media_acp_layout_matches_legacy_stub($columns): bool
{
    $types = meza_get_acp_column_collection_types($columns);
    sort($types);

    return in_array($types, [
        ['column-dimensions', 'column-postid', 'title'],
        ['dimensions', 'mz_id', 'title'],
    ], true);
}

function meza_build_seeded_acp_column_collection(string $list_key): ?AC\ColumnCollection
{
    if (
        !class_exists('AC\\ColumnCollection')
        || !class_exists('AC\\Column\\Base')
        || !class_exists('AC\\Column\\Context')
        || !class_exists('AC\\FormatterCollection')
        || !class_exists('AC\\Setting\\ComponentCollection')
        || !class_exists('AC\\Setting\\Config')
        || !class_exists('AC\\Type\\ColumnId')
    ) {
        return null;
    }

    $list_key = trim($list_key);
    if ($list_key === '') {
        return null;
    }

    $seeded_columns = meza_get_seeded_admin_column_defaults_for_list_key($list_key);
    if ($seeded_columns === []) {
        return null;
    }

    $columns = new AC\ColumnCollection();

    foreach ($seeded_columns as $type => $seeded_column) {
        $type = trim((string) $type);
        if ($type === '' || !AC\Type\ColumnId::is_valid_id($type)) {
            continue;
        }

        $label = trim((string) ($seeded_column['label'] ?? ''));
        if ($label === '') {
            $label = $type;
        }

        $columns->add(new AC\Column\Base(
            $type,
            $label,
            new AC\Setting\ComponentCollection(),
            new AC\Type\ColumnId($type),
            new AC\Column\Context(new AC\Setting\Config([
                'name' => $type,
                'label' => $label,
            ]), $label),
            new AC\FormatterCollection(),
            'default'
        ));
    }

    return $columns->count() > 0 ? $columns : null;
}

function meza_build_seeded_media_acp_column_collection(): ?AC\ColumnCollection
{
    return meza_build_seeded_acp_column_collection('wp-media');
}

function meza_repair_media_acp_list_screen(): void
{
    if (
        !class_exists('AC\\Registry')
        || !class_exists('AC\\ListScreenRepository\\Storage')
        || !class_exists('AC\\Type\\TableId')
    ) {
        return;
    }

    $target_version = '1.1.257';
    if ((string) get_option('meza_acp_media_list_screen_repair_migration') === $target_version) {
        return;
    }

    $storage = AC\Registry::get(AC\ListScreenRepository\Storage::class);
    if (!($storage instanceof AC\ListScreenRepository\Storage)) {
        return;
    }

    $replacement = meza_build_seeded_media_acp_column_collection();
    if (!($replacement instanceof AC\ColumnCollection)) {
        return;
    }

    foreach ($storage->find_all_by_table_id(new AC\Type\TableId('wp-media')) as $list_screen) {
        if (!is_object($list_screen) || !method_exists($list_screen, 'get_columns')) {
            continue;
        }

        $existing_columns = $list_screen->get_columns();
        if (!meza_media_acp_layout_matches_legacy_stub($existing_columns)) {
            continue;
        }

        $list_screen->set_columns($replacement);
        $storage->save($list_screen);
    }

    update_option('meza_acp_media_list_screen_repair_migration', $target_version, false);
}

add_action('admin_init', 'meza_repair_media_acp_list_screen', 1001);

function meza_service_acp_layout_matches_legacy_stub($columns): bool
{
    $types = meza_get_acp_column_collection_types($columns);
    sort($types);

    return in_array($types, [
        ['link', 'mz_id', 'mz_menu_order', 'mz_modified', 'mz_published', 'mz_summary', 'mz_thumbnail', 'title'],
    ], true);
}

function meza_repair_service_acp_list_screen(): void
{
    if (!class_exists('AC\\Registry') || !class_exists('AC\\ListScreenRepository\\Storage')) {
        return;
    }

    $target_version = '1.1.259';
    if ((string) get_option('meza_acp_service_list_screen_repair_migration') === $target_version) {
        return;
    }

    $storage = AC\Registry::get(AC\ListScreenRepository\Storage::class);
    if (!($storage instanceof AC\ListScreenRepository\Storage)) {
        return;
    }

    $replacement = meza_build_seeded_acp_column_collection('service');
    if (!($replacement instanceof AC\ColumnCollection)) {
        return;
    }

    foreach ($storage->find_all() as $list_screen) {
        if (!is_object($list_screen) || !method_exists($list_screen, 'get_columns') || !method_exists($list_screen, 'get_post_type')) {
            continue;
        }

        if (trim((string) $list_screen->get_post_type()) !== 'service') {
            continue;
        }

        $existing_columns = $list_screen->get_columns();
        if (!meza_service_acp_layout_matches_legacy_stub($existing_columns)) {
            continue;
        }

        $list_screen->set_columns($replacement);
        $storage->save($list_screen);
    }

    update_option('meza_acp_service_list_screen_repair_migration', $target_version, false);
}

add_action('admin_init', 'meza_repair_service_acp_list_screen', 1002);

function meza_create_seeded_acp_base_column(string $type, string $label): ?AC\Column\Base
{
    if (
        !class_exists('AC\\Column\\Base')
        || !class_exists('AC\\Column\\Context')
        || !class_exists('AC\\FormatterCollection')
        || !class_exists('AC\\Setting\\ComponentCollection')
        || !class_exists('AC\\Setting\\Config')
        || !class_exists('AC\\Type\\ColumnId')
        || !AC\Type\ColumnId::is_valid_id($type)
    ) {
        return null;
    }

    return new AC\Column\Base(
        $type,
        $label,
        new AC\Setting\ComponentCollection(),
        new AC\Type\ColumnId($type),
        new AC\Column\Context(new AC\Setting\Config([
            'name' => $type,
            'label' => $label,
        ]), $label),
        new AC\FormatterCollection(),
        'default'
    );
}

function meza_repair_review_acp_list_screen(): void
{
    if (
        !class_exists('AC\\Registry')
        || !class_exists('AC\\ColumnCollection')
        || !class_exists('AC\\ColumnIterator')
        || !class_exists('AC\\ListScreenRepository\\Storage')
    ) {
        return;
    }

    $target_version = '1.1.260';
    if ((string) get_option('meza_acp_review_list_screen_repair_migration') === $target_version) {
        return;
    }

    $storage = AC\Registry::get(AC\ListScreenRepository\Storage::class);
    if (!($storage instanceof AC\ListScreenRepository\Storage)) {
        return;
    }

    foreach ($storage->find_all() as $list_screen) {
        if (!is_object($list_screen) || !method_exists($list_screen, 'get_columns') || !method_exists($list_screen, 'get_post_type')) {
            continue;
        }

        $post_type = trim((string) $list_screen->get_post_type());
        if (!in_array($post_type, ['review', 'reviews'], true)) {
            continue;
        }

        $existing_columns = $list_screen->get_columns();
        if (!($existing_columns instanceof AC\ColumnIterator)) {
            continue;
        }

        $repaired_columns = new AC\ColumnCollection();
        $has_review_link = false;
        $inserted_review_link = false;

        foreach ($existing_columns as $column) {
            if (!is_object($column) || !method_exists($column, 'get_id')) {
                continue;
            }

            $column_id = trim((string) $column->get_id());
            if ($column_id === 'mz_review_link') {
                $has_review_link = true;
            }

            $repaired_columns->add($column);

            if (!$inserted_review_link && in_array($column_id, ['mz_review_citer', 'mz_review_quote', 'date', 'title'], true)) {
                $review_link_column = meza_create_seeded_acp_base_column('mz_review_link', __('Link'));
                if ($review_link_column instanceof AC\Column\Base) {
                    $repaired_columns->add($review_link_column);
                    $inserted_review_link = true;
                    $has_review_link = true;
                }
            }
        }

        if (!$has_review_link) {
            $review_link_column = meza_create_seeded_acp_base_column('mz_review_link', __('Link'));
            if ($review_link_column instanceof AC\Column\Base) {
                $repaired_columns->add($review_link_column);
                $has_review_link = true;
            }
        }

        if (!$has_review_link || meza_get_acp_column_collection_ids($existing_columns) === meza_get_acp_column_collection_ids($repaired_columns)) {
            continue;
        }

        $list_screen->set_columns($repaired_columns);
        $storage->save($list_screen);
    }

    update_option('meza_acp_review_list_screen_repair_migration', $target_version, false);
}

add_action('admin_init', 'meza_repair_review_acp_list_screen', 1003);

function meza_run_acp_default_admin_column_order_migration(): void
{
    if (!class_exists('AC\\Registry') || !class_exists('AC\\ListScreenRepository\\Storage')) {
        return;
    }

    $target_version = '1.1.253';
    if ((string) get_option('meza_acp_default_admin_column_order_migration') === $target_version) {
        return;
    }

    $storage = AC\Registry::get(AC\ListScreenRepository\Storage::class);
    if (!($storage instanceof AC\ListScreenRepository\Storage)) {
        return;
    }

    foreach ($storage->find_all() as $list_screen) {
        if (!is_object($list_screen) || !method_exists($list_screen, 'get_columns')) {
            continue;
        }

        $post_type = method_exists($list_screen, 'get_post_type')
            ? trim((string) $list_screen->get_post_type())
            : '';
        $table_id = method_exists($list_screen, 'get_table_id')
            ? trim((string) $list_screen->get_table_id())
            : '';
        $layout_key = meza_get_seeded_admin_column_layout_key($post_type, $table_id);

        if ($post_type === '' && $layout_key === '') {
            continue;
        }

        $existing_columns = $list_screen->get_columns();
        $reordered_columns = meza_build_reordered_acp_column_collection($existing_columns, $post_type, $table_id);
        if (!($reordered_columns instanceof AC\ColumnCollection)) {
            continue;
        }

        if (meza_get_acp_column_collection_ids($existing_columns) === meza_get_acp_column_collection_ids($reordered_columns)) {
            continue;
        }

        $list_screen->set_columns($reordered_columns);
        $storage->save($list_screen);
    }

    update_option('meza_acp_default_admin_column_order_migration', $target_version, false);
}

add_action('admin_init', 'meza_run_acp_default_admin_column_order_migration', 1000);

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
        $value = meza_resolve_seo_template_tokens_for_post($value, $post_id);
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function meza_contains_seo_template_tokens(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return false;
    }

    return preg_match('/%%[^%]+%%/', $value) === 1;
}

function meza_resolve_seo_template_tokens_for_post(string $value, int $post_id): string
{
    $value = trim(wp_strip_all_tags($value));
    if ($value === '') {
        return '';
    }

    if (!meza_contains_seo_template_tokens($value)) {
        return $value;
    }

    $post = get_post($post_id);

    if ($post instanceof WP_Post && class_exists('WPSEO_Replace_Vars')) {
        try {
            $replace_vars = new WPSEO_Replace_Vars();
            if (method_exists($replace_vars, 'replace')) {
                $resolved = $replace_vars->replace($value, $post);
                if (is_string($resolved)) {
                    $resolved = trim(wp_strip_all_tags($resolved));
                    if ($resolved !== '' && !meza_contains_seo_template_tokens($resolved)) {
                        return $resolved;
                    }
                }
            }
        } catch (Throwable $e) {
        }
    }

    $post_type_object = $post instanceof WP_Post ? get_post_type_object($post->post_type) : null;
    $excerpt = '';
    if ($post instanceof WP_Post) {
        $excerpt_source = (string) ($post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content);
        $excerpt = trim(wp_strip_all_tags(wp_trim_words($excerpt_source, 30, '')));
    }

    $separator = (string) apply_filters('document_title_separator', '-');
    if ($separator === '') {
        $separator = '-';
    }

    $replacements = [
        '%%sitename%%'   => get_bloginfo('name'),
        '%%sitedesc%%'   => get_bloginfo('description'),
        '%%tagline%%'    => get_bloginfo('description'),
        '%%title%%'      => $post instanceof WP_Post ? get_the_title($post) : '',
        '%%excerpt%%'    => $excerpt,
        '%%excerpt_only%%' => $excerpt,
        '%%sep%%'        => $separator,
        '%%page%%'       => '',
        '%%currentyear%%' => wp_date('Y'),
        '%%currentdate%%' => wp_date(get_option('date_format') ?: 'F j, Y'),
        '%%date%%'       => $post instanceof WP_Post ? get_the_date('', $post) : '',
        '%%pt_single%%'  => $post_type_object->labels->singular_name ?? '',
    ];

    $resolved = str_ireplace(array_keys($replacements), array_values($replacements), $value);
    $resolved = preg_replace('/\s+/', ' ', trim((string) $resolved)) ?? '';
    $resolved = preg_replace('/(?:\s*' . preg_quote($separator, '/') . '\s*){2,}/', ' ' . $separator . ' ', $resolved) ?? $resolved;
    $resolved = trim((string) $resolved, " \t\n\r\0\x0B-:|");

    if ($resolved === '' || meza_contains_seo_template_tokens($resolved)) {
        return '';
    }

    return $resolved;
}

function meza_get_post_yoast_title_value(int $post_id): string
{
    return meza_get_post_yoast_meta_value($post_id, [
        '_yoast_wpseo_title',
    ]);
}

function meza_get_post_yoast_description_value(int $post_id): string
{
    return meza_get_post_yoast_meta_value($post_id, [
        '_yoast_wpseo_metadesc',
    ]);
}

function meza_get_post_yoast_share_title_value(int $post_id): string
{
    return meza_get_post_yoast_meta_value($post_id, [
        '_yoast_wpseo_opengraph-title',
        '_yoast_wpseo_twitter-title',
        '_yoast_wpseo_title',
    ]);
}

function meza_truncate_admin_column_text(string $value, int $limit): string
{
    $value = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($value)) ?? '');
    if ($value === '' || $limit <= 0) {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, max(1, $limit - 3))) . '...';
    }

    if (strlen($value) <= $limit) {
        return $value;
    }

    return rtrim(substr($value, 0, max(1, $limit - 3))) . '...';
}

function meza_get_post_yoast_share_description_value(int $post_id): string
{
    return meza_get_post_yoast_meta_value($post_id, [
        '_yoast_wpseo_opengraph-description',
        '_yoast_wpseo_twitter-description',
        '_yoast_wpseo_metadesc',
    ]);
}

function meza_get_admin_column_taxonomy_name(string $column, string $post_type): string
{
    $column = trim($column);
    $post_type = trim($post_type);

    if ($column === '' || $post_type === '') {
        return '';
    }

    if ($post_type === 'post' && in_array($column, ['categories', 'taxonomy-category'], true)) {
        return 'category';
    }

    $candidates = [];

    if (str_starts_with($column, 'taxonomy-')) {
        $candidates[] = substr($column, 9);
    }

    $candidates[] = $column;

    $taxonomies = get_object_taxonomies($post_type, 'names');
    if (!is_array($taxonomies)) {
        return '';
    }

    foreach ($taxonomies as $taxonomy_name) {
        $taxonomy_name = sanitize_key((string) $taxonomy_name);
        if ($taxonomy_name === '') {
            continue;
        }

        if (in_array($column, meza_admin_get_post_type_taxonomy_column_aliases($post_type, $taxonomy_name), true)) {
            return $taxonomy_name;
        }
    }

    foreach ($candidates as $candidate) {
        $candidate = sanitize_key((string) $candidate);
        if ($candidate !== '' && in_array($candidate, $taxonomies, true)) {
            return $candidate;
        }
    }

    return '';
}

function meza_resolve_taxonomy_from_admin_column_key(string $column, string $post_type): string
{
    return meza_get_admin_column_taxonomy_name($column, $post_type);
}

function meza_is_event_post_type(string $post_type): bool
{
    $post_type = strtolower(trim($post_type));

    return $post_type !== '' && str_contains($post_type, 'event');
}

function meza_get_event_admin_column_datetime_value(int $post_id, string $context): string
{
    $post_id = (int) $post_id;
    $context = strtolower(trim($context));

    if ($post_id <= 0 || !in_array($context, ['start', 'end'], true)) {
        return '';
    }

    $normalize_date = static function ($value): string {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{8}$/', $value)) {
            return substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
        }

        return $value;
    };

    $normalize_time = static function ($value): string {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $value)) {
            return $value . ':00';
        }

        return $value;
    };

    $meta_keys = $context === 'start'
        ? ['_EventStartDate', 'start_datetime', 'start_date', '_event_start_date']
        : ['_EventEndDate', 'end_datetime', 'end_date', '_event_end_date'];

    foreach ($meta_keys as $meta_key) {
        $value = get_post_meta($post_id, $meta_key, true);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    $start_date = $normalize_date(get_post_meta($post_id, 'date_start', true));

    if ($context === 'start') {
        if ($start_date !== '') {
            $time_start = $normalize_time(get_post_meta($post_id, 'times_0_time_start', true));
            if ($time_start !== '') {
                return $start_date . ' ' . $time_start;
            }

            return $start_date;
        }
    }

    if ($context === 'end') {
        $end_date = $normalize_date(get_post_meta($post_id, 'date_end', true));
        $end_time = '';

        foreach ([
            'times_0_time_end',
            'time_end',
            'times_1_time_start',
        ] as $meta_key) {
            $end_time = $normalize_time(get_post_meta($post_id, $meta_key, true));
            if ($end_time !== '') {
                break;
            }
        }

        if ($end_date === '') {
            $end_date = $start_date;
        }

        if ($end_date !== '' && $end_time !== '') {
            return $end_date . ' ' . $end_time;
        }

        if ($end_date !== '') {
            return $end_date;
        }
    }

    return '';
}

function meza_format_event_admin_datetime(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $parts = meza_get_event_admin_datetime_parts($value);
    if ($parts === []) {
        return '';
    }

    $timestamp = (int) $parts['timestamp'];
    $include_time = !empty($parts['has_time']);
    $date_label = str_replace(' ', "\u{00A0}", wp_date('F j, Y', $timestamp));

    return $include_time
        ? $date_label . "\n" . wp_date('g:ia', $timestamp)
        : $date_label;
}

function meza_get_event_admin_datetime_parts(string $value): array
{
    $value = trim($value);
    if ($value === '') {
        return [];
    }

    $timezone = function_exists('wp_timezone')
        ? wp_timezone()
        : new DateTimeZone((string) date_default_timezone_get());
    $datetime = date_create_immutable($value, $timezone);

    if (!($datetime instanceof DateTimeImmutable)) {
        return [];
    }

    $timestamp = $datetime->getTimestamp();

    $hour = (int) $datetime->format('G');
    $minute = (int) $datetime->format('i');
    $second = (int) $datetime->format('s');
    $has_explicit_time = (bool) preg_match('/\b\d{1,2}:\d{2}\b/i', $value);
    $is_midnight = $hour === 0 && $minute === 0 && $second === 0;
    $is_all_day_end = $hour === 23 && $minute === 59;
    $has_time = $has_explicit_time && !$is_midnight && !$is_all_day_end;

    return [
        'timestamp' => $timestamp,
        'has_time' => $has_time,
    ];
}

function meza_format_event_admin_datetime_range(string $start_value, string $end_value): string
{
    $start_parts = meza_get_event_admin_datetime_parts($start_value);
    $end_parts = meza_get_event_admin_datetime_parts($end_value);

    if ($start_parts === [] && $end_parts === []) {
        return '';
    }

    if ($start_parts === []) {
        return meza_format_event_admin_datetime($end_value);
    }

    if ($end_parts === []) {
        return meza_format_event_admin_datetime($start_value);
    }

    $start_timestamp = (int) $start_parts['timestamp'];
    $end_timestamp = (int) $end_parts['timestamp'];
    $start_has_time = !empty($start_parts['has_time']);
    $end_has_time = !empty($end_parts['has_time']);

    $same_day = wp_date('Y-m-d', $start_timestamp) === wp_date('Y-m-d', $end_timestamp);
    if ($same_day) {
        $day_label = str_replace(' ', "\u{00A0}", wp_date('F j, Y', $start_timestamp));

        if (!$start_has_time && !$end_has_time) {
            return $day_label;
        }

        if ($start_has_time && !$end_has_time) {
            return $day_label . "\n" . wp_date('g:ia', $start_timestamp);
        }

        if (!$start_has_time && $end_has_time) {
            return $day_label . "\n" . wp_date('g:ia', $end_timestamp);
        }

        $same_meridiem = wp_date('a', $start_timestamp) === wp_date('a', $end_timestamp);
        $start_time = $same_meridiem ? wp_date('g:i', $start_timestamp) : wp_date('g:ia', $start_timestamp);
        $end_time = wp_date('g:ia', $end_timestamp);
        $separator = $same_meridiem ? '-' : ' - ';

        return $day_label . "\n" . $start_time . $separator . $end_time;
    }

    if (wp_date('Y', $start_timestamp) === wp_date('Y', $end_timestamp)) {
        if (wp_date('F', $start_timestamp) === wp_date('F', $end_timestamp)) {
            return str_replace(' ', "\u{00A0}", wp_date('F j', $start_timestamp)) . '-' . str_replace(' ', "\u{00A0}", wp_date('j, Y', $end_timestamp));
        }

        return str_replace(' ', "\u{00A0}", wp_date('F j', $start_timestamp)) . ' - ' . str_replace(' ', "\u{00A0}", wp_date('F j, Y', $end_timestamp));
    }

    return str_replace(' ', "\u{00A0}", wp_date('F j, Y', $start_timestamp)) . ' - ' . str_replace(' ', "\u{00A0}", wp_date('F j, Y', $end_timestamp));
}

function meza_get_event_admin_datetime_range_html(string $start_value, string $end_value): string
{
    $formatted = meza_format_event_admin_datetime_range($start_value, $end_value);
    if ($formatted === '') {
        return '';
    }

    $parts = preg_split('/\R/', $formatted, 2);
    $date = trim((string) ($parts[0] ?? ''));
    $time = trim((string) ($parts[1] ?? ''));

    if ($date === '') {
        return '';
    }

    $html = '<span class="meza-event-date-line">' . esc_html($date) . '</span>';

    if ($time !== '') {
        $html .= '<div class="meza-title-permalink"><span class="meza-event-date-time">' . esc_html($time) . '</span></div>';
    }

    return $html;
}

function meza_render_taxonomy_admin_column(string $taxonomy, WP_Post $post): bool
{
    $taxonomy = sanitize_key($taxonomy);
    if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {
        return false;
    }

    $terms = get_the_terms($post, $taxonomy);
    if (is_wp_error($terms)) {
        echo '&mdash;';
        return true;
    }

    if (!is_array($terms) || $terms === []) {
        echo '&mdash;';
        return true;
    }

    $links = [];

    foreach ($terms as $term) {
        if (!($term instanceof WP_Term)) {
            continue;
        }

        $filter_url = add_query_arg([
            'post_type' => $post->post_type,
            $taxonomy => $term->slug,
        ], admin_url('edit.php'));

        $links[] = '<a href="' . esc_url($filter_url) . '">' . esc_html($term->name) . '</a>';
    }

    echo $links !== [] ? implode(', ', $links) : '&mdash;';
    return true;
}

function meza_get_taxonomy_admin_column_html(string $taxonomy, WP_Post $post): string
{
    ob_start();
    $rendered = meza_render_taxonomy_admin_column($taxonomy, $post);
    $html = trim((string) ob_get_clean());

    if (!$rendered) {
        return '';
    }

    return $html !== '' ? $html : '&mdash;';
}

function meza_get_age_group_admin_column_values(int $post_id): array
{
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $raw_value = get_post_meta($post_id, 'age_group', true);
    $values = is_array($raw_value) ? $raw_value : [$raw_value];
    $values = array_values(array_unique(array_filter(array_map(static function ($value): string {
        return is_scalar($value) ? trim((string) $value) : '';
    }, $values))));

    if ($values === []) {
        return [];
    }

    $choices = [];
    $field = function_exists('get_field_object') ? get_field_object('age_group', $post_id) : null;
    if (is_array($field) && !empty($field['choices']) && is_array($field['choices'])) {
        $choices = $field['choices'];
    }

    $resolved = [];

    foreach ($values as $value) {
        $label = isset($choices[$value]) && is_scalar($choices[$value])
            ? trim((string) $choices[$value])
            : $value;

        if ($label === '') {
            continue;
        }

        $resolved[] = [
            'value' => $value,
            'label' => $label,
        ];
    }

    return $resolved;
}

function meza_get_age_group_admin_column_html(WP_Post $post): string
{
    if (taxonomy_exists('age_group')) {
        return '';
    }

    $values = meza_get_age_group_admin_column_values((int) $post->ID);
    if ($values === []) {
        return '&mdash;';
    }

    $links = [];

    foreach ($values as $item) {
        $value = trim((string) ($item['value'] ?? ''));
        $label = trim((string) ($item['label'] ?? ''));
        if ($value === '' || $label === '') {
            continue;
        }

        $filter_url = add_query_arg([
            'post_type' => $post->post_type,
            'age_group' => $value,
        ], admin_url('edit.php'));

        $links[] = '<a href="' . esc_url($filter_url) . '">' . esc_html($label) . '</a>';
    }

    return $links !== [] ? implode(', ', $links) : '&mdash;';
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

function meza_get_admin_column_identifiers($column): array
{
    if (!is_object($column)) {
        return [];
    }

    $identifiers = [];

    foreach (['get_meta_key', 'get_name', 'get_type', 'get_label', 'get_custom_label'] as $method) {
        if (!method_exists($column, $method)) {
            continue;
        }

        $candidate = $column->{$method}();
        if (is_string($candidate) && trim($candidate) !== '') {
            $identifiers[] = $candidate;
        }
    }

    return array_values(array_unique(array_filter(array_map('strval', $identifiers))));
}

function meza_normalize_admin_link_column_identifier(string $value): string
{
    $value = strtolower(trim(wp_strip_all_tags($value)));
    $value = str_replace(['_', '-'], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;

    return trim($value);
}

function meza_admin_column_identifier_supports_link_value(string $identifier): bool
{
    $normalized = meza_normalize_admin_link_column_identifier($identifier);
    if ($normalized === '') {
        return false;
    }

    if (str_contains($normalized, 'link')) {
        return true;
    }

    return (bool) preg_match('/(^|\s)url(\s|$)/', $normalized);
}

function meza_admin_column_name_supports_link_value($column): bool
{
    foreach (meza_get_admin_column_identifiers($column) as $identifier) {
        if (meza_admin_column_identifier_supports_link_value((string) $identifier)) {
            return true;
        }
    }

    return false;
}

function meza_get_admin_link_column_parts($value): array
{
    if (is_string($value)) {
        $value = maybe_unserialize($value);
    }

    if (is_array($value)) {
        $url = isset($value['url']) && !is_array($value['url'])
            ? trim((string) $value['url'])
            : '';
        $title = isset($value['title']) && !is_array($value['title'])
            ? trim(wp_strip_all_tags((string) $value['title']))
            : '';
        $target = isset($value['target']) && !is_array($value['target'])
            ? trim((string) $value['target'])
            : '';

        if ($url !== '') {
            return [
                'url' => $url,
                'title' => $title,
                'target' => $target,
            ];
        }

        return [];
    }

    $url = trim((string) $value);
    if ($url === '') {
        return [];
    }

    return [
        'url' => $url,
        'title' => '',
        'target' => '',
    ];
}

function meza_admin_column_raw_value_is_link_like($value): bool
{
    return meza_get_admin_link_column_parts($value) !== [];
}

function meza_get_event_link_admin_column_html(int $post_id): string
{
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !meza_is_event_post_type(get_post_type($post_id))) {
        return '';
    }

    $raw_value = function_exists('get_field') ? get_field('link', $post_id) : null;
    if ($raw_value === null || $raw_value === '') {
        $raw_value = get_post_meta($post_id, 'link', true);
    }

    $link_parts = meza_get_admin_link_column_parts($raw_value);
    if ($link_parts === []) {
        return '';
    }

    $url = trim((string) ($link_parts['url'] ?? ''));
    if ($url === '') {
        return '';
    }

    $label = trim((string) ($link_parts['title'] ?? ''));
    if ($label === '') {
        $label = meza_get_admin_link_column_display_text($url);
    }

    $target = trim((string) ($link_parts['target'] ?? ''));
    $target = in_array($target, ['_blank', '_self', '_parent', '_top'], true) ? $target : '_blank';
    $rel = ($target === '_blank') ? ' rel="noopener noreferrer"' : '';

    return '<a href="' . esc_url($url) . '" target="' . esc_attr($target) . '"' . $rel . '>' . esc_html($label) . '</a>';
}

function meza_get_certification_link_admin_column_html(int $post_id): string
{
    $post_id = (int) $post_id;
    if ($post_id <= 0 || get_post_type($post_id) !== 'certification') {
        return '';
    }

    $raw_value = function_exists('get_field') ? get_field('url', $post_id) : null;
    if ($raw_value === null || $raw_value === '') {
        $raw_value = get_post_meta($post_id, 'url', true);
    }

    if ($raw_value === null || $raw_value === '') {
        $raw_value = function_exists('get_field') ? get_field('link', $post_id) : null;
    }

    if ($raw_value === null || $raw_value === '') {
        $raw_value = get_post_meta($post_id, 'link', true);
    }

    $link_parts = meza_get_admin_link_column_parts($raw_value);
    if ($link_parts === []) {
        return '';
    }

    $url = trim((string) ($link_parts['url'] ?? ''));
    if ($url === '') {
        return '';
    }

    $label = trim((string) ($link_parts['title'] ?? ''));
    if ($label === '') {
        $label = meza_get_admin_link_column_display_text($url);
    }

    $target = trim((string) ($link_parts['target'] ?? ''));
    $target = in_array($target, ['_blank', '_self', '_parent', '_top'], true) ? $target : '_blank';
    $rel = ($target === '_blank') ? ' rel="noopener noreferrer"' : '';

    return '<a href="' . esc_url($url) . '" target="' . esc_attr($target) . '"' . $rel . '>' . esc_html($label) . '</a>';
}

function meza_get_post_type_link_admin_column_html(int $post_id): string
{
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return '';
    }

    $post_type = (string) get_post_type($post_id);
    if ($post_type === '') {
        return '';
    }

    if (meza_is_event_post_type($post_type)) {
        return meza_get_event_link_admin_column_html($post_id);
    }

    if ($post_type === 'certification') {
        return meza_get_certification_link_admin_column_html($post_id);
    }

    return '';
}

function meza_is_empty_admin_column_display_value($value): bool
{
    $normalized = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, 'UTF-8');
    $normalized = trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);

    return $normalized === '' || in_array($normalized, ['-', '–', '—'], true);
}

function meza_get_link_admin_column_keys(array $columns): array
{
    if (!is_array($columns) || $columns === []) {
        return [];
    }

    $matched = [];

    foreach ($columns as $key => $label) {
        $normalized_key = meza_normalize_admin_column_token((string) $key);
        $normalized_label = meza_normalize_admin_column_token((string) $label);

        if (
            ($normalized_key !== '' && meza_admin_column_identifier_supports_link_value($normalized_key))
            || ($normalized_label !== '' && meza_admin_column_identifier_supports_link_value($normalized_label))
        ) {
            $matched[] = (string) $key;
        }
    }

    return array_values(array_unique(array_filter(array_map('strval', $matched))));
}

function meza_admin_column_should_render_event_date($column, int $id, $value): bool
{
    if (!meza_is_event_post_type(get_post_type($id))) {
        return false;
    }

    $normalized_identifiers = array_map(
        'meza_normalize_admin_link_column_identifier',
        meza_get_admin_column_identifiers($column)
    );

    foreach ($normalized_identifiers as $identifier) {
        if (in_array($identifier, ['eventstartdate', 'eventenddate'], true)) {
            return true;
        }

        if (in_array($identifier, ['start datetime', 'start date', 'end datetime', 'end date', 'event date', 'date start'], true)) {
            return true;
        }

        if ($identifier === 'date' && meza_is_empty_admin_column_display_value($value)) {
            return true;
        }
    }

    return false;
}

function meza_admin_column_should_render_review_date($column, int $id, $value): bool
{
    if (!in_array((string) get_post_type($id), ['review', 'reviews'], true)) {
        return false;
    }

    $normalized_identifiers = array_map(
        'meza_normalize_admin_link_column_identifier',
        meza_get_admin_column_identifiers($column)
    );

    foreach ($normalized_identifiers as $identifier) {
        if (in_array($identifier, ['date', 'review date'], true)) {
            return true;
        }
    }

    return false;
}

function meza_admin_column_should_render_generic_date($column, int $id): bool
{
    if ($id <= 0 || meza_is_event_post_type(get_post_type($id))) {
        return false;
    }

    foreach (meza_get_admin_column_identifiers($column) as $identifier) {
        if (meza_admin_column_identifier_supports_date_value((string) $identifier)) {
            return true;
        }
    }

    return false;
}

function meza_extract_admin_column_emails($value): array
{
    if (is_array($value)) {
        $emails = [];

        foreach ($value as $item) {
            $emails = array_merge($emails, meza_extract_admin_column_emails($item));
        }

        return array_values(array_unique(array_filter($emails)));
    }

    if (!is_scalar($value)) {
        return [];
    }

    $text = trim((string) $value);
    if ($text === '') {
        return [];
    }

    preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches);
    $emails = array_map('sanitize_email', $matches[0] ?? []);

    return array_values(array_unique(array_filter($emails, static function (string $email): bool {
        return $email !== '' && is_email($email);
    })));
}

function meza_get_admin_email_column_html($value, array $args = []): string
{
    $emails = meza_extract_admin_column_emails($value);

    if ($emails === []) {
        $fallback_email = sanitize_email((string) ($args['fallback_email'] ?? ''));
        if ($fallback_email !== '' && is_email($fallback_email)) {
            $fallback_label = trim((string) ($args['fallback_label'] ?? ''));
            $fallback_html = '<a href="' . esc_url('mailto:' . $fallback_email) . '">' . esc_html($fallback_email) . '</a>';

            if ($fallback_label !== '') {
                $fallback_html .= '<br><span>(' . esc_html($fallback_label) . ')</span>';
            }

            return $fallback_html;
        }
    }

    if ($emails === []) {
        return '&mdash;';
    }

    $links = array_map(static function (string $email): string {
        return '<a href="' . esc_url('mailto:' . $email) . '">' . esc_html($email) . '</a>';
    }, $emails);

    return implode('<br>', $links);
}

function meza_parse_admin_phone_value(string $value): array
{
    $value = trim(wp_strip_all_tags($value));
    if ($value === '') {
        return ['display' => '', 'tel' => ''];
    }

    $extension = '';
    if (preg_match('/(?:ext\.?|extension|x)\s*[:.]?\s*(\d+)$/i', $value, $matches) === 1) {
        $extension = trim((string) ($matches[1] ?? ''));
        $value = trim((string) preg_replace('/(?:ext\.?|extension|x)\s*[:.]?\s*\d+$/i', '', $value));
    }

    $digits = preg_replace('/\D+/', '', $value) ?? '';
    if ($digits === '') {
        return ['display' => '', 'tel' => ''];
    }

    if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
        $digits = substr($digits, 1);
    }

    if (strlen($digits) !== 10) {
        $fallback = trim(wp_strip_all_tags($value));

        return [
            'display' => $fallback,
            'tel' => preg_replace('/\D+/', '', $value) ?? '',
        ];
    }

    $display = sprintf(
        '(%s) %s-%s',
        substr($digits, 0, 3),
        substr($digits, 3, 3),
        substr($digits, 6, 4)
    );
    $tel = '+1' . $digits;

    if ($extension !== '') {
        $display .= ' ext. ' . $extension;
        $tel .= ';ext=' . $extension;
    }

    return [
        'display' => $display,
        'tel' => $tel,
    ];
}

function meza_extract_admin_column_phone_values($value): array
{
    if (is_array($value)) {
        $phones = [];

        foreach ($value as $item) {
            $phones = array_merge($phones, meza_extract_admin_column_phone_values($item));
        }

        return array_values(array_unique(array_filter($phones)));
    }

    if (!is_scalar($value)) {
        return [];
    }

    $text = trim((string) $value);
    if ($text === '') {
        return [];
    }

    $parts = preg_split('/[\r\n;,]+/', $text) ?: [$text];
    $phones = [];

    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }

        $parsed = meza_parse_admin_phone_value($part);
        if (($parsed['display'] ?? '') === '' || ($parsed['tel'] ?? '') === '') {
            continue;
        }

        $phones[] = $part;
    }

    return array_values(array_unique($phones));
}

function meza_get_admin_phone_column_html($value): string
{
    $phones = meza_extract_admin_column_phone_values($value);
    if ($phones === []) {
        return '&mdash;';
    }

    $links = [];
    foreach ($phones as $phone) {
        $parsed = meza_parse_admin_phone_value((string) $phone);
        $display = trim((string) ($parsed['display'] ?? ''));
        $tel = trim((string) ($parsed['tel'] ?? ''));

        if ($display === '' || $tel === '') {
            continue;
        }

        $links[] = '<a href="' . esc_url('tel:' . $tel) . '">' . esc_html($display) . '</a>';
    }

    return $links !== [] ? implode('<br>', $links) : '&mdash;';
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

function meza_customize_media_admin_columns(array $columns): array
{
    $columns = is_array($columns) ? $columns : [];
    unset($columns['comments'], $columns['author'], $columns['date'], $columns['download']);

    $columns['mz_id'] = __('ID');
    $columns['mz_converted'] = __('Converted');

    if (isset($columns['title'])) {
        $columns['title'] = __('File');
    }

    if (isset($columns['parent'])) {
        $columns['parent'] = __('Uploaded to');
    }

    $columns['alt_text'] = __('Alt Text');
    $columns['file_size'] = __('Size');
    $columns['mime_type'] = __('Type');
    $columns['dimensions'] = __('Dimensions');
    $columns['mz_modified'] = __('Modified');
    $columns['mz_published'] = __('Published');

    return meza_apply_seeded_admin_column_order($columns, 'wp-media', true);
}

function meza_get_media_admin_column_file_size(int $attachment_id): int
{
    if ($attachment_id <= 0) {
        return 0;
    }

    $metadata = wp_get_attachment_metadata($attachment_id);
    if (is_array($metadata) && !empty($metadata['filesize'])) {
        return max(0, (int) $metadata['filesize']);
    }

    $path = get_attached_file($attachment_id);
    if (!is_string($path) || $path === '' || !file_exists($path)) {
        return 0;
    }

    $size = filesize($path);
    return is_int($size) ? max(0, $size) : 0;
}

function meza_sync_media_admin_column_file_size_meta(int $attachment_id): void
{
    if ($attachment_id <= 0 || get_post_type($attachment_id) !== 'attachment') {
        return;
    }

    $size = meza_get_media_admin_column_file_size($attachment_id);
    if ($size > 0) {
        update_post_meta($attachment_id, '_meza_attachment_filesize', $size);
        return;
    }

    delete_post_meta($attachment_id, '_meza_attachment_filesize');
}

function meza_get_media_admin_column_file_size_kb(int $attachment_id): int
{
    $size = meza_get_media_admin_column_file_size($attachment_id);
    if ($size <= 0) {
        return 0;
    }

    return (int) max(1, (int) ceil($size / 1024));
}

function meza_media_attachment_has_converted_asset(int $attachment_id): bool
{
    if ($attachment_id <= 0) {
        return false;
    }

    $path = get_attached_file($attachment_id);
    if (!is_string($path) || $path === '') {
        return false;
    }

    $mime_type = (string) get_post_mime_type($attachment_id);
    if (!str_starts_with($mime_type, 'image/')) {
        return false;
    }

    if (
        class_exists('\\WebpConverter\\Conversion\\OutputPathGenerator')
        && class_exists('\\WebpConverter\\Conversion\\Format\\FormatFactory')
        && class_exists('\\WebpConverter\\Repository\\TokenRepository')
        && class_exists('\\WebpConverter\\Conversion\\LargerFilesOperator')
        && class_exists('\\WebpConverter\\Conversion\\CrashedFilesOperator')
    ) {
        $format_factory = new \WebpConverter\Conversion\Format\FormatFactory(
            new \WebpConverter\Repository\TokenRepository()
        );
        $output_generator = new \WebpConverter\Conversion\OutputPathGenerator($format_factory);
        $deleted_extension = \WebpConverter\Conversion\LargerFilesOperator::DELETED_FILE_EXTENSION;
        $crashed_extension = \WebpConverter\Conversion\CrashedFilesOperator::CRASHED_FILE_EXTENSION;

        foreach (['webp', 'avif'] as $extension) {
            $output_path = $output_generator->get_path($path, false, $extension);
            if (!is_string($output_path) || $output_path === '') {
                continue;
            }

            if (
                file_exists($output_path)
                || file_exists($output_path . '.' . $deleted_extension)
                || file_exists($output_path . '.' . $crashed_extension)
            ) {
                return true;
            }
        }
    }

    $pathinfo = pathinfo($path);
    $dirname = (string) ($pathinfo['dirname'] ?? '');
    $filename = (string) ($pathinfo['filename'] ?? '');
    if ($dirname === '' || $filename === '') {
        return false;
    }

    $candidates = [
        $dirname . '/' . $filename . '.webp',
        $dirname . '/' . $filename . '.avif',
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && file_exists($candidate)) {
            return true;
        }
    }

    return false;
}

function meza_render_media_admin_column(string $column_name, int $post_id): void
{
    if ($post_id <= 0) {
        echo '&mdash;';
        return;
    }

    if ($column_name === 'mz_id') {
        echo (int) $post_id;
        return;
    }

    if ($column_name === 'mz_converted') {
        echo meza_media_attachment_has_converted_asset($post_id)
            ? '<span class="meza-boolean-icon meza-boolean-icon-true" aria-label="' . esc_attr__('True') . '">&#10003;</span>'
            : '<span class="meza-boolean-icon meza-boolean-icon-false" aria-label="' . esc_attr__('False') . '">&#10005;</span>';
        return;
    }

    if ($column_name === 'mime_type') {
        $mime_type = trim((string) get_post_mime_type($post_id));
        if ($mime_type === '') {
            echo '&mdash;';
            return;
        }

        $url = add_query_arg([
            'mode' => isset($_GET['mode']) ? (string) wp_unslash($_GET['mode']) : 'list',
            'post_mime_type' => $mime_type,
        ], admin_url('upload.php'));

        echo '<a href="' . esc_url($url) . '">' . esc_html($mime_type) . '</a>';
        return;
    }

    if ($column_name === 'dimensions') {
        $metadata = wp_get_attachment_metadata($post_id);
        $width = is_array($metadata) ? (int) ($metadata['width'] ?? 0) : 0;
        $height = is_array($metadata) ? (int) ($metadata['height'] ?? 0) : 0;

        if ($width > 0 && $height > 0) {
            echo esc_html(sprintf(__('%1$d x %2$d'), $width, $height));
            return;
        }

        echo '&mdash;';
        return;
    }

    if ($column_name === 'file_size') {
        $size = meza_get_media_admin_column_file_size($post_id);
        if ($size > 0) {
            update_post_meta($post_id, '_meza_attachment_filesize', $size);
        }
        $size_kb = meza_get_media_admin_column_file_size_kb($post_id);
        echo $size_kb > 0 ? esc_html(sprintf(__('%d KB'), $size_kb)) : '&mdash;';
        return;
    }

    if ($column_name === 'alt_text') {
        $alt_text = trim((string) get_post_meta($post_id, '_wp_attachment_image_alt', true));
        echo $alt_text !== '' ? esc_html($alt_text) : '&mdash;';
        return;
    }

    if ($column_name === 'mz_modified') {
        $attachment = get_post($post_id);
        if (!($attachment instanceof WP_Post)) {
            echo '&mdash;';
            return;
        }

        $modified_timestamp = get_post_modified_time('U', false, $attachment, true);
        if (!$modified_timestamp) {
            echo '&mdash;';
            return;
        }

        $modified_by_id = (int) get_post_meta($post_id, '_edit_last', true);
        if ($modified_by_id <= 0) {
            $modified_by_id = (int) $attachment->post_author;
        }
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

    if ($column_name === 'mz_published') {
        $attachment = get_post($post_id);
        if (!($attachment instanceof WP_Post)) {
            echo '&mdash;';
            return;
        }

        $published_timestamp = get_post_time('U', false, $attachment, true);
        if (!$published_timestamp) {
            echo '&mdash;';
            return;
        }

        $published_by_name = meza_get_user_first_name_by_id((int) $attachment->post_author);
        $published_by_email = meza_get_user_email_by_id((int) $attachment->post_author);
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
}

add_filter('manage_upload_columns', 'meza_customize_media_admin_columns', 1000);
add_action('manage_media_custom_column', 'meza_render_media_admin_column', 100, 2);
add_filter('manage_upload_sortable_columns', function (array $columns): array {
    $columns['mz_id'] = ['mz_id', true];
    $columns['file_size'] = ['file_size', true];
    $columns['mime_type'] = ['mime_type', false];
    $columns['mz_modified'] = ['mz_modified', true, '', '', 'desc'];
    $columns['mz_published'] = ['mz_published', true];

    return $columns;
}, 1000);

add_action('add_attachment', 'meza_sync_media_admin_column_file_size_meta', 100);
add_action('edit_attachment', 'meza_sync_media_admin_column_file_size_meta', 100);
add_filter('wp_generate_attachment_metadata', function ($metadata, $attachment_id) {
    meza_sync_media_admin_column_file_size_meta((int) $attachment_id);
    return $metadata;
}, 1000, 2);

add_action('current_screen', function ($screen): void {
    if (!($screen instanceof WP_Screen) || $screen->id !== 'upload') {
        return;
    }

    if ((isset($_GET['orderby']) && $_GET['orderby'] !== '') || (isset($_REQUEST['orderby']) && $_REQUEST['orderby'] !== '')) {
        return;
    }

    $_GET['orderby'] = 'mz_modified';
    $_REQUEST['orderby'] = 'mz_modified';
    $_GET['order'] = 'desc';
    $_REQUEST['order'] = 'desc';
}, 1);

add_action('pre_get_posts', function (WP_Query $q): void {
    global $pagenow;

    if (!is_admin() || !$q->is_main_query() || $pagenow !== 'upload.php') {
        return;
    }

    $orderby = (string) $q->get('orderby');
    $order = strtoupper((string) $q->get('order'));
    $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';
    $requested_mime_type = isset($_GET['post_mime_type']) ? sanitize_text_field((string) wp_unslash($_GET['post_mime_type'])) : '';

    if ($requested_mime_type !== '') {
        $q->set('post_mime_type', $requested_mime_type);
    }

    if ($orderby === '' || $orderby === 'mz_modified') {
        $q->set('orderby', 'modified');
        $q->set('order', 'DESC');
        return;
    }

    if ($orderby === 'mz_id') {
        $q->set('orderby', 'ID');
        $q->set('order', $order);
        return;
    }

    if ($orderby === 'mz_published') {
        $q->set('orderby', 'date');
        $q->set('order', $order);
        return;
    }

    if ($orderby === 'file_size') {
        $q->set('meta_key', '_meza_attachment_filesize');
        $q->set('orderby', 'meta_value_num');
        $q->set('order', $order);
        return;
    }
}, 15);

add_filter('posts_clauses', function (array $clauses, WP_Query $q): array {
    global $pagenow, $wpdb;

    if (!is_admin() || !$q->is_main_query() || $pagenow !== 'upload.php') {
        return $clauses;
    }

    if ((string) $q->get('orderby') !== 'mime_type') {
        return $clauses;
    }

    $order = strtoupper((string) $q->get('order'));
    $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC';
    $clauses['orderby'] = "{$wpdb->posts}.post_mime_type {$order}, {$wpdb->posts}.ID DESC";

    return $clauses;
}, 20, 2);

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

function meza_insert_admin_column_before(array $columns, string $before_key, string $new_key, string $label): array
{
    if (array_key_exists($new_key, $columns)) {
        $columns[$new_key] = $label;
        return $columns;
    }

    $updated = [];
    $inserted = false;

    foreach ($columns as $key => $value) {
        if (!$inserted && (string) $key === $before_key) {
            $updated[$new_key] = $label;
            $inserted = true;
        }

        $updated[$key] = $value;
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

function meza_ensure_share_text_admin_columns(array $columns, string $post_type): array
{
    if (!is_array($columns)) {
        $columns = [];
    }

    $post_type = trim($post_type);
    if ($post_type === '' || !meza_post_type_shows_share_text_admin_columns($post_type)) {
        unset($columns['mz_share_title'], $columns['mz_share_description']);
        return $columns;
    }

    $taxonomy_keys = meza_get_sorted_taxonomy_admin_column_keys($columns, $post_type);
    $last_taxonomy_key = $taxonomy_keys !== [] ? $taxonomy_keys[count($taxonomy_keys) - 1] : '';
    $pre_meta_anchors = array_values(array_filter([
        'mz_page_form',
        'mz_page_cta',
        'mz_page_headline',
        $last_taxonomy_key,
        'mz_profile_link',
        'mz_organization_url',
        'mz_summary',
        'mz_profile_title',
        'title',
    ]));

    if (!array_key_exists('mz_share_title', $columns)) {
        if (array_key_exists('wpseo-title', $columns)) {
            $columns = meza_insert_admin_column_after($columns, 'wpseo-title', 'mz_share_title', __('Share Title'));
        } elseif (array_key_exists('mz_thumbnail', $columns)) {
            $columns = meza_insert_admin_column_after($columns, 'mz_thumbnail', 'mz_share_title', __('Share Title'));
        } elseif (array_key_exists('wpseo-metadesc', $columns)) {
            $columns = meza_insert_admin_column_after($columns, 'wpseo-metadesc', 'mz_share_title', __('Share Title'));
        } elseif ($pre_meta_anchors !== []) {
            $columns = meza_insert_missing_admin_column($columns, $pre_meta_anchors, 'mz_share_title', __('Share Title'));
        } else {
            $columns['mz_share_title'] = __('Share Title');
        }
    } else {
        $columns['mz_share_title'] = __('Share Title');
    }

    if (!array_key_exists('mz_share_description', $columns)) {
        if (array_key_exists('wpseo-metadesc', $columns)) {
            $columns = meza_insert_admin_column_after($columns, 'wpseo-metadesc', 'mz_share_description', __('Share Description'));
        } elseif (array_key_exists('mz_share_title', $columns)) {
            $columns = meza_insert_admin_column_after($columns, 'mz_share_title', 'mz_share_description', __('Share Description'));
        } elseif (array_key_exists('mz_thumbnail', $columns)) {
            $columns = meza_insert_admin_column_after($columns, 'mz_thumbnail', 'mz_share_description', __('Share Description'));
        } elseif ($pre_meta_anchors !== []) {
            $columns = meza_insert_missing_admin_column($columns, $pre_meta_anchors, 'mz_share_description', __('Share Description'));
        } else {
            $columns['mz_share_description'] = __('Share Description');
        }
    } else {
        $columns['mz_share_description'] = __('Share Description');
    }

    return $columns;
}

function meza_ensure_modified_published_admin_columns(array $columns): array
{
    if (!is_array($columns)) {
        $columns = [];
    }

    $replace_column_key = static function (array $items, string $from, string $to, string $label) : array {
        if ($from === '' || $to === '' || !array_key_exists($from, $items)) {
            return $items;
        }

        $updated = [];

        foreach ($items as $key => $value) {
            if ((string) $key === $from) {
                $updated[$to] = $label;
                continue;
            }

            $updated[$key] = $value;
        }

        return $updated;
    };

    $anchors = [
        'mz_share_description',
        'mz_share_title',
        'mz_thumbnail',
        'wpseo-metadesc',
        'wpseo-title',
        'mz_page_form',
        'mz_page_cta',
        'mz_page_headline',
        'mz_organization_url',
        'mz_profile_link',
        'mz_summary',
        'mz_profile_title',
        'title',
    ];

    if (!array_key_exists('mz_modified', $columns) && !array_key_exists('modified', $columns)) {
        $columns = meza_insert_missing_admin_column($columns, $anchors, 'mz_modified', __('Modified'));
    } elseif (array_key_exists('mz_modified', $columns)) {
        $columns['mz_modified'] = __('Modified');
    } elseif (array_key_exists('modified', $columns)) {
        $columns['modified'] = __('Modified');
    }

    if (!array_key_exists('mz_published', $columns) && !array_key_exists('date', $columns)) {
        $columns = meza_insert_missing_admin_column($columns, array_merge(['mz_modified'], $anchors), 'mz_published', __('Published'));
    } elseif (array_key_exists('mz_published', $columns)) {
        $columns['mz_published'] = __('Published');
    } elseif (array_key_exists('date', $columns)) {
        $columns = $replace_column_key($columns, 'date', 'mz_published', __('Published'));
    }

    return $columns;
}

function meza_insert_missing_admin_column(array $columns, array $anchors, string $key, string $label): array
{
    if ($key === '') {
        return $columns;
    }

    if (array_key_exists($key, $columns)) {
        $columns[$key] = $label;
        return $columns;
    }

    foreach ($anchors as $anchor) {
        $anchor = trim((string) $anchor);
        if ($anchor !== '' && array_key_exists($anchor, $columns)) {
            return meza_insert_admin_column_after($columns, $anchor, $key, $label);
        }
    }

    $columns[$key] = $label;

    return $columns;
}

function meza_insert_missing_admin_column_before(array $columns, array $anchors, string $key, string $label): array
{
    if ($key === '') {
        return $columns;
    }

    if (array_key_exists($key, $columns)) {
        $columns[$key] = $label;
        return $columns;
    }

    foreach ($anchors as $anchor) {
        $anchor = trim((string) $anchor);
        if ($anchor !== '' && array_key_exists($anchor, $columns)) {
            return meza_insert_admin_column_before($columns, $anchor, $key, $label);
        }
    }

    $columns[$key] = $label;

    return $columns;
}

function meza_ensure_standard_admin_columns(array $columns, string $post_type): array
{
    if (!is_array($columns)) {
        $columns = [];
    }

    $post_type = trim($post_type);
    if ($post_type === '') {
        return $columns;
    }

    $supports_thumbnail = post_type_supports($post_type, 'thumbnail');
    $show_page_columns = !meza_is_acf_admin_post_type($post_type) && meza_post_type_has_permalink($post_type);
    $show_cta_link_column = ($post_type === 'cta');
    $show_cta_secondary_link_column = ($post_type === 'cta');
    $show_form_recipients_column = ($post_type === 'form');
    $show_organization_url_column = meza_post_type_is_organization_like($post_type);
    $show_profile_title_column = meza_post_type_is_profile_like($post_type);
    $show_profile_link_column = meza_post_type_is_profile_like($post_type);
    $show_review_link_column = in_array($post_type, ['review', 'reviews'], true);
    $show_faq_count_column = ($post_type === 'faq');
    $show_form_slug_column = ($post_type === 'form');
    $show_review_columns = in_array($post_type, ['review', 'reviews'], true);
    $show_summary_column = meza_post_type_shows_summary_admin_column($post_type);
    $taxonomy_keys = meza_get_sorted_taxonomy_admin_column_keys($columns, $post_type);
    $first_taxonomy_key = $taxonomy_keys[0] ?? '';
    $last_taxonomy_key = $taxonomy_keys !== [] ? $taxonomy_keys[count($taxonomy_keys) - 1] : '';

    $columns = meza_insert_missing_admin_column($columns, ['cb'], 'mz_id', __('ID'));

    if (meza_post_type_supports_menu_order_admin_column($post_type)) {
        $columns = meza_insert_missing_admin_column($columns, ['mz_id', 'cb'], 'mz_menu_order', __('#'));
    } else {
        unset($columns['mz_menu_order']);
    }

    if ($supports_thumbnail) {
        $columns = meza_insert_missing_admin_column(
            $columns,
            ['mz_menu_order', 'mz_id', 'cb'],
            'mz_thumbnail',
            meza_get_post_type_thumbnail_admin_column_label($post_type)
        );
    } else {
        unset($columns['mz_thumbnail']);
    }

    if ($show_profile_title_column) {
        $columns = meza_insert_missing_admin_column($columns, ['title'], 'mz_profile_title', __('Title'));
    } else {
        unset($columns['mz_profile_title']);
    }

    if ($show_profile_link_column) {
        if ($first_taxonomy_key !== '') {
            $columns = meza_insert_missing_admin_column_before($columns, [$first_taxonomy_key], 'mz_profile_link', __('Link'));
        } else {
            $columns = meza_insert_missing_admin_column($columns, ['mz_summary', 'mz_profile_title', 'title'], 'mz_profile_link', __('Link'));
        }
    } else {
        unset($columns['mz_profile_link']);
    }

    if ($show_organization_url_column) {
        if ($first_taxonomy_key !== '') {
            $columns = meza_insert_missing_admin_column_before($columns, [$first_taxonomy_key], 'mz_organization_url', __('Link'));
        } else {
            $columns = meza_insert_missing_admin_column($columns, ['mz_summary', 'title'], 'mz_organization_url', __('Link'));
        }
    } else {
        unset($columns['mz_organization_url']);
    }

    if ($show_review_link_column) {
        if ($first_taxonomy_key !== '') {
            $columns = meza_insert_missing_admin_column_before($columns, [$first_taxonomy_key], 'mz_review_link', __('Link'));
        } else {
            $columns = meza_insert_missing_admin_column($columns, ['mz_review_citer', 'mz_review_quote', 'title'], 'mz_review_link', __('Link'));
        }
    } else {
        unset($columns['mz_review_link']);
    }

    if ($show_summary_column) {
        $columns = meza_insert_missing_admin_column($columns, ['title'], 'mz_summary', meza_get_post_type_summary_admin_column_label($post_type));
    } else {
        unset($columns['mz_summary']);
    }

    if ($show_faq_count_column) {
        $columns = meza_insert_missing_admin_column($columns, ['title', 'mz_summary'], 'mz_faq_count', __('Count'));
    } else {
        unset($columns['mz_faq_count']);
    }

    if ($show_cta_link_column) {
        $columns = meza_insert_missing_admin_column($columns, ['mz_published', 'mz_modified', 'mz_share_description', 'mz_share_title', 'mz_thumbnail', 'wpseo-metadesc', 'wpseo-title'], 'mz_cta_link', __('Link (Primary)'));
    } else {
        unset($columns['mz_cta_link']);
    }

    if ($show_cta_secondary_link_column) {
        $columns = meza_insert_missing_admin_column($columns, ['mz_cta_link'], 'mz_cta_secondary_link', __('Link (Secondary)'));
    } else {
        unset($columns['mz_cta_secondary_link']);
    }

    if ($show_form_slug_column) {
        $columns = meza_insert_missing_admin_column($columns, ['mz_published', 'mz_modified', 'mz_share_description', 'mz_share_title', 'mz_thumbnail', 'wpseo-metadesc', 'wpseo-title'], 'mz_slug', __('Slug'));
    } else {
        unset($columns['mz_slug']);
    }

    if ($show_form_recipients_column) {
        $columns = meza_insert_missing_admin_column($columns, ['mz_slug'], 'mz_form_recipients', __('Recipients'));
    } else {
        unset($columns['mz_form_recipients']);
    }

    if ($show_review_columns) {
        $columns = meza_insert_missing_admin_column($columns, ['title', 'mz_summary'], 'mz_review_quote', __('Quote'));
        $columns = meza_insert_missing_admin_column($columns, ['mz_review_quote', 'title', 'mz_summary'], 'mz_review_citer', __('Citer'));
    } else {
        unset($columns['mz_review_quote'], $columns['mz_review_citer']);
    }

    if ($show_page_columns) {
        $page_anchor_candidates = array_values(array_filter([$last_taxonomy_key, 'mz_profile_link', 'mz_organization_url', 'mz_summary', 'mz_profile_title', 'title']));
        $columns = meza_insert_missing_admin_column($columns, $page_anchor_candidates, 'mz_page_headline', __('Page Headline (H1)'));
        $columns = meza_insert_missing_admin_column($columns, ['mz_page_headline'], 'mz_page_cta', __('Page CTA'));
        $columns = meza_insert_missing_admin_column($columns, ['mz_page_cta', 'mz_page_headline'], 'mz_page_form', __('Page Form'));
    } else {
        unset($columns['mz_page_headline'], $columns['mz_page_cta'], $columns['mz_page_form']);
    }

    return $columns;
}

function meza_place_summary_before_taxonomy_columns(array $columns, string $post_type): array
{
    if (!is_array($columns)) {
        return [];
    }

    $post_type = trim($post_type);
    if ($post_type === '') {
        return $columns;
    }

    $sorted_taxonomy_keys = meza_get_sorted_taxonomy_admin_column_keys($columns, $post_type);
    if ($sorted_taxonomy_keys === []) {
        return $columns;
    }

    $taxonomy_keys = array_fill_keys($sorted_taxonomy_keys, true);
    $summary_label = array_key_exists('mz_summary', $columns) ? $columns['mz_summary'] : null;
    if ($summary_label !== null) {
        unset($columns['mz_summary']);
    }

    $updated = [];
    $inserted = false;

    foreach ($columns as $key => $label) {
        if (!$inserted && isset($taxonomy_keys[(string) $key])) {
            if ($summary_label !== null) {
                $updated['mz_summary'] = $summary_label;
            }

            foreach ($sorted_taxonomy_keys as $taxonomy_key) {
                if (array_key_exists($taxonomy_key, $columns)) {
                    $updated[$taxonomy_key] = $columns[$taxonomy_key];
                }
            }

            $inserted = true;
        }

        if (isset($taxonomy_keys[(string) $key])) {
            continue;
        }

        $updated[$key] = $label;
    }

    if (!$inserted) {
        if ($summary_label !== null) {
            $updated['mz_summary'] = $summary_label;
        }

        foreach ($sorted_taxonomy_keys as $taxonomy_key) {
            if (array_key_exists($taxonomy_key, $columns)) {
                $updated[$taxonomy_key] = $columns[$taxonomy_key];
            }
        }
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
        $headings = meza_normalize_admin_columns_managed_headings($headings, $post_type, true);
    } else {
        $headings = meza_normalize_admin_columns_managed_headings($headings, $post_type, false);
    }
    return $headings;
}, 1000, 2);

function meza_page_form_admin_column_is_default_visible(string $post_type): bool
{
    if (!meza_acf_fields_are_available()) {
        return false;
    }

    return in_array(trim($post_type), ['page', 'service'], true);
}

function meza_get_page_marketing_admin_column_ids(): array
{
    return ['mz_page_headline', 'mz_page_cta'];
}

function meza_summary_admin_column_is_default_visible(string $post_type): bool
{
    $post_type = trim($post_type);
    if ($post_type === '') return false;
    if (!meza_post_type_shows_summary_admin_column($post_type)) return false;

    return ($post_type === 'organization');
}

function meza_normalize_admin_columns_managed_headings(array $columns, string $post_type, bool $preserve_existing_order = false): array
{
    if (!is_array($columns)) return $columns;

    $post_type = trim($post_type);
    if ($post_type === '') return $columns;
    $columns = meza_ensure_taxonomy_admin_columns($columns, $post_type);
    $columns = meza_combine_event_date_admin_columns($columns, $post_type);

    $supports_thumbnail = post_type_supports($post_type, 'thumbnail');
    $show_page_columns = !meza_is_acf_admin_post_type($post_type) && meza_post_type_has_permalink($post_type);
    $show_cta_link_column = ($post_type === 'cta');
    $show_cta_secondary_link_column = ($post_type === 'cta');
    $show_form_recipients_column = ($post_type === 'form');
    $show_organization_url_column = meza_post_type_is_organization_like($post_type);
    $show_profile_title_column = meza_post_type_is_profile_like($post_type);
    $show_profile_link_column = meza_post_type_is_profile_like($post_type);
    $show_review_link_column = in_array($post_type, ['review', 'reviews'], true);
    $show_faq_count_column = ($post_type === 'faq');
    $show_form_slug_column = ($post_type === 'form');
    $show_review_columns = in_array($post_type, ['review', 'reviews'], true);
    $show_summary_column = meza_post_type_shows_summary_admin_column($post_type);
    $show_yoast_share_columns = meza_post_type_shows_share_text_admin_columns($post_type);
    $thumbnail_column_label = meza_get_post_type_thumbnail_admin_column_label($post_type);
    $title_column_label = (
        (meza_post_type_is_profile_like($post_type) || meza_post_type_is_organization_like($post_type))
        ? __('Name')
        : ($post_type === 'cta' ? __('Headline (H2)') : ($post_type === 'form' ? __('Name') : null))
    );

    foreach ($columns as $key => $label) {
        $normalized_key = strtolower(trim((string) $key));
        $normalized_label = strtolower(trim(wp_strip_all_tags((string) $label)));

        if ($normalized_key === 'author' || $normalized_label === 'author') {
            unset($columns[$key]);
            continue;
        }

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
            case 'mz_review_link':
                if (!$show_review_columns) unset($columns[$key]);
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

    $columns = meza_ensure_standard_admin_columns($columns, $post_type);
    $columns = meza_ensure_share_text_admin_columns($columns, $post_type);
    $columns = meza_ensure_modified_published_admin_columns($columns);

    $has_seeded_layout = meza_get_seeded_admin_column_defaults_for_post_type($post_type) !== [];

    if ($preserve_existing_order) {
        $columns = $has_seeded_layout
            ? meza_apply_seeded_admin_column_order($columns, $post_type, true)
            : $columns;

        return meza_place_summary_before_taxonomy_columns($columns, $post_type);
    }

    if ($has_seeded_layout) {
        return meza_place_summary_before_taxonomy_columns(
            meza_apply_seeded_admin_column_order($columns, $post_type, true),
            $post_type
        );
    }

    return meza_place_summary_before_taxonomy_columns(
        meza_apply_default_admin_column_order(
            meza_move_custom_admin_columns_before_meta_columns(
                meza_move_taxonomy_columns_before_meta_columns(
                    meza_reinsert_sorted_taxonomy_columns($columns, $post_type),
                    $post_type
                ),
                $post_type
            ),
            $post_type
        ),
        $post_type
    );
}

function meza_normalize_datetime_columns(array $columns): array
{
    if (!is_array($columns)) return $columns;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $post_type = ($screen instanceof WP_Screen) ? (string) ($screen->post_type ?? '') : '';
    if ($post_type === 'product') return $columns;
    if ($post_type !== '' && meza_post_type_uses_admin_columns_layout($post_type)) {
        return meza_normalize_admin_columns_managed_headings($columns, $post_type, true);
    }
    if ($post_type !== '') {
        $columns = meza_ensure_taxonomy_admin_columns($columns, $post_type);
        $columns = meza_combine_event_date_admin_columns($columns, $post_type);
    }
    $supports_thumbnail = ($post_type !== '' && post_type_supports($post_type, 'thumbnail'));
    $show_menu_order_column = meza_post_type_supports_menu_order_admin_column($post_type);
    $show_page_columns = !meza_is_acf_admin_post_type($post_type) && meza_post_type_has_permalink($post_type);
    $show_cta_link_column = ($post_type === 'cta');
    $show_cta_secondary_link_column = ($post_type === 'cta');
    $show_form_recipients_column = ($post_type === 'form');
    $show_organization_url_column = meza_post_type_is_organization_like($post_type);
    $show_profile_title_column = meza_post_type_is_profile_like($post_type);
    $show_profile_link_column = meza_post_type_is_profile_like($post_type);
    $show_faq_count_column = ($post_type === 'faq');
    $show_form_slug_column = ($post_type === 'form');
    $show_review_columns = in_array($post_type, ['review', 'reviews'], true);
    $show_summary_column = meza_post_type_shows_summary_admin_column($post_type);
    $show_share_image_with_seo_columns = ($supports_thumbnail && meza_post_type_uses_share_image_admin_column($post_type));
    $show_yoast_share_columns = meza_post_type_shows_share_text_admin_columns($post_type);
    $thumbnail_column_label = meza_get_post_type_thumbnail_admin_column_label($post_type);
    $title_column_label = (
        (meza_post_type_is_profile_like($post_type) || meza_post_type_is_organization_like($post_type))
        ? __('Name')
        : ($post_type === 'cta' ? __('Headline (H2)') : ($post_type === 'form' ? __('Name') : null))
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
        $normalized_label = strtolower(trim(wp_strip_all_tags((string) $label)));

        if ($normalized_key === 'author' || $normalized_label === 'author') {
            continue;
        }

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
                if ($show_review_link_column) $updated['mz_review_link'] = __('Link');
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
    if ($show_review_link_column && !isset($updated['mz_review_link'])) {
        $updated['mz_review_link'] = __('Link');
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

    $updated = meza_ensure_share_text_admin_columns($updated, $post_type);
    $updated = meza_ensure_modified_published_admin_columns($updated);

    if (meza_get_seeded_admin_column_defaults_for_post_type($post_type) !== []) {
        return meza_place_summary_before_taxonomy_columns(
            meza_apply_seeded_admin_column_order($updated, $post_type, true),
            $post_type
        );
    }

    return meza_place_summary_before_taxonomy_columns(
        meza_apply_default_admin_column_order(
            meza_move_taxonomy_columns_before_meta_columns(
                meza_reinsert_sorted_taxonomy_columns($updated, $post_type),
                $post_type
            ),
            $post_type
        ),
        $post_type
    );
}

function meza_customize_popup_admin_columns(array $columns): array
{
    $post_type = 'popup';
    $columns = is_array($columns) ? $columns : [];
    $columns = meza_normalize_admin_columns_managed_headings($columns, $post_type);
    $columns = meza_ensure_menu_order_admin_column($columns, $post_type);
    $columns = meza_ensure_summary_admin_column($columns, $post_type);

    if (!array_key_exists('mz_id', $columns)) {
        if (array_key_exists('cb', $columns)) {
            $columns = meza_insert_admin_column_after($columns, 'cb', 'mz_id', __('ID'));
        } else {
            $columns = ['mz_id' => __('ID')] + $columns;
        }
    } else {
        $columns['mz_id'] = __('ID');
    }

    if (!array_key_exists('mz_modified', $columns)) {
        $columns['mz_modified'] = __('Modified');
    }

    if (!array_key_exists('mz_published', $columns)) {
        $columns['mz_published'] = __('Published');
    }

    $ordered = [];
    $append = static function (string $key) use (&$ordered, $columns): void {
        if ($key === '' || !array_key_exists($key, $columns) || array_key_exists($key, $ordered)) {
            return;
        }

        $ordered[$key] = $columns[$key];
    };

    foreach (['cb', 'mz_id', 'mz_menu_order', 'title', 'mz_summary', 'enabled', 'popup_title', 'class', 'views', 'conversions', 'popup_category', 'popup_tag'] as $key) {
        $append($key);
    }

    foreach (array_keys($columns) as $key) {
        $append((string) $key);
    }

    $append('mz_modified');
    $append('mz_published');
    $append('date');

    return meza_move_custom_admin_columns_before_meta_columns(
        meza_move_taxonomy_columns_before_meta_columns(
            meza_reinsert_sorted_taxonomy_columns($ordered, $post_type),
            $post_type
        ),
        $post_type
    );
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
    if (!meza_post_type_is_organization_like($post_type)) return $hidden;

    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];

    if (meza_post_type_organization_link_admin_column_is_default_visible($post_type)) {
        return array_values(array_diff($hidden, ['mz_organization_url']));
    }

    $hidden[] = 'mz_organization_url';
    return array_values(array_unique($hidden));
}, 305, 2);

add_filter('default_hidden_columns', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return $hidden;

    $post_type = (string) ($screen->post_type ?? '');
    if (!in_array($post_type, ['review', 'reviews'], true)) return $hidden;

    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];

    if (meza_review_link_admin_column_is_default_visible($post_type)) {
        return array_values(array_diff($hidden, ['mz_review_link']));
    }

    $hidden[] = 'mz_review_link';
    return array_values(array_unique($hidden));
}, 307, 2);

add_filter('default_hidden_columns', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return $hidden;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === '' || !meza_post_type_has_permalink($post_type)) return $hidden;
    if (meza_is_acf_admin_post_type($post_type)) return $hidden;

    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];

    if (meza_page_marketing_admin_columns_are_default_visible($post_type)) {
        return array_values(array_diff($hidden, meza_get_page_marketing_admin_column_ids()));
    }

    return array_values(array_unique(array_merge($hidden, meza_get_page_marketing_admin_column_ids())));
}, 310, 2);

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
    if (!meza_post_type_supports_menu_order_admin_column($post_type)) return;

    $user_id = get_current_user_id();
    if ($user_id <= 0) return;

    $meta_key = 'meza_menu_order_visibility_initialized_post_types_v2';
    $initialized_post_types = get_user_meta($user_id, $meta_key, true);
    $initialized_post_types = is_array($initialized_post_types)
        ? array_values(array_unique(array_map('strval', $initialized_post_types)))
        : [];

    if (in_array($post_type, $initialized_post_types, true)) return;

    $hidden_key = 'manage' . $screen->id . 'columnshidden';
    $hidden = get_user_option($hidden_key, $user_id);
    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];

    if (meza_post_type_menu_order_admin_column_is_default_visible($post_type)) {
        $hidden = array_values(array_diff($hidden, ['mz_menu_order']));
    } elseif (!in_array('mz_menu_order', $hidden, true)) {
        $hidden[] = 'mz_menu_order';
    }

    update_user_option($user_id, $hidden_key, array_values(array_unique($hidden)), true);

    $initialized_post_types[] = $post_type;
    update_user_meta($user_id, $meta_key, array_values(array_unique($initialized_post_types)));
}, 45);

add_action('current_screen', function ($screen): void {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    $post_type = (string) ($screen->post_type ?? '');
    if (!meza_post_type_is_organization_like($post_type)) return;

    $user_id = get_current_user_id();
    if ($user_id <= 0) return;

    $meta_key = 'meza_organization_link_visibility_initialized_post_types_v1';
    $initialized_post_types = get_user_meta($user_id, $meta_key, true);
    $initialized_post_types = is_array($initialized_post_types)
        ? array_values(array_unique(array_map('strval', $initialized_post_types)))
        : [];

    if (in_array($post_type, $initialized_post_types, true)) return;

    $hidden_key = 'manage' . $screen->id . 'columnshidden';
    $hidden = get_user_option($hidden_key, $user_id);
    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];

    if (meza_post_type_organization_link_admin_column_is_default_visible($post_type)) {
        $hidden = array_values(array_diff($hidden, ['mz_organization_url']));
    } elseif (!in_array('mz_organization_url', $hidden, true)) {
        $hidden[] = 'mz_organization_url';
    }

    update_user_option($user_id, $hidden_key, array_values(array_unique($hidden)), true);

    $initialized_post_types[] = $post_type;
    update_user_meta($user_id, $meta_key, array_values(array_unique($initialized_post_types)));
}, 46);

add_action('current_screen', function ($screen): void {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    $post_type = (string) ($screen->post_type ?? '');
    if (!in_array($post_type, ['review', 'reviews'], true)) return;

    $user_id = get_current_user_id();
    if ($user_id <= 0) return;

    $meta_key = 'meza_review_link_visibility_initialized_post_types_v1';
    $initialized_post_types = get_user_meta($user_id, $meta_key, true);
    $initialized_post_types = is_array($initialized_post_types)
        ? array_values(array_unique(array_map('strval', $initialized_post_types)))
        : [];

    if (in_array($post_type, $initialized_post_types, true)) return;

    $hidden_key = 'manage' . $screen->id . 'columnshidden';
    $hidden = get_user_option($hidden_key, $user_id);
    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];

    if (meza_review_link_admin_column_is_default_visible($post_type)) {
        $hidden = array_values(array_diff($hidden, ['mz_review_link']));
    } elseif (!in_array('mz_review_link', $hidden, true)) {
        $hidden[] = 'mz_review_link';
    }

    update_user_option($user_id, $hidden_key, array_values(array_unique($hidden)), true);

    $initialized_post_types[] = $post_type;
    update_user_meta($user_id, $meta_key, array_values(array_unique($initialized_post_types)));
}, 46);

add_action('current_screen', function ($screen): void {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === '' || !meza_post_type_has_permalink($post_type)) return;
    if (meza_is_acf_admin_post_type($post_type)) return;

    $user_id = get_current_user_id();
    if ($user_id <= 0) return;

    $meta_key = 'meza_page_marketing_visibility_initialized_post_types_v3';
    $initialized_post_types = get_user_meta($user_id, $meta_key, true);
    $initialized_post_types = is_array($initialized_post_types)
        ? array_values(array_unique(array_map('strval', $initialized_post_types)))
        : [];

    if (in_array($post_type, $initialized_post_types, true)) return;

    $hidden_key = 'manage' . $screen->id . 'columnshidden';
    $hidden = get_user_option($hidden_key, $user_id);
    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];

    if (meza_page_marketing_admin_columns_are_default_visible($post_type)) {
        $hidden = array_values(array_diff($hidden, meza_get_page_marketing_admin_column_ids()));
    } else {
        $hidden = array_values(array_unique(array_merge($hidden, meza_get_page_marketing_admin_column_ids())));
    }

    update_user_option($user_id, $hidden_key, $hidden, true);

    $initialized_post_types[] = $post_type;
    update_user_meta($user_id, $meta_key, array_values(array_unique($initialized_post_types)));
}, 47);

add_action('current_screen', function ($screen): void {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === '' || !meza_post_type_has_permalink($post_type)) return;
    if (meza_is_acf_admin_post_type($post_type)) return;

    add_filter("manage_edit-{$post_type}_columns", function ($columns) use ($post_type) {
        if (!is_array($columns)) $columns = [];
        if ($post_type === '' || !meza_post_type_has_permalink($post_type)) return $columns;
        if (meza_is_acf_admin_post_type($post_type)) return $columns;
        if (meza_post_type_uses_admin_columns_layout($post_type)) return $columns;

        $columns = meza_move_taxonomy_columns_before_meta_columns(
            meza_reinsert_sorted_taxonomy_columns(
                meza_ensure_taxonomy_admin_columns($columns, $post_type),
                $post_type
            ),
            $post_type
        );

        return meza_insert_admin_column_after($columns, 'mz_page_cta', 'mz_page_form', __('Page Form'));
    }, 1000);

    if (meza_page_form_admin_column_is_default_visible($post_type)) return;

    $user_id = get_current_user_id();
    if ($user_id <= 0) return;

    $meta_key = 'meza_page_form_hidden_migrated_post_types_v5';
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

    $meta_key = 'meza_summary_visibility_initialized_post_types_v2';
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

    $meta_key = 'meza_page_form_hidden_migrated_post_types_v5';
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

    $meta_key = 'meza_summary_visibility_initialized_post_types_v2';
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
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return $hidden;
    if (!$use_defaults) return $hidden;

    $post_type = (string) ($screen->post_type ?? '');
    if (!meza_post_type_supports_menu_order_admin_column($post_type)) return $hidden;
    if (!meza_post_type_menu_order_admin_column_is_default_visible($post_type)) return $hidden;

    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];
    return array_values(array_diff($hidden, ['mz_menu_order']));
}, 1100, 3);

add_filter('default_hidden_columns', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return $hidden;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === '') return $hidden;

    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];

    $visible_keys = meza_get_default_visible_taxonomy_admin_column_keys($post_type);
    if (meza_is_event_post_type($post_type)) {
        $visible_keys = array_merge($visible_keys, meza_get_event_date_admin_column_runtime_keys(meza_get_current_screen_column_headers_map($screen)));
    }
    if (!meza_is_event_post_type($post_type)) {
        $visible_keys = array_merge($visible_keys, meza_get_generic_date_admin_column_runtime_keys(meza_get_current_screen_column_headers_map($screen)));
    }

    return array_values(array_diff($hidden, array_values(array_unique(array_filter(array_map('strval', $visible_keys))))));
}, 1200, 2);

add_filter('hidden_columns', function ($hidden, $screen, $use_defaults) {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return $hidden;
    if (!$use_defaults) return $hidden;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === '') return $hidden;

    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];

    $visible_keys = meza_get_default_visible_taxonomy_admin_column_keys($post_type);
    if (meza_is_event_post_type($post_type)) {
        $visible_keys = array_merge($visible_keys, meza_get_event_date_admin_column_runtime_keys(meza_get_current_screen_column_headers_map($screen)));
    }
    if (!meza_is_event_post_type($post_type)) {
        $visible_keys = array_merge($visible_keys, meza_get_generic_date_admin_column_runtime_keys(meza_get_current_screen_column_headers_map($screen)));
    }

    return array_values(array_diff($hidden, array_values(array_unique(array_filter(array_map('strval', $visible_keys))))));
}, 1200, 3);

add_filter('hidden_columns', function ($hidden, $screen, $use_defaults) {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return $hidden;
    if (!$use_defaults) return $hidden;

    $post_type = (string) ($screen->post_type ?? '');
    if (!meza_post_type_is_organization_like($post_type)) return $hidden;
    if (!meza_post_type_organization_link_admin_column_is_default_visible($post_type)) return $hidden;

    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];
    return array_values(array_diff($hidden, ['mz_organization_url']));
}, 1110, 3);

add_filter('hidden_columns', function ($hidden, $screen, $use_defaults) {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return $hidden;
    if (!$use_defaults) return $hidden;

    $post_type = (string) ($screen->post_type ?? '');
    if (!in_array($post_type, ['review', 'reviews'], true)) return $hidden;
    if (!meza_review_link_admin_column_is_default_visible($post_type)) return $hidden;

    $hidden = is_array($hidden) ? array_map('strval', $hidden) : [];
    return array_values(array_diff($hidden, ['mz_review_link']));
}, 1111, 3);

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

    if (($screen instanceof WP_Screen) && meza_is_event_post_type($post_type)) {
        foreach (meza_get_event_date_admin_column_runtime_keys(meza_get_current_screen_column_headers_map($screen)) as $column_key) {
            $cols[(string) $column_key] = ['start_datetime_order', true];
        }
    }

    if (($screen instanceof WP_Screen) && !meza_is_event_post_type($post_type)) {
        foreach (meza_get_generic_date_admin_column_runtime_keys(meza_get_current_screen_column_headers_map($screen)) as $column_key) {
            $cols[(string) $column_key] = [(string) $column_key, true];
        }
    }

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
        if (!($taxonomy_obj instanceof WP_Taxonomy) || empty($taxonomy_obj->show_ui)) continue;

        $cols["taxonomy-{$taxonomy}"] = ["mz_tax_{$taxonomy}", false];
    }

    return $cols;
}

function meza_should_render_meza_posts_list_column(string $column): bool
{
    $column = trim($column);

    return $column !== '' && str_starts_with($column, 'mz_');
}

function meza_render_posts_list_column(string $column, int $post_id): void
{
    if (!meza_should_render_meza_posts_list_column($column)) {
        return;
    }

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

        echo meza_get_admin_email_column_html($recipients, [
            'fallback_email' => function_exists('get_field') ? (string) get_field('email', 'option') : '',
            'fallback_label' => 'site email',
        ]);
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
        if ($share_title === '') {
            $share_title = meza_get_post_yoast_title_value((int) $post_id);
        }
        $share_title = meza_truncate_admin_column_text($share_title, 60);
        echo ($share_title !== '') ? esc_html($share_title) : '&mdash;';
        return;
    }
    if ($column === 'mz_share_description') {
        $share_description = meza_get_post_yoast_share_description_value((int) $post_id);
        if ($share_description === '') {
            $share_description = meza_get_post_yoast_description_value((int) $post_id);
        }
        $share_description = meza_truncate_admin_column_text($share_description, 110);
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
        $url = meza_get_post_link_field_url((int) $post_id, ['link', 'url']);

        if ($url === '') {
            echo '&mdash;';
            return;
        }

        echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html(meza_get_admin_link_column_display_text($url)) . '</a>';
        return;
    }
    if ($column === 'mz_review_link') {
        $url = meza_get_post_link_field_url((int) $post_id, ['link']);

        if ($url === '') {
            echo '&mdash;';
            return;
        }

        echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html(meza_get_admin_link_column_display_text($url)) . '</a>';
        return;
    }
    if ($column === 'mz_review_quote') {
        $quote = trim(wp_strip_all_tags((string) get_post_field('post_excerpt', (int) $post_id)));

        if ($quote === '' && function_exists('get_field')) {
            foreach (['quote_short', 'quote'] as $field_name) {
                $acf_quote = get_field($field_name, (int) $post_id);
                if (is_string($acf_quote)) {
                    $quote = trim(wp_strip_all_tags($acf_quote));
                }

                if ($quote !== '') {
                    break;
                }
            }
        }
        if ($quote === '') {
            foreach (['quote_short', 'quote'] as $meta_key) {
                $quote = trim(wp_strip_all_tags((string) get_post_meta((int) $post_id, $meta_key, true)));
                if ($quote !== '') {
                    break;
                }
            }
        }
        echo ($quote !== '') ? esc_html($quote) : '&mdash;';
        return;
    }
    if ($column === 'mz_review_citer') {
        $citer = '';
        if (function_exists('get_field')) {
            foreach (['cite', 'citer'] as $field_name) {
                $acf_citer = get_field($field_name, (int) $post_id);
                if (is_string($acf_citer)) {
                    $citer = trim(wp_strip_all_tags($acf_citer));
                }

                if ($citer !== '') {
                    break;
                }
            }
        }
        if ($citer === '') {
            foreach (['cite', 'citer'] as $meta_key) {
                $citer = trim(wp_strip_all_tags((string) get_post_meta((int) $post_id, $meta_key, true)));
                if ($citer !== '') {
                    break;
                }
            }
        }
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
            'style' => 'width:auto;height:auto;max-width:100%;display:block;margin:0;',
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
        if (!meza_post_has_permalink((int) $post_id)) {
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
        if (!meza_post_has_permalink((int) $post_id)) {
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
        if (!meza_post_has_permalink((int) $post_id)) {
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

    if (in_array((string) $post->post_type, ['review', 'reviews'], true)) {
        $review_date_timestamp = meza_get_review_admin_column_date_timestamp((int) $post_id);
        if ($review_date_timestamp > 0) {
            $date = wp_date(get_option('date_format'), $review_date_timestamp);
            echo esc_html__('Date') . '<br>' . esc_html($date);
            return;
        }
    }

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
        if (meza_post_type_uses_admin_columns_layout($post_type)) {
            return is_array($columns) ? $columns : [];
        }
        return meza_ensure_summary_admin_column(is_array($columns) ? $columns : [], $post_type);
    }, 100000);
    add_action("manage_{$post_type}_posts_custom_column", 'meza_render_posts_list_column', 100, 2);
    add_filter("manage_edit-{$post_type}_sortable_columns", 'meza_register_datetime_sortable_columns', 1000);
    add_filter("manage_edit-{$post_type}_sortable_columns", 'meza_register_taxonomy_sortable_columns', 1001);
});

add_filter('pum_popup_columns', function ($columns) {
    return meza_customize_popup_admin_columns(is_array($columns) ? $columns : []);
}, 1000);

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

function meza_render_woocommerce_admin_layout_reset(): void
{
    echo '<style id="meza-woocommerce-admin-layout-reset">#wpbody{margin-top:0!important;}</style>';
}

function meza_render_woocommerce_admin_title_case_script(): void
{
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

    add_action('admin_head', 'meza_render_woocommerce_admin_layout_reset', 1000);
    add_action('admin_head', 'meza_render_woocommerce_admin_title_case_script', 1001);

    if (class_exists(\Automattic\WooCommerce\Internal\Admin\Loader::class)) {
        remove_action('in_admin_header', [\Automattic\WooCommerce\Internal\Admin\Loader::class, 'embed_page_header']);
        remove_filter('admin_body_class', [\Automattic\WooCommerce\Internal\Admin\Loader::class, 'add_admin_body_classes']);
        remove_action('admin_head', [\Automattic\WooCommerce\Internal\Admin\Loader::class, 'remove_notices']);
        remove_action('admin_notices', [\Automattic\WooCommerce\Internal\Admin\Loader::class, 'inject_before_notices'], -9999);
        remove_action('admin_notices', [\Automattic\WooCommerce\Internal\Admin\Loader::class, 'inject_after_notices'], PHP_INT_MAX);
    }
}, 1000);

add_action('admin_init', 'meza_suppress_non_meza_plugin_admin_notice_callbacks', 0);

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

// Keep explicit custom-field date sorting for ACP date columns.
add_action('pre_get_posts', function (WP_Query $q) {
    global $pagenow;
    if (!is_admin() || !$q->is_main_query() || $pagenow !== 'edit.php') return;

    $post_type = sanitize_key((string) $q->get('post_type'));
    if ($post_type === '' || meza_is_event_post_type($post_type)) return;

    $orderby = (string) $q->get('orderby');
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen)) return;

    $headers = meza_get_current_screen_column_headers_map($screen);
    $runtime_date_keys = meza_get_generic_date_admin_column_runtime_keys($headers);
    if (!in_array($orderby, $runtime_date_keys, true)) return;

    $meta_key = meza_resolve_admin_date_sort_meta_key($post_type, [
        $orderby,
        (string) ($headers[$orderby] ?? ''),
    ]);
    if ($meta_key === '') return;

    $order = strtoupper((string) $q->get('order'));
    $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC';

    $q->set('meta_key', $meta_key);
    $q->set('meta_type', 'NUMERIC');
    $q->set('orderby', [
        'meta_value_num' => $order,
        'date' => 'DESC',
        'ID' => 'DESC',
    ]);
    $q->set('order', $order);
}, 14);

add_action('pre_get_posts', function (WP_Query $q) {
    global $pagenow;
    if (!is_admin() || !$q->is_main_query() || $pagenow !== 'edit.php') return;

    $post_type = sanitize_key((string) $q->get('post_type'));
    if (!meza_is_event_post_type($post_type)) return;

    $orderby = (string) $q->get('orderby');
    $order   = strtoupper((string) $q->get('order'));
    $order   = in_array($order, ['ASC', 'DESC'], true) ? $order : '';

    if ($orderby === 'start_datetime_order') {
        $dir = $order ?: 'DESC';
        $q->set('meta_key', '_EventStartDate');
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

add_action('pre_get_posts', function (WP_Query $q) {
    global $pagenow;
    if (!is_admin() || !$q->is_main_query() || $pagenow !== 'edit.php') return;
    if (taxonomy_exists('age_group')) return;

    $age_group = isset($_GET['age_group']) ? wp_unslash($_GET['age_group']) : '';
    if (!is_scalar($age_group)) return;

    $age_group = sanitize_text_field((string) $age_group);
    if ($age_group === '') return;

    $meta_query = $q->get('meta_query');
    $meta_query = is_array($meta_query) ? $meta_query : [];
    $meta_query[] = [
        'key' => 'age_group',
        'value' => $age_group,
        'compare' => '=',
    ];

    $q->set('meta_query', $meta_query);
}, 20);

/** ================================
 *  CTA ADMIN SORTING
 *  ================================ */

if (!function_exists('meza_seed_edit_screen_default_sort_request')) {
    function meza_seed_edit_screen_default_sort_request(WP_Screen $screen): void
    {
        if ((string) ($screen->base ?? '') !== 'edit') return;

        $post_type = sanitize_key((string) ($screen->post_type ?? ''));
        if ($post_type === '') return;

        if ((isset($_GET['orderby']) && $_GET['orderby'] !== '') || (isset($_REQUEST['orderby']) && $_REQUEST['orderby'] !== '')) {
            return;
        }

        $orderby = 'modified';
        $order = 'desc';

        if (meza_post_type_menu_order_admin_column_is_visible($post_type)) {
            $orderby = 'menu_order';
            $order = 'asc';
        }

        $_GET['orderby'] = $orderby;
        $_REQUEST['orderby'] = $orderby;
        $_GET['order'] = $order;
        $_REQUEST['order'] = $order;
    }
}

add_action('current_screen', function ($screen): void {
    if (!($screen instanceof WP_Screen)) return;
    meza_seed_edit_screen_default_sort_request($screen);
}, 1);

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

    $orderby = $q->get('orderby');
    if (!is_scalar($orderby)) return;
    $orderby = trim((string) $orderby);
    if (!str_starts_with($orderby, 'mz_tax_')) return;

    $taxonomy = substr($orderby, 7);
    if (!is_string($taxonomy) || $taxonomy === '') return;
    if (!taxonomy_exists($taxonomy)) return;

    $post_type = $q->get('post_type');
    if (!is_scalar($post_type)) return;
    $post_type = trim((string) $post_type);
    if ($post_type === '' || !is_object_in_taxonomy($post_type, $taxonomy)) return;

    $order = $q->get('order');
    $order = is_scalar($order) ? strtoupper(trim((string) $order)) : '';
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
    if (str_contains($normalized, 'event')) return 'dashicons-calendar-alt';
    if (str_contains($normalized, 'service')) return 'dashicons-hammer';
    if (str_contains($normalized, 'team')) return 'dashicons-groups';
    if (str_contains($normalized, 'partner')) return 'dashicons-admin-multisite';
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

// Admin Columns plugin renderer: support ACF link arrays for custom columns whose
// key or label includes "link" (for example, "external_link").
add_filter('ac/column/value', function ($value, $id, $column) {
    if (!method_exists($column, 'get_raw_value')) {
        return $value;
    }

    $raw_value = $column->get_raw_value($id);

    if (
        !meza_admin_column_name_supports_link_value($column)
        && !meza_admin_column_raw_value_is_link_like($raw_value)
    ) {
        return $value;
    }

    $link_parts = meza_get_admin_link_column_parts($raw_value);
    if ($link_parts === []) {
        return $value;
    }

    $url = trim((string) ($link_parts['url'] ?? ''));
    if ($url === '') {
        return $value;
    }

    $label = trim((string) ($link_parts['title'] ?? ''));
    if ($label === '') {
        $label = meza_get_admin_link_column_display_text($url);
    }

    $target = trim((string) ($link_parts['target'] ?? ''));
    $target = in_array($target, ['_blank', '_self', '_parent', '_top'], true) ? $target : '_blank';
    $rel = ($target === '_blank') ? ' rel="noopener noreferrer"' : '';

    return '<a href="' . esc_url($url) . '" target="' . esc_attr($target) . '"' . $rel . '>' . esc_html($label) . '</a>';
}, 110, 3);

// Admin Columns plugin renderer: render event date custom fields as the
// normalized start/end date range even when ACP does not format the field.
add_filter('ac/column/value', function ($value, $id, $column) {
    $id = (int) $id;
    if ($id <= 0 || !meza_admin_column_should_render_event_date($column, $id, $value)) {
        return $value;
    }

    $start_raw = meza_get_event_admin_column_datetime_value($id, 'start');
    if ($start_raw === '') {
        return $value;
    }

    $end_raw = meza_get_event_admin_column_datetime_value($id, 'end');
    $date_html = meza_get_event_admin_datetime_range_html($start_raw, $end_raw);

    return $date_html !== '' ? $date_html : $value;
}, 115, 3);

add_filter('ac/column/render', function ($value, $context, $id) {
    $id = (int) $id;
    if ($id <= 0 || !meza_admin_column_should_render_event_date($context, $id, $value)) {
        return $value;
    }

    $start_raw = meza_get_event_admin_column_datetime_value($id, 'start');
    if ($start_raw === '') {
        return $value;
    }

    $end_raw = meza_get_event_admin_column_datetime_value($id, 'end');
    $date_html = meza_get_event_admin_datetime_range_html($start_raw, $end_raw);

    return $date_html !== '' ? $date_html : $value;
}, 115, 5);

// Admin Columns plugin renderer: render date-like custom fields when ACP
// exposes a standalone Date column backed by ACF/meta instead of post_date.
add_filter('ac/column/value', function ($value, $id, $column) {
    $id = (int) $id;
    if ($id <= 0 || !meza_admin_column_should_render_generic_date($column, $id)) {
        return $value;
    }

    $display_value = meza_get_post_admin_date_display_value($id, meza_get_admin_column_identifiers($column));
    if ($display_value === '') {
        return $value;
    }

    return esc_html($display_value);
}, 116, 3);

add_filter('ac/column/render', function ($value, $context, $id) {
    $id = (int) $id;
    if ($id <= 0 || !meza_admin_column_should_render_generic_date($context, $id)) {
        return $value;
    }

    $display_value = meza_get_post_admin_date_display_value($id, meza_get_admin_column_identifiers($context));
    if ($display_value === '') {
        return $value;
    }

    return esc_html($display_value);
}, 116, 5);

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

add_action('admin_head', function (): void {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen)) {
        return;
    }

    if ((string) ($screen->base ?? '') !== 'edit' && (string) ($screen->id ?? '') !== 'users') {
        return;
    }
?>
    <script id="meza-admin-contact-column-normalizer">
        (() => {
            const phoneTokens = ['phone', 'telephone', 'tel', 'mobile', 'cell', 'fax'];
            const emailTokens = ['email', 'e-mail'];

            const normalize = (value) => String(value || '')
                .toLowerCase()
                .replace(/\s+/g, ' ')
                .trim();

            const hasToken = (value, tokens) => {
                const normalized = normalize(value);
                return tokens.some((token) => normalized.includes(token));
            };

            const getColumnType = (cell) => {
                if (!(cell instanceof HTMLElement)) return '';

                const haystack = [
                    String(cell.id || ''),
                    String(cell.className || ''),
                    String(cell.textContent || ''),
                ].join(' ');

                if (hasToken(haystack, emailTokens)) return 'email';
                if (hasToken(haystack, phoneTokens)) return 'phone';

                return '';
            };

            const extractEmails = (value) => {
                const matches = String(value || '').match(/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/gi);
                return Array.from(new Set((matches || []).map((email) => email.trim()).filter(Boolean)));
            };

            const parsePhone = (value) => {
                let text = String(value || '').trim();
                if (!text) return null;

                let extension = '';
                const extensionMatch = text.match(/(?:ext\.?|extension|x)\s*[:.]?\s*(\d+)$/i);
                if (extensionMatch) {
                    extension = String(extensionMatch[1] || '').trim();
                    text = text.replace(/(?:ext\.?|extension|x)\s*[:.]?\s*\d+$/i, '').trim();
                }

                let digits = text.replace(/\D+/g, '');
                if (!digits) return null;
                if (digits.length === 11 && digits.startsWith('1')) {
                    digits = digits.slice(1);
                }
                if (digits.length !== 10) return null;

                let display = `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`;
                let tel = `+1${digits}`;

                if (extension) {
                    display += ` ext. ${extension}`;
                    tel += `;ext=${extension}`;
                }

                return { display, tel };
            };

            const extractPhones = (value) => {
                const parts = String(value || '')
                    .split(/[\r\n;,]+/)
                    .map((part) => part.trim())
                    .filter(Boolean);

                const parsed = parts
                    .map((part) => parsePhone(part))
                    .filter(Boolean);

                return parsed.filter((phone, index, all) => (
                    all.findIndex((candidate) => candidate.tel === phone.tel) === index
                ));
            };

            const maybeNormalizeCell = (cell, type) => {
                if (!(cell instanceof HTMLElement) || !type) return;
                if (cell.querySelector(type === 'email' ? 'a[href^="mailto:"]' : 'a[href^="tel:"]')) return;
                if (cell.querySelector('.row-actions, .toggle-row')) return;

                const rawText = String(cell.textContent || '').trim();
                if (!rawText || rawText === '—') return;

                if (type === 'email') {
                    const emails = extractEmails(rawText);
                    if (!emails.length) return;

                    cell.innerHTML = emails.map((email) => (
                        `<a href="mailto:${email}">${email}</a>`
                    )).join('<br>');
                    return;
                }

                const phones = extractPhones(rawText);
                if (!phones.length) return;

                cell.innerHTML = phones.map((phone) => (
                    `<a href="tel:${phone.tel}">${phone.display}</a>`
                )).join('<br>');
            };

            const syncTable = (table) => {
                if (!(table instanceof HTMLTableElement)) return;

                const headerRow = table.tHead?.rows?.[table.tHead.rows.length - 1];
                if (!(headerRow instanceof HTMLTableRowElement)) return;

                const columnTypes = Array.from(headerRow.children).map((cell) => getColumnType(cell));
                if (!columnTypes.some(Boolean)) return;

                Array.from(table.tBodies).forEach((tbody) => {
                    Array.from(tbody.rows).forEach((row) => {
                        Array.from(row.children).forEach((cell, index) => {
                            maybeNormalizeCell(cell, columnTypes[index] || '');
                        });
                    });
                });
            };

            const sync = () => {
                document.querySelectorAll('table.wp-list-table').forEach(syncTable);
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', sync, { once: true });
            } else {
                sync();
            }

            const observer = new MutationObserver(sync);
            observer.observe(document.documentElement, {
                childList: true,
                subtree: true,
            });
        })();
    </script>
<?php
});

add_action('admin_head', function (): void {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || (string) ($screen->base ?? '') !== 'edit') {
        return;
    }
?>
    <script id="meza-admin-post-link-line-breaks">
        (() => {
            const editPostPattern = /(?:^|\/)post\.php\?(?=[^#]*\bpost=\d+\b)(?=[^#]*\baction=edit\b)/i;
            const ignoreSelector = '.row-actions, .ac-show-more__toggle, .ac-show-more__divider';

            const isEditPostLink = (node) => (
                node instanceof HTMLAnchorElement
                && editPostPattern.test(String(node.getAttribute('href') || ''))
                && !node.closest(ignoreSelector)
            );

            const hasOnlySeparators = (value) => /^[\s,\u00a0]+$/.test(String(value || ''));

            const shouldFormatContainer = (container) => {
                if (!(container instanceof HTMLElement)) return false;

                const links = Array.from(container.querySelectorAll('a')).filter(isEditPostLink);
                return links.length >= 2;
            };

            const formatContainer = (container) => {
                if (!(container instanceof HTMLElement)) return;
                if (container.dataset.mezaPostLinkLineBreaks === 'true') return;
                if (!shouldFormatContainer(container)) return;

                const walker = document.createTreeWalker(
                    container,
                    NodeFilter.SHOW_TEXT,
                    {
                        acceptNode(node) {
                            if (!(node instanceof Text)) {
                                return NodeFilter.FILTER_REJECT;
                            }

                            const parent = node.parentElement;
                            if (!parent || parent.closest(ignoreSelector)) {
                                return NodeFilter.FILTER_REJECT;
                            }

                            return hasOnlySeparators(node.textContent || '')
                                ? NodeFilter.FILTER_ACCEPT
                                : NodeFilter.FILTER_REJECT;
                        },
                    }
                );

                const separatorNodes = [];
                while (walker.nextNode()) {
                    separatorNodes.push(walker.currentNode);
                }

                separatorNodes.forEach((node) => {
                    const lineBreak = document.createElement('br');
                    node.parentNode?.replaceChild(lineBreak, node);
                });

                container.dataset.mezaPostLinkLineBreaks = 'true';
            };

            const sync = () => {
                document.querySelectorAll(
                    'table.wp-list-table td, table.wp-list-table .ac-show-more__content'
                ).forEach(formatContainer);
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', sync, { once: true });
            } else {
                sync();
            }

            const observer = new MutationObserver(sync);
            observer.observe(document.documentElement, {
                childList: true,
                subtree: true,
            });
        })();
    </script>
<?php
});
