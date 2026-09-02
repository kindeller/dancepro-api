@extends('layouts.admin', [
    'title' => 'Contacts',
    'heading' => 'Contacts',
    'subheading' => 'Manage reusable studio and competition contact details.',
])

@section('content')
    @include('admin.contacts._tabs')
    <div class="toolbar">
        <form class="filters" method="GET" action="{{ route('admin.competition-contacts.index') }}">
            <label>Search<input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Competition, organiser or email"></label>
            <label>Status<select name="status"><option value="">Any status</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option></select></label>
            <button type="submit">Filter</button>
            <a class="button secondary" href="{{ route('admin.competition-contacts.index') }}">Reset</a>
        </form>
        <a class="button" href="{{ route('admin.competition-contacts.create') }}">Add competition contact</a>
    </div>

    @forelse($contacts->getCollection()->groupBy(fn ($contact) => $contact->is_active ? 'active' : 'inactive') as $groupStatus => $groupContacts)
        <section class="competition-group">
            <h2 class="competition-group-heading">{{ ucfirst($groupStatus) }} ({{ $statusCounts[$groupStatus === 'active' ? 1 : 0] ?? 0 }})</h2>
            <div class="card competition-table-wrap">
                <table>
                    <thead><tr><th>Competition</th><th>Status</th><th>Contact</th><th>Phone</th><th>Events</th><th>Updated</th></tr></thead>
                    <tbody>
                    @foreach($groupContacts as $contact)
                        @php
                            $primary = $contact->staff->first();
                            $allEmails = $contact->contactEmailAddresses();
                            if (!$allEmails && $contact->organiser_email) $allEmails = [$contact->organiser_email];
                        @endphp
                        <tr class="competition-row" data-href="{{ route('admin.competition-contacts.edit', $contact) }}" tabindex="0" role="link" aria-label="View {{ $contact->name }}">
                            <td><div class="competition-summary">@if($contact->logo_path)<img class="competition-thumbnail" src="{{ $contact->logoUrl() }}" alt="{{ $contact->name }} logo">@else<div class="competition-thumbnail competition-thumbnail-placeholder" aria-hidden="true">{{ str($contact->code ?: $contact->name)->substr(0, 2)->upper() }}</div>@endif<div class="competition-name"><strong>{{ $contact->name }}</strong>@if($contact->code)<div class="muted competition-code">{{ $contact->code }}</div>@endif</div></div></td>
                            <td>
                                <form class="competition-status-form" method="POST" action="{{ route('admin.competition-contacts.status.update', $contact) }}" data-row-control>
                                    @csrf @method('PATCH')
                                    <select class="status-select status-{{ $contact->is_active ? 'active' : 'inactive' }}" name="is_active" aria-label="Change {{ $contact->name }} status" onchange="this.form.submit()">
                                        <option value="1" @selected($contact->is_active)>Active</option>
                                        <option value="0" @selected(!$contact->is_active)>Inactive</option>
                                    </select>
                                </form>
                            </td>
                            <td>
                                {{ $primary?->name ?: ($contact->organiser_name ?: '—') }}
                                @if($contact->staff->count() > 1)<span class="badge">+{{ $contact->staff->count() - 1 }}</span>@endif
                                @if($allEmails)<button type="button" class="copy-emails muted" data-row-control data-copy-emails="{{ implode(', ', $allEmails) }}" title="Copy all email addresses">{{ implode(', ', $allEmails) }}</button>@endif
                            </td>
                            <td>{{ $primary?->phone ?: ($contact->organiser_phone ?: '—') }}</td>
                            <td>{{ $contact->events_count }}</td>
                            <td>{{ $contact->updated_at?->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <div class="card card-pad muted">No competition contacts match this view.</div>
    @endforelse

    <div class="pagination"><x-admin-pagination :paginator="$contacts" /></div>
    <div id="copy-status" class="copy-status" role="status" aria-live="polite"></div>
@endsection

@push('styles')
<style>
    .competition-group { margin-top:24px; }
    .competition-group-heading { margin-bottom:10px; }
    .competition-table-wrap { overflow-x:auto; }
    .competition-table-wrap th, .competition-table-wrap td { text-align:left; }
    .competition-row { cursor:pointer; }
    .competition-row:focus { outline:3px solid rgba(10,160,219,.35); outline-offset:-3px; }
    .competition-summary { display:flex; align-items:center; gap:12px; min-width:260px; }
    .competition-name { display:flex; min-width:0; flex-direction:column; }
    .competition-code { margin-top:2px; font-size:12px; font-weight:700; letter-spacing:.06em; }
    .competition-thumbnail { width:72px; height:48px; flex:0 0 72px; border:1px solid #dbe3ea; border-radius:8px; background:#fff; object-fit:contain; }
    .competition-thumbnail-placeholder { display:grid; place-items:center; background:#eef3f6; color:#71808b; font-size:12px; font-weight:800; letter-spacing:.08em; }
    .competition-status-form { margin:0; }
    .status-select { width:auto; min-width:0; min-height:24px; border:0; border-radius:4px; padding:3px 8px; appearance:none; -webkit-appearance:none; background:var(--soft); color:var(--brand-strong); font:inherit; font-size:12px; font-weight:800; line-height:18px; text-align:left; text-transform:capitalize; cursor:pointer; }
    .status-inactive { background:#fef2f2; color:var(--danger); }
    .copy-emails { display:block; max-width:420px; min-height:0; margin-top:3px; padding:0; overflow:hidden; border:0; background:transparent; color:var(--muted); font:inherit; font-size:13px; font-weight:400; text-align:left; text-overflow:ellipsis; white-space:nowrap; cursor:copy; }
    .copy-emails:hover { background:transparent; color:var(--brand-strong); text-decoration:underline; }
    .copy-status { position:fixed; right:24px; bottom:24px; z-index:20; padding:10px 14px; border-radius:4px; background:var(--brand-dark); color:#fff; box-shadow:var(--shadow); opacity:0; pointer-events:none; transition:opacity .2s; }
    .copy-status.visible { opacity:1; }
</style>
@endpush

@push('scripts')
<script>
(() => {
    const isControl = target => target.closest('[data-row-control], a, button, input, select, textarea, label, form');
    document.querySelectorAll('.competition-row').forEach(row => {
        row.addEventListener('click', event => { if (!isControl(event.target)) window.location.assign(row.dataset.href); });
        row.addEventListener('keydown', event => {
            if ((event.key === 'Enter' || event.key === ' ') && !isControl(event.target)) {
                event.preventDefault();
                window.location.assign(row.dataset.href);
            }
        });
    });

    const status = document.getElementById('copy-status');
    document.querySelectorAll('[data-copy-emails]').forEach(button => button.addEventListener('click', async event => {
        event.stopPropagation();
        try {
            await navigator.clipboard.writeText(button.dataset.copyEmails);
            status.textContent = 'Email addresses copied';
        } catch (_) {
            status.textContent = 'Could not copy email addresses';
        }
        status.classList.add('visible');
        window.setTimeout(() => status.classList.remove('visible'), 1800);
    }));
})();
</script>
@endpush
