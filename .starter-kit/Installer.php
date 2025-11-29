<?php
// .starter-kit/Installer.php

namespace StarterKit;

class Installer
{
    private string $rootPath;
    private array $originalComposer;
    private array $permissions = [];
    private array $devPermissions = [];
    private array $artisanPermissions = [];
    
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
        // Ensure autoloader is available for Laravel Prompts
        if (file_exists($this->rootPath . '/vendor/autoload.php')) {
            require_once $this->rootPath . '/vendor/autoload.php';
        }
        
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
        
        // Step 3: Configure options (App name, DB, Packages)
        $this->configureOptions();

        // Step 4: Install additional packages
        $this->installPackages();
        
        // Step 5: Run post-install setup
        $this->runPostInstall();
        
        // Step 6: Clean up starter kit files
        $this->cleanup();
        
        $this->output("\n✅ Starter kit installed successfully!");
        $this->output("Run 'php artisan serve' to start your application.\n");
    }
    
    private function installLaravel()
    {
        $this->output("📦 Installing Laravel...");
        
        // Temporarily move .starter-kit and install.php to preserve them
        $tempStarterKit = sys_get_temp_dir() . '/starter-kit-' . uniqid();
        $tempInstaller = sys_get_temp_dir() . '/install-' . uniqid() . '.php';
        
        rename($this->rootPath . '/.starter-kit', $tempStarterKit);
        rename($this->rootPath . '/install.php', $tempInstaller);
        
        // Clear everything in root directory
        $this->clearDirectory($this->rootPath, []);
        
        // Install Laravel directly in root (now empty)
        // This will install Laravel's original artisan
        $this->exec("composer create-project laravel/laravel .");
        
        // Move .starter-kit back (but not install.php - we don't need it anymore)
        rename($tempStarterKit, $this->rootPath . '/.starter-kit');
        
        // Clean up temp installer
        unlink($tempInstaller);
        
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
        }
        
    }

    private function configureOptions()
    {
        $this->output("\n⚙️  Configuring your application...");
        
        // Set application name
        $appName = \Laravel\Prompts\text(
            label: 'What is your application name?',
            default: 'Laravel',
            placeholder: 'E.g. My Awesome App'
        );
        $this->updateEnvironmentFile('APP_NAME', $appName);
        
        // Create database
        if (\Laravel\Prompts\confirm(label: 'Create SQLite database?', default: true)) {
            touch($this->rootPath . '/database/database.sqlite');
            $this->updateEnvironmentFile('DB_CONNECTION', 'sqlite');
            $this->updateEnvironmentFile('DB_DATABASE', $this->rootPath . '/database/database.sqlite');
        }

        // Package Selection Logic
        if (!isset($this->originalComposer['extra']['starter-kit'])) {
            return;
        }

        $packages = $this->originalComposer['extra']['starter-kit'];
        
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
        if (isset($this->originalComposer['extra']['packages-post-install-commands'])) {
            foreach ($this->originalComposer['extra']['packages-post-install-commands'] as $title => $commands) {
                $key = "cmd:{$title}";
                $allOptions[$key] = $title;
                $optionLabels[$key] = "{$title} (Artisan Command)";
            }
        }

        if (!empty($allOptions)) {
            $selectedKeys = \Laravel\Prompts\multiselect(
                label: 'Select additional packages and commands to install:',
                options: $optionLabels,
                default: array_keys($allOptions),
                scroll: 10
            );

            foreach ($selectedKeys as $key) {
                if (str_starts_with($key, 'opt:')) {
                    $package = substr($key, 4);
                    $this->permissions[$package] = $packages['optional'][$package];
                } elseif (str_starts_with($key, 'dev:')) {
                    $package = substr($key, 4);
                    $this->devPermissions[$package] = $packages['optional-dev'][$package];
                } elseif (str_starts_with($key, 'cmd:')) {
                    $title = substr($key, 4);
                    foreach ($this->originalComposer['extra']['packages-post-install-commands'][$title] as $command) {
                        $this->artisanPermissions[$title] = $command;
                    }
                }
            }
        }
        
        $this->output("✓ Application configured");
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

        // Install optional packages
        if (!empty($this->permissions)) {
            foreach ($this->permissions as $package => $version) {
                $this->output("  Installing {$package}...");
                $this->exec("composer require {$package}:{$version}");
                $this->runPackagePostInstallCommands($package);
            }
        }

        // Install optional dev packages
        if (!empty($this->devPermissions)) {
            foreach ($this->devPermissions as $package => $version) {
                $this->output("  Installing {$package} (dev)...");
                $this->exec("composer require --dev {$package}:{$version}");
                $this->runPackagePostInstallCommands($package);
            }
        }

        // Install artisan commands
        if (!empty($this->artisanPermissions)) {
            foreach ($this->artisanPermissions as $title => $command) {
                $this->output("    Running artisan command: {$command}...");
                $this->exec("php artisan {$command}");
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

        // Configure Horizon in supervisord
        $this->configureSupervisorProgram('horizon', isset($this->permissions['laravel/horizon']));
        
        // Configure Reverb in supervisord
        $this->configureSupervisorProgram('reverb', isset($this->artisanPermissions['broadcasting']));
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

    private function configureSupervisorProgram($programName, $enabled)
    {
        if ($enabled) {
            return;
        }

        $supervisorConf = $this->rootPath . '/.starter-kit/files/.envs/dev/laravel/supervisord.conf';
        
        $targetFile = $this->rootPath . '/.envs/dev/laravel/supervisord.conf';
        
        if (!file_exists($targetFile)) {
            return;
        }

        $content = file_get_contents($targetFile);
        $lines = explode("\n", $content);
        $newLines = [];
        $inSection = false;
        $sectionHeader = "[program:{$programName}]";

        foreach ($lines as $line) {
            if (trim($line) === $sectionHeader) {
                $inSection = true;
                $newLines[] = '; ' . $line;
                continue;
            }

            if ($inSection) {
                if (trim($line) === '' || str_starts_with(trim($line), '[')) {
                    $inSection = false;
                    $newLines[] = $line;
                } else {
                    $newLines[] = '; ' . $line;
                }
            } else {
                $newLines[] = $line;
            }
        }

        file_put_contents($targetFile, implode("\n", $newLines));
        $this->output("    Disabled {$programName} in supervisord.conf");
    }

    private function runPackagePostInstallCommands($package)
    {
        $artisanCommands = $this->originalComposer['extra']['packages-artisan-commands'][$package] ?? [];
        $this->output("    Running ".count($artisanCommands)." command(s) for package: {$package}...");
        foreach ($artisanCommands as $command) {
            $this->output("    Running artisan command: {$command}...");
            $this->exec("php artisan {$command}");
        }
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

    private function exec($command)
    {
        passthru($command, $returnCode);
        
        if ($returnCode !== 0) {
            $this->output("Error running: {$command}");
            exit(1);
        }
        
        return [];
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
}