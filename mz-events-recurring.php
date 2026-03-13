<?php

/**
 * Plugin Name: DS Events Recurring
 * Description: Recurring event generation and synchronization for the Events post type.
 * Version: 1.3.0
 * Author: Meza LLC
 * Author URI: https://meza.design
 *
 * Runtime:
 *   Must-use plugin loaded by `mz-loader.php` from `/wp-content/mu-plugins/mz-mu-plugins`.
 *
 * Optional constants:
 *   define('MZ_EVENTS_RECURRING_POST_TYPE', 'event');
 *   define('MZ_EVENTS_RECURRING_SINGLE_CAP', 3);
 *
 * Optional filters:
 *   `mz_events_recurring_post_type`
 *   `mz_events_recurring_field_map`
 *   `mz_events_recurring_single_cap`
 *   `mz_seo_site_name`
 */

namespace MZ\Recurring;

if (!defined('ABSPATH')) exit;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;

class MZ_Recurring_Events
{
    private const SEO_TITLE_MAX_LENGTH = 60;
    private const META_PARENT_ID = '_mz_recurrence_parent_id';
    private const META_OCCURRENCE_KEY = '_mz_occurrence_key';
    private const META_GENERATED = '_mz_generated_occurrence';
    private const META_LOCKED = '_mz_occurrence_locked';
    private const META_STALE = '_mz_occurrence_stale';
    private const META_RESET_SINGLE_ON_PUBLISH = '_mz_reset_single_on_publish';

    private bool $is_syncing = false;
    private string $post_type;
    private array $field_map;
    private int $single_event_cap;

    public function __construct()
    {
        $this->post_type = apply_filters(
            'mz_events_recurring_post_type',
            defined('MZ_EVENTS_RECURRING_POST_TYPE') ? (string) MZ_EVENTS_RECURRING_POST_TYPE : 'event'
        );

        $default_field_map = [
            'recurrence' => 'recurrence',
            'recurrence_cap' => 'recurrence_cap',
            'start_datetime' => 'start_datetime',
            'end_datetime' => 'end_datetime',
        ];
        $this->field_map = apply_filters('mz_events_recurring_field_map', $default_field_map);
        if (!is_array($this->field_map)) {
            $this->field_map = $default_field_map;
        }

        $this->single_event_cap = (int) apply_filters(
            'mz_events_recurring_single_cap',
            defined('MZ_EVENTS_RECURRING_SINGLE_CAP') ? (int) MZ_EVENTS_RECURRING_SINGLE_CAP : 3
        );
        if ($this->single_event_cap < 1) {
            $this->single_event_cap = 1;
        }

        $save_post_hook = 'save_post_' . $this->post_type;

        add_action('acf/save_post', [$this, 'handle_acf_save_event_processing'], 20);
        add_action('acf/save_post', [$this, 'reset_recurrence_after_acf_save'], 999);
        add_action('transition_post_status', [$this, 'mark_recurrence_reset_needed_on_publish'], 10, 3);
        add_action($save_post_hook, [$this, 'handle_save_post_event_processing'], 25, 3);
        add_action($save_post_hook, [$this, 'reset_recurrence_to_single_on_publish'], 40, 3);
        add_action($save_post_hook, [$this, 'mark_child_as_locked_on_manual_edit'], 20, 3);
        add_action('admin_notices', [$this, 'render_admin_notice']);
    }

    private function is_target_post_type(string $post_type): bool
    {
        return $post_type === $this->post_type;
    }

    private function field_key(string $logical_key): string
    {
        $mapped = $this->field_map[$logical_key] ?? $logical_key;
        return is_string($mapped) && $mapped !== '' ? $mapped : $logical_key;
    }

    private function get_event_field(int $post_id, string $logical_key)
    {
        $key = $this->field_key($logical_key);
        if (function_exists('get_field')) {
            return get_field($key, $post_id);
        }
        return get_post_meta($post_id, $key, true);
    }

    private function set_event_field(int $post_id, string $logical_key, $value): void
    {
        $this->set_raw_field($post_id, $this->field_key($logical_key), $value);
    }

    private function set_raw_field(int $post_id, string $field_key, $value): void
    {
        if (function_exists('update_field')) {
            update_field($field_key, $value, $post_id);
            return;
        }
        update_post_meta($post_id, $field_key, $value);
    }

    public function mark_recurrence_reset_needed_on_publish(string $new_status, string $old_status, $post): void
    {
        if (!($post instanceof \WP_Post)) return;
        if (!$this->is_target_post_type($post->post_type)) return;
        if ($new_status !== 'publish' || $old_status === 'publish') return;
        if (wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID)) return;
        if ((int) $post->post_parent > 0) return;

        $recurrence = $this->normalize_recurrence($this->get_event_field($post->ID, 'recurrence'));
        if ($recurrence === '' || $recurrence === 'single_event') return;

        update_post_meta($post->ID, self::META_RESET_SINGLE_ON_PUBLISH, '1');
    }

    public function handle_acf_save_event_processing($post_id): void
    {
        if (!is_numeric($post_id)) return;
        $this->maybe_sync_parent_event((int) $post_id);
    }

    public function handle_save_post_event_processing(int $post_id, $post, bool $update): void
    {
        if (!($post instanceof \WP_Post)) return;
        if (!$this->is_target_post_type($post->post_type)) return;
        if (!$update) return;
        $this->maybe_sync_parent_event($post_id);
    }

    private function maybe_sync_parent_event(int $post_id): void
    {
        if ($this->is_syncing) return;
        $post = get_post($post_id);

        if (
            !$post ||
            !$this->is_target_post_type($post->post_type) ||
            wp_is_post_revision($post_id) ||
            wp_is_post_autosave($post_id) ||
            (int) $post->post_parent > 0
        ) {
            return;
        }

        if ($post->post_status !== 'publish') {
            return;
        }

        $lock_key = 'mz_recurrence_sync_lock_' . $post_id;
        if (get_transient($lock_key)) {
            return;
        }
        set_transient($lock_key, 1, 30);

        $recurrence_before_sync = $this->normalize_recurrence($this->get_event_field($post_id, 'recurrence'));
        $should_reset_to_single_defaults = ($recurrence_before_sync !== '' && $recurrence_before_sync !== 'single_event');

        $this->is_syncing = true;
        remove_action('acf/save_post', [$this, 'handle_acf_save_event_processing'], 20);
        try {
            $summary = $this->process_recurring_events($post_id);
            if ($should_reset_to_single_defaults) {
                $this->apply_single_defaults($post_id);
            }
            if ($summary !== '') {
                $this->set_admin_notice('success', $summary);
            }
        } catch (\Throwable $e) {
            error_log('Recurring events sync error for post ' . $post_id . ': ' . $e->getMessage());
            $this->set_admin_notice('error', 'Recurring events could not be updated. Check server logs for details.');
        }

        add_action('acf/save_post', [$this, 'handle_acf_save_event_processing'], 20);
        $this->is_syncing = false;
        delete_transient($lock_key);
    }

    public function mark_child_as_locked_on_manual_edit(int $post_id, $post, bool $update): void
    {
        if ($this->is_syncing) return;
        if (!$update) return;
        if (!($post instanceof \WP_Post)) return;
        if (!$this->is_target_post_type($post->post_type)) return;

        $is_generated = (string) get_post_meta($post_id, self::META_GENERATED, true) === '1';
        if (!$is_generated) return;

        update_post_meta($post_id, self::META_LOCKED, '1');
    }

    public function reset_recurrence_to_single_on_publish(int $post_id, $post, bool $update): void
    {
        if (!($post instanceof \WP_Post)) return;
        if (!$this->is_target_post_type($post->post_type)) return;
        if (!$update) return;
        if ($post->post_status !== 'publish') return;
        if ((int) $post->post_parent > 0) return;
        if (!empty($_POST['acf'])) return;

        $should_reset = (string) get_post_meta($post_id, self::META_RESET_SINGLE_ON_PUBLISH, true) === '1';
        if (!$should_reset) return;

        $this->apply_single_defaults($post_id);
    }

    public function reset_recurrence_after_acf_save($post_id): void
    {
        if (!is_numeric($post_id)) return;
        $post_id = (int) $post_id;

        $post = get_post($post_id);
        if (!($post instanceof \WP_Post)) return;
        if (!$this->is_target_post_type($post->post_type)) return;
        if ($post->post_status !== 'publish') return;
        if ((int) $post->post_parent > 0) return;

        $should_reset = (string) get_post_meta($post_id, self::META_RESET_SINGLE_ON_PUBLISH, true) === '1';
        if (!$should_reset) return;

        $this->apply_single_defaults($post_id);
    }

    private function apply_single_defaults(int $post_id): void
    {
        $this->set_event_field($post_id, 'recurrence', 'single');
        $this->set_event_field($post_id, 'recurrence_cap', $this->single_event_cap);
        delete_post_meta($post_id, self::META_RESET_SINGLE_ON_PUBLISH);
    }

    private function process_recurring_events(int $post_id): string
    {
        $recurrence = $this->normalize_recurrence($this->get_event_field($post_id, 'recurrence'));
        $recurrence_cap_raw = $this->get_event_field($post_id, 'recurrence_cap');
        $start_date_val = (string) $this->get_event_field($post_id, 'start_datetime');
        $end_date_val = (string) $this->get_event_field($post_id, 'end_datetime');

        if ($recurrence === '' || $recurrence === 'single_event') {
            $staled = $this->mark_generated_children_stale($post_id);
            return $staled > 0
                ? 'Recurring children were moved to draft because this event is set to single event.'
                : 'Recurring settings saved (single event mode).';
        }

        if ($start_date_val === '' || $end_date_val === '') {
            $this->set_admin_notice('error', 'Start and end date/time are required for recurring generation.');
            return '';
        }

        $interval = $this->get_recurrence_interval($recurrence);
        if (!$interval) {
            $this->set_admin_notice('error', 'Unsupported recurrence type: ' . esc_html($recurrence));
            return '';
        }

        try {
            $timezone = new DateTimeZone(wp_timezone_string());
            $start_date_obj = new DateTime($start_date_val, $timezone);
            $end_date_obj = new DateTime($end_date_val, $timezone);
        } catch (Exception $e) {
            error_log('Date/time creation error: ' . $e->getMessage());
            $this->set_admin_notice('error', 'Invalid date/time format found on the event.');
            return '';
        }

        if ($end_date_obj <= $start_date_obj) {
            $end_date_obj = clone $start_date_obj;
            $end_date_obj->add(new DateInterval('PT1H'));
            $this->set_admin_notice('error', 'End time was not after start time; defaulted to one hour after start.');
        }

        $recurrence_cap_months = $this->normalize_recurrence_cap_months(
            $recurrence_cap_raw,
            $recurrence
        );
        if ($recurrence_cap_months < 1) {
            $this->set_admin_notice('error', 'Recurrence cap must be at least 1 month.');
            return '';
        }

        $original_data = [
            'title' => get_the_title($post_id),
            'slug_base' => sanitize_title(get_the_title($post_id)),
            'excerpt' => get_the_excerpt($post_id),
            'content' => get_post_field('post_content', $post_id),
            'fields' => function_exists('get_fields') ? (array) get_fields($post_id) : [],
            'thumbnail' => (int) get_post_thumbnail_id($post_id),
            'yoast_metadesc' => get_post_meta($post_id, '_yoast_wpseo_metadesc', true),
        ];

        $this->update_original_event($post_id, $original_data, $start_date_obj, $end_date_obj, $recurrence);

        $desired = $this->build_occurrence_plan(
            $start_date_obj,
            $end_date_obj,
            $interval,
            $recurrence,
            $recurrence_cap_months
        );

        $result = $this->sync_occurrences($post_id, $original_data, $desired);

        return sprintf(
            'Recurring sync complete: %d created, %d updated, %d moved to draft, %d locked untouched.',
            $result['created'],
            $result['updated'],
            $result['staled'],
            $result['locked_skipped']
        );
    }

    private function normalize_recurrence($raw): string
    {
        if (is_array($raw)) {
            $raw = $raw['value'] ?? $raw['label'] ?? reset($raw) ?? '';
        }
        $value = strtolower(trim((string) $raw));
        $value = str_replace([' ', '_'], '-', $value);

        if ($value === '') return '';
        if (in_array($value, ['single-event', 'single'], true)) return 'single_event';
        if (in_array($value, ['weekly', 'week'], true)) return 'weekly';
        if (in_array($value, ['monthly', 'month'], true)) return 'monthly';
        if (in_array($value, ['monthly-week', 'monthly-weekday', 'month-week', 'monthly-same-week-day'], true)) return 'monthly-week';

        return $value;
    }

    private function normalize_recurrence_cap_months($raw, string $recurrence): int
    {
        if (is_array($raw)) {
            $raw = $raw['value'] ?? $raw['label'] ?? reset($raw) ?? '';
        }

        $text = strtolower(trim((string) $raw));
        if ($text === '') return 0;

        if (ctype_digit($text)) {
            return max(0, (int) $text);
        }

        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $text);
        $normalized = trim((string) $normalized);

        $word_to_num = [
            'one' => 1,
            'two' => 2,
            'three' => 3,
            'four' => 4,
            'five' => 5,
            'six' => 6,
            'seven' => 7,
            'eight' => 8,
            'nine' => 9,
            'ten' => 10,
            'eleven' => 11,
            'twelve' => 12,
        ];

        foreach ($word_to_num as $word => $num) {
            $normalized = preg_replace('/\b' . preg_quote($word, '/') . '\b/', (string) $num, $normalized);
        }

        if (preg_match('/\b(\d+)\b/', $normalized, $m) && !preg_match('/\b(day|week|month|year)s?\b/', $normalized)) {
            return max(0, (int) $m[1]);
        }

        if (!preg_match('/\b(\d+)\s*(day|week|month|year)s?\b/', $normalized, $m)) {
            return 0;
        }

        $amount = max(0, (int) $m[1]);
        $unit = $m[2];
        if ($amount < 1) return 0;

        switch ($unit) {
            case 'month':
                return $amount;
            case 'year':
                return $amount * 12;
            case 'week':
                return (int) ceil($amount / 4.34524);
            case 'day':
                return (int) max(1, ceil($amount / 30));
            default:
                return 0;
        }
    }

    private function update_original_event(int $post_id, array $data, DateTime $start_date, DateTime $end_date, string $parent_recurrence_type): void
    {
        $new_slug = $data['slug_base'] . '-' . $start_date->format('m-d-Y');
        $current_slug = get_post_field('post_name', $post_id);

        if ($new_slug !== $current_slug) {
            wp_update_post([
                'ID' => $post_id,
                'post_name' => $new_slug,
            ]);
        }

        $this->update_yoast_title($post_id, $data['title'], $start_date);
        $this->update_event_fields($post_id, $data['fields'], $start_date, $end_date, $parent_recurrence_type, false);
        update_post_meta($post_id, self::META_GENERATED, '0');
        delete_post_meta($post_id, self::META_PARENT_ID);
        delete_post_meta($post_id, self::META_OCCURRENCE_KEY);
        delete_post_meta($post_id, self::META_STALE);
    }

    private function build_occurrence_plan(
        DateTime $start_date,
        DateTime $end_date,
        DateInterval $interval,
        string $recurrence_logic_type,
        int $cap_months
    ): array {
        $plan = [];
        $current_start = clone $start_date;
        $duration_seconds = max(0, $end_date->getTimestamp() - $start_date->getTimestamp());
        $monthly_week_anchor = $recurrence_logic_type === 'monthly-week'
            ? $this->build_monthly_week_anchor($start_date)
            : null;
        $window_end = clone $start_date;
        $window_end->add(new DateInterval('P' . max(1, $cap_months) . 'M'));

        for ($i = 0; $i < 500; $i++) {
            $next_start = $this->get_next_date($current_start, $interval, $recurrence_logic_type, $monthly_week_anchor);
            if ($next_start <= $current_start) break;
            if ($next_start > $window_end) break;

            $next_end = clone $next_start;
            if ($duration_seconds > 0) {
                $next_end->modify('+' . $duration_seconds . ' seconds');
            }

            $plan[] = [
                'start' => $next_start,
                'end' => $next_end,
                'status' => 'publish',
            ];

            $current_start = $next_start;
        }

        $draft_start = $this->get_next_date($current_start, $interval, $recurrence_logic_type, $monthly_week_anchor);
        $draft_end = clone $draft_start;
        if ($duration_seconds > 0) {
            $draft_end->modify('+' . $duration_seconds . ' seconds');
        }
        if ($draft_start > $current_start) {
            $plan[] = [
                'start' => $draft_start,
                'end' => $draft_end,
                'status' => 'draft',
            ];
        }

        return $plan;
    }

    private function sync_occurrences(int $parent_id, array $data, array $desired_plan): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'staled' => 0,
            'locked_skipped' => 0,
        ];

        $existing = $this->get_existing_generated_children_map($parent_id);

        foreach ($desired_plan as $occurrence) {
            $key = $this->build_occurrence_key($occurrence['start']);
            if (isset($existing[$key])) {
                $child_id = (int) $existing[$key];
                $was_updated = $this->update_existing_occurrence(
                    $child_id,
                    $parent_id,
                    $data,
                    $occurrence['start'],
                    $occurrence['end'],
                    $occurrence['status'],
                    $key
                );
                if ($was_updated) $result['updated']++;
                else $result['locked_skipped']++;
                unset($existing[$key]);
                continue;
            }

            $created = $this->create_event_occurrence(
                $parent_id,
                $data,
                $occurrence['start'],
                $occurrence['end'],
                $occurrence['status'],
                $key
            );
            if ($created) $result['created']++;
        }

        // Any leftover generated children are no longer part of this pattern.
        foreach ($existing as $orphan_id) {
            $orphan_id = (int) $orphan_id;
            wp_update_post([
                'ID' => $orphan_id,
                'post_status' => 'draft',
            ]);
            update_post_meta($orphan_id, self::META_STALE, '1');
            $result['staled']++;
        }

        return $result;
    }

    private function get_existing_generated_children_map(int $parent_id): array
    {
        $posts = get_posts([
            'post_type' => $this->post_type,
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'post_parent' => $parent_id,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => self::META_GENERATED,
                    'value' => '1',
                    'compare' => '=',
                ],
            ],
        ]);

        $map = [];
        foreach ($posts as $child_id) {
            $key = (string) get_post_meta($child_id, self::META_OCCURRENCE_KEY, true);
            if ($key !== '') {
                $map[$key] = (int) $child_id;
            }
        }
        return $map;
    }

    private function create_event_occurrence(
        int $parent_id,
        array $data,
        DateTime $start_date,
        DateTime $end_date,
        string $status,
        string $occurrence_key
    ): bool {
        $date_suffix = $start_date->format('m-d-Y');
        $new_slug = $data['slug_base'] . '-' . $date_suffix;

        $post_id = wp_insert_post([
            'post_title' => $data['title'],
            'post_content' => $data['content'],
            'post_status' => $status,
            'post_type' => $this->post_type,
            'post_parent' => $parent_id,
            'post_name' => $new_slug,
            'post_excerpt' => $data['excerpt'],
        ], true);

        if (is_wp_error($post_id) || !$post_id) {
            error_log('Event creation failed: ' . (is_wp_error($post_id) ? $post_id->get_error_message() : 'Unknown'));
            return false;
        }

        if (!empty($data['thumbnail'])) {
            set_post_thumbnail($post_id, (int) $data['thumbnail']);
        }

        if (!empty($data['yoast_metadesc'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $data['yoast_metadesc']);
        }

        $this->update_event_fields($post_id, $data['fields'], $start_date, $end_date, 'single_event', true);
        $this->update_yoast_title((int) $post_id, $data['title'], $start_date);

        update_post_meta($post_id, self::META_GENERATED, '1');
        update_post_meta($post_id, self::META_PARENT_ID, (string) $parent_id);
        update_post_meta($post_id, self::META_OCCURRENCE_KEY, $occurrence_key);
        delete_post_meta($post_id, self::META_STALE);
        delete_post_meta($post_id, self::META_LOCKED);

        return true;
    }

    private function update_existing_occurrence(
        int $post_id,
        int $parent_id,
        array $data,
        DateTime $start_date,
        DateTime $end_date,
        string $status,
        string $occurrence_key
    ): bool {
        $date_suffix = $start_date->format('m-d-Y');
        $new_slug = $data['slug_base'] . '-' . $date_suffix;

        wp_update_post([
            'ID' => $post_id,
            'post_parent' => $parent_id,
            'post_status' => $status,
            'post_name' => $new_slug,
        ]);

        // Always keep child occurrence content/fields synced to parent to minimize editor duplicate work.
        $this->update_event_fields($post_id, $data['fields'], $start_date, $end_date, 'single_event', true);

        wp_update_post([
            'ID' => $post_id,
            'post_title' => $data['title'],
            'post_content' => $data['content'],
            'post_excerpt' => $data['excerpt'],
        ]);

        if (!empty($data['thumbnail'])) {
            set_post_thumbnail($post_id, (int) $data['thumbnail']);
        }
        if (!empty($data['yoast_metadesc'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $data['yoast_metadesc']);
        }
        $this->update_yoast_title($post_id, $data['title'], $start_date);

        update_post_meta($post_id, self::META_GENERATED, '1');
        update_post_meta($post_id, self::META_PARENT_ID, (string) $parent_id);
        update_post_meta($post_id, self::META_OCCURRENCE_KEY, $occurrence_key);
        delete_post_meta($post_id, self::META_STALE);

        return true;
    }

    private function mark_generated_children_stale(int $parent_id): int
    {
        $children = get_posts([
            'post_type' => $this->post_type,
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'post_parent' => $parent_id,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => self::META_GENERATED,
                    'value' => '1',
                    'compare' => '=',
                ],
            ],
        ]);

        $count = 0;
        foreach ($children as $child_id) {
            wp_update_post([
                'ID' => (int) $child_id,
                'post_status' => 'draft',
            ]);
            update_post_meta($child_id, self::META_STALE, '1');
            $count++;
        }
        return $count;
    }

    private function update_event_fields(
        int $post_id,
        array $fields,
        DateTime $start_date,
        DateTime $end_date,
        string $recurrence_setting,
        bool $copy_non_date_fields
    ): void {
        $changes = [
            $this->field_key('start_datetime') => $start_date->format('Y-m-d H:i:s'),
            $this->field_key('end_datetime') => $end_date->format('Y-m-d H:i:s'),
            $this->field_key('recurrence') => $this->to_recurrence_field_value($recurrence_setting),
        ];
        if ($recurrence_setting === 'single_event') {
            $changes[$this->field_key('recurrence_cap')] = $this->single_event_cap;
        }

        $recurrence_cap_key = $this->field_key('recurrence_cap');

        if ($copy_non_date_fields) {
            foreach ($fields as $key => $value) {
                if (array_key_exists($key, $changes) || $key === $recurrence_cap_key) continue;
                $this->set_raw_field($post_id, (string) $key, $value);
            }
        }

        foreach ($changes as $key => $value) {
            $this->set_raw_field($post_id, $key, $value);
        }
    }

    private function to_recurrence_field_value(string $recurrence): string
    {
        return $recurrence === 'single_event' ? 'single' : $recurrence;
    }

    private function update_yoast_title(int $post_id, string $title, DateTime $date): void
    {
        $age_group = $this->get_age_group_label($post_id);
        $location = $this->get_location_title_segment($post_id);
        $formatted_date = $date->format('F j');
        $site_name = 'FAHASS';

        $title_with_age = trim($title);
        if ($age_group !== '') {
            $title_with_age .= ' (' . $age_group . ')';
        }

        $base_with_age = $title_with_age . ' on ' . $formatted_date;
        $base_without_age = trim($title) . ' on ' . $formatted_date;
        $title_with_age_and_location = $location !== ''
            ? $base_with_age . ' at ' . $location . ' | ' . $site_name
            : $base_with_age . ' | ' . $site_name;
        $title_without_age_with_location = $location !== ''
            ? $base_without_age . ' at ' . $location . ' | ' . $site_name
            : $base_without_age . ' | ' . $site_name;
        $title_without_age_and_location = $base_without_age . ' | ' . $site_name;

        $seo_title = $title_with_age_and_location;
        if ($this->string_length($seo_title) > self::SEO_TITLE_MAX_LENGTH) {
            $seo_title = $title_without_age_with_location;
        }
        if ($this->string_length($seo_title) > self::SEO_TITLE_MAX_LENGTH) {
            $seo_title = $title_without_age_and_location;
        }
        if ($this->string_length($seo_title) > self::SEO_TITLE_MAX_LENGTH) {
            $seo_title = $this->truncate_with_ellipsis($seo_title, self::SEO_TITLE_MAX_LENGTH);
        }

        update_post_meta($post_id, '_yoast_wpseo_title', $seo_title);
    }

    private function get_age_group_label(int $post_id): string
    {
        $age_group = trim((string) $this->get_event_field($post_id, 'age_group'));
        if ($age_group === '') return '';

        $age_group_field = function_exists('get_field_object') ? get_field_object('age_group', $post_id) : null;
        if (
            is_array($age_group_field)
            && !empty($age_group_field['choices'])
            && is_array($age_group_field['choices'])
            && isset($age_group_field['choices'][$age_group])
        ) {
            return trim((string) $age_group_field['choices'][$age_group]);
        }

        return $age_group;
    }

    private function get_location_title_segment(int $post_id): string
    {
        $location = $this->get_event_field($post_id, 'location');
        if (!is_array($location)) return '';

        $parts = [];
        $candidates = [
            $location['name'] ?? '',
            $location['venue'] ?? '',
            $location['address'] ?? '',
        ];

        foreach ($candidates as $candidate) {
            $value = trim(wp_strip_all_tags((string) $candidate));
            if ($value === '' || in_array($value, $parts, true)) continue;
            $parts[] = $value;
        }

        return implode(', ', $parts);
    }

    private function string_length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    private function truncate_with_ellipsis(string $value, int $max_length): string
    {
        if ($max_length <= 0) return '';
        if ($this->string_length($value) <= $max_length) return $value;
        if ($max_length <= 3) return str_repeat('.', $max_length);

        $slice_length = $max_length - 3;
        $truncated = function_exists('mb_substr')
            ? mb_substr($value, 0, $slice_length)
            : substr($value, 0, $slice_length);

        return rtrim($truncated) . '...';
    }

    private function get_recurrence_interval(string $recurrence): ?DateInterval
    {
        try {
            switch ($recurrence) {
                case 'weekly':
                    return new DateInterval('P1W');
                case 'monthly':
                case 'monthly-week':
                    return new DateInterval('P1M');
                default:
                    error_log("Unsupported recurrence type: {$recurrence}");
                    return null;
            }
        } catch (Exception $e) {
            error_log('DateInterval error: ' . $e->getMessage());
            return null;
        }
    }

    private function get_next_date(
        DateTime $current_date,
        DateInterval $interval,
        string $recurrence_logic_type,
        ?array $monthly_week_anchor = null
    ): DateTime {
        $next_date = clone $current_date;

        if ($recurrence_logic_type === 'monthly-week') {
            return $this->calculate_monthly_week_date($next_date, $monthly_week_anchor);
        }

        try {
            $next_date->add($interval);
        } catch (Exception $e) {
            error_log('Date addition error: ' . $e->getMessage());
        }

        return $next_date;
    }

    private function build_monthly_week_anchor(DateTime $source_date): array
    {
        $weekday_name = $source_date->format('l');
        $day_of_month = (int) $source_date->format('j');
        $month_number = (int) $source_date->format('n');
        $year_number = (int) $source_date->format('Y');

        $occurrence = 0;
        $temp_date = new DateTime("{$year_number}-{$month_number}-01", $source_date->getTimezone());
        for ($d = 1; $d <= (int) $temp_date->format('t'); $d++) {
            $loop_day = new DateTime("{$year_number}-{$month_number}-{$d}", $source_date->getTimezone());
            if ($loop_day->format('l') === $weekday_name) {
                $occurrence++;
                if ($d === $day_of_month) break;
            }
        }
        if ($occurrence < 1) $occurrence = 1;
        if ($occurrence > 5) $occurrence = 5;

        return [
            'weekday' => $weekday_name,
            'occurrence' => $occurrence,
        ];
    }

    private function calculate_monthly_week_date(DateTime $date_to_calculate_from, ?array $anchor = null): DateTime
    {
        $timezone = $date_to_calculate_from->getTimezone();
        $target_day_of_week_name = $anchor['weekday'] ?? $date_to_calculate_from->format('l');
        $original_hour = $date_to_calculate_from->format('H');
        $original_minute = $date_to_calculate_from->format('i');
        $original_second = $date_to_calculate_from->format('s');

        $occurrence_count = (int) ($anchor['occurrence'] ?? 1);
        if ($occurrence_count < 1) $occurrence_count = 1;
        if ($occurrence_count > 5) $occurrence_count = 5;

        $next_event_date = clone $date_to_calculate_from;
        $next_event_date->modify('first day of next month');
        $next_event_date->setTime((int) $original_hour, (int) $original_minute, (int) $original_second);

        $ordinals = ['first', 'second', 'third', 'fourth', 'fifth'];
        $target_ordinal_string = ($occurrence_count > 0 && $occurrence_count <= 5)
            ? $ordinals[$occurrence_count - 1]
            : 'first';

        $candidate_date = clone $next_event_date;
        $candidate_date->modify($target_ordinal_string . ' ' . $target_day_of_week_name . ' of this month');

        if ($candidate_date->format('n') !== $next_event_date->format('n')) {
            $next_event_date->modify('last ' . $target_day_of_week_name . ' of this month');
        } else {
            $next_event_date = $candidate_date;
        }

        $next_event_date->setTime((int) $original_hour, (int) $original_minute, (int) $original_second);
        return $next_event_date;
    }

    private function build_occurrence_key(DateTime $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    private function set_admin_notice(string $type, string $message): void
    {
        if (!is_admin() || !is_user_logged_in()) return;
        $user_id = get_current_user_id();
        if (!$user_id) return;

        set_transient('mz_recurrence_notice_' . $user_id, [
            'type' => $type,
            'message' => $message,
        ], 90);
    }

    public function render_admin_notice(): void
    {
        if (!is_admin() || !is_user_logged_in()) return;
        $user_id = get_current_user_id();
        if (!$user_id) return;

        $key = 'mz_recurrence_notice_' . $user_id;
        $notice = get_transient($key);
        if (!$notice || !is_array($notice)) return;

        delete_transient($key);

        $type = ($notice['type'] ?? 'success') === 'error' ? 'notice-error' : 'notice-success';
        $message = (string) ($notice['message'] ?? 'Recurring events updated.');
        echo '<div class="notice ' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
}

new MZ_Recurring_Events();
