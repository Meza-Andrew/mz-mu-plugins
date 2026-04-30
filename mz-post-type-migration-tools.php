<?php

/**
 * Plugin Name: MZ Post Type Migration Tools
 * Description: Admin and WP-CLI tools to move posts from one post type to another.
 * Version: 1.0.11
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
