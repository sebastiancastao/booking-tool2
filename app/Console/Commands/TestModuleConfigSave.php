<?php

namespace App\Console\Commands;

use App\Models\Widget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestModuleConfigSave extends Command
{
    protected $signature = 'widget:test-module-config-save {id=1}';
    protected $description = 'Test if module_configs saves correctly';

    public function handle()
    {
        $widget = Widget::find($this->argument('id'));

        if (!$widget) {
            $this->error('Widget not found!');
            return 1;
        }

        $this->info('╔════════════════════════════════════════════════════════╗');
        $this->info('║      TESTING MODULE_CONFIGS SAVE                       ║');
        $this->info('╚════════════════════════════════════════════════════════╝');
        $this->newLine();

        // Show current state
        $this->info('Current State:');
        $currentConfig = $widget->module_configs;
        $this->line('  module_configs keys: ' . count($currentConfig));

        if (isset($currentConfig['origin-challenges']['options'][0])) {
            $currentStairs = $currentConfig['origin-challenges']['options'][0];
            $this->line('  Current stairs pricing_value: ' . $currentStairs['pricing_value']);
        }
        $this->newLine();

        // Test 1: Try to update stairs pricing
        $this->info('Test 1: Updating stairs pricing_value to 0.15 (15%)...');

        $newConfigs = $widget->module_configs;
        $newConfigs['origin-challenges']['options'][0]['pricing_value'] = 0.15;
        $newConfigs['origin-challenges']['options'][0]['description'] = 'Updated: $45 per flight (max 10 flights)';

        $widget->module_configs = $newConfigs;

        $this->line('  Is dirty: ' . ($widget->isDirty('module_configs') ? 'YES' : 'NO'));

        if ($widget->isDirty('module_configs')) {
            $saved = $widget->save();
            $this->line('  Save result: ' . ($saved ? 'true' : 'false'));

            // Refresh and check
            $widget->refresh();
            $savedValue = $widget->module_configs['origin-challenges']['options'][0]['pricing_value'] ?? null;
            $this->line('  Value after save: ' . $savedValue);

            if ($savedValue == 0.15) {
                $this->info('  ✓ Save WORKED!');
            } else {
                $this->error('  ✗ Save DID NOT WORK!');
            }
        } else {
            $this->warn('  ⚠ Laravel did NOT detect change (not dirty)');
            $this->line('  This means Laravel\'s JSON casting has an issue');
        }
        $this->newLine();

        // Test 2: Force save by reassigning the attribute
        $this->info('Test 2: Force save by reassigning attribute...');

        $widget = Widget::find($this->argument('id'));
        $configs = $widget->module_configs;
        $configs['origin-challenges']['options'][0]['pricing_value'] = 0.2;

        // Force Laravel to detect change
        $widget->setAttribute('module_configs', $configs);

        $this->line('  Is dirty: ' . ($widget->isDirty('module_configs') ? 'YES' : 'NO'));

        $saved = $widget->save();
        $this->line('  Save result: ' . ($saved ? 'true' : 'false'));

        $widget->refresh();
        $savedValue = $widget->module_configs['origin-challenges']['options'][0]['pricing_value'] ?? null;
        $this->line('  Value after save: ' . $savedValue);

        if ($savedValue == 0.2) {
            $this->info('  ✓ Save WORKED with setAttribute!');
        } else {
            $this->error('  ✗ Save DID NOT WORK!');
        }
        $this->newLine();

        // Restore original value (0.1)
        $this->info('Restoring original value (0.1)...');
        $widget = Widget::find($this->argument('id'));
        $configs = $widget->module_configs;
        $configs['origin-challenges']['options'][0]['pricing_value'] = 0.1;
        $configs['origin-challenges']['options'][0]['description'] = 'Additional $45 per flight (max 10 flights)';
        $widget->setAttribute('module_configs', $configs);
        $widget->save();
        $widget->refresh();
        $this->line('  Restored to: ' . $widget->module_configs['origin-challenges']['options'][0]['pricing_value']);
        $this->newLine();

        // Check database directly
        $this->info('Test 3: Direct database check...');
        $dbWidget = DB::table('widgets')->where('id', $widget->id)->first();
        $dbConfigs = json_decode($dbWidget->module_configs, true);
        $dbValue = $dbConfigs['origin-challenges']['options'][0]['pricing_value'] ?? null;
        $this->line('  Database value: ' . $dbValue);
        $this->newLine();

        $this->info('═════════════════════════════════════════════════════');
        $this->info('DIAGNOSIS:');
        if ($widget->isDirty('module_configs')) {
            $this->info('  ✓ Laravel detects changes to module_configs');
            $this->info('  ✓ Saving should work correctly');
        } else {
            $this->warn('  ⚠ Laravel does NOT detect nested changes');
            $this->warn('  ⚠ Need to use setAttribute() to force detection');
        }
        $this->info('═════════════════════════════════════════════════════');

        return 0;
    }
}
