@php
$refundedItemIds = $order->refunds->whereIn('status', ['pending_provider','processing','completed'])->flatMap->items->pluck('order_item_id');
@endphp
@foreach($order->refunds as $refund)<p class="notice"><strong>Refund {{ $refund->displayNumber() }}</strong> · {{ ucfirst(str_replace('_',' ',$refund->status)) }} · {{ $money($refund->amount) }}<br><span class="muted">{{ $refund->requested_at->format('j M Y, g:i a') }} · {{ $refund->reason }} · {{ $refund->items->count() }} product(s)</span></p>
@endforeach
@if(data_get($order->metadata,'source')==='complimentary_media_grant')
@php
    $grantRevoked = $order->items->flatMap->mediaEntitlements->isNotEmpty()
        && $order->items->flatMap->mediaEntitlements->every(fn ($entitlement) => $entitlement->revoked_at !== null);
@endphp
@if($grantRevoked)<p class="notice"><strong>Complimentary access revoked</strong><br><span class="muted">{{ data_get($order->metadata,'complimentary_access_revoke_reason') }}</span></p>
@else<form method="POST" action="{{ route('admin.customers.complimentary-media.revoke',[$customer,$order]) }}">
@csrf<label>Revocation reason<textarea name="reason" required maxlength="500"></textarea></label><label class="choice"><input type="checkbox" name="notify_customer" value="1"><span>Email the customer about this change</span></label><button class="danger">Revoke complimentary access</button></form>
@endif
@endif
<form method="POST" action="{{ route('admin.customers.refunds.store',[$customer,$order]) }}">
@csrf<table><thead><tr><th></th><th>Product</th><th>Paid allocation</th><th>GST</th></tr></thead><tbody>
@foreach($order->items as $item)<tr><td>
@if(data_get($order->metadata,'source')==='customer_cart'&&(int)data_get($item->metadata,'net_after_account_credit',$item->total_price_amount)>0)<input type="checkbox" name="order_item_ids[]" value="{{ $item->id }}" @disabled($refundedItemIds->contains($item->id)) aria-label="Refund {{ $item->snapshot_display_name?:$item->snapshot_filename?:'product' }}">
@endif
</td><td>{{ $item->snapshot_display_name?:$item->snapshot_filename?:ucfirst(str_replace('_',' ',$item->item_type)) }}</td><td>{{ $money((int)data_get($item->metadata,'net_after_account_credit',$item->total_price_amount)) }}</td><td>{{ $money($item->tax_amount) }}</td></tr>
@endforeach
</tbody></table>
@if(data_get($order->metadata,'source')==='customer_cart'&&$order->paymentAmount()>0)<p><button class="button secondary" type="button" data-select-refund-items>Select all refundable products</button></p><label class="choice"><input type="checkbox" name="revoke_access" value="1" checked><span>Remove access after the provider refund succeeds</span></label><label>Reason<textarea name="reason" required maxlength="500"></textarea></label><button>Prepare selected refund</button><p class="muted">This queues an accurate refund record. No money moves until the payment provider confirms it.</p>
@endif
</form>