<div class="contact-tabs" role="navigation" aria-label="Contact types">
    <a class="{{ request()->routeIs('admin.studios.*') ? 'active' : '' }}" href="{{ route('admin.studios.index') }}">Studios</a>
    <a class="{{ request()->routeIs('admin.competition-contacts.*') ? 'active' : '' }}" href="{{ route('admin.competition-contacts.index') }}">Competitions</a>
</div>
