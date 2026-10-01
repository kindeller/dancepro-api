@if($receipt['paid_in_full'])<div style="border:1px solid #a4dfbe;border-radius:8px;background:#e5f8ed;color:#147a52;padding:16px 20px;margin-bottom:22px;font-weight:700;font-size:20px">✓ Invoice paid in full</div>@else<h2>Payment received</h2>@endif
{!! $bodyHtml !!}
<p style="margin:24px 0"><a href="{{ $receipt['url'] }}" style="display:inline-block;border-radius:7px;background:#0aa0db;color:#fff;padding:13px 20px;font-weight:700;text-decoration:none">View invoice &amp; payment history</a></p>
