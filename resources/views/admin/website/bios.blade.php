@extends('layouts.admin',['title'=>'Crew bios','heading'=>'Crew bios','subheading'=>'Review crew submissions before they appear on the About page.'])
@section('content')@include('admin.website._nav')
<p>Only approved biographies appear publicly. A later crew edit stays private until reviewed; the previous approved version remains on the website.</p>
<div class="card website-scroll"><table><thead><tr><th>Crew</th><th>Review</th><th>Website</th><th></th></tr></thead><tbody>@foreach($crewProfiles as $crew)@php($bio=$crew->websiteBiography)<tr><td>{{ $crew->preferred_name }}</td><td>{{ blank($crew->biography) ? 'Awaiting crew biography' : (($bio?->reviewed_source !== $crew->biography) ? 'Needs review' : 'Reviewed') }}@if($crew->biographyDueDate())<div>Due {{ $crew->biographyDueDate()->format('j M Y') }}</div>
@endif</td><td>{{ $bio?->published ? 'Published' : 'Hidden' }}</td><td><a class="button secondary" href="{{ route('admin.website.bios.edit',$crew) }}">Review &amp; edit</a></td></tr>
@endforeach</tbody></table></div>{{ $crewProfiles->links() }}
@endsection
