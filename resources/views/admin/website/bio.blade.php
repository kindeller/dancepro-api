@extends('layouts.admin',['title'=>'Review biography','heading'=>$crew->preferred_name.' · Biography','subheading'=>'Approve only the text and image you want visitors to see.'])
@section('content')@include('admin.website._nav')
<a class="button secondary" href="{{ route('admin.website.bios.index') }}">← Back to crew bios</a>
<section class="card website-panel" style="margin-top:20px"><h2>Latest crew submission</h2><p class="website-draft" id="crew-submission">{{ $crew->biography ?: 'No biography submitted yet.' }}</p></section>
<form class="website-editor card website-panel website-fields" method="post" enctype="multipart/form-data" action="{{ route('admin.website.bios.update',$crew) }}">@csrf @method('PATCH')
<input type="hidden" name="source_hash" value="{{ $sourceHash }}">
<label>Public name<input name="name" value="{{ old('name',$draft['name']) }}" required></label><label>Public role<input name="role" value="{{ old('role',$draft['role'] ?? '') }}"></label>
<label>Biography for the website<textarea id="public-biography" name="body" rows="10" required>{{ old('body',$draft['body']) }}</textarea></label>
@if(filled($crew->biography))<button type="button" class="button secondary" onclick="document.getElementById('public-biography').value=document.getElementById('crew-submission').textContent">Use latest crew submission</button>
@endif
@include('admin.website._image',['imageLabel'=>'Public crew photo'])
<label>Display order<input type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order',$bio?->sort_order ?? 0) }}" required></label>
@include('admin.website._actions',['isPublished'=>$bio?->published !== null])
</form>
@endsection
