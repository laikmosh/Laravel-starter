<?php

require __DIR__ . '/vendor/autoload.php';

use function Laravel\Prompts\multiselect;

$packages = [
    'optional' => [
        'livewire/livewire' => '^3.0',
        'spatie/laravel-permission' => '^6.0',
        'laravel/horizon' => '^5.0',
        'laikmosh/plog' => '^1.0'
    ],
    'optional-dev' => [
        'barryvdh/laravel-debugbar' => '^3.0',
        'laravel/boost' => '^1.0'
    ],
    'packages-post-install-commands' => [
        'broadcasting' => ['install:broadcasting']
    ]
];

$permissions = [];
$devPermissions = [];
$artisanPermissions = [];

echo "Testing Merged Selection...\n";

// Collect all options
$allOptions = [];
$optionLabels = [];

// Optional packages
if (isset($packages['optional'])) {
    foreach ($packages['optional'] as $package => $version) {
        $key = "opt:{$package}";
        $allOptions[$key] = $package;
        $optionLabels[$key] = "{$package} (Optional)";
    }
}

// Optional dev packages
if (isset($packages['optional-dev'])) {
    foreach ($packages['optional-dev'] as $package => $version) {
        $key = "dev:{$package}";
        $allOptions[$key] = $package;
        $optionLabels[$key] = "{$package} (Dev)";
    }
}

// Artisan commands
if (isset($packages['packages-post-install-commands'])) {
    foreach ($packages['packages-post-install-commands'] as $title => $commands) {
        $key = "cmd:{$title}";
        $allOptions[$key] = $title;
        $optionLabels[$key] = "{$title} (Artisan Command)";
    }
}

if (!empty($allOptions)) {
    $selectedKeys = multiselect(
        label: 'Select additional packages and commands to install:',
        options: $optionLabels,
        default: array_keys($allOptions)
    );

    foreach ($selectedKeys as $key) {
        if (str_starts_with($key, 'opt:')) {
            $package = substr($key, 4);
            $permissions[$package] = $packages['optional'][$package];
        } elseif (str_starts_with($key, 'dev:')) {
            $package = substr($key, 4);
            $devPermissions[$package] = $packages['optional-dev'][$package];
        } elseif (str_starts_with($key, 'cmd:')) {
            $title = substr($key, 4);
            foreach ($packages['packages-post-install-commands'][$title] as $command) {
                $artisanPermissions[$title] = $command;
            }
        }
    }
}

echo "\nSelected Optional Packages:\n";
print_r($permissions);

echo "\nSelected Optional Dev Packages:\n";
print_r($devPermissions);

echo "\nSelected Artisan Commands:\n";
print_r($artisanPermissions);
