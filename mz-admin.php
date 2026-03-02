<?php

/**
 * Plugin Name: MZ Admin
 * Description: Admin behavior, editorial workflow, and dashboard customization.
 * Version: 1.1.0
 * Author: Meza LLC
 * Author URI: https://meza.design
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) return;

/** ================================
 *  EVENT ADMIN SORTING
 *  ================================ */

// Keep a raw sortable version of start_datetime in Y-m-d H:i:s.
add_action('save_post_event', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;

    $pretty = get_post_meta($post_id, 'start_datetime', true); // e.g. "September 9, 2025 6:00 pm"
    if (!$pretty) {
        delete_post_meta($post_id, 'start_datetime_raw');
        return;
    }

    $ts = strtotime($pretty);
    if ($ts) {
        update_post_meta($post_id, 'start_datetime_raw', date('Y-m-d H:i:s', $ts));
    }
});

// Register Admin Columns key as sortable.
add_filter('manage_edit-event_sortable_columns', function ($cols) {
    // `true` sets the initial click direction to DESC.
    $cols['1b5a56da68f5c3'] = ['start_datetime_order', true]; // Admin Columns key.
    return $cols;
}, 1000);

function meza_get_user_first_name_by_id(int $user_id): string
{
    if ($user_id <= 0) return '';

    $first_name = trim((string) get_user_meta($user_id, 'first_name', true));
    if ($first_name !== '') return $first_name;

    $user = get_userdata($user_id);
    if (!($user instanceof WP_User)) return '';

    return trim((string) $user->display_name);
}

function meza_get_user_email_by_id(int $user_id): string
{
    if ($user_id <= 0) return '';
    $user = get_userdata($user_id);
    if (!($user instanceof WP_User)) return '';
    return sanitize_email((string) $user->user_email);
}

function meza_compact_meridiem(string $time): string
{
    return preg_replace('/\s+([ap])\.?m\.?$/i', '$1m', trim($time)) ?? trim($time);
}

function meza_post_has_internal_pages(WP_Post $post): bool
{
    $content = (string) ($post->post_content ?? '');
    if ($content === '') return false;

    if (str_contains($content, '<!--nextpage-->')) return true;
    if ((bool) preg_match('/<!--\s*wp:nextpage\b[^>]*-->/i', $content)) return true;
    if (function_exists('has_block') && has_block('nextpage', $post)) return true;

    return false;
}

function meza_post_type_has_pages(WP_Post $post): bool
{
    $post_type = (string) ($post->post_type ?? '');
    if ($post_type === 'page') return true;
    return meza_post_has_internal_pages($post);
}

function meza_post_type_has_permalink(string $post_type): bool
{
    $post_type = trim($post_type);
    if ($post_type === '') return false;

    $post_type_object = get_post_type_object($post_type);
    if (!($post_type_object instanceof WP_Post_Type)) return false;

    if (function_exists('is_post_type_viewable') && !is_post_type_viewable($post_type_object)) {
        return false;
    }

    if ($post_type === 'post' || $post_type === 'page') return true;

    return !empty($post_type_object->rewrite) || !empty($post_type_object->query_var);
}

function meza_post_has_permalink(int $post_id): bool
{
    if ($post_id <= 0) return false;
    $url = get_permalink($post_id);
    return is_string($url) && $url !== '' && !is_wp_error($url);
}

function meza_should_show_posts_categories_column(): bool
{
    $default_category_id = (int) get_option('default_category');
    if ($default_category_id <= 0) return true;

    $category_ids = get_terms([
        'taxonomy' => 'category',
        'hide_empty' => false,
        'fields' => 'ids',
    ]);

    if (is_wp_error($category_ids) || !is_array($category_ids)) return true;

    $category_ids = array_values(array_unique(array_map('intval', $category_ids)));
    if (count($category_ids) !== 1) return true;

    return ((int) $category_ids[0] !== $default_category_id);
}

function meza_normalize_datetime_columns(array $columns): array
{
    if (!is_array($columns)) return $columns;

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $post_type = ($screen instanceof WP_Screen) ? (string) ($screen->post_type ?? '') : '';
    $supports_thumbnail = ($post_type !== '' && post_type_supports($post_type, 'thumbnail'));
    $show_page_columns = !meza_is_acf_admin_post_type($post_type) && meza_post_type_has_permalink($post_type);

    $has_modified = false;
    foreach ($columns as $key => $label) {
        $normalized_key = strtolower(trim((string) $key));
        $normalized_label = strtolower(trim(wp_strip_all_tags((string) $label)));
        if ($normalized_key === 'modified' || $normalized_key === 'mz_modified' || $normalized_label === 'modified') {
            $has_modified = true;
        }
    }

    $updated = [];
    foreach ($columns as $key => $label) {
        $normalized_key = strtolower(trim((string) $key));

        // Normalize custom identity columns so we can insert them in a stable place.
        if ($key === 'mz_id' || $key === 'mz_thumbnail') continue;
        if (!$show_page_columns && in_array((string) $key, ['mz_page_link', 'mz_page_headline', 'mz_page_cta'], true)) continue;

        // Posts list: remove non-editorial columns.
        if ($post_type === 'post') {
            if (in_array($normalized_key, ['author', 'comments', 'tags', 'taxonomy-post_tag'], true)) continue;
            if ($normalized_key === 'categories' && !meza_should_show_posts_categories_column()) continue;
        }
        if ($post_type === 'page') {
            if (in_array($normalized_key, ['author', 'comments'], true)) continue;
        }

        if ($key === 'title') {
            $updated['mz_id'] = __('ID');
            if ($supports_thumbnail) $updated['mz_thumbnail'] = __('Image');
            $updated[$key] = $label;
            if ($show_page_columns) {
                $updated['mz_page_link'] = __('Link');
                $updated['mz_page_headline'] = __('Page Headline (H1)');
                $updated['mz_page_cta'] = __('Page CTA');
            }
            continue;
        }

        if ($key === 'date') {
            if (!$has_modified) $updated['mz_modified'] = __('Modified');
            $updated['mz_published'] = __('Published');
            continue;
        }
        $updated[$key] = $label;
    }

    if (!isset($updated['mz_published'])) $updated['mz_published'] = __('Published');
    if (!$has_modified && !isset($updated['mz_modified'])) $updated['mz_modified'] = __('Modified');
    if ($show_page_columns) {
        if (!isset($updated['mz_page_link'])) $updated['mz_page_link'] = __('Link');
        if (!isset($updated['mz_page_headline'])) $updated['mz_page_headline'] = __('Page Headline (H1)');
        if (!isset($updated['mz_page_cta'])) $updated['mz_page_cta'] = __('Page CTA');
    }

    // Enforce editorial column order.
    $ordered = [];
    $used = [];

    $append = static function (string $key) use (&$ordered, &$updated, &$used): void {
        if (isset($used[$key])) return;
        if (!array_key_exists($key, $updated)) return;
        $ordered[$key] = $updated[$key];
        $used[$key] = true;
    };

    // Keep bulk checkbox first when present.
    $append('cb');

    if ($post_type === 'page') {
        // Pages: id, image, title, link, headline, cta, meta title, meta description, template, modified, published.
        $append('mz_id');
        $append('mz_thumbnail');
        $append('title');
        if ($show_page_columns) {
            $append('mz_page_link');
            $append('mz_page_headline');
            $append('mz_page_cta');
        }
        $append('wpseo-title');
        $append('wpseo-metadesc');
        $append('mz_page_template');
        $append('template');
    } else {
        $append('mz_id');
        $append('mz_thumbnail');
        $append('title');
        if ($show_page_columns) {
            $append('mz_page_link');
            $append('mz_page_headline');
            $append('mz_page_cta');
        }
        $append('wpseo-title');
        $append('wpseo-metadesc');
    }

    foreach (array_keys($updated) as $key) {
        if (str_starts_with((string) $key, 'taxonomy-') || $key === 'categories') {
            $append((string) $key);
        }
    }

    $append('mz_modified');
    $append('modified');
    $append('mz_published');
    $append('date');

    // Append any remaining columns in their original order.
    foreach (array_keys($updated) as $key) {
        $append((string) $key);
    }

    return $ordered;
}

function meza_register_datetime_sortable_columns(array $cols): array
{
    $cols['mz_published'] = ['date', true];
    $cols['mz_modified'] = ['modified', true];
    return $cols;
}

function meza_register_taxonomy_sortable_columns(array $cols): array
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return $cols;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === '') return $cols;

    $taxonomies = get_object_taxonomies($post_type, 'objects');
    if (!is_array($taxonomies)) return $cols;

    foreach ($taxonomies as $taxonomy => $taxonomy_obj) {
        if (!is_object($taxonomy_obj) || empty($taxonomy_obj->show_admin_column)) continue;

        $cols["taxonomy-{$taxonomy}"] = ["mz_tax_{$taxonomy}", false];
    }

    return $cols;
}

function meza_render_posts_list_column(string $column, int $post_id): void
{
    $post = get_post((int) $post_id);
    if (!($post instanceof WP_Post)) {
        if (
            $column === 'mz_modified' ||
            $column === 'mz_published' ||
            $column === 'mz_id' ||
            $column === 'mz_thumbnail' ||
            $column === 'mz_page_link' ||
            $column === 'mz_page_headline' ||
            $column === 'mz_page_cta'
        ) {
            echo '&mdash;';
        }
        return;
    }

    if ($column === 'mz_id') {
        echo (int) $post_id;
        return;
    }

    if ($column === 'mz_thumbnail') {
        if (!post_type_supports((string) $post->post_type, 'thumbnail')) {
            echo '&mdash;';
            return;
        }

        $thumb_id = (int) get_post_thumbnail_id((int) $post_id);
        $thumb_html = get_the_post_thumbnail(
            (int) $post_id,
            [100, 100],
            [
                'style' => 'width:100px;height:100px;object-fit:cover;',
                'loading' => 'lazy',
                'decoding' => 'async',
            ]
        );
        if ($thumb_html === '') {
            echo '&mdash;';
            return;
        }

        $thumb_alt = '';
        $thumb_url = '';
        if ($thumb_id > 0) {
            $thumb_alt = trim((string) get_post_meta($thumb_id, '_wp_attachment_image_alt', true));
            $thumb_url = (string) wp_get_attachment_url($thumb_id);
        }

        $actions = [];
        $image_edit_link = ($thumb_id > 0) ? get_edit_post_link($thumb_id) : '';
        if (is_string($image_edit_link) && $image_edit_link !== '') {
            if ($thumb_alt === '') {
                $actions[] = '<span class="fix-alt"><a href="' . esc_url($image_edit_link) . '" target="_blank" rel="noopener noreferrer" style="color:#b32d2e;font-weight:600;">' . esc_html__('Fix Alt Text') . '</a></span>';
            } else {
                $actions[] = '<span class="edit"><a href="' . esc_url($image_edit_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Edit') . '</a></span>';
            }
        }

        if ($thumb_url !== '') {
            $actions[] = '<span class="view"><a href="' . esc_url($thumb_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View') . '</a></span>';
        }

        if ($thumb_url !== '') {
            $actions[] = '<span class="download"><a href="' . esc_url($thumb_url) . '" download>' . esc_html__('Download') . '</a></span>';
        }

        if (is_string($image_edit_link) && $image_edit_link !== '') {
            echo '<a href="' . esc_url($image_edit_link) . '" target="_blank" rel="noopener noreferrer">' . $thumb_html . '</a>';
        } else {
            echo $thumb_html;
        }
        if (!empty($actions)) echo '<div class="row-actions">' . implode(' | ', $actions) . '</div>';
        return;
    }

    if ($column === 'mz_page_link') {
        if (!meza_post_has_permalink((int) $post_id)) {
            echo '&mdash;';
            return;
        }
        $url = (string) get_permalink((int) $post_id);

        echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($url) . '</a>';
        $actions = [];

        $edit_link = get_edit_post_link((int) $post_id);
        if (is_string($edit_link) && $edit_link !== '') {
            $actions[] = '<span class="edit"><a href="' . esc_url($edit_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Edit') . '</a></span>';
        }

        $actions[] = '<span class="view"><a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View') . '</a></span>';
        $actions[] = '<span class="copy"><a href="#" class="mz-copy-link" data-copy-text="' . esc_attr($url) . '">' . esc_html__('Copy URL') . '</a></span>';

        echo '<div class="row-actions">' . implode(' | ', $actions) . '</div>';
        return;
    }

    if ($column === 'mz_page_headline') {
        if (!meza_post_type_has_pages($post) || !meza_post_has_permalink((int) $post_id)) {
            echo '&mdash;';
            return;
        }

        $headline = '';
        if (function_exists('get_field')) {
            $hero_group = get_field('section_hero', (int) $post_id);
            if (is_array($hero_group) && isset($hero_group['headline']) && is_string($hero_group['headline'])) {
                $headline = trim((string) $hero_group['headline']);
            }
            $acf_headline = get_field('section_hero_headline', (int) $post_id);
            if ($headline === '' && is_string($acf_headline)) $headline = trim($acf_headline);
        }
        if ($headline === '') {
            $headline = trim((string) get_post_meta((int) $post_id, 'section_hero_headline', true));
        }
        if ($headline === '') {
            $hero_meta = get_post_meta((int) $post_id, 'section_hero', true);
            if (is_array($hero_meta) && isset($hero_meta['headline']) && is_string($hero_meta['headline'])) {
                $headline = trim((string) $hero_meta['headline']);
            }
        }

        echo ($headline !== '') ? esc_html($headline) : '&mdash;';
        return;
    }

    if ($column === 'mz_page_cta') {
        if (!meza_post_type_has_pages($post) || !meza_post_has_permalink((int) $post_id)) {
            echo '&mdash;';
            return;
        }

        $cta_ids = [];
        if (function_exists('get_field')) {
            $acf_cta_candidates = [
                get_field('section_cta_cta', (int) $post_id),
                get_field('cta', (int) $post_id),
                get_field('section_cta', (int) $post_id),
            ];

            foreach ($acf_cta_candidates as $acf_cta) {
                if (is_array($acf_cta) && isset($acf_cta['cta'])) $acf_cta = $acf_cta['cta'];

                if (is_array($acf_cta)) {
                    foreach ($acf_cta as $entry) {
                        if (is_numeric($entry)) $cta_ids[] = (int) $entry;
                        if ($entry instanceof WP_Post) $cta_ids[] = (int) $entry->ID;
                    }
                } elseif (is_numeric($acf_cta)) {
                    $cta_ids[] = (int) $acf_cta;
                } elseif ($acf_cta instanceof WP_Post) {
                    $cta_ids[] = (int) $acf_cta->ID;
                }
            }
        }

        if (empty($cta_ids)) {
            $raw_cta_candidates = [
                get_post_meta((int) $post_id, 'section_cta_cta', true),
                get_post_meta((int) $post_id, 'cta', true),
                get_post_meta((int) $post_id, 'section_cta', true),
            ];

            foreach ($raw_cta_candidates as $raw_cta) {
                if (is_array($raw_cta) && isset($raw_cta['cta'])) $raw_cta = $raw_cta['cta'];

                if (is_array($raw_cta)) {
                    foreach ($raw_cta as $entry) {
                        if (is_numeric($entry)) $cta_ids[] = (int) $entry;
                    }
                } elseif (is_numeric($raw_cta)) {
                    $cta_ids[] = (int) $raw_cta;
                }
            }
        }

        $cta_ids = array_values(array_unique(array_filter($cta_ids, fn($id) => $id > 0)));
        if (empty($cta_ids)) {
            echo '&mdash;';
            return;
        }

        $links = [];
        foreach ($cta_ids as $cta_id) {
            $title = get_the_title($cta_id);
            $edit_link = get_edit_post_link($cta_id);
            if (!is_string($title) || trim($title) === '') $title = sprintf(__('CTA #%d'), $cta_id);

            if (is_string($edit_link) && $edit_link !== '') {
                $links[] = '<a href="' . esc_url($edit_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html($title) . '</a>';
            } else {
                $links[] = esc_html($title);
            }
        }

        echo !empty($links) ? implode('<br>', $links) : '&mdash;';
        return;
    }

    if ($column === 'mz_modified') {
        $modified_timestamp = get_post_modified_time('U', false, $post, true);
        if (!$modified_timestamp) {
            echo '&mdash;';
            return;
        }

        $modified_by_id = (int) get_post_meta((int) $post_id, '_edit_last', true);
        if ($modified_by_id <= 0) $modified_by_id = (int) $post->post_author;
        $modified_by_name = meza_get_user_first_name_by_id($modified_by_id);
        $modified_by_email = meza_get_user_email_by_id($modified_by_id);

        $header = esc_html__('Last Modified');
        if ($modified_by_name !== '') {
            $header .= ' ' . esc_html__('by') . ' ';
            if ($modified_by_email !== '') {
                $header .= '<a href="' . esc_url('mailto:' . $modified_by_email) . '">' . esc_html($modified_by_name) . '</a>';
            } else {
                $header .= esc_html($modified_by_name);
            }
        }

        $date = wp_date(get_option('date_format'), $modified_timestamp);
        $time = meza_compact_meridiem(wp_date(get_option('time_format'), $modified_timestamp));
        $line = sprintf(__('%1$s at %2$s'), $date, $time);
        echo $header . '<br>' . esc_html($line);
        return;
    }

    if ($column !== 'mz_published') return;

    $published_timestamp = get_post_time('U', false, $post, true);
    if (!$published_timestamp) {
        echo '&mdash;';
        return;
    }

    $published_by_name = meza_get_user_first_name_by_id((int) $post->post_author);
    $published_by_email = meza_get_user_email_by_id((int) $post->post_author);
    $header = esc_html__('Published');
    if ($published_by_name !== '') {
        $header .= ' ' . esc_html__('by') . ' ';
        if ($published_by_email !== '') {
            $header .= '<a href="' . esc_url('mailto:' . $published_by_email) . '">' . esc_html($published_by_name) . '</a>';
        } else {
            $header .= esc_html($published_by_name);
        }
    }
    $date = wp_date(get_option('date_format'), $published_timestamp);
    $time = meza_compact_meridiem(wp_date(get_option('time_format'), $published_timestamp));
    $line = sprintf(__('%1$s at %2$s'), $date, $time);
    echo $header . '<br>' . esc_html($line);
}

// Apply Published/Modified columns to all post-type list tables on edit screens.
add_action('current_screen', function ($screen) {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === '') return;
    static $registered = [];
    if (isset($registered[$post_type])) return;
    $registered[$post_type] = true;

    add_filter("manage_{$post_type}_posts_columns", 'meza_normalize_datetime_columns', 1000);
    add_action("manage_{$post_type}_posts_custom_column", 'meza_render_posts_list_column', 100, 2);
    add_filter("manage_edit-{$post_type}_sortable_columns", 'meza_register_datetime_sortable_columns', 1000);
    add_filter("manage_edit-{$post_type}_sortable_columns", 'meza_register_taxonomy_sortable_columns', 1001);
});

// Keep explicit event start-date sorting. Global default ordering is set below.
add_action('pre_get_posts', function (WP_Query $q) {
    global $pagenow;
    if (!is_admin() || !$q->is_main_query() || $pagenow !== 'edit.php' || $q->get('post_type') !== 'event') return;

    $orderby = (string) $q->get('orderby');
    $order   = strtoupper((string) $q->get('order'));
    $order   = in_array($order, ['ASC', 'DESC'], true) ? $order : '';

    if ($orderby === 'start_datetime_order') {
        $dir = $order ?: 'DESC';
        $q->set('meta_key', 'start_datetime_raw');
        $q->set('meta_type', 'DATETIME');
        $q->set('orderby', [
            'meta_value' => $dir,
            'ID'         => 'DESC',
        ]);
        $q->set('order', $dir);
        return;
    }

    if ($orderby === '') {
        $q->set('meta_key', '');
        $q->set('meta_type', '');
    }
});

/** ================================
 *  CTA ADMIN SORTING
 *  ================================ */

// Default all admin post list tables to "Last Modified" DESC unless user selected a different sort.
add_action('pre_get_posts', function (WP_Query $q) {
    global $pagenow;
    if (!is_admin() || !$q->is_main_query() || $pagenow !== 'edit.php') return;

    // Respect explicit user sorting from list-table header clicks.
    if (isset($_GET['orderby']) && $_GET['orderby'] !== '') return;

    $q->set('orderby', 'modified');
    $q->set('order', 'DESC');
}, 100);

// Enable alphabetical sorting for taxonomy list columns on all post list tables.
add_action('pre_get_posts', function (WP_Query $q) {
    global $pagenow;
    if (!is_admin() || !$q->is_main_query() || $pagenow !== 'edit.php') return;

    $orderby = (string) $q->get('orderby');
    if (!str_starts_with($orderby, 'mz_tax_')) return;

    $taxonomy = substr($orderby, 7);
    if (!is_string($taxonomy) || $taxonomy === '') return;
    if (!taxonomy_exists($taxonomy)) return;

    $post_type = (string) $q->get('post_type');
    if ($post_type === '' || !is_object_in_taxonomy($post_type, $taxonomy)) return;

    $order = strtoupper((string) $q->get('order'));
    $q->set('order', in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC');
    $q->set('meza_tax_sort', $taxonomy);
});

add_filter('posts_clauses', function (array $clauses, WP_Query $q): array {
    if (!is_admin() || !$q->is_main_query()) return $clauses;

    $taxonomy = (string) $q->get('meza_tax_sort');
    if ($taxonomy === '') return $clauses;

    global $wpdb;
    $order = strtoupper((string) $q->get('order'));
    $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC';
    $empty_rank_order = ($order === 'DESC') ? 'DESC' : 'ASC';
    $taxonomy_sql = esc_sql($taxonomy);

    $clauses['join'] .= " LEFT JOIN {$wpdb->term_relationships} AS meza_tr ON ({$wpdb->posts}.ID = meza_tr.object_id)";
    $clauses['join'] .= " LEFT JOIN {$wpdb->term_taxonomy} AS meza_tt ON (meza_tr.term_taxonomy_id = meza_tt.term_taxonomy_id AND meza_tt.taxonomy = '{$taxonomy_sql}')";
    $clauses['join'] .= " LEFT JOIN {$wpdb->terms} AS meza_t ON (meza_tt.term_id = meza_t.term_id)";

    $clauses['groupby'] = "{$wpdb->posts}.ID";
    $term_names_expr = "GROUP_CONCAT(DISTINCT meza_t.name ORDER BY meza_t.name ASC SEPARATOR ', ')";
    $clauses['orderby'] =
        "CASE WHEN NULLIF(TRIM(MIN(meza_t.name)), '') IS NULL THEN 1 ELSE 0 END {$empty_rank_order}, " .
        "COALESCE({$term_names_expr}, '') {$order}, " .
        "{$wpdb->posts}.post_title ASC";

    return $clauses;
}, 20, 2);

/** ================================
 *  YOAST COLUMN SORTING OVERRIDES
 *  ================================ */

// Rename Yoast list-table column labels for clarity.
add_action('current_screen', function ($screen) {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === '') return;

    add_filter("manage_{$post_type}_posts_columns", function ($cols) {
        if (!is_array($cols)) return $cols;

        foreach (['wpseo-links', 'wpseo-linked'] as $column_id) {
            if (array_key_exists($column_id, $cols)) unset($cols[$column_id]);
        }

        if (array_key_exists('wpseo-title', $cols)) {
            $cols['wpseo-title'] = 'Meta Title';
        }

        if (array_key_exists('wpseo-metadesc', $cols)) {
            $cols['wpseo-metadesc'] = 'Meta Description';
        }

        return $cols;
    }, 9999);
});

// Keep Yoast metadata columns readable with fixed max widths.
add_action('admin_head', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    echo '<style id="meza-yoast-column-widths">'
        . '.wp-list-table th.column-wpseo-title,.wp-list-table td.column-wpseo-title{width:225px;max-width:225px;}'
        . '.wp-list-table th.column-wpseo-metadesc,.wp-list-table td.column-wpseo-metadesc{width:275px;max-width:275px;}'
        . '.wp-list-table th.column-mz_page_link,.wp-list-table td.column-mz_page_link{width:225px;max-width:225px;}'
        . '</style>';
});

// Remove sortable behavior from Yoast SEO meta description columns.
add_action('current_screen', function ($screen) {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    $post_type = (string) ($screen->post_type ?? '');
    if ($post_type === '') return;

    add_filter("manage_edit-{$post_type}_sortable_columns", function ($cols) {
        foreach (array_keys($cols) as $key) {
            $normalized = strtolower((string) $key);
            if (
                str_contains($normalized, 'metadesc') ||
                str_contains($normalized, 'meta-desc') ||
                ($normalized === 'wpseo-metadesc')
            ) {
                unset($cols[$key]);
            }
        }

        return $cols;
    }, 9999);
});

/** ================================
 *  PAGES LIST COLUMN NORMALIZATION
 *  ================================ */

function meza_get_page_type_label(int $post_id): string
{
    // Prefer ACF value when available.
    if (function_exists('get_field')) {
        $acf_value = get_field('page_type', $post_id);
        if (is_string($acf_value) && trim($acf_value) !== '') return trim($acf_value);
        if (is_array($acf_value) && isset($acf_value['label']) && is_string($acf_value['label'])) {
            $label = trim($acf_value['label']);
            if ($label !== '') return $label;
        }
    }

    // Fallback to raw post meta.
    $meta_value = get_post_meta($post_id, 'page_type', true);
    if (is_string($meta_value) && trim($meta_value) !== '') return trim($meta_value);

    // Fallback to a `page_type` taxonomy term label when present.
    $terms = get_the_terms($post_id, 'page_type');
    if (!is_wp_error($terms) && !empty($terms)) {
        $first = reset($terms);
        if ($first && isset($first->name) && is_string($first->name) && trim($first->name) !== '') {
            return trim($first->name);
        }
    }

    return '';
}

function meza_get_page_template_label_map(): array
{
    $map = [
        'default' => 'Basic Page',
        'front-page.php' => 'Front Page',
        'home.php' => 'Posts Page',
        'page-events.php' => 'Events Page',
        'page-form.php' => 'Form Page',
        'page-partners.php' => 'Partners Page',
        'page-services.php' => 'Service Page',
        'page-team.php' => 'Team Page',
        'page-style-guide.php' => 'Style Guide',
    ];

    $filtered = apply_filters('meza/page_template_label_map', $map);
    return is_array($filtered) ? $filtered : $map;
}

function meza_get_page_template_label(int $post_id): string
{
    if ((int) get_option('page_on_front') === $post_id) return esc_html__('Front Page');
    if ((int) get_option('page_for_posts') === $post_id) return esc_html__('Posts Page');
    if ((int) get_option('wp_page_for_privacy_policy') === $post_id) return esc_html__('Privacy Policy Page');

    $template = (string) get_post_meta($post_id, '_wp_page_template', true);
    $template_map = meza_get_page_template_label_map();
    $default_label = trim((string) ($template_map['default'] ?? 'Basic Page'));

    if ($template === '' || $template === 'default') {
        $page_type = meza_get_page_type_label($post_id);
        if ($page_type !== '') return esc_html($page_type);
        return esc_html($default_label);
    }
    if (isset($template_map[$template]) && trim((string) $template_map[$template]) !== '') {
        return esc_html(trim((string) $template_map[$template]));
    }

    $post = get_post($post_id);
    $templates = wp_get_theme()->get_page_templates($post instanceof WP_Post ? $post : null, 'page');
    $matching_labels = [];
    foreach ($templates as $label => $file) {
        if ((string) $file !== $template) continue;
        $clean_label = trim((string) $label);
        if ($clean_label === '') continue;
        $matching_labels[] = $clean_label;
    }
    if (!empty($matching_labels)) {
        foreach ($matching_labels as $candidate) {
            $candidate_lc = strtolower($candidate);
            $is_file_like = str_ends_with($candidate_lc, '.php') || !str_contains($candidate, ' ');
            if (!$is_file_like) return esc_html($candidate);
        }
        return esc_html($matching_labels[0]);
    }

    $humanized = ucwords(trim(str_replace(['-', '_'], ' ', (string) pathinfo($template, PATHINFO_FILENAME))));
    if ($humanized !== '') return esc_html($humanized);

    return '&mdash;';
}

function meza_filter_template_state_labels(array $labels, int $post_id, string $template_display): array
{
    $template_meta = strtolower(trim((string) get_post_meta($post_id, '_wp_page_template', true)));
    $template_humanized = strtolower(trim(ucwords(str_replace(['-', '_'], ' ', (string) pathinfo($template_meta, PATHINFO_FILENAME)))));
    $template_display_normalized = strtolower(trim(wp_strip_all_tags($template_display)));

    return array_values(array_filter($labels, function ($label) use ($template_meta, $template_humanized, $template_display_normalized) {
        $normalized = strtolower(trim(wp_strip_all_tags((string) $label)));
        if ($normalized === '') return false;
        if ($template_display_normalized !== '' && $normalized === $template_display_normalized) return false;
        if ($template_meta !== '' && $normalized === $template_meta) return false;
        if ($template_humanized !== '' && $normalized === $template_humanized) return false;
        return true;
    }));
}

function meza_is_empty_template_display(string $value): bool
{
    $stripped = wp_strip_all_tags($value);
    $decoded = html_entity_decode($stripped, ENT_QUOTES, 'UTF-8');
    $normalized = strtolower(trim(str_replace("\xc2\xa0", ' ', $decoded)));

    if ($normalized === '') return true;
    if (in_array($normalized, ['-', '—', '–', '&mdash;', '&#8212;', '&ndash;', '&#8211;'], true)) return true;

    // Treat any dash-only placeholder sequence as empty (e.g. "-", "—", "--", "&mdash;").
    return (bool) preg_match('/^[\-\x{2012}\x{2013}\x{2014}\x{2015}\s]+$/u', $normalized);
}

function meza_page_state_store_set(int $post_id, array $labels): void
{
    $state_by_post = $GLOBALS['meza_page_state_by_post'] ?? [];
    if (!is_array($state_by_post)) $state_by_post = [];
    $state_by_post[$post_id] = $labels;
    $GLOBALS['meza_page_state_by_post'] = $state_by_post;
}

function meza_page_state_store_get(int $post_id): array
{
    $state_by_post = $GLOBALS['meza_page_state_by_post'] ?? [];
    return (is_array($state_by_post) && isset($state_by_post[$post_id]) && is_array($state_by_post[$post_id]))
        ? $state_by_post[$post_id]
        : [];
}

function meza_get_page_state_labels(int $post_id): array
{
    $labels = meza_page_state_store_get($post_id);
    if (!empty($labels)) return $labels;

    // Fallback for cases where title states were not rendered yet.
    if ((int) get_option('page_on_front') === $post_id) $labels[] = __('Front Page');
    if ((int) get_option('page_for_posts') === $post_id) $labels[] = __('Posts Page');
    if ((int) get_option('wp_page_for_privacy_policy') === $post_id) $labels[] = __('Privacy Policy Page');

    return array_values(array_unique(array_filter($labels, fn($v) => is_string($v) && trim($v) !== '')));
}

function meza_is_front_page(int $post_id): bool
{
    return ((int) get_option('page_on_front') === $post_id);
}

// Remove all post-state labels from the title column on Pages admin list.
add_filter('display_post_states', function ($states, $post) {
    if (!is_admin() || ($post->post_type ?? '') !== 'page') return $states;

    $labels = [];
    foreach ($states as $label) {
        $clean = trim(wp_strip_all_tags((string) $label));
        if ($clean !== '') $labels[] = $clean;
    }
    meza_page_state_store_set((int) $post->ID, array_values(array_unique($labels)));

    return [];
}, 9999, 2);

// Replace the Page Template column with an MZ-rendered one so we can include moved title states.
add_filter('manage_pages_columns', function ($columns) {
    $updated = [];
    $has_template_column = false;
    foreach ($columns as $key => $label) {
        $is_template_column = ($key === 'template') || (strtolower(trim((string) $label)) === 'page template');
        if ($is_template_column) {
            $updated['mz_page_template'] = 'Page Template';
            $has_template_column = true;
            continue;
        }
        $updated[$key] = $label;
    }
    if (!$has_template_column) {
        $updated['mz_page_template'] = 'Page Template';
    }
    return $updated;
}, 1000);

// Render only the selected page template label in the Page Template cell.
$meza_render_page_template_column = function ($column, $post_id) {
    if ($column !== 'mz_page_template') return;

    $template_display = meza_get_page_template_label((int) $post_id);
    echo meza_is_empty_template_display($template_display) ? '&mdash;' : $template_display;
};
add_action('manage_page_posts_custom_column', $meza_render_page_template_column, 100, 2);

// Core list table renderer: show an em dash for front page slug.
add_action('manage_page_posts_custom_column', function ($column, $post_id) {
    if ($column !== 'slug') return;
    if (!meza_is_front_page((int) $post_id)) return;

    echo '&mdash;';
}, 100, 2);

// Admin Columns plugin renderer: show only the selected page template label.
add_filter('ac/column/value', function ($value, $id, $column) {
    if (!is_object($column) || !method_exists($column, 'get_type') || !method_exists($column, 'get_post_type')) {
        return $value;
    }
    if ((string) $column->get_post_type() !== 'page') return $value;
    if ((string) $column->get_type() !== 'column-page_template') return $value;

    $template_display = meza_get_page_template_label((int) $id);
    return meza_is_empty_template_display($template_display) ? '&mdash;' : $template_display;
}, 100, 3);

// Admin Columns plugin renderer: show an em dash for front page slug.
add_filter('ac/column/value', function ($value, $id, $column) {
    if (!is_object($column) || !method_exists($column, 'get_type') || !method_exists($column, 'get_post_type')) {
        return $value;
    }
    if ((string) $column->get_post_type() !== 'page') return $value;
    if ((string) $column->get_type() !== 'column-slug') return $value;
    if (!meza_is_front_page((int) $id)) return $value;

    return '&mdash;';
}, 100, 3);

/** ================================
 *  DASHBOARD WIDGET DEFAULTS
 *  ================================ */

function meza_dashboard_collect_widgets(): array
{
    global $wp_meta_boxes;

    $collected = [];
    if (!isset($wp_meta_boxes['dashboard']) || !is_array($wp_meta_boxes['dashboard'])) return $collected;

    foreach ($wp_meta_boxes['dashboard'] as $context => $priorities) {
        if (!is_array($priorities)) continue;
        foreach ($priorities as $priority => $widgets) {
            if (!is_array($widgets)) continue;
            foreach ($widgets as $widget_id => $widget) {
                if (!is_array($widget)) continue;
                $collected[$widget_id] = [
                    'context' => (string) $context,
                    'priority' => (string) $priority,
                    'widget' => $widget,
                ];
            }
        }
    }

    return $collected;
}

function meza_dashboard_find_site_kit_widget_id(array $widgets): string
{
    $known_ids = [
        'googlesitekit_dashboard_widget',
        'googlesitekit_dashboard_key_metrics',
        'googlesitekit_dashboard_summary',
        'googlesitekit_dashboard_overview',
    ];
    foreach ($known_ids as $widget_id) {
        if (isset($widgets[$widget_id])) return $widget_id;
    }

    $best_match = '';
    foreach ($widgets as $widget_id => $data) {
        $title = strtolower(trim(wp_strip_all_tags((string) (($data['widget']['title'] ?? '')))));
        if ($title === '') continue;
        if (str_contains($title, 'site kit') && str_contains($title, 'summary')) return (string) $widget_id;
        if ($best_match === '' && str_contains($title, 'site kit')) $best_match = (string) $widget_id;
    }

    return $best_match;
}

function meza_dashboard_allowed_widget_ids(array $widgets): array
{
    $ids = [
        'column1' => [],
        'column2' => ['dashboard_right_now', 'wp_mail_smtp_reports_widget_lite', 'dashboard_site_health'],
        'column3' => ['ws_php_error_log'],
    ];

    $site_kit_widget_id = meza_dashboard_find_site_kit_widget_id($widgets);
    if ($site_kit_widget_id !== '') $ids['column1'][] = $site_kit_widget_id;

    // Keep only widgets that actually exist for this user.
    foreach ($ids as $column => $column_ids) {
        $ids[$column] = array_values(array_filter($column_ids, fn($id) => isset($widgets[$id])));
    }

    return $ids;
}

// Force a 4-column dashboard layout while keeping column 4 empty.
add_filter('screen_layout_columns', function ($columns) {
    if (!is_array($columns)) return $columns;
    $columns['dashboard'] = 4;
    return $columns;
});
add_filter('get_user_option_screen_layout_dashboard', fn() => 4, 100);

// Keep forced dashboard widgets responsive: 2 columns on medium screens, 1 on small.
add_action('admin_head-index.php', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || $screen->id !== 'dashboard') return;

    echo '<style id="meza-dashboard-responsive-columns">' .
        '#dashboard-widgets .postbox-container{' .
        'width:25%!important;' .
        'float:left!important;' .
        'margin-right:0!important;' .
        'clear:none!important;' .
        '}' .
        '@media screen and (max-width:1400px){' .
        '#dashboard-widgets .postbox-container{' .
        'width:50%!important;' .
        'float:left!important;' .
        'margin-right:0!important;' .
        '}' .
        '#dashboard-widgets #postbox-container-1,' .
        '#dashboard-widgets #postbox-container-3{' .
        'clear:left;' .
        '}' .
        '#dashboard-widgets #postbox-container-2,' .
        '#dashboard-widgets #postbox-container-4{' .
        'clear:none;' .
        '}' .
        '}' .
        '@media screen and (max-width:850px){' .
        '#dashboard-widgets .postbox-container{' .
        'width:100%!important;' .
        'float:none!important;' .
        'clear:both!important;' .
        '}' .
        '}' .
        '</style>';
}, 99999);

// Restrict dashboard widgets and place the allowed ones in requested columns.
add_action('wp_dashboard_setup', function () {
    global $wp_meta_boxes;
    if (!is_array($wp_meta_boxes) || !isset($wp_meta_boxes['dashboard'])) return;

    $widgets = meza_dashboard_collect_widgets();
    $allowed = meza_dashboard_allowed_widget_ids($widgets);
    $allowed_ids = array_values(array_unique(array_merge($allowed['column1'], $allowed['column2'], $allowed['column3'])));

    // Remove all widgets first.
    foreach (array_keys($widgets) as $widget_id) {
        remove_meta_box((string) $widget_id, 'dashboard', 'normal');
        remove_meta_box((string) $widget_id, 'dashboard', 'side');
        remove_meta_box((string) $widget_id, 'dashboard', 'column3');
        remove_meta_box((string) $widget_id, 'dashboard', 'column4');
    }

    // Rebuild dashboard with only the allowed widgets in deterministic order.
    $wp_meta_boxes['dashboard'] = [
        'normal' => ['core' => [], 'high' => [], 'default' => [], 'low' => []],
        'side' => ['core' => [], 'high' => [], 'default' => [], 'low' => []],
        'column3' => ['core' => [], 'high' => [], 'default' => [], 'low' => []],
        'column4' => ['core' => [], 'high' => [], 'default' => [], 'low' => []],
    ];

    foreach ($allowed['column1'] as $widget_id) {
        $wp_meta_boxes['dashboard']['normal']['core'][$widget_id] = $widgets[$widget_id]['widget'];
    }
    foreach ($allowed['column2'] as $widget_id) {
        $wp_meta_boxes['dashboard']['side']['core'][$widget_id] = $widgets[$widget_id]['widget'];
    }
    foreach ($allowed['column3'] as $widget_id) {
        $wp_meta_boxes['dashboard']['column3']['core'][$widget_id] = $widgets[$widget_id]['widget'];
    }

    // Keep Screen Options aligned with the enforced set.
    $hidden_ids = array_values(array_diff(array_keys($widgets), $allowed_ids));
    $GLOBALS['meza_dashboard_hidden_ids'] = $hidden_ids;
}, 1000);

add_filter('default_hidden_meta_boxes', function ($hidden, $screen) {
    if (!($screen instanceof WP_Screen) || $screen->id !== 'dashboard') return $hidden;
    $forced_hidden = $GLOBALS['meza_dashboard_hidden_ids'] ?? [];
    if (!is_array($forced_hidden)) $forced_hidden = [];
    return array_values(array_unique(array_merge((array) $hidden, $forced_hidden)));
}, 100, 2);

// Remove the Dashboard welcome panel for all users.
add_action('admin_init', function () {
    remove_action('welcome_panel', 'wp_welcome_panel');
});

// Hide selected admin menu items that we do not expose to editors/admins.
add_action('admin_menu', function () {
    remove_menu_page('edit-comments.php');

    remove_submenu_page('edit.php', 'edit-tags.php?taxonomy=post_tag');

    remove_submenu_page('themes.php', 'customize.php');
    remove_submenu_page('themes.php', 'theme-editor.php');
    remove_submenu_page('themes.php', 'site-editor.php?path=/patterns');
    remove_submenu_page('themes.php', 'edit.php?post_type=wp_block');

    remove_submenu_page('plugins.php', 'plugin-editor.php');
}, 999);

// Remove late-registered Appearance submenu items by matching the final submenu array.
add_action('admin_menu', function () {
    global $submenu;

    if (!isset($submenu['themes.php']) || !is_array($submenu['themes.php'])) return;

    $submenu['themes.php'] = array_values(array_filter($submenu['themes.php'], function ($item) {
        if (!is_array($item)) return true;

        $label = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));
        $slug = strtolower((string) ($item[2] ?? ''));

        $is_customize = ($slug === 'customize.php')
            || str_starts_with($slug, 'customize.php?')
            || $label === 'customize';
        $is_patterns = ($slug === 'edit.php?post_type=wp_block')
            || (str_starts_with($slug, 'site-editor.php') && str_contains($slug, 'patterns'))
            || $label === 'patterns';

        return !($is_customize || $is_patterns);
    }));
}, 99999);

function meza_admin_menu_content_group(string $menu_slug): string
{
    $menu_slug = trim($menu_slug);
    if ($menu_slug === '') return '';

    if ($menu_slug === 'upload.php' || $menu_slug === 'edit.php') {
        return 'with';
    }

    if (!str_starts_with($menu_slug, 'edit.php?post_type=')) return '';

    $post_type = (string) wp_unslash((string) parse_url($menu_slug, PHP_URL_QUERY));
    parse_str($post_type, $query_args);
    $post_type = (string) ($query_args['post_type'] ?? '');
    if ($post_type === '') return '';
    if (function_exists('meza_is_acf_admin_post_type') && meza_is_acf_admin_post_type($post_type)) return '';

    $post_type_object = get_post_type_object($post_type);
    if (!($post_type_object instanceof WP_Post_Type)) return '';
    if (empty($post_type_object->show_ui)) return '';

    return meza_post_type_has_permalink($post_type) ? 'with' : 'without';
}

// Normalize selected plugin/admin menu labels.
add_action('admin_menu', function () {
    global $menu, $submenu;

    foreach ($menu as $index => &$item) {
        if (!is_array($item)) continue;

        $slug = strtolower((string) ($item[2] ?? ''));
        $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));

        $is_wp_mail_smtp = str_contains($slug, 'wp-mail-smtp') || $title === 'wp mail smtp';
        $is_updraft = str_contains($slug, 'updraft')
            || in_array($title, ['updraft', 'updraftplus'], true);

        if ($is_wp_mail_smtp) {
            $item[0] = 'Mail';
            if (isset($item[3])) $item[3] = 'Mail';
            $item[6] = 'dashicons-email-alt2';
            continue;
        }

        if ($is_updraft) {
            $item[0] = 'Backups';
            if (isset($item[3])) $item[3] = 'Backups';
        }
    }
    unset($item);

    foreach ($submenu as $parent_slug => &$items) {
        if (!is_array($items)) continue;

        foreach ($items as &$item) {
            if (!is_array($item)) continue;

            $slug = strtolower((string) ($item[2] ?? ''));
            $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));

            $is_intuitive_cpo = str_contains($slug, 'intuitive-custom-post-order')
                || str_contains($slug, 'cporder')
                || $title === 'intuitive cpo';
            $is_post_duplicator = str_contains($slug, 'post-duplicator')
                || $title === 'post duplicator';

            if ($parent_slug === 'options-general.php' && $is_intuitive_cpo) {
                $item[0] = 'Post Ordering';
                if (isset($item[3])) $item[3] = 'Post Ordering';
                continue;
            }

            if ($parent_slug === 'options-general.php' && $is_post_duplicator) {
                $item[0] = 'Post Duplication';
                if (isset($item[3])) $item[3] = 'Post Duplication';
            }
        }
        unset($item);
    }
    unset($items);
}, 99999);

// Group top-level content menus after Dashboard: permalink-capable first, then non-viewable/admin-only.
add_action('admin_menu', function () {
    global $menu;

    if (!is_array($menu) || empty($menu)) return;

    $with_permalink = [];
    $without_permalink = [];

    foreach ($menu as $item) {
        if (!is_array($item)) continue;

        $slug = (string) ($item[2] ?? '');
        $group = meza_admin_menu_content_group($slug);
        if ($group === '') continue;

        $entry = [
            'item' => $item,
            'label' => strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? '')))),
        ];

        if ($group === 'with') {
            $with_permalink[] = $entry;
        } else {
            $without_permalink[] = $entry;
        }
    }

    if (empty($with_permalink) && empty($without_permalink)) return;

    $sort_entries = static function (array &$entries): void {
        usort($entries, static function (array $a, array $b): int {
            return strnatcasecmp($a['label'], $b['label']);
        });
    };

    $sort_entries($with_permalink);
    $sort_entries($without_permalink);

    $grouped_items = array_map(
        static fn(array $entry): array => $entry['item'],
        $with_permalink
    );

    if (!empty($with_permalink) && !empty($without_permalink)) {
        $grouped_items[] = [
            '',
            'read',
            'separator-meza-content-groups',
            '',
            'wp-menu-separator',
        ];
    }

    foreach ($without_permalink as $entry) {
        $grouped_items[] = $entry['item'];
    }

    $rebuilt = [];
    $inserted = false;

    foreach ($menu as $item) {
        if (!is_array($item)) {
            $rebuilt[] = $item;
            continue;
        }

        $slug = (string) ($item[2] ?? '');
        if (meza_admin_menu_content_group($slug) !== '') {
            continue;
        }

        $rebuilt[] = $item;

        if (!$inserted && $slug === 'index.php') {
            $rebuilt[] = [
                '',
                'read',
                'separator-meza-dashboard-content',
                '',
                'wp-menu-separator',
            ];
            foreach ($grouped_items as $grouped_item) {
                $rebuilt[] = $grouped_item;
            }
            $inserted = true;
        }
    }

    if ($inserted) {
        $menu = $rebuilt;
    }
}, 100000);

// Keep Backups in the dashboard group, directly after Dashboard and before the content separator.
add_action('admin_menu', function () {
    global $menu;

    if (!is_array($menu) || empty($menu)) return;

    $dashboard_index = null;
    $backups_index = null;

    foreach ($menu as $index => $item) {
        if (!is_array($item)) continue;

        $slug = strtolower((string) ($item[2] ?? ''));
        $title = strtolower(trim(wp_strip_all_tags((string) ($item[0] ?? ''))));

        if ($slug === 'index.php' && $dashboard_index === null) {
            $dashboard_index = (int) $index;
            continue;
        }

        $is_updraft = str_contains($slug, 'updraft')
            || in_array($title, ['backups', 'updraft', 'updraftplus'], true);

        if ($is_updraft && $backups_index === null) {
            $backups_index = (int) $index;
        }
    }

    if ($dashboard_index === null || $backups_index === null || $dashboard_index === $backups_index) return;

    $backups_item = $menu[$backups_index] ?? null;
    if (!is_array($backups_item)) return;

    array_splice($menu, $backups_index, 1);
    if ($backups_index < $dashboard_index) {
        $dashboard_index--;
    }

    array_splice($menu, $dashboard_index + 1, 0, [$backups_item]);
}, 100001);

// Move Site Health from Tools to Dashboard, directly under Updates.
add_action('admin_menu', function () {
    global $submenu;

    if (!isset($submenu['tools.php']) || !is_array($submenu['tools.php'])) return;

    $site_health_item = null;

    $submenu['tools.php'] = array_values(array_filter($submenu['tools.php'], function ($item) use (&$site_health_item) {
        if (!is_array($item)) return true;

        $slug = strtolower((string) ($item[2] ?? ''));
        if (!str_contains($slug, 'site-health.php')) return true;

        if ($site_health_item === null) {
            $site_health_item = $item;
        }

        return false;
    }));

    if (!is_array($site_health_item)) return;

    if (!isset($submenu['index.php']) || !is_array($submenu['index.php'])) {
        $submenu['index.php'] = [];
    }

    foreach ($submenu['index.php'] as $existing_item) {
        if (!is_array($existing_item)) continue;
        $existing_slug = strtolower((string) ($existing_item[2] ?? ''));
        if (str_contains($existing_slug, 'site-health.php')) return;
    }

    $insert_at = count($submenu['index.php']);
    foreach ($submenu['index.php'] as $index => $existing_item) {
        if (!is_array($existing_item)) continue;
        $existing_slug = strtolower((string) ($existing_item[2] ?? ''));
        if ($existing_slug === 'update-core.php') {
            $insert_at = $index + 1;
            break;
        }
    }

    array_splice($submenu['index.php'], $insert_at, 0, [$site_health_item]);
}, 100000);

// Streamline Tools submenu items.
add_action('admin_menu', function () {
    global $submenu;

    if (!isset($submenu['tools.php']) || !is_array($submenu['tools.php'])) return;

    foreach ($submenu['tools.php'] as $index => &$item) {
        if (!is_array($item)) continue;

        $slug = strtolower((string) ($item[2] ?? ''));

        if ($slug === 'tools.php') {
            unset($submenu['tools.php'][$index]);
            continue;
        }

        if ($slug === 'import.php') {
            $item[2] = 'admin.php?import=wordpress';
        }
    }
    unset($item);

    $submenu['tools.php'] = array_values($submenu['tools.php']);
}, 100001);

// Shorten post-type "Add New" submenu labels to "Add".
add_action('admin_menu', function () {
    global $submenu;

    foreach ($submenu as $parent_slug => &$items) {
        if (!is_array($items)) continue;

        $is_post_type_parent = ($parent_slug === 'edit.php')
            || str_starts_with((string) $parent_slug, 'edit.php?post_type=');
        if (!$is_post_type_parent) continue;

        foreach ($items as &$item) {
            if (!is_array($item)) continue;

            $label = trim(wp_strip_all_tags((string) ($item[0] ?? '')));
            $slug = strtolower((string) ($item[2] ?? ''));
            $is_add_screen = ($slug === 'post-new.php')
                || str_starts_with($slug, 'post-new.php?');

            if ($is_add_screen && preg_match('/^add\s+new\b/i', $label)) {
                $new_label = preg_replace('/^add\s+new\b\s*/i', 'Add ', $label) ?? $label;
                $new_label = trim(preg_replace('/\s+/', ' ', $new_label) ?? $new_label);
                if ($new_label === '') $new_label = 'Add';

                $item[0] = $new_label;
                if (isset($item[3])) $item[3] = $new_label;
            }
        }
        unset($item);
    }
    unset($items);
}, 100002);

// Simplify post-type taxonomy submenu labels by removing the parent post type name.
add_action('admin_menu', function () {
    global $menu, $submenu;

    foreach ($submenu as $parent_slug => &$items) {
        if (!is_array($items)) continue;

        $is_post_type_parent = ($parent_slug === 'edit.php')
            || str_starts_with((string) $parent_slug, 'edit.php?post_type=');
        if (!$is_post_type_parent) continue;

        $post_type = 'post';
        if ($parent_slug !== 'edit.php') {
            parse_str((string) parse_url((string) $parent_slug, PHP_URL_QUERY), $query_args);
            $post_type = (string) ($query_args['post_type'] ?? '');
            if ($post_type === '') continue;
        }

        $post_type_object = get_post_type_object($post_type);
        $strip_candidates = [];

        foreach ($menu as $menu_item) {
            if (!is_array($menu_item)) continue;
            if (((string) ($menu_item[2] ?? '')) !== (string) $parent_slug) continue;

            $menu_label = trim(wp_strip_all_tags((string) ($menu_item[0] ?? '')));
            if ($menu_label !== '') $strip_candidates[] = $menu_label;
            break;
        }

        if ($post_type_object instanceof WP_Post_Type) {
            $plural = trim((string) ($post_type_object->labels->name ?? ''));
            $singular = trim((string) ($post_type_object->labels->singular_name ?? ''));
            if ($plural !== '') $strip_candidates[] = $plural;
            if ($singular !== '') $strip_candidates[] = $singular;
        }

        $strip_candidates = array_values(array_unique(array_filter(array_map('trim', $strip_candidates))));
        if (empty($strip_candidates)) continue;

        foreach ($items as &$item) {
            if (!is_array($item)) continue;

            $label = trim(wp_strip_all_tags((string) ($item[0] ?? '')));
            $slug = strtolower((string) ($item[2] ?? ''));
            if (!str_starts_with($slug, 'edit-tags.php?taxonomy=')) continue;
            if ($label === '') continue;

            $new_label = $label;

            foreach ($strip_candidates as $candidate) {
                if ($candidate === '') continue;

                $quoted = preg_quote($candidate, '/');
                $updated_label = preg_replace('/^' . $quoted . '\s+/i', '', $new_label);
                if ($updated_label !== null && $updated_label !== $new_label) {
                    $new_label = trim(preg_replace('/\s+/', ' ', $updated_label) ?? $updated_label);
                    break;
                }

                $updated_label = preg_replace('/\b' . $quoted . '\b\s*/i', '', $new_label, 1);
                if ($updated_label !== null && $updated_label !== $new_label) {
                    $new_label = trim(preg_replace('/\s+/', ' ', $updated_label) ?? $updated_label);
                    break;
                }
            }

            if ($new_label !== '' && $new_label !== $label) {
                $item[0] = $new_label;
                if (isset($item[3])) $item[3] = $new_label;
            }
        }
        unset($item);
    }
    unset($items);
}, 100003);

/** ================================
 *  ADMIN LIST ACTIONS
 *  ================================ */

function meza_remove_quick_edit_action(array $actions): array
{
    if (isset($actions['inline hide-if-no-js'])) unset($actions['inline hide-if-no-js']);
    if (isset($actions['inline'])) unset($actions['inline']);
    if (isset($actions['edit']) && is_string($actions['edit'])) {
        $actions['edit'] = preg_replace(
            '/<a\s/i',
            '<a target="_blank" rel="noopener noreferrer" ',
            $actions['edit'],
            1
        ) ?? $actions['edit'];
    }
    return $actions;
}

add_filter('post_row_actions', 'meza_remove_quick_edit_action', 1000);
add_filter('page_row_actions', 'meza_remove_quick_edit_action', 1000);

/** ================================
 *  ACF ADMIN COLUMN NORMALIZATION
 *  ================================ */

function meza_is_acf_admin_post_type(string $post_type): bool
{
    return str_starts_with($post_type, 'acf-');
}

function meza_strip_acf_key_description_columns(array $columns): array
{
    if (!is_array($columns)) return $columns;

    foreach ($columns as $key => $label) {
        $key_normalized = strtolower(trim((string) $key));
        $label_normalized = strtolower(trim(wp_strip_all_tags((string) $label)));
        if (in_array($key_normalized, ['key', 'description', 'acf_key', 'acf_description'], true)) {
            unset($columns[$key]);
            continue;
        }
        if (in_array($label_normalized, ['key', 'description'], true)) {
            unset($columns[$key]);
        }
    }

    $ordered = [];
    $used = [];

    $append = static function (string $key) use (&$ordered, &$columns, &$used): void {
        if (isset($used[$key])) return;
        if (!array_key_exists($key, $columns)) return;
        $ordered[$key] = $columns[$key];
        $used[$key] = true;
    };

    $normalize = static function (string $value): string {
        $value = strtolower(trim(wp_strip_all_tags($value)));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;
        return trim($value, '_');
    };

    $find_column_key = static function (array $aliases) use ($columns, $normalize): string {
        $aliases = array_values(array_unique(array_map($normalize, $aliases)));
        foreach ($columns as $key => $label) {
            $key_normalized = $normalize((string) $key);
            $label_normalized = $normalize((string) $label);
            if (in_array($key_normalized, $aliases, true) || in_array($label_normalized, $aliases, true)) {
                return (string) $key;
            }
        }
        return '';
    };

    $append('cb');

    $id_key = $find_column_key(['id']);
    if ($id_key !== '') $append($id_key);

    $order = [
        ['title'],
        ['location'],
        ['post_types', 'post_type', 'post types', 'post type'],
        ['taxonomies', 'taxonomy'],
        ['field_groups', 'field group', 'field groups'],
        ['posts', 'post'],
        ['terms', 'term'],
        ['fields', 'field'],
    ];

    foreach ($order as $aliases) {
        $resolved_key = $find_column_key($aliases);
        if ($resolved_key !== '') $append($resolved_key);
    }

    foreach (array_keys($columns) as $key) {
        $append((string) $key);
    }

    return $ordered;
}

add_action('current_screen', function ($screen) {
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    $post_type = (string) ($screen->post_type ?? '');
    if (!meza_is_acf_admin_post_type($post_type)) return;

    add_filter("manage_{$post_type}_posts_columns", 'meza_strip_acf_key_description_columns', 9999);
});

// Set consistent admin list column widths.
add_action('admin_head-edit.php', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    $is_acf_screen = meza_is_acf_admin_post_type((string) ($screen->post_type ?? ''));

    echo '<style id="meza-admin-list-column-widths">' .
        '.wp-list-table .column-mz_id{width:100px;}' .
        '.wp-list-table .column-mz_thumbnail{width:125px;}' .
        '.wp-list-table .column-mz_thumbnail .row-actions{font-size:11px;line-height:1.1;}' .
        '.wp-list-table .column-title{width:225px;}' .
        '.wp-list-table .column-mz_modified,.wp-list-table .column-mz_published{width:225px;}' .
        '.wp-list-table th.column-categories,.wp-list-table td.column-categories{width:225px;max-width:225px;}' .
        '.wp-list-table th[class*="column-taxonomy-"],.wp-list-table td[class*="column-taxonomy-"]{width:225px;}' .
        '.wp-list-table th.column-mz_page_template,.wp-list-table td.column-mz_page_template{width:125px;max-width:125px;}' .
        '.wp-list-table th.column-mz_page_headline,.wp-list-table td.column-mz_page_headline{width:225px;max-width:225px;}' .
        '.wp-list-table th.column-mz_page_cta,.wp-list-table td.column-mz_page_cta{width:175px;max-width:175px;}' .
        '</style>';

    if (!$is_acf_screen) return;

    // ACF list tables often use generic id/key/description column slugs.
    echo '<style id="meza-acf-admin-column-normalization">' .
        'table.wp-list-table.fixed{table-layout:fixed!important;}' .
        '.wp-list-table th.column-id,.wp-list-table td.column-id,.wp-list-table th.column-ID,.wp-list-table td.column-ID{width:100px!important;min-width:100px!important;max-width:100px!important;}' .
        '.wp-list-table th.column-key,.wp-list-table td.column-key,.wp-list-table th.column-description,.wp-list-table td.column-description{display:none!important;}' .
        'table.wp-list-table.fixed col.column-posts,.wp-list-table th.column-posts,.wp-list-table td.column-posts{width:125px!important;min-width:125px!important;max-width:125px!important;}' .
        'table.wp-list-table.fixed col.column-terms,.wp-list-table th.column-terms,.wp-list-table td.column-terms{width:125px!important;min-width:125px!important;max-width:125px!important;}' .
        'table.wp-list-table.fixed col.column-fields,.wp-list-table th.column-fields,.wp-list-table td.column-fields{width:125px!important;min-width:125px!important;max-width:125px!important;}' .
        '</style>';
});

// Open linked post titles in a new tab on admin list tables.
add_action('admin_print_footer_scripts-edit.php', function () {
    echo '<script id="meza-admin-title-link-target">' .
        'document.querySelectorAll(".wp-list-table .row-title").forEach(function(link){' .
        'link.setAttribute("target","_blank");' .
        'link.setAttribute("rel","noopener noreferrer");' .
        '});' .
        'document.querySelectorAll(".wp-list-table .mz-copy-link").forEach(function(link){' .
        'link.addEventListener("click", function(e){' .
        'e.preventDefault();' .
        'var text = this.getAttribute("data-copy-text") || "";' .
        'if (!text) return;' .
        'var original = this.textContent;' .
        'var done = function(){var el=link;el.textContent="Copied";setTimeout(function(){el.textContent=original;},1200);};' .
        'if (navigator.clipboard && navigator.clipboard.writeText) {' .
        'navigator.clipboard.writeText(text).then(done).catch(function(){' .
        'var ta=document.createElement("textarea");ta.value=text;document.body.appendChild(ta);ta.select();' .
        'try{document.execCommand("copy");done();}catch(_e){}document.body.removeChild(ta);' .
        '});' .
        '} else {' .
        'var ta=document.createElement("textarea");ta.value=text;document.body.appendChild(ta);ta.select();' .
        'try{document.execCommand("copy");done();}catch(_e){}document.body.removeChild(ta);' .
        '}' .
        '});' .
        '});' .
        '</script>';
});

/** ================================
 *  ADMIN BAR CLEANUP
 *  ================================ */

function meza_remove_admin_bar_nodes($wp_admin_bar): void
{
    if (!($wp_admin_bar instanceof WP_Admin_Bar)) return;

    // Known core/plugin IDs.
    $wp_admin_bar->remove_node('comments');
    $wp_admin_bar->remove_node('wpseo-menu');
    $wp_admin_bar->remove_node('updraft_admin_node');
    $wp_admin_bar->remove_node('updraftplus_admin_node');

    // Fallback for plugin/version-specific IDs.
    $nodes = $wp_admin_bar->get_nodes();
    if (!is_array($nodes)) return;

    foreach ($nodes as $node) {
        if (!is_object($node) || !isset($node->id)) continue;

        $id = strtolower((string) $node->id);
        $title = strtolower(wp_strip_all_tags((string) ($node->title ?? '')));
        $href = strtolower((string) ($node->href ?? ''));
        $meta_class = strtolower((string) (($node->meta['class'] ?? '')));

        $is_yoast = str_contains($id, 'wpseo')
            || str_contains($title, 'yoast')
            || str_contains($href, 'wpseo')
            || str_contains($meta_class, 'wpseo');
        $is_updraft = str_contains($id, 'updraft')
            || str_contains($title, 'updraft')
            || str_contains($href, 'updraft')
            || str_contains($meta_class, 'updraft');

        if ($is_yoast || $is_updraft) $wp_admin_bar->remove_node((string) $node->id);
    }
}

add_action('admin_bar_menu', function ($wp_admin_bar) {
    meza_remove_admin_bar_nodes($wp_admin_bar);
}, 99999);

add_action('wp_before_admin_bar_render', function () {
    global $wp_admin_bar;
    meza_remove_admin_bar_nodes($wp_admin_bar);
}, 99999);

// Keep the "New" admin-bar dropdown in alphabetical order.
add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!($wp_admin_bar instanceof WP_Admin_Bar)) return;

    $nodes = $wp_admin_bar->get_nodes();
    if (!is_array($nodes)) return;

    $children = [];
    foreach ($nodes as $node) {
        if (!is_object($node) || (($node->parent ?? '') !== 'new-content')) continue;
        $children[] = $node;
    }

    if (count($children) < 2) return;

    usort($children, static function ($a, $b) {
        $title_a = strtolower(trim(wp_strip_all_tags((string) ($a->title ?? ''))));
        $title_b = strtolower(trim(wp_strip_all_tags((string) ($b->title ?? ''))));
        return strnatcmp($title_a, $title_b);
    });

    foreach ($children as $child) {
        $wp_admin_bar->remove_node((string) $child->id);
    }

    foreach ($children as $child) {
        $wp_admin_bar->add_node([
            'id' => (string) $child->id,
            'parent' => 'new-content',
            'title' => $child->title ?? '',
            'href' => $child->href ?? false,
            'group' => !empty($child->group),
            'meta' => is_array($child->meta ?? null) ? $child->meta : [],
        ]);
    }
}, 100000);

function meza_get_custom_logo_url(): string
{
    $custom_logo_id = (int) get_theme_mod('custom_logo');
    if ($custom_logo_id <= 0) return '';

    $custom_logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
    return is_string($custom_logo_url) ? $custom_logo_url : '';
}

// Use theme custom logo on wp-login.php.
add_action('login_enqueue_scripts', function () {
    $custom_logo_url = meza_get_custom_logo_url();
    if ($custom_logo_url === '') return;

    $logo_url = esc_url($custom_logo_url);
    echo '<style id="meza-login-logo">' .
        '.login h1 a,.login .wp-login-logo a{' .
        'background-image:url("' . $logo_url . '")!important;' .
        'background-size:contain!important;' .
        'background-position:center!important;' .
        'background-repeat:no-repeat!important;' .
        'width:320px!important;' .
        'height:100px!important;' .
        '}' .
        '</style>';
}, 99999);

// Last-resort visual fallback in case a plugin prints toolbar markup late.
add_action('admin_head', function () {
    echo '<style id="meza-admin-bar-hide-updraft">' .
        '#wpadminbar li[id*="updraft"],' .
        '#wpadminbar a[href*="updraft"],' .
        '#wpadminbar .updraft_admin_node,' .
        '#wpadminbar .updraftplus_admin_node{' .
        'display:none!important;' .
        '}' .
        '</style>';
}, 99999);

// Hide plugin/theme update badges in the left admin menu, but keep Updates and Site Health notices.
add_action('admin_head', function () {
    echo '<style id="meza-admin-menu-hide-selected-counters">' .
        '#adminmenu #menu-plugins .update-plugins,' .
        '#adminmenu #menu-appearance .wp-submenu a[href="themes.php"] .update-plugins{' .
        'display:none!important;' .
        '}' .
        '</style>';
}, 99999);

/** ================================
 *  THEME-AGNOSTIC EDITORIAL BEHAVIOR
 *  ================================ */

/** Media policy: allow SVG uploads, but only for administrators who can manage site-wide settings. */
function meza_allow_svg_uploads(array $mimes): array
{
    if (current_user_can('manage_options')) {
        $mimes['svg'] = 'image/svg+xml';
    }

    return $mimes;
}
add_filter('upload_mimes', 'meza_allow_svg_uploads');

/** ACF setup: pull the Google API key from the shared theme filter so Maps fields work in the editor. */
function meza_extend_acf_init(): void
{
    $gcloud_key = (string) apply_filters('theme_gcloud_key', '');
    if ($gcloud_key === '') return;

    acf_update_setting('google_api_key', $gcloud_key);
}
add_action('acf/init', 'meza_extend_acf_init');

/** ACF setup: center new map fields on a sensible default location so editors start from the right region. */
add_filter('acf/fields/google_map/api', function ($api) {
    $api['center_lat'] = 38.3032;
    $api['center_lng'] = -77.4605;
    $api['zoom'] = 14;

    return $api;
});

/** Media hygiene: prevent WordPress from auto-filling image titles from filenames on first upload. */
add_filter('wp_insert_attachment_data', function ($data, $postarr) {
    if (
        empty($postarr['ID'])
        && isset($postarr['post_mime_type'])
        && wp_match_mime_types('image', $postarr['post_mime_type'])
    ) {
        $data['post_title'] = '';
    }

    return $data;
}, 10, 2);

/** Dashboard: replace the default "At a Glance" list with counts for the post types editors actually manage. */
function meza_replace_glance_items()
{
    $custom_items = [];

    $post_types = get_post_types(['public' => true], 'objects');

    foreach ($post_types as $post_type) {
        if (!current_user_can($post_type->cap->edit_posts)) {
            continue;
        }

        $count_obj = wp_count_posts($post_type->name);
        $published = (int) $count_obj->publish;

        if ($published === 0) {
            continue;
        }

        $label = $published === 1 ? $post_type->labels->singular_name : $post_type->labels->name;
        $url = admin_url('edit.php?post_type=' . $post_type->name);
        $icon_class = 'dashicons-admin-post';

        if (!empty($post_type->menu_icon) && str_contains($post_type->menu_icon, 'dashicons-')) {
            $icon_class = $post_type->menu_icon;
        }

        $custom_items[] = [
            'name' => 'meza-' . $post_type->name,
            'count' => $published,
            'label' => $label,
            'url' => $url,
            'icon' => $icon_class,
        ];
    }

    if (current_user_can('edit_posts')) {
        $num_comments = wp_count_comments();
        $approved = (int) $num_comments->approved;

        if ($approved > 0) {
            $custom_items[] = [
                'name' => 'meza-comments',
                'count' => $approved,
                'label' => _n('Comment', 'Comments', $approved),
                'url' => admin_url('edit-comments.php'),
                'icon' => 'dashicons-admin-comments',
            ];
        }
    }

    usort($custom_items, fn($a, $b) => $b['count'] <=> $a['count']);

    foreach ($custom_items as $item) {
        $label = sprintf('%s %s', number_format_i18n($item['count']), $item['label']);
        printf(
            '<li class="%s"><a href="%s"><span class="dashicons %s"></span> %s</a></li>',
            esc_attr($item['name']),
            esc_url($item['url']),
            esc_attr($item['icon']),
            esc_html($label)
        );
    }
}
add_action('dashboard_glance_items', 'meza_replace_glance_items');

/** Dashboard: hide the stock core counters so they do not duplicate the custom editorial counts above. */
function meza_hide_default_glance_items_css(): void
{
    echo '<style>
        #dashboard_right_now .post-count,
        #dashboard_right_now .page-count,
        #dashboard_right_now .comment-count {
            display: none !important;
        }
        #dashboard_right_now .search-engines-info:before, #dashboard_right_now li a:before, #dashboard_right_now li>span:before {
            content: none;
        }
    </style>';
}
add_action('admin_head', 'meza_hide_default_glance_items_css');

/** ================================
 *  WYSIWYG NORMALIZATION
 *  ================================ */

/**
 * Normalize editor HTML by removing inline styling noise and converting simple presentational tags to semantic ones.
 * This keeps stored content cleaner and makes front-end output more predictable.
 */
function meza_normalize_wysiwyg_markup(string $html): string
{
    $html = preg_replace('/<(li|span)([^>]*) style="[^"]*"([^>]*)>/i', '<$1$2$3>', $html);
    $html = preg_replace('/<span[^>]*>\s*<\/span>/i', '', $html);
    $html = preg_replace('/<span[^>]*>\s*(<[^>]+>)\s*<\/span>/i', '$1', $html);
    $html = str_replace(['<b>', '</b>', '<i>', '</i>'], ['<strong>', '</strong>', '<em>', '</em>'], $html);

    return $html;
}

/** TinyMCE defaults: reduce extra wrappers and formatting noise before editors even save the field. */
function meza_acf_tinymce_custom_settings($init)
{
    $init['forced_root_block'] = false;
    $init['force_p_newlines'] = true;
    $init['removeformat'] = 'span';
    $init['valid_elements'] = '*[*]';
    return $init;
}
add_filter('tiny_mce_before_init', 'meza_acf_tinymce_custom_settings');

/** Save-time cleanup for ACF WYSIWYG fields so the database stores the normalized version of the content. */
function meza_acf_strip_inline_styles($value, $post_id, $field)
{
    return meza_normalize_wysiwyg_markup((string) $value);
}
add_filter('acf/update_value/type=wysiwyg', 'meza_acf_strip_inline_styles', 10, 3);

/** Extra save-time cleanup for span wrappers that can survive the broader normalization pass. */
function meza_acf_strip_spans_around_elements($value, $post_id, $field)
{
    return preg_replace('/<span[^>]*>\s*(<[^>]+>)\s*<\/span>/i', '$1', $value);
}
add_filter('acf/update_value/type=wysiwyg', 'meza_acf_strip_spans_around_elements', 10, 3);

/** Render-time cleanup so existing legacy content is cleaned up even if it was saved before these rules existed. */
function meza_acf_clean_wysiwyg_output($content)
{
    return meza_normalize_wysiwyg_markup((string) $content);
}
add_filter('acf_the_content', 'meza_acf_clean_wysiwyg_output');
add_filter('the_content', 'meza_acf_clean_wysiwyg_output');
