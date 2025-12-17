<?php

namespace App\Console\Commands;

use App\Models\Widget;
use Illuminate\Console\Command;

class TestWidgetRetrieval extends Command
{
    protected $signature = 'widget:test-retrieval {id=1}';
    protected $description = 'Test widget data retrieval from database';

    public function handle()
    {
        $widgetId = $this->argument('id');

        $this->info("=== Testing Widget Retrieval ===");
        $this->info("Widget ID: {$widgetId}");
        $this->newLine();

        $widget = Widget::find($widgetId);

        if (!$widget) {
            $this->error("Widget #{$widgetId} not found!");
            return 1;
        }

        $this->info("Widget Name: {$widget->name}");
        $this->info("Company: {$widget->company_name}");
        $this->info("Status: {$widget->status}");
        $this->newLine();

        $this->info("=== Enabled Modules ===");
        $this->info("Type: " . gettype($widget->enabled_modules));
        $this->info("Count: " . count($widget->enabled_modules ?? []));
        $this->newLine();

        if ($widget->enabled_modules) {
            $this->table(
                ['#', 'Module Key'],
                collect($widget->enabled_modules)->map(fn($module, $index) => [$index + 1, $module])
            );
        } else {
            $this->warn("No modules enabled!");
        }

        $this->newLine();
        $this->info("=== Raw JSON ===");
        $this->line(json_encode($widget->enabled_modules, JSON_PRETTY_PRINT));

        $this->newLine();
        $this->info("=== Database Raw Value ===");
        $raw = \DB::table('widgets')->where('id', $widgetId)->value('enabled_modules');
        $this->line("Raw DB value type: " . gettype($raw));
        $this->line($raw);

        return 0;
    }
}
