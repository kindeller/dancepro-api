<?php

namespace App\Providers;

use App\Features\Customers\Support\UserType;
use App\Features\Downloads\Models\DownloadLink;
use App\Features\Downloads\Policies\DownloadLinkPolicy;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Policies\MediaAssetPolicy;
use App\Features\Media\Policies\MediaCollectionPolicy;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Gate::policy(MediaCollection::class, MediaCollectionPolicy::class);
        Gate::policy(MediaAsset::class, MediaAssetPolicy::class);

        Gate::define('viewCompetitionObjects', fn (User $user): bool => $this->isStaff($user));
        Gate::define('manageStudios', fn (User $user): bool => $this->isStaff($user));
        Gate::define('manageConcerts', fn (User $user): bool => $this->isStaff($user));
        Gate::define('viewStaffMedia', fn (User $user): bool => $this->isStaff($user));

        RateLimiter::for('media-uploads', fn (Request $request): Limit => Limit::perMinute(120)
            ->by((string) ($request->user()?->id ?? $request->ip())));
    }

    private function isStaff(User $user): bool
    {
        return $user->is_active && in_array($user->type, [UserType::Staff->value, UserType::Admin->value], true);
    }
}
