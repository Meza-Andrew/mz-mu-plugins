<?php
declare(strict_types=1);

function fail_authoring(string $message): void { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expect_authoring(bool $value, string $message): void { if (!$value) fail_authoring($message); }

$root = dirname(__DIR__, 2);
$trees = ['mz-acf-project-options', 'mz-mu-plugins/mz-acf-project-options'];
foreach ($trees as $tree) {
    $base = $root . '/' . $tree . '/modules/field-groups/definitions/';
    $content = (string) file_get_contents($base . 'content-field-groups.php');
    $model = (string) file_get_contents($base . 'content-model-definitions.php');
    $section = (string) file_get_contents($base . 'section-field-groups.php');
    $registry = (string) file_get_contents($base . 'registry.php');
    $runtime = (string) file_get_contents($base . 'runtime.php');
    $managed = (string) file_get_contents($root . '/' . $tree . '/modules/managed-definitions.php');
    $documentation = (string) file_get_contents($root . '/' . ($tree === 'mz-acf-project-options'
        ? 'mz-site-documentation/modules/content-inventory/content-types.php'
        : 'mz-mu-plugins/mz-site-documentation/modules/content-inventory/content-types.php'));

    expect_authoring(substr_count($content, "'key' => 'field_meza_profile_fun_photo'") === 1, "$tree has exactly one Fun Photo");
    expect_authoring(str_contains($content, "'key' => 'field_meza_service_cta_link'") && str_contains($content, "'name' => 'cta_link'") && str_contains($content, "'return_format' => 'array'"), "$tree has optional Service CTA link");
    expect_authoring(str_contains($model, "'featured_image' => 'Serious Photo'") && str_contains($model, "'set_featured_image' => 'Set Serious Photo'"), "$tree retains native Serious Photo support");
    expect_authoring(!str_contains($content, "'name' => 'serious_photo'"), "$tree has no duplicate serious_photo ACF field");
    expect_authoring(str_contains($section, "'headline_default' => 'Upcoming Races'") && str_contains($section, "'headline_default' => 'Results'"), "$tree has distinct race and results labels");
    expect_authoring(substr_count($registry, 'meza_get_list_events_section_field_group_definition()') === 0 && substr_count($registry, 'meza_get_list_past_events_section_field_group_definition()') === 1, "$tree keeps List Events in managed defaults and List Past Events in the shared registry");
    expect_authoring(substr_count($managed, 'meza_get_list_events_section_field_group_definition()') >= 1, "$tree retains List Events in its managed default source");
    expect_authoring(str_contains($runtime, "section_list-past-events"), "$tree recognizes List Past Events runtime identity");
    expect_authoring(str_contains($managed, 'meza_augment_shared_project_statistics_fields') && str_contains($managed, "['statistics', 'metrics']"), "$tree augments only supplied statistics repeaters");
    expect_authoring(str_contains($documentation, 'meza_get_public_facing_url($resolved_term_url)') && str_contains($documentation, "'action_url' => \$edit_url"), "$tree keeps documentation edit URLs separate from public term views");
    expect_authoring(!str_contains($managed, 'Arsenal'), "$tree contains no Arsenal-specific authoring value");

    $script = <<<'PHP'
function sanitize_key($value) { return strtolower((string) $value); }
function meza_is_conference_business_type() { return false; }
function meza_get_acf_location_rules_hidden_by_default() { return []; }
require $argv[1]; require $argv[2]; require $argv[3];
echo json_encode([meza_get_service_field_group_definition(), meza_get_profile_field_group_definition(), meza_get_list_events_section_field_group_definition(), meza_get_list_past_events_section_field_group_definition()]);
PHP;
    $actual = json_decode((string) shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' ' . escapeshellarg($base . 'content-field-groups.php') . ' ' . escapeshellarg($base . 'content-model-definitions.php') . ' ' . escapeshellarg($base . 'section-field-groups.php')), true);
    [$service, $profile, $events, $past_events] = $actual;
    $service_names = array_column((array) ($service['fields'] ?? []), 'name');
    $profile_names = array_column((array) ($profile['fields'] ?? []), 'name');
    expect_authoring(in_array('cta_link', $service_names, true) && ($service['fields'][array_search('cta_link', $service_names, true)]['required'] ?? 1) === 0, "$tree returns optional Service CTA field");
    expect_authoring(in_array('fun_photo', $profile_names, true) && !in_array('serious_photo', $profile_names, true), "$tree returns Fun Photo without a second Serious Photo field");
    expect_authoring(($events['fields'][1]['sub_fields'][0]['default_value'] ?? '') === 'Upcoming Races' && ($past_events['fields'][1]['sub_fields'][0]['default_value'] ?? '') === 'Results', "$tree returns distinct event section defaults");

    $pipeline = <<<'PHP'
$injected=[]; $filter_calls=0; function add_action(...$a){} function add_filter(...$a){} function apply_filters($hook,$value){ global $injected,$filter_calls; if ($hook === 'meza_shared_project_default_acf_field_group_definitions') { $filter_calls++; return array_merge($value,$injected); } return $value; }
function sanitize_key($value){ return strtolower((string) $value); } function meza_get_acf_location_rules_hidden_by_default(){ return []; } function meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies(){ return []; }
function meza_normalize_header_section_group($value){ return $value; } function meza_apply_default_editable_acf_field_group_location_rules($value){ return $value; } function meza_business_information_enables_ecommerce(){ return false; }
function meza_supports_sponsor_features(){ return false; } function meza_are_localities_enabled(){ return false; } function meza_get_contact_locations_section_field_group_definition(){ return []; }
require $argv[2]; $source=file_get_contents($argv[1]); preg_match_all('/function\s+(meza_[a-zA-Z0-9_]+)\s*\(/',$source,$matches);
$excluded=['meza_get_default_editable_acf_field_group_definitions','meza_get_base_default_editable_acf_field_group_definitions','meza_apply_shared_project_default_acf_field_group_definition_filters','meza_augment_shared_project_statistics_fields','meza_get_ecommerce_editable_acf_field_group_definitions','meza_get_ecommerce_default_editable_acf_field_group_definitions'];
foreach(array_unique($matches[1]) as $name) if(!in_array($name,$excluded,true) && !function_exists($name)) eval("function $name(){ return []; }");
$injected=[['key'=>'test_metrics','fields'=>[['key'=>'field_metrics','name'=>'metrics','type'=>'repeater','min'=>2,'max'=>4,'sub_fields'=>[['key'=>'field_value','name'=>'value','type'=>'text']]],['key'=>'field_other','name'=>'items','type'=>'repeater','sub_fields'=>[['key'=>'field_other_value','name'=>'value','type'=>'text']]],['key'=>'field_statistics','name'=>'statistics','type'=>'repeater','sub_fields'=>[['key'=>'field_existing_icon','name'=>'stat_icon','type'=>'image','return_format'=>'id']]]]]];
require $argv[1]; meza_get_ecommerce_editable_acf_field_group_definitions(); meza_get_ecommerce_editable_acf_field_group_definitions(); $defaults=meza_get_default_editable_acf_field_group_definitions(); foreach($defaults as $group) if(($group['key']??'')==='test_metrics'){ $once=$group; $twice=meza_augment_shared_project_statistics_fields([$group])[0]; echo json_encode([$once,$twice,$filter_calls,array_column($defaults,'key')]); }
PHP;
    $pipeline_result = json_decode((string) shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($pipeline) . ' ' . escapeshellarg($root . '/' . $tree . '/modules/managed-definitions.php') . ' ' . escapeshellarg($base . 'section-field-groups.php')), true);
    [$once, $twice, $filter_calls, $default_keys] = $pipeline_result;
    expect_authoring($once === $twice, "$tree default filter statistics augmentation is idempotent");
    expect_authoring(is_array($once) && ($once['key'] ?? '') === 'test_metrics', "$tree executes the effective default-definition consumer filter");
    expect_authoring($filter_calls === 1, "$tree ecommerce getter never invokes the consumer filter and the complete default pipeline invokes it once");
    expect_authoring(str_contains((string) json_encode($once), '"name":"stat_icon"'), "$tree effective default pipeline adds the injected statistics icon");
    $by_key = []; foreach ((array) ($once['fields'] ?? []) as $field) if (is_array($field)) $by_key[$field['key'] ?? ''] = $field;
    expect_authoring(array_column($by_key['field_metrics']['sub_fields'] ?? [], 'name') === ['value', 'stat_icon'] && ($by_key['field_metrics']['min'] ?? null) === 2 && ($by_key['field_metrics']['max'] ?? null) === 4, "$tree preserves metric fields, order, and limits while adding one icon");
    expect_authoring(array_column($by_key['field_other']['sub_fields'] ?? [], 'name') === ['value'] && ($by_key['field_statistics']['sub_fields'][0]['key'] ?? '') === 'field_existing_icon', "$tree preserves unrelated repeaters and compatible existing icons");
    expect_authoring(count(array_keys($default_keys, 'group_meza_list_events_section', true)) === 1 && count(array_keys($default_keys, 'group_meza_list_past_events_section', true)) === 0, "$tree managed defaults contain List Events once and no List Past Events");

    $registry_script = <<<'PHP'
function add_filter(...$a){} function apply_filters($hook,$value){ return $value; } function sanitize_key($value){ return strtolower((string) $value); }
function meza_normalize_header_section_group($value){ return $value; } function meza_get_acf_location_rules_hidden_by_default(){ return []; } function meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies(){ return []; } function meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies_excluding_posts(){ return []; }
require $argv[2]; $source=file_get_contents($argv[1]); preg_match_all('/\b(meza_get_[a-zA-Z0-9_]+)\s*\(/',$source,$matches); foreach(array_unique($matches[1]) as $name) if($name !== 'meza_get_shared_project_acf_field_groups' && !function_exists($name)) eval("function $name(){ return []; }"); require $argv[1]; echo json_encode(array_column(meza_get_shared_project_acf_field_groups(),'key'));
PHP;
    $registry_keys = json_decode((string) shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($registry_script) . ' ' . escapeshellarg($base . 'registry.php') . ' ' . escapeshellarg($base . 'section-field-groups.php')), true);
    expect_authoring(count(array_keys($registry_keys, 'group_meza_list_events_section', true)) === 0 && count(array_keys($registry_keys, 'group_meza_list_past_events_section', true)) === 1, "$tree effective registry contains List Past Events once and no List Events");
}

echo "PASS meza-shared-owner-authoring-contract-test\n";
