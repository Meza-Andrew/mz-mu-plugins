<?php

/**
 * Shared ACF option pages and field groups we want available on every project.
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) {
    return;
}

if (!function_exists('meza_get_business_information_type')) {
    function meza_get_business_information_type(): string
    {
        $option_value = get_option('options_type');
        $value = is_scalar($option_value) ? trim((string) $option_value) : '';

        $value = sanitize_key($value);

        return in_array($value, ['business', 'nonprofit'], true) ? $value : 'business';
    }
}

if (!function_exists('meza_get_business_information_menu_label')) {
    function meza_get_business_information_menu_label(): string
    {
        return meza_get_business_information_type() === 'nonprofit'
            ? 'Nonprofit Information'
            : 'Business Information';
    }
}

if (!function_exists('meza_get_business_information_page_title')) {
    function meza_get_business_information_page_title(): string
    {
        return meza_get_business_information_menu_label() . ' Settings';
    }
}

if (!function_exists('meza_shared_project_acf_options_are_available')) {
    function meza_shared_project_acf_options_are_available(): bool
    {
        return function_exists('acf_add_options_page') && function_exists('acf_add_local_field_group');
    }
}

if (!function_exists('meza_get_theme_thumbnail_id')) {
    function meza_get_theme_thumbnail_id(): int
    {
        $thumbnail_id = (int) get_option('meza_theme_thumbnail_id', 0);

        return $thumbnail_id > 0 && get_post_type($thumbnail_id) === 'attachment'
            ? $thumbnail_id
            : 0;
    }
}

if (!function_exists('meza_get_social_share_default_id')) {
    function meza_get_social_share_default_id(): int
    {
        $default_id = (int) get_option('meza_social_share_default_id', 0);
        if ($default_id > 0 && get_post_type($default_id) === 'attachment') {
            return $default_id;
        }

        return meza_get_theme_thumbnail_id();
    }
}

if (!function_exists('meza_get_social_share_default_url')) {
    function meza_get_social_share_default_url(): string
    {
        $default_id = meza_get_social_share_default_id();
        if ($default_id <= 0) {
            return '';
        }

        $default_url = wp_get_attachment_image_url($default_id, 'full');

        return is_string($default_url) ? $default_url : '';
    }
}

if (!function_exists('meza_post_uses_share_image_permalink')) {
    function meza_post_uses_share_image_permalink(int $post_id): bool
    {
        $post = get_post($post_id);
        if (!($post instanceof WP_Post)) {
            return false;
        }

        if (in_array((string) $post->post_status, ['auto-draft', 'trash'], true)) {
            return false;
        }

        $post_type = trim((string) $post->post_type);
        if ($post_type === '') {
            return false;
        }

        $post_type_object = get_post_type_object($post_type);
        if (!($post_type_object instanceof WP_Post_Type)) {
            return false;
        }

        if (function_exists('is_post_type_viewable') && !is_post_type_viewable($post_type_object)) {
            return false;
        }

        if ($post_type === 'post' || $post_type === 'page') {
            return true;
        }

        return !empty($post_type_object->rewrite) || !empty($post_type_object->query_var);
    }
}

if (!function_exists('meza_get_post_explicit_social_share_image_id')) {
    function meza_get_post_explicit_social_share_image_id(int $post_id): int
    {
        if ($post_id <= 0) {
            return 0;
        }

        foreach ([
            '_yoast_wpseo_opengraph-image-id',
            '_yoast_wpseo_twitter-image-id',
        ] as $meta_key) {
            $attachment_id = absint(get_post_meta($post_id, $meta_key, true));
            if ($attachment_id > 0 && get_post_type($attachment_id) === 'attachment') {
                return $attachment_id;
            }
        }

        foreach ([
            '_yoast_wpseo_opengraph-image',
            '_yoast_wpseo_twitter-image',
        ] as $meta_key) {
            $image_url = trim((string) get_post_meta($post_id, $meta_key, true));
            if ($image_url === '') {
                continue;
            }

            $attachment_id = function_exists('attachment_url_to_postid')
                ? absint(attachment_url_to_postid($image_url))
                : 0;

            if ($attachment_id > 0 && get_post_type($attachment_id) === 'attachment') {
                return $attachment_id;
            }
        }

        return 0;
    }
}

if (!function_exists('meza_get_post_explicit_social_share_image_url')) {
    function meza_get_post_explicit_social_share_image_url(int $post_id): string
    {
        $attachment_id = meza_get_post_explicit_social_share_image_id($post_id);
        if ($attachment_id > 0) {
            $image_url = wp_get_attachment_image_url($attachment_id, 'full');
            if (is_string($image_url) && $image_url !== '') {
                return $image_url;
            }
        }

        foreach ([
            '_yoast_wpseo_opengraph-image',
            '_yoast_wpseo_twitter-image',
        ] as $meta_key) {
            $image_url = trim((string) get_post_meta($post_id, $meta_key, true));
            if ($image_url !== '') {
                return $image_url;
            }
        }

        return '';
    }
}

if (!function_exists('meza_post_has_explicit_social_share_image')) {
    function meza_post_has_explicit_social_share_image(int $post_id): bool
    {
        if ($post_id <= 0) {
            return false;
        }

        if (meza_get_post_explicit_social_share_image_id($post_id) > 0) {
            return true;
        }

        return meza_get_post_explicit_social_share_image_url($post_id) !== '';
    }
}

if (!function_exists('meza_post_has_explicit_social_share_image_meta')) {
    function meza_post_has_explicit_social_share_image_meta(int $post_id, array $meta_keys): bool
    {
        if ($post_id <= 0) {
            return false;
        }

        foreach ($meta_keys as $meta_key) {
            $meta_value = trim((string) get_post_meta($post_id, (string) $meta_key, true));
            if ($meta_value !== '') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('meza_get_post_resolved_social_share_image_id')) {
    function meza_get_post_resolved_social_share_image_id(int $post_id): int
    {
        $explicit_attachment_id = meza_get_post_explicit_social_share_image_id($post_id);
        if ($explicit_attachment_id > 0) {
            return $explicit_attachment_id;
        }

        if (!meza_post_uses_share_image_permalink($post_id)) {
            return 0;
        }

        return meza_get_social_share_default_id();
    }
}

if (!function_exists('meza_get_post_resolved_social_share_image_url')) {
    function meza_get_post_resolved_social_share_image_url(int $post_id): string
    {
        $explicit_image_url = meza_get_post_explicit_social_share_image_url($post_id);
        if ($explicit_image_url !== '') {
            return $explicit_image_url;
        }

        if (!meza_post_uses_share_image_permalink($post_id)) {
            return '';
        }

        return meza_get_social_share_default_url();
    }
}

if (!function_exists('meza_post_is_using_site_default_social_share_image')) {
    function meza_post_is_using_site_default_social_share_image(int $post_id): bool
    {
        if ($post_id <= 0 || meza_post_has_explicit_social_share_image($post_id)) {
            return false;
        }

        return meza_post_uses_share_image_permalink($post_id) && meza_get_social_share_default_id() > 0;
    }
}

if (!function_exists('meza_get_social_share_default_image_payload')) {
    function meza_get_social_share_default_image_payload(): array
    {
        $attachment_id = meza_get_social_share_default_id();
        $image_url = meza_get_social_share_default_url();

        if ($attachment_id <= 0 || $image_url === '') {
            return [];
        }

        return [
            'id' => $attachment_id,
            'url' => $image_url,
            'alt' => trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true)),
            'warnings' => [],
        ];
    }
}

if (!function_exists('meza_get_theme_thumbnail_allowed_extensions')) {
    function meza_get_theme_thumbnail_allowed_extensions(): array
    {
        return ['png', 'gif', 'jpg', 'jpeg', 'webp', 'avif'];
    }
}

if (!function_exists('meza_theme_thumbnail_attachment_is_supported')) {
    function meza_theme_thumbnail_attachment_is_supported(int $attachment_id): bool
    {
        $mime_type = strtolower((string) get_post_mime_type($attachment_id));

        return in_array($mime_type, [
            'image/png',
            'image/gif',
            'image/jpeg',
            'image/webp',
            'image/avif',
        ], true);
    }
}

if (!function_exists('meza_get_shared_project_acf_options_pages')) {
    function meza_get_shared_project_acf_options_pages(): array
    {
        $pages = [
            [
                'page_title'      => meza_get_business_information_page_title(),
                'menu_title'      => meza_get_business_information_menu_label(),
                'menu_slug'       => 'business-information',
                'parent_slug'     => 'options-general.php',
                'capability'      => 'manage_options',
                'redirect'        => false,
                'update_button'   => 'Update',
                'updated_message' => 'Settings Updated',
                'autoload'        => false,
            ],
            [
                'page_title'      => 'Branding Settings',
                'menu_title'      => 'Branding',
                'menu_slug'       => 'branding',
                'parent_slug'     => 'options-general.php',
                'capability'      => 'manage_options',
                'redirect'        => false,
                'update_button'   => 'Update',
                'updated_message' => 'Options Updated',
                'autoload'        => false,
            ],
            [
                'page_title'      => 'CRM Integration Settings',
                'menu_title'      => 'CRM Integration',
                'menu_slug'       => 'crm',
                'parent_slug'     => 'options-general.php',
                'capability'      => 'manage_options',
                'redirect'        => false,
                'update_button'   => 'Update',
                'updated_message' => 'Settings Updated',
                'autoload'        => false,
            ],
        ];

        $pages = apply_filters('meza_shared_project_acf_options_pages', $pages);

        return is_array($pages) ? array_values($pages) : [];
    }
}

if (!function_exists('meza_get_shared_project_acf_options_page_slugs')) {
    function meza_get_shared_project_acf_options_page_slugs(): array
    {
        $slugs = [];

        foreach (meza_get_shared_project_acf_options_pages() as $page) {
            $slug = sanitize_key((string) ($page['menu_slug'] ?? ''));
            if ($slug !== '') {
                $slugs[$slug] = true;
            }
        }

        return array_keys($slugs);
    }
}

if (!function_exists('meza_get_legacy_shared_project_acf_options_page_slug_map')) {
    function meza_get_legacy_shared_project_acf_options_page_slug_map(): array
    {
        return [
            'business-info' => 'business-information',
            'organization-info' => 'business-information',
        ];
    }
}

if (!function_exists('meza_get_shared_project_acf_field_groups')) {
    function meza_get_business_information_general_fields(): array
    {
        return [
            [
                'key' => 'field_meza_business_site_title',
                'label' => 'Name',
                'name' => 'wp_site_title',
                'aria-label' => '',
                'type' => 'text',
                'instructions' => 'Updates the WordPress site name used in browser tabs, admin screens, and fallback branding.',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'default_value' => '',
                'maxlength' => '',
                'allow_in_bindings' => 0,
                'placeholder' => '',
                'prepend' => '',
                'append' => '',
            ],
            [
                'key' => 'field_meza_business_tagline',
                'label' => 'Tagline',
                'name' => 'wp_tagline',
                'aria-label' => '',
                'type' => 'text',
                'instructions' => 'Updates the WordPress site tagline used in metadata and select templates.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'default_value' => '',
                'maxlength' => '',
                'allow_in_bindings' => 0,
                'placeholder' => '',
                'prepend' => '',
                'append' => '',
            ],
            [
                'key' => 'field_meza_business_location',
                'label' => 'Location',
                'name' => 'location',
                'aria-label' => '',
                'type' => 'text',
                'instructions' => '',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'default_value' => '',
                'maxlength' => '',
                'allow_in_bindings' => 0,
                'placeholder' => '',
                'prepend' => '',
                'append' => '',
            ],
            [
                'key' => 'field_meza_business_type',
                'label' => 'Type',
                'name' => 'type',
                'aria-label' => '',
                'type' => 'radio',
                'instructions' => '',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'choices' => [
                    'business' => 'Business',
                    'nonprofit' => 'Nonprofit',
                ],
                'default_value' => 'business',
                'return_format' => 'value',
                'allow_null' => 0,
                'other_choice' => 0,
                'save_other_choice' => 0,
                'layout' => 'horizontal',
            ],
        ];
    }

    function meza_get_business_information_branding_fields(): array
    {
        return [
            [
                'key' => 'field_meza_business_site_logo',
                'label' => 'Site Logo',
                'name' => 'wp_site_logo',
                'aria-label' => '',
                'type' => 'image',
                'instructions' => 'Appears in the header, login screen, and email templates. Upload a transparent version when possible.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'return_format' => 'id',
                'library' => 'all',
                'preview_size' => 'meza_branding_preview',
                'min_width' => '',
                'min_height' => '',
                'min_size' => '',
                'max_width' => '',
                'max_height' => '',
                'max_size' => '',
                'mime_types' => '',
                'allow_in_bindings' => 0,
            ],
            [
                'key' => 'field_meza_business_site_logo_alt',
                'label' => 'Site Logo (Alternative)',
                'name' => 'wp_site_logo_alternative',
                'aria-label' => '',
                'type' => 'image',
                'instructions' => 'Appears in the footer and other dark sections. Upload a transparent version when possible.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'return_format' => 'id',
                'library' => 'all',
                'preview_size' => 'meza_branding_preview',
                'min_width' => '',
                'min_height' => '',
                'min_size' => '',
                'max_width' => '',
                'max_height' => '',
                'max_size' => '',
                'mime_types' => '',
                'allow_in_bindings' => 0,
            ],
            [
                'key' => 'field_meza_business_site_icon',
                'label' => 'Site Icon',
                'name' => 'wp_site_icon',
                'aria-label' => '',
                'type' => 'image',
                'instructions' => 'Used in browser tabs, bookmark bars, and mobile apps. Upload a square image that is at least 512 by 512 pixels.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'return_format' => 'id',
                'library' => 'all',
                'preview_size' => 'meza_branding_preview',
                'min_width' => '',
                'min_height' => '',
                'min_size' => '',
                'max_width' => '',
                'max_height' => '',
                'max_size' => '',
                'mime_types' => '',
                'allow_in_bindings' => 0,
            ],
            [
                'key' => 'field_meza_business_theme_thumbnail',
                'label' => 'Theme Thumbnail',
                'name' => 'wp_theme_thumbnail',
                'aria-label' => '',
                'type' => 'image',
                'instructions' => 'Used by WordPress to represent the website. Upload a 4:3 image that is at least 1200 by 900 pixels.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'return_format' => 'id',
                'library' => 'all',
                'preview_size' => 'meza_theme_thumbnail_preview',
                'min_width' => '',
                'min_height' => '',
                'min_size' => '',
                'max_width' => '',
                'max_height' => '',
                'max_size' => '',
                'mime_types' => 'png,gif,jpg,jpeg,webp,avif',
                'allow_in_bindings' => 0,
            ],
            [
                'key' => 'field_meza_business_social_share_default',
                'label' => 'Social Share Default',
                'name' => 'wp_social_share_default',
                'aria-label' => '',
                'type' => 'image',
                'instructions' => 'Used when a page or post does not have its own share image. Defaults to the Theme Thumbnail until you choose a replacement.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'return_format' => 'id',
                'library' => 'all',
                'preview_size' => 'meza_theme_thumbnail_preview',
                'min_width' => '',
                'min_height' => '',
                'min_size' => '',
                'max_width' => '',
                'max_height' => '',
                'max_size' => '',
                'mime_types' => 'png,gif,jpg,jpeg,webp,avif',
                'allow_in_bindings' => 0,
            ],
        ];
    }

    function meza_get_crm_integration_fields(): array
    {
        return [
            [
                'key' => 'field_69b5b54f29383',
                'label' => 'Platform',
                'name' => 'platform',
                'aria-label' => '',
                'type' => 'select',
                'instructions' => '',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'choices' => [
                    'Constant Contact' => 'Constant Contact',
                    'Go High Level' => 'Go High Level',
                    'MailChimp' => 'MailChimp',
                    'Zeffy' => 'Zeffy',
                ],
                'default_value' => false,
                'return_format' => 'value',
                'multiple' => 0,
                'allow_null' => 1,
                'allow_in_bindings' => 0,
                'ui' => 0,
                'ajax' => 0,
                'placeholder' => '',
                'create_options' => 0,
                'save_options' => 0,
            ],
            [
                'key' => 'field_69b5b6c6fe884',
                'label' => 'Constant Contact',
                'name' => 'constant-contact',
                'aria-label' => '',
                'type' => 'group',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_69b5b54f29383',
                            'operator' => '==',
                            'value' => 'Constant Contact',
                        ],
                    ],
                ],
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'layout' => 'row',
                'sub_fields' => [
                    [
                        'key' => 'field_69b5b6c6fe885',
                        'label' => 'Client ID',
                        'name' => 'client_id',
                        'aria-label' => '',
                        'type' => 'password',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ],
                    [
                        'key' => 'field_69b5c69ed8589',
                        'label' => 'Client Secret',
                        'name' => 'client_secret',
                        'aria-label' => '',
                        'type' => 'password',
                        'instructions' => '',
                        'required' => 1,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_69b5b6c6fe885',
                                    'operator' => '!=empty',
                                ],
                            ],
                        ],
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ],
                    [
                        'key' => 'field_69b5b6c6fe886',
                        'label' => 'List ID',
                        'name' => 'list',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 1,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_69b5b6c6fe885',
                                    'operator' => '!=empty',
                                ],
                                [
                                    'field' => 'field_69b5c69ed8589',
                                    'operator' => '!=empty',
                                ],
                            ],
                        ],
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ],
                ],
            ],
            [
                'key' => 'field_69b5d528ed70b',
                'label' => 'Mailchimp',
                'name' => 'mailchimp',
                'aria-label' => '',
                'type' => 'group',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_69b5b54f29383',
                            'operator' => '==',
                            'value' => 'MailChimp',
                        ],
                    ],
                ],
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'layout' => 'row',
                'sub_fields' => [
                    [
                        'key' => 'field_69b5d528ed70c',
                        'label' => 'API Key',
                        'name' => 'api',
                        'aria-label' => '',
                        'type' => 'password',
                        'instructions' => '',
                        'required' => 1,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ],
                    [
                        'key' => 'field_69b5d528ed70e',
                        'label' => 'List ID',
                        'name' => 'list',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 1,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_69b5d528ed70c',
                                    'operator' => '!=empty',
                                ],
                            ],
                        ],
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ],
                ],
            ],
        ];
    }

    function meza_get_shared_project_acf_field_groups(): array
    {
        $groups = [
            [
                'key' => 'group_meza_business_information_general',
                'title' => 'General',
                'fields' => meza_get_business_information_general_fields(),
                'location' => [
                    [
                        [
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => 'business-information',
                        ],
                    ],
                ],
                'menu_order' => 0,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'left',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
            ],
            [
                'key' => 'group_meza_business_branding',
                'title' => 'Visuals and Identity',
                'fields' => meza_get_business_information_branding_fields(),
                'location' => [
                    [
                        [
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => 'branding',
                        ],
                    ],
                ],
                'menu_order' => 0,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'left',
                'instruction_placement' => 'field',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
            ],
            [
                'key' => 'group_69b5b29b9d099',
                'title' => 'CRM Integration',
                'fields' => meza_get_crm_integration_fields(),
                'location' => [
                    [
                        [
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => 'crm',
                        ],
                    ],
                ],
                'menu_order' => 0,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'top',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
            ],
            [
                'key' => 'group_68b3145739719',
                'title' => 'Mission, Vision, and Values',
                'fields' => [
                    [
                        'key' => 'field_69c172d3bb939',
                        'label' => 'Mission',
                        'name' => 'mission',
                        'aria-label' => '',
                        'type' => 'textarea',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'rows' => 3,
                        'placeholder' => '',
                        'new_lines' => '',
                    ],
                    [
                        'key' => 'field_69c172ebbb93a',
                        'label' => 'Vision',
                        'name' => 'vision',
                        'aria-label' => '',
                        'type' => 'textarea',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'rows' => 3,
                        'placeholder' => '',
                        'new_lines' => '',
                    ],
                    [
                        'key' => 'field_69c172fabb93b',
                        'label' => 'Values',
                        'name' => 'values',
                        'aria-label' => '',
                        'type' => 'repeater',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'layout' => 'row',
                        'pagination' => 0,
                        'min' => 0,
                        'max' => 0,
                        'collapsed' => 'field_69c17303bb93c',
                        'button_label' => 'Add Value',
                        'rows_per_page' => 20,
                        'sub_fields' => [
                            [
                                'key' => 'field_69c17303bb93c',
                                'label' => 'Name',
                                'name' => 'name',
                                'aria-label' => '',
                                'type' => 'text',
                                'instructions' => '',
                                'required' => 1,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'default_value' => '',
                                'maxlength' => '',
                                'allow_in_bindings' => 0,
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                                'parent_repeater' => 'field_69c172fabb93b',
                            ],
                            [
                                'key' => 'field_69c1731ebb93d',
                                'label' => 'Description',
                                'name' => 'description',
                                'aria-label' => '',
                                'type' => 'textarea',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'default_value' => '',
                                'maxlength' => '',
                                'allow_in_bindings' => 0,
                                'rows' => 2,
                                'placeholder' => '',
                                'new_lines' => '',
                                'parent_repeater' => 'field_69c172fabb93b',
                            ],
                            [
                                'key' => 'field_69c1732cbb93e',
                                'label' => 'Icon',
                                'name' => 'icon',
                                'aria-label' => '',
                                'type' => 'text',
                                'instructions' => '',
                                'required' => 0,
                                'conditional_logic' => 0,
                                'wrapper' => [
                                    'width' => '',
                                    'class' => '',
                                    'id' => '',
                                ],
                                'default_value' => '',
                                'maxlength' => '',
                                'allow_in_bindings' => 0,
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                                'parent_repeater' => 'field_69c172fabb93b',
                            ],
                        ],
                    ],
                ],
                'location' => [
                    [
                        [
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => 'branding',
                        ],
                    ],
                ],
                'menu_order' => 10,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'left',
                'instruction_placement' => 'field',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
            ],
            [
                'key' => 'group_68c1337b43d46',
                'title' => 'Contact Information',
                'fields' => [
                    [
                        'key' => 'field_69b5a28923bbe',
                        'label' => 'Phone',
                        'name' => 'phone',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => 'Used in the footer of emails sent through the website.',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ],
                    [
                        'key' => 'field_68c133a0425a8',
                        'label' => 'Email',
                        'name' => 'email',
                        'aria-label' => '',
                        'type' => 'email',
                        'instructions' => 'The default recipient for forms. Also used in the footer of emails sent through the website.',
                        'required' => 1,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ],
                    [
                        'key' => 'field_69b5a2b623bc0',
                        'label' => 'Address',
                        'name' => 'address',
                        'aria-label' => '',
                        'type' => 'google_map',
                        'instructions' => 'Used in the footer of emails sent through the website. Only put an address in this field if visitors can come to a real location.',
                        'required' => 1,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_meza_business_mailing_address',
                                    'operator' => '==empty',
                                ],
                            ],
                        ],
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'allow_in_bindings' => 0,
                        'center_lat' => '38.3032',
                        'center_lng' => '-77.4605',
                        'zoom' => '',
                        'height' => '',
                    ],
                    [
                        'key' => 'field_meza_business_mailing_address',
                        'label' => 'Address',
                        'name' => 'address_text',
                        'aria-label' => '',
                        'type' => 'textarea',
                        'instructions' => 'Used in the footer of emails sent through the website. Only put a mailing-only address in this field, such as a P.O. Box.',
                        'required' => 1,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_69b5a2b623bc0',
                                    'operator' => '==empty',
                                ],
                            ],
                        ],
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'rows' => 2,
                        'placeholder' => '',
                        'new_lines' => '',
                    ],
                ],
                'location' => [
                    [
                        [
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => 'business-information',
                        ],
                    ],
                ],
                'menu_order' => 10,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'left',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true,
                'description' => '',
                'show_in_rest' => 0,
                'display_title' => '',
            ],
        ];

        $groups = apply_filters('meza_shared_project_acf_field_groups', $groups);

        return is_array($groups) ? array_values($groups) : [];
    }
}

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
        return $page === 'branding';
    }
}

add_filter('acf/ui_options_page/registration_args', function (array $args, array $post): array {
    $menu_slug = (string) ($args['menu_slug'] ?? '');

    if ($menu_slug === 'branding') {
        $args['page_title'] = 'Branding Settings';
        $args['menu_title'] = 'Branding';

        return $args;
    }

    if ($menu_slug === 'business-information') {
        $args['page_title'] = meza_get_business_information_page_title();
        $args['menu_title'] = meza_get_business_information_menu_label();

        return $args;
    }

    if ($menu_slug === 'crm') {
        $args['page_title'] = 'CRM Integration Settings';
        $args['menu_title'] = 'CRM Integration';
        $args['capability'] = 'manage_options';

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

    if ($slug === 'branding') {
        $page['page_title'] = 'Branding Settings';
        $page['menu_title'] = 'Branding';
    } elseif ($slug === 'business-information') {
        $page['page_title'] = meza_get_business_information_page_title();
        $page['menu_title'] = meza_get_business_information_menu_label();
        $page['menu_slug'] = 'business-information';
        $page['capability'] = 'manage_options';
    } elseif ($slug === 'crm') {
        $page['page_title'] = 'CRM Integration Settings';
        $page['menu_title'] = 'CRM Integration';
        $page['menu_slug'] = 'crm';
        $page['capability'] = 'manage_options';
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
        $page['menu_title'] = meza_get_business_information_menu_label();
        $page['capability'] = 'manage_options';
        $pages[$current_slug] = $page;
        unset($pages[$legacy_slug]);
    }

    return $pages;
}, 50);

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

    wp_safe_redirect(admin_url('options-general.php?page=' . $legacy_slug_map[$page]));
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

    return 'options-general.php';
}, 20);

add_filter('submenu_file', function ($submenu_file) {
    if (!is_admin()) {
        return $submenu_file;
    }

    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    if ($page === '' || !in_array($page, meza_get_shared_project_acf_options_page_slugs(), true)) {
        return $submenu_file;
    }

    return $page;
}, 20);

if (!function_exists('meza_normalize_branding_settings_submenu_item')) {
    function meza_normalize_branding_settings_submenu_item(): void
    {
        global $submenu;

        if (!isset($submenu['options-general.php']) || !is_array($submenu['options-general.php'])) {
            return;
        }

        $acf_options_available = meza_shared_project_acf_options_are_available();
        $is_site_manager = function_exists('meza_is_site_manager_user') && meza_is_site_manager_user(wp_get_current_user());
        $menu_capability = $is_site_manager ? 'read' : 'manage_options';

        $business_information_item = null;
        $branding_item = null;
        $crm_item = null;
        $business_information_menu_label = meza_get_business_information_menu_label();
        $business_information_page_title = meza_get_business_information_page_title();
        $expected_items = [
            'business-information' => [$business_information_menu_label, $menu_capability, 'business-information', $business_information_page_title],
            'branding' => ['Branding', $menu_capability, 'branding', 'Branding Settings'],
            'crm' => ['CRM Integration', 'manage_options', 'crm', 'CRM Integration Settings'],
        ];
        $allowed_expected_item_slugs = array_keys($expected_items);

        if ($is_site_manager) {
            if (function_exists('meza_get_site_manager_allowed_settings_page_slugs')) {
                $allowed_expected_item_slugs = array_values(array_intersect(
                    $allowed_expected_item_slugs,
                    array_map('sanitize_key', meza_get_site_manager_allowed_settings_page_slugs())
                ));
            }
        }

        foreach ($submenu['options-general.php'] as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $slug = (string) ($item[2] ?? '');

            if (in_array($slug, ['business-information', 'admin.php?page=business-information', 'options-general.php?page=business-information'], true)) {
                if (!$acf_options_available) {
                    unset($submenu['options-general.php'][$index]);
                    continue;
                }

                $submenu['options-general.php'][$index][0] = $business_information_menu_label;
                $submenu['options-general.php'][$index][1] = $menu_capability;
                $submenu['options-general.php'][$index][2] = 'business-information';
                if (isset($submenu['options-general.php'][$index][3])) {
                    $submenu['options-general.php'][$index][3] = $business_information_page_title;
                }

                $business_information_item = $submenu['options-general.php'][$index];
                unset($submenu['options-general.php'][$index]);
                continue;
            }

            if (in_array($slug, ['crm', 'admin.php?page=crm', 'options-general.php?page=crm'], true)) {
                if (!$acf_options_available) {
                    unset($submenu['options-general.php'][$index]);
                    continue;
                }

                $submenu['options-general.php'][$index][0] = 'CRM Integration';
                $submenu['options-general.php'][$index][2] = 'crm';
                if (isset($submenu['options-general.php'][$index][3])) {
                    $submenu['options-general.php'][$index][3] = 'CRM Integration Settings';
                }

                $crm_item = $submenu['options-general.php'][$index];
                unset($submenu['options-general.php'][$index]);
                continue;
            }

            if (!in_array($slug, ['branding', 'admin.php?page=branding', 'options-general.php?page=branding'], true)) {
                continue;
            }

            if (!$acf_options_available) {
                unset($submenu['options-general.php'][$index]);
                continue;
            }

            $submenu['options-general.php'][$index][0] = 'Branding';
            $submenu['options-general.php'][$index][1] = $menu_capability;
            $submenu['options-general.php'][$index][2] = 'branding';
            if (isset($submenu['options-general.php'][$index][3])) {
                $submenu['options-general.php'][$index][3] = 'Branding Settings';
            }

            $branding_item = $submenu['options-general.php'][$index];
            unset($submenu['options-general.php'][$index]);
        }

        if (!$acf_options_available) {
            $submenu['options-general.php'] = array_values($submenu['options-general.php']);
            return;
        }

        if ($business_information_item === null && in_array('business-information', $allowed_expected_item_slugs, true)) {
            $business_information_item = $expected_items['business-information'];
        }

        if ($branding_item === null && in_array('branding', $allowed_expected_item_slugs, true)) {
            $branding_item = $expected_items['branding'];
        }

        if ($crm_item === null && in_array('crm', $allowed_expected_item_slugs, true)) {
            $crm_item = $expected_items['crm'];
        }

        $submenu['options-general.php'] = array_values($submenu['options-general.php']);

        $insert_index = 1;
        foreach ($submenu['options-general.php'] as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $slug = (string) ($item[2] ?? '');
            if ($slug === 'options-writing.php') {
                $insert_index = $index;
                break;
            }
        }

        $items_to_insert = array_values(array_filter([
            $business_information_item,
            $branding_item,
            $crm_item,
        ], 'is_array'));

        if ($items_to_insert === []) {
            return;
        }

        array_splice($submenu['options-general.php'], $insert_index, 0, $items_to_insert);

        if ($is_site_manager) {
            $desired_order = [
                $business_information_menu_label,
                'Branding',
                'Privacy',
                'CRM Integration',
            ];

            usort($submenu['options-general.php'], static function (array $a, array $b) use ($desired_order): int {
                $label_a = trim(wp_strip_all_tags((string) ($a[0] ?? '')));
                $label_b = trim(wp_strip_all_tags((string) ($b[0] ?? '')));
                $index_a = array_search($label_a, $desired_order, true);
                $index_b = array_search($label_b, $desired_order, true);

                $index_a = ($index_a === false) ? PHP_INT_MAX : (int) $index_a;
                $index_b = ($index_b === false) ? PHP_INT_MAX : (int) $index_b;

                if ($index_a === $index_b) {
                    return strnatcasecmp($label_a, $label_b);
                }

                return $index_a <=> $index_b;
            });
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

        #acf-group_meza_business_branding .acf-input > p.description,
        .acf-postbox[data-key="group_meza_business_branding"] .acf-input > p.description {
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
                const hasValue = uploader.classList.contains('has-value')
                    && sourceImage instanceof HTMLImageElement
                    && sourceImage.getAttribute('src');

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
                }, { once: true });
            } else {
                moveDescriptions();
                setupSiteIconDarkPreview();
            }
        })();
    </script>
    <?php
});

add_action('acf/init', function (): void {
    if (!function_exists('acf_add_options_page') || !function_exists('acf_add_local_field_group')) {
        return;
    }

    foreach (meza_get_shared_project_acf_options_pages() as $page) {
        if (!is_array($page)) {
            continue;
        }

        $page_slug = sanitize_key((string) ($page['menu_slug'] ?? ''));
        if ($page_slug === '') {
            continue;
        }

        acf_add_options_page($page);
    }

    foreach (meza_get_shared_project_acf_field_groups() as $group) {
        if (!is_array($group) || empty($group['key'])) {
            continue;
        }

        acf_add_local_field_group($group);
    }
}, 15);
