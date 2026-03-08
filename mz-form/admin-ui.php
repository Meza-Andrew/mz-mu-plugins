<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function () {
    add_management_page(
        'MZ Form Schema',
        'MZ Form Schema',
        'manage_options',
        'mzf-schema',
        'mzf_render_schema_admin_page'
    );
});

if (!function_exists('mzf_render_schema_admin_page')) {
    function mzf_render_schema_admin_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $core_fields = function_exists('mzf_core_fields') ? mzf_core_fields() : [];
        $accepted_fields = function_exists('mzf_fields') ? mzf_fields() : [];
        $slug_registry = function_exists('mzf_slug_registry') ? mzf_slug_registry() : [];
        $labels = function_exists('mzf_registry_field_labels') ? mzf_registry_field_labels() : [];

        echo '<div class="wrap">';
        echo '<h1>MZ Form Schema</h1>';
        echo '<p>Use this page to review the current field contract used by MZ Form.</p>';

        echo '<h2>Core Fields</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Field Key</th><th>Display Label</th></tr></thead><tbody>';
        foreach ($core_fields as $field) {
            $field = (string) $field;
            $label = (string) ($labels[$field] ?? $field);
            echo '<tr><td><code>' . esc_html($field) . '</code></td><td>' . esc_html($label) . '</td></tr>';
        }
        if (empty($core_fields)) {
            echo '<tr><td colspan="2"><em>No core fields configured.</em></td></tr>';
        }
        echo '</tbody></table>';

        echo '<h2 style="margin-top:24px;">Required Fields By Form Slug</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Form Slug</th><th>Required Fields</th></tr></thead><tbody>';
        foreach ($slug_registry as $slug => $profile) {
            $required = (isset($profile['required']) && is_array($profile['required'])) ? $profile['required'] : [];
            $required = array_values(array_filter(array_map('strval', $required)));
            echo '<tr>';
            echo '<td><code>' . esc_html((string) $slug) . '</code></td>';
            echo '<td>';
            if (empty($required)) {
                echo '<em>None</em>';
            } else {
                $items = array_map(static fn($f) => '<code>' . esc_html($f) . '</code>', $required);
                echo implode(', ', $items);
            }
            echo '</td>';
            echo '</tr>';
        }
        if (empty($slug_registry)) {
            echo '<tr><td colspan="2"><em>No slugs registered.</em></td></tr>';
        }
        echo '</tbody></table>';

        echo '<h2 style="margin-top:24px;">Accepted Payload Fields</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Field Key</th><th>Display Label</th></tr></thead><tbody>';
        foreach ($accepted_fields as $field) {
            $field = (string) $field;
            $label = (string) ($labels[$field] ?? $field);
            echo '<tr><td><code>' . esc_html($field) . '</code></td><td>' . esc_html($label) . '</td></tr>';
        }
        if (empty($accepted_fields)) {
            echo '<tr><td colspan="2"><em>No accepted fields configured.</em></td></tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }
}

