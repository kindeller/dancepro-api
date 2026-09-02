<section class="training-section card card-pad grid" draggable="true">
    <div class="toolbar"><div><strong><span class="drag-handle" title="Drag to reorder">⋮⋮</span> Course section</strong><p class="muted">Group related content into a clear part of the course.</p></div><button type="button" class="secondary remove-section">Remove section</button></div>
    <div class="grid two-col"><label>Section title<input data-section-field="title" value="{{ data_get($section, 'title', 'Course content') }}" required></label><label>Section introduction<input data-section-field="description" value="{{ data_get($section, 'description') }}"></label></div>
    <div class="block-list grid">@foreach(data_get($section, 'blocks', []) as $block) @include('admin.crew-management._training-block', compact('block')) @endforeach</div>
    <button type="button" class="secondary add-block">Add content block</button>
</section>
