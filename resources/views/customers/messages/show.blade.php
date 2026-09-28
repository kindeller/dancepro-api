@extends('layouts.public', ['title' => 'My Messages'])
@push('styles')@include('customers.messages._styles')@endpush
@section('content')
@include('customers.messages._header')
<section class="section"><div class="container">
    <p><a href="{{ route('customer.messages.index', ['tab' => 'conversations']) }}">← Back to conversations</a></p>
    @if(session('status'))<div class="card card-body message-notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="card card-body message-notice" role="alert">{{ $errors->first() }}</div>@endif
    <div class="card card-body">@include('customers.messages._thread')</div>
</div></section>
@include('customers._tab-scroll')
@endsection
