<?php


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
        ];
    }

    function meza_get_content_structure_fields(): array
    {
        return [
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
                    'conference' => 'Conference',
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

    function meza_get_business_information_locations_fields(): array
    {
        return [
            [
                'key' => 'field_meza_business_locations',
                'label' => 'Locations',
                'name' => 'locations',
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
                'layout' => 'block',
                'pagination' => 0,
                'min' => 0,
                'max' => 0,
                'collapsed' => 'field_meza_business_location_address',
                'button_label' => 'Add Location',
                'rows_per_page' => 20,
                'sub_fields' => [
                    [
                        'key' => 'field_meza_business_location_primary',
                        'label' => 'Primary',
                        'name' => 'primary',
                        'aria-label' => '',
                        'type' => 'true_false',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => 'meza-location-field-row meza-location-field-row-logo meza-location-field-row-start',
                            'id' => '',
                        ],
                        'message' => 'Is this the primary location?',
                        'default_value' => 0,
                        'allow_in_bindings' => 0,
                        'ui' => 0,
                        'ui_on_text' => '',
                        'ui_off_text' => '',
                        'parent_repeater' => 'field_meza_business_locations',
                    ],
                    [
                        'key' => 'field_meza_business_location_address',
                        'label' => 'Address',
                        'name' => 'address',
                        'aria-label' => '',
                        'type' => 'google_map',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => 'meza-location-field-row meza-location-field-row-logo meza-location-field-row-start',
                            'id' => '',
                        ],
                        'center_lat' => '',
                        'center_lng' => '',
                        'zoom' => '',
                        'height' => '',
                        'allow_in_bindings' => 0,
                        'parent_repeater' => 'field_meza_business_locations',
                    ],
                    [
                        'key' => 'field_meza_business_location_phone',
                        'label' => 'Phone',
                        'name' => 'phone',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => 'meza-location-field-row meza-location-field-row-cta meza-location-field-row-start',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'maxlength' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'parent_repeater' => 'field_meza_business_locations',
                    ],
                    [
                        'key' => 'field_meza_business_location_email',
                        'label' => 'Email',
                        'name' => 'email',
                        'aria-label' => '',
                        'type' => 'email',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => 'meza-location-field-row meza-location-field-row-cta meza-location-field-row-end',
                            'id' => '',
                        ],
                        'default_value' => '',
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'parent_repeater' => 'field_meza_business_locations',
                    ],
                    [
                        'key' => 'field_meza_business_location_locality',
                        'label' => 'Localities',
                        'name' => 'locality',
                        'aria-label' => '',
                        'type' => 'taxonomy',
                        'instructions' => '',
                        'required' => 1,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'allow_in_bindings' => 0,
                        'taxonomy' => 'locality',
                        'add_term' => 0,
                        'save_terms' => 0,
                        'load_terms' => 0,
                        'return_format' => 'id',
                        'field_type' => 'multi_select',
                        'allow_null' => 0,
                        'multiple' => 1,
                        'parent_repeater' => 'field_meza_business_locations',
                    ],
                    [
                        'key' => 'field_meza_business_location_hours',
                        'label' => 'Hours',
                        'name' => 'hours',
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
                        'min' => 0,
                        'max' => 0,
                        'default_value' => [
                            [
                                'day' => 'Monday - Friday',
                                'time' => '8:00am - 5:00pm',
                            ],
                            [
                                'day' => 'Saturday - Sunday',
                                'time' => 'Closed',
                            ],
                        ],
                        'rows_per_page' => 20,
                        'layout' => 'table',
                        'button_label' => 'Add Hours',
                        'collapsed' => '',
                        'sub_fields' => [
                            [
                                'key' => 'field_meza_business_location_hours_day',
                                'label' => 'Day',
                                'name' => 'day',
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
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                                'parent_repeater' => 'field_meza_business_location_hours',
                            ],
                            [
                                'key' => 'field_meza_business_location_hours_time',
                                'label' => 'Time',
                                'name' => 'time',
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
                                'placeholder' => '',
                                'prepend' => '',
                                'append' => '',
                                'parent_repeater' => 'field_meza_business_location_hours',
                            ],
                        ],
                        'parent_repeater' => 'field_meza_business_locations',
                    ],
                    [
                        'key' => 'field_meza_business_location_note',
                        'label' => 'Note',
                        'name' => 'note',
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
                        'allow_in_bindings' => 1,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'parent_repeater' => 'field_meza_business_locations',
                    ],
                    [
                        'key' => 'field_meza_business_location_secondary_logo',
                        'label' => 'Logo',
                        'name' => 'secondary_logo',
                        'aria-label' => '',
                        'type' => 'image',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => 'meza-location-field-row meza-location-field-row-logo meza-location-field-row-start',
                            'id' => '',
                        ],
                        'return_format' => 'id',
                        'library' => 'all',
                        'preview_size' => 'medium',
                        'allow_in_bindings' => 0,
                        'parent_repeater' => 'field_meza_business_locations',
                    ],
                    [
                        'key' => 'field_meza_business_location_use_site_logo',
                        'label' => 'Logo',
                        'name' => 'use_site_logo',
                        'aria-label' => '',
                        'type' => 'true_false',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => 'meza-location-field-row meza-location-field-row-logo meza-location-field-row-end',
                            'id' => '',
                        ],
                        'message' => 'Use Site Logo',
                        'default_value' => 1,
                        'allow_in_bindings' => 0,
                        'ui' => 0,
                        'ui_on_text' => '',
                        'ui_off_text' => '',
                        'parent_repeater' => 'field_meza_business_locations',
                    ],
                    [
                        'key' => 'field_meza_business_location_cta',
                        'label' => 'CTA',
                        'name' => 'cta',
                        'aria-label' => '',
                        'type' => 'relationship',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => 'meza-location-field-row meza-location-field-row-cta meza-location-field-row-start',
                            'id' => '',
                        ],
                        'post_type' => [
                            'cta',
                        ],
                        'post_status' => [
                            'publish',
                        ],
                        'taxonomy' => '',
                        'filters' => [
                            'search',
                        ],
                        'return_format' => 'id',
                        'min' => '',
                        'max' => 1,
                        'allow_in_bindings' => 0,
                        'elements' => '',
                        'bidirectional' => 0,
                        'bidirectional_target' => [],
                        'parent_repeater' => 'field_meza_business_locations',
                    ],
                    [
                        'key' => 'field_meza_business_location_global_sticky_cta',
                        'label' => '',
                        'name' => 'global_sticky_cta',
                        'aria-label' => '',
                        'type' => 'true_false',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_meza_business_location_cta',
                                    'operator' => '!=empty',
                                ],
                            ],
                        ],
                        'wrapper' => [
                            'width' => '',
                            'class' => 'meza-location-field-row meza-location-field-row-cta meza-location-field-row-end',
                            'id' => '',
                        ],
                        'message' => 'Use as global sticky CTA',
                        'default_value' => 0,
                        'allow_in_bindings' => 0,
                        'ui' => 0,
                        'ui_on_text' => '',
                        'ui_off_text' => '',
                        'parent_repeater' => 'field_meza_business_locations',
                    ],
                ],
            ],
        ];
    }

    function meza_get_business_information_ecommerce_fields(): array
    {
        return [
            [
                'key' => 'field_meza_business_woocommerce',
                'label' => 'WooCommerce',
                'name' => 'woocommerce',
                'aria-label' => '',
                'type' => 'true_false',
                'instructions' => 'Enabling installs WooCommerce and creates product content fields and taxonomies for content authors.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'message' => 'Enable e-commerce functionality on this site?',
                'default_value' => 0,
                'allow_in_bindings' => 0,
                'ui' => 0,
                'ui_on_text' => '',
                'ui_off_text' => '',
            ],
            [
                'key' => 'field_meza_business_product_indexing',
                'label' => 'Product Indexing',
                'name' => 'product_indexing',
                'aria-label' => '',
                'type' => 'true_false',
                'instructions' => 'Enabling allows search engine to find, index, and serve up product content to users.',
                'required' => 0,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_meza_business_woocommerce',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'message' => 'Allow product pages to be indexed?',
                'default_value' => 0,
                'allow_in_bindings' => 0,
                'ui' => 0,
                'ui_on_text' => '',
                'ui_off_text' => '',
            ],
            [
                'key' => 'field_meza_business_store',
                'label' => 'Store',
                'name' => 'store',
                'aria-label' => '',
                'type' => 'true_false',
                'instructions' => 'Enabling activates Store functionality for production content including cart, guest checkout, and payment processing functionaliity.',
                'required' => 0,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_meza_business_product_indexing',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'message' => 'Enable store features?',
                'default_value' => 0,
                'allow_in_bindings' => 0,
                'ui' => 0,
                'ui_on_text' => '',
                'ui_off_text' => '',
            ],
            [
                'key' => 'field_meza_business_accounts',
                'label' => 'Accounts',
                'name' => 'accounts',
                'aria-label' => '',
                'type' => 'true_false',
                'instructions' => 'Enabling allows users to create accounts and log in during checkout.',
                'required' => 0,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_meza_business_store',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'message' => 'Allow user logins?',
                'default_value' => 0,
                'allow_in_bindings' => 0,
                'ui' => 0,
                'ui_on_text' => '',
                'ui_off_text' => '',
            ],
        ];
    }

    function meza_get_contact_locations_section_fields(): array
    {
        return [
            [
                'key' => 'field_meza_contact_locations_visibility',
                'label' => 'Visibility',
                'name' => 'show_list-locations',
                'aria-label' => '',
                'type' => 'true_false',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'message' => '',
                'default_value' => 0,
                'allow_in_bindings' => 0,
                'ui' => 0,
                'ui_on_text' => '',
                'ui_off_text' => '',
            ],
            [
                'key' => 'field_meza_contact_locations_section_group',
                'label' => 'Section',
                'name' => 'section_list-locations',
                'aria-label' => '',
                'type' => 'group',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_meza_contact_locations_visibility',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'key' => 'field_meza_contact_locations_headline',
                        'label' => 'Headline (H2)',
                        'name' => 'headline',
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
                        'default_value' => 'localities',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ],
                    [
                        'key' => 'field_meza_contact_locations_subhead',
                        'label' => 'Subhead',
                        'name' => 'subhead',
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
                    ],
                    [
                        'key' => 'field_meza_contact_locations_id',
                        'label' => 'ID',
                        'name' => 'id',
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
                        'default_value' => 'localities',
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

    function meza_get_contact_locations_section_field_group_definition(): array
    {
        return [
            'key' => 'group_meza_contact_locations_section',
            'title' => 'List Locations Section',
            'fields' => meza_get_contact_locations_section_fields(),
            'location' => [
                [
                    [
                        'param' => 'page',
                        'operator' => '==',
                        'value' => (string) meza_get_contact_page_id_for_acf_rules(),
                    ],
                ],
                [
                    [
                        'param' => 'page_type',
                        'operator' => '==',
                        'value' => 'front_page',
                    ],
                ],
                [
                    [
                        'param' => 'page_template',
                        'operator' => '==',
                        'value' => 'page-form.php',
                    ],
                ],
            ],
            'menu_order' => 18,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => '',
            'show_in_rest' => 0,
            'display_title' => '',
        ];
    }

    function meza_get_business_information_contact_fields(): array
    {
        return [
            [
                'key' => 'field_meza_business_phone_group',
                'label' => 'Phone',
                'name' => 'phone_group',
                'aria-label' => '',
                'type' => 'group',
                'instructions' => 'Used in the footer of emails sent through the website.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'key' => 'field_69b5a28923bbe',
                        'label' => '',
                        'name' => 'phone',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_meza_business_use_primary_location_phone',
                                    'operator' => '!=',
                                    'value' => '1',
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
                    [
                        'key' => 'field_meza_business_use_primary_location_phone',
                        'label' => '',
                        'name' => 'use_primary_location_phone',
                        'aria-label' => '',
                        'type' => 'true_false',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'message' => 'Use primary location\'s phone',
                        'default_value' => 0,
                        'allow_in_bindings' => 0,
                        'ui' => 0,
                        'ui_on_text' => '',
                        'ui_off_text' => '',
                    ],
                ],
            ],
            [
                'key' => 'field_meza_business_email_group',
                'label' => 'Email',
                'name' => 'email_group',
                'aria-label' => '',
                'type' => 'group',
                'instructions' => 'The default recipient for forms. Also used in the footer of emails sent through the website.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'key' => 'field_68c133a0425a8',
                        'label' => '',
                        'name' => 'email',
                        'aria-label' => '',
                        'type' => 'email',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_meza_business_use_primary_location_email',
                                    'operator' => '!=',
                                    'value' => '1',
                                ],
                            ],
                        ],
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
                        'key' => 'field_meza_business_use_primary_location_email',
                        'label' => '',
                        'name' => 'use_primary_location_email',
                        'aria-label' => '',
                        'type' => 'true_false',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'message' => 'Use primary location\'s email',
                        'default_value' => 0,
                        'allow_in_bindings' => 0,
                        'ui' => 0,
                        'ui_on_text' => '',
                        'ui_off_text' => '',
                    ],
                ],
            ],
            [
                'key' => 'field_meza_business_address_group',
                'label' => 'Mailing Address',
                'name' => 'address_group',
                'aria-label' => '',
                'type' => 'group',
                'instructions' => 'Used in the footer of emails sent through the website. Only put an address in this field if visitors can come to a real location.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'key' => 'field_69b5a2b623bc0',
                        'label' => '',
                        'name' => 'address',
                        'aria-label' => '',
                        'type' => 'google_map',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_meza_business_use_primary_location_address',
                                    'operator' => '!=',
                                    'value' => '1',
                                ],
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
                        'key' => 'field_meza_business_use_primary_location_address',
                        'label' => '',
                        'name' => 'use_primary_location_address',
                        'aria-label' => '',
                        'type' => 'true_false',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'message' => 'Use primary location\'s address',
                        'default_value' => 0,
                        'allow_in_bindings' => 0,
                        'ui' => 0,
                        'ui_on_text' => '',
                        'ui_off_text' => '',
                    ],
                ],
            ],
            [
                'key' => 'field_meza_business_mailing_address',
                'label' => 'Mailing Address',
                'name' => 'address_text',
                'aria-label' => '',
                'type' => 'textarea',
                'instructions' => 'Used in the footer of emails sent through the website. Only put a mailing-only address in this field, such as a P.O. Box.',
                'required' => 0,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_meza_business_use_primary_location_address',
                            'operator' => '!=',
                            'value' => '1',
                        ],
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
        ];
    }

    function meza_get_business_information_locations_option_rows(): array
    {
        if (
            is_admin()
            && isset($_POST['acf']['field_meza_business_locations'])
            && is_array($_POST['acf']['field_meza_business_locations'])
        ) {
            return array_values(array_filter(
                wp_unslash($_POST['acf']['field_meza_business_locations']),
                'is_array'
            ));
        }

        if (!function_exists('get_field')) {
            return meza_get_business_information_locations_rows_from_raw_options();
        }

        $rows = get_field('locations', 'option');

        if (is_array($rows)) {
            return array_values(array_filter($rows, 'is_array'));
        }

        return meza_get_business_information_locations_rows_from_raw_options();
    }

    function meza_get_business_information_locations_rows_from_raw_options(): array
    {
        $row_count = (int) get_option('options_locations', 0);
        if ($row_count <= 0) {
            return [];
        }

        $rows = [];

        for ($index = 0; $index < $row_count; $index++) {
            $row = [
                'locality' => get_option("options_locations_{$index}_locality", ''),
                'name' => (string) get_option("options_locations_{$index}_name", ''),
                'primary' => get_option("options_locations_{$index}_primary", ''),
                'phone' => (string) get_option("options_locations_{$index}_phone", ''),
                'email' => (string) get_option("options_locations_{$index}_email", ''),
                'address' => get_option("options_locations_{$index}_address", null),
                'note' => (string) get_option("options_locations_{$index}_note", ''),
                'use_site_logo' => get_option("options_locations_{$index}_use_site_logo", 1),
                'secondary_logo' => get_option("options_locations_{$index}_secondary_logo", 0),
                'cta' => get_option("options_locations_{$index}_cta", ''),
                'global_sticky_cta' => get_option("options_locations_{$index}_global_sticky_cta", 0),
            ];

            $hours = [];
            $hours_count = (int) get_option("options_locations_{$index}_hours", 0);

            for ($hour_index = 0; $hour_index < $hours_count; $hour_index++) {
                $hours[] = [
                    'day' => (string) get_option("options_locations_{$index}_hours_{$hour_index}_day", ''),
                    'time' => (string) get_option("options_locations_{$index}_hours_{$hour_index}_time", ''),
                ];
            }

            if (!empty($hours)) {
                $row['hours'] = $hours;
            }

            $rows[] = $row;
        }

        return array_values(array_filter($rows, static function (array $row): bool {
            if (!empty($row['locality'])) {
                return true;
            }

            foreach (['name', 'phone', 'email', 'note'] as $key) {
                if (trim((string) ($row[$key] ?? '')) !== '') {
                    return true;
                }
            }

            if (!empty($row['primary'])) {
                return true;
            }

            if (meza_acf_google_map_has_meaningful_value($row['address'] ?? null)) {
                return true;
            }

            return !empty($row['hours']);
        }));
    }

    function meza_should_register_contact_locations_section_group(): bool
    {
        return (int) get_option('options_locations', 0) > 1
            && meza_get_contact_page_id_for_acf_rules() > 0;
    }

    function meza_count_primary_business_information_locations($rows): int
    {
        if (!is_array($rows)) {
            return 0;
        }

        $primary_count = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (!empty($row['primary'])) {
                $primary_count++;
            }
        }

        return $primary_count;
    }

    function meza_business_information_has_primary_location(?array $rows = null): bool
    {
        if ($rows === null) {
            $rows = meza_get_business_information_locations_option_rows();
        }

        return meza_count_primary_business_information_locations($rows) > 0;
    }

    function meza_get_primary_business_information_location(?array $rows = null): array
    {
        if ($rows === null) {
            $rows = meza_get_business_information_locations_option_rows();
        }

        if (!is_array($rows)) {
            return [];
        }

        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['primary'])) {
                continue;
            }

            return $row;
        }

        return [];
    }

    function meza_get_business_information_contact_group(string $group_name): array
    {
        if (!function_exists('get_field')) {
            return [];
        }

        $value = get_field($group_name, 'option');

        return is_array($value) ? $value : [];
    }

    function meza_get_business_information_contact_field_value(string $field_name, string $group_name, string $legacy_name): string
    {
        if (!function_exists('get_field')) {
            return '';
        }

        $group = meza_get_business_information_contact_group($group_name);
        if (array_key_exists($field_name, $group)) {
            return is_scalar($group[$field_name]) ? trim((string) $group[$field_name]) : '';
        }

        return trim((string) get_field($legacy_name, 'option'));
    }

    function meza_business_information_uses_primary_location_field(string $field_name): bool
    {
        if (!function_exists('get_field')) {
            return false;
        }

        $group_map = [
            'phone' => ['group' => 'phone_group', 'toggle' => 'use_primary_location_phone'],
            'email' => ['group' => 'email_group', 'toggle' => 'use_primary_location_email'],
            'address' => ['group' => 'address_group', 'toggle' => 'use_primary_location_address'],
        ];

        $config = $group_map[$field_name] ?? null;
        if (!is_array($config)) {
            return false;
        }

        $group = meza_get_business_information_contact_group((string) $config['group']);
        if (array_key_exists((string) $config['toggle'], $group)) {
            return !empty($group[(string) $config['toggle']]);
        }

        return !empty(get_field((string) $config['toggle'], 'option'));
    }

    function meza_get_primary_business_information_location_field_value(string $field_name, ?array $rows = null)
    {
        $primary_location = meza_get_primary_business_information_location($rows);
        if (empty($primary_location)) {
            return null;
        }

        $value = $primary_location[$field_name] ?? null;

        if ($field_name === 'address') {
            return meza_acf_google_map_has_meaningful_value($value) ? $value : null;
        }

        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value !== '' ? $value : null;
    }

    function meza_get_business_information_phone(): string
    {
        if (meza_business_information_uses_primary_location_field('phone')) {
            $primary_phone = meza_get_primary_business_information_location_field_value('phone');
            return is_string($primary_phone) ? $primary_phone : '';
        }

        return meza_get_business_information_contact_field_value('phone', 'phone_group', 'phone');
    }

    function meza_get_business_information_email(): string
    {
        if (meza_business_information_uses_primary_location_field('email')) {
            $primary_email = meza_get_primary_business_information_location_field_value('email');
            return is_string($primary_email) ? $primary_email : '';
        }

        return meza_get_business_information_contact_field_value('email', 'email_group', 'email');
    }

    function meza_get_business_information_address(): array
    {
        if (!function_exists('get_field')) {
            return ['raw' => null, 'text' => ''];
        }

        if (meza_business_information_uses_primary_location_field('address')) {
            return [
                'raw' => meza_get_primary_business_information_location_field_value('address'),
                'text' => '',
            ];
        }

        $group = meza_get_business_information_contact_group('address_group');
        $group_raw = $group['address'] ?? null;

        return [
            'raw' => meza_acf_google_map_has_meaningful_value($group_raw) ? $group_raw : get_field('address', 'option'),
            'text' => trim((string) get_field('address_text', 'option')),
        ];
    }

    function meza_validate_business_information_primary_location_dependency($valid, string $field_name, $value)
    {
        return $valid;
    }

    function meza_validate_business_information_email_group($valid, $value, array $field = [])
    {
        $posted_group = meza_get_posted_acf_field_value((string) ($field['key'] ?? ''));
        $group = is_array($posted_group) ? $posted_group : (is_array($value) ? $value : []);
        $use_primary = !empty(meza_get_posted_acf_group_subfield_value(
            $group,
            'field_meza_business_use_primary_location_email',
            'use_primary_location_email'
        ));
        $email = sanitize_email((string) meza_get_posted_acf_group_subfield_value(
            $group,
            'field_68c133a0425a8',
            'email'
        ));

        if ($use_primary) {
            return true;
        }

        return is_email($email) ? true : 'value is required';
    }

    function meza_validate_business_information_address_group($valid, $value, array $field = [])
    {
        $posted_group = meza_get_posted_acf_field_value((string) ($field['key'] ?? ''));
        $group = is_array($posted_group) ? $posted_group : (is_array($value) ? $value : []);
        $use_primary = !empty(meza_get_posted_acf_group_subfield_value(
            $group,
            'field_meza_business_use_primary_location_address',
            'use_primary_location_address'
        ));
        $address = meza_get_posted_acf_group_subfield_value(
            $group,
            'field_69b5a2b623bc0',
            'address'
        );
        $address_text = trim((string) meza_get_posted_acf_field_value('field_meza_business_mailing_address'));
        if ($address_text === '' && function_exists('get_field')) {
            $address_text = trim((string) get_field('address_text', 'option'));
        }

        if ($use_primary) {
            return true;
        }

        if (meza_acf_google_map_has_meaningful_value($address) || $address_text !== '') {
            return true;
        }

        return 'value is required';
    }

    function meza_is_business_information_acf_submission(): bool
    {
        if (
            isset($_POST['acf'])
            && is_array($_POST['acf'])
            && (
                array_key_exists('field_meza_business_email_group', $_POST['acf'])
                || array_key_exists('field_meza_business_address_group', $_POST['acf'])
                || array_key_exists('field_meza_business_mailing_address', $_POST['acf'])
            )
        ) {
            return true;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';

        return $page === 'business-information';
    }

    function meza_get_posted_acf_field_value(string $field_key)
    {
        if (!isset($_POST['acf']) || !is_array($_POST['acf']) || !array_key_exists($field_key, $_POST['acf'])) {
            return null;
        }

        return wp_unslash($_POST['acf'][$field_key]);
    }

    function meza_get_posted_acf_group_subfield_value($group_value, string $field_key, string $field_name = '')
    {
        if (!is_array($group_value)) {
            return null;
        }

        if (array_key_exists($field_key, $group_value)) {
            return $group_value[$field_key];
        }

        if ($field_name !== '' && array_key_exists($field_name, $group_value)) {
            return $group_value[$field_name];
        }

        return null;
    }
