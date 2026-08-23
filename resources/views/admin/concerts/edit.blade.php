@extends('layouts.admin', ['title' => 'Edit '.$concert->name, 'heading' => 'Edit Concert', 'subheading' => $concert->studio->name.' · '.$concert->name])
@section('content')
<form class="card card-pad" method="POST" action="{{ route('admin.concerts.update', $concert) }}">@csrf @method('PUT')
    @include('admin.concerts._form', ['submitLabel' => 'Save concert'])
</form>
@endsection
