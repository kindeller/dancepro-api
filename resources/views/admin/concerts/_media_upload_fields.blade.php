@if($showTitle ?? true)
<label class="media-field">Video title<input name="display_name" maxlength="255" required placeholder="Filled from the original filename; you can edit it"></label>
@endif
<div class="media-drop-grid">
    <label class="media-drop" data-file-slot="original"><strong>Original MP4</strong><span>Drop a file here or choose one</span><input name="original" type="file" accept="video/mp4,.mp4" required><small class="muted" data-file-name>No file selected</small><span class="media-progress"><progress value="0" max="100"></progress><small data-file-progress>Waiting for file · 0%</small></span></label>
    <label class="media-drop" data-file-slot="fallback"><strong>Playback MP4</strong><span>Drop a smaller browser-playable MP4 here or choose one</span><input name="fallback" type="file" accept="video/mp4,.mp4" required><small class="muted" data-file-name>No file selected</small><span class="media-progress"><progress value="0" max="100"></progress><small data-file-progress>Waiting for file · 0%</small></span></label>
</div>
<div class="media-actions"><button type="submit">{{ $buttonLabel }}</button><span class="muted">The files upload in parts. Keep this tab open until verification finishes.</span></div>
