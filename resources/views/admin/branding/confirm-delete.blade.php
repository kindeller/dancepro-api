@extends('layouts.admin', ['title' => 'Clear '.$kind, 'heading' => 'Confirm file removal', 'subheading' => $owner->name])

@section('content')
<div class="card card-pad" style="max-width:780px">
    <h2>Clear {{ $owner->name }} {{ $kind }}?</h2>
    <p>This clears the displayed {{ $kind }} and @if($key)deletes one current object from disk <strong>{{ $disk }}</strong> (bucket <strong>{{ config("filesystems.disks.{$disk}.bucket") }}</strong>): <code style="overflow-wrap:anywhere">{{ $key }}</code>.@else does not delete an S3 object because the current URL is external.@endif</p>
    <p class="muted">If S3 Versioning is enabled, older versions may remain. Existing links to this file will stop working.</p>
    <form method="POST" action="{{ $action }}">@csrf @method('DELETE')
        <input type="hidden" name="digest" value="{{ $digest }}">
        <label>Type DELETE to confirm<input name="confirmation" autocomplete="off" pattern="DELETE" required></label>
        @error('confirmation')<p role="alert">{{ $message }}</p>@enderror
        <div class="actions"><button type="submit">Clear {{ $kind }}</button><a class="button secondary" href="{{ $cancel }}">Cancel</a></div>
    </form>
</div>
@endsection
