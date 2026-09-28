@extends('layouts.admin', ['title' => 'Add studio', 'heading' => 'Add studio', 'subheading' => 'Create a studio before assigning concerts.'])
@section('content')
<form class="card card-pad" method="POST" action="{{ route('admin.studios.store') }}" enctype="multipart/form-data">@csrf
    @include('admin.studios._form', ['submitLabel' => 'Create studio'])
</form>
<p class="muted">After creating the studio, open its edit page to upload a cover image.</p>
@endsection
