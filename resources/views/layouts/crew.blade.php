<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $title ?? 'DancePro Crew' }}</title>
    @include('layouts.partials.foundation-styles')
    <style>
        :root { --brand:#0AA0DB; --dark:#0b202b; --line:#d7e4ea; --soft:#eaf8fd; --muted:#66737a; --green:#147a52; --amber:#9a5b00; }
        body { margin:0; background:#f4f8fa; color:#141719; font:14px/1.45 Arial,sans-serif; }
        a { color:#087fae; }
        h1,h2,h3,p { margin-top:0; }
        h1 { margin-bottom:4px; font-size:30px; line-height:1.1; }
        h2 { margin-bottom:6px; font-size:20px; }
        h3 { margin-bottom:3px; font-size:17px; }
        header { position:sticky; z-index:100; top:0; background:var(--dark); color:white; box-shadow:0 2px 8px rgba(11,32,43,.16); }
        .header-inner { display:flex; align-items:center; justify-content:space-between; max-width:980px; margin:auto; padding:13px 20px; }
        .brand { display:flex; align-items:center; width:150px; height:34px; color:white; text-decoration:none; }
        .brand img { display:block; width:100%; height:100%; object-fit:contain; object-position:left center; }
        .crew-nav { display:flex; align-items:center; gap:4px; margin-left:auto; margin-right:14px; }
        .crew-nav a { padding:5px 8px; border-radius:4px; color:#bcd0d9; font-size:11px; font-weight:700; text-decoration:none; }
        .crew-nav a.active { background:#153b4d; color:white; }
        .nav-indicator { display:inline-block; width:7px; height:7px; margin-right:2px; border-radius:50%; vertical-align:1px; box-shadow:0 0 0 2px rgba(255,255,255,.12); }
        .nav-indicator.shifts { background:#ef4444; }
        .nav-indicator.timesheets { background:#f2bd4c; }
        .nav-indicator.chat { background:#20a9df; }
        .user-menu { display:flex; align-items:center; gap:10px; }
        .admin-return { display:inline-flex; align-items:center; min-height:30px; padding:5px 9px; border:1px solid #54707d; border-radius:5px; color:#dce9ee; font-size:11px; font-weight:800; text-decoration:none; white-space:nowrap; }
        .admin-return:hover { border-color:#8bb5c7; background:#153b4d; color:white; }
        .user-profile-link { display:inline-flex; align-items:center; gap:6px; padding:5px 7px; border-radius:5px; color:#dce9ee; font-size:12px; font-weight:800; text-decoration:none; white-space:nowrap; }
        .user-profile-link:hover,.user-profile-link.active { background:#153b4d; color:white; }
        .user-profile-link svg { width:15px; height:15px; flex:0 0 15px; }
        main { max-width:980px; margin:auto; padding:24px 20px 50px; }
        section { margin-top:24px; }
        .card { margin-bottom:10px; padding:16px; border:1px solid var(--line); border-radius:8px; background:white; box-shadow:0 2px 7px rgba(18,45,57,.04); }
        .muted { color:var(--muted); }
        .eyebrow,.section-label,.detail-label,.type-label { display:block; margin-bottom:4px; color:#087fae; font-size:10px; font-weight:800; letter-spacing:.09em; text-transform:uppercase; }
        .page-heading,.section-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:14px; }
        .crew-hub-brand { display:flex; align-items:center; gap:7px; margin-bottom:5px; color:#087fae; font-size:11px; font-weight:900; letter-spacing:.12em; }
        .crew-hub-brand img { width:25px; height:25px; object-fit:contain; }
        .page-heading .muted { margin-bottom:0; }
        .action-summary { display:flex; gap:5px; flex-wrap:wrap; justify-content:flex-end; }
        .action-summary span { padding:5px 8px; border:1px solid #f0c77b; border-radius:99px; background:#fff8e8; color:#704300; font-size:10px; white-space:nowrap; }
        .next-shift-card { border-left:4px solid var(--brand); }
        .next-grid,.shift-card-top { display:grid; grid-template-columns:auto auto minmax(0,1fr) auto; align-items:center; gap:10px; }
        .date-tile { display:grid; width:48px; height:50px; place-content:center; border-radius:6px; background:#e8f7fc; color:#075d7c; text-align:center; }
        .date-tile strong { font-size:21px; line-height:1; }
        .date-tile span { font-size:9px; font-weight:800; letter-spacing:.08em; }
        .event-visual { position:relative; display:grid; width:70px; min-width:70px; height:50px; place-items:center; overflow:hidden; border:1px dashed #a9c0ca; border-radius:6px; background:#f6fafb; }
        .event-visual img { width:100%; height:100%; padding:2px; object-fit:contain; background:white; }
        .event-image-label { color:#8da1aa; font-size:8px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
        .shift-meta { display:flex; gap:10px; flex-wrap:wrap; margin-top:7px; color:var(--muted); font-size:12px; }
        .status-pill,.count-pill { display:inline-flex; align-items:center; width:max-content; padding:4px 7px; border-radius:99px; background:#eef2f4; color:#52636c; font-size:10px; font-weight:800; white-space:nowrap; }
        .status-pill.done { background:#dcfce7; color:var(--green); }
        .status-pill.attention { background:#fff2cc; color:var(--amber); }
        .event-heading { display:flex; align-items:center; gap:12px; }
        .availability-row { display:grid; grid-template-columns:minmax(130px,.7fr) minmax(0,1.3fr); align-items:center; gap:12px; margin-top:12px; padding-top:12px; border-top:1px solid #e7edf0; }
        .availability-form { display:grid; grid-template-columns:minmax(130px,1fr) auto; align-items:center; gap:8px; }
        input { width:100%; min-height:36px; padding:7px 9px; border:1px solid var(--line); border-radius:5px; }
        button,.button { display:inline-flex; align-items:center; justify-content:center; min-height:36px; padding:8px 12px; border:0; border-radius:5px; background:var(--brand); color:white; font-weight:800; text-decoration:none; cursor:pointer; }
        .button.secondary { border:1px solid #9bc7d8; background:#edf8fc; color:#087fae; }
        .available-button { background:#198754; }
        button.unavailable { background:#68767d; }
        .choice-buttons { display:flex; gap:5px; }
        .choice-buttons button:not(.selected) { opacity:.55; }
        .choice-buttons button.selected { outline:3px solid #10202a; outline-offset:2px; opacity:1; }
        .response-saved { margin-bottom:7px; font-size:11px; font-weight:800; text-align:right; }
        .response-saved.available { color:var(--green); }
        .response-saved.unavailable { color:#4f5d64; }
        .filter-tabs { display:flex; gap:4px; margin:8px 0 10px; padding:3px; overflow:auto; border:1px solid var(--line); border-radius:7px; background:white; }
        .filter-tabs a { padding:7px 10px; border-radius:5px; color:#52636c; font-size:12px; font-weight:700; text-decoration:none; white-space:nowrap; }
        .filter-tabs a.active { background:var(--dark); color:white; }
        .filter-tabs span { display:inline-grid; min-width:17px; height:17px; margin-left:3px; place-items:center; border-radius:50%; background:#f1bc4b; color:#382300; font-size:9px; }
        .main-shift-menu { margin-top:20px; }
        .main-shift-menu a { flex:1; text-align:center; }
        .main-shift-menu .alert-count { margin:0 3px 0 0; background:#e04444; color:white; }
        .shift-title { min-width:0; }
        .shift-title h3 { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .detail-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin-top:14px; padding-top:12px; border-top:1px solid #e7edf0; }
        .detail-grid div { display:flex; flex-direction:column; gap:2px; min-width:0; }
        .detail-grid strong,.detail-grid span,.detail-grid a { overflow:hidden; font-size:12px; text-overflow:ellipsis; }
        .venue-details { margin-top:11px; padding:8px 10px; border-radius:5px; background:#f2f7f9; font-size:12px; }
        .venue-details summary { color:#315564; font-weight:800; cursor:pointer; }
        .venue-details p { margin:8px 0 0; }
        .responsibilities { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-top:10px; padding:9px 10px; border-radius:5px; background:#f2f7f9; font-size:11px; }
        .responsibilities .detail-label { width:100%; margin:0; }
        .responsibilities div { display:flex; gap:5px; padding:3px 6px; border-radius:4px; background:white; }
        .responsibilities span { color:var(--muted); }
        .shift-action { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:11px; padding:10px; border-radius:5px; background:#fff8e8; }
        .shift-action p { margin:0; color:#704300; font-size:11px; }
        .empty-state { text-align:center; }
        .empty-state p { margin:3px 0 0; }
        .updates-list { padding-top:5px; padding-bottom:5px; }
        .updates-list > div { padding:10px 0; border-bottom:1px solid #e7edf0; }
        .updates-list > div:last-child { border:0; }
        .updates-list p { margin:2px 0; font-size:12px; }
        .updates-list span { font-size:10px; }
        .notification-title { display:flex; align-items:center; gap:7px; }
        .unread-dot { width:8px; height:8px; flex:0 0 8px; border-radius:50%; background:#e04444; box-shadow:0 0 0 2px #fee2e2; }
        .chat-shell { display:grid; grid-template-columns:330px minmax(0,1fr); min-height:620px; margin-top:16px; overflow:hidden; border:1px solid var(--line); border-radius:9px; background:white; }
        .chat-sidebar { border-right:1px solid var(--line); background:white; }
        .chat-sidebar-top { padding:14px; border-bottom:1px solid var(--line); }
        .chat-new { position:relative; }
        .chat-new summary { display:inline-flex; min-height:34px; padding:7px 12px; align-items:center; border-radius:5px; background:var(--brand); color:white; font-weight:800; cursor:pointer; list-style:none; }
        .chat-new summary::-webkit-details-marker { display:none; }
        .chat-new-panel { position:absolute; z-index:20; top:40px; left:0; width:285px; padding:12px; border:1px solid var(--line); border-radius:7px; background:white; box-shadow:0 10px 28px rgba(18,45,57,.2); }
        .chat-new-panel form { display:grid; gap:9px; }
        .chat-new-panel select { width:100%; min-height:38px; padding:7px; border:1px solid var(--line); border-radius:5px; }
        .chat-search { margin-top:12px; }
        .chat-tabs { display:flex; gap:2px; padding:9px 10px; overflow-x:auto; border-bottom:1px solid var(--line); }
        .chat-tabs a { padding:6px 7px; border-radius:4px; color:#52636c; font-size:10px; font-weight:800; text-decoration:none; white-space:nowrap; }
        .chat-tabs a.active { background:var(--soft); color:#087fae; }
        .chat-list { max-height:530px; overflow-y:auto; }
        .chat-list-item { display:grid; grid-template-columns:38px minmax(0,1fr) auto; gap:9px; padding:12px 13px; border-bottom:1px solid #edf2f4; color:inherit; text-decoration:none; }
        .chat-list-item:hover,.chat-list-item.active { background:#eaf8fd; }
        .chat-avatar { display:grid; width:38px; height:38px; place-items:center; border-radius:50%; background:#cbeffc; color:#087fae; font-size:11px; font-weight:900; }
        .chat-avatar.direct { background:#dff4e9; color:var(--green); }
        .chat-preview { min-width:0; }
        .chat-preview strong,.chat-preview span { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .chat-preview span { margin-top:2px; color:var(--muted); font-size:11px; }
        .chat-list-meta { display:flex; align-items:flex-end; flex-direction:column; gap:6px; color:var(--muted); font-size:9px; white-space:nowrap; }
        .unread-count { display:grid; min-width:18px; height:18px; padding:0 5px; place-items:center; border-radius:99px; background:#20a9df; color:white; font-size:9px; font-weight:900; }
        .chat-window { display:flex; min-width:0; flex-direction:column; background:#eef8fc; }
        .chat-window-header { padding:15px 18px; border-bottom:1px solid var(--line); background:white; }
        .chat-window-header p { margin:2px 0 0; color:var(--muted); font-size:11px; }
        .chat-messages { display:flex; min-height:450px; max-height:510px; padding:18px; overflow-y:auto; flex-direction:column; gap:10px; }
        .chat-bubble { align-self:flex-start; max-width:76%; padding:9px 11px; border-radius:4px 12px 12px 12px; background:white; box-shadow:0 1px 3px rgba(18,45,57,.08); }
        .chat-bubble.mine { align-self:flex-end; border-radius:12px 4px 12px 12px; background:#cceffc; }
        .chat-bubble.announcement { border:1px solid #f0c77b; background:#fff8e8; }
        .chat-bubble strong { display:block; margin-bottom:3px; color:#315564; font-size:10px; }
        .chat-bubble p { margin:0; white-space:pre-wrap; }
        .chat-bubble time { display:block; margin-top:4px; color:var(--muted); font-size:9px; text-align:right; }
        .chat-compose { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:8px; padding:13px; border-top:1px solid var(--line); background:white; }
        .chat-compose textarea { min-height:42px; max-height:110px; }
        .chat-welcome { display:grid; height:100%; min-height:600px; padding:30px; place-content:center; color:var(--muted); text-align:center; }
        .directory-list { display:grid; gap:7px; margin-top:12px; }
        .directory-row { display:grid; grid-template-columns:minmax(180px,1.3fr) minmax(150px,1fr) auto; align-items:center; gap:14px; margin:0; padding:12px 14px; }
        .directory-primary,.directory-contact { display:flex; min-width:0; flex-direction:column; }
        .directory-primary strong,.directory-contact span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .directory-contact a { width:max-content; font-weight:800; text-decoration:none; }
        .directory-action { min-width:90px; }
        .notice { max-width:980px; margin:12px auto 0; padding:10px 20px; border-radius:5px; background:var(--soft); }
        label { display:flex; flex-direction:column; gap:4px; color:#40545e; font-size:11px; font-weight:700; }
        textarea { width:100%; min-height:76px; padding:8px 9px; border:1px solid var(--line); border-radius:5px; font:inherit; resize:vertical; }
        .profile-warning { margin-top:16px; padding:11px 13px; border:1px solid #f0c77b; border-radius:6px; background:#fff8e8; color:#704300; }
        .profile-section h2 { margin-bottom:12px; }
        .profile-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
        .profile-grid .wide { grid-column:span 2; }
        .address-search { grid-column:1/-1; }
        .address-search-box { min-height:43px; }
        gmp-place-autocomplete { width:100%; color:#141719; }
        .vehicle-row { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-top:12px; padding-top:12px; border-top:1px solid #e7edf0; }
        .vehicle-row .wide { grid-column:span 2; }
        .contract-row { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 0; border-top:1px solid #e7edf0; }
        .contract-row > div { display:flex; flex-direction:column; }
        .contract-document { font-size:14px; line-height:1.65; }
        .contract-document h2,.contract-document h3,.contract-document h4 { margin-top:1.3em; }
        .contract-document blockquote { margin-left:0; padding-left:14px; border-left:3px solid var(--line); color:var(--muted); }
        .resource-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .resource-card { display:flex; flex-direction:column; justify-content:space-between; }
        .resource-copy { margin:8px 0; }
        .resource-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .invoice-card-actions { margin-top:12px; padding-top:12px; border-top:1px solid #e7edf0; }
        .invoice-card-actions > span { flex:1; }
        .invoice-overview { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin-bottom:20px; }
        .invoice-overview > div { display:flex; min-height:72px; flex-direction:column; justify-content:center; gap:4px; padding:12px 14px; border-radius:6px; background:#f2f7f9; }
        .invoice-overview span { color:var(--muted); font-size:9px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
        .invoice-overview strong { font-size:13px; }
        .invoice-table-wrap { overflow-x:auto; border:1px solid var(--line); border-radius:7px; }
        .invoice-table { width:100%; min-width:720px; border-collapse:collapse; }
        .invoice-table th,.invoice-table td { padding:11px 12px; border-bottom:1px solid #e7edf0; text-align:left; vertical-align:top; }
        .invoice-table th { background:#f7fafb; color:var(--muted); font-size:9px; letter-spacing:.07em; text-transform:uppercase; }
        .invoice-table td { font-size:12px; }
        .invoice-table th:nth-last-child(-n+3),.invoice-table td:nth-last-child(-n+3) { text-align:right; }
        .invoice-table tfoot th { border:0; background:#edf8fc; color:#141719; font-size:12px; }
        .invoice-preview-note { margin:10px 0 16px; }
        .invoice-preview-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:16px; }
        .timesheet-pending-card { padding-bottom:10px; }
        .timesheet-pending-row { display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:14px; }
        .timesheet-selector { width:18px; height:18px; min-height:0; cursor:pointer; }
        .timesheet-checkbox-placeholder { width:18px; }
        .timesheet-pending-status { display:grid; justify-items:end; gap:5px; }
        .timesheet-edit-times { margin-bottom:0; }
        .next-shift-workflow { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; margin-top:16px; }
        .workflow-step { position:relative; display:flex; min-height:145px; flex-direction:column; align-items:flex-start; gap:9px; padding:16px; border:1px solid #ead8a8; border-radius:7px; background:#fff8e8; }
        .workflow-step.complete { border-color:#a5d8c5; background:#edf9f4; }
        .workflow-step h3 { margin:0; padding-right:28px; }
        .workflow-step.complete > strong { color:var(--green); }
        .workflow-step form { margin:0; }
        .workflow-step .venue-details { width:100%; margin:4px 0 0; }
        .workflow-number { position:absolute; top:12px; right:12px; display:grid; width:24px; height:24px; place-items:center; border-radius:50%; background:#d9e8ed; color:#31505d; font-size:11px; font-weight:900; }
        .workflow-step.complete .workflow-number { background:#ccebdd; color:var(--green); }
        .shift-card .resource-actions .button { flex:1; min-width:90px; }
        .checklist-group { margin-top:16px; }
        .checklist-item { display:grid; grid-template-columns:auto 1fr; align-items:start; gap:10px; margin-top:7px; padding:11px; border:1px solid var(--line); border-radius:6px; background:#fff; cursor:pointer; font-size:13px; }
        .checklist-item input { width:20px; height:20px; }
        .checklist-item.complete { border-color:#a5d8c5; background:#edf9f4; color:var(--green); text-decoration:line-through; }
        .prestart-dialog { width:min(92vw,680px); max-width:none; max-height:88vh; padding:24px; border:0; border-radius:10px; box-shadow:0 20px 60px rgba(0,0,0,.28); }
        .prestart-dialog::backdrop { background:rgba(3,24,35,.72); }
        .prestart-dialog-heading,.prestart-dialog-actions { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; }
        .prestart-dialog-heading h2 { margin:2px 0 5px; }
        .prestart-progress { margin:18px 0 10px; padding:11px 13px; border-radius:6px; background:var(--light-blue); color:var(--blue); }
        .prestart-dialog .checklist-group { margin-top:18px; }
        .prestart-dialog-actions { align-items:center; margin-top:22px; padding-top:18px; border-top:1px solid var(--line); }
        .event-messages { max-height:520px; margin-top:14px; overflow-y:auto; }
        .event-message { margin-top:8px; padding:11px; border:1px solid var(--line); border-radius:6px; background:#f8fbfc; }
        .event-message.announcement { border-color:#f0c77b; background:#fff8e8; }
        .event-message p { margin:8px 0; }
        .message-heading { display:flex; justify-content:space-between; gap:10px; font-size:12px; }
        .message-heading span { color:var(--muted); font-size:10px; white-space:nowrap; }
        .event-message form { margin-top:10px; }
        .announcement-read { margin-top:9px; color:var(--green); font-size:11px; font-weight:800; }
        .message-form { display:grid; gap:10px; margin-top:16px; padding-top:14px; border-top:1px solid var(--line); }
        .team-finish-list { display:grid; gap:7px; }
        .team-finish-list label { display:grid; grid-template-columns:auto 1fr; align-items:center; gap:9px; padding:9px; border:1px solid var(--line); border-radius:5px; background:#f8fbfc; }
        .team-finish-list > div { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:9px; border:1px solid var(--line); border-radius:5px; background:#f8fbfc; }
        .team-finish-list > div span { color:var(--muted); font-size:13px; text-align:right; }
        .team-finish-list input { width:18px; height:18px; min-height:0; }
        .calendar-scroll { display:grid; gap:14px; max-height:72vh; overflow-y:auto; scroll-behavior:smooth; }
        .calendar-month { margin:0; }
        .calendar-month h2 { margin-bottom:12px; }
        .calendar-grid { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:3px; }
        .calendar-weekday { padding:4px 0; color:var(--muted); font-size:9px; font-weight:800; text-align:center; text-transform:uppercase; }
        .calendar-day { display:grid; min-height:46px; padding:5px; place-content:center; place-items:center; border:1px solid transparent; border-radius:6px; background:transparent; color:#263940; font-size:12px; }
        button.calendar-day { width:100%; cursor:pointer; }
        button.calendar-day:hover,button.calendar-day[aria-expanded="true"] { border-color:#85cbe6; background:#eaf8fd; color:#075d7c; }
        .calendar-day.today { border-color:#b9cbd2; }
        .calendar-dot { width:6px; height:6px; margin-top:3px; border-radius:50%; background:var(--brand); }
        .calendar-selection { margin-top:12px; padding-top:12px; border-top:1px solid var(--line); }
        .calendar-selection[hidden] { display:none; }
        .profile-save { position:sticky; bottom:10px; display:flex; justify-content:flex-end; padding:10px; border:1px solid var(--line); border-radius:7px; background:rgba(255,255,255,.94); box-shadow:0 5px 18px rgba(18,45,57,.12); }
        @media(max-width:650px) {
            main { padding:18px 12px 40px; }
            h1 { font-size:26px; }
            .next-grid,.shift-card-top { grid-template-columns:auto auto minmax(0,1fr); }
            .next-grid .status-pill,.shift-card-top .status-pill { grid-column:3; }
            .availability-row,.availability-form { grid-template-columns:1fr; }
            .choice-buttons button { flex:1; }
            .detail-grid { grid-template-columns:1fr 1fr; }
            .detail-grid > div:last-child { grid-column:1/-1; }
            .shift-action { align-items:stretch; flex-direction:column; }
            .header-inner { flex-wrap:wrap; }
            .crew-nav { order:3; width:100%; margin:8px 0 0; }
            .crew-nav a { flex:1; text-align:center; }
            .profile-grid,.vehicle-row { grid-template-columns:1fr 1fr; }
            .invoice-overview { grid-template-columns:1fr; }
            .resource-grid { grid-template-columns:1fr; }
            .next-shift-workflow { grid-template-columns:1fr; }
            .profile-grid .wide,.vehicle-row .wide { grid-column:1/-1; }
            .calendar-day { min-height:39px; padding:3px; }
            .chat-shell { grid-template-columns:1fr; min-height:0; }
            .chat-sidebar { border-right:0; }
            .chat-shell.has-selection .chat-sidebar { display:none; }
            .chat-shell:not(.has-selection) .chat-window { display:none; }
            .chat-window { min-height:calc(100vh - 170px); }
            .chat-window-header { padding:12px; }
            .chat-messages { min-height:calc(100vh - 310px); max-height:none; }
            .chat-back { display:inline-block !important; margin-right:7px; }
            .directory-row { grid-template-columns:minmax(0,1fr) auto; gap:8px; }
            .directory-contact { grid-column:1; }
            .directory-action { grid-column:2; grid-row:1/3; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <header>
        <div class="header-inner">
            <a class="brand" href="{{ route('crew.availability.index') }}" aria-label="DancePro Crew home"><img src="{{ asset('images/brand/dancepro-logo-inverse.png') }}" alt="DancePro Photography & Video"></a>
            <nav class="crew-nav">
                <a class="{{ request()->routeIs('crew.availability.*', 'crew.assignments.*', 'crew.cover.*') ? 'active' : '' }}" href="{{ route('crew.availability.index') }}">
                    @if($crewNavigationIndicators['shifts'])
                        <span class="nav-indicator shifts" title="{{ $crewNavigationIndicators['shifts'] }} shift actions" aria-label="{{ $crewNavigationIndicators['shifts'] }} shift actions"></span>
                    @endif
                    My Shifts
                </a>
                <a class="{{ request()->routeIs('crew.timesheets.*') ? 'active' : '' }}" href="{{ route('crew.timesheets.index') }}">
                    @if($crewNavigationIndicators['timesheets'])
                        <span class="nav-indicator timesheets" title="{{ $crewNavigationIndicators['timesheets'] }} pending timesheets or invoices" aria-label="{{ $crewNavigationIndicators['timesheets'] }} pending timesheets or invoices"></span>
                    @endif
                    My Timesheets
                </a>
                <a class="{{ request()->routeIs('crew.chat.*') ? 'active' : '' }}" href="{{ route('crew.chat.index') }}">
                    @if($crewNavigationIndicators['chat'])
                        <span class="nav-indicator chat" title="{{ $crewNavigationIndicators['chat'] }} unread chat messages" aria-label="{{ $crewNavigationIndicators['chat'] }} unread chat messages"></span>
                    @endif
                    My Chat
                </a>
                <a class="{{ request()->routeIs('crew.directory.*') ? 'active' : '' }}" href="{{ route('crew.directory.index') }}">My Crew</a>
                <a class="{{ request()->routeIs('crew.training.*') ? 'active' : '' }}" href="{{ route('crew.training.index') }}">My Training</a>
                <a class="{{ request()->routeIs('crew.help.*') ? 'active' : '' }}" href="{{ route('crew.help.index') }}">My Handbook</a>
                @if(config('security.two_factor.enabled'))
                    <a href="{{ route('account.security') }}">Security</a>
                @endif
            </nav>
            <div class="user-menu">
                @if(auth()->user()?->canAccessAdmin())
                    <a class="admin-return" href="{{ route('admin.dashboard') }}">Back to Admin</a>
                @endif
                <a class="user-profile-link {{ request()->routeIs('crew.profile.*') ? 'active' : '' }}" href="{{ route('crew.profile.edit') }}" title="Open My Profile" aria-label="Open My Profile for {{ auth()->user()?->name }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg>
                    <span>{{ auth()->user()?->name }}</span>
                </a>
            </div>
        </div>
    </header>
    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="notice">{{ $errors->first() }}</div>@endif
    <main>@yield('content')</main>
    @stack('scripts')
    <script>
        const crewPagePositionKey = `dancepro-crew-position:${window.location.pathname}`;
        const savedCrewPagePosition = sessionStorage.getItem(crewPagePositionKey);

        if (savedCrewPagePosition !== null) {
            sessionStorage.removeItem(crewPagePositionKey);
            requestAnimationFrame(() => window.scrollTo(0, Number(savedCrewPagePosition)));
        }

        window.addEventListener('beforeunload', () => {
            sessionStorage.setItem(crewPagePositionKey, String(window.scrollY));
        });
    </script>
</body>
</html>
