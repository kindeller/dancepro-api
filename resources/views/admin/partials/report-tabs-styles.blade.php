<style>
    .report-tab-groups { display:flex; flex-wrap:wrap; gap:4px 18px; margin:22px 0 14px; overflow-x:auto; border-bottom:1px solid var(--line); }
    .report-tab-group { display:flex; gap:4px; align-items:flex-end; flex:0 0 auto; }
    .report-tab-group-label { padding:0 7px 12px 0; color:var(--muted); font-size:11px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
    .report-tabs { display:flex; gap:4px; margin:0; }
    .report-tabs a { flex:0 0 auto; padding:12px 16px 11px; border:1px solid transparent; border-bottom:0; border-radius:8px 8px 0 0; color:var(--muted); font-weight:800; text-decoration:none; }
    .report-tabs a:hover { background:var(--soft); color:var(--brand-strong); text-decoration:none; }
    .report-tabs a[aria-selected="true"], .report-tabs a[aria-current="page"] { margin-bottom:-1px; border-color:var(--line); border-bottom-color:var(--panel); background:var(--panel); color:var(--ink); }
</style>
