@extends('layouts.admin', [
    'title' => 'Concert email templates',
    'heading' => 'Concert email templates',
    'subheading' => 'Master wording for Tracker communications',
])

@push('styles')
<style>
    .email-template-shell { max-width:1280px; }
    .template-intro { display:flex; align-items:center; justify-content:space-between; gap:18px; margin-bottom:20px; padding:18px 20px; background:linear-gradient(135deg,#10202c,#17394a); color:#fff; border:0; }
    .template-intro h2 { margin:0 0 5px; color:#fff; font-size:20px; }
    .template-intro p { margin:0; color:#c6d8e1; }
    .template-count { flex:0 0 auto; display:grid; place-items:center; width:62px; height:62px; border-radius:17px; background:#0b9ed2; font-size:23px; font-weight:900; }
    .template-list { display:grid; gap:10px; }
    .template-row { overflow:hidden; }
    .template-row > summary { display:grid; grid-template-columns:48px minmax(0,1fr) auto auto 18px; align-items:center; gap:14px; min-height:76px; padding:10px 18px; list-style:none; cursor:pointer; }
    .template-row > summary::-webkit-details-marker { display:none; }
    .template-row > summary:hover { background:var(--soft); }
    .template-icon { display:grid; place-items:center; width:42px; height:42px; border-radius:12px; background:#e4f5fb; color:#087fa9; }
    .template-icon svg { width:22px; height:22px; }
    .template-title { min-width:0; }
    .template-title strong { display:block; font-size:16px; line-height:1.25; }
    .template-title small { display:block; margin-top:4px; }
    .template-phase { padding:4px 9px; border-radius:99px; background:#eef2ff; color:#4f46a5; font-size:11px; font-weight:850; letter-spacing:.04em; text-transform:uppercase; white-space:nowrap; }
    .template-phase.preparation { background:#ecfdf5; color:#08775a; }
    .template-phase.editing { background:#fff7ed; color:#a64b10; }
    .template-phase.completion { background:#f5f3ff; color:#7251a6; }
    .template-status { display:inline-flex; align-items:center; gap:6px; color:var(--muted); font-size:13px; font-weight:750; }
    .template-status::before { content:''; width:8px; height:8px; border-radius:50%; background:#9aa7ad; }
    .template-status.active { color:var(--ok); }
    .template-status.active::before { background:#22a06b; box-shadow:0 0 0 3px #dcfce7; }
    .template-chevron { width:10px; height:10px; border-right:2px solid currentColor; border-bottom:2px solid currentColor; transform:rotate(45deg) translateY(-3px); transition:transform .15s ease; }
    .template-row[open] .template-chevron { transform:rotate(225deg) translate(-2px,-1px); }
    .template-editor { padding:20px; border-top:1px solid var(--line); background:#fbfdfe; }
    .template-description { margin:0 0 17px; max-width:900px; color:var(--muted); }
    .template-workspace { display:grid; grid-template-columns:minmax(390px,.9fr) minmax(400px,1.1fr); gap:22px; align-items:start; }
    .template-fields { display:grid; gap:12px; }
    .template-fields [data-preview-toggle], .template-fields [data-email-preview] { display:none !important; }
    .template-side-actions { display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .template-active { display:flex; align-items:center; gap:8px; font-weight:750; }
    .template-active input { width:auto; min-height:0; }
    .template-tokens { padding:14px; border:1px solid var(--line); border-radius:9px; background:#fff; }
    .template-tokens p { margin:0; font-size:12px; }
    .template-token-list { display:flex; flex-wrap:wrap; gap:5px; margin-top:9px; }
    .template-token { padding:3px 6px; border-radius:5px; background:var(--soft); color:var(--brand-strong); font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:10px; }
    .email-preview-stage { padding:22px; border-radius:11px; background:#e9edef; }
    .email-preview-label { margin:0 0 9px; color:#53646e; font-size:12px; font-weight:850; letter-spacing:.05em; text-transform:uppercase; }
    .email-preview-card { overflow:hidden; max-width:580px; margin:auto; border-radius:11px; background:#fff; box-shadow:0 8px 26px rgba(16,32,44,.12); }
    .email-preview-accent { height:6px; background:#0aa0db; }
    .email-preview-brand { padding:17px 22px; background:#101820; }
    .email-preview-brand img { display:block; width:220px; max-width:100%; height:42px; object-fit:contain; object-position:left center; }
    .email-preview-subject { padding:18px 22px 0; font-size:19px; font-weight:850; line-height:1.25; }
    .email-preview-content { padding:18px 22px 10px; color:#263640; font-size:13px; line-height:1.6; }
    .email-preview-content h1,.email-preview-content h2 { margin:19px 0 8px; font-size:18px; }
    .email-preview-content p { margin:0 0 13px; }
    .email-preview-content blockquote { margin:14px 0; border-left:4px solid #0aa0db; background:#eaf8fd; padding:9px 12px; }
    .email-preview-signature { padding:0 22px 20px; font-size:12px; line-height:1.5; }
    .email-preview-action { display:inline-block; margin-top:12px; border-radius:7px; background:#0aa0db; color:#fff; padding:10px 14px; font-weight:800; }
    .email-preview-footer { border-top:1px solid #dce6eb; padding:15px 22px; color:#667782; font-size:10px; line-height:1.5; }
    @media (max-width:980px) { .template-workspace { grid-template-columns:1fr; } }
    @media (max-width:720px) {
        .template-row > summary { grid-template-columns:44px minmax(0,1fr) 18px; gap:7px 11px; }
        .template-phase,.template-status { grid-column:2; justify-self:start; }
        .template-chevron { grid-column:3; grid-row:1 / span 3; }
        .email-preview-stage { padding:12px; }
    }
</style>
@endpush

@section('content')
<div class="email-template-shell">
    <div class="toolbar" style="margin-bottom:18px"><a class="button secondary" href="{{ route('admin.concert-tracker.index') }}">← Concert tracker</a></div>
    @if(session('status'))<p class="card card-pad" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<p class="card card-pad" role="alert" style="color:#a62232">{{ $errors->first() }}</p>@endif
    <section class="card template-intro"><div><h2>Studio communication library</h2><p>Select a template to edit its wording and see the finished email beside it.</p></div><span class="template-count">{{ $templates->count() }}</span></section>

    <div class="template-list">
    @foreach($templates as $template)
        @php($definition = $definitions->get($template->task_key))
        @php($phase = $definition['phase'] ?? 'booking')
        <details class="card template-row" data-live-email>
            <summary>
                <span class="template-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 6.5h16v11H4z" stroke="currentColor" stroke-width="1.8"/><path d="m5 8 7 5 7-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <span class="template-title"><strong>{{ $template->name }}</strong><small class="muted">{{ $definition['label'] ?? $template->task_key }}</small></span>
                <span class="template-phase {{ $phase }}">{{ \App\Features\Tracking\Support\ConcertTrackerTaskCatalog::PHASES[$phase] ?? ucfirst($phase) }}</span>
                <span class="template-status {{ $template->is_active ? 'active' : '' }}">{{ $template->is_active ? 'Active' : 'Inactive' }}</span>
                <span class="template-chevron" aria-hidden="true"></span>
            </summary>
            <div class="template-editor">
                @if($definition)<p class="template-description">{{ $definition['description'] }}</p>@endif
                <form method="post" action="{{ route('admin.concert-tracker.templates.update', $template) }}" class="template-workspace">@csrf @method('PATCH')
                    <div class="template-fields">
                        <label>Template name<input name="name" value="{{ $template->name }}" required></label>
                        <label>Email subject<input name="subject" value="{{ $template->subject }}" required data-live-subject></label>
                        <x-email-composer :value="$template->body" :rows="10" />
                        <div class="template-tokens"><p><strong>Personalisation fields</strong> · filled from the selected studio and event.</p><div class="template-token-list">@foreach(['studio_name','contact_name','event_name','event_summary','event_details','services','family_count','venue','start_time','finish_time','arrival_time','crew_names','gallery_access','review_url','gallery_url'] as $token)<span class="template-token">&#123;&#123; {{ $token }} &#125;&#125;</span>@endforeach</div></div>
                        <div class="template-side-actions"><label class="template-active"><input type="checkbox" name="is_active" value="1" @checked($template->is_active)> Available for Tracker emails</label><button>Save template</button></div>
                    </div>
                    <div class="email-preview-stage">
                        <p class="email-preview-label">Live email preview</p>
                        <article class="email-preview-card">
                            <div class="email-preview-accent"></div><div class="email-preview-brand"><img src="{{ $emailBranding['logoUrl'] }}" alt="{{ $emailBranding['businessName'] }}"></div>
                            <div class="email-preview-subject" data-preview-subject></div>
                            <div class="email-preview-content" data-preview-body></div>
                            <div class="email-preview-signature">{{ $emailBranding['signature']['signOff'] }}<br><strong>{{ $emailBranding['signature']['name'] }}</strong>@if($emailBranding['signature']['position'])<br>{{ $emailBranding['signature']['position'] }}@endif<div><span class="email-preview-action">{{ in_array($template->task_key, ['final_numbers_email', 'concert_week_email'], true) ? 'Open Concert events' : 'Log into your DancePro account' }}</span></div></div>
                            <div class="email-preview-footer"><strong>{{ $emailBranding['businessName'] }}</strong>@if($emailBranding['phone'])<br>{{ $emailBranding['phone'] }}@endif<br>{{ preg_replace('/^https?:\/\//', '', $emailBranding['website']) }}</div>
                        </article>
                    </div>
                </form>
            </div>
        </details>
    @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
    const samples = {studio_name:'Aurora Dance Academy',contact_name:'Alex',event_name:'Aurora Spring Gala',event_summary:'- **Junior Showcase** · 28 September 2026\n- **Senior Showcase** · 29 September 2026',event_details:'**Junior Showcase**\n\n- **Date:** Saturday 28 September 2026\n- **Venue access/setup:** 4:30 pm\n- **Curtain up:** 6:00 pm\n- **Expected finish:** 8:00 pm\n- **Venue:** Demo Arts Theatre\n- **Services:** Photography and videography\n\n---\n\n**Senior Showcase**\n\n- **Date:** Saturday 28 September 2026\n- **Venue access/setup:** 7:00 pm\n- **Curtain up:** 8:30 pm\n- **Expected finish:** 10:30 pm\n- **Venue:** Demo Arts Theatre\n- **Services:** Photography and videography',services:'Photography and videography',family_count:'86',venue:'Demo Arts Theatre',start_time:'6:00 pm',finish_time:'9:00 pm',arrival_time:'4:30 pm',crew_names:'Morgan and Taylor',gallery_access:'Available in your DancePro account'};
    const escapeHtml = value => String(value).replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character]));
    const fill = value => value.replace(/\{\{\s*([a-z_]+)\s*\}\}/g, (_, key) => samples[key] || `Sample ${key.replaceAll('_',' ')}`).replace(/\{\{#if [^}]+\}\}/g,'').replace(/\{\{\/if\}\}/g,'');
    const render = value => { let html=escapeHtml(fill(value)); html=html.replace(/^#{1,2} (.+)$/gm,'<h2>$1</h2>').replace(/^&gt; (.+)$/gm,'<blockquote>$1</blockquote>').replace(/^[-*] (.+)$/gm,'<li>$1</li>').replace(/(<li>.*<\/li>\n?)+/g,'<ul>$&</ul>').replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>').replace(/\*(.+?)\*/g,'<em>$1</em>').replace(/\n{2,}/g,'</p><p>').replace(/\n/g,'<br>'); return `<p>${html}</p>`; };
    document.querySelectorAll('[data-live-email]').forEach(row => {
        const subject=row.querySelector('[data-live-subject]'), body=row.querySelector('[data-email-source]'), subjectPreview=row.querySelector('[data-preview-subject]'), bodyPreview=row.querySelector('[data-preview-body]');
        const update=()=>{subjectPreview.textContent=fill(subject.value);bodyPreview.innerHTML=render(body.value);};
        subject.addEventListener('input',update);body.addEventListener('input',update);update();
    });
})();
</script>
@endpush
