<?php

/**
 * Plugin Name: MZ Post Type Migration Tools
 * Description: Admin and WP-CLI tools to move posts from one post type to another.
 * Version: 1.0.16
 * Author: Meza
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

if (!defined('MZ_PTM_PAGE_SLUG')) {
    define('MZ_PTM_PAGE_SLUG', 'mz-post-type-migration-tools');
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

if (!function_exists('mz_ptm_user_can_access_tool')) {
    function mz_ptm_user_can_access_tool(): bool
    {
        $admin_access_enabled = (bool) apply_filters('mz_ptm_admin_tool_enabled', false);
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

if (!function_exists('mz_ptm_register_tools_page')) {
    function mz_ptm_register_tools_page(): void
    {
        if (!mz_ptm_user_can_access_tool()) {
            return;
        }

        add_management_page(
            'Post Type Migration',
            'Post Type Migration',
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
        $result = mz_ptm_get_result();
        ?>
        <div class="wrap" data-meza-admin-chrome="post-type-migration-tools">
            <h1 class="wp-heading-inline">Post Type Migration</h1>
            <hr class="wp-header-end" />
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
