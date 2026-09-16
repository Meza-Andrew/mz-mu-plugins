<?php

define('ARSENAL_EVENTS_CORS_TESTS', true);
define('ARSENAL_EVENTS_REVALIDATION_TESTS', true);

$arsenal_events_test_posts = [];
$arsenal_events_test_meta = [];
$arsenal_events_test_fields = [];
$arsenal_events_test_thumbnail_ids = [];
$arsenal_events_test_attachment_urls = [];
$arsenal_events_test_attachment_titles = [];
$arsenal_events_test_attachment_captions = [];
$arsenal_events_test_attachment_descriptions = [];
$arsenal_events_test_attachment_sizes = [];
$arsenal_events_test_taxonomies = [];
$arsenal_events_test_terms = [];
$arsenal_events_test_options = [];

class WP_Post
{
    public int $ID;
    public string $post_name;
    public string $post_title;
    public string $post_status;
    public string $post_type;
    public string $post_content;
    public string $post_excerpt;

    public function __construct(array $data)
    {
        $this->ID = (int) ($data['ID'] ?? 0);
        $this->post_name = (string) ($data['post_name'] ?? '');
        $this->post_title = (string) ($data['post_title'] ?? '');
        $this->post_status = (string) ($data['post_status'] ?? 'publish');
        $this->post_type = (string) ($data['post_type'] ?? 'post');
        $this->post_content = (string) ($data['post_content'] ?? '');
        $this->post_excerpt = (string) ($data['post_excerpt'] ?? '');
    }
}

class WP_Error
{
}

class WP_REST_Request
{
}

class WP_REST_Response
{
    private $data;
    private int $status;

    public function __construct($data = null, int $status = 200)
    {
        $this->data = $data;
        $this->status = $status;
    }

    public function get_data()
    {
        return $this->data;
    }

    public function get_status(): int
    {
        return $this->status;
    }
}

class WP_Query
{
}

function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1): void
{
}

function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1): void
{
}

function register_rest_route($namespace, $route, array $args): void
{
}

function apply_filters($hook_name, $value)
{
    if ($hook_name === 'the_content') {
        return '<p>' . $value . '</p>';
    }

    return $value;
}

function wp_kses_post($value)
{
    return $value;
}

function absint($value): int
{
    return abs((int) $value);
}

function get_post($post_id)
{
    global $arsenal_events_test_posts;

    return $arsenal_events_test_posts[(int) $post_id] ?? null;
}

function get_post_meta($post_id, $key, $single = false)
{
    global $arsenal_events_test_meta;

    return $arsenal_events_test_meta[(int) $post_id][(string) $key] ?? '';
}

function wp_trim_excerpt($text = '', $post_id = 0): string
{
    $post = get_post((int) $post_id);
    if (!$post instanceof WP_Post) {
        return '';
    }

    return trim(substr(strip_tags((string) $post->post_content), 0, 80));
}

function get_field($field_name, $post_id = false)
{
    global $arsenal_events_test_fields;

    $key = $post_id === 'option' ? 'option' : (string) (int) $post_id;

    return $arsenal_events_test_fields[$key][(string) $field_name] ?? null;
}

function get_option($name, $default = null)
{
    global $arsenal_events_test_options;

    return array_key_exists((string) $name, $arsenal_events_test_options)
        ? $arsenal_events_test_options[(string) $name]
        : $default;
}

function get_post_thumbnail_id($post_id): int
{
    global $arsenal_events_test_thumbnail_ids;

    return (int) ($arsenal_events_test_thumbnail_ids[(int) $post_id] ?? 0);
}

function wp_get_attachment_image_src($attachment_id, $size)
{
    global $arsenal_events_test_attachment_sizes;

    return $arsenal_events_test_attachment_sizes[(int) $attachment_id][(string) $size] ?? false;
}

function wp_get_attachment_url($attachment_id): string
{
    global $arsenal_events_test_attachment_urls;

    return (string) ($arsenal_events_test_attachment_urls[(int) $attachment_id] ?? '');
}

function get_the_title($post_id): string
{
    global $arsenal_events_test_attachment_titles;

    return (string) ($arsenal_events_test_attachment_titles[(int) $post_id] ?? '');
}

function wp_get_attachment_caption($attachment_id): string
{
    global $arsenal_events_test_attachment_captions;

    return (string) ($arsenal_events_test_attachment_captions[(int) $attachment_id] ?? '');
}

function get_post_field($field, $post_id): string
{
    global $arsenal_events_test_attachment_descriptions;

    if ($field === 'post_content') {
        return (string) ($arsenal_events_test_attachment_descriptions[(int) $post_id] ?? '');
    }

    return '';
}

function get_object_taxonomies($post_type, $output = 'names'): array
{
    global $arsenal_events_test_taxonomies;

    return $arsenal_events_test_taxonomies[(string) $post_type] ?? [];
}

function wp_get_post_terms($post_id, $taxonomy)
{
    global $arsenal_events_test_terms;

    return $arsenal_events_test_terms[(int) $post_id][(string) $taxonomy] ?? [];
}

function get_bloginfo($show = ''): string
{
    return '';
}

require_once __DIR__ . '/../mz-rest-api.php';

function arsenal_events_rest_test_assert($condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function arsenal_events_rest_test_assert_same($expected, $actual, string $message): void
{
    if ($expected === $actual) {
        return;
    }

    fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
    exit(1);
}

function arsenal_events_rest_test_seed(): void
{
    global $arsenal_events_test_posts,
        $arsenal_events_test_meta,
        $arsenal_events_test_fields,
        $arsenal_events_test_thumbnail_ids,
        $arsenal_events_test_attachment_urls,
        $arsenal_events_test_attachment_titles,
        $arsenal_events_test_attachment_captions,
        $arsenal_events_test_attachment_descriptions,
        $arsenal_events_test_attachment_sizes,
        $arsenal_events_test_taxonomies,
        $arsenal_events_test_terms;

    $arsenal_events_test_posts = [
        10 => new WP_Post([
            'ID' => 10,
            'post_name' => 'race-one',
            'post_title' => 'Race One',
            'post_type' => 'race',
            'post_content' => 'Race body.',
            'post_excerpt' => 'Race excerpt.',
        ]),
        20 => new WP_Post([
            'ID' => 20,
            'post_name' => 'resource-one',
            'post_title' => 'Resource One',
            'post_type' => 'resource',
            'post_content' => 'Resource body.',
            'post_excerpt' => '',
        ]),
    ];

    $arsenal_events_test_fields = [
        '10' => [
            'race_date' => '2027-03-13',
            'race_location' => '',
            'race_registration_link' => 'https://registration.example/race-one',
            'race_results_link' => '',
            'race_featured_image' => ['ID' => 501, 'url' => 'https://cdn.example/old-race.jpg', 'alt' => 'Hardcoded race alt'],
        ],
        '20' => [
            'resource_external_link' => '',
            'resource_download_link' => 701,
            'resource_featured_image' => 602,
        ],
    ];

    $arsenal_events_test_thumbnail_ids = [
        10 => 501,
        20 => 601,
    ];

    $arsenal_events_test_attachment_urls = [
        501 => 'https://cdn.example/race.jpg',
        601 => 'https://cdn.example/resource.jpg',
        602 => 'https://cdn.example/legacy-resource.jpg',
        701 => 'https://cdn.example/download.pdf',
    ];
    $arsenal_events_test_attachment_titles = [
        501 => 'Race image',
        601 => 'Resource image',
        602 => 'Legacy resource image',
        701 => 'Download PDF',
    ];
    $arsenal_events_test_attachment_captions = [
        501 => 'Race caption',
    ];
    $arsenal_events_test_attachment_descriptions = [
        501 => 'Race image description',
    ];
    $arsenal_events_test_attachment_sizes = [
        501 => [
            'full' => ['https://cdn.example/race.jpg', 1200, 800],
            'thumbnail' => ['https://cdn.example/race-thumb.jpg', 150, 150],
        ],
        601 => [
            'full' => ['https://cdn.example/resource.jpg', 1200, 800],
        ],
    ];

    $arsenal_events_test_meta = [
        10 => [
            '_wp_attachment_image_alt' => 'Race native media alt',
            '_yoast_wpseo_title' => 'Race SEO title',
            '_yoast_wpseo_metadesc' => 'Race SEO description',
            '_yoast_wpseo_opengraph-title' => 'Race OG title',
            '_yoast_wpseo_opengraph-description' => 'Race OG description',
            '_yoast_wpseo_opengraph-image' => 'https://cdn.example/race-og.jpg',
            '_yoast_wpseo_twitter-title' => '',
            '_yoast_wpseo_twitter-description' => '',
            '_yoast_wpseo_twitter-image' => '',
            '_yoast_wpseo_canonical' => 'https://arsenal-events.com/races/race-one',
            '_yoast_wpseo_meta-robots-noindex' => '1',
            '_yoast_wpseo_meta-robots-nofollow' => '',
        ],
        20 => [
            '_yoast_wpseo_title' => '',
            '_yoast_wpseo_metadesc' => '',
        ],
        501 => [
            '_wp_attachment_image_alt' => 'Race native media alt',
        ],
        601 => [
            '_wp_attachment_image_alt' => 'Resource native media alt',
        ],
        602 => [
            '_wp_attachment_image_alt' => 'Legacy resource alt',
        ],
        701 => [
            '_wp_attachment_image_alt' => '',
        ],
    ];

    $arsenal_events_test_taxonomies = [
        'race' => ['race_category'],
        'resource' => ['resource_category'],
    ];
    $arsenal_events_test_terms = [
        10 => [
            'race_category' => [
                (object) [
                    'term_id' => 3,
                    'slug' => 'road-race',
                    'name' => 'Road Race',
                    'taxonomy' => 'race_category',
                ],
            ],
        ],
        20 => [
            'resource_category' => [],
        ],
    ];
}

arsenal_events_rest_test_seed();

$race = meza_get_post_data(10, 'race');

arsenal_events_rest_test_assert_same('<p>Race body.</p>', $race['content'], 'Race content should serialize native editor body');
arsenal_events_rest_test_assert_same('Race body.', $race['content_raw'], 'Race raw content should be preserved');
arsenal_events_rest_test_assert_same('Race excerpt.', $race['excerpt'], 'Race excerpt should serialize native excerpt');
arsenal_events_rest_test_assert_same('', $race['race_location'], 'Intentional empty race location should remain empty');
arsenal_events_rest_test_assert_same('', $race['race_results_link'], 'Intentional empty race results URL should remain empty');
arsenal_events_rest_test_assert_same(501, $race['featured_image']['id'], 'Native featured image should be canonical');
arsenal_events_rest_test_assert_same('Race native media alt', $race['featured_image']['alt'], 'Canonical image alt should come from Media Library attachment meta');
arsenal_events_rest_test_assert_same($race['featured_image'], $race['race_featured_image'], 'Race compatibility image should mirror canonical native image');
arsenal_events_rest_test_assert_same(true, $race['legacy_acf_featured_image_matches_native'], 'Legacy race ACF image should be detected as matching native image');
arsenal_events_rest_test_assert_same('Road Race', $race['taxonomies']['race_category'][0]['name'] ?? '', 'Race category term should serialize');
arsenal_events_rest_test_assert_same('Race SEO title', $race['seo']['title'], 'Canonical SEO title should serialize');
arsenal_events_rest_test_assert_same('Race OG title', $race['seo']['open_graph']['title'], 'Open Graph SEO title should serialize');
arsenal_events_rest_test_assert_same('Race OG title', $race['seo']['twitter']['title'], 'Twitter title should fall back to Open Graph title');
arsenal_events_rest_test_assert_same(true, $race['seo']['robots']['noindex'], 'Robots noindex intent should serialize');
arsenal_events_rest_test_assert_same('Race SEO title', $race['yoast_meta']['title'], 'Yoast compatibility metadata should be preserved');

$resource = meza_get_post_data(20, 'resource');

arsenal_events_rest_test_assert_same('<p>Resource body.</p>', $resource['content'], 'Resource content should serialize native editor body');
arsenal_events_rest_test_assert_same('Resource body.', $resource['excerpt'], 'Resource excerpt should fall back to trimmed native body');
arsenal_events_rest_test_assert_same(601, $resource['featured_image']['id'], 'Resource native featured image should be canonical');
arsenal_events_rest_test_assert_same('Resource native media alt', $resource['resource_featured_image']['alt'], 'Resource compatibility image should mirror native image alt');
arsenal_events_rest_test_assert_same(false, $resource['legacy_acf_featured_image_matches_native'], 'Mismatched legacy resource ACF image should not be canonical');
arsenal_events_rest_test_assert_same('', $resource['resource_external_link'], 'Intentional empty external URL should remain empty');
arsenal_events_rest_test_assert_same(701, $resource['resource_download_link']['id'], 'Resource download should keep approved ACF file field');
arsenal_events_rest_test_assert_same([], $resource['taxonomies']['resource_category'], 'Empty taxonomy terms should serialize as an empty array');

$normalized = meza_normalize_acf_asset(['ID' => 501, 'url' => 'https://cdn.example/stale.jpg', 'alt' => 'Hardcoded stale alt']);
arsenal_events_rest_test_assert_same('Race native media alt', $normalized['alt'], 'ACF image arrays should use Media Library alt text, not stored array alt');

$requirements = meza_get_arsenal_media_requirements([
    'rd_hero_image' => null,
    'rr_hero_image' => ['id' => 601, 'url' => 'https://cdn.example/resource.jpg'],
]);
arsenal_events_rest_test_assert_same(false, $requirements['ok'], 'Missing required media should produce explicit error state');
arsenal_events_rest_test_assert_same('rd_hero_image', $requirements['errors'][0]['field'] ?? '', 'Missing required media error should identify the field');

echo "Arsenal Events REST payload tests passed\n";
