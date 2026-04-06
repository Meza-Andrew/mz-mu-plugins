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

if (!function_exists('mz_plugin_compat_should_suppress_deprecated_notice')) {
    function mz_plugin_compat_should_suppress_deprecated_notice(string $message, string $file): bool
    {
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
