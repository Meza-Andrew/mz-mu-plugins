<?php

/**
 * Plugin Name: MZ Form Migration Tools (MU)
 * Description: Admin-only tools to migrate legacy page section_form data into form posts and clean/reset form records.
 * Author: Meza
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('mzf_mt_form_post_type_ready')) {
    function mzf_mt_form_post_type_ready(): bool
    {
        return post_type_exists('form');
    }
}

if (!function_exists('mzf_mt_map_legacy_section_form')) {
    function mzf_mt_map_legacy_section_form(array $legacy): array
    {
        $headline = trim((string) ($legacy['headline'] ?? ''));
        $subhead = trim((string) ($legacy['subhead'] ?? ''));
        $disclaimer = (string) ($legacy['disclaimer'] ?? '');

        $submit = trim((string) ($legacy['text_submit'] ?? 'Send your message'));
        $sending = trim((string) ($legacy['text_sending'] ?? 'Sending your message'));
        $sent = trim((string) ($legacy['text_sent'] ?? 'Message sent!'));

        $success = (string) ($legacy['message_success'] ?? 'Your message was sent successfully. We will be in touch with you shortly.');
        $error = (string) ($legacy['message_error'] ?? 'Your message failed to send. Please try again.');

        $email_subject = trim((string) ($legacy['email_subject'] ?? 'Thank you for your request!'));
        $email_intro = trim((string) ($legacy['email_intro'] ?? 'Hey'));
        $email_body = (string) ($legacy['email_content'] ?? 'We have received your message and will follow up shortly.');
        $email_salutation = trim((string) ($legacy['email_salutation'] ?? 'Sincerely,'));
        $email_signature = trim((string) ($legacy['email_signature'] ?? ''));
        $email_recipients = trim((string) ($legacy['email_recipients'] ?? ''));

        return [
            'headline' => $headline,
            'subhead' => $subhead,
            'disclaimer' => $disclaimer,
            'button_text' => [
                'submit' => $submit,
                'sending' => $sending,
                'sent' => $sent,
            ],
            'messages' => [
                'success' => $success,
                'error' => $error,
            ],
            'email_user' => [
                'subject' => $email_subject,
                'intro' => $email_intro,
                'body' => $email_body,
                'salutation' => $email_salutation,
                'signature' => $email_signature,
            ],
            'email_admin' => [
                'recipients' => $email_recipients,
            ],
            'legacy' => [
                'text_submit' => $submit,
                'text_sending' => $sending,
                'text_sent' => $sent,
                'message_success' => $success,
                'message_error' => $error,
                'email_subject' => $email_subject,
                'email_intro' => $email_intro,
                'email_content' => $email_body,
                'email_salutation' => $email_salutation,
                'email_signature' => $email_signature,
                'email_recipients' => $email_recipients,
            ],
        ];
    }
}

if (!function_exists('mzf_mt_collect_sources')) {
    function mzf_mt_collect_sources(int $limit = 0, int $source_id = 0): array
    {
        if ($source_id > 0) {
            $post = get_post($source_id);
            if ($post instanceof WP_Post && !in_array($post->post_type, ['form', 'attachment', 'revision'], true)) {
                return [(int) $source_id];
            }
            return [];
        }

        $post_types = get_post_types(['public' => true], 'names');
        unset($post_types['attachment'], $post_types['revision'], $post_types['form']);

        $q = new WP_Query([
            'post_type' => array_values($post_types),
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => $limit > 0 ? $limit : -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'section_form',
                    'compare' => 'EXISTS',
                ],
            ],
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
            'suppress_filters' => true,
        ]);

        return array_map('intval', (array) $q->posts);
    }
}

if (!function_exists('mzf_mt_find_existing_form')) {
    function mzf_mt_find_existing_form(int $source_id): int
    {
        $existing = get_posts([
            'post_type' => 'form',
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_mzf_source_page_id',
                    'value' => (string) $source_id,
                    'compare' => '=',
                ],
            ],
            'no_found_rows' => true,
        ]);

        return !empty($existing) ? (int) $existing[0] : 0;
    }
}

if (!function_exists('mzf_mt_unique_form_slug')) {
    function mzf_mt_unique_form_slug(string $base_slug, int $source_id = 0): string
    {
        $base = sanitize_title($base_slug);
        if ($base === '') {
            $base = 'form-' . max(1, $source_id);
        }

        $candidate = $base;
        $i = 2;
        while (true) {
            $existing = get_page_by_path($candidate, OBJECT, 'form');
            if (!($existing instanceof WP_Post)) {
                return $candidate;
            }
            $candidate = $base . '-' . $i;
            $i++;
            if ($i > 9999) {
                return $base . '-' . time();
            }
        }
    }
}

if (!function_exists('mzf_mt_insert_form_post')) {
    function mzf_mt_insert_form_post(string $title, string $slug)
    {
        global $wpdb;

        $id = wp_insert_post([
            'post_type' => 'form',
            'post_status' => 'draft',
            'post_author' => (int) (get_current_user_id() ?: 1),
            'post_title' => $title,
            'post_name' => $slug,
            'post_content' => ' ',
            'post_excerpt' => ' ',
            'post_parent' => 0,
        ], true, false);

        if (!is_wp_error($id) && (int) $id > 0) {
            return (int) $id;
        }

        // Fallback for environments where wp_insert_post returns 0 despite write success.
        $now = current_time('mysql');
        $now_gmt = get_gmt_from_date($now);
        $author = (int) get_current_user_id();
        if ($author <= 0) {
            $author = 1;
        }

        $inserted = false;
        $post_id = 0;
        for ($i = 0; $i < 25; $i++) {
            $candidate_id = (int) $wpdb->get_var("SELECT COALESCE(MAX(ID), 0) + 1 FROM {$wpdb->posts}");
            if ($candidate_id <= 0) {
                continue;
            }

            $inserted = $wpdb->insert(
                $wpdb->posts,
                [
                    'ID' => $candidate_id,
                    'post_author' => $author,
                    'post_date' => $now,
                    'post_date_gmt' => $now_gmt,
                    'post_content' => ' ',
                    'post_title' => $title,
                    'post_excerpt' => ' ',
                    'post_status' => 'draft',
                    'comment_status' => 'closed',
                    'ping_status' => 'closed',
                    'post_password' => '',
                    'post_name' => $slug,
                    'to_ping' => '',
                    'pinged' => '',
                    'post_modified' => $now,
                    'post_modified_gmt' => $now_gmt,
                    'post_content_filtered' => '',
                    'post_parent' => 0,
                    'guid' => '',
                    'menu_order' => 0,
                    'post_type' => 'form',
                    'post_mime_type' => '',
                    'comment_count' => 0,
                ],
                [
                    '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
                    '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d',
                ]
            );

            if ($inserted !== false) {
                $post_id = $candidate_id;
                break;
            }

            if (stripos((string) $wpdb->last_error, 'Duplicate entry') === false) {
                break;
            }
            usleep(50000);
        }

        if ($inserted === false || $post_id <= 0) {
            return new WP_Error('mzf_mt_insert_failed', is_wp_error($id) ? $id->get_error_message() : (string) ($wpdb->last_error ?: 'Insert failed'));
        }

        $guid = home_url('/?post_type=form&p=' . $post_id);
        $wpdb->update($wpdb->posts, ['guid' => $guid], ['ID' => $post_id], ['%s'], ['%d']);
        clean_post_cache($post_id);

        return $post_id;
    }
}

if (!function_exists('mzf_mt_migrate_section_forms')) {
    function mzf_mt_migrate_section_forms(array $args = []): array
    {
        $apply = !empty($args['apply']);
        $limit = isset($args['limit']) ? max(0, (int) $args['limit']) : 0;
        $source_id = isset($args['source_id']) ? max(0, (int) $args['source_id']) : 0;
        $include_hidden = !empty($args['include_hidden']);

        $rows = [];
        $source_ids = mzf_mt_collect_sources($limit, $source_id);

        foreach ($source_ids as $page_id) {
            $legacy = function_exists('get_field')
                ? get_field('section_form', $page_id)
                : get_post_meta($page_id, 'section_form', true);
            $legacy = is_array($legacy) ? $legacy : [];

            if (empty($legacy)) {
                $rows[] = ['source_id' => $page_id, 'source_slug' => (string) get_post_field('post_name', $page_id), 'status' => 'skipped', 'reason' => 'empty section_form', 'form_id' => 0, 'form_slug' => ''];
                continue;
            }

            $visible = function_exists('get_field')
                ? (bool) get_field('visibility_form', $page_id)
                : (bool) get_post_meta($page_id, 'visibility_form', true);
            if (!$include_hidden && !$visible) {
                $rows[] = ['source_id' => $page_id, 'source_slug' => (string) get_post_field('post_name', $page_id), 'status' => 'skipped', 'reason' => 'visibility_form is false', 'form_id' => 0, 'form_slug' => ''];
                continue;
            }

            $mapped = mzf_mt_map_legacy_section_form($legacy);
            $source_slug = sanitize_title((string) get_post_field('post_name', $page_id));
            $base_slug = $source_slug !== '' ? $source_slug : ('form-' . $page_id);

            $existing_form_id = mzf_mt_find_existing_form($page_id);
            $final_slug = $existing_form_id > 0 ? (string) get_post_field('post_name', $existing_form_id) : mzf_mt_unique_form_slug($base_slug, $page_id);
            $form_title = $mapped['headline'] !== '' ? $mapped['headline'] : (get_the_title($page_id) . ' Form');

            if (!$apply) {
                $rows[] = [
                    'source_id' => $page_id,
                    'source_slug' => $source_slug,
                    'status' => $existing_form_id > 0 ? 'would_update' : 'would_create',
                    'reason' => '',
                    'form_id' => $existing_form_id,
                    'form_slug' => $final_slug,
                ];
                continue;
            }

            if ($existing_form_id > 0) {
                $updated = wp_update_post(['ID' => $existing_form_id, 'post_title' => $form_title], true);
                $form_id = (is_wp_error($updated) || !$updated) ? $existing_form_id : (int) $updated;
            } else {
                $form_id = mzf_mt_insert_form_post($form_title, $final_slug);
            }

            if (is_wp_error($form_id) || !$form_id) {
                global $wpdb;
                $rows[] = [
                    'source_id' => $page_id,
                    'source_slug' => $source_slug,
                    'status' => 'error',
                    'reason' => is_wp_error($form_id) ? $form_id->get_error_message() : ((string) ($wpdb->last_error ?: 'save failed')),
                    'form_id' => 0,
                    'form_slug' => $final_slug,
                ];
                continue;
            }

            $form_id = (int) $form_id;

            if (function_exists('update_field')) {
                update_field('headline', $mapped['headline'], $form_id);
                update_field('subhead', $mapped['subhead'], $form_id);
                update_field('disclaimer', $mapped['disclaimer'], $form_id);
                update_field('button_text', $mapped['button_text'], $form_id);
                update_field('messages', $mapped['messages'], $form_id);
                update_field('email_user', $mapped['email_user'], $form_id);
                update_field('email_admin', $mapped['email_admin'], $form_id);
            }

            foreach ($mapped['legacy'] as $k => $v) {
                update_post_meta($form_id, $k, $v);
            }
            update_post_meta($form_id, '_mzf_source_page_id', (int) $page_id);
            update_post_meta($form_id, '_mzf_source_page_slug', (string) $source_slug);
            update_post_meta($form_id, '_mzf_migrated_at', current_time('mysql'));

            $rows[] = [
                'source_id' => $page_id,
                'source_slug' => $source_slug,
                'status' => $existing_form_id > 0 ? 'updated' : 'created',
                'reason' => '',
                'form_id' => $form_id,
                'form_slug' => (string) get_post_field('post_name', $form_id),
            ];
        }

        return $rows;
    }
}

if (!function_exists('mzf_mt_clear_forms')) {
    function mzf_mt_clear_forms(): array
    {
        global $wpdb;

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'form'");
        $errors = [];

        $queries = [
            "DELETE pm FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = 'form'" => 'postmeta',
            "DELETE tr FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE p.post_type = 'form'" => 'term_relationships',
            "DELETE c FROM {$wpdb->comments} c INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID WHERE p.post_type = 'form'" => 'comments',
            "DELETE cm FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_ID IS NULL" => 'commentmeta',
            "DELETE FROM {$wpdb->posts} WHERE post_type = 'form'" => 'posts',
        ];

        foreach ($queries as $sql => $label) {
            $ok = $wpdb->query($sql);
            if ($ok === false && !empty($wpdb->last_error)) {
                $errors[] = $label . ': ' . $wpdb->last_error;
            }
        }

        $remaining_ids = (array) $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'form'");
        $remaining = count($remaining_ids);

        return [
            'total' => $total,
            'deleted' => max(0, $total - $remaining),
            'failed' => $remaining,
            'remaining_ids' => $remaining_ids,
            'errors' => $errors,
        ];
    }
}

if (!function_exists('mzf_mt_repair_dupe_form_ids')) {
    function mzf_mt_repair_dupe_form_ids(): array
    {
        global $wpdb;

        $dupes = $wpdb->get_results(
            "SELECT ID, COUNT(*) AS c FROM {$wpdb->posts} WHERE post_type='form' GROUP BY ID HAVING COUNT(*) > 1 ORDER BY ID ASC",
            ARRAY_A
        );

        $fixed = [];
        $errors = [];
        foreach ((array) $dupes as $row) {
            $id = (int) ($row['ID'] ?? 0);
            $count = (int) ($row['c'] ?? 0);
            if ($id <= 0 || $count <= 1) {
                continue;
            }
            $to_delete = $count - 1;
            $sql = $wpdb->prepare(
                "DELETE FROM {$wpdb->posts} WHERE ID=%d AND post_type='form' ORDER BY post_modified_gmt ASC, post_date_gmt ASC LIMIT {$to_delete}",
                $id
            );
            $ok = $wpdb->query($sql);
            if ($ok === false) {
                $errors[] = 'ID ' . $id . ': ' . (string) $wpdb->last_error;
            } else {
                $fixed[] = ['id' => $id, 'removed' => (int) $ok];
            }
        }

        $remaining = (array) $wpdb->get_results(
            "SELECT ID, COUNT(*) AS c FROM {$wpdb->posts} WHERE post_type='form' GROUP BY ID HAVING COUNT(*) > 1 ORDER BY ID ASC",
            ARRAY_A
        );

        return ['found' => count((array) $dupes), 'fixed' => $fixed, 'errors' => $errors, 'remaining' => $remaining];
    }
}

if (!function_exists('mzf_mt_print_report')) {
    function mzf_mt_print_report(string $title, array $lines = []): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo $title . "\n";
        foreach ($lines as $line) {
            echo $line . "\n";
        }
    }
}

add_action('admin_init', function () {
    if (!is_admin() || wp_doing_ajax() || !current_user_can('manage_options')) {
        return;
    }
    $action = isset($_GET['mzf_mt_action']) ? sanitize_key((string) $_GET['mzf_mt_action']) : '';
    if ($action === '') {
        return;
    }

    if ($action === 'migrate_section_forms') {
        if (!mzf_mt_form_post_type_ready()) {
            mzf_mt_print_report('MZ Form Migration Tools', ['Form post type is not registered on this site.']);
            exit;
        }

        $args = [
            'apply' => isset($_GET['apply']) && (string) $_GET['apply'] === '1',
            'limit' => isset($_GET['limit']) ? absint($_GET['limit']) : 0,
            'source_id' => isset($_GET['source_id']) ? absint($_GET['source_id']) : 0,
            'include_hidden' => isset($_GET['include_hidden']) && (string) $_GET['include_hidden'] === '1',
        ];

        $rows = mzf_mt_migrate_section_forms($args);
        $counts = [];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? 'unknown');
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        $out = [];
        $out[] = 'Mode: ' . ($args['apply'] ? 'APPLY' : 'DRY RUN');
        $out[] = 'Args: ' . wp_json_encode($args);
        $out[] = 'Total scanned: ' . count($rows);
        $out[] = 'Summary: ' . wp_json_encode($counts);
        $out[] = '';

        foreach ($rows as $row) {
            $out[] = sprintf(
                '[%s] source=%d (%s) -> form=%d (%s)',
                strtoupper((string) $row['status']),
                (int) ($row['source_id'] ?? 0),
                (string) ($row['source_slug'] ?? ''),
                (int) ($row['form_id'] ?? 0),
                (string) ($row['form_slug'] ?? '')
            );
            if (!empty($row['reason'])) {
                $out[] = '  reason: ' . (string) $row['reason'];
            }
        }

        mzf_mt_print_report('Section Form Migration', $out);
        exit;
    }

    if ($action === 'clear_forms') {
        $confirm = isset($_GET['confirm']) && (string) $_GET['confirm'] === '1';
        if (!$confirm) {
            mzf_mt_print_report('MZ Form Migration Tools', [
                'Refusing to clear forms without confirmation.',
                'Run: /wp-admin/?mzf_mt_action=clear_forms&confirm=1',
            ]);
            exit;
        }

        $result = mzf_mt_clear_forms();
        $lines = [
            'Total found: ' . (int) $result['total'],
            'Deleted: ' . (int) $result['deleted'],
            'Failed: ' . (int) $result['failed'],
        ];
        if (!empty($result['errors'])) {
            $lines[] = 'SQL errors: ' . implode(' | ', (array) $result['errors']);
        }
        if (!empty($result['remaining_ids'])) {
            $lines[] = 'Failed IDs: ' . implode(',', (array) $result['remaining_ids']);
        }

        mzf_mt_print_report('Form Post Cleanup', $lines);
        exit;
    }

    if ($action === 'repair_form_dupe_ids') {
        $confirm = isset($_GET['confirm']) && (string) $_GET['confirm'] === '1';
        if (!$confirm) {
            mzf_mt_print_report('MZ Form Migration Tools', [
                'Refusing to run duplicate-ID repair without confirmation.',
                'Run: /wp-admin/?mzf_mt_action=repair_form_dupe_ids&confirm=1',
            ]);
            exit;
        }

        $result = mzf_mt_repair_dupe_form_ids();
        $lines = [
            'Duplicate ID groups found: ' . (int) $result['found'],
            'Groups fixed: ' . count((array) $result['fixed']),
        ];
        foreach ((array) $result['fixed'] as $row) {
            $lines[] = '  ID ' . (int) ($row['id'] ?? 0) . ' removed: ' . (int) ($row['removed'] ?? 0);
        }
        if (!empty($result['errors'])) {
            $lines[] = 'Errors: ' . implode(' | ', (array) $result['errors']);
        }
        $lines[] = 'Remaining duplicate groups: ' . count((array) $result['remaining']);

        mzf_mt_print_report('Form Duplicate ID Repair', $lines);
        exit;
    }

    mzf_mt_print_report('MZ Form Migration Tools', [
        'Unknown action: ' . $action,
        'Valid actions: migrate_section_forms, clear_forms, repair_form_dupe_ids',
    ]);
    exit;
}, 1);
