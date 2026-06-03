<?php

$meza_site_documentation_content_inventory_modules = [
    __DIR__ . '/content-inventory/pages.php',
    __DIR__ . '/content-inventory/templates.php',
    __DIR__ . '/content-inventory/content-types.php',
];

foreach ($meza_site_documentation_content_inventory_modules as $meza_site_documentation_content_inventory_module) {
    if (is_readable($meza_site_documentation_content_inventory_module)) {
        require_once $meza_site_documentation_content_inventory_module;
    }
}
