<?php

$meza_acf_field_group_modules = [
    __DIR__ . '/field-groups/helpers.php',
    __DIR__ . '/field-groups/media-permalinks.php',
    __DIR__ . '/field-groups/options-pages.php',
    __DIR__ . '/field-groups/definitions.php',
];

foreach ($meza_acf_field_group_modules as $meza_acf_field_group_module) {
    if (is_readable($meza_acf_field_group_module)) {
        require_once $meza_acf_field_group_module;
    }
}
