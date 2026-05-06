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

if (!function_exists('meza_site_documentation_contact_roles')) {
    function meza_site_documentation_contact_roles(): array
    {
        return [
            'administrator' => 'Administrator',
            meza_site_documentation_site_manager_role_key() => 'Site Manager',
            meza_site_documentation_seo_manager_role_key() => 'SEO Manager',
            meza_site_documentation_shop_manager_role_key() => 'Shop Manager',
        ];
    }
}

if (!function_exists('meza_site_documentation_access_roles')) {
    function meza_site_documentation_access_roles(): array
    {
        return [
            meza_site_documentation_seo_manager_role_key() => 'SEO Manager',
            meza_site_documentation_site_manager_role_key() => 'Site Manager',
            meza_site_documentation_shop_manager_role_key() => 'Shop Manager',
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

if (!function_exists('meza_site_documentation_default_section_copy')) {
    function meza_site_documentation_default_section_copy(): array
    {
        return [
            'site_documentation_key_links_intro' => 'Use these links to reach the live website, WordPress admin, and other primary destinations tied to the site.',
            'site_documentation_users_and_roles_intro' => 'These are the WordPress roles documented for the website and the users currently assigned to them when applicable.',
            'site_documentation_content_structure_intro' => 'This section outlines how website content is modeled in WordPress, including the templates and reusable building blocks that shape what appears on the front end.',
            'site_documentation_content_intro' => 'This section documents the live content inventory, separating public indexed URLs from content that is managed in WordPress without a front-end permalink.',
            'site_documentation_indexed_urls_intro' => 'These are the published URLs that can be reached on the website, grouped by page, post type, and public taxonomy structure.',
            'site_documentation_content_only_intro' => 'These content types and taxonomies are managed in WordPress but do not publish front-end URLs by default.',
            'site_documentation_content_types_intro' => 'These are the registered post types and taxonomies that support reusable website content, along with their default visibility and role-based management access.',
            'site_documentation_page_templates_intro' => 'These are the core and custom page templates currently available on the website and where they fit into the content structure.',
            'site_documentation_section_templates_intro' => 'These are the reusable section partials available in the theme codebase for assembling pages, archives, and landing-page layouts.',
            'site_documentation_management_capabilities_intro' => 'These are the ongoing maintenance responsibilities on the website, mapped to the documented access each primary role has.',
            'site_documentation_tech_stack_intro' => 'This section outlines the active platforms, plugins, and themes that support the website. Importance indicates how critical each item is to core functionality, maintenance, and troubleshooting.',
            'site_documentation_code_dependencies_intro' => 'These are the code packages used by the active theme stack for front-end behavior, asset compilation, and shared build tooling.',
            'site_documentation_plugins_intro' => '',
            'site_documentation_themes_intro' => '',
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
                $rows[] = [
                    'role' => $role_label,
                    'name' => '',
                    'user_login' => '',
                    'email' => '',
                    'note' => 'Not assigned',
                ];
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
            (int) get_option('meza_page_for_contact') => 'Contact Page',
            (int) get_option('meza_page_for_about') => 'About Page',
            (int) get_option('meza_page_for_faq') => 'FAQ Page',
            (int) get_option('meza_page_for_documentation') => 'Documentation Page',
            (int) get_option('meza_page_for_style_guide') => 'Style Guide Page',
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

