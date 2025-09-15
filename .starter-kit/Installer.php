<?php
// .starter-kit/Installer.php

namespace StarterKit;

class Installer
{
    private string $rootPath;
    private array $originalComposer;
    
    public function __construct()
    {
        $this->rootPath = dirname(__DIR__);
        
        // Read the original composer.json from root and store in memory
        $this->originalComposer = json_decode(
            file_get_contents($this->rootPath . '/composer.json'), 
            true
        );
    }
    
    public function run()
    {
        $this->output("🚀 Laravel Starter Kit Installer");
        $this->output("================================\n");
        
        // Check if Laravel is already installed (when using 'laravel new')
        $laravelInstalled = file_exists($this->rootPath . '/vendor/autoload.php') && 
                           file_exists($this->rootPath . '/bootstrap/app.php');
        
        if (!$laravelInstalled) {
            // Step 1: Install Laravel (only if not already installed)
            $this->installLaravel();
        } else {
            $this->output("✓ Laravel already installed");
        }
        
        // Step 2: Apply customizations
        $this->applyCustomizations();
        
        // Step 3: Install additional packages
        $this->installPackages();
        
        // Step 4: Run post-install setup
        $this->runPostInstall();
        
        // Step 5: Clean up starter kit files
        $this->cleanup();
        
        $this->output("\n✅ Starter kit installed successfully!");
        $this->output("Run 'php artisan serve' to start your application.\n");
    }
    
    private function installLaravel()
    {
        $this->output("📦 Installing Laravel...");
        
        // Temporarily move .starter-kit and our bootstrap artisan to temp location
        $tempStarterKit = sys_get_temp_dir() . '/starter-kit-' . uniqid();
        $tempArtisan = sys_get_temp_dir() . '/artisan-' . uniqid();
        
        rename($this->rootPath . '/.starter-kit', $tempStarterKit);
        if (file_exists($this->rootPath . '/artisan')) {
            rename($this->rootPath . '/artisan', $tempArtisan);
        }
        
        // Clear everything else in root directory
        $this->clearDirectory($this->rootPath, []);
        
        // Install Laravel directly in root (now empty)
        $this->exec("composer create-project laravel/laravel . --no-interaction");
        
        // Move .starter-kit back (but NOT the artisan - keep Laravel's original)
        rename($tempStarterKit, $this->rootPath . '/.starter-kit');
        
        // Clean up temp artisan file
        if (file_exists($tempArtisan)) {
            unlink($tempArtisan);
        }
        
        // Update the Laravel composer.json with our customizations
        $this->updateComposerJson();
        
        $this->output("✓ Laravel installed with original artisan");
    }
    
    private function applyCustomizations()
    {
        $this->output("\n🎨 Applying customizations...");
        
        $filesDir = $this->rootPath . '/.starter-kit/files';
        
        if (is_dir($filesDir)) {
            $this->copyDirectory($filesDir, $this->rootPath);
            $this->output("✓ Custom files copied");
        }
        
        // Update .env.example with custom variables
        $this->updateEnvExample();
        
        // Create .env from .env.example if it doesn't exist
        if (!file_exists($this->rootPath . '/.env')) {
            if (file_exists($this->rootPath . '/.env.example')) {
                copy($this->rootPath . '/.env.example', $this->rootPath . '/.env');
            } else {
                // Create a basic .env file if .env.example doesn't exist
                $this->createBasicEnvFile();
            }
            $this->exec("php artisan key:generate");
        }
        
        // Configure application
        $this->configureApplication();
    }
    
    private function installPackages()
    {
        // Read packages from the original composer.json (stored in memory)
        if (!isset($this->originalComposer['extra']['starter-kit'])) {
            return;
        }
        
        $packages = $this->originalComposer['extra']['starter-kit'];
        
        $this->output("\n📚 Installing additional packages...");
        
        // Install composer packages
        if (isset($packages['require'])) {
            foreach ($packages['require'] as $package => $version) {
                $this->output("  Installing {$package}...");
                $this->exec("composer require {$package}:{$version}");
            }
        }
        
        // Install dev packages
        if (isset($packages['require-dev'])) {
            foreach ($packages['require-dev'] as $package => $version) {
                $this->output("  Installing {$package} (dev)...");
                $this->exec("composer require --dev {$package}:{$version}");
            }
        }
        
        // Install npm packages
        if (isset($packages['npm'])) {
            $this->output("\n📦 Installing NPM packages...");
            $this->exec("npm install");
            
            foreach ($packages['npm'] as $package => $version) {
                $this->exec("npm install {$package}@{$version}");
            }
            
            $this->exec("npm run build");
        }
    }
    
    private function configureApplication()
    {
        $this->output("\n⚙️  Configuring your application...");
        
        // Set application name
        $appName = $this->ask("What is your application name?", "Laravel");
        $this->updateEnvironmentFile('APP_NAME', $appName);
        
        // Create database
        if ($this->confirm("Create SQLite database?", true)) {
            touch($this->rootPath . '/database/database.sqlite');
            $this->updateEnvironmentFile('DB_CONNECTION', 'sqlite');
            $this->updateEnvironmentFile('DB_DATABASE', $this->rootPath . '/database/database.sqlite');
        }
        
        $this->output("✓ Application configured");
    }
    
    private function runPostInstall()
    {
        $this->output("\n🔧 Running post-install tasks...");
        
        // Create storage link
        $this->exec("php artisan storage:link");
        
        // Run migrations
        if ($this->confirm("Run database migrations?", true)) {
            $this->exec("php artisan migrate");
        }
        
        // Create admin user
        if ($this->confirm("Create admin user?", true)) {
            $email = $this->ask("Admin email?", "admin@example.com");
            $password = $this->askSecret("Admin password?");
            
            if ($password) {
                $this->exec("php artisan make:admin --email=\"{$email}\" --password=\"{$password}\"");
            }
        }
        
        // Clear caches
        $this->exec("php artisan optimize:clear");
    }
    
    private function cleanup()
    {
        $this->output("\n🧹 Cleaning up...");
        
        // Remove the .starter-kit directory
        $this->removeDirectory($this->rootPath . '/.starter-kit');
        
        $this->output("✓ Starter kit files removed");
    }
    
    private function updateComposerJson()
    {
        // Read the Laravel composer.json that was installed
        $laravelComposer = json_decode(file_get_contents($this->rootPath . '/composer.json'), true);
        
        // Update with our original package name and description if they exist
        if (isset($this->originalComposer['name'])) {
            $laravelComposer['name'] = $this->originalComposer['name'];
        }
        if (isset($this->originalComposer['description'])) {
            $laravelComposer['description'] = $this->originalComposer['description'];
        }
        
        // Preserve Laravel's standard scripts but remove any starter-kit specific ones
        // Laravel's standard scripts are needed for the framework to work properly
        
        // Write back the updated composer.json
        file_put_contents(
            $this->rootPath . '/composer.json',
            json_encode($laravelComposer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
    
    private function clearDirectory($dir, $exclude = [])
    {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            // Skip excluded files/directories
            if (in_array($file, $exclude)) {
                continue;
            }
            
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
    }
    
    
    
    private function copyDirectory($source, $dest)
    {
        if (!is_dir($source)) {
            return;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $destPath = $dest . '/' . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($item, $destPath);
            }
        }
    }
    
    private function updateEnvExample()
    {
        $envPath = $this->rootPath . '/.env.example';
        $customEnv = $this->rootPath . '/.starter-kit/env.additions';
        
        if (file_exists($customEnv)) {
            $content = file_get_contents($envPath);
            $additions = file_get_contents($customEnv);
            file_put_contents($envPath, $content . "\n" . $additions);
        }
    }
    
    private function exec($command)
    {
        exec($command . ' 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0) {
            $this->output("Error running: {$command}");
            $this->output(implode("\n", $output));
            exit(1);
        }
        
        return $output;
    }
    
    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . "/" . $object)) {
                    $this->removeDirectory($dir . "/" . $object);
                } else {
                    unlink($dir . "/" . $object);
                }
            }
        }
        rmdir($dir);
    }
    
    private function output($message)
    {
        echo $message . "\n";
    }
    
    private function confirm($question, $default = false)
    {
        $defaultText = $default ? "Y/n" : "y/N";
        echo $question . " ({$defaultText}): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);
        
        if (empty($line)) {
            return $default;
        }
        
        return in_array(strtolower($line), ['y', 'yes']);
    }
    
    private function ask($question, $default = null)
    {
        $defaultText = $default ? " [{$default}]" : "";
        echo $question . $defaultText . ": ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);
        
        return empty($line) ? $default : $line;
    }
    
    private function askSecret($question)
    {
        echo $question . ": ";
        
        // Hide input for password
        system('stty -echo');
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);
        system('stty echo');
        
        echo "\n"; // New line after hidden input
        return $line;
    }
    
    private function updateEnvironmentFile($key, $value)
    {
        $path = $this->rootPath . '/.env';
        
        if (file_exists($path)) {
            $content = file_get_contents($path);
            
            // Check if key exists
            if (preg_match("/^{$key}=.*/m", $content)) {
                // Update existing key
                $content = preg_replace("/^{$key}=.*/m", "{$key}=\"{$value}\"", $content);
            } else {
                // Add new key
                $content .= "\n{$key}=\"{$value}\"";
            }
            
            file_put_contents($path, $content);
        }
    }
    
    private function createBasicEnvFile()
    {
        $envContent = <<<ENV
APP_NAME=Laravel
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

SESSION_DRIVER=database
SESSION_LIFETIME=120

CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=log
ENV;
        
        file_put_contents($this->rootPath . '/.env', $envContent);
        $this->output("✓ Created basic .env file");
    }
}