<?php

if (!function_exists('meza_site_documentation_is_theme_available')) {
    function meza_site_documentation_is_theme_available(string $stylesheet): bool
    {
        $theme = wp_get_theme($stylesheet);
        return $theme instanceof WP_Theme && $theme->exists();
    }
}

if (!function_exists('meza_site_documentation_is_dependency_available')) {
    function meza_site_documentation_is_dependency_available(array $row): bool
    {
        if (!empty($row['callback']) && is_callable($row['callback'])) {
            return (bool) call_user_func($row['callback'], $row);
        }

        if (!empty($row['plugin'])) {
            return function_exists('meza_is_plugin_basename_active')
                ? meza_is_plugin_basename_active((string) $row['plugin'])
                : false;
        }

        if (!empty($row['plugin_installed'])) {
            $plugin_file = trim((string) $row['plugin_installed']);
            return defined('WP_PLUGIN_DIR') && $plugin_file !== '' && file_exists(WP_PLUGIN_DIR . '/' . $plugin_file);
        }

        if (!empty($row['theme'])) {
            return meza_site_documentation_is_theme_available((string) $row['theme']);
        }

        return true;
    }
}

if (!function_exists('meza_site_documentation_is_plugin_row_active')) {
    function meza_site_documentation_is_plugin_row_active(array $row): bool
    {
        $plugin_file = trim((string) ($row['plugin_installed'] ?? ''));

        if ($plugin_file === '') {
            return false;
        }

        return function_exists('meza_is_plugin_basename_active')
            ? meza_is_plugin_basename_active($plugin_file)
            : false;
    }
}

if (!function_exists('meza_site_documentation_capabilities_registry')) {
    function meza_site_documentation_capabilities_registry(): array
    {
        $seo = meza_site_documentation_seo_manager_role_key();
        $site = meza_site_documentation_site_manager_role_key();

        return [
            'update_wordpress' => [
                'label' => 'Update WordPress',
                'description' => 'Update WordPress core, themes, and plugins. Automatic updates are enabled where appropriate.',
                'capability' => 'meza_doc_update_wordpress',
                'access' => [
                    $seo => '',
                    $site => '',
                    'administrator' => 'Full access',
                ],
                'action' => 'Manage',
            ],
            'manage_plugins' => [
                'label' => 'Manage Plugins',
                'description' => 'Add, activate, deactivate, and remove plugins used to enhance the website.',
                'capability' => 'meza_doc_manage_plugins',
                'access' => [
                    $seo => '',
                    $site => 'View access',
                    'administrator' => 'Full access',
                ],
                'action' => 'Manage',
            ],
            'manage_users' => [
                'label' => 'Manage Users',
                'description' => 'Add, remove, and update users who have access to the website admin.',
                'capability' => 'meza_doc_manage_users',
                'access' => [
                    $seo => '',
                    $site => 'View access',
                    'administrator' => 'Full access',
                ],
                'action' => 'Manage',
            ],
            'unlock_users' => [
                'label' => 'Unlock Users',
                'description' => 'View and unlock users who have been locked out from the website admin.',
                'capability' => 'meza_doc_unlock_users',
                'plugin' => 'all-in-one-wp-security-and-firewall/wp-security.php',
                'hide_when_dependency_missing' => true,
                'access' => [
                    $seo => '',
                    $site => 'Full access',
                    'administrator' => 'Full access',
                ],
                'action' => 'Manage',
            ],
            'update_site_information' => [
                'label' => 'Update Site Information',
                'description' => 'Manage general business and contact information used across the website.',
                'capability' => 'meza_doc_update_site_information',
                'access' => [
                    $seo => '',
                    $site => 'Full access',
                    'administrator' => 'Full access',
                ],
                'action' => 'Manage',
            ],
            'update_site_branding' => [
                'label' => 'Update Site Branding',
                'description' => 'Manage site logos, icons, social share defaults, and other branding assets.',
                'capability' => 'meza_doc_update_site_branding',
                'access' => [
                    $seo => '',
                    $site => 'Full access',
                    'administrator' => 'Full access',
                ],
                'action' => 'Manage',
            ],
            'manage_menus' => [
                'label' => 'Manage Menus',
                'description' => 'Update the primary, utility, social, and legal navigation menus.',
                'capability' => 'meza_doc_manage_menus',
                'access' => [
                    $seo => 'Full access',
                    $site => 'Full access',
                    'administrator' => 'Full access',
                ],
                'action' => 'Manage',
            ],
            'manage_redirects' => [
                'label' => 'Manage Redirects',
                'description' => 'Add, remove, and update page redirections.',
                'capability' => 'meza_doc_manage_redirects',
                'plugin' => 'redirection/redirection.php',
                'hide_when_dependency_missing' => true,
                'access' => [
                    $seo => 'Full access',
                    $site => 'Full access',
                    'administrator' => 'Full access',
                ],
                'action' => 'Manage',
            ],
            'manage_seo_settings' => [
                'label' => 'Manage SEO Settings',
                'description' => 'Manage sitemaps, indexing settings, schema defaults, and other SEO-related options.',
                'capability' => 'meza_doc_manage_seo_settings',
                'plugin' => 'wordpress-seo/wp-seo.php',
                'hide_when_dependency_missing' => true,
                'access' => [
                    $seo => 'Full access',
                    $site => 'Full access',
                    'administrator' => 'Full access',
                ],
                'action' => 'Manage',
            ],
            'manage_analytics_settings' => [
                'label' => 'Manage Analytics Settings',
                'description' => 'Manage analytics account connections and dashboard access settings.',
                'capability' => 'meza_doc_manage_analytics_settings',
                'plugin' => 'google-site-kit/google-site-kit.php',
                'hide_when_dependency_missing' => true,
                'access' => [
                    $seo => '',
                    $site => '',
                    'administrator' => 'Full access',
                ],
                'action' => 'Manage',
            ],
            'manage_security_settings' => [
                'label' => 'Manage Security Settings',
                'description' => 'Manage settings related to user, file, database, spam, and firewall security.',
                'capability' => 'meza_doc_manage_security_settings',
                'plugin' => 'all-in-one-wp-security-and-firewall/wp-security.php',
                'hide_when_dependency_missing' => true,
                'access' => [
                    $seo => '',
                    $site => '',
                    'administrator' => 'Full access',
                ],
                'action' => 'Manage',
            ],
            'manage_backups' => [
                'label' => 'Manage Backups',
                'description' => 'Create and delete backups. Automatic backups are enabled where supported.',
                'capability' => 'meza_doc_manage_backups',
                'plugin' => 'updraftplus/updraftplus.php',
                'hide_when_dependency_missing' => true,
                'access' => [
                    $seo => '',
                    $site => '',
                    'administrator' => 'Full access',
                ],
                'action' => 'Manage',
            ],
            'view_site_health' => [
                'label' => 'View Site Health Status',
                'description' => 'View back-end health issues and recommendations surfaced by WordPress.',
                'capability' => 'meza_doc_view_site_health',
                'access' => [
                    $seo => '',
                    $site => '',
                    'administrator' => 'Full access',
                ],
                'action' => 'Manage',
            ],
            'manage_maintenance_mode' => [
                'label' => 'Manage Maintenance Mode',
                'description' => 'Activate and deactivate visitor lockout or maintenance mode when the site needs to be temporarily closed.',
                'capability' => 'meza_doc_manage_maintenance_mode',
                'hide_when_dependency_missing' => true,
                'callback' => static function (): bool {
                    foreach ([
                        'all-in-one-wp-security-and-firewall/wp-security.php',
                        'maintenance/maintenance.php',
                        'wp-maintenance-mode/wp-maintenance-mode.php',
                    ] as $plugin_file) {
                        if (function_exists('meza_is_plugin_basename_active') && meza_is_plugin_basename_active($plugin_file)) {
                            return true;
                        }
                    }

                    return false;
                },
                'access' => [
                    $seo => '',
                    $site => '',
                    'administrator' => 'Full access',
                ],
                'action' => 'Manage',
            ],
        ];
    }
}

if (!function_exists('meza_site_documentation_dashboards_registry')) {
    function meza_site_documentation_dashboards_registry(): array
    {
        return [
            'wordpress' => [
                'label' => 'WordPress',
                'description' => 'View quick overviews of website content, updates, and site health from the WordPress dashboard.',
                'capability' => 'meza_doc_dashboard_wordpress',
                'roles' => array_keys(meza_site_documentation_access_roles()),
                'action' => 'View',
            ],
            'google_site_kit' => [
                'label' => 'Google Site Kit',
                'description' => 'View dashboards related to website, SEO, and performance analytics.',
                'capability' => 'meza_doc_dashboard_google_site_kit',
                'plugin' => 'google-site-kit/google-site-kit.php',
                'hide_when_dependency_missing' => true,
                'roles' => array_keys(meza_site_documentation_access_roles()),
                'action' => 'View',
            ],
            'security' => [
                'label' => 'All-in-One Security (AIOS)',
                'description' => 'View security settings, critical features, logs, and login history.',
                'capability' => 'meza_doc_dashboard_security',
                'plugin' => 'all-in-one-wp-security-and-firewall/wp-security.php',
                'hide_when_dependency_missing' => true,
                'roles' => ['administrator'],
                'action' => 'View',
            ],
        ];
    }
}

if (!function_exists('meza_site_documentation_tools_registry')) {
    function meza_site_documentation_tools_registry(): array
    {
        return [
            'import' => [
                'label' => 'Import',
                'description' => 'Import WordPress content exported from another WordPress website.',
                'capability' => 'meza_doc_tool_import',
                'roles' => array_keys(meza_site_documentation_access_roles()),
                'action' => 'Use',
            ],
            'export' => [
                'label' => 'Export',
                'description' => 'Export WordPress content for importing into another WordPress website.',
                'capability' => 'meza_doc_tool_export',
                'roles' => [
                    meza_site_documentation_site_manager_role_key(),
                    'administrator',
                ],
                'action' => 'Use',
            ],
            'seo_bulk_edit' => [
                'label' => 'SEO Bulk Edit',
                'description' => 'Edit meta titles and descriptions in bulk.',
                'capability' => 'meza_doc_tool_seo_bulk_edit',
                'plugin' => 'wordpress-seo/wp-seo.php',
                'hide_when_dependency_missing' => true,
                'roles' => array_keys(meza_site_documentation_access_roles()),
                'action' => 'Use',
            ],
            'two_factor_authentication' => [
                'label' => 'Two-Factor Authentication',
                'description' => 'Set up two-factor authentication for WordPress login.',
                'capability' => 'meza_doc_tool_two_factor',
                'plugin' => 'all-in-one-wp-security-and-firewall/wp-security.php',
                'hide_when_dependency_missing' => true,
                'roles' => array_keys(meza_site_documentation_access_roles()),
                'action' => 'Use',
            ],
            'password_strength' => [
                'label' => 'Password Strength',
                'description' => 'Generate stronger passwords for WordPress accounts.',
                'capability' => 'meza_doc_tool_password_strength',
                'plugin' => 'all-in-one-wp-security-and-firewall/wp-security.php',
                'hide_when_dependency_missing' => true,
                'roles' => array_keys(meza_site_documentation_access_roles()),
                'action' => 'Use',
            ],
            'file_scan' => [
                'label' => 'File Scan',
                'description' => 'Scan for abnormal file changes. Automatic scanning is enabled where supported.',
                'capability' => 'meza_doc_tool_file_scan',
                'plugin' => 'all-in-one-wp-security-and-firewall/wp-security.php',
                'hide_when_dependency_missing' => true,
                'roles' => ['administrator'],
                'action' => 'Use',
            ],
        ];
    }
}

if (!function_exists('meza_site_documentation_analytics_registry')) {
    function meza_site_documentation_analytics_registry(): array
    {
        return [
            'google_analytics' => [
                'label' => 'Google Analytics',
                'description' => 'Measures website metrics like page views, user engagement, and traffic acquisition.',
                'capability' => 'meza_doc_analytics_google_analytics',
                'plugin' => 'google-site-kit/google-site-kit.php',
                'default_access_entity' => '',
                'importance' => 'Important',
                'action' => 'View',
            ],
            'google_search_console' => [
                'label' => 'Google Search Console',
                'description' => 'Measures search query impressions, clicks, and page visibility in Google search results.',
                'capability' => 'meza_doc_analytics_search_console',
                'plugin' => 'google-site-kit/google-site-kit.php',
                'default_access_entity' => '',
                'importance' => 'Important',
                'action' => 'View',
            ],
            'pagespeed_insights' => [
                'label' => 'PageSpeed Insights',
                'description' => 'Measures page speed, technical SEO, accessibility, and performance health.',
                'capability' => 'meza_doc_analytics_pagespeed',
                'default_access_entity' => 'Public',
                'importance' => 'Important',
                'action' => 'View',
            ],
        ];
    }
}

if (!function_exists('meza_site_documentation_plugins_registry')) {
    function meza_site_documentation_plugins_registry(): array
    {
        return [
            'acf_content_analysis' => [
                'label' => 'ACF Content Analysis for Yoast SEO',
                'description' => 'Adds support for ACF content inside Yoast SEO analysis.',
                'capability' => 'meza_doc_plugin_acf_content_analysis',
                'plugin_installed' => 'acf-content-analysis-for-yoast-seo/yoast-acf-analysis.php',
                'roles' => [],
                'importance' => 'Nice to have',
                'action' => '',
            ],
            'admin_columns' => [
                'label' => 'Admin Columns',
                'description' => 'Lets administrators edit admin columns on post listing screens.',
                'capability' => 'meza_doc_plugin_admin_columns',
                'plugin_installed' => 'codepress-admin-columns/codepress-admin-columns.php',
                'roles' => ['administrator'],
                'importance' => 'Nice to have',
                'action' => 'Settings',
            ],
            'admin_menu_editor' => [
                'label' => 'Admin Menu Editor',
                'description' => 'Allows administrators to customize the admin menu for different user roles.',
                'capability' => 'meza_doc_plugin_admin_menu_editor',
                'plugin_installed' => 'admin-menu-editor/menu-editor.php',
                'roles' => ['administrator'],
                'importance' => 'Nice to have',
                'action' => 'Settings',
            ],
            'advanced_custom_fields_pro' => [
                'label' => 'Advanced Custom Fields PRO',
                'description' => 'Allows administrators to set up post types, custom fields, and taxonomies for content.',
                'capability' => 'meza_doc_plugin_acf_pro',
                'plugin_installed' => 'advanced-custom-fields-pro/acf.php',
                'roles' => ['administrator'],
                'importance' => 'Essential',
                'action' => 'Settings',
            ],
            'aios' => [
                'label' => 'All-in-One Security (AIOS)',
                'description' => 'Adds security settings for user, file, database, spam, and firewall protection.',
                'capability' => 'meza_doc_plugin_aios',
                'plugin_installed' => 'all-in-one-wp-security-and-firewall/wp-security.php',
                'roles' => ['administrator'],
                'importance' => 'Important',
                'action' => 'Settings',
            ],
            'classic_editor' => [
                'label' => 'Classic Editor',
                'description' => 'Keeps WordPress WYSIWYG editing on the classic editor experience.',
                'capability' => 'meza_doc_plugin_classic_editor',
                'plugin_installed' => 'classic-editor/classic-editor.php',
                'roles' => ['administrator'],
                'importance' => 'Essential',
                'action' => 'Settings',
            ],
            'intuitive_custom_post_order' => [
                'label' => 'Intuitive Custom Post Order',
                'description' => 'Adds drag-and-drop post ordering on supported post type screens.',
                'capability' => 'meza_doc_plugin_post_order',
                'plugin_installed' => 'intuitive-custom-post-order/intuitive-custom-post-order.php',
                'roles' => ['administrator'],
                'importance' => 'Nice to have',
                'action' => 'Settings',
            ],
            'post_duplicator' => [
                'label' => 'Post Duplicator',
                'description' => 'Allows content editors to duplicate posts and pages more quickly.',
                'capability' => 'meza_doc_plugin_post_duplicator',
                'plugin_installed' => 'post-duplicator/post-duplicator.php',
                'roles' => ['administrator'],
                'importance' => 'Nice to have',
                'action' => 'Settings',
            ],
            'redirection' => [
                'label' => 'Redirection',
                'description' => 'Adds a UI to manage redirects more easily.',
                'capability' => 'meza_doc_plugin_redirection',
                'plugin_installed' => 'redirection/redirection.php',
                'roles' => ['administrator'],
                'importance' => 'Important',
                'action' => 'Settings',
            ],
            'site_kit' => [
                'label' => 'Site Kit by Google',
                'description' => 'Connects Google Analytics, Google Search Console, and PageSpeed Insights to WordPress admin.',
                'capability' => 'meza_doc_plugin_site_kit',
                'plugin_installed' => 'google-site-kit/google-site-kit.php',
                'roles' => ['administrator'],
                'importance' => 'Important',
                'action' => 'Settings',
            ],
            'sqlite_object_cache' => [
                'label' => 'SQLite Object Cache',
                'description' => 'Adds object caching to improve performance on supported environments.',
                'capability' => 'meza_doc_plugin_object_cache',
                'plugin_installed' => 'sqlite-object-cache/sqlite-object-cache.php',
                'roles' => ['administrator'],
                'importance' => 'Important',
                'action' => 'Settings',
            ],
            'updraftplus' => [
                'label' => 'UpdraftPlus',
                'description' => 'Allows administrators to back up, migrate, and clone the website.',
                'capability' => 'meza_doc_plugin_updraftplus',
                'plugin_installed' => 'updraftplus/updraftplus.php',
                'roles' => ['administrator'],
                'importance' => 'Important',
                'action' => 'Settings',
            ],
            'wordpress_importer' => [
                'label' => 'WordPress Importer',
                'description' => 'Adds the WordPress import feature to the admin.',
                'capability' => 'meza_doc_plugin_wordpress_importer',
                'plugin_installed' => 'wordpress-importer/wordpress-importer.php',
                'roles' => [
                    meza_site_documentation_site_manager_role_key(),
                    'administrator',
                ],
                'importance' => 'Nice to have',
                'action' => '',
            ],
            'yoast_seo' => [
                'label' => 'Yoast SEO',
                'description' => 'Adds SEO essentials and a more complete interface for managing metadata.',
                'capability' => 'meza_doc_plugin_yoast_seo',
                'plugin_installed' => 'wordpress-seo/wp-seo.php',
                'roles' => array_keys(meza_site_documentation_access_roles()),
                'importance' => 'Essential',
                'action' => 'Settings',
            ],
        ];
    }
}

if (!function_exists('meza_site_documentation_theme_roles')) {
    function meza_site_documentation_theme_roles(): array
    {
        return [
            meza_site_documentation_site_manager_role_key(),
            'administrator',
        ];
    }
}

if (!function_exists('meza_site_documentation_theme_defaults')) {
    function meza_site_documentation_theme_defaults(): array
    {
        return [
            'meza-starter' => [
                'description' => 'Loads the shared framework used to build performant and SEO-friendly websites.',
                'importance' => 'Essential',
                'type' => 'Starter',
            ],
            'twentytwentyfive' => [
                'description' => 'Installed with WordPress and kept available for maintenance or debugging when needed.',
                'importance' => 'Nice to have',
                'type' => 'Default',
            ],
        ];
    }
}

if (!function_exists('meza_site_documentation_theme_type')) {
    function meza_site_documentation_theme_type(WP_Theme $theme, string $active_stylesheet, string $active_template): string
    {
        $stylesheet = (string) $theme->get_stylesheet();

        if ($stylesheet === $active_stylesheet) {
            return $theme->parent() ? 'Child' : 'Active';
        }

        if ($stylesheet === $active_template) {
            return $stylesheet === 'meza-starter' ? 'Starter' : 'Parent';
        }

        if (str_starts_with($stylesheet, 'twenty')) {
            return 'Default';
        }

        return 'Installed';
    }
}

if (!function_exists('meza_site_documentation_theme_description')) {
    function meza_site_documentation_theme_description(
        WP_Theme $theme,
        string $stylesheet,
        string $active_stylesheet,
        string $active_template
    ): string {
        $defaults = meza_site_documentation_theme_defaults();

        if (!empty($defaults[$stylesheet]['description'])) {
            return (string) $defaults[$stylesheet]['description'];
        }

        if ($stylesheet === $active_stylesheet) {
            return 'Loads the active front-end templates, styles, and functionality used on the website.';
        }

        if ($stylesheet === $active_template) {
            return 'Supports the active website theme with shared templates, styles, and functionality.';
        }

        return 'Installed in WordPress and available for maintenance, debugging, or future use when needed.';
    }
}

if (!function_exists('meza_site_documentation_theme_importance')) {
    function meza_site_documentation_theme_importance(
        string $stylesheet,
        string $active_stylesheet,
        string $active_template
    ): string {
        $defaults = meza_site_documentation_theme_defaults();

        if (!empty($defaults[$stylesheet]['importance'])) {
            return (string) $defaults[$stylesheet]['importance'];
        }

        if ($stylesheet === $active_stylesheet || $stylesheet === $active_template) {
            return 'Essential';
        }

        return 'Nice to have';
    }
}

if (!function_exists('meza_site_documentation_build_theme_row')) {
    function meza_site_documentation_build_theme_row(
        WP_Theme $theme,
        string $active_stylesheet,
        string $active_template
    ): array {
        $stylesheet = (string) $theme->get_stylesheet();
        $type = meza_site_documentation_theme_type($theme, $active_stylesheet, $active_template);

        return [
            'label' => (string) $theme->get('Name'),
            'description' => meza_site_documentation_theme_description(
                $theme,
                $stylesheet,
                $active_stylesheet,
                $active_template
            ),
            'capability' => 'meza_doc_theme_' . sanitize_key($stylesheet),
            'theme' => $stylesheet,
            'roles' => meza_site_documentation_theme_roles(),
            'type' => $type,
            'importance' => meza_site_documentation_theme_importance(
                $stylesheet,
                $active_stylesheet,
                $active_template
            ),
            'action' => 'View',
        ];
    }
}

if (!function_exists('meza_site_documentation_themes_registry')) {
    function meza_site_documentation_themes_registry(): array
    {
        $active_theme = wp_get_theme();
        if (!$active_theme instanceof WP_Theme || !$active_theme->exists()) {
            return [];
        }

        $active_stylesheet = (string) $active_theme->get_stylesheet();
        $active_template = (string) $active_theme->get_template();
        $installed_themes = wp_get_themes();
        $rows = [];

        $ordered_stylesheets = [$active_stylesheet];

        if ($active_template !== '' && $active_template !== $active_stylesheet) {
            $ordered_stylesheets[] = $active_template;
        }

        foreach (array_keys($installed_themes) as $stylesheet) {
            if (!in_array($stylesheet, $ordered_stylesheets, true)) {
                $ordered_stylesheets[] = $stylesheet;
            }
        }

        foreach ($ordered_stylesheets as $stylesheet) {
            $theme = $installed_themes[$stylesheet] ?? null;

            if (!$theme instanceof WP_Theme || !$theme->exists()) {
                continue;
            }

            $theme_name = trim((string) $theme->get('Name'));
            if ($stylesheet === 'meza-starter-child' || strcasecmp($theme_name, 'Meza Child Theme') === 0) {
                continue;
            }

            $rows[sanitize_key($stylesheet)] = meza_site_documentation_build_theme_row(
                $theme,
                $active_stylesheet,
                $active_template
            );
        }

        return $rows;
    }
}

if (!function_exists('meza_site_documentation_code_dependency_description')) {
    function meza_site_documentation_code_dependency_description(string $dependency_name): string
    {
        $map = [
            '@wordpress/scripts' => 'Shared build, lint, and asset-compilation tooling for WordPress theme development.',
            '@babel/runtime' => 'Runtime helpers used by compiled JavaScript in the theme stack.',
            '@fortawesome/fontawesome-free' => 'Icon library used by the shared theme UI.',
            '@googlemaps/js-api-loader' => 'Loads the Google Maps JavaScript API for location features.',
            'glightbox' => 'Provides lightbox behavior for image and gallery interfaces.',
            'list.js' => 'Adds client-side sorting and filtering for documentation tables and other lists.',
            'swiper' => 'Provides carousel and slider behavior in shared front-end components.',
            'the-new-css-reset' => 'Resets browser default styles before shared theme styling is applied.',
            'vanilla-cookieconsent' => 'Powers the site cookie-consent interface and consent state handling.',
            'eslint-import-resolver-alias' => 'Supports shared alias resolution during JavaScript linting.',
        ];

        return $map[$dependency_name] ?? 'Code package used by the active theme stack for front-end behavior or build tooling.';
    }
}

if (!function_exists('meza_site_documentation_get_code_dependency_rows')) {
    function meza_site_documentation_get_code_dependency_rows(): array
    {
        $rows = [];
        $theme_directories = array_values(array_unique(array_filter([
            get_stylesheet_directory(),
            get_template_directory(),
        ])));

        foreach ($theme_directories as $directory) {
            $package_path = trailingslashit($directory) . 'package.json';

            if (!file_exists($package_path)) {
                continue;
            }

            $package_data = json_decode((string) file_get_contents($package_path), true);
            if (!is_array($package_data)) {
                continue;
            }

            $stylesheet = basename($directory);
            $theme = wp_get_theme($stylesheet);
            $group_label = $theme instanceof WP_Theme && $theme->exists()
                ? trim((string) $theme->get('Name'))
                : ucwords(str_replace(['-', '_'], ' ', $stylesheet));

            $dependency_sets = [
                'Runtime' => (array) ($package_data['dependencies'] ?? []),
                'Build' => (array) ($package_data['devDependencies'] ?? []),
            ];

            foreach ($dependency_sets as $type_label => $dependencies) {
                foreach ($dependencies as $dependency_name => $version) {
                    $dependency_name = trim((string) $dependency_name);
                    $version = trim((string) $version);

                    if ($dependency_name === '' || $version === '') {
                        continue;
                    }

                    $rows[] = [
                        'key' => sanitize_key($group_label . '_' . $type_label . '_' . $dependency_name),
                        'group' => $group_label,
                        'label' => $dependency_name,
                        'version' => $version,
                        'type' => $type_label,
                        'description' => meza_site_documentation_code_dependency_description($dependency_name),
                    ];
                }
            }
        }

        usort($rows, static function (array $left, array $right): int {
            $group_compare = strnatcasecmp((string) ($left['group'] ?? ''), (string) ($right['group'] ?? ''));

            if ($group_compare !== 0) {
                return $group_compare;
            }

            $type_weights = [
                'Runtime' => 10,
                'Build' => 20,
            ];

            $left_weight = $type_weights[(string) ($left['type'] ?? '')] ?? 50;
            $right_weight = $type_weights[(string) ($right['type'] ?? '')] ?? 50;

            if ($left_weight !== $right_weight) {
                return $left_weight <=> $right_weight;
            }

            return strnatcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return $rows;
    }
}

if (!function_exists('meza_site_documentation_default_analytics_field_rows')) {
    function meza_site_documentation_default_analytics_field_rows(): array
    {
        $defaults = [];

        foreach (meza_site_documentation_analytics_registry() as $row_key => $row) {
            if (!is_array($row)) {
                continue;
            }

            $defaults[] = [
                'item_key' => sanitize_key((string) $row_key),
                'description' => trim((string) ($row['description'] ?? '')),
                'access_entity' => trim((string) ($row['default_access_entity'] ?? '')),
                'importance' => sanitize_key(str_replace(' ', '_', strtolower(trim((string) ($row['importance'] ?? ''))))),
                'note' => trim((string) ($row['note'] ?? '')),
            ];
        }

        return $defaults;
    }
}

if (!function_exists('meza_site_documentation_merge_analytics_rows')) {
    function meza_site_documentation_merge_analytics_rows(array $defaults, $saved_rows): array
    {
        $merged = [];
        $saved_lookup = [];

        if (is_array($saved_rows)) {
            foreach ($saved_rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $key = sanitize_key((string) ($row['item_key'] ?? ''));
                if ($key !== '') {
                    $saved_lookup[$key] = $row;
                }
            }
        }

        foreach ($defaults as $row) {
            if (!is_array($row)) {
                continue;
            }

            $key = sanitize_key((string) ($row['item_key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $merged[] = isset($saved_lookup[$key]) && is_array($saved_lookup[$key])
                ? array_merge($row, $saved_lookup[$key])
                : $row;

            unset($saved_lookup[$key]);
        }

        foreach ($saved_lookup as $row) {
            if (is_array($row)) {
                $merged[] = $row;
            }
        }

        return $merged;
    }
}

if (!function_exists('meza_site_documentation_registry')) {
    function meza_site_documentation_registry(): array
    {
        return [
            'capabilities' => meza_site_documentation_capabilities_registry(),
            'dashboards' => meza_site_documentation_dashboards_registry(),
            'tools' => meza_site_documentation_tools_registry(),
            'analytics' => meza_site_documentation_analytics_registry(),
            'plugins' => meza_site_documentation_plugins_registry(),
            'themes' => meza_site_documentation_themes_registry(),
        ];
    }
}

if (!function_exists('meza_site_documentation_resolve_role_list')) {
    function meza_site_documentation_resolve_role_list(array $row): array
    {
        $available_roles = meza_site_documentation_active_access_roles();
        $resolved = [];

        foreach ((array) ($row['roles'] ?? []) as $role_key) {
            $role_key = sanitize_key((string) $role_key);
            if ($role_key !== '' && array_key_exists($role_key, $available_roles)) {
                $resolved[$role_key] = $available_roles[$role_key];
            }
        }

        foreach ((array) ($row['access'] ?? []) as $role_key => $label) {
            $role_key = sanitize_key((string) $role_key);
            if ($role_key === '' || trim((string) $label) === '') {
                continue;
            }

            if (array_key_exists($role_key, $available_roles)) {
                $resolved[$role_key] = $available_roles[$role_key];
            }
        }

        return $resolved;
    }
}

if (!function_exists('meza_site_documentation_should_include_row')) {
    function meza_site_documentation_should_include_row(array $row, string $section_key = ''): bool
    {
        $is_available = meza_site_documentation_is_dependency_available($row);

        if ($section_key === 'plugins') {
            return meza_site_documentation_is_plugin_row_active($row);
        }

        if ($section_key !== '' && meza_site_documentation_uses_document_baseline($section_key)) {
            if (!empty($row['hide_when_dependency_missing'])) {
                return $is_available;
            }

            return true;
        }

        return $is_available;
    }
}

if (!function_exists('meza_site_documentation_get_resolved_rows')) {
    function meza_site_documentation_get_resolved_rows(string $section_key): array
    {
        $registry = meza_site_documentation_registry();
        $rows = $registry[$section_key] ?? [];
        $resolved = [];
        $analytics_overrides = $section_key === 'analytics'
            ? meza_site_documentation_get_analytics_account_overrides()
            : [];

        foreach ($rows as $row_key => $row) {
            if (!is_array($row)) {
                continue;
            }

            $row['key'] = sanitize_key((string) $row_key);

            if (!meza_site_documentation_should_include_row($row, $section_key)) {
                continue;
            }

            $row['is_available'] = meza_site_documentation_is_dependency_available($row);
            $row['role_labels'] = meza_site_documentation_resolve_role_list($row);
            $row['roles_text'] = implode(', ', array_values($row['role_labels']));
            $row['access'] = array_map(static function ($label): string {
                return meza_site_documentation_format_access_label((string) $label);
            }, (array) ($row['access'] ?? []));
            $row['action_links'] = meza_site_documentation_get_row_action_links($section_key, $row);

            if ($section_key === 'analytics') {
                $analytics_override = $analytics_overrides[$row['key']] ?? [];
                $dynamic_access_email = meza_site_documentation_analytics_access_email();

                if (array_key_exists('description', $analytics_override)) {
                    $row['description'] = trim((string) $analytics_override['description']);
                }
                if ($row['key'] === 'pagespeed_insights') {
                    $row['access_entity'] = 'Public';
                    $row['access_email'] = '';
                } else {
                    $row['access_entity'] = $dynamic_access_email !== ''
                        ? $dynamic_access_email
                        : trim((string) ($analytics_override['access_entity'] ?? $row['default_access_entity'] ?? ''));
                    $row['access_email'] = $dynamic_access_email !== ''
                        ? $dynamic_access_email
                        : '';
                }
                if (array_key_exists('note', $analytics_override)) {
                    $row['note'] = trim((string) $analytics_override['note']);
                }
                if (!empty($analytics_override['importance'])) {
                    $row['importance'] = ucwords(str_replace('_', ' ', (string) $analytics_override['importance']));
                }
            }

            $resolved[] = $row;
        }

        return $resolved;
    }
}

if (!function_exists('meza_site_documentation_get_capability_grants_by_role')) {
    function meza_site_documentation_get_capability_grants_by_role(): array
    {
        $roles = array_fill_keys(array_keys(meza_site_documentation_contact_roles()), []);

        foreach (meza_site_documentation_registry() as $rows) {
            foreach ($rows as $row) {
                if (!is_array($row) || empty($row['capability'])) {
                    continue;
                }

                $capability = sanitize_key((string) $row['capability']);
                if ($capability === '') {
                    continue;
                }

                foreach (array_keys(meza_site_documentation_resolve_role_list($row)) as $role_key) {
                    if (!isset($roles[$role_key])) {
                        $roles[$role_key] = [];
                    }

                    $roles[$role_key][$capability] = true;
                }
            }
        }

        return $roles;
    }
}

if (!function_exists('meza_site_documentation_site_manager_caps')) {
    function meza_site_documentation_site_manager_caps(): array
    {
        $grants = meza_site_documentation_get_capability_grants_by_role();
        return $grants[meza_site_documentation_site_manager_role_key()] ?? [];
    }
}

if (!function_exists('meza_site_documentation_seo_manager_caps')) {
    function meza_site_documentation_seo_manager_caps(): array
    {
        $grants = meza_site_documentation_get_capability_grants_by_role();
        return $grants[meza_site_documentation_seo_manager_role_key()] ?? [];
    }
}

if (!function_exists('meza_site_documentation_administrator_caps')) {
    function meza_site_documentation_administrator_caps(): array
    {
        $grants = meza_site_documentation_get_capability_grants_by_role();
        return $grants['administrator'] ?? [];
    }
}
