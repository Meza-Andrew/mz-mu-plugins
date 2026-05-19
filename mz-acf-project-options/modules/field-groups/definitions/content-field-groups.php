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

    function meza_get_event_field_group_definition(): array
    {
        $definition = json_decode(<<<'JSON'
{"key":"group_6a08b62b10548","title":"Event","fields":[{"key":"field_6a08df70b3199","label":"Dates and Times","name":"","aria-label":"","type":"tab","instructions":"","required":0,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"placement":"top","endpoint":0,"selected":0},{"key":"field_6a08cef5e142e","label":"Dates","name":"dates","aria-label":"","type":"repeater","instructions":"","required":1,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"layout":"table","pagination":0,"min":1,"max":0,"collapsed":"field_6a08b62b11d32","button_label":"Add a Date","rows_per_page":20,"sub_fields":[{"key":"field_6a08d06ee142f","label":"Date","name":"date","aria-label":"","type":"group","instructions":"","required":0,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"layout":"block","sub_fields":[{"key":"field_6a08b62b11d32","label":"Start Date","name":"start","aria-label":"","type":"date_picker","instructions":"","required":1,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"display_format":"F j, Y","return_format":"F j, Y","first_day":1,"default_to_current_date":0,"allow_in_bindings":0},{"key":"field_6a08ce1ae142b","label":"End Date","name":"end","aria-label":"","type":"date_picker","instructions":"","required":0,"conditional_logic":[[{"field":"field_6a08b62b11d32","operator":"!=empty"}]],"wrapper":{"width":"","class":"","id":""},"display_format":"F j, Y","return_format":"F j, Y","first_day":1,"default_to_current_date":0,"allow_in_bindings":0}],"parent_repeater":"field_6a08cef5e142e"},{"key":"field_6a08d083e1430","label":"Times","name":"times","aria-label":"","type":"repeater","instructions":"","required":1,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"layout":"table","pagination":0,"min":1,"max":0,"collapsed":"field_6a08b62b11d35","button_label":"Add Time Slot","rows_per_page":20,"sub_fields":[{"key":"field_6a08b62b11d35","label":"Start Time","name":"start","aria-label":"","type":"time_picker","instructions":"","required":1,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"display_format":"g:i a","return_format":"g:i a","allow_in_bindings":0,"parent_repeater":"field_6a08d083e1430"},{"key":"field_6a08b62b11d38","label":"End Time","name":"end","aria-label":"","type":"time_picker","instructions":"","required":0,"conditional_logic":[[{"field":"field_6a08b62b11d35","operator":"!=empty"}]],"wrapper":{"width":"","class":"","id":""},"display_format":"g:i a","return_format":"g:i a","allow_in_bindings":0,"parent_repeater":"field_6a08d083e1430"}],"parent_repeater":"field_6a08cef5e142e"}]},{"key":"field_6a08cebae142d","label":"Location","name":"","aria-label":"","type":"tab","instructions":"","required":0,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"placement":"top","endpoint":0,"selected":0},{"key":"field_6a08db22b15e5","label":"Virtual","name":"virtual","aria-label":"","type":"true_false","instructions":"","required":0,"conditional_logic":[[{"field":"field_6a08b62b11d40","operator":"==empty"},{"field":"field_6a08b62b11d42","operator":"==empty"}]],"wrapper":{"width":"","class":"","id":""},"message":"Is this an online event?","default_value":0,"allow_in_bindings":0,"ui":0,"ui_on_text":"","ui_off_text":""},{"key":"field_6a08b62b11d40","label":"Venue","name":"venue","aria-label":"","type":"relationship","instructions":"","required":1,"conditional_logic":[[{"field":"field_6a08db22b15e5","operator":"!=","value":"1"},{"field":"field_6a08b62b11d42","operator":"==empty"}]],"wrapper":{"width":"","class":"","id":""},"post_type":["organization"],"post_status":["publish"],"taxonomy":"","filters":["search"],"return_format":"id","min":"","max":1,"allow_in_bindings":0,"elements":"","bidirectional":0,"bidirectional_target":[]},{"key":"field_6a08b62b11d42","label":"Address","name":"address","aria-label":"","type":"google_map","instructions":"","required":1,"conditional_logic":[[{"field":"field_6a08b62b11d40","operator":"==empty"},{"field":"field_6a08db22b15e5","operator":"!=","value":"1"}]],"wrapper":{"width":"","class":"","id":""},"center_lat":"","center_lng":"","zoom":"","height":"","allow_in_bindings":0},{"key":"field_6a08dc0674992","label":"Details","name":"","aria-label":"","type":"tab","instructions":"","required":0,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"placement":"top","endpoint":0,"selected":0},{"key":"field_6a08dc2c74993","label":"Age","name":"age","aria-label":"","type":"select","instructions":"","required":1,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"choices":{"all":"All Ages","18":"18+","21":"21+"},"default_value":"all","return_format":"array","multiple":0,"allow_null":0,"allow_in_bindings":0,"ui":0,"ajax":0,"placeholder":"","create_options":0,"save_options":0},{"key":"field_6a08dc8474994","label":"Cost","name":"cost","aria-label":"","type":"number","instructions":"","required":1,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"default_value":"0.00","min":0,"max":"","allow_in_bindings":0,"placeholder":"0.00","step":".01","prepend":"$","append":""},{"key":"field_6a08dd8114bdc","label":"Disclaimer","name":"disclaimer","aria-label":"","type":"text","instructions":"","required":0,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"default_value":"","maxlength":"","allow_in_bindings":0,"placeholder":"","prepend":"","append":""},{"key":"field_6a08de2b44e3e","label":"Media","name":"","aria-label":"","type":"tab","instructions":"","required":0,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"placement":"top","endpoint":0,"selected":0},{"key":"field_6a08de3644e3f","label":"Photos","name":"photos","aria-label":"","type":"gallery","instructions":"","required":0,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"return_format":"id","library":"all","min":"","max":12,"min_width":"","min_height":"","min_size":"","max_width":"","max_height":"","max_size":"","mime_types":"","insert":"append","preview_size":"thumbnail"},{"key":"field_6a08de6944e40","label":"Video","name":"video_file","aria-label":"","type":"file","instructions":"","required":0,"conditional_logic":[[{"field":"field_6a08de3644e3f","operator":">","value":"0"},{"field":"field_6a08deb244e41","operator":"==empty"}]],"wrapper":{"width":"","class":"","id":""},"return_format":"id","library":"all","min_size":"","max_size":"","mime_types":".webm","allow_in_bindings":0},{"key":"field_6a08deb244e41","label":"Video","name":"video_embed","aria-label":"","type":"oembed","instructions":"","required":0,"conditional_logic":[[{"field":"field_6a08de3644e3f","operator":">","value":"0"},{"field":"field_6a08de6944e40","operator":"==empty"}]],"wrapper":{"width":"","class":"","id":""},"width":"","height":"","allow_in_bindings":0},{"key":"field_6a08e030237c4","label":"Relationships","name":"","aria-label":"","type":"tab","instructions":"","required":0,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"placement":"top","endpoint":0,"selected":0},{"key":"field_6a08dda1e1ff1","label":"Partners","name":"partners","aria-label":"","type":"relationship","instructions":"","required":0,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"post_type":["organization"],"post_status":["publish"],"taxonomy":["organization-type:partner"],"filters":["search"],"return_format":"id","min":"","max":"","allow_in_bindings":0,"elements":["featured_image"],"bidirectional":0,"bidirectional_target":[]},{"key":"field_6a08b62b11d48","label":"Sponsors","name":"sponsors","aria-label":"","type":"relationship","instructions":"","required":0,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"post_type":["organization"],"post_status":["publish"],"taxonomy":["organization-type:sponsor"],"filters":["search"],"return_format":"id","min":"","max":"","allow_in_bindings":0,"elements":["featured_image"],"bidirectional":0,"bidirectional_target":[]},{"key":"field_6a08b62b11d45","label":"Speakers","name":"speakers","aria-label":"","type":"relationship","instructions":"","required":0,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"post_type":["profile"],"post_status":["publish"],"taxonomy":["profile-type:speaker"],"filters":["search"],"return_format":"id","min":"","max":"","allow_in_bindings":0,"elements":["featured_image"],"bidirectional":0,"bidirectional_target":[]},{"key":"field_6a08d2efe1431","label":"Conditional Content","name":"","aria-label":"","type":"tab","instructions":"","required":0,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"placement":"top","endpoint":0,"selected":0},{"key":"field_6a08e3fc9599b","label":"Summary","name":"summary","aria-label":"","type":"group","instructions":"","required":0,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"layout":"table","sub_fields":[{"key":"field_6a08e3fc9599c","label":"Pre-Event","name":"before","aria-label":"","type":"textarea","instructions":"","required":0,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"default_value":"","maxlength":"","allow_in_bindings":0,"rows":2,"placeholder":"","new_lines":""},{"key":"field_6a08e3fc9599d","label":"Post-Event","name":"after","aria-label":"","type":"textarea","instructions":"","required":0,"conditional_logic":[[{"field":"field_6a08e3fc9599c","operator":"!=empty"}]],"wrapper":{"width":"","class":"","id":""},"default_value":"","maxlength":"","allow_in_bindings":0,"rows":2,"placeholder":"","new_lines":""}]},{"key":"field_6a08b62b11d4a","label":"Link","name":"link","aria-label":"","type":"group","instructions":"","required":0,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"layout":"table","sub_fields":[{"key":"field_6a08d94ae1433","label":"Pre-Event","name":"before","aria-label":"","type":"url","instructions":"","required":0,"conditional_logic":0,"wrapper":{"width":"","class":"","id":""},"default_value":"","allow_in_bindings":0,"placeholder":"https://"},{"key":"field_6a08e0a7a257d","label":"Post-Event","name":"after","aria-label":"","type":"url","instructions":"","required":0,"conditional_logic":[[{"field":"field_6a08d94ae1433","operator":"!=empty"}]],"wrapper":{"width":"","class":"","id":""},"default_value":"","allow_in_bindings":0,"placeholder":"https://"}]}],"location":[[{"param":"post_type","operator":"==","value":"event"}]],"menu_order":0,"position":"normal","style":"default","label_placement":"top","instruction_placement":"label","hide_on_screen":"","active":true,"description":"","show_in_rest":0,"display_title":"","allow_ai_access":false,"ai_description":""}
JSON, true);

        if (!is_array($definition)) {
            return [];
        }

        $fields = isset($definition['fields']) && is_array($definition['fields'])
            ? $definition['fields']
            : [];

        foreach ($fields as $field_index => $field) {
            if (!is_array($field) || ($field['key'] ?? '') !== 'field_6a08cef5e142e') {
                continue;
            }

            $sub_fields = isset($field['sub_fields']) && is_array($field['sub_fields'])
                ? $field['sub_fields']
                : [];

            foreach ($sub_fields as $sub_field_index => $sub_field) {
                if (!is_array($sub_field) || ($sub_field['key'] ?? '') !== 'field_6a08d06ee142f') {
                    continue;
                }

                $date_sub_fields = isset($sub_field['sub_fields']) && is_array($sub_field['sub_fields'])
                    ? $sub_field['sub_fields']
                    : [];

                $date_sub_fields = array_values(array_filter($date_sub_fields, static function ($date_sub_field): bool {
                    return !is_array($date_sub_field) || ($date_sub_field['key'] ?? '') !== 'field_6a08ce1ae142b';
                }));

                $sub_fields[$sub_field_index]['sub_fields'] = $date_sub_fields;
            }

            $fields[$field_index]['sub_fields'] = $sub_fields;
            break;
        }

        $recurrence_fields = [
            [
                'key' => 'field_meza_event_generate_additional_dates',
                'label' => 'Generate additional dates?',
                'name' => 'generate_additional_dates',
                'aria-label' => '',
                'type' => 'true_false',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_6a08b62b11d32',
                            'operator' => '!=empty',
                        ],
                        [
                            'field' => 'field_6a08b62b11d35',
                            'operator' => '!=empty',
                        ],
                    ],
                ],
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'message' => '',
                'default_value' => 0,
                'allow_in_bindings' => 0,
                'ui' => 1,
                'ui_on_text' => '',
                'ui_off_text' => '',
            ],
            [
                'key' => 'field_meza_event_recurrence',
                'label' => 'How frequently does this event occur?',
                'name' => 'recurrence',
                'aria-label' => '',
                'type' => 'select',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_6a08b62b11d32',
                            'operator' => '!=empty',
                        ],
                        [
                            'field' => 'field_6a08b62b11d35',
                            'operator' => '!=empty',
                        ],
                        [
                            'field' => 'field_meza_event_generate_additional_dates',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
                'wrapper' => [
                    'width' => '50',
                    'class' => '',
                    'id' => '',
                ],
                'choices' => [
                    'weekly' => 'Weekly',
                    'monthly' => 'Monthly',
                    'monthly-week' => 'Monthly (same weekday)',
                ],
                'default_value' => 'monthly',
                'return_format' => 'value',
                'multiple' => 0,
                'allow_null' => 0,
                'allow_in_bindings' => 0,
                'ui' => 0,
                'ajax' => 0,
                'placeholder' => '',
                'create_options' => 0,
                'save_options' => 0,
            ],
            [
                'key' => 'field_meza_event_recurrence_cap',
                'label' => 'How many months ahead should be generated?',
                'name' => 'recurrence_cap',
                'aria-label' => '',
                'type' => 'number',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => [
                    [
                        [
                            'field' => 'field_6a08b62b11d32',
                            'operator' => '!=empty',
                        ],
                        [
                            'field' => 'field_6a08b62b11d35',
                            'operator' => '!=empty',
                        ],
                        [
                            'field' => 'field_meza_event_generate_additional_dates',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
                'wrapper' => [
                    'width' => '50',
                    'class' => '',
                    'id' => '',
                ],
                'default_value' => 3,
                'min' => 1,
                'max' => '',
                'allow_in_bindings' => 0,
                'placeholder' => '3',
                'step' => 1,
                'prepend' => '',
                'append' => 'months',
            ],
        ];

        $insert_at = count($fields);
        foreach ($fields as $index => $field) {
            if (($field['key'] ?? '') === 'field_6a08cebae142d') {
                $insert_at = $index;
                break;
            }
        }

        array_splice($fields, $insert_at, 0, $recurrence_fields);

        $fields = array_values(array_filter($fields, static function (array $field): bool {
            return !in_array(($field['key'] ?? ''), [
                'field_6a08d2efe1431',
                'field_6a08dc2c74993',
                'field_6a08dc8474994',
                'field_6a08dd8114bdc',
                'field_6a08e3fc9599b',
                'field_6a08b62b11d4a',
            ], true);
        }));

        $details_insert_at = count($fields);
        foreach ($fields as $index => $field) {
            if (($field['key'] ?? '') === 'field_6a08de2b44e3e') {
                $details_insert_at = $index;
                break;
            }
        }

        $use_event_after_content = function_exists('meza_events_have_after_content')
            && meza_events_have_after_content();

        $summary_field = [
            'key' => 'field_6a08e3fc9599b',
            'label' => 'Summary',
            'name' => 'summary',
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
        ];

        if ($use_event_after_content) {
            $summary_field = [
                'key' => 'field_6a08e3fc9599b',
                'label' => 'Summary',
                'name' => 'summary',
                'aria-label' => '',
                'type' => 'group',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'layout' => 'table',
                'sub_fields' => [
                    [
                        'key' => 'field_6a08e3fc9599c',
                        'label' => 'Pre-Event',
                        'name' => 'before',
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
                        'key' => 'field_6a08e3fc9599d',
                        'label' => 'Post-Event',
                        'name' => 'after',
                        'aria-label' => '',
                        'type' => 'textarea',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_6a08e3fc9599c',
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
                        'rows' => 2,
                        'placeholder' => '',
                        'new_lines' => '',
                    ],
                ],
            ];
        }

        $link_field = [
            'key' => 'field_6a08b62b11d4a',
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
        ];

        if ($use_event_after_content) {
            $link_field = [
                'key' => 'field_6a08b62b11d4a',
                'label' => 'Link',
                'name' => 'link',
                'aria-label' => '',
                'type' => 'group',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'layout' => 'table',
                'sub_fields' => [
                    [
                        'key' => 'field_6a08d94ae1433',
                        'label' => 'Pre-Event',
                        'name' => 'before',
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
                        'key' => 'field_6a08e0a7a257d',
                        'label' => 'Post-Event',
                        'name' => 'after',
                        'aria-label' => '',
                        'type' => 'link',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => [
                            [
                                [
                                    'field' => 'field_6a08d94ae1433',
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
            ];
        }

        $details_fields = [
            $summary_field,
            [
                'key' => 'field_6a08dc2c74993',
                'label' => 'Age',
                'name' => 'age',
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
                    'all' => 'All Ages',
                    '18' => '18+',
                    '21' => '21+',
                ],
                'default_value' => 'all',
                'return_format' => 'array',
                'multiple' => 0,
                'allow_null' => 0,
                'allow_in_bindings' => 0,
                'ui' => 0,
                'ajax' => 0,
                'placeholder' => '',
                'create_options' => 0,
                'save_options' => 0,
            ],
            [
                'key' => 'field_6a08dc8474994',
                'label' => 'Cost',
                'name' => 'cost',
                'aria-label' => '',
                'type' => 'number',
                'instructions' => '',
                'required' => 1,
                'conditional_logic' => 0,
                'wrapper' => [
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ],
                'default_value' => '0.00',
                'min' => 0,
                'max' => '',
                'allow_in_bindings' => 0,
                'placeholder' => '0.00',
                'step' => '.01',
                'prepend' => '$',
                'append' => '',
            ],
            $link_field,
            [
                'key' => 'field_6a08dd8114bdc',
                'label' => 'Disclaimer',
                'name' => 'disclaimer',
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

        array_splice($fields, $details_insert_at, 0, $details_fields);
        $tab_order = [
            'field_6a08df70b3199',
            'field_6a08cebae142d',
            'field_6a08dc0674992',
            'field_6a08e030237c4',
            'field_6a08de2b44e3e',
        ];

        $tab_positions = [];
        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                continue;
            }

            $field_key = (string) ($field['key'] ?? '');
            if (in_array($field_key, $tab_order, true)) {
                $tab_positions[$field_key] = $index;
            }
        }

        if (count($tab_positions) === count($tab_order)) {
            $reordered_fields = [];

            foreach ($tab_order as $position => $tab_key) {
                $start = $tab_positions[$tab_key];
                $end = isset($tab_order[$position + 1]) ? $tab_positions[$tab_order[$position + 1]] : count($fields);

                foreach (array_slice($fields, $start, $end - $start) as $field) {
                    $reordered_fields[] = $field;
                }
            }

            if ($reordered_fields !== []) {
                $fields = $reordered_fields;
            }
        }

        if (
            !(function_exists('meza_supports_profile_features') && meza_supports_profile_features())
            || !(function_exists('meza_events_have_speakers_topics') && meza_events_have_speakers_topics())
        ) {
            $fields = array_values(array_filter($fields, static function (array $field): bool {
                return ($field['key'] ?? '') !== 'field_6a08b62b11d45';
            }));
        }

        $definition['fields'] = $fields;

        return $definition;
    }

    function meza_get_post_field_group_definition(): array
    {
        return [
            'key' => 'group_693a4ad0e2645',
            'title' => 'Post',
            'fields' => [
                [
                    'key' => 'field_693a4ad1d28c3',
                    'label' => 'Profile',
                    'name' => 'profile',
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
                        'profile',
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
                        'value' => 'post',
                    ],
                ],
            ],
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => [
                'discussion',
                'comments',
                'revisions',
                'slug',
                'author',
                'categories',
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

    function meza_get_certification_field_group_definition(): array
    {
        return [
            'key' => 'group_6913c90e4b603',
            'title' => 'Certification',
            'fields' => [
                [
                    'key' => 'field_6913c90ee4d75',
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
                    'allow_in_bindings' => 0,
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'certification',
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
