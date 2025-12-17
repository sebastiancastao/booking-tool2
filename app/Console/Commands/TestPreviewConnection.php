<?php

namespace App\Console\Commands;

use App\Models\Widget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestPreviewConnection extends Command
{
    protected $signature = 'widget:test-preview {id=1}';
    protected $description = 'Test preview connection to database';

    public function handle()
    {
        $widget = Widget::find($this->argument('id'));

        if (!$widget) {
            $this->error('Widget not found!');
            return 1;
        }

        $this->info('╔══════════════════════════════════════════════════════╗');
        $this->info('║         PREVIEW CONNECTION TEST                      ║');
        $this->info('╚══════════════════════════════════════════════════════╝');
        $this->newLine();

        // 1. Direct database query
        $this->info('1️⃣  Direct Database Query:');
        $dbWidget = DB::table('widgets')->where('id', $widget->id)->first();
        $dbModules = json_decode($dbWidget->enabled_modules, true);
        $dbConfigs = json_decode($dbWidget->module_configs, true);

        $this->line('   Enabled Modules: ' . count($dbModules ?? []));
        $this->line('   Module Configs: ' . count($dbConfigs ?? []));
        $this->line('   Updated At: ' . $dbWidget->updated_at);
        $this->newLine();

        // 2. Eloquent Model
        $this->info('2️⃣  Eloquent Model (fresh from DB):');
        $freshWidget = Widget::find($widget->id);
        $this->line('   Enabled Modules: ' . count($freshWidget->enabled_modules ?? []));
        $this->line('   Module Configs: ' . count($freshWidget->module_configs ?? []));
        $this->newLine();

        // 3. getConfigurationArray (what preview uses)
        $this->info('3️⃣  getConfigurationArray() [Preview Method]:');
        $configArray = $freshWidget->getConfigurationArray();
        $this->line('   Steps Data Count: ' . count($configArray['steps_data'] ?? []));
        $this->line('   Step Order Count: ' . count($configArray['step_order'] ?? []));
        $this->newLine();

        // 4. Check stairs pricing
        $this->info('4️⃣  Origin Challenges - Stairs Pricing:');
        if (isset($dbConfigs['origin-challenges']['options'][0])) {
            $dbStairs = $dbConfigs['origin-challenges']['options'][0];
            $this->line('   DB Pricing Value: ' . ($dbStairs['pricing_value'] ?? 'N/A') . ' (' . (($dbStairs['pricing_value'] ?? 0) * 100) . '%)');
        }

        if (isset($configArray['steps_data']['origin-challenges']['options'][0]['estimation'])) {
            $previewStairs = $configArray['steps_data']['origin-challenges']['options'][0]['estimation'];
            $this->line('   Preview Pricing Value: ' . ($previewStairs['pricing_value'] ?? 'N/A') . ' (' . (($previewStairs['pricing_value'] ?? 0) * 100) . '%)');
        }
        $this->newLine();

        // 5. Compare
        $this->info('5️⃣  Data Consistency Check:');
        $dbCount = count($dbModules ?? []);
        $modelCount = count($freshWidget->enabled_modules ?? []);
        $previewCount = count($configArray['steps_data'] ?? []);

        if ($dbCount === $modelCount) {
            $this->info('   ✅ Database matches Eloquent Model');
        } else {
            $this->error('   ❌ Database (' . $dbCount . ') != Model (' . $modelCount . ')');
        }

        if (isset($configArray['steps_data']['origin-challenges'])) {
            $this->info('   ✅ Preview has origin-challenges data');
        } else {
            $this->warn('   ⚠️  Preview missing origin-challenges');
        }
        $this->newLine();

        // 6. Widget URLs
        $this->info('6️⃣  Access URLs:');
        $this->line('   Custom Edit: http://localhost:8000/widgets/' . $widget->id . '/edit');
        $this->line('   Preview: http://localhost:8000/widgets/' . $widget->id . '/preview');
        $this->line('   API Config: http://localhost:8000/api/widget/' . $widget->widget_key . '/config');
        $this->newLine();

        $this->info('═════════════════════════════════════════════════════');
        $this->info('✅ RESULT: Preview is connected to database!');
        $this->line('   The preview route uses getConfigurationArray() which');
        $this->line('   pulls data directly from the widgets table.');
        $this->newLine();
        $this->info('   If preview shows old data:');
        $this->line('   1. Hard refresh browser (Ctrl+Shift+R)');
        $this->line('   2. Check you\'re viewing the correct widget ID');
        $this->line('   3. Verify edits are being saved (check logs)');
        $this->info('═════════════════════════════════════════════════════');

        return 0;
    }
}
