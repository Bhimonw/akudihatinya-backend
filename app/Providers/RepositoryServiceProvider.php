<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\PuskesmasRepositoryInterface;
use App\Repositories\PuskesmasRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(
            PuskesmasRepositoryInterface::class,
            PuskesmasRepository::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
