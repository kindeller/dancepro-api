<?php

namespace App\Providers;

use App\Features\Downloads\Models\DownloadLink;
use App\Features\Downloads\Policies\DownloadLinkPolicy;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(DownloadLink::class, DownloadLinkPolicy::class);
    }
}
