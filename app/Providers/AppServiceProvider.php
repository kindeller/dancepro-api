<?php

namespace App\Providers;

use App\Features\Crew\Services\CrewNavigationIndicators;
use App\Features\Downloads\Models\DownloadLink;
use App\Features\Downloads\Policies\DownloadLinkPolicy;
use App\Features\Exceptions\Services\AdminExceptionOverview;
use App\Features\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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

        RateLimiter::for('sensitive-auth', function (Request $request): array {
            $userAndIp = hash('sha256', ($request->user()?->getAuthIdentifier() ?? 'guest').'|'.$request->ip());

            return [
                Limit::perMinute(5)->by('sensitive-auth:'.$userAndIp),
                Limit::perHour(30)->by('sensitive-auth-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('concert-booking', function (Request $request): array {
            $emailAndIp = hash('sha256', mb_strtolower($request->string('contact_email')->toString()).'|'.$request->ip());
            $response = function (Request $request, array $headers) {
                Log::warning('Public concert booking rate limit exceeded.', [
                    'ip_hash' => hash('sha256', (string) $request->ip()),
                ]);

                return response('Too many booking attempts. Please wait and try again.', 429, $headers);
            };

            return [
                Limit::perMinute(5)->by('concert-booking:'.$emailAndIp)->response($response),
                Limit::perHour(20)->by('concert-booking-ip:'.$request->ip())->response($response),
            ];
        });

        RateLimiter::for('public-download', function (Request $request): array {
            $tokenAndIp = hash('sha256', $request->route('token').'|'.$request->ip());
            $response = function (Request $request, array $headers) {
                Log::warning('Public download rate limit exceeded.', [
                    'ip_hash' => hash('sha256', (string) $request->ip()),
                    'token_hash' => hash('sha256', (string) $request->route('token')),
                ]);

                return response('Too many download attempts. Please wait and try again.', 429, $headers);
            };

            return [
                Limit::perMinute(30)->by('public-download:'.$tokenAndIp)->response($response),
                Limit::perMinute(120)->by('public-download-ip:'.$request->ip())->response($response),
            ];
        });

        RateLimiter::for('public-catalogue', function (Request $request): Limit {
            return Limit::perMinute(max(1, (int) config('concerts.public_api.rate_limit_per_minute')))
                ->by('public-catalogue:'.$request->ip());
        });

        $this->registerConcertMediaRateLimiters();

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

    private function registerConcertMediaRateLimiters(): void
    {
        foreach ([
            'concert-playback' => 'playback_per_minute',
            'concert-media' => 'media_per_minute',
            'concert-media-download' => 'download_per_minute',
        ] as $limiterName => $configKey) {
            RateLimiter::for($limiterName, function (Request $request) use ($limiterName, $configKey): array {
                $limit = max(1, (int) config("concerts.rate_limits.{$configKey}"));
                $asset = $request->route('asset');
                $assetKey = $asset instanceof MediaAsset ? $asset->uuid : (string) $asset;
                $assetAndIp = hash('sha256', $assetKey.'|'.$request->ip());

                return [
                    Limit::perMinute($limit)->by("{$limiterName}:{$assetAndIp}"),
                    Limit::perMinute($limit * 4)->by("{$limiterName}-ip:{$request->ip()}"),
                ];
            });
        }
    }
}
