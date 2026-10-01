@extends('layouts.admin',['title'=>'Help & FAQ','heading'=>'Help & FAQ','subheading'=>'One shared help library for website visitors and signed-in customers.'])
@section('content')@include('admin.website._nav')
<div class="website-actions"><a class="button" href="{{ route('admin.website.help.create') }}">Add help article</a><a class="button secondary" href="{{ route('website.help') }}">View Help</a></div>
<div class="card website-scroll"><table><thead><tr><th>Article</th><th>Category</th><th>Published</th><th></th></tr></thead><tbody>@foreach($articles as $article)<tr><td>{{ $article->title }}</td><td>{{ $article->category }}</td><td>{{ $article->is_published ? 'Yes' : 'Hidden' }}@if($article->draft) · Draft saved
@endif</td><td><a class="button secondary" href="{{ route('admin.website.help.edit',$article) }}">Edit</a></td></tr>
@endforeach</tbody></table></div>{{ $articles->links() }}
@endsection
