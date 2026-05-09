<?php

/**
 * Internal module for MZ Tools post type and media migration flows.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MZ_PTM_RESULT_TRANSIENT')) {
    define('MZ_PTM_RESULT_TRANSIENT', 'mz_ptm_last_result');
}

if (!defined('MZ_PTM_RESULT_USER_META')) {
    define('MZ_PTM_RESULT_USER_META', 'mz_ptm_last_result');
}

if (!defined('MZ_PTM_ACTION')) {
    define('MZ_PTM_ACTION', 'mz_post_type_migration');
}

if (!defined('MZ_PTM_MEDIA_ACTION')) {
    define('MZ_PTM_MEDIA_ACTION', 'mz_media_repair');
}

if (!defined('MZ_PTM_PAGE_SLUG')) {
    define('MZ_PTM_PAGE_SLUG', 'mz-post-type-migration-tools');
}

if (!defined('MZ_PTM_MEDIA_RESULT_TRANSIENT')) {
    define('MZ_PTM_MEDIA_RESULT_TRANSIENT', 'mz_ptm_media_last_result');
}

if (!defined('MZ_PTM_MEDIA_RESULT_USER_META')) {
    define('MZ_PTM_MEDIA_RESULT_USER_META', 'mz_ptm_media_last_result');
}

if (!defined('MZ_PTM_MEDIA_ATTACH_ACTION')) {
    define('MZ_PTM_MEDIA_ATTACH_ACTION', 'mz_media_attach_featured');
}

if (!defined('MZ_PTM_LOCALITY_ACTION')) {
    define('MZ_PTM_LOCALITY_ACTION', 'mz_locality_migration');
}

if (!defined('MZ_PTM_LOCALITY_RESULT_TRANSIENT')) {
    define('MZ_PTM_LOCALITY_RESULT_TRANSIENT', 'mz_ptm_locality_last_result');
}

if (!defined('MZ_PTM_LOCALITY_RESULT_USER_META')) {
    define('MZ_PTM_LOCALITY_RESULT_USER_META', 'mz_ptm_locality_last_result');
}

if (!function_exists('mz_ptm_normalize_post_type_slug')) {
    function mz_ptm_normalize_post_type_slug(string $value): string
    {
        return sanitize_key(trim($value));
    }
}

if (!function_exists('mz_ptm_parse_post_types')) {
    function mz_ptm_parse_post_types($raw_sources = null): array
    {
        $sources = $raw_sources;

        if (!is_array($sources)) {
            $sources = $sources !== null ? explode(',', (string) $sources) : ['testimonial', 'testimonials'];
        }

        return array_values(array_filter(array_unique(array_map(
            'mz_ptm_normalize_post_type_slug',
            array_map('strval', $sources)
        ))));
    }
}

if (!function_exists('mz_ptm_parse_term_slugs')) {
    function mz_ptm_parse_term_slugs($raw_terms = null): array
    {
        $terms = $raw_terms;

        if (!is_array($terms)) {
            $terms = $terms !== null ? explode(',', (string) $terms) : [];
        }

        return array_values(array_filter(array_unique(array_map(
            'mz_ptm_normalize_post_type_slug',
            array_map('strval', $terms)
        ))));
    }
}

if (!function_exists('mz_ptm_parse_csv_slugs')) {
    function mz_ptm_parse_csv_slugs($raw_values = null): array
    {
        $values = $raw_values;

        if (!is_array($values)) {
            $values = $values !== null ? explode(',', (string) $values) : [];
        }

        return array_values(array_filter(array_unique(array_map(
            'sanitize_key',
            array_map('strval', $values)
        ))));
    }
}

if (!function_exists('mz_ptm_get_available_source_post_types')) {
    function mz_ptm_get_available_source_post_types($raw_sources = null): array
    {
        return array_values(array_filter(mz_ptm_parse_post_types($raw_sources), 'post_type_exists'));
    }
}

if (!function_exists('mz_ptm_get_destination_post_type')) {
    function mz_ptm_get_destination_post_type($raw_destination = null): string
    {
        $destination = mz_ptm_normalize_post_type_slug((string) ($raw_destination ?: 'review'));

        return $destination !== '' ? $destination : 'review';
    }
}

if (!function_exists('mz_ptm_get_redirect_url')) {
    function mz_ptm_get_redirect_url(): string
    {
        return admin_url('tools.php?page=' . MZ_PTM_PAGE_SLUG);
    }
}

if (!function_exists('mz_ptm_get_locality_taxonomy')) {
    function mz_ptm_get_locality_taxonomy(): string
    {
        if (taxonomy_exists('locality')) {
            return 'locality';
        }

        if (taxonomy_exists('location')) {
            return 'location';
        }

        return '';
    }
}

if (!function_exists('mz_ptm_locality_is_stateful_name')) {
    function mz_ptm_locality_is_stateful_name(string $name): bool
    {
        return preg_match('/,\s*(virginia|maryland|west virginia|district of columbia|dc)$/i', trim($name)) === 1;
    }
}

if (!function_exists('mz_ptm_locality_normalize_name')) {
    function mz_ptm_locality_normalize_name(string $name): string
    {
        $normalized = strtolower(trim(wp_strip_all_tags($name)));
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = preg_replace('/(?:,\s*(virginia|maryland|west virginia|district of columbia|dc)|\s+(virginia|maryland|west virginia|district of columbia|va|md|wv|dc))$/i', '', (string) $normalized);
        $normalized = preg_replace('/\s+/', ' ', (string) $normalized);

        return trim((string) $normalized, " \t\n\r\0\x0B,");
    }
}

if (!function_exists('mz_ptm_get_locality_merge_candidates')) {
    function mz_ptm_get_locality_merge_candidates(string $taxonomy = ''): array
    {
        $taxonomy = $taxonomy !== '' ? $taxonomy : mz_ptm_get_locality_taxonomy();
        if ($taxonomy === '') {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || !is_array($terms)) {
            return [];
        }

        $groups = [];

        foreach ($terms as $term) {
            if (!($term instanceof WP_Term)) {
                continue;
            }

            $key = mz_ptm_locality_normalize_name((string) $term->name);
            if ($key === '') {
                continue;
            }

            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }

            $groups[$key][] = $term;
        }

        $candidates = [];

        foreach ($groups as $key => $group_terms) {
            if (count($group_terms) < 2) {
                continue;
            }

            $stateful_terms = array_values(array_filter($group_terms, static function ($term): bool {
                return $term instanceof WP_Term && mz_ptm_locality_is_stateful_name((string) $term->name);
            }));

            if ($stateful_terms === []) {
                continue;
            }

            usort($stateful_terms, static function (WP_Term $a, WP_Term $b): int {
                $count_compare = (int) $b->count <=> (int) $a->count;
                if ($count_compare !== 0) {
                    return $count_compare;
                }

                return strcasecmp((string) $a->name, (string) $b->name);
            });

            $destination_term = $stateful_terms[0];
            $source_terms = [];

            foreach ($group_terms as $term) {
                if (!($term instanceof WP_Term)) {
                    continue;
                }

                if ((int) $term->term_id === (int) $destination_term->term_id) {
                    continue;
                }

                $source_terms[] = $term;
            }

            if ($source_terms === []) {
                continue;
            }

            usort($source_terms, static function (WP_Term $a, WP_Term $b): int {
                $count_compare = (int) $b->count <=> (int) $a->count;
                if ($count_compare !== 0) {
                    return $count_compare;
                }

                return strcasecmp((string) $a->name, (string) $b->name);
            });

            $candidates[] = [
                'key' => $key,
                'destination' => $destination_term,
                'sources' => $source_terms,
            ];
        }

        usort($candidates, static function (array $a, array $b): int {
            return strcasecmp((string) $a['key'], (string) $b['key']);
        });

        return $candidates;
    }
}

if (!function_exists('mz_ptm_user_can_access_tool')) {
    function mz_ptm_user_can_access_tool(): bool
    {
        $default_enabled = current_user_can('manage_options');
        $admin_access_enabled = (bool) apply_filters('mz_ptm_admin_tool_enabled', $default_enabled);
        if (!$admin_access_enabled) {
            return false;
        }

        $user = wp_get_current_user();
        $user_login = strtolower((string) ($user->user_login ?? ''));
        $allowed_usernames = ['ameza', 'andrew', 'andrewmeza'];

        return $user instanceof WP_User
            && $user->exists()
            && (
                current_user_can('manage_options')
                || in_array($user_login, $allowed_usernames, true)
            );
    }
}

if (!function_exists('mz_ptm_get_action_url')) {
    function mz_ptm_get_action_url(
        string $mode,
        string $source = 'testimonial,testimonials',
        string $destination = 'review',
        string $taxonomy = '',
        string $terms = ''
    ): string
    {
        $args = [
            'action' => MZ_PTM_ACTION,
            'mode' => $mode,
            'source' => $source,
            'destination' => $destination,
        ];

        if ($taxonomy !== '') {
            $args['taxonomy'] = $taxonomy;
        }

        if ($terms !== '') {
            $args['terms'] = $terms;
        }

        return add_query_arg($args, admin_url('admin-post.php'));
    }
}

if (!function_exists('mz_ptm_collect_post_ids')) {
    function mz_ptm_collect_post_ids(array $sources, array $args = []): array
    {
        if ($sources === []) {
            return [];
        }

        $query_args = [
            'post_type' => $sources,
            'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'suppress_filters' => true,
        ];

        $taxonomy = isset($args['taxonomy']) ? mz_ptm_normalize_post_type_slug((string) $args['taxonomy']) : '';
        $terms = mz_ptm_parse_term_slugs($args['terms'] ?? null);

        if ($taxonomy !== '' && $terms !== []) {
            $query_args['tax_query'] = [[
                'taxonomy' => $taxonomy,
                'field' => 'slug',
                'terms' => $terms,
            ]];
        }

        return get_posts($query_args);
    }
}

if (!function_exists('mz_ptm_get_review_citer_text')) {
    function mz_ptm_get_review_citer_text(int $post_id): string
    {
        $citer_name = trim(wp_strip_all_tags((string) get_post_meta($post_id, 'citer_name', true)));
        $citer_title = trim(wp_strip_all_tags((string) get_post_meta($post_id, 'citer_title', true)));

        if ($citer_name !== '' && $citer_title !== '') {
            return $citer_name . ' - ' . $citer_title;
        }

        if ($citer_name !== '') {
            return $citer_name;
        }

        return $citer_title;
    }
}

if (!function_exists('mz_ptm_prepare_destination_meta')) {
    function mz_ptm_prepare_destination_meta(WP_Post $post, string $destination_post_type): array
    {
        $meta_updates = [];

        if ($destination_post_type !== 'review') {
            return $meta_updates;
        }

        $existing_citer = get_post_meta((int) $post->ID, 'citer', true);
        $existing_citer = is_string($existing_citer) ? trim(wp_strip_all_tags($existing_citer)) : '';

        if ($existing_citer === '') {
            $migrated_citer = mz_ptm_get_review_citer_text((int) $post->ID);

            if ($migrated_citer !== '') {
                $meta_updates['citer'] = $migrated_citer;
            }
        }

        return $meta_updates;
    }
}

if (!function_exists('mz_ptm_run_migration')) {
    function mz_ptm_run_migration(array $args = []): array
    {
        $source_post_types = mz_ptm_get_available_source_post_types($args['source'] ?? null);
        $destination_post_type = mz_ptm_get_destination_post_type($args['destination'] ?? null);
        $taxonomy = isset($args['taxonomy']) ? mz_ptm_normalize_post_type_slug((string) $args['taxonomy']) : '';
        $terms = mz_ptm_parse_term_slugs($args['terms'] ?? null);
        $dry_run = !empty($args['dry_run']);

        $result = [
            'source_post_types' => $source_post_types,
            'requested_source_post_types' => mz_ptm_parse_post_types($args['source'] ?? null),
            'destination_post_type' => $destination_post_type,
            'taxonomy' => $taxonomy,
            'terms' => $terms,
            'dry_run' => $dry_run,
            'total' => 0,
            'migrated' => 0,
            'terms_removed' => 0,
            'failures' => [],
            'messages' => [],
        ];

        if ($source_post_types === []) {
            $result['failures'][] = 'No matching source post types exist on this site.';
            return $result;
        }

        if (!post_type_exists($destination_post_type)) {
            $result['failures'][] = sprintf('Destination post type "%s" does not exist.', $destination_post_type);
            return $result;
        }

        if ($taxonomy !== '' && !taxonomy_exists($taxonomy)) {
            $result['failures'][] = sprintf('Taxonomy "%s" does not exist.', $taxonomy);
            return $result;
        }

        $post_ids = mz_ptm_collect_post_ids($source_post_types, [
            'taxonomy' => $taxonomy,
            'terms' => $terms,
        ]);
        $result['total'] = count($post_ids);

        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);

            if (!($post instanceof WP_Post)) {
                $result['failures'][] = sprintf('Post %d could not be loaded.', (int) $post_id);
                continue;
            }

            $label = sprintf(
                '#%d [%s] %s',
                (int) $post->ID,
                (string) $post->post_status,
                $post->post_title !== '' ? $post->post_title : '(no title)'
            );
            $meta_updates = mz_ptm_prepare_destination_meta($post, $destination_post_type);
            $needs_post_type_update = $post->post_type !== $destination_post_type;
            $needs_term_removal = $taxonomy !== '' && $terms !== [];
            $has_changes = $needs_post_type_update || $meta_updates !== [] || $needs_term_removal;

            if ($dry_run) {
                if (!$has_changes) {
                    $result['messages'][] = 'No changes needed for ' . $label;
                    continue;
                }

                $message = $needs_post_type_update ? 'Would migrate ' . $label : 'Would update ' . $label;

                if (isset($meta_updates['citer'])) {
                    $message .= sprintf(' and set citer to "%s"', $meta_updates['citer']);
                }

                if ($needs_term_removal && !$needs_post_type_update) {
                    $message .= sprintf(' and remove %s term(s) from %s', implode(', ', $terms), $taxonomy);
                }

                $result['messages'][] = $message;
                continue;
            }

            if (!$has_changes) {
                $result['messages'][] = 'No changes needed for ' . $label;
                continue;
            }

            if ($needs_post_type_update) {
                $updated = wp_update_post([
                    'ID' => (int) $post->ID,
                    'post_type' => $destination_post_type,
                ], true);

                if (is_wp_error($updated)) {
                    $result['failures'][] = sprintf('Failed to migrate %s: %s', $label, $updated->get_error_message());
                    continue;
                }
            }

            foreach ($meta_updates as $meta_key => $meta_value) {
                update_post_meta((int) $post->ID, (string) $meta_key, $meta_value);
            }

            $result['migrated']++;
            $message = $needs_post_type_update ? 'Migrated ' . $label : 'Updated ' . $label;

            if (isset($meta_updates['citer'])) {
                $message .= sprintf(' and set citer to "%s"', $meta_updates['citer']);
            }

            $result['messages'][] = $message;

            if ($needs_term_removal) {
                $removed_terms = wp_remove_object_terms((int) $post->ID, $terms, $taxonomy);

                if (is_wp_error($removed_terms)) {
                    $result['failures'][] = sprintf(
                        '%s %s but failed to remove %s term(s) from taxonomy %s: %s',
                        $needs_post_type_update ? 'Migrated' : 'Updated',
                        $label,
                        implode(', ', $terms),
                        $taxonomy,
                        $removed_terms->get_error_message()
                    );
                    continue;
                }

                $result['terms_removed']++;
                $result['messages'][] = sprintf(
                    'Removed %s term(s) from %s on %s',
                    implode(', ', $terms),
                    $taxonomy,
                    $label
                );
            }
        }

        return $result;
    }
}

if (!function_exists('mz_ptm_migrate_schedule_events_to_segments')) {
    /**
     * Temporary helper for migrating schedule events into segment posts.
     * Defaults to dry run; pass ['dry_run' => false] to perform the migration.
     */
    function mz_ptm_migrate_schedule_events_to_segments(array $args = []): array
    {
        return mz_ptm_run_migration([
            'source' => 'event',
            'destination' => 'segment',
            'taxonomy' => 'event-type',
            'terms' => 'schedule',
            'dry_run' => $args['dry_run'] ?? true,
        ]);
    }
}

if (!function_exists('mz_ptm_store_result')) {
    function mz_ptm_store_result(array $result): void
    {
        $result['recorded_at'] = current_time('mysql');
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            update_user_meta($user_id, MZ_PTM_RESULT_USER_META, $result);
        }

        set_transient(MZ_PTM_RESULT_TRANSIENT, $result, HOUR_IN_SECONDS);
    }
}

if (!function_exists('mz_ptm_get_result')) {
    function mz_ptm_get_result(): array
    {
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            $user_result = get_user_meta($user_id, MZ_PTM_RESULT_USER_META, true);

            if (is_array($user_result) && $user_result !== []) {
                return $user_result;
            }
        }

        $result = get_transient(MZ_PTM_RESULT_TRANSIENT);

        return is_array($result) ? $result : [];
    }
}

if (!function_exists('mz_ptm_store_locality_result')) {
    function mz_ptm_store_locality_result(array $result): void
    {
        $result['recorded_at'] = current_time('mysql');
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            update_user_meta($user_id, MZ_PTM_LOCALITY_RESULT_USER_META, $result);
        }

        set_transient(MZ_PTM_LOCALITY_RESULT_TRANSIENT, $result, HOUR_IN_SECONDS);
    }
}

if (!function_exists('mz_ptm_get_locality_result')) {
    function mz_ptm_get_locality_result(): array
    {
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            $user_result = get_user_meta($user_id, MZ_PTM_LOCALITY_RESULT_USER_META, true);

            if (is_array($user_result) && $user_result !== []) {
                return $user_result;
            }
        }

        $result = get_transient(MZ_PTM_LOCALITY_RESULT_TRANSIENT);

        return is_array($result) ? $result : [];
    }
}

if (!function_exists('mz_ptm_store_media_result')) {
    function mz_ptm_store_media_result(array $result): void
    {
        $result['recorded_at'] = current_time('mysql');
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            update_user_meta($user_id, MZ_PTM_MEDIA_RESULT_USER_META, $result);
        }

        set_transient(MZ_PTM_MEDIA_RESULT_TRANSIENT, $result, HOUR_IN_SECONDS);
    }
}

if (!function_exists('mz_ptm_get_media_result')) {
    function mz_ptm_get_media_result(): array
    {
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            $user_result = get_user_meta($user_id, MZ_PTM_MEDIA_RESULT_USER_META, true);

            if (is_array($user_result) && $user_result !== []) {
                return $user_result;
            }
        }

        $result = get_transient(MZ_PTM_MEDIA_RESULT_TRANSIENT);

        return is_array($result) ? $result : [];
    }
}

if (!function_exists('mz_ptm_store_uploaded_xml_file')) {
    function mz_ptm_store_uploaded_xml_file(string $input_name)
    {
        if (empty($_FILES[$input_name]) || !is_array($_FILES[$input_name])) {
            return '';
        }

        $file = $_FILES[$input_name];
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return '';
        }

        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return new WP_Error('mz_ptm_upload_error', sprintf('Upload failed for %s.', $input_name));
        }

        $original_name = sanitize_file_name((string) ($file['name'] ?? ''));
        $extension = strtolower((string) pathinfo($original_name, PATHINFO_EXTENSION));
        if ($extension !== 'xml') {
            return new WP_Error('mz_ptm_upload_invalid_type', sprintf('Uploaded file for %s must be an XML export.', $input_name));
        }

        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return new WP_Error('mz_ptm_upload_dir_error', (string) $uploads['error']);
        }

        $target_dir = trailingslashit((string) $uploads['basedir']) . 'mz-temp-imports';
        if (!wp_mkdir_p($target_dir)) {
            return new WP_Error('mz_ptm_upload_dir_create_failed', 'Could not create the temporary XML upload directory.');
        }

        $target_name = wp_unique_filename($target_dir, $original_name);
        $target_path = trailingslashit($target_dir) . $target_name;

        if (!@move_uploaded_file((string) $file['tmp_name'], $target_path)) {
            return new WP_Error('mz_ptm_upload_move_failed', sprintf('Could not store uploaded XML for %s.', $input_name));
        }

        return $target_path;
    }
}

if (!function_exists('mz_ptm_handle_admin_post')) {
    function mz_ptm_handle_admin_post(): void
    {
        if (!mz_ptm_user_can_access_tool()) {
            wp_die('You do not have permission to run this migration.', 403);
        }

        check_admin_referer('mz_ptm_run');

        $mode = isset($_REQUEST['mode']) ? sanitize_key((string) $_REQUEST['mode']) : '';
        if (!in_array($mode, ['preview', 'run'], true)) {
            wp_die('Invalid migration mode.', 400);
        }

        $source = isset($_REQUEST['source']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['source'])) : null;
        $destination = isset($_REQUEST['destination']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['destination'])) : null;
        $taxonomy = isset($_REQUEST['taxonomy']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['taxonomy'])) : null;
        $terms = isset($_REQUEST['terms']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['terms'])) : null;

        $result = mz_ptm_run_migration([
            'dry_run' => $mode !== 'run',
            'source' => $source,
            'destination' => $destination,
            'taxonomy' => $taxonomy,
            'terms' => $terms,
        ]);

        mz_ptm_store_result($result);

        $redirect_url = mz_ptm_get_redirect_url();
        $redirect_url = add_query_arg([
            'tab' => 'post-type',
            'source' => implode(',', (array) $result['requested_source_post_types']),
            'destination' => (string) $result['destination_post_type'],
            'taxonomy' => (string) ($result['taxonomy'] ?? ''),
            'terms' => implode(',', (array) ($result['terms'] ?? [])),
            'mz_ptm_result' => !empty($result['dry_run']) ? 'preview' : 'run',
        ], $redirect_url);

        wp_safe_redirect($redirect_url);
        exit;
    }

    add_action('admin_post_' . MZ_PTM_ACTION, 'mz_ptm_handle_admin_post');
}

if (!function_exists('mz_ptm_run_locality_migration')) {
    function mz_ptm_run_locality_migration(array $args = []): array
    {
        $taxonomy = mz_ptm_get_locality_taxonomy();
        $source_slug = sanitize_title((string) ($args['source_term'] ?? ''));
        $destination_slug = sanitize_title((string) ($args['destination_term'] ?? ''));
        $apply = !empty($args['apply']);

        $result = [
            'taxonomy' => $taxonomy,
            'source_term' => $source_slug,
            'destination_term' => $destination_slug,
            'apply' => $apply,
            'objects_matched' => 0,
            'objects_updated' => 0,
            'source_deleted' => 0,
            'errors' => 0,
            'messages' => [],
            'per_post_type' => [],
        ];

        if ($taxonomy === '') {
            $result['errors']++;
            $result['messages'][] = 'No locality/location taxonomy exists on this site.';
            return $result;
        }

        if ($source_slug === '' || $destination_slug === '') {
            $result['errors']++;
            $result['messages'][] = 'Both source and destination locality terms are required.';
            return $result;
        }

        if ($source_slug === $destination_slug) {
            $result['errors']++;
            $result['messages'][] = 'Source and destination locality terms must be different.';
            return $result;
        }

        $source_term = get_term_by('slug', $source_slug, $taxonomy);
        $destination_term = get_term_by('slug', $destination_slug, $taxonomy);

        if (!($source_term instanceof WP_Term)) {
            $result['errors']++;
            $result['messages'][] = sprintf('Source term "%s" was not found in %s.', $source_slug, $taxonomy);
            return $result;
        }

        if (!($destination_term instanceof WP_Term)) {
            $result['errors']++;
            $result['messages'][] = sprintf('Destination term "%s" was not found in %s.', $destination_slug, $taxonomy);
            return $result;
        }

        $object_ids = get_objects_in_term((int) $source_term->term_id, $taxonomy);
        $object_ids = array_values(array_filter(array_map('intval', is_array($object_ids) ? $object_ids : [])));
        sort($object_ids, SORT_NUMERIC);
        $result['objects_matched'] = count($object_ids);

        foreach ($object_ids as $object_id) {
            $post = get_post($object_id);
            $post_type = $post instanceof WP_Post ? (string) $post->post_type : 'unknown';
            if (!isset($result['per_post_type'][$post_type])) {
                $result['per_post_type'][$post_type] = 0;
            }
            $result['per_post_type'][$post_type]++;

            $existing_term_ids = wp_get_object_terms($object_id, $taxonomy, ['fields' => 'ids']);
            if (is_wp_error($existing_term_ids)) {
                $result['errors']++;
                $result['messages'][] = sprintf('Could not load existing locality terms for object %d.', $object_id);
                continue;
            }

            $existing_term_ids = array_map('intval', (array) $existing_term_ids);
            $replacement_term_ids = array_values(array_unique(array_merge(
                array_diff($existing_term_ids, [(int) $source_term->term_id]),
                [(int) $destination_term->term_id]
            )));
            sort($replacement_term_ids, SORT_NUMERIC);

            if (!$apply) {
                $result['objects_updated']++;
                $result['messages'][] = sprintf(
                    'Would move object %d (%s) from %s to %s.',
                    $object_id,
                    $post_type,
                    $source_term->slug,
                    $destination_term->slug
                );
                continue;
            }

            $set_result = wp_set_object_terms($object_id, $replacement_term_ids, $taxonomy, false);
            if (is_wp_error($set_result)) {
                $result['errors']++;
                $result['messages'][] = sprintf(
                    'Failed to update object %d (%s): %s',
                    $object_id,
                    $post_type,
                    $set_result->get_error_message()
                );
                continue;
            }

            $result['objects_updated']++;
            $result['messages'][] = sprintf(
                'Moved object %d (%s) from %s to %s.',
                $object_id,
                $post_type,
                $source_term->slug,
                $destination_term->slug
            );
        }

        if ($apply && $result['errors'] === 0) {
            $remaining_ids = get_objects_in_term((int) $source_term->term_id, $taxonomy);
            $remaining_ids = array_values(array_filter(array_map('intval', is_array($remaining_ids) ? $remaining_ids : [])));

            if ($remaining_ids === []) {
                $deleted = wp_delete_term((int) $source_term->term_id, $taxonomy);
                if (is_wp_error($deleted)) {
                    $result['errors']++;
                    $result['messages'][] = sprintf(
                        'Failed to delete source term %s: %s',
                        $source_term->slug,
                        $deleted->get_error_message()
                    );
                } else {
                    $result['source_deleted'] = 1;
                    $result['messages'][] = sprintf(
                        'Deleted source term %s after migration.',
                        $source_term->slug
                    );
                }
            } else {
                $result['messages'][] = sprintf(
                    'Source term %s was not deleted because %d objects still reference it.',
                    $source_term->slug,
                    count($remaining_ids)
                );
            }
        } elseif (!$apply && $result['objects_matched'] > 0) {
            $result['messages'][] = sprintf(
                'Would delete source term %s after moving %d objects.',
                $source_term->slug,
                $result['objects_matched']
            );
        }

        ksort($result['per_post_type']);

        return $result;
    }
}

if (!function_exists('mz_ptm_run_locality_bulk_migration')) {
    function mz_ptm_run_locality_bulk_migration(array $args = []): array
    {
        $taxonomy = mz_ptm_get_locality_taxonomy();
        $apply = !empty($args['apply']);

        $result = [
            'taxonomy' => $taxonomy,
            'source_term' => '',
            'destination_term' => '',
            'apply' => $apply,
            'bulk' => true,
            'pairs_matched' => 0,
            'source_terms_matched' => 0,
            'objects_matched' => 0,
            'objects_updated' => 0,
            'source_deleted' => 0,
            'errors' => 0,
            'messages' => [],
            'per_post_type' => [],
        ];

        if ($taxonomy === '') {
            $result['errors']++;
            $result['messages'][] = 'No locality/location taxonomy exists on this site.';
            return $result;
        }

        $candidates = mz_ptm_get_locality_merge_candidates($taxonomy);
        $result['pairs_matched'] = count($candidates);

        if ($candidates === []) {
            $result['messages'][] = 'No duplicate locality pairs were found.';
            return $result;
        }

        foreach ($candidates as $candidate) {
            $destination = $candidate['destination'] ?? null;
            $sources = $candidate['sources'] ?? [];

            if (!($destination instanceof WP_Term) || !is_array($sources)) {
                continue;
            }

            foreach ($sources as $source) {
                if (!($source instanceof WP_Term)) {
                    continue;
                }

                $result['source_terms_matched']++;

                $pair_result = mz_ptm_run_locality_migration([
                    'source_term' => (string) $source->slug,
                    'destination_term' => (string) $destination->slug,
                    'apply' => $apply,
                ]);

                $result['objects_matched'] += (int) ($pair_result['objects_matched'] ?? 0);
                $result['objects_updated'] += (int) ($pair_result['objects_updated'] ?? 0);
                $result['source_deleted'] += (int) ($pair_result['source_deleted'] ?? 0);
                $result['errors'] += (int) ($pair_result['errors'] ?? 0);

                foreach ((array) ($pair_result['per_post_type'] ?? []) as $post_type => $count) {
                    if (!isset($result['per_post_type'][$post_type])) {
                        $result['per_post_type'][$post_type] = 0;
                    }

                    $result['per_post_type'][$post_type] += (int) $count;
                }

                $result['messages'][] = sprintf(
                    '%s -> %s: %d objects, deleted source=%d.',
                    (string) $source->slug,
                    (string) $destination->slug,
                    (int) ($pair_result['objects_updated'] ?? 0),
                    (int) ($pair_result['source_deleted'] ?? 0)
                );

                foreach ((array) ($pair_result['messages'] ?? []) as $message) {
                    $message = (string) $message;
                    if (stripos($message, 'Would move object ') === 0 || stripos($message, 'Moved object ') === 0) {
                        continue;
                    }

                    $result['messages'][] = $message;
                }
            }
        }

        ksort($result['per_post_type']);

        return $result;
    }
}

if (!function_exists('mz_ptm_handle_locality_admin_post')) {
    function mz_ptm_handle_locality_admin_post(): void
    {
        if (!mz_ptm_user_can_access_tool()) {
            wp_die('You do not have permission to run this migration.', 403);
        }

        check_admin_referer('mz_ptm_locality_run');

        $mode = isset($_REQUEST['mode']) ? sanitize_key((string) $_REQUEST['mode']) : '';
        if (!in_array($mode, ['preview', 'run'], true)) {
            wp_die('Invalid locality migration mode.', 400);
        }

        $scope = isset($_REQUEST['scope']) ? sanitize_key((string) $_REQUEST['scope']) : 'all';
        $source_term = isset($_REQUEST['source_term']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['source_term'])) : '';
        $destination_term = isset($_REQUEST['destination_term']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['destination_term'])) : '';

        if ($scope === 'single' && $source_term !== '' && $destination_term !== '') {
            $result = mz_ptm_run_locality_migration([
                'source_term' => $source_term,
                'destination_term' => $destination_term,
                'apply' => $mode === 'run',
            ]);
        } else {
            $result = mz_ptm_run_locality_bulk_migration([
                'apply' => $mode === 'run',
            ]);
        }

        mz_ptm_store_locality_result($result);

        $redirect_url = add_query_arg([
            'tab' => 'locality',
            'mz_ptm_locality_result' => $mode === 'run' ? 'run' : 'preview',
        ], mz_ptm_get_redirect_url());

        wp_safe_redirect($redirect_url);
        exit;
    }

    add_action('admin_post_' . MZ_PTM_LOCALITY_ACTION, 'mz_ptm_handle_locality_admin_post');
}

if (!function_exists('mz_ptm_handle_media_admin_post')) {
    function mz_ptm_handle_media_admin_post(): void
    {
        if (!mz_ptm_user_can_access_tool()) {
            wp_die('You do not have permission to run this media repair.', 403);
        }

        check_admin_referer('mz_ptm_media_run');

        $mode = isset($_REQUEST['mode']) ? sanitize_key((string) $_REQUEST['mode']) : '';
        if (!in_array($mode, ['preview', 'run'], true)) {
            wp_die('Invalid media repair mode.', 400);
        }

        $staging_file = isset($_REQUEST['staging_file']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['staging_file'])) : '';
        $production_file = isset($_REQUEST['production_file']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['production_file'])) : '';
        $content_file = isset($_REQUEST['content_file']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['content_file'])) : '';
        $post_types = isset($_REQUEST['post_types']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['post_types'])) : '';

        foreach ([
            'staging_upload' => 'staging_file',
            'production_upload' => 'production_file',
            'content_upload' => 'content_file',
        ] as $upload_key => $target_key) {
            $uploaded_file = mz_ptm_store_uploaded_xml_file($upload_key);
            if (is_wp_error($uploaded_file)) {
                mz_ptm_store_media_result([
                    'apply' => false,
                    'errors' => 1,
                    'messages' => [$uploaded_file->get_error_message()],
                ]);
                wp_safe_redirect(add_query_arg('tab', 'media', mz_ptm_get_redirect_url()));
                exit;
            }

            if (is_string($uploaded_file) && $uploaded_file !== '') {
                if ($target_key === 'staging_file') {
                    $staging_file = $uploaded_file;
                } elseif ($target_key === 'production_file') {
                    $production_file = $uploaded_file;
                } else {
                    $content_file = $uploaded_file;
                }
            }
        }

        $result = mz_ptm_media_repair_run([
            'staging_file' => $staging_file,
            'production_file' => $production_file,
            'content_file' => $content_file,
            'post_types' => $post_types,
            'apply' => $mode === 'run',
        ]);

        mz_ptm_store_media_result($result);

        $redirect_url = add_query_arg([
            'tab' => 'media',
            'staging_file' => $staging_file,
            'production_file' => $production_file,
            'content_file' => $content_file,
            'post_types' => $post_types,
            'mz_ptm_media_result' => $mode === 'run' ? 'run' : 'preview',
        ], mz_ptm_get_redirect_url());

        wp_safe_redirect($redirect_url);
        exit;
    }

    add_action('admin_post_' . MZ_PTM_MEDIA_ACTION, 'mz_ptm_handle_media_admin_post');
}

if (!function_exists('mz_ptm_handle_attach_featured_media_admin_post')) {
    function mz_ptm_handle_attach_featured_media_admin_post(): void
    {
        if (!mz_ptm_user_can_access_tool()) {
            wp_die('You do not have permission to run this media repair.', 403);
        }

        check_admin_referer('mz_ptm_media_attach_run');

        $mode = isset($_REQUEST['mode']) ? sanitize_key((string) $_REQUEST['mode']) : '';
        if (!in_array($mode, ['preview', 'run'], true)) {
            wp_die('Invalid media attachment mode.', 400);
        }

        $post_types = isset($_REQUEST['post_types']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['post_types'])) : '';

        $result = mz_ptm_attach_featured_media_run([
            'post_types' => $post_types,
            'apply' => $mode === 'run',
        ]);

        mz_ptm_store_media_result($result);

        $redirect_url = add_query_arg([
            'tab' => 'media',
            'post_types' => $post_types,
            'mz_ptm_media_result' => $mode === 'run' ? 'run' : 'preview',
        ], mz_ptm_get_redirect_url());

        wp_safe_redirect($redirect_url);
        exit;
    }

    add_action('admin_post_' . MZ_PTM_MEDIA_ATTACH_ACTION, 'mz_ptm_handle_attach_featured_media_admin_post');
}

if (!function_exists('mz_ptm_register_tools_page')) {
    function mz_ptm_register_tools_page(): void
    {
        if (!mz_ptm_user_can_access_tool()) {
            return;
        }

        add_management_page(
            'Migration Tools',
            'Migration',
            'manage_options',
            MZ_PTM_PAGE_SLUG,
            'mz_ptm_render_tools_page'
        );
    }

    add_action('admin_menu', 'mz_ptm_register_tools_page');
}

if (!function_exists('mz_ptm_render_result_summary')) {
    function mz_ptm_render_result_summary(array $result): void
    {
        if ($result === []) {
            return;
        }
        ?>
        <div class="notice notice-info inline">
            <p>
                <strong><?php echo esc_html(!empty($result['dry_run']) ? 'Last dry run' : 'Last migration'); ?></strong>
                <?php if (!empty($result['recorded_at'])) : ?>
                    <span>at <?php echo esc_html((string) $result['recorded_at']); ?></span>
                <?php endif; ?>
            </p>
            <p>
                <?php
                echo esc_html(sprintf(
                    'Sources: %s. Destination: %s. Matched: %d. Migrated: %d. Terms removed: %d. Failures: %d.',
                    implode(', ', (array) $result['source_post_types']),
                    (string) $result['destination_post_type'],
                    (int) ($result['total'] ?? 0),
                    (int) ($result['migrated'] ?? 0),
                    (int) ($result['terms_removed'] ?? 0),
                    count((array) ($result['failures'] ?? []))
                ));
                ?>
            </p>
            <?php if (!empty($result['taxonomy']) && !empty($result['terms'])) : ?>
                <p>
                    <?php
                    echo esc_html(sprintf(
                        'Taxonomy filter: %s = %s',
                        (string) $result['taxonomy'],
                        implode(', ', (array) $result['terms'])
                    ));
                    ?>
                </p>
            <?php endif; ?>
            <?php if (!empty($result['messages'])) : ?>
                <textarea readonly rows="10" style="width:100%;font-family:monospace;"><?php echo esc_textarea(implode("\n", (array) $result['messages'])); ?></textarea>
            <?php endif; ?>
            <?php if (!empty($result['failures'])) : ?>
                <textarea readonly rows="6" style="width:100%;font-family:monospace;"><?php echo esc_textarea(implode("\n", (array) $result['failures'])); ?></textarea>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('mz_ptm_render_media_result_summary')) {
    function mz_ptm_render_media_result_summary(array $result): void
    {
        if ($result === []) {
            return;
        }

        $operation = isset($result['operation']) ? (string) $result['operation'] : 'repair';
        ?>
        <div class="notice notice-info inline">
            <p>
                <strong>
                    <?php
                    echo esc_html(
                        $operation === 'attach-featured-media'
                            ? (!empty($result['apply']) ? 'Last featured media attach run' : 'Last featured media attach dry run')
                            : (!empty($result['apply']) ? 'Last media repair' : 'Last media repair dry run')
                    );
                    ?>
                </strong>
                <?php if (!empty($result['recorded_at'])) : ?>
                    <span>at <?php echo esc_html((string) $result['recorded_at']); ?></span>
                <?php endif; ?>
            </p>
            <?php if ($operation === 'attach-featured-media') : ?>
                <p>
                    <?php
                    echo esc_html(sprintf(
                        'Posts scanned: %d. Attachments updated: %d. Shared attachments assigned: %d. Shared attachments skipped: %d. Missing thumbnails: %d. Missing attachments: %d. Errors: %d.',
                        (int) ($result['posts_scanned'] ?? 0),
                        (int) ($result['attachments_updated'] ?? 0),
                        (int) ($result['shared_attachments_assigned'] ?? 0),
                        (int) ($result['shared_attachments_skipped'] ?? 0),
                        (int) ($result['missing_thumbnails'] ?? 0),
                        (int) ($result['missing_attachments'] ?? 0),
                        (int) ($result['errors'] ?? 0)
                    ));
                    ?>
                </p>
            <?php else : ?>
                <p>
                    <?php
                    echo esc_html(sprintf(
                        'Production attachments: %d. Staging attachments: %d. Matched: %d. ID remaps: %d. Attachment parents updated: %d. Postmeta rows updated: %d. Option rows updated: %d. Post content rows updated: %d. Errors: %d.',
                        (int) ($result['production_attachments'] ?? 0),
                        (int) ($result['staging_attachments'] ?? 0),
                        (int) ($result['matched_attachments'] ?? 0),
                        (int) ($result['id_mappings'] ?? 0),
                        (int) ($result['parents_updated'] ?? 0),
                        (int) ($result['postmeta_updated'] ?? 0),
                        (int) ($result['options_updated'] ?? 0),
                        (int) ($result['post_content_updated'] ?? 0),
                        (int) ($result['errors'] ?? 0)
                    ));
                    ?>
                </p>
            <?php endif; ?>
            <?php if (!empty($result['post_types'])) : ?>
                <p><?php echo esc_html('Content post type scope: ' . implode(', ', (array) $result['post_types'])); ?></p>
            <?php endif; ?>
            <?php if ($operation !== 'attach-featured-media') : ?>
                <p>
                    <?php
                    echo esc_html(sprintf(
                        'Missing in staging export: %d. Missing local attachments: %d. Staging-only attachments skipped: %d. Featured images updated: %d. Featured image posts missing: %d. Featured image attachments missing: %d.',
                        (int) ($result['missing_in_staging'] ?? 0),
                        (int) ($result['missing_local_attachments'] ?? 0),
                        (int) ($result['skipped_staging_only'] ?? 0),
                        (int) ($result['featured_images_updated'] ?? 0),
                        (int) ($result['featured_image_posts_missing'] ?? 0),
                        (int) ($result['featured_image_attachments_missing'] ?? 0)
                    ));
                    ?>
                </p>
            <?php endif; ?>
            <?php if (!empty($result['messages'])) : ?>
                <textarea readonly rows="10" style="width:100%;font-family:monospace;"><?php echo esc_textarea(implode("\n", (array) $result['messages'])); ?></textarea>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('mz_ptm_render_locality_result_summary')) {
    function mz_ptm_render_locality_result_summary(array $result): void
    {
        if ($result === []) {
            return;
        }
        ?>
        <div class="notice notice-info inline">
            <p>
                <strong><?php echo esc_html(!empty($result['apply']) ? 'Last locality migration' : 'Last locality dry run'); ?></strong>
                <?php if (!empty($result['recorded_at'])) : ?>
                    <span>at <?php echo esc_html((string) $result['recorded_at']); ?></span>
                <?php endif; ?>
            </p>
            <p>
                <?php
                if (!empty($result['bulk'])) {
                    echo esc_html(sprintf(
                        'Taxonomy: %s. Duplicate pairs: %d. Source terms processed: %d. Objects matched: %d. Objects updated: %d. Source terms deleted: %d. Errors: %d.',
                        (string) ($result['taxonomy'] ?? ''),
                        (int) ($result['pairs_matched'] ?? 0),
                        (int) ($result['source_terms_matched'] ?? 0),
                        (int) ($result['objects_matched'] ?? 0),
                        (int) ($result['objects_updated'] ?? 0),
                        (int) ($result['source_deleted'] ?? 0),
                        (int) ($result['errors'] ?? 0)
                    ));
                } else {
                    echo esc_html(sprintf(
                        'Taxonomy: %s. Source: %s. Destination: %s. Objects matched: %d. Objects updated: %d. Source deleted: %d. Errors: %d.',
                        (string) ($result['taxonomy'] ?? ''),
                        (string) ($result['source_term'] ?? ''),
                        (string) ($result['destination_term'] ?? ''),
                        (int) ($result['objects_matched'] ?? 0),
                        (int) ($result['objects_updated'] ?? 0),
                        (int) ($result['source_deleted'] ?? 0),
                        (int) ($result['errors'] ?? 0)
                    ));
                }
                ?>
            </p>
            <?php if (!empty($result['per_post_type'])) : ?>
                <p>
                    <?php
                    $parts = [];
                    foreach ((array) $result['per_post_type'] as $post_type => $count) {
                        $parts[] = sprintf('%s=%d', (string) $post_type, (int) $count);
                    }
                    echo esc_html('Matched post types: ' . implode(', ', $parts));
                    ?>
                </p>
            <?php endif; ?>
            <?php if (!empty($result['messages'])) : ?>
                <textarea readonly rows="10" style="width:100%;font-family:monospace;"><?php echo esc_textarea(implode("\n", (array) $result['messages'])); ?></textarea>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('mz_ptm_render_tools_page')) {
    function mz_ptm_render_tools_page(): void
    {
        if (!mz_ptm_user_can_access_tool()) {
            wp_die('You do not have permission to access this page.', 403);
        }

        $source = isset($_GET['source']) ? sanitize_text_field(wp_unslash((string) $_GET['source'])) : '';
        $destination = isset($_GET['destination']) ? sanitize_text_field(wp_unslash((string) $_GET['destination'])) : '';
        $taxonomy = isset($_GET['taxonomy']) ? sanitize_text_field(wp_unslash((string) $_GET['taxonomy'])) : '';
        $terms = isset($_GET['terms']) ? sanitize_text_field(wp_unslash((string) $_GET['terms'])) : '';
        $media_result = mz_ptm_get_media_result();
        $locality_result = mz_ptm_get_locality_result();
        $staging_file = isset($_GET['staging_file']) ? sanitize_text_field(wp_unslash((string) $_GET['staging_file'])) : (string) ($media_result['staging_file'] ?? '');
        $production_file = isset($_GET['production_file']) ? sanitize_text_field(wp_unslash((string) $_GET['production_file'])) : (string) ($media_result['production_file'] ?? '');
        $content_file = isset($_GET['content_file']) ? sanitize_text_field(wp_unslash((string) $_GET['content_file'])) : (string) ($media_result['content_file'] ?? '');
        $post_types = isset($_GET['post_types']) ? sanitize_text_field(wp_unslash((string) $_GET['post_types'])) : '';
        $faq_limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 0;
        $faq_source_key = isset($_GET['source_key']) ? sanitize_text_field(wp_unslash((string) $_GET['source_key'])) : '';
        $faq_result = function_exists('mzfmt_get_result') ? mzfmt_get_result() : [];
        $active_tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'post-type';
        if (!in_array($active_tab, ['post-type', 'locality', 'media', 'faq', 'form'], true)) {
            $active_tab = 'post-type';
        }
        $result = mz_ptm_get_result();
        $locality_taxonomy = mz_ptm_get_locality_taxonomy();
        $locality_candidates = $locality_taxonomy !== '' ? mz_ptm_get_locality_merge_candidates($locality_taxonomy) : [];
        ?>
        <div class="wrap" data-meza-admin-chrome="post-type-migration-tools">
            <h1 class="wp-heading-inline">Migration Tools</h1>
            <hr class="wp-header-end" />
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg('tab', 'post-type', mz_ptm_get_redirect_url())); ?>" class="nav-tab <?php echo $active_tab === 'post-type' ? 'nav-tab-active' : ''; ?>">Post Type Migration</a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'locality', mz_ptm_get_redirect_url())); ?>" class="nav-tab <?php echo $active_tab === 'locality' ? 'nav-tab-active' : ''; ?>">Locality Migration</a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'media', mz_ptm_get_redirect_url())); ?>" class="nav-tab <?php echo $active_tab === 'media' ? 'nav-tab-active' : ''; ?>">Media Migration</a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'faq', mz_ptm_get_redirect_url())); ?>" class="nav-tab <?php echo $active_tab === 'faq' ? 'nav-tab-active' : ''; ?>">FAQ Migration</a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'form', mz_ptm_get_redirect_url())); ?>" class="nav-tab <?php echo $active_tab === 'form' ? 'nav-tab-active' : ''; ?>">Form Migration</a>
            </h2>

            <?php if ($active_tab === 'post-type') : ?>
                <p>Move posts from one or more source post types into a destination post type. This changes the existing posts in place.</p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('mz_ptm_run'); ?>
                    <input type="hidden" name="action" value="<?php echo esc_attr(MZ_PTM_ACTION); ?>" />

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="mz-ptm-source">Source post types</label></th>
                                <td>
                                    <input id="mz-ptm-source" name="source" type="text" class="regular-text" value="<?php echo esc_attr($source); ?>" />
                                    <p class="description">Add post slugs separated by commas</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="mz-ptm-taxonomy">Source taxonomy</label></th>
                                <td>
                                    <input id="mz-ptm-taxonomy" name="taxonomy" type="text" class="regular-text" value="<?php echo esc_attr($taxonomy); ?>" />
                                    <p class="description">Add a taxonomy slug to filter posts by</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="mz-ptm-terms">Source term slugs</label></th>
                                <td>
                                    <input id="mz-ptm-terms" name="terms" type="text" class="regular-text" value="<?php echo esc_attr($terms); ?>" />
                                    <p class="description">Add taxonomy term slugs separated by commas to filter posts by</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="mz-ptm-destination">Destination post type</label></th>
                                <td>
                                    <input id="mz-ptm-destination" name="destination" type="text" class="regular-text" value="<?php echo esc_attr($destination); ?>" />
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p class="submit">
                        <button type="submit" name="mode" value="preview" class="button button-secondary">Run dry run</button>
                        <button type="submit" name="mode" value="run" class="button button-primary">Run migration</button>
                    </p>
                </form>

                <?php mz_ptm_render_result_summary($result); ?>
            <?php elseif ($active_tab === 'locality') : ?>
                <p>Move every duplicate locality term into its <code>City, State</code> version within the active <code><?php echo esc_html($locality_taxonomy !== '' ? $locality_taxonomy : 'locality/location'); ?></code> taxonomy, then remove the old terms when they are no longer used.</p>

                <?php if ($locality_taxonomy === '') : ?>
                    <div class="notice notice-error inline"><p>No locality/location taxonomy exists on this site.</p></div>
                <?php else : ?>
                    <p><?php echo esc_html(sprintf('Detected duplicate locality groups: %d.', count((array) $locality_candidates))); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('mz_ptm_locality_run'); ?>
                        <input type="hidden" name="action" value="<?php echo esc_attr(MZ_PTM_LOCALITY_ACTION); ?>" />
                        <input type="hidden" name="scope" value="all" />

                        <p class="submit">
                            <button type="submit" name="mode" value="preview" class="button button-secondary">Run dry run</button>
                            <button type="submit" name="mode" value="run" class="button button-primary">Run locality migration</button>
                        </p>
                    </form>
                <?php endif; ?>

                <?php mz_ptm_render_locality_result_summary($locality_result); ?>
            <?php elseif ($active_tab === 'faq') : ?>
                <?php if (!function_exists('mzfmt_run_migration')) : ?>
                    <div class="notice notice-error inline"><p>FAQ migration tools are not available.</p></div>
                <?php else : ?>
                    <p>Migrate legacy <code>section_faq</code> content into built-in <code>faq</code> posts and the shared <code>section_faqs</code> structure.</p>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('mzfmt_run'); ?>
                        <input type="hidden" name="action" value="<?php echo esc_attr(defined('MZFQMT_ACTION') ? MZFQMT_ACTION : 'mz_faq_migration'); ?>" />

                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row"><label for="mzfmt-source-key">Source key</label></th>
                                    <td>
                                        <input id="mzfmt-source-key" name="source_key" type="text" class="regular-text" value="<?php echo esc_attr($faq_source_key); ?>" />
                                        <p class="description">Optional. Use <code>post:123</code> or <code>term:sign_type:32</code> to target one source.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="mzfmt-limit">Limit</label></th>
                                    <td>
                                        <input id="mzfmt-limit" name="limit" type="number" min="0" step="1" class="small-text" value="<?php echo esc_attr((string) $faq_limit); ?>" />
                                        <p class="description">Leave at <code>0</code> to scan every source.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <p class="submit">
                            <button type="submit" name="mode" value="preview" class="button button-secondary">Run dry run</button>
                            <button type="submit" name="mode" value="run" class="button button-primary">Run migration</button>
                        </p>
                    </form>

                    <?php mzfmt_render_result_summary(is_array($faq_result) ? $faq_result : []); ?>
                <?php endif; ?>
            <?php elseif ($active_tab === 'form') : ?>
                <?php if (!function_exists('mzf_mt_render_tools_tab')) : ?>
                    <div class="notice notice-error inline"><p>Form migration tools are not available.</p></div>
                <?php else : ?>
                    <?php mzf_mt_render_tools_tab(); ?>
                <?php endif; ?>
            <?php else : ?>
                <p>Compare staging and production attachment exports, then repair attachment IDs, parent relationships, featured images, and related references in this database. Production is treated as the source of truth.</p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <?php wp_nonce_field('mz_ptm_media_run'); ?>
                    <input type="hidden" name="action" value="<?php echo esc_attr(MZ_PTM_MEDIA_ACTION); ?>" />

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="mz-ptm-staging-file">Staging media export</label></th>
                                <td>
                                    <input id="mz-ptm-staging-file" name="staging_file" type="hidden" value="<?php echo esc_attr($staging_file); ?>" />
                                    <p><input name="staging_upload" type="file" accept=".xml,text/xml,application/xml" /></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="mz-ptm-production-file">Production media export</label></th>
                                <td>
                                    <input id="mz-ptm-production-file" name="production_file" type="hidden" value="<?php echo esc_attr($production_file); ?>" />
                                    <p><input name="production_upload" type="file" accept=".xml,text/xml,application/xml" /></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="mz-ptm-content-file">Production content export</label></th>
                                <td>
                                    <input id="mz-ptm-content-file" name="content_file" type="hidden" value="<?php echo esc_attr($content_file); ?>" />
                                    <p class="description">Optional production full-content WXR/XML export used to restore featured images from the source site.</p>
                                    <p><input name="content_upload" type="file" accept=".xml,text/xml,application/xml" /></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="mz-ptm-post-types">Content post types</label></th>
                                <td>
                                    <input id="mz-ptm-post-types" name="post_types" type="text" class="regular-text code" value="<?php echo esc_attr($post_types); ?>" />
                                    <p class="description">Optional comma-separated post types to limit featured-image restoration. Leave blank to include all post types found in the content export.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p class="submit">
                        <button type="submit" name="mode" value="preview" class="button button-secondary">Run media dry run</button>
                        <button type="submit" name="mode" value="run" class="button button-primary">Run media repair</button>
                    </p>
                </form>

                <hr />
                <p>Attach each post's featured image to that post in WordPress so it shows as uploaded to the post in the admin media UI.</p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('mz_ptm_media_attach_run'); ?>
                    <input type="hidden" name="action" value="<?php echo esc_attr(MZ_PTM_MEDIA_ATTACH_ACTION); ?>" />

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="mz-ptm-attach-post-types">Content post types</label></th>
                                <td>
                                    <input id="mz-ptm-attach-post-types" name="post_types" type="text" class="regular-text code" value="<?php echo esc_attr($post_types); ?>" />
                                    <p class="description">Optional comma-separated post types. Leave blank to scan all public content types with featured images. Use <code>sign</code> to target only sign posts.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p class="submit">
                        <button type="submit" name="mode" value="preview" class="button button-secondary">Run attach dry run</button>
                        <button type="submit" name="mode" value="run" class="button button-primary">Run attach sync</button>
                    </p>
                </form>

                <?php mz_ptm_render_media_result_summary($media_result); ?>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (defined('WP_CLI') && WP_CLI && !class_exists('MZ_Post_Type_Migration_CLI_Command')) {
    class MZ_Post_Type_Migration_CLI_Command
    {
        /**
         * Move posts from one or more source post types into a destination post type.
         *
         * ## OPTIONS
         *
         * --source=<source>
         * : Comma-separated source post types.
         *
         * --destination=<destination>
         * : Destination post type.
         *
         * [--taxonomy=<taxonomy>]
         * : Optional taxonomy slug to filter source posts.
         *
         * [--terms=<terms>]
         * : Optional comma-separated term slugs used with --taxonomy.
         *
         * [--dry-run]
         * : Preview the migration without writing changes.
         *
         * ## EXAMPLES
         *
         *     wp mz post-type-migrate --source=testimonial --destination=review --dry-run
         *     wp mz post-type-migrate --source=event,event_archive --destination=resource
         *
         * @when after_wp_load
         */
        public function __invoke($args, $assoc_args): void
        {
            if (empty($assoc_args['source']) || empty($assoc_args['destination'])) {
                \WP_CLI::error('Both --source and --destination are required.');
            }

            $result = mz_ptm_run_migration([
                'source' => $assoc_args['source'],
                'destination' => $assoc_args['destination'],
                'taxonomy' => $assoc_args['taxonomy'] ?? null,
                'terms' => $assoc_args['terms'] ?? null,
                'dry_run' => isset($assoc_args['dry-run']),
            ]);

            foreach ((array) $result['messages'] as $message) {
                \WP_CLI::log($message);
            }

            \WP_CLI::log('');
            \WP_CLI::log('Requested source post types: ' . implode(', ', (array) $result['requested_source_post_types']));
            \WP_CLI::log('Matched source post types: ' . implode(', ', (array) $result['source_post_types']));
            \WP_CLI::log('Destination post type: ' . (string) $result['destination_post_type']);
            if (!empty($result['taxonomy']) && !empty($result['terms'])) {
                \WP_CLI::log('Taxonomy filter: ' . (string) $result['taxonomy'] . ' = ' . implode(', ', (array) $result['terms']));
            }
            \WP_CLI::log('Mode: ' . (!empty($result['dry_run']) ? 'dry run' : 'live run'));
            \WP_CLI::log('Matched posts: ' . (int) $result['total']);
            \WP_CLI::log('Migrated posts: ' . (int) $result['migrated']);
            \WP_CLI::log('Posts with filtered terms removed: ' . (int) ($result['terms_removed'] ?? 0));
            \WP_CLI::log('Failures: ' . count((array) $result['failures']));

            if (!empty($result['failures'])) {
                foreach ((array) $result['failures'] as $failure) {
                    \WP_CLI::warning((string) $failure);
                }

                \WP_CLI::halt(1);
            }

            \WP_CLI::success(!empty($result['dry_run']) ? 'Dry run complete.' : 'Migration complete.');
        }
    }

    \WP_CLI::add_command('mz post-type-migrate', 'MZ_Post_Type_Migration_CLI_Command');
}

if (defined('WP_CLI') && WP_CLI && !class_exists('MZ_Locality_Migration_CLI_Command')) {
    class MZ_Locality_Migration_CLI_Command
    {
        /**
         * Move posts from one locality term to another and delete the source term when empty.
         *
         * ## OPTIONS
         *
         * [--source-term=<slug>]
         * : Source locality term slug.
         *
         * [--destination-term=<slug>]
         * : Destination locality term slug.
         *
         * [--all]
         * : Run every duplicate locality merge that has a matching City, State destination.
         *
         * [--apply]
         * : Perform writes. Omit for a dry run.
         *
         * ## EXAMPLES
         *
         *     wp mz migrate-locality --all
         *     wp mz migrate-locality --all --apply
         *     wp mz migrate-locality --source-term=alexandria --destination-term=alexandria-virginia
         *     wp mz migrate-locality --source-term=alexandria --destination-term=alexandria-virginia --apply
         *
         * @when after_wp_load
         */
        public function __invoke($args, $assoc_args): void
        {
            $run_all = isset($assoc_args['all']);

            if ($run_all) {
                $result = mz_ptm_run_locality_bulk_migration([
                    'apply' => isset($assoc_args['apply']),
                ]);
            } else {
                if (empty($assoc_args['source-term']) || empty($assoc_args['destination-term'])) {
                    \WP_CLI::error('Use --all or provide both --source-term and --destination-term.');
                }

                $result = mz_ptm_run_locality_migration([
                    'source_term' => (string) $assoc_args['source-term'],
                    'destination_term' => (string) $assoc_args['destination-term'],
                    'apply' => isset($assoc_args['apply']),
                ]);
            }

            foreach ((array) $result['messages'] as $message) {
                \WP_CLI::log((string) $message);
            }

            \WP_CLI::log('');
            \WP_CLI::log('Taxonomy: ' . (string) ($result['taxonomy'] ?? ''));
            \WP_CLI::log('Mode: ' . (!empty($result['apply']) ? 'live run' : 'dry run'));
            if (!empty($result['bulk'])) {
                \WP_CLI::log('Duplicate pairs: ' . (int) ($result['pairs_matched'] ?? 0));
                \WP_CLI::log('Source terms processed: ' . (int) ($result['source_terms_matched'] ?? 0));
            } else {
                \WP_CLI::log('Source term: ' . (string) ($result['source_term'] ?? ''));
                \WP_CLI::log('Destination term: ' . (string) ($result['destination_term'] ?? ''));
            }
            \WP_CLI::log('Objects matched: ' . (int) ($result['objects_matched'] ?? 0));
            \WP_CLI::log('Objects updated: ' . (int) ($result['objects_updated'] ?? 0));
            \WP_CLI::log('Source deleted: ' . (int) ($result['source_deleted'] ?? 0));
            foreach ((array) ($result['per_post_type'] ?? []) as $post_type => $count) {
                \WP_CLI::log(sprintf('Type %s: %d', (string) $post_type, (int) $count));
            }
            \WP_CLI::log('Errors: ' . (int) ($result['errors'] ?? 0));

            if (!empty($result['errors'])) {
                \WP_CLI::halt(1);
            }

            \WP_CLI::success(!empty($result['apply']) ? 'Locality migration complete.' : 'Locality migration dry run complete.');
        }
    }

    \WP_CLI::add_command('mz migrate-locality', 'MZ_Locality_Migration_CLI_Command');
}

if (!function_exists('mz_ptm_wxr_supported_post_types')) {
    function mz_ptm_wxr_supported_post_types(): array
    {
        return ['post', 'page', 'sign', 'customer', 'attachment', 'form', 'mzf_submission'];
    }
}

if (!function_exists('mz_ptm_wxr_mapped_taxonomy')) {
    function mz_ptm_wxr_mapped_taxonomy(string $taxonomy, string $post_type = ''): string
    {
        $taxonomy = sanitize_key($taxonomy);
        $post_type = sanitize_key($post_type);

        if ($taxonomy === 'location' && in_array($post_type, ['sign', 'customer', 'service', 'review'], true)) {
            return 'locality';
        }

        return $taxonomy;
    }
}

if (!function_exists('mz_ptm_wxr_parse_types')) {
    function mz_ptm_wxr_parse_types($raw_types = null): array
    {
        $types = $raw_types;

        if (!is_array($types)) {
            $types = $types !== null ? explode(',', (string) $types) : mz_ptm_wxr_supported_post_types();
        }

        $types = array_map('sanitize_key', array_map('strval', $types));
        $types = array_values(array_intersect(array_unique($types), mz_ptm_wxr_supported_post_types()));

        return $types !== [] ? $types : mz_ptm_wxr_supported_post_types();
    }
}

if (!function_exists('mz_ptm_resolve_project_root_file_path')) {
    function mz_ptm_resolve_project_root_file_path(string $file_path): string
    {
        $file_path = trim($file_path);
        if ($file_path === '') {
            return '';
        }

        if (str_starts_with($file_path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $file_path)) {
            return $file_path;
        }

        return trailingslashit(ABSPATH) . ltrim($file_path, '/\\');
    }
}

if (!function_exists('mz_ptm_wxr_normalize_status')) {
    function mz_ptm_wxr_normalize_status(string $status): string
    {
        $status = sanitize_key($status);
        $allowed = ['publish', 'future', 'draft', 'pending', 'private', 'inherit'];

        return in_array($status, $allowed, true) ? $status : 'draft';
    }
}

if (!function_exists('mz_ptm_wxr_should_skip_item')) {
    function mz_ptm_wxr_should_skip_item(array $item): bool
    {
        if (($item['slug'] ?? '') === '') {
            return true;
        }

        $post_type = (string) ($item['post_type'] ?? '');
        $status = (string) ($item['status'] ?? '');

        if ($post_type === 'attachment') {
            return in_array($status, ['trash', 'auto-draft'], true);
        }

        return in_array($status, ['trash', 'auto-draft', 'inherit'], true);
    }
}

if (!function_exists('mz_ptm_wxr_find_local_post_id_by_legacy_id')) {
    function mz_ptm_wxr_find_local_post_id_by_legacy_id(int $legacy_id): int
    {
        if ($legacy_id <= 0) {
            return 0;
        }

        $existing_ids = get_posts([
            'post_type' => 'any',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => '_mz_wxr_source_post_id',
            'meta_value' => (string) $legacy_id,
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'suppress_filters' => true,
        ]);

        return !empty($existing_ids[0]) ? (int) $existing_ids[0] : 0;
    }
}

if (!function_exists('mz_ptm_wxr_find_existing_post')) {
    function mz_ptm_wxr_find_existing_post(array $item): ?WP_Post
    {
        $legacy_id = (int) ($item['legacy_id'] ?? 0);
        if ($legacy_id > 0) {
            $existing_id = mz_ptm_wxr_find_local_post_id_by_legacy_id($legacy_id);
            if ($existing_id > 0) {
                $existing = get_post($existing_id);
                if ($existing instanceof WP_Post && $existing->post_type === (string) ($item['post_type'] ?? 'post')) {
                    return $existing;
                }
            }
        }

        $slug = (string) ($item['slug'] ?? '');
        if ($slug === '') {
            return null;
        }

        $posts = get_posts([
            'name' => $slug,
            'post_type' => (string) ($item['post_type'] ?? 'post'),
            'post_status' => 'any',
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'suppress_filters' => true,
        ]);

        return !empty($posts[0]) && $posts[0] instanceof WP_Post ? $posts[0] : null;
    }
}

if (!function_exists('mz_ptm_wxr_find_meta_value_by_suffix')) {
    function mz_ptm_wxr_find_meta_value_by_suffix(array $meta, string $suffix): string
    {
        foreach ($meta as $key => $value) {
            $key = (string) $key;
            if ($key !== '' && str_ends_with($key, $suffix)) {
                return (string) $value;
            }
        }

        return '';
    }
}

if (!function_exists('mz_ptm_wxr_get_attachment_relative_path')) {
    function mz_ptm_wxr_get_attachment_relative_path(array $item): string
    {
        $relative_path = mz_ptm_wxr_find_meta_value_by_suffix((array) ($item['meta'] ?? []), '_attached_file');
        $relative_path = ltrim(str_replace('\\', '/', (string) $relative_path), '/');

        if ($relative_path !== '') {
            return $relative_path;
        }

        $attachment_url = (string) ($item['attachment_url'] ?? '');
        if ($attachment_url === '') {
            return '';
        }

        $path = (string) parse_url($attachment_url, PHP_URL_PATH);
        $marker = '/wp-content/uploads/';
        $position = strpos($path, $marker);

        if ($position === false) {
            return ltrim(basename($path), '/');
        }

        return ltrim(substr($path, $position + strlen($marker)), '/');
    }
}

if (!function_exists('mz_ptm_wxr_get_attachment_alt_text')) {
    function mz_ptm_wxr_get_attachment_alt_text(array $item): string
    {
        return trim((string) mz_ptm_wxr_find_meta_value_by_suffix((array) ($item['meta'] ?? []), '_attachment_image_alt'));
    }
}

if (!function_exists('mz_ptm_wxr_parse_relationship_meta_value')) {
    function mz_ptm_wxr_parse_relationship_meta_value($raw_value): array
    {
        if (is_array($raw_value)) {
            return array_values(array_filter(array_map('intval', $raw_value)));
        }

        $value = (string) $raw_value;
        if ($value === '') {
            return [];
        }

        if (is_serialized($value)) {
            $unserialized = maybe_unserialize($value);
            if (is_array($unserialized)) {
                return array_values(array_filter(array_map('intval', $unserialized)));
            }
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_filter(array_map('intval', $decoded)));
        }

        if (preg_match('/^\d+(,\d+)*$/', $value) === 1) {
            return array_values(array_filter(array_map('intval', explode(',', $value))));
        }

        return ctype_digit($value) ? [(int) $value] : [];
    }
}

if (!function_exists('mz_ptm_wxr_resolve_author_id')) {
    function mz_ptm_wxr_resolve_author_id(string $creator = ''): int
    {
        $creator = sanitize_user($creator, true);
        if ($creator !== '') {
            $user = get_user_by('login', $creator);
            if ($user instanceof WP_User) {
                return (int) $user->ID;
            }
        }

        $current_user_id = get_current_user_id();
        if ($current_user_id > 0) {
            return $current_user_id;
        }

        $admins = get_users([
            'role__in' => ['administrator'],
            'number' => 1,
            'fields' => ['ID'],
        ]);

        if (!empty($admins[0]->ID)) {
            return (int) $admins[0]->ID;
        }

        return 1;
    }
}

if (!function_exists('mz_ptm_wxr_parse_post_meta')) {
    function mz_ptm_wxr_parse_post_meta(SimpleXMLElement $item, string $wp_namespace): array
    {
        $meta = [];
        foreach ($item->children($wp_namespace)->postmeta as $postmeta) {
            $meta_key = (string) $postmeta->meta_key;
            if ($meta_key === '') {
                continue;
            }

            if (!isset($meta[$meta_key])) {
                $meta[$meta_key] = [];
            }

            $meta[$meta_key][] = (string) $postmeta->meta_value;
        }

        return $meta;
    }
}

if (!function_exists('mz_ptm_wxr_should_sync_all_meta')) {
    function mz_ptm_wxr_should_sync_all_meta(string $post_type): bool
    {
        return in_array(sanitize_key($post_type), ['form', 'mzf_submission'], true);
    }
}

if (!function_exists('mz_ptm_wxr_get_local_meta_map')) {
    function mz_ptm_wxr_get_local_meta_map(int $post_id, bool $include_all_meta = false): array
    {
        $normalized = [];

        foreach (get_post_meta($post_id) as $key => $values) {
            $key = (string) $key;
            if ($key === '' || str_starts_with($key, '_mz_wxr_')) {
                continue;
            }

            if (!$include_all_meta && str_starts_with($key, '_') && !in_array($key, ['_thumbnail_id', '_wp_page_template'], true)) {
                continue;
            }

            $normalized[$key] = array_map('strval', is_array($values) ? array_values($values) : [$values]);
        }

        ksort($normalized);

        return $normalized;
    }
}

if (!function_exists('mz_ptm_wxr_get_expected_meta_map')) {
    function mz_ptm_wxr_get_expected_meta_map(array $item, bool $include_all_meta = false): array
    {
        $normalized = [];

        foreach ((array) ($item['meta'] ?? []) as $key => $values) {
            $key = (string) $key;
            if ($key === '' || str_starts_with($key, '_mz_wxr_')) {
                continue;
            }

            if (!$include_all_meta && str_starts_with($key, '_') && !in_array($key, ['_thumbnail_id', '_wp_page_template'], true)) {
                continue;
            }

            $normalized[$key] = array_map('strval', is_array($values) ? array_values($values) : [$values]);
        }

        ksort($normalized);

        return $normalized;
    }
}

if (!function_exists('mz_ptm_wxr_collect_terms')) {
    function mz_ptm_wxr_collect_terms(SimpleXMLElement $item, string $post_type = ''): array
    {
        $terms = [];

        foreach ($item->category as $category) {
            $taxonomy = mz_ptm_wxr_mapped_taxonomy((string) $category['domain'], $post_type);
            $slug = sanitize_title((string) $category['nicename']);
            $name = trim((string) $category);

            if ($taxonomy === '' || $slug === '' || $name === '') {
                continue;
            }

            if (!isset($terms[$taxonomy])) {
                $terms[$taxonomy] = [];
            }

            $terms[$taxonomy][$slug] = [
                'slug' => $slug,
                'name' => $name,
            ];
        }

        foreach ($terms as $taxonomy => $taxonomy_terms) {
            $terms[$taxonomy] = array_values($taxonomy_terms);
        }

        return $terms;
    }
}

if (!function_exists('mz_ptm_wxr_parse_file')) {
    function mz_ptm_wxr_parse_file(string $file_path, array $allowed_types): array
    {
        if (!is_readable($file_path)) {
            return new WP_Error('mz_ptm_wxr_file_missing', sprintf('Import file is not readable: %s', $file_path));
        }

        $xml = simplexml_load_file($file_path, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_COMPACT);
        if (!($xml instanceof SimpleXMLElement)) {
            return new WP_Error('mz_ptm_wxr_invalid_xml', 'Could not parse the WXR file.');
        }

        $namespaces = $xml->getNamespaces(true);
        $wp_namespace = $namespaces['wp'] ?? '';
        $content_namespace = $namespaces['content'] ?? '';
        $excerpt_namespace = $namespaces['excerpt'] ?? '';
        $dc_namespace = $namespaces['dc'] ?? '';

        if ($wp_namespace === '') {
            return new WP_Error('mz_ptm_wxr_missing_namespace', 'The WXR file is missing the wp namespace.');
        }

        $items = [];

        foreach ($xml->channel->item as $item) {
            $wp = $item->children($wp_namespace);
            $post_type = sanitize_key((string) $wp->post_type);

            if (!in_array($post_type, $allowed_types, true)) {
                continue;
            }

            $slug = sanitize_title((string) $wp->post_name);
            if ($slug === '') {
                $slug = sanitize_title((string) $item->title);
            }

            $parsed_item = [
                'legacy_id' => (int) $wp->post_id,
                'post_type' => $post_type,
                'slug' => $slug,
                'title' => wp_strip_all_tags((string) $item->title),
                'status' => sanitize_key((string) $wp->status),
                'content' => $content_namespace !== '' ? (string) $item->children($content_namespace)->encoded : '',
                'excerpt' => $excerpt_namespace !== '' ? (string) $item->children($excerpt_namespace)->encoded : '',
                'creator' => $dc_namespace !== '' ? (string) $item->children($dc_namespace)->creator : '',
                'date' => (string) $wp->post_date,
                'menu_order' => (int) $wp->menu_order,
                'parent_legacy_id' => (int) $wp->post_parent,
                'attachment_url' => (string) $wp->attachment_url,
                'comment_status' => (string) $wp->comment_status,
                'ping_status' => (string) $wp->ping_status,
                'terms' => mz_ptm_wxr_collect_terms($item, $post_type),
                'meta' => mz_ptm_wxr_parse_post_meta($item, $wp_namespace),
            ];

            if (mz_ptm_wxr_should_skip_item($parsed_item)) {
                continue;
            }

            $items[] = $parsed_item;
        }

        return $items;
    }
}

if (!function_exists('mz_ptm_wxr_upsert_term')) {
    function mz_ptm_wxr_upsert_term(array $term, string $taxonomy, bool $apply)
    {
        $existing = get_term_by('slug', (string) $term['slug'], $taxonomy);
        if ($existing instanceof WP_Term) {
            return $existing;
        }

        if (!$apply) {
            return [
                'term_id' => 0,
                'slug' => (string) $term['slug'],
                'name' => (string) $term['name'],
                'planned_create' => true,
            ];
        }

        $created = wp_insert_term((string) $term['name'], $taxonomy, [
            'slug' => (string) $term['slug'],
        ]);

        if (is_wp_error($created)) {
            return $created;
        }

        return get_term((int) $created['term_id'], $taxonomy);
    }
}

if (!function_exists('mz_ptm_wxr_apply_terms_to_post')) {
    function mz_ptm_wxr_apply_terms_to_post(int $post_id, array $terms_by_taxonomy, bool $apply, array &$messages): bool
    {
        foreach ($terms_by_taxonomy as $taxonomy => $terms) {
            if (!taxonomy_exists($taxonomy)) {
                $messages[] = sprintf('Skipped taxonomy "%s" for post %d because it does not exist locally.', $taxonomy, $post_id);
                continue;
            }

            $term_ids = [];

            foreach ((array) $terms as $term) {
                $resolved_term = mz_ptm_wxr_upsert_term((array) $term, $taxonomy, $apply);
                if (is_wp_error($resolved_term)) {
                    $messages[] = sprintf(
                        'Failed to prepare term "%s" in taxonomy "%s" for post %d: %s',
                        (string) ($term['slug'] ?? ''),
                        $taxonomy,
                        $post_id,
                        $resolved_term->get_error_message()
                    );
                    return false;
                }

                if ($resolved_term instanceof WP_Term) {
                    $term_ids[] = (int) $resolved_term->term_id;
                }
            }

            if ($apply) {
                $set_result = wp_set_object_terms($post_id, $term_ids, $taxonomy, false);
                if (is_wp_error($set_result)) {
                    $messages[] = sprintf(
                        'Failed assigning %s terms to post %d: %s',
                        $taxonomy,
                        $post_id,
                        $set_result->get_error_message()
                    );
                    return false;
                }
            }
        }

        return true;
    }
}

if (!function_exists('mz_ptm_wxr_get_local_term_slug_map')) {
    function mz_ptm_wxr_get_local_term_slug_map(int $post_id, array $terms_by_taxonomy): array
    {
        $local_terms = [];

        foreach (array_keys($terms_by_taxonomy) as $taxonomy) {
            $taxonomy = sanitize_key((string) $taxonomy);
            if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {
                continue;
            }

            $local_terms[$taxonomy] = wp_get_object_terms($post_id, $taxonomy, [
                'fields' => 'slugs',
            ]);

            if (is_wp_error($local_terms[$taxonomy])) {
                $local_terms[$taxonomy] = [];
            }

            sort($local_terms[$taxonomy]);
        }

        ksort($local_terms);

        return $local_terms;
    }
}

if (!function_exists('mz_ptm_wxr_get_expected_term_slug_map')) {
    function mz_ptm_wxr_get_expected_term_slug_map(array $terms_by_taxonomy): array
    {
        $expected_terms = [];

        foreach ($terms_by_taxonomy as $taxonomy => $terms) {
            $taxonomy = sanitize_key((string) $taxonomy);
            if ($taxonomy === '') {
                continue;
            }

            $expected_terms[$taxonomy] = array_values(array_filter(array_map(
                static fn($term): string => sanitize_title((string) ($term['slug'] ?? '')),
                (array) $terms
            )));

            sort($expected_terms[$taxonomy]);
        }

        ksort($expected_terms);

        return $expected_terms;
    }
}

if (!function_exists('mz_ptm_wxr_apply_post_meta')) {
    function mz_ptm_wxr_apply_post_meta(int $post_id, array $item, bool $apply): void
    {
        if (!$apply) {
            return;
        }

        update_post_meta($post_id, '_mz_wxr_source_post_id', (int) ($item['legacy_id'] ?? 0));
        update_post_meta($post_id, '_mz_wxr_source_slug', (string) ($item['slug'] ?? ''));

        $page_template_values = $item['meta']['_wp_page_template'] ?? [];
        $page_template = is_array($page_template_values) ? (string) ($page_template_values[0] ?? '') : (string) $page_template_values;
        if ($page_template !== '') {
            update_post_meta($post_id, '_wp_page_template', $page_template);
        }

        if (($item['post_type'] ?? '') === 'attachment') {
            $attachment_url = (string) ($item['attachment_url'] ?? '');
            if ($attachment_url !== '') {
                update_post_meta($post_id, '_mz_wxr_source_attachment_url', $attachment_url);
            }

            $relative_path = mz_ptm_wxr_get_attachment_relative_path($item);
            if ($relative_path !== '') {
                update_post_meta($post_id, '_mz_wxr_source_attached_file', $relative_path);
            }
        }
    }
}

if (!function_exists('mz_ptm_wxr_apply_full_meta_sync')) {
    function mz_ptm_wxr_apply_full_meta_sync(int $post_id, array $item, bool $apply): bool
    {
        if (!$apply) {
            return true;
        }

        $expected_meta = mz_ptm_wxr_get_expected_meta_map($item, true);

        foreach (get_post_meta($post_id) as $key => $values) {
            $key = (string) $key;
            if ($key === '' || str_starts_with($key, '_mz_wxr_')) {
                continue;
            }

            delete_post_meta($post_id, $key);
        }

        foreach ($expected_meta as $key => $values) {
            foreach ((array) $values as $value) {
                add_post_meta($post_id, $key, $value);
            }
        }

        return true;
    }
}

if (!function_exists('mz_ptm_wxr_replace_attachment_media')) {
    function mz_ptm_wxr_replace_attachment_media(int $post_id, array $item, array $legacy_to_local_ids)
    {
        $attachment_url = trim((string) ($item['attachment_url'] ?? ''));
        if ($attachment_url === '') {
            return new WP_Error('mz_ptm_wxr_attachment_missing_url', sprintf('Attachment "%s" is missing an attachment URL.', (string) ($item['slug'] ?? '')));
        }

        $relative_path = mz_ptm_wxr_get_attachment_relative_path($item);
        if ($relative_path === '') {
            return new WP_Error('mz_ptm_wxr_attachment_missing_path', sprintf('Attachment "%s" is missing an attached file path.', (string) ($item['slug'] ?? '')));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return new WP_Error('mz_ptm_wxr_upload_dir_error', (string) $upload_dir['error']);
        }

        $absolute_path = trailingslashit((string) $upload_dir['basedir']) . ltrim($relative_path, '/');
        if (!wp_mkdir_p(dirname($absolute_path))) {
            return new WP_Error('mz_ptm_wxr_attachment_dir_failed', sprintf('Could not create upload directory for %s.', $relative_path));
        }

        $tmp_file = download_url($attachment_url, 300);
        if (is_wp_error($tmp_file)) {
            return $tmp_file;
        }

        $move_ok = true;

        if (file_exists($absolute_path) && !@unlink($absolute_path)) {
            $move_ok = false;
        }

        if ($move_ok && !@rename($tmp_file, $absolute_path)) {
            $move_ok = @copy($tmp_file, $absolute_path);
            @unlink($tmp_file);
        }

        if (!$move_ok) {
            @unlink($tmp_file);
            return new WP_Error('mz_ptm_wxr_attachment_move_failed', sprintf('Could not replace local media file for %s.', $relative_path));
        }

        $mime = wp_check_filetype($absolute_path);
        $parent_local_id = (int) ($legacy_to_local_ids[(int) ($item['parent_legacy_id'] ?? 0)] ?? 0);
        if ($parent_local_id <= 0) {
            $parent_local_id = mz_ptm_wxr_find_local_post_id_by_legacy_id((int) ($item['parent_legacy_id'] ?? 0));
        }

        $updated = wp_update_post([
            'ID' => $post_id,
            'post_mime_type' => (string) ($mime['type'] ?? ''),
            'post_parent' => $parent_local_id,
            'guid' => trailingslashit((string) $upload_dir['baseurl']) . ltrim($relative_path, '/'),
            'post_title' => (string) (($item['title'] ?? '') !== '' ? $item['title'] : basename($relative_path)),
            'post_status' => 'inherit',
            'post_name' => (string) ($item['slug'] ?? ''),
            'post_date' => (string) ($item['date'] ?? ''),
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ], true, false);

        if (is_wp_error($updated)) {
            return $updated;
        }

        update_attached_file($post_id, $relative_path);

        $metadata = wp_generate_attachment_metadata($post_id, $absolute_path);
        if (is_wp_error($metadata)) {
            return $metadata;
        }

        wp_update_attachment_metadata($post_id, $metadata);

        $alt_text = mz_ptm_wxr_get_attachment_alt_text($item);
        if ($alt_text !== '') {
            update_post_meta($post_id, '_wp_attachment_image_alt', $alt_text);
        }

        return true;
    }
}

if (!function_exists('mz_ptm_wxr_apply_page_parents')) {
    function mz_ptm_wxr_apply_page_parents(array $items, array $legacy_to_local_ids, bool $apply, array &$messages): int
    {
        $updated = 0;

        foreach ($items as $item) {
            if (($item['post_type'] ?? '') !== 'page') {
                continue;
            }

            $parent_legacy_id = (int) ($item['parent_legacy_id'] ?? 0);
            if ($parent_legacy_id <= 0) {
                continue;
            }

            $local_id = (int) ($legacy_to_local_ids[(int) ($item['legacy_id'] ?? 0)] ?? 0);
            $parent_local_id = (int) ($legacy_to_local_ids[$parent_legacy_id] ?? 0);

            if ($local_id <= 0 || $parent_local_id <= 0 || $local_id === $parent_local_id) {
                continue;
            }

            $existing_parent_id = (int) wp_get_post_parent_id($local_id);
            if ($existing_parent_id === $parent_local_id) {
                continue;
            }

            if (!$apply) {
                $updated++;
                continue;
            }

            $result = wp_update_post([
                'ID' => $local_id,
                'post_parent' => $parent_local_id,
            ], true);

            if (is_wp_error($result)) {
                $messages[] = sprintf(
                    'Failed setting page parent for "%s" (%d): %s',
                    (string) ($item['slug'] ?? ''),
                    $local_id,
                    $result->get_error_message()
                );
                continue;
            }

            $updated++;
        }

        return $updated;
    }
}

if (!function_exists('mz_ptm_wxr_run_import')) {
    function mz_ptm_wxr_run_import(array $args = []): array
    {
        $file_path = isset($args['file']) ? trim((string) $args['file']) : '';
        $apply = !empty($args['apply']);
        $types = mz_ptm_wxr_parse_types($args['types'] ?? null);

        $result = [
            'file' => $file_path,
            'apply' => $apply,
            'types' => $types,
            'scanned' => 0,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'errors' => 0,
            'page_parents_updated' => 0,
            'per_type' => [],
            'messages' => [],
        ];

        if ($file_path === '') {
            $result['messages'][] = 'A WXR file path is required.';
            $result['errors']++;
            return $result;
        }

        $items = mz_ptm_wxr_parse_file($file_path, $types);
        if (is_wp_error($items)) {
            $result['messages'][] = $items->get_error_message();
            $result['errors']++;
            return $result;
        }

        $result['scanned'] = count($items);
        $legacy_to_local_ids = [];

        foreach ($items as $item) {
            $post_type = (string) ($item['post_type'] ?? '');
            if (!isset($result['per_type'][$post_type])) {
                $result['per_type'][$post_type] = [
                    'scanned' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'unchanged' => 0,
                ];
            }
            $result['per_type'][$post_type]['scanned']++;

            $existing = mz_ptm_wxr_find_existing_post($item);
            $author_id = $existing instanceof WP_Post ? (int) $existing->post_author : mz_ptm_wxr_resolve_author_id((string) ($item['creator'] ?? ''));
            $payload = [
                'post_type' => $post_type,
                'post_title' => (string) $item['title'],
                'post_name' => (string) $item['slug'],
                'post_status' => mz_ptm_wxr_normalize_status((string) $item['status']),
                'post_content' => (string) $item['content'],
                'post_excerpt' => (string) $item['excerpt'],
                'post_date' => (string) $item['date'],
                'post_author' => $author_id,
                'menu_order' => (int) ($item['menu_order'] ?? 0),
                'comment_status' => (string) ($item['comment_status'] ?? 'closed'),
                'ping_status' => (string) ($item['ping_status'] ?? 'closed'),
                'edit_date' => true,
            ];

            $action = $existing instanceof WP_Post ? 'update' : 'create';
            if ($existing instanceof WP_Post) {
                $payload['ID'] = (int) $existing->ID;
            }

            $needs_term_sync = false;
            $needs_media_sync = $post_type === 'attachment' && !empty($item['attachment_url']);
            $needs_meta_sync = mz_ptm_wxr_should_sync_all_meta($post_type);
            $is_unchanged = false;

            if ($existing instanceof WP_Post) {
                $existing_snapshot = [
                    'post_title' => (string) $existing->post_title,
                    'post_name' => (string) $existing->post_name,
                    'post_status' => (string) $existing->post_status,
                    'post_content' => (string) $existing->post_content,
                    'post_excerpt' => (string) $existing->post_excerpt,
                    'post_date' => (string) $existing->post_date,
                    'menu_order' => (int) $existing->menu_order,
                    'comment_status' => (string) $existing->comment_status,
                    'ping_status' => (string) $existing->ping_status,
                ];

                $compare_payload = $payload;
                unset($compare_payload['post_type'], $compare_payload['post_author'], $compare_payload['edit_date'], $compare_payload['ID']);
                $is_unchanged = $existing_snapshot === $compare_payload && !$needs_media_sync;

                if ($is_unchanged && $needs_meta_sync) {
                    $expected_meta = mz_ptm_wxr_get_expected_meta_map($item, true);
                    $local_meta = mz_ptm_wxr_get_local_meta_map((int) $existing->ID, true);
                    $is_unchanged = $expected_meta === $local_meta;
                }

                if ($is_unchanged && !empty($item['terms'])) {
                    $expected_terms = mz_ptm_wxr_get_expected_term_slug_map((array) $item['terms']);
                    $local_terms = mz_ptm_wxr_get_local_term_slug_map((int) $existing->ID, (array) $item['terms']);
                    $needs_term_sync = $expected_terms !== $local_terms;
                    $is_unchanged = !$needs_term_sync;
                }
            }

            if ($is_unchanged) {
                $result['unchanged']++;
                $result['per_type'][$post_type]['unchanged']++;
                $legacy_to_local_ids[(int) $item['legacy_id']] = (int) $existing->ID;
                continue;
            }

            if (!$apply) {
                if ($action === 'create') {
                    $result['created']++;
                    $result['per_type'][$post_type]['created']++;
                } else {
                    $result['updated']++;
                    $result['per_type'][$post_type]['updated']++;
                    $legacy_to_local_ids[(int) $item['legacy_id']] = (int) $existing->ID;
                }

                continue;
            }

            if ($post_type === 'attachment' && $action === 'create') {
                $post_id = wp_insert_attachment($payload, false, 0, true, false);
            } else {
                $post_id = $action === 'create'
                    ? wp_insert_post($payload, true, false)
                    : wp_update_post($payload, true, false);
            }

            if (is_wp_error($post_id)) {
                $result['messages'][] = sprintf(
                    'Failed to %s %s "%s": %s',
                    $action,
                    (string) $item['post_type'],
                    (string) $item['slug'],
                    $post_id->get_error_message()
                );
                $result['errors']++;
                continue;
            }

            $post_id = (int) $post_id;
            $legacy_to_local_ids[(int) $item['legacy_id']] = $post_id;

            if ($post_type === 'attachment') {
                $media_result = mz_ptm_wxr_replace_attachment_media($post_id, $item, $legacy_to_local_ids);
                if (is_wp_error($media_result)) {
                    $result['messages'][] = sprintf(
                        'Failed to replace media for attachment "%s": %s',
                        (string) ($item['slug'] ?? ''),
                        $media_result->get_error_message()
                    );
                    $result['errors']++;
                    continue;
                }
            }

            if (!empty($item['terms']) && !mz_ptm_wxr_apply_terms_to_post($post_id, (array) $item['terms'], true, $result['messages'])) {
                $result['errors']++;
                continue;
            }

            if ($needs_meta_sync && !mz_ptm_wxr_apply_full_meta_sync($post_id, $item, true)) {
                $result['messages'][] = sprintf(
                    'Failed to sync full meta for %s "%s".',
                    $post_type,
                    (string) ($item['slug'] ?? '')
                );
                $result['errors']++;
                continue;
            }

            mz_ptm_wxr_apply_post_meta($post_id, $item, true);

            if ($action === 'create') {
                $result['created']++;
                $result['per_type'][$post_type]['created']++;
            } else {
                $result['updated']++;
                $result['per_type'][$post_type]['updated']++;
            }
        }

        $result['page_parents_updated'] = mz_ptm_wxr_apply_page_parents($items, $legacy_to_local_ids, $apply, $result['messages']);

        return $result;
    }
}

if (!function_exists('mz_ptm_media_repair_parse_attachment_export')) {
    function mz_ptm_media_repair_parse_attachment_export(string $file_path)
    {
        $file_path = mz_ptm_resolve_project_root_file_path($file_path);
        if ($file_path === '' || !is_readable($file_path)) {
            return new WP_Error('mz_ptm_media_repair_unreadable_file', sprintf('The export file "%s" is not readable.', $file_path));
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($file_path, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml instanceof SimpleXMLElement) {
            libxml_clear_errors();
            return new WP_Error('mz_ptm_media_repair_invalid_xml', sprintf('The export file "%s" could not be parsed.', $file_path));
        }

        $items = [];
        foreach ($xml->channel->item as $item) {
            $wp = $item->children('wp', true);
            if ((string) $wp->post_type !== 'attachment') {
                continue;
            }

            $relative_path = mz_ptm_wxr_get_attachment_relative_path([
                'meta' => mz_ptm_wxr_parse_post_meta($item, 'wp'),
                'attachment_url' => (string) $wp->attachment_url,
            ]);

            $items[] = [
                'legacy_id' => (int) $wp->post_id,
                'parent_legacy_id' => (int) $wp->post_parent,
                'slug' => (string) $wp->post_name,
                'title' => (string) $item->title,
                'attachment_url' => (string) $wp->attachment_url,
                'relative_path' => $relative_path,
            ];
        }

        return $items;
    }
}

if (!function_exists('mz_ptm_media_repair_parse_content_featured_images')) {
    function mz_ptm_media_repair_parse_content_featured_images(string $file_path, array $allowed_post_types = [])
    {
        $file_path = mz_ptm_resolve_project_root_file_path($file_path);
        if ($file_path === '' || !is_readable($file_path)) {
            return new WP_Error('mz_ptm_media_repair_unreadable_content_file', sprintf('The content export file "%s" is not readable.', $file_path));
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($file_path, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml instanceof SimpleXMLElement) {
            libxml_clear_errors();
            return new WP_Error('mz_ptm_media_repair_invalid_content_xml', sprintf('The content export file "%s" could not be parsed.', $file_path));
        }

        $items = [];
        foreach ($xml->channel->item as $item) {
            $wp = $item->children('wp', true);
            $post_type = (string) $wp->post_type;
            if ($post_type === '' || $post_type === 'attachment') {
                continue;
            }

            $normalized_post_type = sanitize_key($post_type);
            if ($allowed_post_types !== [] && !in_array($normalized_post_type, $allowed_post_types, true)) {
                continue;
            }

            $thumbnail_id = 0;
            foreach ($wp->postmeta as $postmeta) {
                if ((string) $postmeta->meta_key === '_thumbnail_id') {
                    $thumbnail_id = (int) $postmeta->meta_value;
                    break;
                }
            }

            if ($thumbnail_id <= 0) {
                continue;
            }

            $items[] = [
                'legacy_id' => (int) $wp->post_id,
                'post_type' => $normalized_post_type,
                'slug' => sanitize_title((string) $wp->post_name),
                'thumbnail_legacy_id' => $thumbnail_id,
            ];
        }

        return $items;
    }
}

if (!function_exists('mz_ptm_media_repair_index_export_items')) {
    function mz_ptm_media_repair_index_export_items(array $items): array
    {
        $index = [
            'by_id' => [],
            'by_slug' => [],
            'by_path' => [],
        ];

        foreach ($items as $item) {
            $legacy_id = (int) ($item['legacy_id'] ?? 0);
            $slug = (string) ($item['slug'] ?? '');
            $path = (string) ($item['relative_path'] ?? '');

            if ($legacy_id > 0) {
                $index['by_id'][$legacy_id] = $item;
            }
            if ($slug !== '') {
                $index['by_slug'][$slug] = $item;
            }
            if ($path !== '') {
                $index['by_path'][$path] = $item;
            }
        }

        return $index;
    }
}

if (!function_exists('mz_ptm_media_repair_find_matching_item')) {
    function mz_ptm_media_repair_find_matching_item(array $production_item, array $staging_index): ?array
    {
        $path = (string) ($production_item['relative_path'] ?? '');
        if ($path !== '' && !empty($staging_index['by_path'][$path])) {
            return (array) $staging_index['by_path'][$path];
        }

        $slug = (string) ($production_item['slug'] ?? '');
        if ($slug !== '' && !empty($staging_index['by_slug'][$slug])) {
            return (array) $staging_index['by_slug'][$slug];
        }

        return null;
    }
}

if (!function_exists('mz_ptm_media_repair_find_local_attachment_id')) {
    function mz_ptm_media_repair_find_local_attachment_id(array $staging_item): int
    {
        $staging_id = (int) ($staging_item['legacy_id'] ?? 0);
        if ($staging_id > 0) {
            $post = get_post($staging_id);
            if ($post instanceof WP_Post && $post->post_type === 'attachment') {
                return (int) $post->ID;
            }
        }

        $path = (string) ($staging_item['relative_path'] ?? '');
        if ($path !== '') {
            $ids = get_posts([
                'post_type' => 'attachment',
                'post_status' => 'any',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_key' => '_wp_attached_file',
                'meta_value' => $path,
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'suppress_filters' => true,
            ]);

            if (!empty($ids[0])) {
                return (int) $ids[0];
            }
        }

        $slug = (string) ($staging_item['slug'] ?? '');
        if ($slug !== '') {
            $ids = get_posts([
                'name' => $slug,
                'post_type' => 'attachment',
                'post_status' => 'any',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'suppress_filters' => true,
            ]);

            if (!empty($ids[0])) {
                return (int) $ids[0];
            }
        }

        return 0;
    }
}

if (!function_exists('mz_ptm_media_repair_resolve_local_parent_id')) {
    function mz_ptm_media_repair_resolve_local_parent_id(int $production_parent_id): int
    {
        if ($production_parent_id <= 0) {
            return 0;
        }

        $local_id = mz_ptm_wxr_find_local_post_id_by_legacy_id($production_parent_id);
        if ($local_id > 0) {
            return $local_id;
        }

        $post = get_post($production_parent_id);
        if ($post instanceof WP_Post && $post->post_type !== 'attachment') {
            return (int) $post->ID;
        }

        return 0;
    }
}

if (!function_exists('mz_ptm_media_repair_find_local_post_id')) {
    function mz_ptm_media_repair_find_local_post_id(array $item): int
    {
        $legacy_id = (int) ($item['legacy_id'] ?? 0);
        if ($legacy_id > 0) {
            $local_id = mz_ptm_wxr_find_local_post_id_by_legacy_id($legacy_id);
            if ($local_id > 0) {
                return $local_id;
            }

            $post = get_post($legacy_id);
            if ($post instanceof WP_Post && $post->post_type === (string) ($item['post_type'] ?? '')) {
                return (int) $post->ID;
            }
        }

        $slug = (string) ($item['slug'] ?? '');
        $post_type = (string) ($item['post_type'] ?? '');
        if ($slug === '' || $post_type === '') {
            return 0;
        }

        $posts = get_posts([
            'name' => $slug,
            'post_type' => $post_type,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'suppress_filters' => true,
        ]);

        return !empty($posts[0]) ? (int) $posts[0] : 0;
    }
}

if (!function_exists('mz_ptm_media_repair_replace_string')) {
    function mz_ptm_media_repair_replace_string(string $value, array $id_map, bool &$changed): string
    {
        if ($value === '') {
            return $value;
        }

        if (ctype_digit($value)) {
            $int_value = (int) $value;
            if (isset($id_map[$int_value])) {
                $changed = true;
                return (string) $id_map[$int_value];
            }

            return $value;
        }

        if (preg_match('/^\d+(,\d+)+$/', $value)) {
            $parts = array_map('intval', explode(',', $value));
            $replaced = [];
            $local_changed = false;
            foreach ($parts as $part) {
                if (isset($id_map[$part])) {
                    $replaced[] = (string) $id_map[$part];
                    $local_changed = true;
                } else {
                    $replaced[] = (string) $part;
                }
            }

            if ($local_changed) {
                $changed = true;
                return implode(',', $replaced);
            }
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $decoded_changed = false;
            $decoded = mz_ptm_media_repair_replace_data($decoded, $id_map, $decoded_changed);
            if ($decoded_changed) {
                $changed = true;
                return wp_json_encode($decoded);
            }
        }

        $updated = preg_replace_callback('/wp-image-(\d+)/', static function (array $matches) use ($id_map, &$changed): string {
            $old_id = (int) ($matches[1] ?? 0);
            if ($old_id > 0 && isset($id_map[$old_id])) {
                $changed = true;
                return 'wp-image-' . (int) $id_map[$old_id];
            }

            return (string) $matches[0];
        }, $value);

        $updated = preg_replace_callback('/("id"\s*:\s*)(\d+)/', static function (array $matches) use ($id_map, &$changed): string {
            $old_id = (int) ($matches[2] ?? 0);
            if ($old_id > 0 && isset($id_map[$old_id])) {
                $changed = true;
                return (string) $matches[1] . (int) $id_map[$old_id];
            }

            return (string) $matches[0];
        }, (string) $updated);

        $updated = preg_replace_callback('/(ids=)(["\'])([\d,]+)(\2)/', static function (array $matches) use ($id_map, &$changed): string {
            $parts = array_map('intval', explode(',', (string) ($matches[3] ?? '')));
            $local_changed = false;
            foreach ($parts as &$part) {
                if (isset($id_map[$part])) {
                    $part = (int) $id_map[$part];
                    $local_changed = true;
                }
            }
            unset($part);

            if ($local_changed) {
                $changed = true;
                return (string) $matches[1] . (string) $matches[2] . implode(',', $parts) . (string) $matches[4];
            }

            return (string) $matches[0];
        }, (string) $updated);

        return (string) $updated;
    }
}

if (!function_exists('mz_ptm_media_repair_replace_data')) {
    function mz_ptm_media_repair_replace_data($value, array $id_map, bool &$changed)
    {
        if (is_int($value)) {
            if (isset($id_map[$value])) {
                $changed = true;
                return (int) $id_map[$value];
            }

            return $value;
        }

        if (is_string($value)) {
            return mz_ptm_media_repair_replace_string($value, $id_map, $changed);
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = mz_ptm_media_repair_replace_data($item, $id_map, $changed);
            }

            return $value;
        }

        if (is_object($value)) {
            foreach ($value as $key => $item) {
                $value->{$key} = mz_ptm_media_repair_replace_data($item, $id_map, $changed);
            }

            return $value;
        }

        return $value;
    }
}

if (!function_exists('mz_ptm_media_repair_prepare_db_value')) {
    function mz_ptm_media_repair_prepare_db_value($value): string
    {
        if (is_array($value) || is_object($value)) {
            return maybe_serialize($value);
        }

        return (string) $value;
    }
}

if (!function_exists('mz_ptm_media_repair_update_metadata_table')) {
    function mz_ptm_media_repair_update_metadata_table(string $table, string $id_column, string $value_column, array $id_map, bool $apply): array
    {
        global $wpdb;

        $updated = 0;
        $scanned = 0;
        $rows = $wpdb->get_results("SELECT {$id_column} AS row_id, {$value_column} AS raw_value FROM {$table}", ARRAY_A);

        foreach ((array) $rows as $row) {
            $scanned++;
            $raw_value = isset($row['raw_value']) ? (string) $row['raw_value'] : '';
            $value = is_serialized($raw_value) ? maybe_unserialize($raw_value) : $raw_value;
            $changed = false;
            $updated_value = mz_ptm_media_repair_replace_data($value, $id_map, $changed);

            if (!$changed) {
                continue;
            }

            $updated++;
            if (!$apply) {
                continue;
            }

            $wpdb->update(
                $table,
                [$value_column => mz_ptm_media_repair_prepare_db_value($updated_value)],
                [$id_column => (int) $row['row_id']],
                ['%s'],
                ['%d']
            );
        }

        return [
            'scanned' => $scanned,
            'updated' => $updated,
        ];
    }
}

if (!function_exists('mz_ptm_media_repair_update_posts_table')) {
    function mz_ptm_media_repair_update_posts_table(array $id_map, bool $apply): array
    {
        global $wpdb;

        $updated = 0;
        $scanned = 0;
        $rows = $wpdb->get_results("SELECT ID, post_content FROM {$wpdb->posts}", ARRAY_A);

        foreach ((array) $rows as $row) {
            $scanned++;
            $raw_value = isset($row['post_content']) ? (string) $row['post_content'] : '';
            $changed = false;
            $updated_value = mz_ptm_media_repair_replace_string($raw_value, $id_map, $changed);

            if (!$changed) {
                continue;
            }

            $updated++;
            if (!$apply) {
                continue;
            }

            $wpdb->update(
                $wpdb->posts,
                ['post_content' => $updated_value],
                ['ID' => (int) $row['ID']],
                ['%s'],
                ['%d']
            );
        }

        return [
            'scanned' => $scanned,
            'updated' => $updated,
        ];
    }
}

if (!function_exists('mz_ptm_media_repair_apply_featured_images')) {
    function mz_ptm_media_repair_apply_featured_images(array $content_items, array $attachment_id_map, bool $apply): array
    {
        $updated = 0;
        $missing_posts = 0;
        $missing_attachments = 0;
        $messages = [];

        foreach ($content_items as $item) {
            $local_post_id = mz_ptm_media_repair_find_local_post_id($item);
            if ($local_post_id <= 0) {
                $missing_posts++;
                continue;
            }

            $production_thumbnail_id = (int) ($item['thumbnail_legacy_id'] ?? 0);
            $local_thumbnail_id = (int) ($attachment_id_map[$production_thumbnail_id] ?? 0);
            if ($local_thumbnail_id <= 0) {
                $missing_attachments++;
                $messages[] = sprintf(
                    'Could not resolve local attachment for featured image %d on %s "%s".',
                    $production_thumbnail_id,
                    (string) ($item['post_type'] ?? ''),
                    (string) ($item['slug'] ?? '')
                );
                continue;
            }

            $existing_thumbnail_id = (int) get_post_meta($local_post_id, '_thumbnail_id', true);
            if ($existing_thumbnail_id === $local_thumbnail_id) {
                continue;
            }

            $updated++;
            if (!$apply) {
                continue;
            }

            update_post_meta($local_post_id, '_thumbnail_id', $local_thumbnail_id);
        }

        return [
            'updated' => $updated,
            'missing_posts' => $missing_posts,
            'missing_attachments' => $missing_attachments,
            'messages' => $messages,
        ];
    }
}

if (!function_exists('mz_ptm_media_repair_run')) {
    function mz_ptm_media_repair_run(array $args = []): array
    {
        $staging_file = isset($args['staging_file']) ? trim((string) $args['staging_file']) : '';
        $production_file = isset($args['production_file']) ? trim((string) $args['production_file']) : '';
        $content_file = isset($args['content_file']) ? trim((string) $args['content_file']) : '';
        $post_types = mz_ptm_parse_csv_slugs($args['post_types'] ?? null);
        $apply = !empty($args['apply']);

        $result = [
            'staging_file' => mz_ptm_resolve_project_root_file_path($staging_file),
            'production_file' => mz_ptm_resolve_project_root_file_path($production_file),
            'content_file' => mz_ptm_resolve_project_root_file_path($content_file),
            'post_types' => $post_types,
            'apply' => $apply,
            'production_attachments' => 0,
            'staging_attachments' => 0,
            'matched_attachments' => 0,
            'skipped_staging_only' => 0,
            'missing_in_staging' => 0,
            'missing_local_attachments' => 0,
            'id_mappings' => 0,
            'parents_updated' => 0,
            'postmeta_updated' => 0,
            'options_updated' => 0,
            'post_content_updated' => 0,
            'featured_images_updated' => 0,
            'featured_image_posts_missing' => 0,
            'featured_image_attachments_missing' => 0,
            'errors' => 0,
            'messages' => [],
        ];

        $staging_items = mz_ptm_media_repair_parse_attachment_export($result['staging_file']);
        if (is_wp_error($staging_items)) {
            $result['messages'][] = $staging_items->get_error_message();
            $result['errors']++;
            return $result;
        }

        $production_items = mz_ptm_media_repair_parse_attachment_export($result['production_file']);
        if (is_wp_error($production_items)) {
            $result['messages'][] = $production_items->get_error_message();
            $result['errors']++;
            return $result;
        }

        $staging_index = mz_ptm_media_repair_index_export_items($staging_items);
        $production_index = mz_ptm_media_repair_index_export_items($production_items);
        $result['staging_attachments'] = count($staging_items);
        $result['production_attachments'] = count($production_items);
        $result['skipped_staging_only'] = max(0, count($staging_items) - count(array_intersect_key($staging_index['by_slug'], $production_index['by_slug'])));

        $production_to_local_id_map = [];
        $parent_targets = [];

        foreach ($production_items as $production_item) {
            $staging_item = mz_ptm_media_repair_find_matching_item($production_item, $staging_index);
            if ($staging_item === null) {
                $result['missing_in_staging']++;
                $result['messages'][] = sprintf(
                    'Production attachment missing from staging export: %s',
                    (string) ($production_item['relative_path'] ?: $production_item['slug'] ?: $production_item['legacy_id'])
                );
                continue;
            }

            $local_attachment_id = mz_ptm_media_repair_find_local_attachment_id($staging_item);
            if ($local_attachment_id <= 0) {
                $result['missing_local_attachments']++;
                $result['messages'][] = sprintf(
                    'Could not find a local attachment for staging item %s.',
                    (string) ($staging_item['relative_path'] ?: $staging_item['slug'] ?: $staging_item['legacy_id'])
                );
                continue;
            }

            $result['matched_attachments']++;

            $production_attachment_id = (int) ($production_item['legacy_id'] ?? 0);
            if ($production_attachment_id > 0 && !isset($production_to_local_id_map[$production_attachment_id])) {
                $production_to_local_id_map[$production_attachment_id] = $local_attachment_id;
                if ($production_attachment_id !== $local_attachment_id) {
                    $result['id_mappings']++;
                }
            }

            $parent_targets[$local_attachment_id] = mz_ptm_media_repair_resolve_local_parent_id((int) ($production_item['parent_legacy_id'] ?? 0));
        }

        foreach ($parent_targets as $attachment_id => $parent_id) {
            $current_parent_id = (int) wp_get_post_parent_id($attachment_id);
            if ($current_parent_id === $parent_id) {
                continue;
            }

            $result['parents_updated']++;
            if (!$apply) {
                continue;
            }

            $update_result = wp_update_post([
                'ID' => $attachment_id,
                'post_parent' => $parent_id,
            ], true, false);

            if (is_wp_error($update_result)) {
                $result['errors']++;
                $result['messages'][] = sprintf(
                    'Failed to update attachment parent for %d: %s',
                    $attachment_id,
                    $update_result->get_error_message()
                );
            }
        }

        $postmeta_result = mz_ptm_media_repair_update_metadata_table($GLOBALS['wpdb']->postmeta, 'meta_id', 'meta_value', $production_to_local_id_map, $apply);
        $result['postmeta_updated'] = (int) ($postmeta_result['updated'] ?? 0);

        $options_result = mz_ptm_media_repair_update_metadata_table($GLOBALS['wpdb']->options, 'option_id', 'option_value', $production_to_local_id_map, $apply);
        $result['options_updated'] = (int) ($options_result['updated'] ?? 0);

        $posts_result = mz_ptm_media_repair_update_posts_table($production_to_local_id_map, $apply);
        $result['post_content_updated'] = (int) ($posts_result['updated'] ?? 0);

        if ($result['content_file'] !== '') {
            $content_items = mz_ptm_media_repair_parse_content_featured_images($result['content_file'], $post_types);
            if (is_wp_error($content_items)) {
                $result['messages'][] = $content_items->get_error_message();
                $result['errors']++;
                return $result;
            }

            $featured_result = mz_ptm_media_repair_apply_featured_images((array) $content_items, $production_to_local_id_map, $apply);
            $result['featured_images_updated'] = (int) ($featured_result['updated'] ?? 0);
            $result['featured_image_posts_missing'] = (int) ($featured_result['missing_posts'] ?? 0);
            $result['featured_image_attachments_missing'] = (int) ($featured_result['missing_attachments'] ?? 0);
            $result['messages'] = array_merge($result['messages'], (array) ($featured_result['messages'] ?? []));
        }

        return $result;
    }
}

if (!function_exists('mz_ptm_repair_sign_customer_relationships_run')) {
    function mz_ptm_repair_sign_customer_relationships_run(array $args = []): array
    {
        $file_path = mz_ptm_resolve_project_root_file_path((string) ($args['file'] ?? ''));
        $apply = !empty($args['apply']);

        $result = [
            'file' => $file_path,
            'apply' => $apply,
            'scanned' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped_missing_customer_map' => 0,
            'missing_signs' => 0,
            'missing_customers' => 0,
            'errors' => 0,
            'messages' => [],
        ];

        $items = mz_ptm_wxr_parse_file($file_path, ['sign']);
        if (is_wp_error($items)) {
            $result['errors']++;
            $result['messages'][] = $items->get_error_message();
            return $result;
        }

        foreach ((array) $items as $item) {
            $result['scanned']++;

            $local_sign = mz_ptm_wxr_find_existing_post((array) $item);
            if (!($local_sign instanceof WP_Post) || $local_sign->post_type !== 'sign') {
                $result['missing_signs']++;
                $result['messages'][] = sprintf(
                    'Could not find local sign for "%s".',
                    (string) ($item['slug'] ?? '')
                );
                continue;
            }

            $meta_values = (array) (($item['meta']['customer'] ?? []));
            $expected_customer_ids = [];
            foreach ($meta_values as $meta_value) {
                $expected_customer_ids = array_merge($expected_customer_ids, mz_ptm_wxr_parse_relationship_meta_value($meta_value));
            }
            $expected_customer_ids = array_values(array_unique(array_filter(array_map('intval', $expected_customer_ids))));

            $local_customer_ids = [];
            foreach ($expected_customer_ids as $legacy_customer_id) {
                $local_customer_id = mz_ptm_wxr_find_local_post_id_by_legacy_id((int) $legacy_customer_id);
                if ($local_customer_id <= 0) {
                    $result['missing_customers']++;
                    $result['messages'][] = sprintf('Could not map production customer %d for sign "%s".', (int) $legacy_customer_id, (string) ($item['slug'] ?? ''));
                    continue;
                }

                $local_customer_ids[] = (int) $local_customer_id;
            }
            $local_customer_ids = array_values(array_unique(array_filter($local_customer_ids)));
            $expected_field_key_values = (array) (($item['meta']['_customer'] ?? []));
            $expected_field_key = trim((string) ($expected_field_key_values[0] ?? ''));

            if (count($local_customer_ids) !== count($expected_customer_ids)) {
                $result['skipped_missing_customer_map']++;
                $result['messages'][] = sprintf(
                    'Skipped sign "%s" because one or more customer relationships could not be mapped safely.',
                    (string) ($item['slug'] ?? '')
                );
                continue;
            }

            $existing_customer_ids = mz_ptm_wxr_parse_relationship_meta_value(get_post_meta((int) $local_sign->ID, 'customer', true));
            $existing_field_key = trim((string) get_post_meta((int) $local_sign->ID, '_customer', true));
            sort($existing_customer_ids);
            $comparison_customer_ids = $local_customer_ids;
            sort($comparison_customer_ids);

            if ($existing_customer_ids === $comparison_customer_ids && $existing_field_key === $expected_field_key) {
                $result['unchanged']++;
                continue;
            }

            $result['updated']++;
            if (!$apply) {
                $result['messages'][] = sprintf(
                    'Would update sign "%s" customer relationship to [%s].',
                    (string) ($item['slug'] ?? ''),
                    implode(', ', $comparison_customer_ids)
                );
                continue;
            }

            delete_post_meta((int) $local_sign->ID, 'customer');
            delete_post_meta((int) $local_sign->ID, '_customer');

            if ($local_customer_ids === []) {
                $result['messages'][] = sprintf(
                    'Cleared customer relationship for sign "%s".',
                    (string) ($item['slug'] ?? '')
                );
                continue;
            }

            $store_value = count($local_customer_ids) === 1 ? (int) $local_customer_ids[0] : $local_customer_ids;
            update_post_meta((int) $local_sign->ID, 'customer', $store_value);

            if ($expected_field_key !== '') {
                update_post_meta((int) $local_sign->ID, '_customer', $expected_field_key);
            }

            $result['messages'][] = sprintf(
                'Updated sign "%s" customer relationship to [%s].',
                (string) ($item['slug'] ?? ''),
                implode(', ', $local_customer_ids)
            );
        }

        return $result;
    }
}

if (!function_exists('mz_ptm_attach_featured_media_run')) {
    function mz_ptm_attach_featured_media_run(array $args = []): array
    {
        $post_types = mz_ptm_parse_csv_slugs($args['post_types'] ?? null);
        $apply = !empty($args['apply']);

        if ($post_types === []) {
            $post_types = array_values(array_filter(get_post_types(['public' => true], 'names'), static function ($post_type): bool {
                return !in_array($post_type, ['attachment', 'revision', 'nav_menu_item'], true);
            }));
        }

        $query = new WP_Query([
            'post_type' => $post_types,
            'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'suppress_filters' => true,
            'meta_query' => [
                [
                    'key' => '_thumbnail_id',
                    'compare' => 'EXISTS',
                ],
            ],
        ]);

        $result = [
            'operation' => 'attach-featured-media',
            'post_types' => $post_types,
            'apply' => $apply,
            'posts_scanned' => 0,
            'attachments_updated' => 0,
            'shared_attachments_assigned' => 0,
            'shared_attachments_skipped' => 0,
            'missing_thumbnails' => 0,
            'missing_attachments' => 0,
            'errors' => 0,
            'messages' => [],
        ];

        $thumbnail_usage = [];
        foreach ((array) $query->posts as $post_id) {
            $thumbnail_id = (int) get_post_meta((int) $post_id, '_thumbnail_id', true);
            if ($thumbnail_id > 0) {
                if (!isset($thumbnail_usage[$thumbnail_id])) {
                    $thumbnail_usage[$thumbnail_id] = [];
                }
                $thumbnail_usage[$thumbnail_id][] = (int) $post_id;
            }
        }

        foreach ((array) $query->posts as $post_id) {
            $result['posts_scanned']++;
            $thumbnail_id = (int) get_post_meta((int) $post_id, '_thumbnail_id', true);
            if ($thumbnail_id <= 0) {
                $result['missing_thumbnails']++;
                continue;
            }

            $attachment = get_post($thumbnail_id);
            if (!($attachment instanceof WP_Post) || $attachment->post_type !== 'attachment') {
                $result['missing_attachments']++;
                $result['messages'][] = sprintf(
                    'Post %d has missing featured attachment %d.',
                    (int) $post_id,
                    $thumbnail_id
                );
                continue;
            }

            if (!empty($thumbnail_usage[$thumbnail_id]) && count((array) $thumbnail_usage[$thumbnail_id]) > 1) {
                $shared_post_ids = array_map('intval', (array) $thumbnail_usage[$thumbnail_id]);
                $canonical_post_id = mz_ptm_get_earliest_post_id($shared_post_ids);

                if ($canonical_post_id !== (int) $post_id) {
                    $result['shared_attachments_skipped']++;
                    $result['messages'][] = sprintf(
                        'Skipped shared featured media %d for post %d because it is assigned to earliest post %d in group: %s.',
                        $thumbnail_id,
                        (int) $post_id,
                        $canonical_post_id,
                        implode(', ', $shared_post_ids)
                    );
                    continue;
                }

                $result['shared_attachments_assigned']++;
            }

            if ((int) $attachment->post_parent === (int) $post_id) {
                continue;
            }

            $result['attachments_updated']++;
            if (!$apply) {
                $result['messages'][] = sprintf(
                    'Would attach media %d to post %d.',
                    $thumbnail_id,
                    (int) $post_id
                );
                continue;
            }

            $updated = wp_update_post([
                'ID' => $thumbnail_id,
                'post_parent' => (int) $post_id,
            ], true, false);

            if (is_wp_error($updated)) {
                $result['errors']++;
                $result['messages'][] = sprintf(
                    'Failed to attach media %d to post %d: %s',
                    $thumbnail_id,
                    (int) $post_id,
                    $updated->get_error_message()
                );
                continue;
            }

            $result['messages'][] = sprintf(
                'Attached media %d to post %d.',
                $thumbnail_id,
                (int) $post_id
            );
        }

        return $result;
    }
}

if (defined('WP_CLI') && WP_CLI && !class_exists('MZ_WXR_Content_Import_CLI_Command')) {
    class MZ_WXR_Content_Import_CLI_Command
    {
        /**
         * Upsert selected content types from a WordPress WXR export into the local site.
         *
         * Attachment imports replace matching local media files so production
         * media can override local media without creating duplicate attachments.
         *
         * ## OPTIONS
         *
         * --file=<file>
         * : Absolute path to the WXR/XML file.
         *
         * [--types=<types>]
         * : Comma-separated post types to import. Supported: post,page,sign,customer,attachment,form,mzf_submission
         *
         * [--apply]
         * : Perform writes. Omit for a dry run.
         *
         * ## EXAMPLES
         *
         *     wp mz wxr-import-content --file=/path/export.xml
         *     wp mz wxr-import-content --file=/path/export.xml --types=post,page --apply
         *
         * @when after_wp_load
         */
        public function __invoke($args, $assoc_args): void
        {
            if (empty($assoc_args['file'])) {
                \WP_CLI::error('The --file argument is required.');
            }

            $result = mz_ptm_wxr_run_import([
                'file' => (string) $assoc_args['file'],
                'types' => $assoc_args['types'] ?? null,
                'apply' => isset($assoc_args['apply']),
            ]);

            foreach ((array) $result['messages'] as $message) {
                \WP_CLI::log((string) $message);
            }

            \WP_CLI::log('');
            \WP_CLI::log('File: ' . (string) ($result['file'] ?? ''));
            \WP_CLI::log('Types: ' . implode(', ', (array) ($result['types'] ?? [])));
            \WP_CLI::log('Mode: ' . (!empty($result['apply']) ? 'live run' : 'dry run'));
            \WP_CLI::log('Scanned: ' . (int) ($result['scanned'] ?? 0));
            \WP_CLI::log('Create actions: ' . (int) ($result['created'] ?? 0));
            \WP_CLI::log('Update actions: ' . (int) ($result['updated'] ?? 0));
            \WP_CLI::log('Unchanged: ' . (int) ($result['unchanged'] ?? 0));
            \WP_CLI::log('Page parents synced: ' . (int) ($result['page_parents_updated'] ?? 0));
            \WP_CLI::log('Errors: ' . (int) ($result['errors'] ?? 0));
            foreach ((array) ($result['per_type'] ?? []) as $post_type => $summary) {
                \WP_CLI::log(sprintf(
                    'Type %s: scanned=%d created=%d updated=%d unchanged=%d',
                    (string) $post_type,
                    (int) ($summary['scanned'] ?? 0),
                    (int) ($summary['created'] ?? 0),
                    (int) ($summary['updated'] ?? 0),
                    (int) ($summary['unchanged'] ?? 0)
                ));
            }

            if (!empty($result['errors'])) {
                \WP_CLI::halt(1);
            }

            \WP_CLI::success(!empty($result['apply']) ? 'Import complete.' : 'Dry run complete.');
        }
    }

    \WP_CLI::add_command('mz wxr-import-content', 'MZ_WXR_Content_Import_CLI_Command');
}

if (defined('WP_CLI') && WP_CLI && !class_exists('MZ_Media_Repair_CLI_Command')) {
    class MZ_Media_Repair_CLI_Command
    {
        /**
         * Repair media references using production and staging attachment exports.
         *
         * Production is treated as the source of truth. Staging-only attachments
         * are ignored.
         *
         * ## OPTIONS
         *
         * --staging-file=<file>
         * : Absolute path to the staging attachment export XML.
         *
         * --production-file=<file>
         * : Absolute path to the production attachment export XML.
         *
         * [--content-file=<file>]
         * : Optional production content export XML used to restore featured images.
         *
         * [--post-types=<types>]
         * : Optional comma-separated post types to limit featured-image restoration.
         *
         * [--apply]
         * : Perform writes. Omit for a dry run.
         *
         * ## EXAMPLES
         *
         *     wp mz repair-media-links --staging-file=/path/staging.xml --production-file=/path/production.xml
         *     wp mz repair-media-links --staging-file=/path/staging.xml --production-file=/path/production.xml --content-file=/path/content.xml --post-types=post,page --apply
         *
         * @when after_wp_load
         */
        public function __invoke($args, $assoc_args): void
        {
            if (empty($assoc_args['staging-file']) || empty($assoc_args['production-file'])) {
                \WP_CLI::error('The --staging-file and --production-file arguments are required.');
            }

            $result = mz_ptm_media_repair_run([
                'staging_file' => (string) $assoc_args['staging-file'],
                'production_file' => (string) $assoc_args['production-file'],
                'content_file' => $assoc_args['content-file'] ?? '',
                'post_types' => $assoc_args['post-types'] ?? '',
                'apply' => isset($assoc_args['apply']),
            ]);

            foreach ((array) $result['messages'] as $message) {
                \WP_CLI::log((string) $message);
            }

            \WP_CLI::log('');
            \WP_CLI::log('Staging file: ' . (string) ($result['staging_file'] ?? ''));
            \WP_CLI::log('Production file: ' . (string) ($result['production_file'] ?? ''));
            \WP_CLI::log('Content file: ' . (string) ($result['content_file'] ?? ''));
            \WP_CLI::log('Content post types: ' . implode(', ', (array) ($result['post_types'] ?? [])));
            \WP_CLI::log('Mode: ' . (!empty($result['apply']) ? 'live run' : 'dry run'));
            \WP_CLI::log('Production attachments: ' . (int) ($result['production_attachments'] ?? 0));
            \WP_CLI::log('Staging attachments: ' . (int) ($result['staging_attachments'] ?? 0));
            \WP_CLI::log('Matched attachments: ' . (int) ($result['matched_attachments'] ?? 0));
            \WP_CLI::log('Missing in staging export: ' . (int) ($result['missing_in_staging'] ?? 0));
            \WP_CLI::log('Missing local attachments: ' . (int) ($result['missing_local_attachments'] ?? 0));
            \WP_CLI::log('Staging-only attachments skipped: ' . (int) ($result['skipped_staging_only'] ?? 0));
            \WP_CLI::log('ID remaps: ' . (int) ($result['id_mappings'] ?? 0));
            \WP_CLI::log('Attachment parents updated: ' . (int) ($result['parents_updated'] ?? 0));
            \WP_CLI::log('Postmeta rows updated: ' . (int) ($result['postmeta_updated'] ?? 0));
            \WP_CLI::log('Option rows updated: ' . (int) ($result['options_updated'] ?? 0));
            \WP_CLI::log('Post content rows updated: ' . (int) ($result['post_content_updated'] ?? 0));
            \WP_CLI::log('Featured images updated: ' . (int) ($result['featured_images_updated'] ?? 0));
            \WP_CLI::log('Featured image posts missing: ' . (int) ($result['featured_image_posts_missing'] ?? 0));
            \WP_CLI::log('Featured image attachments missing: ' . (int) ($result['featured_image_attachments_missing'] ?? 0));
            \WP_CLI::log('Errors: ' . (int) ($result['errors'] ?? 0));

            if (!empty($result['errors'])) {
                \WP_CLI::halt(1);
            }

            \WP_CLI::success(!empty($result['apply']) ? 'Media repair complete.' : 'Media repair dry run complete.');
        }
    }

    \WP_CLI::add_command('mz repair-media-links', 'MZ_Media_Repair_CLI_Command');
}

if (defined('WP_CLI') && WP_CLI && !class_exists('MZ_Attach_Featured_Media_CLI_Command')) {
    class MZ_Attach_Featured_Media_CLI_Command
    {
        /**
         * Attach each post's featured image attachment to that post.
         *
         * ## OPTIONS
         *
         * [--post-types=<types>]
         * : Optional comma-separated post types. Leave blank to scan all public post types with featured images.
         *
         * [--apply]
         * : Perform writes. Omit for a dry run.
         *
         * ## EXAMPLES
         *
         *     wp mz attach-featured-media --post-types=sign
         *     wp mz attach-featured-media --post-types=sign --apply
         *
         * @when after_wp_load
         */
        public function __invoke($args, $assoc_args): void
        {
            $result = mz_ptm_attach_featured_media_run([
                'post_types' => $assoc_args['post-types'] ?? '',
                'apply' => isset($assoc_args['apply']),
            ]);

            foreach ((array) $result['messages'] as $message) {
                \WP_CLI::log((string) $message);
            }

            \WP_CLI::log('');
            \WP_CLI::log('Content post types: ' . implode(', ', (array) ($result['post_types'] ?? [])));
            \WP_CLI::log('Mode: ' . (!empty($result['apply']) ? 'live run' : 'dry run'));
            \WP_CLI::log('Posts scanned: ' . (int) ($result['posts_scanned'] ?? 0));
            \WP_CLI::log('Attachments updated: ' . (int) ($result['attachments_updated'] ?? 0));
            \WP_CLI::log('Shared attachments assigned: ' . (int) ($result['shared_attachments_assigned'] ?? 0));
            \WP_CLI::log('Shared attachments skipped: ' . (int) ($result['shared_attachments_skipped'] ?? 0));
            \WP_CLI::log('Missing thumbnails: ' . (int) ($result['missing_thumbnails'] ?? 0));
            \WP_CLI::log('Missing attachments: ' . (int) ($result['missing_attachments'] ?? 0));
            \WP_CLI::log('Errors: ' . (int) ($result['errors'] ?? 0));

            if (!empty($result['errors'])) {
                \WP_CLI::halt(1);
            }

            \WP_CLI::success(!empty($result['apply']) ? 'Featured media attach complete.' : 'Featured media attach dry run complete.');
        }
    }

    \WP_CLI::add_command('mz attach-featured-media', 'MZ_Attach_Featured_Media_CLI_Command');
}

if (defined('WP_CLI') && WP_CLI && !class_exists('MZ_Repair_Sign_Customer_Relationships_CLI_Command')) {
    class MZ_Repair_Sign_Customer_Relationships_CLI_Command
    {
        /**
         * Repair sign-to-customer relationships from a production content export.
         *
         * ## OPTIONS
         *
         * --file=<file>
         * : Absolute path to the production content WXR/XML file.
         *
         * [--apply]
         * : Perform writes. Omit for a dry run.
         *
         * ## EXAMPLES
         *
         *     wp mz repair-sign-customers --file=/path/production.xml
         *     wp mz repair-sign-customers --file=/path/production.xml --apply
         *
         * @when after_wp_load
         */
        public function __invoke($args, $assoc_args): void
        {
            if (empty($assoc_args['file'])) {
                \WP_CLI::error('The --file argument is required.');
            }

            $result = mz_ptm_repair_sign_customer_relationships_run([
                'file' => (string) $assoc_args['file'],
                'apply' => isset($assoc_args['apply']),
            ]);

            foreach ((array) $result['messages'] as $message) {
                \WP_CLI::log((string) $message);
            }

            \WP_CLI::log('');
            \WP_CLI::log('File: ' . (string) ($result['file'] ?? ''));
            \WP_CLI::log('Mode: ' . (!empty($result['apply']) ? 'live run' : 'dry run'));
            \WP_CLI::log('Signs scanned: ' . (int) ($result['scanned'] ?? 0));
            \WP_CLI::log('Relationships updated: ' . (int) ($result['updated'] ?? 0));
            \WP_CLI::log('Unchanged: ' . (int) ($result['unchanged'] ?? 0));
            \WP_CLI::log('Skipped missing customer map: ' . (int) ($result['skipped_missing_customer_map'] ?? 0));
            \WP_CLI::log('Missing signs: ' . (int) ($result['missing_signs'] ?? 0));
            \WP_CLI::log('Missing customers: ' . (int) ($result['missing_customers'] ?? 0));
            \WP_CLI::log('Errors: ' . (int) ($result['errors'] ?? 0));

            if (!empty($result['errors'])) {
                \WP_CLI::halt(1);
            }

            \WP_CLI::success(!empty($result['apply']) ? 'Sign customer repair complete.' : 'Sign customer repair dry run complete.');
        }
    }

    \WP_CLI::add_command('mz repair-sign-customers', 'MZ_Repair_Sign_Customer_Relationships_CLI_Command');
}

if (!function_exists('mz_ptm_get_earliest_post_id')) {
    function mz_ptm_get_earliest_post_id(array $post_ids): int
    {
        $post_ids = array_values(array_filter(array_map('intval', $post_ids)));
        if ($post_ids === []) {
            return 0;
        }

        $posts = get_posts([
            'post__in' => $post_ids,
            'post_type' => 'any',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'orderby' => 'date ID',
            'order' => 'ASC',
            'suppress_filters' => true,
        ]);

        if (empty($posts)) {
            sort($post_ids, SORT_NUMERIC);
            return (int) $post_ids[0];
        }

        usort($posts, static function (WP_Post $a, WP_Post $b): int {
            $a_date = (string) $a->post_date_gmt !== '0000-00-00 00:00:00' ? (string) $a->post_date_gmt : (string) $a->post_date;
            $b_date = (string) $b->post_date_gmt !== '0000-00-00 00:00:00' ? (string) $b->post_date_gmt : (string) $b->post_date;

            if ($a_date === $b_date) {
                return (int) $a->ID <=> (int) $b->ID;
            }

            return $a_date <=> $b_date;
        });

        return (int) $posts[0]->ID;
    }
}
