@extends('layouts.crew', ['title' => 'My Training'])

@section('content')
<div class="page-heading"><div><div class="crew-hub-brand"><img src="{{ asset('images/brand/dancepro-icon-blue.png') }}" alt=""><span>CREW HUB</span></div><h1>My Training</h1><p class="muted">Required learning, quick updates and completed courses.</p></div></div>
<section><div class="resource-grid">
    @forelse($courses as $course)
        @php($enrolment=$course->enrolments->first())
        <article class="card resource-card">
            <div><span class="type-label">{{ $course->role?->name ?: 'All crew' }}</span><h2>{{ $course->title }}</h2><p class="muted">{{ $course->description }}</p></div>
            <div class="resource-actions"><span class="status-pill {{ $enrolment?->status === 'completed' ? 'done' : ($course->is_required || $enrolment ? 'attention' : '') }}">{{ $enrolment?->status === 'completed' ? 'Completed '.$enrolment->completed_at->format('j M Y') : ($enrolment ? 'Assigned' : ($course->is_required ? 'Required' : 'Available')) }}</span>@if($enrolment?->due_at && $enrolment->status !== 'completed')<span class="muted" style="color:{{ $enrolment->due_at->isPast() ? '#9b2c2c' : 'inherit' }}">Due {{ $enrolment->due_at->format('j M Y') }}</span>@endif<span class="muted">{{ $course->modules->count() }} modules{{ $course->estimated_minutes ? ' · '.$course->estimated_minutes.' min' : '' }}</span><a class="button" href="{{ route('crew.training.show', $course) }}">{{ $enrolment?->status === 'completed' ? 'Review' : ($enrolment?->status === 'in_progress' ? 'Continue' : 'Start') }}</a></div>
        </article>
    @empty
        <div class="card empty-state"><h2>No training assigned</h2><p class="muted">Published courses for your roles will appear here.</p></div>
    @endforelse
</div></section>
@endsection
