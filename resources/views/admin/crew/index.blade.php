@extends('layouts.admin', [
    'title' => 'Crew',
    'heading' => 'Crew',
    'subheading' => 'Manage crew details, onboarding, qualifications and employment information.',
])

@section('content')
    @include('admin.crew-management._tabs')
    <div class="toolbar">
        <form class="filters" method="GET" action="{{ route('admin.crew.index') }}">
            <label>Search<input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name or email"></label>
            <label>Status<select name="active"><option value="">Any status</option><option value="1" @selected(($filters['active'] ?? '') === '1')>Active</option><option value="0" @selected(($filters['active'] ?? '') === '0')>Inactive</option></select></label>
            <button type="submit">Filter</button>
            <a class="button secondary" href="{{ route('admin.crew.index') }}">Reset</a>
        </form>
        <a class="button" href="{{ route('admin.crew.create') }}">Invite crew member</a>
    </div>

    <div class="card"><table><thead><tr><th>Crew member</th><th>Onboarding</th><th>Status</th><th>Contact</th><th>Service</th><th>Roles</th><th>Clothing</th><th></th></tr></thead><tbody>
        @forelse($crewProfiles as $crewProfile)
            <tr>
                <td><strong>{{ $crewProfile->preferred_name }}</strong><div class="muted">{{ $crewProfile->legal_name }}</div></td>
                <td>
                    @if($crewProfile->user->onboarding_completed_at)
                        <span class="badge">Complete</span><div class="muted" title="{{ $crewProfile->user->onboarding_completed_at->format('j M Y, g:i a') }}">Profile completed</div>
                    @elseif($crewProfile->user->invitation_sent_at)
                        <span class="badge pending">Invite sent</span><div class="muted" title="{{ $crewProfile->user->invitation_sent_at->format('j M Y, g:i a') }}">Awaiting setup</div>
                    @else
                        <span class="badge revoked">Not invited</span>
                    @endif
                </td>
                <td><span class="badge {{ $crewProfile->user->is_active ? '' : 'revoked' }}">{{ $crewProfile->user->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td>{{ $crewProfile->phone }}<div class="muted">{{ $crewProfile->user->email }}</div></td>
                <td>{{ $crewProfile->completedYearsOfService() }} years<div class="muted">Since {{ $crewProfile->commencement_date?->format('j M Y') }}</div></td>
                <td>{{ $crewProfile->roles->pluck('name')->join(', ') ?: 'None' }}</td>
                <td>Shirt {{ $crewProfile->shirt_size ?: '—' }}<div class="muted">Jacket {{ $crewProfile->jacket_size ?: '—' }}</div></td>
                <td style="white-space:nowrap"><a class="button secondary" href="{{ route('admin.crew.edit', $crewProfile) }}">Edit</a> @unless($crewProfile->user->onboarding_completed_at)<form method="POST" action="{{ route('admin.crew.invite', $crewProfile) }}" style="display:inline">@csrf<button type="submit" class="secondary">{{ $crewProfile->user->invitation_sent_at ? 'Resend invite' : 'Send invite' }}</button></form>@endunless</td>
            </tr>
        @empty<tr><td colspan="8" class="muted">No crew members match this view.</td></tr>@endforelse
    </tbody></table><div class="pagination"><x-admin-pagination :paginator="$crewProfiles" /></div></div>

@endsection
