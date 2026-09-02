<?php

use App\Features\Admin\Controllers\AdminConcertController;
use App\Features\Admin\Controllers\AdminDashboardController;
use App\Features\Admin\Controllers\AdminDownloadLinkController;
use App\Features\Admin\Controllers\AdminStudioController;
use App\Features\Auth\Controllers\PasswordResetController;
use App\Features\Auth\Controllers\TwoFactorController;
use App\Features\Auth\Controllers\WebAuthController;
use App\Features\Bookings\Controllers\AdminConcertBookingController;
use App\Features\Bookings\Controllers\PublicConcertBookingController;
use App\Features\Chat\Controllers\CrewChatController;
use App\Features\Competition\Controllers\AdminCompetitionObjectController;
use App\Features\CompetitionContacts\Controllers\AdminCompetitionContactController;
use App\Features\Concerts\Controllers\PublicConcertController;
use App\Features\Concerts\Controllers\PublicSlugRedirectController;
use App\Features\Concerts\Controllers\PublicStudioController;
use App\Features\Crew\Controllers\AdminCrewContractController;
use App\Features\Crew\Controllers\AdminCrewContractSignatureController;
use App\Features\Crew\Controllers\AdminCrewManagementController;
use App\Features\Crew\Controllers\AdminCrewProfileController;
use App\Features\Crew\Controllers\AdminCrewRoleController;
use App\Features\Crew\Controllers\CrewContractController;
use App\Features\Crew\Controllers\CrewDirectoryController;
use App\Features\Crew\Controllers\CrewProfileController;
use App\Features\Downloads\Controllers\PublicDownloadController;
use App\Features\Exceptions\Controllers\AdminExceptionController;
use App\Features\Operations\Controllers\AdminEventCommunicationController;
use App\Features\Operations\Controllers\AdminHubDashboardController;
use App\Features\Operations\Controllers\AdminOperationsController;
use App\Features\Operations\Controllers\CrewEventCommunicationController;
use App\Features\Operations\Controllers\CrewOperationsController;
use App\Features\Operations\Controllers\InternalDocumentController;
use App\Features\Scheduling\Controllers\AdminEventTypeController;
use App\Features\Scheduling\Controllers\AdminPaymentController;
use App\Features\Scheduling\Controllers\AdminSchedulingEventController;
use App\Features\Scheduling\Controllers\AdminTimeController;
use App\Features\Scheduling\Controllers\CrewAvailabilityController;
use App\Features\Scheduling\Controllers\CrewCoverRequestController;
use App\Features\Scheduling\Controllers\CrewTimeController;
use App\Features\Timesheets\Controllers\AdminTimesheetController;
use App\Features\Timesheets\Controllers\CrewTimesheetController;
use App\Features\Training\Controllers\AdminTrainingController;
use App\Features\Training\Controllers\CrewTrainingController;
use App\Features\Venues\Controllers\AdminVenueController;
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
    Route::get('forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::post('logout', [WebAuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('guest')->group(function (): void {
    Route::get('two-factor-challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('two-factor-challenge', [TwoFactorController::class, 'verifyChallenge'])->middleware('throttle:6,1')->name('two-factor.verify');
});
Route::middleware('auth')->group(function (): void {
    Route::get('account/security', [TwoFactorController::class, 'settings'])->name('account.security');
    Route::post('account/security/two-factor', [TwoFactorController::class, 'begin'])->name('two-factor.begin');
    Route::post('account/security/two-factor/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:6,1')->name('two-factor.confirm');
    Route::post('account/security/two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('two-factor.recovery-codes');
    Route::delete('account/security/two-factor', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
});

Route::get('book-your-concert', [PublicConcertBookingController::class, 'create'])->name('concert-bookings.create');
Route::post('book-your-concert', [PublicConcertBookingController::class, 'store'])->name('concert-bookings.store');
Route::get('book-your-concert/thanks', [PublicConcertBookingController::class, 'thanks'])->name('concert-bookings.thanks');

Route::middleware(['auth', 'two-factor.required'])->prefix('internal-documents')->name('internal-documents.')->group(function (): void {
    Route::get('resources/{resource}', [InternalDocumentController::class, 'resource'])->name('resources.show');
    Route::get('venues/{venue}/map', [InternalDocumentController::class, 'venueMap'])->name('venues.map');
    Route::get('events/{schedulingEvent}/programme', [InternalDocumentController::class, 'programme'])->name('events.programme');
    Route::get('messages/{message}/attachment', [InternalDocumentController::class, 'messageAttachment'])->name('messages.attachment');
});

Route::middleware(['auth', 'admin.required', 'two-factor.required'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::get('hub', AdminHubDashboardController::class)->name('hub.dashboard');
        Route::get('exceptions', AdminExceptionController::class)->name('exceptions.index');
        Route::patch('studios/{studio}/status', [AdminStudioController::class, 'updateStatus'])->name('studios.status.update');
        Route::resource('studios', AdminStudioController::class)->except(['show', 'destroy']);
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
        Route::patch('scheduling-events/{schedulingEvent}/availability', [AdminSchedulingEventController::class, 'updateAvailability'])->name('scheduling-events.availability');
        Route::put('scheduling-shifts/{schedulingShift}/roles/{crewRole}/assignment', [AdminSchedulingEventController::class, 'updateCrewAssignment'])->name('scheduling-shifts.roles.assignment');
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
        Route::get('concert-bookings/{concertBooking}', [AdminConcertBookingController::class, 'show'])->name('concert-bookings.show');
        Route::post('concert-bookings/{concertBooking}/create-studio', [AdminConcertBookingController::class, 'createStudio'])->name('concert-bookings.create-studio');
        Route::post('concert-bookings/{concertBooking}/reconcile-contact', [AdminConcertBookingController::class, 'reconcileContact'])->name('concert-bookings.reconcile-contact');
        Route::post('concert-bookings/{concertBooking}/approve', [AdminConcertBookingController::class, 'approve'])->name('concert-bookings.approve');
        Route::put('concert-booking-events/{concertBookingItem}/venue', [AdminConcertBookingController::class, 'resolveVenue'])->name('concert-booking-events.venue.update');
        Route::post('crew/{crewProfile}/contracts/{crewContract}/signature', [AdminCrewContractSignatureController::class, 'store'])
            ->name('crew.contract-signatures.store');
        Route::get('competitions/objects', [AdminCompetitionObjectController::class, 'index'])->name('competition.objects.index');
        Route::get('competitions/objects/chunk', [AdminCompetitionObjectController::class, 'chunk'])->name('competition.objects.chunk');
        Route::get('download-links', [AdminDownloadLinkController::class, 'index'])->name('download-links.index');
        Route::get('download-links/create', [AdminDownloadLinkController::class, 'create'])->name('download-links.create');
        Route::post('download-links', [AdminDownloadLinkController::class, 'store'])->name('download-links.store');
        Route::get('download-links/{downloadLink}', [AdminDownloadLinkController::class, 'show'])->name('download-links.show');
        Route::patch('download-links/{downloadLink}/revoke', [AdminDownloadLinkController::class, 'revoke'])->name('download-links.revoke');
    });

Route::middleware(['auth', 'crew.required', 'two-factor.required', 'crew.onboarded'])->prefix('crew')->name('crew.')->group(function (): void {
    Route::get('training', [CrewTrainingController::class, 'index'])->name('training.index');
    Route::get('training/{trainingCourse}', [CrewTrainingController::class, 'show'])->name('training.show');
    Route::post('training/{trainingCourse}/modules/{module}/complete', [CrewTrainingController::class, 'complete'])->name('training.modules.complete');
    Route::get('timesheets', [CrewTimesheetController::class, 'index'])->name('timesheets.index');
    Route::get('timesheets/invoices/{invoice}', [CrewTimesheetController::class, 'invoice'])->name('timesheets.invoices.show');
    Route::get('timesheets/invoices/{invoice}/print', [CrewTimesheetController::class, 'printInvoice'])->name('timesheets.invoices.print');
    Route::post('timesheets/external-work-complete', [CrewTimesheetController::class, 'markExternalWorkComplete'])->name('timesheets.external-work-complete');
    Route::post('timesheets/invoices/preview', [CrewTimesheetController::class, 'previewInvoice'])->name('timesheets.invoices.preview');
    Route::post('timesheets/invoices/accept', [CrewTimesheetController::class, 'acceptInvoice'])->name('timesheets.invoices.accept');
    Route::get('chat', [CrewChatController::class, 'index'])->name('chat.index');
    Route::post('chat/direct', [CrewChatController::class, 'start'])->name('chat.start');
    Route::get('chat/events/{schedulingEvent}', [CrewChatController::class, 'event'])->name('chat.event');
    Route::post('chat/events/{schedulingEvent}', [CrewChatController::class, 'postEvent'])->name('chat.event.store');
    Route::get('chat/direct/{conversation}', [CrewChatController::class, 'direct'])->name('chat.direct');
    Route::post('chat/direct/{conversation}', [CrewChatController::class, 'postDirect'])->name('chat.direct.store');
    Route::get('directory', CrewDirectoryController::class)->name('directory.index');
    Route::get('availability', [CrewAvailabilityController::class, 'index'])->name('availability.index');
    Route::put('availability/{schedulingShift}', [CrewAvailabilityController::class, 'store'])->name('availability.store');
    Route::patch('notifications/{crewNotification}/read', [CrewAvailabilityController::class, 'markNotificationRead'])->name('notifications.read');
    Route::post('assignments/{assignment}/acknowledge', [CrewAvailabilityController::class, 'acknowledge'])->name('assignments.acknowledge');
    Route::get('profile', [CrewProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [CrewProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [CrewProfileController::class, 'changePassword'])->name('profile.password');
    Route::get('contracts/{crewContract}', [CrewContractController::class, 'show'])->name('contracts.show');
    Route::post('contracts/{crewContract}/sign', [CrewContractController::class, 'sign'])->name('contracts.sign');
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
    ->name('downloads.public.show');
