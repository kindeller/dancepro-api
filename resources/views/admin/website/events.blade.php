@extends('layouts.admin',['title'=>'Website events','heading'=>'Website events','subheading'=>'Choose events for the homepage’s upcoming events section.'])
@section('content')@include('admin.website._nav')
<p>Events appear automatically within the next six months, including events still in progress. Past and cancelled events are excluded. Hide an event to keep it off the website until you restore it. This does not release its gallery or expose crew information.</p>
<form method="get" class="website-actions"><input name="q" value="{{ request('q') }}" placeholder="Find an event" aria-label="Find an event"><button>Search</button></form>
<div class="card website-scroll"><table><thead><tr><th>Event</th><th>Date</th><th>Website</th><th>Actions</th></tr></thead><tbody>@forelse($events as $event)<tr><td><strong>{{ $event->name }}</strong><div>{{ $event->studio?->name ?? $event->competitionContact?->name }}</div></td><td>{{ $event->event_date?->format('j M Y') }}@if($event->cancelled_at)<p>Cancelled</p>
@endif</td><td>{{ $event->website_hidden ? 'Manually hidden' : 'Automatic · next six months' }}</td><td><form method="post" action="{{ route('admin.website.events.update',$event) }}">@csrf @method('PATCH')<input name="website_visible" type="hidden" value="{{ $event->website_hidden ? 1 : 0 }}"><button @disabled($event->cancelled_at && $event->website_hidden)>{{ $event->website_hidden ? 'Restore automatic display' : 'Hide from website' }}</button></form><a href="{{ route('admin.scheduling-events.edit',$event) }}">Edit event details</a></td></tr>
@empty<tr><td colspan="4">No matching events.</td></tr>
@endforelse</tbody></table></div>{{ $events->links() }}
@endsection
