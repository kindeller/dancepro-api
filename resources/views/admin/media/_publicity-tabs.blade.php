<nav class="contact-tabs" aria-label="Publicity pages" style="margin-bottom:24px">
    <a href="{{ route('admin.top-pics.index', ['type' => ($type ?? 'competition') === 'studio' ? 'concert' : ($type ?? 'competition')]) }}" class="{{ request()->routeIs('admin.top-pics.*') ? 'active' : '' }}" @if(request()->routeIs('admin.top-pics.*')) aria-current="page" @endif>Top Pics</a>
    <a href="{{ route('admin.publicity.tv-slideshows') }}" class="{{ (request()->routeIs('admin.publicity.*') && ! request()->routeIs('admin.publicity.sharing.*')) ? 'active' : '' }}" @if((request()->routeIs('admin.publicity.*') && ! request()->routeIs('admin.publicity.sharing.*'))) aria-current="page" @endif>TV Slideshows</a>
    <a href="{{ route('admin.publicity.sharing.index') }}" class="{{ request()->routeIs('admin.publicity.sharing.*') ? 'active' : '' }}" @if(request()->routeIs('admin.publicity.sharing.*')) aria-current="page" @endif>QR codes &amp; links</a>
</nav>
