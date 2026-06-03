<?php

/**
 * Internal module for MZ Tools event field migration flows.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MZ_ETM_ACTION')) {
    define('MZ_ETM_ACTION', 'mz_event_field_migration');
}

if (!defined('MZ_ETM_RESULT_TRANSIENT')) {
    define('MZ_ETM_RESULT_TRANSIENT', 'mz_etm_last_result');
}

if (!defined('MZ_ETM_RESULT_USER_META')) {
    define('MZ_ETM_RESULT_USER_META', 'mz_etm_last_result');
}

if (!function_exists('mz_etm_user_can_access_tool')) {
    function mz_etm_user_can_access_tool(): bool
    {
        if (function_exists('mz_ptm_user_can_access_tool')) {
            return mz_ptm_user_can_access_tool();
        }

        return current_user_can('manage_options');
    }
}

if (!function_exists('mz_etm_event_post_type_ready')) {
    function mz_etm_event_post_type_ready(): bool
    {
        return post_type_exists('event');
    }
}

if (!function_exists('mz_etm_normalize_date_value')) {
    function mz_etm_normalize_date_value($value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{8}$/', $value) === 1) {
            $date = DateTime::createFromFormat('Ymd', $value);
            return $date instanceof DateTime ? $date->format('Ymd') : '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            $date = DateTime::createFromFormat('Y-m-d', $value);
            return $date instanceof DateTime ? $date->format('Ymd') : '';
        }

        $timestamp = strtotime($value);
        return $timestamp ? gmdate('Ymd', $timestamp) : '';
    }
}

if (!function_exists('mz_etm_normalize_time_value')) {
    function mz_etm_normalize_time_value($value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $formats = ['H:i:s', 'H:i', 'g:i a', 'g:ia', 'ga'];

        foreach ($formats as $format) {
            $time = DateTime::createFromFormat($format, strtolower($value));
            if ($time instanceof DateTime) {
                return $time->format('H:i:s');
            }
        }

        $timestamp = strtotime($value);
        return $timestamp ? gmdate('H:i:s', $timestamp) : '';
    }
}

if (!function_exists('mz_etm_get_legacy_time_rows')) {
    function mz_etm_get_legacy_time_rows(int $post_id): array
    {
        $count = (int) get_post_meta($post_id, 'times', true);
        $rows = [];

        if ($count > 0) {
            for ($index = 0; $index < $count; $index++) {
                $start = mz_etm_normalize_time_value(get_post_meta($post_id, 'times_' . $index . '_time_start', true));
                $end = mz_etm_normalize_time_value(get_post_meta($post_id, 'times_' . $index . '_time_end', true));

                if ($start === '' && $end === '') {
                    continue;
                }

                $rows[] = [
                    'start' => $start,
                    'end' => $end,
                ];
            }
        }

        if ($rows !== []) {
            return $rows;
        }

        $times = function_exists('get_field') ? get_field('times', $post_id) : [];
        if (!is_array($times)) {
            return [];
        }

        foreach ($times as $row) {
            if (!is_array($row)) {
                continue;
            }

            $start = mz_etm_normalize_time_value($row['time_start'] ?? ($row['start'] ?? ''));
            $end = mz_etm_normalize_time_value($row['time_end'] ?? ($row['end'] ?? ''));

            if ($start === '' && $end === '') {
                continue;
            }

            $rows[] = [
                'start' => $start,
                'end' => $end,
            ];
        }

        return $rows;
    }
}

if (!function_exists('mz_etm_get_existing_dates_value')) {
    function mz_etm_get_existing_dates_value(int $post_id): array
    {
        $dates = function_exists('get_field') ? get_field('dates', $post_id) : [];
        return is_array($dates) ? $dates : [];
    }
}

if (!function_exists('mz_etm_build_dates_payload')) {
    function mz_etm_build_dates_payload(int $post_id): array
    {
        $date_start = mz_etm_normalize_date_value(get_post_meta($post_id, 'date_start', true));
        $date_end = mz_etm_normalize_date_value(get_post_meta($post_id, 'date_end', true));
        $legacy_event_date = mz_etm_normalize_date_value(get_post_meta($post_id, 'event_date', true));
        $legacy_event_end_date = mz_etm_normalize_date_value(get_post_meta($post_id, 'event_end_date', true));

        if ($date_start === '') {
            $date_start = $legacy_event_date;
        }

        if ($date_end === '') {
            $date_end = $legacy_event_end_date;
        }

        $time_rows = mz_etm_get_legacy_time_rows($post_id);

        if ($time_rows === []) {
            $legacy_start = mz_etm_normalize_time_value(get_post_meta($post_id, 'event_start_time', true));
            $legacy_end = mz_etm_normalize_time_value(get_post_meta($post_id, 'event_end_time', true));

            if ($legacy_start !== '' || $legacy_end !== '') {
                $time_rows[] = [
                    'start' => $legacy_start,
                    'end' => $legacy_end,
                ];
            }
        }

        if ($date_start === '' || $time_rows === []) {
            return [];
        }

        $times = [];
        foreach ($time_rows as $row) {
            $start = trim((string) ($row['start'] ?? ''));
            $end = trim((string) ($row['end'] ?? ''));

            if ($start === '') {
                continue;
            }

            $times[] = [
                'start' => $start,
                'end' => $end,
            ];
        }

        if ($times === []) {
            return [];
        }

        return [[
            'date' => [
                'start' => $date_start,
                'end' => $date_end,
            ],
            'times' => $times,
        ]];
    }
}

if (!function_exists('mz_etm_get_existing_summary_value')) {
    function mz_etm_get_existing_summary_value(int $post_id): array
    {
        $summary = function_exists('get_field') ? get_field('summary', $post_id) : [];
        if (is_array($summary)) {
            return $summary;
        }

        $before = is_scalar($summary) ? trim((string) $summary) : '';
        $after = trim((string) get_post_meta($post_id, 'summary_after', true));

        return [
            'before' => $before,
            'after' => $after,
        ];
    }
}

if (!function_exists('mz_etm_transform_excerpt_to_past_tense')) {
    function mz_etm_transform_excerpt_to_past_tense(string $excerpt): string
    {
        $excerpt = trim($excerpt);
        if ($excerpt === '') {
            return '';
        }

        $transformed = $excerpt;

        $sentence_start_patterns = [
            '/^Join us for\b/i' => 'Thank you to everyone who joined us for',
            '/^Join us as\b/i' => 'Thank you to everyone who joined us as',
            '/^Join us\b/i' => 'Thank you to everyone who joined us',
            '/^Experience\b/i' => 'Audiences experienced',
            '/^We return to\b/i' => 'We returned to',
            '/^We return\b/i' => 'We returned',
            '/^Our season concludes with\b/i' => 'Our season concluded with',
            '/^Our season concludes\b/i' => 'Our season concluded',
            '/^Our .* concert offers\b/i' => static function (array $matches): string {
                return preg_replace('/\boffers\b/i', 'offered', $matches[0], 1) ?: $matches[0];
            },
            '/^The Chorale reunites\b/i' => 'The Chorale reunited',
            '/^The Chorale teams up\b/i' => 'The Chorale teamed up',
        ];

        foreach ($sentence_start_patterns as $pattern => $replacement) {
            if (preg_match($pattern, $transformed) !== 1) {
                continue;
            }

            $transformed = is_callable($replacement)
                ? preg_replace_callback($pattern, $replacement, $transformed, 1) ?? $transformed
                : (preg_replace($pattern, $replacement, $transformed, 1) ?? $transformed);
            break;
        }

        $global_replacements = [
            '/\bcelebrates\b/i' => 'celebrated',
            '/\boffers\b/i' => 'offered',
            '/\breunites\b/i' => 'reunited',
            '/\bteams up with\b/i' => 'teamed up with',
            '/\bbrings you\b/i' => 'brought',
            '/\breturns to\b/i' => 'returned to',
            '/\bconcludes with\b/i' => 'concluded with',
        ];

        foreach ($global_replacements as $pattern => $replacement) {
            $transformed = preg_replace($pattern, $replacement, $transformed) ?? $transformed;
        }

        if ($transformed === $excerpt) {
            $transformed = 'This event featured ' . lcfirst($excerpt);
        }

        return $transformed;
    }
}

if (!function_exists('mz_etm_collect_summary_updates')) {
    function mz_etm_collect_summary_updates(WP_Post $post, bool $force = false): array
    {
        $excerpt = trim((string) $post->post_excerpt);
        if ($excerpt === '') {
            return [];
        }

        $existing_summary = mz_etm_get_existing_summary_value((int) $post->ID);
        $before = trim((string) ($existing_summary['before'] ?? ''));
        $after = trim((string) ($existing_summary['after'] ?? ''));

        $updates = [];

        if ($force || $before === '') {
            $updates['before'] = $excerpt;
        } else {
            $updates['before'] = $before;
        }

        if ($force || $after === '') {
            $updates['after'] = mz_etm_transform_excerpt_to_past_tense($excerpt);
        } else {
            $updates['after'] = $after;
        }

        if (
            !$force
            && $before !== ''
            && $after !== ''
            && $updates['before'] === $before
            && $updates['after'] === $after
        ) {
            return [];
        }

        return $updates;
    }
}

if (!function_exists('mz_etm_collect_candidate_post_ids')) {
    function mz_etm_collect_candidate_post_ids(array $args = []): array
    {
        if (!empty($args['post_id'])) {
            $post_id = (int) $args['post_id'];
            return $post_id > 0 ? [$post_id] : [];
        }

        $query = new WP_Query([
            'post_type' => 'event',
            'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        return array_map('intval', (array) $query->posts);
    }
}

if (!function_exists('mz_etm_post_has_meaningful_dates')) {
    function mz_etm_post_has_meaningful_dates(int $post_id): bool
    {
        $dates = mz_etm_get_existing_dates_value($post_id);
        return $dates !== [];
    }
}

if (!function_exists('mz_etm_store_result')) {
    function mz_etm_store_result(array $result): void
    {
        $result['recorded_at'] = current_time('mysql');
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            update_user_meta($user_id, MZ_ETM_RESULT_USER_META, $result);
        }

        set_transient(MZ_ETM_RESULT_TRANSIENT, $result, HOUR_IN_SECONDS);
    }
}

if (!function_exists('mz_etm_get_result')) {
    function mz_etm_get_result(): array
    {
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            $user_result = get_user_meta($user_id, MZ_ETM_RESULT_USER_META, true);
            if (is_array($user_result) && $user_result !== []) {
                return $user_result;
            }
        }

        $result = get_transient(MZ_ETM_RESULT_TRANSIENT);
        return is_array($result) ? $result : [];
    }
}

if (!function_exists('mz_etm_run_migration')) {
    function mz_etm_run_migration(array $args = []): array
    {
        $dry_run = !empty($args['dry_run']);
        $force = !empty($args['force']);
        $post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;

        $result = [
            'dry_run' => $dry_run,
            'force' => $force,
            'post_id' => $post_id,
            'total' => 0,
            'eligible' => 0,
            'migrated' => 0,
            'skipped_existing_dates' => 0,
            'skipped_no_legacy_data' => 0,
            'synced_schedule_meta' => 0,
            'failures' => [],
            'messages' => [],
        ];

        if (!mz_etm_event_post_type_ready()) {
            $result['failures'][] = 'The event post type is not available on this site.';
            return $result;
        }

        $post_ids = mz_etm_collect_candidate_post_ids($args);
        $result['total'] = count($post_ids);

        foreach ($post_ids as $candidate_id) {
            $post = get_post($candidate_id);

            if (!($post instanceof WP_Post) || $post->post_type !== 'event') {
                $result['failures'][] = sprintf('Post %d is not a valid event.', $candidate_id);
                continue;
            }

            $label = sprintf(
                '#%d [%s] %s',
                (int) $post->ID,
                (string) $post->post_status,
                $post->post_title !== '' ? $post->post_title : '(no title)'
            );

            $existing_dates = mz_etm_post_has_meaningful_dates((int) $post->ID);
            $dates_payload = mz_etm_build_dates_payload((int) $post->ID);
            $summary_updates = mz_etm_collect_summary_updates($post, $force);

            if ($existing_dates && !$force) {
                if ($summary_updates === []) {
                    $result['skipped_existing_dates']++;
                    $result['messages'][] = 'Skipped ' . $label . ' because dates already exist.';
                    continue;
                }

                $dates_payload = [];
            }

            if ($dates_payload === [] && $summary_updates === []) {
                $result['skipped_no_legacy_data']++;
                $result['messages'][] = 'Skipped ' . $label . ' because no legacy event field data was found.';
                continue;
            }

            $result['eligible']++;

            if ($dry_run) {
                $preview_parts = [];
                if ($dates_payload !== []) {
                    $preview_parts[] = sprintf('dates rows=%d', count($dates_payload));
                }
                if (isset($summary_updates['before'])) {
                    $preview_parts[] = 'summary.before';
                }
                if (isset($summary_updates['after'])) {
                    $preview_parts[] = 'summary.after';
                }

                $result['messages'][] = 'Would migrate ' . $label . ' (' . implode(', ', $preview_parts) . ').';
                continue;
            }

            if ($dates_payload !== []) {
                $updated = function_exists('update_field')
                    ? update_field('field_6a08cef5e142e', $dates_payload, (int) $post->ID)
                    : false;

                if ($updated === false) {
                    $result['failures'][] = 'Failed to update dates for ' . $label . '.';
                    continue;
                }
            }

            if ($summary_updates !== []) {
                $updated_summary = function_exists('update_field')
                    ? update_field('field_6a08e3fc9599b', $summary_updates, (int) $post->ID)
                    : false;

                if ($updated_summary === false) {
                    $result['failures'][] = 'Failed to update summary for ' . $label . '.';
                    continue;
                }
            }

            if (function_exists('\MZ\Recurring\mz_sync_event_schedule_meta')) {
                \MZ\Recurring\mz_sync_event_schedule_meta((int) $post->ID);
                $result['synced_schedule_meta']++;
            }

            $result['migrated']++;
            $result['messages'][] = 'Migrated ' . $label . '.';
        }

        return $result;
    }
}

if (!function_exists('mz_etm_handle_admin_post')) {
    function mz_etm_handle_admin_post(): void
    {
        if (!mz_etm_user_can_access_tool()) {
            wp_die('You do not have permission to run this migration.', 403);
        }

        check_admin_referer('mz_etm_run');

        $mode = isset($_REQUEST['mode']) ? sanitize_key((string) $_REQUEST['mode']) : '';
        if (!in_array($mode, ['preview', 'run'], true)) {
            wp_die('Invalid event migration mode.', 400);
        }

        $result = mz_etm_run_migration([
            'dry_run' => $mode !== 'run',
            'force' => !empty($_REQUEST['force']),
            'post_id' => isset($_REQUEST['post_id']) ? (int) $_REQUEST['post_id'] : 0,
        ]);

        mz_etm_store_result($result);

        $redirect_url = function_exists('mz_ptm_get_redirect_url')
            ? mz_ptm_get_redirect_url()
            : admin_url('tools.php');

        $redirect_url = add_query_arg([
            'tab' => 'event',
            'post_id' => (int) ($result['post_id'] ?? 0),
            'force' => !empty($result['force']) ? '1' : '0',
            'mz_etm_result' => !empty($result['dry_run']) ? 'preview' : 'run',
        ], $redirect_url);

        wp_safe_redirect($redirect_url);
        exit;
    }

    add_action('admin_post_' . MZ_ETM_ACTION, 'mz_etm_handle_admin_post');
}

if (!function_exists('mz_etm_render_result_summary')) {
    function mz_etm_render_result_summary(array $result): void
    {
        if ($result === []) {
            return;
        }
        ?>
        <div class="notice notice-info inline">
            <p>
                <strong><?php echo esc_html(!empty($result['dry_run']) ? 'Last event dry run' : 'Last event migration'); ?></strong>
                <?php if (!empty($result['recorded_at'])) : ?>
                    <span>at <?php echo esc_html((string) $result['recorded_at']); ?></span>
                <?php endif; ?>
            </p>
            <p>
                <?php
                echo esc_html(sprintf(
                    'Scanned: %d. Eligible: %d. Migrated: %d. Skipped with built-in dates: %d. Skipped without legacy data: %d. Schedule syncs: %d. Failures: %d.',
                    (int) ($result['total'] ?? 0),
                    (int) ($result['eligible'] ?? 0),
                    (int) ($result['migrated'] ?? 0),
                    (int) ($result['skipped_existing_dates'] ?? 0),
                    (int) ($result['skipped_no_legacy_data'] ?? 0),
                    (int) ($result['synced_schedule_meta'] ?? 0),
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

if (!function_exists('mz_etm_render_tools_tab')) {
    function mz_etm_render_tools_tab(): void
    {
        $result = mz_etm_get_result();
        $post_id = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;
        $force = isset($_GET['force']) && (string) $_GET['force'] === '1';
        ?>
        <p>Migrate legacy event schedule fields into the built-in <code>dates</code> structure used by the managed Events configuration. This preserves old meta for safety and syncs runtime schedule fields after each live migration.</p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('mz_etm_run'); ?>
            <input type="hidden" name="action" value="<?php echo esc_attr(MZ_ETM_ACTION); ?>" />

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="mz-etm-post-id">Event post ID</label></th>
                        <td>
                            <input id="mz-etm-post-id" name="post_id" type="number" min="0" step="1" class="small-text" value="<?php echo esc_attr((string) $post_id); ?>" />
                            <p class="description">Optional. Leave blank or <code>0</code> to scan every event post.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Force remap</th>
                        <td>
                            <label>
                                <input name="force" type="checkbox" value="1" <?php checked($force); ?> />
                                Rebuild the built-in <code>dates</code> field even when it already has values.
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p class="submit">
                <button type="submit" name="mode" value="preview" class="button button-secondary">Run dry run</button>
                <button type="submit" name="mode" value="run" class="button button-primary">Run migration</button>
            </p>
        </form>

        <?php mz_etm_render_result_summary($result); ?>
        <?php
    }
}

if (defined('WP_CLI') && WP_CLI && !class_exists('MZ_Event_Field_Migration_CLI_Command')) {
    class MZ_Event_Field_Migration_CLI_Command
    {
        /**
         * Migrate legacy event schedule fields into the built-in event field structure.
         *
         * ## OPTIONS
         *
         * [--post-id=<id>]
         * : Optional event post ID to migrate.
         *
         * [--force]
         * : Rebuild built-in dates even when the post already has dates.
         *
         * [--apply]
         * : Perform writes. Omit for a dry run.
         *
         * ## EXAMPLES
         *
         *     wp mz migrate-event-fields
         *     wp mz migrate-event-fields --apply
         *     wp mz migrate-event-fields --post-id=258 --apply
         *
         * @when after_wp_load
         */
        public function __invoke($args, $assoc_args): void
        {
            $result = mz_etm_run_migration([
                'post_id' => isset($assoc_args['post-id']) ? (int) $assoc_args['post-id'] : 0,
                'force' => isset($assoc_args['force']),
                'dry_run' => !isset($assoc_args['apply']),
            ]);

            foreach ((array) ($result['messages'] ?? []) as $message) {
                \WP_CLI::log((string) $message);
            }

            \WP_CLI::log('');
            \WP_CLI::log('Mode: ' . (!empty($result['dry_run']) ? 'dry run' : 'live run'));
            \WP_CLI::log('Scanned: ' . (int) ($result['total'] ?? 0));
            \WP_CLI::log('Eligible: ' . (int) ($result['eligible'] ?? 0));
            \WP_CLI::log('Migrated: ' . (int) ($result['migrated'] ?? 0));
            \WP_CLI::log('Skipped with built-in dates: ' . (int) ($result['skipped_existing_dates'] ?? 0));
            \WP_CLI::log('Skipped without legacy data: ' . (int) ($result['skipped_no_legacy_data'] ?? 0));
            \WP_CLI::log('Schedule syncs: ' . (int) ($result['synced_schedule_meta'] ?? 0));
            \WP_CLI::log('Failures: ' . count((array) ($result['failures'] ?? [])));

            if (!empty($result['failures'])) {
                foreach ((array) $result['failures'] as $failure) {
                    \WP_CLI::warning((string) $failure);
                }

                \WP_CLI::halt(1);
            }

            \WP_CLI::success(!empty($result['dry_run']) ? 'Dry run complete.' : 'Event field migration complete.');
        }
    }

    \WP_CLI::add_command('mz migrate-event-fields', 'MZ_Event_Field_Migration_CLI_Command');
}
