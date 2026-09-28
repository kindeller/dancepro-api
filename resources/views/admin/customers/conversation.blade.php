@extends('layouts.admin', ['title' => 'Customer conversation', 'heading' => 'Customer conversation', 'subheading' => $conversation->user->name])
@push('styles')@include('customers.messages._styles')@endpush
@section('content')
<div class="toolbar"><a class="button secondary" href="{{ route('admin.media-support.index') }}">← Back to customer support</a><a class="button secondary" href="{{ route('admin.customers.show', [$conversation->user, 'tab' => 'messages']) }}">Customer account</a></div>
@if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
<div class="card card-pad">@include('customers.messages._thread')</div>
@endsection
