<?php

/**
 * Shared ACF option pages and field groups we want available on every project.
 * Version: 1.8.204
 */

if (defined('WP_INSTALLING') && WP_INSTALLING) {
    return;
}

require_once __DIR__ . '/mz-acf-project-options/bootstrap.php';
