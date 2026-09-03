@extends('layouts.crew', ['title' => 'Organise cover'])

@section('content')
@php($event=$assignment->shift->schedulingEvent)
<div class="page-heading"><div><p class="eyebrow">Shift cover</p><h1>Organise cover</h1><p class="muted">{{ $event->name }} · {{ $assignment->shift->shift_date->format('D j M Y') }} · {{ $assignment->role->name }}</p></div><a class="button secondary" href="{{ route('crew.assignments.show',$assignment) }}">Back to shift</a></div>

<section class="card profile-section">
    <h2>Who would you like to ask?</h2>
    <p class="muted">These crew members are active, approved for this role and do not have a conflicting published shift. The first person to accept receives the shift.</p>
    @if($assignment->is_team_leader)<div class="profile-warning">This shift includes 👑 Team Leader responsibility, so only Team Leader-qualified crew are shown.</div>@endif
    <form method="POST" action="{{ route('crew.cover.store',$assignment) }}" class="grid" style="margin-top:14px">@csrf
        @if($candidates->isNotEmpty())
            <label class="checklist-item"><input type="checkbox" id="select-all-cover"><span><strong>Select all eligible crew</strong></span></label>
            <div class="team-finish-list">
            @foreach($candidates as $candidate)
                @php($availability=$candidate->availabilityResponses->first()?->status?->value)
                <label><input class="cover-recipient" type="checkbox" name="recipients[]" value="{{ $candidate->uuid }}" @checked(in_array($candidate->uuid,old('recipients',[]),true))><span><strong>{{ $candidate->preferred_name ?: $candidate->user->name }}</strong><span class="status-pill {{ $availability==='available'?'done':($availability==='unavailable'?'attention':'') }}" style="margin-left:7px">{{ $availability ? ucfirst($availability) : 'No availability response' }}</span></span></label>
            @endforeach
            </div>
            <label>Optional personalised message<textarea name="message" maxlength="1000" placeholder="Add a message for everyone selected, or leave blank to send the standard request.">{{ old('message') }}</textarea></label>
            <button type="submit">Send cover request</button>
        @else
            <div class="empty-state"><strong>No eligible crew are currently available to ask.</strong><p class="muted">This can happen when everyone qualified has a conflicting shift.</p></div>
        @endif
    </form>
</section>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">const selectAll=document.getElementById('select-all-cover');if(selectAll){const recipients=[...document.querySelectorAll('.cover-recipient')];selectAll.addEventListener('change',()=>recipients.forEach(box=>box.checked=selectAll.checked));recipients.forEach(box=>box.addEventListener('change',()=>selectAll.checked=recipients.every(item=>item.checked)));}</script>
@endpush
