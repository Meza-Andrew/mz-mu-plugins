<?php

/**
 * Plugin Name: MZ Security
 * Description: Site-wide security restrictions and content access policies.
 * Version: 1.1.2
 * Author: Meza LLC
 * Author URI: https://meza.design
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) return;

/** ================================
 *  FEEDS AND HEAD CLEANUP
 *  ================================ */

/** Feed policy: redirect legacy RSS endpoints back to the homepage. */
foreach (['do_feed_rss2', 'do_feed_rss2_comments'] as $feed_action) {
    add_action($feed_action, function () {
        wp_redirect(home_url(), 301);
        exit;
    }, 1);
}

/** Core head cleanup: remove links and metadata this site does not use. */
remove_action('wp_head', 'feed_links', 2);
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'rest_output_link_wp_head');
remove_action('wp_head', 'wp_shortlink_wp_head');

/** ================================
 *  ACCESS HARDENING
 *  ================================ */

/** Disable XML-RPC globally. */
add_filter('xmlrpc_enabled', function () {
    return false;
});

/** Authentication hardening: avoid revealing whether login credentials were invalid. */
add_filter('login_errors', function () {
    return null;
});

if (!function_exists('meza_should_redirect_cms_shell_homepage')) {
    function meza_should_redirect_cms_shell_homepage(): bool
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return false;
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host);

        if ($host !== 'cms.investupfxbg.com') {
            return false;
        }

        $request_uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
        $query = (string) wp_parse_url($request_uri, PHP_URL_QUERY);
        $query_string = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));

        if ($path !== '' && $path !== '/') {
            return false;
        }

        if ($query !== '' || $query_string !== '') {
            return false;
        }

        return true;
    }
}

add_action('template_redirect', function (): void {
    if (!meza_should_redirect_cms_shell_homepage()) {
        return;
    }

    wp_safe_redirect('https://investupfxbg.com/', 302, 'Meza Headless Shell');
    exit;
}, 0);

/** ================================
 *  CONTENT POLICY
 *  ================================ */

/** Content policy: disable comments and pings site-wide. */
add_filter('comments_open', function () {
    return false;
}, 10, 2);
add_filter('pings_open', function () {
    return false;
}, 10, 2);
add_filter('comments_array', function () {
    return [];
}, 20, 2);

function meza_disable_comments_for_post_type(string $post_type): void
{
    $post_type = sanitize_key($post_type);
    if ($post_type === '' || !post_type_exists($post_type)) {
        return;
    }

    remove_post_type_support($post_type, 'comments');
    remove_post_type_support($post_type, 'trackbacks');
}

add_action('registered_post_type', function ($post_type): void {
    meza_disable_comments_for_post_type((string) $post_type);
}, 1000, 1);

add_action('init', function (): void {
    foreach (get_post_types([], 'names') as $post_type) {
        meza_disable_comments_for_post_type((string) $post_type);
    }
}, 1000);
