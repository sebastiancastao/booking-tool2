<?php

use App\Models\Company;
use App\Models\Widget;
use App\Models\WidgetPricing;
use App\Models\WidgetStep;

describe('Widget Config API', function () {
    test('returns widget configuration for valid widget key', function () {
        // Arrange
        $company = Company::factory()->create();
        $widget = Widget::factory()
            ->for($company)
            ->published()
            ->create([
                'widget_key' => 'test-widget-key-123',
                'branding' => [
                    'primary_color' => '#F4C443',
                    'company_name' => 'Test Company',
                ],
            ]);

        WidgetStep::factory()
            ->for($widget)
            ->create([
                'step_key' => 'welcome',
                'title' => 'Welcome',
                'order_index' => 1,
            ]);

        // Act
        $response = $this->getJson('/api/widget/test-widget-key-123/config');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'widget_id',
                'steps_data',
                'step_order',
                'branding',
                'pricing',
                'estimation_settings',
            ])
            ->assertJson([
                'widget_id' => 'test-widget-key-123',
                'branding' => [
                    'primary_color' => '#F4C443',
                    'company_name' => 'Test Company',
                ],
            ]);
    });

    test('returns 404 for invalid widget key', function () {
        // Act
        $response = $this->getJson('/api/widget/invalid-key/config');

        // Assert
        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Widget not found',
            ]);
    });

    test('returns 404 for draft widget', function () {
        // Arrange
        $widget = Widget::factory()->create([
            'status' => 'draft',
            'widget_key' => 'draft-widget-key',
        ]);

        // Act
        $response = $this->getJson('/api/widget/draft-widget-key/config');

        // Assert
        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Widget not found',
            ]);
    });

    test('returns 404 for paused widget', function () {
        // Arrange
        $widget = Widget::factory()
            ->paused()
            ->create([
                'widget_key' => 'paused-widget-key',
            ]);

        // Act
        $response = $this->getJson('/api/widget/paused-widget-key/config');

        // Assert
        $response->assertStatus(404);
    });

    test('includes widget steps in correct order', function () {
        // Arrange
        $widget = Widget::factory()->published()->create();

        WidgetStep::factory()->for($widget)->create([
            'step_key' => 'step-3',
            'order_index' => 3,
        ]);

        WidgetStep::factory()->for($widget)->create([
            'step_key' => 'step-1',
            'order_index' => 1,
        ]);

        WidgetStep::factory()->for($widget)->create([
            'step_key' => 'step-2',
            'order_index' => 2,
        ]);

        // Act
        $response = $this->getJson("/api/widget/{$widget->widget_key}/config");

        // Assert
        $response->assertStatus(200);
        $data = $response->json();

        expect($data['step_order'])->toBe(['step-1', 'step-2', 'step-3']);
    });

    test('includes pricing configuration', function () {
        // Arrange
        $widget = Widget::factory()->published()->create();

        WidgetPricing::factory()
            ->for($widget)
            ->create([
                'category' => 'moveSize',
                'pricing_rules' => [
                    'studio' => [
                        'basePrice' => 350,
                        'hours' => 3,
                    ],
                ],
            ]);

        WidgetPricing::factory()
            ->for($widget)
            ->create([
                'category' => 'serviceType',
                'pricing_rules' => [
                    'full-service' => [
                        'multiplier' => 1.0,
                    ],
                ],
            ]);

        // Act
        $response = $this->getJson("/api/widget/{$widget->widget_key}/config");

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('pricing.moveSize.studio.basePrice', 350)
            ->assertJsonPath('pricing.serviceType.full-service.multiplier', 1.0);
    });

    test('includes estimation settings from widget settings', function () {
        // Arrange
        $widget = Widget::factory()
            ->published()
            ->create([
                'settings' => [
                    'tax_rate' => 0.10,
                    'service_area_miles' => 50,
                    'minimum_job_price' => 200,
                    'show_price_ranges' => false,
                ],
            ]);

        // Act
        $response = $this->getJson("/api/widget/{$widget->widget_key}/config");

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('estimation_settings.tax_rate', 0.10)
            ->assertJsonPath('estimation_settings.service_area_miles', 50)
            ->assertJsonPath('estimation_settings.minimum_job_price', 200.0)
            ->assertJsonPath('estimation_settings.show_price_ranges', false);
    });

    test('uses default estimation settings when not specified', function () {
        // Arrange
        $widget = Widget::factory()
            ->published()
            ->create([
                'settings' => [],
            ]);

        // Act
        $response = $this->getJson("/api/widget/{$widget->widget_key}/config");

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('estimation_settings.tax_rate', 0.08)
            ->assertJsonPath('estimation_settings.service_area_miles', 100)
            ->assertJsonPath('estimation_settings.minimum_job_price', 0.0)
            ->assertJsonPath('estimation_settings.show_price_ranges', true);
    });

    test('generates steps from module configs when no steps exist', function () {
        // Arrange
        $widget = Widget::factory()
            ->published()
            ->create([
                'enabled_modules' => ['service-selection', 'project-scope'],
                'module_configs' => [
                    'service-selection' => [
                        'title' => 'Select Service',
                        'options' => [
                            [
                                'title' => 'Moving',
                                'description' => 'Moving service',
                            ],
                        ],
                    ],
                    'project-scope' => [
                        'title' => 'Project Size',
                        'options' => [
                            [
                                'title' => 'Small',
                                'base_price' => 200,
                                'estimated_hours' => 2,
                            ],
                        ],
                    ],
                ],
            ]);

        // Act
        $response = $this->getJson("/api/widget/{$widget->widget_key}/config");

        // Assert
        $response->assertStatus(200);
        $data = $response->json();

        expect($data['steps_data'])->toHaveKeys(['service-selection', 'project-scope']);
        expect($data['step_order'])->toBe(['service-selection', 'project-scope']);
    });
});
