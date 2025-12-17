<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\WidgetEmbedController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Validation\ValidationException;

Route::get('/', function () {
    // If user is already authenticated, redirect to dashboard
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    
    // Otherwise show login page as homepage
    return Inertia::render('auth/chalk-login', [
        'canResetPassword' => Route::has('password.request'),
        'status' => session('status'),
    ]);
})->name('home');

Route::get('widgets/{widgetKey}/embed', [WidgetEmbedController::class, 'show'])->name('widgets.embed');
Route::post('quotes/send', [QuoteController::class, 'send'])
    ->name('quotes.send')
    ->withoutMiddleware([VerifyCsrfToken::class]); // Public endpoint for widget submissions; CSRF exempt for cross-origin embeds

// Places API proxy routes - allow cross-origin for embedded widgets
Route::get('api/places/autocomplete', [\App\Http\Controllers\PlacesController::class, 'autocomplete'])
    ->name('places.autocomplete')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::get('api/places/details', [\App\Http\Controllers\PlacesController::class, 'details'])
    ->name('places.details')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = auth()->user();
        $widgets = $user->company->widgets()->latest()->take(5)->get();
        $widgetCount = $user->company->widgets()->count();
        $leadCount = $user->company->widgets()->withCount('leads')->get()->sum('leads_count');
        
        return Inertia::render('chalk-dashboard', [
            'user' => [
                'name' => $user->name,
                'company' => [
                    'name' => $user->company->name,
                ]
            ],
            'widgets' => $widgets->map(fn($widget) => [
                'id' => $widget->id,
                'name' => $widget->name,
                'widget_key' => $widget->widget_key,
                'status' => $widget->status,
                'created_at' => $widget->created_at->format('M j, Y'),
            ]),
            'widgetCount' => $widgetCount,
            'leadCount' => $leadCount,
        ]);
    })->name('dashboard');
    
    // Widget management routes
    Route::get('widgets/create', function () {
        return Inertia::render('widgets/create-advanced');
    })->name('widgets.create');
    
    Route::post('widgets', function (\Illuminate\Http\Request $request) {
        Log::info('=== WIDGET STORE: Request ===', [
            'user_id' => auth()->id(),
            'company_id' => auth()->user()?->company_id,
            'content_type' => $request->header('Content-Type'),
            'keys' => array_keys($request->all()),
            'enabled_modules_type' => gettype($request->input('enabled_modules')),
            'enabled_modules_count' => is_array($request->input('enabled_modules')) ? count($request->input('enabled_modules')) : null,
            'module_configs_type' => gettype($request->input('module_configs')),
            'module_configs_count' => is_array($request->input('module_configs')) ? count($request->input('module_configs')) : null,
        ]);

        $domainRules = [
            'nullable',
            'string',
            'max:255',
            function (string $attribute, mixed $value, \Closure $fail): void {
                $value = trim((string) $value);

                if ($value === '') {
                    return;
                }

                if (filter_var($value, FILTER_VALIDATE_URL)) {
                    return;
                }

                if (filter_var('https://' . $value, FILTER_VALIDATE_URL)) {
                    return;
                }

                $fail('The domain field must be a valid URL or domain.');
            },
        ];

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'service_category' => 'required|string',
            'service_subcategory' => 'nullable|string|max:255',
            'company_name' => 'required|string|max:255',
            'domain' => $domainRules,
            'enabled_modules' => 'required|array',
            'module_configs' => 'nullable|array',
            'branding' => 'required|array',
            'branding.primary_color' => 'required|string',
            'branding.secondary_color' => 'required|string',
            'settings' => 'required|array',
            'settings.tax_rate' => 'required|numeric|min:0|max:1',
            'settings.service_area_miles' => 'required|integer|min:0',
            'settings.minimum_job_price' => 'required|numeric|min:0',
            'settings.show_price_ranges' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            Log::warning('=== WIDGET STORE: Validation Failed ===', [
                'user_id' => auth()->id(),
                'company_id' => auth()->user()?->company_id,
                'errors' => $validator->errors()->toArray(),
            ]);

            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        
        $widget = \App\Models\Widget::create([
            ...$validated,
            'company_id' => auth()->user()->company_id,
            'status' => 'draft', // New widgets start as draft
        ]);

        $widget->refresh();
        Log::info('=== WIDGET STORE: Created ===', [
            'widget_id' => $widget->id,
            'company_id' => $widget->company_id,
            'enabled_modules_count' => is_array($widget->enabled_modules) ? count($widget->enabled_modules) : null,
            'module_configs_keys_count' => is_array($widget->module_configs) ? count($widget->module_configs) : null,
        ]);
        
        return redirect()->route('dashboard')->with('success', 'Widget created successfully!');
    })->name('widgets.store');
    
    Route::get('widgets/{widget}/edit', function (\App\Models\Widget $widget) {
        // Ensure the widget belongs to the user's company
        if ($widget->company_id !== auth()->user()->company_id) {
            abort(403);
        }
        
        return Inertia::render('widgets/edit-advanced', [
            'widget' => $widget->load('company')
        ]);
    })->name('widgets.edit');
    
    Route::put('widgets/{widget}', function (\App\Models\Widget $widget, \Illuminate\Http\Request $request) {
        Log::info('=== WIDGET UPDATE: Request ===', [
            'user_id' => auth()->id(),
            'user_company_id' => auth()->user()?->company_id,
            'widget_id' => $widget->id,
            'widget_company_id' => $widget->company_id,
            'content_type' => $request->header('Content-Type'),
            'keys' => array_keys($request->all()),
            'enabled_modules_type' => gettype($request->input('enabled_modules')),
            'enabled_modules_count' => is_array($request->input('enabled_modules')) ? count($request->input('enabled_modules')) : null,
            'module_configs_type' => gettype($request->input('module_configs')),
            'module_configs_count' => is_array($request->input('module_configs')) ? count($request->input('module_configs')) : null,
        ]);

        $domainRules = [
            'nullable',
            'string',
            'max:255',
            function (string $attribute, mixed $value, \Closure $fail): void {
                $value = trim((string) $value);

                if ($value === '') {
                    return;
                }

                if (filter_var($value, FILTER_VALIDATE_URL)) {
                    return;
                }

                if (filter_var('https://' . $value, FILTER_VALIDATE_URL)) {
                    return;
                }

                $fail('The domain field must be a valid URL or domain.');
            },
        ];

        // Ensure the widget belongs to the user's company
        if ($widget->company_id !== auth()->user()->company_id) {
            Log::warning('=== WIDGET UPDATE: Forbidden ===', [
                'user_id' => auth()->id(),
                'user_company_id' => auth()->user()?->company_id,
                'widget_id' => $widget->id,
                'widget_company_id' => $widget->company_id,
            ]);
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'service_category' => 'required|string',
            'service_subcategory' => 'nullable|string|max:255',
            'company_name' => 'required|string|max:255',
            'domain' => $domainRules,
            'enabled_modules' => 'required|array',
            'module_configs' => 'nullable|array',
            'branding' => 'required|array',
            'branding.primary_color' => 'required|string',
            'branding.secondary_color' => 'required|string',
            'settings' => 'required|array',
            'settings.tax_rate' => 'required|numeric|min:0|max:1',
            'settings.service_area_miles' => 'required|integer|min:0',
            'settings.minimum_job_price' => 'required|numeric|min:0',
            'settings.show_price_ranges' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            Log::warning('=== WIDGET UPDATE: Validation Failed ===', [
                'user_id' => auth()->id(),
                'user_company_id' => auth()->user()?->company_id,
                'widget_id' => $widget->id,
                'errors' => $validator->errors()->toArray(),
            ]);

            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        Log::info('=== WIDGET UPDATE: Before Save ===', [
            'widget_id' => $widget->id,
            'original_enabled_modules_count' => is_array($widget->enabled_modules) ? count($widget->enabled_modules) : null,
            'incoming_enabled_modules_count' => is_array($validated['enabled_modules'] ?? null) ? count($validated['enabled_modules']) : null,
            'original_module_configs_keys_count' => is_array($widget->module_configs) ? count($widget->module_configs) : null,
            'incoming_module_configs_keys_count' => is_array($validated['module_configs'] ?? null) ? count($validated['module_configs']) : null,
        ]);

        // Log origin-challenges config changes in detail
        if (isset($validated['module_configs']['origin-challenges'])) {
            $incomingStairs = $validated['module_configs']['origin-challenges']['options'][0] ?? null;
            $originalStairs = $widget->module_configs['origin-challenges']['options'][0] ?? null;

            Log::info('=== WIDGET UPDATE: Origin Challenges Stairs ===', [
                'original_pricing_value' => $originalStairs['pricing_value'] ?? 'N/A',
                'incoming_pricing_value' => $incomingStairs['pricing_value'] ?? 'N/A',
                'original_description' => $originalStairs['description'] ?? 'N/A',
                'incoming_description' => $incomingStairs['description'] ?? 'N/A',
            ]);
        }

        $widget->fill($validated);

        $dirty = $widget->getDirty();
        Log::info('=== WIDGET UPDATE: Dirty ===', [
            'widget_id' => $widget->id,
            'dirty_keys' => array_keys($dirty),
        ]);

        $saved = $widget->save();
        $widget->refresh();

        Log::info('=== WIDGET UPDATE: After Save ===', [
            'widget_id' => $widget->id,
            'saved' => $saved,
            'enabled_modules_count' => is_array($widget->enabled_modules) ? count($widget->enabled_modules) : null,
            'module_configs_keys_count' => is_array($widget->module_configs) ? count($widget->module_configs) : null,
        ]);

        // Verify origin-challenges was saved correctly
        if (isset($widget->module_configs['origin-challenges']['options'][0])) {
            $savedStairs = $widget->module_configs['origin-challenges']['options'][0];
            Log::info('=== WIDGET UPDATE: Saved Stairs Config ===', [
                'pricing_value' => $savedStairs['pricing_value'] ?? 'N/A',
                'pricing_type' => $savedStairs['pricing_type'] ?? 'N/A',
                'description' => $savedStairs['description'] ?? 'N/A',
                'max_units' => $savedStairs['max_units'] ?? 'N/A',
            ]);
        }

        return redirect()->route('dashboard')->with('success', 'Widget updated successfully!');
    })->name('widgets.update');

    // Widget preview route - visualize and test your widget
    Route::get('widgets/{widget}/preview', function (\App\Models\Widget $widget) {
        // Ensure the widget belongs to the user's company
        if ($widget->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        // Force fresh data - refresh widget from database
        $widget->refresh();
        $widgetConfig = $widget->getConfigurationArray();

        return Inertia::render('widgets/preview', [
            'widget' => [
                'id' => $widget->id,
                'name' => $widget->name,
                'widget_key' => $widget->widget_key,
                'status' => $widget->status,
                'updated_at' => $widget->updated_at->toIso8601String(),
            ],
            'config' => $widgetConfig,
            // Add timestamp to force Inertia to treat this as new data
            'timestamp' => now()->timestamp,
        ]);
    })->name('widgets.preview');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
