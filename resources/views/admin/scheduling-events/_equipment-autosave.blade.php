const equipmentPickers = [...document.querySelectorAll('.equipment-picker')];
let equipmentPending = 0;
let equipmentChanged = false;
const equipmentFailures = new Set();
const readEquipmentRow = row => ({
    item_code: row.dataset.code,
    is_bringing: row.querySelector('.equipment-bring').checked,
    is_taking: row.querySelector('.equipment-take').checked,
    other_notes: row.querySelector('.equipment-other').value || null,
});
function positionEquipmentPopup(picker) {
    const popup = picker.querySelector('.equipment-popover');
    const anchor = picker.querySelector('summary').getBoundingClientRect();
    const bounds = popup.getBoundingClientRect();
    const preferredLeft = picker.dataset.popupAlign === 'left' ? anchor.left : anchor.right - bounds.width;
    popup.style.left = Math.max(12, Math.min(preferredLeft, window.innerWidth - bounds.width - 12)) + 'px';
    popup.style.top = Math.max(12, Math.min(anchor.bottom + 6, window.innerHeight - bounds.height - 12)) + 'px';
}
window.addEventListener('resize', () => equipmentPickers.filter(picker => picker.open).forEach(positionEquipmentPopup));
spreadsheet?.addEventListener('scroll', () => equipmentPickers.filter(picker => picker.open).forEach(positionEquipmentPopup));
function refreshAfterEquipmentClose() {
    if (equipmentChanged && equipmentPending === 0 && equipmentFailures.size === 0 && !equipmentPickers.some(picker => picker.open)) {
        equipmentChanged = false;
        reloadSpreadsheetInPlace();
    }
}
equipmentPickers.forEach(picker => {
    let queue = Promise.resolve();
    let pending = 0;
    const feedback = picker.querySelector('.equipment-save-status');
    picker.querySelectorAll('.equipment-row[data-code]').forEach(row => {
        let confirmed = readEquipmentRow(row);
        let version = 0;
        row.querySelectorAll('.equipment-control').forEach(control => control.addEventListener('change', () => {
            const payload = readEquipmentRow(row);
            const submittedVersion = ++version;
            pending++;
            equipmentPending++;
            feedback.textContent = 'Saving…';
            queue = queue.then(async () => {
                try {
                    const response = await fetch(row.dataset.url, {
                        method: 'PUT',
                        headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
                        body: JSON.stringify(payload),
                    });
                    if (!response.ok) {
                        const error = await response.json();
                        throw new Error(Object.values(error.errors || {}).flat().join(' ') || error.message || 'Could not save equipment.');
                    }
                    confirmed = payload;
                    equipmentChanged = true;
                    equipmentFailures.delete(row);
                } catch (error) {
                    equipmentFailures.add(row);
                    if (submittedVersion === version) {
                        row.querySelector('.equipment-bring').checked = confirmed.is_bringing;
                        row.querySelector('.equipment-take').checked = confirmed.is_taking;
                        row.querySelector('.equipment-other').value = confirmed.other_notes || '';
                    }
                    feedback.textContent = error.message + ' Please try that selection again.';
                    if (!picker.open) alert(feedback.textContent);
                } finally {
                    pending--;
                    equipmentPending--;
                    if (!pending) {
                        feedback.textContent = [...equipmentFailures].some(failedRow => picker.contains(failedRow))
                            ? 'Some changes could not be saved. Please try those selections again.'
                            : 'Saved';
                    }
                    refreshAfterEquipmentClose();
                }
            });
        }));
    });
    picker.addEventListener('toggle', () => {
        if (picker.open) {
            positionEquipmentPopup(picker);
            equipmentPickers.forEach(other => { if (other !== picker) other.open = false; });
        } else refreshAfterEquipmentClose();
    });
    picker.querySelector('.equipment-popover-close').addEventListener('click', () => { picker.open = false; });
});
document.addEventListener('click', event => {
    equipmentPickers.forEach(picker => { if (picker.open && !picker.contains(event.target)) picker.open = false; });
});
