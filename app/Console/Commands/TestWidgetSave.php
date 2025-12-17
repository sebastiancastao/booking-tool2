<?php

namespace App\Console\Commands;

use App\Models\Widget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestWidgetSave extends Command
{
    protected $signature = 'widget:test-save {id=1}';
    protected $description = 'Test if widget save is working correctly';

    public function handle()
    {
        $widget = Widget::find($this->argument('id'));

        if (!$widget) {
            $this->error('Widget not found!');
            return 1;
        }

        $this->info('=== Testing Widget Save Functionality ===');
        $this->newLine();

        // Show current state
        $this->info('Current State:');
        $this->line('  Widget ID: ' . $widget->id);
        $this->line('  Enabled Modules Count: ' . count($widget->enabled_modules ?? []));
        $this->line('  Current Modules: ' . implode(', ', array_slice($widget->enabled_modules ?? [], 0, 5)));
        $this->newLine();

        // Test 1: Try adding a module
        $this->info('Test 1: Adding a test module...');
        $originalModules = $widget->enabled_modules ?? [];
        $testModules = array_merge($originalModules, ['test-module-' . time()]);

        $widget->enabled_modules = $testModules;
        $saveResult = $widget->save();

        $this->line('  Save result: ' . ($saveResult ? 'true' : 'false'));

        // Refresh and check
        $widget->refresh();
        $this->line('  Modules after save: ' . count($widget->enabled_modules ?? []));
        $this->line('  Last module: ' . end($widget->enabled_modules));

        if (in_array('test-module-' . substr(time(), -5), json_encode($widget->enabled_modules))) {
            $this->info('  ✓ Module was saved successfully!');
        } else {
            $this->warn('  ⚠ Module may not have been saved');
        }
        $this->newLine();

        // Restore original
        $this->info('Restoring original modules...');
        $widget->enabled_modules = $originalModules;
        $widget->save();
        $widget->refresh();
        $this->line('  Restored count: ' . count($widget->enabled_modules ?? []));
        $this->newLine();

        // Test 2: Check database connection
        $this->info('Test 2: Checking database connection...');
        $dbConfig = config('database.default');
        $this->line('  Database driver: ' . $dbConfig);
        $this->line('  Connection: ' . config('database.connections.' . $dbConfig . '.host'));
        $this->line('  Database: ' . config('database.connections.' . $dbConfig . '.database'));
        $this->newLine();

        // Test 3: Direct database query
        $this->info('Test 3: Direct database query...');
        $dbWidget = DB::table('widgets')->where('id', $widget->id)->first();
        if ($dbWidget) {
            $this->line('  ✓ Widget found in database');
            $this->line('  DB enabled_modules type: ' . gettype($dbWidget->enabled_modules));
            $dbModules = json_decode($dbWidget->enabled_modules, true);
            $this->line('  DB modules count: ' . count($dbModules ?? []));

            if (count($dbModules ?? []) === count($widget->enabled_modules ?? [])) {
                $this->info('  ✓ Model matches database');
            } else {
                $this->warn('  ⚠ Model does NOT match database!');
                $this->line('    Model: ' . count($widget->enabled_modules ?? []));
                $this->line('    Database: ' . count($dbModules ?? []));
            }
        } else {
            $this->error('  ✗ Widget NOT found in database!');
        }
        $this->newLine();

        // Test 4: Test module_configs save
        $this->info('Test 4: Testing module_configs save...');
        $originalConfigs = $widget->module_configs;
        $widget->module_configs = array_merge($originalConfigs ?? [], ['test-key' => 'test-value']);
        $widget->save();
        $widget->refresh();

        if (isset($widget->module_configs['test-key'])) {
            $this->info('  ✓ module_configs saved successfully!');
            // Clean up
            $widget->module_configs = $originalConfigs;
            $widget->save();
        } else {
            $this->warn('  ⚠ module_configs may not have saved');
        }
        $this->newLine();

        $this->info('═══════════════════════════════════════');
        $this->info('Summary:');
        $this->line('  Database saves appear to be: ' . ($saveResult ? 'WORKING' : 'NOT WORKING'));
        $this->info('═══════════════════════════════════════');

        return 0;
    }
}
