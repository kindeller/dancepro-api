<?php

use App\Features\Auth\Controllers\AuthController;
use App\Features\Auth\Controllers\CrewMobileAuthController;
use App\Features\Auth\Support\TokenAbility;
use App\Features\Competition\Controllers\CompetitionObjectController;
use App\Features\Concerts\Controllers\PublicConcertApiController;
use App\Features\Crew\Controllers\CrewMobileDashboardController;
use App\Features\Crew\Controllers\CrewMobileDirectoryController;
use App\Features\Crew\Controllers\CrewMobileProfileController;
use App\Features\Downloads\Controllers\DownloadLinkController;
use App\Features\Scheduling\Controllers\CrewMobileAssignmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:public-catalogue')->group(function (): void {
    Route::get('studios', [PublicConcertApiController::class, 'studios']);
    Route::get('studios/{studio}', [PublicConcertApiController::class, 'studio']);
    Route::get('concerts/{concert}', [PublicConcertApiController::class, 'concert']);
});

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me'])->middleware('abilities:'.TokenAbility::AccountRead->value);
    });
});

Route::prefix('v1/auth')->group(function (): void {
    Route::post('login', [CrewMobileAuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'abilities:'.TokenAbility::CrewMobile->value, 'api.crew.active'])->group(function (): void {
        Route::post('logout', [CrewMobileAuthController::class, 'logout']);
        Route::get('me', [CrewMobileAuthController::class, 'me']);
    });
});

Route::prefix('v1')->middleware([
    'auth:sanctum',
    'abilities:'.TokenAbility::CrewMobile->value,
    'api.crew.active',
])->group(function (): void {
    Route::get('profile', [CrewMobileProfileController::class, 'show']);

    Route::middleware('api.crew.onboarded')->group(function (): void {
        Route::get('dashboard', CrewMobileDashboardController::class);
        Route::get('assignments', [CrewMobileAssignmentController::class, 'index']);
        Route::get('assignments/{assignment}', [CrewMobileAssignmentController::class, 'show']);
        Route::get('directory', CrewMobileDirectoryController::class);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('competitions/objects', [CompetitionObjectController::class, 'index'])
        ->middleware('abilities:'.TokenAbility::CompetitionObjectsRead->value);

    Route::middleware('abilities:'.TokenAbility::DownloadLinksManage->value)->group(function (): void {
        Route::get('download-links', [DownloadLinkController::class, 'index']);
        Route::post('download-links', [DownloadLinkController::class, 'store']);
        Route::get('download-links/{downloadLink}', [DownloadLinkController::class, 'show']);
        Route::patch('download-links/{downloadLink}/revoke', [DownloadLinkController::class, 'revoke']);
        Route::get('download-links/{downloadLink}/accesses', [DownloadLinkController::class, 'accesses']);
    });
});
