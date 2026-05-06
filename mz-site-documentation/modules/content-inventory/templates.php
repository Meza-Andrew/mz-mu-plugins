<?php

if (!function_exists('meza_site_documentation_page_template_roles_text')) {
    function meza_site_documentation_page_template_roles_text(string $group, string $template_label): string
    {
        if (in_array($template_label, ['Front Page', 'Posts Page'], true)) {
            return meza_site_documentation_format_role_labels(['Administrator']);
        }

        if (in_array($template_label, ['Contact Page', 'About Page', 'FAQ Page', 'Documentation Page', 'Style Guide Page'], true)) {
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
        $allowed_roles = [];

        if (in_array($template_label, ['Front Page', 'Posts Page', 'Contact Page', 'About Page', 'FAQ Page', 'Documentation Page', 'Style Guide Page'], true)) {
            $settings_url = admin_url('options-reading.php');
            $allowed_roles = ['administrator'];
        } elseif (in_array($template_label, ['Privacy Policy Page', 'Cookie Policy Page'], true)) {
            $settings_url = admin_url('options-privacy.php');
            $allowed_roles = [
                meza_site_documentation_site_manager_role_key(),
                'administrator',
            ];
        }

        if ($settings_url !== '') {
            return [
                meza_site_documentation_make_link('Change', $settings_url, [
                    'allowed_roles' => $allowed_roles,
                ]),
            ];
        }

        if (strpos($row_key, 'single_') === 0) {
            $post_type = substr($row_key, strlen('single_'));
            $post_type = is_string($post_type) ? trim($post_type) : '';

            if ($post_type !== '') {
                $post_type_object = get_post_type_object($post_type);
                $edit_capability = $post_type_object instanceof WP_Post_Type && isset($post_type_object->cap->edit_posts)
                    ? (string) $post_type_object->cap->edit_posts
                    : '';

                return [
                    meza_site_documentation_make_link('Manage', admin_url('edit.php?post_type=' . $post_type), [
                        'capability' => $edit_capability,
                    ]),
                ];
            }
        }

        return [];
    }
}

if (!function_exists('meza_site_documentation_is_supported_page_template_row_key')) {
    function meza_site_documentation_is_supported_page_template_row_key(string $row_key, array $template_keys): bool
    {
        $row_key = trim($row_key);

        if ($row_key === '' || $row_key === 'basic_page') {
            return true;
        }

        if (strpos($row_key, 'page_type_') === 0 || strpos($row_key, 'single_') === 0) {
            return true;
        }

        return in_array($row_key, $template_keys, true);
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
        $template_keys = [];

        foreach (meza_site_documentation_discover_page_templates() as $template_definition) {
            $template_file = trim((string) ($template_definition['file'] ?? ''));
            $template_label = trim((string) ($template_definition['label'] ?? ''));

            if ($template_file === '' || $template_label === '') {
                continue;
            }

            $template_key = sanitize_key('template_' . $template_file);

            $template_map[$template_file] = $template_key;
            $template_keys[] = $template_key;
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
                if ($special_page_label === 'Cookie Policy Page') {
                    $row_key = $template_map['page-cookie-policy.php'] ?? sanitize_key('template_page-cookie-policy.php');
                } elseif ($special_page_label === 'FAQ Page') {
                    $row_key = $template_map['page-faq.php'] ?? sanitize_key('template_page-faq.php');
                } elseif ($special_page_label === 'Documentation Page') {
                    $row_key = $template_map['page-documentation.php'] ?? sanitize_key('template_page-documentation.php');
                } elseif ($special_page_label === 'Style Guide Page') {
                    $row_key = $template_map['page-style.php'] ?? sanitize_key('template_page-style.php');
                } else {
                    $row_key = sanitize_key('page_type_' . $special_page_label);
                }

                $row_group = in_array($special_page_label, ['FAQ Page', 'Cookie Policy Page', 'Documentation Page', 'Style Guide Page'], true)
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
            if (!meza_site_documentation_is_supported_page_template_row_key($row_key, $template_keys)) {
                unset($rows[$row_key]);
                continue;
            }

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
                'Contact Page' => 30,
                'About Page' => 40,
                'Basic Page' => 50,
                'Privacy Policy Page' => 60,
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

if (!function_exists('meza_site_documentation_section_template_label')) {
    function meza_site_documentation_section_template_label(string $file): string
    {
        $file = basename($file, '.php');
        $label = str_replace(['list-', 'aside-', '-', '_'], ['List ', 'Aside ', ' ', ' '], $file);
        $label = trim(preg_replace('/\s+/', ' ', $label));

        return ucwords($label);
    }
}

if (!function_exists('meza_site_documentation_section_template_description')) {
    function meza_site_documentation_section_template_description(string $file): string
    {
        $file = basename($file, '.php');
        $normalized = strtolower(trim($file));

        if ($normalized === 'content') {
            return 'Reusable section partial for rendering primary rich-text content blocks.';
        }

        if ($normalized === 'form') {
            return 'Reusable section partial for embedding contact or lead-capture forms.';
        }

        if (str_starts_with($normalized, 'list-')) {
            $subject = ucwords(str_replace(['list-', '-', '_'], ['', ' ', ' '], $normalized));
            return 'Reusable listing section partial for ' . strtolower($subject) . ' content.';
        }

        return 'Reusable section partial used to assemble page layouts and modular content blocks.';
    }
}

if (!function_exists('meza_site_documentation_get_section_template_rows')) {
    function meza_site_documentation_get_section_template_rows(): array
    {
        $rows = [];

        foreach (meza_site_documentation_template_directories() as $directory) {
            $section_directory = trailingslashit($directory) . 'section';

            if (!is_dir($section_directory)) {
                continue;
            }

            $stylesheet = basename($directory);
            $theme = wp_get_theme($stylesheet);
            $group_label = $theme instanceof WP_Theme && $theme->exists()
                ? trim((string) $theme->get('Name'))
                : ucwords(str_replace(['-', '_'], ' ', $stylesheet));

            $paths = glob(trailingslashit($section_directory) . '*.php');
            if (!is_array($paths)) {
                continue;
            }

            foreach ($paths as $path) {
                if (!is_string($path) || !file_exists($path)) {
                    continue;
                }

                $file = basename($path);

                if ($file === 'documentation.php') {
                    continue;
                }

                $rows[] = [
                    'key' => sanitize_key($stylesheet . '_' . $file),
                    'group' => $group_label,
                    'label' => meza_site_documentation_section_template_label($file),
                    'description' => meza_site_documentation_section_template_description($file),
                ];
            }
        }

        usort($rows, static function (array $left, array $right): int {
            $group_compare = strnatcasecmp((string) ($left['group'] ?? ''), (string) ($right['group'] ?? ''));

            if ($group_compare !== 0) {
                return $group_compare;
            }

            return strnatcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return $rows;
    }
}

