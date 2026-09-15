<?php
/**
 * Arsenal Events Custom Post Types & Taxonomies
 *
 * Registers:
 * - race CPT with race_category taxonomy
 * - resource CPT with resource_category taxonomy
 */

// ============================================================================
// RACE CUSTOM POST TYPE
// ============================================================================

function arsenal_events_register_race_cpt() {
    $labels = [
        'name'                  => _x('Races', 'Post Type General Name', 'arsenal-events'),
        'singular_name'         => _x('Race', 'Post Type Singular Name', 'arsenal-events'),
        'menu_name'             => __('Races', 'arsenal-events'),
        'name_admin_bar'        => __('Race', 'arsenal-events'),
        'archives'              => __('Race Archives', 'arsenal-events'),
        'attributes'            => __('Race Attributes', 'arsenal-events'),
        'parent_item_colon'     => __('Parent Race:', 'arsenal-events'),
        'all_items'             => __('All Races', 'arsenal-events'),
        'add_new_item'          => __('Add New Race', 'arsenal-events'),
        'add_new'               => __('Add New', 'arsenal-events'),
        'new_item'              => __('New Race', 'arsenal-events'),
        'edit_item'             => __('Edit Race', 'arsenal-events'),
        'update_item'           => __('Update Race', 'arsenal-events'),
        'view_item'             => __('View Race', 'arsenal-events'),
        'view_items'            => __('View Races', 'arsenal-events'),
        'search_items'          => __('Search Race', 'arsenal-events'),
        'not_found'             => __('Not found', 'arsenal-events'),
        'not_found_in_trash'    => __('Not found in Trash', 'arsenal-events'),
        'featured_image'        => __('Featured Image', 'arsenal-events'),
        'set_featured_image'    => __('Set featured image', 'arsenal-events'),
        'remove_featured_image' => __('Remove featured image', 'arsenal-events'),
        'use_featured_image'    => __('Use as featured image', 'arsenal-events'),
        'insert_into_item'      => __('Insert into race', 'arsenal-events'),
        'uploaded_to_this_item' => __('Uploaded to this race', 'arsenal-events'),
        'items_list'            => __('Races list', 'arsenal-events'),
        'items_list_navigation' => __('Races list navigation', 'arsenal-events'),
        'filter_items_list'     => __('Filter races list', 'arsenal-events'),
    ];

    $args = [
        'label'                 => __('Race', 'arsenal-events'),
        'description'           => __('Upcoming races and race results', 'arsenal-events'),
        'labels'                => $labels,
        'supports'              => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
        'taxonomies'            => ['race_category'],
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-calendar-alt',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => 'races',
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'show_in_rest'          => true,
        'rest_base'             => 'races',
        'rest_controller_class' => 'WP_REST_Posts_Controller',
        'capability_type'       => 'post',
    ];

    register_post_type('race', $args);
}
add_action('init', 'arsenal_events_register_race_cpt', 0);

// ============================================================================
// RACE CATEGORY TAXONOMY
// ============================================================================

function arsenal_events_register_race_category_taxonomy() {
    $labels = [
        'name'                       => _x('Race Categories', 'Taxonomy General Name', 'arsenal-events'),
        'singular_name'              => _x('Race Category', 'Taxonomy Singular Name', 'arsenal-events'),
        'menu_name'                  => __('Race Categories', 'arsenal-events'),
        'all_items'                  => __('All Race Categories', 'arsenal-events'),
        'parent_item'                => __('Parent Race Category', 'arsenal-events'),
        'parent_item_colon'          => __('Parent Race Category:', 'arsenal-events'),
        'new_item_name'              => __('New Race Category Name', 'arsenal-events'),
        'add_new_item'               => __('Add New Race Category', 'arsenal-events'),
        'edit_item'                  => __('Edit Race Category', 'arsenal-events'),
        'update_item'                => __('Update Race Category', 'arsenal-events'),
        'view_item'                  => __('View Race Category', 'arsenal-events'),
        'separate_items_with_commas' => __('Separate race categories with commas', 'arsenal-events'),
        'add_or_remove_items'        => __('Add or remove race categories', 'arsenal-events'),
        'choose_from_most_used'      => __('Choose from the most used', 'arsenal-events'),
        'popular_items'              => __('Popular Race Categories', 'arsenal-events'),
        'search_items'               => __('Search Race Categories', 'arsenal-events'),
        'not_found'                  => __('Not Found', 'arsenal-events'),
        'no_terms'                   => __('No race categories', 'arsenal-events'),
        'items_list'                 => __('Race Categories list', 'arsenal-events'),
        'items_list_navigation'      => __('Race Categories list navigation', 'arsenal-events'),
    ];

    $args = [
        'labels'            => $labels,
        'hierarchical'      => true,
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => true,
        'show_tagcloud'     => true,
        'show_in_rest'      => true,
        'rest_base'         => 'race_categories',
    ];

    register_taxonomy('race_category', ['race'], $args);
}
add_action('init', 'arsenal_events_register_race_category_taxonomy', 0);

// ============================================================================
// RESOURCE CUSTOM POST TYPE
// ============================================================================

function arsenal_events_register_resource_cpt() {
    $labels = [
        'name'                  => _x('Resources', 'Post Type General Name', 'arsenal-events'),
        'singular_name'         => _x('Resource', 'Post Type Singular Name', 'arsenal-events'),
        'menu_name'             => __('Resources', 'arsenal-events'),
        'name_admin_bar'        => __('Resource', 'arsenal-events'),
        'archives'              => __('Resource Archives', 'arsenal-events'),
        'attributes'            => __('Resource Attributes', 'arsenal-events'),
        'parent_item_colon'     => __('Parent Resource:', 'arsenal-events'),
        'all_items'             => __('All Resources', 'arsenal-events'),
        'add_new_item'          => __('Add New Resource', 'arsenal-events'),
        'add_new'               => __('Add New', 'arsenal-events'),
        'new_item'              => __('New Resource', 'arsenal-events'),
        'edit_item'             => __('Edit Resource', 'arsenal-events'),
        'update_item'           => __('Update Resource', 'arsenal-events'),
        'view_item'             => __('View Resource', 'arsenal-events'),
        'view_items'            => __('View Resources', 'arsenal-events'),
        'search_items'          => __('Search Resource', 'arsenal-events'),
        'not_found'             => __('Not found', 'arsenal-events'),
        'not_found_in_trash'    => __('Not found in Trash', 'arsenal-events'),
        'featured_image'        => __('Featured Image', 'arsenal-events'),
        'set_featured_image'    => __('Set featured image', 'arsenal-events'),
        'remove_featured_image' => __('Remove featured image', 'arsenal-events'),
        'use_featured_image'    => __('Use as featured image', 'arsenal-events'),
        'insert_into_item'      => __('Insert into resource', 'arsenal-events'),
        'uploaded_to_this_item' => __('Uploaded to this resource', 'arsenal-events'),
        'items_list'            => __('Resources list', 'arsenal-events'),
        'items_list_navigation' => __('Resources list navigation', 'arsenal-events'),
        'filter_items_list'     => __('Filter resources list', 'arsenal-events'),
    ];

    $args = [
        'label'                 => __('Resource', 'arsenal-events'),
        'description'           => __('Downloadable guides, documentation, and resources', 'arsenal-events'),
        'labels'                => $labels,
        'supports'              => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
        'taxonomies'            => ['resource_category'],
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 6,
        'menu_icon'             => 'dashicons-media-document',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => 'resources',
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'show_in_rest'          => true,
        'rest_base'             => 'resources',
        'rest_controller_class' => 'WP_REST_Posts_Controller',
        'capability_type'       => 'post',
    ];

    register_post_type('resource', $args);
}
add_action('init', 'arsenal_events_register_resource_cpt', 0);

// ============================================================================
// RESOURCE CATEGORY TAXONOMY
// ============================================================================

function arsenal_events_register_resource_category_taxonomy() {
    $labels = [
        'name'                       => _x('Resource Categories', 'Taxonomy General Name', 'arsenal-events'),
        'singular_name'              => _x('Resource Category', 'Taxonomy Singular Name', 'arsenal-events'),
        'menu_name'                  => __('Resource Categories', 'arsenal-events'),
        'all_items'                  => __('All Resource Categories', 'arsenal-events'),
        'parent_item'                => __('Parent Resource Category', 'arsenal-events'),
        'parent_item_colon'          => __('Parent Resource Category:', 'arsenal-events'),
        'new_item_name'              => __('New Resource Category Name', 'arsenal-events'),
        'add_new_item'               => __('Add New Resource Category', 'arsenal-events'),
        'edit_item'                  => __('Edit Resource Category', 'arsenal-events'),
        'update_item'                => __('Update Resource Category', 'arsenal-events'),
        'view_item'                  => __('View Resource Category', 'arsenal-events'),
        'separate_items_with_commas' => __('Separate resource categories with commas', 'arsenal-events'),
        'add_or_remove_items'        => __('Add or remove resource categories', 'arsenal-events'),
        'choose_from_most_used'      => __('Choose from the most used', 'arsenal-events'),
        'popular_items'              => __('Popular Resource Categories', 'arsenal-events'),
        'search_items'               => __('Search Resource Categories', 'arsenal-events'),
        'not_found'                  => __('Not Found', 'arsenal-events'),
        'no_terms'                   => __('No resource categories', 'arsenal-events'),
        'items_list'                 => __('Resource Categories list', 'arsenal-events'),
        'items_list_navigation'      => __('Resource Categories list navigation', 'arsenal-events'),
    ];

    $args = [
        'labels'            => $labels,
        'hierarchical'      => true,
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => true,
        'show_tagcloud'     => true,
        'show_in_rest'      => true,
        'rest_base'         => 'resource_categories',
    ];

    register_taxonomy('resource_category', ['resource'], $args);
}
add_action('init', 'arsenal_events_register_resource_category_taxonomy', 0);

// ============================================================================
// OPTIONS PAGE REGISTRATION
// ============================================================================

function arsenal_events_register_options_page() {
    if (function_exists('acf_add_options_page')) {
        acf_add_options_page([
            'page_title' => 'Arsenal Events Settings',
            'menu_title' => 'Arsenal Events',
            'menu_slug'  => 'arsenal-events-settings',
            'capability' => 'manage_options',
            'redirect'   => false,
        ]);
    }
}
add_action('acf/init', 'arsenal_events_register_options_page');
