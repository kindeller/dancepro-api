@extends('layouts.admin',['title'=>'Website Management','heading'=>'Website Management','subheading'=>'Edit pages, choose public events and approve crew biographies.'])
@section('content')@include('admin.website._nav')
<p>Save changes as a draft, preview them, then publish when ready. Events and crew bios are managed in their own tabs.</p>
@foreach($pages as $page)<section class="card website-panel"><h2>{{ $page->draft['eyebrow'] ?? ucfirst($page->slug) }}</h2><p>{{ $page->published ? 'Published' : 'Hidden' }}@if($page->published_at) · {{ $page->published_at->format('j M Y, g:ia') }}
@endif @if($page->published && $page->draft !== $page->published) · Unpublished changes
@endif</p><a class="button" href="{{ route('admin.website.pages.edit',$page) }}">Edit {{ $page->slug }} page</a></section>
@endforeach
@endsection
