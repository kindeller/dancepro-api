@extends('layouts.admin', ['title' => 'Concert photos', 'heading' => 'Concert photos', 'subheading' => 'Manage studio galleries, event albums and photos.'])

@section('content')
@include('admin.media._backend-gallery-styles')
@include('admin.media._concert-studio-browser', ['topPicsOnly' => false, 'indexRoute' => 'admin.concert-photo-gallery.events', 'eventRoute' => 'admin.concert-photo-gallery.show'])
@endsection
