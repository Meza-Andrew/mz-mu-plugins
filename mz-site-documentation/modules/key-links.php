<?php

if (!function_exists('meza_site_documentation_style_guide_url')) {
    function meza_site_documentation_style_guide_url(): string
    {
        $selected_url = meza_site_documentation_find_page_url_by_option('meza_page_for_style_guide');

        if ($selected_url !== '') {
            return $selected_url;
        }

        return meza_site_documentation_find_page_url_by_template('page-style.php');
    }
}

if (!function_exists('meza_site_documentation_url')) {
    function meza_site_documentation_url(): string
    {
        $selected_url = meza_site_documentation_find_page_url_by_option('meza_page_for_documentation');

        if ($selected_url !== '') {
            return $selected_url;
        }

        return meza_site_documentation_find_page_url_by_template('page-documentation.php');
    }
}

if (!function_exists('meza_site_documentation_analytics_access_email')) {
    function meza_site_documentation_analytics_access_email(): string
    {
        $administrator = meza_site_documentation_find_first_user_for_role('administrator');

        if ($administrator instanceof WP_User && (string) $administrator->user_login === 'ameza') {
            return sanitize_email((string) $administrator->user_email);
        }

        $site_manager = meza_site_documentation_find_first_user_for_role(meza_site_documentation_site_manager_role_key());

        if ($site_manager instanceof WP_User) {
            return sanitize_email((string) $site_manager->user_email);
        }

        if ($administrator instanceof WP_User) {
            return sanitize_email((string) $administrator->user_email);
        }

        return '';
    }
}

if (!function_exists('meza_site_documentation_get_key_links')) {
    function meza_site_documentation_get_key_links(): array
    {
        $links = [
            'live_url' => [
                'label' => 'Live URL',
                'url' => home_url('/'),
            ],
            'admin_login' => [
                'label' => 'Admin Login',
                'url' => admin_url(),
            ],
            'sitemaps' => [
                'label' => 'Sitemaps',
                'url' => meza_site_documentation_is_plugin_active('wordpress-seo/wp-seo.php')
                    ? home_url('/sitemap.xml')
                    : '',
            ],
            'documentation' => [
                'label' => 'Documentation',
                'url' => meza_site_documentation_url(),
            ],
            'style_guide' => [
                'label' => 'Style Guide',
                'url' => meza_site_documentation_style_guide_url(),
            ],
        ];

        return array_values(array_filter($links, static function ($row): bool {
            return !empty($row['url']);
        }));
    }
}

if (!function_exists('meza_site_documentation_get_section_copy')) {
    function meza_site_documentation_get_section_copy(): array
    {
        $defaults = meza_site_documentation_default_section_copy();

        return [
            'key_links_intro' => (string) ($defaults['site_documentation_key_links_intro'] ?? ''),
            'users_and_roles_intro' => (string) ($defaults['site_documentation_users_and_roles_intro'] ?? ''),
            'content_structure_intro' => (string) ($defaults['site_documentation_content_structure_intro'] ?? ''),
            'content_intro' => (string) ($defaults['site_documentation_content_intro'] ?? ''),
            'indexed_urls_intro' => (string) ($defaults['site_documentation_indexed_urls_intro'] ?? ''),
            'content_only_intro' => (string) ($defaults['site_documentation_content_only_intro'] ?? ''),
            'content_types_intro' => (string) ($defaults['site_documentation_content_types_intro'] ?? ''),
            'page_templates_intro' => (string) ($defaults['site_documentation_page_templates_intro'] ?? ''),
            'section_templates_intro' => (string) ($defaults['site_documentation_section_templates_intro'] ?? ''),
            'management_capabilities_intro' => (string) ($defaults['site_documentation_management_capabilities_intro'] ?? ''),
            'tech_stack_intro' => (string) ($defaults['site_documentation_tech_stack_intro'] ?? ''),
            'code_dependencies_intro' => (string) ($defaults['site_documentation_code_dependencies_intro'] ?? ''),
            'plugins_intro' => (string) ($defaults['site_documentation_plugins_intro'] ?? ''),
            'themes_intro' => (string) ($defaults['site_documentation_themes_intro'] ?? ''),
        ];
    }
}

if (!function_exists('meza_site_documentation_get_analytics_account_overrides')) {
    function meza_site_documentation_get_analytics_account_overrides(): array
    {
        return [];
    }
}

if (!function_exists('meza_site_documentation_document_baseline_sections')) {
    function meza_site_documentation_document_baseline_sections(): array
    {
        return [
            'capabilities',
            'dashboards',
            'tools',
            'analytics',
            'plugins',
        ];
    }
}

if (!function_exists('meza_site_documentation_uses_document_baseline')) {
    function meza_site_documentation_uses_document_baseline(string $section_key): bool
    {
        return in_array($section_key, meza_site_documentation_document_baseline_sections(), true);
    }
}

if (!function_exists('meza_site_documentation_make_link')) {
    function meza_site_documentation_make_link(string $label, string $url, array $args = []): array
    {
        return array_merge([
            'label' => trim($label),
            'url' => trim($url),
        ], $args);
    }
}

if (!function_exists('meza_site_documentation_filter_links')) {
    function meza_site_documentation_filter_links(array $links): array
    {
        $filtered = [];

        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }

            $label = trim((string) ($link['label'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));

            if ($label === '' || $url === '') {
                continue;
            }

            if (function_exists('meza_site_documentation_current_user_can_access_link')
                && !meza_site_documentation_current_user_can_access_link($link)) {
                continue;
            }

            $filtered[] = [
                'label' => $label,
                'url' => $url,
            ];
        }

        return $filtered;
    }
}

if (!function_exists('meza_site_documentation_current_user_role_keys')) {
    function meza_site_documentation_current_user_role_keys(): array
    {
        if (!is_user_logged_in()) {
            return [];
        }

        $user = wp_get_current_user();

        if (!($user instanceof WP_User)) {
            return [];
        }

        return array_values(array_filter(array_map('sanitize_key', (array) $user->roles)));
    }
}

if (!function_exists('meza_site_documentation_current_user_is_administrator')) {
    function meza_site_documentation_current_user_is_administrator(): bool
    {
        return in_array('administrator', meza_site_documentation_current_user_role_keys(), true);
    }
}

if (!function_exists('meza_site_documentation_is_admin_link_url')) {
    function meza_site_documentation_is_admin_link_url(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        return str_starts_with($url, admin_url());
    }
}

if (!function_exists('meza_site_documentation_current_user_can_access_link')) {
    function meza_site_documentation_current_user_can_access_link(array $link): bool
    {
        $url = trim((string) ($link['url'] ?? ''));

        if ($url === '') {
            return false;
        }

        if (!meza_site_documentation_is_admin_link_url($url)) {
            return true;
        }

        if (!is_user_logged_in()) {
            return false;
        }

        if (meza_site_documentation_current_user_is_administrator()) {
            return true;
        }

        $capability = trim((string) ($link['capability'] ?? ''));
        $post_id = (int) ($link['post_id'] ?? 0);

        if ($capability !== '') {
            if ($post_id > 0 && in_array($capability, ['edit_post', 'delete_post', 'read_post'], true)) {
                return current_user_can($capability, $post_id);
            }

            return current_user_can($capability);
        }

        $allowed_roles = array_values(array_filter(array_map('sanitize_key', (array) ($link['allowed_roles'] ?? []))));

        if ($allowed_roles !== []) {
            return (bool) array_intersect($allowed_roles, meza_site_documentation_current_user_role_keys());
        }

        return false;
    }
}

if (!function_exists('meza_site_documentation_get_row_action_links')) {
    function meza_site_documentation_get_row_action_links(string $section_key, array $row): array
    {
        $row_key = sanitize_key((string) ($row['key'] ?? ''));

        if ($section_key === 'pages') {
            $post_id = (int) ($row['post_id'] ?? 0);
            $page = $post_id > 0 ? get_post($post_id) : null;
            $links = [
                meza_site_documentation_make_link('View', (string) ($row['url'] ?? '')),
            ];

            if ($page instanceof WP_Post && !meza_site_documentation_is_auto_template_page($page)) {
                $links[] = meza_site_documentation_make_link('Edit', admin_url('post.php?post=' . $post_id . '&action=edit'), [
                    'capability' => 'edit_post',
                    'post_id' => $post_id,
                ]);
            }

            return meza_site_documentation_filter_links($links);
        }

        if ($section_key === 'content_types') {
            return meza_site_documentation_filter_links([
                meza_site_documentation_make_link((string) ($row['action'] ?? 'Edit'), (string) ($row['action_url'] ?? ''), [
                    'capability' => (string) ($row['required_capability'] ?? ''),
                ]),
            ]);
        }

        if ($section_key === 'taxonomy_terms') {
            $links = [];

            $view_url = trim((string) ($row['url'] ?? ''));
            if ($view_url !== '') {
                $links[] = meza_site_documentation_make_link('View', $view_url);
            }

            $edit_url = trim((string) ($row['action_url'] ?? ''));
            if ($edit_url !== '') {
                $links[] = meza_site_documentation_make_link('Edit', $edit_url, [
                    'capability' => (string) ($row['required_capability'] ?? ''),
                ]);
            }

            return meza_site_documentation_filter_links($links);
        }

        $links = [];

        switch ($section_key) {
            case 'capabilities':
                $map = [
                    'update_wordpress' => admin_url('update-core.php'),
                    'manage_plugins' => admin_url('plugins.php'),
                    'manage_users' => admin_url('users.php'),
                    'unlock_users' => admin_url('admin.php?page=aiowpsec&tab=locked-ip&mz_nav=users'),
                    'update_site_information' => admin_url('admin.php?page=business-information'),
                    'update_site_branding' => admin_url('admin.php?page=branding'),
                    'manage_menus' => admin_url('nav-menus.php'),
                    'manage_redirects' => admin_url('tools.php?page=redirection.php'),
                    'manage_seo_settings' => admin_url('admin.php?page=wpseo_dashboard'),
                    'manage_analytics_settings' => admin_url('admin.php?page=googlesitekit-dashboard'),
                    'manage_security_settings' => admin_url('admin.php?page=aiowpsec'),
                    'manage_backups' => admin_url('options-general.php?page=updraftplus'),
                    'view_site_health' => admin_url('site-health.php'),
                    'manage_maintenance_mode' => admin_url('admin.php?page=aiowpsec&tab=visitor-lockout'),
                ];

                $links[] = meza_site_documentation_make_link((string) ($row['action'] ?? 'Manage'), (string) ($map[$row_key] ?? ''), [
                    'capability' => (string) ($row['capability'] ?? ''),
                ]);
                break;

            case 'dashboards':
                $map = [
                    'wordpress' => admin_url('index.php'),
                    'google_site_kit' => admin_url('admin.php?page=googlesitekit-dashboard'),
                    'security' => admin_url('admin.php?page=aiowpsec'),
                ];

                $links[] = meza_site_documentation_make_link((string) ($row['action'] ?? 'View'), (string) ($map[$row_key] ?? ''), [
                    'capability' => (string) ($row['capability'] ?? ''),
                ]);
                break;

            case 'tools':
                $map = [
                    'import' => admin_url('import.php'),
                    'export' => admin_url('export.php'),
                    'seo_bulk_edit' => admin_url('admin.php?page=wpseo_bulk-editor'),
                    'two_factor_authentication' => admin_url('admin.php?page=aiowpsec_two_factor_auth_user'),
                    'password_strength' => admin_url('profile.php'),
                    'file_scan' => admin_url('admin.php?page=aiowpsec&tab=file-change-detect'),
                ];

                $links[] = meza_site_documentation_make_link((string) ($row['action'] ?? 'Use'), (string) ($map[$row_key] ?? ''), [
                    'capability' => (string) ($row['capability'] ?? ''),
                ]);
                break;

            case 'analytics':
                if ($row_key === 'pagespeed_insights') {
                    $links[] = meza_site_documentation_make_link('View', 'https://pagespeed.web.dev/analysis?url=' . rawurlencode(home_url('/')));
                } else {
                    $links[] = meza_site_documentation_make_link((string) ($row['action'] ?? 'View'), admin_url('admin.php?page=googlesitekit-dashboard'), [
                        'capability' => (string) ($row['capability'] ?? ''),
                    ]);
                }
                break;

            case 'plugins':
                $map = [
                    'acf_content_analysis' => admin_url('plugins.php'),
                    'admin_columns' => admin_url('options-general.php?page=codepress-admin-columns'),
                    'admin_menu_editor' => admin_url('options-general.php?page=menu_editor'),
                    'advanced_custom_fields_pro' => admin_url('edit.php?post_type=acf-field-group'),
                    'aios' => admin_url('admin.php?page=aiowpsec'),
                    'classic_editor' => admin_url('options-writing.php'),
                    'intuitive_custom_post_order' => admin_url('settings.php?page=intuitive-cpo'),
                    'post_duplicator' => admin_url('plugins.php'),
                    'redirection' => admin_url('tools.php?page=redirection.php'),
                    'site_kit' => admin_url('admin.php?page=googlesitekit-dashboard'),
                    'sqlite_object_cache' => admin_url('plugins.php'),
                    'updraftplus' => admin_url('options-general.php?page=updraftplus'),
                    'wordpress_importer' => admin_url('admin.php?import=wordpress'),
                    'yoast_seo' => admin_url('admin.php?page=wpseo_dashboard'),
                ];

                $label = trim((string) ($row['action'] ?? ''));
                if ($label === '') {
                    $label = 'View';
                }

                $links[] = meza_site_documentation_make_link($label, (string) ($map[$row_key] ?? admin_url('plugins.php')), [
                    'capability' => (string) ($row['capability'] ?? ''),
                ]);
                break;

            case 'themes':
                $links[] = meza_site_documentation_make_link((string) ($row['action'] ?? 'View'), admin_url('themes.php'), [
                    'capability' => (string) ($row['capability'] ?? ''),
                ]);
                break;
        }

        return meza_site_documentation_filter_links($links);
    }
}

