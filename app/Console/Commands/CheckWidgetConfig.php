<?php

namespace App\Console\Commands;

use App\Models\Widget;
use Illuminate\Console\Command;

class CheckWidgetConfig extends Command
{
    protected $signature = 'widget:check-config {id=1}';
    protected $description = 'Check widget configuration data';

    public function handle()
    {
        $widget = Widget::find($this->argument('id'));

        if (!$widget) {
            $this->error('Widget not found!');
            return 1;
        }

        $this->info('=== Widget Configuration Check ===');
        $this->info('Widget ID: ' . $widget->id);
        $this->info('Widget Name: ' . $widget->name);

        $this->newLine();
        $this->info('=== Enabled Modules ===');
        $this->info('Type: ' . gettype($widget->enabled_modules));
        $this->info('Count: ' . count($widget->enabled_modules ?? []));
        if (is_array($widget->enabled_modules)) {
            foreach ($widget->enabled_modules as $i => $module) {
                $this->line(($i + 1) . '. ' . $module);
            }
        }

        $this->newLine();
        $this->info('=== Module Configs ===');
        $this->info('Type: ' . gettype($widget->module_configs));
        $this->info('Count: ' . (is_array($widget->module_configs) ? count($widget->module_configs) : 0));

        if (is_array($widget->module_configs) && count($widget->module_configs) > 0) {
            $this->info('Module Keys:');
            foreach (array_keys($widget->module_configs) as $i => $key) {
                $this->line(($i + 1) . '. ' . $key);
            }

            // Check if origin-challenges exists and show its config
            if (isset($widget->module_configs['origin-challenges'])) {
                $this->newLine();
                $this->info('=== Origin Challenges Config ===');
                $config = $widget->module_configs['origin-challenges'];
                $this->line(json_encode($config, JSON_PRETTY_PRINT));
            }
        } else {
            $this->warn('Module configs is empty or not an array!');
        }

        $this->newLine();
        $this->info('=== Steps Relationship ===');
        $this->info('Steps Count: ' . $widget->steps->count());

        $this->newLine();
        $this->info('=== getConfigurationArray() Test ===');
        $configArray = $widget->getConfigurationArray();
        $this->info('Steps Data Count: ' . count($configArray['steps_data'] ?? []));
        $this->info('Step Order Count: ' . count($configArray['step_order'] ?? []));

        if (isset($configArray['steps_data']['origin-challenges'])) {
            $this->newLine();
            $this->info('=== Origin Challenges from getConfigurationArray ===');
            $this->line(json_encode($configArray['steps_data']['origin-challenges'], JSON_PRETTY_PRINT));
        }

        return 0;
    }
}
