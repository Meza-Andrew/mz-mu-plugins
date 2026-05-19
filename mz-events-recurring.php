<?php

/**
 * Plugin Name: MZ Events Recurring
 * Description: Recurring event generation and synchronization for the Events post type.
 * Version: 1.4.35
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
    private const AJAX_ACTION = 'mz_generate_event_recurrence_dates';
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
            'dates' => 'dates',
            'generate_additional_dates' => 'generate_additional_dates',
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
        add_action('admin_head', [$this, 'render_event_generator_admin_ui']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'handle_generate_dates_ajax']);
        add_filter('acf/update_value/key=field_6a08cef5e142e', [$this, 'sort_dates_repeater_value_before_save'], 20, 3);
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

    private function is_truthy_value($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    private function is_generation_enabled_for_post(int $post_id): bool
    {
        return $this->is_truthy_value($this->get_event_field($post_id, 'generate_additional_dates'));
    }

    public function mark_recurrence_reset_needed_on_publish(string $new_status, string $old_status, $post): void
    {
        if (!($post instanceof \WP_Post)) return;
        if (!$this->is_target_post_type($post->post_type)) return;
        if ($new_status !== 'publish' || $old_status === 'publish') return;
        if (wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID)) return;
        if ((int) $post->post_parent > 0) return;

        if (!$this->is_generation_enabled_for_post($post->ID)) return;

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

        mz_sync_event_schedule_meta($post_id);

        $recurrence_before_sync = $this->is_generation_enabled_for_post($post_id)
            ? $this->normalize_recurrence($this->get_event_field($post_id, 'recurrence'))
            : 'single_event';
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
        $this->set_event_field($post_id, 'generate_additional_dates', 0);
        $this->set_event_field($post_id, 'recurrence', 'single');
        $this->set_event_field($post_id, 'recurrence_cap', $this->single_event_cap);
        delete_post_meta($post_id, self::META_RESET_SINGLE_ON_PUBLISH);
    }

    private function process_recurring_events(int $post_id): string
    {
        $generation_enabled = $this->is_generation_enabled_for_post($post_id);
        $recurrence = $this->normalize_recurrence($this->get_event_field($post_id, 'recurrence'));
        $recurrence_cap_raw = $this->get_event_field($post_id, 'recurrence_cap');

        if (!$generation_enabled || $recurrence === '' || $recurrence === 'single_event') {
            mz_sync_event_schedule_meta($post_id);
            $staled = $this->mark_generated_children_stale($post_id);
            return $staled > 0
                ? 'Event dates saved and legacy recurring child events were moved to draft.'
                : 'Event dates saved.';
        }

        $recurrence_cap_months = $this->normalize_recurrence_cap_months(
            $recurrence_cap_raw,
            $recurrence
        );
        if ($recurrence_cap_months < 1) {
            $this->set_admin_notice('error', 'Recurrence cap must be at least 1 month.');
            return '';
        }

        $dates_rows = $this->get_event_field($post_id, 'dates');
        $seed_row = $this->extract_seed_date_row(is_array($dates_rows) ? $dates_rows : []);
        if ($seed_row === null) {
            $this->set_admin_notice('error', 'Add the first event date and at least one start time before generating recurring dates.');
            return '';
        }

        try {
            $generated_dates = $this->build_generated_dates_payload($seed_row, $recurrence, $recurrence_cap_months);
        } catch (\Throwable $e) {
            error_log('Recurring date generation error for post ' . $post_id . ': ' . $e->getMessage());
            $this->set_admin_notice('error', 'Recurring dates could not be generated from the first date row.');
            return '';
        }

        $generated_dates = $this->sort_acf_repeater_rows_by_nested_date($generated_dates, ['date', 'start']);

        if (function_exists('update_field')) {
            update_field('field_6a08cef5e142e', $generated_dates, $post_id);
        } else {
            update_post_meta($post_id, $this->field_key('dates'), $generated_dates);
        }
        mz_sync_event_schedule_meta($post_id);
        $staled = $this->mark_generated_children_stale($post_id);

        return sprintf(
            'Generated %d event date%s%s.',
            count($generated_dates),
            count($generated_dates) === 1 ? '' : 's',
            $staled > 0 ? ' and moved ' . $staled . ' legacy child event' . ($staled === 1 ? '' : 's') . ' to draft' : ''
        );
    }

    private function extract_seed_date_row(array $dates_rows): ?array
    {
        foreach ($dates_rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $date_group = isset($row['date']) && is_array($row['date'])
                ? $row['date']
                : [];
            $start = trim((string) ($date_group['start'] ?? ''));
            $times = isset($row['times']) && is_array($row['times'])
                ? $row['times']
                : [];

            if ($start === '' || $times === []) {
                continue;
            }

            return $row;
        }

        return null;
    }

    private function extract_latest_seed_date_row(array $dates_rows): ?array
    {
        $valid_rows = [];

        foreach ($dates_rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $date_group = isset($row['date']) && is_array($row['date'])
                ? $row['date']
                : [];
            $start = trim((string) ($date_group['start'] ?? ''));
            $times = isset($row['times']) && is_array($row['times'])
                ? $row['times']
                : [];

            if ($start === '' || $times === []) {
                continue;
            }

            $valid_rows[] = $row;
        }

        if ($valid_rows === []) {
            return null;
        }

        return $valid_rows[count($valid_rows) - 1];
    }

    private function get_nested_array_string_value(array $source, array $path): string
    {
        $value = $source;

        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return '';
            }

            $value = $value[$segment];
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function get_date_sort_timestamp(string $raw_value): int
    {
        $value = trim($raw_value);
        if ($value === '') {
            return PHP_INT_MAX;
        }

        $timezone = mz_get_event_timezone();
        $normalized = mz_normalize_event_date_value($value, $timezone);

        try {
            if ($normalized !== '') {
                return (new DateTime($normalized . ' 00:00:00', $timezone))->getTimestamp();
            }

            return (new DateTime($value, $timezone))->getTimestamp();
        } catch (\Throwable $e) {
            return PHP_INT_MAX;
        }
    }

    private function sort_acf_repeater_rows_by_nested_date(array $rows, array $date_path): array
    {
        $decorated_rows = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $decorated_rows[] = [
                'index' => $index,
                'timestamp' => $this->get_date_sort_timestamp(
                    $this->get_nested_array_string_value($row, $date_path)
                ),
                'row' => $row,
            ];
        }

        usort($decorated_rows, static function (array $left, array $right): int {
            if ($left['timestamp'] === $right['timestamp']) {
                return $left['index'] <=> $right['index'];
            }

            return $left['timestamp'] <=> $right['timestamp'];
        });

        return array_values(array_map(
            static fn(array $entry): array => $entry['row'],
            $decorated_rows
        ));
    }

    public function sort_dates_repeater_value_before_save($value, $post_id, $field)
    {
        if (!is_array($value)) {
            return $value;
        }

        return $this->sort_acf_repeater_rows_by_nested_date($value, ['date', 'start']);
    }

    private function build_generated_dates_payload(array $seed_row, string $recurrence, int $recurrence_cap_months): array
    {
        $interval = $this->get_recurrence_interval($recurrence);
        if (!$interval) {
            throw new \RuntimeException('Unsupported recurrence type: ' . $recurrence);
        }

        $seed = $this->build_seed_context($seed_row);
        $payload = [
            $this->build_dates_payload_row(
                $seed['date_start'],
                $seed['end_date_offset_days'] > 0
                    ? (clone $seed['date_start'])->add(new DateInterval('P' . $seed['end_date_offset_days'] . 'D'))
                    : null,
                $seed['times']
            ),
        ];

        $desired = $this->build_occurrence_plan(
            $seed['start_datetime'],
            $seed['end_datetime'],
            $interval,
            $recurrence,
            $recurrence_cap_months
        );

        foreach ($desired as $occurrence) {
            if (($occurrence['status'] ?? 'publish') !== 'publish') {
                continue;
            }

            $occurrence_start = $occurrence['start'] ?? null;
            if (!($occurrence_start instanceof DateTime)) {
                continue;
            }

            $row_start_date = (clone $occurrence_start)->setTime(0, 0, 0);
            $row_end_date = null;
            if ($seed['end_date_offset_days'] > 0) {
                $row_end_date = (clone $row_start_date)->add(new DateInterval('P' . $seed['end_date_offset_days'] . 'D'));
            }

            $payload[] = $this->build_dates_payload_row($row_start_date, $row_end_date, $seed['times']);
        }

        return $payload;
    }

    private function build_seed_context(array $seed_row): array
    {
        $timezone = mz_get_event_timezone();
        $date_group = isset($seed_row['date']) && is_array($seed_row['date'])
            ? $seed_row['date']
            : [];
        $times_rows = isset($seed_row['times']) && is_array($seed_row['times'])
            ? $seed_row['times']
            : [];

        $date_start_value = mz_normalize_event_date_value((string) ($date_group['start'] ?? ''), $timezone);
        if ($date_start_value === '') {
            throw new \RuntimeException('Missing seed date.');
        }

        $date_end_value = mz_normalize_event_date_value((string) ($date_group['end'] ?? ''), $timezone);
        if ($date_end_value === '') {
            $date_end_value = $date_start_value;
        }

        $normalized_times = [];
        foreach ($times_rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $start = mz_normalize_event_time_value((string) ($row['start'] ?? $row['time_start'] ?? ''), $timezone);
            if ($start === '') {
                continue;
            }

            $normalized_times[] = [
                'start' => $start,
                'end' => mz_normalize_event_time_value((string) ($row['end'] ?? $row['time_end'] ?? ''), $timezone),
            ];
        }

        if ($normalized_times === []) {
            throw new \RuntimeException('Missing seed time.');
        }

        $date_start = new DateTime($date_start_value . ' 00:00:00', $timezone);
        $date_end = new DateTime($date_end_value . ' 00:00:00', $timezone);
        $start_datetime = mz_build_event_datetime($date_start_value, $normalized_times[0]['start'], $timezone);
        if (!($start_datetime instanceof DateTime)) {
            throw new \RuntimeException('Invalid start time.');
        }

        $last_time = $normalized_times[count($normalized_times) - 1];
        $end_time_value = $last_time['end'] !== '' ? $last_time['end'] : $last_time['start'];
        $end_datetime = mz_build_event_datetime($date_end_value, $end_time_value, $timezone);
        if (!($end_datetime instanceof DateTime) || $end_datetime <= $start_datetime) {
            $end_datetime = (clone $start_datetime)->add(new DateInterval('PT90M'));
        }

        $end_offset = (int) $date_start->diff($date_end)->format('%a');

        return [
            'date_start' => $date_start,
            'end_date_offset_days' => $end_offset,
            'times' => $normalized_times,
            'start_datetime' => $start_datetime,
            'end_datetime' => $end_datetime,
        ];
    }

    private function build_dates_payload_row(DateTime $row_start_date, ?DateTime $row_end_date, array $times): array
    {
        $normalized_times = [];
        foreach ($times as $time) {
            if (!is_array($time)) {
                continue;
            }

            $start = trim((string) ($time['start'] ?? ''));
            if ($start === '') {
                continue;
            }

            $normalized_times[] = [
                'start' => $start,
                'end' => trim((string) ($time['end'] ?? '')),
            ];
        }

        return [
            'date' => [
                'start' => $row_start_date->format('F j, Y'),
                'end' => $row_end_date instanceof DateTime && $row_end_date->format('Y-m-d') !== $row_start_date->format('Y-m-d')
                    ? $row_end_date->format('F j, Y')
                    : '',
            ],
            'times' => $normalized_times,
        ];
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
            'dates' => mz_build_event_dates_payload($start_date, $end_date),
            $this->field_key('start_datetime') => $start_date->format('Y-m-d H:i:s'),
            $this->field_key('end_datetime') => $end_date->format('Y-m-d H:i:s'),
            'date_start' => $start_date->format('Y-m-d'),
            'upcoming_until' => $end_date->format('Y-m-d H:i:s'),
            $this->field_key('generate_additional_dates') => 0,
            $this->field_key('recurrence') => $this->to_recurrence_field_value($recurrence_setting),
        ];
        if ($recurrence_setting === 'single_event') {
            $changes[$this->field_key('recurrence_cap')] = $this->single_event_cap;
        }

        $recurrence_cap_key = $this->field_key('recurrence_cap');
        $generate_additional_dates_key = $this->field_key('generate_additional_dates');

        if ($copy_non_date_fields) {
            foreach ($fields as $key => $value) {
                if (array_key_exists($key, $changes) || $key === $recurrence_cap_key || $key === $generate_additional_dates_key) continue;
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

    private function is_event_editor_screen(): bool
    {
        if (!is_admin() || !function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        return $screen instanceof \WP_Screen
            && in_array((string) $screen->base, ['post', 'post-new'], true)
            && $this->is_target_post_type((string) $screen->post_type);
    }

    public function handle_generate_dates_ajax(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'You do not have permission to generate event dates.'], 403);
        }

        check_ajax_referer(self::AJAX_ACTION, 'nonce');

        $raw_dates = isset($_POST['dates']) ? wp_unslash((string) $_POST['dates']) : '[]';
        $dates_rows = json_decode($raw_dates, true);
        if (!is_array($dates_rows)) {
            wp_send_json_error(['message' => 'Invalid dates payload.'], 400);
        }

        $seed_row = $this->extract_latest_seed_date_row($dates_rows);
        if ($seed_row === null) {
            wp_send_json_error(['message' => 'Add the first event date and time before generating recurring dates.'], 400);
        }

        $recurrence = $this->normalize_recurrence(isset($_POST['recurrence']) ? wp_unslash($_POST['recurrence']) : '');
        if ($recurrence === '' || $recurrence === 'single_event') {
            wp_send_json_success(['dates' => [$seed_row]]);
        }

        $recurrence_cap_months = $this->normalize_recurrence_cap_months(
            isset($_POST['recurrence_cap']) ? wp_unslash($_POST['recurrence_cap']) : '',
            $recurrence
        );
        if ($recurrence_cap_months < 1) {
            wp_send_json_error(['message' => 'Recurrence cap must be at least 1 month.'], 400);
        }

        try {
            $generated_dates = $this->build_generated_dates_payload($seed_row, $recurrence, $recurrence_cap_months);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => 'Recurring dates could not be generated from the first date row.'], 500);
        }

        $appended_dates = array_slice($generated_dates, 1);
        $dates = $this->sort_acf_repeater_rows_by_nested_date(
            array_merge($dates_rows, $appended_dates),
            ['date', 'start']
        );

        wp_send_json_success([
            'dates' => $dates,
        ]);
    }

    public function render_event_generator_admin_ui(): void
    {
        if (!$this->is_event_editor_screen()) {
            return;
        }

        $config = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action' => self::AJAX_ACTION,
            'nonce' => wp_create_nonce(self::AJAX_ACTION),
            'groupKey' => 'group_6a08b62b10548',
            'datesFieldKey' => 'field_6a08cef5e142e',
            'timesFieldKey' => 'field_6a08d083e1430',
            'dateStartKey' => 'field_6a08b62b11d32',
            'timeStartKey' => 'field_6a08b62b11d35',
            'timeEndKey' => 'field_6a08b62b11d38',
            'generatorToggleFieldKey' => 'field_meza_event_generate_additional_dates',
            'recurrenceFieldKey' => 'field_meza_event_recurrence',
            'recurrenceCapFieldKey' => 'field_meza_event_recurrence_cap',
            'defaultRecurrence' => 'monthly',
            'defaultCap' => $this->single_event_cap,
        ];
?>
        <style id="mz-event-recurrence-generator-ui">
            #acf-group_6a08b62b10548 .acf-field[data-key="field_meza_event_generate_additional_dates"] > .acf-input,
            .acf-postbox[data-key="group_6a08b62b10548"] .acf-field[data-key="field_meza_event_generate_additional_dates"] > .acf-input {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: nowrap;
                min-height: 100%;
            }

            #acf-group_6a08b62b10548 .acf-field[data-key="field_meza_event_generate_additional_dates"] .acf-true-false,
            .acf-postbox[data-key="group_6a08b62b10548"] .acf-field[data-key="field_meza_event_generate_additional_dates"] .acf-true-false {
                margin-bottom: 0;
            }

            #acf-group_6a08b62b10548 .mz-acf-repeater-auto-sorted .acf-row-handle.order,
            .acf-postbox[data-key="group_6a08b62b10548"] .mz-acf-repeater-auto-sorted .acf-row-handle.order {
                display: none;
            }

            #acf-group_6a08b62b10548 .mz-acf-repeater-auto-sorted > .acf-input .acf-repeater > table > thead > tr > .acf-row-handle:first-child,
            .acf-postbox[data-key="group_6a08b62b10548"] .mz-acf-repeater-auto-sorted > .acf-input .acf-repeater > table > thead > tr > .acf-row-handle:first-child {
                display: none;
            }

            #acf-group_6a08b62b10548 .mz-acf-repeater-auto-sorted > .acf-input .acf-repeater > table > tbody > tr > .acf-row-handle.order + td,
            #acf-group_6a08b62b10548 .mz-acf-repeater-auto-sorted > .acf-input .acf-repeater > table > thead > tr > .acf-row-handle:first-child + th,
            .acf-postbox[data-key="group_6a08b62b10548"] .mz-acf-repeater-auto-sorted > .acf-input .acf-repeater > table > tbody > tr > .acf-row-handle.order + td,
            .acf-postbox[data-key="group_6a08b62b10548"] .mz-acf-repeater-auto-sorted > .acf-input .acf-repeater > table > thead > tr > .acf-row-handle:first-child + th {
                border-left-width: 0;
                border-left-color: transparent;
            }

            .mz-event-generator-run-button {
                display: none;
            }

            .mz-event-generator-run-button.is-visible {
                display: inline-flex;
                align-items: center;
            }

            .mz-event-generator-run-button.is-busy,
            .mz-event-generator-run-button:disabled {
                pointer-events: none;
                opacity: 0.7;
            }

            #acf-group_6a08b62b10548 .mz-generator-locked-row,
            .acf-postbox[data-key="group_6a08b62b10548"] .mz-generator-locked-row {
                opacity: 0.6;
            }
        </style>
        <script id="mz-event-recurrence-generator-script">
            window.mezaEventRecurrenceGenerator = <?php echo wp_json_encode($config); ?>;
            (() => {
                const config = window.mezaEventRecurrenceGenerator || null;
                const $ = window.jQuery;
                const acf = window.acf;

                if (!config || !$ || !acf || typeof acf.getField !== 'function') {
                    return;
                }

                const postboxSelector = `#acf-${config.groupKey}, .acf-postbox[data-key="${config.groupKey}"]`;
                const stateByPostbox = new WeakMap();

                const toDom = (value) => {
                    if (!value) {
                        return null;
                    }

                    if (value.jquery) {
                        return value[0] || null;
                    }

                    return value instanceof HTMLElement ? value : null;
                };

                const getPostbox = () => document.querySelector(postboxSelector);
                const getState = (postbox) => {
                    if (!stateByPostbox.has(postbox)) {
                        stateByPostbox.set(postbox, {
                            busy: false,
                        });
                    }

                    return stateByPostbox.get(postbox);
                };

                const getDatesFieldEl = (postbox) => getFieldEl(postbox, config.datesFieldKey);
                const getDatesAddButton = (postbox) => {
                    const datesField = getDatesFieldEl(postbox);
                    if (!(datesField instanceof HTMLElement)) {
                        return null;
                    }

                    const input = datesField.querySelector(':scope > .acf-input') || datesField.querySelector('.acf-input');
                    if (!(input instanceof HTMLElement)) {
                        return null;
                    }

                    const repeater = input.querySelector(':scope > .acf-repeater') || input.querySelector('.acf-repeater');
                    if (!(repeater instanceof HTMLElement)) {
                        return null;
                    }

                    const actions = repeater.querySelector(':scope > .acf-actions') || repeater.querySelector('.acf-actions');
                    if (!(actions instanceof HTMLElement)) {
                        return null;
                    }

                    return actions.querySelector('.acf-button.button[data-event="add-row"]');
                };
                const getFieldEl = (postbox, key) => postbox.querySelector(`.acf-field[data-key="${key}"]`);
                const getAcfField = (elementOrKey, context = document) => {
                    const element = typeof elementOrKey === 'string'
                        ? context.querySelector(`.acf-field[data-key="${elementOrKey}"]`)
                        : elementOrKey;

                    if (!(element instanceof HTMLElement)) {
                        return null;
                    }

                    return acf.getField($(element)) || acf.getField(element) || null;
                };

                const getRepeaterTbody = (fieldEl) => fieldEl instanceof HTMLElement
                    ? fieldEl.querySelector(':scope > .acf-input .acf-repeater > table > tbody')
                    : null;

                const getRepeaterRows = (fieldEl) => {
                    const repeaterField = getAcfField(fieldEl);
                    if (repeaterField && typeof repeaterField.$rows === 'function') {
                        return repeaterField.$rows().toArray().filter((row) => row instanceof HTMLElement);
                    }

                    const tbody = getRepeaterTbody(fieldEl);

                    return tbody instanceof HTMLElement
                        ? Array.from(tbody.children).filter((row) => row instanceof HTMLTableRowElement && !row.classList.contains('acf-clone') && !row.classList.contains('acf-deleted'))
                        : [];
                };
                const getRowValue = (row, key) => {
                    const fieldEl = row.querySelector(`.acf-field[data-key="${key}"]`);
                    if (!(fieldEl instanceof HTMLElement)) {
                        return '';
                    }

                    const acfField = getAcfField(fieldEl);
                    if (acfField && typeof acfField.val === 'function') {
                        const value = acfField.val();
                        return typeof value === 'string' ? value : String(value || '');
                    }

                    const input = fieldEl.querySelector('input, select, textarea');
                    return input instanceof HTMLInputElement || input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement
                        ? String(input.value || '')
                        : '';
                };

                const setFieldValue = (fieldEl, value) => {
                    if (!(fieldEl instanceof HTMLElement)) {
                        return;
                    }

                    const acfField = getAcfField(fieldEl);
                    if (acfField && typeof acfField.setValue === 'function') {
                        acfField.setValue(value);
                    } else if (acfField && typeof acfField.val === 'function') {
                        acfField.val(value);
                    } else {
                        const input = fieldEl.querySelector('input, select, textarea');
                        if (input instanceof HTMLInputElement || input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement) {
                            input.value = value;
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }
                };

                const nextFrame = () => new Promise((resolve) => {
                    window.requestAnimationFrame(() => resolve());
                });

                const reinitializePickerField = async (fieldEl) => {
                    const acfField = getAcfField(fieldEl);
                    if (!acfField || typeof acfField.$inputText !== 'function' || typeof acfField.initialize !== 'function') {
                        return false;
                    }

                    const textInput = acfField.$inputText();
                    if (textInput && textInput.length) {
                        try {
                            if (typeof textInput.datepicker === 'function') {
                                textInput.datepicker('destroy');
                            }
                        } catch (error) {
                        }

                        textInput.removeClass('hasDatepicker');
                        textInput.removeAttr('id');
                    }

                    acfField.initialize();
                    await nextFrame();
                    return true;
                };

                const isDatesManagedPickerField = (fieldEl) => {
                    if (!(fieldEl instanceof HTMLElement)) {
                        return false;
                    }

                    const postbox = fieldEl.closest(postboxSelector);
                    if (!(postbox instanceof HTMLElement)) {
                        return false;
                    }

                    const datesFieldEl = getDatesFieldEl(postbox);
                    if (!(datesFieldEl instanceof HTMLElement) || !datesFieldEl.contains(fieldEl)) {
                        return false;
                    }

                    return [
                        config.dateStartKey,
                        config.timeStartKey,
                        config.timeEndKey,
                    ].includes(String(fieldEl.dataset.key || ''));
                };

                const bindPickerAppendRepair = () => {
                    if (document.body.dataset.mzPickerAppendRepairBound === '1' || typeof acf.addAction !== 'function') {
                        return;
                    }

                    const repair = async (field) => {
                        const fieldEl = toDom(field);
                        if (!(fieldEl instanceof HTMLElement) || !isDatesManagedPickerField(fieldEl)) {
                            return;
                        }

                        await reinitializePickerField(fieldEl);
                    };

                    acf.addAction('append_field/type=date_picker', repair);
                    acf.addAction('append_field/type=time_picker', repair);
                    document.body.dataset.mzPickerAppendRepairBound = '1';
                };

                const parseDateSortValue = (value) => {
                    const normalized = String(value || '').trim();
                    if (!normalized) {
                        return Number.POSITIVE_INFINITY;
                    }

                    if (/^\d{8}$/.test(normalized)) {
                        const year = Number(normalized.slice(0, 4));
                        const month = Number(normalized.slice(4, 6)) - 1;
                        const day = Number(normalized.slice(6, 8));
                        return Date.UTC(year, month, day);
                    }

                    const parsed = Date.parse(normalized);
                    return Number.isNaN(parsed) ? Number.POSITIVE_INFINITY : parsed;
                };

                const sortRepeaterPayloadRowsByDate = (rows, getDateValue) => rows
                    .map((row, index) => ({
                        row,
                        index,
                        sortValue: parseDateSortValue(getDateValue(row)),
                    }))
                    .sort((left, right) => {
                        if (left.sortValue === right.sortValue) {
                            return left.index - right.index;
                        }

                        return left.sortValue - right.sortValue;
                    })
                    .map((entry) => entry.row);

                const disableRepeaterManualSorting = (fieldEl) => {
                    if (!(fieldEl instanceof HTMLElement)) {
                        return;
                    }

                    fieldEl.classList.add('mz-acf-repeater-auto-sorted');
                };

                const collectDatesRows = (postbox) => {
                    const datesFieldEl = getDatesFieldEl(postbox);
                    if (!(datesFieldEl instanceof HTMLElement)) {
                        return [];
                    }

                    return getRepeaterRows(datesFieldEl).map((row) => ({
                        date: {
                            start: getRowValue(row, config.dateStartKey),
                            end: '',
                        },
                        times: (() => {
                            const timesFieldEl = row.querySelector(`.acf-field[data-key="${config.timesFieldKey}"]`);
                            if (!(timesFieldEl instanceof HTMLElement)) {
                                return [];
                            }

                            return getRepeaterRows(timesFieldEl).map((timeRow) => ({
                                start: getRowValue(timeRow, config.timeStartKey),
                                end: getRowValue(timeRow, config.timeEndKey),
                            })).filter((timeRow) => timeRow.start);
                        })(),
                    })).filter((row) => row.date.start && row.times.length);
                };

                const getSeedRow = (postbox) => {
                    const datesFieldEl = getDatesFieldEl(postbox);
                    if (!(datesFieldEl instanceof HTMLElement)) {
                        return null;
                    }

                    const rows = getRepeaterRows(datesFieldEl);
                    if (!rows.length) {
                        return null;
                    }

                    const row = rows[0];
                    const timesFieldEl = row.querySelector(`.acf-field[data-key="${config.timesFieldKey}"]`);
                    const timeRows = timesFieldEl instanceof HTMLElement ? getRepeaterRows(timesFieldEl) : [];
                    const firstTimeRow = timeRows.length ? timeRows[0] : null;

                    return {
                        startDate: getRowValue(row, config.dateStartKey),
                        startTime: firstTimeRow instanceof HTMLElement ? getRowValue(firstTimeRow, config.timeStartKey) : '',
                    };
                };

                const getLatestValidDatesRow = (postbox) => {
                    const datesFieldEl = getDatesFieldEl(postbox);
                    if (!(datesFieldEl instanceof HTMLElement)) {
                        return null;
                    }

                    const rows = getRepeaterRows(datesFieldEl);
                    let latestValidRow = null;

                    rows.forEach((row) => {
                        const startDate = getRowValue(row, config.dateStartKey);
                        const timesFieldEl = row.querySelector(`.acf-field[data-key="${config.timesFieldKey}"]`);
                        const timeRows = timesFieldEl instanceof HTMLElement ? getRepeaterRows(timesFieldEl) : [];
                        const hasStartTime = timeRows.some((timeRow) => getRowValue(timeRow, config.timeStartKey) !== '');

                        if (startDate && hasStartTime) {
                            latestValidRow = row;
                        }
                    });

                    return latestValidRow;
                };

                const isSeedReady = (postbox) => {
                    const seedRow = getSeedRow(postbox);
                    return !!(seedRow && seedRow.startDate && seedRow.startTime);
                };

                const getRepeaterAddButton = (fieldEl) => {
                    if (!(fieldEl instanceof HTMLElement)) {
                        return null;
                    }

                    const input = fieldEl.querySelector(':scope > .acf-input') || fieldEl.querySelector('.acf-input');
                    if (!(input instanceof HTMLElement)) {
                        return null;
                    }

                    const repeater = input.querySelector(':scope > .acf-repeater') || input.querySelector('.acf-repeater');
                    if (!(repeater instanceof HTMLElement)) {
                        return null;
                    }

                    const actions = repeater.querySelector(':scope > .acf-actions') || repeater.querySelector('.acf-actions');
                    if (!(actions instanceof HTMLElement)) {
                        return null;
                    }

                    return actions.querySelector('.acf-button.button[data-event="add-row"]');
                };

                const waitForRepeaterAppend = (fieldEl, trigger) => new Promise((resolve, reject) => {
                    const initialCount = getRepeaterRows(fieldEl).length;
                    let settled = false;
                    let timeoutId = 0;

                    const cleanup = () => {
                        if (timeoutId) {
                            window.clearTimeout(timeoutId);
                        }

                        if (typeof acf.removeAction === 'function') {
                            acf.removeAction('append', onAppend);
                        }
                    };

                    const maybeResolve = () => {
                        const rows = getRepeaterRows(fieldEl);
                        if (rows.length <= initialCount) {
                            return false;
                        }

                        const row = rows[rows.length - 1];
                        if (!(row instanceof HTMLElement)) {
                            return false;
                        }

                        settled = true;
                        cleanup();
                        resolve(row);
                        return true;
                    };

                    const onAppend = (appended) => {
                        if (settled) {
                            return;
                        }

                        const appendedEl = toDom(appended);
                        if (!(appendedEl instanceof HTMLElement)) {
                            return;
                        }

                        if (!fieldEl.contains(appendedEl) && appendedEl !== fieldEl) {
                            return;
                        }

                        window.requestAnimationFrame(() => {
                            maybeResolve();
                        });
                    };

                    if (typeof acf.addAction === 'function') {
                        acf.addAction('append', onAppend);
                    }

                    timeoutId = window.setTimeout(() => {
                        if (settled) {
                            return;
                        }

                        if (maybeResolve()) {
                            return;
                        }

                        cleanup();
                        reject(new Error('ACF did not append the expected repeater row.'));
                    }, 1500);

                    trigger();
                    window.requestAnimationFrame(() => {
                        maybeResolve();
                    });
                });

                const appendNativeRepeaterRow = async (fieldEl) => {
                    const addButton = getRepeaterAddButton(fieldEl);
                    if (!(addButton instanceof HTMLAnchorElement)) {
                        throw new Error('The repeater add-row button could not be found.');
                    }

                    return waitForRepeaterAppend(fieldEl, () => {
                        addButton.click();
                    });
                };

                const appendGeneratedTimesRows = async (timesFieldEl, rows) => {
                    if (!(timesFieldEl instanceof HTMLElement)) {
                        return [];
                    }

                    const normalizedRows = Array.isArray(rows) && rows.length
                        ? rows
                        : [{ start: '', end: '' }];
                    let timeRows = getRepeaterRows(timesFieldEl);

                    while (timeRows.length < normalizedRows.length) {
                        await appendNativeRepeaterRow(timesFieldEl);
                        await nextFrame();
                        timeRows = getRepeaterRows(timesFieldEl);
                    }

                    return timeRows.slice(0, normalizedRows.length);
                };

                const appendGeneratedDatesRows = async (postbox, rows) => {
                    const datesFieldEl = getDatesFieldEl(postbox);
                    if (!(datesFieldEl instanceof HTMLElement)) {
                        return;
                    }

                    const normalizedRows = Array.isArray(rows) ? rows : [];
                    const appendedRowData = [];

                    for (const rowData of normalizedRows) {
                        const row = await appendNativeRepeaterRow(datesFieldEl);
                        await nextFrame();

                        const timesFieldEl = row.querySelector(`.acf-field[data-key="${config.timesFieldKey}"]`);
                        const timeRows = await appendGeneratedTimesRows(timesFieldEl, rowData?.times || []);

                        appendedRowData.push({
                            row,
                            rowData,
                            timeRows,
                        });
                    }

                    await nextFrame();
                    await nextFrame();

                    for (const { row, rowData, timeRows } of appendedRowData) {
                        const startDateField = row.querySelector(`.acf-field[data-key="${config.dateStartKey}"]`);

                        await reinitializePickerField(startDateField);

                        for (const timeRow of timeRows) {
                            await reinitializePickerField(timeRow.querySelector(`.acf-field[data-key="${config.timeStartKey}"]`));
                            await reinitializePickerField(timeRow.querySelector(`.acf-field[data-key="${config.timeEndKey}"]`));
                        }

                        setFieldValue(startDateField, String(rowData?.date?.start || ''));

                        timeRows.forEach((timeRow, index) => {
                            const time = Array.isArray(rowData?.times) ? (rowData.times[index] || { start: '', end: '' }) : { start: '', end: '' };
                            setFieldValue(timeRow.querySelector(`.acf-field[data-key="${config.timeStartKey}"]`), String(time.start || ''));
                            setFieldValue(timeRow.querySelector(`.acf-field[data-key="${config.timeEndKey}"]`), String(time.end || ''));
                        });
                    }
                };

                const getControlField = (postbox, key) => {
                    const fieldEl = getFieldEl(postbox, key);
                    return {
                        element: fieldEl,
                        field: getAcfField(fieldEl),
                    };
                };

                const getControlInput = (postbox, key) => {
                    const fieldEl = getFieldEl(postbox, key);
                    const directInput = fieldEl instanceof HTMLElement
                        ? fieldEl.querySelector('input, select, textarea')
                        : null;

                    if (directInput instanceof HTMLInputElement || directInput instanceof HTMLSelectElement || directInput instanceof HTMLTextAreaElement) {
                        return directInput;
                    }

                    return null;
                };

                const setControlValue = (postbox, key, value) => {
                    const control = getControlField(postbox, key);
                    if (control.field && typeof control.field.val === 'function') {
                        try {
                            control.field.val(value);
                            return;
                        } catch (error) {
                        }
                    }

                    const input = getControlInput(postbox, key);
                    if (input instanceof HTMLInputElement || input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement) {
                        input.value = value;
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    } else if (control.element instanceof HTMLElement) {
                        setFieldValue(control.element, value);
                    }
                };

                const getControlValue = (postbox, key) => {
                    const control = getControlField(postbox, key);
                    if (control.field && typeof control.field.val === 'function') {
                        try {
                            const value = control.field.val();
                            return typeof value === 'string' ? value : String(value || '');
                        } catch (error) {
                        }
                    }

                    const input = getControlInput(postbox, key);
                    return input instanceof HTMLInputElement || input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement
                        ? String(input.value || '')
                        : '';
                };

                const isTruthyValue = (value) => {
                    if (typeof value === 'boolean') {
                        return value;
                    }

                    return ['1', 'true', 'yes', 'on'].includes(String(value || '').trim().toLowerCase());
                };

                const isGeneratorEnabled = (postbox) => isTruthyValue(getControlValue(postbox, config.generatorToggleFieldKey));
                const setRowDisabledState = (row, disabled) => {
                    if (!(row instanceof HTMLElement)) {
                        return;
                    }

                    row.classList.toggle('mz-generator-locked-row', disabled);

                    row.querySelectorAll('input, select, textarea, button').forEach((control) => {
                        if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement || control instanceof HTMLButtonElement)) {
                            return;
                        }

                        if (disabled) {
                            if (!control.hasAttribute('data-generator-was-disabled')) {
                                control.setAttribute('data-generator-was-disabled', control.disabled ? '1' : '0');
                            }
                            control.disabled = true;
                        } else {
                            const wasDisabled = control.getAttribute('data-generator-was-disabled') === '1';
                            control.disabled = wasDisabled;
                            control.removeAttribute('data-generator-was-disabled');
                        }
                    });

                    row.querySelectorAll('a').forEach((link) => {
                        if (!(link instanceof HTMLAnchorElement)) {
                            return;
                        }

                        if (disabled) {
                            if (!link.hasAttribute('data-generator-was-tabindex')) {
                                link.setAttribute('data-generator-was-tabindex', link.getAttribute('tabindex') ?? '');
                            }
                            link.classList.add('disabled');
                            link.setAttribute('aria-disabled', 'true');
                            link.tabIndex = -1;
                        } else {
                            link.classList.remove('disabled');
                            link.removeAttribute('aria-disabled');
                            const previousTabIndex = link.getAttribute('data-generator-was-tabindex');
                            if (previousTabIndex === null || previousTabIndex === '') {
                                link.removeAttribute('tabindex');
                            } else {
                                link.tabIndex = Number(previousTabIndex);
                            }
                            link.removeAttribute('data-generator-was-tabindex');
                        }
                    });
                };

                const syncDatesRowLocking = (postbox) => {
                    const datesFieldEl = getDatesFieldEl(postbox);
                    if (!(datesFieldEl instanceof HTMLElement)) {
                        return;
                    }

                    const generatorEnabled = isGeneratorEnabled(postbox);
                    const latestValidRow = getLatestValidDatesRow(postbox);
                    const rows = getRepeaterRows(datesFieldEl);

                    rows.forEach((row) => {
                        const shouldDisable = generatorEnabled && latestValidRow instanceof HTMLElement && row !== latestValidRow;
                        setRowDisabledState(row, shouldDisable);
                    });
                };

                const syncActiveState = (postbox) => {
                    const generatorEnabled = isGeneratorEnabled(postbox);
                    const recurrenceField = getFieldEl(postbox, config.recurrenceFieldKey);
                    const recurrenceCapField = getFieldEl(postbox, config.recurrenceCapFieldKey);
                    const addDateButton = getDatesAddButton(postbox);
                    const datesFieldEl = getDatesFieldEl(postbox);

                    postbox.classList.toggle('is-generator-active', generatorEnabled);

                    [recurrenceField, recurrenceCapField].forEach((field) => {
                        if (!(field instanceof HTMLElement)) {
                            return;
                        }

                        field.style.display = generatorEnabled ? '' : 'none';
                    });

                    if (addDateButton instanceof HTMLAnchorElement) {
                        addDateButton.classList.toggle('disabled', generatorEnabled);
                        addDateButton.setAttribute('aria-disabled', generatorEnabled ? 'true' : 'false');
                        addDateButton.tabIndex = generatorEnabled ? -1 : 0;
                    }

                    if (datesFieldEl instanceof HTMLElement) {
                        disableRepeaterManualSorting(datesFieldEl);
                    }

                    syncDatesRowLocking(postbox);
                };
                const hasUserEditedCap = (postbox) => {
                    const capField = getFieldEl(postbox, config.recurrenceCapFieldKey);
                    return capField instanceof HTMLElement && capField.dataset.userEditedCap === '1';
                };
                const setUserEditedCap = (postbox, edited) => {
                    const capField = getFieldEl(postbox, config.recurrenceCapFieldKey);
                    if (!(capField instanceof HTMLElement)) {
                        return;
                    }

                    capField.dataset.userEditedCap = edited ? '1' : '0';
                };

                const setBusyState = (postbox, busy) => {
                    const state = getState(postbox);
                    state.busy = busy;

                    const button = postbox.querySelector('.mz-event-generator-run-button');
                    if (button instanceof HTMLButtonElement) {
                        button.classList.toggle('is-busy', busy);
                        button.disabled = busy;
                        button.textContent = busy ? 'Generating…' : 'Generate Dates';
                    }
                };

                const syncGeneratorButtons = (postbox) => {
                    const state = getState(postbox);
                    const seedReady = isSeedReady(postbox);
                    const generatorEnabled = isGeneratorEnabled(postbox);
                    const runButton = postbox.querySelector('.mz-event-generator-run-button');

                    if (runButton instanceof HTMLButtonElement) {
                        runButton.classList.toggle('is-visible', generatorEnabled && seedReady);
                        runButton.disabled = state.busy || !seedReady || !generatorEnabled;
                    }
                };

                const generateRows = async (postbox) => {
                    const state = getState(postbox);
                    if (state.busy || !isGeneratorEnabled(postbox)) {
                        return;
                    }

                    setBusyState(postbox, true);

                    try {
                        const recurrence = getControlValue(postbox, config.recurrenceFieldKey) || config.defaultRecurrence;
                        const recurrenceCap = getControlValue(postbox, config.recurrenceCapFieldKey) || String(config.defaultCap || 3);
                        const currentRows = collectDatesRows(postbox);

                        if (!currentRows.length || !isSeedReady(postbox)) {
                            throw new Error('Add the first event start date and start time before generating recurring dates.');
                        }

                        const body = new URLSearchParams({
                            action: config.action,
                            nonce: config.nonce,
                            recurrence,
                            recurrence_cap: recurrenceCap,
                            dates: JSON.stringify(currentRows),
                        });

                        const response = await fetch(config.ajaxUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                            },
                            body: body.toString(),
                            credentials: 'same-origin',
                        });

                        const payload = await response.json();
                        if (!response.ok || !payload?.success) {
                            throw new Error(payload?.data?.message || 'Recurring dates could not be generated.');
                        }

                        const generatedRows = Array.isArray(payload.data?.dates) ? payload.data.dates : [];
                        const rowsToAppend = generatedRows.slice(currentRows.length);

                        setControlValue(postbox, config.generatorToggleFieldKey, 0);
                        syncGeneratorDefaults(postbox);

                        if (rowsToAppend.length) {
                            await appendGeneratedDatesRows(postbox, rowsToAppend);
                        }

                        syncGeneratorDefaults(postbox);
                    } catch (error) {
                        window.alert(error instanceof Error ? error.message : 'Recurring dates could not be generated.');
                    } finally {
                        setBusyState(postbox, false);
                        syncGeneratorButtons(postbox);
                    }
                };

                const ensureRunButton = (postbox) => {
                    const toggleField = getFieldEl(postbox, config.generatorToggleFieldKey);
                    if (!(toggleField instanceof HTMLElement)) {
                        return;
                    }

                    const input = toggleField.querySelector(':scope > .acf-input') || toggleField.querySelector('.acf-input');
                    if (!(input instanceof HTMLElement) || input.querySelector('.mz-event-generator-run-button')) {
                        return;
                    }

                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'button button-primary mz-event-generator-run-button';
                    button.textContent = 'Generate Dates';
                    button.addEventListener('click', (event) => {
                        event.preventDefault();
                        generateRows(postbox);
                    });

                    input.appendChild(button);
                };

                const bindCapEditTracking = (postbox) => {
                    const capField = getFieldEl(postbox, config.recurrenceCapFieldKey);
                    if (!(capField instanceof HTMLElement) || capField.dataset.capTrackingBound === '1') {
                        return;
                    }

                    const input = getControlInput(postbox, config.recurrenceCapFieldKey);
                    if (!(input instanceof HTMLInputElement || input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement)) {
                        return;
                    }

                    const markEdited = () => {
                        setUserEditedCap(postbox, true);
                    };

                    input.addEventListener('change', markEdited);
                    input.addEventListener('input', markEdited);
                    capField.dataset.capTrackingBound = '1';
                };

                const bindRecurrenceBehavior = (postbox) => {
                    const recurrenceField = getFieldEl(postbox, config.recurrenceFieldKey);
                    if (!(recurrenceField instanceof HTMLElement) || recurrenceField.dataset.recurrenceBehaviorBound === '1') {
                        return;
                    }

                    const syncCapForRecurrence = () => {
                        if (hasUserEditedCap(postbox)) {
                            syncGeneratorButtons(postbox);
                            return;
                        }

                        const recurrence = getControlValue(postbox, config.recurrenceFieldKey);
                        const nextCap = recurrence === 'weekly'
                            ? '1'
                            : String(config.defaultCap || 3);

                        setControlValue(postbox, config.recurrenceCapFieldKey, nextCap);
                        syncGeneratorButtons(postbox);
                    };

                    recurrenceField.addEventListener('change', syncCapForRecurrence);
                    recurrenceField.addEventListener('input', syncCapForRecurrence);
                    recurrenceField.dataset.recurrenceBehaviorBound = '1';
                };

                const syncGeneratorDefaults = (postbox) => {
                    if (isGeneratorEnabled(postbox)) {
                        const recurrence = getControlValue(postbox, config.recurrenceFieldKey);
                        const recurrenceCap = getControlValue(postbox, config.recurrenceCapFieldKey);

                        if (!recurrence || recurrence === 'single') {
                            setControlValue(postbox, config.recurrenceFieldKey, config.defaultRecurrence);
                        }

                        if (!recurrenceCap) {
                            setControlValue(postbox, config.recurrenceCapFieldKey, String(config.defaultCap || 3));
                        }

                        if (!hasUserEditedCap(postbox)) {
                            const nextRecurrence = getControlValue(postbox, config.recurrenceFieldKey);
                            setControlValue(
                                postbox,
                                config.recurrenceCapFieldKey,
                                nextRecurrence === 'weekly' ? '1' : String(config.defaultCap || 3)
                            );
                        }
                    }

                    syncActiveState(postbox);
                    syncGeneratorButtons(postbox);
                };

                const bindGeneratorToggle = (postbox) => {
                    const toggleField = getFieldEl(postbox, config.generatorToggleFieldKey);
                    if (!(toggleField instanceof HTMLElement) || toggleField.dataset.generatorToggleBound === '1') {
                        return;
                    }

                    const refresh = () => {
                        syncGeneratorDefaults(postbox);
                    };

                    toggleField.addEventListener('change', refresh);
                    toggleField.addEventListener('input', refresh);
                    toggleField.dataset.generatorToggleBound = '1';
                };

                const bindSeedWatchers = (postbox) => {
                    if (postbox.dataset.generatorSeedWatchersBound === '1') {
                        return;
                    }

                    const refresh = () => {
                        if (getState(postbox).busy) {
                            return;
                        }

                        syncActiveState(postbox);
                        syncGeneratorButtons(postbox);
                    };

                    postbox.addEventListener('change', refresh);
                    postbox.addEventListener('input', refresh);
                    postbox.dataset.generatorSeedWatchersBound = '1';
                };

                const bindDatesAddButtonGuard = (postbox) => {
                    const addDateButton = getDatesAddButton(postbox);
                    if (!(addDateButton instanceof HTMLAnchorElement) || addDateButton.dataset.generatorGuardBound === '1') {
                        return;
                    }

                    addDateButton.addEventListener('click', (event) => {
                        if (!isGeneratorEnabled(postbox)) {
                            return;
                        }

                        event.preventDefault();
                        event.stopImmediatePropagation();
                    }, true);

                    addDateButton.dataset.generatorGuardBound = '1';
                };

                const bindDatesRowGuard = (postbox) => {
                    const datesField = getDatesFieldEl(postbox);
                    if (!(datesField instanceof HTMLElement) || datesField.dataset.generatorRowGuardBound === '1') {
                        return;
                    }

                    datesField.addEventListener('click', (event) => {
                        if (!isGeneratorEnabled(postbox)) {
                            return;
                        }

                        const target = event.target instanceof HTMLElement ? event.target : null;
                        if (!(target instanceof HTMLElement)) {
                            return;
                        }

                        const lockedRow = target.closest('.mz-generator-locked-row');
                        if (!(lockedRow instanceof HTMLElement)) {
                            return;
                        }

                        event.preventDefault();
                        event.stopImmediatePropagation();
                    }, true);

                    datesField.dataset.generatorRowGuardBound = '1';
                };

                const boot = () => {
                    bindPickerAppendRepair();

                    const postbox = getPostbox();
                    if (!(postbox instanceof HTMLElement)) {
                        return;
                    }

                    ensureRunButton(postbox);
                    bindCapEditTracking(postbox);
                    bindRecurrenceBehavior(postbox);
                    bindGeneratorToggle(postbox);
                    bindSeedWatchers(postbox);
                    bindDatesAddButtonGuard(postbox);
                    bindDatesRowGuard(postbox);
                    syncGeneratorDefaults(postbox);
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', boot, { once: true });
                } else {
                    boot();
                }

            })();
        </script>
<?php
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

if (!function_exists(__NAMESPACE__ . '\\mz_get_event_timezone')) {
    function mz_get_event_timezone(): DateTimeZone
    {
        $timezone = function_exists('wp_timezone') ? wp_timezone() : null;
        if ($timezone instanceof DateTimeZone) {
            return $timezone;
        }

        $timezone_string = function_exists('wp_timezone_string') ? wp_timezone_string() : '';
        if (is_string($timezone_string) && $timezone_string !== '') {
            try {
                return new DateTimeZone($timezone_string);
            } catch (Exception $e) {
            }
        }

        return new DateTimeZone('UTC');
    }
}

if (!function_exists(__NAMESPACE__ . '\\mz_parse_event_datetime')) {
    function mz_parse_event_datetime(string $value, DateTimeZone $timezone): ?DateTime
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return new DateTime($value, $timezone);
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists(__NAMESPACE__ . '\\mz_build_event_datetime')) {
    function mz_build_event_datetime(string $date, string $time, DateTimeZone $timezone): ?DateTime
    {
        $date = trim($date);
        $time = trim($time);

        if ($date === '' || $time === '') {
            return null;
        }

        $candidates = [
            $date . ' ' . $time,
        ];

        if (preg_match('/^\d{1,2}:\d{2}$/', $time) === 1) {
            $candidates[] = $date . ' ' . $time . ':00';
        }

        foreach ($candidates as $candidate) {
            try {
                return new DateTime($candidate, $timezone);
            } catch (Exception $e) {
            }
        }

        return null;
    }
}

if (!function_exists(__NAMESPACE__ . '\\mz_normalize_event_time_value')) {
    function mz_normalize_event_time_value(string $value, DateTimeZone $timezone): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $formats = ['H:i:s', 'H:i', 'g:i a', 'g:ia', 'ga'];
        foreach ($formats as $format) {
            $time = DateTime::createFromFormat($format, strtolower($value), $timezone);
            if ($time instanceof DateTime) {
                return strtolower($time->format('g:i a'));
            }
        }

        try {
            return strtolower((new DateTime($value, $timezone))->format('g:i a'));
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists(__NAMESPACE__ . '\\mz_normalize_event_date_value')) {
    function mz_normalize_event_date_value(string $value, DateTimeZone $timezone): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        try {
            return (new DateTime($value, $timezone))->format('Y-m-d');
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists(__NAMESPACE__ . '\\mz_build_event_dates_payload')) {
    function mz_build_event_dates_payload(DateTime $start_date, DateTime $end_date): array
    {
        return [
            [
                'date' => [
                    'start' => $start_date->format('F j, Y'),
                    'end' => $end_date->format('Y-m-d') !== $start_date->format('Y-m-d')
                        ? $end_date->format('F j, Y')
                        : '',
                ],
                'times' => [
                    [
                        'start' => strtolower($start_date->format('g:i a')),
                        'end' => strtolower($end_date->format('g:i a')),
                    ],
                ],
            ],
        ];
    }
}

if (!function_exists(__NAMESPACE__ . '\\mz_format_event_time_label')) {
    function mz_format_event_time_label(DateTime $datetime): string
    {
        $mins = wp_date('i', $datetime->getTimestamp(), $datetime->getTimezone());
        $format = ($mins === '00') ? 'g a' : 'g:i a';
        return strtolower(wp_date($format, $datetime->getTimestamp(), $datetime->getTimezone()));
    }
}

if (!function_exists(__NAMESPACE__ . '\\mz_join_event_time_labels')) {
    function mz_join_event_time_labels(array $labels): string
    {
        $labels = array_values(array_filter(array_map('trim', $labels)));
        $count = count($labels);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $labels[0];
        }

        if ($count === 2) {
            return $labels[0] . ' & ' . $labels[1];
        }

        $last = array_pop($labels);
        return implode(', ', $labels) . ', and ' . $last;
    }
}

if (!function_exists(__NAMESPACE__ . '\\mz_build_event_schedule_row')) {
    function mz_build_event_schedule_row(int $post_id, array $row, int $row_index, DateTimeZone $timezone): ?array
    {
        $date_group = isset($row['date']) && is_array($row['date'])
            ? $row['date']
            : [];
        $date_raw = mz_normalize_event_date_value((string) ($date_group['start'] ?? ''), $timezone);
        if ($date_raw === '') {
            return null;
        }

        $end_date_raw = mz_normalize_event_date_value((string) ($date_group['end'] ?? ''), $timezone);
        $times_rows = isset($row['times']) && is_array($row['times'])
            ? $row['times']
            : [];

        $time_slots = [];
        foreach ($times_rows as $time_index => $time_row) {
            if (!is_array($time_row)) {
                continue;
            }

            $start_value = mz_normalize_event_time_value((string) ($time_row['start'] ?? $time_row['time_start'] ?? ''), $timezone);
            if ($start_value === '') {
                continue;
            }

            $slot_start = mz_build_event_datetime($date_raw, $start_value, $timezone);
            if (!($slot_start instanceof DateTime)) {
                continue;
            }

            $end_value = mz_normalize_event_time_value((string) ($time_row['end'] ?? $time_row['time_end'] ?? ''), $timezone);
            $slot_end = $end_value !== ''
                ? mz_build_event_datetime($end_date_raw !== '' ? $end_date_raw : $date_raw, $end_value, $timezone)
                : null;

            if (!($slot_end instanceof DateTime) || $slot_end <= $slot_start) {
                $slot_end = (clone $slot_start)->add(new DateInterval('PT90M'));
            }

            $time_slots[] = [
                'label' => mz_format_event_time_label($slot_start),
                'anchor_id' => 'addtocalendar_time_' . $post_id . '_' . $row_index . '_' . $time_index,
                'start_datetime' => $slot_start->format('Ymd\THis'),
                'end_datetime' => $slot_end->format('Ymd\THis'),
                'start_iso' => $slot_start->format('Y-m-d\TH:i:s'),
                'end_iso' => $slot_end->format('Y-m-d\TH:i:s'),
            ];
        }

        $start_obj = null;
        $end_obj = null;
        if ($time_slots !== []) {
            $first_slot = $time_slots[0];
            $last_slot = $time_slots[count($time_slots) - 1];
            $start_obj = new DateTime($first_slot['start_iso'], $timezone);
            $end_obj = new DateTime($last_slot['end_iso'], $timezone);
        } else {
            $start_obj = mz_build_event_datetime($date_raw, '12:00 am', $timezone);
            $end_obj = $start_obj instanceof DateTime
                ? (clone $start_obj)->add(new DateInterval('PT90M'))
                : null;
        }

        if (!($start_obj instanceof DateTime)) {
            return null;
        }

        if (!($end_obj instanceof DateTime) || $end_obj < $start_obj) {
            $end_obj = (clone $start_obj)->add(new DateInterval('PT90M'));
        }

        return [
            'date_ymd' => $date_raw,
            'date_label' => wp_date('F j, Y', $start_obj->getTimestamp(), $timezone),
            'times_label' => mz_join_event_time_labels(array_column($time_slots, 'label')),
            'time_slots' => $time_slots,
            'start_obj' => $start_obj,
            'end_obj' => $end_obj,
        ];
    }
}

if (!function_exists(__NAMESPACE__ . '\\mz_build_event_schedule_from_dates_rows')) {
    function mz_build_event_schedule_from_dates_rows(int $post_id, array $dates_rows, DateTimeZone $timezone): ?array
    {
        $rows = [];
        foreach ($dates_rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $parsed = mz_build_event_schedule_row($post_id, $row, (int) $index, $timezone);
            if (is_array($parsed)) {
                $rows[] = $parsed;
            }
        }

        if ($rows === []) {
            return null;
        }

        $now = new DateTime('now', $timezone);
        $selected = null;
        foreach ($rows as $row) {
            if (($row['end_obj'] ?? null) instanceof DateTime && $row['end_obj'] >= $now) {
                $selected = $row;
                break;
            }
        }

        if (!is_array($selected)) {
            $selected = $rows[count($rows) - 1];
        }

        $latest_end = null;
        foreach ($rows as $row) {
            if (!($row['end_obj'] ?? null) instanceof DateTime) {
                continue;
            }

            if (!($latest_end instanceof DateTime) || $row['end_obj'] > $latest_end) {
                $latest_end = clone $row['end_obj'];
            }
        }

        $is_past = $latest_end instanceof DateTime ? $latest_end < $now : false;

        return [
            'date_ymd' => (string) ($selected['date_ymd'] ?? ''),
            'date_label' => (string) ($selected['date_label'] ?? ''),
            'times_label' => (string) ($selected['times_label'] ?? ''),
            'time_slots' => is_array($selected['time_slots'] ?? null) ? $selected['time_slots'] : [],
            'start_datetime' => ($selected['start_obj'] ?? null) instanceof DateTime ? $selected['start_obj']->format('Y-m-d H:i:s') : '',
            'end_datetime' => ($selected['end_obj'] ?? null) instanceof DateTime ? $selected['end_obj']->format('Y-m-d H:i:s') : '',
            'start_obj' => ($selected['start_obj'] ?? null) instanceof DateTime ? clone $selected['start_obj'] : null,
            'end_obj' => ($selected['end_obj'] ?? null) instanceof DateTime ? clone $selected['end_obj'] : null,
            'upcoming_until_mysql' => $latest_end instanceof DateTime ? $latest_end->format('Y-m-d H:i:s') : '',
            'is_past' => $is_past,
            'is_upcoming' => !$is_past,
        ];
    }
}

if (!function_exists(__NAMESPACE__ . '\\mz_get_event_schedule')) {
    function mz_get_event_schedule(int $post_id): array
    {
        $timezone = mz_get_event_timezone();
        $now = new DateTime('now', $timezone);

        $date_raw = '';
        $end_date_raw = '';
        $times_rows = [];

        if (function_exists('get_field')) {
            $dates_rows = get_field('dates', $post_id) ?: [];
            if (is_array($dates_rows)) {
                $normalized_schedule = mz_build_event_schedule_from_dates_rows($post_id, $dates_rows, $timezone);
                if (is_array($normalized_schedule)) {
                    return $normalized_schedule;
                }
            }

            if (is_array($dates_rows) && !empty($dates_rows[0]) && is_array($dates_rows[0])) {
                $first_date_row = $dates_rows[0];
                $date_group = isset($first_date_row['date']) && is_array($first_date_row['date'])
                    ? $first_date_row['date']
                    : [];

                $date_raw = mz_normalize_event_date_value((string) ($date_group['start'] ?? ''), $timezone);
                $end_date_raw = mz_normalize_event_date_value((string) ($date_group['end'] ?? ''), $timezone);

                $candidate_times = $first_date_row['times'] ?? [];
                if (is_array($candidate_times)) {
                    $times_rows = $candidate_times;
                }
            }
        }

        if (function_exists('get_field') && $date_raw === '') {
            $date_raw = (string) (get_field('date_start', $post_id) ?: '');
        }
        if ($date_raw === '') {
            $date_raw = (string) get_post_meta($post_id, 'date_start', true);
        }
        $date_raw = trim($date_raw);

        $start_raw = trim((string) get_post_meta($post_id, 'start_datetime', true));
        if ($start_raw === '' && function_exists('get_field')) {
            $start_raw = trim((string) (get_field('start_datetime', $post_id) ?: ''));
        }

        $end_raw = trim((string) get_post_meta($post_id, 'end_datetime', true));
        if ($end_raw === '' && function_exists('get_field')) {
            $end_raw = trim((string) (get_field('end_datetime', $post_id) ?: ''));
        }

        if ($times_rows === [] && function_exists('get_field')) {
            $times_rows = get_field('times', $post_id) ?: [];
        }
        if (!is_array($times_rows)) {
            $times_rows = [];
        }

        $time_values = [];
        $end_time_values = [];
        foreach ($times_rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $raw_time = trim((string) ($row['start'] ?? $row['time_start'] ?? ''));
            if ($raw_time !== '') {
                $time_values[] = $raw_time;
            }

            $raw_end_time = trim((string) ($row['end'] ?? $row['time_end'] ?? ''));
            if ($raw_end_time !== '') {
                $end_time_values[] = $raw_end_time;
            }
        }

        $start_obj = mz_parse_event_datetime($start_raw, $timezone);
        $end_obj = mz_parse_event_datetime($end_raw, $timezone);

        if ($date_raw === '' && $start_obj instanceof DateTime) {
            $date_raw = $start_obj->format('Y-m-d');
        }

        if (!($start_obj instanceof DateTime) && $date_raw !== '') {
            $start_obj = mz_build_event_datetime($date_raw, $time_values[0] ?? '00:00', $timezone);
        }

        if (!($end_obj instanceof DateTime)) {
            if ($date_raw !== '' && !empty($end_time_values)) {
                $end_date_for_time = $end_date_raw !== '' ? $end_date_raw : $date_raw;
                $candidate_end = mz_build_event_datetime($end_date_for_time, $end_time_values[count($end_time_values) - 1], $timezone);
                if ($candidate_end instanceof DateTime) {
                    $end_obj = $candidate_end;
                }
            }

            if ($date_raw !== '' && !empty($time_values)) {
                $last_time = $time_values[count($time_values) - 1];
                $end_date_for_time = $end_date_raw !== '' ? $end_date_raw : $date_raw;
                $candidate_end = mz_build_event_datetime($end_date_for_time, $last_time, $timezone);
                if ($candidate_end instanceof DateTime && $start_obj instanceof DateTime && $candidate_end > $start_obj) {
                    $end_obj = $candidate_end;
                }
            }

            if (!($end_obj instanceof DateTime) && $start_obj instanceof DateTime) {
                $end_obj = (clone $start_obj)->add(new DateInterval('PT90M'));
            }

            if (!($end_obj instanceof DateTime) && $date_raw !== '') {
                $end_obj = mz_build_event_datetime($date_raw, '23:59', $timezone);
            }
        }

        if (!($start_obj instanceof DateTime) && $end_obj instanceof DateTime) {
            $start_obj = (clone $end_obj)->sub(new DateInterval('PT90M'));
        }

        if ($start_obj instanceof DateTime && $end_obj instanceof DateTime && $end_obj < $start_obj) {
            $end_obj = (clone $start_obj)->add(new DateInterval('PT90M'));
        }

        $date_ymd = $date_raw !== ''
            ? $date_raw
            : ($start_obj instanceof DateTime ? $start_obj->format('Y-m-d') : '');

        $time_slots = [];
        if ($date_ymd !== '' && !empty($time_values)) {
            foreach ($times_rows as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }

                $raw_time = trim((string) ($row['start'] ?? $row['time_start'] ?? ''));
                if ($raw_time === '') {
                    continue;
                }

                $slot_start = mz_build_event_datetime($date_ymd, $raw_time, $timezone);
                if (!($slot_start instanceof DateTime)) {
                    continue;
                }

                $raw_end_time = trim((string) ($row['end'] ?? $row['time_end'] ?? ''));
                $slot_end = $raw_end_time !== ''
                    ? mz_build_event_datetime($end_date_raw !== '' ? $end_date_raw : $date_ymd, $raw_end_time, $timezone)
                    : null;

                if (!($slot_end instanceof DateTime) || $slot_end <= $slot_start) {
                    $slot_end = (clone $slot_start)->add(new DateInterval('PT90M'));
                }

                if (
                    $index === count($times_rows) - 1
                    && $end_obj instanceof DateTime
                    && $end_obj > $slot_start
                ) {
                    $slot_end = clone $end_obj;
                }

                $time_slots[] = [
                    'label' => mz_format_event_time_label($slot_start),
                    'anchor_id' => 'addtocalendar_time_' . $post_id . '_' . $index,
                    'start_datetime' => $slot_start->format('Ymd\THis'),
                    'end_datetime' => $slot_end->format('Ymd\THis'),
                    'start_iso' => $slot_start->format('Y-m-d\TH:i:s'),
                    'end_iso' => $slot_end->format('Y-m-d\TH:i:s'),
                ];
            }
        }

        if (empty($time_slots) && $start_obj instanceof DateTime) {
            $slot_end = $end_obj instanceof DateTime
                ? clone $end_obj
                : (clone $start_obj)->add(new DateInterval('PT90M'));

            $time_slots[] = [
                'label' => mz_format_event_time_label($start_obj),
                'anchor_id' => 'addtocalendar_time_' . $post_id . '_0',
                'start_datetime' => $start_obj->format('Ymd\THis'),
                'end_datetime' => $slot_end->format('Ymd\THis'),
                'start_iso' => $start_obj->format('Y-m-d\TH:i:s'),
                'end_iso' => $slot_end->format('Y-m-d\TH:i:s'),
            ];
        }

        $time_labels = array_column($time_slots, 'label');
        $times_label = mz_join_event_time_labels($time_labels);

        $date_label = '';
        if ($start_obj instanceof DateTime) {
            $date_label = wp_date('F j, Y', $start_obj->getTimestamp(), $timezone);
        } elseif ($date_ymd !== '') {
            $date_label = date_i18n('F j, Y', strtotime($date_ymd));
        }

        $upcoming_until = $end_obj instanceof DateTime
            ? clone $end_obj
            : ($date_ymd !== '' ? mz_build_event_datetime($date_ymd, '23:59', $timezone) : null);

        $is_past = $upcoming_until instanceof DateTime ? $upcoming_until < $now : false;

        return [
            'date_ymd' => $date_ymd,
            'date_label' => $date_label,
            'times_label' => $times_label,
            'time_slots' => $time_slots,
            'start_datetime' => $start_obj instanceof DateTime ? $start_obj->format('Y-m-d H:i:s') : '',
            'end_datetime' => $end_obj instanceof DateTime ? $end_obj->format('Y-m-d H:i:s') : '',
            'start_obj' => $start_obj,
            'end_obj' => $end_obj,
            'upcoming_until_mysql' => $upcoming_until instanceof DateTime ? $upcoming_until->format('Y-m-d H:i:s') : '',
            'is_past' => $is_past,
            'is_upcoming' => !$is_past,
        ];
    }
}

if (!function_exists(__NAMESPACE__ . '\\mz_sync_event_schedule_meta')) {
    function mz_sync_event_schedule_meta(int $post_id): array
    {
        $schedule = mz_get_event_schedule($post_id);

        if ($schedule['date_ymd'] !== '') {
            update_post_meta($post_id, 'date_start', $schedule['date_ymd']);
        }

        if ($schedule['start_datetime'] !== '') {
            update_post_meta($post_id, 'start_datetime', $schedule['start_datetime']);
        }

        if ($schedule['end_datetime'] !== '') {
            update_post_meta($post_id, 'end_datetime', $schedule['end_datetime']);
        }

        if ($schedule['upcoming_until_mysql'] !== '') {
            update_post_meta($post_id, 'upcoming_until', $schedule['upcoming_until_mysql']);
        }

        return $schedule;
    }
}

if (!function_exists(__NAMESPACE__ . '\\mz_get_upcoming_event_meta_query')) {
    function mz_get_upcoming_event_meta_query(): array
    {
        return [
            'relation' => 'OR',
            [
                'key' => 'upcoming_until',
                'value' => current_time('mysql'),
                'compare' => '>=',
                'type' => 'DATETIME',
            ],
            [
                'relation' => 'AND',
                [
                    'key' => 'upcoming_until',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => 'date_start',
                    'value' => current_time('Y-m-d'),
                    'compare' => '>=',
                    'type' => 'DATE',
                ],
            ],
        ];
    }
}
