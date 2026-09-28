@extends('layouts.admin', ['title' => 'Event availability', 'heading' => 'Event availability', 'subheading' => 'All competition and concert shifts, assignments and crew responses in one place.'])
@section('content')
@include('admin.scheduling-events._availability')
@endsection
