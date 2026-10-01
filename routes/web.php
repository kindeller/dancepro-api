<?php

use App\Features\Admin\Controllers\AdminBrandingMediaController;
use App\Features\Admin\Controllers\AdminConcertCodeGroupController;
use App\Features\Admin\Controllers\AdminConcertController;
use App\Features\Admin\Controllers\AdminDashboardController;
use App\Features\Admin\Controllers\AdminDownloadLinkController;
use App\Features\Admin\Controllers\AdminStudioController;
use App\Features\Admin\Controllers\AdminStudioRequestController;
use App\Features\Auth\Controllers\LocalAccountSwitchController;
use App\Features\Auth\Controllers\PasswordResetController;
use App\Features\Auth\Controllers\TwoFactorController;
use App\Features\Auth\Controllers\WebAuthController;
use App\Features\Bookings\Controllers\AdminBookingConfirmationController;
use App\Features\Bookings\Controllers\AdminBookingEventMaterialsController;
use App\Features\Bookings\Controllers\AdminBookingStatusEmailController;
use App\Features\Bookings\Controllers\AdminConcertBookingController;
use App\Features\Bookings\Controllers\BookingFamilyNumbersController;
use App\Features\Bookings\Controllers\CustomerStudioBookingController;
use App\Features\Bookings\Controllers\PublicConcertBookingController;
use App\Features\Chat\Controllers\CrewChatController;
use App\Features\Chat\Controllers\MessageReactionController;
use App\Features\Competition\Controllers\AdminCompetitionObjectController;
use App\Features\CompetitionContacts\Controllers\AdminCompetitionContactController;
use App\Features\Concerts\Controllers\ConcertFamilyController;
use App\Features\Concerts\Controllers\DirectorReviewController;
use App\Features\Concerts\Controllers\PublicConcertController;
use App\Features\Concerts\Controllers\PublicSlugRedirectController;
use App\Features\Concerts\Controllers\PublicStudioController;
use App\Features\Concerts\Controllers\SharedConcertController;
use App\Features\Crew\Controllers\AdminCrewContractController;
use App\Features\Crew\Controllers\AdminCrewContractSignatureController;
use App\Features\Crew\Controllers\AdminCrewManagementController;
use App\Features\Crew\Controllers\AdminCrewProfileController;
use App\Features\Crew\Controllers\AdminCrewRoleController;
use App\Features\Crew\Controllers\CrewContractController;
use App\Features\Crew\Controllers\CrewDirectoryController;
use App\Features\Crew\Controllers\CrewProfileController;
use App\Features\Customers\Controllers\AdminCustomerAccountController;
use App\Features\Customers\Controllers\AdminCustomerConversationController;
use App\Features\Customers\Controllers\AdminCustomerSupportController;
use App\Features\Customers\Controllers\CustomerAccountController;
use App\Features\Customers\Controllers\CustomerAccountEmailController;
use App\Features\Customers\Controllers\CustomerCompetitionController;
use App\Features\Customers\Controllers\CustomerCompetitionSelectionController;
use App\Features\Customers\Controllers\CustomerConcertGalleryController;
use App\Features\Customers\Controllers\CustomerConcertPhotoSelectionController;
use App\Features\Customers\Controllers\CustomerMediaController;
use App\Features\Customers\Controllers\CustomerMediaHelpController;
use App\Features\Customers\Controllers\CustomerMediaLibraryController;
use App\Features\Customers\Controllers\CustomerMessagesController;
use App\Features\Customers\Controllers\CustomerOnboardingController;
use App\Features\Customers\Controllers\CustomerOrderController;
use App\Features\Customers\Controllers\CustomerProfileController;
use App\Features\Customers\Controllers\CustomerStudioCommunicationController;
use App\Features\Customers\Controllers\GuestCompetitionGalleryController;
use App\Features\Customers\Controllers\GuestConcertGalleryController;
use App\Features\Customers\Controllers\GuestGalleryAccessController;
use App\Features\Customers\Controllers\GuestGalleryController;
use App\Features\Customers\Controllers\GuestGalleryRegistrationController;
use App\Features\Customers\Controllers\GuestGallerySelectionController;
use App\Features\Customers\Controllers\GuestStudioSubscriptionController;
use App\Features\Downloads\Controllers\PublicDownloadController;
use App\Features\Exceptions\Controllers\AdminExceptionController;
use App\Features\Families\Controllers\CustomerFamilyController;
use App\Features\Media\Controllers\AdminConcertMediaController;
use App\Features\Media\Controllers\AdminConcertMediaDeletionController;
use App\Features\Media\Controllers\AdminConcertPhotoGalleryController;
use App\Features\Media\Controllers\AdminConcertPhotoReleaseController;
use App\Features\Media\Controllers\AdminMediaCommerceController;
use App\Features\Media\Controllers\AdminMediaWatermarkController;
use App\Features\Media\Controllers\AdminTopPicsController;
use App\Features\Media\Controllers\MediaAssetController;
use App\Features\Media\Controllers\MediaCollectionController;
use App\Features\Media\Controllers\MediaUploadController;
use App\Features\Media\Controllers\PublicBrandingMediaController;
use App\Features\Messaging\Controllers\AdminMessageCentreController;
use App\Features\Operations\Controllers\AdminEventCommunicationController;
use App\Features\Operations\Controllers\AdminHubDashboardController;
use App\Features\Operations\Controllers\AdminOperationsController;
use App\Features\Operations\Controllers\CrewEventCommunicationController;
use App\Features\Operations\Controllers\CrewOperationsController;
use App\Features\Operations\Controllers\InternalDocumentController;
use App\Features\Orders\Controllers\AdminOrderController;
use App\Features\Payments\Controllers\CustomerCheckoutController;
use App\Features\Payments\Controllers\CustomerPurchasedMediaDownloadController;
use App\Features\Programmes\Controllers\AdminCompetitionVideoController;
use App\Features\Programmes\Controllers\AdminConcertTitleCardController;
use App\Features\Programmes\Controllers\AdminPhotoUploadsController;
use App\Features\Programmes\Controllers\CompetitionCustomerPreviewController;
use App\Features\Programmes\Controllers\CompetitionGalleryController;
use App\Features\Programmes\Controllers\ConcertTitlePackageController;
use App\Features\Programmes\Controllers\ProgrammeChatUpdatesController;
use App\Features\Programmes\Controllers\ProgrammeController;
use App\Features\Publicity\Controllers\AdminGallerySharingController;
use App\Features\Publicity\Controllers\AdminSlideshowController;
use App\Features\Publicity\Controllers\PublicityJoinController;
use App\Features\Publicity\Controllers\ScreenController;
use App\Features\Reporting\Controllers\AdminMediaSalesController;
use App\Features\Scheduling\Controllers\AdminEventTypeController;
use App\Features\Scheduling\Controllers\AdminEventWorkflowController;
use App\Features\Scheduling\Controllers\AdminPaymentController;
use App\Features\Scheduling\Controllers\AdminSchedulingEventController;
use App\Features\Scheduling\Controllers\AdminTimeController;
use App\Features\Scheduling\Controllers\CrewAvailabilityController;
use App\Features\Scheduling\Controllers\CrewCoverRequestController;
use App\Features\Scheduling\Controllers\CrewTimeController;
use App\Features\StudioBilling\Controllers\AdminStudioInvoiceController;
use App\Features\StudioBilling\Controllers\CustomerStudioInvoiceController;
use App\Features\Studios\Controllers\StudioBookingChangeController;
use App\Features\Studios\Controllers\StudioEventFileController;
use App\Features\Studios\Controllers\StudioEventRequirementsController;
use App\Features\Studios\Controllers\StudioEventSeatsController;
use App\Features\Timesheets\Controllers\AdminTimesheetController;
use App\Features\Timesheets\Controllers\CrewTimesheetController;
use App\Features\Timesheets\Controllers\InvoiceStylePreferenceController;
use App\Features\Tracking\Controllers\AdminBookingNumbersTrackerController;
use App\Features\Tracking\Controllers\AdminBookingTrackerBarController;
use App\Features\Tracking\Controllers\AdminConcertCommunicationController;
use App\Features\Tracking\Controllers\AdminConcertQualityChecklistController;
use App\Features\Tracking\Controllers\AdminConcertTrackerAlertController;
use App\Features\Tracking\Controllers\AdminConcertTrackerAutomationController;
use App\Features\Tracking\Controllers\AdminConcertTrackerController;
use App\Features\Tracking\Controllers\AdminConcertTrackerTaskController;
use App\Features\Tracking\Controllers\AdminConcertVideoInclusionsController;
use App\Features\Tracking\Controllers\AdminSeatsFamiliesEmailTrackerController;
use App\Features\Tracking\Controllers\AdminSourceTrackerBarController;
use App\Features\Tracking\Controllers\ConcertEditingDownloadController;
use App\Features\Tracking\Controllers\ConcertEmailOpenController;
use App\Features\Tracking\Controllers\CrewConcertEditingController;
use App\Features\Tracking\Controllers\DirectorCorrectionsController;
use App\Features\Tracking\Controllers\PhotoReleaseEmailController;
use App\Features\Tracking\Support\ConcertTrackerTaskCatalog;
use App\Features\Training\Controllers\AdminTrainingController;
use App\Features\Training\Controllers\CrewTrainingController;
use App\Features\Venues\Controllers\AdminVenueController;
use App\Features\Website\Controllers\AdminWebsiteController;
use App\Features\Website\Controllers\PublicWebsiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicWebsiteController::class, 'home'])->name('website.home');
Route::get('about', [PublicWebsiteController::class, 'about'])->name('website.about');
Route::get('services', [PublicWebsiteController::class, 'services'])->name('website.services');
Route::get('{websiteSlug}', [PublicWebsiteController::class, 'detail'])
    ->whereIn('websiteSlug', ['book', 'competition', 'portfolio', 'pose', 'shootme', 'promo', 'studio', 'shop', 'terms', 'privacy'])
    ->name('website.detail');
Route::get('help', [PublicWebsiteController::class, 'help'])->name('website.help');
Route::redirect('faq', '/help')->name('website.faq');
Route::get('studios', [PublicStudioController::class, 'index'])->name('studios.index');
Route::get('concert-email/{delivery}/open.gif', ConcertEmailOpenController::class)->name('concert-tracker.email-open');
Route::get('s/{slug}', [PublicSlugRedirectController::class, 'studio'])->name('studios.resolve-slug');
Route::get('c/{slug}', [PublicSlugRedirectController::class, 'concert'])->name('concerts.resolve-slug');
Route::get('studios/{studio}', [PublicStudioController::class, 'show'])->name('studios.show');
Route::get('studios/{studio}/cover', [PublicBrandingMediaController::class, 'studioCover'])->name('studios.cover');
Route::prefix('shared-concerts/{share}')->name('concert-family.')->group(function (): void {
    Route::get('/', [SharedConcertController::class, 'show'])->name('show');
    Route::post('code', [SharedConcertController::class, 'sendCode'])->middleware('throttle:family-code')->name('code');
    Route::post('verify', [SharedConcertController::class, 'verify'])->middleware('throttle:family-verify')->name('verify');
    Route::get('videos/{asset}/playback', [SharedConcertController::class, 'playback'])->middleware('throttle:family-playback')->name('playback');
    Route::get('videos/{asset}', [SharedConcertController::class, 'media'])->middleware('throttle:family-media')->name('media');
    Route::post('videos/{asset}/activity', [SharedConcertController::class, 'activity'])->middleware('throttle:family-activity')->name('activity');
    Route::post('videos/{asset}/download', [SharedConcertController::class, 'requestDownload'])->middleware('throttle:family-download-request')->name('download.request');
    Route::get('downloads/{download}', [SharedConcertController::class, 'download'])->middleware('throttle:family-download')->name('download');
});
Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])->prefix('my/concerts/{concert}/family-sharing')->name('customer.concert-family.')->group(function (): void {
    Route::get('/', [ConcertFamilyController::class, 'index'])->name('index');
    Route::post('/', [ConcertFamilyController::class, 'store'])->middleware('throttle:family-invite')->name('store');
    Route::get('settings', [ConcertFamilyController::class, 'settings'])->name('settings');
    Route::patch('settings', [ConcertFamilyController::class, 'policy'])->name('policy');
    Route::post('{share}/renew', [ConcertFamilyController::class, 'renew'])->middleware('throttle:family-invite')->name('renew');
    Route::post('{share}/revoke', [ConcertFamilyController::class, 'revoke'])->name('revoke');
});

Route::get('concerts/{concert}', [PublicConcertController::class, 'show'])->name('concerts.show');
Route::get('concerts/{concert}/cover', [PublicBrandingMediaController::class, 'concertCover'])->name('concerts.cover');
Route::get('concerts/{concert}/program', [PublicBrandingMediaController::class, 'program'])->name('concerts.program');
Route::post('concerts/{concert}/unlock', [PublicConcertController::class, 'unlock'])->middleware('throttle:10,1')->name('concerts.unlock');
Route::get('concerts/{concert}/media/{asset}/playback', [PublicConcertController::class, 'playback'])->middleware('throttle:concert-playback')->name('concerts.media.playback');
Route::get('concerts/{concert}/media/{asset}', [PublicConcertController::class, 'media'])->middleware('throttle:concert-media')->name('concerts.media.stream');
Route::get('concerts/{concert}/media/{asset}/download', [PublicConcertController::class, 'download'])->middleware('throttle:concert-media-download')->name('concerts.media.download');

Route::prefix('galleries')->name('guest-galleries.')->group(function (): void {
    Route::get('/', [GuestGalleryController::class, 'index'])->name('index');
    Route::get('studios/{studio}', [GuestGalleryController::class, 'studio'])->name('studios.show');
    Route::get('studios/{studio}/access', [GuestGalleryAccessController::class, 'studio'])->name('studios.access');
    Route::post('studios/{studio}/access', [GuestGalleryAccessController::class, 'unlockStudio'])
        ->middleware('throttle:guest-gallery-unlock')->name('studios.unlock');
    Route::post('studios/{studio}/notify', [GuestStudioSubscriptionController::class, 'store'])
        ->middleware('throttle:guest-gallery-registration')->name('studios.subscribe');
    Route::get('studio-notifications/{subscription}/unsubscribe', [GuestStudioSubscriptionController::class, 'unsubscribe'])
        ->middleware(['signed', 'throttle:20,1'])->name('studios.unsubscribe');
    Route::post('events/{schedulingEvent}/notify', [GuestGalleryRegistrationController::class, 'store'])
        ->middleware('throttle:guest-gallery-registration')->name('notifications.store');
    Route::get('notifications/{registration}/open', [GuestGalleryRegistrationController::class, 'open'])
        ->middleware(['signed', 'throttle:20,1'])->name('notifications.open');
    Route::get('notifications/{registration}/unsubscribe', [GuestGalleryRegistrationController::class, 'unsubscribe'])
        ->middleware(['signed', 'throttle:20,1'])->name('notifications.unsubscribe');
    Route::get('competitions/{schedulingEvent}/access', [GuestGalleryAccessController::class, 'competition'])->name('competitions.access');
    Route::post('competitions/{schedulingEvent}/access', [GuestGalleryAccessController::class, 'unlockCompetition'])
        ->middleware('throttle:guest-gallery-unlock')->name('competitions.unlock');
    Route::get('competitions/{schedulingEvent}', [GuestCompetitionGalleryController::class, 'show'])->name('competitions.show');
    Route::get('competitions/{schedulingEvent}/my-dancers', [GuestCompetitionGalleryController::class, 'dancers'])->name('competitions.dancers');
    Route::get('competitions/{schedulingEvent}/my-studios', [GuestCompetitionGalleryController::class, 'studios'])->name('competitions.studios');
    Route::get('competitions/{schedulingEvent}/improvisation', [GuestCompetitionGalleryController::class, 'improvisation'])->name('competitions.improvisation');
    Route::get('competitions/{schedulingEvent}/cart', [GuestGallerySelectionController::class, 'competitionCart'])->name('competitions.cart');
    Route::get('competitions/{schedulingEvent}/checkout', [GuestCompetitionGalleryController::class, 'checkout'])->name('competitions.checkout');
    Route::get('competitions/{schedulingEvent}/favourites', [GuestGallerySelectionController::class, 'competitionFavourites'])->name('competitions.favourites');
    Route::post('competitions/{schedulingEvent}/selections', [GuestGallerySelectionController::class, 'competition'])
        ->middleware('throttle:30,1')->name('competitions.selections');
    Route::post('competitions/{schedulingEvent}/selections/photos', [GuestGallerySelectionController::class, 'competitionPhotos'])
        ->middleware('throttle:30,1')->name('competitions.selections.photos');
    Route::get('competitions/{schedulingEvent}/photos/{photo}', [GuestCompetitionGalleryController::class, 'image'])
        ->whereUuid('photo')->name('competitions.image');
    Route::get('concerts/{concert}/access', [GuestGalleryAccessController::class, 'concert'])->name('concerts.access');
    Route::get('concerts/{concert}/groups/{group}/cover', [GuestConcertGalleryController::class, 'cover'])
        ->middleware('throttle:60,1')->name('concerts.cover');
    Route::post('concerts/{concert}/access', [GuestGalleryAccessController::class, 'unlockConcert'])
        ->middleware('throttle:guest-gallery-unlock')->name('concerts.unlock');
    Route::get('concerts/{concert}', [GuestConcertGalleryController::class, 'show'])->name('concerts.show');
    Route::get('concerts/{concert}/cart', [GuestConcertGalleryController::class, 'cart'])->name('concerts.cart');
    Route::get('concerts/{concert}/checkout', [GuestConcertGalleryController::class, 'checkout'])->name('concerts.checkout');
    Route::post('concerts/{concert}/selections', [GuestGallerySelectionController::class, 'concert'])
        ->middleware('throttle:30,1')->name('concerts.selections');
    Route::get('concerts/{concert}/assets/{asset}', [GuestConcertGalleryController::class, 'image'])
        ->whereUuid('asset')->name('concerts.image');
});

Route::middleware('guest')->group(function (): void {
    Route::get('customer/sign-in', [WebAuthController::class, 'legacyCreate'])->name('customer.login');
    Route::post('customer/sign-in', [WebAuthController::class, 'store'])->middleware('throttle:login')->name('customer.login.store');
    Route::get('customer/register', [CustomerAccountController::class, 'create'])->name('customer.register');
    Route::post('customer/register', [CustomerAccountController::class, 'store'])->middleware('throttle:5,1')->name('customer.register.store');
    Route::get('login', [WebAuthController::class, 'create'])->name('login');
    Route::post('login', [WebAuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:password-reset-link')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset')->name('password.update');
});

Route::post('logout', [WebAuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

if (app()->environment(['local', 'testing'])) {
    Route::post('local/switch-account', LocalAccountSwitchController::class)
        ->middleware('auth')
        ->name('local.account.switch');
}

Route::get('customer/email/verify/{id}/{hash}', [CustomerOnboardingController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
Route::middleware(['auth', 'two-factor.required'])->prefix('customer')->name('customer.')->group(function (): void {
    Route::get('email/verify', [CustomerOnboardingController::class, 'notice'])->name('verification.notice');
    Route::post('email/verify', [CustomerOnboardingController::class, 'resend'])->middleware('throttle:3,1')->name('verification.resend');
    Route::get('onboarding', [CustomerOnboardingController::class, 'edit'])->name('onboarding.edit');
    Route::post('onboarding', [CustomerOnboardingController::class, 'store'])->name('onboarding.store');
});

Route::middleware('guest')->group(function (): void {
    Route::get('two-factor-challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('two-factor-challenge', [TwoFactorController::class, 'verifyChallenge'])->middleware('throttle:6,1')->name('two-factor.verify');
});
Route::middleware('auth')->group(function (): void {
    Route::get('account/security', [TwoFactorController::class, 'settings'])->name('account.security');
    Route::post('account/security/two-factor', [TwoFactorController::class, 'begin'])
        ->middleware('throttle:sensitive-auth')->name('two-factor.begin');
    Route::post('account/security/two-factor/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:6,1')->name('two-factor.confirm');
    Route::post('account/security/two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])
        ->middleware('throttle:sensitive-auth')->name('two-factor.recovery-codes');
    Route::delete('account/security/two-factor', [TwoFactorController::class, 'disable'])
        ->middleware('throttle:sensitive-auth')->name('two-factor.disable');
});

Route::get('book-your-concert', [PublicConcertBookingController::class, 'create'])->name('concert-bookings.create');
Route::post('book-your-concert', [PublicConcertBookingController::class, 'store'])->middleware('throttle:concert-booking')->name('concert-bookings.store');
Route::get('book-your-concert/thanks', [PublicConcertBookingController::class, 'thanks'])->name('concert-bookings.thanks');
Route::get('book-your-concert/submitted/{concertBooking}', [PublicConcertBookingController::class, 'submission'])->name('concert-bookings.submission');

Route::middleware(['auth', 'two-factor.required'])->prefix('internal-documents')->name('internal-documents.')->group(function (): void {
    Route::get('resources/{resource}', [InternalDocumentController::class, 'resource'])->name('resources.show');
    Route::get('venues/{venue}/map', [InternalDocumentController::class, 'venueMap'])->name('venues.map');
    Route::get('events/{schedulingEvent}/programme', [InternalDocumentController::class, 'programme'])->name('events.programme');
    Route::get('messages/{message}/attachment', [InternalDocumentController::class, 'messageAttachment'])->name('messages.attachment');
});

Route::get('family/invitations/{member}/{token}', [CustomerFamilyController::class, 'invitation'])
    ->middleware('throttle:30,1')->name('customer.family.invitation');
Route::post('family/invitations/{member}/{token}', [CustomerFamilyController::class, 'accept'])
    ->middleware(['auth', 'two-factor.required', 'customer.onboarded', 'throttle:10,1'])->name('customer.family.accept');
Route::post('my/profile/family', [CustomerFamilyController::class, 'store'])
    ->middleware(['auth', 'two-factor.required', 'customer.onboarded', 'throttle:20,1'])->name('customer.family.store');

Route::get('my/profile/email/confirm', [CustomerAccountEmailController::class, 'confirm'])
    ->middleware(['auth', 'two-factor.required', 'signed', 'throttle:6,1'])->name('customer.profile.email.confirm');

Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])->prefix('my/profile')->name('customer.profile.')->group(function (): void {
    Route::get('/', [CustomerProfileController::class, 'edit'])->name('edit');
    Route::post('/', [CustomerProfileController::class, 'store'])->middleware('throttle:10,1')->name('store');
    Route::get('studios/search', [CustomerProfileController::class, 'searchStudios'])->middleware('throttle:60,1')->name('studios.search');
    Route::get('studios/{studio}/photos', [CustomerProfileController::class, 'studio'])->name('studio');
});
Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])
    ->prefix('my/studios/{studio}/bookings')->name('customer.studio-bookings.')->group(function (): void {
        Route::get('/', [CustomerStudioBookingController::class, 'index'])->name('index');
        Route::get('previous', [CustomerStudioBookingController::class, 'previous'])->name('previous');
        Route::post('draft', [CustomerStudioBookingController::class, 'saveDraft'])->middleware('throttle:20,1')->name('draft');
        Route::get('{concertBooking}', [CustomerStudioBookingController::class, 'show'])->name('show');
        Route::get('{concertBooking}/submission', [CustomerStudioBookingController::class, 'submission'])->name('submission');
        Route::post('{concertBooking}/family-numbers', [BookingFamilyNumbersController::class, 'customer'])->middleware('throttle:10,1')->name('families');
    });
Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])
    ->get('my/studio-hub', [CustomerStudioCommunicationController::class, 'hub'])
    ->name('customer.studio-hub');
Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])
    ->get('my/studios/{studio}/communications', CustomerStudioCommunicationController::class)
    ->name('customer.studio-communications.index');
Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])
    ->post('my/studios/{studio}/communications', [CustomerStudioCommunicationController::class, 'store'])
    ->middleware('throttle:10,1')->name('customer.studio-communications.store');
Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])
    ->post('my/studios/{studio}/communications/{source}/{message}/read', [CustomerStudioCommunicationController::class, 'read'])
    ->whereIn('source', ['message', 'email'])->whereUuid('message')->name('customer.studio-communications.read');
Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])
    ->post('my/studios/{studio}/events/{schedulingEvent}/seats', [StudioEventSeatsController::class, 'store'])
    ->middleware('throttle:10,1')->name('customer.studio-events.seats.store');
Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])
    ->post('my/studios/{studio}/events/{schedulingEvent}/requirements', [StudioEventRequirementsController::class, 'store'])
    ->middleware('throttle:10,1')->name('customer.studio-events.requirements.store');
Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])
    ->post('my/studios/{studio}/events/{schedulingEvent}/files', [StudioEventFileController::class, 'store'])
    ->middleware('throttle:10,1')->name('customer.studio-events.files.store');
Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])
    ->post('my/studios/{studio}/events/{schedulingEvent}/booking-changes', [StudioBookingChangeController::class, 'store'])
    ->middleware('throttle:10,1')->name('customer.studio-events.booking-changes.store');
Route::middleware(['auth', 'two-factor.required'])
    ->get('studio-event-files/{studioEventFile}', [StudioEventFileController::class, 'download'])
    ->name('studio-event-files.download');
Route::middleware(['auth', 'two-factor.required'])
    ->get('concert-editing/{schedulingEvent}/downloads/{kind}/{file?}', ConcertEditingDownloadController::class)
    ->whereIn('kind', ['programme', 'titles', 'video'])->whereUuid('file')->name('concert-editing.download');
Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])
    ->get('my/studios/{studio}/communications/attachments/{attachment}', [CustomerStudioCommunicationController::class, 'attachment'])
    ->name('customer.studio-communications.attachments.show');

Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])->prefix('my/messages')->name('customer.messages.')->group(function (): void {
    Route::get('/', [CustomerMessagesController::class, 'index'])->name('index');
    Route::post('/', [CustomerMessagesController::class, 'store'])->middleware('throttle:5,1')->name('store');
    Route::post('notifications/{notification}/read', [CustomerMessagesController::class, 'read'])->name('read');
    Route::get('attachments/{message}', [CustomerMessagesController::class, 'attachment'])->name('attachment');
    Route::get('conversations/{conversation}', [CustomerMessagesController::class, 'show'])->name('show');
    Route::post('conversations/{conversation}', [CustomerMessagesController::class, 'reply'])->middleware('throttle:10,1')->name('reply');
});

Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])->prefix('my/media')->name('customer.media.')->group(function (): void {
    Route::get('/', [CustomerMediaController::class, 'index'])->name('index');
    Route::get('favourites', [CustomerMediaController::class, 'favourites'])->name('favourites');
    Route::get('cart', [CustomerMediaController::class, 'cart'])->name('cart');
    Route::get('help', [CustomerMediaHelpController::class, 'index'])->name('help');
    Route::post('help', [CustomerMediaHelpController::class, 'store'])->middleware('throttle:5,1')->name('help.store');
    Route::post('help/articles/{article}/feedback', [CustomerMediaHelpController::class, 'feedback'])->middleware('throttle:20,1')->name('help.feedback');
    Route::get('orders', [CustomerOrderController::class, 'index'])->name('orders.index');
    Route::get('library/{kind}/{event}', [CustomerMediaLibraryController::class, 'show'])
        ->whereIn('kind', ['competition', 'concert'])->whereUuid('event')->name('library.show');
    Route::get('library/{kind}/{event}/download-all', [CustomerMediaLibraryController::class, 'downloadAll'])
        ->middleware('throttle:5,1')->whereIn('kind', ['competition', 'concert'])->whereUuid('event')->name('library.download-all');
    Route::get('library/{kind}/{event}/assets/{asset}/thumbnail', [CustomerMediaLibraryController::class, 'thumbnail'])
        ->whereIn('kind', ['competition', 'concert'])->whereUuid(['event', 'asset'])->name('library.thumbnail');
    Route::get('recommendations/{schedulingEvent}/{item}/{photo}/thumbnail', [CustomerMediaController::class, 'recommendationThumbnail'])->whereUuid(['item', 'photo'])->name('recommendations.thumbnail');
    Route::post('concerts', [CustomerMediaController::class, 'addConcert'])->middleware('throttle:10,1')->name('concerts.store');
});

Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])->prefix('my/checkout')->name('customer.checkout.')->group(function (): void {
    Route::post('account-cart', [CustomerCheckoutController::class, 'accountCart'])->name('account-cart');
    Route::get('{order}', [CustomerCheckoutController::class, 'show'])->name('show');
    Route::post('{order}/pay', [CustomerCheckoutController::class, 'pay'])->middleware('throttle:10,1')->name('pay');
});
Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])
    ->get('my/purchases/{entitlement}/assets/{asset}/download', CustomerPurchasedMediaDownloadController::class)
    ->middleware('throttle:30,1')->name('customer.purchases.download');

Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])->prefix('my/competitions')->name('customer.competitions.')->group(function (): void {
    Route::get('/', [CustomerCompetitionController::class, 'index'])->name('index');
    Route::post('/', [CustomerCompetitionController::class, 'store'])->middleware('throttle:10,1')->name('store');
    Route::get('{schedulingEvent}/cart', [CustomerCompetitionSelectionController::class, 'cart'])->name('cart');
    Route::post('{schedulingEvent}/checkout', [CustomerCheckoutController::class, 'competition'])->name('checkout');
    Route::get('{schedulingEvent}/favourites', [CustomerCompetitionSelectionController::class, 'favourites'])->name('favourites');
    Route::post('{schedulingEvent}/selections', [CustomerCompetitionSelectionController::class, 'store'])->middleware('throttle:30,1')->name('selections.store');
    Route::post('{schedulingEvent}/selections/photos', [CustomerCompetitionSelectionController::class, 'storePhotos'])->middleware('throttle:30,1')->name('selections.photos.store');
    Route::get('{schedulingEvent}', [CustomerCompetitionController::class, 'show'])->name('show');
    Route::get('{schedulingEvent}/my-dancers', [CustomerCompetitionController::class, 'dancers'])->name('dancers');
    Route::get('{schedulingEvent}/my-studios', [CustomerCompetitionController::class, 'studios'])->name('studios');
    Route::get('{schedulingEvent}/improvisation', [CustomerCompetitionController::class, 'improvisation'])->name('improvisation');
    Route::get('{schedulingEvent}/photos/{photo}', [CustomerCompetitionController::class, 'image'])->whereUuid('photo')->name('image');
});

Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])->prefix('my/concerts')->name('customer.concerts.')->group(function (): void {
    Route::get('{concert}/photos', [CustomerConcertGalleryController::class, 'show'])->name('photos.show');
    Route::get('{concert}/photos/assets/{asset}', [CustomerConcertGalleryController::class, 'image'])->whereUuid('asset')->name('photos.image');
    Route::post('{concert}/photos/selections', [CustomerConcertPhotoSelectionController::class, 'store'])->middleware('throttle:30,1')->name('photos.selections.store');
});
Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])->get('my/studios/{studio}/photo-cart', [CustomerConcertPhotoSelectionController::class, 'cart'])->name('customer.concerts.photos.cart');
Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])->post('my/studios/{studio}/photo-cart/checkout', [CustomerCheckoutController::class, 'concert'])->name('customer.concerts.photos.checkout');

Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])
    ->prefix('my/studios/{studio}/invoices')->name('customer.studio-invoices.')->group(function (): void {
        Route::get('/', [CustomerStudioInvoiceController::class, 'index'])->name('index');
        Route::get('{studioInvoice}', [CustomerStudioInvoiceController::class, 'show'])->name('show');
        Route::get('{studioInvoice}/pdf', [CustomerStudioInvoiceController::class, 'pdf'])->name('pdf');
        Route::get('{studioInvoice}/token', [CustomerStudioInvoiceController::class, 'token'])->middleware('throttle:10,1')->name('token');
        Route::post('{studioInvoice}/pay', [CustomerStudioInvoiceController::class, 'pay'])->middleware('throttle:5,1')->name('pay');
        Route::post('{studioInvoice}/check-payment', [CustomerStudioInvoiceController::class, 'reconcile'])->middleware('throttle:5,1')->name('reconcile');
    });

Route::middleware(['auth', 'admin.required', 'two-factor.required'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::prefix('website')->name('website.')->middleware('can:manageWebsite')->group(function (): void {
            Route::get('/', [AdminWebsiteController::class, 'index'])->name('pages.index');
            Route::get('pages/{page}', [AdminWebsiteController::class, 'page'])->name('pages.edit');
            Route::patch('pages/{page}', [AdminWebsiteController::class, 'savePage'])->name('pages.update');
            Route::get('pages/{page}/preview', [AdminWebsiteController::class, 'preview'])->name('pages.preview');
            Route::get('events', [AdminWebsiteController::class, 'events'])->name('events.index');
            Route::patch('events/{schedulingEvent}', [AdminWebsiteController::class, 'event'])->name('events.update');
            Route::get('bios', [AdminWebsiteController::class, 'bios'])->name('bios.index');
            Route::get('bios/{crewProfile}', [AdminWebsiteController::class, 'bio'])->name('bios.edit');
            Route::patch('bios/{crewProfile}', [AdminWebsiteController::class, 'saveBio'])->name('bios.update');
            Route::get('help', [AdminWebsiteController::class, 'help'])->name('help.index');
            Route::get('help/create', [AdminWebsiteController::class, 'article'])->name('help.create');
            Route::post('help', [AdminWebsiteController::class, 'saveArticle'])->name('help.store');
            Route::get('help/{article}', [AdminWebsiteController::class, 'article'])->name('help.edit');
            Route::patch('help/{article}', [AdminWebsiteController::class, 'saveArticle'])->name('help.update');
        });

        Route::get('messages', [AdminMessageCentreController::class, 'index'])->name('messages.index');
        Route::post('messages', [AdminMessageCentreController::class, 'send'])->middleware('throttle:20,1')->name('messages.send');
        Route::get('messages/crew/{chat}/attachments/{message}', [AdminMessageCentreController::class, 'attachment'])->name('messages.attachment');
        Route::get('hub', AdminHubDashboardController::class)->name('hub.dashboard');
        Route::get('concert-tracker', AdminConcertTrackerController::class)->name('concert-tracker.index');
        Route::get('concert-tracker/communication-schedule', [AdminConcertTrackerAutomationController::class, 'index'])->name('concert-tracker.automation.index');
        Route::get('concert-tracker/automation-settings', [AdminConcertTrackerAutomationController::class, 'settings'])->name('concert-tracker.automation.settings');
        Route::patch('concert-tracker/automation-settings', [AdminConcertTrackerAutomationController::class, 'updateSettings'])->name('concert-tracker.automation.settings.update');
        Route::post('concert-tracker/automation-settings/test-email', [AdminConcertTrackerAutomationController::class, 'sendBrandingTest'])->middleware('throttle:5,1')->name('concert-tracker.automation.settings.test-email');
        Route::post('concert-tracker/communication-schedule/{schedulingEvent}/{taskKey}', [AdminConcertTrackerAutomationController::class, 'schedule'])->name('concert-tracker.automation.schedule');
        Route::get('concert-tracker/alerts', [AdminConcertTrackerAlertController::class, 'index'])->name('concert-tracker.alerts.index');
        Route::post('concert-tracker/alerts/read-all', [AdminConcertTrackerAlertController::class, 'readAll'])->name('concert-tracker.alerts.read-all');
        Route::post('concert-tracker/alerts/{alert}/read', [AdminConcertTrackerAlertController::class, 'read'])->name('concert-tracker.alerts.read');
        Route::patch('concert-tracker/events/{schedulingEvent}/source/{taskKey}/tracker', AdminSourceTrackerBarController::class)->whereIn('taskKey', ['crew_ready', 'programme_prepared', 'slideshow_received', 'video_titles', 'photos_uploaded', 'concert_edited', 'quality_check', 'video_uploaded', 'director_corrections', 'photo_release', 'final_release', 'invoice_sent'])->name('concert-tracker.source-bar.update');
        Route::patch('concert-tracker/events/{schedulingEvent}/video-inclusions', AdminConcertVideoInclusionsController::class)->name('concert-tracker.video-inclusions.update');
        Route::patch('concert-tracker/events/{schedulingEvent}/quality-check', AdminConcertQualityChecklistController::class)->name('concert-tracker.quality.update');
        Route::get('concert-tracker/events/{schedulingEvent}/tasks/{taskKey}', [AdminConcertTrackerTaskController::class, 'show'])->name('concert-tracker.tasks.show');
        Route::patch('concert-tracker/events/{schedulingEvent}/tasks/{taskKey}', [AdminConcertTrackerTaskController::class, 'update'])->name('concert-tracker.tasks.update');
        Route::patch('concert-tracker/events/{schedulingEvent}/tasks/{taskKey}/template', [AdminConcertTrackerTaskController::class, 'updateTemplate'])->name('concert-tracker.tasks.template.update');
        Route::post('concert-tracker/events/{schedulingEvent}/tasks/{taskKey}/email', [AdminConcertTrackerTaskController::class, 'send'])->name('concert-tracker.tasks.email.send');
        Route::get('concert-tracker/events/{schedulingEvent}/communications', [AdminConcertCommunicationController::class, 'event'])->name('concert-tracker.communications.show');
        Route::patch('concert-tracker/studio-event-files/{studioEventFile}', [StudioEventFileController::class, 'review'])->name('concert-tracker.studio-event-files.review');
        Route::patch('concert-tracker/booking-changes/{bookingChange}', [StudioBookingChangeController::class, 'review'])->name('concert-tracker.booking-changes.review');
        Route::get('concert-tracker/studio-communications', [AdminConcertCommunicationController::class, 'inbox'])->name('concert-tracker.studio-communications.index');
        Route::get('concert-tracker/studios/{studio}/communications', [AdminConcertCommunicationController::class, 'studio'])->name('concert-tracker.studio-communications.show');
        Route::post('concert-tracker/studios/{studio}/communications', [AdminConcertCommunicationController::class, 'reply'])->name('concert-tracker.studio-communications.reply');
        Route::patch('concert-tracker/studios/{studio}/communications/assignment', [AdminConcertCommunicationController::class, 'assign'])->name('concert-tracker.studio-communications.assign');
        Route::get('concert-tracker/studio-communications/attachments/{attachment}', [AdminConcertCommunicationController::class, 'attachment'])->name('concert-tracker.studio-communications.attachments.show');
        Route::get('concert-tracker/email-templates', [AdminConcertCommunicationController::class, 'templates'])->name('concert-tracker.templates.index');
        Route::patch('concert-tracker/email-templates/{template}', [AdminConcertCommunicationController::class, 'updateTemplate'])->name('concert-tracker.templates.update');
        Route::post('concert-tracker/email-deliveries/{delivery}/cancel', [AdminConcertCommunicationController::class, 'cancel'])->name('concert-tracker.deliveries.cancel');
        Route::post('concert-tracker/email-deliveries/{delivery}/resend', [AdminConcertCommunicationController::class, 'resend'])->name('concert-tracker.deliveries.resend');
        Route::get('exceptions', AdminExceptionController::class)->name('exceptions.index');
        Route::prefix('scheduling-events/{schedulingEvent}/programme')->name('programmes.')->controller(ProgrammeController::class)->group(function (): void {
            Route::get('extraction', 'extraction')->name('extraction');
            Route::get('/', 'show')->name('show');
            Route::post('/', 'start')->name('start');
            Route::put('draft', 'save')->name('save');
            Route::post('confirm', 'confirm')->name('confirm');
            Route::post('source', 'upload')->name('upload');
            Route::post('attached-source', 'useAttached')->name('use-attached');
            Route::get('sources/{source}', 'source')->name('source');
            Route::get('snapshot/{revision?}', 'snapshot')->name('snapshot');
        });
        Route::patch('studios/{studio}/status', [AdminStudioController::class, 'updateStatus'])->name('studios.status.update');
        Route::post('studios/{studio}/gallery-code', [AdminStudioController::class, 'saveGalleryCode'])->middleware('throttle:10,1')->name('studios.gallery-code.update');
        Route::post('studios/{studio}/account-access', [AdminStudioController::class, 'inviteAccount'])->name('studios.account-access.store');
        Route::delete('studios/{studio}/account-access/{accountAccess}', [AdminStudioController::class, 'revokeAccount'])->name('studios.account-access.revoke');
        Route::get('studio-requests', [AdminStudioRequestController::class, 'index'])->name('studio-requests.index');
        Route::get('studio-requests/{submission}', [AdminStudioRequestController::class, 'show'])->name('studio-requests.show');
        Route::post('studio-requests/{submission}', [AdminStudioRequestController::class, 'store'])->name('studio-requests.store');
        Route::resource('studios', AdminStudioController::class)->except(['show', 'destroy']);
        Route::get('studios/{studio}/concert-code-groups', [AdminConcertCodeGroupController::class, 'index'])->name('concert-code-groups.index');
        Route::post('studios/{studio}/concert-code-groups', [AdminConcertCodeGroupController::class, 'store'])->name('concert-code-groups.store');
        Route::post('studios/{studio}/concert-code-groups/{group}/concerts', [AdminConcertCodeGroupController::class, 'concerts'])->name('concert-code-groups.concerts');
        Route::post('studios/{studio}/concert-code-groups/{group}/release', [AdminConcertCodeGroupController::class, 'release'])->name('concert-code-groups.release');
        Route::post('studios/{studio}/concert-code-groups/{group}/code', [AdminConcertCodeGroupController::class, 'code'])->name('concert-code-groups.code');
        Route::patch('competition-contacts/{competitionContact}/status', [AdminCompetitionContactController::class, 'updateStatus'])->name('competition-contacts.status.update');
        Route::resource('competition-contacts', AdminCompetitionContactController::class)->except(['show', 'destroy']);
        Route::resource('concerts', AdminConcertController::class)->except(['show', 'destroy']);
        Route::resource('crew', AdminCrewProfileController::class)
            ->parameters(['crew' => 'crewProfile'])
            ->except(['show', 'destroy']);
        Route::post('crew/{crewProfile}/invite', [AdminCrewProfileController::class, 'invite'])->name('crew.invite');
        Route::get('crew-roles', [AdminCrewRoleController::class, 'index'])->name('crew-roles.index');
        Route::post('crew-roles', [AdminCrewRoleController::class, 'store'])->name('crew-roles.store');
        Route::put('crew-roles/matrix', [AdminCrewRoleController::class, 'updateMatrix'])->name('crew-roles.matrix.update');
        Route::put('crew-roles/{crewRole}', [AdminCrewRoleController::class, 'update'])->name('crew-roles.update');
        Route::get('crew-contracts/{crewContract}/duplicate', [AdminCrewContractController::class, 'duplicate'])->name('crew-contracts.duplicate');
        Route::resource('crew-contracts', AdminCrewContractController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('crew-management/recognitions-rewards', [AdminCrewManagementController::class, 'recognitionsRewards'])->name('crew-management.recognitions-rewards');
        Route::post('crew-management/recognition-types', [AdminCrewManagementController::class, 'storeRecognitionType'])->name('recognition-types.store');
        Route::put('crew-management/recognition-types/{recognitionType}', [AdminCrewManagementController::class, 'updateRecognitionType'])->name('recognition-types.update');
        Route::post('crew-management/recognitions', [AdminCrewManagementController::class, 'awardRecognition'])->name('recognitions.store');
        Route::get('crew-management/training', [AdminTrainingController::class, 'index'])->name('crew-management.training');
        Route::get('crew-management/training-overview', [AdminTrainingController::class, 'overview'])->name('training-courses.overview');
        Route::get('crew-management/training-overview/export', [AdminTrainingController::class, 'export'])->name('training-courses.export');
        Route::get('crew-management/training-history/{crewProfile}', [AdminTrainingController::class, 'crewHistory'])->name('training-courses.crew-history');
        Route::post('crew-management/training-reminders/{trainingEnrolment}', [AdminTrainingController::class, 'logReminder'])->name('training-reminders.store');
        Route::get('crew-management/training/create', [AdminTrainingController::class, 'create'])->name('training-courses.create');
        Route::post('crew-management/training', [AdminTrainingController::class, 'store'])->name('training-courses.store');
        Route::get('crew-management/training/{trainingCourse}/edit', [AdminTrainingController::class, 'edit'])->name('training-courses.edit');
        Route::put('crew-management/training/{trainingCourse}', [AdminTrainingController::class, 'update'])->name('training-courses.update');
        Route::get('crew-management/training/{trainingCourse}/assignments', [AdminTrainingController::class, 'assignments'])->name('training-courses.assignments');
        Route::put('crew-management/training/{trainingCourse}/assignments', [AdminTrainingController::class, 'updateAssignments'])->name('training-courses.assignments.update');
        Route::resource('scheduling-events', AdminSchedulingEventController::class)->except('destroy');
        Route::get('media/competition-photos', [CompetitionGalleryController::class, 'index'])->name('competition-gallery.index');
        Route::get('media/competition-videos', [AdminCompetitionVideoController::class, 'events'])->name('competition-videos.events');
        Route::get('media/publicity/sharing', [AdminGallerySharingController::class, 'index'])->name('publicity.sharing.index');
        Route::get('media/publicity/sharing/events/{schedulingEvent}/qr', [AdminGallerySharingController::class, 'eventQr'])->name('publicity.sharing.event-qr');
        Route::get('media/publicity/sharing/studios/{studio}/qr', [AdminGallerySharingController::class, 'studioQr'])->name('publicity.sharing.studio-qr');
        Route::get('media/publicity/tv-slideshows', [AdminSlideshowController::class, 'index'])->name('publicity.tv-slideshows');
        Route::post('media/publicity/slides', [AdminSlideshowController::class, 'storeSlide'])->name('publicity.slides.store');
        Route::put('media/publicity/slides/{slide}', [AdminSlideshowController::class, 'updateSlide'])->name('publicity.slides.update');
        Route::post('media/publicity/screens/{screen}/revoke', [AdminSlideshowController::class, 'revoke'])->name('publicity.screens.revoke');
        Route::get('media/publicity/events/{schedulingEvent}', [AdminSlideshowController::class, 'show'])->name('publicity.show');
        Route::put('media/publicity/events/{schedulingEvent}', [AdminSlideshowController::class, 'update'])->name('publicity.update');
        Route::post('media/publicity/events/{schedulingEvent}/slides', [AdminSlideshowController::class, 'storeSlide'])->name('publicity.event-slides.store');
        Route::post('media/publicity/events/{schedulingEvent}/pair', [AdminSlideshowController::class, 'pair'])->name('publicity.pair');
        Route::get('media/publicity/events/{schedulingEvent}/preview', [AdminSlideshowController::class, 'preview'])->name('publicity.preview');
        Route::get('media/publicity/events/{schedulingEvent}/manifest', [AdminSlideshowController::class, 'manifest'])->name('publicity.manifest');
        Route::get('media/publicity/events/{schedulingEvent}/images/{key}', [AdminSlideshowController::class, 'image'])->name('publicity.image');
        Route::get('media/publicity/events/{schedulingEvent}/videos/{key}', [AdminSlideshowController::class, 'video'])->name('publicity.video');
        Route::get('media/top-pics', [AdminTopPicsController::class, 'index'])->name('top-pics.index');
        Route::get('media/top-pics/{schedulingEvent}', [AdminTopPicsController::class, 'show'])->name('top-pics.show');
        Route::post('media/top-pics/{schedulingEvent}/cover', [AdminTopPicsController::class, 'cover'])->name('top-pics.cover');
        Route::get('media/top-pics/{schedulingEvent}/photos/{photo}/download', [AdminTopPicsController::class, 'download'])->name('top-pics.download');
        Route::patch('concert-tracker/events/{schedulingEvent}/director-corrections', [DirectorCorrectionsController::class, 'update'])->name('concert-tracker.corrections.update');
        Route::get('media/concert-photo-releases/{group}/email', [PhotoReleaseEmailController::class, 'show'])->name('concert-photo-releases.email');
        Route::post('media/concert-photo-releases/{group}/email', [PhotoReleaseEmailController::class, 'send'])->name('concert-photo-releases.email.send');
        Route::get('media/concert-photo-releases', [AdminConcertPhotoReleaseController::class, 'index'])->name('concert-photo-releases.index');
        Route::post('media/concert-photo-releases', [AdminConcertPhotoReleaseController::class, 'store'])->name('concert-photo-releases.store');
        Route::post('media/concert-photo-releases/{group}/release', [AdminConcertPhotoReleaseController::class, 'release'])->name('concert-photo-releases.release');
        Route::post('media/concert-photo-releases/{group}/cover', [AdminConcertPhotoReleaseController::class, 'updateCover'])->name('concert-photo-releases.cover.update');
        Route::get('media/concert-photos', [AdminConcertPhotoGalleryController::class, 'events'])->name('concert-photo-gallery.events');
        Route::get('media/concert-photos/{schedulingEvent}', [AdminConcertPhotoGalleryController::class, 'show'])->name('concert-photo-gallery.show');
        Route::post('media/concert-photos/{schedulingEvent}/sync', [AdminConcertPhotoGalleryController::class, 'sync'])->name('concert-photo-gallery.sync');
        Route::post('media/concert-photos/{schedulingEvent}/albums/{collection}', [AdminConcertPhotoGalleryController::class, 'update'])->name('concert-photo-gallery.albums.update');
        Route::post('media/concert-photos/{schedulingEvent}/albums/{collection}/cover', [AdminConcertPhotoGalleryController::class, 'updateCover'])->name('concert-photo-gallery.albums.cover.update');
        Route::get('media/concert-photos/{schedulingEvent}/assets/{asset}', [AdminConcertPhotoGalleryController::class, 'asset'])->name('concert-photo-gallery.asset');
        Route::get('media/concert-photos/{schedulingEvent}/photos/{photo}', [AdminConcertPhotoGalleryController::class, 'image'])->name('concert-photo-gallery.image');
        Route::get('scheduling-events/{schedulingEvent}/title-cards', [AdminConcertTitleCardController::class, 'show'])->name('concert-title-cards.show');
        Route::post('scheduling-events/{schedulingEvent}/title-cards/publish', [ConcertTitlePackageController::class, 'publish'])->name('concert-title-cards.publish');
        Route::patch('scheduling-events/{schedulingEvent}/title-cards', [AdminConcertTitleCardController::class, 'update'])->name('concert-title-cards.update');
        Route::get('scheduling-events/{schedulingEvent}/competition-videos', [AdminCompetitionVideoController::class, 'index'])->name('competition-videos.index');
        Route::post('scheduling-events/{schedulingEvent}/competition-videos/files/{file}/assign', [AdminCompetitionVideoController::class, 'assign'])->name('competition-videos.assign');
        Route::post('scheduling-events/{schedulingEvent}/competition-videos/hold', [AdminCompetitionVideoController::class, 'hold'])->name('competition-videos.hold');
        Route::get('media/settings', [AdminMediaCommerceController::class, 'index'])->name('media-settings.index');
        Route::post('media/settings', [AdminMediaCommerceController::class, 'update'])->name('media-settings.update');
        Route::post('media/price-lists', [AdminMediaCommerceController::class, 'storePriceList'])->name('media-price-lists.store');
        Route::patch('media/price-lists/{priceList}', [AdminMediaCommerceController::class, 'updatePriceList'])->name('media-price-lists.update');
        Route::post('media/price-list-assignments', [AdminMediaCommerceController::class, 'assignPriceList'])->name('media-price-lists.assign');
        Route::post('media/discount-codes', [AdminMediaCommerceController::class, 'storeCode'])->name('media-settings.codes.store');
        Route::patch('media/discount-codes/{discountCode}', [AdminMediaCommerceController::class, 'updateCode'])->name('media-settings.codes.update');
        Route::delete('media/discount-codes/{discountCode}', [AdminMediaCommerceController::class, 'deleteCode'])->name('media-settings.codes.delete');
        Route::post('media/customer-credits', [AdminMediaCommerceController::class, 'grantCustomerCredit'])->name('media-settings.customer-credits.store');
        Route::post('media/notification-tests', [AdminMediaCommerceController::class, 'sendNotificationTest'])->name('media-settings.notifications.test');
        Route::post('media/watermarks', [AdminMediaWatermarkController::class, 'store'])->name('media-watermarks.store');
        Route::post('media/watermarks/{watermark}', [AdminMediaWatermarkController::class, 'update'])->name('media-watermarks.update');
        Route::get('media/watermarks/{watermark}/artwork', [AdminMediaWatermarkController::class, 'artwork'])->name('media-watermarks.artwork');
        Route::get('media/watermarks/{watermark}/preview', [AdminMediaWatermarkController::class, 'preview'])->name('media-watermarks.preview');
        Route::get('media/watermark', [AdminMediaCommerceController::class, 'watermark'])->name('media-settings.watermark');
        Route::get('media/sales', [AdminMediaSalesController::class, 'index'])->name('media-sales.index');
        Route::get('media/sales/export', [AdminMediaSalesController::class, 'export'])->name('media-sales.export');
        Route::get('media/sales/studios/{studio}/activity', [AdminMediaSalesController::class, 'studioActivity'])
            ->name('media-sales.studios.activity');
        Route::get('media/sales/studios/{studio}/activity/export', [AdminMediaSalesController::class, 'exportStudioActivity'])
            ->name('media-sales.studios.activity.export');
        Route::get('media/sales/events/{kind}/{eventUuid}/activity', [AdminMediaSalesController::class, 'eventActivity'])
            ->whereIn('kind', ['competition', 'concert'])->whereUuid('eventUuid')->name('media-sales.events.activity');
        Route::get('media/sales/events/{kind}/{eventUuid}/activity/export', [AdminMediaSalesController::class, 'exportEventActivity'])
            ->whereIn('kind', ['competition', 'concert'])->whereUuid('eventUuid')->name('media-sales.events.activity.export');

        Route::get('studio-invoices', [AdminStudioInvoiceController::class, 'index'])->name('studio-invoices.index');
        Route::get('studio-invoices/settings', [AdminStudioInvoiceController::class, 'settings'])->name('studio-invoices.settings');
        Route::put('studio-invoices/settings', [AdminStudioInvoiceController::class, 'saveSettings'])->name('studio-invoices.settings.save');
        Route::post('concert-bookings/{concertBooking}/invoices', [AdminStudioInvoiceController::class, 'store'])->name('studio-invoices.store');
        Route::get('studio-invoices/{studioInvoice}', [AdminStudioInvoiceController::class, 'show'])->name('studio-invoices.show');
        Route::get('studio-invoices/{studioInvoice}/email', [AdminStudioInvoiceController::class, 'email'])->name('studio-invoices.email');
        Route::get('studio-invoices/{studioInvoice}/pdf', [AdminStudioInvoiceController::class, 'pdf'])->name('studio-invoices.pdf');
        Route::patch('studio-invoices/{studioInvoice}', [AdminStudioInvoiceController::class, 'update'])->name('studio-invoices.update');
        Route::post('studio-invoices/{studioInvoice}/refresh', [AdminStudioInvoiceController::class, 'refresh'])->name('studio-invoices.refresh');
        Route::post('studio-invoices/{studioInvoice}/issue', [AdminStudioInvoiceController::class, 'issue'])->name('studio-invoices.issue');
        Route::post('studio-invoices/{studioInvoice}/void', [AdminStudioInvoiceController::class, 'void'])->name('studio-invoices.void');
        Route::post('studio-invoices/{studioInvoice}/payments', [AdminStudioInvoiceController::class, 'recordPayment'])->name('studio-invoices.record-payment');
        Route::post('studio-invoices/{studioInvoice}/payments/{payment}/reverse', [AdminStudioInvoiceController::class, 'reverse'])->name('studio-invoices.reverse');
        Route::post('studio-invoices/{studioInvoice}/check-payment', [AdminStudioInvoiceController::class, 'reconcile'])->middleware('throttle:10,1')->name('studio-invoices.reconcile');
        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::get('orders/{order}/items/{item}/thumbnail', [AdminOrderController::class, 'thumbnail'])->name('orders.items.thumbnail');
        Route::get('media/customer-support/conversations/{conversation}', [AdminCustomerConversationController::class, 'show'])->name('media-support.conversation');
        Route::post('media/customer-support/conversations/{conversation}', [AdminCustomerConversationController::class, 'reply'])->name('media-support.reply');
        Route::get('media/customer-support/attachments/{message}', [AdminCustomerConversationController::class, 'attachment'])->name('media-support.attachment');
        Route::get('media/customer-support', [AdminCustomerSupportController::class, 'index'])->name('media-support.index');
        Route::patch('media/customer-support/{supportRequest}', [AdminCustomerSupportController::class, 'resolve'])->name('media-support.resolve');
        Route::get('customers', [AdminCustomerAccountController::class, 'index'])->name('customers.index');
        Route::get('customers/search/accounts', [AdminCustomerAccountController::class, 'search'])->name('customers.search');
        Route::get('customers/follow-ups', [AdminCustomerAccountController::class, 'followUps'])->name('customers.follow-ups');
        Route::get('customers/{customer}', [AdminCustomerAccountController::class, 'show'])->name('customers.show');
        Route::get('customers/{customer}/media-assets/{asset}', [AdminCustomerAccountController::class, 'mediaAsset'])->name('customers.media-assets.show');
        Route::post('customers/complimentary-media', [AdminCustomerAccountController::class, 'grant'])->name('customers.complimentary-media.store');
        Route::post('customers/{customer}/orders/{order}/complimentary-media/revoke', [AdminCustomerAccountController::class, 'revokeComplimentaryMedia'])->name('customers.complimentary-media.revoke');
        Route::patch('customers/{customer}/status', [AdminCustomerAccountController::class, 'updateStatus'])->name('customers.status.update');
        Route::patch('customers/{customer}/contact', [AdminCustomerAccountController::class, 'updateContact'])->name('customers.contact.update');
        Route::post('customers/{customer}/password-reset', [AdminCustomerAccountController::class, 'sendPasswordReset'])->name('customers.password-reset.store');
        Route::post('customers/{customer}/emails', [AdminCustomerAccountController::class, 'sendEmail'])->name('customers.emails.store');
        Route::post('customers/{customer}/events', [AdminCustomerAccountController::class, 'addEvent'])->name('customers.events.store');
        Route::post('customers/{customer}/credits', [AdminCustomerAccountController::class, 'addCredit'])->name('customers.credits.store');
        Route::post('customers/{customer}/orders/{order}/refunds', [AdminCustomerAccountController::class, 'refund'])->name('customers.refunds.store');
        Route::patch('customers/{customer}/preferences', [AdminCustomerAccountController::class, 'preferences'])->name('customers.preferences.update');
        Route::post('customers/{customer}/notes', [AdminCustomerAccountController::class, 'note'])->name('customers.notes.store');
        Route::patch('customers/{customer}/notes/{note}/complete', [AdminCustomerAccountController::class, 'completeNote'])->name('customers.notes.complete');
        Route::post('customers/{customer}/risk-events', [AdminCustomerAccountController::class, 'risk'])->name('customers.risk-events.store');
        Route::patch('customers/{customer}/risk-events/{riskEvent}/resolve', [AdminCustomerAccountController::class, 'resolveRisk'])->name('customers.risk-events.resolve');
        Route::post('customers/merge', [AdminCustomerAccountController::class, 'merge'])->name('customers.merge.store');
        Route::post('customers/{customer}/privacy', [AdminCustomerAccountController::class, 'privacy'])->name('customers.privacy.store');
        Route::get('scheduling-events/{schedulingEvent}/competition-gallery', [CompetitionGalleryController::class, 'show'])->name('competition-gallery.show');
        Route::get('scheduling-events/{schedulingEvent}/competition-gallery/customer-preview', [CompetitionCustomerPreviewController::class, 'show'])->name('competition-gallery.customer-preview');
        Route::get('scheduling-events/{schedulingEvent}/competition-gallery/customer-preview/photos/{photo}', [CompetitionCustomerPreviewController::class, 'image'])->whereUuid('photo')->name('competition-gallery.customer-preview.image');
        Route::post('scheduling-events/{schedulingEvent}/competition-gallery/routine-studio', [CompetitionGalleryController::class, 'saveRoutineStudio'])->name('competition-gallery.routine-studio');
        Route::get('scheduling-events/{schedulingEvent}/competition-gallery/settings', [CompetitionGalleryController::class, 'settings'])->name('competition-gallery.settings');
        Route::post('scheduling-events/{schedulingEvent}/competition-gallery/settings', [CompetitionGalleryController::class, 'saveSettings'])->name('competition-gallery.settings.update');
        Route::get('scheduling-events/{schedulingEvent}/competition-gallery/photos/{photo}', [CompetitionGalleryController::class, 'image'])->whereUuid('photo')->name('competition-gallery.image');
        Route::get('scheduling-events/{schedulingEvent}/photo-uploads', [AdminPhotoUploadsController::class, 'show'])->name('photo-uploads.show');
        Route::post('scheduling-events/{schedulingEvent}/photo-uploads', [AdminPhotoUploadsController::class, 'update'])->name('photo-uploads.update');
        Route::get('event-management/pending', [AdminConcertBookingController::class, 'pending'])->name('event-management.pending');
        Route::get('event-management/types', [AdminEventTypeController::class, 'index'])->name('event-types.index');
        Route::post('event-management/types', [AdminEventTypeController::class, 'store'])->name('event-types.store');
        Route::put('event-management/types/{eventType}', [AdminEventTypeController::class, 'update'])->name('event-types.update');
        Route::get('payment-settings', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::get('timesheets', [AdminTimesheetController::class, 'index'])->name('timesheets.index');
        Route::get('timesheets/invoices', [AdminTimesheetController::class, 'invoices'])->name('timesheets.invoices.index');
        Route::get('timesheets/invoices/{invoice}', [AdminTimesheetController::class, 'showInvoice'])->name('timesheets.invoices.show');
        Route::get('timesheets/invoices/{invoice}/export', [AdminTimesheetController::class, 'exportInvoice'])->name('timesheets.invoices.export');
        Route::get('timesheets/invoices/{invoice}/print', [AdminTimesheetController::class, 'printInvoice'])->name('timesheets.invoices.print');
        Route::patch('timesheets/invoices/{invoice}', [AdminTimesheetController::class, 'updateInvoice'])->name('timesheets.invoices.update');
        Route::post('payment-settings/rates', [AdminPaymentController::class, 'storeRate'])->name('payments.rates.store');
        Route::post('payment-settings/rate-matrix', [AdminPaymentController::class, 'storeMatrix'])->name('payments.matrix.store');
        Route::patch('scheduling-events-bulk', [AdminSchedulingEventController::class, 'bulkUpdate'])->name('scheduling-events.bulk');
        Route::patch('scheduling-events/{schedulingEvent}/shifts/{schedulingShift}/times', [AdminSchedulingEventController::class, 'updateShiftTimes'])->name('scheduling-events.shifts.times');
        Route::post('scheduling-events/{schedulingEvent}/roles', [AdminEventWorkflowController::class, 'addRole'])->name('scheduling-events.roles.store');
        Route::patch('scheduling-events/{schedulingEvent}/workflow', [AdminEventWorkflowController::class, 'changeStatus'])->name('scheduling-events.workflow');
        Route::patch('scheduling-events/{schedulingEvent}/availability', [AdminSchedulingEventController::class, 'updateAvailability'])->name('scheduling-events.availability');
        Route::put('scheduling-shifts/{schedulingShift}/roles/{crewRole}/assignment', [AdminSchedulingEventController::class, 'updateCrewAssignment'])->name('scheduling-shifts.roles.assignment');
        Route::put('scheduling-shifts/{schedulingShift}/requirements', [AdminSchedulingEventController::class, 'updateShiftRequirements'])->name('scheduling-shifts.requirements');
        Route::post('scheduling-events/{schedulingEvent}/roster/publish', [AdminSchedulingEventController::class, 'publishRoster'])->name('scheduling-events.roster.publish');
        Route::put('scheduling-shifts/{schedulingShift}/crew/{crewProfile}/availability', [AdminSchedulingEventController::class, 'updateCrewAvailability'])->name('scheduling-shifts.crew.availability');
        Route::put('scheduling-shifts/{schedulingShift}/crew/{crewProfile}/team-leader', [AdminSchedulingEventController::class, 'updateTeamLeader'])->name('scheduling-shifts.crew.team-leader');
        Route::put('scheduling-assignments/{assignment}/equipment', [AdminSchedulingEventController::class, 'updateAssignmentEquipment'])->name('scheduling-assignments.equipment');
        Route::put('scheduling-assignments/{assignment}/time', [AdminTimeController::class, 'update'])->name('scheduling-assignments.time.update');
        Route::put('scheduling-assignments/{assignment}/allowances', [AdminPaymentController::class, 'updateAllowances'])->name('scheduling-assignments.allowances.update');
        Route::get('venues', [AdminVenueController::class, 'index'])->name('venues.index');
        Route::post('venues', [AdminVenueController::class, 'store'])->name('venues.store');
        Route::get('operations', [AdminOperationsController::class, 'index'])->name('operations.index');
        Route::get('crew-management/resources', [AdminOperationsController::class, 'resources'])->name('crew-management.resources');
        Route::get('event-management/checklists', [AdminOperationsController::class, 'checklists'])->name('event-management.checklists');
        Route::post('operations/resources', [AdminOperationsController::class, 'storeResource'])->name('operations.resources.store');
        Route::get('operations/resources/{resource}/file', [InternalDocumentController::class, 'resource'])->name('operations.resources.file');
        Route::put('operations/resources/{resource}', [AdminOperationsController::class, 'updateResource'])->name('operations.resources.update');
        Route::post('operations/checklists', [AdminOperationsController::class, 'storeChecklist'])->name('operations.checklists.store');
        Route::put('operations/checklists/{template}', [AdminOperationsController::class, 'updateChecklist'])->name('operations.checklists.update');
        Route::put('scheduling-events/{schedulingEvent}/operations', [AdminOperationsController::class, 'updateEvent'])->name('scheduling-events.operations.update');
        Route::post('scheduling-events/{schedulingEvent}/messages', [AdminEventCommunicationController::class, 'store'])->name('scheduling-events.messages.store');
        Route::put('venues/{venue}/map', [AdminOperationsController::class, 'updateVenueMap'])->name('venues.map.update');
        Route::get('concert-bookings', [AdminConcertBookingController::class, 'index'])->name('concert-bookings.index');
        Route::patch('concert-booking-events/bulk-status', [AdminConcertBookingController::class, 'bulkUpdate'])->name('concert-booking-events.bulk-status');
        Route::post('concert-bookings/{concertBooking}/status-emails/{bookingStatusNotification}/refresh', [AdminBookingStatusEmailController::class, 'refresh'])->name('concert-bookings.status-email.refresh');
        Route::get('concert-bookings/{concertBooking}/status-emails/{bookingStatusNotification}', [AdminBookingStatusEmailController::class, 'show'])->name('concert-bookings.status-email');
        Route::post('concert-bookings/{concertBooking}/status-emails/{bookingStatusNotification}', [AdminBookingStatusEmailController::class, 'send'])->name('concert-bookings.status-email.send');
        Route::get('concert-bookings/{concertBooking}/confirmation', AdminBookingConfirmationController::class)->name('concert-bookings.confirmation');
        Route::post('concert-booking-events/{concertBookingItem}/seats', [AdminBookingEventMaterialsController::class, 'seats'])->name('concert-booking-events.seats');
        Route::post('concert-booking-events/{concertBookingItem}/files', [AdminBookingEventMaterialsController::class, 'files'])->name('concert-booking-events.files');
        Route::post('concert-booking-events/{concertBookingItem}/programme', [AdminConcertBookingController::class, 'storeProgramme'])->name('concert-booking-events.programme.store');
        Route::get('concert-booking-events/{concertBookingItem}/programme', [AdminConcertBookingController::class, 'programme'])->name('concert-booking-events.programme.show');
        Route::post('concert-booking-events/{concertBookingItem}/cancel', [AdminConcertBookingController::class, 'cancelItem'])->name('concert-booking-events.cancel');
        Route::patch('concert-tracker/{schedulingEvent}/seats-families-email/tracker', AdminSeatsFamiliesEmailTrackerController::class)->name('concert-tracker.seats-families-email.update');
        Route::patch('concert-tracker/{schedulingEvent}/email/{taskKey}/tracker', AdminSeatsFamiliesEmailTrackerController::class)->whereIn('taskKey', ConcertTrackerTaskCatalog::GUARDED_EMAIL_TASKS)->name('concert-tracker.email-bar.update');
        Route::patch('concert-bookings/{concertBooking}/numbers-tracker', AdminBookingNumbersTrackerController::class)->name('concert-bookings.numbers-tracker.update');
        Route::patch('concert-bookings/{concertBooking}/tracker', AdminBookingTrackerBarController::class)->name('concert-bookings.tracker.update');
        Route::get('concert-bookings/{concertBooking}', [AdminConcertBookingController::class, 'show'])->name('concert-bookings.show');
        Route::put('concert-bookings/{concertBooking}', [AdminConcertBookingController::class, 'update'])->name('concert-bookings.update');
        Route::post('concert-bookings/{concertBooking}/family-numbers', [BookingFamilyNumbersController::class, 'admin'])->name('concert-bookings.families');
        Route::post('concert-bookings/{concertBooking}/cancel', [AdminConcertBookingController::class, 'cancel'])->name('concert-bookings.cancel');
        Route::post('concert-bookings/{concertBooking}/create-studio', [AdminConcertBookingController::class, 'createStudio'])->name('concert-bookings.create-studio');
        Route::post('concert-bookings/{concertBooking}/reconcile-contact', [AdminConcertBookingController::class, 'reconcileContact'])->name('concert-bookings.reconcile-contact');
        Route::post('concert-bookings/{concertBooking}/approve', [AdminConcertBookingController::class, 'approve'])->name('concert-bookings.approve');
        Route::put('concert-booking-events/{concertBookingItem}/venue', [AdminConcertBookingController::class, 'resolveVenue'])->name('concert-booking-events.venue.update');
        Route::post('crew/{crewProfile}/contracts/{crewContract}/signature', [AdminCrewContractSignatureController::class, 'store'])
            ->name('crew.contract-signatures.store');
        Route::post('branding/studios/{studio}/cover', [AdminBrandingMediaController::class, 'uploadStudioCover'])->name('branding.studios.cover.store');
        Route::get('branding/studios/{studio}/cover/delete', [AdminBrandingMediaController::class, 'confirmStudioClear'])->name('branding.studios.cover.confirm-delete');
        Route::delete('branding/studios/{studio}/cover', [AdminBrandingMediaController::class, 'clearStudioCover'])->name('branding.studios.cover.destroy');
        Route::post('branding/concerts/{concert}/cover', [AdminBrandingMediaController::class, 'uploadConcertCover'])->name('branding.concerts.cover.store');
        Route::get('branding/concerts/{concert}/cover/delete', [AdminBrandingMediaController::class, 'confirmConcertCoverClear'])->name('branding.concerts.cover.confirm-delete');
        Route::delete('branding/concerts/{concert}/cover', [AdminBrandingMediaController::class, 'clearConcertCover'])->name('branding.concerts.cover.destroy');
        Route::post('branding/concerts/{concert}/program', [AdminBrandingMediaController::class, 'uploadProgram'])->name('branding.concerts.program.store');
        Route::get('branding/concerts/{concert}/program/delete', [AdminBrandingMediaController::class, 'confirmProgramClear'])->name('branding.concerts.program.confirm-delete');
        Route::delete('branding/concerts/{concert}/program', [AdminBrandingMediaController::class, 'clearProgram'])->name('branding.concerts.program.destroy');
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
        Route::post('competitions/{schedulingEvent}/objects/link-videos', [AdminCompetitionObjectController::class, 'syncVideos'])->name('competition.objects.videos.sync');
        Route::get('download-links', [AdminDownloadLinkController::class, 'index'])->name('download-links.index');
        Route::get('download-links/create', [AdminDownloadLinkController::class, 'create'])->name('download-links.create');
        Route::post('download-links', [AdminDownloadLinkController::class, 'store'])->name('download-links.store');
        Route::get('download-links/{downloadLink}', [AdminDownloadLinkController::class, 'show'])->name('download-links.show');
        Route::patch('download-links/{downloadLink}/revoke', [AdminDownloadLinkController::class, 'revoke'])->name('download-links.revoke');
    });

Route::middleware(['auth', 'crew.required', 'two-factor.required', 'crew.onboarded'])->prefix('crew')->name('crew.')->group(function (): void {
    Route::get('editing', [CrewConcertEditingController::class, 'index'])->name('editing.index');
    Route::get('editing/{schedulingEvent}', [CrewConcertEditingController::class, 'show'])->name('editing.show');
    Route::patch('editing/{schedulingEvent}', [CrewConcertEditingController::class, 'update'])->name('editing.update');
    Route::get('training', [CrewTrainingController::class, 'index'])->name('training.index');
    Route::get('training/{trainingCourse}', [CrewTrainingController::class, 'show'])->name('training.show');
    Route::post('training/{trainingCourse}/modules/{module}/complete', [CrewTrainingController::class, 'complete'])->name('training.modules.complete');
    Route::put('timesheets/invoice-style', [InvoiceStylePreferenceController::class, 'update'])->name('timesheets.invoice-style.update');
    Route::put('chat/{chatId}/messages/{message}/reaction', [MessageReactionController::class, 'update'])->name('chat.reaction.update');
    Route::get('timesheets', [CrewTimesheetController::class, 'index'])->name('timesheets.index');
    Route::get('timesheets/invoices/{invoice}', [CrewTimesheetController::class, 'invoice'])->name('timesheets.invoices.show');
    Route::get('timesheets/invoices/{invoice}/print', [CrewTimesheetController::class, 'printInvoice'])->name('timesheets.invoices.print');
    Route::post('timesheets/external-work-complete', [CrewTimesheetController::class, 'markExternalWorkComplete'])->name('timesheets.external-work-complete');
    Route::post('timesheets/invoices/preview', [CrewTimesheetController::class, 'previewInvoice'])->name('timesheets.invoices.preview');
    Route::post('timesheets/invoices/accept', [CrewTimesheetController::class, 'acceptInvoice'])->name('timesheets.invoices.accept');
    Route::get('chat', [CrewChatController::class, 'index'])->name('chat.index');
    Route::post('chat/direct', [CrewChatController::class, 'start'])->name('chat.start');
    Route::get('chat/events/{schedulingEvent}/programme-updates', ProgrammeChatUpdatesController::class)->middleware('throttle:30,1')->name('chat.programme-updates');
    Route::get('chat/events/{schedulingEvent}', [CrewChatController::class, 'event'])->name('chat.event');
    Route::post('chat/events/{schedulingEvent}', [CrewChatController::class, 'postEvent'])->name('chat.event.store');
    Route::get('chat/direct/{conversation}', [CrewChatController::class, 'direct'])->name('chat.direct');
    Route::post('chat/direct/{conversation}', [CrewChatController::class, 'postDirect'])->name('chat.direct.store');
    Route::get('chat/direct/{conversation}/messages/{message}/attachment', [CrewChatController::class, 'directAttachment'])->name('chat.direct.attachment');
    Route::get('directory', CrewDirectoryController::class)->name('directory.index');
    Route::get('availability', [CrewAvailabilityController::class, 'index'])->name('availability.index');
    Route::put('availability/{schedulingShift}', [CrewAvailabilityController::class, 'store'])->name('availability.store');
    Route::delete('availability/{schedulingShift}', [CrewAvailabilityController::class, 'clear'])->name('availability.clear');
    Route::patch('notifications/{crewNotification}/read', [CrewAvailabilityController::class, 'markNotificationRead'])->name('notifications.read');
    Route::post('assignments/{assignment}/acknowledge', [CrewAvailabilityController::class, 'acknowledge'])->name('assignments.acknowledge');
    Route::get('profile', [CrewProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [CrewProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [CrewProfileController::class, 'changePassword'])
        ->middleware('throttle:sensitive-auth')->name('profile.password');
    Route::get('contracts/{crewContract}', [CrewContractController::class, 'show'])->name('contracts.show');
    Route::post('contracts/{crewContract}/sign', [CrewContractController::class, 'sign'])
        ->middleware('throttle:sensitive-auth')->name('contracts.sign');
    Route::get('help', [CrewOperationsController::class, 'help'])->name('help.index');
    Route::get('help/resources/{resource}/file', [InternalDocumentController::class, 'resource'])->name('help.resources.file');
    Route::get('assignments/{assignment}', [CrewOperationsController::class, 'assignment'])->name('assignments.show');
    Route::put('assignments/{assignment}/checklist/{item}', [CrewOperationsController::class, 'toggle'])->name('assignments.checklist.toggle');
    Route::post('assignments/{assignment}/messages', [CrewEventCommunicationController::class, 'store'])->name('assignments.messages.store');
    Route::post('assignments/{assignment}/messages/{message}/acknowledge', [CrewEventCommunicationController::class, 'acknowledge'])->name('assignments.messages.acknowledge');
    Route::post('assignments/{assignment}/clock-in', [CrewTimeController::class, 'clockIn'])->name('assignments.time.clock-in');
    Route::post('assignments/{assignment}/finish', [CrewTimeController::class, 'finish'])->name('assignments.time.finish');
    Route::put('assignments/{assignment}/time', [CrewTimeController::class, 'update'])->name('assignments.time.update');
    Route::post('assignments/{assignment}/finish-team', [CrewTimeController::class, 'finishTeam'])->name('assignments.time.finish-team');
    Route::get('cover', [CrewCoverRequestController::class, 'index'])->name('cover.index');
    Route::get('assignments/{assignment}/cover', [CrewCoverRequestController::class, 'create'])->name('cover.create');
    Route::post('assignments/{assignment}/cover', [CrewCoverRequestController::class, 'store'])->name('cover.store');
    Route::post('cover/{coverRequest}/accept', [CrewCoverRequestController::class, 'accept'])->name('cover.accept');
});

Route::get('download/{token}', [PublicDownloadController::class, 'show'])
    ->middleware('throttle:public-download')
    ->name('downloads.public.show');

Route::get('tv', [ScreenController::class, 'player'])->name('publicity.player');

// Shared links and TV QR codes add gallery access after normal sign-in and onboarding.
Route::get('publicity/join/{schedulingEvent}', [PublicityJoinController::class, 'scan'])
    ->middleware(['signed:relative', 'throttle:30,1'])->name('publicity.join');
Route::get('my/publicity/join', [PublicityJoinController::class, 'complete'])
    ->middleware(['auth', 'two-factor.required', 'customer.onboarded', 'throttle:30,1'])->name('publicity.join.complete');

Route::get('publicity/studios/{studio}/join', [PublicityJoinController::class, 'studio'])
    ->middleware(['signed:relative', 'throttle:30,1'])->name('publicity.studio.join');

Route::middleware(['auth', 'two-factor.required', 'customer.onboarded'])->prefix('my/concert-review/{schedulingEvent}')->name('customer.concert-review.')->group(function (): void {
    Route::get('/', [DirectorReviewController::class, 'show'])->name('show');
    Route::get('videos/{file}', [DirectorReviewController::class, 'video'])->whereUuid('file')->name('video');
    Route::post('feedback', [DirectorReviewController::class, 'feedback'])->middleware('throttle:10,1')->name('feedback');
    Route::post('release', [DirectorReviewController::class, 'release'])->middleware('throttle:10,1')->name('release');
});
