# DancePro TV — Fire OS pilot

Native Android/Fire OS app, with no Google Play Services dependency. Targets API
35 and supports API 26+. Install on the owner's Fire OS stick for the hardware
pilot; this is not a Vega OS package. No Appstore publication or production
signing has been performed.

## Install the local test APK

The verified debug APK is copied to `dist/DancePro-TV-debug.apk` after building.
Enable developer options and ADB debugging on the Fire TV, connect the Mac and
stick to the same trusted network, then use Android platform-tools:

```sh
adb connect FIRE_TV_IP:5555
adb install -r dist/DancePro-TV-debug.apk
```

Open **DancePro TV** on the stick. Choose **Pair screen** and enter the DancePro
server origin. Production needs HTTPS. The debug build permits HTTP for local
LAN tests. `http://localhost` on a stick means the stick itself: use the Mac's
LAN IP and exposed Sail port for local testing, e.g. `http://192.168.1.25`.

Enter the displayed six-digit code in **Publicity → TV Slideshows → event →
Pair a screen** in admin, name the screen, and enable TV playback. The screen
opens a homepage with a date-descending dropdown of all events enabled for TV.
Crew choose an event and press **Play slideshow**; use **Change event** in the
playback controls to return home. Pairing is needed once, not for each event.
Events without Top Pics still play enabled global and event advertisements.
Prepare and enable those events in admin even before photos are uploaded.

## Playback

- Verified current-plan Top Pics, standard advertising and event information.
- Pictures fill the screen proportionally with central cropping. TV derivatives have no watermark;
  customer gallery previews retain their existing watermark protection.
- Changes checked every minute; only new media versions download.
- Failed or partial downloads never enter playback. Ready media keeps playing.
- App-private cache, 500 MB limit; last complete manifest survives restarts.
- Offline playback expires 24 hours after the last successful manifest update.
- Disabling or disconnecting a screen takes effect on its next successful
  connection. Offline revocation cannot be immediate.
- **Back / Escape / B / Backspace** returns to the event menu. **Menu / M** shows or hides
  controls; clicking the slideshow or Select / Enter opens them when hidden.
  Manually opened controls stay visible; **Back to events** returns to the homepage. Arrows move focus and
  Select / Enter activates the focused button. With controls hidden, left/right
  moves between slides. **Space / Play-Pause** pauses playback. Home leaves the app.
- Opens the event-selection homepage on launch. The last downloaded event can
  be selected and played offline within its access window. Switching to an
  uncached event needs a connection. It does not promise automatic launch
  after a device reboot or override the TV's own power controls.
- No account passwords are entered on the TV. The scoped screen credential is
  stored privately; app backups and device transfers are excluded.

## Laptop preview

The actual debug APK also runs in an Android TV emulator. The local Apple
Silicon preview uses the `DanceProTV` AVD (Android TV API 31, arm64), with
SDK/AVD files under `/tmp/dancepro-tv-build`. These temporary files may need
reinstalling if the Mac clears its temporary storage.

Use `http://10.0.2.2` as the server address to reach Sail on the Mac. Pair it
through the same admin controls as a physical TV. The local screen named
**Laptop TV preview** is paired to the PDC realistic demo. Click the emulator
slideshow to reveal its controls, then click **Back to events**. Keyboard **B**
is an alternative when the emulator intercepts Escape. Arrow keys navigate and
Enter selects the focused control. The
emulator's Back control (or keyboard Escape) returns to the event menu. The
side toolbar belongs to the emulator; the on-screen bottom bar is DancePro's
remote-operated playback menu.

This runs the native app, but Android TV emulation does not verify Fire OS
hardware behaviour. The delivered stick still needs the acceptance checks below.

## Rebuild

Install JDK 17 and Android SDK platform 35 / build-tools 35.0.0. Set `JAVA_HOME`
and `ANDROID_HOME` to their installations, then from `apps/fire-tv`:

```sh
./gradlew assembleDebug lintDebug
mkdir -p dist
cp app/build/outputs/apk/debug/app-debug.apk dist/DancePro-TV-debug.apk
```

Gradle 8.11.1 and Android plugin 8.9.2 are pinned. Initial build needs internet.
Generated build files, local SDK paths, signing keys and APKs are ignored.

## Hardware acceptance still required

Install on the delivered stick and check remote focus, HDMI output, text size,
portrait/landscape photos, sleep behaviour, launch after restart, weak Wi-Fi,
network loss/recovery and a full event day. Compilation and browser testing are
not substitutes for those checks. Release signing/Appstore distribution and
production storage delivery remain separate rollout work.

## Timing, advertising and branding

- Photos default to 2 seconds, new general adverts to 5, and new event adverts
  to 8. Existing customised timings are retained; the old 8-second photo default
  is migrated to 2. Each generated event advert uses 8 seconds.
- Six supplied competition templates automatically receive the selected event’s
  scan-to-add QR and play for 8 seconds each, including when Top Pics are empty.
  Only the marked QR box is changed. Logos remain runtime corner overlays.
  Without a gallery access code, a generic event message plays instead.
  Templates are cached lossless 1920 × 1080 PNGs and refresh when the artwork or
  event link changes. Native app 0.3.1 or later supports these template IDs; 0.3.0 rejects them.
  After upgrading once, future artwork/link changes do not require an APK update.
  Additional adverts can still be added through the standard/event slide forms;
  a visual template designer is not implemented.
- A small event logo sits at bottom left and the supplied outline DancePro
  artwork at bottom right. Display-sized logos travel with the cached manifest,
  so both remain visible offline. Customer watermarks are never added to TV photos.
- The automatic TV QR link is equivalent to the competition access code. It adds access once the customer signs
  in and completes normal verification/onboarding. It never grants purchases,
  video entitlements or marketing consent. Account history records the addition.
- Cancelling the competition or changing its gallery code invalidates the old QR.
  Disabling TV playback does not invalidate gallery sharing links. Independent concert QR links add the studio, matching current
  studio-based photo access. New dancer details can be added later for personalised
  competition recommendations.
- `APP_URL` must be a customer-reachable HTTPS URL for live phone scans. Localhost
  is only suitable for laptop preview; the TV's emulator server address is not
  embedded in QR codes. The admin page explains when access is not yet configured.

### Sharing without a TV

Admin → Media → Publicity → QR codes & links provides competition and studio
sharing links and downloadable QR images. Competition links match the automatic
TV advert; studio links need no event slideshow. The TV event picker now contains
only enabled, non-cancelled competitions, including events without photos. Concert
photo links remain available in the separate studio sharing list. This server-side
catalogue change does not require an APK rebuild; online screens refresh it.


## Navigation regression check

On a paired Android TV emulator with a selected, cached event, run:

```sh
python3 apps/fire-tv/tests/navigation-smoke.py --adb /path/to/sdk/platform-tools/adb
```

This exercises Escape, remote Back, B, Backspace, mouse controls, toolbar persistence,
Select/Enter, Menu/M and Hide. It leaves the app on its event homepage. It uses
Android input controls and temporarily starts/stops the selected slideshow.

Version 0.3.1 also accepts the six competition template image IDs. Verified PDC
has all 42 slides downloaded, including those six adverts, on the laptop emulator.


## Photo sizing

Version 0.3.3 fills the display edge to edge while preserving image proportions.
Any overflow is cropped centrally, so portrait and non-widescreen photos lose
some of their edges instead of leaving background bars. The browser preview uses
the same behaviour. Advert titles are admin labels and do not create a strip below
image slides. An explicit advert body is displayed as an overlay without shrinking
the artwork. Text-only slides retain their normal layout.


## Video adverts (0.4.0)

In Publicity → TV Slideshows, add a standard or event slide and upload a video
instead of an image. Competition/Concert audiences still apply. Use MP4 with
H.264 8-bit video and optional mono/stereo AAC audio, up to 1080p, 60 fps,
120 seconds and 100 MB. The server requires ffprobe and ffmpeg and validates
metadata and decoding; it does not transcode uploads. Invalid files show an error.
Replacing a video's image or an image's video switches the slide type. Hiding the
slide removes it on the next successful screen update.

Video adverts are spaced evenly through the Top Pics and play to completion,
ignoring the image/text seconds field and the regular image/QR advert interval.
The playlist recalculates spacing as photos arrive, placing videos around the
middle of equally sized photo groups. It avoids the next regular advert boundary
when possible. With no photos, videos still play in the advertising-only loop. They default to silent; Play audio is an
optional per-advert setting. Browser autoplay restrictions may force silent
playback even when sound is enabled. Logos remain separate corner overlays.

Players cache the complete video before playback, verify its byte count and
SHA-256, and reject incomplete downloads. Native downloads stream to a temporary
file; successful files are published atomically. Cached videos play offline within
the same 24-hour access window. Cache limit is now 500 MB. Download retries begin
on the next update; byte-range resumption is not implemented.

No video controls or progress bar appear during playback. Crew deliberately open
the existing controls with Select/Enter/Menu or a click; mouse movement alone does
not reveal controls. Pause/resume stays within the video; Next skips it, and Back
returns to event selection. Native 0.4.0 is required for video support.


## Shared general advert storage (0.4.1)

General photo and video adverts are retained in private app storage across event
changes and app/device restarts. Switching events clears event-specific assets
but preserves the current general library. Pausing an event's playback retains
that library too. Media content hashes provide stable cache identities: edits to
titles, timing or sound settings do not download the media again. Replaced files
are downloaded on the next update, and hidden/removed adverts are pruned.

The shared library counts towards the 500 MB storage limit and is never evicted
to make room for event photos. Full storage prevents new downloads until content
is reduced. Unpairing, revoked access, clearing app data or uninstalling removes
the library. Existing 24-hour offline playback authorization still applies;
expiry stops playback without deleting downloaded files. The browser player uses
the same retention rules, subject to browser storage availability/eviction.
