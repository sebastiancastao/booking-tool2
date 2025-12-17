<?php

namespace App\Console\Commands;

use App\Models\Widget;
use Illuminate\Console\Command;

class TestPreviewData extends Command
{
    protected $signature = 'widget:test-preview-data {id=1}';
    protected $description = 'Test what data the preview route would return';

    public function handle()
    {
        $widget = Widget::find($this->argument('id'));

        if (!$widget) {
            $this->error('Widget not found!');
            return 1;
        }

        $this->info('╔═══════════════════════════════════════════════════════╗');
        $this->info('║     SIMULATING PREVIEW ROUTE DATA                     ║');
        $this->info('╚═══════════════════════════════════════════════════════╝');
        $this->newLine();

        // This is exactly what the preview route does (web.php:277)
        $widgetConfig = $widget->getConfigurationArray();

        $this->info('Widget Info (passed to Inertia):');
        $widgetData = [
            'id' => $widget->id,
            'name' => $widget->name,
            'widget_key' => $widget->widget_key,
            'status' => $widget->status,
        ];
        $this->line(json_encode($widgetData, JSON_PRETTY_PRINT));
        $this->newLine();

        $this->info('Config Data (passed to Inertia):');
        $this->line('  widget_id: ' . $widgetConfig['widget_id']);
        $this->line('  steps_data count: ' . count($widgetConfig['steps_data'] ?? []));
        $this->line('  step_order count: ' . count($widgetConfig['step_order'] ?? []));
        $this->newLine();

        // Check origin-challenges specifically
        if (isset($widgetConfig['steps_data']['origin-challenges'])) {
            $originChallenges = $widgetConfig['steps_data']['origin-challenges'];

            $this->info('🎯 Origin Challenges Data:');
            $this->line('  Title: ' . $originChallenges['title']);
            $this->line('  Subtitle: ' . $originChallenges['subtitle']);
            $this->line('  Options count: ' . count($originChallenges['options'] ?? []));
            $this->newLine();

            $this->info('📊 Stairs Option (First Option):');
            if (isset($originChallenges['options'][0])) {
                $stairs = $originChallenges['options'][0];
                $this->line('  ID: ' . $stairs['id']);
                $this->line('  Value: ' . $stairs['value']);
                $this->line('  Title: ' . $stairs['title']);
                $this->line('  Description: ' . $stairs['description']);

                if (isset($stairs['estimation'])) {
                    $this->newLine();
                    $this->info('  💰 Estimation:');
                    $this->line('    Pricing Type: ' . $stairs['estimation']['pricing_type']);
                    $this->line('    Pricing Value: ' . $stairs['estimation']['pricing_value']);
                    $this->line('    Percentage: ' . ($stairs['estimation']['pricing_value'] * 100) . '%');
                    $this->line('    Max Units: ' . $stairs['estimation']['max_units']);
                }
            }
            $this->newLine();

            $this->info('📋 All Origin Challenges Options:');
            foreach ($originChallenges['options'] as $index => $option) {
                $pricingValue = $option['estimation']['pricing_value'] ?? 'N/A';
                $pricingType = $option['estimation']['pricing_type'] ?? 'N/A';

                $displayValue = $pricingValue;
                if (is_numeric($pricingValue) && $pricingType === 'percentage') {
                    $displayValue = ($pricingValue * 100) . '%';
                } elseif (is_numeric($pricingValue) && $pricingType === 'fixed') {
                    $displayValue = '$' . $pricingValue;
                }

                $this->line('  ' . ($index + 1) . '. ' . $option['title'] . ' - ' . $pricingType . ': ' . $displayValue);
            }
        } else {
            $this->warn('⚠️  origin-challenges not found in steps_data!');
        }
        $this->newLine();

        $this->info('═══════════════════════════════════════════════════════');
        $this->info('This is the EXACT data that would be sent to the preview');
        $this->info('page via Inertia props.');
        $this->newLine();
        $this->warn('If preview shows different data:');
        $this->line('  1. Inertia may be caching the old props');
        $this->line('  2. Try visiting: /widgets/' . $widget->id . '/preview?v=' . time());
        $this->line('  3. Or clear browser cache completely');
        $this->info('═══════════════════════════════════════════════════════');

        return 0;
    }
}
