@extends('layouts.admin', ['title' => $definition['numbered_label'], 'heading' => $event->studio?->name ?? $event->name, 'subheading' => $event->name])
@section('content')
@include('admin.tracking._task-bar', ['bar' => $trackerBar])
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
<section class="card card-pad" style="max-width:1000px"><h2>Director corrections</h2>
@forelse($review['review']->feedback ?? [] as $feedback)<article style="border-bottom:1px solid var(--line);padding:12px 0"><strong>{{ $feedback['name'] }}</strong> · {{ \Carbon\Carbon::parse($feedback['created_at'])->format('j M Y, g:i a') }}<p style="white-space:pre-wrap">{{ $feedback['body'] }}</p></article>
@empty<p>No corrections have been submitted by the studio director.</p>@endforelse
@if($review['review']->revision && !$review['released'])
@include('admin.tracking._action-blockers', ['blockers' => $review['uploads']['blockers'], 'blockerHeading' => 'Corrections cannot be confirmed as uploaded yet'])
<form method="post" action="{{ route('admin.concert-tracker.corrections.update', $event) }}" style="margin-top:20px" id="correction-checks">@csrf @method('PATCH')
<input type="hidden" name="revision" value="{{ $review['review']->revision }}"><input type="hidden" name="batch_uuid" value="{{ $review['uploads']['batch']?->uuid }}">
<label class="choice" style="margin:14px 0"><input type="checkbox" name="corrections_complete" value="1" @checked($review['review']->corrections_completed_at)> Corrections complete</label>
<label class="choice" style="margin:14px 0"><input type="checkbox" name="corrections_uploaded" value="1" @checked($review['checksReady']) @disabled(!$review['uploads']['ready'])> Corrections uploaded</label>
<button>Save corrections progress</button><p>When both are confirmed, the email page opens. Sending the email completes this task.</p></form>
@endif
@if($review['currentMail'])<p>Corrections email sent {{ $review['currentMail']->sent_at?->format('j M Y, g:i a') }} by {{ $review['currentMail']->createdBy?->name ?? 'Automation' }}.</p>@endif
<a class="button secondary" href="{{ route('admin.concert-tracker.tasks.show', [$event, 'video_uploaded']) }}">View video uploads</a>
</section>
@endsection
@push('scripts')<script>document.getElementById('correction-checks')?.addEventListener('change', function () { if (this.querySelector('[name=corrections_complete]').checked && this.querySelector('[name=corrections_uploaded]').checked) this.requestSubmit(); });</script>@endpush
