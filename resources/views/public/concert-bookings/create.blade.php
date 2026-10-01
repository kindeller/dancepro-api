@extends('layouts.public', ['title' => 'Book DancePro for your concert'])
@section('headerParentUrl', $bookingStudio ? route('customer.studio-communications.index', $bookingStudio) : '')
@push('styles')
<style nonce="{{ request()->attributes->get('csp_nonce') }}">
    .booking-item { border-top:3px solid #91a9b5;padding-top:28px;margin-top:28px; }
    .booking-item:first-child { border-top:0;padding-top:0;margin-top:0; }
    .booking-event-details { overflow:visible; }
    .venue-search-field label { margin-bottom:7px; }
    .venue-picker { display:flex;align-items:flex-end;gap:12px;margin-bottom:10px; }
    .venue-search-field { position:relative;min-width:0;flex:1; }
    .venue-search-field input { margin-bottom:0; }
    .other-venue-button { flex-shrink:0;white-space:nowrap; }
    .other-venue-button[aria-pressed="true"] { background:#e1f4fb;border-color:#087fab;color:#075f84; }
    .venue-results { position:absolute;top:100%;left:0;right:0;z-index:20;max-height:280px;overflow:auto;background:#fff;border:1px solid #91a9b5;border-radius:8px;box-shadow:0 8px 24px #10182022; }
    .venue-results button { display:block;width:100%;padding:12px;border:0;border-bottom:1px solid var(--line);background:#fff;color:var(--ink);text-align:left;cursor:pointer;font:inherit; }
    .venue-results button:hover,.venue-results button[aria-selected="true"] { background:#e1f4fb; }
    .venue-results small { display:block;color:var(--muted);margin-top:4px; }
    .venue-results p { padding:12px;margin:0; }
    .selected-venue-address { margin-bottom:16px;font-size:14px; }
    @media(max-width:480px) { .venue-picker { gap:8px; }.other-venue-button { padding:12px 10px;font-size:13px; } }
    @media (min-width: 851px) {
        .booking-two-columns { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .booking-event-heading { grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); }
    }
</style>
@endpush
@section('content')
@php($draftData = $bookingDraft?->data ?? [])
<section class="hero"><div class="container"><div class="eyebrow">Concert booking request</div><h1>Tell us about your events.</h1><p class="lead">Add every concert and dress rehearsal that needs DancePro coverage. You can add as many performance rows as required.</p></div></section>
<section class="section"><div class="container">@if($errors->any())<div class="error"><strong>Please check the form.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if($bookingStudio)<p class="muted">Your studio and contact details are filled in below. Please check them and add the details for your new events.</p>@endif
@if(session('status'))<div class="card card-body" role="status" style="margin-bottom:20px">{{ session('status') }}</div>@endif
@if($bookingDraft)<p class="muted">Continuing your draft · Last saved {{ $bookingDraft->updated_at->format('j M Y, g:i a') }}. This booking has not been submitted.</p>@endif
<form method="POST" action="{{ route('concert-bookings.store') }}">@csrf
@if($bookingStudio)<input type="hidden" name="studio" value="{{ $bookingStudio->uuid }}"><input type="hidden" name="draft_uuid" value="{{ old('draft_uuid', $bookingDraft?->uuid) }}"><input type="hidden" name="draft_version" value="{{ old('draft_version', $bookingDraft?->version ?? 0) }}">@endif
    <div style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden" aria-hidden="true">
        <label for="website">Website</label>
        <input id="website" name="website" type="text" value="" tabindex="-1" autocomplete="off">
    </div>
<div class="card card-body"><h2>Studio details</h2><div class="grid booking-two-columns"><label>Studio/company name<input name="studio_name" value="{{ old('studio_name', data_get($draftData, 'studio_name', $prefill['studio_name'] ?? '')) }}" required></label><label>Owner/director name<input name="contact_name" value="{{ old('contact_name', data_get($draftData, 'contact_name', $prefill['contact_name'] ?? '')) }}" required></label><label>Email<input type="email" name="contact_email" value="{{ old('contact_email', data_get($draftData, 'contact_email', $prefill['contact_email'] ?? '')) }}" required></label><label>Phone number<input name="contact_phone" value="{{ old('contact_phone', data_get($draftData, 'contact_phone', $prefill['contact_phone'] ?? '')) }}" required></label></div><label>Postal address<textarea name="postal_address" id="postal-address" style="min-height:90px;width:100%">{{ old('postal_address', $draftData['postal_address'] ?? '') }}</textarea></label></div>
<div class="card card-body" style="margin-top:20px"><h2>Services</h2><p class="muted">Photography minimum spend: $800 AUD for the first photography event and $600 AUD for each additional event, including DR Portrait sessions. All amounts include GST.</p><label class="choice"><input type="hidden" name="wants_concert_photography" value="0"><input id="concert-photography" type="checkbox" name="wants_concert_photography" value="1" @checked(old('wants_concert_photography', $draftData['wants_concert_photography'] ?? true))><span>Concert photography</span></label><label class="choice"><input type="hidden" name="wants_concert_videography" value="0"><input id="concert-videography" type="checkbox" name="wants_concert_videography" value="1" @checked(old('wants_concert_videography', $draftData['wants_concert_videography'] ?? true))><span>Concert videography</span></label></div>
<div class="card card-body booking-event-details" style="margin-top:20px"><h2>Event details</h2><p class="muted">Use one row for each concert or DR portrait session. Select Other venue if the location is not listed.</p><div id="booking-items">@php($defaultEventType = $eventTypes->firstWhere('uuid', $selectedEventTypeUuid) ?? $eventTypes->firstWhere('code', 'concert') ?? $eventTypes->first())@php($rows = old('items', $draftData['items'] ?? [['item_type' => 'concert', 'event_type_definition_id' => $defaultEventType?->id]]))@foreach($rows as $index => $row)@include('public.concert-bookings._event-row', compact('index', 'row', 'venues', 'eventTypes'))@endforeach</div><template id="booking-item-template">@include('public.concert-bookings._event-row', ['index' => '__INDEX__', 'row' => ['item_type' => 'concert', 'event_type_definition_id' => $defaultEventType?->id], 'venues' => $venues, 'eventTypes' => $eventTypes])</template><button class="button secondary" type="button" id="add-booking-item" style="margin-top:18px">Add another event</button></div>
<div id="video-delivery" class="card card-body" style="margin-top:20px" @if(!old('wants_concert_videography', $draftData['wants_concert_videography'] ?? true)) hidden @endif><h2>Video delivery</h2><label>Approximate number of performing families across all concerts<input type="number" name="approximate_family_count" value="{{ old('approximate_family_count', $draftData['approximate_family_count'] ?? '') }}" min="1"></label><p class="muted">Count each family once across all concerts. A family includes siblings and parents performing in one or more concerts.</p></div>
<div class="card card-body" style="margin-top:20px"><h2>Booking requirements</h2><label>Feedback and requests (optional)<textarea name="previous_video_feedback" style="min-height:120px;width:100%" placeholder="Tell us what worked well or could be improved in previous photography, video or booking processes, and any requests for this year.">{{ old('previous_video_feedback', $draftData['previous_video_feedback'] ?? '') }}</textarea></label><p class="muted">Please read and confirm the requirements that apply to the services you selected.</p>
<div><h3>General booking confirmation</h3><label class="choice"><input type="checkbox" name="accepted_requirements[]" value="accurate" required @checked(in_array('accurate', old('accepted_requirements', $draftData['accepted_requirements'] ?? [])))><span>The information provided is accurate and changes will be sent to DancePro promptly.</span></label></div>
<div id="portrait-requirements" class="agreement-group" @if(!collect($rows)->contains('item_type', 'dress_rehearsal')) hidden @endif><h3>Dress rehearsal portrait photography</h3>@foreach(\App\Features\Bookings\Support\ConcertBookingRequirements::PORTRAIT as $value => $label)<label class="choice"><input type="checkbox" name="accepted_requirements[]" value="{{ $value }}" @checked(in_array($value, old('accepted_requirements', $draftData['accepted_requirements'] ?? [])))><span>{{ $label }}</span></label>@endforeach</div>
<div id="photography-requirements" class="agreement-group" @if(!old('wants_concert_photography', $draftData['wants_concert_photography'] ?? true)) hidden @endif><h3>Concert photography</h3>@foreach(\App\Features\Bookings\Support\ConcertBookingRequirements::PHOTO as $value => $label)<label class="choice"><input type="checkbox" name="accepted_requirements[]" value="{{ $value }}" @checked(in_array($value, old('accepted_requirements', $draftData['accepted_requirements'] ?? [])))><span>{{ $label }}</span></label>@endforeach</div>
<div id="videography-requirements" class="agreement-group" @if(!old('wants_concert_videography', $draftData['wants_concert_videography'] ?? true)) hidden @endif><h3>Concert videography</h3>@foreach(\App\Features\Bookings\Support\ConcertBookingRequirements::VIDEO as $value => $label)<label class="choice"><input type="checkbox" name="accepted_requirements[]" value="{{ $value }}" @checked(in_array($value, old('accepted_requirements', $draftData['accepted_requirements'] ?? [])))><span>{{ $label }}</span></label>@endforeach</div>
</div>
<div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:20px"><button class="button" type="submit">Submit booking request</button>@if($bookingStudio)<button class="button secondary" type="submit" formnovalidate formaction="{{ route('customer.studio-bookings.draft', $bookingStudio) }}">Save draft</button>@endif</div></form></div></section>
@endsection
@push('scripts')<script nonce="{{ request()->attributes->get('csp_nonce') }}">
@include('public.concert-bookings._venue-search-script')
document.querySelectorAll('.booking-item').forEach(configureVenueRow);
document.getElementById('add-booking-item').addEventListener('click',()=>{const box=document.getElementById('booking-items');const index=box.querySelectorAll('.booking-item').length;box.insertAdjacentHTML('beforeend',document.getElementById('booking-item-template').innerHTML.replaceAll('__INDEX__',index));configureVenueRow(box.lastElementChild);updateConditionalSections();});
const photography=document.getElementById('concert-photography');const videography=document.getElementById('concert-videography');
function toggleSection(section,visible){section.hidden=!visible;section.querySelectorAll('input[type="checkbox"]').forEach(input=>{input.required=visible;if(!visible)input.checked=false;});}
function updateConditionalSections(){
    const hasPortrait=Array.from(document.querySelectorAll('#booking-items .event-type-select')).some(select=>select.value==='dress_rehearsal');
    toggleSection(document.getElementById('portrait-requirements'),hasPortrait);
    toggleSection(document.getElementById('photography-requirements'),photography.checked);
    toggleSection(document.getElementById('videography-requirements'),videography.checked);
    const videoDelivery=document.getElementById('video-delivery');
    videoDelivery.hidden=!videography.checked;
    videoDelivery.querySelectorAll('input').forEach(field=>{field.disabled=!videography.checked;field.required=videography.checked;});
    document.getElementById('postal-address').required=videography.checked;
}
document.getElementById('booking-items').addEventListener('change',event=>{if(event.target.matches('.event-type-select'))updateConditionalSections();});
[photography,videography].forEach(control=>control.addEventListener('change',updateConditionalSections));updateConditionalSections();
</script>@endpush
