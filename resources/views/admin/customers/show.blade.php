@extends('layouts.admin', ['title'=>$customer->name,'heading'=>$customer->name,'subheading'=>'Customer account #'.$customer->id.' · '.$customer->email])
@section('content')
@php
    $money = fn (int $amount) => '$'.number_format($amount / 100, 2);
@endphp
<style>
.customer-tab-groups{display:flex;gap:18px;margin:20px 0;overflow-x:auto;border-bottom:1px solid var(--line)}.customer-tab-group{display:flex;align-items:flex-end;gap:4px;flex:0 0 auto}.customer-tab-label{padding:0 5px 12px 0;color:var(--muted);font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.customer-tabs{display:flex;gap:4px}.customer-tabs a{padding:11px 13px;border-radius:7px 7px 0 0;color:var(--muted);font-weight:800;white-space:nowrap;text-decoration:none}.customer-tabs a:hover{background:var(--soft);color:var(--brand-strong);text-decoration:none}.customer-tabs a.active{margin-bottom:-1px;background:#fff;color:var(--brand-strong);border:1px solid var(--line);border-bottom-color:#fff}.account-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.account-shortcuts{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:18px 0}.account-shortcut{display:block;padding:14px;border:1px solid var(--line);border-radius:10px;background:var(--panel);color:var(--ink);font-weight:800;text-decoration:none}.account-shortcut:hover{border-color:var(--brand);color:var(--brand-strong);text-decoration:none;transform:translateY(-1px)}.account-shortcut span{display:block;margin-top:3px;color:var(--muted);font-size:12px;font-weight:600}.timeline-item{padding:12px 0;border-bottom:1px solid var(--line)}@media(max-width:1000px){.account-shortcuts{grid-template-columns:repeat(2,minmax(0,1fr))}.customer-tab-groups{gap:10px}.customer-tab-label{display:none}}@media(max-width:800px){.account-actions,.account-shortcuts{grid-template-columns:1fr}}.customer-primary{flex-wrap:wrap;gap:4px;border-bottom:1px solid var(--line);margin:20px 0 12px}.customer-secondary{flex-wrap:wrap;gap:4px;margin-bottom:20px}.customer-secondary a{padding:8px 12px;font-size:13px}textarea{min-height:80px}textarea[rows="5"]{min-height:150px}.card form>label{display:block;margin:12px 0}.card form>label.choice{display:flex;gap:8px;align-items:center}table th,table td{text-align:left}table td:last-child{text-align:right}</style>
<div class="toolbar"><a class="button secondary" href="{{ route('admin.customers.index') }}">← Customer accounts</a><span class="badge {{ $customer->account_status==='active'?'success':'attention' }}">{{ ucfirst($customer->account_status??($customer->is_active?'active':'paused')) }}</span><span class="badge">Credit {{ $money($creditBalance) }}</span></div>
@if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>
@endif

@if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>
@endif
@php
    $customerTabGroups = [
        'Overview' => ['overview'=>'Overview', 'insights'=>'Insights'],
        'Orders & credit' => ['orders'=>'Orders & refunds', 'credit'=>'Account credit'],
        'Media & access' => ['access'=>'Events', 'favourites'=>'Favourites', 'cart'=>'Cart', 'downloads'=>'Downloads'],
        'Messages' => ['communications'=>'Messages'],
        'Notes & activity' => ['notes'=>'Notes & follow-ups', 'activity'=>'Activity'],
        'Account settings' => ['settings'=>'Contact & status', 'privacy'=>'Preferences & privacy', 'security'=>'Security & risk'],
    ];
    $activeGroup = collect($customerTabGroups)->search(fn ($tabs) => array_key_exists($activeTab, $tabs));
@endphp
<nav class="customer-tabs customer-primary" aria-label="Customer account sections">
@foreach($customerTabGroups as $group=>$tabs)<a class="{{ $activeGroup===$group?'active':'' }}" @if($activeGroup===$group)aria-current="page"@endif href="{{ route('admin.customers.show',[$customer,'tab'=>array_key_first($tabs)]) }}">{{ $group }}</a>@endforeach
</nav>
@if(count($customerTabGroups[$activeGroup])>1)
<nav class="customer-tabs customer-secondary" aria-label="{{ $activeGroup }} pages">
@foreach($customerTabGroups[$activeGroup] as $tab=>$label)<a class="{{ $activeTab===$tab?'active':'' }}" @if($activeTab===$tab)aria-current="page"@endif href="{{ route('admin.customers.show',[$customer,'tab'=>$tab]) }}">{{ $label }}</a>@endforeach
</nav>
@endif

@if($activeTab==='overview')
<div class="grid two-col"><section class="card card-pad"><h2>Customer details</h2><p><strong>{{ $customer->name }}</strong><br>{{ $customer->email }} · {{ $customer->customerProfile?->phone?:'No phone' }}<br>Account #{{ $customer->id }}</p><p class="muted">Verified {{ $customer->email_verified_at?->format('j M Y')??'No' }} · Last login {{ $customer->last_login_at?->format('j M Y, g:i a')??'Never' }} · Last seen {{ $customer->last_seen_at?->format('j M Y, g:i a')??'Never' }}</p><p><strong>Dancers:</strong> {{ $dancers->pluck('name')->join(', ')?:'None' }}<br><strong>Studios:</strong> {{ $studios->pluck('studio.name')->filter()->join(', ')?:'None' }}</p><p class="muted">Terms {{ $customer->customerProfile?->terms_accepted_at?->format('j M Y')??'not accepted' }} · Privacy {{ $customer->customerProfile?->privacy_accepted_at?->format('j M Y')??'not accepted' }} · Marketing {{ $customer->customerProfile?->marketing_consent_at?->format('j M Y')??'not accepted' }}</p></section><section class="card card-pad"><h2>Summary</h2><p>{{ $overviewCounts['orders'] ?? 0 }} orders/grants<br>{{ $overviewCounts['access'] ?? 0 }} event access records<br>{{ $overviewCounts['downloads'] ?? 0 }} downloads<br>{{ $overviewCounts['emails'] ?? 0 }} recorded emails<br>{{ $overviewCounts['favourites'] ?? 0 }} favourites · {{ $overviewCounts['cart'] ?? 0 }} cart items</p></section></div>
<nav class="account-shortcuts" aria-label="Common customer actions">
    <a class="account-shortcut" href="{{ route('admin.customers.show',[$customer,'tab'=>'orders']) }}">Review orders<span>Refunds, grants and payment history</span></a>
    <a class="account-shortcut" href="{{ route('admin.customers.show',[$customer,'tab'=>'access']) }}">Manage event access<span>Competition and concert access</span></a>
    <a class="account-shortcut" href="{{ route('admin.customers.show',[$customer,'tab'=>'communications']) }}">View communications<span>Emails, notifications and help messages</span></a>
    <a class="account-shortcut" href="{{ route('admin.customers.show',[$customer,'tab'=>'notes']) }}">Add a follow-up<span>Internal notes and reminders</span></a>
</nav>
@elseif($activeTab==='settings')
<div class="account-actions"><section class="card card-pad"><h2>Edit contact details</h2><form method="POST" action="{{ route('admin.customers.contact.update',$customer) }}">
@csrf
@method('PATCH')<label>Full name<input name="name" value="{{ old('name',$customer->name) }}" required maxlength="255"></label><label>Preferred name<input name="preferred_name" value="{{ old('preferred_name',$customer->customerProfile?->preferred_name) }}" maxlength="255"></label><label>Email address<input name="email" type="email" value="{{ old('email',$customer->email) }}" required maxlength="255"></label><label>Phone<input name="phone" value="{{ old('phone',$customer->customerProfile?->phone) }}" maxlength="40"></label><label>Reason for change<textarea name="reason" required maxlength="500"></textarea></label><button>Save contact details</button><p class="muted">Changing the email signs out mobile sessions and requires the customer to verify the new address.</p></form><form method="POST" action="{{ route('admin.customers.password-reset.store',$customer) }}" style="margin-top:20px">
@csrf<button class="secondary">Send password reset link</button><p class="muted">The customer chooses their own password using an expiring link. Staff cannot see it.</p></form></section><section class="card card-pad"><h2>Account status</h2><p class="muted">Pausing or cancelling blocks sign-in and mobile sessions while preserving financial and service history.</p><form method="POST" action="{{ route('admin.customers.status.update',$customer) }}">
@csrf
@method('PATCH')<label>Status<select name="status"><option value="active"
@selected($customer->account_status==='active')>Active</option><option value="paused"
@selected($customer->account_status==='paused')>Paused</option><option value="cancelled"
@selected($customer->account_status==='cancelled')>Cancelled</option></select></label><label>Reason<textarea name="reason" required maxlength="500">{{ $customer->account_status_reason }}</textarea></label><button>Update status</button></form></section></div>
@elseif($activeTab==='credit')
<section class="card card-pad"><h2>Add account credit</h2><form method="POST" action="{{ route('admin.customers.credits.store',$customer) }}">
@csrf<div class="grid two-col"><label>Amount<input name="amount" type="number" min="0.01" step="0.01" required></label><label>Type<select name="credit_type"><option value="reward">Reward</option><option value="gift">Gift</option><option value="purchased">Purchased</option></select></label></div><label>Reason<textarea name="note" required maxlength="500"></textarea></label><button>Add credit</button></form><h3 style="margin-top:24px">Credit history</h3>
@forelse($creditTransactions as $transaction)<div class="timeline-item"><strong>{{ $money($transaction->amount) }}</strong> · {{ ucfirst(str_replace('_',' ',$transaction->kind)) }}<br><span class="muted">{{ $transaction->created_at->format('j M Y, g:i a') }} · {{ $transaction->order ? 'Order '.$transaction->order->displayNumber() : $transaction->note }}
@if($transaction->createdBy)· {{ $transaction->createdBy->name }}
@endif
</span></div>
@empty<p class="muted">No credit activity.</p>
@endforelse
</section>
@elseif($activeTab==='orders')
<section class="card card-pad"><h2>Orders & refunds</h2><p class="muted">Open an order to review products, payment, delivery and refund actions.</p>
<table><thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Source</th><th>Collected</th></tr></thead><tbody>
@forelse($orders as $order)<tr><td><a href="{{ route('admin.orders.show', [$order, 'return_to' => request()->getRequestUri()]) }}">Order {{ $order->displayNumber() }}</a></td><td>{{ ($order->paid_at ?? $order->created_at)->format('j M Y') }}</td><td>{{ ucfirst($order->status->value) }}</td><td>{{ data_get($order->metadata,'source')==='complimentary_media_grant' ? 'Complimentary' : 'Purchase' }}</td><td>{{ $money($order->paid_at ? $order->paymentAmount() : 0) }}</td></tr>
@empty<tr><td colspan="5">No orders.</td></tr>@endforelse
</tbody></table></section>

@elseif($activeTab==='access')
<details class="card card-pad" style="margin-bottom:18px"><summary>Add or renew event access</summary><section class="card card-pad"><h2>Add event access</h2><form method="POST" action="{{ route('admin.customers.events.store',$customer) }}">
@csrf<label>Type<select name="event_kind" id="event-kind"><option value="competition">Competition</option><option value="concert">Concert</option></select></label><label id="competition-label">Competition<select name="event_uuid" id="competition-event">
@foreach($eventOptions as $event)<option value="{{ $event->uuid }}">{{ $event->event_date?->format('j M Y') }} · {{ $event->name }}</option>
@endforeach
</select></label><label id="concert-label">Concert<select id="concert-event">
@foreach($concertOptions as $concert)<option value="{{ $concert->uuid }}">{{ $concert->event_date?->format('j M Y') }} · {{ $concert->name }}</option>
@endforeach
</select></label><label>Support reason<textarea name="reason" required maxlength="500"></textarea></label><button>Add event</button></form></section></details>
<div class="grid two-col"><section class="card card-pad"><h2>Competition access</h2>
@forelse($competitionAccess as $record)<p><strong>{{ $record->event?->name }}</strong><br><span class="muted">Added {{ $record->created_at->format('j M Y') }}</span></p>
@empty<p class="muted">None.</p>
@endforelse
</section><section class="card card-pad"><h2>Concert grants</h2>
@forelse($concertGrants as $grant)<p><strong>{{ $grant->concert?->name }}</strong><br><span class="muted">{{ ucfirst($grant->status->value) }} · {{ str_replace('_',' ',$grant->source->value) }} · expires {{ $grant->expires_at?->format('j M Y')??'not set' }}</span></p>
@empty<p class="muted">None.</p>
@endforelse
</section></div><section class="card card-pad" style="margin-top:18px"><h2>Recorded access</h2>
@forelse($concertAccess as $access)<div class="timeline-item"><strong>{{ $access->concert?->name }}</strong> · {{ $access->was_successful?'Successful':'Failed' }}<br><span class="muted">{{ $access->accessed_at?->format('j M Y, g:i a') }} · {{ is_object($access->access_method)?$access->access_method->value:$access->access_method }} {{ $access->failure_reason }}</span></div>
@empty<p class="muted">No access activity.</p>
@endforelse
</section>

@elseif($activeTab==='communications')
<details class="card card-pad" style="margin-bottom:18px"><summary>Compose email</summary><section class="card card-pad"><h2>Email customer</h2><form method="POST" action="{{ route('admin.customers.emails.store',$customer) }}">
@csrf<label>Subject<input name="subject" required maxlength="160"></label><label>Message<textarea name="body" rows="5" required maxlength="10000"></textarea></label><button>Send and record</button></form></section></details>
<div class="grid two-col"><section class="card card-pad"><h2>Direct emails</h2>
@forelse($accountEmails as $email)<div class="timeline-item"><strong>{{ $email->subject }}</strong><br><span class="muted">{{ ucfirst($email->status) }} · {{ ($email->sent_at??$email->queued_at)->format('j M Y, g:i a') }}</span><p style="white-space:pre-wrap">{{ $email->body }}</p></div>
@empty<p class="muted">None.</p>
@endforelse
</section><section class="card card-pad"><h2>Automatic notifications</h2>
@forelse($notifications as $notification)<div class="timeline-item"><strong>{{ $notification->subject }}</strong><br><span class="muted">{{ ucfirst($notification->status) }} · {{ ($notification->sent_at??$notification->queued_at)->format('j M Y, g:i a') }}</span></div>
@empty<p class="muted">None.</p>
@endforelse
<h2 style="margin-top:24px">Help messages</h2>
@forelse($supportRequests as $request)<div class="timeline-item"><strong><a href="{{ route('admin.media-support.conversation', $request) }}">{{ $request->subject }}</a></strong><br><span class="muted">{{ ucfirst($request->status) }} · {{ $request->requested_at->format('j M Y') }}</span><p>{{ $request->message }}</p></div>
@empty<p class="muted">None.</p>
@endforelse
</section></div>

@elseif($activeTab==='downloads')
<p class="muted">Download requests record files requested and their size. They do not confirm that a customer saved the files.</p>
<section class="card" style="overflow:auto"><table><thead><tr><th>File</th><th>Order</th><th>Requests</th><th>Data requested</th><th>Last request</th></tr></thead><tbody>
@forelse($downloads as $download)<tr><td>{{ $download->asset?->display_name?:$download->asset?->original_filename }}</td><td>@if($download->entitlement?->orderItem?->order)<a href="{{ route('admin.orders.show', [$download->entitlement->orderItem->order, 'return_to'=>request()->getRequestUri()]) }}">{{ $download->entitlement->orderItem->order->displayNumber() }}</a>@endif</td><td>{{ $download->download_count }}</td><td>{{ number_format($download->bytes_requested/1048576,1) }} MB</td><td>{{ $download->last_download_at?->format('j M Y, g:i a') }}</td></tr>
@empty<tr><td colspan="5">No download requests.</td></tr>
@endforelse
</tbody></table></section>

@elseif(in_array($activeTab,['favourites','cart'],true))
@php
    $selectionData = $activeTab === 'favourites' ? $favourites : $cart;
@endphp
@include('admin.customers._media-selections', ['selectionData' => $selectionData, 'mode' => $activeTab])

@elseif($activeTab==='insights')
<div class="grid two-col"><section class="card card-pad"><h2>Customer value</h2><p><strong style="font-size:2rem">{{ $money($metrics['lifetime_value']) }}</strong><br>Lifetime net receipts</p><p>{{ $metrics['order_count'] }} paid orders · {{ $money($metrics['average_order_value']) }} average<br>{{ $metrics['purchased_events'] }} purchased events · {{ $metrics['repeat_customer']?'Repeat customer':'One-time customer' }}</p><p class="muted">First order {{ $metrics['first_order_at']?->format('j M Y')??'—' }} · Last order {{ $metrics['last_order_at']?->format('j M Y')??'—' }}</p></section><section class="card card-pad"><h2>Refund and support outcomes</h2><p>{{ $refundCount }} refund requests · {{ ($supportOutcomes['approved']??0)+($supportOutcomes['declined']??0) }} decided support requests · {{ $supportOutcomes['pending']??0 }} pending</p>
<p class="muted">Reasons below are from the current history pages.</p>
@foreach($orders->flatMap->refunds->groupBy('reason') as $reason=>$records)<p><strong>{{ $records->count() }}</strong> · Refund: {{ $reason }}</p>
@endforeach

@foreach($supportRequests->whereIn('status',['approved','declined'])->groupBy(fn($request)=>$request->response_note?:$request->status) as $reason=>$records)<p><strong>{{ $records->count() }}</strong> · Support: {{ $reason }}</p>
@endforeach
</section></div>
<section class="card" style="margin-top:18px;overflow:auto"><table><thead><tr><th>Event</th><th>Action</th><th>Count</th><th>Last activity</th></tr></thead><tbody>
@forelse($engagement as $row)<tr><td>{{ ucfirst($row->event_kind) }} · {{ $row->event_uuid }}</td><td>{{ ucfirst($row->action) }}</td><td>{{ $row->total }}</td><td>{{ \Illuminate\Support\Carbon::parse($row->last_at)->format('j M Y, g:i a') }}</td></tr>
@empty<tr><td colspan="4">New gallery views, favourite and cart actions will appear here.</td></tr>
@endforelse
</tbody></table></section>

@elseif($activeTab==='security')
<div class="grid two-col"><section class="card card-pad"><h2>Login and device history</h2>
@forelse($loginEvents as $login)<div class="timeline-item"><strong>{{ $login->successful?'Successful':'Failed' }} {{ $login->channel }} login</strong> <span class="badge {{ $login->risk_level==='normal'?'success':'attention' }}">{{ $login->risk_level }}</span><br><span class="muted">{{ $login->occurred_at->format('j M Y, g:i a') }} · {{ $login->ip_address }} · {{ Str::limit($login->user_agent,80) }}</span>
@if($login->risk_reasons)<p>{{ implode(', ',array_map(fn($reason)=>str_replace('_',' ',$reason),$login->risk_reasons)) }}</p>
@endif
</div>
@empty<p class="muted">No recorded sign-ins yet.</p>
@endforelse
</section><section class="card card-pad"><h2>Payment and account risk</h2><form method="POST" action="{{ route('admin.customers.risk-events.store',$customer) }}">
@csrf<label>Type<select name="type"><option value="chargeback">Chargeback</option><option value="dispute">Dispute</option><option value="fraud_review">Fraud review</option><option value="payment_failure">Payment failure</option><option value="manual_flag">Manual flag</option></select></label><label>Order ID <span class="muted">(optional)</span><input name="order_id" type="number"></label><label>Amount <span class="muted">(optional)</span><input name="amount" type="number" min="0" step="0.01"></label><label>Provider reference<input name="provider_reference"></label><label>Details<textarea name="details" required maxlength="5000"></textarea></label><button>Record risk event</button></form>
@foreach($riskEvents as $risk)<div class="timeline-item"><strong>{{ ucfirst(str_replace('_',' ',$risk->type)) }}</strong> · {{ ucfirst($risk->status) }}
@if($risk->amount!==null)· {{ $money($risk->amount) }}
@endif
<br><span class="muted">{{ $risk->occurred_at->format('j M Y') }} · {{ $risk->provider_reference }}</span><p>{{ $risk->details }}</p>
@if($risk->status!=='resolved')<form method="POST" action="{{ route('admin.customers.risk-events.resolve',[$customer,$risk]) }}">
@csrf
@method('PATCH')<button class="secondary">Mark resolved</button></form>
@endif
</div>
@endforeach
</section></div>

@elseif($activeTab==='notes')
<p><a href="{{ route('admin.customers.follow-ups') }}">All customer follow-ups →</a></p>
<div class="grid two-col"><section class="card card-pad"><h2>Add internal note</h2><form method="POST" action="{{ route('admin.customers.notes.store',$customer) }}">
@csrf<label>Note<textarea name="note" required maxlength="5000"></textarea></label><label>Tags <span class="muted">comma separated</span><input name="tags" placeholder="VIP, follow-up, delivery"></label><label>Follow up on<input name="follow_up_at" type="datetime-local"></label><button>Add note</button></form></section><section class="card card-pad"><h2>Notes and reminders</h2>
@forelse($notes as $note)<div class="timeline-item"><p>{{ $note->note }}</p><span class="muted">{{ $note->created_at->format('j M Y, g:i a') }} · {{ $note->createdBy?->name }}
@if($note->tags)· {{ implode(', ',$note->tags) }}
@endif

@if($note->follow_up_at)· Follow up {{ $note->follow_up_at->format('j M Y, g:i a') }}
@endif

@if($note->completed_at)· Completed {{ $note->completed_at->format('j M Y') }}
@endif
</span>
@if($note->follow_up_at&&!$note->completed_at)<form method="POST" action="{{ route('admin.customers.notes.complete',[$customer,$note]) }}">
@csrf
@method('PATCH')<button class="secondary">Complete follow-up</button></form>
@endif
</div>
@empty<p class="muted">No internal notes.</p>
@endforelse
</section></div>

@elseif($activeTab==='privacy')
<div class="grid two-col"><section class="card card-pad"><h2>Contact preferences</h2><form method="POST" action="{{ route('admin.customers.preferences.update',$customer) }}">
@csrf
@method('PATCH')
@php
    $prefs = $customer->customerProfile?->preferences ?? [];
@endphp
@foreach(['marketing_email' => 'Marketing email', 'media_ready_email' => 'Media ready email', 'expiry_reminders_email' => 'Download expiry reminders'] as $channel => $label)
<label class="choice"><input type="checkbox" name="{{ $channel }}" value="1" @checked(\App\Features\Customers\Services\CustomerCommunicationPreferences::enabled($customer, $channel))><span>{{ $label }}</span></label>
@endforeach
<p class="muted">Receipts, security messages and replies to support requests are sent when needed.</p>
<label>Reason<textarea name="reason" required maxlength="500"></textarea></label><button>Save preferences</button></form><h3>Consent history</h3>
@forelse($consentHistory as $consent)<p>{{ ucfirst(str_replace('_',' ',$consent->channel)) }} · <strong>{{ $consent->granted?'Granted':'Withdrawn' }}</strong><br><span class="muted">{{ $consent->created_at->format('j M Y, g:i a') }} · {{ $consent->source }}</span></p>
@empty<p class="muted">Changes will be recorded here.</p>
@endforelse
</section><section class="card card-pad"><h2>Possible duplicate accounts</h2>
@forelse($duplicates as $duplicate)<p><strong>{{ $duplicate->name }}</strong><br>{{ $duplicate->email }} · ID #{{ $duplicate->id }} · {{ $duplicate->orders_count }} orders</p><form method="POST" action="{{ route('admin.customers.merge.store') }}">
@csrf<input type="hidden" name="source_customer_id" value="{{ $duplicate->id }}"><input type="hidden" name="target_customer_id" value="{{ $customer->id }}"><label>Primary account email<input name="confirmation_email" required></label><label>Merge reason<textarea name="reason" required maxlength="500"></textarea></label><button>Merge into this account</button></form>
@empty<p class="muted">No likely duplicates found by name or phone.</p>
@endforelse
</section></div><section class="card card-pad" style="margin-top:18px"><h2>Privacy actions</h2><div class="grid two-col"><form method="POST" action="{{ route('admin.customers.privacy.store',$customer) }}">
@csrf<input type="hidden" name="action" value="export"><label>Export reason<textarea name="reason" required maxlength="500"></textarea></label><button>Download customer data export</button></form><form method="POST" action="{{ route('admin.customers.privacy.store',$customer) }}">
@csrf<input type="hidden" name="action" value="anonymize"><label>Anonymisation reason<textarea name="reason" required maxlength="500"></textarea></label><label>Type current email to confirm<input name="confirmation_email" type="email" required></label><button class="danger">Anonymise personal data</button><p class="muted">Disables access and redacts customer account contact data. Order/media records are retained; original media, backups and external provider records require separate retention review.</p></form></div>
@foreach($privacyRequests as $privacy)<p>{{ ucfirst($privacy->type) }} · {{ ucfirst($privacy->status) }} · {{ $privacy->requested_at->format('j M Y, g:i a') }}</p>
@endforeach
</section>

@else
<section class="card card-pad"><h2>Administrative activity</h2>
@forelse($activity as $entry)<div class="timeline-item"><strong>{{ $entry->summary }}</strong><br><span class="muted">{{ $entry->created_at->format('j M Y, g:i a') }} · {{ $entry->actor?->name?:'System' }}</span></div>
@empty<p class="muted">No administrative activity yet.</p>
@endforelse
</section>
@endif
@include('admin.customers._grant-dialog',['grantCustomer'=>$customer])
<script nonce="{{ request()->attributes->get('csp_nonce') }}">const k=document.getElementById('event-kind'),c=document.getElementById('competition-event'),v=document.getElementById('concert-event');function events(){if(!k)return;const x=k.value==='concert';v.disabled=!x;v.name=x?'event_uuid':'';document.getElementById('concert-label').style.display=x?'grid':'none';c.disabled=x;c.name=x?'':'event_uuid';document.getElementById('competition-label').style.display=x?'none':'grid'}k?.addEventListener('change',events);events();document.querySelectorAll('[data-select-refund-items]').forEach(button=>button.addEventListener('click',()=>button.closest('form').querySelectorAll('input[name="order_item_ids[]"]').forEach(item=>item.checked=true)));</script>
@foreach($historyPages as $label => $historyPage)
@if($historyPage->hasPages())<div class="card card-pad" style="margin-top:16px"><strong>{{ $label }}</strong>{{ $historyPage->links() }}</div>@endif
@endforeach
@endsection
