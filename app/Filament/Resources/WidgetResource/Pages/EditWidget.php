<?php

namespace App\Filament\Resources\WidgetResource\Pages;

use App\Filament\Resources\WidgetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditWidget extends EditRecord
{
    protected static string $resource = WidgetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        // Stay on the edit page after saving
        return null;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        Log::info('=== EDIT WIDGET: Before Fill ===');
        Log::info('enabled_modules type: ' . gettype($data['enabled_modules'] ?? 'not set'));
        Log::info('enabled_modules value: ' . json_encode($data['enabled_modules'] ?? null));

        // Ensure enabled_modules is an array when loading the form
        if (isset($data['enabled_modules']) && is_string($data['enabled_modules'])) {
            $data['enabled_modules'] = json_decode($data['enabled_modules'], true) ?? [];
            Log::info('Decoded enabled_modules from string');
        }

        if (!isset($data['enabled_modules']) || !is_array($data['enabled_modules'])) {
            Log::warning('enabled_modules was not an array, setting to empty array');
            $data['enabled_modules'] = [];
        }

        // Ensure module_configs is an array when loading the form
        if (isset($data['module_configs']) && is_string($data['module_configs'])) {
            $data['module_configs'] = json_decode($data['module_configs'], true) ?? [];
        }

        if (!isset($data['module_configs']) || !is_array($data['module_configs'])) {
            $data['module_configs'] = [];
        }

        // Ensure branding and settings are arrays
        if (isset($data['branding']) && is_string($data['branding'])) {
            $data['branding'] = json_decode($data['branding'], true) ?? [];
        }

        if (isset($data['settings']) && is_string($data['settings'])) {
            $data['settings'] = json_decode($data['settings'], true) ?? [];
        }

        Log::info('enabled_modules after processing: ' . json_encode($data['enabled_modules']));
        Log::info('=== END Before Fill ===');

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        Log::info('=== EDIT WIDGET: Before Save ===');
        Log::info('enabled_modules RAW: ' . json_encode($data['enabled_modules'] ?? null));
        Log::info('enabled_modules type: ' . gettype($data['enabled_modules'] ?? 'not set'));
        Log::info('Full data keys: ' . implode(', ', array_keys($data)));

        // Handle enabled_modules for PostgreSQL
        if (isset($data['enabled_modules'])) {
            if (is_array($data['enabled_modules'])) {
                // Keep the array as-is, just ensure it's properly indexed
                // Don't filter out values - the user selected these modules
                $data['enabled_modules'] = array_values($data['enabled_modules']);
                Log::info('enabled_modules is array with ' . count($data['enabled_modules']) . ' items');
            } else {
                Log::warning('enabled_modules is not an array, converting');
                $data['enabled_modules'] = [];
            }
        } else {
            Log::warning('enabled_modules not present in data');
            $data['enabled_modules'] = [];
        }

        // Ensure other JSON fields are arrays
        $data['module_configs'] = $data['module_configs'] ?? [];
        $data['branding'] = $data['branding'] ?? [];
        $data['settings'] = $data['settings'] ?? [];

        Log::info('Final enabled_modules (' . count($data['enabled_modules']) . ' items): ' . json_encode($data['enabled_modules']));
        Log::info('=== END Before Save ===');

        return $data;
    }

    protected function afterSave(): void
    {
        Log::info('=== EDIT WIDGET: After Save ===');
        Log::info('Record ID: ' . $this->record->id);

        // Refresh the record from database to get the actual saved values
        $this->record->refresh();

        Log::info('Saved enabled_modules from DB: ' . json_encode($this->record->enabled_modules));
        Log::info('Saved enabled_modules type: ' . gettype($this->record->enabled_modules));
        Log::info('Saved enabled_modules count: ' . count($this->record->enabled_modules ?? []));
        Log::info('=== END After Save ===');

        \Filament\Notifications\Notification::make()
            ->title('Widget Updated Successfully!')
            ->success()
            ->body('Module selections saved: ' . count($this->record->enabled_modules ?? []) . ' modules enabled.')
            ->send();
    }
}
