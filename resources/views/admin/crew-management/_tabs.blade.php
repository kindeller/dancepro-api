<div class="contact-tabs" role="navigation" aria-label="Crew management sections">
    <a class="{{ request()->routeIs('admin.crew.*') ? 'active' : '' }}" href="{{ route('admin.crew.index') }}">Crew</a>
    <a class="{{ request()->routeIs('admin.crew-roles.*') ? 'active' : '' }}" href="{{ route('admin.crew-roles.index') }}">Crew roles</a>
    <a class="{{ request()->routeIs('admin.crew-contracts.*') ? 'active' : '' }}" href="{{ route('admin.crew-contracts.index') }}">Crew contracts</a>
    <a class="{{ request()->routeIs('admin.crew-management.recognitions-rewards') ? 'active' : '' }}" href="{{ route('admin.crew-management.recognitions-rewards') }}">Recognitions &amp; rewards</a>
    <a class="{{ request()->routeIs('admin.crew-management.training', 'admin.training-courses.*') ? 'active' : '' }}" href="{{ route('admin.crew-management.training') }}">Crew training</a>
    <a class="{{ request()->routeIs('admin.crew-management.resources') ? 'active' : '' }}" href="{{ route('admin.crew-management.resources') }}">Crew resources</a>
</div>
