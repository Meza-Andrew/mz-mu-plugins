<?php


if (!function_exists('meza_site_documentation_page_group_label')) {
    function meza_site_documentation_is_documentation_page(WP_Post $page): bool
    {
        $documentation_page_id = (int) get_option('meza_page_for_documentation');
        $style_guide_page_id = (int) get_option('meza_page_for_style_guide');

        if (in_array((int) $page->ID, [$documentation_page_id, $style_guide_page_id], true)) {
            return true;
        }

        $template = (string) get_page_template_slug($page->ID);

        return in_array($template, ['page-documentation.php', 'page-style.php'], true);
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

if (!function_exists('meza_site_documentation_post_type_singular_label')) {
    function meza_site_documentation_post_type_singular_label(WP_Post_Type $post_type_object): string
    {
        $label = trim((string) ($post_type_object->labels->singular_name ?? ''));

        if ($label !== '') {
            return $label;
        }

        return meza_site_documentation_post_type_plural_label($post_type_object);
    }
}

if (!function_exists('meza_site_documentation_post_type_group_label')) {
    function meza_site_documentation_post_type_group_label(WP_Post_Type $post_type_object): string
    {
        return meza_site_documentation_post_type_singular_label($post_type_object);
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
            return 'Link Accessible Only';
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

if (!function_exists('meza_site_documentation_should_document_page_url')) {
    function meza_site_documentation_should_document_page_url(WP_Post $page, string $url): bool
    {
        if (meza_site_documentation_has_public_permalink($url)) {
            return true;
        }

        return in_array(
            meza_site_documentation_special_page_label((int) $page->ID),
            ['Documentation Page', 'Style Guide Page'],
            true
        );
    }
}

if (!function_exists('meza_site_documentation_page_sort_weight')) {
    function meza_site_documentation_page_template_sort_weight(WP_Post $page): int
    {
        $page_id = (int) $page->ID;
        $front_page_id = (int) get_option('page_on_front');
        $posts_page_id = (int) get_option('page_for_posts');
        $special_page_label = meza_site_documentation_special_page_label($page_id);

        if ($page_id === $front_page_id) {
            return 10;
        }

        if ($page_id === $posts_page_id) {
            return 20;
        }

        return match ($special_page_label) {
            'FAQ Page' => 30,
            'Contact Page' => 40,
            'Privacy Policy Page' => 50,
            'Cookie Policy Page' => 60,
            'Documentation Page' => 70,
            'Style Guide Page' => 80,
            default => 45,
        };
    }
}

if (!function_exists('meza_site_documentation_page_template_label_for_page')) {
    function meza_site_documentation_page_template_label_for_page(WP_Post $page): string
    {
        $page_id = (int) $page->ID;
        $special_page_label = meza_site_documentation_special_page_label($page_id);

        if ($special_page_label !== '') {
            return $special_page_label;
        }

        $page_type_meta_label = meza_site_documentation_page_type_meta_label($page_id);
        if ($page_type_meta_label !== '') {
            return $page_type_meta_label;
        }

        $page_type_term_label = meza_site_documentation_page_type_term_label($page_id);
        if ($page_type_term_label !== '') {
            return $page_type_term_label;
        }

        $template_slug = trim((string) get_page_template_slug($page_id));
        if ($template_slug !== '') {
            $template_path = locate_template($template_slug, false, false);
            if (is_string($template_path) && $template_path !== '' && is_readable($template_path)) {
                $header = get_file_data($template_path, ['template_name' => 'Template Name']);
                $template_name = trim((string) ($header['template_name'] ?? ''));

                if ($template_name !== '') {
                    return $template_name;
                }
            }

            $registered_templates = [];
            $theme = wp_get_theme();

            if ($theme instanceof WP_Theme) {
                $registered_templates = array_flip((array) $theme->get_page_templates(null, 'page'));
            }

            if (isset($registered_templates[$template_slug])) {
                $template_name = trim((string) $registered_templates[$template_slug]);

                if ($template_name !== '') {
                    return $template_name;
                }
            }

            return ucwords(str_replace(['page-', '.php', '-'], ['', '', ' '], $template_slug));
        }

        return 'Basic Page';
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

            if (is_string($archive_url) && (!$archive_page instanceof WP_Post || meza_site_documentation_should_document_page_url($archive_page, $archive_url))) {
                $archive_visibility = $archive_page instanceof WP_Post
                    ? meza_site_documentation_post_visibility_label($archive_page)
                    : 'Public and Indexable';

                $archive_row = [
                    'key' => sanitize_key($post_type . '_archive'),
                    'post_id' => $archive_page instanceof WP_Post ? (int) $archive_page->ID : 0,
                    'group' => $group_label,
                    'group_sort' => $group_label,
                    'template_sort_weight' => $archive_page instanceof WP_Post
                        ? meza_site_documentation_page_template_sort_weight($archive_page)
                        : 1000,
                    'archive_family' => $archive_family,
                    'title' => $archive_title !== '' ? $archive_title : $archive_family,
                    'slug' => sanitize_title(is_string($archive_url) ? $archive_url : $post_type),
                    'url' => $archive_url,
                    'page_template_label' => $archive_page instanceof WP_Post
                        ? meza_site_documentation_page_template_label_for_page($archive_page)
                        : '',
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
            if (!is_string($page_url) || !meza_site_documentation_should_document_page_url($page, $page_url)) {
                continue;
            }

            $row = [
                'key' => $page->post_name !== '' ? sanitize_key((string) $page->post_name) : 'page_' . (int) $page->ID,
                'post_id' => (int) $page->ID,
                'group' => meza_site_documentation_page_group_label($page),
                'group_sort' => meza_site_documentation_page_group_sort_key($page),
                'template_sort_weight' => meza_site_documentation_page_template_sort_weight($page),
                'archive_family' => '',
                'title' => trim((string) $page->post_title),
                'slug' => sanitize_title((string) $page->post_name),
                'url' => $page_url,
                'page_template_label' => meza_site_documentation_page_template_label_for_page($page),
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
            $left_template_sort_weight = (int) ($left['template_sort_weight'] ?? 1000);
            $right_template_sort_weight = (int) ($right['template_sort_weight'] ?? 1000);

            if ($left_template_sort_weight !== $right_template_sort_weight) {
                return $left_template_sort_weight <=> $right_template_sort_weight;
            }

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

if (!function_exists('meza_site_documentation_get_content_only_rows')) {
    function meza_site_documentation_usage_post_statuses(): array
    {
        return ['publish', 'private', 'draft', 'pending', 'future'];
    }

    function meza_site_documentation_get_page_faq_ids(int $post_id): array
    {
        if ($post_id <= 0) {
            return [];
        }

        $faq_ids = [];
        $append_faq_ids = static function ($value) use (&$faq_ids): void {
            if (is_array($value) && isset($value['faqs'])) {
                $value = $value['faqs'];
            }

            if (is_array($value)) {
                foreach ($value as $entry) {
                    if (is_numeric($entry)) {
                        $faq_ids[] = (int) $entry;
                        continue;
                    }

                    if ($entry instanceof WP_Post) {
                        $faq_ids[] = (int) $entry->ID;
                        continue;
                    }

                    if (is_array($entry)) {
                        $entry_id = (int) ($entry['ID'] ?? $entry['id'] ?? 0);
                        if ($entry_id > 0) {
                            $faq_ids[] = $entry_id;
                        }
                    }
                }
                return;
            }

            if (is_numeric($value)) {
                $faq_ids[] = (int) $value;
                return;
            }

            if ($value instanceof WP_Post) {
                $faq_ids[] = (int) $value->ID;
            }
        };

        if (function_exists('get_field')) {
            $acf_faq_candidates = [
                get_field('section_faqs_faqs', $post_id),
                get_field('faqs', $post_id),
                get_field('section_faqs', $post_id),
            ];

            foreach ($acf_faq_candidates as $acf_faq) {
                $append_faq_ids($acf_faq);
            }
        }

        if (empty($faq_ids)) {
            $raw_faq_candidates = [
                get_post_meta($post_id, 'section_faqs_faqs', true),
                get_post_meta($post_id, 'faqs', true),
                get_post_meta($post_id, 'section_faqs', true),
            ];

            foreach ($raw_faq_candidates as $raw_faq) {
                $append_faq_ids($raw_faq);
            }
        }

        return array_values(array_unique(array_filter(array_map('intval', $faq_ids), static function (int $id): bool {
            return $id > 0 && get_post_type($id) === 'faq';
        })));
    }

    function meza_site_documentation_get_post_cta_ids(int $post_id): array
    {
        if ($post_id <= 0) {
            return [];
        }

        $cta_ids = [];
        $append_cta_ids = static function ($value) use (&$cta_ids): void {
            if (is_array($value) && isset($value['cta'])) {
                $value = $value['cta'];
            }

            if (is_array($value)) {
                foreach ($value as $entry) {
                    if (is_numeric($entry)) {
                        $cta_ids[] = (int) $entry;
                        continue;
                    }

                    if ($entry instanceof WP_Post) {
                        $cta_ids[] = (int) $entry->ID;
                        continue;
                    }

                    if (is_array($entry)) {
                        $entry_id = (int) ($entry['ID'] ?? $entry['id'] ?? 0);
                        if ($entry_id > 0) {
                            $cta_ids[] = $entry_id;
                        }
                    }
                }
                return;
            }

            if (is_numeric($value)) {
                $cta_ids[] = (int) $value;
                return;
            }

            if ($value instanceof WP_Post) {
                $cta_ids[] = (int) $value->ID;
            }
        };

        if (function_exists('get_field')) {
            $acf_cta_candidates = [
                get_field('section_cta_cta', $post_id),
                get_field('cta', $post_id),
                get_field('section_cta', $post_id),
            ];

            foreach ($acf_cta_candidates as $acf_cta) {
                $append_cta_ids($acf_cta);
            }
        }

        if (empty($cta_ids)) {
            $raw_cta_candidates = [
                get_post_meta($post_id, 'section_cta_cta', true),
                get_post_meta($post_id, 'cta', true),
                get_post_meta($post_id, 'section_cta', true),
            ];

            foreach ($raw_cta_candidates as $raw_cta) {
                $append_cta_ids($raw_cta);
            }
        }

        return array_values(array_unique(array_filter(array_map('intval', $cta_ids), static function (int $id): bool {
            return $id > 0 && get_post_type($id) === 'cta';
        })));
    }

    function meza_site_documentation_get_form_recipient_emails(WP_Post $form_post): array
    {
        $recipients = [];

        if (function_exists('get_field')) {
            $email_admin = get_field('email_admin', (int) $form_post->ID);
            if (is_array($email_admin) && function_exists('mzf_parse_recipients')) {
                $recipients = mzf_parse_recipients($email_admin['recipients'] ?? []);
            }
        }

        if ($recipients === [] && function_exists('mzf_parse_recipients')) {
            $recipients = mzf_parse_recipients(get_post_meta((int) $form_post->ID, 'email_admin_recipients', true));
        }

        if ($recipients === [] && function_exists('mzf_parse_recipients')) {
            $recipients = mzf_parse_recipients(get_post_meta((int) $form_post->ID, 'email_recipients', true));
        }

        if (function_exists('meza_form_uses_business_location_store_field') && meza_form_uses_business_location_store_field((int) $form_post->ID)) {
            $location_recipients = function_exists('meza_get_business_location_admin_column_emails')
                ? meza_get_business_location_admin_column_emails()
                : [];

            if (is_array($location_recipients) && $location_recipients !== []) {
                $recipients = array_values(array_unique(array_merge($recipients, $location_recipients)));
            }
        }

        return array_values(array_filter(array_map('sanitize_email', $recipients), 'is_email'));
    }

    function meza_site_documentation_get_form_successful_submission_count(WP_Post $form_post): int
    {
        global $wpdb;

        if (!post_type_exists('mzf_submission')) {
            return 0;
        }

        $form_id = (int) $form_post->ID;
        $form_slug = sanitize_key((string) $form_post->post_name);

        if ($form_id <= 0 && $form_slug === '') {
            return 0;
        }

        $status_meta_key = '_mzf_delivery_status_group';
        $form_id_meta_key = '_mzf_form_post_id';
        $form_slug_meta_key = '_mzf_form_slug';

        $count = $wpdb->get_var($wpdb->prepare(
            "
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_status
                ON pm_status.post_id = p.ID
                AND pm_status.meta_key = %s
                AND pm_status.meta_value = 'success'
            LEFT JOIN {$wpdb->postmeta} pm_form_id
                ON pm_form_id.post_id = p.ID
                AND pm_form_id.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} pm_form_slug
                ON pm_form_slug.post_id = p.ID
                AND pm_form_slug.meta_key = %s
            WHERE p.post_type = 'mzf_submission'
                AND p.post_status NOT IN ('trash', 'auto-draft')
                AND (
                    CAST(COALESCE(pm_form_id.meta_value, '0') AS UNSIGNED) = %d
                    OR (%s <> '' AND pm_form_slug.meta_value = %s)
                )
            ",
            $status_meta_key,
            $form_id_meta_key,
            $form_slug_meta_key,
            $form_id,
            $form_slug,
            $form_slug
        ));

        return max(0, (int) $count);
    }

    function meza_site_documentation_get_form_submissions_admin_url(WP_Post $form_post): string
    {
        $form_id = (int) $form_post->ID;

        if ($form_id <= 0) {
            return '';
        }

        return add_query_arg([
            'post_type' => 'form',
            'page' => defined('MZF_SUBMISSIONS_PAGE_SLUG') ? MZF_SUBMISSIONS_PAGE_SLUG : 'submissions',
            'filter_form_post_id' => $form_id,
            'filter_status' => 'success',
        ], admin_url('edit.php'));
    }

    function meza_site_documentation_collect_form_ids_from_value($value): array
    {
        $form_ids = [];

        $append_form_ids = static function ($entry) use (&$form_ids): void {
            if (is_numeric($entry)) {
                $form_ids[] = (int) $entry;
                return;
            }

            if ($entry instanceof WP_Post) {
                $form_ids[] = (int) $entry->ID;
                return;
            }

            if (is_array($entry)) {
                $entry_id = (int) ($entry['ID'] ?? $entry['id'] ?? 0);

                if ($entry_id > 0) {
                    $form_ids[] = $entry_id;
                }
            }
        };

        if (is_array($value) && array_key_exists('form', $value)) {
            $value = $value['form'];
        }

        if (is_array($value)) {
            foreach ($value as $entry) {
                $append_form_ids($entry);
            }
        } else {
            $append_form_ids($value);
        }

        return array_values(array_unique(array_filter(array_map('intval', $form_ids), static function (int $id): bool {
            return $id > 0 && get_post_type($id) === 'form';
        })));
    }

    function meza_site_documentation_get_source_form_ids($source): array
    {
        $source_for_acf = $source;
        $source_post_id = 0;

        if ($source instanceof WP_Post) {
            $source_post_id = (int) $source->ID;
            $source_for_acf = $source_post_id;
        } elseif (is_numeric($source)) {
            $source_post_id = (int) $source;
            $source_for_acf = $source_post_id;
        }

        $form_ids = [];

        if (function_exists('get_field')) {
            $acf_candidates = [
                get_field('section_form', $source_for_acf),
                get_field('section_form_form', $source_for_acf),
                get_field('form', $source_for_acf),
            ];

            foreach ($acf_candidates as $candidate) {
                $form_ids = array_merge($form_ids, meza_site_documentation_collect_form_ids_from_value($candidate));
            }
        }

        if ($source_post_id > 0) {
            $meta_candidates = [
                get_post_meta($source_post_id, 'section_form_form', true),
                get_post_meta($source_post_id, 'section_form', true),
                get_post_meta($source_post_id, 'form', true),
            ];

            foreach ($meta_candidates as $candidate) {
                $form_ids = array_merge($form_ids, meza_site_documentation_collect_form_ids_from_value($candidate));
            }
        }

        return array_values(array_unique(array_filter(array_map('intval', $form_ids), static function (int $id): bool {
            return $id > 0 && get_post_type($id) === 'form';
        })));
    }

    function meza_site_documentation_get_source_form_family($source): string
    {
        if ($source instanceof WP_Term) {
            if ($source->taxonomy === 'sign_type') {
                return 'sign-quote';
            }

            if ($source->taxonomy === 'locality') {
                return 'quote';
            }

            return 'contact';
        }

        $source_post = null;

        if ($source instanceof WP_Post) {
            $source_post = $source;
        } elseif (is_numeric($source)) {
            $source_post = get_post((int) $source);
        }

        $post_name = $source_post instanceof WP_Post
            ? sanitize_key((string) $source_post->post_name)
            : '';

        if ($post_name === 'surveying-equipment') {
            return 'rent';
        }

        if ($post_name === 'wide-format-printing') {
            return 'print-quote';
        }

        if ($post_name === 'get-a-quote') {
            return 'quote';
        }

        if ($post_name === 'upload-files') {
            return 'upload-files';
        }

        return 'contact';
    }

    function meza_site_documentation_get_runtime_form_ids($source): array
    {
        $form_ids = meza_site_documentation_get_source_form_ids($source);

        if ($form_ids !== []) {
            return $form_ids;
        }

        $family = meza_site_documentation_get_source_form_family($source);

        if ($family === '') {
            return [];
        }

        $fallback_form = function_exists('get_page_by_path')
            ? get_page_by_path($family, OBJECT, 'form')
            : null;

        if (!($fallback_form instanceof WP_Post)) {
            return [];
        }

        return [(int) $fallback_form->ID];
    }

    function meza_site_documentation_source_renders_form($source): bool
    {
        if ($source instanceof WP_Term) {
            if (!in_array($source->taxonomy, ['locality', 'sign_type'], true)) {
                return false;
            }

            if (function_exists('get_field')) {
                return !empty(get_field('show_form', $source));
            }

            return false;
        }

        $source_post = null;

        if ($source instanceof WP_Post) {
            $source_post = $source;
        } elseif (is_numeric($source)) {
            $source_post = get_post((int) $source);
        }

        if (!($source_post instanceof WP_Post)) {
            return false;
        }

        if (!in_array($source_post->post_type, ['page', 'post', 'service'], true)) {
            return false;
        }

        if ($source_post->post_type === 'page' && (int) $source_post->ID === (int) get_option('page_on_front')) {
            return false;
        }

        $show_form = function_exists('get_field')
            ? get_field('show_form', (int) $source_post->ID)
            : get_post_meta((int) $source_post->ID, 'show_form', true);

        return !empty($show_form);
    }

    function meza_site_documentation_get_form_usage_count(WP_Post $form_post): int
    {
        $form_id = (int) $form_post->ID;

        if ($form_id <= 0) {
            return 0;
        }

        $usage_context_keys = [];

        $page_ids = get_posts([
            'post_type' => 'page',
            'post_status' => meza_site_documentation_usage_post_statuses(),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        foreach ((array) $page_ids as $page_id) {
            $page_id = (int) $page_id;

            if (!meza_site_documentation_source_renders_form($page_id)) {
                continue;
            }

            $attached_form_ids = meza_site_documentation_get_runtime_form_ids($page_id);

            if (in_array($form_id, $attached_form_ids, true)) {
                $usage_context_keys[] = 'page:' . $page_id;
            }
        }

        $service_ids = get_posts([
            'post_type' => 'service',
            'post_status' => meza_site_documentation_usage_post_statuses(),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        foreach ((array) $service_ids as $service_id) {
            $service_id = (int) $service_id;

            if (!meza_site_documentation_source_renders_form($service_id)) {
                continue;
            }

            if (in_array($form_id, meza_site_documentation_get_runtime_form_ids($service_id), true)) {
                $usage_context_keys[] = 'service:' . $service_id;
            }
        }

        $post_ids = get_posts([
            'post_type' => 'post',
            'post_status' => meza_site_documentation_usage_post_statuses(),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        foreach ((array) $post_ids as $post_id) {
            $post_id = (int) $post_id;
            if (!meza_site_documentation_source_renders_form($post_id)) {
                continue;
            }

            if (in_array($form_id, meza_site_documentation_get_runtime_form_ids($post_id), true)) {
                $usage_context_keys[] = 'post:' . $post_id;
            }
        }

        foreach (['locality', 'sign_type'] as $taxonomy) {
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
            ]);

            if (is_wp_error($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                if (!($term instanceof WP_Term)) {
                    continue;
                }

                if (
                    meza_site_documentation_source_renders_form($term)
                    && in_array($form_id, meza_site_documentation_get_runtime_form_ids($term), true)
                ) {
                    $usage_context_keys[] = $taxonomy . ':' . (int) $term->term_id;
                }
            }
        }

        return count(array_unique($usage_context_keys));
    }

    function meza_site_documentation_get_faq_usage_count(WP_Post $faq_post): int
    {
        $faq_id = (int) $faq_post->ID;

        if ($faq_id <= 0) {
            return 0;
        }

        $page_ids = get_posts([
            'post_type' => 'page',
            'post_status' => meza_site_documentation_usage_post_statuses(),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        $usage_page_ids = [];

        foreach ((array) $page_ids as $page_id) {
            $page_id = (int) $page_id;

            if (function_exists('meza_site_documentation_special_page_label')
                && meza_site_documentation_special_page_label($page_id) === 'FAQ Page') {
                $usage_page_ids[] = $page_id;
            }

            if (in_array($faq_id, meza_site_documentation_get_page_faq_ids($page_id), true)) {
                $usage_page_ids[] = $page_id;
            }
        }

        return count(array_unique(array_filter($usage_page_ids)));
    }

    function meza_site_documentation_get_cta_usage_count(WP_Post $cta_post): int
    {
        $cta_id = (int) $cta_post->ID;

        if ($cta_id <= 0) {
            return 0;
        }

        $candidate_post_types = [];
        $post_type_objects = get_post_types([], 'objects');

        foreach ($post_type_objects as $post_type_object) {
            if (!($post_type_object instanceof WP_Post_Type)) {
                continue;
            }

            $post_type = (string) $post_type_object->name;

            if (in_array($post_type, ['attachment', 'revision', 'nav_menu_item', 'cta', 'faq', 'form', 'mzf_submission'], true)) {
                continue;
            }

            if (function_exists('meza_site_documentation_is_documented_post_type')) {
                if (!meza_site_documentation_is_documented_post_type($post_type_object)) {
                    continue;
                }
            } elseif (!$post_type_object->show_ui) {
                continue;
            }

            $candidate_post_types[] = $post_type;
        }

        if ($candidate_post_types === []) {
            return 0;
        }

        $post_ids = get_posts([
            'post_type' => $candidate_post_types,
            'post_status' => meza_site_documentation_usage_post_statuses(),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        $count = 0;

        foreach ((array) $post_ids as $post_id) {
            if (in_array($cta_id, meza_site_documentation_get_post_cta_ids((int) $post_id), true)) {
                $count++;
            }
        }

        return $count;
    }

    function meza_site_documentation_get_content_only_rows(): array
    {
        $post_type_objects = get_post_types([], 'objects');
        $rows = [];

        foreach ($post_type_objects as $post_type_object) {
            if (!($post_type_object instanceof WP_Post_Type) || !meza_site_documentation_is_documented_post_type($post_type_object)) {
                continue;
            }

            if (meza_site_documentation_post_type_has_public_permalink($post_type_object)) {
                continue;
            }

            $post_type = (string) $post_type_object->name;
            $edit_capability = isset($post_type_object->cap->edit_posts)
                ? (string) $post_type_object->cap->edit_posts
                : 'edit_posts';
            $group_label = meza_site_documentation_post_type_plural_label($post_type_object);
            $row_label = meza_site_documentation_post_type_singular_label($post_type_object);
            $role_labels = meza_site_documentation_role_labels_for_capability($edit_capability);

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

                $row = [
                    'key' => sanitize_key($post_type . '_' . ($item->post_name !== '' ? (string) $item->post_name : (string) $item->ID)),
                    'post_id' => (int) $item->ID,
                    'group' => $group_label,
                    'row_label' => $row_label,
                    'archive_family' => $group_label,
                    'title' => trim((string) $item->post_title),
                    'url' => '',
                    'visibility' => 'Content Only',
                    'roles_text' => meza_site_documentation_format_role_labels($role_labels),
                    'action' => 'Edit',
                ];

                if ($post_type === 'form') {
                    $row['usage_count'] = meza_site_documentation_get_form_usage_count($item);
                    $row['submissions_count'] = meza_site_documentation_get_form_successful_submission_count($item);
                    $row['submissions_url'] = meza_site_documentation_get_form_submissions_admin_url($item);
                    $row['recipient_emails'] = meza_site_documentation_get_form_recipient_emails($item);
                    $row['recipient_fallback_email'] = function_exists('meza_get_business_information_email')
                        ? sanitize_email((string) meza_get_business_information_email())
                        : (function_exists('get_field') ? sanitize_email((string) get_field('email', 'option')) : '');
                } elseif ($post_type === 'faq') {
                    $row['usage_count'] = meza_site_documentation_get_faq_usage_count($item);
                } elseif ($post_type === 'cta') {
                    $row['usage_count'] = meza_site_documentation_get_cta_usage_count($item);
                }

                if (!meza_site_documentation_should_include_row($row, 'pages')) {
                    continue;
                }

                $row['action_links'] = meza_site_documentation_get_row_action_links('pages', $row);
                $rows[] = $row;
            }
        }

        usort($rows, static function (array $left, array $right): int {
            $group_compare = strnatcasecmp((string) ($left['group'] ?? ''), (string) ($right['group'] ?? ''));

            if ($group_compare !== 0) {
                return $group_compare;
            }

            return strnatcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });

        return $rows;
    }
}
