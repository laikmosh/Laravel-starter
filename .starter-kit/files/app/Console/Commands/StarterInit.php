<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class StarterInit extends Command
{
    protected $signature = 'starter:init';
    protected $description = 'Initialize the starter kit';

    public function handle()
    {
        // Copy APP_KEY to multiple .env.app files
        $appSuccess = $this->copyEnvKeys(
            sourceFile: base_path('.env'),
            targetFiles: [
                base_path('.envs/dev/.config/.env.app'),
                base_path('.envs/prod/.config/.env.app'),
            ],
            keys: [
                'APP_NAME',
                'APP_KEY',
            ]
        );

        // Copy server keys to multiple .env.server files
        $serverSuccess = true;
        if (file_exists(base_path('config/reverb.php'))) {
            $serverSuccess = $this->copyEnvKeys(
                sourceFile: base_path('.env'),
                targetFiles: [
                    base_path('.envs/dev/.config/.env.server'),
                    base_path('.envs/prod/.config/.env.server'),
                ],
                keys: [
                    'REVERB_APP_ID',
                    'REVERB_APP_KEY',
                    'REVERB_APP_SECRET',
                ]
            );
        }

        return ($appSuccess && $serverSuccess) ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Copy multiple keys from source .env file to target .env files
     *
     * @param string $sourceFile Path to source .env file
     * @param string|array $targetFiles Path(s) to target .env file(s)
     * @param array $keys Array of keys to copy (e.g., ['APP_KEY', 'DB_HOST'])
     * @return bool Success status (true if all keys copied successfully to all targets)
     */
    protected function copyEnvKeys(string $sourceFile, string|array $targetFiles, array $keys): bool
    {
        // Normalize target files to array
        $targetFiles = is_array($targetFiles) ? $targetFiles : [$targetFiles];
        
        // Check if source file exists
        if (!file_exists($sourceFile)) {
            $this->error("Source file not found: {$sourceFile}");
            return false;
        }

        // Read source content once
        $sourceContent = file_get_contents($sourceFile);
        
        // Extract all requested keys and their values
        $keyValues = [];
        $allKeysFound = true;
        
        foreach ($keys as $key) {
            $pattern = '/^' . preg_quote($key, '/') . '=(.*)$/m';
            preg_match($pattern, $sourceContent, $matches);
            
            if (empty($matches[1])) {
                $this->error("{$key} not found in source file: {$sourceFile}");
                $allKeysFound = false;
                continue;
            }
            
            $keyValues[$key] = $matches[1];
        }
        
        if (empty($keyValues)) {
            $this->error("No keys found to copy");
            return false;
        }
        
        // Process each target file
        $allTargetsSuccessful = true;
        foreach ($targetFiles as $targetFile) {
            // Ensure target directory exists
            $targetDir = dirname($targetFile);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
                $this->info("Created directory: {$targetDir}");
            }

            // Read or initialize target content
            $targetContent = '';
            if (file_exists($targetFile)) {
                $targetContent = file_get_contents($targetFile);
            }
            
            // Update or add each key
            foreach ($keyValues as $key => $value) {
                $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
                
                if (preg_match($pattern, $targetContent)) {
                    // Key exists, replace its value
                    $targetContent = preg_replace($pattern, $key . '=' . $value, $targetContent);
                    $this->info("Updated {$key} in {$targetFile}");
                } else {
                    // Key doesn't exist, append it
                    if (!empty($targetContent) && !str_ends_with($targetContent, "\n")) {
                        $targetContent .= "\n";
                    }
                    $targetContent .= "{$key}={$value}\n";
                    $this->info("Added {$key} to {$targetFile}");
                }
            }
            
            // Write the updated content
            if (file_put_contents($targetFile, $targetContent) === false) {
                $this->error("Failed to write to {$targetFile}");
                $allTargetsSuccessful = false;
                continue;
            }
            
            if (!file_exists($targetFile)) {
                $this->info("Created {$targetFile} with " . count($keyValues) . " key(s)");
            }
            
            $this->info("Successfully copied " . count($keyValues) . " key(s) from {$sourceFile} to {$targetFile}");
        }
        
        return $allKeysFound && $allTargetsSuccessful;
    }
}