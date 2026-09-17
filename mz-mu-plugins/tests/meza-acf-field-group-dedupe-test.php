<?php

$meza_acf_dedupe_test_actions = [];
$meza_acf_dedupe_test_options = [];
$meza_acf_dedupe_test_posts = [];
$meza_acf_dedupe_test_updates = [];

function meza_acf_dedupe_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function meza_acf_dedupe_test_assert_same($expected, $actual, string $message): void
{
    if ($expected === $actual) {
        return;
    }

    throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
}

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
{
    global $meza_acf_dedupe_test_actions;

    $meza_acf_dedupe_test_actions[$hook][$priority][] = $callback;
}

function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
{
}

function sanitize_key($key): string
{
    $key = strtolower((string) $key);

    return preg_replace('/[^a-z0-9_\-]/', '', $key) ?: '';
}

function get_option($name, $default = false)
{
    global $meza_acf_dedupe_test_options;

    return array_key_exists((string) $name, $meza_acf_dedupe_test_options)
        ? $meza_acf_dedupe_test_options[(string) $name]
        : $default;
}

function update_option($name, $value, $autoload = null): bool
{
    global $meza_acf_dedupe_test_options;

    $meza_acf_dedupe_test_options[(string) $name] = $value;

    return true;
}

class WP_Post
{
    public int $ID;
    public string $post_name;
    public string $post_title;
    public string $post_status;

    public function __construct(int $id, string $post_name, string $post_title, string $post_status)
    {
        $this->ID = $id;
        $this->post_name = $post_name;
        $this->post_title = $post_title;
        $this->post_status = $post_status;
    }
}

function get_posts(array $args): array
{
    global $meza_acf_dedupe_test_posts;

    $name = (string) ($args['name'] ?? '');
    $statuses = array_map('strval', (array) ($args['post_status'] ?? []));
    $ids = [];

    foreach ($meza_acf_dedupe_test_posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        if ((string) $post->post_name !== $name) {
            continue;
        }

        if (!in_array((string) $post->post_status, $statuses, true)) {
            continue;
        }

        $ids[] = (int) $post->ID;
    }

    sort($ids, SORT_NUMERIC);

    return $ids;
}

function get_post($post_id)
{
    global $meza_acf_dedupe_test_posts;

    return $meza_acf_dedupe_test_posts[(int) $post_id] ?? null;
}

function wp_update_post(array $postarr)
{
    global $meza_acf_dedupe_test_posts, $meza_acf_dedupe_test_updates;

    $post_id = (int) ($postarr['ID'] ?? 0);
    if (!$post_id || !isset($meza_acf_dedupe_test_posts[$post_id])) {
        return 0;
    }

    if (isset($postarr['post_status'])) {
        $meza_acf_dedupe_test_posts[$post_id]->post_status = (string) $postarr['post_status'];
    }

    $meza_acf_dedupe_test_updates[] = $postarr;

    return $post_id;
}

require_once __DIR__ . '/../mz-acf-project-options/modules/registration.php';

$meza_acf_dedupe_test_posts = [
    11 => new WP_Post(11, 'group_9f5b5c9a', 'List Localities Section', 'publish'),
    12 => new WP_Post(12, 'group_9f5b5c9a', 'List Localities Section', 'publish'),
    13 => new WP_Post(13, 'group_9f5b5c9a', 'List Localities Section', 'acf-disabled'),
    14 => new WP_Post(14, 'group_9f5b5c9a', 'Different Section', 'publish'),
    15 => new WP_Post(15, 'group_other', 'List Localities Section', 'publish'),
];

meza_acf_dedupe_test_assert_same(
    [
        [
            'post_name' => 'group_9f5b5c9a',
            'title' => 'List Localities Section',
        ],
    ],
    meza_get_managed_acf_field_group_dedupe_specs(),
    'The configured ACF dedupe target should remain the shared Localities section.'
);
meza_acf_dedupe_test_assert(
    !str_contains(meza_get_managed_acf_field_group_dedupe_version(), strtolower('AR' . 'SENAL')),
    'The shared ACF dedupe version must not include a project marker.'
);

meza_dedupe_managed_acf_field_groups();

meza_acf_dedupe_test_assert_same('publish', $meza_acf_dedupe_test_posts[11]->post_status, 'The first active Localities group should stay published.');
meza_acf_dedupe_test_assert_same('acf-disabled', $meza_acf_dedupe_test_posts[12]->post_status, 'A duplicate active Localities group should be disabled.');
meza_acf_dedupe_test_assert_same('acf-disabled', $meza_acf_dedupe_test_posts[13]->post_status, 'An existing disabled Localities duplicate should remain disabled.');
meza_acf_dedupe_test_assert_same('publish', $meza_acf_dedupe_test_posts[14]->post_status, 'A same-slug field group with a different title should not be touched.');
meza_acf_dedupe_test_assert_same('publish', $meza_acf_dedupe_test_posts[15]->post_status, 'A same-title field group with a different slug should not be touched.');
meza_acf_dedupe_test_assert_same(
    [['ID' => 12, 'post_status' => 'acf-disabled']],
    $meza_acf_dedupe_test_updates,
    'Only the extra active duplicate should be updated.'
);
meza_acf_dedupe_test_assert_same(
    meza_get_managed_acf_field_group_dedupe_version(),
    get_option(meza_get_managed_acf_field_group_dedupe_option_name(), ''),
    'The generic shared dedupe version should be recorded.'
);

$meza_acf_dedupe_test_updates = [];
meza_dedupe_managed_acf_field_groups();
meza_acf_dedupe_test_assert_same([], $meza_acf_dedupe_test_updates, 'A completed dedupe version should skip repeated work.');

echo "Meza ACF field-group dedupe tests passed\n";
