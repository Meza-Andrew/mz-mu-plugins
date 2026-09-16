<?php

define('ARSENAL_EVENTS_SITE', true);
define('ARSENAL_EVENTS_CORS_TESTS', true);
define('ARSENAL_EVENTS_REVALIDATION_TESTS', true);

$arsenal_events_test_posts = [];
$arsenal_events_test_fields = [];
$arsenal_events_test_meta = [];
$arsenal_events_test_attachment_urls = [];
$arsenal_events_test_attachment_titles = [];

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
        $this->post_type = (string) ($data['post_type'] ?? 'page');
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
    public function __construct($data = null, int $status = 200)
    {
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

function home_url($path = ''): string
{
    return 'http://arsenal-events.local/' . ltrim((string) $path, '/');
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

function get_field($field_name, $post_id = false)
{
    global $arsenal_events_test_fields;

    return $arsenal_events_test_fields[(int) $post_id][(string) $field_name] ?? null;
}

function get_post_thumbnail_id($post_id): int
{
    return 0;
}

function wp_get_attachment_image_src($attachment_id, $size)
{
    return false;
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
    return '';
}

function get_post_field($field, $post_id): string
{
    return '';
}

function wp_trim_excerpt($text = '', $post_id = 0): string
{
    return '';
}

function get_bloginfo($show = ''): string
{
    return '';
}

function get_object_taxonomies($post_type, $output = 'names'): array
{
    return [];
}

function wp_get_post_terms($post_id, $taxonomy)
{
    return [];
}

require_once __DIR__ . '/../mz-acf-project-options/modules/field-groups/definitions/arsenal-events.php';
require_once __DIR__ . '/../mz-rest-api.php';

function arsenal_events_structured_test_assert($condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function arsenal_events_structured_test_assert_same($expected, $actual, string $message): void
{
    if ($expected === $actual) {
        return;
    }

    fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
    exit(1);
}

$arsenal_events_test_posts = [
    1 => new WP_Post(['ID' => 1, 'post_name' => 'home', 'post_title' => 'Home', 'post_content' => 'Welcome.']),
    2 => new WP_Post(['ID' => 2, 'post_name' => 'for-race-directors', 'post_title' => 'For Race Directors']),
    3 => new WP_Post(['ID' => 3, 'post_name' => 'races-and-results', 'post_title' => 'Races & Results']),
    4 => new WP_Post(['ID' => 4, 'post_name' => 'about', 'post_title' => 'About']),
];
$arsenal_events_test_fields = [
    1 => [
        'home_upcoming_races_title' => 'Upcoming From CMS',
        'home_upcoming_races_description' => '',
        'home_stats_title' => 'Home Stats',
        'home_stats_items' => [
            ['stat_icon' => ['ID' => 901, 'alt' => 'Stored stale alt'], 'stat_value' => '12', 'stat_label' => 'Events'],
        ],
        'home_testimonials_title' => 'Testimonials',
        'home_testimonials_description' => 'Author intro',
        'home_testimonials_items' => [
            ['testimonial_type' => 'runner', 'testimonial_label' => '', 'testimonial_quote' => 'Great race.', 'testimonial_attribution' => 'Runner A'],
        ],
        'home_resources_title' => 'Resource Cards',
        'home_resources_description' => 'Card intro',
        'home_resources_items' => [
            ['resource_title' => 'Checklist', 'resource_image' => ['ID' => 902, 'alt' => 'Stale resource alt'], 'resource_category' => 'for_directors', 'resource_link' => ''],
        ],
        'home_cta_title' => 'Home CTA',
        'home_cta_description' => 'CTA copy',
        'home_cta_primary_text' => 'Primary',
        'home_cta_primary_href' => '/contact',
        'home_cta_secondary_text' => '',
        'home_cta_secondary_href' => '',
    ],
    2 => [
        'rd_stats_title' => 'Director Stats',
        'rd_stats_items' => [
            ['stat_icon' => 901, 'stat_value' => '4', 'stat_label' => 'Services'],
        ],
        'rd_inquiry_title' => 'Inquiry',
        'rd_inquiry_description' => '',
        'rd_inquiry_primary_text' => 'Email',
        'rd_inquiry_primary_href' => 'mailto:kristen@example.invalid',
        'rd_inquiry_secondary_text' => 'Contact',
        'rd_inquiry_secondary_href' => '/contact',
        'rd_resources_title' => 'Director Resources',
        'rd_resources_description' => '',
        'rd_cta_title' => 'Plan',
        'rd_cta_description' => 'Plan copy',
        'rd_cta_primary_text' => 'Start',
        'rd_cta_primary_href' => '/contact',
        'rd_cta_secondary_text' => '',
        'rd_cta_secondary_href' => '',
    ],
    3 => [
        'rr_results_title' => 'Results',
        'rr_results_description' => 'Result intro',
        'rr_results_unavailable_label' => '',
        'rr_results_unavailable_description' => 'Use race records.',
        'rr_upcoming_races_title' => 'Upcoming',
        'rr_upcoming_races_description' => '',
        'rr_stats_title' => 'CMS Records',
        'rr_stats_items' => [],
        'rr_resources_title' => 'Resources',
        'rr_resources_description' => 'Resource intro',
        'rr_cta_title' => 'Next line',
        'rr_cta_description' => '',
        'rr_cta_primary_text' => 'View Races',
        'rr_cta_primary_href' => '/races',
        'rr_cta_secondary_text' => 'Contact',
        'rr_cta_secondary_href' => '/contact',
    ],
];
$arsenal_events_test_meta = [
    901 => ['_wp_attachment_image_alt' => 'Authoritative stat alt'],
    902 => ['_wp_attachment_image_alt' => 'Authoritative resource alt'],
];
$arsenal_events_test_attachment_urls = [
    901 => 'https://cdn.example/stat.svg',
    902 => 'https://cdn.example/resource.jpg',
];
$arsenal_events_test_attachment_titles = [
    901 => 'Stat icon',
    902 => 'Resource image',
];

$home = meza_get_page_structured_content(1);
arsenal_events_structured_test_assert_same('Upcoming From CMS', $home['upcoming_races']['title'], 'Homepage should receive upcoming races structured title');
arsenal_events_structured_test_assert_same('', $home['upcoming_races']['description'], 'Cleared homepage upcoming description should remain empty');
arsenal_events_structured_test_assert_same('Authoritative stat alt', $home['stats']['items'][0]['stat_icon']['alt'] ?? '', 'Stats image alt should come from Media Library');
arsenal_events_structured_test_assert_same('Authoritative resource alt', $home['resources']['items'][0]['resource_image']['alt'] ?? '', 'Resource image alt should come from Media Library');
arsenal_events_structured_test_assert_same('', $home['cta']['secondary_button_text'], 'Cleared homepage secondary CTA text should remain empty');

$home_blocks = meza_get_page_blocks(1);
arsenal_events_structured_test_assert_same(
    ['upcoming_races_block', 'testimonials_stats_block', 'resources_block', 'cta_block'],
    array_map(static fn(array $block): string => $block['type'], $home_blocks),
    'Homepage structured fields should serialize as supported structured blocks'
);

$rd = meza_get_page_structured_content(2);
arsenal_events_structured_test_assert_same('Director Stats', $rd['stats']['title'], 'Race Director page should receive stats payload');
arsenal_events_structured_test_assert_same('', $rd['inquiry']['description'], 'Cleared Race Director inquiry description should remain empty');
arsenal_events_structured_test_assert_same('Plan', $rd['cta']['title'], 'Race Director page should receive CTA payload');

$rr = meza_get_page_structured_content(3);
arsenal_events_structured_test_assert_same('Results', $rr['results']['title'], 'Races & Results page should receive results payload');
arsenal_events_structured_test_assert_same('', $rr['results']['unavailable_label'], 'Cleared results unavailable label should remain empty');
arsenal_events_structured_test_assert_same('Resources', $rr['resources']['title'], 'Races & Results page should receive resources payload');

arsenal_events_structured_test_assert_same([], meza_get_page_structured_content(4), 'Unrelated pages should not receive page-specific structured fields');

$site_manager_caps = ['edit_pages' => true];
$subscriber_caps = ['read' => true];
arsenal_events_structured_test_assert(!empty($site_manager_caps['edit_pages']), 'Site Manager should be able to access page editor controls through edit_pages');
arsenal_events_structured_test_assert(empty($subscriber_caps['edit_pages']), 'Lower privilege roles should not access page editor controls');

$groups = [
    arsenal_events_get_home_structured_content_group(),
    arsenal_events_get_race_director_structured_content_group(),
    arsenal_events_get_races_results_structured_content_group(),
];
$expected_slugs = ['home', 'for-race-directors', 'races-and-results'];
foreach ($groups as $index => $group) {
    arsenal_events_structured_test_assert_same(
        $expected_slugs[$index],
        $group['location'][0][0]['value'] ?? '',
        'Structured field group should be scoped to its target page slug'
    );
}

echo "Arsenal Events structured page tests passed\n";
