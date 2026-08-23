<?php

namespace App\Providers;

use App\Features\Downloads\Models\DownloadLink;
use App\Features\Downloads\Policies\DownloadLinkPolicy;
use App\Features\Customers\Support\UserType;
use App\Models\User;
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

        Gate::define('viewCompetitionObjects', fn ($user): bool => $user->is_active);
        Gate::define('manageStudios', fn (User $user): bool => $this->isStaff($user));
        Gate::define('manageConcerts', fn (User $user): bool => $this->isStaff($user));
    }

    private function isStaff(User $user): bool
    {
        return $user->is_active && in_array($user->type, [UserType::Staff->value, UserType::Admin->value], true);
    }
}
