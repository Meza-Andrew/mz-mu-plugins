<?php


/**
 * Shared ACF option pages and field groups we want available on every project.
 * Version: 1.8.64
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) {
    return;
}


if (!function_exists('meza_get_business_information_type')) {
    function meza_get_business_information_type(): string
    {
        if (
            is_admin()
            && isset($_POST['acf'])
            && is_array($_POST['acf'])
            && array_key_exists('field_meza_business_type', $_POST['acf'])
        ) {
            $posted_value = sanitize_key(trim((string) wp_unslash($_POST['acf']['field_meza_business_type'])));
            if (in_array($posted_value, ['business', 'nonprofit', 'conference'], true)) {
                return $posted_value;
            }
        }

        $option_value = get_option('options_type');
        $value = is_scalar($option_value) ? trim((string) $option_value) : '';

        $value = sanitize_key($value);

        return in_array($value, ['business', 'nonprofit', 'conference'], true) ? $value : 'business';
    }
}

if (!function_exists('meza_is_conference_business_type')) {
    function meza_is_conference_business_type(): bool
    {
        return meza_get_business_information_type() === 'conference';
    }
}

if (!function_exists('meza_get_contact_page_id_for_acf_rules')) {
    function meza_get_contact_page_id_for_acf_rules(): int
    {
        if (function_exists('meza_get_page_by_candidate_slugs') && defined('MEZA_CONTACT_SLUGS')) {
            $page = meza_get_page_by_candidate_slugs(MEZA_CONTACT_SLUGS);
            if ($page instanceof WP_Post) {
                return (int) $page->ID;
            }
        }

        $page = get_page_by_path('contact', OBJECT, 'page');

        return $page instanceof WP_Post ? (int) $page->ID : 0;
    }
}

if (!function_exists('meza_supports_sponsor_features')) {
    function meza_supports_sponsor_features(): bool
    {
        return in_array(meza_get_business_information_type(), ['conference', 'nonprofit'], true);
    }
}

if (!function_exists('meza_business_information_acf_truthy')) {
    function meza_business_information_acf_truthy($value): bool
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
}

if (!function_exists('meza_normalize_business_information_legacy_ecommerce_selection')) {
    function meza_normalize_business_information_legacy_ecommerce_selection($value): array
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

        if (is_bool($value)) {
            return $value ? ['store_functionality'] : [];
        }

        if (is_numeric($value)) {
            return ((int) $value) !== 0 ? ['store_functionality'] : [];
        }

        $normalized_value = sanitize_key(trim((string) $value));
        if (in_array($normalized_value, $allowed_values, true)) {
            return [$normalized_value];
        }

        return meza_business_information_acf_truthy($value) ? ['store_functionality'] : [];
    }

    function meza_get_saved_business_information_legacy_ecommerce_selection(): array
    {
        return meza_normalize_business_information_legacy_ecommerce_selection(get_option('options_ecommerce', []));
    }

    function meza_normalize_business_information_ecommerce_settings(array $settings): array
    {
        $normalized = [
            'woocommerce' => !empty($settings['woocommerce']),
            'product_indexing' => !empty($settings['product_indexing']),
            'store' => !empty($settings['store']),
            'accounts' => !empty($settings['accounts']),
        ];

        if ($normalized['accounts']) {
            $normalized['store'] = true;
        }

        if ($normalized['store']) {
            $normalized['product_indexing'] = true;
        }

        if ($normalized['product_indexing']) {
            $normalized['woocommerce'] = true;
        }

        return $normalized;
    }

    function meza_get_saved_business_information_ecommerce_settings(): array
    {
        $legacy_selection = meza_get_saved_business_information_legacy_ecommerce_selection();

        $woocommerce = get_option('options_woocommerce', null);
        $product_indexing = get_option('options_product_indexing', null);
        $store = get_option('options_store', null);
        $accounts = get_option('options_accounts', null);

        $settings = [
            'woocommerce' => ($woocommerce !== null)
                ? meza_business_information_acf_truthy($woocommerce)
                : ($legacy_selection !== []),
            'product_indexing' => ($product_indexing !== null)
                ? meza_business_information_acf_truthy($product_indexing)
                : in_array('store_functionality', $legacy_selection, true),
            'store' => ($store !== null)
                ? meza_business_information_acf_truthy($store)
                : in_array('store_functionality', $legacy_selection, true),
            'accounts' => ($accounts !== null)
                ? meza_business_information_acf_truthy($accounts)
                : in_array('store_functionality', $legacy_selection, true),
        ];

        return meza_normalize_business_information_ecommerce_settings($settings);
    }

    function meza_get_business_information_ecommerce_settings(): array
    {
        if (is_admin() && meza_is_business_information_acf_submission() && isset($_POST['acf']) && is_array($_POST['acf'])) {
            $posted_fields = wp_unslash($_POST['acf']);

            $settings = [
                'woocommerce' => meza_business_information_acf_truthy($posted_fields['field_meza_business_woocommerce'] ?? 0),
                'product_indexing' => meza_business_information_acf_truthy($posted_fields['field_meza_business_product_indexing'] ?? 0),
                'store' => meza_business_information_acf_truthy($posted_fields['field_meza_business_store'] ?? 0),
                'accounts' => meza_business_information_acf_truthy($posted_fields['field_meza_business_accounts'] ?? 0),
            ];

            return meza_normalize_business_information_ecommerce_settings($settings);
        }

        return meza_get_saved_business_information_ecommerce_settings();
    }

    function meza_saved_business_information_enables_ecommerce(): bool
    {
        $settings = meza_get_saved_business_information_ecommerce_settings();

        return !empty($settings['woocommerce']);
    }

    function meza_saved_business_information_enables_product_indexing(): bool
    {
        $settings = meza_get_saved_business_information_ecommerce_settings();

        return !empty($settings['product_indexing']);
    }

    function meza_saved_business_information_enables_store_functionality(): bool
    {
        $settings = meza_get_saved_business_information_ecommerce_settings();

        return !empty($settings['store']);
    }

    function meza_saved_business_information_enables_accounts(): bool
    {
        $settings = meza_get_saved_business_information_ecommerce_settings();

        return !empty($settings['accounts']);
    }

    function meza_should_seed_ecommerce_default_editable_acf_field_groups(): bool
    {
        if (is_admin() && meza_is_business_information_acf_submission() && isset($_POST['acf']) && is_array($_POST['acf'])) {
            $settings = meza_get_business_information_ecommerce_settings();

            return !empty($settings['woocommerce']);
        }

        return meza_saved_business_information_enables_ecommerce();
    }

    function meza_business_information_enables_ecommerce(): bool
    {
        $settings = meza_get_business_information_ecommerce_settings();

        return !empty($settings['woocommerce']);
    }

    function meza_business_information_enables_content_fields(): bool
    {
        return meza_business_information_enables_ecommerce();
    }

    function meza_business_information_enables_product_indexing(): bool
    {
        $settings = meza_get_business_information_ecommerce_settings();

        return !empty($settings['product_indexing']);
    }

    function meza_business_information_enables_store_functionality(): bool
    {
        $settings = meza_get_business_information_ecommerce_settings();

        return !empty($settings['store']);
    }

    function meza_business_information_enables_accounts(): bool
    {
        $settings = meza_get_business_information_ecommerce_settings();

        return !empty($settings['accounts']);
    }
}

if (!function_exists('meza_get_business_information_menu_label')) {
    function meza_get_business_information_menu_label(): string
    {
        $label_map = [
            'business' => 'Business Information',
            'nonprofit' => 'Nonprofit Information',
            'conference' => 'Conference Information',
        ];

        return $label_map[meza_get_business_information_type()] ?? $label_map['business'];
    }
}

add_filter('acf/load_value/key=field_meza_business_woocommerce', function ($value) {
    if (!function_exists('meza_get_saved_business_information_ecommerce_settings')) {
        return $value;
    }

    $settings = meza_get_saved_business_information_ecommerce_settings();

    return !empty($settings['woocommerce']) ? 1 : 0;
}, 20);

add_filter('acf/load_value/key=field_meza_business_product_indexing', function ($value) {
    if (!function_exists('meza_get_saved_business_information_ecommerce_settings')) {
        return $value;
    }

    $settings = meza_get_saved_business_information_ecommerce_settings();

    return !empty($settings['product_indexing']) ? 1 : 0;
}, 20);

add_filter('acf/load_value/key=field_meza_business_store', function ($value) {
    if (!function_exists('meza_get_saved_business_information_ecommerce_settings')) {
        return $value;
    }

    $settings = meza_get_saved_business_information_ecommerce_settings();

    return !empty($settings['store']) ? 1 : 0;
}, 20);

add_filter('acf/load_value/key=field_meza_business_accounts', function ($value) {
    if (!function_exists('meza_get_saved_business_information_ecommerce_settings')) {
        return $value;
    }

    $settings = meza_get_saved_business_information_ecommerce_settings();

    return !empty($settings['accounts']) ? 1 : 0;
}, 20);

add_action('admin_head', function (): void {
    if (!is_admin()) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    if ($page !== 'business-information') {
        return;
    }
    ?>
    <style id="meza-progressive-ecommerce-settings-locks">
        .acf-field.meza-progressive-ecommerce-locked .acf-input {
            opacity: 0.65;
        }

        .acf-field.meza-progressive-ecommerce-locked .acf-input label,
        .acf-field.meza-progressive-ecommerce-locked .acf-input input[type="checkbox"] {
            cursor: not-allowed;
        }
    </style>
    <script id="meza-progressive-ecommerce-settings-locks-script">
        document.addEventListener('DOMContentLoaded', function () {
            var fieldKeys = {
                woocommerce: 'field_meza_business_woocommerce',
                productIndexing: 'field_meza_business_product_indexing',
                store: 'field_meza_business_store',
                accounts: 'field_meza_business_accounts'
            };

            function getField(fieldKey) {
                return document.querySelector('.acf-field[data-key="' + fieldKey + '"]');
            }

            function getCheckbox(field) {
                return field ? field.querySelector('input[type="checkbox"][name^="acf["]') : null;
            }

            function getHiddenInput(field) {
                return field ? field.querySelector('input[type="hidden"][name^="acf["]') : null;
            }

            function isChecked(fieldKey) {
                var checkbox = getCheckbox(getField(fieldKey));
                return !!(checkbox && checkbox.checked);
            }

            function setLocked(fieldKey, locked) {
                var field = getField(fieldKey);
                var checkbox = getCheckbox(field);
                var hiddenInput = getHiddenInput(field);

                if (!field || !checkbox) {
                    return;
                }

                var mirror = field.querySelector('input.meza-progressive-ecommerce-mirror');

                if (locked) {
                    field.classList.add('meza-progressive-ecommerce-locked');
                    checkbox.disabled = true;
                    checkbox.setAttribute('aria-disabled', 'true');

                    if (hiddenInput) {
                        hiddenInput.disabled = true;
                    }

                    if (!mirror) {
                        mirror = document.createElement('input');
                        mirror.type = 'hidden';
                        mirror.className = 'meza-progressive-ecommerce-mirror';
                        field.appendChild(mirror);
                    }

                    mirror.name = checkbox.name;
                    mirror.value = checkbox.checked ? '1' : '0';
                    return;
                }

                field.classList.remove('meza-progressive-ecommerce-locked');
                checkbox.disabled = false;
                checkbox.removeAttribute('aria-disabled');

                if (hiddenInput) {
                    hiddenInput.disabled = false;
                }

                if (mirror) {
                    mirror.remove();
                }
            }

            function refreshLocks() {
                setLocked(fieldKeys.woocommerce, isChecked(fieldKeys.productIndexing) || isChecked(fieldKeys.store) || isChecked(fieldKeys.accounts));
                setLocked(fieldKeys.productIndexing, isChecked(fieldKeys.store) || isChecked(fieldKeys.accounts));
                setLocked(fieldKeys.store, isChecked(fieldKeys.accounts));
                setLocked(fieldKeys.accounts, false);
            }

            document.addEventListener('change', function (event) {
                var target = event.target;
                if (!(target instanceof HTMLInputElement) || target.type !== 'checkbox' || !target.name || target.name.indexOf('acf[') !== 0) {
                    return;
                }

                refreshLocks();
            });

            refreshLocks();

            if (window.acf && typeof window.acf.addAction === 'function') {
                window.acf.addAction('show_field', refreshLocks);
                window.acf.addAction('hide_field', refreshLocks);
                window.acf.addAction('append', refreshLocks);
            }
        });
    </script>
    <?php
}, 30);

if (!function_exists('meza_get_business_information_page_title')) {
    function meza_get_business_information_page_title(): string
    {
        return meza_get_business_information_menu_label();
    }
}

if (!function_exists('meza_get_business_information_admin_page_title')) {
    function meza_get_business_information_admin_page_title(): string
    {
        $title_map = [
            'business' => 'Business Information Settings',
            'nonprofit' => 'Nonprofit Information Settings',
            'conference' => 'Conference Information',
        ];

        return $title_map[meza_get_business_information_type()] ?? $title_map['business'];
    }
}

if (!function_exists('meza_get_branding_page_title')) {
    function meza_get_branding_page_title(): string
    {
        return 'Branding';
    }
}

if (!function_exists('meza_get_branding_admin_page_title')) {
    function meza_get_branding_admin_page_title(): string
    {
        return 'Branding Settings';
    }
}

if (!function_exists('meza_get_content_structure_page_title')) {
    function meza_get_content_structure_page_title(): string
    {
        return 'Configuration';
    }
}

if (!function_exists('meza_get_content_structure_admin_page_title')) {
    function meza_get_content_structure_admin_page_title(): string
    {
        return 'Configuration Settings';
    }
}

if (!function_exists('meza_shared_project_options_page_capability')) {
    function meza_shared_project_options_page_capability(): string
    {
        return 'meza_manage_shared_project_options';
    }
}

if (!function_exists('meza_get_shared_project_acf_options_page_capability')) {
    function meza_get_shared_project_acf_options_page_capability(string $menu_slug): string
    {
        $menu_slug = sanitize_key($menu_slug);

        if (in_array($menu_slug, ['business-information', 'branding'], true)) {
            return meza_shared_project_options_page_capability();
        }

        if ($menu_slug === 'content-structure') {
            return 'manage_options';
        }

        return 'manage_options';
    }
}

if (!function_exists('meza_get_shared_project_acf_options_page_parent_slug')) {
    function meza_get_shared_project_acf_options_page_parent_slug(string $menu_slug): string
    {
        $menu_slug = sanitize_key($menu_slug);

        if (in_array($menu_slug, ['business-information', 'branding'], true)) {
            return 'meza-business-settings';
        }

        if ($menu_slug === 'content-structure') {
            return 'meza-site-settings';
        }

        if (in_array($menu_slug, ['crm', 'ecommerce'], true)) {
            return 'meza-integrations-settings';
        }

        return 'options-general.php';
    }
}

if (!function_exists('meza_get_shared_project_acf_options_page_menu_slug')) {
    function meza_get_shared_project_acf_options_page_menu_slug(string $menu_slug): string
    {
        $menu_slug = sanitize_key($menu_slug);
        $parent_slug = meza_get_shared_project_acf_options_page_parent_slug($menu_slug);

        if ($menu_slug === '') {
            return '';
        }

        if (
            $parent_slug !== ''
            && $parent_slug !== 'none'
            && str_ends_with($parent_slug, '.php')
        ) {
            return $parent_slug . '?page=' . $menu_slug;
        }

        return 'admin.php?page=' . $menu_slug;
    }
}

if (!function_exists('meza_get_shared_project_acf_options_page_submenu_slug')) {
    function meza_get_shared_project_acf_options_page_submenu_slug(string $menu_slug): string
    {
        $menu_slug = sanitize_key($menu_slug);

        if ($menu_slug === '') {
            return '';
        }

        if (in_array(meza_get_shared_project_acf_options_page_parent_slug($menu_slug), ['meza-business-settings', 'meza-site-settings', 'meza-integrations-settings'], true)) {
            return $menu_slug;
        }

        return meza_get_shared_project_acf_options_page_menu_slug($menu_slug);
    }
}

if (!function_exists('meza_get_crm_page_title')) {
    function meza_get_crm_page_title(): string
    {
        return 'CRM Integration';
    }
}

if (!function_exists('meza_get_crm_admin_page_title')) {
    function meza_get_crm_admin_page_title(): string
    {
        return 'CRM Integration Settings';
    }
}

if (!function_exists('meza_get_ecommerce_page_title')) {
    function meza_get_ecommerce_page_title(): string
    {
        return 'E-Commerce';
    }
}

if (!function_exists('meza_get_ecommerce_admin_page_title')) {
    function meza_get_ecommerce_admin_page_title(): string
    {
        return 'E-Commerce Settings';
    }
}

if (!function_exists('meza_get_settings_admin_page_title_for_slug')) {
    function meza_get_settings_admin_page_title_for_slug(string $slug): string
    {
        $slug = sanitize_key($slug);

        if ($slug === 'business-information') {
            return meza_get_business_information_admin_page_title();
        }

        if ($slug === 'branding') {
            return meza_get_branding_admin_page_title();
        }

        if ($slug === 'content-structure') {
            return meza_get_content_structure_admin_page_title();
        }

        if ($slug === 'crm') {
            return meza_get_crm_admin_page_title();
        }

        if ($slug === 'ecommerce') {
            return meza_get_ecommerce_admin_page_title();
        }

        return '';
    }
}
