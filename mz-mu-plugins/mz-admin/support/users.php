<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('meza_get_support_seed_user_env_key')) {
    function meza_get_support_seed_user_env_key(string $login, string $suffix): string
    {
        return 'MZ_SUPPORT_USER_' . strtoupper($login) . '_' . strtoupper($suffix);
    }
}

if (!function_exists('meza_get_support_user_runtime_password')) {
    function meza_get_support_user_runtime_password(string $login): string
    {
        $key = meza_get_support_seed_user_env_key($login, 'PASSWORD');

        if (defined($key)) {
            return trim((string) constant($key));
        }

        return trim((string) getenv($key));
    }
}

if (!function_exists('meza_get_support_seed_user_specs')) {
    function meza_get_support_seed_user_specs(): array
    {
        $site_manager_role = function_exists('meza_site_manager_role_key')
            ? meza_site_manager_role_key()
            : 'site_manager';

        return [
            'ameza' => [
                'login' => 'ameza',
                'email' => 'andrew@meza.design',
                'display_name' => 'Andrew',
                'first_name' => 'Andrew',
                'last_name' => 'Meza',
                'role' => 'administrator',
                'aliases' => ['andrewmeza', 'andrewmmeza', 'andrew'],
            ],
            'aimeza' => [
                'login' => 'aimeza',
                'email' => 'info@meza.design',
                'display_name' => 'Meza',
                'first_name' => 'Meza',
                'last_name' => 'AI',
                'role' => 'administrator',
                'aliases' => [],
            ],
            'smeza' => [
                'login' => 'smeza',
                'email' => 'shannon@meza.design',
                'display_name' => 'Shannon',
                'first_name' => 'Shannon',
                'last_name' => 'Meza',
                'role' => $site_manager_role,
                'aliases' => [],
            ],
        ];
    }
}

if (!function_exists('meza_get_support_seed_user_logins')) {
    function meza_get_support_seed_user_logins(): array
    {
        return array_keys(meza_get_support_seed_user_specs());
    }
}

if (!function_exists('meza_get_support_seed_administrator_logins')) {
    function meza_get_support_seed_administrator_logins(): array
    {
        $logins = [];

        foreach (meza_get_support_seed_user_specs() as $login => $spec) {
            if (($spec['role'] ?? '') === 'administrator') {
                $logins[] = strtolower((string) $login);
            }
        }

        return array_values(array_unique(array_filter($logins)));
    }
}

if (!function_exists('meza_get_support_seed_administrator_label')) {
    function meza_get_support_seed_administrator_label(): string
    {
        return 'the Meza support administrator accounts';
    }
}

if (!function_exists('meza_is_support_seed_user_login')) {
    function meza_is_support_seed_user_login(string $login): bool
    {
        $normalized = strtolower(sanitize_user(trim($login), true));

        return $normalized !== ''
            && in_array($normalized, meza_get_support_seed_user_logins(), true);
    }
}

if (!function_exists('meza_get_support_seed_user_spec')) {
    function meza_get_support_seed_user_spec(string $login): ?array
    {
        $normalized = strtolower(sanitize_user(trim($login), true));
        $specs = meza_get_support_seed_user_specs();

        return isset($specs[$normalized]) && is_array($specs[$normalized])
            ? $specs[$normalized]
            : null;
    }
}

if (!function_exists('meza_provision_support_seed_user')) {
    function meza_provision_support_seed_user(string $login)
    {
        $spec = meza_get_support_seed_user_spec($login);
        if ($spec === null) {
            return new WP_Error('meza_support_user_unknown', 'The requested support user is not configured.');
        }

        $canonical_login = (string) $spec['login'];
        if (!function_exists('get_user_by') || !function_exists('wp_insert_user')) {
            return new WP_Error('meza_support_user_runtime_unavailable', 'WordPress user provisioning is unavailable.');
        }

        if (get_user_by('login', $canonical_login) instanceof WP_User) {
            return new WP_Error('meza_support_user_exists', 'The requested support user already exists.');
        }

        $password = meza_get_support_user_runtime_password($canonical_login);
        if ($password === '') {
            return new WP_Error('meza_support_user_password_missing', 'A runtime-managed support-user password is required.');
        }

        return wp_insert_user([
            'user_login' => $canonical_login,
            'user_pass' => $password,
            'user_email' => (string) $spec['email'],
            'display_name' => (string) $spec['display_name'],
            'first_name' => (string) $spec['first_name'],
            'last_name' => (string) $spec['last_name'],
            'role' => (string) $spec['role'],
        ]);
    }
}

if (!function_exists('meza_rotate_support_seed_user_password')) {
    function meza_rotate_support_seed_user_password(string $login)
    {
        $spec = meza_get_support_seed_user_spec($login);
        if ($spec === null) {
            return new WP_Error('meza_support_user_unknown', 'The requested support user is not configured.');
        }

        $canonical_login = (string) $spec['login'];
        if (!function_exists('get_user_by') || !function_exists('wp_set_password')) {
            return new WP_Error('meza_support_user_runtime_unavailable', 'WordPress password rotation is unavailable.');
        }

        $user = get_user_by('login', $canonical_login);
        if (!$user instanceof WP_User) {
            return new WP_Error('meza_support_user_missing', 'The requested support user does not exist.');
        }

        $password = meza_get_support_user_runtime_password($canonical_login);
        if ($password === '') {
            return new WP_Error('meza_support_user_password_missing', 'A runtime-managed support-user password is required.');
        }

        wp_set_password($password, (int) $user->ID);

        return (int) $user->ID;
    }
}

if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI') && !class_exists('MZ_Support_User_CLI_Command')) {
    class MZ_Support_User_CLI_Command
    {
        public function provision(array $args, array $assoc_args): void
        {
            $result = meza_provision_support_seed_user((string) ($args[0] ?? ''));
            if (is_wp_error($result)) {
                WP_CLI::error($result->get_error_message());
            }

            WP_CLI::success('Support user provisioned.');
        }

        public function rotate_password(array $args, array $assoc_args): void
        {
            $result = meza_rotate_support_seed_user_password((string) ($args[0] ?? ''));
            if (is_wp_error($result)) {
                WP_CLI::error($result->get_error_message());
            }

            WP_CLI::success('Support user password rotated.');
        }
    }

    WP_CLI::add_command('mz support-user', 'MZ_Support_User_CLI_Command');
}
