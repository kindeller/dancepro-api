<nav class="contact-tabs" aria-label="Event management sections">
    <a class="{{ request()->routeIs('admin.concert-bookings.*') ? 'active' : '' }}" href="{{ route('admin.concert-bookings.index') }}">Event Bookings</a>
    <a class="{{ request()->routeIs('admin.event-management.pending') ? 'active' : '' }}" href="{{ route('admin.event-management.pending') }}">Pending Events</a>
    <a class="{{ request()->routeIs('admin.scheduling-events.*') ? 'active' : '' }}" href="{{ route('admin.scheduling-events.index') }}">Event Availability</a>
    <a class="{{ request()->routeIs('admin.event-types.*') ? 'active' : '' }}" href="{{ route('admin.event-types.index') }}">Event Types</a>
    <a class="{{ request()->routeIs('admin.event-management.checklists') ? 'active' : '' }}" href="{{ route('admin.event-management.checklists') }}">Pre-Start Checks</a>
</nav>
