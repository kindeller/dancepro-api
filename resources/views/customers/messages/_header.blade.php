<section class="hero"><div class="container">@include('customers._heading', ['heading' => 'My Messages', 'description' => 'Your updates and conversations with DancePro, together.'])</div></section>
<nav class="section-tabs" aria-label="My Messages sections"><div class="container">
    <a href="{{ route('customer.messages.index', ['tab' => 'notifications']) }}" @if($messageTab === 'notifications') aria-current="page" @endif>Notifications @if($inboxCounts['notifications'])<span class="inbox-badge" aria-label="{{ $inboxCounts['notifications'] }} unread notifications">{{ $inboxCounts['notifications'] }}</span>@endif</a>
    <a href="{{ route('customer.messages.index', ['tab' => 'conversations']) }}" @if($messageTab === 'conversations') aria-current="page" @endif>Conversations @if($inboxCounts['conversations'])<span class="inbox-badge" aria-label="{{ $inboxCounts['conversations'] }} unread conversations">{{ $inboxCounts['conversations'] }}</span>@endif</a>
    <a href="{{ route('customer.messages.index', ['tab' => 'new']) }}" @if($messageTab === 'new') aria-current="page" @endif>New message</a>
</div></nav>
