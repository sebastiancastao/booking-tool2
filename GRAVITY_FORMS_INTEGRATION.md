# Gravity Forms API Integration - Setup Guide

## Overview

This application integrates with Gravity Forms API v1 to automatically submit widget quote forms to your WordPress Gravity Forms installation. When a user submits a quote through the widget preview, the data is automatically:

1. ✅ Sent via email to the admin
2. 📝 **Submitted to Gravity Forms** (NEW)
3. 💾 Logged to the database

## Architecture

The integration is built using a **server-side PHP approach** for security:

- **Backend (PHP):** Handles authentication and API communication
- **Frontend (React/Inertia):** Submits form data to Laravel backend
- **Gravity Forms API:** Receives authenticated requests from Laravel

### Why Server-Side?

- ✅ API credentials stay secure on the server
- ✅ No exposure of sensitive keys to the client
- ✅ Better error handling and logging
- ✅ Follows Laravel best practices

## Files Created/Modified

### New Files

1. **`app/Services/GravityFormsService.php`** - Core service for Gravity Forms authentication and submission
2. **`GRAVITY_FORMS_INTEGRATION.md`** - This documentation file

### Modified Files

1. **`app/Http/Controllers/QuoteController.php`** - Added Gravity Forms submission to the `send()` method
2. **`config/services.php`** - Added Gravity Forms configuration
3. **`.env`** - Added Gravity Forms environment variables

## Configuration

### Environment Variables

Add the following to your `.env` file (already configured):

```env
# Gravity Forms API Configuration
GRAVITY_FORMS_BASE_URL=https://www.atlantafurnituremovers.com/gravityformsapi
GRAVITY_FORMS_PUBLIC_KEY=0b7fbd1824
GRAVITY_FORMS_PRIVATE_KEY=27842c3fdf765bd
GRAVITY_FORMS_FORM_ID=3
```

### Current Configuration

- **Base URL:** `https://www.atlantafurnituremovers.com/gravityformsapi`
- **Public Key:** `0b7fbd1824`
- **Private Key:** `27842c3fdf765bd`
- **Default Form ID:** `3`

## Field Mapping

The form data is mapped to Gravity Forms fields as follows:

| Widget Field | Gravity Forms Field ID | Description |
|-------------|------------------------|-------------|
| contact-name | `input_1` | Customer name |
| contact-email | `input_2` | Customer email |
| contact-phone | `input_3` | Customer phone |
| origin-location | `input_4` | Starting location |
| target-location | `input_5` | Destination |
| date-selection | `input_6` | Requested date |
| project-scope | `input_7` | Project size/scope |
| service-selection | `input_8` | Selected service |

**Important:** Update these field IDs in `app/Services/GravityFormsService.php` (line 116-125) to match your actual Gravity Forms setup.

### How to Find Your Field IDs

1. Log into your WordPress admin
2. Go to Forms → Your Form → Edit
3. Click on each field to see its Field ID
4. Update the `$fieldMapping` array in `GravityFormsService.php`

## How It Works

### Submission Flow

When a user submits the widget form:

1. **Frontend:** Widget sends data to `/quotes/send` endpoint
2. **Backend:** `QuoteController@send` receives the request
3. **Email:** Sends email notification via Resend
4. **Gravity Forms:** Submits data to Gravity Forms API
5. **Response:** Returns success status with Gravity Forms data

### Authentication

The integration uses **Signature-based authentication (HMAC-SHA1)**:

1. For each API request, a signature is generated using:
   - Public key
   - Private key
   - HTTP method
   - Full URL
   - Expiration timestamp

2. The signature is calculated as:
   ```php
   hash_hmac('sha1', "{public_key}:{method}:{url}:{expires}", $privateKey, true)
   ```

3. The signature is base64-encoded and URL-encoded

4. The authenticated URL includes query parameters:
   ```
   ?api_key={public_key}&signature={signature}&expires={timestamp}
   ```

### Error Handling

- If Gravity Forms submission fails, it **does not** prevent the form from being submitted
- The email will still be sent to the admin
- Errors are logged to Laravel logs for debugging
- The API response includes `gravity_forms_submitted: true/false` to indicate success

## Testing

### 1. Test via Widget Preview

1. Navigate to a widget preview: `http://localhost:8000/widgets/{id}/preview`
2. Fill out and submit the form
3. Check the browser console for the API response
4. Look for:
   ```json
   {
     "message": "Quote sent successfully",
     "gravity_forms_submitted": true,
     "gravity_forms_data": {...}
   }
   ```

### 2. Verify in WordPress

1. Log into WordPress admin
2. Go to Forms → Entries
3. Check if the new submission appears for Form ID 3

### 3. Check Laravel Logs

```bash
tail -f storage/logs/laravel.log
```

Look for:
- `Gravity Forms submission succeeded` (on success)
- `Gravity Forms submission failed (non-critical)` (on error)

## API Response Structure

### Success Response

```json
{
  "message": "Quote sent successfully",
  "gravity_forms_submitted": true,
  "gravity_forms_data": {
    "is_valid": true,
    "page_number": 0,
    "source_page_number": 0,
    "confirmation_message": "Thank you for your submission!",
    "confirmation_redirect": null
  },
  "gravity_forms_error": null
}
```

### Error Response (Gravity Forms failed, but email sent)

```json
{
  "message": "Quote sent successfully",
  "gravity_forms_submitted": false,
  "gravity_forms_data": null,
  "gravity_forms_error": "Gravity Forms API request failed: 401 Unauthorized"
}
```

## Troubleshooting

### Common Issues

#### 1. "API request failed: 401 Unauthorized"
- **Cause:** Invalid signature or expired timestamp
- **Solution:** Check that your public/private keys are correct in `.env`

#### 2. "API request failed: 404 Not Found"
- **Cause:** Incorrect form ID or base URL
- **Solution:**
  - Verify the form ID exists in WordPress
  - Check that the base URL is correct (`GRAVITY_FORMS_BASE_URL`)

#### 3. Submissions not appearing in WordPress
- **Cause:** Field ID mismatch
- **Solution:** Update field IDs in `GravityFormsService.php` to match your Gravity Forms setup

#### 4. SSL Certificate Errors
- **Cause:** Local development environment
- **Solution:** The code currently disables SSL verification for local dev. In production, ensure valid SSL certificates are in place.

### Debugging Tips

1. **Enable verbose logging:**
   ```php
   // In GravityFormsService.php
   Log::debug('Gravity Forms request', [
       'url' => $auth['url'],
       'data' => $fieldData,
   ]);
   ```

2. **Test connection:**
   ```php
   // In tinker or a test route
   $service = new \App\Services\GravityFormsService();
   $connected = $service->testConnection();
   dd($connected); // Should return true if working
   ```

3. **Check Gravity Forms API logs:**
   - In WordPress, go to Forms → Settings → Logging
   - Enable API logging
   - Review logs after submission attempts

## Security Notes

- API credentials are stored in `.env` file (not in version control)
- Signatures expire after 1 hour by default
- All requests are made server-side (credentials never exposed to client)
- SSL verification is disabled for local dev only

### Production Security Checklist

- [ ] Move API credentials to environment variables (already done)
- [ ] Enable SSL certificate verification in production
- [ ] Review and update field mapping to match your form
- [ ] Test submission in production environment
- [ ] Monitor logs for any errors

## Widget Preview Styling

The widget preview has been updated with:
- ✅ White background
- ✅ Dark font colors (gray-950, gray-900, gray-600)
- ✅ Improved contrast and readability

Files modified:
- [booking-tool/resources/js/components/widget/widget-renderer.tsx](booking-tool/resources/js/components/widget/widget-renderer.tsx) (already had white background)

## Next Steps

1. ✅ Update field IDs to match your Gravity Forms setup
2. ✅ Test with a real form submission
3. ✅ Verify entries appear in WordPress
4. ✅ Monitor logs for any errors
5. ✅ (Production) Enable SSL certificate verification
6. ✅ (Production) Test in production environment

## Support

For Gravity Forms API documentation, visit:
https://docs.gravityforms.com/rest-api-v1/

For issues specific to this integration:
- Check Laravel logs: `storage/logs/laravel.log`
- Review the troubleshooting section above
- Test the connection using the `testConnection()` method

## Code Examples

### Testing in Tinker

```bash
php artisan tinker
```

```php
// Test the connection
$service = new \App\Services\GravityFormsService();
$connected = $service->testConnection();

// Test a submission
$formData = [
    'data' => [
        'contact-name' => 'John Doe',
        'contact-email' => 'john@example.com',
        'contact-phone' => '555-123-4567',
        'origin-location' => '123 Main St',
        'target-location' => '456 Oak Ave',
    ]
];

$result = $service->submitForm($formData);
dump($result);
```

### Manual API Call (for debugging)

```php
use App\Services\GravityFormsService;

Route::get('/test-gravity', function () {
    $service = new GravityFormsService();

    try {
        $result = $service->submitForm([
            'data' => [
                'contact-name' => 'Test User',
                'contact-email' => 'test@example.com',
            ]
        ]);

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});
```

## License

This integration is part of the Chalk Leads application and follows the same license terms.
