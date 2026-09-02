@extends('layouts.admin', ['title' => $contract->name.' '.$contract->version, 'heading' => $contract->name, 'subheading' => 'Contract version '.$contract->version])

@section('content')
    @include('admin.crew-management._tabs')
    <div class="toolbar">
        <a class="button secondary" href="{{ route('admin.crew-contracts.index') }}">Back to contracts</a>
        <a class="button" href="{{ route('admin.crew-contracts.duplicate', $contract) }}">Duplicate as new version</a>
    </div>
    <div class="card card-pad contract-summary">
        <div><span class="muted">Version</span><strong>{{ $contract->version }}</strong></div>
        <div><span class="muted">Status</span><strong>{{ ucfirst($contract->status->value) }}</strong></div>
        <div><span class="muted">Effective from</span><strong>{{ $contract->effective_from?->format('j M Y') ?: '—' }}</strong></div>
        <div><span class="muted">Signatures recorded</span><strong>{{ $contract->signatures_count }}</strong></div>
        <div><span class="muted">Created by</span><strong>{{ $contract->createdBy?->name ?: '—' }}</strong></div>
        <div><span class="muted">Created</span><strong>{{ $contract->created_at?->toDayDateTimeString() }}</strong></div>
    </div>
    <article class="card card-pad contract-document">{!! $contract->content !!}</article>
@endsection

@push('styles')
<style>
    .contract-summary { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; margin-bottom:16px; }
    .contract-summary span,.contract-summary strong { display:block; }
    .contract-summary strong { margin-top:4px; }
    .contract-document { max-width:900px; line-height:1.7; }
    @media(max-width:700px) { .contract-summary { grid-template-columns:1fr 1fr; } }
</style>
@endpush
