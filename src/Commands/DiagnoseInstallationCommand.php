<?php

namespace Ihabrouk\Messenger\Commands;

use Schema;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DiagnoseInstallationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'messenger:diagnose';

    /**
     * The console command description.
     */
    protected $description = 'Diagnose messenger package installation issues';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Diagnosing Messenger Package Installation...');
        $this->newLine();

        $issues = 0;

        // Check 1: Class autoloading
        $this->info('1. Checking class autoloading...');
        if (class_exists('Ihabrouk\Messenger\Models\Batch')) {
            $this->info('   ✅ Batch class can be autoloaded');
        } else {
            $this->error('   ❌ Batch class NOT found - autoloading issue');
            $issues++;
        }

        if (class_exists('Ihabrouk\Messenger\Providers\MessengerServiceProvider')) {
            $this->info('   ✅ Service Provider can be autoloaded');
        } else {
            $this->error('   ❌ Service Provider NOT found - package not installed correctly');
            $issues++;
        }

        // Check 2: Database tables
        $this->info('2. Checking database tables...');
        try {
            $tables = [
                'messenger_batches' => 'Batch',
                'messenger_messages' => 'Message',
                'messenger_templates' => 'Template',
                'messenger_logs' => 'Log',
                'messenger_contacts' => 'Contact',
                'messenger_consents' => 'Consent',
                'messenger_webhooks' => 'Webhook',
            ];

            foreach ($tables as $table => $model) {
                if (Schema::hasTable($table)) {
                    $this->info("   ✅ Table '{$table}' exists");
                } else {
                    $this->error("   ❌ Table '{$table}' missing - migrations not run");
                    $issues++;
                }
            }
        } catch (Exception $e) {
            $this->error("   ❌ Database connection error: " . $e->getMessage());
            $issues++;
        }

        // Check 3: Configuration
        $this->info('3. Checking configuration...');
        if (config('messenger')) {
            $this->info('   ✅ Messenger config exists');
        } else {
            $this->warn('   ⚠️  Messenger config not published (optional)');
        }

        // Check 4: Service Provider registration
        $this->info('4. Checking service provider registration...');
        $providers = config('app.providers', []);
        $registered = false;
        foreach ($providers as $provider) {
            if (str_contains($provider, 'MessengerServiceProvider')) {
                $registered = true;
                break;
            }
        }

        if ($registered || app()->bound('Ihabrouk\Messenger\Contracts\MessengerServiceInterface')) {
            $this->info('   ✅ Service Provider is registered');
        } else {
            $this->error('   ❌ Service Provider NOT registered properly');
            $issues++;
        }

        // Check 5: Package installation
        $this->info('5. Checking package installation...');
        if (File::exists(base_path('vendor/ihabrouk/messenger'))) {
            $this->info('   ✅ Package vendor directory exists');
        } else {
            $this->error('   ❌ Package vendor directory missing');
            $issues++;
        }

        $this->newLine();

        if ($issues === 0) {
            $this->info('🎉 All checks passed! Package appears to be installed correctly.');
            $this->info('If you\'re still getting errors, try:');
            $this->info('   - php artisan config:clear');
            $this->info('   - php artisan cache:clear');
            $this->info('   - composer dump-autoload');
        } else {
            $this->error("❌ Found {$issues} issue(s). See recommendations below:");
            $this->newLine();
            $this->info('🔧 Recommended fixes:');
            $this->info('1. composer require ihabrouk/messenger');
            $this->info('2. composer dump-autoload');
            $this->info('3. php artisan vendor:publish --provider="Ihabrouk\Messenger\Providers\MessengerServiceProvider" --tag="messenger-migrations"');
            $this->info('4. php artisan migrate');
            $this->info('5. php artisan config:clear && php artisan cache:clear');
        }

        return $issues === 0 ? 0 : 1;
    }
}
