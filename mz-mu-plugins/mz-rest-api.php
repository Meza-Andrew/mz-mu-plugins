<?php

/**
 * Plugin Name: MZ REST API
 * Description: Custom meza/v1 REST API endpoints for Next.js frontend integration
 * Version: 1.0.0
 * Author: Meza LLC
 * Author URI: https://meza.design
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) return;

/**
 * Define allowed frontend origins for CORS
 * Defaults to localhost:3001 for local dev, can be overridden via constant
 */
if (!defined('ALLOWED_FRONTEND_ORIGINS')) {
    define('ALLOWED_FRONTEND_ORIGINS', [
        'http://localhost:3001',
        'http://localhost:3000',
    ]);
}

/**
 * CORS Security: Validate Origin header for meza/v1 endpoints
 * Intercepts REST API requests at the authentication stage
 */
add_filter('rest_authentication_errors', function ($result) {
    // Only apply to meza/v1 endpoints
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/wp-json/meza/v1/') === false) {
        return $result;
    }

    $origin = isset($_SERVER['HTTP_ORIGIN']) ? sanitize_text_field($_SERVER['HTTP_ORIGIN']) : '';
    $allowed_origins = ALLOWED_FRONTEND_ORIGINS;

    // If origin header is present, validate it
    if (!empty($origin) && !in_array($origin, $allowed_origins, true)) {
        // Invalid origin: return 403 Forbidden error
        return new WP_Error(
            'cors_origin_not_allowed',
            'Origin not allowed to access this resource',
            ['status' => 403]
        );
    }

    return $result;
}, 10, 1);

/**
 * CORS Headers: Set strict CORS headers for meza/v1 endpoints
 * Ensures only allowed origins receive CORS headers
 */
add_filter('rest_send_cors_headers', function ($value) {
    // Only apply to meza/v1 endpoints
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/wp-json/meza/v1/') === false) {
        return $value;
    }

    $origin = isset($_SERVER['HTTP_ORIGIN']) ? sanitize_text_field($_SERVER['HTTP_ORIGIN']) : '';
    $allowed_origins = ALLOWED_FRONTEND_ORIGINS;

    // Only send CORS headers for allowed origins
    if (!empty($origin) && in_array($origin, $allowed_origins, true)) {
        return true;
    }

    // Block CORS for invalid origins by returning false
    return false;
}, 10, 1);

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
        'twitter_card' => 'summary_large_image',
        'canonical' => '',
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
    $yoast_meta['canonical'] = (string) get_post_meta($post_id, '_yoast_wpseo_canonical', true);

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

    return $yoast_meta;
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
        if (!array_key_exists("alt", $normalized)) {
            $normalized["alt"] = (string) get_post_meta($attachment_id, "_wp_attachment_image_alt", true);
        }
        if (empty($normalized["title"])) {
            $normalized["title"] = get_the_title($attachment_id) ?: "";
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

/**
 * REST API: GET /wp-json/meza/v1/arsenal-media
 * Fetch Arsenal media option fields in an ACF-compatible envelope.
 */
function meza_rest_get_arsenal_media(WP_REST_Request $request): WP_REST_Response
{
    $media = meza_get_arsenal_media_options();

    return new WP_REST_Response([
        "acf" => $media,
        "media" => $media,
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
    if (!is_array($blocks_raw)) {
        return [];
    }

    $blocks = [];
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

    return $blocks;
}

/**
 * Get page data with blocks and Yoast metadata
 */
function meza_get_page_data(int $post_id): array
{
    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'publish') {
        return [];
    }

    return [
        'id' => $post->ID,
        'slug' => $post->post_name,
        'title' => $post->post_title,
        'status' => $post->post_status,
        'page_blocks' => meza_get_page_blocks($post->ID),
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
    $blocks = meza_get_page_blocks((int) $page->ID);
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

    $payload['site_title'] = $payload['site']['name'] ?? get_bloginfo('name');
    $payload['site_description'] = $payload['site']['description'] ?? get_bloginfo('description');
    $payload['site_phone'] = $option('site_phone', (string) get_option('meza_site_phone', ''));
    $payload['site_email'] = $option('site_email', (string) get_option('admin_email', ''));
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
function meza_get_post_data(int $post_id, string $post_type): array
{
    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'publish' || $post->post_type !== $post_type) {
        return [];
    }

    $data = [
        'id' => $post->ID,
        'slug' => $post->post_name,
        'title' => $post->post_title,
        'status' => $post->post_status,
        'yoast_meta' => meza_get_yoast_metadata($post->ID),
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
        $data['race_featured_image'] = meza_normalize_acf_asset(get_field('race_featured_image', $post->ID));
    } elseif ($post_type === 'resource') {
        $data['resource_featured_image'] = meza_normalize_acf_asset(get_field('resource_featured_image', $post->ID));
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
