<?php

$arsenal_events_options_pages = [];

function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1): void
{
}

function acf_add_options_page(array $args): void
{
    global $arsenal_events_options_pages;

    $arsenal_events_options_pages[] = $args;
}

function __($text, $domain = null)
{
    return $text;
}

function _x($text, $context = null, $domain = null)
{
    return $text;
}

function register_post_type($post_type, array $args): void
{
}

function register_taxonomy($taxonomy, array $object_type, array $args): void
{
}

function meza_access_site_settings_capability(): string
{
    return 'meza_access_site_settings';
}

require_once __DIR__ . '/../arsenal-events-cpt.php';
require_once __DIR__ . '/../mz-acf-project-options/modules/field-groups/definitions/arsenal-events-media.php';

function arsenal_events_role_test_assert($condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function arsenal_events_role_test_assert_same($expected, $actual, string $message): void
{
    if ($expected === $actual) {
        return;
    }

    fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
    exit(1);
}

function arsenal_events_role_can_access(array $role_caps, string $capability): bool
{
    return !empty($role_caps[$capability]);
}

arsenal_events_register_options_page();
arsenal_events_register_media_options_pages();

$pages_by_slug = [];
foreach ($arsenal_events_options_pages as $page) {
    $pages_by_slug[(string) ($page['menu_slug'] ?? '')] = $page;
}

$expected_capability = meza_access_site_settings_capability();

arsenal_events_role_test_assert_same(
    $expected_capability,
    arsenal_events_site_settings_capability(),
    'Arsenal helper should use established Meza site settings capability'
);

arsenal_events_role_test_assert_same(
    $expected_capability,
    $pages_by_slug['arsenal-events-settings']['capability'] ?? '',
    'Arsenal Settings page should require Site Manager settings capability'
);

arsenal_events_role_test_assert_same(
    $expected_capability,
    $pages_by_slug['arsenal-events-media']['capability'] ?? '',
    'Arsenal Media page should require Site Manager settings capability'
);

$site_manager_caps = [
    'read' => true,
    $expected_capability => true,
];
$editor_caps = [
    'read' => true,
    'edit_posts' => true,
];
$subscriber_caps = [
    'read' => true,
];

foreach (['arsenal-events-settings', 'arsenal-events-media'] as $slug) {
    $capability = (string) ($pages_by_slug[$slug]['capability'] ?? '');

    arsenal_events_role_test_assert(
        arsenal_events_role_can_access($site_manager_caps, $capability),
        "Site Manager should have access to {$slug}"
    );
    arsenal_events_role_test_assert(
        !arsenal_events_role_can_access($editor_caps, $capability),
        "Editor should not have access to {$slug}"
    );
    arsenal_events_role_test_assert(
        !arsenal_events_role_can_access($subscriber_caps, $capability),
        "Subscriber should not have access to {$slug}"
    );
}

echo "Arsenal Events role access tests passed\n";
