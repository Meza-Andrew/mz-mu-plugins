<?php

/**
 * Plugin Name: MZ Performance
 * Description: Front-end asset, markup, and performance optimizations.
 * Version: 1.1.0
 * Author: Meza LLC
 * Author URI: https://meza.design
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) return;

/** ================================
 *  THIRD-PARTY OUTPUT CONTROL
 *  ================================ */

/** Site Kit: disable plugin-managed analytics output so tracking stays under theme control. */
add_filter('googlesitekit_enable_analytics', '__return_false');

/** Site Kit can register late, so remove its head output after normal plugins have loaded. */
add_action('plugins_loaded', function () {
    remove_action('wp_head', 'wp_sitekit_analytics_inline_script', 10);
}, 20);

/** Defensive dequeue in case Site Kit still enqueues gtag. */
add_action('wp_enqueue_scripts', function () {
    wp_dequeue_script('google_gtagjs');
}, 100);

/** Remove Site Kit's JS bootstrap from the document head on front-end requests. */
add_action('wp_head', function () {
    remove_action('wp_head', 'wp_sitekit_analytics_js', 1);
}, 1);

/** ================================
 *  RESOURCE HINTS AND MARKUP
 *  ================================ */

/** Head cleanup: remove unneeded DNS prefetch entries that WordPress adds by default. */
add_filter('wp_resource_hints', function (array $urls, string $relation): array {
    if ($relation !== 'dns-prefetch') {
        return $urls;
    }

    $urls = array_filter($urls, function ($url) {
        return strpos($url, 's.w.org') === false;
    });
    $dns_prefetch_urls = [];
    return array_merge($urls, $dns_prefetch_urls);
}, 10, 2);

/** Markup cleanup: simplify generated style tags for cleaner front-end HTML. */
add_filter('style_loader_tag', function (string $tag, string $handle): string {
    return str_replace(
        ['id="{$handle}-css"', " type='text/css'", " />", "  "],
        ['', '', '>', ' '],
        str_replace("'", '"', $tag)
    );
}, 10, 2);

/** ================================
 *  LEGACY THEME STYLE PRUNING
 *  ================================ */

/** Return true when the request is using the newer theme stack and should keep its own styles untouched. */
function meza_should_skip_legacy_style_pruning(): bool
{
    return function_exists('mz_use_new_theme') && mz_use_new_theme();
}

/** Remove a common block-editor stylesheet that the legacy theme does not rely on on the front end. */
function meza_dequeue_block_library_css(): void
{
    if (is_admin() || meza_should_skip_legacy_style_pruning()) {
        return;
    }

    wp_dequeue_style('wp-components');
    wp_deregister_style('wp-components');
}
add_action('wp_enqueue_scripts', 'meza_dequeue_block_library_css', 100);

/** Remove block styles printed outside normal enqueue flow. */
add_action('wp_print_styles', function (): void {
    if (meza_should_skip_legacy_style_pruning()) {
        return;
    }

    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
});

/** Remove theme.json and classic-theme compatibility styles on the legacy theme. */
add_action('wp_enqueue_scripts', function (): void {
    if (meza_should_skip_legacy_style_pruning()) {
        return;
    }

    wp_dequeue_style('global-styles');
    wp_dequeue_style('classic-theme-styles');
});

/** ================================
 *  CACHE-FRIENDLY ASSET URLS
 *  ================================ */

/** Asset policy: strip query strings in production-like environments for cache-friendly URLs. */
if (in_array(strtolower((string) (defined('WP_ENV') ? WP_ENV : 'production')), ['production', 'qa'], true)) {
    add_filter('script_loader_src', 'meza_remove_query_strings', 15, 1);
    add_filter('style_loader_src', 'meza_remove_query_strings', 15, 1);
}

/** Strip the version query string from a CSS or JS URL so cache layers see a cleaner asset path. */
function meza_remove_query_strings($src)
{
    return strpos($src, '?') ? substr($src, 0, strpos($src, '?')) : $src;
}
