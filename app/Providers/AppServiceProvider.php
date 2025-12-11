<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Repository bindings
        $this->app->bind(
            \App\Repositories\LeadRepository::class,
            function ($app) {
                return new \App\Repositories\LeadRepository(new \App\Models\Lead());
            }
        );

        $this->app->bind(
            \App\Repositories\ClientRepository::class,
            function ($app) {
                return new \App\Repositories\ClientRepository(new \App\Models\Client());
            }
        );

        $this->app->bind(
            \App\Repositories\ProjectRepository::class,
            function ($app) {
                return new \App\Repositories\ProjectRepository(new \App\Models\Project());
            }
        );

        $this->app->bind(
            \App\Repositories\TaskRepository::class,
            function ($app) {
                return new \App\Repositories\TaskRepository(new \App\Models\Task());
            }
        );

        $this->app->bind(
            \App\Repositories\TeamRepository::class,
            function ($app) {
                return new \App\Repositories\TeamRepository(new \App\Models\Team());
            }
        );

        $this->app->bind(
            \App\Repositories\PasswordVaultRepository::class,
            function ($app) {
                return new \App\Repositories\PasswordVaultRepository(new \App\Models\PasswordVault());
            }
        );

        $this->app->bind(
            \App\Repositories\ActivityLogRepository::class,
            function ($app) {
                return new \App\Repositories\ActivityLogRepository(new \App\Models\ActivityLog());
            }
        );

        $this->app->bind(
            \App\Repositories\DeveloperPaymentRepository::class,
            function ($app) {
                return new \App\Repositories\DeveloperPaymentRepository(new \App\Models\DeveloperPayment());
            }
        );

        $this->app->bind(
            \App\Repositories\ClientPaymentRepository::class,
            function ($app) {
                return new \App\Repositories\ClientPaymentRepository(new \App\Models\ClientPayment());
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
