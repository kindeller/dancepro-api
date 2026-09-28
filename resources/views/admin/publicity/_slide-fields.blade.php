@if(!isset($event))<input type="hidden" name="audience" value="{{ $slide?->audience ?? $advertisingType }}">@endif
<div class="publicity-fields">
    <label class="wide">Slide title<input name="title" maxlength="160" required value="{{ $slide?->title }}"></label>
    <label class="wide">Message<textarea name="body" maxlength="1200" rows="3">{{ $slide?->body }}</textarea></label>
    <label class="wide">{{ $slide?->image_key ? 'Replace image (optional)' : 'Image (optional)' }}<input type="file" name="image" accept="image/jpeg,image/png,image/webp"><span class="publicity-note">JPEG, PNG or WebP, up to 10 MB. Without an image, the title and message become a full-screen information slide. Image titles are labels only; an optional message appears over the image. Upload either an image or a video.</span></label>
    <label class="wide">{{ $slide?->video_key ? 'Replace video (optional)' : 'Video advert (optional)' }}<input type="file" name="video" accept="video/mp4"><span class="publicity-note">MP4 · H.264 video, optional stereo AAC audio · up to 1080p, 60 fps, 2 minutes and 100 MB. Downloads before playback and plays to the end. Video uses its own content; title and message are not overlaid. Replaces any image on this slide.</span>@if($slide?->video_key)<span class="publicity-note">Current video: {{ $slide->video_seconds }} seconds · {{ number_format($slide->video_bytes / 1048576, 1) }} MB</span>@endif</label>
    <label class="wide">Video sound<select name="play_sound"><option value="0" @selected(!$slide?->play_sound)>Silent</option><option value="1" @selected($slide?->play_sound)>Play audio</option></select></label>
    <label>Seconds on screen (images / text only)<input type="number" name="duration_seconds" min="3" max="120" required value="{{ $slide?->duration_seconds ?? (isset($event) ? 8 : 5) }}"></label>
    <label>Display order<input type="number" name="sort_order" min="0" max="9999" required value="{{ $slide?->sort_order ?? 0 }}"></label>
    <label class="wide">Status<select name="enabled"><option value="1" @selected($slide?->enabled ?? true)>Included</option><option value="0" @selected($slide && !$slide->enabled)>Hidden</option></select></label>
</div>
<div class="publicity-actions"><button>{{ $slide ? 'Save slide' : 'Add slide' }}</button></div>
