<div class="toolbar" style="margin:18px 0"><a class="button secondary" href="{{ route('admin.scheduling-events.index') }}">← Back to event availability</a></div>
@if(session('status'))<p role="status" class="card card-pad">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert" class="card card-pad" style="color:#b42331">{{ $errors->first() }}</p>@endif
@if($studioMediaWorkspace)
    <form method="get" class="card card-pad" style="margin-bottom:18px">
        <input type="hidden" name="tab" value="studio-media">
        <label>Event<select name="event">@foreach($studioMediaEvents as $mediaEvent)<option value="{{ $mediaEvent->uuid }}" @selected($studioMediaWorkspace['event']->id === $mediaEvent->id)>{{ $mediaEvent->name }} · {{ $mediaEvent->event_date?->format('j M Y') }}</option>@endforeach</select></label><button>Open studio media</button>
    </form>
    <section class="card card-pad booking-materials">
        <p>Review videos and slideshows supplied through the Studio Hub, or upload files received directly. Approving every current file completes this task. Replacements are retained in file history and need a new review.</p>
        @include('admin.concert-bookings._studio-media-files', $studioMediaWorkspace)
    </section>
    <details class="card card-pad" style="margin-top:18px"><summary>Request studio videos or slideshows by email</summary>
        @include('admin.tracking._email-action', $studioMediaWorkspace)
    </details>
@else
    <p class="card card-pad">Studio media can be uploaded and reviewed once an event is approved.</p>
@endif
