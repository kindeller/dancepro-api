@php
    $paths = [
        'calendar' => 'M5 4h14v17H5z M8 2v4 M16 2v4 M5 9h14',
        'camera' => 'M3 7h4l2-3h6l2 3h4v13H3z M16 13a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
        'video' => 'M3 6h12v12H3z M15 10l6-3v10l-6-3',
        'programme' => 'M5 3h14v18H5z M8 8h8 M8 12h8 M8 16h5',
        'invoice' => 'M5 3h14v18l-3-2-4 2-4-2-3 2z M8 8h8 M8 12h8 M8 16h5',
        'seat' => 'M6 13V4h12v9 M4 11v6h16v-6 M6 17v4 M18 17v4 M6 13h12',
        'check' => 'm5 12 4 4L19 6',
    ];
@endphp
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$icon] ?? $paths['calendar'] }}"/></svg>
