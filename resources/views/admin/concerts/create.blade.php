@extends('layouts.admin', ['title' => 'Add Concert', 'heading' => 'Add Concert', 'subheading' => 'Create the concert record now; media collections can be connected later.'])
@section('content')
@if($studios->isEmpty())
<div class="notice">Create a studio before adding a concert. <a href="{{ route('admin.studios.create') }}">Add a studio</a>.</div>
@else
<form class="card card-pad" method="POST" action="{{ route('admin.concerts.store') }}">@csrf
    @include('admin.concerts._form', ['submitLabel' => 'Create concert'])
</form>
@endif
@endsection
