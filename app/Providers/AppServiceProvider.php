<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Outside production, a relation loaded lazily inside a loop throws instead of quietly adding a query per row.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
