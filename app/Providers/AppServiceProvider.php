<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        Schema::defaultStringLength(191);

        \Livewire\Livewire::listen('component.dehydrate', function ($component, $response) {
            Log::channel('host_power_log')->info('Livewire event', [
                'component' => get_class($component),
                'updates' => $response->effects['updates'] ?? [],
            ]);
        });
    }
}
