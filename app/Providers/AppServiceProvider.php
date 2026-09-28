<?php

namespace App\Providers;

use App\Features\Admin\Services\AdminNavigationIndicators;
use App\Features\Auth\Services\LocalAccountSwitcher;
use App\Features\Crew\Services\CrewNavigationIndicators;
use App\Features\Customers\Models\CustomerAccountEmail;
use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Customers\Observers\CustomerInboxSourceObserver;
use App\Features\Customers\Observers\CustomerSupportRequestObserver;
use App\Features\Customers\Services\CustomerInbox;
use App\Features\Downloads\Models\DownloadLink;
use App\Features\Downloads\Policies\DownloadLinkPolicy;
use App\Features\Exceptions\Services\AdminExceptionOverview;
use App\Features\Media\Models\MediaAsset;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Policies\MediaAssetPolicy;
use App\Features\Media\Policies\MediaCollectionPolicy;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Observers\OrderObserver;
use App\Features\Payments\Contracts\PaymentGateway;
use App\Features\Payments\Models\CustomerMediaNotification;
use App\Features\Payments\Notifications\CustomerMediaEmail;
use App\Features\Payments\Services\DisabledPaymentGateway;
use App\Features\Payments\Services\FakePaymentGateway;
use App\Features\Programmes\Policies\ProgrammePolicy;
use App\Features\Tracking\Models\ConcertTrackerAlert;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Services\ConcertTracker;
use App\Models\User;
use App\Shared\Support\LocalDevelopmentSafety;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
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
        $this->app->bind(PaymentGateway::class, fn () => $this->app->environment(['local', 'testing']) && config('payments.driver') === 'fake'
            ? app(FakePaymentGateway::class)
            : app(DisabledPaymentGateway::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        CustomerSupportRequest::observe(CustomerSupportRequestObserver::class);
        foreach ([CustomerMediaNotification::class, CustomerAccountEmail::class, ConcertTrackerEmailDelivery::class, DatabaseNotification::class] as $source) {
            $source::observe(CustomerInboxSourceObserver::class);
        }
        View::composer('layouts.public', function (ViewContract $view): void {
            $view->with('customerInboxCounts', auth()->user()?->canAccessCustomer()
                ? app(CustomerInbox::class)->counts(auth()->user()) : ['notifications' => 0, 'conversations' => 0, 'total' => 0]);
        });
        if ($this->app->environment('local')) {
            $this->app->make(LocalDevelopmentSafety::class)->assertSafe();
        }

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

        RateLimiter::for('address-search', function (Request $request): array {
            $userAndIp = hash('sha256', ($request->user()?->getAuthIdentifier() ?? 'guest').'|'.$request->ip());

            return [
                Limit::perMinute(30)->by('address-search:'.$userAndIp),
                Limit::perMinute(120)->by('address-search-ip:'.$request->ip()),
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

        RateLimiter::for('guest-gallery-registration', function (Request $request): array {
            $emailAndIp = hash('sha256', mb_strtolower($request->string('email')->toString()).'|'.$request->ip());

            return [
                Limit::perMinute(4)->by('guest-gallery-registration:'.$emailAndIp),
                Limit::perHour(30)->by('guest-gallery-registration-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('guest-gallery-unlock', function (Request $request): array {
            $emailAndIp = hash('sha256', mb_strtolower($request->string('email')->toString()).'|'.$request->ip());

            return [
                Limit::perMinute(6)->by('guest-gallery-unlock:'.$emailAndIp),
                Limit::perHour(40)->by('guest-gallery-unlock-ip:'.$request->ip()),
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
        Gate::policy(MediaCollection::class, MediaCollectionPolicy::class);
        Gate::policy(MediaAsset::class, MediaAssetPolicy::class);
        Order::observe(OrderObserver::class);
        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            if ($event->notification instanceof CustomerMediaEmail) {
                CustomerMediaNotification::find($event->notification->deliveryId)
                    ?->update(['status' => 'sent', 'sent_at' => now()]);
            }
        });
        Event::listen(NotificationFailed::class, function (NotificationFailed $event): void {
            if ($event->notification instanceof CustomerMediaEmail) {
                CustomerMediaNotification::find($event->notification->deliveryId)
                    ?->update(['status' => 'failed', 'failed_at' => now()]);
            }
        });

        Gate::define('viewCompetitionObjects', fn (User $user): bool => $user->canAccessAdmin());
        Gate::define('manageStudios', fn (User $user): bool => $user->canAccessAdmin());
        Gate::define('manageConcerts', fn (User $user): bool => $user->canAccessAdmin());
        Gate::define('manageCrew', fn (User $user): bool => $user->canAccessAdmin());
        Gate::define('manageScheduling', fn (User $user): bool => $user->canAccessAdmin());
        Gate::define('viewProgramme', [ProgrammePolicy::class, 'view']);
        Gate::define('updateProgramme', [ProgrammePolicy::class, 'update']);
        Gate::define('viewStaffMedia', fn (User $user): bool => $user->canAccessAdmin());

        RateLimiter::for('media-uploads', fn (Request $request): Limit => Limit::perMinute(120)
            ->by((string) ($request->user()?->id ?? $request->ip())));

        View::composer('layouts.crew', function (ViewContract $view): void {
            $user = auth()->user();
            $view->with('crewNavigationIndicators', $user
                ? app(CrewNavigationIndicators::class)->for($user)
                : ['shifts' => 0, 'timesheets' => 0, 'chat' => 0]);
        });

        if ($this->app->environment(['local', 'testing'])) {
            View::composer(['layouts.public', 'layouts.admin', 'layouts.crew'], function (ViewContract $view): void {
                $view->with('localSwitchAccounts', app(LocalAccountSwitcher::class)->accounts());
            });
        }

        View::composer(['layouts.admin', 'admin.event-management._tabs', 'admin.media.support', 'admin.customers.follow-ups'], function (ViewContract $view): void {
            $view->with('adminNavigationIndicators', app(AdminNavigationIndicators::class)->for(auth()->user()));
        });

        View::composer('layouts.admin', function (ViewContract $view): void {
            $user = auth()->user();
            $view->with('concertTrackerAlertCount', $user && Gate::allows('manageScheduling')
                ? ConcertTrackerAlert::query()->where('user_id', $user->id)->whereNull('resolved_at')->whereNull('read_at')->count()
                : 0);
            $trackerYear = request()->routeIs('admin.concert-tracker.index') ? (int) request('year', now()->year) : now()->year;
            $view->with('concertTrackerNotificationYear', $trackerYear);
            $view->with('concertTrackerAttentionCount', $user && Gate::allows('manageScheduling')
                ? app(ConcertTracker::class)->notificationCount($trackerYear)
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
