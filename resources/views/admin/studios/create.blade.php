@extends('layouts.admin', ['title' => 'Add Studio', 'heading' => 'Add Studio', 'subheading' => 'Create a studio before assigning concerts.'])
@section('content')
<form class="card card-pad" method="POST" action="{{ route('admin.studios.store') }}" enctype="multipart/form-data">@csrf
    @include('admin.studios._form', ['submitLabel' => 'Create studio'])
</form>
@endsection
