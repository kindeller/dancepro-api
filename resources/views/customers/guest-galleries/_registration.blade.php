<dialog class="registration-dialog" id="{{ $registrationId }}" aria-labelledby="{{ $registrationId }}-title" @if($errors->any() && old('registration_event') === $registrationKey) data-open-on-load @endif>
    <div class="registration-dialog-head"><h2 id="{{ $registrationId }}-title">Register for updates</h2><button type="button" class="button secondary" data-register-close aria-label="Close registration">×</button></div>
    <div class="registration-event">
        <img class="registration-logo" src="{{ $registrationLogo ?? asset('images/brand/dancepro-media-heading.png') }}" alt="">
        <div class="registration-event-copy"><h3>{{ $registrationTitle }}</h3>@if($registrationDate)<p class="muted">{{ $registrationDate->format('j F Y') }}</p>@endif</div>
    </div>
    <p>{{ $studioSubscription ? 'Register once and we’ll notify you when each new event gallery for this studio is released. You can unsubscribe at any time.' : 'Register to be notified when this event’s gallery is ready.' }}</p>
    @if($errors->any() && old('registration_event') === $registrationKey)<p class="error" role="alert">{{ $errors->first() }}</p>@endif
    <form class="notify-form" method="post" action="{{ $registrationAction }}">@csrf
        <input type="hidden" name="registration_event" value="{{ $registrationKey }}">
        @guest
            <label>Email address<input type="email" name="email" value="{{ old('registration_event') === $registrationKey ? old('email') : '' }}" autocomplete="email" maxlength="255" required></label>
        @else
            <p>We’ll notify you in your account and by email at <strong>{{ auth()->user()->email }}</strong>.</p>
        @endguest
        @if($studioSubscription || ! auth()->check())
            <label class="choice"><input type="checkbox" name="notification_consent" value="1" required @checked(old('registration_event') === $registrationKey && old('notification_consent'))><span>{{ $studioSubscription ? 'Email me when new event galleries for this studio are released.' : 'Email me once when this event’s media is released.' }}</span></label>
        @endif
        <div class="actions"><button class="button" type="submit">Notify me</button><button class="button secondary" type="button" data-register-close>Cancel</button></div>
    </form>
</dialog>
