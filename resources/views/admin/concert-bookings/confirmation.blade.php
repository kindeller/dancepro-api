@extends('layouts.admin', ['title' => 'Booking confirmation', 'heading' => $booking->studio_name, 'subheading' => '1.1 Booking & services'])
@section('content')
@include('admin.tracking._task-bar', ['bar' => $trackerBar])
<div class="toolbar" style="margin:18px 0"><a class="button secondary" href="{{ route('admin.concert-bookings.show', $booking) }}">← Back to booking</a><a class="button secondary" href="{{ route('admin.concert-tracker.templates.index') }}">Email templates</a></div>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert" style="color:#b42331">{{ $errors->first() }}</p>@endif
<div style="width:100%;min-width:0">
    <h2>Booking confirmation</h2>
    <p>The booking is approved. Review the recipient and personalised template, then send the confirmation. Sending and delivery history are part of 1.1 Booking &amp; services.</p>
    @include('admin.tracking._email-action')
</div>
@endsection
@push('styles')
<style>
.email-preview { padding:18px; border:1px solid var(--line); border-radius:8px; background:#fff; overflow-wrap:anywhere; }
.email-preview-body { line-height:1.55; }
.note { padding:12px 0; border-top:1px solid var(--line); }
.note:first-child { border-top:0; }
</style>
@endpush
