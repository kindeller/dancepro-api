import TurndownService from 'turndown';

const markdown = new TurndownService({headingStyle: 'atx', bulletListMarker: '-', emDelimiter: '*'});
markdown.remove(['script', 'style', 'iframe', 'object']);

function enhanceEmail(editor) {
    const visual = editor.querySelector('[data-email-visual]');
    const source = editor.querySelector('[data-email-source]');
    const subject = editor.querySelector('[data-send-subject]');
    const error = editor.querySelector('[data-email-error]');
    let selection = null;
    let changed = false;

    const sync = () => {
        // Leave an untouched rendered template exactly as it was provided.
        if (changed) source.value = markdown.turndown(visual);
    };
    const edited = () => {
        changed = true;
        sync();
        error.hidden = true;
        visual.removeAttribute('aria-invalid');
    };
    document.addEventListener('selectionchange', () => {
        const selected = window.getSelection();
        if (selected.rangeCount && visual.contains(selected.getRangeAt(0).commonAncestorContainer)) {
            selection = selected.getRangeAt(0).cloneRange();
        }
    });
    const restoreSelection = () => {
        visual.focus();
        if (selection && visual.contains(selection.commonAncestorContainer)) {
            const selected = window.getSelection();
            selected.removeAllRanges();
            selected.addRange(selection);
        }
    };
    visual.addEventListener('input', edited);
    // Paste plain text so external styles and active HTML cannot enter the message.
    visual.addEventListener('paste', event => {
        event.preventDefault();
        document.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
        edited();
    });
    visual.addEventListener('drop', event => event.preventDefault());
    editor.querySelectorAll('[data-command]').forEach(button => {
        button.addEventListener('mousedown', event => event.preventDefault());
        button.addEventListener('click', () => {
            let value = button.dataset.value || null;
            if (button.dataset.command === 'createLink') {
                value = window.prompt('Link address (https://… or mailto:…)');
                if (!value) return;
                try {
                    const url = new URL(value.trim());
                    if (!['https:', 'http:', 'mailto:'].includes(url.protocol)) throw new Error('Invalid link');
                    value = url.href;
                } catch {
                    error.textContent = 'Enter a valid https://, http:// or mailto: link.';
                    error.hidden = false;
                    return;
                }
            }
            restoreSelection();
            document.execCommand(button.dataset.command, false, value);
            edited();
        });
    });
    const reset = editor.querySelector('[data-email-reset]');
    reset.addEventListener('click', () => {
        if (!window.confirm('Discard your one-off edits and restore the original message?')) return;
        subject.value = editor.querySelector('[data-email-original-subject]').content.textContent;
        source.value = editor.querySelector('[data-email-original-body]').content.textContent;
        visual.replaceChildren(editor.querySelector('[data-email-original-html]').content.cloneNode(true));
        changed = false;
        selection = null;
        error.hidden = true;
        visual.removeAttribute('aria-invalid');
        editor.querySelector('[data-email-reset-status]').textContent = 'Original message restored.';
    });
    source.form.addEventListener('submit', event => {
        sync();
        if (!source.value.trim() || source.value.length > 20000) {
            event.preventDefault();
            event.stopImmediatePropagation();
            error.textContent = !source.value.trim() ? 'Enter a message before sending.' : 'Keep the message under 20,000 characters.';
            error.hidden = false;
            visual.setAttribute('aria-invalid', 'true');
            visual.focus();
        }
    }, true);
    source.required = false;
    editor.querySelector('[data-email-fallback]').hidden = true;
    editor.querySelector('[data-visual-email]').hidden = false;
    reset.hidden = false;
}

document.querySelectorAll('[data-email-send-editor]').forEach(enhanceEmail);
