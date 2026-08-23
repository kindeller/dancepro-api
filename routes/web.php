<?php

use App\Features\Admin\Controllers\AdminDashboardController;
use App\Features\Admin\Controllers\AdminDownloadLinkController;
use App\Features\Admin\Controllers\AdminConcertController;
use App\Features\Admin\Controllers\AdminStudioController;
use App\Features\Auth\Controllers\WebAuthController;
use App\Features\Competition\Controllers\AdminCompetitionObjectController;
use App\Features\Concerts\Controllers\PublicConcertController;
use App\Features\Concerts\Controllers\PublicStudioController;
use App\Features\Downloads\Controllers\PublicDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicStudioController::class, 'index'])->name('studios.index');
Route::get('studios/{studio}', [PublicStudioController::class, 'show'])->name('studios.show');
Route::get('concerts/{concert}', [PublicConcertController::class, 'show'])->name('concerts.show');
Route::post('concerts/{concert}/unlock', [PublicConcertController::class, 'unlock'])->middleware('throttle:10,1')->name('concerts.unlock');
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
