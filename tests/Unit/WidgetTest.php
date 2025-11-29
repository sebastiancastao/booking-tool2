<?php

use App\Models\Company;
use App\Models\Widget;
use App\Models\WidgetLead;
use App\Models\WidgetPricing;
use App\Models\WidgetStep;

describe('Widget Model', function () {
    test('creates widget with automatic widget key', function () {
        // Arrange & Act
        $widget = Widget::factory()->create(['widget_key' => null]);

        // Assert
        expect($widget->widget_key)->not->toBeNull();
        expect(strlen($widget->widget_key))->toBe(32);
    });

    test('belongs to a company', function () {
        // Arrange
        $company = Company::factory()->create();
        $widget = Widget::factory()->for($company)->create();

        // Act & Assert
        expect($widget->company)->toBeInstanceOf(Company::class);
        expect($widget->company->id)->toBe($company->id);
    });

    test('has many steps', function () {
        // Arrange
        $widget = Widget::factory()->create();
        WidgetStep::factory()->count(3)->for($widget)->create();

        // Act & Assert
        expect($widget->steps)->toHaveCount(3);
        expect($widget->steps->first())->toBeInstanceOf(WidgetStep::class);
    });

    test('has many leads', function () {
        // Arrange
        $widget = Widget::factory()->create();
        WidgetLead::factory()->count(5)->for($widget)->create();

        // Act & Assert
        expect($widget->leads)->toHaveCount(5);
        expect($widget->leads->first())->toBeInstanceOf(WidgetLead::class);
    });

    test('has many pricing rules', function () {
        // Arrange
        $widget = Widget::factory()->create();
        WidgetPricing::factory()->count(2)->for($widget)->create();

        // Act & Assert
        expect($widget->pricing)->toHaveCount(2);
        expect($widget->pricing->first())->toBeInstanceOf(WidgetPricing::class);
    });

    test('isPublished returns true for published widgets', function () {
        // Arrange
        $widget = Widget::factory()->published()->create();

        // Act & Assert
        expect($widget->isPublished())->toBeTrue();
    });

    test('isPublished returns false for draft widgets', function () {
        // Arrange
        $widget = Widget::factory()->create(['status' => 'draft']);

        // Act & Assert
        expect($widget->isPublished())->toBeFalse();
    });

    test('isPublished returns false for paused widgets', function () {
        // Arrange
        $widget = Widget::factory()->paused()->create();

        // Act & Assert
        expect($widget->isPublished())->toBeFalse();
    });

    test('casts enabled_modules to array', function () {
        // Arrange
        $modules = ['service-selection', 'project-scope', 'contact-info'];
        $widget = Widget::factory()->create(['enabled_modules' => $modules]);

        // Act & Assert
        expect($widget->enabled_modules)->toBeArray();
        expect($widget->enabled_modules)->toBe($modules);
    });

    test('casts module_configs to array', function () {
        // Arrange
        $configs = [
            'service-selection' => ['title' => 'Select Service'],
        ];
        $widget = Widget::factory()->create(['module_configs' => $configs]);

        // Act & Assert
        expect($widget->module_configs)->toBeArray();
        expect($widget->module_configs)->toBe($configs);
    });

    test('casts branding to array', function () {
        // Arrange
        $branding = [
            'primary_color' => '#F4C443',
            'company_name' => 'Test Co',
        ];
        $widget = Widget::factory()->create(['branding' => $branding]);

        // Act & Assert
        expect($widget->branding)->toBeArray();
        expect($widget->branding)->toBe($branding);
    });

    test('casts settings to array', function () {
        // Arrange
        $settings = [
            'tax_rate' => 0.08,
            'service_area_miles' => 100,
        ];
        $widget = Widget::factory()->create(['settings' => $settings]);

        // Act & Assert
        expect($widget->settings)->toBeArray();
        expect($widget->settings)->toBe($settings);
    });

    test('steps are ordered by order_index', function () {
        // Arrange
        $widget = Widget::factory()->create();

        WidgetStep::factory()->for($widget)->create([
            'step_key' => 'step-c',
            'order_index' => 3,
        ]);

        WidgetStep::factory()->for($widget)->create([
            'step_key' => 'step-a',
            'order_index' => 1,
        ]);

        WidgetStep::factory()->for($widget)->create([
            'step_key' => 'step-b',
            'order_index' => 2,
        ]);

        // Act
        $steps = $widget->fresh()->steps;

        // Assert
        expect($steps->pluck('step_key')->toArray())
            ->toBe(['step-a', 'step-b', 'step-c']);
    });
});

describe('Widget Configuration Generation', function () {
    test('getConfigurationArray returns correct structure with steps', function () {
        // Arrange
        $widget = Widget::factory()->create([
            'branding' => ['primary_color' => '#FF0000'],
            'settings' => ['tax_rate' => 0.05],
        ]);

        WidgetStep::factory()->for($widget)->create([
            'step_key' => 'welcome',
            'title' => 'Welcome',
            'order_index' => 1,
        ]);

        // Act
        $config = $widget->getConfigurationArray();

        // Assert
        expect($config)->toHaveKeys([
            'widget_id',
            'steps_data',
            'step_order',
            'branding',
            'pricing',
            'estimation_settings',
        ]);
        expect($config['widget_id'])->toBe($widget->widget_key);
        expect($config['steps_data'])->toHaveKey('welcome');
        expect($config['step_order'])->toBe(['welcome']);
    });

    test('getConfigurationArray includes all step properties', function () {
        // Arrange
        $widget = Widget::factory()->create();

        WidgetStep::factory()->for($widget)->create([
            'step_key' => 'test-step',
            'title' => 'Test Title',
            'subtitle' => 'Test Subtitle',
            'prompt' => ['message' => 'Test', 'type' => 'text'],
            'options' => [['id' => '1', 'title' => 'Option 1']],
            'buttons' => ['primary' => ['text' => 'Next']],
            'layout' => ['type' => 'grid'],
            'validation' => ['required' => true],
        ]);

        // Act
        $config = $widget->getConfigurationArray();
        $stepData = $config['steps_data']['test-step'];

        // Assert
        expect($stepData)->toHaveKeys([
            'id',
            'title',
            'subtitle',
            'prompt',
            'options',
            'buttons',
            'layout',
            'validation',
        ]);
        expect($stepData['title'])->toBe('Test Title');
        expect($stepData['subtitle'])->toBe('Test Subtitle');
    });

    test('getConfigurationArray generates steps from module configs when no steps exist', function () {
        // Arrange
        $widget = Widget::factory()->create([
            'enabled_modules' => ['service-selection'],
            'module_configs' => [
                'service-selection' => [
                    'title' => 'Select Your Service',
                    'subtitle' => 'What can we help with?',
                    'options' => [
                        [
                            'title' => 'Moving',
                            'description' => 'Full service moving',
                            'icon' => 'Truck',
                        ],
                    ],
                ],
            ],
        ]);

        // Act
        $config = $widget->getConfigurationArray();

        // Assert
        expect($config['steps_data'])->toHaveKey('service-selection');
        expect($config['steps_data']['service-selection']['title'])->toBe('Select Your Service');
        expect($config['step_order'])->toBe(['service-selection']);
    });

    test('getConfigurationArray includes pricing configuration', function () {
        // Arrange
        $widget = Widget::factory()->create();

        WidgetPricing::factory()->for($widget)->create([
            'category' => 'moveSize',
            'pricing_rules' => [
                'studio' => ['basePrice' => 350],
            ],
        ]);

        // Act
        $config = $widget->getConfigurationArray();

        // Assert
        expect($config['pricing'])->toHaveKey('moveSize');
        expect($config['pricing']['moveSize']['studio']['basePrice'])->toBe(350);
    });

    test('getConfigurationArray includes estimation settings with defaults', function () {
        // Arrange
        $widget = Widget::factory()->create(['settings' => []]);

        // Act
        $config = $widget->getConfigurationArray();

        // Assert
        expect($config['estimation_settings'])->toBe([
            'tax_rate' => 0.08,
            'service_area_miles' => 100,
            'minimum_job_price' => 0.0,
            'show_price_ranges' => true,
            'currency' => 'USD',
            'currency_symbol' => '$',
        ]);
    });

    test('getConfigurationArray uses custom settings when provided', function () {
        // Arrange
        $widget = Widget::factory()->create([
            'settings' => [
                'tax_rate' => 0.10,
                'service_area_miles' => 50,
                'minimum_job_price' => 250,
                'show_price_ranges' => false,
            ],
        ]);

        // Act
        $config = $widget->getConfigurationArray();

        // Assert
        expect($config['estimation_settings']['tax_rate'])->toBe(0.10);
        expect($config['estimation_settings']['service_area_miles'])->toBe(50);
        expect($config['estimation_settings']['minimum_job_price'])->toBe(250.0);
        expect($config['estimation_settings']['show_price_ranges'])->toBe(false);
    });

    test('formats project-scope module options with estimation fields', function () {
        // Arrange
        $widget = Widget::factory()->create([
            'enabled_modules' => ['project-scope'],
            'module_configs' => [
                'project-scope' => [
                    'title' => 'Project Size',
                    'options' => [
                        [
                            'title' => 'Small Project',
                            'description' => 'Small size',
                            'base_price' => 300,
                            'estimated_hours' => 3,
                            'price_range_min' => 250,
                            'price_range_max' => 350,
                        ],
                    ],
                ],
            ],
        ]);

        // Act
        $config = $widget->getConfigurationArray();
        $option = $config['steps_data']['project-scope']['options'][0];

        // Assert
        expect($option)->toHaveKey('estimation');
        expect($option['estimation']['base_price'])->toBe(300.0);
        expect($option['estimation']['estimated_hours'])->toBe(3.0);
        expect($option['estimation']['price_range_min'])->toBe(250.0);
        expect($option['estimation']['price_range_max'])->toBe(350.0);
    });

    test('formats service-type module options with price multiplier', function () {
        // Arrange
        $widget = Widget::factory()->create([
            'enabled_modules' => ['service-type'],
            'module_configs' => [
                'service-type' => [
                    'title' => 'Service Type',
                    'options' => [
                        [
                            'title' => 'Premium Service',
                            'price_multiplier' => 1.5,
                        ],
                    ],
                ],
            ],
        ]);

        // Act
        $config = $widget->getConfigurationArray();
        $option = $config['steps_data']['service-type']['options'][0];

        // Assert
        expect($option)->toHaveKey('estimation');
        expect($option['estimation']['price_multiplier'])->toBe(1.5);
    });
});
