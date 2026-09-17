<?php

    function meza_get_section_field_wrapper(): array
    {
        return [
            'width' => '',
            'class' => '',
            'id' => '',
        ];
    }

    function meza_get_standard_list_section_visibility_field(string $key, string $name, int $allow_in_bindings = 0): array
    {
        return [
            'key' => $key,
            'label' => 'Visibility',
            'name' => $name,
            'aria-label' => '',
            'type' => 'true_false',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => meza_get_section_field_wrapper(),
            'message' => '',
            'default_value' => 0,
            'allow_in_bindings' => $allow_in_bindings,
            'ui' => 0,
            'ui_on_text' => '',
            'ui_off_text' => '',
        ];
    }

    function meza_get_standard_list_section_text_field(
        string $key,
        string $label,
        string $name,
        string $default_value = '',
        int $required = 0,
        int $allow_in_bindings = 0
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'name' => $name,
            'aria-label' => '',
            'type' => 'text',
            'instructions' => '',
            'required' => $required,
            'conditional_logic' => 0,
            'wrapper' => meza_get_section_field_wrapper(),
            'default_value' => $default_value,
            'maxlength' => '',
            'allow_in_bindings' => $allow_in_bindings,
            'placeholder' => '',
            'prepend' => '',
            'append' => '',
        ];
    }

    function meza_get_standard_list_section_description_field(string $key, int $rows = 2): array
    {
        return [
            'key' => $key,
            'label' => 'Description',
            'name' => 'description',
            'aria-label' => '',
            'type' => 'textarea',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => meza_get_section_field_wrapper(),
            'default_value' => '',
            'maxlength' => '',
            'allow_in_bindings' => 0,
            'rows' => $rows,
            'placeholder' => '',
            'new_lines' => 'wpautop',
        ];
    }

    function meza_get_standard_list_section_display_field(string $key): array
    {
        return [
            'key' => $key,
            'label' => 'Display',
            'name' => 'display',
            'aria-label' => '',
            'type' => 'text',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => meza_get_section_field_wrapper(),
            'default_value' => '',
            'maxlength' => '',
            'allow_in_bindings' => 0,
            'placeholder' => '',
            'prepend' => '',
            'append' => '',
        ];
    }

    function meza_get_standard_list_section_subhead_field(string $key, string $default_value = ''): array
    {
        return [
            'key' => $key,
            'label' => 'Subhead',
            'name' => 'subhead',
            'aria-label' => '',
            'type' => 'textarea',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => meza_get_section_field_wrapper(),
            'default_value' => $default_value,
            'maxlength' => '',
            'allow_in_bindings' => 0,
            'rows' => 1,
            'placeholder' => '',
            'new_lines' => '',
            'meza_textarea_role' => 'subhead',
        ];
    }

    function meza_get_standard_list_section_link_field(string $key): array
    {
        return [
            'key' => $key,
            'label' => 'Link',
            'name' => 'link',
            'aria-label' => '',
            'type' => 'link',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => meza_get_section_field_wrapper(),
            'return_format' => 'array',
            'allow_in_bindings' => 0,
        ];
    }

    function meza_get_standard_list_section_link_secondary_field(string $key, string $primary_link_key): array
    {
        return [
            'key' => $key,
            'label' => 'Link (Secondary)',
            'name' => 'link_secondary',
            'aria-label' => '',
            'type' => 'link',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => [
                [
                    [
                        'field' => $primary_link_key,
                        'operator' => '!=empty',
                    ],
                ],
            ],
            'wrapper' => meza_get_section_field_wrapper(),
            'return_format' => 'array',
            'allow_in_bindings' => 0,
        ];
    }

    function meza_get_standard_section_header_field_names(string $section_field_name): array
    {
        $contracts = [
            'section_hero' => ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary'],
            'section_form' => ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary'],
            'section_content' => ['headline', 'display', 'subhead', 'description', 'link'],
            'section_faqs' => ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary'],
            'section_gallery' => ['headline', 'display', 'subhead', 'description', 'link'],
            'section_list-reviews' => ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary'],
            'section_list-posts' => ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary'],
            'section_list-resources' => ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary'],
            'section_list-events' => ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary'],
            'section_list-past-events' => ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary'],
            'section_list-services' => ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary'],
            'section_list-partners' => ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary'],
            'section_list-sponsors' => ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary'],
            'section_list-profiles' => ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary'],
            'section_list-segments' => ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary'],
            'section_list-locations' => ['headline', 'display', 'subhead', 'description', 'link'],
            'section_benefits' => ['headline', 'display', 'subhead', 'description', 'link'],
            'section_list-songs' => ['headline', 'display', 'subhead', 'description', 'link'],
        ];

        $section_field_name = sanitize_key($section_field_name);

        return $contracts[$section_field_name] ?? ['headline', 'display', 'subhead', 'description', 'link', 'link_secondary'];
    }

    function meza_get_standard_list_section_sub_fields(array $config): array
    {
        $sub_fields = [
            meza_get_standard_list_section_text_field(
                (string) $config['headline_key'],
                'Headline (H2)',
                'headline',
                (string) ($config['headline_default'] ?? ''),
                1
            ),
        ];

        if (!empty($config['include_display'])) {
            $sub_fields[] = meza_get_standard_list_section_display_field((string) $config['display_key']);
        }

        $sub_fields[] = meza_get_standard_list_section_subhead_field(
            (string) $config['subhead_key'],
            (string) ($config['subhead_default'] ?? '')
        );

        if (!empty($config['include_description'])) {
            $sub_fields[] = meza_get_standard_list_section_description_field(
                (string) $config['description_key'],
                (int) ($config['description_rows'] ?? 2)
            );
        }

        $primary_link_key = (string) $config['link_key'];
        $sub_fields[] = meza_get_standard_list_section_link_field($primary_link_key);

        if (!empty($config['include_link_secondary'])) {
            $sub_fields[] = meza_get_standard_list_section_link_secondary_field(
                (string) $config['link_secondary_key'],
                $primary_link_key
            );
        }

        $sub_fields[] = meza_get_standard_list_section_text_field(
            (string) $config['id_key'],
            'ID',
            'id',
            (string) ($config['id_default'] ?? ''),
            0,
            (int) ($config['id_allow_in_bindings'] ?? 0)
        );

        return $sub_fields;
    }

    function meza_get_standard_list_section_group_field(
        string $key,
        string $name,
        string $visibility_key,
        array $sub_fields
    ): array {
        return [
            'key' => $key,
            'label' => 'Section',
            'name' => $name,
            'aria-label' => '',
            'type' => 'group',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => [
                [
                    [
                        'field' => $visibility_key,
                        'operator' => '==',
                        'value' => '1',
                    ],
                ],
            ],
            'wrapper' => meza_get_section_field_wrapper(),
            'layout' => 'block',
            'sub_fields' => $sub_fields,
        ];
    }

    function meza_get_standard_list_section_field_group_definition(array $config): array
    {
        $sub_fields = meza_get_standard_list_section_sub_fields($config);

        return [
            'key' => (string) $config['group_key'],
            'title' => (string) $config['title'],
            'fields' => [
                meza_get_standard_list_section_visibility_field(
                    (string) $config['visibility_key'],
                    (string) $config['visibility_name'],
                    (int) ($config['visibility_allow_in_bindings'] ?? 0)
                ),
                meza_get_standard_list_section_group_field(
                    (string) $config['section_key'],
                    (string) $config['section_name'],
                    (string) $config['visibility_key'],
                    $sub_fields
                ),
            ],
            'location' => $config['location'],
            'menu_order' => (int) $config['menu_order'],
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

    function meza_get_hero_section_field_group_definition(): array
    {
        return [
            'key' => 'group_692cdbb4a0ff0',
            'title' => 'Hero Section',
            'fields' => [
                [
                    'key' => 'field_692cdbb570d1d',
                    'label' => 'Section',
                    'name' => 'section_hero',
                    'aria-label' => '',
                    'type' => 'group',
                    'instructions' => 'Use for the page\'s primary introduction. Write one clear H1 headline, optional supporting copy, and optional calls to action.',
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
                            'key' => 'field_692cdbd770d1e',
                            'label' => 'Headline (H1)',
                            'name' => 'headline',
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
                            'key' => 'field_69b0793b330e0',
                            'label' => 'Display',
                            'name' => 'display',
                            'aria-label' => '',
                            'type' => 'text',
                            'instructions' => '',
                            'required' => 0,
                            'conditional_logic' => [
                                [
                                    [
                                        'field' => 'field_692cdbd770d1e',
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
                        [
                            'key' => 'field_692cdbdf70d1f',
                        ] + meza_get_standard_list_section_subhead_field('field_692cdbdf70d1f'),
                        [
                            'key' => 'field_692cdbeb70d20',
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
                            'new_lines' => 'wpautop',
                        ],
                        [
                            'key' => 'field_692cdc0270d21',
                            'label' => 'Link',
                            'name' => 'link',
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
                            'return_format' => 'array',
                            'allow_in_bindings' => 0,
                        ],
                        [
                            'key' => 'field_697fe81bb6b38',
                            'label' => 'Link (Secondary)',
                            'name' => 'link_secondary',
                            'aria-label' => '',
                            'type' => 'link',
                            'instructions' => '',
                            'required' => 0,
                            'conditional_logic' => [
                                [
                                    [
                                        'field' => 'field_692cdc0270d21',
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
                        [
                            'key' => 'field_69cfe961a379e',
                            'label' => 'Icon',
                            'name' => 'icon',
                            'aria-label' => '',
                            'type' => 'image',
                            'instructions' => '',
                            'required' => 0,
                            'conditional_logic' => 0,
                            'wrapper' => [
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ],
                            'return_format' => 'id',
                            'library' => 'all',
                            'min_width' => '',
                            'min_height' => '',
                            'min_size' => '',
                            'max_width' => '',
                            'max_height' => '',
                            'max_size' => '',
                            'mime_types' => '',
                            'allow_in_bindings' => 0,
                            'preview_size' => 'thumbnail',
                        ],
                        [
                            'key' => 'field_692cdc5a70d23',
                            'label' => 'Image',
                            'name' => 'image',
                            'aria-label' => '',
                            'type' => 'image',
                            'instructions' => '',
                            'required' => false,
                            'conditional_logic' => 0,
                            'wrapper' => [
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ],
                            'return_format' => 'id',
                            'library' => 'all',
                            'preview_size' => 'medium',
                            'min_width' => 0,
                            'min_height' => 0,
                            'min_size' => 0,
                            'max_width' => 0,
                            'max_height' => 0,
                            'max_size' => 0,
                            'mime_types' => '',
                        ],
                        [
                            'key' => 'field_692cdc8070d24',
                            'label' => 'Video File',
                            'name' => 'video',
                            'aria-label' => '',
                            'type' => 'file',
                            'instructions' => '',
                            'required' => 0,
                            'conditional_logic' => [
                                [
                                    [
                                        'field' => 'field_692cdc5a70d23',
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
                            'library' => 'all',
                            'min_size' => '',
                            'max_size' => '',
                            'mime_types' => '',
                            'allow_in_bindings' => 0,
                        ],
                        [
                            'key' => 'field_692cdcb870d25',
                            'label' => 'Video Embed',
                            'name' => 'video_embed',
                            'aria-label' => '',
                            'type' => 'oembed',
                            'instructions' => '',
                            'required' => 0,
                            'conditional_logic' => [
                                [
                                    [
                                        'field' => 'field_692cdc5a70d23',
                                        'operator' => '!=empty',
                                    ],
                                ],
                            ],
                            'wrapper' => [
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ],
                            'width' => '',
                            'height' => '',
                            'allow_in_bindings' => 0,
                        ],
                        [
                            'key' => 'field_692cdc4870d22',
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
                            'default_value' => 'top',
                            'maxlength' => '',
                            'allow_in_bindings' => 0,
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                        ],
                    ],
                ],
            ],
            'location' => meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies(),
            'menu_order' => 1,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => [
                'discussion',
                'comments',
                'revisions',
                'author',
                'format',
                'tags',
                'send-trackbacks',
            ],
            'active' => true,
            'description' => '',
            'show_in_rest' => 0,
            'display_title' => '',
            'allow_ai_access' => false,
            'ai_description' => '',
        ];
    }

    function meza_get_form_section_field_group_definition(): array
    {
        return [
            'key' => 'group_697ffe3780c87',
            'title' => 'Form Section',
            'fields' => [
                [
                    'key' => 'field_69b03fca31afa',
                    'label' => 'Visibility',
                    'name' => 'show_form',
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
                    'key' => 'field_69b03fdc31afb',
                    'label' => 'Section',
                    'name' => 'section_form',
                    'aria-label' => '',
                    'type' => 'group',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_69b03fca31afa',
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
                            'key' => 'field_69b03fe631afc',
                            'label' => 'Headline',
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
                            'default_value' => '',
                            'maxlength' => '',
                            'allow_in_bindings' => 0,
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                        ],
                        [
                            'key' => 'field_69b03ff131afd',
                        ] + meza_get_standard_list_section_subhead_field('field_69b03ff131afd'),
                        [
                            'key' => 'field_69b1272a3d536',
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
                            'key' => 'field_681651f0f8f01',
                            'label' => 'FAQs',
                            'name' => 'faqs',
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
                                'faq',
                            ],
                            'post_status' => [
                                'publish',
                            ],
                            'taxonomy' => '',
                            'filters' => [
                                'search',
                            ],
                            'return_format' => 'id',
                            'min' => 0,
                            'max' => 1,
                            'allow_in_bindings' => 0,
                            'elements' => '',
                            'bidirectional' => 0,
                            'bidirectional_target' => [],
                        ],
                        [
                            'key' => 'field_697ffe37abb58',
                            'label' => 'Form',
                            'name' => 'form',
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
                                'form',
                            ],
                            'post_status' => [
                                'publish',
                            ],
                            'taxonomy' => '',
                            'filters' => [
                                'search',
                            ],
                            'return_format' => 'id',
                            'min' => 1,
                            'max' => 1,
                            'allow_in_bindings' => 0,
                            'elements' => '',
                            'bidirectional' => 0,
                            'bidirectional_target' => [],
                        ],
                        [
                            'key' => 'field_69b03ff731afe',
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
                            'default_value' => '',
                            'maxlength' => '',
                            'allow_in_bindings' => 0,
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                        ],
                    ],
                ],
            ],
            'location' => meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies(),
            'menu_order' => 28,
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

    function meza_get_content_section_field_group_definition(): array
    {
        return [
            'key' => 'group_meza_content_section',
            'title' => 'Content Section',
            'fields' => [
                [
                    'key' => 'field_meza_show_content',
                    'label' => 'Visibility',
                    'name' => 'show_content',
                    'aria-label' => '',
                    'type' => 'true_false',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => meza_get_section_field_wrapper(),
                    'message' => '',
                    'default_value' => 0,
                    'allow_in_bindings' => 0,
                    'ui' => 0,
                    'ui_on_text' => '',
                    'ui_off_text' => '',
                ],
                [
                    'key' => 'field_meza_section_content',
                    'label' => 'Section',
                    'name' => 'section_content',
                    'aria-label' => '',
                    'type' => 'group',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_meza_show_content',
                                'operator' => '==',
                                'value' => '1',
                            ],
                        ],
                    ],
                    'wrapper' => meza_get_section_field_wrapper(),
                    'layout' => 'block',
                    'sub_fields' => [
                        meza_get_standard_list_section_text_field(
                            'field_meza_content_headline',
                            'Headline (H2)',
                            'headline',
                            '',
                            1
                        ),
                        meza_get_standard_list_section_text_field(
                            'field_meza_content_subhead',
                            'Subhead',
                            'subhead'
                        ),
                        [
                            'key' => 'field_meza_content_description',
                            'label' => 'Description',
                            'name' => 'description',
                            'aria-label' => '',
                            'type' => 'wysiwyg',
                            'instructions' => '',
                            'required' => 0,
                            'conditional_logic' => 0,
                            'wrapper' => meza_get_section_field_wrapper(),
                            'default_value' => '',
                            'tabs' => 'all',
                            'toolbar' => 'full',
                            'media_upload' => 1,
                            'delay' => 0,
                        ],
                        meza_get_standard_list_section_link_field('field_meza_content_link'),
                        [
                            'key' => 'field_meza_content_image',
                            'label' => 'Image',
                            'name' => 'image',
                            'aria-label' => '',
                            'type' => 'image',
                            'instructions' => '',
                            'required' => 0,
                            'conditional_logic' => 0,
                            'wrapper' => meza_get_section_field_wrapper(),
                            'return_format' => 'id',
                            'library' => 'all',
                            'preview_size' => 'medium',
                            'min_width' => '',
                            'min_height' => '',
                            'min_size' => '',
                            'max_width' => '',
                            'max_height' => '',
                            'max_size' => '',
                            'mime_types' => '',
                            'allow_in_bindings' => 0,
                        ],
                        meza_get_standard_list_section_text_field(
                            'field_meza_content_id',
                            'ID',
                            'id'
                        ),
                    ],
                ],
            ],
            'location' => meza_get_acf_location_rules_hidden_by_default(),
            'menu_order' => 23,
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

    function meza_get_cta_section_field_group_definition(): array
    {
        $cta_field = [
            'key' => 'field_232e234f',
            'label' => 'CTA',
            'name' => 'cta',
            'aria-label' => '',
            'type' => 'relationship',
            'instructions' => '',
            'required' => false,
            'conditional_logic' => false,
            'wrapper' => [
                'width' => '',
                'class' => '',
                'id' => '',
            ],
            'post_type' => [
                'cta',
            ],
            'filters' => [
                'search',
            ],
            'return_format' => 'id',
            'taxonomy' => [],
            'min' => 0,
            'max' => 0,
            'elements' => [],
            'bidirectional_target' => [],
        ];

        return [
            'key' => 'group_f7b16b48',
            'title' => 'CTA Section',
            'fields' => [
                $cta_field,
            ],
            'location' => meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies(),
            'menu_order' => 29,
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

    function meza_get_list_faqs_section_field_group_definition(): array
    {
        return [
            'key' => 'group_697feb01ec35f',
            'title' => 'List FAQs Section',
            'fields' => [
                [
                    'key' => 'field_69b03ef5673a1',
                    'label' => 'Visibility',
                    'name' => 'show_list-faqs',
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
                    'key' => 'field_69b03f0e673a2',
                    'label' => 'Section',
                    'name' => 'section_faqs',
                    'aria-label' => '',
                    'type' => 'group',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_69b03ef5673a1',
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
                            'key' => 'field_69b03f37673a3',
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
                            'default_value' => 'List FAQs',
                            'maxlength' => '',
                            'allow_in_bindings' => 0,
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                        ],
                        [
                            'key' => 'field_69b03f46673a4',
                        ] + meza_get_standard_list_section_subhead_field('field_69b03f46673a4'),
                        [
                            'key' => 'field_69b11ab5d9e51',
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
                            'key' => 'field_69b11ad6d9e52',
                            'label' => 'Link',
                            'name' => 'link',
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
                            'return_format' => 'array',
                            'allow_in_bindings' => 0,
                        ],
                        [
                            'key' => 'field_697ffc2b176c2',
                            'label' => 'FAQs',
                            'name' => 'faqs',
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
                                'faq',
                            ],
                            'post_status' => [
                                'publish',
                            ],
                            'taxonomy' => '',
                            'filters' => [
                                'search',
                            ],
                            'return_format' => 'id',
                            'min' => 1,
                            'max' => '',
                            'allow_in_bindings' => 0,
                            'elements' => '',
                            'bidirectional' => 0,
                            'bidirectional_target' => [],
                        ],
                        [
                            'key' => 'field_69ce52d4d29eb',
                            'label' => 'Image',
                            'name' => 'image',
                            'aria-label' => '',
                            'type' => 'image',
                            'instructions' => '',
                            'required' => 0,
                            'conditional_logic' => 0,
                            'wrapper' => [
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ],
                            'return_format' => 'id',
                            'library' => 'all',
                            'min_width' => '',
                            'min_height' => '',
                            'min_size' => '',
                            'max_width' => '',
                            'max_height' => '',
                            'max_size' => '',
                            'mime_types' => '',
                            'allow_in_bindings' => 0,
                            'preview_size' => 'medium',
                        ],
                        [
                            'key' => 'field_69b03f4e673a5',
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
                            'default_value' => 'faqs',
                            'maxlength' => '',
                            'allow_in_bindings' => 0,
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                        ],
                    ],
                ],
            ],
            'location' => meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies(),
            'menu_order' => 35,
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

    function meza_get_list_reviews_section_field_group_definition(): array
    {
        return meza_get_standard_list_section_field_group_definition([
            'group_key' => 'group_meza_list_reviews_section',
            'title' => 'List Reviews Section',
            'visibility_key' => 'field_meza_show_list_reviews',
            'visibility_name' => 'show_list-reviews',
            'section_key' => 'field_meza_section_list_reviews',
            'section_name' => 'section_list-reviews',
            'headline_key' => 'field_meza_list_reviews_headline',
            'include_display' => true,
            'display_key' => 'field_meza_list_reviews_display',
            'headline_default' => 'List Reviews',
            'subhead_key' => 'field_meza_list_reviews_subhead',
            'include_description' => true,
            'description_key' => 'field_meza_list_reviews_description',
            'link_key' => 'field_meza_list_reviews_link',
            'include_link_secondary' => true,
            'link_secondary_key' => 'field_meza_list_reviews_link_secondary',
            'id_key' => 'field_meza_list_reviews_id',
            'id_default' => 'reviews',
            'location' => meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies_excluding_posts(),
            'menu_order' => 6,
        ]);
    }

    function meza_get_list_posts_section_field_group_definition(): array
    {
        return meza_get_standard_list_section_field_group_definition([
            'group_key' => 'group_meza_list_posts_section',
            'title' => 'List Posts Section',
            'visibility_key' => 'field_meza_show_list_posts',
            'visibility_name' => 'show_list-posts',
            'section_key' => 'field_meza_section_list_posts',
            'section_name' => 'section_list-posts',
            'headline_key' => 'field_meza_list_posts_headline',
            'headline_default' => 'List Posts',
            'include_display' => true,
            'display_key' => 'field_meza_list_posts_display',
            'subhead_key' => 'field_meza_list_posts_subhead',
            'include_description' => true,
            'description_key' => 'field_meza_list_posts_description',
            'link_key' => 'field_meza_list_posts_link',
            'include_link_secondary' => true,
            'link_secondary_key' => 'field_meza_list_posts_link_secondary',
            'id_key' => 'field_meza_list_posts_id',
            'id_default' => 'posts',
            'location' => meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies(),
            'menu_order' => 33,
        ]);
    }

    function meza_get_list_resources_section_field_group_definition(): array
    {
        return meza_get_standard_list_section_field_group_definition([
            'group_key' => 'group_meza_list_resources_section',
            'title' => 'List Resources Section',
            'visibility_key' => 'field_meza_show_list_resources',
            'visibility_name' => 'show_list-resources',
            'section_key' => 'field_meza_section_list_resources',
            'section_name' => 'section_list-resources',
            'headline_key' => 'field_meza_list_resources_headline',
            'headline_default' => 'List Resources',
            'include_display' => true,
            'display_key' => 'field_meza_list_resources_display',
            'subhead_key' => 'field_meza_list_resources_subhead',
            'include_description' => true,
            'description_key' => 'field_meza_list_resources_description',
            'link_key' => 'field_meza_list_resources_link',
            'include_link_secondary' => true,
            'link_secondary_key' => 'field_meza_list_resources_link_secondary',
            'id_key' => 'field_meza_list_resources_id',
            'id_default' => 'resources',
            'location' => meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies(),
            'menu_order' => 34,
        ]);
    }

    function meza_get_list_events_section_field_group_definition(): array
    {
        return meza_get_standard_list_section_field_group_definition([
            'group_key' => 'group_meza_list_events_section',
            'title' => 'List Events Section',
            'visibility_key' => 'field_meza_show_list_events',
            'visibility_name' => 'show_list-events',
            'section_key' => 'field_meza_section_list_events',
            'section_name' => 'section_list-events',
            'headline_key' => 'field_meza_list_events_headline',
            'headline_default' => 'List Events',
            'include_display' => true,
            'display_key' => 'field_meza_list_events_display',
            'subhead_key' => 'field_meza_list_events_subhead',
            'include_description' => true,
            'description_key' => 'field_meza_list_events_description',
            'link_key' => 'field_meza_list_events_link',
            'include_link_secondary' => true,
            'link_secondary_key' => 'field_meza_list_events_link_secondary',
            'id_key' => 'field_meza_list_events_id',
            'id_default' => 'events',
            'location' => meza_get_acf_location_rules_hidden_by_default(),
            'menu_order' => 25,
        ]);
    }

    function meza_get_list_past_events_section_field_group_definition(): array
    {
        return meza_get_standard_list_section_field_group_definition([
            'group_key' => 'group_meza_list_past_events_section',
            'title' => 'List Past Events Section',
            'visibility_key' => 'field_meza_show_list_past_events',
            'visibility_name' => 'show_list-past-events',
            'section_key' => 'field_meza_section_list_past_events',
            'section_name' => 'section_list-past-events',
            'headline_key' => 'field_meza_list_past_events_headline',
            'headline_default' => 'Past Events',
            'include_display' => true,
            'display_key' => 'field_meza_list_past_events_display',
            'subhead_key' => 'field_meza_list_past_events_subhead',
            'include_description' => true,
            'description_key' => 'field_meza_list_past_events_description',
            'link_key' => 'field_meza_list_past_events_link',
            'include_link_secondary' => true,
            'link_secondary_key' => 'field_meza_list_past_events_link_secondary',
            'id_key' => 'field_meza_list_past_events_id',
            'id_default' => 'past-events',
            'location' => meza_get_acf_location_rules_hidden_by_default(),
            'menu_order' => 26,
        ]);
    }

    function meza_get_list_segments_section_field_group_definition(): array
    {
        return meza_get_standard_list_section_field_group_definition([
            'group_key' => 'group_meza_list_segments_section',
            'title' => 'List Segments Section',
            'visibility_key' => 'field_meza_show_list_segments',
            'visibility_name' => 'show_list-segments',
            'section_key' => 'field_meza_section_list_segments',
            'section_name' => 'section_list-segments',
            'headline_key' => 'field_meza_list_segments_headline',
            'headline_default' => 'List Segments',
            'include_display' => true,
            'display_key' => 'field_meza_list_segments_display',
            'subhead_key' => 'field_meza_list_segments_subhead',
            'include_description' => true,
            'description_key' => 'field_meza_list_segments_description',
            'link_key' => 'field_meza_list_segments_link',
            'include_link_secondary' => true,
            'link_secondary_key' => 'field_meza_list_segments_link_secondary',
            'id_key' => 'field_meza_list_segments_id',
            'id_default' => 'segments',
            'location' => meza_get_acf_location_rules_hidden_by_default(),
            'menu_order' => 5,
        ]);
    }

    function meza_get_list_services_section_field_group_definition(): array
    {
        $definition = meza_get_standard_list_section_field_group_definition([
            'group_key' => 'group_b2f9d7e8',
            'title' => 'List Services Section',
            'visibility_key' => 'field_3ce1e3d6',
            'visibility_name' => 'show_list-services',
            'visibility_allow_in_bindings' => 1,
            'section_key' => 'field_e278a597',
            'section_name' => 'section_list-services',
            'headline_key' => 'field_643fcf0d',
            'headline_default' => 'List Services',
            'include_display' => true,
            'display_key' => 'field_meza_list_services_display',
            'subhead_key' => 'field_9fe55eb5',
            'include_description' => true,
            'description_key' => 'field_meza_list_services_description',
            'link_key' => 'field_meza_list_services_link',
            'include_link_secondary' => true,
            'link_secondary_key' => 'field_meza_list_services_link_secondary',
            'id_key' => 'field_1086a562',
            'id_default' => 'services',
            'location' => meza_get_acf_location_rules_for_permalink_post_types_and_taxonomies_excluding_posts(),
            'menu_order' => 3,
        ]);

        foreach ($definition['fields'] as $field_index => $field) {
            if (!is_array($field) || ($field['name'] ?? '') !== 'section_list-services') {
                continue;
            }

            $definition['fields'][$field_index]['sub_fields'][] = [
                'key' => 'field_meza_list_services_selected_services',
                'label' => 'Services',
                'name' => 'services',
                'aria-label' => '',
                'type' => 'relationship',
                'instructions' => 'Select one or more services. Drag selected services to control display order.',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => meza_get_section_field_wrapper(),
                'post_type' => [
                    'service',
                ],
                'post_status' => [
                    'publish',
                ],
                'taxonomy' => '',
                'filters' => [
                    'search',
                ],
                'return_format' => 'id',
                'min' => 1,
                'max' => '',
                'allow_in_bindings' => 0,
                'elements' => '',
                'bidirectional' => 0,
                'bidirectional_target' => [],
            ];

            break;
        }

        return $definition;
    }

    function meza_get_list_partners_section_field_group_definition(): array
    {
        return meza_get_standard_list_section_field_group_definition([
            'group_key' => 'group_688f8c2740e76',
            'title' => 'List Partners Section',
            'visibility_key' => 'field_688f8c2757d70',
            'visibility_name' => 'show_list-partners',
            'section_key' => 'field_688f8c2757dd7',
            'section_name' => 'section_list-partners',
            'headline_key' => 'field_688f8c275f9ac',
            'headline_default' => 'List Partners',
            'include_display' => true,
            'display_key' => 'field_meza_list_partners_display',
            'subhead_key' => 'field_meza_list_partners_subhead',
            'include_description' => true,
            'description_key' => 'field_meza_list_partners_description',
            'link_key' => 'field_meza_list_partners_link',
            'include_link_secondary' => true,
            'link_secondary_key' => 'field_meza_list_partners_link_secondary',
            'id_key' => 'field_688f8c275faa1',
            'id_default' => 'partners',
            'location' => meza_get_acf_location_rules_hidden_by_default(),
            'menu_order' => 18,
        ]);
    }

    function meza_get_list_sponsors_section_field_group_definition(): array
    {
        return meza_get_standard_list_section_field_group_definition([
            'group_key' => 'group_688f8acec1ae2',
            'title' => 'List Sponsors Section',
            'visibility_key' => 'field_688f8aced885c',
            'visibility_name' => 'show_list-sponsors',
            'section_key' => 'field_688f8aced88a6',
            'section_name' => 'section_list-sponsors',
            'headline_key' => 'field_688f8acee3d3c',
            'headline_default' => 'List Sponsors',
            'include_display' => true,
            'display_key' => 'field_meza_list_sponsors_display',
            'subhead_key' => 'field_688f8acee3d82',
            'include_description' => true,
            'description_key' => 'field_meza_list_sponsors_description',
            'link_key' => 'field_688f8acee3dc0',
            'include_link_secondary' => true,
            'link_secondary_key' => 'field_meza_list_sponsors_link_secondary',
            'id_key' => 'field_688f8acee3e3c',
            'id_default' => 'sponsors',
            'location' => meza_get_acf_location_rules_hidden_by_default(),
            'menu_order' => 19,
        ]);
    }
