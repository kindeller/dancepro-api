<div class="account-heading">
    @if($showMediaLogo ?? true)
        <img class="account-heading-logo" src="{{ $headingImageUrl ?? asset('images/brand/dancepro-media-heading.png') }}" width="3508" height="2480" alt="" aria-hidden="true">
    @endif
    <div class="account-heading-copy">
        <div class="eyebrow">{{ $eyebrow ?? 'Your DancePro account' }}</div>
        <h1>{{ $heading }}</h1>
        <p class="lead">{{ $description }}</p>
    </div>
</div>
