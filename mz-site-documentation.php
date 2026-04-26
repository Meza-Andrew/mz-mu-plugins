<?php

/**
 * Shared site documentation data, ACF options, and resolver helpers.
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) {
    return;
}

if (!function_exists('meza_site_documentation_option_page_slug')) {
    function meza_site_documentation_option_page_slug(): string
    {
        return 'site-documentation';
    }
}

if (!function_exists('meza_site_documentation_site_manager_role_key')) {
    function meza_site_documentation_site_manager_role_key(): string
    {
        return function_exists('meza_site_manager_role_key')
            ? meza_site_manager_role_key()
            : 'site_manager';
    }
}

if (!function_exists('meza_site_documentation_seo_manager_role_key')) {
    function meza_site_documentation_seo_manager_role_key(): string
    {
        return function_exists('meza_seo_manager_role_key')
            ? meza_seo_manager_role_key()
            : 'wpseo_manager';
    }
}

if (!function_exists('meza_site_documentation_contact_roles')) {
    function meza_site_documentation_contact_roles(): array
    {
        return [
            'administrator' => 'Administrator',
            meza_site_documentation_site_manager_role_key() => 'Site Manager',
            meza_site_documentation_seo_manager_role_key() => 'SEO Manager',
        ];
    }
}

if (!function_exists('meza_site_documentation_access_roles')) {
    function meza_site_documentation_access_roles(): array
    {
        return [
            meza_site_documentation_seo_manager_role_key() => 'SEO Manager',
            meza_site_documentation_site_manager_role_key() => 'Site Manager',
            'administrator' => 'Administrator',
        ];
    }
}

if (!function_exists('meza_site_documentation_active_role_keys')) {
    function meza_site_documentation_active_role_keys(): array
    {
        $active = [];

        foreach (meza_site_documentation_contact_roles() as $role_key => $role_label) {
            $user = meza_site_documentation_find_first_user_for_role($role_key);
            if ($user instanceof WP_User) {
                $active[$role_key] = true;
            }
        }

        return array_keys($active);
    }
}

if (!function_exists('meza_site_documentation_active_access_roles')) {
    function meza_site_documentation_active_access_roles(): array
    {
        $active_role_keys = array_fill_keys(meza_site_documentation_active_role_keys(), true);
        $roles = [];

        foreach (meza_site_documentation_access_roles() as $role_key => $role_label) {
            if (!isset($active_role_keys[$role_key])) {
                continue;
            }

            $roles[$role_key] = $role_label;
        }

        return $roles;
    }
}

if (!function_exists('meza_site_documentation_role_choices')) {
    function meza_site_documentation_role_choices(): array
    {
        return meza_site_documentation_contact_roles();
    }
}

if (!function_exists('meza_site_documentation_role_has_capability')) {
    function meza_site_documentation_role_has_capability(string $role_key, string $capability): bool
    {
        $role_key = sanitize_key($role_key);
        $capability = trim($capability);

        if ($role_key === '' || $capability === '') {
            return false;
        }

        $role = get_role($role_key);

        return $role instanceof WP_Role && $role->has_cap($capability);
    }
}

if (!function_exists('meza_site_documentation_role_labels_for_capability')) {
    function meza_site_documentation_role_labels_for_capability(string $capability): array
    {
        $labels = [];

        foreach (meza_site_documentation_active_access_roles() as $role_key => $role_label) {
            if (meza_site_documentation_role_has_capability($role_key, $capability)) {
                $labels[$role_key] = $role_label;
            }
        }

        return $labels;
    }
}

if (!function_exists('meza_site_documentation_format_role_labels')) {
    function meza_site_documentation_format_role_labels(array $labels): string
    {
        return implode(', ', array_values(array_filter(array_map('strval', $labels))));
    }
}

if (!function_exists('meza_site_documentation_support_email')) {
    function meza_site_documentation_support_email(): string
    {
        $email = function_exists('get_field')
            ? sanitize_email((string) get_field('site_documentation_support_email', 'option'))
            : '';

        return $email !== '' ? $email : 'wordpress@meza.design';
    }
}

if (!function_exists('meza_site_documentation_field_text')) {
    function meza_site_documentation_field_text(string $field_name, string $default = ''): string
    {
        if (!function_exists('get_field')) {
            return $default;
        }

        $value = get_field($field_name, 'option');
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('meza_site_documentation_find_first_user_for_role')) {
    function meza_site_documentation_find_first_user_for_role(string $role_key): ?WP_User
    {
        $users = get_users([
            'role' => $role_key,
            'number' => 1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        return !empty($users[0]) && $users[0] instanceof WP_User
            ? $users[0]
            : null;
    }
}

if (!function_exists('meza_site_documentation_get_role_contact_overrides')) {
    function meza_site_documentation_get_role_contact_overrides(): array
    {
        if (!function_exists('get_field')) {
            return [];
        }

        $rows = get_field('site_documentation_role_contacts', 'option');
        if (!is_array($rows)) {
            return [];
        }

        $overrides = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $role_key = sanitize_key((string) ($row['role'] ?? ''));
            if ($role_key === '') {
                continue;
            }

            $overrides[$role_key] = [
                'name' => trim((string) ($row['name'] ?? '')),
                'user_login' => trim((string) ($row['username'] ?? '')),
                'email' => sanitize_email((string) ($row['email'] ?? '')),
                'note' => trim((string) ($row['note'] ?? '')),
            ];
        }

        return $overrides;
    }
}

if (!function_exists('meza_site_documentation_get_role_contacts')) {
    function meza_site_documentation_get_role_contacts(): array
    {
        $overrides = meza_site_documentation_get_role_contact_overrides();
        $rows = [];

        foreach (meza_site_documentation_active_access_roles() as $role_key => $role_label) {
            $override = $overrides[$role_key] ?? [];
            $user = meza_site_documentation_find_first_user_for_role($role_key);

            $name = trim((string) ($override['name'] ?? ''));
            if ($name === '' && $user instanceof WP_User) {
                $name = trim((string) ($user->display_name ?: $user->user_nicename));
            }

            $user_login = trim((string) ($override['user_login'] ?? ''));
            if ($user_login === '' && $user instanceof WP_User) {
                $user_login = trim((string) $user->user_login);
            }

            $email = sanitize_email((string) ($override['email'] ?? ''));
            if ($email === '' && $user instanceof WP_User) {
                $email = sanitize_email((string) $user->user_email);
            }

            $note = trim((string) ($override['note'] ?? ''));

            if ($name === '' && $user_login === '' && $email === '' && $note === '') {
                continue;
            }

            $rows[] = [
                'role' => $role_label,
                'name' => $name,
                'user_login' => $user_login,
                'email' => $email,
                'note' => $note,
            ];
        }

        return $rows;
    }
}

if (!function_exists('meza_site_documentation_style_guide_url')) {
    function meza_site_documentation_style_guide_url(): string
    {
        $page = get_page_by_path('styles');

        if ($page instanceof WP_Post) {
            $url = get_permalink($page);
            return is_string($url) ? $url : '';
        }

        return home_url('/styles');
    }
}

if (!function_exists('meza_site_documentation_get_key_links')) {
    function meza_site_documentation_get_key_links(): array
    {
        $links = [
            'live_url' => [
                'label' => 'Live URL',
                'url' => meza_site_documentation_field_text('site_documentation_live_url', home_url('/')),
            ],
            'admin_login' => [
                'label' => 'Admin Login',
                'url' => meza_site_documentation_field_text('site_documentation_admin_url', admin_url()),
            ],
            'sitemaps' => [
                'label' => 'Sitemaps',
                'url' => meza_site_documentation_field_text('site_documentation_sitemap_url', home_url('/sitemap.xml')),
            ],
            'style_guide' => [
                'label' => 'Style Guide',
                'url' => meza_site_documentation_field_text('site_documentation_style_guide_url', meza_site_documentation_style_guide_url()),
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
        return [
            'overview_intro' => meza_site_documentation_field_text(
                'site_documentation_overview_intro',
                'This private page documents the website tools, maintenance responsibilities, and core platform components used on the site.'
            ),
            'support_note' => meza_site_documentation_field_text(
                'site_documentation_support_note',
                sprintf(
                    'Technical administrative issues can be sent to %s for Meza support.',
                    meza_site_documentation_support_email()
                )
            ),
            'quick_access_intro' => meza_site_documentation_field_text(
                'site_documentation_quick_access_intro',
                'Use these links and role assignments to find the website, sign in, and confirm who owns each website-facing responsibility.'
            ),
            'content_management_intro' => meza_site_documentation_field_text(
                'site_documentation_content_management_intro',
                'This section outlines the core public-facing content that lives in WordPress and which primary roles can maintain it.'
            ),
            'pages_intro' => meza_site_documentation_field_text(
                'site_documentation_pages_intro',
                'Published pages are pulled directly from WordPress and grouped into a documentation-friendly structure.'
            ),
            'content_types_intro' => meza_site_documentation_field_text(
                'site_documentation_content_types_intro',
                'Post types and taxonomies are pulled from the registered WordPress content model and mapped to role access from real capabilities.'
            ),
            'capabilities_intro' => meza_site_documentation_field_text(
                'site_documentation_capabilities_intro',
                'These are the maintenance capabilities available on the website and the level of access each primary role has to them.'
            ),
            'dashboards_intro' => meza_site_documentation_field_text(
                'site_documentation_dashboards_intro',
                'These dashboards provide day-to-day visibility into the website, SEO, performance, and security posture.'
            ),
            'tools_intro' => meza_site_documentation_field_text(
                'site_documentation_tools_intro',
                'These tools are used to manage content imports and exports, SEO workflows, and security-related account tasks.'
            ),
            'analytics_intro' => meza_site_documentation_field_text(
                'site_documentation_analytics_intro',
                'These analytics platforms measure traffic, search visibility, and performance signals connected to the website.'
            ),
            'plugins_intro' => meza_site_documentation_field_text(
                'site_documentation_plugins_intro',
                'These are the preferred plugins currently powering the website, along with their relative importance to the stack.'
            ),
            'themes_intro' => meza_site_documentation_field_text(
                'site_documentation_themes_intro',
                'These themes make up the active front-end stack and any supporting themes kept available for maintenance or debugging.'
            ),
        ];
    }
}

if (!function_exists('meza_site_documentation_get_row_overrides')) {
    function meza_site_documentation_get_row_overrides(): array
    {
        if (!function_exists('get_field')) {
            return [];
        }

        $rows = get_field('site_documentation_row_overrides', 'option');
        if (!is_array($rows)) {
            return [];
        }

        $overrides = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $section_key = sanitize_key((string) ($row['section_key'] ?? ''));
            $row_key = sanitize_key((string) ($row['row_key'] ?? ''));

            if ($section_key === '' || $row_key === '') {
                continue;
            }

            $overrides[$section_key][$row_key] = [
                'visibility' => sanitize_key((string) ($row['visibility'] ?? 'default')),
                'note' => trim((string) ($row['note'] ?? '')),
                'importance' => sanitize_key((string) ($row['importance'] ?? '')),
            ];
        }

        return $overrides;
    }
}

if (!function_exists('meza_site_documentation_get_analytics_account_overrides')) {
    function meza_site_documentation_get_analytics_account_overrides(): array
    {
        if (!function_exists('get_field')) {
            return [];
        }

        $rows = get_field('site_documentation_analytics_accounts', 'option');
        if (!is_array($rows)) {
            return [];
        }

        $overrides = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $item_key = sanitize_key((string) ($row['item_key'] ?? ''));
            if ($item_key === '') {
                continue;
            }

            $overrides[$item_key] = [
                'access_entity' => trim((string) ($row['access_entity'] ?? '')),
                'note' => trim((string) ($row['note'] ?? '')),
                'importance' => sanitize_key((string) ($row['importance'] ?? '')),
            ];
        }

        return $overrides;
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
            'themes',
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
    function meza_site_documentation_make_link(string $label, string $url): array
    {
        return [
            'label' => trim($label),
            'url' => trim($url),
        ];
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

            $filtered[] = [
                'label' => $label,
                'url' => $url,
            ];
        }

        return $filtered;
    }
}

if (!function_exists('meza_site_documentation_get_row_action_links')) {
    function meza_site_documentation_get_row_action_links(string $section_key, array $row): array
    {
        $row_key = sanitize_key((string) ($row['key'] ?? ''));

        if ($section_key === 'pages') {
            $post_id = (int) ($row['post_id'] ?? 0);

            return meza_site_documentation_filter_links([
                meza_site_documentation_make_link('View', (string) ($row['url'] ?? '')),
                meza_site_documentation_make_link('Edit', $post_id > 0 ? admin_url('post.php?post=' . $post_id . '&action=edit') : ''),
            ]);
        }

        if ($section_key === 'content_types') {
            return meza_site_documentation_filter_links([
                meza_site_documentation_make_link((string) ($row['action'] ?? 'Edit'), (string) ($row['action_url'] ?? '')),
            ]);
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

                $links[] = meza_site_documentation_make_link((string) ($row['action'] ?? 'Manage'), (string) ($map[$row_key] ?? ''));
                break;

            case 'dashboards':
                $map = [
                    'wordpress' => admin_url('index.php'),
                    'google_site_kit' => admin_url('admin.php?page=googlesitekit-dashboard'),
                    'security' => admin_url('admin.php?page=aiowpsec'),
                ];

                $links[] = meza_site_documentation_make_link((string) ($row['action'] ?? 'View'), (string) ($map[$row_key] ?? ''));
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

                $links[] = meza_site_documentation_make_link((string) ($row['action'] ?? 'Use'), (string) ($map[$row_key] ?? ''));
                break;

            case 'analytics':
                if ($row_key === 'pagespeed_insights') {
                    $links[] = meza_site_documentation_make_link('View', 'https://pagespeed.web.dev/analysis?url=' . rawurlencode(home_url('/')));
                } else {
                    $links[] = meza_site_documentation_make_link((string) ($row['action'] ?? 'View'), admin_url('admin.php?page=googlesitekit-dashboard'));
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
                    'wordpress_importer' => admin_url('import.php'),
                    'yoast_seo' => admin_url('admin.php?page=wpseo_dashboard'),
                ];

                $label = trim((string) ($row['action'] ?? ''));
                if ($label === '') {
                    $label = 'View';
                }

                $links[] = meza_site_documentation_make_link($label, (string) ($map[$row_key] ?? admin_url('plugins.php')));
                break;

            case 'themes':
                $links[] = meza_site_documentation_make_link((string) ($row['action'] ?? 'View'), admin_url('themes.php'));
                break;
        }

        return meza_site_documentation_filter_links($links);
    }
}

if (!function_exists('meza_site_documentation_page_group_label')) {
    function meza_site_documentation_page_group_label(WP_Post $page): string
    {
        $front_page_id = (int) get_option('page_on_front');
        $posts_page_id = (int) get_option('page_for_posts');
        $slug = sanitize_title((string) $page->post_name);
        $title = strtolower(trim((string) $page->post_title));

        if ($page->ID === $front_page_id) {
            return 'Homepage';
        }

        if ($page->ID === $posts_page_id) {
            return 'Blog';
        }

        foreach (['thank-you', 'thank_you', 'thankyou'] as $needle) {
            if (str_contains($slug, $needle) || str_contains($title, str_replace('-', ' ', $needle))) {
                return 'Thank You Pages';
            }
        }

        foreach (['privacy', 'cookie', 'terms', 'conditions', 'legal', 'accessibility', 'disclaimer'] as $needle) {
            if (str_contains($slug, $needle) || str_contains($title, $needle)) {
                return 'Legal Pages';
            }
        }

        foreach (['contact', 'estimate', 'booking', 'book', 'apply', 'provider', 'form', 'consultation'] as $needle) {
            if (str_contains($slug, $needle) || str_contains($title, $needle)) {
                return 'Form Pages';
            }
        }

        return 'Pages';
    }
}

if (!function_exists('meza_site_documentation_page_group_weight')) {
    function meza_site_documentation_page_group_weight(string $group_label): int
    {
        $weights = [
            'Homepage' => 10,
            'Blog' => 20,
            'Form Pages' => 30,
            'Thank You Pages' => 40,
            'Legal Pages' => 50,
            'Pages' => 60,
        ];

        return $weights[$group_label] ?? 999;
    }
}

if (!function_exists('meza_site_documentation_get_page_rows')) {
    function meza_site_documentation_get_page_rows(): array
    {
        $pages = get_pages([
            'post_status' => 'publish',
            'sort_column' => 'menu_order,post_title',
            'sort_order' => 'ASC',
        ]);

        $role_labels = meza_site_documentation_role_labels_for_capability('edit_pages');
        $rows = [];

        foreach ($pages as $page) {
            if (!($page instanceof WP_Post)) {
                continue;
            }

            $row = [
                'key' => $page->post_name !== '' ? sanitize_key((string) $page->post_name) : 'page_' . (int) $page->ID,
                'post_id' => (int) $page->ID,
                'group' => meza_site_documentation_page_group_label($page),
                'title' => trim((string) $page->post_title),
                'url' => get_permalink($page),
                'roles_text' => meza_site_documentation_format_role_labels($role_labels),
                'action' => 'View / Edit',
                'menu_order' => (int) $page->menu_order,
            ];

            $row = meza_site_documentation_apply_row_override('pages', $row['key'], $row);

            if (!meza_site_documentation_should_include_row($row, 'pages')) {
                continue;
            }

            $row['action_links'] = meza_site_documentation_get_row_action_links('pages', $row);
            $rows[] = $row;
        }

        usort($rows, static function (array $left, array $right): int {
            $left_group_weight = meza_site_documentation_page_group_weight((string) ($left['group'] ?? ''));
            $right_group_weight = meza_site_documentation_page_group_weight((string) ($right['group'] ?? ''));

            if ($left_group_weight !== $right_group_weight) {
                return $left_group_weight <=> $right_group_weight;
            }

            $left_menu_order = (int) ($left['menu_order'] ?? 0);
            $right_menu_order = (int) ($right['menu_order'] ?? 0);

            if ($left_menu_order !== $right_menu_order) {
                return $left_menu_order <=> $right_menu_order;
            }

            return strnatcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });

        return $rows;
    }
}

if (!function_exists('meza_site_documentation_is_documented_post_type')) {
    function meza_site_documentation_is_documented_post_type(WP_Post_Type $post_type_object): bool
    {
        $post_type = (string) $post_type_object->name;

        if (in_array($post_type, [
            'attachment',
            'revision',
            'nav_menu_item',
            'custom_css',
            'customize_changeset',
            'oembed_cache',
            'user_request',
            'wp_block',
            'wp_template',
            'wp_template_part',
            'wp_global_styles',
            'wp_navigation',
            'acf-field-group',
            'acf-field',
            'acf-post-type',
            'acf-taxonomy',
            'acf-ui-options-page',
            'mzf_submission',
        ], true)) {
            return false;
        }

        if (!$post_type_object->show_ui) {
            return false;
        }

        return $post_type_object->public || in_array($post_type, ['page', 'post'], true);
    }
}

if (!function_exists('meza_site_documentation_is_documented_taxonomy')) {
    function meza_site_documentation_is_documented_taxonomy(WP_Taxonomy $taxonomy): bool
    {
        if (in_array((string) $taxonomy->name, ['post_format', 'nav_menu', 'link_category'], true)) {
            return false;
        }

        return $taxonomy->show_ui && $taxonomy->public;
    }
}

if (!function_exists('meza_site_documentation_get_content_type_rows')) {
    function meza_site_documentation_get_content_type_rows(): array
    {
        $post_type_objects = get_post_types([], 'objects');
        $rows = [];

        foreach ($post_type_objects as $post_type_object) {
            if (!($post_type_object instanceof WP_Post_Type) || !meza_site_documentation_is_documented_post_type($post_type_object)) {
                continue;
            }

            $post_type = (string) $post_type_object->name;
            $edit_capability = isset($post_type_object->cap->edit_posts)
                ? (string) $post_type_object->cap->edit_posts
                : '';

            $post_type_row = [
                'key' => sanitize_key($post_type),
                'group' => (string) ($post_type_object->labels->name ?? ucfirst($post_type)),
                'item' => (string) ($post_type_object->labels->name ?? ucfirst($post_type)),
                'roles_text' => meza_site_documentation_format_role_labels(
                    meza_site_documentation_role_labels_for_capability($edit_capability)
                ),
                'action' => 'Edit',
                'kind' => 'post_type',
                'action_url' => admin_url('edit.php?post_type=' . $post_type),
            ];

            $post_type_row = meza_site_documentation_apply_row_override('content_types', $post_type_row['key'], $post_type_row);

            if (meza_site_documentation_should_include_row($post_type_row, 'content_types')) {
                $post_type_row['action_links'] = meza_site_documentation_get_row_action_links('content_types', $post_type_row);
                $rows[] = $post_type_row;
            }

            $taxonomies = get_object_taxonomies($post_type, 'objects');
            foreach ($taxonomies as $taxonomy) {
                if (!($taxonomy instanceof WP_Taxonomy) || !meza_site_documentation_is_documented_taxonomy($taxonomy)) {
                    continue;
                }

                $taxonomy_capability = isset($taxonomy->cap->manage_terms) && is_string($taxonomy->cap->manage_terms) && $taxonomy->cap->manage_terms !== ''
                    ? $taxonomy->cap->manage_terms
                    : (string) ($taxonomy->cap->edit_terms ?? '');

                $taxonomy_row = [
                    'key' => sanitize_key($post_type . '_' . (string) $taxonomy->name),
                    'group' => (string) ($post_type_object->labels->name ?? ucfirst($post_type)),
                    'item' => (string) ($taxonomy->labels->name ?? ucfirst((string) $taxonomy->name)),
                    'roles_text' => meza_site_documentation_format_role_labels(
                        meza_site_documentation_role_labels_for_capability($taxonomy_capability)
                    ),
                    'action' => 'Edit',
                    'kind' => 'taxonomy',
                    'action_url' => admin_url('edit-tags.php?taxonomy=' . (string) $taxonomy->name . '&post_type=' . $post_type),
                ];

                $taxonomy_row = meza_site_documentation_apply_row_override('content_types', $taxonomy_row['key'], $taxonomy_row);

                if (meza_site_documentation_should_include_row($taxonomy_row, 'content_types')) {
                    $taxonomy_row['action_links'] = meza_site_documentation_get_row_action_links('content_types', $taxonomy_row);
                    $rows[] = $taxonomy_row;
                }
            }
        }

        usort($rows, static function (array $left, array $right): int {
            $group_compare = strnatcasecmp((string) ($left['group'] ?? ''), (string) ($right['group'] ?? ''));
            if ($group_compare !== 0) {
                return $group_compare;
            }

            $kind_compare = strnatcasecmp((string) ($left['kind'] ?? ''), (string) ($right['kind'] ?? ''));
            if ($kind_compare !== 0) {
                return $kind_compare;
            }

            return strnatcasecmp((string) ($left['item'] ?? ''), (string) ($right['item'] ?? ''));
        });

        return $rows;
    }
}

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
                    $site => 'Full access',
                    'administrator' => 'Full access',
                ],
                'action' => 'Manage',
            ],
            'manage_plugins' => [
                'label' => 'Manage Plugins',
                'description' => 'Add, activate, deactivate, and remove plugins used to enhance the website.',
                'capability' => 'meza_doc_manage_plugins',
                'access' => [
                    $seo => 'View access',
                    $site => 'Full access',
                    'administrator' => 'Full access',
                ],
                'action' => 'Manage',
            ],
            'manage_users' => [
                'label' => 'Manage Users',
                'description' => 'Add, remove, and update users who have access to the website admin.',
                'capability' => 'meza_doc_manage_users',
                'access' => [
                    $seo => 'View access',
                    $site => 'Full access',
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
                    $seo => 'Full access',
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
                    $seo => 'Full access',
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
                    $site => 'Full access',
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
                    $site => 'Full access',
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
                    $site => 'Full access',
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
                    $site => 'Full access',
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
                    $site => 'Full access',
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
                'roles' => [],
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

if (!function_exists('meza_site_documentation_themes_registry')) {
    function meza_site_documentation_themes_registry(): array
    {
        $active_theme = wp_get_theme();
        $active_stylesheet = $active_theme instanceof WP_Theme ? (string) $active_theme->get_stylesheet() : '';
        $active_name = $active_theme instanceof WP_Theme ? (string) $active_theme->get('Name') : 'Client Theme';

        return [
            'active_child_theme' => [
                'label' => $active_name,
                'description' => 'Loads the client-specific templates, styles, and functionality used on the website.',
                'capability' => 'meza_doc_theme_client',
                'theme' => $active_stylesheet,
                'roles' => [
                    meza_site_documentation_site_manager_role_key(),
                    'administrator',
                ],
                'type' => 'Child',
                'importance' => 'Essential',
                'action' => 'View',
            ],
            'meza_starter' => [
                'label' => 'Meza Starter',
                'description' => 'Loads the shared framework used to build performant and SEO-friendly websites.',
                'capability' => 'meza_doc_theme_meza_starter',
                'theme' => 'meza-starter',
                'roles' => [
                    meza_site_documentation_site_manager_role_key(),
                    'administrator',
                ],
                'type' => 'Starter',
                'importance' => 'Essential',
                'action' => 'View',
            ],
            'twentytwentyfive' => [
                'label' => 'Twenty Twenty-Five',
                'description' => 'Installed with WordPress and kept available for maintenance or debugging when needed.',
                'capability' => 'meza_doc_theme_twenty_twenty_five',
                'theme' => 'twentytwentyfive',
                'roles' => [
                    meza_site_documentation_site_manager_role_key(),
                    'administrator',
                ],
                'type' => 'Default',
                'importance' => 'Nice to have',
                'action' => 'View',
            ],
        ];
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

if (!function_exists('meza_site_documentation_apply_row_override')) {
    function meza_site_documentation_apply_row_override(string $section_key, string $row_key, array $row): array
    {
        $overrides = meza_site_documentation_get_row_overrides();
        $override = $overrides[$section_key][$row_key] ?? null;

        if (!is_array($override)) {
            return $row;
        }

        if (!empty($override['note'])) {
            $row['note'] = $override['note'];
        }

        if (!empty($override['importance'])) {
            $row['importance'] = ucwords(str_replace('_', ' ', (string) $override['importance']));
        }

        $row['_override_visibility'] = $override['visibility'] ?? 'default';

        return $row;
    }
}

if (!function_exists('meza_site_documentation_should_include_row')) {
    function meza_site_documentation_should_include_row(array $row, string $section_key = ''): bool
    {
        $visibility = sanitize_key((string) ($row['_override_visibility'] ?? 'default'));
        $is_available = meza_site_documentation_is_dependency_available($row);

        if ($visibility === 'hide') {
            return false;
        }

        if ($visibility === 'show') {
            return true;
        }

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
            $row = meza_site_documentation_apply_row_override($section_key, (string) $row_key, $row);

            if (!meza_site_documentation_should_include_row($row, $section_key)) {
                continue;
            }

            $row['is_available'] = meza_site_documentation_is_dependency_available($row);
            $row['role_labels'] = meza_site_documentation_resolve_role_list($row);
            $row['roles_text'] = implode(', ', array_values($row['role_labels']));
            $row['action_links'] = meza_site_documentation_get_row_action_links($section_key, $row);

            if ($section_key === 'analytics') {
                $analytics_override = $analytics_overrides[$row['key']] ?? [];

                $row['access_entity'] = trim((string) ($analytics_override['access_entity'] ?? $row['default_access_entity'] ?? ''));
                if (!empty($analytics_override['note'])) {
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

if (!function_exists('meza_site_documentation_get_options_page_definition')) {
    function meza_site_documentation_get_options_page_definition(): array
    {
        return [
            'page_title' => 'Site Documentation Settings',
            'menu_title' => 'Site Documentation',
            'menu_slug' => meza_site_documentation_option_page_slug(),
            'parent_slug' => 'options-general.php',
            'capability' => 'manage_options',
            'redirect' => false,
            'update_button' => 'Update',
            'updated_message' => 'Site Documentation Updated',
            'autoload' => false,
        ];
    }
}

if (!function_exists('meza_site_documentation_section_copy_fields')) {
    function meza_site_documentation_section_copy_fields(): array
    {
        $field_names = [
            'site_documentation_overview_intro' => 'Overview Intro',
            'site_documentation_support_note' => 'Support Note',
            'site_documentation_quick_access_intro' => 'Quick Access Intro',
            'site_documentation_content_management_intro' => 'Content Management Intro',
            'site_documentation_pages_intro' => 'Pages Intro',
            'site_documentation_content_types_intro' => 'Content Types Intro',
            'site_documentation_capabilities_intro' => 'Capabilities Intro',
            'site_documentation_dashboards_intro' => 'Dashboards Intro',
            'site_documentation_tools_intro' => 'Tools Intro',
            'site_documentation_analytics_intro' => 'Analytics Intro',
            'site_documentation_plugins_intro' => 'Plugins Intro',
            'site_documentation_themes_intro' => 'Themes Intro',
        ];

        $fields = [];

        foreach ($field_names as $field_name => $label) {
            $fields[] = [
                'key' => 'field_' . md5($field_name),
                'label' => $label,
                'name' => $field_name,
                'aria-label' => '',
                'type' => 'textarea',
                'instructions' => 'Optional copy shown above this section of the site documentation page.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'default_value' => '',
                'maxlength' => '',
                'allow_in_bindings' => 0,
                'rows' => 3,
                'placeholder' => '',
                'new_lines' => '',
            ];
        }

        return $fields;
    }
}

if (!function_exists('meza_site_documentation_role_contact_fields')) {
    function meza_site_documentation_role_contact_fields(): array
    {
        return [
            [
                'key' => 'field_' . md5('site_documentation_role_contacts'),
                'label' => 'Role Contacts',
                'name' => 'site_documentation_role_contacts',
                'aria-label' => '',
                'type' => 'repeater',
                'instructions' => 'Optional overrides for the role contact table. Leave blank to let WordPress user data populate each role automatically.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'layout' => 'row',
                'pagination' => 0,
                'min' => 0,
                'max' => 0,
                'collapsed' => 'field_' . md5('site_documentation_role_contacts_role'),
                'button_label' => 'Add Role Contact',
                'rows_per_page' => 20,
                'sub_fields' => [
                    [
                        'key' => 'field_' . md5('site_documentation_role_contacts_role'),
                        'label' => 'Role',
                        'name' => 'role',
                        'aria-label' => '',
                        'type' => 'select',
                        'instructions' => '',
                        'required' => 1,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '25',
                            'class' => '',
                            'id' => '',
                        ],
                        'choices' => meza_site_documentation_role_choices(),
                        'default_value' => false,
                        'return_format' => 'value',
                        'multiple' => 0,
                        'allow_null' => 0,
                        'allow_in_bindings' => 0,
                        'ui' => 0,
                        'ajax' => 0,
                        'placeholder' => '',
                        'create_options' => 0,
                        'save_options' => 0,
                        'parent_repeater' => 'field_' . md5('site_documentation_role_contacts'),
                    ],
                    [
                        'key' => 'field_' . md5('site_documentation_role_contacts_name'),
                        'label' => 'Name',
                        'name' => 'name',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '25',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'parent_repeater' => 'field_' . md5('site_documentation_role_contacts'),
                    ],
                    [
                        'key' => 'field_' . md5('site_documentation_role_contacts_username'),
                        'label' => 'Username',
                        'name' => 'username',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '25',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'parent_repeater' => 'field_' . md5('site_documentation_role_contacts'),
                    ],
                    [
                        'key' => 'field_' . md5('site_documentation_role_contacts_email'),
                        'label' => 'Email',
                        'name' => 'email',
                        'aria-label' => '',
                        'type' => 'email',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '25',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'parent_repeater' => 'field_' . md5('site_documentation_role_contacts'),
                    ],
                    [
                        'key' => 'field_' . md5('site_documentation_role_contacts_note'),
                        'label' => 'Note',
                        'name' => 'note',
                        'aria-label' => '',
                        'type' => 'textarea',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'rows' => 2,
                        'placeholder' => '',
                        'new_lines' => '',
                        'parent_repeater' => 'field_' . md5('site_documentation_role_contacts'),
                    ],
                ],
            ],
        ];
    }
}

if (!function_exists('meza_site_documentation_analytics_fields')) {
    function meza_site_documentation_analytics_fields(): array
    {
        return [
            [
                'key' => 'field_' . md5('site_documentation_analytics_accounts'),
                'label' => 'Analytics Accounts',
                'name' => 'site_documentation_analytics_accounts',
                'aria-label' => '',
                'type' => 'repeater',
                'instructions' => 'Optional owner or access details for analytics rows. Leave blank to use built-in defaults.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'layout' => 'row',
                'pagination' => 0,
                'min' => 0,
                'max' => 0,
                'collapsed' => 'field_' . md5('site_documentation_analytics_accounts_item_key'),
                'button_label' => 'Add Analytics Item',
                'rows_per_page' => 20,
                'sub_fields' => [
                    [
                        'key' => 'field_' . md5('site_documentation_analytics_accounts_item_key'),
                        'label' => 'Analytics Item',
                        'name' => 'item_key',
                        'aria-label' => '',
                        'type' => 'select',
                        'instructions' => '',
                        'required' => 1,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '33',
                            'class' => '',
                            'id' => '',
                        ],
                        'choices' => [
                            'google_analytics' => 'Google Analytics',
                            'google_search_console' => 'Google Search Console',
                            'pagespeed_insights' => 'PageSpeed Insights',
                        ],
                        'default_value' => false,
                        'return_format' => 'value',
                        'multiple' => 0,
                        'allow_null' => 0,
                        'allow_in_bindings' => 0,
                        'ui' => 0,
                        'ajax' => 0,
                        'placeholder' => '',
                        'create_options' => 0,
                        'save_options' => 0,
                        'parent_repeater' => 'field_' . md5('site_documentation_analytics_accounts'),
                    ],
                    [
                        'key' => 'field_' . md5('site_documentation_analytics_accounts_access_entity'),
                        'label' => 'Access Entity',
                        'name' => 'access_entity',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => 'Example: info@example.com or Public',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '33',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'parent_repeater' => 'field_' . md5('site_documentation_analytics_accounts'),
                    ],
                    [
                        'key' => 'field_' . md5('site_documentation_analytics_accounts_importance'),
                        'label' => 'Importance Override',
                        'name' => 'importance',
                        'aria-label' => '',
                        'type' => 'select',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '33',
                            'class' => '',
                            'id' => '',
                        ],
                        'choices' => [
                            '' => 'Use Default',
                            'essential' => 'Essential',
                            'important' => 'Important',
                            'nice_to_have' => 'Nice to have',
                        ],
                        'default_value' => false,
                        'return_format' => 'value',
                        'multiple' => 0,
                        'allow_null' => 0,
                        'allow_in_bindings' => 0,
                        'ui' => 0,
                        'ajax' => 0,
                        'placeholder' => '',
                        'create_options' => 0,
                        'save_options' => 0,
                        'parent_repeater' => 'field_' . md5('site_documentation_analytics_accounts'),
                    ],
                    [
                        'key' => 'field_' . md5('site_documentation_analytics_accounts_note'),
                        'label' => 'Note',
                        'name' => 'note',
                        'aria-label' => '',
                        'type' => 'textarea',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'rows' => 2,
                        'placeholder' => '',
                        'new_lines' => '',
                        'parent_repeater' => 'field_' . md5('site_documentation_analytics_accounts'),
                    ],
                ],
            ],
        ];
    }
}

if (!function_exists('meza_site_documentation_override_fields')) {
    function meza_site_documentation_override_fields(): array
    {
        return [
            [
                'key' => 'field_' . md5('site_documentation_row_overrides'),
                'label' => 'Row Overrides',
                'name' => 'site_documentation_row_overrides',
                'aria-label' => '',
                'type' => 'repeater',
                'instructions' => 'Use this only for client-specific visibility, note, or importance adjustments to the shared documentation tables.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'layout' => 'row',
                'pagination' => 0,
                'min' => 0,
                'max' => 0,
                'collapsed' => 'field_' . md5('site_documentation_row_overrides_row_key'),
                'button_label' => 'Add Row Override',
                'rows_per_page' => 20,
                'sub_fields' => [
                    [
                        'key' => 'field_' . md5('site_documentation_row_overrides_section_key'),
                        'label' => 'Section',
                        'name' => 'section_key',
                        'aria-label' => '',
                        'type' => 'select',
                        'instructions' => '',
                        'required' => 1,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '20',
                            'class' => '',
                            'id' => '',
                        ],
                        'choices' => [
                            'pages' => 'Pages',
                            'content_types' => 'Content Types',
                            'capabilities' => 'Capabilities',
                            'dashboards' => 'Dashboards',
                            'tools' => 'Tools',
                            'analytics' => 'Analytics',
                            'plugins' => 'Plugins',
                            'themes' => 'Themes',
                        ],
                        'default_value' => false,
                        'return_format' => 'value',
                        'multiple' => 0,
                        'allow_null' => 0,
                        'allow_in_bindings' => 0,
                        'ui' => 0,
                        'ajax' => 0,
                        'placeholder' => '',
                        'create_options' => 0,
                        'save_options' => 0,
                        'parent_repeater' => 'field_' . md5('site_documentation_row_overrides'),
                    ],
                    [
                        'key' => 'field_' . md5('site_documentation_row_overrides_row_key'),
                        'label' => 'Row Key',
                        'name' => 'row_key',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => 'Use the shared code row key, for example manage_plugins or yoast_seo.',
                        'required' => 1,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '20',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'parent_repeater' => 'field_' . md5('site_documentation_row_overrides'),
                    ],
                    [
                        'key' => 'field_' . md5('site_documentation_row_overrides_visibility'),
                        'label' => 'Visibility',
                        'name' => 'visibility',
                        'aria-label' => '',
                        'type' => 'select',
                        'instructions' => '',
                        'required' => 1,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '20',
                            'class' => '',
                            'id' => '',
                        ],
                        'choices' => [
                            'default' => 'Default',
                            'show' => 'Force Show',
                            'hide' => 'Hide',
                        ],
                        'default_value' => 'default',
                        'return_format' => 'value',
                        'multiple' => 0,
                        'allow_null' => 0,
                        'allow_in_bindings' => 0,
                        'ui' => 0,
                        'ajax' => 0,
                        'placeholder' => '',
                        'create_options' => 0,
                        'save_options' => 0,
                        'parent_repeater' => 'field_' . md5('site_documentation_row_overrides'),
                    ],
                    [
                        'key' => 'field_' . md5('site_documentation_row_overrides_importance'),
                        'label' => 'Importance Override',
                        'name' => 'importance',
                        'aria-label' => '',
                        'type' => 'select',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '20',
                            'class' => '',
                            'id' => '',
                        ],
                        'choices' => [
                            '' => 'Use Default',
                            'essential' => 'Essential',
                            'important' => 'Important',
                            'nice_to_have' => 'Nice to have',
                        ],
                        'default_value' => false,
                        'return_format' => 'value',
                        'multiple' => 0,
                        'allow_null' => 0,
                        'allow_in_bindings' => 0,
                        'ui' => 0,
                        'ajax' => 0,
                        'placeholder' => '',
                        'create_options' => 0,
                        'save_options' => 0,
                        'parent_repeater' => 'field_' . md5('site_documentation_row_overrides'),
                    ],
                    [
                        'key' => 'field_' . md5('site_documentation_row_overrides_note'),
                        'label' => 'Note',
                        'name' => 'note',
                        'aria-label' => '',
                        'type' => 'textarea',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'rows' => 2,
                        'placeholder' => '',
                        'new_lines' => '',
                        'parent_repeater' => 'field_' . md5('site_documentation_row_overrides'),
                    ],
                ],
            ],
        ];
    }
}

if (!function_exists('meza_site_documentation_options_groups')) {
    function meza_site_documentation_options_groups(): array
    {
        $slug = meza_site_documentation_option_page_slug();

        return [
            [
                'key' => 'group_' . md5('site_documentation_general'),
                'title' => 'Overview and Links',
                'fields' => [
                    [
                        'key' => 'field_' . md5('site_documentation_support_email'),
                        'label' => 'Support Email',
                        'name' => 'site_documentation_support_email',
                        'aria-label' => '',
                        'type' => 'email',
                        'instructions' => 'Used in the default support note shown on the Site Documentation page.',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '50',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ],
                    [
                        'key' => 'field_' . md5('site_documentation_live_url'),
                        'label' => 'Live URL',
                        'name' => 'site_documentation_live_url',
                        'aria-label' => '',
                        'type' => 'url',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '50',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'placeholder' => '',
                    ],
                    [
                        'key' => 'field_' . md5('site_documentation_admin_url'),
                        'label' => 'Admin Login URL',
                        'name' => 'site_documentation_admin_url',
                        'aria-label' => '',
                        'type' => 'url',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '50',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'placeholder' => '',
                    ],
                    [
                        'key' => 'field_' . md5('site_documentation_sitemap_url'),
                        'label' => 'Sitemap URL',
                        'name' => 'site_documentation_sitemap_url',
                        'aria-label' => '',
                        'type' => 'url',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '50',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'placeholder' => '',
                    ],
                    [
                        'key' => 'field_' . md5('site_documentation_style_guide_url'),
                        'label' => 'Style Guide URL',
                        'name' => 'site_documentation_style_guide_url',
                        'aria-label' => '',
                        'type' => 'url',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '50',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'placeholder' => '',
                    ],
                    ...meza_site_documentation_section_copy_fields(),
                ],
                'location' => [
                    [
                        [
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => $slug,
                        ],
                    ],
                ],
                'menu_order' => 0,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'left',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
            ],
            [
                'key' => 'group_' . md5('site_documentation_roles'),
                'title' => 'Role Contacts',
                'fields' => meza_site_documentation_role_contact_fields(),
                'location' => [
                    [
                        [
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => $slug,
                        ],
                    ],
                ],
                'menu_order' => 10,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'left',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
            ],
            [
                'key' => 'group_' . md5('site_documentation_analytics'),
                'title' => 'Analytics Access',
                'fields' => meza_site_documentation_analytics_fields(),
                'location' => [
                    [
                        [
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => $slug,
                        ],
                    ],
                ],
                'menu_order' => 20,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'left',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
            ],
            [
                'key' => 'group_' . md5('site_documentation_overrides'),
                'title' => 'Shared Row Overrides',
                'fields' => meza_site_documentation_override_fields(),
                'location' => [
                    [
                        [
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => $slug,
                        ],
                    ],
                ],
                'menu_order' => 30,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'left',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
            ],
        ];
    }
}

add_filter('meza_shared_project_acf_options_pages', static function (array $pages): array {
    $pages[] = meza_site_documentation_get_options_page_definition();
    return $pages;
});

add_filter('meza_shared_project_acf_field_groups', static function (array $groups): array {
    return array_merge($groups, meza_site_documentation_options_groups());
});
