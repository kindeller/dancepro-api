@extends('layouts.admin', [
    'title' => 'Competition Objects',
    'heading' => 'Competition Objects',
    'subheading' => 'Browse the competition storage structure exposed to the app.',
])

@section('content')
    <div class="toolbar">
        <form class="filters" method="GET" action="{{ route('admin.competition.objects.index') }}">
            <label>
                Prefix
                <input name="prefix" value="{{ $prefix }}" placeholder="Folder path">
            </label>
            <label>
                Request chunk size
                <input name="limit" type="number" min="1" max="1000" value="{{ $limit }}">
            </label>
            <button type="submit">View</button>
            <a class="button secondary" href="{{ route('admin.competition.objects.index') }}">Root</a>
        </form>

        <a
            id="create-links-from-selection"
            class="button secondary"
            href="{{ route('admin.download-links.create') }}"
        >
            Create links <span id="competition-selection-count"></span>
        </a>
    </div>

    @if ($objects['breadcrumbs'] !== [])
        <div class="notice">
            <a href="{{ route('admin.competition.objects.index') }}">Root</a>
            @foreach ($objects['breadcrumbs'] as $breadcrumb)
                <span class="muted">/</span>
                <a href="{{ route('admin.competition.objects.index', ['prefix' => $breadcrumb['prefix']]) }}">{{ $breadcrumb['name'] }}</a>
            @endforeach
        </div>
    @endif

    <div
        id="competition-objects-card"
        class="card competition-objects-card"
        data-load-state="{{ $objects['pagination']['has_more'] ? 'loading' : 'complete' }}"
        aria-busy="{{ $objects['pagination']['has_more'] ? 'true' : 'false' }}"
    >
        <div id="competition-objects-loading-panel" class="pagination" @if (! $objects['pagination']['has_more']) hidden @endif>
            <span id="competition-objects-loading" class="loading-indicator" role="status" @if (! $objects['pagination']['has_more']) hidden @endif>
                <span class="loading-spinner" aria-hidden="true"></span>
                Loading
            </span>

            <span id="competition-objects-loading-status" class="muted">
                Loading all objects... {{ number_format(count($objects['directories']) + count($objects['files'])) }} loaded.
            </span>

            <button
                id="competition-objects-continue"
                class="button secondary"
                type="button"
                hidden
            >
                Retry loading
            </button>
        </div>

        <table class="competition-objects-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Last modified</th>
                    <th class="selection-cell">Select</th>
                </tr>
            </thead>
            <tbody
                id="competition-objects-body"
                data-auto-load-url="{{ route('admin.competition.objects.chunk') }}"
                data-prefix="{{ $prefix }}"
                data-limit="{{ $limit }}"
                data-next-token="{{ $objects['pagination']['next_token'] }}"
                data-has-more="{{ $objects['pagination']['has_more'] ? '1' : '0' }}"
                data-loaded-count="{{ count($objects['directories']) + count($objects['files']) }}"
            >
                @forelse ($objects['directories'] as $directory)
                    <tr>
                        <td>
                            <a href="{{ route('admin.competition.objects.index', ['prefix' => $directory['prefix']]) }}">{{ $directory['name'] }}</a>
                        </td>
                        <td><span class="badge">folder</span></td>
                        <td class="muted">-</td>
                        <td class="muted">-</td>
                        <td class="selection-cell"></td>
                    </tr>
                @empty
                @endforelse

                @forelse ($objects['files'] as $file)
                    <tr class="selectable-row" data-storage-key="{{ $file['key'] }}">
                        <td>
                            @if ($file['console_url'])
                                <a href="{{ $file['console_url'] }}" target="_blank" rel="noopener noreferrer" title="Open {{ $file['key'] }} in the AWS Console">{{ $file['name'] }}</a>
                            @else
                                {{ $file['name'] }}
                            @endif
                        </td>
                        <td><span class="badge">{{ $file['extension'] ?: 'file' }}</span></td>
                        <td title="{{ $file['size'] === null ? '' : number_format($file['size']).' bytes' }}">{{ $file['size'] === null ? 'Unknown' : Illuminate\Support\Number::fileSize($file['size'], precision: 2) }}</td>
                        <td>
                            @if ($file['last_modified'])
                                <time datetime="{{ $file['last_modified'] }}">{{ $file['last_modified'] }}</time>
                            @else
                                Unknown
                            @endif
                        </td>
                        <td class="selection-cell">
                            <input
                                class="selection-checkbox"
                                type="checkbox"
                                aria-label="Select {{ $file['key'] }} for link creation"
                            >
                        </td>
                    </tr>
                @empty
                @endforelse

                @if ($objects['directories'] === [] && $objects['files'] === [])
                    <tr id="competition-objects-empty">
                        <td colspan="5" class="muted">No competition objects found for this prefix.</td>
                    </tr>
                @endif
            </tbody>
        </table>

        <div id="competition-objects-complete-panel" class="pagination" @if ($objects['pagination']['has_more']) hidden @endif>
            <span id="competition-objects-complete-status" class="muted">
                Showing {{ number_format(count($objects['directories']) + count($objects['files'])) }} objects. All objects loaded.
            </span>
        </div>

    </div>

    <script>
        (() => {
            const body = document.getElementById('competition-objects-body');
            const card = document.getElementById('competition-objects-card');
            const loadingPanel = document.getElementById('competition-objects-loading-panel');
            const loadingIndicator = document.getElementById('competition-objects-loading');
            const loadingStatus = document.getElementById('competition-objects-loading-status');
            const completePanel = document.getElementById('competition-objects-complete-panel');
            const completeStatus = document.getElementById('competition-objects-complete-status');
            const continueButton = document.getElementById('competition-objects-continue');
            const selectionCount = document.getElementById('competition-selection-count');

            if (!body || !card || !loadingPanel || !loadingIndicator || !loadingStatus || !completePanel || !completeStatus || !continueButton || !selectionCount) {
                return;
            }

            const selectionStorageKey = 'dancepro.competition.selected-objects';

            const readSelection = () => {
                try {
                    const storedKeys = JSON.parse(sessionStorage.getItem(selectionStorageKey) || '[]');

                    return new Set(Array.isArray(storedKeys) ? storedKeys.filter((key) => typeof key === 'string') : []);
                } catch (error) {
                    return new Set();
                }
            };

            const selectedKeys = readSelection();

            const state = {
                url: body.dataset.autoLoadUrl,
                prefix: body.dataset.prefix || '',
                limit: Number.parseInt(body.dataset.limit || '250', 10),
                nextToken: body.dataset.nextToken || '',
                hasMore: body.dataset.hasMore === '1',
                loadedCount: Number.parseInt(body.dataset.loadedCount || '0', 10),
                isLoading: false,
                loadFailed: false,
            };

            const formatCount = (value) => new Intl.NumberFormat().format(value);

            const formatFileSize = (bytes) => {
                if (bytes === null || bytes === undefined) {
                    return 'Unknown';
                }

                const units = ['bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
                const unitIndex = Math.min(
                    Math.floor(Math.log(Math.max(bytes, 1)) / Math.log(1024)),
                    units.length - 1,
                );
                const value = bytes / (1024 ** unitIndex);
                const precision = unitIndex === 0 ? 0 : 2;

                return `${new Intl.NumberFormat('en-AU', { maximumFractionDigits: precision }).format(value)} ${units[unitIndex]}`;
            };

            const formatDateTime = (value) => {
                if (!value) {
                    return 'Unknown';
                }

                const date = new Date(value);

                if (Number.isNaN(date.getTime())) {
                    return 'Unknown';
                }

                return new Intl.DateTimeFormat(undefined, {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                }).format(date);
            };

            const localizeDateTimes = (container = body) => {
                container.querySelectorAll('time[datetime]').forEach((element) => {
                    element.textContent = formatDateTime(element.dateTime);
                });
            };

            const updateStatus = () => {
                if (state.loadFailed) {
                    card.dataset.loadState = 'error';
                    card.setAttribute('aria-busy', 'false');
                    loadingPanel.hidden = false;
                    loadingIndicator.hidden = true;
                    completePanel.hidden = true;
                    continueButton.hidden = false;
                    loadingStatus.textContent = `${formatCount(state.loadedCount)} objects loaded. Loading stopped after an error.`;
                    return;
                }

                if (state.isLoading || state.hasMore) {
                    card.dataset.loadState = 'loading';
                    card.setAttribute('aria-busy', 'true');
                    loadingPanel.hidden = false;
                    loadingIndicator.hidden = false;
                    completePanel.hidden = true;
                    continueButton.hidden = true;
                    loadingStatus.textContent = `Loading all objects... ${formatCount(state.loadedCount)} loaded.`;
                    return;
                }

                card.dataset.loadState = 'complete';
                card.setAttribute('aria-busy', 'false');
                loadingPanel.hidden = true;
                loadingIndicator.hidden = true;
                completePanel.hidden = false;
                continueButton.hidden = true;
                completeStatus.textContent = `Showing ${formatCount(state.loadedCount)} objects. All objects loaded.`;
            };

            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[character]));

            const updateSelection = () => {
                try {
                    sessionStorage.setItem(selectionStorageKey, JSON.stringify([...selectedKeys]));
                } catch (error) {
                    // Selection still works on this page if browser storage is unavailable.
                }

                selectionCount.textContent = selectedKeys.size === 0 ? '' : `(${formatCount(selectedKeys.size)})`;

                body.querySelectorAll('.selectable-row').forEach((row) => {
                    const isSelected = selectedKeys.has(row.dataset.storageKey);
                    row.classList.toggle('is-selected', isSelected);

                    const checkbox = row.querySelector('.selection-checkbox');

                    if (checkbox) {
                        checkbox.checked = isSelected;
                    }
                });
            };

            const toggleSelection = (row, selected = null) => {
                const key = row.dataset.storageKey;

                if (!key) {
                    return;
                }

                const shouldSelect = selected ?? !selectedKeys.has(key);

                if (shouldSelect) {
                    selectedKeys.add(key);
                } else {
                    selectedKeys.delete(key);
                }

                updateSelection();
            };

            const appendDirectory = (directory) => {
                const url = new URL(window.location.href);
                url.searchParams.set('prefix', directory.prefix);
                url.searchParams.set('limit', state.limit);
                url.searchParams.delete('continuation_token');

                body.insertAdjacentHTML('beforeend', `
                    <tr>
                        <td><a href="${escapeHtml(url.toString())}">${escapeHtml(directory.name)}</a></td>
                        <td><span class="badge">folder</span></td>
                        <td class="muted">-</td>
                        <td class="muted">-</td>
                        <td class="selection-cell"></td>
                    </tr>
                `);
            };

            const appendFile = (file) => {
                const extension = file.extension || 'file';
                const size = formatFileSize(file.size);
                const sizeTitle = file.size === null || file.size === undefined
                    ? ''
                    : `${formatCount(file.size)} bytes`;
                const name = file.console_url
                    ? `<a href="${escapeHtml(file.console_url)}" target="_blank" rel="noopener noreferrer" title="Open ${escapeHtml(file.key)} in the AWS Console">${escapeHtml(file.name)}</a>`
                    : escapeHtml(file.name);
                const modified = file.last_modified
                    ? `<time datetime="${escapeHtml(file.last_modified)}">${escapeHtml(formatDateTime(file.last_modified))}</time>`
                    : 'Unknown';

                body.insertAdjacentHTML('beforeend', `
                    <tr class="selectable-row" data-storage-key="${escapeHtml(file.key)}">
                        <td>${name}</td>
                        <td><span class="badge">${escapeHtml(extension)}</span></td>
                        <td title="${escapeHtml(sizeTitle)}">${escapeHtml(size)}</td>
                        <td>${modified}</td>
                        <td class="selection-cell"><input class="selection-checkbox" type="checkbox" aria-label="Select ${escapeHtml(file.key)} for link creation"></td>
                    </tr>
                `);

                updateSelection();
            };

            const appendObjects = (objects) => {
                document.getElementById('competition-objects-empty')?.remove();

                objects.directories.forEach(appendDirectory);
                objects.files.forEach(appendFile);

                state.loadedCount += objects.directories.length + objects.files.length;
                state.nextToken = objects.pagination.next_token || '';
                state.hasMore = Boolean(objects.pagination.has_more);
            };

            const loadNextChunk = async () => {
                if (state.isLoading || !state.hasMore) {
                    updateStatus();
                    return;
                }

                if (!state.nextToken) {
                    state.loadFailed = true;
                    updateStatus();
                    return;
                }

                state.isLoading = true;
                state.loadFailed = false;
                updateStatus();

                const url = new URL(state.url, window.location.origin);
                url.searchParams.set('prefix', state.prefix);
                url.searchParams.set('limit', state.limit);
                url.searchParams.set('continuation_token', state.nextToken);

                try {
                    const response = await fetch(url.toString(), {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Request failed.');
                    }

                    const payload = await response.json();
                    appendObjects(payload.data);
                } catch (error) {
                    state.loadFailed = true;
                } finally {
                    state.isLoading = false;
                }

                updateStatus();

                if (state.loadFailed) {
                    return;
                }

                if (state.hasMore) {
                    window.setTimeout(() => loadNextChunk(), 0);
                }
            };

            continueButton.addEventListener('click', () => {
                loadNextChunk();
            });

            body.addEventListener('click', (event) => {
                const row = event.target.closest('.selectable-row');

                if (!row || event.target.closest('a')) {
                    return;
                }

                if (event.target.matches('.selection-checkbox')) {
                    toggleSelection(row, event.target.checked);
                    return;
                }

                toggleSelection(row);
            });

            updateSelection();
            localizeDateTimes();
            updateStatus();
            window.setTimeout(() => loadNextChunk(), 0);
        })();
    </script>
@endsection
