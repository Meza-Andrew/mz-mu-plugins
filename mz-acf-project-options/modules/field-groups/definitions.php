<?php

if (!function_exists('meza_get_shared_project_acf_field_groups')) {
    $meza_acf_field_group_definition_modules = [
        __DIR__ . '/definitions/business-information.php',
        __DIR__ . '/definitions/branding-crm-conference.php',
        __DIR__ . '/definitions/content-model-definitions.php',
        __DIR__ . '/definitions/section-field-groups.php',
        __DIR__ . '/definitions/content-field-groups.php',
        __DIR__ . '/definitions/runtime.php',
        __DIR__ . '/definitions/registry.php',
    ];

    foreach ($meza_acf_field_group_definition_modules as $meza_acf_field_group_definition_module) {
        if (is_readable($meza_acf_field_group_definition_module)) {
            require_once $meza_acf_field_group_definition_module;
        }
    }
}
