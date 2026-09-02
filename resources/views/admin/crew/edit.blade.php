@extends('layouts.admin', ['title' => 'Edit '.$crewProfile->preferred_name, 'heading' => $crewProfile->preferred_name, 'subheading' => 'Crew profile, qualifications, and contract record.'])

@section('content')
    @include('admin.crew-management._tabs')
    <form method="POST" action="{{ route('admin.crew.update', $crewProfile) }}">
        @csrf
        @method('PUT')
        @include('admin.crew._form', ['submitLabel' => 'Save crew member'])
    </form>

    <div class="card card-pad" style="margin-top:28px">
        <div class="toolbar"><div><h2>Contracts</h2><div class="muted">Enter an existing signature date, or correct it later. Every correction is retained.</div></div><a class="button secondary" href="{{ route('admin.crew-contracts.create') }}">Add contract version</a></div>
        <div class="grid">
            @forelse($contracts as $contract)
                @php($signature = $crewProfile->contractSignatures->firstWhere('crew_contract_id', $contract->id))
                <form method="POST" action="{{ route('admin.crew.contract-signatures.store', [$crewProfile, $contract]) }}" class="grid" style="grid-template-columns:minmax(220px,1fr) minmax(190px,.7fr) minmax(220px,1fr) auto;align-items:end;border-top:1px solid var(--line);padding-top:14px">
                    @csrf
                    <div><strong>{{ $contract->name }}</strong> <span class="badge">{{ $contract->version }}</span><div class="muted">{{ ucfirst($contract->status->value) }}@if($signature) · recorded {{ $signature->events->count() }} time(s)@endif</div></div>
                    <label>Signed date and time<input type="datetime-local" name="signed_at" value="{{ old('signed_at', $signature?->signed_at?->format('Y-m-d\TH:i')) }}" required></label>
                    <label>Reason or note<input name="recording_note" maxlength="2000" placeholder="Required context for corrections" value="{{ old('recording_note') }}"></label>
                    <button type="submit">{{ $signature ? 'Correct record' : 'Record signed' }}</button>
                </form>
            @empty<div class="muted">No contract versions have been created.</div>@endforelse
        </div>
    </div>
@endsection
