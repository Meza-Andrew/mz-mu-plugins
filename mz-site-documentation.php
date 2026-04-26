<?php

/**
 * Shared documentation data, ACF options, and resolver helpers.
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

if (!function_exists('meza_site_documentation_support_email')) {
    function meza_site_documentation_support_email(): string
    {
        return 'wordpress@meza.design';
    }
}

if (!function_exists('meza_site_documentation_option_exists')) {
    function meza_site_documentation_option_exists(string $field_name): bool
    {
        return get_option('options_' . $field_name, null) !== null;
    }
}

if (!function_exists('meza_site_documentation_default_section_copy')) {
    function meza_site_documentation_default_section_copy(): array
    {
        return [
            'site_documentation_overview_intro' => 'This private page documents the website tools, maintenance responsibilities, and core platform components used on the site.',
            'site_documentation_support_note' => sprintf(
                'Technical administrative issues can be sent to %s for Meza support.',
                meza_site_documentation_support_email()
            ),
            'site_documentation_quick_access_intro' => 'Use these links and role assignments to find the website, sign in, and confirm who owns each website-facing responsibility.',
            'site_documentation_content_management_intro' => 'This section outlines the core public-facing content that lives in WordPress and which primary roles can maintain it.',
            'site_documentation_pages_intro' => 'Published pages are pulled directly from WordPress and grouped into a documentation-friendly structure.',
            'site_documentation_content_types_intro' => 'Post types and taxonomies are pulled from the registered WordPress content model and mapped to role access from real capabilities.',
            'site_documentation_page_templates_intro' => 'Page templates are pulled from the active theme and paired with the page types and assigned pages that use them on the website.',
            'site_documentation_capabilities_intro' => 'These are the maintenance capabilities available on the website and the level of access each primary role has to them.',
            'site_documentation_dashboards_intro' => 'These dashboards provide day-to-day visibility into the website, SEO, performance, and security posture.',
            'site_documentation_tools_intro' => 'These tools are used to manage content imports and exports, SEO workflows, and security-related account tasks.',
            'site_documentation_analytics_intro' => 'These analytics platforms measure traffic, search visibility, and performance signals connected to the website.',
            'site_documentation_plugins_intro' => 'These are the preferred plugins currently powering the website, along with their relative importance to the stack.',
            'site_documentation_themes_intro' => 'These themes make up the active front-end stack and any supporting themes kept available for maintenance or debugging.',
        ];
    }
}

if (!function_exists('meza_site_documentation_field_text')) {
    function meza_site_documentation_field_text(string $field_name, string $default = ''): string
    {
        if (!function_exists('get_field')) {
            return $default;
        }

        if (!meza_site_documentation_option_exists($field_name)) {
            return $default;
        }

        $value = get_field($field_name, 'option');
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value;
    }
}

if (!function_exists('meza_site_documentation_find_first_user_for_role')) {
    function meza_site_documentation_handoff_excluded_role_logins(): array
    {
        return ['smeza', 'ameza'];
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
        $primary_user_ids = [];
        $additional_rows = [];

        foreach (meza_site_documentation_contact_roles() as $role_key => $role_label) {
            $user = meza_site_documentation_find_first_user_for_role($role_key);

            $name = $user instanceof WP_User
                ? meza_site_documentation_user_full_name($user)
                : '';
            $user_login = $user instanceof WP_User
                ? trim((string) $user->user_login)
                : '';
            $email = $user instanceof WP_User
                ? sanitize_email((string) $user->user_email)
                : '';

            if ($name === '' && $user_login === '' && $email === '') {
                continue;
            }

            if ($user instanceof WP_User) {
                $primary_user_ids[$user->ID] = true;
            }

            $rows[] = [
                'role' => $role_label,
                'name' => $name,
                'user_login' => $user_login,
                'email' => $email,
                'note' => '',
            ];

            foreach (meza_site_documentation_users_for_role($role_key) as $role_user) {
                if (isset($primary_user_ids[$role_user->ID])) {
                    continue;
                }

                $additional_rows[] = [
                    'role' => $role_label,
                    'name' => meza_site_documentation_user_full_name($role_user),
                    'user_login' => trim((string) $role_user->user_login),
                    'email' => sanitize_email((string) $role_user->user_email),
                    'note' => '',
                    'sort_first_name' => meza_site_documentation_user_first_name($role_user),
                ];
            }
        }

        usort($additional_rows, static function (array $left, array $right): int {
            $first_name_compare = strnatcasecmp(
                (string) ($left['sort_first_name'] ?? ''),
                (string) ($right['sort_first_name'] ?? '')
            );

            if ($first_name_compare !== 0) {
                return $first_name_compare;
            }

            return strnatcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        foreach ($additional_rows as $row) {
            unset($row['sort_first_name']);
            $rows[] = $row;
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
            'post_status' => 'publish',
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
                $headers = get_file_data($path, [
                    'template_name' => 'Template Name',
                ]);
                $template_label = trim((string) ($headers['template_name'] ?? ''));

                if ($template_label === '') {
                    continue;
                }

                $templates[$template_file] = [
                    'file' => $template_file,
                    'label' => $template_label,
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

        $headers = get_file_data($path, [
            'template_post_types' => 'Template Post Type',
        ]);

        $raw_types = trim((string) ($headers['template_post_types'] ?? ''));

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
            (int) get_option('wp_page_for_privacy_policy') => 'Privacy Policy Page',
            (int) get_option('meza_page_for_cookie_policy') => 'Cookie Policy Page',
        ];

        return $special_pages[$post_id] ?? '';
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
                    'group' => 'Custom Template',
                    'template_label' => 'Single ' . $singular_label . ' Page',
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

if (!function_exists('meza_site_documentation_auto_template_files')) {
    function meza_site_documentation_auto_template_files(): array
    {
        return [
            'page-site-documentation.php',
            'page-style.php',
        ];
    }
}

if (!function_exists('meza_site_documentation_style_guide_url')) {
    function meza_site_documentation_style_guide_url(): string
    {
        return meza_site_documentation_find_page_url_by_template('page-style.php');
    }
}

if (!function_exists('meza_site_documentation_url')) {
    function meza_site_documentation_url(): string
    {
        return meza_site_documentation_find_page_url_by_template('page-site-documentation.php');
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
            'style_guide' => [
                'label' => 'Style Guide',
                'url' => meza_site_documentation_style_guide_url(),
            ],
            'documentation' => [
                'label' => 'Documentation',
                'url' => meza_site_documentation_url(),
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
            'overview_intro' => meza_site_documentation_field_text(
                'site_documentation_overview_intro',
                (string) ($defaults['site_documentation_overview_intro'] ?? '')
            ),
            'support_note' => meza_site_documentation_field_text(
                'site_documentation_support_note',
                (string) ($defaults['site_documentation_support_note'] ?? '')
            ),
            'quick_access_intro' => meza_site_documentation_field_text(
                'site_documentation_quick_access_intro',
                (string) ($defaults['site_documentation_quick_access_intro'] ?? '')
            ),
            'content_management_intro' => meza_site_documentation_field_text(
                'site_documentation_content_management_intro',
                (string) ($defaults['site_documentation_content_management_intro'] ?? '')
            ),
            'pages_intro' => meza_site_documentation_field_text(
                'site_documentation_pages_intro',
                (string) ($defaults['site_documentation_pages_intro'] ?? '')
            ),
            'content_types_intro' => meza_site_documentation_field_text(
                'site_documentation_content_types_intro',
                (string) ($defaults['site_documentation_content_types_intro'] ?? '')
            ),
            'page_templates_intro' => meza_site_documentation_field_text(
                'site_documentation_page_templates_intro',
                (string) ($defaults['site_documentation_page_templates_intro'] ?? '')
            ),
            'capabilities_intro' => meza_site_documentation_field_text(
                'site_documentation_capabilities_intro',
                (string) ($defaults['site_documentation_capabilities_intro'] ?? '')
            ),
            'dashboards_intro' => meza_site_documentation_field_text(
                'site_documentation_dashboards_intro',
                (string) ($defaults['site_documentation_dashboards_intro'] ?? '')
            ),
            'tools_intro' => meza_site_documentation_field_text(
                'site_documentation_tools_intro',
                (string) ($defaults['site_documentation_tools_intro'] ?? '')
            ),
            'analytics_intro' => meza_site_documentation_field_text(
                'site_documentation_analytics_intro',
                (string) ($defaults['site_documentation_analytics_intro'] ?? '')
            ),
            'plugins_intro' => meza_site_documentation_field_text(
                'site_documentation_plugins_intro',
                (string) ($defaults['site_documentation_plugins_intro'] ?? '')
            ),
            'themes_intro' => meza_site_documentation_field_text(
                'site_documentation_themes_intro',
                (string) ($defaults['site_documentation_themes_intro'] ?? '')
            ),
        ];
    }
}

if (!function_exists('meza_site_documentation_get_analytics_account_overrides')) {
    function meza_site_documentation_get_analytics_account_overrides(): array
    {
        if (!function_exists('get_field')) {
            return [];
        }

        $rows = meza_site_documentation_merge_analytics_rows(
            meza_site_documentation_default_analytics_field_rows(),
            get_field('site_documentation_analytics_accounts', 'option')
        );

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
                'description' => trim((string) ($row['description'] ?? '')),
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
            $page = $post_id > 0 ? get_post($post_id) : null;
            $links = [
                meza_site_documentation_make_link('View', (string) ($row['url'] ?? '')),
            ];

            if ($page instanceof WP_Post && !meza_site_documentation_is_auto_template_page($page)) {
                $links[] = meza_site_documentation_make_link('Edit', admin_url('post.php?post=' . $post_id . '&action=edit'));
            }

            return meza_site_documentation_filter_links($links);
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
                    'wordpress_importer' => admin_url('admin.php?import=wordpress'),
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
    function meza_site_documentation_is_documentation_page(WP_Post $page): bool
    {
        $documentation_page_id = (int) get_option('meza_page_for_documentation');
        $style_guide_page_id = (int) get_option('meza_page_for_style_guide');

        if (in_array((int) $page->ID, [$documentation_page_id, $style_guide_page_id], true)) {
            return true;
        }

        $template = (string) get_page_template_slug($page->ID);

        return in_array($template, ['page-site-documentation.php', 'page-style.php'], true);
    }
}

if (!function_exists('meza_site_documentation_is_auto_template_page')) {
    function meza_site_documentation_is_auto_template_page(WP_Post $page): bool
    {
        if (meza_site_documentation_is_documentation_page($page)) {
            return true;
        }

        return meza_site_documentation_special_page_label((int) $page->ID) === 'Cookie Policy Page';
    }
}

if (!function_exists('meza_site_documentation_page_group_label')) {
    function meza_site_documentation_page_has_attached_form(WP_Post $page): bool
    {
        if (function_exists('get_field')) {
            $section_form = get_field('section_form', $page->ID);

            if (is_array($section_form) && !empty($section_form['form'])) {
                return true;
            }
        }

        $meta_value = get_post_meta($page->ID, 'section_form_form', true);

        if (is_array($meta_value)) {
            return !empty($meta_value);
        }

        return trim((string) $meta_value) !== '';
    }
}

if (!function_exists('meza_site_documentation_page_group_label')) {
    function meza_site_documentation_page_group_label(WP_Post $page): string
    {
        $slug = sanitize_title((string) $page->post_name);
        $title = strtolower(trim((string) $page->post_title));

        if (meza_site_documentation_is_documentation_page($page)) {
            return 'Documentation';
        }

        foreach (['privacy', 'cookie', 'terms', 'conditions', 'legal', 'accessibility', 'disclaimer'] as $needle) {
            if (str_contains($slug, $needle) || str_contains($title, $needle)) {
                return 'Legal';
            }
        }

        if (meza_site_documentation_page_has_attached_form($page)) {
            return 'Pages (Form)';
        }

        return 'Pages';
    }
}

if (!function_exists('meza_site_documentation_page_group_sort_key')) {
    function meza_site_documentation_page_group_sort_key(WP_Post $page): string
    {
        $group_label = meza_site_documentation_page_group_label($page);

        if ($group_label === 'Pages (Form)') {
            return 'Pages';
        }

        return $group_label;
    }
}

if (!function_exists('meza_site_documentation_archive_post_types_for_page')) {
    function meza_site_documentation_archive_post_types_for_page(WP_Post $page): array
    {
        $posts_page_id = (int) get_option('page_for_posts');
        if ($page->ID === $posts_page_id) {
            return ['post'];
        }

        $page_slug = sanitize_title((string) $page->post_name);
        $matches = [];
        $post_types = get_post_types(['public' => true], 'objects');

        foreach ($post_types as $post_type => $post_type_object) {
            if (!($post_type_object instanceof WP_Post_Type) || in_array($post_type, ['page', 'attachment'], true)) {
                continue;
            }

            $candidates = [
                sanitize_title($post_type),
                sanitize_title((string) ($post_type_object->labels->singular_name ?? '')),
                sanitize_title((string) ($post_type_object->labels->name ?? '')),
                sanitize_title((string) (($post_type_object->rewrite['slug'] ?? ''))),
            ];

            if (!empty($post_type_object->has_archive)) {
                $candidates[] = sanitize_title(is_string($post_type_object->has_archive) ? $post_type_object->has_archive : $post_type);
            }

            $candidates = array_values(array_filter(array_unique($candidates)));

            if (in_array($page_slug, $candidates, true)) {
                $matches[] = (string) $post_type;
            }
        }

        return $matches;
    }
}

if (!function_exists('meza_site_documentation_post_type_plural_label')) {
    function meza_site_documentation_post_type_plural_label(WP_Post_Type $post_type_object): string
    {
        $label = trim((string) ($post_type_object->labels->name ?? ''));

        if ($label !== '') {
            return $label;
        }

        return ucfirst((string) $post_type_object->name);
    }
}

if (!function_exists('meza_site_documentation_post_type_group_label')) {
    function meza_site_documentation_post_type_group_label(WP_Post_Type $post_type_object): string
    {
        return meza_site_documentation_post_type_plural_label($post_type_object);
    }
}

if (!function_exists('meza_site_documentation_page_group_weight')) {
    function meza_site_documentation_page_group_weight(string $group_label): int
    {
        $weights = [
            'Pages' => 10,
            'Legal' => 998,
            'Documentation' => 999,
        ];

        return $weights[$group_label] ?? 50;
    }
}

if (!function_exists('meza_site_documentation_page_group_sort_label')) {
    function meza_site_documentation_page_group_sort_label(string $group_label): string
    {
        $normalized = strtolower(trim($group_label));

        if (in_array($normalized, ['faq', 'faqs'], true)) {
            return 'zzzz-faqs';
        }

        return $normalized;
    }
}

if (!function_exists('meza_site_documentation_post_visibility_label')) {
    function meza_site_documentation_post_visibility_label(WP_Post $post): string
    {
        if ($post->post_status === 'private' || !empty($post->post_password)) {
            return 'Private';
        }

        $noindex_meta = (string) get_post_meta($post->ID, '_yoast_wpseo_meta-robots-noindex', true);
        if ($noindex_meta === '1') {
            return 'Public by Link';
        }

        return 'Public and Indexable';
    }
}

if (!function_exists('meza_site_documentation_has_public_permalink')) {
    function meza_site_documentation_has_public_permalink(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        $parsed_url = wp_parse_url($url);
        if (!is_array($parsed_url)) {
            return false;
        }

        if (!empty($parsed_url['query'])) {
            return false;
        }

        return !empty($parsed_url['path']);
    }
}

if (!function_exists('meza_site_documentation_page_sort_weight')) {
    function meza_site_documentation_page_sort_weight(array $row): int
    {
        $group = (string) ($row['group_sort'] ?? $row['group'] ?? '');
        $front_page_id = (int) get_option('page_on_front');

        if ($group === 'Pages' && (int) ($row['post_id'] ?? 0) === $front_page_id) {
            return 0;
        }

        if ($group !== 'Pages' && !empty($row['is_archive_page'])) {
            return 0;
        }

        return 100;
    }
}

if (!function_exists('meza_site_documentation_archive_page_matches')) {
    function meza_site_documentation_archive_page_matches(WP_Post_Type $post_type_object, WP_Post $page): bool
    {
        $posts_page_id = (int) get_option('page_for_posts');
        if ((int) $page->ID === $posts_page_id) {
            return (string) $post_type_object->name === 'post';
        }

        $page_slug = sanitize_title((string) $page->post_name);
        $page_title = sanitize_title((string) $page->post_title);
        $post_type = (string) $post_type_object->name;

        $candidates = [
            sanitize_title($post_type),
            sanitize_title((string) ($post_type_object->labels->singular_name ?? '')),
            sanitize_title((string) ($post_type_object->labels->name ?? '')),
            sanitize_title((string) (($post_type_object->rewrite['slug'] ?? ''))),
        ];

        if (!empty($post_type_object->has_archive)) {
            $candidates[] = sanitize_title(is_string($post_type_object->has_archive) ? $post_type_object->has_archive : $post_type);
        }

        $candidates = array_values(array_filter(array_unique($candidates)));

        return in_array($page_slug, $candidates, true) || in_array($page_title, $candidates, true);
    }
}

if (!function_exists('meza_site_documentation_archive_post_type_map')) {
    function meza_site_documentation_archive_post_type_map(array $pages): array
    {
        $map = [];
        $posts_page_id = (int) get_option('page_for_posts');
        $post_types = get_post_types(['public' => true], 'objects');

        foreach ($post_types as $post_type => $post_type_object) {
            if (!($post_type_object instanceof WP_Post_Type) || in_array($post_type, ['page', 'attachment'], true)) {
                continue;
            }

            if ($post_type === 'post' && $posts_page_id > 0) {
                foreach ($pages as $page) {
                    if ($page instanceof WP_Post && (int) $page->ID === $posts_page_id) {
                        $map[$post_type] = $page;
                        break;
                    }
                }
                continue;
            }

            foreach ($pages as $page) {
                if (!($page instanceof WP_Post)) {
                    continue;
                }

                if (meza_site_documentation_archive_page_matches($post_type_object, $page)) {
                    $map[$post_type] = $page;
                    break;
                }
            }
        }

        return $map;
    }
}

if (!function_exists('meza_site_documentation_archive_post_type_objects')) {
    function meza_site_documentation_archive_post_type_objects(array $pages): array
    {
        $post_types = get_post_types(['public' => true], 'objects');
        $archive_page_map = meza_site_documentation_archive_post_type_map($pages);
        $items = [];

        foreach ($post_types as $post_type => $post_type_object) {
            if (!($post_type_object instanceof WP_Post_Type) || in_array($post_type, ['page', 'attachment'], true)) {
                continue;
            }

            $counts = wp_count_posts($post_type);
            $item_count = 0;

            if (is_object($counts)) {
                $item_count += (int) ($counts->publish ?? 0);
                $item_count += (int) ($counts->private ?? 0);
            }

            if ($item_count < 1) {
                continue;
            }

            $items[$post_type] = $post_type_object;
        }

        return $items;
    }
}

if (!function_exists('meza_site_documentation_get_page_rows')) {
    function meza_site_documentation_get_page_rows(): array
    {
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => ['publish', 'private'],
            'posts_per_page' => -1,
            'orderby' => [
                'menu_order' => 'ASC',
                'title' => 'ASC',
            ],
        ]);

        $role_labels = meza_site_documentation_role_labels_for_capability('edit_pages');
        $archive_page_map = meza_site_documentation_archive_post_type_map($pages);
        $archive_post_types = meza_site_documentation_archive_post_type_objects($pages);
        $rows = [];

        foreach ($archive_post_types as $post_type => $post_type_object) {
            $archive_page = $archive_page_map[$post_type] ?? null;
            $edit_capability = isset($post_type_object->cap->edit_posts)
                ? (string) $post_type_object->cap->edit_posts
                : 'edit_posts';
            $archive_role_labels = meza_site_documentation_role_labels_for_capability($edit_capability);
            $archive_family = meza_site_documentation_post_type_plural_label($post_type_object);
            $group_label = meza_site_documentation_post_type_group_label($post_type_object);
            $archive_title = $archive_page instanceof WP_Post
                ? trim((string) $archive_page->post_title)
                : $archive_family;
            $archive_url = $archive_page instanceof WP_Post
                ? get_permalink($archive_page)
                : get_post_type_archive_link($post_type);

            if (is_string($archive_url) && meza_site_documentation_has_public_permalink($archive_url)) {
                $archive_visibility = $archive_page instanceof WP_Post
                    ? meza_site_documentation_post_visibility_label($archive_page)
                    : 'Public and Indexable';

                $archive_row = [
                    'key' => sanitize_key($post_type . '_archive'),
                    'post_id' => $archive_page instanceof WP_Post ? (int) $archive_page->ID : 0,
                    'group' => $group_label,
                    'group_sort' => $group_label,
                    'archive_family' => $archive_family,
                    'title' => $archive_title !== '' ? $archive_title : $archive_family,
                    'slug' => sanitize_title(is_string($archive_url) ? $archive_url : $post_type),
                    'url' => $archive_url,
                    'visibility' => $archive_visibility,
                    'roles_text' => meza_site_documentation_format_role_labels($archive_role_labels),
                    'action' => 'View / Edit',
                    'menu_order' => $archive_page instanceof WP_Post ? (int) $archive_page->menu_order : 0,
                    'is_archive_page' => true,
                ];

                if (meza_site_documentation_should_include_row($archive_row, 'pages')) {
                    $archive_row['action_links'] = meza_site_documentation_get_row_action_links('pages', $archive_row);
                    $rows[] = $archive_row;
                }
            }

            $items = get_posts([
                'post_type' => $post_type,
                'post_status' => ['publish', 'private'],
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
            ]);

            foreach ($items as $item) {
                if (!($item instanceof WP_Post)) {
                    continue;
                }

                $item_url = get_permalink($item);
                if (!is_string($item_url) || !meza_site_documentation_has_public_permalink($item_url)) {
                    continue;
                }

                $item_row = [
                    'key' => sanitize_key($post_type . '_' . ($item->post_name !== '' ? (string) $item->post_name : (string) $item->ID)),
                    'post_id' => (int) $item->ID,
                    'group' => $group_label,
                    'group_sort' => $group_label,
                    'archive_family' => $archive_family,
                    'title' => trim((string) $item->post_title),
                    'slug' => sanitize_title((string) $item->post_name),
                    'url' => $item_url,
                    'visibility' => meza_site_documentation_post_visibility_label($item),
                    'roles_text' => meza_site_documentation_format_role_labels($archive_role_labels),
                    'action' => 'View / Edit',
                    'menu_order' => 0,
                    'is_archive_page' => false,
                ];

                if (!meza_site_documentation_should_include_row($item_row, 'pages')) {
                    continue;
                }

                $item_row['action_links'] = meza_site_documentation_get_row_action_links('pages', $item_row);
                $rows[] = $item_row;
            }
        }

        $archive_page_ids = array_values(array_filter(array_map(static function ($page): int {
            return $page instanceof WP_Post ? (int) $page->ID : 0;
        }, $archive_page_map)));

        foreach ($pages as $page) {
            if (!($page instanceof WP_Post)) {
                continue;
            }

            if (in_array((int) $page->ID, $archive_page_ids, true)) {
                continue;
            }

            if (get_post_status((int) $page->ID) === false) {
                continue;
            }

            $page_url = get_permalink($page);
            if (!is_string($page_url) || !meza_site_documentation_has_public_permalink($page_url)) {
                continue;
            }

            $row = [
                'key' => $page->post_name !== '' ? sanitize_key((string) $page->post_name) : 'page_' . (int) $page->ID,
                'post_id' => (int) $page->ID,
                'group' => meza_site_documentation_page_group_label($page),
                'group_sort' => meza_site_documentation_page_group_sort_key($page),
                'archive_family' => '',
                'title' => trim((string) $page->post_title),
                'slug' => sanitize_title((string) $page->post_name),
                'url' => $page_url,
                'visibility' => meza_site_documentation_post_visibility_label($page),
                'roles_text' => meza_site_documentation_format_role_labels($role_labels),
                'action' => 'View / Edit',
                'menu_order' => (int) $page->menu_order,
                'is_archive_page' => false,
            ];

            if (!meza_site_documentation_should_include_row($row, 'pages')) {
                continue;
            }

            $row['action_links'] = meza_site_documentation_get_row_action_links('pages', $row);
            $rows[] = $row;
        }

        usort($rows, static function (array $left, array $right): int {
            $left_group_weight = meza_site_documentation_page_group_weight((string) ($left['group_sort'] ?? $left['group'] ?? ''));
            $right_group_weight = meza_site_documentation_page_group_weight((string) ($right['group_sort'] ?? $right['group'] ?? ''));

            if ($left_group_weight !== $right_group_weight) {
                return $left_group_weight <=> $right_group_weight;
            }

            $left_group_label = (string) ($left['group'] ?? '');
            $right_group_label = (string) ($right['group'] ?? '');
            $left_group_sort = (string) ($left['group_sort'] ?? '');
            $right_group_sort = (string) ($right['group_sort'] ?? '');

            if ($left_group_label !== $right_group_label && $left_group_sort !== $right_group_sort) {
                return strnatcasecmp(
                    meza_site_documentation_page_group_sort_label($left_group_label),
                    meza_site_documentation_page_group_sort_label($right_group_label)
                );
            }

            $left_sort_weight = meza_site_documentation_page_sort_weight($left);
            $right_sort_weight = meza_site_documentation_page_sort_weight($right);

            if ($left_sort_weight !== $right_sort_weight) {
                return $left_sort_weight <=> $right_sort_weight;
            }

            $left_menu_order = (int) ($left['menu_order'] ?? 0);
            $right_menu_order = (int) ($right['menu_order'] ?? 0);

            if ((string) ($left['group_sort'] ?? $left['group'] ?? '') === 'Pages' && $left_menu_order !== $right_menu_order) {
                return $left_menu_order <=> $right_menu_order;
            }

            return strnatcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });

        return $rows;
    }
}

if (!function_exists('meza_site_documentation_page_template_roles_text')) {
    function meza_site_documentation_page_template_roles_text(string $group, string $template_label): string
    {
        if (in_array($template_label, ['Front Page', 'Posts Page'], true)) {
            return meza_site_documentation_format_role_labels(['Administrator']);
        }

        if (in_array($template_label, ['Documentation Page', 'Style Guide Page'], true)) {
            return meza_site_documentation_format_role_labels(['Administrator']);
        }

        if ($template_label === 'Cookie Policy Page') {
            return meza_site_documentation_format_role_labels(['Site Manager', 'Administrator']);
        }

        if ($group === 'Auto Template') {
            return '';
        }

        return meza_site_documentation_format_role_labels(
            meza_site_documentation_role_labels_for_capability('edit_pages')
        );
    }
}

if (!function_exists('meza_site_documentation_page_template_action_links')) {
    function meza_site_documentation_page_template_action_links(string $template_label, string $row_key = ''): array
    {
        $settings_url = '';

        if (in_array($template_label, ['Front Page', 'Posts Page', 'Documentation Page', 'Style Guide Page'], true)) {
            $settings_url = admin_url('options-reading.php');
        } elseif (in_array($template_label, ['Privacy Policy Page', 'Cookie Policy Page'], true)) {
            $settings_url = admin_url('options-privacy.php');
        }

        if ($settings_url !== '') {
            return [
                [
                    'label' => 'Change',
                    'url' => $settings_url,
                ],
            ];
        }

        if (strpos($row_key, 'single_') === 0) {
            $post_type = substr($row_key, strlen('single_'));
            $post_type = is_string($post_type) ? trim($post_type) : '';

            if ($post_type !== '') {
                return [
                    [
                        'label' => 'Manage',
                        'url' => admin_url('edit.php?post_type=' . $post_type),
                    ],
                ];
            }
        }

        return [];
    }
}

if (!function_exists('meza_site_documentation_get_page_template_rows')) {
    function meza_site_documentation_get_page_template_rows(): array
    {
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => ['publish', 'private'],
            'posts_per_page' => -1,
            'orderby' => [
                'menu_order' => 'ASC',
                'title' => 'ASC',
            ],
        ]);

        $rows = [];
        $template_map = [];

        foreach (meza_site_documentation_discover_page_templates() as $template_definition) {
            $template_file = trim((string) ($template_definition['file'] ?? ''));
            $template_label = trim((string) ($template_definition['label'] ?? ''));

            if ($template_file === '' || $template_label === '') {
                continue;
            }

            $template_key = sanitize_key('template_' . $template_file);

            $template_map[$template_file] = $template_key;
            $rows[$template_key] = [
                'key' => $template_key,
                'group' => in_array($template_file, meza_site_documentation_auto_template_files(), true)
                    ? 'Auto Template'
                    : 'Custom Template',
                'template_label' => $template_label,
                'page_references' => [],
                'roles_text' => meza_site_documentation_page_template_roles_text(
                    in_array($template_file, meza_site_documentation_auto_template_files(), true)
                        ? 'Auto Template'
                        : 'Custom Template',
                    $template_label
                ),
                'action_links' => meza_site_documentation_page_template_action_links($template_label, $template_key),
            ];
        }

        foreach ($pages as $page) {
            if (!($page instanceof WP_Post)) {
                continue;
            }

            if (get_post_status((int) $page->ID) === false) {
                continue;
            }

            $page_reference = meza_site_documentation_page_reference_data($page);
            $special_page_label = meza_site_documentation_special_page_label((int) $page->ID);
            $page_type_meta_label = meza_site_documentation_page_type_meta_label((int) $page->ID);
            $page_type_term_label = meza_site_documentation_page_type_term_label((int) $page->ID);
            $template_slug = trim((string) get_page_template_slug($page->ID));

            $row_key = '';
            $row_group = '';
            $row_label = '';

            if ($special_page_label !== '') {
                $row_key = sanitize_key('page_type_' . $special_page_label);
                $row_group = $special_page_label === 'Cookie Policy Page'
                    ? 'Auto Template'
                    : 'Core Template';
                $row_label = $special_page_label;
            } elseif ($page_type_meta_label !== '') {
                $row_key = sanitize_key('page_type_' . $page_type_meta_label);
                $row_group = 'Core Template';
                $row_label = $page_type_meta_label;
            } elseif ($page_type_term_label !== '') {
                $row_key = sanitize_key('page_type_' . $page_type_term_label);
                $row_group = 'Core Template';
                $row_label = $page_type_term_label;
            } elseif ($template_slug !== '') {
                $row_key = $template_map[$template_slug] ?? sanitize_key('template_' . $template_slug);
                $row_group = in_array($template_slug, meza_site_documentation_auto_template_files(), true)
                    ? 'Auto Template'
                    : 'Custom Template';
                $row_label = isset($rows[$row_key]['template_label'])
                    ? (string) $rows[$row_key]['template_label']
                    : ucwords(str_replace(['page-', '.php', '-'], ['', '', ' '], $template_slug));
            } else {
                $row_key = 'basic_page';
                $row_group = 'Core Template';
                $row_label = 'Basic Page';
            }

            if (!isset($rows[$row_key])) {
                $rows[$row_key] = [
                    'key' => $row_key,
                    'group' => $row_group,
                    'template_label' => $row_label,
                    'page_references' => [],
                    'roles_text' => meza_site_documentation_page_template_roles_text($row_group, $row_label),
                    'action_links' => meza_site_documentation_page_template_action_links($row_label, $row_key),
                ];
            }

            $rows[$row_key]['page_references'][] = $page_reference;
            $rows[$row_key]['roles_text'] = meza_site_documentation_page_template_roles_text(
                (string) ($rows[$row_key]['group'] ?? ''),
                (string) ($rows[$row_key]['template_label'] ?? '')
            );
            $rows[$row_key]['action_links'] = meza_site_documentation_page_template_action_links(
                (string) ($rows[$row_key]['template_label'] ?? ''),
                (string) ($rows[$row_key]['key'] ?? $row_key)
            );
        }

        foreach (meza_site_documentation_single_template_rows() as $single_template_row) {
            $single_template_key = sanitize_key((string) ($single_template_row['key'] ?? ''));

            if ($single_template_key === '' || isset($rows[$single_template_key])) {
                continue;
            }

            $single_template_row['roles_text'] = meza_site_documentation_page_template_roles_text(
                (string) ($single_template_row['group'] ?? ''),
                (string) ($single_template_row['template_label'] ?? '')
            );
            $single_template_row['action_links'] = meza_site_documentation_page_template_action_links(
                (string) ($single_template_row['template_label'] ?? ''),
                (string) ($single_template_row['key'] ?? $single_template_key)
            );
            $rows[$single_template_key] = $single_template_row;
        }

        foreach ($rows as $row_key => &$row) {
            if (!meza_site_documentation_should_include_row($row, 'page_templates')) {
                unset($rows[$row_key]);
                continue;
            }

            $page_references = array_values(array_filter((array) ($row['page_references'] ?? []), static function ($page_reference): bool {
                return is_array($page_reference);
            }));
            $row['page_references'] = $page_references;
        }
        unset($row);

        $rows = array_values(array_filter($rows, static function ($row): bool {
            return is_array($row)
                && trim((string) ($row['template_label'] ?? '')) !== '';
        }));

        usort($rows, static function (array $left, array $right): int {
            $weights = [
                'Core Template' => 10,
                'Custom Template' => 20,
                'Auto Template' => 30,
            ];
            $core_template_order = [
                'Front Page' => 10,
                'Posts Page' => 20,
                'Basic Page' => 30,
                'Privacy Policy Page' => 40,
            ];
            $auto_template_order = [
                'FAQ Page' => 10,
                'Cookie Policy Page' => 20,
                'Documentation Page' => 30,
                'Style Guide Page' => 40,
            ];

            $left_group = (string) ($left['group'] ?? '');
            $right_group = (string) ($right['group'] ?? '');
            $left_weight = $weights[$left_group] ?? 50;
            $right_weight = $weights[$right_group] ?? 50;

            if ($left_weight !== $right_weight) {
                return $left_weight <=> $right_weight;
            }

            if ($left_group === 'Core Template' && $right_group === 'Core Template') {
                $left_label = (string) ($left['template_label'] ?? '');
                $right_label = (string) ($right['template_label'] ?? '');
                $left_core_weight = $core_template_order[$left_label] ?? 1000;
                $right_core_weight = $core_template_order[$right_label] ?? 1000;

                if ($left_core_weight !== $right_core_weight) {
                    return $left_core_weight <=> $right_core_weight;
                }
            }

            if ($left_group === 'Auto Template' && $right_group === 'Auto Template') {
                $left_label = (string) ($left['template_label'] ?? '');
                $right_label = (string) ($right['template_label'] ?? '');
                $left_auto_weight = $auto_template_order[$left_label] ?? 1000;
                $right_auto_weight = $auto_template_order[$right_label] ?? 1000;

                if ($left_auto_weight !== $right_auto_weight) {
                    return $left_auto_weight <=> $right_auto_weight;
                }
            }

            return strnatcasecmp(
                (string) ($left['template_label'] ?? ''),
                (string) ($right['template_label'] ?? '')
            );
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

if (!function_exists('meza_site_documentation_post_type_has_public_permalink')) {
    function meza_site_documentation_post_type_has_public_permalink(WP_Post_Type $post_type_object): bool
    {
        if (in_array((string) $post_type_object->name, ['post', 'page'], true)) {
            return true;
        }

        if (!$post_type_object->public || !$post_type_object->publicly_queryable) {
            return false;
        }

        return !empty($post_type_object->rewrite) || !empty($post_type_object->has_archive);
    }
}

if (!function_exists('meza_site_documentation_taxonomy_has_public_permalink')) {
    function meza_site_documentation_taxonomy_has_public_permalink(WP_Taxonomy $taxonomy): bool
    {
        return $taxonomy->public && (!empty($taxonomy->rewrite) || !empty($taxonomy->query_var));
    }
}

if (!function_exists('meza_site_documentation_content_type_is_public_by_default')) {
    function meza_site_documentation_content_type_is_public_by_default(
        bool $has_public_permalink,
        bool $yoast_active = false
    ): bool {
        if (!$has_public_permalink) {
            return false;
        }

        return $yoast_active;
    }
}

if (!function_exists('meza_site_documentation_content_type_visibility_label')) {
    function meza_site_documentation_content_type_visibility_label(
        bool $has_public_permalink,
        bool $is_public_by_default
    ): string
    {
        if ($is_public_by_default) {
            return 'Public and Indexable by Default';
        }

        if ($has_public_permalink) {
            return 'Content Only by Default';
        }

        return 'Content Only';
    }
}

if (!function_exists('meza_site_documentation_get_content_type_rows')) {
    function meza_site_documentation_get_content_type_rows(): array
    {
        $post_type_objects = get_post_types([], 'objects');
        $rows = [];
        $yoast_active = meza_site_documentation_is_plugin_active('wordpress-seo/wp-seo.php');

        foreach ($post_type_objects as $post_type_object) {
            if (!($post_type_object instanceof WP_Post_Type) || !meza_site_documentation_is_documented_post_type($post_type_object)) {
                continue;
            }

            $post_type = (string) $post_type_object->name;
            $edit_capability = isset($post_type_object->cap->edit_posts)
                ? (string) $post_type_object->cap->edit_posts
                : '';
            $post_type_has_public_permalink = meza_site_documentation_post_type_has_public_permalink($post_type_object);
            $post_type_is_public_by_default = $post_type_has_public_permalink;

            $post_type_row = [
                'key' => sanitize_key($post_type),
                'group' => (string) ($post_type_object->labels->name ?? ucfirst($post_type)),
                'item' => (string) ($post_type_object->labels->name ?? ucfirst($post_type)),
                'visibility' => meza_site_documentation_content_type_visibility_label(
                    $post_type_has_public_permalink,
                    $post_type_is_public_by_default
                ),
                'has_public_permalink' => $post_type_has_public_permalink,
                'is_public_by_default' => $post_type_is_public_by_default,
                'roles_text' => meza_site_documentation_format_role_labels(
                    meza_site_documentation_role_labels_for_capability($edit_capability)
                ),
                'action' => 'Edit',
                'kind' => 'post_type',
                'action_url' => admin_url('edit.php?post_type=' . $post_type),
            ];

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
                $taxonomy_has_public_permalink = meza_site_documentation_taxonomy_has_public_permalink($taxonomy);
                $taxonomy_is_public_by_default = meza_site_documentation_content_type_is_public_by_default(
                    $taxonomy_has_public_permalink,
                    $yoast_active
                );

                $taxonomy_row = [
                    'key' => sanitize_key($post_type . '_' . (string) $taxonomy->name),
                    'group' => (string) ($post_type_object->labels->name ?? ucfirst($post_type)),
                    'item' => (string) ($taxonomy->labels->name ?? ucfirst((string) $taxonomy->name)),
                    'visibility' => meza_site_documentation_content_type_visibility_label(
                        $taxonomy_has_public_permalink,
                        $taxonomy_is_public_by_default
                    ),
                    'has_public_permalink' => $taxonomy_has_public_permalink,
                    'is_public_by_default' => $taxonomy_is_public_by_default,
                    'roles_text' => meza_site_documentation_format_role_labels(
                        meza_site_documentation_role_labels_for_capability($taxonomy_capability)
                    ),
                    'action' => 'Edit',
                    'kind' => 'taxonomy',
                    'action_url' => admin_url('edit-tags.php?taxonomy=' . (string) $taxonomy->name . '&post_type=' . $post_type),
                ];

                if (meza_site_documentation_should_include_row($taxonomy_row, 'content_types')) {
                    $taxonomy_row['action_links'] = meza_site_documentation_get_row_action_links('content_types', $taxonomy_row);
                    $rows[] = $taxonomy_row;
                }
            }
        }

        $group_has_public_permalink = [];

        foreach ($rows as $row) {
            $group_label = trim((string) ($row['group'] ?? ''));

            if ($group_label === '') {
                continue;
            }

            if (!array_key_exists($group_label, $group_has_public_permalink)) {
                $group_has_public_permalink[$group_label] = false;
            }

            if (!empty($row['is_public_by_default'])) {
                $group_has_public_permalink[$group_label] = true;
            }
        }

        usort($rows, static function (array $left, array $right) use ($group_has_public_permalink): int {
            $left_group = trim((string) ($left['group'] ?? ''));
            $right_group = trim((string) ($right['group'] ?? ''));

            $left_group_weight = !empty($group_has_public_permalink[$left_group]) ? 0 : 1;
            $right_group_weight = !empty($group_has_public_permalink[$right_group]) ? 0 : 1;

            if ($left_group_weight !== $right_group_weight) {
                return $left_group_weight <=> $right_group_weight;
            }

            $group_compare = strnatcasecmp($left_group, $right_group);
            if ($group_compare !== 0) {
                return $group_compare;
            }

            $left_permalink_weight = !empty($left['is_public_by_default']) ? 0 : 1;
            $right_permalink_weight = !empty($right['is_public_by_default']) ? 0 : 1;

            if ($left_permalink_weight !== $right_permalink_weight) {
                return $left_permalink_weight <=> $right_permalink_weight;
            }

            $left_kind_weight = (string) ($left['kind'] ?? '') === 'post_type' ? 0 : 1;
            $right_kind_weight = (string) ($right['kind'] ?? '') === 'post_type' ? 0 : 1;

            if ($left_kind_weight !== $right_kind_weight) {
                return $left_kind_weight <=> $right_kind_weight;
            }

            return strnatcasecmp((string) ($left['item'] ?? ''), (string) ($right['item'] ?? ''));
        });

        $group_counts = [];

        foreach ($rows as $row) {
            $group_label = trim((string) ($row['group'] ?? ''));

            if ($group_label === '') {
                continue;
            }

            $group_counts[$group_label] = ($group_counts[$group_label] ?? 0) + 1;
        }

        foreach ($rows as &$row) {
            $group_label = trim((string) ($row['group'] ?? ''));
            $item_label = trim((string) ($row['item'] ?? ''));

            if ($group_label === '' || ($group_counts[$group_label] ?? 0) !== 1) {
                continue;
            }

            if (strcasecmp($group_label, $item_label) === 0) {
                $row['group'] = '';
            }
        }
        unset($row);

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

if (!function_exists('meza_site_documentation_get_options_page_definition')) {
    function meza_site_documentation_get_options_page_definition(): array
    {
        return [
            'page_title' => 'Documentation Settings',
            'menu_title' => 'Documentation',
            'menu_slug' => meza_site_documentation_option_page_slug(),
            'parent_slug' => 'options-general.php',
            'capability' => 'manage_options',
            'redirect' => false,
            'update_button' => 'Update',
            'updated_message' => 'Documentation Updated',
            'autoload' => false,
        ];
    }
}

if (!function_exists('meza_site_documentation_section_copy_fields')) {
    function meza_site_documentation_section_copy_fields(): array
    {
        $defaults = meza_site_documentation_default_section_copy();
        $field_names = [
            'site_documentation_overview_intro' => 'Overview Intro',
            'site_documentation_support_note' => 'Support Note',
            'site_documentation_quick_access_intro' => 'Quick Access Intro',
            'site_documentation_content_management_intro' => 'Content Management Intro',
            'site_documentation_pages_intro' => 'Pages Intro',
            'site_documentation_content_types_intro' => 'Content Types Intro',
            'site_documentation_page_templates_intro' => 'Page Templates Intro',
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
                        'instructions' => 'Optional copy shown above this section of the documentation page.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'default_value' => (string) ($defaults[$field_name] ?? ''),
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
                'instructions' => 'Edit analytics descriptions and access details here. Clearing a field omits that value on the front-end.',
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
                        'key' => 'field_' . md5('site_documentation_analytics_accounts_description'),
                        'label' => 'Description',
                        'name' => 'description',
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

if (!function_exists('meza_site_documentation_options_groups')) {
    function meza_site_documentation_options_groups(): array
    {
        $slug = meza_site_documentation_option_page_slug();

        return [
            [
                'key' => 'group_' . md5('site_documentation_general'),
                'title' => 'Overview Copy',
                'fields' => [
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
        ];
    }
}

add_filter('acf/load_value/name=site_documentation_analytics_accounts', static function ($value, $post_id, array $field) {
    if ($post_id !== 'option' && $post_id !== 'options') {
        return $value;
    }

    return meza_site_documentation_merge_analytics_rows(
        meza_site_documentation_default_analytics_field_rows(),
        is_array($value) ? $value : []
    );
}, 20, 3);

add_filter('meza_shared_project_acf_options_pages', static function (array $pages): array {
    $pages[] = meza_site_documentation_get_options_page_definition();
    return $pages;
});

add_filter('meza_shared_project_acf_field_groups', static function (array $groups): array {
    return array_merge($groups, meza_site_documentation_options_groups());
});
