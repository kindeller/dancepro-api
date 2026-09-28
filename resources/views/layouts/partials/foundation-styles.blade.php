<style data-dancepro-foundation>
    *, *::before, *::after { box-sizing: border-box; }
    [hidden] { display: none !important; }
    input[type="checkbox"], input[type="radio"] {
        width: 18px;
        min-width: 18px;
        height: 18px;
        min-height: 18px;
        flex: 0 0 18px;
        margin: 0;
        padding: 0;
        accent-color: var(--accent, var(--brand, #0aa0db));
        vertical-align: middle;
    }
    input[type="checkbox"]:not(:disabled), input[type="radio"]:not(:disabled) { cursor: pointer; }
    label.choice,
    label:not([class]):has(> input[type="checkbox"]),
    label:not([class]):has(> input[type="radio"]) {
        display: flex;
        flex-direction: row;
        align-items: flex-start;
        gap: 10px;
    }
    label.choice > input[type="checkbox"],
    label.choice > input[type="radio"],
    label:not([class]) > input[type="checkbox"],
    label:not([class]) > input[type="radio"] { margin-top: 2px; }
</style>
