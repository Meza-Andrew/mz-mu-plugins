<?php

/**
 * Shared ACF option pages and field groups we want available on every project.
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) {
    return;
}

if (!function_exists('meza_get_shared_project_acf_options_pages')) {
    function meza_get_shared_project_acf_options_pages(): array
    {
        $pages = [
            [
                'page_title'      => 'Business Information',
                'menu_title'      => 'Business Information',
                'menu_slug'       => 'business-info',
                'parent_slug'     => 'options-general.php',
                'capability'      => 'manage_options',
                'redirect'        => false,
                'update_button'   => 'Update',
                'updated_message' => 'Options Updated',
                'autoload'        => false,
            ],
            [
                'page_title'      => 'Contact Information',
                'menu_title'      => 'Contact Information',
                'menu_slug'       => 'organization-info',
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

if (!function_exists('meza_get_shared_project_acf_field_groups')) {
    function meza_get_shared_project_acf_field_groups(): array
    {
        $groups = [
            [
                'key' => 'group_68b3145739719',
                'title' => 'Business Information',
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
                            'value' => 'business-info',
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
                'key' => 'group_68c1337b43d46',
                'title' => 'Contact Information',
                'fields' => [
                    [
                        'key' => 'field_69b5a28923bbe',
                        'label' => 'Phone',
                        'name' => 'phone',
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
                        'key' => 'field_68c133a0425a8',
                        'label' => 'Email',
                        'name' => 'email',
                        'aria-label' => '',
                        'type' => 'email',
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
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ],
                    [
                        'key' => 'field_69b5a2b623bc0',
                        'label' => 'Address',
                        'name' => 'address',
                        'aria-label' => '',
                        'type' => 'textarea',
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
                            'value' => 'organization-info',
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
        ];

        $groups = apply_filters('meza_shared_project_acf_field_groups', $groups);

        return is_array($groups) ? array_values($groups) : [];
    }
}

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
