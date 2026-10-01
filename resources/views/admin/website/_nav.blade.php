<style>.website-tabs{display:flex;gap:5px;border-bottom:1px solid var(--border);margin-bottom:24px;overflow-x:auto}.website-tabs a{padding:13px 18px;color:var(--muted);font-weight:800;text-decoration:none;white-space:nowrap}.website-tabs a[aria-current=page]{color:#0786b5;border-bottom:3px solid #00a2d4}.website-editor{max-width:1050px}.website-panel{padding:24px;margin-bottom:20px}.website-fields{display:grid;gap:16px}.website-fields label{display:grid;gap:6px}.website-fields textarea{width:100%;min-height:100px}.website-actions{display:flex;gap:12px;flex-wrap:wrap;margin:20px 0}.website-draft{white-space:pre-wrap;overflow-wrap:anywhere}.website-check{display:flex!important;gap:10px;align-items:center}.website-check input{width:auto}.website-scroll{overflow-x:auto}.website-section{border:1px solid #d5e5eb;border-radius:8px;padding:20px;margin-top:16px}.website-preview-image{max-width:220px;max-height:150px;object-fit:contain}.website-muted{color:#697983}</style>
<nav class="website-tabs" aria-label="Website management">@foreach(['pages'=>'Pages','events'=>'Events','bios'=>'Crew bios','help'=>'Help & FAQ'] as $key=>$label)<a href="{{ route('admin.website.'.$key.'.index') }}" @if(request()->routeIs('admin.website.'.$key.'.*')) aria-current="page"
@endif>{{ $label }}</a>
@endforeach<a href="{{ route('website.home') }}" target="_blank" rel="noopener">View website ↗</a></nav>
@if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>
@endif
@if($errors->any())<div role="alert" class="notice">@foreach($errors->all() as $error)<p>{{ $error }}</p>
@endforeach</div>
@endif
