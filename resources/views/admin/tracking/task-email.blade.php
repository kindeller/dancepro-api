@extends('layouts.admin', ['title' => $definition['numbered_label'], 'heading' => $event->concertBookingItem?->booking?->studio_name ?? $event->name, 'subheading' => $definition['numbered_label']])
@section('content')
@include('admin.tracking._task-bar', ['bar' => $trackerBar])
<div class="toolbar" style="margin:18px 0">@isset($invoice)<a class="button secondary" href="{{ route('admin.studio-invoices.show', $invoice) }}">Back to invoice &amp; payment</a>@endisset
@if($definition['key'] === 'final_numbers_email' && $event->concertBookingItem?->booking)<a class="button secondary" href="{{ route('admin.concert-bookings.show', [$event->concertBookingItem->booking, 'tab' => 'seats-families']) }}">View seats &amp; families numbers</a>@endif<a class="button secondary" href="{{ route('admin.concert-tracker.templates.index') }}">Email templates</a></div>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert" style="color:#b42331">{{ $errors->first() }}</p>@endif
<div style="width:100%;min-width:0">
    <p>{{ $definition['description'] }}</p>
    @include('admin.tracking._email-action')
</div>
@endsection
@push('styles')
<style>.email-preview{padding:18px;border:1px solid var(--line);border-radius:8px;background:#fff;overflow-wrap:anywhere}.email-preview-body{line-height:1.55}.note{padding:12px 0;border-top:1px solid var(--line)}</style>
@endpush
