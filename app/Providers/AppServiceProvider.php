<?php

namespace App\Providers;

use App\Features\Crew\Services\CrewNavigationIndicators;
use App\Features\Downloads\Models\DownloadLink;
use App\Features\Downloads\Policies\DownloadLinkPolicy;
use App\Features\Exceptions\Services\AdminExceptionOverview;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('login', function (Request $request): array {
            $emailAndIp = hash('sha256', mb_strtolower($request->string('email')->toString()).'|'.$request->ip());

            return [
                Limit::perMinute(5)->by('login:'.$emailAndIp),
                Limit::perMinute(30)->by('login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('password-reset-link', function (Request $request): array {
            $emailAndIp = hash('sha256', mb_strtolower($request->string('email')->toString()).'|'.$request->ip());

            return [
                Limit::perMinute(3)->by('password-reset-link:'.$emailAndIp),
                Limit::perHour(10)->by('password-reset-link-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('password-reset', function (Request $request): array {
            $emailAndIp = hash('sha256', mb_strtolower($request->string('email')->toString()).'|'.$request->ip());

            return [
                Limit::perMinute(5)->by('password-reset:'.$emailAndIp),
                Limit::perHour(20)->by('password-reset-ip:'.$request->ip()),
            ];
        });

        Gate::policy(DownloadLink::class, DownloadLinkPolicy::class);

        Gate::define('viewCompetitionObjects', fn (User $user): bool => $user->canAccessAdmin());
        Gate::define('manageStudios', fn (User $user): bool => $user->canAccessAdmin());
        Gate::define('manageConcerts', fn (User $user): bool => $user->canAccessAdmin());
        Gate::define('manageCrew', fn (User $user): bool => $user->canAccessAdmin());
        Gate::define('manageScheduling', fn (User $user): bool => $user->canAccessAdmin());

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
}
