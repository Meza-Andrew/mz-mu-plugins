<?php

/**
 * Client-only compatibility shims for third-party plugins that are not part of
 * the normal `mz-starter` baseline.
 *
 * Keep these fixes narrowly scoped, easy to remove, and targeted at the exact
 * plugin/version behavior we need to smooth over on a given project.
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) return;

if (!function_exists('mz_plugin_compat_enabled')) {
    function mz_plugin_compat_enabled(): bool
    {
        return !(defined('MZ_DISABLE_PLUGIN_COMPAT') && MZ_DISABLE_PLUGIN_COMPAT);
    }
}

if (!mz_plugin_compat_enabled()) {
    return;
}

// Let UpdraftPlus keep operational warnings while suppressing its promo notice stack.
if (!defined('UPDRAFTPLUS_NOADS_B')) {
    define('UPDRAFTPLUS_NOADS_B', true);
}

if (!function_exists('mz_plugin_compat_is_updraft_admin_page')) {
    function mz_plugin_compat_is_updraft_admin_page(): bool
    {
        if (!is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        return $page === 'updraftplus';
    }
}

if (!function_exists('mz_plugin_compat_cleanup_updraft_admin_markup')) {
    function mz_plugin_compat_cleanup_updraft_admin_markup(string $html): string
    {
        $html = (string) preg_replace(
            '#\s*<tr class="backup-interval-description">.*?</tr>\s*#is',
            '',
            $html
        );

        $html = (string) preg_replace(
            '#\s*<h2 class="updraft_settings_sectionheading">\s*Database Options\s*</h2>\s*<table class="form-table width-900">.*?</table>\s*#is',
            '',
            $html
        );

        $html = (string) preg_replace(
            '#(<p>\s*The above includes all WordPress file directories, except for WordPress core which you can download afresh from WordPress\.org\.\s*).*?(</p>)#is',
            '$1$2',
            $html
        );

        $html = (string) preg_replace(
            '#\s*<p>\s*<a\b[^>]*href="[^"]*(?:\?|&)utm_[^"]*"[^>]*>.*?</a>\s*</p>\s*#is',
            '',
            $html
        );

        $html = (string) preg_replace(
            '#\s*<a\b[^>]*href="[^"]*(?:\?|&)utm_[^"]*"[^>]*>\s*For more reporting features, use the Premium version\s*</a>\s*#is',
            '',
            $html
        );

        $html = (string) preg_replace(
            '#\s*<p>\s*<a\b[^>]*href="https?://wordpress\.org/plugins/[^"]*"[^>]*>.*?</a>\s*</p>\s*#is',
            '',
            $html
        );

        $html = (string) preg_replace(
            '#\s*<br\s*/?>\s*<a\b[^>]*href="https?://wordpress\.org/plugins/[^"]*"[^>]*>.*?</a>\s*#is',
            '',
            $html
        );

        $html = (string) preg_replace(
            '#\s*<div\b[^>]*id=(["\'])updraftcentral_cloud_connect_container\1[^>]*>\s*</div>\s*#i',
            '',
            $html
        );

        $html = (string) preg_replace(
            '#\s*<div\b[^>]*id=(["\'])updraft_backup_started\1[^>]*>\s*</div>\s*#i',
            '',
            $html
        );

        return $html;
    }
}

add_action('admin_init', function (): void {
    if (
        !mz_plugin_compat_is_updraft_admin_page()
        || (defined('DOING_AJAX') && DOING_AJAX)
    ) {
        return;
    }

    ob_start('mz_plugin_compat_cleanup_updraft_admin_markup');
}, 0);

add_action('admin_footer', function (): void {
    if (!mz_plugin_compat_is_updraft_admin_page()) {
        return;
    }
    ?>
<script>
jQuery(function ($) {
    $('#updraftplus-settings-save').on('click', function () {
        if ($('#updraft_backup_started').length) {
            return;
        }

        var $wrap = $('#updraft-wrap');
        if (!$wrap.length) {
            return;
        }

        $('<div id="updraft_backup_started" class="updated updraft-hidden" style="display:none;"></div>')
            .prependTo($wrap);
    });
});
</script>
    <?php
}, 20);

add_filter('updraftplus_main_tabs', function (array $tabs): array {
    unset($tabs['migrate']);
    unset($tabs['addons']);
    return $tabs;
}, PHP_INT_MAX);

add_filter('updraftplus_addonstab_content', function ($content) {
    if (!mz_plugin_compat_is_updraft_admin_page()) {
        return $content;
    }

    return '';
}, PHP_INT_MAX);

add_action('admin_init', function (): void {
    if (!mz_plugin_compat_is_updraft_admin_page()) {
        return;
    }

    $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : '';
    if (!in_array($tab, ['addons', 'migrate'], true)) {
        return;
    }

    wp_safe_redirect(admin_url('options-general.php?page=updraftplus'));
    exit;
}, 20);

add_filter('updraftplus_template', function ($template_file, $path) {
    $override_map = [
        'wp-admin/settings/header.php' => __DIR__ . '/overrides/plugin-compat/updraftplus/header.php',
        'wp-admin/settings/updraftcentral-connect.php' => __DIR__ . '/overrides/plugin-compat/updraftplus/updraftcentral-connect.php',
    ];

    if (!isset($override_map[$path])) {
        return $template_file;
    }

    $override = $override_map[$path];

    return is_file($override) ? $override : $template_file;
}, 20, 2);

if (!function_exists('mz_plugin_compat_toolset_value_is_enabled')) {
    function mz_plugin_compat_toolset_value_is_enabled($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) !== 0;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return false;
        }

        return !in_array($normalized, ['0', 'false', 'no', 'off'], true);
    }
}

if (!function_exists('mz_plugin_compat_toolset_expand_label_template')) {
    function mz_plugin_compat_toolset_expand_label_template(string $key, string $label, string $singular, string $plural): string
    {
        if (!str_contains($label, '%s')) {
            return $label;
        }

        $singular_keys = [
            'singular_name',
            'add_new_item',
            'edit_item',
            'new_item',
            'view_item',
            'parent_item',
            'parent_item_colon',
            'update_item',
            'new_item_name',
            'name_admin_bar',
            'enter_title_here',
        ];

        $replacement = in_array($key, $singular_keys, true) ? $singular : $plural;

        return sprintf($label, $replacement);
    }
}

if (!function_exists('mz_plugin_compat_toolset_build_labels')) {
    function mz_plugin_compat_toolset_build_labels(array $labels): array
    {
        $singular = trim((string) ($labels['singular_name'] ?? ''));
        $plural = trim((string) ($labels['name'] ?? $singular));

        if ($singular === '') {
            $singular = $plural;
        }

        $resolved = [];

        foreach ($labels as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                continue;
            }

            $resolved[$key] = mz_plugin_compat_toolset_expand_label_template(
                $key,
                (string) $value,
                $singular,
                $plural
            );
        }

        return $resolved;
    }
}

if (!function_exists('mz_plugin_compat_toolset_normalize_menu_icon')) {
    function mz_plugin_compat_toolset_normalize_menu_icon(string $icon): string
    {
        $icon = trim($icon);
        if ($icon === '') {
            return '';
        }

        if (
            str_starts_with($icon, 'dashicons-')
            || str_contains($icon, '/')
            || str_contains($icon, '.')
            || str_starts_with($icon, 'data:')
        ) {
            return $icon;
        }

        return 'dashicons-' . $icon;
    }
}

if (!function_exists('mz_plugin_compat_toolset_build_rewrite_args')) {
    function mz_plugin_compat_toolset_build_rewrite_args(array $rewrite_definition, string $default_slug): array|bool
    {
        if (!mz_plugin_compat_toolset_value_is_enabled($rewrite_definition['enabled'] ?? false)) {
            return false;
        }

        $rewrite = [
            'with_front' => mz_plugin_compat_toolset_value_is_enabled($rewrite_definition['with_front'] ?? true),
            'hierarchical' => mz_plugin_compat_toolset_value_is_enabled($rewrite_definition['hierarchical'] ?? false),
        ];

        if (array_key_exists('feeds', $rewrite_definition)) {
            $rewrite['feeds'] = mz_plugin_compat_toolset_value_is_enabled($rewrite_definition['feeds']);
        }

        if (array_key_exists('pages', $rewrite_definition)) {
            $rewrite['pages'] = mz_plugin_compat_toolset_value_is_enabled($rewrite_definition['pages']);
        }

        $slug = trim((string) ($rewrite_definition['slug'] ?? ''));
        if ($slug !== '') {
            $rewrite['slug'] = $slug;
        } elseif ($default_slug !== '') {
            $rewrite['slug'] = $default_slug;
        }

        return $rewrite;
    }
}

if (!function_exists('mz_plugin_compat_toolset_get_enabled_keys')) {
    function mz_plugin_compat_toolset_get_enabled_keys($values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $enabled = [];

        foreach ($values as $key => $value) {
            $key = sanitize_key((string) $key);
            if ($key === '' || !mz_plugin_compat_toolset_value_is_enabled($value)) {
                continue;
            }

            $enabled[] = $key;
        }

        return array_values(array_unique($enabled));
    }
}

if (!function_exists('mz_plugin_compat_get_toolset_taxonomy_args')) {
    function mz_plugin_compat_get_toolset_taxonomy_args(string $taxonomy, array $definition): array
    {
        $labels = mz_plugin_compat_toolset_build_labels(
            isset($definition['labels']) && is_array($definition['labels'])
                ? $definition['labels']
                : []
        );

        $query_var_enabled = mz_plugin_compat_toolset_value_is_enabled($definition['query_var_enabled'] ?? false);
        $query_var_value = trim((string) ($definition['query_var'] ?? ''));

        $args = [
            'labels' => $labels,
            'description' => (string) ($definition['description'] ?? ''),
            'public' => mz_plugin_compat_toolset_value_is_enabled($definition['public'] ?? false),
            'hierarchical' => mz_plugin_compat_toolset_value_is_enabled($definition['hierarchical'] ?? false),
            'show_ui' => mz_plugin_compat_toolset_value_is_enabled($definition['show_ui'] ?? false),
            'show_in_nav_menus' => mz_plugin_compat_toolset_value_is_enabled($definition['show_in_nav_menus'] ?? false),
            'show_tagcloud' => mz_plugin_compat_toolset_value_is_enabled($definition['show_tagcloud'] ?? false),
            'show_admin_column' => mz_plugin_compat_toolset_value_is_enabled($definition['show_admin_column'] ?? false),
            'query_var' => $query_var_enabled ? ($query_var_value !== '' ? $query_var_value : true) : false,
            'rewrite' => mz_plugin_compat_toolset_build_rewrite_args(
                isset($definition['rewrite']) && is_array($definition['rewrite']) ? $definition['rewrite'] : [],
                trim((string) ($definition['slug'] ?? $taxonomy))
            ),
        ];

        $rest_base = trim((string) ($definition['rest_base'] ?? ''));
        if ($rest_base !== '') {
            $args['rest_base'] = $rest_base;
        }

        $meta_box_callback = trim((string) ($definition['meta_box_cb']['callback'] ?? ''));
        if ($meta_box_callback === '') {
            $meta_box_callback = 'post_categories_meta_box';
        }
        $args['meta_box_cb'] = $meta_box_callback;

        $meta_box_sanitize_callback = trim((string) ($definition['meta_box_cb']['meta_box_sanitize_cb'] ?? ''));
        if ($meta_box_sanitize_callback === '' && $meta_box_callback === 'post_categories_meta_box') {
            $meta_box_sanitize_callback = 'taxonomy_meta_box_sanitize_cb_checkboxes';
        }
        if ($meta_box_sanitize_callback !== '') {
            $args['meta_box_sanitize_cb'] = $meta_box_sanitize_callback;
        }

        return $args;
    }
}

if (!function_exists('mz_plugin_compat_get_toolset_post_type_args')) {
    function mz_plugin_compat_get_toolset_post_type_args(string $post_type, array $definition): array
    {
        $labels = mz_plugin_compat_toolset_build_labels(
            isset($definition['labels']) && is_array($definition['labels'])
                ? $definition['labels']
                : []
        );

        $show_in_menu_page = trim((string) ($definition['show_in_menu_page'] ?? ''));
        $show_in_menu = mz_plugin_compat_toolset_value_is_enabled($definition['show_in_menu'] ?? false);

        if ($show_in_menu_page !== '') {
            $show_in_menu = $show_in_menu_page;
        }

        $has_archive_enabled = mz_plugin_compat_toolset_value_is_enabled($definition['has_archive'] ?? false);
        $has_archive_slug = trim((string) ($definition['has_archive_slug'] ?? ''));

        $query_var_enabled = mz_plugin_compat_toolset_value_is_enabled($definition['query_var_enabled'] ?? false);
        $query_var_value = trim((string) ($definition['query_var'] ?? ''));

        $args = [
            'labels' => $labels,
            'description' => (string) ($definition['description'] ?? ''),
            'public' => mz_plugin_compat_toolset_value_is_enabled($definition['public'] ?? false),
            'publicly_queryable' => mz_plugin_compat_toolset_value_is_enabled($definition['publicly_queryable'] ?? false),
            'show_ui' => mz_plugin_compat_toolset_value_is_enabled($definition['show_ui'] ?? false),
            'show_in_menu' => $show_in_menu,
            'show_in_nav_menus' => mz_plugin_compat_toolset_value_is_enabled($definition['show_in_nav_menus'] ?? false),
            'show_in_rest' => mz_plugin_compat_toolset_value_is_enabled($definition['show_in_rest'] ?? false),
            'exclude_from_search' => mz_plugin_compat_toolset_value_is_enabled($definition['exclude_from_search'] ?? false),
            'hierarchical' => mz_plugin_compat_toolset_value_is_enabled($definition['hierarchical'] ?? false),
            'can_export' => mz_plugin_compat_toolset_value_is_enabled($definition['can_export'] ?? true),
            'menu_icon' => mz_plugin_compat_toolset_normalize_menu_icon((string) ($definition['icon'] ?? '')),
            'supports' => mz_plugin_compat_toolset_get_enabled_keys($definition['supports'] ?? []),
            'taxonomies' => mz_plugin_compat_toolset_get_enabled_keys($definition['taxonomies'] ?? []),
            'rewrite' => mz_plugin_compat_toolset_build_rewrite_args(
                isset($definition['rewrite']) && is_array($definition['rewrite']) ? $definition['rewrite'] : [],
                trim((string) ($definition['slug'] ?? $post_type))
            ),
            'has_archive' => $has_archive_slug !== '' ? $has_archive_slug : $has_archive_enabled,
            'query_var' => $query_var_enabled ? ($query_var_value !== '' ? $query_var_value : true) : false,
        ];

        $rest_base = trim((string) ($definition['rest_base'] ?? ''));
        if ($rest_base !== '') {
            $args['rest_base'] = $rest_base;
        }

        return $args;
    }
}

if (!function_exists('mz_plugin_compat_restore_toolset_content_types')) {
    function mz_plugin_compat_restore_toolset_content_types(): void
    {
        $stored_taxonomies = get_option('wpcf-custom-taxonomies', []);
        if (is_array($stored_taxonomies)) {
            foreach ($stored_taxonomies as $taxonomy => $definition) {
                $taxonomy = sanitize_key((string) $taxonomy);
                if (
                    $taxonomy === ''
                    || taxonomy_exists($taxonomy)
                    || !is_array($definition)
                    || mz_plugin_compat_toolset_value_is_enabled($definition['disabled'] ?? false)
                ) {
                    continue;
                }

                $object_types = mz_plugin_compat_toolset_get_enabled_keys($definition['supports'] ?? []);
                if ($object_types === []) {
                    continue;
                }

                register_taxonomy(
                    $taxonomy,
                    $object_types,
                    mz_plugin_compat_get_toolset_taxonomy_args($taxonomy, $definition)
                );
            }
        }

        $stored_post_types = get_option('wpcf-custom-types', []);
        if (!is_array($stored_post_types)) {
            return;
        }

        foreach ($stored_post_types as $post_type => $definition) {
            $post_type = sanitize_key((string) $post_type);
            if (
                $post_type === ''
                || post_type_exists($post_type)
                || !is_array($definition)
                || mz_plugin_compat_toolset_value_is_enabled($definition['disabled'] ?? false)
            ) {
                continue;
            }

            register_post_type(
                $post_type,
                mz_plugin_compat_get_toolset_post_type_args($post_type, $definition)
            );
        }
    }
}

add_action('init', 'mz_plugin_compat_restore_toolset_content_types', 100);

// Popup Maker can cache its bundled frontend assets into uploads, but this site
// is currently returning 404s for those generated files. Force the plugin back
// to its built-in asset URLs so popup markup does not render inline unstyled.
if (!defined('PUM_ASSET_CACHE')) {
    define('PUM_ASSET_CACHE', false);
}

if (!function_exists('mz_plugin_compat_normalize_path')) {
    function mz_plugin_compat_normalize_path(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}

if (!function_exists('mz_plugin_compat_get_eol')) {
    function mz_plugin_compat_get_eol(string $contents): string
    {
        return str_contains($contents, "\r\n") ? "\r\n" : "\n";
    }
}

if (!function_exists('mz_plugin_compat_resolve_source_patch_target')) {
    function mz_plugin_compat_resolve_source_patch_target(string $relative_file): string
    {
        $relative_file = ltrim($relative_file, '/\\');
        if ($relative_file === '') {
            return '';
        }

        if (str_starts_with($relative_file, 'wp-content/')) {
            return rtrim(ABSPATH, '/\\') . '/' . $relative_file;
        }

        if (str_starts_with($relative_file, 'wp-includes/') || str_starts_with($relative_file, 'wp-admin/')) {
            return rtrim(ABSPATH, '/\\') . '/' . $relative_file;
        }

        $plugin_dir = defined('WP_PLUGIN_DIR')
            ? rtrim((string) WP_PLUGIN_DIR, '/\\')
            : rtrim(ABSPATH, '/\\') . '/wp-content/plugins';

        return $plugin_dir . '/' . $relative_file;
    }
}

if (!function_exists('mz_plugin_compat_get_source_patch_rules')) {
    function mz_plugin_compat_get_source_patch_rules(): array
    {
        $rules = [
            'divi_booster_dynamic_package_slug' => [
                'file' => '/divi-booster/core/wtfplugin_1_0.class.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, 'var $package_slug;')) return $contents;

                    $eol = mz_plugin_compat_get_eol($contents);
                    return preg_replace_callback(
                        '/([ \t]*var \$error_handler;\R)/',
                        static function (array $matches) use ($eol): string {
                            return $matches[1] . "\tvar \$package_slug;" . $eol;
                        },
                        $contents,
                        1
                    ) ?: $contents;
                },
            ],
            'divi_booster_contact_form_email_blacklist_runtime_properties' => [
                'file' => '/divi-booster/core/features/contactFormEmailBlacklist/dbdb-contactform-emailblacklist.php',
                'apply' => static function (string $contents): string {
                    if (
                        str_contains($contents, 'protected $current_blacklist = array();')
                        && str_contains($contents, 'protected $current_contact_form = 0;')
                    ) {
                        return $contents;
                    }

                    $eol = mz_plugin_compat_get_eol($contents);
                    return preg_replace_callback(
                        '/([ \t]*protected \$email_blacklist_key = \'dbdb_email_blacklist\';\R)/',
                        static function (array $matches) use ($eol): string {
                            return $matches[1]
                                . "\tprotected \$current_blacklist = array();" . $eol
                                . "\tprotected \$current_contact_form = 0;" . $eol;
                        },
                        $contents,
                        1
                    ) ?: $contents;
                },
            ],
            'monsterinsights_site_health_tracking_property' => [
                'file' => '/google-analytics-for-wordpress/lite/includes/admin/wp-site-health.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, 'private $is_tracking;')) {
                        return $contents;
                    }

                    $eol = mz_plugin_compat_get_eol($contents);
                    return preg_replace_callback(
                        '/([ \t]*private \$ecommerce;\R)/',
                        static function (array $matches) use ($eol): string {
                            return $matches[1] . "\tprivate \$is_tracking;" . $eol;
                        },
                        $contents,
                        1
                    ) ?: $contents;
                },
            ],
            'divi_booster_early_textdomain_constants' => [
                'file' => '/divi-booster/divi-booster.php',
                'apply' => static function (string $contents): string {
                    $updated = preg_replace(
                        "/define\\('BOOSTER_NAME',\\s*__\\('Divi Booster',\\s*BOOSTER_SLUG\\)\\);/",
                        "define('BOOSTER_NAME', 'Divi Booster');",
                        $contents,
                        1
                    );

                    return is_string($updated) ? $updated : $contents;
                },
            ],
            'divi_booster_early_textdomain_config' => [
                'file' => '/divi-booster/divi-booster.php',
                'apply' => static function (string $contents): string {
                    $updated = preg_replace(
                        "/'plugin_name'\\s*=>\\s*__\\('Divi Booster',\\s*'divi-booster'\\),/",
                        "'plugin_name' => 'Divi Booster',",
                        $contents,
                        1
                    );

                    return is_string($updated) ? $updated : $contents;
                },
            ],
            'divi_theme_metabox_post_id_guard' => [
                'file' => '/wp-content/themes/Divi/functions.php',
                'apply' => static function (string $contents): string {
                    $replace = '$enabled = ( is_object( $post ) && isset( $post->ID ) ) ? et_builder_enabled_for_post( $post->ID ) : et_builder_enabled_for_post_type( $post_type );';
                    if (str_contains($contents, $replace)) {
                        return $contents;
                    }

                    $search = '$enabled = $post ? et_builder_enabled_for_post( $post->ID ) : et_builder_enabled_for_post_type( $post_type );';
                    $updated = str_replace($search, $replace, $contents, $count);

                    return $count > 0 ? $updated : $contents;
                },
            ],
            'divi_builder_metabox_post_id_guard' => [
                'file' => '/wp-content/themes/Divi/includes/builder/functions.php',
                'apply' => static function (string $contents): string {
                    $updated = $contents;

                    $first_replace = 'if ( et_builder_bfb_enabled() && ( ! is_object( $post ) || ! isset( $post->ID ) || ! et_pb_is_pagebuilder_used( $post->ID ) ) ) {';
                    if (!str_contains($updated, $first_replace)) {
                        $updated = str_replace(
                            'if ( et_builder_bfb_enabled() && ! et_pb_is_pagebuilder_used( $post->ID ) ) {',
                            $first_replace,
                            $updated
                        );
                    }

                    $second_replace = 'if ( ! $add && is_object( $post ) && isset( $post->ID ) && et_builder_enabled_for_post( $post->ID ) ) {';
                    if (!str_contains($updated, $second_replace)) {
                        $updated = str_replace(
                            'if ( ! $add && ! empty( $post ) && et_builder_enabled_for_post( $post->ID ) ) {',
                            $second_replace,
                            $updated
                        );
                    }

                    return $updated;
                },
            ],
            'divi_theme_builder_request_archive_guard' => [
                'file' => '/wp-content/themes/Divi/includes/builder/frontend-builder/theme-builder/ThemeBuilderRequest.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, 'if ( $object instanceof WP_Term && isset( $object->taxonomy ) ) {')) {
                        return $contents;
                    }

                    $search = <<<'PHP'
		if ( is_category() || is_tag() || is_tax() ) {
			return new self( self::TYPE_TERM, $object->taxonomy, $id );
		}

		if ( is_post_type_archive() ) {
			return new self( self::TYPE_POST_TYPE_ARCHIVE, $object->name, $id );
		}
PHP;

                    $replace = <<<'PHP'
		if ( is_category() || is_tag() || is_tax() ) {
			if ( $object instanceof WP_Term && isset( $object->taxonomy ) ) {
				return new self( self::TYPE_TERM, $object->taxonomy, $id );
			}

			if ( $object instanceof WP_Post_Type && isset( $object->name ) ) {
				return new self( self::TYPE_POST_TYPE_ARCHIVE, $object->name, $id );
			}
		}

		if ( is_post_type_archive() ) {
			$post_type = $object instanceof WP_Post_Type && isset( $object->name ) ? $object->name : get_query_var( 'post_type' );

			if ( is_array( $post_type ) ) {
				$post_type = reset( $post_type );
			}

			return new self( self::TYPE_POST_TYPE_ARCHIVE, (string) $post_type, $id );
		}
PHP;

                    return str_replace($search, $replace, $contents);
                },
            ],
            'divi_dynamic_assets_taxonomy_guard' => [
                'file' => '/wp-content/themes/Divi/includes/builder/feature/dynamic-assets/class-dynamic-assets.php',
                'apply' => static function (string $contents): string {
                    if (
                        str_contains($contents, '$queried_object   = get_queried_object();')
                        && str_contains($contents, '$queried instanceof WP_Term ? sanitize_key( $queried->taxonomy ) : \'\'')
                    ) {
                        return $contents;
                    }

                    $updated = str_replace(
                        "\t\tif ( \$this->is_taxonomy() ) {\n\t\t\t\$this->_object_id = intval( get_queried_object()->term_id );",
                        "\t\tif ( \$this->is_taxonomy() ) {\n\t\t\t\$queried_object   = get_queried_object();\n\t\t\t\$this->_object_id = \$queried_object instanceof WP_Term ? intval( \$queried_object->term_id ) : -1;",
                        $contents
                    );

                    $updated = str_replace(
                        "\t\tif ( \$this->is_taxonomy() ) {\n\t\t\t\$queried     = get_queried_object();\n\t\t\t\$taxonomy    = sanitize_key( \$queried->taxonomy );\n\t\t\t\$folder_name = \"taxonomy/{\$taxonomy}/\" . \$this->_object_id;",
                        "\t\tif ( \$this->is_taxonomy() ) {\n\t\t\t\$queried  = get_queried_object();\n\t\t\t\$taxonomy = \$queried instanceof WP_Term ? sanitize_key( \$queried->taxonomy ) : '';\n\n\t\t\tif ( '' === \$taxonomy ) {\n\t\t\t\treturn 'archive';\n\t\t\t}\n\n\t\t\t\$folder_name = \"taxonomy/{\$taxonomy}/\" . \$this->_object_id;",
                        $updated
                    );

                    return $updated;
                },
            ],
            'divi_postbased_term_id_guard' => [
                'file' => '/wp-content/themes/Divi/includes/builder/module/type/PostBased.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, '$queried_object = get_queried_object();')) {
                        return $contents;
                    }

                    $search = <<<'PHP'
					if ( $is_category || $is_tag || $is_tax ) {
						$term_ids[] = get_queried_object()->term_id;
					}
PHP;

                    $replace = <<<'PHP'
					if ( $is_category || $is_tag || $is_tax ) {
						$queried_object = get_queried_object();

						if ( $queried_object instanceof WP_Term ) {
							$term_ids[] = (int) $queried_object->term_id;
						}
					}
PHP;

                    return str_replace($search, $replace, $contents);
                },
            ],
            'toolset_content_template_taxonomy_guard' => [
                'file' => '/wp-views/vendor/toolset/toolset-common/user-editors/medium/screen/content-template/frontend.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, '$term instanceof WP_Term')) {
                        return $contents;
                    }

                    $updated = preg_replace(
                        "/if\\( \\$term && array_key_exists\\( 'views_template_loop_' \\. \\$term->taxonomy, \\$wpv_options \\) \\) \\{/",
                        "if (\n\t\t\t\t\t\$term instanceof WP_Term\n\t\t\t\t\t&& array_key_exists( 'views_template_loop_' . \$term->taxonomy, \$wpv_options )\n\t\t\t\t) {",
                        $contents,
                        1
                    );

                    return is_string($updated) ? $updated : $contents;
                },
            ],
            'toolset_archive_title_taxonomy_guard' => [
                'file' => '/wp-views/embedded/inc/functions-core-embedded.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, '$queried_object = get_queried_object();')) {
                        return $contents;
                    }

                    $search = <<<'PHP'
    } elseif ( is_tax() ) {
        $tax = get_taxonomy( get_queried_object()->taxonomy );
        /* translators: 1: Taxonomy singular name, 2: Current taxonomy term */
        $title = sprintf( __( '%1$s: %2$s' ), $tax->labels->singular_name, single_term_title( '', false ) );
    } else {
PHP;

                    $replace = <<<'PHP'
    } elseif ( is_tax() ) {
        $queried_object = get_queried_object();
        $tax = $queried_object instanceof WP_Term ? get_taxonomy( $queried_object->taxonomy ) : false;

        if ( $tax && isset( $tax->labels->singular_name ) ) {
            /* translators: 1: Taxonomy singular name, 2: Current taxonomy term */
            $title = sprintf( __( '%1$s: %2$s' ), $tax->labels->singular_name, single_term_title( '', false ) );
        } else {
            $title = __( 'Archives' );
        }
    } else {
PHP;

                    return str_replace($search, $replace, $contents);
                },
            ],
            'toolset_views_template_archive_term_guard' => [
                'file' => '/wp-views/embedded/inc/views-templates/wpv-template.class.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, '$kind = $term instanceof WP_Term ? \'archive-\' . $term->taxonomy : \'archive\';')) {
                        return $contents;
                    }

                    $updated = str_replace(
                        "\t\t\t\t\t\$term = \$wp_query->get_queried_object();\n\t\t\t\t\t\$kind = 'archive-' . \$term->taxonomy;",
                        "\t\t\t\t\t\$term = \$wp_query->get_queried_object();\n\t\t\t\t\t\$kind = \$term instanceof WP_Term ? 'archive-' . \$term->taxonomy : 'archive';",
                        $contents
                    );

                    $updated = str_replace(
                        "\t\t\t\t\t\$term = \$wp_query->get_queried_object();\n\t\t\t\t\t\$archive_loop = 'views_template_loop_' . \$term->taxonomy;",
                        "\t\t\t\t\t\$term = \$wp_query->get_queried_object();\n\t\t\t\t\t\$archive_loop = \$term instanceof WP_Term ? 'views_template_loop_' . \$term->taxonomy : null;",
                        $updated
                    );

                    $updated = str_replace(
                        "\t\t\t\t\t\$term = \$wp_query->get_queried_object();\n\t\t\t\t\tif( \$term ) {",
                        "\t\t\t\t\t\$term = \$wp_query->get_queried_object();\n\t\t\t\t\tif( \$term instanceof WP_Term ) {",
                        $updated
                    );

                    return $updated;
                },
            ],
            'toolset_parent_filter_term_guard' => [
                'file' => '/wp-views/embedded/inc/filters/wpv-filter-parent-embedded.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, '$parent_id = $queried_object instanceof WP_Term ? $queried_object->term_id : $parent_id;')) {
                        return $contents;
                    }

                    $search = <<<'PHP'
						$queried_object = get_queried_object();
						$parent_id = $queried_object->term_id;
PHP;

                    $replace = <<<'PHP'
						$queried_object = get_queried_object();
						$parent_id = $queried_object instanceof WP_Term ? $queried_object->term_id : $parent_id;
PHP;

                    return str_replace($search, $replace, $contents);
                },
            ],
            'yoast_indexable_hierarchy_term_guard' => [
                'file' => '/wordpress-seo/src/builders/indexable-hierarchy-builder.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, 'if ( ! ( $term instanceof \WP_Term ) ) {')) {
                        return $contents;
                    }

                    $search = <<<'PHP'
	private function get_term_parents( $term ) {
		$tax     = $term->taxonomy;
		$parents = [];
PHP;

                    $replace = <<<'PHP'
	private function get_term_parents( $term ) {
		if ( ! ( $term instanceof \WP_Term ) ) {
			return [];
		}

		$tax     = $term->taxonomy;
		$parents = [];
PHP;

                    $updated = str_replace($search, $replace, $contents);

                    $search = <<<'PHP'
			$term      = \get_term( $term->parent, $tax );
			$parents[] = $term;
PHP;

                    $replace = <<<'PHP'
			$term      = \get_term( $term->parent, $tax );

			if ( ! ( $term instanceof \WP_Term ) ) {
				break;
			}

			$parents[] = $term;
PHP;

                    $updated = str_replace($search, $replace, $updated);

                    return is_string($updated) ? $updated : $contents;
                },
            ],
            'yoast_current_page_term_id_guard' => [
                'file' => '/wordpress-seo/src/helpers/current-page-helper.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, '$queried_object instanceof \WP_Term')) {
                        return $contents;
                    }

                    $search = <<<'PHP'
		if ( $wp_query->is_tax() || $wp_query->is_tag() || $wp_query->is_category() ) {
			$queried_object = $wp_query->get_queried_object();
			if ( $queried_object && ! \is_wp_error( $queried_object ) ) {
				return $queried_object->term_id;
			}
		}
PHP;

                    $replace = <<<'PHP'
		if ( $wp_query->is_tax() || $wp_query->is_tag() || $wp_query->is_category() ) {
			$queried_object = $wp_query->get_queried_object();
			if ( $queried_object instanceof \WP_Term && ! \is_wp_error( $queried_object ) ) {
				return $queried_object->term_id;
			}
		}
PHP;

                    return str_replace($search, $replace, $contents);
                },
            ],
            'yoast_current_page_count_queried_terms_guard' => [
                'file' => '/wordpress-seo/src/helpers/current-page-helper.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, "! ( \$term instanceof \\WP_Term ) || empty( \$queried_terms[ \$term->taxonomy ]['terms'] )")) {
                        return $contents;
                    }

                    $search = <<<'PHP'
		$queried_terms = $wp_query->tax_query->queried_terms;
		if ( $term === null || empty( $queried_terms[ $term->taxonomy ]['terms'] ) ) {
			return 0;
		}
PHP;

                    $replace = <<<'PHP'
		$queried_terms = $wp_query->tax_query->queried_terms;
		if ( ! ( $term instanceof \WP_Term ) || empty( $queried_terms[ $term->taxonomy ]['terms'] ) ) {
			return 0;
		}
PHP;

                    return str_replace($search, $replace, $contents);
                },
            ],
            'yoast_term_archive_presentation_guard' => [
                'file' => '/wordpress-seo/src/presentations/indexable-term-archive-presentation.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, '$queried_object = \get_queried_object();')) {
                        return $contents;
                    }

                    $search = <<<'PHP'
	public function generate_source() {
		if ( ! empty( $this->model->object_id ) || \get_queried_object() === null ) {
			return \get_term( $this->model->object_id, $this->model->object_sub_type );
		}

		return \get_term( \get_queried_object()->term_id, \get_queried_object()->taxonomy );
	}
PHP;

                    $replace = <<<'PHP'
	public function generate_source() {
		$queried_object = \get_queried_object();

		if ( ! empty( $this->model->object_id ) || ! ( $queried_object instanceof \WP_Term ) ) {
			return \get_term( $this->model->object_id, $this->model->object_sub_type );
		}

		return \get_term( $queried_object->term_id, $queried_object->taxonomy );
	}
PHP;

                    return str_replace($search, $replace, $contents);
                },
            ],
            'yoast_crawl_cleanup_rss_term_guard' => [
                'file' => '/wordpress-seo/src/integrations/front-end/crawl-cleanup-rss.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, '$url  = ( $term instanceof \WP_Term ) ? \get_term_link( $term, $term->taxonomy ) : \home_url();')) {
                        return $contents;
                    }

                    $search = <<<'PHP'
				$term = \get_queried_object();
				$url  = \get_term_link( $term, $term->taxonomy );
				if ( \is_wp_error( $url ) ) {
PHP;

                    $replace = <<<'PHP'
				$term = \get_queried_object();
				$url  = ( $term instanceof \WP_Term ) ? \get_term_link( $term, $term->taxonomy ) : \home_url();
				if ( \is_wp_error( $url ) ) {
PHP;

                    return str_replace($search, $replace, $contents);
                },
            ],
            'yoast_feed_improvements_term_guard' => [
                'file' => '/wordpress-seo/src/integrations/front-end/feed-improvements.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, '$meta = $queried_object instanceof \WP_Term ? $this->meta->for_term( $queried_object->term_id ) : null;')) {
                        return $contents;
                    }

                    $search = <<<'PHP'
			case 'WP_Term':
				$meta = $this->meta->for_term( $queried_object->term_id );
				break;
PHP;

                    $replace = <<<'PHP'
			case 'WP_Term':
				$meta = $queried_object instanceof \WP_Term ? $this->meta->for_term( $queried_object->term_id ) : null;
				break;
PHP;

                    return str_replace($search, $replace, $contents);
                },
            ],
            'yoast_crawl_cleanup_helper_term_guard' => [
                'file' => '/wordpress-seo/src/helpers/crawl-cleanup-helper.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, "if ( ! ( \$term instanceof \\WP_Term ) ) {\n\t\t\treturn \\home_url();\n\t\t}")) {
                        return $contents;
                    }

                    $search = <<<'PHP'
	public function taxonomy_url() {
		global $wp_query;
		$term = $wp_query->get_queried_object();

		if ( \is_feed() ) {
			return \get_term_feed_link( $term->term_id, $term->taxonomy );
		}
		return \get_term_link( $term, $term->taxonomy );
	}
PHP;

                    $replace = <<<'PHP'
	public function taxonomy_url() {
		global $wp_query;
		$term = $wp_query->get_queried_object();

		if ( ! ( $term instanceof \WP_Term ) ) {
			return \home_url();
		}

		if ( \is_feed() ) {
			return \get_term_feed_link( $term->term_id, $term->taxonomy );
		}
		return \get_term_link( $term, $term->taxonomy );
	}
PHP;

                    return str_replace($search, $replace, $contents);
                },
            ],
            'wordpress_core_sanitize_term_object_guard' => [
                'file' => '/wp-includes/taxonomy.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, "if ( \$do_object && ! isset( \$term->term_id ) ) {\n\t\treturn \$term;\n\t}")) {
                        return $contents;
                    }

                    $search = <<<'PHP'
	$do_object = is_object( $term );

	$term_id = $do_object ? $term->term_id : ( isset( $term['term_id'] ) ? $term['term_id'] : 0 );
PHP;

                    $replace = <<<'PHP'
	$do_object = is_object( $term );

	if ( $do_object && ! isset( $term->term_id ) ) {
		return $term;
	}

	$term_id = $do_object ? $term->term_id : ( isset( $term['term_id'] ) ? $term['term_id'] : 0 );
PHP;

                    return str_replace($search, $replace, $contents);
                },
            ],
            'wordpress_core_pad_term_counts_empty_guard' => [
                'file' => '/wp-includes/taxonomy.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, "if ( empty( \$term_ids ) ) {\n\t\treturn;\n\t}")) {
                        return $contents;
                    }

                    $search = <<<'PHP'
	foreach ( (array) $terms as $key => $term ) {
		$terms_by_id[ $term->term_id ]       = & $terms[ $key ];
		$term_ids[ $term->term_taxonomy_id ] = $term->term_id;
	}

	// Get the object and term IDs and stick them in a lookup table.
PHP;

                    $replace = <<<'PHP'
	foreach ( (array) $terms as $key => $term ) {
		$terms_by_id[ $term->term_id ]       = & $terms[ $key ];
		$term_ids[ $term->term_taxonomy_id ] = $term->term_id;
	}

	if ( empty( $term_ids ) ) {
		return;
	}

	// Get the object and term IDs and stick them in a lookup table.
PHP;

                    return str_replace($search, $replace, $contents);
                },
            ],
            'gravity_wiz_submit_access_dynamic_args' => [
                'file' => '/gw-submit-to-access/gw-submit-to-access.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, 'private $_args = array();')) return $contents;

                    $eol = mz_plugin_compat_get_eol($contents);
                    return preg_replace_callback(
                        '/([ \t]*private static \$instance = null;\R)/',
                        static function (array $matches) use ($eol): string {
                            return $matches[1] . "\tprivate \$_args = array();" . $eol;
                        },
                        $contents,
                        1
                    ) ?: $contents;
                },
            ],
            'gravity_wiz_submit_access_null_post_guard' => [
                'file' => '/gw-submit-to-access/gw-submit-to-access.php',
                'apply' => static function (string $contents): string {
                    if (str_contains($contents, "if ( ! \$post || ! isset( \$post->ID ) ) {\n\t\t\treturn \$content;\n\t\t}")) {
                        return $contents;
                    }

                    $updated = preg_replace(
                        '/public function maybe_hide_the_content\( \$content \) \{\R\t\tglobal \$post;\R/',
                        "public function maybe_hide_the_content( \$content ) {\n\t\tglobal \$post;\n\n\t\tif ( ! \$post || ! isset( \$post->ID ) ) {\n\t\t\treturn \$content;\n\t\t}\n",
                        $contents,
                        1
                    );

                    return is_string($updated) ? $updated : $contents;
                },
            ],
            'custom_widget_area_declared_properties' => [
                'file' => '/wp-custom-widget-area/admin/class-wp-custom-widget-area-admin.php',
                'apply' => static function (string $contents): string {
                    if (
                        str_contains($contents, 'private $view;')
                        && str_contains($contents, 'private $menuView;')
                        && str_contains($contents, 'private $table_name;')
                    ) {
                        return $contents;
                    }

                    $eol = mz_plugin_compat_get_eol($contents);
                    return preg_replace_callback(
                        '/([ \t]*private \$version;\R)/',
                        static function (array $matches) use ($eol): string {
                            return $matches[1]
                                . "\tprivate \$view;" . $eol
                                . "\tprivate \$menuView;" . $eol
                                . "\tprivate \$table_name;" . $eol;
                        },
                        $contents,
                        1
                    ) ?: $contents;
                },
            ],
            'toolset_divi_view_render_signature' => [
                'file' => '/wp-views.deactivated/vendor/toolset/divi/includes/modules/View/View.php',
                'apply' => static function (string $contents): string {
                    $updated = preg_replace(
                        '/public function render\(\s*\$attrs,\s*\$content\s*=\s*null,\s*\$render_slug\s*\)/',
                        'public function render( $attrs, $content = null, $render_slug = \'\' )',
                        $contents,
                        1
                    );

                    return is_string($updated) ? $updated : $contents;
                },
            ],
            'aios_googlebot_prefixes_guard' => [
                'file' => '/all-in-one-wp-security-and-firewall/classes/wp-security-utility.php',
                'apply' => static function (string $contents): string {
                    $search = 'foreach ($json_array[\'prefixes\'] as $prefix) {';
                    $replace = 'foreach ((is_array($json_array[\'prefixes\'] ?? null) ? $json_array[\'prefixes\'] : []) as $prefix) {';

                    if (str_contains($contents, $replace)) {
                        return $contents;
                    }

                    $updated = str_replace($search, $replace, $contents, $count);

                    if ($count < 1) {
                        return $contents;
                    }

                    return $updated;
                },
            ],
        ];

        return apply_filters('mz_plugin_compat_source_patch_rules', $rules);
    }
}

if (!function_exists('mz_plugin_compat_apply_source_patches')) {
    function mz_plugin_compat_apply_source_patches(): void
    {
        foreach (mz_plugin_compat_get_source_patch_rules() as $rule) {
            if (!is_array($rule)) continue;

            $relative_file = (string) ($rule['file'] ?? '');
            $apply = $rule['apply'] ?? null;
            if ($relative_file === '' || !is_callable($apply)) continue;

            $target = mz_plugin_compat_resolve_source_patch_target($relative_file);
            if (!is_file($target) || !is_readable($target) || !is_writable($target)) continue;

            $contents = file_get_contents($target);
            if (!is_string($contents) || $contents === '') continue;

            $updated = $apply($contents);
            if (!is_string($updated) || $updated === $contents) continue;

            file_put_contents($target, $updated);
        }
    }
}

mz_plugin_compat_apply_source_patches();

add_filter('cron_schedules', static function (array $schedules): array {
    if (!isset($schedules['fifteen_minutes'])) {
        $schedules['fifteen_minutes'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => 'Every Fifteen Minutes',
        ];
    }

    return $schedules;
});

if (!function_exists('mz_plugin_compat_get_deprecated_notice_rules')) {
    function mz_plugin_compat_get_deprecated_notice_rules(): array
    {
        $rules = [
            'divi_booster_dynamic_package_slug' => [
                'message' => 'Creation of dynamic property wtfplugin_1_0::$package_slug is deprecated',
                'file' => '/wp-content/plugins/divi-booster/core/wtfplugin_1_0.class.php',
            ],
            'divi_booster_contact_form_email_blacklist_current_blacklist' => [
                'message' => 'Creation of dynamic property DBDB_ContactForm_EmailBlacklist::$current_blacklist is deprecated',
                'file' => '/wp-content/plugins/divi-booster/core/features/contactFormEmailBlacklist/dbdb-contactform-emailblacklist.php',
            ],
            'divi_booster_contact_form_email_blacklist_current_contact_form' => [
                'message' => 'Creation of dynamic property DBDB_ContactForm_EmailBlacklist::$current_contact_form is deprecated',
                'file' => '/wp-content/plugins/divi-booster/core/features/contactFormEmailBlacklist/dbdb-contactform-emailblacklist.php',
            ],
            'monsterinsights_site_health_tracking_property' => [
                'message' => 'Creation of dynamic property MonsterInsights_WP_Site_Health_Lite::$is_tracking is deprecated',
                'file' => '/wp-content/plugins/google-analytics-for-wordpress/lite/includes/admin/wp-site-health.php',
            ],
            'gravity_wiz_submit_access_dynamic_args' => [
                'message' => 'Creation of dynamic property GW_Submit_Access::$_args is deprecated',
                'file' => '/wp-content/plugins/gw-submit-to-access/gw-submit-to-access.php',
            ],
            'custom_widget_area_dynamic_view' => [
                'message' => 'Creation of dynamic property Custom_Widget_Area_Admin::$view is deprecated',
                'file' => '/wp-content/plugins/wp-custom-widget-area/admin/class-wp-custom-widget-area-admin.php',
            ],
            'custom_widget_area_dynamic_menu_view' => [
                'message' => 'Creation of dynamic property Custom_Widget_Area_Admin::$menuView is deprecated',
                'file' => '/wp-content/plugins/wp-custom-widget-area/admin/class-wp-custom-widget-area-admin.php',
            ],
            'custom_widget_area_dynamic_table_name' => [
                'message' => 'Creation of dynamic property Custom_Widget_Area_Admin::$table_name is deprecated',
                'file' => '/wp-content/plugins/wp-custom-widget-area/admin/class-wp-custom-widget-area-admin.php',
            ],
        ];

        return apply_filters('mz_plugin_compat_deprecated_notice_rules', $rules);
    }
}

if (!function_exists('mz_plugin_compat_matches_deprecated_notice')) {
    function mz_plugin_compat_matches_deprecated_notice(string $message, string $file, array $rule): bool
    {
        $expected_message = trim((string) ($rule['message'] ?? ''));
        $expected_file = mz_plugin_compat_normalize_path((string) ($rule['file'] ?? ''));
        $normalized_file = mz_plugin_compat_normalize_path($file);

        if ($expected_message === '' || stripos($message, $expected_message) === false) {
            return false;
        }

        if ($expected_file === '' || stripos($normalized_file, $expected_file) === false) {
            return false;
        }

        return true;
    }
}

if (!function_exists('mz_plugin_compat_is_known_nullable_param_deprecation')) {
    function mz_plugin_compat_is_known_nullable_param_deprecation(string $message, string $file): bool
    {
        $normalized_message = strtolower(trim($message));
        if (
            !str_contains($normalized_message, 'implicitly marking parameter')
            || !str_contains($normalized_message, 'nullable is deprecated')
        ) {
            return false;
        }

        $normalized_file = mz_plugin_compat_normalize_path($file);
        $known_prefixes = [
            '/wp-content/plugins/all-in-one-seo-pack/',
            '/wp-content/plugins/cred-frontend-editor/',
            '/wp-content/plugins/divi-booster/',
            '/wp-content/plugins/the-events-calendar/',
            '/wp-content/plugins/wp-views/',
            '/wp-content/plugins/types.deactivated/',
            '/wp-content/plugins/wp-views.deactivated/',
        ];

        foreach ($known_prefixes as $prefix) {
            if (str_contains($normalized_file, $prefix)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('mz_plugin_compat_should_suppress_deprecated_notice')) {
    function mz_plugin_compat_should_suppress_deprecated_notice(string $message, string $file): bool
    {
        if (mz_plugin_compat_is_known_nullable_param_deprecation($message, $file)) {
            return true;
        }

        foreach (mz_plugin_compat_get_deprecated_notice_rules() as $rule) {
            if (!is_array($rule)) continue;
            if (mz_plugin_compat_matches_deprecated_notice($message, $file, $rule)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('mz_plugin_compat_should_suppress_runtime_warning')) {
    function mz_plugin_compat_should_suppress_runtime_warning(string $message, string $file): bool
    {
        $normalized_file = mz_plugin_compat_normalize_path($file);

        if (!str_contains($normalized_file, '/wp-content/plugins/all-in-one-wp-security-and-firewall/classes/wp-security-utility.php')) {
            return false;
        }

        $normalized_message = trim($message);

        return str_contains($normalized_message, 'Undefined array key "prefixes"')
            || str_contains($normalized_message, 'foreach() argument must be of type array|object, null given');
    }
}

if (!function_exists('mz_plugin_compat_register_error_handler')) {
    function mz_plugin_compat_register_error_handler(): void
    {
        static $registered = false;

        if ($registered) return;
        $registered = true;

        $previous_handler = set_error_handler(
            static function ($errno, $errstr, $errfile = '', $errline = 0) use (&$previous_handler) {
                $is_deprecated = ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED);
                $is_warning = in_array($errno, [E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE], true);

                if (
                    $is_deprecated
                    && mz_plugin_compat_should_suppress_deprecated_notice((string) $errstr, (string) $errfile)
                ) {
                    return true;
                }

                if (
                    $is_warning
                    && mz_plugin_compat_should_suppress_runtime_warning((string) $errstr, (string) $errfile)
                ) {
                    return true;
                }

                if (is_callable($previous_handler)) {
                    return (bool) call_user_func($previous_handler, $errno, $errstr, $errfile, $errline);
                }

                return false;
            },
            E_DEPRECATED | E_USER_DEPRECATED | E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE
        );
    }
}

mz_plugin_compat_register_error_handler();

add_filter('doing_it_wrong_trigger_error', function ($trigger, $function_name, $message, $version) {
    if (!$trigger) return $trigger;
    if ((string) $function_name !== '_load_textdomain_just_in_time') return $trigger;

    $message = strtolower(strip_tags((string) $message));
    if (strpos($message, 'divi-booster') === false) return $trigger;

    return false;
}, 10, 4);

if (!function_exists('mz_plugin_compat_override_single_event_template_include')) {
    function mz_plugin_compat_override_single_event_template_include($template)
    {
        if (is_admin() || !is_singular('tribe_events')) {
            return $template;
        }

        $override = __DIR__ . '/overrides/plugin-compat/the-events-calendar/single-tribe_events.php';

        return is_file($override) ? $override : $template;
    }
}

if (!function_exists('mz_plugin_compat_disable_divi_theme_builder_for_single_events')) {
    function mz_plugin_compat_disable_divi_theme_builder_for_single_events($layouts)
    {
        if (is_admin() || !is_singular('tribe_events') || !is_array($layouts)) {
            return $layouts;
        }

        $layout_types = [];

        if (defined('ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE')) {
            $layout_types[] = ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE;
        }

        if (defined('ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE')) {
            $layout_types[] = ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE;
        }

        if (defined('ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE')) {
            $layout_types[] = ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE;
        }

        foreach ($layout_types as $layout_type) {
            if (!isset($layouts[$layout_type]) || !is_array($layouts[$layout_type])) {
                continue;
            }

            $layouts[$layout_type]['id'] = 0;
            $layouts[$layout_type]['enabled'] = false;
            $layouts[$layout_type]['override'] = false;
        }

        return $layouts;
    }
}

if (!function_exists('mz_plugin_compat_strip_divi_builder_body_classes_for_single_events')) {
    function mz_plugin_compat_strip_divi_builder_body_classes_for_single_events(array $classes): array
    {
        if (is_admin() || !is_singular('tribe_events')) {
            return $classes;
        }

        $remove = [
            'et-db',
            'et-tb-has-template',
            'et-tb-has-footer',
            'et-tb-has-header',
            'et_pb_pagebuilder_layout',
        ];

        return array_values(array_diff($classes, $remove));
    }
}

if (!function_exists('mz_plugin_compat_dequeue_divi_builder_assets_for_single_events')) {
    function mz_plugin_compat_dequeue_divi_builder_assets_for_single_events(): void
    {
        if (is_admin() || !is_singular('tribe_events')) {
            return;
        }

        $script_handles = [
            'et-builder-cpt-modules-wrapper-js',
            'fitvids-js',
        ];

        foreach ($script_handles as $handle) {
            wp_dequeue_script($handle);
            wp_deregister_script($handle);
        }

        global $wp_styles;

        if (!($wp_styles instanceof WP_Styles)) {
            return;
        }

        foreach ((array) $wp_styles->queue as $handle) {
            if (strpos((string) $handle, 'et-builder-module-design-') !== 0) {
                continue;
            }

            wp_dequeue_style($handle);
        }
    }
}

if (!function_exists('mz_plugin_compat_get_safe_tec_venue_object')) {
    function mz_plugin_compat_get_safe_tec_venue_object($venue): ?WP_Post
    {
        if (!function_exists('tribe_get_venue_object')) {
            return null;
        }

        $venue_id = 0;

        if ($venue instanceof WP_Post) {
            $venue_id = (int) $venue->ID;
        } elseif (is_object($venue) && isset($venue->ID)) {
            $venue_id = (int) $venue->ID;
        } elseif (is_scalar($venue)) {
            $venue_id = absint($venue);
        }

        if ($venue_id <= 0) {
            return null;
        }

        $normalized_venue = tribe_get_venue_object($venue_id);

        return $normalized_venue instanceof WP_Post ? $normalized_venue : null;
    }
}

if (!function_exists('mz_plugin_compat_render_tec_venue_html')) {
    function mz_plugin_compat_render_tec_venue_html($event, string $slug, array $config): string
    {
        $config = wp_parse_args($config, [
            'wrapper_class' => '',
            'title_class' => '',
            'address_class' => '',
            'include_city' => true,
            'include_country' => false,
            'include_after_action' => false,
        ]);

        if (!is_object($event) || !isset($event->venues) || !is_object($event->venues) || !method_exists($event->venues, 'count')) {
            return '';
        }

        if ((int) $event->venues->count() < 1) {
            return '';
        }

        $venue = mz_plugin_compat_get_safe_tec_venue_object($event->venues[0] ?? null);
        if (!($venue instanceof WP_Post)) {
            return '';
        }

        $separator = esc_html_x(', ', 'Address separator', 'the-events-calendar');
        $state_parts = array_values(array_filter(array_map('trim', array_filter([
            $venue->state_province ?? null,
            $venue->state ?? null,
            $venue->province ?? null,
        ], static fn($value): bool => !is_array($value)))));
        $state_part = $state_parts[0] ?? '';
        $city = trim((string) ($venue->city ?? ''));
        $country = trim((string) ($venue->country ?? ''));
        $address = trim((string) ($venue->address ?? ''));

        $address_line = $address;

        if (!post_password_required($venue->ID)) {
            if (!empty($config['include_city'])) {
                if ($address_line !== '' && ($city !== '' || $state_part !== '')) {
                    $address_line .= $separator;
                }

                if ($city !== '') {
                    $address_line .= $city;
                }

                if ($state_part !== '') {
                    if ($address_line !== '' && $city !== '') {
                        $address_line .= $separator;
                    }

                    $address_line .= $state_part;
                }
            } elseif ($state_part !== '') {
                if ($address_line !== '') {
                    $address_line .= $separator;
                }

                $address_line .= $state_part;
            }

            if (!empty($config['include_country']) && $country !== '') {
                if ($address_line !== '') {
                    $address_line .= $separator;
                }

                $address_line .= $country;
            }
        } else {
            $address_line = '';
        }

        ob_start();
        ?>
<address class="<?php echo esc_attr((string) $config['wrapper_class']); ?>">
    <span class="<?php echo esc_attr((string) $config['title_class']); ?>">
        <?php echo wp_kses_post($venue->post_title); ?>
    </span>
    <span class="<?php echo esc_attr((string) $config['address_class']); ?>">
        <?php echo esc_html($address_line); ?>
    </span>
    <?php
        if (!empty($config['include_after_action'])) {
            do_action('tec_events_view_venue_after_address', $event, $slug);
        }
    ?>
</address>
        <?php

        return (string) ob_get_clean();
    }
}

if (!function_exists('mz_plugin_compat_render_safe_tec_venue_template_html')) {
    function mz_plugin_compat_render_safe_tec_venue_template_html($html, $file, $name, $template, array $config, array $context = []): ?string
    {
        unset($file, $name);

        $existing_html = is_string($html) ? $html : null;

        if (!is_object($template) || !method_exists($template, 'get')) {
            return $existing_html;
        }

        $event = $context['event'] ?? $template->get('event');
        $slug = isset($context['slug']) ? (string) $context['slug'] : (string) $template->get('slug', '');
        $rendered = mz_plugin_compat_render_tec_venue_html($event, $slug, $config);

        return $rendered !== '' ? $rendered : $existing_html;
    }
}

add_filter('template_include', 'mz_plugin_compat_override_single_event_template_include', 999);
add_filter('et_theme_builder_template_layouts', 'mz_plugin_compat_disable_divi_theme_builder_for_single_events', 20);
add_filter('body_class', 'mz_plugin_compat_strip_divi_builder_body_classes_for_single_events', 20);
add_action('wp_enqueue_scripts', 'mz_plugin_compat_dequeue_divi_builder_assets_for_single_events', 999);
add_filter('tribe_template_pre_html:events/v2/list/event/venue', static function ($html, $file, $name, $template, $context = []) {
    return mz_plugin_compat_render_safe_tec_venue_template_html($html, $file, $name, $template, [
        'wrapper_class' => 'tribe-events-calendar-list__event-venue tribe-common-b2',
        'title_class' => 'tribe-events-calendar-list__event-venue-title tribe-common-b2--bold',
        'address_class' => 'tribe-events-calendar-list__event-venue-address',
        'include_city' => true,
        'include_country' => true,
        'include_after_action' => true,
    ], is_array($context) ? $context : []);
}, 20, 5);
add_filter('tribe_template_pre_html:events/v2/day/event/venue', static function ($html, $file, $name, $template, $context = []) {
    return mz_plugin_compat_render_safe_tec_venue_template_html($html, $file, $name, $template, [
        'wrapper_class' => 'tribe-events-calendar-day__event-venue tribe-common-b2',
        'title_class' => 'tribe-events-calendar-day__event-venue-title tribe-common-b2--bold',
        'address_class' => 'tribe-events-calendar-day__event-venue-address',
        'include_city' => true,
        'include_country' => false,
        'include_after_action' => true,
    ], is_array($context) ? $context : []);
}, 20, 5);
add_filter('tribe_template_pre_html:events/v2/latest-past/event/venue', static function ($html, $file, $name, $template, $context = []) {
    return mz_plugin_compat_render_safe_tec_venue_template_html($html, $file, $name, $template, [
        'wrapper_class' => 'tribe-events-calendar-latest-past__event-venue tribe-common-b2',
        'title_class' => 'tribe-events-calendar-latest-past__event-venue-title tribe-common-b2--bold',
        'address_class' => 'tribe-events-calendar-latest-past__event-venue-address',
        'include_city' => true,
        'include_country' => false,
        'include_after_action' => false,
    ], is_array($context) ? $context : []);
}, 20, 5);
