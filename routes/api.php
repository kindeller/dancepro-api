<?php

use App\Features\Auth\Controllers\AuthController;
use App\Features\Competition\Controllers\CompetitionObjectController;
use App\Features\Downloads\Controllers\DownloadLinkController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('competitions/objects', [CompetitionObjectController::class, 'index']);

    Route::get('download-links', [DownloadLinkController::class, 'index']);
    Route::post('download-links', [DownloadLinkController::class, 'store']);
    Route::get('download-links/{downloadLink}', [DownloadLinkController::class, 'show']);
    Route::patch('download-links/{downloadLink}/revoke', [DownloadLinkController::class, 'revoke']);
    Route::get('download-links/{downloadLink}/accesses', [DownloadLinkController::class, 'accesses']);
});
