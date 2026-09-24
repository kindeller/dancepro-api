@extends('layouts.public', ['title' => 'Finish your account', 'footerLabel' => 'DancePro Media'])
@section('content')
<section class="section"><div class="container" style="max-width:620px">
    <h1>Finish your account</h1>
    <p>Before using your media account, please review DancePro’s published terms and privacy policy.</p>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    <form method="POST" action="{{ route('customer.onboarding.store') }}" class="card card-body">@csrf
        <label class="choice"><input type="checkbox" name="policy_accepted" value="1" required><span>I have read and agree to the <a href="{{ config('customer.terms_url') }}" target="_blank" rel="noopener">Terms &amp; Conditions</a> and <a href="{{ config('customer.privacy_url') }}" target="_blank" rel="noopener">Privacy Policy</a>.</span></label>
        @error('policy_accepted')<p role="alert">{{ $message }}</p>@enderror
        <button class="button">Continue to My Media</button>
    </form>
</div></section>
@endsection
