<nav class="section-tabs hub-tabs" aria-label="Studio Hub sections"><div class="container">
    @foreach(['events' => 'Events', 'messages' => 'Messages', 'invoices' => 'Invoices'] as $key => $label)
        <a href="{{ $key === 'invoices' ? route('customer.studio-invoices.index', $studio) : route('customer.studio-communications.index', [$studio, 'tab' => $key]) }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</div></nav>
