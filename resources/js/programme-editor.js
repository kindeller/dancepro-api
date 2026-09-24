const configElement = document.getElementById('programme-config');
if (configElement) initialiseProgramme(JSON.parse(configElement.textContent));

function initialiseProgramme(config) {
    // Flatten legacy concert acts in their displayed order, keeping item identities.
    const concertRows = (parent = null) => config.nodes
        .filter(node => (node.parent_uuid ?? null) === parent)
        .flatMap(node => node.kind === 'group' ? concertRows(node.uuid) : [{ ...node, parent_uuid: null }]);
    const concertNeedsFlattening = !config.competition && config.nodes.some(node => node.kind === 'group' || node.parent_uuid);
    const cleanFolderTitle = value => (value ?? '').replaceAll('/', ' ').replace(/[^\p{L}\p{M}\p{N}\s]/gu, '').replace(/\s+/gu, ' ').trim();
    let nodes = config.competition ? config.nodes : concertRows();
    let version = config.version;
    let dirty = false;
    let busy = false;
    const rows = document.getElementById('programme-rows');
    const status = document.getElementById('programme-status');
    const fields = document.getElementById('programme-fields');
    const sourceForm = document.getElementById('programme-source-form');
    const kinds = { day: 'Day', section: 'Section', group: 'Act / group', item: config.competition ? 'Competitor / routine' : 'Programme item' };
    const element = (tag, text, className) => {
        const el = document.createElement(tag);
        if (text !== undefined) el.textContent = text;
        if (className) el.className = className;
        return el;
    };
    let autosaveTimer;
    const changed = () => { dirty = true; status.textContent = 'Changes pending…'; clearTimeout(autosaveTimer); autosaveTimer = setTimeout(() => run(save), 1200); };
    const button = (label, action, parent, disabled = false) => {
        const el = element('button', label, 'secondary');
        el.type = 'button'; el.disabled = disabled; el.addEventListener('click', action); parent.append(el);
        return el;
    };
    const uuid = () => {
        // getRandomValues also works on the local-network HTTP development host.
        const bytes = crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
        const hex = Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    };
    function add(kind, parent = null, values = {}) {
        const node = { uuid: uuid(), kind, parent_uuid: parent, title: '', number: null, date: null, session: null, class_name: null, item_kind: kind === 'item' ? 'dance' : null, source_page: null, ...values };
        nodes.push(node); changed(); render();
        rows.querySelector(`[data-uuid="${node.uuid}"] input[data-field="title"]`)?.focus();
    }
    function input(node, key, label, parent, type = 'text', compact = false) {
        const wrap = element('label', compact ? undefined : label);
        if (compact) wrap.className = `programme-input-${key}`;
        const field = element('input'); field.type = type; field.value = node[key] ?? ''; field.dataset.field = key;
        field.setAttribute('aria-label', label);
        if (compact) field.placeholder = key === 'number' ? '#' : key === 'class_name' ? 'Class name' : 'Item title';
        if (type === 'number') { field.min = '1'; field.max = '10000'; }
        if (type === 'text') field.maxLength = key === 'number' ? 12 : key === 'session' ? 150 : 500;
        field.addEventListener('input', () => {
            node[key] = field.value || null; changed();
            if (!config.competition && key === 'title') {
                const preview = rows.querySelector(`[data-uuid="${node.uuid}"] [data-folder-title]`);
                if (preview) preview.textContent = node.item_kind === 'break' ? 'No folder (break)' : cleanFolderTitle(node.title) || 'A title with letters or numbers is needed';
            }
        });
        wrap.append(field); parent.append(wrap);
    }
    function select(node, key, label, choices, parent, redraw = false) {
        const wrap = element('label', label); const field = element('select');
        for (const [value, text] of choices) { const option = element('option', text); option.value = value; field.append(option); }
        field.value = node[key] ?? '';
        field.addEventListener('change', () => { node[key] = field.value || null; changed(); if (redraw) render(); });
        wrap.append(field); parent.append(wrap);
    }
    function move(node, direction) {
        const siblings = nodes.filter(other => (other.parent_uuid ?? null) === (node.parent_uuid ?? null));
        const other = siblings[siblings.indexOf(node) + direction];
        if (!other) return;
        const a = nodes.indexOf(node), b = nodes.indexOf(other);
        [nodes[a], nodes[b]] = [nodes[b], nodes[a]]; changed(); render();
    }
    function remove(node) {
        if (!window.confirm(node.kind === 'item' ? 'Remove this item from the draft?' : 'Remove this row and its children from the draft? Confirmed versions will be kept.')) return;
        const removeIds = new Set([node.uuid]);
        let previous;
        do { previous = removeIds.size; nodes.forEach(other => { if (removeIds.has(other.parent_uuid)) removeIds.add(other.uuid); }); } while (previous !== removeIds.size);
        nodes = nodes.filter(other => !removeIds.has(other.uuid)); changed(); render();
    }
    function pasteControl(parentUuid, parent) {
        const detail = element('details'); detail.style.marginTop = '10px'; detail.append(element('summary', 'Paste multiple items'));
        detail.append(element('p', config.competition ? 'One entry per line: number | performer or group. Keep duos and trios on one line.' : 'One item per line: title, or number | title | class. Leave the number blank when none is printed.', 'muted'));
        const input = element('textarea'); input.rows = 5; input.setAttribute('aria-label', 'Items to paste'); detail.append(input);
        button('Add pasted items', () => {
            const lines = input.value.split(/\r?\n/).map(line => line.trim()).filter(Boolean);
            for (const line of lines) {
                const parts = line.split('|').map(part => part.trim());
                const numbered = parts.length > 1;
                nodes.push({ uuid: uuid(), parent_uuid: parentUuid, kind: 'item', number: numbered ? parts[0] || null : null, title: numbered ? parts[1] : parts[0], class_name: parts[2] || null, item_kind: 'dance' });
            }
            if (lines.length) { changed(); render(); }
        }, detail);
        parent.append(detail);
    }
    function renderChildren(parentUuid, parent) {
        const siblings = nodes.filter(node => (node.parent_uuid ?? null) === parentUuid);
        siblings.forEach((node, index) => {
            const card = element('article', undefined, 'programme-node'); card.dataset.kind = node.kind; card.dataset.uuid = node.uuid;
            const compact = node.kind === 'item';
            if (compact) card.classList.add('programme-item-row');
            const main = element('div', undefined, compact ? 'programme-item-line' : 'programme-fields');
            const toolbar = element('div', undefined, 'toolbar');
            if (!compact) { toolbar.append(element('strong', kinds[node.kind])); card.append(toolbar); }
            if (node.kind === 'day') input(node, 'date', 'Date', main, 'date');
            else if (config.competition) input(node, 'number', 'Number', main, 'text', compact);
            else {
                const folderNumber = element('span', node.item_kind === 'break' ? '—' : String(concertSequence.get(node.uuid)).padStart(2, '0'), 'programme-folder-number');
                folderNumber.setAttribute('aria-label', node.item_kind === 'break' ? 'Break — no folder' : `Folder number ${folderNumber.textContent}`);
                folderNumber.title = node.item_kind === 'break' ? 'Breaks do not create folders' : 'Folder number — follows running order';
                main.append(folderNumber);
            }
            input(node, 'title', config.competition && compact ? 'Performer / group label' : 'Title / printed label', main, 'text', compact);
            if (compact && !config.competition) input(node, 'class_name', 'Class name', main, 'text', true);
            const actions = element('div', undefined, 'programme-row-actions');
            button('↑', () => move(node, -1), actions, index === 0).setAttribute('aria-label', 'Move up');
            button('↓', () => move(node, 1), actions, index === siblings.length - 1).setAttribute('aria-label', 'Move down');
            const removeButton = button(compact ? '×' : 'Remove', () => remove(node), actions);
            removeButton.setAttribute('aria-label', 'Remove item'); removeButton.title = 'Remove item';
            (compact ? main : toolbar).append(actions);
            card.append(main);
            const details = element('details', undefined, 'programme-item-details');
            const summary = element('summary', compact ? 'More' : 'Details and source page');
            summary.setAttribute('aria-label', 'Item details and source page'); details.append(summary);
            if (compact) main.append(details); else card.append(details);
            const extras = element('div', undefined, 'grid two-col');
            if (node.kind === 'section') input(node, 'session', 'Session / printed time', extras);
            if (node.kind === 'item') {
                if (!config.competition) {
                    input(node, 'number', 'Printed programme number (optional)', extras);
                    const preview = element('div'); preview.append(element('span', 'Folder title', 'muted'));
                    const folderTitle = element('div', node.item_kind === 'break' ? 'No folder (break)' : cleanFolderTitle(node.title) || 'A title with letters or numbers is needed');
                    folderTitle.dataset.folderTitle = ''; preview.append(folderTitle); extras.append(preview);
                }
                if (config.competition) input(node, 'class_name', 'Class details (optional)', extras);
                select(node, 'item_kind', 'Item type', ['dance', 'slideshow', 'presentation', 'finale', 'break', 'other'].map(kind => [kind, kind[0].toUpperCase() + kind.slice(1)]), extras, true);
            }
            input(node, 'source_page', 'PDF page (optional)', extras, 'number');
            const parentKind = node.kind === 'section' ? 'day' : node.kind === 'item' && config.competition ? 'section' : null;
            if (parentKind) {
                const choices = nodes.filter(other => other.kind === parentKind).map(other => [other.uuid, [other.number, other.title || kinds[other.kind]].filter(Boolean).join(' · ')]);
                if (!config.competition) choices.unshift(['', 'Outside an act / group']);
                select(node, 'parent_uuid', 'Belongs to', choices, extras, true);
            }
            details.append(extras);
            if (node.source_page && document.getElementById('programme-pdf')) button('View source page', () => {
                const frame = document.getElementById('programme-pdf'); frame.src = `${frame.src.split('#')[0]}#page=${Number(node.source_page)}`;
            }, details);
            if (!compact) { const children = element('div', undefined, 'programme-children'); renderChildren(node.uuid, children); card.append(children); }
            const controls = element('div', undefined, 'programme-actions');
            if (node.kind === 'day') button('Add section', () => add('section', node.uuid), controls);
            if (node.kind === 'section' || node.kind === 'group') {
                button(config.competition ? 'Add competitor / routine' : 'Add item', () => add('item', node.uuid), controls);
                pasteControl(node.uuid, card);
            }
            if (controls.childElementCount) card.append(controls); parent.append(card);
        });
    }
    let concertSequence = new Map();
    function render() {
        let sequence = 0;
        concertSequence = new Map(nodes.filter(node => node.kind === 'item' && node.item_kind !== 'break').map(node => [node.uuid, ++sequence]));
        rows.replaceChildren(); renderChildren(null, rows);
    }
    const addControls = document.getElementById('programme-add');
    if (config.competition) button('Add day', () => add('day'), addControls);
    else {
        button('Add item', () => add('item'), addControls);
        pasteControl(null, addControls);
    }
    const clearButton = button('Clear all items', () => {
        if (!nodes.length) return;
        if (!window.confirm('Clear the entire draft running order? Confirmed versions will remain available.')) return;
        nodes = [];
        changed();
        render();
        run(save);
    }, addControls, nodes.length === 0);
    clearButton.classList.add('danger');
    async function request(url, method, data) {
        const response = await fetch(url, { method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf }, body: JSON.stringify(data) });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            if (response.status === 409) throw new Error('Another window changed this programme. Your edits are still shown here. Reload the page before trying again.');
            const errors = Object.entries(result.errors ?? {}).flatMap(([key, messages]) => {
                const row = /^nodes\.(\d+)/.exec(key);
                const label = row ? nodes[Number(row[1])]?.title || `Row ${Number(row[1]) + 1}` : '';
                return (Array.isArray(messages) ? messages : [messages]).map(message => `${label ? `${label}: ` : ''}${message}`);
            });
            throw new Error(errors.join('\n') || result.message || 'The request failed. Your edits have been kept; please try again.');
        }
        return result.data;
    }
    async function save() {
        const data = await request(config.saveUrl, 'PUT', { version, nodes });
        version = data.version; dirty = false;
        document.getElementById('programme-version').textContent = `Draft ${version}`;
        if (sourceForm) sourceForm.elements.version.value = version;
        status.textContent = 'All changes saved. Approve when the running order is ready.';
    }
    async function run(action) {
        if (busy || config.cancelled || config.extracting) return;
        clearTimeout(autosaveTimer);
        const focused = fields.contains(document.activeElement) ? document.activeElement : null;
        const selection = focused && typeof focused.selectionStart === 'number' ? [focused.selectionStart, focused.selectionEnd] : null;
        busy = true; fields.disabled = true;
        if (sourceForm) sourceForm.querySelector('button').disabled = true;
        status.textContent = 'Saving…';
        try { await action(); } catch (error) { status.textContent = error.message; }
        finally {
            busy = false; fields.disabled = config.cancelled || config.extracting;
            if (sourceForm) sourceForm.querySelector('button').disabled = false;
            if (focused?.isConnected) { focused.focus({ preventScroll: true }); if (selection) focused.setSelectionRange(...selection); }
        }
    }
    document.getElementById('programme-save').addEventListener('click', () => run(save));
    document.getElementById('programme-confirm').addEventListener('click', () => run(async () => {
        if (dirty || concertNeedsFlattening) await save();
        await request(config.confirmUrl, 'POST', { version });
        status.textContent = 'Programme approved.'; window.location.reload();
    }));
    sourceForm?.addEventListener('submit', event => {
        if (busy || config.extracting) { event.preventDefault(); status.textContent = 'Please wait for the current operation to finish.'; return; }
        if (dirty) { event.preventDefault(); run(async () => { await save(); sourceForm.submit(); }); }
    });
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    fields.disabled = config.cancelled || config.extracting;
    const progress = document.getElementById('extraction-progress');
    if (config.extracting && progress) {
        const started = Date.now();
        const poll = async () => {
            try {
                const response = await fetch(progress.dataset.url, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Progress unavailable');
                const result = await response.json();
                if (!['queued', 'processing'].includes(result.data.status)) { window.location.reload(); return; }
                if (Date.now() - started > 240000) {
                    progress.querySelector('[role="status"]').textContent = 'Reading is taking longer than expected. You can leave this page and return later; your PDF is saved.';
                    return;
                }
            } catch (_) {
                progress.querySelector('[role="status"]').textContent = 'Unable to check progress. Refresh this page to try again; your PDF is saved.';
                return;
            }
            setTimeout(poll, 2000);
        };
        setTimeout(poll, 2000);
    }
    render();
}
