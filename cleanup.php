<?php
// Cleanup script for Laravel Starter Kit
echo "Starting cleanup process...\n";
$filesToDelete = [
    '.starter-kit',
    'starter.php',
    'install.php',
    'cleanup.php',
    'app/Console/Commands/StarterInit.php',
];

foreach ($filesToDelete as $file) {
    if (is_dir($file)) {
        // Recursively delete directory
        $it = new RecursiveDirectoryIterator($file, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $fileInfo) {
            if ($fileInfo->isDir()){
                rmdir($fileInfo->getRealPath());
            } else {
                unlink($fileInfo->getRealPath());
            }
        }
        rmdir($file);
        echo "Deleted directory: $file\n";
    } elseif (file_exists($file)) {
        unlink($file);
        echo "Deleted file: $file\n";
    }
}

echo "Cleanup process completed.\n";