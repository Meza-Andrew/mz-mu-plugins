<?php

/**
 * Plugin Name: DS WooCommerce
 * Description: WooCommerce query rules, asset loading, and storefront behavior.
 * Version: 1.1.0
 * Author: Meza LLC
 * Author URI: https://meza.design
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) return;

/** ================================
 *  WOO GUARDS
 *  ================================ */

/** True when WooCommerce is active and its front-end conditionals are available. */
if (!function_exists('mz_has_woo')) {
    function mz_has_woo(): bool
    {
        return class_exists('WooCommerce') && function_exists('is_woocommerce');
    }
}

/** Safely evaluate a Woo conditional without fataling when Woo is missing. */
if (!function_exists('mz_woo_cond')) {
    function mz_woo_cond($fn): bool
    {
        if (!mz_has_woo()) return false;
        return (bool) (is_callable($fn) ? call_user_func($fn) : (function_exists($fn) ? $fn() : false));
    }
}

/** Decide whether the current request is a real store page that still needs WooCommerce CSS and JS. */
function meza_needs_woo_assets(): bool
{
    return mz_woo_cond('is_product')
        || mz_woo_cond('is_cart')
        || mz_woo_cond('is_checkout')
        || mz_woo_cond('is_shop')
        || mz_woo_cond('is_product_taxonomy')
        || mz_woo_cond('is_account_page');
}

/** Confirm WooCommerce is active as a normal plugin before registering Woo-specific cleanup rules. */
function meza_is_woocommerce_plugin_active(): bool
{
    return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')), true);
}

/** ================================
 *  QUERY RULES
 *  ================================ */

/** Let Woo control product archive ordering instead of Intuitive Custom Post Order. */
add_action('pre_get_posts', function ($q) {
    if (!mz_has_woo() || is_admin() || !$q->is_main_query()) return;

    if (
        mz_woo_cond('is_shop')
        || (mz_woo_cond('is_post_type_archive') && $q->get('post_type') === 'product')
        || mz_woo_cond('is_product_taxonomy')
    ) {
        if (class_exists('Hicpo')) {
            remove_action('pre_get_posts', ['Hicpo', 'hicpo_pre_get_posts'], 10);
        }
    }
}, 1);

/** Use popularity-first sorting on product archive queries. */
add_action('woocommerce_product_query', function ($q) {
    if (!mz_has_woo() || is_admin() || !$q->is_main_query()) return;
    if (!(mz_woo_cond('is_shop') || mz_woo_cond('is_product_category') || mz_woo_cond('is_product_taxonomy'))) return;

    $q->set('meta_key', 'total_sales');
    $q->set('orderby', [
        'meta_value_num' => 'DESC',
        'date' => 'DESC',
        'title' => 'ASC',
        'ID' => 'ASC',
    ]);
});

/** Match shortcode product ordering with archive ordering. */
add_filter('woocommerce_shortcode_products_query', function ($args, $atts, $type) {
    if ($type !== 'products') return $args;

    $args['meta_key'] = 'total_sales';
    $args['orderby'] = [
        'meta_value_num' => 'DESC',
        'date' => 'DESC',
        'title' => 'ASC',
        'ID' => 'ASC',
    ];
    return $args;
}, 10, 3);

/** ================================
 *  ASSET PRUNING HELPERS
 *  ================================ */

/** Return the core WooCommerce asset handles that can be removed on non-store pages without breaking checkout flows. */
function meza_woocommerce_prunable_asset_handles(): array
{
    return [
        'styles' => [
            'woocommerce-general',
            'woocommerce-layout',
            'woocommerce-smallscreen',
            'woocommerce-inline',
            'wc-blocks-style',
            'wc-blocks-vendors-style',
            'wc-blocks-style-all-products',
            'wc-blocks-packages-style',
        ],
        'scripts' => [
            'jquery',
            'jquery-core',
            'jquery-migrate',
            'woocommerce',
            'wc-add-to-cart',
            'wc-add-to-cart-variation',
            'wc-single-product',
            'wc-cart',
            'wc-checkout',
            'wc-geolocation',
            'wc-price-slider',
            'wc-blocks-vendors',
            'wc-blocks',
            'wc-order-attribution',
            'sourcebuster-js',
        ],
    ];
}

/** Catch asset handles and URLs that obviously belong to Woo so late-queued files can still be removed. */
function meza_should_strip_woo_asset(string $handle, string $src = ''): bool
{
    return (
        strpos($handle, 'woocommerce') !== false ||
        strpos($handle, 'wc-') === 0 ||
        strpos($handle, 'woo-') === 0 ||
        strpos($src, '/woocommerce/') !== false ||
        strpos($src, '/wc-') !== false
    );
}

/** Remove Woo's most common front-end scripts that are often loaded site-wide even when they are not needed. */
function meza_dequeue_non_woo_scripts(): void
{
    wp_dequeue_script('wc-cart-fragments');
    wp_deregister_script('wc-cart-fragments');
    wp_dequeue_script('wc-order-attribution');
    wp_deregister_script('wc-order-attribution');
    wp_dequeue_script('sourcebuster-js');
    wp_deregister_script('sourcebuster-js');
    remove_action('wp_enqueue_scripts', 'woocommerce_cart_fragments', 20);
}

/** Remove the main set of known Woo CSS and JS handles outside of product, cart, checkout, and account requests. */
function meza_dequeue_prunable_woo_assets(): void
{
    $handles = meza_woocommerce_prunable_asset_handles();

    foreach ($handles['styles'] as $handle) {
        wp_dequeue_style($handle);
        wp_deregister_style($handle);
    }

    foreach ($handles['scripts'] as $handle) {
        wp_dequeue_script($handle);
        wp_deregister_script($handle);
    }
}

/** Sweep the queued assets one last time and remove any Woo handles that were registered after the normal dequeue pass. */
function meza_dequeue_stray_woo_assets(): void
{
    global $wp_scripts, $wp_styles;

    if ($wp_scripts instanceof WP_Scripts) {
        foreach ((array) $wp_scripts->queue as $handle) {
            $src = isset($wp_scripts->registered[$handle]->src) ? $wp_scripts->registered[$handle]->src : '';
            if (meza_should_strip_woo_asset((string) $handle, (string) $src)) {
                wp_dequeue_script($handle);
                wp_deregister_script($handle);
            }
        }
    }

    if ($wp_styles instanceof WP_Styles) {
        foreach ((array) $wp_styles->queue as $handle) {
            $src = isset($wp_styles->registered[$handle]->src) ? $wp_styles->registered[$handle]->src : '';
            if (meza_should_strip_woo_asset((string) $handle, (string) $src)) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }
        }
    }
}

/** ================================
 *  ASSET PRUNING RULES
 *  ================================ */

/** Keep Woo assets on store pages and strip them from non-store pages. */
add_action('init', function () {
    if (
        !mz_has_woo()
        || !meza_is_woocommerce_plugin_active()
    ) {
        return;
    }

    add_filter('woocommerce_enqueue_styles', function ($styles) {
        if (meza_needs_woo_assets()) {
            return $styles;
        }

        return [];
    }, 20);

    add_filter('woocommerce_should_load_block_assets', function ($should_load) {
        return meza_needs_woo_assets() ? $should_load : false;
    }, 20);

    add_action('wp_enqueue_scripts', function () {
        if (is_admin() || meza_needs_woo_assets()) {
            return;
        }

        meza_dequeue_non_woo_scripts();
    }, 999);

    add_action('wp_enqueue_scripts', function (): void {
        if (is_admin() || meza_needs_woo_assets()) {
            return;
        }

        meza_dequeue_prunable_woo_assets();
    }, 999);

    add_action('wp_enqueue_scripts', function () {
        if (is_admin() || meza_needs_woo_assets()) {
            return;
        }

        meza_dequeue_stray_woo_assets();
    }, 1000);
});

/** ================================
 *  LEGACY THEME COMPATIBILITY
 *  ================================ */

/** Remove jQuery on non-Woo pages for the legacy theme stack. */
add_action('wp_default_scripts', function ($scripts) {
    global $pagenow;

    if (is_admin()) {
        return;
    }

    // Keep jQuery available for core/admin auth screens and async endpoints.
    if ('wp-login.php' === $pagenow || wp_doing_ajax()) {
        return;
    }

    if (function_exists('mz_use_new_theme') && mz_use_new_theme()) {
        return;
    }

    if (!mz_has_woo() && isset($scripts->registered['jquery'])) {
        $scripts->remove('jquery');
        $scripts->remove('jquery-core');
        $scripts->remove('jquery-migrate');
    }
});
