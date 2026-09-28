@if($changeRequests->isNotEmpty())
<section class="card card-pad booking-section">
    <h2>Studio change requests</h2>
    @foreach($changeRequests as $request)
        <article style="padding:12px 0;border-top:1px solid var(--line)">
            <div class="toolbar"><strong>{{ str($request->status)->replace('_', ' ')->title() }}</strong><span class="muted">{{ $request->requestedBy?->name }} · {{ $request->created_at->format('j M Y, g:i a') }}</span></div>
            <p style="white-space:pre-line">{{ $request->requested_value }}</p>
            @if($request->details)<p>{{ $request->details }}</p>@endif
            @if($request->resolution_note)<p><strong>Resolution:</strong> {{ $request->resolution_note }}</p>@endif
            @if(in_array($request->status, ['requested', 'in_review']))
                @if($changeRequest?->id === $request->id)<span class="badge">Reviewing below</span>
                @else<a class="button secondary" href="{{ route('admin.concert-bookings.show', [$booking, 'change_request' => $request->uuid]) }}">Review and apply changes</a>@endif
            @endif
        </article>
    @endforeach
</section>
@endif
