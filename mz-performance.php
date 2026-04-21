<?php

/**
 * Plugin Name: DS Performance
 * Description: Front-end asset, markup, and performance optimizations.
 * Version: 1.1.1
 * Author: Meza LLC
 * Author URI: https://meza.design
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) return;

/** Normalize loose config values into a boolean. */
function meza_performance_truthy($value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if (is_numeric($value)) {
        return ((int) $value) !== 0;
    }

    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

/** Return the current environment in a normalized form. */
function meza_current_environment(): string
{
    return strtolower(trim((string) (defined('WP_ENV') ? WP_ENV : 'production')));
}

/** Only allow the navigation lock in staging-style review environments. */
function meza_can_lock_frontend_navigation(): bool
{
    return in_array(meza_current_environment(), ['staging', 'qa'], true);
}

/** Decide whether front-end navigation should be disabled for the current site. */
function meza_should_lock_frontend_navigation(): bool
{
    if (!meza_can_lock_frontend_navigation()) {
        return false;
    }

    return meza_performance_truthy(apply_filters('theme_lock_frontend_navigation', false));
}

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

/** Strip the version query string from a local CSS or JS URL so cache layers see a cleaner asset path. */
function meza_remove_query_strings($src)
{
    $asset_host = wp_parse_url($src, PHP_URL_HOST);
    $site_host = wp_parse_url(home_url(), PHP_URL_HOST);

    // External URLs can depend on query strings for the actual asset definition, like Google Fonts.
    if (!empty($asset_host) && !empty($site_host) && strtolower((string) $asset_host) !== strtolower((string) $site_host)) {
        return $src;
    }

    return strpos($src, '?') ? substr($src, 0, strpos($src, '?')) : $src;
}

/** Replace front-end page links with "#" during staging/QA reviews to prevent unfinished page navigation. */
add_action('wp_enqueue_scripts', function (): void {
    if (is_admin() || is_customize_preview() || !meza_should_lock_frontend_navigation()) {
        return;
    }

    wp_register_script('meza-lock-frontend-navigation', false, [], null, true);
    wp_enqueue_script('meza-lock-frontend-navigation');
    wp_add_inline_script('meza-lock-frontend-navigation', <<<'JS'
(() => {
    const isSkippableHref = (href) => {
        if (!href) {
            return true;
        }

        const normalized = href.trim().toLowerCase();
        return normalized === ''
            || normalized === '#'
            || normalized.startsWith('#')
            || normalized.startsWith('mailto:')
            || normalized.startsWith('tel:')
            || normalized.startsWith('sms:')
            || normalized.startsWith('javascript:');
    };

    const isAllowedLink = (link) => {
        if (!(link instanceof HTMLAnchorElement)) {
            return true;
        }

        if (link.dataset.mezaAllowNavigation === '1') {
            return true;
        }

        if (link.closest('#wpadminbar')) {
            return true;
        }

        if (link.hasAttribute('download')) {
            return true;
        }

        const target = (link.getAttribute('target') || '').trim().toLowerCase();
        if (target !== '' && target !== '_self') {
            return true;
        }

        const rawHref = link.getAttribute('href') || '';
        if (isSkippableHref(rawHref)) {
            return true;
        }

        try {
            const url = new URL(rawHref, window.location.href);
            if (!/^https?:$/.test(url.protocol)) {
                return true;
            }

            return url.origin !== window.location.origin;
        } catch (error) {
            return true;
        }
    };

    const lockLink = (link) => {
        if (isAllowedLink(link) || link.dataset.mezaNavigationLocked === '1') {
            return;
        }

        const href = link.getAttribute('href');
        if (!href) {
            return;
        }

        link.dataset.mezaOriginalHref = href;
        link.dataset.mezaNavigationLocked = '1';
        link.setAttribute('href', '#');
        link.setAttribute('aria-disabled', 'true');
    };

    const lockLinks = (root = document) => {
        root.querySelectorAll('a[href]').forEach(lockLink);
    };

    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!(link instanceof HTMLAnchorElement)) {
            return;
        }

        lockLink(link);
        if (link.dataset.mezaNavigationLocked === '1') {
            event.preventDefault();
        }
    }, true);

    if ('MutationObserver' in window) {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (!(node instanceof Element)) {
                        return;
                    }

                    if (node.matches('a[href]')) {
                        lockLink(node);
                    }

                    lockLinks(node);
                });
            });
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => lockLinks(), { once: true });
    } else {
        lockLinks();
    }
})();
JS);
}, 100);
