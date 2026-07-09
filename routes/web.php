<?php

use App\Features\Downloads\Controllers\PublicDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('download/{token}', [PublicDownloadController::class, 'show'])
    ->name('downloads.public.show');
