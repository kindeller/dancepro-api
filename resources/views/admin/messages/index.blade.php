@extends('layouts.admin', ['title' => 'Message Centre', 'heading' => 'Message Centre', 'subheading' => 'Customer, studio and crew conversations in one place.'])
@push('styles')
@include('customers.messages._styles')
<style>
.message-centre-nav,.message-centre-tools{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:18px}.message-centre-nav{border-bottom:1px solid var(--line);gap:4px}.message-centre-nav a{display:inline-flex;align-items:center;gap:8px;padding:14px 18px;border:1px solid transparent;border-radius:6px 6px 0 0;font-weight:800;color:var(--muted);text-decoration:none;margin-bottom:-1px}.message-centre-nav a[aria-current=page]{background:white;color:var(--brand-dark);border-color:var(--line);border-bottom-color:white}.message-centre-nav a:hover{color:var(--brand-strong)}.message-centre-tools form{display:flex;gap:10px;flex:1;align-items:end;flex-wrap:wrap}.message-centre-tools input[type=search]{width:100%}.message-centre-tools .message-search-label{flex:1;min-width:180px}.message-centre-grid{display:grid;grid-template-columns:minmax(240px,320px) minmax(0,1fr);gap:20px;align-items:start}.message-centre-list{display:grid;gap:8px;max-height:75vh;overflow:auto}.message-centre-list a{display:block;padding:15px;border:1px solid var(--line);border-radius:8px;background:white;text-decoration:none;overflow-wrap:anywhere}.message-centre-list a[aria-current=page]{background:#eaf8fd;border-color:var(--brand)}.message-centre-list small{display:block;color:var(--muted);margin-top:5px}.message-centre-detail{min-width:0}.message-centre-detail h2{margin-top:0}.message-centre-detail .message-bubble{overflow-wrap:anywhere}.centre-compose{display:grid;gap:16px;margin-top:22px}.centre-compose details{padding:12px;background:var(--soft);border-radius:8px}.centre-compose details label{display:block;margin:12px 0}.centre-message{margin:14px 0;padding:18px;border:1px solid var(--line);border-radius:10px}.centre-message.sent{background:#f0f9fc}.centre-message-body{overflow-wrap:anywhere}.centre-message-body img{max-width:100%}.centre-recipient{padding:14px;background:var(--soft);border-radius:8px;margin:14px 0}.centre-recipient small{display:block}.centre-history{max-height:60vh;overflow:auto}.centre-reactions{display:flex;gap:5px;align-items:center;flex-wrap:wrap}.centre-reactions button{padding:3px 8px;min-height:30px}.centre-reactions button[aria-pressed=true]{background:#d7effa}
@media(max-width:550px){.message-centre-tools form{flex-basis:100%}.message-centre-tools .message-search-label{flex-basis:100%}}
@media(max-width:1000px){.message-centre-grid{grid-template-columns:1fr}.message-centre-list{max-height:280px}}
</style>
@endpush
@section('content')
<nav class="message-centre-nav" aria-label="Message audience">
@foreach(['all' => 'All', 'customer' => 'Customers', 'studio' => 'Studios', 'crew' => 'Crew'] as $key => $label)
@if($key !== 'studio' || auth()->user()->can('manageScheduling'))
<a href="{{ route('admin.messages.index', ['channel' => $key]) }}" @if($channel === $key) aria-current="page" @endif>{{ $label }} @include('layouts.partials.notification-badge', ['count'=>$adminNavigationIndicators[['all'=>'messages','customer'=>'support_pending','studio'=>'studio_messages','crew'=>'crew_messages'][$key]], 'key'=>'message-channel-'.$key, 'label'=>'messages needing attention'])</a>
@endif
@endforeach
</nav>
@if(auth()->user()->canAccessCrew())<p><a class="button secondary" href="{{ route('crew.chat.index') }}">Open Crew Hub chat</a></p>@endif
<div class="message-centre-tools">
<form data-live-message-search method="get" action="{{ route('admin.messages.index') }}">
<input type="hidden" name="channel" value="{{ $channel }}"><input type="hidden" name="mode" value="{{ $mode }}">
<label class="message-search-label">{{ $mode === 'new' ? 'Find a recipient' : 'Search conversations' }}<input type="search" name="q" value="{{ $q }}" placeholder="Name, email, ID{{ in_array($channel, ['all', 'customer']) ? ' or order number' : '' }}" maxlength="200"></label>
@if($mode !== 'new')<label>Show<select name="status">@foreach((in_array($channel, ['all', 'customer', 'crew']) ? ['all'=>'All messages','pending'=>'Awaiting reply','approved'=>'Approved requests','declined'=>'Declined requests','resolved'=>'Answered'] : ['all'=>'All messages','unread'=>'Unread']) as $key=>$label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select></label>@endif
<button>Search</button><span class="muted" data-search-status role="status" aria-live="polite"></span>
</form>
<a class="button {{ $mode === 'new' ? 'secondary' : '' }}" href="{{ route('admin.messages.index', ['channel' => $channel, 'mode' => $mode === 'new' ? 'inbox' : 'new']) }}">{{ $mode === 'new' ? 'Back to inbox' : 'New message' }}</a>
</div>
<div class="message-centre-grid">
<aside aria-label="{{ $mode === 'new' ? 'Recipients' : 'Conversations' }}">
@include('admin.messages._list')
</aside>
<section class="card card-pad message-centre-detail">
@if($mode === 'new')
<h2>New message</h2>
@if($recipient)
<div class="centre-recipient"><strong>To: {{ $recipient->name }}</strong><small>{{ $channel === 'studio' ? $recipient->contact_email : $recipient->email }}</small></div>
@include('admin.messages._compose', ['targetRecipient' => (string) $recipient->getRouteKey()])
@else<p class="muted">Choose a recipient from the search results to write your message.</p>@endif
@elseif(in_array($channel, ['customer', 'crew']) && isset($conversation))
<div class="toolbar">@if($conversation->user?->type === 'customer')<a href="{{ route('admin.customers.show', [$conversation->user, 'tab'=>'messages']) }}">Customer account</a>@endif
@if($conversation->order)<a href="{{ route('admin.orders.show', $conversation->order) }}">Order {{ $conversation->order->displayNumber() }}</a>@endif</div>
@include('customers.messages._thread')
@if($channel === 'customer')
@include('admin.messages._support-decision', ['supportRequest'=>$conversation])
@endif
@elseif($channel === 'studio' && isset($studio))
@include('admin.messages._studio')
@else
<h2>Select a conversation</h2><p class="muted">Read received messages, review earlier replies or choose New message.</p>
@endif
</section>
</div>
@endsection
@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
const searchForm = document.querySelector('[data-live-message-search]');
const searchInput = searchForm.querySelector('[name=q]');
const searchStatus = searchForm.querySelector('[data-search-status]');
let searchTimer, searchController, searchVersion = 0;
async function updateSearch() {
    const version = ++searchVersion;
    searchController?.abort();
    searchController = new AbortController();
    const url = new URL(searchForm.action);
    new FormData(searchForm).forEach((value, key) => url.searchParams.set(key, value));
    searchStatus.textContent = 'Searching…';
    try {
        const response = await fetch(url, {signal: searchController.signal, headers: {'X-Requested-With':'XMLHttpRequest'}});
        if (!response.ok) throw new Error('Search failed');
        const documentResult = new DOMParser().parseFromString(await response.text(), 'text/html');
        if (version !== searchVersion) return;
        const results = documentResult.querySelector('[data-message-results]');
        if (!results) throw new Error('Search unavailable');
        document.querySelector('[data-message-results]').replaceWith(results);
        searchStatus.textContent = 'Results updated';
    } catch (error) {
        if (error.name !== 'AbortError' && version === searchVersion) searchStatus.textContent = 'Search unavailable. Use Search to retry.';
    }
}
searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    ++searchVersion;
    searchController?.abort();
    searchTimer = setTimeout(updateSearch, 250);
});
searchForm.querySelector('[name=status]')?.addEventListener('change', updateSearch);
searchForm.addEventListener('submit', event => {event.preventDefault();clearTimeout(searchTimer);updateSearch();});
document.querySelectorAll('.centre-compose').forEach(form=>form.addEventListener('submit',()=>{const button=form.querySelector('[type=submit]');button.disabled=true;button.textContent='Sending…';}));
</script>
@endpush
