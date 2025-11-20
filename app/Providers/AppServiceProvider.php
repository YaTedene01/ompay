<?php

namespace App\Providers;

use App\Http\Controllers\Api\CompteController;
use App\Repository\CompteRepository;
use App\Repository\UserRepository;
use App\Services\CompteService;
use App\Services\UserService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CompteRepository::class, function ($app) {
            return new CompteRepository();
        });

        $this->app->singleton(UserRepository::class, function ($app) {
            return new UserRepository();
        });

        $this->app->singleton(CompteService::class, function ($app) {
            return new CompteService($app->make(CompteRepository::class));
        });

        $this->app->singleton(UserService::class, function ($app) {
            return new UserService($app->make(UserRepository::class));
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
