<?php


/**
 * Shared ACF option pages and field groups we want available on every project.
 * Version: 1.8.85
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

if (!function_exists('meza_is_nonprofit_business_type')) {
    function meza_is_nonprofit_business_type(): bool
    {
        return meza_get_business_information_type() === 'nonprofit';
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

if (!function_exists('meza_supports_profile_features')) {
    function meza_supports_profile_features(): bool
    {
        if (post_type_exists('profile')) {
            return true;
        }

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

if (!function_exists('meza_get_business_information_toggle_value')) {
    function meza_get_business_information_toggle_value(string $field_key, string $option_name, $default = 1): bool
    {
        if (
            is_admin()
            && isset($_POST['acf'])
            && is_array($_POST['acf'])
            && array_key_exists($field_key, $_POST['acf'])
        ) {
            return meza_business_information_acf_truthy(wp_unslash($_POST['acf'][$field_key]));
        }

        $saved = get_option($option_name, null);
        if ($saved !== null) {
            return meza_business_information_acf_truthy($saved);
        }

        return meza_business_information_acf_truthy($default);
    }
}

if (!function_exists('meza_get_content_model_checkbox_selection_for_field')) {
    function meza_get_content_model_checkbox_selection_for_field($field_key): ?array
    {
        if (!is_admin() || !isset($_POST['acf']) || !is_array($_POST['acf'])) {
            return null;
        }

        $field_keys = is_array($field_key) ? $field_key : [$field_key];
        $normalized = [];
        $has_value = false;

        foreach ($field_keys as $candidate_field_key) {
            $candidate_field_key = sanitize_key((string) $candidate_field_key);
            if ($candidate_field_key === '' || !array_key_exists($candidate_field_key, $_POST['acf'])) {
                continue;
            }

            $has_value = true;
            $value = wp_unslash($_POST['acf'][$candidate_field_key]);
            $items = is_array($value) ? $value : [$value];

            foreach ($items as $item) {
                $item = sanitize_key(trim((string) $item));
                if ($item === '') {
                    continue;
                }

                $normalized[$item] = $item;
            }
        }

        if (!$has_value) {
            return null;
        }

        return array_values($normalized);
    }
}

if (!function_exists('meza_is_content_model_checkbox_selection_checked')) {
    function meza_is_content_model_checkbox_selection_checked($field_key, string $identifier): ?bool
    {
        $selection = meza_get_content_model_checkbox_selection_for_field($field_key);
        if ($selection === null) {
            return null;
        }

        return in_array(sanitize_key($identifier), $selection, true);
    }
}

if (!function_exists('meza_is_saved_content_model_checkbox_selection_checked')) {
    function meza_get_saved_content_model_checkbox_selection(string $option_name, ?bool &$has_value = null): array
    {
        $missing_sentinel = '__meza_content_model_option_missing__';
        $saved = get_option($option_name, $missing_sentinel);

        if ($saved === $missing_sentinel) {
            $has_value = false;
            return [];
        }

        $has_value = true;
        $normalized = [];
        $items = is_array($saved) ? $saved : [$saved];

        foreach ($items as $item) {
            $item = sanitize_key(trim((string) $item));
            if ($item === '') {
                continue;
            }

            $normalized[$item] = $item;
        }

        return array_values($normalized);
    }
}

if (!function_exists('meza_is_saved_content_model_checkbox_selection_checked')) {
    function meza_is_saved_content_model_checkbox_selection_checked($option_name, string $identifier): ?bool
    {
        $option_names = is_array($option_name) ? $option_name : [$option_name];
        $normalized = [];
        $has_value = false;

        foreach ($option_names as $candidate_option_name) {
            $candidate_option_name = trim((string) $candidate_option_name);
            if ($candidate_option_name === '') {
                continue;
            }

            $saved = meza_get_saved_content_model_checkbox_selection($candidate_option_name, $candidate_has_value);
            if (!$candidate_has_value) {
                continue;
            }

            $has_value = true;

            foreach ($saved as $item) {
                $item = sanitize_key(trim((string) $item));
                if ($item === '') {
                    continue;
                }

                $normalized[$item] = $item;
            }
        }

        if (!$has_value) {
            return null;
        }

        return in_array(sanitize_key($identifier), array_values($normalized), true);
    }
}

if (!function_exists('meza_are_services_enabled')) {
    function meza_are_services_enabled(): bool
    {
        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            ['field_meza_content_model_built_in_post_types', 'field_meza_content_model_built_in_non_indexable_post_types'],
            'service'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            ['options_content_model_built_in_post_types', 'options_content_model_built_in_non_indexable_post_types'],
            'service'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        return meza_get_business_information_toggle_value('field_6a0947aec1f52', 'options_services', 1);
    }
}

if (!function_exists('meza_are_services_indexable')) {
    function meza_are_services_indexable(): bool
    {
        if (!meza_are_services_enabled()) {
            return false;
        }

        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            'field_meza_content_model_indexable_post_types',
            'service'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            'options_content_model_indexable_post_types',
            'service'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        return meza_get_business_information_toggle_value('field_6a0947aec1f56', 'options_services_indexable', 1);
    }
}

if (!function_exists('meza_are_localities_enabled')) {
    function meza_are_localities_enabled(): bool
    {
        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            ['field_meza_content_model_built_in_taxonomies', 'field_meza_content_model_built_in_non_indexable_taxonomies'],
            'locality'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            ['options_content_model_built_in_taxonomies', 'options_content_model_built_in_non_indexable_taxonomies'],
            'locality'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        return meza_get_business_information_toggle_value('field_6a09483cc8282', 'options_localities', 1);
    }
}

if (!function_exists('meza_are_localities_indexable')) {
    function meza_are_localities_indexable(): bool
    {
        if (!meza_are_localities_enabled()) {
            return false;
        }

        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            'field_meza_content_model_indexable_taxonomies',
            'locality'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            'options_content_model_indexable_taxonomies',
            'locality'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        return meza_get_business_information_toggle_value('field_6a09483cc8285', 'options_localities_indexable', 1);
    }
}

if (!function_exists('meza_are_posts_enabled')) {
    function meza_are_posts_enabled(): bool
    {
        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            ['field_meza_content_model_built_in_post_types', 'field_meza_content_model_built_in_non_indexable_post_types'],
            'post'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            ['options_content_model_built_in_post_types', 'options_content_model_built_in_non_indexable_post_types'],
            'post'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        return false;
    }
}

if (!function_exists('meza_are_posts_indexable')) {
    function meza_are_posts_indexable(): bool
    {
        if (!meza_are_posts_enabled()) {
            return false;
        }

        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            'field_meza_content_model_indexable_post_types',
            'post'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            'options_content_model_indexable_post_types',
            'post'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        return false;
    }
}

if (!function_exists('meza_are_products_enabled')) {
    function meza_are_products_enabled(): bool
    {
        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            ['field_meza_content_model_built_in_post_types', 'field_meza_content_model_built_in_non_indexable_post_types'],
            'product'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            ['options_content_model_built_in_post_types', 'options_content_model_built_in_non_indexable_post_types'],
            'product'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        $saved = get_option('options_woocommerce', null);
        if ($saved !== null) {
            return meza_business_information_acf_truthy($saved);
        }

        return meza_get_saved_business_information_legacy_ecommerce_selection() !== [];
    }
}

if (!function_exists('meza_are_products_indexable')) {
    function meza_are_products_indexable(): bool
    {
        if (!meza_are_products_enabled()) {
            return false;
        }

        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            'field_meza_content_model_indexable_post_types',
            'product'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            'options_content_model_indexable_post_types',
            'product'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        $saved = get_option('options_product_indexing', null);

        return $saved !== null
            ? meza_business_information_acf_truthy($saved)
            : in_array('store_functionality', meza_get_saved_business_information_legacy_ecommerce_selection(), true);
    }
}

if (!function_exists('meza_are_product_categories_enabled')) {
    function meza_are_product_categories_enabled(): bool
    {
        if (!meza_are_products_enabled()) {
            return false;
        }

        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            ['field_meza_content_model_built_in_taxonomies', 'field_meza_content_model_built_in_non_indexable_taxonomies'],
            'product_cat'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            ['options_content_model_built_in_taxonomies', 'options_content_model_built_in_non_indexable_taxonomies'],
            'product_cat'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        return false;
    }
}

if (!function_exists('meza_are_product_categories_indexable')) {
    function meza_are_product_categories_indexable(): bool
    {
        if (!meza_are_product_categories_enabled()) {
            return false;
        }

        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            'field_meza_content_model_indexable_taxonomies',
            'product_cat'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            'options_content_model_indexable_taxonomies',
            'product_cat'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        return false;
    }
}

if (!function_exists('meza_are_product_tags_enabled')) {
    function meza_are_product_tags_enabled(): bool
    {
        if (!meza_are_products_enabled() || !meza_are_product_categories_enabled()) {
            return false;
        }

        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            ['field_meza_content_model_built_in_taxonomies', 'field_meza_content_model_built_in_non_indexable_taxonomies'],
            'product_tag'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            ['options_content_model_built_in_taxonomies', 'options_content_model_built_in_non_indexable_taxonomies'],
            'product_tag'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        return false;
    }
}

if (!function_exists('meza_are_product_tags_indexable')) {
    function meza_are_product_tags_indexable(): bool
    {
        if (!meza_are_product_tags_enabled()) {
            return false;
        }

        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            'field_meza_content_model_indexable_taxonomies',
            'product_tag'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            'options_content_model_indexable_taxonomies',
            'product_tag'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        return false;
    }
}

if (!function_exists('meza_are_product_brands_enabled')) {
    function meza_are_product_brands_enabled(): bool
    {
        if (!meza_are_products_enabled()) {
            return false;
        }

        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            ['field_meza_content_model_built_in_taxonomies', 'field_meza_content_model_built_in_non_indexable_taxonomies'],
            'product_brand'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            ['options_content_model_built_in_taxonomies', 'options_content_model_built_in_non_indexable_taxonomies'],
            'product_brand'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        return false;
    }
}

if (!function_exists('meza_are_product_brands_indexable')) {
    function meza_are_product_brands_indexable(): bool
    {
        if (!meza_are_product_brands_enabled()) {
            return false;
        }

        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            'field_meza_content_model_indexable_taxonomies',
            'product_brand'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            'options_content_model_indexable_taxonomies',
            'product_brand'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        return false;
    }
}

if (!function_exists('meza_are_product_reviews_enabled')) {
    function meza_are_product_reviews_enabled(): bool
    {
        if (!meza_are_products_enabled()) {
            return false;
        }

        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            ['field_meza_content_model_built_in_post_types', 'field_meza_content_model_built_in_non_indexable_post_types'],
            'product-review'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            ['options_content_model_built_in_post_types', 'options_content_model_built_in_non_indexable_post_types'],
            'product-review'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        return false;
    }
}

if (!function_exists('meza_are_post_categories_enabled')) {
    function meza_are_post_categories_enabled(): bool
    {
        if (!meza_are_posts_enabled()) {
            return false;
        }

        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            ['field_meza_content_model_built_in_taxonomies', 'field_meza_content_model_built_in_non_indexable_taxonomies'],
            'category'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            ['options_content_model_built_in_taxonomies', 'options_content_model_built_in_non_indexable_taxonomies'],
            'category'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        return meza_get_business_information_toggle_value('field_meza_posts_categories', 'options_posts_categories', 0);
    }
}

if (!function_exists('meza_are_post_categories_indexable')) {
    function meza_are_post_categories_indexable(): bool
    {
        if (!meza_are_post_categories_enabled()) {
            return false;
        }

        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            'field_meza_content_model_indexable_taxonomies',
            'category'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            'options_content_model_indexable_taxonomies',
            'category'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        return meza_get_business_information_toggle_value('field_meza_posts_categories_indexable', 'options_posts_categories_indexable', 0);
    }
}

if (!function_exists('meza_are_post_tags_enabled')) {
    function meza_are_post_tags_enabled(): bool
    {
        if (!meza_are_posts_enabled() || !meza_are_post_categories_enabled()) {
            return false;
        }

        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            ['field_meza_content_model_built_in_taxonomies', 'field_meza_content_model_built_in_non_indexable_taxonomies'],
            'post_tag'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            ['options_content_model_built_in_taxonomies', 'options_content_model_built_in_non_indexable_taxonomies'],
            'post_tag'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        return meza_get_business_information_toggle_value('field_meza_posts_tags', 'options_posts_tags', 0);
    }
}

if (!function_exists('meza_are_post_tags_indexable')) {
    function meza_are_post_tags_indexable(): bool
    {
        if (!meza_are_post_tags_enabled()) {
            return false;
        }

        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            'field_meza_content_model_indexable_taxonomies',
            'post_tag'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            'options_content_model_indexable_taxonomies',
            'post_tag'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        $saved = get_option('options_posts_tags_indexable', null);

        return $saved !== null ? meza_business_information_acf_truthy($saved) : false;
    }
}

if (!function_exists('meza_is_event_functionality_enabled')) {
    function meza_is_event_functionality_enabled(): bool
    {
        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            ['field_meza_content_model_built_in_post_types', 'field_meza_content_model_built_in_non_indexable_post_types'],
            'event'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            ['options_content_model_built_in_post_types', 'options_content_model_built_in_non_indexable_post_types'],
            'event'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        if (
            is_admin()
            && isset($_POST['acf'])
            && is_array($_POST['acf'])
            && array_key_exists('field_meza_include_event_functionality', $_POST['acf'])
        ) {
            return meza_business_information_acf_truthy(
                wp_unslash($_POST['acf']['field_meza_include_event_functionality'])
            );
        }

        $saved = get_option('options_events', null);
        if ($saved !== null) {
            return meza_business_information_acf_truthy($saved);
        }

        return meza_business_information_acf_truthy(get_option('options_include_event_functionality', 0));
    }
}

if (!function_exists('meza_events_have_after_content')) {
    function meza_events_have_after_content(): bool
    {
        if (!meza_is_event_functionality_enabled()) {
            return false;
        }

        if (
            is_admin()
            && isset($_POST['acf'])
            && is_array($_POST['acf'])
            && array_key_exists('field_meza_event_after_content', $_POST['acf'])
        ) {
            return meza_business_information_acf_truthy(
                wp_unslash($_POST['acf']['field_meza_event_after_content'])
            );
        }

        $saved = get_option('options_events_after', null);
        if ($saved !== null) {
            return meza_business_information_acf_truthy($saved);
        }

        return false;
    }
}

if (!function_exists('meza_seasons_have_after_content')) {
    function meza_seasons_have_after_content(): bool
    {
        if (!function_exists('meza_is_nonprofit_business_type') || !meza_is_nonprofit_business_type()) {
            return false;
        }

        if (
            is_admin()
            && isset($_POST['acf'])
            && is_array($_POST['acf'])
            && array_key_exists('field_meza_enable_season_after_content', $_POST['acf'])
        ) {
            return meza_business_information_acf_truthy(
                wp_unslash($_POST['acf']['field_meza_enable_season_after_content'])
            );
        }

        $saved = get_option('options_season_after', null);
        if ($saved !== null) {
            return meza_business_information_acf_truthy($saved);
        }

        return false;
    }
}

if (!function_exists('meza_annual_years_have_end_of_year_content')) {
    function meza_annual_years_have_end_of_year_content(): bool
    {
        return meza_seasons_have_after_content()
            && function_exists('meza_is_event_functionality_enabled')
            && meza_is_event_functionality_enabled();
    }
}

if (!function_exists('meza_get_annual_year_settings')) {
    function meza_get_annual_year_settings(): array
    {
        $get_setting_value = static function (string $field_key, string $option_name, $default_value = null) {
            if (
                is_admin()
                && isset($_POST['acf'])
                && is_array($_POST['acf'])
                && array_key_exists($field_key, $_POST['acf'])
            ) {
                return wp_unslash($_POST['acf'][$field_key]);
            }

            $saved_value = get_option('options_' . $option_name, null);

            return $saved_value !== null ? $saved_value : $default_value;
        };

        $normalize_month = static function ($value): int {
            if (is_array($value)) {
                $value = reset($value);
            }

            $month = is_scalar($value) ? (int) $value : 1;

            return $month >= 1 && $month <= 12 ? $month : 1;
        };

        return [
            'start_month' => $normalize_month($get_setting_value(
                'field_meza_annual_year_start_month',
                'annual_year_start_month',
                1
            )),
        ];
    }
}

if (!function_exists('meza_get_season_definition_settings')) {
    function meza_get_season_definition_settings(): array
    {
        $settings = meza_get_annual_year_settings();

        return [
            'mode' => 'annual_year_month',
            'annual_year_start_month' => (int) ($settings['start_month'] ?? 1),
        ];
    }
}

if (!function_exists('meza_get_annual_year_boundary_context')) {
    function meza_get_annual_year_boundary_context(?int $timestamp = null): array
    {
        $settings = meza_get_annual_year_settings();
        $start_month = max(1, min(12, (int) ($settings['start_month'] ?? 1)));
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $timestamp = $timestamp ?? current_time('timestamp');

        $now = (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
        $boundary_this_year = new DateTimeImmutable(
            sprintf('%04d-%02d-01 00:00:00', (int) $now->format('Y'), $start_month),
            $timezone
        );

        if ($now < $boundary_this_year) {
            $current_start = $boundary_this_year->modify('-1 year');
            $next_start = $boundary_this_year;
        } else {
            $current_start = $boundary_this_year;
            $next_start = $boundary_this_year->modify('+1 year');
        }

        return [
            'start_month' => $start_month,
            'current_start_timestamp' => $current_start->getTimestamp(),
            'next_start_timestamp' => $next_start->getTimestamp(),
            'current_start_iso' => $current_start->format('Y-m-d H:i:s'),
            'next_start_iso' => $next_start->format('Y-m-d H:i:s'),
        ];
    }
}

if (!function_exists('meza_get_event_boundary_schedule')) {
    function meza_get_event_boundary_schedule(int $event_id): array
    {
        $event_id = max(0, $event_id);
        if ($event_id <= 0 || get_post_type($event_id) !== 'event') {
            return [];
        }

        $schedule = function_exists('\MZ\Recurring\mz_get_event_schedule')
            ? \MZ\Recurring\mz_get_event_schedule($event_id)
            : [];

        $start_timestamp = 0;
        $end_timestamp = 0;

        if (is_array($schedule)) {
            $start_object = $schedule['start_obj'] ?? null;
            $end_object = $schedule['end_obj'] ?? null;

            if ($start_object instanceof DateTimeInterface) {
                $start_timestamp = $start_object->getTimestamp();
            } elseif (!empty($schedule['start_datetime'])) {
                $start_timestamp = (int) strtotime((string) $schedule['start_datetime']);
            } elseif (!empty($schedule['date_ymd'])) {
                $start_timestamp = (int) strtotime((string) $schedule['date_ymd'] . ' 00:00:00');
            }

            if ($end_object instanceof DateTimeInterface) {
                $end_timestamp = $end_object->getTimestamp();
            } elseif (!empty($schedule['upcoming_until_mysql'])) {
                $end_timestamp = (int) strtotime((string) $schedule['upcoming_until_mysql']);
            } elseif (!empty($schedule['end_datetime'])) {
                $end_timestamp = (int) strtotime((string) $schedule['end_datetime']);
            } elseif (!empty($schedule['date_ymd'])) {
                $end_timestamp = (int) strtotime((string) $schedule['date_ymd'] . ' 23:59:59');
            }
        }

        if ($start_timestamp <= 0) {
            $date_start = trim((string) get_post_meta($event_id, 'date_start', true));
            if ($date_start !== '') {
                $start_timestamp = (int) strtotime($date_start . ' 00:00:00');
            }
        }

        if ($end_timestamp <= 0) {
            $date_end = trim((string) get_post_meta($event_id, 'date_end', true));
            if ($date_end !== '') {
                $end_timestamp = (int) strtotime($date_end . ' 23:59:59');
            }
        }

        if ($start_timestamp <= 0 || $end_timestamp <= 0) {
            return [];
        }

        if ($end_timestamp < $start_timestamp) {
            $end_timestamp = $start_timestamp;
        }

        return [
            'event_id' => $event_id,
            'start_timestamp' => $start_timestamp,
            'end_timestamp' => $end_timestamp,
        ];
    }
}

if (!function_exists('meza_get_last_event_before_annual_year_rollover')) {
    function meza_get_last_event_before_annual_year_rollover(int $rollover_timestamp): array
    {
        if (
            $rollover_timestamp <= 0
            || !function_exists('meza_is_event_functionality_enabled')
            || !meza_is_event_functionality_enabled()
        ) {
            return [];
        }

        $candidate_ids = get_posts([
            'post_type' => 'event',
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'order' => 'DESC',
            'meta_key' => 'date_start',
            'meta_type' => 'DATE',
            'meta_query' => [
                [
                    'key' => 'date_start',
                    'value' => wp_date('Y-m-d', $rollover_timestamp),
                    'compare' => '<',
                    'type' => 'DATE',
                ],
            ],
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'cache_results' => false,
        ]);

        $last_event = [];

        foreach ((array) $candidate_ids as $candidate_id) {
            $schedule = meza_get_event_boundary_schedule((int) $candidate_id);
            $event_end_timestamp = (int) ($schedule['end_timestamp'] ?? 0);

            if ($event_end_timestamp <= 0 || $event_end_timestamp >= $rollover_timestamp) {
                continue;
            }

            if ($last_event === [] || $event_end_timestamp > (int) ($last_event['end_timestamp'] ?? 0)) {
                $last_event = $schedule;
            }
        }

        return $last_event;
    }
}

if (!function_exists('meza_get_annual_year_content_context')) {
    function meza_get_annual_year_content_context(int $post_id = 0): array
    {
        $resolved_post_id = $post_id > 0 ? $post_id : (int) get_queried_object_id();
        $is_enabled = meza_seasons_have_after_content();
        $settings = meza_get_annual_year_settings();
        $boundaries = meza_get_annual_year_boundary_context();
        $current_timestamp = current_time('timestamp');
        $events_enabled = function_exists('meza_is_event_functionality_enabled')
            && meza_is_event_functionality_enabled();

        $context = [
            'phase' => $is_enabled ? 'current' : 'none',
            'resolved_slot' => 'default',
            'annual_year_start_month' => (int) ($settings['start_month'] ?? 1),
            'current_start_timestamp' => (int) ($boundaries['current_start_timestamp'] ?? 0),
            'next_start_timestamp' => (int) ($boundaries['next_start_timestamp'] ?? 0),
            'current_start_iso' => (string) ($boundaries['current_start_iso'] ?? ''),
            'next_start_iso' => (string) ($boundaries['next_start_iso'] ?? ''),
            'last_event_id_before_rollover' => 0,
            'last_event_end_timestamp_before_rollover' => 0,
            'is_end_of_year' => false,
            'events_enabled' => $events_enabled,
            'post_id' => $resolved_post_id,
            'post_type' => $resolved_post_id > 0 ? (string) get_post_type($resolved_post_id) : '',
            'is_enabled' => $is_enabled,
        ];
        $default_context = $context;

        if ($is_enabled && $events_enabled) {
            $last_event = meza_get_last_event_before_annual_year_rollover((int) $context['next_start_timestamp']);
            $last_event_end_timestamp = (int) ($last_event['end_timestamp'] ?? 0);

            if ($last_event !== []) {
                $context['last_event_id_before_rollover'] = (int) ($last_event['event_id'] ?? 0);
                $context['last_event_end_timestamp_before_rollover'] = $last_event_end_timestamp;

                if (
                    $last_event_end_timestamp > 0
                    && $current_timestamp > $last_event_end_timestamp
                    && $current_timestamp < (int) $context['next_start_timestamp']
                ) {
                    $context['phase'] = 'end_of_year';
                    $context['resolved_slot'] = 'end_of_year';
                    $context['is_end_of_year'] = true;
                }
            }
        }

        $context = apply_filters('meza_get_annual_year_content_context', $context, $resolved_post_id);
        if (!is_array($context)) {
            $context = [];
        }

        $context = wp_parse_args($context, $default_context);
        $phase = sanitize_key((string) ($context['phase'] ?? 'none'));
        if (!$is_enabled) {
            $phase = 'none';
        } elseif (!in_array($phase, ['current', 'end_of_year', 'none'], true)) {
            $phase = 'current';
        }

        $resolved_slot = sanitize_key((string) ($context['resolved_slot'] ?? 'default'));
        if (!in_array($resolved_slot, ['default', 'end_of_year'], true)) {
            $resolved_slot = $phase === 'end_of_year' ? 'end_of_year' : 'default';
        }

        $context['phase'] = $phase;
        $context['resolved_slot'] = $resolved_slot;
        $context['annual_year_start_month'] = max(1, min(12, (int) ($context['annual_year_start_month'] ?? 1)));
        $context['current_start_timestamp'] = (int) ($context['current_start_timestamp'] ?? 0);
        $context['next_start_timestamp'] = (int) ($context['next_start_timestamp'] ?? 0);
        $context['last_event_id_before_rollover'] = max(0, (int) ($context['last_event_id_before_rollover'] ?? 0));
        $context['last_event_end_timestamp_before_rollover'] = (int) ($context['last_event_end_timestamp_before_rollover'] ?? 0);
        $context['is_end_of_year'] = $phase === 'end_of_year';
        $context['events_enabled'] = !empty($context['events_enabled']);
        $context['post_id'] = $resolved_post_id;
        $context['post_type'] = $resolved_post_id > 0 ? (string) get_post_type($resolved_post_id) : '';
        $context['is_enabled'] = $is_enabled;

        return $context;
    }
}

if (!function_exists('meza_is_end_of_year_content_context')) {
    function meza_is_end_of_year_content_context(int $post_id = 0): bool
    {
        return sanitize_key((string) (meza_get_annual_year_content_context($post_id)['phase'] ?? 'none')) === 'end_of_year';
    }
}

if (!function_exists('meza_get_season_content_context')) {
    function meza_get_season_content_context(int $post_id = 0): array
    {
        $resolved_post_id = $post_id > 0 ? $post_id : (int) get_queried_object_id();
        $annual_year_context = meza_get_annual_year_content_context($resolved_post_id);
        $is_enabled = !empty($annual_year_context['is_enabled']);
        $default_context = [
            'phase' => $is_enabled
                ? (!empty($annual_year_context['is_end_of_year']) ? 'post' : 'current')
                : 'none',
            'resolved_slot' => !empty($annual_year_context['is_end_of_year']) ? 'end_of_year' : 'default',
            'season_term_id' => 0,
            'season_slug' => '',
            'post_id' => $resolved_post_id,
            'post_type' => $resolved_post_id > 0 ? (string) get_post_type($resolved_post_id) : '',
            'is_enabled' => $is_enabled,
            'annual_year_context' => $annual_year_context,
        ];

        $context = apply_filters('meza_get_season_content_context', $default_context, $resolved_post_id);
        if (!is_array($context)) {
            $context = [];
        }

        $context = wp_parse_args($context, $default_context);
        $phase = sanitize_key((string) ($context['phase'] ?? ''));

        if (!$is_enabled) {
            $phase = 'none';
        } elseif (in_array($phase, ['active', 'in_season'], true)) {
            $phase = 'current';
        } elseif (!in_array($phase, ['pre', 'current', 'post', 'none'], true)) {
            $phase = $default_context['phase'];
        }

        $resolved_slot = sanitize_key((string) ($context['resolved_slot'] ?? ''));
        if (!in_array($resolved_slot, ['default', 'end_of_year', 'post_season'], true)) {
            $resolved_slot = $phase === 'post' ? 'end_of_year' : 'default';
        }

        $context['phase'] = $phase;
        $context['resolved_slot'] = $resolved_slot;
        $context['season_term_id'] = max(0, (int) ($context['season_term_id'] ?? 0));
        $context['season_slug'] = sanitize_key((string) ($context['season_slug'] ?? ''));
        $context['post_id'] = $resolved_post_id;
        $context['post_type'] = $resolved_post_id > 0 ? (string) get_post_type($resolved_post_id) : '';
        $context['is_enabled'] = $is_enabled;
        $context['annual_year_context'] = is_array($context['annual_year_context'] ?? null)
            ? $context['annual_year_context']
            : $annual_year_context;

        return $context;
    }
}

if (!function_exists('meza_get_temporal_content_season_phase')) {
    function meza_get_temporal_content_season_phase(int $post_id = 0): string
    {
        $context = meza_get_season_content_context($post_id);
        $phase = sanitize_key((string) ($context['phase'] ?? 'none'));

        if ($phase === 'post') {
            return 'post';
        }

        if (in_array($phase, ['current', 'active', 'in_season'], true)) {
            return 'current';
        }

        return in_array($phase, ['pre', 'none'], true) ? $phase : 'none';
    }
}

if (!function_exists('meza_is_post_season_content_context')) {
    function meza_is_post_season_content_context(int $post_id = 0): bool
    {
        return meza_get_temporal_content_season_phase($post_id) === 'post';
    }
}

if (!function_exists('meza_is_event_settings_acf_submission')) {
    function meza_is_event_settings_acf_submission(): bool
    {
        return isset($_POST['acf'])
            && is_array($_POST['acf'])
            && (
                array_key_exists('field_meza_include_event_functionality', $_POST['acf'])
                || array_key_exists('field_meza_indexable_events', $_POST['acf'])
                || array_key_exists('field_meza_event_speakers_topics', $_POST['acf'])
                || array_key_exists('field_meza_event_after_content', $_POST['acf'])
                || array_key_exists('field_meza_enable_season_after_content', $_POST['acf'])
            );
    }
}

if (!function_exists('meza_is_content_structure_options_screen')) {
    function meza_is_content_structure_options_screen(): bool
    {
        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';

        return in_array($page, ['content-model', 'content-structure'], true);
    }
}

if (!function_exists('meza_is_content_model_read_only_for_current_user')) {
    function meza_is_content_model_read_only_for_current_user(): bool
    {
        return is_admin()
            && function_exists('meza_is_site_manager_user')
            && meza_is_site_manager_user(wp_get_current_user())
            && meza_is_content_structure_options_screen();
    }
}

if (!function_exists('meza_is_content_structure_acf_submission')) {
    function meza_is_content_structure_acf_submission(): bool
    {
        return meza_is_content_structure_options_screen();
    }
}

if (!function_exists('meza_are_events_indexable')) {
    function meza_are_events_indexable(): bool
    {
        if (!meza_is_event_functionality_enabled()) {
            return false;
        }

        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            'field_meza_content_model_indexable_post_types',
            'event'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            'options_content_model_indexable_post_types',
            'event'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        if (
            is_admin()
            && isset($_POST['acf'])
            && is_array($_POST['acf'])
            && array_key_exists('field_meza_indexable_events', $_POST['acf'])
        ) {
            return meza_business_information_acf_truthy(
                wp_unslash($_POST['acf']['field_meza_indexable_events'])
            );
        }

        $saved = get_option('options_events_indexable', null);
        if ($saved !== null) {
            return meza_business_information_acf_truthy($saved);
        }

        return meza_business_information_acf_truthy(get_option('options_indexable_events', 0));
    }
}

if (!function_exists('meza_events_have_speakers_topics')) {
    function meza_events_have_speakers_topics(): bool
    {
        if (!meza_is_event_functionality_enabled()) {
            return false;
        }

        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            ['field_meza_content_model_built_in_taxonomies', 'field_meza_content_model_built_in_non_indexable_taxonomies'],
            'topic'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            ['options_content_model_built_in_taxonomies', 'options_content_model_built_in_non_indexable_taxonomies'],
            'topic'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        if (
            is_admin()
            && isset($_POST['acf'])
            && is_array($_POST['acf'])
            && array_key_exists('field_meza_event_speakers_topics', $_POST['acf'])
        ) {
            return meza_business_information_acf_truthy(
                wp_unslash($_POST['acf']['field_meza_event_speakers_topics'])
            );
        }

        $saved = get_option('options_events_speakers', null);
        if ($saved !== null) {
            return meza_business_information_acf_truthy($saved);
        }

        return meza_business_information_acf_truthy(get_option('options_event_speakers_topics', 0));
    }
}

if (!function_exists('meza_are_topics_indexable')) {
    function meza_are_topics_indexable(): bool
    {
        if (!meza_events_have_speakers_topics()) {
            return false;
        }

        $content_model_value = meza_is_content_model_checkbox_selection_checked(
            'field_meza_content_model_indexable_taxonomies',
            'topic'
        );
        if ($content_model_value !== null) {
            return $content_model_value;
        }

        $saved_content_model_value = meza_is_saved_content_model_checkbox_selection_checked(
            'options_content_model_indexable_taxonomies',
            'topic'
        );
        if ($saved_content_model_value !== null) {
            return $saved_content_model_value;
        }

        $saved = get_option('options_topics_indexable', null);

        return $saved !== null ? meza_business_information_acf_truthy($saved) : false;
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

        if (!$normalized['woocommerce']) {
            $normalized['product_indexing'] = false;
            $normalized['store'] = false;
            $normalized['accounts'] = false;
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
        $saved_products_enabled = meza_is_saved_content_model_checkbox_selection_checked(
            ['options_content_model_built_in_post_types', 'options_content_model_built_in_non_indexable_post_types'],
            'product'
        );
        $saved_products_indexable = meza_is_saved_content_model_checkbox_selection_checked(
            'options_content_model_indexable_post_types',
            'product'
        );

        $settings = [
            'woocommerce' => ($saved_products_enabled !== null)
                ? $saved_products_enabled
                : (($woocommerce !== null)
                    ? meza_business_information_acf_truthy($woocommerce)
                    : ($legacy_selection !== [])),
            'product_indexing' => ($saved_products_indexable !== null)
                ? (($saved_products_enabled ?? true) ? $saved_products_indexable : false)
                : (($product_indexing !== null)
                    ? meza_business_information_acf_truthy($product_indexing)
                    : in_array('store_functionality', $legacy_selection, true)),
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
                'woocommerce' => function_exists('meza_are_products_enabled') ? meza_are_products_enabled() : false,
                'product_indexing' => function_exists('meza_are_products_indexable') ? meza_are_products_indexable() : false,
                'store' => meza_business_information_acf_truthy($posted_fields['field_meza_business_store'] ?? 0),
                'accounts' => meza_business_information_acf_truthy($posted_fields['field_meza_business_accounts'] ?? 0),
            ];

            return meza_normalize_business_information_ecommerce_settings($settings);
        }

        if (is_admin() && meza_is_content_structure_acf_submission()) {
            $saved_settings = meza_get_saved_business_information_ecommerce_settings();

            return meza_normalize_business_information_ecommerce_settings([
                'woocommerce' => function_exists('meza_are_products_enabled') ? meza_are_products_enabled() : !empty($saved_settings['woocommerce']),
                'product_indexing' => function_exists('meza_are_products_indexable') ? meza_are_products_indexable() : !empty($saved_settings['product_indexing']),
                'store' => !empty($saved_settings['store']),
                'accounts' => !empty($saved_settings['accounts']),
            ]);
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
        if (
            is_admin()
            && isset($_POST['acf'])
            && is_array($_POST['acf'])
            && (meza_is_business_information_acf_submission() || meza_is_content_structure_acf_submission())
        ) {
            return meza_business_information_enables_ecommerce();
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

if (!function_exists('meza_get_business_settings_menu_label')) {
    function meza_get_business_settings_menu_label(): string
    {
        return meza_get_business_information_type() === 'nonprofit'
            ? 'Nonprofit'
            : 'Business';
    }
}

if (!function_exists('meza_get_business_settings_menu_icon')) {
    function meza_get_business_settings_menu_icon(): string
    {
        return meza_get_business_information_type() === 'nonprofit'
            ? 'dashicons-heart'
            : 'dashicons-store';
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

add_action('admin_head', function (): void {
    if (!is_admin()) {
        return;
    }

    if (!meza_is_content_structure_options_screen()) {
        return;
    }

    $is_read_only = meza_is_content_model_read_only_for_current_user();

    $taxonomy_term_group_map = [];
    if (
        function_exists('meza_get_content_model_taxonomy_term_management_taxonomy_definitions')
        && function_exists('meza_get_content_model_taxonomy_term_management_term_groups')
        && function_exists('meza_get_content_model_taxonomy_term_management_field_key')
    ) {
        foreach (meza_get_content_model_taxonomy_term_management_taxonomy_definitions() as $taxonomy => $definition) {
            unset($definition);

            $field_key = meza_get_content_model_taxonomy_term_management_field_key((string) $taxonomy);
            $groups = meza_get_content_model_taxonomy_term_management_term_groups((string) $taxonomy);

            if ($field_key === '' || $groups === []) {
                continue;
            }

            $taxonomy_term_group_map[$field_key] = $groups;
        }
    }
    ?>
    <style id="meza-content-model-field-group-row-layout">
        .acf-field.meza-content-model-field-group-management-bucket .acf-input {
            padding-top: 10px;
            padding-bottom: 10px;
        }

        .acf-field.meza-content-model-field-group-management-bucket .acf-checkbox-list {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            column-gap: 18px;
            row-gap: 8px;
            align-items: start;
            margin: 0;
        }

        .acf-field.meza-content-model-field-group-management-bucket .acf-checkbox-list.acf-bl:before,
        .acf-field.meza-content-model-field-group-management-bucket .acf-checkbox-list.acf-bl:after,
        .acf-field.meza-content-model-field-group-management-bucket .acf-checkbox-list.acf-hl:before,
        .acf-field.meza-content-model-field-group-management-bucket .acf-checkbox-list.acf-hl:after,
        .acf-field.meza-content-model-field-group-management-bucket .acf-checkbox-list.acf-cf:before,
        .acf-field.meza-content-model-field-group-management-bucket .acf-checkbox-list.acf-cf:after {
            content: none;
        }

        .acf-field.meza-content-model-field-group-management-bucket .acf-checkbox-list li {
            margin: 0;
            min-width: 0;
            break-inside: avoid;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .acf-field[data-key="field_meza_content_model_built_in_post_types"] .acf-checkbox-list li,
        .acf-field[data-key="field_meza_content_model_built_in_non_indexable_post_types"] .acf-checkbox-list li,
        .acf-field[data-key="field_meza_content_model_built_in_taxonomies"] .acf-checkbox-list li,
        .acf-field[data-key="field_meza_content_model_built_in_non_indexable_taxonomies"] .acf-checkbox-list li {
            align-items: center;
            flex-direction: row;
            justify-content: space-between;
            gap: 12px;
        }

        .acf-field[data-key="field_meza_content_model_built_in_post_types"] .acf-checkbox-list,
        .acf-field[data-key="field_meza_content_model_built_in_non_indexable_post_types"] .acf-checkbox-list,
        .acf-field[data-key="field_meza_content_model_built_in_taxonomies"] .acf-checkbox-list,
        .acf-field[data-key="field_meza_content_model_built_in_non_indexable_taxonomies"] .acf-checkbox-list {
            grid-template-columns: minmax(0, 1fr);
        }

        .acf-field[data-key="field_meza_content_model_custom_post_types"] .acf-checkbox-list,
        .acf-field[data-key="field_meza_content_model_custom_taxonomies"] .acf-checkbox-list {
            grid-template-columns: minmax(0, 1fr);
        }

        .acf-field[data-key="field_meza_content_model_custom_post_types"] .acf-checkbox-list li,
        .acf-field[data-key="field_meza_content_model_custom_taxonomies"] .acf-checkbox-list li {
            align-items: center;
            flex-direction: row;
            justify-content: space-between;
            gap: 12px;
        }

        .acf-field.meza-content-model-field-group-management-bucket .acf-checkbox-list label {
            display: flex;
            align-items: center;
            gap: 8px;
            line-height: 1.35;
            flex: 1 1 auto;
            min-width: 0;
        }

        .acf-field[data-key="field_meza_content_model_custom_post_types"] .meza-content-model-row-switch,
        .acf-field[data-key="field_meza_content_model_custom_taxonomies"] .meza-content-model-row-switch,
        .acf-field[data-key="field_meza_content_model_built_in_post_types"] .meza-content-model-row-switch,
        .acf-field[data-key="field_meza_content_model_built_in_non_indexable_post_types"] .meza-content-model-row-switch,
        .acf-field[data-key="field_meza_content_model_built_in_taxonomies"] .meza-content-model-row-switch,
        .acf-field[data-key="field_meza_content_model_built_in_non_indexable_taxonomies"] .meza-content-model-row-switch {
            justify-content: flex-end;
            margin-top: 0;
            margin-left: 0;
        }

        .acf-field[data-key="field_meza_content_model_custom_post_types"] .meza-content-model-row-switch,
        .acf-field[data-key="field_meza_content_model_custom_taxonomies"] .meza-content-model-row-switch {
            justify-content: flex-end;
            margin-left: 0;
        }

        .acf-field.meza-content-model-merged-bucket-source {
            display: none !important;
        }

        .acf-field.meza-content-model-merged-bucket-host .acf-input {
            padding-top: 10px;
        }

        .meza-content-model-merged-bucket-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            column-gap: 24px;
            row-gap: 0;
            align-items: start;
        }

        .meza-content-model-merged-bucket-column {
            min-width: 0;
        }

        .meza-content-model-merged-bucket-column.meza-content-model-merged-bucket-column-omitted {
            visibility: hidden;
            pointer-events: none;
        }

        .meza-content-model-merged-bucket-title {
            margin: 0 0 10px;
            font-weight: 600;
            line-height: 1.3;
        }

        .meza-content-model-merged-bucket-column .acf-checkbox-list {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            column-gap: 0;
            row-gap: 8px;
            margin-top: 0;
        }

        .acf-field.meza-content-model-merged-bucket-host .meza-content-model-merged-bucket-column .acf-checkbox-list {
            grid-template-columns: minmax(0, 1fr);
        }

        .meza-content-model-merged-bucket-grid.meza-content-model-merged-bucket-grid-no-titles .meza-content-model-merged-bucket-column {
            padding-top: 0;
        }

        .meza-content-model-merged-bucket-grid.meza-content-model-merged-bucket-grid-no-titles .meza-content-model-merged-bucket-title {
            display: none;
        }

        .acf-field.meza-content-model-field-group-management-bucket .acf-checkbox-list input {
            margin-top: 0;
            margin-right: 0;
        }

        .acf-field.meza-content-model-field-group-management-bucket .meza-content-model-row-switch {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            flex: 0 0 auto;
            min-height: 24px;
            margin-top: 1px;
        }

        .acf-field.meza-content-model-field-group-management-bucket .meza-content-model-row-switch[hidden] {
            display: none !important;
        }

        .acf-field.meza-content-model-field-group-management-bucket .meza-content-model-row-switch .acf-switch {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: flex-start;
            width: 36px;
            min-width: 36px;
            height: 20px;
            padding: 0;
            border-radius: 999px;
            cursor: pointer;
            overflow: hidden;
        }

        .acf-field.meza-content-model-field-group-management-bucket .meza-content-model-row-switch.is-disabled .acf-switch {
            cursor: not-allowed;
            opacity: 0.45;
        }

        .acf-field.meza-content-model-field-group-management-bucket .meza-content-model-row-switch .acf-switch-slider {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 14px;
            height: 14px;
            border-radius: 999px;
            transition: transform 0.18s ease;
        }

        .acf-field.meza-content-model-field-group-management-bucket .meza-content-model-row-switch .acf-switch.-on .acf-switch-slider {
            transform: translateX(16px);
        }

        .acf-field.meza-content-model-field-group-management-bucket .meza-content-model-row-switch .acf-switch-on,
        .acf-field.meza-content-model-field-group-management-bucket .meza-content-model-row-switch .acf-switch-off {
            display: none;
        }

        .acf-field.meza-content-model-indexable-store {
            display: none !important;
        }

        .acf-field.meza-content-model-field-group-management-bucket .acf-checkbox-list li.meza-content-model-protected-choice,
        .acf-field.meza-content-model-field-group-management-bucket .acf-checkbox-list label.meza-content-model-protected-choice-label {
            opacity: 0.7;
        }

        .acf-field.meza-content-model-field-group-management-bucket .acf-checkbox-list label.meza-content-model-protected-choice-label,
        .acf-field.meza-content-model-field-group-management-bucket .acf-checkbox-list input[aria-disabled="true"],
        .acf-field.meza-content-model-field-group-management-bucket .acf-checkbox-list li.meza-content-model-protected-choice .meza-content-model-row-switch .acf-switch {
            cursor: not-allowed;
        }

        .meza-content-model-taxonomy-term-group-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            column-gap: 8px;
            row-gap: 4px;
            align-items: start;
        }

        .meza-content-model-taxonomy-term-group-column {
            min-width: 0;
        }

        .acf-field.meza-content-model-field-group-management-bucket .meza-content-model-taxonomy-term-group-column .acf-checkbox-list {
            display: block;
            grid-template-columns: none;
            margin: 0;
        }

        .acf-field.meza-content-model-field-group-management-bucket .meza-content-model-taxonomy-term-group-column .acf-checkbox-list li {
            display: block;
            margin: 0 0 2px;
        }

        .acf-field.meza-content-model-field-group-management-bucket .meza-content-model-taxonomy-term-group-column .acf-checkbox-list li.meza-content-model-taxonomy-term-parent-break {
            margin-top: 12px;
        }

        .acf-field.meza-content-model-field-group-management-bucket .meza-content-model-taxonomy-term-group-column .acf-checkbox-list li.meza-content-model-taxonomy-term-parent-break:first-child {
            margin-top: 0;
        }

        .acf-field.meza-content-model-field-group-management-bucket .meza-content-model-taxonomy-term-group-column .acf-checkbox-list li:last-child {
            margin-bottom: 0;
        }

        .acf-field.meza-content-model-field-group-management-bucket .meza-content-model-taxonomy-term-group-column .acf-checkbox-list label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: auto;
            max-width: 100%;
            line-height: 1.25;
        }

        .meza-content-model-taxonomy-term-source {
            display: none !important;
        }

        #submitdiv #publishing-action .meza-content-model-reset-defaults-button,
        #submitdiv .publishing-action .meza-content-model-reset-defaults-button {
            margin-right: 8px;
        }

        body.meza-content-model-read-only #submitdiv,
        body.meza-content-model-read-only #postbox-container-1 {
            display: none !important;
        }

        body.meza-content-model-read-only .acf-field .acf-input input:not([type="hidden"]),
        body.meza-content-model-read-only .acf-field .acf-input select,
        body.meza-content-model-read-only .acf-field .acf-input textarea,
        body.meza-content-model-read-only .acf-field .acf-input button {
            cursor: not-allowed !important;
        }

        body.meza-content-model-read-only .acf-field .acf-input label,
        body.meza-content-model-read-only .acf-field .acf-input .acf-switch {
            cursor: not-allowed !important;
        }

        body.meza-content-model-read-only .acf-field .acf-input .acf-switch,
        body.meza-content-model-read-only .acf-field .acf-input .meza-content-model-row-switch {
            pointer-events: none !important;
        }

        @media screen and (max-width: 1600px) {
            .acf-field.meza-content-model-field-group-management-bucket .acf-checkbox-list {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .meza-content-model-merged-bucket-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .meza-content-model-taxonomy-term-group-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .acf-field.meza-content-model-merged-bucket-host .meza-content-model-merged-bucket-column .acf-checkbox-list {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        @media screen and (max-width: 1400px) {
            .acf-field.meza-content-model-field-group-management-bucket .acf-checkbox-list {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .meza-content-model-merged-bucket-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .meza-content-model-taxonomy-term-group-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .acf-field.meza-content-model-merged-bucket-host .meza-content-model-merged-bucket-column .acf-checkbox-list {
                grid-template-columns: minmax(0, 1fr);
            }

            .meza-content-model-merged-bucket-column-empty {
                display: none;
            }
        }

        @media screen and (max-width: 900px) {
            .acf-field.meza-content-model-field-group-management-bucket .acf-checkbox-list {
                grid-template-columns: minmax(0, 1fr);
            }

            .meza-content-model-merged-bucket-grid {
                grid-template-columns: minmax(0, 1fr);
                row-gap: 18px;
            }

            .meza-content-model-taxonomy-term-group-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .acf-field.meza-content-model-merged-bucket-host .meza-content-model-merged-bucket-column .acf-checkbox-list {
                grid-template-columns: minmax(0, 1fr);
            }

            .meza-content-model-merged-bucket-grid.meza-content-model-merged-bucket-grid-no-titles .meza-content-model-merged-bucket-column-empty {
                display: none;
            }
        }
    </style>
    <script id="meza-content-model-field-group-row-layout-script">
        document.addEventListener('DOMContentLoaded', function () {
            var isReadOnly = <?php echo $is_read_only ? 'true' : 'false'; ?>;
            var fieldKeys = [
                'field_meza_content_model_custom_field_groups',
                'field_meza_content_model_default_field_groups',
                'field_meza_content_model_built_in_field_groups',
                'field_meza_content_model_custom_post_types',
                'field_meza_content_model_built_in_post_types',
                'field_meza_content_model_built_in_non_indexable_post_types',
                'field_meza_content_model_custom_taxonomies',
                'field_meza_content_model_built_in_taxonomies',
                'field_meza_content_model_built_in_non_indexable_taxonomies',
                'field_meza_content_model_custom_options_pages',
                'field_meza_content_model_built_in_options_pages'
            ];
            var indexableFieldMap = {
                postTypes: {
                    store: 'field_meza_content_model_indexable_post_types',
                    forcedOn: [
                        'page'
                    ],
                    locked: <?php echo wp_json_encode(array_values(array_unique(array_merge(
                        [
                            'certification',
                            'cta',
                            'faq',
                            'form',
                            'organization',
                            'product-review',
                            'review',
                            'song',
                        ],
                        function_exists('meza_get_content_model_object_management_custom_locked_indexable_identifiers')
                            ? meza_get_content_model_object_management_custom_locked_indexable_identifiers('post_types')
                            : []
                    )))); ?>,
                    visible: [
                        'field_meza_content_model_custom_post_types',
                        'field_meza_content_model_built_in_post_types',
                        'field_meza_content_model_built_in_non_indexable_post_types'
                    ]
                },
                taxonomies: {
                    store: 'field_meza_content_model_indexable_taxonomies',
                    locked: <?php echo wp_json_encode(array_values(array_unique(array_merge(
                        [
                            'event-type',
                            'instrument',
                            'organization-type',
                            'post_tag',
                            'profile-type',
                            'season',
                        ],
                        function_exists('meza_get_content_model_object_management_custom_locked_indexable_identifiers')
                            ? meza_get_content_model_object_management_custom_locked_indexable_identifiers('taxonomies')
                            : []
                    )))); ?>,
                    visible: [
                        'field_meza_content_model_custom_taxonomies',
                        'field_meza_content_model_built_in_taxonomies',
                        'field_meza_content_model_built_in_non_indexable_taxonomies'
                    ]
                }
            };
            var readOnlyFieldKey = 'field_meza_content_model_built_in_field_groups';
            var protectedChoiceMap = {
                'field_meza_content_model_built_in_post_types': [
                    'page'
                ],
                'field_meza_content_model_built_in_non_indexable_post_types': [
                    'cta',
                    'faq',
                    'form',
                    'review'
                ],
                'field_meza_content_model_built_in_non_indexable_taxonomies': [
                    'event-type',
                    'organization-type',
                    'profile-type'
                ],
                'field_meza_content_model_built_in_options_pages': [
                    'business-information',
                    'branding',
                    'content-model',
                    'crm',
                    'ecommerce'
                ]
            };
            var taxonomyTermGroupMap = <?php echo wp_json_encode($taxonomy_term_group_map); ?>;

            function getField(fieldKey) {
                return document.querySelector('.acf-field[data-key="' + fieldKey + '"]');
            }

            function syncReadOnlyField(field) {
                if (!field) {
                    return;
                }

                var existingMirrors = field.querySelectorAll('input.meza-content-model-readonly-mirror');
                existingMirrors.forEach(function (mirror) {
                    mirror.remove();
                });

                var inputs = field.querySelectorAll('input[name^="acf["]');
                inputs.forEach(function (input) {
                    if (!(input instanceof HTMLInputElement)) {
                        return;
                    }

                    if (input.type === 'checkbox') {
                        if (input.checked && input.name) {
                            var mirror = document.createElement('input');
                            mirror.type = 'hidden';
                            mirror.className = 'meza-content-model-readonly-mirror';
                            mirror.name = input.name;
                            mirror.value = input.value;
                            field.appendChild(mirror);
                        }

                        var label = input.closest('label');
                        if (label) {
                            label.classList.add('meza-content-model-protected-choice-label');
                            label.style.cursor = 'not-allowed';
                        }

                        var row = input.closest('li');
                        if (row) {
                            row.classList.add('meza-content-model-protected-choice');
                        }
                    }

                    input.disabled = true;
                    input.setAttribute('aria-disabled', 'true');
                });
            }

            function syncProtectedChoices(field, protectedValues) {
                if (!field || !protectedValues.length) {
                    return;
                }

                var existingMirrors = field.querySelectorAll('input.meza-content-model-protected-mirror');
                existingMirrors.forEach(function (mirror) {
                    mirror.remove();
                });

                protectedValues.forEach(function (protectedValue) {
                    var input = field.querySelector('input[type="checkbox"][value="' + protectedValue + '"]');
                    if (!(input instanceof HTMLInputElement)) {
                        return;
                    }

                    input.checked = true;
                    input.disabled = true;
                    input.setAttribute('aria-disabled', 'true');

                    var label = input.closest('label');
                    if (label) {
                        label.classList.add('meza-content-model-protected-choice-label');
                        label.style.cursor = 'not-allowed';
                    }

                    var row = input.closest('li');
                    if (row) {
                        row.classList.add('meza-content-model-protected-choice');
                    }

                    if (input.name) {
                        var mirror = document.createElement('input');
                        mirror.type = 'hidden';
                        mirror.className = 'meza-content-model-protected-mirror';
                        mirror.name = input.name;
                        mirror.value = input.value;
                        field.appendChild(mirror);
                    }
                });
            }

            function refreshFieldGroupLayout() {
                fieldKeys.forEach(function (fieldKey) {
                    var field = getField(fieldKey);

                    if (fieldKey === readOnlyFieldKey) {
                        syncReadOnlyField(field);
                        return;
                    }

                    if (protectedChoiceMap[fieldKey]) {
                        syncProtectedChoices(field, protectedChoiceMap[fieldKey]);
                    }
                });

                syncIndexableSwitches();
                mergeBuiltInBuckets();
                layoutTieredTaxonomyTermFields();
            }

            function getCheckboxInputs(field) {
                return field ? field.querySelectorAll('.acf-checkbox-list input[type="checkbox"][name^="acf["]') : [];
            }

            function getStoreInputMap(fieldKey) {
                var field = getField(fieldKey);
                var map = {};

                getCheckboxInputs(field).forEach(function (input) {
                    if (!(input instanceof HTMLInputElement) || !input.value) {
                        return;
                    }

                    map[input.value] = input;
                });

                return map;
            }

            function ensureSwitch(li, sourceInput, storeInput, isLocked, isForcedOn) {
                if (!li || !(sourceInput instanceof HTMLInputElement)) {
                    return;
                }

                var existing = li.querySelector('.meza-content-model-row-switch');
                var wrapper = existing;

                if (!(storeInput instanceof HTMLInputElement) && !isLocked && !isForcedOn) {
                    if (existing) {
                        existing.hidden = true;
                    }
                    return;
                }

                if (!wrapper) {
                    wrapper = document.createElement('span');
                    wrapper.className = 'meza-content-model-row-switch';
                    wrapper.innerHTML = '<span class="acf-switch"><span class="acf-switch-on">On</span><span class="acf-switch-off">Off</span><div class="acf-switch-slider"></div></span>';
                    li.appendChild(wrapper);
                }

                wrapper.hidden = false;

                var acfSwitch = wrapper.querySelector('.acf-switch');
                if (!acfSwitch) {
                    return;
                }

                var hasStoreInput = storeInput instanceof HTMLInputElement;

                if (isLocked && storeInput) {
                    storeInput.checked = false;
                }

                if (isForcedOn && storeInput) {
                    storeInput.checked = true;
                }

                if (!sourceInput.checked && !isForcedOn && storeInput) {
                    storeInput.checked = false;
                }

                var switchIsDisabled = sourceInput.disabled || !!isForcedOn || !hasStoreInput || (!!isLocked && hasStoreInput);
                var switchIsOn = hasStoreInput ? !!storeInput.checked : false;

                wrapper.classList.toggle('is-disabled', switchIsDisabled);
                acfSwitch.classList.toggle('-on', switchIsOn);
                acfSwitch.setAttribute('role', 'switch');
                acfSwitch.setAttribute('aria-checked', switchIsOn ? 'true' : 'false');
                acfSwitch.setAttribute('aria-disabled', switchIsDisabled ? 'true' : 'false');

                wrapper.onclick = function (event) {
                    event.preventDefault();
                    event.stopPropagation();

                    if (sourceInput.disabled || isForcedOn) {
                        return;
                    }

                    if (!hasStoreInput || isLocked) {
                        return;
                    }

                    if (!sourceInput.checked) {
                        sourceInput.checked = true;
                        storeInput.checked = true;
                        sourceInput.dispatchEvent(new Event('change', { bubbles: true }));
                        storeInput.dispatchEvent(new Event('change', { bubbles: true }));
                        acfSwitch.classList.add('-on');
                        acfSwitch.setAttribute('aria-checked', 'true');
                        return;
                    }

                    storeInput.checked = !storeInput.checked;
                    acfSwitch.classList.toggle('-on', !!storeInput.checked);
                    acfSwitch.setAttribute('aria-checked', storeInput.checked ? 'true' : 'false');
                    storeInput.dispatchEvent(new Event('change', { bubbles: true }));
                };
            }

            function syncIndexableSwitches() {
                Object.keys(indexableFieldMap).forEach(function (groupKey) {
                    var config = indexableFieldMap[groupKey];
                    var storeMap = getStoreInputMap(config.store);
                    var lockedMap = {};
                    var forcedOnMap = {};

                    (config.locked || []).forEach(function (identifier) {
                        lockedMap[identifier] = true;
                    });

                    (config.forcedOn || []).forEach(function (identifier) {
                        forcedOnMap[identifier] = true;
                    });

                    config.visible.forEach(function (fieldKey) {
                        var field = getField(fieldKey);

                        getCheckboxInputs(field).forEach(function (input) {
                            if (!(input instanceof HTMLInputElement)) {
                                return;
                            }

                            ensureSwitch(
                                input.closest('li'),
                                input,
                                storeMap[input.value] || null,
                                !!lockedMap[input.value],
                                !!forcedOnMap[input.value]
                            );
                        });
                    });
                });
            }

            function ensureBucketColumn(list, title) {
                var column = document.createElement('div');
                column.className = 'meza-content-model-merged-bucket-column';

                if (title) {
                    var heading = document.createElement('div');
                    heading.className = 'meza-content-model-merged-bucket-title';
                    heading.textContent = title;
                    column.appendChild(heading);
                }

                column.appendChild(list);

                return column;
            }

            function listHasChoices(list) {
                if (!(list instanceof HTMLElement)) {
                    return false;
                }

                return !!list.querySelector('li');
            }

            function ensureEmptyBucketColumn() {
                var column = document.createElement('div');
                column.className = 'meza-content-model-merged-bucket-column meza-content-model-merged-bucket-column-empty';
                var heading = document.createElement('div');
                heading.className = 'meza-content-model-merged-bucket-title';
                heading.innerHTML = '&nbsp;';
                column.appendChild(heading);
                return column;
            }

            function mergeBuiltInBucketPair(hostFieldKey, sourceFieldKey, hostColumnTitle, sourceColumnTitle) {
                var hostField = getField(hostFieldKey);
                var sourceField = getField(sourceFieldKey);

                if (!hostField || !sourceField) {
                    return;
                }

                sourceField.classList.add('meza-content-model-merged-bucket-source');
                hostField.classList.add('meza-content-model-merged-bucket-host');

                var hostInput = hostField.querySelector('.acf-input');
                var hostList = hostField.querySelector('.acf-checkbox-list');
                var sourceList = sourceField.querySelector('.acf-checkbox-list');

                if (!hostInput || !hostList || !sourceList) {
                    return;
                }

                var existingGrid = hostInput.querySelector('.meza-content-model-merged-bucket-grid');
                if (existingGrid) {
                    existingGrid.remove();
                }

                var hostColumn = ensureBucketColumn(hostList, hostColumnTitle || 'Indexable');
                var sourceColumn = ensureBucketColumn(sourceList, sourceColumnTitle || 'Non-Indexable');
                var emptyColumn = ensureEmptyBucketColumn();

                var grid = document.createElement('div');
                grid.className = 'meza-content-model-merged-bucket-grid';
                grid.appendChild(hostColumn);
                grid.appendChild(sourceColumn);
                grid.appendChild(emptyColumn);

                hostInput.appendChild(grid);
            }

            function restoreMergedBucketSingleSourceList(inputWrap, existingGrid) {
                if (!(inputWrap instanceof HTMLElement) || !(existingGrid instanceof HTMLElement)) {
                    return null;
                }

                var lists = existingGrid.querySelectorAll('.acf-checkbox-list');
                if (!lists.length) {
                    existingGrid.remove();
                    return null;
                }

                var sourceList = lists[0];
                Array.from(lists).slice(1).forEach(function (list) {
                    Array.from(list.children).forEach(function (item) {
                        sourceList.appendChild(item);
                    });
                });

                existingGrid.remove();
                inputWrap.appendChild(sourceList);

                return sourceList;
            }

            function mergeCustomBucketByIndexability(fieldKey, groupKey) {
                var field = getField(fieldKey);

                if (!field) {
                    return;
                }

                field.classList.add('meza-content-model-merged-bucket-host');

                var inputWrap = field.querySelector('.acf-input');
                var list = inputWrap ? inputWrap.querySelector(':scope > .acf-checkbox-list') : null;

                if (!inputWrap) {
                    return;
                }

                var existingGrid = inputWrap.querySelector('.meza-content-model-merged-bucket-grid');
                if (existingGrid) {
                    list = restoreMergedBucketSingleSourceList(inputWrap, existingGrid);
                }

                if (!list) {
                    list = inputWrap.querySelector(':scope > .acf-checkbox-list');
                }

                if (!list) {
                    return;
                }

                var config = indexableFieldMap[groupKey] || null;
                var lockedMap = {};
                if (config && Array.isArray(config.locked)) {
                    config.locked.forEach(function (identifier) {
                        lockedMap[identifier] = true;
                    });
                }

                var nonIndexableList = list.cloneNode(false);
                Array.from(list.children).forEach(function (item) {
                    var input = item.querySelector('input[type="checkbox"]');
                    if (!(input instanceof HTMLInputElement) || !lockedMap[input.value]) {
                        return;
                    }

                    nonIndexableList.appendChild(item);
                });

                var firstColumn = ensureBucketColumn(list, 'Indexable');
                var secondColumn = ensureBucketColumn(nonIndexableList, 'Non-Indexable');
                var thirdColumn = ensureEmptyBucketColumn();

                var grid = document.createElement('div');
                grid.className = 'meza-content-model-merged-bucket-grid';
                grid.appendChild(firstColumn);
                grid.appendChild(secondColumn);
                grid.appendChild(thirdColumn);

                inputWrap.appendChild(grid);
            }

            function mergeBuiltInBuckets() {
                mergeCustomBucketByIndexability('field_meza_content_model_custom_post_types', 'postTypes');
                mergeCustomBucketByIndexability('field_meza_content_model_custom_taxonomies', 'taxonomies');
                mergeBuiltInBucketPair(
                    'field_meza_content_model_built_in_post_types',
                    'field_meza_content_model_built_in_non_indexable_post_types',
                    'Indexable',
                    'Non-Indexable'
                );
                mergeBuiltInBucketPair(
                    'field_meza_content_model_built_in_taxonomies',
                    'field_meza_content_model_built_in_non_indexable_taxonomies',
                    'Indexable',
                    'Non-Indexable'
                );
            }

            function restoreTaxonomyTermGroupSourceList(sourceList, existingGrid) {
                if (!(sourceList instanceof HTMLElement) || !(existingGrid instanceof HTMLElement)) {
                    return;
                }

                existingGrid.remove();
                sourceList.classList.remove('meza-content-model-taxonomy-term-source');
            }

            function setCheckboxLabelText(label, input, text) {
                if (!(label instanceof HTMLElement) || !(input instanceof HTMLInputElement)) {
                    return;
                }

                Array.from(label.childNodes).forEach(function (node) {
                    if (node !== input) {
                        node.remove();
                    }
                });

                label.appendChild(document.createTextNode(' ' + text));
            }

            function layoutTieredTaxonomyTermFields() {
                Object.keys(taxonomyTermGroupMap).forEach(function (fieldKey) {
                    var groups = taxonomyTermGroupMap[fieldKey];
                    var field = getField(fieldKey);

                    if (!field || !Array.isArray(groups) || !groups.length) {
                        return;
                    }

                    var inputWrap = field.querySelector('.acf-input');
                    var sourceList = inputWrap ? inputWrap.querySelector(':scope > .acf-checkbox-list') : null;
                    if (!(inputWrap instanceof HTMLElement) || !(sourceList instanceof HTMLElement)) {
                        return;
                    }

                    var existingGrid = inputWrap.querySelector('.meza-content-model-taxonomy-term-group-grid');
                    if (existingGrid instanceof HTMLElement) {
                        restoreTaxonomyTermGroupSourceList(sourceList, existingGrid);
                    }

                    var itemMap = {};
                    var sourceInputMap = {};
                    sourceList.querySelectorAll(':scope > li').forEach(function (item) {
                        var input = item.querySelector('input[type="checkbox"]');
                        if (!(input instanceof HTMLInputElement) || !input.value) {
                            return;
                        }

                        itemMap[input.value] = item;
                        sourceInputMap[input.value] = input;
                    });

                    var columns = [];
                    var cloneInputsByValue = {};
                    var directChildrenMap = {};
                    var parentMap = {};

                    groups.forEach(function (group) {
                        var parentKey = String(group.id || '');
                        if (!parentKey) {
                            return;
                        }

                        directChildrenMap[parentKey] = Array.isArray(group.children)
                            ? group.children.map(function (childId) { return String(childId || ''); }).filter(Boolean)
                            : [];
                    });

                    groups.forEach(function (group) {
                        var items = Array.isArray(group.items) ? group.items : [];
                        if (!items.length) {
                            return;
                        }

                        var columnList = document.createElement('ul');
                        columnList.className = sourceList.className;

                        items.forEach(function (item) {
                            var itemKey = String(item.id || '');
                            var sourceItem = itemMap[itemKey] || null;
                            var sourceInput = sourceInputMap[itemKey] || null;
                            if (!(sourceItem instanceof HTMLElement) || !(sourceInput instanceof HTMLInputElement)) {
                                return;
                            }

                            var clonedItem = sourceItem.cloneNode(true);
                            var clonedInput = clonedItem.querySelector('input[type="checkbox"]');
                            var clonedLabel = clonedItem.querySelector('label');
                            if (!(clonedInput instanceof HTMLInputElement) || !(clonedLabel instanceof HTMLElement)) {
                                return;
                            }

                            if (Number(item.depth || 0) === 0 && columnList.children.length > 0) {
                                clonedItem.classList.add('meza-content-model-taxonomy-term-parent-break');
                            }

                            clonedInput.removeAttribute('name');
                            clonedInput.dataset.mezaTermValue = itemKey;
                            clonedInput.checked = sourceInput.checked;
                            clonedInput.disabled = sourceInput.disabled;
                            clonedInput.setAttribute('aria-disabled', sourceInput.disabled ? 'true' : 'false');
                            clonedInput.dataset.mezaParentId = String(item.parent_id || '');

                            if (clonedInput.dataset.mezaParentId) {
                                parentMap[itemKey] = clonedInput.dataset.mezaParentId;
                            }

                            setCheckboxLabelText(clonedLabel, clonedInput, String(item.label || ''));

                            cloneInputsByValue[itemKey] = cloneInputsByValue[itemKey] || [];
                            cloneInputsByValue[itemKey].push(clonedInput);

                            columnList.appendChild(clonedItem);
                        });

                        if (!columnList.children.length) {
                            return;
                        }

                        var column = document.createElement('div');
                        column.className = 'meza-content-model-taxonomy-term-group-column';
                        column.appendChild(columnList);
                        columns.push(column);
                    });

                    if (!columns.length) {
                        return;
                    }

                    var grid = document.createElement('div');
                    grid.className = 'meza-content-model-taxonomy-term-group-grid';
                    columns.forEach(function (column) {
                        grid.appendChild(column);
                    });

                    function applyTermValue(termKey, checked, visited) {
                        termKey = String(termKey || '');
                        if (!termKey) {
                            return;
                        }

                        visited = visited || {};
                        if (visited[termKey]) {
                            return;
                        }
                        visited[termKey] = true;

                        var sourceInput = sourceInputMap[termKey] || null;
                        if (sourceInput instanceof HTMLInputElement) {
                            sourceInput.checked = checked;
                        }

                        (cloneInputsByValue[termKey] || []).forEach(function (cloneInput) {
                            if (cloneInput instanceof HTMLInputElement) {
                                cloneInput.checked = checked;
                            }
                        });

                        (directChildrenMap[termKey] || []).forEach(function (childKey) {
                            applyTermValue(childKey, checked, visited);
                        });
                    }

                    function syncClonesFromSourceState() {
                        Object.keys(sourceInputMap).forEach(function (termKey) {
                            var sourceInput = sourceInputMap[termKey];
                            if (!(sourceInput instanceof HTMLInputElement)) {
                                return;
                            }

                            if (sourceInput.checked && (directChildrenMap[termKey] || []).length) {
                                applyTermValue(termKey, true, {});
                                return;
                            }

                            (cloneInputsByValue[termKey] || []).forEach(function (cloneInput) {
                                if (cloneInput instanceof HTMLInputElement) {
                                    cloneInput.checked = sourceInput.checked;
                                }
                            });
                        });
                    }

                    Object.keys(cloneInputsByValue).forEach(function (itemKey) {
                        var sourceInput = sourceInputMap[itemKey] || null;
                        var cloneInputs = cloneInputsByValue[itemKey] || [];
                        if (!(sourceInput instanceof HTMLInputElement) || !cloneInputs.length) {
                            return;
                        }

                        cloneInputs.forEach(function (cloneInput) {
                            cloneInput.addEventListener('change', function () {
                                applyTermValue(itemKey, cloneInput.checked, {});

                                if (cloneInput.checked) {
                                    var ancestorKey = parentMap[itemKey] || '';
                                    while (ancestorKey) {
                                        applyTermValue(ancestorKey, true, {});
                                        ancestorKey = parentMap[ancestorKey] || '';
                                    }
                                }

                                sourceInput.dispatchEvent(new Event('change', { bubbles: true }));
                                window.setTimeout(syncClonesFromSourceState, 0);
                            });
                        });
                    });

                    sourceList.classList.add('meza-content-model-taxonomy-term-source');
                    inputWrap.appendChild(grid);
                });
            }

            function syncCheckboxFieldSubmitMirrors(form, fieldKey) {
                var field = getField(fieldKey);

                if (!(form instanceof HTMLFormElement) || !field) {
                    return;
                }

                field.querySelectorAll('input.meza-content-model-submit-mirror, input.meza-content-model-submit-empty-mirror').forEach(function (mirror) {
                    mirror.remove();
                });

                var expectedBaseName = 'acf[' + fieldKey + ']';
                var expectedName = 'acf[' + fieldKey + '][]';
                var emptyMirror = document.createElement('input');
                emptyMirror.type = 'hidden';
                emptyMirror.className = 'meza-content-model-submit-empty-mirror';
                emptyMirror.name = expectedBaseName;
                emptyMirror.value = '';
                field.appendChild(emptyMirror);

                var checkedInputs = form.querySelectorAll('input[type="checkbox"][name^="acf["]');

                checkedInputs.forEach(function (input) {
                    if (
                        !(input instanceof HTMLInputElement)
                        || input.disabled
                        || !input.checked
                        || input.name !== expectedName
                    ) {
                        return;
                    }

                    var mirror = document.createElement('input');
                    mirror.type = 'hidden';
                    mirror.className = 'meza-content-model-submit-mirror';
                    mirror.name = expectedName;
                    mirror.value = input.value;
                    field.appendChild(mirror);
                });
            }

            function syncTaxonomyTermRenderedMirrors(form) {
                if (!(form instanceof HTMLFormElement)) {
                    return;
                }

                Object.keys(taxonomyTermGroupMap).forEach(function (fieldKey) {
                    var field = getField(fieldKey);
                    if (!field) {
                        return;
                    }

                    field.querySelectorAll('input.meza-content-model-rendered-term-mirror').forEach(function (mirror) {
                        mirror.remove();
                    });

                    var sourceInputs = field.querySelectorAll('.acf-input > .acf-checkbox-list input[type="checkbox"][name^="acf["]');
                    sourceInputs.forEach(function (input) {
                        if (!(input instanceof HTMLInputElement) || !input.value) {
                            return;
                        }

                        var fieldName = input.name || '';
                        var fieldKeyMatch = fieldName.match(/^acf\[(field_meza_content_model_taxonomy_terms_[^\]]+)\]\[\]$/);
                        if (!fieldKeyMatch) {
                            return;
                        }

                        var taxonomy = fieldKeyMatch[1].replace('field_meza_content_model_taxonomy_terms_', '');
                        if (!taxonomy) {
                            return;
                        }

                        var mirror = document.createElement('input');
                        mirror.type = 'hidden';
                        mirror.className = 'meza-content-model-rendered-term-mirror';
                        mirror.name = 'meza_content_model_rendered_taxonomy_terms[' + taxonomy + '][]';
                        mirror.value = input.value;
                        field.appendChild(mirror);
                    });
                });
            }

            function syncContentModelSubmitMirrors(form) {
                [
                    'field_meza_content_model_custom_field_groups',
                    'field_meza_content_model_default_field_groups',
                    'field_meza_content_model_built_in_field_groups',
                    'field_meza_content_model_custom_post_types',
                    'field_meza_content_model_built_in_post_types',
                    'field_meza_content_model_built_in_non_indexable_post_types',
                    'field_meza_content_model_custom_taxonomies',
                    'field_meza_content_model_built_in_taxonomies',
                    'field_meza_content_model_built_in_non_indexable_taxonomies',
                    'field_meza_content_model_custom_options_pages',
                    'field_meza_content_model_built_in_options_pages'
                ].forEach(function (fieldKey) {
                    syncCheckboxFieldSubmitMirrors(form, fieldKey);
                });

                syncTaxonomyTermRenderedMirrors(form);
            }

            function applyReadOnlyMode() {
                document.body.classList.add('meza-content-model-read-only');

                var form = document.querySelector('form#post');
                if (!(form instanceof HTMLFormElement)) {
                    return;
                }

                form.querySelectorAll('input:not([type="hidden"]), select, textarea, button').forEach(function (control) {
                    if (!(control instanceof HTMLElement)) {
                        return;
                    }

                    if (
                        control instanceof HTMLInputElement
                        || control instanceof HTMLSelectElement
                        || control instanceof HTMLTextAreaElement
                        || control instanceof HTMLButtonElement
                    ) {
                        control.disabled = true;
                    }

                    control.setAttribute('aria-disabled', 'true');
                });

                var submitDiv = document.getElementById('submitdiv');
                if (submitDiv instanceof HTMLElement) {
                    var submitContainer = submitDiv.closest('.postbox-container');
                    submitDiv.remove();

                    if (submitContainer instanceof HTMLElement && submitContainer.querySelector('.postbox')) {
                        return;
                    }

                    if (submitContainer instanceof HTMLElement) {
                        submitContainer.remove();
                    }
                }

                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                });
            }

            function injectResetButton() {
                var submitWrap = document.querySelector('#submitdiv #publishing-action') || document.querySelector('#submitdiv .publishing-action');
                if (!submitWrap || submitWrap.querySelector('.meza-content-model-reset-defaults-button')) {
                    return;
                }

                var updateButton = submitWrap.querySelector('input[type="submit"], button[type="submit"]');
                if (!updateButton) {
                    return;
                }

                var resetButton = document.createElement('button');
                resetButton.type = 'submit';
                resetButton.name = 'meza_content_model_reset_defaults';
                resetButton.value = '1';
                resetButton.className = 'button button-secondary meza-content-model-reset-defaults-button';
                resetButton.textContent = 'Reset to defaults';

                submitWrap.insertBefore(resetButton, updateButton);
            }

            function attachSubmitConfirmations() {
                var submitWrap = document.querySelector('#submitdiv #publishing-action') || document.querySelector('#submitdiv .publishing-action');
                if (!submitWrap) {
                    return;
                }

                var form = submitWrap.closest('form');
                if (!(form instanceof HTMLFormElement)) {
                    return;
                }

                if (form.dataset.mezaContentModelConfirmBound === '1') {
                    return;
                }

                form.dataset.mezaContentModelConfirmBound = '1';

                var lastSubmitter = null;
                var buttons = form.querySelectorAll('#submitdiv input[type="submit"], #submitdiv button[type="submit"]');
                buttons.forEach(function (button) {
                    if (!(button instanceof HTMLElement)) {
                        return;
                    }

                    button.addEventListener('click', function () {
                        lastSubmitter = button;
                        syncContentModelSubmitMirrors(form);
                    });
                });

                form.addEventListener('submit', function (event) {
                    if (form.dataset.mezaContentModelSubmitConfirmed === '1') {
                        return;
                    }

                    var submitter = event.submitter || lastSubmitter;
                    var isReset = submitter instanceof HTMLElement && (
                        submitter.classList.contains('meza-content-model-reset-defaults-button')
                        || submitter.getAttribute('name') === 'meza_content_model_reset_defaults'
                    );
                    var message = isReset
                        ? 'Reset the Content Model to its default state for this framework type? This will update wp-admin behavior and ACF configuration.'
                        : 'Update the Content Model? This will change wp-admin behavior and ACF configuration.';

                    if (!window.confirm(message)) {
                        event.preventDefault();
                        event.stopPropagation();
                        return;
                    }

                    form.dataset.mezaContentModelSubmitConfirmed = '1';
                    syncContentModelSubmitMirrors(form);
                });
            }

            refreshFieldGroupLayout();

            if (isReadOnly) {
                applyReadOnlyMode();
            } else {
                injectResetButton();
                attachSubmitConfirmations();
            }

            if (window.acf && typeof window.acf.addAction === 'function') {
                window.acf.addAction('show_field', function () {
                    refreshFieldGroupLayout();

                    if (isReadOnly) {
                        applyReadOnlyMode();
                    }
                });
                window.acf.addAction('append', function () {
                    refreshFieldGroupLayout();

                    if (isReadOnly) {
                        applyReadOnlyMode();
                    }
                });
            }

            document.addEventListener('change', function (event) {
                if (isReadOnly) {
                    return;
                }

                var target = event.target;
                if (!(target instanceof HTMLInputElement) || target.type !== 'checkbox' || !target.name || target.name.indexOf('acf[') !== 0) {
                    return;
                }

                refreshFieldGroupLayout();
            });
        });
    </script>
    <?php
}, 31);

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
        return 'Content Model';
    }
}

if (!function_exists('meza_get_content_structure_admin_page_title')) {
    function meza_get_content_structure_admin_page_title(): string
    {
        return 'Content Model';
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
        $legacy_slug_map = function_exists('meza_get_legacy_shared_project_acf_options_page_slug_map')
            ? meza_get_legacy_shared_project_acf_options_page_slug_map()
            : [];

        if (isset($legacy_slug_map[$menu_slug])) {
            $menu_slug = sanitize_key((string) $legacy_slug_map[$menu_slug]);
        }

        if (in_array($menu_slug, ['business-information', 'branding'], true)) {
            return meza_shared_project_options_page_capability();
        }

        if ($menu_slug === 'content-model') {
            return function_exists('meza_manage_content_model_capability')
                ? meza_manage_content_model_capability()
                : 'meza_manage_content_model';
        }

        return 'manage_options';
    }
}

if (!function_exists('meza_get_shared_project_acf_options_page_parent_slug')) {
    function meza_get_shared_project_acf_options_page_parent_slug(string $menu_slug): string
    {
        $menu_slug = sanitize_key($menu_slug);
        $legacy_slug_map = function_exists('meza_get_legacy_shared_project_acf_options_page_slug_map')
            ? meza_get_legacy_shared_project_acf_options_page_slug_map()
            : [];

        if (isset($legacy_slug_map[$menu_slug])) {
            $menu_slug = sanitize_key((string) $legacy_slug_map[$menu_slug]);
        }

        if (in_array($menu_slug, ['business-information', 'branding'], true)) {
            return 'meza-business-settings';
        }

        if ($menu_slug === 'content-model') {
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
        $legacy_slug_map = function_exists('meza_get_legacy_shared_project_acf_options_page_slug_map')
            ? meza_get_legacy_shared_project_acf_options_page_slug_map()
            : [];

        if (isset($legacy_slug_map[$menu_slug])) {
            $menu_slug = sanitize_key((string) $legacy_slug_map[$menu_slug]);
        }
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
        $legacy_slug_map = function_exists('meza_get_legacy_shared_project_acf_options_page_slug_map')
            ? meza_get_legacy_shared_project_acf_options_page_slug_map()
            : [];

        if (isset($legacy_slug_map[$menu_slug])) {
            $menu_slug = sanitize_key((string) $legacy_slug_map[$menu_slug]);
        }

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
        $legacy_slug_map = function_exists('meza_get_legacy_shared_project_acf_options_page_slug_map')
            ? meza_get_legacy_shared_project_acf_options_page_slug_map()
            : [];

        if (isset($legacy_slug_map[$slug])) {
            $slug = sanitize_key((string) $legacy_slug_map[$slug]);
        }

        if ($slug === 'business-information') {
            return meza_get_business_information_admin_page_title();
        }

        if ($slug === 'branding') {
            return meza_get_branding_admin_page_title();
        }

        if ($slug === 'content-model') {
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
