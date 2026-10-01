@extends('layouts.public', ['title' => 'Notifications stopped', 'footerLabel' => 'Gallery notifications'])

@section('content')
<section class="section"><div class="card unlock"><h1 style="font-size:38px;color:#101820">Notifications stopped</h1><p>{{ ($studioSubscription ?? false) ? 'You won’t receive further gallery release emails for this studio.' : 'You won’t receive a release email for this event.' }}</p><a class="button" href="{{ route('guest-galleries.index') }}">Browse galleries</a></div></section>
@endsection
