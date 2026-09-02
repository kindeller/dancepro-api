@extends('layouts.admin', ['title' => $contact->name, 'heading' => $contact->name, 'subheading' => 'Competition contact'])
@section('content')
<div class="competition-page-controls">
    <a class="button secondary" href="{{ route('admin.competition-contacts.index') }}">← Back to competition contacts</a>
    <form class="competition-status-form" method="POST" action="{{ route('admin.competition-contacts.status.update', $contact) }}">
        @csrf @method('PATCH')
        <select class="status-select status-{{ $contact->is_active ? 'active' : 'inactive' }}" name="is_active" aria-label="Change competition contact status" onchange="this.form.submit()">
            <option value="1" @selected($contact->is_active)>Active</option>
            <option value="0" @selected(!$contact->is_active)>Inactive</option>
        </select>
    </form>
</div>
<form class="card card-pad" method="POST" action="{{ route('admin.competition-contacts.update', $contact) }}" enctype="multipart/form-data">@csrf @method('PUT') @include('admin.competition-contacts._form', ['submitLabel' => 'Save competition contact'])</form>
@endsection

@push('styles')
<style>
    .competition-page-controls { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:16px; }
    .competition-status-form { margin:0; }
    .status-select { width:auto; min-width:0; min-height:24px; border:0; border-radius:4px; padding:3px 8px; appearance:none; -webkit-appearance:none; background:var(--soft); color:var(--brand-strong); font:inherit; font-size:12px; font-weight:800; line-height:18px; text-align:left; text-transform:capitalize; cursor:pointer; }
    .status-inactive { background:#fef2f2; color:var(--danger); }
</style>
@endpush
