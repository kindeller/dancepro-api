@extends('layouts.admin', ['title' => 'Programme preparation', 'heading' => 'Programme preparation', 'subheading' => $event->name])

@section('content')
<style>
    .programme-workspace { display:grid; grid-template-columns:minmax(280px, .85fr) minmax(420px, 1.4fr); gap:20px; align-items:start; }
    .programme-preview { position:sticky; top:16px; }
    .programme-preview iframe { width:100%; height:72vh; border:1px solid var(--line); background:white; }
    .programme-node { border:1px solid var(--line); border-radius:8px; padding:12px; margin-top:12px; background:white; min-width:0; }
    .programme-node[data-kind="day"], .programme-node[data-kind="group"] { background:var(--soft); }
    .programme-fields { display:grid; grid-template-columns:90px minmax(120px, 1fr); gap:10px; }
    .programme-fields label { min-width:0; }
    .programme-fields input, .programme-fields select { box-sizing:border-box; width:100%; }
    .programme-children { margin-left:12px; }
    .programme-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
    .programme-node button { padding:6px 10px; }
    .programme-item-row { padding:6px 8px; margin-top:5px; border-radius:6px; }
    .programme-item-line { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
    .programme-item-line label { margin:0; min-width:0; }
    .programme-folder-number { flex:0 0 40px; text-align:center; font-variant-numeric:tabular-nums; font-weight:600; color:var(--muted); }
    .programme-input-number { flex:0 0 48px; }
    .programme-input-title { flex:1 1 120px; }
    .programme-input-class_name { flex:.8 1 110px; }
    .programme-item-line input { width:100%; box-sizing:border-box; padding:7px 8px; margin:0; }
    .programme-row-actions { display:flex; gap:4px; align-items:center; flex-shrink:0; }
    .programme-row-actions button { padding:5px 8px; min-width:30px; }
    .programme-item-details { margin-top:8px; }
    .programme-item-line > .programme-item-details { display:contents; }
    .programme-item-line > .programme-item-details > summary { display:list-item; font-size:12px; cursor:pointer; margin-left:4px; }
    .programme-item-line > .programme-item-details > .grid { flex:1 0 100%; padding:8px 0; }
    .programme-item-line > .programme-item-details:not([open]) > :not(summary) { display:none; }
    .programme-controls { position:sticky; bottom:0; background:var(--panel); padding:14px 0; border-top:1px solid var(--line); z-index:2; }
    #programme-status { white-space:pre-line; }
    #programme-editor fieldset { padding:0; border:0; min-width:0; }
    @media(max-width:1100px) { .programme-workspace { grid-template-columns:minmax(0,1fr); } .programme-preview { position:static; } .programme-preview iframe { height:420px; } }
</style>
<div class="toolbar" style="margin-bottom:20px">
    <div><p class="eyebrow">Programme preparation</p><h1>{{ $event->name }}</h1><p class="muted">{{ $event->eventCode() }} · {{ ucfirst($event->event_type->value) }}</p></div>
    <div class="programme-actions">@if($event->event_type->value === 'concert' && $programme)<a class="button secondary" href="{{ route('admin.concert-title-cards.show', $event) }}">Video title cards</a>@endif<a class="button secondary" href="{{ route('admin.scheduling-events.show', $event) }}">Back to event</a></div>
</div>
@if($event->cancelled_at)
    <p class="notice">This event is cancelled. Programme editing and preparation downloads are unavailable.</p>
@endif
@if(! $programme)
    <section class="card card-pad"><h2>Upload your programme</h2>
        <p>We'll read the PDF and build a running order for you to check and approve.</p>
        @can('updateProgramme', $event)
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.programmes.start', $event) }}" class="grid">
            @csrf
            <label>Programme PDF (up to 20 MB)<input type="file" name="programme" accept="application/pdf" required></label>
            <button>Upload and read programme</button>
        </form>
        <form method="POST" action="{{ route('admin.programmes.start', $event) }}" style="margin-top:16px">@csrf
            <button class="secondary">{{ $event->programme_path ? 'Read attached programme' : 'Enter running order manually' }}</button>
        </form>
        @endcan
    </section>
@else
@if($extraction)
    <section class="card card-pad" style="margin-bottom:20px" id="extraction-progress" data-status="{{ $extraction->status }}" data-url="{{ route('admin.programmes.extraction', $event) }}">
        <h2>{{ in_array($extraction->status, ['queued', 'processing']) ? 'Reading your programme…' : 'Programme review' }}</h2>
        <p role="status">{{ $extraction->message ?? 'The PDF is being read in the background. Scanned pages can take a little longer. This page will update when it is ready.' }}</p>
        @if($extraction->result)
            <details open><summary>What to check</summary><ul>@foreach($extraction->result['issues'] ?? [] as $issue)<li>{{ $issue }}</li>@endforeach</ul></details>
            @if($extraction->status === 'review')
                <details><summary>View extracted suggestions</summary>
                    <p>Your saved rows and identities are preserved. Use these suggestions to correct the running order.</p>
                    <ol>@foreach($extraction->result['nodes'] ?? [] as $suggestion)<li>{{ $suggestion['number'] ?? '' }} {{ $suggestion['title'] }} @if(!empty($suggestion['class_name'])) · {{ $suggestion['class_name'] }} @endif (PDF page {{ $suggestion['source_page'] }})</li>@endforeach</ol>
                </details>
            @endif
        @endif
    </section>
@endif
<div class="programme-workspace">
    <aside class="programme-preview card card-pad">
        <h2>Source programme</h2>
        @if($source)
            <p><a href="{{ route('admin.programmes.source', [$event, $source]) }}" target="_blank" rel="noopener">{{ $source->name }} ↗</a></p>
            <p class="muted">Uploaded {{ $source->created_at->format('j M Y, g:i a') }} by {{ $source->createdBy?->name ?? 'former staff member' }}</p>
            <iframe id="programme-pdf" title="Source programme PDF" src="{{ route('admin.programmes.source', [$event, $source]) }}"></iframe>
        @else
            <p class="muted">No source PDF retained. You can enter the programme manually or upload a PDF below.</p>
        @endif
        @can('updateProgramme', $event)
        @if($source && !in_array($extraction?->status, ['queued', 'processing']))
            <form method="POST" action="{{ route('admin.programmes.start', $event) }}">@csrf<button>Read PDF again</button></form>
        @endif
        <details style="margin-top:16px" @if(!$source) open @endif><summary>{{ $source ? 'Use a replacement PDF' : 'Upload source PDF' }}</summary>
            <form id="programme-source-form" class="grid" method="POST" enctype="multipart/form-data" action="{{ route('admin.programmes.upload', $event) }}" style="margin-top:12px">
                @csrf<input type="hidden" name="version" value="{{ $programme->draft_version }}">
                <label>Programme PDF (up to 20 MB)<input type="file" name="programme" accept="application/pdf" required></label>
                <p class="muted">Existing rows remain for review. Previous confirmed versions keep their original PDF.</p><button>Upload and read programme</button>
            </form>
        </details>
        @endcan
        <details style="margin-top:16px"><summary>Confirmed versions ({{ $revisions->count() }})</summary>
            @forelse($revisions as $revision)
                <p><strong>Version {{ $revision->version }}</strong><br>{{ $revision->confirmed_at->format('j M Y, g:i a') }} · {{ $revision->confirmedBy?->name ?? 'Former staff member' }}<br>
                    @if(! $event->cancelled_at)<a href="{{ route('admin.programmes.snapshot', [$event, $revision]) }}" target="_blank" rel="noopener">Preparation snapshot</a>@endif
                    @if($revision->programme_source_id)
                        @if($revision->source) · <a href="{{ route('admin.programmes.source', [$event, $revision->source]) }}" target="_blank" rel="noopener">Source PDF</a>@endif
                    @endif
                </p>
            @empty<p class="muted">No confirmed version yet.</p>@endforelse
        </details>
    </aside>
    <section id="programme-editor" class="card card-pad">
        <div class="toolbar"><h2>Running order</h2><span id="programme-version" class="badge">Draft {{ $programme->draft_version }}</span></div>
        <p class="muted">{{ $event->event_type->value === 'competition' ? 'Keep the printed section and competitor numbers, including gaps. Add all performers in a duo or group to one entry.' : 'Folder numbers update automatically: 01, 02, 03… Keep the item title and class name in their separate fields. Printed programme numbers are under More. Folder names omit punctuation; display titles keep it. Breaks do not create folders.' }}</p>
        <p class="muted">Check the running order against the PDF. Changes save automatically; approve when everything is correct.</p>
        <noscript><p>This editor needs JavaScript. Enable it to add and review programme items.</p></noscript>
        <fieldset id="programme-fields" @disabled($event->cancelled_at !== null)>
            <div id="programme-add" class="programme-actions"></div>
            <div id="programme-rows"></div>
            <div class="programme-controls">
                <p id="programme-status" role="status" aria-live="polite">{{ $revisions->first()?->version === $programme->draft_version ? 'This version is confirmed.' : 'Draft changes become available for preparation only after confirmation.' }}</p>
                <div class="programme-actions"><button id="programme-save" type="button">Save draft</button><button id="programme-confirm" type="button" class="secondary">Approve programme</button></div>
            </div>
        </fieldset>
    </section>
</div>
@php
    $editorConfig = [
        'nodes' => $nodes,
        'version' => $programme->draft_version,
        'competition' => $event->event_type->value === 'competition',
        'cancelled' => $event->cancelled_at !== null,
        'saveUrl' => route('admin.programmes.save', $event),
        'confirmUrl' => route('admin.programmes.confirm', $event),
        'csrf' => csrf_token(),
        'extracting' => in_array($extraction?->status, ['queued', 'processing']),
    ];
@endphp
<script type="application/json" id="programme-config">@json($editorConfig)</script>
@push('scripts')
    @vite('resources/js/programme-editor.js')
@endpush
@endif
@endsection
