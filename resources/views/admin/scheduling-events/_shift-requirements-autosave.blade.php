const shiftPickers = [...document.querySelectorAll('.shift-requirements-picker')];
let shiftSavesPending = 0;
let shiftRequirementsChanged = false;
const shiftSaveFailures = new Set();
function refreshAfterShiftRequirementsClose() {
    if (shiftRequirementsChanged && !shiftSavesPending && !shiftSaveFailures.size && !shiftPickers.some(picker => picker.open)) {
        shiftRequirementsChanged = false;
        reloadSpreadsheetInPlace();
    }
}
shiftPickers.forEach(picker => {
    const setup = picker.querySelector('[name=requires_setup]');
    const setDown = picker.querySelector('[name=requires_set_down]');
    const feedback = picker.querySelector('.shift-save-status');
    const read = () => ({requires_setup: setup.checked, requires_set_down: setDown.checked});
    let confirmed = read();
    let version = 0;
    let pending = 0;
    let queue = Promise.resolve();
    [setup, setDown].forEach(input => input.addEventListener('change', () => {
        const payload = read();
        const submittedVersion = ++version;
        pending++;
        shiftSavesPending++;
        feedback.textContent = 'Saving…';
        queue = queue.then(async () => {
            try {
                const response = await fetch(picker.dataset.url, {
                    method: 'PUT',
                    headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
                    body: JSON.stringify(payload),
                });
                if (!response.ok) throw new Error('Could not save setup and set down.');
                confirmed = payload;
                shiftSaveFailures.delete(picker);
                shiftRequirementsChanged = true;
            } catch (error) {
                shiftSaveFailures.add(picker);
                if (submittedVersion === version) {
                    setup.checked = confirmed.requires_setup;
                    setDown.checked = confirmed.requires_set_down;
                }
                feedback.textContent = 'Could not save. Please try that selection again.';
                if (!picker.open) alert(feedback.textContent);
            } finally {
                pending--;
                shiftSavesPending--;
                if (!pending) feedback.textContent = shiftSaveFailures.has(picker) ? 'Could not save. Please try that selection again.' : 'Saved';
                refreshAfterShiftRequirementsClose();
            }
        });
    }));
    picker.addEventListener('toggle', () => {
        if (picker.open) {
            positionEquipmentPopup(picker);
            shiftPickers.forEach(other => { if (other !== picker) other.open = false; });
        } else refreshAfterShiftRequirementsClose();
    });
    picker.querySelector('.shift-requirements-close').addEventListener('click', () => { picker.open = false; });
});
document.addEventListener('click', event => {
    shiftPickers.forEach(picker => { if (picker.open && !picker.contains(event.target)) picker.open = false; });
});
window.addEventListener('resize', () => shiftPickers.filter(picker => picker.open).forEach(positionEquipmentPopup));
spreadsheet?.addEventListener('scroll', () => shiftPickers.filter(picker => picker.open).forEach(positionEquipmentPopup));
