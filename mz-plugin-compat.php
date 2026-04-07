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
                    $updated = preg_replace(
                        '/\$enabled = \$post \? et_builder_enabled_for_post\( \$post->ID \) : et_builder_enabled_for_post_type\( \$post_type \ );/',
                        '$enabled = ( is_object( $post ) && isset( $post->ID ) ) ? et_builder_enabled_for_post( $post->ID ) : et_builder_enabled_for_post_type( $post_type );',
                        $contents,
                        1
                    );

                    return is_string($updated) ? $updated : $contents;
                },
            ],
            'divi_builder_metabox_post_id_guard' => [
                'file' => '/wp-content/themes/Divi/includes/builder/functions.php',
                'apply' => static function (string $contents): string {
                    $updated = preg_replace(
                        '/if \( et_builder_bfb_enabled\(\) && ! et_pb_is_pagebuilder_used\( \$post->ID \) \) \{/',
                        'if ( et_builder_bfb_enabled() && ( ! is_object( $post ) || ! isset( $post->ID ) || ! et_pb_is_pagebuilder_used( $post->ID ) ) ) {',
                        $contents,
                        1
                    );

                    if (!is_string($updated)) {
                        $updated = $contents;
                    }

                    $updated = preg_replace(
                        '/if \( ! \$add && ! empty\( \$post \) && et_builder_enabled_for_post\( \$post->ID \) \ ) \{/',
                        'if ( ! $add && is_object( $post ) && isset( $post->ID ) && et_builder_enabled_for_post( $post->ID ) ) {',
                        $updated,
                        1
                    );

                    return is_string($updated) ? $updated : $contents;
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
        ];

        return apply_filters('mz_plugin_compat_source_patch_rules', $rules);
    }
}

if (!function_exists('mz_plugin_compat_apply_source_patches')) {
    function mz_plugin_compat_apply_source_patches(): void
    {
        $plugin_dir = defined('WP_PLUGIN_DIR')
            ? rtrim((string) WP_PLUGIN_DIR, '/\\')
            : rtrim(ABSPATH, '/\\') . '/wp-content/plugins';

        foreach (mz_plugin_compat_get_source_patch_rules() as $rule) {
            if (!is_array($rule)) continue;

            $relative_file = (string) ($rule['file'] ?? '');
            $apply = $rule['apply'] ?? null;
            if ($relative_file === '' || !is_callable($apply)) continue;

            $target = $plugin_dir . '/' . ltrim($relative_file, '/\\');
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

if (!function_exists('mz_plugin_compat_register_error_handler')) {
    function mz_plugin_compat_register_error_handler(): void
    {
        static $registered = false;

        if ($registered) return;
        $registered = true;

        $previous_handler = set_error_handler(
            static function ($errno, $errstr, $errfile = '', $errline = 0) use (&$previous_handler) {
                $is_deprecated = ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED);

                if (
                    $is_deprecated
                    && mz_plugin_compat_should_suppress_deprecated_notice((string) $errstr, (string) $errfile)
                ) {
                    return true;
                }

                if (is_callable($previous_handler)) {
                    return (bool) call_user_func($previous_handler, $errno, $errstr, $errfile, $errline);
                }

                return false;
            },
            E_DEPRECATED | E_USER_DEPRECATED
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
