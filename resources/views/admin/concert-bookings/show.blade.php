@extends('layouts.admin', ['title' => $booking->studio_name, 'heading' => $booking->studio_name, 'subheading' => 'Event booking details'])

@section('content')
<div class="booking-page-controls">
    <a class="button secondary" href="{{ route('admin.concert-bookings.index') }}">Event bookings</a>
    <div class="booking-page-actions">
        <span class="badge booking-status {{ $booking->status->value }}">{{ $booking->status->label() }}</span>
        @if($bookingTab === 'booking' && in_array($booking->status->value, ['pending', 'received', 'partial_approval']))
            @php($hasUnresolvedVenues = $booking->items->contains(fn ($item) => $item->approval_status === 'pending' && !$item->venue_id))
            <form method="POST" action="{{ route('admin.concert-bookings.approve', $booking) }}" data-approve-booking>
                @csrf
                @if($contactReview['studio'])<input type="hidden" name="studio_uuid" value="{{ $contactReview['studio']->uuid }}">@endif
                <button type="submit" title="{{ $hasUnresolvedVenues ? 'Resolve every proposed venue before approving this booking.' : 'Approve booking and create draft scheduling events.' }}" @disabled($hasUnresolvedVenues)>Approve booking</button>
            </form>
        @endif
    </div>
</div>
@if($bookingTab === 'booking' && in_array($booking->status->value, ['pending', 'received', 'partial_approval']) && ($hasUnresolvedVenues ?? false))
    <p class="card card-pad" role="status">Booking approval is unavailable until every proposed venue is resolved. Select an existing venue or resolve the proposed venue below, then save the booking.</p>
@endif
<nav class="booking-task-tabs" aria-label="Booking tasks">
    <a href="{{ route('admin.concert-bookings.show', $booking) }}" @if($bookingTab === 'booking') aria-current="page" @endif>1.1 Booking &amp; services</a>
    <a href="{{ route('admin.concert-bookings.show', [$booking, 'tab' => 'seats-families']) }}" @if($bookingTab === 'seats-families') aria-current="page" @endif>1.3 Seats &amp; families numbers</a>
    <a href="{{ route('admin.concert-bookings.show', [$booking, 'tab' => 'programme']) }}" @if($bookingTab === 'programme') aria-current="page" @endif>2.2 Prepare programme</a>
    <a href="{{ route('admin.concert-bookings.show', [$booking, 'tab' => 'video-titles']) }}" @if($bookingTab === 'video-titles') aria-current="page" @endif>2.3 Video titles</a>
    <a href="{{ route('admin.concert-bookings.show', [$booking, 'tab' => 'studio-media']) }}" @if($bookingTab === 'studio-media') aria-current="page" @endif>2.5 Studio videos & slideshows</a>
    <a href="{{ route('admin.concert-bookings.show', [$booking, 'tab' => 'invoice']) }}" @if($bookingTab === 'invoice') aria-current="page" @endif>4.2 Invoice &amp; payment</a>
</nav>
<section aria-label="{{ match ($bookingTab) { 'invoice' => '4.2 Invoice & payment', 'programme' => '2.2 Prepare programme', 'video-titles' => '2.3 Video titles', 'studio-media' => '2.5 Studio videos & slideshows', 'seats-families' => '1.3 Seats & families numbers', default => '1.1 Booking & services' } }}" class="booking-task-page">
@if($trackerBar) @include('admin.tracking._task-bar', ['bar' => $trackerBar]) @endif
@if($bookingTab === 'invoice')
    @include('admin.concert-bookings._invoice-workspace')
@elseif($bookingTab === 'programme')
    <div class="toolbar" style="margin:18px 0"><a class="button secondary" href="{{ route('admin.scheduling-events.index') }}">← Back to event availability</a></div>
    @if($programmeEvents->isNotEmpty())
        <form method="get" class="card card-pad" style="margin-bottom:18px">
            <input type="hidden" name="tab" value="programme">
            <label>Event<select name="event">@foreach($programmeEvents as $programmeEvent)<option value="{{ $programmeEvent->uuid }}" @selected($selectedProgrammeEvent->id === $programmeEvent->id)>{{ $programmeEvent->name }} · {{ $programmeEvent->event_date?->format('j M Y') }}</option>@endforeach</select></label><button>Open programme</button>
        </form>
    @endif
    @if($programmeWorkspace)
        @include('admin.programmes._editor', $programmeWorkspace)
    @elseif($programmeNotRequired ?? false)
        <p class="card card-pad">Programmes are not required for dress rehearsals.</p>
    @else
        <p class="card card-pad">Programme preparation is available once an event is approved.</p>
    @endif
@elseif($bookingTab === 'video-titles')
    @include('admin.concert-bookings._video-titles-workspace')
@elseif($bookingTab === 'studio-media')
    @include('admin.concert-bookings._studio-media-workspace')
@elseif($bookingTab === 'seats-families')
    @include('admin.concert-bookings._seats-families')
    @if($seatsAndFamilies->concerts($booking)->isEmpty())<p class="card card-pad">Seats and family numbers are not required for DR portraits.</p>@endif
@else
@include('admin.concert-bookings._change-review')
<form method="POST" action="{{ route('admin.concert-bookings.update', $booking) }}" class="booking-edit-form">
    @csrf @method('PUT')
    @if($changeRequest && in_array($changeRequest->status, ['requested', 'in_review']))
        <div class="card card-pad booking-section"><input type="hidden" name="booking_change_uuid" value="{{ $changeRequest->uuid }}"><label>Agreed resolution<textarea name="resolution_note" required rows="3">{{ old('resolution_note') }}</textarea></label><p class="muted">Apply the agreed details below. Saving will complete this request and record the actual changes. Services apply to every matching event in this booking. Existing crew assignments remain for review.</p></div>
    @endif
    <div class="booking-form-heading"><div><h2>Edit booking</h2><p class="muted">Update the submitted contact, services and event details below.</p></div><button type="submit">Save changes</button></div>
    <div class="card card-pad booking-section"><div class="booking-section-heading"><span class="booking-section-number">1</span><div><h2>Studio and primary contact</h2><p class="muted">The details supplied by the studio for this booking.</p></div></div>
        <div class="grid two-col"><label>Studio/company name<input name="studio_name" value="{{ old('studio_name', $booking->studio_name) }}" required></label><label>Contact name<input name="contact_name" value="{{ old('contact_name', $booking->contact_name) }}" required></label><label>Email<input type="email" name="contact_email" value="{{ old('contact_email', $booking->contact_email) }}" required></label><label>Phone<input name="contact_phone" value="{{ old('contact_phone', $booking->contact_phone) }}" required></label></div>
    </div>
    <div class="card card-pad booking-section"><div class="booking-section-heading"><span class="booking-section-number">2</span><div><h2>Services and delivery</h2><p class="muted">Choose the services included and keep the delivery information current.</p></div></div>
        <div class="service-choices">@foreach(['wants_portrait_photography'=>'Portrait photography','wants_concert_photography'=>'Concert photography','wants_concert_videography'=>'Concert videography'] as $field=>$label)<label class="choice"><input type="hidden" name="{{ $field }}" value="0"><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field,$booking->$field))><span>{{ $label }}</span></label>@endforeach</div>
        <div class="grid two-col"><label>Approximate families<input type="number" name="approximate_family_count" min="1" value="{{ old('approximate_family_count',$booking->approximate_family_count) }}"></label><label>Relationship between multiple concerts<input name="multiple_concert_relationship" value="{{ old('multiple_concert_relationship',$booking->multiple_concert_relationship) }}"></label><label>Postal address<textarea name="postal_address">{{ old('postal_address',$booking->postal_address) }}</textarea></label><label>Feedback and requests<textarea name="previous_video_feedback">{{ old('previous_video_feedback',$booking->previous_video_feedback) }}</textarea></label></div>
        <label class="choice"><input type="checkbox" name="concert_inclusions[]" value="presentations_awards" @checked(in_array('presentations_awards',old('concert_inclusions',$booking->concert_inclusions ?? [])))><span>Presentations / awards included</span></label><label class="choice"><input type="checkbox" name="concert_inclusions[]" value="tap_dance" @checked(in_array('tap_dance',old('concert_inclusions',$booking->concert_inclusions ?? [])))><span>Tap dance included</span></label>
    </div>
    <div class="card card-pad booking-section"><div class="booking-section-heading"><span class="booking-section-number">3</span><div><h2>Events</h2><p class="muted">Each concert or dress rehearsal contained in this booking.</p></div></div>
        @foreach($booking->items as $index=>$item)
            <div id="event-{{ $item->uuid }}" class="booking-edit-event {{ $item->approval_status === 'cancelled' ? 'cancelled' : '' }}"><div class="event-card-heading"><div><span class="event-number">Event {{ $index + 1 }}</span><strong>{{ $item->title ?: str($item->item_type->value)->replace('_',' ')->title() }}</strong></div><div class="event-card-actions">@if($item->approval_status === 'cancelled')<span class="badge cancelled">Cancelled</span>@else @if($item->programme_path)<details class="programme-menu"><summary class="programme-icon attached" title="Programme attached" aria-label="Programme attached">▤</summary><div><a href="{{ route('admin.concert-booking-events.programme.show', $item) }}" target="_blank">Open programme</a><button type="button" data-programme-picker="programme-{{ $item->uuid }}">Replace programme</button></div></details>@else<button class="programme-icon" type="button" title="Attach Programme" aria-label="Attach Programme" data-programme-picker="programme-{{ $item->uuid }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.5 12.5 14.8 6.2a3 3 0 0 1 4.2 4.2l-8.1 8.1a5 5 0 0 1-7.1-7.1l8.5-8.5M7.1 14l8.2-8.2"/></svg></button>@endif @if($item->schedulingEvent)<a href="{{ route('admin.scheduling-events.show',$item->schedulingEvent) }}">View scheduling event</a>@else<span class="muted">Not yet scheduled</span><button class="button danger event-cancel-button" type="submit" form="cancel-event-{{ $item->uuid }}">Cancel event</button>@endif @endif</div></div><input type="hidden" name="items[{{ $index }}][uuid]" value="{{ $item->uuid }}"><div class="grid"><label>Event type<select name="items[{{ $index }}][event_type_definition_id]">@foreach($eventTypes as $type)<option value="{{ $type->id }}" @selected((string)old("items.$index.event_type_definition_id", $item->event_type_definition_id)===(string)$type->id)>{{ $type->name }}</option>@endforeach</select></label><label>Event details<select name="items[{{ $index }}][item_type]"><option value="concert" @selected(old("items.$index.item_type", $item->item_type->value)==='concert')>Concert</option><option value="dress_rehearsal" @selected(old("items.$index.item_type", $item->item_type->value)==='dress_rehearsal')>DR Portrait</option></select></label><label>Title<input name="items[{{ $index }}][title]" value="{{ old("items.$index.title",$item->title) }}" placeholder="Optional"></label></div>
            @php($venueSelection=old("items.$index.venue_uuid", $item->venue?->uuid ?: 'other'))
            <div class="grid"><label>Venue<select name="items[{{ $index }}][venue_uuid]" class="edit-venue-select"><option value="other" @selected($venueSelection==='other')>Other venue</option>@foreach($venues as $venue)<option value="{{ $venue->uuid }}" @selected($venueSelection===$venue->uuid)>{{ $venue->name }}</option>@endforeach</select></label><label>Venue name<input name="items[{{ $index }}][venue_name]" value="{{ old("items.$index.venue_name",$item->venue_name) }}"></label><label>Venue address<input name="items[{{ $index }}][venue_address]" value="{{ old("items.$index.venue_address",$item->venue_address) }}"></label></div>
            <div class="grid"><label>Date<input type="date" name="items[{{ $index }}][event_date]" value="{{ old("items.$index.event_date",$item->event_date->toDateString()) }}" required></label><label>Start time<input type="time" name="items[{{ $index }}][starts_at]" value="{{ old("items.$index.starts_at",substr($item->starts_at,0,5)) }}" required></label><label>Finish time<input type="time" name="items[{{ $index }}][finishes_at]" value="{{ old("items.$index.finishes_at",substr($item->finishes_at,0,5)) }}" required></label></div>@if($item->approval_status !== 'cancelled' && $item->schedulingEvent)<label>Venue access / first crew arrival<input type="time" name="items[{{ $index }}][access_time]" value="{{ old("items.$index.access_time") }}"><small class="muted">Leave blank to preserve the current setup allowance. Current: {{ $item->schedulingEvent->shifts->sortBy("posted_arrival_at")->first()?->posted_arrival_at?->format("g:i a") ?: "Not set" }}.</small></label><div class="scheduled-event-cancellation"><span class="muted">Cancelling keeps this event in scheduling as a cancelled record and stops crew and timesheet activity.</span><button class="button danger event-cancel-button" type="submit" form="cancel-event-{{ $item->uuid }}">Cancel event</button></div>@endif</div>
        @endforeach
    </div>
    <details class="card booking-secondary"><summary><span><strong>Booking confirmations and internal note</strong><small>Requirements accepted by the studio and staff-only notes</small></span></summary><div class="card-pad"><label>Internal review note<textarea name="internal_review_note">{{ old('internal_review_note',$booking->internal_review_note) }}</textarea></label><h3>Accepted requirements</h3><div class="confirmation-grid">@foreach(['accurate'=>'Information is accurate','portrait_space'=>'Portrait space','no_personal_backdrop_photos'=>'No personal backdrop photos','minimum_spend'=>'Minimum spend','photographer_seat'=>'Photographer position','no_audience_recording'=>'No audience recording','promote_gallery'=>'Gallery promotion','credit_images'=>'Image credits','video_access'=>'Early video access','video_space_audio'=>'Video space and audio','programme_invoice'=>'Programme and invoice'] as $value=>$label)<label class="choice"><input type="checkbox" name="accepted_requirements[]" value="{{ $value }}" @checked(in_array($value,old('accepted_requirements',$booking->accepted_requirements ?? [])))><span>{{ $label }}</span></label>@endforeach</div></div></details>
    <div class="booking-save-bar"><span class="muted">Changes are not applied until saved.</span><button type="submit">{{ $changeRequest && in_array($changeRequest->status, ['requested', 'in_review']) ? 'Save changes and complete request' : 'Save booking changes' }}</button></div>
</form>

@if($currentQuote['video_concerts'])<p class="booking-section"><strong>Current video quote:</strong> {{ $currentQuote['video_quote_cents'] !== null ? '$'.number_format($currentQuote['video_quote_cents'] / 100, 2) : 'Awaiting family numbers' }} AUD including GST · {{ $currentQuote['families'] }} {{ $currentQuote['families_confirmed'] ? 'confirmed' : 'estimated' }} families.</p>@endif
@include('admin.concert-bookings._revision-history')
@if($booking->statusNotifications->isNotEmpty())
<section class="card card-pad" style="margin-top:18px"><h2>Booking notifications</h2>
@foreach($booking->statusNotifications as $notification)<p><a href="{{ route('admin.concert-bookings.status-email', [$booking, $notification]) }}">{{ $notification->subject }}</a> · {{ ucfirst($notification->delivery_status) }}@if($notification->sent_at) · {{ $notification->sent_at->format('j M Y, g:i a') }}@endif</p>@endforeach
</section>
@endif


@foreach($booking->items as $item)
    <form class="programme-upload-form" id="programme-{{ $item->uuid }}" method="POST" enctype="multipart/form-data" action="{{ route('admin.concert-booking-events.programme.store', $item) }}">
        @csrf
        <input type="file" name="programme" accept="application/pdf,.pdf" required data-programme-input>
    </form>
    @if($item->approval_status !== 'cancelled')
        <form id="cancel-event-{{ $item->uuid }}" method="POST" action="{{ route('admin.concert-booking-events.cancel', $item) }}" onsubmit="return confirm('Cancel only this event? The other events in this booking will remain active.')">@csrf</form>
    @endif
@endforeach

<details class="card booking-secondary contact-check">
    <summary><span><strong>Studio directory contact check</strong><small>Compare this booking against the saved studio record</small></span></summary><div class="card-pad">
    <div class="toolbar">
        <div><h2>Studio contact check</h2><p class="muted">Compare the submitted booking contact with the saved studio directory. The booking snapshot is always preserved.</p></div>
        @if($contactReview['studio'])<a class="button secondary" href="{{ route('admin.studios.edit', $contactReview['studio']) }}">View studio</a>@endif
    </div>

    <form method="GET" action="{{ route('admin.concert-bookings.show', $booking) }}" class="filters contact-match-form">
        <label>Matched studio<select name="studio_uuid"><option value="">Automatic name match</option>@foreach($studios as $studio)<option value="{{ $studio->uuid }}" @selected($contactReview['studio']?->uuid === $studio->uuid)>{{ $studio->name }}</option>@endforeach</select></label>
        <button type="submit" class="secondary">Check selected studio</button>
    </form>

    @if(!$contactReview['studio'])
        <div class="contact-warning"><strong>No matching studio record found.</strong> Select an existing studio above to compare it, or create the studio before reconciling this contact.</div>
        <details class="create-studio-panel" @if($errors->any()) open @endif>
            <summary>Create studio from this booking</summary>
            <form method="POST" action="{{ route('admin.concert-bookings.create-studio', $booking) }}" enctype="multipart/form-data" class="create-studio-form">
                @csrf
                @include('admin.studios._form', [
                    'studio' => null,
                    'statuses' => $studioStatuses,
                    'submitLabel' => 'Create studio and return to booking',
                    'cancelUrl' => route('admin.concert-bookings.show', $booking),
                    'defaults' => [
                        'name' => $booking->studio_name,
                        'status' => 'active',
                        'notes' => 'Created from concert booking '.$booking->uuid.'.',
                        'contacts' => [[
                            'name' => $booking->contact_name,
                            'role' => '',
                            'emails' => $booking->contact_email,
                            'phone' => $booking->contact_phone,
                        ]],
                    ],
                ])
            </form>
        </details>
    @elseif(!$contactReview['contact'])
        <div class="contact-warning"><strong>This person does not match a saved contact.</strong> The email address and then the name were checked against {{ $contactReview['studio']->name }}.</div>
    @elseif(!$contactReview['differences'])
        <div class="contact-match">
            <strong>Contact details match.</strong> {{ $contactReview['contact']->name }} is already saved
            @if($contactReview['contact']->role)
                as {{ $contactReview['contact']->role }}
            @endif
            .
        </div>
    @else
        <div class="contact-warning"><strong>{{ count($contactReview['differences']) }} {{ Str::plural('discrepancy', count($contactReview['differences'])) }} found.</strong> Select only the saved fields you want to replace.</div>
    @endif

    @if($contactReview['studio'])
        <form method="POST" action="{{ route('admin.concert-bookings.reconcile-contact', $booking) }}" class="contact-resolution">
            @csrf
            <input type="hidden" name="studio_uuid" value="{{ $contactReview['studio']->uuid }}">
            @if($contactReview['contact'])
                <div class="comparison-grid comparison-heading"><strong>Field</strong><strong>Submitted booking</strong><strong>Saved contact</strong><strong>Change?</strong></div>
                @foreach($contactReview['differences'] as $field => $difference)
                    <div class="comparison-grid">
                        <strong>{{ $difference['label'] }}</strong><span>{{ $difference['submitted'] }}</span><span>{{ $difference['stored'] }}</span><label class="choice compact-choice"><input type="checkbox" name="fields[]" value="{{ $field }}" checked><span>Update</span></label>
                    </div>
                @endforeach
                <div class="comparison-grid">
                    <strong>Role / position</strong><span>Not collected on booking form</span><span>{{ $contactReview['contact']->role ?: 'Not supplied' }}</span><label class="choice compact-choice"><input type="checkbox" name="fields[]" value="role"><span>Update</span></label>
                </div>
            @elseif(isset($contactReview['differences']['studio_name']))
                @php($difference = $contactReview['differences']['studio_name'])
                <div class="comparison-grid comparison-heading"><strong>Field</strong><strong>Submitted booking</strong><strong>Saved studio</strong><strong>Change?</strong></div>
                <div class="comparison-grid"><strong>{{ $difference['label'] }}</strong><span>{{ $difference['submitted'] }}</span><span>{{ $difference['stored'] }}</span><label class="choice compact-choice"><input type="checkbox" name="fields[]" value="studio_name"><span>Update</span></label></div>
            @endif
            <label>Role / position<input name="role" value="{{ old('role', $contactReview['contact']?->role) }}" maxlength="255" placeholder="e.g. Studio owner"></label>
            <div class="actions">
                @if($contactReview['contact'])<button type="submit" name="action" value="update">Update selected saved fields</button>@endif
                <button type="submit" name="action" value="add" class="secondary">Add as a new contact</button>
            </div>
        </form>
    @endif
</div></details>

@if($booking->status->value !== 'cancelled')<details class="card booking-danger"><summary>Cancel entire booking</summary><form method="POST" action="{{ route('admin.concert-bookings.cancel',$booking) }}" class="card-pad" onsubmit="return confirm('Cancel this entire booking and every remaining event? This cannot be undone.')">@csrf<h2>Cancel every event in this booking</h2><p class="muted">Use this only when the studio has cancelled the complete booking. To remove just one concert, use “Cancel event” on that event above.</p><label>Reason for cancellation<textarea name="reason" required maxlength="3000">{{ old('reason') }}</textarea></label><button class="danger" type="submit">Cancel and review email</button></form></details>@endif

@endif
</section>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
    const bookingForm = document.querySelector('.booking-edit-form');
    if (location.hash === '#studio-invoice' && !new URLSearchParams(location.search).has('tab')) { const url = new URL(location.href); url.searchParams.set('tab', 'invoice'); url.hash = ''; location.replace(url); }
    if (location.hash === '#seats-families' && !new URLSearchParams(location.search).has('tab')) { const url = new URL(location.href); url.searchParams.set('tab', 'seats-families'); url.hash = ''; location.replace(url); }
    const bookingValues = () => bookingForm ? JSON.stringify([...new FormData(bookingForm).entries()]) : '';
    const initialBookingValues = bookingValues();
    window.bookingHasUnsavedChanges = () => bookingValues() !== initialBookingValues;
    document.querySelectorAll('[data-approve-booking], .booking-materials form').forEach(form => {
        form.addEventListener('submit', event => {
            if (!window.bookingHasUnsavedChanges()) return;
            event.preventDefault();
            let message = form.querySelector('[data-unsaved-warning]');
            if (!message) {
                message = document.createElement('p');
                message.dataset.unsavedWarning = '';
                message.setAttribute('role', 'alert');
                message.style.color = '#b42331';
                form.append(message);
            }
            message.textContent = 'Save the booking changes before continuing.';
        });
    });
    document.querySelectorAll('[data-programme-picker]').forEach((button) => {
        button.addEventListener('click', () => document.querySelector(`#${CSS.escape(button.dataset.programmePicker)} [data-programme-input]`)?.click());
    });
    document.querySelectorAll('[data-programme-input]').forEach((input) => {
        input.addEventListener('change', () => { if (input.files.length) input.form.submit(); });
    });
</script>
@endpush

@push('styles')
<style>
    .booking-back-links{display:flex;flex-direction:column;align-items:flex-start;gap:8px}
    .booking-task-tabs{display:flex;flex-wrap:wrap;gap:6px;border-bottom:1px solid var(--line);margin-bottom:20px}.booking-task-tabs a{padding:14px 18px;font-weight:700;color:var(--muted);border:1px solid transparent;border-radius:8px 8px 0 0}.booking-task-tabs a[aria-current=page]{background:#fff;color:var(--brand-strong);border-color:var(--line);border-bottom-color:#fff;margin-bottom:-1px}
    .booking-page-controls { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:18px; }
    .booking-page-actions { display:flex; align-items:center; gap:10px; }
    .booking-status { padding:5px 10px; text-transform:capitalize; }
    .booking-status.received{background:#fff5df;color:#795400}.booking-status.approved,.booking-status.partial_approval{background:#e7f7ec;color:#126236}.booking-status.cancelled{background:#eef1f3;color:#576573}
    .booking-status.pending { border-color:#e8aaa4; background:#fff0ef; color:#a8241b; }
    .contact-tabs { flex-wrap:wrap; }
    .booking-follow-on { width:100%; min-width:0; }
    .booking-materials { width:100%; min-width:0; margin:24px 0; }
    .booking-materials details { margin-top:16px; padding-top:16px; border-top:1px solid var(--line); }
    .booking-materials form { display:grid; gap:12px; margin:16px 0; }
    .booking-materials textarea { min-height:76px; }
    .booking-materials input[type=file] { min-width:0; max-width:100%; }
    .booking-materials .material-file { padding:12px 0; overflow-wrap:anywhere; }
    .booking-materials summary { cursor:pointer; font-weight:700; }
    .booking-edit-form { display:grid; gap:18px; width:100%; min-width:0; }
    .booking-edit-form input[type="checkbox"],
    .booking-edit-form input[type="radio"],
    .contact-check input[type="checkbox"],
    .contact-check input[type="radio"] {
        width:18px;
        min-width:18px;
        max-width:18px;
        height:18px;
        min-height:18px;
        margin:2px 0 0;
        padding:0;
        flex:0 0 18px;
        accent-color:var(--brand);
    }
    .booking-edit-form .choice, .contact-check .choice {
        display:flex;
        align-items:flex-start;
        gap:10px;
    }
    .booking-section > label > select { max-width:420px; }
    .booking-form-heading { display:flex; align-items:center; justify-content:space-between; gap:20px; padding:4px 2px; }
    .booking-form-heading h2, .booking-form-heading p { margin:0; }
    .booking-section { margin:0; }
    .booking-section-heading { display:flex; align-items:flex-start; gap:12px; margin-bottom:20px; padding-bottom:14px; border-bottom:1px solid var(--line); }
    .booking-section-heading h2, .booking-section-heading p { margin:0; }
    .booking-section-number { display:grid; place-items:center; flex:0 0 30px; width:30px; height:30px; border-radius:50%; background:var(--soft); color:var(--brand-strong); font-weight:800; }
    .service-choices { display:flex; flex-wrap:wrap; gap:10px 22px; margin:14px 0 18px; padding:12px 14px; border-radius:7px; background:var(--paper); }
    .service-choices .choice { margin:0; }
    .booking-edit-event { margin-top:16px; padding:18px; border:1px solid var(--line); border-radius:8px; background:var(--paper); }
    .booking-edit-event.cancelled { border-color:#e8c7c4; background:#fff8f7; opacity:.78; }
    .booking-edit-event.cancelled input, .booking-edit-event.cancelled select { pointer-events:none; }
    .booking-edit-event:first-of-type { margin-top:0; }
    .event-card-heading { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:16px; }
    .event-card-heading > div:first-child { display:grid; }
    .event-card-actions { display:flex; align-items:center; gap:10px; }
    .event-cancel-button { min-height:34px; padding:6px 10px; }
    .badge.cancelled { border-color:#e8aaa4; background:#fff0ef; color:#a8241b; }
    .event-number { color:var(--brand-strong); font-size:11px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
    .booking-secondary, .booking-danger { width:100%; min-width:0; margin-top:18px; overflow:visible; }
    .booking-secondary > summary, .booking-danger > summary { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; list-style:none; cursor:pointer; }
    .booking-secondary > summary::-webkit-details-marker, .booking-danger > summary::-webkit-details-marker { display:none; }
    .booking-secondary > summary::after, .booking-danger > summary::after { content:'+'; color:var(--brand-strong); font-size:22px; }
    .booking-secondary[open] > summary::after, .booking-danger[open] > summary::after { content:'−'; }
    .booking-secondary > summary span { display:grid; }
    .booking-secondary > summary small { color:var(--muted); font-weight:400; }
    .booking-secondary[open] > .card-pad { border-top:1px solid var(--line); }
    .confirmation-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px 18px; }
    .booking-save-bar { position:sticky; bottom:12px; z-index:10; display:flex; align-items:center; justify-content:space-between; gap:16px; padding:12px 16px; border:1px solid #b9dce9; border-radius:8px; background:rgba(255,255,255,.96); box-shadow:0 10px 30px rgba(16,24,32,.12); backdrop-filter:blur(8px); }
    .booking-danger { border-color:#efc1bd; background:#fffafa; }
    .booking-danger > summary { color:var(--danger); font-weight:800; }
    .contact-check h2, .contact-check p { margin-top:0; }
    .contact-match-form { margin:16px 0; }
    .contact-warning, .contact-match { margin:14px 0; padding:12px 14px; border-radius:6px; }
    .contact-warning { border:1px solid #f0c36d; background:#fff8e6; color:#704d00; }
    .contact-match { border:1px solid #9fd5b0; background:#effaf2; color:#245b34; }
    .contact-resolution { display:grid; gap:14px; margin-top:16px; }
    .create-studio-panel { margin-top:16px; border-top:1px solid var(--line); padding-top:16px; }
    .create-studio-panel > summary { color:var(--brand-strong); font-weight:800; cursor:pointer; }
    .create-studio-form { margin-top:16px; }
    .comparison-grid { display:grid; grid-template-columns:minmax(110px,.7fr) minmax(180px,1.2fr) minmax(180px,1.2fr) minmax(90px,.5fr); gap:16px; align-items:center; padding:10px 0; border-top:1px solid var(--line); }
    .comparison-heading { border-top:0; color:var(--muted); font-size:12px; text-transform:uppercase; }
    .compact-choice { margin:0; }
    .programme-icon{display:inline-grid;place-items:center;width:34px;height:34px;min-height:34px;padding:0;border:1px solid #b9c8d0;border-radius:7px;background:#fff;color:#52636c;font-size:18px;line-height:1;cursor:pointer}.programme-icon svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.programme-icon:hover,.programme-icon.attached{border-color:#2478a8;color:#17658f;background:#edf7fc}.programme-menu{position:relative}.programme-menu summary{list-style:none}.programme-menu summary::-webkit-details-marker{display:none}.programme-menu>div{position:absolute;z-index:20;right:0;top:40px;min-width:165px;padding:6px;border:1px solid #c8d3d9;border-radius:8px;background:#fff;box-shadow:0 8px 24px rgba(16,32,42,.16)}.programme-menu a,.programme-menu button{display:block;width:100%;padding:8px 10px;border:0;border-radius:5px;background:transparent;color:#173847;text-align:left;text-decoration:none;font:inherit;cursor:pointer}.programme-menu a:hover,.programme-menu button:hover{background:#edf4f7}.programme-upload-form{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
    .booking-edit-form h2 { margin-top:0; }.booking-edit-form textarea{min-height:90px}
    @media (max-width:760px) { .comparison-heading { display:none; } .comparison-grid, .confirmation-grid { grid-template-columns:1fr; gap:5px; } .booking-form-heading,.booking-save-bar,.event-card-heading{align-items:stretch;flex-direction:column}.booking-save-bar button{width:100%}.booking-edit-event{padding:14px}.booking-page-controls{align-items:flex-start} }
</style>
@endpush
