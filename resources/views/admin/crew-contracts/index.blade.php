@extends('layouts.admin', ['title' => 'Crew contracts', 'heading' => 'Crew contracts', 'subheading' => 'Versioned contract templates and signature progress.'])

@section('content')
    @include('admin.crew-management._tabs')
    <div class="toolbar"><div class="muted">Contract versions cannot silently replace one another.</div><a class="button" href="{{ route('admin.crew-contracts.create') }}">Add contract version</a></div>
    <div class="card"><table><thead><tr><th>Contract</th><th>Version</th><th>Status</th><th>Effective from</th><th>Signatures recorded</th><th>Created</th><th>Actions</th></tr></thead><tbody>
        @forelse($contracts as $contract)
            <tr><td><strong>{{ $contract->name }}</strong></td><td>{{ $contract->version }}</td><td><span class="badge">{{ $contract->status->value }}</span></td><td>{{ $contract->effective_from?->format('j M Y') ?: '—' }}</td><td>{{ $contract->signatures_count }}</td><td>{{ $contract->created_at?->toDayDateTimeString() }}</td><td><div class="table-actions"><a class="button secondary" href="{{ route('admin.crew-contracts.show', $contract) }}">View</a><a class="button" href="{{ route('admin.crew-contracts.duplicate', $contract) }}">Duplicate</a></div></td></tr>
        @empty<tr><td colspan="7" class="muted">No contract versions have been created.</td></tr>@endforelse
    </tbody></table><div class="pagination"><x-admin-pagination :paginator="$contracts" /></div></div>
@endsection

@push('styles')
<style>.table-actions{display:flex;align-items:center;gap:6px;white-space:nowrap}.table-actions .button{padding:7px 10px}</style>
@endpush
