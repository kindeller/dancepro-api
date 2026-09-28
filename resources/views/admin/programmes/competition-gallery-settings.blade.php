@extends('layouts.admin', ['title' => 'Gallery settings', 'heading' => 'Gallery settings', 'subheading' => $presentation['title']])

@section('content')
<div class="toolbar" style="margin-bottom:20px">
    <a class="button secondary" href="{{ route('admin.competition-gallery.show', $event) }}">← Back to gallery</a>
</div>
<section class="card card-pad" style="margin-bottom:20px">
    <h2>Event media access code</h2>
    <p class="muted">{{ $presentation['login_code_configured'] ? 'A login code is set for this event.' : 'Set a login code so customers can access this event after selecting it.' }} This is separate from the media code.</p>
    <form method="POST" action="{{ route('admin.competition-gallery.settings.update', $event) }}" class="grid">
        @csrf
        <input type="hidden" name="action" value="login_code">
        <input type="hidden" name="version" value="{{ $presentation['version'] }}">
        <label>New event media access code<input type="password" name="login_code" required minlength="6" maxlength="64" autocomplete="new-password"></label>
        <p class="muted">Use 6–64 letters, numbers or hyphens. Codes are not case-sensitive. Keep a copy to share with customers; saved codes cannot be displayed. Changing the code keeps existing verified registrations.</p>
        <div><button>Save access code</button></div>
    </form>
</section>
<div class="grid two-col">
    <section class="card card-pad">
        <h2>Gallery title</h2>
        <form method="POST" action="{{ route('admin.competition-gallery.settings.update', $event) }}" class="grid">
            @csrf
            <input type="hidden" name="action" value="title">
            <input type="hidden" name="version" value="{{ $presentation['version'] }}">
            <label>Display title<input name="display_title" maxlength="255" value="{{ old('display_title', $presentation['display_title']) }}" placeholder="{{ $event->name }}"></label>
            <p class="muted">Leave blank to use the event name. The event’s planning details stay unchanged.</p>
            <div><button>Save title</button></div>
        </form>
    </section>
    <section class="card card-pad">
        <h2>Gallery cover</h2>
        <x-event-artwork :url="$presentation['cover_url']" :name="$presentation['title']" size="large" />
        <p class="muted">{{ $presentation['cover_uuid'] ? 'Using your selected gallery photo.' : 'Using the event logo.' }} To choose a photo, open a competitor album and click Use as cover.</p>
        @if($presentation['cover_uuid'])
            <form method="POST" action="{{ route('admin.competition-gallery.settings.update', $event) }}">
                @csrf
                <input type="hidden" name="action" value="reset_cover">
                <input type="hidden" name="version" value="{{ $presentation['version'] }}">
                <button class="secondary">Use event logo</button>
            </form>
        @endif
    </section>
    <section class="card card-pad">
        <h2>Watermark</h2>
        <p class="muted">Current: {{ $presentation['watermark_name'] }}. Changing this creates new preview URLs; originals remain unchanged.</p>
        <form method="POST" action="{{ route('admin.competition-gallery.settings.update', $event) }}" class="grid">@csrf
            <input type="hidden" name="action" value="watermark"><input type="hidden" name="version" value="{{ $presentation['version'] }}">
            <label>Gallery watermark<select name="watermark_uuid"><option value="">Default competition watermark</option>@foreach($watermarks as $watermark)<option value="{{ $watermark->uuid }}" @selected($presentation['watermark_uuid'] === $watermark->uuid)>{{ $watermark->name }}</option>@endforeach</select></label>
            <div><button>Save watermark</button></div>
        </form>
    </section>
</div>
<section class="card card-pad" style="margin-top:20px">
    <h2>Recent changes</h2>
    @forelse($history as $change)
        @php($before = json_decode($change->before, true))
        @php($after = json_decode($change->after, true))
        <div style="border-top:1px solid var(--line);padding:10px 0">
            <strong>{{ $change->actor_name ?? 'Former administrator' }}</strong> · {{ $change->created_at }}
            @if($after['login_code_changed'] ?? false)<div>Changed the event media access code.</div>@endif
            @if(($before['display_title'] ?? null) !== ($after['display_title'] ?? null))
                <div>Title: {{ $before['display_title'] ?? 'Event name' }} → {{ $after['display_title'] ?? 'Event name' }}</div>
            @endif
            @if(($before['cover_photo_uuid'] ?? null) !== ($after['cover_photo_uuid'] ?? null))
                <div>{{ ($after['cover_photo_uuid'] ?? null) ? 'Selected a gallery photo as the cover.' : 'Restored the event logo.' }}</div>
            @endif
        </div>
    @empty<p class="muted">No gallery settings changed yet.</p>@endforelse
</section>
@endsection
