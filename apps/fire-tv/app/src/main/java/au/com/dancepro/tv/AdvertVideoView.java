package au.com.dancepro.tv;

import android.content.Context;
import android.graphics.Matrix;
import android.graphics.SurfaceTexture;
import android.media.MediaPlayer;
import android.view.Surface;
import android.view.TextureView;
import java.io.File;

/** Cached local video, proportional screen fill and explicit lifecycle ownership. */
final class AdvertVideoView extends TextureView implements TextureView.SurfaceTextureListener {
    private MediaPlayer player;
    private Surface surface;
    private final File file;
    private final boolean sound;
    private final Runnable completed, failed;
    private boolean paused, prepared, released, finished;

    AdvertVideoView(Context context, File file, boolean sound, boolean paused, Runnable completed, Runnable failed) {
        super(context); this.file = file; this.sound = sound; this.paused = paused; this.completed = completed; this.failed = failed;
        setContentDescription("Video advert"); setSurfaceTextureListener(this);
    }
    @Override public void onSurfaceTextureAvailable(SurfaceTexture texture, int width, int height) {
        if (released) return;
        try {
            surface = new Surface(texture); player = new MediaPlayer(); player.setSurface(surface);
            player.setDataSource(file.getPath()); player.setVolume(sound ? 1f : 0f,sound ? 1f : 0f);
            player.setOnPreparedListener(mp -> { if (released) return; prepared = true; fit(); if (!paused) mp.start(); });
            player.setOnVideoSizeChangedListener((mp,w,h) -> fit());
            player.setOnCompletionListener(mp -> { if (!released && !finished) { finished = true; completed.run(); } });
            player.setOnErrorListener((mp,what,extra) -> { fail(); return true; });
            player.prepareAsync();
            postDelayed(() -> { if (!released && !prepared) fail(); }, 10000);
        } catch (Exception exception) { fail(); }
    }
    private void fit() {
        if (player == null || released || getWidth() == 0 || getHeight() == 0) return;
        int width = player.getVideoWidth(), height = player.getVideoHeight(); if (width == 0 || height == 0) return;
        float scale = Math.max((float)getWidth()/width,(float)getHeight()/height);
        Matrix matrix = new Matrix(); matrix.setScale(width*scale/getWidth(),height*scale/getHeight(),getWidth()/2f,getHeight()/2f); setTransform(matrix);
    }
    void pausePlayback(boolean value) {
        paused = value;
        if (player != null && prepared && !released && !finished) { if (paused) player.pause(); else player.start(); }
    }
    void release() { released = true; if (player != null) { player.release(); player = null; } if (surface != null) { surface.release(); surface = null; } }
    private void fail() { if (!released && !finished) { finished = true; release(); failed.run(); } }
    @Override public void onSurfaceTextureSizeChanged(SurfaceTexture texture,int width,int height) { fit(); }
    @Override public boolean onSurfaceTextureDestroyed(SurfaceTexture texture) { release(); return true; }
    @Override public void onSurfaceTextureUpdated(SurfaceTexture texture) {}
    @Override protected void onDetachedFromWindow() { release(); super.onDetachedFromWindow(); }
}
