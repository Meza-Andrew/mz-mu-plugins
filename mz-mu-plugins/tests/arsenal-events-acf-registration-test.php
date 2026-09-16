<?php

$arsenal_events_filters = [];
$arsenal_events_test_home_url = 'http://arsenal-events.local/';

function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1): void
{
    global $arsenal_events_filters;

    $arsenal_events_filters[$hook_name][] = $callback;
}

function home_url($path = ''): string
{
    global $arsenal_events_test_home_url;

    return rtrim($arsenal_events_test_home_url, '/') . '/' . ltrim((string) $path, '/');
}

require_once __DIR__ . '/../mz-acf-project-options/modules/field-groups/definitions/arsenal-events.php';

function arsenal_events_test_assert($condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function arsenal_events_test_group_matches_default_page_editor(array $group): bool
{
    foreach ((array) ($group['location'] ?? []) as $location_group) {
        $matches_page = false;
        $excluded_default_template = false;

        foreach ((array) $location_group as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            if (
                ($rule['param'] ?? '') === 'post_type'
                && ($rule['operator'] ?? '') === '=='
                && ($rule['value'] ?? '') === 'page'
            ) {
                $matches_page = true;
            }

            if (
                ($rule['param'] ?? '') === 'page_template'
                && ($rule['operator'] ?? '') === '!='
                && ($rule['value'] ?? '') === 'default'
            ) {
                $excluded_default_template = true;
            }
        }

        if ($matches_page && !$excluded_default_template) {
            return true;
        }
    }

    return false;
}

$groups = [
    [
        'key' => 'group_meza_hero_section',
        'title' => 'Hero Section',
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'page',
                ],
            ],
        ],
    ],
    [
        'key' => 'group_meza_list_services_section',
        'title' => 'List Services Section',
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'page',
                ],
            ],
            [
                [
                    'param' => 'taxonomy',
                    'operator' => '==',
                    'value' => 'category',
                ],
            ],
        ],
    ],
    [
        'key' => 'group_other_page_fields',
        'title' => 'Other Page Fields',
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'page',
                ],
            ],
        ],
    ],
    arsenal_events_get_page_blocks_group(),
];

$filtered = arsenal_events_filter_default_page_acf_field_groups($groups);
$groups_by_title = [];

foreach ($filtered as $group) {
    $groups_by_title[$group['title']] = $group;
}

foreach (arsenal_events_get_hidden_default_page_field_group_titles() as $title) {
    if (!isset($groups_by_title[$title])) {
        continue;
    }

    arsenal_events_test_assert(
        !arsenal_events_test_group_matches_default_page_editor($groups_by_title[$title]),
        "{$title} should not match Arsenal default page editors"
    );
}

arsenal_events_test_assert(
    arsenal_events_test_group_matches_default_page_editor($groups_by_title['Other Page Fields']),
    'Unlisted page groups should remain visible on default page editors'
);

arsenal_events_test_assert(
    arsenal_events_test_group_matches_default_page_editor($groups_by_title['Page Blocks']),
    'Page Blocks should remain visible on Arsenal default page editors'
);

$page_block_layouts = array_map(
    static fn(array $layout): string => (string) ($layout['name'] ?? ''),
    $groups_by_title['Page Blocks']['fields'][0]['layouts'] ?? []
);

arsenal_events_test_assert(
    $page_block_layouts === ['hero_block', 'services_block'],
    'Page Blocks should expose only hero_block and services_block layouts'
);

$loaded_groups = arsenal_events_filter_loaded_default_page_acf_field_groups(
    [
        [
            'key' => 'group_page_blocks',
            'title' => 'Page Blocks',
        ],
        [
            'key' => 'group_db_list_segments_section',
            'title' => 'List Segments Section',
        ],
        [
            'key' => 'group_db_list_localities_section',
            'title' => 'List Localities Section',
        ],
    ],
    'page'
);
$loaded_titles = array_map(static fn(array $group): string => (string) ($group['title'] ?? ''), $loaded_groups);

arsenal_events_test_assert(
    $loaded_titles === ['Page Blocks'],
    'Loaded Arsenal default page groups should remove saved generic section groups and preserve Page Blocks'
);

$saved_group = arsenal_events_filter_default_page_acf_field_group_location([
    'key' => 'group_db_list_localities_section',
    'title' => 'List Localities Section',
    'location' => [
        [
            [
                'param' => 'post_type',
                'operator' => '==',
                'value' => 'page',
            ],
        ],
    ],
]);

arsenal_events_test_assert(
    !arsenal_events_test_group_matches_default_page_editor($saved_group),
    'Saved generic ACF field groups should have locations adjusted before ACF visibility checks'
);

$arsenal_events_test_home_url = 'http://example.local/';
$non_arsenal = arsenal_events_filter_default_page_acf_field_groups([$groups[0]]);

arsenal_events_test_assert(
    arsenal_events_test_group_matches_default_page_editor($non_arsenal[0]),
    'Non-Arsenal sites should keep generic field group page locations unchanged'
);

echo "Arsenal Events ACF registration tests passed\n";
