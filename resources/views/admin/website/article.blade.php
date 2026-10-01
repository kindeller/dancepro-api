@extends('layouts.admin',['title'=>'Edit help article','heading'=>$article ? 'Edit help article' : 'Add help article','subheading'=>'Save a draft or approve the answer for the website and customer Help.'])
@section('content')@include('admin.website._nav')
@php($draft=$article?->draft ?? $article?->only(['category','title','summary','body','keywords','sort_order']) ?? [])
<a class="button secondary" href="{{ route('admin.website.help.index') }}">← Back to Help &amp; FAQ</a>
<form method="post" class="website-editor card website-panel website-fields" style="margin-top:20px" action="{{ $article ? route('admin.website.help.update',$article) : route('admin.website.help.store') }}">@csrf @if($article) @method('PATCH')
@endif
@foreach(['category'=>'Category','title'=>'Question / title','summary'=>'Short answer','keywords'=>'Search keywords'] as $key=>$label)<label>{{ $label }}<input name="{{ $key }}" value="{{ old($key,$draft[$key] ?? '') }}" @required($key!=='keywords')></label>
@endforeach
<label>Full answer<textarea name="body" rows="12" required>{{ old('body',$draft['body'] ?? '') }}</textarea></label><label>Display order<input type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order',$draft['sort_order'] ?? 0) }}"></label>
@include('admin.website._actions',['isPublished'=>$article?->is_published])
</form>
@endsection
