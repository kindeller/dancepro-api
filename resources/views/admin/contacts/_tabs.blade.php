<div class="contact-tabs" role="navigation" aria-label="Contact types">
    <a class="{{ request()->routeIs('admin.studios.*') ? 'active' : '' }}" href="{{ route('admin.studios.index') }}">Studios</a>
    <a class="{{ request()->routeIs('admin.competition-contacts.*') ? 'active' : '' }}" href="{{ route('admin.competition-contacts.index') }}">Competitions</a>
    <a class="{{ request()->routeIs('admin.studio-requests.*') ? 'active' : '' }}" href="{{ route('admin.studio-requests.index') }}">@include('layouts.partials.notification-badge', ['count' => $adminNavigationIndicators['studio_requests'] ?? 0, 'key' => 'studio_requests', 'label' => 'Studio requests awaiting review'])Studio requests</a>
</div>
