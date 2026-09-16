<?php

define('ABSPATH', __DIR__ . '/');
define('MZ_SUPPORT_USER_SMEZA_PASSWORD', 'unit-test-smeza-password');
define('MZ_SUPPORT_USER_AIMEZA_PASSWORD', 'unit-test-aimeza-password');

$mz_support_user_test_actions = [];
$mz_support_user_test_users = [];
$mz_support_user_test_password_sets = [];

function mz_support_user_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
    global $mz_support_user_test_actions;
    $mz_support_user_test_actions[$hook][] = $callback;
}

function do_action(string $hook): void
{
    global $mz_support_user_test_actions;

    foreach ($mz_support_user_test_actions[$hook] ?? [] as $callback) {
        $callback();
    }
}

function sanitize_user(string $value, bool $strict = false): string
{
    return strtolower(trim($value));
}

class WP_Error
{
    private string $code;
    private string $message;

    public function __construct(string $code, string $message)
    {
        $this->code = $code;
        $this->message = $message;
    }

    public function get_error_code(): string
    {
        return $this->code;
    }

    public function get_error_message(): string
    {
        return $this->message;
    }
}

function is_wp_error($value): bool
{
    return $value instanceof WP_Error;
}

class WP_User
{
    public int $ID;
    public string $user_login;
    public string $user_pass;

    public function __construct(int $id, string $login, string $password_hash)
    {
        $this->ID = $id;
        $this->user_login = $login;
        $this->user_pass = $password_hash;
    }
}

function meza_site_manager_role_key(): string
{
    return 'site_manager';
}

function get_user_by(string $field, $value)
{
    global $mz_support_user_test_users;

    foreach ($mz_support_user_test_users as $user) {
        if ($field === 'login' && $user->user_login === (string) $value) {
            return $user;
        }

        if ($field === 'id' && $user->ID === (int) $value) {
            return $user;
        }
    }

    return false;
}

function wp_insert_user(array $args)
{
    global $mz_support_user_test_users;

    $id = count($mz_support_user_test_users) + 1;
    $user = new WP_User($id, (string) $args['user_login'], 'created:' . (string) $args['user_pass']);
    $mz_support_user_test_users[$id] = $user;

    return $id;
}

function wp_set_password(string $password, int $user_id): void
{
    global $mz_support_user_test_password_sets;

    $user = get_user_by('id', $user_id);
    if (!$user instanceof WP_User) {
        return;
    }

    $user->user_pass = 'rotated:' . $password;
    $mz_support_user_test_password_sets[] = $user_id;
}

require_once __DIR__ . '/../mz-admin/support/users.php';

$mz_support_user_test_users = [
    1 => new WP_User(1, 'smeza', 'author-reset-password-hash'),
];

$specs = meza_get_support_seed_user_specs();
mz_support_user_test_assert(!isset($specs['smeza']['password_hash']), 'Support-user specs must not expose a committed fallback password hash.');
mz_support_user_test_assert(empty($mz_support_user_test_actions['init']), 'Support users must not be synchronized on init.');
mz_support_user_test_assert(empty($mz_support_user_test_actions['login_init']), 'Support users must not be synchronized on login_init.');

$reset_hash = $mz_support_user_test_users[1]->user_pass;
do_action('init');
do_action('login_init');
$existing_result = meza_provision_support_seed_user('smeza');
mz_support_user_test_assert(is_wp_error($existing_result), 'Provisioning must refuse an existing support user.');
$mz_support_user_test_assert_same = static function ($expected, $actual, string $message): void {
    mz_support_user_test_assert($expected === $actual, $message);
};
$mz_support_user_test_assert_same($reset_hash, $mz_support_user_test_users[1]->user_pass, 'Existing support-user passwords must survive request hooks and provisioning attempts.');
mz_support_user_test_assert($mz_support_user_test_password_sets === [], 'No password rotation may occur without an explicit command operation.');

$provisioned_id = meza_provision_support_seed_user('aimeza');
$mz_support_user_test_assert_same(2, $provisioned_id, 'Explicit provisioning should create a missing support user.');
$mz_support_user_test_assert_same('created:unit-test-aimeza-password', $mz_support_user_test_users[2]->user_pass, 'Provisioning must use only a runtime-managed password.');

$rotated_id = meza_rotate_support_seed_user_password('smeza');
$mz_support_user_test_assert_same(1, $rotated_id, 'Explicit rotation should target the requested existing support user.');
$mz_support_user_test_assert_same('rotated:unit-test-smeza-password', $mz_support_user_test_users[1]->user_pass, 'Explicit rotation must use only the runtime-managed password.');

echo "MZ Admin support-user tests passed\n";
