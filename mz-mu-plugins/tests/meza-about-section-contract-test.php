<?php

declare(strict_types=1);

function fail_about(string $message): void { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expect_about(bool $condition, string $message): void { if (!$condition) fail_about($message); }

$root = dirname(__DIR__, 2);
$trees = [
    $root . '/mz-acf-project-options/modules/field-groups/definitions/section-field-groups.php',
    $root . '/mz-mu-plugins/mz-acf-project-options/modules/field-groups/definitions/section-field-groups.php',
];

$about_definitions = [];
foreach ($trees as $tree) {
    $script = <<<'PHP'
function sanitize_key($value) { return $value; }
function meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies() { return [['page']]; }
require $argv[1];
echo json_encode(meza_get_about_section_field_group_definition());
PHP;
    $command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' ' . escapeshellarg($tree);
    $json = shell_exec($command);
    $definition = json_decode((string) $json, true);
    expect_about(is_array($definition), basename($tree) . ' loads the About helper in an isolated process');
    expect_about(($definition['key'] ?? '') === 'group_meza_about_section', 'generic About group key');
    expect_about(($definition['title'] ?? '') === 'About Section', 'About title');
    $field = $definition['fields'][0] ?? [];
    expect_about(($field['key'] ?? '') === 'field_meza_section_about' && ($field['name'] ?? '') === 'section_about', 'About storage group');
    $sub = $field['sub_fields'] ?? [];
    $expected = ['headline' => ['field_meza_about_headline', 'text'], 'subhead' => ['field_meza_about_subhead', 'textarea'], 'image' => ['field_meza_about_image', 'image'], 'link' => ['field_meza_about_link', 'link']];
    expect_about(count($sub) === 4, 'About authoring shape is exact');
    foreach ($expected as $name => [$key, $type]) {
        $match = array_values(array_filter($sub, static fn(array $candidate): bool => ($candidate['name'] ?? '') === $name));
        expect_about(count($match) === 1 && ($match[0]['key'] ?? '') === $key && ($match[0]['type'] ?? '') === $type, "About {$name} contract");
        expect_about(($match[0]['default_value'] ?? '') === '', "About {$name} has no editorial default");
        if ($name === 'headline') {
            expect_about(($match[0]['label'] ?? '') === 'Headline (H2)', 'About headline uses the shared H2 label');
        }
    }
    expect_about(($sub[2]['return_format'] ?? '') === 'id' && ($sub[3]['return_format'] ?? '') === 'array', 'compatible image/link return formats');
    expect_about(($definition['location'] ?? null) === [[['param' => 'post_type', 'operator' => '==', 'value' => 'page']]], 'About targets pages only');
    expect_about(stripos(json_encode($definition), 'arsenal') === false, 'About contains no Arsenal identifier');
    expect_about(!str_contains(file_get_contents($tree), "add_action('init', 'meza_get_about"), 'About helper registers no mutation action');
    $about_definitions[] = $definition;
}
expect_about($about_definitions[0] === $about_definitions[1], 'root and nested About definitions are semantically identical');

foreach ([$root . '/mz-acf-project-options/modules/field-groups/definitions/registry.php', $root . '/mz-mu-plugins/mz-acf-project-options/modules/field-groups/definitions/registry.php'] as $registry) {
    $content = file_get_contents($registry);
    expect_about(!str_contains($content, 'meza_get_about_section_field_group_definition'), 'About is absent from the default registry');
    expect_about(str_contains($content, "apply_filters('meza_shared_project_acf_field_groups'"), 'existing opt-in filter remains available');
}

foreach (['mz-acf-project-options', 'mz-mu-plugins/mz-acf-project-options'] as $tree) {
    $script = <<<'PHP'
$filters = []; function add_filter($hook, $callback) { global $filters; $filters[$hook][] = $callback; }
function apply_filters($hook, $value) { global $filters; foreach ($filters[$hook] ?? [] as $callback) $value = $callback($value); return $value; }
function meza_normalize_header_section_group($value) { return $value; }
function meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies() { return []; }
function meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies_excluding_posts() { return []; }
function meza_get_acf_location_rules_hidden_by_default() { return []; }
$registry = $argv[1]; $section = $argv[2]; require $section;
preg_match_all('/\b(meza_get_[a-z0-9_]+)\s*\(/i', file_get_contents($registry), $matches);
foreach (array_unique($matches[1]) as $name) if (!function_exists($name) && $name !== 'meza_get_shared_project_acf_field_groups') eval('function ' . $name . '(){ return []; }');
add_filter('meza_shared_project_acf_field_groups', static function(array $groups): array { $groups[] = meza_get_about_section_field_group_definition(); return $groups; });
require $registry;
echo json_encode(array_column(meza_get_shared_project_acf_field_groups(), 'key'));
PHP;
    $registry = $root . '/' . $tree . '/modules/field-groups/definitions/registry.php';
    $section = $root . '/' . $tree . '/modules/field-groups/definitions/section-field-groups.php';
    $after = json_decode((string) shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' ' . escapeshellarg($registry) . ' ' . escapeshellarg($section)), true);
    expect_about(count(array_keys((array) $after, 'group_meza_about_section', true)) === 1, 'real shared-project filter adds About exactly once');
}

foreach ([$root . '/mz-acf-project-options/modules/managed-definitions.php', $root . '/mz-mu-plugins/mz-acf-project-options/modules/managed-definitions.php'] as $file) {
    $content = file_get_contents($file);
    expect_about(is_string($content) && substr_count($content, "'key' => 'field_meza_list_profiles_selected_profiles'") === 1, 'Profiles field is appended exactly once');
    expect_about(str_contains($content, "'name' => 'profiles'") && str_contains($content, "'type' => 'relationship'") && str_contains($content, "'post_type' => ['profile']") && str_contains($content, "'post_status' => ['publish']") && str_contains($content, "'return_format' => 'id'"), 'Profiles relationship contract');
    expect_about(str_contains($content, 'dragged to control display order') && str_contains($content, "'bidirectional' => 0"), 'Profiles ordering and no bidirectional contract');
}

foreach (['mz-acf-project-options', 'mz-mu-plugins/mz-acf-project-options'] as $tree) {
    $script = <<<'PHP'
function add_action(...$args) {} function add_filter(...$args) {} function apply_filters($hook, $value) { return $value; }
function sanitize_key($value) { return $value; } function meza_get_acf_location_rules_hidden_by_default() { return []; }
function meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies() { return []; }
function meza_supports_sponsor_features() { return false; } function meza_supports_profile_features() { return false; }
function meza_is_nonprofit_business_type() { return false; } function meza_events_have_speakers_topics() { return false; }
function meza_get_contact_locations_section_field_group_definition() { return []; } function meza_business_information_enables_ecommerce() { return false; }
function meza_normalize_header_section_group($value) { return $value; }
require $argv[1]; require $argv[2];
foreach (meza_get_default_editable_acf_field_group_definitions() as $group) if (($group['key'] ?? '') === 'group_meza_list_profiles_section') echo json_encode($group);
PHP;
    $section = $root . '/' . $tree . '/modules/field-groups/definitions/section-field-groups.php';
    $managed = $root . '/' . $tree . '/modules/managed-definitions.php';
    $group = json_decode((string) shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' ' . escapeshellarg($section) . ' ' . escapeshellarg($managed)), true);
    $sub = $group['fields'][1]['sub_fields'] ?? [];
    expect_about(array_column($sub, 'name') === ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary', 'id', 'profiles'], 'loaded List Profiles subfield order');
    $profiles = $sub[7] ?? [];
    expect_about(($profiles['key'] ?? '') === 'field_meza_list_profiles_selected_profiles' && ($profiles['type'] ?? '') === 'relationship', 'loaded Profiles field key/type');
    expect_about(($profiles['post_type'] ?? null) === ['profile'] && ($profiles['post_status'] ?? null) === ['publish'] && ($profiles['return_format'] ?? '') === 'id', 'loaded Profiles type/status/return');
    expect_about(($profiles['required'] ?? 1) === 0 && ($profiles['min'] ?? 1) === 0 && ($profiles['max'] ?? 1) === 0 && ($profiles['filters'] ?? null) === ['search'] && ($profiles['bidirectional'] ?? 1) === 0, 'loaded Profiles optional ordered relationship contract');
}

echo "PASS meza-about-section-contract-test\n";
