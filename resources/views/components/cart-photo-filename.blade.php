@props(['filename' => null])
@if(filled($filename))
    <span class="cart-photo-filename" style="display:block;max-width:100%;overflow-wrap:anywhere;font-size:13px;color:var(--muted)">Filename: {{ $filename }}</span>
@endif
