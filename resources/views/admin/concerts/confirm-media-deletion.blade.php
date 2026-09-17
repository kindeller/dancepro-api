@extends('layouts.admin', ['title' => 'Delete '.$label, 'heading' => 'Confirm media deletion', 'subheading' => $concert->name])

@section('content')
<div class="card card-pad" style="max-width:850px">
    <h2>Delete {{ $label }}?</h2>
    <p>This hides the media, removes {{ count($keys) }} current {{ \Illuminate\Support\Str::plural('object', count($keys)) }} from storage disk <strong>{{ $disk }}</strong>, and soft-deletes the database records. If storage deletion fails partway through, the media remains hidden for a safe retry. S3 Versioning or retention, if enabled, may retain earlier versions. Customer links to this media will stop working.</p>
    <p>Review the exact object keys:</p>
    @if($keys)
        <ul style="max-height:300px;overflow:auto;overflow-wrap:anywhere">@foreach($keys as $key)<li><code>{{ $key }}</code></li>@endforeach</ul>
    @else
        <p class="muted">No current storage objects were found under this media item.</p>
    @endif
    <form method="POST" action="{{ $action }}" style="display:grid;gap:14px;margin-top:20px">@csrf @method('DELETE')
        <input type="hidden" name="digest" value="{{ $digest }}">
        <label>Type DELETE to confirm<input name="confirmation" autocomplete="off" required pattern="DELETE"></label>
        @error('confirmation')<p role="alert">{{ $message }}</p>@enderror
        <div style="display:flex;gap:12px"><button type="submit">Delete from database and storage</button><a class="button secondary" href="{{ route('admin.concerts.edit', $concert) }}">Cancel</a></div>
    </form>
</div>
@endsection
