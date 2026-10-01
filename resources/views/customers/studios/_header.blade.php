<section class="hero hub-hero"><div class="container hub-hero-grid">
    <div class="hub-identity">@if($studio->logoUrl())<img class="hub-studio-logo" src="{{ $studio->logoUrl() }}" alt="{{ $studio->name }} logo">@endif<div class="hub-identity-copy">@include('customers._heading', ['showMediaLogo' => false, 'eyebrow' => 'Studio Hub', 'heading' => $hubHeading ?? $studio->name, 'description' => $hubDescription ?? 'Check every booking, complete what DancePro needs and keep your concert communication together.'])</div></div>
    @isset($hub)
    <div class="hub-summary" aria-label="Studio hub summary">
        <a class="hub-stat" href="{{ route('customer.studio-communications.index', [$studio, 'tab' => 'events']) }}#events"><strong>{{ $hub['events']->count() + $hub['pendingEvents']->count() }}</strong><span>{{ Str::plural('event', $hub['events']->count() + $hub['pendingEvents']->count()) }}</span></a>
        <a class="hub-stat {{ ($hub['overdueCount'] ?? 0) ? 'has-overdue-actions' : '' }}" href="{{ route('customer.studio-communications.index', [$studio, 'tab' => 'events', 'actions' => 1]) }}#events"><strong>{{ $hub['actionCount'] }}</strong><span>actions remaining</span>@if($hub['overdueCount'] ?? 0)<span class="hub-stat-overdue">{{ $hub['overdueCount'] }} overdue</span>@endif</a>
        <a class="hub-stat" href="{{ route('customer.studio-communications.index', [$studio, 'tab' => 'messages']) }}#messages"><strong data-unread-count="studio" data-unread-keep-zero>{{ $hub['messageCount'] }}</strong><span>unread messages</span></a>
    </div>
    @endisset
</div></section>
