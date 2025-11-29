<?php

use App\Models\Company;
use App\Models\User;
use App\Models\Widget;
use App\Models\WidgetLead;
use App\Models\WidgetPricing;
use App\Models\WidgetStep;

describe('Widget Management', function () {
    test('widget can be created with required fields', function () {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $widget = Widget::factory()->for($company)->create([
            'name' => 'Test Widget',
            'service_category' => 'moving-services',
            'status' => 'draft',
        ]);

        // Assert
        expect($widget->exists)->toBeTrue();
        expect($widget->name)->toBe('Test Widget');
        expect($widget->service_category)->toBe('moving-services');
        expect($widget->status)->toBe('draft');
        expect($widget->widget_key)->not->toBeNull();
    });

    test('widget can have steps added', function () {
        // Arrange
        $widget = Widget::factory()->create();

        // Act
        $step = WidgetStep::factory()->for($widget)->create([
            'step_key' => 'welcome',
            'title' => 'Welcome Step',
        ]);

        // Assert
        $widget->refresh();
        expect($widget->steps)->toHaveCount(1);
        expect($widget->steps->first()->step_key)->toBe('welcome');
    });

    test('widget can have pricing rules added', function () {
        // Arrange
        $widget = Widget::factory()->create();

        // Act
        WidgetPricing::factory()->for($widget)->create([
            'category' => 'moveSize',
        ]);

        WidgetPricing::factory()->for($widget)->create([
            'category' => 'serviceType',
        ]);

        // Assert
        $widget->refresh();
        expect($widget->pricing)->toHaveCount(2);
    });

    test('widget can be published', function () {
        // Arrange
        $widget = Widget::factory()->create(['status' => 'draft']);

        // Act
        $widget->update(['status' => 'published']);

        // Assert
        expect($widget->status)->toBe('published');
        expect($widget->isPublished())->toBeTrue();
    });

    test('widget can be paused', function () {
        // Arrange
        $widget = Widget::factory()->published()->create();

        // Act
        $widget->update(['status' => 'paused']);

        // Assert
        expect($widget->status)->toBe('paused');
        expect($widget->isPublished())->toBeFalse();
    });

    test('widget can collect leads', function () {
        // Arrange
        $widget = Widget::factory()->published()->create();

        // Act
        $lead = WidgetLead::factory()->for($widget)->create([
            'contact_info' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '555-1234',
            ],
            'estimated_value' => 650.00,
        ]);

        // Assert
        $widget->refresh();
        expect($widget->leads)->toHaveCount(1);
        expect($widget->leads->first()->getContactName())->toBe('John Doe');
        expect($widget->leads->first()->estimated_value)->toBe('650.00');
    });

    test('widget can have multiple enabled modules', function () {
        // Arrange
        $modules = [
            'service-selection',
            'project-scope',
            'date-selection',
            'contact-info',
        ];

        // Act
        $widget = Widget::factory()->create([
            'enabled_modules' => $modules,
        ]);

        // Assert
        expect($widget->enabled_modules)->toBe($modules);
        expect($widget->enabled_modules)->toHaveCount(4);
    });

    test('widget module configs can be updated', function () {
        // Arrange
        $widget = Widget::factory()->create([
            'module_configs' => [
                'service-selection' => [
                    'title' => 'Old Title',
                ],
            ],
        ]);

        // Act
        $widget->update([
            'module_configs' => [
                'service-selection' => [
                    'title' => 'New Title',
                    'subtitle' => 'New Subtitle',
                ],
            ],
        ]);

        // Assert
        expect($widget->module_configs['service-selection']['title'])->toBe('New Title');
        expect($widget->module_configs['service-selection']['subtitle'])->toBe('New Subtitle');
    });

    test('widget branding can be customized', function () {
        // Arrange
        $branding = [
            'primary_color' => '#FF5733',
            'secondary_color' => '#333333',
            'company_name' => 'Acme Corp',
            'logo_url' => 'https://example.com/logo.png',
            'font_family' => 'Roboto',
        ];

        // Act
        $widget = Widget::factory()->create(['branding' => $branding]);

        // Assert
        expect($widget->branding)->toBe($branding);
        expect($widget->branding['primary_color'])->toBe('#FF5733');
        expect($widget->branding['company_name'])->toBe('Acme Corp');
    });

    test('widget settings can be configured', function () {
        // Arrange
        $settings = [
            'tax_rate' => 0.10,
            'service_area_miles' => 75,
            'minimum_job_price' => 200,
            'show_price_ranges' => false,
        ];

        // Act
        $widget = Widget::factory()->create(['settings' => $settings]);

        // Assert
        expect($widget->settings)->toBe($settings);
        expect($widget->settings['tax_rate'])->toBe(0.10);
        expect($widget->settings['service_area_miles'])->toBe(75);
    });

    test('deleting widget cascades to steps', function () {
        // Arrange
        $widget = Widget::factory()->create();
        WidgetStep::factory()->count(3)->for($widget)->create();

        $widgetId = $widget->id;

        // Act
        $widget->delete();

        // Assert
        expect(Widget::find($widgetId))->toBeNull();
        expect(WidgetStep::where('widget_id', $widgetId)->count())->toBe(0);
    });

    test('deleting widget cascades to leads', function () {
        // Arrange
        $widget = Widget::factory()->create();
        WidgetLead::factory()->count(5)->for($widget)->create();

        $widgetId = $widget->id;

        // Act
        $widget->delete();

        // Assert
        expect(Widget::find($widgetId))->toBeNull();
        expect(WidgetLead::where('widget_id', $widgetId)->count())->toBe(0);
    });

    test('deleting widget cascades to pricing', function () {
        // Arrange
        $widget = Widget::factory()->create();
        WidgetPricing::factory()->count(2)->for($widget)->create();

        $widgetId = $widget->id;

        // Act
        $widget->delete();

        // Assert
        expect(Widget::find($widgetId))->toBeNull();
        expect(WidgetPricing::where('widget_id', $widgetId)->count())->toBe(0);
    });

    test('company can have multiple widgets', function () {
        // Arrange
        $company = Company::factory()->create();

        // Act
        Widget::factory()->count(3)->for($company)->create();

        // Assert
        expect($company->widgets)->toHaveCount(3);
    });

    test('company can access only published widgets', function () {
        // Arrange
        $company = Company::factory()->create();

        Widget::factory()->for($company)->published()->create();
        Widget::factory()->for($company)->published()->create();
        Widget::factory()->for($company)->create(['status' => 'draft']);
        Widget::factory()->for($company)->paused()->create();

        // Act & Assert
        expect($company->publishedWidgets)->toHaveCount(2);
    });
});

describe('Widget Steps', function () {
    test('step belongs to widget', function () {
        // Arrange
        $widget = Widget::factory()->create();
        $step = WidgetStep::factory()->for($widget)->create();

        // Act & Assert
        expect($step->widget)->toBeInstanceOf(Widget::class);
        expect($step->widget->id)->toBe($widget->id);
    });

    test('steps can be reordered', function () {
        // Arrange
        $widget = Widget::factory()->create();

        $step1 = WidgetStep::factory()->for($widget)->create(['order_index' => 1]);
        $step2 = WidgetStep::factory()->for($widget)->create(['order_index' => 2]);
        $step3 = WidgetStep::factory()->for($widget)->create(['order_index' => 3]);

        // Act
        $step1->update(['order_index' => 3]);
        $step2->update(['order_index' => 1]);
        $step3->update(['order_index' => 2]);

        // Assert
        $steps = $widget->fresh()->steps;
        expect($steps->pluck('id')->toArray())
            ->toBe([$step2->id, $step3->id, $step1->id]);
    });

    test('step can be disabled', function () {
        // Arrange
        $step = WidgetStep::factory()->create(['is_enabled' => true]);

        // Act
        $step->update(['is_enabled' => false]);

        // Assert
        expect($step->is_enabled)->toBeFalse();
    });
});

describe('Widget Leads', function () {
    test('lead belongs to widget', function () {
        // Arrange
        $widget = Widget::factory()->create();
        $lead = WidgetLead::factory()->for($widget)->create();

        // Act & Assert
        expect($lead->widget)->toBeInstanceOf(Widget::class);
        expect($lead->widget->id)->toBe($widget->id);
    });

    test('lead status can be updated', function () {
        // Arrange
        $lead = WidgetLead::factory()->create(['status' => 'new']);

        // Act & Assert
        $lead->update(['status' => 'contacted']);
        expect($lead->status)->toBe('contacted');

        $lead->update(['status' => 'converted']);
        expect($lead->status)->toBe('converted');
    });

    test('lead contact methods return correct values', function () {
        // Arrange
        $lead = WidgetLead::factory()->create([
            'contact_info' => [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'phone' => '555-5678',
            ],
        ]);

        // Act & Assert
        expect($lead->getContactName())->toBe('Jane Smith');
        expect($lead->getContactEmail())->toBe('jane@example.com');
    });

    test('lead contact methods handle missing data', function () {
        // Arrange
        $lead = WidgetLead::factory()->create([
            'contact_info' => [],
        ]);

        // Act & Assert
        expect($lead->getContactName())->toBe('Unknown');
        expect($lead->getContactEmail())->toBe('');
    });
});
