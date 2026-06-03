<?php

$meza_acf_project_options_modules = [
    __DIR__ . '/modules/business-information.php',
    __DIR__ . '/modules/field-groups.php',
    __DIR__ . '/modules/managed-definitions.php',
    __DIR__ . '/modules/admin-ui.php',
    __DIR__ . '/modules/registration.php',
];

foreach ($meza_acf_project_options_modules as $meza_acf_project_options_module) {
    if (is_readable($meza_acf_project_options_module)) {
        require_once $meza_acf_project_options_module;
    }
}
