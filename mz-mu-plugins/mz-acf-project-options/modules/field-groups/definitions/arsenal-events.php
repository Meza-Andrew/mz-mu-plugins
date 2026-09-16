<?php

/**
 * Arsenal Events ACF Field Groups & CPT Definitions
 * All field keys, group names, and layout slugs use strict snake_case naming.
 */

function arsenal_events_is_current_site(): bool
{
    if (defined('ARSENAL_EVENTS_SITE') && ARSENAL_EVENTS_SITE) {
        return true;
    }

    if (!function_exists('home_url')) {
        return false;
    }

    $host = parse_url((string) home_url('/'), PHP_URL_HOST);
    return is_string($host) && strtolower($host) === 'arsenal-events.local';
}

function arsenal_events_get_hidden_default_page_field_group_titles(): array
{
    return [
        'Hero Section',
        'List Services Section',
        'List Segments Section',
        'List Reviews Section',
        'List Localities Section',
        'Form Section',
        'CTA Section',
        'List Posts Section',
        'List Resources Section',
        'List FAQs Section',
    ];
}

function arsenal_events_location_group_targets_default_page_editor(array $location_group): bool
{
    foreach ($location_group as $rule) {
        if (
            is_array($rule)
            && ($rule['param'] ?? '') === 'post_type'
            && ($rule['operator'] ?? '') === '=='
            && ($rule['value'] ?? '') === 'page'
        ) {
            return true;
        }
    }

    return false;
}

function arsenal_events_location_group_already_excludes_default_page_template(array $location_group): bool
{
    foreach ($location_group as $rule) {
        if (
            is_array($rule)
            && ($rule['param'] ?? '') === 'page_template'
            && ($rule['operator'] ?? '') === '!='
            && ($rule['value'] ?? '') === 'default'
        ) {
            return true;
        }
    }

    return false;
}

function arsenal_events_hide_group_from_default_page_editor(array $group): array
{
    foreach ((array) ($group['location'] ?? []) as $index => $location_group) {
        if (!is_array($location_group)) {
            continue;
        }

        if (
            !arsenal_events_location_group_targets_default_page_editor($location_group)
            || arsenal_events_location_group_already_excludes_default_page_template($location_group)
        ) {
            continue;
        }

        $location_group[] = [
            'param' => 'page_template',
            'operator' => '!=',
            'value' => 'default',
        ];
        $group['location'][$index] = $location_group;
    }

    return $group;
}

function arsenal_events_filter_default_page_acf_field_groups(array $groups): array
{
    if (!arsenal_events_is_current_site()) {
        return $groups;
    }

    $hidden_titles = array_flip(arsenal_events_get_hidden_default_page_field_group_titles());

    foreach ($groups as $index => $group) {
        if (!is_array($group) || !isset($hidden_titles[(string) ($group['title'] ?? '')])) {
            continue;
        }

        $groups[$index] = arsenal_events_hide_group_from_default_page_editor($group);
    }

    return $groups;
}

function arsenal_events_filter_default_page_acf_field_group_location(array $field_group): array
{
    if (!arsenal_events_is_current_site()) {
        return $field_group;
    }

    $hidden_titles = array_flip(arsenal_events_get_hidden_default_page_field_group_titles());
    if (!isset($hidden_titles[(string) ($field_group['title'] ?? '')])) {
        return $field_group;
    }

    return arsenal_events_hide_group_from_default_page_editor($field_group);
}

function arsenal_events_get_current_page_editor_post_id(): int
{
    $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;

    if ($post_id <= 0 && isset($_POST['post_ID'])) {
        $post_id = (int) $_POST['post_ID'];
    }

    return max(0, $post_id);
}

function arsenal_events_is_default_page_editor_context(string $post_type): bool
{
    if (!arsenal_events_is_current_site() || $post_type !== 'page') {
        return false;
    }

    $post_id = arsenal_events_get_current_page_editor_post_id();
    if ($post_id <= 0 || !function_exists('get_page_template_slug')) {
        return true;
    }

    $template = (string) get_page_template_slug($post_id);
    return $template === '' || $template === 'default';
}

function arsenal_events_filter_loaded_default_page_acf_field_groups(array $field_groups, string $post_type): array
{
    if (!arsenal_events_is_default_page_editor_context($post_type)) {
        return $field_groups;
    }

    $hidden_titles = array_flip(arsenal_events_get_hidden_default_page_field_group_titles());

    return array_values(array_filter(
        $field_groups,
        static fn(array $field_group): bool => !isset($hidden_titles[(string) ($field_group['title'] ?? '')])
    ));
}

function arsenal_events_register_page_slug_location_rule(array $choices): array
{
    if (!isset($choices['Post']) || !is_array($choices['Post'])) {
        $choices['Post'] = [];
    }

    $choices['Post']['arsenal_page_slug'] = 'Page Slug';

    return $choices;
}

function arsenal_events_get_page_slug_location_values(array $choices): array
{
    $choices['home'] = 'Homepage';
    $choices['for-race-directors'] = 'For Race Directors';
    $choices['races-and-results'] = 'Races & Results';

    return $choices;
}

function arsenal_events_match_page_slug_location_rule(bool $match, array $rule, array $options): bool
{
    $raw_post_id = $options['post_id'] ?? arsenal_events_get_current_page_editor_post_id();
    $post_id = is_string($raw_post_id) && strncmp($raw_post_id, 'post_', 5) === 0
        ? (int) substr($raw_post_id, 5)
        : (int) $raw_post_id;

    if ($post_id <= 0 || !function_exists('get_post')) {
        return false;
    }

    $post = get_post($post_id);
    $slug = $post instanceof WP_Post ? (string) $post->post_name : '';
    $target = (string) ($rule['value'] ?? '');
    $operator = (string) ($rule['operator'] ?? '==');
    $matches = $slug === $target || ($target === 'home' && $slug === '');

    return $operator === '!=' ? !$matches : $matches;
}

if (function_exists('add_filter')) {
    add_filter('meza_shared_project_acf_field_groups', 'arsenal_events_filter_default_page_acf_field_groups');
    add_filter('acf/load_field_group', 'arsenal_events_filter_default_page_acf_field_group_location');
    add_filter('acf/load_field_groups', 'arsenal_events_filter_loaded_default_page_acf_field_groups', 20, 2);
    add_filter('acf/location/rule_types', 'arsenal_events_register_page_slug_location_rule');
    add_filter('acf/location/rule_values/arsenal_page_slug', 'arsenal_events_get_page_slug_location_values');
    add_filter('acf/location/rule_match/arsenal_page_slug', 'arsenal_events_match_page_slug_location_rule', 10, 3);
}

function arsenal_events_get_global_settings_group(): array
{
    return [
        'key' => 'group_global_site_settings',
        'title' => 'Global Site Settings',
        'fields' => [
            [
                'key' => 'field_site_phone',
                'label' => 'Site Phone',
                'name' => 'site_phone',
                'type' => 'text',
                'instructions' => 'Main contact phone number',
                'required' => 0,
                'wrapper' => ['width' => '50'],
            ],
            [
                'key' => 'field_site_email',
                'label' => 'Site Email',
                'name' => 'site_email',
                'type' => 'email',
                'instructions' => 'Main contact email',
                'required' => 0,
                'wrapper' => ['width' => '50'],
            ],
            [
                'key' => 'field_site_info_text',
                'label' => 'Site Info Text',
                'name' => 'site_info_text',
                'type' => 'textarea',
                'instructions' => 'General information text',
                'required' => 0,
                'rows' => 3,
            ],
            [
                'key' => 'field_site_copyright_text',
                'label' => 'Copyright Text',
                'name' => 'site_copyright_text',
                'type' => 'text',
                'instructions' => 'Copyright notice',
                'required' => 0,
            ],
            [
                'key' => 'field_global_header_nav_links',
                'label' => 'Header Navigation Links',
                'name' => 'header_nav_links',
                'type' => 'repeater',
                'instructions' => 'Main navigation links displayed in the site header',
                'layout' => 'table',
                'button_label' => 'Add Header Link',
                'sub_fields' => [
                    [
                        'key' => 'field_global_header_nav_label',
                        'label' => 'Label',
                        'name' => 'label',
                        'type' => 'text',
                        'wrapper' => ['width' => '50'],
                    ],
                    [
                        'key' => 'field_global_header_nav_href',
                        'label' => 'URL',
                        'name' => 'href',
                        'type' => 'url',
                        'wrapper' => ['width' => '50'],
                    ],
                ],
            ],
            [
                'key' => 'field_global_footer_nav_links',
                'label' => 'Footer Navigation Links',
                'name' => 'footer_nav_links',
                'type' => 'repeater',
                'instructions' => 'Navigation links displayed in the site footer',
                'layout' => 'table',
                'button_label' => 'Add Footer Link',
                'sub_fields' => [
                    [
                        'key' => 'field_global_footer_nav_label',
                        'label' => 'Label',
                        'name' => 'label',
                        'type' => 'text',
                        'wrapper' => ['width' => '50'],
                    ],
                    [
                        'key' => 'field_global_footer_nav_href',
                        'label' => 'URL',
                        'name' => 'href',
                        'type' => 'url',
                        'wrapper' => ['width' => '50'],
                    ],
                ],
            ],
            [
                'key' => 'field_social_links',
                'label' => 'Social Links',
                'name' => 'social_links',
                'type' => 'repeater',
                'instructions' => 'Social media links',
                'layout' => 'table',
                'button_label' => 'Add Social Link',
                'sub_fields' => [
                    [
                        'key' => 'field_social_icon',
                        'label' => 'Icon',
                        'name' => 'social_icon',
                        'type' => 'select',
                        'choices' => [
                            'linkedin' => 'LinkedIn',
                            'facebook' => 'Facebook',
                            'instagram' => 'Instagram',
                            'twitter' => 'Twitter',
                            'youtube' => 'YouTube',
                        ],
                        'wrapper' => ['width' => '33.33'],
                    ],
                    [
                        'key' => 'field_social_url',
                        'label' => 'URL',
                        'name' => 'social_url',
                        'type' => 'url',
                        'wrapper' => ['width' => '33.33'],
                    ],
                    [
                        'key' => 'field_social_label',
                        'label' => 'Label',
                        'name' => 'social_label',
                        'type' => 'text',
                        'wrapper' => ['width' => '33.33'],
                    ],
                ],
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'options_page',
                    'operator' => '==',
                    'value' => 'arsenal-events-settings',
                ],
            ],
        ],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'description' => 'Global site configuration for Arsenal Events',
        'show_in_rest' => 1,
    ];
}

function arsenal_events_text_field(string $key, string $label, string $name, string $width = ''): array
{
    $field = [
        'key' => $key,
        'label' => $label,
        'name' => $name,
        'type' => 'text',
        'required' => 0,
    ];

    if ($width !== '') {
        $field['wrapper'] = ['width' => $width];
    }

    return $field;
}

function arsenal_events_textarea_field(string $key, string $label, string $name): array
{
    return [
        'key' => $key,
        'label' => $label,
        'name' => $name,
        'type' => 'textarea',
        'required' => 0,
        'rows' => 3,
    ];
}

function arsenal_events_url_field(string $key, string $label, string $name, string $width = '50'): array
{
    return [
        'key' => $key,
        'label' => $label,
        'name' => $name,
        'type' => 'url',
        'required' => 0,
        'wrapper' => ['width' => $width],
    ];
}

function arsenal_events_image_field(string $key, string $label, string $name): array
{
    return [
        'key' => $key,
        'label' => $label,
        'name' => $name,
        'type' => 'image',
        'return_format' => 'array',
        'preview_size' => 'thumbnail',
        'library' => 'all',
        'required' => 0,
    ];
}

function arsenal_events_stat_fields(string $prefix): array
{
    return [
        arsenal_events_image_field("field_{$prefix}_stat_icon", 'Icon', 'stat_icon'),
        arsenal_events_text_field("field_{$prefix}_stat_value", 'Value', 'stat_value', '50'),
        arsenal_events_text_field("field_{$prefix}_stat_label", 'Label', 'stat_label', '50'),
    ];
}

function arsenal_events_stats_repeater(string $key, string $name, string $prefix): array
{
    return [
        'key' => $key,
        'label' => 'Stats',
        'name' => $name,
        'type' => 'repeater',
        'layout' => 'table',
        'button_label' => 'Add Stat',
        'sub_fields' => arsenal_events_stat_fields($prefix),
    ];
}

function arsenal_events_cta_fields(string $prefix): array
{
    return [
        arsenal_events_text_field("field_{$prefix}_cta_title", 'CTA Title', "{$prefix}_cta_title"),
        arsenal_events_textarea_field("field_{$prefix}_cta_description", 'CTA Description', "{$prefix}_cta_description"),
        arsenal_events_text_field("field_{$prefix}_cta_primary_text", 'Primary Button Text', "{$prefix}_cta_primary_text", '50'),
        arsenal_events_url_field("field_{$prefix}_cta_primary_href", 'Primary Button URL', "{$prefix}_cta_primary_href", '50'),
        arsenal_events_text_field("field_{$prefix}_cta_secondary_text", 'Secondary Button Text', "{$prefix}_cta_secondary_text", '50'),
        arsenal_events_url_field("field_{$prefix}_cta_secondary_href", 'Secondary Button URL', "{$prefix}_cta_secondary_href", '50'),
    ];
}

function arsenal_events_page_slug_location(string $slug): array
{
    return [
        [
            [
                'param' => 'arsenal_page_slug',
                'operator' => '==',
                'value' => $slug,
            ],
        ],
    ];
}

function arsenal_events_get_home_structured_content_group(): array
{
    return [
        'key' => 'group_arsenal_home_structured_content',
        'title' => 'Homepage Structured Sections',
        'fields' => [
            arsenal_events_text_field('field_home_upcoming_races_title', 'Upcoming Races Title', 'home_upcoming_races_title'),
            arsenal_events_textarea_field('field_home_upcoming_races_description', 'Upcoming Races Description', 'home_upcoming_races_description'),
            arsenal_events_text_field('field_home_stats_title', 'Stats Title', 'home_stats_title'),
            arsenal_events_stats_repeater('field_home_stats_items', 'home_stats_items', 'home'),
            arsenal_events_text_field('field_home_testimonials_title', 'Testimonials Title', 'home_testimonials_title'),
            arsenal_events_textarea_field('field_home_testimonials_description', 'Testimonials Description', 'home_testimonials_description'),
            [
                'key' => 'field_home_testimonials_items',
                'label' => 'Testimonials',
                'name' => 'home_testimonials_items',
                'type' => 'repeater',
                'layout' => 'block',
                'button_label' => 'Add Testimonial',
                'sub_fields' => [
                    [
                        'key' => 'field_home_testimonial_type',
                        'label' => 'Type',
                        'name' => 'testimonial_type',
                        'type' => 'select',
                        'choices' => [
                            'director' => 'Race Director',
                            'runner' => 'Runner',
                        ],
                        'allow_null' => 1,
                    ],
                    arsenal_events_text_field('field_home_testimonial_label', 'Label', 'testimonial_label'),
                    arsenal_events_textarea_field('field_home_testimonial_quote', 'Quote', 'testimonial_quote'),
                    arsenal_events_text_field('field_home_testimonial_attribution', 'Attribution', 'testimonial_attribution'),
                ],
            ],
            arsenal_events_text_field('field_home_resources_title', 'Resources Title', 'home_resources_title'),
            arsenal_events_textarea_field('field_home_resources_description', 'Resources Description', 'home_resources_description'),
            [
                'key' => 'field_home_resources_items',
                'label' => 'Resource Cards',
                'name' => 'home_resources_items',
                'type' => 'repeater',
                'layout' => 'block',
                'button_label' => 'Add Resource Card',
                'sub_fields' => [
                    arsenal_events_text_field('field_home_resource_title', 'Title', 'resource_title'),
                    arsenal_events_image_field('field_home_resource_image', 'Image', 'resource_image'),
                    [
                        'key' => 'field_home_resource_category',
                        'label' => 'Category',
                        'name' => 'resource_category',
                        'type' => 'select',
                        'choices' => [
                            'for_runners' => 'For Runners',
                            'for_directors' => 'For Race Directors',
                        ],
                        'allow_null' => 1,
                    ],
                    arsenal_events_url_field('field_home_resource_link', 'Link URL', 'resource_link', '100'),
                ],
            ],
            ...arsenal_events_cta_fields('home'),
        ],
        'location' => arsenal_events_page_slug_location('home'),
        'menu_order' => 10,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'description' => 'Narrow homepage structured content fields for visible sections without generic layout groups.',
        'show_in_rest' => 1,
    ];
}

function arsenal_events_get_race_director_structured_content_group(): array
{
    return [
        'key' => 'group_arsenal_rd_structured_content',
        'title' => 'Race Director Structured Sections',
        'fields' => [
            arsenal_events_text_field('field_rd_stats_title', 'Stats Title', 'rd_stats_title'),
            arsenal_events_stats_repeater('field_rd_stats_items', 'rd_stats_items', 'rd'),
            arsenal_events_text_field('field_rd_inquiry_title', 'Inquiry Title', 'rd_inquiry_title'),
            arsenal_events_textarea_field('field_rd_inquiry_description', 'Inquiry Description', 'rd_inquiry_description'),
            arsenal_events_text_field('field_rd_inquiry_primary_text', 'Inquiry Primary Button Text', 'rd_inquiry_primary_text', '50'),
            arsenal_events_url_field('field_rd_inquiry_primary_href', 'Inquiry Primary Button URL', 'rd_inquiry_primary_href', '50'),
            arsenal_events_text_field('field_rd_inquiry_secondary_text', 'Inquiry Secondary Button Text', 'rd_inquiry_secondary_text', '50'),
            arsenal_events_url_field('field_rd_inquiry_secondary_href', 'Inquiry Secondary Button URL', 'rd_inquiry_secondary_href', '50'),
            arsenal_events_text_field('field_rd_resources_title', 'Resources Title', 'rd_resources_title'),
            arsenal_events_textarea_field('field_rd_resources_description', 'Resources Description', 'rd_resources_description'),
            ...arsenal_events_cta_fields('rd'),
        ],
        'location' => arsenal_events_page_slug_location('for-race-directors'),
        'menu_order' => 10,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'description' => 'Race Director page-specific structured fields for visible stats, inquiry, resources, and CTA copy.',
        'show_in_rest' => 1,
    ];
}

function arsenal_events_get_races_results_structured_content_group(): array
{
    return [
        'key' => 'group_arsenal_rr_structured_content',
        'title' => 'Races & Results Structured Sections',
        'fields' => [
            arsenal_events_text_field('field_rr_results_title', 'Results Section Title', 'rr_results_title'),
            arsenal_events_textarea_field('field_rr_results_description', 'Results Section Description', 'rr_results_description'),
            arsenal_events_text_field('field_rr_results_unavailable_label', 'Unavailable Label', 'rr_results_unavailable_label'),
            arsenal_events_textarea_field('field_rr_results_unavailable_description', 'Unavailable Description', 'rr_results_unavailable_description'),
            arsenal_events_text_field('field_rr_upcoming_races_title', 'Upcoming Races Title', 'rr_upcoming_races_title'),
            arsenal_events_textarea_field('field_rr_upcoming_races_description', 'Upcoming Races Description', 'rr_upcoming_races_description'),
            arsenal_events_text_field('field_rr_stats_title', 'Stats Title', 'rr_stats_title'),
            arsenal_events_stats_repeater('field_rr_stats_items', 'rr_stats_items', 'rr'),
            arsenal_events_text_field('field_rr_resources_title', 'Resources Title', 'rr_resources_title'),
            arsenal_events_textarea_field('field_rr_resources_description', 'Resources Description', 'rr_resources_description'),
            ...arsenal_events_cta_fields('rr'),
        ],
        'location' => arsenal_events_page_slug_location('races-and-results'),
        'menu_order' => 10,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'description' => 'Races & Results page-specific structured fields for visible results, stats, resources, and CTA copy.',
        'show_in_rest' => 1,
    ];
}

function arsenal_events_get_page_blocks_group(): array
{
    $group = [
        'key' => 'group_page_blocks',
        'title' => 'Page Blocks',
        'fields' => [
            [
                'key' => 'field_page_blocks',
                'label' => 'Page Blocks',
                'name' => 'page_blocks',
                'type' => 'flexible_content',
                'instructions' => 'Build page content using flexible block layouts',
                'layouts' => [
                    // Header Block
                    [
                        'key' => 'layout_header_block',
                        'name' => 'header_block',
                        'label' => 'Header',
                        'display' => 'block',
                        'sub_fields' => [
                            [
                                'key' => 'field_header_nav_links',
                                'label' => 'Navigation Links',
                                'name' => 'header_nav_links',
                                'type' => 'repeater',
                                'layout' => 'table',
                                'button_label' => 'Add Link',
                                'sub_fields' => [
                                    [
                                        'key' => 'field_header_nav_label',
                                        'label' => 'Label',
                                        'name' => 'label',
                                        'type' => 'text',
                                        'wrapper' => ['width' => '50'],
                                    ],
                                    [
                                        'key' => 'field_header_nav_href',
                                        'label' => 'URL',
                                        'name' => 'href',
                                        'type' => 'url',
                                        'wrapper' => ['width' => '50'],
                                    ],
                                ],
                            ],
                            [
                                'key' => 'field_header_cta_label',
                                'label' => 'CTA Button Label',
                                'name' => 'header_cta_label',
                                'type' => 'text',
                                'wrapper' => ['width' => '50'],
                            ],
                            [
                                'key' => 'field_header_cta_href',
                                'label' => 'CTA Button URL',
                                'name' => 'header_cta_href',
                                'type' => 'url',
                                'wrapper' => ['width' => '50'],
                            ],
                        ],
                    ],
                    // Hero Block
                    [
                        'key' => 'layout_hero_block',
                        'name' => 'hero_block',
                        'label' => 'Hero',
                        'display' => 'block',
                        'sub_fields' => [
                            [
                                'key' => 'field_hero_headline',
                                'label' => 'Headline',
                                'name' => 'hero_headline',
                                'type' => 'text',
                            ],
                            [
                                'key' => 'field_hero_subtitle',
                                'label' => 'Subtitle',
                                'name' => 'hero_subtitle',
                                'type' => 'textarea',
                                'rows' => 2,
                            ],
                            [
                                'key' => 'field_hero_background_image',
                                'label' => 'Background Image',
                                'name' => 'hero_background_image',
                                'type' => 'image',
                                'return_format' => 'array',
                                'preview_size' => 'medium',
                            ],
                            [
                                'key' => 'field_hero_cta_label',
                                'label' => 'CTA Button Label',
                                'name' => 'hero_cta_label',
                                'type' => 'text',
                                'wrapper' => ['width' => '50'],
                            ],
                            [
                                'key' => 'field_hero_cta_href',
                                'label' => 'CTA Button URL',
                                'name' => 'hero_cta_href',
                                'type' => 'url',
                                'wrapper' => ['width' => '50'],
                            ],
                            [
                                'key' => 'field_hero_secondary_cta_label',
                                'label' => 'Secondary CTA Button Label',
                                'name' => 'hero_secondary_cta_label',
                                'type' => 'text',
                                'wrapper' => ['width' => '50'],
                            ],
                            [
                                'key' => 'field_hero_secondary_cta_href',
                                'label' => 'Secondary CTA Button URL',
                                'name' => 'hero_secondary_cta_href',
                                'type' => 'url',
                                'wrapper' => ['width' => '50'],
                            ],
                        ],
                    ],
                    // Services Block
                    [
                        'key' => 'layout_services_block',
                        'name' => 'services_block',
                        'label' => 'Services',
                        'display' => 'block',
                        'sub_fields' => [
                            [
                                'key' => 'field_services_title',
                                'label' => 'Title',
                                'name' => 'services_title',
                                'type' => 'text',
                            ],
                            [
                                'key' => 'field_services_description',
                                'label' => 'Description',
                                'name' => 'services_description',
                                'type' => 'textarea',
                                'rows' => 2,
                            ],
                            [
                                'key' => 'field_services_grid',
                                'label' => 'Services',
                                'name' => 'services_grid',
                                'type' => 'repeater',
                                'layout' => 'block',
                                'button_label' => 'Add Service',
                                'sub_fields' => [
                                    [
                                        'key' => 'field_service_title',
                                        'label' => 'Title',
                                        'name' => 'service_title',
                                        'type' => 'text',
                                    ],
                                    [
                                        'key' => 'field_service_description',
                                        'label' => 'Description',
                                        'name' => 'service_description',
                                        'type' => 'textarea',
                                        'rows' => 2,
                                    ],
                                    [
                                        'key' => 'field_service_icon',
                                        'label' => 'Icon',
                                        'name' => 'service_icon',
                                        'type' => 'image',
                                        'return_format' => 'array',
                                        'preview_size' => 'thumbnail',
                                    ],
                                    [
                                        'key' => 'field_service_link',
                                        'label' => 'Link',
                                        'name' => 'service_link',
                                        'type' => 'link',
                                        'return_format' => 'array',
                                    ],
                                    [
                                        'key' => 'field_service_category',
                                        'label' => 'Service Category',
                                        'name' => 'service_category',
                                        'type' => 'select',
                                        'choices' => [
                                            'director' => 'For Race Directors',
                                            'runner' => 'For Runners',
                                        ],
                                    ],
                                    [
                                        'key' => 'field_service_card_image',
                                        'label' => 'Card Image',
                                        'name' => 'service_card_image',
                                        'type' => 'image',
                                        'return_format' => 'array',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    // Upcoming Races Block
                    [
                        'key' => 'layout_upcoming_races_block',
                        'name' => 'upcoming_races_block',
                        'label' => 'Upcoming Races',
                        'display' => 'block',
                        'sub_fields' => [
                            [
                                'key' => 'field_races_title',
                                'label' => 'Title',
                                'name' => 'races_title',
                                'type' => 'text',
                            ],
                            [
                                'key' => 'field_races_description',
                                'label' => 'Description',
                                'name' => 'races_description',
                                'type' => 'textarea',
                                'rows' => 2,
                            ],
                            [
                                'key' => 'field_races_carousel',
                                'label' => 'Races',
                                'name' => 'races_carousel',
                                'type' => 'repeater',
                                'layout' => 'block',
                                'button_label' => 'Add Race',
                                'sub_fields' => [
                                    [
                                        'key' => 'field_race_title',
                                        'label' => 'Title',
                                        'name' => 'race_title',
                                        'type' => 'text',
                                    ],
                                    [
                                        'key' => 'field_race_date',
                                        'label' => 'Date',
                                        'name' => 'race_date',
                                        'type' => 'date_picker',
                                        'display_format' => 'F j, Y',
                                        'return_format' => 'Y-m-d',
                                        'wrapper' => ['width' => '50'],
                                    ],
                                    [
                                        'key' => 'field_race_location',
                                        'label' => 'Location',
                                        'name' => 'race_location',
                                        'type' => 'text',
                                        'wrapper' => ['width' => '50'],
                                    ],
                                    [
                                        'key' => 'field_race_image',
                                        'label' => 'Image',
                                        'name' => 'race_image',
                                        'type' => 'image',
                                        'return_format' => 'array',
                                        'preview_size' => 'medium',
                                    ],
                                    [
                                        'key' => 'field_race_link',
                                        'label' => 'Link',
                                        'name' => 'race_link',
                                        'type' => 'link',
                                        'return_format' => 'array',
                                    ],
                                    [
                                        'key' => 'field_race_background_image',
                                        'label' => 'Background Image',
                                        'name' => 'race_background_image',
                                        'type' => 'image',
                                        'return_format' => 'array',
                                    ],
                                    [
                                        'key' => 'field_race_logo_image',
                                        'label' => 'Logo Image',
                                        'name' => 'race_logo_image',
                                        'type' => 'image',
                                        'return_format' => 'array',
                                    ],
                                    [
                                        'key' => 'field_is_arsenal_event',
                                        'label' => 'Arsenal Events Race',
                                        'name' => 'is_arsenal_event',
                                        'type' => 'true_false',
                                        'default_value' => 0,
                                    ],
                                    [
                                        'key' => 'field_registration_button_label',
                                        'label' => 'Registration Button Label',
                                        'name' => 'registration_button_label',
                                        'type' => 'text',
                                        'default_value' => 'Register Now',
                                    ],
                                    [
                                        'key' => 'field_registration_button_url',
                                        'label' => 'Registration Button URL',
                                        'name' => 'registration_button_url',
                                        'type' => 'url',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    // Resources Block
                    [
                        'key' => 'layout_resources_block',
                        'name' => 'resources_block',
                        'label' => 'Resources',
                        'display' => 'block',
                        'sub_fields' => [
                            [
                                'key' => 'field_resources_title',
                                'label' => 'Title',
                                'name' => 'resources_title',
                                'type' => 'text',
                            ],
                            [
                                'key' => 'field_resources_description',
                                'label' => 'Description',
                                'name' => 'resources_description',
                                'type' => 'textarea',
                                'rows' => 2,
                            ],
                            [
                                'key' => 'field_resources_grid',
                                'label' => 'Resources',
                                'name' => 'resources_grid',
                                'type' => 'repeater',
                                'layout' => 'block',
                                'button_label' => 'Add Resource',
                                'sub_fields' => [
                                    [
                                        'key' => 'field_resource_title',
                                        'label' => 'Title',
                                        'name' => 'resource_title',
                                        'type' => 'text',
                                    ],
                                    [
                                        'key' => 'field_resource_description',
                                        'label' => 'Description',
                                        'name' => 'resource_description',
                                        'type' => 'textarea',
                                        'rows' => 2,
                                    ],
                                    [
                                        'key' => 'field_resource_image',
                                        'label' => 'Image',
                                        'name' => 'resource_image',
                                        'type' => 'image',
                                        'return_format' => 'array',
                                        'preview_size' => 'medium',
                                    ],
                                    [
                                        'key' => 'field_resource_link',
                                        'label' => 'Link',
                                        'name' => 'resource_link',
                                        'type' => 'link',
                                        'return_format' => 'array',
                                    ],
                                    [
                                        'key' => 'field_resource_category',
                                        'label' => 'Category',
                                        'name' => 'resource_category',
                                        'type' => 'select',
                                        'choices' => [
                                            'for_runners' => 'For Runners',
                                            'for_directors' => 'For Race Directors',
                                        ],
                                    ],
                                    [
                                        'key' => 'field_resource_category_icon',
                                        'label' => 'Category Icon',
                                        'name' => 'resource_category_icon',
                                        'type' => 'image',
                                        'return_format' => 'array',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    // CTA Block
                    [
                        'key' => 'layout_cta_block',
                        'name' => 'cta_block',
                        'label' => 'CTA',
                        'display' => 'block',
                        'sub_fields' => [
                            [
                                'key' => 'field_cta_headline',
                                'label' => 'Headline',
                                'name' => 'cta_headline',
                                'type' => 'text',
                            ],
                            [
                                'key' => 'field_cta_description',
                                'label' => 'Description',
                                'name' => 'cta_description',
                                'type' => 'textarea',
                                'rows' => 2,
                            ],
                            [
                                'key' => 'field_cta_label',
                                'label' => 'Button Label',
                                'name' => 'cta_label',
                                'type' => 'text',
                                'wrapper' => ['width' => '50'],
                            ],
                            [
                                'key' => 'field_cta_href',
                                'label' => 'Button URL',
                                'name' => 'cta_href',
                                'type' => 'url',
                                'wrapper' => ['width' => '50'],
                            ],
                            [
                                'key' => 'field_cta_background_image',
                                'label' => 'Background Image',
                                'name' => 'cta_background_image',
                                'type' => 'image',
                                'return_format' => 'array',
                                'preview_size' => 'medium',
                            ],
                        ],
                    ],
                    // Testimonials & Stats Block (Combined)
                    [
                        'key' => 'layout_testimonials_stats_block',
                        'name' => 'testimonials_stats_block',
                        'label' => 'Testimonials & Stats',
                        'display' => 'block',
                        'sub_fields' => [
                            [
                                'key' => 'field_testimonials_headline',
                                'label' => 'Headline',
                                'name' => 'testimonials_headline',
                                'type' => 'text',
                                'default_value' => "We're Built for Race Day",
                            ],
                            [
                                'key' => 'field_testimonials_description',
                                'label' => 'Description',
                                'name' => 'testimonials_description',
                                'type' => 'textarea',
                                'rows' => 2,
                            ],
                            [
                                'key' => 'field_testimonials_list',
                                'label' => 'Testimonials',
                                'name' => 'testimonials_list',
                                'type' => 'repeater',
                                'layout' => 'table',
                                'button_label' => 'Add Testimonial',
                                'sub_fields' => [
                                    [
                                        'key' => 'field_testimonial_type',
                                        'label' => 'Type',
                                        'name' => 'testimonial_type',
                                        'type' => 'select',
                                        'choices' => [
                                            'director' => 'Race Director',
                                            'runner' => 'Runner',
                                        ],
                                    ],
                                    [
                                        'key' => 'field_testimonial_label',
                                        'label' => 'Label',
                                        'name' => 'testimonial_label',
                                        'type' => 'text',
                                    ],
                                    [
                                        'key' => 'field_testimonial_quote',
                                        'label' => 'Quote',
                                        'name' => 'testimonial_quote',
                                        'type' => 'textarea',
                                        'rows' => 3,
                                    ],
                                    [
                                        'key' => 'field_testimonial_attribution',
                                        'label' => 'Attribution',
                                        'name' => 'testimonial_attribution',
                                        'type' => 'text',
                                    ],
                                ],
                            ],
                            [
                                'key' => 'field_stats_list',
                                'label' => 'Statistics',
                                'name' => 'stats_list',
                                'type' => 'repeater',
                                'layout' => 'table',
                                'max' => 3,
                                'button_label' => 'Add Stat',
                                'sub_fields' => [
                                    [
                                        'key' => 'field_stat_icon',
                                        'label' => 'Icon',
                                        'name' => 'stat_icon',
                                        'type' => 'image',
                                        'return_format' => 'array',
                                    ],
                                    [
                                        'key' => 'field_stat_value',
                                        'label' => 'Value',
                                        'name' => 'stat_value',
                                        'type' => 'text',
                                    ],
                                    [
                                        'key' => 'field_stat_label',
                                        'label' => 'Label',
                                        'name' => 'stat_label',
                                        'type' => 'text',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    // Footer Block
                    [
                        'key' => 'layout_footer_block',
                        'name' => 'footer_block',
                        'label' => 'Footer',
                        'display' => 'block',
                        'sub_fields' => [
                            [
                                'key' => 'field_footer_nav_links',
                                'label' => 'Navigation Links',
                                'name' => 'footer_nav_links',
                                'type' => 'repeater',
                                'layout' => 'table',
                                'button_label' => 'Add Link',
                                'sub_fields' => [
                                    [
                                        'key' => 'field_footer_nav_label',
                                        'label' => 'Label',
                                        'name' => 'label',
                                        'type' => 'text',
                                        'wrapper' => ['width' => '50'],
                                    ],
                                    [
                                        'key' => 'field_footer_nav_href',
                                        'label' => 'URL',
                                        'name' => 'href',
                                        'type' => 'url',
                                        'wrapper' => ['width' => '50'],
                                    ],
                                ],
                            ],
                            [
                                'key' => 'field_footer_info_text',
                                'label' => 'Info Text',
                                'name' => 'footer_info_text',
                                'type' => 'textarea',
                                'rows' => 2,
                            ],
                            [
                                'key' => 'field_footer_copyright_text',
                                'label' => 'Copyright Text',
                                'name' => 'footer_copyright_text',
                                'type' => 'text',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'page',
                ],
            ],
        ],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'description' => 'Flexible content blocks for pages',
        'show_in_rest' => 1,
    ];

    // Keep page authoring limited to layouts the frontend currently renders.
    $group['fields'][0]['layouts'] = array_values(array_filter(
        $group['fields'][0]['layouts'],
        static fn(array $layout): bool => in_array($layout['name'] ?? '', ['hero_block', 'services_block'], true)
    ));

    return $group;
}

function arsenal_events_get_race_details_group(): array
{
    return [
        'key' => 'group_race_details',
        'title' => 'Race Details',
        'fields' => [
            [
                'key' => 'field_race_date_picker',
                'label' => 'Race Date',
                'name' => 'race_date',
                'type' => 'date_picker',
                'display_format' => 'F j, Y',
                'return_format' => 'Y-m-d',
            ],
            [
                'key' => 'field_race_location_text',
                'label' => 'Location',
                'name' => 'race_location',
                'type' => 'text',
            ],
            [
                'key' => 'field_race_registration_link',
                'label' => 'Registration Link',
                'name' => 'race_registration_link',
                'type' => 'url',
            ],
            [
                'key' => 'field_race_results_link',
                'label' => 'Results Link',
                'name' => 'race_results_link',
                'type' => 'url',
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'race',
                ],
            ],
        ],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'description' => 'Race-specific details and metadata',
        'show_in_rest' => 1,
    ];
}

function arsenal_events_get_resource_details_group(): array
{
    return [
        'key' => 'group_resource_details',
        'title' => 'Resource Details',
        'fields' => [
            [
                'key' => 'field_resource_download_link',
                'label' => 'Download File',
                'name' => 'resource_download_link',
                'type' => 'file',
                'return_format' => 'array',
            ],
            [
                'key' => 'field_resource_external_link',
                'label' => 'External Link',
                'name' => 'resource_external_link',
                'type' => 'url',
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'resource',
                ],
            ],
        ],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'description' => 'Resource-specific details and metadata',
        'show_in_rest' => 1,
    ];
}

/**
 * Race Directors Page - Hero Section Fields
 */
function arsenal_events_get_rd_hero_group(): array
{
    return [
        'key' => 'group_rd_hero',
        'title' => 'Race Directors - Hero Section',
        'fields' => [
            [
                'key' => 'field_rd_hero_title',
                'label' => 'Title',
                'name' => 'rd_hero_title',
                'type' => 'text',
                'instructions' => 'Main headline for Race Directors hero section',
                'required' => 0,
            ],
            [
                'key' => 'field_rd_hero_subtitle',
                'label' => 'Subtitle',
                'name' => 'rd_hero_subtitle',
                'type' => 'text',
                'instructions' => 'Secondary headline',
                'required' => 0,
            ],
            [
                'key' => 'field_rd_hero_description',
                'label' => 'Description',
                'name' => 'rd_hero_description',
                'type' => 'textarea',
                'instructions' => 'Hero section description text',
                'required' => 0,
                'rows' => 3,
            ],
            [
                'key' => 'field_rd_hero_primary_button_text',
                'label' => 'Primary Button Text',
                'name' => 'rd_hero_primary_button_text',
                'type' => 'text',
                'wrapper' => ['width' => '50'],
            ],
            [
                'key' => 'field_rd_hero_primary_button_href',
                'label' => 'Primary Button URL',
                'name' => 'rd_hero_primary_button_href',
                'type' => 'url',
                'wrapper' => ['width' => '50'],
            ],
            [
                'key' => 'field_rd_hero_secondary_button_text',
                'label' => 'Secondary Button Text',
                'name' => 'rd_hero_secondary_button_text',
                'type' => 'text',
                'wrapper' => ['width' => '50'],
            ],
            [
                'key' => 'field_rd_hero_secondary_button_href',
                'label' => 'Secondary Button URL',
                'name' => 'rd_hero_secondary_button_href',
                'type' => 'url',
                'wrapper' => ['width' => '50'],
            ],
            [
                'key' => 'field_rd_hero_image',
                'label' => 'Hero Image',
                'name' => 'rd_hero_image',
                'type' => 'image',
                'return_format' => 'array',
                'preview_size' => 'medium',
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'page_template',
                    'operator' => '==',
                    'value' => 'for-race-directors',
                ],
            ],
        ],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'description' => 'Hero section content for Race Directors page',
        'show_in_rest' => 1,
    ];
}

/**
 * Race Directors Page - Services Section Fields
 */
function arsenal_events_get_rd_services_group(): array
{
    return [
        'key' => 'group_rd_services',
        'title' => 'Race Directors - Services Section',
        'fields' => [
            [
                'key' => 'field_rd_services_title',
                'label' => 'Section Title',
                'name' => 'rd_services_title',
                'type' => 'text',
                'instructions' => 'Main title for services section',
                'required' => 0,
            ],
            [
                'key' => 'field_rd_services_subtitle',
                'label' => 'Section Subtitle',
                'name' => 'rd_services_subtitle',
                'type' => 'text',
                'instructions' => 'Subtitle for services section',
                'required' => 0,
            ],
            [
                'key' => 'field_rd_services_list',
                'label' => 'Services',
                'name' => 'rd_services_list',
                'type' => 'repeater',
                'instructions' => 'Add race director services',
                'layout' => 'block',
                'button_label' => 'Add Service',
                'sub_fields' => [
                    [
                        'key' => 'field_rd_service_title',
                        'label' => 'Service Title',
                        'name' => 'title',
                        'type' => 'text',
                        'required' => 0,
                    ],
                    [
                        'key' => 'field_rd_service_body',
                        'label' => 'Service Description',
                        'name' => 'body',
                        'type' => 'textarea',
                        'rows' => 3,
                        'required' => 0,
                    ],
                    [
                        'key' => 'field_rd_service_image',
                        'label' => 'Service Image',
                        'name' => 'image',
                        'type' => 'image',
                        'return_format' => 'array',
                        'preview_size' => 'medium',
                    ],
                    [
                        'key' => 'field_rd_service_icon',
                        'label' => 'Service Icon',
                        'name' => 'icon',
                        'type' => 'image',
                        'return_format' => 'array',
                        'preview_size' => 'thumbnail',
                    ],
                ],
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'page_template',
                    'operator' => '==',
                    'value' => 'for-race-directors',
                ],
            ],
        ],
        'menu_order' => 1,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'description' => 'Services section content for Race Directors page',
        'show_in_rest' => 1,
    ];
}

/**
 * Race Directors Page - Gallery Section Fields
 */
function arsenal_events_get_rd_gallery_group(): array
{
    return [
        'key' => 'group_rd_gallery',
        'title' => 'Race Directors - Gallery Section',
        'fields' => [
            [
                'key' => 'field_rd_gallery_title',
                'label' => 'Gallery Title',
                'name' => 'rd_gallery_title',
                'type' => 'text',
                'instructions' => 'Title for the gallery section',
                'required' => 0,
            ],
            [
                'key' => 'field_rd_gallery_items',
                'label' => 'Gallery Items',
                'name' => 'rd_gallery_items',
                'type' => 'repeater',
                'instructions' => 'Add gallery images',
                'layout' => 'block',
                'button_label' => 'Add Image',
                'sub_fields' => [
                    [
                        'key' => 'field_rd_gallery_item_image',
                        'label' => 'Image',
                        'name' => 'image',
                        'type' => 'image',
                        'return_format' => 'array',
                        'preview_size' => 'medium',
                        'required' => 0,
                    ],
                    [
                        'key' => 'field_rd_gallery_item_caption',
                        'label' => 'Caption',
                        'name' => 'caption',
                        'type' => 'text',
                        'required' => 0,
                    ],
                    [
                        'key' => 'field_rd_gallery_item_classname',
                        'label' => 'CSS Class Name',
                        'name' => 'className',
                        'type' => 'text',
                        'instructions' => 'Optional CSS class for styling',
                        'required' => 0,
                    ],
                ],
            ],
            [
                'key' => 'field_rd_gallery_topography_bg',
                'label' => 'Topography Background Image',
                'name' => 'rd_gallery_topography_bg',
                'type' => 'image',
                'return_format' => 'array',
                'preview_size' => 'medium',
            ],
            [
                'key' => 'field_rd_gallery_chevron',
                'label' => 'Chevron Icon',
                'name' => 'rd_gallery_chevron',
                'type' => 'image',
                'return_format' => 'array',
                'preview_size' => 'thumbnail',
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'page_template',
                    'operator' => '==',
                    'value' => 'for-race-directors',
                ],
            ],
        ],
        'menu_order' => 2,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'description' => 'Gallery section content for Race Directors page',
        'show_in_rest' => 1,
    ];
}

/**
 * Races & Results Page - Hero Section Fields
 */
function arsenal_events_get_rr_hero_group(): array
{
    return [
        'key' => 'group_rr_hero',
        'title' => 'Races & Results - Hero Section',
        'fields' => [
            [
                'key' => 'field_rr_hero_title',
                'label' => 'Title',
                'name' => 'rr_hero_title',
                'type' => 'text',
                'instructions' => 'Main headline for Races & Results hero section',
                'required' => 0,
            ],
            [
                'key' => 'field_rr_hero_subtitle',
                'label' => 'Subtitle',
                'name' => 'rr_hero_subtitle',
                'type' => 'text',
                'instructions' => 'Secondary headline',
                'required' => 0,
            ],
            [
                'key' => 'field_rr_hero_description',
                'label' => 'Description',
                'name' => 'rr_hero_description',
                'type' => 'textarea',
                'instructions' => 'Hero section description text',
                'required' => 0,
                'rows' => 3,
            ],
            [
                'key' => 'field_rr_hero_primary_button_text',
                'label' => 'Primary Button Text',
                'name' => 'rr_hero_primary_button_text',
                'type' => 'text',
                'wrapper' => ['width' => '50'],
            ],
            [
                'key' => 'field_rr_hero_primary_button_href',
                'label' => 'Primary Button URL',
                'name' => 'rr_hero_primary_button_href',
                'type' => 'url',
                'wrapper' => ['width' => '50'],
            ],
            [
                'key' => 'field_rr_hero_secondary_button_text',
                'label' => 'Secondary Button Text',
                'name' => 'rr_hero_secondary_button_text',
                'type' => 'text',
                'wrapper' => ['width' => '50'],
            ],
            [
                'key' => 'field_rr_hero_secondary_button_href',
                'label' => 'Secondary Button URL',
                'name' => 'rr_hero_secondary_button_href',
                'type' => 'url',
                'wrapper' => ['width' => '50'],
            ],
            [
                'key' => 'field_rr_hero_image',
                'label' => 'Hero Image',
                'name' => 'rr_hero_image',
                'type' => 'image',
                'return_format' => 'array',
                'preview_size' => 'medium',
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'page_template',
                    'operator' => '==',
                    'value' => 'races-and-results',
                ],
            ],
        ],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'description' => 'Hero section content for Races & Results page',
        'show_in_rest' => 1,
    ];
}
