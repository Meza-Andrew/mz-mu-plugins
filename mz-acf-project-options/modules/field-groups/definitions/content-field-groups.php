<?php

    function meza_get_service_field_group_definition(): array
    {
        return [
            'key' => 'group_68de19d78cbcb',
            'title' => 'Service',
            'fields' => [
                [
                    'key' => 'field_68de19d72892a',
                    'label' => 'Office',
                    'name' => 'office',
                    'aria-label' => '',
                    'type' => 'relationship',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'post_type' => [
                        'office',
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
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'service',
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
            'allow_ai_access' => false,
            'ai_description' => '',
        ];
    }

    function meza_get_cta_field_group_definition(): array
    {
        return [
            'key' => 'group_697ff4f7482e6',
            'title' => 'CTA',
            'fields' => [
                [
                    'key' => 'field_69b07b3a68600',
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
                ],
                [
                    'key' => 'field_697ff4f7c4120',
                    'label' => 'Link',
                    'name' => 'link',
                    'aria-label' => '',
                    'type' => 'link',
                    'instructions' => '',
                    'required' => 1,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'return_format' => 'array',
                    'allow_in_bindings' => 0,
                ],
                [
                    'key' => 'field_697ff50dc4122',
                    'label' => 'Link (Secondary)',
                    'name' => 'link_secondary',
                    'aria-label' => '',
                    'type' => 'link',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_697ff4f7c4120',
                                'operator' => '!=empty',
                            ],
                        ],
                    ],
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'return_format' => 'array',
                    'allow_in_bindings' => 0,
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'cta',
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
            'allow_ai_access' => false,
            'ai_description' => '',
        ];
    }

    function meza_profile_uses_organization_field(): bool
    {
        return meza_is_conference_business_type();
    }

    function meza_review_uses_profile_citer(): bool
    {
        return meza_is_conference_business_type();
    }

    function meza_get_profile_field_group_definition(): array
    {
        $fields = [
            [
                'key' => 'field_6902a3bf958b8',
                'label' => 'Title',
                'name' => 'title',
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
        ];

        if (meza_profile_uses_organization_field()) {
            $fields[] = [
                'key' => 'field_6902c3cbe169b',
                'label' => 'Organization',
                'name' => 'organization',
                'aria-label' => '',
                'type' => 'relationship',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'post_type' => [
                    'organization',
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
                'elements' => [
                    'featured_image',
                ],
                'bidirectional' => 0,
                'bidirectional_target' => [],
            ];
        }

        $fields[] = [
            'key' => 'field_68bb85c35a8e2',
            'label' => 'URL',
            'name' => 'url',
            'aria-label' => '',
            'type' => 'link',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => meza_profile_uses_organization_field()
                ? [
                    [
                        [
                            'field' => 'field_6902c3cbe169b',
                            'operator' => '==empty',
                        ],
                    ],
                ]
                : 0,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => '',
            ],
            'return_format' => 'url',
            'allow_in_bindings' => 0,
        ];

        return [
            'key' => 'group_68bb85c355957',
            'title' => 'Profile',
            'fields' => $fields,
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'profile',
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
            'allow_ai_access' => false,
            'ai_description' => '',
        ];
    }

    function meza_get_organization_field_group_definition(): array
    {
        return [
            'key' => 'group_69b07c3129ab0',
            'title' => 'Organization',
            'fields' => [
                [
                    'key' => 'field_69b07c31cf5f1',
                    'label' => 'URL',
                    'name' => 'url',
                    'aria-label' => '',
                    'type' => 'url',
                    'instructions' => '',
                    'required' => 1,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'default_value' => '',
                    'allow_in_bindings' => 1,
                    'placeholder' => '',
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'organization',
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
            'allow_ai_access' => false,
            'ai_description' => '',
        ];
    }

    function meza_get_faq_field_group_definition(): array
    {
        return [
            'key' => 'group_681255c43a8a1',
            'title' => 'FAQ',
            'fields' => [
                [
                    'key' => 'field_681255cc3a8a2',
                    'label' => 'FAQs',
                    'name' => 'faqs',
                    'aria-label' => '',
                    'type' => 'repeater',
                    'instructions' => '',
                    'required' => 1,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'layout' => 'block',
                    'pagination' => 0,
                    'min' => 1,
                    'max' => 0,
                    'collapsed' => 'field_681255d93a8a3',
                    'button_label' => 'Add FAQ',
                    'rows_per_page' => 20,
                    'sub_fields' => [
                        [
                            'key' => 'field_681255d93a8a3',
                            'label' => 'Question',
                            'name' => 'question',
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
                            'parent_repeater' => 'field_681255cc3a8a2',
                        ],
                        [
                            'key' => 'field_681255e53a8a4',
                            'label' => 'Answer',
                            'name' => 'answer',
                            'aria-label' => '',
                            'type' => 'wysiwyg',
                            'instructions' => '',
                            'required' => 1,
                            'conditional_logic' => 0,
                            'wrapper' => [
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ],
                            'default_value' => '',
                            'allow_in_bindings' => 0,
                            'tabs' => 'all',
                            'toolbar' => 'basic',
                            'media_upload' => 0,
                            'delay' => 0,
                            'parent_repeater' => 'field_681255cc3a8a2',
                        ],
                    ],
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'faq',
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
            'allow_ai_access' => false,
            'ai_description' => '',
        ];
    }

    function meza_get_review_field_group_definition(): array
    {
        $citer_field = meza_review_uses_profile_citer()
            ? [
                'key' => 'field_69f05fb0b9f36',
                'label' => 'Citer',
                'name' => 'citer',
                'aria-label' => '',
                'type' => 'relationship',
                'instructions' => '',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'post_type' => [
                    'profile',
                ],
                'post_status' => [
                    'publish',
                ],
                'taxonomy' => '',
                'filters' => [
                    'search',
                ],
                'return_format' => 'object',
                'min' => 1,
                'max' => 1,
                'allow_in_bindings' => 0,
                'elements' => [
                    'featured_image',
                ],
                'bidirectional' => 0,
                'bidirectional_target' => [],
            ]
            : [
                'key' => 'field_68f28d2cd3c79',
                'label' => 'Citer',
                'name' => 'citer',
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
            ];

        return [
            'key' => 'group_68f28d05a8e4b',
            'title' => 'Review',
            'fields' => [
                [
                    'key' => 'field_68f28d05d3c78',
                    'label' => 'Quote',
                    'name' => 'quote',
                    'aria-label' => '',
                    'type' => 'textarea',
                    'instructions' => 'Limited to 150 characters.',
                    'required' => 1,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'default_value' => '',
                    'maxlength' => 200,
                    'allow_in_bindings' => 0,
                    'rows' => 2,
                    'placeholder' => '',
                    'new_lines' => '',
                ],
                $citer_field,
                [
                    'key' => 'field_697ff1f193b2a',
                    'label' => 'URL',
                    'name' => 'url',
                    'aria-label' => '',
                    'type' => 'link',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'return_format' => 'url',
                    'allow_in_bindings' => 0,
                ],
                [
                    'key' => 'field_697ff1b893b29',
                    'label' => 'Date',
                    'name' => 'date',
                    'aria-label' => '',
                    'type' => 'date_picker',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'display_format' => 'F j, Y',
                    'return_format' => 'Ymd',
                    'first_day' => 1,
                    'default_to_current_date' => 1,
                    'allow_in_bindings' => 0,
                ],
                [
                    'key' => 'field_6811ca58eb4cb',
                    'label' => 'Featured',
                    'name' => 'featured',
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
                    'ui_on_text' => '',
                    'ui_off_text' => '',
                    'ui' => 1,
                    'allow_in_bindings' => 0,
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'review',
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
            'allow_ai_access' => false,
            'ai_description' => '',
        ];
    }

