<nav class="admin-shortcuts" aria-label="Admin quick access">
@can('manageScheduling')
<a class="admin-shortcut" href="{{ route('admin.scheduling-events.index') }}" aria-label="Event availability" title="Event availability" @if(request()->routeIs('admin.scheduling-events.*')) aria-current="page" @endif>
<span class="admin-shortcut-art"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18m-14 5 3 3 7-6"/></svg></span><span>Availability</span>
@include('layouts.partials.notification-badge', ['count'=>$adminNavigationIndicators['availability'], 'key'=>'availability', 'label'=>'scheduling actions needing attention'])
</a>
<a class="admin-shortcut" href="{{ route('admin.concert-tracker.index', ['year'=>$concertTrackerNotificationYear]) }}" aria-label="Concert tracker" title="Concert tracker" @if(request()->routeIs('admin.concert-tracker.*')) aria-current="page" @endif>
<span class="admin-shortcut-art"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="15" height="18" rx="2"/><path d="M9 2h7v4H9zM8 11l1 1 2-3m2 2h4M8 17l1 1 2-3m2 2h4"/></svg></span><span>Tracker</span>
@include('layouts.partials.notification-badge', ['count'=>$concertTrackerAttentionCount, 'key'=>'tracker', 'label'=>'tracker tasks needing attention'])
</a>
@endcan
@if(auth()->user()?->crewProfile)
<a class="admin-shortcut" href="{{ route('crew.availability.index') }}" aria-label="My Hub" title="My Hub">
<span class="admin-shortcut-art"><img src="{{ asset('images/brand/dancepro-crew-hub-icon.png') }}" alt="" width="44" height="44"></span><span>My Hub</span>
@include('layouts.partials.notification-badge', ['count'=>$adminNavigationIndicators['my_hub'], 'key'=>'my_hub', 'label'=>'crew actions needing attention'])
</a>
@endif
@if(auth()->user()?->canAccessCustomer())
<a class="admin-shortcut" href="{{ route('customer.media.index') }}" aria-label="My Media" title="My Media">
<span class="admin-shortcut-art"><img src="{{ asset('images/brand/dancepro-media-icon.png') }}" alt="" width="44" height="44"></span><span>My Media</span>
@include('layouts.partials.notification-badge', ['count'=>$adminNavigationIndicators['my_media'], 'key'=>'my_media', 'label'=>'unread account updates'])
</a>
@endif
<a class="admin-shortcut" href="{{ route('admin.messages.index') }}" aria-label="Message Centre" title="Message Centre" @if(request()->routeIs('admin.messages.*')) aria-current="page" @endif>
<span class="admin-shortcut-art"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="m3 6 9 7 9-7"/></svg></span><span>Messages</span>
@include('layouts.partials.notification-badge', ['count'=>$adminNavigationIndicators['messages'], 'key'=>'messages', 'label'=>'messages and requests needing attention'])
</a>
</nav>
