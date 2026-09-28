<div class="toolbar" style="margin:18px 0"><a class="button secondary" href="{{ route('admin.scheduling-events.index') }}">← Back to event availability</a></div>
@if($titleWorkspace)
    <form method="get" class="card card-pad" style="margin-bottom:18px">
        <input type="hidden" name="tab" value="video-titles">
        <label>Event<select name="event">@foreach($titleEvents as $titleEvent)<option value="{{ $titleEvent->uuid }}" @selected($titleWorkspace['event']->id === $titleEvent->id)>{{ $titleEvent->name }} · {{ $titleEvent->event_date?->format('j M Y') }}</option>@endforeach</select></label><button>Open video titles</button>
    </form>
    @include('admin.programmes._title-cards', $titleWorkspace)
@else
    <p class="card card-pad">Video titles are available once a concert event is approved. They are not required for DR portraits.</p>
@endif
