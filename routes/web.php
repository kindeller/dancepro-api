<?php

use App\Features\Admin\Controllers\AdminDashboardController;
use App\Features\Admin\Controllers\AdminDownloadLinkController;
use App\Features\Auth\Controllers\WebAuthController;
use App\Features\Downloads\Controllers\PublicDownloadController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

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
        Route::get('download-links', [AdminDownloadLinkController::class, 'index'])->name('download-links.index');
        Route::get('download-links/create', [AdminDownloadLinkController::class, 'create'])->name('download-links.create');
        Route::post('download-links', [AdminDownloadLinkController::class, 'store'])->name('download-links.store');
        Route::get('download-links/{downloadLink}', [AdminDownloadLinkController::class, 'show'])->name('download-links.show');
        Route::patch('download-links/{downloadLink}/revoke', [AdminDownloadLinkController::class, 'revoke'])->name('download-links.revoke');
    });

Route::get('download/{token}', [PublicDownloadController::class, 'show'])
    ->name('downloads.public.show');
