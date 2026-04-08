<?php

/**
 * Plugin Name: MZ Plugins
 * Description: Environment-based plugin installation, activation, and visibility rules.
 * Version: 1.4.23
 * Author: Meza LLC
 * Author URI: https://meza.design
 *
 * Requirements:
 *   define('WP_ENV', 'development'); // or 'staging' | 'qa' | 'production'
 *
 * Optional toggles in wp-config.php:
 *   define('MZ_PRUNE_PLUGINS', true);         // uninstall plugins not meant for this environment
 *   define('MZ_HIDE_NON_ENV_PLUGINS', true);  // hide any non-env plugins from the Plugins screen
 *   define('MZ_ACF_PRO_ZIP', 'https://example.com/advanced-custom-fields-pro.zip'); // private ZIP if needed
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

/** Convert common config-style values like 1/true/yes/on into a real boolean. */
function mz_plugins_truthy($value): bool
{
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

if (!function_exists('mz_plugins_get_catalog')) {
    function mz_plugins_get_catalog(): array
    {
        return [
            // All
            ['name' => 'Advanced Custom Fields PRO', 'slug' => 'advanced-custom-fields-pro', 'file' => 'advanced-custom-fields-pro/acf.php', 'envs' => ['development', 'staging', 'qa', 'production'], 'zip_const' => 'MZ_ACF_PRO_ZIP'],
            ['name' => 'Classic Editor', 'slug' => 'classic-editor', 'file' => 'classic-editor/classic-editor.php', 'envs' => ['development', 'staging', 'qa', 'production']],
            ['name' => 'Intuitive Custom Post Order', 'slug' => 'intuitive-custom-post-order', 'file' => 'intuitive-custom-post-order/intuitive-custom-post-order.php', 'envs' => ['development', 'staging', 'qa', 'production']],
            ['name' => 'Post Duplicator', 'slug' => 'post-duplicator', 'file' => 'post-duplicator/m4c-postduplicator.php', 'envs' => ['development', 'staging', 'qa', 'production']],
            ['name' => 'UpdraftPlus', 'slug' => 'updraftplus', 'file' => 'updraftplus/updraftplus.php', 'envs' => ['development', 'staging', 'qa', 'production']],
            ['name' => 'WordPress Importer', 'slug' => 'wordpress-importer', 'file' => 'wordpress-importer/wordpress-importer.php', 'envs' => ['development', 'staging', 'qa', 'production']],
            ['name' => 'WP Mail SMTP', 'slug' => 'wp-mail-smtp', 'file' => 'wp-mail-smtp/wp_mail_smtp.php', 'envs' => ['development', 'staging', 'qa', 'production']],
            ['name' => 'Error Log Monitor', 'slug' => 'error-log-monitor', 'file' => 'error-log-monitor/plugin.php', 'envs' => ['development', 'staging', 'qa', 'production']],

            // All except development
            ['name' => 'Yoast SEO', 'slug' => 'wordpress-seo', 'file' => 'wordpress-seo/wp-seo.php', 'envs' => ['development'], 'activate' => false],
            ['name' => 'Yoast SEO', 'slug' => 'wordpress-seo', 'file' => 'wordpress-seo/wp-seo.php', 'envs' => ['staging', 'qa', 'production']],
            ['name' => 'ACF Content Analysis for Yoast SEO', 'slug' => 'acf-content-analysis-for-yoast-seo', 'file' => 'acf-content-analysis-for-yoast-seo/yoast-acf-analysis.php', 'envs' => ['staging', 'qa', 'production'], 'requires_active' => ['advanced-custom-fields-pro/acf.php', 'wordpress-seo/wp-seo.php'], 'requires_wp' => '6.6', 'requires_php' => '7.2.5'],
            ['name' => 'Admin Columns', 'slug' => 'codepress-admin-columns', 'file' => 'codepress-admin-columns/codepress-admin-columns.php', 'envs' => ['staging', 'qa', 'production']],
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
    }
}

if (!function_exists('mz_plugins_get_env_catalog')) {
    function mz_plugins_get_env_catalog(?string $env = null): array
    {
        $env = strtolower((string) ($env ?: (defined('WP_ENV') ? WP_ENV : 'production')));

        return array_values(array_filter(mz_plugins_get_catalog(), static function (array $plugin) use ($env): bool {
            return in_array($env, (array) ($plugin['envs'] ?? []), true);
        }));
    }
}

if (!function_exists('mz_plugins_get_env_plugin_signatures')) {
    function mz_plugins_get_env_plugin_signatures(?string $env = null): array
    {
        $signatures = [];

        foreach (mz_plugins_get_env_catalog($env) as $plugin) {
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

            foreach ($tokens as $token) {
                $signatures[$token] = true;
            }
        }

        return array_keys($signatures);
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
    if (!current_user_can('install_plugins') || !current_user_can('activate_plugins')) return;

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
            $should_install[$file] = $p;
            if ($activate) {
                $should_activate[$file] = $p;
            }
        }
    }

    // Fingerprint per env so switching envs/catalog state re-runs.
    $fingerprint = md5(
        $env . '|' .
            wp_json_encode($should_install) . '|' .
            wp_json_encode($should_activate) . '|' .
            (int)$network_wide . '|' .
            (int)$PRUNE
    );

    $has_run = (int)get_option('mz_plugins_initialized', 0) === 1;
    $last_fp = (string)get_option('mz_bootstrap_fingerprint_envonly', '');
    if ($has_run && $last_fp === $fingerprint && !mz_plugins_should_rerun()) return;

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

        if (isset($all[$file])) continue;
        $slug = $p['slug'] ?? '';
        $api_error = null;
        $private_package_source = mz_plugins_resolve_private_package_source($p, $api_error);

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
        if (isset($all[$file])) {
            $results[] = "Installed: {$p['name']}";
            continue;
        }

        $results[] = "Install failed (main file): {$p['name']}";
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
