<?php

/**
 * Plugin Name: MZ Plugins
 * Description: Environment-based plugin installation, activation, and visibility rules.
 * Version: 1.4.0
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

$env          = defined('WP_ENV') ? strtolower(WP_ENV) : 'production';
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

// Catalog: 'envs' = envs where it should be ACTIVE; 'activate' false => keep installed but deactivated.
$catalog = [
    // All
    ['name' => 'Advanced Custom Fields PRO', 'slug' => 'advanced-custom-fields-pro', 'file' => 'advanced-custom-fields-pro/acf.php', 'envs' => ['development', 'staging', 'qa', 'production'], 'zip_const' => 'MZ_ACF_PRO_ZIP'],
    ['name' => 'Classic Editor', 'slug' => 'classic-editor', 'file' => 'classic-editor/classic-editor.php', 'envs' => ['development', 'staging', 'qa', 'production']],
    ['name' => 'Intuitive Custom Post Order', 'slug' => 'intuitive-custom-post-order', 'file' => 'intuitive-custom-post-order/intuitive-custom-post-order.php', 'envs' => ['development', 'staging', 'qa', 'production']],
    ['name' => 'Post Duplicator', 'slug' => 'post-duplicator', 'file' => 'post-duplicator/m4c-postduplicator.php', 'envs' => ['development', 'staging', 'qa', 'production']],
    ['name' => 'UpdraftPlus', 'slug' => 'updraftplus', 'file' => 'updraftplus/updraftplus.php', 'envs' => ['development', 'staging', 'qa', 'production']],
    ['name' => 'WordPress Importer', 'slug' => 'wordpress-importer', 'file' => 'wordpress-importer/wordpress-importer.php', 'envs' => ['development', 'staging', 'qa', 'production']],
    ['name' => 'WP Mail SMTP', 'slug' => 'wp-mail-smtp', 'file' => 'wp-mail-smtp/wp_mail_smtp.php', 'envs' => ['development', 'staging', 'qa', 'production']],

    // All except development
    ['name' => 'ACF Content Analysis for Yoast SEO', 'slug' => 'acf-content-analysis-for-yoast-seo', 'file' => 'acf-content-analysis-for-yoast-seo/acf-content-analysis-for-yoast-seo.php', 'envs' => ['staging', 'qa', 'production']],
    ['name' => 'Admin Columns', 'slug' => 'codepress-admin-columns', 'file' => 'codepress-admin-columns/codepress-admin-columns.php', 'envs' => ['staging', 'qa', 'production']],
    ['name' => 'Admin Menu Editor', 'slug' => 'admin-menu-editor', 'file' => 'admin-menu-editor/menu-editor.php', 'envs' => ['staging', 'qa', 'production']],
    ['name' => 'Yoast SEO', 'slug' => 'wordpress-seo', 'file' => 'wordpress-seo/wp-seo.php', 'envs' => ['development'], 'activate' => false],
    ['name' => 'Yoast SEO', 'slug' => 'wordpress-seo', 'file' => 'wordpress-seo/wp-seo.php', 'envs' => ['staging', 'qa', 'production']],

    // All except production
    ['name' => 'Error Log Monitor', 'slug' => 'error-log-monitor', 'file' => 'error-log-monitor/plugin.php', 'envs' => ['development', 'staging', 'qa']],
    ['name' => 'Query Monitor', 'slug' => 'query-monitor', 'file' => 'query-monitor/query-monitor.php', 'envs' => ['development', 'staging', 'qa']],

    // QA + Production only
    ['name' => 'All-In-One Security (AIOS)', 'slug' => 'all-in-one-wp-security-and-firewall', 'file' => 'all-in-one-wp-security-and-firewall/wp-security.php', 'envs' => ['qa', 'production']],
    ['name' => 'Converter for Media', 'slug' => 'webp-converter-for-media', 'file' => 'webp-converter-for-media/webp-converter-for-media.php', 'envs' => ['qa', 'production']],
    ['name' => 'Redirection', 'slug' => 'redirection', 'file' => 'redirection/redirection.php', 'envs' => ['qa', 'production']],
    ['name' => 'WP Super Cache', 'slug' => 'wp-super-cache', 'file' => 'wp-super-cache/wp-cache.php', 'envs' => ['qa', 'production']],

    // Production only
    ['name' => 'Site Kit by Google', 'slug' => 'google-site-kit', 'file' => 'google-site-kit/google-site-kit.php', 'envs' => ['production']],
];

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
        if (isset($all[$file])) continue;
        $slug = $p['slug'] ?? '';
        $zip  = '';
        if (!empty($p['zip'])) {
            $zip = $p['zip'];
        } elseif (!empty($p['zip_const']) && defined($p['zip_const']) && constant($p['zip_const'])) {
            $zip = constant($p['zip_const']);
        }

        $download = $zip ?: (function ($slug) {
            $api = plugins_api('plugin_information', ['slug' => $slug, 'fields' => ['sections' => false]]);
            if (is_wp_error($api) || empty($api->download_link)) return '';
            return $api->download_link;
        })($slug);

        if (!$download) {
            $results[] = "Install failed (resolve): {$p['name']}";
            continue;
        }
        $ok = $upgrader->install($download);
        if (is_wp_error($ok) || !$ok) {
            $results[] = "Install failed: {$p['name']}";
            continue;
        }
        wp_clean_plugins_cache(true);
        $all = get_plugins();
        if (isset($all[$file])) $results[] = "Installed: {$p['name']}";
    }

    // ACTIVATE required
    foreach ($should_activate as $file => $p) {
        if (is_plugin_active($file) || (is_multisite() && is_plugin_active_for_network($file))) continue;
        if (!isset($all[$file])) {
            wp_clean_plugins_cache(true);
            $all = get_plugins();
        }
        if (!isset($all[$file])) {
            $results[] = "Skip activate (not found): {$p['name']}";
            continue;
        }

        $err = activate_plugin($file, '', $network_wide, true);
        if (is_wp_error($err)) $results[] = "Activation failed: {$p['name']}";
        else $results[] = "Activated: {$p['name']}";
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
