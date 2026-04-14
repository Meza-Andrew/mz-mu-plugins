<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MZF_SUBMISSIONS_PAGE_SLUG')) {
    define('MZF_SUBMISSIONS_PAGE_SLUG', 'submissions');
}

if (!defined('MZF_SUBMISSIONS_PAGE_LEGACY_SLUG')) {
    define('MZF_SUBMISSIONS_PAGE_LEGACY_SLUG', 'mzf-submissions');
}

if (!function_exists('mzf_manage_submissions_capability')) {
    function mzf_manage_submissions_capability(): string
    {
        if (function_exists('meza_submission_manager_capability')) {
            return (string) meza_submission_manager_capability();
        }

        return 'mzf_manage_submissions';
    }
}

if (!function_exists('mzf_user_can_manage_submissions')) {
    function mzf_user_can_manage_submissions(): bool
    {
        return current_user_can('manage_options') || current_user_can(mzf_manage_submissions_capability());
    }
}

if (!function_exists('mzf_is_submissions_admin_page')) {
    function mzf_is_submissions_admin_page(): bool
    {
        if (!is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';

        return in_array($page, [MZF_SUBMISSIONS_PAGE_SLUG, MZF_SUBMISSIONS_PAGE_LEGACY_SLUG], true);
    }
}

add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=form',
        'Form Submission Log',
        'Submissions',
        mzf_manage_submissions_capability(),
        MZF_SUBMISSIONS_PAGE_SLUG,
        'mzf_render_submission_log_admin_page'
    );
});

add_action('admin_init', function (): void {
    if (!is_admin()) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    if ($page !== MZF_SUBMISSIONS_PAGE_LEGACY_SLUG) {
        return;
    }

    if (!mzf_user_can_manage_submissions()) {
        return;
    }

    $redirect_args = wp_unslash($_GET);
    if (!is_array($redirect_args)) {
        $redirect_args = [];
    }
    $redirect_args['page'] = MZF_SUBMISSIONS_PAGE_SLUG;

    wp_safe_redirect(add_query_arg($redirect_args, admin_url('edit.php')));
    exit;
});

add_action('admin_enqueue_scripts', function (): void {
    if (!mzf_is_submissions_admin_page()) return;

    if (!function_exists('wp_scripts') || !function_exists('wp_default_scripts')) {
        require_once ABSPATH . WPINC . '/script-loader.php';
    }

    if (!function_exists('wp_styles') || !function_exists('wp_default_styles')) {
        require_once ABSPATH . WPINC . '/script-loader.php';
    }

    $wp_scripts = wp_scripts();
    if ($wp_scripts instanceof WP_Scripts && !isset($wp_scripts->registered['jquery'])) {
        wp_default_scripts($wp_scripts);
    }

    $wp_styles = wp_styles();
    if ($wp_styles instanceof WP_Styles && !isset($wp_styles->registered['common'])) {
        wp_default_styles($wp_styles);
    }

    foreach (['dashicons', 'common', 'forms', 'admin-menu', 'list-tables', 'edit', 'buttons'] as $style_handle) {
        if (wp_style_is($style_handle, 'registered')) {
            wp_enqueue_style($style_handle);
        }
    }

    foreach (['jquery', 'jquery-core', 'jquery-migrate', 'common', 'wp-hooks', 'wp-dom-ready', 'wp-a11y', 'wp-i18n', 'heartbeat', 'wp-auth-check', 'wp-lists', 'postbox'] as $script_handle) {
        if (wp_script_is($script_handle, 'registered')) {
            wp_enqueue_script($script_handle);
        }
    }

    $css_path = __DIR__ . '/assets/admin-shared.css';
    $css_url = '';
    if (defined('WPMU_PLUGIN_DIR') && defined('WPMU_PLUGIN_URL')) {
        $relative_css_path = str_replace(wp_normalize_path(WPMU_PLUGIN_DIR) . '/', '', wp_normalize_path($css_path));
        $css_url = trailingslashit(WPMU_PLUGIN_URL) . ltrim($relative_css_path, '/');
    } else {
        $css_url = content_url('mu-plugins/mz-mu-plugins/mz-form/assets/admin-shared.css');
    }
    $css_ver = file_exists($css_path) ? (string) filemtime($css_path) : null;
    wp_enqueue_style('mzf-admin-shared', $css_url, [], $css_ver);
});

add_filter('admin_body_class', function (string $classes): string {
    if (!mzf_is_submissions_admin_page()) {
        return $classes;
    }

    return trim($classes . ' edit-php post-type-form');
});

add_action('admin_head', function (): void {
    if (!mzf_is_submissions_admin_page()) {
        return;
    }

    mzf_render_submission_log_inline_styles();
}, 20);

add_action('admin_footer', function (): void {
    if (!mzf_is_submissions_admin_page()) {
        return;
    }

    mzf_render_submission_log_inline_script();
}, 20);

if (!function_exists('mzf_render_submission_log_inline_styles')) {
    function mzf_render_submission_log_inline_styles(): void
    {
        $css_path = __DIR__ . '/assets/admin-shared.css';
        if (!is_readable($css_path)) {
            return;
        }

        $css = file_get_contents($css_path);
        if (!is_string($css) || $css === '') {
            return;
        }

$sticky_css = <<<CSS
.mzf-submissions-table-wrap{
    width:100%;
    max-width:100%;
    max-height:calc(100vh - 260px);
    overflow:auto;
    -webkit-overflow-scrolling:touch;
    border:1px solid #c3c4c7;
    box-sizing:border-box;
    background:#fff;
    scroll-behavior:auto;
}
.mzf-submissions-table-wrap table.wp-list-table{
    width:100%;
    table-layout:fixed;
    border-collapse:separate;
    border-spacing:0;
    border:none !important;
    box-shadow:none !important;
}
.mzf-submissions-table-wrap table.wp-list-table thead,
.mzf-submissions-table-wrap table.wp-list-table tfoot{
    position:relative;
    z-index:4;
}
.mzf-submissions-table-wrap table.wp-list-table thead th,
.mzf-submissions-table-wrap table.wp-list-table thead td{
    position:sticky;
    top:0;
    z-index:5;
    background:#fff;
    border-top:none !important;
    border-bottom:none !important;
    box-shadow:inset 0 -1px 0 #ccd0d4;
    background-clip:padding-box;
}
.mzf-submissions-table-wrap table.wp-list-table tfoot th,
.mzf-submissions-table-wrap table.wp-list-table tfoot td{
    position:sticky;
    bottom:0;
    z-index:5;
    background:#fff;
    border-top:none !important;
    border-bottom:none !important;
    box-shadow:inset 0 1px 0 #ccd0d4;
    background-clip:padding-box;
}
.mzf-submissions-table-wrap table.wp-list-table tbody td{
    position:relative;
    z-index:1;
    background-clip:padding-box;
}
.mzf-submissions-table-wrap table.wp-list-table thead .check-column{
    position:sticky;
    top:0;
    left:0;
    z-index:6;
    background:#fff;
    border-right:none !important;
    border-top:none !important;
    border-bottom:none !important;
    box-shadow:inset 0 -1px 0 #ccd0d4;
    background-clip:padding-box;
}
.mzf-submissions-table-wrap table.wp-list-table tfoot .check-column{
    position:sticky;
    bottom:0;
    left:0;
    z-index:6;
    background:#fff;
    border-right:none !important;
    border-top:none !important;
    border-bottom:none !important;
    box-shadow:inset 0 1px 0 #ccd0d4;
    background-clip:padding-box;
}
.mzf-submissions-table-wrap .widefat .check-column{
    padding:0;
}
CSS;

        echo "<style id='mzf-admin-shared-inline'>\n" . $css . "\n" . $sticky_css . "\n</style>";
    }
}

if (!function_exists('mzf_render_submission_log_inline_script')) {
    function mzf_render_submission_log_inline_script(): void
    {
        echo "<script id='mzf-submissions-inline-script'>(function(){var wrap=document.querySelector('.mzf-submissions-table-wrap');if(wrap){wrap.scrollLeft=0;}var toggles=document.querySelectorAll('.mzf-select-all');if(!toggles.length)return;var rows=document.querySelectorAll('.mzf-select-row');var sync=function(checked){for(var i=0;i<toggles.length;i++){toggles[i].checked=checked;}};for(var i=0;i<toggles.length;i++){toggles[i].addEventListener('change',function(){for(var j=0;j<rows.length;j++){rows[j].checked=this.checked;}sync(this.checked);});}for(var k=0;k<rows.length;k++){rows[k].addEventListener('change',function(){var checked=rows.length>0;for(var m=0;m<rows.length;m++){if(!rows[m].checked){checked=false;break;}}sync(checked);});}})();</script>";
    }
}

if (!function_exists('mzf_bool_to_label')) {
    function mzf_bool_to_label(string $value): string
    {
        return ($value === '1') ? 'Yes' : 'No';
    }
}

if (!function_exists('mzf_get_client_abbr')) {
    function mzf_get_client_abbr(): string
    {
        $abbr = '';
        if (function_exists('abbr')) {
            $abbr = (string) abbr();
        }
        if ($abbr === '') {
            $abbr = (string) apply_filters('theme_abbr', 'client');
        }
        $abbr = strtolower(trim($abbr));
        $abbr = preg_replace('/[^a-z0-9_-]/', '', $abbr);
        return ($abbr !== '') ? $abbr : 'client';
    }
}

if (!function_exists('mzf_submission_export_filename')) {
    function mzf_submission_export_filename(): string
    {
        $ts = gmdate('Ymd-His') . '-' . wp_rand(1000, 9999);
        return mzf_get_client_abbr() . '-website-form-submissions-' . $ts . '.csv';
    }
}

if (!function_exists('mzf_submission_sort_value')) {
    function mzf_submission_sort_value(int $submission_id, string $sort): string
    {
        if ($sort === 'last_name') {
            return strtolower(trim(mzf_submission_text_value($submission_id, ['LastName', 'ContactLastName'], '_mzf_last_name')));
        }

        return strtolower(trim(mzf_submission_text_value($submission_id, ['FirstName', 'ContactFirstName'], '_mzf_first_name')));
    }
}

if (!function_exists('mzf_compare_submission_ids_by_name')) {
    function mzf_compare_submission_ids_by_name(int $left_id, int $right_id, string $sort, string $order): int
    {
        $sort = ($sort === 'last_name') ? 'last_name' : 'first_name';
        $secondary_sort = ($sort === 'last_name') ? 'first_name' : 'last_name';
        $direction = (strtolower($order) === 'desc') ? -1 : 1;

        $left_primary = mzf_submission_sort_value($left_id, $sort);
        $right_primary = mzf_submission_sort_value($right_id, $sort);
        $left_primary_blank = ($left_primary === '');
        $right_primary_blank = ($right_primary === '');
        if ($left_primary_blank !== $right_primary_blank) {
            return $left_primary_blank ? 1 : -1;
        }

        if ($left_primary !== $right_primary) {
            return ($left_primary < $right_primary) ? -1 * $direction : 1 * $direction;
        }

        $left_secondary = mzf_submission_sort_value($left_id, $secondary_sort);
        $right_secondary = mzf_submission_sort_value($right_id, $secondary_sort);
        $left_secondary_blank = ($left_secondary === '');
        $right_secondary_blank = ($right_secondary === '');
        if ($left_secondary_blank !== $right_secondary_blank) {
            return $left_secondary_blank ? 1 : -1;
        }

        if ($left_secondary !== $right_secondary) {
            return ($left_secondary < $right_secondary) ? -1 * $direction : 1 * $direction;
        }

        $left_post = get_post($left_id);
        $right_post = get_post($right_id);
        $left_date = ($left_post instanceof WP_Post) ? strtotime((string) $left_post->post_date_gmt) : 0;
        $right_date = ($right_post instanceof WP_Post) ? strtotime((string) $right_post->post_date_gmt) : 0;
        if ($left_date !== $right_date) {
            return ($left_date > $right_date) ? -1 : 1;
        }

        if ($left_id === $right_id) {
            return 0;
        }

        return ($left_id > $right_id) ? -1 : 1;
    }
}

if (!function_exists('mzf_submission_status_query_clause')) {
    function mzf_submission_status_query_clause(string $status): array
    {
        $status = sanitize_key($status);
        if ($status === '') {
            return [];
        }

        $group = mzf_submission_status_group($status);
        if ($group === 'success') {
            return [
                'key' => '_mzf_delivery_status',
                'value' => 'success',
                'compare' => '=',
            ];
        }

        if ($group === 'spam_detected') {
            return [
                'key' => '_mzf_delivery_status',
                'value' => '^(validation_honeypot|error_recaptcha_failed|error_recaptcha_token_missing)$',
                'compare' => 'REGEXP',
            ];
        }

        if ($group === 'failure_validation') {
            return [
                'relation' => 'AND',
                [
                    'key' => '_mzf_delivery_status',
                    'value' => '^(validation_honeypot)$',
                    'compare' => 'NOT REGEXP',
                ],
                [
                    'key' => '_mzf_delivery_status',
                    'value' => '^validation_',
                    'compare' => 'REGEXP',
                ],
            ];
        }

        return [
            'relation' => 'AND',
            [
                'key' => '_mzf_delivery_status',
                'value' => 'success',
                'compare' => '!=',
            ],
            [
                'key' => '_mzf_delivery_status',
                'value' => '^(validation_honeypot|error_recaptcha_failed|error_recaptcha_token_missing)$',
                'compare' => 'NOT REGEXP',
            ],
            [
                'key' => '_mzf_delivery_status',
                'value' => '^validation_',
                'compare' => 'NOT REGEXP',
            ],
        ];
    }
}

if (!function_exists('mzf_title_case_slug_value')) {
    function mzf_title_case_slug_value(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';
        $value = str_replace(['-', '_'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?: $value;
        return ucwords($value);
    }
}

if (!function_exists('mzf_submissions_redirect_url')) {
    function mzf_submissions_redirect_url(array $default_args = []): string
    {
        $default_url = add_query_arg(array_merge([
            'post_type' => 'form',
            'page' => MZF_SUBMISSIONS_PAGE_SLUG,
        ], $default_args), admin_url('edit.php'));

        $candidates = [];
        if (isset($_REQUEST['redirect_to'])) {
            $candidates[] = esc_url_raw((string) wp_unslash($_REQUEST['redirect_to']));
        }
        $referer = wp_get_referer();
        if (is_string($referer) && $referer !== '') {
            $candidates[] = esc_url_raw($referer);
        }

        foreach ($candidates as $candidate) {
            if ($candidate === '') continue;
            if (
                strpos($candidate, 'page=' . MZF_SUBMISSIONS_PAGE_SLUG) !== false ||
                strpos($candidate, 'page=' . MZF_SUBMISSIONS_PAGE_LEGACY_SLUG) !== false
            ) {
                return $candidate;
            }
        }

        return $default_url;
    }
}

if (!function_exists('mzf_submission_meta_truthy')) {
    function mzf_submission_meta_truthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on', 'y'], true);
    }
}

if (!function_exists('mzf_submission_zip_code')) {
    function mzf_submission_zip_code(int $submission_id): string
    {
        $sources = [
            get_post_meta($submission_id, '_mzf_payload', true),
            get_post_meta($submission_id, '_mzf_submitted', true),
        ];

        $extract_from_text = static function (string $value): string {
            $value = trim($value);
            if ($value === '') {
                return '';
            }

            if (preg_match('/\b(\d{5}(?:-\d{4})?)\b/', $value, $matches)) {
                return (string) ($matches[1] ?? '');
            }

            return '';
        };

        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }

            $explicit_keys = [
                'Zip',
                'ZipCode',
                'zip',
                'zipcode',
                'zip_code',
                'postal_code',
                'ReceivingAddressPostal',
                'ReceivingAddressZip',
                'receivingaddresspostal',
                'receivingaddresszip',
            ];

            foreach ($explicit_keys as $key) {
                if (!array_key_exists($key, $source)) {
                    continue;
                }

                $candidate = $extract_from_text((string) $source[$key]);
                if ($candidate !== '') {
                    return $candidate;
                }
            }

            $address_keys = [
                'LocationDisplay',
                'ReceivingAddressDisplay',
                'ReceivingAddress',
                'Address',
                'locationdisplay',
                'receivingaddressdisplay',
                'receivingaddress',
                'address',
            ];

            foreach ($address_keys as $key) {
                if (!array_key_exists($key, $source)) {
                    continue;
                }

                $candidate = $extract_from_text((string) $source[$key]);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        return '';
    }
}

if (!function_exists('mzf_submission_sources')) {
    function mzf_submission_sources(int $submission_id): array
    {
        $payload = get_post_meta($submission_id, '_mzf_payload', true);
        $submitted = get_post_meta($submission_id, '_mzf_submitted', true);

        return [
            'payload' => is_array($payload) ? $payload : [],
            'submitted' => is_array($submitted) ? $submitted : [],
        ];
    }
}

if (!function_exists('mzf_submission_text_value')) {
    function mzf_submission_text_value(int $submission_id, array $keys, string $meta_key = ''): string
    {
        if ($meta_key !== '') {
            $meta_value = trim((string) get_post_meta($submission_id, $meta_key, true));
            if ($meta_value !== '') {
                return $meta_value;
            }
        }

        $sources = mzf_submission_sources($submission_id);
        foreach (['payload', 'submitted'] as $source_key) {
            $source = (array) ($sources[$source_key] ?? []);
            foreach ($keys as $key) {
                $candidates = array_values(array_unique(array_filter([
                    (string) $key,
                    sanitize_key((string) $key),
                    strtolower((string) $key),
                ])));
                foreach ($candidates as $candidate_key) {
                    if (!array_key_exists($candidate_key, $source)) {
                        continue;
                    }
                    $value = trim((string) $source[$candidate_key]);
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return '';
    }
}

if (!function_exists('mzf_submission_int_value')) {
    function mzf_submission_int_value(int $submission_id, array $keys, string $meta_key = ''): int
    {
        if ($meta_key !== '') {
            $meta_value = (int) get_post_meta($submission_id, $meta_key, true);
            if ($meta_value > 0) {
                return $meta_value;
            }
        }

        $sources = mzf_submission_sources($submission_id);
        foreach (['payload', 'submitted'] as $source_key) {
            $source = (array) ($sources[$source_key] ?? []);
            foreach ($keys as $key) {
                $candidates = array_values(array_unique(array_filter([
                    (string) $key,
                    sanitize_key((string) $key),
                    strtolower((string) $key),
                ])));
                foreach ($candidates as $candidate_key) {
                    if (!array_key_exists($candidate_key, $source)) {
                        continue;
                    }
                    $value = (int) $source[$candidate_key];
                    if ($value > 0) {
                        return $value;
                    }
                }
            }
        }

        return 0;
    }
}

if (!function_exists('mzf_submission_display_name')) {
    function mzf_submission_display_name(string $first_name, string $last_name): string
    {
        return trim($first_name . ' ' . $last_name);
    }
}

if (!function_exists('mzf_submission_form_identifiers')) {
    function mzf_submission_form_identifiers(int $submission_id): array
    {
        $form_slug = sanitize_key(mzf_submission_text_value($submission_id, ['FormSlug'], '_mzf_form_slug'));
        $form_post_id = mzf_submission_int_value($submission_id, ['FormPostId'], '_mzf_form_post_id');

        if ($form_post_id <= 0 && $form_slug !== '') {
            $form_post = get_page_by_path($form_slug, OBJECT, 'form');
            if ($form_post instanceof WP_Post) {
                $form_post_id = (int) $form_post->ID;
            }
        }

        if ($form_slug === '' && $form_post_id > 0) {
            $resolved_form_slug = sanitize_title((string) get_post_field('post_name', $form_post_id));
            if ($resolved_form_slug !== '') {
                $form_slug = $resolved_form_slug;
            }
        }

        return [
            'form_slug' => $form_slug,
            'form_post_id' => $form_post_id,
        ];
    }
}

if (!function_exists('mzf_submission_matches_filters')) {
    function mzf_submission_matches_filters(
        int $submission_id,
        int $filter_page_id = 0,
        int $filter_form_post_id = 0,
        string $filter_form_slug = '',
        string $filter_admin = '',
        string $filter_status = ''
    ): bool {
        if ($filter_page_id > 0) {
            $page_id = mzf_submission_int_value($submission_id, ['PageId'], '_mzf_page_id');
            if ($page_id !== $filter_page_id) {
                return false;
            }
        }

        if ($filter_form_post_id > 0 || $filter_form_slug !== '') {
            $form_identifiers = mzf_submission_form_identifiers($submission_id);
            if ($filter_form_post_id > 0 && (int) $form_identifiers['form_post_id'] !== $filter_form_post_id) {
                return false;
            }
            if ($filter_form_slug !== '' && (string) $form_identifiers['form_slug'] !== $filter_form_slug) {
                return false;
            }
        }

        if ($filter_admin !== '') {
            $admin_to = trim((string) get_post_meta($submission_id, '_mzf_admin_to', true));
            if ($admin_to !== $filter_admin) {
                return false;
            }
        }

        if ($filter_status !== '') {
            $raw_status = (string) get_post_meta($submission_id, '_mzf_delivery_status', true);
            if (mzf_submission_status_group($raw_status) !== $filter_status) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('mzf_submission_matches_view')) {
    function mzf_submission_matches_view(int $submission_id, string $view): bool
    {
        if ($view === 'phone') {
            return mzf_submission_text_value($submission_id, ['Phone', 'ContactPhone'], '_mzf_phone') !== '';
        }

        if ($view === 'zip_code') {
            return mzf_submission_zip_code($submission_id) !== '';
        }

        return true;
    }
}

if (!function_exists('mzf_submission_humanize_status_reason')) {
    function mzf_submission_humanize_status_reason(string $raw_status): string
    {
        $raw_status = sanitize_key($raw_status);
        if ($raw_status === '') {
            return '';
        }

        if (strpos($raw_status, 'validation_') === 0) {
            $raw_status = substr($raw_status, strlen('validation_'));
        } elseif (strpos($raw_status, 'error_') === 0) {
            $raw_status = substr($raw_status, strlen('error_'));
        }

        return mzf_title_case_slug_value($raw_status);
    }
}

if (!function_exists('mzf_submission_status_tooltip')) {
    function mzf_submission_status_tooltip(int $submission_id, string $raw_status = ''): string
    {
        $raw_status = sanitize_key($raw_status !== '' ? $raw_status : (string) get_post_meta($submission_id, '_mzf_delivery_status', true));
        $status_group = mzf_submission_status_group($raw_status);
        if ($status_group === 'success') {
            return '';
        }

        $status_label = mzf_submission_status_label($raw_status);
        $error_message = trim((string) get_post_meta($submission_id, '_mzf_error_message', true));
        if ($error_message !== '') {
            $error_lines = preg_split('/\s*\|\s*/', $error_message) ?: [$error_message];
            $error_lines = array_values(array_filter($error_lines, static function ($line): bool {
                return stripos(trim((string) $line), 'Invalid fields:') !== 0;
            }));
            $error_message = implode("\n", $error_lines);
            $error_message = preg_replace('/\s*\|\s*/', "\n", $error_message) ?: $error_message;
            if ($error_message !== '') {
                return $status_label . "\n" . $error_message;
            }

            return $status_label;
        }

        $reason = mzf_submission_humanize_status_reason($raw_status);
        if ($reason !== '') {
            return $status_label . "\n" . $reason;
        }

        return $status_label;
    }
}

if (!function_exists('mzf_submission_added_to_crm_data')) {
    function mzf_submission_added_to_crm_data(int $submission_id): array
    {
        $marketing_sync = get_post_meta($submission_id, '_mzf_marketing_sync', true);
        $env = strtolower(trim((string) get_post_meta($submission_id, '_mzf_env', true)));
        if ($env === '') {
            $env = function_exists('wp_get_environment_type')
                ? strtolower((string) wp_get_environment_type())
                : 'production';
        }
        $is_live_env = in_array($env, ['production', 'qa'], true);

        $newsletter_opt_in = ((string) get_post_meta($submission_id, '_mzf_newsletter_opt_in', true) === '1');
        if (!$newsletter_opt_in) {
            $sources = function_exists('mzf_submission_sources') ? mzf_submission_sources($submission_id) : ['payload' => [], 'submitted' => []];
            foreach (['payload', 'submitted'] as $source_key) {
                $source = (array) ($sources[$source_key] ?? []);
                foreach (['NewsletterSignup', 'newslettersignup', 'newsletter_signup'] as $key) {
                    if (array_key_exists($key, $source) && mzf_submission_meta_truthy($source[$key])) {
                        $newsletter_opt_in = true;
                        break 2;
                    }
                }
            }
        }

        if (!$newsletter_opt_in) {
            return ['text' => '', 'url' => '', 'csv' => ''];
        }

        $stored_crm_label = trim((string) get_post_meta($submission_id, '_mzf_crm_platform_label', true));
        $crm_platform = (string) get_post_meta($submission_id, '_mzf_crm_platform', true);
        if ($crm_platform === '' && is_array($marketing_sync)) {
            $crm_platform = (string) ($marketing_sync['provider'] ?? '');
        }
        if ($crm_platform === '' && function_exists('mzf_selected_crm_platform')) {
            $crm_platform = (string) mzf_selected_crm_platform();
        }
        if ($crm_platform === '' && function_exists('mzf_crm_platform')) {
            $crm_platform = (string) mzf_crm_platform();
        }
        if ($crm_platform === '' && function_exists('mzf_marketing_provider')) {
            $crm_platform = (string) mzf_marketing_provider();
        }

        $crm_label = $stored_crm_label;
        if ($crm_label === '' && function_exists('mzf_crm_platform_label')) {
            $crm_label = mzf_crm_platform_label($crm_platform);
        }

        if (is_array($marketing_sync)) {
            $sync_ok = mzf_submission_meta_truthy((string) ($marketing_sync['ok'] ?? ''));
            if ($sync_ok) {
                $provider = strtolower(trim((string) ($marketing_sync['provider'] ?? '')));
                $sync_label = trim((string) ($marketing_sync['label'] ?? ''));
                $sync_contact_url = trim((string) ($marketing_sync['contact_url'] ?? ''));
                if ($provider === 'constant_contact') {
                    $sync_contact_id = trim((string) ($marketing_sync['contact_id'] ?? ''));
                    if ($sync_contact_id !== '') {
                        $sync_contact_url = 'https://app.constantcontact.com/contacts/' . rawurlencode($sync_contact_id) . '/profile';
                    }
                }

                $connected_label = $sync_label;
                if ($connected_label === '' && function_exists('mzf_crm_platform_label')) {
                    $connected_label = mzf_crm_platform_label($provider);
                }
                if ($connected_label === '') {
                    $connected_label = $crm_label;
                }

                if ($connected_label !== '') {
                    return [
                        'text' => $connected_label,
                        'url' => $sync_contact_url,
                        'csv' => $connected_label,
                    ];
                }
            }
        }

        if ($crm_label !== '') {
            return [
                'text' => $crm_label,
                'url' => '',
                'csv' => $crm_label,
            ];
        }

        return ['text' => '', 'url' => '', 'csv' => ''];
    }
}

if (!function_exists('mzf_submission_has_crm_entry')) {
    function mzf_submission_has_crm_entry(int $submission_id): bool
    {
        $added_to_crm = mzf_submission_added_to_crm_data($submission_id);

        return $added_to_crm['text'] !== '';
    }
}

if (!function_exists('mzf_submission_query_ids_with_crm_entries')) {
    function mzf_submission_query_ids_with_crm_entries(array $query_args): array
    {
        $query_args['posts_per_page'] = -1;
        $query_args['paged'] = 1;
        $query_args['no_found_rows'] = true;
        $query_args['fields'] = 'ids';

        $query = new WP_Query($query_args);
        $matching_ids = [];

        foreach ((array) $query->posts as $submission_id) {
            $submission_id = (int) $submission_id;
            if ($submission_id <= 0) {
                continue;
            }

            if (mzf_submission_has_crm_entry($submission_id)) {
                $matching_ids[] = $submission_id;
            }
        }

        return $matching_ids;
    }
}

if (!function_exists('mzf_render_submission_log_admin_page')) {
    function mzf_render_submission_log_admin_page(): void
    {
        if (!mzf_user_can_manage_submissions()) return;

        $base_admin_url = add_query_arg([
            'post_type' => 'form',
            'page' => MZF_SUBMISSIONS_PAGE_SLUG,
        ], admin_url('edit.php'));

        $sort = isset($_GET['sort']) ? sanitize_key((string) wp_unslash($_GET['sort'])) : 'date';
        $order = isset($_GET['order']) ? strtolower(sanitize_key((string) wp_unslash($_GET['order']))) : 'desc';
        $allowed_sorts = ['date', 'name'];
        if (!in_array($sort, $allowed_sorts, true)) $sort = 'date';
        if (!in_array($order, ['asc', 'desc'], true)) $order = 'desc';
        $view = isset($_GET['view']) ? sanitize_key((string) wp_unslash($_GET['view'])) : 'all';
        $allowed_views = ['all', 'locked', 'unlocked', 'crm_entries', 'deleted'];
        if (!in_array($view, $allowed_views, true)) $view = 'all';
        $active_statuses = ['private', 'publish', 'draft', 'pending', 'future'];

        $filter_page_id = isset($_GET['filter_page_id']) ? absint($_GET['filter_page_id']) : 0;
        $filter_form_post_id = isset($_GET['filter_form_post_id']) ? absint($_GET['filter_form_post_id']) : 0;
        $filter_form_slug = isset($_GET['filter_form_slug']) ? sanitize_key((string) wp_unslash($_GET['filter_form_slug'])) : '';
        $filter_admin = isset($_GET['filter_admin']) ? sanitize_text_field((string) wp_unslash($_GET['filter_admin'])) : '';
        $filter_status = isset($_GET['filter_status']) ? sanitize_key((string) wp_unslash($_GET['filter_status'])) : '';
        if ($filter_status !== '') {
            $filter_status = mzf_submission_status_group($filter_status);
        }
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

        $state_args = [
            'sort' => $sort,
            'order' => $order,
            'view' => $view,
        ];
        if ($filter_page_id > 0) $state_args['filter_page_id'] = $filter_page_id;
        if ($filter_form_post_id > 0) {
            $state_args['filter_form_post_id'] = $filter_form_post_id;
        } elseif ($filter_form_slug !== '') {
            $state_args['filter_form_slug'] = $filter_form_slug;
        }
        if ($filter_admin !== '') $state_args['filter_admin'] = $filter_admin;
        if ($filter_status !== '') $state_args['filter_status'] = $filter_status;
        $current_view_url = add_query_arg(array_merge($state_args, ['paged' => $paged]), $base_admin_url);

        $build_admin_url = static function (array $overrides = [], array $remove = []) use ($base_admin_url, $state_args): string {
            $args = $state_args;
            unset($args['paged']);

            foreach ($remove as $key) {
                unset($args[$key]);
            }

            foreach ($overrides as $key => $value) {
                if ($value === '' || $value === null || $value === 0) {
                    unset($args[$key]);
                    continue;
                }
                $args[$key] = $value;
            }

            return add_query_arg($args, $base_admin_url);
        };

        $is_filtered = (
            $filter_page_id > 0 ||
            $filter_form_post_id > 0 ||
            $filter_form_slug !== '' ||
            $filter_admin !== '' ||
            $filter_status !== ''
        );
        $active_filter_chips = [];
        if ($filter_page_id > 0) {
            $page_value = (string) get_the_title($filter_page_id);
            if ($page_value === '') $page_value = 'ID ' . $filter_page_id;
            $active_filter_chips[] = [
                'label' => 'Page',
                'value' => $page_value,
                'remove_url' => $build_admin_url([], ['filter_page_id', 'paged']),
            ];
        }
        if ($filter_form_post_id > 0) {
            $form_value = (string) get_the_title($filter_form_post_id);
            if ($form_value === '') $form_value = 'ID ' . $filter_form_post_id;
            $active_filter_chips[] = [
                'label' => 'Form',
                'value' => $form_value,
                'remove_url' => $build_admin_url([], ['filter_form_post_id', 'filter_form_slug', 'paged']),
            ];
        } elseif ($filter_form_slug !== '') {
            $active_filter_chips[] = [
                'label' => 'Form',
                'value' => mzf_title_case_slug_value($filter_form_slug),
                'remove_url' => $build_admin_url([], ['filter_form_post_id', 'filter_form_slug', 'paged']),
            ];
        }
        if ($filter_admin !== '') {
            $active_filter_chips[] = [
                'label' => 'Admin',
                'value' => $filter_admin,
                'remove_url' => $build_admin_url([], ['filter_admin', 'paged']),
            ];
        }
        if ($filter_status !== '') {
            $active_filter_chips[] = [
                'label' => 'Status',
                'value' => mzf_submission_status_label($filter_status),
                'remove_url' => $build_admin_url([], ['filter_status', 'paged']),
            ];
        }

        $export_url = wp_nonce_url(
            add_query_arg(['action' => 'mzf_export_submissions_csv'], admin_url('admin-post.php')),
            'mzf_export_submissions_csv'
        );
        $clear_all_url = wp_nonce_url(
            add_query_arg([
                'action' => 'mzf_clear_all_submissions',
                'redirect_to' => $current_view_url,
            ], admin_url('admin-post.php')),
            'mzf_clear_all_submissions'
        );
        $empty_trash_url = wp_nonce_url(
            add_query_arg([
                'action' => 'mzf_empty_trash_submissions',
                'redirect_to' => $current_view_url,
            ], admin_url('admin-post.php')),
            'mzf_empty_trash_submissions'
        );

        echo '<div class="wrap">';
        echo '<h1>Form Submission Log</h1>';
        $view_labels = [
            'all' => 'All',
            'locked' => 'Saved',
            'unlocked' => 'Unsaved',
            'phone' => 'Phone',
            'zip_code' => 'Zip Code',
            'crm_entries' => 'CRM Entries',
            'deleted' => 'Trash',
        ];
        $view_counts = [];
        foreach (array_keys($view_labels) as $view_key) {
            $view_query_args = [
                'post_type' => 'mzf_submission',
                'posts_per_page' => 1,
                'paged' => 1,
                'no_found_rows' => false,
                'fields' => 'ids',
            ];
            if ($view_key === 'deleted') {
                $view_query_args['post_status'] = 'trash';
            } elseif ($view_key === 'crm_entries') {
                $view_query_args['post_status'] = $active_statuses;
            } else {
                $view_query_args['post_status'] = $active_statuses;
                if ($view_key === 'locked') {
                    $view_query_args['meta_query'] = [
                        [
                            'key' => '_mzf_saved',
                            'value' => '1',
                            'compare' => '=',
                        ],
                    ];
                } elseif ($view_key === 'unlocked') {
                    $view_query_args['meta_query'] = [
                        'relation' => 'OR',
                        [
                            'key' => '_mzf_saved',
                            'compare' => 'NOT EXISTS',
                        ],
                        [
                            'key' => '_mzf_saved',
                            'value' => '1',
                            'compare' => '!=',
                        ],
                    ];
                }
            }
            if ($view_key === 'crm_entries') {
                $view_counts[$view_key] = count(mzf_submission_query_ids_with_crm_entries($view_query_args));
            } elseif ($view_key === 'phone' || $view_key === 'zip_code') {
                $view_query_args['posts_per_page'] = -1;
                $view_query_args['paged'] = 1;
                $view_query_args['no_found_rows'] = true;
                $view_count_query = new WP_Query($view_query_args);
                $view_ids = array_map('intval', (array) $view_count_query->posts);
                $view_counts[$view_key] = count(array_filter($view_ids, static function ($submission_id) use ($view_key): bool {
                    return mzf_submission_matches_view((int) $submission_id, $view_key);
                }));
            } else {
                $view_count_query = new WP_Query($view_query_args);
                $view_counts[$view_key] = (int) $view_count_query->found_posts;
            }
        }
        echo '<ul class="subsubsub mzf-submissions-tabs">';
        $view_index = 0;
        foreach ($view_labels as $view_key => $view_label) {
            if ($view_index > 0) {
                echo ' | ';
            }
            $view_url = $build_admin_url(['view' => $view_key], ['paged']);
            $class = ($view === $view_key) ? ' class="current" aria-current="page"' : '';
            $count = (int) ($view_counts[$view_key] ?? 0);
            echo '<li><a href="' . esc_url($view_url) . '"' . $class . '>' . esc_html($view_label) . ' <span class="count">(' . esc_html((string) $count) . ')</span></a></li>';
            $view_index++;
        }
        echo '</ul>';
        echo '<br class="clear">';
        if (isset($_GET['mzf_deleted'])) {
            $deleted = max(0, absint($_GET['mzf_deleted']));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(_n('%d submission was moved to the Trash.', '%d submissions were moved to the Trash.', $deleted), $deleted)) . '</p></div>';
        }
        if (isset($_GET['mzf_saved_count'])) {
            $saved_count = max(0, absint($_GET['mzf_saved_count']));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(_n('%d submission was saved.', '%d submissions were saved.', $saved_count), $saved_count)) . '</p></div>';
        }
        if (isset($_GET['mzf_unlocked_count'])) {
            $unlocked_count = max(0, absint($_GET['mzf_unlocked_count']));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(_n('%d submission was unsaved.', '%d submissions were unsaved.', $unlocked_count), $unlocked_count)) . '</p></div>';
        }
        if (isset($_GET['mzf_deleted_selected'])) {
            $deleted_selected = max(0, absint($_GET['mzf_deleted_selected']));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(_n('%d submission was moved to the Trash.', '%d submissions were moved to the Trash.', $deleted_selected), $deleted_selected)) . '</p></div>';
        }
        if (isset($_GET['mzf_export_selected_empty'])) {
            echo '<div class="notice notice-warning is-dismissible"><p>No submissions were chosen to export.</p></div>';
        }
        if (isset($_GET['mzf_emptied_trash'])) {
            $emptied_trash = max(0, absint($_GET['mzf_emptied_trash']));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(_n('%d submission was deleted.', '%d submissions were deleted.', $emptied_trash), $emptied_trash)) . '</p></div>';
        }
        if (isset($_GET['mzf_entry_locked'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Submission saved.</p></div>';
        }
        if (isset($_GET['mzf_entry_unlocked'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Submission unsaved.</p></div>';
        }
        if (isset($_GET['mzf_entry_deleted'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Submission moved to the Trash.</p></div>';
        }
        if (isset($_GET['mzf_entry_restored'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Submission restored from the Trash.</p></div>';
        }
        $query_args = [
            'post_type' => 'mzf_submission',
            'post_status' => ($view === 'deleted') ? 'trash' : $active_statuses,
            'posts_per_page' => 50,
            'paged' => $paged,
            'no_found_rows' => false,
        ];

        if ($sort === 'name') {
            $query_args['orderby'] = 'date';
            $query_args['order'] = 'DESC';
        } else {
            $query_args['orderby'] = 'date';
            $query_args['order'] = strtoupper($order);
        }

        $meta_query = ['relation' => 'AND'];
        if ($view === 'locked') {
            $meta_query[] = [
                'key' => '_mzf_saved',
                'value' => '1',
                'compare' => '=',
            ];
        } elseif ($view === 'unlocked') {
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key' => '_mzf_saved',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '_mzf_saved',
                    'value' => '1',
                    'compare' => '!=',
                ],
            ];
        }
        if (count($meta_query) > 1) {
            $query_args['meta_query'] = $meta_query;
        }

        $has_manual_submission_filters = (
            $filter_page_id > 0 ||
            $filter_form_post_id > 0 ||
            $filter_form_slug !== '' ||
            $filter_admin !== '' ||
            $filter_status !== ''
        );

        if ($view === 'crm_entries' || $view === 'phone' || $view === 'zip_code' || $sort === 'name' || $has_manual_submission_filters) {
            $manual_query_args = $query_args;
            $manual_query_args['posts_per_page'] = -1;
            $manual_query_args['paged'] = 1;
            $manual_query_args['no_found_rows'] = true;
            $manual_query_args['fields'] = 'ids';

            if ($view === 'crm_entries') {
                $matching_ids = mzf_submission_query_ids_with_crm_entries($manual_query_args);
            } else {
                $manual_query = new WP_Query($manual_query_args);
                $matching_ids = array_map('intval', (array) $manual_query->posts);
            }

            if ($view === 'phone' || $view === 'zip_code') {
                $matching_ids = array_values(array_filter($matching_ids, static function ($submission_id) use ($view): bool {
                    return mzf_submission_matches_view((int) $submission_id, $view);
                }));
            }

            if ($has_manual_submission_filters) {
                $matching_ids = array_values(array_filter($matching_ids, static function ($submission_id) use (
                    $filter_page_id,
                    $filter_form_post_id,
                    $filter_form_slug,
                    $filter_admin,
                    $filter_status
                ): bool {
                    return mzf_submission_matches_filters(
                        (int) $submission_id,
                        $filter_page_id,
                        $filter_form_post_id,
                        $filter_form_slug,
                        $filter_admin,
                        $filter_status
                    );
                }));
            }

            if ($sort === 'name') {
                usort($matching_ids, static function ($left_id, $right_id) use ($sort, $order): int {
                    return mzf_compare_submission_ids_by_name((int) $left_id, (int) $right_id, 'first_name', $order);
                });
            }

            $total_matching = count($matching_ids);
            $paged_matching_ids = array_slice($matching_ids, max(0, ($paged - 1) * 50), 50);

            $query = new WP_Query([
                'post_type' => 'mzf_submission',
                'post_status' => $query_args['post_status'],
                'post__in' => !empty($paged_matching_ids) ? $paged_matching_ids : [0],
                'orderby' => 'post__in',
                'posts_per_page' => !empty($paged_matching_ids) ? count($paged_matching_ids) : 1,
                'no_found_rows' => true,
            ]);
            $query->found_posts = $total_matching;
            $query->max_num_pages = ($total_matching > 0) ? (int) ceil($total_matching / 50) : 0;
        } else {
            $query = new WP_Query($query_args);
        }

        $sortable_header = static function (string $label, string $column, string $current_sort, string $current_order, callable $url_builder): array {
            $next_order = 'asc';
            if ($current_sort === $column && $current_order === 'asc') {
                $next_order = 'desc';
            }
            if ($current_sort === $column && $current_order === 'desc') {
                $next_order = 'asc';
            }

            $url = $url_builder([
                'sort' => $column,
                'order' => $next_order,
            ], ['paged']);

            $is_current = ($current_sort === $column);
            $direction = ($current_order === 'asc') ? 'asc' : 'desc';
            $sort_class = $is_current ? 'sorted ' . $direction : 'sortable ' . $direction;
            $markup = '<a href="' . esc_url($url) . '"><span>' . esc_html($label) . '</span><span class="sorting-indicators" aria-hidden="true"><span class="sorting-indicator asc"></span><span class="sorting-indicator desc"></span></span></a>';

            return [
                'class' => $sort_class,
                'markup' => $markup,
            ];
        };

        $date_header = $sortable_header('Date/Time', 'date', $sort, $order, $build_admin_url);
        $name_header = $sortable_header('Name', 'name', $sort, $order, $build_admin_url);

        $delete_bulk_confirmation = ($view === 'deleted')
            ? 'Are you sure you want to permanently delete these submissions?'
            : 'Are you sure you want to move these submissions to the Trash?';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="if (document.getElementById(\'mzf_bulk_action\').value===\'delete_selected\') return confirm(\'' . esc_js($delete_bulk_confirmation) . '\'); return true;">';
        wp_nonce_field('mzf_bulk_submissions_action');
        echo '<input type="hidden" name="action" value="mzf_bulk_submissions_action">';
        echo '<input type="hidden" name="redirect_to" value="' . esc_url($current_view_url) . '">';
        echo '<input type="hidden" name="current_view" value="' . esc_attr($view) . '">';
        echo '<div class="tablenav top mzf-table-toolbar">';
        echo '<div class="alignleft actions mzf-actions-left">';
        echo '<label class="screen-reader-text" for="mzf_bulk_action">Bulk action</label>';
        echo '<select id="mzf_bulk_action" name="mzf_bulk_action">';
        echo '<option value="">Bulk actions</option>';
        if ($view === 'deleted') {
            echo '<option value="unlock_selected">Restore</option>';
            echo '<option value="delete_selected">Delete</option>';
            echo '<option value="export_selected">Export</option>';
        } elseif ($view === 'locked') {
            echo '<option value="unlock_selected">Unsave</option>';
            echo '<option value="export_selected">Export</option>';
        } else {
            echo '<option value="save_selected">Save</option>';
            echo '<option value="delete_selected">Delete</option>';
            echo '<option value="export_selected">Export</option>';
        }
        echo '</select> ';
        echo '<button type="submit" class="button action">Apply</button>';
        echo '</div>';
        echo '<div class="alignright actions mzf-actions-right">';
        if ($is_filtered) {
            $clear_filters_url = $build_admin_url([], ['filter_page_id', 'filter_form_post_id', 'filter_form_slug', 'filter_admin', 'filter_status', 'paged']);
            echo '<span class="mzf-filter-wrap">';
            echo '<span class="mzf-filter-label">Active filters:</span>';
            foreach ($active_filter_chips as $chip) {
                echo '<span class="mzf-filter-chip"><strong>' . esc_html((string) $chip['label']) . ':</strong> ' . esc_html((string) $chip['value']) . ' <a class="mzf-filter-chip-remove" href="' . esc_url((string) $chip['remove_url']) . '" aria-label="Remove ' . esc_attr((string) $chip['label']) . ' filter"><span aria-hidden="true">&times;</span></a></span>';
            }
            echo '<a class="mzf-filter-reset" href="' . esc_url($clear_filters_url) . '">Reset all</a>';
            echo '</span>';
        }
        if ($view === 'deleted') {
            echo '<a class="button button-secondary" href="' . esc_url($empty_trash_url) . '" onclick="return confirm(\'Are you sure you want to permanently delete all submissions in the Trash?\');">Empty Trash</a> ';
        } elseif ($view !== 'locked') {
            $clear_all_label = ($view === 'all') ? 'Delete All Unsaved' : 'Delete All';
            echo '<a class="button button-secondary" href="' . esc_url($clear_all_url) . '" onclick="return confirm(\'Are you sure you want to move all submissions except saved submissions to the Trash?\');">' . esc_html($clear_all_label) . '</a> ';
        }
        echo '<a class="button button-primary" href="' . esc_url($export_url) . '">Export All to CSV</a>';
        echo '</div></div>';
        $table_header_cells = ''
            . '<th scope="col" class="manage-column column-cb check-column"><label class="screen-reader-text" for="mzf-select-all-1">Select all submissions</label><input type="checkbox" id="mzf-select-all-1" class="mzf-select-all" aria-label="Select all submissions"></th>'
            . '<th scope="col" class="manage-column column-mzf_lock" aria-label="Saved status"></th>'
            . '<th scope="col" class="manage-column column-mzf_date ' . esc_attr($date_header['class']) . '">' . $date_header['markup'] . '</th>'
            . '<th scope="col" class="manage-column column-mzf_name ' . esc_attr($name_header['class']) . '">' . $name_header['markup'] . '</th>'
            . '<th scope="col" class="manage-column column-mzf_email">Email</th>'
            . '<th scope="col" class="manage-column column-mzf_phone">Phone</th>'
            . '<th scope="col" class="manage-column column-mzf_zip">Zip Code</th>'
            . '<th scope="col" class="manage-column column-mzf_page">Page</th>'
            . '<th scope="col" class="manage-column column-mzf_form">Form</th>'
            . '<th scope="col" class="manage-column column-mzf_crm">CRM Entry</th>'
            . '<th scope="col" class="manage-column column-mzf_admin">Admin</th>'
            . '<th scope="col" class="manage-column column-mzf_status">Status</th>';
        $table_footer_cells = ''
            . '<th scope="col" class="manage-column column-cb check-column"><label class="screen-reader-text" for="mzf-select-all-2">Select all submissions</label><input type="checkbox" id="mzf-select-all-2" class="mzf-select-all" aria-label="Select all submissions"></th>'
            . '<th scope="col" class="manage-column column-mzf_lock" aria-label="Saved status"></th>'
            . '<th scope="col" class="manage-column column-mzf_date ' . esc_attr($date_header['class']) . '">' . $date_header['markup'] . '</th>'
            . '<th scope="col" class="manage-column column-mzf_name ' . esc_attr($name_header['class']) . '">' . $name_header['markup'] . '</th>'
            . '<th scope="col" class="manage-column column-mzf_email">Email</th>'
            . '<th scope="col" class="manage-column column-mzf_phone">Phone</th>'
            . '<th scope="col" class="manage-column column-mzf_zip">Zip Code</th>'
            . '<th scope="col" class="manage-column column-mzf_page">Page</th>'
            . '<th scope="col" class="manage-column column-mzf_form">Form</th>'
            . '<th scope="col" class="manage-column column-mzf_crm">CRM Entry</th>'
            . '<th scope="col" class="manage-column column-mzf_admin">Admin</th>'
            . '<th scope="col" class="manage-column column-mzf_status">Status</th>';

        echo '<div class="mzf-submissions-table-wrap">';
        echo '<table class="widefat fixed striped table-view-list posts mzf-submissions-table wp-list-table">';
        echo '<thead><tr>' . $table_header_cells . '</tr></thead><tbody>';

        if (!$query->have_posts()) {
            $empty_message = 'No submissions logged yet.';
            if ($view === 'deleted') {
                $empty_message = 'No submissions found in the Trash.';
            } elseif ($view === 'locked') {
                $empty_message = 'No saved submissions found.';
            } elseif ($view === 'unlocked') {
                $empty_message = 'No unsaved submissions found.';
            } elseif ($view === 'phone') {
                $empty_message = 'No submissions with phone numbers found.';
            } elseif ($view === 'zip_code') {
                $empty_message = 'No submissions with zip codes found.';
            } elseif ($view === 'crm_entries') {
                $empty_message = 'No CRM entries found.';
            }
            echo '<tr><td colspan="12"><em>' . esc_html($empty_message) . '</em></td></tr>';
        } else {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $first_name = mzf_submission_text_value($id, ['FirstName', 'ContactFirstName'], '_mzf_first_name');
                $last_name = mzf_submission_text_value($id, ['LastName', 'ContactLastName'], '_mzf_last_name');
                $form_slug = sanitize_key(mzf_submission_text_value($id, ['FormSlug'], '_mzf_form_slug'));
                $email = mzf_submission_text_value($id, ['Email', 'ContactEmail'], '_mzf_email');
                $phone = mzf_submission_text_value($id, ['Phone', 'ContactPhone'], '_mzf_phone');
                $zip_code = mzf_submission_zip_code($id);
                $page_id = mzf_submission_int_value($id, ['PageId'], '_mzf_page_id');
                $page_link = ($page_id > 0) ? get_permalink($page_id) : '';
                $page_title = ($page_id > 0) ? get_the_title($page_id) : '';
                $form_post_id = mzf_submission_int_value($id, ['FormPostId'], '_mzf_form_post_id');
                if ($form_post_id <= 0 && $form_slug !== '') {
                    $form_post = get_page_by_path($form_slug, OBJECT, 'form');
                    if ($form_post instanceof WP_Post) $form_post_id = (int) $form_post->ID;
                }
                if ($form_slug === '' && $form_post_id > 0) {
                    $resolved_form_slug = sanitize_title((string) get_post_field('post_name', $form_post_id));
                    if ($resolved_form_slug !== '') $form_slug = $resolved_form_slug;
                }
                $form_label = '';
                if ($form_slug !== '') {
                    $form_label = mzf_title_case_slug_value($form_slug);
                } elseif ($form_post_id > 0) {
                    $form_label = (string) get_the_title($form_post_id);
                }
                $raw_status = (string) get_post_meta($id, '_mzf_delivery_status', true);
                $status = mzf_submission_status_label($raw_status);
                $status_group = mzf_submission_status_group($raw_status);
                $is_saved = ((string) get_post_meta($id, '_mzf_saved', true) === '1');
                $post_status = (string) get_post_status($id);
                if ($post_status === 'trash' && $is_saved) {
                    delete_post_meta($id, '_mzf_saved');
                    $is_saved = false;
                }

                echo '<tr>';
                echo '<td class="column-cb"><input type="checkbox" class="mzf-select-row" name="submission_ids[]" value="' . esc_attr((string) $id) . '" aria-label="Select submission #' . esc_attr((string) $id) . '"></td>';
                echo '<td class="column-mzf_lock">' . ($is_saved ? '<span class="dashicons dashicons-lock" aria-hidden="true"></span><span class="screen-reader-text">Saved</span>' : '&mdash;') . '</td>';
                if ($status_group === 'success') {
                    $status_icon = 'dashicons-yes-alt';
                } elseif ($status_group === 'spam_detected') {
                    $status_icon = 'dashicons-shield-alt';
                } else {
                    $status_icon = 'dashicons-dismiss';
                }
                $lock_url = wp_nonce_url(
                    add_query_arg([
                        'action' => 'mzf_toggle_submission_lock',
                        'submission_id' => $id,
                        'lock' => $is_saved ? 0 : 1,
                        'redirect_to' => $current_view_url,
                    ], admin_url('admin-post.php')),
                    'mzf_toggle_submission_lock_' . $id
                );
                $export_single_url = wp_nonce_url(
                    add_query_arg([
                        'action' => 'mzf_export_single_submission_csv',
                        'submission_id' => $id,
                    ], admin_url('admin-post.php')),
                    'mzf_export_single_submission_csv_' . $id
                );
                $delete_single_url = wp_nonce_url(
                    add_query_arg([
                        'action' => 'mzf_delete_submission',
                        'submission_id' => $id,
                        'force' => ($post_status === 'trash') ? 1 : 0,
                        'redirect_to' => $current_view_url,
                        'return_view' => $view,
                    ], admin_url('admin-post.php')),
                    'mzf_delete_submission_' . $id
                );
                $restore_single_url = wp_nonce_url(
                    add_query_arg([
                        'action' => 'mzf_restore_submission',
                        'submission_id' => $id,
                        'redirect_to' => $current_view_url,
                    ], admin_url('admin-post.php')),
                    'mzf_restore_submission_' . $id
                );
                $row_actions = [];
                if ($post_status === 'trash') {
                    $row_actions[] = '<span class="restore"><a href="' . esc_url($restore_single_url) . '">Restore</a></span>';
                    $row_actions[] = '<span class="delete"><a href="' . esc_url($delete_single_url) . '" onclick="return confirm(\'Are you sure you want to permanently delete this submission?\');">Delete</a></span>';
                    $row_actions[] = '<span class="export"><a href="' . esc_url($export_single_url) . '">Export</a></span>';
                } elseif ($is_saved) {
                    $row_actions[] = '<span class="edit"><a href="' . esc_url($lock_url) . '">Unsave</a></span>';
                    $row_actions[] = '<span class="export"><a href="' . esc_url($export_single_url) . '">Export</a></span>';
                } else {
                    $row_actions[] = '<span class="edit"><a href="' . esc_url($lock_url) . '">Save</a></span>';
                    $row_actions[] = '<span class="trash"><a href="' . esc_url($delete_single_url) . '" onclick="return confirm(\'Are you sure you want to move this submission to the Trash?\');">Delete</a></span>';
                    $row_actions[] = '<span class="export"><a href="' . esc_url($export_single_url) . '">Export</a></span>';
                }
                echo '<td class="column-mzf_date">' . esc_html(get_the_date('F j, Y')) . '<br>at ' . esc_html(get_the_date('g:i A')) . '</td>';
                $display_name = mzf_submission_display_name($first_name, $last_name);
                echo '<td class="column-mzf_name">';
                echo ($display_name !== '') ? esc_html($display_name) : '&mdash;';
                if (!empty($row_actions)) {
                    echo '<div class="row-actions visible">' . implode(' | ', $row_actions) . '</div>';
                }
                echo '</td>';
                echo '<td class="column-mzf_email">';
                if ($email !== '' && is_email($email)) {
                    echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                } else {
                    echo ($email !== '') ? esc_html($email) : '&mdash;';
                }
                echo '</td>';
                echo '<td class="column-mzf_phone">';
                $tel = preg_replace('/[^\d\+]/', '', (string) $phone);
                if ($phone !== '' && $tel !== '') {
                    echo '<a href="tel:' . esc_attr($tel) . '">' . esc_html($phone) . '</a>';
                } else {
                    echo ($phone !== '') ? esc_html($phone) : '&mdash;';
                }
                echo '</td>';
                echo '<td class="column-mzf_zip">' . (($zip_code !== '') ? esc_html($zip_code) : '&mdash;') . '</td>';
                echo '<td class="column-mzf_page">';
                if ($page_link !== '') {
                    $link_text = ($page_title !== '') ? $page_title : $page_link;
                    $page_filter_url = $build_admin_url(['filter_page_id' => $page_id], ['filter_status', 'paged']);
                    echo '<a href="' . esc_url($page_filter_url) . '">' . esc_html($link_text) . '</a>';
                } else {
                    echo '&mdash;';
                }
                echo '</td>';
                echo '<td class="column-mzf_form">';
                if ($form_label !== '') {
                    if ($form_slug !== '') {
                        $form_filter_url = $build_admin_url(['filter_form_slug' => $form_slug], ['filter_form_post_id', 'filter_status', 'paged']);
                    } else {
                        $form_filter_url = $build_admin_url(['filter_form_post_id' => $form_post_id], ['filter_form_slug', 'filter_status', 'paged']);
                    }
                    echo '<a href="' . esc_url($form_filter_url) . '">' . esc_html($form_label) . '</a>';
                } else {
                    echo '&mdash;';
                }
                echo '</td>';
                $added_to_crm = mzf_submission_added_to_crm_data($id);
                $admin_to = trim((string) get_post_meta($id, '_mzf_admin_to', true));
                echo '<td class="column-mzf_crm">';
                if ($added_to_crm['text'] !== '') {
                    if ($added_to_crm['url'] !== '') {
                        echo '<a href="' . esc_url($added_to_crm['url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($added_to_crm['text']) . '</a>';
                    } else {
                        echo esc_html($added_to_crm['text']);
                    }
                } else {
                    echo '&mdash;';
                }
                echo '</td>';
                echo '<td class="column-mzf_admin">';
                if ($admin_to !== '') {
                    $admin_filter_url = $build_admin_url(['filter_admin' => $admin_to], ['paged']);
                    echo '<a href="' . esc_url($admin_filter_url) . '">' . esc_html($admin_to) . '</a>';
                } else {
                    echo '&mdash;';
                }
                echo '</td>';
                echo '<td class="column-mzf_status">';
                $status_filter_url = $build_admin_url(['filter_status' => $status_group], ['paged']);
                $status_tooltip = mzf_submission_status_tooltip($id, $raw_status);
                $status_tooltip_attr = ($status_tooltip !== '') ? ' title="' . esc_attr($status_tooltip) . '"' : '';
                echo '<a class="mzf-status-link mzf-status-link-' . esc_attr($status_group) . '" href="' . esc_url($status_filter_url) . '"' . $status_tooltip_attr . '>';
                echo '<span class="dashicons ' . esc_attr($status_icon) . '" aria-hidden="true"></span>';
                echo '<span class="mzf-status-text">' . esc_html($status) . '</span>';
                echo '</a>';
                echo '</td>';
                echo '</tr>';
            }
            wp_reset_postdata();
        }
        echo '</tbody><tfoot><tr>' . $table_footer_cells . '</tr></tfoot></table>';
        echo '</div>';
        echo '</form>';
        if ((int) $query->max_num_pages > 1) {
            $pagination_args = $state_args;
            unset($pagination_args['paged']);
            echo '<div class="tablenav"><div class="tablenav-pages" style="margin-top:12px;">';
            echo paginate_links([
                'base' => add_query_arg(array_merge($pagination_args, ['paged' => '%#%']), $base_admin_url),
                'format' => '',
                'current' => $paged,
                'total' => (int) $query->max_num_pages,
            ]);
            echo '</div></div>';
        }

        echo '</div>';
    }
}

add_action('admin_post_mzf_export_submissions_csv', function () {
    if (!mzf_user_can_manage_submissions()) {
        wp_die('Unauthorized', 'Unauthorized', 403);
    }
    check_admin_referer('mzf_export_submissions_csv');

    $q = new WP_Query([
        'post_type' => 'mzf_submission',
        'post_status' => 'private',
        'orderby' => 'date',
        'order' => 'DESC',
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'fields' => 'ids',
    ]);

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . mzf_submission_export_filename() . '"');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        wp_die('Failed to open export stream.');
    }

    fputcsv($out, [
        'Date/Time',
        'First Name',
        'Last Name',
        'Email',
        'Phone',
        'Zip Code',
        'Page URL',
        'Form Name',
        'Form Edit URL',
        'CRM Entry',
        'Admin',
        'Status',
    ]);

    foreach ((array) $q->posts as $id) {
        $id = (int) $id;
        $entry = get_post($id);
        if (!($entry instanceof WP_Post)) continue;

        $first_name = (string) get_post_meta($id, '_mzf_first_name', true);
        $last_name = (string) get_post_meta($id, '_mzf_last_name', true);
        $email = (string) get_post_meta($id, '_mzf_email', true);
        $phone = (string) get_post_meta($id, '_mzf_phone', true);
        $zip_code = mzf_submission_zip_code($id);
        $page_id = (int) get_post_meta($id, '_mzf_page_id', true);
        $page_url = ($page_id > 0) ? (string) get_permalink($page_id) : '';
        $form_post_id = (int) get_post_meta($id, '_mzf_form_post_id', true);
        $form_name = ($form_post_id > 0) ? ((string) get_the_title($form_post_id)) : (string) get_post_meta($id, '_mzf_form_slug', true);
        $form_edit_url = ($form_post_id > 0) ? (string) get_edit_post_link($form_post_id) : '';
        $added_to_crm = mzf_submission_added_to_crm_data($id);
        $admin_to = (string) get_post_meta($id, '_mzf_admin_to', true);
        $raw_status = (string) get_post_meta($id, '_mzf_delivery_status', true);
        $status = mzf_submission_status_label($raw_status);

        $date_time = get_date_from_gmt((string) $entry->post_date_gmt, 'Y-m-d g:i:s A');

        fputcsv($out, [
            $date_time,
            $first_name,
            $last_name,
            $email,
            $phone,
            $zip_code,
            $page_url,
            $form_name,
            $form_edit_url,
            (string) ($added_to_crm['csv'] ?? ''),
            $admin_to,
            $status,
        ]);
    }

    fclose($out);
    exit;
});

add_action('admin_post_mzf_clear_all_submissions', function () {
    if (!mzf_user_can_manage_submissions()) {
        wp_die('Unauthorized', 'Unauthorized', 403);
    }
    check_admin_referer('mzf_clear_all_submissions');

    $q = new WP_Query([
        'post_type' => 'mzf_submission',
        'post_status' => ['private', 'publish', 'draft', 'pending', 'future'],
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'fields' => 'ids',
    ]);

    $deleted = 0;
    foreach ((array) $q->posts as $id) {
        $id = (int) $id;
        if ($id <= 0) continue;
        if ((string) get_post_meta($id, '_mzf_saved', true) === '1') continue;
        delete_post_meta($id, '_mzf_saved');
        if (wp_trash_post($id) !== false) {
            $deleted++;
        }
    }

    $redirect = mzf_submissions_redirect_url();
    $redirect = add_query_arg('mzf_deleted', $deleted, $redirect);

    wp_safe_redirect($redirect);
    exit;
});

add_action('admin_post_mzf_bulk_submissions_action', function () {
    if (!mzf_user_can_manage_submissions()) {
        wp_die('Unauthorized', 'Unauthorized', 403);
    }
    check_admin_referer('mzf_bulk_submissions_action');

    $bulk_action = isset($_POST['mzf_bulk_action']) ? sanitize_key((string) wp_unslash($_POST['mzf_bulk_action'])) : '';
    $selected_ids = isset($_POST['submission_ids']) ? array_map('absint', (array) wp_unslash($_POST['submission_ids'])) : [];
    $selected_ids = array_values(array_unique(array_filter($selected_ids, static fn($id) => $id > 0)));
    $current_view = isset($_POST['current_view']) ? sanitize_key((string) wp_unslash($_POST['current_view'])) : 'all';

    $redirect = mzf_submissions_redirect_url();

    if ($bulk_action === 'save_selected') {
        $saved_count = 0;
        foreach ($selected_ids as $id) {
            if (get_post_type($id) !== 'mzf_submission') continue;
            update_post_meta($id, '_mzf_saved', '1');
            $saved_count++;
        }
        $redirect = add_query_arg('mzf_saved_count', $saved_count, $redirect);
        wp_safe_redirect($redirect);
        exit;
    }

    if ($bulk_action === 'unlock_selected') {
        $unlocked_count = 0;
        foreach ($selected_ids as $id) {
            if (get_post_type($id) !== 'mzf_submission') continue;
            delete_post_meta($id, '_mzf_saved');
            if (get_post_status($id) === 'trash') {
                wp_untrash_post($id);
            }
            wp_update_post([
                'ID' => $id,
                'post_status' => 'private',
            ]);
            $unlocked_count++;
        }
        $redirect = add_query_arg('mzf_unlocked_count', $unlocked_count, $redirect);
        wp_safe_redirect($redirect);
        exit;
    }

    if ($bulk_action === 'delete_selected') {
        $deleted_count = 0;
        foreach ($selected_ids as $id) {
            if (get_post_type($id) !== 'mzf_submission') continue;
            delete_post_meta($id, '_mzf_saved');
            $deleted_post = ($current_view === 'deleted')
                ? wp_delete_post($id, true)
                : wp_trash_post($id);
            if ($deleted_post !== false) {
                $deleted_count++;
            }
        }
        $redirect = add_query_arg('mzf_deleted_selected', $deleted_count, $redirect);
        wp_safe_redirect($redirect);
        exit;
    }

    if ($bulk_action === 'export_selected') {
        if (empty($selected_ids)) {
            $redirect = add_query_arg('mzf_export_selected_empty', 1, $redirect);
            wp_safe_redirect($redirect);
            exit;
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . mzf_submission_export_filename() . '"');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            wp_die('Failed to open export stream.');
        }

        fputcsv($out, [
            'Date/Time',
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Zip Code',
            'Page URL',
            'Form Name',
            'Form Edit URL',
            'CRM Entry',
            'Admin',
            'Status',
        ]);

        foreach ($selected_ids as $id) {
            if (get_post_type($id) !== 'mzf_submission') continue;
            $entry = get_post($id);
            if (!($entry instanceof WP_Post)) continue;

            $first_name = mzf_submission_text_value($id, ['FirstName', 'ContactFirstName'], '_mzf_first_name');
            $last_name = mzf_submission_text_value($id, ['LastName', 'ContactLastName'], '_mzf_last_name');
            $email = mzf_submission_text_value($id, ['Email', 'ContactEmail'], '_mzf_email');
            $phone = mzf_submission_text_value($id, ['Phone', 'ContactPhone'], '_mzf_phone');
            $zip_code = mzf_submission_zip_code($id);
            $page_id = mzf_submission_int_value($id, ['PageId'], '_mzf_page_id');
            $page_url = ($page_id > 0) ? (string) get_permalink($page_id) : '';
            $form_post_id = mzf_submission_int_value($id, ['FormPostId'], '_mzf_form_post_id');
            $form_name = ($form_post_id > 0)
                ? ((string) get_the_title($form_post_id))
                : mzf_submission_text_value($id, ['FormSlug'], '_mzf_form_slug');
            $form_edit_url = ($form_post_id > 0) ? (string) get_edit_post_link($form_post_id) : '';
            $added_to_crm = mzf_submission_added_to_crm_data($id);
            $admin_to = (string) get_post_meta($id, '_mzf_admin_to', true);
            $raw_status = (string) get_post_meta($id, '_mzf_delivery_status', true);
            $status = mzf_submission_status_label($raw_status);
            $date_time = get_date_from_gmt((string) $entry->post_date_gmt, 'Y-m-d g:i:s A');

            fputcsv($out, [
                $date_time,
                $first_name,
                $last_name,
                $email,
                $phone,
                $zip_code,
                $page_url,
                $form_name,
                $form_edit_url,
                (string) ($added_to_crm['csv'] ?? ''),
                $admin_to,
                $status,
            ]);
        }

        fclose($out);
        exit;
    }

    wp_safe_redirect($redirect);
    exit;
});

add_action('admin_post_mzf_empty_trash_submissions', function () {
    if (!mzf_user_can_manage_submissions()) {
        wp_die('Unauthorized', 'Unauthorized', 403);
    }
    check_admin_referer('mzf_empty_trash_submissions');

    $q = new WP_Query([
        'post_type' => 'mzf_submission',
        'post_status' => 'trash',
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'fields' => 'ids',
    ]);

    $deleted = 0;
    foreach ((array) $q->posts as $id) {
        $id = (int) $id;
        if ($id <= 0) continue;
        delete_post_meta($id, '_mzf_saved');
        if (wp_delete_post($id, true) instanceof WP_Post) {
            $deleted++;
        }
    }

    $redirect = mzf_submissions_redirect_url(['view' => 'deleted']);
    $redirect = add_query_arg('mzf_emptied_trash', $deleted, $redirect);

    wp_safe_redirect($redirect);
    exit;
});

add_action('admin_post_mzf_toggle_submission_lock', function () {
    if (!mzf_user_can_manage_submissions()) {
        wp_die('Unauthorized', 'Unauthorized', 403);
    }

    $id = isset($_GET['submission_id']) ? absint($_GET['submission_id']) : 0;
    if ($id <= 0 || get_post_type($id) !== 'mzf_submission') {
        wp_die('Invalid submission.', 'Invalid submission', 400);
    }
    check_admin_referer('mzf_toggle_submission_lock_' . $id);

    $lock = isset($_GET['lock']) ? absint($_GET['lock']) : 0;
    if ($lock === 1) {
        update_post_meta($id, '_mzf_saved', '1');
        $flag = 'mzf_entry_locked';
    } else {
        delete_post_meta($id, '_mzf_saved');
        if (get_post_status($id) === 'trash') {
            wp_untrash_post($id);
        }
        wp_update_post([
            'ID' => $id,
            'post_status' => 'private',
        ]);
        $flag = 'mzf_entry_unlocked';
    }

    $redirect = mzf_submissions_redirect_url();
    $redirect = add_query_arg($flag, 1, $redirect);
    wp_safe_redirect($redirect);
    exit;
});

add_action('admin_post_mzf_export_single_submission_csv', function () {
    if (!mzf_user_can_manage_submissions()) {
        wp_die('Unauthorized', 'Unauthorized', 403);
    }

    $id = isset($_GET['submission_id']) ? absint($_GET['submission_id']) : 0;
    if ($id <= 0 || get_post_type($id) !== 'mzf_submission') {
        wp_die('Invalid submission.', 'Invalid submission', 400);
    }
    check_admin_referer('mzf_export_single_submission_csv_' . $id);

    $entry = get_post($id);
    if (!($entry instanceof WP_Post)) {
        wp_die('Submission not found.', 'Submission not found', 404);
    }

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . mzf_submission_export_filename() . '"');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        wp_die('Failed to open export stream.');
    }

    fputcsv($out, [
        'Date/Time',
        'First Name',
        'Last Name',
        'Email',
        'Phone',
        'Zip Code',
        'Page URL',
        'Form Name',
        'Form Edit URL',
        'CRM Entry',
        'Admin',
        'Status',
    ]);

    $first_name = mzf_submission_text_value($id, ['FirstName', 'ContactFirstName'], '_mzf_first_name');
    $last_name = mzf_submission_text_value($id, ['LastName', 'ContactLastName'], '_mzf_last_name');
    $email = mzf_submission_text_value($id, ['Email', 'ContactEmail'], '_mzf_email');
    $phone = mzf_submission_text_value($id, ['Phone', 'ContactPhone'], '_mzf_phone');
    $zip_code = mzf_submission_zip_code($id);
    $page_id = mzf_submission_int_value($id, ['PageId'], '_mzf_page_id');
    $page_url = ($page_id > 0) ? (string) get_permalink($page_id) : '';
    $form_post_id = mzf_submission_int_value($id, ['FormPostId'], '_mzf_form_post_id');
    $form_name = ($form_post_id > 0)
        ? ((string) get_the_title($form_post_id))
        : mzf_submission_text_value($id, ['FormSlug'], '_mzf_form_slug');
    $form_edit_url = ($form_post_id > 0) ? (string) get_edit_post_link($form_post_id) : '';
    $added_to_crm = mzf_submission_added_to_crm_data($id);
    $admin_to = (string) get_post_meta($id, '_mzf_admin_to', true);
    $raw_status = (string) get_post_meta($id, '_mzf_delivery_status', true);
    $status = mzf_submission_status_label($raw_status);
    $date_time = get_date_from_gmt((string) $entry->post_date_gmt, 'Y-m-d g:i:s A');

    fputcsv($out, [
        $date_time,
        $first_name,
        $last_name,
        $email,
        $phone,
        $zip_code,
        $page_url,
        $form_name,
        $form_edit_url,
        (string) ($added_to_crm['csv'] ?? ''),
        $admin_to,
        $status,
    ]);

    fclose($out);
    exit;
});

add_action('admin_post_mzf_delete_submission', function () {
    if (!mzf_user_can_manage_submissions()) {
        wp_die('Unauthorized', 'Unauthorized', 403);
    }

    $id = isset($_GET['submission_id']) ? absint($_GET['submission_id']) : 0;
    if ($id <= 0 || get_post_type($id) !== 'mzf_submission') {
        wp_die('Invalid submission.', 'Invalid submission', 400);
    }
    check_admin_referer('mzf_delete_submission_' . $id);

    $force = isset($_GET['force']) ? absint($_GET['force']) : 0;
    delete_post_meta($id, '_mzf_saved');
    if ($force === 1) {
        wp_delete_post($id, true);
    } else {
        wp_trash_post($id);
    }

    $return_view = isset($_GET['return_view']) ? sanitize_key((string) wp_unslash($_GET['return_view'])) : '';
    $allowed_views = ['all', 'locked', 'unlocked', 'crm_entries', 'deleted'];
    if (!in_array($return_view, $allowed_views, true)) {
        $return_view = 'all';
    }
    $redirect = mzf_submissions_redirect_url(['view' => $return_view]);
    $redirect = add_query_arg('mzf_entry_deleted', 1, $redirect);
    wp_safe_redirect($redirect);
    exit;
});

add_action('admin_post_mzf_restore_submission', function () {
    if (!mzf_user_can_manage_submissions()) {
        wp_die('Unauthorized', 'Unauthorized', 403);
    }

    $id = isset($_GET['submission_id']) ? absint($_GET['submission_id']) : 0;
    if ($id <= 0 || get_post_type($id) !== 'mzf_submission') {
        wp_die('Invalid submission.', 'Invalid submission', 400);
    }
    check_admin_referer('mzf_restore_submission_' . $id);

    wp_untrash_post($id);
    wp_update_post([
        'ID' => $id,
        'post_status' => 'private',
    ]);

    $redirect = mzf_submissions_redirect_url();
    $redirect = add_query_arg('mzf_entry_restored', 1, $redirect);
    wp_safe_redirect($redirect);
    exit;
});

add_action('trashed_post', function ($post_id) {
    if (get_post_type($post_id) !== 'mzf_submission') {
        return;
    }
    delete_post_meta((int) $post_id, '_mzf_saved');
});
