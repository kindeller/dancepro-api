@extends('layouts.public', ['title' => 'Family invitation'])
@section('content')
<section class="hero"><div class="container">@include('customers._heading', ['heading' => 'Family invitation', 'description' => 'Review your family access before accepting.'])</div></section>
<section class="section"><div class="container"><div class="card card-body" style="max-width:800px;margin-inline:auto">
@if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<h2>{{ $member->family->manager->name }} has invited you</h2>
<p>The account manager’s added studios and events will appear in your account automatically, including later additions. Existing release and expiry rules still apply.</p>
<p>View &amp; download purchased media: <strong>{{ $member->can_view_media ? 'Yes' : 'No' }}</strong><br>Make purchases: <strong>{{ $member->can_purchase ? 'Yes' : 'No' }}</strong></p>
<p>Joining automatically shares your eligible existing and future purchases with this family. Members with media permission can view and download them within the original release and expiry rules. Orders, payment details, credits and Studio Hub access stay private.</p>
<p>Everyone with an accepted account can use their own favourites and cart. The account manager can change permissions or remove members. You can leave in My Profile → My Family and keep your own purchases.</p>
<p class="meta">This invitation expires {{ $member->expires_at->format('j M Y') }}. One family per account.</p>
@auth
@if(!auth()->user()->hasVerifiedEmail())
<p>Verify your account email before accepting.</p><a class="button" href="{{ route('customer.verification.notice') }}">Verify email</a>
@elseif(strtolower(auth()->user()->email) !== strtolower($member->email))
<p role="alert">Sign in with the email address that received this invitation.</p><a class="button secondary" href="{{ route('customer.profile.edit') }}">My Profile</a>
@else
<form method="POST" action="{{ route('customer.family.accept', ['member' => $member, 'token' => $token]) }}">@csrf<button class="button">Accept &amp; join family</button></form>
@endif
@else
<p>Use the email address that received this invitation.</p>
<a class="button" href="{{ route('login') }}">Sign in</a>
<a class="button secondary" href="{{ route('customer.register') }}">Create account</a>
@endauth
</div></div></section>
@endsection
