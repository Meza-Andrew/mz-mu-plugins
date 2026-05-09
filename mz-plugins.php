<?php

/**
 * Plugin Name: MZ Plugins
 * Description: Environment-based plugin installation, activation, and visibility rules.
 * Version: 1.4.61
 * Author: Meza LLC
 * Author URI: https://meza.design
 *
 * Requirements:
 *   define('WP_ENV', 'development'); // or 'staging' | 'qa' | 'production'
 *
 * Optional toggles in wp-config.php:
 *   define('MZ_PRUNE_PLUGINS', true);         // uninstall plugins not meant for this environment
 *   define('MZ_HIDE_NON_ENV_PLUGINS', true);  // hide any non-env plugins from the Plugins screen
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) return;

$env = defined('WP_ENV') ? strtolower(WP_ENV) : 'production';

if (!function_exists('mz_plugins_is_api_transport_request')) {
    function mz_plugins_is_api_transport_request(): bool
    {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return true;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? strtolower((string) $_SERVER['REQUEST_URI']) : '';
        if ($request_uri !== '') {
            if (strpos($request_uri, '/wp-json/') !== false) return true;
            if (strpos($request_uri, 'rest_route=') !== false) return true;
            if (strpos($request_uri, '/wp-admin/admin-ajax.php') !== false) return true;
            if (strpos($request_uri, '/wp-admin/admin-post.php') !== false) return true;
        }

        $accept = isset($_SERVER['HTTP_ACCEPT']) ? strtolower((string) $_SERVER['HTTP_ACCEPT']) : '';
        if (strpos($accept, 'application/json') !== false) {
            return true;
        }

        return false;
    }
}

// Keep QA/staging API-style responses loggable but never allow PHP notices/warnings
// to bleed into JSON payloads and break wp-admin screens.
if ($env !== 'development' && mz_plugins_is_api_transport_request()) {
    @ini_set('display_errors', '0');
    @ini_set('display_startup_errors', '0');
    @ini_set('html_errors', '0');
}

if (file_exists(__DIR__ . '/mz-plugin-compat.php')) {
    require_once __DIR__ . '/mz-plugin-compat.php';
}

$network_wide = is_multisite();
$PRUNE        = defined('MZ_PRUNE_PLUGINS') ? (bool) MZ_PRUNE_PLUGINS : false;
$HIDE         = defined('MZ_HIDE_NON_ENV_PLUGINS') ? (bool) MZ_HIDE_NON_ENV_PLUGINS : false;

const MZ_PRUNE_EXCLUDED_PLUGINS = [
    'woocommerce/woocommerce.php',
];

const MZ_PLUGINS_MANUAL_OVERRIDE_OPTION = 'mz_plugins_manual_overrides';

/** Convert common config-style values like 1/true/yes/on into a real boolean. */
function mz_plugins_truthy($value): bool
{
    if (is_array($value)) return $value !== [];
    if (is_bool($value)) return $value;
    if (is_numeric($value)) return ((int)$value) !== 0;
    $s = strtolower(trim((string)$value));
    return in_array($s, ['1', 'true', 'yes', 'on'], true);
}

/** Decide whether the plugin bootstrap should ignore its "already ran" flag and execute again. */
function mz_plugins_should_rerun(): bool
{
    if (defined('MZ_FORCE_RERUN')) return mz_plugins_truthy(MZ_FORCE_RERUN);
    return mz_plugins_truthy(get_option('mz_force_rerun', 0));
}

/** Production should honor explicit plugin deactivate/delete choices made in wp-admin. */
function mz_plugins_support_manual_overrides(?string $env = null): bool
{
    $env = strtolower((string) ($env ?: (defined('WP_ENV') ? WP_ENV : 'production')));

    return $env === 'production';
}

function mz_plugins_normalize_plugin_file(string $plugin_file): string
{
    $plugin_file = plugin_basename(trim($plugin_file));

    return $plugin_file === '.' ? '' : $plugin_file;
}

function mz_plugins_should_manage_woocommerce(): bool
{
    if (function_exists('meza_get_saved_business_information_ecommerce_settings')) {
        $settings = meza_get_saved_business_information_ecommerce_settings();

        return !empty($settings['woocommerce']);
    }

    $legacy_value = get_option('options_ecommerce', []);
    $woocommerce = get_option('options_woocommerce', null);
    $product_indexing = get_option('options_product_indexing', null);
    $store = get_option('options_store', null);

    if ($woocommerce !== null || $product_indexing !== null || $store !== null) {
        return mz_plugins_truthy($woocommerce) || mz_plugins_truthy($product_indexing) || mz_plugins_truthy($store);
    }

    return mz_plugins_truthy($legacy_value);
}

function mz_plugins_is_locked_plugin(string $plugin_file): bool
{
    $plugin_file = mz_plugins_normalize_plugin_file($plugin_file);

    return $plugin_file === 'woocommerce/woocommerce.php' && mz_plugins_should_manage_woocommerce();
}

function mz_plugins_is_plugin_active_in_scope(string $plugin_file): bool
{
    $plugin_file = mz_plugins_normalize_plugin_file($plugin_file);
    if ($plugin_file === '') {
        return false;
    }

    if (in_array($plugin_file, (array) get_option('active_plugins', []), true)) {
        return true;
    }

    if (is_multisite()) {
        $network_active_plugins = (array) get_site_option('active_sitewide_plugins', []);
        return isset($network_active_plugins[$plugin_file]);
    }

    return false;
}

function mz_plugins_can_manage_catalog_runtime(): bool
{
    return current_user_can('install_plugins')
        || current_user_can('activate_plugins')
        || current_user_can('manage_options');
}

add_action('admin_init', function (): void {
    if (!is_admin()) {
        return;
    }

    $requested_actions = [];

    foreach (['action', 'action2'] as $action_key) {
        if (!isset($_REQUEST[$action_key])) {
            continue;
        }

        $action = sanitize_key((string) wp_unslash($_REQUEST[$action_key]));
        if ($action !== '' && $action !== '-1') {
            $requested_actions[$action] = true;
        }
    }

    if (!array_intersect(array_keys($requested_actions), ['deactivate', 'deactivate-selected', 'delete-selected', 'delete-plugin'])) {
        return;
    }

    if (isset($_REQUEST['plugin'])) {
        $plugin_file = mz_plugins_normalize_plugin_file((string) wp_unslash($_REQUEST['plugin']));

        if (mz_plugins_is_locked_plugin($plugin_file)) {
            wp_safe_redirect(admin_url('plugins.php'));
            exit;
        }
    }

    if (!isset($_REQUEST['checked']) || !is_array($_REQUEST['checked'])) {
        return;
    }

    $checked_plugins = array_values(array_filter(array_map(
        static function ($plugin_file): string {
            return mz_plugins_normalize_plugin_file((string) $plugin_file);
        },
        wp_unslash($_REQUEST['checked'])
    )));

    $filtered_plugins = array_values(array_filter($checked_plugins, static function (string $plugin_file): bool {
        return !mz_plugins_is_locked_plugin($plugin_file);
    }));

    if ($filtered_plugins === $checked_plugins) {
        return;
    }

    if ($filtered_plugins === []) {
        wp_safe_redirect(admin_url('plugins.php'));
        exit;
    }

    $_REQUEST['checked'] = $filtered_plugins;

    if (isset($_POST['checked'])) {
        $_POST['checked'] = $filtered_plugins;
    }
}, 1);

function mz_plugins_get_manual_overrides(): array
{
    $stored = get_option(MZ_PLUGINS_MANUAL_OVERRIDE_OPTION, []);
    $overrides = is_array($stored) ? $stored : [];
    $normalized = [
        'skip_install' => [],
        'skip_activate' => [],
    ];

    foreach (array_keys($normalized) as $key) {
        $values = isset($overrides[$key]) && is_array($overrides[$key]) ? $overrides[$key] : [];

        foreach ($values as $plugin_file) {
            $plugin_file = mz_plugins_normalize_plugin_file((string) $plugin_file);
            if ($plugin_file === '' || mz_plugins_is_locked_plugin($plugin_file)) {
                continue;
            }

            $normalized[$key][$plugin_file] = true;
        }
    }

    return $normalized;
}

function mz_plugins_update_manual_overrides(array $overrides): void
{
    $normalized = [
        'skip_install' => [],
        'skip_activate' => [],
    ];

    foreach (array_keys($normalized) as $key) {
        $values = isset($overrides[$key]) && is_array($overrides[$key]) ? array_keys($overrides[$key]) : [];
        $values = array_values(array_unique(array_filter(array_map('strval', $values))));
        sort($values, SORT_NATURAL | SORT_FLAG_CASE);
        $normalized[$key] = $values;
    }

    update_option(MZ_PLUGINS_MANUAL_OVERRIDE_OPTION, $normalized, false);
}

function mz_plugins_is_catalog_managed_plugin(string $plugin_file): bool
{
    static $managed_plugins = null;

    if ($managed_plugins === null) {
        $managed_plugins = [];

        foreach (mz_plugins_get_catalog() as $plugin) {
            $file = mz_plugins_normalize_plugin_file((string) ($plugin['file'] ?? ''));
            if ($file !== '') {
                $managed_plugins[$file] = true;
            }
        }
    }

    $plugin_file = mz_plugins_normalize_plugin_file($plugin_file);

    return $plugin_file !== '' && isset($managed_plugins[$plugin_file]);
}

function mz_plugins_set_manual_override(string $plugin_file, string $scope): void
{
    if (!in_array($scope, ['skip_install', 'skip_activate'], true)) {
        return;
    }

    $plugin_file = mz_plugins_normalize_plugin_file($plugin_file);
    if ($plugin_file === '' || !mz_plugins_is_catalog_managed_plugin($plugin_file)) {
        return;
    }

    $overrides = mz_plugins_get_manual_overrides();
    $overrides[$scope][$plugin_file] = true;

    if ($scope === 'skip_install') {
        $overrides['skip_activate'][$plugin_file] = true;
    }

    mz_plugins_update_manual_overrides($overrides);
}

function mz_plugins_clear_manual_override(string $plugin_file): void
{
    $plugin_file = mz_plugins_normalize_plugin_file($plugin_file);
    if ($plugin_file === '') {
        return;
    }

    $overrides = mz_plugins_get_manual_overrides();
    unset($overrides['skip_install'][$plugin_file], $overrides['skip_activate'][$plugin_file]);
    mz_plugins_update_manual_overrides($overrides);
}

function mz_plugins_get_pending_removal_option_name(): string
{
    return 'mz_plugins_pending_removals_v1';
}

function mz_plugins_get_pending_removals(): array
{
    $stored = get_option(mz_plugins_get_pending_removal_option_name(), []);
    if (!is_array($stored)) {
        return [];
    }

    $plugin_files = array_values(array_unique(array_filter(array_map(
        static function ($plugin_file): string {
            return mz_plugins_normalize_plugin_file((string) $plugin_file);
        },
        $stored
    ))));

    sort($plugin_files, SORT_NATURAL | SORT_FLAG_CASE);

    return $plugin_files;
}

function mz_plugins_update_pending_removals(array $plugin_files): void
{
    $normalized = array_values(array_unique(array_filter(array_map(
        static function ($plugin_file): string {
            return mz_plugins_normalize_plugin_file((string) $plugin_file);
        },
        $plugin_files
    ))));

    sort($normalized, SORT_NATURAL | SORT_FLAG_CASE);
    update_option(mz_plugins_get_pending_removal_option_name(), $normalized, false);
}

function mz_plugins_queue_plugin_package_removal(string $plugin_file): void
{
    $plugin_file = mz_plugins_normalize_plugin_file($plugin_file);
    if ($plugin_file === '') {
        return;
    }

    $pending = mz_plugins_get_pending_removals();
    if (in_array($plugin_file, $pending, true)) {
        return;
    }

    $pending[] = $plugin_file;
    mz_plugins_update_pending_removals($pending);
}

function mz_plugins_unqueue_plugin_package_removal(string $plugin_file): void
{
    $plugin_file = mz_plugins_normalize_plugin_file($plugin_file);
    if ($plugin_file === '') {
        return;
    }

    $pending = array_values(array_filter(
        mz_plugins_get_pending_removals(),
        static function (string $pending_plugin_file) use ($plugin_file): bool {
            return $pending_plugin_file !== $plugin_file;
        }
    ));

    mz_plugins_update_pending_removals($pending);
}

function mz_plugins_deactivate_plugin(string $plugin_file)
{
    $plugin_file = mz_plugins_normalize_plugin_file($plugin_file);
    if ($plugin_file === '') {
        return new WP_Error('mz_plugins_invalid_plugin', 'The plugin file is invalid.');
    }

    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    mz_plugins_clear_manual_override($plugin_file);

    if (!mz_plugins_is_plugin_active_in_scope($plugin_file)) {
        return true;
    }

    deactivate_plugins($plugin_file, true, is_multisite());

    return true;
}

function mz_plugins_get_default_settings_import_specs(): array
{
    return [
        'updraftplus/updraftplus.php' => [
            'name' => 'UpdraftPlus',
            'source' => __DIR__ . '/defaults/updraftplus-settings.json',
            'signature_option' => 'mz_plugins_default_settings_signature_updraftplus',
            'importer' => 'mz_plugins_import_updraftplus_settings_file',
            'is_configured' => 'mz_plugins_has_updraft_settings',
        ],
        'all-in-one-wp-security-and-firewall/wp-security.php' => [
            'name' => 'AIOS',
            'source' => __DIR__ . '/defaults/aiowps-settings.txt',
            'signature_option' => 'mz_plugins_default_settings_signature_aios',
            'importer' => 'mz_plugins_import_aios_settings_file',
            'is_configured' => 'mz_plugins_has_aios_settings',
        ],
    ];
}

function mz_plugins_has_updraft_settings(): bool
{
    return null !== get_option('updraft_interval', null)
        || null !== get_option('updraft_retain', null);
}

function mz_plugins_has_aios_settings(): bool
{
    $configs = get_option('aio_wp_security_configs', null);

    return is_array($configs) && [] !== $configs;
}

function mz_plugins_get_updraft_settings_export_path(): string
{
    return __DIR__ . '/defaults/updraftplus-settings.json';
}

function mz_plugins_get_updraft_installed_version(): string
{
    global $updraftplus;

    if (isset($updraftplus) && is_object($updraftplus) && isset($updraftplus->version) && is_string($updraftplus->version) && '' !== trim($updraftplus->version)) {
        return trim($updraftplus->version);
    }

    $plugin_file = WP_PLUGIN_DIR . '/updraftplus/updraftplus.php';
    if (!file_exists($plugin_file)) {
        return '';
    }

    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $plugin_data = get_plugin_data($plugin_file, false, false);
    $version = isset($plugin_data['Version']) ? trim((string) $plugin_data['Version']) : '';

    return $version;
}

function mz_plugins_get_updraft_option_value(string $option_name, $default = null)
{
    if (class_exists('UpdraftPlus_Options') && is_callable(['UpdraftPlus_Options', 'get_updraft_option'])) {
        return UpdraftPlus_Options::get_updraft_option($option_name, $default);
    }

    return get_option($option_name, $default);
}

function mz_plugins_read_updraft_settings_payload(string $settings_path)
{
    if (!file_exists($settings_path) || !is_readable($settings_path)) {
        return new WP_Error('mz_plugins_updraft_settings_missing', 'Settings file is missing or unreadable.');
    }

    $raw_settings = file_get_contents($settings_path);
    if (!is_string($raw_settings) || '' === trim($raw_settings)) {
        return new WP_Error('mz_plugins_updraft_settings_empty', 'Settings file is empty.');
    }

    $decoded = json_decode($raw_settings, true);
    if (!is_array($decoded) || !isset($decoded['data']) || !is_array($decoded['data'])) {
        return new WP_Error('mz_plugins_updraft_settings_invalid', 'Settings file is not a valid Updraft export.');
    }

    return $decoded;
}

function mz_plugins_build_updraft_settings_payload(array $existing_payload): array
{
    $data = isset($existing_payload['data']) && is_array($existing_payload['data']) ? $existing_payload['data'] : [];
    $missing_option = '__mz_plugins_updraft_option_missing__';

    foreach (array_keys($data) as $option_name) {
        if (!is_string($option_name) || '' === trim($option_name)) {
            continue;
        }

        $option_value = mz_plugins_get_updraft_option_value($option_name, $missing_option);
        if ($option_value !== $missing_option) {
            $data[$option_name] = $option_value;
        }
    }

    $format_version = isset($existing_payload['version']) && is_string($existing_payload['version']) && '' !== trim($existing_payload['version'])
        ? trim($existing_payload['version'])
        : '1.12.40';

    return [
        'version' => $format_version,
        'plugin_version' => mz_plugins_get_updraft_installed_version(),
        'epoch_date' => (int) round(microtime(true) * 1000),
        'local_date' => wp_date('n/j/Y, g:i:s A'),
        'network_site_url' => untrailingslashit(network_site_url()),
        'data' => $data,
    ];
}

function mz_plugins_refresh_updraft_settings_export(bool $force = false)
{
    $env_name = defined('WP_ENV') ? strtolower((string) WP_ENV) : 'production';
    if (!in_array($env_name, ['development', 'local', 'staging', 'qa'], true)) {
        return false;
    }

    $settings_path = mz_plugins_get_updraft_settings_export_path();
    $decoded = mz_plugins_read_updraft_settings_payload($settings_path);
    if (is_wp_error($decoded)) {
        return $decoded;
    }

    $installed_version = mz_plugins_get_updraft_installed_version();
    if ('' === $installed_version) {
        return new WP_Error('mz_plugins_updraft_version_missing', 'Installed UpdraftPlus version could not be determined.');
    }

    $stored_plugin_version = isset($decoded['plugin_version']) ? trim((string) $decoded['plugin_version']) : '';
    if (!$force && $stored_plugin_version === $installed_version) {
        return false;
    }

    if (!file_exists($settings_path) || !is_writable($settings_path)) {
        return new WP_Error('mz_plugins_updraft_settings_not_writable', 'Bundled Updraft settings file is not writable.');
    }

    $payload = mz_plugins_build_updraft_settings_payload($decoded);
    $encoded = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (!is_string($encoded) || '' === trim($encoded)) {
        return new WP_Error('mz_plugins_updraft_settings_encode_failed', 'Bundled Updraft settings could not be encoded.');
    }

    $written = file_put_contents($settings_path, $encoded . PHP_EOL);

    if (false === $written) {
        return new WP_Error('mz_plugins_updraft_settings_write_failed', 'Bundled Updraft settings could not be updated.');
    }

    return true;
}

function mz_plugins_import_updraftplus_settings_file(string $settings_path)
{
    if ($settings_path === mz_plugins_get_updraft_settings_export_path()) {
        $refresh_result = mz_plugins_refresh_updraft_settings_export();
        if (is_wp_error($refresh_result) && $refresh_result->get_error_code() === 'mz_plugins_updraft_settings_missing') {
            return $refresh_result;
        }
    }

    $decoded = mz_plugins_read_updraft_settings_payload($settings_path);
    if (is_wp_error($decoded)) {
        return $decoded;
    }

    global $updraftplus_admin;

    if (!is_a($updraftplus_admin, 'UpdraftPlus_Admin')) {
        $admin_file = WP_PLUGIN_DIR . '/updraftplus/admin.php';
        if (file_exists($admin_file)) {
            require_once $admin_file;
        }
    }

    if (!is_a($updraftplus_admin, 'UpdraftPlus_Admin')) {
        return new WP_Error('mz_plugins_updraft_admin_missing', 'Updraft admin importer is unavailable.');
    }

    if (!class_exists('UpdraftPlus_Options') || !UpdraftPlus_Options::user_can_manage()) {
        return new WP_Error('mz_plugins_updraft_permission_denied', 'Current user cannot manage Updraft settings.');
    }

    $updraftplus_version = isset($decoded['plugin_version']) ? trim((string) $decoded['plugin_version']) : '';
    if ('' === $updraftplus_version) {
        $updraftplus_version = mz_plugins_get_updraft_installed_version();
    }

    $payload = [
        'settings' => wp_json_encode($decoded['data']),
        'updraftplus_version' => $updraftplus_version,
    ];

    if (!is_string($payload['settings']) || '' === $payload['settings']) {
        return new WP_Error('mz_plugins_updraft_settings_encode_failed', 'Could not encode Updraft settings for import.');
    }

    $result = $updraftplus_admin->import_settings($payload, true);

    if (!is_array($result)) {
        return new WP_Error('mz_plugins_updraft_import_unexpected', 'Unexpected Updraft import response.');
    }

    if (isset($result['saved']) && !$result['saved']) {
        $message = isset($result['error_message']) && is_string($result['error_message']) && '' !== trim($result['error_message'])
            ? $result['error_message']
            : 'Updraft settings import failed.';

        return new WP_Error('mz_plugins_updraft_import_failed', $message);
    }

    return $result;
}

add_action('updraftplus_newly_installed', function () {
    mz_plugins_refresh_updraft_settings_export(true);
}, 20);

add_action('updraftplus_version_changed', function () {
    mz_plugins_refresh_updraft_settings_export(true);
}, 20);

function mz_plugins_import_aios_settings_file(string $settings_path)
{
    if (!file_exists($settings_path) || !is_readable($settings_path)) {
        return new WP_Error('mz_plugins_aios_settings_missing', 'Settings file is missing or unreadable.');
    }

    $raw_settings = file_get_contents($settings_path);
    if (!is_string($raw_settings) || '' === trim($raw_settings)) {
        return new WP_Error('mz_plugins_aios_settings_empty', 'Settings file is empty.');
    }

    if (!class_exists('AIOWPSecurity_Commands')) {
        $commands_file = WP_PLUGIN_DIR . '/all-in-one-wp-security-and-firewall/classes/wp-security-commands.php';
        if (file_exists($commands_file)) {
            require_once $commands_file;
        }
    }

    if (!class_exists('AIOWPSecurity_Commands')) {
        return new WP_Error('mz_plugins_aios_commands_missing', 'AIOS importer is unavailable.');
    }

    $commands = new AIOWPSecurity_Commands();
    $result = $commands->perform_restore_aiowps_settings([
        'aiowps_import_settings_file' => basename($settings_path),
        'aiowps_import_settings_file_contents' => $raw_settings,
    ]);

    if (!is_array($result)) {
        return new WP_Error('mz_plugins_aios_import_unexpected', 'Unexpected AIOS import response.');
    }

    if (($result['status'] ?? '') !== 'success') {
        $message = isset($result['message']) && is_string($result['message']) && '' !== trim($result['message'])
            ? $result['message']
            : 'AIOS settings import failed.';

        return new WP_Error('mz_plugins_aios_import_failed', $message);
    }

    return $result;
}

function mz_plugins_maybe_import_default_settings(array $should_activate, array $all, bool $network_wide, array &$results): void
{
    foreach (mz_plugins_get_default_settings_import_specs() as $plugin_file => $spec) {
        $signature_option = (string) ($spec['signature_option'] ?? '');
        $source = (string) ($spec['source'] ?? '');
        $name = (string) ($spec['name'] ?? $plugin_file);
        $importer = $spec['importer'] ?? null;
        $is_configured = $spec['is_configured'] ?? null;

        if ('' === $signature_option || '' === $source || !is_callable($importer)) {
            continue;
        }

        if (!isset($should_activate[$plugin_file])) {
            delete_option($signature_option);
            continue;
        }

        $is_active = isset($all[$plugin_file]) && (is_plugin_active($plugin_file) || ($network_wide && is_multisite() && is_plugin_active_for_network($plugin_file)));
        if (!$is_active) {
            delete_option($signature_option);
            continue;
        }

        if (!file_exists($source) || !is_readable($source)) {
            $results[] = "Settings import failed: {$name} - bundled settings file missing.";
            continue;
        }

        $raw_settings = file_get_contents($source);
        if (!is_string($raw_settings) || '' === trim($raw_settings)) {
            $results[] = "Settings import failed: {$name} - bundled settings file is empty.";
            continue;
        }

        $signature = md5($raw_settings);
        $has_settings = is_callable($is_configured) ? (bool) call_user_func($is_configured) : true;

        if ((string) get_option($signature_option, '') === $signature && $has_settings) {
            continue;
        }

        $import_result = call_user_func($importer, $source);
        if (is_wp_error($import_result)) {
            delete_option($signature_option);
            $results[] = "Settings import failed: {$name} - " . $import_result->get_error_message();
            continue;
        }

        update_option($signature_option, $signature, false);
        $results[] = "Imported settings: {$name}";
    }
}

if (!function_exists('mz_plugins_get_catalog')) {
    function mz_plugins_get_catalog(): array
    {
        $catalog = [
            // All
            ['name' => 'Advanced Custom Fields PRO', 'slug' => 'advanced-custom-fields-pro', 'file' => 'advanced-custom-fields-pro/acf.php', 'envs' => ['development', 'staging', 'qa', 'production'], 'zip' => 'https://downloads.meza.design/vendor/advanced-custom-fields-pro.zip'],
            ['name' => 'Classic Editor', 'slug' => 'classic-editor', 'file' => 'classic-editor/classic-editor.php', 'envs' => ['development', 'staging', 'qa', 'production']],
            ['name' => 'Intuitive Custom Post Order', 'slug' => 'intuitive-custom-post-order', 'file' => 'intuitive-custom-post-order/intuitive-custom-post-order.php', 'envs' => ['development', 'staging', 'qa', 'production']],
            ['name' => 'Post Duplicator', 'slug' => 'post-duplicator', 'file' => 'post-duplicator/m4c-postduplicator.php', 'envs' => ['development', 'staging', 'qa', 'production']],
            ['name' => 'UpdraftPlus', 'slug' => 'updraftplus', 'file' => 'updraftplus/updraftplus.php', 'envs' => ['development', 'staging', 'qa', 'production']],
            ['name' => 'WordPress Importer', 'slug' => 'wordpress-importer', 'file' => 'wordpress-importer/wordpress-importer.php', 'envs' => ['development', 'staging', 'qa', 'production']],
            ['name' => 'WP Mail SMTP', 'slug' => 'wp-mail-smtp', 'file' => 'wp-mail-smtp/wp_mail_smtp.php', 'envs' => ['development', 'staging', 'qa', 'production'], 'required_files' => ['wp-mail-smtp/wp-mail-smtp.php', 'wp-mail-smtp/src/Core.php']],
            ['name' => 'Error Log Monitor', 'slug' => 'error-log-monitor', 'file' => 'error-log-monitor/plugin.php', 'envs' => ['development', 'staging', 'qa', 'production']],

            // All
            ['name' => 'Yoast SEO', 'slug' => 'wordpress-seo', 'file' => 'wordpress-seo/wp-seo.php', 'envs' => ['development', 'staging', 'qa', 'production'], 'signatures' => ['wpseo', 'wpseo_page_settings']],
            ['name' => 'ACF Content Analysis for Yoast SEO', 'slug' => 'acf-content-analysis-for-yoast-seo', 'file' => 'acf-content-analysis-for-yoast-seo/yoast-acf-analysis.php', 'envs' => ['staging', 'qa', 'production'], 'requires_active' => ['advanced-custom-fields-pro/acf.php', 'wordpress-seo/wp-seo.php'], 'requires_wp' => '6.6', 'requires_php' => '7.2.5'],
            ['name' => 'Admin Columns', 'slug' => 'codepress-admin-columns', 'file' => 'codepress-admin-columns/codepress-admin-columns.php', 'envs' => ['development', 'staging', 'qa', 'production']],
            ['name' => 'Admin Menu Editor', 'slug' => 'admin-menu-editor', 'file' => 'admin-menu-editor/menu-editor.php', 'envs' => ['staging', 'qa', 'production']],

            // All except production
            ['name' => 'Query Monitor', 'slug' => 'query-monitor', 'file' => 'query-monitor/query-monitor.php', 'envs' => ['development', 'staging', 'qa']],

            // QA + Production only
            ['name' => 'All-In-One Security (AIOS)', 'slug' => 'all-in-one-wp-security-and-firewall', 'file' => 'all-in-one-wp-security-and-firewall/wp-security.php', 'envs' => ['qa', 'production']],
            ['name' => 'Converter for Media', 'slug' => 'webp-converter-for-media', 'file' => 'webp-converter-for-media/webp-converter-for-media.php', 'envs' => ['qa', 'production']],
            ['name' => 'Redirection', 'slug' => 'redirection', 'file' => 'redirection/redirection.php', 'envs' => ['qa', 'production'], 'requires_wp' => '6.5', 'requires_php' => '7.4'],
            ['name' => 'WP Super Cache', 'slug' => 'wp-super-cache', 'file' => 'wp-super-cache/wp-cache.php', 'envs' => ['qa', 'production']],
            ['name' => 'SQLite Object Cache', 'slug' => 'sqlite-object-cache', 'file' => 'sqlite-object-cache/sqlite-object-cache.php', 'envs' => ['qa', 'production'], 'requires' => ['wp-super-cache/wp-cache.php']],

            // Production only
            ['name' => 'Site Kit by Google', 'slug' => 'google-site-kit', 'file' => 'google-site-kit/google-site-kit.php', 'envs' => ['production']],
        ];

        if (mz_plugins_should_manage_woocommerce()) {
            $catalog[] = ['name' => 'WooCommerce', 'slug' => 'woocommerce', 'file' => 'woocommerce/woocommerce.php', 'envs' => ['development', 'staging', 'qa', 'production']];
        }

        return $catalog;
    }
}

add_action('deactivated_plugin', function ($plugin): void {
    if (!mz_plugins_support_manual_overrides() || mz_plugins_is_locked_plugin((string) $plugin)) {
        return;
    }

    mz_plugins_set_manual_override((string) $plugin, 'skip_activate');
}, 10, 2);

add_action('deleted_plugin', function ($plugin, $deleted): void {
    if (!$deleted || !mz_plugins_support_manual_overrides() || mz_plugins_is_locked_plugin((string) $plugin)) {
        return;
    }

    mz_plugins_set_manual_override((string) $plugin, 'skip_install');
}, 10, 2);

add_action('activated_plugin', function ($plugin): void {
    if (!mz_plugins_support_manual_overrides()) {
        return;
    }

    mz_plugins_clear_manual_override((string) $plugin);
}, 10, 2);

if (!function_exists('mz_plugins_get_env_catalog')) {
    function mz_plugins_get_env_catalog(?string $env = null): array
    {
        $env = strtolower((string) ($env ?: (defined('WP_ENV') ? WP_ENV : 'production')));

        return array_values(array_filter(mz_plugins_get_catalog(), static function (array $plugin) use ($env): bool {
            return in_array($env, (array) ($plugin['envs'] ?? []), true);
        }));
    }
}

if (!function_exists('mz_plugins_get_catalog_signatures')) {
    function mz_plugins_get_catalog_signatures(array $catalog): array
    {
        $signatures = [];

        foreach ($catalog as $plugin) {
            $file = strtolower(trim((string) ($plugin['file'] ?? '')));
            $slug = strtolower(trim((string) ($plugin['slug'] ?? '')));

            $tokens = array_filter([
                $slug,
                str_replace('-', '_', $slug),
                str_replace('-', '', $slug),
                $file,
                dirname($file) !== '.' ? dirname($file) : '',
            ], static function (string $token): bool {
                return $token !== '' && strlen($token) >= 4;
            });

            $custom_signatures = array_filter(array_map(static function ($signature): string {
                return strtolower(trim((string) $signature));
            }, (array) ($plugin['signatures'] ?? [])), static function (string $signature): bool {
                return $signature !== '' && strlen($signature) >= 4;
            });

            $tokens = array_merge($tokens, $custom_signatures);

            foreach ($tokens as $token) {
                $signatures[$token] = true;
            }
        }

        return array_keys($signatures);
    }
}

if (!function_exists('mz_plugins_get_catalog_plugin_signatures')) {
    function mz_plugins_get_catalog_plugin_signatures(): array
    {
        return mz_plugins_get_catalog_signatures(mz_plugins_get_catalog());
    }
}

if (!function_exists('mz_plugins_get_env_plugin_signatures')) {
    function mz_plugins_get_env_plugin_signatures(?string $env = null): array
    {
        return mz_plugins_get_catalog_signatures(mz_plugins_get_env_catalog($env));
    }
}

if (!function_exists('mz_plugins_catalog_dependencies_met')) {
    function mz_plugins_catalog_dependencies_met(array $plugin, array $should_install = [], array $all_plugins = []): bool
    {
        $requires = array_filter((array) ($plugin['requires'] ?? []), static function ($required_plugin): bool {
            return is_string($required_plugin) && trim($required_plugin) !== '';
        });

        foreach ($requires as $required_plugin) {
            $required_plugin = trim((string) $required_plugin);
            $required_plugin_path = WP_PLUGIN_DIR . '/' . $required_plugin;

            if (isset($should_install[$required_plugin])) {
                continue;
            }

            if (isset($all_plugins[$required_plugin]) || file_exists($required_plugin_path)) {
                continue;
            }

            return false;
        }

        return true;
    }
}

if (!function_exists('mz_plugins_catalog_activation_dependencies_met')) {
    function mz_plugins_catalog_activation_dependencies_met(array $plugin): bool
    {
        $required_plugins = array_filter((array) ($plugin['requires_active'] ?? []), static function ($required_plugin): bool {
            return is_string($required_plugin) && trim($required_plugin) !== '';
        });

        foreach ($required_plugins as $required_plugin) {
            $required_plugin = trim((string) $required_plugin);

            if (!is_plugin_active($required_plugin) && !(is_multisite() && is_plugin_active_for_network($required_plugin))) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('mz_plugins_catalog_runtime_requirements_met')) {
    function mz_plugins_catalog_runtime_requirements_met(array $plugin): bool
    {
        $requires_wp = trim((string) ($plugin['requires_wp'] ?? ''));
        if ($requires_wp !== '' && version_compare((string) get_bloginfo('version'), $requires_wp, '<')) {
            return false;
        }

        $requires_php = trim((string) ($plugin['requires_php'] ?? ''));
        if ($requires_php !== '' && version_compare(PHP_VERSION, $requires_php, '<')) {
            return false;
        }

        return true;
    }
}

if (!function_exists('mz_plugins_catalog_runtime_requirement_message')) {
    function mz_plugins_catalog_runtime_requirement_message(array $plugin): string
    {
        $parts = [];
        $requires_wp = trim((string) ($plugin['requires_wp'] ?? ''));
        $requires_php = trim((string) ($plugin['requires_php'] ?? ''));

        if ($requires_wp !== '') {
            $parts[] = 'WP ' . $requires_wp . '+';
        }

        if ($requires_php !== '') {
            $parts[] = 'PHP ' . $requires_php . '+';
        }

        return implode(', ', $parts);
    }
}

if (!function_exists('mz_plugins_catalog_required_files')) {
    function mz_plugins_catalog_required_files(array $plugin): array
    {
        $required_files = [];
        $main_file = trim((string) ($plugin['file'] ?? ''));

        if ($main_file !== '') {
            $required_files[] = ltrim($main_file, '/');
        }

        foreach ((array) ($plugin['required_files'] ?? []) as $required_file) {
            $required_file = ltrim(trim((string) $required_file), '/');

            if ($required_file !== '') {
                $required_files[] = $required_file;
            }
        }

        return array_values(array_unique($required_files));
    }
}

if (!function_exists('mz_plugins_catalog_missing_required_files')) {
    function mz_plugins_catalog_missing_required_files(array $plugin): array
    {
        $missing_files = [];

        foreach (mz_plugins_catalog_required_files($plugin) as $required_file) {
            $full_path = WP_PLUGIN_DIR . '/' . $required_file;

            if (!file_exists($full_path) || !is_readable($full_path)) {
                $missing_files[] = $required_file;
            }
        }

        return $missing_files;
    }
}

if (!function_exists('mz_plugins_format_error_message')) {
    function mz_plugins_format_error_message($error, string $fallback = ''): string
    {
        if (is_wp_error($error)) {
            $message = trim($error->get_error_message());
            if ($message !== '') {
                return $message;
            }
        }

        return $fallback;
    }
}

if (!function_exists('mz_plugins_get_catalog_plugin')) {
    function mz_plugins_get_catalog_plugin(string $plugin_file): ?array
    {
        $plugin_file = mz_plugins_normalize_plugin_file($plugin_file);
        if ($plugin_file === '') {
            return null;
        }

        foreach (mz_plugins_get_catalog() as $plugin) {
            if (mz_plugins_normalize_plugin_file((string) ($plugin['file'] ?? '')) === $plugin_file) {
                return $plugin;
            }
        }

        return null;
    }
}

if (!function_exists('mz_plugins_prepare_admin_plugin_runtime')) {
    function mz_plugins_prepare_admin_plugin_runtime(): void
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        if (function_exists('delete_site_transient')) {
            delete_site_transient('wp_plugin_dependencies_plugin_data');
            delete_site_transient('update_plugins');
        }

        add_filter('filesystem_method', function () {
            return 'direct';
        }, 99);

        if (!class_exists('WP_Ajax_Upgrader_Skin')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skins.php';
        }
    }
}

if (!function_exists('mz_plugins_ensure_catalog_plugin_active')) {
    function mz_plugins_ensure_catalog_plugin_active(string $plugin_file)
    {
        $plugin_file = mz_plugins_normalize_plugin_file($plugin_file);
        $plugin = mz_plugins_get_catalog_plugin($plugin_file);

        if (!is_array($plugin)) {
            return new WP_Error('mz_plugins_unknown_plugin', 'The requested plugin is not managed by the MZ plugin catalog.');
        }

        if (mz_plugins_is_plugin_active_in_scope($plugin_file) && mz_plugins_catalog_missing_required_files($plugin) === []) {
            return true;
        }

        if (!mz_plugins_can_manage_catalog_runtime()) {
            return new WP_Error('mz_plugins_insufficient_permissions', 'The current user cannot install or activate plugins.');
        }

        if (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS && mz_plugins_catalog_missing_required_files($plugin) !== []) {
            return new WP_Error('mz_plugins_file_mods_disabled', 'Plugin installation is disabled for this environment.');
        }

        if (!mz_plugins_catalog_runtime_requirements_met($plugin)) {
            return new WP_Error(
                'mz_plugins_runtime_requirements',
                'Plugin requirements not met: ' . mz_plugins_catalog_runtime_requirement_message($plugin)
            );
        }

        mz_plugins_prepare_admin_plugin_runtime();
        mz_plugins_unqueue_plugin_package_removal($plugin_file);
        mz_plugins_clear_manual_override($plugin_file);

        $all = get_plugins();
        $missing_required_files = mz_plugins_catalog_missing_required_files($plugin);

        if (!isset($all[$plugin_file]) || $missing_required_files !== []) {
            $api_error = null;
            $private_package_source = mz_plugins_resolve_private_package_source($plugin, $api_error);
            $download = $private_package_source ?: (function ($slug, &$api_error = null) {
                $api = plugins_api('plugin_information', ['slug' => $slug, 'fields' => ['sections' => false]]);
                if (is_wp_error($api)) {
                    $api_error = $api;
                    return '';
                }

                return !empty($api->download_link) ? (string) $api->download_link : '';
            })((string) ($plugin['slug'] ?? ''), $api_error);

            if ($download === '') {
                return $api_error instanceof WP_Error
                    ? $api_error
                    : new WP_Error('mz_plugins_download_unavailable', 'The plugin download URL could not be resolved.');
            }

            $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
            $installed = $upgrader->install($download);

            if (is_wp_error($installed) || !$installed) {
                return is_wp_error($installed)
                    ? $installed
                    : new WP_Error('mz_plugins_install_failed', 'The plugin could not be installed.');
            }

            wp_clean_plugins_cache(true);
            $all = get_plugins();

            if (!isset($all[$plugin_file]) || mz_plugins_catalog_missing_required_files($plugin) !== []) {
                return new WP_Error('mz_plugins_install_incomplete', 'The plugin package was installed, but required files are still missing.');
            }
        }

        if (!mz_plugins_catalog_activation_dependencies_met($plugin)) {
            return new WP_Error('mz_plugins_activation_dependencies', 'Plugin activation dependencies are not currently met.');
        }

        if (!mz_plugins_is_plugin_active_in_scope($plugin_file)) {
            $network_wide = is_multisite();
            $activation = activate_plugin($plugin_file, '', $network_wide, true);

            if (is_wp_error($activation)) {
                return $activation;
            }
        }

        return true;
    }
}

if (!function_exists('mz_plugins_remove_plugin_package')) {
    function mz_plugins_remove_plugin_package(string $plugin_file)
    {
        $plugin_file = mz_plugins_normalize_plugin_file($plugin_file);
        if ($plugin_file === '') {
            return new WP_Error('mz_plugins_invalid_plugin', 'The plugin file is invalid.');
        }

        if (!mz_plugins_can_manage_catalog_runtime()) {
            return new WP_Error('mz_plugins_insufficient_permissions', 'The current user cannot remove plugins.');
        }

        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $plugin_directory = dirname($plugin_file);
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
        $plugin_directory_path = WP_PLUGIN_DIR . '/' . $plugin_directory;

        if (!file_exists($plugin_path) && !is_dir($plugin_directory_path)) {
            return true;
        }

        if (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) {
            return new WP_Error('mz_plugins_file_mods_disabled', 'Plugin removal is disabled for this environment.');
        }

        mz_plugins_prepare_admin_plugin_runtime();
        mz_plugins_clear_manual_override($plugin_file);

        if (mz_plugins_is_plugin_active_in_scope($plugin_file)) {
            deactivate_plugins($plugin_file, true, is_multisite());
        }

        $deleted = delete_plugins([$plugin_file]);

        if (is_wp_error($deleted)) {
            return $deleted;
        }

        if ($deleted === false && (file_exists($plugin_path) || is_dir($plugin_directory_path))) {
            return new WP_Error('mz_plugins_delete_failed', 'The plugin package could not be deleted.');
        }

        wp_clean_plugins_cache(true);

        return true;
    }
}

if (!function_exists('mz_plugins_catalog_has_locked_plugin_state_drift')) {
    function mz_plugins_catalog_has_locked_plugin_state_drift(array $should_install, array $should_activate): bool
    {
        foreach ($should_install as $plugin_file => $plugin) {
            if (!mz_plugins_is_locked_plugin((string) $plugin_file)) {
                continue;
            }

            if (mz_plugins_catalog_missing_required_files((array) $plugin) !== []) {
                return true;
            }
        }

        foreach ($should_activate as $plugin_file => $plugin) {
            if (!mz_plugins_is_locked_plugin((string) $plugin_file)) {
                continue;
            }

            if (!mz_plugins_is_plugin_active_in_scope((string) $plugin_file)) {
                return true;
            }
        }

        return false;
    }
}

add_action('admin_init', function (): void {
    if (!is_admin() || !mz_plugins_can_manage_catalog_runtime()) {
        return;
    }

    foreach (mz_plugins_get_pending_removals() as $plugin_file) {
        if (mz_plugins_is_plugin_active_in_scope($plugin_file)) {
            continue;
        }

        $result = mz_plugins_remove_plugin_package($plugin_file);
        if (!is_wp_error($result)) {
            mz_plugins_unqueue_plugin_package_removal($plugin_file);
        }
    }
}, 5);

if (!function_exists('mz_plugins_get_private_package_candidates')) {
    function mz_plugins_get_private_package_candidates(array $plugin): array
    {
        $candidates = [];

        $zip = trim((string) ($plugin['zip'] ?? ''));
        if ($zip !== '') {
            $candidates[] = $zip;
        }

        $zip_const = trim((string) ($plugin['zip_const'] ?? ''));
        if ($zip_const !== '') {
            if (defined($zip_const)) {
                $constant_value = trim((string) constant($zip_const));
                if ($constant_value !== '') {
                    $candidates[] = $constant_value;
                }
            }

            $env_value = getenv($zip_const);
            if (is_string($env_value)) {
                $env_value = trim($env_value);
                if ($env_value !== '') {
                    $candidates[] = $env_value;
                }
            }
        }

        return array_values(array_unique($candidates));
    }
}

if (!function_exists('mz_plugins_resolve_private_package_source')) {
    function mz_plugins_resolve_private_package_source(array $plugin, ?WP_Error &$error = null): string
    {
        $candidates = mz_plugins_get_private_package_candidates($plugin);

        if ($candidates === []) {
            $error = new WP_Error(
                'mz_plugins_private_package_missing',
                sprintf(
                    'No private package source configured for %s.',
                    (string) ($plugin['name'] ?? 'this plugin')
                )
            );
            return '';
        }

        $checked_paths = [];

        foreach ($candidates as $candidate) {
            if (preg_match('#^https?://#i', $candidate) === 1) {
                return $candidate;
            }

            $path_candidates = [$candidate];

            if (!str_starts_with($candidate, '/')) {
                $path_candidates[] = ABSPATH . ltrim($candidate, '/');
                $path_candidates[] = WP_CONTENT_DIR . '/' . ltrim($candidate, '/');
            }

            foreach ($path_candidates as $path_candidate) {
                $normalized_path = trim((string) $path_candidate);
                if ($normalized_path === '') {
                    continue;
                }

                $checked_paths[] = $normalized_path;

                if (!file_exists($normalized_path) || !is_readable($normalized_path)) {
                    continue;
                }

                return $normalized_path;
            }
        }

        $zip_const = trim((string) ($plugin['zip_const'] ?? ''));
        $error_message = sprintf(
            'Private package source not found or not readable for %s.',
            (string) ($plugin['name'] ?? 'this plugin')
        );

        if ($zip_const !== '') {
            $error_message .= ' Configure ' . $zip_const . ' as a readable file path or HTTPS URL on this environment.';
        }

        if ($checked_paths !== []) {
            $error_message .= ' Checked: ' . implode(', ', array_unique($checked_paths));
        }

        $error = new WP_Error('mz_plugins_private_package_unreadable', $error_message);

        return '';
    }
}

// Catalog: 'envs' = envs where it should be ACTIVE; 'activate' false => keep installed but deactivated.
$catalog = mz_plugins_get_catalog();

add_action('admin_init', function () use ($catalog, $env, $network_wide, $PRUNE, $HIDE) {
    if (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) return;
    if (!mz_plugins_can_manage_catalog_runtime()) return;

    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

    // Nuke the dependency transient if present (harmless if missing)
    if (function_exists('delete_site_transient')) {
        delete_site_transient('wp_plugin_dependencies_plugin_data');
    }
    // Also fine to clear the standard plugin update transient:
    if (function_exists('delete_site_transient')) {
        delete_site_transient('update_plugins');
    }

    // Desired state
    $manual_overrides = mz_plugins_support_manual_overrides($env)
        ? mz_plugins_get_manual_overrides()
        : ['skip_install' => [], 'skip_activate' => []];
    $should_install  = []; // plugins that must exist on disk for this env (active OR install-only)
    $should_activate = []; // plugins that must be active in this env
    $managed         = []; // all plugins that are managed by this catalog (across envs)
    foreach ($catalog as $p) {
        $file     = $p['file'];
        $envs     = (array)($p['envs'] ?? []);
        $activate = array_key_exists('activate', $p) ? (bool)$p['activate'] : true;

        // Track everything in the catalog as "managed"
        $managed[$file] = $p;

        // Only “see/have” what’s for THIS env (plus any install-only for this env)
        if (in_array($env, $envs, true)) {
            if (isset($manual_overrides['skip_install'][$file])) {
                continue;
            }

            $should_install[$file] = $p;

            if ($activate && !isset($manual_overrides['skip_activate'][$file])) {
                $should_activate[$file] = $p;
            }
        }
    }

    // Fingerprint per env so switching envs/catalog state re-runs.
    $fingerprint = md5(
        $env . '|' .
            wp_json_encode($should_install) . '|' .
            wp_json_encode($should_activate) . '|' .
            wp_json_encode($manual_overrides) . '|' .
            (int)$network_wide . '|' .
            (int)$PRUNE
    );

    $has_run = (int)get_option('mz_plugins_initialized', 0) === 1;
    $last_fp = (string)get_option('mz_bootstrap_fingerprint_envonly', '');
    if (
        $has_run
        && $last_fp === $fingerprint
        && !mz_plugins_should_rerun()
        && !mz_plugins_catalog_has_locked_plugin_state_drift($should_install, $should_activate)
    ) {
        return;
    }

    add_filter('filesystem_method', function () {
        return 'direct';
    }, 99);
    if (!class_exists('WP_Ajax_Upgrader_Skin')) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skins.php';
    }
    $skin     = new WP_Ajax_Upgrader_Skin();
    $upgrader = new Plugin_Upgrader($skin);

    $results = [];
    $all     = get_plugins();

    // INSTALL any missing that we want to have in this env
    foreach ($should_install as $file => $p) {
        if (!mz_plugins_catalog_dependencies_met($p, $should_install, $all)) {
            $results[] = "Skip install (requires): {$p['name']}";
            continue;
        }

        if (!mz_plugins_catalog_runtime_requirements_met($p)) {
            $results[] = "Skip install (runtime): {$p['name']} requires " . mz_plugins_catalog_runtime_requirement_message($p);
            continue;
        }

        $missing_required_files = mz_plugins_catalog_missing_required_files($p);

        if (isset($all[$file]) && $missing_required_files === []) continue;
        $slug = $p['slug'] ?? '';
        $api_error = null;
        $private_package_source = mz_plugins_resolve_private_package_source($p, $api_error);

        if (isset($all[$file]) && $missing_required_files !== []) {
            $results[] = "Repair install: {$p['name']} - missing " . implode(', ', $missing_required_files);
        }

        $download = $private_package_source ?: (function ($slug, &$api_error = null) {
            $api = plugins_api('plugin_information', ['slug' => $slug, 'fields' => ['sections' => false]]);
            if (is_wp_error($api)) {
                $api_error = $api;
                return '';
            }
            if (empty($api->download_link)) return '';
            return $api->download_link;
        })($slug, $api_error);

        if (!$download) {
            $results[] = "Install failed (resolve): {$p['name']}" . ($api_error instanceof WP_Error ? ' - ' . mz_plugins_format_error_message($api_error) : '');
            continue;
        }
        $ok = $upgrader->install($download);
        if (is_wp_error($ok) || !$ok) {
            $results[] = "Install failed: {$p['name']}" . ($ok instanceof WP_Error ? ' - ' . mz_plugins_format_error_message($ok) : '');
            continue;
        }
        wp_clean_plugins_cache(true);
        $all = get_plugins();
        $missing_required_files = mz_plugins_catalog_missing_required_files($p);

        if (isset($all[$file]) && $missing_required_files === []) {
            $results[] = "Installed: {$p['name']}";
            continue;
        }

        $results[] = "Install failed (package): {$p['name']}" . ($missing_required_files !== [] ? ' - missing ' . implode(', ', $missing_required_files) : '');
    }

    // ACTIVATE required
    $pending_activation = $should_activate;
    $activation_progress = true;

    while (!empty($pending_activation) && $activation_progress) {
        $activation_progress = false;

        foreach ($pending_activation as $file => $p) {
            if (!mz_plugins_catalog_dependencies_met($p, $should_install, $all)) {
                unset($pending_activation[$file]);
                $results[] = "Skip activate (requires): {$p['name']}";
                continue;
            }

            if (!mz_plugins_catalog_runtime_requirements_met($p)) {
                unset($pending_activation[$file]);
                $results[] = "Skip activate (runtime): {$p['name']} requires " . mz_plugins_catalog_runtime_requirement_message($p);
                continue;
            }

            if (is_plugin_active($file) || (is_multisite() && is_plugin_active_for_network($file))) {
                unset($pending_activation[$file]);
                continue;
            }

            if (!isset($all[$file])) {
                wp_clean_plugins_cache(true);
                $all = get_plugins();
            }
            if (!isset($all[$file])) {
                unset($pending_activation[$file]);
                $results[] = "Skip activate (not found): {$p['name']}";
                continue;
            }

            $missing_required_files = mz_plugins_catalog_missing_required_files($p);
            if ($missing_required_files !== []) {
                unset($pending_activation[$file]);
                $results[] = "Skip activate (package): {$p['name']} missing " . implode(', ', $missing_required_files);
                continue;
            }

            if (!mz_plugins_catalog_activation_dependencies_met($p)) {
                continue;
            }

            $err = activate_plugin($file, '', $network_wide, true);
            unset($pending_activation[$file]);
            $activation_progress = true;

            if (is_wp_error($err)) {
                $results[] = "Activation failed: {$p['name']} - " . mz_plugins_format_error_message($err, 'Unknown activation error');
                continue;
            }

            $results[] = "Activated: {$p['name']}";
        }
    }

    foreach ($pending_activation as $file => $p) {
        $required_plugins = array_filter((array) ($p['requires_active'] ?? []), static function ($required_plugin): bool {
            return is_string($required_plugin) && trim($required_plugin) !== '';
        });

        if ($required_plugins !== []) {
            $results[] = "Skip activate (requires active): {$p['name']}";
            continue;
        }

        $results[] = "Skip activate (pending): {$p['name']}";
    }

    mz_plugins_maybe_import_default_settings($should_activate, $all, $network_wide, $results);

    // DEACTIVATE catalog-managed plugins that should NOT be active in this env
    foreach (array_keys($all) as $file) {
        // Only touch plugins that are in our catalog
        if (!isset($managed[$file])) {
            continue;
        }

        // If this plugin should be active in this env, skip it
        if (isset($should_activate[$file])) {
            continue;
        }

        // Otherwise, for catalog-managed plugins that should NOT be active here, deactivate them
        if (is_plugin_active($file) || (is_multisite() && is_plugin_active_for_network($file))) {
            deactivate_plugins($file, true, $network_wide);
            $results[] = "Deactivated (env): {$file}";
        }
    }

    // PRUNE (optional): uninstall plugins that aren't part of this env’s set (so you truly “only see” env plugins)
    if ($PRUNE) {
        $keep_files = array_merge(
            array_keys($should_install), // only keep those meant to exist in this env
            MZ_PRUNE_EXCLUDED_PLUGINS
        );
        $to_delete  = array_diff(array_keys($all), $keep_files);
        if (!empty($to_delete)) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            foreach ($to_delete as $file) {
                if (is_plugin_active($file)) deactivate_plugins($file, true);
                // Don’t try to delete must-use/dropins (get_plugins() doesn’t include them anyway)
                $del = delete_plugins([$file]); // batch API expects array
                if (is_wp_error($del)) $results[] = "Delete failed: {$file}";
                else $results[] = "Deleted: {$file}";
            }
        }
    }

    update_option('mz_bootstrap_fingerprint_envonly', $fingerprint, true);
    update_option('mz_plugins_initialized', 1);

    if ($results) {
        add_action('admin_notices', function () use ($results, $env) {
            echo '<div class="notice notice-success"><p><strong>MZ Env Plugins (' . esc_html($env) . '):</strong></p><ul style="margin-left:1em">';
            foreach ($results as $msg) echo '<li>' . esc_html($msg) . '</li>';
            echo '</ul></div>';
        });
    }
});

// Hide non-env plugins from the Plugins screen without deleting (if toggle on)
if ($HIDE) {
    add_filter('all_plugins', function ($plugins) use ($catalog, $env) {
        // Build set of files we want visible in this env (active + install-only for this env)
        $visible = [];
        foreach ($catalog as $p) {
            if (in_array($env, (array)$p['envs'], true)) $visible[$p['file']] = true;
        }
        foreach ($plugins as $file => $data) {
            if (!isset($visible[$file])) unset($plugins[$file]);
        }
        return $plugins;
    }, 50);
}

// Normalize plugin names in the Plugins list so the title cell stays plain text.
add_filter('all_plugins', function ($plugins) {
    foreach ($plugins as $plugin_file => &$plugin_data) {
        if (!is_array($plugin_data)) continue;

        $plain_name = trim(wp_strip_all_tags((string) ($plugin_data['Name'] ?? $plugin_data['Title'] ?? '')));
        if ($plain_name === '') continue;

        if ($plugin_file === 'all-in-one-seo-pack/all_in_one_seo_pack.php') {
            $plain_name = 'All in One SEO';
        } elseif ($plugin_file === 'popup-maker/popup-maker.php') {
            $plain_name = 'Popup Maker';
        } elseif (preg_match('/^DS\s+/i', $plain_name) === 1) {
            $plain_name = preg_replace('/^DS\s+/i', 'MZ ', $plain_name) ?: $plain_name;
        }

        $plugin_data['Name'] = $plain_name;
        $plugin_data['Title'] = $plain_name;
    }
    unset($plugin_data);

    return $plugins;
}, PHP_INT_MAX);

/**
 * Limit Plugins screen row actions based on state:
 * - Active plugins: Settings, Deactivate
 * - Inactive plugins: Activate, Delete
 */
$mz_limit_plugin_actions = function ($actions) {
    $settings_link   = null;
    $deactivate_link = null;
    $activate_link   = null;
    $delete_link     = null;
    $normalize_action_label = static function ($html, string $label): string {
        $html = (string) $html;
        if ($html === '') {
            return esc_html($label);
        }

        if (stripos($html, '<a') === false) {
            return esc_html($label);
        }

        $updated = preg_replace(
            '/(<a\b[^>]*>)(.*?)(<\/a>)/is',
            '$1' . esc_html($label) . '$3',
            $html,
            1
        );

        return is_string($updated) && $updated !== '' ? $updated : $html;
    };

    foreach ((array)$actions as $key => $html) {
        $key_lc  = strtolower((string)$key);
        $text_lc = strtolower(trim(wp_strip_all_tags((string)$html)));
        $html_lc = strtolower((string)$html);

        if (
            $settings_link === null &&
            (
                strpos($key_lc, 'setting') !== false ||
                strpos($text_lc, 'settings') !== false
            )
        ) {
            $settings_link = $normalize_action_label($html, 'Settings');
        }

        if (
            $deactivate_link === null &&
            (
                strpos($key_lc, 'deactivate') !== false ||
                strpos($text_lc, 'deactivate') !== false ||
                strpos($html_lc, 'action=deactivate') !== false
            )
        ) {
            $deactivate_link = $html;
        }

        if (
            $activate_link === null &&
            (
                (
                    strpos($key_lc, 'activate') !== false &&
                    strpos($key_lc, 'deactivate') === false
                ) ||
                (
                    strpos($text_lc, 'activate') !== false &&
                    strpos($text_lc, 'deactivate') === false
                ) ||
                strpos($html_lc, 'action=activate') !== false
            )
        ) {
            $activate_link = $html;
        }

        if (
            $delete_link === null &&
            (
                strpos($key_lc, 'delete') !== false ||
                strpos($text_lc, 'delete') !== false ||
                strpos($html_lc, 'action=delete') !== false
            )
        ) {
            $delete_link = $html;
        }
    }

    $filtered = [];
    if ($deactivate_link !== null) {
        if ($settings_link !== null) {
            $filtered['settings'] = $settings_link;
        }
        $filtered['deactivate'] = $deactivate_link;
        return $filtered;
    }

    if ($activate_link !== null) {
        $filtered['activate'] = $activate_link;
    }
    if ($delete_link !== null) {
        $filtered['delete'] = $delete_link;
    }

    if (!empty($filtered)) {
        return $filtered;
    }

    return (array)$actions;
};

add_filter('plugin_action_links', $mz_limit_plugin_actions, PHP_INT_MAX, 4);
add_filter('network_admin_plugin_action_links', $mz_limit_plugin_actions, PHP_INT_MAX, 4);

$mz_lock_plugin_actions = function ($actions, $plugin_file = '', $plugin_data = [], $context = '') {
    if (!is_string($plugin_file) || !mz_plugins_is_locked_plugin($plugin_file) || !is_array($actions)) {
        return $actions;
    }

    foreach ($actions as $key => $action) {
        $key_lc = strtolower((string) $key);
        $action_lc = strtolower((string) $action);

        if (
            strpos($key_lc, 'deactivate') !== false
            || strpos($key_lc, 'delete') !== false
            || strpos($action_lc, 'action=deactivate') !== false
            || strpos($action_lc, 'action=delete') !== false
        ) {
            unset($actions[$key]);
        }
    }

    return $actions;
};

add_filter('plugin_action_links', $mz_lock_plugin_actions, PHP_INT_MAX, 4);
add_filter('network_admin_plugin_action_links', $mz_lock_plugin_actions, PHP_INT_MAX, 4);

add_action('admin_init', function () use ($mz_limit_plugin_actions) {
    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    foreach (array_keys(get_plugins()) as $plugin_file) {
        add_filter("plugin_action_links_{$plugin_file}", $mz_limit_plugin_actions, PHP_INT_MAX, 4);
        add_filter("network_admin_plugin_action_links_{$plugin_file}", $mz_limit_plugin_actions, PHP_INT_MAX, 4);
    }
}, PHP_INT_MAX);

// Global auto-update policy by environment (forced, ignores per-site toggles).
$mz_updates_env = defined('WP_ENV') ? strtolower((string) WP_ENV) : 'production';
$mz_updates_is_production = ($mz_updates_env === 'production');

add_filter('automatic_updater_disabled', function () use ($mz_updates_is_production) {
    return !$mz_updates_is_production;
});
add_filter('allow_minor_auto_core_updates', function () use ($mz_updates_is_production) {
    return $mz_updates_is_production;
});
add_filter('allow_major_auto_core_updates', function () use ($mz_updates_is_production) {
    return $mz_updates_is_production;
});
add_filter('allow_dev_auto_core_updates', function () use ($mz_updates_is_production) {
    return $mz_updates_is_production;
});
add_filter('auto_update_theme', function ($update, $item) use ($mz_updates_is_production) {
    return $mz_updates_is_production;
}, 10, 2);
add_filter('auto_update_plugin', function ($update, $item) use ($mz_updates_is_production) {
    return $mz_updates_is_production;
}, 10, 2);

// Hide the "Automatic Updates" column on plugin list screens.
add_filter('manage_plugins_columns', function ($columns) {
    if (isset($columns['auto-updates'])) {
        unset($columns['auto-updates']);
    }
    return $columns;
});
add_filter('manage_plugins-network_columns', function ($columns) {
    if (isset($columns['auto-updates'])) {
        unset($columns['auto-updates']);
    }
    return $columns;
});

// Show only real plugin metadata from headers and omit the default details modal link.
add_filter('plugin_row_meta', function ($plugin_meta, $plugin_file, $plugin_data, $status) {
    $plugin_meta = [];

    $version = isset($plugin_data['Version']) ? trim((string)$plugin_data['Version']) : '';
    if ($version !== '') {
        $plugin_meta[] = 'Version ' . esc_html($version);
    }

    $author_text = isset($plugin_data['AuthorName']) ? trim((string)$plugin_data['AuthorName']) : '';
    if ($author_text === '') {
        $author_text = isset($plugin_data['Author']) ? trim(wp_strip_all_tags((string)$plugin_data['Author'])) : '';
    }
    if ($author_text !== '') {
        $author_uri = isset($plugin_data['AuthorURI']) ? trim((string)$plugin_data['AuthorURI']) : '';
        if ($author_uri !== '') {
            $plugin_meta[] = 'By <a href="' . esc_url($author_uri) . '" target="_blank" rel="noopener noreferrer">' . esc_html($author_text) . '</a>';
        } else {
            $plugin_meta[] = 'By ' . esc_html($author_text);
        }
    }

    return $plugin_meta;
}, PHP_INT_MAX, 4);

// Disable Popup Maker's plugin-list branding script so the plain plugin name remains intact.
add_action('admin_init', function () {
    if (!function_exists('\PopupMaker\plugin')) return;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $screen_base = $screen instanceof WP_Screen ? (string) ($screen->base ?? '') : '';
    if ($screen_base !== '' && !in_array($screen_base, ['plugins', 'plugins-network'], true)) return;

    $plugins_page_controller = \PopupMaker\plugin('Admin\WP\PluginsPage');
    if (!is_object($plugins_page_controller) || !method_exists($plugins_page_controller, 'footer_scripts')) return;

    remove_action('admin_print_footer_scripts', [$plugins_page_controller, 'footer_scripts']);
}, PHP_INT_MAX);
