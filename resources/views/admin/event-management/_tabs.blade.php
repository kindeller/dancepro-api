<nav class="contact-tabs" aria-label="Event management sections">
    <a class="{{ request()->routeIs('admin.concert-bookings.*') ? 'active' : '' }}" href="{{ route('admin.concert-bookings.index') }}">Event bookings</a>
    <a class="{{ request()->routeIs('admin.event-management.pending') ? 'active' : '' }}" href="{{ route('admin.event-management.pending') }}">Pending events</a>
    <a class="{{ request()->routeIs('admin.scheduling-events.*') ? 'active' : '' }}" href="{{ route('admin.scheduling-events.index') }}">Event availability</a>
    <a class="{{ request()->routeIs('admin.event-types.*') ? 'active' : '' }}" href="{{ route('admin.event-types.index') }}">Event types</a>
    <a class="{{ request()->routeIs('admin.event-management.checklists') ? 'active' : '' }}" href="{{ route('admin.event-management.checklists') }}">Pre-start checks</a>
</nav>
