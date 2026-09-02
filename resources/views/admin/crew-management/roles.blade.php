@extends('layouts.admin', ['title' => 'Crew roles', 'heading' => 'Crew roles', 'subheading' => 'Manage the roles and qualifications available to crew members.'])

@section('content')
    @include('admin.crew-management._tabs')

    <div class="card card-pad">
        <h2>Add a crew role</h2>
        <p class="muted">Roles appear as qualifications on crew profiles and can be used when scheduling events.</p>
        <form class="filters" method="POST" action="{{ route('admin.crew-roles.store') }}">
            @csrf
            <label>Role name<input name="name" required maxlength="255" placeholder="Videographer"></label>
            <label>Code<input name="code" required maxlength="80" placeholder="competition-videographer"></label>
            <label>Event type<select name="event_type_definition_id"><option value="">Any event</option>@foreach($eventTypeDefinitions as $eventType)<option value="{{ $eventType->id }}" @selected((int) old('event_type_definition_id') === $eventType->id)>{{ $eventType->name }}</option>@endforeach</select></label>
            <button type="submit">Add role</button>
        </form>
    </div>

    <div class="card card-pad">
        <h2>Existing roles</h2>
        <p class="muted">Edit a role below. Inactive roles remain on historical records but are hidden from the assignment grid.</p>
        @forelse($roles as $role)
            <form class="role-edit-row" method="POST" action="{{ route('admin.crew-roles.update', $role) }}">
                @csrf
                @method('PUT')
                <label>Role name<input name="name" value="{{ $role->name }}" required maxlength="255"></label>
                <label>Code<input name="code" value="{{ $role->code }}" required maxlength="80"></label>
                <label>Event type<select name="event_type_definition_id"><option value="" @selected(blank($role->event_type_definition_id))>Any event</option>@foreach($eventTypeDefinitions as $eventType)<option value="{{ $eventType->id }}" @selected($role->event_type_definition_id === $eventType->id)>{{ $eventType->name }}</option>@endforeach</select></label>
                <label>Status<select name="is_active"><option value="1" @selected($role->is_active)>Active</option><option value="0" @selected(! $role->is_active)>Inactive</option></select></label>
                <button type="submit">Save</button>
            </form>
        @empty<div class="muted">No crew roles have been created.</div>@endforelse
    </div>

    <div class="card card-pad">
        <div class="toolbar"><div><h2>Crew role overview</h2><p class="muted">Checked means the role is on that crew member's profile. Existing training and inactive qualification statuses are preserved.</p></div></div>
        @if($crewProfiles->isNotEmpty() && $matrixRoles->isNotEmpty())
            <form method="POST" action="{{ route('admin.crew-roles.matrix.update') }}">
                @csrf
                @method('PUT')
                @foreach($crewProfiles as $crewProfile)<input type="hidden" name="crew_profile_ids[]" value="{{ $crewProfile->id }}">@endforeach
                @foreach($matrixRoles as $role)<input type="hidden" name="crew_role_ids[]" value="{{ $role->id }}">@endforeach
                <div class="role-matrix-wrap">
                    <table class="role-matrix">
                        <thead><tr><th>Crew member</th>@foreach($matrixRoles as $role)<th><span>{{ $role->name }}</span></th>@endforeach</tr></thead>
                        <tbody>
                            @foreach($crewProfiles as $crewProfile)
                                @php($qualifications = $crewProfile->roleQualifications->keyBy('crew_role_id'))
                                <tr>
                                    <th><strong>{{ $crewProfile->preferred_name }}</strong><span>{{ $crewProfile->user->email }}</span></th>
                                    @foreach($matrixRoles as $role)
                                        @php($qualification = $qualifications->get($role->id))
                                        <td>
                                            <label class="matrix-check" title="{{ $qualification ? ucfirst($qualification->status->value) : 'Not assigned' }}">
                                                <input type="checkbox" name="assignments[{{ $crewProfile->id }}][{{ $role->id }}]" value="1" @checked($qualification !== null)>
                                                <span class="sr-only">{{ $role->name }} for {{ $crewProfile->preferred_name }}</span>
                                            </label>
                                            @if($qualification && $qualification->status->value !== 'approved')<small>{{ ucfirst($qualification->status->value) }}</small>@endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="matrix-actions"><button type="submit">Save role assignments</button></div>
            </form>
        @elseif($crewProfiles->isEmpty())
            <p class="muted">Add an active crew member to use the role overview.</p>
        @else
            <p class="muted">Add an active role to use the role overview.</p>
        @endif
    </div>
@endsection

@push('styles')
<style>
    .role-edit-row { display:grid; grid-template-columns:minmax(180px,1.3fr) minmax(160px,1fr) minmax(150px,1fr) 120px auto; align-items:end; gap:10px; padding:14px 0; border-top:1px solid var(--line); }
    .role-matrix-wrap { max-width:100%; overflow:auto; border:1px solid var(--line); border-radius:6px; }
    .role-matrix { min-width:max-content; margin:0; }
    .role-matrix th,.role-matrix td { min-width:110px; text-align:center; vertical-align:middle; }
    .role-matrix thead th:first-child,.role-matrix tbody th { position:sticky; z-index:1; left:0; min-width:190px; background:#fff; text-align:left; }
    .role-matrix thead th:first-child { z-index:2; background:#f1f6f8; }
    .role-matrix thead th span { display:block; max-width:130px; margin:auto; white-space:normal; }
    .role-matrix tbody th span { display:block; color:var(--muted); font-size:10px; font-weight:400; }
    .matrix-check { display:inline-grid; width:28px; height:28px; place-items:center; cursor:pointer; }
    .matrix-check input { width:19px; height:19px; min-height:0; cursor:pointer; }
    .role-matrix td small { display:block; margin-top:3px; color:var(--muted); font-size:9px; }
    .matrix-actions { display:flex; justify-content:flex-end; margin-top:14px; }
    .sr-only { position:absolute; width:1px; height:1px; padding:0; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
    @media(max-width:900px) { .role-edit-row { grid-template-columns:1fr 1fr; } .role-edit-row button { width:max-content; } }
</style>
@endpush
