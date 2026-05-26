<?php

$meza_site_documentation_modules = [
    __DIR__ . '/modules/core.php',
    __DIR__ . '/modules/key-links.php',
    __DIR__ . '/modules/content-inventory.php',
    __DIR__ . '/modules/registry.php',
];

foreach ($meza_site_documentation_modules as $meza_site_documentation_module) {
    if (is_readable($meza_site_documentation_module)) {
        require_once $meza_site_documentation_module;
    }
}
