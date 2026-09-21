<div class="media-secondary media-stack media-bulk" @if($autoCollection) data-default-collection-name="{{ $concert->name }}" @endif>
    <div><h3>Upload several videos</h3><p class="muted">Drop all MP4 files together. Pairing uses the filename: “Ballet.mp4” is the original and “Ballet-stream.mp4” is its playback version. Check the matches and titles before uploading.</p></div>
    <label class="media-drop" data-bulk-drop>
        <strong>Drop original and -stream MP4 files here</strong>
        <span>Or choose multiple files</span>
        <input type="file" accept="video/mp4,.mp4" multiple data-bulk-files>
    </label>
    <div class="media-bulk-list" data-bulk-preview aria-live="polite"></div>
    <div class="media-actions"><button type="button" data-bulk-upload disabled>Upload matched videos</button><span class="muted">Videos stay hidden until you make them visible and publish the show.</span></div>
</div>
