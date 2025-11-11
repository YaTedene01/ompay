<?php

namespace App\Providers;

use App\Http\Controllers\Api\CompteController;
use App\Repository\CompteRepository;
use App\Services\CompteService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // CompteRepository (nouveau) - injection explicite demandée
        $this->app->singleton(CompteRepository::class, function ($app) {
            return new CompteRepository(new \App\Models\Compte());
        });

        $this->app->singleton(CompteService::class, function ($app) {
            return new CompteService($app->make(CompteRepository::class));
        });

        $this->app->singleton(CompteController::class, function ($app) {
            return new CompteController($app->make(CompteService::class));
        });

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
