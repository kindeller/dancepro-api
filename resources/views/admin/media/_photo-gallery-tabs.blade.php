<nav class="toolbar" aria-label="Photo gallery views" style="justify-content:flex-start;gap:8px;margin:18px 0">
    <a class="button {{ request()->routeIs('admin.top-pics.*') ? 'secondary' : '' }}" href="{{ route($event->event_type->value === 'competition' ? 'admin.competition-gallery.show' : 'admin.concert-photo-gallery.show', $event) }}" @if(!request()->routeIs('admin.top-pics.*')) aria-current="page" @endif>All photos</a>
    <a class="button {{ request()->routeIs('admin.top-pics.show') ? '' : 'secondary' }}" href="{{ route('admin.top-pics.show', $event) }}" @if(request()->routeIs('admin.top-pics.show')) aria-current="page" @endif>Top Pics</a>
</nav>
