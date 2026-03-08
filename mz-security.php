<?php

/**
 * Plugin Name: DS Security
 * Description: Site-wide security restrictions and content access policies.
 * Version: 1.1.0
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
