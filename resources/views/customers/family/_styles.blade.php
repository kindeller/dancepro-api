<style>
.family-row{border-top:1px solid var(--line);padding:18px 0}
.family-form,.family-read-row{display:grid;grid-template-columns:minmax(220px,1fr) minmax(0,2.5fr) auto;align-items:start;gap:16px 24px;margin:0}
.family-identity{min-width:0}.family-identity strong{font-size:16px}.family-identity .meta{overflow-wrap:anywhere}.family-identity-top{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:3px}
.family-status{font-size:12px;font-weight:800;border-radius:18px;padding:4px 9px;background:#e5f5fd;color:#087fab;white-space:nowrap}.family-status.active{background:#e4f7ec;color:#147a52}.family-status.dancer{background:#eef1f3;color:#526471}
.family-permissions{display:flex;justify-content:flex-end;align-items:flex-start;flex-wrap:wrap;gap:12px 22px;border:0;margin:0;padding:5px 0}
.family-permission{position:relative;display:flex;align-items:center;gap:6px;max-width:200px}
.family-permission[hidden]{display:none!important}
.family-permissions label{display:flex;align-items:center;gap:8px;font-size:13px;line-height:1.4;font-weight:600;margin:0;color:var(--ink,#152b37)}
.family-permissions input[type=checkbox]{width:18px;height:18px;margin:0;flex:0 0 18px;accent-color:#009ed1}
.family-help{border:0;border-radius:50%;padding:0;width:18px;height:18px;flex:0 0 18px;background:#e8f4fa;color:#087fab;font-size:12px;font-weight:800;cursor:help}
.family-tip{pointer-events:none;display:none;position:absolute;z-index:10;top:calc(100% + 8px);right:0;width:240px;max-width:calc(100vw - 64px);padding:12px;background:#102a3b;color:#fff;border-radius:8px;box-shadow:0 5px 18px #0002;font-size:13px;font-weight:400;line-height:1.5}
.family-help:hover + .family-tip,.family-help:focus + .family-tip{display:block}
.family-form .button,.family-actions .button{min-height:36px;padding:8px 14px;font-size:13px}
.family-names{display:grid;grid-template-columns:1fr 1fr;gap:12px}.family-fields{display:grid;gap:12px;margin-top:12px}
.family-form input,.family-form textarea{max-width:100%;box-sizing:border-box}.family-form input:not([type=checkbox]):not([type=hidden]){min-height:38px;padding:9px 10px}
.family-details summary,.family-search summary{cursor:pointer;color:#087fab;font-size:13px;font-weight:700;margin-top:6px}
.family-search{grid-column:1/-1;max-width:600px}.family-search[hidden],.family-form [hidden]{display:none!important}
.family-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:8px}.family-actions form{margin:0}.family-actions summary{cursor:pointer;font-size:13px;color:#536c7c}
.family-actions .meta{font-size:12px;margin:0}.family-note{font-size:13px;color:#5b7280;margin:12px 0 18px}.family-note summary{cursor:pointer}
.family-save{display:flex;align-items:center;gap:8px;justify-content:flex-end}
.family-trash{display:grid;place-items:center;width:36px;height:36px;border:1px solid #efc8c6;border-radius:6px;background:#fff;color:#b42318;cursor:pointer;flex:0 0 36px}.family-trash:hover{background:#fff0ef}.family-trash svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.family-remove-dialog{max-width:460px;width:calc(100% - 40px);border:1px solid var(--line);border-radius:12px;padding:24px;color:#142b38;box-shadow:0 20px 70px #0004}.family-remove-dialog::backdrop{background:#071b2b99}.family-remove-dialog h3{margin-top:0}.family-dialog-actions{display:flex;justify-content:flex-end;gap:10px}.family-dialog-actions [data-family-remove-confirm]{background:#b42318}
.family-add{margin-top:18px}.family-add[hidden]{display:none}.family-add-toggle{margin-top:16px}
@media(max-width:1150px){.family-form,.family-read-row{grid-template-columns:minmax(210px,1fr) minmax(0,2fr) auto}.family-permissions{gap:10px 15px}.family-permission{max-width:175px}}
@media(max-width:760px){.family-form,.family-read-row{grid-template-columns:1fr auto;gap:12px}.family-identity{grid-column:1/-1}.family-permissions{grid-column:1;justify-content:flex-start;display:grid;gap:12px}.family-permission{max-width:240px}.family-save{grid-column:2;grid-row:2;align-self:end;flex-direction:column;align-items:flex-end}.family-search{grid-column:1/-1}.family-tip{left:0;right:auto}.family-names{grid-template-columns:1fr 1fr}}
@media(max-width:380px){.family-names{grid-template-columns:1fr}}
</style>
