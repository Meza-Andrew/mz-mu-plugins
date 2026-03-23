<?php

/**
 * Plugin Name: MZ Theme Config
 * Description: Loads client child theme config and maps it into Meza Starter filters.
 * Version: 1.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

function mz_load_active_theme_config(): array
{
    $stylesheet = (string) get_option('stylesheet');
    if ($stylesheet === '') {
        return [];
    }

    $config_path = WP_CONTENT_DIR . '/themes/' . $stylesheet . '/inc/theme-config.php';
    if (!is_readable($config_path)) {
        return [];
    }

    $config = require $config_path;
    return is_array($config) ? $config : [];
}

function mz_theme_config_value(array $config, string $key, $default = null)
{
    return array_key_exists($key, $config) ? $config[$key] : $default;
}

$mz_theme_config = mz_load_active_theme_config();

add_filter('production_url', fn($default) => defined('MZ_PROD_URL') ? MZ_PROD_URL : mz_theme_config_value($mz_theme_config, 'prod_url', $default));
add_filter('theme_env', fn($default) => defined('WP_ENV') ? WP_ENV : $default);
add_filter('theme_version', fn($default) => defined('MZ_THEME_VERSION') ? MZ_THEME_VERSION : $default);
add_filter('theme_abbr', fn($default) => defined('MZ_THEME_ABBR') ? MZ_THEME_ABBR : mz_theme_config_value($mz_theme_config, 'abbr', $default));
add_filter('theme_ga_id', fn() => defined('MZ_GA_ID') ? MZ_GA_ID : mz_theme_config_value($mz_theme_config, 'ga_id', ''));
add_filter('theme_gfonts_url', fn() => defined('MZ_GFONTS_URL') ? MZ_GFONTS_URL : mz_theme_config_value($mz_theme_config, 'gfonts_url', ''));
add_filter('theme_fontawesome_icons', fn($default) => defined('MZ_FONTAWESOME_ICONS') ? MZ_FONTAWESOME_ICONS : mz_theme_config_value($mz_theme_config, 'fontawesome_icons', $default));
add_filter('theme_gcloud_key', fn() => defined('MZ_GCLOUD_KEY') ? MZ_GCLOUD_KEY : mz_theme_config_value($mz_theme_config, 'gcloud_key', ''));
add_filter('theme_grecaptcha_key', fn() => defined('MZ_GRECAPTCHA_SITE_KEY') ? MZ_GRECAPTCHA_SITE_KEY : mz_theme_config_value($mz_theme_config, 'grecaptcha_key', ''));
add_filter('theme_gmaps_key', fn() => defined('MZ_GMAPS_KEY') ? MZ_GMAPS_KEY : mz_theme_config_value($mz_theme_config, 'gmaps_key', mz_theme_config_value($mz_theme_config, 'gcloud_key', '')));

add_filter('theme_page_slugs', function ($slugs) use ($mz_theme_config) {
    return array_merge($slugs, mz_theme_config_value($mz_theme_config, 'additional_slugs', []));
});

add_filter('theme_page_templates', function ($templates) use ($mz_theme_config) {
    return array_merge($templates, mz_theme_config_value($mz_theme_config, 'additional_templates', []));
});

add_filter('theme_assets', function ($assets) use ($mz_theme_config) {
    $additional_assets = mz_theme_config_value($mz_theme_config, 'additional_assets', []);
    $assets['conditional'] = $assets['conditional'] ?? [];

    foreach ($additional_assets as $key => $data) {
        if (!empty($data['assets'])) {
            $assets['conditional'][$key] = [
                'type' => $data['type'],
                'assets' => $data['assets'],
            ];
        }
    }

    return $assets;
});
