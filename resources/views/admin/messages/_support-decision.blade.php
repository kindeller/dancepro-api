<details class="centre-message"><summary>{{ $supportRequest->type === 'download_reactivation' ? 'Download reactivation request' : 'Request decision' }} · {{ ucfirst($supportRequest->status) }}</summary>
@if($supportRequest->context)<p class="muted">{{ data_get($supportRequest->context, 'item_name') }} · {{ data_get($supportRequest->context, 'media_type') }}</p>@endif
@if($supportRequest->status === 'pending')
<form method="post" action="{{ route('admin.media-support.resolve', $supportRequest) }}" class="centre-compose">@csrf @method('PATCH')
<label>Decision<select name="decision">@if($supportRequest->type === 'download_reactivation')<option value="approved">Approve extension</option>@else<option value="resolved">Resolve</option>@endif<option value="declined">Decline</option></select></label>
@if($supportRequest->type === 'download_reactivation')<label>Access for (days)<input type="number" name="extension_days" min="1" max="365" value="30"></label>@endif
<label>Response to customer<textarea name="response_note" maxlength="3000"></textarea></label><button type="submit">Save & notify</button>
</form>
@else<p>{{ $supportRequest->response_note }}</p><p class="muted">{{ $supportRequest->decided_at?->format('j M Y, g:i a') }} · {{ $supportRequest->decidedBy?->name }}</p>@endif
</details>
