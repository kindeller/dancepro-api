<?php

use App\Features\Auth\Controllers\AuthController;
use App\Features\Auth\Controllers\CrewMobileAuthController;
use App\Features\Auth\Controllers\CrewMobileSecurityController;
use App\Features\Auth\Support\TokenAbility;
use App\Features\Chat\Controllers\CrewMobileChatController;
use App\Features\Competition\Controllers\CompetitionObjectController;
use App\Features\Concerts\Controllers\PublicConcertApiController;
use App\Features\Crew\Controllers\CrewMobileContractController;
use App\Features\Crew\Controllers\CrewMobileDashboardController;
use App\Features\Crew\Controllers\CrewMobileDirectoryController;
use App\Features\Crew\Controllers\CrewMobileProfileController;
use App\Features\Downloads\Controllers\DownloadLinkController;
use App\Features\Notifications\Controllers\CrewMobileAppConfigController;
use App\Features\Notifications\Controllers\CrewMobilePushDeviceController;
use App\Features\Operations\Controllers\CrewMobileDocumentController;
use App\Features\Operations\Controllers\CrewMobileHandbookController;
use App\Features\Scheduling\Controllers\CrewMobileAssignmentActionController;
use App\Features\Scheduling\Controllers\CrewMobileAssignmentController;
use App\Features\Scheduling\Controllers\CrewMobileAssignmentFileController;
use App\Features\Scheduling\Controllers\CrewMobileAvailabilityController;
use App\Features\Scheduling\Controllers\CrewMobileCoverRequestController;
use App\Features\Scheduling\Controllers\CrewMobileNotificationController;
use App\Features\Scheduling\Controllers\CrewMobileTimeController;
use App\Features\Timesheets\Controllers\CrewMobileFinancialController;
use App\Features\Training\Controllers\CrewMobileTrainingController;
use App\Features\Venues\Controllers\CrewMobileAddressController;
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
    Route::post('forgot-password', [CrewMobileSecurityController::class, 'forgotPassword'])
        ->middleware('throttle:password-reset-link');

    Route::middleware(['auth:sanctum', 'abilities:'.TokenAbility::CrewMobile->value, 'api.crew.active'])->group(function (): void {
        Route::post('logout', [CrewMobileAuthController::class, 'logout']);
        Route::get('me', [CrewMobileAuthController::class, 'me']);
        Route::get('devices', [CrewMobileAuthController::class, 'devices']);
        Route::delete('devices/{device}', [CrewMobileAuthController::class, 'revokeDevice'])
            ->middleware(['api.idempotency', 'throttle:sensitive-auth']);
        Route::post('two-factor', [CrewMobileSecurityController::class, 'beginTwoFactor'])
            ->middleware(['api.idempotency', 'throttle:sensitive-auth']);
        Route::post('two-factor/confirm', [CrewMobileSecurityController::class, 'confirmTwoFactor'])
            ->middleware(['api.idempotency', 'throttle:sensitive-auth']);
        Route::post('two-factor/recovery-codes', [CrewMobileSecurityController::class, 'recoveryCodes'])
            ->middleware(['api.idempotency', 'throttle:sensitive-auth']);
        Route::delete('two-factor', [CrewMobileSecurityController::class, 'disableTwoFactor'])
            ->middleware(['api.idempotency', 'throttle:sensitive-auth']);
    });
});

Route::get('v1/app-config', CrewMobileAppConfigController::class)->middleware('throttle:public-catalogue');

Route::prefix('v1')->middleware([
    'auth:sanctum',
    'abilities:'.TokenAbility::CrewMobile->value,
    'api.crew.active',
])->group(function (): void {
    Route::get('profile', [CrewMobileProfileController::class, 'show']);
    Route::put('profile', [CrewMobileProfileController::class, 'update'])
        ->middleware(['api.idempotency', 'throttle:sensitive-auth']);
    Route::put('profile/password', [CrewMobileProfileController::class, 'changePassword'])
        ->middleware(['api.idempotency', 'throttle:sensitive-auth']);
    Route::get('address-search', CrewMobileAddressController::class)->middleware('throttle:address-search');
    Route::get('contracts', [CrewMobileContractController::class, 'index']);
    Route::post('push-devices', [CrewMobilePushDeviceController::class, 'store'])->middleware('api.idempotency');
    Route::delete('push-devices/{device}', [CrewMobilePushDeviceController::class, 'destroy'])->middleware('api.idempotency');
    Route::post('contracts/{contract}/sign', [CrewMobileContractController::class, 'sign'])
        ->middleware(['api.idempotency', 'throttle:sensitive-auth']);

    Route::middleware('api.crew.onboarded')->group(function (): void {
        Route::get('dashboard', CrewMobileDashboardController::class);
        Route::get('assignments', [CrewMobileAssignmentController::class, 'index']);
        Route::get('assignments/{assignment}', [CrewMobileAssignmentController::class, 'show']);
        Route::get('assignments/{assignment}/files/{kind}', CrewMobileAssignmentFileController::class)
            ->whereIn('kind', ['programme', 'venue-map', 'venue-reference'])
            ->name('api.v1.assignments.files.show');
        Route::get('cover-requests', [CrewMobileCoverRequestController::class, 'index']);
        Route::post('assignments/{assignment}/cover-requests', [CrewMobileCoverRequestController::class, 'store'])->middleware('api.idempotency');
        Route::post('cover-requests/{coverRequest}/accept', [CrewMobileCoverRequestController::class, 'accept'])->middleware('api.idempotency');
        Route::put('assignments/{assignment}/acknowledgement', [CrewMobileAssignmentActionController::class, 'acknowledge'])->middleware('api.idempotency');
        Route::put('assignments/{assignment}/checklist-items/{item}', [CrewMobileAssignmentActionController::class, 'checklist'])->middleware('api.idempotency');
        Route::get('availability', [CrewMobileAvailabilityController::class, 'index']);
        Route::put('availability/{shift}', [CrewMobileAvailabilityController::class, 'update'])->middleware('api.idempotency');
        Route::get('directory', CrewMobileDirectoryController::class);
        Route::get('chats', [CrewMobileChatController::class, 'index']);
        Route::post('chats/direct', [CrewMobileChatController::class, 'start'])->middleware('api.idempotency');
        Route::get('chats/{chatId}/messages', [CrewMobileChatController::class, 'messages']);
        Route::get('chats/{chatId}/messages/{message}/attachment', [CrewMobileChatController::class, 'attachment'])
            ->name('api.v1.chats.attachments.show');
        Route::post('chats/{chatId}/messages', [CrewMobileChatController::class, 'store'])->middleware('api.idempotency');
        Route::post('chats/{chatId}/messages/{message}/acknowledgement', [CrewMobileChatController::class, 'acknowledge'])->middleware('api.idempotency');
        Route::put('chats/{chatId}/read', [CrewMobileChatController::class, 'read']);
        Route::get('notifications', CrewMobileNotificationController::class);
        Route::put('notifications/{notification}/read', [CrewMobileNotificationController::class, 'read']);
        Route::get('timesheets', [CrewMobileFinancialController::class, 'timesheets']);
        Route::get('invoices', [CrewMobileFinancialController::class, 'invoices']);
        Route::post('invoices/preview', [CrewMobileFinancialController::class, 'previewInvoice'])->middleware('api.idempotency');
        Route::post('invoices', [CrewMobileFinancialController::class, 'createInvoice'])->middleware('api.idempotency');
        Route::post('timesheets/external-invoice', [CrewMobileFinancialController::class, 'markExternalInvoice'])->middleware('api.idempotency');
        Route::get('invoices/{invoice}', [CrewMobileFinancialController::class, 'invoice']);
        Route::post('invoices/{invoice}/download', [CrewMobileFinancialController::class, 'downloadInvoice'])->middleware('api.idempotency');
        Route::get('invoices/{invoice}/content', [CrewMobileFinancialController::class, 'invoiceContent'])
            ->middleware('signed')->name('api.v1.invoices.content');
        Route::post('assignments/{assignment}/clock-in', [CrewMobileTimeController::class, 'clockIn'])->middleware('api.idempotency');
        Route::post('assignments/{assignment}/clock-out', [CrewMobileTimeController::class, 'finish'])->middleware('api.idempotency');
        Route::put('assignments/{assignment}/time', [CrewMobileTimeController::class, 'update'])->middleware('api.idempotency');
        Route::post('assignments/{assignment}/finish-team', [CrewMobileTimeController::class, 'finishTeam'])->middleware('api.idempotency');
        Route::get('documents', [CrewMobileDocumentController::class, 'index']);
        Route::get('handbook', CrewMobileHandbookController::class);
        Route::post('documents/{document}/download', [CrewMobileDocumentController::class, 'download']);
        Route::get('documents/{document}/content', [CrewMobileDocumentController::class, 'content'])
            ->middleware('signed')->name('api.v1.documents.content');
        Route::get('training', CrewMobileTrainingController::class);
        Route::get('training/{course}', [CrewMobileTrainingController::class, 'show']);
        Route::post('training/{course}/start', [CrewMobileTrainingController::class, 'start'])
            ->middleware('api.idempotency');
        Route::post('training/{course}/modules/{module}/complete', [CrewMobileTrainingController::class, 'complete'])
            ->middleware('api.idempotency');
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
