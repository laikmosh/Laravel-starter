<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class StarterInit extends Command
{
    protected $signature = 'starter:init';
    protected $description = 'Initialize the starter kit';

    public function handle()
    {
        // Copy APP_KEY from root .env to .envs/dev/.config/.env.app
        $success = $this->copyEnvKeys(
            sourceFile: base_path('.env'),
            targetFile: base_path('.envs/dev/.config/.env.app'),
            keys: ['APP_KEY']
        );

        // Copy APP_KEY from root .env to .envs/dev/.config/.env.app
        $success = $this->copyEnvKeys(
            sourceFile: base_path('.env'),
            targetFile: base_path('.envs/prod/.config/.env.app'),
            keys: ['APP_KEY']
        );

        return $success ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Copy multiple keys from source .env file to target .env file
     *
     * @param string $sourceFile Path to source .env file
     * @param string $targetFile Path to target .env file
     * @param array $keys Array of keys to copy (e.g., ['APP_KEY', 'DB_HOST'])
     * @return bool Success status (true if all keys copied successfully)
     */
    protected function copyEnvKeys(string $sourceFile, string $targetFile, array $keys): bool
    {
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
        file_put_contents($targetFile, $targetContent);
        
        if (!file_exists($targetFile)) {
            $this->info("Created {$targetFile} with " . count($keyValues) . " key(s)");
        }
        
        $this->info("Successfully copied " . count($keyValues) . " key(s) from {$sourceFile} to {$targetFile}");
        
        return $allKeysFound;
    }
}