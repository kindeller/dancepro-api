package au.com.dancepro.tv;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.SharedPreferences;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Color;
import android.graphics.drawable.GradientDrawable;
import android.graphics.drawable.StateListDrawable;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.*;
import android.widget.*;
import org.json.JSONArray;
import org.json.JSONObject;
import java.net.URI;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

/** A native, remote-operated player. Network work never blocks slide playback. */
public final class MainActivity extends Activity {
    private final Handler handler = new Handler(Looper.getMainLooper());
    private final ExecutorService network = Executors.newSingleThreadExecutor();
    private final ExecutorService images = Executors.newSingleThreadExecutor();
    private AdvertVideoView currentVideo;
    private ScreenStore store; private SharedPreferences preferences;
    private FrameLayout root; private LinearLayout panel; private TextView status, message;
    private FrameLayout branding; private ImageView eventBrand, danceproBrand; private Button firstControl;
    private LinearLayout home; private Spinner eventPicker; private TextView homeStatus;
    private JSONArray events = new JSONArray(); private volatile boolean homeVisible = true;
    private volatile JSONObject manifest; private JSONArray ready = new JSONArray();
    private volatile boolean busy = false, destroyed = false; private boolean paused = false, foreground = false, controlsRequested = false;
    private int index = -1, renderRevision = 0; private View current; private String currentId = "";
    private final Runnable advance = () -> showNext(1);
    private final Runnable poll = () -> sync();
    private final Runnable hide = () -> panel.setVisibility(View.GONE);
    private final Runnable leaseCheck = new Runnable() { public void run() {
        if (manifest != null && !ScreenStore.playable(manifest)) stop("Reconnect to refresh this screen’s offline access.");
        if (!destroyed) handler.postDelayed(this, 10000);
    }};

    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
        getWindow().getDecorView().setSystemUiVisibility(View.SYSTEM_UI_FLAG_FULLSCREEN | View.SYSTEM_UI_FLAG_HIDE_NAVIGATION | View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY);
        preferences = getSharedPreferences("screen", MODE_PRIVATE); store = new ScreenStore(this);
        root = new FrameLayout(this); root.setBackgroundColor(Color.rgb(4,27,62)); setContentView(root);
        message = text("DancePro TV\nPair this screen in Publicity → TV Slideshows", 32); message.setGravity(Gravity.CENTER); root.addView(message, new FrameLayout.LayoutParams(-1,-1));
        branding = new FrameLayout(this);
        FrameLayout.LayoutParams brandingPosition = new FrameLayout.LayoutParams(-1,dp(64),Gravity.BOTTOM); brandingPosition.setMargins(dp(22),0,dp(22),dp(18)); root.addView(branding,brandingPosition);
        eventBrand = new ImageView(this); eventBrand.setScaleType(ImageView.ScaleType.FIT_CENTER); eventBrand.setContentDescription("Event logo"); branding.addView(eventBrand,new FrameLayout.LayoutParams(dp(130),dp(60),Gravity.START | Gravity.BOTTOM));
        danceproBrand = new ImageView(this); danceproBrand.setScaleType(ImageView.ScaleType.FIT_CENTER); danceproBrand.setContentDescription("DancePro Photography and Video"); branding.addView(danceproBrand,new FrameLayout.LayoutParams(dp(200),dp(48),Gravity.END | Gravity.BOTTOM));
        panel = new LinearLayout(this); panel.setOrientation(LinearLayout.VERTICAL); panel.setPadding(24,14,24,14); panel.setBackgroundColor(Color.rgb(14,40,56));
        FrameLayout.LayoutParams params = new FrameLayout.LayoutParams(-1,-2,Gravity.BOTTOM); root.addView(panel, params);
        status = text("Not paired",18); panel.addView(status);
        LinearLayout buttons = new LinearLayout(this); panel.addView(buttons);
        firstControl = button(buttons, "Back to events", this::showHome);
        button(buttons, "Previous", () -> showNext(-1));
        button(buttons, "Play / pause", this::togglePause);
        button(buttons, "Next", () -> showNext(1));
        button(buttons, "Update", this::sync);
        button(buttons, "Hide", this::hideControls);
        panel.addView(text("Back / Esc / B: events    Click / Enter / M: controls    Arrows: select    Space: pause",12));
        buildHome();
        manifest = store.manifest();
        try { events = new JSONArray(preferences.getString("events", "[]")); } catch (Exception ignored) {}
        populateEvents(); showHome(); handler.post(leaseCheck);
    }
    @Override public void onResume() { super.onResume(); foreground = true; showHome(); refreshReady(); sync(); }
    @Override public void onPause() { super.onPause(); releaseVideo(); foreground = false; handler.removeCallbacks(advance); handler.removeCallbacks(poll); handler.removeCallbacks(hide); }
    @Override public void onDestroy() { destroyed = true; releaseVideo(); handler.removeCallbacksAndMessages(null); network.shutdownNow(); images.shutdownNow(); super.onDestroy(); }
    private TextView text(String value, int size) { TextView t = new TextView(this); t.setText(value); t.setTextColor(Color.WHITE); t.setTextSize(size); return t; }
    private Button button(LinearLayout row, String label, Runnable action) { Button b = new Button(this); b.setText(label); b.setTextColor(Color.WHITE); b.setAllCaps(false); b.setTextSize(16); b.setBackground(buttonBackground()); b.setOnClickListener(v -> action.run()); b.setOnFocusChangeListener((v,focused) -> { if (focused) handler.removeCallbacks(hide); }); LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(0,dp(48),1); p.setMargins(dp(4),dp(4),dp(4),dp(4)); row.addView(b,p); return b; }
    private int dp(int value) { return (int)(value * getResources().getDisplayMetrics().density); }
    private GradientDrawable buttonShape(int color, int stroke) { GradientDrawable d = new GradientDrawable(); d.setColor(color); d.setCornerRadius(dp(8)); d.setStroke(dp(2),stroke); return d; }
    private StateListDrawable buttonBackground() { StateListDrawable d = new StateListDrawable(); d.addState(new int[]{android.R.attr.state_focused},buttonShape(Color.rgb(0,152,207),Color.WHITE)); d.addState(new int[]{android.R.attr.state_pressed},buttonShape(Color.rgb(0,152,207),Color.WHITE)); d.addState(new int[]{},buttonShape(Color.rgb(17,55,85),Color.rgb(50,95,122))); return d; }
    private void updateStatus(String value) { handler.post(() -> { if (!destroyed) { status.setText(value); homeStatus.setText(value); } }); }

    private void buildHome() {
        home = new LinearLayout(this); home.setGravity(Gravity.CENTER); home.setPadding(dp(48),dp(24),dp(48),dp(24)); home.setBackgroundColor(Color.rgb(4,27,62));
        root.addView(home,new FrameLayout.LayoutParams(-1,-1));
        ImageView brand = new ImageView(this); brand.setImageResource(au.com.dancepro.tv.R.drawable.tv_brand); brand.setContentDescription("DancePro TV"); brand.setScaleType(ImageView.ScaleType.FIT_CENTER);
        LinearLayout.LayoutParams logo = new LinearLayout.LayoutParams(dp(220),dp(220)); logo.setMargins(0,0,dp(40),0); home.addView(brand,logo);
        LinearLayout content = new LinearLayout(this); content.setOrientation(LinearLayout.VERTICAL); home.addView(content,new LinearLayout.LayoutParams(0,-2,1));
        content.addView(text("DancePro TV",36));
        TextView intro = text("Choose an event to start its slideshow",19); intro.setPadding(0,dp(8),0,dp(22)); content.addView(intro);
        TextView label = text("Event · newest first",16); content.addView(label);
        eventPicker = new Spinner(this,Spinner.MODE_DROPDOWN); eventPicker.setContentDescription("Choose event"); eventPicker.setBackground(buttonBackground()); eventPicker.setPopupBackgroundDrawable(buttonShape(Color.rgb(17,55,85),Color.rgb(50,95,122))); eventPicker.setPadding(dp(10),0,dp(10),0); content.addView(eventPicker,new LinearLayout.LayoutParams(-1,dp(64)));
        LinearLayout actions = new LinearLayout(this); actions.setPadding(0,dp(12),0,0); content.addView(actions); button(actions,"Play slideshow",this::playSelected);
        LinearLayout settings = new LinearLayout(this); content.addView(settings); button(settings,"Refresh events",this::sync); button(settings,"Pair screen",this::configure);
        homeStatus = text("Pair this screen once to load your events.",15); homeStatus.setPadding(0,dp(14),0,0); content.addView(homeStatus);
    }
    private void populateEvents() { populateEvents(""); }
    private void populateEvents(String selected) {
        java.util.ArrayList<String> labels = new java.util.ArrayList<>();
        for (int i=0;i<events.length();i++) { JSONObject e = events.optJSONObject(i); String date = e.optString("date",""); if (!date.isEmpty() && !date.equals("null")) try { date = java.time.LocalDate.parse(date).format(java.time.format.DateTimeFormatter.ofPattern("d MMM yyyy")); } catch (Exception ignored) {} labels.add(date + " · " + e.optString("name")); }
        if (labels.isEmpty()) labels.add("No events enabled for TV yet");
        ArrayAdapter<String> adapter = new ArrayAdapter<String>(this,android.R.layout.simple_spinner_item,labels) {
            @Override public View getView(int position,View convertView,android.view.ViewGroup parent) { TextView v=(TextView)super.getView(position,convertView,parent); v.setTextColor(Color.WHITE); v.setTextSize(18); v.setText(getString(R.string.event_dropdown_selection,v.getText())); v.setSingleLine(false); return v; }
            @Override public View getDropDownView(int position,View convertView,android.view.ViewGroup parent) { TextView v=(TextView)super.getDropDownView(position,convertView,parent); v.setTextColor(Color.WHITE); v.setTextSize(18); v.setPadding(dp(16),dp(12),dp(16),dp(12)); v.setSingleLine(false); return v; }
        };
        adapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item); eventPicker.setAdapter(adapter); eventPicker.setEnabled(events.length()>0);
        for (int i=0;i<events.length();i++) if (events.optJSONObject(i).optString("uuid").equals(selected)) eventPicker.setSelection(i);
    }
    private String selectedEvent() { JSONObject e = eventPicker == null ? null : events.optJSONObject(eventPicker.getSelectedItemPosition()); return e == null ? "" : e.optString("uuid"); }
    private void showHome() { releaseVideo(); homeVisible = true; handler.removeCallbacks(advance); handler.removeCallbacks(hide); renderRevision++; home.setVisibility(View.VISIBLE); panel.setVisibility(View.GONE); branding.setVisibility(View.GONE); eventPicker.requestFocus(); }
    private void startPlayback() { controlsRequested = false; homeVisible = false; applyBranding(); paused = false; home.setVisibility(View.GONE); panel.setVisibility(View.GONE); refreshReady(); if (current != null) showNext(1); }
    private void playSelected() {
        String uuid = selectedEvent();
        if (preferences.getString("token", "").isEmpty()) { updateStatus("Choose Pair screen to connect this TV."); return; }
        if (uuid.isEmpty()) { updateStatus("Enable an event in Publicity → TV Slideshows, then refresh."); return; }
        if (manifest != null && uuid.equals(manifest.optJSONObject("event").optString("uuid")) && ScreenStore.playable(manifest)) { startPlayback(); sync(); return; }
        if (busy) { updateStatus("Finishing the current update. Please try again in a moment."); return; }
        busy = true; updateStatus("Loading the selected event…");
        network.execute(() -> {
            try {
                new ScreenApi(preferences.getString("server", "")).selectEvent(preferences.getString("token", ""),uuid);
                store.clearEvent(); manifest = null;
                handler.post(() -> { stop("Preparing your slideshow…"); startPlayback(); });
            } catch (ScreenApi.AccessEnded e) { handler.post(() -> { unpair(); updateStatus("Access ended. Pair this screen again."); }); }
            catch (Exception e) { updateStatus("Cannot load this event. Reconnect, or play the last downloaded event."); }
            finally { busy = false; handler.post(this::sync); }
        });
    }
    private void configure() {
        if (busy) { updateStatus("Wait for the current update to finish."); return; }
        EditText url = new EditText(this); url.setSingleLine(true); url.setHint("https://your-dancepro-server"); url.setText(preferences.getString("server", ""));
        new AlertDialog.Builder(this).setTitle("DancePro server address").setView(url).setPositiveButton("Pair", (d,w) -> {
            String server = url.getText().toString().trim().replaceAll("/+$", "");
            try { URI uri = new URI(server); if (uri.getHost() == null || uri.getUserInfo() != null || uri.getQuery() != null || uri.getFragment() != null || (uri.getPath() != null && !uri.getPath().isEmpty()) || !("https".equals(uri.getScheme()) || (BuildConfig.DEBUG && "http".equals(uri.getScheme())))) throw new Exception(); }
            catch (Exception e) { updateStatus("Enter a valid HTTPS server address. Local HTTP is available only in debug builds."); return; }
            busy = true;
            network.execute(() -> { try {
                JSONObject pairing = new ScreenApi(server).pair();
                store.clear(); preferences.edit().putString("server", server).putString("token", pairing.getString("token")).putString("code", pairing.getString("code")).apply();
                handler.post(() -> { manifest = null; updateStatus("Pairing code: " + pairing.optString("code") + "\nEnter this in Publicity → TV Slideshows. Valid for 10 minutes."); });
            } catch (Exception e) { updateStatus("Unable to pair. Check the server address and network."); }
            finally { busy = false; handler.post(this::sync); } });
        }).setNegativeButton("Cancel",null).show();
    }
    private void unpair() {
        if (busy) { updateStatus("Wait for the current update to finish, then unpair."); return; }
        preferences.edit().remove("token").remove("code").remove("events").apply(); store.clear(); manifest = null; events = new JSONArray(); populateEvents(); stop("Screen cleared. Choose Pair screen to reconnect."); showHome(); updateStatus("Not paired");
    }
    private void sync() {
        handler.removeCallbacks(poll);
        String token = preferences.getString("token", ""), server = preferences.getString("server", "");
        if (busy || token.isEmpty() || server.isEmpty() || destroyed || !foreground) return;
        busy = true; int count = ready.length();
        String revision = manifest != null && count == manifest.optJSONArray("slides").length() ? manifest.optString("revision") : "";
        network.execute(() -> {
            try {
                ScreenApi api = new ScreenApi(server); JSONObject next = api.manifest(token, count, revision);
                if (!next.optBoolean("paired")) { updateStatus("Waiting for pairing · " + preferences.getString("code", "")); return; }
                if (homeVisible) {
                    JSONArray catalogue = api.events(token).getJSONArray("events");
                    preferences.edit().putString("events",catalogue.toString()).apply();
                    handler.post(() -> { String previous = selectedEvent(); events = catalogue; populateEvents(previous); });
                    updateStatus(catalogue.length() == 0 ? "Enable events in Publicity → TV Slideshows to list them here." : "Choose an event, then select Play slideshow.");
                    return;
                }
                next.getJSONArray("slides"); next.getString("offline_until");
                store.save(next); store.retain(next); manifest = next; handler.post(this::refreshReady);
                JSONArray slides = next.getJSONArray("slides"); int failures = 0;
                for (int i=0;i<slides.length() && !destroyed && !homeVisible;i++) {
                    JSONObject slide = slides.getJSONObject(i);
                    if ("text".equals(slide.getString("kind")) || store.image(slide).isFile()) continue;
                    try {
                        if ("video".equals(slide.optString("kind"))) {
                            java.io.File pending = store.pendingVideo(slide);
                            try { api.video(token,slide,pending); store.commitVideo(slide,pending); }
                            finally { pending.delete(); }
                            handler.post(this::refreshReady);
                            continue;
                        }
                        byte[] bytes = api.image(token, slide);
                        BitmapFactory.Options options = new BitmapFactory.Options(); options.inJustDecodeBounds = true; BitmapFactory.decodeByteArray(bytes,0,bytes.length,options);
                        if (options.outWidth <= 0 || options.outHeight <= 0 || (long)options.outWidth * options.outHeight > 4000000) throw new Exception("Invalid display image");
                        store.image(slide, bytes); handler.post(this::refreshReady);
                        updateStatus("Downloading · " + store.ready(next).length() + "/" + slides.length() + " slides ready");
                    } catch (ScreenApi.AccessEnded e) { throw e; }
                    catch (Exception e) { if (++failures >= 3) break; }
                }
                int readyCount = store.ready(next).length();
                updateStatus(!next.optBoolean("enabled") ? "Playback disabled in admin" : slides.length() == 0 ? "No slides configured for this event yet." : readyCount == slides.length() ? next.getJSONObject("event").getString("name") + " · Ready offline · " + readyCount + " slides" : readyCount + "/" + slides.length() + " slides ready · waiting for downloads");
            } catch (ScreenApi.AccessEnded e) {
                store.clear(); manifest = null; preferences.edit().remove("token").remove("events").apply(); handler.post(() -> { events = new JSONArray(); populateEvents(); stop("Access ended. Pair this screen again."); showHome(); updateStatus("Access ended. Pair this screen again."); });
            } catch (Exception e) {
                updateStatus(homeVisible ? (ScreenStore.playable(manifest) ? "Offline · " + manifest.optJSONObject("event").optString("name") + " is available to play." : "Connect to refresh events and download your slideshow.") : "Connection unavailable · downloaded slides keep playing");
            }
            finally { busy = false; if (!destroyed && foreground) handler.postDelayed(poll, manifest == null ? 5000 : 60000); }
        });
    }
    private void refreshReady() {
        if (destroyed) return;
        ready = store.ready(manifest);
        applyBranding();
        if (homeVisible) return;
        if (ready.length() == 0) { stop(manifest != null && !manifest.optBoolean("enabled") ? "Enable TV playback in Publicity → TV Slideshows." : manifest != null && manifest.optJSONArray("slides").length() == 0 ? "No slides yet. Add general or event advertising in Publicity → TV Slideshows." : "Waiting for downloaded slides."); return; }
        boolean present = false; for (int i=0;i<ready.length();i++) if (ready.optJSONObject(i).optString("id").equals(currentId)) present = true;
        if (current == null || !present) showNext(1);
    }
    private void showNext(int direction) {
        handler.removeCallbacks(advance);
        if (!foreground || homeVisible || ready.length() == 0 || !ScreenStore.playable(manifest)) return;
        index = (index + direction + ready.length()) % ready.length(); JSONObject slide = ready.optJSONObject(index); int requestRevision = ++renderRevision;
        releaseVideo();
        if ("video".equals(slide.optString("kind"))) {
            try {
                currentVideo = new AdvertVideoView(this,store.image(slide),slide.optBoolean("play_sound"),paused,
                    () -> { if (!homeVisible && !paused && requestRevision == renderRevision) showNext(1); },
                    () -> { if (!homeVisible && requestRevision == renderRevision) { updateStatus("Video could not play. Skipping it."); handler.postDelayed(advance,1000); } });
                View previous = current; current = currentVideo; root.addView(current,0,new FrameLayout.LayoutParams(-1,-1));
                if (previous != null) root.removeView(previous);
                message.setVisibility(View.GONE); currentId = slide.optString("id");
            } catch (Exception exception) { if (!paused) handler.postDelayed(advance,1000); }
            return;
        }
        images.execute(() -> {
            Bitmap image = null;
            try { if ("image".equals(slide.getString("kind"))) image = BitmapFactory.decodeFile(store.image(slide).getPath()); } catch (Exception ignored) {}
            Bitmap bitmap = image;
            handler.post(() -> {
                if (destroyed || !foreground || homeVisible || requestRevision != renderRevision || !ScreenStore.playable(manifest)) return;
                if ("image".equals(slide.optString("kind")) && bitmap == null) { if (!paused) handler.postDelayed(advance, 1000); return; }
                FrameLayout frame = new FrameLayout(this);
                if (bitmap != null) {
                    ImageView view = new ImageView(this);
                    view.setScaleType(ImageView.ScaleType.CENTER_CROP);
                    view.setImageBitmap(bitmap);
                    frame.addView(view,new FrameLayout.LayoutParams(-1,-1));
                }
                // Image titles label adverts in admin. Only an explicit body adds an overlay caption.
                boolean hasBody = !slide.isNull("body") && !slide.optString("body").isEmpty();
                if (bitmap == null || hasBody) {
                    LinearLayout caption = new LinearLayout(this); caption.setOrientation(LinearLayout.VERTICAL); caption.setGravity(Gravity.CENTER); caption.setPadding(24,12,24,12);
                    if (!slide.optString("title").isEmpty()) { TextView title = text(slide.optString("title"), bitmap == null ? 38 : 24); title.setGravity(Gravity.CENTER); caption.addView(title); }
                    if (hasBody) { TextView body = text(slide.optString("body"),bitmap == null ? 26 : 18); body.setGravity(Gravity.CENTER); caption.addView(body); }
                    FrameLayout.LayoutParams captionPosition = new FrameLayout.LayoutParams(-1,bitmap == null ? -1 : -2,bitmap == null ? Gravity.CENTER : Gravity.BOTTOM);
                    if (bitmap != null) { captionPosition.bottomMargin = dp(90); caption.setBackgroundColor(0xB307121A); }
                    frame.addView(caption,captionPosition);
                }
                View previous = current; root.addView(frame,0,new FrameLayout.LayoutParams(-1,-1)); frame.setAlpha(0); frame.animate().alpha(1).setDuration(650).start();
                if (previous != null) previous.animate().alpha(0).setDuration(650).withEndAction(() -> root.removeView(previous)).start();
                message.setVisibility(View.GONE); current = frame; currentId = slide.optString("id");
                if (!paused) handler.postDelayed(advance, Math.max(2, Math.min(120,slide.optInt("seconds",2))) * 1000L);
                if (previous == null && panel.getVisibility() == View.VISIBLE && !controlsRequested) { handler.removeCallbacks(hide); handler.postDelayed(hide,12000); }
            });
        });
    }
    private void stop(String value) { releaseVideo(); handler.removeCallbacks(advance); renderRevision++; ready = new JSONArray(); if (current != null) root.removeView(current); current = null; currentId = ""; message.setText(value); message.setVisibility(View.VISIBLE); panel.setVisibility(homeVisible ? View.GONE : View.VISIBLE); if (!homeVisible) firstControl.requestFocusFromTouch(); }
    private void applyBranding() {
        JSONObject logos = manifest == null ? null : manifest.optJSONObject("overlays");
        logo(eventBrand, logos == null ? "" : logos.optString("event"));
        logo(danceproBrand, logos == null ? "" : logos.optString("dancepro"));
        branding.setVisibility(homeVisible ? View.GONE : View.VISIBLE);
    }
    private void logo(ImageView target, String data) {
        try { byte[] bytes = android.util.Base64.decode(data.substring(data.indexOf(',')+1),android.util.Base64.DEFAULT); target.setImageBitmap(BitmapFactory.decodeByteArray(bytes,0,bytes.length)); }
        catch (Exception ignored) { target.setImageDrawable(null); }
    }
    private void releaseVideo() { if (currentVideo != null) { currentVideo.release(); currentVideo = null; } }
    private void togglePause() {
        paused = !paused;
        if (currentVideo != null) currentVideo.pausePlayback(paused);
        else if (paused) handler.removeCallbacks(advance);
        else showNext(1);
    }
    private void hideControls() { controlsRequested = false; handler.removeCallbacks(hide); panel.setVisibility(View.GONE); }
    private void showControls() { controlsRequested = true; handler.removeCallbacks(hide); panel.setVisibility(View.VISIBLE); panel.bringToFront(); firstControl.requestFocusFromTouch(); }
    // Emulator mouse input should provide the same escape route as a remote's Select button.
    @Override public boolean dispatchTouchEvent(MotionEvent event) {
        if (!homeVisible && panel.getVisibility() != View.VISIBLE) {
            if (event.getActionMasked() == MotionEvent.ACTION_UP) showControls();
            return true;
        }
        return super.dispatchTouchEvent(event);
    }
    @Override public void onBackPressed() {
        if (!homeVisible) showHome(); else super.onBackPressed();
    }
    @Override public boolean dispatchKeyEvent(KeyEvent event) {
        int code = event.getKeyCode();
        if (!homeVisible && (code == KeyEvent.KEYCODE_BACK || code == KeyEvent.KEYCODE_ESCAPE || code == KeyEvent.KEYCODE_B || code == KeyEvent.KEYCODE_DEL)) {
            if (event.getAction() == KeyEvent.ACTION_UP) showHome();
            return true;
        }
        if (!homeVisible && (code == KeyEvent.KEYCODE_MENU || code == KeyEvent.KEYCODE_M)) {
            if (event.getAction() == KeyEvent.ACTION_UP) { if (panel.getVisibility() == View.VISIBLE) hideControls(); else showControls(); }
            return true;
        }
        if (!homeVisible && (code == KeyEvent.KEYCODE_SPACE || code == KeyEvent.KEYCODE_MEDIA_PLAY_PAUSE)) {
            if (event.getAction() == KeyEvent.ACTION_UP) togglePause();
            return true;
        }
        if (!homeVisible && panel.getVisibility() != View.VISIBLE) {
            if (code == KeyEvent.KEYCODE_DPAD_CENTER || code == KeyEvent.KEYCODE_ENTER || code == KeyEvent.KEYCODE_NUMPAD_ENTER) {
                if (event.getAction() == KeyEvent.ACTION_UP) showControls(); return true;
            }
            if (code == KeyEvent.KEYCODE_DPAD_RIGHT || code == KeyEvent.KEYCODE_DPAD_LEFT) {
                if (event.getAction() == KeyEvent.ACTION_UP) showNext(code == KeyEvent.KEYCODE_DPAD_RIGHT ? 1 : -1); return true;
            }
        }
        return super.dispatchKeyEvent(event);
    }
}
