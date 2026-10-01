@extends('layouts.admin', ['title' => 'Review studio request', 'heading' => 'Review studio request', 'subheading' => $submission->name])
@section('content')
<p><a class="button secondary" href="{{ route('admin.studio-requests.index') }}">← Back to studio requests</a></p>
<section class="card card-pad">
    <h2>{{ $submission->name }}</h2>
    <p>{{ $submission->location ?: 'No location supplied' }}</p>
    <p>Requested by {{ $submission->user?->name ?? 'Account unavailable' }} · {{ $submission->user?->email }} · {{ $submission->created_at->format('j M Y, g:i a') }}</p>
    <p>Status: <strong>{{ ucfirst($submission->status) }}</strong></p>
    @if($submission->reviewed_at)<p>Reviewed {{ $submission->reviewed_at->format('j M Y, g:i a') }}</p>@endif
    @if($submission->review_note)<p>{{ $submission->review_note }}</p>@endif
    @if($submission->studio)<a class="button" href="{{ route('admin.studios.edit', $submission->studio) }}">Open {{ $submission->studio->name }}</a>@endif
</section>
@if($submission->status === 'pending')
<section class="card card-pad" style="margin-top:20px">
    <h2>Link to an existing studio</h2><p>Check for an existing entry before creating another. Approval attaches the studio to this customer’s profile; it includes released concert photo galleries, but does not grant Studio Hub staff access, competition event access or concert video access.</p>
    <form method="POST" action="{{ route('admin.studio-requests.store', $submission) }}" class="grid">@csrf
        <input type="hidden" name="decision" value="match">
        <label>Existing studio<select name="studio_uuid" required><option value="">Choose studio</option>@foreach($studios as $studio)<option value="{{ $studio->uuid }}" @selected(old('studio_uuid') === $studio->uuid)>{{ $studio->name }}{{ $studio->code ? ' · '.$studio->code : '' }}</option>@endforeach</select></label>
        <div><button>Approve & link studio</button></div>
    </form>
</section>
<details class="card card-pad" style="margin-top:20px" @if(old('decision') === 'create') open @endif><summary><strong>Create a new studio</strong></summary>
    <p>Confirm the name and enter any studio details you have. The customer is attached automatically. Do not add the customer as Studio staff unless they manage the studio.</p>
    <form method="POST" action="{{ route('admin.studio-requests.store', $submission) }}" enctype="multipart/form-data">@csrf
        <input type="hidden" name="decision" value="create">
        @include('admin.studios._form', ['submitLabel' => 'Create studio & approve request', 'studio' => null, 'statuses' => [\App\Features\Studios\Support\StudioStatus::Active], 'cancelUrl' => route('admin.studio-requests.index')])
    </form>
</details>
<details class="card card-pad" style="margin-top:20px" @if(old('decision') === 'decline') open @endif><summary><strong>Decline request</strong></summary>
    <form method="POST" action="{{ route('admin.studio-requests.store', $submission) }}" class="grid">@csrf
        <input type="hidden" name="decision" value="decline">
        <label>Reason shown to the customer<textarea name="review_note" required maxlength="2000">{{ old('review_note') }}</textarea></label>
        <div><button class="secondary">Decline request</button></div>
    </form>
</details>
@endif
@endsection
