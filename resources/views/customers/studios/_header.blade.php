<section class="hero hub-hero"><div class="container hub-hero-grid">
    <div class="hub-identity">@if($studio->logoUrl())<img class="hub-studio-logo" src="{{ $studio->logoUrl() }}" alt="{{ $studio->name }} logo">@endif<div class="hub-identity-copy">@include('customers._heading', ['eyebrow' => 'Studio Hub', 'heading' => $studio->name, 'description' => 'Check every booking, complete what DancePro needs and keep your concert communication together.'])</div></div>
    @isset($hub)
    <div class="hub-summary" aria-label="Studio hub summary">
        <a class="hub-stat" href="{{ route('customer.studio-communications.index', [$studio, 'tab' => 'events']) }}#events"><strong>{{ $hub['events']->count() }}</strong><span>{{ Str::plural('event', $hub['events']->count()) }}</span></a>
        <a class="hub-stat" href="{{ route('customer.studio-communications.index', [$studio, 'tab' => 'events', 'actions' => 1]) }}#events"><strong>{{ $hub['actionCount'] }}</strong><span>actions remaining</span></a>
        <a class="hub-stat" href="{{ route('customer.studio-communications.index', [$studio, 'tab' => 'messages']) }}#messages"><strong>{{ $hub['messageCount'] }}</strong><span>{{ Str::plural('message', $hub['messageCount']) }}</span></a>
    </div>
    @endisset
</div></section>
