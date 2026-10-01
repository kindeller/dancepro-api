@extends('layouts.admin',['title'=>'Edit '.ucfirst($page->slug),'heading'=>ucfirst($page->slug).' page','subheading'=>'Published content stays unchanged until you approve a new version.'])
@section('content')@include('admin.website._nav')
@php($draft=$page->draft)
<div class="website-actions"><a class="button secondary" href="{{ route('admin.website.pages.index') }}">← Back to pages</a><a class="button secondary" href="{{ route('admin.website.pages.preview',$page) }}" target="_blank" rel="noopener">Preview saved draft ↗</a></div>
<form class="website-editor" method="post" enctype="multipart/form-data" action="{{ route('admin.website.pages.update',$page) }}">@csrf @method('PATCH')
<section class="card website-panel website-fields">
@foreach(['eyebrow'=>'Blue heading text','heading'=>'Page heading','description'=>'Description'] as $key=>$label)<label>{{ $label }}<input name="{{ $key }}" value="{{ old($key,$draft[$key] ?? '') }}" required></label>
@endforeach
<label>Introduction<textarea name="body" rows="5">{{ old('body',$draft['body'] ?? '') }}</textarea></label>@include('admin.website._image', ['imageLabel' => 'Heading photograph'])<p class="website-muted">An uploaded image replaces the curated heading photograph. Removing it restores the default photograph. Use a wide photo with room for the heading on the left.</p>
</section>
<section class="card website-panel"><h2>Page sections</h2><p>Edit or remove cards below. Use Show section to temporarily hide a card. Use Move up and Move down to change their order.</p><div data-website-sections>
@foreach(old('sections',$draft['sections'] ?? []) as $i=>$section)@include('admin.website._section',['i'=>$i,'section'=>$section])
@endforeach
</div><button type="button" class="button secondary" data-add-section>Add section</button></section>
@include('admin.website._actions',['isPublished'=>$page->published !== null])
</form>
<template id="website-section-template">@include('admin.website._section',['i'=>'__INDEX__','section'=>[]])</template>
<script>
const sections = document.querySelector('[data-website-sections]');
const indexes = [...sections.querySelectorAll('input[name]')].map(input => Number(input.name.match(/^sections\[(\d+)\]/)?.[1] ?? -1));
let nextSection = Math.max(-1, ...indexes) + 1;
document.querySelector('[data-add-section]').addEventListener('click', () => {
    sections.insertAdjacentHTML('beforeend', document.getElementById('website-section-template').innerHTML.replaceAll('__INDEX__', nextSection++));
});
sections.addEventListener('click', event => {
    const button = event.target.closest('button');
    if (!button) return;
    const section = button.closest('.website-section');
    if (button.matches('[data-remove-section]')) section.remove();
    if (button.matches('[data-move-up]') && section.previousElementSibling) sections.insertBefore(section, section.previousElementSibling);
    if (button.matches('[data-move-down]') && section.nextElementSibling) sections.insertBefore(section.nextElementSibling, section);
});
</script>
@endsection
