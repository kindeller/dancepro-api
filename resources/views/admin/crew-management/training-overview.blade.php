@extends('layouts.admin', ['title' => 'Training overview', 'heading' => 'Training overview', 'subheading' => 'See required learning, due dates and completion progress across the whole crew.'])

@section('content')
    @include('admin.crew-management._tabs')

    <div class="toolbar">
        <a class="button secondary" href="{{ route('admin.crew-management.training') }}">Back to courses</a>
        <a class="button" href="{{ route('admin.training-courses.export') }}">Export CSV</a>
    </div>

    <form method="GET" class="card card-pad filter-grid">
        <label>Search crew<input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name or email"></label>
        <label>Course<select name="course_id"><option value="">All courses</option>@foreach($allCourses as $filterCourse)<option value="{{ $filterCourse->id }}" @selected(($filters['course_id'] ?? null) == $filterCourse->id)>{{ $filterCourse->title }}</option>@endforeach</select></label>
        <label>Status<select name="status"><option value="">All statuses</option>@foreach(['not_assigned'=>'Not assigned','assigned'=>'Assigned','in_progress'=>'In progress','overdue'=>'Overdue','completed'=>'Completed'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>@endforeach</select></label>
        <div class="toolbar" style="align-self:end;justify-content:flex-start"><button>Filter</button><a class="button secondary" href="{{ route('admin.training-courses.overview') }}">Clear</a></div>
    </form>

    <div class="card" style="overflow-x:auto">
        <table class="training-matrix">
            <thead><tr><th class="sticky-name">Crew member</th>@foreach($courses as $course)<th><span title="{{ $course->title }}">{{ Str::limit($course->title, 24) }}</span><div class="muted">{{ $course->is_required ? 'Required' : 'Optional' }}</div></th>@endforeach</tr></thead>
            <tbody>
                @forelse($profiles as $profile)
                    <tr>
                        <th class="sticky-name"><a href="{{ route('admin.training-courses.crew-history', $profile) }}">{{ $report->crewName($profile) }}</a><div class="muted">{{ $profile->user->email }}</div></th>
                        @foreach($courses as $course)
                            @php($enrolment = $profile->trainingEnrolments->firstWhere('training_course_id', $course->id))
                            @php($cellStatus = $report->status($enrolment))
                            <td>
                                <span class="training-state {{ $cellStatus }}">{{ ucfirst(str_replace('_', ' ', $cellStatus)) }}</span>
                                @if($enrolment)
                                    <div class="muted">{{ $enrolment->moduleProgress->whereNotNull('completed_at')->count() }}/{{ $course->modules->count() }} modules</div>
                                    @if($enrolment->due_at && $enrolment->status !== 'completed')<div class="muted">Due {{ $enrolment->due_at->format('j M Y') }}</div>@endif
                                    @if($enrolment->status !== 'completed')
                                        <details class="reminder-log"><summary>Log reminder</summary><form method="POST" action="{{ route('admin.training-reminders.store', $enrolment) }}" class="grid">@csrf<label>Contact method<select name="method"><option value="manual">Manual reminder</option><option value="email">Email</option><option value="phone">Phone</option><option value="in_person">In person</option></select></label><label>Note<textarea name="note" rows="2" placeholder="Optional"></textarea></label><button>Record</button></form>@if($latest=$enrolment->reminders->first())<div class="muted">Last: {{ $latest->reminded_at->format('j M Y') }}</div>@endif</details>
                                    @endif
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ max(2, $courses->count() + 1) }}" class="muted">No crew match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <style>
        .filter-grid{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:14px;margin-bottom:18px}.training-matrix th,.training-matrix td{min-width:180px;vertical-align:top}.training-matrix .sticky-name{position:sticky;left:0;background:white;z-index:1;min-width:220px}.training-state{display:inline-block;padding:5px 9px;border-radius:999px;background:#edf2f4;font-weight:700;font-size:13px}.training-state.assigned,.training-state.in_progress{background:#fff3cd;color:#765600}.training-state.overdue{background:#fde2e2;color:#9b2c2c}.training-state.completed{background:#dff5e8;color:#16734a}.reminder-log{margin-top:8px}.reminder-log summary{cursor:pointer;color:#0787ba;font-weight:700}.reminder-log form{min-width:220px;margin-top:8px}@media(max-width:800px){.filter-grid{grid-template-columns:1fr}}
    </style>
@endsection
