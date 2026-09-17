@extends('layouts.admin', ['title' => 'Edit '.$concert->name, 'heading' => 'Edit Concert', 'subheading' => $concert->studio->name.' · '.$concert->name])
@section('content')
<form class="card card-pad" method="POST" action="{{ route('admin.concerts.update', $concert) }}">@csrf @method('PUT')
    @include('admin.concerts._form', ['submitLabel' => 'Save concert'])
</form>
<div class="grid two-col" style="margin-top:24px">
    <section class="card card-pad">
        <h2>Concert cover image</h2>
        @if($concert->cover_image_url)<p><img src="{{ $concert->cover_image_url }}" alt="Current concert cover" style="max-width:320px;width:100%;border-radius:8px"></p>@endif
        <form method="POST" action="{{ route('admin.branding.concerts.cover.store', $concert) }}" enctype="multipart/form-data">@csrf
            <label>Image (JPG, PNG or WebP; up to 10 MB)<input type="file" name="file" accept="image/jpeg,image/png,image/webp" required></label>
            @if($concert->cover_image_storage_key)<label style="display:flex;align-items:flex-start;gap:8px"><input type="checkbox" name="replace_confirm" value="1" required style="width:auto;min-height:auto"><span><strong>Confirm replacement</strong><br>Replace the current object in {{ config('filesystems.disks.'.config('media.upload_disk').'.bucket') }} at <code>{{ $concert->cover_image_storage_key }}</code>. Earlier versions may remain if S3 Versioning is enabled.</span></label>@endif
            @error('replace_confirm')<p role="alert">{{ $message }}</p>@enderror
            @error('file')<p role="alert">{{ $message }}</p>@enderror
            <div class="actions"><button type="submit">{{ $concert->cover_image_url ? 'Replace cover' : 'Upload cover' }}</button>@if($concert->cover_image_url)<a class="button secondary" href="{{ route('admin.branding.concerts.cover.confirm-delete', $concert) }}">Clear cover</a>@endif</div>
        </form>
    </section>
    <section class="card card-pad">
        <h2>Concert program</h2>
        @if($concert->program_url)<p><a href="{{ $concert->program_url }}" target="_blank" rel="noopener">View current program</a></p>@endif
        <form method="POST" action="{{ route('admin.branding.concerts.program.store', $concert) }}" enctype="multipart/form-data">@csrf
            <label>PDF program (up to 10 MB)<input type="file" name="file" accept="application/pdf,.pdf" required></label>
            @if($concert->program_storage_key)<label style="display:flex;align-items:flex-start;gap:8px"><input type="checkbox" name="replace_confirm" value="1" required style="width:auto;min-height:auto"><span><strong>Confirm replacement</strong><br>Replace the current object in {{ config('filesystems.disks.'.config('media.upload_disk').'.bucket') }} at <code>{{ $concert->program_storage_key }}</code>. Earlier versions may remain if S3 Versioning is enabled.</span></label>@endif
            @error('replace_confirm')<p role="alert">{{ $message }}</p>@enderror
            @error('file')<p role="alert">{{ $message }}</p>@enderror
            <div class="actions"><button type="submit">{{ $concert->program_url ? 'Replace program' : 'Upload program' }}</button>@if($concert->program_url)<a class="button secondary" href="{{ route('admin.branding.concerts.program.confirm-delete', $concert) }}">Clear program</a>@endif</div>
        </form>
    </section>
</div>
@include('admin.concerts._media_management')
@endsection
