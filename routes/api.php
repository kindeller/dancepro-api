<?php

use App\Features\Auth\Controllers\AuthController;
use App\Features\Auth\Controllers\CrewMobileAuthController;
use App\Features\Auth\Support\TokenAbility;
use App\Features\Competition\Controllers\CompetitionObjectController;
use App\Features\Concerts\Controllers\PublicConcertApiController;
use App\Features\Downloads\Controllers\DownloadLinkController;
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
