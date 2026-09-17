<?php

use App\Features\Auth\Controllers\AuthController;
use App\Features\Competition\Controllers\CompetitionObjectController;
use App\Features\Concerts\Controllers\PublicConcertApiController;
use App\Features\Downloads\Controllers\DownloadLinkController;
use App\Features\Media\Controllers\LegacyMediaController;
use App\Features\Media\Controllers\MediaAssetController;
use App\Features\Media\Controllers\MediaCollectionController;
use App\Features\Media\Controllers\MediaUploadController;
use App\Features\Media\Controllers\StaffMediaDiscoveryController;
use Illuminate\Support\Facades\Route;

Route::get('studios', [PublicConcertApiController::class, 'studios']);
Route::get('studios/{studio}', [PublicConcertApiController::class, 'studio']);
Route::get('concerts/{concert}', [PublicConcertApiController::class, 'concert']);

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('competitions/objects', [CompetitionObjectController::class, 'index'])
        ->middleware('token.ability:competition-objects:read');

    Route::middleware('token.ability:download-links:manage')->group(function (): void {
        Route::get('download-links', [DownloadLinkController::class, 'index']);
        Route::post('download-links', [DownloadLinkController::class, 'store']);
        Route::get('download-links/{downloadLink}', [DownloadLinkController::class, 'show']);
        Route::patch('download-links/{downloadLink}/revoke', [DownloadLinkController::class, 'revoke']);
        Route::get('download-links/{downloadLink}/accesses', [DownloadLinkController::class, 'accesses']);
    });

    Route::prefix('staff')->group(function (): void {
        Route::middleware('token.ability:concert-media:read')->group(function (): void {
            Route::get('studios', [StaffMediaDiscoveryController::class, 'studios']);
            Route::get('concerts', [StaffMediaDiscoveryController::class, 'concerts']);
            Route::get('concerts/{concert}', [StaffMediaDiscoveryController::class, 'concert']);
            Route::get('concerts/{concert}/legacy-media', [LegacyMediaController::class, 'index']);
            Route::get('concerts/{concert}/media-collections', [MediaCollectionController::class, 'index']);
            Route::get('media-collections/{collection}', [MediaCollectionController::class, 'show']);
            Route::get('media-collections/{collection}/media-assets', [MediaAssetController::class, 'index']);
            Route::get('media-assets/{asset}', [MediaAssetController::class, 'show']);
            Route::get('media-assets/{asset}/uploads/{upload}', [MediaUploadController::class, 'show']);
        });

        Route::middleware(['token.ability:concert-media:upload', 'throttle:media-uploads'])->group(function (): void {
            Route::post('concerts/{concert}/media-collections', [MediaCollectionController::class, 'store']);
            Route::post('media-collections/{collection}/media-assets', [MediaAssetController::class, 'store']);
            Route::post('media-assets/{asset}/upload-urls', [MediaUploadController::class, 'uploadUrls']);
            Route::post('media-assets/{asset}/upload-batches/{upload}/complete', [MediaUploadController::class, 'completeUploadBatch']);
            Route::post('media-assets/{asset}/multipart-uploads', [MediaUploadController::class, 'startMultipart']);
            Route::post('media-assets/{asset}/multipart-uploads/{upload}/parts', [MediaUploadController::class, 'multipartParts']);
            Route::post('media-assets/{asset}/multipart-uploads/{upload}/complete', [MediaUploadController::class, 'completeMultipart']);
            Route::post('media-assets/{asset}/multipart-uploads/{upload}/abort', [MediaUploadController::class, 'abortMultipart']);
            Route::post('media-assets/{asset}/finalize', [MediaUploadController::class, 'finalize']);
            Route::post('media-collections/{collection}/imports', [LegacyMediaController::class, 'store']);
        });

        Route::middleware('token.ability:concert-media:update')->group(function (): void {
            Route::patch('media-collections/{collection}', [MediaCollectionController::class, 'update']);
            Route::patch('media-assets/{asset}', [MediaAssetController::class, 'update']);
        });
    });
});
