@extends('layouts.admin', ['title' => $definition['numbered_label'], 'heading' => $event->studio?->name ?? $event->name, 'subheading' => $event->name])
@section('content')
@include('admin.tracking._task-bar', ['bar' => $trackerBar])
<section class="card card-pad"><h2>Director approval &amp; final video release</h2>
<p>{{ $status['detail'] }}</p>
@if($status['key'] !== 'not_required' && !$review['released'])
    @include('admin.tracking._action-blockers', ['blockers' => $review['uploads']['blockers'], 'blockerHeading' => 'Director release is not available yet'])
    @if($review['review']->revision && (!$review['checksReady'] || !$review['currentMail']))
        @include('admin.tracking._action-blockers', ['blockers' => ['Complete and upload the current director corrections, then send the corrections email before the director can release this version.'], 'blockerHeading' => 'Director corrections are outstanding'])
    @endif
    @if($review['canRelease'])<p role="status">Ready for the studio director to review and release. This task completes only when the director releases the current version.</p>@endif
@endif
<p>The studio director reviews the concert from their Studio Hub and clicks Release concert video. That action approves and publishes the current videos together. Families then use the video access code provided by their studio.</p>
<a class="button secondary" href="{{ route('admin.concert-tracker.tasks.show', [$event, 'approval_link_email']) }}">Video approval email</a>
<a class="button secondary" href="{{ route('admin.concert-tracker.tasks.show', [$event, 'director_corrections']) }}">Director corrections</a>
</section>
@endsection
