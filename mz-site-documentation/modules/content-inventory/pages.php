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
            return 'Unlisted but Link Accessible';
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

if (!function_exists('meza_site_documentation_get_content_only_rows')) {
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

