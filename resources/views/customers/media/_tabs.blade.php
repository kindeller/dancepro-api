<nav class="section-tabs" aria-label="My Media sections"><div class="container">
    <a href="{{ route('customer.media.index', ['tab' => 'events']) }}" @if($mediaTab === 'events') aria-current="page" @endif>My Events</a>
    <a href="{{ route('customer.media.index', ['tab' => 'purchases']) }}" @if($mediaTab === 'purchases') aria-current="page" @endif>My Purchases</a>
    <a href="{{ route('customer.media.orders.index') }}" @if($mediaTab === 'orders') aria-current="page" @endif>My Orders</a>
</div></nav>
