<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=form',
        'Form Submission Log',
        'Submissions',
        'manage_options',
        'mzf-submissions',
        'mzf_render_submission_log_admin_page'
    );
});

add_action('admin_enqueue_scripts', function (): void {
    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    if ($page !== 'mzf-submissions') return;

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

        echo "<style id='mzf-admin-shared-inline'>\n" . $css . "\n</style>";
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

        if ($group === 'failure_validation') {
            return [
                'key' => '_mzf_delivery_status',
                'value' => '^validation_',
                'compare' => 'REGEXP',
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
            'page' => 'mzf-submissions',
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
            if (strpos($candidate, 'page=mzf-submissions') !== false) {
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

if (!function_exists('mzf_submission_added_to_crm_data')) {
    function mzf_submission_added_to_crm_data(int $submission_id): array
    {
        $marketing_sync = get_post_meta($submission_id, '_mzf_marketing_sync', true);
        if (!is_array($marketing_sync)) {
            return ['text' => '', 'url' => '', 'csv' => ''];
        }

        $sync_ok = mzf_submission_meta_truthy((string) ($marketing_sync['ok'] ?? ''));
        if (!$sync_ok) {
            return ['text' => '', 'url' => '', 'csv' => ''];
        }

        $provider = strtolower(trim((string) ($marketing_sync['provider'] ?? '')));
        if (!in_array($provider, ['mailchimp', 'constant_contact'], true)) {
            return ['text' => '', 'url' => '', 'csv' => ''];
        }

        $sync_label = trim((string) ($marketing_sync['label'] ?? ''));
        $sync_contact_url = trim((string) ($marketing_sync['contact_url'] ?? ''));
        if ($provider === 'constant_contact') {
            $sync_contact_id = trim((string) ($marketing_sync['contact_id'] ?? ''));
            if ($sync_contact_id !== '') {
                $sync_contact_url = 'https://app.constantcontact.com/contacts/' . rawurlencode($sync_contact_id) . '/profile';
            }
        }
        if ($sync_contact_url === '') {
            return ['text' => '', 'url' => '', 'csv' => ''];
        }

        $connected_label = $sync_label !== ''
            ? $sync_label
            : (($provider === 'mailchimp') ? 'Mailchimp' : 'Constant Contact');
        return [
            'text' => $connected_label,
            'url' => $sync_contact_url,
            'csv' => $connected_label,
        ];
    }
}

if (!function_exists('mzf_render_submission_log_admin_page')) {
    function mzf_render_submission_log_admin_page(): void
    {
        if (!current_user_can('manage_options')) return;

        $base_admin_url = add_query_arg([
            'post_type' => 'form',
            'page' => 'mzf-submissions',
        ], admin_url('edit.php'));

        $sort = isset($_GET['sort']) ? sanitize_key((string) wp_unslash($_GET['sort'])) : 'date';
        $order = isset($_GET['order']) ? strtolower(sanitize_key((string) wp_unslash($_GET['order']))) : 'desc';
        $allowed_sorts = ['date', 'first_name', 'last_name'];
        if (!in_array($sort, $allowed_sorts, true)) $sort = 'date';
        if (!in_array($order, ['asc', 'desc'], true)) $order = 'desc';
        $view = isset($_GET['view']) ? sanitize_key((string) wp_unslash($_GET['view'])) : 'all';
        $allowed_views = ['all', 'locked', 'unlocked', 'deleted'];
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
        mzf_render_submission_log_inline_styles();
        echo '<h1>Form Submission Log</h1>';
        $view_labels = [
            'all' => 'All',
            'locked' => 'Saved',
            'unlocked' => 'Unsaved',
            'deleted' => 'Cleared',
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
            $view_count_query = new WP_Query($view_query_args);
            $view_counts[$view_key] = (int) $view_count_query->found_posts;
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
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(_n('%d unsaved submission was cleared.', '%d unsaved submissions were cleared.', $deleted), $deleted)) . '</p></div>';
        }
        if (isset($_GET['mzf_saved_count'])) {
            $saved_count = max(0, absint($_GET['mzf_saved_count']));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(_n('%d submission was marked as saved.', '%d submissions were marked as saved.', $saved_count), $saved_count)) . '</p></div>';
        }
        if (isset($_GET['mzf_unlocked_count'])) {
            $unlocked_count = max(0, absint($_GET['mzf_unlocked_count']));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(_n('%d submission was removed from saved.', '%d submissions were removed from saved.', $unlocked_count), $unlocked_count)) . '</p></div>';
        }
        if (isset($_GET['mzf_deleted_selected'])) {
            $deleted_selected = max(0, absint($_GET['mzf_deleted_selected']));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(_n('%d submission was cleared.', '%d submissions were cleared.', $deleted_selected), $deleted_selected)) . '</p></div>';
        }
        if (isset($_GET['mzf_export_selected_empty'])) {
            echo '<div class="notice notice-warning is-dismissible"><p>No submissions were chosen to export.</p></div>';
        }
        if (isset($_GET['mzf_emptied_trash'])) {
            $emptied_trash = max(0, absint($_GET['mzf_emptied_trash']));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(_n('%d cleared submission was deleted.', '%d cleared submissions were deleted.', $emptied_trash), $emptied_trash)) . '</p></div>';
        }
        if (isset($_GET['mzf_entry_locked'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Submission marked as saved.</p></div>';
        }
        if (isset($_GET['mzf_entry_unlocked'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Submission removed from saved.</p></div>';
        }
        if (isset($_GET['mzf_entry_deleted'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Submission cleared.</p></div>';
        }
        if (isset($_GET['mzf_entry_restored'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Submission restored to the log.</p></div>';
        }
        $query_args = [
            'post_type' => 'mzf_submission',
            'post_status' => ($view === 'deleted') ? 'trash' : $active_statuses,
            'posts_per_page' => 50,
            'paged' => $paged,
            'no_found_rows' => false,
        ];

        if ($sort === 'first_name') {
            $query_args['meta_key'] = '_mzf_first_name';
            $query_args['orderby'] = ['meta_value' => strtoupper($order), 'date' => 'DESC'];
            $query_args['order'] = strtoupper($order);
        } elseif ($sort === 'last_name') {
            $query_args['meta_key'] = '_mzf_last_name';
            $query_args['orderby'] = ['meta_value' => strtoupper($order), 'date' => 'DESC'];
            $query_args['order'] = strtoupper($order);
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
        if ($filter_page_id > 0) {
            $meta_query[] = [
                'key' => '_mzf_page_id',
                'value' => $filter_page_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ];
        }
        if ($filter_form_post_id > 0) {
            $meta_query[] = [
                'key' => '_mzf_form_post_id',
                'value' => $filter_form_post_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ];
        } elseif ($filter_form_slug !== '') {
            $meta_query[] = [
                'key' => '_mzf_form_slug',
                'value' => $filter_form_slug,
                'compare' => '=',
            ];
        }
        if ($filter_admin !== '') {
            $meta_query[] = [
                'key' => '_mzf_admin_to',
                'value' => $filter_admin,
                'compare' => '=',
            ];
        }
        if ($filter_status !== '') {
            $status_query = mzf_submission_status_query_clause($filter_status);
            if (!empty($status_query)) {
                $meta_query[] = $status_query;
            }
        }

        if (count($meta_query) > 1) {
            $query_args['meta_query'] = $meta_query;
        }

        $query = new WP_Query($query_args);

        $sortable_header = static function (string $label, string $column, string $current_sort, string $current_order, callable $url_builder): string {
            $next_order = 'asc';
            if ($current_sort === $column && $current_order === 'asc') {
                $next_order = 'desc';
            }
            if ($current_sort === $column && $current_order === 'desc') {
                $next_order = 'asc';
            }

            $indicator = '';
            if ($current_sort === $column) {
                $indicator = ($current_order === 'asc') ? ' &#8593;' : ' &#8595;';
            }

            $url = $url_builder([
                'sort' => $column,
                'order' => $next_order,
            ], ['paged']);

            return '<a href="' . esc_url($url) . '">' . esc_html($label) . $indicator . '</a>';
        };

        $delete_bulk_confirmation = ($view === 'deleted')
            ? 'Are you sure you want to delete these cleared submissions?'
            : 'Are you sure you want to clear these submissions from the log?';

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
            echo '<option value="export_selected">Export</option>';
            echo '<option value="delete_selected">Delete</option>';
        } elseif ($view === 'locked') {
            echo '<option value="unlock_selected">Unsave</option>';
            echo '<option value="export_selected">Export</option>';
        } else {
            echo '<option value="save_selected">Save</option>';
            echo '<option value="export_selected">Export</option>';
            echo '<option value="delete_selected">Clear</option>';
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
            echo '<a class="button button-secondary" href="' . esc_url($empty_trash_url) . '" onclick="return confirm(\'Are you sure you want to delete all cleared submissions?\');">Delete All</a> ';
        } elseif ($view !== 'locked') {
            $clear_all_label = ($view === 'all') ? 'Clear All Unsaved' : 'Clear All';
            echo '<a class="button button-secondary" href="' . esc_url($clear_all_url) . '" onclick="return confirm(\'Are you sure you want to clear all submissions except saved submissions?\');">' . esc_html($clear_all_label) . '</a> ';
        }
        echo '<a class="button button-primary" href="' . esc_url($export_url) . '">Export All to CSV</a>';
        echo '</div></div>';
        echo '<div class="mzf-submissions-table-wrap">';
        echo '<table class="widefat striped mzf-submissions-table wp-list-table">';
        echo '<thead><tr>';
        echo '<th class="column-cb" style="width:32px;min-width:32px;max-width:32px;"><input type="checkbox" id="mzf-select-all" aria-label="Select all submissions"></th>';
        echo '<th class="column-mzf_lock" style="width:32px;min-width:32px;max-width:32px;" aria-label="Saved status"></th>';
        echo '<th class="column-mzf_date" style="width:150px;min-width:150px;max-width:150px;">' . $sortable_header('Date/Time', 'date', $sort, $order, $build_admin_url) . '</th>';
        echo '<th class="column-mzf_first_name" style="width:225px;min-width:225px;max-width:225px;">' . $sortable_header('First Name', 'first_name', $sort, $order, $build_admin_url) . '</th>';
        echo '<th class="column-mzf_last_name" style="width:150px;min-width:150px;max-width:150px;">' . $sortable_header('Last Name', 'last_name', $sort, $order, $build_admin_url) . '</th>';
        echo '<th class="column-mzf_email" style="width:225px;min-width:225px;max-width:225px;">Email</th>';
        echo '<th class="column-mzf_phone" style="width:125px;min-width:125px;max-width:125px;">Phone</th>';
        echo '<th class="column-mzf_zip" style="width:125px;min-width:125px;max-width:125px;">Zip Code</th>';
        echo '<th class="column-mzf_page" style="width:125px;min-width:125px;max-width:125px;">Page</th>';
        echo '<th class="column-mzf_form" style="width:125px;min-width:125px;max-width:125px;">Form</th>';
        echo '<th class="column-mzf_crm" style="width:125px;min-width:125px;max-width:125px;">CRM Entry</th>';
        echo '<th class="column-mzf_status" style="width:100px;min-width:100px;max-width:100px;">Status</th>';
        echo '</tr></thead><tbody>';

        if (!$query->have_posts()) {
            $empty_message = 'No submissions logged yet.';
            if ($view === 'deleted') {
                $empty_message = 'No cleared submissions found.';
            } elseif ($view === 'locked') {
                $empty_message = 'No saved submissions found.';
            } elseif ($view === 'unlocked') {
                $empty_message = 'No unsaved submissions found.';
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
                echo '<td class="column-mzf_lock">' . ($is_saved ? '<span class="dashicons dashicons-saved" aria-hidden="true"></span><span class="screen-reader-text">Saved</span>' : '&mdash;') . '</td>';
                echo '<td class="column-mzf_date">' . esc_html(get_the_date('F j, Y')) . '<br>at ' . esc_html(get_the_date('g:i A')) . '</td>';
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
                    $row_actions[] = '<span class="export"><a href="' . esc_url($export_single_url) . '">Export to CSV</a></span>';
                    $row_actions[] = '<span class="delete"><a href="' . esc_url($delete_single_url) . '" onclick="return confirm(\'Are you sure you want to delete this cleared submission?\');">Delete</a></span>';
                } elseif ($is_saved) {
                    $row_actions[] = '<span class="edit"><a href="' . esc_url($lock_url) . '">Unsave</a></span>';
                    $row_actions[] = '<span class="export"><a href="' . esc_url($export_single_url) . '">Export to CSV</a></span>';
                } else {
                    $row_actions[] = '<span class="edit"><a href="' . esc_url($lock_url) . '">Save</a></span>';
                    $row_actions[] = '<span class="export"><a href="' . esc_url($export_single_url) . '">Export to CSV</a></span>';
                    $row_actions[] = '<span class="trash"><a href="' . esc_url($delete_single_url) . '" onclick="return confirm(\'Are you sure you want to clear this submission from the log?\');">Clear</a></span>';
                }
                echo '<td class="column-mzf_first_name">';
                echo ($first_name !== '') ? esc_html($first_name) : '&mdash;';
                if (!empty($row_actions)) {
                    echo '<div class="row-actions visible">' . implode(' | ', $row_actions) . '</div>';
                }
                echo '</td>';
                echo '<td class="column-mzf_last_name">' . esc_html($last_name) . '</td>';
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
                    $page_filter_url = $build_admin_url(['filter_page_id' => $page_id], ['paged']);
                    echo '<a href="' . esc_url($page_filter_url) . '">' . esc_html($link_text) . '</a>';
                } else {
                    echo '&mdash;';
                }
                echo '</td>';
                echo '<td class="column-mzf_form">';
                if ($form_label !== '') {
                    if ($form_slug !== '') {
                        $form_filter_url = $build_admin_url(['filter_form_slug' => $form_slug], ['filter_form_post_id', 'paged']);
                    } else {
                        $form_filter_url = $build_admin_url(['filter_form_post_id' => $form_post_id], ['filter_form_slug', 'paged']);
                    }
                    echo '<a href="' . esc_url($form_filter_url) . '">' . esc_html($form_label) . '</a>';
                } else {
                    echo '&mdash;';
                }
                echo '</td>';
                $added_to_crm = mzf_submission_added_to_crm_data($id);
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
                echo '<td class="column-mzf_status">';
                $status_filter_url = $build_admin_url(['filter_status' => $status_group], ['paged']);
                echo '<a href="' . esc_url($status_filter_url) . '">' . esc_html($status) . '</a>';
                echo '</td>';
                echo '</tr>';
            }
            wp_reset_postdata();
        }
        echo '</tbody></table>';
        echo '</div>';
        echo '</form>';
        echo '<script>(function(){var all=document.getElementById("mzf-select-all");if(!all)return;all.addEventListener("change",function(){var rows=document.querySelectorAll(".mzf-select-row");for(var i=0;i<rows.length;i++){rows[i].checked=all.checked;}});})();</script>';

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
    if (!current_user_can('manage_options')) {
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
    if (!current_user_can('manage_options')) {
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
    if (!current_user_can('manage_options')) {
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
    if (!current_user_can('manage_options')) {
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
    if (!current_user_can('manage_options')) {
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
    if (!current_user_can('manage_options')) {
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
    if (!current_user_can('manage_options')) {
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
    $allowed_views = ['all', 'locked', 'unlocked', 'deleted'];
    if (!in_array($return_view, $allowed_views, true)) {
        $return_view = 'all';
    }
    $redirect = mzf_submissions_redirect_url(['view' => $return_view]);
    $redirect = add_query_arg('mzf_entry_deleted', 1, $redirect);
    wp_safe_redirect($redirect);
    exit;
});

add_action('admin_post_mzf_restore_submission', function () {
    if (!current_user_can('manage_options')) {
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
