<?php


/**
 * Shared documentation data, ACF options, and resolver helpers.
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) {
    return;
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

if (!function_exists('meza_site_documentation_shop_manager_role_key')) {
    function meza_site_documentation_shop_manager_role_key(): string
    {
        return 'shop_manager';
    }
}

if (!function_exists('meza_site_documentation_content_manager_role_key')) {
    function meza_site_documentation_content_manager_role_key(): string
    {
        return function_exists('meza_content_editor_role_key')
            ? meza_content_editor_role_key()
            : 'content_manager';
    }
}

if (!function_exists('meza_site_documentation_contact_roles')) {
    function meza_site_documentation_contact_roles(): array
    {
        return [
            'administrator' => 'Administrator',
            meza_site_documentation_site_manager_role_key() => 'Site Manager',
            meza_site_documentation_seo_manager_role_key() => 'SEO Manager',
            meza_site_documentation_content_manager_role_key() => 'Content Manager',
            meza_site_documentation_shop_manager_role_key() => 'Shop Manager',
        ];
    }
}

if (!function_exists('meza_site_documentation_access_roles')) {
    function meza_site_documentation_access_roles(): array
    {
        return [
            'administrator' => 'Administrator',
            meza_site_documentation_site_manager_role_key() => 'Site Manager',
            meza_site_documentation_seo_manager_role_key() => 'SEO Manager',
            meza_site_documentation_content_manager_role_key() => 'Content Manager',
            meza_site_documentation_shop_manager_role_key() => 'Shop Manager',
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

if (!function_exists('meza_site_documentation_assigned_contact_roles')) {
    function meza_site_documentation_assigned_contact_roles(): array
    {
        $assigned_roles = [];
        $known_roles = meza_site_documentation_contact_roles();
        $wp_roles = function_exists('wp_roles') ? wp_roles() : null;
        $users = get_users([
            'number' => 0,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        foreach ($users as $user) {
            if (!($user instanceof WP_User)) {
                continue;
            }

            foreach ((array) $user->roles as $role_key) {
                $role_key = sanitize_key((string) $role_key);

                if ($role_key === '' || isset($assigned_roles[$role_key])) {
                    continue;
                }

                $role_label = trim((string) ($known_roles[$role_key] ?? ''));

                if ($role_label === '' && $wp_roles instanceof WP_Roles) {
                    $role_label = trim((string) ($wp_roles->roles[$role_key]['name'] ?? ''));

                    if ($role_label !== '') {
                        $role_label = translate_user_role($role_label);
                    }
                }

                if ($role_label === '') {
                    $role_label = ucwords(str_replace(['-', '_'], ' ', $role_key));
                }

                $assigned_roles[$role_key] = $role_label;
            }
        }

        uksort($assigned_roles, static function (string $left_key, string $right_key) use ($assigned_roles): int {
            $left_priority = meza_site_documentation_role_key_priority($left_key);
            $right_priority = meza_site_documentation_role_key_priority($right_key);

            if ($left_priority !== $right_priority) {
                return $left_priority <=> $right_priority;
            }

            return strnatcasecmp((string) $assigned_roles[$left_key], (string) $assigned_roles[$right_key]);
        });

        return $assigned_roles;
    }
}

if (!function_exists('meza_site_documentation_active_access_roles')) {
    function meza_site_documentation_active_access_roles(): array
    {
        $roles = [];

        foreach (meza_site_documentation_access_roles() as $role_key => $role_label) {
            if (!(get_role($role_key) instanceof WP_Role)) {
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
    function meza_site_documentation_role_keys_for_capability(string $capability): array
    {
        $roles = [];

        foreach (meza_site_documentation_active_access_roles() as $role_key => $role_label) {
            if (meza_site_documentation_role_has_capability($role_key, $capability)) {
                $roles[$role_key] = $role_label;
            }
        }

        return $roles;
    }
}

if (!function_exists('meza_site_documentation_role_labels_for_capability')) {
    function meza_site_documentation_role_labels_for_capability(string $capability): array
    {
        return meza_site_documentation_role_keys_for_capability($capability);
    }
}

if (!function_exists('meza_site_documentation_format_role_labels')) {
    function meza_site_documentation_role_label_priority(string $label): int
    {
        $label = trim($label);

        return match ($label) {
            'Administrator' => 10,
            'Site Manager' => 20,
            'SEO Manager' => 30,
            'Content Manager' => 35,
            'Shop Manager' => 40,
            default => 1000,
        };
    }
}

if (!function_exists('meza_site_documentation_format_role_labels')) {
    function meza_site_documentation_format_role_labels(array $labels): string
    {
        $labels = array_values(array_unique(array_filter(array_map(static function ($label): string {
            return trim((string) $label);
        }, $labels))));

        usort($labels, static function (string $left, string $right): int {
            $left_priority = meza_site_documentation_role_label_priority($left);
            $right_priority = meza_site_documentation_role_label_priority($right);

            if ($left_priority !== $right_priority) {
                return $left_priority <=> $right_priority;
            }

            return strnatcasecmp($left, $right);
        });

        return implode(', ', $labels);
    }
}

if (!function_exists('meza_site_documentation_format_access_label')) {
    function meza_site_documentation_format_access_label(string $label): string
    {
        $label = trim($label);

        if ($label === '') {
            return '';
        }

        return ucwords(strtolower($label));
    }
}

if (!function_exists('meza_site_documentation_default_section_copy')) {
    function meza_site_documentation_default_section_copy(): array
    {
        return [
            'site_documentation_key_links_intro' => 'Use these links to access the main website destinations and related reference pages documented for this project.',
            'site_documentation_users_and_roles_intro' => 'This section identifies the WordPress roles used on the site and the people or teams associated with them when that information is available.',
            'site_documentation_content_structure_intro' => 'This section explains how content is organized in WordPress, including the templates and reusable patterns that shape the site.',
            'site_documentation_content_intro' => 'This section catalogs the site\'s content collections, separating published URLs from content that is managed only in the admin.',
            'site_documentation_indexed_urls_intro' => 'These entries represent content with a front-end link that can be referenced directly on the website.',
            'site_documentation_content_only_intro' => 'These entries represent content that is maintained in WordPress for internal or supporting use without a public URL by default.',
            'site_documentation_content_types_intro' => 'These are the post types and taxonomies used to organize content across the site, along with their visibility and management access.',
            'site_documentation_page_templates_intro' => 'These templates define the main page-level layouts available on the site and where they are typically used.',
            'site_documentation_section_templates_intro' => 'These templates are reusable content sections available in the theme for building page and archive layouts.',
            'site_documentation_website_maintenance_intro' => 'This section summarizes the operational areas involved in maintaining the site and the systems used to support that work.',
            'site_documentation_management_capabilities_intro' => 'These entries outline the core maintenance responsibilities documented for the site and the roles that can perform them.',
            'site_documentation_dashboards_intro' => 'These are the primary admin and reporting screens used to review site activity, content status, visibility, and related metrics.',
            'site_documentation_tools_intro' => 'These are the administrative tools and utilities available to support configuration, troubleshooting, and routine upkeep.',
            'site_documentation_tech_stack_intro' => 'This section documents the active platforms, themes, plugins, and supporting code dependencies that the site relies on.',
            'site_documentation_analytics_intro' => 'These are the analytics and tracking services connected to the site, along with the places used to review reporting data.',
            'site_documentation_code_dependencies_intro' => 'These are the development and runtime packages used by the active theme stack for front-end features, builds, and shared tooling.',
            'site_documentation_plugins_intro' => 'These are the active WordPress plugins that provide site features, integrations, editorial support, and administrative functionality.',
            'site_documentation_themes_intro' => 'These are the active themes that control the site\'s frontend presentation, template structure, and shared implementation layer.',
        ];
    }
}

if (!function_exists('meza_site_documentation_hero_lead')) {
    function meza_site_documentation_hero_lead(): string
    {
        return 'This private page documents the website tools, maintenance responsibilities, and core platform components used on the site.';
    }
}

if (!function_exists('meza_site_documentation_find_first_user_for_role')) {
    function meza_site_documentation_handoff_excluded_role_logins(): array
    {
        return ['smeza', 'ameza', 'andrew', 'andrewmeza', 'rapi'];
    }
}

if (!function_exists('meza_site_documentation_role_uses_handoff_filter')) {
    function meza_site_documentation_role_uses_handoff_filter(string $role_key): bool
    {
        return in_array($role_key, [
            'administrator',
            meza_site_documentation_site_manager_role_key(),
        ], true);
    }
}

if (!function_exists('meza_site_documentation_find_first_user_for_role')) {
    function meza_site_documentation_find_first_user_for_role(string $role_key): ?WP_User
    {
        $users = get_users([
            'role' => $role_key,
            'number' => 0,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        if (empty($users)) {
            return null;
        }

        $users = array_values(array_filter($users, static function ($user): bool {
            return $user instanceof WP_User;
        }));

        if ($users === []) {
            return null;
        }

        if (count($users) > 1 && meza_site_documentation_role_uses_handoff_filter($role_key)) {
            $excluded_logins = array_fill_keys(meza_site_documentation_handoff_excluded_role_logins(), true);
            $handoff_users = array_values(array_filter($users, static function (WP_User $user) use ($excluded_logins): bool {
                return !isset($excluded_logins[(string) $user->user_login]);
            }));

            if ($handoff_users !== []) {
                return $handoff_users[0];
            }
        }

        return $users[0];
    }
}

if (!function_exists('meza_site_documentation_users_for_role')) {
    function meza_site_documentation_users_for_role(string $role_key): array
    {
        $users = get_users([
            'role' => $role_key,
            'number' => 0,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        if (empty($users)) {
            return [];
        }

        return array_values(array_filter($users, static function ($user): bool {
            return $user instanceof WP_User;
        }));
    }
}

if (!function_exists('meza_site_documentation_role_key_priority')) {
    function meza_site_documentation_role_key_priority(string $role_key): int
    {
        $role_key = sanitize_key($role_key);

        return match ($role_key) {
            'administrator' => 10,
            meza_site_documentation_site_manager_role_key() => 20,
            meza_site_documentation_seo_manager_role_key() => 30,
            meza_site_documentation_content_manager_role_key() => 35,
            meza_site_documentation_shop_manager_role_key() => 40,
            default => 1000,
        };
    }
}

if (!function_exists('meza_site_documentation_current_user_preferred_access_role_key')) {
    function meza_site_documentation_current_user_preferred_access_role_key(): string
    {
        $current_roles = meza_site_documentation_current_user_role_keys();

        foreach (array_keys(meza_site_documentation_access_roles()) as $role_key) {
            if (in_array($role_key, $current_roles, true)) {
                return $role_key;
            }
        }

        return '';
    }
}

if (!function_exists('meza_site_documentation_contact_email_for_role')) {
    function meza_site_documentation_contact_email_for_role(string $role_key): string
    {
        $role_key = sanitize_key($role_key);

        if ($role_key === '') {
            return '';
        }

        $user = meza_site_documentation_find_first_user_for_role($role_key);

        return $user instanceof WP_User
            ? sanitize_email((string) $user->user_email)
            : '';
    }
}

if (!function_exists('meza_site_documentation_contact_role_key_for_row')) {
    function meza_site_documentation_contact_role_key_for_row(array $row, string $section_key = ''): string
    {
        if ($section_key === 'capabilities') {
            $full_access_roles = [];

            foreach ((array) ($row['access'] ?? []) as $role_key => $access_label) {
                if (strcasecmp(trim((string) $access_label), 'Full access') !== 0) {
                    continue;
                }

                $role_key = sanitize_key((string) $role_key);

                if ($role_key === '') {
                    continue;
                }

                $full_access_roles[] = $role_key;
            }

            usort($full_access_roles, static function (string $left, string $right): int {
                return meza_site_documentation_role_key_priority($right) <=> meza_site_documentation_role_key_priority($left);
            });

            foreach ($full_access_roles as $role_key) {
                if (meza_site_documentation_contact_email_for_role($role_key) !== '') {
                    return $role_key;
                }
            }
        }

        return 'administrator';
    }
}

if (!function_exists('meza_site_documentation_get_role_contacts')) {
    function meza_site_documentation_user_full_name(WP_User $user): string
    {
        $first_name = trim((string) get_user_meta($user->ID, 'first_name', true));
        $last_name = trim((string) get_user_meta($user->ID, 'last_name', true));
        $full_name = trim($first_name . ' ' . $last_name);

        if ($full_name !== '') {
            return $full_name;
        }

        return trim((string) ($user->display_name ?: $user->user_nicename));
    }
}

if (!function_exists('meza_site_documentation_user_first_name')) {
    function meza_site_documentation_user_first_name(WP_User $user): string
    {
        $first_name = trim((string) get_user_meta($user->ID, 'first_name', true));

        if ($first_name !== '') {
            return $first_name;
        }

        $full_name = meza_site_documentation_user_full_name($user);
        if ($full_name === '') {
            return trim((string) $user->user_login);
        }

        $parts = preg_split('/\s+/', $full_name);

        return trim((string) ($parts[0] ?? $full_name));
    }
}

if (!function_exists('meza_site_documentation_get_role_contacts')) {
    function meza_site_documentation_get_role_contacts(): array
    {
        $rows = [];

        foreach (meza_site_documentation_assigned_contact_roles() as $role_key => $role_label) {
            $role_rows = [];
            $role_users = array_values(array_filter(
                meza_site_documentation_users_for_role($role_key),
                static function ($role_user): bool {
                    return $role_user instanceof WP_User;
                }
            ));

            usort($role_users, static function (WP_User $left, WP_User $right): int {
                return strnatcasecmp(
                    trim((string) $left->user_login),
                    trim((string) $right->user_login)
                );
            });

            if ($role_users === []) {
                continue;
            }

            foreach ($role_users as $role_user) {
                $role_rows[] = [
                    'role' => $role_label,
                    'name' => meza_site_documentation_user_full_name($role_user),
                    'user_login' => trim((string) $role_user->user_login),
                    'email' => sanitize_email((string) $role_user->user_email),
                    'note' => '',
                ];
            }

            foreach ($role_rows as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('meza_site_documentation_style_guide_url')) {
    function meza_site_documentation_find_page_url_by_template(string $template_file): string
    {
        if ($template_file === '') {
            return '';
        }

        if (is_page_template($template_file)) {
            $url = get_permalink();
            return is_string($url) ? $url : '';
        }

        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => ['publish', 'private'],
            'posts_per_page' => 1,
            'meta_key' => '_wp_page_template',
            'meta_value' => $template_file,
            'fields' => 'ids',
        ]);

        if (!empty($pages[0])) {
            $url = get_permalink((int) $pages[0]);
            return is_string($url) ? $url : '';
        }

        return '';
    }
}

if (!function_exists('meza_site_documentation_find_page_url_by_option')) {
    function meza_site_documentation_find_page_url_by_option(string $option_key): string
    {
        $option_key = trim($option_key);

        if ($option_key === '') {
            return '';
        }

        $page_id = (int) get_option($option_key);

        if ($page_id <= 0) {
            return '';
        }

        $url = get_permalink($page_id);

        return is_string($url) ? $url : '';
    }
}

if (!function_exists('meza_site_documentation_is_plugin_active')) {
    function meza_site_documentation_is_plugin_active(string $plugin_basename): bool
    {
        $plugin_basename = trim($plugin_basename);

        if ($plugin_basename === '') {
            return false;
        }

        return function_exists('meza_is_plugin_basename_active')
            ? meza_is_plugin_basename_active($plugin_basename)
            : false;
    }
}

if (!function_exists('meza_site_documentation_theme_page_slugs')) {
    function meza_site_documentation_theme_page_slugs(): array
    {
        $defaults = [
            'home' => 'homepage',
            'privacy' => 'privacy-policy',
            'cookies' => 'cookie-policy',
        ];

        $slugs = apply_filters('theme_page_slugs', $defaults);

        return is_array($slugs) ? $slugs : $defaults;
    }
}

if (!function_exists('meza_site_documentation_cookie_policy_slug')) {
    function meza_site_documentation_cookie_policy_slug(): string
    {
        $slugs = meza_site_documentation_theme_page_slugs();
        $slug = isset($slugs['cookies']) ? sanitize_title((string) $slugs['cookies']) : 'cookie-policy';

        return $slug !== '' ? $slug : 'cookie-policy';
    }
}

if (!function_exists('meza_site_documentation_template_directories')) {
    function meza_site_documentation_template_directories(): array
    {
        $directories = [
            get_stylesheet_directory(),
        ];

        if (get_template_directory() !== get_stylesheet_directory()) {
            $directories[] = get_template_directory();
        }

        $directories = array_values(array_unique(array_filter(array_map(static function ($directory): string {
            return is_string($directory) ? trim($directory) : '';
        }, $directories))));

        return $directories;
    }
}

if (!function_exists('meza_site_documentation_page_template_file_path')) {
    function meza_site_documentation_page_template_file_path(string $template_file): string
    {
        $template_file = ltrim(trim($template_file), '/');

        if ($template_file === '') {
            return '';
        }

        foreach (meza_site_documentation_template_directories() as $directory) {
            $path = trailingslashit($directory) . $template_file;

            if (file_exists($path)) {
                return $path;
            }
        }

        return '';
    }
}

if (!function_exists('meza_site_documentation_template_file_headers')) {
    function meza_site_documentation_template_file_headers(string $path): array
    {
        if ($path === '' || !file_exists($path)) {
            return [
                'template_name' => '',
                'template_description' => '',
                'template_type' => '',
                'template_post_types' => '',
            ];
        }

        $headers = get_file_data($path, [
            'template_name' => 'Template Name',
            'template_description' => 'Template Description',
            'template_type' => 'Template Type',
            'template_post_types' => 'Template Post Type',
        ]);

        return [
            'template_name' => trim((string) ($headers['template_name'] ?? '')),
            'template_description' => trim((string) ($headers['template_description'] ?? '')),
            'template_type' => trim((string) ($headers['template_type'] ?? '')),
            'template_post_types' => trim((string) ($headers['template_post_types'] ?? '')),
        ];
    }
}

if (!function_exists('meza_site_documentation_discover_page_templates')) {
    function meza_site_documentation_discover_page_templates(): array
    {
        $templates = [];

        foreach (meza_site_documentation_template_directories() as $directory) {
            if (!is_string($directory) || $directory === '') {
                continue;
            }

            $paths = glob(trailingslashit($directory) . 'page-*.php');

            if (!is_array($paths)) {
                continue;
            }

            foreach ($paths as $path) {
                if (!is_string($path) || !file_exists($path)) {
                    continue;
                }

                $template_file = basename($path);
                $headers = meza_site_documentation_template_file_headers($path);
                $template_label = (string) ($headers['template_name'] ?? '');

                if ($template_label === '') {
                    continue;
                }

                $templates[$template_file] = [
                    'file' => $template_file,
                    'label' => $template_label,
                    'description' => (string) ($headers['template_description'] ?? ''),
                    'type' => (string) ($headers['template_type'] ?? ''),
                ];
            }
        }

        uasort($templates, static function (array $left, array $right): int {
            return strnatcasecmp(
                (string) ($left['label'] ?? ''),
                (string) ($right['label'] ?? '')
            );
        });

        return $templates;
    }
}

if (!function_exists('meza_site_documentation_template_supported_page_types')) {
    function meza_site_documentation_template_supported_page_types(string $template_file): array
    {
        $path = meza_site_documentation_page_template_file_path($template_file);

        if ($path === '') {
            return ['Page'];
        }

        $headers = meza_site_documentation_template_file_headers($path);
        $raw_types = (string) ($headers['template_post_types'] ?? '');

        if ($raw_types === '') {
            return ['Page'];
        }

        $labels = [];

        foreach (array_values(array_filter(array_map('trim', explode(',', $raw_types)))) as $post_type) {
            $post_type_object = get_post_type_object($post_type);

            if ($post_type_object instanceof WP_Post_Type) {
                $labels[] = trim((string) ($post_type_object->labels->singular_name ?? $post_type_object->labels->name ?? $post_type));
                continue;
            }

            $labels[] = ucwords(str_replace(['_', '-'], ' ', $post_type));
        }

        $labels = array_values(array_unique(array_filter($labels)));

        return $labels !== [] ? $labels : ['Page'];
    }
}

if (!function_exists('meza_site_documentation_page_template_type_label')) {
    function meza_site_documentation_page_template_type_label(WP_Post $page): string
    {
        if (sanitize_title((string) $page->post_name) === meza_site_documentation_cookie_policy_slug()) {
            return 'Cookie Policy';
        }

        return meza_site_documentation_page_group_label($page);
    }
}

if (!function_exists('meza_site_documentation_page_reference_data')) {
    function meza_site_documentation_page_reference_data(WP_Post $page): array
    {
        $page_url = get_permalink($page);

        return [
            'post_id' => (int) $page->ID,
            'title' => trim((string) $page->post_title),
            'url' => is_string($page_url) ? $page_url : '',
            'edit_url' => admin_url('post.php?post=' . (int) $page->ID . '&action=edit'),
        ];
    }
}

if (!function_exists('meza_site_documentation_page_type_choice_map')) {
    function meza_site_documentation_page_type_choice_map(): array
    {
        if (!function_exists('get_field_object')) {
            return [];
        }

        $field = get_field_object('page_type', 0, false, false);
        if (!is_array($field) || !isset($field['choices']) || !is_array($field['choices'])) {
            return [];
        }

        $choices = [];

        foreach ($field['choices'] as $value => $label) {
            $value = is_scalar($value) ? trim((string) $value) : '';
            $label = is_scalar($label) ? trim((string) $label) : '';

            if ($value === '' || $label === '') {
                continue;
            }

            $choices[strtolower($value)] = $label;
        }

        return $choices;
    }
}

if (!function_exists('meza_site_documentation_special_page_label')) {
    function meza_site_documentation_special_page_label(int $post_id): string
    {
        $special_pages = [
            (int) get_option('page_on_front') => 'Front Page',
            (int) get_option('page_for_posts') => 'Posts Page',
            (int) get_option('meza_page_for_contact') => 'Contact Page',
            (int) get_option('meza_page_for_about') => 'About Page',
            (int) get_option('meza_page_for_faq') => 'FAQ Page',
            (int) get_option('meza_page_for_documentation') => 'Documentation Page',
            (int) get_option('meza_page_for_style_guide') => 'Style Guide Page',
            (int) get_option('wp_page_for_privacy_policy') => 'Privacy Policy Page',
            (int) get_option('meza_page_for_cookie_policy') => 'Cookie Policy Page',
        ];

        if (isset($special_pages[$post_id]) && $special_pages[$post_id] !== '') {
            return $special_pages[$post_id];
        }

        $template_slug = trim((string) get_page_template_slug($post_id));

        return match ($template_slug) {
            'page-documentation.php' => 'Documentation Page',
            'page-style.php' => 'Style Guide Page',
            'page-cookie-policy.php' => 'Cookie Policy Page',
            'page-faq.php' => 'FAQ Page',
            default => '',
        };
    }
}

if (!function_exists('meza_site_documentation_page_type_meta_label')) {
    function meza_site_documentation_page_type_meta_label(int $post_id): string
    {
        $raw_value = trim((string) get_post_meta($post_id, 'page_type', true));

        if ($raw_value === '') {
            return '';
        }

        $choices = meza_site_documentation_page_type_choice_map();
        $normalized_value = strtolower($raw_value);

        return $choices[$normalized_value] ?? $raw_value;
    }
}

if (!function_exists('meza_site_documentation_page_type_term_label')) {
    function meza_site_documentation_page_type_term_label(int $post_id): string
    {
        $terms = get_the_terms($post_id, 'page_type');

        if (!is_array($terms) || $terms === []) {
            return '';
        }

        $labels = [];

        foreach ($terms as $term) {
            if (!($term instanceof WP_Term)) {
                continue;
            }

            $label = trim((string) $term->name);

            if ($label !== '') {
                $labels[] = $label;
            }
        }

        $labels = array_values(array_unique($labels));

        return $labels !== [] ? implode(', ', $labels) : '';
    }
}

if (!function_exists('meza_site_documentation_auto_template_files')) {
    function meza_site_documentation_auto_template_files(): array
    {
        return [
            'page-cookie-policy.php',
            'page-documentation.php',
            'page-style.php',
        ];
    }
}

if (!function_exists('meza_site_documentation_should_include_single_template_file')) {
    function meza_site_documentation_should_include_single_template_file(string $file): bool
    {
        return !preg_match('/^single-service-.+\.php$/', $file);
    }
}

if (!function_exists('meza_site_documentation_single_template_rows')) {
    function meza_site_documentation_single_template_rows(): array
    {
        $rows = [];

        foreach (meza_site_documentation_template_directories() as $directory) {
            if (!is_string($directory) || $directory === '') {
                continue;
            }

            $paths = glob(trailingslashit($directory) . 'single-*.php');

            if (!is_array($paths)) {
                continue;
            }

            foreach ($paths as $path) {
                if (!is_string($path) || !file_exists($path)) {
                    continue;
                }

                $file = basename($path);

                if (!meza_site_documentation_should_include_single_template_file($file)) {
                    continue;
                }

                $post_type = preg_replace('/^single-/', '', (string) pathinfo($file, PATHINFO_FILENAME));
                $post_type = is_string($post_type) ? trim($post_type) : '';

                if ($post_type === '') {
                    continue;
                }

                $post_type_object = get_post_type_object($post_type);
                $singular_label = $post_type_object instanceof WP_Post_Type
                    ? trim((string) ($post_type_object->labels->singular_name ?? $post_type))
                    : ucwords(str_replace(['_', '-'], ' ', $post_type));
                $plural_label = $post_type_object instanceof WP_Post_Type
                    ? trim((string) ($post_type_object->labels->name ?? $singular_label))
                    : $singular_label;
                $headers = meza_site_documentation_template_file_headers($path);
                $template_label = (string) ($headers['template_name'] ?? '');
                $template_description = (string) ($headers['template_description'] ?? '');
                $template_type = (string) ($headers['template_type'] ?? '');
                $page_references = [];

                $posts = get_posts([
                    'post_type' => $post_type,
                    'post_status' => ['publish', 'private'],
                    'posts_per_page' => -1,
                    'orderby' => 'title',
                    'order' => 'ASC',
                ]);

                foreach ($posts as $post) {
                    if (!($post instanceof WP_Post)) {
                        continue;
                    }

                    $post_url = get_permalink($post);

                    if (!is_string($post_url) || !meza_site_documentation_has_public_permalink($post_url)) {
                        continue;
                    }

                    $page_references[] = [
                        'title' => trim((string) $post->post_title),
                        'url' => $post_url,
                        'edit_url' => admin_url('post.php?post=' . (int) $post->ID . '&action=edit'),
                    ];
                }

                if ($page_references === []) {
                    $page_references[] = [
                        'title' => $plural_label,
                        'url' => '',
                        'edit_url' => '',
                    ];
                }

                $rows[$file] = [
                    'key' => sanitize_key('single_' . $post_type),
                    'group' => $template_type !== '' ? $template_type : 'Custom Template',
                    'template_label' => $template_label !== '' ? $template_label : 'Single ' . $singular_label . ' Page',
                    'description' => $template_description,
                    'page_references' => $page_references,
                ];
            }
        }

        uasort($rows, static function (array $left, array $right): int {
            return strnatcasecmp(
                (string) ($left['template_label'] ?? ''),
                (string) ($right['template_label'] ?? '')
            );
        });

        return array_values($rows);
    }
}
