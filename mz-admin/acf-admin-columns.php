<?php

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
        if (in_array($key_normalized, ['id', 'key', 'description', 'acf_id', 'acf_key', 'acf_description'], true)) {
            unset($columns[$key]);
            continue;
        }
        if (in_array($label_normalized, ['id', 'key', 'description'], true)) {
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

function meza_render_admin_list_table_scroll_styles(): void
{
    echo '<style id="meza-admin-list-table-scroll">' .
        '.meza-admin-table-scroll{display:block;width:100%;max-width:100%;max-height:calc(100vh - 260px);overflow:auto;-webkit-overflow-scrolling:touch;border:1px solid #c3c4c7;box-sizing:border-box;background:#fff;}' .
        '.meza-admin-table-scroll table.wp-list-table{min-width:max-content;border-collapse:separate;border-spacing:0;border:none!important;box-shadow:none!important;table-layout:auto!important;}' .
        '.meza-admin-table-scroll table.wp-list-table thead,.meza-admin-table-scroll table.wp-list-table tfoot{position:relative;z-index:4;}' .
        '.meza-admin-table-scroll table.wp-list-table thead th,.meza-admin-table-scroll table.wp-list-table thead td{position:sticky;top:0;z-index:5;background:#fff;border-top:none!important;border-bottom:none!important;box-shadow:inset 0 -1px 0 #ccd0d4;background-clip:padding-box;box-sizing:border-box;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}' .
        '.meza-admin-table-scroll table.wp-list-table thead th a,.meza-admin-table-scroll table.wp-list-table thead td a,.meza-admin-table-scroll table.wp-list-table tfoot th a,.meza-admin-table-scroll table.wp-list-table tfoot td a{display:inline-flex;align-items:center;gap:4px;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:middle;}' .
        '.meza-admin-table-scroll table.wp-list-table thead th .sorting-indicators,.meza-admin-table-scroll table.wp-list-table thead td .sorting-indicators,.meza-admin-table-scroll table.wp-list-table tfoot th .sorting-indicators,.meza-admin-table-scroll table.wp-list-table tfoot td .sorting-indicators{flex:0 0 auto;}' .
        '.meza-admin-table-scroll table.wp-list-table tfoot th,.meza-admin-table-scroll table.wp-list-table tfoot td{position:sticky;bottom:0;z-index:5;background:#fff;border-top:none!important;border-bottom:none!important;box-shadow:inset 0 1px 0 #ccd0d4;background-clip:padding-box;box-sizing:border-box;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}' .
        '.meza-admin-table-scroll table.wp-list-table tbody td{position:relative;z-index:1;background-clip:padding-box;}' .
        '</style>';
}

function meza_render_admin_list_table_scroll_wrap_script(): void
{
    echo '<script id="meza-admin-table-scroll-wrap">' .
        '(function(){' .
        'var table=document.querySelector("#posts-filter table.wp-list-table, .wrap table.wp-list-table");' .
        'if(!table)return;' .
        'if(table.parentElement&&table.parentElement.classList.contains("meza-admin-table-scroll"))return;' .
        'var wrapper=document.createElement("div");' .
        'wrapper.className="meza-admin-table-scroll";' .
        'table.parentNode.insertBefore(wrapper,table);' .
        'wrapper.appendChild(table);' .
        '})();' .
        '</script>';
}

// Set consistent admin list column widths.
add_action('admin_head-edit.php', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    $post_type = (string) ($screen->post_type ?? '');
    $is_acp_layout = meza_post_type_uses_admin_columns_layout($post_type);
    $is_acf_screen = meza_is_acf_admin_post_type((string) ($screen->post_type ?? ''));
    $current_columns = function_exists('get_column_headers') ? get_column_headers($screen) : [];
    $taxonomy_width_selectors = meza_get_taxonomy_admin_column_width_selectors($post_type);
    $compact_date_width_selectors = meza_get_compact_date_admin_column_width_selectors($post_type);
    $taxonomy_column_labels = array_values(array_unique(array_filter(array_map(
        static function ($label): string {
            return strtolower(trim(wp_strip_all_tags((string) $label)));
        },
        array_values(meza_get_taxonomy_admin_column_sort_labels($post_type))
    ))));
    $runtime_taxonomy_width_selectors = meza_get_current_admin_column_width_selectors(
        is_array($current_columns) ? $current_columns : [],
        $taxonomy_column_labels
    );
    $runtime_modified_published_width_selectors = meza_get_current_admin_column_width_selectors(
        is_array($current_columns) ? $current_columns : [],
        ['modified', 'published', 'date']
    );
    $runtime_link_column_keys = is_array($current_columns) ? meza_get_link_admin_column_keys($current_columns) : [];
    $runtime_link_cell_selectors = [];

    foreach ($runtime_link_column_keys as $column_key) {
        $column_key = trim((string) $column_key);
        if ($column_key === '') {
            continue;
        }

        $runtime_link_cell_selectors[] = '.wp-list-table td.column-' . $column_key;
    }

    $runtime_link_cell_selectors = array_values(array_unique($runtime_link_cell_selectors));
    $runtime_link_anchor_selectors = array_values(array_unique(array_map(
        static fn(string $selector): string => $selector . ' a',
        $runtime_link_cell_selectors
    )));
    $column_width_css = $is_acp_layout
        ? '.wp-list-table .column-mz_id{width:65px;}' .
            '.wp-list-table .column-mz_menu_order{width:65px;}' .
            '.wp-list-table .column-mz_cta_link{width:200px;}' .
            '.wp-list-table .column-mz_cta_secondary_link{width:200px;}' .
            '.wp-list-table .column-website{width:200px;}' .
            '.wp-list-table .column-mz_faq_count{width:80px;}' .
            '.wp-list-table .column-mz_form_recipients{width:200px;}' .
            '.wp-list-table .column-mz_slug{width:175px;}' .
            '.wp-list-table .column-mz_organization_url{width:200px;}' .
            '.wp-list-table .column-mz_profile_link{width:200px;}' .
            '.wp-list-table .column-mz_profile_title{width:175px;}' .
            '.wp-list-table .column-mz_summary{width:325px;}' .
            '.wp-list-table .column-mz_review_quote{width:325px;}' .
            '.wp-list-table .column-mz_review_citer{width:175px;}' .
            '.wp-list-table .column-mz_review_link{width:200px;}' .
            '.wp-list-table .column-acf-taxonomies,.wp-list-table .column-acf-post-types,.wp-list-table .column-acf-field-groups{width:225px;}' .
            '.wp-list-table .column-acf-count{width:125px;}' .
            '.wp-list-table .column-mz_thumbnail{width:125px;min-width:125px;max-width:125px;}' .
            '.wp-list-table .column-title{width:225px;}' .
            '.wp-list-table .column-mz_modified,.wp-list-table .column-modified,.wp-list-table .column-mz_published,.wp-list-table .column-date{width:225px;}' .
            '.wp-list-table th.column-categories,.wp-list-table td.column-categories{width:225px;}' .
            '.wp-list-table th[class*="column-taxonomy-"],.wp-list-table td[class*="column-taxonomy-"]{width:225px;}' .
            '.wp-list-table th.meza-admin-taxonomy-column,.wp-list-table td.meza-admin-taxonomy-column{width:225px;}' .
            '.wp-list-table th.meza-admin-compact-date-column,.wp-list-table td.meza-admin-compact-date-column{width:200px;}' .
            ($taxonomy_width_selectors !== []
                ? implode(',', $taxonomy_width_selectors) . '{width:225px;}'
                : '') .
            ($runtime_taxonomy_width_selectors !== []
                ? implode(',', $runtime_taxonomy_width_selectors) . '{width:225px;}'
                : '') .
            ($runtime_modified_published_width_selectors !== []
                ? implode(',', $runtime_modified_published_width_selectors) . '{width:225px;}'
                : '') .
            ($compact_date_width_selectors !== []
                ? implode(',', array_filter($compact_date_width_selectors, static fn($selector): bool => str_contains($selector, 'th'))) . '{width:200px;white-space:nowrap;}'
                : '') .
            ($compact_date_width_selectors !== []
                ? implode(',', array_filter($compact_date_width_selectors, static fn($selector): bool => str_contains($selector, 'td'))) . '{width:200px;white-space:normal;line-height:1.4;}'
                : '') .
            '.wp-list-table th.column-mz_page_headline,.wp-list-table td.column-mz_page_headline{width:225px;}' .
            '.wp-list-table th.column-mz_page_cta,.wp-list-table td.column-mz_page_cta{width:175px;}' .
            '.wp-list-table th.column-mz_page_form,.wp-list-table td.column-mz_page_form{width:175px;}'
        : '.wp-list-table .column-mz_id{width:65px;max-width:65px;}' .
            '.wp-list-table .column-mz_menu_order{width:65px;max-width:65px;}' .
            '.wp-list-table .column-mz_cta_link{width:200px;max-width:200px;}' .
            '.wp-list-table .column-mz_cta_secondary_link{width:200px;max-width:200px;}' .
            '.wp-list-table .column-website{width:200px;max-width:200px;}' .
            '.wp-list-table .column-mz_faq_count{width:80px;max-width:80px;}' .
            '.wp-list-table .column-mz_form_recipients{width:200px;max-width:200px;}' .
            '.wp-list-table .column-mz_slug{width:175px;max-width:175px;}' .
            '.wp-list-table .column-mz_organization_url{width:200px;max-width:200px;}' .
            '.wp-list-table .column-mz_profile_link{width:200px;max-width:200px;}' .
            '.wp-list-table .column-mz_profile_title{width:175px;max-width:175px;}' .
            '.wp-list-table .column-mz_summary{width:325px;max-width:325px;}' .
            '.wp-list-table .column-mz_review_quote{width:325px;max-width:325px;}' .
            '.wp-list-table .column-mz_review_citer{width:175px;max-width:175px;}' .
            '.wp-list-table .column-mz_review_link{width:200px;max-width:200px;}' .
            '.wp-list-table .column-acf-taxonomies,.wp-list-table .column-acf-post-types,.wp-list-table .column-acf-field-groups{width:225px;max-width:225px;}' .
            '.wp-list-table .column-acf-count{width:125px;max-width:125px;}' .
            '.wp-list-table .column-mz_thumbnail{width:125px;}' .
            '.wp-list-table .column-title{width:225px;}' .
            '.wp-list-table .column-mz_modified,.wp-list-table .column-modified,.wp-list-table .column-mz_published,.wp-list-table .column-date{width:225px;max-width:225px;}' .
            '.wp-list-table th.column-categories,.wp-list-table td.column-categories{width:225px;max-width:225px;}' .
            '.wp-list-table th[class*="column-taxonomy-"],.wp-list-table td[class*="column-taxonomy-"]{width:225px;}' .
            '.wp-list-table th.meza-admin-taxonomy-column,.wp-list-table td.meza-admin-taxonomy-column{width:225px;min-width:225px;max-width:225px;}' .
            '.wp-list-table th.meza-admin-compact-date-column,.wp-list-table td.meza-admin-compact-date-column{width:200px;min-width:200px;max-width:200px;}' .
            ($taxonomy_width_selectors !== []
                ? implode(',', $taxonomy_width_selectors) . '{width:225px;min-width:225px;max-width:225px;}'
                : '') .
            ($runtime_taxonomy_width_selectors !== []
                ? implode(',', $runtime_taxonomy_width_selectors) . '{width:225px;min-width:225px;max-width:225px;}'
                : '') .
            ($runtime_modified_published_width_selectors !== []
                ? implode(',', $runtime_modified_published_width_selectors) . '{width:225px;min-width:225px;max-width:225px;}'
                : '') .
            ($compact_date_width_selectors !== []
                ? implode(',', array_filter($compact_date_width_selectors, static fn($selector): bool => str_contains($selector, 'th'))) . '{width:200px;min-width:200px;max-width:200px;white-space:nowrap;}'
                : '') .
            ($compact_date_width_selectors !== []
                ? implode(',', array_filter($compact_date_width_selectors, static fn($selector): bool => str_contains($selector, 'td'))) . '{width:200px;min-width:200px;max-width:200px;white-space:normal;line-height:1.4;}'
                : '') .
            '.wp-list-table th.column-mz_page_headline,.wp-list-table td.column-mz_page_headline{width:225px;max-width:225px;}' .
            '.wp-list-table th.column-mz_page_cta,.wp-list-table td.column-mz_page_cta{width:175px;max-width:175px;}' .
            '.wp-list-table th.column-mz_page_form,.wp-list-table td.column-mz_page_form{width:175px;max-width:175px;}';

    meza_render_admin_list_table_scroll_styles();

    echo '<style id="meza-admin-list-column-widths">' .
        '.wp-list-table thead th.sorted,.wp-list-table tfoot th.sorted{background:#eef4ff;color:#0a4b78;box-shadow:inset 0 -1px 0 #b8d3ea;}' .
        '.wp-list-table th.sorted a,.wp-list-table th.sorted a:focus,.wp-list-table th.sorted a:visited{color:#0a4b78;}' .
        '.wp-list-table th.sorted .sorting-indicators{opacity:1;}' .
        '.wp-list-table th.sorted.asc .sorting-indicator.asc,.wp-list-table th.sorted.desc .sorting-indicator.desc{color:#0a4b78;opacity:1;}' .
        '.wp-list-table .column-mz_id{width:65px;max-width:65px;}' .
        '.wp-list-table .column-mz_menu_order{width:65px;max-width:65px;}' .
        '.wp-list-table .column-mz_cta_link{width:200px;max-width:200px;}' .
        '.wp-list-table .column-mz_cta_secondary_link{width:200px;max-width:200px;}' .
        '.wp-list-table .column-website{width:200px;max-width:200px;}' .
        '.wp-list-table .column-mz_faq_count{width:80px;max-width:80px;}' .
        '.wp-list-table .column-mz_form_recipients{width:200px;max-width:200px;}' .
        '.wp-list-table .column-mz_slug{width:175px;max-width:175px;}' .
        '.wp-list-table .column-mz_organization_url{width:200px;max-width:200px;}' .
        '.wp-list-table .column-mz_profile_link{width:200px;max-width:200px;}' .
        '.wp-list-table .column-mz_profile_title{width:175px;max-width:175px;}' .
        '.wp-list-table .column-mz_summary{width:325px;max-width:325px;}' .
        '.wp-list-table .column-mz_review_quote{width:325px;max-width:325px;}' .
        '.wp-list-table .column-mz_review_citer{width:175px;max-width:175px;}' .
        '.wp-list-table .column-mz_review_link{width:200px;max-width:200px;}' .
        '.wp-list-table .column-acf-taxonomies,.wp-list-table .column-acf-post-types,.wp-list-table .column-acf-field-groups{width:225px;max-width:225px;}' .
        '.wp-list-table .column-acf-count{width:125px;max-width:125px;}' .
        '.wp-list-table .column-mz_thumbnail{width:125px;}' .
        '.wp-list-table td.column-mz_thumbnail{vertical-align:top!important;}' .
        '.wp-list-table td.column-mz_thumbnail .mz-thumb-wrap{display:inline-block!important;max-width:100%!important;line-height:0!important;margin:0 0 6px!important;}' .
        '.wp-list-table td.column-mz_thumbnail .mz-thumb-wrap>a{display:inline-block!important;max-width:100%!important;line-height:0!important;}' .
        '.wp-list-table td.column-mz_thumbnail img{width:auto!important;height:auto!important;max-width:100%!important;display:block!important;margin:0!important;}' .
        '.wp-list-table td[class*="column-"]>img,.wp-list-table td[class*="column-"]>a>img,.wp-list-table td[class*="column-"] .acp-image img,.wp-list-table td[class*="column-"] .ac-column__image img{width:auto!important;height:auto!important;max-width:100%!important;display:block!important;}' .
        '.wp-list-table .column-mz_thumbnail .row-actions{font-size:11px;line-height:1.1;}' .
        '.wp-list-table .column-title{width:225px;}' .
        '.wp-list-table .column-title .meza-title-template,.wp-list-table .column-title .meza-title-permalink{font-size:12px;line-height:1.4;}' .
        '.wp-list-table .column-title .meza-title-template{margin:3px 0 2px;}' .
        '.wp-list-table .column-title .meza-title-permalink{margin:0 0 4px;}' .
        '.wp-list-table .column-title .meza-title-template .meza-title-template-text{display:inline-flex;align-items:center;gap:4px;color:#646970;font-weight:500;overflow-wrap:anywhere;word-break:break-word;}' .
        '.wp-list-table .column-title .meza-title-template .meza-page-type-label{display:inline-flex;align-items:flex-start;gap:4px;vertical-align:top;}' .
        '.wp-list-table .column-title .meza-title-template .meza-page-type-label-text{line-height:1.3;}' .
        ($runtime_link_cell_selectors !== []
            ? implode(',', $runtime_link_cell_selectors) . '{white-space:normal!important;overflow-wrap:anywhere;word-break:break-word;line-height:1.4;vertical-align:top!important;}'
            : '') .
        ($runtime_link_anchor_selectors !== []
            ? implode(',', $runtime_link_anchor_selectors) . '{display:inline-block;max-width:100%;white-space:normal!important;overflow-wrap:anywhere;word-break:break-word;}'
            : '') .
        '.wp-list-table .column-title .meza-title-template .dashicons{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;font-size:14px;width:14px;height:14px;line-height:14px;position:relative;}' .
        '.wp-list-table .column-title .meza-title-permalink a{color:#646970;text-decoration:none;overflow-wrap:anywhere;word-break:break-word;}' .
        '.wp-list-table .column-title .meza-title-permalink a:hover{color:#2271b1;text-decoration:underline;}' .
        '.wp-list-table th.meza-admin-compact-date-column{white-space:nowrap;}' .
        '.wp-list-table td.meza-admin-compact-date-column{white-space:normal;line-height:1.4;}' .
        '.wp-list-table td.meza-admin-compact-date-column .meza-event-date-line{display:block;white-space:nowrap;}' .
        '.wp-list-table td.meza-admin-compact-date-column .meza-title-permalink{font-size:12px;line-height:1.4;margin:2px 0 0;color:#646970;}' .
        '.wp-list-table td.meza-admin-compact-date-column .meza-event-date-time{color:#646970;}' .
        $column_width_css .
        '</style>';

    if ($post_type === 'product') {
        echo '<style id="meza-product-admin-column-widths">' .
            ($is_acp_layout
                ? '.wp-list-table th.column-featured,.wp-list-table td.column-featured{width:48px;text-align:center;}' .
                    '.wp-list-table th.column-mz_thumbnail,.wp-list-table td.column-mz_thumbnail{width:78px;}'
                : '.wp-list-table th.column-featured,.wp-list-table td.column-featured{width:48px;min-width:48px;max-width:48px;text-align:center;}' .
                    '.wp-list-table th.column-mz_thumbnail,.wp-list-table td.column-mz_thumbnail{width:78px;min-width:78px;max-width:78px;}') .
            '.wp-list-table td.column-mz_thumbnail{vertical-align:top!important;}' .
            '.wp-list-table td.column-mz_thumbnail .mz-thumb-wrap{display:inline-block!important;max-width:100%!important;line-height:0!important;margin:0 0 6px!important;}' .
            '.wp-list-table td.column-mz_thumbnail .mz-thumb-wrap>a{display:inline-block!important;max-width:100%!important;line-height:0!important;}' .
            '.wp-list-table td.column-mz_thumbnail img{display:block!important;width:auto!important;height:auto!important;max-width:100%!important;margin:0!important;}' .
            ($is_acp_layout
                ? '.wp-list-table th.column-name,.wp-list-table td.column-name{width:240px;}' .
                    '.wp-list-table th.column-price,.wp-list-table td.column-price{width:90px;white-space:nowrap;}' .
                    '.wp-list-table th.column-is_in_stock,.wp-list-table td.column-is_in_stock{width:110px;white-space:nowrap;}' .
                    '.wp-list-table th.column-taxonomy-product_brand,.wp-list-table td.column-taxonomy-product_brand{width:130px;}' .
                    '.wp-list-table th.column-mz_product_type,.wp-list-table td.column-mz_product_type{width:140px;}' .
                    '.wp-list-table th.column-sku,.wp-list-table td.column-sku{width:190px;}' .
                    '.wp-list-table th.column-product_cat,.wp-list-table td.column-product_cat,.wp-list-table th.column-taxonomy-product_cat,.wp-list-table td.column-taxonomy-product_cat{width:190px;}' .
                    '.wp-list-table th.column-product_tag,.wp-list-table td.column-product_tag,.wp-list-table th.column-taxonomy-product_tag,.wp-list-table td.column-taxonomy-product_tag{width:180px;}' .
                    '.wp-list-table th.column-mz_page_link,.wp-list-table td.column-mz_page_link{width:320px;}' .
                    '.wp-list-table th.column-wpseo-title,.wp-list-table td.column-wpseo-title{width:260px;}' .
                    '.wp-list-table th.column-mz_share_title,.wp-list-table td.column-mz_share_title{width:260px;}' .
                    '.wp-list-table th.column-wpseo-metadesc,.wp-list-table td.column-wpseo-metadesc{width:320px;}' .
                    '.wp-list-table th.column-mz_share_description,.wp-list-table td.column-mz_share_description{width:320px;}' .
                    '.wp-list-table th.column-mz_page_headline,.wp-list-table td.column-mz_page_headline{width:260px;}' .
                    '.wp-list-table th.column-mz_page_cta,.wp-list-table td.column-mz_page_cta{width:220px;}' .
                    '.wp-list-table th.column-mz_page_form,.wp-list-table td.column-mz_page_form{width:220px;}' .
                    '.wp-list-table th.column-mz_modified,.wp-list-table td.column-mz_modified,.wp-list-table th.column-mz_published,.wp-list-table td.column-mz_published{width:220px;vertical-align:top!important;}'
                : '.wp-list-table th.column-name,.wp-list-table td.column-name{width:240px;min-width:240px;max-width:240px;}' .
                    '.wp-list-table th.column-price,.wp-list-table td.column-price{width:90px;min-width:90px;max-width:90px;white-space:nowrap;}' .
                    '.wp-list-table th.column-is_in_stock,.wp-list-table td.column-is_in_stock{width:110px;min-width:110px;max-width:110px;white-space:nowrap;}' .
                    '.wp-list-table th.column-taxonomy-product_brand,.wp-list-table td.column-taxonomy-product_brand{width:130px;min-width:130px;max-width:130px;}' .
                    '.wp-list-table th.column-mz_product_type,.wp-list-table td.column-mz_product_type{width:140px;min-width:140px;max-width:140px;}' .
                    '.wp-list-table th.column-sku,.wp-list-table td.column-sku{width:190px;min-width:190px;max-width:190px;}' .
                    '.wp-list-table th.column-product_cat,.wp-list-table td.column-product_cat,.wp-list-table th.column-taxonomy-product_cat,.wp-list-table td.column-taxonomy-product_cat{width:190px;min-width:190px;max-width:190px;}' .
                    '.wp-list-table th.column-product_tag,.wp-list-table td.column-product_tag,.wp-list-table th.column-taxonomy-product_tag,.wp-list-table td.column-taxonomy-product_tag{width:180px;min-width:180px;max-width:180px;}' .
                    '.wp-list-table th.column-mz_page_link,.wp-list-table td.column-mz_page_link{width:320px;min-width:320px;max-width:320px;}' .
                    '.wp-list-table th.column-wpseo-title,.wp-list-table td.column-wpseo-title{width:260px;min-width:260px;max-width:260px;}' .
                    '.wp-list-table th.column-mz_share_title,.wp-list-table td.column-mz_share_title{width:260px;min-width:260px;max-width:260px;}' .
                    '.wp-list-table th.column-wpseo-metadesc,.wp-list-table td.column-wpseo-metadesc{width:320px;min-width:320px;max-width:320px;}' .
                    '.wp-list-table th.column-mz_share_description,.wp-list-table td.column-mz_share_description{width:320px;min-width:320px;max-width:320px;}' .
                    '.wp-list-table th.column-mz_page_headline,.wp-list-table td.column-mz_page_headline{width:260px;min-width:260px;max-width:260px;}' .
                    '.wp-list-table th.column-mz_page_cta,.wp-list-table td.column-mz_page_cta{width:220px;min-width:220px;max-width:220px;}' .
                    '.wp-list-table th.column-mz_page_form,.wp-list-table td.column-mz_page_form{width:220px;min-width:220px;max-width:220px;}' .
                    '.wp-list-table th.column-mz_modified,.wp-list-table td.column-mz_modified,.wp-list-table th.column-mz_published,.wp-list-table td.column-mz_published{width:220px;min-width:220px;max-width:220px;vertical-align:top!important;}') .
            '.wp-list-table td.column-sku,.wp-list-table td.column-product_cat,.wp-list-table td.column-taxonomy-product_cat,.wp-list-table td.column-product_tag,.wp-list-table td.column-taxonomy-product_tag,.wp-list-table td.column-mz_page_link,.wp-list-table td.column-wpseo-title,.wp-list-table td.column-mz_share_title,.wp-list-table td.column-wpseo-metadesc,.wp-list-table td.column-mz_share_description,.wp-list-table td.column-mz_page_headline,.wp-list-table td.column-mz_page_cta,.wp-list-table td.column-mz_page_form{white-space:normal!important;overflow-wrap:anywhere;word-break:break-word;vertical-align:top!important;}' .
            '.wp-list-table td.column-mz_page_link a:first-child{display:block;white-space:normal!important;overflow-wrap:anywhere;word-break:break-word;}' .
            '.wp-list-table td.column-mz_page_link .row-actions{display:flex;flex-wrap:wrap;align-items:center;gap:0;line-height:1.3;}' .
            '.wp-list-table td.column-mz_page_link .row-actions>span{display:inline-flex;align-items:center;}' .
            '.wp-list-table td.column-mz_page_link .row-actions>span+span::before{content:"|";color:#646970;display:inline-block;margin:0 .25em;}' .
            '</style>';
    }

    if (!$is_acf_screen) return;

    // ACF list tables often use generic id/key/description column slugs.
    echo '<style id="meza-acf-admin-column-normalization">' .
        'table.wp-list-table.fixed{table-layout:fixed!important;}' .
        'table.wp-list-table.fixed col.column-id,table.wp-list-table.fixed col.column-ID{display:none!important;}' .
        '.wp-list-table th.column-id,.wp-list-table td.column-id,.wp-list-table th.column-ID,.wp-list-table td.column-ID{display:none!important;}' .
        '.wp-list-table th.column-key,.wp-list-table td.column-key,.wp-list-table th.column-description,.wp-list-table td.column-description{display:none!important;}' .
        'table.wp-list-table.fixed col.column-posts,.wp-list-table th.column-posts,.wp-list-table td.column-posts{width:125px!important;min-width:125px!important;max-width:125px!important;}' .
        'table.wp-list-table.fixed col.column-terms,.wp-list-table th.column-terms,.wp-list-table td.column-terms{width:125px!important;min-width:125px!important;max-width:125px!important;}' .
        'table.wp-list-table.fixed col.column-fields,.wp-list-table th.column-fields,.wp-list-table td.column-fields{width:125px!important;min-width:125px!important;max-width:125px!important;}' .
        '</style>';
?>
    <script id="meza-admin-list-column-classifier">
        (() => {
            const taxonomyLabels = <?php echo wp_json_encode($taxonomy_column_labels); ?> || [];
            const compactDateLabels = ['start date', 'end date'];

            const normalize = (value) => String(value || '')
                .toLowerCase()
                .replace(/\s+/g, ' ')
                .trim();

            const getCellIndex = (cell) => {
                const row = cell?.parentElement;
                if (!(row instanceof HTMLTableRowElement)) return -1;
                return Array.from(row.children).indexOf(cell);
            };

            const markColumn = (table, index, className) => {
                if (!(table instanceof HTMLTableElement) || index < 0 || !className) return;

                [table.tHead, table.tFoot].forEach((section) => {
                    if (!(section instanceof HTMLTableSectionElement)) return;
                    Array.from(section.rows).forEach((row) => {
                        const cell = row.children[index];
                        if (cell instanceof HTMLElement) cell.classList.add(className);
                    });
                });

                Array.from(table.tBodies).forEach((section) => {
                    Array.from(section.rows).forEach((row) => {
                        const cell = row.children[index];
                        if (cell instanceof HTMLElement) cell.classList.add(className);
                    });
                });
            };

            const syncTable = (table) => {
                if (!(table instanceof HTMLTableElement)) return;

                const headerRow = table.tHead?.rows?.[table.tHead.rows.length - 1];
                if (!(headerRow instanceof HTMLTableRowElement)) return;

                Array.from(headerRow.children).forEach((cell) => {
                    if (!(cell instanceof HTMLElement)) return;

                    const index = getCellIndex(cell);
                    if (index < 0) return;

                    const id = normalize(cell.id);
                    const text = normalize(cell.textContent);
                    const className = String(cell.className || '').toLowerCase();
                    const isTaxonomyColumn = (
                        id === 'categories' ||
                        id.startsWith('taxonomy-') ||
                        className.includes('column-taxonomy-') ||
                        taxonomyLabels.includes(text)
                    );

                    if (isTaxonomyColumn) {
                        markColumn(table, index, 'meza-admin-taxonomy-column');
                    }

                    if (compactDateLabels.includes(text)) {
                        markColumn(table, index, 'meza-admin-compact-date-column');
                    }
                });
            };

            const sync = () => {
                document.querySelectorAll('table.wp-list-table').forEach(syncTable);
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', sync, { once: true });
            } else {
                sync();
            }
        })();
    </script>
<?php
});

add_action('admin_head-edit.php', function (): void {
?>
    <script id="meza-admin-seo-column-truncation">
        (() => {
            const limits = {
                'column-wpseo-title': 60,
                'column-wpseo-metadesc': 160,
                'column-mz_share_title': 60,
                'column-mz_share_description': 110,
            };

            const normalizeText = (value) => String(value || '').replace(/\s+/g, ' ').trim();
            const hasSeoTokens = (value) => /%%[^%]+%%/i.test(String(value || ''));

            const isUnsetPlaceholder = (value) => {
                const normalized = normalizeText(value).toLowerCase();
                if (!normalized) return true;
                if (normalized === '-' || normalized === '—') return true;
                if (hasSeoTokens(normalized)) return true;
                return normalized.includes('not set');
            };

            const truncateText = (value, limit) => {
                const normalized = normalizeText(value);
                if (isUnsetPlaceholder(normalized)) return '';
                if (!limit || normalized.length <= limit) return normalized;
                return normalized.slice(0, Math.max(1, limit - 3)).trimEnd() + '...';
            };

            const getRowCell = (row, className) => row instanceof HTMLTableRowElement
                ? row.querySelector(`td.${className}, th.${className}`)
                : null;

            const clearCell = (cell) => {
                if (!(cell instanceof HTMLElement)) return;
                cell.textContent = '—';
                cell.removeAttribute('title');
            };

            const setCellText = (cell, text) => {
                if (!(cell instanceof HTMLElement)) return;
                if (!text) return;
                cell.textContent = text;
                cell.title = normalizeText(text);
            };

            const syncRow = (row) => {
                if (!(row instanceof HTMLTableRowElement)) return;

                Object.entries(limits).forEach(([className, limit]) => {
                    const cell = getRowCell(row, className);
                    if (!(cell instanceof HTMLElement)) return;

                    if (className === 'column-mz_share_title' || className === 'column-mz_share_description') {
                        const currentText = normalizeText(cell.textContent);
                        if (isUnsetPlaceholder(currentText)) {
                            const fallbackClass = className === 'column-mz_share_title'
                                ? 'column-wpseo-title'
                                : 'column-wpseo-metadesc';
                            const fallbackCell = getRowCell(row, fallbackClass);
                            if (fallbackCell instanceof HTMLElement) {
                                const fallbackText = truncateText(fallbackCell.textContent, limit);
                                if (fallbackText) {
                                    setCellText(cell, fallbackText);
                                    return;
                                }
                            }

                            clearCell(cell);
                            return;
                        }
                    }

                    const text = truncateText(cell.textContent, limit);
                    if (text) {
                        setCellText(cell, text);
                    } else if (isUnsetPlaceholder(cell.textContent)) {
                        clearCell(cell);
                    }
                });
            };

            const sync = () => {
                document.querySelectorAll('table.wp-list-table tbody tr').forEach(syncRow);
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', sync, { once: true });
            } else {
                sync();
            }
        })();
    </script>
<?php
});

add_action('admin_head-edit.php', function (): void {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') {
        return;
    }

    $post_type = (string) ($screen->post_type ?? '');
    $posts = $GLOBALS['wp_query']->posts ?? [];
    if (!is_array($posts) || $posts === []) {
        return;
    }

    $current_columns = function_exists('get_column_headers') ? get_column_headers($screen) : [];
    $event_date_column_keys = is_array($current_columns) ? meza_get_event_date_admin_column_keys($current_columns) : ['start' => '', 'end' => ''];
    $event_runtime_date_keys = is_array($current_columns) ? meza_get_event_date_admin_column_runtime_keys($current_columns) : [];
    $link_column_keys = is_array($current_columns) ? meza_get_link_admin_column_keys($current_columns) : [];
    $age_group_column_keys = is_array($current_columns) ? meza_get_age_group_admin_column_keys($current_columns) : [];

    $column_map = [];
    $taxonomy_keys = meza_get_taxonomy_admin_column_keys_for_post_type($post_type);

    foreach ($posts as $post) {
        if (!($post instanceof WP_Post)) {
            continue;
        }

        foreach ($taxonomy_keys as $column_key) {
            $taxonomy = meza_resolve_taxonomy_from_admin_column_key((string) $column_key, $post_type);
            if ($taxonomy === '') {
                continue;
            }

            $column_html = meza_get_taxonomy_admin_column_html($taxonomy, $post);
            if ($column_html === '') {
                continue;
            }

            $column_map[(int) $post->ID][(string) $column_key] = $column_html;
        }

        if ($age_group_column_keys !== []) {
            $age_group_html = meza_get_age_group_admin_column_html($post);
            if ($age_group_html !== '') {
                foreach ($age_group_column_keys as $column_key) {
                    $column_map[(int) $post->ID][(string) $column_key] = $age_group_html;
                }

                $column_map[(int) $post->ID]['age-group'] = $age_group_html;
                $column_map[(int) $post->ID]['age_group'] = $age_group_html;
            }
        }
    }

    if (!meza_is_event_post_type($post_type)) {
        $link_map = [];

        if ($link_column_keys !== []) {
            foreach ($posts as $post) {
                if (!($post instanceof WP_Post)) {
                    continue;
                }

                $link_html = meza_get_post_type_link_admin_column_html((int) $post->ID);
                if ($link_html === '') {
                    continue;
                }

                foreach ($link_column_keys as $column_key) {
                    $column_key = trim((string) $column_key);
                    if ($column_key === '') {
                        continue;
                    }

                    $link_map[(int) $post->ID][$column_key] = $link_html;
                }
            }
        }

        if ($column_map === [] && $link_map === []) {
            return;
        }
?>
    <script id="meza-admin-column-content-normalizer">
        (() => {
            const columnMap = <?php echo wp_json_encode($column_map); ?> || {};
            const linkMap = <?php echo wp_json_encode($link_map); ?> || {};

            const normalize = (value) => String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();

            const getColumnKeys = (cell) => {
                if (!(cell instanceof HTMLElement)) {
                    return [];
                }

                const keys = [];
                const id = String(cell.id || '').trim();
                if (id) {
                    keys.push(id);
                }

                String(cell.className || '')
                    .split(/\s+/)
                    .filter(Boolean)
                    .forEach((className) => {
                        if (className.startsWith('column-')) {
                            keys.push(className.slice(7));
                        }
                    });

                const label = normalize(cell.textContent);
                if (label === 'age group') {
                    keys.push('age-group', 'age_group');
                }

                return Array.from(new Set(keys.filter(Boolean)));
            };

            const sync = () => {
                document.querySelectorAll('table.wp-list-table').forEach((table) => {
                    if (!(table instanceof HTMLTableElement)) return;

                    const headerRow = table.tHead?.rows?.[table.tHead.rows.length - 1];
                    if (!(headerRow instanceof HTMLTableRowElement)) return;

                    const columnKeysByIndex = Array.from(headerRow.children).map((cell) => getColumnKeys(cell));

                    Array.from(table.tBodies).forEach((tbody) => {
                        Array.from(tbody.rows).forEach((row) => {
                            if (!(row instanceof HTMLTableRowElement)) return;

                            const match = String(row.id || '').match(/^post-(\d+)$/);
                            if (!match) return;

                            const postColumns = columnMap[match[1]];
                            const postLinks = linkMap[match[1]];
                            if (!postColumns && !postLinks) return;

                            Array.from(row.children).forEach((cell, index) => {
                                if (!(cell instanceof HTMLElement)) return;

                                const matchedKey = postColumns
                                    ? (columnKeysByIndex[index] || []).find((key) => key && postColumns[key])
                                    : '';
                                if (matchedKey) {
                                    cell.innerHTML = postColumns[matchedKey] || '&mdash;';
                                    return;
                                }

                                if (!postLinks) return;

                                const linkKey = (columnKeysByIndex[index] || []).find((key) => key && postLinks[key]);
                                if (!linkKey) return;

                                cell.innerHTML = postLinks[linkKey] || '&mdash;';
                            });
                        });
                    });
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', sync, { once: true });
            } else {
                sync();
            }
        })();
    </script>
<?php
        return;
    }

    $date_map = [];
    $link_map = [];

    foreach ($posts as $post) {
        if (!($post instanceof WP_Post)) {
            continue;
        }

        $start_raw = meza_get_event_admin_column_datetime_value((int) $post->ID, 'start');
        $end_raw = meza_get_event_admin_column_datetime_value((int) $post->ID, 'end');
        $combined_date = meza_format_event_admin_datetime_range($start_raw, $end_raw);

        if ($combined_date === '') {
            continue;
        }

        $date_value = meza_get_event_admin_datetime_range_html($start_raw, $end_raw);
        $date_map[(int) $post->ID] = [
            'start-date' => $date_value,
            'start_date' => $date_value,
        ];

        $start_column_key = trim((string) ($event_date_column_keys['start'] ?? ''));
        if ($start_column_key !== '') {
            $date_map[(int) $post->ID][$start_column_key] = $date_value;
        }

        foreach ($event_runtime_date_keys as $column_key) {
            $column_key = trim((string) $column_key);
            if ($column_key === '') {
                continue;
            }

            $date_map[(int) $post->ID][$column_key] = $date_value;
        }

        if ($link_column_keys !== []) {
            $link_html = meza_get_post_type_link_admin_column_html((int) $post->ID);
            if ($link_html !== '') {
                foreach ($link_column_keys as $column_key) {
                    $column_key = trim((string) $column_key);
                    if ($column_key === '') {
                        continue;
                    }

                    $link_map[(int) $post->ID][$column_key] = $link_html;
                }
            }
        }
    }

    if ($column_map === [] && $date_map === [] && $link_map === []) {
        return;
    }
?>
    <script id="meza-admin-column-content-normalizer">
        (() => {
            const columnMap = <?php echo wp_json_encode($column_map); ?> || {};
            const dateMap = <?php echo wp_json_encode($date_map); ?> || {};
            const linkMap = <?php echo wp_json_encode($link_map); ?> || {};
            const eventDateColumnKeys = <?php echo wp_json_encode(array_values(array_filter(array_unique(array_map('strval', [
                $event_date_column_keys['start'] ?? '',
                'start-date',
                'start_date',
            ]))))); ?> || [];
            const normalize = (value) => String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();

            const getColumnKeys = (cell) => {
                if (!(cell instanceof HTMLElement)) {
                    return [];
                }

                const keys = [];
                const id = String(cell.id || '').trim();
                if (id) {
                    keys.push(id);
                }

                String(cell.className || '')
                    .split(/\s+/)
                    .filter(Boolean)
                    .forEach((className) => {
                        if (className.startsWith('column-')) {
                            keys.push(className.slice(7));
                        }
                    });

                const label = normalize(cell.textContent);
                if (label === 'start date') {
                    keys.push('start-date', 'start_date');
                }
                if (label === 'date') {
                    keys.push(...eventDateColumnKeys);
                }
                if (label === 'age group') {
                    keys.push('age-group', 'age_group');
                }

                return Array.from(new Set(keys.filter(Boolean)));
            };

            const getColumnKeysByIndex = (table) => {
                const headerRow = table?.tHead?.rows?.[table.tHead.rows.length - 1];
                if (!(headerRow instanceof HTMLTableRowElement)) {
                    return [];
                }

                return Array.from(headerRow.children).map((cell) => getColumnKeys(cell));
            };

            const sync = () => {
                document.querySelectorAll('table.wp-list-table').forEach((table) => {
                    if (!(table instanceof HTMLTableElement)) return;

                    const columnKeysByIndex = getColumnKeysByIndex(table);
                    if (!columnKeysByIndex.length) return;

                    Array.from(table.tBodies).forEach((tbody) => {
                        Array.from(tbody.rows).forEach((row) => {
                            if (!(row instanceof HTMLTableRowElement)) return;

                            const match = String(row.id || '').match(/^post-(\d+)$/);
                            if (!match) return;

                            const postColumns = columnMap[match[1]] || {};
                            const postDates = dateMap[match[1]];
                            const postLinks = linkMap[match[1]];

                            Array.from(row.children).forEach((cell, index) => {
                                if (!(cell instanceof HTMLElement)) return;

                                const keys = columnKeysByIndex[index] || [];
                                const taxonomyKey = keys.find((key) => key && postColumns[key]);
                                if (taxonomyKey) {
                                    cell.innerHTML = postColumns[taxonomyKey] || '&mdash;';
                                    return;
                                }

                                const dateKey = postDates
                                    ? keys.find((key) => key && postDates[key])
                                    : '';
                                if (dateKey) {
                                    cell.innerHTML = postDates[dateKey] || '&mdash;';
                                    return;
                                }

                                if (!postLinks) return;

                                const linkKey = keys.find((key) => key && postLinks[key]);
                                if (!linkKey) return;

                                cell.innerHTML = postLinks[linkKey] || '&mdash;';
                            });
                        });
                    });
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', sync, { once: true });
            } else {
                sync();
            }
        })();
    </script>
<?php
});

add_action('admin_head-edit.php', function (): void {
?>
    <script id="meza-admin-image-column-containment">
        (() => {
            const WIDTH_RULE_PATTERN = /column-([a-z0-9_-]+)[^{}]*\{\s*width:\s*([^!;}{]+)!important;/gi;
            const IMAGE_SELECTOR = 'img';
            const WRAPPER_SELECTOR = '.mz-thumb-wrap, .acp-image, .ac-column__image, a, span, div';

            const normalizeWidth = (value) => {
                const width = String(value || '').trim();
                return width && width !== 'auto' ? width : '';
            };

            const getConfiguredColumnWidths = () => {
                const widths = new Map();

                document.querySelectorAll('style[id^="ac-column-size-"]').forEach((styleNode) => {
                    const css = String(styleNode.textContent || '');
                    let match;

                    while ((match = WIDTH_RULE_PATTERN.exec(css)) !== null) {
                        const columnId = String(match[1] || '').trim();
                        const width = normalizeWidth(match[2] || '');

                        if (!columnId || !width || widths.has(columnId)) {
                            continue;
                        }

                        widths.set(columnId, width);
                    }
                });

                return widths;
            };

            const constrainImageCell = (cell, width) => {
                if (!(cell instanceof HTMLElement) || !width) return;

                const images = cell.querySelectorAll(IMAGE_SELECTOR);
                if (!images.length) return;

                cell.style.width = width;
                cell.style.minWidth = width;
                cell.style.maxWidth = width;
                cell.style.overflow = 'hidden';
                cell.style.boxSizing = 'border-box';

                cell.querySelectorAll(WRAPPER_SELECTOR).forEach((wrapper) => {
                    if (!(wrapper instanceof HTMLElement) || !wrapper.querySelector(IMAGE_SELECTOR)) {
                        return;
                    }

                    wrapper.style.display = 'block';
                    wrapper.style.width = '100%';
                    wrapper.style.maxWidth = '100%';
                    wrapper.style.overflow = 'hidden';
                    wrapper.style.boxSizing = 'border-box';
                });

                images.forEach((image) => {
                    if (!(image instanceof HTMLElement)) return;

                    image.style.display = 'block';
                    image.style.width = 'auto';
                    image.style.height = 'auto';
                    image.style.maxWidth = '100%';
                    image.style.objectFit = 'contain';
                    image.removeAttribute('width');
                    image.removeAttribute('height');
                });
            };

            const syncTable = (table, widths) => {
                if (!(table instanceof HTMLTableElement) || !(widths instanceof Map) || widths.size === 0) {
                    return;
                }

                widths.forEach((width, columnId) => {
                    table.querySelectorAll(`th.column-${columnId}, td.column-${columnId}`).forEach((cell) => {
                        constrainImageCell(cell, width);
                    });
                });
            };

            const sync = () => {
                const widths = getConfiguredColumnWidths();
                if (widths.size === 0) return;

                document.querySelectorAll('table.wp-list-table').forEach((table) => {
                    syncTable(table, widths);
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', sync, { once: true });
            } else {
                sync();
            }

            window.addEventListener('resize', sync, { passive: true });

            const observer = new MutationObserver(sync);
            observer.observe(document.documentElement, {
                childList: true,
                subtree: true,
            });
        })();
    </script>
<?php
});

// Open linked post titles in a new tab on admin list tables.
add_action('admin_print_footer_scripts-edit.php', function () {
    echo '<script id="meza-admin-title-link-target">' .
        'document.querySelectorAll(".wp-list-table .row-title").forEach(function(link){' .
        'link.setAttribute("target","_blank");' .
        'link.setAttribute("rel","noopener noreferrer");' .
        '});' .
        'document.querySelectorAll(".wp-list-table .mz-copy-link[data-permalink-url]").forEach(function(link){' .
        'var actions=link.closest(".row-actions");' .
        'if(!actions) return;' .
        'var cell=actions.closest("td,th");' .
        'if(!cell || cell.querySelector(".meza-title-permalink")) return;' .
        'var templateHtml=link.getAttribute("data-page-template-html")||"";' .
        'var url=link.getAttribute("data-permalink-url")||"";' .
        'var display=link.getAttribute("data-permalink-display")||url;' .
        'if(!url || !display) return;' .
        'if(templateHtml && !cell.querySelector(".meza-title-template")){' .
        'var templateWrap=document.createElement("div");' .
        'templateWrap.className="meza-title-template";' .
        'templateWrap.innerHTML=templateHtml;' .
        'actions.parentNode.insertBefore(templateWrap,actions);' .
        '}' .
        'var wrap=document.createElement("div");' .
        'wrap.className="meza-title-permalink";' .
        'var anchor=document.createElement("a");' .
        'anchor.href=url;' .
        'anchor.target="_blank";' .
        'anchor.rel="noopener noreferrer";' .
        'anchor.textContent=display;' .
        'wrap.appendChild(anchor);' .
        'actions.parentNode.insertBefore(wrap,actions);' .
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

add_action('admin_footer-edit.php', function () {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || $screen->base !== 'edit') return;

    meza_render_admin_list_table_scroll_wrap_script();
});

add_action('admin_head-upload.php', function (): void {
    meza_render_admin_list_table_scroll_styles();
}, 1000);

add_action('admin_footer-upload.php', function (): void {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen) || $screen->base !== 'upload') return;

    meza_render_admin_list_table_scroll_wrap_script();
}, 1000);
