<?php

/**
 * Internal module for MZ Tools form migration flows.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MZFMT_ACTION')) {
    define('MZFMT_ACTION', 'mz_form_migration_tools');
}

if (!defined('MZFMT_RESULT_USER_META')) {
    define('MZFMT_RESULT_USER_META', 'mz_form_migration_last_result');
}

if (!function_exists('mzf_mt_user_can_access_tool')) {
    function mzf_mt_user_can_access_tool(): bool
    {
        $default_enabled = current_user_can('manage_options');
        return (bool) apply_filters('mzf_mt_admin_tool_enabled', $default_enabled);
    }
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
        $description = trim((string) ($legacy['description'] ?? ''));
        $disclaimer = (string) ($legacy['disclaimer'] ?? '');

        $submit = trim((string) ($legacy['text_submit'] ?? 'Send your message'));
        $sending = trim((string) ($legacy['text_sending'] ?? 'Sending your message'));
        $sent = trim((string) ($legacy['text_sent'] ?? 'Message sent!'));

        $success = (string) ($legacy['message_success'] ?? 'Your message was sent successfully. We will be in touch with you shortly.');
        $error = (string) ($legacy['message_error'] ?? 'Your message failed to send. Please try again.');
        $validation = (string) ($legacy['message_validation'] ?? 'Please review the highlighted fields and try again.');

        $email_subject = trim((string) ($legacy['email_subject'] ?? 'Thank you for your request!'));
        $email_intro = trim((string) ($legacy['email_intro'] ?? 'Hey'));
        $email_body = (string) ($legacy['email_content'] ?? 'We have received your message and will follow up shortly.');
        $email_salutation = trim((string) ($legacy['email_salutation'] ?? 'Sincerely,'));
        $email_signature = trim((string) ($legacy['email_signature'] ?? ''));
        $email_recipients = trim((string) ($legacy['email_recipients'] ?? ''));

        return [
            'section' => [
                'headline' => $headline,
                'subhead' => $subhead,
                'description' => $description,
                'id' => sanitize_title((string) ($legacy['id'] ?? '')),
            ],
            'disclaimer' => $disclaimer,
            'button_text' => [
                'submit' => $submit,
                'sending' => $sending,
                'sent' => $sent,
            ],
            'messages' => [
                'success' => $success,
                'error' => $error,
                'validation' => $validation,
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
                'message_validation' => $validation,
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

if (!function_exists('mzf_mt_source_show_form')) {
    function mzf_mt_source_show_form(int $source_id): bool
    {
        if ($source_id <= 0) {
            return true;
        }

        $show_form = get_post_meta($source_id, 'show_form', true);
        if ($show_form !== '') {
            return (bool) $show_form;
        }

        $legacy_visibility = get_post_meta($source_id, 'visibility_form', true);
        if ($legacy_visibility !== '') {
            return (bool) $legacy_visibility;
        }

        return true;
    }
}

if (!function_exists('mzf_mt_source_form_family')) {
    function mzf_mt_source_form_family(int $source_id, array $legacy = []): string
    {
        $family = apply_filters('mzf_mt_source_form_family', '', $source_id, $legacy);
        $family = sanitize_key((string) $family);

        if ($family !== '') {
            return $family;
        }

        $source_slug = sanitize_key((string) get_post_field('post_name', $source_id));
        return $source_slug !== '' ? $source_slug : 'contact';
    }
}

if (!function_exists('mzf_mt_source_form_slug_base')) {
    function mzf_mt_source_form_slug_base(int $source_id, string $family): string
    {
        $source_slug = sanitize_title((string) get_post_field('post_name', $source_id));
        if ($source_slug === '') {
            $source_slug = 'form-' . max(1, $source_id);
        }

        if ($family === '') {
            return $source_slug;
        }

        if (function_exists('mzf_slug_matches_family') && mzf_slug_matches_family($source_slug, $family)) {
            return $source_slug;
        }

        return sanitize_title($source_slug . '-' . $family);
    }
}

if (!function_exists('mzf_mt_term_object_id')) {
    function mzf_mt_term_object_id(WP_Term $term): string
    {
        return $term->taxonomy . '_' . (int) $term->term_id;
    }
}

if (!function_exists('mzf_mt_term_should_show_form')) {
    function mzf_mt_term_should_show_form(WP_Term $term): bool
    {
        $show_form = get_term_meta($term->term_id, 'show_form', true);
        if ($show_form !== '') {
            return (bool) $show_form;
        }

        $legacy_visibility = get_term_meta($term->term_id, 'visibility_form', true);
        if ($legacy_visibility !== '') {
            return (bool) $legacy_visibility;
        }

        return true;
    }
}

if (!function_exists('mzf_mt_term_form_family')) {
    function mzf_mt_term_form_family(WP_Term $term): string
    {
        $family = '';

        if ($term->taxonomy === 'locality') {
            $family = 'quote';
        } elseif ($term->taxonomy === 'sign_type') {
            $family = 'sign-quote';
        }

        $family = apply_filters('mzf_mt_term_form_family', $family, $term);
        $family = sanitize_key((string) $family);

        return $family !== '' ? $family : 'contact';
    }
}

if (!function_exists('mzf_mt_collect_nested_form_visibility_targets')) {
    function mzf_mt_collect_nested_form_visibility_targets(): array
    {
        $targets = [];

        $localities = get_terms([
            'taxonomy' => 'locality',
            'hide_empty' => false,
        ]);

        if (!is_wp_error($localities)) {
            foreach ($localities as $term) {
                if (!($term instanceof WP_Term) || (int) $term->parent <= 0) {
                    continue;
                }

                $parent = get_term((int) $term->parent, 'locality');

                $targets[] = [
                    'kind' => 'locality',
                    'object_id' => (int) $term->term_id,
                    'label' => (string) $term->name,
                    'parent_label' => ($parent instanceof WP_Term && !is_wp_error($parent)) ? (string) $parent->name : '',
                    'show_form' => get_term_meta((int) $term->term_id, 'show_form', true),
                    'visibility_form' => get_term_meta((int) $term->term_id, 'visibility_form', true),
                ];
            }
        }

        return $targets;
    }
}

if (!function_exists('mzf_mt_source_has_form_configuration')) {
    function mzf_mt_source_has_form_configuration(int $source_id): bool
    {
        if ($source_id <= 0) {
            return false;
        }

        $candidates = [];

        if (function_exists('get_field')) {
            $candidates[] = get_field('section_form_form', $source_id);
            $candidates[] = get_field('section_form', $source_id);
        }

        $candidates[] = get_post_meta($source_id, 'section_form_form', true);
        $candidates[] = get_post_meta($source_id, 'section_form', true);

        $has_meaningful_value = false;

        $scan_value = static function ($value) use (&$has_meaningful_value, &$scan_value): void {
            if ($has_meaningful_value || $value === null || $value === '') {
                return;
            }

            if ($value instanceof WP_Post) {
                if ($value->post_type === 'form') {
                    $has_meaningful_value = true;
                }

                return;
            }

            if (is_numeric($value)) {
                $post = get_post((int) $value);

                if ($post instanceof WP_Post && $post->post_type === 'form') {
                    $has_meaningful_value = true;
                }

                return;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    $scan_value($item);

                    if ($has_meaningful_value) {
                        return;
                    }
                }
            }
        };

        foreach ($candidates as $candidate) {
            $scan_value($candidate);

            if ($has_meaningful_value) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('mzf_mt_collect_post_form_visibility_targets')) {
    function mzf_mt_collect_post_form_visibility_targets(): array
    {
        $targets = [];
        $front_page_id = (int) get_option('page_on_front');

        $post_ids = get_posts([
            'post_type' => ['page', 'post'],
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ]);

        foreach ((array) $post_ids as $post_id) {
            $post_id = (int) $post_id;

            if ($post_id <= 0 || !mzf_mt_source_has_form_configuration($post_id)) {
                continue;
            }

            $post = get_post($post_id);

            if (!($post instanceof WP_Post)) {
                continue;
            }

            $is_front_page = $post->post_type === 'page' && $post_id === $front_page_id;

            if (!$is_front_page && !mzf_mt_source_has_form_configuration($post_id)) {
                continue;
            }

            $targets[] = [
                'kind' => $post->post_type,
                'object_id' => $post_id,
                'label' => (string) get_the_title($post_id),
                'parent_label' => '',
                'show_form' => get_post_meta($post_id, 'show_form', true),
                'visibility_form' => get_post_meta($post_id, 'visibility_form', true),
                'force_off' => $is_front_page,
            ];
        }

        return $targets;
    }
}

if (!function_exists('mzf_mt_disable_nested_form_visibility')) {
    function mzf_mt_disable_nested_form_visibility(array $args = []): array
    {
        $apply = !empty($args['apply']);
        $rows = [];

        foreach (array_merge(
            mzf_mt_collect_nested_form_visibility_targets(),
            mzf_mt_collect_post_form_visibility_targets()
        ) as $target) {
            $kind = (string) ($target['kind'] ?? '');
            $object_id = (int) ($target['object_id'] ?? 0);
            $label = (string) ($target['label'] ?? '');
            $parent_label = (string) ($target['parent_label'] ?? '');
            $old_show_form = (string) ($target['show_form'] ?? '');
            $old_visibility_form = (string) ($target['visibility_form'] ?? '');
            $force_off = !empty($target['force_off']);
            $target_show_form = ($kind === 'locality' || $force_off) ? '0' : '1';
            $target_visibility_form = ($kind === 'locality' || $force_off) ? '0' : '1';
            $already_synced = $old_show_form === $target_show_form && $old_visibility_form === $target_visibility_form;
            $status = $already_synced ? 'unchanged' : 'updated';

            if ($apply && $object_id > 0) {
                if (in_array($kind, ['page', 'post'], true)) {
                    if (function_exists('update_field')) {
                        update_field('show_form', (int) $target_show_form, $object_id);
                    }

                    update_post_meta($object_id, 'show_form', (int) $target_show_form);
                    update_post_meta($object_id, 'visibility_form', (int) $target_visibility_form);
                    update_post_meta($object_id, '_show_form', 'field_69b03fca31afa');
                    clean_post_cache($object_id);
                } elseif ($kind === 'locality') {
                    $term = get_term($object_id, 'locality');

                    if ($term instanceof WP_Term && !is_wp_error($term)) {
                        if (function_exists('update_field')) {
                            update_field('show_form', 0, mzf_mt_term_object_id($term));
                        }

                        update_term_meta($object_id, 'show_form', 0);
                        update_term_meta($object_id, 'visibility_form', 0);
                        update_term_meta($object_id, '_show_form', 'field_69b03fca31afa');
                        clean_term_cache($object_id, 'locality');
                    }
                }
            }

            $rows[] = [
                'status' => $status,
                'kind' => $kind,
                'object_id' => $object_id,
                'label' => $label,
                'parent_label' => $parent_label,
                'old_show_form' => $old_show_form,
                'old_visibility_form' => $old_visibility_form,
                'new_show_form' => $target_show_form,
                'new_visibility_form' => $target_visibility_form,
            ];
        }

        return $rows;
    }
}

if (!function_exists('mzf_mt_find_form_by_family')) {
    function mzf_mt_find_form_by_family(string $family): int
    {
        $family = sanitize_title($family);
        if ($family === '' || !mzf_mt_form_post_type_ready()) {
            return 0;
        }

        $by_slug = get_page_by_path($family, OBJECT, 'form');
        if ($by_slug instanceof WP_Post) {
            return (int) $by_slug->ID;
        }

        $by_family = get_posts([
            'post_type' => 'form',
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_mzf_form_family',
                    'value' => $family,
                    'compare' => '=',
                ],
            ],
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
            'suppress_filters' => true,
        ]);
        if (!empty($by_family)) {
            return (int) $by_family[0];
        }

        if (function_exists('mzf_slug_matches_family')) {
            $forms = get_posts([
                'post_type' => 'form',
                'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
                'posts_per_page' => -1,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
                'no_found_rows' => true,
                'suppress_filters' => true,
            ]);

            foreach ((array) $forms as $form_id) {
                $form_slug = sanitize_title((string) get_post_field('post_name', (int) $form_id));
                if ($form_slug !== '' && mzf_slug_matches_family($form_slug, $family)) {
                    return (int) $form_id;
                }
            }
        }

        return 0;
    }
}

if (!function_exists('mzf_mt_normalize_relationship_value_to_id')) {
    function mzf_mt_normalize_relationship_value_to_id($value): int
    {
        if (is_array($value)) {
            if ($value === []) {
                return 0;
            }

            $first = reset($value);
            if ($first instanceof WP_Post) {
                return (int) $first->ID;
            }

            return (int) $first;
        }

        if ($value instanceof WP_Post) {
            return (int) $value->ID;
        }

        return (int) $value;
    }
}

if (!function_exists('mzf_mt_backfill_term_section_form_relationship_once')) {
    function mzf_mt_backfill_term_section_form_relationship_once(): void
    {
        $version = '2026-05-04-term-section-form-relationship-v2';
        $option_name = 'mzf_mt_term_section_form_relationship_version';

        if ((string) get_option($option_name, '') === $version) {
            return;
        }

        if (!taxonomy_exists('locality') && !taxonomy_exists('sign_type')) {
            return;
        }

        if (!mzf_mt_form_post_type_ready()) {
            return;
        }

        $did_update = false;

        foreach (['locality', 'sign_type'] as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
            ]);

            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                if (!($term instanceof WP_Term) || !mzf_mt_term_should_show_form($term)) {
                    continue;
                }

                $existing_form_id = mzf_mt_normalize_relationship_value_to_id(get_term_meta($term->term_id, 'section_form_form', true));
                $section_form = get_term_meta($term->term_id, 'section_form', true);
                if (!is_array($section_form)) {
                    $section_form = [];
                }

                if ($existing_form_id <= 0) {
                    $existing_form_id = mzf_mt_normalize_relationship_value_to_id($section_form['form'] ?? 0);
                }

                if ($existing_form_id > 0) {
                    continue;
                }

                $family = mzf_mt_term_form_family($term);
                $form_id = mzf_mt_find_form_by_family($family);
                if ($form_id <= 0) {
                    continue;
                }

                $section_form['form'] = $form_id;
                $term_object_id = mzf_mt_term_object_id($term);

                if (function_exists('update_field')) {
                    update_field('field_69b03fca31afa', 1, $term_object_id);
                    update_field('field_69b03fdc31afb', $section_form, $term_object_id);
                }

                update_term_meta($term->term_id, 'show_form', 1);
                update_term_meta($term->term_id, 'visibility_form', 1);
                update_term_meta($term->term_id, 'section_form_form', $form_id);
                update_term_meta($term->term_id, 'section_form', $section_form);
                update_term_meta($term->term_id, '_show_form', 'field_69b03fca31afa');
                update_term_meta($term->term_id, '_section_form', 'field_69b03fdc31afb');
                update_term_meta($term->term_id, '_section_form_form', 'field_697ffe37abb58');

                $did_update = true;
            }
        }

        if ($did_update) {
            update_option($option_name, $version, false);
        }
    }
}

if (!function_exists('mzf_mt_sign_type_reviews_default_headline')) {
    function mzf_mt_sign_type_reviews_default_headline(WP_Term $term): string
    {
        $name = trim((string) $term->name);
        if ($name === '') {
            $name = trim(str_replace(['-', '_'], ' ', (string) $term->slug));
        }

        return $name !== '' ? sprintf('What %s clients are saying', $name) : 'What our clients are saying';
    }
}

if (!function_exists('mzf_mt_sign_type_reviews_default_subhead')) {
    function mzf_mt_sign_type_reviews_default_subhead(): string
    {
        return 'Real reviews from clients who trust our team for reliable service, quality craftsmanship, and clear communication.';
    }
}

if (!function_exists('mzf_mt_backfill_sign_type_section_editor_defaults_once')) {
    function mzf_mt_backfill_sign_type_section_editor_defaults_once(): void
    {
        $version = '2026-05-04-sign-type-section-editor-defaults-v1';
        $option_name = 'mzf_mt_sign_type_section_editor_defaults_version';

        if ((string) get_option($option_name, '') === $version) {
            return;
        }

        if (!taxonomy_exists('sign_type')) {
            return;
        }

        $terms = get_terms([
            'taxonomy' => 'sign_type',
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return;
        }

        $did_update = false;

        foreach ($terms as $term) {
            if (!($term instanceof WP_Term)) {
                continue;
            }

            $term_object_id = mzf_mt_term_object_id($term);

            $section_form = get_term_meta($term->term_id, 'section_form', true);
            $section_form = is_array($section_form) ? $section_form : [];
            $form_id = mzf_mt_normalize_relationship_value_to_id($section_form['form'] ?? 0);
            if ($form_id <= 0) {
                $form_id = mzf_mt_normalize_relationship_value_to_id(get_term_meta($term->term_id, 'section_form_form', true));
            }

            $form_post = $form_id > 0 ? get_post($form_id) : null;
            $existing_form_headline = trim((string) get_term_meta($term->term_id, 'section_form_headline', true));

            if ($existing_form_headline === '' && $form_post instanceof WP_Post) {
                $headline = trim((string) $form_post->post_title);
                if ($headline !== '') {
                    $section_form['headline'] = $headline;
                    update_term_meta($term->term_id, 'section_form_headline', $headline);
                    update_term_meta($term->term_id, '_section_form_headline', 'field_69b03fe631afc');
                    if (function_exists('update_field')) {
                        update_field('field_69b03fdc31afb', $section_form, $term_object_id);
                    } else {
                        update_term_meta($term->term_id, 'section_form', $section_form);
                        update_term_meta($term->term_id, '_section_form', 'field_69b03fdc31afb');
                    }
                    $did_update = true;
                }
            }

            $reviews_visible = (string) get_term_meta($term->term_id, 'show_list-reviews', true) === '1'
                || (string) get_term_meta($term->term_id, 'visibility_list-reviews', true) === '1';

            if (!$reviews_visible) {
                continue;
            }

            $existing_reviews_headline = trim((string) get_term_meta($term->term_id, 'section_list-reviews_headline', true));
            $existing_reviews_subhead = trim((string) get_term_meta($term->term_id, 'section_list-reviews_subhead', true));
            $section_reviews = get_term_meta($term->term_id, 'section_list-reviews', true);
            $section_reviews = is_array($section_reviews) ? $section_reviews : [];
            $section_reviews_changed = false;

            if ($existing_reviews_headline === '') {
                $headline = mzf_mt_sign_type_reviews_default_headline($term);
                if ($headline !== '') {
                    $section_reviews['headline'] = $headline;
                    update_term_meta($term->term_id, 'section_list-reviews_headline', $headline);
                    update_term_meta($term->term_id, '_section_list-reviews_headline', 'field_meza_list_reviews_headline');
                    $section_reviews_changed = true;
                }
            }

            if ($existing_reviews_subhead === '') {
                $subhead = mzf_mt_sign_type_reviews_default_subhead();
                $section_reviews['subhead'] = $subhead;
                update_term_meta($term->term_id, 'section_list-reviews_subhead', $subhead);
                update_term_meta($term->term_id, '_section_list-reviews_subhead', 'field_meza_list_reviews_subhead');
                $section_reviews_changed = true;
            }

            if ($section_reviews_changed) {
                if (function_exists('update_field')) {
                    update_field('field_meza_section_list_reviews', $section_reviews, $term_object_id);
                } else {
                    update_term_meta($term->term_id, 'section_list-reviews', $section_reviews);
                    update_term_meta($term->term_id, '_section_list-reviews', 'field_meza_section_list_reviews');
                }
                $did_update = true;
            }
        }

        if ($did_update) {
            update_option($option_name, $version, false);
        }
    }
}

if (!function_exists('mzf_mt_page_reviews_default_headline')) {
    function mzf_mt_page_reviews_default_headline(int $post_id): string
    {
        $slug = sanitize_key((string) get_post_field('post_name', $post_id));
        if ($slug === 'home') {
            return 'What our clients are saying';
        }

        $title = trim((string) get_the_title($post_id));
        return $title !== '' ? sprintf('What our %s clients are saying', $title) : 'What our clients are saying';
    }
}

if (!function_exists('mzf_mt_page_reviews_default_subhead')) {
    function mzf_mt_page_reviews_default_subhead(): string
    {
        return 'Real reviews from clients who trust our team for reliable service, quality craftsmanship, and clear communication.';
    }
}

if (!function_exists('mzf_mt_backfill_page_section_editor_defaults_once')) {
    function mzf_mt_backfill_page_section_editor_defaults_once(): void
    {
        $version = '2026-05-04-page-section-editor-defaults-v1';
        $option_name = 'mzf_mt_page_section_editor_defaults_version';

        if ((string) get_option($option_name, '') === $version) {
            return;
        }

        $page_ids = get_posts([
            'post_type' => 'page',
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'suppress_filters' => true,
            'no_found_rows' => true,
        ]);

        if (empty($page_ids)) {
            return;
        }

        $did_update = false;

        foreach ((array) $page_ids as $page_id) {
            $page_id = (int) $page_id;
            if ($page_id <= 0) {
                continue;
            }

            $form_visible = (string) get_post_meta($page_id, 'show_form', true) === '1'
                || (string) get_post_meta($page_id, 'visibility_form', true) === '1';

            if ($form_visible) {
                $section_form = get_post_meta($page_id, 'section_form', true);
                $section_form = is_array($section_form) ? $section_form : [];

                $form_id = mzf_mt_normalize_relationship_value_to_id($section_form['form'] ?? 0);
                if ($form_id <= 0) {
                    $form_id = mzf_mt_normalize_relationship_value_to_id(get_post_meta($page_id, 'section_form_form', true));
                }

                if ($form_id <= 0) {
                    $family = mzf_mt_source_form_family($page_id, []);
                    $form_id = mzf_mt_find_form_by_family($family);
                }

                if ($form_id > 0) {
                    if (mzf_mt_normalize_relationship_value_to_id(get_post_meta($page_id, 'section_form_form', true)) <= 0) {
                        update_post_meta($page_id, 'section_form_form', $form_id);
                        update_post_meta($page_id, '_section_form_form', 'field_697ffe37abb58');
                        $did_update = true;
                    }

                    if (mzf_mt_normalize_relationship_value_to_id($section_form['form'] ?? 0) <= 0) {
                        $section_form['form'] = $form_id;
                        update_post_meta($page_id, 'section_form', $section_form);
                        update_post_meta($page_id, '_section_form', 'field_69b03fdc31afb');
                        $did_update = true;
                    }

                    $existing_headline = trim((string) get_post_meta($page_id, 'section_form_headline', true));
                    if ($existing_headline === '') {
                        $form_post = get_post($form_id);
                        $headline = $form_post instanceof WP_Post ? trim((string) $form_post->post_title) : '';
                        if ($headline !== '') {
                            update_post_meta($page_id, 'section_form_headline', $headline);
                            update_post_meta($page_id, '_section_form_headline', 'field_69b03fe631afc');
                            $section_form['headline'] = $headline;
                            update_post_meta($page_id, 'section_form', $section_form);
                            update_post_meta($page_id, '_section_form', 'field_69b03fdc31afb');
                            $did_update = true;
                        }
                    }
                }
            }

            $reviews_visible = (string) get_post_meta($page_id, 'show_list-reviews', true) === '1'
                || (string) get_post_meta($page_id, 'visibility_list-reviews', true) === '1';

            if (!$reviews_visible) {
                continue;
            }

            $section_reviews = get_post_meta($page_id, 'section_list-reviews', true);
            $section_reviews = is_array($section_reviews) ? $section_reviews : [];
            $section_reviews_changed = false;

            $existing_reviews_headline = trim((string) get_post_meta($page_id, 'section_list-reviews_headline', true));
            if ($existing_reviews_headline === '') {
                $headline = mzf_mt_page_reviews_default_headline($page_id);
                if ($headline !== '') {
                    update_post_meta($page_id, 'section_list-reviews_headline', $headline);
                    update_post_meta($page_id, '_section_list-reviews_headline', 'field_meza_list_reviews_headline');
                    $section_reviews['headline'] = $headline;
                    $section_reviews_changed = true;
                }
            }

            $existing_reviews_subhead = trim((string) get_post_meta($page_id, 'section_list-reviews_subhead', true));
            if ($existing_reviews_subhead === '') {
                $subhead = mzf_mt_page_reviews_default_subhead();
                update_post_meta($page_id, 'section_list-reviews_subhead', $subhead);
                update_post_meta($page_id, '_section_list-reviews_subhead', 'field_meza_list_reviews_subhead');
                $section_reviews['subhead'] = $subhead;
                $section_reviews_changed = true;
            }

            if ($section_reviews_changed) {
                update_post_meta($page_id, 'section_list-reviews', $section_reviews);
                update_post_meta($page_id, '_section_list-reviews', 'field_meza_section_list_reviews');
                $did_update = true;
            }
        }

        if ($did_update) {
            update_option($option_name, $version, false);
        }
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

            $linked_form = $legacy['form'] ?? null;
            $linked_form_id = 0;
            if ($linked_form instanceof WP_Post) {
                $linked_form_id = $linked_form->post_type === 'form' ? (int) $linked_form->ID : 0;
            } elseif (is_numeric($linked_form)) {
                $linked_form_id = get_post_type((int) $linked_form) === 'form' ? (int) $linked_form : 0;
            } elseif (is_array($linked_form)) {
                $linked_form_candidate = (int) ($linked_form['ID'] ?? $linked_form['id'] ?? reset($linked_form) ?: 0);
                $linked_form_id = $linked_form_candidate > 0 && get_post_type($linked_form_candidate) === 'form'
                    ? $linked_form_candidate
                    : 0;
            }

            $headline = trim((string) ($legacy['headline'] ?? ''));
            if ($linked_form_id > 0 && $headline === '') {
                $rows[] = [
                    'source_id' => $page_id,
                    'source_slug' => (string) get_post_field('post_name', $page_id),
                    'status' => 'skipped',
                    'reason' => 'already linked to form post',
                    'form_id' => $linked_form_id,
                    'form_slug' => (string) get_post_field('post_name', $linked_form_id),
                ];
                continue;
            }

            if ($headline === '') {
                $rows[] = ['source_id' => $page_id, 'source_slug' => (string) get_post_field('post_name', $page_id), 'status' => 'skipped', 'reason' => 'section_form.headline is empty', 'form_id' => 0, 'form_slug' => ''];
                continue;
            }

            $mapped = mzf_mt_map_legacy_section_form($legacy);
            $source_slug = sanitize_title((string) get_post_field('post_name', $page_id));
            $family = mzf_mt_source_form_family($page_id, $legacy);
            $base_slug = mzf_mt_source_form_slug_base($page_id, $family);
            $show_form = mzf_mt_source_show_form($page_id);

            $existing_form_id = mzf_mt_find_existing_form($page_id);
            $final_slug = $existing_form_id > 0 ? (string) get_post_field('post_name', $existing_form_id) : mzf_mt_unique_form_slug($base_slug, $page_id);
            $form_title = $mapped['section']['headline'] !== '' ? $mapped['section']['headline'] : (get_the_title($page_id) . ' Form');

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
            update_post_meta($form_id, '_mzf_form_family', (string) $family);
            update_post_meta($form_id, '_mzf_migrated_at', current_time('mysql'));

            $section_payload = [
                'headline' => (string) ($mapped['section']['headline'] ?? ''),
                'subhead' => (string) ($mapped['section']['subhead'] ?? ''),
                'description' => (string) ($mapped['section']['description'] ?? ''),
                'form' => $form_id,
                'id' => (string) ($mapped['section']['id'] ?? ''),
            ];

            if (function_exists('update_field')) {
                update_field('show_form', $show_form ? 1 : 0, $page_id);
                update_field('section_form', $section_payload, $page_id);
            }

            update_post_meta($page_id, 'show_form', $show_form ? 1 : 0);
            update_post_meta($page_id, 'section_form_form', $form_id);
            update_post_meta($page_id, 'section_form', $section_payload);
            update_post_meta($page_id, 'visibility_form', $show_form ? 1 : 0);

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

if (!function_exists('mzf_mt_default_sync_json_path')) {
    function mzf_mt_default_sync_json_path(): string
    {
        return __DIR__ . '/mz-form-sync.json';
    }
}

if (!function_exists('mzf_mt_is_volatile_meta_key')) {
    function mzf_mt_is_volatile_meta_key(string $meta_key): bool
    {
        return in_array($meta_key, ['_edit_lock', '_edit_last'], true);
    }
}

if (!function_exists('mzf_mt_export_forms_payload')) {
    function mzf_mt_export_forms_payload(): array
    {
        $ids = get_posts([
            'post_type' => 'form',
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
            'suppress_filters' => true,
        ]);

        $forms = [];
        foreach ((array) $ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }

            $meta = [];
            foreach ((array) get_post_meta($id) as $meta_key => $meta_values) {
                $meta_key = (string) $meta_key;
                if ($meta_key === '' || mzf_mt_is_volatile_meta_key($meta_key)) {
                    continue;
                }
                $meta[$meta_key] = array_values(array_map('strval', (array) $meta_values));
            }

            $forms[] = [
                'id' => $id,
                'slug' => (string) get_post_field('post_name', $id),
                'title' => (string) get_the_title($id),
                'status' => (string) get_post_status($id),
                'content' => (string) get_post_field('post_content', $id),
                'excerpt' => (string) get_post_field('post_excerpt', $id),
                'meta' => $meta,
            ];
        }

        return [
            'schema' => 'mzf_form_sync_v1',
            'generated_at' => gmdate('c'),
            'site' => (string) home_url('/'),
            'forms' => $forms,
        ];
    }
}

if (!function_exists('mzf_mt_read_forms_payload_from_json')) {
    function mzf_mt_read_forms_payload_from_json(string $path): array
    {
        if ($path === '') {
            $path = mzf_mt_default_sync_json_path();
        }

        if (!is_readable($path)) {
            return [new WP_Error('mzf_mt_missing_json', 'JSON file is not readable: ' . $path), $path];
        }

        $json = file_get_contents($path);
        if ($json === false || trim($json) === '') {
            return [new WP_Error('mzf_mt_empty_json', 'JSON file is empty: ' . $path), $path];
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return [new WP_Error('mzf_mt_bad_json', 'Invalid JSON in file: ' . $path), $path];
        }

        if (($payload['schema'] ?? '') !== 'mzf_form_sync_v1') {
            return [new WP_Error('mzf_mt_bad_schema', 'Unsupported schema. Expected mzf_form_sync_v1.'), $path];
        }

        return [$payload, $path];
    }
}

if (!function_exists('mzf_mt_import_forms_payload')) {
    function mzf_mt_import_forms_payload(array $payload, bool $replace_meta = false): array
    {
        $rows = [];
        $forms = (array) ($payload['forms'] ?? []);

        foreach ($forms as $entry) {
            $entry = is_array($entry) ? $entry : [];
            $slug = sanitize_title((string) ($entry['slug'] ?? ''));
            $title = trim((string) ($entry['title'] ?? ''));
            $status = sanitize_key((string) ($entry['status'] ?? 'draft'));
            $content = (string) ($entry['content'] ?? '');
            $excerpt = (string) ($entry['excerpt'] ?? '');
            $meta = is_array($entry['meta'] ?? null) ? (array) $entry['meta'] : [];

            if ($slug === '') {
                $rows[] = ['status' => 'skipped', 'slug' => '', 'form_id' => 0, 'reason' => 'Missing slug'];
                continue;
            }
            if ($title === '') {
                $title = ucwords(str_replace('-', ' ', $slug));
            }
            if (!in_array($status, ['publish', 'draft', 'pending', 'private', 'future'], true)) {
                $status = 'draft';
            }

            $existing = get_page_by_path($slug, OBJECT, 'form');
            $form_id = 0;
            $state = 'created';

            if ($existing instanceof WP_Post) {
                $form_id = (int) $existing->ID;
                $state = 'updated';
            } else {
                $inserted = mzf_mt_insert_form_post($title, $slug);
                if (is_wp_error($inserted) || !$inserted) {
                    $rows[] = [
                        'status' => 'error',
                        'slug' => $slug,
                        'form_id' => 0,
                        'reason' => is_wp_error($inserted) ? $inserted->get_error_message() : 'Unable to create form post',
                    ];
                    continue;
                }
                $form_id = (int) $inserted;
            }

            $post_update = wp_update_post([
                'ID' => $form_id,
                'post_title' => $title,
                'post_status' => $status,
                'post_content' => $content,
                'post_excerpt' => $excerpt,
            ], true);
            if (is_wp_error($post_update)) {
                $rows[] = [
                    'status' => 'error',
                    'slug' => $slug,
                    'form_id' => $form_id,
                    'reason' => $post_update->get_error_message(),
                ];
                continue;
            }

            if ($replace_meta) {
                foreach ((array) get_post_meta($form_id) as $meta_key => $values) {
                    $meta_key = (string) $meta_key;
                    if ($meta_key === '' || mzf_mt_is_volatile_meta_key($meta_key)) {
                        continue;
                    }
                    delete_post_meta($form_id, $meta_key);
                }
            }

            foreach ($meta as $meta_key => $meta_values) {
                $meta_key = (string) $meta_key;
                if ($meta_key === '' || mzf_mt_is_volatile_meta_key($meta_key)) {
                    continue;
                }

                delete_post_meta($form_id, $meta_key);
                foreach ((array) $meta_values as $meta_value) {
                    add_post_meta($form_id, $meta_key, $meta_value);
                }
            }

            $rows[] = ['status' => $state, 'slug' => $slug, 'form_id' => $form_id, 'reason' => ''];
        }

        return $rows;
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

if (!function_exists('mzf_mt_store_result')) {
    function mzf_mt_store_result(array $result): void
    {
        $result['recorded_at'] = current_time('mysql');
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            update_user_meta($user_id, MZFMT_RESULT_USER_META, $result);
        }
    }
}

if (!function_exists('mzf_mt_get_result')) {
    function mzf_mt_get_result(): array
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return [];
        }

        $result = get_user_meta($user_id, MZFMT_RESULT_USER_META, true);
        return is_array($result) ? $result : [];
    }
}

if (!function_exists('mzf_mt_get_redirect_url')) {
    function mzf_mt_get_redirect_url(): string
    {
        if (defined('MZ_PTM_PAGE_SLUG')) {
            return add_query_arg('tab', 'form', admin_url('tools.php?page=' . MZ_PTM_PAGE_SLUG));
        }

        return admin_url('tools.php');
    }
}

if (!function_exists('mzf_mt_execute_action')) {
    function mzf_mt_execute_action(string $action, array $args = []): array
    {
        if ($action === 'migrate_section_forms') {
            if (!mzf_mt_form_post_type_ready()) {
                return [
                    'action' => $action,
                    'title' => 'MZ Form Migration Tools',
                    'lines' => ['Form post type is not registered on this site.'],
                ];
            }

            $run_args = [
                'apply' => !empty($args['apply']),
                'limit' => isset($args['limit']) ? absint((int) $args['limit']) : 0,
                'source_id' => isset($args['source_id']) ? absint((int) $args['source_id']) : 0,
                'include_hidden' => !empty($args['include_hidden']),
            ];

            $rows = mzf_mt_migrate_section_forms($run_args);
            $counts = [];
            foreach ($rows as $row) {
                $status = (string) ($row['status'] ?? 'unknown');
                $counts[$status] = ($counts[$status] ?? 0) + 1;
            }

            $lines = [];
            $lines[] = 'Mode: ' . ($run_args['apply'] ? 'APPLY' : 'DRY RUN');
            $lines[] = 'Args: ' . wp_json_encode($run_args);
            $lines[] = 'Total scanned: ' . count($rows);
            $lines[] = 'Summary: ' . wp_json_encode($counts);
            $lines[] = '';

            foreach ($rows as $row) {
                $lines[] = sprintf(
                    '[%s] source=%d (%s) -> form=%d (%s)',
                    strtoupper((string) ($row['status'] ?? 'unknown')),
                    (int) ($row['source_id'] ?? 0),
                    (string) ($row['source_slug'] ?? ''),
                    (int) ($row['form_id'] ?? 0),
                    (string) ($row['form_slug'] ?? '')
                );
                if (!empty($row['reason'])) {
                    $lines[] = '  reason: ' . (string) $row['reason'];
                }
            }

            return [
                'action' => $action,
                'title' => 'Section Form Migration',
                'lines' => $lines,
                'apply' => $run_args['apply'],
                'args' => $run_args,
                'counts' => $counts,
                'total_scanned' => count($rows),
            ];
        }

        if ($action === 'clear_forms') {
            if (empty($args['confirm'])) {
                return [
                    'action' => $action,
                    'title' => 'MZ Form Migration Tools',
                    'lines' => [
                        'Refusing to clear forms without confirmation.',
                        'Re-run with confirmation enabled.',
                    ],
                ];
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

            return [
                'action' => $action,
                'title' => 'Form Post Cleanup',
                'lines' => $lines,
                'apply' => true,
                'total' => (int) $result['total'],
                'deleted' => (int) $result['deleted'],
                'failed' => (int) $result['failed'],
                'errors' => (array) ($result['errors'] ?? []),
            ];
        }

        if ($action === 'repair_form_dupe_ids') {
            if (empty($args['confirm'])) {
                return [
                    'action' => $action,
                    'title' => 'MZ Form Migration Tools',
                    'lines' => [
                        'Refusing to run duplicate-ID repair without confirmation.',
                        'Re-run with confirmation enabled.',
                    ],
                ];
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

            return [
                'action' => $action,
                'title' => 'Form Duplicate ID Repair',
                'lines' => $lines,
                'apply' => true,
                'found' => (int) $result['found'],
                'fixed' => (array) ($result['fixed'] ?? []),
                'remaining' => (array) ($result['remaining'] ?? []),
                'errors' => (array) ($result['errors'] ?? []),
            ];
        }

        if ($action === 'export_forms_json') {
            $payload = mzf_mt_export_forms_payload();
            $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                return [
                    'action' => $action,
                    'title' => 'Form JSON Export',
                    'lines' => ['Failed to encode JSON payload.'],
                ];
            }

            return [
                'action' => $action,
                'title' => 'Form JSON Export',
                'download' => true,
                'content_type' => 'application/json; charset=utf-8',
                'filename' => 'mzf-form-sync.json',
                'body' => $json,
            ];
        }

        if ($action === 'import_forms_json') {
            if (empty($args['confirm'])) {
                return [
                    'action' => $action,
                    'title' => 'MZ Form Migration Tools',
                    'lines' => [
                        'Refusing JSON import without confirmation.',
                        'Default path: ' . mzf_mt_default_sync_json_path(),
                    ],
                ];
            }

            $path = isset($args['path']) ? wp_unslash((string) $args['path']) : '';
            $replace_meta = !empty($args['replace_meta']);
            [$payload_or_error, $resolved_path] = mzf_mt_read_forms_payload_from_json($path);
            if (is_wp_error($payload_or_error)) {
                return [
                    'action' => $action,
                    'title' => 'Form JSON Import',
                    'lines' => [
                        'Error: ' . $payload_or_error->get_error_message(),
                        'Path: ' . $resolved_path,
                    ],
                ];
            }

            $rows = mzf_mt_import_forms_payload($payload_or_error, $replace_meta);
            $counts = [];
            foreach ($rows as $row) {
                $status = (string) ($row['status'] ?? 'unknown');
                $counts[$status] = ($counts[$status] ?? 0) + 1;
            }

            $lines = [];
            $lines[] = 'Path: ' . $resolved_path;
            $lines[] = 'Replace meta mode: ' . ($replace_meta ? 'ON' : 'OFF');
            $lines[] = 'Summary: ' . wp_json_encode($counts);
            $lines[] = '';
            foreach ($rows as $row) {
                $lines[] = sprintf(
                    '[%s] slug=%s -> form=%d',
                    strtoupper((string) ($row['status'] ?? 'unknown')),
                    (string) ($row['slug'] ?? ''),
                    (int) ($row['form_id'] ?? 0)
                );
                if (!empty($row['reason'])) {
                    $lines[] = '  reason: ' . (string) $row['reason'];
                }
            }

            return [
                'action' => $action,
                'title' => 'Form JSON Import',
                'lines' => $lines,
                'apply' => true,
                'path' => $resolved_path,
                'replace_meta' => $replace_meta,
                'counts' => $counts,
            ];
        }

        if ($action === 'disable_nested_form_visibility') {
            $run_args = [
                'apply' => !empty($args['apply']),
            ];

            $rows = mzf_mt_disable_nested_form_visibility($run_args);
            $counts = [];

            foreach ($rows as $row) {
                $status = (string) ($row['status'] ?? 'unknown');
                $counts[$status] = ($counts[$status] ?? 0) + 1;
            }

            $lines = [];
            $lines[] = 'Mode: ' . ($run_args['apply'] ? 'APPLY' : 'DRY RUN');
            $lines[] = 'Targets scanned: ' . count($rows);
            $lines[] = 'Summary: ' . wp_json_encode($counts);
            $lines[] = '';

            foreach ($rows as $row) {
                $lines[] = sprintf(
                    '[%s] %s:%d | parent=%s | label=%s | show_form:%s->%s | visibility_form:%s->%s',
                    strtoupper((string) ($row['status'] ?? 'unknown')),
                    (string) ($row['kind'] ?? ''),
                    (int) ($row['object_id'] ?? 0),
                    (string) ($row['parent_label'] ?? ''),
                    (string) ($row['label'] ?? ''),
                    (string) ($row['old_show_form'] ?? ''),
                    (string) ($row['new_show_form'] ?? ''),
                    (string) ($row['old_visibility_form'] ?? ''),
                    (string) ($row['new_visibility_form'] ?? '')
                );
            }

            return [
                'action' => $action,
                'title' => 'Form Visibility Sync',
                'lines' => $lines,
                'apply' => $run_args['apply'],
                'counts' => $counts,
                'total_scanned' => count($rows),
            ];
        }

        return [
            'action' => $action,
            'title' => 'MZ Form Migration Tools',
            'lines' => [
                'Unknown action: ' . $action,
                'Valid actions: migrate_section_forms, clear_forms, repair_form_dupe_ids, export_forms_json, import_forms_json, disable_nested_form_visibility',
            ],
            'apply' => false,
        ];
    }
}

if (!function_exists('mzf_mt_output_download_result')) {
    function mzf_mt_output_download_result(array $result): void
    {
        if (!headers_sent()) {
            header('Content-Type: ' . (string) ($result['content_type'] ?? 'text/plain; charset=utf-8'));
            header('Content-Disposition: attachment; filename="' . (string) ($result['filename'] ?? 'download.txt') . '"');
        }

        echo (string) ($result['body'] ?? '');
    }
}

if (!function_exists('mzf_mt_result_heading')) {
    function mzf_mt_result_heading(array $result): string
    {
        $action = (string) ($result['action'] ?? '');
        $apply = !empty($result['apply']);

        if ($action === 'migrate_section_forms') {
            return $apply ? 'Last migration' : 'Last dry run';
        }

        return 'Last action';
    }
}

if (!function_exists('mzf_mt_result_summary_lines')) {
    function mzf_mt_result_summary_lines(array $result): array
    {
        $action = (string) ($result['action'] ?? '');
        $summary = [];

        if ($action === 'migrate_section_forms') {
            $counts = is_array($result['counts'] ?? null) ? (array) $result['counts'] : [];
            $summary[] = sprintf(
                'Scanned: %d. Created: %d. Updated: %d. Skipped: %d. Errors: %d.',
                (int) ($result['total_scanned'] ?? 0),
                (int) ($counts['created'] ?? 0),
                (int) ($counts['updated'] ?? 0),
                (int) ($counts['skipped'] ?? 0),
                (int) ($counts['error'] ?? 0)
            );
            if (!empty($result['args'])) {
                $summary[] = 'Args: ' . wp_json_encode((array) $result['args']);
            }
            return $summary;
        }

        if ($action === 'clear_forms') {
            $summary[] = sprintf(
                'Total found: %d. Deleted: %d. Failed: %d.',
                (int) ($result['total'] ?? 0),
                (int) ($result['deleted'] ?? 0),
                (int) ($result['failed'] ?? 0)
            );
            return $summary;
        }

        if ($action === 'repair_form_dupe_ids') {
            $summary[] = sprintf(
                'Duplicate groups found: %d. Groups fixed: %d. Remaining duplicate groups: %d.',
                (int) ($result['found'] ?? 0),
                count((array) ($result['fixed'] ?? [])),
                count((array) ($result['remaining'] ?? []))
            );
            return $summary;
        }

        if ($action === 'import_forms_json') {
            $counts = is_array($result['counts'] ?? null) ? (array) $result['counts'] : [];
            $summary[] = 'Path: ' . (string) ($result['path'] ?? '');
            $summary[] = sprintf(
                'Created: %d. Updated: %d. Skipped: %d. Errors: %d.',
                (int) ($counts['created'] ?? 0),
                (int) ($counts['updated'] ?? 0),
                (int) ($counts['skipped'] ?? 0),
                (int) ($counts['error'] ?? 0)
            );
            $summary[] = 'Replace meta mode: ' . (!empty($result['replace_meta']) ? 'ON' : 'OFF');
            return $summary;
        }

        if ($action === 'export_forms_json') {
            $summary[] = 'JSON export was generated for download.';
            return $summary;
        }

        return $summary;
    }
}

if (!function_exists('mzf_mt_render_result_summary')) {
    function mzf_mt_render_result_summary(array $result): void
    {
        if ($result === []) {
            return;
        }
        ?>
        <div class="notice notice-info inline">
            <p>
                <strong><?php echo esc_html(mzf_mt_result_heading($result)); ?></strong>
                <?php if (!empty($result['recorded_at'])) : ?>
                    <span>at <?php echo esc_html((string) $result['recorded_at']); ?></span>
                <?php endif; ?>
            </p>
            <?php foreach (mzf_mt_result_summary_lines($result) as $summary_line) : ?>
                <p><?php echo esc_html((string) $summary_line); ?></p>
            <?php endforeach; ?>
            <?php if (!empty($result['lines'])) : ?>
                <textarea readonly rows="14" style="width:100%;font-family:monospace;"><?php echo esc_textarea(implode("\n", (array) $result['lines'])); ?></textarea>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('mzf_mt_render_tools_tab')) {
    function mzf_mt_render_tools_tab(): void
    {
        $result = mzf_mt_get_result();
        $limit = isset($_GET['mzf_limit']) ? absint((int) $_GET['mzf_limit']) : 0;
        $source_id = isset($_GET['mzf_source_id']) ? absint((int) $_GET['mzf_source_id']) : 0;
        $include_hidden = isset($_GET['mzf_include_hidden']) && (string) $_GET['mzf_include_hidden'] === '1';
        ?>
        <p>Migrate legacy <code>section_form</code> content into built-in <code>form</code> posts.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('mzf_mt_run'); ?>
            <input type="hidden" name="action" value="<?php echo esc_attr(MZFMT_ACTION); ?>" />
            <input type="hidden" name="tool_action" value="migrate_section_forms" />
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="mzf-mt-source-id">Source</label></th>
                        <td>
                            <input id="mzf-mt-source-id" name="source_id" type="number" min="0" step="1" class="small-text" value="<?php echo esc_attr((string) $source_id); ?>" />
                            <p class="description">Optional. Enter one post ID to target one source. Leave at <code>0</code> to scan every eligible source.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mzf-mt-limit">Limit</label></th>
                        <td>
                            <input id="mzf-mt-limit" name="limit" type="number" min="0" step="1" class="small-text" value="<?php echo esc_attr((string) $limit); ?>" />
                            <p class="description">Optional. Leave at <code>0</code> to scan every eligible source.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Include hidden</th>
                        <td>
                            <label><input name="include_hidden" type="checkbox" value="1" <?php checked($include_hidden); ?> /> Include sources whose legacy form visibility is turned off.</label>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <button type="submit" name="mode" value="preview" class="button button-secondary">Run dry run</button>
                <button type="submit" name="mode" value="run" class="button button-primary">Run migration</button>
            </p>
        </form>

        <?php mzf_mt_render_result_summary($result); ?>
        <?php
    }
}

if (!function_exists('mzf_mt_handle_admin_post')) {
    function mzf_mt_handle_admin_post(): void
    {
        if (!mzf_mt_user_can_access_tool()) {
            wp_die('You do not have permission to run this migration.', 403);
        }

        check_admin_referer('mzf_mt_run');

        $tool_action = isset($_REQUEST['tool_action']) ? sanitize_key((string) $_REQUEST['tool_action']) : '';
        $mode = isset($_REQUEST['mode']) ? sanitize_key((string) $_REQUEST['mode']) : '';

        $args = [
            'apply' => $mode === 'run',
            'limit' => isset($_REQUEST['limit']) ? absint((int) $_REQUEST['limit']) : 0,
            'source_id' => isset($_REQUEST['source_id']) ? absint((int) $_REQUEST['source_id']) : 0,
            'include_hidden' => isset($_REQUEST['include_hidden']) && (string) $_REQUEST['include_hidden'] === '1',
            'confirm' => isset($_REQUEST['confirm']) && (string) $_REQUEST['confirm'] === '1',
            'path' => isset($_REQUEST['path']) ? wp_unslash((string) $_REQUEST['path']) : '',
            'replace_meta' => isset($_REQUEST['replace_meta']) && (string) $_REQUEST['replace_meta'] === '1',
        ];

        $result = mzf_mt_execute_action($tool_action, $args);

        if (!empty($result['download'])) {
            mzf_mt_output_download_result($result);
            exit;
        }

        mzf_mt_store_result($result);

        $redirect_url = add_query_arg([
            'mzf_limit' => (int) $args['limit'],
            'mzf_source_id' => (int) $args['source_id'],
            'mzf_include_hidden' => !empty($args['include_hidden']) ? '1' : '0',
            'mzf_json_path' => (string) $args['path'],
            'mzf_replace_meta' => !empty($args['replace_meta']) ? '1' : '0',
        ], mzf_mt_get_redirect_url());

        wp_safe_redirect($redirect_url);
        exit;
    }

    add_action('admin_post_' . MZFMT_ACTION, 'mzf_mt_handle_admin_post');
}

add_action('admin_init', function () {
    if (!is_admin() || wp_doing_ajax() || !mzf_mt_user_can_access_tool()) {
        return;
    }
    $action = isset($_GET['mzf_mt_action']) ? sanitize_key((string) $_GET['mzf_mt_action']) : '';
    if ($action === '') {
        return;
    }

    $result = mzf_mt_execute_action($action, [
        'apply' => isset($_GET['apply']) && (string) $_GET['apply'] === '1',
        'limit' => isset($_GET['limit']) ? absint((int) $_GET['limit']) : 0,
        'source_id' => isset($_GET['source_id']) ? absint((int) $_GET['source_id']) : 0,
        'include_hidden' => isset($_GET['include_hidden']) && (string) $_GET['include_hidden'] === '1',
        'confirm' => isset($_GET['confirm']) && (string) $_GET['confirm'] === '1',
        'path' => isset($_GET['path']) ? wp_unslash((string) $_GET['path']) : '',
        'replace_meta' => isset($_GET['replace_meta']) && (string) $_GET['replace_meta'] === '1',
    ]);

    if (!empty($result['download'])) {
        mzf_mt_output_download_result($result);
        exit;
    }

    mzf_mt_print_report((string) ($result['title'] ?? 'MZ Form Migration Tools'), (array) ($result['lines'] ?? []));
    exit;
}, 1);

add_action('init', 'mzf_mt_backfill_term_section_form_relationship_once', 30);

if (defined('WP_CLI') && WP_CLI && !class_exists('MZ_Form_Visibility_Cleanup_CLI_Command')) {
    class MZ_Form_Visibility_Cleanup_CLI_Command
    {
        /**
         * Disable form visibility for child services and child locality terms.
         *
         * ## OPTIONS
         *
         * [--apply]
         * : Write the changes. Omit for a dry run.
         *
         * ## EXAMPLES
         *
         *     wp mz disable-nested-form-visibility
         *     wp mz disable-nested-form-visibility --apply
         *
         * @when after_wp_load
         */
        public function __invoke($args, $assoc_args): void
        {
            $result = mzf_mt_execute_action('disable_nested_form_visibility', [
                'apply' => isset($assoc_args['apply']),
            ]);

            foreach ((array) ($result['lines'] ?? []) as $line) {
                \WP_CLI::log((string) $line);
            }

            \WP_CLI::success(!empty($result['apply']) ? 'Nested form visibility cleanup complete.' : 'Nested form visibility dry run complete.');
        }
    }

    \WP_CLI::add_command('mz disable-nested-form-visibility', 'MZ_Form_Visibility_Cleanup_CLI_Command');
}
