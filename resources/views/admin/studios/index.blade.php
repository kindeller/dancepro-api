@extends('layouts.admin', [
    'title' => 'Contacts',
    'heading' => 'Contacts',
    'subheading' => 'Manage reusable studio and competition contact details.',
])

@section('content')
    @include('admin.contacts._tabs')
    <div class="toolbar">
        <form class="filters" method="GET" action="{{ route('admin.studios.index') }}">
            <label>Search<input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Studio name, code or email"></label>
            <label>Status<select name="status"><option value="">Any status</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ ucfirst($status->value) }}</option>@endforeach</select></label>
            <button type="submit">Filter</button>
            <a class="button secondary" href="{{ route('admin.studios.index') }}">Reset</a>
        </form>
        <a class="button" href="{{ route('admin.studios.create') }}">Add studio</a>
    </div>

    @forelse($studios->getCollection()->groupBy(fn ($studio) => $studio->status->value) as $groupStatus => $groupStudios)
        <section class="studio-group">
            <h2 class="studio-group-heading">{{ ucfirst($groupStatus) }} ({{ $statusCounts[$groupStatus] ?? 0 }})</h2>
            <div class="card studio-table-wrap">
                <table>
                    <thead><tr><th>Studio</th><th>Status</th><th>Contact</th><th>Concerts</th><th>Updated</th></tr></thead>
                    <tbody>
                    @foreach($groupStudios as $studio)
                        @php
                            $primaryContact = $studio->contacts->first();
                            $allEmails = $studio->contactEmailAddresses();
                            if (!$allEmails && $studio->contact_email) $allEmails = [$studio->contact_email];
                        @endphp
                        <tr class="studio-row" data-href="{{ route('admin.studios.edit', $studio) }}" tabindex="0" role="link" aria-label="View {{ $studio->name }}">
                            <td>
                                <div class="studio-summary">
                                    @if($studio->logo_path)<img class="studio-thumbnail" src="{{ $studio->logoUrl() }}" alt="{{ $studio->name }} logo">@else<div class="studio-thumbnail studio-thumbnail-placeholder" aria-hidden="true">{{ str($studio->code ?: $studio->name)->substr(0, 2)->upper() }}</div>@endif
                                    <div><strong>{{ $studio->name }}</strong>@if($studio->code)<div class="muted studio-code">{{ $studio->code }}</div>@endif</div>
                                </div>
                            </td>
                            <td>
                                <form class="studio-status-form" method="POST" action="{{ route('admin.studios.status.update', $studio) }}" data-row-control>
                                    @csrf @method('PATCH')
                                    <select id="status-{{ $studio->uuid }}" class="status-select status-{{ $studio->status->value }}" name="status" aria-label="Change {{ $studio->name }} status" onchange="this.form.submit()">
                                        @foreach($statuses as $status)<option value="{{ $status->value }}" @selected($studio->status === $status)>{{ ucfirst($status->value) }}</option>@endforeach
                                    </select>
                                </form>
                            </td>
                            <td>
                                {{ $primaryContact?->name ?: ($studio->contact_name ?: '—') }}
                                @if($studio->contacts->count() > 1)<span class="badge">+{{ $studio->contacts->count() - 1 }}</span>@endif
                                @if($allEmails)
                                    <button type="button" class="copy-emails muted" data-row-control data-copy-emails="{{ implode(', ', $allEmails) }}" title="Copy all email addresses">{{ implode(', ', $allEmails) }}</button>
                                @endif
                            </td>
                            <td>{{ $studio->concerts_count }}</td>
                            <td>{{ $studio->updated_at?->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <div class="card card-pad muted">No studios match this view.</div>
    @endforelse

    <div class="pagination"><x-admin-pagination :paginator="$studios" /></div>
    <div id="copy-status" class="copy-status" role="status" aria-live="polite"></div>
@endsection

@push('styles')
<style>
    .studio-group { margin-top:24px; }
    .studio-group-heading { margin-bottom:10px; }
    .studio-table-wrap { overflow-x:auto; }
    .studio-table-wrap th, .studio-table-wrap td { text-align:left; }
    .studio-row { cursor:pointer; }
    .studio-row:focus { outline:3px solid rgba(10,160,219,.35); outline-offset:-3px; }
    .studio-summary { display:flex; align-items:center; gap:12px; min-width:260px; }
    .studio-thumbnail { width:72px; height:48px; flex:0 0 72px; border:1px solid #dbe3ea; border-radius:8px; background:#fff; object-fit:contain; }
    .studio-thumbnail-placeholder { display:grid; place-items:center; background:#eef3f6; color:#71808b; font-size:12px; font-weight:800; letter-spacing:.08em; }
    .studio-code { margin-top:2px; font-size:12px; font-weight:700; letter-spacing:.06em; }
    .studio-status-form { margin:0; }
    .status-select { width:auto; min-width:0; min-height:24px; border:0; border-radius:4px; padding:3px 8px; appearance:none; -webkit-appearance:none; background:var(--soft); color:var(--brand-strong); font:inherit; font-size:12px; font-weight:800; line-height:18px; text-align:left; text-transform:capitalize; cursor:pointer; }
    .status-inactive, .status-archived { background:#fef2f2; color:var(--danger); }
    .copy-emails { display:block; max-width:420px; min-height:0; margin-top:3px; padding:0; overflow:hidden; border:0; background:transparent; color:var(--muted); font:inherit; font-size:13px; font-weight:400; text-align:left; text-overflow:ellipsis; white-space:nowrap; cursor:copy; }
    .copy-emails:hover { background:transparent; color:var(--brand-strong); text-decoration:underline; }
    .copy-status { position:fixed; right:24px; bottom:24px; z-index:20; padding:10px 14px; border-radius:4px; background:var(--brand-dark); color:#fff; box-shadow:var(--shadow); opacity:0; pointer-events:none; transition:opacity .2s; }
    .copy-status.visible { opacity:1; }
</style>
@endpush

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
    const isControl = target => target.closest('[data-row-control], a, button, input, select, textarea, label, form');
    document.querySelectorAll('.studio-row').forEach(row => {
        row.addEventListener('click', event => { if (!isControl(event.target)) window.location.assign(row.dataset.href); });
        row.addEventListener('keydown', event => {
            if ((event.key === 'Enter' || event.key === ' ') && !isControl(event.target)) {
                event.preventDefault();
                window.location.assign(row.dataset.href);
            }
        });
    });

})();
</script>
@endpush
