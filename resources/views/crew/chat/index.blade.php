@extends('layouts.crew')

@section('content')
<div class="page-heading"><div>@include('crew._brand')
<h1>My Chat</h1><p class="muted">Event updates and conversations with your crew.</p></div></div>

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
                    @if($chat['kind'] === 'event')
                        <x-event-artwork :url="$chat['model']->crewLogoUrl()" :name="$chat['title']" style="width:44px;height:44px" />
                    @else
                        <span class="chat-avatar direct">{{ strtoupper(substr($chat['title'],0,2)) }}</span>
                    @endif
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
            <div class="chat-window-header"><a class="chat-back" style="display:none" href="{{ route('crew.chat.index',['filter'=>$filter]) }}">‹ Back</a><strong>{{ $isEvent ? $selected->crewChatTitle() : ($other?->crewProfile?->preferred_name ?: $other?->name) }}</strong><p>{{ $isEvent ? 'Event chat · assigned crew' : 'Direct chat' }}</p></div>
            <div class="chat-messages" data-chat-messages>
                @forelse($messages as $message)
                    <div data-message-uuid="{{ $message->uuid }}" @if($message->author_user_id !== auth()->id()) data-reactable tabindex="0" aria-label="Message from {{ $message->author->name }}. Hold to react." @endif class="chat-bubble {{ $message->author_user_id === auth()->id() ? 'mine' : '' }} {{ ($message->message_type ?? null) === 'announcement' ? 'announcement' : '' }}">
                        <strong>{{ $message->author->name }}{{ ($message->message_type ?? null) === 'announcement' ? ' · IMPORTANT UPDATE' : '' }}</strong>
                        @if(filled($message->body))<div>{!! app(\App\Features\Tracking\Services\EmailContentFormatter::class)->html($message->body) !!}</div>@endif
                        @if(filled($message->attachment_path))
                            @php($attachmentUrl = $message->attachmentUrl())
                            @if(Str::startsWith((string) $message->attachment_mime, 'image/'))
                                <a href="{{ $attachmentUrl }}"><img src="{{ $attachmentUrl }}" alt="{{ $message->attachment_name ?: 'Chat attachment' }}" style="display:block;max-width:min(100%,420px);max-height:320px;border-radius:10px;margin:.65rem 0;object-fit:contain"></a>
                            @endif
                            <a href="{{ $attachmentUrl }}" download>{{ $message->attachment_name ?: 'Download attachment' }}</a>
                        @endif
                        @php($reactions = collect(\App\Features\Chat\Services\MessageReactions::summary($message, auth()->id()))->keyBy('reaction'))
                        <div class="reaction-summary" aria-label="Reactions" data-reaction-summary>
                            @foreach($reactions as $key=>$state)
                                <span>{{ ['thumbs_up'=>'👍','heart'=>'❤️','laugh'=>'😂','angry'=>'😡'][$key] ?? $key }} {{ $state['count'] }}</span>
                            @endforeach
                        </div>
                        @if($message->author_user_id !== auth()->id())
                        <div class="message-reactions" hidden role="group" aria-label="Message reactions" data-reaction-url="{{ route('crew.chat.reaction.update', ['chatId'=>$selected->uuid,'message'=>$message->uuid]) }}">
                            @foreach(['thumbs_up'=>['👍','Thumbs up'],'heart'=>['❤️','Heart'],'laugh'=>['😂','Laugh'],'angry'=>['😡','Angry']] as $key=>$reaction)
                                <button type="button" data-reaction="{{ $key }}" aria-label="{{ $reaction[1] }}" aria-pressed="{{ data_get($reactions->get($key),'selected',false) ? 'true' : 'false' }}" title="{{ $reaction[1] }}"><span>{{ $reaction[0] }}</span> <span data-reaction-count>{{ data_get($reactions->get($key),'count',0) ?: '' }}</span></button>
                            @endforeach
                        </div>
                        @endif
                        <time>{{ $message->created_at->format('D j M, g:i a') }}</time>
                    </div>
                @empty
                    <div class="empty-state"><strong>This chat is ready</strong><p class="muted">Send the first message below.</p></div>
                @endforelse
            </div>
            <form class="chat-compose" method="POST" enctype="multipart/form-data" action="{{ $isEvent ? route('crew.chat.event.store',$selected) : route('crew.chat.direct.store',$selected) }}">
                @csrf<textarea name="body" maxlength="10000" placeholder="Write a message…" aria-label="Message"></textarea><label title="Attach a photo or file"><span class="sr-only">Attach a photo or file</span><input type="file" name="attachment" accept="image/jpeg,image/png,image/webp,application/pdf,.doc,.docx,.xls,.xlsx,.txt,.csv"></label><button type="submit">Send</button>
            </form>
        @else
            <div class="chat-welcome"><h2>Select a chat</h2><p>Choose a conversation or start a new direct chat.</p></div>
        @endif
    </section>
</div>
@endsection

@push('scripts')
<style nonce="{{ request()->attributes->get('csp_nonce') }}">
.message-reactions[hidden]{display:none}.reaction-summary{display:flex;gap:6px;flex-wrap:wrap}.reaction-summary span{background:#eaf8fd;color:#243b48;border-radius:12px;padding:2px 7px;margin-top:6px}[data-reactable]{-webkit-touch-callout:none}.message-reactions{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px}.message-reactions button{padding:5px 9px;border:1px solid #ccd5dc;border-radius:16px;background:#fff;color:#243b48;font-size:16px;cursor:pointer;min-height:36px}.message-reactions button[aria-pressed="true"]{background:#d7effa;border:2px solid #007db3}.message-reactions button:disabled{opacity:.55}

.reaction-overlay{position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.54);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px)}
.reaction-focus{position:absolute;display:flex;flex-direction:column;align-items:flex-start;gap:12px}
.reaction-focus .message-reactions{margin:0;padding:6px 8px;gap:0;flex-wrap:nowrap;border-radius:40px;background:#333;box-shadow:0 6px 24px #0004}
.reaction-focus .message-reactions button{width:52px;height:52px;padding:0;border:0;border-radius:50%;background:transparent;font-size:32px;line-height:1;color:white}
.reaction-focus .message-reactions button[aria-pressed="true"]{background:#ffffff33;border:0}
.reaction-focus [data-reaction-count]{display:none}
.reaction-message-preview{box-sizing:border-box;width:100%;padding:16px;border-radius:20px;background:#333;color:#fff;font-size:17px;line-height:1.45;overflow:auto;white-space:pre-wrap;overflow-wrap:anywhere}
</style>
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
let reactionOverlay=null;
const closeReactionPickers=()=>{
    document.querySelectorAll('[data-reaction-url]').forEach(group=>{
        group.hidden=true;
        if(group.reactionBubble)group.reactionBubble.append(group);
    });
    reactionOverlay?.remove();reactionOverlay=null;
};
document.querySelectorAll('[data-reactable]').forEach(bubble=>{
    let timer, origin, held=false;
    const cancel=()=>clearTimeout(timer);
    const open=()=>{
        closeReactionPickers();
        const group=bubble.querySelector('[data-reaction-url]');
        const bounds=bubble.getBoundingClientRect();
        const width=Math.min(320,window.innerWidth-32);
        reactionOverlay=document.createElement('div');reactionOverlay.className='reaction-overlay';
        reactionOverlay.setAttribute('role','dialog');reactionOverlay.setAttribute('aria-label','Message reactions');reactionOverlay.setAttribute('aria-modal','true');
        const focus=document.createElement('div');focus.className='reaction-focus';
        focus.style.left=`${Math.max(16,Math.min(bounds.left,window.innerWidth-width-16))}px`;
        const top=Math.max(24,Math.min(bounds.top-76,window.innerHeight-200));
        focus.style.top=`${top}px`;focus.style.width=`${width}px`;
        const preview=bubble.cloneNode(true);
        const originals=[bubble,...bubble.querySelectorAll('*')];
        const copies=[preview,...preview.querySelectorAll('*')];
        originals.forEach((source,index)=>{
            const computed=getComputedStyle(source);
            copies[index].style.cssText=Array.from(computed).map(name=>`${name}:${computed.getPropertyValue(name)};`).join('');
            copies[index].removeAttribute('id');copies[index].removeAttribute('tabindex');
        });
        preview.removeAttribute('data-reactable');preview.setAttribute('aria-hidden','true');
        preview.querySelector('[data-reaction-url]')?.remove();
        Object.assign(preview.style,{position:'fixed',left:`${bounds.left}px`,top:`${bounds.top}px`,width:`${bounds.width}px`,height:`${bounds.height}px`,margin:'0',boxSizing:'border-box',pointerEvents:'none',transform:'none'});
        const pickerTop=bounds.top-76>=16?bounds.top-76:bounds.bottom+8;
        focus.style.top=`${Math.max(16,Math.min(pickerTop,window.innerHeight-80))}px`;
        group.reactionBubble=bubble;group.hidden=false;
        focus.append(group);reactionOverlay.append(preview,focus);document.body.append(reactionOverlay);
        reactionOverlay.addEventListener('click',event=>{if(event.target===reactionOverlay)closeReactionPickers();});
        held=true;
    };
    bubble.addEventListener('pointerdown',event=>{
        if(event.button!==0||event.target.closest('button'))return;
        held=false;origin={x:event.clientX,y:event.clientY};
        timer=setTimeout(open,500);
    });
    bubble.addEventListener('pointermove',event=>{if(origin&&Math.hypot(event.clientX-origin.x,event.clientY-origin.y)>10)cancel();});
    ['pointerup','pointercancel','pointerleave'].forEach(name=>bubble.addEventListener(name,cancel));
    bubble.addEventListener('click',event=>{if(held&&!event.target.closest('button')){event.preventDefault();event.stopPropagation();held=false;}},true);
    bubble.addEventListener('contextmenu',event=>event.preventDefault());
    bubble.addEventListener('keydown',event=>{if(event.target===bubble&&(event.key===' '||event.key==='Enter')){event.preventDefault();open();reactionOverlay.querySelector('button').focus();}});
});
document.addEventListener('pointerdown',event=>{if(!event.target.closest('[data-reactable], [data-reaction-url]'))closeReactionPickers();});
document.addEventListener('keydown',event=>{if(event.key==='Escape')closeReactionPickers();});
window.addEventListener('resize',closeReactionPickers);
document.querySelector('[data-chat-messages]')?.addEventListener('scroll',closeReactionPickers);
document.querySelectorAll('[data-reaction-url]').forEach(group=>group.addEventListener('click',async event=>{
    const button=event.target.closest('[data-reaction]'); if(!button||button.disabled)return;
    const buttons=[...group.querySelectorAll('button')];
    const reaction=button.getAttribute('aria-pressed')==='true'?null:button.dataset.reaction;
    buttons.forEach(item=>item.disabled=true);
    try {
        const response=await fetch(group.dataset.reactionUrl,{method:'PUT',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token())},body:JSON.stringify({reaction})});
        if(!response.ok)throw new Error('save failed');
        const result=await response.json();
        const emoji={thumbs_up:'👍',heart:'❤️',laugh:'😂',angry:'😡'};
        const summary=group.reactionBubble.querySelector('[data-reaction-summary]');
        summary.replaceChildren(...result.data.reactions.map(state=>{const item=document.createElement('span');item.textContent=`${emoji[state.reaction]} ${state.count}`;return item;}));
        closeReactionPickers();

        buttons.forEach(item=>{const state=result.data.reactions.find(value=>value.reaction===item.dataset.reaction);item.setAttribute('aria-pressed',state?.selected?'true':'false');item.querySelector('[data-reaction-count]').textContent=state?.count||'';});
    }catch(error){alert('Your reaction could not be saved. Please try again.');}
    finally{buttons.forEach(item=>item.disabled=false);}
}));
</script>
<script nonce="{{ request()->attributes->get('csp_nonce') }}">const chatSearch=document.querySelector('[data-chat-search]');chatSearch?.addEventListener('input',()=>{const term=chatSearch.value.trim().toLowerCase();document.querySelectorAll('[data-chat-name]').forEach(item=>item.hidden=!item.dataset.chatName.includes(term));});const chatMessages=document.querySelector('[data-chat-messages]');if(chatMessages)requestAnimationFrame(()=>chatMessages.scrollTop=chatMessages.scrollHeight);const newChat=document.querySelector('.chat-new');document.addEventListener('click',event=>{if(newChat?.open&&!newChat.contains(event.target))newChat.removeAttribute('open');});document.addEventListener('keydown',event=>{if(event.key==='Escape'&&newChat?.open){newChat.removeAttribute('open');newChat.querySelector('summary')?.focus();}});</script>
@if($selectedChat && $selectedChat['kind'] === 'event')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
    const list = document.querySelector('[data-chat-messages]');
    const known = new Set(Array.from(list.querySelectorAll('[data-message-uuid]'), item => item.dataset.messageUuid));
    let checking = false;
    async function checkProgrammeUpdates() {
        if (document.hidden || checking) return;
        checking = true;
        try {
            const response = await fetch(@json(route('crew.chat.programme-updates', $selectedChat['model'])), {headers: {'Accept': 'application/json'}, cache: 'no-store'});
            if (!response.ok) return;
            const result = await response.json();
            const atBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 80;
            for (const message of result.data) {
                if (known.has(message.uuid)) continue;
                const bubble = document.createElement('div');
                bubble.className = 'chat-bubble';
                bubble.dataset.messageUuid = message.uuid;
                const author = document.createElement('strong'); author.textContent = message.author + ' · PROGRAMME UPDATE';
                const body = document.createElement('p'); body.textContent = message.body;
                const time = document.createElement('small'); time.textContent = new Date(message.created_at).toLocaleString();
                bubble.append(author, body, time); list.append(bubble); known.add(message.uuid);
            }
            if (atBottom) list.scrollTop = list.scrollHeight;
        } catch { /* Keep the existing conversation and draft available offline. */ }
        finally { checking = false; }
    }
    setInterval(checkProgrammeUpdates, 5000);
    document.addEventListener('visibilitychange', checkProgrammeUpdates);
})();
</script>
@endif
@endpush
