@extends('layouts.admin', ['title' => 'Add concert', 'heading' => 'Add concert', 'subheading' => 'Add a concert and set its studio, availability and access settings.'])
@section('content')
@if($studios->isEmpty())
<div class="notice">Create a studio before adding a concert. <a href="{{ route('admin.studios.create') }}">Add a studio</a>.</div>
@else
<form class="card card-pad" method="POST" action="{{ route('admin.concerts.store') }}">@csrf
    @include('admin.concerts._form', ['submitLabel' => 'Create concert'])
</form>
<p class="muted">After creating the concert, open its edit page to upload a cover image and PDF programme.</p>
@endif
@endsection
