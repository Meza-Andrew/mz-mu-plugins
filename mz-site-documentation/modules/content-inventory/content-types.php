<?php

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

if (!function_exists('meza_site_documentation_content_type_description')) {
    function meza_site_documentation_content_type_description(object $object): string
    {
        if ($object instanceof WP_Post_Type) {
            $post_type = (string) $object->name;

            $description = trim((string) ($object->description ?? ''));

            if ($post_type === 'page') {
                return 'Manage evergreen website pages such as service, landing, contact, and general information pages.';
            }

            if ($post_type === 'post') {
                return 'Manage time-based article content for news, insights, and educational resources.';
            }

            if ($post_type === 'product') {
                return 'Manage product content for the site\'s catalog and store inventory.';
            }

            if ($description !== '') {
                return $description;
            }

            return '';
        }

        if ($object instanceof WP_Taxonomy) {
            if ((string) $object->name === 'category') {
                return 'Organize article content into blog categories.';
            }

            if ((string) $object->name === 'product_brand') {
                return 'Organize products by manufacturer or brand so inventory can be grouped into browseable collections.';
            }

            if ((string) $object->name === 'product_cat') {
                return 'Organize products into browseable product categories.';
            }

            if ((string) $object->name === 'product_tag') {
                return 'Add flexible product labels for merchandising, filtering, and related-product organization.';
            }

            $description = trim((string) ($object->description ?? ''));

            if ($description !== '') {
                return $description;
            }

            return '';
        }

        return '';
    }
}

if (!function_exists('meza_site_documentation_randomize_examples')) {
    function meza_site_documentation_randomize_examples(array $values, int $limit): array
    {
        $values = array_values(array_filter(array_map(static function ($value): string {
            return trim((string) $value);
        }, $values)));

        if ($values === []) {
            return [];
        }

        shuffle($values);

        return array_slice(array_values(array_unique($values)), 0, $limit);
    }
}

if (!function_exists('meza_site_documentation_format_example_list')) {
    function meza_site_documentation_format_example_list(array $values): string
    {
        $values = array_values(array_filter(array_map(static function ($value): string {
            return trim((string) $value);
        }, $values)));

        $count = count($values);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $values[0];
        }

        if ($count === 2) {
            return $values[0] . ' and ' . $values[1];
        }

        $last_value = array_pop($values);

        return implode(', ', $values) . ', and ' . $last_value;
    }
}

if (!function_exists('meza_site_documentation_random_page_template_examples')) {
    function meza_site_documentation_random_page_template_examples(): array
    {
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => '_wp_page_template',
                    'value' => ['default', '', 'page-documentation.php', 'page-style.php', 'page-cookie-policy.php'],
                    'compare' => 'NOT IN',
                ],
            ],
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        if ($pages === []) {
            return [];
        }

        $titles_by_template = [];

        foreach ($pages as $page_id) {
            $page_id = (int) $page_id;
            $template = (string) get_page_template_slug($page_id);
            $title = trim((string) get_the_title($page_id));

            if ($template === '' || $title === '' || isset($titles_by_template[$template])) {
                continue;
            }

            $titles_by_template[$template] = $title;
        }

        return meza_site_documentation_randomize_examples(array_values($titles_by_template), 3);
    }
}

if (!function_exists('meza_site_documentation_random_post_examples')) {
    function meza_site_documentation_random_post_examples(): array
    {
        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        if ($posts === []) {
            return [];
        }

        $titles = array_map(static function ($post_id): string {
            return trim((string) get_the_title((int) $post_id));
        }, $posts);

        return meza_site_documentation_randomize_examples($titles, 2);
    }
}

if (!function_exists('meza_site_documentation_should_include_category_taxonomy')) {
    function meza_site_documentation_should_include_category_taxonomy(): bool
    {
        $terms = get_terms([
            'taxonomy' => 'category',
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || count($terms) !== 1) {
            return true;
        }

        $term = $terms[0];

        if (!($term instanceof WP_Term)) {
            return true;
        }

        $default_category_id = (int) get_option('default_category');

        return !(
            $default_category_id > 0
            && (int) $term->term_id === $default_category_id
            && sanitize_title((string) $term->slug) === 'uncategorized'
        );
    }
}

if (!function_exists('meza_site_documentation_get_content_type_group_label')) {
    function meza_site_documentation_get_content_type_group_label(WP_Post_Type $post_type_object): string
    {
        $singular_label = isset($post_type_object->labels->singular_name)
            ? trim((string) $post_type_object->labels->singular_name)
            : '';

        if ($singular_label !== '') {
            return $singular_label;
        }

        $label = isset($post_type_object->labels->name)
            ? trim((string) $post_type_object->labels->name)
            : '';

        if ($label !== '') {
            return $label;
        }

        return ucfirst((string) $post_type_object->name);
    }
}

if (!function_exists('meza_site_documentation_get_content_type_taxonomy_label')) {
    function meza_site_documentation_get_content_type_taxonomy_label(WP_Taxonomy $taxonomy, WP_Post_Type $post_type_object): string
    {
        $label = isset($taxonomy->labels->name)
            ? trim((string) $taxonomy->labels->name)
            : ucfirst((string) $taxonomy->name);

        if ($label === '') {
            return $label;
        }

        $post_type_labels = [
            isset($post_type_object->labels->name) ? trim((string) $post_type_object->labels->name) : '',
            isset($post_type_object->labels->singular_name) ? trim((string) $post_type_object->labels->singular_name) : '',
        ];

        $post_type_labels = array_values(array_unique(array_filter($post_type_labels, static function ($candidate): bool {
            return is_string($candidate) && trim($candidate) !== '';
        })));

        usort($post_type_labels, static function (string $left, string $right): int {
            return strlen($right) <=> strlen($left);
        });

        foreach ($post_type_labels as $post_type_label) {
            $trimmed_label = preg_replace('/^' . preg_quote($post_type_label, '/') . '\s+/i', '', $label);

            if (!is_string($trimmed_label)) {
                continue;
            }

            $trimmed_label = trim($trimmed_label);

            if ($trimmed_label !== '' && strcasecmp($trimmed_label, $label) !== 0) {
                return $trimmed_label;
            }

            $trimmed_label = preg_replace('/\s+' . preg_quote($post_type_label, '/') . '$/i', '', $label);

            if (!is_string($trimmed_label)) {
                continue;
            }

            $trimmed_label = trim($trimmed_label);

            if ($trimmed_label !== '' && strcasecmp($trimmed_label, $label) !== 0) {
                return $trimmed_label;
            }
        }

        return $label;
    }
}

if (!function_exists('meza_site_documentation_get_content_type_rows')) {
    function meza_site_documentation_get_content_type_rows(): array
    {
        $post_type_objects = get_post_types([], 'objects');
        $rows = [];
        $yoast_active = meza_site_documentation_is_plugin_active('wordpress-seo/wp-seo.php');
        $taxonomy_counts = [];

        foreach ($post_type_objects as $post_type_object) {
            if (!($post_type_object instanceof WP_Post_Type) || !meza_site_documentation_is_documented_post_type($post_type_object)) {
                continue;
            }

            $post_type = (string) $post_type_object->name;
            $post_type_group_label = meza_site_documentation_get_content_type_group_label($post_type_object);
            $edit_capability = isset($post_type_object->cap->edit_posts)
                ? (string) $post_type_object->cap->edit_posts
                : '';
            $post_type_has_public_permalink = meza_site_documentation_post_type_has_public_permalink($post_type_object);
            $post_type_is_public_by_default = $post_type_has_public_permalink;
            $post_type_counts = wp_count_posts($post_type);
            $post_type_count = $post_type_counts instanceof stdClass
                ? array_sum((array) $post_type_counts)
                : 0;

            $post_type_row = [
                'key' => sanitize_key($post_type),
                'group' => $post_type_group_label,
                'item' => (string) ($post_type_object->labels->name ?? ucfirst($post_type)),
                'description' => meza_site_documentation_content_type_description($post_type_object),
                'count' => (int) $post_type_count,
                'visibility' => meza_site_documentation_content_type_visibility_label(
                    $post_type_has_public_permalink,
                    $post_type_is_public_by_default
                ),
                'has_public_permalink' => $post_type_has_public_permalink,
                'is_public_by_default' => $post_type_is_public_by_default,
                'roles_text' => meza_site_documentation_format_role_labels(
                    meza_site_documentation_role_labels_for_capability($edit_capability)
                ),
                'required_capability' => $edit_capability,
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

                if ((string) $taxonomy->name === 'category' && !meza_site_documentation_should_include_category_taxonomy()) {
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
                $taxonomy_name = (string) $taxonomy->name;

                if (!array_key_exists($taxonomy_name, $taxonomy_counts)) {
                    $taxonomy_counts[$taxonomy_name] = (int) wp_count_terms([
                        'taxonomy' => $taxonomy_name,
                        'hide_empty' => false,
                    ]);
                }

                $taxonomy_row = [
                    'key' => sanitize_key($post_type . '_' . $taxonomy_name),
                    'group' => $post_type_group_label,
                    'item' => meza_site_documentation_get_content_type_taxonomy_label($taxonomy, $post_type_object),
                    'description' => meza_site_documentation_content_type_description($taxonomy),
                    'count' => (int) ($taxonomy_counts[$taxonomy_name] ?? 0),
                    'visibility' => meza_site_documentation_content_type_visibility_label(
                        $taxonomy_has_public_permalink,
                        $taxonomy_is_public_by_default
                    ),
                    'has_public_permalink' => $taxonomy_has_public_permalink,
                    'is_public_by_default' => $taxonomy_is_public_by_default,
                    'roles_text' => meza_site_documentation_format_role_labels(
                        meza_site_documentation_role_labels_for_capability($taxonomy_capability)
                    ),
                    'required_capability' => $taxonomy_capability,
                    'action' => 'Edit',
                    'kind' => 'taxonomy',
                    'action_url' => admin_url('edit-tags.php?taxonomy=' . $taxonomy_name . '&post_type=' . $post_type),
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

if (!function_exists('meza_site_documentation_get_taxonomy_term_rows')) {
    function meza_site_documentation_get_taxonomy_term_post_dates(int $term_id, string $taxonomy): array
    {
        $term_id = (int) $term_id;
        $taxonomy = trim($taxonomy);

        if ($term_id <= 0 || $taxonomy === '') {
            return [
                'published' => '',
                'modified' => '',
            ];
        }

        $object_ids = array_map('intval', get_objects_in_term($term_id, $taxonomy));
        $object_ids = array_values(array_unique(array_filter($object_ids)));

        if ($object_ids === []) {
            return [
                'published' => '',
                'modified' => '',
            ];
        }

        $published_timestamps = [];
        $modified_timestamps = [];

        foreach ($object_ids as $object_id) {
            $post = get_post($object_id);

            if (!($post instanceof WP_Post) || $post->post_status === 'auto-draft') {
                continue;
            }

            $published_datetime = get_post_datetime($post, 'date');
            if ($published_datetime instanceof DateTimeInterface) {
                $published_timestamps[] = $published_datetime->getTimestamp();
            }

            $modified_datetime = get_post_datetime($post, 'modified');
            if ($modified_datetime instanceof DateTimeInterface) {
                $modified_timestamps[] = $modified_datetime->getTimestamp();
            }
        }

        $format_timestamp = static function (array $timestamps, string $mode): string {
            if ($timestamps === []) {
                return '';
            }

            $timestamp = $mode === 'published'
                ? min($timestamps)
                : max($timestamps);

            return wp_date('Y/m/d', $timestamp);
        };

        return [
            'published' => $format_timestamp($published_timestamps, 'published'),
            'modified' => $format_timestamp($modified_timestamps, 'modified'),
        ];
    }
}

if (!function_exists('meza_site_documentation_get_taxonomy_term_rows')) {
    function meza_site_documentation_get_taxonomy_term_rows(): array
    {
        $taxonomy_objects = get_taxonomies([], 'objects');
        $rows = [];
        $seen_taxonomies = [];

        foreach ($taxonomy_objects as $taxonomy) {
            if (!($taxonomy instanceof WP_Taxonomy) || !meza_site_documentation_is_documented_taxonomy($taxonomy)) {
                continue;
            }

            if ((string) $taxonomy->name === 'category' && !meza_site_documentation_should_include_category_taxonomy()) {
                continue;
            }

            $taxonomy_name = (string) $taxonomy->name;
            if (isset($seen_taxonomies[$taxonomy_name])) {
                continue;
            }
            $seen_taxonomies[$taxonomy_name] = true;

            $terms = get_terms([
                'taxonomy' => $taxonomy_name,
                'hide_empty' => false,
            ]);

            if (is_wp_error($terms) || !is_array($terms) || $terms === []) {
                continue;
            }

            $term_capability = isset($taxonomy->cap->manage_terms) && is_string($taxonomy->cap->manage_terms) && $taxonomy->cap->manage_terms !== ''
                ? $taxonomy->cap->manage_terms
                : (string) ($taxonomy->cap->edit_terms ?? '');
            $taxonomy_has_public_permalink = meza_site_documentation_taxonomy_has_public_permalink($taxonomy);
            $taxonomy_label = isset($taxonomy->labels->name)
                ? trim((string) $taxonomy->labels->name)
                : ucfirst($taxonomy_name);
            $term_label = isset($taxonomy->labels->singular_name)
                ? trim((string) $taxonomy->labels->singular_name)
                : 'Term';
            $primary_post_type = '';

            foreach ((array) $taxonomy->object_type as $object_type) {
                $object_type = is_string($object_type) ? trim($object_type) : '';

                if ($object_type === '') {
                    continue;
                }

                $post_type_object = get_post_type_object($object_type);
                if ($post_type_object instanceof WP_Post_Type && meza_site_documentation_is_documented_post_type($post_type_object)) {
                    $primary_post_type = $object_type;
                    break;
                }
            }

            foreach ($terms as $term) {
                if (!($term instanceof WP_Term)) {
                    continue;
                }

                $term_name = trim((string) $term->name);
                if ($term_name === '') {
                    continue;
                }

                $term_url = '';
                if ($taxonomy_has_public_permalink) {
                    $resolved_term_url = get_term_link($term);

                    if (is_string($resolved_term_url) && !is_wp_error($resolved_term_url)) {
                        $term_url = $resolved_term_url;
                    }
                }

                $edit_url = admin_url(
                    'term.php?taxonomy=' . rawurlencode($taxonomy_name)
                    . '&tag_ID=' . (int) $term->term_id
                    . ($primary_post_type !== '' ? '&post_type=' . rawurlencode($primary_post_type) : '')
                );
                $term_dates = meza_site_documentation_get_taxonomy_term_post_dates((int) $term->term_id, $taxonomy_name);

                $row = [
                    'key' => sanitize_key($taxonomy_name . '_' . (string) $term->term_id),
                    'group' => $taxonomy_label !== '' ? $taxonomy_label : ucfirst($taxonomy_name),
                    'row_label' => $term_label !== '' ? $term_label : 'Term',
                    'taxonomy' => $taxonomy_name,
                    'term_id' => (int) $term->term_id,
                    'title' => $term_name,
                    'url' => $term_url,
                    'has_public_permalink' => $taxonomy_has_public_permalink,
                    'visibility' => meza_site_documentation_content_type_visibility_label(
                        $taxonomy_has_public_permalink,
                        $taxonomy_has_public_permalink
                    ),
                    'roles_text' => meza_site_documentation_format_role_labels(
                        meza_site_documentation_role_labels_for_capability($term_capability)
                    ),
                    'modified' => (string) ($term_dates['modified'] ?? ''),
                    'published' => (string) ($term_dates['published'] ?? ''),
                    'count' => (int) $term->count,
                    'required_capability' => $term_capability,
                    'action' => 'View / Edit',
                    'action_url' => $edit_url,
                ];

                if (!meza_site_documentation_should_include_row($row, 'pages')) {
                    continue;
                }

                $row['action_links'] = meza_site_documentation_get_row_action_links('taxonomy_terms', $row);
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
