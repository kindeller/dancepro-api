@extends('layouts.public', ['title' => $studio->name.' emails', 'footerLabel' => 'Studio communications'])
@section('content')
<section class="hero"><div class="container"><div class="eyebrow">Studio account</div><h1>{{ $studio->name }} emails</h1><p class="lead">DancePro concert messages and replies for your studio, kept together.</p></div></section>
<section class="section"><div class="container">
    <p><a href="{{ route('customer.profile.edit') }}#studios">← My Profile</a></p>
    @if(session('status'))<p class="card card-body" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
    <section class="card card-body" style="margin-bottom:24px"><h2>Event details</h2><p class="meta">Add the reserved auditorium seats DancePro can use for each role. Dress rehearsal portraits do not need seating.</p>
        @foreach($events as $event)
            @php
                $seatedRoles = $event->roleRequirements->filter(fn($requirement) => str_contains($requirement->crewRole->code, 'concert-photographer') || str_contains($requirement->crewRole->code, 'videographer'));
                $savedSeats = $event->roleSeats->keyBy('crew_role_id');
            @endphp
            @if($seatedRoles->isNotEmpty())
                <details class="card" style="margin-top:12px"><summary class="card-body" style="cursor:pointer"><strong>{{ $event->concertBookingItem?->title ?: $event->name }}</strong> <span class="meta">· {{ $event->event_date?->format('j M Y') }}</span></summary>
                    <form class="card-body" style="border-top:1px solid var(--line)" method="post" action="{{ route('customer.studio-events.seats.store', [$studio, $event]) }}">@csrf
                        @foreach($seatedRoles as $requirement)
                            @php
                                $role = $requirement->crewRole; $limit = str_contains($role->code, 'videographer') ? 6 : 2;
                                $minimum = str_contains($role->code, 'videographer') ? 5 : 1;
                                $positions = collect(old("roles.{$role->code}", $savedSeats->get($role->id)?->seats ?? []));
                            @endphp
                            <fieldset style="border:0;padding:0;margin:0 0 18px"><legend><strong>{{ $role->name }}</strong> <span class="meta">({{ $minimum }}–{{ $limit }} {{ Str::plural('seat', $limit) }})</span></legend>
                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:10px">
                                    @for($position = 0; $position < $limit; $position++)
                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                                            <label>Row<input name="roles[{{ $role->code }}][{{ $position }}][row]" maxlength="30" value="{{ $positions->get($position)['row'] ?? '' }}" inputmode="text"></label>
                                            <label>Seat<input name="roles[{{ $role->code }}][{{ $position }}][seat]" maxlength="30" value="{{ $positions->get($position)['seat'] ?? '' }}" inputmode="text"></label>
                                        </div>
                                    @endfor
                                </div>
                            </fieldset>
                        @endforeach
                        <button class="button">Save seating details</button>
                    </form>
                    <div class="card-body" style="border-top:1px solid var(--line)">
                        <h3>Programme and studio videos</h3>
                        <div class="grid two-col">
                            <form class="studio-file-upload" method="post" action="{{ route('customer.studio-events.files.store', [$studio, $event]) }}" enctype="multipart/form-data">@csrf<input type="hidden" name="kind" value="programme">
                                <label><strong>Programme</strong> <span class="meta">PDF preferred; other formats are accepted</span><input type="file" name="files[]" required></label>
                                <button class="button secondary">Upload programme</button><progress value="0" max="100" hidden style="width:100%;margin-top:8px"></progress><span class="meta upload-status"></span>
                            </form>
                            <form class="studio-file-upload" method="post" action="{{ route('customer.studio-events.files.store', [$studio, $event]) }}" enctype="multipart/form-data">@csrf<input type="hidden" name="kind" value="studio_video">
                                <label><strong>Videos and slideshows</strong> <span class="meta">Choose up to 20 files; you can add more later</span><input type="file" name="files[]" accept="video/*,.ppt,.pptx,.key,.pdf" multiple required></label>
                                <button class="button secondary">Upload studio files</button><progress value="0" max="100" hidden style="width:100%;margin-top:8px"></progress><span class="meta upload-status"></span>
                            </form>
                        </div>
                        @if($event->studioFiles->isNotEmpty())<div style="margin-top:16px"><strong>Submitted files</strong>@foreach($event->studioFiles->where('is_current', true) as $file)<p style="margin:8px 0 0"><a href="{{ route('studio-event-files.download', $file) }}">{{ $file->kind === 'programme' ? '📄' : '🎬' }} {{ $file->original_name }}</a> <span class="badge">{{ str($file->status)->replace('_', ' ')->title() }}</span><br><span class="meta">{{ number_format($file->size_bytes / 1048576, 1) }} MB · {{ $file->uploaded_at->format('j M Y, g:i a') }}</span></p>@endforeach</div>@endif
                    </div>
                </details>
            @endif
        @endforeach
    </section>
    <section class="card card-body" style="margin-bottom:24px"><h2>Reply to DancePro</h2><form method="post" action="{{ route('customer.studio-communications.store', $studio) }}" enctype="multipart/form-data">@csrf
        <label>Concert <span class="meta">(optional)</span><select name="event_uuid"><option value="">General studio message</option>@foreach($events as $event)<option value="{{ $event->uuid }}">{{ $event->name }} · {{ $event->event_date?->format('j M Y') }}</option>@endforeach</select></label>
        <label>Subject <span class="meta">(optional)</span><input name="subject" maxlength="255" value="{{ old('subject') }}"></label>
        <x-email-composer :value="old('body', '')" :rows="6" />
        <label>Attachment <span class="meta">(optional, up to 10 MB)</span><input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt"></label>
        <button class="button">Send reply</button>
    </form></section>
    @forelse($communications as $message)
        <details class="card" style="margin-bottom:14px">
            <summary class="card-body" style="cursor:pointer;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center">
                <span><strong>{{ $message['subject'] }}</strong><br><span class="meta">{{ $message['event'] ?: 'Studio communication' }} · {{ $message['direction'] === 'from_dancepro' ? 'From DancePro' : 'Sent to DancePro' }}</span></span>
                <span class="meta">{{ $message['occurred_at']?->format('j M Y, g:i a') }}</span>
            </summary>
            <div class="card-body" style="border-top:1px solid var(--line)">
                <p class="meta">{{ ucfirst($message['status']) }} · {{ $message['contact'] }}</p>
                <div>{!! app(\App\Features\Tracking\Services\EmailContentFormatter::class)->html($message['body']) !!}</div>@foreach($message['attachments'] as $attachment)<p><a href="{{ route('customer.studio-communications.attachments.show', [$studio, $attachment]) }}">📎 {{ $attachment->original_name }}</a> <span class="meta">({{ number_format($attachment->size_bytes / 1024) }} KB)</span></p>@endforeach
            </div>
        </details>
    @empty
        <div class="empty"><h2>No studio emails yet</h2><p>Concert messages sent through DancePro will appear here.</p></div>
    @endforelse
</div></section>
<script>
document.querySelectorAll('.studio-file-upload').forEach(form => form.addEventListener('submit', event => {
    event.preventDefault();
    const button = form.querySelector('button');
    const progress = form.querySelector('progress');
    const status = form.querySelector('.upload-status');
    button.disabled = true; progress.hidden = false; status.textContent = ' Uploading…';
    const request = new XMLHttpRequest();
    request.open('POST', form.action);
    request.setRequestHeader('Accept', 'application/json');
    request.upload.addEventListener('progress', upload => {
        if (upload.lengthComputable) { progress.value = Math.round(upload.loaded / upload.total * 100); status.textContent = ` ${progress.value}% uploaded`; }
    });
    request.addEventListener('load', () => {
        if (request.status >= 200 && request.status < 300) { status.textContent = ' Upload complete'; window.location.reload(); return; }
        let message = 'Upload failed. Please try again.';
        try { const response = JSON.parse(request.responseText); message = response.message || Object.values(response.errors || {}).flat()[0] || message; } catch (_) {}
        status.textContent = ` ${message}`; button.disabled = false;
    });
    request.addEventListener('error', () => { status.textContent = ' Upload interrupted. Please try again.'; button.disabled = false; });
    request.send(new FormData(form));
}));
</script>
@endsection
