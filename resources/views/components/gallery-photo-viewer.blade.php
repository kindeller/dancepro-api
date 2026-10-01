<dialog class="gallery-photo-dialog" data-gallery-photo-dialog aria-label="Photo preview">
    <header><strong data-photo-title>Photo preview</strong><button type="button" data-close>Close ✕</button></header>
    <img alt="">
    <p data-photo-error role="alert" hidden style="padding:16px">This photo preview is currently unavailable.</p>
    <footer><button type="button" data-previous>← Previous photo</button><span data-photo-position aria-live="polite"></span><button type="button" data-next>Next photo →</button></footer>
</dialog>
@push('styles')
<style>
    .gallery-photo-dialog { width:min(1100px,calc(100% - 24px)); max-height:calc(100vh - 24px); padding:0; border:0; border-radius:12px; background:#101820; color:#fff; }
    .gallery-photo-dialog::backdrop { background:#000c; }
    .gallery-photo-dialog header,.gallery-photo-dialog footer { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:12px 16px; }
    .gallery-photo-dialog header strong { overflow-wrap:anywhere; min-width:0; }
    .gallery-photo-dialog img { display:block; margin:auto; max-width:100%; max-height:calc(100vh - 180px); object-fit:contain; }
    .gallery-photo-dialog button { border:1px solid #52636c; border-radius:7px; background:#24323a; color:#fff; padding:8px 12px; cursor:pointer; }
    .gallery-photo-dialog button:disabled { opacity:.4; cursor:default; }
</style>
@endpush
@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
    const viewer = document.querySelector('[data-gallery-photo-dialog]');
    const links = [...document.querySelectorAll('[data-gallery-preview]')];
    if (!viewer || !links.length) return;
    const image = viewer.querySelector('img');
    const previous = viewer.querySelector('[data-previous]');
    const next = viewer.querySelector('[data-next]');
    let current = 0;
    const show = index => {
        current = index;
        viewer.querySelector('[data-photo-error]').hidden = true;
        image.src = links[index].href;
        image.alt = links[index].querySelector('img')?.alt || 'Photo preview';
        viewer.querySelector('[data-photo-title]').textContent = image.alt;
        viewer.querySelector('[data-photo-position]').textContent = `${index + 1} / ${links.length}`;
        previous.disabled = index === 0;
        next.disabled = index === links.length - 1;
    };
    links.forEach((link, index) => link.addEventListener('click', event => {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        show(index);
        viewer.showModal();
    }));
    image.addEventListener('error', () => { viewer.querySelector('[data-photo-error]').hidden = false; });
    previous.addEventListener('click', () => { if (current > 0) show(current - 1); });
    next.addEventListener('click', () => { if (current < links.length - 1) show(current + 1); });
    viewer.querySelector('[data-close]').addEventListener('click', () => viewer.close());
    viewer.addEventListener('click', event => { if (event.target === viewer) viewer.close(); });
    viewer.addEventListener('close', () => { image.removeAttribute('src'); links[current]?.focus({preventScroll:true}); });
    viewer.addEventListener('keydown', event => {
        if (event.ctrlKey || event.metaKey || event.altKey || event.shiftKey) return;
        if (event.key === 'ArrowLeft') { event.preventDefault(); if (current > 0) show(current - 1); }
        if (event.key === 'ArrowRight') { event.preventDefault(); if (current < links.length - 1) show(current + 1); }
    });
})();
</script>
@endpush
