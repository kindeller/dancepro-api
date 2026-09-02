@extends('layouts.admin', ['title' => $booking->studio_name, 'heading' => $booking->studio_name, 'subheading' => 'Concert booking review'])

@section('content')
@include('admin.event-management._tabs')
<div class="grid two-col"><div class="card card-pad"><h2>Contact</h2><dl class="detail-list"><dt>Name</dt><dd>{{ $booking->contact_name }}</dd><dt>Email</dt><dd>{{ $booking->contact_email }}</dd><dt>Phone</dt><dd>{{ $booking->contact_phone }}</dd><dt>Status</dt><dd><span class="badge">{{ $booking->status->value }}</span></dd></dl></div><div class="card card-pad"><h2>Services</h2><p>Portrait photography: {{ $booking->wants_portrait_photography ? 'Yes' : 'No' }}</p><p>Concert photography: {{ $booking->wants_concert_photography ? 'Yes' : 'No' }}</p><p>Concert videography: {{ $booking->wants_concert_videography ? 'Yes' : 'No' }}</p><p>Approximate families: {{ $booking->approximate_family_count ?: 'Not supplied' }}</p></div></div>

<section class="card card-pad contact-check" style="margin-top:20px">
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
</section>

<div class="card card-pad" style="margin-top:20px"><h2>Events supplied</h2><table><thead><tr><th>Type</th><th>Title</th><th>Date and time</th><th>Venue</th><th>Scheduling event</th></tr></thead><tbody>
@foreach($booking->items as $item)
<tr>
    <td><strong>{{ $item->eventTypeDefinition?->name ?: 'Concert' }}</strong><div class="muted">{{ str($item->item_type->value)->replace('_', ' ')->title() }}</div></td>
    <td>{{ $item->title ?: 'Dress rehearsal' }}</td>
    <td>{{ $item->event_date->format('j M Y') }}<div class="muted">{{ $item->starts_at }}–{{ $item->finishes_at }}</div></td>
    <td>
        <strong>{{ $item->venue_name }}</strong><div class="muted">{{ $item->venue_address }}</div>
        @if($item->venue)
            <p><span class="badge">Resolved: {{ $item->venue->name }}</span></p>
        @elseif($item->approval_status === 'pending')
            <div class="error" style="margin-top:8px">This venue must be resolved before approval.</div>
            <form method="POST" action="{{ route('admin.concert-booking-events.venue.update', $item) }}" class="grid" style="margin-top:8px">
                @csrf @method('PUT')
                <label>Match an existing venue<select name="venue_uuid"><option value="">Select venue</option>@foreach($venues as $venue)<option value="{{ $venue->uuid }}">{{ $venue->name }}</option>@endforeach</select></label>
                <div class="toolbar">
                    <button type="submit" name="resolution_action" value="match">Use selected venue</button>
                    <button class="secondary" type="submit" name="resolution_action" value="create">Create “{{ $item->venue_name }}”</button>
                </div>
            </form>
        @endif
    </td>
    <td>@if($item->schedulingEvent)<a href="{{ route('admin.scheduling-events.show', $item->schedulingEvent) }}">View draft event</a>@else Not created @endif</td>
</tr>
@endforeach
</tbody></table></div>

@if($booking->status->value === 'pending')
<form method="POST" action="{{ route('admin.concert-bookings.approve', $booking) }}" class="card card-pad" style="margin-top:20px">
    @csrf
    @if($contactReview['studio'])<input type="hidden" name="studio_uuid" value="{{ $contactReview['studio']->uuid }}">@endif
    <h2>Approve booking</h2>
    @if($booking->items->contains(fn ($item) => $item->approval_status === 'pending' && !$item->venue_id))
        <div class="error">Resolve every proposed venue above before approving this booking.</div>
    @else
        <p class="muted">This creates one draft scheduling event per supplied concert or rehearsal. Crew will not see them yet.</p>
    @endif
    <label>Internal review note<textarea name="internal_review_note" style="min-height:100px">{{ old('internal_review_note') }}</textarea></label>
    <button @disabled($booking->items->contains(fn ($item) => $item->approval_status === 'pending' && !$item->venue_id))>Approve and create draft events</button>
</form>
@endif
@endsection

@push('styles')
<style>
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
    @media (max-width:760px) { .comparison-heading { display:none; } .comparison-grid { grid-template-columns:1fr; gap:5px; } }
</style>
@endpush
