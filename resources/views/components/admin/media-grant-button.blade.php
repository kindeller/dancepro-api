{{-- Keep inside .media-grant-target around the thumbnail only, beside its link or preview button. --}}
@props([
    'sourceKind',
    'sourceUuid',
    'eventUuid' => null,
    'label' => 'Grant this media at no cost.',
    'ariaLabel' => 'Add media to an account',
    'includeVideo' => false,
])

@once
    @push('styles')
        <style>
            .media-grant-target { position:relative; }
            .grant-media-trigger { position:absolute;right:10px;bottom:10px;z-index:3;display:inline-grid;box-sizing:border-box;width:39px;min-width:39px;height:39px;min-height:39px;aspect-ratio:1;padding:0;place-items:center;border:1px solid rgba(255,255,255,.62);border-radius:999px!important;background:rgba(16,42,59,.78);color:#fff;box-shadow:0 4px 14px rgba(7,16,22,.28);backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px);cursor:pointer;transition:background .16s ease,border-color .16s ease,transform .16s ease,box-shadow .16s ease; }
            .grant-media-trigger svg { width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2.25;stroke-linecap:round; }
            .grant-media-trigger:hover,.grant-media-trigger:focus-visible { border-color:#fff;background:rgba(8,126,174,.94);color:#fff;transform:translateY(-1px) scale(1.04);box-shadow:0 6px 18px rgba(7,16,22,.34); }
            .grant-media-trigger:focus-visible { outline:3px solid rgba(121,212,246,.55);outline-offset:2px; }
        </style>
    @endpush
@endonce

<button
    type="button"
    {{ $attributes->class(['grant-media-trigger']) }}
    title="Add to account"
    aria-label="{{ $ariaLabel }}"
    data-grant-media
    data-source-kind="{{ $sourceKind }}"
    data-event-uuid="{{ $eventUuid }}"
    data-source-uuid="{{ $sourceUuid }}"
    data-include-video="{{ $includeVideo ? 'true' : 'false' }}"
    data-label="{{ $label }}"
>
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
</button>
