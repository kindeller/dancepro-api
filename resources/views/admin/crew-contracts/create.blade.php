@extends('layouts.admin', ['title' => $sourceContract ? 'Duplicate crew contract' : 'Add crew contract', 'heading' => $sourceContract ? 'Duplicate contract version' : 'Add contract version', 'subheading' => $sourceContract ? 'Review and edit the copied contract before creating a new version.' : 'Create a versioned contract that can be assigned to crew records.'])

@section('content')
    @include('admin.crew-management._tabs')
    @if($sourceContract)<div class="notice">Duplicating <strong>{{ $sourceContract->name }}</strong>, version {{ $sourceContract->version }}. Enter a new version before saving.</div>@endif
    <form method="POST" action="{{ route('admin.crew-contracts.store') }}">
        @csrf
        <div class="grid two-col">
            <div class="grid">
                <label>Contract name<input name="name" value="{{ old('name', $sourceContract?->name) }}" required maxlength="255" placeholder="Crew services agreement"></label>
                <label>Version<input name="version" value="{{ old('version') }}" required maxlength="100" placeholder="2026.1"></label>
                <label>Status<select name="status" required>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', 'draft') === $status->value)>{{ ucfirst($status->value) }}</option>@endforeach</select></label>
                <label>Effective from<input type="date" name="effective_from" value="{{ old('effective_from') }}"></label>
            </div>
            <div><label>Contract text</label><div class="contract-toolbar" role="toolbar" aria-label="Contract formatting">
                <button type="button" data-command="formatBlock" data-value="p">Paragraph</button>
                <button type="button" data-command="formatBlock" data-value="h2">Heading</button>
                <button type="button" data-command="bold"><strong>Bold</strong></button>
                <button type="button" data-command="italic"><em>Italic</em></button>
                <button type="button" data-command="insertUnorderedList">• List</button>
                <button type="button" data-command="insertOrderedList">1. List</button>
                <button type="button" id="contract-link-button">Link</button>
            </div><div id="contract-editor" class="contract-editor" contenteditable="true" role="textbox" aria-multiline="true">@if(old('content') !== null){{ old('content') }}@elseif($sourceContract){!! $sourceContract->content !!}@endif</div><textarea id="contract-content" name="content" required hidden>{{ old('content', $sourceContract?->content) }}</textarea><p class="muted">Paste your contract here, then use the toolbar for headings, emphasis, lists and links. Unsafe formatting is removed when saved.</p></div>
        </div>
        <div style="margin-top:16px"><button type="submit">Create contract version</button> <a class="button secondary" href="{{ route('admin.crew-contracts.index') }}">Cancel</a></div>
    </form>
@endsection

@push('scripts')
<style>.contract-toolbar{display:flex;gap:5px;flex-wrap:wrap;padding:7px;border:1px solid var(--line);border-bottom:0;border-radius:4px 4px 0 0;background:#f4f8fa}.contract-toolbar button{min-height:30px;padding:4px 8px;background:white;color:var(--ink);border:1px solid var(--line)}.contract-editor{min-height:430px;padding:18px;border:1px solid var(--line);border-radius:0 0 4px 4px;background:white;line-height:1.6;overflow:auto}.contract-editor:focus{outline:2px solid #8bd8f4;outline-offset:-2px}.contract-editor h2,.contract-editor h3{margin-top:1.2em}</style>
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
    const contractForm = document.querySelector('form[action="{{ route('admin.crew-contracts.store') }}"]');
    const contractEditor = document.getElementById('contract-editor');
    const contractContent = document.getElementById('contract-content');

    document.querySelectorAll('.contract-toolbar [data-command]').forEach(button => button.addEventListener('click', () => {
        contractEditor.focus();
        document.execCommand(button.dataset.command, false, button.dataset.value || null);
    }));
    document.getElementById('contract-link-button').addEventListener('click', () => {
        const url = window.prompt('Paste the web address for this link');
        if (url) {
            contractEditor.focus();
            document.execCommand('createLink', false, url);
        }
    });
    contractForm.addEventListener('submit', () => contractContent.value = contractEditor.innerHTML);
</script>
@endpush
