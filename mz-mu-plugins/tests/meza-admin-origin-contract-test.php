<?php
declare(strict_types=1);

function fail_origin(string $message): void { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
function expect_origin(bool $value, string $message): void { if (!$value) fail_origin($message); }

$root = dirname(__DIR__, 2);
foreach (['mz-admin/list-tables/row-actions.php', 'mz-mu-plugins/mz-admin/list-tables/row-actions.php'] as $relative) {
    $script = <<<'PHP'
$filters=[]; $can_delete=false;
class WP_Post { public $post_type='post'; public $ID=1; } class WP_User {} class WP_Term {} class WP_Screen {} class WP_Post_Type {} class WP_Admin_Bar {}
function add_filter($hook,$callback,...$rest) { global $filters; $filters[$hook][]=$callback; } function add_action(...$args) {} function __($x) { return $x; }
function wp_strip_all_tags($x) { return strip_tags($x); } function esc_url($x) { return $x; } function esc_html($x) { return $x; }
function esc_html__($x) { return $x; } function esc_attr($x) { return $x; } function wp_get_current_user() { return null; }
function meza_can_delete_taxonomy_terms($x) { global $can_delete; return $can_delete; } function meza_get_public_facing_url($url) { return str_replace('cms.example.com', 'example.com', $url); }
function meza_post_has_permalink($id) { return false; } function get_post_type_object($type) { return null; }
require $argv[1];
$actions=['edit'=>'<a href="https://cms.example.com/wp-admin/term.php?action=edit">Edit</a>','delete'=>'<a href="https://cms.example.com/wp-admin/term.php?action=delete">Delete</a>','trash'=>'<a href="https://cms.example.com/wp-admin/term.php?action=trash">Trash</a>','untrash'=>'<a href="https://cms.example.com/wp-admin/term.php?action=untrash">Untrash</a>','view'=>'<a href="https://cms.example.com/service-categories/races/">View</a>','qa'=>'<a href="https://cms.example.com/qa/races/">QA</a>'];
$term=$filters['term_row_actions'][0]; $unauthorized=$term($actions,new WP_Term()); $can_delete=true; $authorized=$term($actions,new WP_Term());
$post=$filters['post_row_actions'][0]; $post_actions=$post(['edit'=>$actions['edit'],'view'=>$actions['view']],new WP_Post());
echo json_encode([$unauthorized,$authorized,$post_actions,meza_get_public_admin_url('http://local.test:8080/a?b=1#c'),[meza_get_public_admin_url('https://cms.example.com/preview/race/'),meza_get_public_admin_url('https://cms.example.com/copy/race/'),meza_get_public_admin_url('https://cms.example.com/sample/race/'),meza_get_public_admin_url('https://cms.example.com/qa/race/')]]);
PHP;
    $result = json_decode((string) shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' ' . escapeshellarg($root . '/' . $relative)), true);
    [$unauthorized, $authorized, $post_actions, $local, $public_urls] = $result;
    expect_origin(!isset($unauthorized['delete']) && isset($authorized['delete']), basename($relative) . ' removes Delete only for unauthorized taxonomy users');
    foreach ([$unauthorized, $authorized] as $actions) {
        foreach (['edit', 'delete', 'trash', 'untrash'] as $key) if (isset($actions[$key])) expect_origin(str_contains($actions[$key], 'cms.example.com/wp-admin/'), basename($relative) . " keeps {$key} on CMS origin");
        expect_origin(str_contains($actions['view'], 'example.com/service-categories/races/') && str_contains($actions['qa'], 'example.com/qa/races/'), basename($relative) . ' converts public taxonomy View/QA actions');
    }
    expect_origin(str_contains($post_actions['edit'], 'cms.example.com/wp-admin/') && str_contains($post_actions['view'], 'example.com/service-categories/races/'), basename($relative) . ' executes post action normalization');
    expect_origin($local === 'http://local.test:8080/a?b=1#c', basename($relative) . ' preserves non-CMS URL details');
    expect_origin($public_urls === ['https://example.com/preview/race/', 'https://example.com/copy/race/', 'https://example.com/sample/race/', 'https://example.com/qa/race/'], basename($relative) . ' converts Preview, Copy URL, sample, and QA public URLs');
}
echo "PASS meza-admin-origin-contract-test\n";
