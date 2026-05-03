<?php

/**
 * Plugin Name: MZ WooCommerce
 * Description: WooCommerce query rules, asset loading, and storefront behavior.
 * Version: 1.1.12
 * Author: Meza LLC
 * Author URI: https://meza.design
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) return;

/** ================================
 *  WOO GUARDS
 *  ================================ */

if (!function_exists('meza_woocommerce_ecommerce_is_enabled')) {
    function meza_woocommerce_normalize_checkbox_option_value($value): bool
    {
        if (is_array($value)) {
            return $value !== [];
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) !== 0;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    function meza_woocommerce_normalize_legacy_ecommerce_option_value($value): array
    {
        $allowed_values = ['content_fields', 'store_functionality'];

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $item) {
                $item = sanitize_key(trim((string) $item));
                if ($item === '' || !in_array($item, $allowed_values, true) || in_array($item, $normalized, true)) {
                    continue;
                }

                $normalized[] = $item;
            }

            return $normalized;
        }

        if (meza_woocommerce_normalize_checkbox_option_value($value)) {
            return ['store_functionality'];
        }

        return [];
    }

    function meza_woocommerce_get_saved_ecommerce_settings(): array
    {
        if (function_exists('meza_get_saved_business_information_ecommerce_settings')) {
            return meza_get_saved_business_information_ecommerce_settings();
        }

        $legacy_selection = meza_woocommerce_normalize_legacy_ecommerce_option_value(get_option('options_ecommerce', []));
        $settings = [
            'woocommerce' => (($value = get_option('options_woocommerce', null)) !== null)
                ? meza_woocommerce_normalize_checkbox_option_value($value)
                : ($legacy_selection !== []),
            'product_indexing' => (($value = get_option('options_product_indexing', null)) !== null)
                ? meza_woocommerce_normalize_checkbox_option_value($value)
                : in_array('store_functionality', $legacy_selection, true),
            'store' => (($value = get_option('options_store', null)) !== null)
                ? meza_woocommerce_normalize_checkbox_option_value($value)
                : in_array('store_functionality', $legacy_selection, true),
            'accounts' => (($value = get_option('options_accounts', null)) !== null)
                ? meza_woocommerce_normalize_checkbox_option_value($value)
                : in_array('store_functionality', $legacy_selection, true),
        ];

        if ($settings['product_indexing'] || $settings['store'] || $settings['accounts']) {
            $settings['woocommerce'] = true;
        }

        return $settings;
    }

    function meza_woocommerce_ecommerce_is_enabled(): bool
    {
        $settings = meza_woocommerce_get_saved_ecommerce_settings();

        return !empty($settings['woocommerce']);
    }

    function meza_woocommerce_product_indexing_is_enabled(): bool
    {
        $settings = meza_woocommerce_get_saved_ecommerce_settings();

        return !empty($settings['product_indexing']);
    }

    function meza_woocommerce_store_functionality_is_enabled(): bool
    {
        $settings = meza_woocommerce_get_saved_ecommerce_settings();

        return !empty($settings['store']);
    }

    function meza_woocommerce_accounts_are_enabled(): bool
    {
        $settings = meza_woocommerce_get_saved_ecommerce_settings();

        return !empty($settings['accounts']);
    }
}

if (!function_exists('meza_get_woocommerce_unlisted_page_slugs')) {
    function meza_get_woocommerce_unlisted_page_slugs(): array
    {
        return [
            'shop',
            'cart',
            'checkout',
            'my-account',
            'terms-and-conditions',
            'privacy-policy',
            'privacy',
            'cookie-policy',
            'cookies',
            'refunds-and-returns-policy',
            'refund-and-returns-policy',
            'refund-returns-policy',
            'returns-and-refunds-policy',
            'refund-policy',
            'returns-policy',
            'refund_returns',
        ];
    }
}

if (!function_exists('meza_get_woocommerce_unlisted_page_titles')) {
    function meza_get_woocommerce_unlisted_page_titles(): array
    {
        return [
            'Shop',
            'Cart',
            'Checkout',
            'My account',
            'My Account',
            'Terms and Conditions',
            'Privacy Policy',
            'Cookie Policy',
            'Refunds and Returns Policy',
            'Refund and Returns Policy',
            'Refund & Returns Policy',
            'Returns and Refunds Policy',
            'Returns Policy',
            'Refund Policy',
        ];
    }
}

if (!function_exists('meza_get_woocommerce_page_definitions')) {
    function meza_get_woocommerce_page_definitions(): array
    {
        $definitions = [
            'shop' => [
                'title' => 'Shop',
                'slugs' => ['shop'],
                'option_keys' => ['woocommerce_shop_page_id'],
                'content' => '',
                'status' => 'draft',
            ],
            'cart' => [
                'title' => 'Cart',
                'slugs' => ['cart'],
                'option_keys' => ['woocommerce_cart_page_id'],
                'content' => '<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->',
                'status' => 'draft',
            ],
            'checkout' => [
                'title' => 'Checkout',
                'slugs' => ['checkout'],
                'option_keys' => ['woocommerce_checkout_page_id'],
                'content' => '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->',
                'status' => 'draft',
            ],
            'myaccount' => [
                'title' => 'My Account',
                'slugs' => ['my-account'],
                'option_keys' => ['woocommerce_myaccount_page_id'],
                'content' => '<!-- wp:shortcode -->[woocommerce_my_account]<!-- /wp:shortcode -->',
                'status' => 'draft',
            ],
            'terms' => [
                'title' => 'Terms and Conditions',
                'slugs' => ['terms-and-conditions'],
                'option_keys' => ['woocommerce_terms_page_id'],
                'content' => '',
                'status' => 'draft',
            ],
            'refund_returns' => [
                'title' => 'Refunds and Returns Policy',
                'slugs' => [
                    'refunds-and-returns-policy',
                    'refund-and-returns-policy',
                    'refund-returns-policy',
                    'returns-and-refunds-policy',
                    'refund-policy',
                    'returns-policy',
                    'refund_returns',
                ],
                'option_keys' => ['meza_woocommerce_refund_returns_page_id', 'woocommerce_refund_returns_page_id'],
                'content' => '',
                'status' => 'draft',
                'title_candidates' => [
                    'Refunds and Returns Policy',
                    'Refund and Returns Policy',
                    'Refund & Returns Policy',
                    'Returns and Refunds Policy',
                    'Returns Policy',
                    'Refund Policy',
                ],
            ],
        ];

        if (!meza_woocommerce_accounts_are_enabled()) {
            unset($definitions['myaccount']);
        }

        return $definitions;
    }
}

if (!function_exists('meza_get_woocommerce_page_by_definition')) {
    function meza_get_woocommerce_page_by_definition(array $definition): ?WP_Post
    {
        foreach ((array) ($definition['option_keys'] ?? []) as $option_key) {
            $page_id = (int) get_option((string) $option_key, 0);
            if ($page_id <= 0) {
                continue;
            }

            $page = get_post($page_id);
            if (
                $page instanceof WP_Post
                && $page->post_type === 'page'
                && !in_array((string) $page->post_status, ['auto-draft', 'trash'], true)
            ) {
                return $page;
            }
        }

        if (function_exists('meza_get_page_by_candidate_slugs')) {
            $page = meza_get_page_by_candidate_slugs((array) ($definition['slugs'] ?? []));
            if ($page instanceof WP_Post) {
                return $page;
            }
        }

        $title_candidates = (array) ($definition['title_candidates'] ?? []);
        $title_candidates[] = (string) ($definition['title'] ?? '');
        foreach ($title_candidates as $title) {
            $title = trim((string) $title);
            if ($title === '') {
                continue;
            }

            $page = get_page_by_title($title, OBJECT, 'page');
            if (
                $page instanceof WP_Post
                && $page->post_type === 'page'
                && !in_array((string) $page->post_status, ['auto-draft', 'trash'], true)
            ) {
                return $page;
            }
        }

        return null;
    }
}

if (!function_exists('meza_sync_woocommerce_page_content')) {
    function meza_sync_woocommerce_page_content(int $page_id, string $content): void
    {
        if ($page_id <= 0 || trim($content) === '') {
            return;
        }

        $page = get_post($page_id);
        if (!($page instanceof WP_Post) || $page->post_type !== 'page') {
            return;
        }

        if (trim((string) $page->post_content) !== '') {
            return;
        }

        wp_update_post([
            'ID' => $page_id,
            'post_content' => $content,
        ]);
    }
}

if (!function_exists('meza_sync_woocommerce_page_definition')) {
    function meza_sync_woocommerce_page_definition(array $definition): int
    {
        $page = meza_get_woocommerce_page_by_definition($definition);
        $title = trim((string) ($definition['title'] ?? ''));
        $slugs = (array) ($definition['slugs'] ?? []);
        $status = trim((string) ($definition['status'] ?? 'publish'));
        $was_created = false;

        $page_id = $page instanceof WP_Post ? (int) $page->ID : 0;

        if ($page_id <= 0 && function_exists('meza_ensure_page_state')) {
            $page_id = meza_ensure_page_state($title, $slugs, [
                'status' => $status !== '' ? $status : 'publish',
                'force_title' => true,
                'yoast_allow_indexing' => false,
                'yoast_allow_follow' => false,
            ]);
            $was_created = $page_id > 0;
        }

        if ($page_id <= 0) {
            return 0;
        }

        $page = get_post($page_id);
        if (!($page instanceof WP_Post) || $page->post_type !== 'page') {
            return 0;
        }

        if ($title !== '' && $page->post_title !== $title) {
            wp_update_post([
                'ID' => $page_id,
                'post_title' => $title,
            ]);
        }

        if ($was_created && $status !== '' && (string) $page->post_status !== $status) {
            wp_update_post([
                'ID' => $page_id,
                'post_status' => $status,
            ]);
            $page = get_post($page_id);
        }

        meza_sync_woocommerce_page_content($page_id, (string) ($definition['content'] ?? ''));

        if (function_exists('meza_update_page_reference_options')) {
            meza_update_page_reference_options('meza_woocommerce_page_option_keys', $page_id, (array) ($definition['option_keys'] ?? []));
        }

        meza_set_woocommerce_page_unlisted($page_id);

        return $page_id;
    }
}

if (!function_exists('meza_enforce_woocommerce_page_definition_status')) {
    function meza_enforce_woocommerce_page_definition_status(int $page_id, array $definition): void
    {
        if ($page_id <= 0) {
            return;
        }

        $status = trim((string) ($definition['status'] ?? ''));
        if ($status === '') {
            return;
        }

        $page = get_post($page_id);
        if (!($page instanceof WP_Post) || $page->post_type !== 'page' || (string) $page->post_status === $status) {
            return;
        }

        wp_update_post([
            'ID' => $page_id,
            'post_status' => $status,
        ]);
    }
}

if (!function_exists('meza_should_manage_woocommerce_pages')) {
    function meza_should_manage_woocommerce_pages(): bool
    {
        return meza_woocommerce_store_functionality_is_enabled();
    }
}

if (!function_exists('meza_should_restore_managed_woocommerce_pages')) {
    function meza_should_restore_managed_woocommerce_pages(): bool
    {
        return meza_woocommerce_store_functionality_is_enabled() || meza_woocommerce_accounts_are_enabled();
    }
}

if (!function_exists('meza_is_protected_woocommerce_page')) {
    function meza_is_protected_woocommerce_page(int $post_id): bool
    {
        return $post_id > 0
            && meza_should_restore_managed_woocommerce_pages()
            && is_array(meza_get_woocommerce_page_definition_for_post($post_id));
    }
}

if (!function_exists('meza_get_woocommerce_page_definition_for_post')) {
    function meza_get_woocommerce_page_definition_for_post(int $post_id): ?array
    {
        if ($post_id <= 0) {
            return null;
        }

        $post = get_post($post_id);
        if (!($post instanceof WP_Post) || $post->post_type !== 'page') {
            return null;
        }

        foreach (meza_get_woocommerce_page_definitions() as $definition) {
            foreach ((array) ($definition['option_keys'] ?? []) as $option_key) {
                if ((int) get_option((string) $option_key, 0) === $post_id) {
                    return $definition;
                }
            }
        }

        $post_name = sanitize_title((string) $post->post_name);
        $post_name = preg_replace('/(?:__trashed)+$/', '', $post_name);

        $post_title = trim((string) $post->post_title);

        foreach (meza_get_woocommerce_page_definitions() as $definition) {
            foreach ((array) ($definition['slugs'] ?? []) as $slug) {
                if ($post_name === sanitize_title((string) $slug)) {
                    return $definition;
                }
            }

            $title_candidates = (array) ($definition['title_candidates'] ?? []);
            $title_candidates[] = (string) ($definition['title'] ?? '');

            foreach ($title_candidates as $title_candidate) {
                if ($post_title !== '' && $post_title === trim((string) $title_candidate)) {
                    return $definition;
                }
            }
        }

        return null;
    }
}

if (!function_exists('meza_woocommerce_page_restore_state')) {
    function meza_woocommerce_page_restore_state(?array $queued = null, ?bool $restoring = null): array
    {
        static $state = [
            'queued' => [],
            'restoring' => false,
        ];

        if (is_array($queued)) {
            $state['queued'] = $queued;
        }

        if ($restoring !== null) {
            $state['restoring'] = $restoring;
        }

        return $state;
    }
}

if (!function_exists('meza_woocommerce_is_restoring_pages')) {
    function meza_woocommerce_is_restoring_pages(): bool
    {
        $state = meza_woocommerce_page_restore_state();

        return !empty($state['restoring']);
    }
}

if (!function_exists('meza_sync_woocommerce_pages')) {
    function meza_sync_woocommerce_pages(): void
    {
        foreach (meza_get_woocommerce_page_definitions() as $definition) {
            meza_sync_woocommerce_page_definition($definition);
        }
    }
}

if (!function_exists('meza_get_woocommerce_unlisted_page_ids')) {
    function meza_get_woocommerce_unlisted_page_ids(): array
    {
        $page_ids = [
            (int) get_option('woocommerce_shop_page_id', 0),
            (int) get_option('woocommerce_cart_page_id', 0),
            (int) get_option('woocommerce_checkout_page_id', 0),
            (int) get_option('woocommerce_myaccount_page_id', 0),
            (int) get_option('woocommerce_terms_page_id', 0),
            (int) get_option('woocommerce_refund_returns_page_id', 0),
            (int) get_option('meza_woocommerce_refund_returns_page_id', 0),
            (int) get_option('wp_page_for_privacy_policy', 0),
            (int) get_option('meza_page_for_cookie_policy', 0),
        ];

        foreach (meza_get_woocommerce_unlisted_page_slugs() as $slug) {
            $page = get_page_by_path($slug, OBJECT, 'page');
            if ($page instanceof WP_Post) {
                $page_ids[] = (int) $page->ID;
            }
        }

        foreach (meza_get_woocommerce_unlisted_page_titles() as $title) {
            $page = get_page_by_title($title, OBJECT, 'page');
            if ($page instanceof WP_Post) {
                $page_ids[] = (int) $page->ID;
            }
        }

        return array_values(array_unique(array_filter(array_map('intval', $page_ids))));
    }
}

if (!function_exists('meza_set_woocommerce_page_unlisted')) {
    function meza_set_woocommerce_page_indexed(int $page_id): void
    {
        if ($page_id <= 0) {
            return;
        }

        $page = get_post($page_id);
        if (!($page instanceof WP_Post) || $page->post_type !== 'page') {
            return;
        }

        if (function_exists('meza_assign_yoast_robots')) {
            meza_assign_yoast_robots($page_id, true, true);
        } else {
            update_post_meta($page_id, '_yoast_wpseo_meta-robots-noindex', '2');
            update_post_meta($page_id, '_yoast_wpseo_meta-robots-nofollow', '0');
        }

        if (function_exists('meza_refresh_yoast_indexable')) {
            meza_refresh_yoast_indexable($page_id);
        }
    }

    function meza_set_woocommerce_page_unlisted(int $page_id): void
    {
        if ($page_id <= 0) {
            return;
        }

        $page = get_post($page_id);
        if (!($page instanceof WP_Post) || $page->post_type !== 'page') {
            return;
        }

        if (function_exists('meza_assign_yoast_robots')) {
            meza_assign_yoast_robots($page_id, false, false);
        } else {
            update_post_meta($page_id, '_yoast_wpseo_meta-robots-noindex', '1');
            update_post_meta($page_id, '_yoast_wpseo_meta-robots-nofollow', '1');
        }

        if (function_exists('meza_refresh_yoast_indexable')) {
            meza_refresh_yoast_indexable($page_id);
        }
    }
}

if (!function_exists('meza_set_all_woocommerce_pages_unlisted')) {
    function meza_set_all_woocommerce_pages_indexed(): void
    {
        foreach (meza_get_woocommerce_unlisted_page_ids() as $page_id) {
            meza_set_woocommerce_page_indexed((int) $page_id);
        }
    }

    function meza_set_all_woocommerce_pages_unlisted(): void
    {
        foreach (meza_get_woocommerce_unlisted_page_ids() as $page_id) {
            meza_set_woocommerce_page_unlisted((int) $page_id);
        }
    }
}

if (!function_exists('meza_get_woocommerce_page_assignment_option_keys')) {
    function meza_get_woocommerce_page_assignment_option_keys(): array
    {
        $option_keys = [];

        foreach (meza_get_woocommerce_page_definitions() as $definition) {
            foreach ((array) ($definition['option_keys'] ?? []) as $option_key) {
                $option_key = trim((string) $option_key);
                if ($option_key === '' || in_array($option_key, $option_keys, true)) {
                    continue;
                }

                $option_keys[] = $option_key;
            }
        }

        return $option_keys;
    }
}

if (!function_exists('meza_clear_woocommerce_page_assignments')) {
    function meza_woocommerce_myaccount_page_assignment_guard(?bool $set = null): bool
    {
        static $allow_update = false;

        if ($set !== null) {
            $allow_update = $set;
        }

        return $allow_update;
    }

    function meza_clear_woocommerce_page_assignment(string $option_key): void
    {
        $option_key = trim($option_key);
        if ($option_key === '') {
            return;
        }

        update_option($option_key, 0, false);
    }

    function meza_clear_woocommerce_page_assignments(): void
    {
        foreach (meza_get_woocommerce_page_assignment_option_keys() as $option_key) {
            meza_clear_woocommerce_page_assignment($option_key);
        }
    }
}

if (!function_exists('meza_sync_woocommerce_account_settings')) {
    function meza_sync_woocommerce_account_settings(): void
    {
        $enabled = meza_woocommerce_accounts_are_enabled() ? 'yes' : 'no';

        update_option('woocommerce_enable_checkout_login_reminder', $enabled, false);
        update_option('woocommerce_enable_signup_and_login_from_checkout', $enabled, false);
        update_option('woocommerce_enable_myaccount_registration', $enabled, false);
    }
}

if (!function_exists('meza_get_woocommerce_product_ids')) {
    function meza_get_woocommerce_product_ids(): array
    {
        return get_posts([
            'post_type' => 'product',
            'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
            'fields' => 'ids',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);
    }
}

if (!function_exists('meza_set_woocommerce_product_visibility')) {
    function meza_set_woocommerce_product_visibility(int $product_id, bool $allow_indexing, bool $allow_follow): void
    {
        if ($product_id <= 0) {
            return;
        }

        $product = get_post($product_id);
        if (!($product instanceof WP_Post) || $product->post_type !== 'product') {
            return;
        }

        update_post_meta($product_id, '_yoast_wpseo_meta-robots-noindex', $allow_indexing ? '2' : '1');
        update_post_meta($product_id, '_yoast_wpseo_meta-robots-nofollow', $allow_follow ? '0' : '1');

        if (function_exists('meza_refresh_yoast_indexable')) {
            meza_refresh_yoast_indexable($product_id);
        }
    }
}

if (!function_exists('meza_sync_woocommerce_product_visibility')) {
    function meza_sync_woocommerce_product_visibility(bool $allow_indexing, bool $allow_follow): void
    {
        foreach (meza_get_woocommerce_product_ids() as $product_id) {
            meza_set_woocommerce_product_visibility((int) $product_id, $allow_indexing, $allow_follow);
        }
    }
}

if (!function_exists('meza_get_woocommerce_configuration_signature')) {
    function meza_get_woocommerce_configuration_signature(): string
    {
        $settings = meza_woocommerce_get_saved_ecommerce_settings();

        return sprintf(
            'woo:%d|index:%d|store:%d',
            !empty($settings['woocommerce']) ? 1 : 0,
            !empty($settings['product_indexing']) ? 1 : 0,
            !empty($settings['store']) ? 1 : 0
        );
    }
}

if (!function_exists('meza_sync_woocommerce_configuration')) {
    function meza_sync_woocommerce_configuration(bool $force = false): void
    {
        $signature_option = 'meza_woocommerce_configuration_signature';
        $target_signature = meza_get_woocommerce_configuration_signature();
        $current_signature = (string) get_option($signature_option, '');

        if (!$force && $current_signature === $target_signature) {
            return;
        }

        if (meza_woocommerce_store_functionality_is_enabled()) {
            meza_sync_woocommerce_pages();
        } else {
            meza_clear_woocommerce_page_assignments();
        }

        meza_set_all_woocommerce_pages_unlisted();

        if (meza_woocommerce_accounts_are_enabled()) {
            $my_account_definition = meza_get_woocommerce_page_definitions()['myaccount'] ?? null;
            if (is_array($my_account_definition)) {
                meza_woocommerce_myaccount_page_assignment_guard(true);
                $my_account_page_id = meza_sync_woocommerce_page_definition($my_account_definition);
                meza_woocommerce_myaccount_page_assignment_guard(false);
                if ($my_account_page_id <= 0) {
                    meza_clear_woocommerce_page_assignment('woocommerce_myaccount_page_id');
                }
            }
        } else {
            meza_clear_woocommerce_page_assignment('woocommerce_myaccount_page_id');
        }

        meza_sync_woocommerce_account_settings();

        if (meza_woocommerce_product_indexing_is_enabled()) {
            meza_sync_woocommerce_product_visibility(true, true);
        } else {
            meza_sync_woocommerce_product_visibility(false, false);
        }

        update_option($signature_option, $target_signature, false);
    }
}

if (!function_exists('meza_get_woocommerce_store_only_capabilities')) {
    function meza_get_woocommerce_store_only_capabilities(): array
    {
        $caps = [
            'manage_woocommerce',
            'create_customers',
            'view_woocommerce_reports',
        ];

        foreach (['shop_order', 'shop_coupon'] as $capability_type) {
            $caps = array_merge($caps, [
                "edit_{$capability_type}",
                "read_{$capability_type}",
                "delete_{$capability_type}",
                "edit_{$capability_type}s",
                "edit_others_{$capability_type}s",
                "publish_{$capability_type}s",
                "read_private_{$capability_type}s",
                "delete_{$capability_type}s",
                "delete_private_{$capability_type}s",
                "delete_published_{$capability_type}s",
                "delete_others_{$capability_type}s",
                "edit_private_{$capability_type}s",
                "edit_published_{$capability_type}s",
                "manage_{$capability_type}_terms",
                "edit_{$capability_type}_terms",
                "delete_{$capability_type}_terms",
                "assign_{$capability_type}_terms",
            ]);
        }

        return array_values(array_unique($caps));
    }
}

if (!function_exists('meza_is_woocommerce_store_admin_request')) {
    function meza_is_woocommerce_store_admin_request(): bool
    {
        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        $post_type = isset($_GET['post_type']) ? sanitize_key((string) wp_unslash($_GET['post_type'])) : '';
        $pagenow = isset($GLOBALS['pagenow']) ? (string) $GLOBALS['pagenow'] : '';

        if (in_array($post_type, ['shop_order', 'shop_coupon'], true)) {
            return true;
        }

        if ($page !== '') {
            if (str_starts_with($page, 'wc-') || str_starts_with($page, 'woocommerce')) {
                return true;
            }

            if (in_array($page, ['wc-admin', 'wc-settings', 'wc-status', 'wc-addons', 'wc-reports'], true)) {
                return true;
            }
        }

        if ($pagenow === 'admin.php' && $page !== '') {
            return str_starts_with($page, 'wc-') || str_starts_with($page, 'woocommerce');
        }

        return false;
    }
}

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

add_action('woocommerce_page_created', function ($page_id, $page_data): void {
    meza_set_woocommerce_page_unlisted((int) $page_id);
}, 20, 2);

add_action('before_delete_post', function ($post_id, $post): void {
    if (meza_woocommerce_is_restoring_pages() || !meza_should_restore_managed_woocommerce_pages()) {
        return;
    }

    $definition = meza_get_woocommerce_page_definition_for_post((int) $post_id);
    if (!is_array($definition)) {
        return;
    }

    $state = meza_woocommerce_page_restore_state();
    $state['queued'][(int) $post_id] = $definition;
    meza_woocommerce_page_restore_state($state['queued']);
}, 20, 2);

add_action('trashed_post', function ($post_id): void {
    if (meza_woocommerce_is_restoring_pages() || !meza_should_restore_managed_woocommerce_pages()) {
        return;
    }

    $definition = meza_get_woocommerce_page_definition_for_post((int) $post_id);
    if (!is_array($definition)) {
        return;
    }

    meza_woocommerce_page_restore_state(null, true);

    try {
        wp_untrash_post((int) $post_id);
        $restored_page_id = meza_sync_woocommerce_page_definition($definition);
        meza_enforce_woocommerce_page_definition_status($restored_page_id, $definition);
    } finally {
        meza_woocommerce_page_restore_state(null, false);
    }
}, 20);

add_action('deleted_post', function ($post_id, $post): void {
    if (!($post instanceof WP_Post) || $post->post_type !== 'page') {
        return;
    }

    $state = meza_woocommerce_page_restore_state();
    $definition = $state['queued'][(int) $post_id] ?? null;
    if (!is_array($definition)) {
        return;
    }

    unset($state['queued'][(int) $post_id]);
    meza_woocommerce_page_restore_state($state['queued']);

    if (meza_woocommerce_is_restoring_pages() || !meza_should_restore_managed_woocommerce_pages()) {
        return;
    }

    meza_woocommerce_page_restore_state(null, true);

    try {
        $restored_page_id = meza_sync_woocommerce_page_definition($definition);
        meza_enforce_woocommerce_page_definition_status($restored_page_id, $definition);
    } finally {
        meza_woocommerce_page_restore_state(null, false);
    }
}, 20, 2);

add_action('init', function (): void {
    meza_set_all_woocommerce_pages_unlisted();
}, 20);

add_action('admin_init', function (): void {
    if (!is_admin()) {
        return;
    }

    if (!meza_woocommerce_store_functionality_is_enabled() && meza_is_woocommerce_store_admin_request()) {
        wp_safe_redirect(admin_url());
        exit;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    $should_force_sync = function_exists('mz_plugins_should_rerun') && mz_plugins_should_rerun();
    meza_sync_woocommerce_configuration($should_force_sync);
}, 30);

add_action('admin_menu', function (): void {
    if (meza_woocommerce_store_functionality_is_enabled()) {
        return;
    }

    remove_menu_page('woocommerce');
    remove_menu_page('woocommerce-marketing');
}, PHP_INT_MAX);

add_filter('page_row_actions', function ($actions, $post) {
    if (!($post instanceof WP_Post) || $post->post_type !== 'page' || !meza_is_protected_woocommerce_page((int) $post->ID)) {
        return $actions;
    }

    unset($actions['trash'], $actions['delete']);

    return $actions;
}, 1000, 2);

add_filter('pre_update_option_woocommerce_enable_checkout_login_reminder', function ($new_value, $old_value) {
    return meza_woocommerce_accounts_are_enabled() ? 'yes' : 'no';
}, 999, 2);

add_filter('pre_update_option_woocommerce_enable_signup_and_login_from_checkout', function ($new_value, $old_value) {
    return meza_woocommerce_accounts_are_enabled() ? 'yes' : 'no';
}, 999, 2);

add_filter('pre_update_option_woocommerce_enable_myaccount_registration', function ($new_value, $old_value) {
    return meza_woocommerce_accounts_are_enabled() ? 'yes' : 'no';
}, 999, 2);

add_filter('pre_update_option_woocommerce_myaccount_page_id', function ($new_value, $old_value) {
    if (meza_woocommerce_myaccount_page_assignment_guard()) {
        return $new_value;
    }

    if (meza_woocommerce_accounts_are_enabled()) {
        return $old_value;
    }

    return '0';
}, 999, 2);

add_action('admin_head', function (): void {
    if (!is_admin()) {
        return;
    }

    $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
    $pagenow = isset($GLOBALS['pagenow']) ? (string) $GLOBALS['pagenow'] : '';

    if ($pagenow === 'post.php' && $post_id > 0 && meza_is_protected_woocommerce_page($post_id)) {
        ?>
        <style id="meza-hide-protected-woocommerce-page-delete-action">
            #delete-action,
            .submitbox .submitdelete {
                display: none !important;
            }
        </style>
        <script id="meza-hide-protected-woocommerce-page-delete-action-script">
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('#delete-action, .submitbox .submitdelete').forEach(function (node) {
                    node.remove();
                });
            });
        </script>
        <?php
    }

    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : '';

    if ($page !== 'wc-settings') {
        return;
    }

    if ($tab === 'account') {
        ?>
        <style id="meza-lock-woocommerce-account-settings">
            #woocommerce_enable_checkout_login_reminder,
            #woocommerce_enable_signup_and_login_from_checkout,
            #woocommerce_enable_myaccount_registration {
                pointer-events: none;
            }
        </style>
        <script id="meza-lock-woocommerce-account-settings-script">
            document.addEventListener('DOMContentLoaded', function () {
                [
                    'woocommerce_enable_checkout_login_reminder',
                    'woocommerce_enable_signup_and_login_from_checkout',
                    'woocommerce_enable_myaccount_registration'
                ].forEach(function (id) {
                    var field = document.getElementById(id);
                    if (!field) {
                        return;
                    }
                    field.disabled = true;
                    var row = field.closest('tr');
                    if (row) {
                        row.classList.add('meza-locked-woocommerce-setting');
                    }
                });
            });
        </script>
        <?php
    }

    if ($tab === 'advanced') {
        ?>
        <script id="meza-lock-woocommerce-myaccount-page-script">
            document.addEventListener('DOMContentLoaded', function () {
                var field = document.getElementById('woocommerce_myaccount_page_id');
                if (!field) {
                    return;
                }
                field.disabled = true;
                var row = field.closest('tr');
                if (row) {
                    row.classList.add('meza-locked-woocommerce-setting');
                }
            });
        </script>
        <?php
    }
}, 30);

add_filter('user_has_cap', function (array $allcaps, array $caps, array $args, $user): array {
    if (!is_admin() || meza_woocommerce_store_functionality_is_enabled()) {
        return $allcaps;
    }

    foreach (meza_get_woocommerce_store_only_capabilities() as $cap) {
        $allcaps[$cap] = false;
    }

    return $allcaps;
}, 999, 4);

add_action('save_post_product', function ($post_id, $post, $update): void {
    if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }

    meza_set_woocommerce_product_visibility(
        (int) $post_id,
        meza_woocommerce_product_indexing_is_enabled(),
        meza_woocommerce_product_indexing_is_enabled()
    );
}, 20, 3);

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

/** Decide whether front-end jQuery should be stripped for the current request.
 * Non-Woo sites strip it by default, while Woo sites keep the store-page rules above.
 * Projects that truly need front-end jQuery can opt back in via a constant or filter.
 */
function meza_should_strip_frontend_jquery(): bool
{
    if (defined('MZ_ALLOW_JQUERY_REMOVAL')) {
        return MZ_ALLOW_JQUERY_REMOVAL === true;
    }

    return (bool) apply_filters('meza_strip_frontend_jquery', !mz_has_woo());
}

/** Remove jQuery on non-Woo sites for the legacy theme stack.
 * Core/admin auth screens and async endpoints intentionally keep their own registrations.
 */
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

    if (!meza_should_strip_frontend_jquery()) {
        return;
    }

    if (!mz_has_woo() && isset($scripts->registered['jquery'])) {
        $scripts->remove('jquery');
        $scripts->remove('jquery-core');
        $scripts->remove('jquery-migrate');
    }
});
