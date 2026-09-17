<?php

use App\Features\Admin\Controllers\AdminDashboardController;
use App\Features\Admin\Controllers\AdminDownloadLinkController;
use App\Features\Admin\Controllers\AdminConcertController;
use App\Features\Admin\Controllers\AdminStudioController;
use App\Features\Auth\Controllers\WebAuthController;
use App\Features\Competition\Controllers\AdminCompetitionObjectController;
use App\Features\Concerts\Controllers\PublicConcertController;
use App\Features\Concerts\Controllers\PublicSlugRedirectController;
use App\Features\Concerts\Controllers\PublicStudioController;
use App\Features\Downloads\Controllers\PublicDownloadController;
use App\Features\Media\Controllers\AdminConcertMediaController;
use App\Features\Media\Controllers\AdminConcertMediaDeletionController;
use App\Features\Media\Controllers\MediaAssetController;
use App\Features\Media\Controllers\MediaCollectionController;
use App\Features\Media\Controllers\MediaUploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicStudioController::class, 'index'])->name('studios.index');
Route::get('s/{slug}', [PublicSlugRedirectController::class, 'studio'])->name('studios.resolve-slug');
Route::get('c/{slug}', [PublicSlugRedirectController::class, 'concert'])->name('concerts.resolve-slug');
Route::get('studios/{studio}', [PublicStudioController::class, 'show'])->name('studios.show');
Route::get('concerts/{concert}', [PublicConcertController::class, 'show'])->name('concerts.show');
Route::post('concerts/{concert}/unlock', [PublicConcertController::class, 'unlock'])->middleware('throttle:10,1')->name('concerts.unlock');
Route::get('concerts/{concert}/media/{asset}/playback', [PublicConcertController::class, 'playback'])->name('concerts.media.playback');
Route::get('concerts/{concert}/media/{asset}', [PublicConcertController::class, 'media'])->name('concerts.media.stream');
Route::get('concerts/{concert}/media/{asset}/download', [PublicConcertController::class, 'download'])->name('concerts.media.download');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [WebAuthController::class, 'create'])->name('login');
    Route::post('login', [WebAuthController::class, 'store'])->name('login.store');
});

Route::post('logout', [WebAuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::resource('studios', AdminStudioController::class)->except(['show', 'destroy']);
        Route::resource('concerts', AdminConcertController::class)->except(['show', 'destroy']);
        Route::get('concerts/{concert}/media', AdminConcertMediaController::class)->name('concerts.media.index');
        Route::get('concert-media/assets/{asset}/delete', [AdminConcertMediaDeletionController::class, 'confirmAsset'])->name('concerts.media.assets.confirm-delete');
        Route::delete('concert-media/assets/{asset}', [AdminConcertMediaDeletionController::class, 'destroyAsset'])->name('concerts.media.assets.destroy');
        Route::get('concert-media/collections/{collection}/delete', [AdminConcertMediaDeletionController::class, 'confirmCollection'])->name('concerts.media.collections.confirm-delete');
        Route::delete('concert-media/collections/{collection}', [AdminConcertMediaDeletionController::class, 'destroyCollection'])->name('concerts.media.collections.destroy');
        Route::prefix('media-api')->name('media-api.')->middleware('throttle:media-uploads')->group(function (): void {
            Route::post('concerts/{concert}/collections', [MediaCollectionController::class, 'store'])->name('collections.store');
            Route::patch('collections/{collection}', [MediaCollectionController::class, 'update'])->name('collections.update');
            Route::post('collections/{collection}/assets', [MediaAssetController::class, 'store'])->name('assets.store');
            Route::patch('assets/{asset}', [MediaAssetController::class, 'update'])->name('assets.update');
            Route::post('assets/{asset}/multipart', [MediaUploadController::class, 'startMultipart'])->name('multipart.start');
            Route::post('assets/{asset}/multipart/{upload}/parts', [MediaUploadController::class, 'multipartParts'])->name('multipart.parts');
            Route::post('assets/{asset}/multipart/{upload}/complete', [MediaUploadController::class, 'completeMultipart'])->name('multipart.complete');
            Route::post('assets/{asset}/finalize', [MediaUploadController::class, 'finalize'])->name('assets.finalize');
        });
        Route::get('competitions/objects', [AdminCompetitionObjectController::class, 'index'])->name('competition.objects.index');
        Route::get('competitions/objects/chunk', [AdminCompetitionObjectController::class, 'chunk'])->name('competition.objects.chunk');
        Route::get('download-links', [AdminDownloadLinkController::class, 'index'])->name('download-links.index');
        Route::get('download-links/create', [AdminDownloadLinkController::class, 'create'])->name('download-links.create');
        Route::post('download-links', [AdminDownloadLinkController::class, 'store'])->name('download-links.store');
        Route::get('download-links/{downloadLink}', [AdminDownloadLinkController::class, 'show'])->name('download-links.show');
        Route::patch('download-links/{downloadLink}/revoke', [AdminDownloadLinkController::class, 'revoke'])->name('download-links.revoke');
    });

Route::get('download/{token}', [PublicDownloadController::class, 'show'])
    ->name('downloads.public.show');
