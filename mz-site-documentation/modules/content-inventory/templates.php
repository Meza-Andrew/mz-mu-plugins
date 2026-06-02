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

        if (in_array($template_label, ['Privacy Policy Page', 'Cookie Policy Page'], true)) {
            return meza_site_documentation_format_role_labels(['Site Manager', 'Administrator']);
        }

        if ($group === 'System') {
            return '';
        }

        return meza_site_documentation_format_role_labels(
            meza_site_documentation_role_labels_for_capability('edit_pages')
        );
    }
}

if (!function_exists('meza_site_documentation_page_template_group_label')) {
    function meza_site_documentation_page_template_group_label(string $group): string
    {
        $group = trim($group);

        return match ($group) {
            'Core Template' => 'Default',
            'Custom Template' => 'Custom',
            'Auto Template' => 'System',
            default => $group,
        };
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

if (!function_exists('meza_site_documentation_page_template_description')) {
    function meza_site_documentation_page_template_description(string $group, string $template_label, string $row_key = ''): string
    {
        $group = trim($group);
        $template_label = trim($template_label);
        $row_key = trim($row_key);

        $descriptions = [
            'Front Page' => 'Default template used for the website homepage.',
            'Posts Page' => 'Default archive template used for the main blog or article listing page.',
            'Contact Page' => 'Default page template used for contact-focused pages and lead-capture destinations.',
            'About Page' => 'Default page template used for company overview and brand-story pages.',
            'Basic Page' => 'Default page template used for standard evergreen content pages.',
            'Privacy Policy Page' => 'System page template used for the site privacy policy.',
            'FAQ Page' => 'Default page template used for frequently asked questions.',
            'Cookie Policy Page' => 'System page template used for the site cookie policy.',
            'Documentation Page' => 'System page template used for internal website documentation.',
            'Style Guide Page' => 'System page template used for brand, UI, or content style references.',
        ];

        if ($template_label !== '' && isset($descriptions[$template_label])) {
            return $descriptions[$template_label];
        }

        if ($group === 'Custom' && $template_label !== '') {
            return 'Custom page template available for pages that need a specialized layout or content structure.';
        }

        if ($group === 'System' && $template_label !== '') {
            return 'Automatically assigned page template used for a specific system or utility page.';
        }

        if ($group === 'Default' && $template_label !== '') {
            return 'Default page template used for a standard website page type.';
        }

        if ($row_key !== '') {
            return '';
        }

        return '';
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
            $template_description = trim((string) ($template_definition['description'] ?? ''));
            $template_type = trim((string) ($template_definition['type'] ?? ''));

            if ($template_file === '' || $template_label === '') {
                continue;
            }

            $template_key = sanitize_key('template_' . $template_file);
            $normalized_group = meza_site_documentation_page_template_group_label(
                $template_type !== '' ? $template_type : (
                    in_array($template_file, meza_site_documentation_auto_template_files(), true)
                        ? 'Auto Template'
                        : 'Custom Template'
                )
            );

            $template_map[$template_file] = $template_key;
            $template_keys[] = $template_key;
            $rows[$template_key] = [
                'key' => $template_key,
                'group' => $normalized_group,
                'template_label' => $template_label,
                'description' => $template_description !== ''
                    ? $template_description
                    : meza_site_documentation_page_template_description($normalized_group, $template_label, $template_key),
                'page_references' => [],
                'roles_text' => meza_site_documentation_page_template_roles_text(
                    $normalized_group,
                    $template_label
                ),
                'action_links' => meza_site_documentation_finalize_links(
                    'page_templates',
                    [
                        'key' => $template_key,
                        'template_label' => $template_label,
                    ],
                    meza_site_documentation_page_template_action_links($template_label, $template_key)
                ),
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

                $row_group = in_array($special_page_label, ['Privacy Policy Page', 'Cookie Policy Page', 'Documentation Page', 'Style Guide Page'], true)
                    ? 'System'
                    : 'Default';
                $row_label = $special_page_label;
            } elseif ($page_type_meta_label !== '') {
                $row_key = sanitize_key('page_type_' . $page_type_meta_label);
                $row_group = 'Default';
                $row_label = $page_type_meta_label;
            } elseif ($page_type_term_label !== '') {
                $row_key = sanitize_key('page_type_' . $page_type_term_label);
                $row_group = 'Default';
                $row_label = $page_type_term_label;
            } elseif ($template_slug !== '') {
                if ($template_slug === 'page-form.php') {
                    $row_key = 'basic_page';
                    $row_group = 'Default';
                    $row_label = 'Basic Page';
                } else {
                    $row_key = $template_map[$template_slug] ?? sanitize_key('template_' . $template_slug);
                    $row_group = meza_site_documentation_page_template_group_label(
                        in_array($template_slug, meza_site_documentation_auto_template_files(), true)
                            ? 'Auto Template'
                            : 'Custom Template'
                    );
                    $row_label = isset($rows[$row_key]['template_label'])
                        ? (string) $rows[$row_key]['template_label']
                        : ucwords(str_replace(['page-', '.php', '-'], ['', '', ' '], $template_slug));
                }
            } else {
                $row_key = 'basic_page';
                $row_group = 'Default';
                $row_label = 'Basic Page';
            }

            if (!isset($rows[$row_key])) {
                $rows[$row_key] = [
                    'key' => $row_key,
                    'group' => $row_group,
                    'template_label' => $row_label,
                    'description' => meza_site_documentation_page_template_description($row_group, $row_label, $row_key),
                    'page_references' => [],
                    'roles_text' => meza_site_documentation_page_template_roles_text($row_group, $row_label),
                    'action_links' => meza_site_documentation_finalize_links(
                        'page_templates',
                        [
                            'key' => $row_key,
                            'template_label' => $row_label,
                        ],
                        meza_site_documentation_page_template_action_links($row_label, $row_key)
                    ),
                ];
            }

            $rows[$row_key]['group'] = $row_group;
            $rows[$row_key]['page_references'][] = $page_reference;
            $rows[$row_key]['description'] = meza_site_documentation_page_template_description(
                (string) ($rows[$row_key]['group'] ?? ''),
                (string) ($rows[$row_key]['template_label'] ?? ''),
                (string) ($rows[$row_key]['key'] ?? $row_key)
            );
            $rows[$row_key]['roles_text'] = meza_site_documentation_page_template_roles_text(
                (string) ($rows[$row_key]['group'] ?? ''),
                (string) ($rows[$row_key]['template_label'] ?? '')
            );
            $rows[$row_key]['action_links'] = meza_site_documentation_finalize_links(
                'page_templates',
                $rows[$row_key],
                meza_site_documentation_page_template_action_links(
                    (string) ($rows[$row_key]['template_label'] ?? ''),
                    (string) ($rows[$row_key]['key'] ?? $row_key)
                )
            );
        }

        if (!isset($rows['basic_page'])) {
            $rows['basic_page'] = [
                'key' => 'basic_page',
                'group' => 'Default',
                'template_label' => 'Basic Page',
                'description' => meza_site_documentation_page_template_description('Default', 'Basic Page', 'basic_page'),
                'page_references' => [],
                'roles_text' => meza_site_documentation_page_template_roles_text('Default', 'Basic Page'),
                'action_links' => meza_site_documentation_finalize_links(
                    'page_templates',
                    [
                        'key' => 'basic_page',
                        'template_label' => 'Basic Page',
                    ],
                    meza_site_documentation_page_template_action_links('Basic Page', 'basic_page')
                ),
            ];
        }

        foreach (meza_site_documentation_single_template_rows() as $single_template_row) {
            $single_template_key = sanitize_key((string) ($single_template_row['key'] ?? ''));

            if ($single_template_key === '' || isset($rows[$single_template_key])) {
                continue;
            }

            $single_template_row['group'] = meza_site_documentation_page_template_group_label(
                (string) ($single_template_row['group'] ?? '')
            );
            $single_template_row['roles_text'] = meza_site_documentation_page_template_roles_text(
                (string) ($single_template_row['group'] ?? ''),
                (string) ($single_template_row['template_label'] ?? '')
            );
            $single_template_row['description'] = trim((string) ($single_template_row['description'] ?? '')) !== ''
                ? trim((string) $single_template_row['description'])
                : meza_site_documentation_page_template_description(
                    (string) ($single_template_row['group'] ?? ''),
                    (string) ($single_template_row['template_label'] ?? ''),
                    (string) ($single_template_row['key'] ?? $single_template_key)
                );
            $single_template_row['action_links'] = meza_site_documentation_finalize_links(
                'page_templates',
                $single_template_row,
                meza_site_documentation_page_template_action_links(
                    (string) ($single_template_row['template_label'] ?? ''),
                    (string) ($single_template_row['key'] ?? $single_template_key)
                )
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
            $row['page_count'] = count($page_references);
        }
        unset($row);

        $rows = array_values(array_filter($rows, static function ($row): bool {
            return is_array($row)
                && trim((string) ($row['template_label'] ?? '')) !== '';
        }));

        usort($rows, static function (array $left, array $right): int {
            $weights = [
                'Default' => 10,
                'Custom' => 20,
                'System' => 30,
            ];
            $core_template_order = [
                'Front Page' => 10,
                'Posts Page' => 20,
                'FAQ Page' => 30,
                'Contact Page' => 40,
                'About Page' => 45,
                'Basic Page' => 50,
                'Privacy Policy Page' => 60,
            ];
            $auto_template_order = [
                'Privacy Policy Page' => 10,
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

            if ($left_group === 'Default' && $right_group === 'Default') {
                $left_label = (string) ($left['template_label'] ?? '');
                $right_label = (string) ($right['template_label'] ?? '');
                $left_core_weight = $core_template_order[$left_label] ?? 1000;
                $right_core_weight = $core_template_order[$right_label] ?? 1000;

                if ($left_core_weight !== $right_core_weight) {
                    return $left_core_weight <=> $right_core_weight;
                }
            }

            if ($left_group === 'System' && $right_group === 'System') {
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
        $explicit_labels = [
            'list-donors-sponsors' => 'List Donors & Sponsors',
        ];

        if (isset($explicit_labels[$file])) {
            return $explicit_labels[$file];
        }

        $label = str_replace(['list-', 'aside-', '-', '_'], ['List ', 'Aside ', ' ', ' '], $file);
        $label = trim(preg_replace('/\s+/', ' ', $label));

        $label = ucwords($label);

        $replacements = [
            'Cta' => 'CTA',
            'Faq' => 'FAQ',
            'Faqs' => 'FAQs',
        ];

        return strtr($label, $replacements);
    }
}

if (!function_exists('meza_site_documentation_section_template_order_map')) {
    function meza_site_documentation_normalize_section_template_row_key(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $normalized = sanitize_key(str_replace('_', '-', $value));

        return trim((string) preg_replace('/-+/', '-', $normalized), '-');
    }

    function meza_site_documentation_section_template_row_key_from_field_group_title(string $title): string
    {
        $title = trim($title);

        if ($title === '') {
            return '';
        }

        $title_to_row_key = meza_site_documentation_section_template_title_map();

        if (isset($title_to_row_key[$title])) {
            return (string) $title_to_row_key[$title];
        }

        if (preg_match('/^(?<label>.+?)\s+Section$/i', $title, $matches)) {
            return meza_site_documentation_normalize_section_template_row_key((string) ($matches['label'] ?? ''));
        }

        return '';
    }

    function meza_site_documentation_section_template_row_key_from_field_group(array $field_group): string
    {
        $field_group_id = $field_group['key'] ?? $field_group['ID'] ?? null;
        $field_group_fields = function_exists('acf_get_fields')
            ? acf_get_fields($field_group_id)
            : [];

        if (is_array($field_group_fields)) {
            foreach ($field_group_fields as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $field_name = trim((string) ($field['name'] ?? ''));

                if ($field_name === '') {
                    continue;
                }

                if (str_starts_with($field_name, 'show_')) {
                    return meza_site_documentation_normalize_section_template_row_key(substr($field_name, 5));
                }

                if (str_starts_with($field_name, 'section_')) {
                    return meza_site_documentation_normalize_section_template_row_key(substr($field_name, 8));
                }
            }
        }

        return meza_site_documentation_section_template_row_key_from_field_group_title((string) ($field_group['title'] ?? ''));
    }

    function meza_site_documentation_section_template_field_group_configs(): array
    {
        $configs = [];

        if (function_exists('meza_get_section_field_group_menu_order_map')) {
            foreach ((array) meza_get_section_field_group_menu_order_map() as $title => $menu_order) {
                $row_key = meza_site_documentation_section_template_row_key_from_field_group_title((string) $title);

                if ($row_key === '') {
                    continue;
                }

                $configs[$row_key] = [
                    'row_key' => $row_key,
                    'title' => trim((string) $title),
                    'menu_order' => (int) $menu_order,
                ];
            }
        }

        if (function_exists('acf_get_field_groups')) {
            foreach ((array) acf_get_field_groups() as $field_group) {
                if (!is_array($field_group)) {
                    continue;
                }

                $row_key = meza_site_documentation_section_template_row_key_from_field_group($field_group);

                if ($row_key === '') {
                    continue;
                }

                $configs[$row_key] = [
                    'row_key' => $row_key,
                    'title' => trim((string) ($field_group['title'] ?? ($configs[$row_key]['title'] ?? ''))),
                    'menu_order' => (int) ($field_group['menu_order'] ?? ($configs[$row_key]['menu_order'] ?? 0)),
                ];
            }
        }

        return $configs;
    }

    function meza_site_documentation_enabled_section_template_field_group_configs(): array
    {
        $all_configs = meza_site_documentation_section_template_field_group_configs();

        if (
            !function_exists('meza_get_content_model_field_group_management_choice_map')
            || !function_exists('meza_get_content_model_field_group_management_current_selection')
            || !function_exists('meza_get_content_model_field_group_management_definition_map')
            || !function_exists('meza_get_content_model_custom_field_group_records')
        ) {
            return $all_configs;
        }

        $enabled_configs = [];
        $definition_sources = [
            'built_in' => function (): array {
                return meza_get_content_model_field_group_management_definition_map('built_in');
            },
            'defaults' => function (): array {
                return meza_get_content_model_field_group_management_definition_map('defaults');
            },
            'custom' => function (): array {
                return meza_get_content_model_custom_field_group_records();
            },
        ];

        foreach ($definition_sources as $bucket => $get_definitions) {
            $choices = meza_get_content_model_field_group_management_choice_map($bucket);
            $selected_keys = meza_get_content_model_field_group_management_current_selection($bucket, $choices);
            $definitions = $get_definitions();

            foreach ($selected_keys as $selected_key) {
                $selected_key = sanitize_key((string) $selected_key);

                if ($selected_key === '' || !isset($definitions[$selected_key]) || !is_array($definitions[$selected_key])) {
                    continue;
                }

                $row_key = meza_site_documentation_section_template_row_key_from_field_group($definitions[$selected_key]);

                if ($row_key === '' || !isset($all_configs[$row_key])) {
                    continue;
                }

                $enabled_configs[$row_key] = $all_configs[$row_key];
            }
        }

        return $enabled_configs;
    }

    function meza_site_documentation_section_template_title_map(): array
    {
        return [
            'Hero Section' => 'hero',
            'List Services Section' => 'list-services',
            'List Products Section' => 'list-products',
            'List Reviews Section' => 'list-reviews',
            'List Customers Section' => 'list-customers',
            'List Signs Section' => 'list-signs',
            'List Brands Section' => 'list-brands',
            'Benefits Section' => 'benefits',
            'List Donors & Sponsors Section' => 'list-donors-sponsors',
            'List Donors &amp; Sponsors Section' => 'list-donors-sponsors',
            'List Donors and Sponsors Section' => 'list-donors-sponsors',
            'List Localities Section' => 'list-localities',
            'Form Section' => 'form',
            'List Locations Section' => 'list-locations',
            'CTA Section' => 'cta',
            'List Resources Section' => 'list-resources',
            'List Posts Section' => 'list-posts',
            'List FAQs Section' => 'list-faqs',
        ];
    }
}

if (!function_exists('meza_site_documentation_section_template_field_group_row_keys')) {
    function meza_site_documentation_section_template_field_group_row_keys(): array
    {
        return array_keys(meza_site_documentation_enabled_section_template_field_group_configs());
    }
}

if (!function_exists('meza_site_documentation_section_template_order_map')) {
    function meza_site_documentation_section_template_order_map(): array
    {
        $order_map = [
            'content' => 2,
        ];

        foreach (meza_site_documentation_section_template_field_group_configs() as $config) {
            $row_key = trim((string) ($config['row_key'] ?? ''));

            if ($row_key === '') {
                continue;
            }

            $order_map[$row_key] = (int) ($config['menu_order'] ?? ($order_map[$row_key] ?? 0));
        }

        return $order_map;
    }
}

if (!function_exists('meza_site_documentation_section_template_description_from_path')) {
    function meza_site_documentation_section_template_description_from_path(string $path): string
    {
        static $cache = [];

        $path = trim($path);

        if ($path === '') {
            return '';
        }

        if (array_key_exists($path, $cache)) {
            return $cache[$path];
        }

        if (!file_exists($path) || !is_readable($path)) {
            $cache[$path] = '';
            return '';
        }

        $contents = (string) file_get_contents($path);

        if ($contents !== '' && preg_match('/^[\s\/*#@]*Section Description:\s*(.+)$/mi', $contents, $matches)) {
            $cache[$path] = trim((string) ($matches[1] ?? ''));
            return $cache[$path];
        }

        $cache[$path] = '';

        return '';
    }
}

if (!function_exists('meza_site_documentation_section_template_description')) {
    function meza_site_documentation_section_template_description(string $file, string $path = ''): string
    {
        $file = basename($file, '.php');
        $normalized = strtolower(trim($file));
        $file_description = meza_site_documentation_section_template_description_from_path($path);

        if ($file_description !== '') {
            return $file_description;
        }

        if ($normalized === 'content') {
            return 'Reusable section partial for rendering primary rich-text content blocks.';
        }

        if ($normalized === 'form') {
            return 'Reusable section partial for embedding contact or lead-capture forms.';
        }

        if ($normalized === 'list-faqs') {
            return 'Reusable listing section partial for FAQs content.';
        }

        if (str_starts_with($normalized, 'list-')) {
            $subject = ucwords(str_replace(['list-', '-', '_'], ['', ' ', ' '], $normalized));
            return 'Reusable listing section partial for ' . strtolower($subject) . ' content.';
        }

        return 'Reusable section partial used to assemble page layouts and modular content blocks.';
    }
}

if (!function_exists('meza_site_documentation_section_template_usage_counts')) {
    function meza_site_documentation_section_template_usage_counts(array $show_keys): array
    {
        global $wpdb;

        $show_keys = array_values(array_unique(array_filter(array_map(static function ($key): string {
            return trim((string) $key);
        }, $show_keys))));

        if ($show_keys === []) {
            return [];
        }

        $meta_placeholders = implode(', ', array_fill(0, count($show_keys), '%s'));
        $status_exclusions = ['auto-draft', 'trash', 'inherit'];
        $status_placeholders = implode(', ', array_fill(0, count($status_exclusions), '%s'));

        $query = $wpdb->prepare(
            "
            SELECT pm.meta_key, COUNT(DISTINCT p.ID) AS usage_count
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key IN ($meta_placeholders)
                AND pm.meta_value = '1'
                AND p.post_type = 'page'
                AND p.post_status NOT IN ($status_placeholders)
            GROUP BY pm.meta_key
            ",
            array_merge($show_keys, $status_exclusions)
        );

        if (!is_string($query) || $query === '') {
            return [];
        }

        $results = $wpdb->get_results($query, ARRAY_A);

        if (!is_array($results)) {
            return [];
        }

        $counts = [];

        foreach ($results as $result) {
            $meta_key = trim((string) ($result['meta_key'] ?? ''));

            if ($meta_key === '') {
                continue;
            }

            $counts[$meta_key] = (int) ($result['usage_count'] ?? 0);
        }

        return $counts;
    }
}

if (!function_exists('meza_site_documentation_section_template_active_page_ids')) {
    function meza_site_documentation_section_template_active_page_ids(): array
    {
        $page_ids = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'fields' => 'ids',
            'numberposts' => -1,
            'orderby' => 'menu_order title',
            'order' => 'ASC',
        ]);

        return is_array($page_ids)
            ? array_values(array_filter(array_map('intval', $page_ids), static function (int $id): bool {
                return $id > 0;
            }))
            : [];
    }
}

if (!function_exists('meza_site_documentation_section_template_cta_page_count')) {
    function meza_site_documentation_section_template_cta_page_count(array $page_ids): int
    {
        $count = 0;

        foreach ($page_ids as $page_id) {
            $value = function_exists('get_field') ? get_field('cta', $page_id) : null;

            if (empty($value)) {
                $value = get_post_meta($page_id, 'cta', true);
            }

            if (!empty($value)) {
                $count++;
            }
        }

        return $count;
    }
}

if (!function_exists('meza_site_documentation_section_template_value_is_truthy')) {
    function meza_site_documentation_section_template_value_is_truthy($value): bool
    {
        if ($value instanceof WP_Post || $value instanceof WP_Term) {
            return true;
        }

        if (is_array($value)) {
            return !empty($value);
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return !in_array($normalized, ['', '0', 'false', 'no', 'off'], true);
    }
}

if (!function_exists('meza_site_documentation_section_template_service_variant_file')) {
    function meza_site_documentation_section_template_service_variant_file(WP_Post $post): string
    {
        $root_post = $post;

        while ($root_post instanceof WP_Post && (int) $root_post->post_parent > 0) {
            $parent_post = get_post((int) $root_post->post_parent);

            if (!($parent_post instanceof WP_Post)) {
                break;
            }

            $root_post = $parent_post;
        }

        $root_slug = trim((string) $root_post->post_name);

        return match ($root_slug) {
            'wide-format-printing' => 'single-service-printing.php',
            'surveying-equipment' => 'single-service-equipment.php',
            default => 'single-service-signs.php',
        };
    }
}

if (!function_exists('meza_site_documentation_section_template_page_file')) {
    function meza_site_documentation_section_template_page_file(int $page_id): string
    {
        $special_page_label = function_exists('meza_site_documentation_special_page_label')
            ? meza_site_documentation_special_page_label($page_id)
            : '';

        return match ($special_page_label) {
            'Front Page' => 'front-page.php',
            'Posts Page' => 'home.php',
            'FAQ Page' => 'page-faq.php',
            'Documentation Page' => 'page-documentation.php',
            'Style Guide Page' => 'page-style.php',
            'Cookie Policy Page' => 'page-cookie-policy.php',
            default => 'page.php',
        };
    }
}

if (!function_exists('meza_site_documentation_section_template_active_contexts')) {
    function meza_site_documentation_section_template_active_contexts(): array
    {
        $contexts = [];

        foreach (meza_site_documentation_section_template_active_page_ids() as $page_id) {
            $template_file = meza_site_documentation_section_template_page_file($page_id);

            if (meza_site_documentation_page_template_file_path($template_file) === '') {
                continue;
            }

            $contexts[] = [
                'key' => 'post:' . $page_id,
                'kind' => 'post',
                'post_id' => $page_id,
                'post_type' => 'page',
                'template_file' => $template_file,
            ];
        }

        $single_post_type_templates = [
            'post' => 'single-post.php',
            'customer' => 'single-customer.php',
        ];

        foreach ($single_post_type_templates as $post_type => $template_file) {
            if (meza_site_documentation_page_template_file_path($template_file) === '') {
                continue;
            }

            $post_ids = get_posts([
                'post_type' => $post_type,
                'post_status' => 'publish',
                'fields' => 'ids',
                'numberposts' => -1,
            ]);

            if (!is_array($post_ids)) {
                continue;
            }

            foreach ($post_ids as $post_id) {
                $post_id = (int) $post_id;

                if ($post_id <= 0) {
                    continue;
                }

                $contexts[] = [
                    'key' => 'post:' . $post_id,
                    'kind' => 'post',
                    'post_id' => $post_id,
                    'post_type' => $post_type,
                    'template_file' => $template_file,
                ];
            }
        }

        $service_ids = get_posts([
            'post_type' => 'service',
            'post_status' => 'publish',
            'fields' => 'ids',
            'numberposts' => -1,
        ]);

        if (is_array($service_ids)) {
            foreach ($service_ids as $service_id) {
                $service_id = (int) $service_id;
                $service_post = $service_id > 0 ? get_post($service_id) : null;

                if (!($service_post instanceof WP_Post)) {
                    continue;
                }

                $template_file = meza_site_documentation_section_template_service_variant_file($service_post);

                if (meza_site_documentation_page_template_file_path($template_file) === '') {
                    continue;
                }

                $contexts[] = [
                    'key' => 'post:' . $service_id,
                    'kind' => 'post',
                    'post_id' => $service_id,
                    'post_type' => 'service',
                    'template_file' => $template_file,
                ];
            }
        }

        $term_template_map = [
            'locality' => 'taxonomy-locality.php',
            'sign_type' => 'taxonomy-sign_type.php',
            'category' => 'category.php',
        ];

        foreach ($term_template_map as $taxonomy => $template_file) {
            if (meza_site_documentation_page_template_file_path($template_file) === '') {
                continue;
            }

            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => true,
            ]);

            if (!is_array($terms) || is_wp_error($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                if (!($term instanceof WP_Term)) {
                    continue;
                }

                $contexts[] = [
                    'key' => 'term:' . $taxonomy . ':' . (int) $term->term_id,
                    'kind' => 'term',
                    'term' => $term,
                    'taxonomy' => $taxonomy,
                    'template_file' => $template_file,
                ];
            }
        }

        return $contexts;
    }
}

if (!function_exists('meza_site_documentation_section_template_file_sections')) {
    function meza_site_documentation_section_template_file_sections(string $template_file): array
    {
        static $cache = [];

        $template_file = trim($template_file);

        if ($template_file === '') {
            return [];
        }

        if (isset($cache[$template_file])) {
            return $cache[$template_file];
        }

        $path = meza_site_documentation_page_template_file_path($template_file);

        if ($path === '' || !file_exists($path)) {
            $cache[$template_file] = [];
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $calls = [];
        $history = [];

        if (is_array($lines)) {
            $pending_condition = null;

            foreach ($lines as $line) {
                $line = trim((string) $line);
                $section_name = '';
                $line_condition = null;

                if (preg_match("/get_field\\('([^']+)'/", $line, $meta_matches) && str_starts_with((string) ($meta_matches[1] ?? ''), 'show_')) {
                    $line_condition = [
                        'type' => 'meta_true',
                        'key' => trim((string) ($meta_matches[1] ?? '')),
                    ];
                } elseif (preg_match('/meza_page_has_form_section\\(/', $line)) {
                    $line_condition = ['type' => 'page_form'];
                } elseif (preg_match('/meza_page_has_locations_section\\(/', $line)) {
                    $line_condition = ['type' => 'page_locations'];
                } elseif (preg_match('/ds_should_render_locations_section\\(/', $line)) {
                    $line_condition = ['type' => 'locations_callable'];
                } elseif (preg_match("/get_field\\('cta'/", $line) || preg_match("/get_post_meta\\([^\\n]+['\"]cta['\"]/", $line)) {
                    $line_condition = ['type' => 'cta'];
                } elseif (preg_match('/get_the_content\\(/', $line)) {
                    $line_condition = ['type' => 'content'];
                }

                if (preg_match("/get_template_part\\(\\s*'section\\/([^']+)'\\s*,\\s*'([^']+)'\\s*\\)/", $line, $matches)) {
                    $section_name = trim($matches[1] . '-' . $matches[2]);
                } elseif (preg_match("/get_template_part\\(\\s*'section\\/([^']+)'\\s*\\)/", $line, $matches)) {
                    $section_name = trim((string) ($matches[1] ?? ''));
                } elseif (preg_match("/get_section\\(\\s*'([^']+)'/", $line, $matches)) {
                    $section_name = trim((string) ($matches[1] ?? ''));
                }

                if ($section_name !== '') {
                    $condition = ['type' => 'always'];

                    if ($section_name === 'content') {
                        $condition = ['type' => 'content'];
                    } elseif (is_array($line_condition)) {
                        $condition = $line_condition;
                    } elseif (is_array($pending_condition)) {
                        $condition = $pending_condition;
                    }

                    $calls[] = [
                        'section' => sanitize_key($section_name),
                        'condition' => $condition,
                    ];

                    $pending_condition = null;
                } elseif (is_array($line_condition)) {
                    $pending_condition = $line_condition;
                }

                if ($line !== '') {
                    $history[] = $line;

                    if (count($history) > 5) {
                        array_shift($history);
                    }
                }
            }
        }

        $source = (string) file_get_contents($path);

        if ($source !== '' && preg_match('/get_hero\\(/', $source)) {
            $calls[] = [
                'section' => 'hero',
                'condition' => ['type' => 'always'],
            ];
        }

        $cache[$template_file] = $calls;

        return $calls;
    }
}

if (!function_exists('meza_site_documentation_section_template_context_has_content')) {
    function meza_site_documentation_section_template_context_has_content(array $context): bool
    {
        if (($context['kind'] ?? '') !== 'post') {
            return false;
        }

        return trim((string) get_post_field('post_content', (int) ($context['post_id'] ?? 0))) !== '';
    }
}

if (!function_exists('meza_site_documentation_section_template_context_has_cta')) {
    function meza_site_documentation_section_template_context_has_cta(array $context): bool
    {
        if (($context['kind'] ?? '') === 'post') {
            $post_id = (int) ($context['post_id'] ?? 0);
            $value = function_exists('get_field') ? get_field('cta', $post_id) : null;

            if (empty($value)) {
                $value = get_post_meta($post_id, 'cta', true);
            }

            return meza_site_documentation_section_template_value_is_truthy($value);
        }

        if (($context['kind'] ?? '') === 'term' && !empty($context['term']) && $context['term'] instanceof WP_Term) {
            $term = $context['term'];
            $value = function_exists('get_field') ? get_field('cta', $term) : null;

            if (empty($value)) {
                $value = get_term_meta((int) $term->term_id, 'cta', true);
            }

            return meza_site_documentation_section_template_value_is_truthy($value);
        }

        return false;
    }
}

if (!function_exists('meza_site_documentation_section_template_context_truthy_meta')) {
    function meza_site_documentation_section_template_context_truthy_meta(array $context, string $meta_key): bool
    {
        $meta_key = trim($meta_key);

        if ($meta_key === '') {
            return false;
        }

        if (($context['kind'] ?? '') === 'post') {
            $post_id = (int) ($context['post_id'] ?? 0);
            $post_type = trim((string) ($context['post_type'] ?? ''));

            if ($post_type === 'page' && $meta_key === 'show_form' && function_exists('meza_page_has_form_section')) {
                return meza_page_has_form_section($post_id);
            }

            if ($post_type === 'page' && $meta_key === 'show_list-locations' && function_exists('meza_page_has_locations_section')) {
                return meza_page_has_locations_section($post_id);
            }

            $value = function_exists('get_field') ? get_field($meta_key, $post_id) : null;

            if (empty($value)) {
                $value = get_post_meta($post_id, $meta_key, true);
            }

            return meza_site_documentation_section_template_value_is_truthy($value);
        }

        if (($context['kind'] ?? '') === 'term' && !empty($context['term']) && $context['term'] instanceof WP_Term) {
            $term = $context['term'];
            $value = function_exists('get_field') ? get_field($meta_key, $term) : null;

            if (empty($value)) {
                $value = get_term_meta((int) $term->term_id, $meta_key, true);
            }

            return meza_site_documentation_section_template_value_is_truthy($value);
        }

        return false;
    }
}

if (!function_exists('meza_site_documentation_section_template_context_has_locations_section')) {
    function meza_site_documentation_section_template_context_has_locations_section(array $context): bool
    {
        if (($context['kind'] ?? '') !== 'term' || ($context['taxonomy'] ?? '') !== 'locality' || empty($context['term']) || !($context['term'] instanceof WP_Term)) {
            return false;
        }

        if (function_exists('ds_get_business_locations_for_term')) {
            return !empty(ds_get_business_locations_for_term($context['term']));
        }

        return false;
    }
}

if (!function_exists('meza_site_documentation_section_template_usage_map')) {
    function meza_site_documentation_section_template_usage_map(): array
    {
        $usage_map = [];

        foreach (meza_site_documentation_section_template_active_contexts() as $context) {
            $template_file = trim((string) ($context['template_file'] ?? ''));

            if ($template_file === '') {
                continue;
            }

            foreach (meza_site_documentation_section_template_file_sections($template_file) as $call) {
                $section_key = sanitize_key((string) ($call['section'] ?? ''));
                $condition = is_array($call['condition'] ?? null) ? $call['condition'] : ['type' => 'always'];
                $condition_type = trim((string) ($condition['type'] ?? 'always'));

                $should_count = match ($condition_type) {
                    'content' => meza_site_documentation_section_template_context_has_content($context),
                    'cta' => meza_site_documentation_section_template_context_has_cta($context),
                    'meta_true' => meza_site_documentation_section_template_context_truthy_meta($context, (string) ($condition['key'] ?? '')),
                    'page_form' => meza_site_documentation_section_template_context_truthy_meta($context, 'show_form'),
                    'page_locations' => meza_site_documentation_section_template_context_truthy_meta($context, 'show_list-locations'),
                    'locations_callable' => meza_site_documentation_section_template_context_has_locations_section($context),
                    default => true,
                };

                if (!$should_count || $section_key === '') {
                    continue;
                }

                $usage_map[$section_key][(string) ($context['key'] ?? $section_key)] = true;
            }
        }

        return $usage_map;
    }
}

if (!function_exists('meza_site_documentation_get_section_template_rows')) {
    function meza_site_documentation_get_section_template_rows(): array
    {
        $rows = [];
        $order_map = meza_site_documentation_section_template_order_map();
        $field_group_configs = meza_site_documentation_enabled_section_template_field_group_configs();
        $allowed_row_keys = array_fill_keys(array_keys($field_group_configs), true);

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

                $row_key = sanitize_key(basename($file, '.php'));

                if (isset($rows[$row_key])) {
                    continue;
                }

                if ($row_key !== 'hero' && !isset($allowed_row_keys[$row_key])) {
                    continue;
                }

                $rows[$row_key] = [
                    'key' => $row_key,
                    'group' => $group_label,
                    'label' => meza_site_documentation_section_template_label($file),
                    'description' => meza_site_documentation_section_template_description($file, $path),
                    'menu_order' => $order_map[$row_key] ?? '',
                    'show_key' => 'show_' . basename($file, '.php'),
                ];
            }
        }

        if (!isset($rows['hero'])) {
            $rows['hero'] = [
                'key' => 'hero',
                'group' => wp_get_theme() instanceof WP_Theme ? trim((string) wp_get_theme()->get('Name')) : '',
                'label' => 'Hero',
                'description' => meza_site_documentation_section_template_description(
                    'hero.php',
                    trailingslashit(get_template_directory()) . 'functions.php'
                ),
                'menu_order' => $order_map['hero'] ?? '',
            ];
        }

        $usage_map = meza_site_documentation_section_template_usage_map();

        foreach ($rows as &$row) {
            $row_key = trim((string) ($row['key'] ?? ''));
            $row['page_count'] = isset($usage_map[$row_key]) && is_array($usage_map[$row_key])
                ? count($usage_map[$row_key])
                : 0;
        }

        unset($row);

        usort($rows, static function (array $left, array $right) use ($order_map): int {
            $left_key = trim((string) ($left['key'] ?? ''));
            $right_key = trim((string) ($right['key'] ?? ''));
            $left_weight = $order_map[$left_key] ?? 1000;
            $right_weight = $order_map[$right_key] ?? 1000;

            if ($left_weight !== $right_weight) {
                return $left_weight <=> $right_weight;
            }

            $label_compare = strnatcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));

            if ($label_compare !== 0) {
                return $label_compare;
            }

            return strnatcasecmp((string) ($left['group'] ?? ''), (string) ($right['group'] ?? ''));
        });

        return $rows;
    }
}
