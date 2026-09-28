@push('styles')
<style>
    .hub-hero-grid { display:grid;grid-template-columns:minmax(0,1fr);gap:24px; }
    .hub-identity { display:flex;align-items:center;gap:24px;min-width:0; }
    .hub-studio-logo { width:clamp(100px,11vw,150px);height:clamp(82px,9vw,120px);flex:0 0 auto;border-radius:10px;background:#fff;object-fit:contain;padding:8px;box-shadow:0 10px 30px rgba(0,0,0,.2); }
    .hub-identity-copy { min-width:0; }
    .hub-summary { display:grid;grid-template-columns:repeat(3,minmax(112px,1fr));gap:10px;justify-self:end; }
    .hub-stat { display:block;color:#fff;text-decoration:none;min-width:112px;border:1px solid rgba(255,255,255,.2);border-radius:10px;background:rgba(255,255,255,.1);padding:14px 16px;backdrop-filter:blur(8px); }
    .hub-stat:hover,.hub-stat:focus-visible { background:rgba(255,255,255,.2);outline:2px solid #fff;outline-offset:2px; }
    #events,#messages { scroll-margin-top:65px; }
    .hub-stat strong { display:block;font-size:25px;line-height:1.1; }
    .hub-stat span { color:#d9e8ee;font-size:12px;font-weight:750; }
    .hub-intro { display:grid;grid-template-columns:minmax(0,1.5fr) minmax(260px,.5fr);gap:20px;margin-bottom:26px; }
    .hub-card { overflow:visible; }
    .hub-card-header { display:grid;grid-template-columns:minmax(0,1fr) auto 34px;gap:20px;align-items:center;padding:20px 22px;cursor:pointer;list-style:none; }
    .hub-card-header::-webkit-details-marker { display:none; }
    .hub-card-header h2 { margin:3px 0 5px;font-size:26px; }
    .hub-card-header::after { content:'⌄';display:grid;width:34px;height:34px;place-items:center;border-radius:50%;background:#eaf6fa;font-size:20px;transition:transform .2s; }
    details[open]>.hub-card-header::after { transform:rotate(180deg); }
    .hub-card-content { border-top:1px solid var(--line);padding:22px; }
    .hub-progress { display:flex;align-items:center;gap:10px; }
    .hub-progress-track { width:120px;height:8px;overflow:hidden;border-radius:99px;background:#dfe9ed; }
    .hub-progress-track span { display:block;height:100%;border-radius:inherit;background:#20a66a; }
    .hub-event-meta { display:flex;flex-wrap:wrap;gap:8px 18px;margin-top:10px;color:var(--muted);font-size:14px;font-weight:650; }
    .hub-badge { display:inline-flex;align-items:center;gap:6px;border-radius:99px;background:#eaf6fa;color:#075d7e;padding:5px 10px;font-size:12px;font-weight:850; }
    .hub-badge.success { background:#e8f8ef;color:#12643e; }
    .hub-badge.warning { background:#fff4db;color:#865b00; }
    .hub-badge.danger { background:#fff0f1;color:#a5202c; }
    .booking-panel { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 24px;border-radius:10px;background:#f5f9fb;padding:18px; }
    .booking-detail span { display:block;color:var(--muted);font-size:12px;font-weight:800;letter-spacing:.04em;text-transform:uppercase; }
    .booking-detail strong { display:block;margin-top:2px; }
    .booking-detail small { display:block;color:var(--muted); }
    .booking-panel label { margin-bottom:0; }
    .booking-detail input[readonly] { min-height:0;border:0;background:transparent;padding:2px 0;box-shadow:none;color:var(--ink);font-weight:800; }
    .booking-change-form.editing .booking-detail input { min-height:46px;border:1px solid var(--accent);background:#fff;padding:10px 12px;font-weight:650; }
    .booking-services { display:flex;flex-wrap:wrap;gap:8px 16px;margin-top:6px; }
    .booking-services .choice { margin:0; }
    .hub-actions { display:flex;flex-wrap:wrap;gap:10px;margin-top:16px; }
    .checklist { display:grid;gap:10px;margin:20px 0; }
    .check-item { display:grid;grid-template-columns:32px minmax(0,1fr) auto;gap:12px;align-items:start;border:1px solid var(--line);border-radius:10px;padding:14px;background:#fff; }
    .check-icon { display:grid;width:30px;height:30px;place-items:center;border-radius:50%;background:#edf2f4;color:#65757d;font-weight:900; }
    .check-item.complete .check-icon { background:#dff6e9;color:#167246; }
    .check-item.overdue { border-color:#f0bdc1;background:#fffafa; }
    .check-item p { margin:3px 0 0;color:var(--muted);font-size:13px; }
    .hub-review-action { margin:0;align-items:center; }
    .hub-review-action h3 { margin:0;font-size:18px; }
    .hub-review-action.unavailable { background:#f2f4f5;color:var(--muted); }
    .hub-review-action.ready { border-color:#81cbe3;background:#f2fbfe; }
    .hub-review-action.ready .check-icon { background:#e0f4fc;color:#075d7e; }
    .hub-progress-meta { display:flex;flex-wrap:wrap;justify-content:flex-end;align-items:center;gap:8px 14px;max-width:300px; }
    .hub-login-code { display:inline-flex;align-items:center;gap:6px;max-width:100%;padding:6px 10px;border:1px solid #c5e7f4;border-radius:8px;background:#eaf6fa;color:#075d7e;font-size:12px;font-weight:750; }
    .hub-login-code > span { white-space:nowrap; }
    .hub-login-code code { min-width:0;font-size:14px;font-weight:850;letter-spacing:.04em;overflow-wrap:anywhere;user-select:all; }
    .check-due { color:var(--muted);font-size:12px;font-weight:800;text-align:right; }
    .check-item.overdue .check-due { color:#a5202c; }
    .hub-workspace { display:grid;grid-template-columns:1fr;gap:10px; }
    .hub-tool { border:1px solid var(--line);border-radius:10px;padding:18px; }
    details.hub-tool { padding:0; }
    .hub-tool-summary { display:flex;align-items:center;justify-content:space-between;gap:12px;cursor:pointer;list-style:none;padding:16px 18px; }
    .hub-tool-summary::-webkit-details-marker { display:none; }
    .hub-tool-summary::after { content:'+';display:grid;width:28px;height:28px;place-items:center;border-radius:50%;background:#eaf6fa;font-weight:900; }
    details.hub-tool[open]>.hub-tool-summary::after { content:'−'; }
    .hub-tool-summary h3 { margin:0; }
    .hub-tool-summary.action-summary { display:grid;grid-template-columns:32px minmax(0,1fr) auto 28px;align-items:center; }
    .hub-tool-summary.action-summary::after { grid-column:4;grid-row:1 / span 2; }
    .hub-tool-summary.action-summary .check-icon { grid-column:1;grid-row:1 / span 2; }
    .hub-tool-summary.action-summary .action-copy { grid-column:2;min-width:0; }
    .hub-tool-summary.action-summary .action-copy p { margin:3px 0 0; }
    .hub-tool-summary.action-summary .check-due { grid-column:3;grid-row:1 / span 2;white-space:nowrap; }
    .hub-tool-content { border-top:1px solid var(--line);padding:18px; }
    .hub-tool h3 { display:flex;align-items:center;gap:8px;margin-bottom:5px; }
    .hub-tool p { margin-bottom:14px;color:var(--muted);font-size:14px; }
    .hub-tool.full { grid-column:1 / -1; }
    .file-list { display:grid;gap:8px;margin-top:14px; }
    .file-row { display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;border-radius:8px;background:#f5f9fb;padding:11px 12px; }
    .file-row a { font-weight:800;text-decoration:none; }
    .message-list { display:grid;gap:12px; }
    .message-card { box-shadow:none; }
    .message-card summary { cursor:pointer;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center; }
    .empty-compact { border:1px dashed #b6c8cf;border-radius:10px;padding:24px;text-align:center; }
    @media(max-width:850px) {
        .hub-hero-grid,.hub-intro,.hub-workspace,.booking-panel { grid-template-columns:1fr; }
        .hub-identity { align-items:flex-start;gap:15px; }
        .hub-studio-logo { width:82px;height:70px;padding:6px; }
        .hub-summary { grid-template-columns:repeat(3,1fr);justify-self:stretch; }
        .hub-stat { min-width:0;padding:11px; }
        .hub-card-header { grid-template-columns:minmax(0,1fr) 34px; }
        .hub-progress { grid-column:1 / -1; }
        .check-item { grid-template-columns:32px minmax(0,1fr); }
        .check-due { grid-column:2;text-align:left; }
        .hub-production-item { grid-template-columns:30px minmax(0,1fr) auto;gap:8px; }
        .hub-progress-meta { max-width:120px; }
        .hub-login-code { flex-wrap:wrap;justify-content:center; }
        .hub-login-code code { flex:1 0 100%;text-align:center; }
        .hub-progress-meta .check-due { text-align:right; }
        .hub-tool-summary.action-summary { grid-template-columns:32px minmax(0,1fr) 28px; }
        .hub-tool-summary.action-summary::after { grid-column:3; }
        .hub-tool-summary.action-summary .check-due { grid-column:2;grid-row:2;text-align:left;white-space:normal; }
    }
</style>
@endpush
