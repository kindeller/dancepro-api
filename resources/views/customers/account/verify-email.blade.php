@extends('layouts.public', ['title' => 'Verify your email', 'footerLabel' => 'DancePro Media'])
@section('content')
<section class="section"><div class="container" style="max-width:560px">
    <h1>Verify your email</h1>
    <p>We sent a verification link to your email address. Open it to finish setting up your DancePro account.</p>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    <form method="POST" action="{{ route('customer.verification.resend') }}">@csrf<button class="button">Send a new link</button></form>
</div></section>
@endsection
