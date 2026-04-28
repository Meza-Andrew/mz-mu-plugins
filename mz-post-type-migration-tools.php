<?php

/**
 * Plugin Name: MZ Post Type Migration Tools
 * Description: Admin and WP-CLI tools to move posts from one post type to another.
 * Version: 1.0.0
 * Author: Meza
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MZ_PTM_RESULT_TRANSIENT')) {
    define('MZ_PTM_RESULT_TRANSIENT', 'mz_ptm_last_result');
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

if (!function_exists('mz_ptm_get_action_url')) {
    function mz_ptm_get_action_url(string $mode, string $source = 'testimonial,testimonials', string $destination = 'review'): string
    {
        return add_query_arg([
            'action' => MZ_PTM_ACTION,
            'mode' => $mode,
            'source' => $source,
            'destination' => $destination,
        ], admin_url('admin-post.php'));
    }
}

if (!function_exists('mz_ptm_collect_post_ids')) {
    function mz_ptm_collect_post_ids(array $sources): array
    {
        if ($sources === []) {
            return [];
        }

        return get_posts([
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
        ]);
    }
}

if (!function_exists('mz_ptm_run_migration')) {
    function mz_ptm_run_migration(array $args = []): array
    {
        $source_post_types = mz_ptm_get_available_source_post_types($args['source'] ?? null);
        $destination_post_type = mz_ptm_get_destination_post_type($args['destination'] ?? null);
        $dry_run = !empty($args['dry_run']);

        $result = [
            'source_post_types' => $source_post_types,
            'requested_source_post_types' => mz_ptm_parse_post_types($args['source'] ?? null),
            'destination_post_type' => $destination_post_type,
            'dry_run' => $dry_run,
            'total' => 0,
            'migrated' => 0,
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

        if (in_array($destination_post_type, $source_post_types, true)) {
            $result['failures'][] = 'Destination post type must be different from the source post type.';
            return $result;
        }

        $post_ids = mz_ptm_collect_post_ids($source_post_types);
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

            if ($dry_run) {
                $result['messages'][] = 'Would migrate ' . $label;
                continue;
            }

            $updated = wp_update_post([
                'ID' => (int) $post->ID,
                'post_type' => $destination_post_type,
            ], true);

            if (is_wp_error($updated)) {
                $result['failures'][] = sprintf('Failed to migrate %s: %s', $label, $updated->get_error_message());
                continue;
            }

            $result['migrated']++;
            $result['messages'][] = 'Migrated ' . $label;
        }

        return $result;
    }
}

if (!function_exists('mz_ptm_store_result')) {
    function mz_ptm_store_result(array $result): void
    {
        $result['recorded_at'] = current_time('mysql');
        set_transient(MZ_PTM_RESULT_TRANSIENT, $result, HOUR_IN_SECONDS);
    }
}

if (!function_exists('mz_ptm_get_result')) {
    function mz_ptm_get_result(): array
    {
        $result = get_transient(MZ_PTM_RESULT_TRANSIENT);

        return is_array($result) ? $result : [];
    }
}

if (!function_exists('mz_ptm_handle_admin_post')) {
    function mz_ptm_handle_admin_post(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to run this migration.', 403);
        }

        check_admin_referer('mz_ptm_run');

        $mode = isset($_REQUEST['mode']) ? sanitize_key((string) $_REQUEST['mode']) : '';
        if (!in_array($mode, ['preview', 'run'], true)) {
            wp_die('Invalid migration mode.', 400);
        }

        $source = isset($_REQUEST['source']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['source'])) : null;
        $destination = isset($_REQUEST['destination']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['destination'])) : null;

        $result = mz_ptm_run_migration([
            'dry_run' => $mode !== 'run',
            'source' => $source,
            'destination' => $destination,
        ]);

        mz_ptm_store_result($result);

        $redirect_url = mz_ptm_get_redirect_url();
        $redirect_url = add_query_arg([
            'source' => implode(',', (array) $result['requested_source_post_types']),
            'destination' => (string) $result['destination_post_type'],
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
                    'Sources: %s. Destination: %s. Matched: %d. Migrated: %d. Failures: %d.',
                    implode(', ', (array) $result['source_post_types']),
                    (string) $result['destination_post_type'],
                    (int) ($result['total'] ?? 0),
                    (int) ($result['migrated'] ?? 0),
                    count((array) ($result['failures'] ?? []))
                ));
                ?>
            </p>
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
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.', 403);
        }

        $source = isset($_GET['source']) ? sanitize_text_field(wp_unslash((string) $_GET['source'])) : 'testimonial,testimonials';
        $destination = isset($_GET['destination']) ? sanitize_text_field(wp_unslash((string) $_GET['destination'])) : 'review';
        $preview_url = wp_nonce_url(mz_ptm_get_action_url('preview', $source, $destination), 'mz_ptm_run');
        $run_url = wp_nonce_url(mz_ptm_get_action_url('run', $source, $destination), 'mz_ptm_run');
        $result = mz_ptm_get_result();
        ?>
        <div class="wrap">
            <h1>Post Type Migration</h1>
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
                                <p class="description">Use a comma-separated list, like <code>testimonial,testimonials</code>.</p>
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

            <h2>Direct URLs</h2>
            <p>Use these while logged into WordPress admin:</p>
            <p><code><?php echo esc_html($preview_url); ?></code></p>
            <p><code><?php echo esc_html($run_url); ?></code></p>

            <h2>WP-CLI</h2>
            <p><code>wp mz post-type-migrate --source=<?php echo esc_html($source); ?> --destination=<?php echo esc_html($destination); ?> --dry-run</code></p>
            <p><code>wp mz post-type-migrate --source=<?php echo esc_html($source); ?> --destination=<?php echo esc_html($destination); ?></code></p>

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
                'dry_run' => isset($assoc_args['dry-run']),
            ]);

            foreach ((array) $result['messages'] as $message) {
                \WP_CLI::log($message);
            }

            \WP_CLI::log('');
            \WP_CLI::log('Requested source post types: ' . implode(', ', (array) $result['requested_source_post_types']));
            \WP_CLI::log('Matched source post types: ' . implode(', ', (array) $result['source_post_types']));
            \WP_CLI::log('Destination post type: ' . (string) $result['destination_post_type']);
            \WP_CLI::log('Mode: ' . (!empty($result['dry_run']) ? 'dry run' : 'live run'));
            \WP_CLI::log('Matched posts: ' . (int) $result['total']);
            \WP_CLI::log('Migrated posts: ' . (int) $result['migrated']);
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
