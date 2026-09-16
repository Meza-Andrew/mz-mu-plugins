<?php

$root = dirname(__DIR__, 2);

function meza_shared_arsenal_isolation_assert($condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

$loader = file_get_contents($root . '/mz-loader.php');
$definitions_loader = file_get_contents(__DIR__ . '/../mz-acf-project-options/modules/field-groups/definitions.php');
$registry = file_get_contents(__DIR__ . '/../mz-acf-project-options/modules/field-groups/definitions/registry.php');

meza_shared_arsenal_isolation_assert(is_string($loader), 'Shared loader should be readable');
meza_shared_arsenal_isolation_assert(strpos($loader, 'arsenal-events-cpt.php') === false, 'Shared loader should not load Arsenal CPT runtime');
meza_shared_arsenal_isolation_assert(strpos($loader, 'mz-rest-api.php') === false, 'Shared loader should not load Arsenal REST runtime');

meza_shared_arsenal_isolation_assert(is_string($definitions_loader), 'Shared ACF definitions loader should be readable');
meza_shared_arsenal_isolation_assert(strpos($definitions_loader, 'definitions/arsenal-events.php') === false, 'Shared ACF definitions loader should not load Arsenal field groups');
meza_shared_arsenal_isolation_assert(strpos($definitions_loader, 'definitions/arsenal-events-media.php') === false, 'Shared ACF definitions loader should not load Arsenal media groups');

meza_shared_arsenal_isolation_assert(is_string($registry), 'Shared ACF registry should be readable');
meza_shared_arsenal_isolation_assert(strpos($registry, 'arsenal_events_get_') === false, 'Shared ACF registry should not append Arsenal field groups directly');

echo "Shared MU-plugin Arsenal isolation tests passed\n";
