@extends('layouts.admin', ['title' => 'Studio requests', 'heading' => 'Studio requests', 'subheading' => 'Confirm customer-submitted studios and keep each customer linked to the approved record.'])
@section('content')
@include('admin.contacts._tabs')
<div class="card card-pad">
    @forelse($submissions as $submission)
        <div class="toolbar" style="padding:16px 0;border-bottom:1px solid var(--line)">
            <div><strong>{{ $submission->name }}</strong><p class="muted">{{ $submission->location }} · {{ $submission->user?->name ?? 'Account unavailable' }} · {{ $submission->created_at->format('j M Y') }}</p><span class="badge">{{ ucfirst($submission->status) }}</span></div>
            <a class="button secondary" href="{{ route('admin.studio-requests.show', $submission) }}">{{ $submission->status === 'pending' ? 'Review studio' : 'View outcome' }}</a>
        </div>
    @empty<p>No studio requests to review.</p>@endforelse
    {{ $submissions->links() }}
</div>
@endsection
