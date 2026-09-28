@extends('layouts.public', ['title' => 'My Messages'])
@push('styles')@include('customers.messages._styles')@endpush
@section('content')
@include('customers.messages._header')
<section class="section"><div class="container">
    @if(session('status'))<div class="card card-body message-notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="card card-body message-notice" role="alert">{{ $errors->first() }}</div>@endif
    @if($messageTab === 'new')
        <div class="card card-body"><h2>New message</h2><p class="meta">Send us a question. Your message and our replies will stay together in Conversations.</p>@include('customers.messages._form')</div>
    @elseif($messageTab === 'conversations')
        <div class="inbox-list">@forelse($conversations as $conversation)
            @php($unread = $conversation->latest_staff_message_at && (!$conversation->customer_read_at || $conversation->customer_read_at->lt($conversation->latest_staff_message_at)))
            <a class="card card-body inbox-row {{ $unread ? 'unread' : '' }}" href="{{ route('customer.messages.show', $conversation) }}"><div class="inbox-meta"><span>{{ $conversation->last_activity_at?->format('j M Y, g:i a') }}</span>@if($unread)<strong>Unread reply</strong>@endif<span>{{ $conversation->status === 'pending' ? 'Awaiting DancePro reply' : 'Answered' }}</span></div><h2>{{ $conversation->subject }}</h2><p class="meta">{{ \Illuminate\Support\Str::limit($conversation->message, 160) }}</p></a>
        @empty<div class="card inbox-empty"><h2>No conversations yet</h2><p>Send us a message whenever you need help with your account, events or orders.</p><a class="button" href="{{ route('customer.messages.index', ['tab' => 'new']) }}">New message</a></div>@endforelse</div>
        {{ $conversations->links() }}
    @else
        <div class="inbox-list">@forelse($notifications as $notification)
            <article class="card card-body inbox-row {{ !$notification->read_at ? 'unread' : '' }}"><div class="inbox-meta"><span>{{ $notification->occurred_at->format('j M Y, g:i a') }}</span>@if(!$notification->read_at)<strong>Unread</strong>@endif</div><h2>{{ $notification->subject }}</h2><div class="inbox-body">{{ $notification->body }}</div>
                @if($notification->email_status)<p class="message-hint">Email: {{ match($notification->email_status) { 'sent' => 'sent', 'queued' => 'queued', 'failed' => 'delivery failed — this update is available here', 'suppressed' => 'not sent', 'simulated' => 'saved to test inbox', default => $notification->email_status } }}@if($notification->email_sent_at) · {{ $notification->email_sent_at->format('j M Y, g:i a') }}@endif</p>@endif
                <div class="inbox-actions">@if($notification->action_url)<a class="button secondary" href="{{ $notification->action_url }}">{{ $notification->action_label ?: 'View details' }}</a>@endif @if(!$notification->read_at)<form method="post" action="{{ route('customer.messages.read', $notification) }}">@csrf<button class="button secondary">Mark as read</button></form>@endif</div>
            </article>
        @empty<div class="card inbox-empty"><h2>You’re up to date</h2><p>Your order confirmations, gallery announcements and media updates will appear here.</p></div>@endforelse</div>
        {{ $notifications->links() }}
    @endif
</div></section>
@include('customers._tab-scroll')
@endsection
