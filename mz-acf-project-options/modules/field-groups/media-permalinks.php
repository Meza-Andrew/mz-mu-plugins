<?php

if (!function_exists('meza_get_theme_thumbnail_id')) {
    function meza_get_theme_thumbnail_id(): int
    {
        $thumbnail_id = (int) get_option('meza_theme_thumbnail_id', 0);

        return $thumbnail_id > 0 && get_post_type($thumbnail_id) === 'attachment'
            ? $thumbnail_id
            : 0;
    }
}

if (!function_exists('meza_get_social_share_default_id')) {
    function meza_get_social_share_default_id(): int
    {
        $default_id = (int) get_option('meza_social_share_default_id', 0);
        if ($default_id > 0 && get_post_type($default_id) === 'attachment') {
            return $default_id;
        }

        return meza_get_theme_thumbnail_id();
    }
}

if (!function_exists('meza_get_social_share_default_url')) {
    function meza_get_social_share_default_url(): string
    {
        $default_id = meza_get_social_share_default_id();
        if ($default_id <= 0) {
            return '';
        }

        $default_url = wp_get_attachment_image_url($default_id, 'full');

        return is_string($default_url) ? $default_url : '';
    }
}

if (!function_exists('meza_post_uses_share_image_permalink')) {
    function meza_post_uses_share_image_permalink(int $post_id): bool
    {
        $post = get_post($post_id);
        if (!($post instanceof WP_Post)) {
            return false;
        }

        if (in_array((string) $post->post_status, ['auto-draft', 'trash'], true)) {
            return false;
        }

        $post_type = trim((string) $post->post_type);
        if ($post_type === '') {
            return false;
        }

        $post_type_object = get_post_type_object($post_type);
        if (!($post_type_object instanceof WP_Post_Type)) {
            return false;
        }

        if (function_exists('is_post_type_viewable') && !is_post_type_viewable($post_type_object)) {
            return false;
        }

        if ($post_type === 'post' || $post_type === 'page') {
            return true;
        }

        return !empty($post_type_object->rewrite) || !empty($post_type_object->query_var);
    }
}

if (!function_exists('meza_get_permalink_enabled_post_type_slugs_option_name')) {
    function meza_get_permalink_enabled_post_type_slugs_option_name(): string
    {
        return 'meza_permalink_enabled_post_type_slugs_v1';
    }
}

if (!function_exists('meza_sort_permalink_enabled_post_type_slugs')) {
    function meza_sort_permalink_enabled_post_type_slugs(array $post_type_slugs): array
    {
        $post_type_slugs = array_values(array_unique(array_filter(array_map('sanitize_key', $post_type_slugs))));

        usort($post_type_slugs, static function (string $left, string $right): int {
            $priority = [
                'page' => 0,
                'post' => 1,
            ];

            $left_priority = $priority[$left] ?? 10;
            $right_priority = $priority[$right] ?? 10;

            if ($left_priority !== $right_priority) {
                return $left_priority <=> $right_priority;
            }

            return strcmp($left, $right);
        });

        return $post_type_slugs;
    }
}

if (!function_exists('meza_collect_current_permalink_enabled_post_type_slugs')) {
    function meza_collect_current_permalink_enabled_post_type_slugs(): array
    {
        $post_type_objects = get_post_types(['show_ui' => true], 'objects');
        if (!is_array($post_type_objects)) {
            return ['page', 'post'];
        }

        $post_type_slugs = [];

        foreach ($post_type_objects as $post_type => $post_type_object) {
            $post_type = sanitize_key((string) $post_type);
            if ($post_type === '' || $post_type === 'attachment' || !($post_type_object instanceof WP_Post_Type)) {
                continue;
            }

            if (function_exists('is_post_type_viewable') && !is_post_type_viewable($post_type_object)) {
                continue;
            }

            if ($post_type !== 'post' && $post_type !== 'page' && empty($post_type_object->rewrite) && empty($post_type_object->query_var)) {
                continue;
            }

            $post_type_slugs[] = $post_type;
        }

        return meza_sort_permalink_enabled_post_type_slugs($post_type_slugs);
    }
}

if (!function_exists('meza_get_permalink_enabled_post_type_slugs')) {
    function meza_get_permalink_enabled_post_type_slugs(): array
    {
        $cached_post_type_slugs = get_option(meza_get_permalink_enabled_post_type_slugs_option_name(), []);
        if (!is_array($cached_post_type_slugs)) {
            $cached_post_type_slugs = [];
        }

        return meza_sort_permalink_enabled_post_type_slugs(array_merge(
            $cached_post_type_slugs,
            meza_collect_current_permalink_enabled_post_type_slugs()
        ));
    }
}

if (!function_exists('meza_refresh_permalink_enabled_post_type_slugs')) {
    function meza_refresh_permalink_enabled_post_type_slugs(): void
    {
        update_option(
            meza_get_permalink_enabled_post_type_slugs_option_name(),
            meza_collect_current_permalink_enabled_post_type_slugs(),
            false
        );
    }
}
add_action('init', 'meza_refresh_permalink_enabled_post_type_slugs', 100);

if (!function_exists('meza_get_acf_location_rules_for_permalink_post_types')) {
    function meza_get_acf_location_rules_for_permalink_post_types(): array
    {
        $location = [];

        foreach (meza_get_permalink_enabled_post_type_slugs() as $post_type) {
            $location[] = [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => $post_type,
                ],
            ];
        }

        return $location;
    }
}

if (!function_exists('meza_get_permalink_enabled_taxonomy_slugs')) {
    function meza_get_permalink_enabled_taxonomy_slugs(): array
    {
        $taxonomy_slugs = [];

        foreach ((array) get_taxonomies(['public' => true], 'objects') as $taxonomy_slug => $taxonomy_object) {
            if (!is_string($taxonomy_slug) || $taxonomy_slug === '') {
                continue;
            }

            if (!($taxonomy_object instanceof WP_Taxonomy)) {
                continue;
            }

            if (empty($taxonomy_object->query_var)) {
                continue;
            }

            $rewrite = $taxonomy_object->rewrite;
            if ($rewrite === false) {
                continue;
            }

            $taxonomy_slugs[] = $taxonomy_slug;
        }

        sort($taxonomy_slugs, SORT_STRING);
        return array_values(array_unique($taxonomy_slugs));
    }
}

if (!function_exists('meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies')) {
    function meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies(): array
    {
        $location = meza_get_acf_location_rules_for_permalink_post_types();

        foreach (meza_get_permalink_enabled_taxonomy_slugs() as $taxonomy_slug) {
            $location[] = [
                [
                    'param' => 'taxonomy',
                    'operator' => '==',
                    'value' => $taxonomy_slug,
                ],
            ];
        }

        return $location;
    }
}

if (!function_exists('meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies_excluding_posts')) {
    function meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies_excluding_posts(): array
    {
        $location = [];

        foreach (meza_get_permalink_enabled_post_type_slugs() as $post_type) {
            if ($post_type === 'post') {
                continue;
            }

            $location[] = [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => $post_type,
                ],
            ];
        }

        foreach (meza_get_permalink_enabled_taxonomy_slugs() as $taxonomy_slug) {
            $location[] = [
                [
                    'param' => 'taxonomy',
                    'operator' => '==',
                    'value' => $taxonomy_slug,
                ],
            ];
        }

        return $location;
    }
}

if (!function_exists('meza_get_post_explicit_social_share_image_id')) {
    function meza_get_post_explicit_social_share_image_id(int $post_id): int
    {
        if ($post_id <= 0) {
            return 0;
        }

        foreach (
            [
                '_yoast_wpseo_opengraph-image-id',
                '_yoast_wpseo_twitter-image-id',
            ] as $meta_key
        ) {
            $attachment_id = absint(get_post_meta($post_id, $meta_key, true));
            if ($attachment_id > 0 && get_post_type($attachment_id) === 'attachment') {
                return $attachment_id;
            }
        }

        foreach (
            [
                '_yoast_wpseo_opengraph-image',
                '_yoast_wpseo_twitter-image',
            ] as $meta_key
        ) {
            $image_url = trim((string) get_post_meta($post_id, $meta_key, true));
            if ($image_url === '') {
                continue;
            }

            $attachment_id = function_exists('attachment_url_to_postid')
                ? absint(attachment_url_to_postid($image_url))
                : 0;

            if ($attachment_id > 0 && get_post_type($attachment_id) === 'attachment') {
                return $attachment_id;
            }
        }

        return 0;
    }
}

if (!function_exists('meza_get_post_explicit_social_share_image_url')) {
    function meza_get_post_explicit_social_share_image_url(int $post_id): string
    {
        $attachment_id = meza_get_post_explicit_social_share_image_id($post_id);
        if ($attachment_id > 0) {
            $image_url = wp_get_attachment_image_url($attachment_id, 'full');
            if (is_string($image_url) && $image_url !== '') {
                return $image_url;
            }
        }

        foreach (
            [
                '_yoast_wpseo_opengraph-image',
                '_yoast_wpseo_twitter-image',
            ] as $meta_key
        ) {
            $image_url = trim((string) get_post_meta($post_id, $meta_key, true));
            if ($image_url !== '') {
                return $image_url;
            }
        }

        return '';
    }
}

if (!function_exists('meza_post_has_explicit_social_share_image')) {
    function meza_post_has_explicit_social_share_image(int $post_id): bool
    {
        if ($post_id <= 0) {
            return false;
        }

        if (meza_get_post_explicit_social_share_image_id($post_id) > 0) {
            return true;
        }

        return meza_get_post_explicit_social_share_image_url($post_id) !== '';
    }
}

if (!function_exists('meza_post_has_explicit_social_share_image_meta')) {
    function meza_post_has_explicit_social_share_image_meta(int $post_id, array $meta_keys): bool
    {
        if ($post_id <= 0) {
            return false;
        }

        foreach ($meta_keys as $meta_key) {
            $meta_value = trim((string) get_post_meta($post_id, (string) $meta_key, true));
            if ($meta_value !== '') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('meza_get_post_resolved_social_share_image_id')) {
    function meza_get_post_resolved_social_share_image_id(int $post_id): int
    {
        $explicit_attachment_id = meza_get_post_explicit_social_share_image_id($post_id);
        if ($explicit_attachment_id > 0) {
            return $explicit_attachment_id;
        }

        if (!meza_post_uses_share_image_permalink($post_id)) {
            return 0;
        }

        return meza_get_social_share_default_id();
    }
}

if (!function_exists('meza_get_post_resolved_social_share_image_url')) {
    function meza_get_post_resolved_social_share_image_url(int $post_id): string
    {
        $explicit_image_url = meza_get_post_explicit_social_share_image_url($post_id);
        if ($explicit_image_url !== '') {
            return $explicit_image_url;
        }

        if (!meza_post_uses_share_image_permalink($post_id)) {
            return '';
        }

        return meza_get_social_share_default_url();
    }
}

if (!function_exists('meza_post_is_using_site_default_social_share_image')) {
    function meza_post_is_using_site_default_social_share_image(int $post_id): bool
    {
        if ($post_id <= 0 || meza_post_has_explicit_social_share_image($post_id)) {
            return false;
        }

        return meza_post_uses_share_image_permalink($post_id) && meza_get_social_share_default_id() > 0;
    }
}

if (!function_exists('meza_get_social_share_default_image_payload')) {
    function meza_get_social_share_default_image_payload(): array
    {
        $attachment_id = meza_get_social_share_default_id();
        $image_url = meza_get_social_share_default_url();

        if ($attachment_id <= 0 || $image_url === '') {
            return [];
        }

        return [
            'id' => $attachment_id,
            'url' => $image_url,
            'alt' => trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true)),
            'warnings' => [],
        ];
    }
}

if (!function_exists('meza_get_theme_thumbnail_allowed_extensions')) {
    function meza_get_theme_thumbnail_allowed_extensions(): array
    {
        return ['png', 'gif', 'jpg', 'jpeg', 'webp', 'avif'];
    }
}

if (!function_exists('meza_theme_thumbnail_attachment_is_supported')) {
    function meza_theme_thumbnail_attachment_is_supported(int $attachment_id): bool
    {
        $mime_type = strtolower((string) get_post_mime_type($attachment_id));

        return in_array($mime_type, [
            'image/png',
            'image/gif',
            'image/jpeg',
            'image/webp',
            'image/avif',
        ], true);
    }
}

