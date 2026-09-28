@extends('layouts.admin', ['title' => $definition['numbered_label'], 'heading' => $event->studio?->name ?? $event->name, 'subheading' => $event->name])
@section('content')
@include('admin.tracking._task-bar', ['bar' => $trackerBar])
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
<section class="card card-pad">
<h2>Concert video uploads</h2>
@if($status['key'] === 'not_required')
<p>{{ $status['detail'] }}</p>
@else
<p><strong>{{ $uploads['verified'] }} of {{ $uploads['queued'] }} videos verified · {{ $uploads['percent'] }}%</strong></p>
<progress value="{{ $uploads['verified'] }}" max="{{ max(1, $uploads['queued']) }}" style="width:100%;height:20px" aria-label="Verified concert videos"></progress>
<p>Progress counts complete, verified files. The laptop uploader registers its entire queue before transferring videos.</p>
@if($uploads['batch']?->completed_at)
<p><strong>Finished Uploading</strong> · {{ $uploads['batch']->completedBy?->name ?? 'Former staff member' }} · {{ $uploads['batch']->completed_at->format('j M Y, g:i a') }}</p>
@else
<p>Waiting for the uploader to confirm <strong>Finished Uploading</strong>.</p>
@endif
@if($uploads['ready'])
<p style="color:var(--ok)"><strong>Ready to send to the studio director.</strong> Sending the approval request and releasing videos remain separate steps.</p>
@else
@include('admin.tracking._action-blockers', ['blockers' => $uploads['blockers'], 'blockerHeading' => 'Concert video is not ready for studio review'])
@endif
<div style="display:flex;gap:12px;flex-wrap:wrap;margin:18px 0">
<a class="button secondary" href="{{ route('admin.concert-tracker.tasks.show', [$event, 'concert_edited']) }}">3.2 Concert editing</a>
<a class="button secondary" href="{{ route('admin.concert-tracker.tasks.show', [$event, 'quality_check']) }}">3.3 Final quality check</a>
@if($event->concertBookingItem?->concert)<a class="button secondary" href="{{ route('admin.concerts.edit', $event->concertBookingItem->concert) }}">Open concert video</a>@endif
</div>
@foreach($uploads['files'] as $file)
<div style="display:flex;justify-content:space-between;gap:16px;padding:12px 0;border-bottom:1px solid var(--line)"><span style="overflow-wrap:anywhere">{{ $file->original_filename }}</span><strong>{{ $file->verified_at ? 'Verified' : 'Waiting for upload' }}</strong></div>
@endforeach
<h3 style="margin-top:28px">Studio videos &amp; slideshows</h3>
<p>Confirm which final export includes each studio file, including opening videos and slideshows edited into the concert. Replaced studio files must be checked again.</p>
@if($uploads['studioFiles']->isEmpty())<p>No current studio videos or slideshows have been supplied.</p>
@elseif($uploads['batch'])
<form method="POST" action="{{ route('admin.concert-tracker.video-inclusions.update', $event) }}">@csrf @method('PATCH')
<input type="hidden" name="batch_uuid" value="{{ $uploads['batch']->uuid }}">
@foreach($uploads['studioFiles'] as $file)
<label style="display:block;margin:16px 0">{{ $file->original_name }} · {{ ucfirst($file->status) }}
<select name="inclusions[{{ $file->uuid }}]" @disabled($file->status !== 'approved')>
<option value="">Not yet confirmed in final exports</option>
@foreach($uploads['files']->whereNotNull('verified_at') as $export)<option value="{{ $export->uuid }}" @selected(($uploads['inclusions'][$file->uuid]['file_uuid'] ?? null) === $export->uuid)>Included in {{ $export->original_filename }}</option>@endforeach
</select></label>
@endforeach
<button>Save studio video inclusions</button>
</form>
@else
<p>Register the final video queue from the laptop uploader before confirming inclusions.</p>
@endif
@endif
</section>
@endsection
