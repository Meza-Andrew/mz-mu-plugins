<?php

/**
 * Plugin Name: MZ FAQ Migration Tools (MU)
 * Description: Admin-only tools to migrate legacy section_faq data into built-in FAQ posts and FAQ sections.
 * Author: Meza
 * Version: 1.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MZFQMT_PAGE_SLUG')) {
    define('MZFQMT_PAGE_SLUG', 'mz-faq-migration-tools');
}

if (!defined('MZFQMT_ACTION')) {
    define('MZFQMT_ACTION', 'mz_faq_migration');
}

if (!defined('MZFQMT_RESULT_USER_META')) {
    define('MZFQMT_RESULT_USER_META', 'mz_faq_migration_last_result');
}

if (!defined('MZFQMT_FAQ_REPEATER_FIELD_KEY')) {
    define('MZFQMT_FAQ_REPEATER_FIELD_KEY', 'field_681255cc3a8a2');
}

if (!defined('MZFQMT_SECTION_VISIBILITY_FIELD_KEY')) {
    define('MZFQMT_SECTION_VISIBILITY_FIELD_KEY', 'field_69b03ef5673a1');
}

if (!defined('MZFQMT_SECTION_GROUP_FIELD_KEY')) {
    define('MZFQMT_SECTION_GROUP_FIELD_KEY', 'field_69b03f0e673a2');
}

if (!function_exists('mzfmt_user_can_access_tool')) {
    function mzfmt_user_can_access_tool(): bool
    {
        $admin_access_enabled = (bool) apply_filters('mzfmt_admin_tool_enabled', false);
        if (!$admin_access_enabled) {
            return false;
        }

        $user = wp_get_current_user();
        $user_login = strtolower((string) ($user->user_login ?? ''));

        return $user instanceof WP_User
            && $user->exists()
            && (
                current_user_can('manage_options')
                || in_array($user_login, ['ameza', 'andrew', 'andrewmeza'], true)
            );
    }
}

if (!function_exists('mzfmt_faq_post_type_ready')) {
    function mzfmt_faq_post_type_ready(): bool
    {
        return post_type_exists('faq');
    }
}

if (!function_exists('mzfmt_get_source_taxonomies')) {
    function mzfmt_get_source_taxonomies(): array
    {
        $taxonomies = ['sign_type', 'location'];
        $taxonomies = apply_filters('mzfmt_source_taxonomies', $taxonomies);

        return array_values(array_filter(array_map('sanitize_key', (array) $taxonomies)));
    }
}

if (!function_exists('mzfmt_get_acf_target')) {
    function mzfmt_get_acf_target(array $source)
    {
        if (($source['type'] ?? '') === 'term') {
            return sanitize_key((string) $source['taxonomy']) . '_' . (int) $source['id'];
        }

        return (int) ($source['id'] ?? 0);
    }
}

if (!function_exists('mzfmt_get_source_key')) {
    function mzfmt_get_source_key(array $source): string
    {
        if (($source['type'] ?? '') === 'term') {
            return 'term:' . sanitize_key((string) $source['taxonomy']) . ':' . (int) ($source['id'] ?? 0);
        }

        return 'post:' . (int) ($source['id'] ?? 0);
    }
}

if (!function_exists('mzfmt_parse_source_key')) {
    function mzfmt_parse_source_key(string $source_key): array
    {
        $source_key = trim($source_key);
        if ($source_key === '') {
            return [];
        }

        if (preg_match('/^post:(\d+)$/', $source_key, $matches) === 1) {
            return [
                'type' => 'post',
                'id' => (int) $matches[1],
            ];
        }

        if (preg_match('/^term:([a-z0-9_-]+):(\d+)$/i', $source_key, $matches) === 1) {
            return [
                'type' => 'term',
                'taxonomy' => sanitize_key((string) $matches[1]),
                'id' => (int) $matches[2],
            ];
        }

        return [];
    }
}

if (!function_exists('mzfmt_get_source_label')) {
    function mzfmt_get_source_label(array $source): string
    {
        if (($source['type'] ?? '') === 'term') {
            return sprintf(
                'term:%s:%d (%s)',
                sanitize_key((string) ($source['taxonomy'] ?? '')),
                (int) ($source['id'] ?? 0),
                (string) ($source['title'] ?? '')
            );
        }

        return sprintf(
            'post:%d (%s)',
            (int) ($source['id'] ?? 0),
            (string) ($source['title'] ?? '')
        );
    }
}

if (!function_exists('mzfmt_collect_sources')) {
    function mzfmt_collect_sources(int $limit = 0, string $source_key = ''): array
    {
        global $wpdb;

        $parsed = mzfmt_parse_source_key($source_key);
        $sources = [];

        if ($parsed !== []) {
            if ($parsed['type'] === 'post') {
                $post = get_post((int) $parsed['id']);
                if ($post instanceof WP_Post && !in_array($post->post_type, ['attachment', 'revision', 'faq'], true)) {
                    $sources[] = [
                        'type' => 'post',
                        'id' => (int) $post->ID,
                        'post_type' => (string) $post->post_type,
                        'title' => get_the_title($post),
                        'slug' => (string) $post->post_name,
                    ];
                }
            } elseif (!empty($parsed['taxonomy'])) {
                $term = get_term((int) $parsed['id'], (string) $parsed['taxonomy']);
                if ($term instanceof WP_Term && !is_wp_error($term)) {
                    $sources[] = [
                        'type' => 'term',
                        'id' => (int) $term->term_id,
                        'taxonomy' => (string) $term->taxonomy,
                        'title' => (string) $term->name,
                        'slug' => (string) $term->slug,
                    ];
                }
            }

            return $sources;
        }

        $post_types = get_post_types(['public' => true], 'names');
        unset($post_types['attachment'], $post_types['revision'], $post_types['faq'], $post_types['form']);

        $query = new WP_Query([
            'post_type' => array_values($post_types),
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'section_faq_faq',
                    'compare' => 'EXISTS',
                ],
            ],
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
            'suppress_filters' => true,
        ]);

        foreach ((array) $query->posts as $post_id) {
            $post = get_post((int) $post_id);
            if (!($post instanceof WP_Post)) {
                continue;
            }

            $sources[] = [
                'type' => 'post',
                'id' => (int) $post->ID,
                'post_type' => (string) $post->post_type,
                'title' => get_the_title($post),
                'slug' => (string) $post->post_name,
            ];
        }

        foreach (mzfmt_get_source_taxonomies() as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            $term_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT tm.term_id
                FROM {$wpdb->termmeta} tm
                INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id
                WHERE tm.meta_key = %s AND tt.taxonomy = %s
                ORDER BY tm.term_id ASC",
                'section_faq_faq',
                $taxonomy
            ));

            foreach ((array) $term_ids as $term_id) {
                $term = get_term((int) $term_id, $taxonomy);
                if (!($term instanceof WP_Term)) {
                    continue;
                }

                $sources[] = [
                    'type' => 'term',
                    'id' => (int) $term->term_id,
                    'taxonomy' => (string) $term->taxonomy,
                    'title' => (string) $term->name,
                    'slug' => (string) $term->slug,
                ];
            }
        }

        if ($limit > 0) {
            $sources = array_slice($sources, 0, $limit);
        }

        return $sources;
    }
}

if (!function_exists('mzfmt_get_legacy_section')) {
    function mzfmt_get_legacy_section(array $source): array
    {
        $meta_reader = ($source['type'] ?? '') === 'term' ? 'get_term_meta' : 'get_post_meta';
        $source_id = (int) ($source['id'] ?? 0);

        if ($source_id <= 0 || !function_exists($meta_reader)) {
            return [];
        }

        $faq_count = (int) call_user_func($meta_reader, $source_id, 'section_faq_faq', true);
        $headline = (string) call_user_func($meta_reader, $source_id, 'section_faq_headline', true);
        $subhead = (string) call_user_func($meta_reader, $source_id, 'section_faq_subhead', true);
        $image = (int) call_user_func($meta_reader, $source_id, 'section_faq_image', true);
        $id = (string) call_user_func($meta_reader, $source_id, 'section_faq_id', true);

        if ($faq_count <= 0 && trim($headline) === '' && trim($subhead) === '' && $image <= 0) {
            return [];
        }

        $faq_rows = [];
        for ($i = 0; $i < $faq_count; $i++) {
            $question = (string) call_user_func($meta_reader, $source_id, 'section_faq_faq_' . $i . '_question', true);
            $answer = (string) call_user_func($meta_reader, $source_id, 'section_faq_faq_' . $i . '_answer', true);

            $faq_rows[] = [
                'question' => $question,
                'answer' => $answer,
            ];
        }

        return [
            'headline' => $headline,
            'subhead' => $subhead,
            'image' => $image,
            'id' => $id,
            'faq' => $faq_rows,
        ];
    }
}

if (!function_exists('mzfmt_normalize_legacy_faq_items')) {
    function mzfmt_normalize_legacy_faq_items(array $legacy): array
    {
        $rows = [];

        foreach ((array) ($legacy['faq'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $question = trim((string) ($item['question'] ?? ''));
            $answer = (string) ($item['answer'] ?? '');

            if ($question === '' || trim(wp_strip_all_tags($answer)) === '') {
                continue;
            }

            $rows[] = [
                'question' => $question,
                'answer' => $answer,
            ];
        }

        return $rows;
    }
}

if (!function_exists('mzfmt_build_faq_post_title')) {
    function mzfmt_build_faq_post_title(array $source): string
    {
        $label = trim((string) ($source['title'] ?? ''));
        if ($label === '') {
            $label = mzfmt_get_source_key($source);
        }

        if (function_exists('meza_get_faq_group_label')) {
            return meza_get_faq_group_label($label);
        }

        return preg_match('/\bfaqs?$/i', $label) ? $label : $label . ' FAQs';
    }
}

if (!function_exists('mzfmt_unique_faq_slug')) {
    function mzfmt_unique_faq_slug(string $base_slug, int $ignore_post_id = 0): string
    {
        $base_slug = sanitize_title($base_slug);
        if ($base_slug === '') {
            $base_slug = 'faq-group';
        }

        $candidate = $base_slug;
        $i = 2;

        while (true) {
            $existing = get_page_by_path($candidate, OBJECT, 'faq');
            if (!($existing instanceof WP_Post) || (int) $existing->ID === $ignore_post_id) {
                return $candidate;
            }

            $candidate = $base_slug . '-' . $i;
            $i++;

            if ($i > 9999) {
                return $base_slug . '-' . time();
            }
        }
    }
}

if (!function_exists('mzfmt_find_existing_faq_post')) {
    function mzfmt_find_existing_faq_post(string $source_key): int
    {
        $existing = get_posts([
            'post_type' => 'faq',
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_mzfmt_source_key',
                    'value' => $source_key,
                    'compare' => '=',
                ],
            ],
            'no_found_rows' => true,
            'suppress_filters' => true,
        ]);

        return !empty($existing) ? (int) $existing[0] : 0;
    }
}

if (!function_exists('mzfmt_upsert_faq_post')) {
    function mzfmt_upsert_faq_post(array $source, string $source_key, array $faq_rows, bool $apply)
    {
        $existing_id = mzfmt_find_existing_faq_post($source_key);
        $faq_title = mzfmt_build_faq_post_title($source);
        $faq_slug = mzfmt_unique_faq_slug((string) ($source['slug'] ?? '') . '-faq', $existing_id);
        $status = 'publish';

        if (!$apply) {
            return [
                'faq_post_id' => $existing_id,
                'created' => $existing_id <= 0,
                'updated' => $existing_id > 0,
                'title' => $faq_title,
                'slug' => $faq_slug,
            ];
        }

        if ($existing_id > 0) {
            $updated = wp_update_post([
                'ID' => $existing_id,
                'post_title' => $faq_title,
                'post_name' => $faq_slug,
                'post_status' => $status,
            ], true);

            if (is_wp_error($updated)) {
                return $updated;
            }

            $faq_post_id = $existing_id;
            $created = false;
        } else {
            $inserted = wp_insert_post([
                'post_type' => 'faq',
                'post_status' => $status,
                'post_author' => (int) (get_current_user_id() ?: 1),
                'post_title' => $faq_title,
                'post_name' => $faq_slug,
                'post_content' => ' ',
                'post_excerpt' => ' ',
            ], true, false);

            if (is_wp_error($inserted)) {
                return $inserted;
            }

            $faq_post_id = (int) $inserted;
            $created = true;
        }

        update_field(MZFQMT_FAQ_REPEATER_FIELD_KEY, $faq_rows, $faq_post_id);
        update_post_meta($faq_post_id, '_mzfmt_source_key', $source_key);

        return [
            'faq_post_id' => $faq_post_id,
            'created' => $created,
            'updated' => !$created,
            'title' => $faq_title,
            'slug' => $faq_slug,
        ];
    }
}

if (!function_exists('mzfmt_write_built_in_section')) {
    function mzfmt_write_built_in_section(array $source, array $legacy, int $faq_post_id, bool $apply): void
    {
        if (!$apply) {
            return;
        }

        $section = [
            'headline' => trim((string) ($legacy['headline'] ?? '')),
            'subhead' => trim((string) ($legacy['subhead'] ?? '')),
            'image' => !empty($legacy['image']) ? (int) $legacy['image'] : 0,
            'description' => '',
            'link' => [],
            'faqs' => [$faq_post_id],
        ];

        $target = mzfmt_get_acf_target($source);

        update_field(MZFQMT_SECTION_VISIBILITY_FIELD_KEY, 1, $target);
        update_field(MZFQMT_SECTION_GROUP_FIELD_KEY, $section, $target);
    }
}

if (!function_exists('mzfmt_store_result')) {
    function mzfmt_store_result(array $result): void
    {
        $result['recorded_at'] = current_time('mysql');
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            update_user_meta($user_id, MZFQMT_RESULT_USER_META, $result);
        }
    }
}

if (!function_exists('mzfmt_get_result')) {
    function mzfmt_get_result(): array
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return [];
        }

        $result = get_user_meta($user_id, MZFQMT_RESULT_USER_META, true);
        return is_array($result) ? $result : [];
    }
}

if (!function_exists('mzfmt_run_migration')) {
    function mzfmt_run_migration(array $args = []): array
    {
        $apply = !empty($args['apply']);
        $limit = max(0, (int) ($args['limit'] ?? 0));
        $source_key = trim((string) ($args['source_key'] ?? ''));

        $result = [
            'apply' => $apply,
            'limit' => $limit,
            'source_key' => $source_key,
            'total_scanned' => 0,
            'summary' => [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0,
            ],
            'messages' => [],
        ];

        if (!mzfmt_faq_post_type_ready()) {
            $result['summary']['errors']++;
            $result['messages'][] = '[ERROR] FAQ post type is not available.';
            return $result;
        }

        $sources = mzfmt_collect_sources($limit, $source_key);
        $result['total_scanned'] = count($sources);

        foreach ($sources as $source) {
            $source_label = mzfmt_get_source_label($source);
            $legacy = mzfmt_get_legacy_section($source);

            if ($legacy === []) {
                continue;
            }

            $faq_rows = mzfmt_normalize_legacy_faq_items($legacy);
            if ($faq_rows === []) {
                $result['summary']['skipped']++;
                $result['messages'][] = '[SKIPPED] ' . $source_label . ' -> no valid FAQ rows found';
                continue;
            }

            $source_key_value = mzfmt_get_source_key($source);
            $faq_post = mzfmt_upsert_faq_post($source, $source_key_value, $faq_rows, $apply);

            if (is_wp_error($faq_post)) {
                $result['summary']['errors']++;
                $result['messages'][] = '[ERROR] ' . $source_label . ' -> ' . $faq_post->get_error_message();
                continue;
            }

            mzfmt_write_built_in_section($source, $legacy, (int) ($faq_post['faq_post_id'] ?? 0), $apply);

            if (!empty($faq_post['created'])) {
                $result['summary']['created']++;
                $verb = $apply ? 'Created' : 'Would create';
            } else {
                $result['summary']['updated']++;
                $verb = $apply ? 'Updated' : 'Would update';
            }

            $result['messages'][] = sprintf(
                '[%s] %s -> faq=%d (%s), rows=%d',
                strtoupper($verb),
                $source_label,
                (int) ($faq_post['faq_post_id'] ?? 0),
                (string) ($faq_post['title'] ?? ''),
                count($faq_rows)
            );
        }

        return $result;
    }
}

if (!function_exists('mzfmt_get_redirect_url')) {
    function mzfmt_get_redirect_url(): string
    {
        return admin_url('tools.php?page=' . MZFQMT_PAGE_SLUG);
    }
}

if (!function_exists('mzfmt_handle_admin_post')) {
    function mzfmt_handle_admin_post(): void
    {
        if (!mzfmt_user_can_access_tool()) {
            wp_die('You do not have permission to run this migration.', 403);
        }

        check_admin_referer('mzfmt_run');

        $mode = isset($_REQUEST['mode']) ? sanitize_key((string) $_REQUEST['mode']) : '';
        if (!in_array($mode, ['preview', 'run'], true)) {
            wp_die('Invalid migration mode.', 400);
        }

        $result = mzfmt_run_migration([
            'apply' => $mode === 'run',
            'limit' => isset($_REQUEST['limit']) ? (int) $_REQUEST['limit'] : 0,
            'source_key' => isset($_REQUEST['source_key']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['source_key'])) : '',
        ]);

        mzfmt_store_result($result);

        $redirect_url = add_query_arg([
            'page' => MZFQMT_PAGE_SLUG,
            'limit' => (int) ($result['limit'] ?? 0),
            'source_key' => (string) ($result['source_key'] ?? ''),
            'mzfmt_result' => !empty($result['apply']) ? 'run' : 'preview',
        ], admin_url('tools.php'));

        wp_safe_redirect($redirect_url);
        exit;
    }

    add_action('admin_post_' . MZFQMT_ACTION, 'mzfmt_handle_admin_post');
}

if (!function_exists('mzfmt_render_result_summary')) {
    function mzfmt_render_result_summary(array $result): void
    {
        if ($result === []) {
            return;
        }
        ?>
        <div class="notice notice-info inline">
            <p>
                <strong><?php echo esc_html(!empty($result['apply']) ? 'Last migration' : 'Last dry run'); ?></strong>
                <?php if (!empty($result['recorded_at'])) : ?>
                    <span>at <?php echo esc_html((string) $result['recorded_at']); ?></span>
                <?php endif; ?>
            </p>
            <p>
                <?php
                echo esc_html(sprintf(
                    'Scanned: %d. Created: %d. Updated: %d. Skipped: %d. Errors: %d.',
                    (int) ($result['total_scanned'] ?? 0),
                    (int) ($result['summary']['created'] ?? 0),
                    (int) ($result['summary']['updated'] ?? 0),
                    (int) ($result['summary']['skipped'] ?? 0),
                    (int) ($result['summary']['errors'] ?? 0)
                ));
                ?>
            </p>
            <?php if (!empty($result['messages'])) : ?>
                <textarea readonly rows="14" style="width:100%;font-family:monospace;"><?php echo esc_textarea(implode("\n", (array) $result['messages'])); ?></textarea>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('mzfmt_render_tools_page')) {
    function mzfmt_render_tools_page(): void
    {
        if (!mzfmt_user_can_access_tool()) {
            wp_die('You do not have permission to access this page.', 403);
        }

        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 0;
        $source_key = isset($_GET['source_key']) ? sanitize_text_field(wp_unslash((string) $_GET['source_key'])) : '';
        $result = mzfmt_get_result();
        ?>
        <div class="wrap" data-meza-admin-chrome="faq-migration-tools">
            <h1 class="wp-heading-inline">FAQ Migration</h1>
            <hr class="wp-header-end" />
            <p>Migrate legacy <code>section_faq</code> content on service pages and taxonomy terms into built-in <code>faq</code> posts plus the shared <code>section_faqs</code> structure.</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('mzfmt_run'); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(MZFQMT_ACTION); ?>" />

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="mzfmt-source-key">Source key</label></th>
                            <td>
                                <input id="mzfmt-source-key" name="source_key" type="text" class="regular-text" value="<?php echo esc_attr($source_key); ?>" />
                                <p class="description">Optional. Use <code>post:123</code> or <code>term:sign_type:32</code> to target one source.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mzfmt-limit">Limit</label></th>
                            <td>
                                <input id="mzfmt-limit" name="limit" type="number" min="0" step="1" class="small-text" value="<?php echo esc_attr((string) $limit); ?>" />
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

            <?php mzfmt_render_result_summary($result); ?>
        </div>
        <?php
    }
}

if (!function_exists('mzfmt_register_tools_page')) {
    function mzfmt_register_tools_page(): void
    {
        if (!mzfmt_user_can_access_tool()) {
            return;
        }

        add_management_page(
            'FAQ Migration',
            'FAQ Migration',
            'manage_options',
            MZFQMT_PAGE_SLUG,
            'mzfmt_render_tools_page'
        );
    }

    add_action('admin_menu', 'mzfmt_register_tools_page');
}

if (defined('WP_CLI') && WP_CLI && !class_exists('MZ_FAQ_Migration_CLI_Command')) {
    class MZ_FAQ_Migration_CLI_Command
    {
        /**
         * Migrate legacy FAQ sections into built-in faq posts and list-faqs sections.
         *
         * ## OPTIONS
         *
         * [--source-key=<source-key>]
         * : Optional source key like post:123 or term:sign_type:32.
         *
         * [--limit=<limit>]
         * : Optional result limit.
         *
         * [--apply]
         * : Perform writes. Omit for a dry run.
         *
         * ## EXAMPLES
         *
         *     wp mz faq-migrate
         *     wp mz faq-migrate --source-key=term:sign_type:32 --apply
         *
         * @when after_wp_load
         */
        public function __invoke($args, $assoc_args): void
        {
            $result = mzfmt_run_migration([
                'apply' => isset($assoc_args['apply']),
                'limit' => isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 0,
                'source_key' => isset($assoc_args['source-key']) ? (string) $assoc_args['source-key'] : '',
            ]);

            foreach ((array) $result['messages'] as $message) {
                \WP_CLI::log((string) $message);
            }

            \WP_CLI::log('');
            \WP_CLI::log('Mode: ' . (!empty($result['apply']) ? 'live run' : 'dry run'));
            \WP_CLI::log('Scanned: ' . (int) ($result['total_scanned'] ?? 0));
            \WP_CLI::log('Created: ' . (int) ($result['summary']['created'] ?? 0));
            \WP_CLI::log('Updated: ' . (int) ($result['summary']['updated'] ?? 0));
            \WP_CLI::log('Skipped: ' . (int) ($result['summary']['skipped'] ?? 0));
            \WP_CLI::log('Errors: ' . (int) ($result['summary']['errors'] ?? 0));

            if (!empty($result['summary']['errors'])) {
                \WP_CLI::halt(1);
            }

            \WP_CLI::success(!empty($result['apply']) ? 'Migration complete.' : 'Dry run complete.');
        }
    }

    \WP_CLI::add_command('mz faq-migrate', 'MZ_FAQ_Migration_CLI_Command');
}
