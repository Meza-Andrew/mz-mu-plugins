<?php

if (!function_exists('meza_get_business_information_branding_field_map')) {
    function meza_get_business_information_branding_field_map(): array
    {
        return [
            'wp_site_title' => [
                'load' => static function (): string {
                    return (string) get_option('blogname', '');
                },
                'update' => static function ($value): string {
                    $sanitized_value = sanitize_text_field((string) $value);
                    update_option('blogname', $sanitized_value);
                    return $sanitized_value;
                },
            ],
            'wp_tagline' => [
                'load' => static function (): string {
                    return (string) get_option('blogdescription', '');
                },
                'update' => static function ($value): string {
                    $sanitized_value = sanitize_text_field((string) $value);
                    update_option('blogdescription', $sanitized_value);
                    return $sanitized_value;
                },
            ],
            'wp_site_logo' => [
                'load' => static function (): int {
                    if (function_exists('meza_get_custom_logo_id')) {
                        return (int) meza_get_custom_logo_id();
                    }

                    return (int) get_theme_mod('custom_logo');
                },
                'update' => static function ($value): int {
                    $logo_id = function_exists('meza_sanitize_custom_logo_id')
                        ? (int) meza_sanitize_custom_logo_id($value)
                        : absint($value);

                    update_option('meza_custom_logo_id', $logo_id);
                    return $logo_id;
                },
            ],
            'wp_site_logo_alternative' => [
                'load' => static function (): int {
                    if (function_exists('meza_get_alternative_logo_id')) {
                        return (int) meza_get_alternative_logo_id();
                    }

                    return (int) get_option('meza_alternative_logo_id', 0);
                },
                'update' => static function ($value): int {
                    $logo_id = function_exists('meza_sanitize_alternative_logo_id')
                        ? (int) meza_sanitize_alternative_logo_id($value)
                        : absint($value);

                    update_option('meza_alternative_logo_id', $logo_id);
                    return $logo_id;
                },
            ],
            'wp_site_icon' => [
                'load' => static function (): int {
                    return (int) get_option('site_icon', 0);
                },
                'validate' => static function ($valid, $value) {
                    if ($valid !== true) {
                        return $valid;
                    }

                    $attachment_id = absint($value);
                    if ($attachment_id <= 0) {
                        return $valid;
                    }

                    $attachment = get_post($attachment_id);
                    if (!($attachment instanceof WP_Post) || $attachment->post_type !== 'attachment') {
                        return __('Select a valid media library image for the Site Icon.', 'mz-mu-plugins');
                    }

                    $metadata = wp_get_attachment_metadata($attachment_id);
                    $width = (int) ($metadata['width'] ?? 0);
                    $height = (int) ($metadata['height'] ?? 0);

                    if ($width < 512 || $height < 512) {
                        return __('Select a Site Icon image that is at least 512 by 512 pixels.', 'mz-mu-plugins');
                    }

                    return $valid;
                },
                'update' => static function ($value): int {
                    $site_icon_id = absint($value);
                    update_option('site_icon', $site_icon_id);
                    return $site_icon_id;
                },
            ],
            'wp_theme_thumbnail' => [
                'load' => static function (): int {
                    return meza_get_theme_thumbnail_id();
                },
                'validate' => static function ($valid, $value) {
                    if ($valid !== true) {
                        return $valid;
                    }

                    $attachment_id = absint($value);
                    if ($attachment_id <= 0) {
                        return $valid;
                    }

                    $attachment = get_post($attachment_id);
                    if (!($attachment instanceof WP_Post) || $attachment->post_type !== 'attachment') {
                        return __('Select a valid media library image for the Theme Thumbnail.', 'mz-mu-plugins');
                    }

                    if (!meza_theme_thumbnail_attachment_is_supported($attachment_id)) {
                        return __('Select a PNG, JPG, GIF, WebP, or AVIF image for the Theme Thumbnail.', 'mz-mu-plugins');
                    }

                    $thumbnail_url = wp_get_attachment_image_url($attachment_id, 'full');
                    if (!is_string($thumbnail_url) || $thumbnail_url === '') {
                        return __('The selected Theme Thumbnail image URL could not be found.', 'mz-mu-plugins');
                    }

                    return $valid;
                },
                'update' => static function ($value): int {
                    $thumbnail_id = absint($value);
                    $GLOBALS['meza_previous_theme_thumbnail_id'] = meza_get_theme_thumbnail_id();

                    if ($thumbnail_id <= 0) {
                        delete_option('meza_theme_thumbnail_id');
                        return 0;
                    }

                    update_option('meza_theme_thumbnail_id', $thumbnail_id, false);
                    return $thumbnail_id;
                },
            ],
            'wp_social_share_default' => [
                'load' => static function (): int {
                    return meza_get_social_share_default_id();
                },
                'validate' => static function ($valid, $value) {
                    if ($valid !== true) {
                        return $valid;
                    }

                    $attachment_id = absint($value);
                    if ($attachment_id <= 0) {
                        return $valid;
                    }

                    $attachment = get_post($attachment_id);
                    if (!($attachment instanceof WP_Post) || $attachment->post_type !== 'attachment') {
                        return __('Select a valid media library image for the Social Share Default.', 'mz-mu-plugins');
                    }

                    if (!meza_theme_thumbnail_attachment_is_supported($attachment_id)) {
                        return __('Select a PNG, JPG, GIF, WebP, or AVIF image for the Social Share Default.', 'mz-mu-plugins');
                    }

                    $thumbnail_url = wp_get_attachment_image_url($attachment_id, 'full');
                    if (!is_string($thumbnail_url) || $thumbnail_url === '') {
                        return __('The selected Social Share Default image URL could not be found.', 'mz-mu-plugins');
                    }

                    return $valid;
                },
                'update' => static function ($value): int {
                    $default_id = absint($value);
                    $theme_thumbnail_id = meza_get_theme_thumbnail_id();
                    $stored_default_id = (int) get_option('meza_social_share_default_id', 0);
                    $previous_theme_thumbnail_id = isset($GLOBALS['meza_previous_theme_thumbnail_id'])
                        ? absint($GLOBALS['meza_previous_theme_thumbnail_id'])
                        : $theme_thumbnail_id;

                    if ($default_id <= 0) {
                        delete_option('meza_social_share_default_id');

                        return $theme_thumbnail_id;
                    }

                    if ($stored_default_id <= 0 && $previous_theme_thumbnail_id > 0 && $default_id === $previous_theme_thumbnail_id) {
                        delete_option('meza_social_share_default_id');
                        return $theme_thumbnail_id > 0 ? $theme_thumbnail_id : $default_id;
                    }

                    if ($default_id > 0 && $default_id === $theme_thumbnail_id) {
                        delete_option('meza_social_share_default_id');
                        return $default_id;
                    }

                    update_option('meza_social_share_default_id', $default_id, false);
                    return $default_id;
                },
            ],
        ];

        return $definitions;
    }
}

if (!function_exists('meza_normalize_acf_textarea_option_value')) {
    function meza_normalize_acf_textarea_option_value($value): string
    {
        if (!is_array($value)) {
            return (string) $value;
        }

        $preferred_keys = [
            'address',
            'formatted_address',
            'place_name',
            'street_name',
            'street_number',
            'city',
            'state',
            'state_short',
            'post_code',
            'country',
            'country_short',
        ];

        foreach (['address', 'formatted_address'] as $key) {
            if (isset($value[$key]) && !is_array($value[$key])) {
                $formatted = trim((string) $value[$key]);
                if ($formatted !== '') {
                    return $formatted;
                }
            }
        }

        $parts = [];

        $append_scalar = static function ($candidate) use (&$parts): void {
            if (is_array($candidate)) {
                return;
            }

            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                $parts[$candidate] = true;
            }
        };

        foreach ($preferred_keys as $key) {
            if (array_key_exists($key, $value)) {
                $append_scalar($value[$key]);
            }
        }

        array_walk_recursive($value, static function ($candidate) use (&$parts): void {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                $parts[$candidate] = true;
            }
        });

        return implode("\n", array_keys($parts));
    }
}

if (!function_exists('meza_acf_google_map_has_meaningful_value')) {
    function meza_acf_google_map_has_meaningful_value($value): bool
    {
        if (is_array($value)) {
            foreach (['address', 'formatted_address', 'place_name', 'name', 'place_id'] as $key) {
                if (!array_key_exists($key, $value) || is_array($value[$key])) {
                    continue;
                }

                if (trim((string) $value[$key]) !== '') {
                    return true;
                }
            }

            return false;
        }

        return trim((string) $value) !== '';
    }
}

add_filter('acf/load_value', static function ($value, $post_id, $field) {
    if (($field['type'] ?? '') !== 'textarea' || !is_array($value)) {
        return $value;
    }

    $normalized_post_id = is_scalar($post_id) ? (string) $post_id : '';
    if ($normalized_post_id !== '' && !in_array($normalized_post_id, ['option', 'options'], true) && !str_starts_with($normalized_post_id, 'options_')) {
        return $value;
    }

    return meza_normalize_acf_textarea_option_value($value);
}, 5, 3);

add_filter('acf/load_value/key=field_69b5a2b623bc0', static function ($value, $post_id, $field) {
    if (meza_acf_google_map_has_meaningful_value($value)) {
        return $value;
    }

    return '';
}, 5, 3);

add_filter('acf/update_value', static function ($value, $post_id, $field) {
    if (($field['type'] ?? '') !== 'textarea' || !is_array($value)) {
        return $value;
    }

    $normalized_post_id = is_scalar($post_id) ? (string) $post_id : '';
    if ($normalized_post_id !== '' && !in_array($normalized_post_id, ['option', 'options'], true) && !str_starts_with($normalized_post_id, 'options_')) {
        return $value;
    }

    return meza_normalize_acf_textarea_option_value($value);
}, 5, 3);

add_filter('acf/update_value/key=field_69b5a2b623bc0', static function ($value, $post_id, $field) {
    if (meza_acf_google_map_has_meaningful_value($value)) {
        return $value;
    }

    return '';
}, 5, 3);

add_filter('acf/validate_value/name=locations', static function ($valid, $value, $field, $input) {
    if ($valid !== true) {
        return $valid;
    }

    if (meza_count_primary_business_information_locations($value) > 1) {
        return 'There can only be 1 primary location.';
    }

    return $valid;
}, 20, 4);

add_filter('acf/validate_value/name=use_primary_location_phone', static function ($valid, $value) {
    return meza_validate_business_information_primary_location_dependency($valid, 'phone', $value);
}, 20, 4);

add_filter('acf/validate_value/key=field_meza_business_email_group', static function ($valid, $value, $field) {
    return meza_validate_business_information_email_group($valid, $value, is_array($field) ? $field : []);
}, 20, 4);

add_filter('acf/validate_value/key=field_meza_business_address_group', static function ($valid, $value, $field) {
    return meza_validate_business_information_address_group($valid, $value, is_array($field) ? $field : []);
}, 20, 4);

add_filter('acf/validate_value/key=field_68c133a0425a8', static function ($valid, $value) {
    if (!meza_is_business_information_acf_submission()) {
        return $valid;
    }

    return true;
}, 20, 4);

add_filter('acf/validate_value/key=field_69b5a2b623bc0', static function ($valid, $value) {
    if (!meza_is_business_information_acf_submission()) {
        return $valid;
    }

    return true;
}, 20, 4);

add_filter('acf/validate_value/key=field_meza_business_mailing_address', static function ($valid, $value) {
    if (!meza_is_business_information_acf_submission()) {
        return $valid;
    }

    return true;
}, 20, 4);

add_filter('acf/validate_value/name=use_primary_location_email', static function ($valid, $value) {
    return meza_validate_business_information_primary_location_dependency($valid, 'email', $value);
}, 20, 4);

add_filter('acf/validate_value/name=use_primary_location_address', static function ($valid, $value) {
    return meza_validate_business_information_primary_location_dependency($valid, 'address', $value);
}, 20, 4);

add_filter('acf/prepare_field/key=field_meza_business_location_primary', static function ($field) {
    if (!is_admin()) {
        return $field;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!($screen instanceof WP_Screen)) {
        return $field;
    }

    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    if ($page !== 'business-information' || !meza_business_information_has_primary_location()) {
        return $field;
    }

    return !empty($field['value']) ? $field : false;
}, 20);

foreach (meza_get_business_information_branding_field_map() as $field_name => $callbacks) {
    if (isset($callbacks['load']) && is_callable($callbacks['load'])) {
        add_filter("acf/load_value/name={$field_name}", static function ($value, $post_id, $field) use ($callbacks) {
            return call_user_func($callbacks['load']);
        }, 20, 3);
    }

    if (isset($callbacks['validate']) && is_callable($callbacks['validate'])) {
        add_filter("acf/validate_value/name={$field_name}", static function ($valid, $value, $field, $input) use ($callbacks) {
            return call_user_func($callbacks['validate'], $valid, $value);
        }, 20, 4);
    }

    if (isset($callbacks['update']) && is_callable($callbacks['update'])) {
        add_filter("acf/update_value/name={$field_name}", static function ($value, $post_id, $field) use ($callbacks) {
            return call_user_func($callbacks['update'], $value);
        }, 20, 3);
    }
}

add_filter('wp_prepare_themes_for_js', static function (array $themes): array {
    $thumbnail_id = meza_get_theme_thumbnail_id();
    if ($thumbnail_id <= 0) {
        return $themes;
    }

    $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'full');
    if (!is_string($thumbnail_url) || $thumbnail_url === '') {
        return $themes;
    }

    $current_theme = get_stylesheet();

    foreach ($themes as &$theme) {
        if (!is_array($theme) || (string) ($theme['id'] ?? '') !== $current_theme) {
            continue;
        }

        $theme['screenshot'] = [$thumbnail_url];
        break;
    }
    unset($theme);

    return $themes;
}, 20);

add_filter('wpseo_add_opengraph_additional_images', static function ($image_container) {
    if (is_object($image_container) && method_exists($image_container, 'has_images') && $image_container->has_images()) {
        return $image_container;
    }

    $default_id = meza_get_social_share_default_id();
    if ($default_id <= 0 || !is_object($image_container) || !method_exists($image_container, 'add_image_by_id')) {
        return $image_container;
    }

    $image_container->add_image_by_id($default_id);

    return $image_container;
}, 20);

add_filter('wpseo_twitter_image', static function ($image_url): string {
    $image_url = is_scalar($image_url) ? trim((string) $image_url) : '';
    if ($image_url !== '') {
        return $image_url;
    }

    return meza_get_social_share_default_url();
}, 20);

add_filter('wpseo_post_edit_values', static function (array $values, $post): array {
    if (!($post instanceof WP_Post) || !meza_post_is_using_site_default_social_share_image((int) $post->ID)) {
        return $values;
    }

    $default_image_url = meza_get_social_share_default_url();
    if ($default_image_url === '') {
        return $values;
    }

    $values['social_image_template'] = $default_image_url;

    return $values;
}, 20, 2);

add_action('admin_enqueue_scripts', static function (): void {
    if (!is_admin() || !function_exists('get_current_screen')) {
        return;
    }

    $screen = get_current_screen();
    if (!($screen instanceof WP_Screen) || !in_array((string) $screen->base, ['post', 'post-new'], true)) {
        return;
    }

    $post_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;
    if ($post_id <= 0) {
        $post = get_post();
        $post_id = $post instanceof WP_Post ? (int) $post->ID : 0;
    }

    if ($post_id <= 0 || !meza_post_uses_share_image_permalink($post_id)) {
        return;
    }

    $default_image = meza_get_social_share_default_image_payload();
    if (empty($default_image)) {
        return;
    }

    $config = [
        'image' => $default_image,
        'hasExplicitFacebookImage' => meza_post_has_explicit_social_share_image_meta($post_id, [
            '_yoast_wpseo_opengraph-image-id',
            '_yoast_wpseo_opengraph-image',
        ]),
        'hasExplicitTwitterImage' => meza_post_has_explicit_social_share_image_meta($post_id, [
            '_yoast_wpseo_twitter-image-id',
            '_yoast_wpseo_twitter-image',
        ]),
    ];

    $script = 'window.mezaYoastSocialShareDefault = ' . wp_json_encode($config) . ';'
        . '(function(config){'
        . 'if(!config||!config.image||!config.image.url){return;}'
        . 'var attempts=0;'
        . 'var apply=function(){'
        . 'if(!window.wp||!window.wp.data||typeof window.wp.data.dispatch!=="function"){return false;}'
        . 'var store=window.wp.data.dispatch("yoast-seo/editor");'
        . 'if(!store){return false;}'
        . 'if(typeof store.loadFacebookPreviewData==="function"){store.loadFacebookPreviewData();}'
        . 'if(typeof store.loadTwitterPreviewData==="function"){store.loadTwitterPreviewData();}'
        . 'if(!config.hasExplicitFacebookImage&&typeof store.setFacebookPreviewImage==="function"){store.setFacebookPreviewImage(config.image);}'
        . 'if(!config.hasExplicitTwitterImage&&typeof store.setTwitterPreviewImage==="function"){store.setTwitterPreviewImage({id:config.image.id,url:config.image.url,alt:config.image.alt||"",warnings:config.image.warnings||[]});}'
        . 'return true;'
        . '};'
        . 'if(apply()){return;}'
        . 'var timer=window.setInterval(function(){attempts+=1;if(apply()||attempts>40){window.clearInterval(timer);}},150);'
        . '})(window.mezaYoastSocialShareDefault);';

    foreach (['yoast-seo-post-edit', 'yoast-seo-post-edit-classic'] as $handle) {
        if (wp_script_is($handle, 'registered') || wp_script_is($handle, 'enqueued')) {
            wp_add_inline_script($handle, $script, 'after');
        }
    }
}, 20);

if (!function_exists('meza_is_business_information_options_screen')) {
    function meza_is_business_information_options_screen(): bool
    {
        if (!is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        return in_array($page, ['business-information', 'branding'], true);
    }
}

add_filter('acf/ui_options_page/registration_args', function (array $args, array $post): array {
    $menu_slug = (string) ($args['menu_slug'] ?? '');

    if ($menu_slug === 'branding') {
        $args['page_title'] = meza_get_branding_page_title();
        $args['menu_title'] = 'Branding';
        $args['parent_slug'] = meza_get_shared_project_acf_options_page_parent_slug('branding');
        $args['capability'] = meza_get_shared_project_acf_options_page_capability('branding');

        return $args;
    }

    if ($menu_slug === 'content-structure') {
        $args['page_title'] = meza_get_content_structure_page_title();
        $args['menu_title'] = 'Configuration';
        $args['parent_slug'] = meza_get_shared_project_acf_options_page_parent_slug('content-structure');
        $args['capability'] = meza_get_shared_project_acf_options_page_capability('content-structure');

        return $args;
    }

    if ($menu_slug === 'business-information') {
        $args['page_title'] = meza_get_business_information_page_title();
        $args['menu_title'] = 'Information';
        $args['parent_slug'] = meza_get_shared_project_acf_options_page_parent_slug('business-information');
        $args['capability'] = meza_get_shared_project_acf_options_page_capability('business-information');

        return $args;
    }

    if ($menu_slug === 'crm') {
        $args['page_title'] = meza_get_crm_page_title();
        $args['menu_title'] = 'CRM Integration';
        $args['parent_slug'] = meza_get_shared_project_acf_options_page_parent_slug('crm');
        $args['capability'] = meza_get_shared_project_acf_options_page_capability('crm');

        return $args;
    }

    if ($menu_slug === 'ecommerce') {
        $args['page_title'] = meza_get_ecommerce_page_title();
        $args['menu_title'] = 'E-Commerce';
        $args['parent_slug'] = meza_get_shared_project_acf_options_page_parent_slug('ecommerce');
        $args['capability'] = meza_get_shared_project_acf_options_page_capability('ecommerce');

        return $args;
    }

    if ($menu_slug === 'conference') {
        $args['page_title'] = 'Conference';
        $args['menu_title'] = 'Conference';
        $args['menu_slug'] = 'conference';
        $args['capability'] = 'manage_options';
        $args['parent_slug'] = 'none';

        return $args;
    }

    if ($menu_slug === 'conference-schedule') {
        $args['page_title'] = 'Schedule';
        $args['menu_title'] = 'Schedule';
        $args['menu_slug'] = 'conference-schedule';
        $args['capability'] = 'manage_options';
        $args['parent_slug'] = 'conference';

        return $args;
    }

    return $args;
}, 20, 2);

add_filter('acf/get_options_page', function ($page, $slug) {
    if (!is_array($page)) {
        return $page;
    }

    $slug = sanitize_key((string) $slug);
    $legacy_slug_map = meza_get_legacy_shared_project_acf_options_page_slug_map();
    if (isset($legacy_slug_map[$slug])) {
        $slug = $legacy_slug_map[$slug];
    }

    $current_page_slug = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';

    if ($slug === 'branding') {
        $page['page_title'] = $current_page_slug === 'branding'
            ? meza_get_branding_admin_page_title()
            : meza_get_branding_page_title();
        $page['menu_title'] = 'Branding';
        $page['parent_slug'] = meza_get_shared_project_acf_options_page_parent_slug('branding');
        $page['capability'] = meza_get_shared_project_acf_options_page_capability('branding');
    } elseif ($slug === 'content-structure') {
        $page['page_title'] = $current_page_slug === 'content-structure'
            ? meza_get_content_structure_admin_page_title()
            : meza_get_content_structure_page_title();
        $page['menu_title'] = 'Configuration';
        $page['menu_slug'] = 'content-structure';
        $page['parent_slug'] = meza_get_shared_project_acf_options_page_parent_slug('content-structure');
        $page['capability'] = meza_get_shared_project_acf_options_page_capability('content-structure');
    } elseif ($slug === 'business-information') {
        $page['page_title'] = $current_page_slug === 'business-information'
            ? meza_get_business_information_admin_page_title()
            : meza_get_business_information_page_title();
        $page['menu_title'] = 'Information';
        $page['menu_slug'] = 'business-information';
        $page['parent_slug'] = meza_get_shared_project_acf_options_page_parent_slug('business-information');
        $page['capability'] = meza_get_shared_project_acf_options_page_capability('business-information');
    } elseif ($slug === 'crm') {
        $page['page_title'] = $current_page_slug === 'crm'
            ? meza_get_crm_admin_page_title()
            : meza_get_crm_page_title();
        $page['menu_title'] = 'CRM Integration';
        $page['menu_slug'] = 'crm';
        $page['parent_slug'] = meza_get_shared_project_acf_options_page_parent_slug('crm');
        $page['capability'] = meza_get_shared_project_acf_options_page_capability('crm');
    } elseif ($slug === 'ecommerce') {
        $page['page_title'] = $current_page_slug === 'ecommerce'
            ? meza_get_ecommerce_admin_page_title()
            : meza_get_ecommerce_page_title();
        $page['menu_title'] = 'E-Commerce';
        $page['menu_slug'] = 'ecommerce';
        $page['parent_slug'] = meza_get_shared_project_acf_options_page_parent_slug('ecommerce');
        $page['capability'] = meza_get_shared_project_acf_options_page_capability('ecommerce');
    } elseif ($slug === 'conference') {
        $page['page_title'] = 'Conference';
        $page['menu_title'] = 'Conference';
        $page['menu_slug'] = 'conference';
        $page['capability'] = 'manage_options';
        $page['parent_slug'] = 'none';
    } elseif ($slug === 'conference-schedule') {
        $page['page_title'] = 'Schedule';
        $page['menu_title'] = 'Schedule';
        $page['menu_slug'] = 'conference-schedule';
        $page['capability'] = 'manage_options';
        $page['parent_slug'] = 'conference';
    }

    return $page;
}, 20, 2);

add_filter('acf/get_options_pages', function ($pages) {
    if (!is_array($pages)) {
        return $pages;
    }

    foreach (meza_get_legacy_shared_project_acf_options_page_slug_map() as $legacy_slug => $current_slug) {
        if (isset($pages[$current_slug])) {
            unset($pages[$legacy_slug]);
            continue;
        }

        if (!isset($pages[$legacy_slug]) || !is_array($pages[$legacy_slug])) {
            continue;
        }

        $page = $pages[$legacy_slug];
        $page['menu_slug'] = $current_slug;
        $page['page_title'] = meza_get_business_information_page_title();
        $page['menu_title'] = $current_slug === 'business-information'
            ? 'Information'
            : meza_get_business_information_menu_label();
        $page['parent_slug'] = meza_get_shared_project_acf_options_page_parent_slug($current_slug);
        $page['capability'] = meza_get_shared_project_acf_options_page_capability($current_slug);
        $pages[$current_slug] = $page;
        unset($pages[$legacy_slug]);
    }

    return $pages;
}, 50);

add_action('admin_head', function (): void {
    if (!is_admin()) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    $admin_title = meza_get_settings_admin_page_title_for_slug($page);
    if ($admin_title === '') {
        return;
    }
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const heading = document.querySelector('h1.wp-heading-inline');
            if (heading) {
                heading.textContent = <?php echo wp_json_encode($admin_title); ?>;
            }
            if (document.title && document.title.length) {
                document.title = document.title.replace(/^[^<]+/, <?php echo wp_json_encode($admin_title); ?>);
            }
        });
    </script>
    <?php
}, 20);

add_action('admin_init', function (): void {
    if (!is_admin()) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    if ($page === '') {
        return;
    }

    $legacy_slug_map = meza_get_legacy_shared_project_acf_options_page_slug_map();
    if (!isset($legacy_slug_map[$page])) {
        return;
    }

    wp_safe_redirect(admin_url(meza_get_shared_project_acf_options_page_menu_slug($legacy_slug_map[$page])));
    exit;
}, 1);

add_filter('parent_file', function ($parent_file) {
    if (!is_admin()) {
        return $parent_file;
    }

    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    if ($page === '' || !in_array($page, meza_get_shared_project_acf_options_page_slugs(), true)) {
        return $parent_file;
    }

    return meza_get_shared_project_acf_options_page_parent_slug($page);
}, 20);

add_filter('submenu_file', function ($submenu_file) {
    if (!is_admin()) {
        return $submenu_file;
    }

    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    if ($page === '' || !in_array($page, meza_get_shared_project_acf_options_page_slugs(), true)) {
        return $submenu_file;
    }

    return meza_get_shared_project_acf_options_page_submenu_slug($page);
}, 20);

if (!function_exists('meza_ensure_acf_admin_options_page_class_loaded')) {
    function meza_ensure_acf_admin_options_page_class_loaded(): void
    {
        static $initialized = false;

        if ($initialized) {
            return;
        }

        $initialized = true;

        if (!class_exists('acf_admin_options_page')) {
            $acf_admin_options_page_file = WP_PLUGIN_DIR . '/advanced-custom-fields-pro/pro/admin/admin-options-page.php';
            if (is_readable($acf_admin_options_page_file)) {
                require_once $acf_admin_options_page_file;
            }
        }
    }
}

function meza_prepare_business_settings_acf_subpages(): void
{
    if (
        !is_admin()
        || !function_exists('meza_get_business_settings_menu_slug')
        || !function_exists('meza_shared_project_options_page_capability')
        || !function_exists('meza_can_access_business_settings')
        || !meza_can_access_business_settings(wp_get_current_user())
    ) {
        return;
    }

    meza_ensure_acf_admin_options_page_class_loaded();
}
add_action('admin_menu', 'meza_prepare_business_settings_acf_subpages', 98);
add_action('admin_menu_editor-menu_replaced', 'meza_prepare_business_settings_acf_subpages', 98);

if (!function_exists('meza_normalize_branding_settings_submenu_item')) {
    function meza_normalize_branding_settings_submenu_item(): void
    {
        global $submenu;

        $acf_options_available = meza_shared_project_acf_options_are_available();
        $branding_capability = meza_get_shared_project_acf_options_page_capability('branding');
        $business_information_capability = meza_get_shared_project_acf_options_page_capability('business-information');
        $content_structure_capability = meza_get_shared_project_acf_options_page_capability('content-structure');
        $crm_capability = meza_get_shared_project_acf_options_page_capability('crm');
        $ecommerce_capability = meza_get_shared_project_acf_options_page_capability('ecommerce');

        $business_information_item = [
            'Information',
            $business_information_capability,
            meza_get_shared_project_acf_options_page_submenu_slug('business-information'),
            meza_get_business_information_admin_page_title(),
        ];
        $branding_item = [
            'Branding',
            $branding_capability,
            meza_get_shared_project_acf_options_page_submenu_slug('branding'),
            meza_get_branding_admin_page_title(),
        ];
        $content_structure_item = [
            'Configuration',
            $content_structure_capability,
            meza_get_shared_project_acf_options_page_submenu_slug('content-structure'),
            meza_get_content_structure_admin_page_title(),
        ];
        $crm_item = [
            'CRM Integration',
            $crm_capability,
            meza_get_shared_project_acf_options_page_submenu_slug('crm'),
            meza_get_crm_admin_page_title(),
        ];
        $ecommerce_item = [
            'E-Commerce',
            $ecommerce_capability,
            meza_get_shared_project_acf_options_page_submenu_slug('ecommerce'),
            meza_get_ecommerce_admin_page_title(),
        ];
        $site_management_items = array_values(array_filter([
            function_exists('meza_get_dashboard_site_health_submenu_item')
                ? meza_get_dashboard_site_health_submenu_item()
                : null,
            function_exists('meza_get_dashboard_updates_submenu_item')
                ? meza_get_dashboard_updates_submenu_item()
                : null,
            function_exists('meza_get_dashboard_activity_submenu_item')
                ? meza_get_dashboard_activity_submenu_item()
                : null,
        ], 'is_array'));

        $matching_slugs = [
            'business-information',
            'admin.php?page=business-information',
            'index.php?page=business-information',
            'themes.php?page=business-information',
            'options-general.php?page=business-information',
            'branding',
            'admin.php?page=branding',
            'themes.php?page=branding',
            'options-general.php?page=branding',
            'content-structure',
            'admin.php?page=content-structure',
            'options-general.php?page=content-structure',
            'crm',
            'admin.php?page=crm',
            'options-general.php?page=crm',
            'ecommerce',
            'admin.php?page=ecommerce',
            'options-general.php?page=ecommerce',
            'update-core.php',
            'site-health.php',
        ];
        if (defined('MEZA_ACTIVITY_LOG_PAGE_SLUG')) {
            $matching_slugs[] = MEZA_ACTIVITY_LOG_PAGE_SLUG;
            $matching_slugs[] = 'index.php?page=' . MEZA_ACTIVITY_LOG_PAGE_SLUG;
            $matching_slugs[] = 'admin.php?page=' . MEZA_ACTIVITY_LOG_PAGE_SLUG;
        }

        foreach (['index.php', 'themes.php', 'options-general.php', 'meza-business-settings', 'meza-site-settings', 'meza-integrations-settings'] as $parent_slug) {
            if (!isset($submenu[$parent_slug]) || !is_array($submenu[$parent_slug])) {
                $submenu[$parent_slug] = [];
            }

            $submenu[$parent_slug] = array_values(array_filter(
                $submenu[$parent_slug],
                static function ($item) use ($matching_slugs): bool {
                    if (!is_array($item)) {
                        return false;
                    }

                    $slug = (string) ($item[2] ?? '');
                    return !in_array($slug, $matching_slugs, true);
                }
            ));
        }

        if (!$acf_options_available) {
            return;
        }

        if (current_user_can($business_information_capability)) {
            $submenu['meza-business-settings'] = array_values(array_filter([
                $business_information_item,
                $branding_item,
            ], 'is_array'));
        } else {
            unset($submenu['meza-business-settings']);
        }

        if (current_user_can($content_structure_capability)) {
            $submenu['meza-site-settings'] = array_values(array_filter([
                ...$site_management_items,
                $content_structure_item,
            ], 'is_array'));
        } else {
            unset($submenu['meza-site-settings']);
        }

        if (current_user_can($crm_capability)) {
            $submenu['meza-integrations-settings'] = array_values(array_filter([
                $crm_item,
                $ecommerce_item,
            ], 'is_array'));
        } else {
            unset($submenu['meza-integrations-settings']);
        }
    }
}

add_action('admin_menu', 'meza_normalize_branding_settings_submenu_item', PHP_INT_MAX - 1);
add_action('admin_menu_editor-menu_replaced', 'meza_normalize_branding_settings_submenu_item', PHP_INT_MAX - 1);
add_action('admin_menu', 'meza_normalize_branding_settings_submenu_item', PHP_INT_MAX);
add_action('admin_menu_editor-menu_replaced', 'meza_normalize_branding_settings_submenu_item', PHP_INT_MAX);

add_action('after_setup_theme', function (): void {
    if (function_exists('add_image_size')) {
        add_image_size('meza_branding_preview', 250, 50, false);
        add_image_size('meza_theme_thumbnail_preview', 250, 188, false);
    }
}, 20);

add_action('admin_head', function (): void {
    if (!meza_is_business_information_options_screen()) {
        return;
    }
?>
    <style id="meza-business-branding-preview-fix">
        #acf-group_meza_business_branding .acf-image-uploader .image-wrap,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-image-uploader .image-wrap {
            padding: 10px;
            box-sizing: border-box;
            background: #f0f0f1;
        }

        #acf-group_meza_business_branding .acf-field[data-name="wp_site_logo_alternative"] .acf-image-uploader .image-wrap,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-field[data-name="wp_site_logo_alternative"] .acf-image-uploader .image-wrap {
            background: #1d2327;
        }

        #acf-group_meza_business_branding .acf-field[data-name="wp_site_logo_alternative"] .acf-image-uploader .image-wrap img,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-field[data-name="wp_site_logo_alternative"] .acf-image-uploader .image-wrap img {
            background: transparent;
        }

        #acf-group_meza_business_branding .acf-field[data-name="wp_theme_thumbnail"] .acf-image-uploader .image-wrap,
        #acf-group_meza_business_branding .acf-field[data-name="wp_social_share_default"] .acf-image-uploader .image-wrap,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-field[data-name="wp_theme_thumbnail"] .acf-image-uploader .image-wrap,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-field[data-name="wp_social_share_default"] .acf-image-uploader .image-wrap {
            width: 250px;
            height: 188px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        #acf-group_meza_business_branding .acf-field[data-name="wp_theme_thumbnail"] .acf-image-uploader .image-wrap img,
        #acf-group_meza_business_branding .acf-field[data-name="wp_social_share_default"] .acf-image-uploader .image-wrap img,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-field[data-name="wp_theme_thumbnail"] .acf-image-uploader .image-wrap img,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-field[data-name="wp_social_share_default"] .acf-image-uploader .image-wrap img {
            width: 100%;
            height: 100%;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        #acf-group_meza_business_branding .acf-label p,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-label p {
            display: block;
            margin-top: 6px;
            color: #646970;
        }

        #acf-group_meza_business_branding .acf-input>p.description,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-input>p.description {
            display: none;
        }

        #acf-group_meza_business_branding .acf-image-uploader .image-wrap img[src$=".svg"],
        .acf-postbox[data-key="group_meza_business_branding"] .acf-image-uploader .image-wrap img[src$=".svg"] {
            min-width: 0;
            min-height: 0;
        }

        #acf-group_meza_business_branding .acf-field[data-name="wp_site_icon"] .acf-image-uploader,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-field[data-name="wp_site_icon"] .acf-image-uploader {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }

        #acf-group_meza_business_branding .meza-site-icon-dark-preview,
        .acf-postbox[data-key="group_meza_business_branding"] .meza-site-icon-dark-preview {
            display: none;
            padding: 10px;
            box-sizing: border-box;
            background: #1d2327;
        }

        #acf-group_meza_business_branding .meza-site-icon-dark-preview.is-visible,
        .acf-postbox[data-key="group_meza_business_branding"] .meza-site-icon-dark-preview.is-visible {
            display: block;
        }

        #acf-group_meza_business_branding .meza-site-icon-dark-preview img,
        .acf-postbox[data-key="group_meza_business_branding"] .meza-site-icon-dark-preview img {
            display: block;
            width: auto;
            height: auto;
            max-width: 100%;
            max-height: 50px;
            background: transparent;
        }

        #acf-group_meza_business_branding .acf-field[data-name="wp_site_icon"] .acf-image-uploader .hide-if-value,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-field[data-name="wp_site_icon"] .acf-image-uploader .hide-if-value {
            flex-basis: 100%;
        }
    </style>
    <script id="meza-business-branding-label-notes">
        (() => {
            const moveDescriptions = () => {
                const fields = document.querySelectorAll(
                    '#acf-group_meza_business_branding .acf-field, .acf-postbox[data-key="group_meza_business_branding"] .acf-field'
                );

                fields.forEach((field) => {
                    if (!(field instanceof HTMLElement)) {
                        return;
                    }

                    const label = field.querySelector(':scope > .acf-label');
                    const inputDescription = field.querySelector(':scope > .acf-input > p.description');

                    if (!(label instanceof HTMLElement) || !(inputDescription instanceof HTMLElement)) {
                        return;
                    }

                    let labelDescription = label.querySelector(':scope > p');
                    if (!(labelDescription instanceof HTMLElement)) {
                        labelDescription = document.createElement('p');
                        label.appendChild(labelDescription);
                    }

                    labelDescription.textContent = inputDescription.textContent ?? '';
                });
            };

            const syncSiteIconDarkPreview = (uploader) => {
                if (!(uploader instanceof HTMLElement)) {
                    return;
                }

                let preview = uploader.querySelector(':scope > .meza-site-icon-dark-preview');
                if (!(preview instanceof HTMLElement)) {
                    preview = document.createElement('div');
                    preview.className = 'meza-site-icon-dark-preview';
                    preview.innerHTML = '<img alt="" />';
                    const hideIfValue = uploader.querySelector(':scope > .hide-if-value');
                    if (hideIfValue instanceof HTMLElement) {
                        uploader.insertBefore(preview, hideIfValue);
                    } else {
                        uploader.appendChild(preview);
                    }
                }

                const previewImage = preview.querySelector('img');
                const sourceImage = uploader.querySelector(':scope > .show-if-value.image-wrap img');
                const hasValue = uploader.classList.contains('has-value') &&
                    sourceImage instanceof HTMLImageElement &&
                    sourceImage.getAttribute('src');

                if (!(previewImage instanceof HTMLImageElement) || !hasValue) {
                    preview.classList.remove('is-visible');
                    if (previewImage instanceof HTMLImageElement) {
                        previewImage.removeAttribute('src');
                        previewImage.removeAttribute('alt');
                    }
                    return;
                }

                previewImage.src = sourceImage.getAttribute('src') || '';
                previewImage.alt = sourceImage.getAttribute('alt') || '';
                preview.classList.add('is-visible');
            };

            const bindSiteIconSourceObserver = (uploader) => {
                if (!(uploader instanceof HTMLElement)) {
                    return;
                }

                if (uploader.mezaSiteIconSourceObserver instanceof MutationObserver) {
                    uploader.mezaSiteIconSourceObserver.disconnect();
                }

                const sourceImage = uploader.querySelector(':scope > .show-if-value.image-wrap img');
                if (!(sourceImage instanceof HTMLImageElement)) {
                    uploader.mezaSiteIconSourceObserver = null;
                    return;
                }

                const sourceObserver = new MutationObserver(() => {
                    syncSiteIconDarkPreview(uploader);
                });

                sourceObserver.observe(sourceImage, {
                    attributes: true,
                    attributeFilter: ['src', 'alt'],
                });

                uploader.mezaSiteIconSourceObserver = sourceObserver;
            };

            const setupSiteIconDarkPreview = () => {
                const uploaders = document.querySelectorAll(
                    '#acf-group_meza_business_branding .acf-field[data-name="wp_site_icon"] .acf-image-uploader, .acf-postbox[data-key="group_meza_business_branding"] .acf-field[data-name="wp_site_icon"] .acf-image-uploader'
                );

                uploaders.forEach((uploader) => {
                    if (!(uploader instanceof HTMLElement)) {
                        return;
                    }

                    syncSiteIconDarkPreview(uploader);
                    bindSiteIconSourceObserver(uploader);

                    if (uploader.dataset.mezaSiteIconPreviewReady === '1') {
                        return;
                    }

                    const classObserver = new MutationObserver(() => {
                        syncSiteIconDarkPreview(uploader);
                        bindSiteIconSourceObserver(uploader);
                    });

                    classObserver.observe(uploader, {
                        attributes: true,
                        attributeFilter: ['class'],
                    });

                    const treeObserver = new MutationObserver(() => {
                        syncSiteIconDarkPreview(uploader);
                        bindSiteIconSourceObserver(uploader);
                    });

                    treeObserver.observe(uploader, {
                        childList: true,
                        subtree: true,
                    });

                    uploader.dataset.mezaSiteIconPreviewReady = '1';
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    moveDescriptions();
                    setupSiteIconDarkPreview();
                }, {
                    once: true
                });
            } else {
                moveDescriptions();
                setupSiteIconDarkPreview();
            }
        })();
    </script>
<?php
});

add_action('admin_head', function (): void {
    if (!is_admin()) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    if ($page !== 'business-information') {
        return;
    }
?>
    <style id="meza-business-contact-compact-ui">
        #acf-group_68c1337b43d46 .acf-field[data-key="field_meza_business_phone_group"]>.acf-label,
        #acf-group_68c1337b43d46 .acf-field[data-key="field_meza_business_email_group"]>.acf-label,
        #acf-group_68c1337b43d46 .acf-field[data-key="field_meza_business_address_group"]>.acf-label,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-key="field_meza_business_phone_group"]>.acf-label,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-key="field_meza_business_email_group"]>.acf-label,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-key="field_meza_business_address_group"]>.acf-label {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }

        #acf-group_68c1337b43d46 .acf-field[data-key="field_meza_business_phone_group"]>.acf-input,
        #acf-group_68c1337b43d46 .acf-field[data-key="field_meza_business_email_group"]>.acf-input,
        #acf-group_68c1337b43d46 .acf-field[data-key="field_meza_business_address_group"]>.acf-input,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-key="field_meza_business_phone_group"]>.acf-input,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-key="field_meza_business_email_group"]>.acf-input,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-key="field_meza_business_address_group"]>.acf-input {
            padding-left: 12px !important;
            padding-right: 12px !important;
        }

        #acf-group_68c1337b43d46 .acf-field[data-key="field_meza_business_email_group"]>.acf-label label::after,
        #acf-group_68c1337b43d46 .acf-field[data-key="field_meza_business_address_group"]>.acf-label label::after,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-key="field_meza_business_email_group"]>.acf-label label::after,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-key="field_meza_business_address_group"]>.acf-label label::after {
            content: " *";
            color: #f00;
        }

        #acf-group_68c1337b43d46 .acf-field[data-type="group"]>.acf-input>.acf-fields,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-type="group"]>.acf-input>.acf-fields {
            border: 0;
            background: transparent;
        }

        #acf-group_68c1337b43d46 .acf-field[data-type="group"]>.acf-input>.acf-fields>.acf-field,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-type="group"]>.acf-input>.acf-fields>.acf-field {
            border: 0;
            margin: 0;
            padding: 0 0 8px;
        }

        #acf-group_68c1337b43d46 .acf-field[data-type="group"]>.acf-input>.acf-fields>.acf-field:last-child,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-type="group"]>.acf-input>.acf-fields>.acf-field:last-child {
            padding-bottom: 0;
        }

        #acf-group_68c1337b43d46 .acf-field[data-type="group"]>.acf-input>.acf-fields>.acf-field .acf-label,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-type="group"]>.acf-input>.acf-fields>.acf-field .acf-label {
            margin: 0;
            padding: 0;
        }

        #acf-group_68c1337b43d46 .acf-field[data-type="group"]>.acf-input>.acf-fields>.acf-field .acf-input,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-type="group"]>.acf-input>.acf-fields>.acf-field .acf-input {
            margin: 0;
            padding: 0;
        }

        #acf-group_68c1337b43d46 .acf-field[data-type="group"]>.acf-input>.acf-fields>.acf-field .acf-input .description,
        .acf-postbox[data-key="group_68c1337b43d46"] .acf-field[data-type="group"]>.acf-input>.acf-fields>.acf-field .acf-input .description {
            margin: 0 0 8px;
        }
    </style>
<?php
}, 20);
