package au.com.dancepro.tv;

import android.content.Context;
import android.util.AtomicFile;
import org.json.JSONArray;
import org.json.JSONObject;
import java.io.*;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.time.Instant;
import java.time.OffsetDateTime;
import java.util.HashSet;
import java.util.Set;

/** Private app storage; only complete media files and manifests are published. */
final class ScreenStore {
    private final File directory;
    ScreenStore(Context context) { directory = new File(context.getFilesDir(), "slideshow"); directory.mkdirs(); }
    synchronized JSONObject manifest() {
        try {
            JSONObject value = new JSONObject(new String(new AtomicFile(new File(directory, "manifest.json")).readFully(), StandardCharsets.UTF_8));
            if (!"unwatermarked-v1".equals(value.optString("image_variant"))) { clear(); return null; }
            return value;
        }
        catch (Exception e) { return null; }
    }
    synchronized void save(JSONObject value) throws IOException { write(new File(directory, "manifest.json"), value.toString().getBytes(StandardCharsets.UTF_8)); }
    static boolean playable(JSONObject value) {
        try { return value != null && value.getBoolean("enabled") && OffsetDateTime.parse(value.getString("offline_until")).toInstant().isAfter(Instant.now()); }
        catch (Exception e) { return false; }
    }
    File image(JSONObject slide) throws Exception {
        byte[] digest = MessageDigest.getInstance("SHA-256").digest((slide.getString("id") + ":" + slide.optString("asset_version", slide.getString("version"))).getBytes(StandardCharsets.UTF_8));
        StringBuilder name = new StringBuilder(); for (byte b : digest) name.append(String.format("%02x", b));
        return new File(directory, name + ("video".equals(slide.optString("kind")) ? ".mp4" : ".jpg"));
    }
    synchronized void image(JSONObject slide, byte[] bytes) throws Exception {
        long total = 0; File[] files = directory.listFiles(); if (files != null) for (File f : files) total += f.length();
        if (total + bytes.length > 500L * 1024 * 1024) throw new IOException("Screen cache is full. Reduce the number of Top Pics.");
        write(image(slide), bytes);
    }
    File pendingVideo(JSONObject slide) throws Exception { return new File(image(slide).getPath() + ".part"); }
    synchronized void commitVideo(JSONObject slide, File pending) throws Exception {
        long total = 0; File[] files = directory.listFiles(); if (files != null) for (File f : files) total += f.length();
        if (total > 500L * 1024 * 1024) throw new IOException("Screen cache is full. Reduce advertising or Top Pics.");
        android.media.MediaMetadataRetriever metadata = new android.media.MediaMetadataRetriever();
        try {
            metadata.setDataSource(pending.getPath());
            String duration = metadata.extractMetadata(android.media.MediaMetadataRetriever.METADATA_KEY_DURATION);
            if (duration == null || Long.parseLong(duration) <= 0) throw new IOException("Invalid video");
        } finally { metadata.release(); }
        if (!pending.renameTo(image(slide))) throw new IOException("Cannot save video");
    }
    synchronized JSONArray ready(JSONObject value) {
        JSONArray ready = new JSONArray();
        if (!playable(value)) return ready;
        JSONArray all = value.optJSONArray("slides");
        if (all == null) return ready;
        for (int i=0; i<all.length(); i++) {
            JSONObject slide = all.optJSONObject(i);
            try { if (slide != null && ("text".equals(slide.getString("kind")) || (("image".equals(slide.optString("kind")) || "video".equals(slide.optString("kind"))) && image(slide).isFile()))) ready.put(slide); }
            catch (Exception ignored) { }
        }
        return ready;
    }
    synchronized void retain(JSONObject value) throws Exception {
        Set<String> keep = new HashSet<>(); JSONArray slides = value.getJSONArray("slides");
        for (int i=0;i<slides.length();i++) if (!"text".equals(slides.getJSONObject(i).getString("kind"))) keep.add(image(slides.getJSONObject(i)).getName());
        JSONArray standard = value.optJSONArray("standard_slides");
        if (standard != null) for (int i=0;i<standard.length();i++) {
            JSONObject slide = standard.getJSONObject(i);
            if (!"text".equals(slide.getString("kind"))) keep.add(image(slide).getName());
        }
        File[] files = directory.listFiles();
        if (files != null) for (File f : files) if ((f.getName().endsWith(".jpg") || f.getName().endsWith(".mp4") || f.getName().endsWith(".part")) && !keep.contains(f.getName())) f.delete();
    }
    /** Switching events keeps the shared adverts, including through a process restart. */
    synchronized void clearEvent() throws Exception {
        JSONObject saved = manifest();
        if (saved != null) {
            saved.put("slides", new JSONArray());
            saved.put("enabled", false);
            save(saved);
            retain(saved);
        }
    }
    synchronized void clear() { File[] files = directory.listFiles(); if (files != null) for (File f : files) f.delete(); }
    private void write(File file, byte[] bytes) throws IOException {
        AtomicFile atomic = new AtomicFile(file); FileOutputStream stream = null;
        try { stream = atomic.startWrite(); stream.write(bytes); atomic.finishWrite(stream); }
        catch (IOException e) { atomic.failWrite(stream); throw e; }
    }
}
