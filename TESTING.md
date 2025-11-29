# Widget Testing Guide

This document provides comprehensive information about testing the Chalk Leads widget system.

## Overview

The widget testing suite includes:
- **Unit Tests**: Testing individual model methods and behaviors
- **Feature Tests**: Testing API endpoints and widget functionality
- **Integration Tests**: Testing complete widget workflows from creation to lead capture

## Test Structure

### Test Files

#### Feature Tests
- **[tests/Feature/WidgetConfigApiTest.php](tests/Feature/WidgetConfigApiTest.php)** - Tests for the public widget configuration API
- **[tests/Feature/WidgetManagementTest.php](tests/Feature/WidgetManagementTest.php)** - Tests for widget CRUD operations and relationships
- **[tests/Feature/WidgetIntegrationTest.php](tests/Feature/WidgetIntegrationTest.php)** - End-to-end integration tests

#### Unit Tests
- **[tests/Unit/WidgetTest.php](tests/Unit/WidgetTest.php)** - Tests for Widget model methods and configuration generation

### Database Factories

The following factories are available for testing:
- **[CompanyFactory](database/factories/CompanyFactory.php)** - Create test companies
- **[WidgetFactory](database/factories/WidgetFactory.php)** - Create test widgets with various configurations
- **[WidgetStepFactory](database/factories/WidgetStepFactory.php)** - Create test widget steps
- **[WidgetPricingFactory](database/factories/WidgetPricingFactory.php)** - Create test pricing rules
- **[WidgetLeadFactory](database/factories/WidgetLeadFactory.php)** - Create test leads

## Running Tests

### Run All Tests
```bash
cd booking-tool
php artisan test
```

### Run Specific Test Suite
```bash
# Run only feature tests
php artisan test --testsuite=Feature

# Run only unit tests
php artisan test --testsuite=Unit
```

### Run Specific Test File
```bash
# Run widget API tests
php artisan test tests/Feature/WidgetConfigApiTest.php

# Run widget model tests
php artisan test tests/Unit/WidgetTest.php
```

### Run Tests with Coverage
```bash
php artisan test --coverage
```

### Run Tests in Parallel
```bash
php artisan test --parallel
```

## Using Factories in Tests

### Basic Usage

```php
// Create a simple widget
$widget = Widget::factory()->create();

// Create a published widget
$widget = Widget::factory()->published()->create();

// Create a widget with specific attributes
$widget = Widget::factory()->create([
    'name' => 'My Custom Widget',
    'service_category' => 'home-services',
]);
```

### Advanced Factory Usage

```php
// Create a widget with company
$company = Company::factory()->create();
$widget = Widget::factory()->for($company)->create();

// Create a widget with steps
$widget = Widget::factory()->create();
WidgetStep::factory()->count(3)->for($widget)->create();

// Create a widget with pricing
$widget = Widget::factory()->create();
WidgetPricing::factory()->for($widget)->create([
    'category' => 'moveSize',
    'pricing_rules' => [
        'studio' => ['basePrice' => 350],
    ],
]);

// Create a widget with leads
$widget = Widget::factory()->published()->create();
WidgetLead::factory()->count(5)->for($widget)->create();

// Create a complete widget setup
$widget = Widget::factory()
    ->published()
    ->withModules(['service-selection', 'project-scope'])
    ->create();

WidgetStep::factory()
    ->for($widget)
    ->stepKey('welcome')
    ->order(1)
    ->create();
```

## Test Coverage

### Widget API Tests (WidgetConfigApiTest.php)
- ✅ Returns widget configuration for valid widget key
- ✅ Returns 404 for invalid widget key
- ✅ Returns 404 for draft widgets
- ✅ Returns 404 for paused widgets
- ✅ Includes widget steps in correct order
- ✅ Includes pricing configuration
- ✅ Includes estimation settings
- ✅ Uses default estimation settings when not specified
- ✅ Generates steps from module configs when no steps exist

### Widget Model Tests (WidgetTest.php)
- ✅ Creates widget with automatic widget key
- ✅ Widget relationships (company, steps, leads, pricing)
- ✅ isPublished() method for all statuses
- ✅ JSON casting for arrays (modules, configs, branding, settings)
- ✅ Steps ordered by order_index
- ✅ getConfigurationArray() structure and content
- ✅ Module option formatting with estimation fields
- ✅ Project-scope pricing estimation
- ✅ Service-type price multipliers

### Widget Management Tests (WidgetManagementTest.php)
- ✅ Widget creation with required fields
- ✅ Adding steps, pricing rules, and leads
- ✅ Publishing and pausing widgets
- ✅ Module and configuration management
- ✅ Branding and settings customization
- ✅ Cascade delete behavior
- ✅ Company relationships and filtering
- ✅ Step reordering and disabling
- ✅ Lead status updates
- ✅ Contact information handling

### Widget Integration Tests (WidgetIntegrationTest.php)
- ✅ Complete widget flow from creation to lead capture
- ✅ Configuration changes reflected in API
- ✅ Pausing widget stops API access
- ✅ Module configs generate valid configuration
- ✅ Multiple widgets operate independently

## Writing New Tests

### Example: Testing a New Widget Feature

```php
<?php

use App\Models\Widget;

test('new widget feature works correctly', function () {
    // Arrange - Set up test data
    $widget = Widget::factory()->published()->create([
        'settings' => ['new_feature' => true],
    ]);

    // Act - Perform the action
    $result = $widget->someNewMethod();

    // Assert - Verify the result
    expect($result)->toBeTrue();
});
```

### Example: Testing an API Endpoint

```php
test('api endpoint returns expected response', function () {
    // Arrange
    $widget = Widget::factory()->published()->create();

    // Act
    $response = $this->getJson("/api/widget/{$widget->widget_key}/config");

    // Assert
    $response->assertStatus(200)
        ->assertJsonStructure(['widget_id', 'steps_data']);
});
```

## Best Practices

1. **Use Factories**: Always use factories to create test data instead of manually creating records
2. **Descriptive Names**: Use clear, descriptive test names that explain what is being tested
3. **Arrange-Act-Assert**: Follow the AAA pattern in your tests
4. **Independent Tests**: Each test should be independent and not rely on other tests
5. **Clean Database**: Tests use an in-memory SQLite database that is reset after each test
6. **Test One Thing**: Each test should verify one specific behavior

## Continuous Integration

The test suite is configured to run automatically on:
- Every commit
- Pull requests
- Before deployment

Tests use an in-memory SQLite database for fast execution and complete isolation.

## Troubleshooting

### Tests Failing After Migration Changes
```bash
php artisan migrate:fresh
php artisan test
```

### Clear Test Cache
```bash
php artisan test:clear-cache
```

### Debug Specific Test
```bash
php artisan test tests/Feature/WidgetConfigApiTest.php --filter="returns widget configuration"
```

## Additional Resources

- [Pest Documentation](https://pestphp.com/)
- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [Factory Documentation](https://laravel.com/docs/eloquent-factories)
