@props(['active' => 'help'])
@once
@push('styles')
<style>
.help-terms-toggle{display:flex;gap:4px;flex-wrap:wrap;background:#e8f0f4;border:1px solid var(--line);padding:5px;border-radius:10px;width:fit-content;max-width:100%;margin-bottom:26px}.help-terms-toggle a{padding:11px 20px;border-radius:7px;text-decoration:none;font-weight:800;color:var(--ink)}.help-terms-toggle a[aria-current=page]{background:#10212c;color:#fff}.help-terms-toggle a:focus-visible{outline:3px solid #00a3d5;outline-offset:2px}
</style>
@endpush
@endonce
<nav class="help-terms-toggle" aria-label="Help and terms">
    <a href="{{ auth()->user()?->canAccessCustomer() ? route('customer.media.help') : route('website.help') }}#website-content" @if($active === 'help')aria-current="page"@endif>Help</a>
    <a href="{{ route('website.detail', ['websiteSlug' => 'terms']) }}#website-content" @if($active === 'terms')aria-current="page"@endif>Terms &amp; Conditions</a>
</nav>
