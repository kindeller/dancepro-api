<?php

use App\Features\Auth\Controllers\AuthController;
use App\Features\Auth\Controllers\CrewMobileAuthController;
use App\Features\Auth\Controllers\CrewMobileSecurityController;
use App\Features\Auth\Support\TokenAbility;
use App\Features\Chat\Controllers\CrewMobileChatController;
use App\Features\Chat\Controllers\MessageReactionController;
use App\Features\Competition\Controllers\CompetitionObjectController;
use App\Features\Concerts\Controllers\PublicConcertApiController;
use App\Features\Concerts\Controllers\PublicConcertController;
use App\Features\Crew\Controllers\CrewMobileContractController;
use App\Features\Crew\Controllers\CrewMobileDashboardController;
use App\Features\Crew\Controllers\CrewMobileDirectoryController;
use App\Features\Crew\Controllers\CrewMobileProfileController;
use App\Features\Customers\Controllers\CustomerMobileAuthController;
use App\Features\Customers\Controllers\CustomerMobileMediaController;
use App\Features\Downloads\Controllers\DownloadLinkController;
use App\Features\Media\Controllers\LegacyMediaController;
use App\Features\Media\Controllers\LocalMediaUploadController;
use App\Features\Media\Controllers\MediaAssetController;
use App\Features\Media\Controllers\MediaCollectionController;
use App\Features\Media\Controllers\MediaUploadController;
use App\Features\Media\Controllers\StaffMediaDiscoveryController;
use App\Features\Notifications\Controllers\CrewMobileAppConfigController;
use App\Features\Notifications\Controllers\CrewMobilePushDeviceController;
use App\Features\Operations\Controllers\CrewMobileDocumentController;
use App\Features\Operations\Controllers\CrewMobileHandbookController;
use App\Features\Programmes\Controllers\CompetitionFoldersController;
use App\Features\Programmes\Controllers\CompetitionVideoUploadController;
use App\Features\Programmes\Controllers\PhotoCompletionController;
use App\Features\Programmes\Controllers\PhotoContentController;
use App\Features\Programmes\Controllers\PhotoContributionsController;
use App\Features\Programmes\Controllers\PhotoFilesController;
use App\Features\Programmes\Controllers\PhotoUploadProgressController;
use App\Features\Programmes\Controllers\ProgrammeController;
use App\Features\Programmes\Controllers\ProgrammePhotographersController;
use App\Features\Programmes\Controllers\ProgrammeSyncController;
use App\Features\Programmes\Controllers\ProgrammeUploadRolesController;
use App\Features\Programmes\Controllers\SharedConcertProgrammeController;
use App\Features\Scheduling\Controllers\CrewMobileAssignmentActionController;
use App\Features\Scheduling\Controllers\CrewMobileAssignmentController;
use App\Features\Scheduling\Controllers\CrewMobileAssignmentFileController;
use App\Features\Scheduling\Controllers\CrewMobileAvailabilityController;
use App\Features\Scheduling\Controllers\CrewMobileCoverRequestController;
use App\Features\Scheduling\Controllers\CrewMobileNotificationController;
use App\Features\Scheduling\Controllers\CrewMobileTimeController;
use App\Features\Studios\Controllers\StudioEventFileController;
use App\Features\Studios\Controllers\StudioEventSeatsController;
use App\Features\Timesheets\Controllers\CrewMobileFinancialController;
use App\Features\Timesheets\Controllers\InvoiceStylePreferenceController;
use App\Features\Training\Controllers\CrewMobileTrainingController;
use App\Features\Venues\Controllers\CrewMobileAddressController;
use Illuminate\Support\Facades\Route;

// Signed uploads are deliberately inert outside APP_ENV=local (see request authorization).
Route::put('local-media/objects', [LocalMediaUploadController::class, 'object'])
    ->middleware(['signed', 'throttle:media-uploads'])->name('local-media.object.put');
Route::put('local-media/parts/{uploadId}/{partNumber}', [LocalMediaUploadController::class, 'part'])
    ->middleware(['signed', 'throttle:media-uploads'])->name('local-media.part.put');

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

Route::prefix('v1/customer')->name('api.v1.customer.')->group(function (): void {
    Route::post('register', [CustomerMobileAuthController::class, 'register'])->middleware('throttle:5,1')->name('register');
    Route::post('auth/login', [CustomerMobileAuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');
    Route::post('auth/forgot-password', [CustomerMobileAuthController::class, 'forgotPassword'])->middleware('throttle:password-reset-link')->name('auth.forgot-password');
    Route::middleware(['auth:sanctum', 'token.ability:'.TokenAbility::CustomerMobile->value])->group(function (): void {
        Route::get('auth/me', [CustomerMobileAuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [CustomerMobileAuthController::class, 'logout'])->name('auth.logout');
        Route::post('auth/resend-verification', [CustomerMobileAuthController::class, 'resend'])->middleware('throttle:3,1')->name('auth.resend');
        Route::post('policy', [CustomerMobileAuthController::class, 'policy'])->name('policy');

        Route::middleware('api.customer.onboarded')->group(function (): void {
            Route::get('media', [CustomerMobileMediaController::class, 'home'])->name('media');
            Route::post('profile', [CustomerMobileMediaController::class, 'profile'])->name('profile');
            Route::post('competitions', [CustomerMobileMediaController::class, 'competition'])->middleware('throttle:10,1')->name('competitions.store');
            Route::get('competitions/{schedulingEvent}', [CustomerMobileMediaController::class, 'gallery'])->name('competitions.show');
            Route::get('competitions/{schedulingEvent}/photos/{photo}', [CustomerMobileMediaController::class, 'image'])->whereUuid('photo')->name('competitions.image');
            Route::get('recommendations/{schedulingEvent}/{item}/{photo}/thumbnail', [CustomerMobileMediaController::class, 'recommendation'])->whereUuid(['item', 'photo'])->name('recommendations.thumbnail');
            Route::post('concert-codes', [CustomerMobileMediaController::class, 'concertCode'])->middleware('throttle:10,1')->name('concert-codes.store');
            Route::get('concerts/{concert}', [CustomerMobileMediaController::class, 'concert'])->name('concerts.show');
            Route::post('studios/{studio}/events/{schedulingEvent}/seats', [StudioEventSeatsController::class, 'store'])
                ->middleware('throttle:10,1')->name('studio-events.seats.store');
            Route::post('studios/{studio}/events/{schedulingEvent}/files', [StudioEventFileController::class, 'store'])
                ->middleware('throttle:10,1')->name('studio-events.files.store');
            Route::get('concerts/{concert}/media/{asset}/playback', [PublicConcertController::class, 'playback'])->name('concerts.playback');
            Route::get('concerts/{concert}/media/{asset}/download', [PublicConcertController::class, 'download'])->name('concerts.download');
        });
    });
});

Route::get('v1/app-config', CrewMobileAppConfigController::class)->middleware('throttle:public-catalogue');
Route::get('v1/assignment-files/{assignment}/{kind}/content', [CrewMobileAssignmentFileController::class, 'content'])
    ->whereIn('kind', ['programme', 'venue-map', 'venue-reference'])
    ->middleware('signed')
    ->name('api.v1.assignment-files.content');

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
    Route::post('profile/payment-details/reveal', [CrewMobileProfileController::class, 'revealPaymentDetails'])
        ->middleware('throttle:sensitive-auth')
        ->name('api.v1.profile.payment-details.reveal');
    Route::get('address-search', CrewMobileAddressController::class)->middleware('throttle:address-search');
    Route::get('address-search/{placeId}', [CrewMobileAddressController::class, 'resolve'])
        ->middleware('throttle:address-search');
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
        Route::post('assignments/{assignment}/files/{kind}/download', [CrewMobileAssignmentFileController::class, 'download'])
            ->whereIn('kind', ['programme', 'venue-map', 'venue-reference'])
            ->middleware('api.idempotency')
            ->name('api.v1.assignments.files.download');
        Route::get('cover-requests', [CrewMobileCoverRequestController::class, 'index']);
        Route::get('assignments/{assignment}/cover-candidates', [CrewMobileCoverRequestController::class, 'candidates']);
        Route::post('assignments/{assignment}/cover-requests', [CrewMobileCoverRequestController::class, 'store'])->middleware('api.idempotency');
        Route::post('cover-requests/{coverRequest}/accept', [CrewMobileCoverRequestController::class, 'accept'])->middleware('api.idempotency');
        Route::put('assignments/{assignment}/acknowledgement', [CrewMobileAssignmentActionController::class, 'acknowledge'])->middleware('api.idempotency');
        Route::put('assignments/{assignment}/checklist-items/{item}', [CrewMobileAssignmentActionController::class, 'checklist'])->middleware('api.idempotency');
        Route::get('availability', [CrewMobileAvailabilityController::class, 'index']);
        Route::put('availability/{shift}', [CrewMobileAvailabilityController::class, 'update'])->middleware('api.idempotency');
        Route::delete('availability/{shift}', [CrewMobileAvailabilityController::class, 'destroy'])->middleware('api.idempotency');
        Route::get('directory', CrewMobileDirectoryController::class);
        Route::get('chats', [CrewMobileChatController::class, 'index']);
        Route::post('chats/direct', [CrewMobileChatController::class, 'start'])->middleware('api.idempotency');
        Route::get('chats/{chatId}/messages', [CrewMobileChatController::class, 'messages']);
        Route::get('chats/{chatId}/messages/{message}/attachment', [CrewMobileChatController::class, 'attachment'])
            ->name('api.v1.chats.attachments.show');
        Route::post('chats/{chatId}/messages', [CrewMobileChatController::class, 'store'])->middleware('api.idempotency');
        Route::post('chats/{chatId}/messages/{message}/acknowledgement', [CrewMobileChatController::class, 'acknowledge'])->middleware('api.idempotency');
        Route::put('chats/{chatId}/messages/{message}/reaction', [MessageReactionController::class, 'update']);
        Route::put('chats/{chatId}/read', [CrewMobileChatController::class, 'read']);
        Route::get('notifications', CrewMobileNotificationController::class);
        Route::put('notifications/{notification}/read', [CrewMobileNotificationController::class, 'read']);
        Route::get('timesheets', [CrewMobileFinancialController::class, 'timesheets']);
        Route::get('invoices/preferences', [InvoiceStylePreferenceController::class, 'show']);
        Route::put('invoices/preferences', [InvoiceStylePreferenceController::class, 'update']);
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
        Route::post('documents/{document}/download', [CrewMobileDocumentController::class, 'download'])
            ->name('api.v1.documents.download');
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
        ->middleware('token.ability:'.TokenAbility::CompetitionObjectsRead->value);

    Route::middleware('token.ability:'.TokenAbility::DownloadLinksManage->value)->group(function (): void {
        Route::get('download-links', [DownloadLinkController::class, 'index']);
        Route::post('download-links', [DownloadLinkController::class, 'store']);
        Route::get('download-links/{downloadLink}', [DownloadLinkController::class, 'show']);
        Route::patch('download-links/{downloadLink}/revoke', [DownloadLinkController::class, 'revoke']);
        Route::get('download-links/{downloadLink}/accesses', [DownloadLinkController::class, 'accesses']);
    });

    Route::prefix('staff')->group(function (): void {
        Route::get('events/{schedulingEvent}/programme/photo-progress', PhotoUploadProgressController::class)
            ->middleware(['token.ability:'.TokenAbility::ProgrammeRead->value, 'throttle:60,1']);
        Route::get('events/{schedulingEvent}/programme/upload-roles', ProgrammeUploadRolesController::class)
            ->middleware(['token.ability:'.TokenAbility::ProgrammeRead->value, 'throttle:60,1']);
        Route::get('events/{schedulingEvent}/programme/photographers', ProgrammePhotographersController::class)
            ->middleware(['token.ability:'.TokenAbility::ProgrammeRead->value, 'throttle:60,1']);
        Route::put('events/{schedulingEvent}/programme/photo-files/{photoFile}/content', PhotoContentController::class)
            ->middleware(['token.ability:'.TokenAbility::ConcertMediaUpload->value, 'throttle:120,1']);
        Route::post('events/{schedulingEvent}/programme/photo-files', PhotoFilesController::class)
            ->middleware(['token.ability:'.TokenAbility::ConcertMediaUpload->value, 'throttle:60,1']);
        Route::post('events/{schedulingEvent}/programme/photo-completion', PhotoCompletionController::class)
            ->middleware(['token.ability:concert-media:upload', 'throttle:30,1']);
        Route::post('events/{schedulingEvent}/programme/photo-contributions', PhotoContributionsController::class)
            ->middleware(['token.ability:'.TokenAbility::ConcertMediaUpload->value, 'throttle:30,1']);
        Route::get('events/{schedulingEvent}/programme/competition-folders', [CompetitionFoldersController::class, 'show'])->middleware(['token.ability:programmes:read', 'throttle:60,1']);
        Route::post('events/{schedulingEvent}/programme/competition-folders', [CompetitionFoldersController::class, 'store'])->middleware(['token.ability:concert-media:upload', 'throttle:30,1']);
        Route::post('events/{schedulingEvent}/competition-videos/batches', [CompetitionVideoUploadController::class, 'begin'])->middleware(['token.ability:'.TokenAbility::ConcertMediaUpload->value, 'throttle:30,1']);
        Route::post('events/{schedulingEvent}/competition-videos/batches/{batch}/files', [CompetitionVideoUploadController::class, 'reserve'])->middleware(['token.ability:'.TokenAbility::ConcertMediaUpload->value, 'throttle:60,1']);
        Route::post('events/{schedulingEvent}/competition-videos/batches/{batch}/files/{file}/verify', [CompetitionVideoUploadController::class, 'verify'])->middleware(['token.ability:'.TokenAbility::ConcertMediaUpload->value, 'throttle:60,1']);
        Route::post('events/{schedulingEvent}/competition-videos/batches/{batch}/complete', [CompetitionVideoUploadController::class, 'complete'])->middleware(['token.ability:'.TokenAbility::ConcertMediaUpload->value, 'throttle:30,1']);
        Route::get('events/{schedulingEvent}/programme/shared', SharedConcertProgrammeController::class)
            ->middleware(['token.ability:'.TokenAbility::ProgrammeRead->value, 'throttle:60,1']);
        Route::post('events/{schedulingEvent}/programme/sync', ProgrammeSyncController::class)
            ->middleware(['token.ability:'.TokenAbility::ConcertMediaUpload->value, 'throttle:30,1']);
        Route::get('programme-events', [ProgrammeController::class, 'events'])
            ->middleware(['token.ability:'.TokenAbility::ProgrammeRead->value, 'throttle:60,1']);
        Route::get('events/{schedulingEvent}/programme/source/{revision?}', [ProgrammeController::class, 'confirmedSource'])->name('staff.programmes.source')
            ->middleware(['token.ability:'.TokenAbility::ProgrammeRead->value, 'throttle:60,1']);
        Route::get('events/{schedulingEvent}/programme', [ProgrammeController::class, 'snapshot'])
            ->middleware(['token.ability:'.TokenAbility::ProgrammeRead->value, 'throttle:60,1']);
        Route::middleware('token.ability:'.TokenAbility::ConcertMediaRead->value)->group(function (): void {
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

        Route::middleware(['token.ability:'.TokenAbility::ConcertMediaUpload->value, 'throttle:media-uploads'])->group(function (): void {
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

        Route::middleware('token.ability:'.TokenAbility::ConcertMediaUpdate->value)->group(function (): void {
            Route::patch('media-collections/{collection}', [MediaCollectionController::class, 'update']);
            Route::patch('media-assets/{asset}', [MediaAssetController::class, 'update']);
        });
    });
});
