@extends('layouts.admin', ['title' => $studio->name.' video codes', 'heading' => 'Concert video codes', 'subheading' => $studio->name])
@section('content')
<p><a class="button secondary" href="{{ route('admin.studios.edit', $studio) }}">← Back to studio</a></p>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
<section class="card card-pad">
    <h2>Create code group</h2>
    <p class="muted">Set a code and choose concerts from the same studio and year. Newly saved codes are encrypted and shown to authorised studio managers in Studio Hub. Re-enter an older code once to make it displayable. Release the group when the access period should start.</p>
    <form method="POST" action="{{ route('admin.concert-code-groups.store', $studio) }}">@csrf
        <label>Group name<input name="name" value="{{ old('name') }}" maxlength="120" required placeholder="Junior concerts"></label>
        <label>Year<input name="year" type="number" min="1900" max="2200" value="{{ old('year', now()->year) }}" required></label>
        <label>Customer video code<input name="code" type="password" minlength="6" maxlength="64" pattern="[A-Za-z0-9-]+" required autocomplete="new-password"></label>
        <fieldset><legend>Concerts in this group</legend>
            @foreach($availableConcerts as $concert)
                <label class="choice"><input type="checkbox" name="concert_uuids[]" value="{{ $concert->uuid }}" @checked(in_array($concert->uuid, old('concert_uuids', []), true))><span>{{ $concert->name }} · {{ $concert->event_date?->format('j M Y') }}</span></label>
            @endforeach
        </fieldset>
        @foreach(['name','year','code','concert_uuids'] as $field)@error($field)<p role="alert">{{ $message }}</p>@enderror @endforeach
        <button>Create group</button>
    </form>
</section>
@foreach($groups as $group)
    <section class="card card-pad" style="margin-top:24px">
        <h2>{{ $group->name }} · {{ $group->year }}</h2>
        <p class="muted">{{ $group->released_at ? 'Released '.$group->released_at->format('j M Y') : 'Not released' }} · {{ $group->concerts->count() }} concerts · code set</p>
        @if($group->released_at)<p>Streaming until {{ $group->streamEndsAt()->format('j M Y') }}. Downloads until {{ $group->downloadEndsAt()->format('j M Y') }}.</p>@endif
        <ul>@foreach($group->concerts as $concert)<li>{{ $concert->name }} · {{ $concert->event_date?->format('j M Y') }}</li>@endforeach</ul>
        @if(!$group->released_at)
            <form method="POST" action="{{ route('admin.concert-code-groups.release', [$studio, $group]) }}">@csrf<input type="hidden" name="version" value="{{ $group->version }}"><button>Release group and start access periods</button></form>
        @endif
        @if($availableConcerts->where('event_date', '!=', null)->isNotEmpty())
            <form method="POST" action="{{ route('admin.concert-code-groups.concerts', [$studio, $group]) }}">@csrf
                <input type="hidden" name="version" value="{{ $group->version }}">
                <fieldset><legend>Add concerts from {{ $group->year }}</legend>
                    @foreach($availableConcerts->filter(fn ($concert) => $concert->event_date?->year === $group->year) as $concert)
                        <label class="choice"><input type="checkbox" name="concert_uuids[]" value="{{ $concert->uuid }}"><span>{{ $concert->name }} · {{ $concert->event_date?->format('j M Y') }}</span></label>
                    @endforeach
                </fieldset>
                <button class="button secondary">Add concerts</button>
            </form>
        @endif
        <form method="POST" action="{{ route('admin.concert-code-groups.code', [$studio, $group]) }}">@csrf
            <input type="hidden" name="version" value="{{ $group->version }}">
            <label>Replace customer code<input name="code" type="password" minlength="6" maxlength="64" pattern="[A-Za-z0-9-]+" autocomplete="new-password" required></label>
            <button class="button secondary">Change code</button>
        </form>
        @error('version')<p role="alert">{{ $message }}</p>@enderror
        <h3>Change history</h3>
        <ul>@foreach($group->changes->sortByDesc('id') as $change)<li>{{ str_replace('_', ' ', ucfirst($change->action)) }} · {{ $change->created_at->format('j M Y g:ia') }} · {{ $change->actor?->name ?? 'Former staff member' }}</li>@endforeach</ul>
    </section>
@endforeach
@endsection
