<section class="hero"><div class="container">@include('customers._heading', ['heading' => 'My Messages', 'description' => 'Your updates and conversations with DancePro, together.'])</div></section>
<nav class="section-tabs message-tabs" aria-label="My Messages sections"><div class="container">
    <a href="{{ route('customer.messages.index', ['tab' => 'notifications']) }}" @if($messageTab === 'notifications') aria-current="page" @endif>@if($inboxCounts['notifications'])<span class="inbox-badge" data-unread-count="notifications" aria-label="{{ $inboxCounts['notifications'] }} unread notifications">{{ $inboxCounts['notifications'] }}</span>@endif Notifications</a>
    <a href="{{ route('customer.messages.index', ['tab' => 'conversations']) }}" @if($messageTab === 'conversations') aria-current="page" @endif>@if($inboxCounts['conversations'])<span class="inbox-badge" data-unread-count="conversations" aria-label="{{ $inboxCounts['conversations'] }} unread conversations">{{ $inboxCounts['conversations'] }}</span>@endif Conversations</a>
    <a href="{{ route('customer.messages.index', ['tab' => 'new']) }}" @if($messageTab === 'new') aria-current="page" @endif>New message</a>
</div></nav>

@if(auth()->user()->canAccessStudioHub())
<div class="container" style="display:flex;justify-content:flex-end;padding-top:20px"><a class="button secondary" href="{{ route('customer.studio-hub', ['tab' => 'messages']) }}">Studio messages</a></div>
@endif
