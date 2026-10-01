<div class="message-centre-list" data-message-results>
@if($mode === 'new')
@forelse($results as $result)
<a href="{{ route('admin.messages.index', ['channel'=>$result['channel'], 'mode'=>'new', 'q'=>$q, 'recipient'=>$result['reference']]) }}" @if((string) $recipient?->getRouteKey() === $result['reference'] && $channel === $result['channel']) aria-current="page" @endif><strong>{{ $result['name'] }}</strong><small>{{ ucfirst($result['channel']) }} · {{ $result['detail'] }}</small></a>
@empty<p class="muted">{{ $q === '' ? 'Start typing a name, email or ID to find a recipient.' : 'No matching recipients. Try their name or email.' }}</p>@endforelse
@else
@forelse($threads as $item)
<a href="{{ route('admin.messages.index', ['channel'=>$item['channel'], 'thread'=>$item['reference']]) }}" @if(request('thread') === $item['reference']) aria-current="page" @endif><strong>{{ $item['name'] }}</strong><small>{{ ucfirst($item['channel']) }} · {{ $item['detail'] }}</small><span>{{ $item['subject'] }}</span><small>{{ $item['status'] }}@if($item['at']) · {{ $item['at']->format('j M Y, g:i a') }}@endif</small></a>
@empty<p>No conversations match this filter.</p>@endforelse
{{ $threads->links() }}
@endif
</div>
