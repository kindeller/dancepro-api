@extends('layouts.admin', ['title' => 'Crew Payments · Payment Settings', 'heading' => 'Crew Payments', 'subheading' => 'Crew-specific rates in one compact table.'])

@section('content')
@include('admin.timesheets._tabs')
<div class="notice"><strong>Current local amounts are temporary placeholders.</strong> Replace them with confirmed DancePro rates before launch. Saving with a new effective date preserves older event calculations.</div>

<form method="POST" action="{{ route('admin.payments.matrix.store') }}" style="margin-top:16px">
    @csrf
    <div class="toolbar" style="margin-bottom:12px">
        <label style="max-width:220px">Rates effective from<input type="date" name="effective_from" value="{{ today()->toDateString() }}" required></label>
        <button>Save all crew rates</button>
    </div>
    <div class="card" style="overflow:auto;max-height:calc(100vh - 250px)">
        <table style="min-width:max-content">
            <thead style="position:sticky;top:0;z-index:2;background:#fff"><tr><th style="position:sticky;left:0;z-index:3;background:#fff;min-width:240px">Rate type</th>@foreach($crew as $person)<th style="min-width:130px;text-align:center">{{ $person->preferred_name ?: $person->user->name }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach($catalog as $matrixKey => [$name,$type,$rateKeys])
                <tr>
                    <th style="position:sticky;left:0;z-index:1;background:#fff;text-align:left"><strong>{{ $name }}</strong><div class="muted">{{ $type==='hourly'?'Per hour':ucfirst($type) }}</div></th>
                    @foreach($crew as $person)
                        @php($rate=$rates->get($person->id.'|'.$rateKeys[0]))
                        <td><label style="display:flex;align-items:center;gap:4px"><span>$</span><input aria-label="{{ $name }} for {{ $person->preferred_name }}" type="number" name="rates[{{ $person->id }}][{{ $matrixKey }}]" min="0" max="999999.99" step="0.01" value="{{ old('rates.'.$person->id.'.'.$matrixKey,$rate?->amount) }}" placeholder="—" style="min-width:95px"></label></td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</form>
<p class="muted" style="margin-top:10px">Blank cells fall back to the temporary general rate until a crew-specific amount is entered. Superable classification follows the rate type and does not add super to the preview total.</p>
@endsection
