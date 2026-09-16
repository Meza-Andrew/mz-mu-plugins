<?php

/**
 * Plugin Name: MZ REST API
 * Description: Custom meza/v1 REST API endpoints for Next.js frontend integration
 * Version: 1.0.0
 * Author: Meza LLC
 * Author URI: https://meza.design
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) return;

require_once __DIR__ . '/arsenal-events-cors.php';
require_once __DIR__ . '/arsenal-events-revalidation.php';
require_once __DIR__ . '/arsenal-events-preview.php';

/**
 * Extract Yoast SEO metadata for a post
 */
function meza_get_yoast_metadata(int $post_id): array
{
    $yoast_meta = [
        'title' => '',
        'description' => '',
        'og_title' => '',
        'og_description' => '',
        'og_image' => '',
        'twitter_title' => '',
        'twitter_description' => '',
        'twitter_image' => '',
        'twitter_card' => 'summary_large_image',
        'canonical' => '',
        'robots' => [
            'noindex' => false,
            'nofollow' => false,
        ],
    ];

    if ($post_id <= 0) {
        return $yoast_meta;
    }

    // Get Yoast SEO meta values from post meta
    $yoast_meta['title'] = (string) get_post_meta($post_id, '_yoast_wpseo_title', true);
    $yoast_meta['description'] = (string) get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
    $yoast_meta['og_title'] = (string) get_post_meta($post_id, '_yoast_wpseo_opengraph-title', true);
    $yoast_meta['og_description'] = (string) get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true);
    $yoast_meta['og_image'] = (string) get_post_meta($post_id, '_yoast_wpseo_opengraph-image', true);
    $yoast_meta['twitter_title'] = (string) get_post_meta($post_id, '_yoast_wpseo_twitter-title', true);
    $yoast_meta['twitter_description'] = (string) get_post_meta($post_id, '_yoast_wpseo_twitter-description', true);
    $yoast_meta['twitter_image'] = (string) get_post_meta($post_id, '_yoast_wpseo_twitter-image', true);
    $yoast_meta['canonical'] = (string) get_post_meta($post_id, '_yoast_wpseo_canonical', true);
    $robots_noindex = (string) get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true);
    $robots_nofollow = (string) get_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', true);
    $yoast_meta['robots'] = [
        'noindex' => in_array($robots_noindex, ['1', 'noindex'], true),
        'nofollow' => in_array($robots_nofollow, ['1', 'nofollow'], true),
    ];

    // Fallback to post title and excerpt if Yoast meta not set
    if (empty($yoast_meta['title'])) {
        $post = get_post($post_id);
        if ($post) {
            $yoast_meta['title'] = $post->post_title;
        }
    }

    if (empty($yoast_meta['description'])) {
        $post = get_post($post_id);
        if ($post) {
            $yoast_meta['description'] = wp_trim_excerpt('', $post_id);
        }
    }

    // Fallback OG title to regular title
    if (empty($yoast_meta['og_title'])) {
        $yoast_meta['og_title'] = $yoast_meta['title'];
    }

    // Fallback OG description to regular description
    if (empty($yoast_meta['og_description'])) {
        $yoast_meta['og_description'] = $yoast_meta['description'];
    }

    if (empty($yoast_meta['twitter_title'])) {
        $yoast_meta['twitter_title'] = $yoast_meta['og_title'];
    }

    if (empty($yoast_meta['twitter_description'])) {
        $yoast_meta['twitter_description'] = $yoast_meta['og_description'];
    }

    if (empty($yoast_meta['twitter_image'])) {
        $yoast_meta['twitter_image'] = $yoast_meta['og_image'];
    }

    return $yoast_meta;
}

function meza_get_canonical_seo_payload(int $post_id): array
{
    $yoast = meza_get_yoast_metadata($post_id);

    return [
        'title' => (string) ($yoast['title'] ?? ''),
        'description' => (string) ($yoast['description'] ?? ''),
        'canonical' => (string) ($yoast['canonical'] ?? ''),
        'robots' => is_array($yoast['robots'] ?? null) ? $yoast['robots'] : [
            'noindex' => false,
            'nofollow' => false,
        ],
        'open_graph' => [
            'title' => (string) ($yoast['og_title'] ?? ''),
            'description' => (string) ($yoast['og_description'] ?? ''),
            'image' => (string) ($yoast['og_image'] ?? ''),
        ],
        'twitter' => [
            'title' => (string) ($yoast['twitter_title'] ?? ''),
            'description' => (string) ($yoast['twitter_description'] ?? ''),
            'image' => (string) ($yoast['twitter_image'] ?? ''),
            'card' => (string) ($yoast['twitter_card'] ?? 'summary_large_image'),
        ],
    ];
}

/**
 * Normalize ACF image and file values into frontend-safe media arrays.
 */
function meza_normalize_acf_asset($value)
{
    if (empty($value)) {
        return $value;
    }

    if (is_numeric($value)) {
        $attachment_id = absint($value);
        if ($attachment_id <= 0) {
            return null;
        }

        $sizes = [];
        foreach (["thumbnail", "medium", "large", "full"] as $size) {
            $image = wp_get_attachment_image_src($attachment_id, $size);
            if (is_array($image) && !empty($image[0])) {
                $sizes[$size] = $image[0];
            }
        }

        return [
            "id" => $attachment_id,
            "url" => wp_get_attachment_url($attachment_id) ?: "",
            "alt" => (string) get_post_meta($attachment_id, "_wp_attachment_image_alt", true),
            "title" => get_the_title($attachment_id) ?: "",
            "caption" => wp_get_attachment_caption($attachment_id) ?: "",
            "description" => get_post_field("post_content", $attachment_id) ?: "",
            "sizes" => $sizes,
        ];
    }

    if (!is_array($value)) {
        return $value;
    }

    $normalized = $value;
    $attachment_id = absint($normalized["ID"] ?? $normalized["id"] ?? 0);
    if ($attachment_id > 0) {
        $normalized["id"] = $attachment_id;
        if (empty($normalized["url"])) {
            $normalized["url"] = wp_get_attachment_url($attachment_id) ?: "";
        }
        $normalized["alt"] = (string) get_post_meta($attachment_id, "_wp_attachment_image_alt", true);
        if (empty($normalized["title"])) {
            $normalized["title"] = get_the_title($attachment_id) ?: "";
        }
        if (!array_key_exists("caption", $normalized)) {
            $normalized["caption"] = wp_get_attachment_caption($attachment_id) ?: "";
        }
        if (!array_key_exists("description", $normalized)) {
            $normalized["description"] = get_post_field("post_content", $attachment_id) ?: "";
        }
        if (!array_key_exists("sizes", $normalized)) {
            $sizes = [];
            foreach (["thumbnail", "medium", "large", "full"] as $size) {
                $image = wp_get_attachment_image_src($attachment_id, $size);
                if (is_array($image) && !empty($image[0])) {
                    $sizes[$size] = $image[0];
                }
            }
            $normalized["sizes"] = $sizes;
        }
    }

    return $normalized;
}

/**
 * Read Arsenal option fields from ACF with raw option fallbacks.
 */
function meza_get_arsenal_option_field(string $field_name)
{
    if (function_exists("get_field")) {
        $value = get_field($field_name, "option");
        if ($value !== null && $value !== false && $value !== "") {
            return $value;
        }
    }

    $acf_option_value = get_option("options_" . $field_name, null);
    if ($acf_option_value !== null && $acf_option_value !== false && $acf_option_value !== "") {
        return $acf_option_value;
    }

    return get_option($field_name, null);
}

/**
 * Return Arsenal media/settings fields in the frontend ACF envelope shape.
 */
function meza_get_arsenal_media_options(): array
{
    $fields = [
        "rd_hero_title",
        "rd_hero_subtitle",
        "rd_hero_description",
        "rd_hero_primary_button_text",
        "rd_hero_primary_button_href",
        "rd_hero_secondary_button_text",
        "rd_hero_secondary_button_href",
        "rd_hero_image",
        "rd_services_title",
        "rd_services_description",
        "rd_services_items",
        "rd_gallery_title",
        "rd_gallery_items",
        "rr_hero_title",
        "rr_hero_subtitle",
        "rr_hero_description",
        "rr_hero_primary_button_text",
        "rr_hero_primary_button_href",
        "rr_hero_secondary_button_text",
        "rr_hero_secondary_button_href",
        "rr_hero_image",
    ];

    $media = [];
    foreach ($fields as $field_name) {
        $media[$field_name] = meza_get_arsenal_option_field($field_name);
    }

    foreach (["rd_hero_image", "rr_hero_image"] as $image_field) {
        $media[$image_field] = meza_normalize_acf_asset($media[$image_field]);
    }

    if (is_array($media["rd_services_items"])) {
        foreach ($media["rd_services_items"] as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            if (isset($item["rd_service_image"])) {
                $media["rd_services_items"][$index]["rd_service_image"] = meza_normalize_acf_asset($item["rd_service_image"]);
            }
        }
    }

    if (is_array($media["rd_gallery_items"])) {
        foreach ($media["rd_gallery_items"] as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            if (isset($item["rd_gallery_image"])) {
                $media["rd_gallery_items"][$index]["rd_gallery_image"] = meza_normalize_acf_asset($item["rd_gallery_image"]);
            }
        }
    }

    return $media;
}

function meza_get_arsenal_media_requirements(array $media): array
{
    $required_fields = [
        'rd_hero_image',
        'rr_hero_image',
    ];
    $optional_fields = [
        'rd_services_items',
        'rd_gallery_items',
    ];
    $errors = [];

    foreach ($required_fields as $field_name) {
        $value = $media[$field_name] ?? null;
        if (!is_array($value) || empty($value['id']) || empty($value['url'])) {
            $errors[] = [
                'field' => $field_name,
                'code' => 'required_media_missing',
                'message' => 'Required Arsenal media field is not configured.',
            ];
        }
    }

    return [
        'required' => $required_fields,
        'optional' => $optional_fields,
        'errors' => $errors,
        'ok' => $errors === [],
    ];
}

/**
 * REST API: GET /wp-json/meza/v1/arsenal-media
 * Fetch Arsenal media option fields in an ACF-compatible envelope.
 */
function meza_rest_get_arsenal_media(WP_REST_Request $request): WP_REST_Response
{
    $media = meza_get_arsenal_media_options();
    $requirements = meza_get_arsenal_media_requirements($media);

    return new WP_REST_Response([
        "acf" => $media,
        "media" => $media,
        "requirements" => $requirements,
        "errors" => $requirements["errors"],
    ], 200);
}

/**
 * Get ACF page blocks for a post
 */
function meza_get_page_blocks(int $post_id): array
{
    if ($post_id <= 0 || !function_exists('get_field')) {
        return [];
    }

    $blocks_raw = get_field('page_blocks', $post_id);
    $blocks = [];
    if (is_array($blocks_raw)) {
        foreach ($blocks_raw as $block) {
            if (!is_array($block)) {
                continue;
            }

            $block_type = isset($block['acf_fc_layout']) ? (string) $block['acf_fc_layout'] : '';
            if (empty($block_type)) {
                continue;
            }

            // Convert block data to snake_case props
            $props = [];
            foreach ($block as $key => $value) {
                if ($key === 'acf_fc_layout') {
                    continue;
                }
                // Convert camelCase to snake_case
                $snake_key = strtolower(preg_replace('/(?<!^)(?=[A-Z])/', '_', $key));
                $props[$snake_key] = $value;
            }

            $blocks[] = [
                'type' => $block_type,
                'props' => $props,
            ];
        }
    }

    return array_merge($blocks, meza_get_page_structured_blocks($post_id));
}

function meza_get_page_field_value(int $post_id, string $field_name, $empty_value = '')
{
    if (!function_exists('get_field')) {
        return $empty_value;
    }

    $value = get_field($field_name, $post_id);

    return $value === null || $value === false ? $empty_value : $value;
}

function meza_normalize_structured_stat_items($items): array
{
    if (!is_array($items)) {
        return [];
    }

    return array_values(array_map(
        static function ($item): array {
            $item = is_array($item) ? $item : [];

            return [
                'stat_icon' => meza_normalize_acf_asset($item['stat_icon'] ?? null),
                'stat_value' => (string) ($item['stat_value'] ?? ''),
                'stat_label' => (string) ($item['stat_label'] ?? ''),
            ];
        },
        $items
    ));
}

function meza_normalize_structured_resource_items($items): array
{
    if (!is_array($items)) {
        return [];
    }

    return array_values(array_map(
        static function ($item): array {
            $item = is_array($item) ? $item : [];

            return [
                'resource_title' => (string) ($item['resource_title'] ?? ''),
                'resource_image' => meza_normalize_acf_asset($item['resource_image'] ?? null),
                'resource_category' => (string) ($item['resource_category'] ?? ''),
                'resource_link' => (string) ($item['resource_link'] ?? ''),
            ];
        },
        $items
    ));
}

function meza_normalize_structured_testimonial_items($items): array
{
    if (!is_array($items)) {
        return [];
    }

    return array_values(array_map(
        static function ($item): array {
            $item = is_array($item) ? $item : [];

            return [
                'testimonial_type' => (string) ($item['testimonial_type'] ?? ''),
                'testimonial_label' => (string) ($item['testimonial_label'] ?? ''),
                'testimonial_quote' => (string) ($item['testimonial_quote'] ?? ''),
                'testimonial_attribution' => (string) ($item['testimonial_attribution'] ?? ''),
            ];
        },
        $items
    ));
}

function meza_get_structured_cta_payload(int $post_id, string $prefix): array
{
    return [
        'title' => (string) meza_get_page_field_value($post_id, "{$prefix}_cta_title"),
        'description' => (string) meza_get_page_field_value($post_id, "{$prefix}_cta_description"),
        'primary_button_text' => (string) meza_get_page_field_value($post_id, "{$prefix}_cta_primary_text"),
        'primary_button_href' => (string) meza_get_page_field_value($post_id, "{$prefix}_cta_primary_href"),
        'secondary_button_text' => (string) meza_get_page_field_value($post_id, "{$prefix}_cta_secondary_text"),
        'secondary_button_href' => (string) meza_get_page_field_value($post_id, "{$prefix}_cta_secondary_href"),
    ];
}

function meza_get_home_structured_content(int $post_id): array
{
    return [
        'upcoming_races' => [
            'title' => (string) meza_get_page_field_value($post_id, 'home_upcoming_races_title'),
            'description' => (string) meza_get_page_field_value($post_id, 'home_upcoming_races_description'),
        ],
        'stats' => [
            'title' => (string) meza_get_page_field_value($post_id, 'home_stats_title'),
            'items' => meza_normalize_structured_stat_items(meza_get_page_field_value($post_id, 'home_stats_items', [])),
        ],
        'testimonials' => [
            'title' => (string) meza_get_page_field_value($post_id, 'home_testimonials_title'),
            'description' => (string) meza_get_page_field_value($post_id, 'home_testimonials_description'),
            'items' => meza_normalize_structured_testimonial_items(meza_get_page_field_value($post_id, 'home_testimonials_items', [])),
        ],
        'resources' => [
            'title' => (string) meza_get_page_field_value($post_id, 'home_resources_title'),
            'description' => (string) meza_get_page_field_value($post_id, 'home_resources_description'),
            'items' => meza_normalize_structured_resource_items(meza_get_page_field_value($post_id, 'home_resources_items', [])),
        ],
        'cta' => meza_get_structured_cta_payload($post_id, 'home'),
    ];
}

function meza_get_race_director_structured_content(int $post_id): array
{
    return [
        'stats' => [
            'title' => (string) meza_get_page_field_value($post_id, 'rd_stats_title'),
            'items' => meza_normalize_structured_stat_items(meza_get_page_field_value($post_id, 'rd_stats_items', [])),
        ],
        'inquiry' => [
            'title' => (string) meza_get_page_field_value($post_id, 'rd_inquiry_title'),
            'description' => (string) meza_get_page_field_value($post_id, 'rd_inquiry_description'),
            'primary_button_text' => (string) meza_get_page_field_value($post_id, 'rd_inquiry_primary_text'),
            'primary_button_href' => (string) meza_get_page_field_value($post_id, 'rd_inquiry_primary_href'),
            'secondary_button_text' => (string) meza_get_page_field_value($post_id, 'rd_inquiry_secondary_text'),
            'secondary_button_href' => (string) meza_get_page_field_value($post_id, 'rd_inquiry_secondary_href'),
        ],
        'resources' => [
            'title' => (string) meza_get_page_field_value($post_id, 'rd_resources_title'),
            'description' => (string) meza_get_page_field_value($post_id, 'rd_resources_description'),
        ],
        'cta' => meza_get_structured_cta_payload($post_id, 'rd'),
    ];
}

function meza_get_races_results_structured_content(int $post_id): array
{
    return [
        'results' => [
            'title' => (string) meza_get_page_field_value($post_id, 'rr_results_title'),
            'description' => (string) meza_get_page_field_value($post_id, 'rr_results_description'),
            'unavailable_label' => (string) meza_get_page_field_value($post_id, 'rr_results_unavailable_label'),
            'unavailable_description' => (string) meza_get_page_field_value($post_id, 'rr_results_unavailable_description'),
        ],
        'upcoming_races' => [
            'title' => (string) meza_get_page_field_value($post_id, 'rr_upcoming_races_title'),
            'description' => (string) meza_get_page_field_value($post_id, 'rr_upcoming_races_description'),
        ],
        'stats' => [
            'title' => (string) meza_get_page_field_value($post_id, 'rr_stats_title'),
            'items' => meza_normalize_structured_stat_items(meza_get_page_field_value($post_id, 'rr_stats_items', [])),
        ],
        'resources' => [
            'title' => (string) meza_get_page_field_value($post_id, 'rr_resources_title'),
            'description' => (string) meza_get_page_field_value($post_id, 'rr_resources_description'),
        ],
        'cta' => meza_get_structured_cta_payload($post_id, 'rr'),
    ];
}

function meza_get_page_structured_content(int $post_id): array
{
    $post = get_post($post_id);
    $slug = $post instanceof WP_Post ? (string) $post->post_name : '';

    if ($slug === 'home' || $slug === '') {
        return meza_get_home_structured_content($post_id);
    }

    if ($slug === 'for-race-directors') {
        return meza_get_race_director_structured_content($post_id);
    }

    if ($slug === 'races-and-results') {
        return meza_get_races_results_structured_content($post_id);
    }

    return [];
}

function meza_get_page_structured_blocks(int $post_id): array
{
    $post = get_post($post_id);
    $slug = $post instanceof WP_Post ? (string) $post->post_name : '';
    if ($slug !== 'home' && $slug !== '') {
        return [];
    }

    $structured = meza_get_home_structured_content($post_id);

    return [
        [
            'id' => 'home-upcoming-races',
            'type' => 'upcoming_races_block',
            'props' => [
                'upcoming_races_block' => [
                    'races_title' => $structured['upcoming_races']['title'],
                    'races_description' => $structured['upcoming_races']['description'],
                    'races_carousel' => [],
                ],
            ],
        ],
        [
            'id' => 'home-testimonials-stats',
            'type' => 'testimonials_stats_block',
            'props' => [
                'testimonials_stats_block' => [
                    'testimonials_headline' => $structured['testimonials']['title'],
                    'testimonials_description' => $structured['testimonials']['description'],
                    'testimonials_list' => $structured['testimonials']['items'],
                    'stats_list' => $structured['stats']['items'],
                ],
                'stats_title' => $structured['stats']['title'],
            ],
        ],
        [
            'id' => 'home-resources',
            'type' => 'resources_block',
            'props' => [
                'resources_block' => [
                    'resources_title' => $structured['resources']['title'],
                    'resources_description' => $structured['resources']['description'],
                    'resources_grid' => $structured['resources']['items'],
                ],
            ],
        ],
        [
            'id' => 'home-cta',
            'type' => 'cta_block',
            'props' => [
                'cta_title' => $structured['cta']['title'],
                'cta_description' => $structured['cta']['description'],
                'cta_button_text' => $structured['cta']['primary_button_text'],
                'cta_button_url' => $structured['cta']['primary_button_href'],
                'cta_secondary_label' => $structured['cta']['secondary_button_text'],
                'cta_secondary_href' => $structured['cta']['secondary_button_href'],
            ],
        ],
    ];
}

/**
 * Get page data with blocks and Yoast metadata
 */
function meza_get_page_data(int $post_id, bool $published_only = true): array
{
    $post = get_post($post_id);
    if (!$post || ($published_only && $post->post_status !== 'publish')) {
        return [];
    }

    return [
        'id' => $post->ID,
        'slug' => $post->post_name,
        'title' => $post->post_title,
        'status' => $post->post_status,
        'content' => function_exists('wp_kses_post')
            ? wp_kses_post(apply_filters('the_content', (string) $post->post_content))
            : (string) apply_filters('the_content', (string) $post->post_content),
        'structured_content' => meza_get_page_structured_content($post->ID),
        'page_blocks' => meza_get_page_blocks($post->ID),
        'seo' => meza_get_canonical_seo_payload($post->ID),
        'yoast_meta' => meza_get_yoast_metadata($post->ID),
    ];
}

/**
 * REST API: GET /wp-json/meza/v1/pages
 * Fetch all pages with ACF blocks and Yoast metadata
 */
function meza_rest_get_pages(WP_REST_Request $request): WP_REST_Response
{
    $pages_query = new WP_Query([
        'post_type' => 'page',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'menu_order',
        'order' => 'ASC',
    ]);

    $pages = [];
    if ($pages_query->have_posts()) {
        foreach ($pages_query->posts as $post) {
            $page_data = meza_get_page_data($post->ID);
            if (!empty($page_data)) {
                $pages[] = $page_data;
            }
        }
    }

    return new WP_REST_Response([
        'pages' => $pages,
    ], 200);
}

/**
 * REST API: GET /wp-json/meza/v1/pages/{slug}
 * Fetch single page by slug with full ACF blocks and Yoast metadata
 */
function meza_rest_get_page_by_slug(WP_REST_Request $request): WP_REST_Response
{
    $slug = $request->get_param('slug');
    if (empty($slug)) {
        return new WP_REST_Response([
            'error' => 'Slug parameter is required',
        ], 400);
    }

    $page = get_page_by_path($slug, OBJECT, 'page');
    if (!$page || $page->post_status !== 'publish') {
        return new WP_REST_Response([
            'error' => 'Page not found',
        ], 404);
    }

    $page_data = meza_get_page_data($page->ID);
    if (empty($page_data)) {
        return new WP_REST_Response([
            'error' => 'Page not found',
        ], 404);
    }

    return new WP_REST_Response($page_data, 200);
}

/**
 * REST API: GET /wp-json/meza/v1/settings
 * Fetch global site options and default SEO fallbacks
 */
function meza_rest_get_settings(WP_REST_Request $request): WP_REST_Response
{
    $settings = [
        'site_title' => get_bloginfo('name'),
        'site_description' => get_bloginfo('description'),
        'site_phone' => meza_get_arsenal_option_field('site_phone') ?: get_option('meza_site_phone', '(804) 572-3060'),
        'site_email' => meza_get_arsenal_option_field('site_email') ?: get_option('meza_site_email', 'info@arsenalevent.com'),
        'site_info_text' => meza_get_arsenal_option_field('site_info_text') ?: '',
        'site_copyright_text' => meza_get_arsenal_option_field('site_copyright_text') ?: get_option('meza_site_copyright_text', '© 2026 Arsenal Events'),
        'header_nav_links' => [],
        'footer_nav_links' => [],
        'social_links' => [],
        'seo_defaults' => [
            'og_image' => get_option('meza_seo_og_image', ''),
            'twitter_card' => 'summary_large_image',
        ],
    ];

    // Get header nav links from ACF options
    if (function_exists('get_field')) {
        $header_links = get_field('header_nav_links', 'option');
        if (is_array($header_links)) {
            $settings['header_nav_links'] = $header_links;
        }

        $footer_links = get_field('footer_nav_links', 'option');
        if (is_array($footer_links)) {
            $settings['footer_nav_links'] = $footer_links;
        }

        $social_links = get_field('social_links', 'option');
        if (is_array($social_links)) {
            $settings['social_links'] = $social_links;
        }
    }

    return new WP_REST_Response($settings, 200);
}

/**
 * Permission callback for REST API endpoints
 */
function meza_rest_api_permission_callback(): bool
{
    // Allow public read access to pages and settings
    return true;
}

/**
 * Extends headless-core's canonical page contract for Arsenal's author-facing
 * flexible content field. Arsenal must not register a competing pages route.
 */
function meza_arsenal_extend_page_payload(array $payload, WP_Post $page): array
{
    $arsenal_blocks = meza_get_page_blocks((int) $page->ID);
    $structured_blocks = meza_get_page_structured_blocks((int) $page->ID);
    $structured_content = meza_get_page_structured_content((int) $page->ID);
    $blocks = $arsenal_blocks !== []
        ? $arsenal_blocks
        : (is_array($payload['blocks'] ?? null) ? $payload['blocks'] : []);

    if ($arsenal_blocks === [] && $structured_blocks !== []) {
        $blocks = array_merge($blocks, $structured_blocks);
    }

    $content = trim((string) $page->post_content);

    if ($content !== '') {
        $content_block = [
            'id' => 'content-' . (int) $page->ID,
            'type' => 'content',
            'props' => [
                'html' => wp_kses_post(apply_filters('the_content', $content)),
            ],
        ];

        $insert_at = 0;
        foreach ($blocks as $index => $block) {
            if (in_array($block['type'] ?? '', ['hero', 'hero_block'], true)) {
                $insert_at = $index + 1;
                break;
            }
        }

        array_splice($blocks, $insert_at, 0, [$content_block]);
    }

    $payload['blocks'] = $blocks;
    $payload['page_blocks'] = $blocks;
    $payload['structured_content'] = $structured_content;
    $payload['yoast_meta'] = meza_get_yoast_metadata((int) $page->ID);

    return $payload;
}
add_filter('mzhc_page_payload', 'meza_arsenal_extend_page_payload', 20, 2);

/**
 * Adds backward-compatible Arsenal settings aliases to the canonical
 * headless-core settings contract without taking ownership of its route.
 */
function meza_arsenal_extend_settings_payload(array $payload): array
{
    $option = static function (string $field, string $fallback = ''): string {
        return (string) (meza_get_arsenal_option_field($field) ?: $fallback);
    };

    $header_links = function_exists('get_field') ? get_field('header_nav_links', 'option') : [];
    $footer_links = function_exists('get_field') ? get_field('footer_nav_links', 'option') : [];
    $social_links = function_exists('get_field') ? get_field('social_links', 'option') : [];
    $site_phone = $option('site_phone', (string) get_option('meza_site_phone', ''));
    $site_email = $option('site_email', (string) get_option('meza_site_email', ''));
    $contact = is_array($payload['contact'] ?? null) ? $payload['contact'] : [];

    if ($site_email !== '') {
        $contact['email'] = $site_email;
    }

    if ($site_phone !== '') {
        $contact['phone'] = $site_phone;
    }

    $payload['site_title'] = $payload['site']['name'] ?? get_bloginfo('name');
    $payload['site_description'] = $payload['site']['description'] ?? get_bloginfo('description');
    $payload['contact'] = $contact;
    $payload['site_phone'] = $site_phone;
    $payload['site_email'] = $site_email;
    $payload['site_info_text'] = $option('site_info_text');
    $payload['site_copyright_text'] = $option('site_copyright_text');
    $payload['header_nav_links'] = is_array($header_links) ? $header_links : [];
    $payload['footer_nav_links'] = is_array($footer_links) ? $footer_links : [];
    $payload['social_links'] = is_array($social_links) ? $social_links : [];
    $payload['seo_defaults'] = [
        'og_image' => $payload['seo']['default_og_image'] ?? '',
        'twitter_card' => 'summary_large_image',
    ];

    return $payload;
}
add_filter('mzhc_settings_payload', 'meza_arsenal_extend_settings_payload', 20);

/**
 * Register REST API routes
 */
add_action('rest_api_init', function () {
    // GET /wp-json/meza/v1/arsenal-media
    register_rest_route('meza/v1', '/arsenal-media', [
        'methods' => 'GET',
        'callback' => 'meza_rest_get_arsenal_media',
        'permission_callback' => 'meza_rest_api_permission_callback',
    ]);
});

/**
 * Get race/resource data with ACF fields and Yoast metadata
 */
function meza_render_post_content(WP_Post $post): string
{
    $content = (string) $post->post_content;
    if ($content === '') {
        return '';
    }

    return function_exists('wp_kses_post')
        ? wp_kses_post(apply_filters('the_content', $content))
        : (string) apply_filters('the_content', $content);
}

function meza_get_post_excerpt_payload(WP_Post $post): array
{
    $raw = (string) $post->post_excerpt;
    $rendered = $raw !== '' ? $raw : (string) wp_trim_excerpt('', (int) $post->ID);

    return [
        'raw' => $raw,
        'rendered' => $rendered,
    ];
}

function meza_get_native_featured_image(int $post_id)
{
    if (!function_exists('get_post_thumbnail_id')) {
        return null;
    }

    $thumbnail_id = (int) get_post_thumbnail_id($post_id);
    if ($thumbnail_id <= 0) {
        return null;
    }

    return meza_normalize_acf_asset($thumbnail_id);
}

function meza_get_legacy_acf_featured_image(int $post_id, string $post_type)
{
    if (!function_exists('get_field')) {
        return null;
    }

    if ($post_type === 'race') {
        return meza_normalize_acf_asset(get_field('race_featured_image', $post_id));
    }

    if ($post_type === 'resource') {
        return meza_normalize_acf_asset(get_field('resource_featured_image', $post_id));
    }

    return null;
}

function meza_get_post_featured_image_payload(int $post_id, string $post_type): array
{
    $native = meza_get_native_featured_image($post_id);
    $legacy = meza_get_legacy_acf_featured_image($post_id, $post_type);
    $canonical = is_array($native) ? $native : null;
    $legacy_matches = is_array($canonical)
        && is_array($legacy)
        && (int) ($canonical['id'] ?? 0) > 0
        && (int) ($canonical['id'] ?? 0) === (int) ($legacy['id'] ?? 0);

    return [
        'canonical' => $canonical,
        'legacy' => is_array($legacy) ? $legacy : null,
        'legacy_matches_canonical' => $legacy_matches,
        'source' => is_array($canonical) ? 'native_featured_image' : 'none',
    ];
}

function meza_get_post_taxonomy_payload(int $post_id, string $post_type): array
{
    if (!function_exists('get_object_taxonomies') || !function_exists('wp_get_post_terms')) {
        return [];
    }

    $taxonomy_names = get_object_taxonomies($post_type, 'names');
    if (!is_array($taxonomy_names)) {
        return [];
    }

    $taxonomies = [];
    foreach ($taxonomy_names as $taxonomy_name) {
        $taxonomy_name = (string) $taxonomy_name;
        $terms = wp_get_post_terms($post_id, $taxonomy_name);
        if (!is_array($terms)) {
            continue;
        }

        $taxonomies[$taxonomy_name] = array_values(array_map(
            static function ($term): array {
                return [
                    'id' => (int) ($term->term_id ?? 0),
                    'slug' => (string) ($term->slug ?? ''),
                    'name' => (string) ($term->name ?? ''),
                    'taxonomy' => (string) ($term->taxonomy ?? ''),
                ];
            },
            $terms
        ));
    }

    return $taxonomies;
}

function meza_get_post_data(int $post_id, string $post_type, bool $published_only = true): array
{
    $post = get_post($post_id);
    if (!$post || ($published_only && $post->post_status !== 'publish') || $post->post_type !== $post_type) {
        return [];
    }

    $excerpt = meza_get_post_excerpt_payload($post);
    $image_payload = meza_get_post_featured_image_payload($post->ID, $post_type);
    $yoast_meta = meza_get_yoast_metadata($post->ID);

    $data = [
        'id' => $post->ID,
        'slug' => $post->post_name,
        'title' => $post->post_title,
        'status' => $post->post_status,
        'content' => meza_render_post_content($post),
        'content_raw' => (string) $post->post_content,
        'excerpt' => $excerpt['rendered'],
        'excerpt_raw' => $excerpt['raw'],
        'featured_image' => $image_payload['canonical'],
        'featured_image_source' => $image_payload['source'],
        'legacy_acf_featured_image' => $image_payload['legacy'],
        'legacy_acf_featured_image_matches_native' => $image_payload['legacy_matches_canonical'],
        'taxonomies' => meza_get_post_taxonomy_payload($post->ID, $post_type),
        'seo' => meza_get_canonical_seo_payload($post->ID),
        'yoast_meta' => $yoast_meta,
    ];

    // Add post-specific ACF fields based on post type
    if (!function_exists('get_field')) {
        return $data;
    }

    if ($post_type === 'race') {
        $data['race_date'] = get_field('race_date', $post->ID);
        $data['race_location'] = get_field('race_location', $post->ID);
        $data['race_registration_link'] = get_field('race_registration_link', $post->ID);
        $data['race_results_link'] = get_field('race_results_link', $post->ID);
        $data['race_featured_image'] = $image_payload['canonical'];
    } elseif ($post_type === 'resource') {
        $data['resource_featured_image'] = $image_payload['canonical'];
        $data['resource_download_link'] = meza_normalize_acf_asset(get_field('resource_download_link', $post->ID));
        $data['resource_external_link'] = get_field('resource_external_link', $post->ID);
    }

    return $data;
}

/**
 * REST API: GET /wp-json/meza/v1/races
 * Fetch all races with ACF fields and Yoast metadata
 */
function meza_rest_get_races(WP_REST_Request $request): WP_REST_Response
{
    $races_query = new WP_Query([
        'post_type' => 'race',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    $races = [];
    if ($races_query->have_posts()) {
        foreach ($races_query->posts as $post) {
            $race_data = meza_get_post_data($post->ID, 'race');
            if (!empty($race_data)) {
                $races[] = $race_data;
            }
        }
    }

    return new WP_REST_Response([
        'races' => $races,
    ], 200);
}

/**
 * REST API: GET /wp-json/meza/v1/races/{slug}
 * Fetch single race by slug with full ACF fields and Yoast metadata
 */
function meza_rest_get_race_by_slug(WP_REST_Request $request): WP_REST_Response
{
    $slug = $request->get_param('slug');
    if (empty($slug)) {
        return new WP_REST_Response([
            'error' => 'Slug parameter is required',
        ], 400);
    }

    $race = get_page_by_path($slug, OBJECT, 'race');
    if (!$race || $race->post_status !== 'publish') {
        return new WP_REST_Response([
            'error' => 'Race not found',
        ], 404);
    }

    $race_data = meza_get_post_data($race->ID, 'race');
    if (empty($race_data)) {
        return new WP_REST_Response([
            'error' => 'Race not found',
        ], 404);
    }

    return new WP_REST_Response($race_data, 200);
}

/**
 * REST API: GET /wp-json/meza/v1/resources
 * Fetch all resources with ACF fields and Yoast metadata
 */
function meza_rest_get_resources(WP_REST_Request $request): WP_REST_Response
{
    $resources_query = new WP_Query([
        'post_type' => 'resource',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    $resources = [];
    if ($resources_query->have_posts()) {
        foreach ($resources_query->posts as $post) {
            $resource_data = meza_get_post_data($post->ID, 'resource');
            if (!empty($resource_data)) {
                $resources[] = $resource_data;
            }
        }
    }

    return new WP_REST_Response([
        'resources' => $resources,
    ], 200);
}

/**
 * REST API: GET /wp-json/meza/v1/resources/{slug}
 * Fetch single resource by slug with full ACF fields and Yoast metadata
 */
function meza_rest_get_resource_by_slug(WP_REST_Request $request): WP_REST_Response
{
    $slug = $request->get_param('slug');
    if (empty($slug)) {
        return new WP_REST_Response([
            'error' => 'Slug parameter is required',
        ], 400);
    }

    $resource = get_page_by_path($slug, OBJECT, 'resource');
    if (!$resource || $resource->post_status !== 'publish') {
        return new WP_REST_Response([
            'error' => 'Resource not found',
        ], 404);
    }

    $resource_data = meza_get_post_data($resource->ID, 'resource');
    if (empty($resource_data)) {
        return new WP_REST_Response([
            'error' => 'Resource not found',
        ], 404);
    }

    return new WP_REST_Response($resource_data, 200);
}

/**
 * Register REST API routes for races and resources
 */
add_action('rest_api_init', function () {
    // GET /wp-json/meza/v1/races
    register_rest_route('meza/v1', '/races', [
        'methods' => 'GET',
        'callback' => 'meza_rest_get_races',
        'permission_callback' => 'meza_rest_api_permission_callback',
    ]);

    // GET /wp-json/meza/v1/races/{slug}
    register_rest_route('meza/v1', '/races/(?P<slug>[a-zA-Z0-9_-]+)', [
        'methods' => 'GET',
        'callback' => 'meza_rest_get_race_by_slug',
        'permission_callback' => 'meza_rest_api_permission_callback',
        'args' => [
            'slug' => [
                'type' => 'string',
                'required' => true,
            ],
        ],
    ]);

    // GET /wp-json/meza/v1/resources
    register_rest_route('meza/v1', '/resources', [
        'methods' => 'GET',
        'callback' => 'meza_rest_get_resources',
        'permission_callback' => 'meza_rest_api_permission_callback',
    ]);

    // GET /wp-json/meza/v1/resources/{slug}
    register_rest_route('meza/v1', '/resources/(?P<slug>[a-zA-Z0-9_-]+)', [
        'methods' => 'GET',
        'callback' => 'meza_rest_get_resource_by_slug',
        'permission_callback' => 'meza_rest_api_permission_callback',
        'args' => [
            'slug' => [
                'type' => 'string',
                'required' => true,
            ],
        ],
    ]);
}, 11);
