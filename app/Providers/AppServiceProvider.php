<?php

namespace App\Providers;

use App\Features\Crew\Services\CrewNavigationIndicators;
use App\Features\Customers\Support\UserType;
use App\Features\Downloads\Models\DownloadLink;
use App\Features\Downloads\Policies\DownloadLinkPolicy;
use App\Features\Exceptions\Services\AdminExceptionOverview;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewContract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AdminExceptionOverview::class);
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
        Gate::define('manageCrew', fn (User $user): bool => $this->isStaff($user));
        Gate::define('manageScheduling', fn (User $user): bool => $this->isStaff($user));

        View::composer('layouts.crew', function (ViewContract $view): void {
            $user = auth()->user();
            $view->with('crewNavigationIndicators', $user
                ? app(CrewNavigationIndicators::class)->for($user)
                : ['shifts' => 0, 'timesheets' => 0, 'chat' => 0]);
        });

        View::composer('layouts.admin', function (ViewContract $view): void {
            $user = auth()->user();
            $view->with('adminExceptionCount', $user && Gate::allows('manageScheduling')
                ? app(AdminExceptionOverview::class)->all()->where('severity', 'action')->count()
                : 0);
        });
    }

    private function isStaff(User $user): bool
    {
        return $user->is_active && in_array($user->type, [UserType::Staff->value, UserType::Admin->value], true);
    }
}
