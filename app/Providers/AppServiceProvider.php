<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $request = $this->app->make('request');
        $proto = $request->header('x-forwarded-proto');

        if (
            $request->server('HTTPS') === 'on'
            || ($proto && str_contains($proto, 'https'))
        ) {
            URL::forceScheme('https');
        }
    }
}
