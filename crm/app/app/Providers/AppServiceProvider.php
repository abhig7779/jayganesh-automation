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
        // [devops] Every link, redirect and asset URL is https:// in production (crm/DEPLOY.md).
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
