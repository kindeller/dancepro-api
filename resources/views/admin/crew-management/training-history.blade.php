@extends('layouts.admin', ['title' => 'Crew training history', 'heading' => $report->crewName($profile), 'subheading' => 'Permanent course history, assessment results and reminder records.'])

@section('content')
    @include('admin.crew-management._tabs')
    <div class="toolbar"><a class="button secondary" href="{{ route('admin.training-courses.overview') }}">Back to overview</a><span class="muted">{{ $profile->user->email }}</span></div>

    <div class="grid">
        @forelse($profile->trainingEnrolments as $enrolment)
            @php($historyStatus = $report->status($enrolment))
            <article class="card card-pad grid">
                <div class="toolbar"><div><h2>{{ $enrolment->course->title }}</h2><p class="muted">{{ $enrolment->course->is_required ? 'Required' : 'Optional' }} · {{ $enrolment->moduleProgress->whereNotNull('completed_at')->count() }}/{{ $enrolment->course->modules->count() }} modules</p></div><span class="training-state {{ $historyStatus }}">{{ ucfirst(str_replace('_', ' ', $historyStatus)) }}</span></div>
                <dl class="details-grid"><div><dt>Assigned</dt><dd>{{ $enrolment->assigned_at?->format('j M Y, g:i a') ?: '—' }}</dd></div><div><dt>Due</dt><dd>{{ $enrolment->due_at?->format('j M Y') ?: '—' }}</dd></div><div><dt>Started</dt><dd>{{ $enrolment->started_at?->format('j M Y, g:i a') ?: '—' }}</dd></div><div><dt>Completed</dt><dd>{{ $enrolment->completed_at?->format('j M Y, g:i a') ?: '—' }}</dd></div></dl>
                @if($enrolment->assessmentAttempts->isNotEmpty())<div><h3>Assessment attempts</h3><p class="muted">{{ $enrolment->assessmentAttempts->count() }} attempts · Best score {{ number_format($enrolment->assessmentAttempts->max('score_percent'), 0) }}%</p></div>@endif
                @if($enrolment->reminders->isNotEmpty())<div><h3>Reminder history</h3><ul>@foreach($enrolment->reminders->sortByDesc('reminded_at') as $reminder)<li>{{ $reminder->reminded_at->format('j M Y, g:i a') }} · {{ ucfirst(str_replace('_', ' ', $reminder->method)) }}@if($reminder->note) — {{ $reminder->note }}@endif</li>@endforeach</ul></div>@endif
            </article>
        @empty
            <div class="card empty-state"><h2>No training history</h2><p class="muted">This crew member has not started or been assigned any courses.</p></div>
        @endforelse
    </div>
    <style>.training-state{display:inline-block;padding:5px 9px;border-radius:999px;background:#edf2f4;font-weight:700}.training-state.assigned,.training-state.in_progress{background:#fff3cd;color:#765600}.training-state.overdue{background:#fde2e2;color:#9b2c2c}.training-state.completed{background:#dff5e8;color:#16734a}</style>
@endsection
