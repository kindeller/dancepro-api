<nav class="section-tabs hub-tabs" aria-label="Studio Hub sections"><div class="container">
    @foreach(['events' => 'Concert events', 'messages' => 'Studio messages', 'invoices' => 'Event invoices'] as $key => $label)
        <a href="{{ $key === 'invoices' ? route('customer.studio-invoices.index', $studio) : route('customer.studio-communications.index', [$studio, 'tab' => $key]) }}" @if($tab === $key) aria-current="page" @endif>
            @if($key === 'messages' && ($hub['messageCount'] ?? 0))
                <span class="inbox-badge" data-unread-count="studio" aria-label="{{ $hub['messageCount'] }} unread studio messages">{{ $hub['messageCount'] }}</span>
            @endif
            @if($key === 'invoices' && ($hub['invoiceCount'] ?? 0))
                <span class="inbox-badge" data-unpaid-invoices aria-label="{{ $hub['invoiceCount'] }} invoices awaiting payment">{{ $hub['invoiceCount'] }}</span>
            @endif
            {{ $label }}
        </a>
    @endforeach
</div></nav>
