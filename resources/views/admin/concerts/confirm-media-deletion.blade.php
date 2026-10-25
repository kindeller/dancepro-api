@extends('layouts.admin', ['title' => 'Delete '.$label, 'heading' => 'Confirm media deletion', 'subheading' => $concert->name])

@section('content')
@php($keys = $preview['keys'])
<div class="card card-pad" style="max-width:850px">
    <h2>Delete {{ $label }}?</h2>
    <p>This hides the media, removes {{ count($keys) }} current {{ \Illuminate\Support\Str::plural('object', count($keys)) }} from storage disk <strong>{{ $disk }}</strong>, and soft-deletes the database records. If storage deletion fails partway through, the media remains hidden for a safe retry. S3 Versioning or retention, if enabled, may retain earlier versions. Customer links to this media will stop working.</p>
    @if($preview['bucket'])<p>Bucket: <code>{{ $preview['bucket'] }}</code></p>@endif
    <p>{{ count($preview['assets']) }} video {{ \Illuminate\Support\Str::plural('record', count($preview['assets'])) }} will be removed.</p>
    @if($preview['requires_force'])
        <div class="notice" role="alert"><strong>This media has unfinished uploads or processing videos.</strong><br>You can force delete without completing upload verification. Stop uploading in Flutter and close any browser upload tabs before continuing. Existing signed upload links can remain usable until they expire; active transfers may leave new files behind.</div>
    @endif
    <p>Review the exact object keys:</p>
    @if($keys)
        <ul style="max-height:300px;overflow:auto;overflow-wrap:anywhere">@foreach($keys as $key)<li><code>{{ $preview['root'] ? $preview['root'].'/'.$key : $key }}</code></li>@endforeach</ul>
    @else
        <p class="muted">No current storage objects were found under this media item.</p>
    @endif
    @if($preview['uploads'])
        <p>Cancel {{ count($preview['uploads']) }} unfinished {{ \Illuminate\Support\Str::plural('upload', count($preview['uploads'])) }}:</p>
        <ul style="overflow-wrap:anywhere">
            @foreach($preview['uploads'] as $upload)
                <li><code>{{ $upload['uuid'] }}</code> · {{ $upload['kind'] }}@if($upload['provider_upload_id']) · key: <code>{{ $preview['root'] ? $preview['root'].'/'.$upload['storage_key'] : $upload['storage_key'] }}</code> · upload ID: <code>{{ $upload['provider_upload_id'] }}</code>@endif</li>
            @endforeach
        </ul>
    @endif
    <form method="POST" action="{{ $action }}" style="display:grid;gap:14px;margin-top:20px">@csrf @method('DELETE')
        <input type="hidden" name="digest" value="{{ $digest }}">
        @if($preview['requires_force'])
            <label style="display:flex;align-items:center;gap:10px"><input type="checkbox" name="force" value="1" required style="width:auto;min-height:auto"> I have stopped the uploader and want to force delete this media and cancel its unfinished uploads.</label>
            @error('force')<p role="alert">{{ $message }}</p>@enderror
        @endif
        <label>Type DELETE to confirm<input name="confirmation" autocomplete="off" required pattern="DELETE"></label>
        @error('confirmation')<p role="alert">{{ $message }}</p>@enderror
        <div style="display:flex;gap:12px"><button type="submit">{{ $preview['requires_force'] ? 'Force delete and clean up' : 'Delete from database and storage' }}</button><a class="button secondary" href="{{ route('admin.concerts.edit', $concert) }}">Cancel</a></div>
    </form>
</div>
@endsection
