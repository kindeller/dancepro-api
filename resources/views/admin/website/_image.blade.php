@if(!empty($draft['image']))<img class="website-preview-image" src="{{ Storage::disk(config('uploads.public_disk'))->url($draft['image']) }}" alt="Current image"><label class="website-check"><input type="checkbox" name="remove_image" value="1">Remove image</label>
@endif
<label>{{ $imageLabel ?? 'Page image' }}<input type="file" name="image" accept="image/jpeg,image/png,image/webp"><span class="website-muted">JPG, PNG or WebP, up to 4 MB. This image is intended for public display.</span></label>
