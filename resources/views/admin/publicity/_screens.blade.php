@forelse($screens as $screen)
<div class="publicity-screen"><div><strong>{{ $screen->name }}</strong>
<p>{{ $screen->event?->name ?? (isset($event) ? $event->name : 'Event unavailable') }}</p>
<p class="publicity-note">{{ $screen->revoked_at ? 'Disconnected' : ($screen->last_seen_at?->gt(now()->subMinutes(2)) ? 'Connected' : 'Not currently connected') }} · Last contact: {{ $screen->last_seen_at?->diffForHumans() ?? 'Waiting for screen' }} · {{ $screen->cached_count }} slides ready</p>
</div>
@if(!$screen->revoked_at)<form method="post" action="{{ route('admin.publicity.screens.revoke', $screen) }}">@csrf<button class="secondary">Disconnect screen</button></form>@endif</div>
@empty<p class="muted">No paired screens yet. Open an event below to pair a screen.</p>@endforelse
