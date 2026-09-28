# Publicity TV slideshows

## Agreed scope

Internal Fire OS TV player: select an event, show standard advertising, event
information and current Top Pics, and refresh smoothly as uploader stars sync.
The owner confirmed Fire OS; the physical stick is out for delivery.

## Local pilot implemented — 26 September 2026

- Replaced the TV Slideshows placeholder with the existing competition/concert
  Top Pics event catalogue, event settings, shared advertising and per-event
  slides. Titles/messages produce text slides; optional uploaded images are
  resized to private JPEGs. Slides can be edited, ordered and hidden.
- A disabled-by-default switch controls paired TV playback. Preview remains
  available to administrators without enabling a TV event.
- Shared current Top Pics query; no copied originals or independent selections.
  Playlists interleave advertising, include all information slides even when
  there are few photos, and change as current verified star flags change.
- Admin-approved one-use pairing codes, per-screen names, last contact and
  ready-slide counts; disconnect action. Screen credentials are hashed server
  side and can access only their assigned event.
- Full-screen browser preview and paired player with a public offline shell,
  IndexedDB slide cache, background updates, fades and preserved photo proportions.
- Native Fire OS app in `apps/fire-tv`: pairing, remote controls, app-private
  atomic cache, background downloads, saved event on reopen and foreground
  keep-awake request. An Android debug APK can be built locally.
- 200 MB cache and 24-hour offline window. Failed downloads do not replace
  playable content. New or changed files download once; revoked/expired access
  stops playback. Offline removals take effect only after reconnecting/expiry.

## Verification

- New migration applied locally; rollback reviewed in reverse dependency order.
- Focused Laravel suite: 13 tests / 125 assertions passed before final cleanup.
- Browser checks: management index, event settings and live PDC preview (35 photos).
- All four automated browser tests passed: offline restart, image cache reuse,
  incremental updates, unavailable images, removals, disablement and revoked/expired
  access. Actual image bytes survived offline reload and were not downloaded twice.
- Android `assembleDebug lintDebug` passed; test APK available at
  `apps/fire-tv/dist/DancePro-TV-debug.apk`. No physical device test yet.
- Installed and opened the actual APK in the local Android TV API 31 arm64
  emulator. Paired `Laptop TV preview` to PDC, downloaded all 35 slides, and
  verified native photo playback. This exposed an Android date-parser issue
  with the server's `+08:00` offline expiry; switched to `OffsetDateTime` and
  rebuilt/linted successfully. Fire OS hardware acceptance remains outstanding.
- Disabled the emulator's Wi-Fi and force-stopped/reopened the app: cached
  photos displayed with the connection-unavailable status. Restored Wi-Fi.
- Full Sail suite passed: **751 tests / 6,913 assertions**. Scoped Pint and
  JavaScript syntax checks passed; new routes registered and whitespace checked.
- Subsequent full-suite run after the native timezone fix: **750/751 passed**.
  `LocalDevelopmentSeederTest::test_seeder_creates_repeatable_linked_local_data`
  reported collection/asset counts increasing on its second seed (72/281 to
  80/321). No seeder or Laravel application code changed during emulator setup.
  The isolated `LocalDevelopmentSeederTest` rerun passed (2 tests / 57
  assertions); the intermittent full-suite seeder discrepancy remains recorded.

## Remaining / next step

- Install the debug APK on the delivered Fire OS stick. Verify remote focus,
  aspect ratios, weak Wi-Fi, offline restart, power/sleep behaviour and a full
  event-day run. No hardware verification has been claimed.
- Native homepage event selection now uses all explicitly TV-enabled events;
  admin pairing authorises the crew device once.
- The existing local photo derivative provider is reused. Production private
  storage delivery, HTTPS hosting, deployment and release signing are not done.
- Standard advertising starts empty. Add the actual artwork/messages in admin.
- Video adverts, scheduling, automatic launch after device reboot and public
  Appstore distribution are later work.

See [ADR-0053](../decisions/ADR-0053-Publicity-TV-Playback.md) and
[Fire OS app instructions](../../apps/fire-tv/README.md).

## Native homepage and branding — 26 September 2026

- Homepage uses the supplied `DancePro TV Icon.jpg` artwork, with a dropdown of
  TV-enabled competition/concert events sorted newest date first and a Play
  slideshow button. Change event returns to the homepage without re-pairing.
- The TV management catalogue includes events with no photos, so their adverts
  can be prepared and enabled before uploads. General and event adverts remain
  in the playlist when no Top Pics exist.
- New authenticated catalogue and selection endpoints allow paired crew devices
  to choose only TV-enabled events. Image requests remain restricted to the
  selected event; unpaired/revoked devices cannot list or select events.
- Separate private 1920px unwatermarked derivatives use the existing verified
  original, orientation/memory checks and atomic writes. Customer preview cache
  keys and watermarks remain unchanged. A new version marker prevents native or
  browser playback from reusing old watermarked TV caches.
- Supplied artwork is bundled unchanged, referenced by the native homepage,
  launcher icon and proportion-preserving TV launcher banner.
- Focused API tests passed: 7 tests / 85 assertions. All 4 browser offline/cache
  checks passed. Native build and Android lint passed with no lint issues.
- Visually verified homepage and unwatermarked native photo playback in the
  Android TV emulator. Physical Fire OS testing is still pending.
- Verified newest-first ordering and switching from PDC to the EK concert
  without re-pairing. Disabled emulator Wi-Fi and started the cached concert
  from the homepage successfully; restored Wi-Fi afterwards.
- Final full Sail suite passed: **753 tests / 6,939 assertions**. An earlier
  overlapping test invocation invalidated its run by resetting the shared test
  database; the final suite ran alone. Route listing, scoped Pint and whitespace
  checks passed.

## Remote controls, timings and automatic event advert — 26 September 2026

- Back/Escape returns to the native event menu. Menu/M or Select/Enter opens
  playback controls, with visible button focus; arrows and Enter operate them.
  Space/Play-Pause pauses. The emulator side toolbar is separate from the app.
- Defaults are 2 seconds for photos, 5 for new general adverts and 8 for new
  event adverts. The previous photo default is migrated to 2; other custom values
  remain. Migration applied locally; rollback changes the default without
  overwriting saved durations.
- Automatic event advert uses the current name/date/logo and a signed scan-to-add
  QR when the relevant gallery code exists. Without a code, the advert prompts
  attendees to ask the crew. The generated image is cached by content version.
  Existing custom image/message slides remain available. A multi-template editor
  is not included yet.
- QR admission survives sign-in, email verification and onboarding, then adds
  competition gallery or studio photo access idempotently and records account
  history. It does not grant purchases, video access or marketing consent.
  Invalid/tampered links, changed access codes, disabled TV and cancelled events
  are rejected. Real phone scans require a reachable server URL; localhost is
  explicitly identified as preview-only in admin.
- Both native and browser playback show the event logo bottom left and supplied
  outline DancePro logo bottom right, with cached manifest artwork for offline use.
- Verified native photo overlays, generated advert, Back/Escape, Menu and arrow
  focus. Decoded the rendered PDC QR successfully using macOS Vision. The PDC
  demo currently inherits its stored competition-contact logo; artwork is not
  guessed from the event name.
- Full Sail suite passed: **757 tests / 6,979 assertions**. All **4 browser
  playback/cache tests** passed using installed Chrome (the default Playwright
  browser binary was unavailable). Android build and lint passed with no issues.
  Scoped Pint, route listing (18 Publicity routes) and whitespace checks passed.
- Updated APK installed in the laptop emulator. Actual Fire OS remote/hardware,
  public hosting and phone-to-live-server scan acceptance remain outstanding.


## Independent competition and studio sharing — 26 September 2026

- Added Publicity → QR codes & links with searchable competition/studio lists,
  copy-link controls, QR PNG downloads and direct access-code settings links.
- Competition QR/link is the same URL used in the generated TV advert. Studio
  links add studio photo-gallery access without requiring any concert or slideshow.
- Removed TV enablement from admission checks. Changed gallery codes, cancelled
  events and inactive studios still invalidate links; signup and verification
  retain the selection and grant access once, recording account history.
- Concerts no longer appear in the TV catalogue or TV management event list.
  Existing concert playback is disabled on the next successful feed refresh.
- Verified browser search, studio/competition tabs, QR rendering and Copy link.
  Localhost sharing is marked preview-only; public phone access requires hosting.
- Final full Sail suite passed: **761 tests / 7,048 assertions**. Scoped Pint,
  full route listing and whitespace checks passed. One legacy navigation test
  was updated to reflect the removal of concert TV selection; no application
  failures remained. No APK rebuild or database migration was needed.


## Separate standard advertising — 26 September 2026

- Standard advertising now has a Competition / Concert segmented switch. Each
  category lists, creates and edits its own adverts; the selected query keeps the
  section open when returning from save. New standard adverts still default to 5s.
- Added `publicity_slides.audience`; existing standard adverts default to competition
  to preserve the established TV pilot. Event-specific adverts remain tied to their
  event. A shared query scopes both playlist entries and image delivery, so concert
  adverts cannot appear in or be fetched through a competition playlist.
- Concert advertising can be prepared separately; concert TV playback remains
  disabled as requested. No native app rebuild or route changes were required.
- Migration applied locally. Its down method drops only the audience column,
  retaining slide records and files; rollback loses category separation and should
  be paired with the previous application version.
- Focused TV tests passed (12 tests / 151 assertions). Visually checked the segmented
  switch and the concert category in the admin browser.
- Full Sail suite passed: **762 tests / 7,071 assertions**. Scoped Pint and
  whitespace checks passed. Next: physical Fire OS testing when the stick arrives.


## Six competition QR adverts — 26 September 2026

- Imported the owner’s six PNG designs, normalized to 1920 × 1080. Recorded source
  hashes and left/right QR rectangles in the artwork README. Original files untouched.
- Event admission QR replaces only the outlined guide. No extra text or baked logos;
  existing event and DancePro corner overlays remain. Each advert plays for 8 seconds.
- Automatically included for competitions with a configured access code, even with
  no Top Pics. Missing codes retain the existing generic event message. Standard
  advertising and manually added event slides remain independent.
- Content-addressed PNGs are approximately 150–540 KB each in the local example.
  Changed artwork or access URL changes the playlist version; paired apps download
  them through existing authorised endpoints and retain offline playback.
- Added all six previews to the event’s TV Slideshows management page. No migration,
  new route or APK rebuild required.
- Focused TV suite passed: 13 tests / 219 assertions. Coverage verifies all six
  templates, image dimensions/MIME, 8-second timing, no-photo playlists, exact
  pixel preservation outside QR boxes, cache reuse, changed-code refresh, missing-code
  fallback, event isolation and customer denial. All six actual rendered QRs decoded
  to the exact intended event admission URL using macOS Vision.
- Visually inspected both left/right rendered artwork. Live admin browser verification
  was interrupted by an expired login; the page renders successfully in feature tests.
- Full Sail suite passed: **763 tests / 7,139 assertions**. Scoped Pint and
  whitespace checks passed. Physical Fire OS playback remains a device acceptance check.


## DancePro standard advert — 26 September 2026

- Imported the supplied DancePro STicker.jpg through the existing advertising
  service as the sole active competition general advert, at 5 seconds. There
  were no existing database standard adverts to hide; all six event QR templates
  remain included separately. No concert advertising settings were changed.
- Preserved the original source in resources/images/publicity/standard with its
  hash and import notes. The saved 1920 × 1080 TV image is approximately 124 KB.
- Verified one active standard advert, successful image resolution and inclusion
  in the event playlist at 5 seconds, alongside all six QR templates.
- This is a content change using existing management controls; no application
  code, routes, migrations or APK changes. Future adverts can be added or replaced
  in the same Standard advertising panel.
- Full Sail suite passed: **763 tests / 7,139 assertions**; whitespace checks passed.
- Confirmed PDC REALISTIC DEMO remains enabled and the laptop preview is assigned
  to it. Prepared and decoded all 42 playlist images: 35 Top Pics at 2 seconds,
  one general advert at 5 seconds, six QR adverts at 8 seconds. Advertising interval
  remains one advert per five Top Pics, rotating through all seven adverts.


## Native navigation and template downloads — 26 September 2026

- Investigated the installed 0.3.0 Android TV emulator app. Escape delivered by
  ADB returned home, but touching/clicking the slideshow did not reveal controls.
  Added click/hover to reveal, a Back to events button, B/Backspace fallbacks,
  and the Android activity Back fallback. Manually opened controls remain visible
  while in use. No browser player changes were made.
- Found a separate native slide-ID allowlist rejecting `template-{event}-1..6`.
  Corrected it and verified the emulator now reports 42/42 PDC slides ready offline.
  Earlier notes claiming no APK update was needed for templates were incomplete:
  0.3.0 needed this compatibility fix. Future content changes work on 0.3.1.
- Built/linted 0.3.1 and installed it over the emulator app, preserving pairing and
  cache. Updated the distributable debug APK. Physical Fire OS testing remains pending.
- Added a repeatable ADB navigation smoke test. Full Sail suite passed:
  **763 tests / 7,139 assertions**.
- Emulator smoke test passed all seven navigation scenarios, including mouse
  toolbar persistence beyond 12 seconds, direct keyboard/Back routes, Select/Enter
  and Menu/Hide. Left the updated app on its event-selection homepage.


## Proportional full-screen photos — 26 September 2026

- Removed image-slide padding in the native player and the browser preview's
  image-only frame padding/height cap. Both fit photos to the display without
  stretching or cropping; unused space remains where aspect ratios differ.
- Bumped native version to 0.3.2 and the browser shell cache to v4. Built/linted
  and installed the updated APK over the emulator app, preserving its data.
- Visually verified a PDC photo on the 1920 × 1080 emulator: full display height,
  correct proportions and side bars. Existing corner overlays remain in place.
- All six browser playback tests passed, including landscape and portrait sizing.
- Full Sail suite passed: **763 tests / 7,139 assertions**. Git whitespace checks passed.


## Edge-to-edge playback clarification — 26 September 2026

- User clarified that no background bars should remain. Supersedes the earlier
  proportional-fit behaviour: native CENTER_CROP and browser object-fit: cover
  now fill the display while preserving proportions and cropping overflow.
- Removed the title-only strip under image adverts. Explicit body captions overlay
  images without taking space away; text-only slides retain their normal layout.
- Native version 0.3.3; browser shell cache v5. Build and Android lint passed;
  installed APK over the emulator app, preserving pairing/downloads.
- Visually confirmed a Top Pic, the general advert and a QR advert fill the full
  1920 × 1080 emulator display. No surrounding bars or advert title strip remain.
- Eight browser tests passed, covering portrait/landscape fill, image-only adverts,
  overlay captions and existing offline playback behaviour.
- Full Sail suite passed: **763 tests / 7,139 assertions**; whitespace checks passed.


## Video adverts — 26 September 2026

- Added video uploads to standard/event adverts with existing audience grouping.
  MP4 compatibility, size, duration and full-decode validation run before saving.
- Native 0.4.0 and browser support silent-by-default, full-length playback with
  proportional screen fill, logo overlays, pause/resume and skip.
- Complete video downloads are hash/length checked and privately cached. Raised
  screen cache limit to 500 MB; incomplete files never enter playback.
- Automatic playback hides controls; deliberate crew input opens them. Removed
  hover-triggered native controls and browser pointer-movement controls.
- Migration applied; down removes only the five added video metadata columns.
- Focused backend suite: 15 tests / 249 assertions passed. Twelve browser playback
  tests and native build/lint passed.
- Full Sail suite passed: **765 tests / 7,170 assertions**. Publicity routes listed
  successfully; Pint and whitespace checks passed.
- Installed 0.4.0 in the emulator. Temporary synthetic advert played at full-screen
  size, with hidden controls and logo overlays. Two paused screenshots were byte-
  identical; resumed screenshot changed; completion returned to photos. No runtime
  crash was logged. Removed the temporary advert and refreshed the cached playlist.
- Physical Fire TV testing and testing the user's actual video exports remain next;
  no customer-facing video access or real advert content was changed.

## Shared general advert storage — 26 September 2026

- Native 0.4.1 keeps general photo/video adverts when switching events and through
  app restarts. Event-specific media is pruned independently. Disabled playback
  retains the current general library; unpairing/revocation still clears it.
- Manifest exposes the current standard library and media-content versions.
  Label, timing and sound changes reuse stored media. Replacement downloads a new
  file; hiding/removing an advert removes its cached copy on the next update.
- Browser shell v7 uses the same content identity and retention rules. The existing
  500 MB capacity and 24-hour offline playback authorization remain in place.
- Full Sail suite passed: **766 tests / 7,180 assertions**. Fourteen browser tests
  passed, including general photo/video reuse, restart, settings changes,
  replacement and removal. Native build and lint passed; Pint/whitespace passed.
- Installed 0.4.1 in the emulator. General image and synthetic general video kept
  identical size, modification time and inode through restart and a confirmed
  switch from PDC to Express. Returning to PDC reused the image again.
- Removed the synthetic advert and confirmed its cached video was pruned. Restored
  Express's original TV settings and left the emulator playing the original PDC
  slideshow. Physical Fire TV verification remains pending.

## Supplied general video adverts — 26 September 2026

- Imported EDC423 Highlights, ELI423 Highlights, UDC823 REEL, UDC425, Elite 425 and
  EDC525 Highlight as enabled general Competition adverts, silent by default.
- Created 1920 × 1080 H.264/AAC TV copies from the supplied 4K files, preserving
  frame rates and complete clips. Originals remain untouched. The six prepared
  copies total approximately 155 MiB and pass the normal full-decode validation.
- Retained the existing DancePro general photo advert and event QR adverts.
  Videos use existing general-ad rotation, play to completion, and share the
  device's persistent general-advert storage. No application code changed.
- Full Sail suite passed: **766 tests / 7,180 assertions**.
- Emulator verification: all six private cached files match their manifest byte
  counts and SHA-256 hashes; all six have sound disabled. Observed actual supplied
  video playback with controls hidden and the existing corner overlays. The PDC
  slideshow remains running with the new adverts available.

## Even video spacing — 26 September 2026

- Video adverts now appear once each, distributed across equal groups of Top Pics,
  independently of the regular image/QR advert interval. Spacing recalculates as
  new photos arrive. Avoids a video immediately before a regular advert boundary
  where there is room; no-photo galleries still play their advertising loop.
- Updated the playback-settings label/help and TV documentation to explain the
  separate rules. Server-side manifest change applies to existing TV/browser
  players and does not change cached video identities.
- Verified the emulator's actual PDC playlist: 35 photos; videos after photos
  3, 9, 14, 21, 27 and 33; regular image/QR adverts remain at five-photo intervals.
- Added coverage for 35-photo and growing 60-photo galleries, plus empty and
  one-photo galleries. Each video remains included exactly once per loop.
- Full Sail verification completed: **766/767 tests passed**, including all TV
  tests and the new spacing cases. One unrelated failure occurred in
  `LocalDevelopmentSeederTest::test_seeder_creates_repeatable_linked_local_data`:
  a repeated seed increased collection/asset counts (72/281 to 80/321). No seeder
  code was changed for this task. Pint and whitespace checks passed.
- Isolated follow-up passed: **19 tests / 325 assertions**, covering the demo
  seeder and all TV slideshow tests. The full-suite seeder failure did not repeat;
  recorded as an intermittent verification limitation, not a TV-spacing failure.

## Additional general video adverts — 26 September 2026

- Added TDE523 REEL, RAD623 REEL, S2S Highlight Reel 2.0, SSDC823 REEL, PDC823 REEL
  and UDC824 Highlight Reel as enabled, silent general Competition adverts.
- All six supplied files already meet the 1080p H.264/AAC compatibility limits
  and passed full decoding validation. Imported without re-encoding; original
  files remain untouched. Existing six videos and the DancePro photo ad remain.
- General video library now contains 12 videos totalling 341,237,705 bytes
  (approximately 325 MiB), below the shared 500 MB device cache limit before
  event-specific media. Existing even-spacing and persistent-storage rules apply.
- Verified all 12 cached video files in the emulator against their expected byte
  counts and SHA-256 hashes. All are configured silent; observed full-screen
  video playback with no visible controls and the existing logo overlays.
- Full Sail suite passed: **767 tests / 7,189 assertions**. Whitespace checks
  passed. No application code or playback rules changed for this content import.

## Physical Fire TV installation — 27–28 September 2026

- Installed the existing 0.4.1 debug APK on the owner's Fire OS stick using
  authorised network ADB and opened the native app successfully.
- Paired the device as **Fire TV Stick** to the existing PDC realistic demo
  through the application pairing service; verified that the event appeared
  in the native event picker after refresh. The local server uses the Mac's
  LAN address rather than localhost or the emulator gateway.
- Owner confirmed that moving the app into the Fire TV home-screen app row works.
- These checks establish installation, pairing, event discovery and launcher
  access only. Full remote/playback, offline recovery, sleep and day-long
  hardware acceptance checks remain required. No production deployment or
  Appstore publication was performed.

## Integrated commit verification — 28 September 2026

- Full Sail suite: 892 tests passed, 8,923 assertions.
- Mac uploader: 93 Swift tests passed; programme extraction: nine Python tests passed in Sail.
- Fire TV `assembleDebug lintDebug` and Sail frontend production build passed.
- All 14 TV browser tests passed using installed Mac Chrome in an isolated test
  profile. Sail's Chromium lacks H.264 decoding (confirmed with a direct fixture
  decode); its four video-test failures were resolved by testing with the supported
  browser runtime, without changing or weakening tests.
- Route registration passed (647 routes); all local migrations are applied.
  New migration rollback methods were reviewed without rolling back local data.
- Pint and whitespace checks passed. Temporary review media, screenshots and
  generated output remain local and are excluded from version control.
