<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Packk\Core\Actions\Customer\FallbackDistance\FallbackDistance;
use Packk\Core\Actions\Customer\FallbackDistance\MsMapa;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(FallbackDistance::class, MsMapa::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
