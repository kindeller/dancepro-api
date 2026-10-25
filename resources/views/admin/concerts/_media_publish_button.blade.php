@php
    $publishing = $collection->status->value !== 'published';
    $ready = $collection->assets->filter(fn ($asset) => $asset->status->value === 'available' && $asset->verified_at !== null);
    $hiddenCount = $ready->where('is_visible', false)->count();
    $unfinishedCount = $collection->assets->count() - $ready->count();
    $warning = [];
    if ($hiddenCount > 0) {
        $warning[] = $hiddenCount.' verified '.\Illuminate\Support\Str::plural('video', $hiddenCount).' currently hidden. Show all verified videos and publish this collection? You can hide individual videos afterwards.';
    }
    if ($unfinishedCount > 0) {
        $warning[] = $unfinishedCount.' '.\Illuminate\Support\Str::plural('video', $unfinishedCount).' not ready and will remain hidden. Upload and verification are still required before those videos can be shown.';
    }
@endphp
@if($collection->archived_at)
    <span class="muted">Deletion cleanup pending. Use delete to review and retry.</span>
@else
    <button type="button" class="button secondary" data-collection-status="{{ $publishing ? 'published' : 'draft' }}" @if($publishing && $warning) data-publish-warning="{{ implode(' ', $warning) }}" @endif @if($publishing && $hiddenCount > 0) data-show-all-verified="1" @endif>{{ $publishing ? 'Publish' : 'Unpublish' }}</button>
@endif
