package au.com.dancepro.tv;

import org.json.JSONObject;
import java.io.*;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

final class ScreenApi {
    static final class AccessEnded extends IOException {}
    final String base;
    ScreenApi(String base) { this.base = base; }
    JSONObject pair() throws Exception { return new JSONObject(new String(request("/api/publicity/screens/pair", "POST", null), StandardCharsets.UTF_8)).getJSONObject("data"); }
    JSONObject events(String token) throws Exception { return new JSONObject(new String(request("/api/publicity/screens/events", "GET", token), StandardCharsets.UTF_8)).getJSONObject("data"); }
    void selectEvent(String token, String uuid) throws Exception {
        if (!uuid.matches("[a-f0-9-]{36}")) throw new IOException("Invalid event");
        request("/api/publicity/screens/event?event_uuid=" + uuid, "POST", token);
    }
    JSONObject manifest(String token, int count, String revision) throws Exception {
        String query = "?cached_count=" + count + (revision.matches("[a-f0-9]{64}") ? "&cached_revision=" + revision : "");
        return new JSONObject(new String(request("/api/publicity/screens/manifest" + query, "GET", token), StandardCharsets.UTF_8)).getJSONObject("data");
    }
    byte[] image(String token, JSONObject slide) throws Exception {
        String id = slide.getString("id");
        if (!id.matches("(?:(?:photo|slide|auto)-[a-f0-9-]{36}|template-[a-f0-9-]{36}-[1-6])")) throw new IOException("Invalid slide identity");
        return request("/api/publicity/screens/images/" + id, "GET", token);
    }
    void video(String token, JSONObject slide, File destination) throws Exception {
        String id = slide.getString("id");
        if (!id.matches("slide-[a-f0-9-]{36}") || !slide.optString("sha256").matches("[a-f0-9]{64}")) throw new IOException("Invalid video identity");
        HttpURLConnection connection = (HttpURLConnection) new URL(base + "/api/publicity/screens/videos/" + id).openConnection();
        connection.setConnectTimeout(15000); connection.setReadTimeout(30000); connection.setInstanceFollowRedirects(false);
        connection.setRequestProperty("Authorization", "Bearer " + token);
        try {
            int code = connection.getResponseCode();
            if (code == 401 || code == 403) throw new AccessEnded();
            if (code != 200 || !"video/mp4".equals(connection.getContentType())) throw new IOException("Video download failed");
            java.security.MessageDigest digest = java.security.MessageDigest.getInstance("SHA-256"); long count = 0;
            try (InputStream input = connection.getInputStream(); FileOutputStream output = new FileOutputStream(destination)) {
                byte[] buffer = new byte[65536]; int n;
                while ((n = input.read(buffer)) != -1) { count += n; if (count > 100L*1024*1024) throw new IOException("Video too large"); digest.update(buffer,0,n); output.write(buffer,0,n); }
            }
            StringBuilder hash = new StringBuilder(); for (byte b : digest.digest()) hash.append(String.format("%02x", b));
            if (count != slide.optLong("bytes") || !hash.toString().equals(slide.optString("sha256"))) throw new IOException("Incomplete video");
        } finally { connection.disconnect(); }
    }
    private byte[] request(String path, String method, String token) throws Exception {
        HttpURLConnection connection = (HttpURLConnection) new URL(base + path).openConnection();
        connection.setConnectTimeout(15000); connection.setReadTimeout(20000); connection.setInstanceFollowRedirects(false); connection.setRequestMethod(method);
        connection.setRequestProperty("Accept", "application/json");
        if (token != null) connection.setRequestProperty("Authorization", "Bearer " + token);
        try {
            int code = connection.getResponseCode();
            if (code == 401 || code == 403) throw new AccessEnded();
            if (code != 200) throw new IOException("Update failed (" + code + ")");
            try (InputStream input = connection.getInputStream(); ByteArrayOutputStream output = new ByteArrayOutputStream()) {
                byte[] buffer = new byte[8192]; int n;
                while ((n = input.read(buffer)) != -1) { output.write(buffer, 0, n); if (output.size() > 20 * 1024 * 1024) throw new IOException("Response too large"); }
                return output.toByteArray();
            }
        } finally { connection.disconnect(); }
    }
}
