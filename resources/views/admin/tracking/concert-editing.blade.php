@extends($editingLayout, ['title' => $definition['numbered_label'], 'heading' => $event->studio?->name ?? $event->name, 'subheading' => $event->name])

@if($editingLayout === 'layouts.crew')
@push('styles')
<style>
main .tracker-bar{top:var(--editing-header-height,0px);--ink:#141719}
.tracker-bar-dialog header{position:static;background:transparent;color:inherit;box-shadow:none}
</style>
@endpush
@push('scripts')
<script>
(() => {
    const header = document.querySelector('body > header');
    if (header) {
        const resize = () => document.documentElement.style.setProperty('--editing-header-height', `${header.offsetHeight}px`);
        resize();
        new ResizeObserver(resize).observe(header);
    }
})();
</script>
@endpush
@endif

@section('content')
@if($editingLayout === 'layouts.crew')
@include('crew._brand')
@endif
@include('admin.tracking._task-bar', ['bar' => $trackerBar])
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
<section class="card card-pad" style="width:100%;min-width:0;padding:24px">
    <h2>Concert editing</h2>
    <p>{{ $event->name }} · {{ $event->event_date?->format('j M Y') }}</p>
    @if($status['sourceDriven'])
        <p>{{ $status['detail'] }}</p>
    @else
        <p>@if($editingLayout === 'layouts.admin')Assign the editor using the Editor box above. @endif When the concert edit is ready for quality checking, mark editing complete.</p>
        <p>Check the programme order, video titles and any supplied studio videos or slideshows as part of the edit.</p>
        <section aria-label="Editing downloads" style="margin:24px 0">
            <h3>Downloads</h3>
            <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px">
                @if($editingDownloads['programme'])
                    <a class="button secondary" href="{{ route('concert-editing.download', [$event, 'kind' => 'programme']) }}">Download programme</a>
                @else
                    <span class="muted">Programme not uploaded yet.</span>
                @endif
                @if($editingDownloads['titles'])
                    <a class="button secondary" href="{{ route('concert-editing.download', [$event, 'kind' => 'titles']) }}">Download video titles (ZIP)</a>
                @else
                    <span class="muted">Video titles are not ready. Publish the current titles in 2.3.</span>
                @endif
            </div>
            <h3>Studio videos &amp; slideshows</h3>
            @forelse($editingDownloads['videos'] as $file)
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:12px 0;border-bottom:1px solid var(--line)">
                    <span style="overflow-wrap:anywhere;min-width:0">{{ $file->original_name }} <small class="muted">· {{ ucfirst(str_replace('_', ' ', $file->status)) }}</small></span>
                    <a class="button secondary" href="{{ route('concert-editing.download', [$event, 'kind' => 'video', 'file' => $file->uuid]) }}" aria-label="Download {{ $file->original_name }}">Download video</a>
                </div>
            @empty
                <p class="muted">{{ $editingLayout === 'layouts.crew' ? 'No approved studio videos or slideshows are available yet.' : 'No studio videos or slideshows uploaded yet.' }}</p>
            @endforelse
        </section>
        @if($editingLayout === 'layouts.admin')
            <div class="toolbar" style="display:flex;flex-wrap:wrap;gap:10px;margin:20px 0">
                <a class="button secondary" href="{{ route('admin.programmes.show', $event) }}">Programme</a>
                <a class="button secondary" href="{{ route('admin.concert-title-cards.show', $event) }}">Video titles</a>
                <a class="button secondary" href="{{ route('admin.concert-tracker.tasks.show', [$event, 'slideshow_received']) }}">Studio videos &amp; slideshows</a>
            </div>
        @endif
        @if($status['key'] === 'complete')
            <p role="status" style="color:#126236"><strong>Editing complete</strong><br>{{ $task->completedBy?->name ?? 'Former staff member' }} · {{ $task->completed_at?->format('j M Y, g:i a') }}</p>
            <p>The edit is ready for quality checking. Video approval and release are separate tasks.</p>
        @endif
        <form method="POST" action="{{ $trackerBar['updateUrl'] }}" style="display:flex;gap:12px;flex-wrap:wrap;margin-top:20px">
            @csrf @method('PATCH')
            <input type="hidden" name="panel" value="status">
            @if($status['key'] === 'complete')
                <button class="secondary" name="status_override" value="in_progress">Reopen editing</button>
            @else
                @if($status['key'] !== 'in_progress')<button class="secondary" name="status_override" value="in_progress">Start editing</button>@endif
                <button name="status_override" value="complete">Mark editing complete</button>
            @endif
        </form>
    @endif
</section>
@endsection
