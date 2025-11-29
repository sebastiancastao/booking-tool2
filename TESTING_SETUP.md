# Widget Testing Setup Guide

## Quick Start

Your widget testing suite is now ready! Here's how to get started.

## Prerequisites

### Option 1: Enable SQLite (Recommended for Testing)

SQLite is the fastest option for testing as it uses an in-memory database.

1. Open your `php.ini` file (usually at `C:\xampp\php\php.ini`)
2. Find and uncomment these lines (remove the semicolon):
   ```ini
   extension=pdo_sqlite
   extension=sqlite3
   ```
3. Restart Apache in XAMPP Control Panel
4. Verify SQLite is enabled:
   ```bash
   cd booking-tool
   php -m | findstr sqlite
   ```

### Option 2: Use MySQL for Testing

If you prefer to use MySQL (already configured in XAMPP):

1. Update `phpunit.xml` to use MySQL instead of SQLite:
   ```xml
   <env name="DB_CONNECTION" value="mysql"/>
   <env name="DB_DATABASE" value="testing"/>
   <env name="DB_USERNAME" value="root"/>
   <env name="DB_PASSWORD" value=""/>
   ```

2. Create a test database:
   ```bash
   php artisan db:create testing
   ```

   Or manually create it in phpMyAdmin at http://localhost/phpmyadmin

## Running Tests

### Run All Widget Tests
```bash
cd booking-tool
php artisan test tests/Feature/WidgetConfigApiTest.php
php artisan test tests/Feature/WidgetManagementTest.php
php artisan test tests/Feature/WidgetIntegrationTest.php
php artisan test tests/Unit/WidgetTest.php
```

### Run All Tests at Once
```bash
cd booking-tool
php artisan test --filter=Widget
```

### Run with Detailed Output
```bash
cd booking-tool
php artisan test tests/Feature/WidgetConfigApiTest.php -v
```

## Test Files Created

✅ **Database Factories** (for creating test data):
- [database/factories/CompanyFactory.php](database/factories/CompanyFactory.php)
- [database/factories/WidgetFactory.php](database/factories/WidgetFactory.php)
- [database/factories/WidgetStepFactory.php](database/factories/WidgetStepFactory.php)
- [database/factories/WidgetPricingFactory.php](database/factories/WidgetPricingFactory.php)
- [database/factories/WidgetLeadFactory.php](database/factories/WidgetLeadFactory.php)

✅ **Feature Tests**:
- [tests/Feature/WidgetConfigApiTest.php](tests/Feature/WidgetConfigApiTest.php) - Tests the public API endpoint
- [tests/Feature/WidgetManagementTest.php](tests/Feature/WidgetManagementTest.php) - Tests CRUD operations
- [tests/Feature/WidgetIntegrationTest.php](tests/Feature/WidgetIntegrationTest.php) - Tests complete workflows

✅ **Unit Tests**:
- [tests/Unit/WidgetTest.php](tests/Unit/WidgetTest.php) - Tests model methods and logic

✅ **Model Updates**:
- Added `HasFactory` trait to Widget, Company, WidgetStep, WidgetPricing, and WidgetLead models

## What's Being Tested

### API Endpoint Tests (9 tests)
- ✅ Valid widget configuration retrieval
- ✅ 404 responses for invalid/draft/paused widgets
- ✅ Steps in correct order
- ✅ Pricing configuration included
- ✅ Estimation settings (default and custom)
- ✅ Module config generation

### Model Tests (21 tests)
- ✅ Widget creation with auto-generated keys
- ✅ Relationships (company, steps, leads, pricing)
- ✅ Status checks (published, draft, paused)
- ✅ JSON casting for all array fields
- ✅ Configuration array generation
- ✅ Module option formatting
- ✅ Estimation field handling

### Management Tests (24 tests)
- ✅ Widget CRUD operations
- ✅ Module and config management
- ✅ Branding customization
- ✅ Settings configuration
- ✅ Cascade deletes
- ✅ Company relationships
- ✅ Step management and reordering
- ✅ Lead capture and status updates

### Integration Tests (5 tests)
- ✅ Complete widget lifecycle
- ✅ Configuration changes reflected in API
- ✅ Widget pausing stops access
- ✅ Module configs generate valid output
- ✅ Multiple widgets work independently

**Total: 59 comprehensive tests covering all widget functionality**

## Troubleshooting

### "could not find driver" Error

This means SQLite or MySQL drivers aren't enabled. Follow the Prerequisites section above.

### Database Migration Errors

```bash
cd booking-tool
php artisan migrate:fresh --env=testing
php artisan test
```

### Clear Cache

```bash
cd booking-tool
php artisan config:clear
php artisan cache:clear
php artisan test
```

## Example Test Usage

### Creating Test Data

```php
// Create a simple widget
$widget = Widget::factory()->create();

// Create a published widget with steps
$widget = Widget::factory()->published()->create();
WidgetStep::factory()->count(3)->for($widget)->create();

// Create a complete setup
$company = Company::factory()->create();
$widget = Widget::factory()
    ->for($company)
    ->published()
    ->create();

WidgetPricing::factory()->for($widget)->create();
WidgetLead::factory()->count(5)->for($widget)->create();
```

### Testing API Responses

```php
test('widget config is accessible', function () {
    $widget = Widget::factory()->published()->create();

    $response = $this->getJson("/api/widget/{$widget->widget_key}/config");

    $response->assertStatus(200)
        ->assertJsonStructure(['widget_id', 'steps_data', 'branding']);
});
```

## Next Steps

1. **Enable SQLite or configure MySQL** (see Prerequisites above)
2. **Run the tests** to verify everything works
3. **Add custom tests** as you build new features
4. **Run tests before commits** to catch issues early

## Need Help?

- See [TESTING.md](TESTING.md) for detailed testing guide
- Laravel Testing Docs: https://laravel.com/docs/testing
- Pest Docs: https://pestphp.com/
