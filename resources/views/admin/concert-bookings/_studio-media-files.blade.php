                <h3>Studio videos &amp; slideshows</h3>
                @forelse($event->studioFiles->where('kind', 'studio_video')->where('is_current', true) as $file)
                    <div class="material-file">
                        <a href="{{ route('studio-event-files.download', $file) }}">{{ $file->original_name }}</a>
                        <span class="muted" @if($file->size_bytes !== null) title="{{ number_format($file->size_bytes) }} bytes" @endif>· {{ $file->size_bytes !== null ? Illuminate\Support\Number::fileSize($file->size_bytes, precision: 1) : 'Size unavailable' }}</span>
                        · {{ str($file->status)->replace('_', ' ')->title() }}
                        <details><summary>Replace file</summary>
                            <form method="post" enctype="multipart/form-data" action="{{ route('admin.concert-booking-events.files', $item) }}">
                                @csrf<input type="hidden" name="kind" value="studio_video"><input type="hidden" name="replace_uuid" value="{{ $file->uuid }}">
                                <label>Replacement file<input type="file" name="files[]" required></label><small class="muted">The previous version is retained in file history. The replacement needs review.</small><div><button type="submit">Upload replacement</button></div>
                            </form>
                        </details>
                        <form method="post" action="{{ route('admin.concert-tracker.studio-event-files.review', $file) }}">
                            @csrf @method('PATCH')
                            <label>Review status<select name="status"><option value="approved" @selected($file->status === 'approved')>Approved</option><option value="needs_attention" @selected($file->status !== 'approved')>Needs attention</option></select></label>
                            <label>Review note<textarea name="review_note" rows="2">{{ $file->review_note }}</textarea></label><div><button class="secondary" type="submit">Save file review</button></div>
                        </form>
                    </div>
                @empty<p class="muted">Not received yet.</p>@endforelse
                <form method="post" enctype="multipart/form-data" action="{{ route('admin.concert-booking-events.files', $item) }}">
                    @csrf<input type="hidden" name="kind" value="studio_video">
                    <label>Add studio videos or slideshows<input type="file" name="files[]" multiple required></label><small class="muted">Up to 20 files, 2 GB per file. Files remain awaiting review until you approve them.</small><div><button type="submit">Upload studio files</button></div>
                </form>
