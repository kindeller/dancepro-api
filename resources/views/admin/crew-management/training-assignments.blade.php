@extends('layouts.admin', ['title' => 'Training assignments', 'heading' => $course->title, 'subheading' => 'Assign this course, set a due date and monitor each crew member’s progress.'])

@section('content')
    @include('admin.crew-management._tabs')

    @php($enrolments = $course->enrolments->keyBy('crew_profile_id'))
    <form method="POST" action="{{ route('admin.training-courses.assignments.update', $course) }}" class="grid">
        @csrf @method('PUT')
        <div class="toolbar">
            <label style="max-width:260px">Due date for selected crew<input type="date" name="due_at" value="{{ old('due_at', $course->enrolments->whereNotNull('due_at')->first()?->due_at?->format('Y-m-d')) }}"></label>
            <div><a class="button secondary" href="{{ route('admin.crew-management.training') }}">Back to courses</a> <button>Save assignments</button></div>
        </div>

        <div class="card">
            <table>
                <thead><tr><th style="width:50px"><input type="checkbox" id="select-all" aria-label="Select all crew"></th><th>Crew member</th><th>Status</th><th>Due</th><th>Progress</th></tr></thead>
                <tbody>
                    @forelse($crewProfiles as $profile)
                        @php($enrolment = $enrolments->get($profile->id))
                        @php($name = $profile->preferred_name ?: $profile->legal_name ?: $profile->user->name)
                        @php($isOverdue = $enrolment?->due_at?->isPast() && $enrolment->status !== 'completed')
                        <tr>
                            <td><input type="checkbox" name="crew_profile_ids[]" value="{{ $profile->id }}" @checked($enrolment) @disabled($enrolment?->started_at || $enrolment?->completed_at) style="width:auto">@if($enrolment?->started_at || $enrolment?->completed_at)<input type="hidden" name="crew_profile_ids[]" value="{{ $profile->id }}">@endif</td>
                            <td><strong>{{ $name }}</strong><div class="muted">{{ $profile->user->email }}</div></td>
                            <td><span class="badge {{ $isOverdue ? 'attention' : ($enrolment?->status === 'completed' ? 'done' : '') }}">{{ $isOverdue ? 'Overdue' : ucfirst(str_replace('_', ' ', $enrolment?->status ?: 'Not assigned')) }}</span></td>
                            <td>{{ $enrolment?->due_at?->format('j M Y') ?: '—' }}</td>
                            <td>
                                @if($enrolment)
                                    <strong>{{ $enrolment->moduleProgress->whereNotNull('completed_at')->count() }}</strong> / {{ $course->modules->count() }} modules
                                    @if($enrolment->completed_at)
                                        <div class="muted">Completed {{ $enrolment->completed_at->format('j M Y') }}</div>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">No active crew profiles are available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>
    <script>document.getElementById('select-all')?.addEventListener('change',event=>document.querySelectorAll('input[name="crew_profile_ids[]"]:not([disabled])').forEach(input=>input.checked=event.target.checked));</script>
@endsection
