<?php

if (!function_exists('meza_get_shared_project_acf_options_pages')) {
    function meza_get_shared_project_acf_options_pages(): array
    {
        $pages = [
            [
                'page_title'      => meza_get_business_information_page_title(),
                'menu_title'      => 'Information',
                'menu_slug'       => 'business-information',
                'parent_slug'     => meza_get_shared_project_acf_options_page_parent_slug('business-information'),
                'capability'      => meza_get_shared_project_acf_options_page_capability('business-information'),
                'redirect'        => false,
                'update_button'   => 'Update',
                'updated_message' => 'Settings Updated',
                'autoload'        => false,
            ],
            [
                'page_title'      => meza_get_branding_page_title(),
                'menu_title'      => 'Branding',
                'menu_slug'       => 'branding',
                'parent_slug'     => meza_get_shared_project_acf_options_page_parent_slug('branding'),
                'capability'      => meza_get_shared_project_acf_options_page_capability('branding'),
                'redirect'        => false,
                'update_button'   => 'Update',
                'updated_message' => 'Options Updated',
                'autoload'        => false,
            ],
            [
                'page_title'      => meza_get_content_structure_page_title(),
                'menu_title'      => 'Content Model',
                'menu_slug'       => 'content-model',
                'parent_slug'     => meza_get_shared_project_acf_options_page_parent_slug('content-model'),
                'capability'      => meza_get_shared_project_acf_options_page_capability('content-model'),
                'redirect'        => false,
                'update_button'   => 'Update',
                'updated_message' => 'Settings Updated',
                'autoload'        => false,
            ],
            [
                'page_title'      => meza_get_crm_page_title(),
                'menu_title'      => 'CRM Integration',
                'menu_slug'       => 'crm',
                'parent_slug'     => meza_get_shared_project_acf_options_page_parent_slug('crm'),
                'capability'      => meza_get_shared_project_acf_options_page_capability('crm'),
                'redirect'        => false,
                'update_button'   => 'Update',
                'updated_message' => 'Settings Updated',
                'autoload'        => false,
            ],
            [
                'page_title'      => meza_get_ecommerce_page_title(),
                'menu_title'      => 'E-Commerce',
                'menu_slug'       => 'ecommerce',
                'parent_slug'     => meza_get_shared_project_acf_options_page_parent_slug('ecommerce'),
                'capability'      => meza_get_shared_project_acf_options_page_capability('ecommerce'),
                'redirect'        => false,
                'update_button'   => 'Update',
                'updated_message' => 'Settings Updated',
                'autoload'        => false,
            ],
        ];

        $pages[] = [
            'page_title'      => 'Conference',
            'menu_title'      => 'Conference',
            'menu_slug'       => 'conference',
            'parent_slug'     => 'none',
            'capability'      => 'manage_options',
            'redirect'        => false,
            'icon_url'        => 'dashicons-tickets-alt',
            'update_button'   => 'Update Conference',
            'updated_message' => 'Conference Updated',
            'autoload'        => false,
        ];

        $pages[] = [
            'page_title'      => 'Schedule',
            'menu_title'      => 'Schedule',
            'menu_slug'       => 'conference-schedule',
            'parent_slug'     => 'conference',
            'capability'      => 'manage_options',
            'position'        => 2,
            'redirect'        => false,
            'update_button'   => 'Update Schedule',
            'updated_message' => 'Schedule Updated',
            'autoload'        => false,
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
            'event-info' => 'conference',
            'content-structure' => 'content-model',
        ];
    }
}
