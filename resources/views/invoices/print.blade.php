<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Invoice {{ $invoice->invoice_number }}</title>
<style>
@if (!($pdf ?? false))
@foreach (['DejaVu Sans' => 'DejaVuSans', 'DejaVu Serif' => 'DejaVuSerif'] as $family => $file)
@font-face { font-family:"{{ $family }}"; font-weight:normal; src:url("data:font/ttf;base64,{{ base64_encode(file_get_contents(base_path('vendor/dompdf/dompdf/lib/fonts/'.$file.'.ttf'))) }}") format("truetype"); }
@font-face { font-family:"{{ $family }}"; font-weight:bold; src:url("data:font/ttf;base64,{{ base64_encode(file_get_contents(base_path('vendor/dompdf/dompdf/lib/fonts/'.$file.'-Bold.ttf'))) }}") format("truetype"); }
@endforeach
@endif
@page { size:A4 portrait; margin:12mm; }
* { box-sizing:border-box; }
body { margin:0; color:#17202a; background:#fff; font:10pt/1.25 Arial,sans-serif; }
.toolbar { max-width:186mm; margin:12px auto; }
.toolbar button { padding:8px 14px; }
.sheet { width:100%; margin:0; padding:0; }
.top { display:table; width:100%; border-bottom:2px solid #243b64; padding-bottom:5px; }

.top>div { display:table-cell; vertical-align:top; width:55%; }
.top .invoice-meta { width:45%; text-align:right; }
.invoice-meta strong { display:inline; font-size:9pt; }
.invoice-meta { font-size:8pt; line-height:1.4; }
h1 { margin:0; color:#243b64; font-size:16pt; }
h2 { margin:0 0 2px; font-size:10pt; }
h3 { margin:0 0 2px; color:#243b64; font-size:9pt; text-transform:uppercase; }
p { margin:2px 0; }
.parties { display:table; width:100%; margin:7px 0; font-size:8pt; line-height:1.15; }
.box { display:table-cell; vertical-align:top; width:50%; padding:7px; background:#f0f4f9; }

table { width:100%; table-layout:fixed; border-collapse:collapse; margin-top:8px; }
thead { display:table-header-group; }
tr { page-break-inside:avoid; }
th { color:#fff; background:#243b64; text-align:left; padding:6px 4px; font-size:8pt; }
td { padding:7px 4px; border-bottom:1px solid #d8dde3; vertical-align:top; overflow-wrap:anywhere; word-wrap:break-word; }
th:nth-child(1) { width:17%; } th:nth-child(2) { width:45%; } th:nth-child(3) { width:12%; } th:nth-child(4),th:nth-child(5) { width:13%; }
.number { text-align:right; }
.total { margin:14px 0 14px auto; width:42%; padding:7px; background:#f0f4f9; font-size:12pt; page-break-inside:avoid; }
.total strong:last-child { float:right; }
.payment { display:table; width:100%; border-top:1px solid #243b64; padding-top:10px; page-break-inside:avoid; }
.payment>div { display:table-cell; width:50%; vertical-align:top; }
.note { margin-top:8px; font-size:8pt; color:#56606b; }
/* Classic: formal serif headings, double rules, unshaded ledger. */
.classic h1,.classic h2 { font-family:"DejaVu Serif",serif; font-weight:normal; }
.classic h1 { font-size:18pt; letter-spacing:1pt; }
.classic .top { border-bottom:3px double #243b64; }
.classic .box { background:transparent; padding:5px 0; }
.classic th { background:transparent; color:#243b64; border-top:1px solid #243b64; border-bottom:1px solid #243b64; }
.classic .total { background:transparent; border-top:3px double #243b64; border-bottom:1px solid #243b64; padding:7px 0; }
/* Minimal: monochrome typography and fine lines, no blocks or ornament. */
.minimal { font-family:"DejaVu Sans",sans-serif; font-size:9pt; }
.minimal h1 { color:#202020; font-size:15pt; font-weight:normal; letter-spacing:2pt; }
.minimal h2 { font-size:9pt; font-weight:normal; }
.minimal h3 { color:#666; font-size:7pt; font-weight:normal; letter-spacing:1pt; }
.minimal .top { border-bottom:0; }
.minimal .box { padding:5px 0; background:transparent; }
.minimal th { color:#666; background:transparent; font-weight:normal; font-size:7pt; border-bottom:1px solid #999; }
.minimal td { border-bottom:1px solid #eee; padding:5px 4px; line-height:1.1; }
.minimal .total { width:100%; background:transparent; border-top:1px solid #202020; padding:7px 0; font-size:12pt; }
.minimal .payment { border-top:1px solid #ddd; font-size:8pt; }
/* Modern: bold asymmetrical header, accent rail and a high-contrast total. */
.modern { font-family:"DejaVu Sans",sans-serif; font-size:9pt; }
.modern .top { border-bottom:0; border-left:5px solid #176b6b; }
.modern .top>div:first-child { padding-left:10px; }
.modern h1 { color:#176b6b; font-size:18pt; letter-spacing:-0.5pt; }
.modern h2 { font-size:9pt; }
.modern h3 { color:#176b6b; font-size:7pt; letter-spacing:1pt; }
.modern .box { background:transparent; padding:5px 0; }
.modern th { background:#176b6b; color:#fff; font-size:7pt; }
.modern tbody tr:nth-child(even) { background:#edf6f4; }
.modern td { border-bottom:0; padding:5px 4px; line-height:1.1; }
.modern .total { width:48%; background:#176b6b; color:#fff; font-size:12pt; padding:9px; }
.modern .payment { border-top:2px solid #176b6b; font-size:8pt; }
@media screen { body { background:#eef1f4; }.sheet { max-width:210mm; margin:auto; padding:12mm; background:#fff; } }
@media print { .toolbar { display:none; } }
</style></head>
@php($issuer = $invoice->issuer_snapshot ?? \App\Features\Timesheets\Support\InvoiceIssuerSnapshot::fromCrewProfile($invoice->crewProfile))
<body class="{{ $invoice->invoice_style }}"><div class="toolbar"><span>A4 portrait preview · {{ ucfirst($invoice->invoice_style) }} style</span><button onclick="window.print()">Print / Save as PDF</button></div><main class="sheet">
<header class="top"><div><h1>INVOICE</h1></div><div class="invoice-meta"><span>Invoice </span><strong>{{ $invoice->invoice_number }}</strong><br><span>Issued </span><strong>{{ ($invoice->submitted_at ?? $invoice->created_at)->format('j F Y') }}</strong></div></header>
<section class="parties"><div class="box"><h3>From</h3><h2>{{ $issuer['legal_name'] }}</h2><p>{{ $issuer['address_line_1'] }}</p>
@if ($issuer['address_line_2'])<p>{{ $issuer['address_line_2'] }}</p>@endif
<p>{{ $issuer['suburb'] }} {{ $issuer['state'] }} {{ $issuer['postcode'] }}</p><p>{{ $issuer['phone'] }}</p><p>ABN {{ $issuer['abn'] }}</p></div><div class="box"><h3>Bill to</h3><h2>DancePro Photography &amp; Video</h2><p>accounts@dancepro.com.au</p><p>ABN 77 221 718 867</p></div></section>
<table><thead><tr><th>Date</th><th>Description and time</th><th class="number">Quantity</th><th class="number">Unit price</th><th class="number">Total</th></tr></thead><tbody>
@foreach ($invoice->lines as $line)
<tr><td>{{ \Carbon\Carbon::parse($line->snapshot['date'])->format('D j M Y') }}</td><td><strong>{{ $line->snapshot['event'] }}</strong><br>{{ $line->snapshot['role'] }}
@if (data_get($line->snapshot, 'start') || data_get($line->snapshot, 'finish'))<br>{{ data_get($line->snapshot, 'start', '—') }}–{{ data_get($line->snapshot, 'finish', '—') }}@endif
@if ((float) $line->allowance_amount > 0)<br>Includes ${{ number_format((float) $line->allowance_amount, 2) }} allowances
@endif
</td><td class="number">{{ data_get($line->snapshot, 'hours') !== null ? number_format((float) data_get($line->snapshot, 'hours'), 2).' hrs' : '1' }}</td><td class="number">${{ number_format((float) data_get($line->snapshot, 'rate'), 2) }}</td><td class="number">${{ number_format((float) $line->line_total, 2) }}</td></tr>
@endforeach
</tbody></table>
<div class="total"><strong>Total due</strong><strong>${{ number_format((float)$invoice->total,2) }}</strong></div><section class="payment"><div><h3>Payment details</h3><p><strong>Account name:</strong> {{ $issuer['bank_account_name'] }}</p><p><strong>Bank:</strong> {{ $issuer['bank_name'] }}</p></div><div><h3>&nbsp;</h3><p><strong>BSB:</strong> {{ $issuer['bank_bsb'] }}</p><p><strong>Account number:</strong> {{ $issuer['bank_account_number'] }}</p></div></section><p class="note">Payment is due within 10 working days. Please use invoice number {{ $invoice->invoice_number }} as the payment reference.</p>
</main></body></html>
