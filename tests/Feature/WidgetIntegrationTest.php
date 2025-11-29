<?php

use App\Models\Widget;
use App\Models\WidgetLead;
use App\Models\WidgetPricing;
use App\Models\WidgetStep;

describe('Widget Integration', function () {
    test('complete widget flow from creation to lead capture', function () {
        // Step 1: Create a widget
        $widget = Widget::factory()->create([
            'name' => 'Moving Services Widget',
            'service_category' => 'moving-services',
            'status' => 'draft',
            'branding' => [
                'primary_color' => '#F4C443',
                'company_name' => 'Best Movers',
            ],
            'settings' => [
                'tax_rate' => 0.08,
                'service_area_miles' => 100,
            ],
        ]);

        expect($widget->status)->toBe('draft');

        // Step 2: Add steps to the widget
        WidgetStep::factory()->for($widget)->create([
            'step_key' => 'service-selection',
            'title' => 'Select Your Service',
            'order_index' => 1,
        ]);

        WidgetStep::factory()->for($widget)->create([
            'step_key' => 'project-scope',
            'title' => 'Project Size',
            'order_index' => 2,
        ]);

        WidgetStep::factory()->for($widget)->create([
            'step_key' => 'contact-info',
            'title' => 'Your Information',
            'order_index' => 3,
        ]);

        expect($widget->fresh()->steps)->toHaveCount(3);

        // Step 3: Add pricing rules
        WidgetPricing::factory()->for($widget)->create([
            'category' => 'moveSize',
            'pricing_rules' => [
                'studio' => ['basePrice' => 350, 'hours' => 3],
                '1-bedroom' => ['basePrice' => 450, 'hours' => 4],
            ],
        ]);

        expect($widget->fresh()->pricing)->toHaveCount(1);

        // Step 4: Publish the widget
        $widget->update(['status' => 'published']);

        expect($widget->isPublished())->toBeTrue();

        // Step 5: Fetch widget configuration via API
        $response = $this->getJson("/api/widget/{$widget->widget_key}/config");

        $response->assertStatus(200);
        $config = $response->json();

        expect($config)->toHaveKeys([
            'widget_id',
            'steps_data',
            'step_order',
            'branding',
            'pricing',
        ]);

        expect($config['step_order'])->toHaveCount(3);
        expect($config['branding']['company_name'])->toBe('Best Movers');
        expect($config['pricing'])->toHaveKey('moveSize');

        // Step 6: Simulate lead submission
        $lead = WidgetLead::factory()->for($widget)->create([
            'lead_data' => [
                'serviceType' => 'full-service',
                'projectScope' => '1-bedroom',
                'contactInfo' => [
                    'name' => 'John Customer',
                    'email' => 'john@example.com',
                ],
            ],
            'contact_info' => [
                'name' => 'John Customer',
                'email' => 'john@example.com',
                'phone' => '555-1234',
            ],
            'estimated_value' => 450.00,
            'status' => 'new',
        ]);

        // Step 7: Verify lead was captured
        expect($widget->fresh()->leads)->toHaveCount(1);
        expect($lead->getContactName())->toBe('John Customer');
        expect($lead->estimated_value)->toBe('450.00');
        expect($lead->status)->toBe('new');
    });

    test('widget configuration changes are reflected in API response', function () {
        // Arrange
        $widget = Widget::factory()->published()->create([
            'branding' => ['primary_color' => '#000000'],
        ]);

        WidgetStep::factory()->for($widget)->create([
            'step_key' => 'step-1',
            'order_index' => 1,
        ]);

        // Initial state
        $response1 = $this->getJson("/api/widget/{$widget->widget_key}/config");
        expect($response1->json('branding.primary_color'))->toBe('#000000');
        expect($response1->json('step_order'))->toHaveCount(1);

        // Act: Update widget
        $widget->update([
            'branding' => ['primary_color' => '#FF0000'],
        ]);

        WidgetStep::factory()->for($widget)->create([
            'step_key' => 'step-2',
            'order_index' => 2,
        ]);

        // Assert: Changes are reflected
        $response2 = $this->getJson("/api/widget/{$widget->widget_key}/config");
        expect($response2->json('branding.primary_color'))->toBe('#FF0000');
        expect($response2->json('step_order'))->toHaveCount(2);
    });

    test('pausing widget immediately stops API access', function () {
        // Arrange
        $widget = Widget::factory()->published()->create();

        // Verify it's accessible
        $response1 = $this->getJson("/api/widget/{$widget->widget_key}/config");
        $response1->assertStatus(200);

        // Act: Pause widget
        $widget->update(['status' => 'paused']);

        // Assert: No longer accessible
        $response2 = $this->getJson("/api/widget/{$widget->widget_key}/config");
        $response2->assertStatus(404);
    });

    test('widget with module configs generates valid configuration', function () {
        // Arrange
        $widget = Widget::factory()
            ->published()
            ->create([
                'enabled_modules' => [
                    'service-selection',
                    'project-scope',
                    'date-selection',
                    'contact-info',
                ],
                'module_configs' => [
                    'service-selection' => [
                        'title' => 'What service do you need?',
                        'options' => [
                            [
                                'title' => 'Residential Moving',
                                'description' => 'Home and apartment moves',
                                'icon' => 'Home',
                            ],
                            [
                                'title' => 'Commercial Moving',
                                'description' => 'Office and business relocations',
                                'icon' => 'Building',
                            ],
                        ],
                    ],
                    'project-scope' => [
                        'title' => 'How large is your move?',
                        'options' => [
                            [
                                'title' => 'Studio',
                                'base_price' => 350,
                                'estimated_hours' => 3,
                                'price_range_min' => 300,
                                'price_range_max' => 400,
                            ],
                            [
                                'title' => '1 Bedroom',
                                'base_price' => 450,
                                'estimated_hours' => 4,
                                'price_range_min' => 400,
                                'price_range_max' => 500,
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

        // Verify all modules are present
        expect($data['steps_data'])->toHaveKeys([
            'service-selection',
            'project-scope',
            'date-selection',
            'contact-info',
        ]);

        // Verify module options are properly formatted
        $serviceSelection = $data['steps_data']['service-selection'];
        expect($serviceSelection['title'])->toBe('What service do you need?');
        expect($serviceSelection['options'])->toHaveCount(2);
        expect($serviceSelection['options'][0]['title'])->toBe('Residential Moving');

        // Verify project-scope has estimation data
        $projectScope = $data['steps_data']['project-scope'];
        expect($projectScope['options'][0]['estimation'])->toHaveKeys([
            'base_price',
            'estimated_hours',
            'price_range_min',
            'price_range_max',
        ]);
    });

    test('multiple widgets can exist and be accessed independently', function () {
        // Arrange
        $widget1 = Widget::factory()->published()->create([
            'name' => 'Widget 1',
            'branding' => ['company_name' => 'Company A'],
        ]);

        $widget2 = Widget::factory()->published()->create([
            'name' => 'Widget 2',
            'branding' => ['company_name' => 'Company B'],
        ]);

        // Act
        $response1 = $this->getJson("/api/widget/{$widget1->widget_key}/config");
        $response2 = $this->getJson("/api/widget/{$widget2->widget_key}/config");

        // Assert
        $response1->assertStatus(200);
        $response2->assertStatus(200);

        expect($response1->json('widget_id'))->toBe($widget1->widget_key);
        expect($response2->json('widget_id'))->toBe($widget2->widget_key);

        expect($response1->json('branding.company_name'))->toBe('Company A');
        expect($response2->json('branding.company_name'))->toBe('Company B');
    });
});
