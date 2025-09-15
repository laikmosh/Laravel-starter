#!/usr/bin/env php
<?php
/**
 * Laravel Starter Kit Installer
 * This script installs Laravel and applies starter kit customizations
 */

// Simple autoloader for our installer
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/.starter-kit/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

require __DIR__ . '/.starter-kit/Installer.php';
$installer = new StarterKit\Installer();
$installer->run();