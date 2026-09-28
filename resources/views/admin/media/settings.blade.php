@extends('layouts.admin', ['title' => 'Gallery settings', 'heading' => 'Gallery settings', 'subheading' => 'Pricing, promotions and customer experience'])

@push('styles')
<style>
    .settings-tabs { display:flex; gap:4px; margin:24px 0; overflow-x:auto; border-bottom:1px solid var(--line); }
    .settings-tabs a { flex:0 0 auto; padding:13px 18px 12px; border:1px solid transparent; border-bottom:0; border-radius:8px 8px 0 0; color:var(--muted); font-weight:800; text-decoration:none; }
    .settings-tabs a:hover { background:var(--soft); color:var(--brand-strong); text-decoration:none; }
    .settings-tabs a[aria-selected="true"] { margin-bottom:-1px; border-color:var(--line); border-bottom-color:var(--panel); background:var(--panel); color:var(--ink); }
    .settings-panel { max-width:1180px; }
    .price-list-section { margin-bottom:28px; }
    .price-list-intro { margin-bottom:32px; }
    .price-list-heading { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; }
    .price-list-heading p { margin:4px 0 0; }
    .price-list-badge { display:inline-block; padding:3px 8px; border-radius:99px; background:var(--soft); color:var(--brand-strong); font-size:11px; font-weight:850; text-transform:uppercase; letter-spacing:.05em; }
    .price-list-badge.inactive { background:#f2f4f5; color:var(--muted); }
    .create-price-list > summary, .price-list-row > summary { list-style:none; cursor:pointer; }
    .create-price-list > summary::-webkit-details-marker, .price-list-row > summary::-webkit-details-marker { display:none; }
    .create-price-list-button { pointer-events:none; }
    .create-price-list[open] .create-price-list-button { background:var(--soft); color:var(--brand-strong); }
    .create-price-list-form { max-width:680px; margin:0 0 14px; }
    .create-price-list-form h3 { margin-bottom:14px; }
    .price-list-rows { display:grid; gap:10px; }
    .price-list-row { overflow:hidden; }
    .price-list-summary { display:grid; grid-template-columns:minmax(180px,1fr) auto minmax(190px,auto) 18px; align-items:center; gap:14px; min-height:66px; padding:13px 18px; }
    .price-list-summary:hover { background:var(--soft); }
    .price-list-summary-name { min-width:0; font-size:17px; font-weight:850; }
    .price-list-summary-badges { display:flex; gap:7px; }
    .price-list-assignment-count { color:var(--muted); font-size:14px; text-align:right; }
    .price-list-chevron { width:10px; height:10px; border-right:2px solid currentColor; border-bottom:2px solid currentColor; transform:rotate(45deg) translateY(-3px); transition:transform .15s ease; }
    .price-list-row[open] .price-list-chevron { transform:rotate(225deg) translate(-2px,-1px); }
    .price-list-row-form { max-width:680px; padding:8px 18px 22px; border-top:1px solid var(--line); }
    .price-list-options { display:flex; flex-wrap:wrap; gap:16px; align-items:center; }
    .price-list-checkbox { display:flex; align-items:center; gap:8px; }
    .price-list-checkbox input { width:auto; min-height:0; }
    .price-product-row { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:12px; align-items:end; }
    .price-product-row .gst-toggle { display:flex; min-height:42px; align-items:center; gap:7px; padding:9px 2px; color:var(--ink); white-space:nowrap; }
    .price-product-row .gst-toggle input { width:auto; min-height:0; }
    .price-list-assignments { padding-top:6px; }
    .discount-settings { display:grid; gap:14px; }
    .settings-collapse { overflow:hidden; }
    .settings-collapse > summary { display:grid; grid-template-columns:minmax(0,1fr) auto 18px; align-items:center; gap:14px; min-height:70px; padding:16px 18px; list-style:none; cursor:pointer; }
    .settings-collapse > summary::-webkit-details-marker { display:none; }
    .settings-collapse > summary:hover { background:var(--soft); }
    .settings-collapse-title { display:grid; gap:4px; }
    .settings-collapse-title p { margin:0; }
    .settings-collapse-meta { color:var(--muted); font-size:14px; text-align:right; }
    .settings-collapse-content { max-width:680px; padding:18px; border-top:1px solid var(--line); }
    .settings-collapse[open] .price-list-chevron { transform:rotate(225deg) translate(-2px,-1px); }
    .discount-code-list { display:grid; gap:10px; margin-top:26px; }
    .discount-code-list-heading { margin-bottom:2px; }
    .discount-code-summary { grid-template-columns:minmax(130px,1fr) auto minmax(250px,1.4fr) auto 18px !important; }
    .discount-code-name { font-size:17px; font-weight:850; letter-spacing:.03em; }
    .discount-code-value { font-weight:800; white-space:nowrap; }
    .discount-code-details { color:var(--muted); font-size:13px; }
    .discount-status { display:inline-flex; align-items:center; justify-content:center; min-width:74px; padding:4px 9px; border-radius:99px; background:#f2f4f5; color:var(--muted); font-size:11px; font-weight:850; letter-spacing:.04em; text-transform:uppercase; }
    .discount-status.active { background:#dcfce7; color:var(--ok); }
    .discount-status.scheduled { background:var(--soft); color:var(--brand-strong); }
    .discount-status.expired, .discount-status.used-up { background:#fff7ed; color:var(--warn); }
    .discount-value-wrap { position:relative; }
    .discount-value-symbol { position:absolute; z-index:1; top:50%; right:12px; transform:translateY(-50%); color:var(--muted); font-weight:800; pointer-events:none; }
    .discount-value-wrap input { padding-right:30px; }
    .discount-value-wrap.is-fixed .discount-value-symbol { right:auto; left:12px; }
    .discount-value-wrap.is-fixed input { padding-right:11px; padding-left:30px; }
    .discount-form-actions { display:flex; flex-wrap:wrap; gap:10px; }
    .inline-checkbox { display:flex; align-items:center; gap:8px; }
    .inline-checkbox input { width:auto; min-height:0; }
    .customer-credit-search { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:10px; align-items:end; }
    .customer-credit-result { margin-top:16px; padding-top:16px; border-top:1px solid var(--line); }
    .watermark-panel { display:grid; gap:28px; }
    .watermark-intro { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-start; gap:14px; }
    .watermark-intro p { margin:4px 0 0; }
    .create-watermark { width:100%; }
    .watermark-section { display:grid; gap:10px; }
    .watermark-section-heading { display:flex; align-items:end; justify-content:space-between; gap:12px; }
    .watermark-section-heading p { margin:3px 0 0; }
    .watermark-row { overflow:hidden; }
    .watermark-row > summary { display:grid; grid-template-columns:92px minmax(170px,1fr) auto minmax(170px,auto) 18px; align-items:center; gap:14px; min-height:78px; padding:10px 18px; list-style:none; cursor:pointer; }
    .watermark-row > summary::-webkit-details-marker { display:none; }
    .watermark-row > summary:hover { background:var(--soft); }
    .watermark-row[open] .price-list-chevron { transform:rotate(225deg) translate(-2px,-1px); }
    .watermark-thumb { display:block; width:92px; height:58px; border-radius:7px; object-fit:cover; background:#10202c; border:1px solid var(--line); }
    .watermark-name { min-width:0; font-size:17px; font-weight:850; }
    .watermark-summary-badges { display:flex; flex-wrap:wrap; gap:6px; }
    .watermark-assignment { color:var(--muted); font-size:13px; text-align:right; }
    .watermark-details { display:grid; grid-template-columns:minmax(260px,420px) minmax(220px,1fr); gap:20px; padding:18px; border-top:1px solid var(--line); }
    .watermark-preview { display:block; width:100%; aspect-ratio:900/560; border-radius:10px; object-fit:cover; background:#10202c; }
    .watermark-actions { display:flex; flex-wrap:wrap; gap:9px; align-items:flex-start; }
    .watermark-actions form { margin:0; }
    .watermark-archived { margin-top:4px; }
    .notification-settings { display:grid; gap:14px; }
    .notification-intro { display:flex; align-items:center; justify-content:space-between; gap:18px; border:0; background:linear-gradient(135deg,#10202c,#17394a); color:#fff; }
    .notification-intro h2 { color:#fff; margin-bottom:5px; }
    .notification-intro p { margin:0; color:#c6d8e1; }
    .notification-intro-icon { flex:0 0 auto; display:grid; place-items:center; width:58px; height:58px; border-radius:16px; background:#0b9ed2; }
    .notification-intro-icon svg { width:27px; height:27px; }
    .notification-summary-title { display:flex; align-items:center; gap:13px; }
    .notification-summary-icon { flex:0 0 auto; display:grid; place-items:center; width:39px; height:39px; border-radius:11px; background:#e4f5fb; color:#087fa9; }
    .notification-summary-icon svg { width:20px; height:20px; }
    .notification-collapse > summary { min-height:78px; }
    .notification-collapse > .settings-collapse-content { max-width:none; }
    .notification-workspace { display:grid; grid-template-columns:minmax(360px,.82fr) minmax(410px,1.18fr); gap:24px; align-items:start; }
    .notification-editor { display:grid; gap:13px; }
    .notification-editor textarea { min-height:220px; line-height:1.55; }
    .notification-editor-actions { display:flex; justify-content:flex-end; }
    .notification-template-preview { padding:20px; border-radius:11px; background:#e9edef; }
    .notification-template-preview-header { margin:0 0 9px; color:#53646e; font-size:12px; font-weight:850; letter-spacing:.05em; text-transform:uppercase; }
    .notification-email-card { overflow:hidden; max-width:570px; margin:auto; border-radius:11px; background:#fff; box-shadow:0 8px 26px rgba(16,32,44,.12); }
    .notification-email-accent { height:6px; background:#0aa0db; }
    .notification-email-brand { padding:17px 22px; background:#101820; }
    .notification-email-brand img { display:block; width:220px; max-width:100%; height:42px; object-fit:contain; object-position:left center; }
    .notification-email-body { padding:23px 24px 20px; color:#263640; font-size:13px; line-height:1.6; }
    .notification-email-subject { margin:0 0 16px; color:#101820; font-size:20px; line-height:1.25; }
    .notification-email-message { margin:0 0 18px; white-space:pre-line; }
    .notification-email-action { display:inline-block; border-radius:7px; background:#0aa0db; color:#fff; padding:10px 14px; font-weight:800; }
    .notification-email-signature { margin:20px 0 0; }
    .notification-email-footer { border-top:1px solid #dce6eb; padding:15px 24px; color:#667782; font-size:10px; line-height:1.5; }
    .notification-history { margin-top:14px; }
    .notification-history-list { display:grid; gap:0; margin-top:12px; border:1px solid var(--line); border-radius:10px; overflow:hidden; }
    .notification-history-row { display:grid; grid-template-columns:minmax(135px,.7fr) minmax(190px,1fr) minmax(210px,1.4fr) auto; gap:14px; align-items:center; padding:13px 15px; border-bottom:1px solid var(--line); }
    .notification-history-row:last-child { border-bottom:0; }
    .notification-history-recipient, .notification-history-subject { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .notification-status { display:inline-flex; align-items:center; justify-content:center; padding:4px 9px; border-radius:99px; background:#fff7ed; color:var(--warn); font-size:11px; font-weight:850; text-transform:uppercase; letter-spacing:.04em; }
    .notification-status.sent { background:#dcfce7; color:var(--ok); }
    @media (max-width:760px) {
        .price-list-summary { grid-template-columns:minmax(0,1fr) 18px; gap:7px 12px; }
        .price-list-summary-badges, .price-list-assignment-count { grid-column:1; text-align:left; }
        .price-list-chevron { grid-column:2; grid-row:1 / span 3; }
        .settings-collapse > summary, .discount-code-summary { grid-template-columns:minmax(0,1fr) 18px !important; gap:7px 12px; }
        .settings-collapse-meta, .discount-code-value, .discount-code-details, .discount-status { grid-column:1; text-align:left; justify-self:start; }
        .settings-collapse > summary .price-list-chevron { grid-column:2; grid-row:1 / span 5; }
        .watermark-row > summary { grid-template-columns:76px minmax(0,1fr) 18px; }
        .watermark-thumb { width:76px; height:50px; grid-row:1 / span 3; }
        .watermark-summary-badges, .watermark-assignment { grid-column:2; text-align:left; }
        .watermark-row > summary .price-list-chevron { grid-column:3; grid-row:1 / span 3; }
        .watermark-details { grid-template-columns:1fr; }
        .notification-history-row { grid-template-columns:1fr auto; gap:5px 10px; }
        .notification-history-recipient, .notification-history-subject { grid-column:1; white-space:normal; }
        .notification-status { grid-column:2; grid-row:1 / span 3; }
        .notification-workspace { grid-template-columns:1fr; }
        .notification-template-preview { padding:12px; }
    }
</style>
@endpush

@section('content')
@if(session('status'))<p class="card card-pad" role="status">{{ session('status') }}</p>@endif
<p class="muted">Changes affect new cart quotes and order drafts. Existing paid orders keep their original prices and discounts.</p>

<nav class="settings-tabs" aria-label="Gallery settings" role="tablist">
    @foreach([
        'pricing' => 'Products & pricing',
        'discounts' => 'Discounts & promotions',
        'watermarks' => 'Watermarks',
        'notifications' => 'Notifications',
        'help' => 'Customer help',
    ] as $tab => $label)
        <a href="{{ route('admin.media-settings.index', ['tab' => $tab]) }}" role="tab" aria-selected="{{ $activeTab === $tab ? 'true' : 'false' }}">{{ $label }}</a>
    @endforeach
</nav>

@if($activeTab === 'pricing')
    @include('admin.media.partials.price-lists')
@elseif($activeTab === 'discounts')
    <div class="settings-panel discount-settings">
        <details class="card settings-collapse">
            <summary>
                <span class="settings-collapse-title"><h2>Bulk discounts &amp; upselling</h2><p class="muted">Four spend levels and the customer upsell prompts.</p></span>
                <span class="settings-collapse-meta">{{ $settings['upsell_enabled'] ? 'Upselling on' : 'Upselling off' }}</span>
                <span class="price-list-chevron" aria-hidden="true"></span>
            </summary>
            <div class="settings-collapse-content">
                <form method="post" action="{{ route('admin.media-settings.update', ['tab' => 'discounts']) }}" class="grid">@csrf<input type="hidden" name="section" value="discounts">
                    @foreach(range(1,4) as $tier)
                        <div style="display:flex;gap:10px"><label>Level {{ $tier }} threshold · $<input type="number" step="0.01" name="bulk_{{ $tier }}_threshold" min="1" value="{{ old('bulk_'.$tier.'_threshold', number_format($settings['bulk_'.$tier.'_threshold'] / 100, 2, '.', '')) }}" required></label><label>Discount %<input type="number" name="bulk_{{ $tier }}_percent" min="0" max="90" value="{{ old('bulk_'.$tier.'_percent', $settings['bulk_'.$tier.'_percent']) }}" required></label></div>
                    @endforeach
                    <label class="inline-checkbox"><input type="checkbox" name="upsell_enabled" value="1" @checked(old('upsell_enabled', $settings['upsell_enabled']))>Show package and next-discount suggestions</label>
                    <div><button>Save bulk discounts</button></div>
                </form>
            </div>
        </details>

        <details class="card settings-collapse" @if($errors->has('code') || $errors->has('discount_value')) open @endif>
            <summary>
                <span class="settings-collapse-title"><h2>Add discount code</h2><p class="muted">Create a percentage or dollar discount with optional usage limits.</p></span>
                <span class="settings-collapse-meta">+ Add code</span>
                <span class="price-list-chevron" aria-hidden="true"></span>
            </summary>
            <div class="settings-collapse-content">
                <p class="muted">A code reduces eligible spend before the bulk discount is calculated. For a private one-use code, set both usage limits to 1.</p>
                <form method="post" action="{{ route('admin.media-settings.codes.store', ['tab' => 'discounts']) }}" class="grid" data-discount-form>@csrf
                    <label>Code<input name="code" maxlength="32" value="{{ old('code') }}" placeholder="SPRING10" required></label>
                    <label>Discount type<select name="discount_type" data-discount-type><option value="percent" @selected(old('discount_type', 'percent') === 'percent')>Percentage</option><option value="fixed" @selected(old('discount_type') === 'fixed')>Dollar amount</option></select></label>
                    <label>Discount <span data-discount-unit>{{ old('discount_type', 'percent') === 'fixed' ? '($)' : '(%)' }}</span><span class="discount-value-wrap @if(old('discount_type', 'percent') === 'fixed') is-fixed @endif"><span class="discount-value-symbol" data-discount-symbol>{{ old('discount_type', 'percent') === 'fixed' ? '$' : '%' }}</span><input type="number" name="discount_value" data-discount-value min="1" max="90" step="1" value="{{ old('discount_value') }}" required></span></label>
                    <label>Starts at<input type="datetime-local" name="starts_at" value="{{ old('starts_at', today()->format('Y-m-d\TH:i')) }}"></label>
                    <label>Ends at<input type="datetime-local" name="ends_at" value="{{ old('ends_at', today()->addDays(30)->endOfDay()->format('Y-m-d\TH:i')) }}"></label>
                    <label>Maximum uses<input type="number" name="max_redemptions" min="1" value="{{ old('max_redemptions') }}" placeholder="No limit"></label>
                    <label>Uses per customer account<input type="number" name="max_redemptions_per_customer" min="1" value="{{ old('max_redemptions_per_customer') }}" placeholder="No limit"><span class="muted">Limits how many approved orders each customer account can use this code on.</span></label>
                    <label class="inline-checkbox"><input type="checkbox" name="enabled" value="1" @checked(old('enabled'))> Active</label>
                    <div><button>Add discount code</button></div>
                </form>
            </div>
        </details>

        <details class="card settings-collapse" @if($customerLookup !== '' || $errors->has('user_id') || $errors->has('amount') || $errors->has('credit_type') || $errors->has('note')) open @endif>
            <summary>
                <span class="settings-collapse-title"><h2>Customer account credit</h2><p class="muted">Find a customer by email or account ID and add credit to their balance.</p></span>
                <span class="settings-collapse-meta">Add credit</span>
                <span class="price-list-chevron" aria-hidden="true"></span>
            </summary>
            <div class="settings-collapse-content">
                <form method="get" action="{{ route('admin.media-settings.index') }}" class="customer-credit-search">
                    <input type="hidden" name="tab" value="discounts">
                    <label>Customer email or account ID<input name="customer_lookup" value="{{ $customerLookup }}" placeholder="customer@example.com or 10482" required></label>
                    <button class="secondary">Find account</button>
                </form>
                @if($customerLookup !== '')
                    <div class="customer-credit-result">
                        @if($creditCustomers->isEmpty())
                            <p class="muted">No customer account matched “{{ $customerLookup }}”.</p>
                        @else
                            <form method="post" action="{{ route('admin.media-settings.customer-credits.store') }}" class="grid">@csrf
                                <label>Customer account<select name="user_id" required>
                                    @foreach($creditCustomers as $customer)<option value="{{ $customer->id }}" @selected((int) old('user_id') === $customer->id)>Account #{{ $customer->id }} · {{ $customer->name }} · {{ $customer->email }} · ${{ number_format(($customer->credit_balance ?? 0) / 100, 2) }} credit</option>@endforeach
                                </select></label>
                                <label>Credit amount · $<input type="number" name="amount" min="0.01" max="100000" step="0.01" value="{{ old('amount') }}" required></label>
                                <label>Credit type<select name="credit_type" required><option value="reward" @selected(old('credit_type', 'reward') === 'reward')>Reward</option><option value="gift" @selected(old('credit_type') === 'gift')>Gift</option><option value="purchased" @selected(old('credit_type') === 'purchased')>Purchased credit</option></select><span class="muted">GST for purchased credit is recorded when the credit is sold, not when it is redeemed.</span></label>
                                <label>Reason for credit<textarea name="note" rows="3" maxlength="500" required placeholder="Customer service credit, damaged order, goodwill…">{{ old('note') }}</textarea></label>
                                <div><button>Add account credit</button></div>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        </details>

        <section class="discount-code-list">
            <div class="discount-code-list-heading"><h2>Created discount codes</h2><p class="muted">Select a code to view or change its full settings.</p></div>
            @forelse($codes as $code)
                @php($status = $code->statusLabel())
                <details class="card settings-collapse discount-code-row">
                    <summary class="discount-code-summary">
                        <span class="discount-code-name">{{ $code->code }}</span>
                        <span class="discount-code-value">{{ $code->discountLabel() }}</span>
                        <span class="discount-code-details">
                            {{ $code->starts_at?->format('j M Y') ?? 'No start date' }} – {{ $code->ends_at?->format('j M Y') ?? 'No expiry' }} · {{ $code->redemptions_count }} used
                            @if($code->max_redemptions !== null) / {{ $code->max_redemptions }} @endif
                            @if($code->max_redemptions_per_customer !== null) · {{ $code->max_redemptions_per_customer }} per account @endif
                        </span>
                        <span class="discount-status {{ strtolower(str_replace(' ', '-', $status)) }}">{{ $status }}</span>
                        <span class="price-list-chevron" aria-hidden="true"></span>
                    </summary>
                    <div class="settings-collapse-content">
                        <form method="post" action="{{ route('admin.media-settings.codes.update', ['discountCode' => $code, 'tab' => 'discounts']) }}" class="grid" data-discount-form>@csrf @method('PATCH')
                            <label>Code<input name="code" value="{{ $code->code }}" required></label>
                            <label>Discount type<select name="discount_type" data-discount-type><option value="percent" @selected($code->discount_type === 'percent')>Percentage</option><option value="fixed" @selected($code->discount_type === 'fixed')>Dollar amount</option></select></label>
                            <label>Discount <span data-discount-unit>{{ $code->discount_type === 'fixed' ? '($)' : '(%)' }}</span><span class="discount-value-wrap @if($code->discount_type === 'fixed') is-fixed @endif"><span class="discount-value-symbol" data-discount-symbol>{{ $code->discount_type === 'fixed' ? '$' : '%' }}</span><input type="number" name="discount_value" data-discount-value value="{{ $code->discount_type === 'fixed' ? number_format($code->amount / 100, 2, '.', '') : $code->percent }}" required></span></label>
                            <label>Starts at<input type="datetime-local" name="starts_at" value="{{ $code->starts_at?->format('Y-m-d\TH:i') }}"></label>
                            <label>Ends at<input type="datetime-local" name="ends_at" value="{{ $code->ends_at?->format('Y-m-d\TH:i') }}"></label>
                            <label>Maximum uses<input type="number" name="max_redemptions" min="1" value="{{ $code->max_redemptions }}" placeholder="No limit"></label>
                            <label>Uses per customer account<input type="number" name="max_redemptions_per_customer" min="1" value="{{ $code->max_redemptions_per_customer }}" placeholder="No limit"><span class="muted">This limit applies separately to each customer account.</span></label>
                            <label class="inline-checkbox"><input type="checkbox" name="enabled" value="1" @checked($code->enabled)> Active</label>
                            <div class="discount-form-actions"><button>Save code</button></div>
                        </form>
                        <form method="post" action="{{ route('admin.media-settings.codes.delete', ['discountCode' => $code, 'tab' => 'discounts']) }}" style="margin-top:10px">@csrf @method('DELETE')<button type="submit" class="secondary">Remove code</button></form>
                    </div>
                </details>
            @empty
                <div class="card card-pad"><p class="muted">No discount codes have been created.</p></div>
            @endforelse
        </section>
    </div>
@elseif($activeTab === 'watermarks')
    <div class="settings-panel watermark-panel">
        <div class="watermark-intro">
            <div><h2>Watermark library</h2><p class="muted">Defaults apply automatically. A gallery can choose a different active watermark when needed.</p></div>
            <details class="create-price-list create-watermark" @if($errors->has('name') || $errors->has('gallery_type') || $errors->has('mode') || $errors->has('artwork') || $errors->has('text')) open @endif>
                <summary><span class="button create-price-list-button">+ Create watermark</span></summary>
                <div class="card card-pad create-price-list-form" style="margin-top:14px"><h3>Create watermark</h3>
                    <form method="post" action="{{ route('admin.media-watermarks.store', ['tab' => 'watermarks']) }}" enctype="multipart/form-data" class="grid" data-watermark-form>@csrf
                        <label>Name<input name="name" maxlength="100" value="{{ old('name') }}" required placeholder="DancePro competition blue"></label>
                        <label>Used for<select name="gallery_type" required><option value="competition" @selected(old('gallery_type', 'competition') === 'competition')>Competition galleries</option><option value="concert" @selected(old('gallery_type') === 'concert')>Concert galleries</option></select></label>
                        <label>Watermark type<select name="mode" required data-watermark-mode><option value="image" @selected(old('mode', 'image') === 'image')>PNG artwork</option><option value="text" @selected(old('mode') === 'text')>Repeated text</option></select></label>
                        <label data-watermark-image>PNG artwork · up to 10 MB and 6000 × 6000 px<input type="file" name="artwork" accept="image/png"><span class="muted">Large artwork is automatically optimised to a maximum 2000-pixel edge while preserving transparency.</span></label>
                        <label data-watermark-text>Text<input name="text" maxlength="100" value="{{ old('text') }}" placeholder="DANCEPRO PREVIEW DO NOT COPY"></label>
                        <label class="inline-checkbox"><input type="checkbox" name="is_default" value="1" @checked(old('is_default'))>Use as the default for this gallery type</label>
                        <div><button>Add watermark</button></div>
                    </form>
                </div>
            </details>
        </div>

        @foreach(['competition' => 'Competition', 'concert' => 'Concert'] as $type => $label)
            @php($typeWatermarks = $watermarks->where('gallery_type', $type))
            <section class="watermark-section">
                <div class="watermark-section-heading"><div><h2>{{ $label }}</h2><p class="muted">{{ $typeWatermarks->firstWhere('is_default', true)?->name ?? 'Built-in fallback currently active' }} is the current default.</p></div><span class="price-list-badge">{{ $typeWatermarks->count() }} active</span></div>
                <div class="price-list-rows">
                    @forelse($typeWatermarks as $watermark)
                        @php($assignmentCount = $watermark->assignmentCount())
                        <details class="card watermark-row">
                            <summary>
                                <img class="watermark-thumb" src="{{ route('admin.media-watermarks.preview', $watermark) }}" alt="Preview of {{ $watermark->name }}">
                                <span class="watermark-name">{{ $watermark->name }}</span>
                                <span class="watermark-summary-badges"><span class="price-list-badge">{{ $watermark->mode === 'image' ? 'PNG artwork' : 'Text' }}</span>@if($watermark->is_default)<span class="price-list-badge">Default</span>@endif</span>
                                <span class="watermark-assignment">{{ $assignmentCount }} direct {{ Str::plural('assignment', $assignmentCount) }}</span>
                                <span class="price-list-chevron" aria-hidden="true"></span>
                            </summary>
                            <div class="watermark-details">
                                <img class="watermark-preview" src="{{ route('admin.media-watermarks.preview', $watermark) }}" alt="Large preview of {{ $watermark->name }}">
                                <div><h3>{{ $watermark->name }}</h3><p class="muted">{{ $watermark->mode === 'image' ? 'PNG artwork' : 'Repeated text: '.$watermark->text }}</p><p class="muted">Used by {{ $assignmentCount }} specifically assigned {{ Str::plural('gallery or release group', $assignmentCount) }}.@if($watermark->is_default) It also applies automatically wherever no override is selected.@endif</p>
                                    <div class="watermark-actions">
                                        @if($watermark->mode === 'image')<a class="button secondary" href="{{ route('admin.media-watermarks.artwork', $watermark) }}" target="_blank" rel="noopener">View artwork</a>@endif
                                        @if(!$watermark->is_default)<form method="post" action="{{ route('admin.media-watermarks.update', ['watermark' => $watermark, 'tab' => 'watermarks']) }}">@csrf<input type="hidden" name="action" value="default"><button>Make default</button></form>@endif
                                        <form method="post" action="{{ route('admin.media-watermarks.update', ['watermark' => $watermark, 'tab' => 'watermarks']) }}">@csrf<input type="hidden" name="action" value="archive"><button class="secondary" @disabled($watermark->is_default || $assignmentCount > 0)>Archive</button></form>
                                    </div>
                                    @if($watermark->is_default)<p class="muted">Choose another default before archiving this watermark.</p>@elseif($assignmentCount > 0)<p class="muted">Remove its gallery assignments before archiving it.</p>@endif
                                </div>
                            </div>
                        </details>
                    @empty
                        <div class="card card-pad"><p class="muted">No {{ strtolower($label) }} library watermark yet. The private built-in fallback remains active.</p></div>
                    @endforelse
                </div>
            </section>
        @endforeach

        @if($archivedWatermarks->isNotEmpty())
            <details class="card settings-collapse watermark-archived">
                <summary><span class="settings-collapse-title"><h2>Archived watermarks</h2><p class="muted">Kept for history and hidden from gallery choices.</p></span><span class="settings-collapse-meta">{{ $archivedWatermarks->count() }} archived</span><span class="price-list-chevron" aria-hidden="true"></span></summary>
                <div class="settings-collapse-content">@foreach($archivedWatermarks as $watermark)<p><strong>{{ $watermark->name }}</strong> · {{ ucfirst($watermark->gallery_type) }} · {{ $watermark->mode === 'image' ? 'PNG artwork' : 'Text' }}</p>@endforeach</div>
            </details>
        @endif
    </div>
@elseif($activeTab === 'notifications')
    <div class="settings-panel notification-settings">
        <section class="card card-pad notification-intro">
            <div><h2>Customer email notifications</h2><p>Open a template to edit it beside a realistic branded preview. Order confirmations are always sent; other messages follow each customer’s preferences.</p></div>
            <span class="notification-intro-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 6.5h16v11H4z" stroke="currentColor" stroke-width="1.8"/><path d="m5 8 7 5 7-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        </section>

        @foreach([
            'receipt' => ['Order confirmation', 'Sent after a successful payment', 'Always sent', 'View order'],
            'ready' => ['Media ready', 'Sent when pending purchased media becomes ready', 'Customer preference', 'View my media'],
            'expiry' => ['Download expiry reminder', 'Sent 10 days before an undownloaded item expires', 'Customer preference', 'Download my media'],
        ] as $template => [$title, $description, $meta, $action])
            @php($subjectKey = $template.'_email_subject')
            @php($bodyKey = $template.'_email_body')
            <details class="card settings-collapse notification-collapse" data-notification-email @if($errors->has($subjectKey) || $errors->has($bodyKey)) open @endif>
                <summary>
                    <span class="notification-summary-title"><span class="notification-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 6.5h16v11H4z" stroke="currentColor" stroke-width="1.8"/><path d="m5 8 7 5 7-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span class="settings-collapse-title"><h2>{{ $title }}</h2><p class="muted">{{ $description }}</p></span></span>
                    <span class="settings-collapse-meta">{{ $meta }}</span>
                    <span class="price-list-chevron" aria-hidden="true"></span>
                </summary>
                <div class="settings-collapse-content">
                    <div class="notification-workspace">
                        <form method="post" action="{{ route('admin.media-settings.update', ['tab' => 'notifications']) }}" class="notification-editor">@csrf
                            <input type="hidden" name="section" value="notifications">
                            <input type="hidden" name="notification_template" value="{{ $template }}">
                            <label>Email subject<input name="{{ $subjectKey }}" maxlength="160" value="{{ old($subjectKey, $settings[$subjectKey]) }}" required data-notification-subject></label>
                            <label>Email message<textarea name="{{ $bodyKey }}" rows="9" maxlength="3000" required data-notification-body>{{ old($bodyKey, $settings[$bodyKey]) }}</textarea></label>
                            <p class="muted" style="margin:0">The account button, DancePro branding and staff signature are added automatically.</p>
                            <div class="notification-editor-actions"><button>Save {{ strtolower($title) }}</button></div>
                        </form>
                        <div class="notification-template-preview" aria-label="{{ $title }} preview">
                            <p class="notification-template-preview-header">Live email preview</p>
                            <article class="notification-email-card">
                                <div class="notification-email-accent"></div><div class="notification-email-brand"><img src="{{ $emailBranding['logoUrl'] }}" alt="{{ $emailBranding['businessName'] }}"></div>
                                <div class="notification-email-body"><h3 class="notification-email-subject" data-notification-preview-subject></h3><p class="notification-email-message" data-notification-preview-body></p><span class="notification-email-action">{{ $action }}</span><p class="notification-email-signature">{{ $emailBranding['signature']['signOff'] }}<br><strong>{{ $emailBranding['signature']['name'] }}</strong>@if($emailBranding['signature']['position'])<br>{{ $emailBranding['signature']['position'] }}@endif</p></div>
                                <div class="notification-email-footer"><strong>{{ $emailBranding['businessName'] }}</strong>@if($emailBranding['phone'])<br>{{ $emailBranding['phone'] }}@endif<br>{{ preg_replace('/^https?:\/\//', '', $emailBranding['website']) }}</div>
                            </article>
                        </div>
                    </div>
                </div>
            </details>
        @endforeach

        <details class="card settings-collapse" @if($errors->has('template') || $errors->has('email')) open @endif>
            <summary>
                <span class="settings-collapse-title"><h2>Send test email</h2><p class="muted">Check a saved template in an inbox before customers receive it.</p></span>
                <span class="settings-collapse-meta">Test delivery</span>
                <span class="price-list-chevron" aria-hidden="true"></span>
            </summary>
            <div class="settings-collapse-content">
                <form method="post" action="{{ route('admin.media-settings.notifications.test') }}" class="grid">@csrf
                    <label>Template<select name="template" required><option value="receipt" @selected(old('template') === 'receipt')>Order confirmation</option><option value="ready" @selected(old('template') === 'ready')>Media ready</option><option value="expiry" @selected(old('template') === 'expiry')>Download expiry reminder</option></select></label>
                    <label>Send to<input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required></label>
                    <div><button>Send test email</button></div>
                </form>
            </div>
        </details>

        <section class="card card-pad notification-history">
            <h2>Recent delivery history</h2>
            <p class="muted">The latest 30 customer and test emails handed to the mail queue.</p>
            @if($notificationHistory->isEmpty())
                <p class="muted">No media emails have been queued yet.</p>
            @else
                <div class="notification-history-list">
                    @foreach($notificationHistory as $delivery)
                        <div class="notification-history-row">
                            <div><strong>{{ match($delivery->type) { 'receipt' => 'Order confirmation', 'media_ready' => 'Media ready', 'expiry_reminder' => 'Expiry reminder', default => 'Test email' } }}</strong><br><span class="muted">{{ ($delivery->sent_at ?? $delivery->queued_at)->format('j M Y, g:i a') }}</span></div>
                            <div class="notification-history-recipient">@if($delivery->user_id)<a href="{{ route('admin.customers.show', $delivery->user_id) }}">{{ $delivery->recipient_email }}</a>@else{{ $delivery->recipient_email }}@endif</div>
                            <div class="notification-history-subject">{{ $delivery->subject }}@if($delivery->order)<br><span class="muted">Order {{ $delivery->order->displayNumber() }}</span>@endif</div>
                            <span class="notification-status {{ $delivery->status }}">{{ $delivery->status }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@else
    <div class="settings-panel">
        <section class="card card-pad">
            <h2>Customer help</h2>
            <p class="muted">Introductory text shown on the customer Help page. Keep it consistent with current ordering, access and download processes.</p>
            <form method="post" action="{{ route('admin.media-settings.update', ['tab' => 'help']) }}" class="grid">@csrf<input type="hidden" name="section" value="help">
                <label>Help content<textarea name="help_content" rows="14" required>{{ old('help_content', $settings['help_content']) }}</textarea></label>
                <div><button>Save help</button></div>
            </form>
        </section>
        <section class="card card-pad"><h2>Help insights</h2><div class="grid two-col"><div><h3>Searches with no answer</h3>@forelse($helpInsights['unanswered'] as $search)<p><strong>{{ $search->query }}</strong><br><span class="muted">{{ $search->searches }} {{ Str::plural('search', $search->searches) }} · last searched {{ \Carbon\Carbon::parse($search->last_searched_at)->format('j M Y') }}</span></p>@empty<p class="muted">No unanswered searches yet.</p>@endforelse</div><div><h3>Article feedback</h3>@foreach($helpInsights['articleFeedback'] as $article)<p><strong>{{ $article->title }}</strong><br><span class="muted">{{ $article->helpful_count }} helpful · {{ $article->unhelpful_count }} not helpful</span></p>@endforeach</div></div></section>
    </div>
@endif
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
    document.querySelectorAll('[data-discount-form]').forEach(form => {
        const type = form.querySelector('[data-discount-type]');
        const value = form.querySelector('[data-discount-value]');
        const unit = form.querySelector('[data-discount-unit]');
        const symbol = form.querySelector('[data-discount-symbol]');
        const valueWrap = value?.closest('.discount-value-wrap');
        if (! type || ! value || ! unit || ! symbol || ! valueWrap) return;

        const updateDiscountInput = () => {
            const fixed = type.value === 'fixed';
            unit.textContent = fixed ? '($)' : '(%)';
            symbol.textContent = fixed ? '$' : '%';
            valueWrap.classList.toggle('is-fixed', fixed);
            value.min = fixed ? '0.01' : '1';
            value.max = fixed ? '100000' : '90';
            value.step = fixed ? '0.01' : '1';
        };

        type.addEventListener('change', updateDiscountInput);
        updateDiscountInput();
    });

    document.querySelectorAll('[data-watermark-form]').forEach(form => {
        const mode = form.querySelector('[data-watermark-mode]');
        const imageField = form.querySelector('[data-watermark-image]');
        const textField = form.querySelector('[data-watermark-text]');
        if (! mode || ! imageField || ! textField) return;

        const updateWatermarkFields = () => {
            const usesImage = mode.value === 'image';
            imageField.hidden = ! usesImage;
            textField.hidden = usesImage;
        };

        mode.addEventListener('change', updateWatermarkFields);
        updateWatermarkFields();
    });

    document.querySelectorAll('[data-notification-email]').forEach(template => {
        const subject = template.querySelector('[data-notification-subject]');
        const body = template.querySelector('[data-notification-body]');
        const subjectPreview = template.querySelector('[data-notification-preview-subject]');
        const bodyPreview = template.querySelector('[data-notification-preview-body]');
        if (! subject || ! body || ! subjectPreview || ! bodyPreview) return;

        const updatePreview = () => {
            subjectPreview.textContent = subject.value;
            bodyPreview.textContent = body.value;
        };
        subject.addEventListener('input', updatePreview);
        body.addEventListener('input', updatePreview);
        updatePreview();
    });
</script>
@endpush
