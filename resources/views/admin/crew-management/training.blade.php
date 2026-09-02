@extends('layouts.admin', ['title' => 'Crew training', 'heading' => 'Crew training', 'subheading' => 'Build anything from a one-video equipment update to full role training.'])

@section('content')
    @include('admin.crew-management._tabs')

    <div class="toolbar">
        <p class="muted">Completions are permanent. When training needs refreshing, create a renewal course linked to the original.</p>
        <div><a class="button secondary" href="{{ route('admin.training-courses.overview') }}">Training overview</a> <a class="button" href="{{ route('admin.training-courses.create') }}">Add course</a></div>
    </div>

    <div class="card">
        <table>
            <thead><tr><th>Course</th><th>Audience</th><th>Size</th><th>Status</th><th>Progress</th><th></th></tr></thead>
            <tbody>
                @forelse($courses as $course)
                    <tr>
                        <td><strong>{{ $course->title }}</strong><div class="muted">{{ $course->is_required ? 'Required' : 'Optional' }}{{ $course->estimated_minutes ? ' · '.$course->estimated_minutes.' min' : '' }}</div></td>
                        <td>{{ $course->role?->name ?: 'All crew' }}@if($course->grants_role_qualification)<div class="muted">Awards role on completion</div>@endif</td>
                        <td>{{ $course->modules->count() }} {{ Str::plural('module', $course->modules->count()) }}</td>
                        <td><span class="badge">{{ ucfirst($course->status) }}</span></td>
                        <td>{{ $course->completions_count }} completed</td>
                        <td><div class="toolbar" style="justify-content:flex-end"><a class="button secondary" href="{{ route('admin.training-courses.assignments', $course) }}">Assignments &amp; progress</a><a class="button secondary" href="{{ route('admin.training-courses.edit', $course) }}">Edit</a></div></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No training courses yet. Add the structure now and keep it as a draft until the material is ready.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
