<div class="publicity-slide-list">
@foreach($slides as $slide)
<details class="card"><summary>{{ $slide->title }}{{ $slide->video_key ? ' · Video' : '' }} · {{ $slide->enabled ? 'Included' : 'Hidden' }}</summary>
<form action="{{ route('admin.publicity.slides.update', $slide) }}" method="post" enctype="multipart/form-data">@csrf @method('PUT') @include('admin.publicity._slide-fields')</form>
</details>
@endforeach
</div>
<details class="card card-pad"><summary style="cursor:pointer;font-weight:800">Add {{ isset($event) ? 'event' : 'standard' }} slide</summary>
<form style="margin-top:18px" method="post" enctype="multipart/form-data" action="{{ isset($event) ? route('admin.publicity.event-slides.store', $event) : route('admin.publicity.slides.store') }}">@csrf @include('admin.publicity._slide-fields', ['slide' => null])</form>
</details>
