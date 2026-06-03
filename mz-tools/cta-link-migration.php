<?php

/**
 * Internal module for MZ Tools CTA temporal link migration.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MZCTLM_SYNC_OPTION')) {
    define('MZCTLM_SYNC_OPTION', 'mz_cta_temporal_link_migration_version');
}

if (!function_exists('mzctalm_get_sync_version')) {
    function mzctalm_get_sync_version(): string
    {
        return '2026-05-20-cta-temporal-link-v1';
    }
}

if (!function_exists('mzctalm_get_link_field_map')) {
    function mzctalm_get_link_field_map(): array
    {
        return [
            'link' => 'field_685d8cc4534bf',
            'link_secondary' => 'field_697ff50dc4122',
        ];
    }
}

if (!function_exists('mzctalm_normalize_link_value')) {
    function mzctalm_normalize_link_value($value): array
    {
        if (function_exists('meza_normalize_event_context_link_field_value')) {
            return meza_normalize_event_context_link_field_value($value);
        }

        if (is_string($value)) {
            $value = maybe_unserialize($value);
        }

        if (!is_array($value)) {
            $url = is_scalar($value) ? trim((string) $value) : '';
            if ($url === '') {
                return [];
            }

            return [
                'url' => $url,
                'title' => '',
                'target' => '',
            ];
        }

        $url = isset($value['url']) && !is_array($value['url'])
            ? trim((string) $value['url'])
            : '';
        $title = isset($value['title']) && !is_array($value['title'])
            ? trim((string) $value['title'])
            : '';
        $target = isset($value['target']) && !is_array($value['target'])
            ? trim((string) $value['target'])
            : '';

        if ($url === '') {
            return [];
        }

        return [
            'url' => $url,
            'title' => $title,
            'target' => $target,
        ];
    }
}

if (!function_exists('mzctalm_link_value_is_empty')) {
    function mzctalm_link_value_is_empty($value): bool
    {
        if (function_exists('meza_section_field_value_is_empty')) {
            return meza_section_field_value_is_empty($value);
        }

        return mzctalm_normalize_link_value($value) === [];
    }
}

if (!function_exists('mzctalm_get_group_slot_value')) {
    function mzctalm_get_group_slot_value($value, string $slot)
    {
        if (!is_array($value)) {
            return null;
        }

        if (array_key_exists($slot, $value)) {
            return $value[$slot];
        }

        if ($slot === 'before' && array_key_exists('base', $value)) {
            return $value['base'];
        }

        return null;
    }
}

if (!function_exists('mzctalm_sync_cta_link_meta_for_post')) {
    function mzctalm_sync_cta_link_meta_for_post(int $post_id): bool
    {
        if ($post_id <= 0) {
            return false;
        }

        $did_update = false;

        foreach (mzctalm_get_link_field_map() as $field_name => $field_key) {
            $base_value = get_post_meta($post_id, $field_name, true);
            $current_reference = (string) get_post_meta($post_id, '_' . $field_name, true);

            if (mzctalm_normalize_link_value($base_value) !== []) {
                if ($current_reference !== $field_key) {
                    update_post_meta($post_id, '_' . $field_name, $field_key);
                    $did_update = true;
                }
            }

            $before_meta_key = $field_name . '_before';
            $season_after_meta_key = $field_name . '_season_after';
            $before_field_key = function_exists('meza_get_season_context_value_field_key')
                ? meza_get_season_context_value_field_key($field_key, 'before')
                : '';
            $season_after_field_key = function_exists('meza_get_season_context_value_field_key')
                ? meza_get_season_context_value_field_key($field_key, 'season_after')
                : '';

            $before_candidate = mzctalm_get_group_slot_value($base_value, 'before');
            if (mzctalm_link_value_is_empty($before_candidate)) {
                $before_candidate = mzctalm_get_group_slot_value($base_value, 'base');
            }
            if (mzctalm_link_value_is_empty($before_candidate)) {
                $before_candidate = $base_value;
            }

            $season_after_candidate = mzctalm_get_group_slot_value($base_value, 'season_after');
            if (mzctalm_link_value_is_empty($season_after_candidate) && metadata_exists('post', $post_id, $season_after_meta_key)) {
                $season_after_candidate = get_post_meta($post_id, $season_after_meta_key, true);
            }

            $before_value = metadata_exists('post', $post_id, $before_meta_key)
                ? get_post_meta($post_id, $before_meta_key, true)
                : null;
            $season_after_value = metadata_exists('post', $post_id, $season_after_meta_key)
                ? get_post_meta($post_id, $season_after_meta_key, true)
                : null;

            $normalized_before_candidate = mzctalm_normalize_link_value($before_candidate);
            $normalized_season_after_candidate = mzctalm_normalize_link_value($season_after_candidate);

            if (
                $normalized_before_candidate !== []
                && mzctalm_link_value_is_empty($before_value)
            ) {
                update_post_meta($post_id, $before_meta_key, $normalized_before_candidate);
                if (function_exists('meza_update_event_context_meta_reference') && $before_field_key !== '') {
                    meza_update_event_context_meta_reference($post_id, $before_meta_key, $before_field_key);
                } else {
                    update_post_meta($post_id, '_' . $before_meta_key, $before_field_key);
                }
                $did_update = true;
            } elseif ($normalized_before_candidate !== [] && $before_field_key !== '') {
                $current_before_reference = (string) get_post_meta($post_id, '_' . $before_meta_key, true);
                if ($current_before_reference !== $before_field_key) {
                    update_post_meta($post_id, '_' . $before_meta_key, $before_field_key);
                    $did_update = true;
                }
            }

            if (
                $normalized_season_after_candidate !== []
                && mzctalm_link_value_is_empty($season_after_value)
            ) {
                update_post_meta($post_id, $season_after_meta_key, $normalized_season_after_candidate);
                if (function_exists('meza_update_event_context_meta_reference') && $season_after_field_key !== '') {
                    meza_update_event_context_meta_reference($post_id, $season_after_meta_key, $season_after_field_key);
                } else {
                    update_post_meta($post_id, '_' . $season_after_meta_key, $season_after_field_key);
                }
                $did_update = true;
            } elseif ($normalized_season_after_candidate !== [] && $season_after_field_key !== '') {
                $current_season_after_reference = (string) get_post_meta($post_id, '_' . $season_after_meta_key, true);
                if ($current_season_after_reference !== $season_after_field_key) {
                    update_post_meta($post_id, '_' . $season_after_meta_key, $season_after_field_key);
                    $did_update = true;
                }
            }
        }

        return $did_update;
    }
}

if (!function_exists('mzctalm_run_cta_link_migration')) {
    function mzctalm_run_cta_link_migration(): void
    {
        $version = mzctalm_get_sync_version();
        if ((string) get_option(MZCTLM_SYNC_OPTION, '') === $version) {
            return;
        }

        $cta_posts = get_posts([
            'post_type' => 'cta',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'suppress_filters' => false,
            'cache_results' => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        foreach ($cta_posts as $post_id) {
            mzctalm_sync_cta_link_meta_for_post((int) $post_id);
        }

        update_option(MZCTLM_SYNC_OPTION, $version, false);
    }
}
add_action('acf/init', 'mzctalm_run_cta_link_migration', 25);
