<?php

/**
 * Arsenal Events Media Library ACF Field Groups
 * Configures ACF image fields to use WordPress media library
 * All fields use strict snake_case naming convention
 */

/**
 * RD Hero Media Field Group
 * Replaces static image paths with WordPress media library integration
 */
function arsenal_events_get_rd_hero_media_group(): array
{
    return [
        'key' => 'group_rd_hero_media',
        'title' => 'RD Hero - Media Library',
        'fields' => [
            [
                'key' => 'field_rd_hero_image_media',
                'label' => 'Hero Image',
                'name' => 'rd_hero_image',
                'type' => 'image',
                'instructions' => 'Upload or select hero image from media library',
                'required' => 0,
                'return_format' => 'array',
                'preview_size' => 'medium',
                'library' => 'all',
                'min_width' => 1200,
                'min_height' => 600,
                'min_size' => '',
                'max_size' => '',
                'mime_types' => 'jpg,jpeg,png,gif,webp',
                'wrapper' => ['width' => '100'],
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'options_page',
                    'operator' => '==',
                    'value' => 'arsenal-events-media',
                ],
            ],
        ],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'description' => 'Media library integration for RD Hero section',
        'show_in_rest' => 1,
    ];
}

/**
 * RD Services Media Field Group
 * Configures service images to use WordPress media library
 */
function arsenal_events_get_rd_services_media_group(): array
{
    return [
        'key' => 'group_rd_services_media',
        'title' => 'RD Services - Media Library',
        'fields' => [
            [
                'key' => 'field_rd_services_items_media',
                'label' => 'Services',
                'name' => 'rd_services_items',
                'type' => 'repeater',
                'instructions' => 'Add service items with media library images',
                'layout' => 'block',
                'button_label' => 'Add Service',
                'sub_fields' => [
                    [
                        'key' => 'field_rd_service_title_media',
                        'label' => 'Title',
                        'name' => 'rd_service_title',
                        'type' => 'text',
                        'required' => 0,
                        'wrapper' => ['width' => '50'],
                    ],
                    [
                        'key' => 'field_rd_service_description_media',
                        'label' => 'Description',
                        'name' => 'rd_service_description',
                        'type' => 'textarea',
                        'required' => 0,
                        'rows' => 3,
                        'wrapper' => ['width' => '50'],
                    ],
                    [
                        'key' => 'field_rd_service_image_media',
                        'label' => 'Service Image',
                        'name' => 'rd_service_image',
                        'type' => 'image',
                        'instructions' => 'Upload or select service image from media library',
                        'required' => 0,
                        'return_format' => 'array',
                        'preview_size' => 'thumbnail',
                        'library' => 'all',
                        'min_width' => 400,
                        'min_height' => 300,
                        'mime_types' => 'jpg,jpeg,png,gif,webp',
                        'wrapper' => ['width' => '100'],
                    ],
                ],
                'wrapper' => ['width' => '100'],
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'options_page',
                    'operator' => '==',
                    'value' => 'arsenal-events-media',
                ],
            ],
        ],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'description' => 'Media library integration for RD Services section',
        'show_in_rest' => 1,
    ];
}

/**
 * RD Gallery Media Field Group
 * Configures gallery images to use WordPress media library
 */
function arsenal_events_get_rd_gallery_media_group(): array
{
    return [
        'key' => 'group_rd_gallery_media',
        'title' => 'RD Gallery - Media Library',
        'fields' => [
            [
                'key' => 'field_rd_gallery_items_media',
                'label' => 'Gallery Items',
                'name' => 'rd_gallery_items',
                'type' => 'repeater',
                'instructions' => 'Add gallery items with media library images',
                'layout' => 'block',
                'button_label' => 'Add Gallery Item',
                'sub_fields' => [
                    [
                        'key' => 'field_rd_gallery_title_media',
                        'label' => 'Title',
                        'name' => 'rd_gallery_title',
                        'type' => 'text',
                        'required' => 0,
                        'wrapper' => ['width' => '50'],
                    ],
                    [
                        'key' => 'field_rd_gallery_image_media',
                        'label' => 'Gallery Image',
                        'name' => 'rd_gallery_image',
                        'type' => 'image',
                        'instructions' => 'Upload or select gallery image from media library',
                        'required' => 0,
                        'return_format' => 'array',
                        'preview_size' => 'thumbnail',
                        'library' => 'all',
                        'min_width' => 600,
                        'min_height' => 400,
                        'mime_types' => 'jpg,jpeg,png,gif,webp',
                        'wrapper' => ['width' => '50'],
                    ],
                ],
                'wrapper' => ['width' => '100'],
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'options_page',
                    'operator' => '==',
                    'value' => 'arsenal-events-media',
                ],
            ],
        ],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'description' => 'Media library integration for RD Gallery section',
        'show_in_rest' => 1,
    ];
}

/**
 * RR Hero Media Field Group
 * Configures Races & Results hero image to use WordPress media library
 */
function arsenal_events_get_rr_hero_media_group(): array
{
    return [
        'key' => 'group_rr_hero_media',
        'title' => 'RR Hero - Media Library',
        'fields' => [
            [
                'key' => 'field_rr_hero_image_media',
                'label' => 'Hero Image',
                'name' => 'rr_hero_image',
                'type' => 'image',
                'instructions' => 'Upload or select hero image from media library',
                'required' => 0,
                'return_format' => 'array',
                'preview_size' => 'medium',
                'library' => 'all',
                'min_width' => 1200,
                'min_height' => 600,
                'min_size' => '',
                'max_size' => '',
                'mime_types' => 'jpg,jpeg,png,gif,webp',
                'wrapper' => ['width' => '100'],
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'options_page',
                    'operator' => '==',
                    'value' => 'arsenal-events-media',
                ],
            ],
        ],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'description' => 'Media library integration for RR Hero section',
        'show_in_rest' => 1,
    ];
}

/**
 * Register all media field groups
 */
function arsenal_events_register_media_field_groups()
{
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group(arsenal_events_get_rd_hero_media_group());
        acf_add_local_field_group(arsenal_events_get_rd_services_media_group());
        acf_add_local_field_group(arsenal_events_get_rd_gallery_media_group());
        acf_add_local_field_group(arsenal_events_get_rr_hero_media_group());
    }
}

add_action('acf/init', 'arsenal_events_register_media_field_groups');

/**
 * Register options pages for media field groups
 */
function arsenal_events_register_media_options_pages()
{
    if (function_exists('acf_add_options_page')) {
        // Unified Media Library options page
        acf_add_options_page([
            'page_title' => 'Media Library',
            'menu_title' => 'Media Library',
            'menu_slug'  => 'arsenal-events-media',
            'capability' => 'manage_options',
            'redirect'   => false,
            'parent_slug' => 'arsenal-events-settings',
        ]);
    }
}
add_action('acf/init', 'arsenal_events_register_media_options_pages');
