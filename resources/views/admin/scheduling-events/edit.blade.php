@extends('layouts.admin', ['title' => 'Edit event', 'heading' => 'Edit '.$event->name, 'subheading' => 'Times and availability settings remain independent for each shift.'])
@section('content')
@include('admin.event-management._tabs')
<div class="competition-programme card card-pad">
    <div><strong>Competition programme</strong><div class="muted">One programme is shared across every shift in this competition.</div></div>
    @if($event->programme_path)
        <details class="programme-menu"><summary class="programme-icon attached" title="Programme attached" aria-label="Programme attached">▤</summary><div><a href="{{ $event->programmeUrl() }}" target="_blank">Open programme</a><button type="button" data-programme-picker="competition-programme-upload">Replace programme</button></div></details>
    @else
        <button class="programme-icon" type="button" title="Attach Programme" aria-label="Attach Programme" data-programme-picker="competition-programme-upload"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.5 12.5 14.8 6.2a3 3 0 0 1 4.2 4.2l-8.1 8.1a5 5 0 0 1-7.1-7.1l8.5-8.5M7.1 14l8.2-8.2"/></svg></button>
    @endif
</div>
<form class="programme-upload-form" id="competition-programme-upload" method="POST" enctype="multipart/form-data" action="{{ route('admin.scheduling-events.operations.update', $event) }}">@csrf @method('PUT')<input type="file" name="programme" accept="application/pdf,.pdf" required data-programme-input></form>
<form method="POST" action="{{ route('admin.scheduling-events.update', $event) }}" enctype="multipart/form-data">@csrf @method('PUT') @include('admin.scheduling-events._form', ['submitLabel' => 'Save event'])</form>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
    document.querySelectorAll('[data-programme-picker]').forEach((button) => button.addEventListener('click', () => document.querySelector(`#${CSS.escape(button.dataset.programmePicker)} [data-programme-input]`)?.click()));
    document.querySelectorAll('[data-programme-input]').forEach((input) => input.addEventListener('change', () => { if (input.files.length) input.form.submit(); }));
</script>
@endpush

@push('styles')
<style nonce="{{ request()->attributes->get('csp_nonce') }}">
    .competition-programme{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:20px;max-width:760px}.programme-icon{display:inline-grid;place-items:center;width:34px;height:34px;min-height:34px;padding:0;border:1px solid #b9c8d0;border-radius:7px;background:#fff;color:#52636c;font-size:18px;line-height:1;cursor:pointer}.programme-icon svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.programme-icon:hover,.programme-icon.attached{border-color:#2478a8;color:#17658f;background:#edf7fc}.programme-menu{position:relative}.programme-menu summary{list-style:none}.programme-menu summary::-webkit-details-marker{display:none}.programme-menu>div{position:absolute;z-index:20;right:0;top:40px;min-width:165px;padding:6px;border:1px solid #c8d3d9;border-radius:8px;background:#fff;box-shadow:0 8px 24px rgba(16,32,42,.16)}.programme-menu a,.programme-menu button{display:block;width:100%;padding:8px 10px;border:0;border-radius:5px;background:transparent;color:#173847;text-align:left;text-decoration:none;font:inherit;cursor:pointer}.programme-menu a:hover,.programme-menu button:hover{background:#edf4f7}.programme-upload-form{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
</style>
@endpush
