<?php

namespace App\Filament\Resources\WidgetResource\Pages;

use App\Filament\Resources\WidgetResource;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateWidget extends CreateRecord
{
    protected static string $resource = WidgetResource::class;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        // Redirect to the list page after creating
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        Log::info('=== CREATE WIDGET: Before Create ===');
        Log::info('enabled_modules RAW: ' . json_encode($data['enabled_modules'] ?? null));
        Log::info('enabled_modules type: ' . gettype($data['enabled_modules'] ?? 'not set'));

        $data['company_id'] = Filament::getTenant()->id;

        // Handle enabled_modules for PostgreSQL
        if (isset($data['enabled_modules'])) {
            if (is_array($data['enabled_modules'])) {
                // Keep the array as-is, just ensure it's properly indexed
                $data['enabled_modules'] = array_values($data['enabled_modules']);
                Log::info('enabled_modules is array with ' . count($data['enabled_modules']) . ' items');
            } else {
                Log::warning('enabled_modules is not an array, converting');
                $data['enabled_modules'] = [];
            }
        } else {
            Log::warning('enabled_modules not present, setting to empty array');
            $data['enabled_modules'] = [];
        }

        // Ensure other JSON fields are arrays
        $data['module_configs'] = $data['module_configs'] ?? [];
        $data['branding'] = $data['branding'] ?? [];
        $data['settings'] = $data['settings'] ?? [];

        Log::info('Final enabled_modules (' . count($data['enabled_modules']) . ' items): ' . json_encode($data['enabled_modules']));
        Log::info('=== END Before Create ===');

        return $data;
    }

    protected function afterCreate(): void
    {
        Log::info('=== CREATE WIDGET: After Create ===');
        Log::info('Record ID: ' . $this->record->id);
        Log::info('Created enabled_modules from DB: ' . json_encode($this->record->enabled_modules));
        Log::info('Created enabled_modules type: ' . gettype($this->record->enabled_modules));
        Log::info('=== END After Create ===');
    }
}
