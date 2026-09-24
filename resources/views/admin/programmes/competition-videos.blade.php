@extends('layouts.admin', ['title' => 'Competition videos', 'heading' => 'Competition videos', 'subheading' => $schedulingEvent->name])

@section('content')
<div class="toolbar" style="margin-bottom:20px"><a class="button secondary" href="{{ route('admin.competition.objects.index', ['prefix' => $schedulingEvent->event_date?->format('Y').'/'.$schedulingEvent->eventCode()]) }}">← Competition Media</a><a class="button secondary" href="{{ route('admin.competition-gallery.show', $schedulingEvent) }}">Competition photos</a></div>
@if(session('status'))<p class="card card-pad" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p class="card card-pad" role="alert" style="color:#a62232">{{ $errors->first() }}</p>@endif
<p class="muted">The Mac uploader sends video files privately. A routine becomes available to customers only after every file in its batch is verified, unmatched files are assigned, and the batch is completed. HOLD blocks video access without affecting photos.</p>
<section class="card card-pad"><h2>Routine video status</h2>
    @foreach($items as $item)
        @php($collection = $collections->get($item['uuid']))
        @php($held = in_array($item['uuid'], $holds, true))
        <div style="display:flex;align-items:center;gap:14px;border-top:1px solid #dbe5e9;padding:12px 0">
            <div style="flex:1"><strong>{{ $item['number'] ?? '' }} {{ $item['title'] }}</strong><div class="muted">{{ $held ? 'HOLD' : ($collection?->visibility?->value === 'customer' ? $collection->assets_count.' verified clip(s) · available' : 'Pending video') }}</div></div>
            <form method="post" action="{{ route('admin.competition-videos.hold', $schedulingEvent) }}">@csrf
                <input type="hidden" name="item_uuid" value="{{ $item['uuid'] }}"><input type="hidden" name="is_held" value="{{ $held ? 0 : 1 }}">
                <button class="{{ $held ? 'secondary' : '' }}">{{ $held ? 'Unhold' : 'HOLD' }}</button>
            </form>
        </div>
    @endforeach
</section>
<section class="card card-pad" style="margin-top:20px"><h2>Upload batches</h2>
    @forelse($batches as $batch)
        <h3>{{ $batch->created_at->format('j M Y g:i a') }} · {{ $batch->status }} · {{ $batch->files->count() }} files</h3>
        @foreach($batch->files as $file)
            <div style="border-top:1px solid #dbe5e9;padding:10px 0"><strong>{{ $file->original_filename }}</strong> · {{ $file->status }}
                @if($file->item_uuid)<span class="muted"> · {{ $items->firstWhere('uuid', $file->item_uuid)['title'] ?? 'Routine unavailable' }}</span>
                @else
                    <strong style="color:#a62232"> · Needs assignment</strong>
                    @if($batch->status === 'open')
                        <form method="post" action="{{ route('admin.competition-videos.assign', [$schedulingEvent, $file]) }}" style="display:flex;gap:8px;margin-top:8px">@csrf
                            <select name="item_uuid" required><option value="">Choose routine</option>@foreach($items as $item)<option value="{{ $item['uuid'] }}">{{ $item['number'] ?? '' }} {{ $item['title'] }}</option>@endforeach</select>
                            <button>Assign</button>
                        </form>
                    @endif
                @endif
            </div>
        @endforeach
    @empty<p class="muted">No competition videos uploaded yet.</p>@endforelse
</section>
<section class="card card-pad" style="margin-top:20px"><h2>HOLD history</h2>
    @forelse($history as $change)<p>{{ $change->created_at }} · {{ $items->firstWhere('uuid', $change->item_uuid)['title'] ?? $change->item_uuid }} · {{ $change->is_held ? 'HOLD' : 'Unhold' }}</p>@empty<p class="muted">No HOLD changes.</p>@endforelse
</section>
@endsection
