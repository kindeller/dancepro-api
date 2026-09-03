@extends('layouts.crew')

@section('content')
<div class="page-heading"><div><div class="crew-hub-brand"><img src="{{ asset('images/brand/dancepro-icon-blue.png') }}" alt=""><span>CREW HUB</span></div><h1>My Chat</h1><p class="muted">Event updates and conversations with your crew.</p></div></div>

<div class="chat-shell {{ $selectedChat ? 'has-selection' : '' }}">
    <aside class="chat-sidebar">
        <div class="chat-sidebar-top">
            <details class="chat-new">
                <summary>+ New chat</summary>
                <div class="chat-new-panel">
                    <form method="POST" action="{{ route('crew.chat.start') }}">
                        @csrf
                        <label>Choose a crew member<select name="recipient_profile_uuid" required><option value="">Select someone…</option>@foreach($crew as $profile)<option value="{{ $profile->uuid }}">{{ $profile->preferred_name ?: $profile->user->name }}</option>@endforeach</select></label>
                        <button type="submit">Open chat</button>
                    </form>
                </div>
            </details>
            <input class="chat-search" type="search" placeholder="Search chats" aria-label="Search chats" data-chat-search>
        </div>
        <nav class="chat-tabs" aria-label="Chat filters">
            @foreach(['all'=>'All','unread'=>'Unread','upcoming'=>'Upcoming','events'=>'Events','direct'=>'Direct'] as $value=>$label)<a class="{{ $filter === $value ? 'active' : '' }}" href="{{ route('crew.chat.index',['filter'=>$value]) }}">{{ $label }}</a>@endforeach
        </nav>
        <div class="chat-list" data-chat-list>
            @forelse($chats as $chat)
                @php
                    $url = $chat['kind'] === 'event'
                        ? route('crew.chat.event', $chat['model'])
                        : route('crew.chat.direct', $chat['model']);
                @endphp
                <a class="chat-list-item {{ $selectedChat && $selectedChat['kind'] === $chat['kind'] && $selectedChat['model']->is($chat['model']) ? 'active' : '' }}" href="{{ $url }}?filter={{ $filter }}" data-chat-name="{{ strtolower($chat['title']) }}">
                    <span class="chat-avatar {{ $chat['kind'] }}">{{ $chat['kind'] === 'event' ? 'EVENT' : strtoupper(substr($chat['title'],0,2)) }}</span>
                    <span class="chat-preview"><strong>{{ $chat['title'] }}</strong><span>@if($chat['is_upcoming'] && !$chat['model']->messages->count())Upcoming · @endif{{ Str::limit($chat['subtitle'],45) }}</span></span>
                    <span class="chat-list-meta"><span>{{ $chat['activity_at']?->diffForHumans() }}</span>@if($chat['unread_count'])<span class="unread-count">{{ $chat['unread_count'] }}</span>@endif</span>
                </a>
            @empty
                <div class="empty-state card"><strong>No chats here yet</strong><p class="muted">Upcoming event chats appear seven days before the event.</p></div>
            @endforelse
        </div>
    </aside>

    <section class="chat-window">
        @if($selectedChat)
            @php
                $selected = $selectedChat['model'];
                $isEvent = $selectedChat['kind'] === 'event';
                $other = $isEvent ? null : $selected->participants->firstWhere('id','!=',auth()->id());
                $messages = $selected->messages->sortBy('created_at');
            @endphp
            <div class="chat-window-header"><a class="chat-back" style="display:none" href="{{ route('crew.chat.index',['filter'=>$filter]) }}">‹ Back</a><strong>{{ $isEvent ? $selected->name : ($other?->crewProfile?->preferred_name ?: $other?->name) }}</strong><p>{{ $isEvent ? 'Event chat · assigned crew' : 'Direct chat' }}</p></div>
            <div class="chat-messages" data-chat-messages>
                @forelse($messages as $message)
                    <div class="chat-bubble {{ $message->author_user_id === auth()->id() ? 'mine' : '' }} {{ ($message->message_type ?? null) === 'announcement' ? 'announcement' : '' }}">
                        <strong>{{ $message->author->name }}{{ ($message->message_type ?? null) === 'announcement' ? ' · IMPORTANT UPDATE' : '' }}</strong>
                        <p>{{ $message->body }}</p><time>{{ $message->created_at->format('D j M, g:i a') }}</time>
                    </div>
                @empty
                    <div class="empty-state"><strong>This chat is ready</strong><p class="muted">Send the first message below.</p></div>
                @endforelse
            </div>
            <form class="chat-compose" method="POST" action="{{ $isEvent ? route('crew.chat.event.store',$selected) : route('crew.chat.direct.store',$selected) }}">
                @csrf<textarea name="body" required maxlength="5000" placeholder="Write a message…" aria-label="Message"></textarea><button type="submit">Send</button>
            </form>
        @else
            <div class="chat-welcome"><h2>Select a chat</h2><p>Choose a conversation or start a new direct chat.</p></div>
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">const chatSearch=document.querySelector('[data-chat-search]');chatSearch?.addEventListener('input',()=>{const term=chatSearch.value.trim().toLowerCase();document.querySelectorAll('[data-chat-name]').forEach(item=>item.hidden=!item.dataset.chatName.includes(term));});const chatMessages=document.querySelector('[data-chat-messages]');if(chatMessages)requestAnimationFrame(()=>chatMessages.scrollTop=chatMessages.scrollHeight);const newChat=document.querySelector('.chat-new');document.addEventListener('click',event=>{if(newChat?.open&&!newChat.contains(event.target))newChat.removeAttribute('open');});document.addEventListener('keydown',event=>{if(event.key==='Escape'&&newChat?.open){newChat.removeAttribute('open');newChat.querySelector('summary')?.focus();}});</script>
@endpush
