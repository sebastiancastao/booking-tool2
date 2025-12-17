# Module Config Save Issue - Diagnosis & Fix Guide

## Problem Statement
When editing `module_configs` (like "Pickup Location Challenges" stairs pricing) in the widget editor, changes are not persisting to the Supabase `widgets` table.

## ✅ What's Working

Based on testing, the following components are functioning correctly:

1. **Database Connection**: ✓ Working
2. **Widget Model**: ✓ `module_configs` is fillable and cast as JSON
3. **Save Mechanism**: ✓ Laravel CAN save nested JSON changes
4. **Backend Route**: ✓ Accepts and validates `module_configs`
5. **Preview Display**: ✓ Correctly pulls from database

## 🔍 Diagnosis Steps

### Step 1: Check if Data is Being Sent from Frontend

1. Open the widget edit page: http://localhost:8000/widgets/1/edit
2. Open browser DevTools (F12) → Network tab
3. Make a change to module configs (e.g., change stairs pricing)
4. Click "Save Changes"
5. Look at the PUT request to `/widgets/1`
6. Check the Request Payload for `module_configs`

**Expected**: Should see the full `module_configs` object with your changes

**If Missing**: Frontend is not sending the data → Fix in `edit-advanced.tsx`

### Step 2: Check Backend Logs

After saving, check the logs:

```bash
cd booking-tool
tail -n 100 storage/logs/laravel.log | findstr "WIDGET UPDATE"
```

Look for these log entries:

1. **`=== WIDGET UPDATE: Request ===`**
   - Check `module_configs_count` - should match number of configured modules

2. **`=== WIDGET UPDATE: Origin Challenges Stairs ===`**
   - `original_pricing_value`: What's currently in DB
   - `incoming_pricing_value`: What you're trying to save
   - Should show the new value you entered

3. **`=== WIDGET UPDATE: Saved Stairs Config ===`**
   - `pricing_value`: What was actually saved to DB
   - Should match your new value

### Step 3: Verify Database

Check what's actually in the database:

```bash
php artisan tinker --execute="echo json_encode(\App\Models\Widget::find(1)->module_configs['origin-challenges']['options'][0], JSON_PRETTY_PRINT);"
```

## 🛠️ Common Issues & Fixes

### Issue 1: Frontend Not Sending Data

**Symptom**: Request payload doesn't include `module_configs` changes

**Fix**: Ensure `updateModuleOption()` is being called when editing options

In `edit-advanced.tsx`, when updating an option field:
```typescript
// Make sure this function is called
updateModuleOption(moduleId, optionIndex, 'pricing_value', newValue);
```

### Issue 2: Validation Failing

**Symptom**: Logs show validation errors

**Fix**: Check validation rules in `routes/web.php` line 217:
```php
'module_configs' => 'nullable|array',
```

Make sure your data structure matches.

### Issue 3: Data Type Mismatch

**Symptom**: Data is saved but as wrong type (string instead of number)

**Fix**: Ensure numeric values are sent as numbers, not strings:
```typescript
// Good
pricing_value: 0.1

// Bad
pricing_value: "0.1"
```

### Issue 4: Nested Changes Not Detected

**Symptom**: No error, but changes don't persist

**Fix**: Already handled by our tests - Laravel DOES detect nested changes correctly.

## 📋 Testing Checklist

Run these commands to verify everything works:

```bash
# 1. Test save mechanism
php artisan widget:test-module-config-save 1

# 2. Check current database state
php artisan widget:test-preview-data 1

# 3. Watch logs in real-time
tail -f storage/logs/laravel.log
```

## 🎯 Step-by-Step Save Test

1. Go to: http://localhost:8000/widgets/1/edit
2. Navigate to "Configure Modules" step
3. Click on "Origin Challenges" module
4. Change the stairs `pricing_value` to `0.15` (15%)
5. Click "Save Changes"
6. Check logs for detailed save info
7. Run: `php artisan widget:test-preview-data 1`
8. Verify pricing_value is now `0.15`

## 🔧 Enhanced Logging

I've added detailed logging to the update route. Every save will now log:

- **Before Save**: Original vs incoming data
- **Origin Challenges Details**: Exact stairs config being sent
- **After Save**: What was actually saved to database

Check `storage/logs/laravel.log` after each save attempt.

## 📞 Next Steps

1. Make a test edit in the UI
2. Save and check the logs
3. Share the relevant log entries if the issue persists
4. We'll diagnose exactly where the data is getting lost

## 💡 Quick Fix

If you need to manually update the config right now:

```bash
php artisan tinker

$widget = \App\Models\Widget::find(1);
$configs = $widget->module_configs;
$configs['origin-challenges']['options'][0]['pricing_value'] = 0.15;
$widget->module_configs = $configs;
$widget->save();
exit
```

Then refresh the preview page.
